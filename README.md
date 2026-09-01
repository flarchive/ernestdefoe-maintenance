# Maintenance

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/ernestdefoe/maintenance.svg)](https://packagist.org/packages/ernestdefoe/maintenance)

A [Flarum 2](https://flarum.org) extension. Adds **Run Migrations** and **Publish Assets** to the admin dashboard's tools menu — right beside Clear Cache, System Info and Toggle Advanced Page — so you can finish an extension install or update without ever opening a terminal.

## Features

- **Run Migrations** — the in-process equivalent of `php flarum migrate`: runs outstanding core and extension migrations, then reloads the admin so everything picks up the changes.
- **Publish Assets** — the equivalent of `php flarum assets:publish`: republishes core fonts and every enabled extension's assets.
- Admin-only (both endpoints reject non-admins), and each run reports exactly what it did.
- Perfect companion to the Extension Manager on shared hosting.
- Fully translatable.

## Installation

```sh
composer require ernestdefoe/maintenance
php flarum cache:clear
```

## Updating

```sh
composer update ernestdefoe/maintenance
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/ernestdefoe/maintenance)
- [GitHub](https://github.com/ernestdefoe/maintenance)
