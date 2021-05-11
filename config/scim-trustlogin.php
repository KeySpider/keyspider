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
    "schemas": [
      "urn:ietf:params:scim:api:messages:2.0:User"
    ],
    "externalId": "(User.ID)",
    "active": (User.DeleteFlag),
    "roles": [
      {
        "value": "Standard"
      }
    ],
    "name": {
      "givenName": "(User.givenName)",
      "familyName": "(User.surname)"
    },
    "userType": "Employee",
    "emails": [
      {
        "value": "(User.mail)"
      }
    ],
    "phoneNumbers": [
      {
        "value": "(User.telephone-Number)"
      }
    ],
    "addresses": [
      {
        "postalCode": "(User.postalCode)",
        "country": "(User.country)",
        "region": "(User.state)",
        "locality": "(User.city)",
        "streetAddress": "(User.streetAddress)"
      }
    ],
    "organization": "(User.Organization)",
    "department": "(User.department)",
    "groups": [
      {
        "value": 0,
        "display": "string"
      }
    ],
    "password": "(User.Password)"
  }',
  'createGroup' => '{
    "Name":"G1",
    "DeveloperName":"DEV"
  }',
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
}'
];
