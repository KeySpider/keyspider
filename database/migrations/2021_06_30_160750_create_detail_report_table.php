<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDetailReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('DetailReport', function (Blueprint $table) {
            $table->string('ID');
            $table->string('KeyspiderID');
            $table->string('ExternalID');
            $table->string('CrudType');
            $table->string('Status');
            $table->string('DataDetail');
            $table->string('ProcessedDatetime');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('DetailReport');
    }
}
