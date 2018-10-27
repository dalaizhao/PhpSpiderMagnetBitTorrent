<?php

namespace SurgicalFruit\tasks;

use SurgicalFruit\service\DhtOperateService;

require_once '../../vendor/autoload.php';

class ServerTask
{
    public $obj_dht_service;
    public $obj_server;
    /** 初始化路由器*/
    public $table = array();
    /** 保存线程列表*/
    public $threads = [];

    public function __construct()
    {
        $this->obj_dht_service = new DhtOperateService();
        /** UDP服务器*/
        $this->obj_server = new swoole_server('0.0.0.0', 9501, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
        $this->obj_server->set(array(
            'worker_num'    => ServerConst::SERVER_WORKER_NUM,
            'daemonize'     => ServerConst::SERVER_DAEMONIZE,
            'max_request'   => ServerConst::SERVER_MAX_REQUEST,
            'dispatch_mode' => ServerConst::SERVER_DISPATCH_MODE,
        ));
        $this->obj_server->on('Start', array($this, 'onStart'));
        $this->obj_server->on('Connect', array($this, 'onConnect'));
        $this->obj_server->on('Receive', array($this, 'onReceive'));
        $this->obj_server->on('Timer', array($this, 'onTimer'));
        //$this->timer_tick();
    }

    public function onStart($serv)
    {
        /** 启动服务器*/
        $serv->start();
    }

    public function onConnect($serv, $fd, $from_id)
    {
        $serv->on('WorkerStart', function ($serv, $worker_id) use ($obj_dht_service) {
            /** 添加一个定时器, 使服务器定时寻找节点*/
            swoole_timer_tick(ServerConst::SERVER_AUTO_FIND_TIME, function () use ($obj_dht_service) {
                $obj_dht_service->auto_find_node();
            });
        });
    }

    public function onReceive($serv, $fd, $from_id, $data)
    {

        $serv->on('Receive', function ($serv, $fd, $from_id, $data) {
            /** 检查数据长度*/
            if (strlen($data) == 0)
                return false;
            /** 对数据进行解码*/
            $msg = Base::decode($data);
            /** 获取对端链接信息, udp链接需要加上$from_id参数 */
            $fdinfo = $serv->connection_info($fd, $from_id);
            /** 对接收到的数据进行类型判断*/
            if (!isset($msg['y'])) {
                /** 数据格式不合法,什么都不做**/
            } else if ($msg['y'] == 'r') {
                /** 如果是回复, 且包含nodes信息 */
                if (array_key_exists('nodes', $msg['r']))
                    /** 对nodes进行操作 */
                    $obj_dht_service->response_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
            } elseif ($msg['y'] == 'q') {
                /** 如果是请求, 则执行请求判断 */
                $obj_dht_service->request_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
            } else {
                return false;
            }
        });
    }
}

/** 启动服务器 */
$server = new ServerTask();
/** 定时器 */
swoole_timer_tick(ServerConst::SERVER_AUTO_FIND_TIME, function () use ($server) {
    for ($i = 0; $i < ServerConst::SERVER_MAX_PROCESS; $i++) {
        $process = new swoole_process(function () use ($server) {
            $server->obj_dht_service->auto_find_node();
        });
        $pid = $process->start();
        $$server->threads[$pid] = $process;
        swoole_process::wait();
    }
});
