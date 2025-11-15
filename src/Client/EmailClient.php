<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Client;

use Kodegen\Ipqs\Config\IpqsConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class EmailClient
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
     * Score an email address via IPQS API
     *
     * @return array<string, mixed>|null Raw API response or null on error
     */
    public function scoreRaw(string $email): ?array
    {
        try {
            $normalizedEmail = $this->normalizeEmail($email);
            $url = sprintf(
                '%s/email/%s/%s',
                $this->config->getBaseUrl(),
                $this->config->getApiKey(),
                $normalizedEmail
            );

            $response = $this->httpClient->post($url, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            $this->logger->error('EmailClient::scoreRaw failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalize email matching Kotlin's standardizeEmail() logic
     *
     * Steps:
     * 1. Lowercase and trim
     * 2. Remove '+' aliases (user+tag@domain → user@domain)
     * 3. For Gmail: remove dots from username part
     */
    private function normalizeEmail(string $email): string
    {
        // Step 1: Lowercase and trim
        $email = strtolower(trim($email));

        // Step 2: Remove '+' aliases
        $email = preg_replace('/^(.+)(\+.+)@(.+)$/', '$1@$3', $email) ?? $email;

        // Step 3: Gmail-specific normalization (remove dots from username)
        if (str_contains($email, '@gmail.com')) {
            if (preg_match('/^(.+)@(.+)$/', $email, $matches)) {
                $username = str_replace('.', '', $matches[1]);
                $email = $username . '@' . $matches[2];
            }
        }

        return $email;
    }
}
