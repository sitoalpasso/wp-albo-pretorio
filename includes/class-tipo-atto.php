<?php
/**
 * Registrazione del tipo di contenuto atto e dei suoi due elenchi di voci.
 *
 * Il tipo non si registra direttamente con WordPress: passa dal meccanismo
 * comune, che lo accetta solo se dichiara una sezione gia' registrata e che
 * ricava da se' i nomi dei permessi dall'identificativo del tipo. Un tipo
 * registrato fuori da una sezione sarebbe contenuto pubblicabile che nessuna
 * regola governa.
 *
 * Gli elenchi di voci si registrano invece direttamente, perche' per loro il
 * meccanismo comune non offre niente. La differenza e' dichiarata nel catalogo
 * di collaudo, riga A-16, invece di essere una sorpresa che si scopre leggendo
 * il codice.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Il tipo atto, i suoi elenchi di voci, e la loro chiusura verso il pubblico.
 */
final class TipoAtto {

	/**
	 * La registrazione e' gia' avvenuta in questa richiesta.
	 *
	 * @var bool
	 */
	private static $registrato = false;

	/**
	 * L'oggetto del tipo come lo abbiamo ricevuto alla registrazione.
	 *
	 * **Serve a dimostrare la proprieta', che un valore booleano non dimostra.**
	 * Quel valore dice che abbiamo registrato qualcosa in passato; non dice che
	 * l'oggetto oggi presente nel registro sia ancora il nostro. Un altro
	 * componente puo' registrare lo stesso identificativo e WordPress mette il
	 * suo oggetto al posto del nostro, senza dire niente a nessuno. Il confronto
	 * per identita' con l'oggetto che avevamo ricevuto e' la prova che regge.
	 *
	 * @var \WP_Post_Type|null
	 */
	private static $oggetto_tipo = null;

	/**
	 * Gli oggetti dei due elenchi di voci, per identificativo.
	 *
	 * Stessa ragione dell'oggetto del tipo.
	 *
	 * @var array<string, \WP_Taxonomy>
	 */
	private static $oggetti_elenchi = array();

	/**
	 * Messaggi da mostrare a chi puo' rimediare.
	 *
	 * @var array<int, string>
	 */
	private static $avvisi = array();

	/**
	 * Aggancio a `init`, dove i tipi di contenuto si registrano.
	 */
	public static function da_init(): void {
		self::registra();
	}

	/**
	 * Registra il tipo e i due elenchi di voci.
	 *
	 * @return true|\WP_Error Vero se il tipo risulta registrato, errore altrimenti.
	 */
	public static function registra() {
		if ( self::$registrato ) {
			return true;
		}

		/*
		 * **La precondizione e' la paternita' della sezione, non la presenza del
		 * meccanismo comune.** Che le sue funzioni esistano dice che quel
		 * componente e' caricato; non dice che la sezione dell'albo sia nostra.
		 * Un altro componente puo' averla registrata prima, anche con le stesse
		 * politiche, e in quel caso il meccanismo comune vedrebbe una sezione
		 * valida e accetterebbe il tipo: staremmo costruendo dentro la sezione di
		 * qualcun altro mentre l'avvio si e' gia' dichiarato inerte.
		 */
		if ( ! Avvio::registrata() ) {
			return new \WP_Error(
				'albo_sezione_non_nostra',
				sprintf(
					/* translators: %s: nome del componente. */
					__( '%s: il tipo atto non e\' stato registrato perche\' la sezione dell\'albo non risulta registrata da questo componente.', 'albo-pretorio-pa' ),
					NOME
				)
			);
		}

		$occupato = self::elenco_di_voci_occupato();

		if ( '' !== $occupato ) {
			self::avvisa(
				sprintf(
					/* translators: 1: nome del componente, 2: identificativo dell'elenco di voci in conflitto. */
					__( '%1$s resta attivo e inerte: l\'elenco di voci %2$s e\' gia\' registrato da un altro componente, e sostituirlo cancellerebbe il suo. Rinominare l\'altro elenco oppure disattivare il componente che lo registra.', 'albo-pretorio-pa' ),
					NOME,
					$occupato
				)
			);

			return new \WP_Error(
				'albo_elenco_di_voci_in_conflitto',
				sprintf(
					/* translators: %s: identificativo dell'elenco di voci in conflitto. */
					__( 'Elenco di voci %s gia\' registrato da un altro componente.', 'albo-pretorio-pa' ),
					$occupato
				),
				array( 'elenco' => $occupato )
			);
		}

		$esito = conformita_core_registra_tipo(
			TIPO,
			array(

				/*
				 * L'esposizione per programmi e' dichiarata spenta, e la scelta e'
				 * obbligatoria in entrambi i sensi. Spenta perche' finche' la
				 * pubblicazione e' chiusa un contenuto arrivato per sbaglio allo
				 * stato pubblicato sarebbe leggibile senza autenticazione: il
				 * controllore REST di WordPress considera leggibile da chiunque
				 * ogni contenuto pubblicato, senza guardare se il tipo e' pubblico.
				 * Si accende quando la pubblicazione si apre.
				 */
				'show_in_rest' => false,
				'sezione'      => SEZIONE,
				'argomenti'    => self::argomenti(),
			)
		);

		if ( is_wp_error( $esito ) ) {
			self::avvisa(
				sprintf(
					/* translators: 1: nome del componente, 2: messaggio di errore del componente comune. */
					__( '%1$s resta attivo e inerte: il tipo atto non e\' stato registrato. %2$s', 'albo-pretorio-pa' ),
					NOME,
					$esito->get_error_message()
				)
			);

			return $esito;
		}

		$elenchi = self::registra_elenchi_di_voci();

		if ( is_wp_error( $elenchi ) ) {
			self::avvisa(
				sprintf(
					/* translators: 1: nome del componente, 2: messaggio di errore. */
					__( '%1$s resta attivo e inerte: %2$s', 'albo-pretorio-pa' ),
					NOME,
					$elenchi->get_error_message()
				)
			);

			return $elenchi;
		}

		self::$oggetto_tipo = get_post_type_object( TIPO );
		self::$registrato   = true;

		return true;
	}

	/**
	 * Il primo dei due elenchi di voci gia' registrato da qualcun altro.
	 *
	 * **Si guarda prima di registrare il tipo, e non dopo.** WordPress mette
	 * l'oggetto nuovo nel registro globale e sostituisce quello che trova, senza
	 * dire niente: accorgersene dopo significherebbe avere gia' cancellato
	 * l'elenco di un altro componente. E indietro non si torna in modo pulito,
	 * perche' il meccanismo comune non espone una funzione per smontare quel
	 * solo tipo senza lasciare uno stato a meta'.
	 *
	 * @return string Identificativo in conflitto, stringa vuota se non ce ne sono.
	 */
	private static function elenco_di_voci_occupato(): string {
		foreach ( array( TASSONOMIA_TIPO_ATTO, TASSONOMIA_ORGANO ) as $nome ) {
			if ( taxonomy_exists( $nome ) ) {
				return $nome;
			}
		}

		return '';
	}

	/**
	 * Il tipo risulta registrato da questo componente in questa richiesta.
	 *
	 * @return bool
	 */
	public static function registrato(): bool {
		if ( ! self::$registrato || null === self::$oggetto_tipo ) {
			return false;
		}

		if ( get_post_type_object( TIPO ) !== self::$oggetto_tipo ) {
			return false;
		}

		foreach ( self::$oggetti_elenchi as $nome => $oggetto ) {
			if ( get_taxonomy( (string) $nome ) !== $oggetto ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Argomenti di registrazione del tipo.
	 *
	 * **Sono valori scritti uno per uno e non lasciati derivare.** WordPress
	 * ricava `publicly_queryable`, `query_var` e `exclude_from_search` da
	 * `public` quando non li si dichiara: una derivazione che cambiasse in una
	 * versione futura aprirebbe il tipo senza che nessuno abbia deciso niente.
	 *
	 * Niente supporto all'editor, perche' un atto non ha un corpo di testo: ha
	 * un documento. La conseguenza utile e' che WordPress usa da se' la
	 * schermata classica, dove i campi dell'atto potranno essere compilati e
	 * letti nella stessa richiesta che salva.
	 *
	 * @return array<string, mixed>
	 */
	private static function argomenti(): array {
		return array(
			'labels'              => self::etichette_tipo(),
			'description'         => __( 'Atti pubblicati con effetto di pubblicita\' legale.', 'albo-pretorio-pa' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-media-document',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author', 'revisions' ),
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}

	/**
	 * Registra i due elenchi di voci del tipo atto, e ne verifica l'esito.
	 *
	 * Piatti, perche' il dominio non ha rapporti fra voce e sottovoce: ne' i
	 * tipi di atto ne' gli organi si contengono a vicenda.
	 *
	 * Nessun riquadro di scelta in questa fase (`meta_box_cb` a falso): il
	 * riquadro predefinito di un elenco piatto e' un campo a testo libero, e su
	 * un vocabolario controllato lo snaturerebbe. L'interfaccia di scelta arriva
	 * con la schermata di compilazione.
	 *
	 * @return true|\WP_Error Vero se entrambi risultano registrati e agganciati al tipo.
	 */
	private static function registra_elenchi_di_voci() {
		$comuni = array(
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_admin_column'  => true,
			'show_in_rest'       => false,
			'rewrite'            => false,
			'query_var'          => false,
			'meta_box_cb'        => false,
			'capabilities'       => self::permessi_elenchi(),
		);

		$elenchi = array(
			TASSONOMIA_TIPO_ATTO => self::etichette_tipo_atto(),
			TASSONOMIA_ORGANO    => self::etichette_organo(),
		);

		foreach ( $elenchi as $nome => $etichette ) {
			$esito = register_taxonomy( $nome, array( TIPO ), array_merge( $comuni, array( 'labels' => $etichette ) ) );

			if ( is_wp_error( $esito ) ) {
				return new \WP_Error(
					'albo_elenco_di_voci_non_registrato',
					sprintf(
						/* translators: 1: identificativo dell'elenco di voci, 2: messaggio di errore di WordPress. */
						__( 'Elenco di voci %1$s non registrato: %2$s', 'albo-pretorio-pa' ),
						$nome,
						$esito->get_error_message()
					)
				);
			}
		}

		/*
		 * La postcondizione si rilegge dal registro, e non e' pignoleria. Una
		 * registrazione puo' non reggere senza che la chiamata segnali niente,
		 * per esempio se qualcuno smonta l'elenco subito dopo averlo visto
		 * nascere: senza questo controllo il componente proseguirebbe convinto
		 * di avere due elenchi che non ci sono.
		 */
		self::$oggetti_elenchi = array();

		foreach ( array_keys( $elenchi ) as $nome ) {
			$oggetto = get_taxonomy( (string) $nome );

			if ( false !== $oggetto ) {
				self::$oggetti_elenchi[ (string) $nome ] = $oggetto;
			}

			if ( false === $oggetto || ! in_array( TIPO, (array) $oggetto->object_type, true ) ) {
				return new \WP_Error(
					'albo_elenco_di_voci_non_registrato',
					sprintf(
						/* translators: %s: identificativo dell'elenco di voci. */
						__( 'Elenco di voci %s non risulta registrato e agganciato al tipo atto dopo la registrazione.', 'albo-pretorio-pa' ),
						$nome
					)
				);
			}
		}

		return true;
	}

	/**
	 * Permessi degli elenchi di voci, ricavati da quelli del tipo.
	 *
	 * Nessun nome nuovo: chi puo' pubblicare governa il vocabolario, chi puo'
	 * redigere lo usa. Agganciarli ai permessi delle categorie li renderebbe
	 * governabili da chi non ha niente a che fare con l'albo.
	 *
	 * @return array<string, string>
	 */
	private static function permessi_elenchi(): array {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) ) {
			return array();
		}

		return array(
			'manage_terms' => $mappa['publish_posts'],
			'edit_terms'   => $mappa['publish_posts'],
			'delete_terms' => $mappa['publish_posts'],
			'assign_terms' => $mappa['edit_posts'],
		);
	}

	/**
	 * Etichette del tipo atto.
	 *
	 * @return array<string, string>
	 */
	private static function etichette_tipo(): array {
		return array(
			'name'          => __( 'Atti', 'albo-pretorio-pa' ),
			'singular_name' => __( 'Atto', 'albo-pretorio-pa' ),
			'menu_name'     => __( 'Albo pretorio', 'albo-pretorio-pa' ),
			'add_new_item'  => __( 'Nuovo atto', 'albo-pretorio-pa' ),
			'edit_item'     => __( 'Modifica atto', 'albo-pretorio-pa' ),
			'search_items'  => __( 'Cerca atti', 'albo-pretorio-pa' ),
			'not_found'     => __( 'Nessun atto.', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * Etichette dell'elenco dei tipi di atto.
	 *
	 * @return array<string, string>
	 */
	private static function etichette_tipo_atto(): array {
		return array(
			'name'          => __( 'Tipi di atto', 'albo-pretorio-pa' ),
			'singular_name' => __( 'Tipo di atto', 'albo-pretorio-pa' ),
			'search_items'  => __( 'Cerca tipi di atto', 'albo-pretorio-pa' ),
			'edit_item'     => __( 'Modifica tipo di atto', 'albo-pretorio-pa' ),
			'add_new_item'  => __( 'Nuovo tipo di atto', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * Etichette dell'elenco degli organi.
	 *
	 * @return array<string, string>
	 */
	private static function etichette_organo(): array {
		return array(
			'name'          => __( 'Organi', 'albo-pretorio-pa' ),
			'singular_name' => __( 'Organo', 'albo-pretorio-pa' ),
			'search_items'  => __( 'Cerca organi', 'albo-pretorio-pa' ),
			'edit_item'     => __( 'Modifica organo', 'albo-pretorio-pa' ),
			'add_new_item'  => __( 'Nuovo organo', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * Registra un messaggio per chi puo' rimediare.
	 *
	 * @param string $messaggio Testo dell'avviso.
	 */
	private static function avvisa( string $messaggio ): void {
		self::$avvisi[] = $messaggio;

		if ( ! has_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) ) ) {
			add_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) );
		}
	}

	/**
	 * Mostra gli avvisi a chi puo' attivare i componenti.
	 */
	public static function mostra_avvisi(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( self::$avvisi as $messaggio ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $messaggio ) );
		}
	}

	/**
	 * Smonta gli elenchi di voci e riporta lo stato interno all'inizio.
	 *
	 * Serve alla suite di prove, dove tutto gira nello stesso processo: un
	 * elenco di voci lasciato dietro renderebbe verde o rossa la prova
	 * successiva per un motivo che non c'entra. Il tipo lo smonta il meccanismo
	 * comune, che e' quello che lo ha montato.
	 */
	public static function azzera(): void {
		foreach ( array( TASSONOMIA_TIPO_ATTO, TASSONOMIA_ORGANO ) as $tassonomia ) {
			if ( taxonomy_exists( $tassonomia ) ) {
				unregister_taxonomy( $tassonomia );
			}
		}

		self::$registrato      = false;
		self::$oggetto_tipo    = null;
		self::$oggetti_elenchi = array();
		self::$avvisi          = array();
	}
}
