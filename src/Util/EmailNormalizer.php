<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Util;

use Psr\Log\LoggerInterface;

/**
 * Centralized Email Normalization Service
 *
 * Provides a single source of truth for email normalization across the entire application.
 * This ensures that cache keys (in EmailQualityScoreService) match the actual email addresses
 * sent to the IPQS API (in EmailClient).
 *
 * Normalization Rules (applied in order):
 * 1. Lowercase entire address (RFC 5321 compatibility)
 * 2. Trim whitespace (leading/trailing)
 * 3. Remove subaddresses (RFC 5233 - plus addressing: user+tag@domain → user@domain)
 * 4. Gmail dot removal (Gmail-specific: john.doe@gmail.com → johndoe@gmail.com)
 * 5. Googlemail normalization (@googlemail.com → @gmail.com)
 *
 * @see ../Service/EmailQualityScoreService.php Uses for cache keys
 * @see ../Client/EmailClient.php Uses for API calls
 */
class EmailNormalizer
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    /**
     * Normalize an email address to its canonical form
     *
     * @param string $email Email address to normalize
     * @return string Normalized email address
     */
    public function normalize(string $email): string
    {
        // Step 1: Lowercase and trim
        $email = strtolower(trim($email));

        // Handle empty string edge case
        if ($email === '') {
            return '';
        }

        // Step 2: Remove '+' aliases (RFC 5233 subaddressing)
        // Pattern: user+anything@domain.com → user@domain.com
        $result = preg_replace('/^(.+?)(\+.+)@(.+)$/', '$1@$3', $email);

        if ($result === null) {
            // PCRE error occurred - log for production monitoring
            $this->logger->warning('EmailNormalizer::normalize preg_replace failed', [
                'email' => $email,
                'pattern' => '/^(.+)(\+.+)@(.+)$/',
                'error_code' => preg_last_error(),
                'error_msg' => preg_last_error_msg(),
            ]);
            // Fall back to original email (same behavior as ??)
        } else {
            $email = $result;
        }

        // Step 3: Gmail-specific normalization
        // Gmail ignores dots in the username part
        if (str_contains($email, '@gmail.com') || str_contains($email, '@googlemail.com')) {
            $matchResult = preg_match('/^(.+)@(.+)$/', $email, $matches);

            if ($matchResult === 1) {
                // Regex matched successfully
                $username = str_replace('.', '', $matches[1]);
                $domain = $matches[2];

                // Normalize googlemail.com to gmail.com
                if ($domain === 'googlemail.com') {
                    $domain = 'gmail.com';
                }

                $email = $username . '@' . $domain;
            } elseif ($matchResult === false) {
                // PCRE error occurred
                $this->logger->warning('EmailNormalizer::normalize preg_match failed', [
                    'email' => $email,
                    'pattern' => '/^(.+)@(.+)$/',
                    'error_code' => preg_last_error(),
                    'error_msg' => preg_last_error_msg(),
                ]);
            }
            // If $matchResult === 0 (no match), do nothing
        }

        return $email;
    }
}
