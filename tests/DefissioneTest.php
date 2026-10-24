<?php
/**
 * La defissione anticipata di un atto pubblicato: righe A-235, A-237..A-241 e parte di A-242 (ALBO-22, ALBO-10, ALBO-09).
 *
 * Gli atti si portano a pubblicato con il passaggio vero e si tolgono
 * dall'albo con il passaggio vero, da codice e dal riquadro, con l'orologio
 * fissato. Ogni rifiuto ha accanto il suo controllo positivo. Il pubblico
 * dopo la defissione e' provato in `PaginaPubblicaTest`, ogni strada con lo
 * stato riportato a pubblicato in `ScadutiInvisibiliTest` (A-236), la
 * consultazione riservata in `ConsultazioneScadutiTest`, il rilancio
 * dell'importazione in `ImportazioneVeraTest`.
 *
 * I valori attesi sono scritti per esteso: 1 ottobre 2041 alle 9:30 piu' 15
 * giorni fa fine il 16 ottobre; la defissione avviene il 5 ottobre alle
 * 10:00, quindi la fine scritta e' il 4 ottobre.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\ChiusuraPubblicazione;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\RiquadroPassaggi;

use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TIPO;

/**
 * Defissione anticipata: causa, motivo, permesso, data, stato fermo, tutto o niente.
 */
class DefissioneTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'istante della defissione.
	 */
	private const ISTANTE_DEFISSIONE = '2041-10-05 10:00:00';

	/**
	 * La causa usata quando la causa non e' cio' che si prova.
	 */
	private const CAUSA = 'dati_inesatti';

	/**
	 * Il motivo usato quando il motivo non e' cio' che si prova.
	 */
	private const MOTIVO = 'Il nome del destinatario e\' sbagliato: si ripubblica corretto.';

	/**
	 * Il guasto attivo sulle istruzioni, o nullo.
	 *
	 * @var callable|null
	 */
	private $guasto = null;

	/**
	 * Assetto comune, e l'aggancio che guasta le istruzioni.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();

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
	 * Un atto pubblicato con il passaggio vero all'istante indicato, poi l'orologio sull'istante della defissione.
	 *
	 * @param string $istante Data e ora nel fuso del sito.
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function pubblicato_il( string $istante = '2041-10-01 09:30:00', string $oggetto = 'Atto di prova' ): int {
		$this->fissa_orologi( $istante );

		$id    = $this->atto_in_verifica( 'normativo', array( 'post_title' => $oggetto ) );
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->fissa_orologi( self::ISTANTE_DEFISSIONE );

		return $id;
	}

	/**
	 * Toglie dall'albo da codice, come l'utente indicato.
	 *
	 * @param int   $atto_id Atto.
	 * @param int   $utente  Utente.
	 * @param mixed $causa   Causa.
	 * @param mixed $motivo  Motivo.
	 * @return mixed
	 */
	private function defiggi( int $atto_id, int $utente, $causa = self::CAUSA, $motivo = self::MOTIVO ) {
		return $this->come(
			$utente,
			static function () use ( $atto_id, $causa, $motivo ) {
				return albo_pretorio_defiggi( $atto_id, $causa, $motivo );
			}
		);
	}

	/**
	 * Annulla da codice, come l'utente indicato.
	 *
	 * @param int   $atto_id Atto.
	 * @param int   $utente  Utente.
	 * @param mixed $motivo  Motivo.
	 * @return mixed
	 */
	private function annulla( int $atto_id, int $utente, $motivo = 'Atto annullato in autotutela.' ) {
		return $this->come(
			$utente,
			static function () use ( $atto_id, $motivo ) {
				return albo_pretorio_annulla( $atto_id, $motivo );
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
		$this->assertWPError( $esito, 'Il passaggio deve essere rifiutato.' );

		return $esito->get_error_codes();
	}

	/**
	 * La fotografia dell'atto senza cio' che la defissione cambia: stato, date di modifica, fine e registro.
	 *
	 * @param array<string, mixed> $foto Fotografia.
	 * @return array<string, mixed>
	 */
	private function senza_defissione( array $foto ): array {
		$chiave = conformita_core_chiave_fine_pubblicazione();

		unset( $foto['riga']['post_status'], $foto['riga']['post_modified'], $foto['riga']['post_modified_gmt'], $foto['registro'] );

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
	 * Un atto portato a pubblicato scrivendo lo stato, senza il passaggio: niente numero.
	 *
	 * @return int
	 */
	private function scavalcato(): int {
		$id = $this->atto_completo( 'normativo', array( 'post_title' => 'Atto scavalcato di prova' ) );

		$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $id, '2041-12-31' ), 'Precondizione: la fine e\' scritta.' );

		$this->scavalca_barriera(
			static function () use ( $id ) {
				wp_publish_post( $id );
			}
		);
		clean_post_cache( $id );

		$this->assertSame( 'publish', get_post_status( $id ), 'Precondizione: lo stato e\' pubblicato.' );
		$this->assertNull( Repertorio::di( $id ), 'Precondizione: non ha numero.' );

		return $id;
	}

	/**
	 * I motivi del rifiuto lasciati dal riquadro all'utente.
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
	 * La richiesta si ferma: restituisce il messaggio.
	 *
	 * @param callable $scrittura Scrittura.
	 * @param string   $caso      Caso, per i messaggi.
	 * @return string
	 */
	private function richiesta_fermata( callable $scrittura, string $caso ): string {
		try {
			$scrittura();
		} catch ( \WPDieException $fermata ) {
			return $fermata->getMessage();
		}

		$this->fail( $caso . ': la richiesta doveva fermarsi.' );
	}

	/**
	 * L'unica voce della defissione di un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function voce_defissione( int $atto_id ): array {
		$voci = $this->voci_di( $atto_id, array( 'azione' => Passaggi::AZIONE_DEFISSIONE ) );

		$this->assertCount( 1, $voci, 'Una voce di defissione.' );

		return $voci[0];
	}

	/**
	 * A-235: defissione senza causa, senza motivo, poi con causa e motivo, da codice e dal riquadro.
	 */
	public function test_a235_defissione(): void {
		$id     = $this->pubblicato_il();
		$numero = Repertorio::di( $id );
		$prima  = $this->fotografia( $id );

		$this->assertIsArray( $numero, 'Precondizione: l\'atto ha il numero.' );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'Precondizione: la fine pianificata.' );

		foreach ( array( '', null ) as $senza ) {
			$this->assertSame( array( 'albo_causa_non_ammessa' ), $this->codici( $this->defiggi( $id, $this->pubblicatore, $senza ) ), 'Causa ' . wp_json_encode( $senza ) . ': rifiutata.' );
		}

		foreach ( array( '', "  \n\t ", '<script></script>', null, array( 'motivo' ) ) as $vuoto ) {
			$this->assertSame( array( 'albo_motivo_mancante' ), $this->codici( $this->defiggi( $id, $this->pubblicatore, self::CAUSA, $vuoto ) ), 'Motivo ' . wp_json_encode( $vuoto ) . ': rifiutato.' );
		}

		// Dal riquadro: senza causa, poi con la causa e senza motivo.
		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO         => 'defiggi',
				RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => self::MOTIVO,
			)
		);

		$this->assertArrayHasKey( 'albo_causa_non_ammessa', $this->rifiuti_del_riquadro( $id, $this->pubblicatore ), 'Il riquadro spiega che manca la causa.' );

		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO         => 'defiggi',
				RiquadroPassaggi::CAMPO_CAUSA             => self::CAUSA,
				RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => '   ',
			)
		);

		$this->assertArrayHasKey( 'albo_motivo_mancante', $this->rifiuti_del_riquadro( $id, $this->pubblicatore ), 'Il riquadro spiega che manca il motivo.' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto resta pubblicato e identico, registro compreso.' );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertFalse( conformita_core_scaduto( $id ), 'E raggiungibile.' );

		// Con causa e motivo, da codice.
		$voce = $this->defiggi( $id, $this->pubblicatore, self::CAUSA, "Il nome del destinatario e' sbagliato.\nSi ripubblica <b>corretto</b>." );

		$this->assertIsInt( $voce, is_wp_error( $voce ) ? $voce->get_error_message() : '' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), 'Lo stato memorizzato e\' defisso.' );

		$dopo = $this->fotografia( $id );

		$this->assertSame( ChiusuraPubblicazione::DEFISSO, $dopo['riga']['post_status'], 'Nella banca dati.' );
		$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $id ), 'La fine e\' il giorno civile prima della defissione.' );
		$this->assertTrue( conformita_core_valida_fine_pubblicazione( conformita_core_fine_pubblicazione( $id ) ), 'Ed e\' una data valida.' );
		$this->assertTrue( conformita_core_scaduto( $id ), 'Per il meccanismo comune l\'atto e\' gia\' scaduto, adesso.' );
		$this->assertSame( $this->senza_defissione( $prima ), $this->senza_defissione( $dopo ), 'Inizio, numero, dati, voci e documenti sono quelli di prima.' );
		$this->assertSame( $numero, Repertorio::di( $id ), 'Il numero resta.' );
		$this->assertSame( '2041-10-01 09:30:00', $dopo['riga']['post_date'], 'L\'inizio resta un fatto.' );
		$this->assertSame( $prima['registro'], array_slice( $dopo['registro'], 0, count( $prima['registro'] ) ), 'Le voci di prima restano com\'erano.' );
		$this->assertCount( count( $prima['registro'] ) + 3, $dopo['registro'], 'Tre voci in piu\': la rimozione, la data di fine e la defissione.' );

		$rimozioni = $this->voci_di(
			$id,
			array(
				'azione'  => 'rimozione',
				'origine' => 'automatica',
			)
		);

		$this->assertCount( 1, $rimozioni, 'Il meccanismo comune registra il cambio di stato.' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, $rimozioni[0]['dettagli']['stato_nuovo'] );

		$fini = $this->voci_di(
			$id,
			array(
				'azione'  => 'modifica_fine_pubblicazione',
				'origine' => 'automatica',
			)
		);

		$this->assertNotSame( array(), $fini, 'Il meccanismo comune registra la data di fine cambiata.' );

		$defissione = $this->voce_defissione( $id );

		$this->assertSame( $voce, $defissione['id'], 'Il passaggio restituisce la voce della defissione.' );
		$this->assertSame( "Il nome del destinatario e' sbagliato.\nSi ripubblica corretto.", $defissione['motivazione'], 'Il motivo, ripulito dal codice, con il suo a capo.' );
		$this->assertSame( $this->pubblicatore, $defissione['utente'], 'Chi.' );
		$this->assertSame( '2041-10-05 08:00:00', $defissione['istante'], 'Quando: le 10 nell\'ora del sito, le 8 in UTC come le scrive il registro.' );
		$this->assertSame( SEZIONE, $defissione['sezione'] );
		$this->assertSame(
			array(
				'causa'            => self::CAUSA,
				'anno'             => 2041,
				'numero'           => $numero['numero'],
				'fine_pianificata' => '2041-10-16',
				'fine'             => '2041-10-04',
			),
			$defissione['dettagli'],
			'Causa, numero, anno, fine pianificata e fine scritta.'
		);

		$letta = Passaggi::defissione( $id );

		$this->assertIsArray( $letta );
		$this->assertSame( self::CAUSA, $letta['causa'] );
		$this->assertSame( '2041-10-16', $letta['fine_pianificata'] );

		// Dal riquadro, su un altro atto, con ogni causa dell'elenco.
		foreach ( array_keys( Passaggi::cause_defissione() ) as $indice => $causa ) {
			$this->fissa_orologi( self::ISTANTE_DEFISSIONE );

			$altro = $this->pubblicato_il( '2041-10-02 09:30:00', 'Atto ' . $causa );
			$url   = $this->invia_riquadro(
				$altro,
				$this->pubblicatore,
				array(
					RiquadroPassaggi::CAMPO_PASSAGGIO => 'defiggi',
					RiquadroPassaggi::CAMPO_CAUSA     => $causa,
					RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => 'Motivo ' . $indice . '.',
				)
			);

			$this->assertStringContainsString( RiquadroPassaggi::ESITO . '=defisso', $url, $causa . ': il riquadro torna con l\'esito.' );
			$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $altro ), $causa );
			$this->assertSame( $causa, $this->voce_defissione( $altro )['dettagli']['causa'], $causa . ': nel registro.' );
			$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $altro ), $causa . ': la fine.' );
		}
	}

	/**
	 * A-237: una causa fuori elenco non toglie un atto, una dell'elenco si'.
	 */
	public function test_a237_causa_fuori_elenco(): void {
		$id    = $this->pubblicato_il();
		$prima = $this->fotografia( $id );

		$this->assertSame( array( 'dati_salute', 'senza_norma', 'dati_inesatti', 'ordine_autorita' ), array_keys( Passaggi::cause_defissione() ), 'Le quattro cause dell\'elenco.' );

		foreach ( array(
			'volonta dell\'amministrazione' => 'volonta_amministrazione',
			'causa scritta a mano'          => 'Abbiamo deciso di toglierlo prima',
			'causa che non e\' testo'       => array( 'dati_salute' ),
			'numero'                        => 1,
			'chiave con lo spazio'          => 'dati_salute ',
			'maiuscole'                     => 'DATI_SALUTE',
		) as $caso => $causa ) {
			$this->assertSame( array( 'albo_causa_non_ammessa' ), $this->codici( $this->defiggi( $id, $this->pubblicatore, $causa ) ), $caso . '.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': l\'atto resta identico.' );
		}

		$this->assertSame( array( 'albo_causa_oscuramento' ), $this->codici( $this->defiggi( $id, $this->pubblicatore, Passaggi::CAUSA_DATI_ECCEDENTI ) ), 'I dati eccedenti si oscurano, non si tolgono.' );
		$this->assertStringContainsString( 'sostituendo il documento', $this->messaggi( $this->defiggi( $id, $this->pubblicatore, Passaggi::CAUSA_DATI_ECCEDENTI ) ), 'Il rifiuto rinvia alla sostituzione.' );

		// Dal riquadro, la sola volonta' dell'amministrazione.
		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO         => 'defiggi',
				RiquadroPassaggi::CAMPO_CAUSA             => 'volonta_amministrazione',
				RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => 'L\'amministrazione ritiene sufficiente la pubblicazione fin qui.',
			)
		);

		$this->assertArrayHasKey( 'albo_causa_non_ammessa', $this->rifiuti_del_riquadro( $id, $this->pubblicatore ) );
		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto resta pubblicato, con la fine invariata e nessuna voce nuova.' );
		$this->assertFalse( conformita_core_scaduto( $id ), 'E raggiungibile.' );

		// Controllo positivo: lo stesso atto, con una causa dell'elenco.
		$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore, 'senza_norma' ) );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ) );
	}

	/**
	 * A-238: chi toglie un atto, e quale atto si toglie.
	 */
	public function test_a238_chi_e_da_dove(): void {
		$id    = $this->pubblicato_il();
		$altro = $this->pubblicato_il( '2041-10-02 09:30:00', 'Altro atto' );
		$prima = $this->fotografia( $id );

		$senza_defiggere = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) ) );
		$solo_defiggere  = $this->utente_con( array( Permessi::CHIAVE_DEFISSIONE ) );
		$consultatore    = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );

		$this->assertTrue( user_can( $senza_defiggere, (string) Permessi::mappa()['publish_posts'] ), 'Precondizione: pubblica.' );

		foreach ( array(
			'chi redige'                   => $this->redattore,
			'chi consulta gli scaduti'     => $consultatore,
			'chi pubblica senza defiggere' => $senza_defiggere,
		) as $chi => $utente ) {
			$this->assertSame( array( 'albo_defissione_non_permessa' ), $this->codici( $this->defiggi( $id, $utente ) ), $chi . ', da codice.' );

			$this->invia_riquadro(
				$id,
				$utente,
				array(
					RiquadroPassaggi::CAMPO_PASSAGGIO => 'defiggi',
					RiquadroPassaggi::CAMPO_CAUSA     => self::CAUSA,
					RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => self::MOTIVO,
				)
			);

			$this->assertArrayHasKey( 'albo_defissione_non_permessa', $this->rifiuti_del_riquadro( $id, $utente ), $chi . ', dal riquadro.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $chi . ': l\'atto resta identico, registro compreso.' );
		}

		// Che cosa: solo un atto pubblicato dal passaggio, con il numero, non ancora scaduto.
		$bozza       = $this->atto_completo();
		$in_verifica = $this->atto_in_verifica();
		$scavalcato  = $this->scavalcato();
		$annullato   = $this->pubblicato_il( '2041-10-03 09:30:00', 'Atto annullato' );
		$scaduto     = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto' );
		$articolo    = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->assertIsInt( $this->annulla( $annullato, $this->pubblicatore ), 'Precondizione: annullato.' );
		$this->assertSame( 'publish', get_post_status( $scaduto ), 'Precondizione: lo scaduto e\' ancora pubblicato nella banca dati.' );
		$this->assertSame( '2041-09-16', conformita_core_fine_pubblicazione( $scaduto ), 'Precondizione: la sua fine e\' passata.' );
		$this->assertTrue( conformita_core_scaduto( $scaduto ), 'Precondizione: scaduto per il meccanismo comune.' );

		$fotografie = array();

		foreach ( array( $bozza, $in_verifica, $scavalcato, $annullato, $scaduto ) as $atto ) {
			$fotografie[ $atto ] = $this->fotografia( $atto );
		}

		$this->assertSame( array( 'albo_atto_non_pubblicato' ), $this->codici( $this->defiggi( $bozza, $this->amministratore ) ), 'Bozza.' );
		$this->assertSame( array( 'albo_atto_non_pubblicato' ), $this->codici( $this->defiggi( $in_verifica, $this->amministratore ) ), 'In verifica.' );
		$this->assertSame( array( 'albo_atto_non_pubblicato' ), $this->codici( $this->defiggi( $annullato, $this->amministratore ) ), 'Annullato.' );
		$this->assertSame( array( 'albo_atto_senza_numero' ), $this->codici( $this->defiggi( $scavalcato, $this->amministratore ) ), 'Pubblicato scavalcando il passaggio.' );
		$this->assertSame( array( 'albo_atto_gia_scaduto' ), $this->codici( $this->defiggi( $scaduto, $this->amministratore ) ), 'Scaduto: non c\'e\' niente da anticipare.' );
		$this->assertSame( array( 'albo_non_un_atto' ), $this->codici( $this->defiggi( $articolo, $this->amministratore ) ), 'Un articolo.' );
		$this->assertSame( array( 'albo_non_un_atto' ), $this->codici( $this->defiggi( 999999999, $this->amministratore ) ), 'Un numero mai dato.' );

		foreach ( $fotografie as $atto => $foto ) {
			$this->assertSame( $foto, $this->fotografia( $atto ), 'Atto ' . $atto . ': identico.' );
		}

		$this->assertSame( 'publish', get_post_status( $scaduto ), 'Lo stato dello scaduto e\' quello di prima.' );
		$this->assertSame( '2041-09-16', conformita_core_fine_pubblicazione( $scaduto ), 'E la data anche.' );

		// L'ultimo giorno di pubblicazione si toglie ancora: fino a mezzanotte e' in vista.
		$ultimo = $this->pubblicato_il( '2041-09-20 09:30:00', 'Atto all\'ultimo giorno' );

		$this->assertSame( '2041-10-05', conformita_core_fine_pubblicazione( $ultimo ), 'Precondizione: oggi e\' il suo ultimo giorno.' );
		$this->assertIsInt( $this->defiggi( $ultimo, $this->pubblicatore ), 'L\'atto all\'ultimo giorno si toglie.' );

		// Controlli positivi: chi ha il solo permesso di defiggere, l'amministratore.
		$this->assertFalse( user_can( $solo_defiggere, (string) Permessi::mappa()['publish_posts'] ), 'Precondizione: non pubblica.' );
		$this->assertIsInt( $this->defiggi( $id, $solo_defiggere ), 'Chi ha il solo permesso di defiggere toglie l\'atto.' );
		$this->assertIsInt( $this->defiggi( $altro, $this->amministratore ), 'L\'amministratore toglie l\'atto.' );

		// Gia' defisso.
		$defisso = $this->fotografia( $id );

		$this->assertSame( array( 'albo_atto_gia_defisso' ), $this->codici( $this->defiggi( $id, $this->amministratore ) ), 'Gia\' defisso.' );
		$this->assertSame( $defisso, $this->fotografia( $id ), 'La seconda defissione non scrive niente.' );
	}

	/**
	 * A-239: il defisso e' fermo, per tutti e da ogni ingresso; solo l'annullamento ne esce.
	 */
	public function test_a239_defisso_fermo(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id        = $this->pubblicato_il();
		$controllo = $this->pubblicato_il( '2041-10-01 10:00:00', 'Atto di controllo' );

		$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore ) );

		$prima         = $this->fotografia( $id );
		$altro_organo  = $this->voce( TASSONOMIA_ORGANO, 'Consiglio' );
		$this->scritti = array();

		foreach ( array( $this->redattore, $this->pubblicatore, $this->amministratore ) as $utente ) {
			$this->come(
				$utente,
				function () use ( $id ) {
					foreach ( array( 'publish', 'draft', 'pending', 'auto-draft', 'private', 'future', 'trash', ChiusuraPubblicazione::DEFISSO ) as $stato ) {
						wp_update_post(
							array(
								'ID'          => $id,
								'post_status' => $stato,
							)
						);
					}

					wp_update_post( array( 'ID' => $id ) );
					wp_update_post(
						array(
							'ID'         => $id,
							'post_title' => 'Oggetto cambiato',
						)
					);
					wp_update_post(
						array(
							'ID'            => $id,
							'post_date'     => '2041-09-01 08:00:00',
							'post_date_gmt' => '2041-09-01 06:00:00',
							'edit_date'     => true,
						)
					);
					wp_update_post(
						array(
							'ID'         => $id,
							'meta_input' => array( 'albo_pretorio_numero' => '999' ),
						)
					);

					$this->assertFalse( wp_trash_post( $id ), 'Non va nel cestino.' );
					$this->assertFalse( wp_delete_post( $id, true ), 'Non si cancella.' );
					$this->assertFalse( update_post_meta( $id, conformita_core_chiave_fine_pubblicazione(), '2041-12-31' ), 'La data di fine non cambia.' );

					$this->assertSame( array( 'albo_atto_non_in_verifica' ), albo_pretorio_rimanda_in_bozza( $id, 'Motivo.' )->get_error_codes() );
					$this->assertSame( array( 'albo_atto_non_in_verifica' ), albo_pretorio_pubblica( $id, array( 'conferma' => true ) )->get_error_codes() );
					$this->assertSame( array( 'albo_atto_gia_defisso' ), albo_pretorio_defiggi( $id, 'senza_norma', 'Di nuovo.' )->get_error_codes() );

					// Dalla schermata di WordPress.
					set_current_screen( 'post' );

					$_POST = array(
						'action'               => 'editpost',
						'post_ID'              => (string) $id,
						'post_type'            => TIPO,
						'post_title'           => 'Oggetto dalla schermata',
						'original_post_status' => ChiusuraPubblicazione::DEFISSO,
						'post_status'          => 'publish',
						'_wpnonce'             => wp_create_nonce( 'update-post_' . $id ),
					);

					try {
						edit_post();
					} catch ( \WPDieException $fermato ) {
						unset( $fermato );
					} finally {
						$_POST = array();
					}

					$this->assertArrayHasKey( 'albo_atto_defisso', Rifiuti::preleva_per_utente( $id ), 'Il rifiuto della schermata dice che l\'atto e\' tolto dall\'albo.' );
				}
			);

			$this->assertSame( $prima, $this->fotografia( $id ), 'Niente cambia, utente ' . $utente . '.' );
		}

		$this->assertSame( array(), $this->stati_scritti( $id ), 'Nessuna scrittura della riga: ogni richiesta si e\' fermata prima.' );

		// Le funzioni che scrivono la riga da se', le voci e un'istruzione dritta.
		foreach ( array(
			'wp_publish_post'              => static function () use ( $id ) {
				wp_publish_post( $id );
			},
			'set_post_type'                => static function () use ( $id ) {
				set_post_type( $id, 'post' );
			},
			'voce dell\'organo'            => static function () use ( $id, $altro_organo ) {
				wp_set_object_terms( $id, array( $altro_organo ), TASSONOMIA_ORGANO );
			},
			'istruzione dritta sulla riga' => static function () use ( $id ) {
				global $wpdb;

				$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la banca dati scritta come la lascerebbe un altro programma.
			},
		) as $caso => $scrittura ) {
			$this->assertStringContainsString( 'tolto dall\'albo', html_entity_decode( $this->richiesta_fermata( $scrittura, $caso ), ENT_QUOTES ), $caso . ': il rifiuto nomina la defissione.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': niente cambia.' );
		}

		// Una cancellazione dritta della data di fine non tocca il defisso.
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, conformita_core_chiave_fine_pubblicazione() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la banca dati scritta come la lascerebbe un altro programma.
		$this->assertSame( $prima, $this->fotografia( $id ), 'Cancellazione dritta della data di fine: niente cambia.' );

		// I documenti collegati all'atto. Il documento come contenuto a se', nella libreria dei media, e' di ALBO-09 e ALBO-12.
		$documento = (int) \AlboPretorioPa\DocumentiAtto::principale( $id );

		$this->assertGreaterThan( 0, $documento, 'Precondizione: l\'atto ha il documento principale.' );
		$this->come(
			$this->amministratore,
			function () use ( $id, $documento ) {
				$this->assertFalse( update_post_meta( $id, \AlboPretorioPa\META_DOCUMENTO_PRINCIPALE, $documento + 1000 ), 'Il documento principale non si sostituisce.' );
				$this->assertFalse( delete_post_meta( $id, \AlboPretorioPa\META_DOCUMENTO_PRINCIPALE ), 'Il documento principale non si stacca.' );

				// Dall'interfaccia di programmazione: il tipo non ci e' (A-25), e la rotta non esiste.
				$richiesta = new \WP_REST_Request( 'POST', '/wp/v2/' . TIPO . '/' . $id );
				$richiesta->set_param( 'title', 'Oggetto dall\'interfaccia' );
				$this->assertSame( 404, rest_do_request( $richiesta )->get_status(), 'Nessuna rotta di scrittura.' );
			}
		);
		$this->assertSame( $prima, $this->fotografia( $id ), 'Documenti e interfaccia di programmazione: niente cambia.' );
		$this->assertSame( $documento, \AlboPretorioPa\DocumentiAtto::principale( $id ), 'Il documento resta quello.' );

		// La proroga per disservizio sul giorno della defissione allunga il controllo e non il defisso.
		$this->fissa_orologi( '2041-10-06 10:00:00' );

		$esito = $this->come(
			$this->pubblicatore,
			static function () {
				return albo_pretorio_proroga(
					array(
						'giorni'       => array( '2041-10-05' ),
						'attestazione' => 'Attestazione prot. 12 del 6 ottobre',
					)
				);
			}
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $controllo ), 'Il controllo e\' prorogato.' );
		$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $id ), 'Il defisso no.' );
		$prorogati = array_map( 'intval', array_keys( (array) ( $esito['atti'] ?? array() ) ) );
		$this->assertContains( $controllo, $prorogati, 'Controllo positivo: la proroga elenca l\'atto prorogato.' );
		$this->assertNotContains( $id, $prorogati, 'E non elenca il defisso.' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Proroga: il defisso non cambia, voci comprese.' );
	}

	/**
	 * A-240: tutto o niente, con un pezzo della defissione che fallisce per volta.
	 */
	public function test_a240_tutto_o_niente(): void {
		global $wpdb;

		$registro = $wpdb->prefix . 'conformita_core_registro';
		$chiave   = conformita_core_chiave_fine_pubblicazione();
		$guasti   = array(
			'scrittura della data di fine'       => array(
				'albo_fine_non_scritta',
				function () use ( $wpdb, $chiave ) {
					$this->guasta_istruzioni( array( 'UPDATE `' . $wpdb->postmeta . '`', "'" . $chiave . "'", "'2041-10-04'" ) );
				},
			),
			'scrittura dello stato'              => array(
				'albo_stato_non_scritto',
				function () use ( $wpdb ) {
					$this->guasta_istruzioni( array( 'UPDATE `' . $wpdb->posts . '`', "'" . ChiusuraPubblicazione::DEFISSO . "'" ) );
				},
			),
			'voce automatica della rimozione'    => array(
				'albo_voce_automatica_mancante',
				function () use ( $registro ) {
					$this->guasta_istruzioni( array( 'INSERT INTO `' . $registro . '`', "'rimozione'" ) );
				},
			),
			'voce automatica della data di fine' => array(
				'albo_voce_automatica_mancante',
				function () use ( $registro ) {
					$this->guasta_istruzioni( array( 'INSERT INTO `' . $registro . '`', "'modifica_fine_pubblicazione'" ) );
				},
			),
			'voce della defissione'              => array(
				'albo_voce_non_scritta',
				function () use ( $registro ) {
					$this->guasta_istruzioni( array( 'INSERT INTO `' . $registro . '`', "'" . Passaggi::AZIONE_DEFISSIONE . "'" ) );
				},
			),
		);

		foreach ( $guasti as $caso => list( $codice, $guasta ) ) {
			$id    = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto ' . $caso );
			$prima = $this->fotografia( $id );

			$guasta();

			try {
				$esito = $this->defiggi( $id, $this->pubblicatore );
			} finally {
				$this->ripara();
			}

			$codici = $this->codici( $esito );

			$this->assertSame( 'albo_atto_non_defisso', $codici[0], $caso . ': il primo messaggio dice che l\'atto resta pubblicato.' );
			$this->assertContains( $codice, $codici, $caso . ': con il codice del pezzo fallito.' );
			$this->assertSame( 'publish', get_post_status( $id ), $caso . ': pubblicato anche per chi legge dalla memoria.' );
			$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), $caso . ': con la fine pianificata.' );
			$this->assertFalse( conformita_core_scaduto( $id ), $caso . ': raggiungibile.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': identico a prima, registro compreso.' );

			$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore ), $caso . ': tolto il guasto, lo stesso atto si toglie.' );
			$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ) );
		}

		// Le tabelle che non annullano: niente comincia.
		$id    = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto delle tabelle' );
		$prima = $this->fotografia( $id );

		foreach ( array( $registro, $wpdb->posts, $wpdb->postmeta ) as $tabella ) {
			$rispondi = static function ( $istruzione ) use ( $tabella ) {
				return str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$tabella}' AND t.TABLE_NAME IN (", (string) $istruzione );
			};

			add_filter( 'query', $rispondi );

			try {
				$esito = $this->defiggi( $id, $this->pubblicatore );
			} finally {
				remove_filter( 'query', $rispondi );
			}

			$this->assertContains( 'albo_tabelle_non_transazionali', $this->codici( $esito ), $tabella );
			$this->assertSame( $prima, $this->fotografia( $id ), $tabella . ': niente cambia.' );
		}

		$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore ), 'Controllo positivo: con le tabelle che annullano, lo stesso atto si toglie.' );
	}

	/**
	 * A-240: finite le scritture, durante il controllo finale e la conferma, nessuna concessione resta sull'atto.
	 */
	public function test_a240_nessuna_concessione_durante_i_controlli(): void {
		global $wpdb;

		$chiave  = conformita_core_chiave_fine_pubblicazione();
		$tabella = $wpdb->prefix . 'conformita_core_registro';

		foreach ( array( 'controlli', 'conferma' ) as $caso ) {
			$id = $this->pubblicato_il();

			$e_il_momento = 'controlli' === $caso
				? static function ( string $istruzione ) use ( $wpdb, $id ): bool {
					return 1 === preg_match( '/^\s*SELECT \* FROM `?' . preg_quote( $wpdb->posts, '/' ) . '`? WHERE ID = ' . $id . '\s*$/', $istruzione );
				}
				: static function ( string $istruzione ): bool {
					return 1 === preg_match( '/^\s*(COMMIT|RELEASE SAVEPOINT)/i', $istruzione );
				};

			$scritte    = false;
			$tentativi  = array();
			$intromette = static function ( $istruzione ) use ( $wpdb, $tabella, $id, $chiave, $e_il_momento, &$scritte, &$tentativi ) {
				if ( ! is_string( $istruzione ) || array() !== $tentativi ) {
					return $istruzione;
				}

				// L'ultima scrittura della defissione e' la sua voce nel registro.
				if ( 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Passaggi::AZIONE_DEFISSIONE . "'" ) ) {
					$scritte = true;

					return $istruzione;
				}

				if ( $scritte && $e_il_momento( $istruzione ) ) {
					$tentativi = array( 'in corso' );
					$tentativi = array(
						'riscrive' => update_post_meta( $id, $chiave, '2041-12-31' ),
						'diretta'  => $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la scrittura dell'aggancio.
							$wpdb->postmeta,
							array( 'meta_value' => '2041-12-31' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- la scrittura dell'aggancio.
							array(
								'post_id'  => $id,
								'meta_key' => $chiave, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- la scrittura dell'aggancio.
							)
						),
					);
				}

				return $istruzione;
			};

			add_filter( 'query', $intromette );

			try {
				$esito = $this->defiggi( $id, $this->pubblicatore );
			} finally {
				remove_filter( 'query', $intromette );
			}

			clean_post_cache( $id );

			$this->assertArrayHasKey( 'riscrive', $tentativi, $caso . ': precondizione, l\'aggancio ha provato.' );
			$this->assertFalse( $tentativi['riscrive'], $caso . ': la riscrittura con le funzioni di WordPress e\' rifiutata.' );
			$this->assertSame( 0, $tentativi['diretta'], $caso . ': la riscrittura diretta non tocca niente.' );
			$this->assertIsInt( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
			$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), $caso . ': l\'atto e\' defisso.' );
			$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $id ), $caso . ': con la fine del 4 ottobre.' );
		}
	}

	/**
	 * A-240: la data di fine cambia fra la lettura dei controlli e la fotografia di partenza.
	 *
	 * Come una proroga confermata da un'altra richiesta in quell'istante. La
	 * voce non deve registrare come fine pianificata una data che non e' piu'
	 * quella dell'atto: la defissione e' rifiutata e niente e' scritto.
	 */
	public function test_a240_fine_cambiata_prima_della_fotografia(): void {
		global $wpdb;

		$id     = $this->pubblicato_il();
		$chiave = conformita_core_chiave_fine_pubblicazione();

		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'Precondizione: la fine letta dai controlli.' );

		$fatto      = false;
		$intromette = function ( $istruzione ) use ( $wpdb, $id, $chiave, &$fatto ) {
			if ( $fatto || ! is_string( $istruzione ) || 1 !== preg_match( '/^\s*SELECT meta_id, meta_key, meta_value FROM `?' . preg_quote( $wpdb->postmeta, '/' ) . '`? WHERE post_id = ' . $id . ' /', $istruzione ) ) {
				return $istruzione;
			}

			$fatto = true;
			$this->scavalca_barriera(
				static function () use ( $wpdb, $id, $chiave ) {
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la proroga di un'altra richiesta.
						$wpdb->postmeta,
						array( 'meta_value' => '2041-10-17' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- la proroga di un'altra richiesta.
						array(
							'post_id'  => $id,
							'meta_key' => $chiave, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- la proroga di un'altra richiesta.
						)
					);
				}
			);

			return $istruzione;
		};

		add_filter( 'query', $intromette );

		try {
			$esito = $this->defiggi( $id, $this->pubblicatore );
		} finally {
			remove_filter( 'query', $intromette );
		}

		clean_post_cache( $id );

		$this->assertTrue( $fatto, 'Precondizione: la data e\' cambiata prima della fotografia.' );
		$this->assertSame( array( 'albo_atto_non_defisso', 'albo_atto_cambiato_prima_del_passaggio' ), $this->codici( $esito ), 'Rifiutata: l\'atto non e\' piu\' quello controllato.' );
		$this->assertSame( 'publish', get_post_status( $id ), 'L\'atto resta pubblicato.' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ), 'Con la fine della proroga.' );
		$this->assertSame( array(), $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_DEFISSIONE ) ), 'Nessuna voce di defissione.' );
	}

	/**
	 * A-241: un atto defisso si annulla con il motivo, e resta fuori dalla vista.
	 */
	public function test_a241_annullamento_da_defisso(): void {
		$id     = $this->pubblicato_il();
		$numero = Repertorio::di( $id );

		$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore ) );

		$defissione = $this->voce_defissione( $id );
		$prima      = $this->fotografia( $id );

		foreach ( array( '', '   ', null ) as $vuoto ) {
			$this->assertSame( array( 'albo_motivo_mancante' ), $this->codici( $this->annulla( $id, $this->pubblicatore, $vuoto ) ), 'Motivo ' . wp_json_encode( $vuoto ) . ': rifiutato.' );
		}

		$this->assertSame( $prima, $this->fotografia( $id ), 'Senza motivo l\'atto resta defisso e identico.' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ) );

		$senza_annullare = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) ) );

		$this->assertSame( array( 'albo_annullamento_non_permesso' ), $this->codici( $this->annulla( $id, $senza_annullare ) ), 'Chi pubblica senza il permesso non annulla.' );
		$this->assertSame( $prima, $this->fotografia( $id ) );

		// Con il motivo.
		$voce = $this->annulla( $id, $this->pubblicatore, 'Annullato in autotutela.' );

		$this->assertIsInt( $voce, is_wp_error( $voce ) ? $voce->get_error_message() : '' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ), 'Lo stato e\' annullato.' );
		$this->assertSame( $numero, Repertorio::di( $id ), 'Il numero resta.' );
		$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $id ), 'La fine resta quella della defissione.' );
		$this->assertTrue( conformita_core_scaduto( $id ), 'E l\'atto resta fuori dalla vista.' );

		$dopo = $this->fotografia( $id );

		$this->assertSame( '2041-10-01 09:30:00', $dopo['riga']['post_date'], 'L\'inizio resta.' );
		$this->assertSame( $prima['registro'], array_slice( $dopo['registro'], 0, count( $prima['registro'] ) ), 'Le voci di prima restano com\'erano, la defissione compresa.' );
		$this->assertSame( $defissione, $this->voce_defissione( $id ), 'La voce della defissione e\' identica.' );
		$this->assertCount( count( $prima['registro'] ) + 2, $dopo['registro'], 'Due voci in piu\': il cambio di stato e l\'annullamento.' );

		$cambi = $this->voci_di(
			$id,
			array(
				'azione'  => 'cambio_stato',
				'origine' => 'automatica',
			)
		);

		$cambi = array_values(
			array_filter(
				$cambi,
				static function ( $voce ) {
					return ChiusuraPubblicazione::DEFISSO === ( $voce['dettagli']['stato_precedente'] ?? null );
				}
			)
		);

		$this->assertCount( 1, $cambi, 'Il meccanismo comune registra il cambio di stato.' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, $cambi[0]['dettagli']['stato_precedente'] );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $cambi[0]['dettagli']['stato_nuovo'] );

		$annullamenti = $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_ANNULLAMENTO ) );

		$this->assertCount( 1, $annullamenti );
		$this->assertSame( $voce, $annullamenti[0]['id'] );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => $numero['numero'],
				'fine'   => '2041-10-04',
			),
			$annullamenti[0]['dettagli'],
			'Numero, anno e fine, quella della defissione.'
		);

		// Annullato e' terminale anche qui.
		$annullato = $this->fotografia( $id );

		$this->assertSame( array( 'albo_atto_gia_annullato' ), $this->codici( $this->annulla( $id, $this->amministratore, 'Di nuovo.' ) ) );
		$this->assertSame( array( 'albo_atto_non_pubblicato' ), $this->codici( $this->defiggi( $id, $this->amministratore ) ) );
		$this->assertSame( $annullato, $this->fotografia( $id ) );

		// Dal riquadro, su un altro atto defisso.
		$altro = $this->pubblicato_il( '2041-10-02 09:30:00', 'Altro atto' );

		$this->assertIsInt( $this->defiggi( $altro, $this->pubblicatore ) );

		$url = $this->invia_riquadro(
			$altro,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'annulla',
				RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO => 'Annullato dal riquadro.',
			)
		);

		$this->assertStringContainsString( RiquadroPassaggi::ESITO . '=annullato', $url );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $altro ) );
	}

	/**
	 * A-241: un pezzo dell'annullamento da defisso che fallisce lascia l'atto defisso.
	 */
	public function test_a241_tutto_o_niente_da_defisso(): void {
		global $wpdb;

		$registro = $wpdb->prefix . 'conformita_core_registro';

		foreach ( array(
			'scrittura dello stato'               => array( 'albo_stato_non_scritto', array( 'UPDATE `' . $wpdb->posts . '`', "'" . ChiusuraPubblicazione::ANNULLATO . "'" ) ),
			'voce automatica del cambio di stato' => array( 'albo_voce_automatica_mancante', array( 'INSERT INTO `' . $registro . '`', "'cambio_stato'" ) ),
			'voce dell\'annullamento'             => array( 'albo_voce_non_scritta', array( 'INSERT INTO `' . $registro . '`', "'" . Passaggi::AZIONE_ANNULLAMENTO . "'" ) ),
		) as $caso => list( $codice, $pezzi ) ) {
			$id = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto ' . $caso );

			$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore ) );

			$prima = $this->fotografia( $id );

			$this->guasta_istruzioni( $pezzi );

			try {
				$esito = $this->annulla( $id, $this->pubblicatore );
			} finally {
				$this->ripara();
			}

			$codici = $this->codici( $esito );

			$this->assertSame( 'albo_atto_non_annullato', $codici[0], $caso );
			$this->assertContains( $codice, $codici, $caso . ': con il codice del pezzo fallito.' );
			$this->assertStringContainsString( 'tolto dall\'albo', $esito->get_error_message( 'albo_atto_non_annullato' ), $caso . ': il messaggio dice che l\'atto resta tolto dall\'albo.' );
			$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), $caso );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': identico a prima, registro compreso.' );

			$this->assertIsInt( $this->annulla( $id, $this->pubblicatore ), $caso . ': tolto il guasto, si annulla.' );
		}
	}

	/**
	 * A-242: il riquadro dell'atto in corso, dello scaduto e del defisso.
	 */
	public function test_a242_riquadro(): void {
		$id              = $this->pubblicato_il();
		$scaduto         = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto' );
		$senza_defiggere = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) ) );

		$suo = $this->riquadro( $id, $this->pubblicatore );

		$this->assertStringContainsString( 'value="defiggi"', $suo, 'Chi puo\' defiggere trova il pulsante.' );
		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE . '"', $suo, 'E il campo del motivo.' );
		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_GETTONE . '"', $suo, 'Con il gettone.' );

		preg_match_all( '/<input type="radio"[^>]*name="' . preg_quote( RiquadroPassaggi::CAMPO_CAUSA, '/' ) . '"[^>]*>/', $suo, $scelte );

		$this->assertCount( 4, $scelte[0], 'Le quattro cause, e nessun\'altra.' );

		foreach ( array_keys( Passaggi::cause_defissione() ) as $causa ) {
			$this->assertStringContainsString( 'value="' . $causa . '"', implode( '', $scelte[0] ), $causa );
		}

		$this->assertStringNotContainsString( 'checked', implode( '', $scelte[0] ), 'Nessuna causa gia\' scelta.' );
		$this->assertStringNotContainsString( Passaggi::CAUSA_DATI_ECCEDENTI, $suo, 'I dati eccedenti non sono una causa di defissione.' );

		$altro = $this->riquadro( $id, $senza_defiggere );

		$this->assertStringContainsString( 'Numero di repertorio', $altro, 'Precondizione: il riquadro c\'e\'.' );
		$this->assertStringNotContainsString( 'value="defiggi"', $altro, 'Chi non puo\' defiggere non trova il pulsante.' );
		$this->assertStringNotContainsString( RiquadroPassaggi::CAMPO_CAUSA, $altro );

		$dello_scaduto = $this->riquadro( $scaduto, $this->pubblicatore );

		$this->assertStringContainsString( 'value="annulla"', $dello_scaduto, 'Precondizione: lo scaduto si annulla ancora.' );
		$this->assertStringNotContainsString( 'value="defiggi"', $dello_scaduto, 'Lo scaduto non si toglie: e\' gia\' fuori dalla vista.' );

		$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore, 'ordine_autorita', "Ordine del Garante del 3 ottobre 2041.\nProt. 45." ) );

		foreach ( array( $this->pubblicatore, $this->amministratore ) as $utente ) {
			$defisso = $this->riquadro( $id, $utente );

			$this->assertStringContainsString( 'Atto tolto dall&#039;albo prima della scadenza', $defisso );
			$this->assertStringContainsString( 'Ordine del Garante del 3 ottobre 2041.<br />' . "\n" . 'Prot. 45.', $defisso, 'Il motivo, con il suo a capo.' );
			$this->assertStringContainsString( esc_html( Passaggi::cause_defissione()['ordine_autorita'] ), $defisso, 'La causa, con la sua spiegazione.' );
			$this->assertStringContainsString( 'Fine pianificata', $defisso );
			$this->assertStringContainsString( wp_date( (string) get_option( 'date_format' ), ( new \DateTimeImmutable( '2041-10-16 12:00:00', wp_timezone() ) )->getTimestamp() ), $defisso, 'La fine pianificata, 16 ottobre.' );
			$this->assertStringContainsString( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), ( new \DateTimeImmutable( self::ISTANTE_DEFISSIONE, wp_timezone() ) )->getTimestamp() ), $defisso, 'La data della defissione.' );
			$this->assertStringContainsString( 'Numero di repertorio', $defisso, 'Numero e date restano.' );
			$this->assertStringNotContainsString( 'value="defiggi"', $defisso, 'Non si toglie di nuovo.' );
			$this->assertStringContainsString( 'value="annulla"', $defisso, 'Si annulla.' );
		}

		$this->assertStringNotContainsString( 'value="annulla"', $this->riquadro( $id, $senza_defiggere ), 'Chi non ha il permesso non annulla.' );
	}
}
