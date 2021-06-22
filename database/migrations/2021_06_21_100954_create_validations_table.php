<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateValidationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('function');
            $table->string('item');
            $table->string('type');
            $table->string('value1')->nullable();            
            $table->string('value2')->nullable();            
            $table->string('value3')->nullable();            
            $table->string('value4')->nullable();            
            $table->string('value5')->nullable();            
            $table->boolean('delete_flag')->default(false);            
            // $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('validations');
    }
}
