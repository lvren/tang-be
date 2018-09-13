<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $table = 'sharer';
    // 当前分享的分享产品
    public function product()
    {
        return $this->hasMany('App\Product');
    }
}
