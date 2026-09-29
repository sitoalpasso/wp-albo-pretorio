<?php
/**
 * Pubblicazione e rimando in bozza: righe A-93..A-104.
 *
 * La seconda parte del flusso di ALBO-22, con le righe di ALBO-11, ALBO-27,
 * ALBO-02, ALBO-01 e ALBO-08 che si chiudono alla pubblicazione. I due ingressi
 * da cui una pubblicazione puo' concludersi sono il riquadro Pubblicazione
 * della schermata e la funzione pubblica; la richiesta di programmazione e
 * gli ingressi generici di WordPress restano rifiutati, e si provano come
 * attacchi. Il "tutto o niente" ha le sue prove, in `TuttoONienteTest`.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\RiquadroPassaggi;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\META_DOCUMENTO_PRINCIPALE;
use const AlboPretorioPa\META_DURATA;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/**
 * I due passaggi dalla verifica, riusciti e rifiutati.
 */
class PubblicazioneTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Assetto comune.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * La data di inizio e la fine attese per l'istante fissato e una durata.
	 *
	 * @param int $giorni Durata.
	 * @return array{inizio: string, fine: string}
	 */
	private function date_attese( int $giorni ): array {
		$adesso = Passaggi::ora();

		return array(
			'inizio' => $adesso->format( 'Y-m-d H:i:s' ),
			'fine'   => $adesso->setTime( 0, 0 )->modify( '+' . $giorni . ' days' )->format( 'Y-m-d' ),
		);
	}

	/**
	 * Controlla che l'atto risulti pubblicato con le date del sistema.
	 *
	 * @param int    $atto_id Atto.
	 * @param int    $giorni  Durata applicabile.
	 * @param string $caso    Caso, per i messaggi.
	 */
	private function pubblicato_con_date_del_sistema( int $atto_id, int $giorni, string $caso ): void {
		$attese = $this->date_attese( $giorni );
		$riga   = $this->fotografia( $atto_id )['riga'];

		$this->assertSame( 'publish', $riga['post_status'], $caso . ': pubblicato.' );
		$this->assertSame( $attese['inizio'], $riga['post_date'], $caso . ': inizio dall\'orologio del sistema.' );
		$this->assertSame(
			( new \DateTimeImmutable( $attese['inizio'], wp_timezone() ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			$riga['post_date_gmt'],
			$caso . ': inizio in tempo universale coerente.'
		);
		$this->assertSame( $attese['fine'], conformita_core_fine_pubblicazione( $atto_id ), $caso . ': fine uguale a inizio piu\' durata.' );
	}

	/**
	 * A-93: da in verifica a pubblicato, chi puo' solo redigere no, chi puo' pubblicare si'.
	 */
	public function test_a93_pubblicazione_e_permesso_di_pubblicare(): void {
		// Chi possiede tutti i permessi confondibili, meno quello di pubblicare.
		$quasi = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( 'publish_posts' ) ) ) );
		$id    = $this->atto_in_verifica();

		$this->assertTrue( user_can( $quasi, 'edit_post', $id ), 'Precondizione: modifica l\'atto di un altro.' );
		$this->assertFalse( user_can( $quasi, 'publish_post', $id ), 'Precondizione: non puo\' pubblicare.' );

		$prima         = $this->fotografia( $id );
		$this->scritti = array();

		foreach ( array( $this->redattore, $quasi ) as $utente ) {
			$esito = $this->pubblica_da_codice( $id, $utente );

			$this->assertSame( array( 'albo_pubblicazione_non_permessa' ), $esito->get_error_codes() );

			$this->invia_riquadro(
				$id,
				$utente,
				array(
					RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
					RiquadroPassaggi::CAMPO_CONFERMA  => (string) $id,
				)
			);

			$this->assertArrayHasKey(
				'albo_pubblicazione_non_permessa',
				$this->come(
					$utente,
					static function () use ( $id ) {
						return Rifiuti::preleva_per_utente( $id );
					}
				),
				'Dalla schermata, il rifiuto e\' spiegato.'
			);
		}

		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto resta in verifica, identico.' );
		$this->assertSame( array(), $this->stati_scritti( $id ), 'Nessuna scrittura della riga.' );

		// Controllo positivo: il solo permesso di pubblicare, senza quelli di redazione.
		$solo = $this->utente_con( array( 'publish_posts' ) );

		$this->assertFalse( user_can( $solo, 'edit_post', $id ), 'Precondizione: non redige.' );

		$esito = $this->pubblica_da_codice( $id, $solo );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->pubblicato_con_date_del_sistema( $id, 15, 'Solo permesso di pubblicare' );

		// Una stessa persona con entrambi i permessi compie i due passaggi di fila.
		$doppio = $this->atto_completo();

		$this->come(
			$this->pubblicatore,
			static function () use ( $doppio ) {
				return wp_update_post(
					array(
						'ID'          => $doppio,
						'post_status' => 'pending',
					)
				);
			}
		);

		clean_post_cache( $doppio );

		$this->assertSame( 'pending', get_post_status( $doppio ), 'Il primo passaggio, la verifica.' );

		$url = $this->invia_riquadro(
			$doppio,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $doppio,
			)
		);

		$this->assertStringContainsString( RiquadroPassaggi::ESITO . '=pubblicato', $url );
		$this->pubblicato_con_date_del_sistema( $doppio, 15, 'Stessa persona, due passaggi' );

		$voci = $this->voci_di( $doppio, array( 'azione' => 'pubblicazione' ) );

		$this->assertCount( 1, $voci, 'Il meccanismo comune registra la pubblicazione.' );
		$this->assertSame( $this->pubblicatore, $voci[0]['utente'], 'Con chi l\'ha compiuta.' );
	}

	/**
	 * A-94: rimando in bozza senza e con motivo.
	 */
	public function test_a94_rimando_in_bozza(): void {
		$id    = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );

		foreach ( array( '', "  \n\t ", null, array( 'motivo' ) ) as $vuoto ) {
			$esito = $this->come(
				$this->pubblicatore,
				static function () use ( $id, $vuoto ) {
					return albo_pretorio_rimanda_in_bozza( $id, $vuoto );
				}
			);

			$this->assertSame( array( 'albo_motivo_mancante' ), $esito->get_error_codes() );
		}

		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'rimanda',
				RiquadroPassaggi::CAMPO_MOTIVO    => '   ',
			)
		);

		$this->assertArrayHasKey(
			'albo_motivo_mancante',
			$this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					return Rifiuti::preleva_per_utente( $id );
				}
			)
		);

		// Chi puo' solo redigere non rimanda, nemmeno con il motivo.
		$esito = $this->come(
			$this->redattore,
			static function () use ( $id ) {
				return albo_pretorio_rimanda_in_bozza( $id, 'Manca un allegato.' );
			}
		);

		$this->assertSame( array( 'albo_rimando_non_permesso' ), $esito->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Senza motivo, o senza permesso, l\'atto resta in verifica, identico.' );

		// Con il motivo, dalla schermata.
		$url = $this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'rimanda',
				RiquadroPassaggi::CAMPO_MOTIVO    => "L'oggetto non corrisponde al documento.",
			)
		);

		$this->assertStringContainsString( RiquadroPassaggi::ESITO . '=rimandato', $url );
		$this->assertSame( 'draft', get_post_status( $id ), 'L\'atto torna in bozza.' );

		$voci = $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_RIMANDO ) );

		$this->assertCount( 1, $voci );
		$this->assertSame( "L'oggetto non corrisponde al documento.", $voci[0]['motivazione'], 'Il motivo e\' nel registro, com\'e\' stato scritto.' );
		$this->assertSame( $this->pubblicatore, $voci[0]['utente'] );
		$this->assertSame( 'componente', $voci[0]['origine'] );
		$passaggi = $this->voci_di( $id, array( 'azione' => 'cambio_stato' ) );

		$this->assertCount( 2, $passaggi, 'Il passaggio di stato lo registra anche il meccanismo comune: verifica e rimando.' );
		$this->assertSame( 'draft', $passaggi[1]['dettagli']['stato_nuovo'] );
		$this->assertNull( Repertorio::di( $id ), 'Il rimando non numera.' );
		$this->assertSame( '', conformita_core_fine_pubblicazione( $id ), 'Il rimando non scrive date.' );

		// La bozza torna modificabile da chi redige.
		$this->come(
			$this->redattore,
			static function () use ( $id ) {
				return wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => 'Oggetto corretto',
					)
				);
			}
		);

		clean_post_cache( $id );

		$this->assertSame( 'Oggetto corretto', get_the_title( $id ) );
		$this->assertSame( 'draft', get_post_status( $id ) );

		// Il rimando riparte da capo: una bozza non si rimanda.
		$esito = $this->come(
			$this->pubblicatore,
			static function () use ( $id ) {
				return albo_pretorio_rimanda_in_bozza( $id, 'Di nuovo.' );
			}
		);

		$this->assertSame( array( 'albo_atto_non_in_verifica' ), $esito->get_error_codes() );
	}

	/**
	 * A-95: ALBO-11, senza la conferma sui dati personali il flusso non si conclude.
	 */
	public function test_a95_conferma_sui_dati_personali(): void {
		$id    = $this->atto_in_verifica();
		$altro = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );

		foreach ( array( array(), array( 'conferma' => false ), array( 'conferma' => 'si' ), array( 'conferma' => 1 ) ) as $richiesta ) {
			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore, $richiesta );

			$this->assertSame( array( 'albo_conferma_mancante' ), $esito->get_error_codes(), 'Richiesta: ' . wp_json_encode( $richiesta ) );
		}

		// Dalla schermata: senza la casella, e con la casella dell'altro atto.
		foreach ( array( array(), array( RiquadroPassaggi::CAMPO_CONFERMA => (string) $altro ) ) as $casella ) {
			$this->invia_riquadro( $id, $this->pubblicatore, array( RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica' ) + $casella );

			$this->assertArrayHasKey(
				'albo_conferma_mancante',
				$this->come(
					$this->pubblicatore,
					static function () use ( $id ) {
						return Rifiuti::preleva_per_utente( $id );
					}
				)
			);
		}

		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto resta in verifica, identico, senza numero.' );
		$this->assertSame( 'pending', get_post_status( $altro ), 'La casella non pubblica nemmeno l\'altro atto.' );

		// La schermata elenca i casi di rivelazione indiretta e il divieto assoluto.
		$stampato = $this->come(
			$this->pubblicatore,
			static function () use ( $id ) {
				ob_start();
				RiquadroPassaggi::mostra( get_post( $id ) );
				return (string) ob_get_clean();
			}
		);

		foreach ( array( 'collocamento mirato', 'congedi per assistenza', 'graduatorie con punteggi', 'provvedimenti disciplinari', 'redditi e ISEE', '2-septies' ) as $caso ) {
			$this->assertStringContainsString( $caso, $stampato );
		}

		$this->assertMatchesRegularExpression( '/name="' . RiquadroPassaggi::CAMPO_CONFERMA . '" value="' . $id . '"/', $stampato, 'La casella porta l\'atto a cui si riferisce.' );
		$this->assertStringNotContainsString( 'checked', $stampato, 'La conferma non e\' mai data in partenza.' );

		// Controllo positivo: con la conferma si pubblica, e la conferma e' nel registro.
		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $id,
			)
		);

		$this->pubblicato_con_date_del_sistema( $id, 15, 'Con la conferma' );

		$voci = $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_CONFERMA ) );

		$this->assertCount( 1, $voci );
		$this->assertSame( $this->pubblicatore, $voci[0]['utente'] );
		$this->assertSame( $this->date_attese( 15 )['inizio'], $voci[0]['dettagli']['inizio'] );
	}

	/**
	 * A-96: ALBO-27, una data di inizio nel futuro e' una programmazione, e si rifiuta nominandola.
	 */
	public function test_a96_data_di_inizio_nel_futuro(): void {
		$id      = $this->atto_in_verifica();
		$futura  = Passaggi::ora()->modify( '+3 days' )->format( 'Y-m-d H:i:s' );
		$memoria = (string) get_post_field( 'post_date', $id );
		$prima   = $this->fotografia( $id );

		$this->assertTrue( user_can( $this->pubblicatore, 'publish_post', $id ), 'Precondizione: puo\' pubblicare.' );

		// Da codice.
		$this->assertStringContainsString(
			$futura,
			$this->messaggi(
				$this->pubblica_da_codice(
					$id,
					$this->pubblicatore,
					array(
						'conferma'    => true,
						'data_inizio' => $futura,
					)
				)
			)
		);
		$this->assertStringContainsString(
			substr( $futura, 0, 10 ) . ' 00:00:00',
			$this->messaggi(
				$this->pubblica_da_codice(
					$id,
					$this->pubblicatore,
					array(
						'conferma'    => true,
						'data_inizio' => substr( $futura, 0, 10 ),
					)
				)
			),
			'Anche una data senza ora.'
		);

		// Dalla schermata, con la data cambiata nella casella di WordPress.
		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $id,
			) + $this->campi_data( $memoria, $futura )
		);

		$motivi = $this->come(
			$this->pubblicatore,
			static function () use ( $id ) {
				return Rifiuti::preleva_per_utente( $id );
			}
		);

		$this->assertArrayHasKey( 'albo_data_inizio_futura', $motivi );
		$this->assertStringContainsString( substr( $futura, 0, 16 ), $motivi['albo_data_inizio_futura'] );

		// Dalla richiesta di programmazione, da codice e dalla schermata di WordPress.
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		foreach ( array( 'future', 'publish' ) as $stato ) {
			$this->come(
				$this->pubblicatore,
				static function () use ( $id, $stato, $futura ) {
					return wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => $stato,
							'post_date'   => $futura,
							'edit_date'   => true,
						)
					);
				}
			);
		}

		$this->come(
			$this->pubblicatore,
			function () use ( $id, $memoria, $futura ) {
				set_current_screen( 'post' );

				$_POST = array(
					'action'               => 'editpost',
					'post_ID'              => (string) $id,
					'post_type'            => TIPO,
					'post_title'           => get_the_title( $id ),
					'original_post_status' => 'pending',
					'post_status'          => 'publish',
					'publish'              => 'Programma',
					'_wpnonce'             => wp_create_nonce( 'update-post_' . $id ),
				) + $this->campi_data( $memoria, $futura );

				try {
					edit_post();
				} finally {
					$_POST = array();
				}

				$motivi = Rifiuti::preleva_per_utente( $id );

				$this->assertArrayHasKey( 'albo_data_inizio_futura', $motivi, 'La programmazione dalla schermata e\' rifiutata per la data.' );
				$this->assertStringContainsString( substr( $futura, 0, 16 ), $motivi['albo_data_inizio_futura'] );
			}
		);

		$this->assertSame( $prima, $this->fotografia( $id ), 'Resta in verifica, identico, senza numero.' );
		$this->assertSame( array(), array_intersect( array( 'publish', 'future' ), $this->stati_scritti( $id ) ), 'Mai scritto pubblicato o programmato.' );

		/*
		 * Una data futura gia' scritta nella bozza. WordPress la conserva nel
		 * passaggio in verifica solo se chi redige la manda di nuovo insieme,
		 * altrimenti la riporta all'ora corrente: qui la manda.
		 */
		$bozza = $this->atto_completo();

		$this->come(
			$this->redattore,
			static function () use ( $bozza, $futura ) {
				return wp_update_post(
					array(
						'ID'          => $bozza,
						'post_status' => 'pending',
						'post_date'   => $futura,
						'edit_date'   => true,
					)
				);
			}
		);

		clean_post_cache( $bozza );

		$this->assertSame( 'pending', get_post_status( $bozza ) );
		$this->assertSame( $futura, get_post_field( 'post_date', $bozza ), 'Precondizione: la bozza porta la data futura.' );
		$this->assertStringContainsString( $futura, $this->messaggi( $this->pubblica_da_codice( $bozza, $this->pubblicatore ) ) );
		$this->assertSame( 'pending', get_post_status( $bozza ) );

		// Controllo positivo: lo stesso atto in verifica, senza data futura, si pubblica.
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );
		$this->pubblicato_con_date_del_sistema( $id, 15, 'Senza data futura' );

		// E quello della bozza, quando la sua data non e' piu' nel futuro, si pubblica con la data del sistema.
		$this->fissa_orologi( Passaggi::ora()->modify( '+4 days' )->format( 'Y-m-d H:i:s' ) );

		$this->assertIsArray( $this->pubblica_da_codice( $bozza, $this->pubblicatore ) );
		$this->pubblicato_con_date_del_sistema( $bozza, 15, 'Bozza con data ormai passata' );
	}

	/**
	 * A-97: ALBO-27, la data di inizio e' sempre quella del sistema, anche quando se ne fornisce una passata.
	 */
	public function test_a97_data_di_inizio_passata_ignorata(): void {
		$passata = Passaggi::ora()->modify( '-20 days' )->format( 'Y-m-d H:i:s' );

		// Controllo positivo: senza data fornita.
		$senza = $this->atto_in_verifica();

		$this->assertIsArray( $this->pubblica_da_codice( $senza, $this->pubblicatore ) );
		$this->pubblicato_con_date_del_sistema( $senza, 15, 'Senza data' );

		// Da codice.
		$codice = $this->atto_in_verifica();

		$this->assertIsArray(
			$this->pubblica_da_codice(
				$codice,
				$this->pubblicatore,
				array(
					'conferma'    => true,
					'data_inizio' => $passata,
				)
			)
		);
		$this->pubblicato_con_date_del_sistema( $codice, 15, 'Data passata da codice' );

		// Dalla schermata.
		$schermata = $this->atto_in_verifica();

		$this->invia_riquadro(
			$schermata,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $schermata,
			) + $this->campi_data( (string) get_post_field( 'post_date', $schermata ), $passata )
		);

		$this->pubblicato_con_date_del_sistema( $schermata, 15, 'Data passata dalla schermata' );

		// Gia' scritta nella bozza.
		$bozza = $this->atto_completo();

		$this->come(
			$this->redattore,
			static function () use ( $bozza, $passata ) {
				return wp_update_post(
					array(
						'ID'          => $bozza,
						'post_status' => 'pending',
						'post_date'   => $passata,
						'edit_date'   => true,
					)
				);
			}
		);

		clean_post_cache( $bozza );

		$this->assertSame( 'pending', get_post_status( $bozza ) );
		$this->assertSame( $passata, get_post_field( 'post_date', $bozza ), 'Precondizione: la bozza porta la data passata.' );

		$this->assertIsArray( $this->pubblica_da_codice( $bozza, $this->pubblicatore ) );
		$this->pubblicato_con_date_del_sistema( $bozza, 15, 'Data passata nella bozza' );
	}

	/**
	 * A-98: ALBO-27, la fine si conta dalla pubblicazione, non dalla preparazione.
	 *
	 * Copre la meta' della riga con la durata del tipo: quella con la durata
	 * propria abbreviata aspetta ALBO-04.
	 */
	public function test_a98_fine_dalla_pubblicazione(): void {
		$id = $this->atto_in_verifica( 'normativo' );

		$this->fissa_orologi( '2041-10-06 23:10:00' );

		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );

		$this->assertSame( '2041-10-06 23:10:00', get_post_field( 'post_date', $id ), 'L\'inizio e\' l\'istante della pubblicazione.' );
		$this->assertSame( '2041-10-21', conformita_core_fine_pubblicazione( $id ), 'Fine: il giorno di inizio non si conta, piu\' quindici.' );

		// Con il compito pianificato spento, resta raggiungibile fino all'ultimo istante della fine.
		$this->fissa_orologi( '2041-10-21 23:59:59' );
		$this->assertFalse( conformita_core_scaduto( $id ), 'L\'ultimo giorno e\' ancora di pubblicazione.' );

		$this->fissa_orologi( '2041-10-22 00:00:00' );
		$this->assertTrue( conformita_core_scaduto( $id ), 'Dal giorno dopo non lo e\' piu\'.' );

		// Nessuna pubblicazione ha un periodo inferiore alla durata: giorni interi fra inizio e scadenza.
		$inizio   = new \DateTimeImmutable( '2041-10-06 23:10:00', wp_timezone() );
		$scadenza = conformita_core_istante_scadenza( $id );

		$this->assertGreaterThanOrEqual( 15, (int) floor( ( $scadenza->getTimestamp() - $inizio->getTimestamp() ) / DAY_IN_SECONDS ) );

		// L'altro tipo, di origine dell'amministrazione: stessa regola con i suoi giorni.
		$this->fissa_orologi( self::$istante_di_prova );

		$scelto = $this->atto_in_verifica( 'scelto' );

		$this->assertIsArray( $this->pubblica_da_codice( $scelto, $this->pubblicatore ) );
		$this->pubblicato_con_date_del_sistema( $scelto, 10, 'Durata scelta dall\'amministrazione' );
	}

	/**
	 * A-99: ALBO-27, una data di fine fornita non diventa mai quella effettiva.
	 */
	public function test_a99_data_di_fine_fornita_ignorata(): void {
		$attesa = $this->date_attese( 15 )['fine'];
		$vicina = Passaggi::ora()->modify( '+2 days' )->format( 'Y-m-d' );
		$lonta  = Passaggi::ora()->modify( '+200 days' )->format( 'Y-m-d' );
		$chiave = conformita_core_chiave_fine_pubblicazione();

		// Controllo positivo: senza fine fornita.
		$senza = $this->atto_in_verifica();

		$this->assertIsArray( $this->pubblica_da_codice( $senza, $this->pubblicatore ) );
		$this->assertSame( $attesa, conformita_core_fine_pubblicazione( $senza ) );

		foreach ( array( $vicina, $lonta ) as $fornita ) {
			// Da codice.
			$codice = $this->atto_in_verifica();

			$this->assertIsArray(
				$this->pubblica_da_codice(
					$codice,
					$this->pubblicatore,
					array(
						'conferma'  => true,
						'data_fine' => $fornita,
					)
				)
			);
			$this->assertSame( $attesa, conformita_core_fine_pubblicazione( $codice ), 'Da codice: ' . $fornita );

			// Dalla schermata, con un campo costruito a mano.
			$schermata = $this->atto_in_verifica();

			$this->invia_riquadro(
				$schermata,
				$this->pubblicatore,
				array(
					RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
					RiquadroPassaggi::CAMPO_CONFERMA  => (string) $schermata,
					$chiave                           => $fornita,
					'data_fine'                       => $fornita,
				)
			);

			$this->assertSame( 'publish', get_post_status( $schermata ) );
			$this->assertSame( $attesa, conformita_core_fine_pubblicazione( $schermata ), 'Dalla schermata: ' . $fornita );

			// Gia' scritta nella bozza.
			$bozza = $this->atto_completo();

			update_post_meta( $bozza, $chiave, $fornita );

			$this->come(
				$this->redattore,
				static function () use ( $bozza ) {
					return wp_update_post(
						array(
							'ID'          => $bozza,
							'post_status' => 'pending',
						)
					);
				}
			);

			$this->assertSame( $fornita, conformita_core_fine_pubblicazione( $bozza ), 'Precondizione: la bozza porta la fine fornita.' );
			$this->assertIsArray( $this->pubblica_da_codice( $bozza, $this->pubblicatore ) );
			$this->assertSame( $attesa, conformita_core_fine_pubblicazione( $bozza ), 'Nella bozza: ' . $fornita );
			$this->assertSame( array( $attesa ), get_post_meta( $bozza, $chiave, false ), 'Un valore solo.' );
		}
	}

	/**
	 * A-100: ALBO-02, senza una durata la data di fine non si calcola e l'atto non si pubblica.
	 */
	public function test_a100_fine_non_calcolabile(): void {
		$id = $this->atto_in_verifica();

		$this->assertTrue( user_can( $this->pubblicatore, 'publish_post', $id ), 'Precondizione: puo\' pubblicare.' );

		$durata = get_term_meta( $this->voci['normativo'], META_DURATA, true );

		// Solo adesso, con l'atto in verifica, si toglie la durata al tipo.
		delete_term_meta( $this->voci['normativo'], META_DURATA );

		$prima = $this->fotografia( $id );

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertSame( array( 'albo_fine_non_calcolabile' ), $esito->get_error_codes() );
		$this->assertStringContainsString( 'data di fine', $esito->get_error_message() );

		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $id,
			)
		);

		$motivi = $this->come(
			$this->pubblicatore,
			static function () use ( $id ) {
				return Rifiuti::preleva_per_utente( $id );
			}
		);

		$this->assertArrayHasKey( 'albo_fine_non_calcolabile', $motivi );
		$this->assertStringContainsString( 'data di fine', $motivi['albo_fine_non_calcolabile'] );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Resta in verifica, identico, senza numero consumato.' );

		// Controllo positivo: ripristinata la durata, lo stesso atto si pubblica.
		add_term_meta( $this->voci['normativo'], META_DURATA, $durata );

		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );
		$this->pubblicato_con_date_del_sistema( $id, 15, 'Durata ripristinata' );
	}

	/**
	 * A-101: ALBO-01, alla pubblicazione i dati si ricontrollano uno per volta; le date le scrive il sistema.
	 */
	public function test_a101_dati_ricontrollati_alla_pubblicazione(): void {
		global $wpdb;

		$togli = array(
			'albo_manca_oggetto'              => static function ( int $id ) use ( $wpdb ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: il dato cambia fuori dal sito, scavalcando anche la barriera.
				$wpdb->update( $wpdb->posts, array( 'post_title' => '' ), array( 'ID' => $id ) );
			},
			'albo_manca_tipo'                 => static function ( int $id ) {
				wp_set_object_terms( $id, array(), TASSONOMIA_TIPO_ATTO );
			},
			'albo_manca_organo'               => static function ( int $id ) {
				wp_set_object_terms( $id, array(), TASSONOMIA_ORGANO );
			},
			'albo_manca_data_adozione'        => static function ( int $id ) {
				delete_post_meta( $id, META_DATA_ADOZIONE );
			},
			'albo_manca_documento_principale' => static function ( int $id ) {
				delete_post_meta( $id, META_DOCUMENTO_PRINCIPALE );
			},
		);

		foreach ( $togli as $codice => $toglie ) {
			$id = $this->atto_in_verifica();

			$this->scavalca_barriera(
				static function () use ( $toglie, $id ) {
					$toglie( $id );
				}
			);
			clean_post_cache( $id );

			$prima = $this->fotografia( $id );
			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

			$this->assertSame( array( $codice ), $esito->get_error_codes(), 'Il rifiuto nomina il dato che manca: ' . $codice );
			$this->assertSame( $prima, $this->fotografia( $id ), 'Resta in verifica, identico: ' . $codice );
		}

		// Controllo positivo: a un atto non e' mai stata fornita nessuna data; nessun allegato ulteriore.
		$id = $this->atto_in_verifica();

		$this->assertSame( array(), DocumentiAtto::ulteriori( $id ), 'Precondizione: nessun allegato ulteriore.' );
		$this->assertSame( '', conformita_core_fine_pubblicazione( $id ), 'Precondizione: nessuna fine prima della pubblicazione.' );
		$this->assertSame( '0000-00-00 00:00:00', get_post_field( 'post_date_gmt', $id ), 'Precondizione: nessun inizio fissato prima della pubblicazione.' );

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito );
		$this->pubblicato_con_date_del_sistema( $id, 15, 'Atto senza date fornite' );
		$this->assertSame( $esito['inizio'], get_post_field( 'post_date', $id ), 'Le due date nascono nello stesso istante.' );
		$this->assertSame( $esito['fine'], conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-102: ALBO-08 alla pubblicazione: il numero lo assegna il sistema, e nessuna richiesta lo sceglie.
	 */
	public function test_a102_numero_di_repertorio_alla_pubblicazione(): void {
		$primo   = $this->atto_in_verifica();
		$secondo = $this->atto_in_verifica();

		// Da codice, una richiesta che porta un numero e' rifiutata per intero.
		foreach ( array( 'numero', 'repertorio', 'anno' ) as $chiave ) {
			$esito = $this->pubblica_da_codice(
				$primo,
				$this->pubblicatore,
				array(
					'conferma' => true,
					$chiave    => 999,
				)
			);

			$this->assertSame( array( 'albo_richiesta_non_valida' ), $esito->get_error_codes() );
		}

		$this->assertNull( Repertorio::di( $primo ), 'Nessun numero preso da una richiesta rifiutata.' );

		// Dalla schermata, un campo costruito a mano si ignora.
		$this->invia_riquadro(
			$primo,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $primo,
				'numero'                          => '999',
				'albo_pretorio_numero'            => '999',
			)
		);

		$this->assertSame( 'publish', get_post_status( $primo ) );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => 1,
			),
			Repertorio::di( $primo ),
			'Il primo numero dopo la partenza dichiarata.'
		);

		$esito = $this->pubblica_da_codice( $secondo, $this->pubblicatore );

		$this->assertSame( 2, $esito['numero'], 'Progressivo.' );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => 2,
			),
			Repertorio::di( $secondo )
		);

		$voce = $this->voci_di( $secondo, array( 'azione' => Passaggi::AZIONE_CONFERMA ) );

		$this->assertSame( 2, $voce[0]['dettagli']['numero'], 'Il numero e\' anche nella voce di registro.' );
	}

	/**
	 * A-103: ALBO-22 e ALBO-27, da pubblicato non si torna indietro e non si cambia niente, da nessuno.
	 */
	public function test_a103_nessun_ritorno_da_pubblicato(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = $this->atto_in_verifica();

		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );

		$prima         = $this->fotografia( $id );
		$this->scritti = array();

		foreach ( array( $this->redattore, $this->pubblicatore, $this->amministratore ) as $utente ) {
			$this->come(
				$utente,
				function () use ( $id ) {
					foreach ( array( 'draft', 'pending', 'auto-draft', 'private', 'future', 'trash' ) as $stato ) {
						wp_update_post(
							array(
								'ID'          => $id,
								'post_status' => $stato,
							)
						);
					}

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

					$this->assertFalse( wp_trash_post( $id ), 'Non va nel cestino.' );
					$this->assertFalse( wp_delete_post( $id, true ), 'Non si cancella.' );

					$this->assertSame( array( 'albo_atto_non_in_verifica' ), albo_pretorio_rimanda_in_bozza( $id, 'Motivo.' )->get_error_codes() );
					$this->assertSame( array( 'albo_atto_non_in_verifica' ), albo_pretorio_pubblica( $id, array( 'conferma' => true ) )->get_error_codes() );

					// Dalla schermata di WordPress.
					set_current_screen( 'post' );

					$_POST = array(
						'action'               => 'editpost',
						'post_ID'              => (string) $id,
						'post_type'            => TIPO,
						'post_title'           => 'Oggetto dalla schermata',
						'original_post_status' => 'publish',
						'post_status'          => 'draft',
						'_wpnonce'             => wp_create_nonce( 'update-post_' . $id ),
					);

					try {
						edit_post();
					} catch ( \WPDieException $fermato ) {
						unset( $fermato );
					} finally {
						$_POST = array();
					}

					$this->assertArrayHasKey( 'albo_atto_pubblicato', Rifiuti::preleva_per_utente( $id ), 'Il rifiuto della schermata e\' spiegato.' );
				}
			);

			// ALBO-08: una richiesta manipolata che porta un numero, dal riquadro e da codice.
			$this->invia_riquadro(
				$id,
				$utente,
				array(
					RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
					RiquadroPassaggi::CAMPO_CONFERMA  => (string) $id,
					'numero'                          => '999',
					'albo_pretorio_numero'            => '999',
				)
			);
			$this->come(
				$utente,
				static function () use ( $id ) {
					return wp_update_post(
						array(
							'ID'         => $id,
							'meta_input' => array(
								'numero'                => '999',
								'albo_pretorio_numero'  => '999',
								'_albo_pretorio_numero' => '999',
							),
						)
					);
				}
			);

			$this->assertSame( $prima, $this->fotografia( $id ), 'Niente cambia, numero compreso, utente ' . $utente . '.' );
		}

		$this->assertSame( array(), $this->stati_scritti( $id ), 'Nessuna scrittura della riga: ogni richiesta si e\' fermata prima.' );
	}

	/**
	 * A-104: ALBO-22, una transizione rifiutata non lascia stati intermedi.
	 */
	public function test_a104_nessuno_stato_intermedio(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id            = $this->atto_in_verifica();
		$this->scritti = array();

		$this->pubblica_da_codice( $id, $this->redattore );
		$this->pubblica_da_codice( $id, $this->pubblicatore, array() );
		$this->pubblica_da_codice(
			$id,
			$this->pubblicatore,
			array(
				'conferma'    => true,
				'data_inizio' => Passaggi::ora()->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
			)
		);
		$this->come(
			$this->pubblicatore,
			static function () use ( $id ) {
				albo_pretorio_rimanda_in_bozza( $id, '' );

				foreach ( array( 'publish', 'future', 'draft' ) as $stato ) {
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => $stato,
						)
					);
				}
			}
		);

		$this->assertSame( array(), $this->stati_scritti( $id ), 'Nessuna scrittura: non ha mai attraversato uno stato richiesto e rifiutato.' );
		$this->assertSame( 'pending', get_post_status( $id ) );

		// Controllo positivo: il passaggio riuscito scrive una volta sola, e lo stato giusto.
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );
		$this->assertSame( array( 'publish' ), $this->stati_scritti( $id ) );
	}
}
