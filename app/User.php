<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'User';
    protected $primaryKey = "ID";

    // 自動増分ではない場合
    // public $incrementing = false;

    // 主キーが数値型ではない場合
    protected $keyType = 'string';

    // created_at, updated_at を自動更新しない
    public $timestamps = false;

    //belongsTo設定
    public function organization()
    {
        return $this->belongsTo('App\Organization', "OrganizationID1", "ID")->withDefault();
    }

    public function groups()
    {
        return $this->belongsToMany('App\Group', 'UserToGroup', 'User_ID', 'Group_ID')
            ->withPivot(['Group_ID'])->orderBy('Group_ID');
    }

    public function organizations()
    {
        return $this->belongsToMany('App\Organization', 'UserToOrganization', 'User_ID', 'Organization_ID')
            ->withPivot(['Organization_ID'])->orderBy('Organization_ID');
    }

    public function roles()
    {
        return $this->belongsToMany('App\Role', 'UserToRole', 'User_ID', 'Role_ID')
            ->withPivot(['Role_ID'])->orderBy('Role_ID');
    }

    public function privileges()
    {
        return $this->belongsToMany('App\Privilege', 'UserToPrivilege', 'User_ID', 'Privilege_ID')
            ->withPivot(['Privilege_ID'])->orderBy('Privilege_ID');
    }
}
