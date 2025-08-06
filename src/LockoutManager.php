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

        // Get the minimum attempts before lockout (default: 3)
        $contextConfig = $this->getContextConfig($context);
        $minAttempts = $contextConfig['min_attempts'] ?? 3;

        // Only apply lockout if we've reached the minimum attempt threshold
        if ($lockoutData['attempts'] >= $minAttempts) {
            // Calculate lockout duration based on attempts beyond the threshold
            $delays = $this->getDelaysForContext($context);
            $lockoutAttemptIndex = $lockoutData['attempts'] - $minAttempts;
            $delayIndex = min($lockoutAttemptIndex, count($delays) - 1);
            $lockoutDuration = $delays[$delayIndex] ?? end($delays);

            // Set lockout expiration
            $lockoutData['locked_until'] = Carbon::now()->addSeconds($lockoutDuration)->timestamp;
        } else {
            // Not enough attempts yet - no lockout
            $lockoutData['locked_until'] = null;
        }

        // Store the updated data with appropriate TTL
        $ttl = isset($lockoutDuration) ? $lockoutDuration + 3600 : 3600; // Add 1 hour buffer
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

        if (!$lockoutData || !isset($lockoutData['locked_until']) || $lockoutData['locked_until'] === null) {
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
        $this->validateContext($context);
        
        $cacheKey = $this->buildCacheKey($context, $key);
        $lockoutData = $this->getCacheStore()->get($cacheKey);
        
        if (!$lockoutData || !isset($lockoutData['locked_until']) || $lockoutData['locked_until'] === null) {
            return 0;
        }

        $lockedUntil = Carbon::createFromTimestamp($lockoutData['locked_until']);
        $now = Carbon::now();
        
        // If lockout has expired, return 0
        if ($lockedUntil->isPast()) {
            return 0;
        }
        
        // Calculate remaining seconds - use ceiling to ensure we don't return 0 when there's still time left
        $remaining = $lockedUntil->diffInSeconds($now, false);
        return max(1, (int) ceil($remaining)); // Always return at least 1 second if locked
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
     * This method extracts the unique identifier for lockout tracking from the HTTP request.
     * The extraction method is defined in the context configuration.
     * 
     * Supported key extractors:
     * - 'email': Extracts from request input field 'email'
     * - 'phone': Extracts from request input field 'phone' 
     * - 'username': Extracts from request input field 'username'
     * - 'ip': Uses client IP address
     * - Custom callable: Uses a custom function for extraction
     * 
     * @param string $context The lockout context (e.g., 'login', 'otp')
     * @param Request $request The HTTP request
     * @return string The extracted key for lockout tracking
     * @throws InvalidArgumentException If key cannot be extracted and no fallback available
     */
    public function extractKeyFromRequest(string $context, Request $request): string
    {
        $contextConfig = $this->getContextConfig($context);
        $keyExtractor = $contextConfig['key'] ?? 'ip';

        // If it's a string, look for a predefined extractor first
        if (is_string($keyExtractor)) {
            $extractors = $this->config['key_extractors'] ?? [];
            
            // Check for custom extractor
            if (isset($extractors[$keyExtractor]) && is_callable($extractors[$keyExtractor])) {
                $extractedKey = $extractors[$keyExtractor]($request);
                if ($extractedKey) {
                    return $extractedKey;
                }
            }
            
            // Handle built-in extractors
            $extractedKey = $this->extractBuiltInKey($keyExtractor, $request);
            if ($extractedKey) {
                return $extractedKey;
            }

            // Log warning if key field is missing from request
            if ($keyExtractor !== 'ip' && !$request->has($keyExtractor)) {
                error_log("Warning: Exponential Lockout - Required field '{$keyExtractor}' missing from request for context '{$context}'. Falling back to IP address.");
            }
        }

        // If it's a callable, use it directly
        if (is_callable($keyExtractor)) {
            $extractedKey = $keyExtractor($request);
            if ($extractedKey) {
                return $extractedKey;
            }
        }

        // Final fallback to IP address
        $ipAddress = $request->ip();
        if (!$ipAddress) {
            throw new InvalidArgumentException("Unable to extract lockout key for context '{$context}' - no fallback IP available.");
        }

        return $ipAddress;
    }

    /**
     * Extract key using built-in extractors
     * 
     * @param string $extractor The extractor name
     * @param Request $request The HTTP request
     * @return string|null The extracted key or null if not found
     */
    protected function extractBuiltInKey(string $extractor, Request $request): ?string
    {
        switch ($extractor) {
            case 'email':
                return $request->input('email') ?: $request->input('username');
            
            case 'phone':
                return $request->input('phone') ?: $request->input('mobile') ?: $request->input('telephone');
            
            case 'username':
                return $request->input('username') ?: $request->input('email');
            
            case 'ip':
                return $request->ip();
            
            default:
                // For any other string, try to extract from request input
                return $request->input($extractor);
        }
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