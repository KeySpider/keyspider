<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsUpdatedToCccTable extends Migration
{
    const DATA_UPDATED_DEFAULT = [
        "scim" => [
            "isUpdated" => 0
        ],
        "csv" => [
            "isUpdated" => 0
        ]
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('CCC', function (Blueprint $table) {
            $table->json('isUpdated')->default(json_encode(self::DATA_UPDATED_DEFAULT));
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
