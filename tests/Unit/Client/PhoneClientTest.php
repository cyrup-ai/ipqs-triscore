<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Client;

use Kodegen\Ipqs\Client\PhoneClient;
use Kodegen\Ipqs\Config\IpqsConfig;
use Kodegen\Ipqs\Exception\InvalidPhoneNumberException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;

class PhoneClientTest extends TestCase
{
    private function createClient(?ClientInterface $httpClient = null): PhoneClient
    {
        $config = new IpqsConfig('test-api-key-12345');
        $logger = new NullLogger();

        return new PhoneClient($config, $logger, $httpClient);
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
        $mockStream = $this->getMockBuilder(StreamInterface::class)->getMock();
        $mockStream->method('getContents')->willReturn($json);

        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockHttp = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->addMethods(['get'])
            ->getMock();
        $mockHttp->method('get')->willReturn($mockResponse);

        return $mockHttp;
    }

    /**
     * Test 1: Empty phone number throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForEmptyPhone(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Phone number cannot be empty');

        $client->scoreRaw('');
    }

    /**
     * Test 2: Whitespace-only phone throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForWhitespaceOnlyPhone(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Phone number cannot be empty');

        $client->scoreRaw('   ');
    }

    /**
     * Test 3: Invalid phone format (letters) throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForInvalidPhoneWithLetters(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        $client->scoreRaw('abc-def-ghij');
    }

    /**
     * Test 4: Invalid phone format (special chars) throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForInvalidPhoneWithSpecialChars(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Invalid phone number format');

        $client->scoreRaw('555@1234#');
    }

    /**
     * Test 5: Invalid country code (too short) throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForInvalidCountryCodeTooShort(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');

        $client->scoreRaw('+15551234567', 'U');
    }

    /**
     * Test 6: Invalid country code (too long) throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForInvalidCountryCodeTooLong(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');

        $client->scoreRaw('+15551234567', 'USA');
    }

    /**
     * Test 7: Invalid country code (numbers) throws InvalidPhoneNumberException
     */
    public function testScoreRawThrowsExceptionForInvalidCountryCodeWithNumbers(): void
    {
        $client = $this->createClient();

        $this->expectException(InvalidPhoneNumberException::class);
        $this->expectExceptionMessage('Country code must be 2-letter ISO code');

        $client->scoreRaw('+15551234567', 'U1');
    }

    /**
     * Test 8: Valid E.164 format phone passes validation
     */
    public function testScoreRawAcceptsValidE164Phone(): void
    {
        $responseData = ['success' => true, 'fraud_score' => 10];
        $mockHttp = $this->createHttpClientWithJsonResponse(json_encode($responseData));

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+15551234567');

        $this->assertIsArray($result);
        $this->assertSame($responseData, $result);
    }

    /**
     * Test 9: Valid phone with dashes passes validation
     */
    public function testScoreRawAcceptsValidPhoneWithDashes(): void
    {
        $responseData = ['success' => true, 'fraud_score' => 5];
        $mockHttp = $this->createHttpClientWithJsonResponse(json_encode($responseData));

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('555-123-4567');

        $this->assertIsArray($result);
        $this->assertSame($responseData, $result);
    }

    /**
     * Test 10: Valid phone with parentheses passes validation
     */
    public function testScoreRawAcceptsValidPhoneWithParentheses(): void
    {
        $responseData = ['success' => true, 'fraud_score' => 8];
        $mockHttp = $this->createHttpClientWithJsonResponse(json_encode($responseData));

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('(555) 123-4567');

        $this->assertIsArray($result);
        $this->assertSame($responseData, $result);
    }

    /**
     * Test 11: HTTP exception (network error) returns null
     */
    public function testScoreRawReturnsNullOnHttpException(): void
    {
        $mockRequest = $this->createMock(RequestInterface::class);
        $exception = new RequestException('Network error', $mockRequest);
        $mockHttp = $this->createHttpClientWithException($exception);

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+15551234567');

        $this->assertNull($result);
    }

    /**
     * Test 12: HTTP exception (connection timeout) returns null
     */
    public function testScoreRawReturnsNullOnConnectionException(): void
    {
        $mockRequest = $this->createMock(RequestInterface::class);
        $exception = new ConnectException('Connection timeout', $mockRequest);
        $mockHttp = $this->createHttpClientWithException($exception);

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+15551234567');

        $this->assertNull($result);
    }

    /**
     * Test 13: JSON decode error (malformed JSON) returns null
     */
    public function testScoreRawReturnsNullOnJsonDecodeError(): void
    {
        $mockHttp = $this->createHttpClientWithJsonResponse('{invalid-json}');

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+15551234567');

        $this->assertNull($result);
    }

    /**
     * Test 14: Non-array response (string) returns null
     */
    public function testScoreRawReturnsNullOnNonArrayResponseString(): void
    {
        $mockHttp = $this->createHttpClientWithJsonResponse('"string-response"');

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+15551234567');

        $this->assertNull($result);
    }

    /**
     * Test 15: Country code is uppercased in URL
     */
    public function testScoreRawUppercasesCountryCode(): void
    {
        $responseData = ['success' => true, 'fraud_score' => 12];

        $mockStream = $this->getMockBuilder(StreamInterface::class)->getMock();
        $mockStream->method('getContents')->willReturn(json_encode($responseData));

        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockHttp = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->addMethods(['get'])
            ->getMock();

        // Verify that country code is uppercased in URL
        $mockHttp->expects($this->once())
            ->method('get')
            ->with($this->stringContains('country=UK'))
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+441234567890', 'uk');  // lowercase 'uk'

        $this->assertIsArray($result);
    }

    /**
     * Test 16: Default country is used when not provided
     */
    public function testScoreRawUsesDefaultCountryWhenNotProvided(): void
    {
        $responseData = ['success' => true, 'fraud_score' => 10];

        $mockStream = $this->getMockBuilder(StreamInterface::class)->getMock();
        $mockStream->method('getContents')->willReturn(json_encode($responseData));

        $mockResponse = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $mockResponse->method('getBody')->willReturn($mockStream);

        $mockHttp = $this->getMockBuilder(ClientInterface::class)
            ->onlyMethods(['sendRequest'])
            ->addMethods(['get'])
            ->getMock();

        // Verify that default country (US) is used
        $mockHttp->expects($this->once())
            ->method('get')
            ->with($this->stringContains('country=US'))
            ->willReturn($mockResponse);

        $client = $this->createClient($mockHttp);
        $result = $client->scoreRaw('+15551234567');  // No country provided

        $this->assertIsArray($result);
    }
}
