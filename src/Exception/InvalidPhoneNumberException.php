<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Exception;

/**
 * Thrown when a phone number or country code fails format validation
 *
 * Examples of invalid phone numbers:
 * - "" (empty string)
 * - "abc123" (contains letters)
 * - "   " (only whitespace)
 *
 * Examples of invalid country codes:
 * - "USA" (should be "US" - must be 2 letters)
 * - "U" (too short)
 * - "1" (not a letter)
 * - "" (empty string)
 */
class InvalidPhoneNumberException extends ValidationException
{
}
