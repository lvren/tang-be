<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    protected $table = 'user';

    // 用户订单
    public function order()
    {
        return $this->hasMany('App\Model\Order');
    }

    // 用户的评论
    public function evaludate()
    {
        return $this->hasMany('App\Model\Evaluate');
    }
}
