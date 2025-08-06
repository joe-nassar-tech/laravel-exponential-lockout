# Command Line Tools Guide

This guide shows you how to manage lockouts using the command line (terminal). These tools help you unblock users, check status, and maintain your security system.

## 🖥️ What is the Command Line?

The command line (also called terminal, console, or cmd) is a text-based way to control your website. Think of it as a powerful remote control for your server.

## 🛠️ The Main Command

The package provides one main command:

```bash
php artisan lockout:clear
```

This command can do many different things depending on how you use it.

## 🚨 Emergency: Unblock a User

### Unblock Specific User from Login

```bash
php artisan lockout:clear login user@example.com
```

**What this does:** Immediately removes the login block for `user@example.com`

### Unblock Phone Number from OTP

```bash
php artisan lockout:clear otp +1234567890
```

**What this does:** Removes OTP verification block for phone number

### Unblock Admin User

```bash
php artisan lockout:clear admin admin@company.com
```

**What this does:** Removes admin login block

## 🧹 Mass Cleanup

### Remove All Login Blocks

```bash
php artisan lockout:clear login --all
```

**What this does:** Removes ALL login blocks for ALL users

**⚠️ Warning:** This affects everyone! Use carefully.

### Remove All OTP Blocks

```bash
php artisan lockout:clear otp --all
```

**What this does:** Removes ALL OTP blocks for ALL phone numbers

## 🚀 Quick Actions (No Confirmation)

### Force Unblock (No Questions Asked)

```bash
php artisan lockout:clear login user@example.com --force
```

**What this does:** Unblocks immediately without asking for confirmation

### Force Mass Cleanup

```bash
php artisan lockout:clear login --all --force
```

**What this does:** Removes all blocks without asking for confirmation

## 📊 Understanding Command Output

### Successful Unblock

```
Current status for 'login' / 'user@example.com':
- Locked out: Yes
- Attempts: 4
- Remaining time: 285 seconds

Do you want to clear the lockout for 'login' / 'user@example.com'? (yes/no):
> yes

Successfully cleared lockout for context 'login' and key 'user@example.com'.
```

### No Block Found

```
No lockout found for context 'login' and key 'user@example.com'.
```

### Context Not Found

```
Lockout context 'wrong_name' is not configured.
```

## 🎯 Common Use Cases

### 1. Help a Locked-Out Customer

**Scenario:** Customer calls saying they can't log in

**Solution:**
```bash
# Check their email and unblock
php artisan lockout:clear login customer@email.com
```

### 2. Clear OTP Issues

**Scenario:** User not receiving OTP codes due to blocks

**Solution:**
```bash
# Clear OTP block for their phone
php artisan lockout:clear otp +1234567890
```

### 3. After System Maintenance

**Scenario:** You've fixed an issue and want to give everyone a fresh start

**Solution:**
```bash
# Clear all login blocks
php artisan lockout:clear login --all --force
```

### 4. Emergency Admin Access

**Scenario:** Admin is locked out during emergency

**Solution:**
```bash
# Quickly unblock admin
php artisan lockout:clear admin admin@company.com --force
```

## 🎨 Interactive vs Force Mode

### Interactive Mode (Default)

```bash
php artisan lockout:clear login user@example.com
```

**What happens:**
1. Shows current block status
2. Asks for confirmation
3. Performs action only if you say "yes"

**Good for:** Regular maintenance, double-checking before action

### Force Mode

```bash
php artisan lockout:clear login user@example.com --force
```

**What happens:**
1. Immediately performs action
2. No questions asked
3. Shows result

**Good for:** Scripts, emergency situations, batch operations

## 🔧 Advanced Usage

### Using in Scripts

Create a script file (`unblock_user.sh`):

```bash
#!/bin/bash
EMAIL=$1

if [ -z "$EMAIL" ]; then
    echo "Usage: ./unblock_user.sh user@example.com"
    exit 1
fi

echo "Unblocking user: $EMAIL"
php artisan lockout:clear login "$EMAIL" --force
php artisan lockout:clear password_reset "$EMAIL" --force
php artisan lockout:clear otp "$EMAIL" --force

echo "User $EMAIL has been unblocked from all systems"
```

**Usage:**
```bash
chmod +x unblock_user.sh
./unblock_user.sh user@example.com
```

### Batch Operations

```bash
# Unblock multiple users
php artisan lockout:clear login user1@example.com --force
php artisan lockout:clear login user2@example.com --force
php artisan lockout:clear login user3@example.com --force

# Or clear all at once
php artisan lockout:clear login --all --force
```

### Maintenance Window

```bash
# Clear all blocks during maintenance
php artisan lockout:clear login --all --force
php artisan lockout:clear otp --all --force
php artisan lockout:clear password_reset --all --force
php artisan lockout:clear admin --all --force

echo "All security blocks cleared for maintenance window"
```

## 🎯 Context Reference

### Available Contexts (Default)

| Context | Purpose | Example Key |
|---------|---------|-------------|
| `login` | User login | `user@example.com` |
| `otp` | Phone verification | `+1234567890` |
| `pin` | PIN validation | `user123` |
| `admin` | Admin login | `admin@company.com` |

### Custom Contexts

If you've added custom contexts in your configuration, you can use them too:

```bash
php artisan lockout:clear my_custom_context some_key --force
```

## 🚨 Error Messages and Solutions

### "Context not configured"

**Error:**
```
Lockout context 'wrong_name' is not configured.
```

**Solution:** Check your config file and use the correct context name

### "Command not found"

**Error:**
```
Command "lockout:clear" is not defined.
```

**Solutions:**
1. Make sure you're in your Laravel project directory
2. Check that the package is installed: `composer show joe-nassar-tech/laravel-exponential-lockout`
3. Clear Laravel cache: `php artisan cache:clear`

### "Permission denied"

**Error:**
```
Permission denied
```

**Solutions:**
1. Make sure you have proper file permissions
2. Run with appropriate user privileges
3. Check Laravel storage permissions

## 🎛️ Automation and Scheduling

### Daily Cleanup (Optional)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Clear old blocks every day at 3 AM
    $schedule->command('lockout:clear login --all --force')
             ->dailyAt('03:00')
             ->onFailure(function () {
                 Log::error('Failed to clear daily lockouts');
             });
}
```

### Weekly Cleanup

```php
protected function schedule(Schedule $schedule)
{
    // Clear all blocks every Sunday
    $schedule->command('lockout:clear login --all --force')
             ->weekly()
             ->sundays()
             ->at('02:00');
}
```

## 🔍 Monitoring and Logging

### Check Laravel Logs

```bash
# View recent logs
tail -f storage/logs/laravel.log

# Search for lockout activity
grep "lockout" storage/logs/laravel.log
```

### Custom Logging Script

Create `check_lockouts.php`:

```php
<?php
// Add this to a custom Artisan command or script

use ExponentialLockout\Facades\Lockout;

$contexts = ['login', 'otp', 'admin'];
$testKeys = ['test@example.com', '+1234567890'];

foreach ($contexts as $context) {
    foreach ($testKeys as $key) {
        $info = Lockout::getLockoutInfo($context, $key);
        if ($info['is_locked_out']) {
            echo "BLOCKED: {$context} / {$key} - {$info['remaining_time']} seconds left\n";
        }
    }
}
```

## 📱 Mobile/Remote Access

### SSH Access

```bash
# Connect to your server
ssh user@yourserver.com

# Navigate to your Laravel project
cd /path/to/your/laravel/project

# Run lockout commands
php artisan lockout:clear login user@example.com --force
```

### Using Laravel Forge

If you use Laravel Forge:

1. Go to your site's page
2. Click "Commands"
3. Run: `php artisan lockout:clear login user@example.com --force`

### Using cPanel or Similar

1. Open Terminal/SSH in your hosting panel
2. Navigate to your Laravel installation
3. Run the commands as shown above

## 🎉 Best Practices

### ✅ Do This

1. **Use force mode sparingly**
   ```bash
   # Interactive for regular use
   php artisan lockout:clear login user@example.com
   
   # Force only for emergencies
   php artisan lockout:clear login user@example.com --force
   ```

2. **Be specific with contexts**
   ```bash
   # Good - specific context
   php artisan lockout:clear login user@example.com
   
   # Not ideal - clearing everything
   php artisan lockout:clear login --all
   ```

3. **Document your actions**
   ```bash
   # Keep notes of what you did
   echo "$(date): Cleared login block for user@example.com" >> maintenance.log
   php artisan lockout:clear login user@example.com --force
   ```

### ❌ Don't Do This

1. **Don't clear everything routinely**
   ```bash
   # Bad - defeats the purpose of security
   php artisan lockout:clear login --all --force
   ```

2. **Don't ignore context names**
   ```bash
   # Bad - wrong context name
   php artisan lockout:clear wrong_name user@example.com
   ```

3. **Don't run without understanding**
   ```bash
   # Bad - could affect many users
   php artisan lockout:clear login --all --force
   ```

## 🎯 Quick Reference

### Most Common Commands

```bash
# Unblock specific user from login
php artisan lockout:clear login user@example.com

# Unblock phone from OTP
php artisan lockout:clear otp +1234567890

# Emergency unblock (no questions)
php artisan lockout:clear login user@example.com --force

# Clear all blocks (be careful!)
php artisan lockout:clear login --all --force
```

### Command Structure

```bash
php artisan lockout:clear [CONTEXT] [KEY] [OPTIONS]

CONTEXT: login, otp, admin, etc.
KEY: email, phone, username, etc.
OPTIONS: --all, --force
```

## 🚀 Next Steps

- **[Troubleshooting Guide](troubleshooting.md)** - Fix common problems
- **[Examples and Recipes](examples-and-recipes.md)** - More real-world examples
- **[Manual Control Guide](manual-control.md)** - Control in your code
- **[Configuration Guide](configuration.md)** - Customize settings

## 🆘 Need Help?

- **[Troubleshooting Guide](troubleshooting.md)** - Common command-line problems
- **Email Developer:** joe.nassar.tech@gmail.com

You now have powerful command-line control over your security system! 💪