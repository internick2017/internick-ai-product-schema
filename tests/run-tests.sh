#!/usr/bin/env bash
# Dev helper: run PHPUnit inside DDEV with the right test config, and log the
# output to tests/phpunit.out (readable from the host).
set -o pipefail
cd /var/www/html || exit 1
export WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-tests-config.php
export WP_TESTS_CONFIG_FILE_PATH=/var/www/html/wp-tests-config.php
vendor/bin/phpunit "$@" 2>&1 | tee tests/phpunit.out
code=${PIPESTATUS[0]}
echo "PHPUNIT_EXIT=$code" | tee -a tests/phpunit.out
exit "$code"
