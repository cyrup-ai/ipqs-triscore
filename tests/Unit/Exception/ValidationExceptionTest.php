<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Unit\Exception;

use Kodegen\Ipqs\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $message = 'Invalid input provided';
        $exception = new ValidationException($message);
        
        $this->assertSame($message, $exception->getMessage());
    }
    
    public function testExceptionCode(): void
    {
        $exception = new ValidationException('Test message', 123);
        
        $this->assertSame(123, $exception->getCode());
    }
    
    public function testExceptionInheritance(): void
    {
        $exception = new ValidationException('Test');
        
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
