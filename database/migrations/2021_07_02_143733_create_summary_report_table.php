<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSummaryReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('SummaryReport', function (Blueprint $table) {
            $table->string('ID');
            $table->string('Operation');
            $table->string('Target');
            $table->string('Table');
            $table->string('Total')->default(0);
            $table->string('Create')->default(0);
            $table->string('Update')->default(0);
            $table->string('Delete')->default(0);
            $table->string('Error')->default(0);
            $table->string('StartDatetime');
            $table->string('EndDatetime');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('SummaryReport');
    }
}
