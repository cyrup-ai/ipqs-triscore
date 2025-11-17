<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Model;

use Kodegen\Ipqs\Model\IpQualityScore;
use Kodegen\Ipqs\Enum\ConnectionType;
use Kodegen\Ipqs\Enum\AbuseVelocity;
use PHPUnit\Framework\TestCase;

class IpQualityScoreTest extends TestCase
{
    /**
     * Test 1: Constructor sets all properties including enums
     */
    public function testConstructorSetsAllProperties(): void
    {
        $timestamp = new \DateTimeImmutable();

        $score = new IpQualityScore(
            ipAddress: '192.168.1.1',
            fraudScore: 45,
            timestamp: $timestamp,
            countryCode: 'US',
            region: 'California',
            city: 'San Francisco',
            latitude: 37.7749,
            longitude: -122.4194,
            isp: 'Comcast',
            asn: 7922,
            isCrawler: false,
            proxy: false,
            vpn: false,
            tor: false,
            recentAbuse: false,
            botStatus: false,
            connectionType: ConnectionType::RESIDENTIAL,
            abuseVelocity: AbuseVelocity::LOW,
            timezone: 'America/Los_Angeles',
            vendorMetadata: []
        );

        $this->assertSame('192.168.1.1', $score->ipAddress);
        $this->assertSame(45, $score->fraudScore);
        $this->assertSame('US', $score->countryCode);
        $this->assertSame(ConnectionType::RESIDENTIAL, $score->connectionType);
        $this->assertSame(AbuseVelocity::LOW, $score->abuseVelocity);
    }
    
    /**
     * Test 2: fromApiResponse creates object from clean residential IP
     */
    public function testFromApiResponseWithCleanResidentialIp(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 10.0,
            'proxy' => false,
            'vpn' => false,
            'tor' => false,
            'country_code' => 'US',
            'region' => 'California',
            'city' => 'San Francisco',
            'ISP' => 'Comcast Cable',
            'connection_type' => 'Residential',
            'abuse_velocity' => 'low',
        ];
        
        $score = IpQualityScore::fromApiResponse('8.8.8.8', $response);

        $this->assertSame('8.8.8.8', $score->ipAddress);
        $this->assertSame(10, $score->fraudScore);
        $this->assertFalse($score->proxy);
        $this->assertFalse($score->vpn);
        $this->assertFalse($score->tor);
        $this->assertSame(ConnectionType::RESIDENTIAL, $score->connectionType);
        $this->assertSame(AbuseVelocity::LOW, $score->abuseVelocity);
    }
    
    /**
     * Test 3: fromApiResponse detects VPN/Proxy
     */
    public function testFromApiResponseWithVpnProxy(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 75.0,
            'proxy' => true,
            'vpn' => true,
            'tor' => false,
            'country_code' => 'NL',
            'ISP' => 'NordVPN',
            'connection_type' => 'Premium required.',  // VPN detection
            'abuse_velocity' => 'high',
        ];

        $score = IpQualityScore::fromApiResponse('45.67.89.123', $response);

        $this->assertSame(75, $score->fraudScore);
        $this->assertTrue($score->proxy);
        $this->assertTrue($score->vpn);
        $this->assertFalse($score->tor);
        $this->assertNull($score->connectionType);  // "Premium required." is not mapped
        $this->assertSame(AbuseVelocity::HIGH, $score->abuseVelocity);
    }
    
    /**
     * Test 4: fromApiResponse detects Tor exit node
     */
    public function testFromApiResponseWithTorNode(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 90.0,
            'proxy' => true,
            'vpn' => false,
            'tor' => true,  // Tor detected
            'country_code' => 'Unknown',
            'connection_type' => 'Premium required.',
            'abuse_velocity' => 'high',
        ];

        $score = IpQualityScore::fromApiResponse('185.220.101.1', $response);

        $this->assertSame(90, $score->fraudScore);
        $this->assertTrue($score->tor);
        $this->assertTrue($score->proxy);
        $this->assertSame(AbuseVelocity::HIGH, $score->abuseVelocity);
    }
    
    /**
     * Test 5: fromApiResponse handles corporate connection
     */
    public function testFromApiResponseWithCorporateConnection(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 5.0,
            'proxy' => false,
            'vpn' => false,
            'tor' => false,
            'country_code' => 'US',
            'ISP' => 'Google LLC',
            'connection_type' => 'Corporate',
            'abuse_velocity' => 'none',
        ];
        
        $score = IpQualityScore::fromApiResponse('8.8.4.4', $response);
        
        $this->assertSame(ConnectionType::CORPORATE, $score->connectionType);
        $this->assertSame(AbuseVelocity::NONE, $score->abuseVelocity);
    }
    
    /**
     * Test 6: fromApiResponse handles missing/invalid connection type
     */
    public function testFromApiResponseWithInvalidConnectionType(): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 20.0,
            'connection_type' => 'Unknown Type',  // Not a valid enum value
            'abuse_velocity' => 'invalid',       // Not a valid enum value
        ];
        
        $score = IpQualityScore::fromApiResponse('1.2.3.4', $response);
        
        $this->assertNull($score->connectionType);  // Falls back to null
        $this->assertNull($score->abuseVelocity);   // Falls back to null
    }
    
    /**
     * Test 7: Test all ConnectionType enum mappings
     * 
     * @dataProvider connectionTypeProvider
     */
    public function testConnectionTypeMapping(string $apiValue, ?ConnectionType $expected): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 0,
            'connection_type' => $apiValue,
        ];
        
        $score = IpQualityScore::fromApiResponse('1.1.1.1', $response);
        
        $this->assertSame($expected, $score->connectionType);
    }
    
    public static function connectionTypeProvider(): array
    {
        return [
            'residential' => ['Residential', ConnectionType::RESIDENTIAL],
            'corporate' => ['Corporate', ConnectionType::CORPORATE],
            'education' => ['Education', ConnectionType::EDUCATION],
            'mobile' => ['Mobile', ConnectionType::MOBILE],
            'data center' => ['Data Center', ConnectionType::DATA_CENTER],
            'invalid' => ['Invalid', null],
            'premium' => ['Premium required.', null],
        ];
    }
    
    /**
     * Test 8: Test all AbuseVelocity enum mappings
     * 
     * @dataProvider abuseVelocityProvider
     */
    public function testAbuseVelocityMapping(string $apiValue, ?AbuseVelocity $expected): void
    {
        $response = [
            'success' => true,
            'fraud_score' => 0,
            'abuse_velocity' => $apiValue,
        ];
        
        $score = IpQualityScore::fromApiResponse('1.1.1.1', $response);
        
        $this->assertSame($expected, $score->abuseVelocity);
    }
    
    public static function abuseVelocityProvider(): array
    {
        return [
            'none' => ['none', AbuseVelocity::NONE],
            'low' => ['low', AbuseVelocity::LOW],
            'medium' => ['medium', AbuseVelocity::MEDIUM],
            'high' => ['high', AbuseVelocity::HIGH],
            'invalid' => ['invalid', null],
        ];
    }
}
