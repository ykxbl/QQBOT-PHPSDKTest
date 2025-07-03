<?php

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$redis = new Redis();
$redis->pconnect('127.0.0.1', 6379);

?>
