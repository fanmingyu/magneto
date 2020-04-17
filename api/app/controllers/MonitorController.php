<?php

namespace Group\Magneto\Admin\Controllers;

class MonitorController extends BaseController
{

    protected $token = '80ltbf1ZoGj4zHRXhMurxFCY8A9';

    protected $aesKey = 'XcpgiLOYSQXY7nlqVys1TW5lyOIQkSd7glbIdh9YNju';

    protected $corpId = 'wx4d9455246e8b5915';

    protected $rules = array(
        '热点' => 'responseTop',
    );

    protected $help = array(
        '- 输入【热点】查看当前热点指标',
        '- 输入【热点 关键字】查看含指定关键字的热点指标',
    );

    public function indexAction() {}

    protected function responseTop()
    {
        if (isset($this->keyArray[1])) {
            $where = "WHERE name LIKE '%".addslashes($this->keyArray[1])."%'";
        }

        $sql = "SELECT * FROM monitor_point_config {$where} ORDER BY lastvalue DESC LIMIT 10";
        $result = getDI()->get('db_magneto')->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);

        $content = '';
        foreach ($result as $item) {
            $content .= "{$item['lastvalue']} - {$item['title']}\n";
        }

        return $this->responseText('一分钟热点', $content);
    }

}
