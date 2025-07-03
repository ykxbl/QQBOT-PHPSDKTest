<?php

require_once('../../config/redis.php');
require_once('all.php');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$at = $input['AT'] ?? 'null';

token_y($redis, $at);
$list = $redis->hGetAll('qqbot:robot_info');
$data = [];
foreach($list as $field => $temp){
    $data[] = $field;
}
json_exit(200, 'success', $data);

?>