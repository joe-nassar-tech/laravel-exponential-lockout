<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Exponential Lockout Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the default configuration for the exponential lockout
    | package. You can customize the behavior for different contexts like
    | login, OTP verification, PIN validation, etc.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the cache store and key prefix for storing lockout data.
    |
    */
    'cache' => [
        'store' => env('LOCKOUT_CACHE_STORE', null), // null uses default cache store
        'prefix' => env('LOCKOUT_CACHE_PREFIX', 'exponential_lockout'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Delay Sequence
    |--------------------------------------------------------------------------
    |
    | The default exponential delay sequence in seconds.
    | [60, 300, 900, 1800, 7200, 21600, 43200, 86400]
    | = [1min, 5min, 15min, 30min, 2hr, 6hr, 12hr, 24hr]
    |
    */
    'default_delays' => [60, 300, 900, 1800, 7200, 21600, 43200, 86400],

    /*
    |--------------------------------------------------------------------------
    | Default Response Mode
    |--------------------------------------------------------------------------
    |
    | How to respond when a user is locked out:
    | 'auto' - Auto-detect JSON or redirect based on request
    | 'json' - Always return JSON response
    | 'redirect' - Always redirect to a route
    | 'callback' - Use custom callback function
    |
    */
    'default_response_mode' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Default Redirect Route
    |--------------------------------------------------------------------------
    |
    | Route to redirect to when response_mode is 'redirect' or 'auto' 
    | detects a web request.
    |
    */
    'default_redirect_route' => 'login',

    /*
    |--------------------------------------------------------------------------
    | Context-Specific Configurations
    |--------------------------------------------------------------------------
    |
    | Define specific configurations for different contexts. Each context
    | can have its own delay sequence, response mode, and key extraction logic.
    |
    | Available Options:
    | - enabled: Whether lockout is active for this context
    | - key: Field to track users by ('email', 'phone', 'ip', etc.)
    | - delays: Custom delay sequence (null uses default_delays)
    | - min_attempts: Minimum failed attempts before lockout starts (default: 3)
    | - max_attempts: Maximum attempts allowed (null = unlimited)
    | - response_mode: 'auto', 'json', 'redirect', or 'callback'
    | - redirect_route: Route to redirect to when locked out
    |
    | Examples:
    | min_attempts => 1: Lock immediately after 1st failure
    | min_attempts => 3: Allow 2 free attempts, lock on 3rd failure
    | min_attempts => 5: Allow 4 free attempts, lock on 5th failure
    |
    */
    'contexts' => [
        'login' => [
            'enabled' => true,
            'key' => 'email', // or 'ip', 'phone', custom callback
            'delays' => null, // null uses default_delays
            'response_mode' => null, // null uses default_response_mode
            'redirect_route' => null, // null uses default_redirect_route
            'max_attempts' => null, // null means unlimited (uses delay sequence length)
            'min_attempts' => 3, // Lock after 3 failed attempts (allow 2 free attempts)
            'reset_after_hours' => 24, // Reset attempt count after 24 hours of inactivity
        ],

        'otp' => [
            'enabled' => true,
            'key' => 'phone',
            'delays' => [30, 60, 180, 300, 600], // Shorter delays for OTP
            'response_mode' => 'json',
            'redirect_route' => null,
            'max_attempts' => null,
            'min_attempts' => 3, // Lock after 3 failed attempts (allow 2 free attempts)
            'reset_after_hours' => 12, // Reset OTP attempts after 12 hours (faster than login)
        ],

        'pin' => [
            'enabled' => true,
            'key' => 'user_id',
            'delays' => [60, 300, 900, 1800], // Moderate delays for PIN
            'response_mode' => null,
            'redirect_route' => null,
            'max_attempts' => null,
            'min_attempts' => 3, // Lock after 3 failed attempts (allow 2 free attempts)
            'reset_after_hours' => 24, // Reset PIN attempts after 24 hours
        ],

        'admin' => [
            'enabled' => true,
            'key' => 'email',
            'delays' => [300, 900, 1800, 7200, 21600], // Longer delays for admin
            'response_mode' => null,
            'redirect_route' => 'admin.login',
            'max_attempts' => null,
            'min_attempts' => 2, // Lock after 2 failed attempts (stricter for admin)
            'reset_after_hours' => 48, // Keep admin attempts longer (stricter)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Response Callback
    |--------------------------------------------------------------------------
    |
    | When response_mode is 'callback', this function will be called to
    | generate the response. It receives the context, key, and remaining time.
    |
    */
    'custom_response_callback' => null,
    // Example:
    // 'custom_response_callback' => function ($context, $key, $remainingTime) {
    //     return response()->json([
    //         'error' => 'Too many attempts',
    //         'context' => $context,
    //         'retry_after' => $remainingTime,
    //     ], 429);
    // },

    /*
    |--------------------------------------------------------------------------
    | Custom Key Extraction Callbacks
    |--------------------------------------------------------------------------
    |
    | Define custom callbacks for extracting keys from requests.
    | These can be referenced by name in context configurations.
    |
    */
    'key_extractors' => [
        'email' => function ($request) {
            return $request->input('email') ?: $request->ip();
        },
        'phone' => function ($request) {
            return $request->input('phone') ?: $request->ip();
        },
        'user_id' => function ($request) {
            return $request->user()?->id ?: $request->ip();
        },
        'ip' => function ($request) {
            return $request->ip();
        },
        'username' => function ($request) {
            return $request->input('username') ?: $request->ip();
        },
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Status Code
    |--------------------------------------------------------------------------
    |
    | HTTP status code to return when a user is locked out.
    |
    */
    'http_status_code' => 429, // Too Many Requests

    /*
    |--------------------------------------------------------------------------
    | Include Headers
    |--------------------------------------------------------------------------
    |
    | Whether to include Retry-After and X-RateLimit headers in responses.
    |
    */
    'include_headers' => true,
];