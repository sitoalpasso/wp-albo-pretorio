<?php
/**
 * Documenti dell'atto: righe A-77..A-84.
 *
 * I file sono veri e passano dal meccanismo comune, con la verifica della
 * protezione fatta rispondere da un server finto. Ogni esito si verifica sulla
 * banca dati **e sul disco**.
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
use AlboPretorioPa\SchedaDocumenti;
use AlboPretorioPa\TipoAtto;

use const AlboPretorioPa\META_ALLEGATI_ULTERIORI;
use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\META_DOCUMENTO_PRINCIPALE;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/*
 * Il riquadro si legge con il lettore di documenti di PHP, le cui proprieta'
 * hanno i nomi che hanno: non sono variabili di questo componente.
 */
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * Deposito, sostituzione, rimozione, lettura validata, permessi e riquadro.
 */
class DocumentiAttoTest extends \WP_UnitTestCase {

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
	 * Voci degli elenchi: un tipo con durata e un organo.
	 *
	 * @var array<string, int>
	 */
	private $voci = array();

	/**
	 * Tipo, permessi, dati, utenti, voci e server finto.
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

		wp_set_current_user( $this->amministratore );

		$tipo   = wp_insert_term( 'Determinazione', TASSONOMIA_TIPO_ATTO );
		$organo = wp_insert_term( 'Giunta', TASSONOMIA_ORGANO );

		$this->assertIsArray( $tipo );
		$this->assertIsArray( $organo );

		$this->voci = array(
			'tipo'   => (int) $tipo['term_id'],
			'organo' => (int) $organo['term_id'],
		);

		$this->assertTrue( Durate::configura( $this->voci['tipo'], 10, 'amministrazione', '' ) );

		$this->aggancia_server_finto();
	}

	/**
	 * Ripulisce modulo simulato, disco e ambiente.
	 */
	public function tear_down(): void {
		$_POST  = array();
		$_FILES = array();
		unset( $GLOBALS['current_screen'] );
		$this->sgancia_server_finto();
		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * Un utente con i soli permessi di chi redige.
	 *
	 * @return int
	 */
	private function utente_redattore(): int {
		$mappa = Permessi::mappa();

		$this->assertIsArray( $mappa, 'Precondizione: la corrispondenza dei permessi deve esserci.' );

		$id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$utente = new \WP_User( $id );

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			$utente->add_cap( $mappa[ $chiave ] );
		}

		$this->assertFalse( user_can( $id, $mappa['edit_others_posts'] ), 'Precondizione: non deve modificare gli atti degli altri.' );

		return $id;
	}

	/**
	 * Un atto in bozza del redattore con tutti i dati tranne i documenti.
	 *
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function atto( string $oggetto = 'Atto di prova' ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => TIPO,
				'post_status' => 'draft',
				'post_title'  => $oggetto,
				'post_author' => $this->redattore,
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		wp_set_object_terms( $id, array( $this->voci['tipo'] ), TASSONOMIA_TIPO_ATTO );
		wp_set_object_terms( $id, array( $this->voci['organo'] ), TASSONOMIA_ORGANO );
		update_post_meta( $id, META_DATA_ADOZIONE, '2026-03-10' );

		return $id;
	}

	/**
	 * Un atto con il principale e due ulteriori.
	 *
	 * @return array{atto: int, principale: int, ulteriori: array<int, int>}
	 */
	private function atto_con_documenti(): array {
		$atto = $this->atto();

		return array(
			'atto'       => $atto,
			'principale' => $this->deposita_documento( $atto, DocumentiAtto::PRINCIPALE, 'principale.pdf' ),
			'ulteriori'  => array(
				$this->deposita_documento( $atto, DocumentiAtto::ULTERIORE, 'primo-allegato.pdf' ),
				$this->deposita_documento( $atto, DocumentiAtto::ULTERIORE, 'secondo-allegato.pdf' ),
			),
		);
	}

	/**
	 * Tutto cio' che esiste, letto grezzo: atto, suoi dati, allegati e disco.
	 *
	 * @param int $atto_id Contenuto osservato.
	 * @return array<string, mixed>
	 */
	private function fotografia( int $atto_id ): array {
		global $wpdb;

		clean_post_cache( $atto_id );
		wp_cache_flush();

		$atto = get_post( $atto_id );

		return array(
			'stato'      => $atto->post_status,
			'principale' => get_post_meta( $atto_id, META_DOCUMENTO_PRINCIPALE, false ),
			'ulteriori'  => get_post_meta( $atto_id, META_ALLEGATI_ULTERIORI, false ),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si conta cio' che c'e' davvero nella banca dati.
			'allegati'   => $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID" ),
			'disco'      => $this->documenti_sul_disco(),
		);
	}

	/**
	 * Un invio vero della schermata dell'atto, con file.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $post    Campi oltre a quelli della schermata.
	 * @param array<string, mixed> $files   Voci di `$_FILES`.
	 */
	private function invia( int $atto_id, array $post, array $files = array() ): void {
		set_current_screen( 'post' );

		$_POST  = array_merge(
			array(
				'action'               => 'editpost',
				'post_ID'              => (string) $atto_id,
				'post_type'            => TIPO,
				'post_title'           => get_the_title( $atto_id ),
				'original_post_status' => 'draft',
				'_wpnonce'             => wp_create_nonce( 'update-post_' . $atto_id ),
			),
			$post
		);
		$_FILES = $files;

		try {
			edit_post();
		} finally {
			$_POST  = array();
			$_FILES = array();
		}
	}

	/**
	 * Il riquadro stampato per un atto, dall'utente corrente.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private function riquadro( int $atto_id ): string {
		ob_start();

		try {
			SchedaDocumenti::mostra( get_post( $atto_id ) );
		} finally {
			$stampato = (string) ob_get_clean();
		}

		return $stampato;
	}

	/**
	 * A-77: il documento principale nasce e si sostituisce, e il vecchio sparisce.
	 */
	public function test_a77_il_principale_nasce_e_si_sostituisce(): void {
		$atto = $this->atto();

		$this->assertSame( array( 'albo_manca_documento_principale' ), array_keys( DatiAtto::mancanti( $atto ) ), 'Prima del deposito manca solo il documento principale.' );

		$primo      = $this->deposita_documento( $atto, DocumentiAtto::PRINCIPALE, 'primo.pdf' );
		$file_primo = (string) get_attached_file( $primo );

		$this->assertSame( array(), DatiAtto::mancanti( $atto ), 'Dopo il deposito l\'atto non manca di niente.' );
		$this->assertSame( $primo, DocumentiAtto::principale( $atto ) );
		$this->assertSame( $atto, (int) get_post( $primo )->post_parent, 'Il principale e\' figlio dell\'atto.' );
		$this->assertTrue( conformita_core_allegato_protetto( $primo ), 'Il principale l\'ha depositato il meccanismo comune.' );
		$this->assertStringStartsWith( untrailingslashit( \Conformita_Core_Allegati::cartella() ), $file_primo, 'Il file sta nella cartella protetta.' );
		$this->assertFileExists( $file_primo );

		$impronta = conformita_core_impronta_allegato( $primo );

		$this->assertIsArray( $impronta, 'Il principale ha l\'impronta.' );
		$this->assertSame( hash_file( 'sha256', $file_primo ), $impronta['valore'] );
		$this->assertSame( array( $file_primo ), $this->documenti_sul_disco() );

		$secondo      = $this->deposita_documento( $atto, DocumentiAtto::PRINCIPALE, 'secondo.pdf' );
		$file_secondo = (string) get_attached_file( $secondo );

		clean_post_cache( $primo );

		$this->assertSame( $secondo, DocumentiAtto::principale( $atto ), 'Il principale e\' il nuovo.' );
		$this->assertNull( get_post( $primo ), 'Il vecchio principale non esiste piu\' nella banca dati.' );
		$this->assertFileDoesNotExist( $file_primo, 'Il vecchio principale non esiste piu\' sul disco.' );
		$this->assertSame( array( $file_secondo ), $this->documenti_sul_disco(), 'Sul disco resta solo il nuovo.' );
		$this->assertSame( array(), DocumentiAtto::ulteriori( $atto ), 'La sostituzione non tocca gli ulteriori.' );
		$this->assertSame( array(), DatiAtto::mancanti( $atto ) );
	}

	/**
	 * A-78: gli ulteriori nell'ordine del deposito, e la rimozione.
	 */
	public function test_a78_gli_allegati_ulteriori(): void {
		$atto       = $this->atto();
		$principale = $this->deposita_documento( $atto );

		$this->assertSame( array(), DocumentiAtto::ulteriori( $atto ) );
		$this->assertSame( array(), DatiAtto::mancanti( $atto ), 'Senza ulteriori l\'atto non manca di niente.' );

		$primo   = $this->deposita_documento( $atto, DocumentiAtto::ULTERIORE, 'primo.pdf' );
		$secondo = $this->deposita_documento( $atto, DocumentiAtto::ULTERIORE, 'secondo.pdf' );
		$terzo   = $this->deposita_documento( $atto, DocumentiAtto::ULTERIORE, 'terzo.pdf' );

		$this->assertSame( array( $primo, $secondo, $terzo ), DocumentiAtto::ulteriori( $atto ), 'Nell\'ordine del deposito.' );

		$file_secondo = (string) get_attached_file( $secondo );

		$this->assertFileExists( $file_secondo );
		$this->assertTrue( DocumentiAtto::togli( $atto, $secondo ) );

		clean_post_cache( $secondo );

		$this->assertSame( array( $primo, $terzo ), DocumentiAtto::ulteriori( $atto ), 'Gli altri due restano nello stesso ordine.' );
		$this->assertNull( get_post( $secondo ) );
		$this->assertFileDoesNotExist( $file_secondo );

		$file_principale = (string) get_attached_file( $principale );

		$this->assertTrue( DocumentiAtto::togli( $atto, $principale ) );

		clean_post_cache( $principale );

		$this->assertSame( array( 'albo_manca_documento_principale' ), array_keys( DatiAtto::mancanti( $atto ) ), 'Tolto il principale, manca di nuovo.' );
		$this->assertSame( array( $primo, $terzo ), DocumentiAtto::ulteriori( $atto ), 'Gli ulteriori restano.' );
		$this->assertNull( get_post( $principale ) );
		$this->assertFileDoesNotExist( $file_principale );
		$this->assertCount( 2, $this->documenti_sul_disco() );

		$rifiuto = DocumentiAtto::togli( $atto, $principale );

		$this->assertWPError( $rifiuto, 'Un documento gia\' tolto non si toglie due volte.' );
		$this->assertSame( 'albo_documento_non_dell_atto', $rifiuto->get_error_code() );
	}

	/**
	 * A-79: depositi rifiutati, una condizione per volta.
	 */
	public function test_a79_depositi_rifiutati(): void {
		global $wpdb;

		// Controllo positivo sullo stesso assetto: una bozza riceve il documento.
		$this->deposita_documento( $this->atto( 'Controllo positivo' ) );

		$articolo    = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$in_verifica = $this->atto( 'In verifica' );
		$pubblicato  = $this->atto( 'Pubblicato di lato' );
		$bozza       = $this->atto( 'Bozza' );

		wp_update_post(
			array(
				'ID'          => $in_verifica,
				'post_status' => 'pending',
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato si scrive dritto nella banca dati, scavalcando lo sbarramento.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $pubblicato ) );
		clean_post_cache( $pubblicato );

		$this->assertSame( 'pending', get_post_status( $in_verifica ), 'Precondizione: l\'atto e\' in verifica.' );
		$this->assertSame( 'publish', get_post_status( $pubblicato ), 'Precondizione: l\'atto risulta pubblicato.' );

		$casi = array(
			'non un atto'          => array( $articolo, DocumentiAtto::PRINCIPALE, 'atto.pdf', 'albo_non_un_atto' ),
			'in verifica'          => array( $in_verifica, DocumentiAtto::PRINCIPALE, 'atto.pdf', 'albo_documenti_non_in_bozza' ),
			'pubblicato di lato'   => array( $pubblicato, DocumentiAtto::ULTERIORE, 'atto.pdf', 'albo_documenti_non_in_bozza' ),
			'ruolo sconosciuto'    => array( $bozza, 'allegato', 'atto.pdf', 'albo_ruolo_documento_sconosciuto' ),
			'tipo di file escluso' => array( $bozza, DocumentiAtto::PRINCIPALE, 'programma.exe', 'conformita_core_tipo_file_non_ammesso' ),
		);

		foreach ( $casi as $caso => list( $contenuto, $ruolo, $nome, $codice ) ) {
			$prima = $this->fotografia( $contenuto );
			$esito = DocumentiAtto::deposita( $contenuto, $this->file_temporaneo( $nome ), $ruolo, 'percorso_locale' );

			$this->assertWPError( $esito, 'Rifiutato: ' . $caso . '.' );
			$this->assertSame( $codice, $esito->get_error_code(), 'Il motivo di: ' . $caso . '.' );
			$this->assertNotSame( '', $esito->get_error_message() );
			$this->assertSame( $prima, $this->fotografia( $contenuto ), 'Niente di nuovo, ne\' nella banca dati ne\' sul disco: ' . $caso . '.' );
		}

		$this->server_che_serve_l_esca();

		$prima = $this->fotografia( $bozza );
		$esito = DocumentiAtto::deposita( $bozza, $this->file_temporaneo(), DocumentiAtto::PRINCIPALE, 'percorso_locale' );

		$this->assertWPError( $esito, 'Rifiutato con la protezione non verificata.' );
		$this->assertSame( 'conformita_core_protezione_non_verificata', $esito->get_error_code() );
		$this->assertSame( $prima, $this->fotografia( $bozza ) );
	}

	/**
	 * A-80 [attacco]: invii della schermata senza gettone, con quello di un altro atto, senza permesso, e un percorso del server.
	 */
	public function test_a80_invii_della_schermata_rifiutati(): void {
		$documenti = $this->atto_con_documenti();
		$atto      = $documenti['atto'];
		$altro     = $this->atto( 'Un altro atto' );
		$bersaglio = $documenti['ulteriori'][0];

		$richiesta = array( SchedaDocumenti::CAMPO_TOGLI => array( (string) $bersaglio ) );
		$file      = array( SchedaDocumenti::CAMPO_ULTERIORE => $this->file_temporaneo( 'nuovo.pdf' ) );

		wp_set_current_user( $this->redattore );

		$prima = $this->fotografia( $atto );

		// Senza gettone.
		$this->invia( $atto, $richiesta, $file );
		$this->assertSame( $prima, $this->fotografia( $atto ), 'Senza gettone non succede niente.' );
		$this->assertSame( array(), SchedaDocumenti::preleva_rifiuto( $atto ) );

		// Con il gettone di un altro atto.
		$this->invia( $atto, $richiesta + array( SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $altro ) ) ), $file );
		$this->assertSame( $prima, $this->fotografia( $atto ), 'Il gettone di un altro atto non vale.' );
		$this->assertCount( 1, SchedaDocumenti::preleva_rifiuto( $atto ), 'Il rifiuto e\' spiegato.' );

		// Da chi redige ma non puo' modificare quell'atto: la schermata lo fermerebbe prima, qui si salva da codice.
		wp_set_current_user( $this->altro_redattore );

		$this->assertFalse( current_user_can( 'edit_post', $atto ), 'Precondizione: non puo\' modificare l\'atto.' );

		$_POST  = $richiesta + array( SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $atto ) ) );
		$_FILES = $file;

		try {
			wp_update_post(
				array(
					'ID'         => $atto,
					'post_title' => get_the_title( $atto ),
				)
			);
		} finally {
			$_POST  = array();
			$_FILES = array();
		}

		$this->assertSame( $prima, $this->fotografia( $atto ), 'Senza il permesso sull\'atto non succede niente.' );
		$this->assertCount( 1, SchedaDocumenti::preleva_rifiuto( $atto ) );

		// Da chi puo', un percorso del server al posto di un file caricato.
		wp_set_current_user( $this->redattore );

		$segreto = $this->file_temporaneo( 'segreto.pdf' );

		$this->invia(
			$atto,
			array( SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $atto ) ) ),
			array( SchedaDocumenti::CAMPO_PRINCIPALE => $segreto )
		);

		$motivi = SchedaDocumenti::preleva_rifiuto( $atto );

		$this->assertSame( $prima, $this->fotografia( $atto ), 'Il percorso del server non si deposita.' );
		$this->assertCount( 1, $motivi );
		$this->assertStringContainsString( 'caricamento HTTP', $motivi[0], 'Il motivo dice che il file non e\' stato caricato.' );
		$this->assertFileExists( $segreto['tmp_name'], 'Il file indicato resta dov\'era.' );
		$this->assertSame( $documenti['principale'], DocumentiAtto::principale( $atto ) );

		// Controllo positivo: la stessa richiesta, con il gettone giusto e da chi puo'.
		$this->invia( $atto, $richiesta + array( SchedaDocumenti::CAMPO_GETTONE => wp_create_nonce( SchedaDocumenti::azione( $atto ) ) ) );

		clean_post_cache( $bersaglio );

		$this->assertSame( array( $documenti['ulteriori'][1] ), DocumentiAtto::ulteriori( $atto ), 'Con il gettone giusto l\'ulteriore si toglie.' );
		$this->assertNull( get_post( $bersaglio ) );
		$this->assertSame( array(), SchedaDocumenti::preleva_rifiuto( $atto ) );
	}

	/**
	 * A-81: la lettura non si fida dei dati memorizzati.
	 */
	public function test_a81_la_lettura_valida_il_dato_letto(): void {
		global $wpdb;

		$dichiarati = get_registered_meta_keys( 'post', TIPO );

		foreach ( array( META_DOCUMENTO_PRINCIPALE, META_ALLEGATI_ULTERIORI ) as $chiave ) {
			$this->assertArrayHasKey( $chiave, $dichiarati, 'Dichiarato: ' . $chiave );
			$this->assertFalse( $dichiarati[ $chiave ]['show_in_rest'], 'Esposizione per programmi spenta: ' . $chiave );
			$this->assertTrue( is_protected_meta( $chiave, 'post' ), 'Protetto, cioe\' non scrivibile come campo personalizzato: ' . $chiave );
		}

		$primo   = $this->atto_con_documenti();
		$secondo = $this->atto_con_documenti();
		$atto    = $primo['atto'];

		$libreria = self::factory()->attachment->create_object(
			'libreria.pdf',
			$atto,
			array( 'post_mime_type' => 'application/pdf' )
		);

		$cestinato = $this->deposita_documento( $atto, DocumentiAtto::ULTERIORE, 'cestinato.pdf' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: il cestino si scrive di lato.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $cestinato ) );
		clean_post_cache( $cestinato );

		$sbagliati = array(
			'di un altro atto' => $secondo['principale'],
			'della libreria'   => $libreria,
			'nel cestino'      => $cestinato,
			'che non esiste'   => 999999,
			'non un numero'    => 'uno',
		);

		foreach ( $sbagliati as $caso => $valore ) {
			update_post_meta( $atto, META_DOCUMENTO_PRINCIPALE, $valore );

			$this->assertNull( DocumentiAtto::principale( $atto ), 'Principale ' . $caso . ': si legge come assente.' );
			$this->assertArrayHasKey( 'albo_manca_documento_principale', DatiAtto::mancanti( $atto ), 'Principale ' . $caso . '.' );
		}

		delete_post_meta( $atto, META_DOCUMENTO_PRINCIPALE );
		add_post_meta( $atto, META_DOCUMENTO_PRINCIPALE, $primo['principale'] );
		add_post_meta( $atto, META_DOCUMENTO_PRINCIPALE, $primo['principale'] );

		$this->assertNull( DocumentiAtto::principale( $atto ), 'Due righe valgono come nessuna.' );

		delete_post_meta( $atto, META_DOCUMENTO_PRINCIPALE );
		add_post_meta( $atto, META_DOCUMENTO_PRINCIPALE, (string) $primo['principale'] );

		$this->assertSame( $primo['principale'], DocumentiAtto::principale( $atto ), 'Controllo positivo: il principale valido scritto di lato si legge.' );

		list( $uno, $due ) = $primo['ulteriori'];

		update_post_meta(
			$atto,
			META_ALLEGATI_ULTERIORI,
			array( $uno, 'x', $secondo['ulteriori'][0], 999999, $libreria, $cestinato, $due, $uno, $primo['principale'], -3 )
		);

		$this->assertSame( array( $uno, $due ), DocumentiAtto::ulteriori( $atto ), 'Degli ulteriori si leggono solo i validi, nell\'ordine.' );

		update_post_meta( $atto, META_ALLEGATI_ULTERIORI, array( (string) $due, (string) $uno ) );

		$this->assertSame( array( $due, $uno ), DocumentiAtto::ulteriori( $atto ), 'Controllo positivo: gli ulteriori validi scritti di lato si leggono.' );

		update_post_meta( $atto, META_ALLEGATI_ULTERIORI, (string) $uno );

		$this->assertSame( array(), DocumentiAtto::ulteriori( $atto ), 'Un elenco che non e\' un elenco non si legge.' );
	}

	/**
	 * A-82: i documenti si governano con i permessi dell'atto, e non stanno nella libreria dei media.
	 */
	public function test_a82_permessi_dell_atto_e_libreria_dei_media(): void {
		$documenti  = $this->atto_con_documenti();
		$principale = $documenti['principale'];
		$redattrice = self::factory()->user->create( array( 'role' => 'editor' ) );
		$qualunque  = self::factory()->attachment->create_object(
			'qualunque.pdf',
			0,
			array( 'post_mime_type' => 'application/pdf' )
		);

		$this->assertTrue( user_can( $redattrice, 'edit_others_posts' ), 'Precondizione: modifica gli articoli di tutti.' );
		$this->assertTrue( user_can( $redattrice, 'delete_others_posts' ), 'Precondizione: cancella gli articoli di tutti.' );
		$this->assertTrue( user_can( $redattrice, 'edit_post', $qualunque ), 'Precondizione: governa la libreria dei media.' );
		$this->assertFalse( user_can( $redattrice, 'edit_post', $documenti['atto'] ), 'Precondizione: non ha i permessi dell\'albo.' );

		$this->assertFalse( user_can( $redattrice, 'edit_post', $principale ), 'Senza i permessi dell\'albo non si modifica il documento.' );
		$this->assertFalse( user_can( $redattrice, 'delete_post', $principale ), 'Senza i permessi dell\'albo non si cancella il documento.' );
		$this->assertFalse( user_can( $redattrice, 'read_post', $principale ), 'Ne\' si legge, finche\' l\'atto e\' in bozza.' );

		$this->assertTrue( user_can( $this->redattore, 'edit_post', $principale ), 'Controllo positivo: chi modifica l\'atto modifica il suo documento.' );
		$this->assertFalse( user_can( $this->redattore, 'delete_post', $principale ), 'Cancellare per questa via non e\' concesso: si toglie dal riquadro.' );
		$this->assertFalse( user_can( $this->amministratore, 'delete_post', $principale ), 'Nemmeno all\'amministratore.' );
		$this->assertTrue( user_can( $redattrice, 'delete_post', $qualunque ), 'Controllo positivo: un allegato qualunque si governa come prima.' );

		// La libreria a griglia.
		$argomenti = apply_filters(
			'ajax_query_attachments_args',
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$griglia   = array_map( 'intval', ( new \WP_Query( $argomenti ) )->posts );

		$this->assertContains( $qualunque, $griglia, 'Controllo positivo: un allegato qualunque compare nella libreria.' );

		foreach ( array_merge( array( $principale ), $documenti['ulteriori'] ) as $documento ) {
			$this->assertNotContains( $documento, $griglia, 'Il documento non compare nella libreria a griglia.' );
		}

		// La libreria a elenco: l'interrogazione principale della schermata dei media.
		set_current_screen( 'upload' );

		$originale               = $GLOBALS['wp_the_query'];
		$elenco                  = new \WP_Query();
		$GLOBALS['wp_the_query'] = $elenco;

		try {
			$elenco->query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
		} finally {
			$GLOBALS['wp_the_query'] = $originale;
		}

		$elenco = array_map( 'intval', $elenco->posts );

		$this->assertContains( $qualunque, $elenco, 'Controllo positivo: un allegato qualunque compare nell\'elenco.' );
		$this->assertNotContains( $principale, $elenco, 'Il documento non compare nella libreria a elenco.' );

		/*
		 * Un'interrogazione qualunque degli allegati non cambia. Con
		 * `WP_Query` e non con `get_posts()`, che spegne i filtri e renderebbe
		 * vuoto questo controllo.
		 */
		$tutti = array_map(
			'intval',
			( new \WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			) )->posts
		);

		$this->assertContains( $principale, $tutti, 'Fuori dalla libreria l\'esclusione non vale.' );
	}

	/**
	 * A-83: cancellare una bozza porta via i suoi documenti, e solo i suoi.
	 */
	public function test_a83_la_bozza_cancellata_porta_via_i_documenti(): void {
		$primo   = $this->atto_con_documenti();
		$terzo   = $this->atto_con_documenti();
		$secondo = $this->atto_con_documenti();

		$file = static function ( array $documenti ): array {
			return array_map(
				static function ( int $id ): string {
					return (string) get_attached_file( $id );
				},
				array_merge( array( $documenti['principale'] ), $documenti['ulteriori'] )
			);
		};

		$file_primo   = $file( $primo );
		$file_terzo   = $file( $terzo );
		$file_secondo = $file( $secondo );

		$this->assertCount( 9, $this->documenti_sul_disco(), 'Precondizione: nove documenti sul disco.' );

		wp_delete_post( $primo['atto'], true );

		foreach ( array_merge( array( $primo['principale'] ), $primo['ulteriori'] ) as $documento ) {
			clean_post_cache( $documento );
			$this->assertNull( get_post( $documento ), 'Il documento della bozza cancellata non esiste piu\'.' );
		}

		foreach ( $file_primo as $percorso ) {
			$this->assertFileDoesNotExist( $percorso );
		}

		// La stessa cosa passando dal cestino: vale lo stato di prima.
		wp_trash_post( $secondo['atto'] );
		$this->assertSame( 'trash', get_post_status( $secondo['atto'] ) );
		wp_delete_post( $secondo['atto'], true );

		foreach ( $file_secondo as $percorso ) {
			$this->assertFileDoesNotExist( $percorso );
		}

		foreach ( $file_terzo as $percorso ) {
			$this->assertFileExists( $percorso, 'I file dell\'altro atto restano.' );
		}

		$this->assertSame( $terzo['principale'], DocumentiAtto::principale( $terzo['atto'] ) );
		$this->assertSame( $terzo['ulteriori'], DocumentiAtto::ulteriori( $terzo['atto'] ) );

		$restanti = $file_terzo;
		sort( $restanti );

		$this->assertSame( $restanti, $this->documenti_sul_disco(), 'Sul disco restano solo i documenti dell\'altro atto.' );
	}

	/**
	 * A-84: il riquadro mostra nome, dimensione e indirizzo di consegna, mai il percorso.
	 */
	public function test_a84_il_riquadro_dei_documenti(): void {
		$documenti = $this->atto_con_documenti();
		$atto      = $documenti['atto'];
		$tutti     = array_merge( array( $documenti['principale'] ), $documenti['ulteriori'] );

		wp_set_current_user( $this->redattore );

		$html = $this->riquadro( $atto );

		$this->assertNotSame( '', $html );

		foreach ( array( 'principale.pdf', 'primo-allegato.pdf', 'secondo-allegato.pdf' ) as $nome ) {
			$this->assertStringContainsString( $nome, $html, 'Il riquadro nomina ' . $nome . '.' );
		}

		$this->assertLessThan( strpos( $html, 'secondo-allegato.pdf' ), strpos( $html, 'primo-allegato.pdf' ), 'Gli ulteriori nell\'ordine.' );

		$documento = new \DOMDocument();
		libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		$collegamenti = array();

		foreach ( $documento->getElementsByTagName( 'a' ) as $collegamento ) {
			$collegamenti[] = $collegamento->getAttribute( 'href' );
		}

		$attesi = array();

		foreach ( $tutti as $id ) {
			$impronta = conformita_core_impronta_allegato( $id );

			$this->assertIsArray( $impronta );
			$this->assertStringContainsString( (string) size_format( (int) $impronta['dimensione'] ), $html, 'Il riquadro mostra la dimensione.' );

			// L'indirizzo esce gia' pronto per l'HTML, con le e commerciali codificate: il documento letto le ha decodificate.
			$attesi[] = html_entity_decode( (string) conformita_core_indirizzo_consegna_amministrativa( $id ), ENT_QUOTES );

			$relativo = (string) get_post_meta( $id, '_wp_attached_file', true );

			$this->assertNotSame( '', $relativo );
			$this->assertStringNotContainsString( (string) get_attached_file( $id ), $html, 'Il percorso del file non compare.' );
			$this->assertStringNotContainsString( $relativo, $html, 'Nemmeno il percorso relativo.' );
		}

		$this->assertSame( $attesi, $collegamenti, 'I collegamenti sono gli indirizzi di consegna amministrativi, nell\'ordine.' );
		$this->assertStringNotContainsString( \Conformita_Core_Allegati::CARTELLA, $html, 'La cartella protetta non compare.' );
		$this->assertStringNotContainsString( (string) wp_upload_dir()['baseurl'], $html, 'Nessun indirizzo diretto dei caricamenti.' );

		ob_start();
		do_action( 'post_edit_form_tag', get_post( $atto ) );
		$modulo = (string) ob_get_clean();

		$this->assertStringContainsString( 'enctype="multipart/form-data"', $modulo, 'Il modulo della schermata dichiara l\'invio di file.' );

		wp_set_current_user( $this->altro_redattore );

		$this->assertSame( '', $this->riquadro( $atto ), 'Senza permesso il riquadro non stampa niente.' );
	}
}
