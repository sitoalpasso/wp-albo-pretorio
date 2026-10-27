<?php
/**
 * Atti di un'altra amministrazione ospitati sull'albo: righe A-249..A-256 e A-260 (ALBO-29).
 *
 * L'elenco si salva con invii veri della schermata, la scelta sull'atto con
 * invii veri della schermata dell'atto, la pubblicazione con il passaggio vero.
 * Le pagine pubbliche si leggono con richieste vere della pagina dell'albo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\AmministrazioniOspitate;
use AlboPretorioPa\ChiusuraPubblicazione;
use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\PaginaPubblica;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchedaAtto;
use AlboPretorioPa\SchermataAmministrazioniOspitate;
use AlboPretorioPa\SchermataScaduti;
use AlboPretorioPa\VistaPubblica;

use const AlboPretorioPa\TIPO;

// La prova della lettura sola scrive l'elenco di lato, e quella dei salvataggi sovrapposti lancia processi veri.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open

/**
 * L'elenco delle amministrazioni ospitate, la scelta sull'atto e cio' che vede il pubblico.
 */
class AmministrazioniOspitateTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Il nome dell'amministrazione ospitata delle prove.
	 */
	private const OSPITATA = 'Unione dei comuni di prova';

	/**
	 * La pagina dell'albo.
	 *
	 * @var int
	 */
	private int $pagina = 0;

	/**
	 * Assetto comune, con indirizzi semplici e intestazioni trattenute.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->set_permalink_structure( '' );

		PaginaPubblica::fissa_emettitore( static function ( string $riga ): void {} );
		$this->ammetti_transazione_esterna( true );
	}

	/**
	 * Lascia salvare l'elenco dentro la transazione delle prove, o no.
	 *
	 * @param bool $ammessa Se e' ammessa.
	 */
	private function ammetti_transazione_esterna( bool $ammessa ): void {
		$valore = new \ReflectionProperty( AmministrazioniOspitate::class, 'dentro_altra_transazione' );
		$valore->setAccessible( true );
		$valore->setValue( null, $ammessa );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		PaginaPubblica::fissa_emettitore( null );
		$this->ammetti_transazione_esterna( false );

		$this->smonta_flusso();
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Un invio vero della schermata dell'elenco, come l'utente indicato.
	 *
	 * @param int                  $utente  Utente.
	 * @param array<string, mixed> $campi   Campi inviati.
	 * @param string|null          $gettone Gettone; nullo per quello giusto.
	 * @return true|\WP_Error
	 */
	private function invia_elenco( int $utente, array $campi, ?string $gettone = null ) {
		return $this->come(
			$utente,
			static function () use ( $campi, $gettone ) {
				// Con le barre aggiunte, come WordPress consegna ogni richiesta vera.
				$_POST = wp_slash( array( SchermataAmministrazioniOspitate::CAMPO_GETTONE => $gettone ?? wp_create_nonce( SchermataAmministrazioniOspitate::AZIONE ) ) + $campi );

				try {
					return SchermataAmministrazioniOspitate::elabora();
				} finally {
					$_POST = array();
				}
			}
		);
	}

	/**
	 * Aggiunge voci all'elenco con un invio vero, come chi pubblica.
	 *
	 * @param string $nuove Un nome per riga.
	 */
	private function aggiungi( string $nuove ): void {
		$esito = $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => $nuove ) );

		$this->assertTrue( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
	}

	/**
	 * Il numero della voce con il nome indicato.
	 *
	 * @param string $nome Nome.
	 * @return int
	 */
	private function numero_di( string $nome ): int {
		$numero = array_search( $nome, AmministrazioniOspitate::voci(), true );

		$this->assertIsInt( $numero, 'Precondizione: la voce ' . $nome . ' c\'e\'.' );

		return $numero;
	}

	/**
	 * Un invio vero della schermata dell'atto, come chi redige.
	 *
	 * @param int         $atto_id Atto.
	 * @param string|null $scelta  Valore del campo dell'amministrazione; nullo per non mandarlo.
	 * @param string|null $stato   Stato chiesto, per esempio in verifica; nullo per salvare la bozza.
	 * @return array<int, string> Motivi dei rifiuti della scheda.
	 */
	private function invia_scheda( int $atto_id, ?string $scelta, ?string $stato = null ): array {
		return $this->come(
			$this->redattore,
			static function () use ( $atto_id, $scelta, $stato ) {
				set_current_screen( 'post' );

				$_POST = array(
					'action'                  => 'editpost',
					'post_ID'                 => (string) $atto_id,
					'post_type'               => TIPO,
					'post_title'              => get_the_title( $atto_id ),
					'original_post_status'    => 'draft',
					'_wpnonce'                => wp_create_nonce( 'update-post_' . $atto_id ),
					SchedaAtto::CAMPO_GETTONE => wp_create_nonce( SchedaAtto::azione( $atto_id ) ),
				);

				if ( null !== $scelta ) {
					$_POST[ SchedaAtto::CAMPO_AMMINISTRAZIONE ] = wp_slash( $scelta );
				}

				if ( null !== $stato ) {
					$_POST['post_status'] = $stato;
				}

				try {
					edit_post();
				} catch ( \WPDieException $fermata ) {
					unset( $fermata );
				} finally {
					$_POST = array();
					clean_post_cache( $atto_id );
				}

				return SchedaAtto::preleva_rifiuto( $atto_id );
			}
		);
	}

	/**
	 * Il riquadro dei dati dell'atto, come chi redige.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private function riquadro( int $atto_id ): string {
		return $this->come(
			$this->redattore,
			static function () use ( $atto_id ) {
				ob_start();

				try {
					SchedaAtto::mostra( get_post( $atto_id ) );
				} finally {
					$stampato = (string) ob_get_clean();
				}

				return $stampato;
			}
		);
	}

	/**
	 * Una bozza completa mandata in verifica da chi redige, con la scelta indicata.
	 *
	 * @param string      $oggetto Oggetto.
	 * @param string|null $scelta  Scelta da inviare con la scheda; nullo per non sceglierla.
	 * @return int
	 */
	private function in_verifica_con( string $oggetto, ?string $scelta ): int {
		$id = $this->atto_completo_senza_controllo( $oggetto );

		$this->invia_scheda( $id, $scelta, 'pending' );

		return $id;
	}

	/**
	 * Una bozza completa nei dati di sempre, senza pretendere che non manchi niente.
	 *
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function atto_completo_senza_controllo( string $oggetto ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => TIPO,
				'post_status'  => 'draft',
				'post_title'   => $oggetto,
				'post_content' => 'Testo di prova.',
				'post_author'  => $this->redattore,
			)
		);

		$this->assertIsInt( $id );

		wp_set_object_terms( $id, array( $this->voci['normativo'] ), \AlboPretorioPa\TASSONOMIA_TIPO_ATTO );
		wp_set_object_terms( $id, array( $this->voci['organo'] ), \AlboPretorioPa\TASSONOMIA_ORGANO );
		update_post_meta( $id, \AlboPretorioPa\META_DATA_ADOZIONE, '2041-09-10' );

		$this->deposita_documento( $id );

		return $id;
	}

	/**
	 * Pubblica con il passaggio vero e controlla che sia riuscito.
	 *
	 * @param int $atto_id Atto in verifica.
	 */
	private function pubblica( int $atto_id ): void {
		$this->assertSame( 'pending', get_post_status( $atto_id ), 'Precondizione: l\'atto e\' in verifica.' );

		$esito = $this->pubblica_da_codice( $atto_id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 'publish', get_post_status( $atto_id ) );
	}

	/**
	 * La pagina dell'albo, pubblicata.
	 */
	private function pagina_albo(): void {
		$this->pagina = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Albo pretorio',
				'post_content' => '[albo_pretorio]',
			)
		);
	}

	/**
	 * Quello che la pagina dell'albo disegna all'indirizzo con i parametri indicati.
	 *
	 * @param array<string, mixed> $parametri Parametri.
	 * @return string
	 */
	private function disegnata( array $parametri = array() ): string {
		unset( $GLOBALS['post'], $GLOBALS['posts'], $GLOBALS['more'], $GLOBALS['single'] );

		$this->richiesta_pubblica( add_query_arg( $parametri, (string) get_permalink( $this->pagina ) ) );

		$this->assertFalse( is_404(), 'Precondizione: la pagina risponde.' );

		$pagina          = get_queried_object();
		$GLOBALS['post'] = $pagina;
		setup_postdata( $pagina );

		return (string) apply_filters( 'the_content', $pagina->post_content );
	}

	/**
	 * Il testo della riga di un atto nell'elenco, oppure nullo se la riga non c'e'.
	 *
	 * @param string $html Codice disegnato.
	 * @param int    $atto Atto.
	 * @return string|null
	 */
	private function riga( string $html, int $atto ): ?string {
		if ( 1 !== preg_match( '#<tr class="albo-pretorio__atto" role="row" data-atto="' . $atto . '">(.*?)</tr>#s', $html, $trovata ) ) {
			return null;
		}

		return html_entity_decode( wp_strip_all_tags( $trovata[1] ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * I dati della scheda pubblica di un atto, per etichetta.
	 *
	 * @param int $atto Atto.
	 * @return array<string, string>
	 */
	private function scheda( int $atto ): array {
		$html = $this->disegnata( array( PaginaPubblica::PARAMETRO_ATTO => $atto ) );

		preg_match_all( '#<dt>(.*?)</dt><dd>(.*?)</dd>#s', $html, $coppie, PREG_SET_ORDER );

		$dati = array();

		foreach ( $coppie as $coppia ) {
			$dati[ html_entity_decode( $coppia[1], ENT_QUOTES, 'UTF-8' ) ] = html_entity_decode( $coppia[2], ENT_QUOTES, 'UTF-8' );
		}

		$this->assertArrayHasKey( 'Numero di registro', $dati, 'Precondizione: la scheda c\'e\'.' );

		return $dati;
	}

	/**
	 * La voce di conferma della pubblicazione di un atto.
	 *
	 * @param int $atto Atto.
	 * @return array<string, mixed>
	 */
	private function conferma( int $atto ): array {
		$voci = $this->voci_di( $atto, array( 'azione' => Passaggi::AZIONE_CONFERMA ) );

		$this->assertCount( 1, $voci );

		return $voci[0]['dettagli'];
	}

	/**
	 * A-249: con l'elenco vuoto la domanda non compare e niente indica un atto ospitato.
	 */
	public function test_a249_elenco_vuoto(): void {
		$this->pagina_albo();

		$id = $this->atto_completo_senza_controllo( 'Atto con elenco vuoto' );

		$this->assertSame( array(), AmministrazioniOspitate::voci(), 'Precondizione: l\'elenco e\' vuoto.' );
		$this->assertStringNotContainsString( SchedaAtto::CAMPO_AMMINISTRAZIONE, $this->riquadro( $id ), 'La scelta non compare.' );
		$this->assertSame( array(), DatiAtto::mancanti( $id ), 'La scelta non e\' pretesa.' );

		$this->invia_scheda( $id, null, 'pending' );
		$this->pubblica( $id );

		$this->assertSame( '', $this->conferma( $id )[ AmministrazioniOspitate::DETTAGLIO_NOME ] );
		$this->assertSame( 0, $this->conferma( $id )[ AmministrazioniOspitate::DETTAGLIO_VOCE ] );

		$elenco = $this->disegnata();
		$this->assertNotNull( $this->riga( $elenco, $id ), 'Precondizione: l\'atto e\' in elenco.' );
		$this->assertStringNotContainsString( 'Atto di:', $elenco );
		$this->assertStringNotContainsString( 'albo-pretorio__amministrazione', $elenco );
		$this->assertArrayNotHasKey( AmministrazioniOspitate::etichetta(), $this->scheda( $id ) );
		$ricerca = $this->disegnata( array( VistaPubblica::CAMPO_TESTO => 'elenco vuoto' ) );
		$this->assertNotNull( $this->riga( $ricerca, $id ), 'Precondizione: la ricerca trova l\'atto.' );
		$this->assertStringNotContainsString( 'Atto di:', $ricerca );

		// Controllo: sullo stesso assetto con una voce nell'elenco la scelta compare.
		$this->aggiungi( self::OSPITATA );
		$this->assertStringContainsString( 'name="' . SchedaAtto::CAMPO_AMMINISTRAZIONE . '"', $this->riquadro( $this->atto_completo_senza_controllo( 'Atto di controllo' ) ) );
	}

	/**
	 * A-250: con una voce la scelta si pretende senza preselezione, e il nome si vede ovunque.
	 */
	public function test_a250_elenco_con_una_voce(): void {
		$this->pagina_albo();
		$this->aggiungi( self::OSPITATA );

		$ospitata = $this->numero_di( self::OSPITATA );

		// La prima bozza, senza scegliere: prima si guarda la schermata cosi' come si presenta.
		$prima    = $this->atto_completo_senza_controllo( 'Atto senza scelta' );
		$riquadro = $this->riquadro( $prima );

		$this->assertMatchesRegularExpression( '#<option value="" selected="selected">Da scegliere</option>#', $riquadro, 'In testa la voce che non e\' una risposta.' );
		$this->assertSame( 1, substr_count( $riquadro, 'selected="selected"' ), 'Nessuna risposta segnata.' );
		$this->assertStringContainsString( '<option value="0">Questa amministrazione</option>', $riquadro );
		$this->assertStringContainsString( '<option value="' . $ospitata . '">' . self::OSPITATA . '</option>', $riquadro );
		$this->assertDoesNotMatchRegularExpression( '#<select[^>]*' . SchedaAtto::CAMPO_AMMINISTRAZIONE . '[^>]*required#', $riquadro, 'La bozza si salva anche senza scelta.' );

		$this->invia_scheda( $prima, '', 'pending' );
		$this->assertSame( 'draft', get_post_status( $prima ), 'Senza scelta l\'atto resta in bozza.' );
		$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $prima ) );
		$this->assertStringContainsString( 'Manca l\'amministrazione che ha adottato l\'atto', DatiAtto::mancanti( $prima )['albo_manca_amministrazione'] );

		$questa = $this->in_verifica_con( 'Atto di questa amministrazione', '0' );
		$altra  = $this->in_verifica_con( 'Atto ospitato di prova', (string) $ospitata );

		$this->pubblica( $questa );
		$this->pubblica( $altra );

		$this->assertSame(
			array(
				AmministrazioniOspitate::DETTAGLIO_NOME => '',
				AmministrazioniOspitate::DETTAGLIO_VOCE => 0,
			),
			array_intersect_key( $this->conferma( $questa ), array_flip( array( AmministrazioniOspitate::DETTAGLIO_NOME, AmministrazioniOspitate::DETTAGLIO_VOCE ) ) )
		);
		$this->assertSame(
			array(
				AmministrazioniOspitate::DETTAGLIO_NOME => self::OSPITATA,
				AmministrazioniOspitate::DETTAGLIO_VOCE => $ospitata,
			),
			array_intersect_key( $this->conferma( $altra ), array_flip( array( AmministrazioniOspitate::DETTAGLIO_NOME, AmministrazioniOspitate::DETTAGLIO_VOCE ) ) )
		);

		$elenco = $this->disegnata();
		$this->assertNotNull( $this->riga( $elenco, $questa ), 'Precondizione: l\'atto di questa amministrazione e\' in elenco.' );
		$this->assertStringContainsString( 'Atto di: ' . self::OSPITATA, (string) $this->riga( $elenco, $altra ), 'In elenco.' );
		$this->assertStringNotContainsString( 'Atto di:', (string) $this->riga( $elenco, $questa ) );
		$this->assertSame( self::OSPITATA, $this->scheda( $altra )[ AmministrazioniOspitate::etichetta() ], 'Nella scheda.' );
		$this->assertArrayNotHasKey( AmministrazioniOspitate::etichetta(), $this->scheda( $questa ) );

		$ricerca = $this->disegnata( array( VistaPubblica::CAMPO_TESTO => 'ospitato' ) );
		$this->assertStringContainsString( 'Atto di: ' . self::OSPITATA, (string) $this->riga( $ricerca, $altra ), 'Nei risultati della ricerca.' );

		// Nella consultazione riservata, dopo la scadenza.
		$this->fissa_orologi( '2041-12-31 10:00:00' );
		wp_set_current_user( $this->amministratore );
		set_current_screen( 'dashboard' );
		ob_start();
		SchermataScaduti::mostra();
		$riservata                 = (string) ob_get_clean();
		$GLOBALS['current_screen'] = null;
		$righe                     = array();

		foreach ( array( $altra, $questa ) as $atto ) {
			$this->assertSame( 1, preg_match( '#<tr data-atto="' . $atto . '">(.*?)</tr>#s', $riservata, $trovata ), 'Precondizione: l\'atto scaduto ' . $atto . ' e\' in elenco.' );
			$righe[ $atto ] = html_entity_decode( $trovata[1], ENT_QUOTES, 'UTF-8' );
		}

		$this->assertStringContainsString( self::OSPITATA, $righe[ $altra ], 'Il nome sta nella riga dell\'atto ospitato.' );
		$this->assertStringNotContainsString( self::OSPITATA, $righe[ $questa ], 'E non in quella dell\'atto di questa amministrazione.' );
	}

	/**
	 * A-251: rinominare o togliere la voce non cambia l'autore di un atto pubblicato, e non crea permessi.
	 */
	public function test_a251_la_storia_dell_atto(): void {
		$this->pagina_albo();

		$ruoli_prima = wp_roles()->roles;

		$this->aggiungi( self::OSPITATA . "\nConsorzio di prova" );

		$this->assertSame( $ruoli_prima, wp_roles()->roles, 'Aggiungere voci non crea ruoli ne\' permessi.' );

		$ospitata  = $this->numero_di( self::OSPITATA );
		$consorzio = $this->numero_di( 'Consorzio di prova' );

		$primo   = $this->in_verifica_con( 'Primo atto ospitato', (string) $ospitata );
		$secondo = $this->in_verifica_con( 'Secondo atto ospitato', (string) $consorzio );

		$this->pubblica( $primo );
		$this->pubblica( $secondo );

		$foto_primo   = $this->fotografia( $primo );
		$foto_secondo = $this->fotografia( $secondo );

		$rinomina = $this->invia_elenco(
			$this->pubblicatore,
			array(
				SchermataAmministrazioniOspitate::CAMPO_NOMI  => array(
					$ospitata  => 'Unione rinominata',
					$consorzio => 'Consorzio di prova',
				),
				SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( (string) $consorzio ),
			)
		);

		$this->assertTrue( $rinomina, is_wp_error( $rinomina ) ? $rinomina->get_error_message() : '' );
		$this->assertSame( array( $ospitata => 'Unione rinominata' ), AmministrazioniOspitate::voci(), 'Precondizione: rinominata l\'una, tolta l\'altra.' );

		$this->assertSame( self::OSPITATA, $this->scheda( $primo )[ AmministrazioniOspitate::etichetta() ], 'Il nome della pubblicazione, non quello di adesso.' );
		$this->assertSame( 'Consorzio di prova', $this->scheda( $secondo )[ AmministrazioniOspitate::etichetta() ], 'La voce tolta resta sull\'atto pubblicato.' );
		$this->assertSame( $foto_primo, $this->fotografia( $primo ), 'L\'atto pubblicato e\' identico.' );
		$this->assertSame( $foto_secondo, $this->fotografia( $secondo ), 'L\'atto pubblicato e\' identico.' );
		$this->assertSame( $ruoli_prima, wp_roles()->roles, 'Nessun ruolo e nessun permesso nuovo.' );

		// Chi pubblica l'atto ospitato e' chi pubblica tutti gli altri.
		$terzo = $this->in_verifica_con( 'Terzo atto ospitato', (string) $ospitata );
		$this->assertWPError( $this->pubblica_da_codice( $terzo, $this->redattore ), 'Chi redige non pubblica nemmeno l\'atto ospitato.' );
		$this->pubblica( $terzo );
		$this->assertSame( 'Unione rinominata', $this->conferma( $terzo )[ AmministrazioniOspitate::DETTAGLIO_NOME ], 'Un atto pubblicato dopo prende il nome di adesso.' );
	}

	/**
	 * A-252: la schermata salva tutto o niente, con un numero per voce che non si riusa.
	 */
	public function test_a252_la_schermata(): void {
		$this->aggiungi( "Ente   Alfa\n\nEnte Beta" );
		$this->assertSame(
			array(
				1 => 'Ente Alfa',
				2 => 'Ente Beta',
			),
			AmministrazioniOspitate::voci(),
			'Spazi ripetuti ridotti, righe vuote saltate.'
		);

		$prima = get_option( AmministrazioniOspitate::OPZIONE );

		$rifiuti = array(
			'gettone sbagliato'      => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => 'Ente Gamma' ), 'sbagliato' ),
			'chi redige'             => array( $this->redattore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => 'Ente Gamma' ), null ),
			'nome ripetuto'          => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => "Ente Gamma\nente beta" ), null ),
			'rinomina su un altro'   => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NOMI => array( 1 => 'Ente Beta' ) ), null ),
			'rinomina vuota'         => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NOMI => array( 1 => '   ' ) ), null ),
			'carattere di controllo' => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NOMI => array( 1 => "Ente\tAlfa" ) ), null ),
			'segni di formattazione' => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => '<b>Ente</b>' ), null ),
			'voce sparita'           => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NOMI => array( 7 => 'Ente Delta' ) ), null ),
			'togli scritto male'     => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( '1x' ) ), null ),
			'nomi non in un gruppo'  => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NOMI => 'Ente Alfa' ), null ),
			'nuove non in un testo'  => array( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => array( 'Ente Gamma' ) ), null ),
			'nome valido poi errore' => array(
				$this->pubblicatore,
				array(
					SchermataAmministrazioniOspitate::CAMPO_NOMI  => array( 1 => 'Ente Alfa nuovo' ),
					SchermataAmministrazioniOspitate::CAMPO_NUOVE => "Ente Gamma\nEnte Gamma",
				),
				null,
			),
		);

		foreach ( $rifiuti as $caso => list( $utente, $campi, $gettone ) ) {
			$this->assertWPError( $this->invia_elenco( $utente, $campi, $gettone ), $caso );
			$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ), $caso . ': l\'elenco non cambia.' );
		}

		// Togliere l'ultima voce e aggiungerne una: il numero tolto non si riusa, e una bozza che lo indicava non cambia autore.
		$bozza = $this->atto_completo_senza_controllo( 'Bozza della voce tolta' );
		$this->assertSame( array(), $this->invia_scheda( $bozza, '2' ) );

		$this->assertTrue( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( '2' ) ) ) );
		$this->aggiungi( 'Ente Gamma' );
		$this->assertSame(
			array(
				1 => 'Ente Alfa',
				3 => 'Ente Gamma',
			),
			AmministrazioniOspitate::voci()
		);
		$this->assertNull( AmministrazioniOspitate::scelta_valida( $bozza ), 'La bozza non passa alla voce nuova.' );

		// Una voce aggiunta da altri mentre la schermata era aperta, assente dall'invio, resta.
		$esito = $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NOMI => array( 1 => 'Ente Alfa' ) ) );
		$this->assertTrue( $esito );
		$this->assertSame(
			array(
				1 => 'Ente Alfa',
				3 => 'Ente Gamma',
			),
			AmministrazioniOspitate::voci()
		);

		// Una voce gia' tolta da altri e indicata da togliere non e' un errore.
		$this->assertTrue( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( '2' ) ) ) );

		// La schermata dice la norma e le due situazioni, e non segna niente.
		$schermata = $this->come(
			$this->pubblicatore,
			static function () {
				ob_start();
				SchermataAmministrazioniOspitate::mostra();
				return (string) ob_get_clean();
			}
		);
		$this->assertStringContainsString( 'l. 69/2009, art. 32, c. 3', $schermata );
		$this->assertStringContainsString( 'Per chi consulta l&#039;albo', $schermata );
		$this->assertStringContainsString( 'Per chi prepara gli atti', $schermata );
		$this->assertStringNotContainsString( 'checked', $schermata, 'Nessuna casella "Togli" segnata.' );
	}

	/**
	 * A-253: una voce tolta o un elenco svuotato dopo la scelta fermano l'atto, che si corregge.
	 */
	public function test_a253_voce_tolta_dopo_la_scelta(): void {
		$this->aggiungi( self::OSPITATA );

		$ospitata = $this->numero_di( self::OSPITATA );

		$bozza = $this->atto_completo_senza_controllo( 'Bozza con voce tolta' );
		$this->assertSame( array(), $this->invia_scheda( $bozza, (string) $ospitata ) );

		$verifica = $this->in_verifica_con( 'In verifica con voce tolta', (string) $ospitata );
		$this->assertSame( 'pending', get_post_status( $verifica ), 'Precondizione: in verifica.' );

		/*
		 * "Da scegliere" non toglie nemmeno una scelta che vale: un'altra
		 * richiesta che toglie la voce mentre la scheda si salva lascerebbe
		 * l'atto senza scelta, cioe' di questa amministrazione a elenco vuoto.
		 */
		$this->aggiungi( 'Ente in corsa' );
		$corsa   = $this->numero_di( 'Ente in corsa' );
		$tornata = $this->atto_completo_senza_controllo( 'Bozza che torna a Da scegliere' );
		$this->assertSame( array(), $this->invia_scheda( $tornata, (string) $corsa ) );

		$this->invia_scheda( $tornata, '' );
		$this->assertSame( array( (string) $corsa ), get_post_meta( $tornata, AmministrazioniOspitate::META, false ), 'Con la voce ancora in elenco "Da scegliere" non toglie la scelta.' );

		$toglie = static function ( $id ) use ( $tornata, $corsa ) {
			global $wpdb;

			if ( $tornata !== $id ) {
				return;
			}

			$valore         = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", AmministrazioniOspitate::OPZIONE ) ) );
			$valore['voci'] = array_values( array_filter( $valore['voci'], static fn( $voce ) => $voce['id'] !== $corsa ) );
			$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $valore ) ), array( 'option_name' => AmministrazioniOspitate::OPZIONE ) );
		};

		add_action( 'save_post_' . TIPO, $toglie, 1 );

		try {
			$this->invia_scheda( $tornata, '' );
		} finally {
			remove_action( 'save_post_' . TIPO, $toglie, 1 );
		}

		$this->assertNotContains( 'Ente in corsa', AmministrazioniOspitate::voci(), 'Precondizione: la voce e\' stata tolta durante il salvataggio.' );
		$this->assertSame( array( (string) $corsa ), get_post_meta( $tornata, AmministrazioniOspitate::META, false ), '"Da scegliere" non cancella la scelta.' );
		$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $tornata ), 'La bozza resta ferma finche\' non si sceglie di nuovo.' );

		/*
		 * Una scelta esplicita sostituisce quella scritta senza lasciare
		 * l'atto senza scelta nemmeno per un istante: dopo ogni scrittura
		 * della scelta si guarda la banca dati, e una riga c'e' sempre.
		 */
		$this->aggiungi( 'Ente che resta' );
		$resta    = $this->numero_di( 'Ente che resta' );
		$cambiata = $this->atto_completo_senza_controllo( 'Bozza che cambia scelta' );
		$this->assertSame( array(), $this->invia_scheda( $cambiata, (string) $resta ) );

		$minimo    = PHP_INT_MAX;
		$scritture = 0;
		$guarda    = static function ( $meta, $id, $chiave ) use ( $cambiata, &$minimo, &$scritture ) {
			global $wpdb;

			if ( $cambiata !== (int) $id || AmministrazioniOspitate::META !== $chiave ) {
				return;
			}

			++$scritture;
			$minimo = min( $minimo, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $chiave ) ) );
		};

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $azione ) {
			add_action( $azione, $guarda, 10, 3 );
		}

		try {
			$this->invia_scheda( $cambiata, (string) $resta );
			$this->assertSame( 0, $scritture, 'La stessa scelta non si riscrive.' );

			$this->invia_scheda( $cambiata, '0' );
			$this->assertSame( array( '0' ), get_post_meta( $cambiata, AmministrazioniOspitate::META, false ) );

			// Due righe storte scritte di lato, poi una scelta vera.
			add_post_meta( $cambiata, AmministrazioniOspitate::META, '01' );
			$this->invia_scheda( $cambiata, (string) $resta );
			$this->assertSame( array( (string) $resta ), get_post_meta( $cambiata, AmministrazioniOspitate::META, false ), 'Una riga sola, con la scelta nuova.' );
		} finally {
			foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $azione ) {
				remove_action( $azione, $guarda, 10 );
			}
		}

		$this->assertGreaterThan( 0, $scritture, 'Precondizione: la scelta e\' stata riscritta.' );
		$this->assertGreaterThanOrEqual( 1, $minimo, 'Mai un istante senza scelta.' );

		// Una scrittura della scelta che non riesce lascia la scelta di prima, e lo dice.
		$rifiuta = static function ( $controllo, $id, $chiave ) use ( $cambiata ) {
			return $cambiata === (int) $id && AmministrazioniOspitate::META === $chiave ? false : $controllo;
		};

		add_filter( 'update_post_metadata', $rifiuta, 10, 3 );

		try {
			$motivi = $this->invia_scheda( $cambiata, '0' );
		} finally {
			remove_filter( 'update_post_metadata', $rifiuta, 10 );
		}

		$this->assertStringContainsString( 'non e\' riuscita', implode( ' ', $motivi ) );
		$this->assertSame( array( (string) $resta ), get_post_meta( $cambiata, AmministrazioniOspitate::META, false ), 'Resta la scelta di prima.' );

		/*
		 * Un guasto dopo la riscrittura (la cancellazione delle righe in piu'
		 * rifiutata, la rilettura finale che fallisce) o una prima lettura che
		 * fallisce: la transazione torna indietro, le righe di prima restano
		 * con i loro identificativi, e la scheda lo dice.
		 */
		global $wpdb;

		$righe = static function () use ( $cambiata ) {
			global $wpdb;

			return $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $cambiata, AmministrazioniOspitate::META ), ARRAY_A );
		};

		add_post_meta( $cambiata, AmministrazioniOspitate::META, '01' );
		$prima = $righe();
		$this->assertCount( 2, $prima, 'Precondizione: due righe diverse.' );

		$nega_cancellazione = static function ( $controllo, $id ) use ( $prima ) {
			return in_array( (string) $id, array_column( $prima, 'meta_id' ), true ) ? false : $controllo;
		};
		$letture            = 0;
		$cieca_dalla        = 0;
		$lettura_cieca      = static function ( $istruzione ) use ( &$letture, &$cieca_dalla ) {
			if ( 0 === strpos( (string) $istruzione, 'SELECT meta_id, meta_value FROM' ) && false !== strpos( (string) $istruzione, AmministrazioniOspitate::META ) && ++$letture === $cieca_dalla ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $istruzione;
		};

		foreach ( array(
			'cancellazione rifiutata'       => array( 'delete_post_metadata_by_mid', $nega_cancellazione, 0 ),
			'rilettura finale che fallisce' => array( 'query', $lettura_cieca, 3 ),
			'prima lettura che fallisce'    => array( 'query', $lettura_cieca, 1 ),
		) as $caso => list( $aggancio, $funzione, $quale ) ) {
			$letture     = 0;
			$cieca_dalla = $quale;
			add_filter( $aggancio, $funzione, 10, 2 );
			$zitto = $wpdb->suppress_errors( true );

			try {
				$motivi = $this->invia_scheda( $cambiata, '0' );
			} finally {
				$wpdb->suppress_errors( $zitto );
				remove_filter( $aggancio, $funzione, 10 );
			}

			$this->assertStringContainsString( 'resta la scelta di prima', implode( ' ', $motivi ), $caso );
			$this->assertSame( $prima, $righe(), $caso . ': le righe di prima, con i loro identificativi e valori.' );
			$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $cambiata ), $caso . ': l\'atto resta fermo.' );
		}

		// Una prima lettura che fallisce con una scelta valida: niente si scrive, e la scelta resta.
		delete_metadata_by_mid( 'post', (int) $prima[1]['meta_id'] );
		$sola        = $righe();
		$letture     = 0;
		$cieca_dalla = 1;
		add_filter( 'query', $lettura_cieca );
		$zitto = $wpdb->suppress_errors( true );

		try {
			$motivi = $this->invia_scheda( $cambiata, '0' );
		} finally {
			$wpdb->suppress_errors( $zitto );
			remove_filter( 'query', $lettura_cieca );
		}

		$this->assertStringContainsString( 'resta la scelta di prima', implode( ' ', $motivi ) );
		$this->assertSame( $sola, $righe(), 'La scelta valida resta, con il suo identificativo.' );

		/*
		 * Se nemmeno il ritorno indietro si conferma, la scheda non dice che
		 * la scelta di prima e' rimasta: dice di ricontrollarla. Lo stato e'
		 * quello di prima o quello chiesto, mai un altro.
		 */
		$letture       = 0;
		$cieca_dalla   = 3;
		$senza_ritorno = static function ( $istruzione ) {
			return 0 === strpos( (string) $istruzione, 'ROLLBACK TO SAVEPOINT albo_pretorio_scelta' ) ? 'SELECT guasto_forzato FROM tabella_che_non_esiste' : $istruzione;
		};
		$sempre_cieca  = static function ( $istruzione ) use ( &$letture ) {
			if ( 0 === strpos( (string) $istruzione, 'SELECT meta_id, meta_value FROM' ) && false !== strpos( (string) $istruzione, AmministrazioniOspitate::META ) && ++$letture >= 3 ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $istruzione;
		};

		add_filter( 'query', $senza_ritorno );
		add_filter( 'query', $sempre_cieca );
		$zitto = $wpdb->suppress_errors( true );

		try {
			$motivi = $this->invia_scheda( $cambiata, '0' );
		} finally {
			$wpdb->suppress_errors( $zitto );
			remove_filter( 'query', $senza_ritorno );
			remove_filter( 'query', $sempre_cieca );
		}

		$this->assertStringNotContainsString( 'resta la scelta di prima', implode( ' ', $motivi ) );
		$this->assertStringContainsString( 'Ricontrollare la scelta', implode( ' ', $motivi ) );
		$this->assertContains( array_column( $righe(), 'meta_value' ), array( array( (string) $resta ), array( '0' ) ), 'La scelta di prima o quella chiesta, una riga sola.' );

		delete_post_meta( $cambiata, AmministrazioniOspitate::META );
		add_post_meta( $cambiata, AmministrazioniOspitate::META, (string) $resta );
		$this->assertTrue( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( (string) $resta ) ) ) );

		// Mandato in verifica senza scelta a elenco vuoto non si puo': si prepara a mano un atto in verifica senza scelta.
		$this->assertTrue( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( (string) $ospitata ) ) ) );
		$this->assertSame( array(), AmministrazioniOspitate::voci(), 'Precondizione: l\'elenco e\' vuoto.' );

		$riquadro = $this->riquadro( $bozza );
		$this->assertStringContainsString( 'name="' . SchedaAtto::CAMPO_AMMINISTRAZIONE . '"', $riquadro, 'A elenco vuoto la domanda resta per l\'atto che indicava una voce.' );
		$this->assertStringContainsString( 'non e&#039; piu&#039; nell&#039;elenco', $riquadro );
		$this->assertMatchesRegularExpression( '#<option value="" selected="selected">#', $riquadro );

		$this->invia_scheda( $bozza, null, 'pending' );
		$this->assertSame( 'draft', get_post_status( $bozza ), 'La voce tolta ferma la bozza.' );

		// Il menu mostra "Da scegliere", e il browser lo manda vuoto: salvare o chiedere la verifica cosi' non sblocca niente.
		$this->invia_scheda( $bozza, '' );
		$this->assertSame( array( (string) $ospitata ), get_post_meta( $bozza, AmministrazioniOspitate::META, false ), '"Da scegliere" non cancella una scelta che non vale piu\'.' );
		$this->invia_scheda( $bozza, '', 'pending' );
		$this->assertSame( 'draft', get_post_status( $bozza ), 'Inviato com\'e\', il riquadro non sblocca la bozza.' );
		$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $bozza ) );

		$senza_numero = Repertorio::di( $verifica );
		$this->assertNull( $senza_numero );
		$esito = $this->pubblica_da_codice( $verifica, $this->pubblicatore );
		$this->assertWPError( $esito, 'La voce tolta ferma la pubblicazione.' );
		$this->assertArrayHasKey( 'albo_manca_amministrazione', array_flip( $esito->get_error_codes() ) );
		$this->assertSame( 'pending', get_post_status( $verifica ) );
		$this->assertNull( Repertorio::di( $verifica ), 'Nessun numero consumato.' );

		// Chi redige corregge scegliendo questa amministrazione.
		$this->invia_scheda( $bozza, '0', 'pending' );
		$this->assertSame( 'pending', get_post_status( $bozza ) );

		// Un atto in verifica senza scelta, mandato quando l'elenco era vuoto, si ferma se poi arriva una voce.
		$senza = $this->in_verifica_con( 'In verifica prima della voce', null );
		$this->assertSame( 'pending', get_post_status( $senza ), 'Precondizione: a elenco vuoto va in verifica senza scelta.' );
		$this->aggiungi( 'Consorzio arrivato dopo' );
		$this->assertWPError( $this->pubblica_da_codice( $senza, $this->pubblicatore ) );
		$this->assertSame( 'pending', get_post_status( $senza ) );

		// L'elenco cancellato da fuori non fa ripartire i numeri: la voce nuova non prende quello che un atto ha gia' scelto.
		$consorzio = $this->numero_di( 'Consorzio arrivato dopo' );
		$scelto    = $this->in_verifica_con( 'In verifica prima della cancellazione', (string) $consorzio );
		$this->assertSame( 'pending', get_post_status( $scelto ), 'Precondizione: in verifica con la voce.' );

		delete_option( AmministrazioniOspitate::OPZIONE );
		$this->aggiungi( 'Ente arrivato dopo la cancellazione' );

		$this->assertGreaterThan( $consorzio, $this->numero_di( 'Ente arrivato dopo la cancellazione' ), 'Il numero nuovo viene dopo quelli che gli atti indicano.' );
		$this->assertWPError( $this->pubblica_da_codice( $scelto, $this->pubblicatore ), 'L\'atto non passa all\'amministrazione nuova.' );
		$this->assertNull( Repertorio::di( $scelto ) );

		// Un salvataggio chiamato dentro un salvataggio si rifiuta, e il numero non si da' due volte.
		$interno  = null;
		$chiamato = false;
		$dentro   = function ( $valore ) use ( &$interno, &$chiamato ) {
			if ( ! $chiamato ) {
				$chiamato = true;
				$interno  = AmministrazioniOspitate::salva( array(), array(), 'Ente annidato', $this->pubblicatore, Passaggi::ora() );
			}

			return $valore;
		};

		add_filter( 'pre_update_option_' . AmministrazioniOspitate::OPZIONE, $dentro );

		try {
			$esterno = AmministrazioniOspitate::salva( array(), array(), 'Ente esterno', $this->pubblicatore, Passaggi::ora() );
		} finally {
			remove_filter( 'pre_update_option_' . AmministrazioniOspitate::OPZIONE, $dentro );
		}

		$this->assertTrue( $esterno, 'Il salvataggio esterno riesce.' );
		$this->assertWPError( $interno, 'Quello annidato si rifiuta.' );
		$this->assertNotContains( 'Ente annidato', AmministrazioniOspitate::voci() );
		$this->assertContains( 'Ente esterno', AmministrazioniOspitate::voci() );

		// Un numero dato e mai scritto su un atto non si ridà nemmeno con l'elenco cancellato: lo ricorda il registro.
		$this->aggiungi( 'Ente Alfa' );
		$alfa   = $this->numero_di( 'Ente Alfa' );
		$aperta = $this->atto_completo_senza_controllo( 'Bozza con il modulo aperto' );

		delete_option( AmministrazioniOspitate::OPZIONE );
		$this->aggiungi( 'Ente Beta' );

		$this->assertGreaterThan( $alfa, $this->numero_di( 'Ente Beta' ), 'Il numero di Alfa non va a Beta.' );
		$motivi = $this->invia_scheda( $aperta, (string) $alfa );
		$this->assertStringContainsString( 'non e\' nell\'elenco', implode( ' ', $motivi ), 'Il modulo aperto prima non sceglie Beta con il numero di Alfa.' );
		$this->assertSame( array(), get_post_meta( $aperta, AmministrazioniOspitate::META, false ) );

		// Un salvataggio che non si conferma torna indietro su tutto: elenco e registro restano com'erano.
		$prima     = get_option( AmministrazioniOspitate::OPZIONE );
		$numeri    = count( conformita_core_voci_registro( array( 'azione' => AmministrazioniOspitate::AZIONE_NUMERO ) ) );
		$altera    = static function ( $valore ) {
			array_pop( $valore['voci'] );

			return $valore;
		};
		$letture   = 0;
		$cieca     = static function ( $istruzione ) use ( &$letture ) {
			if ( false !== strpos( (string) $istruzione, AmministrazioniOspitate::OPZIONE ) && 0 === stripos( ltrim( (string) $istruzione ), 'SELECT' ) && 2 === ++$letture ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $istruzione;
		};
		$suppressi = $GLOBALS['wpdb']->suppress_errors( true );

		foreach ( array(
			'scrittura alterata'               => array( 'pre_update_option_' . AmministrazioniOspitate::OPZIONE, $altera ),
			'lettura di conferma che fallisce' => array( 'query', $cieca ),
		) as $caso => list( $aggancio, $funzione ) ) {
			$letture = 0;
			add_filter( $aggancio, $funzione );

			try {
				$esito = AmministrazioniOspitate::salva( array(), array(), "Ente Gamma\nEnte Delta", $this->pubblicatore, Passaggi::ora() );
			} finally {
				remove_filter( $aggancio, $funzione );
			}

			$this->assertWPError( $esito, $caso . ': il salvataggio si rifiuta.' );
			$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ), $caso . ': l\'elenco e\' quello di prima.' );
			$this->assertCount( $numeri, conformita_core_voci_registro( array( 'azione' => AmministrazioniOspitate::AZIONE_NUMERO ) ), $caso . ': nessun numero segnato.' );
		}

		// Un registro che non si legge non vale come "nessun numero dato".
		$registro_cieco = static function ( $istruzione ) {
			if ( false !== strpos( (string) $istruzione, 'conformita_core_registro' ) && false !== strpos( (string) $istruzione, AmministrazioniOspitate::AZIONE_NUMERO ) && 0 === stripos( ltrim( (string) $istruzione ), 'SELECT' ) ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $istruzione;
		};

		add_filter( 'query', $registro_cieco );

		try {
			$esito = AmministrazioniOspitate::salva( array(), array(), 'Ente Epsilon', $this->pubblicatore, Passaggi::ora() );
		} finally {
			remove_filter( 'query', $registro_cieco );
			$GLOBALS['wpdb']->suppress_errors( $suppressi );
		}

		$this->assertWPError( $esito, 'Registro illeggibile: il salvataggio si rifiuta.' );
		$this->assertSame( 'albo_ospitate_illeggibile', $esito->get_error_code() );
		$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ), 'Registro illeggibile: l\'elenco e\' quello di prima.' );

		// Un numero gia' segnato nel registro per un'altra strada si rifiuta dalla banca dati, con la chiave unica.
		$this->assertTrue(
			conformita_core_registra_voce(
				array(
					'sezione'     => \AlboPretorioPa\SEZIONE,
					'azione'      => 'prova_numero_preso',
					'motivazione' => 'Numero preso da un\'altra strada.',
					'dettagli'    => array(),
					'chiave'      => \AlboPretorioPa\SEZIONE . ':amministrazione_ospitata:' . $prima['prossimo'],
				)
			) > 0
		);
		$suppressi = $GLOBALS['wpdb']->suppress_errors( true );
		$esito     = AmministrazioniOspitate::salva( array(), array(), 'Ente Zeta', $this->pubblicatore, Passaggi::ora() );
		$GLOBALS['wpdb']->suppress_errors( $suppressi );

		$this->assertWPError( $esito, 'Numero gia\' segnato: il salvataggio si rifiuta.' );
		$this->assertSame( 'albo_ospitate_numero', $esito->get_error_code() );
		$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ), 'Numero gia\' segnato: l\'elenco e\' quello di prima.' );
	}

	/**
	 * A-260: [attacco] salvataggio dell'elenco e transazioni di altri.
	 */
	public function test_a260_transazioni_altrui(): void {
		global $wpdb;

		$this->aggiungi( 'Ente Alfa' );

		$prima  = get_option( AmministrazioniOspitate::OPZIONE );
		$numeri = static function (): int {
			return count( conformita_core_voci_registro( array( 'azione' => AmministrazioniOspitate::AZIONE_NUMERO ) ) );
		};
		$quanti = $numeri();

		// Dentro la transazione di un altro componente (qui quella delle prove) il salvataggio si rifiuta.
		$this->assertTrue( \AlboPretorioPa\Repertorio::transazione_esterna(), 'Precondizione: la suite tiene aperta la sua transazione.' );
		$this->ammetti_transazione_esterna( false );

		try {
			$esito = AmministrazioniOspitate::salva( array(), array(), 'Ente esterno', $this->pubblicatore, Passaggi::ora() );
		} finally {
			$this->ammetti_transazione_esterna( true );
		}

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_ospitate_transazione_esterna', $esito->get_error_code() );
		$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ) );
		$this->assertSame( $quanti, $numeri() );

		// Un salvataggio chiamato durante una pubblicazione si rifiuta, e la pubblicazione riesce.
		$atto     = $this->in_verifica_con( 'Pubblicato con un salvataggio in mezzo', '0' );
		$interno  = null;
		$aggancio = function () use ( &$interno ) {
			if ( null === $interno ) {
				$interno = AmministrazioniOspitate::salva( array(), array(), 'Ente dentro la pubblicazione', $this->pubblicatore, Passaggi::ora() );
			}
		};

		add_action( 'save_post_' . TIPO, $aggancio );

		try {
			$this->pubblica( $atto );
		} finally {
			remove_action( 'save_post_' . TIPO, $aggancio );
		}

		$this->assertWPError( $interno, 'Il salvataggio dentro la pubblicazione si rifiuta.' );
		$this->assertSame( 'albo_ospitate_transazione_esterna', $interno->get_error_code() );
		$this->assertFalse( Passaggi::transazione_aperta(), 'Chiusa la pubblicazione, nessuna transazione resta aperta.' );

		// Una pubblicazione che fallisce a meta' non lascia segnata una transazione aperta.
		$fallito = $this->in_verifica_con( 'Pubblicazione che fallisce', '0' );
		$guasto  = static function () {
			throw new \RuntimeException( 'guasto di prova' );
		};

		add_action( 'save_post_' . TIPO, $guasto );

		try {
			$this->pubblica_da_codice( $fallito, $this->pubblicatore );
			$this->fail( 'La pubblicazione doveva fallire.' );
		} catch ( \RuntimeException $errore ) {
			$this->assertSame( 'guasto di prova', $errore->getMessage() );
		} finally {
			remove_action( 'save_post_' . TIPO, $guasto );
		}

		$this->assertSame( 'pending', get_post_status( $fallito ) );
		$this->assertFalse( Passaggi::transazione_aperta(), 'Annullata la pubblicazione, nessuna transazione resta aperta.' );
		$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ) );
		$this->assertSame( $quanti, $numeri() );

		// Una pubblicazione o un rimando in bozza chiamati durante il salvataggio si rifiutano, e il salvataggio riesce.
		$altro      = $this->in_verifica_con( 'Pubblicato durante un salvataggio', '0' );
		$rimandato  = $this->in_verifica_con( 'Rimandato durante un salvataggio', '0' );
		$pubblicato = null;
		$rimando    = null;
		$dentro     = function ( $valore ) use ( &$pubblicato, &$rimando, $altro, $rimandato ) {
			if ( null === $pubblicato ) {
				$pubblicato = $this->pubblica_da_codice( $altro, $this->pubblicatore );
				$rimando    = $this->come(
					$this->pubblicatore,
					static function () use ( $rimandato ) {
						return albo_pretorio_rimanda_in_bozza( $rimandato, 'Da rivedere.' );
					}
				);
			}

			return $valore;
		};

		add_filter( 'pre_update_option_' . AmministrazioniOspitate::OPZIONE, $dentro );

		try {
			$esito = AmministrazioniOspitate::salva( array(), array(), 'Ente Beta', $this->pubblicatore, Passaggi::ora() );
		} finally {
			remove_filter( 'pre_update_option_' . AmministrazioniOspitate::OPZIONE, $dentro );
		}

		$this->assertTrue( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertWPError( $pubblicato, 'La pubblicazione dentro il salvataggio si rifiuta.' );
		$this->assertSame( 'pending', get_post_status( $altro ) );
		$this->assertNull( Repertorio::di( $altro ), 'Il rifiuto non consuma il numero.' );
		$this->assertWPError( $rimando, 'Il rimando in bozza dentro il salvataggio si rifiuta.' );
		$this->assertSame( 'pending', get_post_status( $rimandato ) );
		$this->assertContains( 'Ente Beta', AmministrazioniOspitate::voci() );

		// Con la tabella delle opzioni o quella del registro che non annullano, o con il tipo che non si legge, si rifiuta prima di scrivere.
		$prima  = get_option( AmministrazioniOspitate::OPZIONE );
		$quanti = $numeri();

		foreach ( array(
			'opzioni'  => $wpdb->options,
			'registro' => $wpdb->prefix . 'conformita_core_registro',
			'lettura'  => null,
		) as $caso => $tabella ) {
			$motore = static function ( $istruzione ) use ( $tabella ) {
				if ( false === strpos( (string) $istruzione, 'information_schema.ENGINES' ) ) {
					return $istruzione;
				}

				return null === $tabella ? 'SELECT guasto_forzato FROM tabella_che_non_esiste' : str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$tabella}' AND t.TABLE_NAME IN (", (string) $istruzione );
			};

			add_filter( 'query', $motore );
			$zitto = $wpdb->suppress_errors( true );

			try {
				$esito = AmministrazioniOspitate::salva( array(), array(), 'Ente Gamma', $this->pubblicatore, Passaggi::ora() );
			} finally {
				$wpdb->suppress_errors( $zitto );
				remove_filter( 'query', $motore );
			}

			$this->assertWPError( $esito, $caso );
			$this->assertSame( 'albo_ospitate_tabelle_non_transazionali', $esito->get_error_code(), $caso );
			$this->assertSame( $prima, get_option( AmministrazioniOspitate::OPZIONE ), $caso . ': l\'elenco e\' quello di prima.' );
			$this->assertSame( $quanti, $numeri(), $caso . ': nessun numero segnato.' );
		}
	}

	/**
	 * A-254: [attacco] valori scritti di lato e letture che non rispondono.
	 */
	public function test_a254_valori_scritti_di_lato(): void {
		$this->pagina_albo();
		$this->aggiungi( self::OSPITATA );

		$ospitata = $this->numero_di( self::OSPITATA );

		foreach ( array( '01', ' 1', $ospitata . "\n", 'abc', '-1', '1.0', '9999999999' ) as $valore ) {
			$id = $this->atto_completo_senza_controllo( 'Valore storto' );
			update_post_meta( $id, AmministrazioniOspitate::META, $valore );

			$this->assertNull( AmministrazioniOspitate::scelta( $id ), wp_json_encode( $valore ) );
			$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $id ), wp_json_encode( $valore ) );
		}

		$doppio = $this->atto_completo_senza_controllo( 'Due righe' );
		add_post_meta( $doppio, AmministrazioniOspitate::META, '0' );
		add_post_meta( $doppio, AmministrazioniOspitate::META, (string) $ospitata );
		$this->assertNull( AmministrazioniOspitate::scelta( $doppio ), 'Due righe valgono come nessuna.' );

		// Una scelta storta non e' una scelta assente: anche a elenco vuoto l'atto non va avanti, e "Da scegliere" non la toglie.
		$storta_uno = $this->atto_completo_senza_controllo( 'Storta con zero davanti' );
		$verifica   = $this->in_verifica_con( 'In verifica, poi scritta storta', (string) $ospitata );
		$storte     = array( $storta_uno => array( '01' ) );

		update_post_meta( $storta_uno, AmministrazioniOspitate::META, '01' );
		$storte[ $doppio ] = array( '0', (string) $ospitata );
		$this->scavalca_barriera(
			static function () use ( $verifica ) {
				update_post_meta( $verifica, AmministrazioniOspitate::META, '01' );
			}
		);

		$this->assertTrue( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( (string) $ospitata ) ) ) );
		$this->assertSame( array(), AmministrazioniOspitate::voci(), 'Precondizione: l\'elenco e\' vuoto.' );

		foreach ( $storte as $id => $righe ) {
			$this->assertTrue( AmministrazioniOspitate::da_chiedere( $id ), $id . ': la domanda resta.' );
			$this->assertStringContainsString( 'non si legge', $this->riquadro( $id ), $id . ': il riquadro dice perche\'.' );
			$this->invia_scheda( $id, '', 'pending' );
			$this->assertSame( 'draft', get_post_status( $id ), $id . ': resta in bozza.' );
			$this->assertSame( $righe, get_post_meta( $id, AmministrazioniOspitate::META, false ), $id . ': le righe storte restano.' );
		}

		$esito = $this->pubblica_da_codice( $verifica, $this->pubblicatore );
		$this->assertWPError( $esito, 'La scelta storta ferma la pubblicazione anche a elenco vuoto.' );
		$this->assertNull( Repertorio::di( $verifica ), 'Nessun numero consumato.' );

		// Una scelta vera sostituisce tutte le righe storte.
		$this->invia_scheda( $doppio, '0', 'pending' );
		$this->assertSame( array( '0' ), get_post_meta( $doppio, AmministrazioniOspitate::META, false ) );
		$this->assertSame( 'pending', get_post_status( $doppio ), 'Con la scelta fatta va in verifica.' );

		$this->aggiungi( self::OSPITATA );
		$ospitata = $this->numero_di( self::OSPITATA );

		// La scheda rifiuta una voce che non c'e' e salva il resto.
		$fuori  = $this->atto_completo_senza_controllo( 'Voce fuori elenco' );
		$motivi = $this->invia_scheda( $fuori, '42' );
		$this->assertStringContainsString( 'non e\' nell\'elenco', implode( ' ', $motivi ) );
		$this->assertSame( array(), get_post_meta( $fuori, AmministrazioniOspitate::META, false ) );

		// L'elenco scritto di lato in forma diversa: la domanda resta, passa solo "questa amministrazione".
		$atto_ospitato = $this->in_verifica_con( 'Ospitato prima del guasto', (string) $ospitata );
		$this->pubblica( $atto_ospitato );

		update_option( AmministrazioniOspitate::OPZIONE, array( 'voci' => 'storto' ) );
		$this->assertTrue( AmministrazioniOspitate::illeggibile() );

		$guasta = $this->atto_completo_senza_controllo( 'Atto con elenco guasto' );
		$this->assertStringContainsString( 'name="' . SchedaAtto::CAMPO_AMMINISTRAZIONE . '"', $this->riquadro( $guasta ) );
		$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $guasta ) );
		$this->invia_scheda( $guasta, '0' );
		$this->assertArrayNotHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $guasta ) );
		$this->assertWPError( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_NUOVE => 'Ente nuovo' ) ), 'Sopra un elenco che non si legge non si salva.' );
		$this->assertSame( array( 'voci' => 'storto' ), get_option( AmministrazioniOspitate::OPZIONE ) );

		// L'atto pubblicato conserva il nome fissato nel registro anche con l'elenco guasto.
		$this->assertSame( self::OSPITATA, $this->scheda( $atto_ospitato )[ AmministrazioniOspitate::etichetta() ] );

		// E anche con due righe scritte di lato sull'atto pubblicato: vale il nome fissato, non "questa amministrazione".
		$this->scavalca_barriera(
			static function () use ( $atto_ospitato ) {
				add_post_meta( $atto_ospitato, AmministrazioniOspitate::META, '0' );
			}
		);
		$this->assertSame( self::OSPITATA, AmministrazioniOspitate::da_mostrare( $atto_ospitato ), 'Scelta storta sull\'atto pubblicato: il nome fissato.' );
		$this->scavalca_barriera(
			static function () use ( $atto_ospitato ) {
				delete_post_meta( $atto_ospitato, AmministrazioniOspitate::META, '0' );
			}
		);

		// Se la voce di conferma non si legge si dice, invece di tacere.
		$guasto = static function ( $istruzione ) {
			return false !== strpos( (string) $istruzione, 'conformita_core_registro' ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		add_filter( 'query', $guasto );
		$suppressi = $GLOBALS['wpdb']->suppress_errors( true );

		try {
			$this->assertSame( 'non leggibile in questo momento', AmministrazioniOspitate::da_mostrare( $atto_ospitato ) );
		} finally {
			remove_filter( 'query', $guasto );
			$GLOBALS['wpdb']->suppress_errors( $suppressi );
		}

		// Ripristinato un elenco valido, la sua lettura che fallisce non vale come elenco vuoto.
		delete_option( AmministrazioniOspitate::OPZIONE );
		$this->aggiungi( self::OSPITATA );
		$salvata  = get_option( AmministrazioniOspitate::OPZIONE );
		$senza    = $this->atto_completo_senza_controllo( 'Senza scelta, lettura fallita' );
		$ospitata = $this->numero_di( self::OSPITATA );
		$guasto   = static function ( $istruzione ) {
			return false !== strpos( (string) $istruzione, AmministrazioniOspitate::OPZIONE ) && 0 === stripos( ltrim( (string) $istruzione ), 'SELECT' ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};

		$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $senza ), 'Controllo positivo: con l\'elenco letto la scelta manca.' );

		add_filter( 'query', $guasto );
		$suppressi = $GLOBALS['wpdb']->suppress_errors( true );

		try {
			$this->assertTrue( AmministrazioniOspitate::illeggibile(), 'La lettura fallita e\' un elenco illeggibile.' );
			$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $senza ), 'La lettura fallita non fa passare l\'atto senza scelta.' );
			$this->assertWPError( AmministrazioniOspitate::salva( array(), array(), 'Ente durante il guasto', $this->pubblicatore, Passaggi::ora() ), 'Sopra una lettura fallita non si salva.' );
		} finally {
			remove_filter( 'query', $guasto );
			$GLOBALS['wpdb']->suppress_errors( $suppressi );
		}

		$this->assertSame( $salvata, get_option( AmministrazioniOspitate::OPZIONE ), 'L\'elenco non e\' stato ricominciato.' );

		/*
		 * La pubblicazione controlla e prende il nome da una lettura sola. Se
		 * l'elenco si rileggesse, la seconda lettura troverebbe la voce tolta
		 * da un'altra richiesta e l'atto ospitato uscirebbe come atto di
		 * questa amministrazione.
		 */
		$conteso = $this->in_verifica_con( 'Ospitato conteso', (string) $ospitata );
		$letture = 0;
		$togli   = static function ( $istruzione ) use ( &$letture, $ospitata ) {
			if ( false === strpos( (string) $istruzione, AmministrazioniOspitate::OPZIONE ) || 0 !== stripos( ltrim( (string) $istruzione ), 'SELECT' ) ) {
				return $istruzione;
			}

			++$letture;

			if ( 2 === $letture ) {
				global $wpdb;

				$valore         = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s AND %d", AmministrazioniOspitate::OPZIONE, 1 ) ) );
				$valore['voci'] = array_values( array_filter( $valore['voci'], static fn( $voce ) => $voce['id'] !== $ospitata ) );
				$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $valore ) ), array( 'option_name' => AmministrazioniOspitate::OPZIONE ) );
			}

			return $istruzione;
		};

		add_filter( 'query', $togli );

		try {
			$dettagli = AmministrazioniOspitate::per_registro( $conteso );
		} finally {
			remove_filter( 'query', $togli );
		}

		$this->assertSame(
			array(
				AmministrazioniOspitate::DETTAGLIO_NOME => self::OSPITATA,
				AmministrazioniOspitate::DETTAGLIO_VOCE => $ospitata,
			),
			$dettagli,
			'Controllo e nome dalla stessa lettura.'
		);

		/*
		 * Lo stesso attraverso la pubblicazione vera: la voce si toglie dopo
		 * ciascuna delle letture dell'elenco che la pubblicazione fa, una per
		 * volta. La pubblicazione riesce con il nome letto, o si rifiuta senza
		 * consumare il numero: mai un nome vuoto o un'altra amministrazione.
		 */
		global $wpdb;

		$intero     = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", AmministrazioniOspitate::OPZIONE ) );
		$ripristina = static function () use ( $intero ) {
			global $wpdb;

			$wpdb->update( $wpdb->options, array( 'option_value' => $intero ), array( 'option_name' => AmministrazioniOspitate::OPZIONE ) );
			wp_cache_delete( AmministrazioniOspitate::OPZIONE, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		};
		$ripristina();

		$conta  = 0;
		$conto  = static function ( $istruzione ) use ( &$conta ) {
			if ( false !== strpos( (string) $istruzione, AmministrazioniOspitate::OPZIONE ) && 0 === stripos( ltrim( (string) $istruzione ), 'SELECT' ) ) {
				++$conta;
			}

			return $istruzione;
		};
		$misura = $this->in_verifica_con( 'Ospitato misurato', (string) $ospitata );

		add_filter( 'query', $conto );

		try {
			$this->pubblica( $misura );
		} finally {
			remove_filter( 'query', $conto );
		}

		$this->assertGreaterThan( 0, $conta, 'Precondizione: la pubblicazione legge l\'elenco.' );
		$this->assertSame( self::OSPITATA, $this->conferma( $misura )[ AmministrazioniOspitate::DETTAGLIO_NOME ] );

		for ( $dopo = 1; $dopo <= $conta; $dopo++ ) {
			$ripristina();

			$atto    = $this->in_verifica_con( 'Ospitato conteso nella pubblicazione ' . $dopo, (string) $ospitata );
			$letture = 0;
			$tolta   = false;
			$rimuovi = static function () use ( &$tolta, $ospitata ) {
				global $wpdb;

				$tolta          = true;
				$valore         = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s AND %d", AmministrazioniOspitate::OPZIONE, 1 ) ) );
				$valore['voci'] = array_values( array_filter( $valore['voci'], static fn( $voce ) => $voce['id'] !== $ospitata ) );
				$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $valore ) ), array( 'option_name' => AmministrazioniOspitate::OPZIONE ) );
			};
			$togli   = static function ( $istruzione ) use ( &$letture, &$tolta, $dopo, $rimuovi ) {
				if ( $tolta || false === strpos( (string) $istruzione, AmministrazioniOspitate::OPZIONE ) || 0 !== stripos( ltrim( (string) $istruzione ), 'SELECT' ) ) {
					return $istruzione;
				}

				++$letture;

				// Prima della lettura seguente, cioe' dopo la lettura numero $dopo.
				if ( $dopo + 1 === $letture ) {
					$rimuovi();
				}

				return $istruzione;
			};
			// Dopo l'ultima lettura non ne segue un'altra: la voce si toglie alla prima scrittura dell'atto.
			$dopo_ultima = static function () use ( &$tolta, &$letture, $dopo, $rimuovi ) {
				if ( ! $tolta && $letture >= $dopo ) {
					$rimuovi();
				}
			};

			add_filter( 'query', $togli );
			add_action( 'save_post_' . TIPO, $dopo_ultima, 1 );

			try {
				$esito = $this->pubblica_da_codice( $atto, $this->pubblicatore );
			} finally {
				remove_filter( 'query', $togli );
				remove_action( 'save_post_' . TIPO, $dopo_ultima, 1 );
			}

			$this->assertTrue( $tolta, 'La voce e\' stata tolta dopo la lettura ' . $dopo . '.' );

			if ( is_wp_error( $esito ) ) {
				$this->assertSame( 'pending', get_post_status( $atto ), 'Rifiuto dopo la lettura ' . $dopo . ': l\'atto resta in verifica.' );
				$this->assertNull( Repertorio::di( $atto ), 'Rifiuto dopo la lettura ' . $dopo . ': nessun numero.' );
				continue;
			}

			$this->assertSame( 'publish', get_post_status( $atto ), 'Dopo la lettura ' . $dopo );
			$this->assertSame(
				array(
					AmministrazioniOspitate::DETTAGLIO_NOME => self::OSPITATA,
					AmministrazioniOspitate::DETTAGLIO_VOCE => $ospitata,
				),
				array_intersect_key( $this->conferma( $atto ), array_flip( array( AmministrazioniOspitate::DETTAGLIO_NOME, AmministrazioniOspitate::DETTAGLIO_VOCE ) ) ),
				'Pubblicato dopo la lettura ' . $dopo . ': il nome e\' quello letto.'
			);
		}

		$ripristina();
	}

	/**
	 * A-253: un'altra richiesta rimette in memoria la scelta di prima mentre la nuova si conferma.
	 */
	public function test_a253_memoria_rimessa_prima_della_conferma(): void {
		$this->aggiungi( self::OSPITATA );

		$ospitata = $this->numero_di( self::OSPITATA );
		$id       = $this->atto_completo_senza_controllo( 'Scelta cambiata, memoria rimessa' );

		$this->invia_scheda( $id, '0' );
		$this->assertSame( array( '0' ), get_post_meta( $id, AmministrazioniOspitate::META, false ), 'Precondizione: la scelta di prima.' );

		// Nell'istante della conferma un'altra richiesta legge la riga ancora confermata e la rimette in memoria.
		$rimesse = 0;
		$rimette = static function ( $istruzione ) use ( $id, &$rimesse ) {
			if ( 1 === preg_match( '/^\s*(COMMIT|RELEASE SAVEPOINT albo_pretorio_scelta)\b/i', (string) $istruzione ) ) {
				wp_cache_set( $id, array( AmministrazioniOspitate::META => array( '0' ) ), 'post_meta' );
				++$rimesse;
			}

			return $istruzione;
		};
		$vista   = null;
		$guarda  = static function ( $atto ) use ( $id, &$vista ) {
			if ( $id === (int) $atto ) {
				$vista = get_post_meta( $id, AmministrazioniOspitate::META, false );
			}
		};

		add_filter( 'query', $rimette );
		add_action( 'wp_after_insert_post', $guarda, PHP_INT_MAX );

		try {
			$motivi = $this->invia_scheda( $id, (string) $ospitata );
		} finally {
			remove_filter( 'query', $rimette );
			remove_action( 'wp_after_insert_post', $guarda, PHP_INT_MAX );
		}

		$this->assertSame( array(), $motivi );
		$this->assertSame( 1, $rimesse, 'Precondizione: la memoria e\' stata rimessa una volta, alla conferma.' );
		$this->assertSame( array( (string) $ospitata ), $vista, 'Dopo la conferma la memoria non tiene la scelta di prima.' );
	}

	/**
	 * A-254: [attacco] la memoria tiene una scelta diversa da quella nella banca dati.
	 */
	public function test_a254_scelta_in_memoria_diversa(): void {
		$this->pagina_albo();
		$this->aggiungi( self::OSPITATA );

		$ospitata = $this->numero_di( self::OSPITATA );
		$vecchia  = static function ( int $atto, string $valore ): void {
			wp_cache_set( $atto, array( AmministrazioniOspitate::META => array( $valore ) ), 'post_meta' );
		};

		// La memoria dice "questa amministrazione", la banca dati la voce: si fissa la voce.
		$ospitato = $this->in_verifica_con( 'Ospitato, memoria vecchia', (string) $ospitata );
		$this->assertSame( 'pending', get_post_status( $ospitato ), 'Precondizione: in verifica.' );
		$vecchia( $ospitato, '0' );
		$this->assertSame( array( '0' ), get_post_meta( $ospitato, AmministrazioniOspitate::META, false ), 'Precondizione: la memoria e\' vecchia.' );
		$this->assertSame( $ospitata, AmministrazioniOspitate::per_registro( $ospitato )[ AmministrazioniOspitate::DETTAGLIO_VOCE ], 'Il nome da fissare viene dalla banca dati.' );

		$vecchia( $ospitato, '0' );
		$this->pubblica( $ospitato );
		$this->assertSame( $ospitata, $this->conferma( $ospitato )[ AmministrazioniOspitate::DETTAGLIO_VOCE ] );
		$this->assertSame( self::OSPITATA, $this->conferma( $ospitato )[ AmministrazioniOspitate::DETTAGLIO_NOME ] );

		// La voce dell'atto e' stata tolta, la memoria dice "questa amministrazione": l'atto non si pubblica.
		$tolta = $this->in_verifica_con( 'Ospitato, voce tolta, memoria vecchia', (string) $ospitata );
		$this->assertSame( 'pending', get_post_status( $tolta ), 'Precondizione: in verifica.' );
		$this->assertTrue( $this->invia_elenco( $this->pubblicatore, array( SchermataAmministrazioniOspitate::CAMPO_TOGLI => array( (string) $ospitata ) ) ) );
		$this->assertSame( array(), AmministrazioniOspitate::voci(), 'Precondizione: l\'elenco e\' vuoto.' );
		$vecchia( $tolta, '0' );

		$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $tolta ) );
		$vecchia( $tolta, '0' );
		$esito = $this->pubblica_da_codice( $tolta, $this->pubblicatore );
		$this->assertWPError( $esito, 'La voce tolta ferma la pubblicazione anche con la memoria vecchia.' );
		$this->assertSame( 'pending', get_post_status( $tolta ) );
		$this->assertNull( Repertorio::di( $tolta ), 'Nessun numero consumato.' );

		// Una lettura della banca dati che fallisce non vale come scelta assente.
		$senza = $this->in_verifica_con( 'Questa amministrazione, lettura fallita', '0' );
		$this->assertSame( 'pending', get_post_status( $senza ), 'Precondizione: in verifica.' );
		$guasto    = static function ( $istruzione ) {
			return false !== strpos( (string) $istruzione, AmministrazioniOspitate::META ) && 0 === stripos( ltrim( (string) $istruzione ), 'SELECT meta_value' ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$suppressi = $GLOBALS['wpdb']->suppress_errors( true );
		add_filter( 'query', $guasto );

		try {
			$this->assertArrayHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $senza ) );
		} finally {
			remove_filter( 'query', $guasto );
			$GLOBALS['wpdb']->suppress_errors( $suppressi );
		}

		$this->assertArrayNotHasKey( 'albo_manca_amministrazione', DatiAtto::mancanti( $senza ), 'Con la lettura ripristinata la scelta vale.' );
	}

	/**
	 * A-255: l'avviso di un atto ospitato annullato porta ancora l'amministrazione.
	 */
	public function test_a255_annullato_ospitato(): void {
		$this->pagina_albo();
		$this->aggiungi( self::OSPITATA );

		$id = $this->in_verifica_con( 'Atto ospitato poi annullato', (string) $this->numero_di( self::OSPITATA ) );
		$this->pubblica( $id );

		$esito = $this->come(
			$this->pubblicatore,
			static function () use ( $id ) {
				return albo_pretorio_annulla( $id, 'Errore materiale nell\'oggetto' );
			}
		);

		$this->assertIsInt( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ) );

		$this->assertSame( self::OSPITATA, $this->scheda( $id )[ AmministrazioniOspitate::etichetta() ] );
		$this->assertStringContainsString( 'Atto di: ' . self::OSPITATA, (string) $this->riga( $this->disegnata(), $id ) );
	}

	/**
	 * A-256: salvataggi dell'elenco sovrapposti, da processi veri.
	 *
	 * Sei processi aggiungono dieci voci ciascuno, una per salvataggio, partendo
	 * insieme. Senza la fila due salvataggi leggerebbero lo stesso numero per la
	 * voce nuova e l'ultimo cancellerebbe le aggiunte dell'altro.
	 */
	public function test_a256_salvataggi_sovrapposti(): void {
		global $wpdb, $table_prefix;

		$processi  = 6;
		$per_ogni  = 10;
		$cartella  = get_temp_dir() . 'albo-ospitate-' . wp_generate_password( 8, false );
		$core_file = albo_pretorio_file_core();
		$attesi    = array();
		$gruppi    = array();

		for ( $i = 0; $i < $processi; $i++ ) {
			for ( $j = 0; $j < $per_ogni; $j++ ) {
				$gruppi[ $i ][] = sprintf( 'Ente %d di %d', $j, $i );
				$attesi[]       = sprintf( 'Ente %d di %d', $j, $i );
			}
		}

		$this->assertNotSame( '', $core_file, 'Precondizione: il componente comune deve essere raggiungibile dai figli.' );
		$this->assertTrue( wp_mkdir_p( $cartella ) );

		self::commit_transaction();

		$tubi  = array();
		$figli = array();

		try {
			for ( $i = 0; $i < $processi; $i++ ) {
				$ambiente                     = getenv();
				$ambiente['ALBO_CONCORRENZA'] = wp_json_encode(
					array(
						'abspath'     => ABSPATH,
						'db_name'     => DB_NAME,
						'db_user'     => DB_USER,
						'db_password' => DB_PASSWORD,
						'db_host'     => DB_HOST,
						'prefisso'    => $table_prefix,
						'core'        => $core_file,
						'albo'        => \AlboPretorioPa\FILE_PRINCIPALE,
						'cartella'    => $cartella,
						'indice'      => $i,
						'nomi'        => $gruppi[ $i ],
					)
				);

				$figli[ $i ] = proc_open(
					array( PHP_BINARY, __DIR__ . '/concorrenza/ospitate.php' ),
					array(
						1 => array( 'pipe', 'w' ),
						2 => array( 'pipe', 'w' ),
					),
					$tubi[ $i ],
					null,
					$ambiente
				);

				$this->assertIsResource( $figli[ $i ], "Il processo {$i} deve partire." );
			}

			$limite = microtime( true ) + 60;

			do {
				$pronti = count( (array) glob( $cartella . '/pronto-*' ) );

				$this->assertLessThan( $limite, microtime( true ), 'I processi figli non sono diventati pronti.' );
				usleep( 10000 );
			} while ( $pronti < $processi );

			touch( $cartella . '/via' );

			$risultati = array();

			for ( $i = 0; $i < $processi; $i++ ) {
				$uscita = stream_get_contents( $tubi[ $i ][1] );
				$errori = stream_get_contents( $tubi[ $i ][2] );
				$codice = proc_close( $figli[ $i ] );

				$figli[ $i ] = null;

				$this->assertSame( 0, $codice, "Il processo {$i} e' terminato male: {$errori}" );

				$risultati[ $i ] = json_decode( (string) $uscita, true );

				$this->assertIsArray( $risultati[ $i ], "Il processo {$i} non ha restituito esiti: {$uscita} {$errori}" );
			}

			self::commit_transaction();

			// Controllo di realta': i processi si sono sovrapposti davvero.
			$this->assertLessThan( min( array_column( $risultati, 'fine' ) ), max( array_column( $risultati, 'inizio' ) ), 'I processi non si sono sovrapposti: la prova non misurerebbe niente.' );

			foreach ( $risultati as $i => $risultato ) {
				foreach ( $risultato['esiti'] as $esito ) {
					$this->assertTrue( $esito['esito'], "Il processo {$i} ha ricevuto un rifiuto per {$esito['nome']}." );
				}
			}

			$impostazione = AmministrazioniOspitate::impostazione();
			$voci         = AmministrazioniOspitate::voci();
			$nomi         = array_values( $voci );

			sort( $nomi );
			sort( $attesi );

			$this->assertSame( $attesi, $nomi, 'Ogni voce aggiunta c\'e\', una volta sola.' );
			$this->assertSame( range( 1, $processi * $per_ogni ), array_keys( $voci ), 'Ogni voce ha un numero suo, senza buchi e senza doppioni.' );
			$this->assertSame( $processi * $per_ogni + 1, $impostazione['prossimo'] ?? null, 'Il prossimo numero viene dopo l\'ultimo dato.' );
		} finally {
			foreach ( $figli as $figlio ) {
				if ( is_resource( $figlio ) ) {
					proc_terminate( $figlio );
				}
			}

			// I processi hanno confermato voci del registro fuori dalla transazione della prova: si tolgono a mano.
			delete_option( AmministrazioniOspitate::OPZIONE );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE azione = %s', $wpdb->prefix . 'conformita_core_registro', AmministrazioniOspitate::AZIONE_NUMERO ) );
			self::commit_transaction();

			foreach ( (array) glob( $cartella . '/*' ) as $file ) {
				unlink( $file );
			}

			rmdir( $cartella );
		}
	}
}
