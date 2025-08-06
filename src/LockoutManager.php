<?php

namespace ExponentialLockout;

use Illuminate\Cache\CacheManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * LockoutManager - Core class for managing exponential lockouts
 * 
 * This class handles the core logic for tracking failed attempts,
 * calculating lockout durations, and managing lockout state.
 */
class LockoutManager
{
    /**
     * Cache manager instance
     */
    protected CacheManager $cache;

    /**
     * Package configuration
     */
    protected array $config;

    /**
     * Create a new LockoutManager instance
     */
    public function __construct(CacheManager $cache, array $config)
    {
        $this->cache = $cache;
        $this->config = $config;
    }

    /**
     * Record a failed attempt for the given context and key
     * 
     * @param string $context The lockout context (e.g., 'login', 'otp')
     * @param string $key The unique identifier for the user/session
     * @return int The current attempt count after recording this failure
     */
    public function recordFailure(string $context, string $key): int
    {
        $this->validateContext($context);
        
        $cacheKey = $this->buildCacheKey($context, $key);
        $store = $this->getCacheStore();
        
        // Get current lockout data
        $lockoutData = $store->get($cacheKey, [
            'attempts' => 0,
            'locked_until' => null,
            'last_attempt' => null,
        ]);

        // Increment attempt count
        $lockoutData['attempts']++;
        $lockoutData['last_attempt'] = Carbon::now()->timestamp;

        // Calculate lockout duration
        $delays = $this->getDelaysForContext($context);
        $delayIndex = min($lockoutData['attempts'] - 1, count($delays) - 1);
        $lockoutDuration = $delays[$delayIndex] ?? end($delays);

        // Set lockout expiration
        $lockoutData['locked_until'] = Carbon::now()->addSeconds($lockoutDuration)->timestamp;

        // Store the updated data with appropriate TTL
        $ttl = $lockoutDuration + 3600; // Add 1 hour buffer
        $store->put($cacheKey, $lockoutData, $ttl);

        return $lockoutData['attempts'];
    }

    /**
     * Check if the given context and key is currently locked out
     * 
     * @param string $context The lockout context
     * @param string $key The unique identifier
     * @return bool True if locked out, false otherwise
     */
    public function isLockedOut(string $context, string $key): bool
    {
        $this->validateContext($context);
        
        $cacheKey = $this->buildCacheKey($context, $key);
        $lockoutData = $this->getCacheStore()->get($cacheKey);

        if (!$lockoutData || !isset($lockoutData['locked_until'])) {
            return false;
        }

        $lockedUntil = Carbon::createFromTimestamp($lockoutData['locked_until']);
        
        // If lockout has expired, clean up the cache entry
        if ($lockedUntil->isPast()) {
            $this->getCacheStore()->forget($cacheKey);
            return false;
        }

        return true;
    }

    /**
     * Get the remaining lockout time in seconds
     * 
     * @param string $context The lockout context
     * @param string $key The unique identifier
     * @return int Remaining seconds (0 if not locked out)
     */
    public function getRemainingTime(string $context, string $key): int
    {
        if (!$this->isLockedOut($context, $key)) {
            return 0;
        }

        $cacheKey = $this->buildCacheKey($context, $key);
        $lockoutData = $this->getCacheStore()->get($cacheKey);
        
        if (!$lockoutData || !isset($lockoutData['locked_until'])) {
            return 0;
        }

        $lockedUntil = Carbon::createFromTimestamp($lockoutData['locked_until']);
        return max(0, $lockedUntil->diffInSeconds(Carbon::now()));
    }

    /**
     * Clear lockout for the given context and key
     * 
     * @param string $context The lockout context
     * @param string $key The unique identifier
     * @return bool True if cleared successfully
     */
    public function clear(string $context, string $key): bool
    {
        $this->validateContext($context);
        
        $cacheKey = $this->buildCacheKey($context, $key);
        return $this->getCacheStore()->forget($cacheKey);
    }

    /**
     * Clear all lockouts for a given context
     * 
     * @param string $context The lockout context
     * @return bool True if cleared successfully
     */
    public function clearContext(string $context): bool
    {
        $this->validateContext($context);
        
        $prefix = $this->config['cache']['prefix'];
        $pattern = $prefix . ':' . $context . ':*';
        
        // Note: This is a basic implementation. For Redis, you might want to use SCAN
        // For other cache stores, this might need to be implemented differently
        $store = $this->getCacheStore();
        
        if (method_exists($store, 'flush')) {
            // For stores that support flushing by pattern
            return $store->flush();
        }
        
        return true; // Graceful fallback
    }

    /**
     * Get current attempt count for context and key
     * 
     * @param string $context The lockout context
     * @param string $key The unique identifier
     * @return int Current attempt count
     */
    public function getAttemptCount(string $context, string $key): int
    {
        $this->validateContext($context);
        
        $cacheKey = $this->buildCacheKey($context, $key);
        $lockoutData = $this->getCacheStore()->get($cacheKey);
        
        return $lockoutData['attempts'] ?? 0;
    }

    /**
     * Extract key from request based on context configuration
     * 
     * @param string $context The lockout context
     * @param Request $request The HTTP request
     * @return string The extracted key
     */
    public function extractKeyFromRequest(string $context, Request $request): string
    {
        $contextConfig = $this->getContextConfig($context);
        $keyExtractor = $contextConfig['key'];

        // If it's a string, look for a predefined extractor
        if (is_string($keyExtractor)) {
            $extractors = $this->config['key_extractors'] ?? [];
            
            if (isset($extractors[$keyExtractor])) {
                $extractor = $extractors[$keyExtractor];
                if (is_callable($extractor)) {
                    return $extractor($request);
                }
            }
            
            // Fallback to simple input extraction
            return $request->input($keyExtractor) ?: $request->ip();
        }

        // If it's a callable, use it directly
        if (is_callable($keyExtractor)) {
            return $keyExtractor($request);
        }

        // Final fallback to IP address
        return $request->ip();
    }

    /**
     * Get the lockout information for a context and key
     * 
     * @param string $context The lockout context
     * @param string $key The unique identifier
     * @return array Lockout information
     */
    public function getLockoutInfo(string $context, string $key): array
    {
        $this->validateContext($context);
        
        $cacheKey = $this->buildCacheKey($context, $key);
        $lockoutData = $this->getCacheStore()->get($cacheKey, [
            'attempts' => 0,
            'locked_until' => null,
            'last_attempt' => null,
        ]);

        return [
            'context' => $context,
            'key' => $key,
            'attempts' => $lockoutData['attempts'],
            'is_locked_out' => $this->isLockedOut($context, $key),
            'remaining_time' => $this->getRemainingTime($context, $key),
            'locked_until' => $lockoutData['locked_until'] ? 
                Carbon::createFromTimestamp($lockoutData['locked_until']) : null,
            'last_attempt' => $lockoutData['last_attempt'] ? 
                Carbon::createFromTimestamp($lockoutData['last_attempt']) : null,
        ];
    }

    /**
     * Build cache key for the given context and key
     */
    protected function buildCacheKey(string $context, string $key): string
    {
        $prefix = $this->config['cache']['prefix'];
        return $prefix . ':' . $context . ':' . hash('sha256', $key);
    }

    /**
     * Get the cache store instance
     */
    protected function getCacheStore()
    {
        $store = $this->config['cache']['store'];
        return $store ? $this->cache->store($store) : $this->cache->store();
    }

    /**
     * Get delay sequence for the given context
     */
    protected function getDelaysForContext(string $context): array
    {
        $contextConfig = $this->getContextConfig($context);
        return $contextConfig['delays'] ?? $this->config['default_delays'];
    }

    /**
     * Get configuration for the given context
     */
    protected function getContextConfig(string $context): array
    {
        $contexts = $this->config['contexts'] ?? [];
        return $contexts[$context] ?? [];
    }

    /**
     * Validate that the context exists and is enabled
     */
    protected function validateContext(string $context): void
    {
        $contextConfig = $this->getContextConfig($context);
        
        if (empty($contextConfig)) {
            throw new InvalidArgumentException("Lockout context '{$context}' is not configured.");
        }
        
        if (isset($contextConfig['enabled']) && !$contextConfig['enabled']) {
            throw new InvalidArgumentException("Lockout context '{$context}' is disabled.");
        }
    }
}