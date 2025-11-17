<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Enum;

use Kodegen\Ipqs\Enum\RiskCategory;
use PHPUnit\Framework\TestCase;

class RiskCategoryTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertInstanceOf(RiskCategory::class, RiskCategory::LOW);
        $this->assertInstanceOf(RiskCategory::class, RiskCategory::MEDIUM);
        $this->assertInstanceOf(RiskCategory::class, RiskCategory::HIGH);
    }
    
    public function testEnumBackingValues(): void
    {
        $this->assertSame('LOW', RiskCategory::LOW->value);
        $this->assertSame('MEDIUM', RiskCategory::MEDIUM->value);
        $this->assertSame('HIGH', RiskCategory::HIGH->value);
    }
    
    public function testSerializationToJson(): void
    {
        $this->assertSame('"LOW"', json_encode(RiskCategory::LOW));
        $this->assertSame('"MEDIUM"', json_encode(RiskCategory::MEDIUM));
        $this->assertSame('"HIGH"', json_encode(RiskCategory::HIGH));
    }
    
    public function testFromStringValue(): void
    {
        $this->assertSame(RiskCategory::LOW, RiskCategory::from('LOW'));
        $this->assertSame(RiskCategory::MEDIUM, RiskCategory::from('MEDIUM'));
        $this->assertSame(RiskCategory::HIGH, RiskCategory::from('HIGH'));
    }
    
    public function testFromInvalidStringThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        RiskCategory::from('INVALID');
    }
    
    public function testTryFromReturnsNullForInvalid(): void
    {
        $this->assertNull(RiskCategory::tryFrom('INVALID'));
        $this->assertNull(RiskCategory::tryFrom('low'));  // Case sensitive
    }
}
