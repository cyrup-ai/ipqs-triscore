<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Model;

/**
 * Phone Quality Score Model
 *
 * Maps IPQS Phone Validation API responses to typed PHP object.
 *
 * @see https://www.ipqualityscore.com/documentation/phone-number-validation-api/response-parameters
 * @see ../../analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/iqs/phone/PhoneQualityScoreResponse.kt
 */
class PhoneQualityScore
{
    public function __construct(
        public readonly string $phoneNumber,
        public readonly int $fraudScore,                   // 0-100
        public readonly \DateTimeImmutable $timestamp,
        public readonly bool $valid,                       // Number format validity
        public readonly ?bool $active,                     // HLR lookup result (nullable)
        public readonly ?bool $recentAbuse,                // Recent fraud activity (nullable)
        public readonly ?bool $voip,                       // Voice over IP detection (nullable)
        public readonly ?bool $prepaid,                    // Prepaid line detection (nullable)
        public readonly ?bool $risky,                      // General risk indicator (nullable)
        public readonly string $carrier,                   // Service provider name
        public readonly string $lineType,                  // Wireless/Landline/VOIP/etc.
        public readonly string $country,                   // ISO country code
        public readonly ?string $region,                   // State/province (nullable)
        public readonly ?string $city,                     // City name (nullable)
        public readonly ?string $timezone,                 // IANA timezone (nullable)
        public readonly ?bool $doNotCall,                  // DNC registry status (nullable)
        public readonly array $vendorMetadata = [],        // Full API response
    ) {}

    /**
     * Create PhoneQualityScore from IPQS API response
     *
     * @param string $phoneNumber The phone number being scored (E.164 format)
     * @param array<string, mixed> $response Raw IPQS API JSON response
     * @return self
     */
    public static function fromApiResponse(string $phoneNumber, array $response): self
    {
        return new self(
            phoneNumber: $phoneNumber,
            fraudScore: $response['fraud_score'] ?? 0,
            timestamp: new \DateTimeImmutable(),
            valid: $response['valid'] ?? false,
            active: $response['active'] ?? null,                        // Nullable
            recentAbuse: $response['recent_abuse'] ?? null,             // Nullable
            voip: $response['VOIP'] ?? $response['voip'] ?? null,       // Handle uppercase first!
            prepaid: $response['prepaid'] ?? null,                      // Nullable
            risky: $response['risky'] ?? null,                          // Nullable
            carrier: $response['carrier'] ?? '',
            lineType: $response['line_type'] ?? '',
            country: $response['country'] ?? '',
            region: $response['region'] ?? null,                        // Nullable
            city: $response['city'] ?? null,                            // Nullable
            timezone: $response['timezone'] ?? null,                    // Nullable
            doNotCall: $response['do_not_call'] ?? null,                // Nullable
            vendorMetadata: $response,
        );
    }
}
