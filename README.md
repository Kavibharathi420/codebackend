# Laravel API

Laravel 12 + MySQL API for the academy React frontend. All JSON routes use the `/api` prefix; uploaded raster images are served from `/src/assets`.

See the [root README](../README.md) for installation, admin provisioning, role permissions, moderation and deployment settings.

Run `php tests/security.php` (or `composer test`) for isolated MySQL integration checks. The configured database user must be able to create and drop temporary `simatrix_test_*` databases. Run `php artisan route:list --path=api` to inspect the routes.

The API accepts only validated resource fields. Invalid values return JSON validation errors with status 422; unauthenticated requests return 401 and unauthorized operations return 403. Staff may handle enquiries, while settings, account changes, uploads, publication and deletion require the admin role.
