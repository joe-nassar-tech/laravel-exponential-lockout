# Getting Started with Laravel Exponential Lockout

Welcome! This guide will help you install and set up the Laravel Exponential Lockout package in simple, easy steps.

## 🎯 What You'll Learn

- How to install the package
- How to set up basic protection
- How to test that it's working
- What to do next

## 📋 Before You Start

You need:
- A Laravel website (version 9 or higher)
- Access to your website's code
- Basic knowledge of Laravel (or someone who can help)

## 🚀 Step 1: Install the Package

Open your terminal/command prompt in your Laravel project folder and run:

```bash
composer require joe-nassar-tech/laravel-exponential-lockout
```

**What this does:** Downloads and installs the security package into your website.

## ⚙️ Step 2: Publish Configuration

Run this command:

```bash
php artisan vendor:publish --tag=exponential-lockout-config
```

**What this does:** Creates a settings file where you can customize how the protection works.

**You'll see:** A new file at `config/exponential-lockout.php`

## 🛡️ Step 3: Add Protection to Your Login

Find your login route (usually in `routes/web.php`) and add protection:

**Before:**
```php
Route::post('/login', [LoginController::class, 'login']);
```

**After:**
```php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('exponential.lockout:login');
```

**What this does:** Automatically protects your login page from hackers.

## ✅ Step 4: Test It's Working

1. **Go to your login page**
2. **Try logging in with wrong password 4 times**
3. **You should see a message saying "Too many attempts"**
4. **Wait 1 minute and try again**

If you see the blocking message, congratulations! 🎉 Your website is now protected.

## 🔍 What Just Happened?

When someone tries to login with wrong password:

1. **First attempt:** Login fails normally (free attempt)
2. **Second attempt:** Login fails normally (free attempt)
3. **Third attempt:** Login fails normally (free attempt)
4. **Fourth attempt:** System blocks them for 1 minute
5. **Fifth attempt:** System blocks them for 5 minutes
6. **And so on...** Times keep getting longer

This makes it impossible for hackers to try thousands of passwords quickly.

## 📊 Default Protection Schedule

| Attempt | Wait Time |
|---------|-----------|
| 1st     | No wait   |
| 2nd     | No wait   |
| 3rd     | No wait   |
| 4th     | 1 minute  |
| 5th     | 5 minutes |
| 6th     | 15 minutes|
| 7th     | 30 minutes|
| 8th     | 2 hours   |
| 9th     | 6 hours   |
| 10th    | 12 hours  |
| 11th+   | 24 hours  |

## 🎛️ Basic Settings (Optional)

You can change settings in `config/exponential-lockout.php`. Here are the most important ones:

### Change Wait Times
```php
'default_delays' => [30, 60, 180, 300], // Shorter times
```

### Protect Different Pages
```php
'contexts' => [
    'login' => ['enabled' => true],
    'password_reset' => ['enabled' => true],
    'otp' => ['enabled' => true],
],
```

## 🚨 Important Notes

1. **Real users aren't affected** - Only people making many wrong attempts get blocked
2. **Automatic cleanup** - Old blocks are automatically removed
3. **No database needed** - Uses Laravel's cache system
4. **Works immediately** - No additional setup required

## 🎉 Success! What's Next?

Your basic protection is now working! Here's what you can do next:

1. **[Learn Basic Usage](basic-usage.md)** - See more examples
2. **[Protect More Pages](middleware-protection.md)** - Add protection to password reset, OTP, etc.
3. **[Customize Settings](configuration.md)** - Adjust protection for your needs
4. **[Learn Manual Control](manual-control.md)** - Control protection in your code

## 🆘 Something Not Working?

### Common Issues

**"Command not found"**
- Make sure you're in your Laravel project folder
- Check that Composer is installed

**"Route not found"**
- Make sure your routes file has the correct syntax
- Check that your controller exists

**"No blocking happening"**
- Make sure you added the middleware correctly
- Check that the route is being used (not cached)
- Try clearing cache: `php artisan cache:clear`

### Still Need Help?

1. Check [Troubleshooting Guide](troubleshooting.md)
2. Email: joe.nassar.tech@gmail.com

## 🎊 Congratulations!

You've successfully installed and set up Laravel Exponential Lockout! Your website is now much safer from hackers and brute force attacks.

Remember: Good security is like a good lock - you install it once and it protects you every day! 🔒