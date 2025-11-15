<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Service;

use Kodegen\Ipqs\Client\IpClient;
use Kodegen\Ipqs\Model\IpQualityScore;
use Kodegen\Ipqs\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * IP Quality Scoring Service with 3-day caching
 *
 * NOTE: IP scores cached for only 3 DAYS (not 90) because IPs are reassigned frequently
 *
 * Matches IPQualityScoreService.kt from Kotlin implementation
 * @see analytics.kodegen.com/analytics/src/main/kotlin/com/kodegen/analytics/iqs/ip/IPQualityScoreService.kt
 */
class IpQualityScoreService
{
    private const CACHE_DAYS = 3; // ← 3 days (not 90!)
    private const CACHE_TTL_SECONDS = self::CACHE_DAYS * 86400; // 259,200 seconds

    public function __construct(
        private IpClient $client,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * Score an IP address with caching (3-day TTL)
     *
     * Matches IPQualityScoreService.scoreIPAddress() in Kotlin
     *
     * @param string $ipAddress IP address to score (IPv4 or IPv6)
     * @param string $userAgent User agent string (required by API)
     * @param array<string, mixed> $options Additional API parameters (e.g., strictness)
     * @return IpQualityScore|null Score object or null on error
     */
    public function score(string $ipAddress, string $userAgent, array $options = []): ?IpQualityScore
    {
        $cacheKey = "ipqs:ip:{$ipAddress}";

        // Check cache (matching Kotlin's 3-day window)
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            if ($cached instanceof IpQualityScore) {
                $this->logger->debug('IpQualityScoreService::score cache hit', [
                    'ipAddress' => $ipAddress,
                ]);
                return $cached;
            } else {
                $this->logger->warning('IpQualityScoreService::score cache corruption, deleting', [
                    'ipAddress' => $ipAddress,
                    'cachedType' => get_debug_type($cached),
                ]);
                $this->cache->delete($cacheKey);
            }
        }

        // Cache miss - call API
        $this->logger->debug('IpQualityScoreService::score cache miss, calling API', [
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'options' => $options,
        ]);

        // Build API parameters (matching IPQualityScoreRequest)
        $params = array_merge([
            'user_agent' => $userAgent,
        ], $options);

        $response = $this->client->scoreRaw($ipAddress, $params);

        // Handle API errors (matching Kotlin lines 32-35)
        if ($response === null) {
            $this->logger->error('IpQualityScoreService::score API response is null', [
                'ipAddress' => $ipAddress,
            ]);
            return null;
        }

        // Validate API response
        if (!isset($response['success']) || $response['success'] !== true) {
            $this->logger->error('IpQualityScoreService::score API returned success=false', [
                'ipAddress' => $ipAddress,
                'message' => $response['message'] ?? 'unknown',
            ]);
            return null;
        }

        // Map response to model
        try {
            $score = IpQualityScore::fromApiResponse($ipAddress, $response);
        } catch (\Throwable $e) {
            $this->logger->error('IpQualityScoreService::score failed to map API response', [
                'ipAddress' => $ipAddress,
                'error' => $e->getMessage(),
                'response' => $response,
            ]);
            return null;
        }

        // Store in cache with 3-day TTL (matching Kotlin's Duration.of(3, ChronoUnit.DAYS))
        try {
            $this->cache->set($cacheKey, $score, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            $this->logger->warning('IpQualityScoreService::score failed to cache result', [
                'ipAddress' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return $score;
    }
}
