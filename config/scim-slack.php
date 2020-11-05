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
    'createUser' => '{
      "schemas":[
          "urn:scim:schemas:core:1.0"
       ],
       "externalId":"(User.ID)",
       "userName":"(User.userName)",
       "nickName":"",
       "name":{
          "givenName": "(User.givenName)",
          "familyName": "(User.familyName)"
      },
       "displayName":"(User.displayName)",
       "profileUrl":"",
       "title":"(User.title)",
       "timezone":"Asia/Tokyo",
       "active":(User.DeleteFlag),
       "emails":[
          {
             "value":"(User.mail)",
             "primary":true
          }
       ],
       "photos":[
          {
             "value":"",
             "type":"photo"
          }
       ],
       "addresses": [
          {
              "streetAddress": "(User.streetAddress)",
              "locality": "(User.locality)",
              "region": "(User.region)",
              "postalCode": "(User.postalCode)",
              "country": "(User.country)",
          }
       ],
       "phoneNumbers": [
          {
              "value": "(User.telephone)",
              "type": "work",
              "primary": true
          }
       ],
       "userType": "Employee",
       "roles": [
          {
              "value": "(User.RoleName)",
              "primary": true
          }
      ],
       "preferredLanguage": "ja_JP",
       "locale": "ja_JP",
       "groups":[
    
       ]
    },
}',
'createGroup' => '{
  "schemas": [
    "urn:scim:schemas:core:1.0"
  ],
  "id": "(Organization.externalID)",
  "displayName": "(Organization.DisplayName)",
  "members": [],
  "meta": {
      "created": "",
      "location": ""
  }
}'
  ];
  