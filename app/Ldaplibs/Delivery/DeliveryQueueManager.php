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

namespace App\Ldaplibs\Delivery;

use App\Ldaplibs\QueueManager;

class DeliveryQueueManager extends QueueManager
{
    private $fileList;
    private $history;

    public function __construct($file_list = null)
    {
        $this->fileList = $file_list;
        $this->history = new DeliveryHistoryManager();
    }

    public function process()
    {
        $this->history->saveHistory($something = array());
    }

    public function getHistory()
    {
        return $this->history;
    }
}
