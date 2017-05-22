# dht协议解析
 
BitTorrent使用“DHT(分布式散列表)”来存储“无追踪器”种子的对等联系信息。 实际上，每个对等体成为跟踪器。 该协议基于Kademila，并通过UDP实现。  

请注意本文档中使用的术语，以避免混淆。 “peer(对等体)”是在实现BitTorrent协议的TCP端口上侦听的客户端/服务器。 “node(节点)”是在实现DHT(分布式哈希表)协议的UDP端口上侦听的客户端/服务器。 DHT由node组成，存储peer的位置。 BitTorrent客户端包括一个DHT节点，用于与DHT中的其他节点联系，以便使用BitTorrent协议获取peer的位置。  

## 1.概述

每个节点具有称为“节点ID”的全局唯一标识符。从与BitTorrent infohashes相同的160位空间中随机选择节点ID。 “距离度量”用于比较两个节点ID或节点ID和“亲密度”的信息。节点必须维护包含少量其他节点的联系信息的路由表。随着ID越来越接近节点本身的ID，路由表变得更加详细。节点知道DHT中的许多其他节点具有与其自身“接近”的ID，但只有少数与自己的ID相距较远的联系人。  

在Kademlia中，距离度量是XOR，结果被解释为无符号整数。距离（A，B）= | A xor B |价值越小越靠近。  

当一个节点想要找到一个torrent的对等体时，它使用距离度量来比较torrent的信息和它自己的路由表中节点的ID。然后，它将其知道的节点与最接近infohash的ID联系起来，并询问他们当前正在下载洪流的对等体的联系信息。如果联系的节点知道torrent的对等体，则返回对等联系人信息的响应。否则，所联系的节点必须使用最接近于洪流信息的路由表中的节点的联系信息进行响应。原始节点迭代地查询更接近目标信息的节点，直到找不到更靠近的节点。搜索结束后，客户端将自己的对等联系人信息插入到最接近洪流信息的ID的响应节点上。  

对等体查询的返回值包括称为“令牌”的不透明值。对于一个节点宣布其控制对等体正在下载一个洪流，它必须在最近的对等体查询中呈现从相同查询节点接收到的令牌。当节点尝试“宣告”一个洪流时，查询的节点会根据查询节点的IP地址检查令牌。这是为了防止恶意主机注册其他主机的洪流。由于令牌仅由查询节点返回给相同的节点，所以从它接收到令牌，实现没有被定义。令牌在分发后必须接受合理的时间。 BitTorrent实现使用连接在一个秘密上的IP地址的SHA1散列，每五分钟更改一次，并接受十分钟以上的令牌。  

## 2.Routing Table(路由表)

每个节点维护已知好的节点的路由表。路由表中的节点用作DHT中查询的起点。响应来自其他节点的查询返回路由表中的节点。  

不是我们学到的所有节点都是相等的。有些是“好”，有些不是。使用DHT的许多节点能够发送查询和接收响应，但是不能响应来自其他节点的查询。重要的是每个节点的路由表必须只包含已知的好的节点。一个好的节点是一个节点在最近15分钟内响应了我们的一个查询。如果一个节点对我们的一个查询作出了回应，并在最近15分钟内向我们发送了一个查询，那么一个节点也是很好的。 15分钟不活动后，一个节点变得有问题。当节点无法响应多行查询时，节点变得不好。我们知道的节点优先级高于具有未知状态的节点。  

路由表涵盖从0到2160的整个节点ID空间。路由表被细分为“桶”，每个都包含一部分空间。一个空表有一个桶的ID空间范围为min = 0，max = 2160。当ID为“N”的节点被插入到表中时，它被放置在最小<= N <最大一个空表只有一个桶，所以任何节点都必须在其中。每个桶只能容纳K个节点，目前为8个，才能成为“满”。当桶已满的已知好的节点时，除非我们自己的节点ID落在桶的范围内，否则不再添加节点。在这种情况下，桶被替换为两个新的桶，每个桶具有旧桶的一半范围，并且来自旧桶的节点在两个新桶之间分配。对于只有一个桶的新表，全桶将始终分为两个新的桶，覆盖范围为0..2159和2159..2160。  

当桶中充满了好的节点时，新节点被简单地丢弃。如果桶中的任何节点已知会变坏，则新节点将被替换。如果在最近15分钟内没有看到桶中有可疑的节点，则最近看到的最少节点被ping通。如果pinged节点响应，则下一个最近看到的可疑节点被ping通，直到一个响应失败或者桶中的所有节点被认为是好的。如果桶中的某个节点无法响应ping，建议再尝试一次，然后再丢弃该节点并将其替换为新的优良节点。以这种方式，表填满稳定的长运行节点。  

每个桶应保持“最后更改”属性，以指示内容“新鲜”。当一个桶中的一个节点被ping通并响应时，或者一个节点被添加到一个桶中，或者一个桶中的一个节点被另一个节点替换时，应该更新桶的最后一个改变的属性。在15分钟内未更改的料桶应“刷新”。这是通过在桶的范围内选择一个随机ID并在其上执行find_nodes搜索来完成的。能够从其他节点接收查询的节点通常不需要经常刷新桶。不能从其他节点接收查询的节点通常需要定期刷新所有存储桶，以确保在需要DHT时，表中有好的节点。  

在将第一个节点插入到它的路由表中，并且在此之后启动时，节点应该尝试在DHT中找到最接近的节点给自身。它通过向更近和更靠近的节点发出find_node消息，直到找不到更近的节点。路由表应保存在客户端软件的调用之间。  

## 3.BitTorrent协议扩展

BitTorrent协议已被扩展，以便在跟踪器引入的对等体之间交换节点UDP端口号。以这种方式，客户端可以通过下载常规的种子来自动种植他们的路由表。尝试在第一次尝试下载无追踪器的洪流的新安装的客户端将不会在其路由表中具有任何节点，并且需要包含在torrent文件中的联系人。  

支持DHT的对等体设置BitTorrent协议握手中交换的8字节保留标志的最后一位。对等体接收握手指示远程对等体支持DHT应发送PORT消息。它以字节0x09开始，并且具有以网络字节顺序包含DHT节点的UDP端口的两字节有效载荷。接收该消息的对等体应尝试对接收的端口上的节点和远程对等体的IP地址进行ping。如果接收到ping的响应，则节点应根据通常的规则尝试将新的联系人信息插入到它们的路由表中。  

## 4.Torrent文件扩展名

无追踪器的洪流字典没有“公告”键。相反，无追踪器的洪流具有“节点”键。这个密钥应该设置为BT中生成客户端路由表的K个最接近的节点。或者，密钥可以被设置为已知的良好节点，例如由生成洪流的人操作的节点。请勿自动将“router.bittorrent.com”添加到torrent文件，或者自动将此节点添加到客户端路由表中。  
```
nodes = [[“<host>”，<port>]，[“<host>”，<port>]，...]
nodes = [[“127.0.0.1”，6881]，[“your.router.node”，4804]]
```

## 5.KRPC协议

KRPC协议是一种简单的RPC机制，由通过UDP发送的贝克罗斯数字字典组成。发送单个查询分组，响应发送单个分组。没有重试。有三种消息类型：查询，响应和错误。对于DHT协议，有四个查询：ping，find_node，get_peers和announce_peer。  

KRPC消息是具有每个消息通用的两个密钥和附加密钥的单个字典，这取决于消息的类型。每个消息都有一个带有表示交易ID的字符串值的键“t”。此事务ID由查询节点生成，并在响应中回显，因此响应可能与多个查询相关联到同一个节点。交易ID应该编码为一个二进制数字的短串，通常2个字符就足够了，因为它们涵盖2 ^ 16个未完成的查询。每个KRPC消息中包含的另一个密钥是“y”，单个字符值描述消息的类型。 “y”键的值是查询的“q”，响应的“r”，错误的“e”。  

### 联系编码  
对等体的联系信息被编码为6字节的字符串。也称为“紧凑型IP地址/端口信息”，4字节IP地址是网络字节顺序，网络字节顺序中的2字节端口连接到最后。  

节点的联系信息被编码为26字节的字符串。也称为“紧凑节点信息”，网络字节顺序中的20字节节点ID具有紧凑的IP地址/端口信息连接到最后。  

### 查询
查询或“y”值为“q”的KRPC消息字典包含两个附加键; “q”和“a”。键“q”具有包含查询的方法名称的字符串值。密钥“a”具有包含查询的命名参数的字典值。

### 回应
响应或“y”值为“r”的KRPC消息字典包含一个附加键“r”。值“r”是包含命名返回值的字典。响应消息在成功完成查询后发送。

### 错误
错误或“y”值为“e”的KRPC消息字典包含一个附加键“e”。 “e”的值是一个列表。 第一个元素是表示错误代码的整数。 第二个元素是包含错误消息的字符串。 无法履行查询时发送错误。 下表描述了可能的错误代码：
```
Code	Description
201	Generic Error
202	Server Error
203	Protocol Error, such as a malformed packet, invalid arguments, or bad token
204	Method Unknown
```
示例错误包：  
```
generic error = {"t":"aa", "y":"e", "e":[201, "A Generic Error Ocurred"]}
bencoded = d1:eli201e23:A Generic Error Ocurrede1:t2:aa1:y1:ee
```

## 6.DHT查询

所有查询都有一个“id”键和包含查询节点的节点ID的值。所有响应都具有“id”键和包含响应节点的节点ID的值。  

### ping

最基本的查询是ping。 “q”=“ping”ping查询有一个参数，“id”值是一个20字节的字符串，包含网络字节顺序中的发件人节点ID。对ping的适当响应具有包含响应节点的节点ID的单个密钥“id”。
```
arguments:  {"id" : "<querying nodes id>"}
response: {"id" : "<queried nodes id>"}
```
示例数据包  
```
ping Query = {"t":"aa", "y":"q", "q":"ping", "a":{"id":"abcdefghij0123456789"}}
bencoded = d1:ad2:id20:abcdefghij0123456789e1:q4:ping1:t2:aa1:y1:qe
Response = {"t":"aa", "y":"r", "r": {"id":"mnopqrstuvwxyz123456"}}
bencoded = d1:rd2:id20:mnopqrstuvwxyz123456e1:t2:aa1:y1:re
```

### find_node

查找节点用于查找给定其ID的节点的联系人信息。 “q”==“find`_`node”find`_`node查询有两个参数，“id”包含查询节点的节点ID，“target”包含查询者寻求的节点的ID。当一个节点接收到一个find_node查询时，它应该响应一个关键字“nodes”和一个字符串的值，该字符串包含目标节点或其自身路由表中最接近的K（8）个好节点的紧凑节点信息。  
```
arguments:  {"id" : "<querying nodes id>", "target" : "<id of target node>"}
response: {"id" : "<queried nodes id>", "nodes" : "<compact node info>"}
```
示例数据包  
```
find_node Query = {"t":"aa", "y":"q", "q":"find_node", "a": {"id":"abcdefghij0123456789", "target":"mnopqrstuvwxyz123456"}}
bencoded = d1:ad2:id20:abcdefghij01234567896:target20:mnopqrstuvwxyz123456e1:q9:find_node1:t2:aa1:y1:qe
Response = {"t":"aa", "y":"r", "r": {"id":"0123456789abcdefghij", "nodes": "def456..."}}
bencoded = d1:rd2:id20:0123456789abcdefghij5:nodes9:def456...e1:t2:aa1:y1:re
```

### get_peers

获取与torrent infohash相关联的对等体。 “q”=“get`_`peers”get`_`peers查询有两个参数，“id”包含查询节点的节点ID，“info`_`hash”包含torrent的infohash。如果查询节点具有infohash的对等体，则将其作为字符串列表以关键字“values”的形式返回。每个字符串包含单个对等体的“紧凑”格式对等体信息。如果所查询的节点没有infohash的对等体，则返回包含最接近查询中提供的infohash的查询节点路由表中的K个节点的密钥“nodes”。在任一情况下，返回值中也包含“令牌”键。令牌值是未来的announce`_`peer查询的必需参数。令牌值应该是一个短的二进制字符串。  
```
arguments:  {"id" : "<querying nodes id>", "info_hash" : "<20-byte infohash of target torrent>"}
response: {"id" : "<queried nodes id>", "token" :"<opaque write token>", "values" : ["<peer 1 info string>", "<peer 2 info string>"]}
or: {"id" : "<queried nodes id>", "token" :"<opaque write token>", "nodes" : "<compact node info>"}
```
示例数据包：  
```
get_peers Query = {"t":"aa", "y":"q", "q":"get_peers", "a": {"id":"abcdefghij0123456789", "info_hash":"mnopqrstuvwxyz123456"}}
bencoded = d1:ad2:id20:abcdefghij01234567899:info_hash20:mnopqrstuvwxyz123456e1:q9:get_peers1:t2:aa1:y1:qe
Response with peers = {"t":"aa", "y":"r", "r": {"id":"abcdefghij0123456789", "token":"aoeusnth", "values": ["axje.u", "idhtnm"]}}
bencoded = d1:rd2:id20:abcdefghij01234567895:token8:aoeusnth6:valuesl6:axje.u6:idhtnmee1:t2:aa1:y1:re
Response with closest nodes = {"t":"aa", "y":"r", "r": {"id":"abcdefghij0123456789", "token":"aoeusnth", "nodes": "def456..."}}
bencoded = d1:rd2:id20:abcdefghij01234567895:nodes9:def456...5:token8:aoeusnthe1:t2:aa1:y1:re
```
### announce_peer

宣布控制查询节点的对等体正在端口上下载一个洪流。 announce_peer有四个参数：包含查询节点的节点ID的“id”，包含torrent的infohash的“info_hash”，包含端口为整数的“port”，以及响应先前的get_peers查询收到的“token” 。查询节点必须验证令牌之前是否已发送到与查询节点相同的IP地址。然后查询节点应存储查询节点的IP地址和提供的端口号在其对等联系人信息存储下的infohash下。  

有一个名为implied_port的可选参数，它的值为0或1.如果存在且非零，则端口参数应被忽略，UDP数据包的源端口应该用作对端的端口。这对于可能不知道其外部端口的NAT后面的对等体是有用的，并且支持uTP，它们接受与DHT端口相同的端口上的传入连接。  
```
arguments:  {"id" : "<querying nodes id>",
  "implied_port": <0 or 1>,
  "info_hash" : "<20-byte infohash of target torrent>",
  "port" : <port number>,
  "token" : "<opaque token>"}

response: {"id" : "<queried nodes id>"}
```
示例数据包：
```
announce_peers Query = {"t":"aa", "y":"q", "q":"announce_peer", "a": {"id":"abcdefghij0123456789", "implied_port": 1, "info_hash":"mnopqrstuvwxyz123456", "port": 6881, "token": "aoeusnth"}}
bencoded = d1:ad2:id20:abcdefghij01234567899:info_hash20:<br />
mnopqrstuvwxyz1234564:porti6881e5:token8:aoeusnthe1:q13:announce_peer1:t2:aa1:y1:qe
Response = {"t":"aa", "y":"r", "r": {"id":"mnopqrstuvwxyz123456"}}
bencoded = d1:rd2:id20:mnopqrstuvwxyz123456e1:t2:aa1:y1:re
```

### 原文英文版：Andrew Loewenstern , Arvid Norberg: DHT Protocol http://www.bittorrent.org/beps/bep_0005.html  
