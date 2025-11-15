<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Service;

use Kodegen\Ipqs\Client\PhoneClient;
use Kodegen\Ipqs\Model\PhoneQualityScore;
use Kodegen\Ipqs\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Phone Quality Scoring Service with 90-day caching
 *
 * Matches PhoneQualityScoreService.kt from Kotlin implementation
 * @see analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/iqs/phone/PhoneQualityScoreService.kt
 */
class PhoneQualityScoreService
{
    private const CACHE_DAYS = 90;
    private const CACHE_TTL_SECONDS = self::CACHE_DAYS * 86400; // 7,776,000 seconds

    public function __construct(
        private PhoneClient $client,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * Score a phone number with caching (90-day TTL)
     *
     * Matches PhoneQualityScoreService.scorePhoneNumber() in Kotlin
     *
     * @param string $phoneNumber Phone number to score (E.164 format recommended)
     * @param string|null $country Optional country code (defaults to client's config default)
     * @return PhoneQualityScore|null Score object or null on error
     */
    public function score(string $phoneNumber, ?string $country = null): ?PhoneQualityScore
    {
        // Cache key includes only phone number (not country) to match Kotlin
        // Kotlin: phoneQualityScoreRepository.findByPhoneNumber(phoneNumber)
        $cacheKey = "ipqs:phone:{$phoneNumber}";

        // Check cache (matching Kotlin's 90-day window)
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            if ($cached instanceof PhoneQualityScore) {
                $this->logger->debug('PhoneQualityScoreService::score cache hit', [
                    'phoneNumber' => $phoneNumber,
                ]);
                return $cached;
            } else {
                $this->logger->warning('PhoneQualityScoreService::score cache corruption, deleting', [
                    'phoneNumber' => $phoneNumber,
                    'cachedType' => get_debug_type($cached),
                ]);
                $this->cache->delete($cacheKey);
            }
        }

        // Cache miss - call API
        $this->logger->debug('PhoneQualityScoreService::score cache miss, calling API', [
            'phoneNumber' => $phoneNumber,
            'country' => $country,
        ]);

        $response = $this->client->scoreRaw($phoneNumber, $country);

        // Handle API errors (matching Kotlin lines 23-26)
        if ($response === null) {
            $this->logger->error('PhoneQualityScoreService::score API response is null', [
                'phoneNumber' => $phoneNumber,
            ]);
            return null;
        }

        // Validate API response
        if (!isset($response['success']) || $response['success'] !== true) {
            $this->logger->error('PhoneQualityScoreService::score API returned success=false', [
                'phoneNumber' => $phoneNumber,
                'message' => $response['message'] ?? 'unknown',
                'errors' => $response['errors'] ?? [],
            ]);
            return null;
        }

        // Map response to model (matching Kotlin line 28)
        try {
            $score = PhoneQualityScore::fromApiResponse($phoneNumber, $response);
        } catch (\Throwable $e) {
            $this->logger->error('PhoneQualityScoreService::score failed to map API response', [
                'phoneNumber' => $phoneNumber,
                'error' => $e->getMessage(),
                'response' => $response,
            ]);
            return null;
        }

        // Store in cache with 90-day TTL
        try {
            $this->cache->set($cacheKey, $score, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            $this->logger->warning('PhoneQualityScoreService::score failed to cache result', [
                'phoneNumber' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return $score;
    }
}
