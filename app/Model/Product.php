<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];

    protected $table = 'product';
    // 分享者的信息
    public function share()
    {
        return $this->hasOne('App\Share');
    }
}
