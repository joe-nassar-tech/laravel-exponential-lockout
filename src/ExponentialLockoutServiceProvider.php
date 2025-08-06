<?php

namespace ExponentialLockout;

use ExponentialLockout\Commands\ClearLockoutCommand;
use ExponentialLockout\Middleware\ExponentialLockout;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * ExponentialLockoutServiceProvider
 * 
 * Service provider for the Exponential Lockout package.
 * Handles registration of services, middleware, commands, and configuration.
 */
class ExponentialLockoutServiceProvider extends ServiceProvider
{
    /**
     * Register any application services
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/exponential-lockout.php',
            'exponential-lockout'
        );

        // Register the LockoutManager as a singleton
        $this->app->singleton(LockoutManager::class, function (Application $app) {
            $cache = $app->make(CacheManager::class);
            $config = $app->make('config')->get('exponential-lockout');
            
            return new LockoutManager($cache, $config);
        });

        // Register the LockoutManager with an alias for the facade
        $this->app->alias(LockoutManager::class, 'exponential-lockout');

        // Register middleware
        $this->registerMiddleware();

        // Register commands
        $this->registerCommands();
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../config/exponential-lockout.php' => config_path('exponential-lockout.php'),
        ], 'exponential-lockout-config');

        // Register Blade directives
        $this->registerBladeDirectives();
    }

    /**
     * Register middleware
     */
    protected function registerMiddleware(): void
    {
        $this->app->singleton(ExponentialLockout::class, function (Application $app) {
            $lockoutManager = $app->make(LockoutManager::class);
            $config = $app->make('config')->get('exponential-lockout');
            
            return new ExponentialLockout($lockoutManager, $config);
        });

        // Register middleware alias
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('exponential.lockout', ExponentialLockout::class);
    }

    /**
     * Register Artisan commands
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(ClearLockoutCommand::class, function (Application $app) {
                return new ClearLockoutCommand($app->make(LockoutManager::class));
            });

            $this->commands([
                ClearLockoutCommand::class,
            ]);
        }
    }

    /**
     * Register Blade directives
     */
    protected function registerBladeDirectives(): void
    {
        // @lockout directive to check if a user is locked out
        \Blade::directive('lockout', function ($expression) {
            $parts = explode(',', $expression, 2);
            $context = trim($parts[0], " '\"");
            $key = isset($parts[1]) ? trim($parts[1], " '\"") : 'null';
            
            return "<?php if (app('exponential-lockout')->isLockedOut('{$context}', {$key})): ?>";
        });

        \Blade::directive('endlockout', function () {
            return "<?php endif; ?>";
        });

        // @notlockout directive (opposite of @lockout)
        \Blade::directive('notlockout', function ($expression) {
            $parts = explode(',', $expression, 2);
            $context = trim($parts[0], " '\"");
            $key = isset($parts[1]) ? trim($parts[1], " '\"") : 'null';
            
            return "<?php if (!app('exponential-lockout')->isLockedOut('{$context}', {$key})): ?>";
        });

        \Blade::directive('endnotlockout', function () {
            return "<?php endif; ?>";
        });

        // @lockoutinfo directive to get lockout information
        \Blade::directive('lockoutinfo', function ($expression) {
            $parts = explode(',', $expression, 3);
            $variable = trim($parts[0], " '\"$");
            $context = trim($parts[1], " '\"");
            $key = isset($parts[2]) ? trim($parts[2], " '\"") : 'null';
            
            return "<?php \${$variable} = app('exponential-lockout')->getLockoutInfo('{$context}', {$key}); ?>";
        });

        // @lockouttime directive to get remaining time
        \Blade::directive('lockouttime', function ($expression) {
            $parts = explode(',', $expression, 3);
            $variable = trim($parts[0], " '\"$");
            $context = trim($parts[1], " '\"");
            $key = isset($parts[2]) ? trim($parts[2], " '\"") : 'null';
            
            return "<?php \${$variable} = app('exponential-lockout')->getRemainingTime('{$context}', {$key}); ?>";
        });
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            LockoutManager::class,
            'exponential-lockout',
            ExponentialLockout::class,
            ClearLockoutCommand::class,
        ];
    }
}