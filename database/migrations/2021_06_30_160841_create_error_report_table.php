<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateErrorReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ErrorReport', function (Blueprint $table) {
            $table->string('ID');
            $table->string('KeyspiderID');
            $table->string('ExternalID');
            $table->string('ErrorDetail');
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
        Schema::dropIfExists('ErrorReport');
    }
}
