<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksMethodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks_methods', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->uuid('task_id')->index();
            $table->string('method', 20)->index();
            $table->integer('classification')->nullable();
            $table->float('prediction_score')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->foreign('task_id')->references('id')->on('tasks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tasks_methods');
    }
}
