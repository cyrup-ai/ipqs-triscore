<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Cache;

/**
 * Cache abstraction for IPQS fraud scores
 *
 * Inspired by PSR-16 (Simple Cache) but simplified for IPQS use case
 *
 * Implementations might use:
 * - Redis (recommended for production)
 * - Database table (e.g., MySQL, PostgreSQL)
 * - File cache (development only)
 * - APCu/OPcache (single-server deployments)
 *
 * Cache TTL recommendations:
 * - Email scores: 90 days (7,776,000 seconds)
 * - Phone scores: 90 days (7,776,000 seconds)
 * - IP scores: 3 days (259,200 seconds)
 */
interface CacheInterface
{
    /**
     * Get cached value by key
     *
     * @param string $key Cache key (e.g., "ipqs:email:user@example.com")
     * @return mixed|null Cached value (typically array or object) or null if not found/expired
     */
    public function get(string $key): mixed;

    /**
     * Set cached value with TTL in seconds
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache (must be serializable)
     * @param int $ttl Time to live in seconds
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, int $ttl): bool;

    /**
     * Delete cached value by key
     *
     * @param string $key Cache key
     * @return bool True if deleted, false if key didn't exist or delete failed
     */
    public function delete(string $key): bool;
}
