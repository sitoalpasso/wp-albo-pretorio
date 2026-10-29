<?php
/**
 * La pagina pubblica dello storico: righe A-272..A-277 (ALBO-36).
 *
 * Le prove fanno richieste vere alla pagina che contiene il codice breve
 * dello storico, con l'atto pubblicato dal passaggio vero, la copia messa
 * dall'ingresso vero e l'orologio fissato. Ogni assenza ha accanto la sua
 * presenza di controllo: la stessa copia che nello stesso istante si vede, o
 * lo stesso documento che si scarica.
 *
 * L'atto di prova e' pubblicato il primo ottobre 2041 e finisce il 16; la
 * copia entra il 20 con tre anni, e finisce il 20 ottobre 2044.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\CopieStorico;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\PaginaPubblica;
use AlboPretorioPa\PaginaStorico;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Storico;
use AlboPretorioPa\VistaStorico;

use const AlboPretorioPa\TIPO_STORICO;

/**
 * Elenco, scheda, documento, motori e memorie della pagina dello storico.
 */
class PaginaStoricoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Il giorno in cui la copia entra: l'atto e' scaduto da qualche giorno.
	 */
	private const INGRESSO = '2041-10-20 10:00:00';

	/**
	 * Un giorno in cui la copia e' nello storico.
	 */
	private const DURANTE = '2042-03-10 10:00:00';

	/**
	 * Le risposte del punto pubblico di consegna.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $consegne = array();

	/**
	 * Le righe di intestazione mandate per conto della pagina.
	 *
	 * @var array<int, string>
	 */
	private array $righe = array();

	/**
	 * Le intestazioni mandate da WordPress nell'ultima richiesta.
	 *
	 * @var array<string, mixed>
	 */
	private array $mandate = array();

	/**
	 * Chi gestisce lo storico.
	 *
	 * @var int
	 */
	private int $custode = 0;

	/**
	 * La pagina dello storico.
	 *
	 * @var int
	 */
	private int $pagina = 0;

	/**
	 * Assetto comune, indirizzi semplici, consegna osservata, storico acceso con tre anni.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->set_permalink_structure( '' );
		$this->custode = $this->utente_con( array( Permessi::CHIAVE_STORICO ) );

		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ) {
				$this->consegne[] = $risposta;
			}
		);

		PaginaPubblica::fissa_emettitore(
			function ( string $riga ) {
				$this->righe[] = $riga;
			}
		);

		add_action( 'parse_request', array( $this, 'dimentica_righe' ), 1 );
		add_filter( 'wp_headers', array( $this, 'osserva_intestazioni' ), PHP_INT_MAX );

		$this->imposta( Storico::ACCESO, '3' );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		remove_action( 'parse_request', array( $this, 'dimentica_righe' ), 1 );
		remove_filter( 'wp_headers', array( $this, 'osserva_intestazioni' ), PHP_INT_MAX );
		\Conformita_Core_Consegna::azzera_emettitore();
		PaginaPubblica::fissa_emettitore( null );

		$this->smonta_flusso();
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Le righe contano per l'ultima richiesta soltanto.
	 */
	public function dimentica_righe(): void {
		$this->righe = array();
	}

	/**
	 * Le intestazioni che WordPress sta per mandare, dopo tutti i filtri.
	 *
	 * @param mixed $intestazioni Intestazioni.
	 * @return mixed
	 */
	public function osserva_intestazioni( $intestazioni ) {
		$this->mandate = is_array( $intestazioni ) ? $intestazioni : array();

		return $intestazioni;
	}

	/**
	 * Salva l'impostazione dello storico, e pretende che riesca.
	 *
	 * @param string $scelta Scelta.
	 * @param string $anni   Anni.
	 */
	private function imposta( string $scelta, string $anni ): void {
		$esito = Storico::salva( $scelta, $anni, Storico::stato_mostrato(), $this->amministratore, Passaggi::ora() );

		$this->assertTrue( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
	}

	/**
	 * Una pagina pubblicata con il contenuto indicato.
	 *
	 * @param string $contenuto Contenuto.
	 * @param string $nome      Nome nell'indirizzo.
	 * @return int
	 */
	private function pagina_con( string $contenuto, string $nome ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Pagina ' . $nome,
				'post_name'    => $nome,
				'post_content' => '<p>Testo della pagina.</p>' . "\n\n" . $contenuto,
			)
		);
	}

	/**
	 * La pagina dello storico, riconosciuta.
	 *
	 * @return int
	 */
	private function pagina_storico(): int {
		$this->pagina = $this->pagina_con( '[albo_pretorio_storico]', 'storico' );

		$this->assertSame( array( $this->pagina ), PaginaStorico::pagine(), 'Precondizione: la pagina dello storico e\' riconosciuta.' );

		return $this->pagina;
	}

	/**
	 * Un atto pubblicato il primo ottobre 2041, con la fine il 16.
	 *
	 * @param string $oggetto Oggetto.
	 * @param string $istante Istante della pubblicazione.
	 * @return int
	 */
	private function pubblicato( string $oggetto = 'Concessione a Mario Rossi', string $istante = '2041-10-01 09:30:00' ): int {
		$this->fissa_orologi( $istante );

		$id    = $this->atto_in_verifica( 'normativo', array( 'post_title' => $oggetto ) );
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		return $id;
	}

	/**
	 * La copia di un atto, messa dall'ingresso vero al giorno indicato.
	 *
	 * @param int    $atto    Atto.
	 * @param string $oggetto Oggetto della copia.
	 * @param string $istante Istante dell'ingresso.
	 * @return int
	 */
	private function copia( int $atto, string $oggetto = 'Concessione di un contributo', string $istante = self::INGRESSO ): int {
		$this->fissa_orologi( $istante );

		$file  = $this->file_temporaneo( 'copia-oscurata.pdf' );
		$copia = $this->come(
			$this->custode,
			static function () use ( $atto, $file, $oggetto ) {
				return CopieStorico::metti( $atto, $file, $oggetto, true, 'percorso_locale' );
			}
		);

		$this->assertIsInt( $copia, is_wp_error( $copia ) ? $copia->get_error_message() : '' );

		return $copia;
	}

	/**
	 * L'indirizzo di una pagina con i parametri indicati.
	 *
	 * @param array<string, mixed> $parametri Parametri.
	 * @param int                  $pagina    Pagina, quella dello storico se zero.
	 * @return string
	 */
	private function indirizzo( array $parametri = array(), int $pagina = 0 ): string {
		return add_query_arg( $parametri, (string) get_permalink( 0 === $pagina ? $this->pagina : $pagina ) );
	}

	/**
	 * Una richiesta vera, e quello che la pagina disegna; nullo se "non trovata".
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return string|null
	 */
	private function pagina_disegnata( string $indirizzo ): ?string {
		unset( $GLOBALS['post'], $GLOBALS['posts'], $GLOBALS['more'], $GLOBALS['single'] );
		$this->richiesta_pubblica( $indirizzo );

		if ( is_404() ) {
			return null;
		}

		$pagina = get_queried_object();

		$this->assertInstanceOf( \WP_Post::class, $pagina, 'Precondizione: la richiesta apre una pagina.' );

		$GLOBALS['post'] = $pagina;
		setup_postdata( $pagina );

		return (string) apply_filters( 'the_content', $pagina->post_content );
	}

	/**
	 * Le copie elencate, nell'ordine.
	 *
	 * @param string $html Codice disegnato.
	 * @return array<int, int>
	 */
	private function copie_elencate( string $html ): array {
		preg_match_all( '/<tr class="albo-pretorio__atto" role="row" data-copia="([0-9]+)"/', $html, $trovate );

		return array_map( 'intval', $trovate[1] );
	}

	/**
	 * Tutto cio' che il punto pubblico di consegna emette per un indirizzo.
	 *
	 * @param string $indirizzo Indirizzo di consegna.
	 * @return array<int, array<string, mixed>>
	 */
	private function consegna( string $indirizzo ): array {
		$this->consegne = array();

		$this->go_to( $indirizzo );

		return $this->consegne;
	}

	/**
	 * La consegna riuscita di un file.
	 *
	 * @param array<int, array<string, mixed>> $consegne Risposte.
	 * @return bool
	 */
	private function consegnato( array $consegne ): bool {
		return 1 === count( $consegne ) && 200 === ( $consegne[0]['stato'] ?? 0 ) && ! empty( $consegne[0]['percorso'] );
	}

	/**
	 * Il divieto ai motori come lo stampa il sorgente della pagina.
	 *
	 * @return string
	 */
	private function robot_stampati(): string {
		ob_start();
		wp_robots();

		return (string) ob_get_clean();
	}

	/**
	 * A-272: spento, niente; acceso, la copia si apre mentre l'atto resta "non trovato".
	 */
	public function test_a272_spento_e_acceso(): void {
		$albo = $this->pagina_con( '[albo_pretorio]', 'albo' );
		$this->pagina_storico();

		$atto  = $this->pubblicato();
		$copia = $this->copia( $atto );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$indirizzo = conformita_core_indirizzo_consegna( (int) CopieStorico::documento( $copia ) );
		$this->assertIsString( $indirizzo, 'Precondizione: il documento ha il suo indirizzo di consegna.' );

		// Acceso: elenco, scheda e documento.
		$elenco = $this->pagina_disegnata( $this->indirizzo() );
		$this->assertSame( array( $copia ), $this->copie_elencate( (string) $elenco ), 'Acceso: la copia e\' nell\'elenco.' );
		$this->assertNotNull( $this->pagina_disegnata( $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $copia ) ) ), 'Acceso: la scheda si apre.' );
		$this->assertTrue( $this->consegnato( $this->consegna( $indirizzo ) ), 'Acceso: il documento oscurato si scarica.' );

		// Nello stesso istante l'atto resta "non trovato" sulla pagina dell'albo, e il suo documento non esce.
		$this->assertNull( $this->pagina_disegnata( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atto ), $albo ) ), 'L\'atto scaduto resta "non trovato".' );
		$this->assertFalse( $this->consegnato( $this->consegna( (string) conformita_core_indirizzo_consegna( (int) DocumentiAtto::principale( $atto ) ) ) ), 'Il documento originale non si scarica.' );

		// Spento: niente sulla pagina, la scheda "non trovata", il documento non esce; la copia resta salvata.
		$this->imposta( Storico::SPENTO, '3' );
		wp_set_current_user( 0 );

		$spenta = $this->pagina_disegnata( $this->indirizzo() );
		$this->assertNotNull( $spenta, 'Spento: la pagina si apre, come ogni pagina del sito.' );
		$this->assertStringNotContainsString( 'albo-pretorio', (string) $spenta, 'Spento: il codice breve non disegna niente.' );
		$this->assertStringNotContainsString( 'Concessione', (string) $spenta, 'Spento: nessun oggetto.' );
		$this->assertNull( $this->pagina_disegnata( $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $copia ) ) ), 'Spento: la scheda e\' "non trovata".' );
		$this->assertFalse( $this->consegnato( $this->consegna( $indirizzo ) ), 'Spento: il documento non si scarica.' );
		$this->assertSame( 'publish', get_post_status( $copia ), 'Spento: la copia resta salvata.' );

		// Riacceso: tutto torna.
		$this->imposta( Storico::ACCESO, '3' );
		wp_set_current_user( 0 );

		$this->assertSame( array( $copia ), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Riacceso: la copia torna nell\'elenco.' );
		$this->assertTrue( $this->consegnato( $this->consegna( $indirizzo ) ), 'Riacceso: il documento torna a scaricarsi.' );
	}

	/**
	 * A-273: l'elenco mostra le copie in vista, dalla piu' recente per numero; la scheda dice che e' una copia.
	 */
	public function test_a273_elenco_e_scheda(): void {
		$this->pagina_storico();

		$primo   = $this->pubblicato( 'Concessione a Mario Rossi', '2041-10-01 09:30:00' );
		$secondo = $this->pubblicato( 'Contributo ad Anna Bianchi', '2041-10-01 10:30:00' );
		$terzo   = $this->pubblicato( 'Rimborso a Carlo Verdi', '2041-10-01 11:30:00' );

		$vecchia = $this->copia( $secondo, 'Contributo per attivita\' culturali' );
		$nuova   = $this->copia( $primo, 'Concessione di un contributo' );
		$tolta   = $this->copia( $terzo, 'Rimborso di spese' );

		$this->assertTrue( $this->come( $this->custode, static fn() => CopieStorico::togli( $tolta, 'dati_nella_copia', 'Prova.' ) ), 'Precondizione: la terza copia e\' tolta.' );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$html = (string) $this->pagina_disegnata( $this->indirizzo() );

		$this->assertSame( array( $vecchia, $nuova ), $this->copie_elencate( $html ), 'Le due copie in vista, il numero di registro piu\' alto per primo; la copia tolta no.' );
		$this->assertStringContainsString( 'copie con i dati personali oscurati', $html, 'L\'elenco dice che sono copie.' );

		foreach ( array( 'Mario Rossi', 'Anna Bianchi', 'Carlo Verdi' ) as $nome ) {
			$this->assertStringNotContainsString( $nome, $html, 'L\'oggetto originale non compare: ' . $nome . '.' );
		}

		$scheda = (string) $this->pagina_disegnata( $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $nuova ) ) );
		$numero = \AlboPretorioPa\Repertorio::di( $primo );

		$this->assertStringContainsString( 'Concessione di un contributo', $scheda, 'Il titolo e\' l\'oggetto della copia.' );
		$this->assertStringNotContainsString( 'Mario Rossi', $scheda, 'Mai l\'oggetto originale.' );
		$this->assertStringContainsString( 'la pubblicazione all&#039;albo e&#039; terminata il 16/10/2041', $scheda, 'L\'avviso con la fine della pubblicazione all\'albo.' );
		$this->assertStringContainsString( '<dd>' . $numero['numero'] . '/' . $numero['anno'] . '</dd>', $scheda, 'Il numero di registro dell\'atto.' );
		$this->assertStringContainsString( '<dd>20/10/2044</dd>', $scheda, 'La fine della copia.' );
		$this->assertStringContainsString( 'Copia oscurata del documento', $scheda, 'Il documento della copia.' );
		$this->assertStringContainsString( esc_url( (string) conformita_core_indirizzo_consegna( (int) CopieStorico::documento( $nuova ) ) ), $scheda, 'Dal punto di consegna.' );
		$this->assertStringNotContainsString( esc_url( (string) conformita_core_indirizzo_consegna( (int) DocumentiAtto::principale( $primo ) ) ), $scheda, 'Mai il documento originale.' );

		$this->assertNull( $this->pagina_disegnata( $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $tolta ) ) ), 'La scheda della copia tolta e\' "non trovata".' );
	}

	/**
	 * A-273: i dati delle copie che non si leggono non fanno sparire le copie in silenzio.
	 *
	 * La lettura di dati e documento delle copie non risponde: l'elenco dice
	 * che le copie non si leggono, non che non ce ne sono, e non mostra un
	 * conteggio sbagliato.
	 */
	public function test_a273_dati_che_non_si_leggono(): void {
		global $wpdb;

		$this->pagina_storico();

		$prima   = $this->copia( $this->pubblicato( 'Concessione a Mario Rossi', '2041-10-01 09:30:00' ) );
		$seconda = $this->copia( $this->pubblicato( 'Contributo ad Anna Bianchi', '2041-10-01 10:30:00' ) );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$this->assertSame( array( $seconda, $prima ), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Controllo positivo: le due copie.' );

		$guasto = static function ( $istruzione ) {
			return 0 === strpos( ltrim( (string) $istruzione ), 'SELECT m.post_id, m.meta_key' ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$zitto  = $wpdb->suppress_errors( true );

		add_filter( 'query', $guasto );

		try {
			$html = (string) $this->pagina_disegnata( $this->indirizzo() );
		} finally {
			remove_filter( 'query', $guasto );
			$wpdb->suppress_errors( $zitto );
		}

		$this->assertSame( array(), $this->copie_elencate( $html ), 'Nessuna riga.' );
		$this->assertStringContainsString( 'Le copie non si leggono in questo momento.', $html, 'L\'elenco dice che la lettura non e\' riuscita.' );
		$this->assertStringNotContainsString( 'non ci sono copie', $html, 'Non dice che non ce ne sono.' );
		$this->assertStringNotContainsString( '1 copia', $html, 'Nessun conteggio sbagliato.' );

		$this->assertSame( array( $seconda, $prima ), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Controllo positivo, dopo: le due copie.' );
	}

	/**
	 * A-274: la copia finisce da sola; prima e dopo la fine, senza il compito pianificato.
	 */
	public function test_a274_fine_della_copia(): void {
		$this->pagina_storico();

		$copia     = $this->copia( $this->pubblicato() );
		$indirizzo = (string) conformita_core_indirizzo_consegna( (int) CopieStorico::documento( $copia ) );

		wp_set_current_user( 0 );

		$this->fissa_orologi( '2044-10-20 23:59:59' );
		$this->assertSame( array( $copia ), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'L\'ultimo istante: la copia si vede.' );
		$this->assertTrue( $this->consegnato( $this->consegna( $indirizzo ) ), 'L\'ultimo istante: il documento si scarica.' );

		$this->fissa_orologi( '2044-10-21 00:00:00' );
		$this->assertSame( array(), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Il primo istante dopo: la copia non c\'e\'.' );
		$this->assertNull( $this->pagina_disegnata( $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $copia ) ) ), 'Il primo istante dopo: la scheda e\' "non trovata".' );
		$this->assertFalse( $this->consegnato( $this->consegna( $indirizzo ) ), 'Il primo istante dopo: il documento non si scarica.' );
	}

	/**
	 * A-275: solo il documento della copia, solo dal suo indirizzo.
	 */
	public function test_a275_solo_il_documento_della_copia(): void {
		$this->pagina_storico();

		$atto      = $this->pubblicato();
		$copia     = $this->copia( $atto );
		$documento = (int) CopieStorico::documento( $copia );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$giusto = (string) conformita_core_indirizzo_consegna( $documento );
		$this->assertTrue( $this->consegnato( $this->consegna( $giusto ) ), 'Controllo positivo: il documento della copia si scarica.' );

		$originale = (int) DocumentiAtto::principale( $atto );
		$scambiato = add_query_arg( \Conformita_Core_Consegna::VAR_ATTO, $copia, (string) conformita_core_indirizzo_consegna( $originale ) );
		$this->assertFalse( $this->consegnato( $this->consegna( $scambiato ) ), 'Il documento originale chiesto con il numero della copia non esce.' );

		$altro = conformita_core_deposita_allegato( $copia, $this->file_temporaneo( 'altro.pdf' ), array( 'origine' => 'percorso_locale' ) );
		$this->assertIsInt( $altro, 'Precondizione: un secondo file depositato sulla copia da un altro componente.' );
		$this->assertFalse( $this->consegnato( $this->consegna( add_query_arg( \Conformita_Core_Consegna::VAR_ALLEGATO, $altro, $giusto ) ) ), 'Un altro allegato appeso alla copia non esce.' );

		$this->assertSame( 0, PaginaStorico::da_oembed_request_post_id( $copia ), 'Nessuna anteprima incorporata della copia.' );
		$this->assertFalse( get_post_type_object( TIPO_STORICO )->publicly_queryable, 'Il tipo resta chiuso agli indirizzi di WordPress.' );

		$this->assertTrue( $this->consegnato( $this->consegna( $giusto ) ), 'Controllo positivo, dopo: il documento della copia si scarica.' );
	}

	/**
	 * A-276: motori e memorie su elenco, scheda e "non trovata"; fuori dalla mappa del sito.
	 */
	public function test_a276_motori_e_memorie(): void {
		$ordinaria = $this->pagina_con( '', 'ordinaria' );
		$this->pagina_storico();

		$atto   = $this->pubblicato();
		$copia  = $this->copia( $atto );
		$numero = \AlboPretorioPa\Repertorio::di( $atto );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$this->pagina_disegnata( (string) get_permalink( $ordinaria ) );
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $this->mandate, 'Controllo positivo: una pagina ordinaria non porta il divieto.' );
		$this->assertStringNotContainsString( 'noindex', $this->robot_stampati(), 'Controllo positivo: nel sorgente di una pagina ordinaria non c\'e\' il divieto.' );

		foreach ( array(
			'elenco'       => $this->indirizzo(),
			'scheda'       => $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $copia ) ),
			'non trovata'  => $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => $copia + 1000 ) ),
			'scritta male' => $this->indirizzo( array( PaginaStorico::PARAMETRO_COPIA => '0' . $copia ) ),
			'pagina oltre' => $this->indirizzo( array( VistaStorico::CAMPO_PAGINA => 9 ) ),
		) as $quale => $dove ) {
			$this->mandate = array();
			$html          = $this->pagina_disegnata( $dove );

			$this->assertSame( in_array( $quale, array( 'non trovata', 'scritta male' ), true ), null === $html, $quale . ': precondizione, la risposta e\' quella attesa.' );
			$this->assertSame( 'noindex', $this->mandate['X-Robots-Tag'] ?? '', $quale . ': il divieto nell\'intestazione.' );
			$this->assertContains( 'X-Robots-Tag: noindex', $this->righe, $quale . ': il divieto su una riga propria.' );
			$this->assertStringContainsString( 'noindex', $this->robot_stampati(), $quale . ': il divieto nel sorgente.' );
			$this->assertSame( PaginaPubblica::CACHE_CONTROL, $this->mandate['Cache-Control'] ?? null, $quale . ': la risposta non si conserva.' );

			if ( 'scheda' === $quale ) {
				$titolo = str_replace( "\u{2019}", "'", html_entity_decode( wp_get_document_title(), ENT_QUOTES ) );

				$this->assertStringStartsWith( 'Copia dell\'atto ' . $numero['numero'] . '/' . $numero['anno'], $titolo, 'Il titolo della finestra e\' il numero dell\'atto.' );
				$this->assertStringNotContainsString( 'contributo', $titolo, 'Non l\'oggetto.' );
			}
		}

		$mappa  = wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_url_list( 1, 'page' );
		$luoghi = wp_list_pluck( $mappa, 'loc' );

		$this->assertContains( (string) get_permalink( $ordinaria ), $luoghi, 'Controllo positivo: la pagina ordinaria e\' nella mappa.' );
		$this->assertNotContains( (string) get_permalink( $this->pagina ), $luoghi, 'La pagina dello storico non e\' nella mappa.' );
	}

	/**
	 * A-277: il codice breve disegna solo nella sua pagina, e non nella pagina dell'albo.
	 */
	public function test_a277_solo_nella_sua_pagina(): void {
		$insieme = $this->pagina_con( '[albo_pretorio]' . "\n\n" . '[albo_pretorio_storico]', 'insieme' );
		$this->pagina_storico();

		$copia = $this->copia( $this->pubblicato() );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$this->assertSame( array( $this->pagina ), PaginaStorico::pagine(), 'La pagina con tutti e due i codici e\' dell\'albo, non dello storico.' );
		$this->assertContains( $insieme, PaginaPubblica::pagine(), 'Precondizione: e\' riconosciuta come pagina dell\'albo.' );

		$html = (string) $this->pagina_disegnata( $this->indirizzo( array(), $insieme ) );
		$this->assertSame( array(), $this->copie_elencate( $html ), 'Sulla pagina dell\'albo lo storico non si disegna.' );
		$this->assertStringContainsString( 'albo-pretorio__ricerca', $html, 'Controllo positivo: l\'albo si disegna.' );

		$this->assertSame( array( $copia ), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Controllo positivo: nella sua pagina lo storico si disegna.' );

		// Fuori dalla sua pagina il codice breve non disegna niente.
		$ordinaria = $this->pagina_con( '', 'ordinaria' );
		$this->pagina_disegnata( (string) get_permalink( $ordinaria ) );
		$this->assertSame( '', do_shortcode( '[albo_pretorio_storico]' ), 'In un\'altra pagina, niente.' );
	}

	/**
	 * A-277: venti copie per pagina, e una pagina chiesta male.
	 */
	public function test_a277_pagine_dell_elenco(): void {
		$this->pagina_storico();

		$copie = array();

		for ( $i = 0; $i < 21; $i++ ) {
			$copie[] = $this->copia( $this->pubblicato( 'Atto di prova ' . $i, sprintf( '2041-10-01 09:%02d:00', $i ) ) );
		}

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$prima = (string) $this->pagina_disegnata( $this->indirizzo() );
		$this->assertCount( 20, $this->copie_elencate( $prima ), 'Venti nella prima pagina.' );
		$this->assertStringContainsString( '21 copie', $prima );
		$this->assertSame( array( $copie[0] ), $this->copie_elencate( (string) $this->pagina_disegnata( $this->indirizzo( array( VistaStorico::CAMPO_PAGINA => 2 ) ) ) ), 'La piu\' vecchia nella seconda.' );

		foreach ( array( '0', '3', 'due', '02' ) as $valore ) {
			$html = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaStorico::CAMPO_PAGINA => $valore ) ) );
			$this->assertSame( array(), $this->copie_elencate( $html ), $valore . ': nessuna copia.' );
			$this->assertStringContainsString( 'Questa pagina dell&#039;elenco non esiste.', $html, $valore . ': lo dice.' );
			$this->assertStringContainsString( 'Vai alla prima pagina', $html, $valore . ': e porta alla prima.' );
		}
	}

	/**
	 * A-278: la ricerca per parole dell'oggetto della copia e per numero di registro.
	 */
	public function test_a278_ricerca(): void {
		$this->pagina_storico();

		$primo   = $this->pubblicato( 'Concessione a Mario Rossi', '2041-10-01 09:30:00' );
		$secondo = $this->pubblicato( 'Contributo ad Anna Bianchi', '2041-10-01 10:30:00' );
		$terzo   = $this->pubblicato( 'Rimborso a Carlo Verdi', '2041-10-01 11:30:00' );

		$concessione = $this->copia( $primo, 'Concessione di un contributo per lo sport' );
		$contributo  = $this->copia( $secondo, 'Contributo per attivita\' culturali' );
		$rimborso    = $this->copia( $terzo, 'Rimborso di spese di viaggio' );
		$numero      = \AlboPretorioPa\Repertorio::di( $secondo );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$tutte = (string) $this->pagina_disegnata( $this->indirizzo() );
		$this->assertSame( array( $rimborso, $contributo, $concessione ), $this->copie_elencate( $tutte ), 'Controllo positivo: senza ricerca, tutte.' );
		$this->assertStringNotContainsString( 'Risultati della ricerca', $tutte );
		$this->assertStringContainsString( 'Cerca nelle copie', $tutte, 'Il modulo c\'e\'.' );

		foreach ( array(
			'una parola'            => array( 'contributo', array( $contributo, $concessione ) ),
			'maiuscole'             => array( 'CONTRIBUTO', array( $contributo, $concessione ) ),
			'due parole, tutte'     => array( 'contributo sport', array( $concessione ) ),
			'numero di registro'    => array( $numero['numero'] . '/' . $numero['anno'], array( $contributo ) ),
			'numero con gli spazi'  => array( $numero['numero'] . ' / ' . $numero['anno'], array( $contributo ) ),
			'numero mai dato'       => array( '999/' . $numero['anno'], array() ),
			'oggetto originale'     => array( 'Rossi', array() ),
			'parola che non c\'e\'' => array( 'urbanistica', array() ),
			'vuota'                 => array( '', array( $rimborso, $contributo, $concessione ) ),
		) as $quale => list( $testo, $attese ) ) {
			$html = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaStorico::CAMPO_TESTO => $testo ) ) );

			$this->assertSame( $attese, $this->copie_elencate( $html ), $quale . ': le copie trovate.' );

			if ( '' === $testo ) {
				$this->assertStringNotContainsString( 'Risultati della ricerca', $html, $quale . ': non e\' una ricerca.' );
				continue;
			}

			$this->assertStringContainsString( 'Risultati della ricerca', $html, $quale . ': il titolo dice che e\' una ricerca.' );
			$this->assertStringContainsString( 'Mostra tutte le copie', $html, $quale . ': si torna all\'elenco intero.' );

			if ( array() === $attese ) {
				$this->assertStringContainsString( 'Nessuna copia risponde alla ricerca.', $html, $quale . ': lo dice.' );
			}
		}
	}

	/**
	 * A-278: le pagine dell'elenco conservano la ricerca.
	 */
	public function test_a278_ricerca_e_pagine(): void {
		$this->pagina_storico();

		for ( $i = 0; $i < 21; $i++ ) {
			$this->copia( $this->pubblicato( 'Atto di prova ' . $i, sprintf( '2041-10-01 09:%02d:00', $i ) ), 'Contributo numero ' . $i );
		}

		$altra = $this->copia( $this->pubblicato( 'Altro atto', '2041-10-01 10:30:00' ), 'Rimborso di spese' );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$prima = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaStorico::CAMPO_TESTO => 'contributo' ) ) );

		$this->assertCount( 20, $this->copie_elencate( $prima ), 'Venti nella prima pagina.' );
		$this->assertNotContains( $altra, $this->copie_elencate( $prima ), 'La copia che non risponde non c\'e\'.' );
		$this->assertStringContainsString( '21 copie', $prima, 'Il conteggio e\' quello della ricerca.' );
		$this->assertStringContainsString(
			esc_url(
				$this->indirizzo(
					array(
						VistaStorico::CAMPO_TESTO  => 'contributo',
						VistaStorico::CAMPO_PAGINA => 2,
					)
				)
			),
			$prima,
			'Il collegamento alla seconda pagina conserva la ricerca.'
		);

		$seconda = (string) $this->pagina_disegnata(
			$this->indirizzo(
				array(
					VistaStorico::CAMPO_TESTO  => 'contributo',
					VistaStorico::CAMPO_PAGINA => 2,
				)
			)
		);
		$this->assertCount( 1, $this->copie_elencate( $seconda ), 'Una nella seconda.' );
	}

	/**
	 * A-279: la ricerca scritta male non mostra l'elenco intero, e il modulo e' accessibile.
	 */
	public function test_a279_ricerca_scritta_male(): void {
		$this->pagina_storico();

		$copia = $this->copia( $this->pubblicato() );

		$this->fissa_orologi( self::DURANTE );
		wp_set_current_user( 0 );

		$controllo = (string) $this->pagina_disegnata( $this->indirizzo() );
		$this->assertSame( array( $copia ), $this->copie_elencate( $controllo ), 'Controllo positivo: la copia e\' nell\'elenco.' );
		$this->assertStringContainsString( '<label for="' . VistaStorico::CAMPO_TESTO . '">', $controllo, 'Il campo ha l\'etichetta.' );
		$this->assertStringContainsString( 'aria-describedby="' . VistaStorico::CAMPO_TESTO . '-aiuto"', $controllo, 'Il campo e\' collegato al suo aiuto.' );
		$this->assertStringContainsString( 'id="' . VistaStorico::CAMPO_TESTO . '-aiuto"', $controllo, 'L\'aiuto c\'e\'.' );
		$this->assertStringNotContainsString( 'aria-invalid', $controllo, 'Senza errore, il campo non e\' segnato.' );

		foreach ( array(
			'solo segni'   => '<b></b>',
			'numero zero'  => '0/2041',
			'numero oltre' => '9999999999/2041',
			'anno zero'    => '1/0000',
		) as $quale => $testo ) {
			$html = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaStorico::CAMPO_TESTO => $testo ) ) );

			$this->assertSame( array(), $this->copie_elencate( $html ), $quale . ': nessuna riga, e non l\'elenco intero.' );
			$this->assertStringContainsString( 'La ricerca non e&#039; stata fatta', $html, $quale . ': lo dice.' );
			$this->assertStringContainsString( 'role="alert"', $html, $quale . ': ad alta voce.' );
			$this->assertStringContainsString( 'aria-invalid="true"', $html, $quale . ': il campo e\' segnato.' );
			$this->assertStringContainsString( 'aria-describedby="' . VistaStorico::CAMPO_TESTO . '-aiuto ' . VistaStorico::ID_ERRORE . '"', $html, $quale . ': e collegato all\'errore.' );
		}

		$html = (string) $this->pagina_disegnata( add_query_arg( VistaStorico::CAMPO_TESTO . '[]', 'contributo', $this->indirizzo() ) );
		$this->assertSame( array(), $this->copie_elencate( $html ), 'Come elenco: nessuna riga.' );
		$this->assertStringContainsString( 'La ricerca non e&#039; stata fatta', $html, 'Come elenco: lo dice.' );

		$html = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaStorico::CAMPO_TESTO => '"><script>contributo</script>' ) ) );
		$this->assertStringNotContainsString( '<script>', $html, 'Il testo scritto non diventa codice.' );
	}
}
