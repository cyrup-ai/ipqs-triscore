<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Client;

use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Exception\InvalidPhoneNumberException;
use Kodegen\Ipqs\Util\UrlSanitizer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;

class PhoneClient
{
    private ClientInterface $httpClient;

    public function __construct(
        private IpqsConfig $config,
        private LoggerInterface $logger,
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'timeout' => $this->config->getTimeout(),
        ]);
    }

    /**
     * Score a phone number via IPQS API
     *
     * @param string $phoneNumber Phone number in any format (preferably E.164)
     * @param string|null $country ISO country code (defaults to config default, typically "US")
     * @return array<string, mixed>|null Raw API response or null on error
     *
     * @throws InvalidPhoneNumberException if phone number or country code format is invalid
     */
    public function scoreRaw(string $phoneNumber, ?string $country = null): ?array
    {
        // ========== VALIDATE PHONE NUMBER (NEW) ==========
        $phoneNumber = trim($phoneNumber);

        if (empty($phoneNumber)) {
            throw new InvalidPhoneNumberException('Phone number cannot be empty');
        }

        // Basic validation - phone number should contain mostly digits and allowed chars
        // Pattern allows: digits, spaces, +, (, ), -, .
        if (!preg_match('/^[\d\s\+\(\)\-\.]+$/', $phoneNumber)) {
            throw new InvalidPhoneNumberException(
                sprintf('Invalid phone number format: %s', $phoneNumber)
            );
        }

        // ========== VALIDATE COUNTRY CODE (NEW) ==========
        $country = $country ?? $this->config->getDefaultCountry();
        $country = trim($country);

        // Country code must be 2-letter ISO code (e.g., "US", "UK", "FR")
        if (!preg_match('/^[A-Z]{2}$/', strtoupper($country))) {
            throw new InvalidPhoneNumberException(
                sprintf('Country code must be 2-letter ISO code, got: %s', $country)
            );
        }

        // Ensure uppercase for consistency
        $country = strtoupper($country);
        // ========== END VALIDATION BLOCK ==========

        // Validation passed - proceed with API call (existing code)
        try {
            $url = sprintf(
                '%s/phone/%s/%s?country=%s',
                $this->config->getBaseUrl(),
                $this->config->getApiKey(),
                $phoneNumber,
                $country
            );

            $response = $this->httpClient->get($url, [
                'headers' => ['Accept' => 'application/json'],
            ]);

            $body = $response->getBody()->getContents();

            // Decode with error detection
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            // Validate decoded type is array (IPQS API always returns objects)
            if (!is_array($decoded)) {
                $this->logger->error('PhoneClient::scoreRaw response is not an array', [
                    'url' => UrlSanitizer::sanitize($url),
                    'phoneNumber' => $phoneNumber,
                    'country' => $country,
                    'type' => get_debug_type($decoded),
                    'body' => substr($body, 0, 200),
                ]);
                return null;
            }

            return $decoded;
        } catch (\JsonException $e) {
            $this->logger->error('PhoneClient::scoreRaw JSON decode failed', [
                'url' => UrlSanitizer::sanitize($url),
                'phoneNumber' => $phoneNumber,
                'country' => $country,
                'error' => $e->getMessage(),
                'body' => substr($body ?? '', 0, 500),
            ]);
            return null;
        } catch (GuzzleException $e) {
            $this->logger->error('PhoneClient::scoreRaw failed', [
                'url' => UrlSanitizer::sanitize($url),
                'phoneNumber' => $phoneNumber,
                'country' => $country,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
