<?php
/*
*连接mysql工具
*/
require_once(__DIR__ . '/header.php');

//获取一个sql链接
function getMysqlConn(){
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
		mysql_query($conn,$sql);

	}catch(Exception $e){
		die("Insert failed!");
	}
}


