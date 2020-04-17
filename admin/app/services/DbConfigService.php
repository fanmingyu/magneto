<?php
namespace Group\Magneto\Admin\Service;
use Group\Common\Library\AesLib;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;

class DbConfigService
{

    private static $connections = array();

    //库类型为主库
    const DB_TYPE_MASTER = 'master';
    //库类型为从库
    const DB_TYPE_SLAVE = 'slave';
    //库类型为备份库
    const DB_TYPE_BACKUP = 'backup';
    //密码加解密的key
    const PASSWORD_KEY = 'password';

    public static $dbTypes = array(
        self::DB_TYPE_MASTER,
        self::DB_TYPE_BACKUP,
        self::DB_TYPE_SLAVE,
    );

    /**
     * 获取所有库的信息
     */
    public static function getAll()
    {
        return getDI()->get('db_magneto')->fetchAll("SELECT * FROM db_config ", \Phalcon\Db::FETCH_ASSOC);
    }

    /**
     * 获取别名列表
     */
    public static function getAliases()
    {
        $result = getDI()->get('db_magneto')->fetchAll("SELECT * FROM db_config", \Phalcon\Db::FETCH_ASSOC);
        $alias = array();
        foreach ($result as $item){
            $alias[] = $item['alias'];
        }
        return $alias;
    }

    /**
     * 根据类型获取别名列表
     */
    public static function getAliasesByType($type)
    {
        $result = getDI()->get('db_magneto')->fetchAll("SELECT * FROM db_config WHERE type ='{$type}'", \Phalcon\Db::FETCH_ASSOC);
        $alias = array();
        foreach ($result as $item){
            $alias[] = $item['alias'];
        }
        return $alias;
    }

    /**
     * 根据id获取库的别名
     */
    public static function getAliasById($id)
    {
        $result = getDI()->get('db_magneto')->fetchOne("SELECT * FROM db_config WHERE id = '{$id}'", \Phalcon\Db::FETCH_ASSOC);
        if(empty($result)){
            throw new \Exception("id为{$id}的库的配置不存在");
        }
        return $result['alias'];
    }

    /**
     * 根据别名获取id
     */
    public static function getIdByAlias($alias)
    {
        $result = getDI()->get('db_magneto')->fetchOne("SELECT * FROM db_config WHERE alias = '$alias'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($result)) {
            throw new \Exception("别名为{$alias}的库的配置不存在");
        }
        return $result['id'];
    }

    /**
     * 根据别名获取数据库信息
     */
    public static function getDbInfo($alias)
    {
        return getDI()->get('db_magneto')->fetchOne("SELECT * FROM db_config WHERE alias='{$alias}'", \Phalcon\Db::FETCH_ASSOC);
    }

    /**
     * 添加库信息
     */
    public static function addDbConfig($data)
    {
        if (empty($data['dbname'])) {
            throw new \Exception("库名不能为空");
        }
        if (empty($data['username'])) {
            throw new \Exception("用户名不能为空");
        }
        if (empty($data['host'])) {
            throw new \Exception("host不能为空");
        }
        if (empty($data['alias'])) {
            throw new \Exception("别名不能为空");
        }

        $data['port'] = isset($data['port']) ? $data['port'] : 3306;
        $data['type'] = isset($data['type']) ? $data['type'] : self::DB_TYPE_MASTER;
        $data['create_time'] = time();
        $data['update_time'] = time();
        //对密码加密处理再存入
        $data['password'] = self::encode($data['password']);
        try {
            getDI()->get('db_magneto')->insert('db_config', $data, array_keys($data));
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                throw new \Exception("别名为{$data['alias']}的库已经存在");
            }
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * 给密码进行加密
     */
    public static function encode($password)
    {
        //return AesLib::encode($password,self::PASSWORD_KEY);
        return AesLib::EncodeWithOpenssl($password,self::PASSWORD_KEY);
    }

    /**
     * 获取解密密码
     */
    public static function decode($password)
    {
        //return AesLib::decode($password,self::PASSWORD_KEY);
        return AesLib::DecodeWithOpenssl($password,self::PASSWORD_KEY);
    }

    /**
     * 删除库的信息
     */
    public static function deleteDbConfig($id)
    {
        return getDI()->get('db_magneto')->delete('db_config'," id = {$id}");
    }

    /**
     * 修改库信息
     */
    public static function updateDbConfig($id, $data)
    {
        if(empty($data['dbname'])){
            throw new \Exception("库名不能为空");
        }
        if(empty($data['username'])){
            throw new \Exception("用户名不能为空");
        }
        if(empty($data['host'])){
            throw new \Exception("host不能为空");
        }
        if(empty($data['alias'])){
            throw new \Exception("别名不能为空");
        }
        $data['update_time'] = time();

        try {
            getDI()->get('db_magneto')->update('db_config', array_keys($data), array_values($data), "id='{$id}'");
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                throw new \Exception("别名为{$data['alias']}的库已经存在");
            }
        }
    }

    /**
     * 根据别名获取数据库的连接
     */
    public static function getDbConnection($alias)
    {
        if (empty(self::$connections[$alias])) {
            $config = self::getDbInfo($alias);
            $config['password'] = self::decode($config['password']);
            $adapter = new DbAdapter($config);
            self::$connections[$alias] = $adapter;
        }
        return self::$connections[$alias];
    }

    /**
     * 根据条件和表名获取最小的id
     */
    public static function getMinIdByCondition($connection, $tableName, $condition)
    {
        $sql = "SELECT id FROM {$tableName} WHERE {$condition} ORDER BY id ASC LIMIT 1";
        $minId = (int) current($connection->fetchOne($sql));
        return $minId;
    }

}
