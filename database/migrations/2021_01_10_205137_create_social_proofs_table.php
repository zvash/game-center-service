<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialProofsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('social_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->index()->unique();
            $table->unsignedInteger('play_count');
            $table->double('won_amount');
            $table->string('currency');
            $table->text('comment')->nullable()->default(null);
            $table->boolean('visible')->default(false);
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
        Schema::dropIfExists('social_proofs');
    }
}
