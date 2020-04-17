<?php
namespace Group\Magneto\Admin\Controllers;
use Group\Magneto\Admin\Service\DbConfigService;

class DbconfigController extends BaseController
{

    public function indexAction()
    {
        $result = DbConfigService::getAll();
        foreach ($result as $key => $value) {
            $result[$key]['create_date']= date("Y-m-d H:i",intval($value['create_time']));
            $result[$key]['update_date'] = $value['update_time'] > 0 ? date("Y-m-d H:i",intval($value['update_time'])) : '-';
        }
        $this->view->setVar('dbConfig', $result);
    }

    /**
     * 添加库信息
     */
    public function addAction()
    {
        $this->view->setVar('dbTypes', DbConfigService::$dbTypes);
    }

    /**
     * 添加或修改库
     */
    public function saveAction()
    {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $dbName = isset($_POST['dbname']) ? addslashes($_POST['dbname']) : '';
        $username = isset($_POST['username']) ? addslashes($_POST['username']) : '';
        $password = isset($_POST['password']) ? addslashes($_POST['password']) : '';
        $port = isset($_POST['port']) ? intval($_POST['port']) : 3306;
        $host = isset($_POST['host']) ? addslashes($_POST['host']) : '';
        $type = isset($_POST['type']) ? addslashes($_POST['type']) : 'master';
        $alias = isset($_POST['alias']) ? addslashes($_POST['alias']) : '';

        $data = array(
            'dbname' => $dbName,
            'username' => $username,
            'password' => $password,
            'port' => $port,
            'host' => $host,
            'type' => $type,
            'alias' => $alias,
        );
        if($id == 0){
            DbConfigService::addDbConfig($data);
            $this->success('/dbconfig');
        }

        //判断密码是否需要加密
        if( $password != $_POST['oldpwd']){
            $data['password'] = DbConfigService::encode($password);
        }

        //修改库
        DbConfigService::updateDbConfig($id,$data);
        $this->success('/dbconfig');
    }

    /**
     * 删除库
     */
    public function deleteAction()
    {
        if(!DbConfigService::deleteDbConfig($_GET['id'])){
            $this->error("删除失败");
        }
        $this->success('/dbconfig');
    }

    /**
     * 修改库
     */
    public function editAction()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $alias = DbConfigService::getAliasById($id);
        $config = DbConfigService::getDbInfo($alias);
        if(empty($config)){
            $this->error("该库不存在");
        }
        $this->view->setVar('config',$config);
        $this->view->setVar('dbTypes', DbConfigService::$dbTypes);
    }

}
