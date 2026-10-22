<?php
/**
 * L'annullamento di un atto pubblicato: righe A-224..A-226, A-229 e parte di A-230 (ALBO-22, ALBO-10, ALBO-09).
 *
 * Gli atti si portano a pubblicato con il passaggio vero e si annullano con
 * il passaggio vero, da codice e dal riquadro, con l'orologio fissato. Ogni
 * rifiuto ha accanto il suo controllo positivo: lo stesso atto, o un atto
 * gemello, che chi ha il permesso annulla. La pagina pubblica dell'annullato
 * e' provata in `PaginaPubblicaTest`, la consultazione riservata in
 * `ConsultazioneScadutiTest`, il rilancio dell'importazione in
 * `ImportazioneVeraTest`.
 *
 * I valori attesi sono scritti per esteso: 1 ottobre 2041 alle 9:30 piu' 15
 * giorni fa fine il 16 ottobre; l'annullamento avviene il 5 ottobre.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\ChiusuraPubblicazione;
use AlboPretorioPa\Installazione;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\RiquadroPassaggi;

use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TIPO;

/**
 * Annullamento: motivo, permesso, stato terminale, tutto o niente.
 */
class AnnullamentoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'istante dell'annullamento.
	 */
	private const ISTANTE_ANNULLAMENTO = '2041-10-05 10:00:00';

	/**
	 * Il motivo usato quando il motivo non e' cio' che si prova.
	 */
	private const MOTIVO = 'Errore materiale nell\'oggetto: si ripubblica corretto.';

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
	 * Un atto pubblicato con il passaggio vero all'istante indicato, poi l'orologio sull'istante dell'annullamento.
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
		$this->fissa_orologi( self::ISTANTE_ANNULLAMENTO );

		return $id;
	}

	/**
	 * Annulla da codice, come l'utente indicato.
	 *
	 * @param int   $atto_id Atto.
	 * @param int   $utente  Utente.
	 * @param mixed $motivo  Motivo.
	 * @return mixed
	 */
	private function annulla( int $atto_id, int $utente, $motivo = self::MOTIVO ) {
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
	 * La fotografia dell'atto senza cio' che l'annullamento cambia: stato, date di modifica e registro.
	 *
	 * @param array<string, mixed> $foto Fotografia.
	 * @return array<string, mixed>
	 */
	private function senza_annullamento( array $foto ): array {
		unset( $foto['riga']['post_status'], $foto['riga']['post_modified'], $foto['riga']['post_modified_gmt'], $foto['registro'] );

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
	 * A-224: annullamento senza e con motivo, da codice e dal riquadro, anche di un atto gia' scaduto.
	 */
	public function test_a224_annullamento(): void {
		$id     = $this->pubblicato_il();
		$numero = Repertorio::di( $id );
		$prima  = $this->fotografia( $id );

		$this->assertIsArray( $numero, 'Precondizione: l\'atto ha il numero.' );

		foreach ( array( '', "  \n\t ", '<script></script>', null, array( 'motivo' ) ) as $vuoto ) {
			$this->assertSame( array( 'albo_motivo_mancante' ), $this->codici( $this->annulla( $id, $this->pubblicatore, $vuoto ) ), 'Motivo ' . wp_json_encode( $vuoto ) . ': rifiutato.' );
		}

		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'annulla',
				RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO => '   ',
			)
		);

		$this->assertArrayHasKey( 'albo_motivo_mancante', $this->rifiuti_del_riquadro( $id, $this->pubblicatore ), 'Il riquadro spiega il rifiuto.' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Senza motivo l\'atto resta pubblicato e identico, registro compreso.' );
		$this->assertSame( 'publish', get_post_status( $id ) );

		// Con il motivo, da codice.
		$voce = $this->annulla( $id, $this->pubblicatore, "Errore materiale nell'oggetto.\nSi ripubblica corretto <b>subito</b>." );

		$this->assertIsInt( $voce, is_wp_error( $voce ) ? $voce->get_error_message() : '' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ), 'Lo stato memorizzato e\' annullato.' );

		$dopo = $this->fotografia( $id );

		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $dopo['riga']['post_status'], 'Nella banca dati.' );
		$this->assertSame( $this->senza_annullamento( $prima ), $this->senza_annullamento( $dopo ), 'Numero, date, dati, voci e documenti sono quelli di prima.' );
		$this->assertSame( $numero, Repertorio::di( $id ), 'Il numero resta.' );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'La fine resta quella pianificata.' );
		$this->assertSame( '2041-10-01 09:30:00', $dopo['riga']['post_date'], 'L\'inizio resta un fatto.' );
		$this->assertSame( $prima['registro'], array_slice( $dopo['registro'], 0, count( $prima['registro'] ) ), 'Le voci di prima restano com\'erano.' );
		$this->assertCount( count( $prima['registro'] ) + 2, $dopo['registro'], 'Due voci in piu\': la rimozione e l\'annullamento.' );

		$rimozioni = $this->voci_di(
			$id,
			array(
				'azione'  => 'rimozione',
				'origine' => 'automatica',
			)
		);

		$this->assertCount( 1, $rimozioni, 'Il meccanismo comune registra il cambio di stato.' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $rimozioni[0]['dettagli']['stato_nuovo'] );

		$annullamenti = $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_ANNULLAMENTO ) );

		$this->assertCount( 1, $annullamenti );
		$this->assertSame( $voce, $annullamenti[0]['id'], 'Il passaggio restituisce la voce del motivo.' );
		$this->assertSame( "Errore materiale nell'oggetto.\nSi ripubblica corretto subito.", $annullamenti[0]['motivazione'], 'Il motivo, ripulito dal codice, con il suo a capo.' );
		$this->assertSame( $this->pubblicatore, $annullamenti[0]['utente'], 'Chi.' );
		$this->assertSame( '2041-10-05 08:00:00', $annullamenti[0]['istante'], 'Quando: le 10 nell\'ora del sito, le 8 in UTC come le scrive il registro.' );
		$this->assertSame( SEZIONE, $annullamenti[0]['sezione'] );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => $numero['numero'],
				'fine'   => '2041-10-16',
			),
			$annullamenti[0]['dettagli'],
			'Numero, anno e fine pianificata.'
		);

		$letta = Passaggi::annullamento( $id );

		$this->assertIsArray( $letta );
		$this->assertSame( "Errore materiale nell'oggetto.\nSi ripubblica corretto subito.", $letta['motivo'] );

		// Dal riquadro, su un altro atto.
		$secondo = $this->pubblicato_il( '2041-10-02 09:30:00', 'Secondo atto' );
		$url     = $this->invia_riquadro(
			$secondo,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'annulla',
				RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO => 'Atto pubblicato per errore.',
			)
		);

		$this->assertStringContainsString( RiquadroPassaggi::ESITO . '=annullato', $url, 'Il riquadro torna con l\'esito.' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $secondo ) );
		$this->assertSame( 'Atto pubblicato per errore.', $this->voci_di( $secondo, array( 'azione' => Passaggi::AZIONE_ANNULLAMENTO ) )[0]['motivazione'] );

		// Un atto pubblicato gia' scaduto si annulla allo stesso modo.
		$scaduto = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto' );

		$this->assertTrue( conformita_core_scaduto( $scaduto ), 'Precondizione: scaduto per il meccanismo comune.' );
		$this->assertSame( 'publish', get_post_status( $scaduto ), 'Precondizione: lo stato memorizzato e\' ancora pubblicato.' );
		$this->assertIsInt( $this->annulla( $scaduto, $this->pubblicatore ) );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $scaduto ) );
		$this->assertSame( '2041-09-16', conformita_core_fine_pubblicazione( $scaduto ), 'La fine non cambia.' );
	}

	/**
	 * A-225: chi annulla, e che cosa si annulla.
	 */
	public function test_a225_chi_annulla_e_che_cosa(): void {
		$id    = $this->pubblicato_il();
		$altro = $this->pubblicato_il( '2041-10-02 09:30:00', 'Altro atto' );
		$prima = $this->fotografia( $id );

		$senza_annullare = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) ) );
		$solo_annullare  = $this->utente_con( array( Permessi::CHIAVE_DEFISSIONE ) );
		$consultatore    = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );
		$abbonato        = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertTrue( user_can( $senza_annullare, (string) Permessi::mappa()['publish_posts'] ), 'Precondizione: pubblica.' );

		foreach ( array(
			'chi redige'                   => $this->redattore,
			'abbonato'                     => $abbonato,
			'chi consulta gli scaduti'     => $consultatore,
			'chi pubblica senza annullare' => $senza_annullare,
		) as $chi => $utente ) {
			$this->assertSame( array( 'albo_annullamento_non_permesso' ), $this->codici( $this->annulla( $id, $utente ) ), $chi . ', da codice.' );

			$this->invia_riquadro(
				$id,
				$utente,
				array(
					RiquadroPassaggi::CAMPO_PASSAGGIO => 'annulla',
					RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO => self::MOTIVO,
				)
			);

			$this->assertArrayHasKey( 'albo_annullamento_non_permesso', $this->rifiuti_del_riquadro( $id, $utente ), $chi . ', dal riquadro.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $chi . ': l\'atto resta identico, registro compreso.' );
		}

		// Il gettone di un altro atto, e un gettone inventato.
		foreach ( array(
			'gettone di un altro atto' => function () use ( $altro ) {
				return wp_create_nonce( RiquadroPassaggi::azione( $altro ) );
			},
			'gettone inventato'        => static function () {
				return 'abcdef1234';
			},
		) as $caso => $gettone ) {
			$this->come(
				$this->pubblicatore,
				static function () use ( $id, $gettone ) {
					set_current_screen( 'post' );

					$_POST = array(
						'action'                          => 'editpost',
						'post_ID'                         => (string) $id,
						'_wpnonce'                        => wp_create_nonce( 'update-post_' . $id ),
						RiquadroPassaggi::CAMPO_GETTONE   => $gettone(),
						RiquadroPassaggi::CAMPO_PASSAGGIO => 'annulla',
						RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO => self::MOTIVO,
					);

					try {
						RiquadroPassaggi::elabora();
					} finally {
						$_POST = array();
					}
				}
			);

			$this->assertArrayHasKey( 'albo_modulo_non_riconosciuto', $this->rifiuti_del_riquadro( $id, $this->pubblicatore ), $caso . '.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': l\'atto resta identico.' );
		}

		// Che cosa: solo un atto pubblicato dal passaggio, con il numero.
		$bozza       = $this->atto_completo();
		$in_verifica = $this->atto_in_verifica();
		$scavalcato  = $this->scavalcato();
		$articolo    = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$fotografie  = array();

		foreach ( array( $bozza, $in_verifica, $scavalcato ) as $atto ) {
			$fotografie[ $atto ] = $this->fotografia( $atto );
		}

		$this->assertSame( array( 'albo_atto_non_pubblicato' ), $this->codici( $this->annulla( $bozza, $this->amministratore ) ), 'Bozza.' );
		$this->assertSame( array( 'albo_atto_non_pubblicato' ), $this->codici( $this->annulla( $in_verifica, $this->amministratore ) ), 'In verifica.' );
		$this->assertSame( array( 'albo_atto_senza_numero' ), $this->codici( $this->annulla( $scavalcato, $this->amministratore ) ), 'Pubblicato scavalcando il passaggio.' );
		$this->assertSame( array( 'albo_non_un_atto' ), $this->codici( $this->annulla( $articolo, $this->amministratore ) ), 'Un articolo.' );
		$this->assertSame( array( 'albo_non_un_atto' ), $this->codici( $this->annulla( 999999999, $this->amministratore ) ), 'Un numero mai dato.' );

		foreach ( $fotografie as $atto => $foto ) {
			$this->assertSame( $foto, $this->fotografia( $atto ), 'Atto ' . $atto . ': identico.' );
		}

		$this->assertSame( 'publish', get_post_status( $articolo ), 'L\'articolo resta com\'e\'.' );

		// Controlli positivi: chi ha il solo permesso di annullare, l'amministratore, il ruolo dell'albo.
		$this->assertFalse( user_can( $solo_annullare, (string) Permessi::mappa()['publish_posts'] ), 'Precondizione: non pubblica.' );
		$this->assertIsInt( $this->annulla( $id, $solo_annullare ), 'Chi ha il solo permesso di annullare annulla.' );
		$this->assertIsInt( $this->annulla( $altro, $this->amministratore ), 'L\'amministratore annulla.' );
		$this->assertIsInt( $this->annulla( $this->pubblicato_il( '2041-10-03 09:30:00' ), $this->pubblicatore ), 'Il ruolo dell\'albo annulla.' );

		// Gia' annullato.
		$annullato = $this->fotografia( $id );

		$this->assertSame( array( 'albo_atto_gia_annullato' ), $this->codici( $this->annulla( $id, $this->amministratore, 'Di nuovo.' ) ), 'Gia\' annullato.' );
		$this->assertSame( $annullato, $this->fotografia( $id ), 'Il secondo annullamento non scrive niente.' );
	}

	/**
	 * A-226: annullato e' terminale, per tutti e da ogni ingresso.
	 */
	public function test_a226_annullato_terminale(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id        = $this->pubblicato_il();
		$controllo = $this->pubblicato_il( '2041-10-01 10:00:00', 'Atto di controllo' );

		$this->assertIsInt( $this->annulla( $id, $this->pubblicatore ) );

		$prima         = $this->fotografia( $id );
		$chiave_fine   = conformita_core_chiave_fine_pubblicazione();
		$altro_organo  = $this->voce( TASSONOMIA_ORGANO, 'Consiglio' );
		$this->scritti = array();

		foreach ( array( $this->redattore, $this->pubblicatore, $this->amministratore ) as $utente ) {
			$this->come(
				$utente,
				function () use ( $id ) {
					foreach ( array( 'publish', 'draft', 'pending', 'auto-draft', 'private', 'future', 'trash', ChiusuraPubblicazione::ANNULLATO ) as $stato ) {
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
					$this->assertSame( array( 'albo_atto_gia_annullato' ), albo_pretorio_annulla( $id, 'Di nuovo.' )->get_error_codes() );

					// Dalla schermata di WordPress.
					set_current_screen( 'post' );

					$_POST = array(
						'action'               => 'editpost',
						'post_ID'              => (string) $id,
						'post_type'            => TIPO,
						'post_title'           => 'Oggetto dalla schermata',
						'original_post_status' => ChiusuraPubblicazione::ANNULLATO,
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

					$this->assertArrayHasKey( 'albo_atto_annullato', Rifiuti::preleva_per_utente( $id ), 'Il rifiuto della schermata dice che l\'atto e\' annullato.' );
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
			$this->assertStringContainsString( 'annullato', $this->richiesta_fermata( $scrittura, $caso ), $caso . ': il rifiuto nomina l\'annullato.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': niente cambia.' );
		}

		// Una scrittura dei dati condizionata dalla barriera non tocca l'annullato.
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $chiave_fine ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la banca dati scritta come la lascerebbe un altro programma.
		$this->assertSame( $prima, $this->fotografia( $id ), 'Cancellazione dritta della data di fine: niente cambia.' );

		// La proroga per disservizio allunga il controllo e non l'annullato.
		$esito = $this->come(
			$this->pubblicatore,
			static function () {
				return albo_pretorio_proroga(
					array(
						'giorni'       => array( '2041-10-03' ),
						'attestazione' => 'Attestazione prot. 12 del 4 ottobre',
					)
				);
			}
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $controllo ), 'Il controllo e\' prorogato.' );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'L\'annullato no.' );

		$dopo = $this->fotografia( $id );

		$this->assertSame( $prima['riga'], $dopo['riga'], 'Proroga: la riga dell\'annullato non cambia.' );
		$this->assertSame( $prima['dati'], $dopo['dati'], 'Proroga: i dati dell\'annullato non cambiano.' );
	}

	/**
	 * A-229: tutto o niente, con un pezzo dell'annullamento che fallisce per volta.
	 */
	public function test_a229_tutto_o_niente(): void {
		global $wpdb;

		$registro = $wpdb->prefix . 'conformita_core_registro';
		$guasti   = array(
			'scrittura dello stato'                => array(
				'albo_stato_non_scritto',
				function () use ( $wpdb ) {
					$this->guasta_istruzioni( array( 'UPDATE `' . $wpdb->posts . '`', "'" . ChiusuraPubblicazione::ANNULLATO . "'" ) );
				},
			),
			'voce automatica della rimozione'      => array(
				'albo_voce_automatica_mancante',
				function () use ( $registro ) {
					$this->guasta_istruzioni( array( 'INSERT INTO `' . $registro . '`', "'rimozione'" ) );
				},
			),
			'voce dell\'annullamento'              => array(
				'albo_voce_non_scritta',
				function () use ( $registro ) {
					$this->guasta_istruzioni( array( 'INSERT INTO `' . $registro . '`', "'" . Passaggi::AZIONE_ANNULLAMENTO . "'" ) );
				},
			),
			'stato riscritto da un altro aggancio' => array(
				'albo_scrittura_rifiutata_nel_passaggio',
				static function () use ( $wpdb ) {
					add_action(
						'wp_insert_post',
						static function ( $post_id ) use ( $wpdb ) {
							$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la banca dati scritta come la lascerebbe un altro programma.
						},
						5
					);
				},
			),
		);

		foreach ( $guasti as $caso => list( $codice, $guasta ) ) {
			$id    = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto ' . $caso );
			$prima = $this->fotografia( $id );

			$guasta();

			try {
				$esito = $this->annulla( $id, $this->pubblicatore );
			} finally {
				$this->ripara();
				remove_all_actions( 'wp_insert_post', 5 );
			}

			$codici = $this->codici( $esito );

			$this->assertSame( 'albo_atto_non_annullato', $codici[0], $caso . ': il primo messaggio dice che l\'atto resta pubblicato.' );
			$this->assertContains( $codice, $codici, $caso . ': con il codice del pezzo fallito.' );
			$this->assertSame( 'publish', get_post_status( $id ), $caso . ': pubblicato anche per chi legge dalla memoria.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': identico a prima, registro compreso.' );

			$this->assertIsInt( $this->annulla( $id, $this->pubblicatore ), $caso . ': tolto il guasto, lo stesso atto si annulla.' );
			$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ) );
		}

		// Le tabelle che non annullano: niente comincia.
		$id    = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto delle tabelle' );
		$prima = $this->fotografia( $id );

		foreach ( array( $registro, $wpdb->posts, $wpdb->term_relationships ) as $tabella ) {
			$rispondi = static function ( $istruzione ) use ( $tabella ) {
				return str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$tabella}' AND t.TABLE_NAME IN (", (string) $istruzione );
			};

			add_filter( 'query', $rispondi );

			try {
				$esito = $this->annulla( $id, $this->pubblicatore );
			} finally {
				remove_filter( 'query', $rispondi );
			}

			$this->assertContains( 'albo_tabelle_non_transazionali', $this->codici( $esito ), $tabella );
			$this->assertStringContainsString( $tabella, implode( ' ', $esito->get_error_messages() ), 'Il rifiuto nomina la tabella.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $tabella . ': niente cambia.' );
		}

		$this->assertIsInt( $this->annulla( $id, $this->pubblicatore ), 'Controllo positivo: con le tabelle che annullano, lo stesso atto si annulla.' );
	}

	/**
	 * A-230: il riquadro dell'atto pubblicato e dell'annullato.
	 */
	public function test_a230_riquadro(): void {
		$id              = $this->pubblicato_il();
		$senza_annullare = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) ) );

		$suo = $this->riquadro( $id, $this->pubblicatore );

		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO . '"', $suo, 'Chi puo\' annullare trova il campo del motivo.' );
		$this->assertStringContainsString( 'value="annulla"', $suo, 'E il pulsante.' );
		$this->assertStringContainsString( 'name="' . RiquadroPassaggi::CAMPO_GETTONE . '"', $suo, 'Con il gettone.' );

		$altro = $this->riquadro( $id, $senza_annullare );

		$this->assertStringContainsString( 'Numero di repertorio', $altro, 'Precondizione: il riquadro c\'e\'.' );
		$this->assertStringNotContainsString( RiquadroPassaggi::CAMPO_MOTIVO_ANNULLAMENTO, $altro, 'Chi non puo\' annullare non trova il campo.' );
		$this->assertStringNotContainsString( 'value="annulla"', $altro );

		$this->assertIsInt( $this->annulla( $id, $this->pubblicatore, "Pubblicato per errore.\nVedi l'atto 12/2041." ) );

		foreach ( array( $this->pubblicatore, $this->amministratore ) as $utente ) {
			$annullato = $this->riquadro( $id, $utente );

			$this->assertStringContainsString( 'Atto annullato', $annullato );
			$this->assertStringContainsString( 'Pubblicato per errore.<br />' . "\n" . 'Vedi l&#039;atto 12/2041.', $annullato, 'Il motivo, con il suo a capo.' );
			$this->assertStringContainsString( 'Numero di repertorio', $annullato, 'Numero e date restano.' );
			$this->assertStringNotContainsString( '<button', $annullato, 'Nessun pulsante.' );
			$this->assertStringNotContainsString( '<textarea', $annullato, 'Nessun campo.' );
		}
	}

	/**
	 * A-230: il permesso di annullare arriva all'installazione e all'aggiornamento, e solo a chi pubblica.
	 */
	public function test_a230_permesso_all_installazione_e_all_aggiornamento(): void {
		$permesso = Permessi::PERMESSO_DEFISSIONE;
		$mappa    = Permessi::mappa();

		$this->assertIsArray( $mappa );
		$this->assertSame( $permesso, $mappa[ Permessi::CHIAVE_DEFISSIONE ] );
		$this->assertStringContainsString( TIPO, $permesso, 'Il nome porta il tipo, come gli altri permessi dell\'albo.' );

		foreach ( array( 'administrator', RUOLO ) as $ruolo ) {
			$this->assertTrue( get_role( $ruolo )->has_cap( $permesso ), $ruolo . ': all\'installazione.' );
		}

		// Un sito con la versione di prima: il permesso non c'e' ancora da nessuna parte.
		add_role( 'albo_prova_pubblica', 'Prova pubblica', array( 'read' => true ) );
		add_role( 'albo_prova_redige', 'Prova redige', array( 'read' => true ) );

		$senza = array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) );

		foreach ( $senza as $chiave ) {
			get_role( 'albo_prova_pubblica' )->add_cap( $mappa[ $chiave ] );
		}

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			get_role( 'albo_prova_redige' )->add_cap( $mappa[ $chiave ] );
		}

		foreach ( wp_roles()->role_objects as $oggetto ) {
			$oggetto->remove_cap( $permesso );
		}

		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, '0.5.0-alpha' );

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'Con la versione di prima l\'aggiornamento gira.' );

		foreach ( array( 'administrator', RUOLO, 'albo_prova_pubblica' ) as $ruolo ) {
			$this->assertTrue( get_role( $ruolo )->has_cap( $permesso ), $ruolo . ': all\'aggiornamento.' );
		}

		$this->assertFalse( get_role( 'albo_prova_redige' )->has_cap( $permesso ), 'Chi redige non lo riceve.' );
		$this->assertFalse( user_can( $this->redattore, $permesso ), 'Nemmeno l\'utente che redige.' );

		remove_role( 'albo_prova_pubblica' );
		remove_role( 'albo_prova_redige' );
	}
}
