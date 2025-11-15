<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\TriRisk;

use Kodegen\Ipqs\Enum\RiskCategory;
use Kodegen\Ipqs\Model\EmailQualityScore;
use Kodegen\Ipqs\Model\IpQualityScore;
use Kodegen\Ipqs\Model\PhoneQualityScore;
use Kodegen\Ipqs\Model\FraudEvaluationResult;
use Kodegen\Ipqs\Service\EmailQualityScoreService;
use Kodegen\Ipqs\Service\IpQualityScoreService;
use Kodegen\Ipqs\Service\PhoneQualityScoreService;

/**
 * Tri-Risk Evaluation Service
 *
 * Implements the canonical tri-risk algorithm from SPEC_IPQUALITY_TRIRISK_RULES.md
 * Matches FraudService.generateScore() in Kotlin (analytics/src/.../fraud/FraudService.kt)
 *
 * Algorithm Summary:
 * 1. Collect scores from Email, IP, Phone (use pre-fetched OR call services)
 * 2. Calculate average score: floor(sumOfScores / scoredServices)
 * 3. Map to base risk: ≤50=LOW, 51-75=MEDIUM, ≥76=HIGH
 * 4. Apply IP overrides: LOW→MEDIUM at 75, MEDIUM→HIGH at 88
 *
 * @see https://github.com/kodegen/analytics/blob/main/analytics/src/main/kotlin/com/kodegen/analytics/fraud/FraudService.kt
 */
class TriRiskEvaluator
{
    public function __construct(
        private EmailQualityScoreService $emailService,
        private IpQualityScoreService $ipService,
        private PhoneQualityScoreService $phoneService,
    ) {}

    /**
     * Evaluate tri-risk from available data
     *
     * Matches Kotlin's FraudService.generateScore() logic (lines 29-116)
     *
     * @param string|null $email Email address to score (if not providing pre-fetched score)
     * @param string|null $ipAddress IP address to score (if not providing pre-fetched score)
     * @param string|null $userAgent User agent for IP scoring (REQUIRED if ipAddress provided)
     * @param string|null $phoneNumber Phone number to score (if not providing pre-fetched score)
     * @param EmailQualityScore|null $emailScore Pre-fetched email score (optional)
     * @param IpQualityScore|null $ipScore Pre-fetched IP score (optional)
     * @param PhoneQualityScore|null $phoneScore Pre-fetched phone score (optional)
     * @return FraudEvaluationResult Tri-risk result with category, avgScore, and metadata
     */
    public function evaluate(
        ?string $email = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $phoneNumber = null,
        ?EmailQualityScore $emailScore = null,
        ?IpQualityScore $ipScore = null,
        ?PhoneQualityScore $phoneScore = null,
    ): FraudEvaluationResult {
        $sumOfScores = 0;
        $scoredServices = 0;

        // ============================================================================
        // STEP 1: SCORE COLLECTION (matching Kotlin lines 40-63)
        // ============================================================================

        // Collect Email Score
        // Use pre-fetched score if available, otherwise call service if email provided
        $emailQualityScore = $emailScore ?? ($email !== null ? $this->emailService->score($email) : null);
        if ($emailQualityScore !== null) {
            $sumOfScores += $emailQualityScore->fraudScore;
            $scoredServices++;
        }

        // Collect IP Score
        // CRITICAL: Requires BOTH ipAddress AND userAgent
        // Pre-fetched score takes precedence over raw inputs
        $ipQualityScore = $ipScore ?? (($ipAddress !== null && $userAgent !== null)
            ? $this->ipService->score($ipAddress, $userAgent)
            : null);
        if ($ipQualityScore !== null) {
            $sumOfScores += $ipQualityScore->fraudScore;
            $scoredServices++;
        }

        // Collect Phone Score
        $phoneQualityScore = $phoneScore ?? ($phoneNumber !== null ? $this->phoneService->score($phoneNumber) : null);
        if ($phoneQualityScore !== null) {
            $sumOfScores += $phoneQualityScore->fraudScore;
            $scoredServices++;
        }

        // ============================================================================
        // STEP 2: BASE TRI-RISK CALCULATION (matching Kotlin lines 74-84)
        // ============================================================================

        if ($scoredServices === 0) {
            // Special case: No scores available → default to LOW risk
            $avgScore = 0;
            $riskCategory = RiskCategory::LOW;
        } else {
            // Calculate average using floor division (matching Kotlin's .toInt() behavior)
            $avgScore = (int)floor($sumOfScores / $scoredServices);

            // Map average to base risk category
            $riskCategory = match (true) {
                $avgScore <= 50 => RiskCategory::LOW,
                $avgScore >= 51 && $avgScore <= 75 => RiskCategory::MEDIUM,
                default => RiskCategory::HIGH,  // avgScore >= 76
            };
        }

        // ============================================================================
        // STEP 3: IP-BASED OVERRIDES (matching Kotlin lines 86-99)
        // ============================================================================

        // Override 1: Elevate LOW to MEDIUM when IP fraud score ≥ 75
        // Rationale: High IP risk overrides clean Email/Phone signals
        if ($riskCategory === RiskCategory::LOW
            && $ipQualityScore !== null
            && $ipQualityScore->fraudScore >= 75
        ) {
            $riskCategory = RiskCategory::MEDIUM;
        }

        // Override 2: Elevate MEDIUM to HIGH when IP fraud score ≥ 88
        // Rationale: Very high IP risk indicates sophisticated attack
        if ($riskCategory === RiskCategory::MEDIUM
            && $ipQualityScore !== null
            && $ipQualityScore->fraudScore >= 88
        ) {
            $riskCategory = RiskCategory::HIGH;
        }

        // ============================================================================
        // STEP 4: BUILD RESULT METADATA
        // ============================================================================

        // Populate metadata with IP geo and flags for downstream analysis
        $metadata = [];
        if ($ipQualityScore !== null) {
            $metadata['ip'] = [
                'countryCode' => $ipQualityScore->countryCode,
                'proxy' => $ipQualityScore->proxy,
                'vpn' => $ipQualityScore->vpn,
                'tor' => $ipQualityScore->tor,
                'connectionType' => $ipQualityScore->connectionType?->value,
                'abuseVelocity' => $ipQualityScore->abuseVelocity?->value,
            ];
        }

        // Return final tri-risk evaluation result
        return new FraudEvaluationResult(
            riskCategory: $riskCategory,
            avgScore: $avgScore,
            emailFraudScore: $emailQualityScore?->fraudScore,
            ipFraudScore: $ipQualityScore?->fraudScore,
            phoneFraudScore: $phoneQualityScore?->fraudScore,
            metadata: $metadata,
        );
    }
}
