<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Model;

use Kodegen\Ipqs\Model\PhoneQualityScore;
use PHPUnit\Framework\TestCase;

class PhoneQualityScoreTest extends TestCase
{
    /**
     * Test 1: Constructor sets all properties
     */
    public function testConstructorSetsAllProperties(): void
    {
        $timestamp = new \DateTimeImmutable('2024-01-15 10:30:00');
        
        $score = new PhoneQualityScore(
            phoneNumber: '+15551234567',
            fraudScore: 15,
            timestamp: $timestamp,
            valid: true,
            active: true,
            recentAbuse: false,
            voip: false,
            prepaid: false,
            risky: false,
            carrier: 'Verizon',
            lineType: 'Wireless',
            country: 'US',
            region: 'California',
            city: 'San Francisco',
            timezone: 'America/Los_Angeles',
            doNotCall: false,
            vendorMetadata: ['key' => 'value']
        );
        
        $this->assertSame('+15551234567', $score->phoneNumber);
        $this->assertSame(15, $score->fraudScore);
        $this->assertEquals($timestamp, $score->timestamp);
        $this->assertTrue($score->valid);
        $this->assertTrue($score->active);
        $this->assertFalse($score->voip);
        $this->assertSame('Verizon', $score->carrier);
        $this->assertSame('Wireless', $score->lineType);
        $this->assertSame(['key' => 'value'], $score->vendorMetadata);
    }
    
    /**
     * Test 2: fromApiResponse creates object from valid phone
     */
    public function testFromApiResponseWithValidPhone(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 10,
            'valid' => true,
            'active' => true,
            'recent_abuse' => false,
            'VOIP' => false,
            'prepaid' => false,
            'risky' => false,
            'carrier' => 'AT&T',
            'line_type' => 'Wireless',
            'country' => 'US',
            'region' => 'Texas',
            'city' => 'Austin',
            'timezone' => 'America/Chicago',
            'do_not_call' => false,
        ];
        
        $score = PhoneQualityScore::fromApiResponse('+15125551234', $response);
        
        $this->assertSame('+15125551234', $score->phoneNumber);
        $this->assertSame(10, $score->fraudScore);
        $this->assertTrue($score->valid);
        $this->assertTrue($score->active);
        $this->assertFalse($score->voip);
        $this->assertSame('AT&T', $score->carrier);
        $this->assertSame('Wireless', $score->lineType);
        $this->assertSame($response, $score->vendorMetadata);
    }
    
    /**
     * Test 3: fromApiResponse handles VOIP phone
     */
    public function testFromApiResponseWithVoipPhone(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 65,
            'valid' => true,
            'active' => true,
            'recent_abuse' => true,
            'VOIP' => true,  // Uppercase variant
            'prepaid' => false,
            'risky' => true,
            'carrier' => 'Google Voice',
            'line_type' => 'VOIP',
            'country' => 'US',
        ];
        
        $score = PhoneQualityScore::fromApiResponse('+15555550100', $response);
        
        $this->assertSame(65, $score->fraudScore);
        $this->assertTrue($score->voip);
        $this->assertTrue($score->risky);
        $this->assertTrue($score->recentAbuse);
        $this->assertSame('VOIP', $score->lineType);
    }
    
    /**
     * Test 4: fromApiResponse handles invalid phone
     */
    public function testFromApiResponseWithInvalidPhone(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 90,
            'valid' => false,
            'active' => false,
            'carrier' => 'Unknown',
            'line_type' => 'Unknown',
            'country' => '',
        ];
        
        $score = PhoneQualityScore::fromApiResponse('invalid-number', $response);
        
        $this->assertFalse($score->valid);
        $this->assertFalse($score->active);
        $this->assertSame(90, $score->fraudScore);
    }
    
    /**
     * Test 5: fromApiResponse handles missing fields with defaults
     */
    public function testFromApiResponseWithMissingFields(): void
    {
        $response = [
            'success' => true,
            // Most fields missing
        ];
        
        $score = PhoneQualityScore::fromApiResponse('+15551234567', $response);
        
        $this->assertSame(0, $score->fraudScore);
        $this->assertFalse($score->valid);
        $this->assertNull($score->active);
        $this->assertNull($score->voip);
        $this->assertNull($score->prepaid);
        $this->assertNull($score->risky);
        $this->assertSame('', $score->carrier);
        $this->assertSame('', $score->lineType);
        $this->assertSame('', $score->country);
        $this->assertNull($score->region);
        $this->assertNull($score->city);
    }
    
    /**
     * Test 6: fromApiResponse handles lowercase voip field
     */
    public function testFromApiResponseWithLowercaseVoipField(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 50,
            'voip' => true,  // Lowercase variant
        ];
        
        $score = PhoneQualityScore::fromApiResponse('+15551234567', $response);
        
        $this->assertTrue($score->voip);
    }
    
    /**
     * Test 7: fromApiResponse prioritizes uppercase VOIP
     */
    public function testFromApiResponsePrioritizesUppercaseVoip(): void
    {
        $response = [
            'success' => true,
            'VOIP' => true,   // Uppercase should win
            'voip' => false,  // Lowercase ignored
        ];
        
        $score = PhoneQualityScore::fromApiResponse('+15551234567', $response);
        
        $this->assertTrue($score->voip);
    }
    
    /**
     * Test 8: Timestamp is set to current time
     */
    public function testFromApiResponseSetsCurrentTimestamp(): void
    {
        $before = new \DateTimeImmutable();
        
        $score = PhoneQualityScore::fromApiResponse('+15551234567', ['success' => true]);
        
        $after = new \DateTimeImmutable();
        
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $score->timestamp->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $score->timestamp->getTimestamp());
    }
}
