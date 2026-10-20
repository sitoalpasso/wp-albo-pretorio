<?php
/**
 * La pagina pubblica dell'albo: righe A-195..A-203 (ALBO-06, ALBO-16, ALBO-28).
 *
 * Le prove fanno richieste vere alla pagina che contiene il codice breve, con
 * gli atti portati a pubblicato dal passaggio vero e l'orologio fissato. Il
 * compito pianificato non esiste: a togliere uno scaduto e' soltanto la data
 * letta. Ogni assenza ha accanto la sua presenza di controllo, un atto che
 * nello stesso istante si vede, altrimenti direbbe soltanto che la pagina e'
 * rotta.
 *
 * I valori attesi sono scritti per esteso: 1 ottobre 2041 alle 9:30 piu' 15
 * giorni fa fine il 16 ottobre, visibile fino alle 23:59:59 e sparito dalla
 * mezzanotte.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\PaginaPubblica;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\VistaPubblica;

use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TIPO;

/**
 * Elenco, scheda, ricerca, motori, memorie e accessibilita' della pagina pubblica.
 */
class PaginaPubblicaTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'ultimo istante in cui l'atto del primo ottobre e' in pubblicazione.
	 */
	private const ULTIMO_ISTANTE = '2041-10-16 23:59:59';

	/**
	 * Il primo istante in cui l'atto del primo ottobre e' scaduto.
	 */
	private const PRIMO_ISTANTE_SCADUTO = '2041-10-17 00:00:00';

	/**
	 * Le risposte del punto pubblico di consegna dei documenti.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $consegne = array();

	/**
	 * Codici di stato emessi durante una richiesta.
	 *
	 * @var array<int, int>
	 */
	private array $stati = array();

	/**
	 * La pagina dell'albo, creata dalle prove che la vogliono.
	 *
	 * @var int
	 */
	private int $pagina = 0;

	/**
	 * Assetto comune, indirizzi semplici, consegna e stati osservati.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->set_permalink_structure( '' );

		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ) {
				$this->consegne[] = $risposta;
			}
		);

		add_filter( 'status_header', array( $this, 'osserva_stato' ), 10, 2 );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		remove_filter( 'status_header', array( $this, 'osserva_stato' ), 10 );
		\Conformita_Core_Consegna::azzera_emettitore();

		$this->smonta_flusso();
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * Annota il codice di stato.
	 *
	 * @param mixed $intestazione Riga di stato.
	 * @param mixed $codice       Codice.
	 * @return mixed
	 */
	public function osserva_stato( $intestazione, $codice ) {
		$this->stati[] = (int) $codice;

		return $intestazione;
	}

	/**
	 * La pagina dell'albo, pubblicata, con un testo prima del codice breve.
	 *
	 * @return int
	 */
	private function pagina_albo(): int {
		$this->pagina = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Albo pretorio',
				'post_name'    => 'albo-pretorio',
				'post_content' => '<p>Gli atti in pubblicazione.</p>' . "\n\n" . '[albo_pretorio]',
			)
		);

		PaginaPubblica::azzera();

		$this->assertSame( array( $this->pagina ), PaginaPubblica::pagine(), 'Precondizione: la pagina dell\'albo e\' riconosciuta.' );

		return $this->pagina;
	}

	/**
	 * Un atto pubblicato con il passaggio vero all'istante indicato.
	 *
	 * @param string             $istante Data e ora nel fuso del sito.
	 * @param string             $oggetto Oggetto.
	 * @param string             $tipo    Nome breve del tipo.
	 * @param array<int, string> $allegati Nomi dei file degli allegati ulteriori.
	 * @param int|null           $organo  Organo al posto di quello comune.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto, string $tipo = 'normativo', array $allegati = array(), ?int $organo = null ): int {
		$this->fissa_orologi( $istante );

		$id = $this->atto_completo( $tipo, array( 'post_title' => $oggetto ) );

		if ( null !== $organo ) {
			wp_set_object_terms( $id, array( $organo ), TASSONOMIA_ORGANO );
		}

		foreach ( $allegati as $nome ) {
			$this->deposita_documento( $id, DocumentiAtto::ULTERIORE, $nome );
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
		$this->assertSame( 'publish', get_post_status( $id ), 'Precondizione: l\'atto e\' pubblicato.' );
		$this->assertNotNull( Repertorio::di( $id ), 'Precondizione: l\'atto ha il numero di registro.' );

		return $id;
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
		$this->assertFalse( conformita_core_scaduto( $id ), 'Precondizione: per il meccanismo comune non e\' scaduto.' );
		$this->assertNull( Repertorio::di( $id ), 'Precondizione: non ha numero.' );

		return $id;
	}

	/**
	 * L'indirizzo della pagina dell'albo con i parametri indicati.
	 *
	 * @param array<string, mixed> $parametri Parametri.
	 * @return string
	 */
	private function indirizzo( array $parametri = array() ): string {
		return add_query_arg( $parametri, (string) get_permalink( $this->pagina ) );
	}

	/**
	 * Una richiesta vera, e quello che la pagina disegna; nullo se "non trovata".
	 *
	 * Il codice breve si disegna come lo disegna il tema, attraverso il filtro
	 * del contenuto della pagina in corso.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return string|null
	 */
	private function pagina_disegnata( string $indirizzo ): ?string {
		$this->stati = array();
		$this->richiesta_pubblica( $indirizzo );

		if ( is_404() ) {
			return null;
		}

		$pagina = get_queried_object();

		$this->assertInstanceOf( \WP_Post::class, $pagina, 'Precondizione: la richiesta apre una pagina.' );

		$GLOBALS['post'] = $pagina;
		setup_postdata( $pagina );

		return (string) apply_filters( 'the_content', $pagina->post_content );
	}

	/**
	 * Gli atti elencati, nell'ordine in cui compaiono.
	 *
	 * @param string $html Codice disegnato.
	 * @return array<int, int>
	 */
	private function atti_elencati( string $html ): array {
		preg_match_all( '/<tr class="albo-pretorio__atto" role="row" data-atto="([0-9]+)"/', $html, $trovati );

		return array_map( 'intval', $trovati[1] );
	}

	/**
	 * Le celle della riga di un atto, per intestazione di colonna, come le legge un lettore di schermo.
	 *
	 * Il nome della colonna scritto davanti al dato e' nascosto ai lettori di
	 * schermo e qui non si legge: il dato lo prende dall'intestazione.
	 *
	 * @param string $html Codice disegnato.
	 * @param int    $atto Atto.
	 * @return array<string, string>
	 */
	private function celle( string $html, int $atto ): array {
		$xpath        = $this->leggi( $html );
		$intestazioni = array();

		foreach ( $xpath->query( '//table//thead//th' ) as $intestazione ) {
			$intestazioni[] = $this->testo( $intestazione );
		}

		$righe = $xpath->query( '//tr[@data-atto="' . $atto . '"]' );
		$this->assertSame( 1, $righe->length, 'Precondizione: la riga dell\'atto ' . $atto . ' c\'e\' una volta.' );

		$celle = array();

		foreach ( $xpath->query( './th | ./td', $righe->item( 0 ) ) as $posizione => $cella ) {
			foreach ( $xpath->query( './/*[@aria-hidden="true"]', $cella ) as $nascosto ) {
				$nascosto->parentNode->removeChild( $nascosto ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di DOMNode.
			}

			$celle[ $intestazioni[ $posizione ] ?? (string) $posizione ] = trim( (string) preg_replace( '/\s+/u', ' ', $this->testo( $cella ) ) );
		}

		return $celle;
	}

	/**
	 * Un codice disegnato, aperto per le interrogazioni.
	 *
	 * @param string $html Codice disegnato.
	 * @return \DOMXPath
	 */
	private function leggi( string $html ): \DOMXPath {
		$documento = new \DOMDocument();
		$vecchio   = libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $vecchio );

		return new \DOMXPath( $documento );
	}

	/**
	 * Quello che risponde una richiesta, per il confronto con un indirizzo mai esistito.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return array<string, mixed>
	 */
	private function risposta( string $indirizzo ): array {
		$this->stati = array();
		$this->richiesta_pubblica( $indirizzo );

		return array(
			'non trovata'  => is_404(),
			'stati'        => array_values( array_unique( $this->stati ) ),
			'risultati'    => wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' ),
			'titolo'       => wp_get_document_title(),
			'intestazioni' => apply_filters( 'wp_headers', array(), $GLOBALS['wp'] ),
			'robot'        => $this->robot_stampati(),
		);
	}

	/**
	 * Tutto cio' che il punto pubblico di consegna emette per un documento.
	 *
	 * @param int $documento Documento.
	 * @return array<int, array<string, mixed>>
	 */
	private function consegna( int $documento ): array {
		$this->consegne = array();

		$this->go_to( (string) conformita_core_indirizzo_consegna( $documento ) );

		return $this->consegne;
	}

	/**
	 * La consegna riuscita di un file.
	 *
	 * @param array<int, array<string, mixed>> $consegne Risposte.
	 * @return bool
	 */
	private function consegnato( array $consegne ): bool {
		return 1 === count( $consegne ) && 200 === ( $consegne[0]['stato'] ?? 0 ) && ! empty( $consegne[0]['percorso'] );
	}

	/**
	 * A-202 (memorie di pagina): la costante che le estensioni di memoria leggono.
	 *
	 * Sta per prima perche' una costante, una volta definita, non si toglie:
	 * il controllo che prima di una richiesta della pagina dell'albo non ci
	 * sia vale solo qui.
	 */
	public function test_a202_memorie_di_pagina_la_costante(): void {
		$ordinaria = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Pagina ordinaria',
			)
		);
		$this->pagina_albo();

		$this->richiesta_pubblica( (string) get_permalink( $ordinaria ) );
		$this->assertTrue( is_page( $ordinaria ), 'Precondizione: la pagina ordinaria si apre.' );
		$this->assertFalse( defined( 'DONOTCACHEPAGE' ), 'Una pagina ordinaria non definisce la costante.' );

		$this->richiesta_pubblica( $this->indirizzo() );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && true === constant( 'DONOTCACHEPAGE' ), 'La pagina dell\'albo definisce la costante.' );
	}

	/**
	 * A-195: l'elenco mostra solo gli atti in pubblicazione, dal piu' recente.
	 */
	public function test_a195_elenco_solo_atti_in_pubblicazione(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$this->pagina_albo();

		$scaduto = $this->pubblicato_il( '2041-09-01 09:00:00', 'Atto gia\' scaduto' );
		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Deliberazione sulla viabilita\'' );
		$secondo = $this->pubblicato_il( '2041-10-03 11:00:00', 'Avviso di selezione', 'scelto' );

		$bozza = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$verif = $this->atto_in_verifica( 'normativo', array( 'post_title' => 'Atto in verifica di prova' ) );
		$scav  = $this->scavalcato();

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
		) as $chi => $utente ) {
			wp_set_current_user( $utente );

			$html = (string) $this->pagina_disegnata( $this->indirizzo() );

			$this->assertSame( array( $secondo, $primo ), $this->atti_elencati( $html ), $chi . ': solo i due in corso, dal piu\' recente.' );

			foreach ( array( $scaduto, $bozza, $verif, $scav ) as $assente ) {
				$this->assertStringNotContainsString( get_the_title( $assente ), $html, $chi . ': nessuna traccia dell\'oggetto di ' . $assente . '.' );
			}

			$numero = Repertorio::di( $primo );
			$this->assertIsArray( $numero );

			$this->assertStringContainsString( '<h2 id="albo-pretorio-titolo-elenco" class="albo-pretorio__titolo">Atti in pubblicazione</h2>', $html, $chi );
			$this->assertStringContainsString( '<p class="albo-pretorio__conteggio">2 atti</p>', $html, $chi );
			$this->assertStringContainsString( 'href="' . esc_url( home_url( '/albo-pretorio/?albo_atto=' . $primo ) ) . '">' . get_the_title( $primo ) . '</a>', $html, $chi . ': l\'oggetto apre la scheda.' );
			$this->assertSame(
				array(
					'Numero'         => $numero['numero'] . '/2041',
					'Tipo'           => 'Deliberazione',
					'Oggetto'        => html_entity_decode( get_the_title( $primo ), ENT_QUOTES, 'UTF-8' ) . ' Giunta',
					'Pubblicato dal' => '01/10/2041',
					'Fino al'        => '16/10/2041',
					'Documenti'      => '1 documento',
				),
				$this->celle( $html, $primo ),
				$chi . ': la riga del primo, colonna per colonna.'
			);
			$this->assertSame( '03/10/2041', $this->celle( $html, $secondo )['Pubblicato dal'], $chi );
			$this->assertSame( '13/10/2041', $this->celle( $html, $secondo )['Fino al'], $chi );
			$this->assertStringContainsString( 'Gli atti in pubblicazione.', $html, $chi . ': il testo della pagina resta.' );
		}

		$this->assertSame( 'publish', get_post_status( $scaduto ), 'Lo scaduto resta pubblicato nello stato: a toglierlo e\' la data.' );
	}

	/**
	 * A-195: venti per pagina, e le pagine messe insieme danno tutti gli atti una volta.
	 */
	public function test_a195_venti_per_pagina(): void {
		$this->pagina_albo();

		$attesi = array();

		for ( $i = 0; $i < 21; $i++ ) {
			$attesi[] = $this->pubblicato_il( sprintf( '2041-10-01 09:%02d:00', $i ), 'Atto numero ' . $i );
		}

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$prima = (string) $this->pagina_disegnata( $this->indirizzo() );
		$this->assertCount( 20, $this->atti_elencati( $prima ), 'La prima pagina e\' piena.' );
		$this->assertStringContainsString( '<p class="albo-pretorio__conteggio">21 atti</p>', $prima );
		$this->assertSame( 1, substr_count( $prima, 'aria-current="page"' ), 'La pagina corrente e\' dichiarata una volta.' );

		preg_match( '/<a class="albo-pretorio__collegamento" aria-label="Pagina 2" href="([^"]+)">2<\/a>/', $prima, $collegamento );
		$this->assertNotEmpty( $collegamento, 'Il collegamento alla seconda pagina c\'e\'.' );

		$seconda = (string) $this->pagina_disegnata( html_entity_decode( $collegamento[1] ) );
		$tutti   = array_merge( $this->atti_elencati( $prima ), $this->atti_elencati( $seconda ) );

		$this->assertSame( array_reverse( $attesi ), $tutti, 'Tutti, una volta ciascuno, dal piu\' recente.' );
	}

	/**
	 * A-196: la scheda mostra dati e documenti, senza il nome del file caricato.
	 */
	public function test_a196_scheda_dell_atto(): void {
		$this->pagina_albo();

		$atto = $this->pubblicato_il( '2041-10-01 09:30:00', 'Deliberazione con allegati', 'normativo', array( 'Mario-Rossi-certificato.pdf', 'Mario-Rossi-domanda.pdf' ) );
		update_post_meta( $atto, \AlboPretorioPa\META_NUMERO_PROPRIO, '' );

		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		$html = (string) $this->pagina_disegnata( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atto ) ) );

		$this->assertNotContains( 404, $this->stati, 'La scheda di un atto in corso non risponde "non trovata".' );

		$numero = Repertorio::di( $atto );
		$this->assertIsArray( $numero );

		$this->assertStringContainsString( '<h2 id="albo-pretorio-titolo-atto" class="albo-pretorio__titolo">Deliberazione con allegati</h2>', $html );
		$this->assertStringContainsString( '<dt>Numero di registro</dt><dd>' . $numero['numero'] . '/2041</dd>', $html );
		$this->assertStringContainsString( '<dt>Data di adozione</dt><dd>10/09/2041</dd>', $html );
		$this->assertStringContainsString( '<dt>Pubblicato dal</dt><dd>01/10/2041</dd></div><div class="albo-pretorio__dato"><dt>Fino al</dt><dd>16/10/2041</dd>', $html );
		$this->assertStringContainsString( 'Torna all&#039;elenco degli atti', $html );
		$this->assertStringNotContainsString( 'Mario-Rossi', $html, 'Il nome del file caricato non compare.' );
		$this->assertStringNotContainsString( 'albo-pretorio__tabella', $html, 'La scheda non porta l\'elenco.' );
		$this->assertSame( 'Atto ' . $numero['numero'] . '/2041 &#8211; ' . get_bloginfo( 'name', 'display' ), wp_get_document_title(), 'Il titolo della finestra porta il numero, non l\'oggetto.' );

		$documenti = array_merge( array( DocumentiAtto::principale( $atto ) ), DocumentiAtto::ulteriori( $atto ) );
		$this->assertCount( 3, $documenti, 'Precondizione: un principale e due allegati.' );

		foreach ( array( 'Documento principale', 'Allegato 1', 'Allegato 2' ) as $posizione => $nome ) {
			$indirizzo = (string) conformita_core_indirizzo_consegna( (int) $documenti[ $posizione ] );

			$this->assertStringContainsString( '<a class="albo-pretorio__collegamento" href="' . esc_url( $indirizzo ) . '">' . $nome . '</a> <span class="albo-pretorio__dettagli">(PDF, ', $html, $nome . ': collegamento alla consegna, con formato e dimensione.' );
			$this->assertTrue( $this->consegnato( $this->consegna( (int) $documenti[ $posizione ] ) ), $nome . ': il collegamento consegna il file.' );
		}
	}

	/**
	 * A-196: ogni scheda che non e' in pubblicazione risponde come un atto mai esistito.
	 */
	public function test_a196_scheda_non_in_pubblicazione_come_mai_esistita(): void {
		$this->pagina_albo();

		$scaduto = $this->pubblicato_il( '2041-09-01 09:00:00', 'Atto gia\' scaduto' );
		$corso   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso' );
		$bozza   = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$verif   = $this->atto_in_verifica( 'normativo', array( 'post_title' => 'Atto in verifica di prova' ) );
		$scav    = $this->scavalcato();
		$altro   = self::factory()->post->create( array( 'post_title' => 'Articolo ordinario' ) );

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
		) as $chi => $utente ) {
			wp_set_current_user( $utente );

			$this->richiesta_pubblica( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $corso ) ) );
			$this->assertFalse( is_404(), $chi . ': precondizione, la scheda dell\'atto in corso si apre.' );

			$mai = $this->risposta( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => 999999999 ) ) );
			$this->assertTrue( $mai['non trovata'], $chi . ': precondizione, un numero mai dato risponde "non trovata".' );
			$this->assertSame( array( 404 ), $mai['stati'], $chi . ': precondizione, con lo stato 404.' );

			foreach ( array(
				'scaduto'            => $scaduto,
				'bozza'              => $bozza,
				'in verifica'        => $verif,
				'scavalcato'         => $scav,
				'articolo ordinario' => $altro,
				'la pagina stessa'   => $this->pagina,
				'zero'               => '0',
				'lettere'            => 'abc',
				'negativo'           => '-' . $corso,
				'con spazi'          => ' ' . $corso,
				'numero enorme'      => '99999999999999999999999',
				'elenco'             => array( $corso ),
			) as $quale => $valore ) {
				$this->assertSame( $mai, $this->risposta( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $valore ) ) ), $chi . ', ' . $quale . ': risponde come un atto mai esistito.' );
			}
		}
	}

	/**
	 * A-197: dalla mezzanotte l'atto sparisce da elenco, scheda e documento.
	 */
	public function test_a197_mezzanotte_della_fine(): void {
		$this->pagina_albo();

		$atto      = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$controllo = $this->pubblicato_il( '2041-10-10 09:30:00', 'Atto di controllo' );
		$documento = (int) DocumentiAtto::principale( $atto );
		$scheda    = $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atto ) );
		$inventato = $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => 999999999 ) );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
		) as $chi => $utente ) {
			wp_set_current_user( $utente );

			$this->fissa_orologi( self::ULTIMO_ISTANTE );
			$this->assertSame( array( $controllo, $atto ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo() ) ), $chi . ', ultimo secondo: tutti e due in elenco.' );
			$this->assertNotNull( $this->pagina_disegnata( $scheda ), $chi . ', ultimo secondo: la scheda si apre.' );
			$this->assertTrue( $this->consegnato( $this->consegna( $documento ) ), $chi . ', ultimo secondo: il documento si scarica.' );

			$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );
			$this->assertSame( array( $controllo ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo() ) ), $chi . ', mezzanotte: resta solo il controllo.' );
			$this->assertSame( $this->risposta( $inventato ), $this->risposta( $scheda ), $chi . ', mezzanotte: la scheda risponde come un atto mai esistito.' );

			$rifiuto = $this->consegna( $documento );
			$this->assertFalse( $this->consegnato( $rifiuto ), $chi . ', mezzanotte: il documento non si scarica.' );
			$this->assertTrue( $this->consegnato( $this->consegna( (int) DocumentiAtto::principale( $controllo ) ) ), $chi . ', mezzanotte: il documento del controllo si scarica.' );
		}

		$this->assertSame( 'publish', get_post_status( $atto ), 'Lo stato resta pubblicato: a toglierlo e\' la data.' );
	}

	/**
	 * A-198: ogni criterio di ricerca restringe a un sottoinsieme proprio, e lo scaduto non c'e' mai.
	 */
	public function test_a198_ricerca(): void {
		$this->pagina_albo();

		$consiglio = $this->voce( TASSONOMIA_ORGANO, 'Consiglio' );

		$vecchio = $this->pubblicato_il( '2041-09-01 09:00:00', 'Deliberazione sulla viabilita\' vecchia' );
		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Deliberazione sulla viabilita\' di via Roma' );
		$secondo = $this->pubblicato_il( '2041-10-03 11:00:00', 'Avviso di gara', 'scelto', array(), $consiglio );

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$numero_secondo = Repertorio::di( $secondo );
		$numero_vecchio = Repertorio::di( $vecchio );
		$this->assertIsArray( $numero_secondo );
		$this->assertIsArray( $numero_vecchio );

		$this->assertSame( array( $secondo, $primo ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Precondizione: senza ricerca ci sono tutti e due.' );

		foreach ( array(
			'parola dell\'oggetto'       => array( array( VistaPubblica::CAMPO_TESTO => 'viabilita\'' ), array( $primo ) ),
			'parola anche dello scaduto' => array( array( VistaPubblica::CAMPO_TESTO => 'Deliberazione' ), array( $primo ) ),
			'numero di registro'         => array( array( VistaPubblica::CAMPO_TESTO => $numero_secondo['numero'] . '/2041' ), array( $secondo ) ),
			'numero con spazi'           => array( array( VistaPubblica::CAMPO_TESTO => $numero_secondo['numero'] . ' / 2041' ), array( $secondo ) ),
			'numero dello scaduto'       => array( array( VistaPubblica::CAMPO_TESTO => $numero_vecchio['numero'] . '/2041' ), array() ),
			'numero mai dato'            => array( array( VistaPubblica::CAMPO_TESTO => '9999/2041' ), array() ),
			'tipo'                       => array( array( VistaPubblica::CAMPO_TIPO => $this->voci['scelto'] ), array( $secondo ) ),
			'organo'                     => array( array( VistaPubblica::CAMPO_ORGANO => $consiglio ), array( $secondo ) ),
			'dal'                        => array( array( VistaPubblica::CAMPO_DAL => '2041-10-02' ), array( $secondo ) ),
			'fino al'                    => array( array( VistaPubblica::CAMPO_AL => '2041-10-02' ), array( $primo ) ),
			'un giorno solo'             => array(
				array(
					VistaPubblica::CAMPO_DAL => '2041-10-01',
					VistaPubblica::CAMPO_AL  => '2041-10-01',
				),
				array( $primo ),
			),
			'tipo e organo insieme'      => array(
				array(
					VistaPubblica::CAMPO_TIPO   => $this->voci['normativo'],
					VistaPubblica::CAMPO_ORGANO => $consiglio,
				),
				array(),
			),
		) as $caso => list( $parametri, $attesi ) ) {
			$html = (string) $this->pagina_disegnata( $this->indirizzo( $parametri ) );

			$this->assertSame( $attesi, $this->atti_elencati( $html ), $caso );
			$this->assertStringContainsString( '>Risultati della ricerca</h2>', $html, $caso . ': il titolo dice che e\' una ricerca.' );
			$this->assertStringContainsString( 'Mostra tutti gli atti in pubblicazione', $html, $caso . ': si torna all\'elenco intero.' );
			$this->assertStringNotContainsString( 'vecchia', $html, $caso . ': dello scaduto nessuna traccia.' );

			if ( array() === $attesi ) {
				$this->assertStringContainsString( 'Nessun atto in pubblicazione risponde alla ricerca.', $html, $caso );
			}
		}

		$html = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_TIPO => $this->voci['scelto'] ) ) );
		$this->assertMatchesRegularExpression( '/<option value="' . $this->voci['scelto'] . '" selected=\'selected\'>Avviso<\/option>/', $html, 'La scelta resta nel modulo.' );
	}

	/**
	 * A-199: un campo scritto male non si scarta in silenzio, e le pagine conservano la ricerca.
	 */
	public function test_a199_ricerca_scritta_male_e_pagine(): void {
		$this->pagina_albo();

		$atto = $this->pubblicato_il( '2041-10-01 09:30:00', 'Avviso di prova' );

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		foreach ( array(
			'numero zero'            => array( VistaPubblica::CAMPO_TESTO, '0/2041' ),
			'tipo con lettere'       => array( VistaPubblica::CAMPO_TIPO, 'abc' ),
			'tipo inesistente'       => array( VistaPubblica::CAMPO_TIPO, '999999' ),
			'tipo come elenco'       => array( VistaPubblica::CAMPO_TIPO, array( $this->voci['normativo'] ) ),
			'organo di altro elenco' => array( VistaPubblica::CAMPO_ORGANO, (string) $this->voci['normativo'] ),
			'data impossibile'       => array( VistaPubblica::CAMPO_DAL, '2041-02-30' ),
			'data in altro formato'  => array( VistaPubblica::CAMPO_DAL, '01/10/2041' ),
		) as $caso => list( $campo, $valore ) ) {
			$html = (string) $this->pagina_disegnata( $this->indirizzo( array( $campo => $valore ) ) );

			$this->assertSame( array(), $this->atti_elencati( $html ), $caso . ': nessun atto, nemmeno quello che c\'e\'.' );
			$this->assertStringContainsString( '<p id="albo-pretorio-errore-ricerca" class="albo-pretorio__avviso" role="alert">La ricerca non è stata fatta.', $html, $caso . ': c\'e\' il messaggio.' );
			$this->assertMatchesRegularExpression( '/id="' . $campo . '"[^>]* aria-describedby="[^"]*albo-pretorio-errore-ricerca[^"]*" aria-invalid="true"/', $html, $caso . ': il campo e\' segnato e collegato al messaggio.' );
		}

		$html = (string) $this->pagina_disegnata(
			$this->indirizzo(
				array(
					VistaPubblica::CAMPO_DAL => '2041-10-03',
					VistaPubblica::CAMPO_AL  => '2041-10-02',
				)
			)
		);
		$this->assertSame( array(), $this->atti_elencati( $html ), 'Fine prima dell\'inizio: nessun atto.' );
		$this->assertStringContainsString( 'viene prima della data', $html, 'Fine prima dell\'inizio: il messaggio lo dice.' );

		$this->assertSame( array( $atto ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_TESTO => 'Avviso' ) ) ) ), 'Controllo positivo: la stessa pagina trova l\'atto con un campo giusto.' );

		// Le pagine conservano la ricerca: ventuno atti con la stessa parola e uno senza.
		$attesi = array();

		for ( $i = 0; $i < 21; $i++ ) {
			$attesi[] = $this->pubblicato_il( sprintf( '2041-10-02 09:%02d:00', $i ), 'Bando di concorso ' . $i );
		}

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$prima = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_TESTO => 'Bando di concorso' ) ) );
		$this->assertStringContainsString( '<p class="albo-pretorio__conteggio">21 atti</p>', $prima );

		preg_match( '/aria-label="Pagina 2" href="([^"]+)"/', $prima, $collegamento );
		$this->assertNotEmpty( $collegamento );

		$seconda = (string) $this->pagina_disegnata( html_entity_decode( $collegamento[1] ) );

		$this->assertSame( array_reverse( $attesi ), array_merge( $this->atti_elencati( $prima ), $this->atti_elencati( $seconda ) ), 'Le due pagine danno tutti e soli gli atti della ricerca.' );

		$fuori = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_PAGINA => 'x' ) ) );
		$this->assertSame( array(), $this->atti_elencati( $fuori ), 'Una pagina scritta male non mostra l\'elenco.' );
		$this->assertStringContainsString( 'La pagina chiesta non è valida.', $fuori );
	}

	/**
	 * A-200: il codice breve non disegna niente fuori dalla pagina che lo contiene.
	 */
	public function test_a200_codice_breve_fuori_dalla_sua_pagina(): void {
		$this->pagina_albo();

		$this->pubblicato_il( '2041-10-01 09:30:00', 'Oggetto da non vedere fuori' );
		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		$this->assertStringContainsString( 'Oggetto da non vedere fuori', (string) $this->pagina_disegnata( $this->indirizzo() ), 'Precondizione: sulla sua pagina l\'atto si vede.' );

		// Un articolo con il codice breve, mostrato nell'elenco degli articoli.
		$articolo = self::factory()->post->create(
			array(
				'post_title'   => 'Articolo con il codice',
				'post_content' => '[albo_pretorio]',
			)
		);
		PaginaPubblica::azzera();

		$this->richiesta_pubblica( home_url( '/' ) );
		$this->assertTrue( is_home(), 'Precondizione: l\'elenco degli articoli si apre.' );
		$GLOBALS['post'] = get_post( $articolo );
		setup_postdata( $GLOBALS['post'] );
		$this->assertSame( '', trim( (string) apply_filters( 'the_content', '[albo_pretorio]' ) ), 'Nell\'elenco degli articoli non disegna niente.' );

		// Lo stesso articolo aperto come pagina singola e' una pagina dell'albo: lo si dichiara.
		$this->assertContains( $articolo, PaginaPubblica::pagine(), 'Un articolo pubblicato con il codice breve e\' una pagina dell\'albo.' );

		// Sulla pagina dell'albo, ma dentro un altro contenuto (un riquadro che mostra un altro articolo).
		$this->richiesta_pubblica( $this->indirizzo() );
		$ordinario       = self::factory()->post->create( array( 'post_title' => 'Articolo ordinario' ) );
		$GLOBALS['post'] = get_post( $ordinario );
		setup_postdata( $GLOBALS['post'] );
		$this->assertSame( '', do_shortcode( '[albo_pretorio]' ), 'Dentro un altro contenuto non disegna niente.' );

		// Nel feed, nell'anteprima incorporata e nell'interfaccia per programmi.
		$this->richiesta_pubblica( home_url( '/?feed=rss2' ) );
		$this->assertTrue( is_feed(), 'Precondizione: il feed si apre.' );
		$this->assertSame( '', do_shortcode( '[albo_pretorio]' ), 'Nel feed non disegna niente.' );

		$this->richiesta_pubblica( $this->indirizzo( array( 'embed' => 'true' ) ) );
		$this->assertTrue( is_embed(), 'Precondizione: l\'anteprima incorporata si apre.' );
		$GLOBALS['post'] = get_post( $this->pagina );
		setup_postdata( $GLOBALS['post'] );
		$this->assertSame( '', do_shortcode( '[albo_pretorio]' ), 'Nell\'anteprima incorporata non disegna niente.' );

		$GLOBALS['wp_rest_server'] = null;
		$risposta                  = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/pages/' . $this->pagina ) );
		$this->assertSame( 200, $risposta->get_status(), 'Precondizione: l\'interfaccia per programmi risponde.' );
		$this->assertStringNotContainsString( 'Oggetto da non vedere fuori', (string) wp_json_encode( $risposta->get_data() ), 'Nell\'interfaccia per programmi non disegna niente.' );
		$GLOBALS['wp_rest_server'] = null;

		// Pagine che non sono pagine dell'albo: in bozza e con password.
		$bozza  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '[albo_pretorio]',
			)
		);
		$chiusa = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_password' => 'segreta',
				'post_content'  => '[albo_pretorio]',
			)
		);
		PaginaPubblica::azzera();

		$this->assertNotContains( $bozza, PaginaPubblica::pagine(), 'Una bozza non e\' una pagina dell\'albo.' );
		$this->assertNotContains( $chiusa, PaginaPubblica::pagine(), 'Una pagina con password non e\' una pagina dell\'albo.' );
	}

	/**
	 * A-201: il tipo e' consultabile solo con la pagina, e i suoi indirizzi restano chiusi.
	 */
	public function test_a201_tipo_consultabile_solo_con_la_pagina(): void {
		$atto      = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto per la consultabilita\'' );
		$documento = (int) DocumentiAtto::principale( $atto );

		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		$this->assertFalse( is_post_type_viewable( TIPO ), 'Senza pagina il tipo non e\' consultabile.' );
		$this->assertFalse( $this->consegnato( $this->consegna( $documento ) ), 'Senza pagina il documento non si scarica.' );
		$this->assertStringNotContainsString( PaginaPubblica::PARAMETRO_ATTO, (string) get_permalink( $atto ), 'Senza pagina l\'indirizzo dell\'atto non e\' una scheda.' );

		$this->pagina_albo();

		$this->assertTrue( is_post_type_viewable( TIPO ), 'Con la pagina il tipo e\' consultabile.' );
		$this->assertTrue( is_post_type_viewable( 'post' ), 'Gli altri tipi non cambiano.' );
		$this->assertTrue( $this->consegnato( $this->consegna( $documento ) ), 'Con la pagina il documento si scarica.' );
		$this->assertSame( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atto ) ), get_permalink( $atto ), 'L\'indirizzo dell\'atto e\' la sua scheda.' );

		// Gli indirizzi di WordPress del tipo restano chiusi.
		$this->richiesta_pubblica(
			add_query_arg(
				array(
					'post_type' => TIPO,
					'p'         => $atto,
				),
				home_url( '/' )
			)
		);
		$this->assertFalse( is_singular( TIPO ), 'L\'indirizzo di WordPress dell\'atto non apre l\'atto.' );
		$this->assertNotContains( $atto, wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' ), 'E non lo porta fra i risultati.' );

		$this->richiesta_pubblica( home_url( '/?s=consultabilita' ) );
		$this->assertNotContains( $atto, wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' ), 'La ricerca del sito non trova l\'atto.' );

		// Nessuna anteprima incorporata dell'atto.
		$GLOBALS['wp_rest_server'] = null;
		$richiesta                 = new \WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$richiesta->set_param( 'url', add_query_arg( 'p', $atto, home_url( '/' ) ) );
		$risposta = rest_do_request( $richiesta );
		$this->assertSame( 404, $risposta->get_status(), 'L\'anteprima incorporata dell\'atto non risponde.' );
		$this->assertSame( 0, apply_filters( 'oembed_request_post_id', $atto, home_url( '/' ) ), 'L\'albo toglie l\'atto dall\'anteprima: le versioni di WordPress senza il tipo "incorporabile" lo incorporerebbero.' );
		$this->assertStringNotContainsString( 'consultabilita', (string) wp_json_encode( $risposta->get_data() ) );

		$controllo = new \WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$controllo->set_param( 'url', (string) get_permalink( $this->pagina ) );
		$this->assertSame( 200, rest_do_request( $controllo )->get_status(), 'Controllo positivo: l\'anteprima di una pagina risponde.' );
		$GLOBALS['wp_rest_server'] = null;

		// Tolta la pagina, il tipo torna come prima.
		wp_trash_post( $this->pagina );

		$this->assertFalse( is_post_type_viewable( TIPO ), 'Tolta la pagina il tipo non e\' piu\' consultabile.' );
		$this->assertFalse( $this->consegnato( $this->consegna( $documento ) ), 'Tolta la pagina il documento non si scarica.' );
	}

	/**
	 * A-202: divieto ai motori e alle memorie su elenco, scheda e ricerca; pagina fuori dalla mappa.
	 */
	public function test_a202_motori_e_memorie(): void {
		$ordinaria = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Pagina ordinaria',
			)
		);
		$this->pagina_albo();

		$atto = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto per i motori' );
		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$controllo = $this->risposta( (string) get_permalink( $ordinaria ) );
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $controllo['intestazioni'], 'Controllo positivo: una pagina ordinaria non porta il divieto.' );
		$this->assertStringNotContainsString( 'noindex', $controllo['robot'], 'Controllo positivo: nel sorgente di una pagina ordinaria non c\'e\' il divieto.' );

		foreach ( array(
			'elenco'  => $this->indirizzo(),
			'scheda'  => $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atto ) ),
			'ricerca' => $this->indirizzo( array( VistaPubblica::CAMPO_TESTO => 'motori' ) ),
		) as $quale => $dove ) {
			$risposta = $this->risposta( $dove );

			$this->assertFalse( $risposta['non trovata'], $quale . ': precondizione, la pagina si apre.' );
			$this->assertSame( 'noindex', $risposta['intestazioni']['X-Robots-Tag'] ?? '', $quale . ': il divieto nell\'intestazione.' );
			$this->assertStringContainsString( 'noindex', $risposta['robot'], $quale . ': il divieto nel sorgente.' );

			foreach ( wp_get_nocache_headers() as $nome => $valore ) {
				$this->assertSame( $valore, $risposta['intestazioni'][ $nome ] ?? null, $quale . ': ' . $nome . ' vieta di conservare la pagina.' );
			}
		}

		// Un divieto gia' scritto da altri, rivolto a tutti, resta com'e'; una direttiva diversa riceve il divieto in coda.
		$this->richiesta_pubblica( $this->indirizzo() );
		$this->assertSame( 'noindex, nofollow', PaginaPubblica::da_wp_headers( array( 'x-robots-tag' => 'noindex, nofollow' ) )['X-Robots-Tag'] );
		$this->assertSame( 'noarchive, noindex', PaginaPubblica::da_wp_headers( array( 'X-Robots-Tag' => 'noarchive' ) )['X-Robots-Tag'] );

		$mappa  = wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_url_list( 1, 'page' );
		$luoghi = wp_list_pluck( $mappa, 'loc' );

		$this->assertContains( (string) get_permalink( $ordinaria ), $luoghi, 'Controllo positivo: la pagina ordinaria e\' nella mappa.' );
		$this->assertNotContains( (string) get_permalink( $this->pagina ), $luoghi, 'La pagina dell\'albo non e\' nella mappa.' );
	}

	/**
	 * A-203: il codice prodotto e' accessibile con i soli mezzi dell'albo.
	 */
	public function test_a203_codice_prodotto_accessibile(): void {
		$this->pagina_albo();

		$atti = array();

		for ( $i = 0; $i < 21; $i++ ) {
			$atti[] = $this->pubblicato_il( sprintf( '2041-10-01 09:%02d:00', $i ), 'Atto accessibile ' . $i, 'normativo', 0 === $i ? array( 'allegato.pdf' ) : array() );
		}

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$stile = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/albo-pretorio-pubblico.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lettura del foglio di stile del componente.

		$this->assertMatchesRegularExpression( '/\.albo-pretorio a:focus-visible,[^{]*\{[^}]*outline: 3px solid/s', $stile, 'L\'indicatore del fuoco lo disegna l\'albo.' );

		foreach ( array(
			'elenco'             => $this->indirizzo(),
			'seconda pagina'     => $this->indirizzo( array( VistaPubblica::CAMPO_PAGINA => 2 ) ),
			'ricerca con filtro' => $this->indirizzo( array( VistaPubblica::CAMPO_TIPO => $this->voci['normativo'] ) ),
			'ricerca vuota'      => $this->indirizzo( array( VistaPubblica::CAMPO_TESTO => 'nessuna parola cosi' ) ),
			'ricerca sbagliata'  => $this->indirizzo( array( VistaPubblica::CAMPO_DAL => 'ieri' ) ),
			'scheda'             => $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atti[0] ) ),
		) as $quale => $dove ) {
			$html = (string) $this->pagina_disegnata( $dove );
			$this->assertStringContainsString( 'class="albo-pretorio"', $html, $quale . ': precondizione, la pagina e\' disegnata.' );

			$this->controlla_accessibilita( $quale, $html, $stile );
		}
	}

	/**
	 * Le regole di A-203 su un codice disegnato.
	 *
	 * @param string $quale Pagina.
	 * @param string $html  Codice.
	 * @param string $stile Foglio di stile.
	 */
	private function controlla_accessibilita( string $quale, string $html, string $stile ): void {
		$documento = new \DOMDocument();
		$vecchio   = libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $vecchio );

		$xpath  = new \DOMXPath( $documento );
		$radici = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " albo-pretorio ")]' );
		$this->assertSame( 1, $radici->length, $quale . ': un solo contenitore dell\'albo.' );
		$radice = $radici->item( 0 );

		// Gli identificativi non si ripetono.
		$identificativi = array();
		foreach ( $xpath->query( './/*[@id]', $radice ) as $nodo ) {
			$identificativi[] = $nodo->getAttribute( 'id' );
		}
		$this->assertSame( array_unique( $identificativi ), $identificativi, $quale . ': identificativi unici.' );

		// Ogni campo ha un'etichetta visibile collegata.
		foreach ( $xpath->query( './/input[not(@type="hidden")] | .//select | .//textarea', $radice ) as $campo ) {
			$id = $campo->getAttribute( 'id' );
			$this->assertNotSame( '', $id, $quale . ': ogni campo ha un identificativo.' );
			$etichette = $xpath->query( './/label[@for="' . $id . '"]', $radice );
			$this->assertSame( 1, $etichette->length, $quale . ': il campo ' . $id . ' ha la sua etichetta.' );
			$this->assertNotSame( '', $this->testo( $etichette->item( 0 ) ), $quale . ': l\'etichetta di ' . $id . ' ha un testo.' );
		}

		// Ogni riferimento di descrizione porta a un elemento che c'e'.
		foreach ( $xpath->query( './/*[@aria-describedby]', $radice ) as $nodo ) {
			foreach ( preg_split( '/\s+/', trim( $nodo->getAttribute( 'aria-describedby' ) ) ) as $riferimento ) {
				$this->assertContains( $riferimento, $identificativi, $quale . ': la descrizione ' . $riferimento . ' esiste.' );
			}
		}

		// I titoli partono dal secondo livello e non saltano.
		$livelli = array();
		foreach ( $xpath->query( './/h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $radice ) as $titolo ) {
			$livelli[] = (int) substr( $this->elemento( $titolo ), 1 );
			$this->assertNotSame( '', $this->testo( $titolo ), $quale . ': ogni titolo ha un testo.' );
		}
		$this->assertNotEmpty( $livelli, $quale );
		$this->assertSame( 2, $livelli[0], $quale . ': il primo titolo dell\'albo e\' di secondo livello.' );
		$precedente = 2;
		foreach ( $livelli as $livello ) {
			$this->assertLessThanOrEqual( $precedente + 1, $livello, $quale . ': i titoli non saltano livelli.' );
			$precedente = $livello;
		}

		// I collegamenti hanno un nome, e quelli con lo stesso nome vanno nello stesso posto.
		$destinazioni = array();
		foreach ( $xpath->query( './/a', $radice ) as $collegamento ) {
			$nome = trim( '' !== $collegamento->getAttribute( 'aria-label' ) ? $collegamento->getAttribute( 'aria-label' ) : $this->testo( $collegamento ) );
			$this->assertNotSame( '', $nome, $quale . ': ogni collegamento ha un nome.' );
			$this->assertNotSame( '', $collegamento->getAttribute( 'href' ), $quale . ': ogni collegamento porta da qualche parte.' );
			$this->assertNotSame( '_blank', $collegamento->getAttribute( 'target' ), $quale . ': nessuna finestra nuova.' );
			$destinazioni[ $nome ][] = $collegamento->getAttribute( 'href' );
		}
		foreach ( $destinazioni as $nome => $indirizzi ) {
			$this->assertCount( 1, array_unique( $indirizzi ), $quale . ': il nome "' . $nome . '" porta in un posto solo.' );
		}

		// Niente ordine di tabulazione forzato, niente stili in linea, niente testo nascosto preso dal tema.
		$this->assertSame( 0, $xpath->query( './/*[@tabindex]', $radice )->length, $quale . ': nessun ordine di tabulazione forzato.' );
		$this->assertSame( 0, $xpath->query( './/*[@style]', $radice )->length, $quale . ': nessuno stile in linea.' );

		// Ogni classe usata e' definita nel foglio di stile dell'albo.
		foreach ( $xpath->query( './/*[@class] | .', $radice ) as $nodo ) {
			foreach ( preg_split( '/\s+/', trim( (string) $nodo->getAttribute( 'class' ) ) ) as $classe ) {
				if ( '' === $classe ) {
					continue;
				}

				$this->assertStringStartsWith( 'albo-pretorio', $classe, $quale . ': la classe ' . $classe . ' e\' dell\'albo.' );
				$this->assertMatchesRegularExpression( '/\.' . preg_quote( $classe, '/' ) . '(?![\w-])/', $stile, $quale . ': la classe ' . $classe . ' e\' definita nel foglio di stile.' );
			}
		}

		// Un testo nascosto ai lettori di schermo non lascia vuoto il suo contenitore.
		foreach ( $xpath->query( './/*[@aria-hidden="true"]', $radice ) as $nascosto ) {
			$contenitore = $nascosto->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di DOMNode.
			$rimasto     = str_replace( $this->testo( $nascosto ), '', $this->testo( $contenitore ) );
			$this->assertNotSame( '', trim( $rimasto ), $quale . ': accanto al testo nascosto "' . $this->testo( $nascosto ) . '" resta un testo per i lettori di schermo.' );
		}

		// La pagina corrente dell'elenco e' dichiarata, al massimo una volta.
		$this->assertLessThanOrEqual( 1, $xpath->query( './/*[@aria-current]', $radice )->length, $quale );

		// Il modulo di ricerca, dove c'e', e' dichiarato come tale e ha un pulsante con un testo.
		foreach ( $xpath->query( './/form', $radice ) as $modulo ) {
			$this->assertSame( 'search', $modulo->getAttribute( 'role' ), $quale . ': il modulo e\' una ricerca.' );
			$this->assertSame( 'get', $modulo->getAttribute( 'method' ), $quale . ': la ricerca e\' un indirizzo.' );
			$this->assertSame( 1, $xpath->query( './/button[@type="submit" and normalize-space(.) != ""]', $modulo )->length, $quale . ': un pulsante per cercare.' );
		}
	}

	/**
	 * A-204: la tabella degli atti resta una tabella per i lettori di schermo, su ogni schermo.
	 */
	public function test_a204_tabella_degli_atti(): void {
		$this->pagina_albo();

		$con_allegati = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto con due allegati', 'normativo', array( 'primo.pdf', 'secondo.pdf' ) );
		$solo         = $this->pubblicato_il( '2041-10-02 09:30:00', 'Atto con il solo documento' );

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$html  = (string) $this->pagina_disegnata( $this->indirizzo() );
		$xpath = $this->leggi( $html );
		$stile = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/albo-pretorio-pubblico.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lettura del foglio di stile del componente.

		$tabelle = $xpath->query( '//table' );
		$this->assertSame( 1, $tabelle->length, 'Una tabella sola.' );
		$tabella = $tabelle->item( 0 );

		$this->assertSame( 'table', $tabella->getAttribute( 'role' ), 'La tabella dichiara il suo ruolo.' );
		$this->assertSame( 'albo-pretorio-titolo-elenco', $tabella->getAttribute( 'aria-labelledby' ), 'La tabella prende il nome dal titolo dell\'elenco.' );

		$colonne = array( 'Numero', 'Tipo', 'Oggetto', 'Pubblicato dal', 'Fino al', 'Documenti' );
		$testata = array();

		foreach ( $xpath->query( './thead/tr/th', $tabella ) as $intestazione ) {
			$this->assertSame( 'col', $intestazione->getAttribute( 'scope' ), 'Intestazione di colonna.' );
			$this->assertSame( 'columnheader', $intestazione->getAttribute( 'role' ), 'L\'intestazione di colonna dichiara il suo ruolo.' );
			$testata[] = $this->testo( $intestazione );
		}

		$this->assertSame( $colonne, $testata, 'Sei colonne, nell\'ordine.' );

		foreach ( array( './thead', './tbody' ) as $gruppo ) {
			$this->assertSame( 'rowgroup', $xpath->query( $gruppo, $tabella )->item( 0 )->getAttribute( 'role' ), $gruppo . ': il gruppo di righe dichiara il suo ruolo.' );
		}

		$this->assertSame( 'row', $xpath->query( './thead/tr', $tabella )->item( 0 )->getAttribute( 'role' ), 'La riga delle intestazioni dichiara il suo ruolo.' );

		$righe = $xpath->query( './tbody/tr', $tabella );
		$this->assertSame( 2, $righe->length, 'Una riga per atto.' );

		foreach ( $righe as $riga ) {
			$this->assertSame( 'row', $riga->getAttribute( 'role' ), 'La riga dichiara il suo ruolo.' );

			$celle = $xpath->query( './th | ./td', $riga );
			$this->assertSame( 6, $celle->length, 'Sei celle per riga.' );

			foreach ( $celle as $posizione => $cella ) {
				if ( 2 === $posizione ) {
					$this->assertSame( 'th', $this->elemento( $cella ), 'L\'oggetto e\' l\'intestazione della riga.' );
					$this->assertSame( 'row', $cella->getAttribute( 'scope' ), 'L\'oggetto intesta la riga.' );
					$this->assertSame( 'rowheader', $cella->getAttribute( 'role' ), 'L\'intestazione di riga dichiara il suo ruolo.' );
					$this->assertSame( 1, $xpath->query( './a[contains(@href, "albo_atto=")]', $cella )->length, 'L\'oggetto apre la scheda.' );
					continue;
				}

				$this->assertSame( 'td', $this->elemento( $cella ), 'Il dato e\' una cella.' );
				$this->assertSame( 'cell', $cella->getAttribute( 'role' ), 'La cella dichiara il suo ruolo.' );

				$nomi = $xpath->query( './*[contains(concat(" ", normalize-space(@class), " "), " albo-pretorio__etichetta ")]', $cella );
				$this->assertSame( 1, $nomi->length, $colonne[ $posizione ] . ': il nome della colonna davanti al dato.' );
				$this->assertSame( $colonne[ $posizione ], $this->testo( $nomi->item( 0 ) ), 'Il nome davanti al dato e\' quello dell\'intestazione.' );
				$this->assertSame( 'true', $nomi->item( 0 )->getAttribute( 'aria-hidden' ), $colonne[ $posizione ] . ': il nome davanti al dato e\' nascosto ai lettori di schermo.' );
			}
		}

		$this->assertSame( '3 documenti', $this->celle( $html, $con_allegati )['Documenti'], 'Documento principale e due allegati.' );
		$this->assertSame( '1 documento', $this->celle( $html, $solo )['Documenti'], 'Il solo documento principale.' );

		$this->assertMatchesRegularExpression( '/\.albo-pretorio__etichetta\s*\{[^}]*display:\s*none/s', $stile, 'Su schermo largo il nome davanti al dato non si vede.' );
		$this->assertMatchesRegularExpression( '/@media[^{]*max-width:\s*40em[^{]*\{.*\.albo-pretorio__etichetta\s*\{[^}]*display:\s*block/s', $stile, 'Su schermo stretto il nome davanti al dato si vede.' );
		$this->assertMatchesRegularExpression( '/@media[^{]*max-width:\s*40em[^{]*\{.*\.albo-pretorio__tabella tr\s*\{[^}]*display:\s*block/s', $stile, 'Su schermo stretto ogni riga diventa un riquadro.' );
	}

	/**
	 * A-205: la funzione per il tema dice la pagina dell'albo e l'atto in vista, solo se in pubblicazione.
	 */
	public function test_a205_funzione_per_il_tema(): void {
		$this->assertTrue( function_exists( 'albo_pretorio_vista_corrente' ), 'Precondizione: la funzione esiste.' );

		$articolo = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Articolo ordinario',
				'post_content' => 'Si parla di albo_pretorio senza il codice breve.',
			)
		);
		$parola   = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Pagina che nomina il codice',
				'post_content' => 'Il codice breve si chiama albo_pretorio.',
			)
		);

		$this->go_to( (string) get_permalink( $articolo ) );
		$this->assertNull( albo_pretorio_vista_corrente(), 'Senza pagina dell\'albo: nullo.' );

		$this->pagina_albo();

		$atto     = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto con un oggetto da non ripetere' );
		$bozza    = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$verif    = $this->atto_in_verifica( 'normativo', array( 'post_title' => 'Atto in verifica di prova' ) );
		$scav     = $this->scavalcato();
		$numero   = Repertorio::di( $atto );
		$elenco   = (string) get_permalink( $this->pagina );
		$scheda   = $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $atto ) );
		$in_vista = array(
			'elenco' => $elenco,
			'atto'   => array(
				'numero' => $numero['numero'] . '/2041',
				'tipo'   => 'Deliberazione',
			),
		);

		$this->fissa_orologi( self::ULTIMO_ISTANTE );

		foreach ( array( $articolo, $parola ) as $altrove ) {
			$this->richiesta_pubblica( (string) get_permalink( $altrove ) );
			$this->assertNull( albo_pretorio_vista_corrente(), 'Fuori dalla pagina dell\'albo: nullo (' . $altrove . ').' );
		}

		$this->richiesta_pubblica( $elenco );
		$this->assertSame(
			array(
				'elenco' => $elenco,
				'atto'   => null,
			),
			albo_pretorio_vista_corrente(),
			'Sull\'elenco: l\'indirizzo e nessun atto.'
		);

		$this->richiesta_pubblica( $scheda );
		$this->assertSame( $in_vista, albo_pretorio_vista_corrente(), 'Ultimo secondo: la scheda in corso ha numero e tipo.' );
		$this->assertStringNotContainsString( 'oggetto da non ripetere', (string) wp_json_encode( albo_pretorio_vista_corrente() ), 'L\'oggetto non esce.' );

		$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );

		$casi = array(
			'scaduto'      => $atto,
			'bozza'        => $bozza,
			'in verifica'  => $verif,
			'senza numero' => $scav,
			'mai dato'     => 999999999,
		);

		foreach ( $casi as $caso => $chiesto ) {
			$this->richiesta_pubblica( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $chiesto ) ) );
			$this->assertTrue( is_404(), $caso . ': precondizione, la scheda risponde "non trovata".' );
			$this->assertNull( albo_pretorio_vista_corrente(), $caso . ': nullo, come ogni pagina "non trovata".' );
		}

		// Anche se un altro componente togliesse la risposta "non trovata", il numero non esce.
		remove_filter( 'pre_handle_404', array( PaginaPubblica::class, 'da_pre_handle_404' ), 10 );

		foreach ( $casi as $caso => $chiesto ) {
			$this->richiesta_pubblica( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $chiesto ) ) );
			$this->assertFalse( is_404(), $caso . ': precondizione, la pagina si apre senza la risposta "non trovata".' );
			$this->assertSame(
				array(
					'elenco' => $elenco,
					'atto'   => null,
				),
				albo_pretorio_vista_corrente(),
				$caso . ': senza la risposta "non trovata", nessun atto.'
			);
		}

		add_filter( 'pre_handle_404', array( PaginaPubblica::class, 'da_pre_handle_404' ), 10, 2 );
	}

	/**
	 * Le direttive del metatag `robots` che WordPress stamperebbe nella pagina in corso.
	 *
	 * @return string
	 */
	private function robot_stampati(): string {
		ob_start();
		wp_robots();

		return (string) ob_get_clean();
	}

	/**
	 * Il testo di un nodo.
	 *
	 * @param \DOMNode $nodo Nodo.
	 * @return string
	 */
	private function testo( \DOMNode $nodo ): string {
		return trim( (string) $nodo->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di DOMNode.
	}

	/**
	 * Il nome dell'elemento di un nodo.
	 *
	 * @param \DOMNode $nodo Nodo.
	 * @return string
	 */
	private function elemento( \DOMNode $nodo ): string {
		return (string) $nodo->nodeName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di DOMNode.
	}
}
