<?php

namespace App\Infrastructure\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FhirApiClient
{
    private const TOKEN_CACHE_KEY = 'fhir_api_auth_token';
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $consumerKey;
    private ?string $token = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        string $baseUrl,
        string $username,
        string $password,
        string $consumerKey
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->consumerKey = $consumerKey;
    }

    public function getToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $this->logger->info('Fetching new FHIR API auth token');

        $response = $this->httpClient->request('POST', $this->baseUrl . '/api/Login/Login', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $this->username,
                'password' => $this->password,
                'consumerKey' => $this->consumerKey,
            ],
        ]);

        $data = $response->toArray();
        $this->token = $data['token'] ?? '';

        if (empty($this->token)) {
            throw new \RuntimeException('Failed to obtain authentication token from FHIR API');
        }

        $this->logger->info('Successfully obtained FHIR API auth token');
        return $this->token;

//        return $this->cache->get(self::TOKEN_CACHE_KEY, function (ItemInterface $item) {
//            $this->logger->info('Fetching new FHIR API auth token');
//
//            $item->expiresAfter(23.5 * 3600);
//
//            $response = $this->httpClient->request('POST', $this->baseUrl . '/api/Login/Login', [
//                'headers' => [
//                    'Content-Type' => 'application/json',
//                ],
//                'json' => [
//                    'username' => $this->username,
//                    'password' => $this->password,
//                    'consumerKey' => $this->consumerKey,
//                ],
//            ]);
//
//            $data = $response->toArray();
//            $this->token = $data['token'] ?? '';
//
//            if (empty($this->token)) {
//                throw new \RuntimeException('Failed to obtain authentication token from FHIR API');
//            }
//
//            $this->logger->info('Successfully obtained FHIR API auth token');
//            return $this->token;
//        });
    }

    public function get(string $endpoint, array $queryParams = []): array
    {
        $token = $this->getToken();

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/fhir+json',
                ],
                'query' => $queryParams,
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('FHIR API request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'query' => $queryParams,
            ]);

            if (strpos($e->getMessage(), '401') !== false) {
                $this->cache->delete(self::TOKEN_CACHE_KEY);
                $this->token = null;

                $token = $this->getToken();
                $response = $this->httpClient->request('GET', $this->baseUrl . $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/fhir+json',
                    ],
                    'query' => $queryParams,
                ]);

                return $response->toArray();
            }

            throw $e;
        }
    }

    public function post(string $endpoint, array $data): array
    {
        $token = $this->getToken();

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/fhir+json',
                    'Accept' => 'application/fhir+json',
                ],
                'json' => $data,
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('FHIR API POST request failed: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
            ]);

            if (strpos($e->getMessage(), '401') !== false) {
                $this->cache->delete(self::TOKEN_CACHE_KEY);
                $this->token = null;

                // Retry the request once with a new token
                $token = $this->getToken();
                $response = $this->httpClient->request('POST', $this->baseUrl . $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/fhir+json',
                        'Accept' => 'application/fhir+json',
                    ],
                    'json' => $data,
                ]);

                return $response->toArray();
            }

            throw $e;
        }
    }
}