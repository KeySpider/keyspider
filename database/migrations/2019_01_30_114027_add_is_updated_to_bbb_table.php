<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->json('updateFlags')->default(json_encode(self::DATA_UPDATED_DEFAULT));
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
