<?php
/**
 * Processo figlio della prova di concorrenza dell'elenco delle amministrazioni ospitate (A-256).
 *
 * Come `assegna.php`: WordPress sulla stessa banca dati della suite, fuori
 * dalla suite, con una connessione sua. Al segnale comune di partenza
 * aggiunge all'elenco, una per salvataggio, le voci che gli sono state date,
 * e stampa gli esiti in JSON. Non e' una prova da sola: la lancia
 * `AmministrazioniOspitateTest`.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.PrefixAllGlobals

$albo_conf = json_decode( (string) getenv( 'ALBO_CONCORRENZA' ), true );

if ( ! is_array( $albo_conf ) ) {
	fwrite( STDERR, "Configurazione del processo figlio assente.\n" );
	exit( 2 );
}

define( 'ABSPATH', $albo_conf['abspath'] );
define( 'DB_NAME', $albo_conf['db_name'] );
define( 'DB_USER', $albo_conf['db_user'] );
define( 'DB_PASSWORD', $albo_conf['db_password'] );
define( 'DB_HOST', $albo_conf['db_host'] );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = $albo_conf['prefisso'];

$_SERVER['HTTP_HOST']       = 'example.org';
$_SERVER['SERVER_NAME']     = 'example.org';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

/*
 * Stesso modo della suite per caricare i componenti: un aggancio preparato
 * prima che WordPress esista, che WordPress trasforma in un aggancio vero.
 */
$GLOBALS['wp_filter'] = array(
	'muplugins_loaded' => array(
		10 => array(
			'albo_concorrenza' => array(
				'function'      => static function () use ( $albo_conf ): void {
					require_once $albo_conf['core'];
					require_once $albo_conf['albo'];
				},
				'accepted_args' => 0,
			),
		),
	),
);

require ABSPATH . 'wp-settings.php';

file_put_contents( $albo_conf['cartella'] . '/pronto-' . $albo_conf['indice'], '1' );

$albo_via    = $albo_conf['cartella'] . '/via';
$albo_limite = microtime( true ) + 60;

while ( ! file_exists( $albo_via ) ) {
	if ( microtime( true ) > $albo_limite ) {
		fwrite( STDERR, "Segnale di partenza mai arrivato.\n" );
		exit( 3 );
	}

	usleep( 200 );
}

$albo_esiti  = array();
$albo_inizio = microtime( true );

foreach ( $albo_conf['nomi'] as $albo_nome ) {
	$albo_esito   = \AlboPretorioPa\AmministrazioniOspitate::salva( array(), array(), $albo_nome, 1, new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) );
	$albo_esiti[] = array(
		'nome'  => $albo_nome,
		'esito' => is_wp_error( $albo_esito ) ? $albo_esito->get_error_code() : $albo_esito,
	);
}

$albo_fine = microtime( true );

echo wp_json_encode(
	array(
		'inizio' => $albo_inizio,
		'fine'   => $albo_fine,
		'esiti'  => $albo_esiti,
	)
);
