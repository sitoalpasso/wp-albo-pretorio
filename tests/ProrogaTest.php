<?php
/**
 * Proroga per disservizio: righe A-127..A-138 e A-147..A-155 (ALBO-31).
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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si osserva la banca dati da due connessioni.

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
	 * Un invio vero dell'anteprima della schermata, come l'utente indicato.
	 *
	 * @param int    $utente       Utente.
	 * @param string $dal          Primo giorno.
	 * @param string $al           Ultimo giorno.
	 * @param string $attestazione Attestazione.
	 * @return array<string, mixed>
	 */
	private function anteprima_schermata( int $utente, string $dal, string $al, string $attestazione ): array {
		return $this->invia_schermata(
			$utente,
			array(
				SchermataProroga::CAMPO_MODO         => 'anteprima',
				SchermataProroga::CAMPO_DAL          => $dal,
				SchermataProroga::CAMPO_AL           => $al,
				SchermataProroga::CAMPO_ATTESTAZIONE => $attestazione,
			)
		);
	}

	/**
	 * Un invio vero del pulsante che dispone la proroga, come l'utente indicato.
	 *
	 * @param int                   $utente Utente.
	 * @param string                $codice Codice dell'anteprima.
	 * @param array<string, string> $campi  Altri campi inviati insieme, che la conferma deve ignorare.
	 * @return array<string, mixed>
	 */
	private function conferma_schermata( int $utente, string $codice, array $campi = array() ): array {
		return $this->invia_schermata(
			$utente,
			array(
				SchermataProroga::CAMPO_MODO      => 'disponi',
				SchermataProroga::CAMPO_ANTEPRIMA => $codice,
			) + $campi
		);
	}

	/**
	 * Un invio vero della schermata, con il gettone, come l'utente indicato.
	 *
	 * @param int                   $utente Utente.
	 * @param array<string, string> $campi  Campi inviati.
	 * @return array<string, mixed>
	 */
	private function invia_schermata( int $utente, array $campi ): array {
		return $this->come(
			$utente,
			static function () use ( $campi ) {
				$_POST = array( SchermataProroga::CAMPO_GETTONE => wp_create_nonce( SchermataProroga::AZIONE ) ) + $campi;

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
		$anteprima = $this->anteprima_schermata( $this->pubblicatore, '2041-10-06', '', 'Attestazione prot. 124' );

		$this->assertArrayHasKey( 'anteprima', $anteprima, (string) ( $anteprima['errore'] ?? '' ) );
		$this->assertSame( '2041-10-18', $anteprima['anteprima'][ $id ]['nuova'] );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ), 'L\'anteprima non scrive.' );

		$disposta = $this->conferma_schermata( $this->pubblicatore, (string) $anteprima['codice'] );

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

		$schermata = $this->anteprima_schermata( $this->pubblicatore, '2041-10-05', '', '   ' );

		$this->assertArrayHasKey( 'errore', $schermata );
		$this->assertArrayNotHasKey( 'codice', $schermata, 'Nessuna anteprima da confermare.' );
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

			$schermata = $this->anteprima_schermata( $utente, '2041-10-05', '', 'Attestazione' );

			$this->assertArrayHasKey( 'errore', $schermata );
			$this->assertArrayNotHasKey( 'codice', $schermata, 'Nessuna anteprima da confermare.' );
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
		$tabella   = $wpdb->prefix . 'conformita_core_registro';
		$passate   = 0;
		$bloccate  = 0;
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
				static function ( $istruzione ) use ( $tabella, &$passate, &$bloccate ) {
					if ( ! is_string( $istruzione ) || 0 !== strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) || false === strpos( $istruzione, "'" . Proroga::AZIONE_ATTO . "'" ) ) {
						return $istruzione;
					}

					// La voce del terzo atto e' l'unica con la fine nuova del 19: i primi due vanno al 17 e al 18.
					if ( false !== strpos( $istruzione, '2041-10-19' ) ) {
						++$bloccate;

						return '';
					}

					++$passate;

					return $istruzione;
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

		$this->assertSame( array( 2, 1 ), array( $passate, $bloccate ), 'Il guasto e\' arrivato proprio alla voce del terzo, dopo quelle dei primi due.' );

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
		$anteprima = $this->anteprima_schermata( $this->pubblicatore, '2041-10-04', '2041-10-05', 'Attestazione' );

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

		// Prima della pubblicazione nessun atto era in pubblicazione: anteprima vuota, poi la sua conferma.
		$vuota = $this->anteprima_schermata( $this->pubblicatore, '2041-09-20', '', 'Attestazione' );

		$this->assertSame( array(), $vuota['anteprima'] ?? null, (string) ( $vuota['errore'] ?? '' ) );

		$esito = $this->conferma_schermata( $this->pubblicatore, (string) $vuota['codice'] );

		$this->assertSame( 0, $esito['disposta'] ?? null, (string) ( $esito['errore'] ?? '' ) );
		$this->assertSame( $prima, $this->fotografia( $id ) );
		$this->assertCount( 1, $this->voci_di_sezione( Proroga::AZIONE_PROROGA ) );
		$this->assertSame( array( '2041-09-20' ), Proroga::giorni_attestati() );
	}

	/**
	 * A-147: dalla schermata si conferma l'anteprima mostrata, e solo quella.
	 */
	public function test_a147_la_conferma_e_l_anteprima_mostrata(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$prima = $this->fotografia( $id );
		$altro = self::factory()->user->create( array( 'role' => \AlboPretorioPa\RUOLO ) );
		$primo = $this->anteprima_schermata( $this->pubblicatore, '2041-10-04', '2041-10-05', 'Attestazione A' );

		$this->assertSame( '2041-10-18', $primo['anteprima'][ $id ]['nuova'] ?? null, (string) ( $primo['errore'] ?? '' ) );

		// Senza codice, con un codice inventato, con il codice di un altro utente.
		foreach ( array( array( $this->pubblicatore, '' ), array( $this->pubblicatore, str_repeat( 'x', 32 ) ), array( $altro, (string) $primo['codice'] ) ) as $caso => $invio ) {
			$esito = $this->conferma_schermata( $invio[0], $invio[1] );

			$this->assertArrayHasKey( 'errore', $esito, 'Caso ' . $caso );
			$this->assertSame( $prima, $this->fotografia( $id ), 'Caso ' . $caso . ': nessuna data cambia.' );
			$this->nessuna_proroga( 'Caso ' . $caso );
		}

		// Una nuova anteprima sostituisce la prima: il pulsante rimasto aperto non conferma piu' niente.
		$secondo = $this->anteprima_schermata( $this->pubblicatore, '2041-10-06', '', 'Attestazione B' );

		$this->assertSame( '2041-10-17', $secondo['anteprima'][ $id ]['nuova'] ?? null, (string) ( $secondo['errore'] ?? '' ) );
		$this->assertArrayHasKey( 'errore', $this->conferma_schermata( $this->pubblicatore, (string) $primo['codice'] ) );
		$this->nessuna_proroga( 'Anteprima sostituita' );

		// I campi del modulo cambiati dopo l'anteprima non contano: si dispone quella mostrata.
		$esito = $this->conferma_schermata(
			$this->pubblicatore,
			(string) $secondo['codice'],
			array(
				SchermataProroga::CAMPO_DAL          => '2041-09-20',
				SchermataProroga::CAMPO_AL           => '2041-10-07',
				SchermataProroga::CAMPO_ATTESTAZIONE => 'Altro testo',
			)
		);

		$this->assertSame( 1, $esito['disposta'] ?? null, (string) ( $esito['errore'] ?? '' ) );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );
		$this->assertSame( array( '2041-10-06' ), Proroga::giorni_attestati() );
		$this->assertSame( 'Attestazione B', $this->voci_di_sezione( Proroga::AZIONE_PROROGA )[0]['motivazione'] );

		// L'anteprima si consuma: lo stesso codice non dispone una seconda volta, e lo dice.
		$ancora = $this->conferma_schermata( $this->pubblicatore, (string) $secondo['codice'] );

		$this->assertStringContainsString( 'non e\' piu\' quella valida', (string) ( $ancora['errore'] ?? '' ) );
		$this->assertCount( 1, $this->voci_di_sezione( Proroga::AZIONE_PROROGA ) );
	}

	/**
	 * A-148: un'anteprima superata da un'altra proroga non si conferma.
	 */
	public function test_a148_anteprima_superata_da_un_altra_proroga(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$anteprima = $this->anteprima_schermata( $this->pubblicatore, '2041-10-05', '', 'Attestazione A' );

		$this->assertSame( '2041-10-17', $anteprima['anteprima'][ $id ]['nuova'] ?? null, (string) ( $anteprima['errore'] ?? '' ) );

		// Nel frattempo un'altra persona attesta il 6.
		$this->assertIsArray(
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-06' ),
					'attestazione' => 'Attestazione B',
				)
			)
		);

		$prima = $this->fotografia( $id );
		$esito = $this->conferma_schermata( $this->pubblicatore, (string) $anteprima['codice'] );

		$this->assertArrayHasKey( 'errore', $esito );
		$this->assertStringContainsString( 'anteprima', (string) $esito['errore'] );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Niente scritto: la fine resta quella dell\'altra proroga.' );
		$this->assertSame( array( '2041-10-06' ), Proroga::giorni_attestati(), 'Il 5 resta attestabile.' );

		// Da codice, con il risultato atteso che non coincide: stesso rifiuto.
		$codice = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-05' ),
				'attestazione' => 'Attestazione A',
				'attese'       => array( $id => '2041-10-16>2041-10-17' ),
			)
		);

		$this->assertSame( array( 'albo_proroga_anteprima_superata' ), $codice->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ) );

		// Un'anteprima nuova mostra lo stato di adesso, e si conferma.
		$nuova = $this->anteprima_schermata( $this->pubblicatore, '2041-10-05', '', 'Attestazione A' );

		$this->assertSame( '2041-10-18', $nuova['anteprima'][ $id ]['nuova'] ?? null );
		$this->assertSame( 1, $this->conferma_schermata( $this->pubblicatore, (string) $nuova['codice'] )['disposta'] ?? null );
		$this->assertSame( '2041-10-18', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-148: un'anteprima superata dalla mezzanotte non si conferma.
	 */
	public function test_a148_anteprima_superata_dalla_mezzanotte(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		// L'atto e' uscito dalla vista il 16; il 10 e' sotto soglia.
		$this->fissa_orologi( '2041-10-17 23:50:00' );

		$anteprima = $this->anteprima_schermata( $this->pubblicatore, '2041-10-10', '', 'Attestazione' );

		$this->assertSame( '2041-10-18', $anteprima['anteprima'][ $id ]['nuova'] ?? null, (string) ( $anteprima['errore'] ?? '' ) );

		$prima = $this->fotografia( $id );

		$this->fissa_orologi( '2041-10-18 00:10:00' );

		$esito = $this->conferma_schermata( $this->pubblicatore, (string) $anteprima['codice'] );

		$this->assertArrayHasKey( 'errore', $esito );
		$this->assertSame( $prima, $this->fotografia( $id ), 'La fine mostrata non e\' piu\' quella che si scriverebbe.' );
		$this->nessuna_proroga( 'Dopo la mezzanotte' );

		$nuova = $this->anteprima_schermata( $this->pubblicatore, '2041-10-10', '', 'Attestazione' );

		$this->assertSame( '2041-10-19', $nuova['anteprima'][ $id ]['nuova'] ?? null );
	}

	/**
	 * A-149: le proroghe vanno una alla volta, e il calcolo legge cio' che la precedente ha confermato.
	 */
	public function test_a149_una_proroga_alla_volta(): void {
		global $wpdb;

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave      = conformita_core_chiave_fine_pubblicazione();
		$fatto       = false;
		$connessione = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$turno       = \AlboPretorioPa\Passaggi::nome_turno();
		$tabella     = $wpdb->prefix . 'conformita_core_registro';
		$durante     = null;

		/*
		 * Mentre la proroga scrive, cioe' dopo il calcolo, un'altra connessione
		 * prova a prendere il turno: non deve riuscirci.
		 */
		$guarda = static function ( $istruzione ) use ( $connessione, $turno, $tabella, &$durante ) {
			if ( null === $durante && is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_PROROGA . "'" ) ) {
				$durante = array(
					'libero' => (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ),
					'preso'  => (string) $connessione->get_var( $connessione->prepare( 'SELECT GET_LOCK( %s, 0 )', $turno ) ),
				);
			}

			return $istruzione;
		};

		// La fine e' gia' stata letta in questa richiesta: il calcolo deve rileggerla dalla banca dati.
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ) );

		/*
		 * Mentre questa proroga aspetta il turno, un'altra richiesta conferma la
		 * sua: il 6 attestato, la fine portata al 17. Si scrive con la
		 * connessione grezza, come la vedrebbe arrivare la banca dati.
		 */
		$altra = static function ( $istruzione ) use ( $wpdb, $id, $chiave, &$fatto ) {
			if ( ! $fatto && is_string( $istruzione ) && false !== strpos( $istruzione, 'GET_LOCK' ) ) {
				$fatto = true;

				mysqli_query( $wpdb->dbh, $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s", '2041-10-17', $id, $chiave ) ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- la scrittura dell'altra richiesta, fuori dai filtri.

				conformita_core_registra_voce(
					array(
						'sezione'  => SEZIONE,
						'azione'   => Proroga::AZIONE_GIORNO,
						'dettagli' => array( 'giorno' => '2041-10-06' ),
						'chiave'   => Proroga::chiave_giorno( '2041-10-06' ),
					)
				);
			}

			return $istruzione;
		};

		add_filter( 'query', $altra );
		add_filter( 'query', $guarda );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-04', '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_filter( 'query', $altra );
			remove_filter( 'query', $guarda );
		}

		$this->assertTrue( $fatto, 'Precondizione: la proroga prende il turno.' );
		$this->assertSame(
			array(
				'libero' => '0',
				'preso'  => '0',
			),
			$durante,
			'Mentre la proroga scrive, il turno e\' suo: un\'altra connessione non lo prende.'
		);
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-19', conformita_core_fine_pubblicazione( $id ), 'I due giorni si sommano al giorno dell\'altra proroga.' );

		$voci = $this->voci_di( $id, array( 'azione' => Proroga::AZIONE_ATTO ) );

		$this->assertCount( 1, $voci );
		$this->assertSame( '2041-10-17', $voci[0]['dettagli']['fine_precedente'], 'La fine precedente e\' quella confermata dall\'altra proroga.' );
		$this->assertSame( '2041-10-19', $voci[0]['dettagli']['fine_nuova'] );

		// Il turno e' stato lasciato: un'altra connessione lo prende subito.
		$this->assertSame( '1', (string) $connessione->get_var( $connessione->prepare( 'SELECT GET_LOCK( %s, 0 )', $turno ) ) );
		$this->assertSame( '1', (string) $connessione->get_var( $connessione->prepare( 'SELECT RELEASE_LOCK( %s )', $turno ) ) );

		$connessione->close();
	}

	/**
	 * A-150: durante una proroga, anche senza atti da prorogare, nessun altro passaggio comincia.
	 */
	public function test_a150_fermo_anche_senza_atti(): void {
		global $wpdb;

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$in_verifica = $this->atto_in_verifica();
		$prima       = $this->fotografia( $in_verifica );
		$tabella     = $wpdb->prefix . 'conformita_core_registro';
		$annidato    = null;

		// Prima di scrivere il secondo giorno, un aggancio prova a rimandare in bozza; poi quella scrittura fallisce.
		$guasto = static function ( $istruzione ) use ( $tabella, $in_verifica, &$annidato ) {
			if ( null === $annidato && is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_GIORNO . "'" ) && false !== strpos( $istruzione, '2041-09-21' ) ) {
				$annidato = albo_pretorio_rimanda_in_bozza( $in_verifica, 'Da completare.' );

				return '';
			}

			return $istruzione;
		};

		add_filter( 'query', $guasto );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-09-20', '2041-09-21' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_filter( 'query', $guasto );
		}

		$this->assertWPError( $annidato, 'Il rimando annidato e\' rifiutato.' );
		$this->assertSame( 'albo_passaggio_annidato', $annidato->get_error_code() );
		$this->assertWPError( $esito );
		$this->assertSame( 'albo_proroga_non_riuscita', $esito->get_error_code() );
		$this->assertSame( $prima, $this->fotografia( $in_verifica ), 'L\'atto in verifica resta com\'era.' );
		$this->nessuna_proroga( 'Rimando annidato' );

		// Finita la proroga, il rimando si fa.
		$this->assertTrue( ! is_wp_error( $this->come( $this->pubblicatore, static fn() => albo_pretorio_rimanda_in_bozza( $in_verifica, 'Da completare.' ) ) ) );
	}

	/**
	 * Fa girare una prova con i dati confermati e la scrittura automatica accesa, come su un sito.
	 *
	 * Serve quando una seconda connessione deve vedere e cambiare i dati della
	 * prova. Alla fine si toglie quello che la conferma ha reso definitivo e si
	 * rimettono le opzioni com'erano.
	 *
	 * @param callable $prova Riceve la seconda connessione.
	 */
	private function con_dati_confermati( callable $prova ): void {
		global $wpdb;

		$altra     = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$originali = $altra->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", OBJECT_K );
		$registro  = $wpdb->prefix . 'conformita_core_registro';
		$ultima    = (int) $altra->get_var( $altra->prepare( 'SELECT COALESCE( MAX( id ), 0 ) FROM %i', $registro ) );

		self::commit_transaction();
		$wpdb->query( 'SET autocommit = 1' );

		try {
			$prova( $altra );
		} finally {
			$wpdb->query( 'ROLLBACK' );
			$wpdb->query( 'SET autocommit = 1' );

			_delete_all_data();

			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id > %d', $registro, $ultima ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Repertorio::tabella_assegnazioni() ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Repertorio::tabella_anni() ) );

			$attuali = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", OBJECT_K );

			foreach ( $attuali as $nome => $riga ) {
				if ( ! isset( $originali[ $nome ] ) ) {
					$wpdb->delete( $wpdb->options, array( 'option_name' => $nome ) );
				} elseif ( $originali[ $nome ]->option_value !== $riga->option_value || $originali[ $nome ]->autoload !== $riga->autoload ) {
					$wpdb->update(
						$wpdb->options,
						array(
							'option_value' => $originali[ $nome ]->option_value,
							'autoload'     => $originali[ $nome ]->autoload,
						),
						array( 'option_name' => $nome )
					);
				}
			}

			foreach ( array_diff_key( $originali, $attuali ) as $nome => $riga ) {
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => $nome,
						'option_value' => $riga->option_value,
						'autoload'     => $riga->autoload,
					)
				);
			}

			wp_cache_flush();
			$altra->close();

			$wpdb->query( 'SET autocommit = 0' );
			self::commit_transaction();
		}
	}

	/**
	 * A-151: dentro la transazione di un altro programma, con una lettura coerente nata prima di un'altra proroga.
	 */
	public function test_a151_transazione_di_chi_chiama_con_dati_vecchi(): void {
		$this->con_dati_confermati(
			function ( \wpdb $altra ): void {
				global $wpdb;

				$id     = $this->pubblicato_il( '2041-10-01 09:30:00' );
				$chiave = conformita_core_chiave_fine_pubblicazione();
				$legge  = static function () use ( $altra, $wpdb, $id, $chiave ): string {
					return (string) $altra->get_var( $altra->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $chiave ) );
				};

				$this->fissa_orologi( '2041-10-08 11:00:00' );
				$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@autocommit' ), 'Precondizione: scrittura automatica accesa.' );
				$this->assertSame( '2041-10-16', $legge(), 'Precondizione: la pubblicazione e\' confermata.' );

				// Un programma apre la sua transazione e legge: la sua lettura coerente nasce qui.
				$wpdb->query( 'SET autocommit = 0' );

				try {
					$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ) );

					// Un'altra richiesta conferma la sua proroga: fine al 17.
					mysqli_query( $altra->dbh, $altra->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = %s", '2041-10-17', $id, $chiave ) ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- la scrittura dell'altra richiesta, fuori dai filtri.

					$this->assertSame( '2041-10-17', $legge(), 'Precondizione: l\'altra proroga e\' confermata.' );
					$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'Precondizione: il programma vede ancora la fine di prima.' );

					$esito = $this->proroga(
						$this->pubblicatore,
						array(
							'giorni'       => array( '2041-10-04', '2041-10-05' ),
							'attestazione' => 'Attestazione',
						)
					);

					$this->assertWPError( $esito );
					$this->assertSame( 'albo_proroga_lettura_superata', $esito->get_error_code(), $esito->get_error_message() );
				} finally {
					$wpdb->query( 'ROLLBACK' );
					$wpdb->query( 'SET autocommit = 1' );
				}

				$this->assertSame( '2041-10-17', $legge(), 'Niente scritto sopra la proroga dell\'altra richiesta.' );
				$this->nessuna_proroga( 'Dati vecchi' );

				// Fuori da quella transazione la proroga calcola dalla fine confermata.
				$esito = $this->proroga(
					$this->pubblicatore,
					array(
						'giorni'       => array( '2041-10-04', '2041-10-05' ),
						'attestazione' => 'Attestazione',
					)
				);

				$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
				$this->assertSame( '2041-10-19', $legge(), 'Confermata, e visibile alla seconda connessione.' );
				$this->assertSame( '2041-10-17', $this->voci_di( $id, array( 'azione' => Proroga::AZIONE_ATTO ) )[0]['dettagli']['fine_precedente'] );
			}
		);
	}

	/**
	 * A-152: la memoria dopo un'eccezione nel recupero di una presa rimasta del turno.
	 */
	public function test_a152_memoria_nel_recupero_del_turno(): void {
		global $wpdb;

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave = conformita_core_chiave_fine_pubblicazione();
		$turno  = \AlboPretorioPa\Passaggi::nome_turno();

		// Una proroga confermata il cui turno resta preso.
		$fallisce = static function ( $istruzione ) {
			return ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) ) ? '' : $istruzione;
		};

		add_filter( 'query', $fallisce );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Prima',
				)
			);
		} finally {
			remove_filter( 'query', $fallisce );
		}

		$this->assertSame( array( 'albo_proroga_turno_non_lasciato' ), $esito->get_error_codes(), 'Precondizione: turno rimasto preso.' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );

		// Alla proroga successiva, il rilascio di recupero rimette in memoria la fine di prima e solleva un'eccezione.
		$recuperi = 0;
		$solleva  = static function ( $istruzione ) use ( $id, $chiave, &$recuperi ) {
			if ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) && false === strpos( $istruzione, 'lasciato' ) ) {
				++$recuperi;
				wp_cache_set( $id, array( $chiave => array( '2041-10-16' ) ), 'post_meta' );

				throw new \LogicException( 'Componente di prova.' );
			}

			return $istruzione;
		};
		$preso    = null;

		add_filter( 'query', $solleva );

		try {
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-06' ),
					'attestazione' => 'Seconda',
				)
			);
		} catch ( \LogicException $errore ) {
			$preso = $errore;
		} finally {
			remove_filter( 'query', $solleva );
		}

		$this->assertSame( 1, $recuperi, 'Precondizione: il guasto e\' arrivato al rilascio di recupero.' );
		$this->assertInstanceOf( \LogicException::class, $preso );
		$this->assertSame( '2041-10-17', get_post_meta( $id, $chiave, true ), 'Memoria svuotata: non la fine rimessa dal componente.' );

		// La presa rimasta si lascia a mano; poi la proroga si fa.
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $turno ) );

		$this->assertIsArray(
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-06' ),
					'attestazione' => 'Terza',
				)
			)
		);
		$this->assertSame( '2041-10-18', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-152: il turno che la banca dati non conferma di aver lasciato, e l'eccezione durante il rilascio.
	 */
	public function test_a152_turno_non_lasciato(): void {
		global $wpdb;

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave      = conformita_core_chiave_fine_pubblicazione();
		$turno       = \AlboPretorioPa\Passaggi::nome_turno();
		$connessione = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );

		// La memoria ricorda la fine di prima: dopo la conferma deve essere svuotata comunque.
		$this->assertSame( '2041-10-16', get_post_meta( $id, $chiave, true ) );

		$fallisce = static function ( $istruzione ) {
			return ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) ) ? '' : $istruzione;
		};

		add_filter( 'query', $fallisce );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Prima',
				)
			);
		} finally {
			remove_filter( 'query', $fallisce );
		}

		$this->assertWPError( $esito );
		$this->assertSame( array( 'albo_proroga_turno_non_lasciato' ), $esito->get_error_codes() );
		$this->assertStringContainsString( 'confermata', $esito->get_error_message(), 'Dice che la proroga e\' confermata.' );
		$this->assertArrayHasKey( $id, $esito->get_error_data()['atti'] ?? array(), 'E porta l\'esito della proroga.' );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );
		$this->assertSame( '2041-10-17', get_post_meta( $id, $chiave, true ), 'Memoria svuotata.' );
		$this->assertSame( '0', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ), 'Il turno e\' davvero ancora preso, come dice il messaggio.' );

		// La proroga successiva sulla stessa connessione, senza lasciare il turno a mano: recupera la presa rimasta e lo lascia davvero.
		$recupero = $this->proroga(
			$this->pubblicatore,
			array(
				'giorni'       => array( '2041-10-04' ),
				'attestazione' => 'Recupero',
			)
		);

		$this->assertIsArray( $recupero, is_wp_error( $recupero ) ? $recupero->get_error_message() : '' );
		$this->assertSame( '2041-10-18', conformita_core_fine_pubblicazione( $id ) );
		$this->assertSame( '1', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ), 'Il turno e\' libero per le altre connessioni.' );

		// Eccezione durante il rilascio: la memoria si svuota lo stesso, e l'eccezione dice che la proroga e' confermata.
		$solleva = static function ( $istruzione ) use ( $id, $chiave ) {
			if ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) ) {
				// Il componente rimette in memoria la fine di prima: lo svuotamento finale deve toglierla.
				wp_cache_set( $id, array( $chiave => array( '2041-10-18' ) ), 'post_meta' );

				throw new \LogicException( 'Componente di prova.' );
			}

			return $istruzione;
		};
		$preso   = null;

		add_filter( 'query', $solleva );

		try {
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-06' ),
					'attestazione' => 'Seconda',
				)
			);
		} catch ( \RuntimeException $errore ) {
			$preso = $errore;
		} finally {
			remove_filter( 'query', $solleva );
		}

		$this->assertInstanceOf( \RuntimeException::class, $preso );
		$this->assertStringContainsString( 'confermata', $preso->getMessage() );
		$this->assertInstanceOf( \LogicException::class, $preso->getPrevious() );
		$this->assertSame( '2041-10-19', get_post_meta( $id, $chiave, true ), 'Memoria svuotata dopo l\'eccezione: non la fine rimessa dal componente.' );
		$this->assertSame( '2041-10-19', conformita_core_fine_pubblicazione( $id ) );
		$this->assertSame( '0', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ), 'Il turno e\' ancora preso.' );

		// Un'altra presa dello stesso turno sulla stessa connessione, presa durante la proroga: il rilascio della proroga riesce, ma il turno non e' libero.
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$doppia  = false;
		$prende  = static function ( $istruzione ) use ( $wpdb, $turno, $tabella, &$doppia ) {
			if ( ! $doppia && is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_PROROGA . "'" ) ) {
				$doppia = true;

				$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $turno ) );
			}

			return $istruzione;
		};

		add_filter( 'query', $prende );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-03' ),
					'attestazione' => 'Quarta',
				)
			);
		} finally {
			remove_filter( 'query', $prende );
		}

		$this->assertTrue( $doppia, 'Precondizione: la seconda presa c\'e\' stata.' );
		$this->assertWPError( $esito, 'Non dice che il turno e\' libero.' );
		$this->assertSame( array( 'albo_proroga_turno_non_lasciato' ), $esito->get_error_codes() );
		$this->assertSame( '2041-10-20', conformita_core_fine_pubblicazione( $id ), 'La proroga e\' confermata.' );
		$this->assertSame( '0', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ) );

		// Il fermo dell'operazione e' tolto: la proroga successiva si fa, recupera il turno e lo lascia.
		$this->assertIsArray(
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-07' ),
					'attestazione' => 'Terza',
				)
			)
		);
		$this->assertSame( '1', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ) );

		$connessione->close();
	}

	/**
	 * A-153: durante una proroga il nome nell'indirizzo di un atto pubblicato senza nome non si scrive.
	 */
	public function test_a153_nome_nell_indirizzo_solo_alla_pubblicazione(): void {
		global $wpdb;

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		// Un atto pubblicato senza nome nell'indirizzo, come uno importato.
		mysqli_query( $wpdb->dbh, $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_name = '' WHERE ID = %d", $id ) ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- il dato di partenza, fuori dai filtri.
		clean_post_cache( $id );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$prima   = $this->fotografia( $id );
		$chiave  = conformita_core_chiave_fine_pubblicazione();
		$tentato = false;
		$visto   = null;

		$this->assertSame( '', $prima['riga']['post_name'] ?? null, 'Precondizione: nessun nome.' );

		// Un componente assegna il nome quando la fine cambia; subito dopo si guarda se il guardiano lo ha fermato.
		$nomina = static function ( $meta_id, $atto_id, $meta_key ) use ( $wpdb, $id, $chiave, &$tentato, &$visto ) {
			if ( $id === (int) $atto_id && $chiave === $meta_key && ! $tentato ) {
				$tentato = true;

				$wpdb->update( $wpdb->posts, array( 'post_name' => 'nome-assegnato' ), array( 'ID' => $id ) );

				$visto = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", $id ) );
			}
		};

		add_action( 'updated_post_meta', $nomina, 10, 3 );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_action( 'updated_post_meta', $nomina, 10 );
		}

		$this->assertTrue( $tentato, 'Precondizione: il componente ha provato a scrivere il nome.' );
		$this->assertSame( '', $visto, 'Il guardiano ferma la scrittura del nome, prima del controllo finale.' );
		$this->assertWPError( $esito );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Ne\' il nome ne\' la fine cambiano.' );
		$this->nessuna_proroga( 'Nome assegnato durante la proroga' );

		// Il nome scritto scavalcando il guardiano: lo vede il controllo finale, e annulla.
		$tentato  = false;
		$scavalca = static function ( $meta_id, $atto_id, $meta_key ) use ( $wpdb, $id, $chiave, &$tentato ) {
			if ( $id === (int) $atto_id && $chiave === $meta_key && ! $tentato ) {
				$tentato = true;

				mysqli_query( $wpdb->dbh, $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_name = %s WHERE ID = %d", 'nome-assegnato', $id ) ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- la scrittura che scavalca il guardiano.
			}
		};

		add_action( 'updated_post_meta', $scavalca, 10, 3 );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_action( 'updated_post_meta', $scavalca, 10 );
		}

		$this->assertTrue( $tentato, 'Precondizione: il nome e\' stato scritto.' );
		$this->assertWPError( $esito );
		$this->assertContains( 'albo_atto_cambiato_nel_passaggio', $esito->get_error_codes(), $esito->get_error_message() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Annullato: ne\' il nome ne\' la fine cambiano.' );
		$this->nessuna_proroga( 'Nome scritto scavalcando il guardiano' );

		// Senza il componente la proroga si fa, e il nome resta vuoto.
		$this->assertIsArray(
			$this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			)
		);
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );
		$this->assertSame( '', $this->fotografia( $id )['riga']['post_name'] );
	}

	/**
	 * A-152: un rilascio con eccezione dopo una proroga annullata, o interrotta da un'eccezione, non lascia in memoria la fine tentata.
	 */
	public function test_a152_memoria_dopo_un_annullamento(): void {
		global $wpdb;

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave      = conformita_core_chiave_fine_pubblicazione();
		$tabella     = $wpdb->prefix . 'conformita_core_registro';
		$turno       = \AlboPretorioPa\Passaggi::nome_turno();
		$connessione = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$richiesta   = array(
			'giorni'       => array( '2041-10-05' ),
			'attestazione' => 'Attestazione',
		);

		// Durante il rilascio un componente rimette in memoria la fine tentata, e solleva un'eccezione.
		$rilascio = static function ( $istruzione ) use ( $id, $chiave ) {
			if ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) ) {
				wp_cache_set( $id, array( $chiave => array( '2041-10-17' ) ), 'post_meta' );

				throw new \LogicException( 'Componente di prova.' );
			}

			return $istruzione;
		};
		$guasti   = array(
			'scrittura fallita'      => static function ( $istruzione ) use ( $tabella ) {
				return ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_PROROGA . "'" ) ) ? '' : $istruzione;
			},
			'eccezione in scrittura' => static function ( $istruzione ) use ( $tabella ) {
				if ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_PROROGA . "'" ) ) {
					throw new \DomainException( 'Scrittura interrotta.' );
				}

				return $istruzione;
			},
		);

		foreach ( $guasti as $caso => $guasto ) {
			// Il turno rimasto preso dal caso precedente si lascia qui, fuori dalla prova.
			while ( '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $turno ) ) ) {
				continue;
			}

			$this->assertSame( '2041-10-16', get_post_meta( $id, $chiave, true ), $caso . ': precondizione.' );

			$esito = null;
			$preso = null;

			add_filter( 'query', $guasto );
			add_filter( 'query', $rilascio );

			try {
				$esito = $this->proroga( $this->pubblicatore, $richiesta );
			} catch ( \Throwable $errore ) {
				$preso = $errore;
			} finally {
				remove_filter( 'query', $guasto );
				remove_filter( 'query', $rilascio );
			}

			if ( 'scrittura fallita' === $caso ) {
				$this->assertWPError( $esito, $caso );
				$this->assertSame( 'albo_proroga_non_riuscita', $esito->get_error_code(), $caso . ': resta l\'errore della proroga.' );
				$this->assertContains( 'albo_proroga_turno_non_lasciato', $esito->get_error_codes(), $caso . ': e quello del turno.' );
			} else {
				$this->assertInstanceOf( \DomainException::class, $preso, $caso . ': esce l\'eccezione della scrittura.' );
			}

			$this->assertSame( '2041-10-16', get_post_meta( $id, $chiave, true ), $caso . ': nessuna fine tentata in memoria.' );
			$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), $caso );
			$this->nessuna_proroga( $caso );
		}

		// Rilascio riuscito, con il componente che rimette in memoria la fine tentata senza sollevare niente.
		while ( '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $turno ) ) ) {
			continue;
		}

		$rimette = static function ( $istruzione ) use ( $id, $chiave ) {
			if ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) ) {
				wp_cache_set( $id, array( $chiave => array( '2041-10-17' ) ), 'post_meta' );
			}

			return $istruzione;
		};

		add_filter( 'query', $guasti['scrittura fallita'] );
		add_filter( 'query', $rimette );

		try {
			$esito = $this->proroga( $this->pubblicatore, $richiesta );
		} finally {
			remove_filter( 'query', $guasti['scrittura fallita'] );
			remove_filter( 'query', $rimette );
		}

		$this->assertSame( array( 'albo_proroga_non_riuscita' ), array_slice( $esito->get_error_codes(), 0, 1 ) );
		$this->assertNotContains( 'albo_proroga_turno_non_lasciato', $esito->get_error_codes(), 'Il rilascio e\' riuscito.' );
		$this->assertSame( '2041-10-16', get_post_meta( $id, $chiave, true ), 'Rilascio riuscito: nessuna fine tentata in memoria.' );
		$this->assertSame( '1', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ) );

		// La proroga successiva recupera il turno, si fa e lo lascia.
		$this->assertIsArray( $this->proroga( $this->pubblicatore, $richiesta ) );
		$this->assertSame( '2041-10-17', get_post_meta( $id, $chiave, true ) );
		$this->assertSame( '1', (string) $connessione->get_var( $connessione->prepare( 'SELECT IS_FREE_LOCK( %s )', $turno ) ) );

		$connessione->close();
	}

	/**
	 * A-137: dopo l'annullamento la memoria non ricorda la fine tentata.
	 */
	public function test_a137_memoria_dopo_l_annullamento(): void {
		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00' );
		$secondo = $this->pubblicato_il( '2041-10-02 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave = conformita_core_chiave_fine_pubblicazione();
		$letta  = null;

		// Un aggancio comune legge la fine appena scritta del primo atto: la memoria la ricorda.
		$legge = static function ( $meta_id, $atto_id, $meta_key ) use ( $primo, $chiave, &$letta ) {
			if ( $primo === (int) $atto_id && $chiave === $meta_key ) {
				$letta = get_post_meta( $primo, $chiave, true );
			}
		};
		// Poi la fine del secondo non si scrive, e la proroga si annulla.
		$guasto = static function ( $esito, $atto_id, $meta_key ) use ( $secondo, $chiave ) {
			return ( $secondo === (int) $atto_id && $chiave === $meta_key ) ? false : $esito;
		};

		// Al rilascio del turno, prima dello svuotamento finale, la memoria e' gia' quella dell'annullamento.
		$nel_rilascio = 'non osservata';
		$osserva      = static function ( $istruzione ) use ( $primo, &$nel_rilascio ) {
			if ( is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) && false !== strpos( $istruzione, 'lasciato' ) ) {
				$nel_rilascio = wp_cache_get( $primo, 'post_meta' );
			}

			return $istruzione;
		};

		add_action( 'updated_post_meta', $legge, 10, 3 );
		add_filter( 'update_post_metadata', $guasto, 10, 3 );
		add_filter( 'query', $osserva );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_action( 'updated_post_meta', $legge, 10 );
			remove_filter( 'update_post_metadata', $guasto, 10 );
			remove_filter( 'query', $osserva );
		}

		$this->assertSame( '2041-10-17', $letta, 'Precondizione: la memoria ha visto la fine tentata.' );
		$this->assertFalse( $nel_rilascio, 'L\'annullamento stesso ha svuotato la memoria, prima del rilascio.' );
		$this->assertWPError( $esito );
		$this->assertSame( 'albo_proroga_non_riuscita', $esito->get_error_code() );

		// Prima di qualunque pulizia della prova: la memoria dice la fine vera.
		$this->assertSame( '2041-10-16', get_post_meta( $primo, $chiave, true ), 'La memoria non ricorda la fine annullata.' );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $primo ) );
		$this->nessuna_proroga( 'Annullata' );
	}

	/**
	 * A-154: durante il rilascio del turno, dopo la conferma o dopo l'annullamento, nessuna concessione resta.
	 */
	public function test_a154_nessuna_concessione_durante_il_rilascio(): void {
		global $wpdb;

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$in_verifica = $this->atto_in_verifica();
		$chiave      = conformita_core_chiave_fine_pubblicazione();
		$tabella     = $wpdb->prefix . 'conformita_core_registro';
		$tentativi   = array();

		// Durante il rilascio un componente prova a cancellare la fine, a scriverne un'altra e a rimandare in bozza.
		$rilascio = static function ( $istruzione ) use ( $id, $chiave, $in_verifica, &$tentativi ) {
			if ( array() === $tentativi && is_string( $istruzione ) && false !== strpos( $istruzione, 'RELEASE_LOCK' ) ) {
				$tentativi = array(
					'cancella' => delete_post_meta( $id, $chiave ),
					'scrive'   => update_post_meta( $id, $chiave, '2041-10-17' ),
					'rimanda'  => albo_pretorio_rimanda_in_bozza( $in_verifica, 'Da completare.' ),
				);
			}

			return $istruzione;
		};
		$annulla  = static function ( $istruzione ) use ( $tabella ) {
			return ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_PROROGA . "'" ) ) ? '' : $istruzione;
		};

		// Dopo l'annullamento.
		add_filter( 'query', $annulla );
		add_filter( 'query', $rilascio );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_filter( 'query', $annulla );
			remove_filter( 'query', $rilascio );
		}

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_proroga_non_riuscita', $esito->get_error_code() );
		$this->assertFalse( $tentativi['cancella'], 'Dopo l\'annullamento la fine non si cancella.' );
		$this->assertFalse( $tentativi['scrive'], 'Ne\' si scrive la fine tentata.' );
		$this->assertSame( 'albo_passaggio_annidato', is_wp_error( $tentativi['rimanda'] ) ? $tentativi['rimanda']->get_error_code() : null, 'Il fermo dell\'operazione resta fino alla fine del rilascio.' );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ) );
		$this->nessuna_proroga( 'Annullata' );

		// Dopo la conferma.
		$tentativi = array();
		$prima     = count( $this->voci_di( $id, array( 'azione' => 'modifica_fine_pubblicazione' ) ) );

		add_filter( 'query', $rilascio );

		try {
			$esito = $this->proroga(
				$this->pubblicatore,
				array(
					'giorni'       => array( '2041-10-06' ),
					'attestazione' => 'Attestazione',
				)
			);
		} finally {
			remove_filter( 'query', $rilascio );
		}

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertFalse( $tentativi['cancella'], 'Dopo la conferma la fine non si cancella.' );
		$this->assertFalse( $tentativi['scrive'], 'Ne\' se ne scrive un\'altra.' );
		$this->assertSame( 'albo_passaggio_annidato', is_wp_error( $tentativi['rimanda'] ) ? $tentativi['rimanda']->get_error_code() : null );
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ), 'La fine e\' quella della proroga.' );
		$this->assertCount( $prima + 1, $this->voci_di( $id, array( 'azione' => 'modifica_fine_pubblicazione' ) ), 'Una sola modifica della fine, quella della proroga.' );
		$this->assertCount( 1, $this->voci_di( $id, array( 'azione' => Proroga::AZIONE_ATTO ) ) );
		$this->assertSame( 'pending', get_post_status( $in_verifica ) );
	}

	/**
	 * A-155: finite le scritture, durante i controlli finali e la conferma, nessuna concessione resta sugli atti gia' controllati.
	 */
	public function test_a155_nessuna_concessione_durante_i_controlli(): void {
		global $wpdb;

		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00' );
		$secondo = $this->pubblicato_il( '2041-10-02 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$chiave  = conformita_core_chiave_fine_pubblicazione();
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$momenti = array(
			// Il controllo finale del secondo atto, quando il primo e' gia' controllato.
			'controlli' => static function ( string $istruzione ) use ( $wpdb, $secondo ): bool {
				return 1 === preg_match( '/^\s*SELECT \* FROM `?' . preg_quote( $wpdb->posts, '/' ) . '`? WHERE ID = ' . $secondo . '\s*$/', $istruzione );
			},
			// La conferma.
			'conferma'  => static function ( string $istruzione ): bool {
				return 1 === preg_match( '/^\s*(COMMIT|RELEASE SAVEPOINT)/i', $istruzione );
			},
		);
		$giorni  = array(
			'controlli' => '2041-10-05',
			'conferma'  => '2041-10-06',
		);

		foreach ( $momenti as $caso => $e_il_momento ) {
			$scritte    = false;
			$tentativi  = array();
			$intromette = static function ( $istruzione ) use ( $wpdb, $tabella, $primo, $secondo, $chiave, $e_il_momento, &$scritte, &$tentativi ) {
				if ( ! is_string( $istruzione ) || array() !== $tentativi ) {
					return $istruzione;
				}

				// L'ultima scrittura della proroga e' la voce del secondo atto.
				if ( 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Proroga::AZIONE_ATTO . "'" ) && false !== strpos( $istruzione, (string) $secondo ) ) {
					$scritte = true;

					return $istruzione;
				}

				if ( $scritte && $e_il_momento( $istruzione ) ) {
					$tentativi = array( 'in corso' );
					$tentativi = array(
						'cancella' => delete_post_meta( $primo, $chiave ),
						'diretta'  => $wpdb->delete(
							$wpdb->postmeta,
							array(
								'post_id'  => $primo,
								'meta_key' => $chiave, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- la scrittura dell'aggancio.
							)
						),
					);
				}

				return $istruzione;
			};

			add_filter( 'query', $intromette );

			try {
				$esito = $this->proroga(
					$this->pubblicatore,
					array(
						'giorni'       => array( $giorni[ $caso ] ),
						'attestazione' => 'Attestazione',
					)
				);
			} finally {
				remove_filter( 'query', $intromette );
			}

			$this->assertArrayHasKey( 'cancella', $tentativi, $caso . ': precondizione, l\'aggancio ha provato.' );
			$this->assertFalse( $tentativi['cancella'], $caso . ': la cancellazione con le funzioni di WordPress e\' rifiutata.' );
			$this->assertSame( 0, $tentativi['diretta'], $caso . ': la cancellazione diretta non tocca niente.' );
			$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		}

		$this->assertSame( array( '2041-10-18', '2041-10-19' ), array( conformita_core_fine_pubblicazione( $primo ), conformita_core_fine_pubblicazione( $secondo ) ), 'Tutti e due prorogati due volte, con la fine.' );
	}
}
