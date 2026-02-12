# Versions

This document lists the dependency versions this repo is currently built and tested against.

Source of truth:
- `composer.json` for PHP/Laravel/Filament/Livewire
- `package.json` for Node/Vite/Tailwind/Alpine
- `composer.lock` and `package-lock.json` for exact patch versions

Update this file on every release that changes dependencies.

## Backend
- PHP (constraint): `^8.2`
- Laravel (locked): `12.49.0`
- Filament (locked): `4.6.3`
- Livewire (locked): `3.7.8`
- Socialite (locked): `5.24.2`

## Billing adapters
- Stripe SDK (locked): `19.3.0`
- Paddle SDK: not pinned (adapter is implemented without an official SDK package)

## Frontend
- Node.js: see `README.md` requirements
- Vite (locked): `7.3.0`
- Tailwind CSS (locked): `3.4.19`
- Alpine.js (locked): `3.15.3`

## Tooling
- Pint (locked): `1.27.0`
- Test runner (locked): `PHPUnit 11.5.50`
- PHPStan/Larastan: not installed
