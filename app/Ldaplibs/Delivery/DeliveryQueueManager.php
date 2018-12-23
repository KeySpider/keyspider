<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 11:40 PM
 */

namespace App\Ldaplibs\Import;

use App\Ldaplibs\Delivery\DeliveryHistoryManager;
use App\Ldaplibs\QueueManager;

class DeliveryQueueManager extends QueueManager
{
    private $file_list = array();
    private $history;
    public function __construct($file_list = null)
    {
        $this->file_list = $file_list;
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
