<?php
/**
 * Elenco, scheda e ricerca della pagina pubblica dell'albo (ALBO-16, ALBO-28).
 *
 * Il codice prodotto qui non chiede niente al tema: ogni classe che usa e'
 * definita nel foglio di stile dell'albo, nessun testo e' nascosto con classi
 * prese in prestito, ogni campo ha un'etichetta visibile, i titoli seguono
 * l'ordine di lettura (il titolo della pagina lo mette il tema, l'albo parte
 * dal secondo livello). Gli atti mostrati sono soltanto quelli in
 * pubblicazione, ricontrollati uno per uno con `PaginaPubblica`.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Il disegno della pagina pubblica.
 */
final class VistaPubblica {

	/**
	 * Campi della ricerca.
	 *
	 * @var string
	 */
	const CAMPO_TESTO  = 'albo_testo';
	const CAMPO_TIPO   = 'albo_tipo';
	const CAMPO_ORGANO = 'albo_organo';
	const CAMPO_DAL    = 'albo_dal';
	const CAMPO_AL     = 'albo_al';
	const CAMPO_PAGINA = 'albo_pagina';

	/**
	 * Atti per pagina.
	 *
	 * @var int
	 */
	const PER_PAGINA = 20;

	/**
	 * Variabile di interrogazione che tiene solo gli atti con il numero di registro.
	 *
	 * @var string
	 */
	const VAR_NUMERATI = 'albo_pretorio_solo_numerati';

	/**
	 * Identificativo del messaggio sugli errori della ricerca.
	 *
	 * @var string
	 */
	const ID_ERRORE = 'albo-pretorio-errore-ricerca';

	/**
	 * Lo stesso codice con gli identificativi di un'altra istanza.
	 *
	 * Ogni identificativo, e ogni riferimento a un identificativo (etichette,
	 * descrizioni, titoli di regione), prende in coda il numero dell'istanza.
	 * Gli identificativi li scrive solo questa classe, e solo in questi
	 * quattro attributi; i nomi dei campi non cambiano, quindi il modulo manda
	 * la stessa ricerca (A-207).
	 *
	 * @param string $html     Codice disegnato.
	 * @param int    $istanza  Numero dell'istanza, dal secondo.
	 * @return string
	 */
	public static function distingui( string $html, int $istanza ): string {
		return (string) preg_replace_callback(
			'/ (id|for|aria-describedby|aria-labelledby)="([^"]*)"/',
			static function ( array $parti ) use ( $istanza ): string {
				$voci = preg_split( '/\s+/', trim( $parti[2] ), -1, PREG_SPLIT_NO_EMPTY );
				$voci = array_map(
					static function ( string $voce ) use ( $istanza ): string {
						return $voce . '-' . $istanza;
					},
					false === $voci ? array() : $voci
				);

				return ' ' . $parti[1] . '="' . esc_attr( implode( ' ', $voci ) ) . '"';
			},
			$html
		);
	}

	/**
	 * La pagina, per la richiesta indicata.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @param \DateTimeImmutable   $adesso    Istante della richiesta.
	 * @return string
	 */
	public static function disegna( array $richiesta, \DateTimeImmutable $adesso ): string {
		$atto = PaginaPubblica::atto_chiesto( $richiesta );

		if ( false !== $atto ) {
			// Uno non in pubblicazione e' gia' stato rifiutato con la pagina "non trovata": qui e' un doppio controllo.
			return PaginaPubblica::in_pubblicazione( $atto ) ? self::scheda( $atto ) : '';
		}

		$ricerca = self::ricerca( $richiesta );
		$esito   = self::trovati( $ricerca, $adesso );

		return '<div class="albo-pretorio">' . self::modulo( $ricerca ) . self::risultati( $ricerca, $esito ) . '</div>';
	}

	/**
	 * La ricerca chiesta, ripulita.
	 *
	 * Un campo vuoto non restringe; un campo scritto male non si scarta in
	 * silenzio, perche' l'elenco intero passerebbe per il risultato della
	 * ricerca: si segnala, e la ricerca non si fa.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return array{testo: string, anno: int|null, numero: int|null, tipo: int|null, organo: int|null, dal: string, al: string, pagina: int, attiva: bool, errori: array<string, string>}
	 */
	public static function ricerca( array $richiesta ): array {
		$errori = array();

		/*
		 * Si guarda prima il valore com'e' arrivato, e solo dopo lo si ripulisce:
		 * ripulito, un valore fatto di soli segni come `<b></b>` diventerebbe
		 * un campo vuoto, e un campo vuoto non restringe, quindi la pagina
		 * mostrerebbe l'elenco intero al posto del rifiuto (A-199). Vuoto e'
		 * solo cio' che e' vuoto gia' prima. I campi rigidi (numeri e date) non
		 * si ripuliscono affatto: si controllano cosi' come sono, e un valore
		 * fuori dalla forma e' un errore.
		 */
		$testo_di = static function ( string $campo, bool $rigido = false ) use ( $richiesta, &$errori ): string {
			if ( ! isset( $richiesta[ $campo ] ) ) {
				return '';
			}

			if ( ! is_scalar( $richiesta[ $campo ] ) ) {
				$errori[ $campo ] = '';
				return '';
			}

			$grezzo = trim( wp_unslash( (string) $richiesta[ $campo ] ) );

			if ( $rigido || '' === $grezzo ) {
				return $grezzo;
			}

			$pulito = trim( sanitize_text_field( $grezzo ) );

			if ( '' === $pulito ) {
				$errori[ $campo ] = '';
			}

			return $pulito;
		};

		$voce = static function ( string $campo, string $tassonomia ) use ( $testo_di, &$errori ): ?int {
			$testo = $testo_di( $campo, true );

			if ( '' === $testo ) {
				return null;
			}

			if ( 1 === preg_match( '/^[1-9][0-9]{0,9}$/', $testo ) ) {
				$termine = get_term( (int) $testo, $tassonomia );

				if ( $termine instanceof \WP_Term ) {
					return (int) $termine->term_id;
				}
			}

			$errori[ $campo ] = '';

			return null;
		};

		$data = static function ( string $campo ) use ( $testo_di, &$errori ): string {
			$testo = $testo_di( $campo, true );

			if ( '' === $testo ) {
				return '';
			}

			/*
			 * Prima la forma, poi la lettura: un valore con un carattere nullo
			 * dentro fa sollevare un'eccezione alla lettura della data, e la
			 * pagina si interromperebbe invece di dire che la ricerca e' scritta
			 * male (A-199).
			 */
			if ( 1 !== preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $testo ) ) {
				$errori[ $campo ] = '';

				return '';
			}

			$letta = \DateTimeImmutable::createFromFormat( '!Y-m-d', $testo, wp_timezone() );

			if ( false !== $letta && $letta->format( 'Y-m-d' ) === $testo ) {
				return $testo;
			}

			$errori[ $campo ] = '';

			return '';
		};

		$testo  = $testo_di( self::CAMPO_TESTO );
		$anno   = null;
		$numero = null;

		// "71/2026" e' un numero di registro, non una parola dell'oggetto.
		if ( 1 === preg_match( '#^([0-9]{1,10})\s*/\s*([0-9]{4})$#', $testo, $parti ) ) {
			$numero = (int) $parti[1];
			$anno   = (int) $parti[2];

			if ( $numero < 1 || $numero > Repertorio::MASSIMO || $anno < 1 ) {
				$errori[ self::CAMPO_TESTO ] = '';
			}
		}

		$pagina = $testo_di( self::CAMPO_PAGINA, true );

		if ( '' !== $pagina && 1 !== preg_match( '/^[1-9][0-9]{0,8}$/', $pagina ) ) {
			$errori[ self::CAMPO_PAGINA ] = '';
		}

		$ricerca = array(
			'testo'  => $testo,
			'anno'   => $anno,
			'numero' => $numero,
			'tipo'   => $voce( self::CAMPO_TIPO, TASSONOMIA_TIPO_ATTO ),
			'organo' => $voce( self::CAMPO_ORGANO, TASSONOMIA_ORGANO ),
			'dal'    => $data( self::CAMPO_DAL ),
			'al'     => $data( self::CAMPO_AL ),
			'pagina' => '' === $pagina || isset( $errori[ self::CAMPO_PAGINA ] ) ? 1 : (int) $pagina,
			'attiva' => false,
			'errori' => array(),
		);

		if ( '' !== $ricerca['dal'] && '' !== $ricerca['al'] && $ricerca['dal'] > $ricerca['al'] ) {
			$errori[ self::CAMPO_AL ] = '';
		}

		$ricerca['attiva'] = '' !== $testo || null !== $ricerca['tipo'] || null !== $ricerca['organo'] || '' !== $ricerca['dal'] || '' !== $ricerca['al'] || array() !== array_diff_key( $errori, array( self::CAMPO_PAGINA => true ) );

		foreach ( array_keys( $errori ) as $campo ) {
			$ricerca['errori'][ $campo ] = self::spiegazione( $campo );
		}

		return $ricerca;
	}

	/**
	 * Cosa c'e' di sbagliato in un campo, detto a chi l'ha scritto.
	 *
	 * @param string $campo Campo.
	 * @return string
	 */
	private static function spiegazione( string $campo ): string {
		switch ( $campo ) {
			case self::CAMPO_TESTO:
				return __( 'Il numero di registro si scrive come numero e anno separati da una barra, per esempio 71/2026.', 'albo-pretorio-pa' );
			case self::CAMPO_TIPO:
				return __( 'Il tipo di atto scelto non esiste: sceglierne uno dall\'elenco.', 'albo-pretorio-pa' );
			case self::CAMPO_ORGANO:
				return __( 'L\'organo scelto non esiste: sceglierne uno dall\'elenco.', 'albo-pretorio-pa' );
			case self::CAMPO_DAL:
				return __( 'La data "pubblicati dal" non è valida: si scrive come giorno, mese e anno.', 'albo-pretorio-pa' );
			case self::CAMPO_AL:
				return __( 'La data "pubblicati fino al" non è valida, oppure viene prima della data "pubblicati dal".', 'albo-pretorio-pa' );
			default:
				return __( 'La pagina chiesta non è valida.', 'albo-pretorio-pa' );
		}
	}

	/**
	 * Gli atti in pubblicazione che rispondono alla ricerca, una pagina alla volta.
	 *
	 * Ordinati dalla data di inizio della pubblicazione, il piu' recente per
	 * primo. Ogni atto trovato si ricontrolla con `PaginaPubblica`, che
	 * guarda stato, numero di registro e scadenza.
	 *
	 * @param array<string, mixed> $ricerca Ricerca ripulita.
	 * @param \DateTimeImmutable   $adesso  Istante della richiesta.
	 * @return array{atti: array<int, int>, totale: int}|\WP_Error
	 */
	public static function trovati( array $ricerca, \DateTimeImmutable $adesso ) {
		if ( array() !== $ricerca['errori'] ) {
			return new \WP_Error( 'albo_ricerca_campo_non_valido', implode( ' ', $ricerca['errori'] ) );
		}

		$oggi = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		$argomenti = array(
			'post_type'           => TIPO,
			'post_status'         => PaginaPubblica::STATI_IN_VISTA,
			'has_password'        => false,
			'posts_per_page'      => self::PER_PAGINA,
			'paged'               => max( 1, (int) $ricerca['pagina'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- la scadenza e' la data di fine.
				array(
					'key'     => conformita_core_chiave_fine_pubblicazione(),
					'value'   => $oggi,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
			self::VAR_NUMERATI    => true,
		);

		if ( null !== $ricerca['numero'] ) {
			$numerati = Repertorio::atti_numerati( (int) $ricerca['anno'], (int) $ricerca['numero'] );

			if ( null === $numerati ) {
				return new \WP_Error( 'albo_ricerca_lettura', __( 'La ricerca per numero di registro non è disponibile in questo momento. Riprovare più tardi, oppure cercare per oggetto.', 'albo-pretorio-pa' ) );
			}

			if ( array() === $numerati ) {
				return array(
					'atti'   => array(),
					'totale' => 0,
				);
			}

			$argomenti['post__in'] = $numerati;
		} elseif ( '' !== $ricerca['testo'] ) {
			$argomenti['s']              = $ricerca['testo'];
			$argomenti['search_columns'] = array( 'post_title' );
		}

		$voci = array();

		foreach ( array(
			TASSONOMIA_TIPO_ATTO => $ricerca['tipo'],
			TASSONOMIA_ORGANO    => $ricerca['organo'],
		) as $tassonomia => $voce ) {
			if ( null !== $voce ) {
				$voci[] = array(
					'taxonomy' => $tassonomia,
					'field'    => 'term_id',
					'terms'    => array( (int) $voce ),
				);
			}
		}

		if ( array() !== $voci ) {
			$argomenti['tax_query'] = array_merge( array( 'relation' => 'AND' ), $voci ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- ricerca per tipo e organo.
		}

		$intervallo = array( 'inclusive' => true );

		if ( '' !== $ricerca['dal'] ) {
			$intervallo['after'] = $ricerca['dal'] . ' 00:00:00';
		}

		if ( '' !== $ricerca['al'] ) {
			$intervallo['before'] = $ricerca['al'] . ' 23:59:59';
		}

		if ( count( $intervallo ) > 1 ) {
			$argomenti['date_query'] = array( $intervallo );
		}

		$interrogazione = new \WP_Query( $argomenti );

		$atti = array();

		foreach ( $interrogazione->posts as $atto ) {
			$id = $atto instanceof \WP_Post ? (int) $atto->ID : (int) $atto;

			if ( PaginaPubblica::in_pubblicazione( $id ) ) {
				$atti[] = $id;
			}
		}

		$totale = (int) $interrogazione->found_posts;

		/*
		 * Oltre l'ultima pagina WordPress non conta niente: senza atti nella
		 * pagina il totale resta zero. Si conta di nuovo dalla prima, cosi'
		 * una pagina che non c'e' piu', per esempio salvata prima che un atto
		 * scadesse, non si legge come un albo vuoto (A-208).
		 */
		if ( array() === $interrogazione->posts && $argomenti['paged'] > 1 ) {
			$conta  = new \WP_Query(
				array_merge(
					$argomenti,
					array(
						'paged'          => 1,
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				)
			);
			$totale = (int) $conta->found_posts;
		}

		return array(
			'atti'   => $atti,
			'totale' => $totale,
		);
	}

	/**
	 * Solo gli atti che hanno il numero di registro, nell'interrogazione dell'elenco.
	 *
	 * Senza, un atto pubblicato scavalcando il passaggio sarebbe tolto dal
	 * ricontrollo ma contato nel totale, e le pagine dell'elenco salterebbero.
	 * Agganciato sempre, agisce solo sull'interrogazione che porta la variabile.
	 *
	 * @param mixed $condizione     Condizione costruita da WordPress.
	 * @param mixed $interrogazione Interrogazione.
	 * @return mixed
	 */
	public static function da_posts_where( $condizione, $interrogazione = null ) {
		global $wpdb;

		if ( ! is_string( $condizione ) || ! $interrogazione instanceof \WP_Query || true !== $interrogazione->get( self::VAR_NUMERATI ) ) {
			return $condizione;
		}

		return $condizione . $wpdb->prepare( " AND EXISTS ( SELECT 1 FROM %i AS albo_numeri WHERE albo_numeri.atto_id = {$wpdb->posts}.ID )", Repertorio::tabella_assegnazioni() );
	}

	/**
	 * Il modulo della ricerca.
	 *
	 * Metodo GET verso la pagina stessa: la ricerca e' un indirizzo che si
	 * puo' copiare e riaprire. Con gli indirizzi semplici la pagina si
	 * riconosce da un parametro (`page_id=`), che il modulo riporta.
	 *
	 * @param array<string, mixed> $ricerca Ricerca ripulita.
	 * @return string
	 */
	private static function modulo( array $ricerca ): string {
		$base   = (string) PaginaPubblica::indirizzo();
		$azione = strtok( $base, '?' );
		$errori = $ricerca['errori'];

		$html  = '<section class="albo-pretorio__ricerca" aria-labelledby="albo-pretorio-titolo-ricerca">';
		$html .= '<h2 id="albo-pretorio-titolo-ricerca" class="albo-pretorio__titolo">' . esc_html__( 'Cerca negli atti', 'albo-pretorio-pa' ) . '</h2>';
		$html .= '<form class="albo-pretorio__modulo" role="search" method="get" action="' . esc_url( false === $azione ? $base : $azione ) . '" aria-labelledby="albo-pretorio-titolo-ricerca">';

		$query = (string) wp_parse_url( $base, PHP_URL_QUERY );
		parse_str( $query, $fissi );

		foreach ( $fissi as $nome => $valore ) {
			if ( is_string( $nome ) && is_scalar( $valore ) ) {
				$html .= '<input type="hidden" name="' . esc_attr( $nome ) . '" value="' . esc_attr( (string) $valore ) . '" />';
			}
		}

		$descrizione = static function ( string $campo, string $aiuto = '' ) use ( $errori ): string {
			$descritto = array();

			if ( '' !== $aiuto ) {
				$descritto[] = $campo . '-aiuto';
			}

			if ( isset( $errori[ $campo ] ) ) {
				$descritto[] = self::ID_ERRORE;
			}

			$attributi = array() === $descritto ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $descritto ) ) . '"';

			return $attributi . ( isset( $errori[ $campo ] ) ? ' aria-invalid="true"' : '' );
		};

		$html .= '<div class="albo-pretorio__campi">';

		$html .= '<div class="albo-pretorio__campo albo-pretorio__campo--largo">';
		$html .= '<label for="' . esc_attr( self::CAMPO_TESTO ) . '">' . esc_html__( 'Oggetto o numero di registro', 'albo-pretorio-pa' ) . '</label>';
		$html .= '<input type="text" id="' . esc_attr( self::CAMPO_TESTO ) . '" name="' . esc_attr( self::CAMPO_TESTO ) . '" value="' . esc_attr( $ricerca['testo'] ) . '"' . $descrizione( self::CAMPO_TESTO, 'aiuto' ) . ' />';
		$html .= '<p id="' . esc_attr( self::CAMPO_TESTO ) . '-aiuto" class="albo-pretorio__aiuto">' . esc_html__( 'Una o più parole dell\'oggetto, oppure il numero di registro con l\'anno, per esempio 71/2026.', 'albo-pretorio-pa' ) . '</p>';
		$html .= '</div>';

		$html .= self::scelta( self::CAMPO_TIPO, __( 'Tipo di atto', 'albo-pretorio-pa' ), __( 'Tutti i tipi', 'albo-pretorio-pa' ), TASSONOMIA_TIPO_ATTO, $ricerca['tipo'], $descrizione( self::CAMPO_TIPO ) );
		$html .= self::scelta( self::CAMPO_ORGANO, __( 'Organo', 'albo-pretorio-pa' ), __( 'Tutti gli organi', 'albo-pretorio-pa' ), TASSONOMIA_ORGANO, $ricerca['organo'], $descrizione( self::CAMPO_ORGANO ) );

		foreach ( array(
			self::CAMPO_DAL => __( 'Pubblicati dal', 'albo-pretorio-pa' ),
			self::CAMPO_AL  => __( 'Pubblicati fino al', 'albo-pretorio-pa' ),
		) as $campo => $etichetta ) {
			$html .= '<div class="albo-pretorio__campo">';
			$html .= '<label for="' . esc_attr( $campo ) . '">' . esc_html( $etichetta ) . '</label>';
			$html .= '<input type="date" id="' . esc_attr( $campo ) . '" name="' . esc_attr( $campo ) . '" value="' . esc_attr( self::CAMPO_DAL === $campo ? $ricerca['dal'] : $ricerca['al'] ) . '"' . $descrizione( $campo ) . ' />';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '<div class="albo-pretorio__pulsanti">';
		$html .= '<button type="submit" class="albo-pretorio__pulsante">' . esc_html__( 'Cerca', 'albo-pretorio-pa' ) . '</button>';

		if ( $ricerca['attiva'] ) {
			$html .= '<a class="albo-pretorio__collegamento" href="' . esc_url( $base ) . '">' . esc_html__( 'Mostra tutti gli atti in pubblicazione', 'albo-pretorio-pa' ) . '</a>';
		}

		$html .= '</div></form></section>';

		return $html;
	}

	/**
	 * Un elenco a scelta fra le voci di un elenco di voci dell'albo.
	 *
	 * @param string   $campo       Campo.
	 * @param string   $etichetta   Etichetta.
	 * @param string   $tutti       Voce che non restringe.
	 * @param string   $tassonomia  Elenco di voci.
	 * @param int|null $scelta      Voce scelta.
	 * @param string   $descrizione Attributi di descrizione, gia' sfuggiti.
	 * @return string
	 */
	private static function scelta( string $campo, string $etichetta, string $tutti, string $tassonomia, ?int $scelta, string $descrizione ): string {
		$voci = get_terms(
			array(
				'taxonomy'   => $tassonomia,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		$html  = '<div class="albo-pretorio__campo">';
		$html .= '<label for="' . esc_attr( $campo ) . '">' . esc_html( $etichetta ) . '</label>';
		$html .= '<select id="' . esc_attr( $campo ) . '" name="' . esc_attr( $campo ) . '"' . $descrizione . '>';
		$html .= '<option value="">' . esc_html( $tutti ) . '</option>';

		foreach ( is_array( $voci ) ? $voci : array() as $voce ) {
			if ( ! $voce instanceof \WP_Term ) {
				continue;
			}

			$html .= '<option value="' . (int) $voce->term_id . '"' . selected( $scelta, (int) $voce->term_id, false ) . '>' . esc_html( $voce->name ) . '</option>';
		}

		return $html . '</select></div>';
	}

	/**
	 * Il titolo, il conteggio, l'elenco e le pagine.
	 *
	 * @param array<string, mixed>                                $ricerca Ricerca ripulita.
	 * @param array{atti: array<int, int>, totale: int}|\WP_Error $esito   Atti trovati.
	 * @return string
	 */
	private static function risultati( array $ricerca, $esito ): string {
		$titolo = $ricerca['attiva'] ? __( 'Risultati della ricerca', 'albo-pretorio-pa' ) : __( 'Atti in pubblicazione', 'albo-pretorio-pa' );

		$html  = '<section class="albo-pretorio__risultati" aria-labelledby="albo-pretorio-titolo-elenco">';
		$html .= '<h2 id="albo-pretorio-titolo-elenco" class="albo-pretorio__titolo">' . esc_html( $titolo ) . '</h2>';

		if ( is_wp_error( $esito ) ) {
			$html .= '<p id="' . esc_attr( self::ID_ERRORE ) . '" class="albo-pretorio__avviso" role="alert">' . esc_html__( 'La ricerca non è stata fatta.', 'albo-pretorio-pa' ) . ' ' . esc_html( $esito->get_error_message() ) . '</p>';

			return $html . '</section>';
		}

		if ( array() === $esito['atti'] && $esito['totale'] > 0 ) {
			$html .= '<p class="albo-pretorio__conteggio">' . esc_html(
				sprintf(
					/* translators: %s: numero degli atti. */
					_n( 'In questa pagina dell\'elenco non ci sono atti: l\'elenco ne ha %s.', 'In questa pagina dell\'elenco non ci sono atti: l\'elenco ne ha %s.', $esito['totale'], 'albo-pretorio-pa' ),
					number_format_i18n( $esito['totale'] )
				)
			) . '</p>';
			$html .= '<p class="albo-pretorio__torna"><a class="albo-pretorio__collegamento" href="' . esc_url( add_query_arg( self::parametri( $ricerca ), (string) PaginaPubblica::indirizzo() ) ) . '">' . esc_html__( 'Vai alla prima pagina', 'albo-pretorio-pa' ) . '</a></p>';

			return $html . self::pagine( $ricerca, $esito['totale'] ) . '</section>';
		}

		if ( array() === $esito['atti'] ) {
			$vuoto = $ricerca['attiva'] ? __( 'Nessun atto in pubblicazione risponde alla ricerca.', 'albo-pretorio-pa' ) : __( 'In questo momento non ci sono atti in pubblicazione.', 'albo-pretorio-pa' );

			return $html . '<p class="albo-pretorio__conteggio">' . esc_html( $vuoto ) . '</p></section>';
		}

		$html .= '<p class="albo-pretorio__conteggio">' . esc_html(
			sprintf(
				/* translators: %s: numero degli atti. */
				_n( '%s atto', '%s atti', $esito['totale'], 'albo-pretorio-pa' ),
				number_format_i18n( $esito['totale'] )
			)
		) . '</p>';

		$html .= self::tabella( $esito['atti'] );

		return $html . self::pagine( $ricerca, $esito['totale'] ) . '</section>';
	}

	/**
	 * Le colonne della tabella, nell'ordine.
	 *
	 * @return array<string, string> Nome della colonna per chiave.
	 */
	private static function colonne(): array {
		return array(
			'numero'    => __( 'Numero', 'albo-pretorio-pa' ),
			'tipo'      => __( 'Tipo', 'albo-pretorio-pa' ),
			'oggetto'   => __( 'Oggetto', 'albo-pretorio-pa' ),
			'dal'       => __( 'Pubblicato dal', 'albo-pretorio-pa' ),
			'al'        => __( 'Fino al', 'albo-pretorio-pa' ),
			'documenti' => __( 'Documenti', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * La tabella degli atti.
	 *
	 * Ogni elemento dichiara anche il suo ruolo: su schermo stretto il foglio
	 * di stile cambia la disposizione delle celle, e alcuni browser, quando
	 * succede, smettono di presentare la tabella come tale ai lettori di
	 * schermo (A-218).
	 *
	 * @param array<int, int> $atti Atti, nell'ordine.
	 * @return string
	 */
	private static function tabella( array $atti ): string {
		$html  = '<table class="albo-pretorio__tabella" role="table" aria-labelledby="albo-pretorio-titolo-elenco">';
		$html .= '<thead class="albo-pretorio__testata" role="rowgroup"><tr role="row">';

		foreach ( self::colonne() as $nome ) {
			$html .= '<th class="albo-pretorio__intestazione" scope="col" role="columnheader">' . esc_html( $nome ) . '</th>';
		}

		$html .= '</tr></thead><tbody role="rowgroup">';

		foreach ( $atti as $atto ) {
			$html .= self::riga( $atto );
		}

		return $html . '</tbody></table>';
	}

	/**
	 * La riga di un atto.
	 *
	 * L'oggetto e' l'intestazione della riga. Davanti a ogni altro dato c'e'
	 * il nome della sua colonna, che si vede solo su schermo stretto e che i
	 * lettori di schermo saltano: per loro il nome e' gia' nell'intestazione.
	 *
	 * @param int $atto Atto.
	 * @return string
	 */
	private static function riga( int $atto ): string {
		$colonne   = self::colonne();
		$tipo      = DatiAtto::tipo( $atto );
		$organo    = DatiAtto::organo( $atto );
		$fine      = conformita_core_fine_pubblicazione( $atto );
		$annullato = PaginaPubblica::annullato( $atto );
		$quanti    = $annullato ? 0 : self::quanti_documenti( $atto );

		$oggetto = '<a class="albo-pretorio__collegamento" href="' . esc_url( (string) PaginaPubblica::indirizzo_scheda( $atto ) ) . '">' . esc_html( get_the_title( $atto ) ) . '</a>';

		if ( $annullato ) {
			$oggetto .= ' <strong class="albo-pretorio__annullato">' . esc_html__( 'Annullato', 'albo-pretorio-pa' ) . '</strong>';
		}

		$amministrazione = AmministrazioniOspitate::da_mostrare( $atto );

		if ( null !== $amministrazione ) {
			$oggetto .= ' <span class="albo-pretorio__amministrazione">' . esc_html(
				sprintf(
					/* translators: %s: amministrazione che ha adottato l'atto ospitato. */
					__( 'Atto di: %s', 'albo-pretorio-pa' ),
					$amministrazione
				)
			) . '</span>';
		}

		if ( null !== $organo ) {
			$oggetto .= ' <span class="albo-pretorio__organo">' . esc_html( $organo->name ) . '</span>';
		}

		$html  = '<tr class="albo-pretorio__atto" role="row" data-atto="' . (int) $atto . '">';
		$html .= self::cella( $colonne['numero'], esc_html( self::numero( $atto ) ), 'albo-pretorio__cella--numero' );
		$html .= self::cella( $colonne['tipo'], null === $tipo ? '' : esc_html( $tipo->name ) );
		$html .= '<th class="albo-pretorio__cella albo-pretorio__cella--oggetto" scope="row" role="rowheader">' . $oggetto . '</th>';
		$html .= self::cella( $colonne['dal'], esc_html( (string) get_post_time( 'd/m/Y', false, $atto ) ), 'albo-pretorio__cella--data' );
		$html .= self::cella( $colonne['al'], is_string( $fine ) ? esc_html( self::data( $fine ) ) : '', 'albo-pretorio__cella--data' );
		$html .= self::cella(
			$colonne['documenti'],
			esc_html(
				$annullato
					? __( 'nessuno: atto annullato', 'albo-pretorio-pa' )
					: sprintf(
						/* translators: %s: numero dei documenti dell'atto. */
						_n( '%s documento', '%s documenti', $quanti, 'albo-pretorio-pa' ),
						number_format_i18n( $quanti )
					)
			)
		);

		return $html . '</tr>';
	}

	/**
	 * Una cella con il nome della sua colonna davanti, per lo schermo stretto.
	 *
	 * @param string $colonna Nome della colonna, non sfuggito.
	 * @param string $valore  Valore, gia' sfuggito.
	 * @param string $classe  Classe in piu', facoltativa.
	 * @return string
	 */
	private static function cella( string $colonna, string $valore, string $classe = '' ): string {
		$classi = trim( 'albo-pretorio__cella ' . $classe );

		return '<td class="' . esc_attr( $classi ) . '" role="cell"><span class="albo-pretorio__etichetta" aria-hidden="true">' . esc_html( $colonna ) . '</span>' . $valore . '</td>';
	}

	/**
	 * Quanti documenti ha l'atto: il principale, se c'e', e gli allegati.
	 *
	 * @param int $atto Atto.
	 * @return int
	 */
	private static function quanti_documenti( int $atto ): int {
		return ( null === DocumentiAtto::principale( $atto ) ? 0 : 1 ) + count( DocumentiAtto::ulteriori( $atto ) );
	}

	/**
	 * La scheda di un atto in pubblicazione.
	 *
	 * Per un atto annullato e' l'avviso dell'annullamento: gli stessi dati
	 * dell'atto, con data e motivo dell'annullamento, senza i dati del
	 * procedimento, che possono nominare persone, e senza documenti (A-227).
	 *
	 * @param int $atto Atto.
	 * @return string
	 */
	private static function scheda( int $atto ): string {
		$annullato = PaginaPubblica::annullato( $atto );

		$html  = '<div class="albo-pretorio">';
		$html .= '<article class="albo-pretorio__scheda" aria-labelledby="albo-pretorio-titolo-atto" data-atto="' . (int) $atto . '">';
		$html .= '<p class="albo-pretorio__torna"><a class="albo-pretorio__collegamento" href="' . esc_url( (string) PaginaPubblica::indirizzo() ) . '">' . esc_html__( 'Torna all\'elenco degli atti', 'albo-pretorio-pa' ) . '</a></p>';
		$html .= '<h2 id="albo-pretorio-titolo-atto" class="albo-pretorio__titolo">' . esc_html( get_the_title( $atto ) ) . '</h2>';

		if ( $annullato ) {
			$html .= '<p class="albo-pretorio__avviso"><strong>' . esc_html__( 'Atto annullato. I suoi documenti non si consultano piu\'.', 'albo-pretorio-pa' ) . '</strong></p>';
		}

		$dati = array( __( 'Numero di registro', 'albo-pretorio-pa' ) => self::numero( $atto ) );
		$tipo = DatiAtto::tipo( $atto );

		if ( null !== $tipo ) {
			$dati[ __( 'Tipo', 'albo-pretorio-pa' ) ] = $tipo->name;
		}

		$amministrazione = AmministrazioniOspitate::da_mostrare( $atto );

		if ( null !== $amministrazione ) {
			$dati[ AmministrazioniOspitate::etichetta() ] = $amministrazione;
		}

		$organo = DatiAtto::organo( $atto );

		if ( null !== $organo ) {
			$dati[ __( 'Organo', 'albo-pretorio-pa' ) ] = $organo->name;
		}

		if ( '' !== DatiAtto::numero_proprio( $atto ) ) {
			$dati[ __( 'Numero dell\'atto', 'albo-pretorio-pa' ) ] = DatiAtto::numero_proprio( $atto );
		}

		$adozione = DatiAtto::data_adozione( $atto );

		if ( null !== $adozione ) {
			$dati[ __( 'Data di adozione', 'albo-pretorio-pa' ) ] = self::data( $adozione );
		}

		$fine = conformita_core_fine_pubblicazione( $atto );

		$dati[ __( 'Pubblicato dal', 'albo-pretorio-pa' ) ] = (string) get_post_time( 'd/m/Y', false, $atto );
		$dati[ __( 'Fino al', 'albo-pretorio-pa' ) ]        = is_string( $fine ) ? self::data( $fine ) : '';

		if ( $annullato ) {
			$voce = Passaggi::annullamento( $atto );

			$dati[ __( 'Annullato il', 'albo-pretorio-pa' ) ]              = null === $voce ? __( 'non leggibile in questo momento', 'albo-pretorio-pa' ) : wp_date( 'd/m/Y', $voce['istante']->getTimestamp() );
			$dati[ __( 'Motivo dell\'annullamento', 'albo-pretorio-pa' ) ] = null === $voce ? __( 'non leggibile in questo momento', 'albo-pretorio-pa' ) : $voce['motivo'];

			return $html . self::dati( $dati ) . '</article></div>';
		}

		foreach ( DatiProcedimento::da_mostrare( $atto ) as $etichetta => $valore ) {
			$dati[ $etichetta ] = $valore;
		}

		$html .= self::dati( $dati );
		$html .= '<h3 class="albo-pretorio__sottotitolo">' . esc_html__( 'Documenti', 'albo-pretorio-pa' ) . '</h3>';
		$html .= self::documenti( $atto );

		return $html . '</article></div>';
	}

	/**
	 * L'elenco dei documenti di un atto, dal punto pubblico di consegna.
	 *
	 * Senza il nome del file caricato, che puo' contenere nomi di persona:
	 * "Documento principale", "Allegato 1", con formato e dimensione.
	 *
	 * @param int $atto Atto.
	 * @return string
	 */
	private static function documenti( int $atto ): string {
		$documenti  = array();
		$principale = DocumentiAtto::principale( $atto );

		if ( null !== $principale ) {
			$documenti[ $principale ] = __( 'Documento principale', 'albo-pretorio-pa' );
		}

		$numero = 0;

		foreach ( DocumentiAtto::ulteriori( $atto ) as $allegato ) {
			++$numero;
			/* translators: %d: numero d'ordine dell'allegato. */
			$documenti[ $allegato ] = sprintf( __( 'Allegato %d', 'albo-pretorio-pa' ), $numero );
		}

		if ( array() === $documenti ) {
			return '<p class="albo-pretorio__conteggio">' . esc_html__( 'Nessun documento.', 'albo-pretorio-pa' ) . '</p>';
		}

		$html = '<ul class="albo-pretorio__documenti">';

		foreach ( $documenti as $documento => $nome ) {
			$indirizzo = conformita_core_indirizzo_consegna( $documento );
			$dettagli  = self::dettagli_file( $documento );

			$html .= '<li class="albo-pretorio__documento">';

			if ( is_string( $indirizzo ) && '' !== $indirizzo ) {
				$html .= '<a class="albo-pretorio__collegamento" href="' . esc_url( $indirizzo ) . '">' . esc_html( $nome ) . '</a>';
			} else {
				$html .= esc_html( $nome ) . ' ' . esc_html__( '(non disponibile)', 'albo-pretorio-pa' );
			}

			if ( '' !== $dettagli ) {
				$html .= ' <span class="albo-pretorio__dettagli">(' . esc_html( $dettagli ) . ')</span>';
			}

			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	/**
	 * Formato e dimensione di un documento, come "PDF, 245 KB".
	 *
	 * La dimensione e' quella registrata al deposito, non quella del disco.
	 *
	 * @param int $documento Allegato.
	 * @return string
	 */
	private static function dettagli_file( int $documento ): string {
		$parti = array();
		$tipo  = (string) get_post_mime_type( $documento );

		foreach ( wp_get_mime_types() as $estensioni => $mime ) {
			if ( $mime === $tipo ) {
				$parti[] = strtoupper( (string) strtok( $estensioni, '|' ) );
				break;
			}
		}

		$impronta = conformita_core_impronta_allegato( $documento );

		if ( is_array( $impronta ) && isset( $impronta['dimensione'] ) && is_numeric( $impronta['dimensione'] ) && (int) $impronta['dimensione'] > 0 ) {
			$parti[] = (string) size_format( (int) $impronta['dimensione'], 1 );
		}

		return implode( ', ', $parti );
	}

	/**
	 * Un gruppo di dati come elenco di termini e valori.
	 *
	 * @param array<string, string> $dati Dati per etichetta, non sfuggiti.
	 * @return string
	 */
	private static function dati( array $dati ): string {
		$html = '<dl class="albo-pretorio__dati">';

		foreach ( $dati as $etichetta => $valore ) {
			$html .= '<div class="albo-pretorio__dato"><dt>' . esc_html( (string) $etichetta ) . '</dt><dd>' . esc_html( (string) $valore ) . '</dd></div>';
		}

		return $html . '</dl>';
	}

	/**
	 * Il numero di registro, come 71/2026.
	 *
	 * @param int $atto Atto.
	 * @return string
	 */
	private static function numero( int $atto ): string {
		$numero = Repertorio::di( $atto );

		return null === $numero ? '' : $numero['numero'] . '/' . $numero['anno'];
	}

	/**
	 * I collegamenti alle pagine dell'elenco, con la ricerca conservata.
	 *
	 * @param array<string, mixed> $ricerca Ricerca ripulita.
	 * @param int                  $totale  Atti trovati in tutto.
	 * @return string
	 */
	private static function pagine( array $ricerca, int $totale ): string {
		$quante = (int) ceil( $totale / self::PER_PAGINA );

		if ( $quante < 2 ) {
			return '';
		}

		$parametri = self::parametri( $ricerca );
		$base      = (string) PaginaPubblica::indirizzo();
		$html      = '<nav class="albo-pretorio__pagine" aria-label="' . esc_attr__( 'Pagine dell\'elenco', 'albo-pretorio-pa' ) . '"><ul class="albo-pretorio__numeri">';

		for ( $pagina = 1; $pagina <= $quante; $pagina++ ) {
			/* translators: %d: numero della pagina. */
			$nome = sprintf( __( 'Pagina %d', 'albo-pretorio-pa' ), $pagina );

			$html .= '<li class="albo-pretorio__numero">';

			if ( $pagina === (int) $ricerca['pagina'] ) {
				$html .= '<a class="albo-pretorio__collegamento" aria-current="page" aria-label="' . esc_attr( $nome ) . '" href="' . esc_url( add_query_arg( array_merge( $parametri, array( self::CAMPO_PAGINA => $pagina ) ), $base ) ) . '">' . (int) $pagina . '</a>';
			} else {
				$html .= '<a class="albo-pretorio__collegamento" aria-label="' . esc_attr( $nome ) . '" href="' . esc_url( add_query_arg( array_merge( $parametri, array( self::CAMPO_PAGINA => $pagina ) ), $base ) ) . '">' . (int) $pagina . '</a>';
			}

			$html .= '</li>';
		}

		return $html . '</ul></nav>';
	}

	/**
	 * I parametri della ricerca, senza la pagina, per i collegamenti dell'elenco.
	 *
	 * @param array<string, mixed> $ricerca Ricerca ripulita.
	 * @return array<string, mixed>
	 */
	private static function parametri( array $ricerca ): array {
		return array_filter(
			array(
				self::CAMPO_TESTO  => '' === $ricerca['testo'] ? null : rawurlencode( $ricerca['testo'] ),
				self::CAMPO_TIPO   => $ricerca['tipo'],
				self::CAMPO_ORGANO => $ricerca['organo'],
				self::CAMPO_DAL    => '' === $ricerca['dal'] ? null : $ricerca['dal'],
				self::CAMPO_AL     => '' === $ricerca['al'] ? null : $ricerca['al'],
			),
			static function ( $valore ): bool {
				return null !== $valore;
			}
		);
	}

	/**
	 * Una data AAAA-MM-GG scritta come GG/MM/AAAA.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function data( string $data ): string {
		$letta = \DateTimeImmutable::createFromFormat( '!Y-m-d', $data, wp_timezone() );

		return false === $letta ? $data : $letta->format( 'd/m/Y' );
	}
}
