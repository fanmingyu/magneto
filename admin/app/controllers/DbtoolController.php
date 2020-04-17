<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Admin\Service\DbConfigService;
use Group\Magneto\Admin\Service\DbtoolService;


class DbtoolController extends BaseController
{

    /**
     * 首页
     */
    public function indexAction()
    {
        $dbs = DbConfigService::getAliases();

        $db = isset($_REQUEST['db']) ? trim($_REQUEST['db']) : 'magneto';

        if (!in_array($db,$dbs)) {
            $this->error('db不允许查看');
        }
        $connection = DbConfigService::getDbConnection($db);
        $dbName = $connection->getDescriptor()['dbname'];
        //获取所有表
        $tables = DbtoolService::getTableList($db);
        //获取表数据统计
        $tableStat = array();
        $count = 0;
        $sql = "SELECT TABLE_ROWS, DATA_LENGTH, TABLE_NAME, INDEX_LENGTH from information_schema.tables where TABLE_SCHEMA='{$dbName}'";
        $result = $connection->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);

        foreach ($result as $item) {
            $tableStat[$item['TABLE_NAME']] = $item;
            $count += $item['TABLE_ROWS'];
        }
        $this->view->setVar('tables', $tables);
        $this->view->setVar('tableStat', $tableStat);
        $this->view->setVar('db', $db);
        $this->view->setVar('dbList', $dbs);
        $this->view->setVar('count', $count);
    }

    /**
     * 查看表结构
     */
    public function structureAction()
    {
        $db = isset($_REQUEST['db']) ? trim($_REQUEST['db']) : 'corp';
        $table = isset($_REQUEST['table']) ? trim(addslashes($_REQUEST['table'])) : '';

        if (empty($table) || empty($db)) {
            $this->error('参数错误');
        }
        $structure = DbtoolService::getStructure($db, $table);

        $this->view->setVar('db', $db);
        $this->view->setVar('table', $table);
        $this->view->setVar('result', $structure);
    }

}
