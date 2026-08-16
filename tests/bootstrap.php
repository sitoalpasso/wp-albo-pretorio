<?php
/**
 * Avvio della suite di test sulla suite di WordPress.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

$albo_pretorio_dir_test = getenv( 'WP_TESTS_DIR' );

if ( ! $albo_pretorio_dir_test ) {
	$albo_pretorio_dir_test = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $albo_pretorio_dir_test . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Suite di test di WordPress non trovata in {$albo_pretorio_dir_test}.\n" );
	fwrite( STDERR, "Impostare WP_TESTS_DIR oppure eseguire i test dentro wp-env.\n" );

	exit( 1 );
}

require_once $albo_pretorio_dir_test . '/includes/functions.php';

/**
 * Carica il plugin prima dell'avvio di WordPress.
 */
function albo_pretorio_carica_plugin(): void {
	require dirname( __DIR__ ) . '/albo-pretorio-pa.php';
}

tests_add_filter( 'muplugins_loaded', 'albo_pretorio_carica_plugin' );

require $albo_pretorio_dir_test . '/includes/bootstrap.php';
