# Configuration Guide

This guide explains how to customize the Laravel Exponential Lockout package for your specific needs. Everything is explained in simple terms with easy examples.

## 📁 Configuration File Location

After installation, your settings are stored in:
```
config/exponential-lockout.php
```

## 🎯 Basic Configuration Concepts

### What are "Contexts"?

Contexts are different types of protection. Think of them as different security systems for different areas:

- **login** - For user login pages
- **otp** - For phone/email verification codes  
- **password_reset** - For "forgot password" requests
- **admin** - For admin login pages

Each context can have different rules and timing.

## ⏰ Changing Wait Times

### Default Wait Times

```php
'default_delays' => [60, 300, 900, 1800, 7200, 21600, 43200, 86400],
```

**What this means:**
- 1st failure: 60 seconds (1 minute)
- 2nd failure: 300 seconds (5 minutes)
- 3rd failure: 900 seconds (15 minutes)
- 4th failure: 1800 seconds (30 minutes)
- 5th failure: 7200 seconds (2 hours)
- 6th failure: 21600 seconds (6 hours)
- 7th failure: 43200 seconds (12 hours)
- 8th+ failure: 86400 seconds (24 hours)

### Make Wait Times Shorter

```php
'default_delays' => [30, 60, 180, 300, 600],
```

**Result:** Shorter waits (30 sec → 1 min → 3 min → 5 min → 10 min)

### Make Wait Times Longer

```php
'default_delays' => [300, 900, 1800, 7200, 21600, 86400],
```

**Result:** Longer waits (5 min → 15 min → 30 min → 2 hr → 6 hr → 24 hr)

### Very Quick Protection (For Testing)

```php
'default_delays' => [5, 10, 20, 30],
```

**Result:** Very short waits (5 sec → 10 sec → 20 sec → 30 sec)

## 🎛️ Context-Specific Settings

### Basic Context Setup

```php
'contexts' => [
    'login' => [
        'enabled' => true,
        'key' => 'email',          // Looks for 'email' field in request
        'delays' => null,          // Uses default_delays
    ],
],
```

## 🔑 **Key Extractors (Input Field Requirements)**

The `'key'` setting tells the system which field to use for tracking users:

### Built-in Key Extractors

| Key Value | Primary Field | Fallback Fields | Use Case |
|-----------|---------------|-----------------|----------|
| `'email'` | `email` | `username` | Login, registration, password reset |
| `'phone'` | `phone` | `mobile`, `telephone` | OTP verification, SMS codes |
| `'username'` | `username` | `email` | Username-based login |
| `'ip'` | Client IP | None | Rate limiting by IP |

### Example Request Requirements

```php
// For 'key' => 'email'
{
  "email": "user@example.com",     // Required
  "password": "secret123"
}

// For 'key' => 'phone'  
{
  "phone": "+1234567890",          // Required
  "code": "123456"
}

// For 'key' => 'username'
{
  "username": "johndoe",           // Required  
  "password": "secret123"
}
```

### Context with Custom Wait Times

```php
'contexts' => [
    'otp' => [
        'enabled' => true,
        'key' => 'phone',         // Requires 'phone' field in request
        'delays' => [30, 60, 180, 300, 600], // Custom timing for OTP
    ],
],
```

### Multiple Contexts Example

```php
'contexts' => [
    // Regular user login - moderate protection
    'login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [60, 300, 900, 1800], // 1min → 5min → 15min → 30min
    ],
    
    // Admin login - stronger protection
    'admin' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [300, 900, 1800, 7200, 21600], // 5min → 15min → 30min → 2hr → 6hr
    ],
    
    // OTP verification - quick protection
    'otp' => [
        'enabled' => true,
        'key' => 'phone',
        'delays' => [30, 60, 120, 300], // 30sec → 1min → 2min → 5min
    ],
    
    // Password reset - prevent spam
    'password_reset' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [300, 600, 1800], // 5min → 10min → 30min
    ],
],
```

## 🔑 How Users Are Identified

### By Email (Default for Login)

```php
'login' => [
    'key' => 'email',
],
```

**What this does:** Tracks failures by the email address entered in the form.

### By Phone Number (For OTP)

```php
'otp' => [
    'key' => 'phone',
],
```

**What this does:** Tracks failures by the phone number entered.

### By IP Address (Anonymous)

```php
'anonymous_contact' => [
    'key' => 'ip',
],
```

**What this does:** Tracks failures by visitor's IP address (good for contact forms).

### By User ID (For Logged-in Users)

```php
'profile_update' => [
    'key' => 'user_id',
],
```

**What this does:** Tracks failures by the logged-in user's ID.

## 🎨 Response Customization

### Automatic Response (Recommended)

```php
'default_response_mode' => 'auto',
```

**What this does:**
- API calls (JSON) → Returns JSON error
- Web pages (HTML) → Redirects with error message

### Always JSON Response

```php
'contexts' => [
    'api_login' => [
        'response_mode' => 'json',
    ],
],
```

**Good for:** Mobile apps and API endpoints

### Always Redirect

```php
'contexts' => [
    'web_login' => [
        'response_mode' => 'redirect',
        'redirect_route' => 'login',
    ],
],
```

**Good for:** Traditional web forms

## 🗂️ Cache Settings

### Use Default Cache

```php
'cache' => [
    'store' => null, // Uses your app's default cache
    'prefix' => 'exponential_lockout',
],
```

### Use Specific Cache Store

```php
'cache' => [
    'store' => 'redis', // Use Redis specifically
    'prefix' => 'app_lockout',
],
```

### Use File Cache

```php
'cache' => [
    'store' => 'file',
    'prefix' => 'security_locks',
],
```

## 🎯 Real-World Configuration Examples

### E-commerce Website

```php
'contexts' => [
    // Customer login - balanced protection
    'login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [60, 300, 900, 1800, 7200],
    ],
    
    // Admin login - strong protection
    'admin' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [300, 900, 1800, 7200, 21600, 86400],
    ],
    
    // Payment verification - quick but limited
    'payment_verify' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [30, 60, 180, 300],
    ],
    
    // Contact form - prevent spam
    'contact' => [
        'enabled' => true,
        'key' => 'ip',
        'delays' => [300, 600, 1800],
    ],
],
```

### SaaS Application

```php
'contexts' => [
    // User login - standard protection
    'login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [60, 300, 900, 1800],
    ],
    
    // API authentication - strict protection
    'api_auth' => [
        'enabled' => true,
        'key' => 'api_key',
        'delays' => [60, 300, 900, 1800, 7200],
        'response_mode' => 'json',
    ],
    
    // Two-factor auth - quick protection
    '2fa' => [
        'enabled' => true,
        'key' => 'user_id',
        'delays' => [30, 60, 120, 300],
    ],
],
```

### Educational Platform

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
],
```

## 🔧 Advanced Settings

### Custom Key Extraction

```php
'key_extractors' => [
    'device_id' => function ($request) {
        return $request->header('Device-ID') ?: $request->ip();
    },
    'session' => function ($request) {
        return $request->session()->getId();
    },
],

'contexts' => [
    'mobile_login' => [
        'key' => 'device_id',
    ],
],
```

### Custom Response Callback

```php
'custom_response_callback' => function ($context, $key, $remainingTime) {
    return response()->json([
        'success' => false,
        'message' => 'Too many attempts for ' . $context,
        'wait_time_minutes' => ceil($remainingTime / 60),
        'try_again_at' => now()->addSeconds($remainingTime)->toISOString(),
    ], 429);
},

'contexts' => [
    'api_special' => [
        'response_mode' => 'callback',
    ],
],
```

## 🛡️ Security Levels

### Low Security (Testing/Development)

```php
'default_delays' => [5, 10, 20, 30], // Very short waits
'contexts' => [
    'login' => [
        'enabled' => true,
        'delays' => [5, 15, 30],
    ],
],
```

### Medium Security (Standard Websites)

```php
'default_delays' => [60, 300, 900, 1800, 7200], // Standard waits
```

### High Security (Financial/Healthcare)

```php
'default_delays' => [300, 900, 1800, 7200, 21600, 86400], // Long waits
'contexts' => [
    'login' => [
        'delays' => [600, 1800, 7200, 21600, 86400], // 10min → 30min → 2hr → 6hr → 24hr
    ],
],
```

### Maximum Security (Admin/Critical Systems)

```php
'default_delays' => [600, 1800, 7200, 21600, 86400, 172800], // Very long waits
'contexts' => [
    'admin' => [
        'delays' => [900, 1800, 7200, 21600, 86400, 172800], // 15min → 30min → 2hr → 6hr → 24hr → 48hr
    ],
],
```

## 🎛️ Environment-Specific Settings

### Development Environment

```php
// In your .env file
LOCKOUT_CACHE_STORE=array
LOCKOUT_CACHE_PREFIX=dev_lockout

// In config file
'cache' => [
    'store' => env('LOCKOUT_CACHE_STORE', null),
    'prefix' => env('LOCKOUT_CACHE_PREFIX', 'exponential_lockout'),
],

'default_delays' => [5, 10, 20], // Quick testing
```

### Production Environment

```php
// In your .env file
LOCKOUT_CACHE_STORE=redis
LOCKOUT_CACHE_PREFIX=prod_lockout

// In config file
'default_delays' => [300, 900, 1800, 7200, 21600, 86400], // Strong protection
```

## 📊 Monitoring and Headers

### Enable Response Headers

```php
'include_headers' => true,
```

**Result:** Responses include helpful headers like `Retry-After` and rate limit info.

### Custom HTTP Status Code

```php
'http_status_code' => 423, // Locked instead of 429 Too Many Requests
```

## 🔄 Disable Specific Contexts

### Temporarily Disable

```php
'contexts' => [
    'login' => [
        'enabled' => false, // Temporarily turn off login protection
    ],
],
```

### Enable Only in Production

```php
'contexts' => [
    'login' => [
        'enabled' => app()->environment('production'),
    ],
],
```

## 🎯 Testing Your Configuration

### Test Settings

```php
'contexts' => [
    'test_login' => [
        'enabled' => true,
        'key' => 'email',
        'delays' => [5, 10, 15], // Very short for testing
    ],
],
```

### How to Test

1. **Try wrong password 4 times rapidly**
2. **You should be blocked after the 3rd attempt**
3. **Wait the specified time and try again**
4. **Login successfully to clear the block**

## 🚨 Common Configuration Mistakes

### ❌ Don't Do This

1. **Zero delays**
   ```php
   'delays' => [0, 0, 0], // Bad - no protection
   ```

2. **Too many contexts**
   ```php
   // Bad - too complex
   'contexts' => [
       'login1', 'login2', 'login3', 'login4', 'login5'...
   ],
   ```

3. **Wrong key types**
   ```php
   'login' => [
       'key' => 'phone', // Bad - login usually uses email
   ],
   ```

### ✅ Do This

1. **Reasonable delays**
   ```php
   'delays' => [60, 300, 900], // Good - meaningful protection
   ```

2. **Simple contexts**
   ```php
   'contexts' => [
       'login' => [...],
       'otp' => [...],
       'admin' => [...],
   ],
   ```

3. **Matching keys**
   ```php
   'login' => ['key' => 'email'],  // Good match
   'otp' => ['key' => 'phone'],    // Good match
   ```

## 🎉 Configuration Tips

1. **Start simple** - Begin with default settings and adjust as needed
2. **Test thoroughly** - Always test your configuration changes
3. **Monitor usage** - Check if protection is working as expected
4. **Adjust gradually** - Make small changes and observe results
5. **Document changes** - Keep notes on why you changed settings

## 📝 Configuration Backup

Always backup your configuration before making changes:

```bash
cp config/exponential-lockout.php config/exponential-lockout.php.backup
```

## 🚀 Next Steps

- **[Command Line Tools](command-line-tools.md)** - Manage configuration from terminal
- **[Manual Control Guide](manual-control.md)** - Control in your code
- **[Examples and Recipes](examples-and-recipes.md)** - More configuration examples
- **[Troubleshooting Guide](troubleshooting.md)** - Fix configuration problems

## 🆘 Need Help?

- **[Troubleshooting Guide](troubleshooting.md)** - Common configuration problems
- **Email Developer:** joe.nassar.tech@gmail.com

Your configuration is now customized for your needs! 🔧