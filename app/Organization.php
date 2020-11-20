<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Organization extends Model
{
    //
    protected $table = 'Organization';
    protected $primaryKey = "ID";

    // // 自動増分ではない場合
    // public $incrementing = false;

    // 主キーが数値型ではない場合
    protected $keyType = 'string';

    public $timestamps = false;

    // hasMany設定
    public function users($org = null)
    {
        return $this->hasMany('App\User', "OrganizationID1");
    }

    // 循環参照
    public function parent()
    {
        return $this->belongsTo('App\Organization', "UpperID")->withDefault();
    }

}
