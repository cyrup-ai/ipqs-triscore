<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Client;

use Kodegen\Ipqs\Client\IpClient;
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Exception\InvalidIpAddressException;
use Kodegen\Ipqs\Tests\Fixtures\IpApiResponse;
use Kodegen\Ipqs\Tests\Support\MockHttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class IpClientTest extends TestCase
{
    // ========== 1. Constructor Tests ==========

    public function testConstructorStoresDependencies(): void
    {
        $config = new IpqsConfig('test-key');
        $logger = new NullLogger();
        $httpClient = $this->createMock(ClientInterface::class);

        $client = new IpClient($config, $logger, $httpClient);

        // Verify construction succeeds (no exceptions)
        $this->assertInstanceOf(IpClient::class, $client);
    }

    // ========== 2. IP Validation Tests ==========

    /**
     * @dataProvider invalidIpProvider
     */
    public function testScoreRawThrowsExceptionForInvalidIp(string $invalidIp, string $expectedMessagePart): void
    {
        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $this->createMock(ClientInterface::class));

        $this->expectException(InvalidIpAddressException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        $client->scoreRaw($invalidIp);
    }

    public static function invalidIpProvider(): array
    {
        return [
            'empty string' => ['', 'IP address cannot be empty'],
            'just spaces' => ['   ', 'IP address cannot be empty'],
            'not an IP' => ['not-an-ip', 'Invalid IP address format: not-an-ip'],
            'letters' => ['abc.def.ghi.jkl', 'Invalid IP address format: abc.def.ghi.jkl'],
            'incomplete IPv4' => ['192.168.1', 'Invalid IP address format: 192.168.1'],
            'out of range' => ['256.256.256.256', 'Invalid IP address format: 256.256.256.256'],
            'invalid IPv6' => ['gggg::1', 'Invalid IP address format: gggg::1'],
        ];
    }

    // ========== 3. IPv4 Validation Success Tests ==========

    /**
     * @dataProvider validIpv4Provider
     */
    public function testScoreRawAcceptsValidIpv4(string $validIp): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw($validIp);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public static function validIpv4Provider(): array
    {
        return [
            'standard' => ['8.8.8.8'],
            'private' => ['192.168.1.1'],
            'loopback' => ['127.0.0.1'],
            'zero' => ['0.0.0.0'],
            'broadcast' => ['255.255.255.255'],
            'cloudflare' => ['1.1.1.1'],
        ];
    }

    // ========== 4. IPv6 Validation Success Tests ==========

    /**
     * @dataProvider validIpv6Provider
     */
    public function testScoreRawAcceptsValidIpv6(string $validIp): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw($validIp);

        $this->assertIsArray($result);
    }

    public static function validIpv6Provider(): array
    {
        return [
            'loopback' => ['::1'],
            'full' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            'compressed' => ['2001:db8:85a3::8a2e:370:7334'],
            'ipv4 mapped' => ['::ffff:192.0.2.1'],
            'all zeros' => ['::'],
            'google dns' => ['2001:4860:4860::8888'],
        ];
    }

    // ========== 5. Query Parameter Building Tests ==========

    public function testScoreRawAcceptsAdditionalParams(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('8.8.8.8', [
            'user_agent' => 'Mozilla/5.0',
            'language' => 'en',
            'mobile' => 'true',
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function testScoreRawWithStrictnessOverride(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-key', defaultStrictness: 1);
        $client = new IpClient($config, new NullLogger(), $mockClient);

        // Additional params should override config params
        $result = $client->scoreRaw('8.8.8.8', ['strictness' => 3]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    // ========== 6. HTTP Exception Handling Tests ==========

    public function testScoreRawHandlesHttpClientException(): void
    {
        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->method('get')
            ->willThrowException(new \GuzzleHttp\Exception\RequestException(
                'Network error',
                new \GuzzleHttp\Psr7\Request('GET', 'http://example.com')
            ));

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('8.8.8.8');

        $this->assertNull($result);
    }

    public function testScoreRawLogsErrorOnHttpException(): void
    {
        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->method('get')
            ->willThrowException(new \GuzzleHttp\Exception\ConnectException(
                'Connection timeout',
                new \GuzzleHttp\Psr7\Request('GET', 'http://example.com')
            ));

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('IpClient::scoreRaw failed'),
                $this->callback(function ($context) {
                    return isset($context['ipAddress'])
                        && $context['ipAddress'] === '8.8.8.8'
                        && isset($context['error'])
                        && isset($context['url'])
                        && str_contains($context['error'], 'Connection timeout');
                })
            );

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, $mockLogger, $mockClient);

        $client->scoreRaw('8.8.8.8');
    }

    // ========== 7. JSON Decode Error Tests ==========

    public function testScoreRawHandlesInvalidJsonResponse(): void
    {
        $mockBody = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockBody->method('getContents')->willReturn('invalid json {]');

        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockResponse->method('getBody')->willReturn($mockBody);

        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->method('get')->willReturn($mockResponse);

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('8.8.8.8');

        $this->assertNull($result);
    }

    public function testScoreRawLogsErrorOnJsonError(): void
    {
        $mockBody = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockBody->method('getContents')->willReturn('not valid json');

        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockResponse->method('getBody')->willReturn($mockBody);

        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->method('get')->willReturn($mockResponse);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('JSON decode failed'),
                $this->callback(function ($context) {
                    return isset($context['ipAddress'])
                        && $context['ipAddress'] === '1.2.3.4'
                        && isset($context['url'])
                        && isset($context['error'])
                        && isset($context['body']);
                })
            );

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, $mockLogger, $mockClient);

        $client->scoreRaw('1.2.3.4');
    }

    // ========== 7.5. Non-Array Response Tests ==========

    public function testScoreRawHandlesNonArrayJsonResponse(): void
    {
        $mockBody = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockBody->method('getContents')->willReturn('"string response"');

        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockResponse->method('getBody')->willReturn($mockBody);

        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->method('get')->willReturn($mockResponse);

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('8.8.8.8');

        $this->assertNull($result);
    }

    public function testScoreRawLogsErrorOnNonArrayResponse(): void
    {
        $mockBody = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $mockBody->method('getContents')->willReturn('123');

        $mockResponse = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
        $mockResponse->method('getBody')->willReturn($mockBody);

        $mockClient = $this->createMock(\GuzzleHttp\Client::class);
        $mockClient->method('get')->willReturn($mockResponse);

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('response is not an array'),
                $this->callback(function ($context) {
                    return isset($context['ipAddress'])
                        && $context['ipAddress'] === '1.2.3.4'
                        && isset($context['url'])
                        && isset($context['type'])
                        && isset($context['body']);
                })
            );

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, $mockLogger, $mockClient);

        $client->scoreRaw('1.2.3.4');
    }

    // ========== 8. Success Response Tests ==========

    public function testScoreRawReturnsSuccessResponse(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::success())
            ->build();

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('8.8.8.8');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('fraud_score', $result);
        $this->assertArrayHasKey('proxy', $result);
    }

    public function testScoreRawReturnsHighFraudResponse(): void
    {
        $mockClient = (new MockHttpClient())
            ->addJsonResponse(IpApiResponse::highFraud())
            ->build();

        $config = new IpqsConfig('test-key');
        $client = new IpClient($config, new NullLogger(), $mockClient);

        $result = $client->scoreRaw('1.2.3.4');

        $this->assertIsArray($result);
        $this->assertTrue($result['proxy']);
        $this->assertTrue($result['vpn']);
        $this->assertGreaterThanOrEqual(90, $result['fraud_score']);
    }
}
