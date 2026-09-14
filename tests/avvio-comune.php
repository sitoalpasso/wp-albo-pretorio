<?php
/**
 * Parti comuni ai tre avvii della suite.
 *
 * Le prove dell'unita' di avvio hanno bisogno di tre ambienti diversi, e la
 * differenza sta tutta nel componente comune: presente e compatibile, assente,
 * presente con una versione di interfaccia incompatibile. La costante che
 * dichiara quella versione si definisce una volta sola per processo, quindi i
 * tre ambienti non possono convivere nella stessa esecuzione: sono tre avvii
 * separati che condividono questo file.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

/**
 * Percorso della suite di test di WordPress, o interrompe con una diagnosi.
 *
 * @return string
 */
function albo_pretorio_dir_test(): string {
	$dir = getenv( 'WP_TESTS_DIR' );

	if ( ! $dir ) {
		$dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
	}

	if ( ! file_exists( $dir . '/includes/functions.php' ) ) {
		fwrite( STDERR, "Suite di test di WordPress non trovata in {$dir}.\n" );
		fwrite( STDERR, "Impostare WP_TESTS_DIR oppure eseguire i test dentro wp-env.\n" );

		exit( 1 );
	}

	return $dir;
}

/**
 * Percorso del file principale del componente comune, o stringa vuota.
 *
 * @return string
 */
function albo_pretorio_file_core(): string {
	$core = getenv( 'CONFORMITA_CORE_FILE' );

	if ( ! $core ) {
		$radice = getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) : '/tmp/wordpress';
		$core   = $radice . '/wp-content/plugins/conformita-core/conformita-core.php';
	}

	return file_exists( $core ) ? $core : '';
}

/**
 * Prepara la suite, con o senza il componente comune.
 *
 * @param bool $con_core Carica il componente comune prima dell'albo.
 */
function albo_pretorio_prepara_suite( bool $con_core ): void {
	$dir_test = albo_pretorio_dir_test();

	require_once $dir_test . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $con_core ): void {
			if ( $con_core ) {
				$core = albo_pretorio_file_core();

				if ( '' === $core ) {
					fwrite( STDERR, "Componente comune non trovato: eseguire bin/installa-core.sh.\n" );

					exit( 1 );
				}

				require_once $core;
			}

			require dirname( __DIR__ ) . '/albo-pretorio-pa.php';
		}
	);

	require $dir_test . '/includes/bootstrap.php';
}
