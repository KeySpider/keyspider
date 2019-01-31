<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsUpdatedToCccTable extends Migration
{
    const DATA_UPDATED_DEFAULT = [
        "scim" => [
            "isUpdated" => 0
        ],
        "csv" => [
            "isUpdated" => 1
        ]
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $settingManagement = new \App\Ldaplibs\SettingsManager();
        $getFlags = $settingManagement->getFlags();
        $getUpdateFlag = $getFlags['updateFlags'];
        $column = $getUpdateFlag[2];
        $column = explode('.', $column);

        Schema::table('CCC', function (Blueprint $table) use ($column) {
            $table->json("{$column[1]}")->default(json_encode(self::DATA_UPDATED_DEFAULT));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('CCC', function (Blueprint $table) {
            //
        });
    }
}
