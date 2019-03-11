<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ViewReport extends Model
{
    use SoftDeletes;

    protected $table = 'view_report';

    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    // 分享者的信息
    public function user()
    {
        return $this->hasOne('App\Model\User');
    }
}
