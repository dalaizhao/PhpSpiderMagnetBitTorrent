# PHP编写的DHT网络爬虫  

以分析当前热门、用户喜好为目的，收集和查看磁力链接和种子，当然少不了证明PHP是世界上最好的语言 ^_^  

磁力搜索站点：www.imdalai.com （目前在收集分析磁力和种子阶段，尚未提供搜索）  

## 1.开发环境介绍 

### 1.环境搭建过程   

[centos7+php7.1.4+mysql5.7+swoole搭建记录](https://dalaizhao.github.io/2017/05/23/centos7-php7-1-4-mysql5-7-swoole%E6%90%AD%E5%BB%BA%E8%AE%B0%E5%BD%95/)  

### 2.介绍开发环境

linux系统：centos7.2   
php版本：php7.1.4     
数据库：mysql5.7     
当然要用高性能、高并发通信引擎：[swoole1.9.9](https://github.com/swoole/swoole-src)      

### 3.协议介绍  

[DHT协议解析](https://github.com/dalaizhao/PhpSpider_Magnet-BitTorrent/blob/master/dht_readme.md)   

目前忙于实习，本系统也是处于未完善状态，不过收集hashInfo倒是完善的（见dht文件夹），其它异步数据库、和bt下载都出与未完善阶段。那天抽空再完善整个系统。  谢谢，鼓励！  




