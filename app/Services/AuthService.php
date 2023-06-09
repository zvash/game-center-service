<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;

class AuthService
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
        $baseUrl = rtrim(env('AUTH_SERVICE_URL', 'internal-auth'), '/') . '/';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    public function authenticate(Request $request)
    {
        $headers = $this->headers + $this->getHeadersFromRequest($request);

        try {
            $response = $this->client->request(
                'GET',
                $this->getAuthenticateUrlSuffix(),
                [
                    'headers' => $headers
                ]
            );

            if ($response->getStatusCode() == 200) {
                $content = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $content['data'], 'status' => 200];
            } else {
                return ['data' => $response->getBody()->getContents(), 'status' => $response->getStatusCode()];
            }

        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getUserById(int $userId)
    {
        $headers = $this->headers + ['Service-Token' => env('AUTH_SERVICE_TOKEN', '')];
        try {
            $response = $this->client->request(
                'GET',
                $this->getUserByIdUrl($userId),
                [
                    'headers' => $headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param array $userIds
     * @return array
     */
    public function getUsersById(array $userIds)
    {
        $payload = [
            'ids' => $userIds
        ];
        $headers = $this->headers + ['Service-Token' => env('AUTH_SERVICE_TOKEN', '')];
        try {
            $response = $this->client->request(
                'POST',
                $this->getUsersByIdUrl(),
                [
                    'json' => $payload,
                    'headers' => $headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    private function getHeadersFromRequest(Request $request)
    {
        $headers = $request->headers->all();
        $formattedHeaders = [];
        foreach ($headers as $key => $record) {
            if (in_array(strtolower($key), ['content-length'])) {
                continue;
            }
            if (is_array($record)) {
                $value = $record[0];
            } else {
                $value = $record;
            }
            $key = implode('-', array_map('ucfirst', explode('-', $key)));
            $formattedHeaders[$key] = $value;
        }
        return $formattedHeaders;
    }

    /**
     * @return string
     */
    private function getAuthenticateUrlSuffix()
    {
        return 'api/v1/authenticate';
    }

    /**
     * @param int $userId
     * @return string
     */
    private function getUserByIdUrl(int $userId)
    {
        return "api/v1/users/$userId";
    }

    /**
     * @return string
     */
    private function getUsersByIdUrl()
    {
        return 'api/v1/users/profiles/get';
    }
}