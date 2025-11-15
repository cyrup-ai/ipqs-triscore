<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Client;

use Kodegen\Ipqs\Config\IpqsConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class IpClient
{
    private Client $httpClient;

    public function __construct(
        private IpqsConfig $config,
        private LoggerInterface $logger,
    ) {
        $this->httpClient = new Client([
            'timeout' => $this->config->getTimeout(),
        ]);
    }

    /**
     * Score an IP address via IPQS API
     *
     * @param string $ipAddress IP address to score
     * @param array<string, mixed> $params Additional query parameters (userAgent, strictness, etc.)
     * @return array<string, mixed>|null Raw API response or null on error
     */
    public function scoreRaw(string $ipAddress, array $params = []): ?array
    {
        try {
            // Build query string with defaults
            $queryParams = array_merge([
                'strictness' => $this->config->getDefaultStrictness(),
            ], $params);

            // Remove null values (matching Kotlin behavior)
            $queryParams = array_filter($queryParams, fn($value) => $value !== null);

            $queryString = http_build_query($queryParams);

            $url = sprintf(
                '%s/ip/%s/%s?%s',
                $this->config->getBaseUrl(),
                $this->config->getApiKey(),
                $ipAddress,
                $queryString
            );

            $response = $this->httpClient->post($url, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            $body = $response->getBody()->getContents();

            // Match Kotlin: log successful response at info level
            $this->logger->info('IpClient::scoreRaw response', ['json' => $body]);

            return json_decode($body, true);
        } catch (GuzzleException $e) {
            $this->logger->error('IpClient::scoreRaw failed', [
                'ipAddress' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
