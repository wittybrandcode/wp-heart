<?php
/**
 * Error sanitization tests (WH-084).
 *
 * @package WP_Heart_Tests
 */

use WPHeart\Security\ErrorSanitizer;

wh_group( 'ErrorSanitizer' );

wh_check(
	false !== strpos( ErrorSanitizer::sanitize( 'Failed in C:\\xampp\\htdocs\\wp\\file.php on line 3' ), '[path]' ),
	'windows path stripped'
);
wh_check(
	false !== strpos( ErrorSanitizer::sanitize( 'Error at /var/www/html/wp-config.php' ), '[path]' ),
	'unix path stripped'
);
wh_check(
	false !== strpos( ErrorSanitizer::sanitize( "Access denied password: 's3cret' for user" ), '[redacted]' ),
	'credential fragment redacted'
);
wh_check(
	false === strpos( ErrorSanitizer::sanitize( 'WordPress database error Table crashed' ), 'WordPress database error' ),
	'db prefix noise removed'
);
wh_check(
	'An unexpected database error occurred.' === ErrorSanitizer::sanitize( '' ),
	'empty becomes generic message'
);
wh_check(
	500 >= mb_strlen( ErrorSanitizer::sanitize( str_repeat( 'x', 5000 ) ) ),
	'long messages truncated'
);

$err = ErrorSanitizer::rest_error( 'wp_heart_x', 'boom in /etc/passwd file', 500 );
wh_check( $err instanceof WP_Error && 500 === $err->get_error_data()['status'], 'rest error envelope' );
wh_check( false === strpos( $err->get_error_message(), '/etc/passwd' ), 'rest message sanitized' );
