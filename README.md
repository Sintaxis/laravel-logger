# CrudLog Laravel Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/crudlog/laravel-logger.svg?style=flat-square)](https://packagist.org/packages/crudlog/laravel-logger)
[![Total Downloads](https://img.shields.io/packagist/dt/crudlog/laravel-logger.svg?style=flat-square)](https://packagist.org/packages/crudlog/laravel-logger)

The official CrudLog client package for Laravel applications. This package provides a seamless, "zero-code on models" experience for implicitly logging Eloquent model events to your CrudLog account.

## Features

- **Implicit Logging:** Automatically log `created`, `updated`, and `deleted` events for any Eloquent model.
- **Configuration Driven:** All settings are managed from your CrudLog dashboard—no need to define what to log in your code.
- **Asynchronous by Default:** Uses your application's queue for maximum performance, ensuring your user requests are never slowed down by logging.
- **Tenant-Aware:** Securely associates all logs with your specific account.

## Installation

You can install the package via Composer:

```bash
composer require crudlog/laravel-logger
```

The package will automatically register its service provider.

Next, you must publish the configuration file using the `vendor:publish` command:

```bash
php artisan vendor:publish --provider="CrudLog\Logger\Providers\LoggableServiceProvider" --tag="crudlog-config"
```

This will create a `config/logging-service.php` file in your application.

## Configuration

Finally, add your CrudLog API Key and preferred dispatch method to your application's `.env` file. You can generate an API key from your [CrudLog API Keys dashboard](https://crudlog.com/account/api-keys).

```dotenv
CRUDLOG_API_KEY="your-api-key-here"
CRUDLOG_DISPATCH_METHOD=async # Can be 'async' (recommended) or 'sync'
```

## Usage

Once the package is installed and configured, no further code changes are needed in your application to start logging.

1.  **Log in** to your CrudLog dashboard.
2.  Navigate to the **Logging Configuration** page.
3.  **Enable Implicit Logging**.
4.  Add the **fully qualified class names** of the Eloquent models you wish to track (e.g., `App\Models\User`).
5.  Select the events (`created`, `updated`, `deleted`) you want to monitor for each model.
6.  **Save** your configuration.

Our service will now automatically start capturing events for the models you've configured.

## Security

If you discover any security related issues, please email [support@crudlog.com](mailto:support@crudlog.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.