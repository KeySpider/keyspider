<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserToPrivilege extends Model
{
    protected $table = 'UserToPrivilege';

    // 主キーが数値型ではない場合
    // protected $keyType = 'string';

    public $timestamps = false;

    // belongsTo設定
    public function getUser()
    // {
    //     return $this->belongsTo('App\User')->withDefault();
    // }    

    // public function getPrivileges()
    // {
    //     return $this->belongsToMany('App\Privilege')->withDefault();
    // }

}

