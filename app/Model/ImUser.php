<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImUser extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    protected $table = 'im_user';

    // 用户订单
    public function user()
    {
        return $this->belongsTo('App\Model\User');
    }

}
