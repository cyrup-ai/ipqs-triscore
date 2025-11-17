<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Util;

/**
 * Utility to sanitize URLs containing API keys before logging
 *
 * IPQS API requires keys in URL paths, so we must sanitize before logging
 * to prevent API key exposure in application logs.
 *
 * @see https://www.ipqualityscore.com/documentation/overview
 */
class UrlSanitizer
{
    /**
     * Redaction placeholder for masked API keys
     */
    private const REDACTED = '***REDACTED***';

    /**
     * Sanitize IPQS API URLs by masking the API key
     *
     * Replaces the API key segment with ***REDACTED*** in IPQS API URLs.
     * The pattern matches the standard IPQS URL structure.
     *
     * @param string $url Full URL potentially containing API key
     * @return string Sanitized URL with API key masked
     */
    public static function sanitize(string $url): string
    {
        // Pattern: /api/json/{endpoint}/{API_KEY}/{identifier}
        // Match the API key segment after endpoint and before the next segment
        $result = preg_replace(
            pattern: '#(/api/json/(?:ip|email|phone)/)([^/]+)(/.+)#',
            replacement: '$1' . self::REDACTED . '$3',
            subject: $url
        );

        // Explicit PCRE error detection (null return indicates error)
        if ($result === null) {
            // Log the error with full PCRE diagnostics
            error_log(sprintf(
                'WARNING: UrlSanitizer::sanitize failed - PCRE error: %s (%d)',
                preg_last_error_msg(),
                preg_last_error()
            ));

            // Return visibly marked failure - DO NOT return unsanitized URL
            return '[SANITIZATION_FAILED]' . $url;
        }

        return $result;
    }

    /**
     * Sanitize URL for logging context
     *
     * Returns an array with the sanitized URL, suitable for passing
     * as context to PSR-3 logger methods.
     *
     * @param string $url Full URL
     * @return array{url: string} Array suitable for logger context
     */
    public static function forLogging(string $url): array
    {
        return ['url' => self::sanitize($url)];
    }

    /**
     * Check if a URL appears to be an IPQS API URL
     *
     * Useful for conditional sanitization or validation.
     *
     * @param string $url URL to check
     * @return bool True if URL matches IPQS API pattern
     */
    public static function isIpqsApiUrl(string $url): bool
    {
        return (bool) preg_match('#/api/json/(?:ip|email|phone)/#', $url);
    }
}
