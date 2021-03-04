<?php

namespace App\Console\Commands;

use App\Ldaplibs\SettingsManager;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VersionInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'command:version';
    protected $signature = 'version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show keyspider version';

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
     */
    public function handle()
    {
        // echo <<< AA
        //  _                              _      _
        // | |                            (_)    | |
        // | | __  ___  _   _  ___  _ __   _   __| |  ___  _ __
        // | |/ / / _ \| | | |/ __|| '_ \ | | / _` | / _ \| '__|
        // |   < |  __/| |_| |\__ \| |_) || || (_| ||  __/| |
        // |_|\_\ \___| \__, ||___/| .__/ |_| \__,_| \___||_|
        //               __/ |     | |
        //              |___/      |_|
        // AA;

        // echo <<< AA
        //  __                                 __     __
        // |  |--..-----..--.--..-----..-----.|__|.--|  |.-----..----.
        // |    < |  -__||  |  ||__ --||  _  ||  ||  _  ||  -__||   _|
        // |__|__||_____||___  ||_____||   __||__||_____||_____||__|
        //               |_____|       |__|
        // AA;
        
        $version = sprintf("Keyspider\nversion %s\n", config('const.KSC_VERSION'));
        echo($version);
    }
}
