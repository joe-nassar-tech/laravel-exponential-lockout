# Laravel Exponential Lockout - Documentation

Welcome to the complete documentation for Laravel Exponential Lockout! This documentation is written in simple English so anyone can understand and use this powerful security tool.

## 📚 Documentation Structure

### For Beginners
- **[Getting Started](getting-started.md)** - Start here if you're new to this package
- **[Basic Usage](basic-usage.md)** - Simple examples to get you going
- **[Configuration](configuration.md)** - How to set up the package for your needs

### Feature Guides
- **[Middleware Protection](middleware-protection.md)** - Protect your website routes automatically
- **[Manual Control](manual-control.md)** - Control lockouts in your code
- **[Command Line Tools](command-line-tools.md)** - Manage lockouts from terminal

### Reference & Help
- **[Examples and Recipes](examples-and-recipes.md)** - Real-world examples and solutions
- **[Troubleshooting](troubleshooting.md)** - Common problems and solutions

### For Developers
- **[Learning Roadmap](learning-roadmap.md)** - How to learn Laravel and understand this package
- **[Developer Guide](developer-guide.md)** - Internal architecture and code explanation
- **[Publishing Guide](publishing-guide.md)** - How to publish Laravel packages

## 🎯 What This Package Does

This package helps protect your website from hackers and spam by:

1. **Blocking repeat attackers** - If someone tries to login with wrong password multiple times, we block them
2. **Getting smarter over time** - Each failed attempt increases the waiting time (1 min → 5 min → 15 min → etc.)
3. **Protecting different areas** - Login, password reset, OTP verification, etc. can have different rules
4. **Being flexible** - Works with any Laravel website automatically

## 🚀 Quick Start

1. **Install the package**
2. **Add protection to your login page**
3. **Done!** - Your website is now protected

It's that simple! See [Getting Started](getting-started.md) for detailed steps.

## 💡 How It Works (Simple Explanation)

Think of it like a security guard at a building:

- **First wrong attempt**: "Please wait 1 minute before trying again"
- **Second wrong attempt**: "Please wait 5 minutes before trying again"  
- **Third wrong attempt**: "Please wait 15 minutes before trying again"
- **And so on...**

The waiting time keeps getting longer, making it impossible for hackers to try thousands of passwords quickly.

## 🛡️ What Makes This Special

- **Automatic Protection** - Just add one line of code to protect any page
- **Smart Detection** - Knows the difference between real users and hackers
- **Easy to Use** - No complicated setup required
- **Flexible** - Can protect login, password reset, OTP verification, and more
- **Safe** - Uses Laravel's built-in security features

## 👨‍💻 About the Developer

**Joe Nassar**  
Email: joe.nassar.tech@gmail.com

Created with ❤️ to help keep Laravel websites safe and secure.

## 🆘 Need Help?

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Look at [Examples and Recipes](examples-and-recipes.md) 
3. Email the developer: joe.nassar.tech@gmail.com

Let's make your website secure! 🔒