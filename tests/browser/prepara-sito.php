<?php
/**
 * Prepara un sito vero per la prova nel browser della pagina pubblica (ALBO-16, ALBO-28).
 *
 * Si esegue con `wp eval-file` su un sito appena installato, con il componente
 * comune e l'albo attivi. Scrive gli atti con i passaggi veri dell'albo, come li
 * scriverebbe chi pubblica, e stampa in JSON gli indirizzi delle pagine da
 * esaminare. L'ora e' quella vera: gli atti si pubblicano adesso e restano in
 * pubblicazione per i quindici giorni del loro tipo, molto piu' della prova.
 *
 * Il server di prova di PHP non legge le regole della cartella protetta dei
 * documenti, e il meccanismo comune rifiuta di depositare dove non la vede
 * protetta. Per il solo deposito la verifica riceve la risposta di un server
 * che protegge la cartella: la protezione dei file non e' oggetto di questa
 * prova, ha le sue righe nel collaudo del meccanismo comune.
 *
 * Senza `declare( strict_types = 1 )`: wp-cli esegue il file con `eval()`, che
 * non la accetta.
 *
 * @package AlboPretorioPa
 */

namespace AlboPretorioPa\Tests\Browser;

use AlboPretorioPa\Durate;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Festivi;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Repertorio;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

defined( 'ABSPATH' ) || exit;

/**
 * Ferma la preparazione con un messaggio.
 *
 * @param string $messaggio Cosa non e' riuscito.
 * @param mixed  $esito     Esito ricevuto.
 */
function ferma( string $messaggio, $esito = null ): void {
	if ( is_wp_error( $esito ) ) {
		$messaggio .= ': ' . implode( ' ', $esito->get_error_messages() );
	}

	fwrite( STDERR, $messaggio . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- riga di comando.
	exit( 1 );
}

/**
 * Un utente con il ruolo indicato.
 *
 * @param string $nome  Nome di accesso.
 * @param string $ruolo Ruolo.
 * @return int
 */
function utente( string $nome, string $ruolo ): int {
	$id = wp_insert_user(
		array(
			'user_login' => $nome,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $nome . '@example.org',
			'role'       => $ruolo,
		)
	);

	if ( is_wp_error( $id ) ) {
		ferma( 'Utente non creato', $id );
	}

	return (int) $id;
}

/**
 * Una voce di un elenco.
 *
 * @param string $tassonomia Elenco.
 * @param string $nome       Nome.
 * @return int
 */
function voce( string $tassonomia, string $nome ): int {
	$esito = wp_insert_term( $nome, $tassonomia );

	if ( is_wp_error( $esito ) ) {
		ferma( 'Voce non creata', $esito );
	}

	return (int) $esito['term_id'];
}

/**
 * Deposita un PDF minimo sull'atto.
 *
 * @param int    $atto  Atto.
 * @param string $ruolo Documento principale o allegato.
 * @param string $nome  Nome del file.
 */
function documento( int $atto, string $ruolo, string $nome ): void {
	$percorso  = wp_tempnam( $nome );
	$contenuto = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n% " . wp_generate_password( 12, false ) . "\n";

	file_put_contents( $percorso, $contenuto ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- il file deve esistere sul disco.

	$esito = DocumentiAtto::deposita(
		$atto,
		array(
			'name'     => $nome,
			'tmp_name' => $percorso,
			'type'     => '',
			'size'     => strlen( $contenuto ),
			'error'    => 0,
		),
		$ruolo,
		'percorso_locale'
	);

	if ( ! is_int( $esito ) ) {
		ferma( 'Documento non depositato', $esito );
	}
}

/**
 * Un atto scritto, mandato in verifica e pubblicato con i passaggi veri.
 *
 * @param string             $oggetto  Oggetto.
 * @param int                $tipo     Tipo di atto.
 * @param int                $organo   Organo.
 * @param int                $autore   Chi redige.
 * @param int                $editore  Chi pubblica.
 * @param array<int, string> $allegati Nomi degli allegati ulteriori.
 * @return int
 */
function pubblicato( string $oggetto, int $tipo, int $organo, int $autore, int $editore, array $allegati = array() ): int {
	wp_set_current_user( $autore );

	$id = wp_insert_post(
		array(
			'post_type'    => TIPO,
			'post_status'  => 'draft',
			'post_title'   => $oggetto,
			'post_content' => 'Testo dell\'atto.',
			'post_author'  => $autore,
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		ferma( 'Atto non creato', $id );
	}

	wp_set_object_terms( $id, array( $tipo ), TASSONOMIA_TIPO_ATTO );
	wp_set_object_terms( $id, array( $organo ), TASSONOMIA_ORGANO );
	update_post_meta( $id, META_DATA_ADOZIONE, wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) );

	documento( $id, DocumentiAtto::PRINCIPALE, 'atto.pdf' );

	foreach ( $allegati as $nome ) {
		documento( $id, DocumentiAtto::ULTERIORE, $nome );
	}

	$verifica = wp_update_post(
		array(
			'ID'          => $id,
			'post_status' => 'pending',
		),
		true
	);

	if ( is_wp_error( $verifica ) ) {
		ferma( 'Atto non mandato in verifica', $verifica );
	}

	clean_post_cache( $id );
	wp_set_current_user( $editore );

	$esito = albo_pretorio_pubblica( $id, array( 'conferma' => true ) );

	if ( is_wp_error( $esito ) ) {
		ferma( 'Atto non pubblicato', $esito );
	}

	clean_post_cache( $id );

	return (int) $id;
}

update_option( 'timezone_string', 'Europe/Rome' );
update_option( 'blogname', 'Sito di prova' );

$albo_pretorio_server = static function ( $esito, $argomenti, $indirizzo ) {
	unset( $esito, $argomenti, $indirizzo );

	return array(
		'headers'  => array(),
		'body'     => '<html><body>Forbidden</body></html>',
		'response' => array(
			'code'    => 403,
			'message' => 'Forbidden',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};
add_filter( 'pre_http_request', $albo_pretorio_server, 10, 3 );

\Conformita_Core_Allegati::azzera();
$albo_pretorio_protezione = conformita_core_verifica_protezione_allegati();

if ( 'verificata' !== ( $albo_pretorio_protezione['copertura'] ?? '' ) ) {
	ferma( 'La protezione della cartella dei documenti non risulta verificata' );
}

$albo_pretorio_amministratore = (int) ( get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0] ?? 0 );

if ( $albo_pretorio_amministratore < 1 ) {
	ferma( 'Nessun amministratore sul sito' );
}

wp_set_current_user( $albo_pretorio_amministratore );

$albo_pretorio_redattore = utente( 'redattore', RUOLO );
$albo_pretorio_editore   = utente( 'editore', RUOLO );

$albo_pretorio_normativo = voce( TASSONOMIA_TIPO_ATTO, 'Deliberazione' );
$albo_pretorio_scelto    = voce( TASSONOMIA_TIPO_ATTO, 'Avviso' );
$albo_pretorio_organo    = voce( TASSONOMIA_ORGANO, 'Giunta' );

foreach ( array(
	array( $albo_pretorio_normativo, 15, 'norma', 'Estremi di prova' ),
	array( $albo_pretorio_scelto, 15, 'amministrazione', '' ),
) as $albo_pretorio_durata ) {
	$albo_pretorio_esito = Durate::configura( ...$albo_pretorio_durata );

	if ( true !== $albo_pretorio_esito ) {
		ferma( 'Durata non configurata', $albo_pretorio_esito );
	}
}

$albo_pretorio_esito = Repertorio::dichiara( 0, $albo_pretorio_amministratore, Passaggi::ora() );

if ( true !== $albo_pretorio_esito ) {
	ferma( 'Partenza della numerazione non dichiarata', $albo_pretorio_esito );
}

$albo_pretorio_esito = Festivi::salva( Festivi::SPENTO, '', $albo_pretorio_amministratore, Passaggi::ora() );

if ( is_wp_error( $albo_pretorio_esito ) ) {
	ferma( 'Scelta sui festivi non salvata', $albo_pretorio_esito );
}

// Ventuno atti: l'elenco ha una seconda pagina. Il primo ha due allegati, per la scheda.
$albo_pretorio_scheda = pubblicato( 'Deliberazione sulla manutenzione dei sentieri', $albo_pretorio_normativo, $albo_pretorio_organo, $albo_pretorio_redattore, $albo_pretorio_editore, array( 'planimetria.pdf', 'computo.pdf' ) );

$albo_pretorio_oggetti = array( get_the_title( $albo_pretorio_scheda ) );
$albo_pretorio_avvisi  = array();

for ( $albo_pretorio_n = 2; $albo_pretorio_n <= 21; $albo_pretorio_n++ ) {
	$albo_pretorio_oggetto   = sprintf( 'Avviso di prova numero %d', $albo_pretorio_n );
	$albo_pretorio_oggetti[] = $albo_pretorio_oggetto;

	if ( 0 === $albo_pretorio_n % 2 ) {
		$albo_pretorio_avvisi[] = $albo_pretorio_oggetto;
	}

	pubblicato(
		$albo_pretorio_oggetto,
		0 === $albo_pretorio_n % 2 ? $albo_pretorio_scelto : $albo_pretorio_normativo,
		$albo_pretorio_organo,
		$albo_pretorio_redattore,
		$albo_pretorio_editore
	);
}

remove_filter( 'pre_http_request', $albo_pretorio_server, 10 );
wp_set_current_user( $albo_pretorio_amministratore );

$albo_pretorio_pagina = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Albo pretorio',
		'post_name'    => 'albo-pretorio',
		'post_content' => "<!-- wp:paragraph -->\n<p>Gli atti in pubblicazione.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[albo_pretorio]\n<!-- /wp:shortcode -->",
	),
	true
);

if ( is_wp_error( $albo_pretorio_pagina ) ) {
	ferma( 'Pagina dell\'albo non creata', $albo_pretorio_pagina );
}

$albo_pretorio_base = (string) get_permalink( $albo_pretorio_pagina );

echo wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON per lo script della prova, su riga di comando.
	array(
		'elenco'                  => $albo_pretorio_base,
		'seconda pagina'          => add_query_arg( 'albo_pagina', 2, $albo_pretorio_base ),
		'ricerca con un filtro'   => add_query_arg( 'albo_tipo', $albo_pretorio_scelto, $albo_pretorio_base ),
		'ricerca senza risultati' => add_query_arg( 'albo_testo', 'nessunaparolacosi', $albo_pretorio_base ),
		'ricerca scritta male'    => add_query_arg( 'albo_dal', '2041-02-31', $albo_pretorio_base ),
		'scheda con allegati'     => add_query_arg( 'albo_atto', $albo_pretorio_scheda, $albo_pretorio_base ),
		'tipo scelto'             => $albo_pretorio_scelto,
		'oggetto della scheda'    => get_the_title( $albo_pretorio_scheda ),
		// Cio' che ogni pagina deve mostrare: la prova lo confronta prima di esaminarla.
		'oggetti'                 => $albo_pretorio_oggetti,
		'oggetti del tipo scelto' => $albo_pretorio_avvisi,
		'documenti della scheda'  => array( 'Documento principale', 'Allegato 1', 'Allegato 2' ),
		'atti per pagina'         => \AlboPretorioPa\VistaPubblica::PER_PAGINA,
	)
);
echo "\n";
