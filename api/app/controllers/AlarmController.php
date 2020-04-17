<?php

namespace Group\Magneto\Admin\Controllers;

class AlarmController extends BaseController
{

    protected $token = '7qCLg2B3YqwccRckSWxp';

    protected $aesKey = 'NfHfCwkrwN3IfTtFHX4S93xESsol68TCcrHT8NsHXaS';

    protected $corpId = 'wx4d9455246e8b5915';

    protected $rules = array(
        '1' => 'responseTop',
        '清理' => 'responseClear',
    );

    protected $help = array(
        '- 输入【1】查看告警排行',
        '- 输入【清理 key】清理相应告警',
    );

    public function indexAction() {}

    protected function responseTop()
    {
        $result = getDI()->get('db_magneto')->fetchAll('SELECT * FROM alarm_config');
        foreach ($result as $key => $item) {
            $count = getDI()->get('redis')->lLen('ALARM_QUEUE_'.$item['name']);
            $result[$key]['count'] = $count;
        }

        usort($result, function ($a, $b) {
            return $a['count'] < $b['count'];
        });

        $content = '';
        for ($i = 0; $i < 10; $i++) {
            $item = $result[$i];
            $content .= "{$item['count']} - {$item['name']}\n";
        }

        return $this->responseText('未发告警排行', $content);
    }

    protected function responseClear()
    {
        $name = addslashes($this->keyArray[1]);

        $config = getDI()->get('db_magneto')->fetchOne("SELECT * FROM alarm_config WHERE name='{$name}'");
        if (empty($config)) {
            return $this->responseText('告警key不存在');
        }

        $count = getDI()->get('redis')->lLen('ALARM_QUEUE_'.$name);
        getDI()->get('redis')->del('ALARM_QUEUE_'.$name);

        return $this->responseText('清理完成', "KEY:{$config['name']}", "名称:{$config['title']}", '清理告警数:'.$count);
    }

}
