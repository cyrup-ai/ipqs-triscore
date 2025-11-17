<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\TriRisk;

use Kodegen\Ipqs\TriRisk\TriRiskEvaluator;
use Kodegen\Ipqs\Client\EmailClient;
use Kodegen\Ipqs\Client\IpClient;
use Kodegen\Ipqs\Client\PhoneClient;
use Kodegen\Ipqs\Util\EmailNormalizer;
use Kodegen\Ipqs\Model\EmailQualityScore;
use Kodegen\Ipqs\Model\IpQualityScore;
use Kodegen\Ipqs\Model\PhoneQualityScore;
use Kodegen\Ipqs\Enum\RiskCategory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TriRiskEvaluatorTest extends TestCase
{
    /**
     * Test 1: Evaluate with pre-fetched scores (all three)
     * avgScore = floor((10 + 20 + 30) / 3) = 20 → LOW
     */
    public function testEvaluateWithPrefetchedScores(): void
    {
        $evaluator = $this->createEvaluator();

        $emailScore = $this->createEmailScore(fraudScore: 10);
        $ipScore = $this->createIpScore(fraudScore: 20);
        $phoneScore = $this->createPhoneScore(fraudScore: 30);

        $result = $evaluator->evaluate(
            emailScore: $emailScore,
            ipScore: $ipScore,
            phoneScore: $phoneScore
        );

        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
        $this->assertSame(20, $result->avgScore);  // floor(60/3) = 20
        $this->assertSame(10, $result->emailFraudScore);
        $this->assertSame(20, $result->ipFraudScore);
        $this->assertSame(30, $result->phoneFraudScore);
    }

    /**
     * Test 2: Base risk mapping - LOW (avgScore <= 50)
     *
     * @dataProvider lowRiskScoreProvider
     */
    public function testBaseRiskMappingLow(int $email, int $ip, int $phone, int $expectedAvg): void
    {
        $evaluator = $this->createEvaluator();

        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore($email),
            ipScore: $this->createIpScore($ip),
            phoneScore: $this->createPhoneScore($phone)
        );

        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
        $this->assertSame($expectedAvg, $result->avgScore);
    }

    public static function lowRiskScoreProvider(): array
    {
        return [
            'all zero' => [0, 0, 0, 0],
            'all low' => [10, 20, 30, 20],  // floor(60/3) = 20
            'exactly 50' => [50, 50, 50, 50],
            'mixed under 50' => [25, 35, 40, 33],  // floor(100/3) = 33
        ];
    }

    /**
     * Test 3: Base risk mapping - MEDIUM (51 <= avgScore <= 75)
     *
     * @dataProvider mediumRiskScoreProvider
     */
    public function testBaseRiskMappingMedium(int $email, int $ip, int $phone, int $expectedAvg): void
    {
        $evaluator = $this->createEvaluator();

        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore($email),
            ipScore: $this->createIpScore($ip),
            phoneScore: $this->createPhoneScore($phone)
        );

        $this->assertSame(RiskCategory::MEDIUM, $result->riskCategory);
        $this->assertSame($expectedAvg, $result->avgScore);
    }

    public static function mediumRiskScoreProvider(): array
    {
        return [
            'exactly 51' => [51, 51, 51, 51],
            'mixed medium' => [60, 65, 70, 65],  // floor(195/3) = 65
            'exactly 75' => [75, 75, 75, 75],
        ];
    }

    /**
     * Test 4: Base risk mapping - HIGH (avgScore >= 76)
     *
     * @dataProvider highRiskScoreProvider
     */
    public function testBaseRiskMappingHigh(int $email, int $ip, int $phone, int $expectedAvg): void
    {
        $evaluator = $this->createEvaluator();

        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore($email),
            ipScore: $this->createIpScore($ip),
            phoneScore: $this->createPhoneScore($phone)
        );

        $this->assertSame(RiskCategory::HIGH, $result->riskCategory);
        $this->assertSame($expectedAvg, $result->avgScore);
    }

    public static function highRiskScoreProvider(): array
    {
        return [
            'exactly 76' => [76, 76, 76, 76],
            'mixed high' => [80, 85, 90, 85],  // floor(255/3) = 85
            'all max' => [100, 100, 100, 100],
        ];
    }

    /**
     * Test 5: IP Override Rule 1 - Elevate LOW to MEDIUM when IP >= 75
     */
    public function testIpOverrideElevatesLowToMedium(): void
    {
        $evaluator = $this->createEvaluator();

        // Base: (10 + 75 + 10) / 3 = 31.67 → floor = 31 → LOW
        // But IP score is 75, so LOW → MEDIUM
        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(10),
            ipScore: $this->createIpScore(75),  // Triggers override
            phoneScore: $this->createPhoneScore(10)
        );

        $this->assertSame(RiskCategory::MEDIUM, $result->riskCategory);
        $this->assertSame(31, $result->avgScore);
    }

    /**
     * Test 6: IP Override Rule 1 - Does NOT trigger when IP < 75
     */
    public function testIpOverrideDoesNotTriggerBelowThreshold(): void
    {
        $evaluator = $this->createEvaluator();

        // Base: (10 + 74 + 10) / 3 = 31.33 → floor = 31 → LOW
        // IP score is 74 (< 75), so remains LOW
        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(10),
            ipScore: $this->createIpScore(74),  // One below threshold
            phoneScore: $this->createPhoneScore(10)
        );

        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
    }

    /**
     * Test 7: IP Override Rule 2 - Elevate MEDIUM to HIGH when IP >= 88
     */
    public function testIpOverrideElevatesMediumToHigh(): void
    {
        $evaluator = $this->createEvaluator();

        // Base: (60 + 88 + 60) / 3 = 69.33 → floor = 69 → MEDIUM
        // But IP score is 88, so MEDIUM → HIGH
        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(60),
            ipScore: $this->createIpScore(88),  // Triggers override
            phoneScore: $this->createPhoneScore(60)
        );

        $this->assertSame(RiskCategory::HIGH, $result->riskCategory);
        $this->assertSame(69, $result->avgScore);
    }

    /**
     * Test 8: IP Override Rule 2 - Does NOT trigger when IP < 88
     */
    public function testIpOverrideDoesNotElevateMediumBelowThreshold(): void
    {
        $evaluator = $this->createEvaluator();

        // Base: (60 + 87 + 60) / 3 = 69 → MEDIUM
        // IP score is 87 (< 88), so remains MEDIUM
        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(60),
            ipScore: $this->createIpScore(87),  // One below threshold
            phoneScore: $this->createPhoneScore(60)
        );

        $this->assertSame(RiskCategory::MEDIUM, $result->riskCategory);
    }

    /**
     * Test 9: No scores available - defaults to LOW with avgScore = 0
     */
    public function testEvaluateWithNoScores(): void
    {
        $evaluator = $this->createEvaluator();

        $result = $evaluator->evaluate();  // No parameters

        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
        $this->assertSame(0, $result->avgScore);
        $this->assertNull($result->emailFraudScore);
        $this->assertNull($result->ipFraudScore);
        $this->assertNull($result->phoneFraudScore);
    }

    /**
     * Test 10: Partial scores (only email and phone, no IP)
     */
    public function testEvaluateWithPartialScores(): void
    {
        $evaluator = $this->createEvaluator();

        // Only email (20) and phone (40), no IP
        // avgScore = floor((20 + 40) / 2) = 30 → LOW
        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(20),
            phoneScore: $this->createPhoneScore(40)
            // No IP score
        );

        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
        $this->assertSame(30, $result->avgScore);
        $this->assertSame(20, $result->emailFraudScore);
        $this->assertNull($result->ipFraudScore);
        $this->assertSame(40, $result->phoneFraudScore);
    }

    /**
     * Test 11: IP override does not apply when IP score is null
     */
    public function testIpOverrideDoesNotApplyWhenIpScoreIsNull(): void
    {
        $evaluator = $this->createEvaluator();

        // avgScore = floor((40 + 50) / 2) = 45 → LOW
        // No IP score, so override does not apply
        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(40),
            phoneScore: $this->createPhoneScore(50)
        );

        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
    }

    /**
     * Test 12: Metadata includes IP geo data when IP score is present
     */
    public function testMetadataIncludesIpGeoData(): void
    {
        $evaluator = $this->createEvaluator();

        $ipScore = $this->createIpScore(
            fraudScore: 25,
            countryCode: 'US',
            proxy: false,
            vpn: false,
            tor: false
        );

        $result = $evaluator->evaluate(ipScore: $ipScore);

        $this->assertArrayHasKey('ip', $result->metadata);
        $this->assertSame('US', $result->metadata['ip']['countryCode']);
        $this->assertFalse($result->metadata['ip']['proxy']);
        $this->assertFalse($result->metadata['ip']['vpn']);
        $this->assertFalse($result->metadata['ip']['tor']);
    }

    /**
     * Test 13: Metadata is empty when no IP score
     */
    public function testMetadataIsEmptyWithoutIpScore(): void
    {
        $evaluator = $this->createEvaluator();

        $result = $evaluator->evaluate(
            emailScore: $this->createEmailScore(20)
        );

        $this->assertEmpty($result->metadata);
    }

    /**
     * Test 14: Floor division behavior (matching Kotlin)
     */
    public function testFloorDivisionBehavior(): void
    {
        $evaluator = $this->createEvaluator();

        // (33 + 33 + 33) / 3 = 33.0 → floor = 33
        $result1 = $evaluator->evaluate(
            emailScore: $this->createEmailScore(33),
            ipScore: $this->createIpScore(33),
            phoneScore: $this->createPhoneScore(33)
        );
        $this->assertSame(33, $result1->avgScore);

        // (34 + 33 + 33) / 3 = 33.33 → floor = 33
        $result2 = $evaluator->evaluate(
            emailScore: $this->createEmailScore(34),
            ipScore: $this->createIpScore(33),
            phoneScore: $this->createPhoneScore(33)
        );
        $this->assertSame(33, $result2->avgScore);

        // (34 + 34 + 33) / 3 = 33.67 → floor = 33
        $result3 = $evaluator->evaluate(
            emailScore: $this->createEmailScore(34),
            ipScore: $this->createIpScore(34),
            phoneScore: $this->createPhoneScore(33)
        );
        $this->assertSame(33, $result3->avgScore);
    }

    // Helper methods
    private function createEvaluator(): TriRiskEvaluator
    {
        // Mock clients (not used in pre-fetched score tests)
        $emailClient = $this->createMock(EmailClient::class);
        $ipClient = $this->createMock(IpClient::class);
        $phoneClient = $this->createMock(PhoneClient::class);
        $emailNormalizer = new EmailNormalizer(new NullLogger());

        return new TriRiskEvaluator(
            $emailClient,
            $ipClient,
            $phoneClient,
            $emailNormalizer,
            new NullLogger()
        );
    }

    private function createEmailScore(int $fraudScore): EmailQualityScore
    {
        return new EmailQualityScore(
            email: 'test@example.com',
            fraudScore: $fraudScore,
            timestamp: new \DateTimeImmutable(),
            valid: true,
            disposable: false,
            leaked: false,
            suspect: false,
            recentAbuse: false,
            smtpScore: 3,
            overallScore: 4,
            deliverability: 'high',
            catchAll: false,
            generic: false,
            honeypot: false
        );
    }

    private function createIpScore(
        int $fraudScore,
        string $countryCode = 'US',
        bool $proxy = false,
        bool $vpn = false,
        bool $tor = false
    ): IpQualityScore {
        return new IpQualityScore(
            ipAddress: '1.2.3.4',
            fraudScore: $fraudScore,
            timestamp: new \DateTimeImmutable(),
            countryCode: $countryCode,
            region: 'CA',
            city: 'San Francisco',
            latitude: 37.7749,
            longitude: -122.4194,
            isp: 'Test ISP',
            asn: 15169,
            isCrawler: false,
            proxy: $proxy,
            vpn: $vpn,
            tor: $tor,
            recentAbuse: false,
            botStatus: false,
            connectionType: null,
            abuseVelocity: null,
            timezone: 'America/Los_Angeles'
        );
    }

    private function createPhoneScore(int $fraudScore): PhoneQualityScore
    {
        return new PhoneQualityScore(
            phoneNumber: '+15551234567',
            fraudScore: $fraudScore,
            timestamp: new \DateTimeImmutable(),
            valid: true,
            active: true,
            recentAbuse: false,
            voip: false,
            prepaid: false,
            risky: false,
            carrier: 'Verizon',
            lineType: 'Wireless',
            country: 'US',
            region: 'CA',
            city: 'San Francisco',
            timezone: 'America/Los_Angeles',
            doNotCall: false
        );
    }
}
