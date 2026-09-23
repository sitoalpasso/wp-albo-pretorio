<?php
/**
 * Passaggio in verifica: righe A-85..A-92.
 *
 * La prima parte del flusso di ALBO-22. Gli invii della schermata passano da
 * `edit_post()`, le chiamate da codice da `wp_insert_post()` e
 * `wp_update_post()`. Dove un passaggio e' rifiutato si osserva anche il valore
 * **scritto** nella tabella dei contenuti, non solo quello riletto alla fine.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Durate;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\SchedaAtto;
use AlboPretorioPa\SchedaDocumenti;
use AlboPretorioPa\TipoAtto;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/**
 * Bozza, passaggio in verifica, blocco dell'atto in verifica, verifica non saltabile.
 */
class PassaggioInVerificaTest extends \WP_UnitTestCase {

	use AmbienteAlbo;
	use DepositoDiProva;

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	private $amministratore = 0;

	/**
	 * Chi redige, autore degli atti di prova.
	 *
	 * @var int
	 */
	private $redattore = 0;

	/**
	 * Un secondo redattore, che non e' autore degli atti di prova.
	 *
	 * @var int
	 */
	private $altro_redattore = 0;

	/**
	 * Chi pubblica, con il ruolo proprio del componente.
	 *
	 * @var int
	 */
	private $pubblicatore = 0;

	/**
	 * Voci degli elenchi, per nome breve.
	 *
	 * @var array<string, int>
	 */
	private $voci = array();

	/**
	 * Le scritture osservate nella tabella dei contenuti: stato per atto.
	 *
	 * @var array<int, array{id: int, stato: string}>
	 */
	private $scritti = array();

	/**
	 * I messaggi delle segnalazioni di uso scorretto.
	 *
	 * @var array<int, string>
	 */
	private $segnalazioni = array();

	/**
	 * Tipo, permessi, dati, utenti, voci, server finto e osservatori.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->azzera_ambiente_albo();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );
		$this->assertTrue( Durate::registra() );
		$this->assertTrue( DatiAtto::registra() );
		$this->assertTrue( DocumentiAtto::registra() );

		Permessi::applica();

		$this->amministratore  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->redattore       = $this->utente_redattore();
		$this->altro_redattore = $this->utente_redattore();
		$this->pubblicatore    = self::factory()->user->create( array( 'role' => RUOLO ) );

		wp_set_current_user( $this->amministratore );

		$this->voci = array(
			'tipo'   => $this->voce( TASSONOMIA_TIPO_ATTO, 'Determinazione' ),
			'altro'  => $this->voce( TASSONOMIA_TIPO_ATTO, 'Deliberazione' ),
			'senza'  => $this->voce( TASSONOMIA_TIPO_ATTO, 'Avviso pubblico' ),
			'organo' => $this->voce( TASSONOMIA_ORGANO, 'Giunta' ),
			'altro2' => $this->voce( TASSONOMIA_ORGANO, 'Consiglio' ),
		);

		$this->assertTrue( Durate::configura( $this->voci['tipo'], 10, 'amministrazione', '' ) );
		$this->assertTrue( Durate::configura( $this->voci['altro'], 15, 'norma', 'Estremi di prova' ) );

		$this->aggancia_server_finto();

		add_filter( 'wp_insert_post_data', array( $this, 'osserva_scrittura' ), PHP_INT_MAX, 2 );
		add_action( 'doing_it_wrong_run', array( $this, 'osserva_segnalazione' ), 10, 2 );
	}

	/**
	 * Ripulisce osservatori, modulo simulato, disco e ambiente.
	 */
	public function tear_down(): void {
		remove_filter( 'wp_insert_post_data', array( $this, 'osserva_scrittura' ), PHP_INT_MAX );
		remove_action( 'doing_it_wrong_run', array( $this, 'osserva_segnalazione' ), 10 );

		$_POST  = array();
		$_FILES = array();
		unset( $GLOBALS['current_screen'] );
		$this->sgancia_server_finto();
		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * Osservatore delle scritture, all'ultima priorita': quello che va davvero nella tabella.
	 *
	 * @param array<string, mixed> $data    Dati che stanno per essere scritti.
	 * @param array<string, mixed> $postarr Richiesta.
	 * @return array<string, mixed>
	 */
	public function osserva_scrittura( $data, $postarr ) {
		if ( TIPO === $data['post_type'] ) {
			$this->scritti[] = array(
				'id'    => isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0,
				'stato' => (string) $data['post_status'],
			);
		}

		return $data;
	}

	/**
	 * Osservatore delle segnalazioni di uso scorretto: il motivo di un rifiuto da codice.
	 *
	 * @param string $funzione Funzione segnalata.
	 * @param string $messaggio Messaggio.
	 */
	public function osserva_segnalazione( $funzione, $messaggio ): void {
		if ( 'wp_insert_post' === $funzione ) {
			$this->segnalazioni[] = (string) $messaggio;
		}
	}

	/**
	 * Gli stati scritti per un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, string>
	 */
	private function stati_scritti( int $atto_id ): array {
		$stati = array();

		foreach ( $this->scritti as $scritto ) {
			if ( $atto_id === $scritto['id'] ) {
				$stati[] = $scritto['stato'];
			}
		}

		return $stati;
	}

	/**
	 * Un utente con i soli permessi di chi redige.
	 *
	 * @return int
	 */
	private function utente_redattore(): int {
		$mappa = Permessi::mappa();

		$this->assertIsArray( $mappa );

		$id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$utente = new \WP_User( $id );

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			$utente->add_cap( $mappa[ $chiave ] );
		}

		$this->assertFalse( user_can( $id, $mappa['publish_posts'] ), 'Precondizione: non deve poter pubblicare.' );

		return $id;
	}

	/**
	 * Una voce di un elenco.
	 *
	 * @param string $tassonomia Elenco.
	 * @param string $nome       Nome.
	 * @return int
	 */
	private function voce( string $tassonomia, string $nome ): int {
		$esito = wp_insert_term( $nome, $tassonomia );

		$this->assertIsArray( $esito );

		return (int) $esito['term_id'];
	}

	/**
	 * Una bozza del redattore con oggetto e testo, senza altri dati.
	 *
	 * Il testo c'e' perche' WordPress considera vuota, e rifiuta per conto suo,
	 * una richiesta senza oggetto e senza testo: la prova sull'oggetto mancante
	 * deve arrivare al guardiano.
	 *
	 * @return int
	 */
	private function bozza(): int {
		$id = wp_insert_post(
			array(
				'post_type'    => TIPO,
				'post_status'  => 'draft',
				'post_title'   => 'Atto di prova',
				'post_content' => 'Testo di prova.',
				'post_author'  => $this->redattore,
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		return $id;
	}

	/**
	 * Una bozza completa, documento principale compreso.
	 *
	 * @return int
	 */
	private function atto_completo(): int {
		$id = $this->bozza();

		wp_set_object_terms( $id, array( $this->voci['tipo'] ), TASSONOMIA_TIPO_ATTO );
		wp_set_object_terms( $id, array( $this->voci['organo'] ), TASSONOMIA_ORGANO );
		update_post_meta( $id, META_DATA_ADOZIONE, '2026-03-10' );

		$this->deposita_documento( $id );

		$this->assertSame( array(), DatiAtto::mancanti( $id ), 'Precondizione: l\'atto e\' completo.' );

		return $id;
	}

	/**
	 * Un atto completo, con un allegato ulteriore, mandato in verifica dal suo autore.
	 *
	 * @return int
	 */
	private function atto_in_verifica(): int {
		$id = $this->atto_completo();

		$this->deposita_documento( $id, DocumentiAtto::ULTERIORE, 'allegato.pdf' );

		$this->passa( $id, 'pending', $this->redattore );

		$this->assertSame( 'pending', get_post_status( $id ), 'Precondizione: l\'atto e\' in verifica.' );

		return $id;
	}

	/**
	 * Chiede un passaggio di stato da codice, come l'utente indicato.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $stato   Stato richiesto.
	 * @param int    $utente  Utente.
	 * @return int|\WP_Error
	 */
	private function passa( int $atto_id, string $stato, int $utente ) {
		$prima = get_current_user_id();

		wp_set_current_user( $utente );

		try {
			return wp_update_post(
				array(
					'ID'          => $atto_id,
					'post_status' => $stato,
				)
			);
		} finally {
			wp_set_current_user( $prima );
			clean_post_cache( $atto_id );
		}
	}

	/**
	 * Un invio vero della schermata dell'atto, dall'utente corrente.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $post    Campi oltre a quelli della schermata.
	 */
	private function invia( int $atto_id, array $post ): void {
		set_current_screen( 'post' );

		$_POST = array_merge(
			array(
				'action'               => 'editpost',
				'post_ID'              => (string) $atto_id,
				'post_type'            => TIPO,
				'post_title'           => get_the_title( $atto_id ),
				'content'              => get_post_field( 'post_content', $atto_id ),
				'original_post_status' => get_post_status( $atto_id ),
				'post_status'          => get_post_status( $atto_id ),
				'_wpnonce'             => wp_create_nonce( 'update-post_' . $atto_id ),
			),
			$post
		);

		try {
			edit_post();
		} finally {
			$_POST = array();
			clean_post_cache( $atto_id );
		}
	}

	/**
	 * I campi del riquadro dei dati, con il gettone valido per l'atto e l'utente corrente.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $campi   Valori per nome del campo.
	 * @return array<string, mixed>
	 */
	private function riquadro_dati( int $atto_id, array $campi ): array {
		return array( SchedaAtto::CAMPO_GETTONE => wp_create_nonce( SchedaAtto::azione( $atto_id ) ) ) + $campi;
	}

	/**
	 * La fotografia intera dell'atto: riga, voci, dati, allegati e disco, letti grezzi.
	 *
	 * **Fuori dalla fotografia due dati di WordPress**, `_edit_last` e
	 * `_edit_lock`: li scrive la schermata a ogni apertura e a ogni invio,
	 * prima di chiedere di salvare, e dicono chi ha avuto la schermata aperta,
	 * non com'e' l'atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function fotografia( int $atto_id ): array {
		global $wpdb;

		clean_post_cache( $atto_id );
		wp_cache_flush();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge cio' che c'e' davvero nella banca dati.
		$riga     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $atto_id ), ARRAY_A );
		$dati     = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key NOT IN ( '_edit_last', '_edit_lock' ) ORDER BY meta_id", $atto_id ), ARRAY_A );
		$allegati = $wpdb->get_results( "SELECT ID, post_parent, post_status FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID", ARRAY_A );
		// phpcs:enable

		return array(
			'riga'     => $riga,
			'tipi'     => wp_get_object_terms( $atto_id, TASSONOMIA_TIPO_ATTO, array( 'fields' => 'ids' ) ),
			'organi'   => wp_get_object_terms( $atto_id, TASSONOMIA_ORGANO, array( 'fields' => 'ids' ) ),
			'dati'     => $dati,
			'allegati' => $allegati,
			'disco'    => $this->documenti_sul_disco(),
		);
	}

	/**
	 * Un riquadro stampato per un atto, dall'utente corrente.
	 *
	 * @param callable $mostra Funzione del riquadro.
	 * @param int      $atto_id Atto.
	 * @return string
	 */
	private function stampa( callable $mostra, int $atto_id ): string {
		ob_start();

		try {
			$mostra( get_post( $atto_id ) );
		} finally {
			$stampato = (string) ob_get_clean();
		}

		return $stampato;
	}

	/**
	 * A-85: la bozza si crea incompleta e si risalva, dalla schermata e da codice.
	 */
	public function test_a85_creazione_e_risalvataggio_della_bozza(): void {
		wp_set_current_user( $this->redattore );

		$id = wp_insert_post(
			array(
				'post_type'   => TIPO,
				'post_status' => 'draft',
				'post_title'  => 'Bozza incompleta',
			)
		);

		$this->assertIsInt( $id );
		$this->assertSame( 'draft', get_post_status( $id ), 'La bozza nasce incompleta.' );
		$this->assertNotSame( array(), DatiAtto::mancanti( $id ), 'Precondizione: mancano dati.' );

		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Bozza ritoccata da codice',
			)
		);

		clean_post_cache( $id );

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 'Bozza ritoccata da codice', get_the_title( $id ) );

		$this->invia(
			$id,
			array( 'post_title' => 'Bozza ritoccata dalla schermata' ) + $this->riquadro_dati( $id, array( SchedaAtto::CAMPO_TIPO => (string) $this->voci['tipo'] ) )
		);

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 'Bozza ritoccata dalla schermata', get_the_title( $id ) );
		$this->assertSame( $this->voci['tipo'], (int) DatiAtto::tipo( $id )->term_id, 'Anche i dati del riquadro si salvano.' );

		// L'atto nuovo della schermata nasce come bozza automatica, poi si salva.
		$automatica = wp_insert_post(
			array(
				'post_type'   => TIPO,
				'post_status' => 'auto-draft',
				'post_title'  => 'Bozza automatica',
			)
		);

		$this->invia( $automatica, array( 'post_status' => 'draft' ) );

		$this->assertSame( 'draft', get_post_status( $automatica ) );
		$this->assertSame( array(), array_diff( $this->stati_scritti( $id ), array( 'draft' ) ), 'Ogni scrittura della bozza porta lo stato di bozza.' );
		$this->assertSame( array(), Rifiuti::preleva_per_utente( $id ), 'Nessun rifiuto.' );
	}

	/**
	 * A-86: la bozza passa in verifica solo con tutti i dati, e il motivo nomina quello che manca.
	 */
	public function test_a86_passaggio_con_un_dato_mancante_per_volta(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$togli = array(
			'albo_manca_oggetto'              => function ( int $id ): array {
				unset( $id );
				return array( 'post_title' => '   ' );
			},
			'albo_manca_tipo'                 => function ( int $id ): array {
				wp_set_object_terms( $id, array(), TASSONOMIA_TIPO_ATTO );
				return array();
			},
			'albo_manca_durata'               => function ( int $id ): array {
				wp_set_object_terms( $id, array( $this->voci['senza'] ), TASSONOMIA_TIPO_ATTO );
				return array();
			},
			'albo_manca_organo'               => function ( int $id ): array {
				wp_set_object_terms( $id, array(), TASSONOMIA_ORGANO );
				return array();
			},
			'albo_manca_data_adozione'        => function ( int $id ): array {
				delete_post_meta( $id, META_DATA_ADOZIONE );
				return array();
			},
			'albo_manca_documento_principale' => function ( int $id ): array {
				$this->assertTrue( DocumentiAtto::togli( $id, (int) DocumentiAtto::principale( $id ) ) );
				return array();
			},
		);

		foreach ( $togli as $codice => $togli_dato ) {
			// Da codice.
			$id     = $this->atto_completo();
			$campi  = $togli_dato( $id );
			$prima  = count( $this->segnalazioni );
			$titolo = isset( $campi['post_title'] ) ? $campi['post_title'] : get_the_title( $id );

			wp_set_current_user( $this->redattore );

			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'pending',
					'post_title'  => $titolo,
				)
			);

			clean_post_cache( $id );

			$this->assertSame( 'draft', get_post_status( $id ), 'Da codice, resta in bozza senza: ' . $codice );
			$this->assertNotContains( 'pending', $this->stati_scritti( $id ), 'Nessuna scrittura in verifica senza: ' . $codice );
			$this->assertCount( $prima + 1, $this->segnalazioni, 'Una segnalazione a chi programma.' );

			$atteso = 'albo_manca_oggetto' === $codice ? 'Manca l\'oggetto' : DatiAtto::mancanti( $id )[ $codice ];

			$this->assertStringContainsString( esc_html( $atteso ), end( $this->segnalazioni ), 'Il motivo nomina il dato: ' . $codice );

			// Dalla schermata.
			wp_set_current_user( $this->amministratore );

			$id    = $this->atto_completo();
			$campi = $togli_dato( $id );

			wp_set_current_user( $this->redattore );

			$this->invia( $id, array( 'post_status' => 'pending' ) + $campi );

			$motivi = Rifiuti::preleva_per_utente( $id );

			$this->assertSame( 'draft', get_post_status( $id ), 'Dalla schermata, resta in bozza senza: ' . $codice );
			$this->assertNotContains( 'pending', $this->stati_scritti( $id ) );
			$this->assertSame( array( $codice ), array_keys( $motivi ), 'Un solo motivo, quello di: ' . $codice );

			wp_set_current_user( $this->amministratore );
		}

		// Un atto nuovo non nasce in verifica, nemmeno da chi potrebbe mandarcelo.
		wp_set_current_user( $this->amministratore );

		$nuovo = wp_insert_post(
			array(
				'post_type'    => TIPO,
				'post_status'  => 'pending',
				'post_title'   => 'Atto nuovo',
				'post_content' => 'Testo.',
			)
		);

		$this->assertSame( 'draft', get_post_status( $nuovo ), 'Un atto nuovo nasce in bozza.' );
		$this->assertStringContainsString( 'nasce sempre in bozza', (string) end( $this->segnalazioni ) );

		// Controllo positivo: completa, senza date di pubblicazione, passa da codice e dalla schermata.
		$id = $this->atto_completo();

		$this->passa( $id, 'pending', $this->redattore );
		$this->assertSame( 'pending', get_post_status( $id ), 'Controllo positivo da codice.' );

		$id = $this->atto_completo();

		wp_set_current_user( $this->redattore );
		$this->invia( $id, array( 'post_status' => 'pending' ) );

		$this->assertSame( 'pending', get_post_status( $id ), 'Controllo positivo dalla schermata.' );
		$this->assertSame( array(), Rifiuti::preleva_per_utente( $id ) );
	}

	/**
	 * A-87: il controllo guarda i dati scritti nello stesso invio.
	 */
	public function test_a87_bozza_completata_nello_stesso_invio(): void {
		$id = $this->bozza();

		$this->deposita_documento( $id );

		$this->assertSame(
			array( 'albo_manca_tipo', 'albo_manca_organo', 'albo_manca_data_adozione' ),
			array_keys( DatiAtto::mancanti( $id ) ),
			'Precondizione: mancano i tre dati del riquadro.'
		);

		wp_set_current_user( $this->redattore );

		$this->invia(
			$id,
			array( 'post_status' => 'pending' ) + $this->riquadro_dati(
				$id,
				array(
					SchedaAtto::CAMPO_TIPO   => (string) $this->voci['tipo'],
					SchedaAtto::CAMPO_ORGANO => (string) $this->voci['organo'],
					SchedaAtto::CAMPO_DATA   => '2026-03-10',
				)
			)
		);

		$this->assertSame( 'pending', get_post_status( $id ), 'Completata nello stesso invio, passa in verifica.' );
		$this->assertSame( '2026-03-10', DatiAtto::data_adozione( $id ) );
		$this->assertSame( array(), Rifiuti::preleva_per_utente( $id ) );
		$this->assertSame( array(), SchedaAtto::preleva_rifiuto( $id ) );

		// Un allegato tolto nello stesso invio si toglie mentre l'atto e' ancora in bozza.
		wp_set_current_user( $this->amministratore );

		$id        = $this->atto_completo();
		$ulteriore = $this->deposita_documento( $id, DocumentiAtto::ULTERIORE, 'da-togliere.pdf' );

		wp_set_current_user( $this->redattore );

		$this->invia(
			$id,
			array(
				'post_status'                  => 'pending',
				SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $id ) ),
				SchedaDocumenti::CAMPO_TOGLI   => array( (string) $ulteriore ),
			)
		);

		clean_post_cache( $ulteriore );

		$this->assertSame( 'pending', get_post_status( $id ) );
		$this->assertSame( array(), DocumentiAtto::ulteriori( $id ), 'L\'allegato e\' stato tolto.' );
		$this->assertNull( get_post( $ulteriore ) );
		$this->assertSame( array(), SchedaDocumenti::preleva_rifiuto( $id ), 'Nessun rifiuto dal riquadro dei documenti.' );

		// Un dato svuotato nello stesso invio: resta in bozza, e il dato risulta svuotato.
		wp_set_current_user( $this->amministratore );

		$id = $this->atto_completo();

		wp_set_current_user( $this->redattore );

		$this->invia( $id, array( 'post_status' => 'pending' ) + $this->riquadro_dati( $id, array( SchedaAtto::CAMPO_DATA => '' ) ) );

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertNull( DatiAtto::data_adozione( $id ), 'La data e\' stata svuotata.' );
		$this->assertSame( array( 'albo_manca_data_adozione' ), array_keys( Rifiuti::preleva_per_utente( $id ) ) );
		$this->assertNotContains( 'pending', $this->stati_scritti( $id ) );
	}

	/**
	 * A-88: manda in verifica solo chi puo' modificare quell'atto.
	 */
	public function test_a88_chi_manda_in_verifica(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = $this->atto_completo();

		$this->assertFalse( user_can( $this->altro_redattore, 'edit_post', $id ), 'Precondizione: non puo\' modificare l\'atto.' );

		$this->passa( $id, 'pending', $this->altro_redattore );

		$this->assertSame( 'draft', get_post_status( $id ), 'Chi non puo\' modificare l\'atto non lo manda in verifica.' );
		$this->assertStringContainsString( 'permesso', (string) end( $this->segnalazioni ) );
		$this->assertNotContains( 'pending', $this->stati_scritti( $id ) );

		$this->passa( $id, 'pending', $this->redattore );

		$this->assertSame( 'pending', get_post_status( $id ), 'Controllo positivo: l\'autore la manda in verifica.' );
	}

	/**
	 * A-89: un atto in verifica non si modifica, da nessuna via.
	 */
	public function test_a89_atto_in_verifica_non_si_modifica(): void {
		global $wpdb;

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id         = $this->atto_in_verifica();
		$principale = (int) DocumentiAtto::principale( $id );
		$ulteriore  = DocumentiAtto::ulteriori( $id )[0];
		$foto       = $this->fotografia( $id );

		foreach ( array( $this->redattore, $this->pubblicatore ) as $utente ) {
			wp_set_current_user( $utente );

			$tentativi = array(
				'oggetto dalla schermata'              => function () use ( $id ): void {
					$this->invia( $id, array( 'post_title' => 'Oggetto cambiato' ) );
				},
				'oggetto da codice'                    => function () use ( $id ): void {
					wp_update_post(
						array(
							'ID'         => $id,
							'post_title' => 'Oggetto cambiato',
						)
					);
				},
				'voci con la richiesta di salvataggio' => function () use ( $id ): void {
					wp_update_post(
						array(
							'ID'        => $id,
							'tax_input' => array(
								TASSONOMIA_TIPO_ATTO => array( $this->voci['altro'] ),
								TASSONOMIA_ORGANO    => array( $this->voci['altro2'] ),
							),
						)
					);
				},
				'dati dal riquadro'                    => function () use ( $id ): void {
					$this->invia(
						$id,
						$this->riquadro_dati(
							$id,
							array(
								SchedaAtto::CAMPO_TIPO   => (string) $this->voci['altro'],
								SchedaAtto::CAMPO_ORGANO => (string) $this->voci['altro2'],
								SchedaAtto::CAMPO_DATA   => '2026-01-01',
							)
						)
					);
				},
				'allegato tolto dal riquadro'          => function () use ( $id, $ulteriore ): void {
					$this->invia(
						$id,
						array(
							SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $id ) ),
							SchedaDocumenti::CAMPO_TOGLI   => array( (string) $ulteriore ),
						)
					);
				},
				'principale da codice'                 => function () use ( $id ): void {
					$this->assertWPError( DocumentiAtto::deposita( $id, $this->file_temporaneo(), DocumentiAtto::PRINCIPALE, 'percorso_locale' ) );
				},
				'ulteriore da codice'                  => function () use ( $id ): void {
					$this->assertWPError( DocumentiAtto::deposita( $id, $this->file_temporaneo(), DocumentiAtto::ULTERIORE, 'percorso_locale' ) );
				},
				'allegato tolto da codice'             => function () use ( $id, $ulteriore ): void {
					$this->assertWPError( DocumentiAtto::togli( $id, $ulteriore ) );
				},
				'principale tolto da codice'           => function () use ( $id, $principale ): void {
					$this->assertWPError( DocumentiAtto::togli( $id, $principale ) );
				},
				'risalvataggio senza cambiamenti'      => function () use ( $id ): void {
					wp_update_post( array( 'ID' => $id ) );
				},
			);

			foreach ( $tentativi as $tentativo => $esegui ) {
				$esegui();

				$this->assertSame( $foto, $this->fotografia( $id ), 'Niente cambia: ' . $tentativo . ', utente ' . $utente . '.' );
			}

			$motivi = Rifiuti::preleva_per_utente( $id );

			$this->assertArrayHasKey( 'albo_atto_in_verifica', $motivi, 'Il rifiuto della schermata e\' spiegato.' );
		}

		$this->assertSame( array(), array_diff( $this->stati_scritti( $id ), array( 'draft', 'pending' ) ) );

		/*
		 * Controllo positivo: le stesse modifiche riescono sull'atto riportato in
		 * bozza. Il ritorno in bozza qui si scrive nella banca dati, perche' quello
		 * vero, con la sua motivazione, arriva con la seconda parte del flusso.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: il rimando in bozza non esiste ancora.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) );
		clean_post_cache( $id );

		wp_set_current_user( $this->redattore );

		$this->invia( $id, array( 'post_title' => 'Oggetto cambiato' ) );
		$this->invia(
			$id,
			$this->riquadro_dati(
				$id,
				array(
					SchedaAtto::CAMPO_TIPO   => (string) $this->voci['altro'],
					SchedaAtto::CAMPO_ORGANO => (string) $this->voci['altro2'],
					SchedaAtto::CAMPO_DATA   => '2026-01-01',
				)
			)
		);
		$this->invia(
			$id,
			array(
				SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $id ) ),
				SchedaDocumenti::CAMPO_TOGLI   => array( (string) $ulteriore ),
			)
		);

		$nuovo = $this->deposita_documento( $id );

		$this->assertSame( 'Oggetto cambiato', get_the_title( $id ) );
		$this->assertSame( $this->voci['altro'], (int) DatiAtto::tipo( $id )->term_id );
		$this->assertSame( $this->voci['altro2'], (int) DatiAtto::organo( $id )->term_id );
		$this->assertSame( '2026-01-01', DatiAtto::data_adozione( $id ) );
		$this->assertSame( array(), DocumentiAtto::ulteriori( $id ) );
		$this->assertSame( $nuovo, DocumentiAtto::principale( $id ) );
	}

	/**
	 * A-89, seconda meta': il fermo annullato da un altro componente.
	 *
	 * Il fermo di ogni scrittura e' la prima difesa. Se un altro componente lo
	 * annulla, restano le due difese dietro: la riga dell'atto riscritta com'era
	 * e i riquadri che salvano solo in bozza. Qui si provano quelle due, con un
	 * aggancio agganciato dopo il nostro che fa riprendere la scrittura.
	 */
	public function test_a89_fermo_annullato_da_un_altro_componente(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id        = $this->atto_in_verifica();
		$ulteriore = DocumentiAtto::ulteriori( $id )[0];
		$foto      = $this->fotografia( $id );
		$annulla   = static function () {
			return false;
		};

		add_filter( 'wp_insert_post_empty_content', $annulla, PHP_INT_MAX );

		$scritti_prima = count( $this->scritti );

		wp_set_current_user( $this->redattore );

		try {
			wp_update_post(
				array(
					'ID'           => $id,
					'post_title'   => 'Oggetto cambiato',
					'post_content' => 'Testo cambiato',
					'post_status'  => 'draft',
				)
			);
			$this->invia(
				$id,
				$this->riquadro_dati(
					$id,
					array(
						SchedaAtto::CAMPO_TIPO   => (string) $this->voci['altro'],
						SchedaAtto::CAMPO_ORGANO => (string) $this->voci['altro2'],
						SchedaAtto::CAMPO_DATA   => '2026-01-01',
					)
				) + array( 'post_title' => 'Oggetto cambiato' )
			);
			$this->invia(
				$id,
				array(
					SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $id ) ),
					SchedaDocumenti::CAMPO_TOGLI   => array( (string) $ulteriore ),
				)
			);
		} finally {
			remove_filter( 'wp_insert_post_empty_content', $annulla, PHP_INT_MAX );
		}

		$stati = array_values( array_unique( array_column( array_slice( $this->scritti, $scritti_prima ), 'stato' ) ) );

		$this->assertNotSame( array(), $stati, 'Precondizione: con il fermo annullato le scritture arrivano davvero alla tabella.' );
		$this->assertSame( array( 'pending' ), $stati, 'Lo stato scritto resta in verifica.' );
		$this->assertSame( $foto, $this->fotografia( $id ), 'Niente cambia anche con il fermo annullato.' );
		$this->assertNotSame( array(), SchedaAtto::preleva_rifiuto( $id ), 'Il riquadro dei dati spiega il rifiuto.' );
	}

	/**
	 * A-90: in questa parte un atto in verifica non esce dalla verifica, e non si cestina.
	 */
	public function test_a90_nessuna_uscita_dalla_verifica(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = $this->atto_in_verifica();

		$this->scritti = array();

		foreach ( array( $this->pubblicatore, $this->amministratore ) as $utente ) {
			foreach ( array( 'draft', 'publish', 'future', 'private', 'trash' ) as $stato ) {
				$this->passa( $id, $stato, $utente );

				$this->assertSame( 'pending', get_post_status( $id ), 'Resta in verifica: verso ' . $stato . ', utente ' . $utente . '.' );
			}

			wp_set_current_user( $utente );

			$this->assertFalse( wp_trash_post( $id ), 'Non va nel cestino.' );
			$this->assertFalse( wp_delete_post( $id, true ), 'Non si cancella.' );

			clean_post_cache( $id );

			$this->assertSame( 'pending', get_post_status( $id ) );
			$this->assertSame( array(), get_post_meta( $id, '_wp_trash_meta_status', false ), 'Nessuna traccia di un cestino avviato.' );
		}

		$this->assertSame( array(), $this->stati_scritti( $id ), 'Nessuna scrittura della riga: ogni richiesta si e\' fermata prima.' );
		$this->assertNotEmpty( $this->segnalazioni, 'I rifiuti da codice sono segnalati.' );

		// Controllo positivo: una bozza si cestina, si ripristina in bozza e si cancella, senza nessun rifiuto.
		wp_set_current_user( $this->redattore );

		$bozza        = $this->bozza();
		$segnalazioni = count( $this->segnalazioni );

		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $bozza ) );
		$this->assertSame( 'trash', get_post_status( $bozza ) );
		$this->assertInstanceOf( \WP_Post::class, wp_untrash_post( $bozza ) );
		$this->assertSame( 'draft', get_post_status( $bozza ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_post( $bozza, true ) );
		$this->assertNull( get_post( $bozza ) );
		$this->assertCount( $segnalazioni, $this->segnalazioni, 'Nessun passaggio della bozza e\' stato rifiutato.' );
	}

	/**
	 * A-91: una bozza completa non si pubblica saltando la verifica.
	 */
	public function test_a91_saltare_la_verifica(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = $this->atto_completo();

		// Dalla schermata, con il pulsante di pubblicazione.
		$this->invia(
			$id,
			array(
				'post_status' => 'publish',
				'publish'     => 'Pubblica',
			)
		);

		$this->assertSame( 'draft', get_post_status( $id ), 'Dalla schermata resta in bozza.' );
		$this->assertArrayHasKey( 'albo_pubblicazione_non_aperta', Rifiuti::preleva_per_utente( $id ) );

		// Aggiornamento da codice.
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		clean_post_cache( $id );

		$this->assertSame( 'draft', get_post_status( $id ), 'Da aggiornamento resta in bozza.' );

		// Nemmeno verso privato, che non e' fra i passaggi di una bozza.
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'private',
			)
		);

		clean_post_cache( $id );

		$this->assertSame( 'draft', get_post_status( $id ), 'Verso privato resta in bozza.' );

		// Inserimento da codice di un atto nuovo che chiede di nascere pubblicato.
		$nuovo = wp_insert_post(
			array(
				'post_type'   => TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto nuovo',
			)
		);

		$this->assertSame( 'draft', get_post_status( $nuovo ), 'Da inserimento nasce in bozza.' );

		foreach ( $this->scritti as $scritto ) {
			$this->assertNotContains( $scritto['stato'], array( 'publish', 'future', 'pending', 'private' ), 'Nessuna scrittura pubblica, programma, rende privato o manda in verifica.' );
		}

		$this->assertSame( array(), DatiAtto::mancanti( $id ), 'La bozza era completa: il rifiuto viene dalla verifica saltata.' );
	}

	/**
	 * A-92: in verifica i due riquadri mostrano i dati senza campi da compilare.
	 */
	public function test_a92_riquadri_in_sola_lettura(): void {
		$id = $this->atto_completo();

		$this->deposita_documento( $id, DocumentiAtto::ULTERIORE, 'allegato.pdf' );

		wp_set_current_user( $this->redattore );

		$dati_bozza      = $this->stampa( array( SchedaAtto::class, 'mostra' ), $id );
		$documenti_bozza = $this->stampa( array( SchedaDocumenti::class, 'mostra' ), $id );

		$this->assertStringContainsString( '<select', $dati_bozza, 'Controllo positivo: in bozza i campi ci sono.' );
		$this->assertStringContainsString( SchedaAtto::CAMPO_GETTONE, $dati_bozza );
		$this->assertStringContainsString( 'type="file"', $documenti_bozza );
		$this->assertStringContainsString( SchedaDocumenti::CAMPO_GETTONE, $documenti_bozza );

		$this->passa( $id, 'pending', $this->redattore );

		$this->assertSame( 'pending', get_post_status( $id ) );

		$dati      = $this->stampa( array( SchedaAtto::class, 'mostra' ), $id );
		$documenti = $this->stampa( array( SchedaDocumenti::class, 'mostra' ), $id );

		foreach ( array( $dati, $documenti ) as $riquadro ) {
			$this->assertStringNotContainsString( '<input', $riquadro, 'Nessun campo da compilare.' );
			$this->assertStringNotContainsString( '<select', $riquadro );
			$this->assertStringNotContainsString( 'gettone', $riquadro, 'Nessun gettone.' );
			$this->assertStringContainsString( 'solo mentre l&#039;atto e&#039; in bozza', $riquadro, 'Una frase dice perche\'.' );
		}

		$this->assertStringContainsString( 'Determinazione', $dati, 'I dati si vedono.' );
		$this->assertStringContainsString( 'Giunta', $dati );
		$this->assertStringContainsString( '2026-03-10', $dati );
		$this->assertStringContainsString( 'atto.pdf', $documenti, 'I documenti si vedono.' );
		$this->assertStringContainsString( 'allegato.pdf', $documenti );
	}
}
