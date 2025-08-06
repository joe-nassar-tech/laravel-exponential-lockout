# Manual Control Guide

Sometimes you need to control the blocking system yourself in your code. This guide shows you how to do it with simple examples that anyone can understand.

## 🎯 What is Manual Control?

Manual control means you decide when to:
- Block someone
- Unblock someone  
- Check if someone is blocked
- Get information about blocks

It's like being the security guard yourself instead of having an automatic system.

## 🛠️ The Basic Commands

### Import the Controller

First, add this to the top of your PHP file:

```php
use ExponentialLockout\Facades\Lockout;
```

Now you can use `Lockout::` to control the blocking system.

## 🚫 Recording Failed Attempts

### Basic Usage

```php
// Someone tried wrong password for login
Lockout::recordFailure('login', 'user@example.com');

// Someone entered wrong OTP code
Lockout::recordFailure('otp', '+1234567890');

// Someone tried wrong admin password
Lockout::recordFailure('admin', 'admin@company.com');
```

**What this does:** Counts the failure and starts/extends the block if needed.

### Get the Attempt Count

```php
$attempts = Lockout::recordFailure('login', 'user@example.com');
echo "This is attempt number: $attempts";
```

## ✅ Clearing Blocks (Unblocking)

### Clear Specific User

```php
// User logged in successfully - remove their block
Lockout::clear('login', 'user@example.com');

// OTP verification succeeded - remove OTP block
Lockout::clear('otp', '+1234567890');
```

**Important:** Always clear blocks when someone succeeds!

### Clear All Blocks for a Type

```php
// Remove ALL login blocks (be careful!)
Lockout::clearContext('login');

// Remove ALL OTP blocks
Lockout::clearContext('otp');
```

**Warning:** This removes blocks for everyone. Only use when needed!

## 🔍 Checking Block Status

### Simple Check

```php
// Is this user blocked from logging in?
if (Lockout::isLockedOut('login', 'user@example.com')) {
    echo "User is blocked";
} else {
    echo "User can try again";
}
```

### Get Time Remaining

```php
$seconds = Lockout::getRemainingTime('login', 'user@example.com');

if ($seconds > 0) {
    $minutes = ceil($seconds / 60);
    echo "Please wait $minutes minutes";
}
```

### Get Attempt Count

```php
$attempts = Lockout::getAttemptCount('login', 'user@example.com');
echo "Failed attempts: $attempts";
```

## 📊 Getting Detailed Information

```php
$info = Lockout::getLockoutInfo('login', 'user@example.com');

echo "User: " . $info['key'];                  // user@example.com
echo "Context: " . $info['context'];           // login
echo "Attempts: " . $info['attempts'];         // 3
echo "Is blocked: " . $info['is_locked_out'];  // true/false
echo "Time left: " . $info['remaining_time'];  // 300 seconds
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
        
        // Check if user is already blocked
        if (Lockout::isLockedOut('login', $email)) {
            $timeLeft = Lockout::getRemainingTime('login', $email);
            $minutes = ceil($timeLeft / 60);
            
            return back()->withErrors([
                'email' => "Too many attempts. Please wait $minutes minutes."
            ]);
        }
        
        // Try to log in
        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            // Success! Clear any blocks
            Lockout::clear('login', $email);
            return redirect()->intended('dashboard');
        }
        
        // Login failed - record the attempt
        $attempts = Lockout::recordFailure('login', $email);
        
        return back()->withErrors([
            'email' => "Invalid credentials. Attempt: $attempts"
        ]);
    }
}
```

**What this does:**
1. Check if user is already blocked
2. If blocked, show wait time
3. If not blocked, try login
4. If login succeeds, clear blocks
5. If login fails, record failure

### Example 2: Phone Verification

```php
class PhoneController extends Controller
{
    public function verifyCode(Request $request)
    {
        $phone = $request->phone;
        $code = $request->code;
        
        // Check if phone number is blocked
        if (Lockout::isLockedOut('otp', $phone)) {
            return response()->json([
                'error' => 'Too many attempts',
                'retry_after' => Lockout::getRemainingTime('otp', $phone)
            ], 429);
        }
        
        // Check if code is correct
        if ($this->isValidCode($phone, $code)) {
            // Success! Clear blocks and verify phone
            Lockout::clear('otp', $phone);
            $this->markPhoneAsVerified($phone);
            
            return response()->json(['message' => 'Phone verified!']);
        }
        
        // Wrong code - record failure
        $attempts = Lockout::recordFailure('otp', $phone);
        
        return response()->json([
            'error' => 'Invalid code',
            'attempts_remaining' => max(0, 5 - $attempts)
        ], 400);
    }
}
```

### Example 3: Password Reset

```php
class PasswordResetController extends Controller
{
    public function sendResetEmail(Request $request)
    {
        $email = $request->email;
        
        // Check if email reset requests are blocked
        if (Lockout::isLockedOut('password_reset', $email)) {
            $info = Lockout::getLockoutInfo('password_reset', $email);
            
            return back()->withErrors([
                'email' => "Too many reset requests. " . 
                          "Please wait " . ceil($info['remaining_time'] / 60) . " minutes."
            ]);
        }
        
        // Find user
        $user = User::where('email', $email)->first();
        
        if ($user) {
            // User exists - send reset email
            $this->sendPasswordResetEmail($user);
            
            // Don't record failure for existing users
            return back()->with('status', 'Reset link sent!');
        }
        
        // User doesn't exist - record attempt to prevent email enumeration
        Lockout::recordFailure('password_reset', $email);
        
        // Still show success message for security
        return back()->with('status', 'Reset link sent if email exists!');
    }
}
```

## 🎨 User-Friendly Messages

### Convert Seconds to Human Time

```php
function formatWaitTime($seconds) {
    if ($seconds < 60) {
        return "$seconds seconds";
    } elseif ($seconds < 3600) {
        $minutes = ceil($seconds / 60);
        return "$minutes minutes";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = ceil(($seconds % 3600) / 60);
        if ($minutes > 0) {
            return "$hours hours and $minutes minutes";
        } else {
            return "$hours hours";
        }
    }
}

// Usage
$timeLeft = Lockout::getRemainingTime('login', $email);
echo "Please wait " . formatWaitTime($timeLeft);
```

### Show Remaining Attempts

```php
function getRemainingAttempts($context, $key) {
    // Get current attempts
    $attempts = Lockout::getAttemptCount($context, $key);
    
    // Most contexts allow 2 free attempts before blocking
    $freeAttempts = 2;
    $remaining = max(0, $freeAttempts - $attempts);
    
    return $remaining;
}

// Usage
$remaining = getRemainingAttempts('login', $email);
if ($remaining > 0) {
    echo "You have $remaining attempts remaining";
} else {
    echo "Account temporarily locked";
}
```

## 🔧 Advanced Techniques

### Conditional Blocking

```php
public function attemptLogin($email, $password)
{
    // Only block after business hours for extra security
    $isBusinessHours = now()->hour >= 9 && now()->hour <= 17;
    
    if (Auth::attempt(['email' => $email, 'password' => $password])) {
        Lockout::clear('login', $email);
        return true;
    }
    
    // Only record failures during business hours
    if ($isBusinessHours) {
        Lockout::recordFailure('login', $email);
    }
    
    return false;
}
```

### Smart Key Selection

```php
public function getIdentifier(Request $request)
{
    // Try different identifiers in order of preference
    if ($request->has('email')) {
        return $request->email;
    } elseif ($request->has('phone')) {
        return $request->phone;
    } elseif ($request->has('username')) {
        return $request->username;
    } else {
        // Fallback to IP address
        return $request->ip();
    }
}

// Usage
$identifier = $this->getIdentifier($request);
if (Lockout::isLockedOut('login', $identifier)) {
    // Handle blocking
}
```

### Multiple Context Checking

```php
public function isUserCompletelyBlocked($identifier)
{
    $contexts = ['login', 'password_reset', 'otp'];
    
    foreach ($contexts as $context) {
        if (Lockout::isLockedOut($context, $identifier)) {
            return true;
        }
    }
    
    return false;
}
```

## 📱 API-Friendly Responses

### Structured JSON Responses

```php
public function createLockoutResponse($context, $identifier)
{
    $info = Lockout::getLockoutInfo($context, $identifier);
    
    return response()->json([
        'success' => false,
        'error' => [
            'type' => 'rate_limit_exceeded',
            'message' => 'Too many failed attempts',
            'context' => $context,
            'details' => [
                'attempts_made' => $info['attempts'],
                'retry_after_seconds' => $info['remaining_time'],
                'retry_after_human' => formatWaitTime($info['remaining_time']),
                'locked_until' => $info['locked_until']?->toISOString(),
            ]
        ]
    ], 429);
}
```

### Consistent Error Handling

```php
trait HandlesLockouts
{
    protected function checkLockout($context, $identifier)
    {
        if (Lockout::isLockedOut($context, $identifier)) {
            return $this->createLockoutResponse($context, $identifier);
        }
        
        return null; // No lockout active
    }
    
    protected function handleFailedAttempt($context, $identifier, $customMessage = null)
    {
        $attempts = Lockout::recordFailure($context, $identifier);
        
        $message = $customMessage ?? 'Invalid credentials';
        
        return response()->json([
            'success' => false,
            'error' => [
                'type' => 'authentication_failed',
                'message' => $message,
                'attempts_made' => $attempts
            ]
        ], 401);
    }
}
```

## 🎯 Best Practices

### ✅ Do This

1. **Always clear on success**
   ```php
   if ($loginSuccessful) {
       Lockout::clear('login', $email);
   }
   ```

2. **Check before processing**
   ```php
   if (Lockout::isLockedOut('login', $email)) {
       return $this->lockoutResponse();
   }
   ```

3. **Give helpful feedback**
   ```php
   $timeLeft = Lockout::getRemainingTime('login', $email);
   return "Please wait " . ceil($timeLeft / 60) . " minutes";
   ```

4. **Use appropriate contexts**
   ```php
   Lockout::recordFailure('login', $email);     // For login
   Lockout::recordFailure('otp', $phone);      // For OTP
   ```

### ❌ Don't Do This

1. **Don't forget to clear blocks**
   ```php
   // BAD - successful login but no clear
   if ($loginSuccessful) {
       return redirect()->home();
   }
   ```

2. **Don't use wrong contexts**
   ```php
   // BAD - using login context for OTP
   Lockout::recordFailure('login', $phone);
   ```

3. **Don't ignore return values**
   ```php
   // BAD - not using attempt count
   Lockout::recordFailure('login', $email);
   
   // GOOD - showing attempt count
   $attempts = Lockout::recordFailure('login', $email);
   ```

## 🚨 Common Mistakes

### Mistake 1: Not Clearing Blocks

```php
// WRONG - blocks never get cleared
public function login($email, $password)
{
    if (Auth::attempt(['email' => $email, 'password' => $password])) {
        return redirect()->intended();
    }
    
    Lockout::recordFailure('login', $email);
    return back()->withErrors(['email' => 'Invalid credentials']);
}

// RIGHT - blocks get cleared on success
public function login($email, $password)
{
    if (Auth::attempt(['email' => $email, 'password' => $password])) {
        Lockout::clear('login', $email); // ← This is important!
        return redirect()->intended();
    }
    
    Lockout::recordFailure('login', $email);
    return back()->withErrors(['email' => 'Invalid credentials']);
}
```

### Mistake 2: Wrong Context Usage

```php
// WRONG - mixing contexts
Lockout::recordFailure('login', $phone);  // Phone number in login context
Lockout::recordFailure('otp', $email);   // Email in OTP context

// RIGHT - matching contexts to data
Lockout::recordFailure('login', $email); // Email for login
Lockout::recordFailure('otp', $phone);   // Phone for OTP
```

## 🎛️ Advanced: Custom Response Callbacks

For ultimate control, use callback responses to create custom lockout handling:

### Basic Callback Setup

```php
// config/exponential-lockout.php
'contexts' => [
    'login' => [
        'response_mode' => 'callback',
        'response_callback' => 'App\Http\Controllers\SecurityController@handleLockout',
    ],
],
```

### Advanced Callback Examples

```php
// app/Http/Controllers/SecurityController.php
public function handleLockout($request, $lockoutInfo)
{
    // Different handling based on attempt count
    $attempts = $lockoutInfo['attempts'];
    
    if ($attempts >= 10) {
        // Alert security team for suspicious activity
        Mail::to('security@company.com')->send(
            new SuspiciousActivityAlert($lockoutInfo)
        );
    }
    
    // VIP user special handling
    if ($this->isVipUser($lockoutInfo['key'])) {
        return response()->json([
            'message' => 'VIP account protection activated',
            'contact_support' => true,
            'retry_after' => $lockoutInfo['remaining_time']
        ], 429);
    }
    
    // Regular response
    return response()->json([
        'error' => 'Account locked for security',
        'retry_after' => $lockoutInfo['remaining_time'],
        'attempts_made' => $attempts,
    ], 429);
}
```

### Available Callback Data

```php
$lockoutInfo = [
    'key' => 'user@example.com',           // User identifier
    'context' => 'login',                  // Context name
    'attempts' => 4,                       // Failed attempts count
    'is_locked_out' => true,               // Current lock status
    'remaining_time' => 300,               // Seconds until unlock
    'locked_until' => Carbon::instance,    // Unlock timestamp
];
```

## 🎉 Success Tips

1. **Test everything** - Try wrong passwords to see blocking in action
2. **Monitor your logs** - Check if the system is working as expected
3. **Give clear messages** - Tell users exactly how long to wait
4. **Use consistent identifiers** - Don't mix email and phone for same context
5. **Clear blocks on success** - Always unblock when authentication succeeds
6. **Use callbacks wisely** - Great for logging, notifications, and custom logic

## 🚀 Next Steps

- **[Configuration Guide](configuration.md)** - Customize blocking behavior
- **[Command Line Tools](command-line-tools.md)** - Manage blocks from terminal
- **[Examples and Recipes](examples-and-recipes.md)** - More real-world examples
- **[Troubleshooting Guide](troubleshooting.md)** - Fix common problems

## 🆘 Need Help?

- **[Troubleshooting Guide](troubleshooting.md)** - Common problems and solutions
- **Email Developer:** joe.nassar.tech@gmail.com

You now have full control over your website's security! 🔒