<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Privilege extends Model
{
    protected $table = 'Privilege';
    protected $primaryKey = "ID";

    // 主キーが数値型ではない場合
    protected $keyType = 'string';

    public $timestamps = false;

    //belongsTo設定
    public function user()
    {
        return $this->belongsToMany('App\User', 'UserToPrivilege', 'Privilege_ID', 'User_ID')
            ->withPivot(['User_ID'])->orderBy('User_ID');
    }

    public function role()
    {
        return $this->belongsToMany('App\Role', 'RoleToPrivilege', 'Privilege_ID', 'Role_ID')
            ->withPivot(['Role_ID'])->orderBy('Role_ID');
    }
}
