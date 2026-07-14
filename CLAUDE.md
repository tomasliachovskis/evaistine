# Working in this repo

- This is a Laravel app run via Sail (Docker). Always prefix PHP/artisan/composer commands with `sail` (e.g. `sail artisan tinker`, `sail composer install`) instead of running `php`/`composer` directly — the host PHP version does not satisfy this project's requirements.
- Follow existing project patterns and reuse existing components/helpers instead of introducing new ones when something equivalent already exists.
