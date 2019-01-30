<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsUpdatedToBbbTable extends Migration
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
        Schema::table('BBB', function (Blueprint $table) {
            $table->json('013')->default(json_encode(self::DATA_UPDATED_DEFAULT));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('BBB', function (Blueprint $table) {
            //
        });
    }
}
