<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'user';

    // 当前分享的分享产品
    public function order()
    {
        return $this->hasMany('App\Order');
    }
}
