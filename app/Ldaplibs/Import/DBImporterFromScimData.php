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

use Illuminate\Support\Facades\Log;

class DBImporterFromScimData
{
    public const SCHEMAS_EXTENSION_USER = 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User';

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

        $isSave = $scimReader->importFromSCIMData($this->dataPost, $this->setting);

        if ($isSave) {
            return true;
        }

        Log::error('Error of insert user to database');
        return false;
    }
}
