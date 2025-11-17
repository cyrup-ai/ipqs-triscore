<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Enum;

use Kodegen\Ipqs\Enum\AbuseVelocity;
use PHPUnit\Framework\TestCase;

class AbuseVelocityTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertInstanceOf(AbuseVelocity::class, AbuseVelocity::NONE);
        $this->assertInstanceOf(AbuseVelocity::class, AbuseVelocity::LOW);
        $this->assertInstanceOf(AbuseVelocity::class, AbuseVelocity::MEDIUM);
        $this->assertInstanceOf(AbuseVelocity::class, AbuseVelocity::HIGH);
    }
    
    public function testEnumBackingValues(): void
    {
        $this->assertSame('none', AbuseVelocity::NONE->value);
        $this->assertSame('low', AbuseVelocity::LOW->value);
        $this->assertSame('medium', AbuseVelocity::MEDIUM->value);
        $this->assertSame('high', AbuseVelocity::HIGH->value);
    }
    
    public function testSerializationToJson(): void
    {
        $this->assertSame('"none"', json_encode(AbuseVelocity::NONE));
        $this->assertSame('"low"', json_encode(AbuseVelocity::LOW));
        $this->assertSame('"medium"', json_encode(AbuseVelocity::MEDIUM));
        $this->assertSame('"high"', json_encode(AbuseVelocity::HIGH));
    }
    
    /**
     * Test fromString with exact API values (lowercase)
     */
    public function testFromStringWithExactValues(): void
    {
        $this->assertSame(AbuseVelocity::NONE, AbuseVelocity::fromString('none'));
        $this->assertSame(AbuseVelocity::LOW, AbuseVelocity::fromString('low'));
        $this->assertSame(AbuseVelocity::MEDIUM, AbuseVelocity::fromString('medium'));
        $this->assertSame(AbuseVelocity::HIGH, AbuseVelocity::fromString('high'));
    }
    
    /**
     * Test fromString normalizes to lowercase
     */
    public function testFromStringNormalizesToLowercase(): void
    {
        $this->assertSame(AbuseVelocity::NONE, AbuseVelocity::fromString('NONE'));
        $this->assertSame(AbuseVelocity::LOW, AbuseVelocity::fromString('LOW'));
        $this->assertSame(AbuseVelocity::MEDIUM, AbuseVelocity::fromString('MEDIUM'));
        $this->assertSame(AbuseVelocity::HIGH, AbuseVelocity::fromString('HIGH'));
        $this->assertSame(AbuseVelocity::MEDIUM, AbuseVelocity::fromString('MeDiUm'));
    }
    
    /**
     * Test fromString with null and empty values
     */
    public function testFromStringWithNullAndEmpty(): void
    {
        $this->assertNull(AbuseVelocity::fromString(null));
        $this->assertNull(AbuseVelocity::fromString(''));
        $this->assertNull(AbuseVelocity::fromString('   '));  // Whitespace only
    }
    
    /**
     * Test fromString with invalid values
     */
    public function testFromStringWithInvalidValues(): void
    {
        $this->assertNull(AbuseVelocity::fromString('invalid'));
        $this->assertNull(AbuseVelocity::fromString('critical'));
        $this->assertNull(AbuseVelocity::fromString('unknown'));
    }
}
