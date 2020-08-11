<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id')->index();
            $table->integer('level_index')->index();
            $table->integer('boxes_count');
            $table->integer('winner_box')->index();
            $table->integer('chosen_box')->nullable()->default(null);
            $table->string('revealable_boxes');
            $table->integer('reveal_price');
            $table->integer('win_prize');
            $table->integer('leave_prize');
            $table->timestamp('last_move_time')->index();
            $table->string('status')->index()->default('inactive');
            $table->timestamps();

            $table->unique(['game_id', 'level_index']);

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('levels');
    }
}
