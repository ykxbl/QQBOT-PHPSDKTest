<?php

function json_exit($code = 200, $msg = 'success', $data = []){
    exit(json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function token_y($redis, $at = null){
    try{
        $get = $redis->hGet('qqbot:admin', hash('sha256', $at));
        if(!$get){
            json_exit(403, '鉴权失败');
        }else if(time() > $get){
            json_exit(405, '鉴权令牌过期');
        }
        return true;
    }catch(Throwable $t){
        json_exit(500, '服务器内部错误');
    }
}

?>
