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

		if ( ! function_exists( 'conformita_core_registra_tipo' ) ) {
			return new \WP_Error(
				'albo_meccanismo_comune_assente',
				sprintf(
					/* translators: 1: nome del componente, 2: nome del componente comune. */
					__( '%1$s: il tipo atto non e\' stato registrato perche\' %2$s non e\' caricato.', 'albo-pretorio-pa' ),
					NOME,
					CORE_NOME
				)
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

		self::registra_elenchi_di_voci();

		self::$registrato = true;

		return true;
	}

	/**
	 * Il tipo risulta registrato da questo componente in questa richiesta.
	 *
	 * @return bool
	 */
	public static function registrato(): bool {
		return self::$registrato;
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
	 * Registra i due elenchi di voci del tipo atto.
	 *
	 * Piatti, perche' il dominio non ha rapporti fra voce e sottovoce: ne' i
	 * tipi di atto ne' gli organi si contengono a vicenda.
	 *
	 * Nessun riquadro di scelta in questa fase (`meta_box_cb` a falso): il
	 * riquadro predefinito di un elenco piatto e' un campo a testo libero, e su
	 * un vocabolario controllato lo snaturerebbe. L'interfaccia di scelta arriva
	 * con la schermata di compilazione.
	 */
	private static function registra_elenchi_di_voci(): void {
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

		register_taxonomy(
			TASSONOMIA_TIPO_ATTO,
			array( TIPO ),
			array_merge( $comuni, array( 'labels' => self::etichette_tipo_atto() ) )
		);

		register_taxonomy(
			TASSONOMIA_ORGANO,
			array( TIPO ),
			array_merge( $comuni, array( 'labels' => self::etichette_organo() ) )
		);
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

		self::$registrato = false;
		self::$avvisi     = array();
	}
}
