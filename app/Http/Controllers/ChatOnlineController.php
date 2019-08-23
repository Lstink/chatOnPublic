<?php

namespace App\Http\Controllers;

use App\models\Common;
use Illuminate\Http\Request;

class ChatOnlineController extends Controller
{
    /**
     * @content 测试web_socket
     */
    public function webSocket()
    {
        //判断用户是否登陆
        // $login = Common::checkLogin();
        return view('webSocket');
    }
    /**
     * @content ajax发送数据
     */
    public function privateChat(Request $request)
    {
        $data=$request->all();
        return view('privateChat');
        dd($data);
    }
}
