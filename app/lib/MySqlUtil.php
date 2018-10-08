<?php
/**
 *连接mysql工具
 */

namespace app\lib;


require_once(__DIR__ . '/header.php');

class MySqlUtil
{

    function insert($value)
    {
        global $DB_HOST, $DB_USER, $DB_PASSWORD, $DB_DATABASE;
        $db     = new Swoole\MySQL;
        $server = array(
            'host'     => $DB_HOST,
            'user'     => $DB_USER,
            'password' => $DB_PASSWORD,
            'database' => $DB_DATABASE,
            'timeout'  => '1000'
        );
        $sql    = "INSERT INTO infohash_table (infohash) VALUES('$value')";

        $db->connect($server, function ($db, $result) {
            global $sql;
            $db->query($sql, function (Swoole\MySQL $db, $result) {
                if ($result === false) {
                    var_dump($db->error, $db->errno);
                }
                $db->close();
            });
        });
    }
}
