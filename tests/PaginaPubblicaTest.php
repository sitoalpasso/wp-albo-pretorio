<?php
/**
 * La pagina pubblica dell'albo: righe A-195..A-209 (ALBO-06, ALBO-16, ALBO-28).
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
	 * Le righe di intestazione che la pagina dell'albo manda per conto suo.
	 *
	 * @var array<int, string>
	 */
	private array $righe = array();

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

		PaginaPubblica::fissa_emettitore(
			function ( string $riga ) {
				$this->righe[] = $riga;
			}
		);

		add_filter( 'status_header', array( $this, 'osserva_stato' ), 10, 2 );
		add_action( 'parse_request', array( $this, 'dimentica_righe' ), 1 );
	}

	/**
	 * Le righe contano per l'ultima richiesta soltanto.
	 */
	public function dimentica_righe(): void {
		$this->righe = array();
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		remove_filter( 'status_header', array( $this, 'osserva_stato' ), 10 );
		remove_action( 'parse_request', array( $this, 'dimentica_righe' ), 1 );
		\Conformita_Core_Consegna::azzera_emettitore();
		PaginaPubblica::fissa_emettitore( null );
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

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

		$this->assertSame( array( $this->pagina ), PaginaPubblica::pagine(), 'Precondizione: la pagina dell\'albo e\' riconosciuta.' );

		return $this->pagina;
	}

	/**
	 * Un atto pubblicato con il passaggio vero all'istante indicato.
	 *
	 * @param string               $istante Data e ora nel fuso del sito.
	 * @param string               $oggetto Oggetto.
	 * @param string               $tipo    Nome breve del tipo.
	 * @param array<int, string>   $allegati Nomi dei file degli allegati ulteriori.
	 * @param int|null             $organo  Organo al posto di quello comune.
	 * @param array<string, mixed> $campi Altri campi della riga, come testo ed estratto.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto, string $tipo = 'normativo', array $allegati = array(), ?int $organo = null, array $campi = array() ): int {
		$this->fissa_orologi( $istante );

		$id = $this->atto_completo( $tipo, array( 'post_title' => $oggetto ) + $campi );

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
		preg_match_all( '/<li class="albo-pretorio__atto" data-atto="([0-9]+)"/', $html, $trovati );

		return array_map( 'intval', $trovati[1] );
	}

	/**
	 * Quello che risponde una richiesta, per il confronto con un indirizzo mai esistito.
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return array<string, mixed>
	 */
	private function risposta( string $indirizzo ): array {
		$this->stati = array();
		$this->righe = array();
		$this->richiesta_pubblica( $indirizzo );

		return array(
			'non trovata'  => is_404(),
			'stati'        => array_values( array_unique( $this->stati ) ),
			'righe'        => $this->righe,
			'risultati'    => wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' ),
			'titolo'       => wp_get_document_title(),
			'corpo'        => $this->corpo_del_modello(),
			'intestazioni' => apply_filters( 'wp_headers', array(), $GLOBALS['wp'] ),
			'robot'        => $this->robot_stampati(),
		);
	}

	/**
	 * Quello che disegna un modello che percorre i risultati, come fanno molti modelli 404.
	 *
	 * @return string
	 */
	private function corpo_del_modello(): string {
		ob_start();

		while ( have_posts() ) {
			the_post();
			echo esc_html( get_the_title() );
			the_content();
		}

		wp_reset_postdata();

		return trim( (string) ob_get_clean() );
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
	 * Come `consegna()`, con l'identificativo dell'atto seguito da lettere.
	 *
	 * @param int $documento Documento.
	 * @return array<int, array<string, mixed>>
	 */
	private function consegna_storpiata( int $documento ): array {
		$indirizzo = (string) conformita_core_indirizzo_consegna( $documento );
		$atto      = (int) get_post( $documento )->post_parent;

		$this->consegne = array();
		$this->go_to( add_query_arg( \Conformita_Core_Consegna::VAR_ATTO, $atto . 'x', $indirizzo ) );

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
			$this->assertStringContainsString( '<dt>Numero di registro</dt><dd>' . $numero['numero'] . '/2041</dd>', $html, $chi );
			$this->assertStringContainsString( '<dt>Tipo</dt><dd>Deliberazione</dd>', $html, $chi );
			$this->assertStringContainsString( '<dt>Organo</dt><dd>Giunta</dd>', $html, $chi );
			$this->assertStringContainsString( '<dt>In pubblicazione</dt><dd>dal 01/10/2041 al 16/10/2041</dd>', $html, $chi );
			$this->assertStringContainsString( '<dt>In pubblicazione</dt><dd>dal 03/10/2041 al 13/10/2041</dd>', $html, $chi );
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
		$this->assertStringContainsString( '<dt>In pubblicazione</dt><dd>dal 01/10/2041 al 16/10/2041</dd>', $html );
		$this->assertStringContainsString( 'Torna all&#039;elenco degli atti', $html );
		$this->assertStringNotContainsString( 'Mario-Rossi', $html, 'Il nome del file caricato non compare.' );
		$this->assertStringNotContainsString( 'albo-pretorio__elenco', $html, 'La scheda non porta l\'elenco.' );
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
			$this->assertSame( array(), $mai['risultati'], $chi . ': la "non trovata" non ha risultati.' );
			$this->assertSame( '', $mai['corpo'], $chi . ': un modello che percorre i risultati non disegna niente.' );

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

		// La parola dell'oggetto, ma solo nel testo o solo nell'estratto: la ricerca e' nell'oggetto.
		$nel_testo     = $this->pubblicato_il( '2041-10-04 09:00:00', 'Ordinanza di prova', 'normativo', array(), null, array( 'post_content' => 'Interventi sulla viabilita\' del quartiere.' ) );
		$nell_estratto = $this->pubblicato_il( '2041-10-04 10:00:00', 'Determinazione di prova', 'normativo', array(), null, array( 'post_excerpt' => 'Sulla viabilita\' del quartiere.' ) );

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$numero_secondo = Repertorio::di( $secondo );
		$numero_vecchio = Repertorio::di( $vecchio );
		$this->assertIsArray( $numero_secondo );
		$this->assertIsArray( $numero_vecchio );

		$this->assertSame( array( $nell_estratto, $nel_testo, $secondo, $primo ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo() ) ), 'Precondizione: senza ricerca ci sono tutti e quattro.' );
		$this->assertStringContainsString( 'viabilita', get_post( $nel_testo )->post_content, 'Precondizione: la parola e\' nel testo.' );
		$this->assertStringContainsString( 'viabilita', get_post( $nell_estratto )->post_excerpt, 'Precondizione: la parola e\' nell\'estratto.' );

		foreach ( array(
			'parola dell\'oggetto'       => array( array( VistaPubblica::CAMPO_TESTO => 'viabilita\'' ), array( $primo ) ),
			'parola anche dello scaduto' => array( array( VistaPubblica::CAMPO_TESTO => 'Deliberazione' ), array( $primo ) ),
			'numero di registro'         => array( array( VistaPubblica::CAMPO_TESTO => $numero_secondo['numero'] . '/2041' ), array( $secondo ) ),
			'numero con spazi'           => array( array( VistaPubblica::CAMPO_TESTO => $numero_secondo['numero'] . ' / 2041' ), array( $secondo ) ),
			'numero dello scaduto'       => array( array( VistaPubblica::CAMPO_TESTO => $numero_vecchio['numero'] . '/2041' ), array() ),
			'numero mai dato'            => array( array( VistaPubblica::CAMPO_TESTO => '9999/2041' ), array() ),
			'tipo'                       => array( array( VistaPubblica::CAMPO_TIPO => $this->voci['scelto'] ), array( $secondo ) ),
			'organo'                     => array( array( VistaPubblica::CAMPO_ORGANO => $consiglio ), array( $secondo ) ),
			'dal'                        => array( array( VistaPubblica::CAMPO_DAL => '2041-10-02' ), array( $nell_estratto, $nel_testo, $secondo ) ),
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
			// Valori che ripuliti diventerebbero vuoti, o validi: non sono un campo vuoto.
			'tipo di soli segni'     => array( VistaPubblica::CAMPO_TIPO, '<b></b>' ),
			'tipo con un segno'      => array( VistaPubblica::CAMPO_TIPO, $this->voci['normativo'] . '<b>' ),
			'organo di soli segni'   => array( VistaPubblica::CAMPO_ORGANO, '<b></b>' ),
			'dal di soli segni'      => array( VistaPubblica::CAMPO_DAL, '<b></b>' ),
			'al di soli segni'       => array( VistaPubblica::CAMPO_AL, '<b></b>' ),
			'oggetto di soli segni'  => array( VistaPubblica::CAMPO_TESTO, '<b></b>' ),
			// Un carattere nullo dentro la data: la pagina non si interrompe.
			'dal con un nullo'       => array( VistaPubblica::CAMPO_DAL, '2041-10%0001' ),
			'al con un nullo'        => array( VistaPubblica::CAMPO_AL, '2041-10%0001' ),
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

		// La pagina non e' un campo del modulo: niente da segnare, ma niente elenco intero.
		$html = (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_PAGINA => '<b></b>' ) ) );
		$this->assertSame( array(), $this->atti_elencati( $html ), 'Pagina di soli segni: nessun atto.' );
		$this->assertStringContainsString( 'La ricerca non è stata fatta.', $html, 'Pagina di soli segni: c\'e\' il messaggio.' );

		$this->assertSame( array( $atto ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_TESTO => 'Avviso' ) ) ) ), 'Controllo positivo: la stessa pagina trova l\'atto con un campo giusto.' );
		$this->assertSame( array( $atto ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_TIPO => '' ) ) ) ), 'Controllo positivo: un campo davvero vuoto non restringe.' );
		$this->assertSame( array( $atto ), $this->atti_elencati( (string) $this->pagina_disegnata( $this->indirizzo( array( VistaPubblica::CAMPO_DAL => '2041-10-01' ) ) ) ), 'Controllo positivo: una data giusta trova l\'atto.' );

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

		/*
		 * L'interfaccia per programmi, due volte. Prima come in una richiesta
		 * vera, con la richiesta principale che non e' la pagina dell'albo;
		 * poi con la richiesta principale proprio sulla pagina dell'albo, cosi'
		 * che ogni altra condizione per disegnare sia vera e a fermare il
		 * codice breve resti soltanto il controllo sull'interfaccia.
		 */
		foreach ( array(
			'richiesta principale altrove'      => home_url( '/' ),
			'richiesta principale sulla pagina' => $this->indirizzo(),
		) as $come => $dove ) {
			$this->richiesta_pubblica( $dove );
			$GLOBALS['post'] = get_post( $this->pagina );
			setup_postdata( $GLOBALS['post'] );

			if ( $this->indirizzo() === $dove ) {
				$this->assertTrue( PaginaPubblica::richiesta_dell_albo(), $come . ': precondizione, la richiesta principale e\' la pagina dell\'albo.' );
				$this->assertStringContainsString( 'Oggetto da non vedere fuori', do_shortcode( '[albo_pretorio]' ), $come . ': precondizione, fuori dall\'interfaccia disegna.' );
			}

			$GLOBALS['wp_rest_server'] = null;
			$risposta                  = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/pages/' . $this->pagina ) );
			$this->assertSame( 200, $risposta->get_status(), $come . ': precondizione, l\'interfaccia per programmi risponde.' );
			$this->assertStringContainsString( 'Gli atti in pubblicazione.', (string) wp_json_encode( $risposta->get_data() ), $come . ': precondizione, il contenuto della pagina e\' letto.' );
			$this->assertStringNotContainsString( 'Oggetto da non vedere fuori', (string) wp_json_encode( $risposta->get_data() ), $come . ': nell\'interfaccia per programmi non disegna niente.' );
			$GLOBALS['wp_rest_server'] = null;
		}

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
	 * A-204: il documento di un atto che la pagina non mostra non si scarica.
	 *
	 * Il punto di consegna del meccanismo comune guarda pubblicazione,
	 * password e scadenza, ma non il numero di registro, e una password data
	 * gli basta. La pagina non mostra ne' l'uno ne' l'altro atto, quindi
	 * nemmeno i loro documenti escono: il rifiuto e' quello che il punto da'
	 * al documento di uno scaduto.
	 */
	public function test_a204_documento_di_un_atto_che_la_pagina_non_mostra(): void {
		$this->pagina_albo();

		$scaduto   = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto di riferimento' );
		$controllo = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto di controllo' );
		$chiuso    = $this->pubblicato_il( '2041-10-02 09:30:00', 'Atto a cui si mette una password' );
		$senza     = $this->scavalcato();

		$this->scavalca_barriera(
			static function () use ( $chiuso ) {
				global $wpdb;

				return $wpdb->update( $wpdb->posts, array( 'post_password' => 'segreta' ), array( 'ID' => $chiuso ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- scrittura da fuori, come la farebbe un altro programma.
			}
		);
		clean_post_cache( $chiuso );
		$this->assertSame( 'segreta', get_post( $chiuso )->post_password, 'Precondizione: l\'atto ha la password.' );

		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		$this->assertTrue( conformita_core_scaduto( $scaduto ), 'Precondizione: il riferimento e\' scaduto.' );
		$rifiuto = $this->consegna( (int) DocumentiAtto::principale( $scaduto ) );
		$this->assertFalse( $this->consegnato( $rifiuto ), 'Precondizione: il documento dello scaduto non si scarica.' );
		$this->assertSame( 404, $rifiuto[0]['stato'] ?? 0, 'Precondizione: il rifiuto e\' un "non trovato".' );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$biscotto = ( new \PasswordHash( 8, true ) )->HashPassword( 'segreta' );

		foreach ( array(
			'senza numero'                         => array( $senza, null ),
			'con password, senza biscotto'         => array( $chiuso, null ),
			'con password, con il biscotto giusto' => array( $chiuso, $biscotto ),
		) as $quale => list( $atto, $valore ) ) {
			unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

			if ( null !== $valore ) {
				$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = wp_slash( $valore );
				$this->assertFalse( post_password_required( $atto ), $quale . ': precondizione, con il biscotto WordPress considera la password data.' );
			}

			$this->assertTrue( $this->consegnato( $this->consegna( (int) DocumentiAtto::principale( $controllo ) ) ), $quale . ': controllo positivo, prima il documento in corso si scarica.' );
			$this->assertSame( $rifiuto, $this->consegna( (int) DocumentiAtto::principale( $atto ) ), $quale . ': il documento non si scarica, con la risposta dello scaduto.' );
			$this->assertSame( $rifiuto, $this->consegna_storpiata( (int) DocumentiAtto::principale( $atto ) ), $quale . ': nemmeno con l\'atto seguito da lettere.' );
			$this->assertTrue( $this->consegnato( $this->consegna_storpiata( (int) DocumentiAtto::principale( $controllo ) ) ), $quale . ': controllo positivo, con le lettere il punto di consegna legge lo stesso atto.' );
			$this->assertTrue( $this->consegnato( $this->consegna( (int) DocumentiAtto::principale( $controllo ) ) ), $quale . ': controllo positivo, dopo il documento in corso si scarica.' );
		}
	}

	/**
	 * A-205: la "non trovata" di una scheda porta il divieto ai motori e alle memorie.
	 */
	public function test_a205_non_trovata_fuori_dai_motori(): void {
		$this->pagina_albo();

		$scaduto = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto' );
		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		foreach ( array(
			'scaduto'         => $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => $scaduto ) ),
			'numero mai dato' => $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => 999999 ) ),
		) as $quale => $dove ) {
			$risposta = $this->risposta( $dove );

			$this->assertTrue( $risposta['non trovata'], $quale . ': precondizione, la scheda risponde "non trovata".' );
			$this->assertSame( 'noindex', $risposta['intestazioni']['X-Robots-Tag'] ?? '', $quale . ': il divieto nell\'intestazione.' );
			$this->assertSame( array( 'X-Robots-Tag: noindex' ), $risposta['righe'], $quale . ': il divieto su una riga propria.' );
			$this->assertStringContainsString( 'noindex', $risposta['robot'], $quale . ': il divieto nel sorgente.' );

			foreach ( wp_get_nocache_headers() as $nome => $valore ) {
				$this->assertSame( $valore, $risposta['intestazioni'][ $nome ] ?? null, $quale . ': ' . $nome . ' vieta di conservare la risposta.' );
			}
		}

		$fuori = $this->risposta( home_url( '/?p=999999' ) );
		$this->assertTrue( $fuori['non trovata'], 'Precondizione: l\'indirizzo inesistente fuori dalla pagina risponde "non trovata".' );
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $fuori['intestazioni'], 'Fuori dalla pagina la "non trovata" non riceve il divieto dell\'albo.' );
		$this->assertSame( array(), $fuori['righe'], 'Fuori dalla pagina nessuna riga dell\'albo.' );
	}

	/**
	 * A-205 (memorie di pagina): la costante dopo la scheda non trovata, in un avvio separato.
	 *
	 * Una costante definita da un'altra prova non si toglie: qui il processo
	 * e' nuovo, quindi la costante c'e' solo se la definisce questa richiesta.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a205_non_trovata_la_costante(): void {
		$this->pagina_albo();
		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		$this->assertFalse( defined( 'DONOTCACHEPAGE' ), 'Precondizione: nel processo nuovo la costante non c\'e\'.' );

		$this->richiesta_pubblica( $this->indirizzo( array( PaginaPubblica::PARAMETRO_ATTO => 999999 ) ) );

		$this->assertTrue( is_404(), 'Precondizione: la scheda risponde "non trovata".' );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && true === constant( 'DONOTCACHEPAGE' ), 'La scheda non trovata definisce la costante.' );
	}

	/**
	 * A-206: le direttive per un motore specifico non si mescolano al divieto generale.
	 */
	public function test_a206_direttive_per_un_motore(): void {
		$this->pagina_albo();
		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		foreach ( array(
			'per un motore'                     => array( array( 'X-Robots-Tag' => 'googlebot: nofollow' ), 'noindex', array( 'X-Robots-Tag: googlebot: nofollow', 'X-Robots-Tag: noindex' ) ),
			'per un motore, con il suo divieto' => array( array( 'X-Robots-Tag' => 'googlebot: nofollow, noindex' ), 'noindex', array( 'X-Robots-Tag: googlebot: nofollow, noindex', 'X-Robots-Tag: noindex' ) ),
			'generale e per un motore'          => array(
				array(
					'x-robots-tag' => 'googlebot: nofollow',
					'X-Robots-Tag' => 'nofollow',
				),
				'nofollow, noindex',
				array( 'X-Robots-Tag: googlebot: nofollow', 'X-Robots-Tag: noindex' ),
			),
		) as $quale => list( $scritte, $composta, $righe ) ) {
			$altri = static function ( $intestazioni ) use ( $scritte ) {
				return array_merge( (array) $intestazioni, $scritte );
			};
			add_filter( 'wp_headers', $altri );

			$risposta = $this->risposta( $this->indirizzo() );

			remove_filter( 'wp_headers', $altri );

			$this->assertFalse( $risposta['non trovata'], $quale . ': precondizione, la pagina si apre.' );
			$this->assertSame( $composta, $risposta['intestazioni']['X-Robots-Tag'] ?? '', $quale . ': l\'intestazione composta.' );
			$this->assertArrayNotHasKey( 'x-robots-tag', $risposta['intestazioni'], $quale . ': ne resta una sola.' );
			$this->assertSame( $righe, $risposta['righe'], $quale . ': le righe proprie.' );
		}
	}

	/**
	 * A-207: due codici brevi nella stessa pagina, ciascuno con i suoi identificativi.
	 */
	public function test_a207_due_codici_brevi(): void {
		$this->pagina_albo();
		$this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in due moduli' );
		$this->fissa_orologi( '2041-10-05 10:00:00' );
		wp_set_current_user( 0 );

		$dove = $this->indirizzo( array( VistaPubblica::CAMPO_DAL => 'ieri' ) );

		$una = $this->riferimenti( (string) $this->pagina_disegnata( $dove ) );
		$this->assertCount( 1, $una, 'Precondizione: con un codice breve, un modulo.' );
		$this->assertContains( VistaPubblica::ID_ERRORE, $una[0]['id'], 'Precondizione: la ricerca scritta male mostra l\'errore.' );

		wp_update_post(
			array(
				'ID'           => $this->pagina,
				'post_content' => '[albo_pretorio]' . "\n\n" . '[albo_pretorio]',
			)
		);

		$due = $this->riferimenti( (string) $this->pagina_disegnata( $dove ) );
		$this->assertCount( 2, $due, 'Precondizione: con due codici brevi, due moduli.' );

		$tutti = array_merge( $due[0]['id'], $due[1]['id'] );
		$this->assertSame( array_values( array_unique( $tutti ) ), $tutti, 'Sull\'intero risultato gli identificativi sono unici.' );

		foreach ( $due as $n => $modulo ) {
			$this->assertNotEmpty( $modulo['rimandi'], 'Precondizione: il modulo ' . $n . ' ha riferimenti.' );

			foreach ( $modulo['rimandi'] as $rimando ) {
				$this->assertContains( $rimando, $modulo['id'], 'Il riferimento ' . $rimando . ' punta a un elemento dello stesso modulo.' );
			}

			$this->assertSame( $una[0]['nomi'], $modulo['nomi'], 'I nomi dei campi del modulo ' . $n . ' restano gli stessi.' );
		}

		$this->assertSame( $una[0]['id'], $due[0]['id'], 'Il primo modulo ha gli identificativi di sempre.' );
	}

	/**
	 * A-208: una pagina dell'elenco oltre l'ultima non si legge come un albo vuoto.
	 */
	public function test_a208_pagina_oltre_l_ultima(): void {
		$this->pagina_albo();

		$this->pubblicato_il( '2041-09-25 09:00:00', 'Atto che scade prima' );

		for ( $i = 0; $i < 20; $i++ ) {
			$this->pubblicato_il( sprintf( '2041-10-01 09:%02d:00', $i ), 'Atto numero ' . $i );
		}

		wp_set_current_user( 0 );

		$tipo = $this->voci['normativo'];

		foreach ( array(
			'terza pagina'                  => array( '2041-10-05 10:00:00', array( VistaPubblica::CAMPO_PAGINA => 3 ), '21', array() ),
			'terza pagina, con il tipo'     => array(
				'2041-10-05 10:00:00',
				array(
					VistaPubblica::CAMPO_TIPO   => $tipo,
					VistaPubblica::CAMPO_PAGINA => 3,
				),
				'21',
				array( VistaPubblica::CAMPO_TIPO => $tipo ),
			),
			'seconda pagina, dopo una fine' => array( '2041-10-12 10:00:00', array( VistaPubblica::CAMPO_PAGINA => 2 ), '20', array() ),
		) as $quale => list( $istante, $parametri, $quanti, $conservati ) ) {
			$this->fissa_orologi( $istante );

			$html = (string) $this->pagina_disegnata( $this->indirizzo( $parametri ) );

			$this->assertSame( array(), $this->atti_elencati( $html ), $quale . ': precondizione, nella pagina non ci sono atti.' );
			$this->assertStringContainsString( esc_html( 'In questa pagina dell\'elenco non ci sono atti: l\'elenco ne ha ' . $quanti . '.' ), $html, $quale . ': dice quanti atti ha l\'elenco.' );
			$this->assertStringNotContainsString( 'non ci sono atti in pubblicazione', $html, $quale . ': non dice che l\'albo e\' vuoto.' );
			$this->assertStringNotContainsString( 'risponde alla ricerca', $html, $quale . ': non dice che la ricerca non trova niente.' );

			$prima = esc_url( add_query_arg( $conservati, (string) get_permalink( $this->pagina ) ) );
			$this->assertStringContainsString( 'href="' . $prima . '">Vai alla prima pagina</a>', $html, $quale . ': il collegamento alla prima pagina conserva la ricerca.' );
			// Con ventuno atti le pagine sono due e si elencano; con venti ce n'e' una sola, e l'elenco delle pagine non c'e'.
			$this->assertSame( '21' === $quanti, str_contains( $html, 'aria-label="Pagina 2"' ), $quale . ': le pagine che ci sono.' );
		}
	}

	/**
	 * A-209: le pagine dell'albo riconosciute seguono la vita della pagina, da sole.
	 *
	 * Nessun azzeramento a mano: la memoria la svuotano gli agganci di
	 * WordPress, ed e' quello che la riga guarda.
	 */
	public function test_a209_pagine_riconosciute_lungo_la_vita_della_pagina(): void {
		$this->assertSame( array(), PaginaPubblica::pagine(), 'Precondizione: nessuna pagina dell\'albo, e la domanda e\' gia\' stata fatta.' );
		$this->assertFalse( is_post_type_viewable( TIPO ), 'Precondizione: senza pagina il tipo non e\' consultabile.' );

		$pagina = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Albo',
				'post_content' => '[albo_pretorio]',
			)
		);
		$this->assertIsInt( $pagina );

		$passi = array(
			'creata con il codice breve' => array(
				static function () {},
				true,
			),
			'senza il codice breve'      => array(
				static function () use ( $pagina ) {
					wp_update_post(
						array(
							'ID'           => $pagina,
							'post_content' => 'Nessun codice.',
						)
					);
				},
				false,
			),
			'con il codice rimesso'      => array(
				static function () use ( $pagina ) {
					wp_update_post(
						array(
							'ID'           => $pagina,
							'post_content' => '[albo_pretorio]',
						)
					);
				},
				true,
			),
			'con una password'           => array(
				static function () use ( $pagina ) {
					wp_update_post(
						array(
							'ID'            => $pagina,
							'post_password' => 'segreta',
						)
					);
				},
				false,
			),
			'senza la password'          => array(
				static function () use ( $pagina ) {
					wp_update_post(
						array(
							'ID'            => $pagina,
							'post_password' => '',
						)
					);
				},
				true,
			),
			'nel cestino'                => array(
				static function () use ( $pagina ) {
					wp_trash_post( $pagina );
				},
				false,
			),
			'ripristinata'               => array(
				static function () use ( $pagina ) {
					wp_untrash_post( $pagina );
				},
				false,
			),
			'ripubblicata'               => array(
				static function () use ( $pagina ) {
					wp_publish_post( $pagina );
				},
				true,
			),
		);

		foreach ( $passi as $quale => list( $passo, $atteso ) ) {
			$passo();

			if ( 'ripristinata' === $quale ) {
				$this->assertNotSame( 'publish', get_post_status( $pagina ), 'Precondizione: ripristinata, la pagina non e\' pubblicata.' );
			}

			$this->assertSame( $atteso ? array( $pagina ) : array(), PaginaPubblica::pagine(), $quale . ': le pagine dell\'albo.' );
			$this->assertSame( $atteso, is_post_type_viewable( TIPO ), $quale . ': la consultabilita\' del tipo.' );
		}
	}

	/**
	 * Per ogni contenitore dell'albo: identificativi, riferimenti e nomi dei campi.
	 *
	 * @param string $html Codice disegnato.
	 * @return array<int, array{id: array<int, string>, rimandi: array<int, string>, nomi: array<int, string>}>
	 */
	private function riferimenti( string $html ): array {
		$documento = new \DOMDocument();
		$vecchio   = libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $vecchio );

		$xpath  = new \DOMXPath( $documento );
		$moduli = array();

		foreach ( $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " albo-pretorio ")]' ) as $radice ) {
			$voce = array(
				'id'      => array(),
				'rimandi' => array(),
				'nomi'    => array(),
			);

			foreach ( $xpath->query( './/*[@id]', $radice ) as $nodo ) {
				$voce['id'][] = $nodo->getAttribute( 'id' );
			}

			foreach ( array( 'for', 'aria-describedby', 'aria-labelledby' ) as $attributo ) {
				foreach ( $xpath->query( './/*[@' . $attributo . ']', $radice ) as $nodo ) {
					foreach ( preg_split( '/\s+/', trim( $nodo->getAttribute( $attributo ) ), -1, PREG_SPLIT_NO_EMPTY ) as $rimando ) {
						$voce['rimandi'][] = $rimando;
					}
				}
			}

			foreach ( $xpath->query( './/*[@name]', $radice ) as $nodo ) {
				$voce['nomi'][] = $nodo->getAttribute( 'name' );
			}

			$moduli[] = $voce;
		}

		return $moduli;
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
