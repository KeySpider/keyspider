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
    "status": "(User.DeleteFlag)",
    "name": "(User.name)",
    "login": "(User.login)",
    "language": "ja",
    "timezone": "Asia/Tokyo",
    "job_title": "(User.job_title)",
    "phone": "(User.phone)",
    "address": "(User.joinAddress)"
  }',
  'createGroup' => '{
    "name": "(Group.name)"
  }',
  'addGroup' => '{
    "user": {
      "id": "(upn)"
    },
    "group": {
      "id": "(gpn)"
    }
  }'
];

/*

  'createUser' => '{
    "id": 11446498,
    "type": "user",
    "address": "900 Jefferson Ave, Redwood City, CA 94063",
    "avatar_url": "https://www.box.com/api/avatar/large/181216415",
    "created_at": "2012-12-12T10:53:43-08:00",
    "job_title": "CEO",
    "language": "en",
    "login": "ceo@example.com",
    "max_upload_size": 2147483648,
    "modified_at": "2012-12-12T10:53:43-08:00",
    "name": "Aaron Levie",
    "notification_email": {
      "email": "notifications@example.com",
      "is_confirmed": true
    },
    "phone": 6509241374,
    "space_amount": 11345156112,
    "space_used": 1237009912,
    "status": "active",
    "timezone": "Asia/Tokyo"
  }',
  'createGroup' => '{
    "id": 11446498,
    "type": "group",
    "created_at": "2012-12-12T10:53:43-08:00",
    "group_type": "managed_group",
    "modified_at": "2012-12-12T10:53:43-08:00",
    "name": "Support"  
  }'

*/