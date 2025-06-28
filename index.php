<?php
file_put_contents('log.log', file_get_contents('php://input')."\n\n".json_encode(getallheaders()));
require_once('qqbot.php');

$redis = new Redis();
$redis->pconnect('127.0.0.1', 6379);

$qqbot = new qqbot($redis, ['group_message' => 'msg_group', 'c2c_message' => 'msg_c2c']);

function msg_group($qqbot, $msg){
    $read = $qqbot->read("ces", $msg['qq'], '默认');
    $rand = rand(100, 10000);
    $qqbot->message_reply(['content' => "\n读取到" . (string)$read . "\n随机到" . (string)$rand]);
    $qqbot->write("ces", $msg['qq'], $rand);
}

function msg_c2c($qqbot, $msg){
    $qqbot->message_reply(['content' => "这是一条单聊消息.."]);
}

?>