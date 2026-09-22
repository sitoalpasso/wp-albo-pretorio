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
	 * La registrazione e' stata portata a termine per intero in questa richiesta.
	 *
	 * @var bool
	 */
	private static $completa = false;

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
	 * **Si conserva appena il nucleo comune registra il tipo, prima degli
	 * elenchi di voci.** L'ordine non e' un dettaglio: se gli elenchi non
	 * reggono, il tipo e' comunque nostro e lo sbarramento deve continuare a
	 * proteggerlo.
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
	 * Messaggi da mostrare a chi puo' rimediare, per chiave.
	 *
	 * **La chiave serve a toglierli.** Un avviso nasce da una condizione, e
	 * quella condizione puo' finire mentre la richiesta e' ancora in corso: un
	 * tentativo di registrazione fallisce e il successivo riesce. Senza una
	 * chiave l'avviso del primo resta in lista e in bacheca compare un errore
	 * che descrive uno stato non piu' vero, che e' peggio di nessun avviso,
	 * perche' chi lo legge non ha modo di saperlo e cerca di rimediare a un
	 * guasto che non c'e' piu'.
	 *
	 * @var array<string, string>
	 */
	private static $avvisi = array();

	/**
	 * Le chiavi degli avvisi che parlano di un tentativo di registrazione.
	 *
	 * Sono quelle, e soltanto quelle, che smettono di essere vere quando la
	 * registrazione arriva alla postcondizione completa. Gli avvisi di altre
	 * superfici non si toccano: descrivono condizioni che questa funzione non
	 * ha cambiato.
	 *
	 * @var array<int, string>
	 */
	private const AVVISI_DEL_TENTATIVO = array(
		'registrazione_incompleta',
		'elenco_in_conflitto',
		'tipo_non_registrato',
	);

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
		if ( self::registrazione_completa() ) {
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

		/*
		 * **La verifica delle collisioni precede ogni tentativo, e sta qui e non
		 * dentro la registrazione del tipo.** Dentro quella, un ritentativo la
		 * salterebbe: il tipo e' gia' nostro, quel passaggio non viene eseguito,
		 * e la registrazione degli elenchi passerebbe sopra un identificativo
		 * che nel frattempo e' diventato di un altro componente. La garanzia
		 * varrebbe alla prima richiesta e non alla seconda.
		 */
		$liberi = self::verifica_elenchi_di_voci_liberi();

		if ( is_wp_error( $liberi ) ) {
			return $liberi;
		}

		/*
		 * Il tipo si registra solo se non e' gia' nostro. Puo' esserlo dopo un
		 * tentativo in cui gli elenchi di voci non hanno retto: in quel caso
		 * resta da rifare soltanto la parte che e' caduta, e richiedere di nuovo
		 * il tipo al nucleo comune darebbe un errore di duplicato su una cosa che
		 * abbiamo gia'.
		 */
		if ( ! self::tipo_nostro() ) {
			$esito = self::registra_tipo();

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}
		}

		$elenchi = self::registra_elenchi_di_voci();

		if ( is_wp_error( $elenchi ) ) {
			self::avvisa(
				'registrazione_incompleta',
				sprintf(
					/* translators: 1: nome del componente, 2: messaggio di errore, 3: identificativo del tipo di contenuto. */
					__( '%1$s non ha completato la registrazione: %2$s Il tipo %3$s resta registrato e chiuso al pubblico, e il componente non ha modificato ne\' i permessi ne\' la versione installata. Riprovera\' alla richiesta successiva.', 'albo-pretorio-pa' ),
					NOME,
					$elenchi->get_error_message(),
					TIPO
				)
			);

			return $elenchi;
		}

		self::$completa = true;

		self::dimentica_avvisi_del_tentativo();

		return true;
	}

	/**
	 * Il tipo oggi presente nel registro e' quello che abbiamo registrato noi.
	 *
	 * **E' la domanda da cui dipende lo sbarramento della pubblicazione, e non
	 * va confusa con la completezza della registrazione.** Se un altro
	 * componente registra lo stesso identificativo, WordPress mette il suo
	 * oggetto al posto del nostro e da quel momento quei contenuti non sono
	 * nostri: governarli sarebbe governare roba di altri. Se invece si perde un
	 * elenco di voci, il tipo e' ancora il nostro e va protetto: confondere le
	 * due cose fa smettere lo sbarramento nel momento sbagliato, cioe' apre
	 * invece di chiudere.
	 *
	 * @return bool
	 */
	public static function tipo_nostro(): bool {
		return null !== self::$oggetto_tipo && get_post_type_object( TIPO ) === self::$oggetto_tipo;
	}

	/**
	 * Tipo e due elenchi di voci sono tutti presenti e tutti nostri.
	 *
	 * E' la domanda dell'installazione e di chiunque abbia bisogno
	 * dell'insieme completo. Piu' esigente della proprieta' del tipo, e usarla
	 * al suo posto nello sbarramento sarebbe l'errore da evitare.
	 *
	 * @return bool
	 */
	public static function registrazione_completa(): bool {
		if ( ! self::$completa || ! self::tipo_nostro() ) {
			return false;
		}

		if ( count( self::$oggetti_elenchi ) < 2 ) {
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
	 * Nessuno dei due elenchi di voci e' di qualcun altro.
	 *
	 * @return true|\WP_Error
	 */
	private static function verifica_elenchi_di_voci_liberi() {
		$occupato = self::elenco_di_voci_occupato();

		if ( '' === $occupato ) {
			return true;
		}

		/*
		 * Il messaggio cambia con lo stato, perche' dire "resta attivo e
		 * inerte" a chi ha gia' il tipo registrato sarebbe falso: quel tipo
		 * c'e', e l'avviso deve dire cosa resta in piedi.
		 */
		if ( self::tipo_nostro() ) {
			self::avvisa(
				'elenco_in_conflitto',
				sprintf(
					/* translators: 1: nome del componente, 2: identificativo dell'elenco di voci in conflitto, 3: identificativo del tipo di contenuto. */
					__( '%1$s non ha completato la registrazione: l\'elenco di voci %2$s e\' ora registrato da un altro componente, e sostituirlo cancellerebbe il suo. Il tipo %3$s resta registrato e chiuso al pubblico, e il componente non ha modificato ne\' i permessi ne\' la versione installata. Rinominare l\'altro elenco oppure disattivare il componente che lo registra.', 'albo-pretorio-pa' ),
					NOME,
					$occupato,
					TIPO
				)
			);
		} else {
			self::avvisa(
				'elenco_in_conflitto',
				sprintf(
					/* translators: 1: nome del componente, 2: identificativo dell'elenco di voci in conflitto. */
					__( '%1$s resta attivo e inerte: l\'elenco di voci %2$s e\' gia\' registrato da un altro componente, e sostituirlo cancellerebbe il suo. Rinominare l\'altro elenco oppure disattivare il componente che lo registra.', 'albo-pretorio-pa' ),
					NOME,
					$occupato
				)
			);
		}

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

	/**
	 * Registra il tipo presso il nucleo comune e ne conserva l'oggetto.
	 *
	 * Le collisioni le ha gia' guardate chi chiama, prima di questo passaggio
	 * e prima di quello degli elenchi di voci: guardarle qui dentro lascerebbe
	 * scoperto il ritentativo, che questo passaggio non lo esegue affatto.
	 *
	 * @return true|\WP_Error
	 */
	private static function registra_tipo() {
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
				'tipo_non_registrato',
				sprintf(
					/* translators: 1: nome del componente, 2: messaggio di errore del componente comune. */
					__( '%1$s resta attivo e inerte: il tipo atto non e\' stato registrato. %2$s', 'albo-pretorio-pa' ),
					NOME,
					$esito->get_error_message()
				)
			);

			return $esito;
		}

		self::$oggetto_tipo = get_post_type_object( TIPO );

		return true;
	}

	/**
	 * Il primo dei due elenchi di voci gia' registrato da qualcun altro.
	 *
	 * **Si guarda prima di ogni tentativo, e non dopo.** WordPress mette
	 * l'oggetto nuovo nel registro globale e sostituisce quello che trova, senza
	 * dire niente: accorgersene dopo significherebbe avere gia' cancellato
	 * l'elenco di un altro componente. E indietro non si torna in modo pulito,
	 * perche' il meccanismo comune non espone una funzione per smontare quel
	 * solo tipo senza lasciare uno stato a meta'.
	 *
	 * Ogni tentativo, non solo il primo: un identificativo libero quando il
	 * tipo e' nato puo' essere di qualcun altro quando si rifa' la parte che
	 * era caduta.
	 *
	 * @return string Identificativo in conflitto, stringa vuota se non ce ne sono.
	 */
	private static function elenco_di_voci_occupato(): string {
		foreach ( array( TASSONOMIA_TIPO_ATTO, TASSONOMIA_ORGANO ) as $nome ) {
			if ( ! taxonomy_exists( $nome ) ) {
				continue;
			}

			// Un elenco che e' gia' nostro non e' una collisione: e' roba nostra.
			if ( isset( self::$oggetti_elenchi[ $nome ] ) && get_taxonomy( $nome ) === self::$oggetti_elenchi[ $nome ] ) {
				continue;
			}

			return $nome;
		}

		return '';
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
		$permessi = self::permessi_elenchi();

		if ( is_wp_error( $permessi ) ) {
			return $permessi;
		}

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
			'capabilities'       => $permessi,
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
	 * **Senza la corrispondenza si restituisce un errore, non una
	 * corrispondenza vuota.** Il ripiego sembrava prudente e faceva l'opposto:
	 * `register_taxonomy` con i permessi vuoti non lascia l'elenco senza
	 * permessi, ricade sui predefiniti di WordPress, cioe' proprio i permessi
	 * delle categorie e degli articoli che questa funzione dichiara di voler
	 * evitare. Fra i due modi di sbagliare quello era il modo che **apre**, e
	 * un ramo che in caso di guasto consegna il vocabolario dell'albo a chi
	 * gestisce le categorie non deve esistere, nemmeno irraggiungibile.
	 *
	 * @return array<string, string>|\WP_Error
	 */
	private static function permessi_elenchi() {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) ) {
			return new \WP_Error(
				'albo_permessi_degli_elenchi_non_disponibili',
				sprintf(
					/* translators: %s: messaggio di errore sulla corrispondenza dei permessi. */
					__( 'Elenchi di voci non registrati: i nomi dei permessi derivati dal tipo non sono disponibili, e registrarli senza farebbe ricadere WordPress sui permessi delle categorie. %s', 'albo-pretorio-pa' ),
					$mappa->get_error_message()
				)
			);
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
	 * @param string $chiave    Chiave con cui l'avviso si riconosce e si toglie.
	 * @param string $messaggio Testo dell'avviso.
	 */
	private static function avvisa( string $chiave, string $messaggio ): void {
		self::$avvisi[ $chiave ] = $messaggio;

		if ( ! has_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) ) ) {
			add_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) );
		}
	}

	/**
	 * Toglie gli avvisi dei tentativi che la registrazione completa smentisce.
	 *
	 * **Si tolgono per chiave, non tutti.** Gli avvisi delle altre superfici
	 * parlano di condizioni che questa funzione non ha cambiato, e cancellarli
	 * qui vorrebbe dire nascondere un guasto vero per averne risolto un altro.
	 */
	private static function dimentica_avvisi_del_tentativo(): void {
		foreach ( self::AVVISI_DEL_TENTATIVO as $chiave ) {
			unset( self::$avvisi[ $chiave ] );
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

		self::$completa        = false;
		self::$oggetto_tipo    = null;
		self::$oggetti_elenchi = array();
		self::$avvisi          = array();
	}
}
