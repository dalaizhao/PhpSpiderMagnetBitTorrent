<?php

namespace SurgicalFruit\service;

class DhtOperateService extends BaseService
{

    /**  设置自身node id*/
    protected $node_id;
    protected $routing_table = [];

    public function __construct()
    {
        $this->node_id = self::get_node_id();
    }

    /**
     * 自动查找节点方法, 将在DHT网络中自动搜寻节点信息
     * @return void
     */
    function auto_find_node()
    {
        /** 如果路由表中没有数据则先加入DHT网络*/
        if (count($this->routing_table) == 0)
            return $this->join_dht();

        /** 循环处理路由表 */
        while (count($this->routing_table)) {
            /** 从路由表中删除第一个node并返回被删除的node */
            $node = array_shift($this->routing_table);
            /** 发送查找find_node到node中*/
            $this->find_node(array($node->ip, $node->port), $node->nid);
        }
    }

    /**
     * 加入dht网络
     * @return void
     */
    function join_dht()
    {
        foreach (DhtConst::$bootstrap_nodes as $node) {
            /** 将node域名解析为IP地址, 并发送find_node请求*/
            $this->find_node(array(gethostbyname($node[0]), $node[1]));
        }
    }

    /**
     * 发送find_node请求
     * @param  array $address 对端链接信息
     * @param string $id      node id
     * @return void
     */
    function find_node($address, $id = null)
    {
        /** 若未指定id则使用自身node id，否侧伪造邻居节点*/
        if (is_null($id)) {
            $mid = $this->node_id;
        } else {
            $mid = Base::get_neighbor($id, $this->node_id);
        }
        /** 定义发送数据*/
        $msg = array(
            't' => Base::entropy(2),
            'y' => 'q',
            'q' => 'find_node',
            'a' => array(
                'id'     => $this->node_id,
                'target' => $mid
            )
        );

        /** 发送请求数据到对端*/
        $this->send_response($msg, $address);
    }

    /**
     * 处理对端发来的请求
     * @param  array $msg     接收到的请求数据
     * @param  array $address 对端链接信息
     * @return bool
     */
    function request_action($msg, $address)
    {
        switch ($msg['q']) {
            case 'ping':
                $this->on_ping($msg, $address);
                break;
            case 'find_node':
                $this->on_find_node($msg, $address);
                break;
            case 'get_peers':
                // 处理get_peers请求
                $this->on_get_peers($msg, $address);
                break;
            case 'announce_peer':
                // 处理announce_peer请求
                $this->on_announce_peer($msg, $address);
                break;
            default:
                return false;
        }
    }

    /**
     * 处理接收到的find_node回复
     * @param  array $msg     接收到的数据
     * @param  array $address 对端链接信息
     * @return bool
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
            $this->append($node);
        }
    }

    /**
     * 处理ping请求
     * @param  array $msg     接收到的ping请求数据
     * @param  array $address 对端链接信息
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
        $this->append(new Node($id, $address[0], $address[1]));
        // 发送回复数据
        $this->send_response($msg, $address);
    }

    /**
     * 处理find_node请求
     * @param  array $msg     接收到的find_node请求数据
     * @param  array $address 对端链接信息
     * @return void
     */
    function on_find_node($msg, $address)
    {
        global $nid;

        // 获取node列表
        $nodes = $this->get_nodes(16);
        // 获取对端node id
        $id = $msg['a']['id'];
        // 生成回复数据
        $msg = array(
            't' => $msg['t'],
            'y' => 'r',
            'r' => array(
                'id'    => $nid,
                'nodes' => Base::encode_nodes($nodes)
            )
        );

        // 将node加入路由表
        $this->append(new Node($id, $address[0], $address[1]));
        // 发送回复数据
        $this->send_response($msg, $address);
    }

    /**
     * 处理get_peers请求
     * @param  array $msg     接收到的get_peers请求数据
     * @param  array $address 对端链接信息
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
                'id'    => $nid,
                'nodes' => Base::encode_nodes(get_nodes()),
                'token' => substr($infohash, 0, 2)
            )
        );
        //插入数据库
        $this->insert(strtoupper(bin2hex($infohash)));
        // 将node加入路由表
        $this->append(new Node($id, $address[0], $address[1]));
        // 向对端发送回复数据
        $this->send_response($msg, $address);
    }

    /**
     * 处理announce_peer请求
     * @param  array $msg     接收到的announce_peer请求数据
     * @param  array $address 对端链接信息
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
            //插入数据库
            $this->insert(strtoupper(bin2hex($infohash)));
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
        $this->send_response($msg, $address);
    }

    /**
     * 向对端发送数据
     * @param        $serv
     * @param  array $msg     要发送的数据
     * @param  array $address 对端链接信息
     * @return void
     */
    function send_response($serv, $msg, $address)
    {
        if (filter_var($address[0], FILTER_VALIDATE_IP) === false) {

            $ip = gethostbyname($address[0]);
            if (strcmp($ip, $address[0]) !== 0) {
                $address[0] = $ip;
            } else {
            }
        }
        $serv->sendto($address[0], $address[1], Base::encode($msg));

    }

    /**
     * 添加node到路由表
     * @param  Node $node node模型
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

    /**
     * @param int $len
     * @return array
     */
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
}

