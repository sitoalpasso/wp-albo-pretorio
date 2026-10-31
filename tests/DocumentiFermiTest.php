<?php
/**
 * I documenti di un atto fermo restano quelli pubblicati: righe A-317..A-323 (ALBO-09, ALBO-10, ALBO-12).
 *
 * Un atto pubblicato con il passaggio vero, con principale e allegato, e il
 * principale sostituito una volta con la versione oscurata, cosi' che ci sia
 * anche una versione sostituita. Da amministratore si tenta ogni via, una
 * alla volta, e dopo ogni tentativo si rilegge dalla banca dati la riga e i
 * metadati del documento, si controlla l'impronta del file sul disco e, per i
 * documenti indicati, che il pubblico scarichi ancora lo stesso file. Ogni
 * rifiuto ha accanto il suo controllo positivo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\DocumentiFermi;
use AlboPretorioPa\Passaggi;

use const AlboPretorioPa\META_SOSTITUITO_DA;
use const AlboPretorioPa\TIPO;

/**
 * Cancellazione, cestino, salvataggio, libreria dei media e metadati dei documenti di un atto fermo.
 */
class DocumentiFermiTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Le consegne emesse dal meccanismo comune.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $consegne = array();

	/**
	 * Il guasto attivo sulle istruzioni, o nullo.
	 *
	 * @var callable|null
	 */
	private $guasto = null;

	/**
	 * Assetto comune, pagina dell'albo, consegna osservata e aggancio dei guasti.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->set_permalink_structure( '' );

		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Albo pretorio',
				'post_content' => '[albo_pretorio]',
			)
		);

		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ) {
				$this->consegne[] = $risposta;
			}
		);

		add_filter( 'query', array( $this, 'guasta' ) );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'query', array( $this, 'guasta' ) );

		$this->guasto = null;
		$wpdb->suppress_errors( false );

		\Conformita_Core_Consegna::azzera_emettitore();
		$_REQUEST = array();
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Sostituisce con un'istruzione che fallisce quelle che il guasto attivo riconosce.
	 *
	 * @param string $istruzione Istruzione.
	 * @return string
	 */
	public function guasta( $istruzione ) {
		if ( null !== $this->guasto && ( $this->guasto )( (string) $istruzione ) ) {
			return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
		}

		return $istruzione;
	}

	/**
	 * Attiva un guasto sulle istruzioni che contengono il pezzo indicato.
	 *
	 * @param string $pezzo Pezzo dell'istruzione.
	 */
	private function guasta_istruzioni( string $pezzo ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );

		$this->guasto = static function ( string $istruzione ) use ( $pezzo ): bool {
			return false !== strpos( $istruzione, $pezzo );
		};
	}

	/**
	 * Toglie il guasto.
	 */
	private function ripara(): void {
		global $wpdb;

		$this->guasto = null;
		$wpdb->suppress_errors( false );
	}

	/**
	 * Un PDF minimo, diverso a ogni chiamata.
	 *
	 * @param string $segno Testo che lo distingue.
	 * @return string
	 */
	private function pdf( string $segno ): string {
		return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n% " . $segno . ' ' . wp_generate_password( 12, false ) . "\n";
	}

	/**
	 * Un atto pubblicato con principale e un allegato, e il principale sostituito una volta.
	 *
	 * @return array{atto: int, principale: int, allegato: int, sostituito: int}
	 */
	private function pubblicato_e_oscurato(): array {
		$this->fissa_orologi( '2041-10-01 09:30:00' );

		$id = $this->atto_completo();
		$this->assertIsInt( DocumentiAtto::deposita( $id, $this->file_temporaneo( 'allegato.pdf', $this->pdf( 'allegato' ) ), DocumentiAtto::ULTERIORE, 'percorso_locale' ) );

		$this->come(
			$this->redattore,
			static function () use ( $id ) {
				return wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'pending',
					)
				);
			}
		);
		clean_post_cache( $id );

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$uscente = (int) DocumentiAtto::principale( $id );
		$file    = $this->file_temporaneo( 'oscurato.pdf', $this->pdf( 'oscurato' ) );
		$esito   = $this->come(
			$this->pubblicatore,
			static function () use ( $id, $uscente, $file ) {
				return albo_pretorio_oscura(
					$id,
					$uscente,
					$file,
					'percorso_locale',
					array(
						'conferma' => true,
						'causa'    => Passaggi::CAUSA_DATI_ECCEDENTI,
						'motivo'   => 'Il documento riporta l\'indirizzo di casa del destinatario.',
					)
				);
			}
		);
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : 'Precondizione: la sostituzione.' );

		clean_post_cache( $id );

		$documenti = array(
			'atto'       => $id,
			'principale' => (int) DocumentiAtto::principale( $id ),
			'allegato'   => DocumentiAtto::ulteriori( $id )[0],
			'sostituito' => $uscente,
		);

		$this->assertSame( (int) $esito['documento'], $documenti['principale'], 'Precondizione: il principale e\' la versione oscurata.' );
		$this->assertSame( $documenti['principale'], DocumentiAtto::sostituito_da( $uscente ), 'Precondizione: il segno della sostituzione.' );
		wp_set_current_user( $this->amministratore );

		return $documenti;
	}

	/**
	 * Tutto cio' che descrive il documento, letto dalla banca dati, piu' l'impronta del file sul disco.
	 *
	 * @param int $documento Documento.
	 * @return array<string, mixed>
	 */
	private function fotografia_documento( int $documento ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la riga com'e' nella banca dati.
		$riga = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE ID = %d', $wpdb->posts, $documento ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- come sopra.
		$dati = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_id, meta_key, meta_value FROM %i WHERE post_id = %d ORDER BY meta_id', $wpdb->postmeta, $documento ), ARRAY_A );

		clean_post_cache( $documento );
		$file = get_attached_file( $documento );

		return array(
			'riga'     => $riga,
			'dati'     => $dati,
			'impronta' => is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : null,
		);
	}

	/**
	 * Le fotografie di tutti i documenti dell'atto.
	 *
	 * @param array<string, int> $documenti Documenti.
	 * @return array<string, array<string, mixed>>
	 */
	private function fotografie( array $documenti ): array {
		$fotografie = array();

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$fotografie[ $ruolo ] = $this->fotografia_documento( $documenti[ $ruolo ] );
			$this->assertNotNull( $fotografie[ $ruolo ]['riga'], 'Precondizione: il documento esiste.' );
			$this->assertNotNull( $fotografie[ $ruolo ]['impronta'], 'Precondizione: il file e\' sul disco.' );
		}

		return $fotografie;
	}

	/**
	 * Scarica un documento come un visitatore: lo stato della risposta e il file.
	 *
	 * @param int $documento Documento.
	 * @return array<string, mixed>
	 */
	private function scarica( int $documento ): array {
		$utente         = get_current_user_id();
		$this->consegne = array();
		wp_set_current_user( 0 );

		$this->go_to( (string) conformita_core_indirizzo_consegna( $documento ) );
		wp_set_current_user( $utente );

		$this->assertCount( 1, $this->consegne, 'Precondizione: una risposta del punto di consegna.' );

		return $this->consegne[0];
	}

	/**
	 * Nessun documento e' cambiato, e il pubblico scarica ancora gli stessi file.
	 *
	 * @param array<string, int>                  $documenti  Documenti.
	 * @param array<string, array<string, mixed>> $fotografie Fotografie di prima.
	 * @param string                              $via        La via tentata, per il messaggio.
	 */
	private function invariati( array $documenti, array $fotografie, string $via ): void {
		foreach ( $fotografie as $ruolo => $fotografia ) {
			$this->assertSame( $fotografia, $this->fotografia_documento( $documenti[ $ruolo ] ), $via . ': il documento ' . $ruolo . ' resta com\'era.' );
		}

		clean_post_cache( $documenti['atto'] );
		$this->assertSame( array( $documenti['principale'], $documenti['allegato'] ), DocumentiAtto::indicati( $documenti['atto'] ), $via . ': l\'atto indica ancora i suoi documenti.' );

		foreach ( array( 'principale', 'allegato' ) as $ruolo ) {
			$risposta = $this->scarica( $documenti[ $ruolo ] );

			$this->assertSame( 200, $risposta['stato'], $via . ': il pubblico scarica ancora il documento ' . $ruolo . '.' );
			$this->assertSame( $fotografie[ $ruolo ]['impronta'], hash_file( 'sha256', (string) $risposta['percorso'] ), $via . ': ed e\' lo stesso file.' );
		}
	}

	/**
	 * Lancia l'azione della libreria dei media che stacca o aggancia gli allegati scelti, fermandosi al rinvio.
	 *
	 * @param int            $genitore Contenuto a cui si agganciano, o da cui si staccano.
	 * @param array<int,int> $allegati Allegati scelti.
	 * @param string         $azione   `attach` oppure `detach`.
	 */
	private function libreria( int $genitore, array $allegati, string $azione ): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/media.php';

		$ferma = static function () {
			throw new \RuntimeException( 'rinvio' );
		};

		$_REQUEST['media'] = array_map( 'strval', $allegati );
		add_filter( 'wp_redirect', $ferma );

		try {
			wp_media_attach_action( $genitore, $azione );
		} catch ( \RuntimeException $rinvio ) {
			$this->assertSame( 'rinvio', $rinvio->getMessage() );
			// Quando nessun allegato resta, l'istruzione non parte: nessuna istruzione monca arriva alla banca dati.
			$this->assertSame( '', $wpdb->last_error, 'Nessun errore della banca dati.' );
		} finally {
			remove_filter( 'wp_redirect', $ferma );
			unset( $_REQUEST['media'] );
		}
	}

	/**
	 * Un allegato qualunque del sito, fuori dall'albo.
	 *
	 * @param int $genitore Contenuto a cui appartiene.
	 * @return int
	 */
	private function allegato_qualunque( int $genitore = 0 ): int {
		$file = wp_upload_bits( 'immagine-' . wp_generate_password( 6, false ) . '.txt', null, 'testo' );

		$this->assertSame( false, $file['error'], 'Precondizione: il file caricato.' );

		$id = wp_insert_attachment(
			array(
				'post_title'     => 'Allegato qualunque',
				'post_mime_type' => 'text/plain',
				'post_status'    => 'inherit',
			),
			$file['file'],
			$genitore
		);

		$this->assertIsInt( $id );

		return $id;
	}

	/**
	 * A-317: il documento di un atto pubblicato non si cancella e non va nel cestino (ALBO-09).
	 *
	 * Per il principale, l'allegato e la versione sostituita: cancellazione
	 * con e senza la forzatura, cancellazione chiesta come contenuto, cestino,
	 * e la cancellazione dall'interfaccia per programmi. Dopo ogni tentativo
	 * niente e' cambiato e il pubblico scarica gli stessi file.
	 */
	public function test_a317_ne_cancellazione_ne_cestino(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$id = $documenti[ $ruolo ];

			$this->assertFalse( wp_delete_attachment( $id, true ), 'La cancellazione forzata del documento ' . $ruolo . ' e\' rifiutata.' );
			$this->invariati( $documenti, $fotografie, 'wp_delete_attachment forzata, ' . $ruolo );

			$this->assertFalse( wp_delete_attachment( $id, false ), 'La cancellazione del documento ' . $ruolo . ' e\' rifiutata.' );
			$this->invariati( $documenti, $fotografie, 'wp_delete_attachment, ' . $ruolo );

			$this->assertFalse( wp_delete_post( $id, true ), 'La cancellazione come contenuto e\' rifiutata.' );
			$this->invariati( $documenti, $fotografie, 'wp_delete_post, ' . $ruolo );

			$this->assertFalse( wp_trash_post( $id ), 'Il cestino e\' rifiutato.' );
			$this->invariati( $documenti, $fotografie, 'wp_trash_post, ' . $ruolo );

			$richiesta = new \WP_REST_Request( 'DELETE', '/wp/v2/media/' . $id );
			$richiesta->set_param( 'force', true );
			$this->assertGreaterThanOrEqual( 400, rest_do_request( $richiesta )->get_status(), 'L\'interfaccia per programmi non cancella.' );
			$this->invariati( $documenti, $fotografie, 'interfaccia per programmi, ' . $ruolo );
		}
	}

	/**
	 * A-318: il salvataggio del documento non cambia atto, stato, tipo o tipo di file; titolo e descrizione si (ALBO-09).
	 */
	public function test_a318_salvataggio(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );
		$altro      = self::factory()->post->create();

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$id = $documenti[ $ruolo ];

			foreach ( array(
				array( 'post_parent' => 0 ),
				array( 'post_parent' => $altro ),
				array( 'post_status' => 'private' ),
				array( 'post_status' => 'trash' ),
				array( 'post_mime_type' => 'text/plain' ),
			) as $campi ) {
				$esito = wp_update_post( array( 'ID' => $id ) + $campi, true );

				$this->assertWPError( $esito, 'Il salvataggio di ' . implode( ', ', array_keys( $campi ) ) . ' del documento ' . $ruolo . ' e\' rifiutato.' );
				$this->invariati( $documenti, $fotografie, 'wp_update_post ' . wp_json_encode( $campi ) . ', ' . $ruolo );
			}

			$this->assertWPError(
				wp_insert_post(
					array(
						'ID'         => $id,
						'post_type'  => 'post',
						'post_title' => 'Ora sono un articolo',
					),
					true
				),
				'Il salvataggio che cambia il tipo e\' rifiutato.'
			);
			$this->invariati( $documenti, $fotografie, 'wp_insert_post con il tipo, ' . $ruolo );

			$richiesta = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $id );
			$richiesta->set_param( 'post', $altro );
			$this->assertGreaterThanOrEqual( 400, rest_do_request( $richiesta )->get_status(), 'L\'interfaccia per programmi non cambia l\'atto del documento.' );
			$this->invariati( $documenti, $fotografie, 'interfaccia per programmi, ' . $ruolo );
		}

		// Un altro aggancio cambia l'atto dopo il vaglio della richiesta: si scrive il titolo, l'atto resta.
		$sposta = static function () use ( $altro ) {
			return $altro;
		};
		add_filter( 'wp_insert_post_parent', $sposta );
		$this->assertSame(
			$documenti['allegato'],
			wp_update_post(
				array(
					'ID'         => $documenti['allegato'],
					'post_title' => 'Titolo cambiato con l\'atto spostato da un altro aggancio',
				)
			)
		);
		remove_filter( 'wp_insert_post_parent', $sposta );
		clean_post_cache( $documenti['allegato'] );
		$this->assertSame( $documenti['atto'], (int) get_post( $documenti['allegato'] )->post_parent, 'L\'atto del documento resta quello.' );
		$this->assertSame( 'Titolo cambiato con l\'atto spostato da un altro aggancio', get_post( $documenti['allegato'] )->post_title );

		// Controllo positivo: titolo e descrizione cambiano, da codice e dall'interfaccia per programmi.
		$this->assertSame(
			$documenti['principale'],
			wp_update_post(
				array(
					'ID'           => $documenti['principale'],
					'post_title'   => 'Titolo nuovo',
					'post_content' => 'Descrizione nuova',
				)
			)
		);
		$richiesta = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $documenti['sostituito'] );
		$richiesta->set_param( 'title', 'Titolo della versione sostituita' );
		$this->assertSame( 200, rest_do_request( $richiesta )->get_status() );

		clean_post_cache( $documenti['principale'] );
		clean_post_cache( $documenti['sostituito'] );
		$this->assertSame( 'Titolo nuovo', get_post( $documenti['principale'] )->post_title );
		$this->assertSame( 'Descrizione nuova', get_post( $documenti['principale'] )->post_content );
		$this->assertSame( 'Titolo della versione sostituita', get_post( $documenti['sostituito'] )->post_title );
		$this->assertSame( array( $documenti['principale'], $documenti['allegato'] ), DocumentiAtto::indicati( $documenti['atto'] ) );
		$this->assertSame( $fotografie['principale']['impronta'], $this->fotografia_documento( $documenti['principale'] )['impronta'] );
	}

	/**
	 * A-319: dalla libreria dei media il documento non si stacca e non si aggancia altrove, e il tipo non cambia (ALBO-09).
	 *
	 * L'azione vera di WordPress, con l'amministratore che ha il permesso di
	 * modificare l'atto. In una scelta mista l'allegato qualunque si stacca, il
	 * documento resta.
	 */
	public function test_a319_libreria_dei_media(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );
		$altro      = self::factory()->post->create();
		$qualunque  = $this->allegato_qualunque( $altro );

		$this->assertTrue( current_user_can( 'edit_post', $documenti['principale'] ), 'Precondizione: l\'amministratore puo\' modificare il documento, e quindi staccarlo.' );

		$this->libreria( $documenti['atto'], array( $documenti['principale'], $documenti['allegato'], $documenti['sostituito'] ), 'detach' );
		$this->invariati( $documenti, $fotografie, 'stacco dalla libreria' );

		$this->libreria( $altro, array( $documenti['principale'], $documenti['allegato'], $documenti['sostituito'] ), 'attach' );
		$this->invariati( $documenti, $fotografie, 'aggancio dalla libreria' );

		$this->libreria( $altro, array( $qualunque, $documenti['principale'] ), 'detach' );
		$this->invariati( $documenti, $fotografie, 'stacco misto' );
		clean_post_cache( $qualunque );
		$this->assertSame( 0, (int) get_post( $qualunque )->post_parent, 'Controllo positivo: l\'allegato qualunque si stacca.' );

		$this->libreria( $altro, array( $qualunque ), 'attach' );
		clean_post_cache( $qualunque );
		$this->assertSame( $altro, (int) get_post( $qualunque )->post_parent, 'Controllo positivo: e si riaggancia.' );

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$this->assertFalse( set_post_type( $documenti[ $ruolo ], 'post' ), 'Il tipo del documento ' . $ruolo . ' non cambia.' );
			$this->invariati( $documenti, $fotografie, 'set_post_type, ' . $ruolo );
		}

		$this->assertSame( 1, set_post_type( $qualunque, 'post' ), 'Controllo positivo: il tipo di un contenuto qualunque cambia.' );
	}

	/**
	 * A-320: i dati del file, la marca e l'impronta del documento non cambiano; il segno della sostituzione resta (ALBO-10, ALBO-12).
	 */
	public function test_a320_metadati(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );
		$chiavi     = array(
			'_wp_attached_file',
			\Conformita_Core_Allegati::MARCA,
			\Conformita_Core_Allegati::IMPRONTA,
			\Conformita_Core_Allegati::DIMENSIONE,
			\Conformita_Core_Allegati::DEPOSITO,
		);

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$id = $documenti[ $ruolo ];

			$this->assertFalse( update_attached_file( $id, '/tmp/altro.pdf' ), 'Il file del documento ' . $ruolo . ' non cambia.' );
			$this->invariati( $documenti, $fotografie, 'update_attached_file, ' . $ruolo );

			foreach ( $chiavi as $chiave ) {
				$this->assertFalse( update_post_meta( $id, $chiave, 'altro' ), $chiave . ' non cambia.' );
				$this->assertFalse( delete_post_meta( $id, $chiave ), $chiave . ' non si toglie.' );
				$this->assertFalse( add_post_meta( $id, $chiave, 'doppione' ), $chiave . ' non si raddoppia.' );
				$this->invariati( $documenti, $fotografie, $chiave . ', ' . $ruolo );

				$riga = (int) $this->fotografia_documento( $id )['dati'][ array_search( $chiave, array_column( $this->fotografia_documento( $id )['dati'], 'meta_key' ), true ) ]['meta_id'];
				$this->assertFalse( update_metadata_by_mid( 'post', $riga, 'altro' ), $chiave . ' non cambia per numero di riga.' );
				$this->assertFalse( update_metadata_by_mid( 'post', $riga, 'altro', '_chiave_qualunque' ), $chiave . ' non cambia nome.' );
				$this->assertFalse( delete_metadata_by_mid( 'post', $riga ), $chiave . ' non si toglie per numero di riga.' );
				$this->invariati( $documenti, $fotografie, $chiave . ' per numero di riga, ' . $ruolo );

				$this->assertFalse( delete_post_meta_by_key( $chiave ), $chiave . ' non si toglie a tutti i contenuti.' );
				$this->invariati( $documenti, $fotografie, $chiave . ' a tutti i contenuti, ' . $ruolo );
			}
		}

		// Una riga qualunque non prende il nome di una chiave del file.
		$this->assertIsInt( add_post_meta( $documenti['allegato'], '_chiave_qualunque', 'valore' ) );
		$riga = (int) end( $this->fotografia_documento( $documenti['allegato'] )['dati'] )['meta_id'];
		$this->assertFalse( update_metadata_by_mid( 'post', $riga, '/tmp/altro.pdf', '_wp_attached_file' ), 'Una riga qualunque non diventa il file del documento.' );
		$this->assertTrue( delete_post_meta( $documenti['allegato'], '_chiave_qualunque' ), 'Controllo positivo: la riga qualunque si toglie.' );

		// Il segno della sostituzione resta, e non si cambia.
		$sostituito = $documenti['sostituito'];
		$this->assertFalse( update_post_meta( $sostituito, META_SOSTITUITO_DA, $documenti['allegato'] ), 'Il segno non cambia.' );
		$this->assertFalse( delete_post_meta( $sostituito, META_SOSTITUITO_DA ), 'Il segno non si toglie.' );
		$this->assertFalse( add_post_meta( $sostituito, META_SOSTITUITO_DA, $documenti['allegato'] ), 'Il segno non si raddoppia.' );
		$this->assertFalse( delete_post_meta_by_key( META_SOSTITUITO_DA ), 'Il segno non si toglie a tutti i contenuti.' );
		$this->assertSame( $documenti['principale'], DocumentiAtto::sostituito_da( $sostituito ) );
		$this->assertSame( 1, count( get_post_meta( $sostituito, META_SOSTITUITO_DA, false ) ) );

		// Controlli positivi: un dato qualunque del documento si scrive, e la chiave del file di un allegato qualunque cambia.
		$qualunque = $this->allegato_qualunque();
		$this->assertTrue( update_attached_file( $qualunque, '/tmp/altro.txt' ) );
		$this->invariati( $documenti, $fotografie, 'dopo i controlli positivi' );
		$this->assertNotFalse( update_post_meta( $documenti['principale'], '_wp_attachment_image_alt', 'Testo alternativo' ) );
		$this->assertSame( 'Testo alternativo', get_post_meta( $documenti['principale'], '_wp_attachment_image_alt', true ) );
	}

	/**
	 * A-321: la protezione vale per l'atto in verifica, tolto dall'albo e annullato (ALBO-09).
	 */
	public function test_a321_atto_in_verifica_tolto_e_annullato(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$in_verifica = $this->atto_in_verifica();
		$documento   = (int) DocumentiAtto::principale( $in_verifica );
		$this->assertFalse( wp_delete_attachment( $documento, true ), 'In verifica il documento non si cancella.' );
		$this->assertWPError(
			wp_update_post(
				array(
					'ID'          => $documento,
					'post_parent' => 0,
				),
				true
			),
			'In verifica non si stacca.'
		);
		clean_post_cache( $documento );
		$this->assertSame( $in_verifica, (int) get_post( $documento )->post_parent );

		foreach ( array( 'tolto', 'annullato' ) as $caso ) {
			$documenti = $this->pubblicato_e_oscurato();
			$id        = $documenti['atto'];
			$esito     = $this->come(
				$this->amministratore,
				static function () use ( $id, $caso ) {
					return 'tolto' === $caso
						? albo_pretorio_defiggi( $id, 'dati_salute', 'Contiene una diagnosi.' )
						: albo_pretorio_annulla( $id, 'Atto adottato per errore materiale.' );
				}
			);
			$this->assertIsInt( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : 'Precondizione: ' . $caso . '.' );

			foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
				$prima = $this->fotografia_documento( $documenti[ $ruolo ] );

				$this->assertFalse( wp_delete_attachment( $documenti[ $ruolo ], true ), $caso . ': il documento ' . $ruolo . ' non si cancella.' );
				$this->assertWPError(
					wp_update_post(
						array(
							'ID'          => $documenti[ $ruolo ],
							'post_parent' => 0,
						),
						true
					),
					$caso . ': non si stacca.'
				);
				$this->libreria( $id, array( $documenti[ $ruolo ] ), 'detach' );
				$this->assertFalse( update_attached_file( $documenti[ $ruolo ], '/tmp/altro.pdf' ), $caso . ': il file non cambia.' );
				$this->assertSame( $prima, $this->fotografia_documento( $documenti[ $ruolo ] ), $caso . ': il documento ' . $ruolo . ' resta com\'era.' );
			}
		}
	}

	/**
	 * A-322: una lettura che non risponde ferma la scrittura (ALBO-09).
	 *
	 * Il guasto colpisce la lettura che decide se il contenuto e' un documento
	 * di un atto fermo, poi quella dei dati che lo legano all'atto.
	 */
	public function test_a322_lettura_guasta(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );
		$qualunque  = $this->allegato_qualunque();

		foreach ( array( 'AS documento LEFT JOIN', 'OR ( post_id = ' ) as $pezzo ) {
			$this->guasta_istruzioni( $pezzo );

			$this->assertFalse( wp_delete_attachment( $documenti['allegato'], true ), $pezzo . ': la cancellazione si ferma.' );
			$this->assertFalse( wp_trash_post( $documenti['allegato'] ), $pezzo . ': il cestino si ferma.' );
			$this->assertWPError(
				wp_update_post(
					array(
						'ID'          => $documenti['allegato'],
						'post_parent' => 0,
					),
					true
				),
				$pezzo . ': il salvataggio si ferma.'
			);
			$this->assertFalse( update_attached_file( $documenti['allegato'], '/tmp/altro.pdf' ), $pezzo . ': il file non cambia.' );
			$this->assertFalse( delete_post_meta( $documenti['sostituito'], META_SOSTITUITO_DA ), $pezzo . ': il segno resta.' );
			$this->libreria( $documenti['atto'], array( $documenti['allegato'] ), 'detach' );

			// Senza la prima lettura anche l'allegato qualunque vale come protetto: nel dubbio si chiude.
			if ( 'AS documento LEFT JOIN' === $pezzo ) {
				$this->assertFalse( wp_delete_attachment( $qualunque, true ), 'Nel dubbio si chiude anche per un allegato qualunque.' );
			}

			$this->ripara();
			$this->invariati( $documenti, $fotografie, 'lettura guasta ' . $pezzo );
		}

		$this->guasta_istruzioni( 'AS m JOIN' );
		$this->assertFalse( delete_post_meta_by_key( '_wp_attached_file' ), 'Senza la lettura la chiave non si toglie a tutti.' );
		$this->ripara();

		$this->guasta_istruzioni( 'SELECT meta_key, post_id FROM' );
		$riga = (int) $this->fotografia_documento( $documenti['allegato'] )['dati'][0]['meta_id'];
		$this->assertFalse( delete_metadata_by_mid( 'post', $riga ), 'Senza la lettura della riga, la riga non si toglie.' );
		$this->ripara();

		$this->invariati( $documenti, $fotografie, 'dopo le letture guaste' );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $qualunque, true ), 'Controllo positivo: riparata la lettura, l\'allegato qualunque si cancella.' );
	}

	/**
	 * A-323: cio' che non e' un documento di un atto fermo si toglie come prima (ALBO-09, ALBO-12).
	 *
	 * Il documento di una bozza si toglie dal riquadro; il figlio di un atto
	 * pubblicato che nessun dato indica, come il file di un oscuramento non
	 * riuscito, si cancella; un allegato qualunque si stacca e si cancella.
	 */
	public function test_a323_cio_che_resta_libero(): void {
		$bozza     = $this->atto_completo();
		$documento = (int) DocumentiAtto::principale( $bozza );
		$this->assertTrue( DocumentiAtto::togli( $bozza, $documento ), 'Il documento della bozza si toglie dal riquadro.' );
		clean_post_cache( $documento );
		$this->assertNull( get_post( $documento ) );

		$documenti = $this->pubblicato_e_oscurato();
		$orfano    = conformita_core_deposita_allegato( $documenti['atto'], $this->file_temporaneo( 'orfano.pdf', $this->pdf( 'orfano' ) ), array( 'origine' => 'percorso_locale' ) );
		$this->assertIsInt( $orfano, 'Precondizione: un figlio dell\'atto che nessun dato indica.' );
		$this->assertSame( DocumentiFermi::LIBERO, DocumentiFermi::stato( $orfano ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $orfano, true ), 'Il figlio non indicato si cancella.' );

		$qualunque = $this->allegato_qualunque( self::factory()->post->create() );
		$this->assertSame(
			$qualunque,
			wp_update_post(
				array(
					'ID'          => $qualunque,
					'post_parent' => 0,
				)
			),
			'Un allegato qualunque si stacca.'
		);
		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $qualunque ), 'Va nel cestino.' );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $qualunque, true ), 'E si cancella.' );

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$this->assertSame( DocumentiFermi::PROTETTO, DocumentiFermi::stato( $documenti[ $ruolo ] ), 'Il documento ' . $ruolo . ' e\' protetto.' );
		}

		$this->assertSame( DocumentiFermi::LIBERO, DocumentiFermi::stato( $documenti['atto'] ), 'L\'atto stesso non e\' un documento: lo governa lo sbarramento.' );

		// Un documento gia' guastato da un'istruzione diretta non e' piu' in vita, e si ripara.
		global $wpdb;
		$this->scavalca_barriera(
			static function () use ( $wpdb, $documenti ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato guastato da fuori.
				return $wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $documenti['allegato'] ) );
			}
		);
		clean_post_cache( $documenti['allegato'] );
		$this->assertSame( DocumentiFermi::LIBERO, DocumentiFermi::stato( $documenti['allegato'] ) );
		$this->assertSame(
			$documenti['allegato'],
			wp_update_post(
				array(
					'ID'          => $documenti['allegato'],
					'post_status' => 'inherit',
				)
			),
			'Lo stato si rimette.'
		);
		clean_post_cache( $documenti['allegato'] );
		$this->assertSame( array( $documenti['principale'], $documenti['allegato'] ), DocumentiAtto::indicati( $documenti['atto'] ), 'E l\'atto indica di nuovo il suo allegato.' );
		$this->assertSame( DocumentiFermi::PROTETTO, DocumentiFermi::stato( $documenti['allegato'] ) );

		// Un contenuto di un altro tipo, pubblicato, che porta gli stessi dati non protegge il suo allegato.
		$articolo = self::factory()->post->create();
		$suo      = $this->allegato_qualunque( $articolo );
		update_post_meta( $articolo, \AlboPretorioPa\META_DOCUMENTO_PRINCIPALE, $suo );
		update_post_meta( $suo, META_SOSTITUITO_DA, 1 );
		$this->assertSame( DocumentiFermi::LIBERO, DocumentiFermi::stato( $suo ) );
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $suo, true ), 'L\'allegato di un articolo si cancella.' );
		$this->assertSame( TIPO, get_post_type( $documenti['atto'] ) );
	}

	/**
	 * A-324: le scritture dirette di WordPress sulla riga del documento non cambiano atto, stato, tipo o tipo di file (ALBO-09).
	 *
	 * `wp_publish_post()` e ogni altra scrittura nella forma di `$wpdb->update()`
	 * sulla riga del documento, con un campo solo o con tutti i campi come la
	 * scrive il salvataggio.
	 */
	public function test_a324_scritture_dirette_di_wordpress(): void {
		global $wpdb;

		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );

		foreach ( array( 'principale', 'allegato', 'sostituito' ) as $ruolo ) {
			$id = $documenti[ $ruolo ];

			wp_publish_post( $id );
			$this->invariati( $documenti, $fotografie, 'wp_publish_post, ' . $ruolo );

			foreach ( array(
				array( 'post_status' => 'private' ),
				array( 'post_parent' => 0 ),
				array( 'post_mime_type' => 'text/plain' ),
				array(
					'post_title'  => 'Titolo cambiato insieme allo stato',
					'post_status' => 'draft',
				),
			) as $campi ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la forma di $wpdb->update usata dalle funzioni di WordPress.
				$this->assertFalse( $wpdb->update( $wpdb->posts, $campi, array( 'ID' => $id ) ), wp_json_encode( $campi ) . ' non si scrive sul documento ' . $ruolo . '.' );
				$this->invariati( $documenti, $fotografie, '$wpdb->update ' . wp_json_encode( $campi ) . ', ' . $ruolo );
			}
		}

		// Controllo positivo: la stessa forma con i campi com'erano passa, e cambia il titolo.
		$riga = $fotografie['allegato']['riga'];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- come sopra.
		$esito = $wpdb->update(
			$wpdb->posts,
			array(
				'post_title'     => 'Titolo con apice \' e barra \\ e 50%',
				'post_parent'    => (int) $riga['post_parent'],
				'post_status'    => $riga['post_status'],
				'post_type'      => $riga['post_type'],
				'post_mime_type' => $riga['post_mime_type'],
			),
			array( 'ID' => $documenti['allegato'] )
		);
		$this->assertSame( 1, $esito, 'Con i campi di prima la scrittura passa.' );
		clean_post_cache( $documenti['allegato'] );
		$this->assertSame( 'Titolo con apice \' e barra \\ e 50%', get_post( $documenti['allegato'] )->post_title );

		$qualunque = $this->allegato_qualunque();
		wp_publish_post( $qualunque );
		clean_post_cache( $qualunque );
		$this->assertSame( 'publish', get_post_status( $qualunque ), 'Controllo positivo: un allegato qualunque si scrive.' );
	}

	/**
	 * A-325: le chiavi dei metadati scritte con un'altra forma non aggirano la protezione (ALBO-10, ALBO-12).
	 *
	 * La banca dati confronta le chiavi senza distinguere maiuscole e
	 * minuscole e senza contare gli spazi in fondo: una variante della chiave
	 * tocca la stessa riga, o ne aggiunge una che le letture dirette contano.
	 */
	public function test_a325_chiavi_in_altra_forma(): void {
		$this->setExpectedIncorrectUsage( DocumentiFermi::SEGNALAZIONE );

		$documenti  = $this->pubblicato_e_oscurato();
		$fotografie = $this->fotografie( $documenti );
		$varianti   = array( '_WP_ATTACHED_FILE', '_wp_attached_file ', '_Conformita_Core_Impronta', strtoupper( META_SOSTITUITO_DA ), '_wp_àttached_file' );

		foreach ( $varianti as $variante ) {
			// Il segno manca sul principale, e li' si puo' scrivere: la variante del segno si prova sulla versione sostituita.
			foreach ( strtoupper( META_SOSTITUITO_DA ) === $variante ? array( 'sostituito' ) : array( 'principale', 'sostituito' ) as $ruolo ) {
				$id = $documenti[ $ruolo ];

				$this->assertFalse( update_post_meta( $id, $variante, 'altro' ), '"' . $variante . '" non cambia sul documento ' . $ruolo . '.' );
				$this->assertFalse( delete_post_meta( $id, $variante ), '"' . $variante . '" non si toglie.' );
				$this->assertFalse( add_post_meta( $id, $variante, 'doppione' ), '"' . $variante . '" non si aggiunge.' );
				$this->invariati( $documenti, $fotografie, $variante . ', ' . $ruolo );
			}

			$this->assertFalse( delete_post_meta_by_key( $variante ), '"' . $variante . '" non si toglie a tutti i contenuti.' );
			$this->invariati( $documenti, $fotografie, $variante . ' a tutti i contenuti' );
		}

		$this->assertIsInt( add_post_meta( $documenti['allegato'], '_chiave_qualunque', 'valore' ) );
		$righe = $this->fotografia_documento( $documenti['allegato'] )['dati'];
		$riga  = (int) end( $righe )['meta_id'];
		$this->assertFalse( update_metadata_by_mid( 'post', $riga, '/tmp/altro.pdf', '_WP_Attached_File' ), 'Una riga qualunque non diventa il file del documento con un altro nome.' );
		$this->assertTrue( delete_post_meta( $documenti['allegato'], '_chiave_qualunque' ) );

		// Controllo positivo: una chiave qualunque in maiuscolo si scrive.
		$this->assertIsInt( add_post_meta( $documenti['allegato'], '_CHIAVE_QUALUNQUE', 'valore' ) );
		$this->assertTrue( delete_post_meta( $documenti['allegato'], '_CHIAVE_QUALUNQUE' ) );
		$this->invariati( $documenti, $fotografie, 'dopo i controlli positivi' );
	}
}
