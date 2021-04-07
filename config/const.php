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

return [
    'scim_input' => "SCIM Input Bacic Configuration",
    'scim_format' => "SCIM Input Format Conversion",
    'scim_schema' => "urn:ietf:params:scim:schemas:extension:enterprise:2.0:User",
    'updated_flag_default' => [
        'scim' => [
            'isUpdated' => 1,
        ],
        'csv' => [
            'isUpdated' => 1,
        ]
    ],
    'PATH_INI_CONFIGS' => env('PATH_INI_CONFIGS'),
    'INI_CONFIGS' => '/ini_configs/',
    'SET_ALL_EXTRACTIONS_IS_TRUE' => '1',
    'JOB_TITLE' => 'jobTitle',
    'ROLE_ID' => 'RoleID',
    'ROLE_FLAG' => 'RoleFlag-',
    'KSC_VERSION' => '20210407',
];
