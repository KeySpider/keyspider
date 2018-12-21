<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDeliveryHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('output_type')->nullable();
            $table->string('delivery_source')->nullable();
            $table->string('delivery_target')->nullable();
            $table->dateTime('execution_at')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('rows_count')->nullable();
            $table->tinyInteger('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_histories');
    }
}
