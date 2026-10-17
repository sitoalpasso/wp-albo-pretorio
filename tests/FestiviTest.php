<?php
/**
 * Slittamento della fine ai giorni festivi: righe A-139..A-143 e A-156..A-164 (ALBO-32).
 *
 * Gli atti si pubblicano con il passaggio vero, a orologio fissato su giorni
 * scelti perche' la fine cada di domenica, in un festivo dell'elenco o nel
 * lunedi' dopo Pasqua. Le date attese sono scritte per esteso: nell'ottobre
 * 2041 il 20 e' domenica, il 1 novembre venerdi', il 2 sabato e il 3 domenica;
 * Pasqua 2041 e' il 21 aprile.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Festivi;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Proroga;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\RiquadroPassaggi;
use AlboPretorioPa\SchermataFestivi;
use AlboPretorioPa\SchermataProroga;

use const AlboPretorioPa\SEZIONE;

/**
 * La scelta sui festivi, lo slittamento e la schermata.
 */
class FestiviTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'elenco delle prove: una festa annuale, un giorno singolo, il lunedi' dopo Pasqua, il patrono.
	 *
	 * @var string
	 */
	const ELENCO = "01/11 | Ognissanti (l. 260/1949 art. 2)\n02/11/2041 | Chiusura straordinaria\nPasqua+1 | Lunedi' dell'Angelo (l. 260/1949 art. 2)\n17/10 | Santo patrono";

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
	 * Salva la scelta da codice, come chi pubblica.
	 *
	 * @param string $scelta Scelta.
	 * @param string $elenco Elenco.
	 */
	private function scegli( string $scelta, string $elenco = self::ELENCO ): void {
		$this->assertTrue( Festivi::salva( $scelta, $elenco, $this->pubblicatore, Passaggi::ora() ) );
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
	 * La voce di conferma della pubblicazione di un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function conferma( int $atto_id ): array {
		$voci = $this->voci_di( $atto_id, array( 'azione' => 'conferma_dati_personali' ) );

		$this->assertCount( 1, $voci );

		return $voci[0]['dettagli'];
	}

	/**
	 * Un invio vero della schermata dei festivi, con o senza gettone, come l'utente indicato.
	 *
	 * @param int                  $utente  Utente.
	 * @param array<string, mixed> $campi   Campi inviati.
	 * @param string|null          $gettone Gettone; nullo per quello giusto.
	 * @return true|\WP_Error
	 */
	private function invia_festivi( int $utente, array $campi, ?string $gettone = null ) {
		return $this->come(
			$utente,
			static function () use ( $campi, $gettone ) {
				$_POST = array( SchermataFestivi::CAMPO_GETTONE => $gettone ?? wp_create_nonce( SchermataFestivi::AZIONE ) ) + $campi;

				try {
					return SchermataFestivi::elabora();
				} finally {
					$_POST = array();
				}
			}
		);
	}

	/**
	 * La schermata dei festivi stampata, come l'utente indicato.
	 *
	 * @param int $utente Utente.
	 * @return string
	 */
	private function schermata( int $utente ): string {
		return $this->come(
			$utente,
			static function () {
				ob_start();

				try {
					SchermataFestivi::mostra();
				} finally {
					$stampato = (string) ob_get_clean();
				}

				return $stampato;
			}
		);
	}

	/**
	 * Proroga da codice, come chi pubblica.
	 *
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @return mixed
	 */
	private function proroga( array $richiesta ) {
		return $this->come(
			$this->pubblicatore,
			static function () use ( $richiesta ) {
				return albo_pretorio_proroga( $richiesta );
			}
		);
	}

	/**
	 * Nessuna traccia di proroga nel registro.
	 *
	 * @param string $caso Caso, per i messaggi.
	 */
	private function nessuna_proroga( string $caso ): void {
		foreach ( array( Proroga::AZIONE_PROROGA, Proroga::AZIONE_GIORNO, Proroga::AZIONE_ATTO ) as $azione ) {
			$voci = conformita_core_voci_registro(
				array(
					'sezione' => SEZIONE,
					'azione'  => $azione,
				)
			);

			$this->assertSame( array(), $voci, $caso . ': nessuna voce ' . $azione . '.' );
		}
	}

	/**
	 * A-139: senza scelta la pubblicazione si rifiuta, senza consumare numeri, e la schermata non segna niente.
	 */
	public function test_a139_senza_scelta_non_si_pubblica(): void {
		delete_option( Festivi::OPZIONE );

		$id       = $this->atto_in_verifica();
		$prima    = $this->fotografia( $id );
		$prossimo = Repertorio::prossimo( 2041 );
		$esito    = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertSame( array( 'albo_festivi_non_scelti' ), $esito->get_error_codes() );
		$this->assertStringContainsString( 'Giorni festivi', $this->messaggi( $esito ), 'La frase rimanda alla schermata.' );
		$this->assertStringContainsString( 'resta in verifica', $this->messaggi( $esito ) );
		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto resta in verifica, identico.' );
		$this->assertSame( $prossimo, Repertorio::prossimo( 2041 ), 'Nessun numero consumato.' );

		$stampato = $this->schermata( $this->pubblicatore );

		$this->assertSame( 2, substr_count( $stampato, 'type="radio"' ), 'Le due scelte ci sono.' );
		$this->assertStringNotContainsString( 'checked', $stampato, 'Nessuna scelta segnata.' );

		// Controllo positivo: fatta la scelta, lo stesso atto si pubblica.
		$this->scegli( Festivi::SPENTO );
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );
	}

	/**
	 * A-140: domenica, festivo dell'elenco, festivo seguito da un giorno singolo e da una domenica; nel fuso del sito.
	 */
	public function test_a140_la_fine_va_al_primo_giorno_non_festivo(): void {
		$this->scegli( Festivi::ACCESO );

		// Mezzanotte e mezza del 5: in tempo universale e' ancora il 4, e la fine sarebbe il 19, un sabato.
		$domenica = $this->pubblicato_il( '2041-10-05 00:30:00' );

		$this->assertSame( '2041-10-21', conformita_core_fine_pubblicazione( $domenica ), 'Domenica 20, fine lunedi\' 21.' );

		// Fine calcolata il 1 novembre, festivo; il 2 e' un giorno singolo dell'elenco, il 3 domenica.
		$ognissanti = $this->pubblicato_il( '2041-10-17 10:00:00' );

		$this->assertSame( '2041-11-04', conformita_core_fine_pubblicazione( $ognissanti ), 'Dal 1 novembre al 4.' );

		// Il giorno singolo vale solo nel suo anno: nel 2040 venerdi' 2 novembre non e' festivo.
		$this->fissa_orologi( '2040-10-18 09:00:00' );
		$this->assertTrue( Repertorio::dichiara( 0, $this->amministratore, Passaggi::ora() ), 'Precondizione: il 2040 numera.' );

		$altro_anno = $this->pubblicato_il( '2040-10-18 10:00:00' );

		$this->assertSame( '2040-11-02', conformita_core_fine_pubblicazione( $altro_anno ), 'Il 2/11/2041 non vale nel 2040.' );

		// Lo stesso giorno scritto come festa annuale vale anche nel 2040: fine sabato 3.
		$this->scegli( Festivi::ACCESO, "02/11 | Commemorazione annuale\n17/10 | Santo patrono" );

		$annuale = $this->pubblicato_il( '2040-10-18 11:00:00' );

		$this->assertSame( '2040-11-03', conformita_core_fine_pubblicazione( $annuale ), 'Il 2/11 annuale vale nel 2040.' );

		// Il 1 novembre senza il giorno singolo: va al 2, un sabato.
		$this->scegli( Festivi::ACCESO, "01/11 | Ognissanti (l. 260/1949 art. 2)\n17/10 | Santo patrono" );

		$festivo = $this->pubblicato_il( '2041-10-17 11:00:00' );

		$this->assertSame( '2041-11-02', conformita_core_fine_pubblicazione( $festivo ), 'Venerdi\' 1 festivo, fine sabato 2.' );
	}

	/**
	 * A-141: spento, la fine resta di domenica.
	 */
	public function test_a141_spento_la_fine_resta(): void {
		$this->scegli( Festivi::SPENTO );

		$id = $this->pubblicato_il( '2041-10-05 09:30:00' );

		$this->assertSame( '2041-10-20', conformita_core_fine_pubblicazione( $id ), 'Domenica 20, e resta.' );
	}

	/**
	 * A-142: la proroga che porta la fine su un festivo la fa slittare.
	 */
	public function test_a142_slittamento_dopo_la_proroga(): void {
		$this->scegli( Festivi::ACCESO );

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), 'Precondizione: mercoledi\' 16, feriale.' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$esito = $this->proroga(
			array(
				'giorni'       => array( '2041-10-05' ),
				'attestazione' => 'Attestazione prot. 1',
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( '2041-10-18', conformita_core_fine_pubblicazione( $id ), 'Fine calcolata il 17, patrono: va al 18.' );
	}

	/**
	 * A-143: un festivo tolto dall'elenco, o la scelta cambiata, non tocca le fini gia' scritte.
	 */
	public function test_a143_le_fini_scritte_non_cambiano(): void {
		$this->scegli( Festivi::ACCESO );

		$id = $this->pubblicato_il( '2041-10-02 09:30:00' );

		$this->assertSame( '2041-10-18', conformita_core_fine_pubblicazione( $id ), 'Precondizione: fine calcolata il 17, patrono.' );

		$prima = $this->fotografia( $id );

		$this->scegli( Festivi::ACCESO, '01/11 | Ognissanti' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Tolto il patrono, la fine resta il 18.' );

		$this->scegli( Festivi::SPENTO, '' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Spento lo slittamento, la fine resta il 18.' );

		// La scadenza si legge dal dato: consultabile il 18, non il 19.
		$this->fissa_orologi( '2041-10-18 23:59:00' );
		$this->assertFalse( conformita_core_scaduto( $id ) );
		$this->fissa_orologi( '2041-10-19 00:00:01' );
		$this->assertTrue( conformita_core_scaduto( $id ) );
	}

	/**
	 * A-156: senza scelta anteprima e proroga si rifiutano, anche quando non toccherebbero nessun atto.
	 */
	public function test_a156_senza_scelta_non_si_proroga(): void {
		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		delete_option( Festivi::OPZIONE );
		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$prima = $this->fotografia( $id );

		foreach ( array( '2041-10-05', '2041-09-20' ) as $giorno ) {
			$richiesta = array(
				'giorni'       => array( $giorno ),
				'attestazione' => 'Attestazione',
			);
			$anteprima = $this->come(
				$this->pubblicatore,
				static function () use ( $richiesta ) {
					return Passaggi::anteprima_proroga( $richiesta );
				}
			);
			$proroga   = $this->proroga( $richiesta );

			foreach ( array( $anteprima, $proroga ) as $esito ) {
				$this->assertSame( array( 'albo_festivi_non_scelti' ), $esito->get_error_codes(), $giorno );
				$this->assertStringContainsString( 'Nessuna proroga disposta', $this->messaggi( $esito ) );
				$this->assertStringContainsString( 'Giorni festivi', $this->messaggi( $esito ) );
			}
		}

		$this->assertSame( $prima, $this->fotografia( $id ), 'Nessuna data cambiata.' );
		$this->nessuna_proroga( 'Senza scelta' );
	}

	/**
	 * A-157: il lunedi' dopo Pasqua, e il calcolo di Pasqua in anni diversi.
	 */
	public function test_a157_lunedi_dopo_pasqua(): void {
		$this->scegli( Festivi::ACCESO );

		$id = $this->pubblicato_il( '2041-04-07 09:30:00' );

		$this->assertSame( '2041-04-23', conformita_core_fine_pubblicazione( $id ), 'Lunedi\' 22 aprile 2041 e\' il giorno dopo Pasqua.' );

		$elenco = array(
			array(
				'data'        => 'Pasqua+1',
				'descrizione' => 'Lunedi dell\'Angelo',
			),
		);
		$attesi = array( '2026-04-06', '2027-03-29', '2038-04-26', '2041-04-22', '2285-03-23' );

		foreach ( $attesi as $atteso ) {
			$giorno = new \DateTimeImmutable( $atteso, wp_timezone() );

			$this->assertSame( 'Lunedi dell\'Angelo', Festivi::festivo( $giorno, $elenco ), $atteso );
			$this->assertNull( Festivi::festivo( $giorno->modify( '+1 day' ), $elenco ), $atteso . ', il giorno dopo.' );
		}
	}

	/**
	 * A-158: la schermata dice norma, scelte ed effetti; prima della scelta propone l'elenco e non segna niente.
	 */
	public function test_a158_schermata_dei_festivi(): void {
		delete_option( Festivi::OPZIONE );

		$stampato = $this->schermata( $this->pubblicatore );

		foreach ( array( 'art. 124', 'art. 2963', 'art. 155', '260/1949', 'Spostare la fine al primo giorno non festivo', 'Lasciare la fine dove cade', 'Per chi consulta l&#039;albo', 'Per chi prepara e pubblica gli atti' ) as $atteso ) {
			$this->assertStringContainsString( $atteso, $stampato );
		}

		$this->assertSame( 2, substr_count( $stampato, 'Per chi consulta l&#039;albo' ), 'Effetti per chi consulta, per ciascuna scelta.' );
		$this->assertSame( 2, substr_count( $stampato, 'Per chi prepara e pubblica gli atti' ), 'Effetti per chi pubblica, per ciascuna scelta.' );
		$this->assertStringNotContainsString( 'checked', $stampato );
		$this->assertStringContainsString( 'Pasqua+1 | Luned', $stampato, 'Proposta delle feste nazionali.' );
		$this->assertStringContainsString( '04/10 | San Francesco', $stampato );
		$this->assertStringContainsString( 'santo patrono', $stampato );

		// Dopo il salvataggio: segnata quella salvata, elenco salvato, chi e quando.
		$this->assertTrue(
			$this->invia_festivi(
				$this->pubblicatore,
				array(
					SchermataFestivi::CAMPO_SCELTA => Festivi::ACCESO,
					SchermataFestivi::CAMPO_ELENCO => "17/10 | Santo patrono\n# nota",
				)
			)
		);

		$stampato = $this->schermata( $this->pubblicatore );

		$this->assertMatchesRegularExpression( '/value="acceso" checked="checked"/', $stampato );
		$this->assertSame( 1, substr_count( $stampato, 'checked="checked"' ), 'Una sola scelta segnata.' );
		$this->assertStringContainsString( '17/10 | Santo patrono</textarea>', $stampato, 'L\'elenco salvato, senza le note.' );
		$this->assertStringNotContainsString( 'Pasqua+1 | Luned', $stampato, 'Niente proposta dopo il salvataggio.' );
		$this->assertMatchesRegularExpression( '/Ultimo salvataggio: ' . preg_quote( get_userdata( $this->pubblicatore )->display_name, '/' ) . ', il \\d{2}\/\\d{2}\/\\d{4} \\d{2}:\\d{2}\\./', $stampato, 'Chi e quando.' );

		// Spento con l'elenco vuoto: il campo resta vuoto, la proposta non torna.
		$this->assertTrue(
			$this->invia_festivi(
				$this->pubblicatore,
				array(
					SchermataFestivi::CAMPO_SCELTA => Festivi::SPENTO,
					SchermataFestivi::CAMPO_ELENCO => '',
				)
			)
		);

		$stampato = $this->schermata( $this->pubblicatore );

		$this->assertMatchesRegularExpression( '/value="spento" checked="checked"/', $stampato );
		$this->assertMatchesRegularExpression( '/<textarea[^>]*><\/textarea>/', $stampato );
	}

	/**
	 * A-159: gettone e permesso sul salvataggio.
	 */
	public function test_a159_salvataggio_protetto(): void {
		delete_option( Festivi::OPZIONE );

		$campi    = array(
			SchermataFestivi::CAMPO_SCELTA => Festivi::ACCESO,
			SchermataFestivi::CAMPO_ELENCO => self::ELENCO,
		);
		$estraneo = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$casi     = array(
			'senza gettone'     => array( $this->pubblicatore, '' ),
			'gettone sbagliato' => array( $this->pubblicatore, 'abc123' ),
			'chi redige'        => array( $this->redattore, null ),
			'senza permessi'    => array( $estraneo, null ),
		);

		foreach ( $casi as $caso => list( $utente, $gettone ) ) {
			$this->assertWPError( $this->invia_festivi( $utente, $campi, $gettone ), $caso );
			$this->assertNull( get_option( Festivi::OPZIONE, null ), $caso . ': niente salvato.' );
		}

		$this->assertTrue( $this->invia_festivi( $this->pubblicatore, $campi ), 'Controllo positivo.' );
		$this->assertSame( Festivi::ACCESO, Festivi::scelta() );

		// Chi redige non apre la schermata.
		$this->expectException( \WPDieException::class );
		$this->schermata( $this->redattore );
	}

	/**
	 * A-160: una riga sbagliata, o una scelta sbagliata, rifiutano tutto l'invio.
	 */
	public function test_a160_elenco_non_valido(): void {
		$this->scegli( Festivi::SPENTO, '25/12 | Natale' );

		$prima = get_option( Festivi::OPZIONE );
		$casi  = array(
			'31/02 | Inesistente'      => 'non esiste',
			'32/01 | Inesistente'      => 'non esiste',
			'25/04'                    => 'descrizione',
			'25/04 | '                 => 'descrizione',
			'domani | Festa'           => 'forma',
			'Pasqua+500 | Troppo dopo' => 'al massimo',
			'<b></b>'                  => 'descrizione',
			'<b>25/04</b> | Festa'     => 'forma',
		);

		foreach ( $casi as $riga => $motivo ) {
			$esito = $this->invia_festivi(
				$this->pubblicatore,
				array(
					SchermataFestivi::CAMPO_SCELTA => Festivi::ACCESO,
					SchermataFestivi::CAMPO_ELENCO => "01/11 | Ognissanti\n" . $riga . "\n08/12 | Immacolata",
				)
			);

			$this->assertStringContainsString( 'riga 2', $this->messaggi( $esito ), $riga );
			$this->assertStringContainsString( $motivo, $this->messaggi( $esito ), $riga );
			$this->assertSame( $prima, get_option( Festivi::OPZIONE ), $riga . ': niente salvato.' );
		}

		foreach ( array( 'forse', '' ) as $scelta ) {
			$this->assertWPError(
				$this->invia_festivi(
					$this->pubblicatore,
					array(
						SchermataFestivi::CAMPO_SCELTA => $scelta,
						SchermataFestivi::CAMPO_ELENCO => '01/11 | Ognissanti',
					)
				)
			);
			$this->assertSame( $prima, get_option( Festivi::OPZIONE ), 'Scelta "' . $scelta . '": niente salvato.' );
		}

		$this->assertWPError( $this->invia_festivi( $this->pubblicatore, array( SchermataFestivi::CAMPO_ELENCO => '01/11 | Ognissanti' ) ) );
		$this->assertSame( $prima, get_option( Festivi::OPZIONE ), 'Senza scelta: niente salvato.' );

		// Un elenco che non e' un testo, o che manca, non diventa un elenco vuoto: l'invio si rifiuta.
		$forme = array(
			'elenco come insieme' => array(
				SchermataFestivi::CAMPO_SCELTA => Festivi::ACCESO,
				SchermataFestivi::CAMPO_ELENCO => array( '01/11 | Ognissanti' ),
			),
			'elenco mancante'     => array( SchermataFestivi::CAMPO_SCELTA => Festivi::ACCESO ),
			'scelta come insieme' => array(
				SchermataFestivi::CAMPO_SCELTA => array( Festivi::ACCESO ),
				SchermataFestivi::CAMPO_ELENCO => '01/11 | Ognissanti',
			),
		);

		foreach ( $forme as $caso => $campi ) {
			$this->assertWPError( $this->invia_festivi( $this->pubblicatore, $campi ), $caso );
			$this->assertSame( $prima, get_option( Festivi::OPZIONE ), $caso . ': niente salvato.' );
		}

		foreach ( array( null, array(), 0 ) as $testo ) {
			$this->assertWPError( Festivi::salva( Festivi::ACCESO, $testo, $this->pubblicatore, Passaggi::ora() ) );
			$this->assertSame( $prima, get_option( Festivi::OPZIONE ), 'Testo non stringa da codice: niente salvato.' );
		}

		// L'elenco vuoto scritto apposta resta ammesso.
		$this->assertTrue(
			$this->invia_festivi(
				$this->pubblicatore,
				array(
					SchermataFestivi::CAMPO_SCELTA => Festivi::ACCESO,
					SchermataFestivi::CAMPO_ELENCO => '',
				)
			)
		);
		$this->assertSame( array(), Festivi::impostazione()['elenco'] );
	}

	/**
	 * A-161: il registro dice la fine calcolata, la scelta e i festivi saltati.
	 */
	public function test_a161_registro_della_pubblicazione_e_della_proroga(): void {
		$this->scegli( Festivi::ACCESO );

		$acceso = $this->pubblicato_il( '2041-10-18 10:00:00' );
		$voce   = $this->conferma( $acceso );

		$this->assertSame( '2041-11-04', $voce['fine'] );
		$this->assertSame( '2041-11-02', $voce['fine_calcolata'] );
		$this->assertSame( 'acceso', $voce['slittamento'] );
		$this->assertSame( array( '2041-11-02 (Chiusura straordinaria)', '2041-11-03 (Domenica)' ), $voce['festivi_saltati'] );

		// La proroga: fine calcolata il 17, patrono saltato.
		$prorogato = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );
		$this->assertIsArray(
			$this->proroga(
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
				)
			)
		);

		$voci = $this->voci_di( $prorogato, array( 'azione' => Proroga::AZIONE_ATTO ) );

		$this->assertCount( 1, $voci );
		$this->assertSame( '2041-10-18', $voci[0]['dettagli']['fine_nuova'] );
		$this->assertSame( '2041-10-17', $voci[0]['dettagli']['fine_calcolata'] );
		$this->assertSame( 'acceso', $voci[0]['dettagli']['slittamento'] );
		$this->assertSame( array( '2041-10-17 (Santo patrono)' ), $voci[0]['dettagli']['festivi_saltati'] );

		// Spento: la fine calcolata e' la fine, niente saltato.
		$this->scegli( Festivi::SPENTO );

		$spento = $this->pubblicato_il( '2041-10-05 09:30:00' );
		$voce   = $this->conferma( $spento );

		$this->assertSame( '2041-10-20', $voce['fine'] );
		$this->assertSame( '2041-10-20', $voce['fine_calcolata'] );
		$this->assertSame( 'spento', $voce['slittamento'] );
		$this->assertSame( array(), $voce['festivi_saltati'] );

		// La proroga con la scelta spento: fine nuova di domenica, resta li'.
		$prorogato_spento = $this->pubblicato_il( '2041-10-04 09:30:00' );

		$this->fissa_orologi( '2041-10-12 11:00:00' );
		$this->assertIsArray(
			$this->proroga(
				array(
					'giorni'       => array( '2041-10-10' ),
					'attestazione' => 'Attestazione',
				)
			)
		);

		$voci = $this->voci_di( $prorogato_spento, array( 'azione' => Proroga::AZIONE_ATTO ) );

		$this->assertCount( 1, $voci );
		$this->assertSame( '2041-10-20', $voci[0]['dettagli']['fine_nuova'] );
		$this->assertSame( '2041-10-20', $voci[0]['dettagli']['fine_calcolata'] );
		$this->assertSame( 'spento', $voci[0]['dettagli']['slittamento'] );
		$this->assertSame( array(), $voci[0]['dettagli']['festivi_saltati'] );
	}

	/**
	 * A-162: un'impostazione di forma diversa da quella del salvataggio vale come scelta non fatta.
	 */
	public function test_a162_impostazione_scritta_a_mano(): void {
		$valida = array(
			'scelta'  => Festivi::ACCESO,
			'elenco'  => array(
				array(
					'data'        => '01/11',
					'descrizione' => 'Ognissanti',
				),
			),
			'utente'  => $this->pubblicatore,
			'istante' => '2041-10-01 07:30:00',
		);
		// Ogni caso cambia una cosa sola della forma valida, nello stesso ordine di chiavi.
		$casi = array(
			'scelta sconosciuta'  => array_merge( $valida, array( 'scelta' => 'si' ) ),
			'chiavi mancanti'     => array( 'scelta' => Festivi::ACCESO ),
			'chiave in piu'       => array_merge( $valida, array( 'altro' => 1 ) ),
			'utente non numero'   => array_merge( $valida, array( 'utente' => (string) $this->pubblicatore ) ),
			'istante vuoto'       => array_merge( $valida, array( 'istante' => '' ) ),
			'istante non data'    => array_merge( $valida, array( 'istante' => 'non-data' ) ),
			'istante inesistente' => array_merge( $valida, array( 'istante' => '2041-02-30 07:30:00' ) ),
			'istante altra forma' => array_merge( $valida, array( 'istante' => '2041-10-01T07:30:00' ) ),
			'istante con nul'     => array_merge( $valida, array( 'istante' => "2041-10-01 07:30:00\0" ) ),
			'riga non valida'     => array_merge(
				$valida,
				array(
					'elenco' => array(
						array(
							'data'        => '31/02',
							'descrizione' => 'x',
						),
					),
				)
			),
			'riga non normale'    => array_merge(
				$valida,
				array(
					'elenco' => array(
						array(
							'data'        => '1/11',
							'descrizione' => 'Ognissanti',
						),
					),
				)
			),
			'non un insieme'      => Festivi::ACCESO,
		);

		$id    = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );

		foreach ( $casi as $caso => $valore ) {
			update_option( Festivi::OPZIONE, $valore );

			$this->assertNull( Festivi::scelta(), $caso );

			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

			$this->assertSame( array( 'albo_festivi_non_scelti' ), $esito->get_error_codes(), $caso );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso );

			// La schermata si apre, senza scelta segnata, per correggere l'impostazione.
			$stampato = $this->schermata( $this->pubblicatore );

			$this->assertStringContainsString( SchermataFestivi::CAMPO_ELENCO, $stampato, $caso );
			$this->assertStringNotContainsString( 'checked', $stampato, $caso );
		}

		// Controllo positivo: la forma del salvataggio vale.
		update_option( Festivi::OPZIONE, $valida );
		$this->assertSame( Festivi::ACCESO, Festivi::scelta() );
	}

	/**
	 * A-163: un festivo tolto fra anteprima e conferma rende l'anteprima superata.
	 */
	public function test_a163_anteprima_superata_dal_cambio_dell_elenco(): void {
		$this->scegli( Festivi::ACCESO );

		$id = $this->pubblicato_il( '2041-10-01 09:30:00' );

		$this->fissa_orologi( '2041-10-08 11:00:00' );

		$anteprima = $this->come(
			$this->pubblicatore,
			function () {
				$_POST = array(
					SchermataProroga::CAMPO_GETTONE      => wp_create_nonce( SchermataProroga::AZIONE ),
					SchermataProroga::CAMPO_MODO         => 'anteprima',
					SchermataProroga::CAMPO_DAL          => '2041-10-05',
					SchermataProroga::CAMPO_AL           => '',
					SchermataProroga::CAMPO_ATTESTAZIONE => 'Attestazione',
				);

				try {
					return SchermataProroga::elabora();
				} finally {
					$_POST = array();
				}
			}
		);

		$this->assertSame( '2041-10-18', $anteprima['anteprima'][ $id ]['nuova'] ?? null, (string) ( $anteprima['errore'] ?? '' ) );

		$this->scegli( Festivi::ACCESO, '01/11 | Ognissanti' );

		$prima = $this->fotografia( $id );
		$esito = $this->come(
			$this->pubblicatore,
			function () use ( $anteprima ) {
				$_POST = array(
					SchermataProroga::CAMPO_GETTONE   => wp_create_nonce( SchermataProroga::AZIONE ),
					SchermataProroga::CAMPO_MODO      => 'disponi',
					SchermataProroga::CAMPO_ANTEPRIMA => (string) $anteprima['codice'],
				);

				try {
					return SchermataProroga::elabora();
				} finally {
					$_POST = array();
				}
			}
		);

		$this->assertStringContainsString( 'anteprima', (string) ( $esito['errore'] ?? '' ) );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Niente scritto.' );
		$this->nessuna_proroga( 'Anteprima superata' );

		// Da codice, l'anteprima nuova si conferma.
		$nuova = $this->come(
			$this->pubblicatore,
			static function () {
				return Passaggi::anteprima_proroga(
					array(
						'giorni'       => array( '2041-10-05' ),
						'attestazione' => 'Attestazione',
					)
				);
			}
		);

		$this->assertSame( '2041-10-17', $nuova['atti'][ $id ]['nuova'] );
		$this->assertIsArray(
			$this->proroga(
				array(
					'giorni'       => array( '2041-10-05' ),
					'attestazione' => 'Attestazione',
					'attese'       => $nuova['attese'],
				)
			)
		);
		$this->assertSame( '2041-10-17', conformita_core_fine_pubblicazione( $id ) );
	}

	/**
	 * A-164: l'avviso in bacheca finche' la scelta manca, e il riquadro che annuncia lo slittamento.
	 */
	public function test_a164_avvisi(): void {
		delete_option( Festivi::OPZIONE );

		wp_set_current_user( $this->pubblicatore );
		$this->assertStringContainsString( SchermataFestivi::PAGINA, $this->avvisi_in_bacheca( true ) );

		wp_set_current_user( $this->redattore );
		$this->assertStringNotContainsString( SchermataFestivi::PAGINA, $this->avvisi_in_bacheca( true ) );

		$this->scegli( Festivi::ACCESO );

		wp_set_current_user( $this->pubblicatore );
		$this->assertStringNotContainsString( SchermataFestivi::PAGINA, $this->avvisi_in_bacheca( true ), 'Dopo la scelta l\'avviso sparisce.' );

		$id       = $this->atto_in_verifica();
		$riquadro = function () use ( $id ): string {
			return $this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					ob_start();
					RiquadroPassaggi::mostra( get_post( $id ) );
					return (string) ob_get_clean();
				}
			);
		};

		$this->assertStringContainsString( 'la fine passa al primo giorno seguente non festivo', $riquadro() );

		$this->scegli( Festivi::SPENTO );
		$this->assertStringNotContainsString( 'non festivo', $riquadro() );
	}
}
