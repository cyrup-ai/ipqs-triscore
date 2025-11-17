<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Integration;

use Kodegen\Ipqs\Client\EmailClient;
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Util\EmailNormalizer;
use Kodegen\Ipqs\Tests\Fixtures\EmailApiResponse;
use Kodegen\Ipqs\Tests\Support\MockHttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EmailClientIntegrationTest extends TestCase
{
    public function testScoreRawWithValidEmail(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(EmailApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new EmailClient($config, new NullLogger(), new EmailNormalizer(new NullLogger()), $mockClient);

        $result = $client->scoreRaw('john@example.com');

        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertEquals('high', $result['deliverability']);
        $this->assertFalse($result['disposable']);
    }

    public function testScoreRawWithDisposableEmail(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(EmailApiResponse::disposable())
            ->build();

        $config = new IpqsConfig('test-api-key');
        $client = new EmailClient($config, new NullLogger(), new EmailNormalizer(new NullLogger()), $mockClient);

        $result = $client->scoreRaw('temp@tempmail.com');

        $this->assertIsArray($result);
        $this->assertTrue($result['disposable']);
        $this->assertEquals(75, $result['fraud_score']);
        $this->assertTrue($result['suspect']);
    }

    public function testScoreRawHandlesRateLimit(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(EmailApiResponse::rateLimited())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new EmailClient($config, new NullLogger(), new EmailNormalizer(new NullLogger()), $mockClient);
        
        $result = $client->scoreRaw('test@example.com');
        
        // Verify rate limit response is returned
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Rate limit exceeded', $result['message']);
    }

    public function testScoreRawHandlesTimeout(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(EmailApiResponse::timeout())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new EmailClient($config, new NullLogger(), new EmailNormalizer(new NullLogger()), $mockClient);
        
        $result = $client->scoreRaw('test@example.com');
        
        // Verify timeout response is returned
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('timeout', $result['message']);
    }

    public function testScoreRawHandlesMalformedJson(): void
    {
        $mockClient = (new MockHttpClient())
            ->addErrorResponse(200, EmailApiResponse::malformedJson())
            ->build();
        
        $config = new IpqsConfig('test-api-key');
        $client = new EmailClient($config, new NullLogger(), new EmailNormalizer(new NullLogger()), $mockClient);
        
        $result = $client->scoreRaw('test@example.com');
        
        // Verify graceful handling of malformed JSON
        $this->assertNull($result);
    }
}
