<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sharer extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    protected $table = 'sharer';
    // 当前分享的分享产品
    public function product()
    {
        return $this->hasMany('App\Model\Product');
    }

    public function school()
    {
        return $this->belongsTo('App\Model\School');
    }
}
