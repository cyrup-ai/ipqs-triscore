<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Model;

use Kodegen\Ipqs\Enum\RiskCategory;

/**
 * Fraud Evaluation Result
 *
 * Output model for tri-risk evaluation service combining Email, IP, and Phone scores.
 *
 * @see ../../spec/SPEC_IPQUALITY_TRIRISK_RULES.md (lines 34-46)
 * @see ../../analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/fraud/FraudResult.kt
 */
class FraudEvaluationResult
{
    public function __construct(
        public readonly RiskCategory $riskCategory,        // LOW, MEDIUM, or HIGH
        public readonly int $avgScore,                     // 0-100 (average of available scores)
        public readonly ?int $emailFraudScore,             // Email score if available
        public readonly ?int $ipFraudScore,                // IP score if available
        public readonly ?int $phoneFraudScore,             // Phone score if available
        public readonly array $metadata = [],              // IP geo, flags, debug info
    ) {}
}
