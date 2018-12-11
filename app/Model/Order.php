<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'order';

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];

    // 分享者的信息
    public function user()
    {
        return $this->belongsTo('App\Model\User');
    }

    // 分享者的信息
    public function product()
    {
        return $this->belongsTo('App\Model\Product');
    }
}
