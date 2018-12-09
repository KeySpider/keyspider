<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/22/18
 * Time: 11:40 PM
 */

namespace App\Ldaplibs\Import;


use App\Ldaplibs\QueueManager;

class ImportQueueManager extends QueueManager
{
    private $file_list = array();
    public function __construct()
    {
    }

    public function process()
    {
        dd('ok');
    }

    public function getJobInfo(){

    }
}
