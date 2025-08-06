# Middleware Protection Guide

Middleware protection is the easiest way to secure your website pages. Just add one line of code and your page is protected! This guide explains everything in simple terms.

## 🎯 What is Middleware Protection?

Think of middleware as a security guard that checks everyone before they enter a room:

- **Good visitors** → Let them through normally
- **Suspicious visitors** → Ask them to wait outside
- **Repeat troublemakers** → Block them for longer periods

## 🛡️ How to Add Protection

### Basic Syntax

```php
->middleware('exponential.lockout:CONTEXT_NAME')
```

**CONTEXT_NAME** is like a label that tells the system what kind of protection this is (login, otp, password reset, etc.)

## 📝 Step-by-Step Examples

### 1. Protect Login Page

**Find your login route** (usually in `routes/web.php`):

```php
// BEFORE (no protection)
Route::post('/login', [LoginController::class, 'login']);

// AFTER (with protection)
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');
```

**What happens:**
- First wrong password → No blocking
- Second wrong password → No blocking  
- Third wrong password → Wait 1 minute
- Fourth wrong password → Wait 5 minutes
- And so on...

### 2. Protect Password Reset

```php
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->middleware('exponential.lockout:password_reset');
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