# Response Modes Guide - Complete Examples

This guide covers all available response modes in Laravel Exponential Lockout with real-world examples and use cases.

## 🎯 **Available Response Modes**

1. **`'auto'`** - Automatically detects JSON vs HTML requests
2. **`'json'`** - Always returns JSON responses
3. **`'redirect'`** - Always redirects with flash messages
4. **`'callback'`** - Custom callback function for complete control

---

## 🚀 **1. Auto Mode (Recommended)**

### **Configuration**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'auto',  // ← Automatically detects request type
        'redirect_route' => 'login', // ← Used for web requests
    ],
],
```

### **How It Works**
- **API/Mobile requests** → Returns JSON response
- **Web browser requests** → Redirects with flash errors
- **Automatic detection** based on `Accept` header and request type

### **Example Responses**

**API Request (JSON):**
```json
{
    "error": "Too many failed attempts",
    "context": "login",
    "retry_after": 300,
    "locked_until": "2024-01-15T14:30:00Z"
}
```

**Web Request (Redirect):**
```php
// Redirects to login page with flash message
return redirect()->route('login')
    ->withErrors(['email' => 'Too many attempts. Please wait 5 minutes.']);
```

### **Use Cases**
- ✅ **Mixed applications** (web + API)
- ✅ **Mobile apps** with web admin
- ✅ **SPA applications** with API backend
- ✅ **Quick setup** without configuration

---

## 📱 **2. JSON Mode**

### **Configuration**
```php
'contexts' => [
    'api_login' => [
        'response_mode' => 'json',
    ],
    'mobile_auth' => [
        'response_mode' => 'json',
    ],
],
```

### **Example Response**
```json
{
    "success": false,
    "error": "Account temporarily locked",
    "error_code": "ACCOUNT_LOCKED",
    "retry_after": 300,
    "retry_after_minutes": 5,
    "attempts_made": 4,
    "locked_until": "2024-01-15T14:30:00Z",
    "context": "api_login"
}
```

### **Use Cases**
- ✅ **REST APIs**
- ✅ **Mobile applications**
- ✅ **Single Page Applications (SPA)**
- ✅ **Webhook endpoints**
- ✅ **Microservices**

### **Real-World Example**
```php
// API Authentication Controller
class ApiAuthController extends Controller
{
    public function login(Request $request)
    {
        // Middleware automatically handles lockout
        // Returns JSON for API requests
        
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if (Auth::attempt($credentials)) {
            return response()->json([
                'success' => true,
                'user' => Auth::user(),
                'token' => $user->createToken('API')->plainTextToken,
            ]);
        }
        
        // Returns 401 JSON automatically
        return response()->json(['error' => 'Invalid credentials'], 401);
    }
}

// Route with JSON response mode
Route::post('/api/login', [ApiAuthController::class, 'login'])
    ->middleware('exponential.lockout:api_login');
```

---

## 🌐 **3. Redirect Mode**

### **Configuration**
```php
'contexts' => [
    'web_login' => [
        'response_mode' => 'redirect',
        'redirect_route' => 'login',
    ],
    'admin_login' => [
        'response_mode' => 'redirect',
        'redirect_route' => 'admin.login',
    ],
],
```

### **Example Response**
```php
// Redirects to login page with flash message
return redirect()->route('login')
    ->withErrors(['email' => 'Too many attempts. Please wait 5 minutes.'])
    ->withInput($request->only(['email'])); // Preserves email field
```

### **Blade Template Display**
```php
{{-- resources/views/login.blade.php --}}
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@error('email')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

{{-- Preserve email input --}}
<input type="email" name="email" value="{{ old('email') }}" />
```

### **Use Cases**
- ✅ **Traditional web forms**
- ✅ **Multi-page applications**
- ✅ **Server-rendered views**
- ✅ **Admin panels**
- ✅ **Contact forms**

### **Real-World Example**
```php
// Web Login Controller
class WebLoginController extends Controller
{
    public function login(Request $request)
    {
        // Middleware automatically handles lockout
        // Redirects for web requests
        
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if (Auth::attempt($credentials)) {
            return redirect()->intended('/dashboard');
        }
        
        // Returns 422 redirect automatically
        return back()->withErrors(['email' => 'Invalid credentials']);
    }
}

// Route with redirect response mode
Route::post('/login', [WebLoginController::class, 'login'])
    ->middleware('exponential.lockout:web_login');
```

---

## 🎛️ **4. Callback Mode (Advanced)**

### **Configuration**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'callback',
        'response_callback' => 'App\Http\Controllers\SecurityController@handleLockout',
    ],
    'api' => [
        'response_mode' => 'callback',
        'response_callback' => function($request, $lockoutInfo) {
            // Inline callback function
            return response()->json([
                'error' => 'API_RATE_LIMITED',
                'retry_after' => $lockoutInfo['remaining_time'],
            ], 429);
        },
    ],
],
```

### **Available Callback Data**
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

---

## 🔧 **Callback Use Cases & Examples**

### **1. VIP User Handling**
```php
'response_callback' => function($request, $lockoutInfo) {
    $email = $lockoutInfo['key'];
    $user = User::where('email', $email)->first();
    
    if ($user && $user->is_vip) {
        return response()->json([
            'error' => 'VIP account protection activated',
            'message' => 'Your account is temporarily locked for enhanced security',
            'retry_after' => $lockoutInfo['remaining_time'],
            'vip_support' => true,
            'priority_contact' => 'vip-support@company.com'
        ], 429);
    }
    
    return response()->json([
        'error' => 'Account temporarily locked',
        'retry_after' => $lockoutInfo['remaining_time'],
    ], 429);
},
```

### **2. Security Logging & Alerting**
```php
'response_callback' => function($request, $lockoutInfo) {
    // Log security event
    Log::warning('User locked out', [
        'email' => $lockoutInfo['key'],
        'ip' => $request->ip(),
        'attempts' => $lockoutInfo['attempts'],
        'user_agent' => $request->userAgent(),
    ]);
    
    // Alert security team for high attempts
    if ($lockoutInfo['attempts'] >= 10) {
        Mail::to('security@company.com')->send(
            new SuspiciousActivityAlert($lockoutInfo)
        );
    }
    
    // Send notification to user
    Mail::to($lockoutInfo['key'])->send(
        new AccountLockedNotification($lockoutInfo['remaining_time'])
    );
    
    return response()->json([
        'error' => 'Account locked for security',
        'retry_after' => $lockoutInfo['remaining_time'],
    ], 429);
},
```

### **3. Different Responses Based on Attempt Count**
```php
'response_callback' => function($request, $lockoutInfo) {
    $attempts = $lockoutInfo['attempts'];
    $timeLeft = $lockoutInfo['remaining_time'];
    
    if ($attempts <= 5) {
        return response()->json([
            'error' => 'Too many login attempts',
            'retry_after' => $timeLeft,
            'severity' => 'low'
        ], 429);
    } elseif ($attempts <= 10) {
        return response()->json([
            'error' => 'Multiple failed login attempts detected',
            'retry_after' => $timeLeft,
            'severity' => 'medium',
            'contact_support' => true
        ], 429);
    } else {
        return response()->json([
            'error' => 'Account flagged for security review',
            'retry_after' => $timeLeft,
            'severity' => 'high',
            'contact_support' => true,
            'security_alert' => true
        ], 429);
    }
},
```

### **4. Context-Specific Responses**
```php
'response_callback' => function($request, $lockoutInfo) {
    $context = $lockoutInfo['context'];
    $timeLeft = $lockoutInfo['remaining_time'];
    
    switch ($context) {
        case 'login':
            return response()->json([
                'error' => 'Login temporarily disabled',
                'retry_after' => $timeLeft,
            ], 429);
            
        case 'otp':
            return response()->json([
                'error' => 'OTP verification locked',
                'message' => 'Too many invalid codes entered',
                'retry_after' => $timeLeft,
            ], 429);
            
        case 'admin':
            return response()->json([
                'error' => 'Admin access temporarily blocked',
                'retry_after' => $timeLeft,
                'security_alert' => true,
            ], 429);
            
        default:
            return response()->json([
                'error' => 'Access temporarily blocked',
                'retry_after' => $timeLeft,
            ], 429);
    }
},
```

### **5. Mixed JSON/Redirect Based on Request Type**
```php
'response_callback' => function($request, $lockoutInfo) {
    $timeLeft = $lockoutInfo['remaining_time'];
    
    // Check if it's an API request
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json([
            'error' => 'Account locked',
            'retry_after' => $timeLeft,
        ], 429);
    }
    
    // Web request - redirect with flash message
    return redirect()->route('login')
        ->withErrors(['email' => "Too many attempts. Wait " . ceil($timeLeft/60) . " minutes."])
        ->withInput($request->only(['email']));
},
```

### **6. Integration with External Services**
```php
'response_callback' => function($request, $lockoutInfo) {
    // Send to external security service
    Http::post('https://security-api.company.com/lockout', [
        'user' => $lockoutInfo['key'],
        'ip' => $request->ip(),
        'attempts' => $lockoutInfo['attempts'],
        'context' => $lockoutInfo['context'],
        'timestamp' => now()->toISOString(),
    ]);
    
    // Send Slack notification
    Http::post(env('SLACK_WEBHOOK_URL'), [
        'text' => "🚨 Lockout Alert: {$lockoutInfo['key']} locked for {$lockoutInfo['remaining_time']} seconds",
    ]);
    
    return response()->json([
        'error' => 'Account locked',
        'retry_after' => $lockoutInfo['remaining_time'],
    ], 429);
},
```

---

## 🎯 **Response Mode Comparison**

| Mode | Best For | Pros | Cons |
|------|----------|------|------|
| `auto` | Mixed apps | Zero config, smart detection | Less control |
| `json` | APIs/Mobile | Consistent JSON, simple | No web support |
| `redirect` | Web forms | Laravel standard, flash messages | No API support |
| `callback` | Advanced | Complete control, custom logic | More complex setup |

---

## 🚀 **Quick Setup Examples**

### **API-Only Application**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'json',
    ],
],
```

### **Web-Only Application**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'redirect',
        'redirect_route' => 'login',
    ],
],
```

### **Mixed Application (Recommended)**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'auto',
        'redirect_route' => 'login',
    ],
],
```

### **Advanced Application**
```php
'contexts' => [
    'login' => [
        'response_mode' => 'callback',
        'response_callback' => 'App\Http\Controllers\SecurityController@handleLockout',
    ],
],
```

**Choose the response mode that best fits your application's needs!** 🎯 