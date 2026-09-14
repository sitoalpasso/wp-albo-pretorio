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
 * **Perche' l'avvio automatico si puo' spegnere.** Caricare il file principale
 * dell'albo aggancia la procedura di avvio a `plugins_loaded`, che l'avvio della
 * suite esegue subito dopo. In un ambiente dove quella procedura fallisce, il
 * fallimento avviene una volta prima che qualunque prova cominci, e lascia dietro
 * di se' cio' che il fallimento produce. Nel caso della versione incompatibile
 * cio' che resta e' un avviso agganciato dal componente comune con una chiusura
 * anonima, che non e' rimuovibile ne' azzerabile da fuori: una prova successiva
 * lo troverebbe in bacheca e lo scambierebbe per il proprio. Togliendo l'aggancio
 * subito dopo il caricamento, l'unica esecuzione della procedura in quel processo
 * e' quella che la prova fa di persona.
 *
 * Non si spegne dove non serve: negli altri due ambienti l'avvio automatico non
 * lascia niente che le prove non sappiano azzerare, e lasciarlo acceso mantiene
 * l'aggancio osservabile dalla riga A-02.
 *
 * @param bool $con_core         Carica il componente comune prima dell'albo.
 * @param bool $avvio_automatico Lascia agganciata la procedura di avvio a plugins_loaded.
 */
function albo_pretorio_prepara_suite( bool $con_core, bool $avvio_automatico = true ): void {
	$dir_test = albo_pretorio_dir_test();

	require_once $dir_test . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $con_core, $avvio_automatico ): void {
			if ( $con_core ) {
				$core = albo_pretorio_file_core();

				if ( '' === $core ) {
					fwrite( STDERR, "Componente comune non trovato: eseguire bin/installa-core.sh.\n" );

					exit( 1 );
				}

				require_once $core;
			}

			require dirname( __DIR__ ) . '/albo-pretorio-pa.php';

			if ( ! $avvio_automatico ) {
				remove_action( 'plugins_loaded', array( 'AlboPretorioPa\\Avvio', 'da_plugins_loaded' ) );
			}
		}
	);

	require $dir_test . '/includes/bootstrap.php';
}
