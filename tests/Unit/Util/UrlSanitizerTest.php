<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Util;

use Kodegen\Ipqs\Util\UrlSanitizer;
use PHPUnit\Framework\TestCase;

class UrlSanitizerTest extends TestCase
{
    /**
     * Test 1: Sanitize email API URL
     */
    public function testSanitizeEmailApiUrl(): void
    {
        $url = 'https://ipqualityscore.com/api/json/email/abc123key456/john@example.com';
        $expected = 'https://ipqualityscore.com/api/json/email/***REDACTED***/john@example.com';
        
        $this->assertSame($expected, UrlSanitizer::sanitize($url));
    }
    
    /**
     * Test 2: Sanitize IP API URL
     */
    public function testSanitizeIpApiUrl(): void
    {
        $url = 'https://ipqualityscore.com/api/json/ip/myapikey123/192.168.1.1';
        $expected = 'https://ipqualityscore.com/api/json/ip/***REDACTED***/192.168.1.1';
        
        $this->assertSame($expected, UrlSanitizer::sanitize($url));
    }
    
    /**
     * Test 3: Sanitize phone API URL
     */
    public function testSanitizePhoneApiUrl(): void
    {
        $url = 'https://ipqualityscore.com/api/json/phone/secretkey789/15551234567';
        $expected = 'https://ipqualityscore.com/api/json/phone/***REDACTED***/15551234567';
        
        $this->assertSame($expected, UrlSanitizer::sanitize($url));
    }
    
    /**
     * Test 4: Sanitize URL with query parameters
     */
    public function testSanitizeUrlWithQueryParams(): void
    {
        $url = 'https://ipqualityscore.com/api/json/email/key123/test@example.com?strictness=2';
        $expected = 'https://ipqualityscore.com/api/json/email/***REDACTED***/test@example.com?strictness=2';
        
        $this->assertSame($expected, UrlSanitizer::sanitize($url));
    }
    
    /**
     * Test 5: Does not sanitize non-IPQS URLs
     */
    public function testDoesNotSanitizeNonIpqsUrls(): void
    {
        $url = 'https://example.com/api/data';
        
        $this->assertSame($url, UrlSanitizer::sanitize($url));
    }
    
    /**
     * Test 6: Sanitize URL with complex path
     */
    public function testSanitizeUrlWithComplexPath(): void
    {
        $url = 'https://ipqualityscore.com/api/json/ip/longapikey123456789/8.8.8.8/additional/path';
        $expected = 'https://ipqualityscore.com/api/json/ip/***REDACTED***/8.8.8.8/additional/path';
        
        $this->assertSame($expected, UrlSanitizer::sanitize($url));
    }
    
    /**
     * Test 7: forLogging returns array with sanitized URL
     */
    public function testForLoggingReturnsArray(): void
    {
        $url = 'https://ipqualityscore.com/api/json/email/apikey/user@example.com';
        $result = UrlSanitizer::forLogging($url);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('url', $result);
        $this->assertStringContainsString('***REDACTED***', $result['url']);
        $this->assertStringNotContainsString('apikey', $result['url']);
    }
    
    /**
     * Test 8: isIpqsApiUrl detects email API URLs
     */
    public function testIsIpqsApiUrlDetectsEmailUrls(): void
    {
        $url = 'https://ipqualityscore.com/api/json/email/key/test@example.com';
        
        $this->assertTrue(UrlSanitizer::isIpqsApiUrl($url));
    }
    
    /**
     * Test 9: isIpqsApiUrl detects IP API URLs
     */
    public function testIsIpqsApiUrlDetectsIpUrls(): void
    {
        $url = 'https://ipqualityscore.com/api/json/ip/key/1.1.1.1';
        
        $this->assertTrue(UrlSanitizer::isIpqsApiUrl($url));
    }
    
    /**
     * Test 10: isIpqsApiUrl detects phone API URLs
     */
    public function testIsIpqsApiUrlDetectsPhoneUrls(): void
    {
        $url = 'https://ipqualityscore.com/api/json/phone/key/15551234567';
        
        $this->assertTrue(UrlSanitizer::isIpqsApiUrl($url));
    }
    
    /**
     * Test 11: isIpqsApiUrl returns false for non-IPQS URLs
     */
    public function testIsIpqsApiUrlReturnsFalseForNonIpqsUrls(): void
    {
        $this->assertFalse(UrlSanitizer::isIpqsApiUrl('https://example.com'));
        $this->assertFalse(UrlSanitizer::isIpqsApiUrl('https://google.com/api/json/data'));
        $this->assertFalse(UrlSanitizer::isIpqsApiUrl('https://ipqualityscore.com/other'));
    }
    
    /**
     * Test 12: Sanitize preserves protocol
     */
    public function testSanitizePreservesProtocol(): void
    {
        $https = 'https://ipqualityscore.com/api/json/email/key/test@example.com';
        $http = 'http://ipqualityscore.com/api/json/email/key/test@example.com';
        
        $this->assertStringStartsWith('https://', UrlSanitizer::sanitize($https));
        $this->assertStringStartsWith('http://', UrlSanitizer::sanitize($http));
    }
    
    /**
     * Test 13: Sanitize handles special characters in email
     */
    public function testSanitizeHandlesSpecialCharactersInEmail(): void
    {
        $url = 'https://ipqualityscore.com/api/json/email/key/user+tag@example.com';
        $result = UrlSanitizer::sanitize($url);
        
        $this->assertStringContainsString('***REDACTED***', $result);
        $this->assertStringContainsString('user+tag@example.com', $result);
    }
    
    /**
     * Test 14: Empty URL returns as-is
     */
    public function testEmptyUrlReturnsAsIs(): void
    {
        $this->assertSame('', UrlSanitizer::sanitize(''));
    }
}
