<?php
/**
 * Gli atti scaduti sono invisibili al pubblico: righe A-175..A-178 (ALBO-05, ALBO-18).
 *
 * **Perche' il tipo si apre nella prova.** Oggi il tipo atto non e' consultabile
 * dal pubblico (A-25), quindi un atto scaduto e' invisibile anche perche' lo e'
 * ogni atto: una prova fatta cosi' sarebbe verde per la ragione sbagliata. Qui
 * il tipo si apre con `register_post_type_args`, come farebbe un tema o un altro
 * componente, ed e' lo scenario che conta davvero: un sito dove qualcuno ha reso
 * pubblici gli atti senza chiedere niente all'albo. Su quel sito un atto in corso
 * si vede su ogni strada, e la prova lo verifica prima; uno scaduto non si vede
 * su nessuna, ne' da un visitatore ne' da un amministratore.
 *
 * **L'orologio, non il compito pianificato.** Il compito che porta un atto a
 * defisso non esiste ancora, e la prova lo usa: lo stato memorizzato resta
 * pubblicato, e a nascondere l'atto e' soltanto la data letta.
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

use const AlboPretorioPa\INDICIZZAZIONE;
use const AlboPretorioPa\SCADENZA;
use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TIPO;

/**
 * Lo scaduto non si vede su nessuna strada, per nessuno.
 */
class ScadutiInvisibiliTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'ultimo istante in cui l'atto di prova e' in pubblicazione.
	 */
	private const ULTIMO_ISTANTE = '2041-10-16 23:59:59';

	/**
	 * Il primo istante in cui l'atto di prova e' scaduto.
	 */
	private const PRIMO_ISTANTE_SCADUTO = '2041-10-17 00:00:00';

	/**
	 * Le risposte del punto pubblico di consegna dei documenti.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $consegne = array();

	/**
	 * Apre il tipo come farebbe un altro componente, poi l'assetto comune.
	 */
	public function set_up(): void {
		parent::set_up();

		add_filter( 'register_post_type_args', array( $this, 'apri_il_tipo' ), 10, 2 );

		$this->prepara_flusso();
		$this->set_permalink_structure( '' );

		// Il server dell'interfaccia per programmi nasce con le rotte dei tipi di quel momento: si rifa' con il tipo aperto.
		$GLOBALS['wp_rest_server'] = null;
		update_option( 'wp_attachment_pages_enabled', '1' );

		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ) {
				$this->consegne[] = $risposta;
			}
		);
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		\Conformita_Core_Consegna::azzera_emettitore();
		remove_filter( 'register_post_type_args', array( $this, 'apri_il_tipo' ), 10 );
		$GLOBALS['wp_rest_server'] = null;

		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Un altro componente rende pubblico il tipo atto, con tutte le strade aperte.
	 *
	 * @param array<string, mixed> $argomenti Argomenti di registrazione.
	 * @param string               $tipo      Tipo che si registra.
	 * @return array<string, mixed>
	 */
	public function apri_il_tipo( $argomenti, $tipo ) {
		if ( TIPO !== $tipo ) {
			return $argomenti;
		}

		return array_merge(
			$argomenti,
			array(
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'has_archive'         => true,
				'query_var'           => TIPO,
				'show_in_rest'        => true,
			)
		);
	}

	/**
	 * Una richiesta pubblica come quella di un sito vero, con le variabili dei tipi aperti.
	 *
	 * `richiesta_pubblica()` riparte da un `WP` nuovo, che non conosce la
	 * variabile che la registrazione del tipo aggiunge a quello del sito: su un
	 * sito vero quell'oggetto nasce prima dei tipi e la riceve. Qui la si
	 * riporta, altrimenti l'indirizzo della scheda non risponderebbe neppure
	 * per un atto in corso.
	 *
	 * @param string $indirizzo Indirizzo da richiedere.
	 */
	private function richiesta( string $indirizzo ): void {
		$variabili = $GLOBALS['wp']->public_query_vars;

		$this->go_to( $indirizzo );

		$GLOBALS['wp_the_query']          = new \WP_Query();
		$GLOBALS['wp_query']              = $GLOBALS['wp_the_query'];
		$GLOBALS['wp']                    = new \WP();
		$GLOBALS['wp']->public_query_vars = $variabili;
		$GLOBALS['wp']->main( '' );
	}

	/**
	 * Un atto del tipo normativo, 15 giorni, pubblicato con il passaggio vero.
	 *
	 * @param string $istante Data e ora nel fuso del sito.
	 * @param string $oggetto Oggetto dell'atto.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto ): int {
		$this->fissa_orologi( $istante );

		$id    = $this->atto_in_verifica( 'normativo', array( 'post_title' => $oggetto ) );
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 'publish', get_post_status( $id ), 'Precondizione: l\'atto e\' pubblicato.' );

		return $id;
	}

	/**
	 * Il documento principale dell'atto.
	 *
	 * @param int $atto_id Atto.
	 * @return int
	 */
	private function documento( int $atto_id ): int {
		$documento = (int) get_post_meta( $atto_id, \AlboPretorioPa\META_DOCUMENTO_PRINCIPALE, true );

		$this->assertGreaterThan( 0, $documento, 'Precondizione: l\'atto ha il documento principale.' );

		return $documento;
	}

	/**
	 * Gli atti che il pubblico raggiunge da ogni strada, come l'utente indicato.
	 *
	 * Per ogni strada l'elenco degli atti fra quelli indicati che la strada
	 * consegna. Le strade sono quelle dell'elenco chiuso del meccanismo comune
	 * che un sito con il tipo aperto offre: la mappa per i motori di ricerca e'
	 * a parte, perche' li' un atto non c'e' nemmeno in corso (ALBO-06).
	 *
	 * @param int             $utente Utente, zero per il visitatore.
	 * @param array<int, int> $atti   Atti da cercare.
	 * @return array<string, array<int, int>>
	 */
	private function strade( int $utente, array $atti ): array {
		wp_set_current_user( $utente );

		$visti = array();

		foreach ( $atti as $atto ) {
			$this->richiesta( get_permalink( $atto ) );
			if ( is_singular( TIPO ) && get_queried_object_id() === $atto ) {
				$visti['scheda'][] = $atto;
			}

			$this->richiesta( get_permalink( $this->documento( $atto ) ) );
			if ( is_attachment() && get_queried_object_id() === $this->documento( $atto ) ) {
				$visti['pagina del documento'][] = $atto;
			}

			$this->consegne = array();
			$this->go_to( conformita_core_indirizzo_consegna( $this->documento( $atto ) ) );
			if ( isset( $this->consegne[0]['stato'] ) && 200 === $this->consegne[0]['stato'] ) {
				$visti['documento'][] = $atto;
			}

			$this->richiesta(
				add_query_arg(
					array(
						'p'         => $atto,
						'post_type' => TIPO,
					),
					home_url( '/' )
				)
			);
			if ( is_singular( TIPO ) && get_queried_object_id() === $atto ) {
				$visti['scheda per numero e tipo'][] = $atto;
			}

			$documenti = new \WP_REST_Request( 'GET', '/wp/v2/media' );
			$documenti->set_param( 'parent', $atto );
			$risposta = rest_do_request( $documenti );
			if ( 200 === $risposta->get_status() && in_array( $this->documento( $atto ), array_map( 'intval', wp_list_pluck( (array) $risposta->get_data(), 'id' ) ), true ) ) {
				$visti['dati per programmi, documenti dell\'atto'][] = $atto;
			}

			$singolo = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/' . TIPO . '/' . $atto ) );
			if ( 200 === $singolo->get_status() ) {
				$visti['dati per programmi, singolo'][] = $atto;
			}

			$anteprima = get_oembed_response_data( get_post( $atto ), 600 );
			if ( is_array( $anteprima ) && isset( $anteprima['title'] ) ) {
				$visti['anteprima incorporata'][] = $atto;
			}
		}

		$elenchi = array(
			'elenco'             => get_post_type_archive_link( TIPO ),
			'ricerca'            => add_query_arg( 's', 'di prova', home_url( '/' ) ),
			'feed'               => add_query_arg( 'feed', 'rss2', (string) get_post_type_archive_link( TIPO ) ),
			'elenco per anno'    => add_query_arg(
				array(
					'm'         => '2041',
					'post_type' => TIPO,
				),
				home_url( '/' )
			),
			'elenco per autore'  => add_query_arg(
				array(
					'author'    => $this->redattore,
					'post_type' => TIPO,
				),
				home_url( '/' )
			),
			'feed della ricerca' => add_query_arg(
				array(
					's'    => 'di prova',
					'feed' => 'rss2',
				),
				home_url( '/' )
			),
		);

		foreach ( $elenchi as $strada => $indirizzo ) {
			$this->richiesta( (string) $indirizzo );
			$visti[ $strada ] = array_values( array_intersect( $atti, array_map( 'intval', wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' ) ) ) );
		}

		$ricerca = new \WP_REST_Request( 'GET', '/wp/v2/search' );
		$ricerca->set_param( 'search', 'di prova' );
		$ricerca->set_param( 'subtype', TIPO );
		$trovati                              = rest_do_request( $ricerca );
		$visti['dati per programmi, ricerca'] = array_values( array_intersect( $atti, array_map( 'intval', 200 === $trovati->get_status() ? wp_list_pluck( (array) $trovati->get_data(), 'id' ) : array() ) ) );

		$collezione                          = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/' . TIPO ) );
		$restituiti                          = 200 === $collezione->get_status() ? wp_list_pluck( (array) $collezione->get_data(), 'id' ) : array();
		$visti['dati per programmi, elenco'] = array_values( array_intersect( $atti, array_map( 'intval', $restituiti ) ) );

		$terzi                            = new \WP_Query(
			array(
				'post_type'      => TIPO,
				'posts_per_page' => -1,
			)
		);
		$visti['interrogazione di terzi'] = array_values( array_intersect( $atti, array_map( 'intval', wp_list_pluck( $terzi->posts, 'ID' ) ) ) );

		foreach ( $atti as $atto ) {
			foreach ( $atti as $vicino ) {
				if ( $vicino === $atto ) {
					continue;
				}

				$GLOBALS['post'] = get_post( $vicino );
				foreach ( array( true, false ) as $precedente ) {
					$trovato = get_adjacent_post( false, '', $precedente );
					if ( $trovato instanceof \WP_Post && $atto === (int) $trovato->ID ) {
						$visti['precedente e successivo'][] = $atto;
					}
				}
			}
		}
		unset( $GLOBALS['post'] );

		foreach ( $visti as $strada => $elenco ) {
			$visti[ $strada ] = array_values( array_unique( $elenco ) );
			sort( $visti[ $strada ] );
		}
		ksort( $visti );

		return $visti;
	}

	/**
	 * Tutte le strade, ciascuna con gli stessi atti.
	 *
	 * @param array<int, int> $atti Atti.
	 * @return array<string, array<int, int>>
	 */
	private function ovunque( array $atti ): array {
		sort( $atti );

		$strade = array(
			'anteprima incorporata',
			'dati per programmi, documenti dell\'atto',
			'dati per programmi, elenco',
			'dati per programmi, ricerca',
			'dati per programmi, singolo',
			'documento',
			'elenco',
			'elenco per anno',
			'elenco per autore',
			'feed',
			'feed della ricerca',
			'interrogazione di terzi',
			'pagina del documento',
			'precedente e successivo',
			'ricerca',
			'scheda',
			'scheda per numero e tipo',
		);

		return array_fill_keys( $strade, $atti );
	}

	/**
	 * A-175: in corso su ogni strada fino all'ultimo secondo, scaduto su nessuna dalla mezzanotte.
	 *
	 * Due atti: il primo scade il 16, il secondo il 20. Alla mezzanotte del 17
	 * il secondo si vede ancora su ogni strada, cosi' l'assenza del primo non
	 * dipende da una strada che in quell'istante non risponde. Lo stesso per il
	 * visitatore e per l'amministratore: la politica e' `irraggiungibile`, e chi
	 * ha permessi, sul sito pubblico, vede quello che vede chiunque (ALBO-05).
	 */
	public function test_a175_scaduto_invisibile_su_ogni_strada_per_chiunque(): void {
		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$secondo = $this->pubblicato_il( '2041-10-05 10:00:00', 'Avviso di prova' );

		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $primo ), 'Precondizione: la fine del primo atto.' );
		$this->assertSame( '2041-10-20', conformita_core_fine_pubblicazione( $secondo ), 'Precondizione: la fine del secondo atto.' );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
		) as $chi => $utente ) {
			$this->fissa_orologi( self::ULTIMO_ISTANTE );
			$this->assertSame(
				$this->ovunque( array( $primo, $secondo ) ),
				$this->strade( $utente, array( $primo, $secondo ) ),
				$chi . ', ultimo secondo: tutti e due gli atti si vedono su ogni strada.'
			);

			$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );
			$this->assertSame(
				$this->ovunque( array( $secondo ) ),
				$this->strade( $utente, array( $primo, $secondo ) ),
				$chi . ', mezzanotte: il primo atto non si vede su nessuna strada, il secondo su tutte.'
			);
		}

		$this->assertSame( 'publish', get_post_status( $primo ), 'Lo stato memorizzato resta pubblicato: a nascondere l\'atto e\' la data letta.' );
	}

	/**
	 * A-176: la mappa per i motori di ricerca non elenca gli atti, in corso o scaduti.
	 *
	 * La riga dichiara il proprio limite: qui l'assenza dello scaduto non prova
	 * il filtro di scadenza, perche' l'atto manca anche in corso, per la politica
	 * di indicizzazione (ALBO-06). La scadenza sulla mappa la prova il meccanismo
	 * comune su un tipo indicizzabile (C-14). Il controllo positivo e' un
	 * contenuto ordinario, che nella stessa mappa c'e'.
	 */
	public function test_a176_mappa_per_i_motori_senza_atti(): void {
		$atto      = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$ordinario = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_set_current_user( 0 );

		foreach ( array( self::ULTIMO_ISTANTE, self::PRIMO_ISTANTE_SCADUTO ) as $istante ) {
			$this->fissa_orologi( $istante );

			$indirizzi = array();
			$fornitore = wp_sitemaps_get_server()->registry->get_provider( 'posts' );

			foreach ( array_keys( $fornitore->get_object_subtypes() ) as $sottotipo ) {
				foreach ( $fornitore->get_url_list( 1, (string) $sottotipo ) as $voce ) {
					$indirizzi[] = $voce['loc'];
				}
			}

			$this->assertContains( get_permalink( $ordinario ), $indirizzi, $istante . ': precondizione, la mappa elenca i contenuti ordinari.' );
			$this->assertNotContains( get_permalink( $atto ), $indirizzi, $istante . ': l\'atto non e\' nella mappa.' );
		}
	}

	/**
	 * A-177: un atto scritto con le sue date originali, come fara' l'importatore, e' invisibile da subito.
	 *
	 * Gli atti che arrivano da un altro albo portano date di fatti gia' avvenuti,
	 * e un atto la cui fine e' passata non entra mai nella vista pubblica, anche
	 * se nell'albo di provenienza era ancora consultabile fra gli scaduti
	 * (decisione dell'8 ottobre 2026). L'importatore non c'e' ancora: la prova
	 * scrive lo stato e la data direttamente, come la scriverebbe lui, e la riga
	 * si ripete con l'importatore quando arriva. Controllo positivo: un atto in
	 * corso pubblicato con il passaggio vero si vede su ogni strada.
	 */
	public function test_a177_atto_importato_gia_scaduto_mai_visibile(): void {
		$in_corso  = $this->pubblicato_il( '2041-10-01 09:30:00', 'Avviso di prova' );
		$importato = $this->atto_completo( 'normativo', array( 'post_title' => 'Graduatoria di prova' ) );

		$scritta = $this->scavalca_barriera(
			static function () use ( $importato ) {
				wp_publish_post( $importato );

				return conformita_core_imposta_fine_pubblicazione( $importato, '2041-09-30' );
			}
		);
		$this->assertTrue( $scritta, 'Precondizione: la fine originale e\' scritta.' );
		$this->assertSame( '2041-09-30', conformita_core_fine_pubblicazione( $importato ), 'Precondizione: la fine originale si rilegge.' );

		clean_post_cache( $importato );
		$this->assertSame( 'publish', get_post_status( $importato ), 'Precondizione: l\'atto importato risulta pubblicato.' );

		$this->fissa_orologi( '2041-10-02 12:00:00' );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
		) as $chi => $utente ) {
			$this->assertSame(
				$this->ovunque( array( $in_corso ) ),
				$this->strade( $utente, array( $in_corso, $importato ) ),
				$chi . ': l\'atto importato gia\' scaduto non si vede su nessuna strada.'
			);
		}
	}

	/**
	 * A-178: nessuna impostazione rende visibili gli scaduti.
	 *
	 * La politica letta dal meccanismo comune e' quella scritta nel codice, e
	 * nei sorgenti non c'e' nessuna strada per cambiarla: le due costanti si
	 * usano solo nella registrazione della sezione, e nessuna opzione, nessun
	 * filtro e nessun dato dell'albo porta un nome di archivio, storico o
	 * consultazione degli scaduti. L'archivio riservato, quando arrivera', sara'
	 * una schermata dell'amministrazione, non una scelta sul sito pubblico.
	 */
	public function test_a178_nessuna_impostazione_per_gli_scaduti(): void {
		$politica = conformita_core_politica_sezione( SEZIONE );

		$this->assertInstanceOf( \Conformita_Core_Politica::class, $politica );
		$this->assertSame( 'irraggiungibile', $politica->scadenza(), 'La politica di scadenza letta dal meccanismo comune.' );
		$this->assertSame( 'irraggiungibile', SCADENZA );
		$this->assertSame( 'vietata', INDICIZZAZIONE );

		$radice  = dirname( __DIR__ );
		$trovati = glob( $radice . '/includes/*.php' );
		$file    = array_merge( array( $radice . '/albo-pretorio-pa.php' ), is_array( $trovati ) ? $trovati : array() );
		$usi     = array();

		foreach ( $file as $percorso ) {
			$testo = (string) file_get_contents( $percorso );
			$testo = (string) preg_replace( '#/\*.*?\*/#s', '', $testo );
			$testo = (string) preg_replace( '#^\s*//.*$#m', '', $testo );

			if ( preg_match_all( '/\b(?:SCADENZA|INDICIZZAZIONE)\b(?!\s*=(?!=))/', $testo, $presenze ) ) {
				$usi[ basename( $percorso ) ] = count( $presenze[0] );
			}

			$this->assertDoesNotMatchRegularExpression(
				'/(?:get_option|update_option|add_option|apply_filters|get_post_meta|register_setting)\s*\([^;]*(?:scadut|storico|oblio|archivi|politica)/i',
				$testo,
				basename( $percorso ) . ': nessuna impostazione sugli scaduti.'
			);
		}

		$this->assertSame( array( 'class-avvio.php' => 2 ), $usi, 'Le due politiche si usano solo nella registrazione della sezione.' );
	}
}
