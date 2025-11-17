<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Exception;

use Kodegen\Ipqs\Exception\InvalidEmailException;
use Kodegen\Ipqs\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class InvalidEmailExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $message = 'Invalid email address format';
        $exception = new InvalidEmailException($message);
        
        $this->assertSame($message, $exception->getMessage());
    }
    
    public function testExceptionCode(): void
    {
        $exception = new InvalidEmailException('Test message', 456);
        
        $this->assertSame(456, $exception->getCode());
    }
    
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidEmailException('Test');
        
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
