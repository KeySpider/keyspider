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
    'addMemberToGroup' => '{
  "schemas": [
    "urn:ietf:params:scim:api:messages:2.0:PatchOp"
  ],
  "Operations": [
    {
      "op": "Add",
      "value":{"members": [
        {	
          "$ref": null,
          "value": "0052v00000gjmlTAAQ"
        }
      ]
    }
    }
  ]
}',
    'createUser' => '{
  "UserName": "user@keyspider.jp",
  "IsActive": true,
  "LastName":"Tuan",
  "FirstName":"Le",
  "Email": "ks@keyspider.jp",
  "Alias": "ks",
  "TimeZoneSidKey": "Asia/Ho_Chi_Minh",
  "LocaleSidKey": "en_GB",
  "EmailEncodingKey": "UTF-8",
  "LanguageLocaleKey": "ja",
  "LastName": "User",
  "LastName": "KeySpider",
  "ProfileId": "00e2w000001IUc1"
}',

    'createGroup' => '{
    "Name":"G1",
    "DeveloperName":"DEV"
}'
];
