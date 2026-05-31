# Holiday Calendar tests

PHPUnit integration tests using the [WordPress test framework](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/).

## Setup (one time)

1. Install PHP dependencies from the plugin root:

   ```bash
   composer install
   ```

2. Install the WordPress test library and a test database. From Git Bash or WSL:

   ```bash
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

   Adjust database name, user, password, and host for your environment. On Windows PowerShell, set `WP_TESTS_DIR` manually if you already have the test library elsewhere.

3. Optional: point to a custom test library path:

   ```bash
   export WP_TESTS_DIR=/path/to/wordpress-tests-lib
   ```

## Run tests

From the plugin root:

```bash
composer test
```

Or:

```bash
vendor/bin/phpunit
```
