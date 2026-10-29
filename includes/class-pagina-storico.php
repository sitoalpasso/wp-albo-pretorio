<?php
/**
 * La pagina pubblica dello storico: dove sta, che cosa mostra, che cosa dice ai motori (ALBO-36).
 *
 * Lo storico si legge soltanto dal codice breve `[albo_pretorio_storico]`,
 * scritto dall'amministrazione in una pagina diversa da quella dell'albo. Il
 * tipo della copia resta chiuso: nessun indirizzo di WordPress apre una copia.
 * Qui si decide cio' che circonda il codice breve, con le stesse regole della
 * pagina dell'albo: divieto ai motori e alle memorie, "non trovata" per una
 * copia che non si mostra, fuori dalla mappa del sito. Le regole delle
 * intestazioni sono quelle di `PaginaPubblica`, che riconosce anche questa
 * pagina come una sua risposta.
 *
 * **Spento lo storico, niente.** La pagina non disegna niente, ogni scheda
 * risponde "non trovata" e nessun documento si scarica. Le copie restano
 * salvate.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Riconoscimento della pagina dello storico e regole della sua risposta.
 */
final class PaginaStorico {

	/**
	 * Il codice breve.
	 *
	 * @var string
	 */
	const CODICE_BREVE = 'albo_pretorio_storico';

	/**
	 * Il parametro dell'indirizzo che apre la scheda di una copia.
	 *
	 * @var string
	 */
	const PARAMETRO_COPIA = 'albo_copia';

	/**
	 * Le pagine dello storico lette in questa richiesta, o nullo se non ancora lette.
	 *
	 * @var array<int, int>|null
	 */
	private static ?array $pagine = null;

	/**
	 * In questa richiesta si chiede un documento che lo storico non mostra.
	 *
	 * @var bool
	 */
	private static bool $consegna_negata = false;

	/**
	 * Quante volte il codice breve ha disegnato in questa richiesta.
	 *
	 * @var int
	 */
	private static int $istanze = 0;

	/**
	 * Aggancia codice breve e regole.
	 */
	public static function aggancia(): void {
		add_shortcode( self::CODICE_BREVE, array( self::class, 'da_codice_breve' ) );

		add_action( 'parse_request', array( self::class, 'da_parse_request' ), 9 );
		add_filter( 'is_post_type_viewable', array( self::class, 'da_is_post_type_viewable' ), 10, 2 );
		add_filter( 'post_type_link', array( self::class, 'da_post_type_link' ), 10, 2 );
		add_filter( 'pre_handle_404', array( self::class, 'da_pre_handle_404' ), 10, 2 );
		add_filter( 'oembed_request_post_id', array( self::class, 'da_oembed_request_post_id' ), PHP_INT_MAX );
		add_filter( 'document_title_parts', array( self::class, 'da_document_title_parts' ) );

		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'transition_post_status' ) as $aggancio ) {
			add_action( $aggancio, array( self::class, 'azzera' ) );
		}
	}

	/**
	 * Dimentica le pagine lette: si rileggono alla prossima domanda.
	 */
	public static function azzera(): void {
		self::$pagine = null;
	}

	/**
	 * Le pagine pubblicate che contengono il codice breve dello storico e non quello dell'albo.
	 *
	 * Pubblicate, senza password, di un tipo consultabile dal pubblico. Una
	 * pagina che contiene anche l'albo e' la pagina dell'albo, e lo storico
	 * non vi si disegna: i due elenchi hanno regole diverse e non si mescolano.
	 * Se la banca dati non risponde, nessuna: nel dubbio si chiude.
	 *
	 * @return array<int, int>
	 */
	public static function pagine(): array {
		global $wpdb;

		if ( null !== self::$pagine ) {
			return self::$pagine;
		}

		if ( ! PaginaPubblica::albo_attivo() || ! Storico::pronto() ) {
			return array();
		}

		self::$pagine = array();

		$tipi = array_values(
			array_diff(
				get_post_types( array( 'public' => true ) ),
				array( 'attachment', TIPO, TIPO_STORICO )
			)
		);

		if ( array() === $tipi ) {
			return self::$pagine;
		}

		$righe = Lettura::righe(
			$wpdb->prepare(
				"SELECT ID, post_type, post_content FROM %i WHERE post_status = 'publish' AND post_password = '' AND post_content LIKE %s ORDER BY ID ASC",
				$wpdb->posts,
				'%' . $wpdb->esc_like( '[' . self::CODICE_BREVE ) . '%'
			),
			array( 'ID', 'post_type', 'post_content' )
		);

		if ( null === $righe ) {
			return self::$pagine;
		}

		$albo = PaginaPubblica::pagine();

		foreach ( $righe as $riga ) {
			$pagina = (int) $riga['ID'];

			if ( in_array( (string) $riga['post_type'], $tipi, true ) && ! in_array( $pagina, $albo, true ) && has_shortcode( (string) $riga['post_content'], self::CODICE_BREVE ) ) {
				self::$pagine[] = $pagina;
			}
		}

		return self::$pagine;
	}

	/**
	 * La richiesta in corso e' la pagina dello storico, guardata come pagina singola.
	 *
	 * @return bool
	 */
	public static function richiesta_dello_storico(): bool {
		if ( ! is_singular() || is_feed() || is_embed() ) {
			return false;
		}

		return in_array( (int) get_queried_object_id(), self::pagine(), true );
	}

	/**
	 * L'indirizzo della pagina dello storico, o nullo se non ce n'e' una.
	 *
	 * Con piu' pagine vale quella in cui si e', altrimenti la prima creata.
	 *
	 * @return string|null
	 */
	public static function indirizzo(): ?string {
		$pagine = self::pagine();

		if ( array() === $pagine ) {
			return null;
		}

		$pagina    = self::richiesta_dello_storico() ? (int) get_queried_object_id() : $pagine[0];
		$indirizzo = get_permalink( $pagina );

		return is_string( $indirizzo ) && '' !== $indirizzo ? $indirizzo : null;
	}

	/**
	 * L'indirizzo della scheda di una copia, o nullo se non c'e' la pagina.
	 *
	 * @param int $copia Copia.
	 * @return string|null
	 */
	public static function indirizzo_scheda( int $copia ): ?string {
		$base = self::indirizzo();

		return null === $base ? null : add_query_arg( self::PARAMETRO_COPIA, $copia, $base );
	}

	/**
	 * La copia chiesta dall'indirizzo: falso se non ne chiede una, zero se il valore non puo' esserlo.
	 *
	 * La regola della scheda dell'albo: sole cifre, senza zeri davanti.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return int|false
	 */
	public static function copia_chiesta( array $richiesta ) {
		if ( ! array_key_exists( self::PARAMETRO_COPIA, $richiesta ) ) {
			return false;
		}

		$valore = PaginaPubblica::atto_chiesto( array( PaginaPubblica::PARAMETRO_ATTO => $richiesta[ self::PARAMETRO_COPIA ] ) );

		return false === $valore ? 0 : $valore;
	}

	/**
	 * La copia si mostra al pubblico, adesso.
	 *
	 * Lo storico acceso, e la copia mostrabile: vedi `mostrabile()`.
	 *
	 * @param int $copia Copia.
	 * @return bool
	 */
	public static function in_vista( int $copia ): bool {
		return Storico::acceso() && self::mostrabile( $copia );
	}

	/**
	 * La copia, da sola, si puo' mostrare: lo stato dell'interruttore lo guarda chi chiama.
	 *
	 * Pubblicata, senza password, con i dati dell'atto e il suo documento, e
	 * non finita per il meccanismo comune, che resta il solo a decidere della
	 * fine. Una copia tolta ha la fine al giorno prima e non si mostra.
	 * Dati e documento si leggono dalla banca dati, o si ricevono gia' letti
	 * da chi elenca piu' copie: una lettura che non risponde non mostra la copia.
	 *
	 * @param int                                                              $copia Copia.
	 * @param array{dati: array<string, mixed>|null, documento: int|null}|null $letto Dati e documento gia' letti, da `CopieStorico::letture()`.
	 * @return bool
	 */
	public static function mostrabile( int $copia, ?array $letto = null ): bool {
		if ( $copia < 1 || ! PaginaPubblica::albo_attivo() || ! Storico::pronto() ) {
			return false;
		}

		$contenuto = get_post( $copia );

		if ( ! $contenuto instanceof \WP_Post || TIPO_STORICO !== $contenuto->post_type || 'publish' !== $contenuto->post_status || '' !== $contenuto->post_password ) {
			return false;
		}

		if ( null === $letto ) {
			$letture = CopieStorico::letture( array( $copia ) );
			$letto   = null === $letture ? null : ( $letture[ $copia ] ?? null );
		}

		if ( null === $letto || null === $letto['dati'] || null === $letto['documento'] ) {
			return false;
		}

		return false === conformita_core_scaduto( $copia );
	}

	/**
	 * Aggancio del codice breve: disegna solo dentro la sua pagina, e solo con lo storico acceso.
	 *
	 * @param mixed $attributi Attributi, ignorati.
	 * @return string
	 */
	public static function da_codice_breve( $attributi = array() ): string {
		unset( $attributi );

		if ( ! self::richiesta_dello_storico() || (int) get_the_ID() !== (int) get_queried_object_id() || wp_is_rest_endpoint() ) {
			return '';
		}

		if ( ! Storico::acceso() ) {
			return '';
		}

		++self::$istanze;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica: vedi sicurezza.md.
		$html = VistaStorico::disegna( $_GET );

		return 1 === self::$istanze ? $html : VistaPubblica::distingui( $html, self::$istanze );
	}

	/**
	 * All'inizio della richiesta: si guarda se chiede il documento di una copia che lo storico non mostra.
	 *
	 * Prima del punto pubblico di consegna. Si consegna soltanto il documento
	 * della copia, e soltanto di una copia in vista.
	 *
	 * @param mixed $wp Ambiente della richiesta.
	 */
	public static function da_parse_request( $wp ): void {
		self::$consegna_negata = false;
		self::$istanze         = 0;

		if ( ! defined( '\Conformita_Core_Consegna::VAR_ATTO' ) || ! defined( '\Conformita_Core_Consegna::VAR_ALLEGATO' ) || ! is_object( $wp ) || ! isset( $wp->query_vars ) || ! is_array( $wp->query_vars ) ) {
			return;
		}

		$chiave = (string) constant( '\Conformita_Core_Consegna::VAR_ATTO' );

		if ( ! isset( $wp->query_vars[ $chiave ] ) ) {
			return;
		}

		// La stessa conversione del punto di consegna, come per l'albo.
		$copia    = (int) $wp->query_vars[ $chiave ];
		$allegato = (int) ( $wp->query_vars[ (string) constant( '\Conformita_Core_Consegna::VAR_ALLEGATO' ) ] ?? 0 );

		if ( $copia > 0 && TIPO_STORICO === get_post_type( $copia ) && ( ! self::in_vista( $copia ) || CopieStorico::documento( $copia ) !== $allegato ) ) {
			self::$consegna_negata = true;
		}
	}

	/**
	 * Il tipo della copia e' consultabile soltanto con lo storico acceso e la sua pagina.
	 *
	 * E' la porta del punto pubblico di consegna, come per l'albo: se le
	 * pagine non si aprono, i documenti non si scaricano. Gli indirizzi di
	 * WordPress del tipo restano chiusi, perche' `publicly_queryable` resta
	 * falso.
	 *
	 * @param mixed $consultabile Esito calcolato da WordPress.
	 * @param mixed $tipo         Oggetto del tipo.
	 * @return mixed
	 */
	public static function da_is_post_type_viewable( $consultabile, $tipo ) {
		if ( ! $tipo instanceof \WP_Post_Type || TIPO_STORICO !== $tipo->name || self::$consegna_negata ) {
			return $consultabile;
		}

		if ( ! defined( '\Conformita_Core_Consegna::VAR_ATTO' ) || array() === self::pagine() || ! Storico::acceso() ) {
			return $consultabile;
		}

		return true;
	}

	/**
	 * L'indirizzo di una copia e' la sua scheda sulla pagina dello storico.
	 *
	 * @param mixed $indirizzo Indirizzo calcolato da WordPress.
	 * @param mixed $copia     Contenuto.
	 * @return mixed
	 */
	public static function da_post_type_link( $indirizzo, $copia ) {
		if ( ! $copia instanceof \WP_Post || TIPO_STORICO !== $copia->post_type ) {
			return $indirizzo;
		}

		$scheda = self::indirizzo_scheda( (int) $copia->ID );

		return null === $scheda ? $indirizzo : $scheda;
	}

	/**
	 * La scheda di una copia che non si mostra risponde "non trovata", come per l'albo.
	 *
	 * La risposta e' quella della pagina dell'albo, con il divieto ai motori e
	 * alle memorie: una copia tolta, finita, mai esistita o con lo storico
	 * spento non si distinguono.
	 *
	 * @param mixed $anticipato     Esito di chi e' passato prima.
	 * @param mixed $interrogazione Interrogazione principale.
	 * @return mixed
	 */
	public static function da_pre_handle_404( $anticipato, $interrogazione = null ) {
		if ( false !== $anticipato || ! $interrogazione instanceof \WP_Query || ! self::richiesta_dello_storico() ) {
			return $anticipato;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		$copia = self::copia_chiesta( $_GET );

		if ( false === $copia || self::in_vista( $copia ) ) {
			return $anticipato;
		}

		PaginaPubblica::nega( $interrogazione );

		return true;
	}

	/**
	 * Nessuna anteprima incorporata di una copia: si legge soltanto dalla pagina dello storico.
	 *
	 * @param mixed $contenuto_id Contenuto riconosciuto dall'indirizzo.
	 * @return mixed
	 */
	public static function da_oembed_request_post_id( $contenuto_id ) {
		return is_numeric( $contenuto_id ) && (int) $contenuto_id > 0 && TIPO_STORICO === get_post_type( (int) $contenuto_id ) ? 0 : $contenuto_id;
	}

	/**
	 * Il titolo della finestra sulla scheda: il numero di registro dell'atto, non l'oggetto.
	 *
	 * @param mixed $parti Parti del titolo.
	 * @return mixed
	 */
	public static function da_document_title_parts( $parti ) {
		if ( ! is_array( $parti ) || ! self::richiesta_dello_storico() ) {
			return $parti;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		$copia = self::copia_chiesta( $_GET );
		$dati  = false !== $copia && self::in_vista( $copia ) ? CopieStorico::dati( $copia ) : null;

		if ( null !== $dati ) {
			$parti['title'] = sprintf(
				/* translators: %s: numero di registro dell'atto, come 71/2026. */
				__( 'Copia dell\'atto %s', 'albo-pretorio-pa' ),
				$dati['numero'] . '/' . $dati['anno']
			);
		}

		return $parti;
	}
}
