<?php
/*******************************************************************************
 * Key Spider
 * Copyright (C) 2019 Key Spider Japan LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 ******************************************************************************/

namespace App\Ldaplibs\Import;

use App\Http\Models\User;
use App\Http\Models\UserResource;
use Illuminate\Support\Facades\Log;

class DBImporterFromScimData
{
    const SCHEMAS_EXTENSION_USER = "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User";

    protected $dataPost;
    protected $setting;

    public function __construct($dataPost, $setting)
    {
        $this->dataPost = $dataPost;
        $this->setting = $setting;
    }

    public function importToDBFromDataPost(): bool
    {
        $scimReader = new SCIMReader();

        $scimReader->addColumns($this->setting);
        $scimReader->getFormatData($this->dataPost, $this->setting);

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
        try {
            User::create($dataToSaveToDB);
            return true;
        } catch (\Exception $exception) {
            Log::error("Error of insert user to database");
            return false;
        }
    }
}
