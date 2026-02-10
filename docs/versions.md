# Versions

Fill this file with the exact major/minor versions pinned in this repo.

Source of truth:
- `composer.json` for PHP/Laravel/Filament/Livewire
- `package.json` for Node/Vite/Tailwind/Alpine
- `composer.lock` and `package-lock.json` for exact patch versions

Update this file on every release that changes dependencies.

## Backend
- PHP: >= 8.2
- Laravel: 12.x
- Filament: 4.4.x
- Livewire: 3.7.x
- Social login: Socialite (not installed)

## Billing adapters
- Stripe SDK or package: stripe/stripe-php 19.x
- Paddle SDK or package: not installed

## Frontend
- Node.js: >= 20
- Vite: 7.x
- Tailwind CSS: 3.1.x
- Alpine.js: 3.4.x

## Tooling
- Pint: 1.x
- PHPStan/Larastan: not installed
- Test runner: PHPUnit 11.x
