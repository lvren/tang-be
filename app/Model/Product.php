<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'product';
    // 分享者的信息
    public function share()
    {
        return $this->hasOne('App\Share');
    }
}
