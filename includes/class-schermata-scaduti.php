<?php
/**
 * La consultazione riservata degli atti scaduti (ALBO-34).
 *
 * Una schermata dell'amministrazione, in sola lettura, per chi ha il permesso
 * di consultare gli scaduti. Elenca gli atti la cui fine e' passata, letta
 * dalla data e non dallo stato, quindi anche se il compito pianificato non e'
 * mai partito, con i dati del registro e i documenti, che si aprono dalla
 * consegna amministrativa del meccanismo comune.
 *
 * **Non riapre niente sul sito pubblico**: l'indirizzo di un atto scaduto
 * resta "non trovato" per tutti, chi ha questo permesso compreso (A-175,
 * A-179). E non da' nessun potere sugli atti: chi ha soltanto questo permesso
 * non prepara, non pubblica e non modifica.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, ricerca ed elenco della consultazione riservata.
 */
final class SchermataScaduti {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-consultazione';

	/**
	 * Azione del gettone di sicurezza della ricerca.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_cerca_consultazione';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_gettone_consultazione';

	/**
	 * Campi della ricerca.
	 *
	 * @var string
	 */
	const CAMPO_ANNO    = 'albo_anno';
	const CAMPO_NUMERO  = 'albo_numero';
	const CAMPO_OGGETTO = 'albo_oggetto';
	const CAMPO_TIPO    = 'albo_tipo';
	const CAMPO_PAGINA  = 'albo_pagina';

	/**
	 * Atti per pagina.
	 *
	 * @var int
	 */
	const PER_PAGINA = 20;

	/**
	 * Il permesso che apre la schermata.
	 *
	 * @return string|null Nome del permesso, o nullo se il meccanismo comune non lo fornisce.
	 */
	public static function permesso(): ?string {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || empty( $mappa[ Permessi::CHIAVE_CONSULTAZIONE ] ) ) {
			return null;
		}

		return (string) $mappa[ Permessi::CHIAVE_CONSULTAZIONE ];
	}

	/**
	 * Aggancio a `admin_menu`.
	 *
	 * Per chi lavora sugli atti la voce sta nel menu degli atti. Chi ha
	 * soltanto il permesso di consultare quel menu non lo vede, perche' si
	 * apre con il permesso di modificare: per lui la voce sta in cima al
	 * menu dell'amministrazione.
	 */
	public static function menu(): void {
		$permesso = self::permesso();

		if ( null === $permesso || ! post_type_exists( TIPO ) ) {
			return;
		}

		$titolo = __( 'Consultazione degli atti scaduti', 'albo-pretorio-pa' );
		$voce   = __( 'Atti scaduti', 'albo-pretorio-pa' );
		$tipo   = get_post_type_object( TIPO );

		if ( null !== $tipo && current_user_can( $tipo->cap->edit_posts ) ) {
			add_submenu_page( 'edit.php?post_type=' . TIPO, $titolo, $voce, $permesso, self::PAGINA, array( self::class, 'mostra' ) );
			return;
		}

		add_menu_page( $titolo, $voce, $permesso, self::PAGINA, array( self::class, 'mostra' ), 'dashicons-media-document' );
	}

	/**
	 * La ricerca chiesta, ripulita.
	 *
	 * Senza gettone valido la ricerca non si applica: l'elenco si mostra
	 * intero, con un avviso.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return array{anno: int|null, numero: int|null, oggetto: string, tipo: int|null, pagina: int, valida: bool, errore: string}
	 */
	public static function ricerca( array $richiesta ): array {
		$vuota = array(
			'anno'    => null,
			'numero'  => null,
			'oggetto' => '',
			'tipo'    => null,
			'pagina'  => 1,
			'valida'  => true,
			'errore'  => '',
		);

		$campi   = array( self::CAMPO_ANNO, self::CAMPO_NUMERO, self::CAMPO_OGGETTO, self::CAMPO_TIPO, self::CAMPO_PAGINA );
		$chiesta = array_intersect_key( $richiesta, array_flip( $campi ) );

		if ( array() === $chiesta ) {
			return $vuota;
		}

		$gettone = isset( $richiesta[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( (string) $richiesta[ self::CAMPO_GETTONE ] ) ) : '';

		if ( false === wp_verify_nonce( $gettone, self::AZIONE ) ) {
			$vuota['valida'] = false;
			return $vuota;
		}

		/*
		 * Un campo vuoto non restringe; un campo scritto male non si scarta in
		 * silenzio, perche' l'elenco intero passerebbe per il risultato della
		 * ricerca: si segnala, e l'elenco resta vuoto.
		 */
		$errori = array();
		$intero = static function ( string $campo, int $massimo, string $nome ) use ( $chiesta, &$errori ): ?int {
			if ( ! isset( $chiesta[ $campo ] ) ) {
				return null;
			}

			$testo = is_scalar( $chiesta[ $campo ] ) ? trim( sanitize_text_field( wp_unslash( (string) $chiesta[ $campo ] ) ) ) : 'x';

			if ( '' === $testo ) {
				return null;
			}

			if ( 1 === preg_match( '/^[0-9]{1,10}$/', $testo ) && (int) $testo >= 1 && (int) $testo <= $massimo ) {
				return (int) $testo;
			}

			$errori[] = $nome;

			return null;
		};

		$oggetto = '';
		if ( isset( $chiesta[ self::CAMPO_OGGETTO ] ) ) {
			if ( is_scalar( $chiesta[ self::CAMPO_OGGETTO ] ) ) {
				$oggetto = trim( sanitize_text_field( wp_unslash( (string) $chiesta[ self::CAMPO_OGGETTO ] ) ) );
			} else {
				$errori[] = __( 'l\'oggetto', 'albo-pretorio-pa' );
			}
		}

		$ricerca = array(
			'anno'    => $intero( self::CAMPO_ANNO, 9999, __( 'l\'anno', 'albo-pretorio-pa' ) ),
			'numero'  => $intero( self::CAMPO_NUMERO, Repertorio::MASSIMO, __( 'il numero', 'albo-pretorio-pa' ) ),
			'oggetto' => $oggetto,
			'tipo'    => $intero( self::CAMPO_TIPO, PHP_INT_MAX, __( 'il tipo', 'albo-pretorio-pa' ) ),
			'pagina'  => $intero( self::CAMPO_PAGINA, PHP_INT_MAX, __( 'la pagina', 'albo-pretorio-pa' ) ) ?? 1,
			'valida'  => true,
			'errore'  => '',
		);

		if ( array() !== $errori ) {
			$ricerca['errore'] = sprintf(
				/* translators: %s: elenco dei campi scritti male. */
				__( 'La ricerca non e\' stata fatta: non e\' valido %s. Anno, numero, tipo e pagina si scrivono solo con cifre, senza spazi ne\' punti; l\'oggetto e\' un testo solo.', 'albo-pretorio-pa' ),
				implode( ', ', $errori )
			);
		}

		return $ricerca;
	}

	/**
	 * Gli atti scaduti che rispondono alla ricerca, una pagina alla volta.
	 *
	 * Scaduto vuol dire che la fine e' un giorno gia' passato nel fuso del
	 * sito: lo stesso criterio del meccanismo comune, letto dalla data e non
	 * dallo stato. Ogni atto trovato si ricontrolla con il meccanismo comune,
	 * che resta il solo a decidere.
	 *
	 * @param array{anno: int|null, numero: int|null, oggetto: string, tipo: int|null, pagina: int, valida: bool, errore: string} $ricerca Ricerca ripulita.
	 * @param \DateTimeImmutable                                                                                                  $adesso  Istante della consultazione.
	 * @return array{atti: array<int, int>, totale: int}|\WP_Error
	 */
	public static function trovati( array $ricerca, \DateTimeImmutable $adesso ) {
		if ( '' !== $ricerca['errore'] ) {
			return new \WP_Error( 'albo_consultazione_campo_non_valido', $ricerca['errore'] );
		}

		$oggi = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		$argomenti = array(
			'post_type'           => TIPO,
			'post_status'         => 'publish',
			'posts_per_page'      => self::PER_PAGINA,
			'paged'               => max( 1, $ricerca['pagina'] ),
			'ignore_sticky_posts' => true,
			'meta_key'            => conformita_core_chiave_fine_pubblicazione(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- la consultazione ordina per fine.
			'orderby'             => array(
				'meta_value' => 'DESC', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- ordine per data di fine.
				'ID'         => 'DESC',
			),
			'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- la scadenza e' la data di fine.
				array(
					'key'     => conformita_core_chiave_fine_pubblicazione(),
					'value'   => $oggi,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		);

		if ( null !== $ricerca['anno'] || null !== $ricerca['numero'] ) {
			if ( null === $ricerca['anno'] ) {
				return new \WP_Error( 'albo_consultazione_anno_mancante', __( 'Per cercare un numero serve anche l\'anno.', 'albo-pretorio-pa' ) );
			}

			$numerati = Repertorio::atti_numerati( $ricerca['anno'], $ricerca['numero'] );

			if ( null === $numerati ) {
				return new \WP_Error( 'albo_consultazione_lettura', __( 'La numerazione non ha risposto: la ricerca per numero non e\' disponibile in questo momento.', 'albo-pretorio-pa' ) );
			}

			if ( array() === $numerati ) {
				return array(
					'atti'   => array(),
					'totale' => 0,
				);
			}

			$argomenti['post__in'] = $numerati;
		}

		if ( '' !== $ricerca['oggetto'] ) {
			$argomenti['s']              = $ricerca['oggetto'];
			$argomenti['search_columns'] = array( 'post_title' );
		}

		if ( null !== $ricerca['tipo'] ) {
			$argomenti['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- ricerca per tipo di atto.
				array(
					'taxonomy' => TASSONOMIA_TIPO_ATTO,
					'field'    => 'term_id',
					'terms'    => array( $ricerca['tipo'] ),
				),
			);
		}

		$interrogazione = new \WP_Query( $argomenti );
		$atti           = array();

		foreach ( $interrogazione->posts as $atto ) {
			$id = $atto instanceof \WP_Post ? (int) $atto->ID : (int) $atto;

			if ( true === conformita_core_scaduto( $id ) ) {
				$atti[] = $id;
			}
		}

		return array(
			'atti'   => $atti,
			'totale' => (int) $interrogazione->found_posts,
		);
	}

	/**
	 * Il contenuto della schermata.
	 */
	public static function mostra(): void {
		$permesso = self::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			wp_die( esc_html__( 'Non si possiede il permesso di consultare gli atti scaduti.', 'albo-pretorio-pa' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- il gettone lo verifica ricerca().
		$ricerca = self::ricerca( $_GET );
		$esito   = self::trovati( $ricerca, Passaggi::ora() );

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Consultazione degli atti scaduti', 'albo-pretorio-pa' ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'Gli atti la cui pubblicazione e\' finita. Sul sito pubblico non si vedono piu\', per nessuno: qui si consultano, con i loro documenti, e non si modificano.', 'albo-pretorio-pa' )
		);

		if ( ! $ricerca['valida'] ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'La ricerca non e\' stata applicata perche\' il modulo e\' scaduto: ripeterla dal modulo qui sotto.', 'albo-pretorio-pa' ) );
		}

		self::modulo( $ricerca );

		if ( is_wp_error( $esito ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $esito->get_error_message() ) );
			echo '</div>';
			return;
		}

		self::elenco( $esito['atti'] );
		self::pagine( $ricerca, $esito['totale'] );

		echo '</div>';
	}

	/**
	 * Il modulo della ricerca.
	 *
	 * @param array{anno: int|null, numero: int|null, oggetto: string, tipo: int|null, pagina: int, valida: bool, errore: string} $ricerca Ricerca ripulita.
	 */
	private static function modulo( array $ricerca ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::PAGINA ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE, false );

		$campo = static function ( string $nome, string $etichetta, string $valore, string $modo ): void {
			printf(
				'<label for="%1$s">%2$s</label> <input type="text" inputmode="%4$s" id="%1$s" name="%1$s" value="%3$s" /> ',
				esc_attr( $nome ),
				esc_html( $etichetta ),
				esc_attr( $valore ),
				esc_attr( $modo )
			);
		};

		echo '<p>';
		$campo( self::CAMPO_ANNO, __( 'Anno', 'albo-pretorio-pa' ), null === $ricerca['anno'] ? '' : (string) $ricerca['anno'], 'numeric' );
		$campo( self::CAMPO_NUMERO, __( 'Numero', 'albo-pretorio-pa' ), null === $ricerca['numero'] ? '' : (string) $ricerca['numero'], 'numeric' );
		$campo( self::CAMPO_OGGETTO, __( 'Oggetto', 'albo-pretorio-pa' ), $ricerca['oggetto'], 'text' );

		$voci = get_terms(
			array(
				'taxonomy'   => TASSONOMIA_TIPO_ATTO,
				'hide_empty' => false,
			)
		);

		printf( '<label for="%1$s">%2$s</label> <select id="%1$s" name="%1$s">', esc_attr( self::CAMPO_TIPO ), esc_html__( 'Tipo di atto', 'albo-pretorio-pa' ) );
		printf( '<option value="">%s</option>', esc_html__( 'Tutti', 'albo-pretorio-pa' ) );

		foreach ( is_array( $voci ) ? $voci : array() as $voce ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $voce->term_id,
				selected( $ricerca['tipo'], (int) $voce->term_id, false ),
				esc_html( $voce->name )
			);
		}

		echo '</select> ';
		submit_button( __( 'Cerca', 'albo-pretorio-pa' ), 'secondary', '', false );
		echo '</p></form>';
	}

	/**
	 * L'elenco degli atti trovati.
	 *
	 * @param array<int, int> $atti Atti.
	 */
	private static function elenco( array $atti ): void {
		if ( array() === $atti ) {
			printf( '<p>%s</p>', esc_html__( 'Nessun atto scaduto risponde alla ricerca.', 'albo-pretorio-pa' ) );
			return;
		}

		echo '<table class="widefat striped albo-pretorio-consultazione"><thead><tr>';

		foreach ( array(
			__( 'Numero', 'albo-pretorio-pa' ),
			__( 'Oggetto', 'albo-pretorio-pa' ),
			__( 'Dati dell\'atto', 'albo-pretorio-pa' ),
			__( 'Pubblicazione', 'albo-pretorio-pa' ),
			__( 'Documenti', 'albo-pretorio-pa' ),
		) as $intestazione ) {
			printf( '<th scope="col">%s</th>', esc_html( $intestazione ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $atti as $atto ) {
			self::riga( $atto );
		}

		echo '</tbody></table>';
	}

	/**
	 * Una riga dell'elenco.
	 *
	 * @param int $atto Atto.
	 */
	private static function riga( int $atto ): void {
		$numero = Repertorio::di( $atto );

		printf(
			'<tr data-atto="%1$d"><td>%2$s</td><td>%3$s</td><td>',
			(int) $atto,
			esc_html( null === $numero ? __( 'senza numero', 'albo-pretorio-pa' ) : $numero['numero'] . '/' . $numero['anno'] ),
			esc_html( get_the_title( $atto ) )
		);

		$dati = array();
		$tipo = DatiAtto::tipo( $atto );

		if ( null !== $tipo ) {
			$dati[ __( 'Tipo', 'albo-pretorio-pa' ) ] = $tipo->name;
		}

		$organo = DatiAtto::organo( $atto );

		if ( null !== $organo ) {
			$dati[ __( 'Organo', 'albo-pretorio-pa' ) ] = $organo->name;
		}

		if ( '' !== DatiAtto::numero_proprio( $atto ) ) {
			$dati[ __( 'Numero proprio', 'albo-pretorio-pa' ) ] = DatiAtto::numero_proprio( $atto );
		}

		$adozione = DatiAtto::data_adozione( $atto );

		if ( null !== $adozione ) {
			$dati[ __( 'Data di adozione', 'albo-pretorio-pa' ) ] = self::data( $adozione );
		}

		foreach ( DatiProcedimento::da_mostrare( $atto ) as $etichetta => $valore ) {
			$dati[ $etichetta ] = $valore;
		}

		foreach ( $dati as $etichetta => $valore ) {
			printf( '<strong>%1$s</strong>: %2$s<br />', esc_html( (string) $etichetta ), esc_html( (string) $valore ) );
		}

		$fine = conformita_core_fine_pubblicazione( $atto );

		printf(
			'</td><td>%s</td><td>',
			esc_html(
				sprintf(
					/* translators: 1: data di inizio, 2: data di fine. */
					__( 'dal %1$s al %2$s', 'albo-pretorio-pa' ),
					get_the_date( 'd/m/Y', $atto ),
					is_string( $fine ) ? self::data( $fine ) : __( 'fine non leggibile', 'albo-pretorio-pa' )
				)
			)
		);

		$documenti  = array();
		$principale = DocumentiAtto::principale( $atto );

		if ( null !== $principale ) {
			$documenti[] = $principale;
		}

		$documenti = array_merge( $documenti, DocumentiAtto::ulteriori( $atto ) );

		if ( array() === $documenti ) {
			esc_html_e( 'Nessun documento.', 'albo-pretorio-pa' );
		} else {
			echo '<ul>';

			foreach ( $documenti as $documento ) {
				$indirizzo = conformita_core_indirizzo_consegna_amministrativa( $documento );

				echo '<li>';

				if ( is_string( $indirizzo ) ) {
					printf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $indirizzo ), esc_html( get_the_title( $documento ) ) );
				} else {
					echo esc_html( get_the_title( $documento ) );
				}

				echo '</li>';
			}

			echo '</ul>';
		}

		echo '</td></tr>';
	}

	/**
	 * I collegamenti alle pagine dell'elenco.
	 *
	 * @param array{anno: int|null, numero: int|null, oggetto: string, tipo: int|null, pagina: int, valida: bool, errore: string} $ricerca Ricerca ripulita.
	 * @param int                                                                                                                 $totale  Atti trovati in tutto.
	 */
	private static function pagine( array $ricerca, int $totale ): void {
		$quante = (int) ceil( $totale / self::PER_PAGINA );

		if ( $quante < 2 ) {
			return;
		}

		$parametri = array_filter(
			array(
				'page'              => self::PAGINA,
				self::CAMPO_ANNO    => $ricerca['anno'],
				self::CAMPO_NUMERO  => $ricerca['numero'],
				self::CAMPO_OGGETTO => '' === $ricerca['oggetto'] ? null : rawurlencode( $ricerca['oggetto'] ),
				self::CAMPO_TIPO    => $ricerca['tipo'],
				self::CAMPO_GETTONE => wp_create_nonce( self::AZIONE ),
			),
			static function ( $valore ): bool {
				return null !== $valore;
			}
		);

		echo '<p class="albo-pretorio-pagine">';

		for ( $pagina = 1; $pagina <= $quante; $pagina++ ) {
			if ( $pagina === $ricerca['pagina'] ) {
				printf( '<strong>%d</strong> ', (int) $pagina );
				continue;
			}

			printf(
				'<a href="%1$s">%2$d</a> ',
				esc_url( add_query_arg( array_merge( $parametri, array( self::CAMPO_PAGINA => $pagina ) ), admin_url( 'admin.php' ) ) ),
				(int) $pagina
			);
		}

		echo '</p>';
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
