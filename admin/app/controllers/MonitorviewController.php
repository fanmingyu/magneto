<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Admin\Daos\MonitorDAO;
use Group\Magneto\Plugins\Weixin;
use Group\Magneto\Admin\Daos\UserDAO;


/**
 * 业务监控系统 视图
 */
class MonitorviewController extends MonitorController
{

    /**
     * 视图列表
     */
    public function indexAction()
    {
        $configAll = MonitorDAO::getAllViewConfig();
        $viewCount = MonitorDAO::getAllViewPointsCount();

        $this->view->setVar('configAll', $configAll);
        $this->view->setVar('viewCount', $viewCount);
    }

    /**
     * 视图详情
     */
    public function detailAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $fullscreen = isset($_REQUEST['fullscreen']) ? intval($_REQUEST['fullscreen']) : 0;

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_view_config WHERE id='{$id}'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($config)) {
            $this->error('视图不存在');
        }

        $viewpoints = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_view_point WHERE viewid='{$id}' ORDER BY `order` DESC, id ASC", \Phalcon\Db::FETCH_ASSOC);
        foreach ($viewpoints as $key => $item) {
            $viewpoints[$key]['starttime'] = $item['starttime'] == 0 ? '' : date('Y-m-d', time() - $item['starttime']);
        }

        $this->view->setVar('fullscreen', $fullscreen);
        $this->view->setVar('config', $config);
        $this->view->setVar('viewpoints', $viewpoints);
    }

    /**
     * 截图
     */
    public function captureAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_view_config WHERE id='{$id}'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($config)) {
            $this->error('视图不存在');
        }

        $viewpoints = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_view_point WHERE viewid='{$id}' ORDER BY `order` DESC, id ASC LIMIT {$config['capture_num']}", \Phalcon\Db::FETCH_ASSOC);
        foreach ($viewpoints as $key => $item) {
           $viewpoints[$key]['starttime'] = $item['starttime'] == 0 ? '' : date('Y-m-d', time() - $item['starttime']);
        }

        $this->view->setVar('config', $config);
        $this->view->setVar('viewpoints', $viewpoints);

    }

    /**
     * 视图添加
     */
    public function addAction()
    {
        $this->view->setVar('config', array(
            'title' => isset($config['title']) ? $config['title'] : '',
            'owner' => $_SESSION['username'],
            'capture_schedule' => '',
            'capture_num' => 0,
            'capture_switch' => 0,
        ));
        $this->view->setVar('viewpoints', array());
    }

    /**
     * 视图修改
     */
    public function editAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $point = isset($_REQUEST['point']) ? $_REQUEST['point'] : '';

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_view_config WHERE id='{$id}'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($config)) {
            $this->error('视图不存在');
        }

        $viewpoints = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_view_point WHERE viewid='{$id}' ORDER BY `order` DESC, id ASC", \Phalcon\Db::FETCH_ASSOC);

        $names = MonitorDAO::getAllPointNames();

        $this->view->setVar('emails', UserDAO::getAllEmails());
        $this->view->setVar('point', $point);
        $this->view->setVar('config', $config);
        $this->view->setVar('names', $names);
        $this->view->setVar('viewpoints', $viewpoints);
    }

    /**
     * 视图保存
     */
    public function saveAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $title = isset($_REQUEST['title']) ? addslashes(trim($_REQUEST['title'])) : '';
        $owner = isset($_REQUEST['owner']) ? addslashes(trim($_REQUEST['owner'])) : '';
        $permission = isset($_REQUEST['permission']) ? intval($_REQUEST['owner']) : 0;
        $captureSchedule = isset($_REQUEST['capture_schedule']) ? addslashes($_REQUEST['capture_schedule']) : '';
        $captureNum = isset($_REQUEST['capture_num']) ? intval($_REQUEST['capture_num']) : 0;
        $captureSwitch = isset($_REQUEST['capture_switch']) ? intval($_REQUEST['capture_switch']) : 0;

        if (empty($title) || empty($owner)) {
            $this->error('参数错误');
        }

        $owner = trim(implode(' ', preg_split('/[,\s　]+/', $owner)));

        $data = array(
            'title' => $title,
            'owner' => $owner,
            'permission' => $permission,
            'capture_schedule' => $captureSchedule,
            'capture_num' => $captureNum,
            'capture_switch' => $captureSwitch,
        );

        //添加
        if ($id === 0) {
            $data['createtime'] = time();
            if (!getDI()->get('db_magneto')->insert('monitor_view_config', $data, array_keys($data))) {
                $this->error('添加失败');
            }
            $id = getDI()->get('db_magneto')->lastInsertId();
            $this->success('/monitorview/edit?id='.$id);
        }

        //修改
        $data['updatetime'] = time();
        if (!getDI()->get('db_magneto')->update('monitor_view_config', array_keys($data), array_values($data), "id='{$id}'")) {
            $this->error('修改失败');
        }

        $this->success('/monitorview/edit?id='.$id);
    }

    /**
     * 视图删除
     */
    public function deleteAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

        $result = getDI()->get('db_magneto')->fetchOne("SELECT id FROM monitor_view_point WHERE viewid='{$id}' LIMIT 1");
        if (!empty($result)) {
            $this->error('请先删除视图下的所有监控点');
        }

        getDI()->get('db_magneto')->delete('monitor_view_config', "id='{$id}'");
        getDI()->get('db_magneto')->delete('monitor_view_point', "viewId='{$id}'");

        $this->success('/monitorview');
    }

    /**
     * 视图监控点保存
     */
    public function pointSaveAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $viewId = isset($_REQUEST['viewid']) ? intval($_REQUEST['viewid']) : 0;
        $type = isset($_REQUEST['type']) ? intval($_REQUEST['type']) : 0;
        $merge = isset($_REQUEST['merge']) ? intval($_REQUEST['merge']) : 0;
        $starttime = isset($_REQUEST['starttime']) ? intval($_REQUEST['starttime']) : 0;
        $order = isset($_REQUEST['order']) ? intval($_REQUEST['order']) : 0;
        $points = isset($_REQUEST['points']) ? addslashes(trim($_REQUEST['points'])) : '';

        $points = str_replace(' ', '', $points);

        if (empty($points) || empty($viewId)) {
            $this->error('参数错误');
        }

        $data = array(
            'viewId' => $viewId,
            'type' => $type,
            'merge' => $merge,
            'starttime' => $starttime,
            'order' => $order,
            'points' => $points,
        );

        //添加
        if ($id === 0) {
            $data['createtime'] = time();
            if (!getDI()->get('db_magneto')->insert('monitor_view_point', $data, array_keys($data))) {
                $this->error('添加失败');
            }
            $this->success('/monitorview/edit?id='.$viewId);
        }

        //修改
        $data['updatetime'] = time();
        if (!getDI()->get('db_magneto')->update('monitor_view_point', array_keys($data), array_values($data), "id='{$id}'")) {
            $this->error('修改失败');
        }

        $this->success('/monitorview/edit?id='.$viewId);
    }

    public function pointDeleteAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $viewId = isset($_REQUEST['viewid']) ? intval($_REQUEST['viewid']) : 0;

        getDI()->get('db_magneto')->delete('monitor_view_point', "id='{$id}' AND viewId='{$viewId}'");

        $this->success('/monitorview/edit?id='.$viewId);
    }

}
