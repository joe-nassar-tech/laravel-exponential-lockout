# Developer Guide - Internal Architecture

This guide explains how the Laravel Exponential Lockout package works internally. It covers every file, class, method, and important code section for developers who want to understand, extend, or contribute to the library.

## 📋 Table of Contents

- [Package Overview](#-package-overview)
- [File Structure](#-file-structure)
- [Core Components](#-core-components)
- [Data Flow](#-data-flow)
- [Configuration System](#-configuration-system)
- [Cache Strategy](#-cache-strategy)
- [Security Considerations](#-security-considerations)
- [Extension Points](#-extension-points)

## 🎯 Package Overview

### Architecture Pattern
The package follows Laravel's standard patterns:
- **Service Provider Pattern** - Auto-registration and bootstrapping
- **Facade Pattern** - Easy access to functionality
- **Middleware Pattern** - HTTP request interception
- **Command Pattern** - Artisan CLI integration
- **Strategy Pattern** - Configurable response handling

### Key Design Principles
1. **Zero Configuration** - Works out of the box
2. **Highly Configurable** - Every aspect can be customized
3. **Laravel Native** - Uses Laravel's cache, config, and service container
4. **Stateless** - No database dependencies
5. **Performance First** - Minimal overhead, efficient caching

## 📁 File Structure

```
├── composer.json                          # Package definition
├── config/exponential-lockout.php         # Configuration file
└── src/
    ├── ExponentialLockoutServiceProvider.php  # Service provider
    ├── LockoutManager.php                     # Core logic
    ├── Facades/
    │   └── Lockout.php                        # Facade
    ├── Middleware/
    │   └── ExponentialLockout.php             # HTTP middleware
    └── Commands/
        └── ClearLockoutCommand.php            # Artisan command
```

## 🔧 Core Components

### 1. LockoutManager.php - Core Logic Engine

**Purpose:** The heart of the package. Handles all lockout logic, state management, and cache operations.

#### Class Structure
```php
class LockoutManager
{
    protected CacheManager $cache;    // Laravel's cache manager
    protected array $config;          // Package configuration
}
```

#### Constructor Analysis
```php
public function __construct(CacheManager $cache, array $config)
{
    $this->cache = $cache;
    $this->config = $config;
}
```
**What it does:**
- Injects Laravel's cache manager for storage operations
- Stores package configuration for runtime access
- No heavy initialization - keeps constructor lightweight

#### Key Methods Deep Dive

##### recordFailure() - The Heart of Lockout Logic
```php
public function recordFailure(string $context, string $key): int
{
    $this->validateContext($context);                    // Line 1: Security validation
    
    $cacheKey = $this->buildCacheKey($context, $key);    // Line 2: Generate cache key
    $store = $this->getCacheStore();                     // Line 3: Get cache instance
    
    // Get current lockout data or initialize
    $lockoutData = $store->get($cacheKey, [              // Line 4-8: Retrieve state
        'attempts' => 0,
        'locked_until' => null,
        'last_attempt' => null,
    ]);

    // Increment attempt count
    $lockoutData['attempts']++;                          // Line 9: Increment counter
    $lockoutData['last_attempt'] = Carbon::now()->timestamp;  // Line 10: Record timestamp

    // Calculate lockout duration using exponential delays
    $delays = $this->getDelaysForContext($context);      // Line 11: Get delay sequence
    $delayIndex = min($lockoutData['attempts'] - 1, count($delays) - 1);  // Line 12: Calculate index
    $lockoutDuration = $delays[$delayIndex] ?? end($delays);  // Line 13: Get delay time

    // Set lockout expiration
    $lockoutData['locked_until'] = Carbon::now()->addSeconds($lockoutDuration)->timestamp;  // Line 14

    // Store with TTL buffer
    $ttl = $lockoutDuration + 3600;                     // Line 15: Add 1-hour buffer
    $store->put($cacheKey, $lockoutData, $ttl);         // Line 16: Persist to cache

    return $lockoutData['attempts'];                     // Line 17: Return attempt count
}
```

**Line-by-Line Explanation:**
- **Line 1:** Validates context exists and is enabled - throws exception if invalid
- **Line 2:** Creates SHA256 hash of context+key for cache storage
- **Line 3:** Gets configured cache store (Redis, File, etc.)
- **Lines 4-8:** Retrieves existing lockout data or creates default structure
- **Line 9:** Increments the failure attempt counter
- **Line 10:** Records current timestamp for tracking
- **Line 11:** Gets delay sequence from context config or default
- **Line 12:** Calculates which delay to use (prevents array overflow)
- **Line 13:** Gets the actual delay time in seconds
- **Line 14:** Calculates when the lockout expires
- **Line 15:** Adds buffer time to prevent premature cache expiration
- **Line 16:** Stores the complete lockout state in cache
- **Line 17:** Returns current attempt count for user feedback

##### isLockedOut() - State Checking Logic
```php
public function isLockedOut(string $context, string $key): bool
{
    $this->validateContext($context);                    // Validate context
    
    $cacheKey = $this->buildCacheKey($context, $key);    // Generate cache key
    $lockoutData = $this->getCacheStore()->get($cacheKey);  // Retrieve data

    if (!$lockoutData || !isset($lockoutData['locked_until'])) {  // No lockout data
        return false;
    }

    $lockedUntil = Carbon::createFromTimestamp($lockoutData['locked_until']);  // Parse timestamp
    
    // Auto-cleanup expired lockouts
    if ($lockedUntil->isPast()) {                        // Check if expired
        $this->getCacheStore()->forget($cacheKey);       // Clean up cache
        return false;
    }

    return true;                                         // Still locked
}
```

**Logic Flow:**
1. **Validation:** Ensures context is properly configured
2. **Retrieval:** Gets lockout data from cache
3. **Existence Check:** Returns false if no lockout exists
4. **Expiration Check:** Automatically cleans up expired lockouts
5. **State Return:** Returns current lockout status

##### Cache Key Generation Strategy
```php
protected function buildCacheKey(string $context, string $key): string
{
    $prefix = $this->config['cache']['prefix'];           // Get configured prefix
    return $prefix . ':' . $context . ':' . hash('sha256', $key);  // Build hierarchical key
}
```

**Key Structure:** `prefix:context:hash(key)`
- **Prefix:** Prevents conflicts with other cache data
- **Context:** Separates different lockout types
- **Hash:** SHA256 of the actual key for privacy and consistency

#### Helper Methods

##### Context Configuration Resolution
```php
protected function getContextConfig(string $context): array
{
    $contexts = $this->config['contexts'] ?? [];         // Get all contexts
    return $contexts[$context] ?? [];                    // Return specific context or empty
}
```

##### Delay Sequence Resolution
```php
protected function getDelaysForContext(string $context): array
{
    $contextConfig = $this->getContextConfig($context);  // Get context config
    return $contextConfig['delays'] ?? $this->config['default_delays'];  // Use custom or default
}
```

##### Key Extraction from HTTP Requests
```php
public function extractKeyFromRequest(string $context, Request $request): string
{
    $contextConfig = $this->getContextConfig($context);
    $keyExtractor = $contextConfig['key'];               // Get extractor type

    // Handle string extractors (predefined)
    if (is_string($keyExtractor)) {
        $extractors = $this->config['key_extractors'] ?? [];
        
        if (isset($extractors[$keyExtractor])) {
            $extractor = $extractors[$keyExtractor];
            if (is_callable($extractor)) {
                return $extractor($request);            // Call custom extractor
            }
        }
        
        return $request->input($keyExtractor) ?: $request->ip();  // Fallback to input
    }

    // Handle callable extractors (custom functions)
    if (is_callable($keyExtractor)) {
        return $keyExtractor($request);
    }

    return $request->ip();                               // Final fallback to IP
}
```

### 2. ExponentialLockoutServiceProvider.php - Bootstrap & Registration

**Purpose:** Registers all package components with Laravel's service container and sets up auto-discovery.

#### Service Registration
```php
public function register(): void
{
    // Merge package configuration with app config
    $this->mergeConfigFrom(
        __DIR__ . '/../config/exponential-lockout.php',
        'exponential-lockout'
    );

    // Register LockoutManager as singleton
    $this->app->singleton(LockoutManager::class, function (Application $app) {
        $cache = $app->make(CacheManager::class);        // Inject cache manager
        $config = $app->make('config')->get('exponential-lockout');  // Get config
        
        return new LockoutManager($cache, $config);      // Create instance
    });

    // Create facade binding
    $this->app->alias(LockoutManager::class, 'exponential-lockout');

    $this->registerMiddleware();                         // Register middleware
    $this->registerCommands();                           // Register commands
}
```

#### Middleware Registration
```php
protected function registerMiddleware(): void
{
    // Register middleware with dependency injection
    $this->app->singleton(ExponentialLockout::class, function (Application $app) {
        $lockoutManager = $app->make(LockoutManager::class);  // Get manager
        $config = $app->make('config')->get('exponential-lockout');  // Get config
        
        return new ExponentialLockout($lockoutManager, $config);  // Create middleware
    });

    // Register middleware alias for routes
    $router = $this->app->make(Router::class);
    $router->aliasMiddleware('exponential.lockout', ExponentialLockout::class);
}
```

#### Blade Directives Registration
```php
protected function registerBladeDirectives(): void
{
    // @lockout directive
    \Blade::directive('lockout', function ($expression) {
        $parts = explode(',', $expression, 2);           // Parse parameters
        $context = trim($parts[0], " '\"");              // Extract context
        $key = isset($parts[1]) ? trim($parts[1], " '\"") : 'null';  // Extract key
        
        return "<?php if (app('exponential-lockout')->isLockedOut('{$context}', {$key})): ?>";
    });

    \Blade::directive('endlockout', function () {
        return "<?php endif; ?>";
    });
    
    // Additional directives: @notlockout, @lockoutinfo, @lockouttime...
}
```

### 3. Middleware/ExponentialLockout.php - HTTP Request Interceptor

**Purpose:** Intercepts HTTP requests and applies lockout logic before reaching the application.

#### Request Handling Flow
```php
public function handle(Request $request, Closure $next, string $context)
{
    // Extract identifier for this request
    $key = $this->lockoutManager->extractKeyFromRequest($context, $request);
    
    // Check lockout status
    if ($this->lockoutManager->isLockedOut($context, $key)) {
        return $this->buildLockoutResponse($request, $context, $key);
    }

    // Continue request processing
    return $next($request);
}
```

#### Response Building Strategy
```php
protected function buildLockoutResponse(Request $request, string $context, string $key): SymfonyResponse
{
    $remainingTime = $this->lockoutManager->getRemainingTime($context, $key);
    $contextConfig = $this->getContextConfig($context);
    $responseMode = $this->determineResponseMode($request, $contextConfig);

    // Build response data
    $responseData = [
        'message' => 'Too many failed attempts. Please try again later.',
        'error' => 'lockout_active',
        'context' => $context,
        'retry_after' => $remainingTime,
        'locked_until' => now()->addSeconds($remainingTime)->toISOString(),
    ];

    // Route to appropriate response handler
    switch ($responseMode) {
        case 'json':
            return $this->buildJsonResponse($responseData, $remainingTime);
        case 'redirect':
            return $this->buildRedirectResponse($request, $contextConfig, $responseData);
        case 'callback':
            return $this->buildCallbackResponse($context, $key, $remainingTime);
        case 'auto':
        default:
            return $this->buildAutoResponse($request, $contextConfig, $responseData, $remainingTime);
    }
}
```

#### Smart Response Detection
```php
protected function expectsJson(Request $request): bool
{
    return $request->expectsJson() ||                    // Laravel's built-in detection
           $request->isXmlHttpRequest() ||               // AJAX requests
           $request->is('api/*') ||                      // API routes
           $request->header('Accept') === 'application/json' ||  // JSON accept header
           $request->header('Content-Type') === 'application/json';  // JSON content type
}
```

### 4. Facades/Lockout.php - Developer Interface

**Purpose:** Provides simple, static interface to LockoutManager functionality.

```php
class Lockout extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'exponential-lockout';                    // References service container binding
    }
}
```

**How it works:**
- Laravel's Facade system resolves `'exponential-lockout'` from service container
- All static calls are forwarded to the LockoutManager instance
- Provides clean API: `Lockout::isLockedOut()` instead of `app(LockoutManager::class)->isLockedOut()`

### 5. Commands/ClearLockoutCommand.php - CLI Management

**Purpose:** Provides command-line interface for lockout management.

#### Command Structure
```php
protected $signature = 'lockout:clear 
                       {context : The lockout context to clear}
                       {key? : The specific key to clear (optional)}
                       {--all : Clear all lockouts for the context}
                       {--force : Force clearing without confirmation}';
```

#### Execution Logic
```php
public function handle(): int
{
    $context = $this->argument('context');               // Get context argument
    $key = $this->argument('key');                       // Get key argument
    $clearAll = $this->option('all');                    // Check --all flag
    $force = $this->option('force');                     // Check --force flag

    try {
        $this->validateContext($context);                // Validate context exists
        
        if ($clearAll || !$key) {
            return $this->clearAllForContext($context, $force);
        } else {
            return $this->clearSpecificKey($context, $key, $force);
        }
    } catch (InvalidArgumentException $e) {
        $this->error($e->getMessage());
        return self::FAILURE;
    }
}
```

## 🔄 Data Flow

### 1. Lockout Recording Flow
```
HTTP Request → Middleware → Check Lockout → (if not locked) → Controller
                                           ↓
Controller Logic → Auth Fails → Lockout::recordFailure()
                                           ↓
LockoutManager → validateContext() → buildCacheKey() → getCacheStore()
                                           ↓
Increment Attempts → Calculate Delay → Set Expiration → Store in Cache
```

### 2. Lockout Checking Flow
```
HTTP Request → Middleware → extractKeyFromRequest() → isLockedOut()
                                           ↓
LockoutManager → validateContext() → buildCacheKey() → getCacheStore()
                                           ↓
Retrieve Data → Check Expiration → (if expired) Clean Cache → Return Status
```

### 3. Configuration Resolution
```
Context Request → getContextConfig() → Check contexts array
                                           ↓
Context Found → Merge with Defaults → Return Config
Context Missing → Return Empty Array → validateContext() throws Exception
```

## ⚙️ Configuration System

### Configuration Hierarchy
1. **Package Defaults** - Built into code
2. **Published Config** - `config/exponential-lockout.php`
3. **Environment Variables** - `.env` file overrides
4. **Runtime Overrides** - Dynamic configuration

### Context Resolution Logic
```php
protected function getContextConfig(string $context): array
{
    $contexts = $this->config['contexts'] ?? [];         // Get all contexts
    $contextConfig = $contexts[$context] ?? [];          // Get specific context
    
    // Merge with defaults
    return array_merge([
        'enabled' => true,
        'key' => 'ip',
        'delays' => null,
        'response_mode' => null,
        'redirect_route' => null,
        'max_attempts' => null,
    ], $contextConfig);
}
```

### Key Extractor Resolution
```php
// 1. Check if it's a predefined extractor
if (is_string($keyExtractor) && isset($this->config['key_extractors'][$keyExtractor])) {
    $extractor = $this->config['key_extractors'][$keyExtractor];
    return $extractor($request);
}

// 2. Check if it's a custom callable
if (is_callable($keyExtractor)) {
    return $keyExtractor($request);
}

// 3. Fallback to input field extraction
return $request->input($keyExtractor) ?: $request->ip();
```

## 🗄️ Cache Strategy

### Cache Key Design
**Format:** `{prefix}:{context}:{hash(key)}`

**Example:** `exponential_lockout:login:a1b2c3d4e5f6...`

### Why SHA256 Hashing?
1. **Privacy** - Real emails/phones not stored in cache keys
2. **Consistency** - Same input always produces same hash
3. **Security** - Prevents cache key enumeration attacks
4. **Length** - Consistent key length regardless of input

### TTL Strategy
```php
$ttl = $lockoutDuration + 3600;  // Lockout time + 1 hour buffer
```

**Why the buffer?**
- Prevents race conditions during expiration
- Allows for clock drift between servers
- Provides grace period for debugging
- Ensures data isn't lost prematurely

### Cache Operations
```php
// Store lockout data
$store->put($cacheKey, $lockoutData, $ttl);

// Retrieve lockout data
$lockoutData = $store->get($cacheKey, $defaultData);

// Clear specific lockout
$store->forget($cacheKey);

// Context clearing (implementation varies by store)
$store->flush(); // or pattern-based deletion
```

## 🔒 Security Considerations

### Input Validation
```php
protected function validateContext(string $context): void
{
    $contextConfig = $this->getContextConfig($context);
    
    // Check context exists
    if (empty($contextConfig)) {
        throw new InvalidArgumentException("Lockout context '{$context}' is not configured.");
    }
    
    // Check context is enabled
    if (isset($contextConfig['enabled']) && !$contextConfig['enabled']) {
        throw new InvalidArgumentException("Lockout context '{$context}' is disabled.");
    }
}
```

### Key Sanitization
```php
protected function buildCacheKey(string $context, string $key): string
{
    $prefix = $this->config['cache']['prefix'];
    return $prefix . ':' . $context . ':' . hash('sha256', $key);  // Hash prevents injection
}
```

### Rate Limiting Prevention
- **No unlimited attempts** - Always enforces delay sequence
- **Context isolation** - Different contexts don't interfere
- **Automatic cleanup** - Expired lockouts are automatically removed
- **Buffer time** - Prevents timing attacks

## 🔧 Extension Points

### 1. Custom Key Extractors
```php
// In configuration
'key_extractors' => [
    'custom_extractor' => function ($request) {
        // Custom logic for extracting user identifier
        return $request->header('X-User-ID') ?: $request->ip();
    },
],
```

### 2. Custom Response Handlers
```php
'custom_response_callback' => function ($context, $key, $remainingTime) {
    // Custom response logic
    return response()->json([
        'error' => 'Custom lockout message',
        'wait_time' => $remainingTime,
    ], 429);
},
```

### 3. Context-Specific Overrides
```php
'contexts' => [
    'special_context' => [
        'key' => function ($request) {
            // Context-specific key extraction
        },
        'delays' => [10, 20, 40, 80], // Custom exponential sequence
        'response_mode' => 'callback',
    ],
],
```

### 4. Extending LockoutManager
```php
class CustomLockoutManager extends LockoutManager
{
    public function recordFailure(string $context, string $key): int
    {
        // Custom logic before recording
        $this->logAttempt($context, $key);
        
        // Call parent method
        $attempts = parent::recordFailure($context, $key);
        
        // Custom logic after recording
        $this->notifyAdmins($context, $key, $attempts);
        
        return $attempts;
    }
}
```

## 🧪 Testing Strategy

### Unit Test Coverage
```php
// Test core functionality
$this->assertEquals(1, Lockout::recordFailure('login', 'test@example.com'));
$this->assertFalse(Lockout::isLockedOut('login', 'test@example.com'));

// Test lockout activation
Lockout::recordFailure('login', 'test@example.com');
Lockout::recordFailure('login', 'test@example.com');
Lockout::recordFailure('login', 'test@example.com');
$this->assertTrue(Lockout::isLockedOut('login', 'test@example.com'));

// Test clearing
Lockout::clear('login', 'test@example.com');
$this->assertFalse(Lockout::isLockedOut('login', 'test@example.com'));
```

### Integration Test Coverage
```php
// Test middleware integration
$response = $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
$response = $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
$response = $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
$response = $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);

$response->assertStatus(429); // Should be locked out
```

## 🎯 Performance Considerations

### Cache Efficiency
- **Single cache operation per check** - No multiple round trips
- **Efficient key structure** - Hierarchical and predictable
- **Automatic cleanup** - Expired entries are removed
- **TTL optimization** - Prevents indefinite storage

### Memory Usage
- **Minimal data storage** - Only essential lockout information
- **No database overhead** - Pure cache-based solution
- **Efficient serialization** - Simple array structures

### Response Time
- **Early exit conditions** - Quick returns for non-locked users
- **Optimized cache lookups** - Direct key-based access
- **Minimal computation** - Simple arithmetic operations

## 🔍 Debugging and Monitoring

### Debug Information
```php
// Get complete lockout information
$info = Lockout::getLockoutInfo('login', 'test@example.com');
/*
Returns:
[
    'context' => 'login',
    'key' => 'test@example.com',
    'attempts' => 3,
    'is_locked_out' => true,
    'remaining_time' => 285,
    'locked_until' => Carbon instance,
    'last_attempt' => Carbon instance,
]
*/
```

### Logging Integration
```php
// Add to LockoutManager for monitoring
public function recordFailure(string $context, string $key): int
{
    $attempts = parent::recordFailure($context, $key);
    
    // Log for monitoring
    Log::info('Lockout attempt recorded', [
        'context' => $context,
        'key' => hash('sha256', $key), // Don't log actual keys
        'attempts' => $attempts,
    ]);
    
    return $attempts;
}
```

### Health Checks
```php
// Monitor lockout system health
public function healthCheck(): array
{
    return [
        'cache_available' => $this->isCacheAvailable(),
        'contexts_configured' => count($this->config['contexts']),
        'default_delays_count' => count($this->config['default_delays']),
    ];
}
```

## 📚 Further Reading

### Laravel Documentation
- [Service Container](https://laravel.com/docs/container)
- [Service Providers](https://laravel.com/docs/providers)
- [Middleware](https://laravel.com/docs/middleware)
- [Facades](https://laravel.com/docs/facades)
- [Cache](https://laravel.com/docs/cache)

### Design Patterns Used
- **Facade Pattern** - Simplified interface
- **Strategy Pattern** - Configurable responses
- **Template Method** - Extensible behavior
- **Dependency Injection** - Loose coupling
- **Factory Pattern** - Object creation

---

**Developer:** Joe Nassar  
**Email:** joe.nassar.tech@gmail.com

This guide provides complete insight into the package's internal workings. Use this knowledge to extend, customize, or contribute to the Laravel Exponential Lockout package.