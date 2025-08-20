# CrudLog Laravel Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/crudlog/laravel-logger.svg?style=flat-square)](https://packagist.org/packages/crudlog/laravel-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/crudlog/laravel-logger.svg?style=flat-square)](https://packagist.org/packages/crudlog/laravel-logger)

**This is the official client package for the CrudLog service. An account at [CrudLog.com](https://crudlog.com) is required to use this package.**

CrudLog provides a complete, managed service for activity logging and audit trails. This Laravel package is the easiest way to implement "implicit logging" by automatically capturing Eloquent model events without changing your existing code.

---

## What is CrudLog?

CrudLog is a SaaS platform that gives developers a powerful, framework-agnostic solution for logging user activities. Stop building audit trails from scratch. Our service provides:

- A secure, scalable backend to store your log data.
- A beautiful web dashboard to view, search, and filter logs.
- A flexible REST API to send and retrieve log data from any application.
- Configurable data masking, retention policies, and plan-based usage limits.

**[Create Your Free Account at CrudLog.com](https://crudlog.com/register)**

---

## Installation

You can install the package into your Laravel 11+ project via Composer:

```bash
composer require crudlog/laravel-logger
```

The package will automatically register its service provider.

Next, you must publish the configuration file:

```bash
php artisan vendor:publish --provider="CrudLog\Logger\Providers\LoggableServiceProvider" --tag="crudlog-config"
```

This will create a `config/logging-service.php` file in your application.

## Configuration

Finally, add your CrudLog API Key to your application's `.env` file.

1.  **Sign up** for a free account at [CrudLog.com](https://crudlog.com).
2.  Navigate to your **Account -> API Keys** dashboard and generate a new key.
3.  Add the key to your `.env` file:

```dotenv
CRUDLOG_API_KEY="your-api-key-here"

# Optional: You can also specify the dispatch method ('async' or 'sync').
# 'async' is recommended for best performance but requires a queue worker.
CRUDLOG_DISPATCH_METHOD=async
```

## Usage

Once the package is installed and configured, all logging rules are managed from your CrudLog dashboard.

1.  Log in to your CrudLog account.
2.  Navigate to the **Logging Configuration** page.
3.  **Enable Implicit Logging** and add the fully qualified class names of the Eloquent models you wish to track (e.g., `App\Models\User`).

For more detailed instructions and advanced usage, please see our full **[Documentation on CrudLog.com](https://crudlog.com/docs)**.

## Security

If you discover any security related issues, please email [support@crudlog.com](mailto:support@crudlog.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.