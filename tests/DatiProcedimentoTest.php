<?php
/**
 * Responsabile del procedimento, ufficio e richiedente: righe A-144..A-146 e A-165..A-171 (ALBO-33).
 *
 * La scelta si salva da codice o con un invio vero della schermata, la scheda
 * dell'atto con un invio vero della schermata dell'atto, la pubblicazione con
 * il passaggio vero. I dati si rileggono dalla banca dati.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DatiProcedimento;
use AlboPretorioPa\Festivi;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\SchedaAtto;
use AlboPretorioPa\SchermataDatiProcedimento;
use AlboPretorioPa\SchermataFestivi;

use const AlboPretorioPa\TIPO;

/**
 * La scelta sui dati del procedimento, gli elenchi, la scheda e il registro.
 */
class DatiProcedimentoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Elenco dei responsabili delle prove.
	 *
	 * @var string
	 */
	const RESPONSABILI = "Mario Rossi\nAnna Bianchi";

	/**
	 * Elenco degli uffici delle prove.
	 *
	 * @var string
	 */
	const UFFICI = "Ufficio tecnico\nRicerca & Sviluppo";

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
	 * Salva la stessa scelta per i tre dati, da codice, come chi pubblica.
	 *
	 * @param string $scelta       Scelta.
	 * @param string $responsabili Elenco dei responsabili.
	 * @param string $uffici       Elenco degli uffici.
	 */
	private function scegli( string $scelta, string $responsabili = self::RESPONSABILI, string $uffici = self::UFFICI ): void {
		$esito = DatiProcedimento::salva( array_fill_keys( DatiProcedimento::DATI, $scelta ), $responsabili, $uffici, $this->pubblicatore, Passaggi::ora() );

		$this->assertTrue( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
	}

	/**
	 * Un invio vero della schermata dei dati del procedimento, come l'utente indicato.
	 *
	 * @param int                  $utente  Utente.
	 * @param array<string, mixed> $campi   Campi inviati.
	 * @param string|null          $gettone Gettone; nullo per quello giusto.
	 * @return true|\WP_Error
	 */
	private function invia_schermata( int $utente, array $campi, ?string $gettone = null ) {
		return $this->come(
			$utente,
			static function () use ( $campi, $gettone ) {
				$_POST = array( SchermataDatiProcedimento::CAMPO_GETTONE => $gettone ?? wp_create_nonce( SchermataDatiProcedimento::AZIONE ) ) + $campi;

				try {
					return SchermataDatiProcedimento::elabora();
				} finally {
					$_POST = array();
				}
			}
		);
	}

	/**
	 * I campi di un invio completo e valido della schermata.
	 *
	 * @param string $scelta Scelta per i tre dati.
	 * @return array<string, mixed>
	 */
	private function campi_validi( string $scelta = DatiProcedimento::USA ): array {
		return array(
			SchermataDatiProcedimento::CAMPO_SCELTE       => array_fill_keys( DatiProcedimento::DATI, $scelta ),
			SchermataDatiProcedimento::CAMPO_RESPONSABILI => self::RESPONSABILI,
			SchermataDatiProcedimento::CAMPO_UFFICI       => self::UFFICI,
		);
	}

	/**
	 * Una schermata di impostazione stampata, come l'utente indicato.
	 *
	 * @param int      $utente  Utente.
	 * @param callable $mostra  Funzione che la stampa.
	 * @return string
	 */
	private function stampa( int $utente, callable $mostra ): string {
		return $this->come(
			$utente,
			static function () use ( $mostra ) {
				ob_start();

				try {
					$mostra();
				} finally {
					$stampato = (string) ob_get_clean();
				}

				return $stampato;
			}
		);
	}

	/**
	 * Il riquadro dei dati dell'atto, come chi redige.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private function riquadro( int $atto_id ): string {
		return $this->stampa(
			$this->redattore,
			static function () use ( $atto_id ) {
				SchedaAtto::mostra( get_post( $atto_id ) );
			}
		);
	}

	/**
	 * Un invio vero della schermata dell'atto, come chi redige, con il gettone della scheda.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $campi   Valori per dato.
	 * @return array<int, string> Motivi dei rifiuti.
	 */
	private function invia_scheda( int $atto_id, array $campi ): array {
		return $this->come(
			$this->redattore,
			static function () use ( $atto_id, $campi ) {
				set_current_screen( 'post' );

				$_POST = array(
					'action'                  => 'editpost',
					'post_ID'                 => (string) $atto_id,
					'post_type'               => TIPO,
					'post_title'              => get_the_title( $atto_id ),
					'original_post_status'    => 'draft',
					'_wpnonce'                => wp_create_nonce( 'update-post_' . $atto_id ),
					SchedaAtto::CAMPO_GETTONE => wp_create_nonce( SchedaAtto::azione( $atto_id ) ),
				);

				foreach ( $campi as $dato => $valore ) {
					$_POST[ SchedaAtto::campo_procedimento( $dato ) ] = $valore;
				}

				try {
					edit_post();
				} finally {
					$_POST = array();
				}

				return SchedaAtto::preleva_rifiuto( $atto_id );
			}
		);
	}

	/**
	 * I tre dati come sono nella banca dati, letti grezzi.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, array<int, mixed>>
	 */
	private function grezzi( int $atto_id ): array {
		clean_post_cache( $atto_id );
		wp_cache_flush();

		$grezzi = array();

		foreach ( DatiProcedimento::DATI as $dato ) {
			$grezzi[ $dato ] = get_post_meta( $atto_id, DatiProcedimento::meta( $dato ), false );
		}

		return $grezzi;
	}

	/**
	 * Scrive i tre dati sull'atto di lato, come farebbe l'importazione da un albo precedente.
	 *
	 * @param int                   $atto_id Atto.
	 * @param array<string, string> $valori  Valori per dato.
	 */
	private function scrivi_di_lato( int $atto_id, array $valori ): void {
		foreach ( $valori as $dato => $valore ) {
			update_post_meta( $atto_id, DatiProcedimento::meta( $dato ), $valore );
		}
	}

	/**
	 * Manda in verifica una bozza, come il suo autore.
	 *
	 * @param int $atto_id Atto.
	 */
	private function in_verifica( int $atto_id ): void {
		$this->come(
			$this->redattore,
			static function () use ( $atto_id ) {
				return wp_update_post(
					array(
						'ID'          => $atto_id,
						'post_status' => 'pending',
					)
				);
			}
		);

		clean_post_cache( $atto_id );

		$this->assertSame( 'pending', get_post_status( $atto_id ), 'Precondizione: l\'atto e\' in verifica.' );
	}

	/**
	 * Un elemento del riquadro per identificativo, o nullo.
	 *
	 * @param string $html Riquadro.
	 * @param string $id   Identificativo.
	 * @return \DOMElement|null
	 */
	private function elemento( string $html, string $id ): ?\DOMElement {
		$documento = new \DOMDocument();
		libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		return $documento->getElementById( $id );
	}

	/**
	 * Le opzioni di un menu: valore, testo, selezionata.
	 *
	 * @param string $html  Riquadro.
	 * @param string $campo Nome del menu.
	 * @return array<int, array{0: string, 1: string, 2: bool}>
	 */
	private function opzioni( string $html, string $campo ): array {
		$menu = $this->elemento( $html, $campo );

		$this->assertNotNull( $menu, 'Il riquadro deve contenere il menu ' . $campo . '.' );

		$opzioni = array();

		foreach ( $menu->getElementsByTagName( 'option' ) as $opzione ) {
			$opzioni[] = array(
				$opzione->getAttribute( 'value' ),
				$opzione->textContent, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di DOMNode.
				$opzione->hasAttribute( 'selected' ),
			);
		}

		return $opzioni;
	}

	/**
	 * A-144: scelta "non usare": niente campi, niente da mostrare, dati gia' presenti conservati.
	 */
	public function test_a144_non_usare(): void {
		$this->scegli( DatiProcedimento::NON_USA );

		$atto = $this->atto_completo();
		$this->scrivi_di_lato(
			$atto,
			array(
				DatiProcedimento::RESPONSABILE => 'Giulia Verdi',
				DatiProcedimento::UFFICIO      => 'Segreteria',
				DatiProcedimento::RICHIEDENTE  => 'Altro ente',
			)
		);
		$prima = $this->grezzi( $atto );

		$riquadro = $this->riquadro( $atto );

		foreach ( DatiProcedimento::DATI as $dato ) {
			$this->assertNull( $this->elemento( $riquadro, SchedaAtto::campo_procedimento( $dato ) ), 'Nessun campo: ' . $dato );
		}

		$this->assertStringNotContainsString( 'Giulia Verdi', $riquadro );

		// Un invio che porta i campi non li tocca: il dato non in uso resta com'e'.
		$motivi = $this->invia_scheda(
			$atto,
			array(
				DatiProcedimento::RESPONSABILE => '',
				DatiProcedimento::UFFICIO      => 'Ufficio tecnico',
				DatiProcedimento::RICHIEDENTE  => 'Altro',
			)
		);

		$this->assertSame( array(), $motivi );
		$this->assertSame( $prima, $this->grezzi( $atto ), 'I dati conservati restano.' );
		$this->assertSame( array(), DatiProcedimento::da_mostrare( $atto ), 'Niente da mostrare.' );
		$this->assertSame( 'Giulia Verdi', DatiProcedimento::valore( $atto, DatiProcedimento::RESPONSABILE ), 'La lettura li trova.' );
	}

	/**
	 * A-145: scelta "chiedere e mostrare": campi facoltativi, nessuna voce proposta, mostrati solo se compilati, sfuggiti.
	 */
	public function test_a145_chiedere_e_mostrare(): void {
		$this->scegli( DatiProcedimento::USA );

		$atto     = $this->atto_completo();
		$riquadro = $this->riquadro( $atto );

		$this->assertSame(
			array(
				array( '', 'Nessuno', true ),
				array( 'Mario Rossi', 'Mario Rossi', false ),
				array( 'Anna Bianchi', 'Anna Bianchi', false ),
			),
			$this->opzioni( $riquadro, SchedaAtto::campo_procedimento( DatiProcedimento::RESPONSABILE ) ),
			'Nessuna voce proposta.'
		);
		$this->assertCount( 3, $this->opzioni( $riquadro, SchedaAtto::campo_procedimento( DatiProcedimento::UFFICIO ) ) );
		$this->assertSame( 'input', $this->elemento( $riquadro, SchedaAtto::campo_procedimento( DatiProcedimento::RICHIEDENTE ) )->nodeName );

		// Facoltativi: un atto senza va in verifica e si pubblica.
		$this->assertSame( array(), DatiAtto::mancanti( $atto ) );

		$motivi = $this->invia_scheda(
			$atto,
			array(
				DatiProcedimento::RESPONSABILE => 'Anna Bianchi',
				DatiProcedimento::UFFICIO      => 'Ricerca & Sviluppo',
				DatiProcedimento::RICHIEDENTE  => '',
			)
		);

		$this->assertSame( array(), $motivi );
		$this->assertSame(
			array(
				'Responsabile del procedimento' => 'Anna Bianchi',
				'Ufficio'                       => 'Ricerca & Sviluppo',
			),
			DatiProcedimento::da_mostrare( $atto ),
			'Il richiedente vuoto non si mostra.'
		);

		$riquadro = $this->riquadro( $atto );
		$this->assertSame( array( 'Anna Bianchi', 'Anna Bianchi', true ), $this->opzioni( $riquadro, SchedaAtto::campo_procedimento( DatiProcedimento::RESPONSABILE ) )[2] );

		// Dopo la pubblicazione la scheda e' in sola lettura, con l'escaping in uscita.
		$id = $this->atto_completo();
		$this->scrivi_di_lato( $id, array( DatiProcedimento::UFFICIO => 'Ricerca & Sviluppo "R&S"' ) );
		$this->in_verifica( $id );
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );

		$lettura = $this->riquadro( $id );

		$this->assertStringContainsString( '<dd>Ricerca &amp; Sviluppo &quot;R&amp;S&quot;</dd>', $lettura );
		$this->assertStringContainsString( '<dt>Responsabile del procedimento</dt><dd>non indicato</dd>', $lettura );
	}

	/**
	 * A-146: ogni schermata di impostazione dice norma, scelte ed effetti, senza scelta segnata.
	 */
	public function test_a146_regola_delle_impostazioni(): void {
		delete_option( Festivi::OPZIONE );

		$schermate = array(
			'festivi'      => array( array( SchermataFestivi::class, 'mostra' ), 'd.lgs. 267/2000' ),
			'procedimento' => array( array( SchermataDatiProcedimento::class, 'mostra' ), 'l. 241/1990' ),
		);

		foreach ( $schermate as $nome => list( $mostra, $estremi ) ) {
			$html = $this->stampa( $this->pubblicatore, $mostra );

			$this->assertStringContainsString( 'Che cosa dice la norma', $html, $nome );
			$this->assertStringContainsString( $estremi, $html, $nome . ': norma per estremi.' );
			$this->assertStringContainsString( 'Per chi consulta l&#039;albo', $html, $nome . ': effetti per il cittadino.' );
			$this->assertStringContainsString( 'Per chi prepara', $html, $nome . ': effetti per chi redige.' );
			$this->assertGreaterThanOrEqual( 2, substr_count( $html, 'type="radio"' ), $nome . ': almeno due scelte.' );
			$this->assertStringNotContainsString( 'checked', $html, $nome . ': nessuna scelta segnata.' );
		}

		$html = $this->stampa( $this->pubblicatore, array( SchermataDatiProcedimento::class, 'mostra' ) );
		$this->assertSame( 6, substr_count( $html, 'type="radio"' ), 'Due scelte per ciascuno dei tre dati.' );
	}

	/**
	 * A-165: senza scelta i campi non ci sono, l'albo pubblica, l'avviso va a chi pubblica e poi sparisce.
	 */
	public function test_a165_scelta_non_fatta(): void {
		$atto = $this->atto_completo();

		foreach ( DatiProcedimento::DATI as $dato ) {
			$this->assertNull( DatiProcedimento::scelta( $dato ) );
			$this->assertNull( $this->elemento( $this->riquadro( $atto ), SchedaAtto::campo_procedimento( $dato ) ) );
		}

		$id = $this->atto_in_verifica();
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ), 'L\'albo pubblica anche senza scelta.' );

		wp_set_current_user( $this->pubblicatore );
		$this->assertStringContainsString( SchermataDatiProcedimento::PAGINA, $this->avvisi_in_bacheca( true ) );

		wp_set_current_user( $this->redattore );
		$this->assertStringNotContainsString( SchermataDatiProcedimento::PAGINA, $this->avvisi_in_bacheca( true ) );

		$this->scegli( DatiProcedimento::NON_USA );

		wp_set_current_user( $this->pubblicatore );
		$this->assertStringNotContainsString( SchermataDatiProcedimento::PAGINA, $this->avvisi_in_bacheca( true ), 'Dopo la scelta l\'avviso sparisce.' );
	}

	/**
	 * A-166: salvataggi rifiutati per intero dalla schermata; gettone e permesso.
	 */
	public function test_a166_salvataggi_rifiutati(): void {
		$this->assertTrue( $this->invia_schermata( $this->pubblicatore, $this->campi_validi() ) );

		$prima = get_option( DatiProcedimento::OPZIONE );

		$senza_una = $this->campi_validi();
		unset( $senza_una[ SchermataDatiProcedimento::CAMPO_SCELTE ][ DatiProcedimento::UFFICIO ] );

		$sconosciuta = $this->campi_validi();
		$sconosciuta[ SchermataDatiProcedimento::CAMPO_SCELTE ][ DatiProcedimento::RICHIEDENTE ] = 'forse';

		$scelte_testo = $this->campi_validi();
		$scelte_testo[ SchermataDatiProcedimento::CAMPO_SCELTE ] = DatiProcedimento::USA;

		$scelta_insieme = $this->campi_validi();
		$scelta_insieme[ SchermataDatiProcedimento::CAMPO_SCELTE ][ DatiProcedimento::RESPONSABILE ] = array( DatiProcedimento::USA );

		$casi = array(
			'una scelta mancante'             => array( $senza_una, 'per l\'ufficio va scelto' ),
			'scelta sconosciuta'              => array( $sconosciuta, 'richiedente' ),
			'scelte come testo'               => array( $scelte_testo, 'va scelto' ),
			'scelta come insieme'             => array( $scelta_insieme, 'responsabile' ),
			'nome ripetuto'                   => array( array( SchermataDatiProcedimento::CAMPO_RESPONSABILI => "Mario Rossi\n\nmario rossi" ) + $this->campi_validi(), 'riga 3: il nome e\' gia\' scritto alla riga 1' ),
			'nome troppo lungo'               => array( array( SchermataDatiProcedimento::CAMPO_UFFICI => "Ufficio\n" . str_repeat( 'a', 201 ) ) + $this->campi_validi(), 'riga 2: il nome supera' ),
			'segni di formattazione'          => array( array( SchermataDatiProcedimento::CAMPO_UFFICI => '<b>Ufficio</b>' ) + $this->campi_validi(), 'riga 1: il nome contiene segni' ),
			'carattere di controllo'          => array( array( SchermataDatiProcedimento::CAMPO_RESPONSABILI => "Mario\x00Rossi" ) + $this->campi_validi(), 'riga 1: il testo non e\' leggibile' ),
			'elenco vuoto con il dato in uso' => array( array( SchermataDatiProcedimento::CAMPO_UFFICI => "\n  \n" ) + $this->campi_validi(), 'serve almeno un nome nell\'elenco degli uffici' ),
			'elenco come insieme'             => array( array( SchermataDatiProcedimento::CAMPO_RESPONSABILI => array( 'Mario Rossi' ) ) + $this->campi_validi(), 'non e\' arrivato come testo' ),
		);

		$mancante = $this->campi_validi();
		unset( $mancante[ SchermataDatiProcedimento::CAMPO_UFFICI ] );
		$casi['elenco mancante'] = array( $mancante, 'non e\' arrivato come testo' );

		foreach ( $casi as $nome => list( $campi, $motivo ) ) {
			$esito = $this->invia_schermata( $this->pubblicatore, $campi );

			$this->assertStringContainsString( $motivo, $this->messaggi( $esito ), $nome );
			$this->assertSame( $prima, get_option( DatiProcedimento::OPZIONE ), $nome . ': impostazione identica a prima.' );
		}

		$this->assertStringContainsString( 'scaduto', $this->messaggi( $this->invia_schermata( $this->pubblicatore, $this->campi_validi( DatiProcedimento::NON_USA ), 'abcdef' ) ) );
		$this->assertStringContainsString( 'permesso', $this->messaggi( $this->invia_schermata( $this->redattore, $this->campi_validi( DatiProcedimento::NON_USA ) ) ) );
		$this->assertSame( $prima, get_option( DatiProcedimento::OPZIONE ), 'Gettone e permesso.' );

		// Con il dato non in uso l'elenco vuoto si salva; gli spazi ripetuti diventano uno.
		$campi = array(
			SchermataDatiProcedimento::CAMPO_UFFICI       => '',
			SchermataDatiProcedimento::CAMPO_RESPONSABILI => "  Mario   Rossi  \r\nAnna Bianchi",
		) + $this->campi_validi();
		$campi[ SchermataDatiProcedimento::CAMPO_SCELTE ][ DatiProcedimento::UFFICIO ] = DatiProcedimento::NON_USA;

		$this->assertTrue( $this->invia_schermata( $this->pubblicatore, $campi ) );
		$this->assertSame( array( 'Mario Rossi', 'Anna Bianchi' ), DatiProcedimento::elenco( DatiProcedimento::RESPONSABILE ) );
		$this->assertSame( array(), DatiProcedimento::elenco( DatiProcedimento::UFFICIO ) );
		$this->assertFalse( DatiProcedimento::in_uso( DatiProcedimento::UFFICIO ) );
		$this->assertTrue( DatiProcedimento::in_uso( DatiProcedimento::RICHIEDENTE ) );
	}

	/**
	 * A-167: [attacco] impostazione scritta a mano in forma diversa: vale come scelta non fatta.
	 */
	public function test_a167_impostazione_scritta_di_lato(): void {
		$this->scegli( DatiProcedimento::USA );

		$buona = get_option( DatiProcedimento::OPZIONE );

		$this->assertIsArray( $buona );
		$this->assertSame( $buona, DatiProcedimento::impostazione(), 'Controllo positivo: la forma del salvataggio si legge.' );

		$varianti = array(
			'non un insieme'         => 'usa',
			'chiave mancante'        => array_diff_key( $buona, array( 'uffici' => true ) ),
			'scelta sconosciuta'     => array_merge( $buona, array( 'scelte' => array_merge( $buona['scelte'], array( 'responsabile' => 'si' ) ) ) ),
			'scelte in altro ordine' => array_merge( $buona, array( 'scelte' => array_reverse( $buona['scelte'], true ) ) ),
			'elenco non in fila'     => array_merge( $buona, array( 'uffici' => array( 3 => 'Ufficio tecnico' ) ) ),
			'nome non valido'        => array_merge( $buona, array( 'uffici' => array( '<i>Ufficio</i>' ) ) ),
			'nome ripetuto'          => array_merge( $buona, array( 'uffici' => array( 'Ufficio', 'UFFICIO' ) ) ),
			'nome non testo'         => array_merge( $buona, array( 'uffici' => array( 12 ) ) ),
			'in uso, elenco vuoto'   => array_merge( $buona, array( 'responsabili' => array() ) ),
			'istante vuoto'          => array_merge( $buona, array( 'istante' => '' ) ),
			'istante inesistente'    => array_merge( $buona, array( 'istante' => '2041-02-31 10:00:00' ) ),
			'istante con byte nullo' => array_merge( $buona, array( 'istante' => "2041-10-01 09:30:00\0" ) ),
			'utente non numero'      => array_merge( $buona, array( 'utente' => '3' ) ),
		);

		foreach ( $varianti as $nome => $valore ) {
			update_option( DatiProcedimento::OPZIONE, $valore );

			$this->assertNull( DatiProcedimento::impostazione(), $nome );
			$this->assertFalse( DatiProcedimento::in_uso( DatiProcedimento::RICHIEDENTE ), $nome );
			$this->assertSame( array(), DatiProcedimento::elenco( DatiProcedimento::UFFICIO ), $nome );

			$html = $this->stampa( $this->pubblicatore, array( SchermataDatiProcedimento::class, 'mostra' ) );

			$this->assertStringNotContainsString( 'checked', $html, $nome . ': nessuna scelta segnata.' );
			$this->assertStringContainsString( 'non si legge', $html, $nome );
		}

		$id = $this->atto_in_verifica();
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ), 'L\'albo pubblica comunque.' );
	}

	/**
	 * A-168: la scheda accetta solo voci dell'elenco, conserva quelle tolte, ripulisce il richiedente.
	 */
	public function test_a168_scheda_e_elenchi(): void {
		$this->scegli( DatiProcedimento::USA );

		$atto = $this->atto_completo();

		$motivi = $this->invia_scheda(
			$atto,
			array(
				DatiProcedimento::RESPONSABILE => 'Persona inventata',
				DatiProcedimento::UFFICIO      => 'Ufficio tecnico',
				DatiProcedimento::RICHIEDENTE  => '<b>Altro ente</b>',
			)
		);

		$this->assertCount( 1, $motivi );
		$this->assertStringContainsString( 'Responsabile del procedimento non salvato', $motivi[0] );
		$this->assertSame(
			array(
				DatiProcedimento::RESPONSABILE => array(),
				DatiProcedimento::UFFICIO      => array( 'Ufficio tecnico' ),
				DatiProcedimento::RICHIEDENTE  => array( 'Altro ente' ),
			),
			$this->grezzi( $atto ),
			'Il valore sbagliato non si salva, gli altri si'
		);

		$this->assertCount( 1, $this->invia_scheda( $atto, array( DatiProcedimento::RICHIEDENTE => array( 'Altro' ) ) ), 'Richiedente come insieme.' );
		$this->assertCount( 1, $this->invia_scheda( $atto, array( DatiProcedimento::RICHIEDENTE => str_repeat( 'a', 201 ) ) ), 'Richiedente troppo lungo.' );
		$this->assertSame( array( 'Altro ente' ), $this->grezzi( $atto )[ DatiProcedimento::RICHIEDENTE ] );

		$this->assertSame( array(), $this->invia_scheda( $atto, array( DatiProcedimento::RESPONSABILE => 'Mario Rossi' ) ) );

		// Mario Rossi esce dall'elenco: resta sull'atto, la scheda lo mostra segnato, salvare non lo toglie.
		$this->scegli( DatiProcedimento::USA, 'Anna Bianchi' );

		$opzioni = $this->opzioni( $this->riquadro( $atto ), SchedaAtto::campo_procedimento( DatiProcedimento::RESPONSABILE ) );

		$this->assertSame(
			array(
				array( '', 'Nessuno', false ),
				array( 'Mario Rossi', 'Mario Rossi (non piu\' nell\'elenco)', true ),
				array( 'Anna Bianchi', 'Anna Bianchi', false ),
			),
			$opzioni
		);

		$this->assertSame( array(), $this->invia_scheda( $atto, array( DatiProcedimento::RESPONSABILE => 'Mario Rossi' ) ) );
		$this->assertSame( array( 'Mario Rossi' ), $this->grezzi( $atto )[ DatiProcedimento::RESPONSABILE ] );

		// Un altro atto non lo puo' scegliere piu'.
		$altro = $this->atto_completo();
		$this->assertCount( 1, $this->invia_scheda( $altro, array( DatiProcedimento::RESPONSABILE => 'Mario Rossi' ) ) );
		$this->assertSame( array(), $this->grezzi( $altro )[ DatiProcedimento::RESPONSABILE ] );

		// La voce vuota toglie il dato.
		$this->assertSame( array(), $this->invia_scheda( $atto, array( DatiProcedimento::RESPONSABILE => '' ) ) );
		$this->assertSame( array(), $this->grezzi( $atto )[ DatiProcedimento::RESPONSABILE ] );
	}

	/**
	 * A-169: alla pubblicazione i dati si fissano e vanno nel registro, che non cambia con gli elenchi.
	 */
	public function test_a169_registro_e_dati_fissati(): void {
		$this->scegli( DatiProcedimento::USA );

		$id = $this->atto_completo();

		$this->assertSame(
			array(),
			$this->invia_scheda(
				$id,
				array(
					DatiProcedimento::RESPONSABILE => 'Mario Rossi',
					DatiProcedimento::UFFICIO      => 'Ufficio tecnico',
					DatiProcedimento::RICHIEDENTE  => 'Altro ente',
				)
			)
		);

		$this->in_verifica( $id );

		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );

		$conferma = $this->voci_di( $id, array( 'azione' => 'conferma_dati_personali' ) );

		$this->assertCount( 1, $conferma );
		$this->assertSame( 'Mario Rossi', $conferma[0]['dettagli']['responsabile'] );
		$this->assertSame( 'Ufficio tecnico', $conferma[0]['dettagli']['ufficio'] );
		$this->assertSame( 'Altro ente', $conferma[0]['dettagli']['richiedente'] );

		$registro = $this->voci_di( $id );
		$grezzi   = $this->grezzi( $id );

		// Gli elenchi cambiano, la scelta si spegne: registro e dati dell'atto restano.
		$this->scegli( DatiProcedimento::USA, 'Anna Bianchi', 'Segreteria' );
		$this->scegli( DatiProcedimento::NON_USA );

		// Dopo la pubblicazione i dati non si cambiano, da nessuna strada.
		update_post_meta( $id, DatiProcedimento::meta( DatiProcedimento::RESPONSABILE ), 'Anna Bianchi' );
		delete_post_meta( $id, DatiProcedimento::meta( DatiProcedimento::UFFICIO ) );
		add_post_meta( $id, DatiProcedimento::meta( DatiProcedimento::RICHIEDENTE ), 'Secondo' );

		$this->scegli( DatiProcedimento::USA, 'Anna Bianchi', 'Segreteria' );
		$this->invia_scheda( $id, array( DatiProcedimento::RESPONSABILE => 'Anna Bianchi' ) );

		$this->assertSame( $grezzi, $this->grezzi( $id ) );
		$this->assertSame( $registro, $this->voci_di( $id ) );
	}

	/**
	 * A-170: la scelta cambiata decide che cosa si mostra; i dati restano.
	 */
	public function test_a170_scelta_cambiata(): void {
		$this->scegli( DatiProcedimento::USA );

		$id = $this->atto_completo();
		$this->scrivi_di_lato(
			$id,
			array(
				DatiProcedimento::RESPONSABILE => 'Giulia Verdi',
				DatiProcedimento::RICHIEDENTE  => 'Altro ente',
			)
		);
		$this->in_verifica( $id );
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );

		$grezzi = $this->grezzi( $id );
		$attesi = array(
			'Responsabile del procedimento'   => 'Giulia Verdi',
			'Richiedente della pubblicazione' => 'Altro ente',
		);

		$this->assertSame( $attesi, DatiProcedimento::da_mostrare( $id ), 'Il nome tolto dall\'elenco, o mai entrato, si mostra.' );

		$this->scegli( DatiProcedimento::NON_USA );
		$this->assertSame( array(), DatiProcedimento::da_mostrare( $id ) );
		$this->assertStringNotContainsString( 'Giulia Verdi', $this->riquadro( $id ) );
		$this->assertSame( $grezzi, $this->grezzi( $id ) );

		$scelte = array(
			DatiProcedimento::RESPONSABILE => DatiProcedimento::NON_USA,
			DatiProcedimento::UFFICIO      => DatiProcedimento::NON_USA,
			DatiProcedimento::RICHIEDENTE  => DatiProcedimento::USA,
		);
		$this->assertTrue( DatiProcedimento::salva( $scelte, '', '', $this->pubblicatore, Passaggi::ora() ) );
		$this->assertSame( array( 'Richiedente della pubblicazione' => 'Altro ente' ), DatiProcedimento::da_mostrare( $id ), 'Una scelta per ciascun dato.' );

		$this->scegli( DatiProcedimento::USA );
		$this->assertSame( $attesi, DatiProcedimento::da_mostrare( $id ), 'Tornano visibili.' );
	}

	/**
	 * A-171: la lettura valida il dato letto; i dati sono dichiarati protetti e senza esposizione per programmi.
	 */
	public function test_a171_lettura_validata(): void {
		$this->scegli( DatiProcedimento::USA );

		$atto   = $this->atto_completo();
		$chiave = DatiProcedimento::meta( DatiProcedimento::UFFICIO );

		$casi = array(
			'due righe'              => array( 'Ufficio tecnico', 'Segreteria' ),
			'non un testo'           => array( array( 'Ufficio tecnico' ) ),
			'segni di formattazione' => array( '<b>Ufficio</b>' ),
			'spazi ai lati'          => array( ' Ufficio' ),
			'troppo lungo'           => array( str_repeat( 'a', 201 ) ),
			'carattere di controllo' => array( "Uff\x01icio" ),
		);

		foreach ( $casi as $nome => $righe ) {
			delete_post_meta( $atto, $chiave );

			foreach ( $righe as $riga ) {
				add_post_meta( $atto, $chiave, $riga );
			}

			$this->assertSame( '', DatiProcedimento::valore( $atto, DatiProcedimento::UFFICIO ), $nome );
			$this->assertSame( array(), DatiProcedimento::da_mostrare( $atto ), $nome );
		}

		$dichiarati = get_registered_meta_keys( 'post', TIPO );

		foreach ( DatiProcedimento::DATI as $dato ) {
			$this->assertArrayHasKey( DatiProcedimento::meta( $dato ), $dichiarati );
			$this->assertFalse( $dichiarati[ DatiProcedimento::meta( $dato ) ]['show_in_rest'] );
			$this->assertTrue( is_protected_meta( DatiProcedimento::meta( $dato ), 'post' ) );
		}
	}
}
