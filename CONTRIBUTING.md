# Contributing to Laravel Exponential Lockout

Thank you for considering contributing to Laravel Exponential Lockout! This document provides guidelines and information for contributors.

## 🤝 How to Contribute

We welcome contributions in many forms:

- 🐛 **Bug reports** - Help us identify and fix issues
- ✨ **Feature requests** - Suggest new functionality
- 📝 **Documentation improvements** - Help make our docs better
- 🔧 **Code contributions** - Submit bug fixes and new features
- 💬 **Community support** - Help other users in discussions

## 🐛 Reporting Bugs

### Before Reporting a Bug

1. **Check existing issues** - Search [GitHub Issues](https://github.com/joenassar/laravel-exponential-lockout/issues) to see if the bug has already been reported
2. **Check documentation** - Review our [documentation](docs/) to ensure you're using the package correctly
3. **Test with latest version** - Make sure you're using the most recent version

### How to Report a Bug

Create a detailed issue with:

1. **Clear title** - Summarize the problem in the title
2. **Environment details:**
   - Laravel version
   - PHP version
   - Package version
   - Operating system
3. **Steps to reproduce** - Detailed steps to recreate the issue
4. **Expected behavior** - What you expected to happen
5. **Actual behavior** - What actually happened
6. **Code samples** - Minimal code that reproduces the issue
7. **Error messages** - Include complete error messages and stack traces

### Bug Report Template

```markdown
**Environment:**
- Laravel version: 10.x
- PHP version: 8.1
- Package version: 1.0.0
- OS: Ubuntu 22.04

**Description:**
Brief description of the bug.

**Steps to Reproduce:**
1. Step one
2. Step two
3. Step three

**Expected Behavior:**
What should happen.

**Actual Behavior:**
What actually happens.

**Code Sample:**
```php
// Minimal code that reproduces the issue
```

**Error Messages:**
```
Complete error message and stack trace
```

**Additional Context:**
Any other relevant information.
```

## ✨ Suggesting Features

### Before Suggesting a Feature

1. **Check existing features** - Review documentation to see if it already exists
2. **Search issues** - Look for similar feature requests
3. **Consider the scope** - Ensure the feature fits the package's purpose

### How to Suggest a Feature

Create an issue with:

1. **Clear title** - Summarize the feature request
2. **Problem description** - What problem does this solve?
3. **Proposed solution** - How should it work?
4. **Use cases** - Real-world examples of when this would be useful
5. **Alternative solutions** - Other ways to solve the problem
6. **Additional context** - Any other relevant information

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Other solutions you've thought about.

**Use Cases**
Real-world examples of when this would be useful.

**Additional context**
Any other context or screenshots about the feature request.
```

## 🔧 Code Contributions

### Development Setup

1. **Fork the repository**
   ```bash
   # Fork on GitHub, then clone your fork
   git clone https://github.com/YOUR-USERNAME/laravel-exponential-lockout.git
   cd laravel-exponential-lockout
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

### Coding Standards

We follow Laravel and PSR-12 coding standards:

#### PHP Standards
- **PSR-12** for code style
- **Type hints** for all parameters and return types
- **DocBlocks** for all public methods
- **Meaningful names** for variables and methods

#### Code Examples

**✅ Good:**
```php
/**
 * Record a failed attempt for the given context and key
 * 
 * @param string $context The lockout context
 * @param string $key The unique identifier
 * @return int The current attempt count
 */
public function recordFailure(string $context, string $key): int
{
    $this->validateContext($context);
    
    $cacheKey = $this->buildCacheKey($context, $key);
    $lockoutData = $this->getCacheStore()->get($cacheKey, [
        'attempts' => 0,
        'locked_until' => null,
    ]);
    
    return ++$lockoutData['attempts'];
}
```

**❌ Bad:**
```php
// No docblock, unclear method name, no type hints
public function rf($c, $k)
{
    $d = $this->cache->get($c . $k, []);
    return $d['a']++;
}
```

#### Laravel Conventions
- Use Laravel's built-in features when possible
- Follow Laravel naming conventions
- Use dependency injection
- Leverage service container

### Testing

#### Running Tests
```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/LockoutManagerTest.php
```

#### Writing Tests

All new features must include tests:

**Unit Tests** - Test individual methods:
```php
public function test_records_failure_and_increments_attempts()
{
    $attempts = Lockout::recordFailure('login', 'test@example.com');
    $this->assertEquals(1, $attempts);
    
    $attempts = Lockout::recordFailure('login', 'test@example.com');
    $this->assertEquals(2, $attempts);
}
```

**Feature Tests** - Test complete functionality:
```php
public function test_middleware_blocks_after_multiple_failures()
{
    // Make multiple failed login attempts
    for ($i = 0; $i < 3; $i++) {
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
    }
    
    // Next attempt should be blocked
    $response = $this->post('/login', ['email' => 'test@example.com', 'password' => 'wrong']);
    $response->assertStatus(429);
}
```

### Documentation

When contributing code, also update:

1. **README.md** - If adding major features
2. **Documentation files** - Update relevant docs in `/docs`
3. **DocBlocks** - All public methods need documentation
4. **Examples** - Add usage examples for new features

### Pull Request Process

1. **Ensure tests pass**
   ```bash
   composer test
   ```

2. **Update documentation** as needed

3. **Update CHANGELOG.md** with your changes

4. **Create pull request** with:
   - Clear title describing the change
   - Detailed description of what was changed
   - Link to any related issues
   - Screenshots if UI changes are involved

5. **Respond to feedback** promptly

### Pull Request Template

```markdown
## Description
Brief description of changes.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Related Issues
Fixes #(issue number)

## Testing
- [ ] Tests pass locally
- [ ] New tests added for new functionality
- [ ] Manual testing completed

## Screenshots (if applicable)
Add screenshots here.

## Checklist
- [ ] My code follows the project's style guidelines
- [ ] I have performed a self-review of my code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
```

## 📝 Documentation Contributions

### Types of Documentation

1. **API Documentation** - Method and class documentation
2. **User Guides** - How-to guides for end users
3. **Examples** - Real-world usage examples
4. **Troubleshooting** - Common problems and solutions

### Documentation Standards

- **Clear language** - Use simple, easy-to-understand language
- **Code examples** - Include working code samples
- **Step-by-step** - Break complex tasks into steps
- **Screenshots** - Use images when helpful
- **Links** - Reference related documentation

### Updating Documentation

Documentation is in the `/docs` folder:

```
docs/
├── README.md                    # Documentation overview
├── getting-started.md          # Installation guide
├── basic-usage.md              # Simple examples
├── middleware-protection.md    # Route protection
├── manual-control.md          # Programmatic control
├── configuration.md           # Settings guide
├── command-line-tools.md      # CLI tools
├── troubleshooting.md         # Problem solving
├── examples-and-recipes.md    # Real-world examples
├── learning-roadmap.md        # Beginner learning path
└── developer-guide.md         # Internal architecture
```

## 🌟 Recognition

Contributors will be recognized in:

1. **CHANGELOG.md** - Credit for specific contributions
2. **GitHub Contributors** - Automatic recognition
3. **README.md** - Major contributors may be listed

## 📞 Getting Help

### Questions About Contributing

- **Create a discussion** on GitHub
- **Email the maintainer:** joe.nassar.tech@gmail.com
- **Join the conversation** in existing issues

### Development Questions

- **Laravel documentation** - https://laravel.com/docs
- **PHP documentation** - https://php.net/docs
- **PSR-12 standard** - https://www.php-fig.org/psr/psr-12/

## 🎯 Development Goals

Our priorities for the package:

1. **Security** - Protect against brute force attacks effectively
2. **Performance** - Minimal overhead and efficient caching
3. **Usability** - Easy to install, configure, and use
4. **Compatibility** - Work with all supported Laravel versions
5. **Documentation** - Comprehensive and beginner-friendly
6. **Testing** - High test coverage and reliable functionality

## 📜 Code of Conduct

### Our Pledge

We pledge to make participation in our project a harassment-free experience for everyone, regardless of age, body size, disability, ethnicity, gender identity and expression, level of experience, nationality, personal appearance, race, religion, or sexual identity and orientation.

### Expected Behavior

- Use welcoming and inclusive language
- Be respectful of differing viewpoints
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Trolling, insulting/derogatory comments, and personal attacks
- Public or private harassment
- Publishing others' private information without permission
- Other conduct which could reasonably be considered inappropriate

### Enforcement

Instances of abusive, harassing, or otherwise unacceptable behavior may be reported by contacting joe.nassar.tech@gmail.com. All complaints will be reviewed and investigated promptly and fairly.

## 📊 Release Process

### Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

### Release Schedule

- **Patch releases** as needed for critical bugs
- **Minor releases** monthly or bi-monthly
- **Major releases** annually or for significant changes

## 🙏 Thank You

Thank you for contributing to Laravel Exponential Lockout! Your contributions help make the Laravel ecosystem more secure and developer-friendly.

Every contribution, no matter how small, is valued and appreciated. Whether you're reporting a bug, suggesting a feature, improving documentation, or contributing code, you're helping make this package better for everyone.

---

**Maintainer:** Joe Nassar  
**Email:** joe.nassar.tech@gmail.com  
**GitHub:** [@joenassar](https://github.com/joenassar)

Happy contributing! 🚀