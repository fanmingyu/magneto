<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Admin\Daos\UserDAO;

class AlarmController extends BaseController
{

    const KEY_LIST = 'ALARM_QUEUE_';

    const KEY_LASTTIME = 'ALARM_LASTTIME_';

    const KEY_STAT = 'ALARM_STAT_';

    /**
     * 首页
     */
    public function indexAction()
    {
        $result = getDI()->get('db_magneto')->fetchAll('SELECT * FROM alarm_config');
        foreach ($result as $key => $item) {
            $result[$key]['count'] = $this->count($item['name']);
            $result[$key]['lastTime'] = $this->getLastTime($item['name']);
        }

        $this->view->setVar('result', $result);
    }

    /**
     * 告警详情
     */
    public function detailAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM alarm_config WHERE id='{$id}'");
        if (empty($config)) {
            $this->error('告警配置不存在');
        }

        $count = $this->count($config['name']);

        $date = array();
        $total = array();

        $dayStart = isset($_REQUEST['dayStart']) ? strtotime($_REQUEST['dayStart']) : time();
        for ($i = 0; $i < 30; $i++) {
            $time = $dayStart - 86400 * $i;
            $date[] = date('Y-n-j', $time);
            $total[] = $this->getStat($config['name'], date('Ymd', $time));
        }

        $hours = array();
        $hoursTotol = array();
        $hourStart = isset($_REQUEST['hourStart']) ? strtotime($_REQUEST['hourStart']) : strtotime(date('Y-m-d H:00:00')) - 86400;
        $end = $hourStart + 3600 * 25;
        for ($i = $hourStart; $i < $end; $i += 3600) {
            $hours[] = date('Y-m-d H', $i);
            $hoursTotol[] = $this->getStat($config['name'], date('Ymd_H', $i));
        }

        $this->view->setVar('config', $config);
        $this->view->setVar('count', $count);
        $this->view->setVar('date', array_reverse($date));
        $this->view->setVar('total', array_reverse($total));
        $this->view->setVar('hours', $hours);
        $this->view->setVar('hoursTotol', $hoursTotol);
        $this->view->setVar('hourStart', $hourStart);
        $this->view->setVar('dayStart', $dayStart);
    }

    /**
     * 预览
     */
    public function viewAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
        $count = isset($_REQUEST['count']) ? intval($_REQUEST['count']) : 50;

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM alarm_config WHERE id='{$id}'");
        if (empty($config)) {
            $this->error('告警配置不存在');
        }

        $start = ($page - 1) * $count;
        $end = $start + $count - 1;

        $result = getDI()->get('redis')->lRange(self::KEY_LIST.$config['name'], $start, $end);

        $this->view->setVar('config', $config);
        $this->view->setVar('page', $page);
        $this->view->setVar('result', $result);
    }

    /**
     * 清空告警
     */
    public function clearAction()
    {
        $name = isset($_REQUEST['name']) ? addslashes(trim($_REQUEST['name'])) : '';
        if ($name === '') {
            $this->error('参数错误');
        }

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM alarm_config WHERE name='{$name}'");
        if (empty($config)) {
            $this->error('告警配置不存在');
        }

        $this->clear($name);

        $this->success("/alarm/detail?id={$config['id']}");
    }

    /**
     * 添加告警
     */
    public function addAction()
    {
        $this->view->setVar('config', array(
            'time_limit' => 60,
            'trigger_limit' => 1,
            'sms_trigger_limit' => 10,
            'title' => '',
            'name' => '',
            'mail' => '',
        ));
        $this->view->setVar('emails', UserDAO::getAllEmails());
    }

    /**
     * 修改
     */
    public function editAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        if ($id === 0) {
            $this->error('参数错误');
        }

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM alarm_config WHERE id='{$id}'");

        $this->view->setVar('config', $config);
        $this->view->setVar('emails', UserDAO::getAllEmails());
    }

    /**
     * 保存添加或修改
     */
    public function saveAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $data = array(
            'name' => addslashes(trim($_REQUEST['name'])),
            'title' => addslashes(trim($_REQUEST['title'])),
            'mail' => addslashes(trim($_REQUEST['mail'])),
            'sms' => '',
            'time_limit' => intval($_REQUEST['time_limit']),
            'trigger_limit' => intval($_REQUEST['trigger_limit']),
            'sms_trigger_limit' => intval($_REQUEST['sms_trigger_limit']),
        );

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM alarm_config WHERE name='{$data['name']}' AND id!='{$id}'");
        if (!empty($config)) {
            $this->error('告警KEY重复');
        }

        //添加
        if ($id === 0) {
            $data['create_time'] = time();
            if (!getDI()->get('db_magneto')->insert('alarm_config', $data, array_keys($data))) {
                $this->error('添加失败');
            }
            $this->success('/alarm');
        }

        //修改
        $data['update_time'] = time();
        if (!getDI()->get('db_magneto')->update('alarm_config', array_keys($data), array_values($data), "id='{$id}'")) {
            $this->error('修改失败');
        }

        $this->success("/alarm/detail?id={$id}");
    }

    /**
     * 未发告警数
     */
    private function count($name)
    {
        return getDI()->get('redis')->lLen(self::KEY_LIST.$name);
    }

    /**
     * 最后发送告警时间
     */
    private static function getLastTime($name)
    {
        return getDI()->get('redis')->get(self::KEY_LASTTIME.$name);
    }

    /**
     * 获取告警统计
     */
    private static function getStat($name, $date)
    {
        return (int) getDI()->get('redis')->get(self::KEY_STAT.$name.'_'.$date);
    }

    /**
     * 清除告警
     */
    public static function clear($name)
    {
        return getDI()->get('redis')->del(self::KEY_LIST.$name);
    }

}
