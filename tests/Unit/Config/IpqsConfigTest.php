<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Config;

use Kodegen\Ipqs\Config\IpqsConfig;
use PHPUnit\Framework\TestCase;

class IpqsConfigTest extends TestCase
{
    /**
     * Test constructor sets API key
     */
    public function testConstructorSetsApiKey(): void
    {
        $config = new IpqsConfig('test-api-key-123');
        
        $this->assertSame('test-api-key-123', $config->getApiKey());
    }
    
    /**
     * Test constructor with default values
     */
    public function testConstructorWithDefaultValues(): void
    {
        $config = new IpqsConfig('test-key');
        
        $this->assertSame('test-key', $config->getApiKey());
        $this->assertSame('https://ipqualityscore.com/api/json', $config->getBaseUrl());
        $this->assertSame(10, $config->getTimeout());
        $this->assertSame('US', $config->getDefaultCountry());
        $this->assertSame(2, $config->getDefaultStrictness());
    }
    
    /**
     * Test constructor with custom values
     */
    public function testConstructorWithCustomValues(): void
    {
        $config = new IpqsConfig(
            apiKey: 'custom-key',
            baseUrl: 'https://custom.api.com',
            timeout: 30,
            defaultCountry: 'CA',
            defaultStrictness: 3
        );
        
        $this->assertSame('custom-key', $config->getApiKey());
        $this->assertSame('https://custom.api.com', $config->getBaseUrl());
        $this->assertSame(30, $config->getTimeout());
        $this->assertSame('CA', $config->getDefaultCountry());
        $this->assertSame(3, $config->getDefaultStrictness());
    }
    
    /**
     * Test constructor throws exception for empty API key
     */
    public function testConstructorThrowsExceptionForEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IPQS API key cannot be empty');
        
        new IpqsConfig('');
    }
    
    /**
     * Test constructor validates strictness minimum
     */
    public function testConstructorValidatesStrictnessMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Strictness must be between 0 and 3');
        
        new IpqsConfig('test-key', defaultStrictness: -1);
    }
    
    /**
     * Test constructor validates strictness maximum
     */
    public function testConstructorValidatesStrictnessMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Strictness must be between 0 and 3');
        
        new IpqsConfig('test-key', defaultStrictness: 4);
    }
    
    /**
     * Test constructor accepts valid strictness values
     */
    public function testConstructorAcceptsValidStrictnessValues(): void
    {
        $config0 = new IpqsConfig('key', defaultStrictness: 0);
        $config1 = new IpqsConfig('key', defaultStrictness: 1);
        $config2 = new IpqsConfig('key', defaultStrictness: 2);
        $config3 = new IpqsConfig('key', defaultStrictness: 3);
        
        $this->assertSame(0, $config0->getDefaultStrictness());
        $this->assertSame(1, $config1->getDefaultStrictness());
        $this->assertSame(2, $config2->getDefaultStrictness());
        $this->assertSame(3, $config3->getDefaultStrictness());
    }
    
    /**
     * Test fromEnv reads from environment variables
     */
    public function testFromEnvReadsEnvironmentVariables(): void
    {
        $_ENV['IPQS_API_KEY'] = 'env-test-key';
        $_ENV['IPQS_BASE_URL'] = 'https://env.api.com';
        $_ENV['IPQS_TIMEOUT'] = '20';
        $_ENV['IPQS_DEFAULT_COUNTRY'] = 'GB';
        $_ENV['IPQS_DEFAULT_STRICTNESS'] = '1';
        
        $config = IpqsConfig::fromEnv();
        
        $this->assertSame('env-test-key', $config->getApiKey());
        $this->assertSame('https://env.api.com', $config->getBaseUrl());
        $this->assertSame(20, $config->getTimeout());
        $this->assertSame('GB', $config->getDefaultCountry());
        $this->assertSame(1, $config->getDefaultStrictness());
        
        // Cleanup
        unset($_ENV['IPQS_API_KEY'], $_ENV['IPQS_BASE_URL'], $_ENV['IPQS_TIMEOUT'], 
              $_ENV['IPQS_DEFAULT_COUNTRY'], $_ENV['IPQS_DEFAULT_STRICTNESS']);
    }
    
    /**
     * Test fromEnv uses defaults when optional vars not set
     */
    public function testFromEnvUsesDefaultsForOptionalVars(): void
    {
        $_ENV['IPQS_API_KEY'] = 'minimal-key';
        // No other vars set
        
        $config = IpqsConfig::fromEnv();
        
        $this->assertSame('minimal-key', $config->getApiKey());
        $this->assertSame('https://ipqualityscore.com/api/json', $config->getBaseUrl());
        $this->assertSame(10, $config->getTimeout());
        $this->assertSame('US', $config->getDefaultCountry());
        $this->assertSame(2, $config->getDefaultStrictness());
        
        // Cleanup
        unset($_ENV['IPQS_API_KEY']);
    }
    
    /**
     * Test fromEnv throws exception when API key not set
     */
    public function testFromEnvThrowsExceptionWhenApiKeyNotSet(): void
    {
        // Ensure key is not set
        unset($_ENV['IPQS_API_KEY']);
        putenv('IPQS_API_KEY');  // Remove from getenv() too
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IPQS_API_KEY environment variable is required');
        
        IpqsConfig::fromEnv();
    }
    
    /**
     * Test all getters return correct values
     */
    public function testGettersReturnCorrectValues(): void
    {
        $config = new IpqsConfig(
            apiKey: 'getter-test-key',
            baseUrl: 'https://getter.test.com',
            timeout: 15,
            defaultCountry: 'FR',
            defaultStrictness: 0
        );
        
        $this->assertSame('getter-test-key', $config->getApiKey());
        $this->assertSame('https://getter.test.com', $config->getBaseUrl());
        $this->assertSame(15, $config->getTimeout());
        $this->assertSame('FR', $config->getDefaultCountry());
        $this->assertSame(0, $config->getDefaultStrictness());
    }
}
