<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserToGroup extends Model
{
    //
    // protected $table = 'User';
    // protected $primaryKey = "ID";

    // 自動増分ではない場合
    // public $incrementing = false;

    // 主キーが数値型ではない場合
    protected $keyType = 'string';

    public $timestamps = false;

    // belongsTo設定
    public function getUser()
    {
        return $this->belongsTo('App\User')->withDefault();
    }    

    public function getGroups()
    {
        return $this->belongsToMany('App\Group')->withDefault();
    }    

}
