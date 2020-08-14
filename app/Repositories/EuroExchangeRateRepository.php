<?php

namespace App\Repositories;


use App\EuroExchangeRate;

class EuroExchangeRateRepository
{
    /**
     * @param array $rates
     */
    public function updateRates(array $rates)
    {
        foreach ($rates as $currency => $rate) {
            EuroExchangeRate::updateOrCreate(
                ['currency' => $currency],
                ['rate' => $rate]
            );
        }
    }

    /**
     * @param string $base
     * @param string $target
     * @return float|int
     * @throws \Exception
     */
    public function exchangeRate(string $base, string $target)
    {
        $exchangeRates = EuroExchangeRate::all()->pluck('rate', 'currency')->toArray();
        if (!array_key_exists($base, $exchangeRates) || !array_key_exists($target, $exchangeRates)) {
            throw new \Exception('Currency exchange rate information does not exists');
        }
        $baseToTargetExchangeRate = $exchangeRates[$target] / $exchangeRates[$base];

        return $baseToTargetExchangeRate;
    }
}