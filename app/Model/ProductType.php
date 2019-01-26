<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    protected $table = 'product_type';
    // 分享者的信息
    public function sharer()
    {
        return $this->belongsTo('App\Model\Sharer');
    }

    // 订单信息
    public function order()
    {
        return $this->hasMany('App\Model\Order');
    }
}
