# Basic Usage Guide

This guide shows you simple examples of how to use Laravel Exponential Lockout in your website. All examples are written in easy-to-understand language.

## 📚 What You'll Learn

- How to protect different pages (fully automatic)
- What request fields are required
- How to check if someone is blocked
- How to unblock users
- How to get information about blocks

## ✨ **NEW: Automatic Operation**

**Great news!** The lockout system is now **100% automatic**:
- ✅ **No manual code needed** in your controllers
- ✅ **Automatically detects failures** from HTTP status codes
- ✅ **Automatically clears lockouts** when login succeeds
- ✅ **Works with ANY error response** your app returns

## 🛡️ Protecting Different Pages

### Protect Login Page

```php
// In routes/web.php or routes/api.php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');
```

**Required request field:**
```json
{
  "email": "user@example.com",
  "password": "secret123"
}
```

**What this does:** 
- Automatically blocks users who get 401/422 errors (wrong passwords)
- Automatically unblocks when user gets 200 success response
- Tracks by email address

### Protect Password Reset

```php
Route::post('/password/reset', [PasswordController::class, 'reset'])
    ->middleware('exponential.lockout:password_reset');
```

**Required request field:**
```json
{
  "email": "user@example.com"
}
```

**What this does:** 
- Stops people from spamming password reset requests
- Tracks by email address

### Protect Phone Verification (OTP)

```php
Route::post('/verify-phone', [PhoneController::class, 'verify'])
    ->middleware('exponential.lockout:otp');
```

**Required request field:**
```json
{
  "phone": "+1234567890",
  "code": "123456"
}
```

**What this does:** 
- Prevents people from guessing phone verification codes
- Tracks by phone number

## 🎮 Manual Control in Your Code

Sometimes you want to control the blocking yourself. Here's how:

### Check if User is Blocked

```php
use ExponentialLockout\Facades\Lockout;

// Check if an email is blocked from logging in
if (Lockout::isLockedOut('login', 'user@example.com')) {
    return response()->json(['error' => 'Please wait before trying again']);
}
```

### Record a Failed Attempt

```php
// When someone enters wrong password
Lockout::recordFailure('login', 'user@example.com');
```

### Clear a Block (Unblock Someone)

```php
// When someone logs in successfully
Lockout::clear('login', 'user@example.com');
```

### Get Block Information

```php
// Get detailed information about a block
$info = Lockout::getLockoutInfo('login', 'user@example.com');

// $info contains:
// - How many attempts were made
// - If they're currently blocked
// - How much time is left
// - When the last attempt was made
```

## 🎯 Real-World Examples

### Example 1: Login Controller

```php
class LoginController extends Controller
{
    public function login(Request $request)
    {
        $email = $request->email;
        $password = $request->password;
        
        // Try to log the user in
        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            // Success! Clear any blocks
            Lockout::clear('login', $email);
            return redirect()->intended('dashboard');
        }
        
        // Login failed - record the attempt
        $attempts = Lockout::recordFailure('login', $email);
        
        return back()->withErrors([
            'email' => "Wrong password. Attempt: $attempts"
        ]);
    }
}
```

**What this does:**
- If login succeeds → Remove any blocks
- If login fails → Count the failure
- Show user how many attempts they've made

### Example 2: Phone Verification

```php
class PhoneController extends Controller
{
    public function verify(Request $request)
    {
        $phone = $request->phone;
        $code = $request->code;
        
        // Check if the verification code is correct
        if ($this->isValidCode($phone, $code)) {
            // Success! Clear blocks
            Lockout::clear('otp', $phone);
            return response()->json(['message' => 'Phone verified!']);
        }
        
        // Wrong code - record failure
        Lockout::recordFailure('otp', $phone);
        
        return response()->json([
            'error' => 'Wrong verification code'
        ], 400);
    }
}
```

### Example 3: Password Reset

```php
class PasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $email = $request->email;
        
        // Check if user exists
        $user = User::where('email', $email)->first();
        
        if ($user) {
            // User exists - send email and clear blocks
            Mail::to($user)->send(new PasswordResetMail());
            Lockout::clear('password_reset', $email);
            
            return response()->json(['message' => 'Reset link sent!']);
        }
        
        // User doesn't exist - record attempt
        Lockout::recordFailure('password_reset', $email);
        
        return response()->json([
            'error' => 'Email not found'
        ], 404);
    }
}
```

## 📊 Understanding Block Status

### Get Simple Status

```php
// Just check if blocked (true/false)
$isBlocked = Lockout::isLockedOut('login', 'user@example.com');

// Get time remaining (in seconds)
$timeLeft = Lockout::getRemainingTime('login', 'user@example.com');

// Get number of failed attempts
$attempts = Lockout::getAttemptCount('login', 'user@example.com');
```

### Get Detailed Information

```php
$info = Lockout::getLockoutInfo('login', 'user@example.com');

echo "User: " . $info['key'];                    // user@example.com
echo "Failed attempts: " . $info['attempts'];     // 3
echo "Is blocked: " . $info['is_locked_out'];     // true/false
echo "Time left: " . $info['remaining_time'];     // 300 (seconds)
echo "Blocked until: " . $info['locked_until'];   // 2024-01-15 14:30:00
```

## 🎨 Showing Messages to Users

### Simple Message

```php
if (Lockout::isLockedOut('login', $request->email)) {
    $timeLeft = Lockout::getRemainingTime('login', $request->email);
    $minutes = ceil($timeLeft / 60);
    
    return back()->withErrors([
        'email' => "Too many attempts. Please wait $minutes minutes."
    ]);
}
```

### Detailed Message

```php
$info = Lockout::getLockoutInfo('login', $request->email);

if ($info['is_locked_out']) {
    $timeLeft = $info['remaining_time'];
    $hours = floor($timeLeft / 3600);
    $minutes = floor(($timeLeft % 3600) / 60);
    
    $message = "Account locked. ";
    if ($hours > 0) {
        $message .= "Please wait $hours hours and $minutes minutes.";
    } else {
        $message .= "Please wait $minutes minutes.";
    }
    
    return back()->withErrors(['email' => $message]);
}
```

## 🔧 Useful Helper Functions

### Convert Seconds to Human Time

```php
function formatTime($seconds) {
    if ($seconds < 60) {
        return "$seconds seconds";
    } elseif ($seconds < 3600) {
        $minutes = ceil($seconds / 60);
        return "$minutes minutes";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "$hours hours and $minutes minutes";
    }
}

// Usage
$timeLeft = Lockout::getRemainingTime('login', $email);
echo "Please wait " . formatTime($timeLeft);
```

### Check Multiple Contexts

```php
function isUserBlocked($identifier) {
    $contexts = ['login', 'password_reset', 'otp'];
    
    foreach ($contexts as $context) {
        if (Lockout::isLockedOut($context, $identifier)) {
            return true;
        }
    }
    
    return false;
}
```

## 🎉 Tips for Success

1. **Always clear blocks on success** - When login/verification succeeds, call `Lockout::clear()`

2. **Give helpful messages** - Tell users how long they need to wait

3. **Use different contexts** - Don't mix login blocks with OTP blocks

4. **Test your protection** - Try wrong passwords to make sure blocking works

5. **Monitor your logs** - Check if the protection is working as expected

## 🚨 Common Mistakes to Avoid

❌ **Don't forget to clear blocks**
```php
// BAD - blocks never get cleared
if (Auth::attempt($credentials)) {
    return redirect()->intended();
}
```

✅ **Always clear on success**
```php
// GOOD - blocks get cleared on success
if (Auth::attempt($credentials)) {
    Lockout::clear('login', $request->email);
    return redirect()->intended();
}
```

❌ **Don't use wrong context names**
```php
// BAD - context doesn't exist in config
Lockout::recordFailure('wrong_name', $email);
```

✅ **Use configured context names**
```php
// GOOD - using configured context
Lockout::recordFailure('login', $email);
```

## 🎊 Next Steps

Now that you understand basic usage, you can:

1. **[Learn Middleware Protection](middleware-protection.md)** - Automatic protection
2. **[Customize Configuration](configuration.md)** - Adjust settings
3. **[Use Command Line Tools](command-line-tools.md)** - Manage from terminal
4. **[See More Examples](examples-and-recipes.md)** - Real-world solutions

## 🆘 Need Help?

- **[Troubleshooting Guide](troubleshooting.md)** - Common problems
- **Email Developer:** joe.nassar.tech@gmail.com

Happy coding! 🚀