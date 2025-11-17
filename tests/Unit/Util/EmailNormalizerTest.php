<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Util;

use Kodegen\Ipqs\Util\EmailNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EmailNormalizerTest extends TestCase
{
    private EmailNormalizer $normalizer;
    
    protected function setUp(): void
    {
        $this->normalizer = new EmailNormalizer(new NullLogger());
    }
    
    /**
     * Test 1: Lowercase conversion
     * 
     * @dataProvider lowercaseProvider
     */
    public function testLowercaseConversion(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }
    
    public static function lowercaseProvider(): array
    {
        return [
            'all uppercase' => ['JOHN@EXAMPLE.COM', 'john@example.com'],
            'mixed case' => ['JoHn@ExAmPlE.CoM', 'john@example.com'],
            'already lowercase' => ['john@example.com', 'john@example.com'],
        ];
    }
    
    /**
     * Test 2: Whitespace trimming
     * 
     * @dataProvider whitespaceProvider
     */
    public function testWhitespaceTrimming(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }
    
    public static function whitespaceProvider(): array
    {
        return [
            'leading space' => [' john@example.com', 'john@example.com'],
            'trailing space' => ['john@example.com ', 'john@example.com'],
            'both sides' => ['  john@example.com  ', 'john@example.com'],
            'tabs' => ["\tjohn@example.com\t", 'john@example.com'],
        ];
    }
    
    /**
     * Test 3: Plus addressing removal (RFC 5233)
     * 
     * @dataProvider plusAddressingProvider
     */
    public function testPlusAddressingRemoval(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }
    
    public static function plusAddressingProvider(): array
    {
        return [
            'simple plus' => ['john+tag@example.com', 'john@example.com'],
            'multiple plus' => ['john+tag+more@example.com', 'john@example.com'],
            'no plus' => ['john@example.com', 'john@example.com'],
        ];
    }
    
    /**
     * Test 4: Gmail dot removal
     * 
     * @dataProvider gmailDotProvider
     */
    public function testGmailDotRemoval(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }
    
    public static function gmailDotProvider(): array
    {
        return [
            'single dot' => ['john.doe@gmail.com', 'johndoe@gmail.com'],
            'multiple dots' => ['j.o.h.n@gmail.com', 'john@gmail.com'],
            'no dots' => ['john@gmail.com', 'john@gmail.com'],
            'non-gmail not affected' => ['john.doe@example.com', 'john.doe@example.com'],
        ];
    }
    
    /**
     * Test 5: Googlemail to Gmail normalization
     */
    public function testGooglemailNormalization(): void
    {
        $this->assertSame(
            'john@gmail.com',
            $this->normalizer->normalize('john@googlemail.com')
        );
        
        $this->assertSame(
            'johndoe@gmail.com',
            $this->normalizer->normalize('john.doe@googlemail.com')
        );
    }
    
    /**
     * Test 6: Combined normalization rules
     * 
     * @dataProvider combinedNormalizationProvider
     */
    public function testCombinedNormalization(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }
    
    public static function combinedNormalizationProvider(): array
    {
        return [
            'all rules' => [' John.Doe+Spam@Gmail.Com ', 'johndoe@gmail.com'],
            'googlemail with plus' => ['john+tag@googlemail.com', 'john@gmail.com'],
            'uppercase plus dots' => ['JOHN.DOE+TAG@GMAIL.COM', 'johndoe@gmail.com'],
        ];
    }
    
    /**
     * Test 7: Edge cases
     */
    public function testEdgeCases(): void
    {
        // Empty string
        $this->assertSame('', $this->normalizer->normalize(''));
        
        // Invalid email (no @) - normalization still applies
        $this->assertSame('notanemail', $this->normalizer->normalize('NotAnEmail'));
    }
}
