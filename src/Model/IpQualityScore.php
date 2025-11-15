<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Model;

use Kodegen\Ipqs\Enum\ConnectionType;
use Kodegen\Ipqs\Enum\AbuseVelocity;

/**
 * IP Quality Score Model
 *
 * Maps IPQS Proxy Detection API responses to typed PHP object.
 *
 * @see https://www.ipqualityscore.com/documentation/proxy-detection-api/response-parameters
 * @see ../../analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/iqs/ip/IPQualityScoreResponse.kt
 */
class IpQualityScore
{
    public function __construct(
        public readonly string $ipAddress,
        public readonly int $fraudScore,                      // 0-100
        public readonly \DateTimeImmutable $timestamp,
        public readonly ?string $countryCode,                 // ISO-2 (limited to 2 chars)
        public readonly ?string $region,                      // State/province
        public readonly ?string $city,                        // City name
        public readonly float $latitude,                      // GPS coordinate
        public readonly float $longitude,                     // GPS coordinate
        public readonly ?string $isp,                         // Internet Service Provider
        public readonly ?int $asn,                            // Autonomous System Number
        public readonly bool $isCrawler,                      // Search engine bot
        public readonly bool $proxy,                          // Proxy connection
        public readonly bool $vpn,                            // VPN connection
        public readonly bool $tor,                            // TOR connection
        public readonly bool $recentAbuse,                    // Recent fraud activity
        public readonly bool $botStatus,                      // Automated behavior
        public readonly ?ConnectionType $connectionType,      // Residential/Corporate/etc.
        public readonly ?AbuseVelocity $abuseVelocity,       // none/low/medium/high
        public readonly ?string $timezone,                    // IANA timezone
        public readonly array $vendorMetadata = [],           // Full API response
    ) {}

    /**
     * Create IpQualityScore from IPQS API response
     *
     * @param string $ipAddress The IP address being scored
     * @param array<string, mixed> $response Raw IPQS API JSON response
     * @return self
     */
    public static function fromApiResponse(string $ipAddress, array $response): self
    {
        return new self(
            ipAddress: $ipAddress,
            fraudScore: $response['fraud_score'] ?? 0,
            timestamp: new \DateTimeImmutable(),
            countryCode: isset($response['country_code'])
                ? substr($response['country_code'], 0, 2)               // Limit to 2 chars!
                : null,
            region: $response['region'] ?? null,
            city: $response['city'] ?? null,
            latitude: (float)($response['latitude'] ?? 0.0),
            longitude: (float)($response['longitude'] ?? 0.0),
            isp: $response['ISP'] ?? $response['isp'] ?? null,          // Uppercase first!
            asn: $response['ASN'] ?? $response['asn'] ?? null,          // Uppercase first!
            isCrawler: $response['is_crawler'] ?? false,
            proxy: $response['proxy'] ?? false,
            vpn: $response['vpn'] ?? false,
            tor: $response['tor'] ?? false,
            recentAbuse: $response['recent_abuse'] ?? false,
            botStatus: $response['bot_status'] ?? false,
            connectionType: ConnectionType::fromString($response['connection_type'] ?? null),
            abuseVelocity: AbuseVelocity::fromString($response['abuse_velocity'] ?? null),
            timezone: $response['timezone'] ?? null,
            vendorMetadata: $response,
        );
    }
}
