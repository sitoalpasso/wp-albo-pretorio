<?php
/**
 * Elenco e scheda della pagina pubblica dello storico (ALBO-36).
 *
 * Lo stesso disegno della pagina dell'albo, con le sue classi e il suo foglio
 * di stile. Le copie mostrate sono soltanto quelle in vista, ricontrollate una
 * per una con `PaginaStorico`. Di ogni copia si mostrano i dati fissati
 * all'ingresso, che identificano l'atto e non le persone, l'oggetto scritto da
 * chi l'ha messa e il documento oscurato: mai l'oggetto originale, mai il
 * documento originale.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Il disegno della pagina dello storico.
 */
final class VistaStorico {

	/**
	 * Il parametro della pagina dell'elenco.
	 *
	 * @var string
	 */
	const CAMPO_PAGINA = 'albo_copie_pagina';

	/**
	 * Copie per pagina.
	 *
	 * @var int
	 */
	const PER_PAGINA = 20;

	/**
	 * Il campo della ricerca.
	 *
	 * @var string
	 */
	const CAMPO_TESTO = 'albo_copie_testo';

	/**
	 * Identificativo del messaggio sull'errore della ricerca.
	 *
	 * @var string
	 */
	const ID_ERRORE = 'albo-pretorio-errore-ricerca';

	/**
	 * I caratteri piu' lunghi di una ricerca, prima della ripulitura.
	 *
	 * La pagina e' aperta a chiunque e la ricerca confronta ogni parola con
	 * l'oggetto di ogni copia: senza un tetto, un indirizzo di qualche migliaio
	 * di parole, ripetuto, farebbe lavorare il server a costo zero per chi lo
	 * manda. Duecento caratteri bastano per qualunque oggetto che si cerca
	 * davvero; oltre, la ricerca e' scritta male.
	 *
	 * @var int
	 */
	const LUNGHEZZA_MASSIMA = 200;

	/**
	 * Le parole piu' numerose di una ricerca, per la stessa ragione.
	 *
	 * @var int
	 */
	const PAROLE_MASSIME = 10;

	/**
	 * La pagina, per la richiesta indicata.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return string
	 */
	public static function disegna( array $richiesta ): string {
		$copia = PaginaStorico::copia_chiesta( $richiesta );

		if ( false !== $copia ) {
			// Una copia che non si mostra e' gia' stata rifiutata con la pagina "non trovata": qui e' un doppio controllo.
			return PaginaStorico::in_vista( $copia ) ? self::scheda( $copia ) : '';
		}

		return '<div class="albo-pretorio">' . self::elenco( $richiesta ) . '</div>';
	}

	/**
	 * Le copie in vista, dalla piu' recente per numero di registro dell'atto.
	 *
	 * Nulla se la lettura delle copie, o dei loro dati, non risponde: una
	 * copia non letta non sparisce in silenzio dall'elenco e dal conteggio.
	 *
	 * @return array<int, int>|null
	 */
	public static function copie(): ?array {
		$copie = self::copie_con_dati();

		return null === $copie ? null : array_keys( $copie );
	}

	/**
	 * Le copie in vista con i loro dati, nell'ordine dell'elenco.
	 *
	 * I dati sono quelli della lettura in blocco: la ricerca li usa senza
	 * rileggerli, cosi' una seconda lettura che fallisce non fa sparire una
	 * copia in silenzio.
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	private static function copie_con_dati(): ?array {
		$presenti = CopieStorico::presenti();
		$letture  = null === $presenti ? null : CopieStorico::letture( $presenti );

		if ( null === $presenti || null === $letture ) {
			return null;
		}

		$copie = array();

		foreach ( $presenti as $copia ) {
			$letto = $letture[ $copia ] ?? null;

			if ( null !== $letto && PaginaStorico::mostrabile( $copia, $letto ) ) {
				$copie[ $copia ] = $letto['dati'];
			}
		}

		uksort(
			$copie,
			static function ( int $prima, int $dopo ) use ( $copie ): int {
				return array( (int) $copie[ $dopo ]['anno'], (int) $copie[ $dopo ]['numero'], $dopo ) <=> array( (int) $copie[ $prima ]['anno'], (int) $copie[ $prima ]['numero'], $prima );
			}
		);

		return $copie;
	}

	/**
	 * L'avviso, la ricerca, il conteggio, l'elenco e le pagine.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return string
	 */
	private static function elenco( array $richiesta ): string {
		$ricerca = self::ricerca( $richiesta );
		$titolo  = $ricerca['attiva'] ? __( 'Risultati della ricerca', 'albo-pretorio-pa' ) : __( 'Copie per consultazione di atti non piu\' in pubblicazione', 'albo-pretorio-pa' );

		$html  = '<p class="albo-pretorio__avviso">' . esc_html__( 'Questi non sono gli atti dell\'albo: sono copie con i dati personali oscurati, messe a disposizione dopo la fine della pubblicazione. Il documento originale si chiede all\'amministrazione con l\'accesso agli atti.', 'albo-pretorio-pa' ) . '</p>';
		$html .= self::modulo( $ricerca );
		$html .= '<section class="albo-pretorio__risultati" aria-labelledby="albo-pretorio-titolo-elenco">';
		$html .= '<h2 id="albo-pretorio-titolo-elenco" class="albo-pretorio__titolo">' . esc_html( $titolo ) . '</h2>';

		if ( $ricerca['errore'] ) {
			return $html . '<p id="' . esc_attr( self::ID_ERRORE ) . '" class="albo-pretorio__avviso" role="alert">' . esc_html(
				sprintf(
					/* translators: 1: parole al massimo, 2: caratteri al massimo. */
					__( 'La ricerca non e\' stata fatta: scrivere una o piu\' parole dell\'oggetto, al massimo %1$d parole e %2$d caratteri, oppure il numero di registro con l\'anno, per esempio 71/2026.', 'albo-pretorio-pa' ),
					self::PAROLE_MASSIME,
					self::LUNGHEZZA_MASSIMA
				)
			) . '</p></section>';
		}

		$letti = self::copie_con_dati();

		if ( null === $letti ) {
			return $html . '<p class="albo-pretorio__avviso" role="alert">' . esc_html__( 'Le copie non si leggono in questo momento. Riprovare piu\' tardi.', 'albo-pretorio-pa' ) . '</p></section>';
		}

		$copie = self::trovate( $letti, $ricerca );

		if ( array() === $copie ) {
			$vuoto = $ricerca['attiva'] ? __( 'Nessuna copia risponde alla ricerca.', 'albo-pretorio-pa' ) : __( 'In questo momento non ci sono copie da consultare.', 'albo-pretorio-pa' );

			return $html . '<p class="albo-pretorio__conteggio">' . esc_html( $vuoto ) . '</p></section>';
		}

		$totale    = count( $copie );
		$quante    = (int) ceil( $totale / self::PER_PAGINA );
		$pagina    = self::pagina( $richiesta );
		$parametri = self::parametri( $ricerca );

		if ( null === $pagina || $pagina > $quante ) {
			$html .= '<p class="albo-pretorio__conteggio">' . esc_html__( 'Questa pagina dell\'elenco non esiste.', 'albo-pretorio-pa' ) . '</p>';
			$html .= '<p class="albo-pretorio__torna"><a class="albo-pretorio__collegamento" href="' . esc_url( add_query_arg( $parametri, (string) PaginaStorico::indirizzo() ) ) . '">' . esc_html__( 'Vai alla prima pagina', 'albo-pretorio-pa' ) . '</a></p>';

			return $html . self::pagine( $quante, 0, $parametri ) . '</section>';
		}

		$html .= '<p class="albo-pretorio__conteggio">' . esc_html(
			sprintf(
				/* translators: %s: numero delle copie. */
				_n( '%s copia', '%s copie', $totale, 'albo-pretorio-pa' ),
				number_format_i18n( $totale )
			)
		) . '</p>';

		$html .= self::tabella( array_slice( $copie, ( $pagina - 1 ) * self::PER_PAGINA, self::PER_PAGINA ), $letti );

		return $html . self::pagine( $quante, $pagina, $parametri ) . '</section>';
	}

	/**
	 * La ricerca chiesta, ripulita.
	 *
	 * La regola della ricerca dell'albo: il valore si guarda com'e' arrivato
	 * e solo dopo si ripulisce, e un valore che ripulito resta vuoto, o che
	 * non e' un testo, e' un errore, non una ricerca vuota che mostrerebbe
	 * l'elenco intero. "71/2026" e' un numero di registro.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return array{testo: string, parole: array<int, string>, anno: int|null, numero: int|null, attiva: bool, errore: bool}
	 */
	public static function ricerca( array $richiesta ): array {
		$ricerca = array(
			'testo'  => '',
			'parole' => array(),
			'anno'   => null,
			'numero' => null,
			'attiva' => false,
			'errore' => false,
		);

		if ( ! isset( $richiesta[ self::CAMPO_TESTO ] ) ) {
			return $ricerca;
		}

		$ricerca['attiva'] = true;

		if ( ! is_scalar( $richiesta[ self::CAMPO_TESTO ] ) ) {
			$ricerca['errore'] = true;

			return $ricerca;
		}

		$grezzo = trim( wp_unslash( (string) $richiesta[ self::CAMPO_TESTO ] ) );

		if ( '' === $grezzo ) {
			$ricerca['attiva'] = false;

			return $ricerca;
		}

		// Oltre il limite la ricerca non si fa: vedi `LUNGHEZZA_MASSIMA`.
		if ( mb_strlen( $grezzo ) > self::LUNGHEZZA_MASSIMA ) {
			$ricerca['errore'] = true;

			return $ricerca;
		}

		$testo = trim( sanitize_text_field( $grezzo ) );

		$ricerca['testo'] = $testo;

		if ( '' === $testo ) {
			$ricerca['errore'] = true;

			return $ricerca;
		}

		if ( 1 === preg_match( '#^([0-9]{1,10})\s*/\s*([0-9]{4})$#', $testo, $parti ) ) {
			$ricerca['numero'] = (int) $parti[1];
			$ricerca['anno']   = (int) $parti[2];
			$ricerca['errore'] = $ricerca['numero'] < 1 || $ricerca['numero'] > Repertorio::MASSIMO || $ricerca['anno'] < 1;

			return $ricerca;
		}

		$parole = preg_split( '/\s+/u', mb_strtolower( $testo ), -1, PREG_SPLIT_NO_EMPTY );

		$ricerca['parole'] = false === $parole ? array() : $parole;
		$ricerca['errore'] = array() === $ricerca['parole'] || count( $ricerca['parole'] ) > self::PAROLE_MASSIME;

		return $ricerca;
	}

	/**
	 * Le copie che rispondono alla ricerca, nell'ordine dell'elenco.
	 *
	 * Per numero, il numero di registro dell'atto; per parole, tutte le parole
	 * nell'oggetto della copia. Mai nell'oggetto originale dell'atto, che la
	 * copia non porta con se'.
	 *
	 * @param array<int, array<string, mixed>> $copie   Copie in vista con i loro dati gia' letti.
	 * @param array<string, mixed>             $ricerca Ricerca ripulita.
	 * @return array<int, int>
	 */
	private static function trovate( array $copie, array $ricerca ): array {
		if ( ! $ricerca['attiva'] ) {
			return array_keys( $copie );
		}

		return array_keys(
			array_filter(
				$copie,
				static function ( array $dati, int $copia ) use ( $ricerca ): bool {
					if ( null !== $ricerca['numero'] ) {
						return (int) $dati['numero'] === $ricerca['numero'] && (int) $dati['anno'] === $ricerca['anno'];
					}

					$oggetto = mb_strtolower( (string) get_post_field( 'post_title', $copia, 'raw' ) );

					foreach ( $ricerca['parole'] as $parola ) {
						if ( false === mb_strpos( $oggetto, $parola ) ) {
							return false;
						}
					}

					return true;
				},
				ARRAY_FILTER_USE_BOTH
			)
		);
	}

	/**
	 * Il modulo della ricerca.
	 *
	 * Come quello dell'albo: etichetta visibile, aiuto collegato al campo,
	 * errore annunciato e collegato, indirizzo della pagina con i suoi
	 * parametri fissi.
	 *
	 * @param array<string, mixed> $ricerca Ricerca ripulita.
	 * @return string
	 */
	private static function modulo( array $ricerca ): string {
		$base   = (string) PaginaStorico::indirizzo();
		$azione = strtok( $base, '?' );

		$html  = '<section class="albo-pretorio__ricerca" aria-labelledby="albo-pretorio-titolo-ricerca">';
		$html .= '<h2 id="albo-pretorio-titolo-ricerca" class="albo-pretorio__titolo">' . esc_html__( 'Cerca nelle copie', 'albo-pretorio-pa' ) . '</h2>';
		$html .= '<form class="albo-pretorio__modulo" role="search" method="get" action="' . esc_url( false === $azione ? $base : $azione ) . '" aria-labelledby="albo-pretorio-titolo-ricerca">';

		parse_str( (string) wp_parse_url( $base, PHP_URL_QUERY ), $fissi );

		foreach ( $fissi as $nome => $valore ) {
			if ( is_string( $nome ) && is_scalar( $valore ) ) {
				$html .= '<input type="hidden" name="' . esc_attr( $nome ) . '" value="' . esc_attr( (string) $valore ) . '" />';
			}
		}

		$descritto = self::CAMPO_TESTO . '-aiuto' . ( $ricerca['errore'] ? ' ' . self::ID_ERRORE : '' );

		$html .= '<div class="albo-pretorio__campi"><div class="albo-pretorio__campo albo-pretorio__campo--largo">';
		$html .= '<label for="' . esc_attr( self::CAMPO_TESTO ) . '">' . esc_html__( 'Oggetto o numero di registro', 'albo-pretorio-pa' ) . '</label>';
		$html .= '<input type="text" id="' . esc_attr( self::CAMPO_TESTO ) . '" name="' . esc_attr( self::CAMPO_TESTO ) . '" value="' . esc_attr( $ricerca['testo'] ) . '" aria-describedby="' . esc_attr( $descritto ) . '"' . ( $ricerca['errore'] ? ' aria-invalid="true"' : '' ) . ' />';
		$html .= '<p id="' . esc_attr( self::CAMPO_TESTO ) . '-aiuto" class="albo-pretorio__aiuto">' . esc_html__( 'Una o piu\' parole dell\'oggetto della copia, oppure il numero di registro con l\'anno, per esempio 71/2026.', 'albo-pretorio-pa' ) . '</p>';
		$html .= '</div></div>';
		$html .= '<div class="albo-pretorio__pulsanti"><button type="submit" class="albo-pretorio__pulsante">' . esc_html__( 'Cerca', 'albo-pretorio-pa' ) . '</button>';

		if ( $ricerca['attiva'] ) {
			$html .= '<a class="albo-pretorio__collegamento" href="' . esc_url( $base ) . '">' . esc_html__( 'Mostra tutte le copie', 'albo-pretorio-pa' ) . '</a>';
		}

		return $html . '</div></form></section>';
	}

	/**
	 * I parametri della ricerca, per i collegamenti alle pagine dell'elenco.
	 *
	 * @param array<string, mixed> $ricerca Ricerca ripulita.
	 * @return array<string, string>
	 */
	private static function parametri( array $ricerca ): array {
		return $ricerca['attiva'] && '' !== $ricerca['testo'] ? array( self::CAMPO_TESTO => rawurlencode( $ricerca['testo'] ) ) : array();
	}

	/**
	 * La pagina chiesta: uno se non la chiede, nullo se e' scritta male.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return int|null
	 */
	private static function pagina( array $richiesta ): ?int {
		if ( ! array_key_exists( self::CAMPO_PAGINA, $richiesta ) ) {
			return 1;
		}

		$valore = $richiesta[ self::CAMPO_PAGINA ];
		$testo  = is_string( $valore ) || is_int( $valore ) ? (string) $valore : '';

		return 1 === preg_match( '/^[1-9][0-9]{0,8}$/D', $testo ) ? (int) $testo : null;
	}

	/**
	 * Le colonne della tabella, nell'ordine.
	 *
	 * @return array<string, string>
	 */
	private static function colonne(): array {
		return array(
			'numero'  => __( 'Numero', 'albo-pretorio-pa' ),
			'tipo'    => __( 'Tipo', 'albo-pretorio-pa' ),
			'oggetto' => __( 'Oggetto', 'albo-pretorio-pa' ),
			'albo'    => __( 'All\'albo', 'albo-pretorio-pa' ),
			'fino'    => __( 'Consultabile fino al', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * La tabella delle copie, con i ruoli dichiarati come quella dell'albo.
	 *
	 * @param array<int, int>                  $copie Copie, nell'ordine.
	 * @param array<int, array<string, mixed>> $letti Dati delle copie, dalla lettura in blocco.
	 * @return string
	 */
	private static function tabella( array $copie, array $letti ): string {
		$colonne = self::colonne();
		$html    = '<table class="albo-pretorio__tabella" role="table" aria-labelledby="albo-pretorio-titolo-elenco">';
		$html   .= '<thead class="albo-pretorio__testata" role="rowgroup"><tr role="row">';

		foreach ( $colonne as $nome ) {
			$html .= '<th class="albo-pretorio__intestazione" scope="col" role="columnheader">' . esc_html( $nome ) . '</th>';
		}

		$html .= '</tr></thead><tbody role="rowgroup">';

		foreach ( $copie as $copia ) {
			$dati = $letti[ $copia ];
			$fine = conformita_core_fine_pubblicazione( $copia );

			$oggetto = '<a class="albo-pretorio__collegamento" href="' . esc_url( (string) PaginaStorico::indirizzo_scheda( $copia ) ) . '">' . esc_html( get_the_title( $copia ) ) . '</a>';

			$html .= '<tr class="albo-pretorio__atto" role="row" data-copia="' . (int) $copia . '">';
			$html .= VistaPubblica::cella( $colonne['numero'], esc_html( self::numero( $dati ) ), 'albo-pretorio__cella--numero' );
			$html .= VistaPubblica::cella( $colonne['tipo'], esc_html( (string) ( $dati['tipo'] ?? '' ) ) );
			$html .= '<th class="albo-pretorio__cella albo-pretorio__cella--oggetto" scope="row" role="rowheader">' . $oggetto . '</th>';
			$html .= VistaPubblica::cella( $colonne['albo'], esc_html( self::periodo( $dati ) ), 'albo-pretorio__cella--data' );
			$html .= VistaPubblica::cella( $colonne['fino'], is_string( $fine ) ? esc_html( VistaPubblica::data( $fine ) ) : '', 'albo-pretorio__cella--data' );
			$html .= '</tr>';
		}

		return $html . '</tbody></table>';
	}

	/**
	 * La scheda di una copia in vista.
	 *
	 * @param int $copia Copia.
	 * @return string
	 */
	private static function scheda( int $copia ): string {
		$dati      = (array) CopieStorico::dati( $copia );
		$fine_albo = VistaPubblica::data( (string) ( $dati['fine'] ?? '' ) );
		$fine      = conformita_core_fine_pubblicazione( $copia );

		$html  = '<div class="albo-pretorio">';
		$html .= '<article class="albo-pretorio__scheda" aria-labelledby="albo-pretorio-titolo-atto" data-copia="' . (int) $copia . '">';
		$html .= '<p class="albo-pretorio__torna"><a class="albo-pretorio__collegamento" href="' . esc_url( (string) PaginaStorico::indirizzo() ) . '">' . esc_html__( 'Torna all\'elenco delle copie', 'albo-pretorio-pa' ) . '</a></p>';
		$html .= '<h2 id="albo-pretorio-titolo-atto" class="albo-pretorio__titolo">' . esc_html( get_the_title( $copia ) ) . '</h2>';
		$html .= '<p class="albo-pretorio__avviso"><strong>' . esc_html(
			sprintf(
				/* translators: %s: data di fine della pubblicazione all'albo, come 16/10/2041. */
				__( 'Copia per consultazione: la pubblicazione all\'albo e\' terminata il %s.', 'albo-pretorio-pa' ),
				$fine_albo
			)
		) . '</strong> ' . esc_html__( 'Il documento e\' una copia con i dati personali oscurati. L\'originale si chiede all\'amministrazione con l\'accesso agli atti.', 'albo-pretorio-pa' ) . '</p>';

		$voci = array( __( 'Numero di registro', 'albo-pretorio-pa' ) => self::numero( $dati ) );

		foreach ( array(
			'tipo'            => __( 'Tipo', 'albo-pretorio-pa' ),
			'amministrazione' => AmministrazioniOspitate::etichetta(),
			'organo'          => __( 'Organo', 'albo-pretorio-pa' ),
			'numero_proprio'  => __( 'Numero dell\'atto', 'albo-pretorio-pa' ),
		) as $chiave => $etichetta ) {
			if ( '' !== (string) ( $dati[ $chiave ] ?? '' ) ) {
				$voci[ $etichetta ] = (string) $dati[ $chiave ];
			}
		}

		if ( '' !== (string) ( $dati['data_adozione'] ?? '' ) ) {
			$voci[ __( 'Data di adozione', 'albo-pretorio-pa' ) ] = VistaPubblica::data( (string) $dati['data_adozione'] );
		}

		$voci[ __( 'Pubblicato all\'albo dal', 'albo-pretorio-pa' ) ] = VistaPubblica::data( (string) ( $dati['inizio'] ?? '' ) );
		$voci[ __( 'Fino al', 'albo-pretorio-pa' ) ]                  = $fine_albo;
		$voci[ __( 'Consultabile qui fino al', 'albo-pretorio-pa' ) ] = is_string( $fine ) ? VistaPubblica::data( $fine ) : '';

		$html .= VistaPubblica::dati( $voci );
		$html .= '<h3 class="albo-pretorio__sottotitolo">' . esc_html__( 'Documento', 'albo-pretorio-pa' ) . '</h3>';
		$html .= self::documento( $copia );

		return $html . '</article></div>';
	}

	/**
	 * Il documento oscurato della copia, dal punto pubblico di consegna.
	 *
	 * @param int $copia Copia.
	 * @return string
	 */
	private static function documento( int $copia ): string {
		$documento = (int) CopieStorico::documento( $copia );
		$indirizzo = conformita_core_indirizzo_consegna( $documento );
		$dettagli  = VistaPubblica::dettagli_file( $documento );
		$nome      = __( 'Copia oscurata del documento', 'albo-pretorio-pa' );

		$html = '<ul class="albo-pretorio__documenti"><li class="albo-pretorio__documento">';

		if ( is_string( $indirizzo ) && '' !== $indirizzo ) {
			$html .= '<a class="albo-pretorio__collegamento" href="' . esc_url( $indirizzo ) . '">' . esc_html( $nome ) . '</a>';
		} else {
			$html .= esc_html( $nome ) . ' ' . esc_html__( '(non disponibile)', 'albo-pretorio-pa' );
		}

		if ( '' !== $dettagli ) {
			$html .= ' <span class="albo-pretorio__dettagli">(' . esc_html( $dettagli ) . ')</span>';
		}

		return $html . '</li></ul>';
	}

	/**
	 * Il numero di registro dell'atto, come 71/2026.
	 *
	 * @param array<string, mixed> $dati Dati della copia.
	 * @return string
	 */
	private static function numero( array $dati ): string {
		return isset( $dati['numero'], $dati['anno'] ) ? $dati['numero'] . '/' . $dati['anno'] : '';
	}

	/**
	 * Il periodo di pubblicazione all'albo, come "dal 01/10/2041 al 16/10/2041".
	 *
	 * @param array<string, mixed> $dati Dati della copia.
	 * @return string
	 */
	private static function periodo( array $dati ): string {
		return sprintf(
			/* translators: 1: primo giorno di pubblicazione, 2: ultimo giorno. */
			__( 'dal %1$s al %2$s', 'albo-pretorio-pa' ),
			VistaPubblica::data( (string) ( $dati['inizio'] ?? '' ) ),
			VistaPubblica::data( (string) ( $dati['fine'] ?? '' ) )
		);
	}

	/**
	 * I collegamenti alle pagine dell'elenco.
	 *
	 * @param int                   $quante    Pagine in tutto.
	 * @param int                   $attuale   Pagina mostrata, zero se nessuna.
	 * @param array<string, string> $parametri Parametri della ricerca da conservare.
	 * @return string
	 */
	private static function pagine( int $quante, int $attuale, array $parametri = array() ): string {
		if ( $quante < 2 ) {
			return '';
		}

		$base = (string) PaginaStorico::indirizzo();
		$html = '<nav class="albo-pretorio__pagine" aria-label="' . esc_attr__( 'Pagine dell\'elenco', 'albo-pretorio-pa' ) . '"><ul class="albo-pretorio__numeri">';

		for ( $pagina = 1; $pagina <= $quante; $pagina++ ) {
			/* translators: %d: numero della pagina. */
			$nome  = sprintf( __( 'Pagina %d', 'albo-pretorio-pa' ), $pagina );
			$segno = $pagina === $attuale ? ' aria-current="page"' : '';

			$html .= '<li class="albo-pretorio__numero"><a class="albo-pretorio__collegamento"' . $segno . ' aria-label="' . esc_attr( $nome ) . '" href="' . esc_url( add_query_arg( array_merge( $parametri, array( self::CAMPO_PAGINA => $pagina ) ), $base ) ) . '">' . (int) $pagina . '</a></li>';
		}

		return $html . '</ul></nav>';
	}
}
