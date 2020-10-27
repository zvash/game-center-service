<?php

use App\GameConfig;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGameConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('game_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->string('value');
            $table->timestamps();
        });

        GameConfig::create(['key' => 'game_price', 'value' => 20]);

        GameConfig::create(['key' => 'total_levels', 'value' => 7]);

        GameConfig::create(['key' => 'win_prize.level_1', 'value' => 2]);
        GameConfig::create(['key' => 'win_prize.level_2', 'value' => 4]);
        GameConfig::create(['key' => 'win_prize.level_3', 'value' => 8]);
        GameConfig::create(['key' => 'win_prize.level_4', 'value' => 16]);
        GameConfig::create(['key' => 'win_prize.level_5', 'value' => 32]);
        GameConfig::create(['key' => 'win_prize.level_6', 'value' => 64]);
        GameConfig::create(['key' => 'win_prize.level_7', 'value' => 128]);

        GameConfig::create(['key' => 'leave_prize.level_1', 'value' => 1]);
        GameConfig::create(['key' => 'leave_prize.level_2', 'value' => 2]);
        GameConfig::create(['key' => 'leave_prize.level_3', 'value' => 3]);
        GameConfig::create(['key' => 'leave_prize.level_4', 'value' => 4]);
        GameConfig::create(['key' => 'leave_prize.level_5', 'value' => 5]);
        GameConfig::create(['key' => 'leave_prize.level_6', 'value' => 6]);
        GameConfig::create(['key' => 'leave_prize.level_7', 'value' => 7]);

        GameConfig::create(['key' => 'prize_currency', 'value' => 'EUR']);

        GameConfig::create(['key' => 'reveal_price', 'value' => 50]);
        GameConfig::create(['key' => 'reveal_min_boxes', 'value' => 3]);

        GameConfig::create(['key' => 'box_count.level_1', 'value' => 2]);
        GameConfig::create(['key' => 'box_count.level_2', 'value' => 3]);
        GameConfig::create(['key' => 'box_count.level_3', 'value' => 4]);
        GameConfig::create(['key' => 'box_count.level_4', 'value' => 5]);
        GameConfig::create(['key' => 'box_count.level_5', 'value' => 6]);
        GameConfig::create(['key' => 'box_count.level_6', 'value' => 7]);
        GameConfig::create(['key' => 'box_count.level_7', 'value' => 8]);

        GameConfig::create(['key' => 'seconds_to_play', 'value' => 60]);


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('game_configs');
    }
}
