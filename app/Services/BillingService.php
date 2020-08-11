<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;

class BillingService
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
        $baseUrl = rtrim(env('BILLING_SERVICE_URL', 'internal-billing'), '/') . '/';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Service-Token' => env('BILLING_SERVICE_TOKEN', '')
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    public function createTransactions(array $transactions)
    {
        
    }

    /**
     * @param int $userId
     * @param int $amount
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function withdrawCoin(int $userId, int $amount, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'withdraw-coin',
            $amount,
            'COIN',
            $sourceType,
            $sourceId,
            "$sourceType-withdraw-coin"
        );
    }

    /**
     * @param int $userId
     * @param int $amount
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function depositCoin(int $userId, int $amount, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'deposit-coin',
            $amount,
            'COIN',
            $sourceType,
            $sourceId,
            "$sourceType-deposit-coin"
        );
    }

    /**
     * @param int $userId
     * @param int $amount
     * @param string $currency
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function withdrawMoney(int $userId, int $amount, string $currency, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'withdraw-money',
            $amount,
            $currency,
            $sourceType,
            $sourceId,
            "$sourceType-withdraw-money"
        );
    }

    /**
     * @param int $userId
     * @param int $amount
     * @param string $currency
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function depositMoney(int $userId, int $amount, string $currency, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'deposit-money',
            $amount,
            $currency,
            $sourceType,
            $sourceId,
            "$sourceType-deposit-money"
        );
    }

    private function transactionMaker(int $userId, string $action, int $amount, string $currency, string $sourceType, int $sourceId, string $description = '', array $extraParams = [])
    {
        $transaction = [
            'action' => $action,
            'amount' => $amount,
            'currency' => $currency,
            'user_id' => $userId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'extra_params' => json_encode($extraParams)
        ];
        return $transaction;
    }

    private function getCreateTransactionsUrlSuffix()
    {
        return 'api/v1/transactions/create';
    }
}