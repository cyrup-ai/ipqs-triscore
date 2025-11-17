<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Exception;

/**
 * Thrown when an email address fails format validation
 *
 * Examples of invalid emails:
 * - "not-an-email" (no @ symbol)
 * - "user@" (missing domain)
 * - "@example.com" (missing username)
 * - "" (empty string)
 * - "user @example.com" (whitespace in username)
 */
class InvalidEmailException extends ValidationException
{
}
