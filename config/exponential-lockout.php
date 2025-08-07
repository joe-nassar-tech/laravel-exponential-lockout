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
    | Context Templates & Inheritance
    |--------------------------------------------------------------------------
    |
    | Define reusable context templates that can be inherited by other contexts.
    | This allows you to create consistent security policies across multiple contexts.
    |
    | Template Inheritance:
    | - Use 'extends' => 'template_name' to inherit from a template
    | - Override specific settings while keeping others from the template
    | - Templates can extend other templates (nested inheritance)
    |
    | Examples:
    | 'extends' => 'strict' - Inherit strict security template
    | 'extends' => 'lenient' - Inherit lenient security template
    | 'extends' => 'api' - Inherit API-specific template
    |
    */
    'context_templates' => [
        'strict' => [
            'enabled' => true,
            'min_attempts' => 1, // Lock immediately after 1st failure
            'delays' => [300, 900, 1800, 7200, 21600], // 5min → 15min → 30min → 2hr → 6hr
            'reset_after_hours' => 48, // Keep attempts longer
        ],
        
        'lenient' => [
            'enabled' => true,
            'min_attempts' => 5, // Allow 4 free attempts
            'delays' => [30, 60, 180, 300, 600], // 30sec → 1min → 3min → 5min → 10min
            'reset_after_hours' => 12, // Reset faster
        ],
        
        'api' => [
            'enabled' => true,
            'response_mode' => 'json',
            'min_attempts' => 3,
            'delays' => [60, 300, 900, 1800, 7200],
            'reset_after_hours' => 24,
        ],
        
        'web' => [
            'enabled' => true,
            'response_mode' => 'redirect',
            'min_attempts' => 3,
            'delays' => [60, 300, 900, 1800, 7200],
            'reset_after_hours' => 24,
        ],
        
        'mfa' => [
            'enabled' => true,
            'min_attempts' => 2, // Stricter for MFA
            'delays' => [30, 60, 120, 300, 600], // Quick cycles for time-sensitive MFA
            'reset_after_hours' => 12, // Reset faster for MFA
        ],
    ],

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
    | - extends: Inherit settings from a template (see context_templates above)
    |
    | Examples:
    | min_attempts => 1: Lock immediately after 1st failure
    | min_attempts => 3: Allow 2 free attempts, lock on 3rd failure
    | min_attempts => 5: Allow 4 free attempts, lock on 5th failure
    |
    | Inheritance Examples:
    | 'extends' => 'strict' - Inherit strict security template
    | 'extends' => 'api' - Inherit API template and override specific settings
    |
    */
    'contexts' => [
        'login' => [
            'extends' => 'web', // Inherit web template
            'key' => 'email',
            'redirect_route' => 'login',
        ],

        'otp' => [
            'extends' => 'mfa', // Inherit MFA template
            'key' => 'phone',
            'response_mode' => 'json',
        ],

        'pin' => [
            'extends' => 'web', // Inherit web template
            'key' => 'user_id',
            'delays' => [60, 300, 900, 1800], // Override with moderate delays
        ],

        'admin' => [
            'extends' => 'strict', // Inherit strict template
            'key' => 'email',
            'redirect_route' => 'admin.login',
        ],

        // API contexts using inheritance
        'api_login' => [
            'extends' => 'api', // Inherit API template
            'key' => 'email',
        ],

        'api_otp' => [
            'extends' => 'mfa', // Inherit MFA template
            'key' => 'phone',
            'response_mode' => 'json', // Override to JSON for API
        ],

        // Additional contexts using inheritance
        'password_reset' => [
            'extends' => 'web',
            'key' => 'email',
            'redirect_route' => 'password.request',
        ],

        'email_verification' => [
            'extends' => 'mfa',
            'key' => 'email',
            'response_mode' => 'json',
        ],

        'two_factor' => [
            'extends' => 'mfa',
            'key' => 'user_id',
            'response_mode' => 'json',
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