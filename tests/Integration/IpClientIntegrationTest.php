<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Integration;

use Kodegen\Ipqs\Client\IpClient;
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Tests\Fixtures\IpApiResponse;
use Kodegen\Ipqs\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class IpClientIntegrationTest extends TestCase
{
    public function testScoreRawWithValidResponse(): void
    {
        // Arrange: Create mock HTTP client with fixture response
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::success(['fraud_score' => 25]))
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        // Act: Call scoreRaw with test IP
        $result = $client->scoreRaw('8.8.8.8');

        // Assert: Verify response structure
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(25, $result['fraud_score']);
        $this->assertEquals('US', $result['country_code']);
    }

    public function testScoreRawWithHighFraudScore(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::highFraud())
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('1.2.3.4');

        $this->assertIsArray($result);
        $this->assertEquals(95, $result['fraud_score']);
        $this->assertTrue($result['vpn']);
        $this->assertTrue($result['proxy']);
        $this->assertTrue($result['recent_abuse']);
    }

    public function testScoreRawHandlesApiError(): void
    {
        $mockClient = (new MockHttpClient())
            ->addErrorResponse(500, 'Internal Server Error')
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('8.8.8.8');

        // Client should return null on error (as per src/Client/IpClient.php:115)
        $this->assertNull($result);
    }

    public function testScoreRawHandlesRateLimit(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::rateLimited())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);
        
        $result = $client->scoreRaw('8.8.8.8');
        
        // Verify rate limit response is returned
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Rate limit exceeded', $result['message']);
    }

    public function testScoreRawHandlesTimeout(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::timeout())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);
        
        $result = $client->scoreRaw('8.8.8.8');
        
        // Verify timeout response is returned
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timeout', $result['message']);
    }

    public function testScoreRawHandlesMalformedJson(): void
    {
        $mockClient = (new MockHttpClient())
            ->addErrorResponse(200, IpApiResponse::malformedJson())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);
        
        $result = $client->scoreRaw('8.8.8.8');
        
        // Verify graceful handling of malformed JSON
        $this->assertNull($result);
    }
}
