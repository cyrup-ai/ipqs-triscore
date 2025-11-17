<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Exception;

/**
 * Thrown when an IP address fails format validation
 *
 * Examples of invalid IP addresses:
 * - "999.999.999.999" (octets out of range)
 * - "192.168.1" (incomplete)
 * - "" (empty string)
 * - "not-an-ip" (contains non-numeric characters)
 * - "192.168.1.1.1" (too many octets)
 */
class InvalidIpAddressException extends ValidationException
{
}
