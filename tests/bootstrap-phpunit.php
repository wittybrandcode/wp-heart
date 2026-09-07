<?php
/**
 * PHPUnit bootstrap for CI (phpunit.xml.dist). Falls back to plain stubs
 * when the WordPress test suite is unavailable.
 *
 * @package WP_Heart_Tests
 */

require_once __DIR__ . '/support/stubs.php';
require_once dirname( __DIR__ ) . '/autoload.php';
require_once __DIR__ . '/support/Fakes.php';
require_once __DIR__ . '/support/container.php';
