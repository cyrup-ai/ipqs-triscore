<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Model;

use Kodegen\Ipqs\Model\EmailQualityScore;
use PHPUnit\Framework\TestCase;

class EmailQualityScoreTest extends TestCase
{
    /**
     * Test 1: Constructor creates object with all properties
     */
    public function testConstructorSetsAllProperties(): void
    {
        $timestamp = new \DateTimeImmutable('2024-01-15 10:30:00');
        
        $score = new EmailQualityScore(
            email: 'test@example.com',
            fraudScore: 25,
            timestamp: $timestamp,
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
            honeypot: false,
            vendorMetadata: ['key' => 'value']
        );
        
        $this->assertSame('test@example.com', $score->email);
        $this->assertSame(25, $score->fraudScore);
        $this->assertEquals($timestamp, $score->timestamp);
        $this->assertTrue($score->valid);
        $this->assertFalse($score->disposable);
        $this->assertSame(3, $score->smtpScore);
        $this->assertSame('high', $score->deliverability);
        $this->assertSame(['key' => 'value'], $score->vendorMetadata);
    }
    
    /**
     * Test 2: fromApiResponse creates object from typical successful response
     */
    public function testFromApiResponseWithSuccessfulResponse(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 15.5,  // Float from API
            'valid' => true,
            'disposable' => false,
            'leaked' => false,
            'suspect' => false,
            'recent_abuse' => false,
            'smtp_score' => 3,
            'overall_score' => 4,
            'deliverability' => 'high',
            'catch_all' => false,
            'generic' => false,
            'honeypot' => false,
        ];
        
        $score = EmailQualityScore::fromApiResponse('john@example.com', $response);
        
        $this->assertSame('john@example.com', $score->email);
        $this->assertSame(15, $score->fraudScore);  // Cast to int
        $this->assertTrue($score->valid);
        $this->assertSame('high', $score->deliverability);
        $this->assertSame($response, $score->vendorMetadata);
    }
    
    /**
     * Test 3: fromApiResponse handles high fraud score (disposable email)
     */
    public function testFromApiResponseWithDisposableEmail(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 85.0,
            'valid' => true,
            'disposable' => true,
            'leaked' => false,
            'suspect' => true,
            'recent_abuse' => false,
            'smtp_score' => 2,
            'overall_score' => 1,
            'deliverability' => 'low',
            'catch_all' => false,
            'generic' => false,
            'honeypot' => false,
        ];
        
        $score = EmailQualityScore::fromApiResponse('temp@10minutemail.com', $response);
        
        $this->assertSame(85, $score->fraudScore);
        $this->assertTrue($score->disposable);
        $this->assertTrue($score->suspect);
        $this->assertSame('low', $score->deliverability);
    }
    
    /**
     * Test 4: fromApiResponse handles missing fields with defaults
     */
    public function testFromApiResponseWithMissingFields(): void
    {
        $response = [
            'success' => true,
            // Most fields missing
        ];
        
        $score = EmailQualityScore::fromApiResponse('minimal@example.com', $response);
        
        $this->assertSame(0, $score->fraudScore);
        $this->assertFalse($score->valid);
        $this->assertFalse($score->disposable);
        $this->assertSame(0, $score->smtpScore);
        $this->assertSame('unknown', $score->deliverability);
    }
    
    /**
     * Test 5: fromApiResponse handles leaked email (dark web exposure)
     */
    public function testFromApiResponseWithLeakedEmail(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 65.0,
            'valid' => true,
            'disposable' => false,
            'leaked' => true,  // Email found in data breaches
            'suspect' => true,
            'recent_abuse' => true,
            'smtp_score' => 3,
            'overall_score' => 2,
            'deliverability' => 'medium',
            'catch_all' => false,
            'generic' => false,
            'honeypot' => false,
        ];
        
        $score = EmailQualityScore::fromApiResponse('leaked@example.com', $response);
        
        $this->assertTrue($score->leaked);
        $this->assertTrue($score->recentAbuse);
        $this->assertSame(65, $score->fraudScore);
    }
    
    /**
     * Test 6: Timestamp is set to current time
     */
    public function testFromApiResponseSetsCurrentTimestamp(): void
    {
        $before = new \DateTimeImmutable();
        
        $score = EmailQualityScore::fromApiResponse('test@example.com', ['success' => true]);
        
        $after = new \DateTimeImmutable();
        
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $score->timestamp->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $score->timestamp->getTimestamp());
    }
}
