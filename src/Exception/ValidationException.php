<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Exception;

/**
 * Base exception for input validation failures
 *
 * Thrown when client method receives invalid input that would
 * result in a guaranteed API failure. This is a programmer error,
 * not a runtime error.
 *
 * Examples:
 * - IP address "999.999.999.999" (invalid format)
 * - Email "not-an-email" (invalid format)
 * - Phone number "" (empty string)
 * - Country code "USA" (should be "US")
 */
class ValidationException extends \InvalidArgumentException
{
}
