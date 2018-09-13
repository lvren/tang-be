<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $table = 'user';

    // 当前分享的分享产品
    public function order()
    {
        return $this->hasMany('App\Order');
    }
}
