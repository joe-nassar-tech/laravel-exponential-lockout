# Middleware Protection Guide

Middleware protection is the easiest way to secure your website pages. Just add one line of code and your page is **fully automatic**! This guide explains everything in simple terms.

## 🎯 What is Middleware Protection?

Think of middleware as a smart security guard that:

- **Checks everyone** before they enter a room
- **Remembers troublemakers** automatically  
- **Blocks suspicious visitors** for increasing time periods
- **Records failures** automatically based on error responses
- **Clears blocks** automatically when login succeeds

## ✨ **NEW: Fully Automatic Operation**

**No manual coding required!** The middleware now:
- ✅ **Automatically records failures** for any 4xx/5xx error responses
- ✅ **Automatically clears lockouts** for any 2xx success responses  
- ✅ **Tracks all error codes**: 400, 401, 403, 404, 422, 500, 502, etc.
- ✅ **Works with any HTTP status** your controller returns

## 🛡️ How to Add Protection

### Basic Syntax

```php
->middleware('exponential.lockout:CONTEXT_NAME')
```

**CONTEXT_NAME** is like a label that tells the system what kind of protection this is (login, otp, password reset, etc.)

### 📋 **Required Request Fields**

The middleware needs specific input fields in your request to identify users:

| Context | Required Field | Alternative Fields | Example |
|---------|---------------|-------------------|---------|
| `login` | `email` | `username` | `"email": "user@example.com"` |
| `otp` | `phone` | `mobile`, `telephone` | `"phone": "+1234567890"` |
| `password_reset` | `email` | `username` | `"email": "user@example.com"` |
| Custom | Configurable | Configurable | Any field you specify |

**⚠️ Important:** If the required field is missing, the middleware will log a warning and fall back to IP address tracking.

## 📝 Step-by-Step Examples

### 1. Protect Login Page

**Find your login route** (usually in `routes/web.php` or `routes/api.php`):

```php
// BEFORE (no protection)
Route::post('/login', [LoginController::class, 'login']);

// AFTER (with protection) - ONE LINE CHANGE!
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');
```

**Your request must include:**
```json
{
  "email": "user@example.com",
  "password": "userpassword"
}
```

**What happens automatically:**
- ✅ **Success (200)** → Lockout cleared automatically
- ❌ **Failed (401/422)** → Failure recorded automatically  
- 🚫 **1st failure** → No blocking yet (free attempt)
- 🚫 **2nd failure** → No blocking yet (free attempt)
- 🚫 **3rd failure** → No blocking yet (free attempt)
- ⏱️ **4th failure** → Locked for 1 minute
- ⏱️ **5th failure** → Locked for 5 minutes
- ⏱️ **6th failure** → Locked for 15 minutes
- And so on with exponential delays...

## 🤖 **How Automatic Detection Works**

The middleware automatically detects success/failure by checking HTTP status codes:

### ✅ **Success Responses (2xx) - Auto-Clear Lockouts**
```
200 OK              → Login successful, clear lockout
201 Created         → Registration successful, clear lockout  
202 Accepted        → Request accepted, clear lockout
204 No Content      → Action completed, clear lockout
```

### ❌ **Failure Responses (4xx/5xx) - Auto-Record Failures**
```
400 Bad Request     → Invalid request format, record failure
401 Unauthorized    → Wrong password/credentials, record failure
403 Forbidden       → Access denied, record failure
404 Not Found       → Resource not found, record failure
422 Unprocessable   → Validation failed, record failure
429 Too Many Req    → Rate limited, record failure
500 Server Error    → Internal error, record failure
502 Bad Gateway     → Service unavailable, record failure
503 Service Unavail → Service down, record failure
```

### ℹ️ **Informational/Redirect (1xx/3xx) - No Action**
```
100 Continue        → Ignored (no action taken)
301 Moved Permanent → Ignored (no action taken)
302 Found           → Ignored (no action taken)
```

**This means your existing controller code needs NO changes!** 

### 2. Protect Password Reset

```php
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('exponential.lockout:password_reset');
```

**Required request field:**
```json
{
  "email": "user@example.com"
}
```

**What this prevents:**
- People spamming "forgot password" requests
- Hackers trying to flood your email system

### 3. Protect Phone/Email Verification

```php
Route::post('/verify-phone', [VerificationController::class, 'verify'])
    ->middleware('exponential.lockout:otp');

Route::post('/verify-email', [VerificationController::class, 'verifyEmail'])
    ->middleware('exponential.lockout:email_verification');
```

**Required request fields:**
```json
// For OTP verification
{
  "phone": "+1234567890",
  "code": "123456"
}

// For email verification  
{
  "email": "user@example.com",
  "token": "verification_token_here"
}
```

**What this prevents:**
- People guessing verification codes
- Automated attacks on verification systems

### 4. Protect Admin Login

```php
Route::post('/admin/login', [AdminController::class, 'login'])
    ->middleware('exponential.lockout:admin');
```

**What this prevents:**
- Attacks specifically targeting admin accounts
- Separate protection from regular user login

## 🎛️ Different Protection Types

### Quick Protection (For OTP/Codes)

```php
// Shorter wait times for time-sensitive things
Route::post('/verify-otp', [OtpController::class, 'verify'])
    ->middleware('exponential.lockout:otp');
```

**Default wait times:** 30sec → 1min → 3min → 5min → 10min

### Strong Protection (For Login)

```php
// Longer wait times for important things
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');
```

**Default wait times:** 1min → 5min → 15min → 30min → 2hr → 6hr → 12hr → 24hr

### Custom Protection

You can create your own protection types! See [Configuration Guide](configuration.md) for details.

## 🎯 Multiple Route Protection

### Protect Multiple Routes at Once

```php
// Group routes with same protection
Route::middleware(['exponential.lockout:login'])->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/mobile-login', [MobileController::class, 'login']);
    Route::post('/social-login', [SocialController::class, 'login']);
});
```

### Different Protection for Different Routes

```php
// Each route has its own protection
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');

Route::post('/admin/login', [AdminController::class, 'login'])
    ->middleware('exponential.lockout:admin');

Route::post('/verify-phone', [OtpController::class, 'verify'])
    ->middleware('exponential.lockout:otp');
```

## 🎨 What Users See

### Automatic Responses

The middleware automatically gives appropriate responses:

**For Web Pages (HTML):**
- Redirects back to the form
- Shows error message: "Too many attempts. Please try again later."
- Includes how long to wait

**For API Calls (JSON):**
```json
{
    "message": "Too many failed attempts. Please try again later.",
    "error": "lockout_active",
    "retry_after": 300,
    "locked_until": "2024-01-15T14:30:00Z"
}
```

**HTTP Status Code:** 429 (Too Many Requests)

### Custom Error Messages

You can customize what users see. Check [Configuration Guide](configuration.md) for details.

## 🔍 How the System Identifies Users

### By Email (Default for Login)

```php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');
```

**Tracks by:** The email address entered in the form

### By Phone Number (For OTP)

```php
Route::post('/verify-otp', [OtpController::class, 'verify'])
    ->middleware('exponential.lockout:otp');
```

**Tracks by:** The phone number entered in the form

### By IP Address (Fallback)

If no email/phone is provided, the system uses the visitor's IP address.

## 🛠️ Advanced Examples

### API Route Protection

```php
// For API routes (JSON responses)
Route::prefix('api')->group(function () {
    Route::post('/login', [ApiController::class, 'login'])
        ->middleware('exponential.lockout:api_login');
        
    Route::post('/verify-token', [ApiController::class, 'verify'])
        ->middleware('exponential.lockout:token_verification');
});
```

### Combined with Other Middleware

```php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware(['throttle:60,1', 'exponential.lockout:login']);
```

**What this does:**
- `throttle:60,1` → Limits to 60 requests per minute
- `exponential.lockout:login` → Blocks repeat failed attempts

### Protected Route Groups

```php
Route::middleware(['web'])->group(function () {
    // All authentication routes protected
    Route::prefix('auth')->middleware(['exponential.lockout:login'])->group(function () {
        Route::post('/login', [LoginController::class, 'login']);
        Route::post('/register', [RegisterController::class, 'register']);
        Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    });
    
    // Verification routes with separate protection
    Route::prefix('verify')->middleware(['exponential.lockout:otp'])->group(function () {
        Route::post('/phone', [VerificationController::class, 'phone']);
        Route::post('/email', [VerificationController::class, 'email']);
    });
});
```

## 📊 Protection Status

### Headers Included

When someone is blocked, the response includes helpful headers:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 300
X-RateLimit-Limit: exponential
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1705329000
```

**What these mean:**
- `Retry-After: 300` → Wait 300 seconds (5 minutes)
- `X-RateLimit-Remaining: 0` → No attempts left
- `X-RateLimit-Reset: 1705329000` → Block ends at this timestamp

### JSON Response Format

```json
{
    "message": "Too many failed attempts. Please try again later.",
    "error": "lockout_active",
    "context": "login",
    "retry_after": 300,
    "locked_until": "2024-01-15T14:30:00.000000Z"
}
```

## 🎯 Best Practices

### ✅ Do This

1. **Use specific context names**
   ```php
   ->middleware('exponential.lockout:login')        // Good
   ->middleware('exponential.lockout:admin_login')  // Good
   ->middleware('exponential.lockout:otp')          // Good
   ```

2. **Match context to purpose**
   ```php
   // Login routes use 'login'
   Route::post('/login', ...)
       ->middleware('exponential.lockout:login');
   
   // OTP routes use 'otp'  
   Route::post('/verify-otp', ...)
       ->middleware('exponential.lockout:otp');
   ```

3. **Protect all authentication endpoints**
   ```php
   Route::post('/login', ...)->middleware('exponential.lockout:login');
   Route::post('/password/email', ...)->middleware('exponential.lockout:password_reset');
   Route::post('/verify-phone', ...)->middleware('exponential.lockout:otp');
   ```

### ❌ Don't Do This

1. **Don't use undefined contexts**
   ```php
   // BAD - 'custom_thing' not configured
   ->middleware('exponential.lockout:custom_thing')
   ```

2. **Don't mix different types**
   ```php
   // BAD - using login context for OTP
   Route::post('/verify-otp', ...)
       ->middleware('exponential.lockout:login');
   ```

3. **Don't forget to configure contexts**
   ```php
   // BAD - using context without configuration
   ->middleware('exponential.lockout:admin')
   // But 'admin' is not in config/exponential-lockout.php
   ```

## 🚨 Troubleshooting

### "Context not configured" Error

**Problem:** Using a context that doesn't exist in configuration.

**Solution:** 
1. Check `config/exponential-lockout.php`
2. Make sure your context is listed in the `contexts` section
3. Make sure `enabled` is set to `true`

### Protection Not Working

**Problem:** Users aren't getting blocked.

**Solutions:**
1. Check that middleware is applied to the correct route
2. Clear Laravel cache: `php artisan cache:clear`
3. Make sure the route is actually being used (check your forms)
4. Verify the context is enabled in configuration

### Wrong Response Format

**Problem:** Getting JSON when you want HTML, or vice versa.

**Solution:** Check the `response_mode` setting in your context configuration.

## 🎉 Success Tips

1. **Test your protection** - Try wrong passwords to see the blocking in action
2. **Monitor your logs** - Check if attacks are being blocked
3. **Adjust timing** - Change wait times based on your needs
4. **Use different contexts** - Don't mix login with OTP protection
5. **Clear cache after changes** - Run `php artisan cache:clear` after configuration changes

## 🚀 Next Steps

- **[Manual Control Guide](manual-control.md)** - Control blocking in your code
- **[Configuration Guide](configuration.md)** - Customize protection settings
- **[Command Line Tools](command-line-tools.md)** - Manage blocks from terminal
- **[Examples and Recipes](examples-and-recipes.md)** - Real-world solutions

## 🆘 Need Help?

- **[Troubleshooting Guide](troubleshooting.md)** - Common problems and solutions
- **Email Developer:** joe.nassar.tech@gmail.com

Your website is now much safer! 🔒