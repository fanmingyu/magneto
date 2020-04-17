<?php

namespace Group\Magneto\Admin\Controllers;

use Group\Magneto\Admin\Daos\MonitorDAO;
use Group\Magneto\Admin\Daos\UserDAO;
use Group\Magneto\Plugins\RPNCalc;

/**
 * 业务监控系统
 */
class MonitorController extends BaseController
{

    const PAGE_SIZE = 100;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * 监控列表
     */
    public function indexAction()
    {
        $key = isset($_GET['key']) ? trim($_GET['key']) : '';
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $sort = empty($_GET['sort']) ? 'id DESC': 'lastvalue DESC';

        $count = 0;
        $configAll = MonitorDAO::getConfigPage($key, $page, self::PAGE_SIZE, $count, $sort);
        $pageCount = ceil($count / self::PAGE_SIZE);

        //告警条数
        $alarmCount = MonitorDAO::getAllAlarmCount();

        //视图相关
        $pointViews = MonitorDAO::getAllPointViews();
        $viewConfig = MonitorDAO::getAllViewConfig();

        $this->view->setVar('key', $key);
        $this->view->setVar('config', $configAll);
        $this->view->setVar('alarmCount', $alarmCount);
        $this->view->setVar('pointViews', $pointViews);
        $this->view->setVar('viewConfig', $viewConfig);

        $this->view->setVar('page', $page);
        $this->view->setVar('pageCount', $pageCount);
        $this->view->setVar('count', $count);
    }

    /**
     * 未设置的监控点
     */
    public function undefinedAction()
    {
        $configAll = MonitorDAO::getAllConfig();

        $keys = MonitorDAO::getAllKeys();
        $keysIdleTime = [];
        foreach ($configAll as $item) {
            if (isset($keys[$item['name']])) {
                unset($keys[$item['name']]);
            }
        }

        if (!empty($keys)) {
            foreach ($keys as $key => $value) {
                $keysIdleTime[$key] = MonitorDAO::getIdleTime($key);
            }
        }

        $scanTime = MonitorDAO::getAllKeysScanTime();

        $this->view->setVar('keys', $keys);
        $this->view->setVar('scanTime', $scanTime);
        $this->view->setVar('keysIdleTime', $keysIdleTime);
    }

    /**
     * 运行状态
     */
    public function infoAction()
    {
        $sql = "SELECT TABLE_ROWS, DATA_LENGTH, TABLE_NAME, INDEX_LENGTH from information_schema.tables where TABLE_NAME LIKE 'monitor_point_value_%'";
        $result = getDI()->get('db_magneto')->fetchAll($sql, \Phalcon\Db::FETCH_ASSOC);

        foreach ($result as $key => $item) {
            $sql = "SELECT time FROM {$item['TABLE_NAME']} ORDER BY id DESC LIMIT 1";
            $result[$key]['time'] = getDI()->get('db_magneto')->fetchOne($sql, \Phalcon\Db::FETCH_ASSOC)['time'];
        }

        $this->view->setVar('result', $result);
    }

    /**
     * 扫描所有的Key
     */
    public function scanAction()
    {
        $keys = MonitorDAO::scanAllKeys();
        echo '<pre>';
        print_r($keys);
    }

    /**
     * 详情
     */
    public function detailAction()
    {
        $nameString = isset($_REQUEST['name']) ? addslashes($_REQUEST['name']) : '';

        //将空格变为逗号
        $nameString = trim($nameString);
        $nameString = preg_replace('/\s*([\+\-\*\/\,])\s*/i', '$1', $nameString);
        $nameString = preg_replace('/([\(])\s*/i', '$1', $nameString);
        $nameString = preg_replace('/\s*([\)])/i', '$1', $nameString);
        $nameString = preg_replace('/\s+/i', ',', $nameString);
        $nameString = preg_replace('/([\+\-\*\/\(\)\,])/i', ' $1 ', $nameString);

        $nameArray = MonitorDAO::parseNames($nameString);
        if (empty($nameArray[0])) {
            $this->error('参数错误');
        }

        $rpn = new RPNCalc($nameArray[0]);
        $config = MonitorDAO::getPointConfig($rpn->getFirstVar());

        $statInfo = MonitorDAO::getPointStatInfo($config['id']);

        $names = MonitorDAO::getAllPointNames();

        $views = MonitorDAO::getViewsByPointName($config['name']);
        $allViews = MonitorDAO::getAllViewConfig();

        $this->view->setVar('views', $views);
        $this->view->setVar('allViews', $allViews);
        $this->view->setVar('names', $names);
        $this->view->setVar('name', $nameString);
        $this->view->setVar('config', $config);
        $this->view->setVar('statInfo', $statInfo);
    }

    /**
     * 监控图表
     */
    public function chartAction()
    {
        ini_set('memory_limit', '1024M');

        $operation = isset($_REQUEST['op']) ? addslashes($_REQUEST['op']) : '';
        $name = isset($_REQUEST['name']) ? addslashes($_REQUEST['name']) : '';

        $height = isset($_REQUEST['height']) ? intval($_REQUEST['height']) : 269;
        $wave = isset($_REQUEST['wave']) ? intval($_REQUEST['wave']) : 0;

        $this->start = !empty($_REQUEST['start']) ? strtotime($_REQUEST['start']) : strtotime(date('Y-m-d H:i')) - 86400;
        $this->end = !empty($_REQUEST['end']) ? strtotime($_REQUEST['end']) : strtotime(date('Y-m-d H:i')) - 60;
        $this->step = !empty($_REQUEST['step']) ? intval($_REQUEST['step']) : 1;

        //解析并计算表达式
        $expressions = MonitorDAO::parseNames($name);

        $valueArray = array();
        $configArray = array();
        foreach ($expressions as $key => $exp) {
            $rpn = new RPNCalc($exp);
            $rpn->setGetVarValueFunction(array($this, 'getPointValue'));
            $rpn->setOperatorFunction(array($this, 'operateArray'));

            $valueArray[$key] = $rpn->calculate();
            $configArray[$key] = MonitorDAO::getPointConfig($rpn->getFirstVar());
        }

        //计算波动值
        if ($wave === 1) {
            foreach ($valueArray as $key => $value) {
                $valueArray[$key] = MonitorDAO::calcPointWaveData($value);
            }
        }

        //计算第一组数据的统计值
        $sum = array_sum($valueArray[0]);
        $max = max($valueArray[0]);
        $min = min($valueArray[0]);
        $avg = $sum / count($valueArray[0]);

        $alarmConfig = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_alarm_config WHERE pointId='{$configArray[0]['id']}'", \Phalcon\Db::FETCH_ASSOC);

        $count = count($configArray);
        $showName = $count > 1 ? "{$configArray[0]['title']} (共{$count}个指标叠加)" : "{$configArray[0]['title']} ({$expressions[0]})";
        $showNameShort = $count > 1 ? "{$configArray[0]['title']}(共{$count}个叠加)" : "{$configArray[0]['title']}";

        //导出
        if (!empty($_REQUEST['export'])) {
            return $this->exportChartData($showNameShort, $expressions, $valueArray, $this->step);
        }

        $this->view->setVar('showName', $showName);
        $this->view->setVar('name', $name);
        $this->view->setVar('showNameShort', $showNameShort);

        $this->view->setVar('configArray', $configArray);
        $this->view->setVar('expressions', $expressions);
        $this->view->setVar('start', $this->start);
        $this->view->setVar('end', $this->end);
        $this->view->setVar('step', $this->step);
        $this->view->setVar('height', $height);
        $this->view->setVar('wave', $wave);
        $this->view->setVar('op', $operation);

        $this->view->setVar('alarmConfig', $alarmConfig);
        $this->view->setVar('alarmType', $this->alarmType);

        $this->view->setVar('valueArray', $valueArray);
        $this->view->setVar('sum', $sum);
        $this->view->setVar('max', $max);
        $this->view->setVar('min', $min);
        $this->view->setVar('avg', $avg);

        $this->view->setVar('show_alarm', isset($_REQUEST['show_alarm']) ? intval($_REQUEST['show_alarm']) : 1);
        $this->view->setVar('show_gather', isset($_REQUEST['show_gather']) ? intval($_REQUEST['show_gather']) : 1);
        $this->view->setVar('show_export', isset($_REQUEST['show_export']) ? intval($_REQUEST['show_export']) : 1);
        $this->view->setVar('show_timeselector', isset($_REQUEST['show_timeselector']) ? intval($_REQUEST['show_timeselector']) : 1);
        $this->view->setVar('show_toolbar', isset($_REQUEST['show_toolbar']) ? intval($_REQUEST['show_toolbar']) : 1);

        $this->view->setVar('daysCount', intval(($this->end - $this->start) / 86400) + 1);
        // 是否动态刷新图表
        $this->view->setVar('autoUpdate', intval($this->step == 1 && !$wave && $this->end >= (time() - 120) && !$operation));
    }

    private function exportChartData($title, $headers, $data, $step)
    {
        $content = '';
        $content .= 'Time,'.implode(',', $headers)."\n";
        foreach ($data[0] as $time => $dataItem) {
            $row = array();
            $row[] = date('Y-m-d H:i', $time * 60 * $step);
            foreach ($headers as $key => $value) {
                $row[] = $data[$key][$time];
            }
            $content .= implode(',', $row)."\n";
        }

        header('Content-Disposition: attachment; filename='.$title.'_'.date('YmdHis').'.csv');
        echo $content;
        exit();
    }

    /**
     * 获取监控点数据
     */
    public function getPointValue($var)
    {
        if (is_array($var)) {
            return $var;
        }

        if (is_numeric($var)) {
            return $this->completePointData($var, $this->start, $this->end, $this->step);
        }

        $var = addslashes(trim($var));
        $config = MonitorDAO::getPointConfig($var);
        $id = isset($config['id']) ? intval($config['id']) : 0;

        $data = MonitorDAO::getPointData($id, $var, $this->start, $this->end);

        return $this->completePointData($data, $this->start, $this->end, $this->step);
    }

    /**
     * 补全并汇总数据
     */
    public function completePointData($data, $start, $end, $step)
    {
        //调用放在外面，防止for循环次数太多影响性能
        $dataIsArray = is_array($data);

        $value = array();
        for ($i = $start; $i <= $end; $i += 60) {
            //使用int取代intval，此处性能提高4倍左右
            $key = (int) (($i + 28800) / 60 / $step);
            if (!isset($value[$key])) {
                $value[$key] = 0;
            }

            if ($dataIsArray) {
                $value[$key] += isset($data[$i]) ? (int) ($data[$i]) : 0;
            } else {
                $value[$key] = $data;
            }
        }

        return $value;
    }

    /**
     * 数组计算
     */
    public function operateArray($array1, $array2, $operation)
    {
        $result = array();
        foreach ($array1 as $key => $value) {
            if (is_array($array2)) {
                $value2 = isset($array2[$key]) ? $array2[$key] : 0;
            } else {
                $value2 = $array2;
            }

            $result[$key] = $this->operateValue($value, $value2, $operation);
        }

        return $result;
    }

    /**
     * 数值计算
     */
    public function operateValue($value1, $value2, $operation)
    {
        if ($operation === '+') {
            return $value1 + $value2;
        }

        if ($operation === '-') {
            return $value1 - $value2;
        }

        if ($operation === '*') {
            return $value1 * $value2;
        }

        if ($operation === '/') {
            return $value2 == 0 ? 0 : round($value1 / $value2, 3);
        }

        return 0;
    }

    /**
     * 添加监控
     */
    public function addAction()
    {
        $this->view->setVar('config', array(
            'name' => isset($_REQUEST['name']) ? htmlspecialchars($_REQUEST['name']) : '',
            'owner' => $_SESSION['username'],
            'title' => '',
        ));
        $this->view->setVar('emails', UserDAO::getAllEmails());
    }

    /**
     * 修改监控
     */
    public function editAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        if ($id === 0) {
            $this->error('参数错误');
        }

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_point_config WHERE id='{$id}'");

        $this->view->setVar('config', $config);
        $this->view->setVar('emails', UserDAO::getAllEmails());
    }

    /**
     * 保存
     */
    public function saveAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $name = isset($_REQUEST['name']) ? addslashes(trim($_REQUEST['name'])) : '';
        $title = isset($_REQUEST['title']) ? addslashes(trim($_REQUEST['title'])) : '';
        $owner = isset($_REQUEST['owner']) ? addslashes(trim($_REQUEST['owner'])) : '';

        if (empty($name) || empty($title) || empty($owner)) {
            $this->error('参数错误');
        }

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_point_config WHERE name='{$name}' AND id!='{$id}'");
        if (!empty($config)) {
            $this->error('监控KEY不能重复');
        }

        $owner = trim(implode(' ', preg_split('/[,\s　]+/', $owner)));

        $data = array(
            'name' => $name,
            'title' => $title,
            'owner' => $owner,
        );

        //添加
        if ($id === 0) {
            $data['createtime'] = time();
            if (!getDI()->get('db_magneto')->insert('monitor_point_config', $data, array_keys($data))) {
                $this->error('添加失败');
            }
            $this->success('/monitor');
        }

        //修改
        $data['updatetime'] = time();
        if (!getDI()->get('db_magneto')->update('monitor_point_config', array_keys($data), array_values($data), "id='{$id}'")) {
            $this->error('修改失败');
        }

        $this->success('/monitor/detail?name='.$name);
    }

    /**
     * 告警类型
     */
    private $alarmType = array(
        'max' => '超过%s次/%s分钟',
        'min' => '低于%s次/%s分钟',
        'wave' => '波动超过%s%%/%s分钟',
        'compare' => '比值超过%s',
        'total' => '总数超过%s',
    );

    public function alarmHistoryAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $key = isset($_GET['key']) ? addslashes(trim($_GET['key'])) : '';
        $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
        $count = isset($_REQUEST['count']) ? intval($_REQUEST['count']) : 100;

        $condition = 'WHERE 1';
        if ($id > 0) {
            $condition .= " AND pointId='{$id}'";
        }

        if ($key !== '') {
            $condition .= " AND content LIKE '%{$key}%'";
        }

        $condition .= ' ORDER BY id DESC';
        $condition .= ' LIMIT '.(($page - 1) * $count).', '.$count;

        $result = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_alarm_content {$condition}", \Phalcon\Db::FETCH_ASSOC);

        foreach ($result as $i => $item) {
            $result[$i]['pointInfo'] = getDI()->get('db_magneto')->fetchOne("SELECT name, title FROM monitor_point_config WHERE id='{$item['pointId']}'");
        }

        $this->view->setVar('result', $result);
        $this->view->setVar('page', $page);
        $this->view->setVar('key', $key);
    }

    /**
     * 设置告警
     */
    public function alarmEditAction()
    {
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM monitor_point_config WHERE id='{$id}'", \Phalcon\Db::FETCH_ASSOC);
        if (empty($config)) {
            $this->error('监控不存在');
        }

        $result = getDI()->get('db_magneto')->fetchAll("SELECT * FROM monitor_alarm_config WHERE pointId='{$id}'", \Phalcon\Db::FETCH_ASSOC);
        $alarmConfig = array();
        foreach ($result as $item) {
            $alarmConfig[$item['type']][] = $item;
        }

        $this->view->setVar('id', $id);
        $this->view->setVar('config', $config);
        $this->view->setVar('alarmType', $this->alarmType);
        $this->view->setVar('alarmConfig', $alarmConfig);
    }

    /**
     * 保存告警
     */
    public function alarmSaveAction()
    {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        $pointId = isset($_POST['pointId']) ? intval($_POST['pointId']) : 0;
        $type = isset($_POST['type']) ? addslashes(trim($_POST['type'])) : '';
        $interval = isset($_POST['interval']) ? intval($_POST['interval']) : 0;
        $value = isset($_POST['value']) ? addslashes(trim($_POST['value'])) : '';

        if ($pointId === 0) {
            $this->error('参数错误');
        }

        if (!isset($this->alarmType[$type])) {
            $this->error('告警类型不存在');
        }

        if ($interval < 1) {
            $this->error('时间间隔不能低于1分钟');
        }

        if (!is_numeric($value)) {
            $this->error('阈值输入有误');
        }

        $data = array(
            'pointId' => $pointId,
            'type' => $type,
            'interval' => $interval,
            'value' => $value,
        );

        //添加
        if ($id === 0) {
            $data['createtime'] = time();
            if (!getDI()->get('db_magneto')->insert('monitor_alarm_config', $data, array_keys($data))) {
                $this->error('添加失败');
            }
            $this->success('/monitor/alarmEdit?id='.$pointId);
        }

        //修改
        $data['updatetime'] = time();
        if (!getDI()->get('db_magneto')->update('monitor_alarm_config', array_keys($data), array_values($data), "id='{$id}'")) {
            $this->error('修改失败');
        }

        $this->success('/monitor/alarmEdit?id='.$pointId);
    }

    /**
     * 告警删除
     */
    public function alarmDeleteAction()
    {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $pointId = isset($_GET['pointId']) ? intval($_GET['pointId']) : 0;

        getDI()->get('db_magneto')->delete('monitor_alarm_config', "id='{$id}'");
        $this->success('/monitor/alarmEdit?id='.$pointId);
    }

    public function getStatAsynAction()
    {
        $name = isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '';
        $time = isset($_REQUEST['time']) ? intval($_REQUEST['time']) : 0;

        if ($name === '') {
            echo json_encode(array('code' => -1, 'message' => '参数错误'));
            return;
        }

        $result = MonitorDAO::getStatRecently($id, $name, $start, $end);
        $data = array(
            'time' => array_keys($result),
            'value' => array_values($result),
        );

        echo json_encode(array('code' => 0, 'message' => '', 'data' => $data));
        return;
    }

    /**
     * 获取新的节点
     */
    public function getNextPointAction()
    {

        if (!isset($_REQUEST['name'])) {
            echo json_encode(array('code' => -1, 'message' => '参数错误', 'data' => []));
            return;
        }

        $name = addslashes($_REQUEST['name']);
        $pointIndex = intval($_REQUEST['pointIndex']);
        $pointTime = intval(time()/60) * 60 - 60;
        try {
            $pointValue = intval(getDI()->get('redis')->hget(MonitorDAO::KEY_MONITOR.$name, $pointTime));
        } catch (\Exception $e) {
            echo json_encode(array('code' => -1, 'message' => '系统错误', 'data' => []));
            return;
        }
        $data = [
            'pointTime' => $pointTime,
            'pointValue' => $pointValue,
            'pointIndex' => $pointIndex
        ];
        echo json_encode(array('code' => 0, 'message' => '', 'data' => $data));
        return;
    }

    /**
     * 获取每日统计
     */
    public function dailyStatAction()
    {
        $start = isset($_GET['start']) ? strtotime($_GET['start']) : strtotime('-1 day');
        $end = $start + 86400 - 1;

        $data = array();
        for ($i = 0; $i < 16; $i++) {
            $result = getDI()->get('db_magneto')->fetchAll("SELECT pointId,sum(value) total FROM monitor_point_value_{$i} WHERE time>='{$start}' AND time<='{$end}' GROUP BY pointId", \Phalcon\Db::FETCH_ASSOC);
            $data = array_merge($data, $result);
        }

        $dataSort = array();
        foreach ($data as $item) {
            $dataSort[$item['pointId']] = $item['total'];
        }
        arsort($dataSort);

        $config = MonitorDAO::getAllConfig();

        $this->view->setVar('start', $start);
        $this->view->setVar('data', $dataSort);
        $this->view->setVar('config', $config);
        $this->view->setVar('sum', array_sum($dataSort));
    }

}
