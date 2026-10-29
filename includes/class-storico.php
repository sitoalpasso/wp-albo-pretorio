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
	 * L'impostazione salvata, se si legge per intero.
	 *
	 * Un valore che non ha la forma che il salvataggio scrive non vale: lo
	 * storico e' spento, e la schermata lo dice.
	 *
	 * @return array{scelta: string, anni: int|null, utente: int, istante: string}|null
	 */
	public static function impostazione(): ?array {
		$valore = get_option( self::OPZIONE, null );

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
	 *
	 * @param mixed              $scelta Scelta inviata.
	 * @param mixed              $anni   Anni inviati, come testo.
	 * @param int                $utente Chi salva.
	 * @param \DateTimeImmutable $adesso Istante del salvataggio.
	 * @return true|\WP_Error
	 */
	public static function salva( $scelta, $anni, int $utente, \DateTimeImmutable $adesso ) {
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

		$precedente = get_option( self::OPZIONE, null );
		$valore     = array(
			'scelta'  => $scelta,
			'anni'    => $numero,
			'utente'  => $utente,
			'istante' => $adesso->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);

		update_option( self::OPZIONE, $valore, true );

		if ( self::impostazione() !== $valore ) {
			self::ripristina( $precedente );

			return new \WP_Error(
				'albo_copia_non_scritto',
				__( 'Impostazione non salvata: la banca dati non ha confermato la scrittura. Riprovare.', 'albo-pretorio-pa' )
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
			self::ripristina( $precedente );

			return new \WP_Error(
				'albo_copia_registro',
				sprintf(
					/* translators: %s: motivo riportato dal registro. */
					__( 'Impostazione non salvata: il registro non ha scritto la voce, e l\'impostazione e\' tornata com\'era. %s', 'albo-pretorio-pa' ),
					$voce->get_error_message()
				)
			);
		}

		return true;
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
	 * Rimette l'opzione com'era prima del salvataggio.
	 *
	 * @param mixed $precedente Valore precedente, nullo se non c'era.
	 */
	private static function ripristina( $precedente ): void {
		if ( null === $precedente ) {
			delete_option( self::OPZIONE );
			return;
		}

		update_option( self::OPZIONE, $precedente, true );
	}

	/**
	 * Riporta la classe allo stato del caricamento.
	 *
	 * @internal Serve alle prove.
	 */
	public static function azzera(): void {
		self::$pronto       = false;
		self::$oggetto_tipo = null;
		self::$sezione      = false;
		self::$avvisi       = array();
	}
}
