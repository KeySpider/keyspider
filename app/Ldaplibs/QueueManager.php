<?php
/**
 * Created by PhpStorm.
 * User: tuanla
 * Date: 11/23/18
 * Time: 12:40 AM
 */

namespace App\Ldaplibs;


use App\Jobs\DBImporterJob;

class QueueManager
{

    public function push()
    {
        $importer = new DBImporterJob();
        dispatch($importer);
    }

    public function push_high_priority(){
        $importer = new DBImporterJob();
        dispatch($importer)->onQueue('high');
    }
    public function pop($file)
    {

    }
}