<?php
/**
 * Le copie nello storico pubblico: ingresso e uscita (ALBO-36).
 *
 * Una copia nasce da un atto arrivato regolarmente alla fine, per scelta di
 * chi ha il permesso dello storico (l'utente di questa richiesta, lo stesso
 * che il registro scrive), con un documento caricato a parte da cui
 * sono stati tolti i dati personali, un oggetto proprio e la conferma del
 * controllo. Prende una fine sua, calcolata con gli anni in vigore, e alla
 * fine sparisce per la data letta. Si toglie in ogni momento, con una causa
 * dell'elenco e un motivo. Ingresso e uscita vanno nel registro.
 *
 * L'atto non cambia: resta scaduto, irraggiungibile, con il suo documento
 * originale nella consultazione riservata.
 *
 * Righe di collaudo A-266..A-271.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Ingresso, uscita e lettura delle copie.
 */
final class CopieStorico {

	/**
	 * Il dato della copia che dice di quale atto e' copia.
	 *
	 * @var string
	 */
	const META_ATTO = '_albo_pretorio_copia_atto';

	/**
	 * I dati dell'atto fissati all'ingresso, quelli che la pagina mostrera'.
	 *
	 * @var string
	 */
	const META_DATI = '_albo_pretorio_copia_dati';

	/**
	 * Il documento della copia.
	 *
	 * @var string
	 */
	const META_DOCUMENTO = '_albo_pretorio_copia_documento';

	/**
	 * Il giorno in cui la copia e' entrata nello storico, nel fuso del sito.
	 *
	 * @var string
	 */
	const META_INGRESSO = '_albo_pretorio_copia_ingresso';

	/**
	 * Azione della voce di registro dell'ingresso.
	 *
	 * @var string
	 */
	const AZIONE_INGRESSO = 'ingresso_copia';

	/**
	 * Azione della voce di registro dell'uscita.
	 *
	 * @var string
	 */
	const AZIONE_USCITA = 'uscita_copia';

	/**
	 * Lunghezza massima dell'oggetto della copia.
	 *
	 * @var int
	 */
	const OGGETTO_MASSIMO = 1000;

	/**
	 * Le cause di uscita, con il loro nome visibile.
	 *
	 * @return array<string, string>
	 */
	public static function cause(): array {
		return array(
			'richiesta_interessato'  => __( 'Richiesta di una persona nominata nella copia (regolamento UE 2016/679, art. 17)', 'albo-pretorio-pa' ),
			'dati_nella_copia'       => __( 'Nella copia sono rimasti dati personali', 'albo-pretorio-pa' ),
			'scelta_amministrazione' => __( 'Scelta dell\'amministrazione', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * L'atto puo' entrare nello storico: e' arrivato regolarmente alla fine.
	 *
	 * Pubblicato, con il numero di registro, con la fine passata nel fuso del
	 * sito. Non un annullato, non un atto tolto dall'albo prima della fine:
	 * il loro documento non si rimette in circolazione, nemmeno oscurato.
	 * Gli importati scaduti entrano come gli altri.
	 *
	 * @param int $atto Atto.
	 * @return true|\WP_Error
	 */
	public static function ammissibile( int $atto ) {
		$contenuto = get_post( $atto );

		if ( ! $contenuto instanceof \WP_Post || TIPO !== $contenuto->post_type ) {
			return self::errore( 'albo_copia_non_atto', __( 'Copia non creata: il contenuto indicato non e\' un atto dell\'albo.', 'albo-pretorio-pa' ) );
		}

		if ( ChiusuraPubblicazione::PUBBLICATO !== $contenuto->post_status ) {
			return self::errore( 'albo_copia_non_regolare', __( 'Copia non creata: nello storico entrano soltanto gli atti arrivati regolarmente alla fine, non quelli annullati o tolti dall\'albo prima della scadenza.', 'albo-pretorio-pa' ) );
		}

		if ( null === Repertorio::di( $atto ) ) {
			return self::errore( 'albo_copia_senza_numero', __( 'Copia non creata: l\'atto non ha un numero di registro.', 'albo-pretorio-pa' ) );
		}

		if ( true !== conformita_core_scaduto( $atto ) || ! is_string( conformita_core_fine_pubblicazione( $atto ) ) ) {
			return self::errore( 'albo_copia_in_corso', __( 'Copia non creata: la pubblicazione dell\'atto non e\' ancora finita.', 'albo-pretorio-pa' ) );
		}

		return true;
	}

	/**
	 * Mette nello storico la copia di un atto, tutto o niente.
	 *
	 * @param int                  $atto     Atto.
	 * @param array<string, mixed> $file     Documento oscurato, nella forma di `$_FILES`.
	 * @param mixed                $oggetto  Oggetto della copia.
	 * @param mixed                $conferma Conferma del controllo: vale solo `true`.
	 * @param string               $origine  `caricamento` oppure `percorso_locale`, come per il meccanismo comune.
	 * @return int|\WP_Error Numero della copia, oppure errore.
	 */
	public static function metti( int $atto, array $file, $oggetto, $conferma, string $origine = 'caricamento' ) {
		$preliminare = self::preliminare();

		if ( is_wp_error( $preliminare ) ) {
			return $preliminare;
		}

		$ammesso = self::ammissibile( $atto );

		if ( is_wp_error( $ammesso ) ) {
			return $ammesso;
		}

		$presenti = self::presenti_di( $atto );

		if ( null === $presenti ) {
			return self::errore( 'albo_copia_lettura', __( 'Copia non creata: le copie gia\' nello storico non si leggono in questo momento. Riprovare.', 'albo-pretorio-pa' ) );
		}

		if ( array() !== $presenti ) {
			return self::errore( 'albo_copia_gia_presente', __( 'Copia non creata: l\'atto ha gia\' una copia nello storico. Per sostituirla, toglierla e metterne una nuova.', 'albo-pretorio-pa' ) );
		}

		if ( true !== $conferma ) {
			return self::errore( 'albo_copia_senza_conferma', __( 'Copia non creata: va confermato di aver controllato che documento e oggetto non contengono dati personali.', 'albo-pretorio-pa' ) );
		}

		$oggetto = is_string( $oggetto ) ? trim( sanitize_text_field( $oggetto ) ) : '';

		if ( '' === $oggetto ) {
			return self::errore( 'albo_copia_senza_oggetto', __( 'Copia non creata: va scritto l\'oggetto della copia, senza dati personali.', 'albo-pretorio-pa' ) );
		}

		if ( mb_strlen( $oggetto ) > self::OGGETTO_MASSIMO ) {
			return self::errore( 'albo_copia_oggetto_lungo', __( 'Copia non creata: l\'oggetto e\' troppo lungo.', 'albo-pretorio-pa' ) );
		}

		if ( ! isset( $file['tmp_name'], $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || '' === (string) $file['tmp_name'] ) {
			return self::errore( 'albo_copia_senza_documento', __( 'Copia non creata: va caricato il documento oscurato. Il documento originale non entra mai nello storico.', 'albo-pretorio-pa' ) );
		}

		$anni     = (int) Storico::anni();
		$oggi     = Passaggi::ora()->setTimezone( wp_timezone() );
		$fine     = $oggi->modify( '+' . $anni . ' years' )->format( 'Y-m-d' );
		$ingresso = $oggi->format( 'Y-m-d' );
		$dati     = self::fotografia( $atto );

		$copia = wp_insert_post(
			array(
				'post_type'   => TIPO_STORICO,
				'post_status' => 'draft',
				'post_title'  => $oggetto,
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $copia ) || ! is_int( $copia ) || $copia < 1 ) {
			return self::errore( 'albo_copia_non_scritta', __( 'Copia non creata: la banca dati non ha scritto la copia. Riprovare.', 'albo-pretorio-pa' ) );
		}

		$fallita = static function ( string $codice, string $messaggio, ?int $documento = null ) use ( $copia ): \WP_Error {
			if ( null !== $documento ) {
				wp_delete_attachment( $documento, true );
			}

			foreach ( get_attached_media( '', $copia ) as $resto ) {
				wp_delete_attachment( (int) $resto->ID, true );
			}

			wp_delete_post( $copia, true );

			return new \WP_Error( $codice, $messaggio );
		};

		if ( ! update_post_meta( $copia, self::META_ATTO, $atto ) || ! update_post_meta( $copia, self::META_DATI, $dati ) || ! update_post_meta( $copia, self::META_INGRESSO, $ingresso ) ) {
			return $fallita( 'albo_copia_non_scritta', __( 'Copia non creata: la banca dati non ha scritto i dati della copia. Riprovare.', 'albo-pretorio-pa' ) );
		}

		$scritta = conformita_core_imposta_fine_pubblicazione( $copia, $fine );

		if ( is_wp_error( $scritta ) ) {
			return $fallita( 'albo_copia_senza_fine', __( 'Copia non creata: la fine della copia non si e\' scritta.', 'albo-pretorio-pa' ) . ' ' . $scritta->get_error_message() );
		}

		$documento = conformita_core_deposita_allegato( $copia, $file, array( 'origine' => $origine ) );

		if ( is_wp_error( $documento ) ) {
			return $fallita( 'albo_copia_documento', __( 'Copia non creata: il documento non si e\' depositato.', 'albo-pretorio-pa' ) . ' ' . $documento->get_error_message() );
		}

		$documento = (int) $documento;

		if ( ! update_post_meta( $copia, self::META_DOCUMENTO, $documento ) ) {
			return $fallita( 'albo_copia_non_scritta', __( 'Copia non creata: la banca dati non ha scritto il documento della copia. Riprovare.', 'albo-pretorio-pa' ), $documento );
		}

		$impronta = conformita_core_impronta_allegato( $documento );

		if ( is_wp_error( $impronta ) ) {
			return $fallita( 'albo_copia_documento', __( 'Copia non creata: l\'impronta del documento non si legge.', 'albo-pretorio-pa' ), $documento );
		}

		$pubblicata = wp_update_post(
			array(
				'ID'          => $copia,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $pubblicata ) || 'publish' !== get_post_status( $copia ) ) {
			return $fallita( 'albo_copia_non_scritta', __( 'Copia non creata: la copia non si e\' resa pubblica. Riprovare.', 'albo-pretorio-pa' ), $documento );
		}

		/*
		 * Due richieste sullo stesso atto possono passare insieme il controllo
		 * delle copie presenti. Dopo la scrittura si riguarda: resta soltanto
		 * la copia piu' vecchia, e questa, se non e' lei, si ritira prima di
		 * andare nel registro.
		 */
		$dopo = self::presenti_di( $atto );

		if ( null === $dopo || array( $copia ) !== array_slice( $dopo, 0, 1 ) ) {
			return $fallita( 'albo_copia_gia_presente', __( 'Copia non creata: nello stesso momento e\' entrata un\'altra copia dello stesso atto.', 'albo-pretorio-pa' ), $documento );
		}

		$voce = conformita_core_registra_voce(
			array(
				'sezione'   => SEZIONE_STORICO,
				'azione'    => self::AZIONE_INGRESSO,
				'contenuto' => $copia,
				'dettagli'  => array(
					'atto'               => (string) $atto,
					'anno'               => (string) $dati['anno'],
					'numero'             => (string) $dati['numero'],
					'fine_copia'         => $fine,
					'anni'               => (string) $anni,
					'impronta_algoritmo' => (string) $impronta['algoritmo'],
					'impronta_valore'    => (string) $impronta['valore'],
					'conferma_controllo' => 'si',
				),
			)
		);

		if ( is_wp_error( $voce ) ) {
			return $fallita( 'albo_copia_registro', __( 'Copia non creata: il registro non ha scritto la voce, e la copia e\' stata ritirata.', 'albo-pretorio-pa' ) . ' ' . $voce->get_error_message(), $documento );
		}

		return $copia;
	}

	/**
	 * Toglie una copia dallo storico, da subito.
	 *
	 * La fine della copia si riporta al giorno prima: la copia sparisce per la
	 * data letta, come ogni contenuto governato, documento compreso. Resta
	 * salvata, e il registro dice chi l'ha tolta, quando e perche'.
	 *
	 * @param int   $copia  Copia.
	 * @param mixed $causa  Una delle cause dell'elenco.
	 * @param mixed $motivo Motivo, obbligatorio.
	 * @return true|\WP_Error
	 */
	public static function togli( int $copia, $causa, $motivo ) {
		if ( ! Storico::pronto() ) {
			return self::errore( 'albo_copia_fermo', __( 'Copia non tolta: lo storico pubblico non risulta registrato.', 'albo-pretorio-pa' ) );
		}

		if ( ! current_user_can( Permessi::PERMESSO_STORICO ) ) {
			return self::errore( 'albo_copia_non_permesso', __( 'Copia non tolta: non si possiede il permesso di gestire lo storico pubblico.', 'albo-pretorio-pa' ) );
		}

		$contenuto = get_post( $copia );

		if ( ! $contenuto instanceof \WP_Post || TIPO_STORICO !== $contenuto->post_type || 'publish' !== $contenuto->post_status ) {
			return self::errore( 'albo_copia_sconosciuta', __( 'Copia non tolta: la copia indicata non c\'e\'.', 'albo-pretorio-pa' ) );
		}

		if ( true === conformita_core_scaduto( $copia ) ) {
			return self::errore( 'albo_copia_gia_fuori', __( 'Copia non tolta: la copia non e\' gia\' piu\' nello storico.', 'albo-pretorio-pa' ) );
		}

		if ( ! is_string( $causa ) || ! array_key_exists( $causa, self::cause() ) ) {
			return self::errore( 'albo_copia_senza_causa', __( 'Copia non tolta: va scelta una delle cause dell\'elenco.', 'albo-pretorio-pa' ) );
		}

		$motivo = is_string( $motivo ) ? trim( sanitize_textarea_field( $motivo ) ) : '';

		if ( '' === $motivo ) {
			return self::errore( 'albo_copia_senza_motivo', __( 'Copia non tolta: va scritto il motivo.', 'albo-pretorio-pa' ) );
		}

		$prima = conformita_core_fine_pubblicazione( $copia );
		$ieri  = Passaggi::ora()->setTimezone( wp_timezone() )->modify( '-1 day' )->format( 'Y-m-d' );

		$scritta = conformita_core_imposta_fine_pubblicazione( $copia, $ieri );

		if ( is_wp_error( $scritta ) ) {
			return self::errore( 'albo_copia_non_tolta', __( 'Copia non tolta: la fine della copia non si e\' scritta. Riprovare.', 'albo-pretorio-pa' ) );
		}

		$voce = conformita_core_registra_voce(
			array(
				'sezione'     => SEZIONE_STORICO,
				'azione'      => self::AZIONE_USCITA,
				'contenuto'   => $copia,
				'motivazione' => $motivo,
				'dettagli'    => array(
					'causa'           => $causa,
					'atto'            => (string) (int) get_post_meta( $copia, self::META_ATTO, true ),
					'fine_precedente' => is_string( $prima ) ? $prima : '',
					'fine_scritta'    => $ieri,
				),
			)
		);

		if ( is_wp_error( $voce ) ) {
			/*
			 * La copia resta fuori anche se il registro non scrive: fra una
			 * copia tolta senza voce e una copia rimessa in rete contro la
			 * volonta' di chi la toglie, si sceglie la prima, e lo si dice.
			 */
			return self::errore( 'albo_copia_tolta_senza_voce', __( 'La copia e\' stata tolta dallo storico, ma il registro non ha scritto la voce. Annotare a parte chi l\'ha tolta e perche\'.', 'albo-pretorio-pa' ) );
		}

		return true;
	}

	/**
	 * Le copie nello storico di un atto, non ancora finite, dalla piu' vecchia.
	 *
	 * Nulla se la lettura non risponde.
	 *
	 * @param int $atto Atto.
	 * @return array<int, int>|null
	 */
	public static function presenti_di( int $atto ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura di controllo, senza memoria: deve vedere le scritture di questa stessa richiesta.
		$righe = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.post_status = 'publish' AND m.meta_value = %s ORDER BY p.ID ASC",
				self::META_ATTO,
				TIPO_STORICO,
				(string) $atto
			)
		);

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return null;
		}

		clean_post_cache( 0 );

		$presenti = array();

		foreach ( $righe as $riga ) {
			if ( true !== conformita_core_scaduto( (int) $riga ) ) {
				$presenti[] = (int) $riga;
			}
		}

		return $presenti;
	}

	/**
	 * Le copie nello storico, non ancora finite, dalla piu' recente.
	 *
	 * @return array<int, int>
	 */
	public static function presenti(): array {
		$trovate = get_posts(
			array(
				'post_type'        => TIPO_STORICO,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		return array_values(
			array_filter(
				array_map( 'intval', $trovate ),
				static function ( int $copia ): bool {
					return true !== conformita_core_scaduto( $copia );
				}
			)
		);
	}

	/**
	 * I dati fissati di una copia, o nullo se non si leggono.
	 *
	 * @param int $copia Copia.
	 * @return array<string, string|int>|null
	 */
	public static function dati( int $copia ): ?array {
		$dati = get_post_meta( $copia, self::META_DATI, true );

		return is_array( $dati ) && isset( $dati['anno'], $dati['numero'] ) ? $dati : null;
	}

	/**
	 * Il documento di una copia, o nullo.
	 *
	 * @param int $copia Copia.
	 * @return int|null
	 */
	public static function documento( int $copia ): ?int {
		$documento = (int) get_post_meta( $copia, self::META_DOCUMENTO, true );

		if ( $documento < 1 || (int) wp_get_post_parent_id( $documento ) !== $copia ) {
			return null;
		}

		return $documento;
	}

	/**
	 * Il giorno in cui la copia e' entrata nello storico, o nullo.
	 *
	 * @param int $copia Copia.
	 * @return string|null
	 */
	public static function giorno_di_ingresso( int $copia ): ?string {
		$giorno = get_post_meta( $copia, self::META_INGRESSO, true );

		return is_string( $giorno ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $giorno ) ? $giorno : null;
	}

	/**
	 * I dati dell'atto che la copia porta con se', fissati all'ingresso.
	 *
	 * Soltanto i dati che identificano l'atto e non le persone: numero, date,
	 * tipo, organo, numero proprio, data di adozione, amministrazione che l'ha
	 * adottato. Mai l'oggetto originale, il richiedente, il responsabile o
	 * l'ufficio: l'oggetto della copia lo scrive chi la mette.
	 *
	 * @param int $atto Atto.
	 * @return array<string, string|int>
	 */
	private static function fotografia( int $atto ): array {
		$numero = Repertorio::di( $atto );
		$tipo   = DatiAtto::tipo( $atto );
		$organo = DatiAtto::organo( $atto );
		$fine   = conformita_core_fine_pubblicazione( $atto );

		return array(
			'anno'            => null === $numero ? 0 : (int) $numero['anno'],
			'numero'          => null === $numero ? 0 : (int) $numero['numero'],
			'inizio'          => substr( (string) get_post_field( 'post_date', $atto ), 0, 10 ),
			'fine'            => is_string( $fine ) ? $fine : '',
			'tipo'            => null === $tipo ? '' : (string) $tipo->name,
			'organo'          => null === $organo ? '' : (string) $organo->name,
			'numero_proprio'  => DatiAtto::numero_proprio( $atto ),
			'data_adozione'   => (string) DatiAtto::data_adozione( $atto ),
			'amministrazione' => (string) AmministrazioniOspitate::da_mostrare( $atto ),
		);
	}

	/**
	 * I controlli che valgono prima di guardare l'atto.
	 *
	 * @return true|\WP_Error
	 */
	private static function preliminare() {
		if ( ! Storico::pronto() ) {
			return self::errore( 'albo_copia_fermo', __( 'Copia non creata: lo storico pubblico non risulta registrato.', 'albo-pretorio-pa' ) );
		}

		if ( ! current_user_can( Permessi::PERMESSO_STORICO ) ) {
			return self::errore( 'albo_copia_non_permesso', __( 'Copia non creata: non si possiede il permesso di gestire lo storico pubblico.', 'albo-pretorio-pa' ) );
		}

		if ( ! Storico::acceso() || null === Storico::anni() ) {
			return self::errore( 'albo_copia_spento', __( 'Copia non creata: lo storico pubblico e\' spento. Si accende dalla sua schermata, dichiarando gli anni.', 'albo-pretorio-pa' ) );
		}

		return true;
	}

	/**
	 * Un errore con il suo codice.
	 *
	 * @param string $codice    Codice.
	 * @param string $messaggio Messaggio.
	 * @return \WP_Error
	 */
	private static function errore( string $codice, string $messaggio ): \WP_Error {
		return new \WP_Error( $codice, $messaggio );
	}
}
