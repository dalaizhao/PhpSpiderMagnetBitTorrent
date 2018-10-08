<?php

namespace app\server;

// 载入类文件
use app\common\Base;
use app\common\DhtConst;

define('ROOT_PATH', dirname(__DIR__));

require '../../vendor/autoload.php';

$obj_dht_service = new DhtService();

/** 初始化路由器*/
$table = array();
/** 最后请求时间*/
$last_find = time();
/** 保存线程列表*/
$threads = [];
//保存infohash
//$file = fopen("infohash.log", "a") or exit("无法打开文件!");

/** 保存swoole_server对象*/
$serv = new swoole_server('0.0.0.0', 9501, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set(array(
    'worker_num'    => DhtConst::SERVER_WORKER_NUM,
    'daemonize'     => false,
    'max_request'   => DhtConst::SERVER_MAX_REQUEST,
    'dispatch_mode' => 2
));


$serv->on('WorkerStart', function ($serv, $worker_id) use ($obj_dht_service) {
    /** 添加一个定时器, 使服务器定时寻找节点*/
    swoole_timer_tick(DhtConst::SERVER_AUTO_FIND_TIME, function () use ($obj_dht_service) {
        $obj_dht_service->auto_find_node();
    });
});
$serv->on('Receive', function ($serv, $fd, $from_id, $data) use ($obj_dht_service) {
    // 检查数据长度
    if (strlen($data) == 0)
        return false;

    // 对数据进行解码
    $msg = Base::decode($data);

    // 获取对端链接信息, udp链接需要加上$from_id参数
    $fdinfo = $serv->connection_info($fd, $from_id);

    // 对接收到的数据进行类型判断
    if (!isset($msg['y'])) {
        /// 数据格式不合法
        /// 什么都不做
    } else if ($msg['y'] == 'r') {
        // 如果是回复, 且包含nodes信息
        if (array_key_exists('nodes', $msg['r']))
            // 对nodes进行操作
            $obj_dht_service->response_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
    } elseif ($msg['y'] == 'q') {
        // 如果是请求, 则执行请求判断
        $obj_dht_service->request_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
    } else {
        return false;
    }
});


/** 启动服务器*/
$foo = $serv->start();

swoole_timer_tick(DhtConst::SERVER_AUTO_FIND_TIME, function ($interval) use ($obj_dht_service) {
    for ($i = 0; $i < DhtConst::SERVER_MAX_PROCESS; $i++) {
        $process       = new swoole_process(function () use ($obj_dht_service) {
            $obj_dht_service->auto_find_node();
        });
        $pid           = $process->start();
        $threads[$pid] = $process;
        swoole_process::wait();
    }
});
