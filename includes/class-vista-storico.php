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
	 * Nulla se la lettura non risponde.
	 *
	 * @return array<int, int>|null
	 */
	public static function copie(): ?array {
		$presenti = CopieStorico::presenti();

		if ( null === $presenti ) {
			return null;
		}

		$copie = array();

		foreach ( $presenti as $copia ) {
			$dati = PaginaStorico::mostrabile( $copia ) ? CopieStorico::dati( $copia ) : null;

			if ( null !== $dati ) {
				$copie[ $copia ] = array( (int) $dati['anno'], (int) $dati['numero'], $copia );
			}
		}

		uasort(
			$copie,
			static function ( array $prima, array $dopo ): int {
				return $dopo <=> $prima;
			}
		);

		return array_keys( $copie );
	}

	/**
	 * L'avviso, il conteggio, l'elenco e le pagine.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return string
	 */
	private static function elenco( array $richiesta ): string {
		$html  = '<section class="albo-pretorio__risultati" aria-labelledby="albo-pretorio-titolo-elenco">';
		$html .= '<h2 id="albo-pretorio-titolo-elenco" class="albo-pretorio__titolo">' . esc_html__( 'Copie per consultazione di atti non piu\' in pubblicazione', 'albo-pretorio-pa' ) . '</h2>';
		$html .= '<p class="albo-pretorio__avviso">' . esc_html__( 'Questi non sono gli atti dell\'albo: sono copie con i dati personali oscurati, messe a disposizione dopo la fine della pubblicazione. Il documento originale si chiede all\'amministrazione con l\'accesso agli atti.', 'albo-pretorio-pa' ) . '</p>';

		$copie = self::copie();

		if ( null === $copie ) {
			return $html . '<p class="albo-pretorio__avviso" role="alert">' . esc_html__( 'Le copie non si leggono in questo momento. Riprovare piu\' tardi.', 'albo-pretorio-pa' ) . '</p></section>';
		}

		if ( array() === $copie ) {
			return $html . '<p class="albo-pretorio__conteggio">' . esc_html__( 'In questo momento non ci sono copie da consultare.', 'albo-pretorio-pa' ) . '</p></section>';
		}

		$totale = count( $copie );
		$quante = (int) ceil( $totale / self::PER_PAGINA );
		$pagina = self::pagina( $richiesta );
		$base   = (string) PaginaStorico::indirizzo();

		if ( null === $pagina || $pagina > $quante ) {
			$html .= '<p class="albo-pretorio__conteggio">' . esc_html__( 'Questa pagina dell\'elenco non esiste.', 'albo-pretorio-pa' ) . '</p>';
			$html .= '<p class="albo-pretorio__torna"><a class="albo-pretorio__collegamento" href="' . esc_url( $base ) . '">' . esc_html__( 'Vai alla prima pagina', 'albo-pretorio-pa' ) . '</a></p>';

			return $html . self::pagine( $quante, 0 ) . '</section>';
		}

		$html .= '<p class="albo-pretorio__conteggio">' . esc_html(
			sprintf(
				/* translators: %s: numero delle copie. */
				_n( '%s copia', '%s copie', $totale, 'albo-pretorio-pa' ),
				number_format_i18n( $totale )
			)
		) . '</p>';

		$html .= self::tabella( array_slice( $copie, ( $pagina - 1 ) * self::PER_PAGINA, self::PER_PAGINA ) );

		return $html . self::pagine( $quante, $pagina ) . '</section>';
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
	 * @param array<int, int> $copie Copie, nell'ordine.
	 * @return string
	 */
	private static function tabella( array $copie ): string {
		$colonne = self::colonne();
		$html    = '<table class="albo-pretorio__tabella" role="table" aria-labelledby="albo-pretorio-titolo-elenco">';
		$html   .= '<thead class="albo-pretorio__testata" role="rowgroup"><tr role="row">';

		foreach ( $colonne as $nome ) {
			$html .= '<th class="albo-pretorio__intestazione" scope="col" role="columnheader">' . esc_html( $nome ) . '</th>';
		}

		$html .= '</tr></thead><tbody role="rowgroup">';

		foreach ( $copie as $copia ) {
			$dati = (array) CopieStorico::dati( $copia );
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
	 * @param int $quante Pagine in tutto.
	 * @param int $attuale Pagina mostrata, zero se nessuna.
	 * @return string
	 */
	private static function pagine( int $quante, int $attuale ): string {
		if ( $quante < 2 ) {
			return '';
		}

		$base = (string) PaginaStorico::indirizzo();
		$html = '<nav class="albo-pretorio__pagine" aria-label="' . esc_attr__( 'Pagine dell\'elenco', 'albo-pretorio-pa' ) . '"><ul class="albo-pretorio__numeri">';

		for ( $pagina = 1; $pagina <= $quante; $pagina++ ) {
			/* translators: %d: numero della pagina. */
			$nome  = sprintf( __( 'Pagina %d', 'albo-pretorio-pa' ), $pagina );
			$segno = $pagina === $attuale ? ' aria-current="page"' : '';

			$html .= '<li class="albo-pretorio__numero"><a class="albo-pretorio__collegamento"' . $segno . ' aria-label="' . esc_attr( $nome ) . '" href="' . esc_url( add_query_arg( self::CAMPO_PAGINA, $pagina, $base ) ) . '">' . (int) $pagina . '</a></li>';
		}

		return $html . '</ul></nav>';
	}
}
