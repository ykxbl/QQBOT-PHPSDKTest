<?php
file_put_contents('log.log', file_get_contents('php://input')."\n\n".json_encode(getallheaders()));
require_once('qqbot.php');

$redis = new Redis();
$redis->pconnect('127.0.0.1', 6379);

$qqbot = new qqbot($redis, ['group_message' => 'msg_group', 'c2c_message' => 'msg_c2c']);

function msg_group($qqbot, $msg){
    $qqbot->message_reply(['content' => bot_tool::json_analysis($msg, "这是一个JSON解析测试\n\n你的openid为@json[qq]")]);
}

function msg_c2c($qqbot, $msg){
    $qqbot->message_reply(['content' => "这是一条单聊消息.."]);
}

?>