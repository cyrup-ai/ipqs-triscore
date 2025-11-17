<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Model;

use Kodegen\Ipqs\Model\FraudEvaluationResult;
use Kodegen\Ipqs\Enum\RiskCategory;
use PHPUnit\Framework\TestCase;

class FraudEvaluationResultTest extends TestCase
{
    /**
     * Test 1: Constructor with all scores
     */
    public function testConstructorWithAllScores(): void
    {
        $metadata = ['ip' => ['countryCode' => 'US']];
        
        $result = new FraudEvaluationResult(
            riskCategory: RiskCategory::MEDIUM,
            avgScore: 65,
            emailFraudScore: 60,
            ipFraudScore: 70,
            phoneFraudScore: 65,
            metadata: $metadata
        );
        
        $this->assertSame(RiskCategory::MEDIUM, $result->riskCategory);
        $this->assertSame(65, $result->avgScore);
        $this->assertSame(60, $result->emailFraudScore);
        $this->assertSame(70, $result->ipFraudScore);
        $this->assertSame(65, $result->phoneFraudScore);
        $this->assertSame($metadata, $result->metadata);
    }
    
    /**
     * Test 2: Constructor with partial scores
     */
    public function testConstructorWithPartialScores(): void
    {
        $result = new FraudEvaluationResult(
            riskCategory: RiskCategory::LOW,
            avgScore: 30,
            emailFraudScore: 20,
            ipFraudScore: null,  // IP not available
            phoneFraudScore: 40
        );
        
        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
        $this->assertSame(30, $result->avgScore);
        $this->assertSame(20, $result->emailFraudScore);
        $this->assertNull($result->ipFraudScore);
        $this->assertSame(40, $result->phoneFraudScore);
    }
    
    /**
     * Test 3: Constructor with no scores
     */
    public function testConstructorWithNoScores(): void
    {
        $result = new FraudEvaluationResult(
            riskCategory: RiskCategory::LOW,
            avgScore: 0,
            emailFraudScore: null,
            ipFraudScore: null,
            phoneFraudScore: null
        );
        
        $this->assertSame(RiskCategory::LOW, $result->riskCategory);
        $this->assertSame(0, $result->avgScore);
        $this->assertNull($result->emailFraudScore);
        $this->assertNull($result->ipFraudScore);
        $this->assertNull($result->phoneFraudScore);
        $this->assertEmpty($result->metadata);
    }
    
    /**
     * Test 4: Metadata is stored correctly
     */
    public function testMetadataIsStored(): void
    {
        $metadata = [
            'ip' => [
                'countryCode' => 'GB',
                'proxy' => true,
                'vpn' => false,
                'tor' => false,
            ],
            'debug' => ['source' => 'test'],
        ];
        
        $result = new FraudEvaluationResult(
            riskCategory: RiskCategory::HIGH,
            avgScore: 85,
            emailFraudScore: 80,
            ipFraudScore: 90,
            phoneFraudScore: null,
            metadata: $metadata
        );
        
        $this->assertSame($metadata, $result->metadata);
        $this->assertArrayHasKey('ip', $result->metadata);
        $this->assertSame('GB', $result->metadata['ip']['countryCode']);
    }
    
    /**
     * Test 5: Test all risk categories
     */
    public function testAllRiskCategories(): void
    {
        $low = new FraudEvaluationResult(
            riskCategory: RiskCategory::LOW,
            avgScore: 25,
            emailFraudScore: null,
            ipFraudScore: null,
            phoneFraudScore: null
        );
        
        $medium = new FraudEvaluationResult(
            riskCategory: RiskCategory::MEDIUM,
            avgScore: 60,
            emailFraudScore: null,
            ipFraudScore: null,
            phoneFraudScore: null
        );
        
        $high = new FraudEvaluationResult(
            riskCategory: RiskCategory::HIGH,
            avgScore: 90,
            emailFraudScore: null,
            ipFraudScore: null,
            phoneFraudScore: null
        );
        
        $this->assertSame(RiskCategory::LOW, $low->riskCategory);
        $this->assertSame(RiskCategory::MEDIUM, $medium->riskCategory);
        $this->assertSame(RiskCategory::HIGH, $high->riskCategory);
    }
}
