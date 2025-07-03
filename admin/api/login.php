<?php

require_once('../../config/login.php');
require_once('all.php');
$key = hash('sha256', 'password-' . $login['password']);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$input_token = $input['token'] ?? 'null';

if($key !== $input_token){
    json_exit(403, '密码错误');
}

//随便生成个token返回
$admin_token = hash('sha256', hash('sha256', $key . time()) . hash('sha256', (string)rand(0, 10000)));

try{
    require_once('../../config/redis.php');
    //计算过期时间
    $last_time = (int)time() + $login['time'];
    $redis->hSet('qqbot:admin', hash('sha256', $admin_token), $last_time);
    json_exit(200, 'success', ['AT' => $admin_token]);
}catch(Throwable $t){
    file_put_contents('log.log', $t->getMessage());
    json_exit(500, '服务器内部错误');
}

?>