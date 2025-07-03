<?php

require_once('../../config/redis.php');
require_once('all.php');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$at = $input['AT'] ?? 'null';

token_y($redis, $at);
if(!isset($input['appid']) || !isset($input['appsecret']) || empty($input['appid']) || empty($input['appsecret']) || !is_numeric($input['appid']) || !is_string($input['appsecret'])){
    json_exit(406, '参数错误');
}
$appid = (int)$input['appid'];
$secret = $input['appsecret'];
$redis->hSet('qqbot:robot_info', $appid, $secret);
json_exit();

?>