<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Model;

/**
 * Email Quality Score Model
 *
 * Maps IPQS Email Validation API responses to typed PHP object.
 *
 * @see https://www.ipqualityscore.com/documentation/email-validation-api/response-parameters
 * @see ../../analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/iqs/email/EmailQualityScoreApiAdapter.kt
 */
class EmailQualityScore
{
    public function __construct(
        public readonly string $email,
        public readonly int $fraudScore,                  // 0-100 (cast from API's float)
        public readonly \DateTimeImmutable $timestamp,
        public readonly bool $valid,                      // Email format validity
        public readonly bool $disposable,                 // Temporary email service
        public readonly bool $leaked,                     // Dark web exposure
        public readonly bool $suspect,                    // Suspicious patterns
        public readonly bool $recentAbuse,                // Recent fraud activity
        public readonly int $smtpScore,                   // -1 to 3 (SMTP validation)
        public readonly int $overallScore,                // 0 to 4 (aggregate quality)
        public readonly string $deliverability,           // 'high', 'medium', 'low'
        public readonly bool $catchAll,                   // Catch-all domain
        public readonly bool $generic,                    // Generic/role-based email
        public readonly bool $honeypot,                   // Spam trap detection
        public readonly array $vendorMetadata = [],       // Full API response
    ) {}

    /**
     * Create EmailQualityScore from IPQS API response
     *
     * Matches EmailQualityScoreApiAdapter.fromApiResponse() in Kotlin
     *
     * @param string $email The email address being scored
     * @param array<string, mixed> $response Raw IPQS API JSON response
     * @return self
     */
    public static function fromApiResponse(string $email, array $response): self
    {
        return new self(
            email: $email,
            fraudScore: (int)($response['fraud_score'] ?? 0),          // Cast float to int
            timestamp: new \DateTimeImmutable(),
            valid: $response['valid'] ?? false,
            disposable: $response['disposable'] ?? false,
            leaked: $response['leaked'] ?? false,
            suspect: $response['suspect'] ?? false,
            recentAbuse: $response['recent_abuse'] ?? false,
            smtpScore: $response['smtp_score'] ?? 0,
            overallScore: $response['overall_score'] ?? 0,
            deliverability: $response['deliverability'] ?? 'unknown',
            catchAll: $response['catch_all'] ?? false,
            generic: $response['generic'] ?? false,
            honeypot: $response['honeypot'] ?? false,
            vendorMetadata: $response,                                  // Preserve full response
        );
    }
}
