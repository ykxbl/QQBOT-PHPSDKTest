<?php

class qqbot{
    
    private $headers, $body, $msg, $appid, $secret, $access_token, $redis, $qqbot_list;
    private $message_call = [];
    private $seq = 1;
    //QQ机器人请求地址
    const BOT_URL = 'https://api.sgroup.qq.com';
    //Redis Stream最多消息数量，默认500
    const MAX_STREAM_LENGTH = 500;
    //Redis保留字段 防止被嘿壳嘿
    const QQBOT_RRIVATE = ['config', 'robot_info', 'messages', 'robot_access_token'];
    
    public function __construct(Redis $redis, $array = []){
        $this->redis = $redis;
        $this->headers = getallheaders();
        $this->appid = $this->headers['X-Bot-Appid'] ?? null;
        $this->body = file_get_contents('php://input');
        if(!in_array($this->appid, $this->robot_list())){
            exit;
        }
        $this->secret = $this->robot_secret($this->appid);
        $this->robot_access_token($this->appid);
        $this->message_call = $array;
        $this->msg = $this->message(file_get_contents('php://input'));
        $this->message_call($this->msg);
    }
    
    
    private function message_call($msg){
        $list = $this->message_call;
        $type = $msg['source'] . '_' . $msg['a_type'];
        foreach($list as $name => $function){
            if($name == $type){
                call_user_func($function, $this, $msg);
            }
        }
    }
    
    public function robot_list(){
        $this->qqbot_list = $this->redis->hGetAll('qqbot:robot_info') ?? ['yuanshen' => 'qidong'];
        $array = [];
        foreach($this->qqbot_list as $key => $secret){
            $array[] = $key;
        }
        return $array;
    }
    
    public function read($path, $key, $value = null){
        if(in_array($path, self::QQBOT_RRIVATE, true)){
            trigger_error('\''.$path.'\' is a reserved field', E_USER_WARNING);
        }
        $v = $this->redis->hGet('qqbot:' . $path, $key);
        if(empty($v)){
            $v = $value;
        }
        return $v;
    }
    
    public function write($path, $key, $value){
        if(in_array($path, self::QQBOT_RRIVATE, true)){
            trigger_error('\''.$path.'\' is a reserved field', E_USER_WARNING);
        }
        $this->redis->hSet('qqbot:' . $path, $key, $value);
        return true;
    }
    
    public function message_reply($array){
        $content = $array['content'] ?? '未传入内容';
        $type = $array['type'] ?? 'text';
        $msg = $this->msg;
        $msgid = $msg['msgid'];
        $source = $msg['source'];
        $msgseq = $array['seq'] ?? $this->seq;
        $msgon = $msg['msgon'];
        $t = $msg['t'];
        switch($source){
            case 'group':
                $group = $msg['group'];
                $path = '/v2/groups/'.$group.'/messages';
                $body = $this->message_r($type, $content);
                $body['msg_seq'] = $msgseq;
                $body[$msgon] = $msgid;
                break;
            case 'c2c':
                $users = $msg['qq'];
                $path = '/v2/users/'.$users.'/messages';
                $body = $this->message_r($type, $content);
                $body['msg_seq'] = $msgseq;
                $body[$msgon] = $msgid;
        }
        $this->log('body', json_encode($body));
        $re = $this->authorization_request($path, $body);
        $this->seq ++;
        return $re;
    }
    
    public function message_r($type, $content){
        $r = [];
        $type_array = ['text' => 0, 'keyboard' => 2];
        $r['msg_type'] = $type_array[$type] ?? 0;
        switch($type){
            case 'text':
                $r['content'] = $content;
                break;
            case 'keyboard':
                $r['content'] = 'hello';
                $r['keyboard'] = ['id' => $content];
                break;
        }
        return $r;
    }
    
    public function message($message = null){
        if($message === null){
            $message = $this->msg;
        }
        if(!is_array($message)){
            $msg = json_decode($message, true);
            if($msg === false){
                trigger_error('Parameter message is not JSON', E_USER_ERROR);
            }
        }else{
            $msg = $message;
        }
        $op = $msg['op'] ?? 'null';
        switch($op){
            case 'null':
                trigger_error('JSON does not contain this key \'op\'', E_USER_ERROR);
                return [];
                break;
            case 13:
                $this->callback_verification($msg);
                exit();
                break;
            case 0:
                $msgid = $msg['d']['id']; //msgid
                if($this->msg_repeated($msgid)){
                    return;
                }else{
                    $this->redis->xAdd('qqbot:messages', md5($msgid) . '-*', [], self::MAX_STREAM_LENGTH, true);
                }
                $type = $msg['t'];
                switch($type){
                    case 'GROUP_AT_MESSAGE_CREATE':
                        $qq = $msg['d']['author']['id']; //openid
                        $group = $msg['d']['group_id']; //group_openid
                        $content = $msg['d']['content']; //content
                        $timestamp = $msg['d']['timestamp']; //timestamp
                        $source = 'group'; //message form
                        $a_type = 'message';
                        $msgon = 'msg_id';
                        break;
                    case 'C2C_MESSAGE_CREATE':
                        $qq = $msg['d']['author']['id']; //openid
                        $group = null;
                        $content = $msg['d']['content']; //content
                        $timestamp = $msg['d']['timestamp']; //timestamp
                        $source = 'c2c'; //message form
                        $a_type = 'message';
                        $msgon = 'msg_id';
                        break;
                }
                $msg = [
                    'appid' => $this->appid,
                    'qq' => $qq,
                    'group' => $group,
                    'content' => $content,
                    'timestamp' => $timestamp,
                    'msgid' => $msgid,
                    'source' => $source,
                    't' => $type,
                    'a_type' => $a_type,
                    'msgon' => $msgon
                ];
                return $msg;
                break;
        }
    }
    
    private function msg_repeated($msgid){
        $lastMessages = $this->redis->xRevRange('qqbot:messages', '+', '-', 5);
        $msgid = md5($msgid);
        foreach($lastMessages as $message){
            if(strpos($message[0], $msgid . '-') === 0){
                return true;
            }
        }
        return false;
    }
    
    private function robot_secret($appid){
        $this->secret = $this->qqbot_list[$appid] ?? null;
        return $this->secret;
    }
    
    private function robot_access_token($appid){
        $redis = $this->redis;
        $access_token = json_decode($redis->hGet('qqbot:robot_access_token', $appid), true) ?? ['last_time' => 0];
        if($access_token['last_time'] > (int)time()){
            $this->access_token = $access_token['access_token'];
            return $access_token['access_token'];
        }
        $secret = $this->secret;
        if($secret === null){
            return null;
        }
        $url = 'https://bots.qq.com/app/getAppAccessToken';
        $body = ['appId' => $appid, 'clientSecret' => $secret];
        $payload = json_decode($this->request_post_json($url, $body), true);
        if(!isset($payload['access_token'])){
            return null;
        }
        $json = json_encode(['access_token' => $payload['access_token'], 'last_time' => (int)time() + (int)$payload['expires_in'] - 20]);
        $redis->hSet('qqbot:robot_access_token', $appid, $json);
        $this->access_token = $payload['access_token'];
        return $payload['access_token'];
    }
    
    public function request_get($url, $value = null, $headers = null){
        if(is_array($value)){
            $value = params_struct($value);
            $url .= '?' . $value;
        }else if($value !== null){
            $url .= '?' . $value;
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if($headers !== null){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $get = curl_exec($curl);
        curl_close($curl);
        return $get;
    }
    
    public function request_post_json($url, $array, $headers = []){
        if(is_array($array)){
            $array = json_encode($array, JSON_UNESCAPED_UNICODE);
        }
        $headers[] = 'Content-Type: application/json';
        return $this->request_post($url, $array, $headers);
    }
    
    public function request_post($url, $value = null, $headers = null){
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true]);
        if(is_array($value)){
            $value = params_struct($value);
        }
        if($value !== null){
            curl_setopt($curl, CURLOPT_POSTFIELDS, $value);
        }
        if($headers !== null){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $get = curl_exec($curl);
        curl_close($curl);
        return $get;
    }
    
    private function callback_verification($payload){
        if (!isset($payload['d'])) {
            return;
        }
        $validationPayload = $payload['d'];
        $seed = $this->secret;
        while (strlen($seed) < SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            $seed = str_repeat($seed, 2);
        }
        $seed = substr($seed, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $privateKey = sodium_crypto_sign_secretkey($keypair);
        $eventTs = isset($validationPayload['event_ts']) ? $validationPayload['event_ts'] : '';
        $plainToken = isset($validationPayload['plain_token']) ? $validationPayload['plain_token'] : '';
        $message = $eventTs . $plainToken;
        // 生成签名
        $signature = sodium_crypto_sign_detached($message, $privateKey);
        $signatureHex = bin2hex($signature);
        $response = [
            'plain_token' => $plainToken,
            'signature' => $signatureHex
        ];
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response);
    }
    
    private function authorization_request($path, $body){
        $access_token = $this->access_token;
        $url = self::BOT_URL . $path;
        $headers = ['Authorization: QQBot ' . $access_token];
        $this->log('system', $this->request_post_json($url, $body, $headers));
    }
    
    private function log($user, $text){
        file_put_contents('qqbot.log', '['.date('Y.m.d H:i:s').'] '.$user . ': ' . $text . "\n", FILE_APPEND);
    }
    
    private function params_struct($value){
        $return = '';
        foreach($value as $k => $v){
            $return .= urlencode($k) . '=' . urlencode($v) . '&';
        }
        return rtrim($return, '&');
    }
}

?>