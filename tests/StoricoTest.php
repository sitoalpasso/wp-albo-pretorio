<?php
/**
 * Lo storico pubblico, primo passo: righe A-261..A-265 (ALBO-36).
 *
 * L'interruttore con gli anni, la schermata che spiega la scelta, il permesso
 * apposito, la seconda sezione e il tipo della copia. Nessuna copia si crea
 * ancora e niente compare sul sito pubblico: sono i passi successivi.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Installazione;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\SchermataStorico;
use AlboPretorioPa\Storico;
use AlboPretorioPa\TipoAtto;

use const AlboPretorioPa\OPZIONE_VERSIONE;
use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\SEZIONE_STORICO;
use const AlboPretorioPa\TIPO;
use const AlboPretorioPa\TIPO_STORICO;

/**
 * Prove del primo passo dello storico pubblico.
 */
class StoricoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Utente che ha soltanto il permesso dello storico.
	 *
	 * @var int
	 */
	private $custode = 0;

	/**
	 * Prepara un sito con l'albo avviato e nessuna scelta sullo storico.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->custode = $this->utente_con( array( Permessi::CHIAVE_STORICO ) );

		set_current_screen( 'dashboard' );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();

		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * La schermata come la vede l'utente indicato, o nullo se la rifiuta.
	 *
	 * @param int $utente Utente.
	 * @return string|null
	 */
	private function schermata( int $utente ): ?string {
		wp_set_current_user( $utente );

		ob_start();

		try {
			SchermataStorico::mostra();
		} catch ( \WPDieException $rifiuto ) {
			ob_end_clean();
			$this->assertStringContainsString( 'permesso di gestire lo storico', $rifiuto->getMessage() );

			return null;
		}

		return (string) ob_get_clean();
	}

	/**
	 * Un invio della schermata, come lo fa il modulo, per l'utente indicato.
	 *
	 * @param int                  $utente Utente.
	 * @param array<string, mixed> $campi  Campi inviati, senza il gettone.
	 * @param bool                 $con_gettone Aggiunge il gettone valido.
	 * @return true|\WP_Error
	 */
	private function invia( int $utente, array $campi, bool $con_gettone = true ) {
		wp_set_current_user( $utente );

		if ( $con_gettone ) {
			$campi[ SchermataStorico::CAMPO_GETTONE ] = wp_create_nonce( SchermataStorico::AZIONE );
		}

		// Senza indicazione, il modulo e' aperto adesso: porta lo stato in vigore.
		if ( ! array_key_exists( SchermataStorico::CAMPO_MOSTRATO, $campi ) ) {
			$campi[ SchermataStorico::CAMPO_MOSTRATO ] = Storico::stato_mostrato();
		}

		$_POST    = $campi;
		$_REQUEST = $campi;

		return SchermataStorico::elabora();
	}

	/**
	 * Le voci di registro dello storico sull'impostazione, dalla prima.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function voci_impostazione(): array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE_STORICO,
				'azione'  => Storico::AZIONE_IMPOSTAZIONE,
				'ordine'  => 'crescente',
			)
		);

		$this->assertIsArray( $voci );

		return $voci;
	}

	/**
	 * I dettagli di una voce come coppie di nome e valore.
	 *
	 * @param array<string, mixed> $voce Voce del registro.
	 * @return array<string, mixed>
	 */
	private function dettagli( array $voce ): array {
		$coppie = array();

		foreach ( (array) $voce['dettagli'] as $nome => $valore ) {
			if ( is_array( $valore ) && isset( $valore['nome'] ) ) {
				$coppie[ (string) $valore['nome'] ] = $valore['valore'] ?? null;
			} else {
				$coppie[ (string) $nome ] = $valore;
			}
		}

		return $coppie;
	}

	/**
	 * A-261: finche' l'amministrazione non sceglie, lo storico e' spento.
	 *
	 * La presenza di controllo e' l'impostazione scritta bene, che accende: le
	 * forme sbagliate valgono spento perche' sono sbagliate, non perche' la
	 * lettura non accende mai.
	 */
	public function test_a261_spento_finche_non_si_sceglie(): void {
		$this->assertNull( get_option( Storico::OPZIONE, null ), 'Precondizione: sito senza scelta.' );
		$this->assertFalse( Storico::acceso(), 'Senza scelta lo storico e\' spento.' );
		$this->assertNull( Storico::anni(), 'Senza scelta non ci sono anni.' );

		$contenuto = (string) $this->schermata( $this->amministratore );

		$this->assertStringContainsString( esc_html( 'La scelta non e\' ancora fatta' ), $contenuto, 'La schermata dice che la scelta manca.' );
		$this->assertStringNotContainsString( 'checked', $contenuto, 'Nessuna scelta segnata.' );
		$this->assertSame( 1, preg_match( '/name="' . preg_quote( SchermataStorico::CAMPO_ANNI, '/' ) . '" value=""/', $contenuto ), 'Nessun numero di anni proposto.' );

		$buona = array(
			'scelta'  => Storico::ACCESO,
			'anni'    => 5,
			'utente'  => $this->amministratore,
			'istante' => '2041-10-01 08:00:00',
		);

		update_option( Storico::OPZIONE, $buona );
		$this->assertTrue( Storico::acceso(), 'Presenza di controllo: l\'impostazione scritta bene accende.' );
		$this->assertSame( 5, Storico::anni() );

		foreach ( array(
			'scelta sconosciuta' => array( 'scelta' => 'forse' ) + $buona,
			'acceso senza anni'  => array( 'anni' => null ) + $buona,
			'zero anni'          => array( 'anni' => 0 ) + $buona,
			'cento anni'         => array( 'anni' => 100 ) + $buona,
			'anni come testo'    => array( 'anni' => '5' ) + $buona,
			'senza istante'      => array( 'istante' => '' ) + $buona,
			'non un elenco'      => 'acceso',
			'utente come testo'  => array( 'utente' => '1' ) + $buona,
		) as $caso => $valore ) {
			update_option( Storico::OPZIONE, $valore );

			$this->assertNull( Storico::impostazione(), $caso . ': l\'impostazione non si legge.' );
			$this->assertFalse( Storico::acceso(), $caso . ': lo storico e\' spento.' );
			$this->assertNull( Storico::anni(), $caso . ': nessun anno.' );
		}

		$this->assertStringContainsString( esc_html( 'L\'impostazione salvata non si legge' ), (string) $this->schermata( $this->amministratore ), 'La schermata lo dice.' );
	}

	/**
	 * A-262: la schermata spiega norma, scelte ed effetti.
	 */
	public function test_a262_la_schermata_spiega_la_scelta(): void {
		$contenuto = (string) $this->schermata( $this->amministratore );

		foreach ( array(
			'provvedimento n. 243 del 2014',
			'paragrafo 3.a',
			'paragrafo 2.b',
			'paragrafo 2.a',
			'legge 241/1990',
			'articolo 2-septies, comma 8',
			'articolo 8, comma 3, del d.lgs. 33/2013',
			'data di oblio',
			'Per chi consulta',
			'Per chi lavora gli atti',
			'consultazione riservata',
			'registro delle modifiche',
			'Nessun numero e\' proposto',
		) as $atteso ) {
			$this->assertStringContainsString( esc_html( $atteso ), $contenuto, 'La schermata dice: ' . $atteso );
		}

		$this->assertSame( 2, preg_match_all( '/type="radio"/', $contenuto ), 'Due scelte.' );
		$this->assertSame( 2, substr_count( $contenuto, esc_html( 'Per chi consulta' ) ), 'Ogni scelta dice l\'effetto per chi consulta.' );
		$this->assertSame( 2, substr_count( $contenuto, esc_html( 'Per chi lavora gli atti' ) ), 'Ogni scelta dice l\'effetto per chi lavora gli atti.' );
	}

	/**
	 * A-263: il salvataggio, i rifiuti e il registro.
	 */
	public function test_a263_salvataggio_e_registro(): void {
		$scelta = SchermataStorico::CAMPO_SCELTA;
		$anni   = SchermataStorico::CAMPO_ANNI;

		foreach ( array(
			'nessuna scelta'      => array( array( $anni => '5' ), 'albo_copia_scelta_mancante' ),
			'scelta sconosciuta'  => array(
				array(
					$scelta => 'forse',
					$anni   => '5',
				),
				'albo_copia_scelta_mancante',
			),
			'acceso senza anni'   => array( array( $scelta => Storico::ACCESO ), 'albo_copia_anni_mancanti' ),
			'acceso, anni vuoti'  => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => '  ',
				),
				'albo_copia_anni_mancanti',
			),
			'zero anni'           => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => '0',
				),
				'albo_copia_anni_non_validi',
			),
			'cento anni'          => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => '100',
				),
				'albo_copia_anni_non_validi',
			),
			'anni con parole'     => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => '5 anni',
				),
				'albo_copia_anni_non_validi',
			),
			'anni con la virgola' => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => '3,5',
				),
				'albo_copia_anni_non_validi',
			),
			'anni negativi'       => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => '-5',
				),
				'albo_copia_anni_non_validi',
			),
			'anni come elenco'    => array(
				array(
					$scelta => Storico::ACCESO,
					$anni   => array( '5' ),
				),
				'albo_copia_anni_non_validi',
			),
			'spento, anni errati' => array(
				array(
					$scelta => Storico::SPENTO,
					$anni   => 'mai',
				),
				'albo_copia_anni_non_validi',
			),
		) as $caso => $prova ) {
			$esito = $this->invia( $this->amministratore, $prova[0] );

			$this->assertWPError( $esito, $caso . ': rifiutato.' );
			$this->assertSame( $prova[1], $esito->get_error_code(), $caso . ': con il suo motivo.' );
			$this->assertNull( get_option( Storico::OPZIONE, null ), $caso . ': niente scritto.' );
			$this->assertSame( array(), $this->voci_impostazione(), $caso . ': niente nel registro.' );
		}

		$this->assertStringContainsString( 'Impostazione non salvata', (string) $this->schermata( $this->amministratore ), 'Il motivo dell\'ultimo rifiuto arriva sulla schermata.' );

		$this->fissa_orologi( '2041-10-01 10:00:00' );
		$this->assertTrue(
			$this->invia(
				$this->amministratore,
				array(
					$scelta => Storico::ACCESO,
					$anni   => ' 5 ',
				)
			)
		);
		$this->assertTrue( Storico::acceso() );
		$this->assertSame( 5, Storico::anni() );

		$contenuto = (string) $this->schermata( $this->amministratore );
		$this->assertSame( 1, substr_count( $contenuto, 'checked="checked"' ), 'La scelta salvata e\' segnata.' );
		$this->assertSame( 1, preg_match( '/value="' . Storico::ACCESO . '" checked="checked"/', $contenuto ), 'E\' quella accesa.' );
		$this->assertSame( 1, preg_match( '/name="' . preg_quote( $anni, '/' ) . '" value="5"/', $contenuto ), 'Gli anni salvati.' );

		$this->assertTrue(
			$this->invia(
				$this->custode,
				array(
					$scelta => Storico::SPENTO,
					$anni   => '5',
				)
			),
			'Chi ha soltanto il permesso dello storico spegne.'
		);
		$this->assertFalse( Storico::acceso() );
		$this->assertNull( Storico::anni(), 'Spento, gli anni non valgono.' );
		$this->assertSame( 5, Storico::impostazione()['anni'], 'Ma restano scritti per la volta dopo.' );

		$this->assertTrue( $this->invia( $this->amministratore, array( $scelta => Storico::SPENTO ) ), 'Spegnere non chiede gli anni.' );
		$this->assertNull( Storico::impostazione()['anni'] );

		$voci = $this->voci_impostazione();

		$this->assertCount( 3, $voci, 'Una voce per ogni salvataggio, e nessuna per i rifiuti.' );
		$this->assertSame( array( $this->amministratore, $this->custode, $this->amministratore ), array_map( 'intval', array_column( $voci, 'utente' ) ), 'Con chi ha salvato.' );
		$this->assertSame(
			array(
				array(
					'scelta' => Storico::ACCESO,
					'anni'   => '5',
				),
				array(
					'scelta' => Storico::SPENTO,
					'anni'   => '5',
				),
				array(
					'scelta' => Storico::SPENTO,
					'anni'   => '',
				),
			),
			array_map( array( $this, 'dettagli' ), $voci ),
			'Con la scelta e gli anni.'
		);
		$this->assertNull( $voci[0]['contenuto'], 'Voce della sola sezione: nessun atto.' );

		/*
		 * Il registro che non scrive: l'impostazione torna com'era. Si
		 * guasta soltanto la scrittura nella tabella del registro.
		 */
		$prima = get_option( Storico::OPZIONE );
		$rompi = static function ( $istruzione ) {
			return 0 === stripos( ltrim( (string) $istruzione ), 'INSERT' ) && false !== strpos( (string) $istruzione, 'conformita_core_registro' ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};

		add_filter( 'query', $rompi );
		$esito = $this->invia(
			$this->amministratore,
			array(
				$scelta => Storico::ACCESO,
				$anni   => '7',
			)
		);
		remove_filter( 'query', $rompi );

		$this->assertWPError( $esito, 'Registro guasto: salvataggio rifiutato.' );
		$this->assertSame( 'albo_copia_registro', $esito->get_error_code() );
		$this->assertSame( $prima, get_option( Storico::OPZIONE ), 'L\'impostazione e\' tornata com\'era.' );
		$this->assertFalse( Storico::acceso() );
		$this->assertCount( 3, $this->voci_impostazione() );

		// Sul sito pulito, il registro guasto non lascia nemmeno un'opzione scritta a meta'.
		delete_option( Storico::OPZIONE );
		add_filter( 'query', $rompi );
		$esito = $this->invia(
			$this->amministratore,
			array(
				$scelta => Storico::ACCESO,
				$anni   => '7',
			)
		);
		remove_filter( 'query', $rompi );

		$this->assertWPError( $esito );
		$this->assertNull( get_option( Storico::OPZIONE, null ), 'Nessuna opzione rimasta.' );
	}

	/**
	 * A-263: il salvataggio legge e rilegge la banca dati, non la memoria.
	 *
	 * Una lettura che fallisce non vale "nessuna impostazione": il salvataggio
	 * non scrive, e un'impostazione valida non si cancella. Una scrittura che
	 * la banca dati non ha fatto non passa per fatta, anche se la memoria delle
	 * opzioni dice il contrario. E un ripristino che non si conferma lo dice.
	 */
	public function test_a263_letture_dalla_banca_dati(): void {
		global $wpdb;

		$scelta = SchermataStorico::CAMPO_SCELTA;
		$anni   = SchermataStorico::CAMPO_ANNI;

		$this->fissa_orologi( '2041-10-01 10:00:00' );
		$this->assertTrue(
			$this->invia(
				$this->amministratore,
				array(
					$scelta => Storico::ACCESO,
					$anni   => '5',
				)
			)
		);

		$prima    = Storico::fotografia();
		$mostrato = Storico::stato_mostrato();
		$opzione  = "'" . Storico::OPZIONE . "'";

		$this->assertSame( Storico::VALIDA, $prima['esito'], 'Precondizione: impostazione valida.' );

		// La lettura dell'opzione fallisce.
		$guasta = static function ( $istruzione ) use ( $wpdb ) {
			return 0 === strpos( ltrim( (string) $istruzione ), "SELECT option_value FROM {$wpdb->options} WHERE option_name = '" . Storico::OPZIONE . "'" ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$zitto  = $wpdb->suppress_errors( true );

		add_filter( 'query', $guasta );
		$esito = $this->invia(
			$this->amministratore,
			array(
				$scelta                          => Storico::SPENTO,
				SchermataStorico::CAMPO_MOSTRATO => $mostrato,
			)
		);
		$this->assertFalse( Storico::acceso(), 'Con la lettura fallita lo storico vale spento.' );
		$this->assertStringContainsString( 'non si legge in questo momento', (string) $this->schermata( $this->amministratore ), 'La schermata lo dice.' );
		remove_filter( 'query', $guasta );
		$wpdb->suppress_errors( $zitto );

		$this->assertWPError( $esito, 'Lettura fallita: niente salvato.' );
		$this->assertSame( 'albo_copia_lettura', $esito->get_error_code() );
		$this->assertSame( $prima, Storico::fotografia(), 'L\'impostazione valida e\' rimasta, non cancellata.' );
		$this->assertCount( 1, $this->voci_impostazione() );

		/*
		 * La banca dati che finge di scrivere: l'aggiornamento dell'opzione
		 * diventa una lettura che risponde una riga. WordPress lo crede fatto
		 * e mette il valore nuovo nella sua memoria; la rilettura guarda la
		 * banca dati e se ne accorge.
		 */
		$finta = static function ( $istruzione ) use ( $opzione ) {
			return 0 === stripos( ltrim( (string) $istruzione ), 'UPDATE' ) && false !== strpos( (string) $istruzione, $opzione ) ? 'SELECT 1' : $istruzione;
		};

		add_filter( 'query', $finta );
		$esito = $this->invia(
			$this->amministratore,
			array(
				$scelta => Storico::SPENTO,
				$anni   => '5',
			)
		);
		remove_filter( 'query', $finta );

		$this->assertWPError( $esito, 'Scrittura finta: salvataggio rifiutato.' );
		$this->assertSame( 'albo_copia_non_scritto', $esito->get_error_code() );
		$this->assertStringContainsString( 'tornata com\'era', $esito->get_error_message() );
		$this->assertSame( $prima, Storico::fotografia() );
		$this->assertSame( $prima['impostazione'], array_intersect_key( (array) get_option( Storico::OPZIONE ), $prima['impostazione'] ), 'Nemmeno la memoria tiene il valore rifiutato.' );
		$this->assertTrue( Storico::acceso() );
		$this->assertCount( 1, $this->voci_impostazione(), 'Niente nel registro.' );

		/*
		 * Il registro non scrive e il ripristino non arriva alla banca dati:
		 * la prima scrittura passa, le successive sono finte. Il rifiuto non
		 * dice che l'impostazione e' tornata com'era.
		 */
		$scritture = 0;
		$a_meta    = static function ( $istruzione ) use ( $opzione, &$scritture ) {
			$testo = ltrim( (string) $istruzione );

			if ( 0 === stripos( $testo, 'INSERT' ) && false !== strpos( $testo, 'conformita_core_registro' ) ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			if ( 0 === stripos( $testo, 'UPDATE' ) && false !== strpos( $testo, $opzione ) && ++$scritture > 1 ) {
				return 'SELECT 1';
			}

			return $istruzione;
		};
		$zitto     = $wpdb->suppress_errors( true );

		add_filter( 'query', $a_meta );
		$esito = $this->invia(
			$this->amministratore,
			array(
				$scelta => Storico::SPENTO,
				$anni   => '5',
			)
		);
		remove_filter( 'query', $a_meta );
		$wpdb->suppress_errors( $zitto );

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_non_ripristinato', $esito->get_error_code(), 'Il ripristino non confermato si dice.' );
		$this->assertStringNotContainsString( 'tornata com\'era. Riprovare', $esito->get_error_message() );
		$this->assertSame( Storico::SPENTO, Storico::impostazione()['scelta'], 'E la banca dati tiene davvero la scrittura non registrata: il rifiuto dice il vero.' );
	}

	/**
	 * A-263: il modulo rimasto indietro, il salvataggio uguale e la fila.
	 *
	 * Due persone aprono la schermata; una spegne, l'altra salva il modulo
	 * vecchio: il secondo salvataggio si rifiuta, anche sul modulo rimostrato
	 * dopo un rifiuto, finche' non si parte dalla scelta in vigore. La stessa
	 * scelta con gli stessi anni non scrive niente. Con un salvataggio in
	 * corso, un altro aspetta il suo turno e poi si rifiuta.
	 */
	public function test_a263_modulo_rimasto_indietro(): void {
		global $wpdb;

		$scelta = SchermataStorico::CAMPO_SCELTA;
		$anni   = SchermataStorico::CAMPO_ANNI;

		// Il modulo come lo porta la schermata.
		$dal_modulo = function ( int $utente ): string {
			$contenuto = (string) $this->schermata( $utente );

			$this->assertSame( 1, preg_match( '/name="' . preg_quote( SchermataStorico::CAMPO_MOSTRATO, '/' ) . '" value="([^"]*)"/', $contenuto, $trovato ), 'Il modulo porta lo stato mostrato.' );

			return html_entity_decode( $trovato[1] );
		};

		$aperto_dal_custode = $dal_modulo( $this->custode );
		$aperto_dall_altro  = $dal_modulo( $this->amministratore );

		$this->fissa_orologi( '2041-10-01 10:00:00' );
		$this->assertTrue(
			$this->invia(
				$this->amministratore,
				array(
					$scelta                          => Storico::ACCESO,
					$anni                            => '5',
					SchermataStorico::CAMPO_MOSTRATO => $aperto_dall_altro,
				)
			)
		);

		$this->fissa_orologi( '2041-10-01 10:05:00' );
		$this->assertTrue(
			$this->invia(
				$this->amministratore,
				array(
					$scelta                          => Storico::SPENTO,
					SchermataStorico::CAMPO_MOSTRATO => $dal_modulo( $this->amministratore ),
				)
			),
			'Chi parte dalla scelta in vigore salva.'
		);

		$in_vigore = Storico::fotografia();

		foreach ( array(
			'modulo aperto prima di tutti' => $aperto_dal_custode,
			'modulo senza stato'           => null,
			'stato inventato'              => 'qualcosa',
			'stato vuoto'                  => '',
		) as $caso => $mostrato ) {
			$campi = array(
				$scelta => Storico::ACCESO,
				$anni   => '9',
			);

			if ( null !== $mostrato ) {
				$campi[ SchermataStorico::CAMPO_MOSTRATO ] = $mostrato;
			}

			$esito = $this->invia( $this->custode, null === $mostrato ? $campi + array( SchermataStorico::CAMPO_MOSTRATO => null ) : $campi );

			$this->assertWPError( $esito, $caso . ': rifiutato.' );
			$this->assertSame( 'albo_copia_conflitto', $esito->get_error_code(), $caso );
			$this->assertSame( $in_vigore, Storico::fotografia(), $caso . ': niente scritto.' );
		}

		$this->assertCount( 2, $this->voci_impostazione(), 'I modulo vecchi non scrivono nel registro.' );

		// Il modulo rimostrato dopo il rifiuto parte dalla scelta in vigore, e salva.
		$this->assertTrue(
			$this->invia(
				$this->custode,
				array(
					$scelta                          => Storico::ACCESO,
					$anni                            => '9',
					SchermataStorico::CAMPO_MOSTRATO => $dal_modulo( $this->custode ),
				)
			)
		);
		$this->assertSame( 9, Storico::anni() );

		// La stessa scelta, con gli stessi anni: niente scritto, niente nel registro.
		$uguale = Storico::fotografia();

		$this->fissa_orologi( '2041-10-01 11:00:00' );
		$this->assertTrue(
			$this->invia(
				$this->amministratore,
				array(
					$scelta => Storico::ACCESO,
					$anni   => '09',
				)
			)
		);
		$this->assertSame( $uguale, Storico::fotografia(), 'Stesso valore, stesso istante e stessa persona dell\'ultimo salvataggio vero.' );
		$this->assertCount( 3, $this->voci_impostazione() );

		// Un altro salvataggio in corso, su un'altra connessione: si aspetta, poi si rifiuta senza scrivere.
		$altra = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );

		try {
			$this->assertSame( '1', (string) $altra->get_var( $altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Storico::nome_blocco() ) ) );

			$esito = $this->invia(
				$this->amministratore,
				array(
					$scelta => Storico::SPENTO,
				)
			);
		} finally {
			$altra->query( 'SELECT RELEASE_ALL_LOCKS()' );
			$altra->close();
		}

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_occupato', $esito->get_error_code() );
		$this->assertSame( $uguale, Storico::fotografia() );
	}

	/**
	 * A-264: gettone, permesso, menu.
	 */
	public function test_a264_gettone_permesso_e_menu(): void {
		$campi = array(
			SchermataStorico::CAMPO_SCELTA => Storico::ACCESO,
			SchermataStorico::CAMPO_ANNI   => '5',
		);

		$esito = $this->invia( $this->amministratore, $campi, false );
		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_gettone', $esito->get_error_code(), 'Senza gettone non si salva.' );

		$abbonato = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		foreach ( array(
			'chi redige'   => $this->redattore,
			'chi pubblica' => $this->utente_con( Permessi::CHIAVI_PUBBLICAZIONE ),
			'chi consulta' => $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) ),
			'abbonato'     => $abbonato,
		) as $chi => $utente ) {
			$esito = $this->invia( $utente, $campi );

			$this->assertWPError( $esito, $chi . ': rifiutato.' );
			$this->assertSame( 'albo_copia_non_permesso', $esito->get_error_code(), $chi . ': per il permesso, con un gettone valido.' );
			$this->assertNull( $this->schermata( $utente ), $chi . ': la schermata rifiuta.' );
		}

		$this->assertNull( get_option( Storico::OPZIONE, null ), 'Niente scritto.' );
		$this->assertNotNull( $this->schermata( $this->custode ), 'Presenza di controllo: chi ha il permesso apre la schermata.' );
		$this->assertSame( 10, has_action( 'admin_menu', array( SchermataStorico::class, 'menu' ) ), 'La voce si aggiunge dall\'aggancio del menu.' );
		$this->assertSame( 10, has_action( 'admin_post_' . SchermataStorico::AZIONE, array( SchermataStorico::class, 'da_invio' ) ), 'L\'invio passa dall\'aggancio del modulo.' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$voci = function ( int $utente ): array {
			global $menu, $submenu, $_registered_pages, $_parent_pages;

			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: il menu si costruisce da zero per ogni utente.
			$menu              = array();
			$submenu           = array();
			$_registered_pages = array();
			$_parent_pages     = array();
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

			wp_set_current_user( $utente );
			do_action( 'admin_menu', '' );

			$visibili = static function ( array $voci ): array {
				$slug = array();
				foreach ( $voci as $voce ) {
					if ( current_user_can( $voce[1] ) ) {
						$slug[] = $voce[2];
					}
				}

				return $slug;
			};

			return array(
				'in cima'        => in_array( SchermataStorico::PAGINA, $visibili( (array) $menu ), true ),
				'sotto gli atti' => in_array( SchermataStorico::PAGINA, $visibili( (array) ( $submenu[ 'edit.php?post_type=' . TIPO ] ?? array() ) ), true ),
			);
		};

		$this->assertSame(
			array(
				'in cima'        => false,
				'sotto gli atti' => true,
			),
			$voci( $this->amministratore ),
			'Amministratore: nel menu degli atti.'
		);
		$this->assertSame(
			array(
				'in cima'        => true,
				'sotto gli atti' => false,
			),
			$voci( $this->custode ),
			'Chi ha soltanto il permesso dello storico: in cima.'
		);
		$this->assertSame(
			array(
				'in cima'        => false,
				'sotto gli atti' => false,
			),
			$voci( $this->redattore ),
			'Chi redige: da nessuna parte.'
		);
	}

	/**
	 * A-265: il permesso, la seconda sezione e il tipo della copia.
	 */
	public function test_a265_permesso_sezione_e_tipo(): void {
		$permesso = Permessi::PERMESSO_STORICO;
		$mappa    = Permessi::mappa();

		$this->assertIsArray( $mappa );
		$this->assertSame( $permesso, $mappa[ Permessi::CHIAVE_STORICO ] );
		$this->assertStringContainsString( TIPO, $permesso, 'Il nome porta il tipo atto, come gli altri permessi dell\'albo.' );
		$this->assertNotContains( Permessi::CHIAVE_STORICO, Permessi::CHIAVI_PUBBLICAZIONE, 'Non e\' fra i permessi di chi pubblica.' );
		$this->assertNotContains( Permessi::CHIAVE_STORICO, Permessi::CHIAVI_REDAZIONE, 'Ne\' fra quelli di chi redige.' );

		foreach ( array( 'administrator', RUOLO ) as $ruolo ) {
			$this->assertTrue( get_role( $ruolo )->has_cap( $permesso ), $ruolo . ': all\'installazione.' );
		}

		$this->assertFalse( user_can( $this->redattore, $permesso ), 'Chi redige non lo ha.' );

		// Un sito con la versione di prima e un altro ruolo che pubblica.
		add_role( 'albo_prova_pubblica', 'Prova pubblica', array( 'read' => true ) );

		foreach ( Permessi::CHIAVI_PUBBLICAZIONE as $chiave ) {
			get_role( 'albo_prova_pubblica' )->add_cap( $mappa[ $chiave ] );
		}

		foreach ( wp_roles()->role_objects as $oggetto ) {
			$oggetto->remove_cap( $permesso );
		}

		update_option( OPZIONE_VERSIONE, '0.6.0-alpha' );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'Con la versione di prima l\'aggiornamento gira.' );

		foreach ( array( 'administrator', RUOLO ) as $ruolo ) {
			$this->assertTrue( get_role( $ruolo )->has_cap( $permesso ), $ruolo . ': all\'aggiornamento.' );
		}

		$this->assertFalse( get_role( 'albo_prova_pubblica' )->has_cap( $permesso ), 'Un altro ruolo che pubblica non lo riceve da se\'.' );

		foreach ( array(
			''            => array(),
			'0.6.0-alpha' => array( Permessi::CHIAVE_DEFISSIONE ),
			'0.6.9'       => array( Permessi::CHIAVE_DEFISSIONE ),
			'0.7.0-alpha' => array( Permessi::CHIAVE_DEFISSIONE, Permessi::CHIAVE_STORICO ),
			'0.7.1'       => array( Permessi::CHIAVE_DEFISSIONE, Permessi::CHIAVE_STORICO ),
		) as $da => $attese ) {
			$this->assertSame( $attese, Installazione::chiavi_escluse( (string) $da ), 'Aggiornamento da ' . wp_json_encode( (string) $da ) . '.' );
		}

		// L'ente lo toglie al ruolo dell'albo: un aggiornamento successivo non lo ridà.
		get_role( RUOLO )->remove_cap( $permesso );
		update_option( OPZIONE_VERSIONE, '0.7.0-alpha' );
		\AlboPretorioPa\Permessi::applica( null, Installazione::chiavi_escluse( '0.7.0-alpha' ) );

		$this->assertFalse( get_role( RUOLO )->has_cap( $permesso ), 'La revoca dell\'ente resta.' );
		$this->assertTrue( get_role( 'administrator' )->has_cap( $permesso ), 'L\'amministratore lo conserva.' );

		remove_role( 'albo_prova_pubblica' );

		// La seconda sezione, con la politica dell'albo.
		$this->assertTrue( Storico::pronto() );
		$this->assertContains( SEZIONE_STORICO, conformita_core_sezioni_registrate() );
		$this->assertSame( SEZIONE_STORICO, conformita_core_sezione_del_tipo( TIPO_STORICO ) );
		$this->assertSame( SEZIONE, conformita_core_sezione_del_tipo( TIPO ), 'Il tipo atto resta nella sezione dell\'albo.' );

		// Il tipo della copia: chiuso a tutti, senza interfaccia di WordPress.
		$tipo = get_post_type_object( TIPO_STORICO );

		$this->assertInstanceOf( \WP_Post_Type::class, $tipo );

		foreach ( array( 'public', 'publicly_queryable', 'show_ui', 'show_in_menu', 'show_in_rest', 'show_in_nav_menus', 'has_archive', 'query_var' ) as $proprieta ) {
			$this->assertFalse( (bool) $tipo->$proprieta, 'Il tipo della copia non ha ' . $proprieta . '.' );
		}

		$this->assertFalse( is_post_type_viewable( $tipo ), 'Il tipo della copia non e\' consultabile.' );
		$this->assertTrue( (bool) $tipo->exclude_from_search );

		$permessi_tipo = conformita_core_capacita_tipo( TIPO_STORICO );
		$this->assertIsArray( $permessi_tipo );

		foreach ( wp_roles()->role_objects as $nome => $oggetto ) {
			foreach ( $permessi_tipo as $chiave => $derivato ) {
				if ( false === strpos( (string) $derivato, TIPO_STORICO ) ) {
					continue;
				}

				$this->assertFalse( $oggetto->has_cap( $derivato ), $nome . ': nessun ruolo ha i permessi del tipo della copia (' . $chiave . ').' );
			}
		}

		$this->assertFalse( user_can( $this->amministratore, $tipo->cap->create_posts ), 'Nemmeno l\'amministratore crea copie dalle strade di WordPress.' );
		$this->assertFalse( user_can( $this->amministratore, $tipo->cap->edit_posts ) );
	}

	/**
	 * A-265, seconda parte: se il meccanismo comune rifiuta la seconda sezione,
	 * lo storico resta fermo con un avviso e l'albo lavora come prima.
	 */
	public function test_a265_sezione_rifiutata(): void {
		$this->azzera_ambiente_albo();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		// Un altro componente ha gia' registrato una sezione con lo stesso nome.
		$this->assertTrue(
			conformita_core_registra_sezione(
				SEZIONE_STORICO,
				array(
					'indicizzazione' => 'consentita',
					'scadenza'       => 'archivio',
				)
			)
		);

		$esito = Storico::registra();

		$this->assertWPError( $esito );
		$this->assertFalse( Storico::pronto(), 'Lo storico resta fermo.' );
		$this->assertFalse( post_type_exists( TIPO_STORICO ), 'Il tipo della copia non c\'e\'.' );
		$this->assertTrue( TipoAtto::registrazione_completa(), 'L\'albo lavora come prima.' );

		$utente = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $utente );

		ob_start();
		Storico::mostra_avviso();
		$avviso = (string) ob_get_clean();

		$this->assertStringContainsString( 'lo storico pubblico resta fermo', $avviso );

		$this->assertWPError( Storico::salva( Storico::ACCESO, '5', Storico::stato_mostrato(), $utente, new \DateTimeImmutable() ), 'Non si accende uno storico fermo.' );
		$this->assertNull( get_option( Storico::OPZIONE, null ) );
	}
}
