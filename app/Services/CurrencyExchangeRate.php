<?php

namespace App\Services;

use App\Repositories\EuroExchangeRateRepository;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;

class CurrencyExchangeRate
{
    /**
     * @var array $headers
     */
    protected $headers;

    /**
     * @var Client $client
     */
    protected $client;

    /**
     * AuthService constructor.
     */
    public function __construct()
    {
        $baseUrl = env('CURRENCY_EXCHANGE_URL', 'http://data.fixer.io/api/latest');
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    /**
     * @param EuroExchangeRateRepository $repository
     */
    public function getAllExchanges(EuroExchangeRateRepository $repository)
    {
        try {
            $response = $this->client->request('GET');
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                $repository->updateRates($contents['rates']);
            }
        } catch (GuzzleException $exception) {

        }
    }
}