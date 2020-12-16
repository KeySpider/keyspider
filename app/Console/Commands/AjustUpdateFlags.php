<?php

namespace App\Console\Commands;

use App\Ldaplibs\SettingsManager;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AjustUpdateFlags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ajust_updateflags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ajust UpdateFlags';

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
        $this->alterUpdateFlags('User');
        $this->alterUpdateFlags('Role');
        $this->alterUpdateFlags('Group');
        $this->alterUpdateFlags('Organization');
        $this->alterUpdateFlags('UserToGroup');
    }

    private function alterUpdateFlags($nameTable)
    {
        $alterTableName = $nameTable;
        if ($nameTable == 'UserToGroup' || 
            $nameTable == 'UserToOrganization' || 
            $nameTable == 'UserToRole') {
            $alterTableName = str_replace('UserTo', '', $nameTable);
        }

        // $nameTable = 'User';
        $settingManagement = new SettingsManager();
        // $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($nameTable);
        $colUpdateFlag = $settingManagement->getUpdateFlagsColumnName($alterTableName);
        // set UpdateFlags
        // $updateFlagsJson = $settingManagement->makeUpdateFlagsJson($nameTable);
        // $updateFlagsArray = $settingManagement->getAllExtractionProcessID($nameTable);
        $updateFlagsArray = $settingManagement->getAllExtractionProcessID($alterTableName);

        $users = DB::table($nameTable)->get()->toArray();
        Log::info($nameTable . " Find out " . count($users) . " in total");
        echo $nameTable . " Find out " . count($users) . " in total\n";

        $effectives = 0;
        foreach ($users as $user) {
            $updateFlags = json_decode($user->UpdateFlags, true);
            foreach ($updateFlagsArray as $processId) {
                if (empty($updateFlags[$processId])) {
                    $updateFlags[$processId] = '1';
                    $effectives += 1;
                }
            }

            $setValues[$colUpdateFlag] = json_encode($updateFlags);
            DB::table($nameTable)->where("ID", $user->ID)->update($setValues);
        }
        Log::info($nameTable . " Changed " . $effectives . " processIDs");
        echo $nameTable . " Changed " . $effectives . " processIDs\n";
    }

}
