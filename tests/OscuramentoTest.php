<?php
/**
 * La sostituzione di un documento con la sua versione oscurata: righe A-293..A-301 e, dal riquadro, A-302..A-304 (ALBO-12, ALBO-08, ALBO-09, ALBO-10, ALBO-27).
 *
 * Gli atti si portano a pubblicato con il passaggio vero, l'orologio avanza
 * di qualche giorno e la sostituzione si fa con il passaggio vero, da
 * codice. Le impronte attese si calcolano qui, sui file usati, prima di
 * consegnarli al componente. Ogni rifiuto ha accanto il suo controllo
 * positivo.
 *
 * I valori attesi sono scritti per esteso: 1 ottobre 2041 alle 9:30 piu' 15
 * giorni fa fine il 16 ottobre; la sostituzione avviene il 5 ottobre alle
 * 10:00.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Durate;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\RiquadroPassaggi;

use const AlboPretorioPa\META_ALLEGATI_ULTERIORI;
use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\META_DOCUMENTO_PRINCIPALE;
use const AlboPretorioPa\META_NUMERO_PROPRIO;
use const AlboPretorioPa\META_SOSTITUITO_DA;
use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;

/**
 * Sostituzione per oscuramento: date ferme, impronte, consegna, permessi, tutto o niente.
 */
class OscuramentoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'istante della sostituzione: quattro giorni dopo la pubblicazione.
	 */
	private const ISTANTE_OSCURAMENTO = '2041-10-05 10:00:00';

	/**
	 * Il motivo usato quando il motivo non e' cio' che si prova.
	 */
	private const MOTIVO = 'Il documento riporta l\'indirizzo di casa del destinatario.';

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
	 * Le impronte dei documenti principali depositati dalle prove, per atto.
	 *
	 * @var array<int, string>
	 */
	private array $impronte = array();

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
	 * Attiva un guasto sulle istruzioni che contengono tutti i pezzi indicati.
	 *
	 * @param array<int, string> $pezzi Pezzi dell'istruzione.
	 */
	private function guasta_istruzioni( array $pezzi ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );

		$this->guasto = static function ( string $istruzione ) use ( $pezzi ): bool {
			foreach ( $pezzi as $pezzo ) {
				if ( false === strpos( $istruzione, $pezzo ) ) {
					return false;
				}
			}

			return true;
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
	 * Un atto pubblicato con il passaggio vero, con un principale di contenuto noto, poi l'orologio sulla sostituzione.
	 *
	 * @param string             $tipo     Nome breve del tipo.
	 * @param array<int, string> $allegati Contenuti degli allegati ulteriori.
	 * @return int
	 */
	private function pubblicato( string $tipo = 'normativo', array $allegati = array() ): int {
		$this->fissa_orologi( '2041-10-01 09:30:00' );

		$id        = $this->atto_completo( $tipo );
		$contenuto = $this->pdf( 'originale' );

		$this->impronte[ $id ] = hash( 'sha256', $contenuto );
		$this->assertIsInt( DocumentiAtto::deposita( $id, $this->file_temporaneo( 'atto.pdf', $contenuto ), DocumentiAtto::PRINCIPALE, 'percorso_locale' ) );

		foreach ( $allegati as $indice => $allegato ) {
			$this->assertIsInt( DocumentiAtto::deposita( $id, $this->file_temporaneo( 'allegato-' . $indice . '.pdf', $allegato ), DocumentiAtto::ULTERIORE, 'percorso_locale' ) );
		}

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
		$this->fissa_orologi( self::ISTANTE_OSCURAMENTO );

		return $id;
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
	 * La richiesta corretta, con le modifiche indicate.
	 *
	 * @param array<string, mixed> $altro Chiavi da aggiungere o sostituire.
	 * @return array<string, mixed>
	 */
	private function richiesta( array $altro = array() ): array {
		return array_merge(
			array(
				'conferma' => true,
				'causa'    => Passaggi::CAUSA_DATI_ECCEDENTI,
				'motivo'   => self::MOTIVO,
			),
			$altro
		);
	}

	/**
	 * Sostituisce da codice, come l'utente indicato.
	 *
	 * @param int                  $atto_id   Atto.
	 * @param int                  $utente    Utente.
	 * @param int                  $documento Documento da sostituire.
	 * @param string               $contenuto Contenuto del file entrante.
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @return mixed
	 */
	private function oscura( int $atto_id, int $utente, int $documento, string $contenuto, array $richiesta ) {
		$file = $this->file_temporaneo( 'oscurato.pdf', $contenuto );

		return $this->come(
			$utente,
			static function () use ( $atto_id, $documento, $file, $richiesta ) {
				return albo_pretorio_oscura( $atto_id, $documento, $file, 'percorso_locale', $richiesta );
			}
		);
	}

	/**
	 * I codici di un rifiuto.
	 *
	 * @param mixed $esito Esito.
	 * @return array<int, string>
	 */
	private function codici( $esito ): array {
		$this->assertWPError( $esito, 'La sostituzione deve essere rifiutata.' );

		return $esito->get_error_codes();
	}

	/**
	 * Scarica un documento come un visitatore: la risposta del punto pubblico.
	 *
	 * @param int $documento Documento.
	 * @return array<string, mixed>
	 */
	private function scarica( int $documento ): array {
		$this->consegne = array();
		wp_set_current_user( 0 );

		$this->go_to( (string) conformita_core_indirizzo_consegna( $documento ) );

		$this->assertCount( 1, $this->consegne, 'Precondizione: una risposta del punto di consegna.' );

		return $this->consegne[0];
	}

	/**
	 * Apre un documento dal punto amministrativo, come l'utente indicato, con un collegamento valido per lui.
	 *
	 * @param int $utente    Utente.
	 * @param int $documento Documento.
	 * @return int Lo stato della risposta.
	 */
	private function apri_in_amministrazione( int $utente, int $documento ): int {
		wp_set_current_user( $utente );

		wp_parse_str( (string) wp_parse_url( html_entity_decode( (string) conformita_core_indirizzo_consegna_amministrativa( $documento ) ), PHP_URL_QUERY ), $parametri );

		$this->consegne = array();
		$_GET           = $parametri;
		$_REQUEST       = $parametri;

		try {
			do_action( 'admin_post_' . $parametri['action'] );
		} catch ( \WPDieException $rifiuto ) {
			$this->assertCount( 0, $this->consegne, 'Il rifiuto arriva prima della consegna.' );

			return 404;
		}

		$this->assertCount( 1, $this->consegne );

		if ( 200 === $this->consegne[0]['stato'] ) {
			$this->assertSame( realpath( (string) get_attached_file( $documento ) ), $this->consegne[0]['percorso'], 'Ed e\' proprio quel file.' );
		}

		return (int) $this->consegne[0]['stato'];
	}

	/**
	 * I documenti depositati dal meccanismo comune, figli di un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, int>
	 */
	private function figli( int $atto_id ): array {
		return array_map(
			'intval',
			get_children(
				array(
					'post_parent' => $atto_id,
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
					'orderby'     => 'ID',
					'order'       => 'ASC',
				)
			)
		);
	}

	/**
	 * La fotografia dell'atto senza cio' che la sostituzione cambia: il dato del documento, gli allegati, il disco e il registro.
	 *
	 * @param array<string, mixed> $foto   Fotografia.
	 * @param string               $chiave Il dato del documento.
	 * @return array<string, mixed>
	 */
	private function senza_sostituzione( array $foto, string $chiave ): array {
		unset( $foto['allegati'], $foto['disco'], $foto['registro'] );

		$foto['dati'] = array_values(
			array_filter(
				$foto['dati'],
				static function ( array $riga ) use ( $chiave ): bool {
					return $chiave !== $riga['meta_key'];
				}
			)
		);

		return $foto;
	}

	/**
	 * A-293: la sostituzione giorni dopo la pubblicazione lascia numero, stato e tutte e due le date (ALBO-12, ALBO-27).
	 *
	 * L'atto si pubblica il 1 ottobre e si oscura il 5: se la sostituzione
	 * riscrivesse l'inizio con l'istante corrente, o ricalcolasse la fine, le
	 * date cambierebbero. La riga e' ripetuta su un atto la cui durata del
	 * tipo cambia dopo la pubblicazione, cosi' che un ricalcolo della fine
	 * dalla durata si veda: la fine resta quella scritta, e con il compito
	 * pianificato fermo l'atto e' irraggiungibile dal giorno dopo quella fine.
	 * La durata propria del singolo atto (ALBO-04) non esiste ancora: quando
	 * ci sara', la ripetizione si fa su quella.
	 */
	public function test_a293_date_e_numero_restano(): void {
		foreach ( array( 'normativo', 'scelto' ) as $tipo ) {
			$id         = $this->pubblicato( $tipo );
			$numero     = Repertorio::di( $id );
			$fine       = conformita_core_fine_pubblicazione( $id );
			$prima      = $this->fotografia( $id );
			$principale = (int) DocumentiAtto::principale( $id );

			$this->assertIsArray( $numero, 'Precondizione: l\'atto ha il numero.' );
			$this->assertSame( 'normativo' === $tipo ? '2041-10-16' : '2041-10-11', $fine, 'Precondizione: la fine scritta alla pubblicazione.' );

			// La durata del tipo cambia dopo la pubblicazione: una fine ricalcolata sarebbe un'altra.
			$voce = 'normativo' === $tipo ? $this->voci['normativo'] : $this->voci['scelto'];
			$this->assertTrue( 'normativo' === $tipo ? Durate::configura( $voce, 30, 'norma', 'Estremi di prova' ) : Durate::configura( $voce, 30, 'amministrazione', '' ) );

			$esito = $this->oscura( $id, $this->pubblicatore, $principale, $this->pdf( 'oscurato' ), $this->richiesta() );

			$this->assertIsArray( $esito, is_wp_error( $esito ) ? implode( ' ', $esito->get_error_messages() ) : '' );

			$dopo = $this->fotografia( $id );

			$this->assertSame( 'publish', $dopo['riga']['post_status'], 'Tipo ' . $tipo . ': resta pubblicato.' );
			$this->assertSame( '2041-10-01 09:30:00', $dopo['riga']['post_date'], 'Tipo ' . $tipo . ': l\'inizio e\' quello della pubblicazione, non quello della sostituzione.' );
			$this->assertSame( $prima['riga'], $dopo['riga'], 'Tipo ' . $tipo . ': la riga dell\'atto non e\' toccata, date di modifica comprese.' );
			$this->assertSame( $fine, conformita_core_fine_pubblicazione( $id ), 'Tipo ' . $tipo . ': la fine non cambia.' );
			$this->assertSame( $numero, Repertorio::di( $id ), 'Tipo ' . $tipo . ': il numero non cambia.' );
			$this->assertSame( $this->senza_sostituzione( $prima, META_DOCUMENTO_PRINCIPALE ), $this->senza_sostituzione( $dopo, META_DOCUMENTO_PRINCIPALE ), 'Tipo ' . $tipo . ': voci e dati della scheda non cambiano.' );
			$this->assertSame( $esito['documento'], DocumentiAtto::principale( $id ), 'Tipo ' . $tipo . ': il principale e\' l\'entrante.' );
			$this->assertNotSame( $principale, $esito['documento'] );

			// Con il compito pianificato fermo: raggiungibile fino alla fine scritta, non oltre.
			$this->fissa_orologi( $fine . ' 23:59:00' );
			$this->assertFalse( conformita_core_scaduto( $id ), 'Tipo ' . $tipo . ': raggiungibile l\'ultimo giorno.' );
			$this->fissa_orologi( gmdate( 'Y-m-d', (int) strtotime( $fine . ' +1 day' ) ) . ' 00:00:00' );
			$this->assertTrue( conformita_core_scaduto( $id ), 'Tipo ' . $tipo . ': irraggiungibile dal giorno dopo, prima della fine che la durata nuova darebbe.' );
			$this->assertSame( 'publish', get_post_status( $id ), 'Si guarda l\'orologio, non lo stato.' );
		}
	}

	/**
	 * A-294: impronte di tutte e due le versioni, e il pubblico scarica l'entrante (ALBO-12, ALBO-10).
	 *
	 * Prima della sostituzione il pubblico scarica l'uscente, con l'impronta
	 * calcolata qui; dopo scarica l'entrante, con l'impronta calcolata qui sul
	 * file prima di consegnarlo, e l'uscente non esce piu'. Le due impronte del
	 * registro si confrontano con quelle calcolate qui, non con quelle che il
	 * componente ha scritto per conto suo. Lo stesso per un allegato
	 * ulteriore, che resta nella sua posizione dell'elenco.
	 */
	public function test_a294_impronte_e_consegna(): void {
		$id         = $this->pubblicato( 'normativo', array( $this->pdf( 'primo' ), $this->pdf( 'secondo' ) ) );
		$principale = (int) DocumentiAtto::principale( $id );
		$ulteriori  = DocumentiAtto::ulteriori( $id );

		$this->assertCount( 2, $ulteriori, 'Precondizione: due allegati ulteriori.' );

		$prima = $this->scarica( $principale );
		$this->assertSame( 200, $prima['stato'], 'Prima della sostituzione l\'uscente si scarica.' );
		$this->assertSame( $this->impronte[ $id ], hash_file( 'sha256', (string) $prima['percorso'] ), 'Ed e\' il file pubblicato.' );

		$registro  = $this->voci_di( $id );
		$contenuto = $this->pdf( 'oscurato' );
		$attesa    = hash( 'sha256', $contenuto );
		$esito     = $this->oscura( $id, $this->pubblicatore, $principale, $contenuto, $this->richiesta() );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? implode( ' ', $esito->get_error_messages() ) : '' );

		$dopo = $this->scarica( $esito['documento'] );
		$this->assertSame( 200, $dopo['stato'], 'Dopo, l\'entrante si scarica.' );
		$this->assertSame( $attesa, hash_file( 'sha256', (string) $dopo['percorso'] ), 'Ed e\' proprio il file consegnato per la sostituzione.' );
		$this->assertSame( 404, $this->scarica( $principale )['stato'], 'L\'uscente non esce piu\' dal punto pubblico.' );
		$this->assertFileExists( (string) get_attached_file( $principale ), 'Ma resta sul disco: e\' parte dell\'atto adottato.' );
		$this->assertSame( $esito['documento'], DocumentiAtto::sostituito_da( $principale ), 'Ed e\' segnato come sostituito dall\'entrante.' );

		foreach ( $ulteriori as $ulteriore ) {
			$this->assertSame( 200, $this->scarica( $ulteriore )['stato'], 'Gli allegati non toccati si scaricano ancora.' );
		}

		$voci = $this->voci_di( $id );

		$this->assertSame( $registro, array_slice( $voci, 0, count( $registro ) ), 'Le voci di prima restano com\'erano.' );
		$this->assertCount( count( $registro ) + 2, $voci, 'Due voci in piu\': l\'allegato aggiunto, che il meccanismo comune registra da se\', e la sostituzione.' );
		$this->assertSame( 'allegato_aggiunto', $voci[ count( $registro ) ]['azione'] );
		$this->assertSame( array( 'allegato' => $esito['documento'] ), $voci[ count( $registro ) ]['dettagli'] );

		$voce = $voci[ count( $registro ) + 1 ];

		$this->assertSame( $esito['voce'], $voce['id'] );
		$this->assertSame( SEZIONE, $voce['sezione'] );
		$this->assertSame( Passaggi::AZIONE_OSCURAMENTO, $voce['azione'] );
		$this->assertSame( self::MOTIVO, $voce['motivazione'], 'Il motivo.' );
		$this->assertSame( $this->pubblicatore, $voce['utente'], 'Chi.' );
		$this->assertSame( '2041-10-05 08:00:00', $voce['istante'], 'Quando: le 10 nell\'ora del sito, le 8 in UTC come le scrive il registro.' );
		$this->assertSame( 'sha256:' . $this->impronte[ $id ], $voce['dettagli']['impronta_uscente'], 'L\'impronta dell\'uscente, calcolata qui.' );
		$this->assertSame( 'sha256:' . $attesa, $voce['dettagli']['impronta_entrante'], 'L\'impronta dell\'entrante, calcolata qui.' );
		$this->assertSame( Passaggi::CAUSA_DATI_ECCEDENTI, $voce['dettagli']['causa'] );
		$this->assertSame( DocumentiAtto::PRINCIPALE, $voce['dettagli']['posto'] );
		$this->assertSame( $principale, $voce['dettagli']['uscente'] );
		$this->assertSame( $esito['documento'], $voce['dettagli']['entrante'] );

		// Il primo ulteriore: l'entrante prende la sua posizione.
		$contenuto = $this->pdf( 'allegato oscurato' );
		$attesa    = hash( 'sha256', $contenuto );
		$esito     = $this->oscura( $id, $this->pubblicatore, $ulteriori[0], $contenuto, $this->richiesta() );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? implode( ' ', $esito->get_error_messages() ) : '' );
		$this->assertSame( array( $esito['documento'], $ulteriori[1] ), DocumentiAtto::ulteriori( $id ), 'L\'entrante prende la posizione dell\'uscente.' );
		$this->assertSame( $attesa, hash_file( 'sha256', (string) $this->scarica( $esito['documento'] )['percorso'] ) );
		$this->assertSame( 404, $this->scarica( $ulteriori[0] )['stato'], 'L\'allegato uscente non esce piu\'.' );
		$this->assertSame( 200, $this->scarica( $ulteriori[1] )['stato'], 'L\'altro si scarica ancora.' );

		$ultima = $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_OSCURAMENTO ) );
		$this->assertCount( 2, $ultima, 'Ogni sostituzione ha la sua voce.' );
		$this->assertSame( DocumentiAtto::ULTERIORE, $ultima[1]['dettagli']['posto'] );
		$this->assertSame( 'sha256:' . $attesa, $ultima[1]['dettagli']['impronta_entrante'] );
	}

	/**
	 * A-295: tre tentativi indipendenti rifiutati, e il documento resta quello (ALBO-12).
	 *
	 * Chi non pubblica, su un atto pubblicato e non scaduto; chi pubblica, su
	 * un atto annullato con la fine ancora futura; chi pubblica, su un atto
	 * scaduto con il compito pianificato fermo, quindi ancora pubblicato nella
	 * banca dati. Nessun file entrante resta. Il controllo positivo e' chi
	 * pubblica sullo stesso assetto prima della scadenza.
	 */
	public function test_a295_tre_attacchi(): void {
		$redige    = $this->pubblicato();
		$annullato = $this->pubblicato();
		$scaduto   = $this->pubblicato();

		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $annullato ) {
					return albo_pretorio_annulla( $annullato, 'Annullato in autotutela.' );
				}
			)
		);
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $annullato ), 'Precondizione: la fine dell\'annullato e\' futura.' );

		$casi = array(
			'chi redige soltanto, su un pubblicato' => array( $redige, $this->redattore, self::ISTANTE_OSCURAMENTO, 'albo_oscuramento_non_permesso' ),
			'chi pubblica, su un annullato'         => array( $annullato, $this->pubblicatore, self::ISTANTE_OSCURAMENTO, 'albo_atto_non_pubblicato' ),
			'chi pubblica, su uno scaduto'          => array( $scaduto, $this->pubblicatore, '2041-10-17 00:00:00', 'albo_atto_gia_scaduto' ),
		);

		foreach ( $casi as $quale => list( $atto, $utente, $istante, $codice ) ) {
			$this->fissa_orologi( $istante );

			$documento = (int) DocumentiAtto::principale( $atto );
			$prima     = $this->fotografia( $atto );

			$this->assertSame( array( $codice ), $this->codici( $this->oscura( $atto, $utente, $documento, $this->pdf( 'oscurato' ), $this->richiesta() ) ), $quale . ': rifiutata per la sua ragione.' );
			$this->assertSame( $prima, $this->fotografia( $atto ), $quale . ': l\'atto, i suoi documenti, il disco e il registro sono quelli di prima.' );
			$this->assertSame( $documento, DocumentiAtto::principale( $atto ), $quale . ': il documento resta quello.' );
		}

		$this->assertSame( 'publish', get_post_status( $scaduto ), 'Precondizione dello scaduto: nella banca dati e\' ancora pubblicato.' );

		// Controllo positivo: chi pubblica, sugli stessi atti pubblicati, prima della scadenza.
		$this->fissa_orologi( '2041-10-16 23:59:00' );

		foreach ( array( $redige, $scaduto ) as $atto ) {
			$esito = $this->oscura( $atto, $this->pubblicatore, (int) DocumentiAtto::principale( $atto ), $this->pdf( 'oscurato' ), $this->richiesta() );
			$this->assertIsArray( $esito, is_wp_error( $esito ) ? implode( ' ', $esito->get_error_messages() ) : '' );
		}
	}

	/**
	 * A-296: una sostituzione dichiarata per un'altra causa e' rifiutata (ALBO-09).
	 *
	 * Gli errori di contenuto si rettificano con un nuovo atto: la sola
	 * eccezione all'immodificabilita' e' l'oscuramento. Il controllo positivo
	 * e' la stessa sostituzione dichiarata come oscuramento.
	 */
	public function test_a296_altra_causa(): void {
		$id        = $this->pubblicato();
		$documento = (int) DocumentiAtto::principale( $id );
		$prima     = $this->fotografia( $id );

		foreach ( array( 'dati_inesatti', 'rettifica', '', null, array( Passaggi::CAUSA_DATI_ECCEDENTI ), 'DATI_ECCEDENTI' ) as $causa ) {
			$this->assertSame( array( 'albo_causa_non_oscuramento' ), $this->codici( $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'altro' ), $this->richiesta( array( 'causa' => $causa ) ) ) ), 'Causa ' . wp_json_encode( $causa ) . ': rifiutata.' );
		}

		$senza = $this->richiesta();
		unset( $senza['causa'] );
		$this->assertSame( array( 'albo_causa_non_oscuramento' ), $this->codici( $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'altro' ), $senza ) ), 'Senza causa: rifiutata.' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto e il disco sono quelli di prima.' );

		$esito = $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'oscurato' ), $this->richiesta() );
		$this->assertIsArray( $esito, 'Controllo positivo: per oscuramento riesce.' );
	}

	/**
	 * A-297: una richiesta con un dato estraneo, un campo per volta, e' rifiutata per intero (ALBO-08, ALBO-27).
	 *
	 * Il numero di registro da solo, l'anno da solo, la data di inizio da
	 * sola, la fine da sola, ogni dato della scheda da solo senza nessuna
	 * data. Nessuno cambia, e nemmeno il documento: la richiesta si rifiuta
	 * per intero. Il controllo positivo e' la sostituzione senza dati
	 * estranei.
	 */
	public function test_a297_dati_estranei_uno_per_volta(): void {
		$id        = $this->pubblicato();
		$documento = (int) DocumentiAtto::principale( $id );
		$prima     = $this->fotografia( $id );
		$estranei  = array(
			'numero'                  => 99,
			'anno'                    => 2040,
			'data_inizio'             => '2041-10-05',
			'post_date'               => '2041-10-05 10:00:00',
			'data_fine'               => '2041-12-31',
			'post_title'              => 'Oggetto cambiato',
			'post_status'             => 'draft',
			META_DATA_ADOZIONE        => '2041-01-01',
			META_NUMERO_PROPRIO       => '123',
			TASSONOMIA_TIPO_ATTO      => array( $this->voci['scelto'] ),
			TASSONOMIA_ORGANO         => array(),
			META_DOCUMENTO_PRINCIPALE => $documento,
			META_ALLEGATI_ULTERIORI   => array(),
		);

		$this->assertSame( array(), DatiAtto::mancanti( $id ), 'Precondizione: la scheda e\' completa.' );

		foreach ( $estranei as $campo => $valore ) {
			$esito = $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'oscurato' ), $this->richiesta( array( $campo => $valore ) ) );

			$this->assertSame( array( 'albo_richiesta_non_valida' ), $this->codici( $esito ), 'Campo ' . $campo . ': rifiutata per intero.' );
			$this->assertStringContainsString( (string) $campo, $esito->get_error_message(), 'Il rifiuto nomina il campo.' );
			$this->assertSame( $prima, $this->fotografia( $id ), 'Campo ' . $campo . ': niente e\' cambiato, documento compreso.' );
		}

		$esito = $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'oscurato' ), $this->richiesta() );
		$this->assertIsArray( $esito, 'Controllo positivo: senza dati estranei riesce.' );
	}

	/**
	 * A-298: la versione sostituita si apre in amministrazione solo a chi consulta o pubblica.
	 *
	 * Vale per ogni figlio dell'atto che i suoi dati non indicano, con o senza
	 * il segno della sostituzione, anche se il segno e' su due righe o guasto.
	 *
	 * Contiene proprio i dati che l'oscuramento toglie: chi redige soltanto non
	 * la apre, anche con un collegamento valido costruito per lui, e apre
	 * invece la versione entrante, cosi' che il rifiuto venga dalla
	 * sostituzione e non dal collegamento o dal permesso sull'atto.
	 */
	public function test_a298_versione_sostituita_riservata(): void {
		$id           = $this->pubblicato();
		$uscente      = (int) DocumentiAtto::principale( $id );
		$consultatore = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );

		set_current_screen( 'dashboard' );

		$this->assertSame( 200, $this->apri_in_amministrazione( $this->redattore, $uscente ), 'Precondizione: prima della sostituzione chi redige apre il documento.' );

		$esito = $this->oscura( $id, $this->pubblicatore, $uscente, $this->pdf( 'oscurato' ), $this->richiesta() );
		$this->assertIsArray( $esito );

		$this->assertSame( 404, $this->apri_in_amministrazione( $this->redattore, $uscente ), 'Chi redige soltanto non apre la versione sostituita.' );
		$this->assertSame( 200, $this->apri_in_amministrazione( $this->redattore, $esito['documento'] ), 'Apre la versione oscurata.' );
		$this->assertSame( 200, $this->apri_in_amministrazione( $this->pubblicatore, $uscente ), 'Chi pubblica apre la sostituita.' );
		$this->assertSame( 200, $this->apri_in_amministrazione( $consultatore, $uscente ), 'Chi consulta apre la sostituita.' );
		$this->assertSame( 200, $this->apri_in_amministrazione( $this->amministratore, $uscente ), 'L\'amministratore apre la sostituita.' );

		// Il rifiuto non dipende dal segno: con due righe, con un valore guasto, o senza segno, un figlio non indicato resta riservato.
		$secondo = $this->pubblicato();
		$guasto  = (int) DocumentiAtto::principale( $secondo );
		$this->assertIsArray( $this->oscura( $secondo, $this->pubblicatore, $guasto, $this->pdf( 'oscurato' ), $this->richiesta() ) );
		add_post_meta( $uscente, META_SOSTITUITO_DA, $esito['documento'] );
		update_post_meta( $guasto, META_SOSTITUITO_DA, 'non un numero' );

		$terzo = $this->pubblicato();
		$this->assertIsInt( $this->come( $this->pubblicatore, fn () => conformita_core_deposita_allegato( $terzo, $this->file_temporaneo( 'estraneo.pdf', $this->pdf( 'estraneo' ) ), array( 'origine' => 'percorso_locale' ) ) ) );
		$senza_segno = (int) max( $this->figli( $terzo ) );

		$this->assertNotContains( $senza_segno, array_merge( array( DocumentiAtto::principale( $terzo ) ), DocumentiAtto::ulteriori( $terzo ) ), 'Precondizione: il figlio non e\' indicato dai dati dell\'atto.' );
		$this->assertNull( DocumentiAtto::sostituito_da( $senza_segno ), 'Precondizione: e non ha il segno.' );

		foreach ( array(
			'segno su due righe' => $uscente,
			'segno guasto'       => $guasto,
			'figlio senza segno' => $senza_segno,
		) as $quale => $documento ) {
			$this->assertSame( 404, $this->apri_in_amministrazione( $this->redattore, $documento ), $quale . ': chi redige soltanto non lo apre.' );
			$this->assertSame( 200, $this->apri_in_amministrazione( $this->pubblicatore, $documento ), $quale . ': chi pubblica lo apre.' );
		}
	}

	/**
	 * A-299: tutto o niente, e i rifiuti prima di cominciare (ALBO-12).
	 *
	 * Un guasto sulla scrittura del dato del documento, uno sulla voce del
	 * registro e uno sul segno dell'uscente: ogni volta l'atto, il disco e il
	 * registro sono quelli di prima, e il file entrante non resta. Senza
	 * conferma, senza motivo, con un documento di un altro atto: rifiutata
	 * prima di depositare. Dopo ogni guasto la stessa sostituzione riesce.
	 */
	public function test_a299_tutto_o_niente(): void {
		global $wpdb;

		$id        = $this->pubblicato();
		$altro     = $this->pubblicato();
		$documento = (int) DocumentiAtto::principale( $id );
		$prima     = $this->fotografia( $id );

		$rifiuti = array(
			'senza conferma'           => array( $documento, $this->richiesta( array( 'conferma' => 'si' ) ), 'albo_conferma_mancante' ),
			'senza motivo'             => array( $documento, $this->richiesta( array( 'motivo' => "  \n " ) ), 'albo_motivo_mancante' ),
			'documento di un altro'    => array( (int) DocumentiAtto::principale( $altro ), $this->richiesta(), 'albo_documento_non_dell_atto' ),
			'documento che non c\'e\'' => array( 0, $this->richiesta(), 'albo_documento_non_dell_atto' ),
		);

		foreach ( $rifiuti as $quale => list( $documento_indicato, $richiesta, $codice ) ) {
			$this->assertSame( array( $codice ), $this->codici( $this->oscura( $id, $this->pubblicatore, $documento_indicato, $this->pdf( 'oscurato' ), $richiesta ) ), $quale . ': rifiutata.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $quale . ': niente e\' cambiato, disco compreso.' );
		}

		$guasti = array(
			'il dato del documento'  => array( $wpdb->postmeta, META_DOCUMENTO_PRINCIPALE ),
			'il segno dell\'uscente' => array( $wpdb->postmeta, META_SOSTITUITO_DA ),
			'la voce del registro'   => array( 'INSERT', Passaggi::AZIONE_OSCURAMENTO ),
		);

		foreach ( $guasti as $quale => $pezzi ) {
			$this->guasta_istruzioni( $pezzi );
			$esito = $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'oscurato' ), $this->richiesta() );
			$this->ripara();

			$this->assertContains( 'albo_documento_non_sostituito', $this->codici( $esito ), 'Guasto su ' . $quale . ': rifiutata.' );

			$dopo = $this->fotografia( $id );

			$this->assertSame( $this->senza_registro( $prima ), $this->senza_registro( $dopo ), 'Guasto su ' . $quale . ': l\'atto, i documenti e il disco sono quelli di prima.' );
			$this->assert_traccia_del_file_tolto( $prima['registro'], $dopo['registro'], 'Guasto su ' . $quale );

			$prima = $dopo;
		}

		$esito = $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'oscurato' ), $this->richiesta() );
		$this->assertIsArray( $esito, 'Controllo positivo: senza guasti riesce.' );
		$this->assertSame( array_merge( array( $documento ), array( $esito['documento'] ) ), $this->figli( $id ), 'I due documenti dell\'atto, e nessun altro.' );
	}

	/**
	 * A-300: durante la sostituzione nessun'altra scrittura dell'atto resta (ALBO-09).
	 *
	 * Un altro componente, agganciato alla scrittura del dato del documento,
	 * prova a cambiare l'atto. Con le funzioni di WordPress il guardiano
	 * rifiuta la scrittura e la sostituzione prosegue con l'atto com'era; con
	 * un'istruzione diretta alla banca dati la scrittura passa, e il controllo
	 * finale la trova e annulla tutto. In ogni caso l'atto resta con il solo
	 * documento cambiato, oppure com'era.
	 */
	public function test_a300_nessuna_altra_scrittura(): void {
		$intrusioni = array(
			'un dato della scheda, con WordPress'  => array(
				false,
				static function ( int $atto, int $uscente ): void {
					unset( $uscente );
					update_post_meta( $atto, META_DATA_ADOZIONE, '2041-01-01' );
				},
			),
			'il documento indietro, con WordPress' => array(
				false,
				static function ( int $atto, int $uscente ): void {
					update_post_meta( $atto, META_DOCUMENTO_PRINCIPALE, $uscente );
				},
			),
			'un dato della scheda, diretto'        => array(
				true,
				static function ( int $atto, int $uscente ): void {
					global $wpdb;

					unset( $uscente );
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- prova: la scrittura di un altro programma.
					$wpdb->update(
						$wpdb->postmeta,
						array( 'meta_value' => '2041-01-01' ),
						array(
							'post_id'  => $atto,
							'meta_key' => META_DATA_ADOZIONE,
						)
					);
					// phpcs:enable
				},
			),
			'il documento indietro, diretto'       => array(
				true,
				static function ( int $atto, int $uscente ): void {
					global $wpdb;

					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- prova: la scrittura di un altro programma.
					$wpdb->update(
						$wpdb->postmeta,
						array( 'meta_value' => (string) $uscente ),
						array(
							'post_id'  => $atto,
							'meta_key' => META_DOCUMENTO_PRINCIPALE,
						)
					);
					// phpcs:enable
				},
			),
		);

		foreach ( $intrusioni as $quale => list( $annulla, $intrusione ) ) {
			$id        = $this->pubblicato();
			$documento = (int) DocumentiAtto::principale( $id );
			$prima     = $this->fotografia( $id );
			$una_volta = false;
			$aggancio  = static function ( $meta_id, $oggetto, $chiave ) use ( &$una_volta, $intrusione, $documento ): void {
				unset( $meta_id );

				if ( ! $una_volta && META_DOCUMENTO_PRINCIPALE === $chiave ) {
					$una_volta = true;
					$intrusione( (int) $oggetto, $documento );
				}
			};

			add_action( 'updated_post_meta', $aggancio, 10, 3 );
			$esito = $this->oscura( $id, $this->pubblicatore, $documento, $this->pdf( 'oscurato' ), $this->richiesta() );
			remove_action( 'updated_post_meta', $aggancio, 10 );

			$this->assertTrue( $una_volta, $quale . ': precondizione, l\'intrusione e\' partita.' );

			if ( $annulla ) {
				$this->assertContains( 'albo_atto_cambiato_nel_passaggio', $this->codici( $esito ), $quale . ': il controllo finale la trova.' );
				$this->assertSame( $this->senza_registro( $prima ), $this->senza_registro( $this->fotografia( $id ) ), $quale . ': tutto e\' come prima.' );
				continue;
			}

			$this->assertIsArray( $esito, $quale . ': la scrittura e\' rifiutata e la sostituzione prosegue.' );
			$this->assertSame( $this->senza_sostituzione( $prima, META_DOCUMENTO_PRINCIPALE ), $this->senza_sostituzione( $this->fotografia( $id ), META_DOCUMENTO_PRINCIPALE ), $quale . ': cambia solo il documento.' );
			$this->assertSame( $esito['documento'], DocumentiAtto::principale( $id ), $quale . ': ed e\' l\'entrante.' );
		}
	}

	/**
	 * A-301: le due autorizzazioni leggono i dati dell'atto dalla banca dati, e una lettura fallita vale no (ALBO-12).
	 *
	 * Una richiesta che avesse in memoria i dati dell'atto di prima della
	 * sostituzione, chiusa nel frattempo da un'altra richiesta, non apre la
	 * versione uscente: ne' al pubblico ne' a chi redige soltanto. Se la
	 * lettura dei dati dell'atto non risponde, nemmeno l'entrante esce. Il
	 * controllo positivo e' la stessa richiesta senza guasto.
	 */
	public function test_a301_letture_dalla_banca_dati(): void {
		$id      = $this->pubblicato();
		$uscente = (int) DocumentiAtto::principale( $id );
		$esito   = $this->oscura( $id, $this->pubblicatore, $uscente, $this->pdf( 'oscurato' ), $this->richiesta() );

		$this->assertIsArray( $esito );

		set_current_screen( 'dashboard' );

		$avvelena = function () use ( $id, $uscente ): void {
			$dati = wp_cache_get( $id, 'post_meta' );

			if ( ! is_array( $dati ) ) {
				get_post_meta( $id );
				$dati = wp_cache_get( $id, 'post_meta' );
			}

			$this->assertIsArray( $dati, 'Precondizione: i dati dell\'atto sono in memoria.' );
			$dati[ META_DOCUMENTO_PRINCIPALE ] = array( (string) $uscente );
			wp_cache_set( $id, $dati, 'post_meta' );
			$this->assertSame( $uscente, DocumentiAtto::principale( $id ), 'Precondizione: la memoria ricorda il principale di prima.' );
		};

		$avvelena();
		$this->assertSame( 404, $this->scarica( $uscente )['stato'], 'Memoria di prima: il pubblico non scarica l\'uscente.' );
		$avvelena();
		$this->assertSame( 404, $this->apri_in_amministrazione( $this->redattore, $uscente ), 'Memoria di prima: chi redige soltanto non apre l\'uscente.' );

		wp_cache_delete( $id, 'post_meta' );

		$this->guasta_istruzioni( array( 'SELECT meta_key, meta_value', META_ALLEGATI_ULTERIORI ) );
		$pubblico = $this->scarica( $esito['documento'] )['stato'];
		$redige   = $this->apri_in_amministrazione( $this->redattore, $esito['documento'] );
		$this->ripara();

		$this->assertSame( 404, $pubblico, 'Lettura fallita: il pubblico non scarica nemmeno l\'entrante.' );
		$this->assertSame( 404, $redige, 'Lettura fallita: chi redige soltanto non apre nemmeno l\'entrante.' );

		$this->assertSame( 200, $this->scarica( $esito['documento'] )['stato'], 'Controllo positivo: senza guasto il pubblico scarica l\'entrante.' );
		$this->assertSame( 200, $this->apri_in_amministrazione( $this->redattore, $esito['documento'] ), 'Controllo positivo: e chi redige lo apre.' );
	}

	/**
	 * Quello che il riquadro disegna per l'utente indicato.
	 *
	 * @param int $atto_id Atto.
	 * @param int $utente  Utente.
	 * @return string
	 */
	private function riquadro( int $atto_id, int $utente ): string {
		return $this->come(
			$utente,
			static function () use ( $atto_id ) {
				clean_post_cache( $atto_id );

				ob_start();
				RiquadroPassaggi::mostra( get_post( $atto_id ) );

				return (string) ob_get_clean();
			}
		);
	}

	/**
	 * I rifiuti che il riquadro mostra all'utente indicato, prelevati.
	 *
	 * @param int $atto_id Atto.
	 * @param int $utente  Utente.
	 * @return array<string, string>
	 */
	private function rifiuti_del_riquadro( int $atto_id, int $utente ): array {
		return $this->come(
			$utente,
			static function () use ( $atto_id ) {
				return Rifiuti::preleva_per_utente( $atto_id );
			}
		);
	}

	/**
	 * Invia il modulo dell'oscuramento dal riquadro, con il file indicato.
	 *
	 * @param int                       $atto_id Atto.
	 * @param int                       $utente  Utente.
	 * @param array<string, mixed>      $campi   Campi del modulo.
	 * @param array<string, mixed>|null $file    Voce di `$_FILES`, o nulla.
	 * @return string L'indirizzo a cui il riquadro torna.
	 */
	private function oscura_dal_riquadro( int $atto_id, int $utente, array $campi, ?array $file ): string {
		$_FILES = null === $file ? array() : array( RiquadroPassaggi::CAMPO_FILE_OSCURATO => $file );

		try {
			return $this->invia_riquadro( $atto_id, $utente, array_merge( array( RiquadroPassaggi::CAMPO_PASSAGGIO => 'oscura' ), $campi ) );
		} finally {
			$_FILES = array();
		}
	}

	/**
	 * I campi completi del modulo, con le modifiche indicate (nullo toglie il campo).
	 *
	 * @param int                  $atto_id   Atto.
	 * @param int                  $documento Documento scelto.
	 * @param array<string, mixed> $altro     Modifiche.
	 * @return array<string, mixed>
	 */
	private function campi_oscuramento( int $atto_id, int $documento, array $altro = array() ): array {
		$campi = array_merge(
			array(
				RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO => (string) $documento,
				RiquadroPassaggi::CAMPO_MOTIVO_OSCURAMENTO => self::MOTIVO,
				RiquadroPassaggi::CAMPO_CONFERMA_OSCURAMENTO => (string) $atto_id,
			),
			$altro
		);

		return array_filter(
			$campi,
			static function ( $valore ): bool {
				return null !== $valore;
			}
		);
	}

	/**
	 * A-302: la parte dell'oscuramento compare solo a chi pubblica, su un atto pubblicato non ancora scaduto, senza scelte gia' fatte (ALBO-12).
	 *
	 * Si prova sul testo disegnato: la parte, un'opzione per documento con
	 * il suo posto, nessuna gia' scelta, il campo del file, il motivo, la
	 * conferma legata all'atto, il pulsante. Poi i casi in cui non deve
	 * comparire: chi redige soltanto, la fine passata con il compito
	 * pianificato fermo (lo stato resta pubblicato), un atto tolto, un atto
	 * annullato.
	 */
	public function test_a302_chi_vede_la_parte_dell_oscuramento(): void {
		$id         = $this->pubblicato( 'normativo', array( $this->pdf( 'allegato' ) ) );
		$principale = DocumentiAtto::principale( $id );
		$allegato   = DocumentiAtto::ulteriori( $id )[0];
		$html       = $this->riquadro( $id, $this->pubblicatore );

		$this->assertStringContainsString( 'Oscura i dati in piu&#039; di un documento', $html, 'Chi pubblica vede la parte.' );
		$this->assertStringContainsString( 'Documento principale: ' . get_the_title( $principale ), $html, 'Il principale, con il suo posto.' );
		$this->assertStringContainsString( 'Allegato 1: ' . get_the_title( $allegato ), $html, 'L\'allegato, con il suo posto.' );
		$this->assertSame( 2, substr_count( $html, 'name="' . RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO . '"' ), 'Un\'opzione per documento.' );
		$this->assertStringContainsString( 'value="' . $principale . '"', $html );
		$this->assertStringContainsString( 'value="' . $allegato . '"', $html );
		$this->assertStringNotContainsString( 'checked', $html, 'Nessun documento e nessuna conferma gia\' scelti.' );
		$this->assertStringContainsString( 'type="file" id="' . RiquadroPassaggi::CAMPO_FILE_OSCURATO . '"', $html, 'Il campo del file.' );
		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_MOTIVO_OSCURAMENTO . '"', $html, 'Il motivo.' );
		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_CONFERMA_OSCURAMENTO . '" value="' . $id . '"', $html, 'La conferma vale per questo atto.' );
		$this->assertStringContainsString( 'value="oscura">Sostituisci con la versione oscurata', $html, 'Il pulsante.' );

		$this->assertStringNotContainsString( 'name="' . RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO . '"', $this->riquadro( $id, $this->redattore ), 'Chi redige soltanto non la vede.' );

		// Il 17 ottobre la fine (16 ottobre) e' passata; il compito pianificato non e' girato.
		$this->fissa_orologi( '2041-10-17 08:00:00' );
		$this->assertSame( 'publish', get_post_status( $id ), 'Precondizione: lo stato e\' ancora pubblicato.' );
		$this->assertStringNotContainsString( 'name="' . RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO . '"', $this->riquadro( $id, $this->pubblicatore ), 'Fine passata: la parte non c\'e\'.' );
		$this->fissa_orologi( '2041-10-16 23:00:00' );
		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO . '"', $this->riquadro( $id, $this->pubblicatore ), 'Controllo positivo: l\'ultimo giorno la parte c\'e\'.' );
		$this->fissa_orologi( self::ISTANTE_OSCURAMENTO );

		$tolto = $this->pubblicato();
		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $tolto ) {
					return Passaggi::defiggi_atto( $tolto, 'dati_salute', 'Il documento riporta una diagnosi.' );
				}
			)
		);
		$this->assertStringNotContainsString( 'name="' . RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO . '"', $this->riquadro( $tolto, $this->pubblicatore ), 'Atto tolto: la parte non c\'e\'.' );

		$annullato = $this->pubblicato();
		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $annullato ) {
					return Passaggi::annulla_atto( $annullato, 'Atto pubblicato per errore.' );
				}
			)
		);
		$this->assertStringNotContainsString( 'name="' . RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO . '"', $this->riquadro( $annullato, $this->pubblicatore ), 'Atto annullato: la parte non c\'e\'.' );
	}

	/**
	 * A-303: dal riquadro ogni campo mancante o sbagliato ha il suo rifiuto, e l'atto resta identico (ALBO-12).
	 *
	 * Un caricamento vero non si puo' simulare nelle prove: il meccanismo
	 * comune accetta da un modulo solo un file arrivato con una richiesta
	 * HTTP. Il controllo positivo e' quindi il caso con tutti i campi giusti
	 * e un file del disco al posto del caricamento: l'unico rifiuto e' quello
	 * del meccanismo comune sull'origine del file, che arriva al deposito,
	 * cioe' dopo tutti gli altri controlli.
	 */
	public function test_a303_rifiuti_dal_riquadro(): void {
		$id         = $this->pubblicato();
		$principale = (int) DocumentiAtto::principale( $id );
		$altro      = $this->pubblicato();
		$estraneo   = (int) DocumentiAtto::principale( $altro );
		$prima      = $this->fotografia( $id );
		$file       = function (): array {
			return $this->file_temporaneo( 'oscurato.pdf', $this->pdf( 'oscurato' ) );
		};

		foreach ( array(
			'nessun documento scelto'    => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO => null ), $file(), 'albo_documento_non_scelto' ),
			'documento non valido'       => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO => 'principale' ), $file(), 'albo_documento_non_scelto' ),
			'nessun file'                => array( $this->pubblicatore, array(), null, 'albo_versione_oscurata_mancante' ),
			'file vuoto nel modulo'      => array(
				$this->pubblicatore,
				array(),
				array(
					'name'     => '',
					'tmp_name' => '',
					'type'     => '',
					'size'     => 0,
					'error'    => UPLOAD_ERR_NO_FILE,
				),
				'albo_versione_oscurata_mancante',
			),
			'file arrivato a meta\''     => array( $this->pubblicatore, array(), array_merge( $file(), array( 'error' => UPLOAD_ERR_PARTIAL ) ), 'albo_caricamento_non_riuscito' ),
			'senza conferma'             => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_CONFERMA_OSCURAMENTO => null ), $file(), 'albo_conferma_mancante' ),
			'conferma di un altro atto'  => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_CONFERMA_OSCURAMENTO => (string) $altro ), $file(), 'albo_conferma_mancante' ),
			'senza motivo'               => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_MOTIVO_OSCURAMENTO => '  ' ), $file(), 'albo_motivo_mancante' ),
			'documento di un altro atto' => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_DOCUMENTO_OSCURATO => (string) $estraneo ), $file(), 'albo_documento_non_dell_atto' ),
			'chi redige soltanto'        => array( $this->redattore, array(), $file(), 'albo_oscuramento_non_permesso' ),
			'gettone di un altro atto'   => array( $this->pubblicatore, array( RiquadroPassaggi::CAMPO_GETTONE => wp_create_nonce( RiquadroPassaggi::azione( $altro ) ) ), $file(), 'albo_modulo_non_riconosciuto' ),
		) as $caso => $dati ) {
			list( $utente, $modifiche, $voce, $codice ) = $dati;

			$url = $this->oscura_dal_riquadro( $id, $utente, $this->campi_oscuramento( $id, $principale, $modifiche ), $voce );

			$this->assertStringNotContainsString( RiquadroPassaggi::ESITO . '=oscurato', $url, $caso . ': nessun esito di riuscita.' );
			$this->assertArrayHasKey( $codice, $this->rifiuti_del_riquadro( $id, $utente ), $caso . ': il riquadro spiega il rifiuto.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': l\'atto resta identico, registro compreso.' );
		}

		$url     = $this->oscura_dal_riquadro( $id, $this->pubblicatore, $this->campi_oscuramento( $id, $principale ), $file() );
		$rifiuti = $this->rifiuti_del_riquadro( $id, $this->pubblicatore );

		$this->assertStringNotContainsString( RiquadroPassaggi::ESITO . '=oscurato', $url );
		$this->assertArrayHasKey( 'conformita_core_origine_incoerente', $rifiuti, 'Tutti i campi giusti: resta solo il rifiuto del file che non viene da un caricamento.' );
		$this->assertStringContainsString( 'caricamento HTTP', implode( ' ', $rifiuti ) );
		$this->assertSame( $principale, DocumentiAtto::principale( $id ), 'Il documento resta quello di prima.' );
		$this->assertSame( $this->senza_registro( $prima ), $this->senza_registro( $this->fotografia( $id ) ), 'L\'atto resta identico fuori dal registro.' );
	}

	/**
	 * A-304: il riquadro elenca le sostituzioni fatte, e il collegamento al documento di prima compare solo a chi lo puo' aprire (ALBO-12, ALBO-27).
	 *
	 * Dopo una sostituzione da codice, chi pubblica e chi redige soltanto
	 * vedono la riga con chi, il posto e il motivo; il collegamento
	 * "Apri la versione di prima" lo vede solo chi pubblica, e porta proprio
	 * al documento uscente. Lo stesso elenco resta su un atto poi tolto. Con il
	 * registro guasto il riquadro lo dice, invece di tacere.
	 */
	public function test_a304_elenco_delle_sostituzioni(): void {
		$id      = $this->pubblicato();
		$uscente = (int) DocumentiAtto::principale( $id );

		$this->assertStringNotContainsString( 'Documenti sostituiti con la versione oscurata', $this->riquadro( $id, $this->pubblicatore ), 'Prima della sostituzione l\'elenco non c\'e\'.' );

		$this->assertIsArray( $this->oscura( $id, $this->pubblicatore, $uscente, $this->pdf( 'oscurato' ), $this->richiesta() ) );

		$autore    = get_userdata( $this->pubblicatore )->display_name;
		$pubblica  = $this->riquadro( $id, $this->pubblicatore );
		$redige    = $this->riquadro( $id, $this->redattore );
		$indirizzo = esc_url(
			(string) $this->come(
				$this->pubblicatore,
				static function () use ( $uscente ) {
					return conformita_core_indirizzo_consegna_amministrativa( $uscente );
				}
			)
		);

		foreach ( array(
			'chi pubblica'        => $pubblica,
			'chi redige soltanto' => $redige,
		) as $chi => $html ) {
			$this->assertStringContainsString( 'Documenti sostituiti con la versione oscurata', $html, $chi . ': l\'elenco.' );
			$this->assertStringContainsString( esc_html( $autore ) . ', documento principale: ' . esc_html( self::MOTIVO ), $html, $chi . ': chi, il posto e il motivo.' );
		}

		$this->assertStringContainsString( 'href="' . $indirizzo . '"', $pubblica, 'Chi pubblica ha il collegamento al documento di prima.' );
		$this->assertStringContainsString( 'Apri la versione di prima', $pubblica );
		$this->assertStringNotContainsString( 'Apri la versione di prima', $redige, 'Chi redige soltanto non ha il collegamento.' );
		$this->assertSame( 200, $this->apri_in_amministrazione( $this->pubblicatore, $uscente ), 'E il collegamento si apre per chi lo vede.' );

		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					return Passaggi::defiggi_atto( $id, 'dati_salute', 'Il documento riporta una diagnosi.' );
				}
			)
		);
		$this->assertStringContainsString( esc_html( self::MOTIVO ), $this->riquadro( $id, $this->pubblicatore ), 'Sull\'atto tolto l\'elenco resta.' );

		$this->guasta_istruzioni( array( 'SELECT', 'conformita_core_registro' ) );
		$guasto = $this->riquadro( $id, $this->pubblicatore );
		$this->ripara();

		$this->assertStringContainsString( 'le sostituzioni per oscuramento non si leggono in questo momento', $guasto, 'Registro guasto: il riquadro lo dice.' );
	}

	/**
	 * La fotografia senza il registro, per i casi in cui il registro ha la traccia del file entrante tolto.
	 *
	 * @param array<string, mixed> $foto Fotografia.
	 * @return array<string, mixed>
	 */
	private function senza_registro( array $foto ): array {
		unset( $foto['registro'] );

		return $foto;
	}

	/**
	 * Il registro dopo una sostituzione fallita: quello di prima, piu' il file entrante aggiunto e tolto.
	 *
	 * Il meccanismo comune registra da se' ogni allegato aggiunto e tolto.
	 * Il file entrante si deposita prima della transazione, quindi la sua
	 * traccia resta anche quando la sostituzione si annulla: e' il racconto
	 * vero di quello che e' successo al disco, e la voce dell'oscuramento non
	 * c'e'.
	 *
	 * @param array<int, array<string, mixed>> $prima Registro di prima.
	 * @param array<int, array<string, mixed>> $dopo  Registro di dopo.
	 * @param string                           $quale Caso.
	 */
	private function assert_traccia_del_file_tolto( array $prima, array $dopo, string $quale ): void {
		$this->assertSame( $prima, array_slice( $dopo, 0, count( $prima ) ), $quale . ': le voci di prima restano com\'erano.' );
		$this->assertSame( array( 'allegato_aggiunto', 'allegato_eliminato' ), array_column( array_slice( $dopo, count( $prima ) ), 'azione' ), $quale . ': in piu\' solo il file entrante aggiunto e tolto.' );
		$this->assertSame( $dopo[ count( $prima ) ]['dettagli'], $dopo[ count( $prima ) + 1 ]['dettagli'], $quale . ': ed e\' lo stesso file.' );
	}
}
