<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsUpdatedToAaasTable extends Migration
{
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
        $column = $getUpdateFlag[0];
        $column = explode('.', $column);

        Schema::table('AAA', function (Blueprint $table) use ($column) {
            $table->json("{$column[1]}");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('AAA', function (Blueprint $table) {
            //
        });
    }
}
