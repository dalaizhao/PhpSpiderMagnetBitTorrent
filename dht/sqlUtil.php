<?php
/*
*连接mysql工具
*/
require_once(__DIR__ . '/header.php');

function insert($value){
    global $DB_HOST,$DB_USER ,$DB_PASSWORD,$DB_DATABASE;
    $db = new Swoole\MySQL;
    $server = array(
        'host' => $DB_HOST,
        'user' => $DB_USER,
        'password' => $DB_PASSWORD,
        'database' => $DB_DATABASE,
        'timeout' => '1000'
    );
    $sql="INSERT INTO infohash_table (infohash) VALUES('$value')";

    $db->connect($server, function ($db, $result) {
        global $sql;
        $db->query($sql , function (Swoole\MySQL $db, $result) {
            if ($result === false) {
                var_dump($db->error, $db->errno);
            }
            $db->close();
        });
    });
}



//获取一个sql链接
/**
 * @return mysqli
 */
/*function getMysqlConn(){

	try{
		$conn=mysqli_connect($DB_HOST,$DB_USER ,$DB_PASSWORD,$DB_DATABASE);

	}catch(Exception $e){
		die("Connection failed:".mysqli_connect_error());
	}
	return $conn;
}
//插入数据到infohash表中
function insert($conn,$value){

	$sql="INSERT INTO infohash_table (infohash) VALUES('$value')";
	try{
		mysqli_query($conn,$sql);

	}catch(Exception $e){
		die("Insert failed!");
	}
}*/


