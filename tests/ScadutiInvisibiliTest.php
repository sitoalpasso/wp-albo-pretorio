<?php
/**
 * Gli atti scaduti sono invisibili al pubblico: righe A-175..A-179 (ALBO-05, ALBO-18).
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
	 * Codici di stato emessi durante una richiesta (A-179).
	 *
	 * @var int[]
	 */
	private array $stati = array();

	/**
	 * Chi ha soltanto il permesso di consultare gli scaduti (ALBO-34): sul sito
	 * pubblico vede quello che vede chiunque.
	 *
	 * @var int
	 */
	private int $consultatore = 0;

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
		$this->consultatore = $this->utente_con( array( \AlboPretorioPa\Permessi::CHIAVE_CONSULTAZIONE ) );
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
		$this->commenta( $id );

		return $id;
	}

	/**
	 * Un commento approvato sull'atto e uno sul suo documento.
	 *
	 * Sull'albo un commento non si scrive su un atto pubblicato, perche' la
	 * barriera lo rifiuta: ci arriva solo da una scrittura diretta, per esempio
	 * di un importatore. La prova lo scrive cosi', perche' i commenti sono
	 * strade che il meccanismo comune chiude e che qui si devono vedere.
	 *
	 * @param int $atto_id Atto.
	 */
	private function commenta( int $atto_id ): void {
		$documento = $this->documento( $atto_id );

		foreach ( array( $atto_id, $documento ) as $destinatario ) {
			$commento = $this->scavalca_barriera(
				static function () use ( $destinatario ) {
					return wp_insert_comment(
						array(
							'comment_post_ID'  => $destinatario,
							'comment_content'  => 'Commento di prova ' . $destinatario,
							'comment_approved' => 1,
							'comment_author'   => 'Prova',
						)
					);
				}
			);
			$this->assertGreaterThan( 0, (int) $commento, 'Precondizione: il commento e\' scritto.' );
		}
	}

	/**
	 * Gli atti, o i loro documenti, a cui appartengono i commenti indicati.
	 *
	 * @param mixed      $commenti  Commenti, come oggetti o come righe dell'interfaccia.
	 * @param array<int> $atti      Atti in prova.
	 * @param bool       $documenti Contano anche i commenti dei documenti.
	 * @return int[]
	 */
	private function atti_dei_commenti( $commenti, array $atti, bool $documenti ): array {
		$contenuti = array();
		foreach ( (array) $commenti as $commento ) {
			if ( $commento instanceof \WP_Comment ) {
				$contenuti[] = (int) $commento->comment_post_ID;
			} elseif ( is_array( $commento ) && isset( $commento['post'] ) ) {
				$contenuti[] = (int) $commento['post'];
			}
		}

		$trovati = array();
		foreach ( $atti as $atto ) {
			if ( in_array( $atto, $contenuti, true ) || ( $documenti && in_array( $this->documento( $atto ), $contenuti, true ) ) ) {
				$trovati[] = $atto;
			}
		}

		return $trovati;
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
	 * Che cosa ogni strada consegna degli atti indicati, come l'utente indicato.
	 *
	 * Per ogni strada due elenchi di atti. In `visti` gli atti che la strada
	 * consegna davvero, riconosciuti per identita' e oggetto: e' l'osservazione
	 * dei controlli positivi. In `tracce` gli atti di cui la strada lascia
	 * uscire **qualunque cosa**: il contenuto nei risultati anche senza la
	 * pagina giusta, un'anteprima senza titolo ma non vuota, una risposta
	 * dell'interfaccia che non e' il rifiuto o che porta l'oggetto, uno
	 * scaricamento diverso dal rifiuto di un indirizzo inesistente. Sullo
	 * scaduto si guarda `tracce`: un'osservazione che misura solo il successo
	 * resterebbe verde con un'esposizione parziale.
	 *
	 * Le strade sono quelle dell'elenco chiuso del meccanismo comune che un sito
	 * con il tipo aperto offre, piu' quelle che WordPress apre con lo stesso
	 * tipo: la mappa per i motori di ricerca e' a parte, perche' li' un atto non
	 * c'e' nemmeno in corso (ALBO-06).
	 *
	 * @param int             $utente Utente, zero per il visitatore.
	 * @param array<int, int> $atti   Atti da cercare.
	 * @return array{visti: array<string, array<int, int>>, tracce: array<string, array<int, int>>}
	 */
	private function strade( int $utente, array $atti ): array {
		wp_set_current_user( $utente );

		$visti  = array();
		$tracce = array();
		$segna  = static function ( array &$elenco, string $strada, int $atto, bool $si ): void {
			$elenco[ $strada ] = $elenco[ $strada ] ?? array();
			if ( $si ) {
				$elenco[ $strada ][] = $atto;
			}
		};

		$rifiuto = $this->consegna( PHP_INT_MAX, PHP_INT_MAX - 1 );
		$this->assertCount( 1, $rifiuto, 'Precondizione: il rifiuto di un indirizzo inesistente esce una volta.' );
		$this->assertSame( 404, $rifiuto[0]['stato'], 'Precondizione: il rifiuto di un indirizzo inesistente.' );

		// Il rifiuto atteso, per intero: stato e corpo, senza niente dell'atto.
		$rifiuti = array(
			'dati per programmi, singolo'              => $this->esito( rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/' . TIPO . '/' . PHP_INT_MAX ) ) ),
			'dati per programmi, scheda del documento' => $this->esito( rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/media/' . PHP_INT_MAX ) ) ),
			'anteprima incorporata, interfaccia'       => $this->esito( $this->interfaccia( '/oembed/1.0/embed', array( 'url' => add_query_arg( 'p', PHP_INT_MAX, home_url( '/' ) ) ) ) ),
			'anteprima incorporata del documento'      => $this->esito( $this->interfaccia( '/oembed/1.0/embed', array( 'url' => add_query_arg( 'attachment_id', PHP_INT_MAX, home_url( '/' ) ) ) ) ),
		);
		foreach ( $rifiuti as $strada => $rifiuto_atteso ) {
			$this->assertSame( 404, $rifiuto_atteso[0], 'Precondizione: ' . $strada . ', un numero inesistente e\' rifiutato.' );
		}

		foreach ( $atti as $atto ) {
			$oggetto   = get_post( $atto )->post_title;
			$documento = $this->documento( $atto );
			$indirizzi = array(
				'scheda'                   => get_permalink( $atto ),
				'scheda per numero e tipo' => add_query_arg(
					array(
						'p'         => $atto,
						'post_type' => TIPO,
					),
					home_url( '/' )
				),
				'scheda da incorporare'    => add_query_arg( 'embed', 'true', get_permalink( $atto ) ),
			);

			foreach ( $indirizzi as $strada => $indirizzo ) {
				$this->richiesta( (string) $indirizzo );
				$nei_risultati = in_array( $atto, $this->risultati(), true );
				$segna( $visti, $strada, $atto, is_singular( TIPO ) && get_queried_object_id() === $atto && $nei_risultati && ( 'scheda da incorporare' !== $strada || is_embed() ) );
				$segna( $tracce, $strada, $atto, get_queried_object_id() === $atto || $nei_risultati );
			}

			$this->richiesta( get_permalink( $documento ) );
			$nei_risultati = in_array( $documento, $this->risultati(), true );
			$segna( $visti, 'pagina del documento', $atto, is_attachment() && get_queried_object_id() === $documento && $nei_risultati );
			$segna( $tracce, 'pagina del documento', $atto, get_queried_object_id() === $documento || $nei_risultati );

			$consegne   = $this->consegna( $atto, $documento );
			$prima      = $consegne[0] ?? array();
			$consegnato = 1 === count( $consegne ) && 200 === ( $prima['stato'] ?? 0 ) && ! empty( $prima['percorso'] );
			$segna( $visti, 'documento', $atto, $consegnato );
			$segna( $tracce, 'documento', $atto, $rifiuto !== $consegne );

			$risposte = array(
				'dati per programmi, singolo'              => rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/' . TIPO . '/' . $atto ) ),
				'dati per programmi, documenti dell\'atto' => $this->interfaccia( '/wp/v2/media', array( 'parent' => $atto ) ),
				'anteprima incorporata, interfaccia'       => $this->interfaccia( '/oembed/1.0/embed', array( 'url' => add_query_arg( 'p', $atto, home_url( '/' ) ) ) ),
				'dati per programmi, scheda del documento' => rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/media/' . $documento ) ),
				'anteprima incorporata del documento'      => $this->interfaccia( '/oembed/1.0/embed', array( 'url' => get_permalink( $documento ) ) ),
			);

			foreach ( $risposte as $strada => $risposta ) {
				$dati   = wp_json_encode( $risposta->get_data() );
				$campi  = is_array( $risposta->get_data() ) ? $risposta->get_data() : array();
				$numero = (int) ( $campi['id'] ?? 0 );
				$titolo = $campi['title']['rendered'] ?? ( $campi['title'] ?? '' );
				switch ( $strada ) {
					case 'dati per programmi, singolo':
						$visto = 200 === $risposta->get_status() && $atto === $numero && $oggetto === $titolo;
						break;
					case 'dati per programmi, scheda del documento':
						$padre = (int) ( $campi['post'] ?? 0 );
						$visto = 200 === $risposta->get_status() && $documento === $numero && $atto === $padre;
						break;
					case 'anteprima incorporata del documento':
						$visto = 200 === $risposta->get_status() && get_post( $documento )->post_title === $titolo;
						break;
					case 'dati per programmi, documenti dell\'atto':
						$visto = 200 === $risposta->get_status() && in_array( $documento, array_map( 'intval', wp_list_pluck( (array) $risposta->get_data(), 'id' ) ), true );
						break;
					default:
						$visto = 200 === $risposta->get_status() && $oggetto === $titolo;
				}
				$segna( $visti, $strada, $atto, $visto );
				if ( 'dati per programmi, documenti dell\'atto' === $strada ) {
					// Per lo scaduto si pretende una collezione valida e vuota, non un errore.
					$traccia = array( 200, array() ) !== $this->esito( $risposta );
				} else {
					$traccia = $rifiuti[ $strada ] !== $this->esito( $risposta );
				}
				$segna( $tracce, $strada, $atto, $traccia || false !== strpos( (string) $dati, $oggetto ) || array() !== array_intersect( array( $atto, $documento ), $this->identificativi( $risposta->get_data() ) ) );
			}

			$anteprima = get_oembed_response_data( get_post( $atto ), 600 );
			$segna( $visti, 'anteprima incorporata', $atto, is_array( $anteprima ) && isset( $anteprima['title'] ) && $oggetto === $anteprima['title'] );
			$segna( $tracce, 'anteprima incorporata', $atto, ! empty( $anteprima ) );
		}

		$commenti = array(
			'feed dei commenti'                 => add_query_arg( 'feed', 'comments-rss2', home_url( '/' ) ),
			'feed della ricerca con i commenti' => add_query_arg(
				array(
					's'            => 'di prova',
					'feed'         => 'rss2',
					'withcomments' => '1',
				),
				home_url( '/' )
			),
		);
		foreach ( $atti as $atto ) {
			$commenti[ 'feed dei commenti dell\'atto ' . $atto ] = add_query_arg( 'feed', 'rss2', (string) get_permalink( $atto ) );
		}

		foreach ( $commenti as $strada => $indirizzo ) {
			$this->richiesta( (string) $indirizzo );
			$letti  = (array) ( $GLOBALS['wp_query']->comments ?? array() );
			$strada = 0 === strpos( $strada, 'feed dei commenti dell\'atto ' ) ? 'feed dei commenti dell\'atto' : $strada;

			$visti[ $strada ]  = array_values( array_unique( array_merge( $visti[ $strada ] ?? array(), $this->atti_dei_commenti( $letti, $atti, false ) ) ) );
			$tracce[ $strada ] = array_values( array_unique( array_merge( $tracce[ $strada ] ?? array(), $this->atti_dei_commenti( $letti, $atti, true ) ) ) );
		}

		$risposte_commenti = array( 'dati per programmi, commenti' => array( $this->interfaccia( '/wp/v2/comments', array( 'per_page' => 100 ) ) ) );
		foreach ( $atti as $atto ) {
			$risposte_commenti['dati per programmi, commenti dell\'atto'][] = $this->interfaccia(
				'/wp/v2/comments',
				array(
					'post'     => array( $atto, $this->documento( $atto ) ),
					'per_page' => 100,
				)
			);
		}

		foreach ( $risposte_commenti as $strada => $elenco_risposte ) {
			$visti[ $strada ]  = array();
			$tracce[ $strada ] = array();
			foreach ( $elenco_risposte as $risposta ) {
				$righe             = 200 === $risposta->get_status() ? (array) $risposta->get_data() : array();
				$visti[ $strada ]  = array_merge( $visti[ $strada ], $this->atti_dei_commenti( $righe, $atti, false ) );
				$tracce[ $strada ] = array_merge( $tracce[ $strada ], $this->atti_dei_commenti( (array) $risposta->get_data(), $atti, true ) );
				foreach ( $atti as $atto ) {
					if ( false !== strpos( (string) wp_json_encode( $risposta->get_data() ), 'Commento di prova ' . $atto ) ) {
						$tracce[ $strada ][] = $atto;
					}
				}
			}
			$visti[ $strada ]  = array_values( array_unique( $visti[ $strada ] ) );
			$tracce[ $strada ] = array_values( array_unique( $tracce[ $strada ] ) );
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
			$visti[ $strada ]  = array_values( array_intersect( $atti, $this->risultati() ) );
			$tracce[ $strada ] = $visti[ $strada ];
		}

		$trovati                              = $this->interfaccia(
			'/wp/v2/search',
			array(
				'search'  => 'di prova',
				'subtype' => TIPO,
			)
		);
		$visti['dati per programmi, ricerca'] = array_values( array_intersect( $atti, array_map( 'intval', 200 === $trovati->get_status() ? wp_list_pluck( (array) $trovati->get_data(), 'id' ) : array() ) ) );

		$collezione                          = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/' . TIPO ) );
		$visti['dati per programmi, elenco'] = array_values( array_intersect( $atti, array_map( 'intval', 200 === $collezione->get_status() ? wp_list_pluck( (array) $collezione->get_data(), 'id' ) : array() ) ) );

		foreach ( array(
			'dati per programmi, ricerca' => $trovati,
			'dati per programmi, elenco'  => $collezione,
		) as $strada => $risposta ) {
			$dati              = (string) wp_json_encode( $risposta->get_data() );
			$tracce[ $strada ] = array();
			foreach ( $atti as $atto ) {
				if ( in_array( $atto, $visti[ $strada ], true ) || in_array( $atto, $this->identificativi( $risposta->get_data() ), true ) || false !== strpos( $dati, get_post( $atto )->post_title ) ) {
					$tracce[ $strada ][] = $atto;
				}
			}
		}

		$terzi                             = new \WP_Query(
			array(
				'post_type'      => TIPO,
				'posts_per_page' => -1,
			)
		);
		$visti['interrogazione di terzi']  = array_values( array_intersect( $atti, array_map( 'intval', wp_list_pluck( $terzi->posts, 'ID' ) ) ) );
		$tracce['interrogazione di terzi'] = $visti['interrogazione di terzi'];

		$visti['precedente e successivo'] = array();
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
		$tracce['precedente e successivo'] = $visti['precedente e successivo'];

		return array(
			'visti'  => $this->in_ordine( $visti ),
			'tracce' => $this->in_ordine( $tracce ),
		);
	}

	/**
	 * Elenchi senza doppioni e in ordine, strade in ordine.
	 *
	 * @param array<string, array<int, int>> $strade Strade.
	 * @return array<string, array<int, int>>
	 */
	private function in_ordine( array $strade ): array {
		foreach ( $strade as $strada => $elenco ) {
			$strade[ $strada ] = array_values( array_unique( $elenco ) );
			sort( $strade[ $strada ] );
		}
		ksort( $strade );

		return $strade;
	}

	/**
	 * Gli identificativi dei contenuti della richiesta appena fatta.
	 *
	 * @return array<int, int>
	 */
	private function risultati(): array {
		return array_map( 'intval', wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' ) );
	}

	/**
	 * Stato e corpo di una risposta dell'interfaccia, da confrontare per intero.
	 *
	 * @param \WP_REST_Response $risposta Risposta.
	 * @return array{0:int,1:mixed}
	 */
	private function esito( \WP_REST_Response $risposta ): array {
		return array( $risposta->get_status(), $risposta->get_data() );
	}

	/**
	 * Ogni identificativo di contenuto che compare nel corpo, a qualunque profondita' e con qualunque stato.
	 *
	 * Si raccolgono i campi che nell'interfaccia portano il numero di un
	 * contenuto: il suo, quello del contenuto a cui appartiene, quello del
	 * contenuto commentato.
	 *
	 * @param mixed $dati Corpo della risposta.
	 * @return int[]
	 */
	private function identificativi( $dati ): array {
		$trovati = array();

		if ( is_object( $dati ) ) {
			$dati = get_object_vars( $dati );
		}

		if ( ! is_array( $dati ) ) {
			return $trovati;
		}

		foreach ( $dati as $chiave => $valore ) {
			if ( in_array( $chiave, array( 'id', 'post', 'parent' ), true ) && is_numeric( $valore ) ) {
				$trovati[] = (int) $valore;
			}
			$trovati = array_merge( $trovati, $this->identificativi( $valore ) );
		}

		return $trovati;
	}

	/**
	 * Una richiesta all'interfaccia per programmi, con parametri.
	 *
	 * @param string               $rotta     Rotta.
	 * @param array<string, mixed> $parametri Parametri.
	 * @return \WP_REST_Response
	 */
	private function interfaccia( string $rotta, array $parametri ): \WP_REST_Response {
		$richiesta = new \WP_REST_Request( 'GET', $rotta );

		foreach ( $parametri as $nome => $valore ) {
			$richiesta->set_param( $nome, $valore );
		}

		return rest_do_request( $richiesta );
	}

	/**
	 * Tutto cio' che il punto pubblico di consegna emette per una richiesta.
	 *
	 * @param int $atto      Atto dichiarato.
	 * @param int $documento Documento chiesto.
	 * @return array<int, array<string, mixed>>
	 */
	private function consegna( int $atto, int $documento ): array {
		$this->consegne = array();

		$this->go_to(
			add_query_arg(
				array(
					\Conformita_Core_Consegna::VAR_ATTO => $atto,
					\Conformita_Core_Consegna::VAR_ALLEGATO => $documento,
				),
				home_url( '/' )
			)
		);

		return $this->consegne;
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
			'anteprima incorporata, interfaccia',
			'dati per programmi, documenti dell\'atto',
			'dati per programmi, elenco',
			'anteprima incorporata del documento',
			'dati per programmi, commenti',
			'dati per programmi, commenti dell\'atto',
			'dati per programmi, ricerca',
			'dati per programmi, scheda del documento',
			'dati per programmi, singolo',
			'documento',
			'elenco',
			'elenco per anno',
			'elenco per autore',
			'feed',
			'feed dei commenti',
			'feed dei commenti dell\'atto',
			'feed della ricerca',
			'feed della ricerca con i commenti',
			'interrogazione di terzi',
			'pagina del documento',
			'precedente e successivo',
			'ricerca',
			'scheda',
			'scheda da incorporare',
			'scheda per numero e tipo',
		);

		$tutte = array_fill_keys( $strade, $atti );
		ksort( $tutte );

		return $tutte;
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
			'consultatore'   => $this->consultatore,
		) as $chi => $utente ) {
			$this->fissa_orologi( self::ULTIMO_ISTANTE );
			$this->assertSame(
				$this->ovunque( array( $primo, $secondo ) ),
				$this->strade( $utente, array( $primo, $secondo ) )['visti'],
				$chi . ', ultimo secondo: tutti e due gli atti si vedono su ogni strada.'
			);

			$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );
			$strade = $this->strade( $utente, array( $primo, $secondo ) );
			$this->assertSame( $this->ovunque( array( $secondo ) ), $strade['visti'], $chi . ', mezzanotte: il secondo atto si vede su ogni strada.' );
			$this->assertSame( $this->ovunque( array( $secondo ) ), $strade['tracce'], $chi . ', mezzanotte: del primo atto non esce niente da nessuna strada.' );
		}

		$this->assertSame( 'publish', get_post_status( $primo ), 'Lo stato memorizzato resta pubblicato: a nascondere l\'atto e\' la data letta.' );
	}

	/**
	 * A-175, prova dell'osservatore: un rifiuto che porta dati dell'atto si vede.
	 *
	 * Le osservazioni negative di A-175 non devono dipendere dallo stato della
	 * risposta: qui l'interfaccia viene guastata apposta perche', per lo
	 * scaduto, risponda "non trovato" lasciando nel corpo il suo numero e il suo
	 * testo, senza titolo. La prova pretende che `strade()` lo segnali, sul
	 * singolo e sull'elenco.
	 */
	public function test_a175_osservatore_vede_un_rifiuto_che_porta_dati(): void {
		$primo = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );

		$guasto = static function ( $risposta, $gestore, $richiesta ) use ( $primo ) {
			unset( $gestore );
			if ( '/wp/v2/' . TIPO . '/' . $primo === $richiesta->get_route() ) {
				return new \WP_REST_Response(
					array(
						'id'      => $primo,
						'content' => array( 'rendered' => 'testo dell\'atto' ),
					),
					404
				);
			}
			if ( '/wp/v2/' . TIPO === $richiesta->get_route() ) {
				return new \WP_REST_Response( array( array( 'id' => $primo ) ), 404 );
			}

			return $risposta;
		};
		add_filter( 'rest_request_after_callbacks', $guasto, 10, 3 );
		$tracce = $this->strade( 0, array( $primo ) )['tracce'];
		remove_filter( 'rest_request_after_callbacks', $guasto, 10 );

		$this->assertSame( array( $primo ), $tracce['dati per programmi, singolo'], 'Un rifiuto con il numero dell\'atto nel corpo e\' una traccia.' );
		$this->assertSame( array( $primo ), $tracce['dati per programmi, elenco'], 'Un elenco rifiutato che porta il numero dell\'atto e\' una traccia.' );
	}

	/**
	 * A-179: l'indirizzo di un atto scaduto risponde come un indirizzo mai esistito.
	 *
	 * Decisione del 17 ottobre 2026: nessun avviso "non piu' in pubblicazione",
	 * che confermerebbe che l'atto c'e' stato. All'ultimo secondo la scheda si
	 * apre, dalla mezzanotte risponde "non trovata" con lo stesso codice e la
	 * stessa interrogazione vuota di un indirizzo inventato, per il visitatore e
	 * per l'amministratore.
	 */
	public function test_a179_indirizzo_scaduto_come_mai_esistito(): void {
		$atto        = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$indirizzo   = (string) get_permalink( $atto );
		$inventato   = add_query_arg( TIPO, 'atto-mai-esistito', home_url( '/' ) );
		$this->stati = array();
		$cattura     = function ( $intestazione, $codice ) {
			$this->stati[] = (int) $codice;

			return $intestazione;
		};
		add_filter( 'status_header', $cattura, 10, 2 );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
			'consultatore'   => $this->consultatore,
		) as $chi => $utente ) {
			wp_set_current_user( $utente );

			$this->fissa_orologi( self::ULTIMO_ISTANTE );
			$this->stati = array();
			$this->richiesta( $indirizzo );
			$this->assertTrue( is_singular( TIPO ) && get_queried_object_id() === $atto, $chi . ', ultimo secondo: precondizione, la scheda si apre.' );
			$this->assertNotContains( 404, $this->stati, $chi . ', ultimo secondo: la scheda non risponde "non trovata".' );

			$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );
			$risposte = array();
			foreach ( array(
				'scaduto'   => $indirizzo,
				'inventato' => $inventato,
			) as $quale => $dove ) {
				$this->stati = array();
				$this->richiesta( $dove );
				$risposte[ $quale ] = array(
					'non trovata' => is_404(),
					'stati'       => array_values( array_unique( $this->stati ) ),
					'risultati'   => $this->risultati(),
					'oggetto'     => get_queried_object_id(),
				);
			}

			$this->assertSame( array( 404 ), $risposte['inventato']['stati'], $chi . ': precondizione, un indirizzo inventato risponde "non trovata".' );
			$this->assertSame( $risposte['inventato'], $risposte['scaduto'], $chi . ', mezzanotte: l\'atto scaduto risponde come un indirizzo mai esistito.' );
		}

		remove_filter( 'status_header', $cattura, 10 );
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
		$importato = $this->atto_completo(
			'normativo',
			array(
				'post_title'    => 'Graduatoria di prova',
				'post_name'     => 'graduatoria-di-prova',
				'post_date'     => '2041-09-15 10:00:00',
				'post_date_gmt' => '2041-09-15 08:00:00',
			)
		);

		$scritta = $this->scavalca_barriera(
			static function () use ( $importato ) {
				wp_publish_post( $importato );

				return conformita_core_imposta_fine_pubblicazione( $importato, '2041-09-30' );
			}
		);
		$this->assertTrue( $scritta, 'Precondizione: la fine originale e\' scritta.' );
		$this->commenta( $importato );
		$this->assertSame( '2041-09-30', conformita_core_fine_pubblicazione( $importato ), 'Precondizione: la fine originale si rilegge.' );

		clean_post_cache( $importato );
		$this->assertSame( 'publish', get_post_status( $importato ), 'Precondizione: l\'atto importato risulta pubblicato.' );
		$this->assertSame( '2041-09-15 10:00:00', get_post( $importato )->post_date, 'Precondizione: l\'atto importato porta la sua data originale.' );

		foreach ( array(
			'visitatore'     => 0,
			'amministratore' => $this->amministratore,
			'consultatore'   => $this->consultatore,
		) as $chi => $utente ) {
			// Lo stesso atto, prima della sua fine, si vede su ogni strada: l'assenza dopo non dipende dall'assetto.
			$this->fissa_orologi( '2041-09-30 23:59:59' );
			$this->assertSame(
				$this->ovunque( array( $in_corso, $importato ) ),
				$this->strade( $utente, array( $in_corso, $importato ) )['visti'],
				$chi . ': prima della sua fine l\'atto importato si vede su ogni strada.'
			);

			$this->fissa_orologi( '2041-10-02 12:00:00' );
			$strade = $this->strade( $utente, array( $in_corso, $importato ) );
			$this->assertSame( $this->ovunque( array( $in_corso ) ), $strade['visti'], $chi . ': l\'atto in corso si vede su ogni strada.' );
			$this->assertSame( $this->ovunque( array( $in_corso ) ), $strade['tracce'], $chi . ': dell\'atto importato gia\' scaduto non esce niente da nessuna strada.' );
		}
	}

	/**
	 * A-178: nessuna impostazione rende visibili gli scaduti.
	 *
	 * Tre controlli, dal piu' forte al piu' debole. La politica letta dal
	 * meccanismo comune e' `irraggiungibile`. La registrazione della sezione e'
	 * **una sola, e la sua chiamata e' esattamente quella**: le due costanti
	 * passate dritte, senza condizioni, operatori o filtri in mezzo; e le due
	 * costanti sono definite una volta, con quel valore. Infine una sentinella
	 * lessicale: nei sorgenti, compresi quelli nelle sottocartelle, nessuna
	 * stringa che abbia la forma di un nome di opzione, filtro o dato parla di
	 * scadenza, storico, oblio, archivio, politica o visibilita', tranne le due
	 * che servono alla registrazione. La sentinella non prova che nessuna
	 * impostazione possa esistere: prova che non ce n'e' una con un nome
	 * riconoscibile, e obbliga chi ne aggiunge una a cambiare questa riga.
	 * Una seconda sentinella guarda l'altra porta: un'impostazione potrebbe
	 * lasciare la politica com'e' e staccare i filtri che la applicano. Nei
	 * sorgenti non c'e' nessuna rimozione di agganci (`remove_filter` e
	 * simili, anche per nome o toccando l'elenco degli agganci): quando ne
	 * servira' una, si aggiunge qui, una per una, con la sua ragione.
	 * L'archivio riservato, quando arrivera', sara' una schermata
	 * dell'amministrazione, non una scelta sul sito pubblico.
	 */
	public function test_a178_nessuna_impostazione_per_gli_scaduti(): void {
		$politica = conformita_core_politica_sezione( SEZIONE );

		$this->assertInstanceOf( \Conformita_Core_Politica::class, $politica );
		$this->assertSame( 'irraggiungibile', $politica->scadenza(), 'La politica di scadenza letta dal meccanismo comune.' );
		$this->assertSame( 'irraggiungibile', SCADENZA );
		$this->assertSame( 'vietata', INDICIZZAZIONE );

		$radice = dirname( __DIR__ );
		$file   = array( $radice . '/albo-pretorio-pa.php' );
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $radice . '/includes', \FilesystemIterator::SKIP_DOTS ) ) as $voce ) {
			if ( '.php' === substr( (string) $voce, -4 ) ) {
				$file[] = (string) $voce;
			}
		}
		sort( $file );

		$chiamate    = array();
		$definizioni = array();
		$nomi        = array();
		$tolti       = array();
		$rimozioni   = array( 'remove_filter', 'remove_action', 'remove_all_filters', 'remove_all_actions' );

		foreach ( $file as $percorso ) {
			$nome    = substr( $percorso, strlen( $radice ) + 1 );
			$gettoni = array_values(
				array_filter(
					token_get_all( (string) file_get_contents( $percorso ) ),
					static function ( $gettone ) {
						return ! is_array( $gettone ) || ! in_array( $gettone[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
					}
				)
			);
			$testi   = array_map(
				static function ( $gettone ) {
					return is_array( $gettone ) ? $gettone[1] : $gettone;
				},
				$gettoni
			);

			foreach ( $gettoni as $posto => $gettone ) {
				if ( ! is_array( $gettone ) ) {
					continue;
				}

				if ( T_STRING === $gettone[0] && 'conformita_core_registra_sezione' === $gettone[1] && '(' === ( $testi[ $posto + 1 ] ?? '' ) ) {
					$profondita = 0;
					$chiamata   = '';
					$quanti     = count( $testi );
					for ( $i = $posto; $i < $quanti; $i++ ) {
						$chiamata .= $testi[ $i ];
						if ( '(' === $testi[ $i ] ) {
							++$profondita;
						} elseif ( ')' === $testi[ $i ] && 0 === --$profondita ) {
							break;
						}
					}
					$chiamate[] = $nome . ': ' . $chiamata;
				}

				if ( T_CONST === $gettone[0] && in_array( $testi[ $posto + 1 ] ?? '', array( 'SCADENZA', 'INDICIZZAZIONE' ), true ) ) {
					$definizioni[] = $nome . ': ' . implode( '', array_slice( $testi, $posto, 5 ) );
				}

				if ( T_STRING === $gettone[0] && 'define' === $gettone[1] && false !== strpos( (string) ( $testi[ $posto + 2 ] ?? '' ), 'SCADENZA' ) ) {
					$definizioni[] = $nome . ': define';
				}

				if ( ( T_STRING === $gettone[0] && in_array( strtolower( $gettone[1] ), $rimozioni, true ) )
					|| ( T_CONSTANT_ENCAPSED_STRING === $gettone[0] && in_array( strtolower( substr( $gettone[1], 1, -1 ) ), array_merge( $rimozioni, array( 'wp_filter' ) ), true ) )
					|| ( T_VARIABLE === $gettone[0] && '$wp_filter' === $gettone[1] ) ) {
					$tolti[] = $nome . ': ' . $gettone[1];
				}

				if ( T_CONSTANT_ENCAPSED_STRING === $gettone[0] ) {
					$valore = substr( $gettone[1], 1, -1 );
					if ( 1 === preg_match( '/^[a-z0-9_\-]+$/i', $valore ) && 1 === preg_match( '/scad|storic|oblio|archiv|politic|visibil/i', $valore ) ) {
						$nomi[] = $nome . ': ' . $valore;
					}
				}
			}
		}

		$this->assertSame(
			array( "includes/class-avvio.php: conformita_core_registra_sezione(SEZIONE,array('indicizzazione'=>INDICIZZAZIONE,'scadenza'=>SCADENZA,))" ),
			$chiamate,
			'Una sola registrazione della sezione, con le due costanti passate dritte.'
		);
		$this->assertSame(
			array(
				"albo-pretorio-pa.php: constINDICIZZAZIONE='vietata';",
				"albo-pretorio-pa.php: constSCADENZA='irraggiungibile';",
			),
			$definizioni,
			'Le due politiche sono definite una volta, con il loro valore.'
		);
		$this->assertSame(
			array(
				// Il nome di una colonna del backup di Albo Pretorio On line (ALBO-35): l'importazione a secco la riconosce e la scarta.
				'includes/class-analisi-importazione.php: DataOblio',
				'includes/class-avvio.php: scadenza',
				// Il permesso della consultazione riservata (ALBO-34): una schermata dell'amministrazione, non una scelta sul sito pubblico.
				'includes/class-permessi.php: consultare_scaduti',
				'includes/class-permessi.php: conformita_core_capacita_consultazione_scaduti',
				'includes/class-tipo-atto.php: has_archive',
			),
			$nomi,
			'Sentinella: nessun nome di opzione, filtro o dato parla di scaduti, storico, oblio, archivio o visibilita\'.'
		);
		$this->assertSame(
			array(),
			$tolti,
			'Sentinella: l\'albo non stacca agganci, ne\' quelli del meccanismo comune ne\' altri; ogni rimozione futura va autorizzata qui, una per una.'
		);
	}
}
