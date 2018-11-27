<?php

namespace App\Console\Commands;

use App\Ldaplibs\SettingsManager;
use Illuminate\Console\Command;
use DB;

class ImportCSV extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ImportCSV';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reader setting import file and process it';

    /**
     * define const
     */
    const CONVERSION = "CSV Import Process Format Conversion";
    const CONFIGURATION = "CSV Import Process Bacic Configuration";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
//        $setting = $this->setting();
//        dump($setting);
    }

    protected function createTable()
    {

    }

    protected function setting()
    {
//        $import_settings = new SettingsManager();
//        $setting = $import_settings->get_rule_of_import();
//        return json_decode($setting);
    }
}
