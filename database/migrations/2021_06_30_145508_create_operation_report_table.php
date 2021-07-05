<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOperationReportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('OperationReport', function (Blueprint $table) {
            $table->string('ReportDate');
            $table->string('UserActive')->default(0);
            $table->string('UserLock')->default(0);
            $table->string('UserDelete')->default(0);
            $table->string('OrganizationActive')->default(0);
            $table->string('OrganizationDelete')->default(0);
            $table->string('RoleActive')->default(0);
            $table->string('RoleDelete')->default(0);
            $table->string('GroupActive')->default(0);
            $table->string('GroupDelete')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('OperationReport');
    }
}
