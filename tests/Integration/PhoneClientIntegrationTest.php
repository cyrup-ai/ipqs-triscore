<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Integration;

use Kodegen\Ipqs\Client\PhoneClient;
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Tests\Fixtures\PhoneApiResponse;
use Kodegen\Ipqs\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PhoneClientIntegrationTest extends TestCase
{
    public function testScoreRawWithValidPhone(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(PhoneApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new PhoneClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('+15551234567', 'US');

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertTrue($result['active']);
        $this->assertFalse($result['VOIP']);
        $this->assertEquals('AT&T', $result['carrier']);
    }

    public function testScoreRawWithHighRiskPhone(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(PhoneApiResponse::highRisk())
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new PhoneClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('+15559999999', 'US');

        $this->assertIsArray($result);
        $this->assertEquals(88, $result['fraud_score']);
        $this->assertTrue($result['VOIP']);
        $this->assertTrue($result['risky']);
    }

    public function testScoreRawHandlesRateLimit(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(PhoneApiResponse::rateLimited())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new PhoneClient($config, new NullLogger(), $mockClient);
        
        $result = $client->scoreRaw('+15551234567', 'US');
        
        // Verify rate limit response is returned
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Rate limit exceeded', $result['message']);
    }

    public function testScoreRawHandlesTimeout(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(PhoneApiResponse::timeout())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new PhoneClient($config, new NullLogger(), $mockClient);
        
        $result = $client->scoreRaw('+15551234567', 'US');
        
        // Verify timeout response is returned
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timeout', $result['message']);
    }

    public function testScoreRawHandlesMalformedJson(): void
    {
        $mockClient = (new MockHttpClient())
            ->addErrorResponse(200, PhoneApiResponse::malformedJson())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new PhoneClient($config, new NullLogger(), $mockClient);
        
        $result = $client->scoreRaw('+15551234567', 'US');
        
        // Verify graceful handling of malformed JSON
        $this->assertNull($result);
    }
}
