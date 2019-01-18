<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('externalId')->nullable();
            $table->string('userName')->nullable();
            $table->boolean('active')->nullable();
            $table->text('addresses')->nullable();
            $table->string('displayName')->nullable();
            $table->json('meta')->nullable();
            $table->json('name')->nullable();
            $table->text('phoneNumbers')->nullable();
            $table->text('roles')->nullable();
            $table->string('title')->nullable();
            $table->json('department')->nullable();
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
        Schema::dropIfExists('users');
    }
}
