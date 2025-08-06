<?php

namespace ExponentialLockout\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Lockout Facade
 * 
 * Provides easy access to the LockoutManager functionality
 * 
 * @method static int recordFailure(string $context, string $key)
 * @method static bool isLockedOut(string $context, string $key)
 * @method static int getRemainingTime(string $context, string $key)
 * @method static bool clear(string $context, string $key)
 * @method static bool clearContext(string $context)
 * @method static int getAttemptCount(string $context, string $key)
 * @method static string extractKeyFromRequest(string $context, \Illuminate\Http\Request $request)
 * @method static array getLockoutInfo(string $context, string $key)
 * 
 * @see \ExponentialLockout\LockoutManager
 */
class Lockout extends Facade
{
    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return 'exponential-lockout';
    }
}