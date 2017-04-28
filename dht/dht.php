<?php
// 文件路径
define('ABSPATH', dirname(__FILE__));
// 主进程数, 一般为CPU的1至4倍
define('WORKER_NUM', 1);
// 允许最大连接数, 不可大于系统ulimit -n的值
define('MAX_REQUEST', 10000);
// 线程数
define('MAX_PROCESS', 10);
// 自动查找间隔, 单位为毫秒
define('AUTO_FIND_TIME', 10000);
// 发送find_node间隔, 单位秒
define('NEXT_FIND_NODE_TIME', 0.5);

// 载入类文件
require_once ABSPATH . '/inc/Node.class.php';
require_once ABSPATH . '/inc/Bencode.class.php';
require_once ABSPATH . '/inc/Base.class.php';

require_once(__DIR__ . '/header.php');

// 保存swoole_server对象
$serv = NULL;
// 设置自身node id
$nid = Base::get_node_id();
// 初始化路由器
$table = array();
// 最后请求时间
$last_find = time();
// 保存线程列表
$threads = [];
//保存infohash
$file = fopen("infohash.log", "a") or exit("无法打开文件!");


// 长期在线node
$bootstrap_nodes = array(
    array('router.bittorrent.com', 6881),
    array('dht.transmissionbt.com', 6881),
    array('router.utorrent.com', 6881),
    array('208.67.16.113', 8000),
    //array('open.acgtracker.com', 1096),
    //array('t2.popgo.org', 7456),
);

//设置为北京时间
date_default_timezone_set("Asia/Shanghai");

//fwrite($file, "DHT网络爬虫启动 时间:" . date("y-m-d h:i:sa") . "\n");

$serv = new swoole_server('0.0.0.0', 9501, SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
$serv->set(array(
    'worker_num' => WORKER_NUM,
    'daemonize' => FALSE,
    'max_request' => MAX_REQUEST,
    'dispatch_mode' => 2,
    //'log_file' => '/dev/null'
));


$serv->on('WorkerStart', function ($serv, $worker_id) {
    // 添加一个定时器, 使服务器定时寻找节点
    swoole_timer_tick(10000, function () {
        auto_find_node();
    });
});
$serv->on('Receive', function ($serv, $fd, $from_id, $data) {
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
            response_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
    } elseif ($msg['y'] == 'q') {
        // 如果是请求, 则执行请求判断
        request_action($msg, array($fdinfo['remote_ip'], $fdinfo['remote_port']));
    } else {
        return false;
    }
});


//启动服务器
$foo = $serv->start();

swoole_timer_tick(10000, function ($interval) {
    for ($i = 0; $i < MAX_PROCESS; $i++) {
        $process = new swoole_process(function () {
            auto_find_node();
        });
        $pid = $process->start();
        $threads[$pid] = $process;
        swoole_process::wait();
    }
});


/**
 * 自动查找节点方法, 将在DHT网络中自动搜寻节点信息
 *
 * @return void
 */
function auto_find_node()
{
    global $table;

    // 如果路由表中没有数据则先加入DHT网络
    if (count($table) == 0)
        return join_dht();

    // 循环处理路由表
    while (count($table)) {
        // 从路由表中删除第一个node并返回被删除的node
        $node = array_shift($table);
        // 发送查找find_node到node中
        find_node(array($node->ip, $node->port), $node->nid);
        //sleep(0.005);
    }
}

/**
 * 加入dht网络
 *
 * @return void
 */
function join_dht()
{
    global $table, $bootstrap_nodes;

    // 循环操作
    foreach ($bootstrap_nodes as $node) {
        // 将node域名解析为IP地址, 并发送find_node请求
        find_node(array(gethostbyname($node[0]), $node[1]));
    }
}

/**
 * 发送find_node请求
 *
 * @param  array $address 对端链接信息
 * @param string $id      node id
 *
 * @return void
 */
function find_node($address, $id = null)
{
    global $nid, $table;

    // 若未指定id则使用自身node id
    if (is_null($id))
        $mid = $nid;
    else
        // 否则伪造一个相邻id
        $mid = Base::get_neighbor($id, $nid);

    // 定义发送数据
    $msg = array(
        't' => Base::entropy(2),
        'y' => 'q',
        'q' => 'find_node',
        'a' => array(
            'id' => $nid,
            'target' => $mid
        )
    );

    // 发送请求数据到对端
    send_response($msg, $address);
}

/**
 * 处理对端发来的请求
 *
 * @param  array $msg     接收到的请求数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function request_action($msg, $address)
{
    switch ($msg['q']) {
        case 'ping':
            on_ping($msg, $address);
            break;
        case 'find_node':
            on_find_node($msg, $address);
            break;
        case 'get_peers':
            // 处理get_peers请求
            on_get_peers($msg, $address);
            break;
        case 'announce_peer':
            // 处理announce_peer请求
            on_announce_peer($msg, $address);
            break;
        default:
            return false;
    }
}

/**
 * 处理接收到的find_node回复
 *
 * @param  array $msg     接收到的数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function response_action($msg, $address)
{
    // 先检查接收到的信息是否正确
    if (!isset($msg['r']['nodes']) || !isset($msg['r']['nodes'][1]))
        return false;

    // 对nodes数据进行解码
    $nodes = Base::decode_nodes($msg['r']['nodes']);

    // 对nodes循环处理
    foreach ($nodes as $node) {
        // 将node加入到路由表中
        append($node);
    }
}

/**
 * 处理ping请求
 *
 * @param  array $msg     接收到的ping请求数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function on_ping($msg, $address)
{
    global $nid;

    // 获取对端node id
    $id = $msg['a']['id'];
    // 生成回复数据
    $msg = array(
        't' => $msg['t'],
        'y' => 'r',
        'r' => array(
            'id' => $nid
        )
    );

    // 将node加入路由表
    append(new Node($id, $address[0], $address[1]));
    // 发送回复数据
    send_response($msg, $address);
}

/**
 * 处理find_node请求
 *
 * @param  array $msg     接收到的find_node请求数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function on_find_node($msg, $address)
{
    global $nid;

    // 获取node列表
    $nodes = get_nodes(16);
    // 获取对端node id
    $id = $msg['a']['id'];
    // 生成回复数据
    $msg = array(
        't' => $msg['t'],
        'y' => 'r',
        'r' => array(
            'id' => $nid,
            'nodes' => Base::encode_nodes($nodes)
        )
    );

    // 将node加入路由表
    append(new Node($id, $address[0], $address[1]));
    // 发送回复数据
    send_response($msg, $address);
}

/**
 * 处理get_peers请求
 *
 * @param  array $msg     接收到的get_peers请求数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function on_get_peers($msg, $address)
{
    global $nid, $file;

    // 获取info_hash信息
    $infohash = $msg['a']['info_hash'];
    // 获取node id
    $id = $msg['a']['id'];

    // 生成回复数据
    $msg = array(
        't' => $msg['t'],
        'y' => 'r',
        'r' => array(
            'id' => $nid,
            'nodes' => Base::encode_nodes(get_nodes()),
            'token' => substr($infohash, 0, 2)
        )
    );
    try{
         $conn=getMysqlConn();
         insert($conn,$infohash);

    }catch(Exception $e){
         fwrite($file, "插入数据库失败！ info_hash: " . $infohash . "  " . date("y-m-d  h:i:sa") . "\n");
    }
    
    // 将node加入路由表
    append(new Node($id, $address[0], $address[1]));
    // 向对端发送回复数据
    send_response($msg, $address);
}

/**
 * 处理announce_peer请求
 *
 * @param  array $msg     接收到的announce_peer请求数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function on_announce_peer($msg, $address)
{
    global $nid, $file;

    // 获取infohash
    $infohash = $msg['a']['info_hash'];
    // 获取token
    $token = $msg['a']['token'];
    // 获取node id
    $id = $msg['a']['id'];

    // 验证token是否正确
    if (substr($infohash, 0, 2) == $token) {
        /*$txt = array(
            'action' => 'announce_peer',
            'msg' => array(
                'ip' => $address[0],
                'port1' => $address[1],
                'port2' => $msg['a']['port'],
                'infohash' => $infohash
            )
        );
        var_dump($txt);*/

        $nodeid = bin2hex($id);
        //LOGI("(node_id={$nodeid}) 获取到info_hash: " . strtoupper(bin2hex($infohash)));
       
        try{
             $conn=getMysqlConn();
             insert($conn,strtoupper(bin2hex($infohash)));

        }catch(Exception $e){
              fwrite($file, "info_hash: " . strtoupper(bin2hex($infohash)) . "  " . date("y-m-d  h:i:sa") . "\n");
        }
        //logDHTAnnouncePeer($nodeid, bin2hex($infohash));
    }

    // 生成回复数据
    $msg = array(
        't' => $msg['t'],
        'y' => 'r',
        'r' => array(
            'id' => $nid
        )
    );

    // 发送请求回复
    send_response($msg, $address);
}

/**
 * 向对端发送数据
 *
 * @param  array $msg     要发送的数据
 * @param  array $address 对端链接信息
 *
 * @return void
 */
function send_response($msg, $address)
{
    global $serv, $file;

    if (filter_var($address[0], FILTER_VALIDATE_IP) === FALSE) {

        $ip = gethostbyname($address[0]);
        if (strcmp($ip, $address[0]) !== 0) {
           // fwrite($file, "{$address[0]} 不是一个有效的 IP 地址，将其作为域名解析得到 IP {$ip} " . date("y-m-d  h:i:sa") . "\n");
            $address[0] = $ip;
        } else {
           // fwrite($file, "{$address[0]} 不是一个有效的 IP 地址，且将其当作域名解析失败 " . date("y-m-d  h:i:sa") . "\n");
        }
    }else{
        $serv->sendto($address[0], $address[1], Base::encode($msg));
    }

    
}

/**
 * 添加node到路由表
 *
 * @param  Node $node node模型
 *
 * @return boolean       是否添加成功
 */
function append($node)
{
    global $nid, $table;

    // 检查node id是否正确
    if (!isset($node->nid[19]))
        return false;

    // 检查是否为自身node id
    if ($node->nid == $nid)
        return false;

    // 检查node是否已存在
    if (in_array($node, $table))
        return false;

    // 如果路由表中的项达到200时, 删除第一项
    if (count($table) >= 200)
        array_shift($table);

    return array_push($table, $node);
}

function get_nodes($len = 8)
{
    global $table;

    if (count($table) <= $len)
        return $table;

    $nodes = array();

    for ($i = 0; $i < $len; $i++) {
        $nodes[] = $table[mt_rand(0, count($table) - 1)];
    }

    return $nodes;
}

