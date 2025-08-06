# Examples and Recipes

This guide provides real-world examples and ready-to-use solutions for common scenarios. Copy and paste these examples to get started quickly!

## ✨ **New: 100% Automatic Operation**

All examples now work **automatically** with no manual coding required:
- ✅ **Auto-detects failures** from HTTP status codes (4xx/5xx)
- ✅ **Auto-clears lockouts** on success responses (2xx)  
- ✅ **No controller changes needed** - just add middleware
- ✅ **Works with ANY framework response** format

## 🎯 Complete Application Examples

### 1. E-commerce Website

**Perfect for:** Online stores, marketplaces, shopping sites

#### Configuration

```php
// config/exponential-lockout.php
'contexts' => [
    // Customer login - balanced protection
    'customer_login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [60, 300, 900, 1800, 7200], // 1min → 5min → 15min → 30min → 2hr
        'response_mode' => 'auto',
    ],
    
    // Admin login - stronger protection
    'admin_login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [300, 900, 1800, 7200, 21600], // 5min → 15min → 30min → 2hr → 6hr
        'response_mode' => 'auto',
        'redirect_route' => 'admin.login',
    ],
    
    // Password reset - prevent spam
    'password_reset' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [300, 600, 1800, 3600], // 5min → 10min → 30min → 1hr
    ],
    
    // Order verification (for high-value orders)
    'order_verify' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [60, 180, 300, 600], // 1min → 3min → 5min → 10min
    ],
],
```

#### Routes

```php
// routes/web.php

// Customer routes
Route::post('/login', [CustomerController::class, 'login'])
    ->middleware('exponential.lockout:customer_login');

Route::post('/password/email', [PasswordController::class, 'sendResetLinkEmail'])
    ->middleware('exponential.lockout:password_reset');

// Admin routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login'])
        ->middleware('exponential.lockout:admin_login');
});

// API routes
Route::prefix('api')->group(function () {
    Route::post('/login', [ApiController::class, 'login'])
        ->middleware('exponential.lockout:customer_login');
        
    Route::post('/verify-order', [OrderController::class, 'verify'])
        ->middleware('exponential.lockout:order_verify');
});
```

#### Customer Controller

```php
class CustomerController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check if already locked out (optional - middleware handles this)
        if (Lockout::isLockedOut('customer_login', $credentials['email'])) {
            $remainingTime = Lockout::getRemainingTime('customer_login', $credentials['email']);
            return back()->withErrors([
                'email' => 'Too many attempts. Please wait ' . ceil($remainingTime / 60) . ' minutes.'
            ]);
        }

        if (Auth::guard('customer')->attempt($credentials, $request->filled('remember'))) {
            // Success - clear any lockouts
            Lockout::clear('customer_login', $credentials['email']);
            
            return redirect()->intended('/dashboard');
        }

        // Login failed - record attempt
        $attempts = Lockout::recordFailure('customer_login', $credentials['email']);
        
        return back()->withErrors([
            'email' => 'Invalid credentials. Attempt: ' . $attempts
        ])->withInput($request->except('password'));
    }
}
```

### 2. SaaS Application

**Perfect for:** Software-as-a-Service, B2B platforms, subscription services

#### Configuration

```php
'contexts' => [
    // User login - standard protection
    'user_login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [60, 300, 900, 1800, 7200],
    ],
    
    // API authentication - strict protection
    'api_auth' => [
        'enabled' => true,
        'key' => 'api_key',
        'delays' => [60, 300, 900, 1800, 7200, 21600],
        'response_mode' => 'json',
    ],
    
    // Two-factor authentication
    '2fa_verify' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [30, 60, 120, 300, 600], // Quick cycles for time-sensitive 2FA
    ],
    
    // Webhook validation (for external integrations)
    'webhook_auth' => [
        'enabled' => true,
        'key' => 'ip',
        'delays' => [300, 600, 1800, 7200], // Longer delays for automated systems
    ],
],
```

#### API Authentication Controller

```php
class ApiAuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        // Check lockout status
        if (Lockout::isLockedOut('api_auth', $apiKey)) {
            $info = Lockout::getLockoutInfo('api_auth', $apiKey);
            
            return response()->json([
                'error' => 'API key temporarily locked',
                'retry_after' => $info['remaining_time'],
                'locked_until' => $info['locked_until']->toISOString(),
            ], 429);
        }

        // Validate API key
        $user = User::where('api_key', $apiKey)->first();
        
        if (!$user || !$user->is_active) {
            // Record failed attempt
            Lockout::recordFailure('api_auth', $apiKey);
            
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Success - clear lockout
        Lockout::clear('api_auth', $apiKey);
        
        return response()->json([
            'user' => $user,
            'token' => $user->createToken('API Access')->plainTextToken,
        ]);
    }
}
```

### 3. Educational Platform

**Perfect for:** Schools, online courses, learning management systems

#### Configuration

```php
'contexts' => [
    // Student login - lenient protection
    'student_login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [120, 300, 600, 1200], // 2min → 5min → 10min → 20min
    ],
    
    // Teacher login - standard protection
    'teacher_login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [60, 300, 900, 1800],
    ],
    
    // Exam access - strict protection
    'exam_access' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [300, 900, 1800, 7200], // 5min → 15min → 30min → 2hr
    ],
    
    // Quiz attempts
    'quiz_attempt' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [60, 180, 300], // 1min → 3min → 5min
    ],
],
```

#### Exam Access Controller

```php
class ExamController extends Controller
{
    public function startExam(Request $request, $examId)
    {
        $user = $request->user();
        $userKey = $user->id . ':exam:' . $examId; // Unique key per user per exam

        if (Lockout::isLockedOut('exam_access', $userKey)) {
            $remainingTime = Lockout::getRemainingTime('exam_access', $userKey);
            
            return response()->json([
                'error' => 'Exam access temporarily locked',
                'message' => 'Too many failed access attempts',
                'retry_after_minutes' => ceil($remainingTime / 60),
            ], 429);
        }

        // Validate exam access
        $exam = Exam::findOrFail($examId);
        
        if (!$this->canUserAccessExam($user, $exam)) {
            Lockout::recordFailure('exam_access', $userKey);
            
            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have permission to access this exam',
            ], 403);
        }

        // Success - clear lockout and start exam
        Lockout::clear('exam_access', $userKey);
        
        $examSession = $this->createExamSession($user, $exam);
        
        return response()->json([
            'exam' => $exam,
            'session' => $examSession,
            'time_limit' => $exam->time_limit_minutes,
        ]);
    }
}
```

## 🎨 Feature-Specific Examples

### OTP Verification System

**Complete OTP system with lockout protection**

#### Configuration

```php
'contexts' => [
    'otp_send' => [
        'enabled' => true,
        'key' => 'phone',
        'delays' => [60, 180, 300, 600], // 1min → 3min → 5min → 10min
    ],
    
    'otp_verify' => [
        'enabled' => true,
        'key' => 'phone',
        'delays' => [30, 60, 180, 300, 600], // 30sec → 1min → 3min → 5min → 10min
    ],
],
```

#### OTP Controller

```php
class OtpController extends Controller
{
    public function sendOtp(Request $request)
    {
        $phone = $request->validate(['phone' => 'required|string'])['phone'];

        // Check if sending is locked out
        if (Lockout::isLockedOut('otp_send', $phone)) {
            $remainingTime = Lockout::getRemainingTime('otp_send', $phone);
            
            return response()->json([
                'error' => 'Too many OTP requests',
                'retry_after' => $remainingTime,
            ], 429);
        }

        // Check if phone number exists
        $user = User::where('phone', $phone)->first();
        
        if (!$user) {
            // Record failure for invalid phone numbers
            Lockout::recordFailure('otp_send', $phone);
            
            return response()->json([
                'error' => 'Phone number not found'
            ], 404);
        }

        // Generate and send OTP
        $otp = $this->generateOtp();
        $this->storeOtp($phone, $otp);
        $this->sendSms($phone, "Your verification code is: $otp");

        // Don't record failure for successful sends
        return response()->json([
            'message' => 'OTP sent successfully',
            'expires_in' => 300, // 5 minutes
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        // Check if verification is locked out
        if (Lockout::isLockedOut('otp_verify', $data['phone'])) {
            $info = Lockout::getLockoutInfo('otp_verify', $data['phone']);
            
            return response()->json([
                'error' => 'Too many verification attempts',
                'attempts_made' => $info['attempts'],
                'retry_after' => $info['remaining_time'],
            ], 429);
        }

        // Verify OTP
        if ($this->isValidOtp($data['phone'], $data['otp'])) {
            // Success - clear both lockouts
            Lockout::clear('otp_send', $data['phone']);
            Lockout::clear('otp_verify', $data['phone']);
            
            $this->clearStoredOtp($data['phone']);
            
            return response()->json([
                'message' => 'Phone verified successfully'
            ]);
        }

        // Failed verification
        $attempts = Lockout::recordFailure('otp_verify', $data['phone']);
        
        return response()->json([
            'error' => 'Invalid verification code',
            'attempts_made' => $attempts,
        ], 400);
    }
}
```

### Multi-Factor Authentication

**Complete 2FA system with backup codes**

#### Configuration

```php
'contexts' => [
    '2fa_totp' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [30, 60, 120, 300], // Quick cycles for TOTP
    ],
    
    '2fa_backup' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [60, 180, 300, 600], // Longer for backup codes
    ],
],
```

#### 2FA Controller

```php
class TwoFactorController extends Controller
{
    public function verifyTotp(Request $request)
    {
        $user = $request->user();
        $code = $request->validate(['code' => 'required|string'])['code'];

        if (Lockout::isLockedOut('2fa_totp', $user->id)) {
            $remainingTime = Lockout::getRemainingTime('2fa_totp', $user->id);
            
            return back()->withErrors([
                'code' => 'Too many attempts. Please wait ' . ceil($remainingTime / 60) . ' minutes.'
            ]);
        }

        if ($this->verifyTotpCode($user, $code)) {
            // Success
            Lockout::clear('2fa_totp', $user->id);
            session(['2fa_verified' => true]);
            
            return redirect()->intended('/dashboard');
        }

        // Failed attempt
        $attempts = Lockout::recordFailure('2fa_totp', $user->id);
        
        return back()->withErrors([
            'code' => 'Invalid code. Attempt: ' . $attempts
        ]);
    }

    public function verifyBackupCode(Request $request)
    {
        $user = $request->user();
        $code = $request->validate(['backup_code' => 'required|string'])['backup_code'];

        if (Lockout::isLockedOut('2fa_backup', $user->id)) {
            $remainingTime = Lockout::getRemainingTime('2fa_backup', $user->id);
            
            return back()->withErrors([
                'backup_code' => 'Too many backup code attempts. Please wait ' . ceil($remainingTime / 60) . ' minutes.'
            ]);
        }

        if ($this->verifyBackupCode($user, $code)) {
            // Success - consume the backup code
            Lockout::clear('2fa_backup', $user->id);
            Lockout::clear('2fa_totp', $user->id); // Clear TOTP lockout too
            
            $this->consumeBackupCode($user, $code);
            session(['2fa_verified' => true]);
            
            return redirect()->intended('/dashboard');
        }

        // Failed attempt
        Lockout::recordFailure('2fa_backup', $user->id);
        
        return back()->withErrors([
            'backup_code' => 'Invalid backup code'
        ]);
    }
}
```

## 🔧 Advanced Patterns

### Smart Key Generation

**Context-aware key generation for complex scenarios**

```php
class SmartLockoutHelper
{
    public static function generateKey($context, $request, $additionalData = [])
    {
        switch ($context) {
            case 'login':
                return $request->input('email') ?: $request->ip();
                
            case 'api_auth':
                return $request->header('X-API-Key') ?: $request->ip();
                
            case 'payment_verify':
                $userId = $request->user()?->id;
                $orderId = $additionalData['order_id'] ?? 'unknown';
                return $userId ? "{$userId}:order:{$orderId}" : $request->ip();
                
            case 'admin_action':
                $userId = $request->user()?->id;
                $action = $additionalData['action'] ?? 'unknown';
                return $userId ? "{$userId}:admin:{$action}" : $request->ip();
                
            default:
                return $request->ip();
        }
    }
}

// Usage in controller
$key = SmartLockoutHelper::generateKey('payment_verify', $request, ['order_id' => $order->id]);
if (Lockout::isLockedOut('payment_verify', $key)) {
    // Handle lockout
}
```

### Conditional Lockout Logic

**Apply different rules based on user type or time**

```php
class ConditionalLockoutController extends Controller
{
    public function login(Request $request)
    {
        $email = $request->email;
        $user = User::where('email', $email)->first();
        
        // Determine context based on user type
        $context = $this->determineContext($user);
        
        if (Lockout::isLockedOut($context, $email)) {
            return $this->handleLockout($context, $email);
        }

        if (Auth::attempt($request->only('email', 'password'))) {
            Lockout::clear($context, $email);
            return redirect()->intended();
        }

        // Apply different lockout rules based on time and user type
        if ($this->shouldApplyLockout($user)) {
            Lockout::recordFailure($context, $email);
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    private function determineContext($user)
    {
        if (!$user) {
            return 'unknown_user_login';
        }
        
        if ($user->is_admin) {
            return 'admin_login';
        }
        
        if ($user->is_premium) {
            return 'premium_login';
        }
        
        return 'regular_login';
    }

    private function shouldApplyLockout($user)
    {
        $currentHour = now()->hour;
        
        // Stricter lockout during business hours
        if ($currentHour >= 9 && $currentHour <= 17) {
            return true;
        }
        
        // More lenient for premium users
        if ($user && $user->is_premium) {
            return false;
        }
        
        return true;
    }
}
```

### Gradual Response System

**Escalating responses before full lockout**

```php
class GradualLockoutController extends Controller
{
    public function handleLogin(Request $request)
    {
        $email = $request->email;
        $attempts = Lockout::getAttemptCount('login', $email);
        
        // Check for full lockout
        if (Lockout::isLockedOut('login', $email)) {
            return $this->fullLockoutResponse($email);
        }
        
        // Apply progressive challenges based on attempt count
        switch ($attempts) {
            case 1:
                return $this->addCaptcha($request);
            case 2:
                return $this->requireEmailVerification($email);
            case 3:
                return $this->addDelay($request, 10); // 10 second delay
            default:
                return $this->processLogin($request);
        }
    }

    private function addCaptcha($request)
    {
        if (!$this->verifyCaptcha($request)) {
            return back()->withErrors(['captcha' => 'Please complete the captcha']);
        }
        
        return $this->processLogin($request);
    }

    private function requireEmailVerification($email)
    {
        $token = $this->sendVerificationEmail($email);
        
        return back()->with('message', 'Please verify your email before continuing');
    }

    private function addDelay($request, $seconds)
    {
        $lastAttempt = session('last_login_attempt', 0);
        $timeDiff = time() - $lastAttempt;
        
        if ($timeDiff < $seconds) {
            $remaining = $seconds - $timeDiff;
            return back()->withErrors(['delay' => "Please wait {$remaining} seconds"]);
        }
        
        session(['last_login_attempt' => time()]);
        return $this->processLogin($request);
    }
}
```

## 🌐 Integration Examples

### Laravel Breeze Integration

**Add lockout protection to Laravel Breeze**

#### Update AuthenticatedSessionController

```php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php

use ExponentialLockout\Facades\Lockout;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request)
    {
        $email = $request->email;

        // Check lockout before processing
        if (Lockout::isLockedOut('login', $email)) {
            $remainingTime = Lockout::getRemainingTime('login', $email);
            
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again in ' . 
                          ceil($remainingTime / 60) . ' minutes.',
            ]);
        }

        try {
            $request->authenticate();
            
            // Success - clear lockout
            Lockout::clear('login', $email);
            
            $request->session()->regenerate();
            return redirect()->intended(RouteServiceProvider::HOME);
            
        } catch (ValidationException $e) {
            // Authentication failed - record attempt
            Lockout::recordFailure('login', $email);
            throw $e;
        }
    }
}
```

#### Add Middleware to Routes

```php
// routes/auth.php

Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'exponential.lockout:login']);

Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
    ->middleware(['guest', 'exponential.lockout:password_reset']);
```

### Laravel Fortify Integration

**Add to Fortify service provider**

```php
// app/Providers/FortifyServiceProvider.php

use ExponentialLockout\Facades\Lockout;
use Laravel\Fortify\Fortify;

public function boot()
{
    // Custom login response
    Fortify::authenticateUsing(function (Request $request) {
        $email = $request->email;
        
        if (Lockout::isLockedOut('login', $email)) {
            $remainingTime = Lockout::getRemainingTime('login', $email);
            throw ValidationException::withMessages([
                'email' => 'Account temporarily locked. Please wait ' . 
                          ceil($remainingTime / 60) . ' minutes.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            Lockout::clear('login', $email);
            return $user;
        }
        
        Lockout::recordFailure('login', $email);
        return null;
    });
}
```

## 📱 Mobile App Examples

### API Error Handling

**Consistent API responses for mobile apps**

```php
trait ApiLockoutResponses
{
    protected function lockoutResponse($context, $key)
    {
        $info = Lockout::getLockoutInfo($context, $key);
        
        return response()->json([
            'success' => false,
            'error' => [
                'type' => 'rate_limit_exceeded',
                'code' => 'E_TOO_MANY_ATTEMPTS',
                'message' => 'Too many failed attempts',
                'details' => [
                    'context' => $context,
                    'attempts_made' => $info['attempts'],
                    'retry_after_seconds' => $info['remaining_time'],
                    'retry_after_human' => $this->formatDuration($info['remaining_time']),
                    'locked_until' => $info['locked_until']?->toISOString(),
                ]
            ]
        ], 429);
    }

    protected function formatDuration($seconds)
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        } elseif ($seconds < 3600) {
            $minutes = ceil($seconds / 60);
            return "{$minutes} minutes";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = ceil(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }
}
```

### React Native Integration

**JavaScript client handling**

```javascript
// AuthService.js
class AuthService {
    async login(email, password) {
        try {
            const response = await fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (response.status === 429) {
                // Handle lockout
                const retryAfter = data.error.details.retry_after_seconds;
                const humanTime = data.error.details.retry_after_human;
                
                throw new LockoutError(
                    `Too many attempts. Please wait ${humanTime}`,
                    retryAfter
                );
            }

            if (!response.ok) {
                throw new Error(data.message || 'Login failed');
            }

            return data;
        } catch (error) {
            if (error instanceof LockoutError) {
                // Start countdown timer
                this.startLockoutTimer(error.retryAfter);
            }
            throw error;
        }
    }

    startLockoutTimer(seconds) {
        const interval = setInterval(() => {
            seconds--;
            this.updateUI(seconds);
            
            if (seconds <= 0) {
                clearInterval(interval);
                this.enableLoginForm();
            }
        }, 1000);
    }
}

class LockoutError extends Error {
    constructor(message, retryAfter) {
        super(message);
        this.name = 'LockoutError';
        this.retryAfter = retryAfter;
    }
}
```

## 🎯 Testing Examples

### Feature Tests

**PHPUnit tests for lockout functionality**

```php
// tests/Feature/LockoutTest.php

class LockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_locked_out_after_multiple_failures()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Make 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            $response = $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // Fourth attempt should be blocked
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $this->assertTrue(Lockout::isLockedOut('login', 'test@example.com'));
    }

    public function test_successful_login_clears_lockout()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // Make failed attempts
        for ($i = 0; $i < 3; $i++) {
            Lockout::recordFailure('login', 'test@example.com');
        }

        $this->assertTrue(Lockout::isLockedOut('login', 'test@example.com'));

        // Successful login should clear lockout
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertFalse(Lockout::isLockedOut('login', 'test@example.com'));
    }
}
```

### Unit Tests

```php
// tests/Unit/LockoutManagerTest.php

class LockoutManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Clear cache before each test
    }

    public function test_records_failure_and_increases_attempts()
    {
        $attempts = Lockout::recordFailure('login', 'test@example.com');
        $this->assertEquals(1, $attempts);

        $attempts = Lockout::recordFailure('login', 'test@example.com');
        $this->assertEquals(2, $attempts);
    }

    public function test_clears_lockout_successfully()
    {
        Lockout::recordFailure('login', 'test@example.com');
        Lockout::recordFailure('login', 'test@example.com');
        Lockout::recordFailure('login', 'test@example.com');

        $this->assertTrue(Lockout::isLockedOut('login', 'test@example.com'));

        $result = Lockout::clear('login', 'test@example.com');
        $this->assertTrue($result);
        $this->assertFalse(Lockout::isLockedOut('login', 'test@example.com'));
    }
}
```

## 🚀 Next Steps

Now you have complete, real-world examples! Here's what to do next:

1. **Choose the example that matches your application type**
2. **Copy and customize the configuration**
3. **Implement the controller patterns**
4. **Test thoroughly with the provided test examples**
5. **Monitor and adjust based on your specific needs**

## 🆘 Need Help?

- **[Troubleshooting Guide](troubleshooting.md)** - Fix common issues
- **[Configuration Guide](configuration.md)** - Customize settings
- **Email Developer:** joe.nassar.tech@gmail.com

Your security system is now ready for production! 🔒