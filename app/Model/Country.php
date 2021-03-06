<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use SoftDeletes;
    protected $dates = ['deleted_at', 'created_at', 'updated_at'];
    protected $hidden = ['deleted_at'];
    protected $table = 'country';

    public function shool()
    {
        return $this->hasMany('App\Model\School');
    }
}
