# Flux Core

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net)

Flux Core is a lightweight Laravel package for license activation validation with route-level protection for administrative paths.

## Features

- Automatic package discovery via Laravel service provider
- Cached remote license validation to reduce API calls
- Expiration-aware activation checks using `expires_at`
- Admin route protection for `/admin` and `/admin-panel`
- Global helper function for quick activation checks

## Requirements

- PHP 7.4 or higher
- Laravel application with package auto-discovery support

## Installation

```bash
composer require rixetbd/flux-core
```

## Configuration

Set the following environment variables in your `.env` file:

```env
LICENSE_ADMIN_PANEL_USERNAME=your_username
LICENSE_ADMIN_PANEL_PURCHASE_KEY=your_purchase_key
LICENSE_ADMIN_PANEL_SOFTWARE_ID=your_software_id
```

## How It Works

1. The service provider runs an activation check during `boot()`.
2. License data is requested from the remote API and cached.
3. Activation is considered valid only when:
	- `active` resolves to `true`, and
	- `expires_at` is greater than current time.
4. If activation fails, requests to `/admin` or `/admin-panel` are blocked with HTTP `403`.

## Usage

Use the global helper anywhere in your application:

```php
$isActive = checkActivationCache('admin_panel');
```

You can also call the checker directly:

```php
$checker = new \Rixetbd\FluxCore\LicenseChecker();
$isActive = $checker->checkActivationCache('admin_panel');
```

## Caching Behavior

- License payloads are cached for 1 day.
- If old/stale cache contains invalid format, the package refreshes and rewrites it.
- If the license API is temporarily unreachable, the checker returns a safe inactive payload.

## Notes

- Route blocking currently targets only first URL segment values: `admin` and `admin-panel`.
- The package includes obfuscated API field and endpoint strings internally.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).