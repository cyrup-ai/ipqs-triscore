<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Client;

use Kodegen\Ipqs\Config\IpqsConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class PhoneClient
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
     * Score a phone number via IPQS API
     *
     * @param string $phoneNumber Phone number in any format (preferably E.164)
     * @param string|null $country ISO country code (defaults to config default, typically "US")
     * @return array<string, mixed>|null Raw API response or null on error
     */
    public function scoreRaw(string $phoneNumber, ?string $country = null): ?array
    {
        try {
            $country = $country ?? $this->config->getDefaultCountry();
            $url = sprintf(
                '%s/phone/%s/%s?country=%s',
                $this->config->getBaseUrl(),
                $this->config->getApiKey(),
                $phoneNumber,
                $country
            );

            $response = $this->httpClient->post($url, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $this->logger->error('PhoneClient::scoreRaw failed', [
                'phoneNumber' => $phoneNumber,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
