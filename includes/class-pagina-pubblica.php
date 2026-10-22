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
 * **Il passaggio da "Albo Pretorio On line".** Spento quel plugin, il suo
 * codice breve `[Albo]` disegna questa stessa pagina, e i suoi indirizzi delle
 * schede e dei documenti portano alla scheda nuova dell'atto importato se e'
 * in pubblicazione, altrimenti rispondono "non trovata" come un atto mai
 * esistito (ALBO-35, scheda `vecchi-indirizzi.md`).
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
	 * Il codice breve della pagina di "Albo Pretorio On line".
	 *
	 * Lo prende l'albo solo se nessuno lo ha registrato: con il plugin
	 * precedente acceso resta suo, e le sue pagine restano sue (A-221).
	 *
	 * @var string
	 */
	const CODICE_VECCHIO = 'Albo';

	/**
	 * Gli altri codici brevi di "Albo Pretorio On line", che qui non disegnano niente.
	 *
	 * Un atto incollato in un articolo e un gruppo di atti con la stessa
	 * etichetta: fuori dalla pagina dell'albo non valgono il divieto ai motori
	 * ne' la risposta "non trovata", quindi non si disegnano. Presi solo per
	 * non lasciare sul sito il codice scritto fra parentesi quadre (A-221).
	 *
	 * @var array<int, string>
	 */
	const CODICI_VECCHI_MUTI = array( 'AlboAtto', 'AlboGruppiAtti' );

	/**
	 * I tre indirizzi di "Albo Pretorio On line" che portano a un atto, e il
	 * parametro che in ciascuno porta il suo numero interno.
	 *
	 * La scheda, la stampa della scheda o del certificato, il documento.
	 *
	 * @var array<string, string>
	 */
	const AZIONI_VECCHIE = array(
		'visatto'   => 'id',
		'printatto' => 'id',
		'dwnalle'   => 'idAtto',
	);

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
	 * I codici brevi con cui le pagine ricordate sono state lette.
	 *
	 * Se chi possiede `[Albo]` cambia dopo la lettura, le pagine si rileggono
	 * (A-221).
	 *
	 * @var array<int, string>
	 */
	private static array $codici_letti = array();

	/**
	 * In questa richiesta la scheda chiesta e' stata trasformata in "non trovata".
	 *
	 * Dopo `set_404()` la richiesta non e' piu' una pagina singola, quindi
	 * guardarla di nuovo non direbbe piu' che e' la pagina dell'albo: lo si
	 * ricorda qui, per dare alla risposta le stesse regole (A-205).
	 *
	 * @var bool
	 */
	private static bool $scheda_negata = false;

	/**
	 * In questa richiesta si chiede il documento di un atto che non e' in pubblicazione.
	 *
	 * @var bool
	 */
	private static bool $consegna_negata = false;

	/**
	 * L'atto in pubblicazione a cui porta il vecchio indirizzo chiesto, o zero.
	 *
	 * @var int
	 */
	private static int $rinvio = 0;

	/**
	 * Quante volte il codice breve ha disegnato in questa richiesta.
	 *
	 * @var int
	 */
	private static int $istanze = 0;

	/**
	 * Le direttive per un motore specifico messe da parte per la riga propria.
	 *
	 * @var array<int, string>
	 */
	private static array $specifiche = array();

	/**
	 * Chi manda le righe di intestazione, nelle prove.
	 *
	 * @var callable|null
	 */
	private static $emettitore = null;

	/**
	 * Aggancia codice breve e regole.
	 */
	public static function aggancia(): void {
		add_shortcode( self::CODICE_BREVE, array( self::class, 'da_codice_breve' ) );
		add_action( 'init', array( self::class, 'da_init' ), 0 );
		add_action( 'template_redirect', array( self::class, 'da_template_redirect' ), 0 );

		add_action( 'parse_request', array( self::class, 'da_parse_request' ), 9 );
		add_filter( 'is_post_type_viewable', array( self::class, 'da_is_post_type_viewable' ), 10, 2 );
		add_filter( 'post_type_link', array( self::class, 'da_post_type_link' ), 10, 2 );
		add_filter( 'pre_handle_404', array( self::class, 'da_pre_handle_404' ), 10, 2 );
		add_filter( 'wp_robots', array( self::class, 'da_wp_robots' ), PHP_INT_MAX );
		add_filter( 'wp_headers', array( self::class, 'da_wp_headers' ), PHP_INT_MAX );
		add_action( 'send_headers', array( self::class, 'da_send_headers' ), PHP_INT_MAX );
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
	 * Dimentica cio' che si sa della richiesta in corso.
	 */
	public static function azzera_richiesta(): void {
		self::$scheda_negata   = false;
		self::$consegna_negata = false;
		self::$rinvio          = 0;
		self::$istanze         = 0;
		self::$specifiche      = array();
	}

	/**
	 * Prende i codici brevi di "Albo Pretorio On line" rimasti liberi.
	 *
	 * Quel plugin li registra quando si carica, prima di `init`: se e' acceso
	 * li trova occupati e non li tocca. Se li prende, la pagina con `[Albo]`
	 * diventa la pagina dell'albo, con ogni attributo ignorato: `stato`, `cat`
	 * e gli altri chiedevano scaduti o filtri che qui non esistono (A-220).
	 */
	public static function da_init(): void {
		if ( ! shortcode_exists( self::CODICE_VECCHIO ) ) {
			add_shortcode( self::CODICE_VECCHIO, array( self::class, 'da_codice_breve' ) );
		}

		foreach ( self::CODICI_VECCHI_MUTI as $codice ) {
			if ( ! shortcode_exists( $codice ) ) {
				add_shortcode( $codice, array( self::class, 'da_codice_muto' ) );
			}
		}

		self::azzera();
	}

	/**
	 * I codici brevi che disegnano la pagina dell'albo: il suo, e `[Albo]` se e' dell'albo.
	 *
	 * Chi possiede un codice breve si guarda adesso, non alla registrazione:
	 * un componente che lo registra dopo lo prende per se', e allora quelle
	 * pagine non sono piu' dell'albo (A-221).
	 *
	 * @return array<int, string>
	 */
	public static function codici(): array {
		global $shortcode_tags;

		$codici = array( self::CODICE_BREVE );

		if ( isset( $shortcode_tags[ self::CODICE_VECCHIO ] ) && array( self::class, 'da_codice_breve' ) === $shortcode_tags[ self::CODICE_VECCHIO ] ) {
			$codici[] = self::CODICE_VECCHIO;
		}

		return $codici;
	}

	/**
	 * Sostituisce l'invio delle righe di intestazione.
	 *
	 * @internal Solo per le prove: nella riga di comando `header()` non lascia
	 *           traccia, e le righe che partono davvero sono cio' che A-205 e
	 *           A-206 guardano.
	 *
	 * @param callable|null $emettitore Funzione che riceve la riga e se sostituisce.
	 */
	public static function fissa_emettitore( ?callable $emettitore ): void {
		self::$emettitore = $emettitore;
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

		$codici = self::codici();

		if ( null !== self::$pagine && $codici === self::$codici_letti ) {
			return self::$pagine;
		}

		// Con l'albo non ancora avviato non si ricorda niente: la domanda si rifa' quando lo e'.
		if ( ! self::albo_attivo() ) {
			return array();
		}

		self::$pagine       = array();
		self::$codici_letti = $codici;

		$tipi = array_values(
			array_diff(
				get_post_types( array( 'public' => true ) ),
				array( 'attachment', TIPO )
			)
		);

		if ( array() === $tipi ) {
			return self::$pagine;
		}

		$simili = implode( ' OR ', array_fill( 0, count( $codici ), 'post_content LIKE %s' ) );
		$righe  = Lettura::righe(
			$wpdb->prepare(
				"SELECT ID, post_type, post_content FROM %i WHERE post_status = 'publish' AND post_password = '' AND ( {$simili} ) ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- solo segnaposto, uno per codice breve.
				array_merge(
					array( $wpdb->posts ),
					array_map(
						static function ( string $codice ) use ( $wpdb ): string {
							return '%' . $wpdb->esc_like( '[' . $codice ) . '%';
						},
						$codici
					)
				)
			),
			array( 'ID', 'post_type', 'post_content' )
		);

		if ( null === $righe ) {
			return self::$pagine;
		}

		foreach ( $righe as $riga ) {
			if ( ! in_array( (string) $riga['post_type'], $tipi, true ) ) {
				continue;
			}

			foreach ( $codici as $codice ) {
				if ( has_shortcode( (string) $riga['post_content'], $codice ) ) {
					self::$pagine[] = (int) $riga['ID'];
					break;
				}
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
	 * La risposta in corso e' della pagina dell'albo: la pagina, oppure una sua
	 * scheda trasformata in "non trovata".
	 *
	 * Sono le risposte che portano il divieto ai motori e alle memorie. La
	 * "non trovata" di una scheda li porta come la pagina: e' l'indirizzo
	 * della pagina con un parametro, e senza divieto un motore potrebbe
	 * conservarla, oppure una memoria servirla dopo che l'atto e' tornato
	 * consultabile (A-205).
	 *
	 * @return bool
	 */
	public static function risposta_dell_albo(): bool {
		return self::$scheda_negata || self::richiesta_dell_albo();
	}

	/**
	 * Il nome con cui il punto pubblico di consegna riceve l'atto, o nullo.
	 *
	 * E' parte di ogni indirizzo di consegna gia' stampato, quindi non cambia
	 * senza rompere gli indirizzi; se non si trova, il tipo non si dichiara
	 * consultabile e nessun documento esce dal punto pubblico (A-204).
	 *
	 * @return string|null
	 */
	private static function chiave_consegna(): ?string {
		$costante = '\Conformita_Core_Consegna::VAR_ATTO';

		if ( ! defined( $costante ) ) {
			return null;
		}

		$chiave = constant( $costante );

		return is_string( $chiave ) && '' !== $chiave ? $chiave : null;
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
	 * L'atto a cui porta un vecchio indirizzo di "Albo Pretorio On line", se l'indirizzo e' uno di quelli.
	 *
	 * Falso se l'indirizzo non e' una scheda, una stampa o un documento di quel
	 * plugin, o se chiede gia' una scheda di questo albo, che vale per prima.
	 * Zero se lo e' ma non porta a nessun atto importato: numero interno
	 * mancante o scritto male, mai importato, o la banca dati che non risponde.
	 * Il numero interno si legge con la regola della scheda, sole cifre senza
	 * zeri davanti: sono gli indirizzi che quel plugin stampava.
	 *
	 * @param array<string, mixed> $richiesta Parametri della richiesta.
	 * @return int|false
	 */
	public static function atto_del_vecchio_indirizzo( array $richiesta ) {
		if ( array_key_exists( self::PARAMETRO_ATTO, $richiesta ) || ! isset( $richiesta['action'] ) || ! is_string( $richiesta['action'] ) || ! isset( self::AZIONI_VECCHIE[ $richiesta['action'] ] ) ) {
			return false;
		}

		$parametro = self::AZIONI_VECCHIE[ $richiesta['action'] ];
		$origine   = self::atto_chiesto( array( self::PARAMETRO_ATTO => $richiesta[ $parametro ] ?? '' ) );

		return 0 === $origine ? 0 : self::atto_di_origine( (string) $origine );
	}

	/**
	 * L'atto che l'importazione ha portato da quel numero interno, o zero.
	 *
	 * Uno solo, in qualunque stato: se due atti ricordano la stessa origine,
	 * anche uno in bozza o annullato, nessuno, perche' l'indirizzo non saprebbe
	 * quale dei due e' il suo. Se l'atto e' in pubblicazione lo dice poi
	 * `in_pubblicazione()` (A-222).
	 *
	 * @param string $origine Numero interno in "Albo Pretorio On line".
	 * @return int
	 */
	private static function atto_di_origine( string $origine ): int {
		global $wpdb;

		$righe = Lettura::righe(
			$wpdb->prepare(
				'SELECT DISTINCT p.ID FROM %i p JOIN %i m ON m.post_id = p.ID WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_type = %s ORDER BY p.ID ASC',
				$wpdb->posts,
				$wpdb->postmeta,
				META_ORIGINE_IMPORTAZIONE,
				$origine,
				TIPO
			),
			array( 'ID' )
		);

		if ( null === $righe || 1 !== count( $righe ) ) {
			return 0;
		}

		return (int) $righe[0]['ID'];
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

		/*
		 * Anche una lettura dell'interfaccia per programmi fatta da dentro una
		 * pagina, non solo una richiesta all'interfaccia: `wp_is_rest_endpoint()`
		 * risponde per tutte e due (A-200).
		 */
		if ( wp_is_rest_endpoint() ) {
			return '';
		}

		++self::$istanze;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica: vedi sicurezza.md.
		$html = VistaPubblica::disegna( $_GET, Passaggi::ora() );

		/*
		 * Due codici brevi nella stessa pagina disegnano due moduli: dal
		 * secondo gli identificativi prendono il numero dell'istanza, cosi'
		 * etichette e descrizioni puntano al proprio modulo (A-207). Non si
		 * disegna una volta sola: un'estensione che legge il contenuto per
		 * conto suo prima del tema consumerebbe l'unica istanza.
		 */
		return 1 === self::$istanze ? $html : VistaPubblica::distingui( $html, self::$istanze );
	}

	/**
	 * Aggancio dei codici brevi di "Albo Pretorio On line" che qui non disegnano niente.
	 *
	 * @param mixed $attributi Attributi, ignorati.
	 * @return string
	 */
	public static function da_codice_muto( $attributi = array() ): string {
		unset( $attributi );

		return '';
	}

	/**
	 * Un vecchio indirizzo di un atto in pubblicazione porta alla sua scheda nuova.
	 *
	 * Prima del tema e degli altri rinvii di WordPress. Il rinvio e'
	 * temporaneo e non si conserva: l'indirizzo vecchio vale solo finche'
	 * l'atto e' in pubblicazione, e la scheda nuova ricontrolla l'atto quando
	 * si apre (A-222, A-223).
	 *
	 * L'atto si ricontrolla qui. Se fra la lettura della richiesta e questo
	 * momento e' passata la mezzanotte, o il rinvio non riesce, la risposta e'
	 * la stessa di un atto mai esistito, non la pagina dell'elenco (A-222).
	 */
	public static function da_template_redirect(): void {
		$atto = self::$rinvio;

		if ( $atto < 1 ) {
			return;
		}

		self::$rinvio = 0;

		$scheda = self::richiesta_dell_albo() && self::in_pubblicazione( $atto ) ? self::indirizzo_scheda( $atto ) : null;

		if ( null !== $scheda ) {
			nocache_headers();

			if ( wp_safe_redirect( $scheda, 302, 'Albo Pretorio' ) ) {
				exit;
			}
		}

		if ( $GLOBALS['wp_query'] instanceof \WP_Query ) {
			self::nega( $GLOBALS['wp_query'] );
		}
	}

	/**
	 * All'inizio della richiesta: si dimentica la precedente, e si guarda se
	 * chiede il documento di un atto che la pagina non mostra.
	 *
	 * Prima del punto pubblico di consegna, che sta sullo stesso aggancio
	 * alla priorita' normale (A-204).
	 *
	 * @param mixed $wp Ambiente della richiesta.
	 */
	public static function da_parse_request( $wp ): void {
		self::azzera_richiesta();

		$chiave = self::chiave_consegna();

		if ( null === $chiave || ! is_object( $wp ) || ! isset( $wp->query_vars ) || ! is_array( $wp->query_vars ) || ! isset( $wp->query_vars[ $chiave ] ) ) {
			return;
		}

		/*
		 * Lo stesso atto che leggera' il punto di consegna, con la stessa
		 * conversione: `123x` per lui e' l'atto 123, e un controllo che
		 * leggesse il valore in un altro modo guarderebbe un atto diverso da
		 * quello consegnato (A-204).
		 */
		$atto = (int) $wp->query_vars[ $chiave ];

		if ( $atto > 0 && TIPO === get_post_type( $atto ) && ! self::in_pubblicazione( $atto ) ) {
			self::$consegna_negata = true;
		}
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
	 * **Non per ogni atto.** Il punto di consegna guarda pubblicazione, password
	 * e scadenza, ma non il numero di registro, e una password data con il
	 * biscotto di WordPress gli basta: la pagina invece non mostra un atto
	 * senza numero ne' uno con password. Nella richiesta del documento di un
	 * atto che la pagina non mostra l'albo non apre il tipo, e il punto di
	 * consegna risponde con il suo rifiuto, uguale per ogni motivo (A-204). Se
	 * il tipo lo ha aperto un altro componente, resta la sua scelta, come per
	 * l'anteprima incorporata (A-175, A-177).
	 *
	 * @param mixed $consultabile Esito calcolato da WordPress.
	 * @param mixed $tipo         Oggetto del tipo.
	 * @return mixed
	 */
	public static function da_is_post_type_viewable( $consultabile, $tipo ) {
		if ( ! $tipo instanceof \WP_Post_Type || TIPO !== $tipo->name ) {
			return $consultabile;
		}

		if ( self::$consegna_negata ) {
			return $consultabile;
		}

		return array() !== self::pagine() && null !== self::chiave_consegna() ? true : $consultabile;
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

		if ( false === $atto ) {
			/*
			 * Un vecchio indirizzo di "Albo Pretorio On line": porta alla scheda
			 * nuova solo un atto in pubblicazione. Tutto il resto, scaduto,
			 * annullato, mai importato o scritto male, risponde come un atto
			 * mai esistito: un rinvio, o una risposta diversa, direbbe che
			 * l'atto c'e' (A-222, A-223).
			 */
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lettura pubblica, nessuna modifica.
			$atto = self::atto_del_vecchio_indirizzo( $_GET );

			if ( false === $atto ) {
				return $anticipato;
			}

			/*
			 * Il rinvio parte dopo, prima del tema. Intanto nessuno stato:
			 * `true` ferma il 200 di WordPress, e se all'ultimo l'atto non
			 * e' piu' in pubblicazione la risposta resta da scrivere tutta.
			 */
			if ( self::in_pubblicazione( $atto ) ) {
				self::$rinvio = $atto;

				return true;
			}
		} elseif ( self::in_pubblicazione( $atto ) ) {
			return $anticipato;
		}

		self::nega( $interrogazione );

		return true;
	}

	/**
	 * La risposta "non trovata" della pagina dell'albo, uguale per ogni atto che non si mostra.
	 *
	 * @param \WP_Query $interrogazione Interrogazione principale.
	 */
	private static function nega( \WP_Query $interrogazione ): void {
		self::$scheda_negata = true;

		$interrogazione->set_404();

		/*
		 * `set_404()` non toglie i risultati: la pagina dell'albo resterebbe
		 * nell'interrogazione, e un modello 404 che li percorre la
		 * disegnerebbe. Una "non trovata" vera non ha risultati (A-196).
		 */
		$interrogazione->posts         = array();
		$interrogazione->post          = null;
		$interrogazione->post_count    = 0;
		$interrogazione->found_posts   = 0;
		$interrogazione->max_num_pages = 0;
		$interrogazione->current_post  = -1;

		status_header( 404 );
		nocache_headers();
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
		if ( ! is_array( $direttive ) || ! self::risposta_dell_albo() ) {
			return $direttive;
		}

		unset( $direttive['index'] );
		$direttive['noindex'] = true;

		return $direttive;
	}

	/**
	 * Il divieto ai motori e alle memorie nell'intestazione della risposta.
	 *
	 * `X-Robots-Tag: noindex` e le intestazioni con cui WordPress vieta di
	 * conservare una risposta: l'elenco di oggi non deve uscire da una memoria
	 * dopo la mezzanotte.
	 *
	 * Il valore generale gia' presente si conserva e riceve il divieto in coda,
	 * se non lo ha gia' come direttiva a se'. Un valore che nomina un motore,
	 * come `googlebot: nofollow`, non si tocca: dopo il nome del motore le
	 * direttive valgono per quel motore soltanto, quindi un `noindex` aggiunto
	 * in coda non varrebbe per gli altri, e uno gia' presente in coda non e'
	 * un divieto generale. Si mette da parte ed esce intero su una riga
	 * propria, accanto alla riga generale (A-206).
	 *
	 * @param mixed $intestazioni Intestazioni.
	 * @return mixed
	 */
	public static function da_wp_headers( $intestazioni ) {
		self::$specifiche = array();

		if ( ! is_array( $intestazioni ) || ! self::risposta_dell_albo() ) {
			return $intestazioni;
		}

		$generali = array();

		foreach ( array_keys( $intestazioni ) as $nome ) {
			if ( ! is_string( $nome ) || 0 !== strcasecmp( $nome, 'X-Robots-Tag' ) ) {
				continue;
			}

			$valore = trim( (string) $intestazioni[ $nome ] );
			unset( $intestazioni[ $nome ] );

			if ( '' === $valore ) {
				continue;
			}

			if ( self::rivolto_a_un_motore( $valore ) ) {
				self::$specifiche[] = $valore;
			} else {
				$generali[] = $valore;
			}
		}

		$robot = implode( ', ', $generali );

		if ( ! self::contiene_divieto( $robot ) ) {
			$robot = '' === $robot ? 'noindex' : $robot . ', noindex';
		}

		$intestazioni['X-Robots-Tag'] = $robot;

		return array_merge( $intestazioni, wp_get_nocache_headers() );
	}

	/**
	 * Il divieto su righe proprie, dopo che WordPress ha mandato le sue.
	 *
	 * Le direttive per un motore messe da parte e, sempre, una riga generale
	 * `X-Robots-Tag: noindex` a se', aggiunte e mai in sostituzione: un altro
	 * componente che su `send_headers` manda la stessa intestazione
	 * sostituirebbe la riga di WordPress, divieto compreso (A-205, A-206).
	 */
	public static function da_send_headers(): void {
		$specifiche       = self::$specifiche;
		self::$specifiche = array();

		if ( ! self::risposta_dell_albo() ) {
			return;
		}

		foreach ( $specifiche as $valore ) {
			self::manda( 'X-Robots-Tag: ' . $valore );
		}

		self::manda( 'X-Robots-Tag: noindex' );
	}

	/**
	 * Manda una riga di intestazione in aggiunta alle altre con lo stesso nome.
	 *
	 * @param string $riga Riga.
	 */
	private static function manda( string $riga ): void {
		if ( null !== self::$emettitore ) {
			call_user_func( self::$emettitore, $riga, false );

			return;
		}

		if ( ! headers_sent() ) {
			header( $riga, false );
		}
	}

	/**
	 * Il valore nomina un motore: una sua parte comincia con un nome seguito
	 * dai due punti che non e' una direttiva con un valore.
	 *
	 * Nel dubbio conta come rivolto a un motore: costa una riga in piu',
	 * perche' il divieto generale esce comunque su una riga propria.
	 *
	 * @param string $valore Valore di `X-Robots-Tag`.
	 * @return bool
	 */
	private static function rivolto_a_un_motore( string $valore ): bool {
		$con_valore = array( 'max-snippet', 'max-image-preview', 'max-video-preview', 'unavailable_after' );

		foreach ( explode( ',', $valore ) as $parte ) {
			if ( 1 === preg_match( '/^\s*([A-Za-z0-9_.-]+)\s*:/', $parte, $parti ) && ! in_array( strtolower( $parti[1] ), $con_valore, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Il valore generale vieta gia' l'indicizzazione, con `noindex` o `none`.
	 *
	 * @param string $valore Valore generale di `X-Robots-Tag`.
	 * @return bool
	 */
	private static function contiene_divieto( string $valore ): bool {
		foreach ( explode( ',', $valore ) as $direttiva ) {
			if ( in_array( strtolower( trim( $direttiva ) ), array( 'noindex', 'none' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Le estensioni di memoria di pagina non conservano la pagina dell'albo.
	 *
	 * `DONOTCACHEPAGE` e' la costante che le estensioni piu' diffuse leggono
	 * prima di conservare una risposta. Una costante non si toglie: una volta
	 * definita vale fino alla fine della richiesta, che e' quella dell'albo.
	 */
	public static function da_wp(): void {
		if ( self::risposta_dell_albo() && ! defined( 'DONOTCACHEPAGE' ) ) {
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
