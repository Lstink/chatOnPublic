<?php

//创建websocket服务器对象，监听0.0.0.0:9502端口

$ws = new swoole_websocket_server("0.0.0.0", 9502);

//监听WebSocket连接打开事件
$ws->on('open', function ($ws, $request) {
    // var_dump($request->fd, $request->get, $request->server);
    // $ws->push($request->fd, "hello, welcome\n");
});

//监听WebSocket消息事件
$ws->on('message', function ($ws, $frame) {
    if (!json_decode($frame->data,true)) {
        return false;
    }
    echo 'fd: '.$frame->fd.' Message:'.$frame->data."\n\r";
    //处理数据
    $webSocket = new WebSocketClass($ws,$frame);
    $webSocket -> doDate($frame->data);

});

//监听WebSocket连接关闭事件
$ws->on('close', function ($ws, $fd) {
    $webSocket = new WebSocketClass($ws,$fd);
    //获取用户名称
    $data = $webSocket -> getUsers();
    $username = $data[$fd]??null;
    if ($username) {
        //清除用户的信息
        $webSocket -> cleanUser();
        //刷新用户列表
        $webSocket -> refreshUserListByOut($fd,$username);
    }
    // echo "client-{$fd} is closed\n";
});

//心跳检测
$ws->set(array(
    //每多长时间检测一次
    'heartbeat_check_interval' => 60,
    //多长时间后关闭连接
    'heartbeat_idle_time' => 1800,
));


$ws->start();



class WebSocketClass
{
    private $ws;
    private $frame;
    private $db;
    private $redis;

    public function __construct($ws,$frame)
    {
        $this -> ws = $ws;
        $this -> frame = $frame;
        $this -> db = new Swoole\Coroutine\MySQL();
        $this -> db->connect([
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'root',
            'password' => '95811yyy',
            'database' => 'edu',
        ]);
        $this-> redis = new Swoole\Coroutine\Redis();
        $this-> redis->connect('127.0.0.1', 6379);
    }
    /**
     * @content 数据处理
     */
    public function doDate($json)
    {
        //将json转化为数组
        $data = json_decode($json,true);
        if (!$data) {
            return false;
        }
        //判断action
        switch ($data['action']) {
            case 'login':
                return $this -> login($data);
                break;
            case 'chatOnPublic':
                return $this->chatOnPublic($data);
                break;
            case 'chatOnPrivate':
                return $this->chatOnPrivate($data);
                break;
            
            default:
                return json_encode([]);
                break;
        }
        
    }
    /**
     * @content 登陆方法
     */
    public function login($data)
    {
        $stmt = $this -> db->prepare('SELECT * FROM users WHERE username=?');
        if ($stmt == false) {
            return false;
        } else {
            $res = $stmt->execute(array($data['username']));
            //判断用户的密码是否正确
            if (password_verify($data['pwd'],$res[0]['pwd'])) {
                //推送登陆成功的消息
                $json = json_encode(['data'=>$data['username'],'action'=>'login','code'=>200]);
                //获取用户
                $this -> getUserByRedis($data);
                //刷新用户列表
                $this -> refreshUserListByLogin($data['username']);
            }else{
                $json = json_encode(['data'=>[],'action'=>'login','code'=>400,'msg'=>'密码错误']);
            }
            $this -> ws -> push($this->frame ->fd,$json);
        }
        
    }
    /**
     * @content 登陆成功，将用户存入redis，获取用户
     */
    public function getUserByRedis($data)
    {

        //获取当前用户的fd
        $fd = $this -> frame -> fd;
        //获取redis中的用户
        $userArr = $this -> getUsers();
        //将用户存入redis
        $userArr = $userArr + [$fd=>$data['username']];
        $this->redis->set('chatOnline_users',json_encode($userArr));
        //发送当前用户
        $data = [
            'code'=>200,
            'action'=>'fd',
            'user'=>['fd'=>$fd,'username'=>$data['username']],
        ];
        $this -> ws -> push($fd,json_encode($data));
    }
    /**
     * @content 清理用户的信息
     */
    public function cleanUser()
    {
        $data = $this -> getUsers();
        //将该用户的信息删除
        $fd = $this -> frame;
        unset($data[$fd]);
        $this -> redis -> set('chatOnline_users',json_encode($data));
    }
    /**
     * @content 登录刷新所有用户的列表信息
     */
    public function refreshUserListByLogin($username)
    {
        $userList = $this->getUsers();
        //发送用户列表信息
        $data = [
            'code'=>200,
            'data'=>$userList,
            'action'=>'userList',
            'user'=>['fd'=>$this->frame->fd,'username'=>$username],
        ];
        $this->sendMessageToAll($data);
    }
    /**
     * @content 退出刷新所有用户列表
     */
    public function refreshUserListByOut($fd,$username)
    {
        $userList = $this->getUsers();
        //发送用户列表信息
        $data = [
            'code'=>200,
            'data'=>$userList,
            'action'=>'out',
            'user'=>['fd'=>$fd,'username'=>$username],
        ];
        $this->sendMessageToAll($data);
    }
    /**
     * @content 从redis中获取所有用户
     */
    public function getUsers()
    {
        //取出redis的数据
        $data = $this -> redis -> get('chatOnline_users');
        if ($data) {
            $data = json_decode($data,true);
        }else{
            $data = [];
        }
        return $data;
    }
    /**
     * @content 给所有用户推送消息
     */
    public function sendMessageToAll($data)
    {
        $userList = $this->getUsers();
        foreach ($userList as $k => $v) {
            $this -> ws -> push($k,json_encode($data));
        }
    }
    /**
     * @content 给指定用户推送消息
     */
    public function sendMessageToUser($fd,$data)
    {
        $this -> ws -> push($fd,json_encode($data));
    }
    /**
     * @content 聊天室
     */
    public function chatOnPublic($data)
    {
        $username = $this -> getUserNameByFd($data['fd']);
        //将数据发送给所有用户
        $data= [
            'code'=>200,
            'data'=>$data['message'],
            'action'=>'chatOnPublic',
            'time'=>date('Y-m-d H:i:s'),
            'user'=>['fd'=>$data['fd'],'username'=>$username],
            'type'=>'public'
        ];
        $this->sendMessageToAll($data);
    }
    /**
     * @content 私有聊天室
     */
    public function chatOnPrivate($data)
    {
        $username = $this -> getUserNameByFd($data['fd']);
        //将数据发送给指定用户
        $arr= [
            'code'=>200,
            'data'=>$data['message'],
            'action'=>'chatOnPrivate',
            'time'=>date('Y-m-d H:i:s'),
            'user'=>['fd'=>$data['fd'],'username'=>$username],
            'type'=>'private'
        ];
        $this->sendMessageToUser($data['to_fd'],$arr);
        //给自己也发一条
        $this->sendMessageToUser($this->frame->fd,$arr);
    }
    /**
     * @content 根据fd获取用户的名字
     */
    public function getUserNameByFd($fd)
    {
        $data = $this -> getUsers();
        $username = $data[$fd]??null;
        return $username;
    }
    
}