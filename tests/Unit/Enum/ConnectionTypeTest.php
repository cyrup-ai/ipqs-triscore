<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Enum;

use Kodegen\Ipqs\Enum\ConnectionType;
use PHPUnit\Framework\TestCase;

class ConnectionTypeTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertInstanceOf(ConnectionType::class, ConnectionType::RESIDENTIAL);
        $this->assertInstanceOf(ConnectionType::class, ConnectionType::CORPORATE);
        $this->assertInstanceOf(ConnectionType::class, ConnectionType::EDUCATION);
        $this->assertInstanceOf(ConnectionType::class, ConnectionType::MOBILE);
        $this->assertInstanceOf(ConnectionType::class, ConnectionType::DATA_CENTER);
    }
    
    public function testEnumBackingValues(): void
    {
        $this->assertSame('Residential', ConnectionType::RESIDENTIAL->value);
        $this->assertSame('Corporate', ConnectionType::CORPORATE->value);
        $this->assertSame('Education', ConnectionType::EDUCATION->value);
        $this->assertSame('Mobile', ConnectionType::MOBILE->value);
        $this->assertSame('Data Center', ConnectionType::DATA_CENTER->value);
    }
    
    public function testSerializationToJson(): void
    {
        $this->assertSame('"Residential"', json_encode(ConnectionType::RESIDENTIAL));
        $this->assertSame('"Corporate"', json_encode(ConnectionType::CORPORATE));
        $this->assertSame('"Data Center"', json_encode(ConnectionType::DATA_CENTER));
    }
    
    /**
     * Test fromString with exact API values
     */
    public function testFromStringWithExactValues(): void
    {
        $this->assertSame(ConnectionType::RESIDENTIAL, ConnectionType::fromString('Residential'));
        $this->assertSame(ConnectionType::CORPORATE, ConnectionType::fromString('Corporate'));
        $this->assertSame(ConnectionType::EDUCATION, ConnectionType::fromString('Education'));
        $this->assertSame(ConnectionType::MOBILE, ConnectionType::fromString('Mobile'));
        $this->assertSame(ConnectionType::DATA_CENTER, ConnectionType::fromString('Data Center'));
    }
    
    /**
     * Test fromString with case variations
     */
    public function testFromStringWithCaseVariations(): void
    {
        $this->assertSame(ConnectionType::RESIDENTIAL, ConnectionType::fromString('RESIDENTIAL'));
        $this->assertSame(ConnectionType::RESIDENTIAL, ConnectionType::fromString('residential'));
        $this->assertSame(ConnectionType::DATA_CENTER, ConnectionType::fromString('DATA_CENTER'));
        $this->assertSame(ConnectionType::DATA_CENTER, ConnectionType::fromString('data center'));
        $this->assertSame(ConnectionType::DATA_CENTER, ConnectionType::fromString('DATACENTER'));
    }
    
    /**
     * Test fromString with null and empty values
     */
    public function testFromStringWithNullAndEmpty(): void
    {
        $this->assertNull(ConnectionType::fromString(null));
        $this->assertNull(ConnectionType::fromString(''));
        $this->assertNull(ConnectionType::fromString('   '));  // Whitespace only
    }
    
    /**
     * Test fromString with invalid values
     */
    public function testFromStringWithInvalidValues(): void
    {
        $this->assertNull(ConnectionType::fromString('Unknown'));
        $this->assertNull(ConnectionType::fromString('Satellite'));
        $this->assertNull(ConnectionType::fromString('Premium required.'));
    }
}
