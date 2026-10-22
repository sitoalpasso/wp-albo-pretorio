<?php
/**
 * La pagina pubblica dell'albo: dove sta, chi la vede, cosa dice ai motori.
 *
 * Il tipo atto resta chiuso: nessun indirizzo di WordPress apre un atto. La
 * sola vista pubblica e' il codice breve `[albo_pretorio]`, scritto
 * dall'amministrazione in una pagina del sito. Qui si decide tutto cio' che
 * circonda quel codice breve: quali pagine lo contengono, che cosa risponde
 * l'indirizzo della scheda di un atto che non e' in pubblicazione, il divieto
 * ai motori di ricerca, l'esclusione dalla mappa del sito e dalle memorie di
 * pagina, e la dichiarazione del tipo come consultabile, che apre la consegna
 * dei documenti solo quando la pagina esiste. Il disegno di elenco, scheda e
 * ricerca sta in `VistaPubblica`.
 *
 * **Nessuna impostazione.** La pagina dell'albo e' quella che contiene il
 * codice breve: l'amministrazione la crea, la sposta, la toglie con gli
 * strumenti di sempre (decisione del 19 ottobre 2026).
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Riconoscimento della pagina dell'albo e regole della sua risposta.
 */
final class PaginaPubblica {

	/**
	 * Il codice breve.
	 *
	 * Non `Albo`, che e' il nome del plugin precedente: i due plugin restano
	 * accesi insieme durante il passaggio, e lo stesso nome farebbe disegnare a
	 * uno le pagine dell'altro.
	 *
	 * @var string
	 */
	const CODICE_BREVE = 'albo_pretorio';

	/**
	 * Il parametro dell'indirizzo che apre la scheda di un atto.
	 *
	 * @var string
	 */
	const PARAMETRO_ATTO = 'albo_atto';

	/**
	 * Identificativo del foglio di stile.
	 *
	 * @var string
	 */
	const STILE = 'albo-pretorio-pubblico';

	/**
	 * Le pagine dell'albo lette in questa richiesta, o nullo se non ancora lette.
	 *
	 * @var array<int, int>|null
	 */
	private static ?array $pagine = null;

	/**
	 * Aggancia codice breve e regole.
	 */
	public static function aggancia(): void {
		add_shortcode( self::CODICE_BREVE, array( self::class, 'da_codice_breve' ) );

		add_filter( 'is_post_type_viewable', array( self::class, 'da_is_post_type_viewable' ), 10, 2 );
		add_filter( 'post_type_link', array( self::class, 'da_post_type_link' ), 10, 2 );
		add_filter( 'pre_handle_404', array( self::class, 'da_pre_handle_404' ), 10, 2 );
		add_filter( 'wp_robots', array( self::class, 'da_wp_robots' ), PHP_INT_MAX );
		add_filter( 'wp_headers', array( self::class, 'da_wp_headers' ), PHP_INT_MAX );
		add_action( 'wp', array( self::class, 'da_wp' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'da_wp_sitemaps_posts_query_args' ), PHP_INT_MAX, 2 );
		add_filter( 'oembed_request_post_id', array( self::class, 'da_oembed_request_post_id' ), PHP_INT_MAX );
		add_filter( 'document_title_parts', array( self::class, 'da_document_title_parts' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'da_wp_enqueue_scripts' ) );
		add_filter( 'posts_where', array( VistaPubblica::class, 'da_posts_where' ), 10, 2 );

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
	 * L'albo e' avviato: il tipo atto esiste e il meccanismo comune risponde.
	 *
	 * @return bool
	 */
	public static function albo_attivo(): bool {
		return post_type_exists( TIPO ) && function_exists( 'conformita_core_versione_api' );
	}

	/**
	 * Le pagine pubblicate che contengono il codice breve.
	 *
	 * Pubblicate, senza password, di un tipo consultabile dal pubblico. Se la
	 * banca dati non risponde, nessuna: una pagina che non si sa riconoscere
	 * non riceve le regole della pagina dell'albo, e senza pagina il tipo resta
	 * non consultabile, quindi nessun documento esce. Nel dubbio si chiude.
	 *
	 * @return array<int, int>
	 */
	public static function pagine(): array {
		global $wpdb;

		if ( null !== self::$pagine ) {
			return self::$pagine;
		}

		// Con l'albo non ancora avviato non si ricorda niente: la domanda si rifa' quando lo e'.
		if ( ! self::albo_attivo() ) {
			return array();
		}

		self::$pagine = array();

		$tipi = array_values(
			array_diff(
				get_post_types( array( 'public' => true ) ),
				array( 'attachment', TIPO )
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

		foreach ( $righe as $riga ) {
			if ( in_array( (string) $riga['post_type'], $tipi, true ) && has_shortcode( (string) $riga['post_content'], self::CODICE_BREVE ) ) {
				self::$pagine[] = (int) $riga['ID'];
			}
		}

		return self::$pagine;
	}

	/**
	 * La richiesta in corso e' la pagina dell'albo, guardata come pagina singola.
	 *
	 * @return bool
	 */
	public static function richiesta_dell_albo(): bool {
		if ( ! is_singular() || is_feed() || is_embed() ) {
			return false;
		}

		return in_array( (int) get_queried_object_id(), self::pagine(), true );
	}

	/**
	 * L'indirizzo della pagina dell'albo, o nullo se non ce n'e' una.
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

		$pagina    = self::richiesta_dell_albo() ? (int) get_queried_object_id() : $pagine[0];
		$indirizzo = get_permalink( $pagina );

		return is_string( $indirizzo ) && '' !== $indirizzo ? $indirizzo : null;
	}

	/**
	 * L'indirizzo della scheda di un atto, o nullo se non c'e' la pagina.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null
	 */
	public static function indirizzo_scheda( int $atto_id ): ?string {
		$base = self::indirizzo();

		return null === $base ? null : add_query_arg( self::PARAMETRO_ATTO, $atto_id, $base );
	}

	/**
	 * L'atto chiesto dall'indirizzo, se l'indirizzo ne chiede uno.
	 *
	 * Falso se l'indirizzo non chiede nessun atto; zero se ne chiede uno con un
	 * valore che non puo' essere un atto (lettere, zero, un numero oltre il
	 * massimo): risponde come un atto mai esistito.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return int|false
	 */
	public static function atto_chiesto( array $richiesta ) {
		if ( ! array_key_exists( self::PARAMETRO_ATTO, $richiesta ) ) {
			return false;
		}

		$valore = $richiesta[ self::PARAMETRO_ATTO ];

		/*
		 * Un atto, un indirizzo: si accettano solo cifre, senza zeri davanti,
		 * spazi o segni, e il valore non si ripulisce prima di guardarlo,
		 * perche' ripulito diventerebbe un secondo indirizzo della stessa
		 * scheda. Fuori dalle cifre il valore non si usa, quindi non serve
		 * sanificarlo.
		 */
		$testo = is_string( $valore ) || is_int( $valore ) ? (string) $valore : '';

		if ( 1 !== preg_match( '/^[1-9][0-9]{0,17}$/D', $testo ) ) {
			return 0;
		}

		return (int) $testo;
	}

	/**
	 * L'atto e' in pubblicazione, cioe' si mostra al pubblico.
	 *
	 * Tre condizioni, tutte lette adesso: pubblicato senza password, con il
	 * numero di registro che solo il passaggio vero assegna, e non scaduto per
	 * il meccanismo comune, che resta il solo a decidere della scadenza. Un
	 * atto portato a pubblicato scrivendo lo stato nella banca dati non ha il
	 * numero e non si mostra (A-196).
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function in_pubblicazione( int $atto_id ): bool {
		if ( $atto_id < 1 || ! self::albo_attivo() ) {
			return false;
		}

		$atto = get_post( $atto_id );

		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type || 'publish' !== $atto->post_status || '' !== $atto->post_password ) {
			return false;
		}

		if ( null === Repertorio::di( $atto_id ) ) {
			return false;
		}

		return false === conformita_core_scaduto( $atto_id );
	}

	/**
	 * Aggancio del codice breve.
	 *
	 * Disegna solo dentro la pagina che lo contiene, guardata come pagina
	 * singola: e' l'unico posto dove valgono il divieto ai motori, la risposta
	 * "non trovata" e il divieto di conservare la pagina. Altrove (un riquadro
	 * laterale, un elenco di articoli, un feed, l'interfaccia per programmi,
	 * l'anteprima incorporata) non disegna niente (A-200).
	 *
	 * @param mixed $attributi Attributi, ignorati.
	 * @return string
	 */
	public static function da_codice_breve( $attributi = array() ): string {
		unset( $attributi );

		if ( ! self::albo_attivo() || ! self::richiesta_dell_albo() || (int) get_the_ID() !== (int) get_queried_object_id() ) {
			return '';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica: vedi sicurezza.md.
		return VistaPubblica::disegna( $_GET, Passaggi::ora() );
	}

	/**
	 * Il tipo atto e' consultabile dal pubblico solo se la pagina dell'albo esiste.
	 *
	 * Il punto pubblico di consegna del meccanismo comune non consegna i file
	 * di un tipo non consultabile (C-176): e' la regola "se le pagine non si
	 * aprono, i documenti non si scaricano". La pagina dell'albo e' la pagina
	 * degli atti, quindi con la pagina il tipo si dichiara consultabile e i
	 * documenti degli atti in corso si scaricano; senza, il tipo resta come
	 * oggi. Gli indirizzi di WordPress del tipo restano chiusi in ogni caso:
	 * li governa `publicly_queryable`, che resta falso (A-25, A-201).
	 *
	 * @param mixed $consultabile Esito calcolato da WordPress.
	 * @param mixed $tipo         Oggetto del tipo.
	 * @return mixed
	 */
	public static function da_is_post_type_viewable( $consultabile, $tipo ) {
		if ( ! $tipo instanceof \WP_Post_Type || TIPO !== $tipo->name ) {
			return $consultabile;
		}

		return array() !== self::pagine() ? true : $consultabile;
	}

	/**
	 * L'indirizzo di un atto e' la sua scheda sulla pagina dell'albo.
	 *
	 * @param mixed $indirizzo Indirizzo calcolato da WordPress.
	 * @param mixed $atto      Contenuto.
	 * @return mixed
	 */
	public static function da_post_type_link( $indirizzo, $atto ) {
		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return $indirizzo;
		}

		$scheda = self::indirizzo_scheda( (int) $atto->ID );

		return null === $scheda ? $indirizzo : $scheda;
	}

	/**
	 * La scheda di un atto che non e' in pubblicazione risponde "non trovata".
	 *
	 * Con il percorso di WordPress per un indirizzo inesistente, prima che il
	 * tema disegni qualunque cosa: stesso stato, stesse intestazioni, stesso
	 * modello di pagina. Uno scaduto, una bozza e un numero mai dato non si
	 * distinguono (A-196, A-197).
	 *
	 * @param mixed $anticipato Esito di chi e' passato prima.
	 * @param mixed $interrogazione Interrogazione principale.
	 * @return mixed
	 */
	public static function da_pre_handle_404( $anticipato, $interrogazione = null ) {
		if ( false !== $anticipato || ! $interrogazione instanceof \WP_Query || ! self::richiesta_dell_albo() ) {
			return $anticipato;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		$atto = self::atto_chiesto( $_GET );

		if ( false === $atto || self::in_pubblicazione( $atto ) ) {
			return $anticipato;
		}

		$interrogazione->set_404();
		status_header( 404 );
		nocache_headers();

		return true;
	}

	/**
	 * Il divieto ai motori nel sorgente della pagina dell'albo (ALBO-06).
	 *
	 * Alla priorita' piu' alta, come il meccanismo comune: un filtro che prima
	 * ha scritto il contrario viene corretto.
	 *
	 * @param mixed $direttive Direttive raccolte finora.
	 * @return mixed
	 */
	public static function da_wp_robots( $direttive ) {
		if ( ! is_array( $direttive ) || ! self::richiesta_dell_albo() ) {
			return $direttive;
		}

		unset( $direttive['index'] );
		$direttive['noindex'] = true;

		return $direttive;
	}

	/**
	 * Il divieto ai motori e alle memorie nell'intestazione della risposta.
	 *
	 * `X-Robots-Tag: noindex`, aggiunto a quello che c'e' gia', e le
	 * intestazioni con cui WordPress vieta di conservare una risposta: l'elenco
	 * di oggi non deve uscire da una memoria dopo la mezzanotte.
	 *
	 * @param mixed $intestazioni Intestazioni.
	 * @return mixed
	 */
	public static function da_wp_headers( $intestazioni ) {
		if ( ! is_array( $intestazioni ) || ! self::richiesta_dell_albo() ) {
			return $intestazioni;
		}

		$robot = '';

		foreach ( array_keys( $intestazioni ) as $nome ) {
			if ( is_string( $nome ) && 0 === strcasecmp( $nome, 'X-Robots-Tag' ) ) {
				$robot = trim( (string) $intestazioni[ $nome ] );
				unset( $intestazioni[ $nome ] );
			}
		}

		if ( 1 !== preg_match( '/(^|,)\s*(noindex|none)\s*(,|$)/i', $robot ) ) {
			$robot = '' === $robot ? 'noindex' : $robot . ', noindex';
		}

		$intestazioni['X-Robots-Tag'] = $robot;

		return array_merge( $intestazioni, wp_get_nocache_headers() );
	}

	/**
	 * Le estensioni di memoria di pagina non conservano la pagina dell'albo.
	 *
	 * `DONOTCACHEPAGE` e' la costante che le estensioni piu' diffuse leggono
	 * prima di conservare una risposta. Una costante non si toglie: una volta
	 * definita vale fino alla fine della richiesta, che e' quella dell'albo.
	 */
	public static function da_wp(): void {
		if ( self::richiesta_dell_albo() && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- e' il nome che leggono le estensioni di memoria.
		}
	}

	/**
	 * La pagina dell'albo fuori dalla mappa del sito (ALBO-06).
	 *
	 * @param mixed $argomenti Argomenti dell'interrogazione della mappa.
	 * @param mixed $tipo      Tipo di contenuto della mappa.
	 * @return mixed
	 */
	public static function da_wp_sitemaps_posts_query_args( $argomenti, $tipo = '' ) {
		unset( $tipo );

		if ( ! is_array( $argomenti ) || array() === self::pagine() ) {
			return $argomenti;
		}

		$esclusi = isset( $argomenti['post__not_in'] ) && is_array( $argomenti['post__not_in'] ) ? $argomenti['post__not_in'] : array();

		$argomenti['post__not_in'] = array_values( array_unique( array_merge( array_map( 'intval', $esclusi ), self::pagine() ) ) );

		return $argomenti;
	}

	/**
	 * Nessuna anteprima incorporata di un atto.
	 *
	 * Con la pagina dell'albo il tipo e' consultabile, e WordPress
	 * risponderebbe all'anteprima dell'indirizzo con il numero del contenuto
	 * con i dati dell'atto, fuori dalla pagina e dalle sue regole (A-201).
	 * Le versioni di WordPress che conoscono il tipo "incorporabile" la
	 * rifiutano gia'; quelle precedenti, fino alla minima dichiarata, no.
	 *
	 * Vale solo finche' il tipo resta chiuso, cioe' finche' la consultabilita'
	 * e' quella dichiarata dall'albo. Se un altro componente lo apre, l'atto in
	 * corso ha le sue strade di WordPress, anteprima compresa, e la scadenza
	 * su ciascuna la garantisce il meccanismo comune (A-175).
	 *
	 * @param mixed $contenuto_id Contenuto riconosciuto dall'indirizzo.
	 * @return mixed
	 */
	public static function da_oembed_request_post_id( $contenuto_id ) {
		if ( ! is_numeric( $contenuto_id ) || (int) $contenuto_id < 1 || TIPO !== get_post_type( (int) $contenuto_id ) ) {
			return $contenuto_id;
		}

		$tipo = get_post_type_object( TIPO );

		return null !== $tipo && true === $tipo->publicly_queryable ? $contenuto_id : 0;
	}

	/**
	 * Il titolo della finestra sulla scheda: il numero di registro, non l'oggetto.
	 *
	 * L'oggetto puo' contenere dati di persone, e il titolo della finestra
	 * finisce nella cronologia del browser e nei segnalibri.
	 *
	 * @param mixed $parti Parti del titolo.
	 * @return mixed
	 */
	public static function da_document_title_parts( $parti ) {
		if ( ! is_array( $parti ) ) {
			return $parti;
		}

		$atto   = self::atto_in_vista();
		$numero = null === $atto ? null : Repertorio::di( $atto );

		if ( null !== $numero ) {
			$parti['title'] = sprintf(
				/* translators: %s: numero di registro, come 71/2026. */
				__( 'Atto %s', 'albo-pretorio-pa' ),
				$numero['numero'] . '/' . $numero['anno']
			);
		}

		return $parti;
	}

	/**
	 * L'atto della scheda aperta, se la richiesta e' la pagina dell'albo e l'atto e' in pubblicazione.
	 *
	 * @return int|null
	 */
	public static function atto_in_vista(): ?int {
		if ( ! self::richiesta_dell_albo() ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
		$atto = self::atto_chiesto( $_GET );

		return false !== $atto && self::in_pubblicazione( $atto ) ? $atto : null;
	}

	/**
	 * Quello che un tema puo' sapere della pagina in corso, per la sua testata (A-219).
	 *
	 * Nullo fuori dalla pagina dell'albo, e su una pagina "non trovata". Sulla
	 * pagina: l'indirizzo dell'elenco e, se c'e' una scheda aperta di un atto in
	 * pubblicazione, il numero di registro e il tipo. Mai l'oggetto, che resta
	 * nella scheda come il titolo della finestra.
	 *
	 * @return array{elenco: string, atto: array{numero: string, tipo: string}|null}|null
	 */
	public static function vista(): ?array {
		if ( ! self::albo_attivo() || ! self::richiesta_dell_albo() ) {
			return null;
		}

		$elenco = self::indirizzo();

		if ( null === $elenco ) {
			return null;
		}

		$atto   = self::atto_in_vista();
		$numero = null === $atto ? null : Repertorio::di( $atto );

		if ( null === $numero ) {
			return array(
				'elenco' => $elenco,
				'atto'   => null,
			);
		}

		$tipo = DatiAtto::tipo( (int) $atto );

		return array(
			'elenco' => $elenco,
			'atto'   => array(
				'numero' => $numero['numero'] . '/' . $numero['anno'],
				'tipo'   => null === $tipo ? '' : $tipo->name,
			),
		);
	}

	/**
	 * Il foglio di stile dell'albo, solo sulla sua pagina.
	 */
	public static function da_wp_enqueue_scripts(): void {
		if ( ! self::richiesta_dell_albo() ) {
			return;
		}

		wp_enqueue_style( self::STILE, plugins_url( 'assets/css/albo-pretorio-pubblico.css', FILE_PRINCIPALE ), array(), VERSIONE );
	}
}
