<?php

namespace Group\Magneto\Admin\Service;

class DbtoolService
{

    /**
     * 获取表结构
     */
    public static function getStructure($db, $table)
    {
        $dbs = DbConfigService::getAliases();
        if (!in_array($db,$dbs)) {
            throw new \Exception('db配置不存在');
        }

        if (strpos($table, '`') || strpos($table, '.')) {
            throw new \Exception('表名格式错误');
        }

        try {
            $result = DbConfigService::getDbConnection($db)->fetchAll("SHOW CREATE TABLE `{$table}`");
        } catch (\Exception $e) {
            throw new \Exception("{$table}表不存在");
        }
        return isset($result[0]['Create Table']) ? $result[0]['Create Table'] : '';
    }

    /**
     * 获取表列表
     */
    public static function getTableList($db)
    {
        $dbs = DbConfigService::getAliases();
        if (!in_array($db,$dbs)) {
            throw new \Exception('db配置不存在');
        }

        $tables = array();
        $result = DbConfigService::getDbConnection($db)->fetchAll("SHOW TABLES", \Phalcon\Db::FETCH_NUM);
        foreach ($result as $item) {
            $tables[$item[0]] = $item[0];
        }
        return $tables;
    }

}
