<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEuroExchangeRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('euro_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency')->unique()->index();
            $table->double('rate');
            $table->timestamps();
        });

        $repo = new \App\Repositories\EuroExchangeRateRepository();
        $exchangeService = new \App\Services\CurrencyExchangeRate();
        $exchangeService->getAllExchanges($repo);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('euro_exchange_rates');
    }
}
