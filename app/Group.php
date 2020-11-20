<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    //
    protected $table = 'Group';
    protected $primaryKey = "ID";

    // 自動増分ではない場合
    // public $incrementing = false;

    // 主キーが数値型ではない場合
    protected $keyType = 'string';

    public $timestamps = false;

    //belongsTo設定
    public function users()
    {
        return $this->belongsToMany('App\User', 'UserToGroup', 'Group_ID', 'User_ID')
            ->withPivot(['User_ID'])->orderBy('User_ID');
    }    
}
