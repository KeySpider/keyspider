<?php
/**
 */

namespace App\Ldaplibs\Import;


use App\Http\Models\User;
use App\Http\Models\UserResource;

class DBImporterFromScimData
{
    public const SCHEMAS_EXTENSION_USER = "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User";
    protected $dataPost;
    public function __construct($dataPost)
    {
        $this->dataPost = $dataPost;
    }

    public function importToDBFromDataPost(): void
    {
        $dataPost = $this->dataPost;
        UserResource::create([
            "data" => json_encode($dataPost, JSON_PRETTY_PRINT),
        ]);

        $dataToSaveToDB = [
            'firstName' => isset($dataPost['name']['givenName'])? $dataPost['name']['givenName']:"",
            'familyName' => isset($dataPost['name']['familyName'])?$dataPost['name']['familyName']:"",
            'fullName' => isset($dataPost['name']['formatted'])?$dataPost['name']['formatted']:"",
            'externalId' => $dataPost['externalId'],
            'email' => $dataPost['userName'],
            'displayName' => $dataPost['displayName'],
            'role_id' => $dataPost['title'],
            'organization_id' => $dataPost[self::SCHEMAS_EXTENSION_USER]['department'],
        ];

        // save users model
        User::create($dataToSaveToDB);
    }
}