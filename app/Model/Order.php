<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'order';

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    // 分享者的信息
    public function user()
    {
        return $this->hasOne('App\User');
    }

    // 分享者的信息
    public function product()
    {
        return $this->hasOne('App\Product');
    }
}
