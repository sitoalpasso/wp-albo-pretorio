<?php
/**
 * Il guardiano dei passaggi di stato degli atti, e lo sbarramento che tiene
 * chiusa la pubblicazione.
 *
 * **Non e' una condizione, e' un elenco ordinato di regole.** La prima nega la
 * pubblicazione e la programmazione, perche' la pubblicazione non e' ancora
 * aperta. Le altre applicano l'elenco chiuso dei passaggi deciso con ALBO-22:
 * un atto nasce in bozza, la bozza si risalva, passa in verifica solo con tutti
 * i dati che la pubblicazione pretende, e in verifica non si modifica piu'. La
 * lavorazione che apre la pubblicazione toglie la prima regola e aggiunge i
 * passaggi che partono dalla verifica.
 *
 * **Due modi di rifiutare, secondo lo stato di partenza.** Da una bozza, il
 * rifiuto lascia avvenire la scrittura con lo stato di bozza: la bozza e'
 * modificabile, e quello che chi redige ha scritto si conserva. Da un atto in
 * verifica, la scrittura si ferma per intero prima di cominciare, perche' non
 * solo lo stato ma nemmeno l'oggetto, le voci o i dati collegati devono
 * cambiare.
 *
 * **Tre cose diverse, e questa classe ne copre una sola.** Gli ingressi
 * supportati passano tutti da `wp_insert_post`, quindi da qui, e vengono
 * fermati prima della scrittura: non esiste nessun istante in cui la riga
 * abbia lo stato pubblicato o programmato. La superficie per programmi non
 * esiste, quindi non e' un ingresso da intercettare. E `wp_publish_post()`,
 * chiamata di persona da codice di terzi, scrive lo stato direttamente nella
 * banca dati e **non passa di qui**: quel caso non si impedisce, e la garanzia
 * e' che il contenuto resti comunque irraggiungibile, perche' il tipo non e'
 * interrogabile dal pubblico.
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
	 * Gli stati che questa fase non concede.
	 *
	 * Anche quello programmato: conservare una pubblicazione che sappiamo non
	 * potersi concludere sarebbe una promessa non mantenibile.
	 *
	 * @return array<int, string>
	 */
	public static function stati_non_concessi(): array {
		return array( 'publish', 'future' );
	}

	/**
	 * Aggancia lo sbarramento agli ingressi supportati.
	 */
	public static function aggancia(): void {
		add_filter( 'wp_insert_post_empty_content', array( self::class, 'da_wp_insert_post_empty_content' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_insert_post_data', array( self::class, 'da_wp_insert_post_data' ), 10, 2 );
		add_action( 'wp_insert_post', array( self::class, 'da_wp_insert_post' ), 10, 2 );
		add_filter( 'pre_trash_post', array( self::class, 'da_pre_trash_post' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_post', array( self::class, 'da_pre_delete_post' ), PHP_INT_MAX, 2 );
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
			$motivi['albo_pubblicazione_non_aperta'] = sprintf(
				/* translators: %s: nome del componente. */
				__( '%s: la pubblicazione degli atti non e\' ancora aperta, quindi l\'atto resta in bozza. Un atto si pubblica passando dalla verifica, e la pubblicazione scrive chi e quando nel registro delle modifiche del meccanismo comune, che non e\' ancora disponibile.', 'albo-pretorio-pa' ),
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
	 * Il motivo del fermo di ogni scrittura su un atto in verifica.
	 *
	 * @return array<string, string>
	 */
	private static function motivi_in_verifica(): array {
		return array(
			'albo_atto_in_verifica' => __( 'L\'atto e\' in verifica e non si modifica: quello che e\' stato verificato e\' quello che esce. Per correggerlo dovra\' tornare in bozza con una motivazione, passaggio che arriva con il registro delle modifiche.', 'albo-pretorio-pa' ),
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
	 * Ferma per intero ogni scrittura su un atto in verifica, prima che cominci.
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
		$atto_id = is_array( $postarr ) && isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( self::IN_VERIFICA !== self::stato_memorizzato( $atto_id ) ) {
			return $vuoto;
		}

		Rifiuti::deposita( $atto_id, self::motivi_in_verifica() );

		return true;
	}

	/**
	 * Un atto in verifica non va nel cestino.
	 *
	 * @param mixed    $esito Risposta proposta, nullo per proseguire.
	 * @param \WP_Post $atto  Contenuto.
	 * @return mixed
	 */
	public static function da_pre_trash_post( $esito, $atto ) {
		if ( ! $atto instanceof \WP_Post || self::IN_VERIFICA !== self::stato_memorizzato( (int) $atto->ID ) ) {
			return $esito;
		}

		Rifiuti::deposita( (int) $atto->ID, self::motivi_in_verifica() );

		return false;
	}

	/**
	 * Un atto in verifica non si cancella.
	 *
	 * @param mixed    $esito Risposta proposta, nullo per proseguire.
	 * @param \WP_Post $atto  Contenuto.
	 * @return mixed
	 */
	public static function da_pre_delete_post( $esito, $atto ) {
		if ( ! $atto instanceof \WP_Post || self::IN_VERIFICA !== self::stato_memorizzato( (int) $atto->ID ) ) {
			return $esito;
		}

		Rifiuti::deposita( (int) $atto->ID, self::motivi_in_verifica() );

		return false;
	}

	/**
	 * Riporta in bozza la scrittura in corso, prima che avvenga.
	 *
	 * WordPress applica questo filtro **prima** di scrivere nella tabella dei
	 * contenuti: e' il motivo per cui non esiste un istante in cui l'atto
	 * risulti pubblicato. Una correzione fatta dopo la scrittura sarebbe una
	 * riparazione tardiva, con quell'istante in mezzo e con gli effetti
	 * collaterali della pubblicazione gia' partiti.
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
		 * Un atto in verifica non arriva fin qui: la scrittura e' gia' stata
		 * fermata. Se ci arriva lo stesso, perche' qualcuno ha scavalcato quel
		 * fermo, la riga si riscrive com'era: in verifica, con i suoi valori.
		 */
		if ( self::IN_VERIFICA === $partenza ) {
			$memorizzato = get_post( $atto_id, ARRAY_A );

			foreach ( array_keys( $data ) as $campo ) {
				if ( is_array( $memorizzato ) && array_key_exists( $campo, $memorizzato ) ) {
					$data[ $campo ] = $memorizzato[ $campo ];
				}
			}

			self::$pila[] = array(
				'id'     => $atto_id,
				'motivi' => self::motivi_in_verifica(),
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

		if ( array() === $motivi ) {
			return $data;
		}

		$data['post_status'] = 'draft';

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
}
