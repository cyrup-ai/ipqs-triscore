<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Exception;

use Kodegen\Ipqs\Exception\InvalidPhoneNumberException;
use Kodegen\Ipqs\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class InvalidPhoneNumberExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $message = 'Invalid phone number format';
        $exception = new InvalidPhoneNumberException($message);
        
        $this->assertSame($message, $exception->getMessage());
    }
    
    public function testExceptionCode(): void
    {
        $exception = new InvalidPhoneNumberException('Test message', 101);
        
        $this->assertSame(101, $exception->getCode());
    }
    
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidPhoneNumberException('Test');
        
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
