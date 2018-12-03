<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    protected $table = 'school';
    // 当前分享的分享产品
    public function country()
    {
        return $this->hasOne('App\Model\Country');
    }

    public function sharer()
    {
        return $this->hasMany('App\Model\Sharer');
    }
}
