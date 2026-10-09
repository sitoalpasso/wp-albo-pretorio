<?php
/**
 * Il guardiano dei passaggi di stato degli atti, e lo sbarramento che tiene
 * chiusa la pubblicazione.
 *
 * **Non e' una condizione, e' un elenco ordinato di regole.** La prima nega la
 * pubblicazione e la programmazione a ogni richiesta che arrivi da WordPress.
 * Le altre applicano l'elenco chiuso dei passaggi deciso con ALBO-22: un atto
 * nasce in bozza, la bozza si risalva, passa in verifica solo con tutti i dati
 * che la pubblicazione pretende, e in verifica non si modifica piu'. I due
 * passaggi che partono dalla verifica, pubblicazione e rimando in bozza, li
 * compie soltanto `Passaggi`, e questa classe li lascia passare solo quando la
 * richiesta arriva da li'. Da pubblicato non si torna indietro.
 *
 * **Due modi di rifiutare, secondo lo stato di partenza.** Da una bozza, il
 * rifiuto lascia avvenire la scrittura con lo stato di bozza: la bozza e'
 * modificabile, e quello che chi redige ha scritto si conserva. Da un atto in
 * verifica o pubblicato, la scrittura si ferma per intero prima di cominciare,
 * perche' non solo lo stato ma nemmeno l'oggetto, le voci o i dati collegati
 * devono cambiare.
 *
 * **Due livelli.** Gli ingressi ordinari passano da `wp_insert_post`, e li
 * ferma il primo livello prima che la scrittura cominci. Alcune funzioni di
 * WordPress scrivono invece da se' nella banca dati senza passare di li':
 * `wp_publish_post()`, che usa anche la pubblicazione programmata del compito
 * pianificato, e `set_post_type()`. Il secondo livello guarda l'istruzione che
 * sta per arrivare alla banca dati: una scrittura della riga di un atto che il
 * primo livello non ha vagliato, e che tocca un atto in verifica, pubblicato
 * o in un passaggio in corso, oppure porta un atto fuori dalla bozza, ferma
 * la richiesta prima di partire. I dati collegati hanno le loro difese: i
 * metadati si fermano con i filtri che WordPress chiama prima di scriverli, e
 * le voci degli elenchi con gli annunci che WordPress fa prima di assegnarle
 * o toglierle, anche qui fermando la richiesta. Resta fuori soltanto
 * un'istruzione scritta a mano da un altro componente in una forma diversa da
 * quella delle funzioni di WordPress.
 *
 * **Il vaglio vale per lo stato da cui parte.** Fra il primo livello e
 * l'istruzione WordPress fa girare altri agganci, e un'altra richiesta puo'
 * intanto mandare in verifica o pubblicare lo stesso atto. Il secondo livello
 * ammette la scrittura vagliata solo se la riga, riletta dalla banca dati, ha
 * ancora lo stato su cui il vaglio e' stato fatto, e l'istruzione stessa
 * scrive solo a quella condizione: se un'altra richiesta cambia la riga anche
 * nell'ultimo istante, la scrittura non avviene e la richiesta si ferma prima
 * degli annunci di WordPress.
 *
 * **La concessione e' di `Passaggi`, non di questa classe.** Il guardiano non
 * ha nessun modo pubblico per concedere un passaggio: chiede a `Passaggi` se
 * la scrittura porta il gettone del passaggio in corso su quell'atto, e il
 * gettone si consuma alla prima domanda. Una seconda scrittura, anche dello
 * stesso atto e anche annidata dentro la prima, trova il gettone gia' usato e
 * si ferma.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Rifiuto della pubblicazione sugli ingressi supportati.
 */
final class ChiusuraPubblicazione {

	/**
	 * Le decisioni prese e non ancora depositate, dalla piu' recente.
	 *
	 * **Una pila e non un appoggio solo.** La decisione si prende prima che la
	 * riga esista e il motivo si deposita dopo, quando l'atto ha un
	 * identificativo: in mezzo un altro componente puo' inserire un secondo
	 * contenuto, e con un appoggio solo il secondo cancellerebbe il primo prima
	 * che arrivi a destinazione. Gli inserimenti annidati si chiudono in ordine
	 * inverso a come si aprono, quindi una pila li rimette in fila da sola.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $pila = array();

	/**
	 * La scrittura concessa da `Passaggi` che sta attraversando WordPress, o nulla.
	 *
	 * Si apre quando il primo filtro consuma il gettone e si chiude quando il
	 * secondo la applica: in mezzo non c'e' nessuna scrittura della banca dati.
	 *
	 * @var array{id: int, gettone: string, campi: array<string, string>}|null
	 */
	private static $scrittura = null;

	/**
	 * Le scritture della riga vagliate dal primo livello, per atto, con lo stato che portano.
	 *
	 * Ciascuna ammette una sola istruzione al secondo livello, e si consuma.
	 * Porta anche lo stato da cui il vaglio e' partito: il vaglio vale per
	 * quello stato e per nessun altro, perche' fra il vaglio e l'istruzione
	 * un'altra richiesta puo' aver cambiato l'atto.
	 *
	 * @var array<int, array{stato: string, partenza: string|null, passaggio: bool}>
	 */
	private static $vagliate = array();

	/**
	 * Le scritture della riga ammesse dal secondo livello e non ancora verificate, per atto.
	 *
	 * L'istruzione ammessa scrive solo se la riga ha ancora lo stato letto dal
	 * secondo livello; se nel frattempo un'altra richiesta l'ha cambiato, non
	 * scrive niente, e la richiesta va fermata prima che WordPress annunci una
	 * scrittura che non c'e' stata.
	 *
	 * @var array<int, array{scritto: string, letto: string}>
	 */
	private static $attese = array();

	/**
	 * Gli stati di bozza: la bozza vera e quella che WordPress crea aprendo la schermata di un atto nuovo.
	 *
	 * @var array<int, string>
	 */
	const STATI_BOZZA = array( 'draft', 'auto-draft' );

	/**
	 * Lo stato che l'albo chiama "in verifica".
	 *
	 * @var string
	 */
	const IN_VERIFICA = 'pending';

	/**
	 * Lo stato dell'atto pubblicato.
	 *
	 * @var string
	 */
	const PUBBLICATO = 'publish';

	/**
	 * Gli stati in cui ogni scrittura si ferma prima di cominciare.
	 *
	 * @var array<int, string>
	 */
	const STATI_FERMI = array( 'pending', 'publish' );

	/**
	 * I campi che WordPress riscrive da se' a ogni scrittura, lasciati com'e' li propone.
	 *
	 * @var array<int, string>
	 */
	const CAMPI_DI_WORDPRESS = array( 'post_modified', 'post_modified_gmt' );

	/**
	 * I metadati di servizio che WordPress scrive da se' anche su un atto fermo.
	 *
	 * Il blocco della schermata aperta e l'ultimo autore, e i due segni che la
	 * pubblicazione lascia per gli avvisi ad altri siti. Nessuno dice niente
	 * dell'atto.
	 *
	 * @var array<int, string>
	 */
	const CHIAVI_DI_SERVIZIO = array( '_edit_lock', '_edit_last', '_pingme', '_encloseme' );

	/**
	 * Gli stati in cui un atto puo' trovarsi senza essere passato dai passaggi dell'albo.
	 *
	 * @var array<int, string>
	 */
	const STATI_LIBERI = array( 'draft', 'auto-draft', 'trash' );

	/**
	 * Gli stati che nessuna richiesta di WordPress ottiene.
	 *
	 * Pubblicato si ottiene solo da `Passaggi`, dalla verifica. Programmato
	 * mai: la data di inizio la scrive il sistema quando l'atto si pubblica
	 * (ALBO-27).
	 *
	 * @return array<int, string>
	 */
	public static function stati_non_concessi(): array {
		return array( 'publish', 'future' );
	}

	/**
	 * Consuma il gettone del passaggio in corso, se la scrittura lo porta ed e' pulita.
	 *
	 * Una scrittura concessa cambia lo stato, e per la pubblicazione le date:
	 * se porta con se' voci o dati, non e' quella chiesta da `Passaggi`.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $postarr Richiesta.
	 * @return array<string, string>|null I campi concessi.
	 */
	private static function consuma_concessione( int $atto_id, array $postarr ): ?array {
		$gettone = isset( $postarr[ Passaggi::CAMPO_GETTONE ] ) ? $postarr[ Passaggi::CAMPO_GETTONE ] : null;

		if ( ! is_string( $gettone ) || self::porta_dati( $postarr ) ) {
			return null;
		}

		$campi = Passaggi::consuma( $atto_id, $gettone );

		if ( null === $campi ) {
			return null;
		}

		self::$scrittura = array(
			'id'      => $atto_id,
			'gettone' => $gettone,
			'campi'   => $campi,
		);

		return $campi;
	}

	/**
	 * La richiesta porta voci, dati, categorie o etichette.
	 *
	 * @param array<string, mixed> $postarr Richiesta.
	 * @return bool
	 */
	private static function porta_dati( array $postarr ): bool {
		foreach ( array( 'meta_input', 'tax_input', 'tags_input', 'post_category' ) as $chiave ) {
			if ( ! empty( $postarr[ $chiave ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Aggancia lo sbarramento agli ingressi supportati.
	 */
	public static function aggancia(): void {
		add_filter( 'wp_insert_post_empty_content', array( self::class, 'da_wp_insert_post_empty_content' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'da_wp_insert_post_data' ), PHP_INT_MAX, 2 );
		add_action( 'wp_insert_post', array( self::class, 'da_wp_insert_post' ), 10, 2 );
		add_filter( 'pre_trash_post', array( self::class, 'da_pre_trash_post' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_post', array( self::class, 'da_pre_delete_post' ), PHP_INT_MAX, 2 );
		add_filter( 'query', array( self::class, 'da_query' ), PHP_INT_MAX );
		add_filter( 'add_post_metadata', array( self::class, 'da_add_post_metadata' ), PHP_INT_MAX, 4 );
		add_filter( 'update_post_metadata', array( self::class, 'da_update_post_metadata' ), PHP_INT_MAX, 4 );
		add_filter( 'delete_post_metadata', array( self::class, 'da_delete_post_metadata' ), PHP_INT_MAX, 5 );
		add_filter( 'update_post_metadata_by_mid', array( self::class, 'da_update_post_metadata_by_mid' ), PHP_INT_MAX, 4 );
		add_filter( 'delete_post_metadata_by_mid', array( self::class, 'da_delete_post_metadata_by_mid' ), PHP_INT_MAX, 2 );
		add_action( 'add_term_relationship', array( self::class, 'da_voce_dell_atto' ), PHP_INT_MIN, 1 );
		add_action( 'delete_term_relationships', array( self::class, 'da_voce_dell_atto' ), PHP_INT_MIN, 1 );
		add_action( 'transition_post_status', array( self::class, 'da_transition_post_status' ), PHP_INT_MIN, 3 );
		add_action( 'added_term_relationship', array( self::class, 'da_voce_aggiunta' ), PHP_INT_MIN, 2 );
		add_action( 'deleted_term_relationships', array( self::class, 'da_voci_tolte' ), PHP_INT_MIN, 2 );
		add_action( 'deleted_post_meta', array( self::class, 'da_metadati_tolti' ), PHP_INT_MIN, 1 );
	}

	/**
	 * Le ragioni per cui lo stato proposto non e' concesso.
	 *
	 * Riceve lo stato proposto e non legge niente da se': e' cio' che la rende
	 * esercitabile da sola, senza passare da un aggancio di WordPress. Le chiavi
	 * lette sono `post_status`, lo stato richiesto, e facoltative `partenza`, lo
	 * stato memorizzato o `nuovo`, `ammesso`, se chi scrive puo' modificare
	 * l'atto, `cambiamenti`, se la richiesta porta con se' voci o dati
	 * dell'albo, e `mancanti`, l'elenco dei dati mancanti dopo la scrittura.
	 *
	 * @param array<string, mixed> $stato_proposto Stato dell'atto come sara' dopo questa richiesta.
	 * @return array<string, string> Motivi per codice, vuoto se non ce ne sono.
	 */
	public static function motivi( array $stato_proposto ): array {
		$motivi = array();

		$richiesto = isset( $stato_proposto['post_status'] ) ? (string) $stato_proposto['post_status'] : '';
		$partenza  = isset( $stato_proposto['partenza'] ) ? (string) $stato_proposto['partenza'] : 'nuovo';

		if ( in_array( $richiesto, self::stati_non_concessi(), true ) ) {
			$motivi['albo_pubblicazione_solo_dalla_verifica'] = sprintf(
				/* translators: %s: nome del componente. */
				__( '%s: l\'atto resta in bozza. Un atto si pubblica solo dopo la verifica, da chi puo\' pubblicarlo, con il riquadro Pubblicazione e la conferma del controllo sui dati personali; la data di inizio la scrive il sistema in quel momento, e non si programma.', 'albo-pretorio-pa' ),
				NOME
			);

			return $motivi;
		}

		if ( self::IN_VERIFICA === $richiesto ) {
			if ( ! in_array( $partenza, self::STATI_BOZZA, true ) ) {
				$motivi['albo_verifica_solo_da_bozza'] = __( 'Un atto passa in verifica solo da una bozza gia\' salvata: nasce sempre in bozza.', 'albo-pretorio-pa' );

				return $motivi;
			}

			if ( empty( $stato_proposto['ammesso'] ) ) {
				$motivi['albo_verifica_non_permessa'] = __( 'L\'atto non passa in verifica: non si possiede il permesso di modificare questo atto.', 'albo-pretorio-pa' );

				return $motivi;
			}

			if ( ! empty( $stato_proposto['cambiamenti'] ) ) {
				$motivi['albo_verifica_con_cambiamenti'] = __( 'L\'atto resta in bozza e non passa in verifica: la stessa richiesta cambia anche il tipo, l\'organo o altri dati dell\'atto, e quei cambiamenti arriverebbero dopo il controllo. Si salvano prima nella bozza, poi si manda l\'atto in verifica.', 'albo-pretorio-pa' );

				return $motivi;
			}

			$mancanti = isset( $stato_proposto['mancanti'] ) && is_array( $stato_proposto['mancanti'] ) ? $stato_proposto['mancanti'] : array();

			foreach ( $mancanti as $codice => $messaggio ) {
				$motivi[ (string) $codice ] = sprintf(
					/* translators: %s: il dato che manca. */
					__( 'L\'atto resta in bozza e non passa in verifica. %s', 'albo-pretorio-pa' ),
					(string) $messaggio
				);
			}

			return $motivi;
		}

		$consentiti = array(
			'nuovo'      => array( 'draft', 'auto-draft' ),
			'auto-draft' => array( 'draft', 'auto-draft', 'trash' ),
			'draft'      => array( 'draft', 'trash' ),
			'trash'      => array( 'draft', 'trash' ),
		);

		if ( ! isset( $consentiti[ $partenza ] ) || ! in_array( $richiesto, $consentiti[ $partenza ], true ) ) {
			$motivi['albo_passaggio_non_consentito'] = __( 'Passaggio di stato non consentito: l\'atto resta in bozza. Da una bozza si passa soltanto in verifica.', 'albo-pretorio-pa' );
		}

		return $motivi;
	}

	/**
	 * Il motivo del fermo di ogni scrittura su un atto in verifica o pubblicato.
	 *
	 * Una richiesta di pubblicazione con una data di inizio nel futuro ha il
	 * suo motivo, che nomina la data: e' una richiesta di programmazione, e va
	 * rifiutata per quella ragione (ALBO-27).
	 *
	 * @param string               $stato   Stato memorizzato.
	 * @param array<string, mixed> $postarr Richiesta, se c'e'.
	 * @return array<string, string>
	 */
	private static function motivi_fermo( string $stato, array $postarr = array() ): array {
		if ( ! in_array( $stato, self::STATI_FERMI, true ) ) {
			return array(
				'albo_passaggio_in_corso' => __( 'L\'atto e\' in mezzo a un passaggio dell\'albo e non si modifica finche\' il passaggio non e\' finito.', 'albo-pretorio-pa' ),
			);
		}

		if ( self::PUBBLICATO === $stato ) {
			return array(
				'albo_atto_pubblicato' => __( 'L\'atto e\' pubblicato e non si modifica, non torna in bozza o in verifica e non si cancella, da nessuno: una correzione e\' un atto nuovo che rinvia a questo.', 'albo-pretorio-pa' ),
			);
		}

		$richiesto = isset( $postarr['post_status'] ) ? (string) $postarr['post_status'] : '';
		$data      = isset( $postarr['post_date'] ) ? (string) $postarr['post_date'] : '';

		if ( in_array( $richiesto, self::stati_non_concessi(), true ) && '' !== $data && $data > Passaggi::ora()->format( 'Y-m-d H:i:s' ) ) {
			$errore = Passaggi::errore_programmazione( $data );

			return array( (string) $errore->get_error_code() => $errore->get_error_message() );
		}

		return array(
			'albo_atto_in_verifica' => __( 'L\'atto e\' in verifica e non si modifica: quello che e\' stato verificato e\' quello che esce. Chi puo\' pubblicarlo lo pubblica, oppure lo rimanda in bozza con il motivo, dal riquadro Pubblicazione.', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * Se la richiesta porta con se' voci o dati dell'albo.
	 *
	 * **Dopo il controllo, non prima.** `wp_insert_post` assegna le voci di
	 * `tax_input` e scrive i dati di `meta_input` dopo aver scritto la riga,
	 * cioe' dopo che il passaggio e' stato giudicato: un passaggio che li porta
	 * con se' sarebbe giudicato sui dati di prima e lascerebbe in verifica
	 * quelli di dopo. La schermata dell'atto non li manda mai, perche' i due
	 * elenchi non hanno il riquadro di WordPress e i dati passano dai riquadri
	 * dell'albo, che salvano prima del controllo.
	 *
	 * @param array<string, mixed> $postarr Richiesta.
	 * @return bool
	 */
	private static function porta_cambiamenti( array $postarr ): bool {
		if ( isset( $postarr['tax_input'] ) && is_array( $postarr['tax_input'] ) ) {
			foreach ( array( TASSONOMIA_TIPO_ATTO, TASSONOMIA_ORGANO ) as $tassonomia ) {
				if ( array_key_exists( $tassonomia, $postarr['tax_input'] ) ) {
					return true;
				}
			}
		}

		if ( isset( $postarr['meta_input'] ) && is_array( $postarr['meta_input'] ) ) {
			foreach ( array_keys( $postarr['meta_input'] ) as $chiave ) {
				if ( 0 === strpos( ltrim( (string) $chiave, '_' ), 'albo_pretorio_' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Lo stato memorizzato di un atto nostro, o nullo se non e' un atto nostro.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null
	 */
	private static function stato_memorizzato( int $atto_id ): ?string {
		if ( $atto_id <= 0 || ! TipoAtto::tipo_nostro() ) {
			return null;
		}

		$atto = get_post( $atto_id );

		return $atto instanceof \WP_Post && TIPO === $atto->post_type ? (string) $atto->post_status : null;
	}

	/**
	 * Ferma per intero ogni scrittura su un atto in verifica o pubblicato, prima che cominci.
	 *
	 * **Prima che cominci, non dopo.** `wp_insert_post` guarda questo filtro
	 * prima di scrivere la riga, le voci e i dati collegati: fermarsi qui vuol
	 * dire che niente cambia. Riportare indietro dopo sarebbe una riparazione
	 * tardiva, e le voci assegnate con la stessa richiesta resterebbero.
	 * L'aggancio e' all'ultima priorita', cosi' che nessun altro possa
	 * rispondere dopo e far ripartire la scrittura.
	 *
	 * @param bool                 $vuoto   Risposta proposta: la scrittura va fermata.
	 * @param array<string, mixed> $postarr Dati della richiesta.
	 * @return bool
	 */
	public static function da_wp_insert_post_empty_content( $vuoto, $postarr ) {
		$postarr = is_array( $postarr ) ? $postarr : array();
		$atto_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$stato   = self::stato_memorizzato( $atto_id );

		if ( null === $stato || ( ! in_array( $stato, self::STATI_FERMI, true ) && ! Passaggi::in_corso( $atto_id ) ) ) {
			return $vuoto;
		}

		/*
		 * Durante un passaggio l'atto e' fermo qualunque sia il suo stato: dopo
		 * la scrittura del rimando e' gia' in bozza, ma gli agganci che
		 * WordPress chiama subito dopo non devono poterlo cambiare.
		 */
		if ( self::IN_VERIFICA === $stato && Passaggi::in_corso( $atto_id ) && null !== self::consuma_concessione( $atto_id, $postarr ) ) {
			return $vuoto;
		}

		Rifiuti::deposita( $atto_id, self::motivi_fermo( $stato, $postarr ) );

		return true;
	}

	/**
	 * Un atto in verifica o pubblicato non va nel cestino.
	 *
	 * @param mixed    $esito Risposta proposta, nullo per proseguire.
	 * @param \WP_Post $atto  Contenuto.
	 * @return mixed
	 */
	public static function da_pre_trash_post( $esito, $atto ) {
		return self::ferma_rimozione( $esito, $atto );
	}

	/**
	 * Un atto in verifica o pubblicato non si cancella.
	 *
	 * @param mixed    $esito Risposta proposta, nullo per proseguire.
	 * @param \WP_Post $atto  Contenuto.
	 * @return mixed
	 */
	public static function da_pre_delete_post( $esito, $atto ) {
		return self::ferma_rimozione( $esito, $atto );
	}

	/**
	 * Il fermo comune del cestino e della cancellazione.
	 *
	 * @param mixed    $esito Risposta proposta, nullo per proseguire.
	 * @param \WP_Post $atto  Contenuto.
	 * @return mixed
	 */
	private static function ferma_rimozione( $esito, $atto ) {
		$stato = $atto instanceof \WP_Post ? self::stato_memorizzato( (int) $atto->ID ) : null;

		if ( null === $stato || ! in_array( $stato, self::STATI_FERMI, true ) ) {
			return $esito;
		}

		Rifiuti::deposita( (int) $atto->ID, self::motivi_fermo( $stato ) );

		return false;
	}

	/**
	 * Riporta in bozza la scrittura in corso, prima che avvenga.
	 *
	 * WordPress applica questo filtro **prima** di scrivere nella tabella dei
	 * contenuti: e' il motivo per cui non esiste un istante in cui l'atto
	 * risulti pubblicato. Una correzione fatta dopo la scrittura sarebbe una
	 * riparazione tardiva, con quell'istante in mezzo e con gli effetti
	 * collaterali della pubblicazione gia' partiti. L'aggancio e' all'ultima
	 * priorita': a una priorita' qualunque, un altro componente agganciato dopo
	 * potrebbe rialzare lo stato che questo filtro ha riportato in bozza.
	 *
	 * @param array<string, mixed> $data    Dati che stanno per essere scritti.
	 * @param array<string, mixed> $postarr Dati come sono arrivati alla funzione di inserimento.
	 * @return array<string, mixed>
	 */
	public static function da_wp_insert_post_data( $data, $postarr ) {
		if ( ! is_array( $data ) || ! isset( $data['post_type'] ) || TIPO !== $data['post_type'] ) {
			return $data;
		}

		/*
		 * **La domanda e' se il tipo e' nostro, non se la registrazione e'
		 * completa.** Sono due cose diverse e vanno tenute separate proprio qui.
		 * Un altro componente puo' registrare un tipo con lo stesso
		 * identificativo: in quel caso i contenuti non sono nostri e riportarli
		 * in bozza sarebbe governare roba di altri. Ma se a mancare e' soltanto
		 * un elenco di voci, il tipo e' ancora il nostro e va protetto:
		 * chiedere qui la registrazione completa farebbe smettere lo sbarramento
		 * nel momento sbagliato, cioe' aprirebbe invece di chiudere.
		 */
		if ( ! TipoAtto::tipo_nostro() ) {
			return $data;
		}

		$atto_id   = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$partenza  = self::stato_memorizzato( $atto_id );
		$richiesto = isset( $data['post_status'] ) ? (string) $data['post_status'] : '';

		/*
		 * Un atto in verifica o pubblicato non arriva fin qui: la scrittura e'
		 * gia' stata fermata. Se ci arriva lo stesso, perche' qualcuno ha
		 * scavalcato quel fermo, la riga si riscrive com'era, con i suoi valori.
		 * L'unica scrittura che passa e' quella concessa a `Passaggi`, e anche
		 * quella cambia soltanto i campi concessi: stato e date li scrive questo
		 * filtro, all'ultima priorita', e nessun altro aggancio li sposta.
		 */
		if ( null !== $partenza && ( in_array( $partenza, self::STATI_FERMI, true ) || Passaggi::in_corso( $atto_id ) ) ) {
			$memorizzato = get_post( $atto_id, ARRAY_A );
			$concesso    = null;

			// La concessione vale solo per la scrittura che ha consumato il gettone.
			if ( null !== self::$scrittura && $atto_id === self::$scrittura['id'] && is_array( $postarr )
				&& isset( $postarr[ Passaggi::CAMPO_GETTONE ] ) && self::$scrittura['gettone'] === $postarr[ Passaggi::CAMPO_GETTONE ] ) {
				$concesso = self::$scrittura['campi'];
			}

			self::$scrittura = null;

			foreach ( array_keys( $data ) as $campo ) {
				if ( null !== $concesso && array_key_exists( $campo, $concesso ) ) {
					$data[ $campo ] = $concesso[ $campo ];
					continue;
				}

				if ( null !== $concesso && in_array( $campo, self::CAMPI_DI_WORDPRESS, true ) ) {
					continue;
				}

				/*
				 * Il filtro riceve i valori con le barre di protezione e
				 * WordPress le toglie subito dopo: quelli riletti dalla banca
				 * dati le ricevono qui, o una barra rovesciata nel testo
				 * verificato andrebbe persa.
				 */
				if ( is_array( $memorizzato ) && array_key_exists( $campo, $memorizzato ) ) {
					$data[ $campo ] = wp_slash( $memorizzato[ $campo ] );
				}
			}

			self::$vagliate[ $atto_id ] = array(
				'stato'     => (string) $data['post_status'],
				'partenza'  => $partenza,
				'passaggio' => null !== $concesso,
			);

			self::$pila[] = array(
				'id'     => $atto_id,
				'motivi' => null === $concesso ? self::motivi_fermo( $partenza, is_array( $postarr ) ? $postarr : array() ) : array(),
			);

			return $data;
		}

		$proposto = array(
			'post_status' => $richiesto,
			'partenza'    => null === $partenza ? 'nuovo' : $partenza,
		);

		if ( self::IN_VERIFICA === $richiesto && null !== $partenza && in_array( $partenza, self::STATI_BOZZA, true ) ) {
			/*
			 * **I riquadri salvano prima del controllo.** Chi compila tutto e
			 * chiede la verifica nello stesso invio deve vedere giudicati i dati
			 * che ha appena scritto, e i documenti caricati in quell'invio si
			 * depositano solo finche' l'atto e' in bozza, cioe' adesso.
			 */
			$atto = get_post( $atto_id );

			SchedaAtto::da_salvataggio( $atto_id, $atto );
			SchedaDocumenti::da_salvataggio( $atto_id, $atto );

			$mancanti = DatiAtto::mancanti( $atto_id );

			// L'oggetto si giudica per quello che sta per essere scritto, non per quello memorizzato.
			unset( $mancanti['albo_manca_oggetto'] );

			if ( '' === trim( isset( $data['post_title'] ) ? (string) $data['post_title'] : '' ) ) {
				$mancanti = array( 'albo_manca_oggetto' => __( 'Manca l\'oggetto dell\'atto.', 'albo-pretorio-pa' ) ) + $mancanti;
			}

			$proposto['ammesso']     = current_user_can( 'edit_post', $atto_id );
			$proposto['cambiamenti'] = self::porta_cambiamenti( is_array( $postarr ) ? $postarr : array() );
			$proposto['mancanti']    = $mancanti;
		}

		$motivi = self::motivi( $proposto );

		self::$pila[] = array(
			'id'     => isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0,
			'motivi' => $motivi,
		);

		if ( array() !== $motivi ) {
			$data['post_status'] = 'draft';
		}

		if ( $atto_id > 0 ) {
			self::$vagliate[ $atto_id ] = array(
				'stato'     => (string) $data['post_status'],
				'partenza'  => $partenza,
				'passaggio' => false,
			);
		}

		return $data;
	}

	/**
	 * Deposita il motivo, ora che l'atto ha un identificativo.
	 *
	 * @param int      $post_id Identificativo dell'atto.
	 * @param \WP_Post $post    Contenuto appena scritto.
	 */
	public static function da_wp_insert_post( $post_id, $post ): void {
		if ( array() === self::$pila ) {
			return;
		}

		if ( ! $post instanceof \WP_Post || TIPO !== $post->post_type || ! TipoAtto::tipo_nostro() ) {
			return;
		}

		$post_id = (int) $post_id;
		$voce    = null;

		/*
		 * Si prende la decisione in cima, e si scartano quelle che non
		 * corrispondono: sono inserimenti che non sono arrivati a destinazione,
		 * per esempio per un errore della banca dati, e tenerle farebbe
		 * attribuire a un atto il motivo di un altro.
		 */
		while ( array() !== self::$pila ) {
			$candidata = array_pop( self::$pila );

			if ( 0 === $candidata['id'] || $post_id === $candidata['id'] ) {
				$voce = $candidata;
				break;
			}
		}

		if ( null === $voce || array() === $voce['motivi'] ) {
			return;
		}

		Rifiuti::deposita( $post_id, $voce['motivi'] );
	}

	/**
	 * Il secondo livello: l'istruzione che sta per scrivere la riga di un atto.
	 *
	 * Riconosce la forma delle scritture di WordPress per identificativo,
	 * `UPDATE` della tabella dei contenuti con la sola condizione sull'`ID`,
	 * che e' quella di `wp_insert_post`, `wp_publish_post()` e
	 * `set_post_type()`. Gira all'ultima priorita', cosi' da giudicare
	 * l'istruzione che arriva davvero alla banca dati.
	 *
	 * @param mixed $istruzione Istruzione.
	 * @return mixed
	 */
	public static function da_query( $istruzione ) {
		global $wpdb;

		if ( ! is_string( $istruzione ) ) {
			return $istruzione;
		}

		$dipendente = self::condiziona_dipendente( $istruzione );

		if ( null !== $dipendente ) {
			return $dipendente;
		}

		$inizio = 'UPDATE `' . $wpdb->posts . '` SET ';

		if ( 0 !== strpos( $istruzione, $inizio ) || 1 !== preg_match( '/ WHERE `ID` = (\d+)$/', $istruzione, $trovato ) ) {
			return $istruzione;
		}

		$atto_id  = (int) $trovato[1];
		$insieme  = substr( $istruzione, strlen( $inizio ), - strlen( $trovato[0] ) );
		$vagliata = isset( self::$vagliate[ $atto_id ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura della riga com'e' nella banca dati, senza la memoria di WordPress, per giudicare la scrittura che sta per arrivare.
		$riga = $wpdb->get_row(
			$wpdb->prepare( "SELECT post_type, post_status, post_name FROM {$wpdb->posts} WHERE ID = %d", $atto_id ),
			ARRAY_A
		);

		if ( ! is_array( $riga ) ) {
			return $istruzione;
		}

		$stato = self::valore_scritto( $insieme, 'post_status' );
		$tipo  = self::valore_scritto( $insieme, 'post_type' );

		if ( ! self::riga_ammessa( $atto_id, $riga, $stato, $tipo, $insieme ) ) {
			self::ferma_richiesta( $atto_id );

			return '';
		}

		if ( ! TipoAtto::tipo_nostro() || ( TIPO !== $riga['post_type'] && TIPO !== $tipo ) ) {
			return $istruzione;
		}

		/*
		 * **Il controllo e la scrittura in un colpo solo.** La riga e' stata
		 * giudicata con lo stato appena letto, ma fra questa lettura e
		 * l'istruzione un'altra richiesta puo' ancora cambiarla: l'istruzione
		 * scrive solo se la riga ha ancora quello stato. Se non scrive, lo
		 * scopre la verifica che segue, prima degli annunci di WordPress.
		 */
		if ( $vagliata ) {
			self::$attese[ $atto_id ] = array(
				'scritto' => null === $stato ? (string) $riga['post_status'] : $stato,
				'letto'   => (string) $riga['post_status'],
			);
		}

		return $istruzione . " AND `post_status` = '" . esc_sql( (string) $riga['post_status'] ) . "'";
	}

	/**
	 * Il primo annuncio dopo la scrittura della riga: la verifica, prima di tutti gli altri.
	 *
	 * Fra l'istruzione e questo annuncio `wp_insert_post` assegna solo voci e
	 * dati collegati, che su un atto fermo hanno le loro difese.
	 *
	 * @param mixed $nuovo   Stato nuovo.
	 * @param mixed $vecchio Stato vecchio.
	 * @param mixed $atto    Contenuto.
	 */
	public static function da_transition_post_status( $nuovo, $vecchio, $atto ): void {
		if ( $atto instanceof \WP_Post ) {
			self::verifica_scrittura( (int) $atto->ID );
		}
	}

	/**
	 * Ferma la richiesta se la scrittura vagliata non e' arrivata alla banca dati.
	 *
	 * La riga ha lo stato scritto, o quello letto se l'istruzione non e'
	 * nemmeno arrivata, per un errore della banca dati che WordPress riferisce
	 * da se'. Uno stato diverso dai due vuol dire che un'altra richiesta ha
	 * cambiato l'atto e che questa non ha scritto niente: WordPress
	 * annuncerebbe una scrittura che non c'e' stata, e la richiesta si ferma.
	 *
	 * @param int $atto_id Atto.
	 */
	private static function verifica_scrittura( int $atto_id ): void {
		if ( ! isset( self::$attese[ $atto_id ] ) ) {
			return;
		}

		$attesa = self::$attese[ $atto_id ];

		unset( self::$attese[ $atto_id ] );

		$stato = self::stato_diretto( $atto_id );

		if ( null !== $stato && $attesa['scritto'] !== $stato && $attesa['letto'] !== $stato ) {
			self::ferma_richiesta( $atto_id );
		}
	}

	/**
	 * Le scritture dei dati e delle voci di un atto, condizionate allo stato dell'atto in quel momento.
	 *
	 * **Il controllo e la scrittura in un colpo solo, anche qui.** I filtri dei
	 * metadati e gli annunci delle voci giudicano lo stato prima della
	 * scrittura, e fra quel giudizio e l'istruzione WordPress fa girare altri
	 * agganci: intanto un'altra richiesta puo' mandare l'atto in verifica e
	 * pubblicarlo. L'istruzione che scrive un dato o una voce porta con se' la
	 * condizione che l'atto non sia fermo, letta dalla banca dati nell'istante
	 * della scrittura; i dati di servizio restano liberi come nei filtri, salvo
	 * quando l'istruzione li rinomina. Gli atti di un passaggio in corso in
	 * questa richiesta ne sono esclusi: li governano la concessione del
	 * passaggio e il suo controllo finale. Un'istruzione in una forma diversa
	 * da quelle di WordPress non si tocca.
	 *
	 * @param string $istruzione Istruzione.
	 * @return string|null L'istruzione condizionata, o nulla se non scrive dati o voci.
	 */
	private static function condiziona_dipendente( string $istruzione ): ?string {
		global $wpdb;

		if ( ! TipoAtto::tipo_nostro() ) {
			return null;
		}

		$tabelle = array(
			$wpdb->postmeta           => 'post_id',
			$wpdb->term_relationships => 'object_id',
		);

		foreach ( $tabelle as $tabella => $colonna ) {
			$nome = '`?' . preg_quote( $tabella, '/' ) . '`?';

			if ( 1 === preg_match( '/^INSERT INTO ' . $nome . ' \(([^()]*)\) VALUES \((.*)\)$/s', $istruzione, $parti ) ) {
				return self::inserimento_condizionato( $istruzione, $tabella, $colonna, $parti[1], $parti[2] );
			}

			if ( 1 === preg_match( '/^(?:UPDATE ' . $nome . ' SET |DELETE FROM ' . $nome . ' WHERE )/', $istruzione ) ) {
				return self::modifica_condizionata( $istruzione, $tabella, $colonna );
			}
		}

		return null;
	}

	/**
	 * Un inserimento di una riga sola, riscritto perche' avvenga solo se l'atto non e' fermo.
	 *
	 * @param string $istruzione Istruzione.
	 * @param string $tabella    Tabella.
	 * @param string $colonna    Colonna dell'atto.
	 * @param string $colonne    Elenco delle colonne.
	 * @param string $valori     Elenco dei valori.
	 * @return string
	 */
	private static function inserimento_condizionato( string $istruzione, string $tabella, string $colonna, string $colonne, string $valori ): string {
		global $wpdb;

		$nomi   = array_map(
			static function ( string $nome ): string {
				return trim( $nome, " `\t\n" );
			},
			explode( ',', $colonne )
		);
		$elenco = self::valori( $valori );

		if ( null === $elenco || count( $elenco ) !== count( $nomi ) ) {
			return $istruzione;
		}

		$riga = array_combine( $nomi, $elenco );

		if ( ! isset( $riga[ $colonna ] ) || 1 !== preg_match( '/^\d+$/', $riga[ $colonna ] ) ) {
			return $istruzione;
		}

		if ( $wpdb->postmeta === $tabella && isset( $riga['meta_key'] ) && in_array( $riga['meta_key'], self::chiavi_di_servizio_scritte(), true ) ) {
			return $istruzione;
		}

		return substr( $istruzione, 0, (int) strpos( $istruzione, ' VALUES (' ) ) . ' SELECT ' . $valori . ' FROM DUAL WHERE ' . self::non_fermo( $riga[ $colonna ] );
	}

	/**
	 * Una modifica o una cancellazione, condizionata riga per riga allo stato del suo atto.
	 *
	 * @param string $istruzione Istruzione.
	 * @param string $tabella    Tabella.
	 * @param string $colonna    Colonna dell'atto.
	 * @return string
	 */
	private static function modifica_condizionata( string $istruzione, string $tabella, string $colonna ): string {
		global $wpdb;

		$dove = self::fuori_dalle_virgolette( $istruzione, ' WHERE ' );

		if ( null === $dove ) {
			return $istruzione;
		}

		$testa      = substr( $istruzione, 0, $dove );
		$condizione = self::non_fermo( '`' . $tabella . '`.`' . $colonna . '`' );

		/*
		 * Una riga di servizio resta libera, anche quando WordPress riscrive la
		 * chiave insieme al valore, come fa l'aggiornamento per numero di riga:
		 * conta che la chiave nuova sia anch'essa di servizio. Rinominata in un
		 * dato, la riga non e' piu' libera.
		 */
		if ( $wpdb->postmeta === $tabella && self::chiave_nuova_di_servizio( $testa ) ) {
			$condizione = '( `' . $tabella . '`.`meta_key` IN ( ' . implode( ', ', self::chiavi_di_servizio_scritte() ) . ' ) OR ' . $condizione . ' )';
		}

		return $testa . ' WHERE ( ' . substr( $istruzione, $dove + strlen( ' WHERE ' ) ) . ' ) AND ' . $condizione;
	}

	/**
	 * Se la chiave che l'istruzione scrive e' di servizio, o se l'istruzione non scrive nessuna chiave.
	 *
	 * @param string $testa Parte dell'istruzione prima della condizione.
	 * @return bool
	 */
	private static function chiave_nuova_di_servizio( string $testa ): bool {
		$campo     = '`meta_key` = ';
		$posizione = self::fuori_dalle_virgolette( $testa, $campo );

		if ( null === $posizione ) {
			return true;
		}

		$resto = substr( $testa, $posizione + strlen( $campo ) );

		foreach ( self::chiavi_di_servizio_scritte() as $chiave ) {
			if ( $resto === $chiave || 0 === strpos( $resto, $chiave . ',' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * La condizione che l'atto indicato non sia un atto fermo, fuori dai passaggi in corso qui.
	 *
	 * @param string $riferimento Numero dell'atto, o colonna che lo contiene.
	 * @return string
	 */
	private static function non_fermo( string $riferimento ): string {
		global $wpdb;

		$in_corso = array_map( 'intval', Passaggi::atti_in_corso() );
		$fermi    = array_map(
			static function ( string $stato ): string {
				return "'" . esc_sql( $stato ) . "'";
			},
			self::STATI_FERMI
		);

		return 'NOT EXISTS ( SELECT 1 FROM `' . $wpdb->posts . '` AS albo_atto WHERE albo_atto.ID = ' . $riferimento
			. " AND albo_atto.post_type = '" . esc_sql( TIPO ) . "' AND albo_atto.post_status IN ( " . implode( ', ', $fermi ) . ' )'
			. ( array() === $in_corso ? '' : ' AND albo_atto.ID NOT IN ( ' . implode( ', ', $in_corso ) . ' )' )
			. ' )';
	}

	/**
	 * Le chiavi di servizio come le scrive un'istruzione.
	 *
	 * @return array<int, string>
	 */
	private static function chiavi_di_servizio_scritte(): array {
		return array_map(
			static function ( string $chiave ): string {
				return "'" . esc_sql( $chiave ) . "'";
			},
			self::CHIAVI_DI_SERVIZIO
		);
	}

	/**
	 * La prima posizione di un pezzo fuori dai valori tra apici, o nulla.
	 *
	 * @param string $istruzione Istruzione.
	 * @param string $pezzo      Pezzo cercato.
	 * @return int|null
	 */
	private static function fuori_dalle_virgolette( string $istruzione, string $pezzo ): ?int {
		$lunghezza = strlen( $istruzione );
		$dentro    = false;

		for ( $i = 0; $i < $lunghezza; $i++ ) {
			$carattere = $istruzione[ $i ];

			if ( $dentro ) {
				if ( '\\' === $carattere ) {
					++$i;
				} elseif ( "'" === $carattere ) {
					$dentro = false;
				}

				continue;
			}

			if ( "'" === $carattere ) {
				$dentro = true;
			} elseif ( 0 === substr_compare( $istruzione, $pezzo, $i, strlen( $pezzo ) ) ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * I valori di una riga sola, separati, o nulla se l'elenco non e' una riga sola di valori semplici.
	 *
	 * @param string $valori Elenco dei valori.
	 * @return array<int, string>|null
	 */
	private static function valori( string $valori ): ?array {
		$elenco    = array();
		$corrente  = '';
		$dentro    = false;
		$lunghezza = strlen( $valori );

		for ( $i = 0; $i < $lunghezza; $i++ ) {
			$carattere = $valori[ $i ];

			if ( $dentro ) {
				$corrente .= $carattere;

				if ( '\\' === $carattere && $i + 1 < $lunghezza ) {
					$corrente .= $valori[ ++$i ];
				} elseif ( "'" === $carattere ) {
					$dentro = false;
				}

				continue;
			}

			if ( '(' === $carattere || ')' === $carattere ) {
				return null;
			}

			if ( ',' === $carattere ) {
				$elenco[] = trim( $corrente );
				$corrente = '';

				continue;
			}

			if ( "'" === $carattere ) {
				$dentro = true;
			}

			$corrente .= $carattere;
		}

		if ( $dentro ) {
			return null;
		}

		$elenco[] = trim( $corrente );

		return $elenco;
	}

	/**
	 * Dopo l'assegnazione di una voce a un atto: ferma la richiesta se l'assegnazione non e' avvenuta.
	 *
	 * WordPress annuncia l'assegnazione senza guardare se l'istruzione ha
	 * scritto; se l'atto nel frattempo e' diventato fermo, non l'ha fatto.
	 *
	 * @param mixed $atto_id Contenuto.
	 * @param mixed $voce    Voce, come numero della coppia voce ed elenco.
	 */
	public static function da_voce_aggiunta( $atto_id, $voce ): void {
		global $wpdb;

		$atto_id = (int) $atto_id;

		if ( ! TipoAtto::tipo_nostro() || null === self::stato_diretto( $atto_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura della banca dati com'e', senza la memoria di WordPress, per sapere se la scrittura e' avvenuta.
		$c_e = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id = %d", $atto_id, (int) $voce ) );

		if ( 0 === $c_e ) {
			self::ferma_richiesta( $atto_id );
		}
	}

	/**
	 * Dopo la rimozione di voci da un atto: ferma la richiesta se la rimozione non e' avvenuta.
	 *
	 * @param mixed $atto_id Contenuto.
	 * @param mixed $voci    Voci tolte, come numeri delle coppie voce ed elenco.
	 */
	public static function da_voci_tolte( $atto_id, $voci ): void {
		global $wpdb;

		$atto_id = (int) $atto_id;
		$voci    = array_map( 'intval', (array) $voci );

		if ( ! TipoAtto::tipo_nostro() || array() === $voci || null === self::stato_diretto( $atto_id ) ) {
			return;
		}

		$segnaposto = implode( ', ', array_fill( 0, count( $voci ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- lettura della banca dati com'e', con segnaposto costruiti qui sopra.
		$restano = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id IN ( {$segnaposto} )", array_merge( array( $atto_id ), $voci ) ) );

		if ( 0 !== $restano ) {
			self::ferma_richiesta( $atto_id );
		}
	}

	/**
	 * Dopo la cancellazione di dati di un atto: ferma la richiesta se la cancellazione non e' avvenuta.
	 *
	 * WordPress annuncia la cancellazione per numero di riga anche quando
	 * l'istruzione non ha tolto niente.
	 *
	 * L'atto si rilegge dalle righe rimaste, non si prende dall'annuncio.
	 *
	 * @param mixed $righe Numeri delle righe tolte.
	 */
	public static function da_metadati_tolti( $righe ): void {
		global $wpdb;

		$righe = array_map( 'intval', (array) $righe );

		if ( ! TipoAtto::tipo_nostro() || array() === $righe ) {
			return;
		}

		$segnaposto = implode( ', ', array_fill( 0, count( $righe ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- lettura della banca dati com'e', con segnaposto costruiti qui sopra.
		$restano = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_id IN ( {$segnaposto} )", $righe ) );

		foreach ( array_unique( array_map( 'intval', (array) $restano ) ) as $atto ) {
			if ( null !== self::stato_diretto( $atto ) ) {
				self::ferma_richiesta( $atto );
			}
		}
	}

	/**
	 * Il valore che l'istruzione scrive in una colonna, nullo se non la scrive.
	 *
	 * Dentro un valore tra apici ogni apice e' preceduto dalla barra di
	 * protezione, quindi la sequenza cercata compare solo come campo vero. Se
	 * compare piu' volte con valori diversi il risultato e' un valore che non
	 * esiste, e la scrittura non corrisponde a niente di ammesso.
	 *
	 * @param string $insieme Parte `SET` dell'istruzione.
	 * @param string $colonna Colonna.
	 * @return string|null
	 */
	private static function valore_scritto( string $insieme, string $colonna ): ?string {
		if ( false === strpos( $insieme, '`' . $colonna . '`' ) ) {
			return null;
		}

		preg_match_all( '/(?:^|, )`' . $colonna . "` = '([a-z0-9_-]*)'/", $insieme, $trovati );

		$valori = array_values( array_unique( $trovati[1] ) );

		return 1 === count( $valori ) ? $valori[0] : '?';
	}

	/**
	 * Se la scrittura della riga e' ammessa.
	 *
	 * @param int                   $atto_id Atto.
	 * @param array<string, string> $riga    Tipo, stato e nome memorizzati.
	 * @param string|null           $stato   Stato scritto, se l'istruzione lo scrive.
	 * @param string|null           $tipo    Tipo scritto, se l'istruzione lo scrive.
	 * @param string                $insieme Parte `SET` dell'istruzione.
	 * @return bool
	 */
	private static function riga_ammessa( int $atto_id, array $riga, ?string $stato, ?string $tipo, string $insieme ): bool {
		$nostro_prima = TIPO === $riga['post_type'];
		$nostro_dopo  = TIPO === ( null === $tipo ? $riga['post_type'] : $tipo );

		if ( ( ! $nostro_prima && ! $nostro_dopo ) || ! TipoAtto::tipo_nostro() ) {
			return true;
		}

		if ( isset( self::$vagliate[ $atto_id ] ) ) {
			$vagliata = self::$vagliate[ $atto_id ];

			unset( self::$vagliate[ $atto_id ] );

			if ( $vagliata['passaggio'] && ! Passaggi::in_corso( $atto_id ) ) {
				return false;
			}

			// Il vaglio vale per lo stato da cui e' partito: se la riga ne ha un altro, non vale piu'.
			if ( null === $vagliata['partenza'] || $vagliata['partenza'] !== $riga['post_status'] ) {
				return false;
			}

			return $nostro_prima && ( null === $stato || $vagliata['stato'] === $stato ) && ( null === $tipo || TIPO === $tipo );
		}

		// Il nome nell'indirizzo che WordPress genera subito dopo la pubblicazione.
		if ( $nostro_prima && self::PUBBLICATO === $riga['post_status'] && '' === (string) $riga['post_name']
			&& Passaggi::in_corso( $atto_id ) && 1 === preg_match( "/^`post_name` = '[a-z0-9%_-]*'$/", $insieme ) ) {
			return true;
		}

		if ( Passaggi::in_corso( $atto_id ) || ( $nostro_prima && in_array( $riga['post_status'], self::STATI_FERMI, true ) ) ) {
			return false;
		}

		return ! $nostro_dopo || in_array( null === $stato ? $riga['post_status'] : $stato, self::STATI_LIBERI, true );
	}

	/**
	 * Ferma la richiesta: una scrittura che nessun passaggio dell'albo ha concesso.
	 *
	 * Rifiutare la sola istruzione non basta: la funzione che l'ha mandata
	 * proseguirebbe annunciando un passaggio che non e' avvenuto. Dentro un
	 * passaggio in corso invece la richiesta non si ferma: il rifiuto si
	 * annota al passaggio, che annulla tutto nella sua transazione e risponde
	 * con il suo errore, invece di lasciare la transazione aperta.
	 *
	 * Le scritture che si rifiutano prima che comincino, al primo livello o
	 * sui metadati, non lasciano niente a valle: dentro un passaggio si
	 * rifiutano e basta, e il passaggio prosegue con l'atto com'era.
	 *
	 * @param int $atto_id Atto.
	 */
	private static function ferma_richiesta( int $atto_id ): void {
		if ( Passaggi::annota_rifiuto() ) {
			return;
		}

		wp_die(
			esc_html(
				sprintf(
					/* translators: %d: identificativo dell'atto. */
					__( 'Scrittura rifiutata: l\'atto %d dell\'albo e\' in verifica, pubblicato o in mezzo a un passaggio, oppure la scrittura lo porterebbe fuori dalla bozza senza i passaggi dell\'albo. Nessuna modifica e\' stata fatta.', 'albo-pretorio-pa' ),
					$atto_id
				)
			),
			esc_html__( 'Scrittura rifiutata', 'albo-pretorio-pa' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Un metadato di un atto fermo non si aggiunge.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $atto_id Contenuto.
	 * @param mixed $chiave  Chiave.
	 * @param mixed $valore  Valore.
	 * @return mixed
	 */
	public static function da_add_post_metadata( $esito, $atto_id, $chiave, $valore ) {
		return self::metadato_ammesso( (int) $atto_id, (string) $chiave, $valore, false ) ? $esito : false;
	}

	/**
	 * Un metadato di un atto fermo non si cambia.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $atto_id Contenuto.
	 * @param mixed $chiave  Chiave.
	 * @param mixed $valore  Valore.
	 * @return mixed
	 */
	public static function da_update_post_metadata( $esito, $atto_id, $chiave, $valore ) {
		return self::metadato_ammesso( (int) $atto_id, (string) $chiave, $valore, false ) ? $esito : false;
	}

	/**
	 * Un metadato di un atto fermo non si toglie, nemmeno togliendolo a tutti i contenuti.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $atto_id Contenuto.
	 * @param mixed $chiave  Chiave.
	 * @param mixed $valore  Valore.
	 * @param mixed $tutti   Se la cancellazione vale per tutti i contenuti.
	 * @return mixed
	 */
	public static function da_delete_post_metadata( $esito, $atto_id, $chiave, $valore, $tutti ) {
		if ( ! $tutti ) {
			return self::metadato_ammesso( (int) $atto_id, (string) $chiave, null, true ) ? $esito : false;
		}

		return self::chiave_libera( (string) $chiave ) ? $esito : false;
	}

	/**
	 * Lo stesso, per la modifica di una riga indicata per numero, anche quando la rinomina.
	 *
	 * WordPress permette di dare alla riga una chiave nuova insieme al valore:
	 * la scrittura toglie la chiave vecchia e scrive la nuova, e devono essere
	 * ammesse tutte e due. Cosi' una riga di servizio non diventa un dato
	 * dell'atto.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $meta_id Numero della riga.
	 * @param mixed $valore  Valore.
	 * @param mixed $nuova   Chiave nuova, o falso se la chiave resta.
	 * @return mixed
	 */
	public static function da_update_post_metadata_by_mid( $esito, $meta_id, $valore, $nuova = false ) {
		$riga = self::riga_di_metadato( (int) $meta_id );

		if ( null === $riga ) {
			return $esito;
		}

		$atto_id  = (int) $riga['post_id'];
		$chiave   = (string) $riga['meta_key'];
		$rinomina = is_string( $nuova ) && '' !== $nuova && $nuova !== $chiave;

		if ( ! $rinomina ) {
			return self::metadato_ammesso( $atto_id, $chiave, $valore, false ) ? $esito : false;
		}

		return self::metadato_ammesso( $atto_id, $chiave, null, true ) && self::metadato_ammesso( $atto_id, $nuova, $valore, false ) ? $esito : false;
	}

	/**
	 * Lo stesso, per la cancellazione di una riga indicata per numero.
	 *
	 * @param mixed $esito   Risposta proposta, nulla per proseguire.
	 * @param mixed $meta_id Numero della riga.
	 * @return mixed
	 */
	public static function da_delete_post_metadata_by_mid( $esito, $meta_id ) {
		$riga = self::riga_di_metadato( (int) $meta_id );

		return null === $riga || self::metadato_ammesso( (int) $riga['post_id'], (string) $riga['meta_key'], null, true ) ? $esito : false;
	}

	/**
	 * La riga di metadato indicata per numero.
	 *
	 * @param int $meta_id Numero della riga.
	 * @return array<string, string>|null
	 */
	private static function riga_di_metadato( int $meta_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura della riga com'e' nella banca dati, senza la memoria di WordPress, per giudicare la scrittura che sta per arrivare.
		$riga = $wpdb->get_row(
			$wpdb->prepare( "SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_id ),
			ARRAY_A
		);

		return is_array( $riga ) ? $riga : null;
	}

	/**
	 * Se la scrittura di un metadato di questo contenuto e' ammessa.
	 *
	 * Su un atto fermo passano solo i metadati di servizio. Durante una
	 * pubblicazione passa anche la data di fine, e soltanto con il valore che
	 * `Passaggi` ha calcolato.
	 *
	 * @param int    $atto_id       Contenuto.
	 * @param string $chiave        Chiave.
	 * @param mixed  $valore        Valore, per una scrittura.
	 * @param bool   $cancellazione Se e' una cancellazione.
	 * @return bool
	 */
	private static function metadato_ammesso( int $atto_id, string $chiave, $valore, bool $cancellazione ): bool {
		if ( $atto_id <= 0 || in_array( $chiave, self::CHIAVI_DI_SERVIZIO, true ) || ! TipoAtto::tipo_nostro() ) {
			return true;
		}

		$stato = self::stato_diretto( $atto_id );

		if ( null === $stato ) {
			return true;
		}

		if ( Passaggi::in_corso( $atto_id ) ) {
			$fine = Passaggi::fine_attesa( $atto_id );

			return null !== $fine && conformita_core_chiave_fine_pubblicazione() === $chiave && ( $cancellazione || $fine === $valore );
		}

		return ! in_array( $stato, self::STATI_FERMI, true );
	}

	/**
	 * Se una chiave si puo' togliere a tutti i contenuti: nessun atto fermo la porta.
	 *
	 * @param string $chiave Chiave.
	 * @return bool
	 */
	private static function chiave_libera( string $chiave ): bool {
		global $wpdb;

		if ( in_array( $chiave, self::CHIAVI_DI_SERVIZIO, true ) || ! TipoAtto::tipo_nostro() ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura della riga com'e' nella banca dati, senza la memoria di WordPress, per giudicare la scrittura che sta per arrivare.
		$atti = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s AND p.post_status IN ( %s, %s )",
				$chiave,
				TIPO,
				self::IN_VERIFICA,
				self::PUBBLICATO
			)
		);

		if ( array() !== ( is_array( $atti ) ? $atti : array() ) ) {
			return false;
		}

		foreach ( Passaggi::atti_in_corso() as $atto_id ) {
			if ( metadata_exists( 'post', $atto_id, $chiave ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Lo stato di un atto nostro letto dalla banca dati, o nullo se non e' un atto nostro.
	 *
	 * @param int $atto_id Contenuto.
	 * @return string|null
	 */
	private static function stato_diretto( int $atto_id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura della riga com'e' nella banca dati, senza la memoria di WordPress, per giudicare la scrittura che sta per arrivare.
		$riga = $wpdb->get_row(
			$wpdb->prepare( "SELECT post_type, post_status FROM {$wpdb->posts} WHERE ID = %d", $atto_id ),
			ARRAY_A
		);

		return is_array( $riga ) && TIPO === $riga['post_type'] ? (string) $riga['post_status'] : null;
	}

	/**
	 * Le voci di un atto fermo non si assegnano e non si tolgono.
	 *
	 * WordPress annuncia l'assegnazione e la rimozione prima di farle, senza
	 * un modo per rifiutarle: la richiesta si ferma.
	 *
	 * @param mixed $atto_id Contenuto.
	 */
	public static function da_voce_dell_atto( $atto_id ): void {
		$atto_id = (int) $atto_id;
		$stato   = TipoAtto::tipo_nostro() ? self::stato_diretto( $atto_id ) : null;

		if ( null !== $stato && ( in_array( $stato, self::STATI_FERMI, true ) || Passaggi::in_corso( $atto_id ) ) ) {
			self::ferma_richiesta( $atto_id );
		}
	}
}
