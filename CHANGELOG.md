# Changelog

All notable changes to `laravel-exponential-lockout` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.5] - 2024-01-XX

### Added
- **Context Template Inheritance System**
  - Reusable security templates for consistent policies
  - Template inheritance with `extends` option
  - Pre-built templates: `strict`, `lenient`, `api`, `web`, `mfa`
  - Context-specific overrides while maintaining template defaults
  - Improved configuration organization and maintainability

### Changed
- Updated default context configurations to use template inheritance
- Enhanced configuration documentation with template examples
- Improved code organization and readability

### Technical Details
- Added `context_templates` configuration section
- Enhanced `getContextConfig()` method with inheritance support
- Template merging with context-specific overrides
- Backward compatibility maintained for direct context configuration

---

## [Unreleased]

### Added
- Initial package development
- Core lockout functionality
- Comprehensive documentation

## [1.0.0] - 2024-01-XX (Planned Release)

### Added
- **Core Lockout System**
  - Exponential delay sequence: 1min → 5min → 15min → 30min → 2hr → 6hr → 12hr → 24hr
  - Context-based lockouts (login, OTP, admin, etc.)
  - Configurable delay sequences per context
  - Automatic lockout expiration and cleanup

- **Multiple Key Extraction Methods**
  - Email-based lockouts for login contexts
  - Phone-based lockouts for OTP verification
  - IP-based lockouts for anonymous requests
  - User ID-based lockouts for authenticated users
  - Custom key extractors via callable functions

- **Middleware Protection**
  - `exponential.lockout:{context}` middleware for routes
  - Auto-detection of JSON vs HTML responses
  - Proper HTTP 429 status codes with Retry-After headers
  - Context-specific response modes (JSON, redirect, auto)

- **Manual API Control**
  - `Lockout::recordFailure()` - Record failed attempts
  - `Lockout::isLockedOut()` - Check lockout status
  - `Lockout::clear()` - Clear specific lockouts
  - `Lockout::getRemainingTime()` - Get time until unlock
  - `Lockout::getAttemptCount()` - Get current attempt count
  - `Lockout::getLockoutInfo()` - Get detailed lockout information

- **Configuration System**
  - Comprehensive configuration file with sensible defaults
  - Context-specific configurations
  - Environment variable support
  - Custom response callbacks
  - Configurable cache stores and key prefixes

- **Laravel Integration**
  - Service provider with auto-registration
  - Facade for easy access (`Lockout::`)
  - Blade directives (@lockout, @lockoutinfo, @lockouttime)
  - Cache integration (Redis, File, Array, etc.)
  - Laravel 9.0+ compatibility

- **Command Line Tools**
  - `php artisan lockout:clear {context} {key}` - Clear specific lockouts
  - `php artisan lockout:clear {context} --all` - Clear all lockouts for context
  - `--force` flag for non-interactive clearing
  - Interactive confirmation prompts

- **Response Handling**
  - Smart auto-detection of API vs web requests
  - JSON responses for API endpoints
  - Redirect responses for web forms
  - Custom response callbacks
  - Proper HTTP headers (Retry-After, X-RateLimit-*)

- **Security Features**
  - SHA256 hashing of cache keys for privacy
  - Context isolation prevents cross-contamination
  - Automatic cleanup of expired lockouts
  - Input validation and sanitization
  - Protection against cache injection attacks

- **Documentation**
  - Comprehensive README with examples
  - Getting Started guide for beginners
  - Basic Usage guide with real-world examples
  - Middleware Protection guide
  - Manual Control guide
  - Configuration guide with all options
  - Command Line Tools guide
  - Troubleshooting guide
  - Examples and Recipes for different application types
  - Learning Roadmap for beginner Laravel developers
  - Developer Guide explaining internal architecture

### Dependencies
- PHP 8.0 or higher
- Laravel 9.0 or higher (supports 9.x, 10.x, 11.x)
- illuminate/support
- illuminate/cache
- illuminate/console
- illuminate/http

### Configuration
- Published config file: `config/exponential-lockout.php`
- Default contexts: login, otp, pin, admin
- Configurable delay sequences per context
- Environment variable overrides
- Custom key extractors and response handlers

---

## Version History

### Version Format
This project uses [Semantic Versioning](https://semver.org/):
- **MAJOR.MINOR.PATCH** (e.g., 1.0.0)
- **MAJOR**: Incompatible API changes
- **MINOR**: Backwards-compatible functionality additions  
- **PATCH**: Backwards-compatible bug fixes

### Release Types

#### 🎉 Major Releases (X.0.0)
- Breaking changes
- New major features
- API redesigns
- Laravel version upgrades

#### ✨ Minor Releases (1.X.0)
- New features
- New configuration options
- New contexts or key extractors
- Documentation improvements

#### 🐛 Patch Releases (1.0.X)
- Bug fixes
- Security patches
- Performance improvements
- Documentation fixes

---

## Upgrade Guides

### Upgrading to 1.0.0
Initial release - no upgrade path needed.

### Future Upgrades
Upgrade guides will be provided for each major version change.

---

## Contributors

### Core Development
- **Joe Nassar** (@joenassar) - Initial development and architecture

### Documentation
- **Joe Nassar** - Complete documentation suite

### Community Contributors
- *No community contributions yet - be the first!*

---

## Acknowledgments

### Inspiration
This package was inspired by:
- Laravel's built-in rate limiting
- Common brute force protection patterns
- Community feedback on authentication security

### Laravel Community
Thanks to the Laravel community for:
- Excellent documentation and examples
- Package development best practices
- Feedback and testing

### Security Research
Based on security best practices from:
- OWASP guidelines for authentication
- Industry standard lockout patterns
- Academic research on brute force prevention

---

## Links

- **Repository**: https://github.com/joenassar/laravel-exponential-lockout
- **Packagist**: https://packagist.org/packages/vendor/laravel-exponential-lockout
- **Documentation**: https://github.com/joenassar/laravel-exponential-lockout/tree/main/docs
- **Issues**: https://github.com/joenassar/laravel-exponential-lockout/issues
- **Discussions**: https://github.com/joenassar/laravel-exponential-lockout/discussions

---

## Maintenance

### Current Status
- **Status**: Active development
- **Maintainer**: Joe Nassar (joe.nassar.tech@gmail.com)
- **Support**: Laravel 9.x, 10.x, 11.x
- **PHP**: 8.0+

### Release Schedule
- **Patch releases**: As needed for critical bugs
- **Minor releases**: Monthly or bi-monthly
- **Major releases**: Annually or for significant changes

---

*This changelog is automatically updated with each release. For the most current information, please check the [GitHub repository](https://github.com/joenassar/laravel-exponential-lockout).*