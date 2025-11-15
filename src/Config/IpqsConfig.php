<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Config;

class IpqsConfig
{
    /**
     * Create configuration from constructor parameters
     *
     * @param string $apiKey IPQualityScore API key (never hard-code in source!)
     * @param string $baseUrl Base URL for IPQS API endpoints
     * @param int $timeout Request timeout in seconds
     * @param string $defaultCountry Default country code for phone validation
     * @param int $defaultStrictness IP API strictness level (0-3)
     */
    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://ipqualityscore.com/api/json',
        private int $timeout = 10,
        private string $defaultCountry = 'US',
        private int $defaultStrictness = 2,
    ) {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('IPQS API key cannot be empty');
        }

        if ($defaultStrictness < 0 || $defaultStrictness > 3) {
            throw new \InvalidArgumentException('Strictness must be between 0 and 3');
        }
    }

    /**
     * Create configuration from environment variables
     *
     * Reads from $_ENV or getenv() - assumes dotenv or similar has been loaded
     *
     * Required: IPQS_API_KEY
     * Optional: IPQS_BASE_URL, IPQS_TIMEOUT, IPQS_DEFAULT_COUNTRY, IPQS_DEFAULT_STRICTNESS
     *
     * @throws \RuntimeException if IPQS_API_KEY is not set
     */
    public static function fromEnv(): self
    {
        $apiKey = $_ENV['IPQS_API_KEY'] ?? getenv('IPQS_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'IPQS_API_KEY environment variable is required. ' .
                'See .env.example for configuration template.'
            );
        }

        return new self(
            apiKey: $apiKey,
            baseUrl: $_ENV['IPQS_BASE_URL'] ?? getenv('IPQS_BASE_URL') ?: 'https://ipqualityscore.com/api/json',
            timeout: (int)($_ENV['IPQS_TIMEOUT'] ?? getenv('IPQS_TIMEOUT') ?: 10),
            defaultCountry: $_ENV['IPQS_DEFAULT_COUNTRY'] ?? getenv('IPQS_DEFAULT_COUNTRY') ?: 'US',
            defaultStrictness: (int)($_ENV['IPQS_DEFAULT_STRICTNESS'] ?? getenv('IPQS_DEFAULT_STRICTNESS') ?: 2),
        );
    }

    // Getters for immutable access
    public function getApiKey(): string { return $this->apiKey; }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function getTimeout(): int { return $this->timeout; }
    public function getDefaultCountry(): string { return $this->defaultCountry; }
    public function getDefaultStrictness(): int { return $this->defaultStrictness; }
}
