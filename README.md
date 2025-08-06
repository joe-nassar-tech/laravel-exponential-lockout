# Laravel Exponential Lockout

A comprehensive Laravel package for implementing exponential lockout functionality on failed authentication attempts with configurable contexts and response handling.

## Features

- ✅ **Exponential Delay Sequence**: Configurable delay progression (default: 1min → 5min → 15min → 30min → 2hr → 6hr → 12hr → 24hr)
- ✅ **Multiple Contexts**: Support for different lockout contexts (`login`, `otp`, `pin`, etc.)
- ✅ **Flexible Key Extraction**: Track by email, phone, user ID, IP, or custom logic
- ✅ **Middleware Support**: Easy route protection with `exponential.lockout:{context}`
- ✅ **Manual API Control**: Programmatic lockout management
- ✅ **Smart Response Handling**: Auto-detect JSON/redirect responses
- ✅ **Cache-Based Storage**: Uses Laravel's cache system (Redis, File, etc.)
- ✅ **Artisan Commands**: CLI tools for lockout management
- ✅ **Blade Directives**: Template helpers for lockout status
- ✅ **Laravel 9+ Compatible**: Full support for modern Laravel versions

## Installation

Install via Composer:

```bash
composer require joenassar/laravel-exponential-lockout
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=exponential-lockout-config
```

## Configuration

The package comes with sensible defaults, but you can customize everything in `config/exponential-lockout.php`:

```php
return [
    // Cache configuration
    'cache' => [
        'store' => null, // Uses default cache store
        'prefix' => 'exponential_lockout',
    ],

    // Default delay sequence (in seconds)
    'default_delays' => [60, 300, 900, 1800, 7200, 21600, 43200, 86400],

    // Response handling
    'default_response_mode' => 'auto', // 'auto', 'json', 'redirect', 'callback'
    'default_redirect_route' => 'login',

    // Context-specific configurations
    'contexts' => [
        'login' => [
            'enabled' => true,
            'key' => 'email',
            'delays' => null, // Uses default_delays
        ],
        'otp' => [
            'enabled' => true,
            'key' => 'phone',
            'delays' => [30, 60, 180, 300, 600], // Shorter delays for OTP
            'response_mode' => 'json',
        ],
        // ... more contexts
    ],
];
```

## Basic Usage

### 1. Middleware Protection

Protect routes with middleware:

```php
use Illuminate\Support\Facades\Route;

// Login route protection
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');

// OTP verification protection  
Route::post('/verify-otp', [OtpController::class, 'verify'])
    ->middleware('exponential.lockout:otp');

// PIN validation protection
Route::post('/validate-pin', [PinController::class, 'validate'])
    ->middleware('exponential.lockout:pin');
```

### 2. Manual Lockout Management

Use the `Lockout` facade for manual control:

```php
use ExponentialLockout\Facades\Lockout;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        if (Auth::attempt($credentials)) {
            // Clear lockout on successful login
            Lockout::clear('login', $request->email);
            
            return redirect()->intended('dashboard');
        }
        
        // Record failed attempt
        $attemptCount = Lockout::recordFailure('login', $request->email);
        
        return back()->withErrors([
            'email' => 'Invalid credentials. Attempt: ' . $attemptCount
        ]);
    }
}
```

### 3. OTP Verification Example

```php
class OtpController extends Controller
{
    public function verify(Request $request)
    {
        $phone = $request->input('phone');
        $otp = $request->input('otp');
        
        if ($this->isValidOtp($phone, $otp)) {
            // Clear lockout on successful verification
            Lockout::clear('otp', $phone);
            
            return response()->json(['message' => 'OTP verified successfully']);
        }
        
        // Record failed attempt
        Lockout::recordFailure('otp', $phone);
        
        return response()->json([
            'error' => 'Invalid OTP',
            'attempts' => Lockout::getAttemptCount('otp', $phone)
        ], 401);
    }
}
```

## Advanced Usage

### Custom Key Extraction

Define custom key extractors in the config:

```php
'key_extractors' => [
    'user_session' => function ($request) {
        return $request->session()->getId();
    },
    'device_fingerprint' => function ($request) {
        return hash('sha256', $request->userAgent() . $request->ip());
    },
],

'contexts' => [
    'admin_login' => [
        'key' => 'device_fingerprint',
        'delays' => [300, 900, 1800, 7200],
    ],
],
```

### Custom Response Handling

Implement custom response logic:

```php
'custom_response_callback' => function ($context, $key, $remainingTime) {
    return response()->json([
        'error' => 'Account temporarily locked',
        'context' => $context,
        'retry_after' => $remainingTime,
        'retry_after_human' => gmdate('H:i:s', $remainingTime),
    ], 429);
},
```

### Check Lockout Status

Check if a user is locked out before processing:

```php
if (Lockout::isLockedOut('login', $email)) {
    $remainingTime = Lockout::getRemainingTime('login', $email);
    
    return response()->json([
        'error' => 'Account locked',
        'retry_after' => $remainingTime
    ], 429);
}
```

### Get Detailed Lockout Information

```php
$info = Lockout::getLockoutInfo('login', $email);
/*
Returns:
[
    'context' => 'login',
    'key' => 'user@example.com',
    'attempts' => 3,
    'is_locked_out' => true,
    'remaining_time' => 840,
    'locked_until' => Carbon instance,
    'last_attempt' => Carbon instance,
]
*/
```

## Blade Directives

Use Blade directives in your templates:

```blade
{{-- Check if user is locked out --}}
@lockout('login', $user->email)
    <div class="alert alert-warning">
        Your account is temporarily locked. Please try again later.
    </div>
@endlockout

{{-- Show content when NOT locked out --}}
@notlockout('login', $user->email)
    <form method="POST" action="/login">
        <!-- Login form -->
    </form>
@endnotlockout

{{-- Get lockout information --}}
@lockoutinfo($lockoutInfo, 'login', $user->email)
@if($lockoutInfo['is_locked_out'])
    <p>Locked for {{ gmdate('H:i:s', $lockoutInfo['remaining_time']) }} more</p>
@endif

{{-- Get remaining time --}}
@lockouttime($remainingSeconds, 'login', $user->email)
@if($remainingSeconds > 0)
    <p>Try again in {{ $remainingSeconds }} seconds</p>
@endif
```

## Artisan Commands

### Clear Specific Lockout

```bash
# Clear lockout for specific context and key
php artisan lockout:clear login user@example.com

# Clear with force (no confirmation)
php artisan lockout:clear login user@example.com --force
```

### Clear All Lockouts for Context

```bash
# Clear all lockouts for a context
php artisan lockout:clear login --all

# With force flag
php artisan lockout:clear login --all --force
```

## API Reference

### Lockout Facade Methods

```php
// Record a failed attempt
Lockout::recordFailure(string $context, string $key): int

// Check if locked out
Lockout::isLockedOut(string $context, string $key): bool

// Get remaining lockout time in seconds
Lockout::getRemainingTime(string $context, string $key): int

// Clear lockout
Lockout::clear(string $context, string $key): bool

// Clear all lockouts for context
Lockout::clearContext(string $context): bool

// Get attempt count
Lockout::getAttemptCount(string $context, string $key): int

// Extract key from request
Lockout::extractKeyFromRequest(string $context, Request $request): string

// Get detailed lockout information
Lockout::getLockoutInfo(string $context, string $key): array
```

## Context Configuration

Each context can be configured independently:

```php
'contexts' => [
    'login' => [
        'enabled' => true,                    // Enable/disable this context
        'key' => 'email',                     // Key extraction method
        'delays' => [60, 300, 900],          // Custom delay sequence
        'response_mode' => 'auto',            // Response handling mode
        'redirect_route' => 'login',          // Redirect route for web requests
        'max_attempts' => null,               // Max attempts (null = use delay sequence length)
    ],
],
```

### Available Key Extractors

- `email` - Extract from `email` input field
- `phone` - Extract from `phone` input field  
- `user_id` - Extract from authenticated user ID
- `ip` - Use client IP address
- `username` - Extract from `username` input field
- Custom callable - Define your own extraction logic

### Response Modes

- `auto` - Auto-detect JSON or redirect based on request
- `json` - Always return JSON response
- `redirect` - Always redirect to specified route
- `callback` - Use custom callback function

## Delay Sequences

Default sequence provides exponential backoff:

```php
[60, 300, 900, 1800, 7200, 21600, 43200, 86400]
// 1min, 5min, 15min, 30min, 2hr, 6hr, 12hr, 24hr
```

Customize per context:

```php
'contexts' => [
    'otp' => [
        'delays' => [30, 60, 180, 300, 600], // Shorter for OTP
    ],
    'admin' => [
        'delays' => [600, 1800, 7200, 21600], // Longer for admin
    ],
],
```

## Error Handling

The package includes comprehensive error handling:

```php
try {
    Lockout::recordFailure('invalid_context', $key);
} catch (InvalidArgumentException $e) {
    // Context not configured or disabled
    Log::error('Lockout error: ' . $e->getMessage());
}
```

## Cache Considerations

### Cache Store Selection

Configure the cache store in your config:

```php
'cache' => [
    'store' => 'redis', // Use specific store
    'prefix' => 'app_lockout',
],
```

### TTL Management

Cache entries automatically expire after lockout duration + 1 hour buffer.

### Redis Optimization

For Redis, consider using a dedicated database:

```php
// config/cache.php
'stores' => [
    'lockout_redis' => [
        'driver' => 'redis',
        'connection' => 'lockout',
    ],
],

// config/database.php
'redis' => [
    'lockout' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'database' => 2, // Dedicated database
    ],
],
```

## Testing

The package includes comprehensive test coverage. Run tests with:

```bash
composer test
```

### Testing Lockouts in Your App

```php
class LoginTest extends TestCase
{
    public function test_user_gets_locked_out_after_failures()
    {
        // Simulate multiple failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
        }
        
        // Verify lockout is active
        $this->assertTrue(Lockout::isLockedOut('login', 'test@example.com'));
        
        // Test lockout response
        $response = $this->post('/login', ['email' => 'test@example.com', 'password' => 'correct']);
        $response->assertStatus(429);
    }
}
```

## Performance Considerations

- **Cache Efficiency**: Uses single cache key per context/user combination
- **TTL Optimization**: Automatic cleanup of expired lockouts
- **Memory Usage**: Minimal data storage per lockout entry
- **Lookup Speed**: O(1) cache lookups for lockout status

## Security Best Practices

1. **Rate Limiting**: Combine with Laravel's rate limiting for comprehensive protection
2. **IP Tracking**: Use IP-based lockouts for anonymous endpoints
3. **Context Separation**: Use different contexts for different authentication methods
4. **Cache Security**: Secure your cache store (Redis AUTH, etc.)
5. **Key Hashing**: Keys are automatically hashed for privacy

## Troubleshooting

### Common Issues

**Lockouts not working:**
- Check context is enabled in config
- Verify cache store is working
- Ensure middleware is applied to routes

**Lockouts not clearing:**
- Check cache connectivity
- Verify context and key match exactly
- Use Artisan command to manually clear

**Wrong response format:**
- Check `response_mode` in context config
- Verify request headers for JSON detection
- Test with custom response callback

### Debug Mode

Enable debug logging:

```php
// In your controller
Log::info('Lockout status', [
    'context' => 'login',
    'key' => $email,
    'info' => Lockout::getLockoutInfo('login', $email)
]);
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and updates.

## Support

- **Documentation**: This README and inline code comments
- **Issues**: GitHub Issues for bug reports and feature requests
- **Discussions**: GitHub Discussions for questions and community support

---

## About the Developer

**Joe Nassar**  
Email: joe.nassar.tech@gmail.com

**Made with ❤️ for the Laravel community**