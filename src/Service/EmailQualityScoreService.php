<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Service;

use Kodegen\Ipqs\Client\EmailClient;
use Kodegen\Ipqs\Model\EmailQualityScore;
use Kodegen\Ipqs\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Email Quality Scoring Service with 90-day caching
 *
 * Matches EmailQualityScoreService.kt from Kotlin implementation
 * @see analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/iqs/email/EmailQualityScoreService.kt
 */
class EmailQualityScoreService
{
    private const CACHE_DAYS = 90;
    private const CACHE_TTL_SECONDS = self::CACHE_DAYS * 86400; // 7,776,000 seconds

    public function __construct(
        private EmailClient $client,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * Score an email with caching (90-day TTL)
     *
     * Matches EmailQualityScoreService.scoreEmail() in Kotlin
     *
     * @param string $email Email address to score
     * @return EmailQualityScore|null Score object or null on error
     */
    public function score(string $email): ?EmailQualityScore
    {
        // Step 1: Normalize email (matching Kotlin's standardizeEmail())
        $normalizedEmail = strtolower(trim($email));
        $cacheKey = "ipqs:email:{$normalizedEmail}";

        // Step 2: Check cache (matching Kotlin's 90-day window)
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            // Verify it's a valid EmailQualityScore object
            if ($cached instanceof EmailQualityScore) {
                $this->logger->debug('EmailQualityScoreService::score cache hit', [
                    'email' => $normalizedEmail,
                ]);
                return $cached;
            } else {
                // Cache corruption - clear and continue to API call
                $this->logger->warning('EmailQualityScoreService::score cache corruption, deleting', [
                    'email' => $normalizedEmail,
                    'cachedType' => get_debug_type($cached),
                ]);
                $this->cache->delete($cacheKey);
            }
        }

        // Step 3: Cache miss - call API via client
        $this->logger->debug('EmailQualityScoreService::score cache miss, calling API', [
            'email' => $normalizedEmail,
        ]);

        $response = $this->client->scoreRaw($normalizedEmail);

        // Step 4: Handle API errors (matching Kotlin lines 26-29)
        if ($response === null) {
            $this->logger->error('EmailQualityScoreService::score API response is null', [
                'email' => $normalizedEmail,
            ]);
            return null;
        }

        // Step 5: Validate API response structure
        if (!isset($response['success']) || $response['success'] !== true) {
            $this->logger->error('EmailQualityScoreService::score API returned success=false', [
                'email' => $normalizedEmail,
                'message' => $response['message'] ?? 'unknown',
                'errors' => $response['errors'] ?? [],
            ]);
            return null;
        }

        // Step 6: Map response to model (matching Kotlin line 31)
        try {
            $score = EmailQualityScore::fromApiResponse($normalizedEmail, $response);
        } catch (\Throwable $e) {
            $this->logger->error('EmailQualityScoreService::score failed to map API response', [
                'email' => $normalizedEmail,
                'error' => $e->getMessage(),
                'response' => $response,
            ]);
            return null;
        }

        // Step 7: Store in cache with 90-day TTL (matching Kotlin's upsert behavior)
        try {
            $this->cache->set($cacheKey, $score, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            // Log but don't fail - we have the score
            $this->logger->warning('EmailQualityScoreService::score failed to cache result', [
                'email' => $normalizedEmail,
                'error' => $e->getMessage(),
            ]);
        }

        return $score;
    }
}
