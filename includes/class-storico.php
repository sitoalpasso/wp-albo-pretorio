<?php
/**
 * Lo storico pubblico: la seconda sezione, il suo tipo e l'impostazione (ALBO-36).
 *
 * Lo storico non riapre l'atto scaduto. Mette a disposizione una copia
 * oscurata, che e' un contenuto distinto, di un tipo proprio, registrato dal
 * meccanismo comune in una sezione propria con la stessa politica dell'albo:
 * indicizzazione vietata e scadenza `irraggiungibile`. Ogni copia ha una fine
 * calcolata all'ingresso con gli anni che l'amministrazione ha dichiarato, e
 * alla fine sparisce per la data letta come ogni altro contenuto governato.
 *
 * Righe di collaudo A-261..A-265.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Sezione, tipo e impostazione dello storico pubblico.
 */
final class Storico {

	/**
	 * L'opzione dell'impostazione.
	 *
	 * @var string
	 */
	const OPZIONE = 'albo_pretorio_storico_pubblico';

	/**
	 * Scelta: lo storico e' acceso.
	 *
	 * @var string
	 */
	const ACCESO = 'acceso';

	/**
	 * Scelta: lo storico e' spento.
	 *
	 * @var string
	 */
	const SPENTO = 'spento';

	/**
	 * Il numero di anni piu' alto che si accetta.
	 *
	 * Non e' una durata scelta al posto dell'amministrazione: e' il confine oltre
	 * il quale un numero e' un errore di battitura. Una copia che restasse in rete
	 * per un secolo sarebbe una copia senza fine, che lo storico non ammette.
	 *
	 * @var int
	 */
	const ANNI_MASSIMI = 99;

	/**
	 * L'azione della voce di registro di ogni salvataggio.
	 *
	 * @var string
	 */
	const AZIONE_IMPOSTAZIONE = 'impostazione_storico';

	/**
	 * Stato dell'opzione letta: non c'e'.
	 *
	 * @var string
	 */
	const ASSENTE = 'assente';

	/**
	 * Stato dell'opzione letta: c'e' e ha la forma che il salvataggio scrive.
	 *
	 * @var string
	 */
	const VALIDA = 'valida';

	/**
	 * Stato dell'opzione letta: c'e', ma non ha quella forma.
	 *
	 * @var string
	 */
	const MALFORMATA = 'malformata';

	/**
	 * Stato dell'opzione letta: la banca dati non ha risposto.
	 *
	 * @var string
	 */
	const ILLEGGIBILE = 'illeggibile';

	/**
	 * Secondi di attesa del blocco dei salvataggi.
	 *
	 * @var int
	 */
	const ATTESA_BLOCCO = 5;

	/**
	 * Sezione e tipo risultano registrati da questo componente.
	 *
	 * @var bool
	 */
	private static $pronto = false;

	/**
	 * L'oggetto del tipo come lo abbiamo ricevuto alla registrazione.
	 *
	 * Stessa ragione dell'oggetto del tipo atto: il confronto per identita'
	 * dice che il tipo nel registro e' ancora il nostro.
	 *
	 * @var \WP_Post_Type|null
	 */
	private static $oggetto_tipo = null;

	/**
	 * La sezione e' stata registrata in questa richiesta.
	 *
	 * @var bool
	 */
	private static $sezione = false;

	/**
	 * Messaggi da mostrare a chi puo' rimediare.
	 *
	 * @var array<int, string>
	 */
	private static $avvisi = array();

	/**
	 * Un salvataggio e' in corso in questa richiesta.
	 *
	 * @var bool
	 */
	private static $in_salvataggio = false;

	/**
	 * Aggancio a `init`, dopo il tipo atto.
	 */
	public static function da_init(): void {
		self::registra();
	}

	/**
	 * Registra la seconda sezione e il tipo dello storico.
	 *
	 * Se il meccanismo comune rifiuta, lo storico resta fermo con un avviso e
	 * l'albo lavora come prima: lo storico e' una funzione in piu', non una
	 * condizione dell'albo.
	 *
	 * @return true|\WP_Error
	 */
	public static function registra() {
		if ( self::pronto() ) {
			return true;
		}

		if ( ! Avvio::registrata() ) {
			return new \WP_Error(
				'albo_copia_senza_albo',
				__( 'Lo storico pubblico non si registra perche\' la sezione dell\'albo non risulta registrata da questo componente.', 'albo-pretorio-pa' )
			);
		}

		if ( ! self::$sezione ) {
			$esito = conformita_core_registra_sezione(
				SEZIONE_STORICO,
				array(
					'indicizzazione' => INDICIZZAZIONE,
					'scadenza'       => SCADENZA,
				)
			);

			if ( true !== $esito ) {
				return self::fermo( $esito );
			}

			self::$sezione = true;
		}

		$esito = conformita_core_registra_tipo(
			TIPO_STORICO,
			array(

				/*
				 * Spenta: la copia si legge soltanto dalla pagina dello storico,
				 * che decide da se' che cosa mostrare. Il controllore REST di
				 * WordPress considera leggibile da chiunque ogni contenuto
				 * pubblicato, senza guardare se lo storico e' acceso.
				 */
				'show_in_rest' => false,
				'sezione'      => SEZIONE_STORICO,
				'argomenti'    => self::argomenti(),
			)
		);

		if ( is_wp_error( $esito ) ) {
			return self::fermo( $esito );
		}

		self::$oggetto_tipo = get_post_type_object( TIPO_STORICO );
		self::$pronto       = true;
		self::$avvisi       = array();

		return true;
	}

	/**
	 * Sezione e tipo dello storico sono registrati e il tipo e' ancora il nostro.
	 *
	 * @return bool
	 */
	public static function pronto(): bool {
		return self::$pronto && null !== self::$oggetto_tipo && get_post_type_object( TIPO_STORICO ) === self::$oggetto_tipo;
	}

	/**
	 * Argomenti di registrazione del tipo, scritti uno per uno.
	 *
	 * **Nessuna interfaccia di WordPress.** La copia si crea e si toglie soltanto
	 * dalle schermate dell'albo, che pretendono il file oscurato e la conferma:
	 * la schermata generica di un contenuto le salterebbe tutte e due. I permessi
	 * del tipo che il meccanismo comune crea non vanno a nessun ruolo.
	 *
	 * @return array<string, mixed>
	 */
	private static function argomenti(): array {
		return array(
			'labels'              => array(
				'name'          => _x( 'Copie nello storico', 'nome plurale del tipo di contenuto', 'albo-pretorio-pa' ),
				'singular_name' => _x( 'Copia nello storico', 'nome singolare del tipo di contenuto', 'albo-pretorio-pa' ),
			),
			'description'         => __( 'Copie oscurate di atti la cui pubblicazione all\'albo e\' terminata, per la sola consultazione.', 'albo-pretorio-pa' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}

	/**
	 * Registra il rifiuto e lo restituisce.
	 *
	 * @param mixed $esito Esito del meccanismo comune.
	 * @return \WP_Error
	 */
	private static function fermo( $esito ): \WP_Error {
		$errore = is_wp_error( $esito ) ? $esito : new \WP_Error( 'albo_copia_non_registrato', __( 'Esito non riconosciuto.', 'albo-pretorio-pa' ) );

		self::$avvisi = array(
			sprintf(
				/* translators: 1: nome del componente, 2: motivo riportato dal meccanismo comune. */
				__( '%1$s: lo storico pubblico resta fermo, l\'albo lavora come sempre. %2$s', 'albo-pretorio-pa' ),
				NOME,
				$errore->get_error_message()
			),
		);

		return $errore;
	}

	/**
	 * Aggancio a `admin_notices`: il rifiuto della registrazione, a chi puo' rimediare.
	 */
	public static function mostra_avviso(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( self::$avvisi as $messaggio ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $messaggio ) );
		}
	}

	/**
	 * L'opzione come sta adesso nella banca dati.
	 *
	 * **Si legge dalla banca dati a ogni chiamata**, senza la memoria delle
	 * opzioni di WordPress. `get_option()` risponde con il valore predefinito
	 * sia quando l'opzione non c'e' sia quando la lettura fallisce: qui le due
	 * cose si distinguono, e il salvataggio con una lettura fallita non scrive
	 * niente. E la memoria non e' la banca dati: la rilettura dopo una
	 * scrittura deve guardare la banca dati, o non prova niente.
	 *
	 * @return array{esito: string, grezzo: string|null, impostazione: array{scelta: string, anni: int|null, utente: int, istante: string}|null}
	 */
	public static function fotografia(): array {
		global $wpdb;

		$righe = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPZIONE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura fresca che distingue l'errore dall'assenza, vedi sopra.

		if ( '' !== $wpdb->last_error || ! is_array( $righe ) || count( $righe ) > 1 ) {
			return array(
				'esito'        => self::ILLEGGIBILE,
				'grezzo'       => null,
				'impostazione' => null,
			);
		}

		if ( array() === $righe ) {
			return array(
				'esito'        => self::ASSENTE,
				'grezzo'       => null,
				'impostazione' => null,
			);
		}

		$impostazione = self::controlla( maybe_unserialize( (string) $righe[0] ) );

		return array(
			'esito'        => null === $impostazione ? self::MALFORMATA : self::VALIDA,
			'grezzo'       => (string) $righe[0],
			'impostazione' => $impostazione,
		);
	}

	/**
	 * L'impostazione salvata, se si legge per intero.
	 *
	 * Un valore che non ha la forma che il salvataggio scrive non vale, e
	 * nemmeno una lettura che fallisce: lo storico e' spento, e la schermata
	 * lo dice.
	 *
	 * @return array{scelta: string, anni: int|null, utente: int, istante: string}|null
	 */
	public static function impostazione(): ?array {
		return self::fotografia()['impostazione'];
	}

	/**
	 * Lo stato dell'opzione in una riga, da portare nel modulo della schermata.
	 *
	 * Il salvataggio lo confronta con lo stato che trova: se sono diversi,
	 * qualcuno ha salvato mentre il modulo era aperto, e il modulo vecchio non
	 * scrive sopra la scelta nuova. Vuoto se la lettura fallisce: un modulo
	 * aperto cosi' non salva.
	 *
	 * @param array{esito: string, grezzo: string|null, impostazione: array<string, mixed>|null}|null $fotografia Fotografia gia' letta, o nulla per leggerla.
	 * @return string
	 */
	public static function stato_mostrato( ?array $fotografia = null ): string {
		$fotografia = $fotografia ?? self::fotografia();

		if ( self::ILLEGGIBILE === $fotografia['esito'] ) {
			return '';
		}

		return null === $fotografia['grezzo'] ? self::ASSENTE : md5( $fotografia['grezzo'] );
	}

	/**
	 * Il valore letto se ha la forma dell'impostazione, oppure nullo.
	 *
	 * @param mixed $valore Valore letto.
	 * @return array{scelta: string, anni: int|null, utente: int, istante: string}|null
	 */
	private static function controlla( $valore ): ?array {
		if ( ! is_array( $valore ) ) {
			return null;
		}

		$scelta  = $valore['scelta'] ?? null;
		$anni    = $valore['anni'] ?? null;
		$utente  = $valore['utente'] ?? null;
		$istante = $valore['istante'] ?? null;

		if ( ! is_string( $scelta ) || ! in_array( $scelta, array( self::ACCESO, self::SPENTO ), true ) ) {
			return null;
		}

		if ( null !== $anni && ( ! is_int( $anni ) || $anni < 1 || $anni > self::ANNI_MASSIMI ) ) {
			return null;
		}

		if ( self::ACCESO === $scelta && null === $anni ) {
			return null;
		}

		if ( ! is_int( $utente ) || ! is_string( $istante ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $istante ) ) {
			return null;
		}

		return array(
			'scelta'  => $scelta,
			'anni'    => $anni,
			'utente'  => $utente,
			'istante' => $istante,
		);
	}

	/**
	 * Lo storico e' acceso: c'e' una scelta salvata, e la scelta e' accendere.
	 *
	 * @return bool
	 */
	public static function acceso(): bool {
		$impostazione = self::impostazione();

		return null !== $impostazione && self::ACCESO === $impostazione['scelta'];
	}

	/**
	 * Gli anni per cui una copia resta nello storico, se lo storico e' acceso.
	 *
	 * @return int|null
	 */
	public static function anni(): ?int {
		$impostazione = self::impostazione();

		return null !== $impostazione && self::ACCESO === $impostazione['scelta'] ? $impostazione['anni'] : null;
	}

	/**
	 * Salva la scelta e la scrive nel registro, tutto o niente.
	 *
	 * Gli anni si chiedono soltanto per accendere. Spegnendo si possono
	 * lasciare: se ci sono si controllano, e restano scritti per la volta dopo.
	 * I salvataggi vanno in fila, uno alla volta, dalla lettura alla rilettura.
	 * Un modulo aperto prima di un altro salvataggio non scrive sopra la
	 * scelta nuova; una scelta uguale a quella in vigore non scrive niente.
	 *
	 * @param mixed              $scelta   Scelta inviata.
	 * @param mixed              $anni     Anni inviati, come testo.
	 * @param mixed              $mostrato Stato dell'opzione all'apertura del modulo, da `stato_mostrato()`.
	 * @param int                $utente   Chi salva.
	 * @param \DateTimeImmutable $adesso   Istante del salvataggio.
	 * @return true|\WP_Error
	 */
	public static function salva( $scelta, $anni, $mostrato, int $utente, \DateTimeImmutable $adesso ) {
		global $wpdb;

		if ( ! is_string( $scelta ) || ! in_array( $scelta, array( self::ACCESO, self::SPENTO ), true ) ) {
			return new \WP_Error(
				'albo_copia_scelta_mancante',
				__( 'Impostazione non salvata: va scelto se lo storico pubblico e\' acceso o spento.', 'albo-pretorio-pa' )
			);
		}

		$numero = self::leggi_anni( $anni );

		if ( is_wp_error( $numero ) ) {
			return $numero;
		}

		if ( self::ACCESO === $scelta && null === $numero ) {
			return new \WP_Error(
				'albo_copia_anni_mancanti',
				__( 'Impostazione non salvata: per accendere lo storico va scritto per quanti anni ogni copia resta consultabile.', 'albo-pretorio-pa' )
			);
		}

		if ( ! self::pronto() ) {
			return new \WP_Error(
				'albo_copia_non_registrato',
				__( 'Impostazione non salvata: lo storico pubblico non risulta registrato, quindi la scelta non si potrebbe scrivere nel registro.', 'albo-pretorio-pa' )
			);
		}

		/*
		 * Il blocco della banca dati vale per la connessione, e la stessa
		 * connessione lo riprende senza attendere: un salvataggio chiamato da
		 * un aggancio di questo salvataggio passerebbe. Si rifiuta qui.
		 */
		if ( self::$in_salvataggio ) {
			return self::occupato();
		}

		$blocco = self::nome_blocco();

		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $blocco, self::ATTESA_BLOCCO ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- blocco della fila, non dati.
			return self::occupato();
		}

		self::$in_salvataggio = true;

		try {
			return self::salva_in_fila( $scelta, $numero, $mostrato, $utente, $adesso );
		} finally {
			self::$in_salvataggio = false;
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $blocco ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- blocco della fila, non dati.
		}
	}

	/**
	 * Il salvataggio, con il blocco preso.
	 *
	 * @param string             $scelta   Scelta controllata.
	 * @param int|null           $numero   Anni controllati.
	 * @param mixed              $mostrato Stato dell'opzione all'apertura del modulo.
	 * @param int                $utente   Chi salva.
	 * @param \DateTimeImmutable $adesso   Istante del salvataggio.
	 * @return true|\WP_Error
	 */
	private static function salva_in_fila( string $scelta, ?int $numero, $mostrato, int $utente, \DateTimeImmutable $adesso ) {
		$prima = self::fotografia();

		// Con la lettura fallita non si sa che cosa si sovrascrive, ne' che cosa si rimetterebbe: non si scrive.
		if ( self::ILLEGGIBILE === $prima['esito'] ) {
			return new \WP_Error(
				'albo_copia_lettura',
				__( 'Impostazione non salvata: l\'impostazione in vigore non si legge in questo momento, e senza leggerla non si scrive. Riprovare.', 'albo-pretorio-pa' )
			);
		}

		if ( ! is_string( $mostrato ) || self::stato_mostrato( $prima ) !== $mostrato ) {
			return new \WP_Error(
				'albo_copia_conflitto',
				__( 'Impostazione non salvata: mentre la schermata era aperta l\'impostazione e\' stata cambiata da un altro salvataggio. La schermata mostra ora quella in vigore: rifare la scelta guardando quella.', 'albo-pretorio-pa' )
			);
		}

		// La stessa scelta con gli stessi anni: niente da scrivere, e niente nel registro.
		if ( null !== $prima['impostazione'] && $scelta === $prima['impostazione']['scelta'] && $numero === $prima['impostazione']['anni'] ) {
			return true;
		}

		$valore = array(
			'scelta'  => $scelta,
			'anni'    => $numero,
			'utente'  => $utente,
			'istante' => $adesso->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);

		update_option( self::OPZIONE, $valore, true );

		if ( self::fotografia()['impostazione'] !== $valore ) {
			return self::rifiuto_ripristinato(
				$prima,
				new \WP_Error(
					'albo_copia_non_scritto',
					__( 'Impostazione non salvata: la banca dati non ha confermato la scrittura.', 'albo-pretorio-pa' )
				)
			);
		}

		$voce = conformita_core_registra_voce(
			array(
				'sezione'  => SEZIONE_STORICO,
				'azione'   => self::AZIONE_IMPOSTAZIONE,
				'dettagli' => array(
					'scelta' => $scelta,
					'anni'   => null === $numero ? '' : (string) $numero,
				),
			)
		);

		if ( is_wp_error( $voce ) ) {
			return self::rifiuto_ripristinato(
				$prima,
				new \WP_Error(
					'albo_copia_registro',
					sprintf(
						/* translators: %s: motivo riportato dal registro. */
						__( 'Impostazione non salvata: il registro non ha scritto la voce. %s', 'albo-pretorio-pa' ),
						$voce->get_error_message()
					)
				)
			);
		}

		return true;
	}

	/**
	 * Rimette l'opzione com'era, la rilegge dalla banca dati, e completa il rifiuto.
	 *
	 * @param array{esito: string, grezzo: string|null, impostazione: array<string, mixed>|null} $prima  L'opzione letta prima di scrivere.
	 * @param \WP_Error                                                                          $errore Il rifiuto.
	 * @return \WP_Error
	 */
	private static function rifiuto_ripristinato( array $prima, \WP_Error $errore ): \WP_Error {
		if ( null === $prima['grezzo'] ) {
			delete_option( self::OPZIONE );
		} else {
			update_option( self::OPZIONE, maybe_unserialize( $prima['grezzo'] ), true );
		}

		// La memoria delle opzioni puo' aver tenuto il valore rifiutato: si svuota, e decide la banca dati.
		wp_cache_delete( self::OPZIONE, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		$dopo = self::fotografia();

		if ( $dopo['esito'] === $prima['esito'] && $dopo['grezzo'] === $prima['grezzo'] ) {
			return new \WP_Error( $errore->get_error_code(), $errore->get_error_message() . ' ' . __( 'L\'impostazione e\' tornata com\'era. Riprovare.', 'albo-pretorio-pa' ) );
		}

		return new \WP_Error(
			'albo_copia_non_ripristinato',
			$errore->get_error_message() . ' ' . __( 'E non e\' stato possibile confermare che l\'impostazione sia tornata com\'era: riaprire la schermata e controllare la scelta in vigore prima di fare altro.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Il nome del blocco dei salvataggi, per sito.
	 *
	 * @return string
	 */
	public static function nome_blocco(): string {
		global $wpdb;

		return substr( $wpdb->prefix, 0, 40 ) . 'albo_copie_impostazione';
	}

	/**
	 * Il rifiuto di un salvataggio mentre un altro e' in corso.
	 *
	 * @return \WP_Error
	 */
	private static function occupato(): \WP_Error {
		return new \WP_Error(
			'albo_copia_occupato',
			__( 'Impostazione non salvata: un altro salvataggio dello storico pubblico e\' in corso. Riprovare fra poco.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Gli anni inviati, come numero intero, nullo se il campo e' vuoto.
	 *
	 * Un campo scritto male non diventa vuoto: si rifiuta con il motivo.
	 *
	 * @param mixed $anni Valore inviato.
	 * @return int|null|\WP_Error
	 */
	public static function leggi_anni( $anni ) {
		if ( null === $anni || ( is_string( $anni ) && '' === trim( $anni ) ) ) {
			return null;
		}

		if ( ! is_string( $anni ) || 1 !== preg_match( '/^\d{1,2}$/', trim( $anni ) ) || (int) trim( $anni ) < 1 ) {
			return new \WP_Error(
				'albo_copia_anni_non_validi',
				sprintf(
					/* translators: %d: numero massimo di anni. */
					__( 'Impostazione non salvata: gli anni vanno scritti in cifre, come numero intero da 1 a %d.', 'albo-pretorio-pa' ),
					self::ANNI_MASSIMI
				)
			);
		}

		return (int) trim( $anni );
	}

	/**
	 * Riporta la classe allo stato del caricamento.
	 *
	 * @internal Serve alle prove.
	 */
	public static function azzera(): void {
		self::$pronto         = false;
		self::$oggetto_tipo   = null;
		self::$sezione        = false;
		self::$avvisi         = array();
		self::$in_salvataggio = false;
	}
}
