# Troubleshooting Guide

Having problems with Laravel Exponential Lockout? This guide helps you find and fix common issues quickly. Everything is explained in simple terms with step-by-step solutions.

## 🚨 Quick Problem Solver

### Is it working at all?

1. **Try wrong password 3 times** - Are you getting blocked?
2. **Check error messages** - What exactly do you see?
3. **Look at Laravel logs** - Any error messages there?

### Most Common Issues

- [Protection not working](#-protection-not-working)
- [Command not found](#-command-not-found-errors)
- [Wrong response format](#-wrong-response-format)
- [Configuration errors](#-configuration-errors)
- [Cache problems](#-cache-problems)

## 🛡️ Protection Not Working

### Problem: Users aren't getting blocked

**Symptoms:**
- Can try wrong password many times
- No blocking message appears
- No waiting time enforced

**Solutions:**

#### 1. Check Middleware is Applied

**Look for this in your routes:**
```php
// GOOD - middleware is applied
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');

// BAD - no middleware
Route::post('/login', [LoginController::class, 'login']);
```

**Fix:** Add the middleware to your route.

#### 2. Verify Context is Enabled

**Check `config/exponential-lockout.php`:**
```php
'contexts' => [
    'login' => [
        'enabled' => true, // ← Make sure this is true
    ],
],
```

**Fix:** Set `enabled` to `true`.

#### 3. Clear Laravel Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

**Why:** Laravel might be using old cached configuration.

#### 4. Check Route is Being Used

**Test:** Add a simple log to your controller:
```php
public function login(Request $request)
{
    \Log::info('Login attempt for: ' . $request->email);
    // ... rest of your code
}
```

**Check logs:** Look for the log message when you try to login.

#### 5. Verify Context Name Matches

**In routes:**
```php
->middleware('exponential.lockout:login')
```

**In config:**
```php
'contexts' => [
    'login' => [...], // ← Names must match exactly
],
```

### Problem: Blocking too aggressive

**Symptoms:**
- Users blocked after 1 attempt
- Blocking lasts too long
- Everyone gets blocked

**Solutions:**

#### 1. Check Delay Configuration

```php
'contexts' => [
    'login' => [
        'delays' => [5, 10, 20], // ← Too short for production
    ],
],
```

**Fix:** Use reasonable delays like `[60, 300, 900]`.

#### 2. Check Default Delays

```php
'default_delays' => [1, 2, 3], // ← Too aggressive
```

**Fix:** Use standard delays like `[60, 300, 900, 1800]`.

## 💻 Command Not Found Errors

### Problem: `php artisan lockout:clear` doesn't work

**Error message:**
```
Command "lockout:clear" is not defined.
```

**Solutions:**

#### 1. Check Package Installation

```bash
composer show joe-nassar-tech/laravel-exponential-lockout
```

**Expected:** Should show package information
**If error:** Package not installed, run:
```bash
composer require joe-nassar-tech/laravel-exponential-lockout
```

#### 2. Check Service Provider Registration

**Look in `config/app.php`:**
```php
'providers' => [
    // Should be auto-registered, but if not, add:
    ExponentialLockout\ExponentialLockoutServiceProvider::class,
],
```

#### 3. Clear Application Cache

```bash
php artisan clear-compiled
php artisan config:clear
php artisan cache:clear
```

#### 4. Check Laravel Version

**Requirement:** Laravel 9.0 or higher

**Check your version:**
```bash
php artisan --version
```

**If too old:** Upgrade Laravel or use compatible version.

## 📱 Wrong Response Format

### Problem: Getting JSON when expecting HTML redirect

**Symptoms:**
- Login form shows JSON response
- No proper error message on form
- Raw JSON displayed to user

**Solutions:**

#### 1. Check Response Mode

**In config:**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'json', // ← Change this
    ],
],
```

**Fix:** Change to `'auto'` or `'redirect'`.

#### 2. Check Default Response Mode

```php
'default_response_mode' => 'auto', // ← Should be 'auto' for web forms
```

#### 3. Check Request Headers

**Problem:** Your form might be sending JSON headers

**Fix:** Make sure your form sends normal form data:
```html
<form method="POST" action="/login">
    <!-- NOT: Content-Type: application/json -->
</form>
```

### Problem: Getting redirect when expecting JSON

**Symptoms:**
- API calls get redirected
- Mobile app receives HTML instead of JSON
- AJAX calls fail

**Solutions:**

#### 1. Set Response Mode to JSON

```php
'contexts' => [
    'api_login' => [
        'response_mode' => 'json',
    ],
],
```

#### 2. Check Request Headers

**Make sure API calls include:**
```javascript
headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
}
```

## ⚙️ Configuration Errors

### Problem: "Context not configured" error

**Error message:**
```
Lockout context 'login' is not configured.
```

**Solutions:**

#### 1. Check Context Exists

**In `config/exponential-lockout.php`:**
```php
'contexts' => [
    'login' => [          // ← Context must be here
        'enabled' => true,
    ],
],
```

#### 2. Check Context Name Spelling

**In middleware:**
```php
->middleware('exponential.lockout:login')
//                                 ^^^^^ Must match exactly
```

#### 3. Publish Configuration

```bash
php artisan vendor:publish --tag=exponential-lockout-config
```

### Problem: "Undefined function env" error

**Error in config file:**
```
Call to undefined function env()
```

**Solution:** Config is being cached. Clear it:
```bash
php artisan config:clear
```

**Prevention:** Don't use `env()` in config files except in published config.

## 🗄️ Cache Problems

### Problem: Changes not taking effect

**Symptoms:**
- Changed configuration but nothing happens
- Old behavior persists
- New contexts not working

**Solutions:**

#### 1. Clear All Caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### 2. Check Cache Store

**In config:**
```php
'cache' => [
    'store' => 'redis', // ← Is this cache store working?
],
```

**Test cache store:**
```bash
php artisan tinker
>>> Cache::store('redis')->put('test', 'value', 60);
>>> Cache::store('redis')->get('test');
```

#### 3. Check Cache Permissions

**For file cache:**
```bash
# Check storage directory permissions
ls -la storage/framework/cache/
```

**Fix permissions:**
```bash
chmod -R 775 storage/framework/cache/
chown -R www-data:www-data storage/framework/cache/
```

### Problem: Redis connection issues

**Error:**
```
Connection refused [tcp://127.0.0.1:6379]
```

**Solutions:**

#### 1. Check Redis is Running

```bash
redis-cli ping
```

**Expected response:** `PONG`

#### 2. Use Different Cache Store

**Temporarily switch to file cache:**
```php
'cache' => [
    'store' => 'file',
],
```

#### 3. Check Redis Configuration

**In `.env`:**
```
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 🔑 Key Extraction Issues

### Problem: Wrong users getting blocked

**Symptoms:**
- IP address getting blocked instead of email
- Multiple users affected by one block
- Blocks not specific to users

**Solutions:**

#### 1. Check Key Configuration

```php
'contexts' => [
    'login' => [
        'key' => 'email', // ← Make sure this matches your form field
    ],
],
```

#### 2. Check Form Field Names

**Your form:**
```html
<input type="email" name="email" required>
<!--                       ^^^^^ Must match key config -->
```

#### 3. Check Custom Key Extractor

```php
'key_extractors' => [
    'email' => function ($request) {
        return $request->input('email') ?: $request->ip();
        //                     ^^^^^ Must match form field
    },
],
```

## 🎯 Middleware Issues

### Problem: Middleware not executing

**Symptoms:**
- No blocking happening
- Middleware seems to be ignored
- Other middleware works fine

**Solutions:**

#### 1. Check Middleware Registration

**Look in `app/Http/Kernel.php`:**
```php
protected $routeMiddleware = [
    // Should be auto-registered, but check if it's here:
    'exponential.lockout' => \ExponentialLockout\Middleware\ExponentialLockout::class,
];
```

#### 2. Check Middleware Order

```php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware(['web', 'exponential.lockout:login']);
    //           ^^^^^^ ^^^^^^^^^^^^^^^^^^^^^^^^^^
    //           Order can matter
```

#### 3. Test Middleware Directly

**Add debug logging:**
```php
Route::post('/test', function () {
    return 'Test route reached';
})->middleware('exponential.lockout:login');
```

## 📊 Debugging Tools

### Enable Debug Mode

**In `.env`:**
```
APP_DEBUG=true
LOG_LEVEL=debug
```

### Add Custom Logging

**In your controller:**
```php
use ExponentialLockout\Facades\Lockout;

public function login(Request $request)
{
    $email = $request->email;
    
    // Debug logging
    \Log::info('Lockout debug', [
        'email' => $email,
        'is_locked' => Lockout::isLockedOut('login', $email),
        'attempts' => Lockout::getAttemptCount('login', $email),
        'remaining_time' => Lockout::getRemainingTime('login', $email),
    ]);
    
    // ... rest of your code
}
```

### Check Cache Contents

```bash
php artisan tinker
>>> use ExponentialLockout\Facades\Lockout;
>>> $info = Lockout::getLockoutInfo('login', 'test@example.com');
>>> dd($info);
```

## 🚨 Emergency Fixes

### Emergency: Disable All Protection

**Temporarily disable in config:**
```php
'contexts' => [
    'login' => [
        'enabled' => false,
    ],
    'otp' => [
        'enabled' => false,
    ],
],
```

### Emergency: Clear All Blocks

```bash
php artisan lockout:clear login --all --force
php artisan lockout:clear otp --all --force
php artisan lockout:clear admin --all --force
```

### Emergency: Use Different Cache

**Switch to array cache (temporary):**
```php
'cache' => [
    'store' => 'array', // ← Data lost after request
],
```

## 🔍 Common Error Messages

### "Class 'ExponentialLockout\Facades\Lockout' not found"

**Problem:** Package not properly installed or autoloaded

**Solutions:**
1. `composer dump-autoload`
2. Check package is in `composer.json`
3. Reinstall: `composer require joe-nassar-tech/laravel-exponential-lockout`

### "Call to undefined method isLockedOut()"

**Problem:** Using wrong class or method

**Fix:** Make sure you're using:
```php
use ExponentialLockout\Facades\Lockout;

Lockout::isLockedOut('login', $email);
```

### "Undefined index: contexts"

**Problem:** Configuration not published or corrupted

**Fix:**
```bash
php artisan vendor:publish --tag=exponential-lockout-config --force
```

## 📝 Diagnostic Checklist

When something isn't working, check these in order:

- [ ] Package installed correctly (`composer show joe-nassar-tech/laravel-exponential-lockout`)
- [ ] Configuration published (`config/exponential-lockout.php` exists)
- [ ] Context is configured and enabled
- [ ] Middleware applied to correct routes
- [ ] Route names match context names exactly
- [ ] Form field names match key configuration
- [ ] Cache is working (test with simple cache operations)
- [ ] Laravel logs show no errors
- [ ] All caches cleared after changes

## 🎯 Prevention Tips

### Development Best Practices

1. **Always test after installation**
2. **Use version control for config files**
3. **Clear cache after configuration changes**
4. **Monitor Laravel logs during testing**
5. **Test with realistic user scenarios**

### Production Deployment

1. **Test in staging environment first**
2. **Have rollback plan ready**
3. **Monitor application after deployment**
4. **Keep emergency unblock commands ready**
5. **Document configuration decisions**

## 🆘 Still Need Help?

### Before Contacting Support

1. **Check Laravel logs:** `storage/logs/laravel.log`
2. **Try the emergency fixes above**
3. **Gather diagnostic information:**
   - Laravel version
   - PHP version
   - Package version
   - Configuration file
   - Error messages
   - Steps to reproduce

### Contact Information

**Developer:** Joe Nassar  
**Email:** joe.nassar.tech@gmail.com

**When contacting, please include:**
1. Description of the problem
2. What you expected to happen
3. What actually happened
4. Error messages (complete text)
5. Your configuration file
6. Steps to reproduce the issue

## 🚀 Next Steps

Once you've solved your problem:

- **[Examples and Recipes](examples-and-recipes.md)** - See more implementation examples
- **[Configuration Guide](configuration.md)** - Fine-tune your settings
- **[Command Line Tools](command-line-tools.md)** - Master the management commands

Your security system should now be working perfectly! 🔒