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

namespace App\Ldaplibs\SCIM;

use Illuminate\Contracts\Support\Jsonable;

class Error implements Jsonable
{
    protected $detail;
    protected $status;
    protected $scimType;
    protected $errors;

    public function toJson($options = 0)
    {
        return json_encode(array_filter([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'],
            'detail' => $this->detail,
            'status' => $this->status,
            'scimType' => $this->status === 400 ? $this->scimType : null,

            // not defined in SCIM 2.0
            'errors' => $this->errors
        ]), $options);
    }

    public function __construct($detail, $status = '404', $scimType = 'invalidValue')
    {
        $this->detail = $detail;
        $this->status = $status;
        $this->scimType = $scimType;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
        return $this;
    }
}
