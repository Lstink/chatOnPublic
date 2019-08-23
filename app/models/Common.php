<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class Common extends Model
{
    /**
     * @content 判断用户是否登陆
     */
    public static function checkLogin()
    {
        if (session('userInfo')) {
            return true;
        }else{
            return false;
        }
    }
}
