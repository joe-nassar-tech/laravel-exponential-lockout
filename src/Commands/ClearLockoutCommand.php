<?php

namespace ExponentialLockout\Commands;

use ExponentialLockout\LockoutManager;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Clear Lockout Command
 * 
 * Artisan command to clear lockouts for specific contexts and keys
 */
class ClearLockoutCommand extends Command
{
    /**
     * The name and signature of the console command
     */
    protected $signature = 'lockout:clear 
                           {context : The lockout context to clear (e.g., login, otp)}
                           {key? : The specific key to clear (optional, clears all if omitted)}
                           {--all : Clear all lockouts for the context}
                           {--force : Force clearing without confirmation}';

    /**
     * The console command description
     */
    protected $description = 'Clear exponential lockouts for a specific context and key';

    /**
     * The lockout manager instance
     */
    protected LockoutManager $lockoutManager;

    /**
     * Create a new command instance
     */
    public function __construct(LockoutManager $lockoutManager)
    {
        parent::__construct();
        $this->lockoutManager = $lockoutManager;
    }

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        $context = $this->argument('context');
        $key = $this->argument('key');
        $clearAll = $this->option('all');
        $force = $this->option('force');

        try {
            // Validate context exists
            $this->validateContext($context);

            if ($clearAll || !$key) {
                return $this->clearAllForContext($context, $force);
            } else {
                return $this->clearSpecificKey($context, $key, $force);
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Clear lockout for a specific context and key
     */
    protected function clearSpecificKey(string $context, string $key, bool $force): int
    {
        // Check if the key is currently locked out
        $isLockedOut = $this->lockoutManager->isLockedOut($context, $key);
        $attemptCount = $this->lockoutManager->getAttemptCount($context, $key);

        if (!$isLockedOut && $attemptCount === 0) {
            $this->info("No lockout found for context '{$context}' and key '{$key}'.");
            return self::SUCCESS;
        }

        // Show current status
        if ($isLockedOut) {
            $remainingTime = $this->lockoutManager->getRemainingTime($context, $key);
            $this->warn("Current status for '{$context}' / '{$key}':");
            $this->line("- Locked out: Yes");
            $this->line("- Attempts: {$attemptCount}");
            $this->line("- Remaining time: {$remainingTime} seconds");
        } else {
            $this->warn("Current status for '{$context}' / '{$key}':");
            $this->line("- Locked out: No");
            $this->line("- Attempts: {$attemptCount}");
        }

        // Confirm before clearing (unless forced)
        if (!$force && !$this->confirm("Do you want to clear the lockout for '{$context}' / '{$key}'?")) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        // Clear the lockout
        $success = $this->lockoutManager->clear($context, $key);

        if ($success) {
            $this->info("Successfully cleared lockout for context '{$context}' and key '{$key}'.");
            return self::SUCCESS;
        } else {
            $this->error("Failed to clear lockout for context '{$context}' and key '{$key}'.");
            return self::FAILURE;
        }
    }

    /**
     * Clear all lockouts for a context
     */
    protected function clearAllForContext(string $context, bool $force): int
    {
        $this->warn("This will clear ALL lockouts for context '{$context}'.");

        // Confirm before clearing (unless forced)
        if (!$force && !$this->confirm("Are you sure you want to proceed?")) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        // Clear all lockouts for the context
        $success = $this->lockoutManager->clearContext($context);

        if ($success) {
            $this->info("Successfully cleared all lockouts for context '{$context}'.");
            return self::SUCCESS;
        } else {
            $this->error("Failed to clear lockouts for context '{$context}'.");
            return self::FAILURE;
        }
    }

    /**
     * Validate that the context exists in configuration
     */
    protected function validateContext(string $context): void
    {
        try {
            // This will throw an exception if context doesn't exist or is disabled
            $this->lockoutManager->getLockoutInfo($context, 'test');
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }
}