<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Client;

use Kodegen\Ipqs\Client\EmailClient;
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Exception\InvalidEmailException;
use Kodegen\Ipqs\Util\EmailNormalizer;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

class EmailClientTest extends TestCase
{
    private function createClient(?ClientInterface $httpClient = null): EmailClient
    {
        $config = new IpqsConfig('test-api-key-12345');
        $logger = new NullLogger();
        $normalizer = new EmailNormalizer($logger);

        return new EmailClient($config, $logger, $normalizer, $httpClient);
    }

    private function createHttpClientWithException(\Throwable $exception): ClientInterface
    {
        $mockHttp = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->addMethods(['get'])
            ->getMock();
        $mockHttp->method('get')->willThrowException($exception);
        return $mockHttp;
    }

    private function createHttpClientWithJsonResponse(string $json): ClientInterface
    {
        $mockStream = $this->getMockBuilder(StreamInterface::class)
            ->getMock();
        $mockStream->method('getContents')->willReturn($json);

        $mockResponse = $this->getMockBuilder(ResponseInterface::class)
            ->getMock();
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockHttp = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->addMethods(['get'])
            ->getMock();
        $mockHttp->method('get')->willReturn($mockResponse);

        return $mockHttp;
    }

    /**
     * Test 1: Empty email throws InvalidEmailException
     */
    public function testScoreRawThrowsExceptionForEmptyEmail(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Email address cannot be empty');

        $client->scoreRaw('');
    }

    /**
     * Test 2: Whitespace-only email throws InvalidEmailException
     */
    public function testScoreRawThrowsExceptionForWhitespaceOnlyEmail(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Email address cannot be empty');

        $client->scoreRaw('   ');
    }

    /**
     * Test 3: Invalid email format (no @ symbol) throws InvalidEmailException
     */
    public function testScoreRawThrowsExceptionForInvalidEmailNoAtSymbol(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Invalid email address format');

        $client->scoreRaw('not-an-email');
    }

    /**
     * Test 4: Invalid email format (missing domain) throws InvalidEmailException
     */
    public function testScoreRawThrowsExceptionForInvalidEmailMissingDomain(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Invalid email address format');

        $client->scoreRaw('user@');
    }

    /**
     * Test 5: Invalid email format (missing username) throws InvalidEmailException
     */
    public function testScoreRawThrowsExceptionForInvalidEmailMissingUsername(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Invalid email address format');

        $client->scoreRaw('@example.com');
    }

    /**
     * Test 6: Invalid email format (special characters) throws InvalidEmailException
     */
    public function testScoreRawThrowsExceptionForInvalidEmailSpecialChars(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidEmailException::class);
        $this->expectExceptionMessage('Invalid email address format');

        $client->scoreRaw('user @example.com');
    }

    /**
     * Test 7: HTTP exception (network error) returns null
     */
    public function testScoreRawReturnsNullOnHttpException(): void
    {
        $mockRequest = $this->createMock(RequestInterface::class);
        $exception = new RequestException('Network error', $mockRequest);
        $mockHttp = $this->createHttpClientWithException($exception);

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test 8: HTTP exception (connection timeout) returns null
     */
    public function testScoreRawReturnsNullOnConnectionException(): void
    {
        $mockRequest = $this->createMock(RequestInterface::class);
        $exception = new ConnectException('Connection timeout', $mockRequest);
        $mockHttp = $this->createHttpClientWithException($exception);

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test 9: JSON decode error (malformed JSON) returns null
     */
    public function testScoreRawReturnsNullOnJsonDecodeError(): void
    {
        $mockHttp = $this->createHttpClientWithJsonResponse('{invalid-json}');

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test 10: JSON decode error (truncated JSON) returns null
     */
    public function testScoreRawReturnsNullOnTruncatedJson(): void
    {
        $mockHttp = $this->createHttpClientWithJsonResponse('{"success": true, "fraud_score":');

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test 11: Non-array response (string) returns null
     */
    public function testScoreRawReturnsNullOnNonArrayResponseString(): void
    {
        $mockHttp = $this->createHttpClientWithJsonResponse('"string-response"');

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test 12: Non-array response (integer) returns null
     */
    public function testScoreRawReturnsNullOnNonArrayResponseInteger(): void
    {
        $mockHttp = $this->createHttpClientWithJsonResponse('42');

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertNull($result);
    }

    /**
     * Test 13: Successful response returns array
     */
    public function testScoreRawReturnsArrayOnSuccessfulResponse(): void
    {
        $responseData = [
            'success' => true,
            'fraud_score' => 25,
            'valid' => true,
            'disposable' => false,
        ];
        $mockHttp = $this->createHttpClientWithJsonResponse(json_encode($responseData));

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('test@example.com');

        $this->assertIsArray($result);
        $this->assertSame($responseData, $result);
    }

    /**
     * Test 14: Email normalization is applied before API call
     */
    public function testScoreRawNormalizesEmail(): void
    {
        $responseData = ['success' => true, 'fraud_score' => 0];

        $mockStream = $this->getMockBuilder(StreamInterface::class)->getMock();
        $mockStream->method('getContents')->willReturn(json_encode($responseData));

        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockHttp = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->addMethods(['get'])
            ->getMock();

        // Verify that the normalized email is used in the URL
        $mockHttp->expects($this->once())
            ->method('get')
            ->with($this->stringContains('johndoe@gmail.com'))
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttp);

        // Gmail normalization: john.doe@gmail.com -> johndoe@gmail.com
        $result = $client->scoreRaw('john.doe@gmail.com');

        $this->assertIsArray($result);
    }
}
