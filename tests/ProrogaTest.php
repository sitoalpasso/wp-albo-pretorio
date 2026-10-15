<?php
/**
 * Proroga per disservizio: righe A-127..A-138 (ALBO-31).
 *
 * Gli atti si pubblicano con il passaggio vero, a orologio fissato; poi
 * l'orologio va avanti e la proroga si dispone da codice o dalla schermata.
 * Si guarda la data di fine scritta, il registro e la fotografia intera degli
 * atti che non devono cambiare. I valori attesi sono scritti per esteso.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Proroga;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchermataProroga;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\SEZIONE;

/**
 * La proroga, riuscita e rifiutata.
 */
class ProrogaTest extends \WP_UnitTestCase {

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
	 * Un atto del tipo normativo, 15 giorni, pubblicato nell'istante indicato.
	 *
	 * @param string $istante Data e ora nel fuso del sito.
	 * @return int
	 */
	private function pubblicato_il( string $istante ): int {
		$this->fissa_orologi( $istante );

		$id    = $this->atto_in_verifica();
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		return $id;
	}

	/**
	 * Dispone la proroga da codice, come l'utente indicato.
	 *
	 * @param int                  $utente    Utente.
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @return mixed
	 */
	private function proroga( int $utente, array $richiesta ) {
		return $this->come(
			$utente,
			static function () use ( $richiesta ) {
				return albo_pretorio_proroga( $richiesta );
			}
		);
	}

	/**
	 * Un invio vero della schermata, come l'utente indicato.
	 *
	 * @param int    $utente       Utente.
	 * @param string $modo         `anteprima` o `disponi`.
	 * @param string $dal          Primo giorno.
	 * @param string $al           Ultimo giorno.
	 * @param string $attestazione Attestazione.
	 * @return array<string, mixed>
	 */
	private function invia_schermata( int $utente, string $modo, string $dal, string $al, string $attestazione ): array {
		return $this->come(
			$utente,
			static function () use ( $modo, $dal, $al, $attestazione ) {
				$_POST = array(
					SchermataProroga::CAMPO_GETTONE      => wp_create_nonce( SchermataProroga::AZIONE ),
					SchermataProroga::CAMPO_MODO         => $modo,
					SchermataProroga::CAMPO_DAL          => $dal,
					SchermataProroga::CAMPO_AL           => $al,
					SchermataProroga::CAMPO_ATTESTAZIONE => $attestazione,
				);

				try {
					return SchermataProroga::elabora();
				} finally {
					$_POST = array();
				}
			}
		);
	}

	/**
	 * Le voci di sezione di un'azione della proroga.
	 *
	 * @param string $azione Azione.
	 * @return array<int, array<string, mixed>>
	 */
	private function voci_di_sezione( string $azione ): array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE,
				'azione'  => $azione,
			)
		);

		$this->assertIsArray( $voci );

		return $voci;
	}

	/**
	 * Nessuna traccia di proroga nel registro.
	 *
	 * @param string $caso Caso, per i messaggi.
	 */
	private function nessuna_proroga( string $caso ): void {
		foreach ( array( Proroga::AZIONE_PROROGA, Proroga::AZIONE_GIORNO, Proroga::AZIONE_ATTO ) as $azione ) {
			$this->assertSame( array(), $this->voci_di_sezione( $azione ), $caso . ': nessuna voce ' . $azione . '.' );
		}
	}

	/**
	 * A-127: un giorno sotto soglia, un atto in pubblicazione, da codice e dalla schermata.
	 */
	public function test_a127_proroga_di_un_giorno(): void {
		$id     = $this->pubblicato_il( '2041-10-01 09:30:00' );
		$prima  = $this->fotografia( $id );
		$numero = Repertorio::di( $id );

		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'Precondizione: 15 giorni dal 1 ottobre.' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$fine_prima = count( $this->voci_di( $id, array( 'azione' => 'modifica_fine_pubblicazione' ) ) );
		$esito      = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-05' ),
				'attestazione' => 'Attestazione prot. 123 del 7 ottobre',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ), 'La fine va avanti di un giorno.' );

		$dopo = $this->fotografia( $id );

		$this->assertSame( $prima['riga'], $dopo['riga'], 'Riga invariata: inizio, stato, oggetto.' );
		$this->assertSame( $numero, Repertorio::di( $id ), 'Numero di repertorio invariato.' );

		$voci = $this->voci_di( $id, array( 'azione' => Proroga::AZIONE_ATTO ) );

		$this->assertCount( 1, $voci );
		$this->assertSame( $this->pubblicatore, $voci[0]['utente'], 'Chi ha disposto.' );
		$this->assertSame( 'Attestazione prot. 123 del 7 ottobre', $voci[0]['motivazione'], 'L\'attestazione.' );
		$this->assertSame( '2041-10-16', $voci[0]['dettagli']['fine_precedente'] );
		$this->assertSame( '2041-10-17', $voci[0]['dettagli']['fine_nuova'] );
		$this->assertCount( $fine_prima + 1, $this->voci_di( $id, array( 'azione' => 'modifica_fine_pubblicazione' ) ), 'Il meccanismo comune registra la fine cambiata.' );
		$this->assertCount( 1, $this->voci_di_sezione( Proroga::AZIONE_PROROGA ) );
		$this->assertCount( 1, $this->voci_di_sezione( Proroga::AZIONE_GIORNO ) );

		// Dalla schermata: anteprima, poi la proroga.
		$anteprima = $this->invia_schermata( $this->pubblicatore, 'anteprima', '2041-10-06', '', 'Attestazione prot. 124' );

		$this->assertArrayHasKey( 'anteprima', $anteprima, (string) ( $anteprima['errore'] ?? '' ) );
		$this->assertSame( '2041-10-18', $anteprima['anteprima'][ $id ]['nuova'] );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ), 'L\'anteprima non scrive.' );

		$disposta = $this->invia_schermata( $this->pubblicatore, 'disponi', '2041-10-06', '', 'Attestazione prot. 124' );

		$this->assertSame( 1, $disposta['disposta'] ?? null, (string) ( $disposta['errore'] ?? '' ) );
		$this->assertSame( '2041-10-18', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-128: contano i giorni sotto soglia caduti nel periodo, non quello di pubblicazione.
	 */
	public function test_a128_contano_i_giorni_del_periodo(): void {
		$lungo  = $this->pubblicato_il( '2041-10-01 09:30:00' );
		$giorno = $this->pubblicato_il( '2041-10-06 10:00:00' );

		$this->assertSame( '2041-10-21', conformita_core_fine_pubblicazione( $giorno ), 'Precondizione.' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$esito = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-07', '2041-10-05', '2041-10-06' ),
				'attestazione' => 'Attestazione',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-19', conformita_core_fine_pubblicazione( $lungo ), 'Tre giorni nel periodo, tre giorni in piu\'.' );
		$this->assertSame( '2041-10-22', conformita_core_fine_pubblicazione( $giorno ), 'Un giorno solo: il 5 e\' prima, il 6 e\' il giorno di pubblicazione.' );
		$this->assertSame( array( '2041-10-05', '2041-10-06', '2041-10-07' ), $esito['giorni'], 'Giorni in ordine.' );
	}

	/**
	 * A-129: senza attestazione, vuota, di soli spazi.
	 */
	public function test_a129_attestazione_obbligatoria(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$prima = $this->fotografia( $id );

		foreach ( array( array(), array( 'attestazione' => '' ), array( 'attestazione' => "  \n\t " ), array( 'attestazione' => array( 'x' ) ) ) as $caso => $parte ) {
			$esito = $this->proroga( $this->pubblicatore, array( 'giorni' => array( '2041-10-05' ) ) + $parte );

			$this->assertSame( array( 'albo_proroga_senza_attestazione' ), $esito->get_error_codes(), 'Caso ' . $caso );
		}

		$schermata = $this->invia_schermata( $this->pubblicatore, 'disponi', '2041-10-05', '', '   ' );

		$this->assertArrayHasKey( 'errore', $schermata );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Nessuna data cambia.' );
		$this->nessuna_proroga( 'Senza attestazione' );
	}

	/**
	 * A-130: chi redige soltanto e chi non ha permessi dell'albo, da codice e da schermata.
	 */
	public function test_a130_solo_chi_pubblica(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$estraneo  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$prima     = $this->fotografia( $id );
		$richiesta = array(
			'giorni'       => array( '2041-10-05' ),
			'attestazione' => 'Attestazione',
		);

		foreach ( array( $this->redattore, $estraneo ) as $utente ) {
			$this->assertSame( array( 'albo_proroga_non_permessa' ), $this->proroga( $utente, $richiesta )->get_error_codes() );

			$anteprima = $this->come(
				$utente,
				static function () use ( $richiesta ) {
					return \AlboPretorioPa\Passaggi::anteprima_proroga( $richiesta );
				}
			);

			$this->assertSame( array( 'albo_proroga_non_permessa' ), $anteprima->get_error_codes(), 'Neanche l\'anteprima.' );

			$schermata = $this->invia_schermata( $utente, 'disponi', '2041-10-05', '', 'Attestazione' );

			$this->assertArrayHasKey( 'errore', $schermata );
		}

		$this->assertSame( $prima, $this->fotografia( $id ), 'Nessuna data cambia.' );
		$this->nessuna_proroga( 'Senza permesso' );

		// Controllo positivo.
		$this->assertIsArray( $this->proroga( $this->pubblicatore, $richiesta ) );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-131: un giorno di oggi o futuro, anche insieme a giorni passati; il giorno e' quello del fuso del sito.
	 */
	public function test_a131_solo_giorni_trascorsi(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		// Mezzanotte e mezza a Roma: in tempo universale e' ancora il 7.
		$this->fissa_orologi( '2041-10-08 00:30:00' );

		$prima = $this->fotografia( $id );

		foreach ( array( array( '2041-10-08' ), array( '2041-10-09' ), array( '2041-10-05', '2041-10-08' ), array( '2041-10-5' ), array( 'ieri' ) ) as $giorni ) {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => $giorni,
					'attestazione' => 'Attestazione',
				)
			);

			$this->assertWPError( $esito, implode( ',', $giorni ) );
		}

		$this->assertSame( $prima, $this->fotografia( $id ) );
		$this->nessuna_proroga( 'Giorni non trascorsi' );

		// Il 7 a Roma e' gia' finito.
		$esito = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-07' ),
				'attestazione' => 'Attestazione',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-132: bozze, atti in verifica e atti pubblicati dopo i giorni indicati non si toccano.
	 */
	public function test_a132_atti_non_toccati(): void {
		$this->fissa_orologi( '2041-10-01 09:30:00' );

		$bozza    = $this->atto_completo();
		$verifica = $this->atto_in_verifica();
		$dopo     = $this->pubblicato_il( '2041-10-06 09:00:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$foto = array();

		foreach ( array( $bozza, $verifica, $dopo ) as $atto ) {
			$foto[ $atto ] = $this->fotografia( $atto );
		}

		$esito = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-04', '2041-10-05' ),
				'attestazione' => 'Attestazione',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( array(), $esito['atti'] );

		foreach ( $foto as $atto => $prima ) {
			$this->assertSame( $prima, $this->fotografia( $atto ), 'Atto ' . $atto . ' identico.' );
		}
	}

	/**
	 * A-133: durante la proroga, un aggancio prova a scrivere sull'atto un'altra fine e un altro dato.
	 */
	public function test_a133_la_fine_avanza_solo_del_valore_calcolato(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave  = conformita_core_chiave_fine_pubblicazione();
		$tentato = 0;
		$intruso = static function ( $meta_id, $atto_id, $meta_key ) use ( $chiave, &$tentato ) {
			if ( $chiave !== $meta_key || $tentato > 0 ) {
				return;
			}

			++$tentato;
			update_post_meta( $atto_id, $chiave, '2042-12-31' );
			update_post_meta( $atto_id, META_DATA_ADOZIONE, '2000-01-01' );
		};

		add_action( 'updated_post_meta', $intruso, 10, 3 );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_action( 'updated_post_meta', $intruso, 10 );
		}

		$this->assertSame( 1, $tentato, 'Precondizione: l\'aggancio ha provato.' );
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ), 'La fine e\' quella calcolata.' );
		$this->assertSame( '2041-09-10', get_post_meta( $id, META_DATA_ADOZIONE, true ), 'Il dato non cambia.' );

		// Fuori dalla proroga la fine di un atto pubblicato non si muove.
		$this->assertFalse( update_post_meta( $id, $chiave, '2042-12-31' ) );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );

		// Un dato scritto a mano durante la proroga, senza le interfacce di WordPress: lo vede il controllo finale.
		$prima  = $this->fotografia( $id );
		$a_mano = static function ( $meta_id, $atto_id, $meta_key ) use ( $chiave ) {
			global $wpdb;

			if ( $chiave === $meta_key ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- prova: la scrittura che nessun filtro vede.
				$wpdb->insert(
					$wpdb->postmeta,
					array(
						'post_id'    => $atto_id,
						'meta_key'   => 'intruso', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- prova.
						'meta_value' => 'x', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- prova.
					)
				);
			}
		};

		add_action( 'updated_post_meta', $a_mano, 10, 3 );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-06' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_action( 'updated_post_meta', $a_mano, 10 );
		}

		$this->assertSame( 'albo_proroga_non_riuscita', $esito->get_error_code() );
		$this->assertContains( 'albo_atto_cambiato_nel_passaggio', $esito->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Annullato: l\'atto e\' identico.' );
	}

	/**
	 * A-135: un atto gia' uscito dalla vista conta i giorni dal giorno della proroga.
	 */
	public function test_a135_atto_gia_uscito(): void {
		$id = $this->pubblicato_il( '2041-09-25 09:30:00' );

		$this->assertSame( '2041-10-10', conformita_core_fine_pubblicazione( $id ), 'Precondizione.' );

		$this->fissa_orologi( '2041-10-13 11:00:00' );

		$this->assertTrue( conformita_core_scaduto( $id ), 'Precondizione: fuori dalla vista.' );

		$esito = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-10' ),
				'attestazione' => 'Attestazione',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-14', conformita_core_fine_pubblicazione( $id ), 'Dal 13, che non conta: il 14.' );
		$this->assertFalse( conformita_core_scaduto( $id ), 'Di nuovo consultabile.' );

		// L'11, il 12 e il 13 non erano giorni di pubblicazione; il 14 si'.
		$this->fissa_orologi( '2041-10-20 11:00:00' );

		$esito = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-11', '2041-10-12', '2041-10-13', '2041-10-14' ),
				'attestazione' => 'Attestazione',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 1, $esito['atti'][ $id ]['giorni'] );
		$this->assertSame( '2041-10-21', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-136: un giorno gia' attestato, da solo e con un giorno nuovo, anche scritto da un'altra richiesta.
	 */
	public function test_a136_un_giorno_si_attesta_una_volta(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$this->assertIsArray(
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Prima',
				)
			)
		);

		$prima = $this->fotografia( $id );

		foreach ( array( array( '2041-10-05' ), array( '2041-10-05', '2041-10-06' ) ) as $giorni ) {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => $giorni,
					'attestazione' => 'Seconda',
				)
			);

			$this->assertSame( array( 'albo_proroga_giorno_gia_attestato' ), $esito->get_error_codes() );
			$this->assertStringContainsString( '05/10/2041', $esito->get_error_message() );
		}

		$anteprima = $this->come(
			$this->pubblicatore,
			static function () {
				return \AlboPretorioPa\Passaggi::anteprima_proroga(
					array(
						'giorni'       => array( '2041-10-05' ),
						'attestazione' => 'Seconda',
					)
				);
			}
		);

		$this->assertSame( array( 'albo_proroga_giorno_gia_attestato' ), $anteprima->get_error_codes(), 'Gia\' dall\'anteprima, che non scrive.' );

		// Un'altra richiesta ha preso la chiave del 6 senza che la lettura lo veda: decide la banca dati.
		$this->assertIsInt(
			conformita_core_registra_voce(
				array(
					'sezione' => SEZIONE,
					'azione'  => 'prova_concorrente',
					'chiave'  => Proroga::chiave_giorno( '2041-10-06' ),
				)
			)
		);

		$esito = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-06' ),
				'attestazione' => 'Terza',
			)
		);

		$this->assertSame( 'albo_proroga_giorno_gia_attestato', $esito->get_error_code() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Nessun atto riceve due volte lo stesso giorno.' );
		$this->assertCount( 1, $this->voci_di_sezione( Proroga::AZIONE_PROROGA ) );
	}

	/**
	 * A-137: tutto o niente su piu' atti.
	 */
	public function test_a137_tutto_o_niente(): void {
		global $wpdb;

		$atti = array(
			$this->pubblicato_il( '2041-10-01 09:30:00' ),
			$this->pubblicato_il( '2041-10-02 09:30:00' ),
			$this->pubblicato_il( '2041-10-03 09:30:00' ),
		);

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$foto = array();

		foreach ( $atti as $atto ) {
			$foto[ $atto ] = $this->fotografia( $atto );
		}

		$chiave    = conformita_core_chiave_fine_pubblicazione();
		$secondo   = $atti[1];
		$terzo     = $atti[2];
		$tabella   = $wpdb->prefix . 'conformita_core_registro';
		$richiesta = array(
			'giorni'       => array( '2041-10-05' ),
			'attestazione' => 'Attestazione',
		);
		$guasti    = array(
			'fine del secondo'   => array(
				'update_post_metadata',
				static function ( $esito, $atto_id, $meta_key ) use ( $chiave, $secondo ) {
					return ( $secondo === (int) $atto_id && $chiave === $meta_key ) ? false : $esito;
				},
				3,
			),
			'voce del terzo'     => array(
				'query',
				static function ( $istruzione ) use ( $tabella, $terzo ) {
					return ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_ATTO . "'" ) && false !== strpos( $istruzione, (string) $terzo ) ) ? '' : $istruzione;
				},
				1,
			),
			'voce della proroga' => array(
				'query',
				static function ( $istruzione ) use ( $tabella ) {
					return ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_PROROGA . "'" ) ) ? '' : $istruzione;
				},
				1,
			),
			'voce del giorno'    => array(
				'query',
				static function ( $istruzione ) use ( $tabella ) {
					return ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_GIORNO . "'" ) ) ? '' : $istruzione;
				},
				1,
			),
		);

		foreach ( $guasti as $caso => $guasto ) {
			add_filter( $guasto[0], $guasto[1], 10, $guasto[2] );

			try {
				$esito = $this->proroga( $this->pubblicatore, $richiesta );
			} finally {
				remove_filter( $guasto[0], $guasto[1], 10 );
			}

			$this->assertWPError( $esito, $caso );
			$this->assertSame( 'albo_proroga_non_riuscita', $esito->get_error_code(), $caso . ': ' . $esito->get_error_message() );

			foreach ( $atti as $atto ) {
				$this->assertSame( $foto[ $atto ], $this->fotografia( $atto ), $caso . ': atto ' . $atto . ' identico.' );
			}

			$this->nessuna_proroga( $caso );
		}

		// Tolti i guasti, il giorno e' ancora attestabile.
		$esito = $this->proroga( $this->pubblicatore, $richiesta );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( array( '2041-10-17', '2041-10-18', '2041-10-19' ), array_map( 'conformita_core_fine_pubblicazione', $atti ) );
	}

	/**
	 * A-138: l'anteprima non scrive; una proroga senza atti si registra lo stesso.
	 */
	public function test_a138_anteprima_e_proroga_senza_atti(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$prima     = $this->fotografia( $id );
		$anteprima = $this->invia_schermata( $this->pubblicatore, 'anteprima', '2041-10-04', '2041-10-05', 'Attestazione' );

		$this->assertArrayHasKey( 'anteprima', $anteprima, (string) ( $anteprima['errore'] ?? '' ) );
		$this->assertSame(
			array(
				'fine'    => '2041-10-16',
				'nuova'   => '2041-10-18',
				'giorni'  => 2,
				'numero'  => '1/2041',
				'oggetto' => 'Atto di prova',
			),
			$anteprima['anteprima'][ $id ]
		);
		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'anteprima non scrive niente.' );
		$this->nessuna_proroga( 'Anteprima' );

		// Prima della pubblicazione nessun atto era in pubblicazione.
		$esito = $this->invia_schermata( $this->pubblicatore, 'disponi', '2041-09-20', '', 'Attestazione' );

		$this->assertSame( 0, $esito['disposta'] ?? null, (string) ( $esito['errore'] ?? '' ) );
		$this->assertSame( $prima, $this->fotografia( $id ) );
		$this->assertCount( 1, $this->voci_di_sezione( Proroga::AZIONE_PROROGA ) );
		$this->assertSame( array( '2041-09-20' ), Proroga::giorni_attestati() );
	}
}
