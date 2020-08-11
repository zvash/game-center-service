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

        GameConfig::create(['coin_price', 20]);

        GameConfig::create(['total_levels', 7]);

        GameConfig::create(['win_prize.level_1', 2]);
        GameConfig::create(['win_prize.level_2', 4]);
        GameConfig::create(['win_prize.level_3', 8]);
        GameConfig::create(['win_prize.level_4', 16]);
        GameConfig::create(['win_prize.level_5', 32]);
        GameConfig::create(['win_prize.level_6', 64]);
        GameConfig::create(['win_prize.level_7', 128]);

        GameConfig::create(['leave_prize.level_1', 1]);
        GameConfig::create(['leave_prize.level_2', 2]);
        GameConfig::create(['leave_prize.level_3', 3]);
        GameConfig::create(['leave_prize.level_4', 4]);
        GameConfig::create(['leave_prize.level_5', 5]);
        GameConfig::create(['leave_prize.level_6', 6]);
        GameConfig::create(['leave_prize.level_7', 7]);

        GameConfig::create(['reveal_price', 50]);

        GameConfig::create(['box_count.level_1', 2]);
        GameConfig::create(['box_count.level_2', 3]);
        GameConfig::create(['box_count.level_3', 4]);
        GameConfig::create(['box_count.level_4', 5]);
        GameConfig::create(['box_count.level_5', 6]);
        GameConfig::create(['box_count.level_6', 7]);
        GameConfig::create(['box_count.level_7', 8]);

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
