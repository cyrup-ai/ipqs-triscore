<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Exception;

use Kodegen\Ipqs\Exception\InvalidIpAddressException;
use Kodegen\Ipqs\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class InvalidIpAddressExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $message = 'Invalid IP address format';
        $exception = new InvalidIpAddressException($message);
        
        $this->assertSame($message, $exception->getMessage());
    }
    
    public function testExceptionCode(): void
    {
        $exception = new InvalidIpAddressException('Test message', 789);
        
        $this->assertSame(789, $exception->getCode());
    }
    
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidIpAddressException('Test');
        
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
