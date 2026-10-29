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
	 * Secondi di attesa del blocco di un atto o di una copia.
	 *
	 * @var int
	 */
	const ATTESA_BLOCCO = 5;

	/**
	 * Un ingresso o un'uscita e' in corso in questa richiesta.
	 *
	 * @var bool
	 */
	private static $in_corso = false;

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
	 * Il controllo dell'atto rifatto sulla banca dati, senza la memoria.
	 *
	 * Un'altra richiesta puo' aver annullato o prorogato l'atto dopo il primo
	 * controllo: la memoria di questa richiesta non lo sa.
	 *
	 * @param int $atto Atto.
	 * @return true|\WP_Error
	 */
	private static function ancora_ammissibile( int $atto ) {
		clean_post_cache( $atto );

		return self::ammissibile( $atto );
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

		return self::in_fila(
			self::nome_blocco_atto( $atto ),
			static function () use ( $atto, $file, $oggetto, $conferma, $origine ) {
				return self::metti_in_fila( $atto, $file, $oggetto, $conferma, $origine );
			}
		);
	}

	/**
	 * L'ingresso, con il blocco dell'atto preso: dal controllo delle copie presenti al registro.
	 *
	 * @param int                  $atto     Atto.
	 * @param array<string, mixed> $file     Documento oscurato.
	 * @param mixed                $oggetto  Oggetto della copia.
	 * @param mixed                $conferma Conferma del controllo.
	 * @param string               $origine  Origine del documento.
	 * @return int|\WP_Error
	 */
	private static function metti_in_fila( int $atto, array $file, $oggetto, $conferma, string $origine ) {
		$ammesso = self::ancora_ammissibile( $atto );

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

		/*
		 * L'impostazione e i dati dell'atto si rileggono qui, con il blocco
		 * preso: lo storico puo' essersi spento mentre l'ingresso aspettava,
		 * e una lettura che non risponde non diventa zero anni o un atto 0/0.
		 */
		$impostazione = Storico::fotografia();

		if ( Storico::VALIDA !== $impostazione['esito'] || null === $impostazione['impostazione'] || Storico::ACCESO !== $impostazione['impostazione']['scelta'] || ! is_int( $impostazione['impostazione']['anni'] ) || $impostazione['impostazione']['anni'] < 1 ) {
			return self::errore( 'albo_copia_spento', __( 'Copia non creata: lo storico pubblico e\' spento, oppure la sua impostazione non si legge in questo momento.', 'albo-pretorio-pa' ) );
		}

		$dati = self::fotografia( $atto );

		if ( null === $dati ) {
			return self::errore( 'albo_copia_lettura', __( 'Copia non creata: numero di registro o fine della pubblicazione dell\'atto non si leggono in questo momento. Riprovare.', 'albo-pretorio-pa' ) );
		}

		$anni     = $impostazione['impostazione']['anni'];
		$oggi     = Passaggi::ora()->setTimezone( wp_timezone() );
		$fine     = self::fine( $oggi, $anni );
		$ingresso = $oggi->format( 'Y-m-d' );

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
			return self::ritira( $copia, $documento, new \WP_Error( $codice, $messaggio ) );
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

		/*
		 * L'annullamento e la proroga non passano dal blocco dell'atto: se uno
		 * dei due e' arrivato durante l'ingresso, l'atto non e' piu' quello
		 * controllato, e la copia si ritira prima del registro.
		 */
		$ammesso = self::ancora_ammissibile( $atto );

		if ( is_wp_error( $ammesso ) ) {
			return $fallita( $ammesso->get_error_code(), $ammesso->get_error_message() . ' ' . __( 'L\'atto e\' cambiato mentre la copia entrava, e la copia e\' stata ritirata.', 'albo-pretorio-pa' ), $documento );
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

		return self::in_fila(
			self::nome_blocco_copia( $copia ),
			static function () use ( $copia, $causa, $motivo ) {
				return self::togli_in_fila( $copia, $causa, $motivo );
			}
		);
	}

	/**
	 * L'uscita, con il blocco della copia preso: dal controllo al registro.
	 *
	 * @param int   $copia  Copia.
	 * @param mixed $causa  Causa.
	 * @param mixed $motivo Motivo.
	 * @return true|\WP_Error
	 */
	private static function togli_in_fila( int $copia, $causa, $motivo ) {
		clean_post_cache( $copia );

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
	 * Nulla se la lettura non risponde: un elenco vuoto direbbe "nessuna
	 * copia" quando non si sa.
	 *
	 * @return array<int, int>|null
	 */
	public static function presenti(): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lettura che distingue l'errore dall'assenza.
		$righe = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID DESC", TIPO_STORICO ) );

		if ( ! is_array( $righe ) || '' !== $wpdb->last_error ) {
			return null;
		}

		$presenti = array();

		foreach ( $righe as $riga ) {
			if ( true !== conformita_core_scaduto( (int) $riga ) ) {
				$presenti[] = (int) $riga;
			}
		}

		return $presenti;
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
	 * Nulla se numero, anno o fine non si leggono: senza i dati che
	 * identificano l'atto la copia non entra.
	 *
	 * @param int $atto Atto.
	 * @return array<string, string|int>|null
	 */
	private static function fotografia( int $atto ): ?array {
		$numero = Repertorio::di( $atto );
		$fine   = conformita_core_fine_pubblicazione( $atto );

		if ( null === $numero || ! isset( $numero['anno'], $numero['numero'] ) || (int) $numero['anno'] < 1 || (int) $numero['numero'] < 1 || ! is_string( $fine ) || '' === $fine ) {
			return null;
		}

		$tipo   = DatiAtto::tipo( $atto );
		$organo = DatiAtto::organo( $atto );

		return array(
			'anno'            => (int) $numero['anno'],
			'numero'          => (int) $numero['numero'],
			'inizio'          => substr( (string) get_post_field( 'post_date', $atto ), 0, 10 ),
			'fine'            => $fine,
			'tipo'            => null === $tipo ? '' : (string) $tipo->name,
			'organo'          => null === $organo ? '' : (string) $organo->name,
			'numero_proprio'  => DatiAtto::numero_proprio( $atto ),
			'data_adozione'   => (string) DatiAtto::data_adozione( $atto ),
			'amministrazione' => (string) AmministrazioniOspitate::da_mostrare( $atto ),
		);
	}

	/**
	 * La fine di una copia che entra il giorno indicato: lo stesso giorno, tanti anni dopo.
	 *
	 * Il 29 febbraio, in un anno che non lo ha, diventa il 28: la lettura
	 * prudente, che non aggiunge un giorno di diffusione.
	 *
	 * @param \DateTimeImmutable $oggi Giorno d'ingresso, nel fuso del sito.
	 * @param int                $anni Anni.
	 * @return string
	 */
	public static function fine( \DateTimeImmutable $oggi, int $anni ): string {
		$anno   = (int) $oggi->format( 'Y' ) + $anni;
		$mese   = (int) $oggi->format( 'n' );
		$primo  = $oggi->setDate( $anno, $mese, 1 );
		$giorno = min( (int) $oggi->format( 'j' ), (int) $primo->format( 't' ) );

		return $primo->setDate( $anno, $mese, $giorno )->format( 'Y-m-d' );
	}

	/**
	 * Esegue un ingresso o un'uscita con il blocco indicato preso, uno alla volta.
	 *
	 * Il blocco della banca dati vale per la connessione, e la stessa
	 * connessione lo riprende senza attendere: un ingresso o un'uscita
	 * chiamati da un aggancio di un altro passerebbero. Si rifiutano qui.
	 *
	 * @param string   $blocco   Nome del blocco.
	 * @param callable $funzione Il lavoro.
	 * @return mixed
	 */
	private static function in_fila( string $blocco, callable $funzione ) {
		global $wpdb;

		if ( self::$in_corso ) {
			return self::occupato();
		}

		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $blocco, self::ATTESA_BLOCCO ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- blocco della fila, non dati.
			return self::occupato();
		}

		self::$in_corso = true;

		try {
			return $funzione();
		} finally {
			self::$in_corso = false;
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $blocco ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- blocco della fila, non dati.
		}
	}

	/**
	 * Il nome del blocco degli ingressi di un atto.
	 *
	 * @param int $atto Atto.
	 * @return string
	 */
	public static function nome_blocco_atto( int $atto ): string {
		global $wpdb;

		return substr( $wpdb->prefix, 0, 40 ) . 'albo_copia_' . $atto;
	}

	/**
	 * Il nome del blocco delle uscite di una copia.
	 *
	 * @param int $copia Copia.
	 * @return string
	 */
	public static function nome_blocco_copia( int $copia ): string {
		global $wpdb;

		return substr( $wpdb->prefix, 0, 40 ) . 'albo_uscita_' . $copia;
	}

	/**
	 * Il rifiuto quando un altro ingresso o un'altra uscita sono in corso.
	 *
	 * @return \WP_Error
	 */
	private static function occupato(): \WP_Error {
		return self::errore( 'albo_copia_occupato', __( 'Niente fatto: sulla stessa copia o sullo stesso atto e\' in corso un\'altra operazione dello storico. Riprovare fra poco.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Ritira una copia non riuscita, e controlla che non ne resti niente.
	 *
	 * Si cancellano documento e copia, poi si rilegge dalla banca dati e dal
	 * disco. Se qualcosa resta, la copia si mette fuori con la fine al giorno
	 * prima, e il rifiuto lo dice invece di dire "ritirata".
	 *
	 * @param int       $copia     Copia.
	 * @param int|null  $documento Documento depositato, o nullo.
	 * @param \WP_Error $errore    Il rifiuto.
	 * @return \WP_Error
	 */
	private static function ritira( int $copia, ?int $documento, \WP_Error $errore ): \WP_Error {
		global $wpdb;

		$documenti = array_map(
			static function ( $resto ): int {
				return (int) $resto->ID;
			},
			get_attached_media( '', $copia )
		);

		if ( null !== $documento ) {
			$documenti[] = $documento;
		}

		$documenti = array_values( array_unique( $documenti ) );
		$file      = array();

		foreach ( $documenti as $allegato ) {
			$percorso = get_attached_file( $allegato, true );

			if ( is_string( $percorso ) && '' !== $percorso ) {
				$file[] = $percorso;
			}

			wp_delete_attachment( $allegato, true );
		}

		wp_delete_post( $copia, true );
		clearstatcache();

		$righe     = array_merge( array( $copia ), $documenti );
		$segni     = implode( ', ', array_fill( 0, count( $righe ), '%d' ) );
		$restano   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID IN ( {$segni} )", $righe ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- rilettura di controllo; i segnaposto sono costruiti qui sopra, uno per riga.
		$letto     = is_array( $restano ) && '' === $wpdb->last_error;
		$sul_disco = array_filter( $file, 'file_exists' );

		if ( $letto && array() === $restano && array() === $sul_disco ) {
			return $errore;
		}

		$ieri  = Passaggi::ora()->setTimezone( wp_timezone() )->modify( '-1 day' )->format( 'Y-m-d' );
		$fuori = ( $letto && ! in_array( (string) $copia, array_map( 'strval', $restano ), true ) ) || true === conformita_core_imposta_fine_pubblicazione( $copia, $ieri );

		return new \WP_Error(
			'albo_copia_non_ritirata',
			$errore->get_error_message() . ' ' . ( $fuori
				? __( 'La copia non si e\' cancellata del tutto: quello che resta e\' fuori dallo storico, non visibile. Avvisare chi gestisce il sito perche\' lo tolga.', 'albo-pretorio-pa' )
				: __( 'Attenzione: la copia non si e\' cancellata e non e\' stato possibile metterla fuori dallo storico. Toglierla subito dall\'elenco delle copie e avvisare chi gestisce il sito.', 'albo-pretorio-pa' ) )
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
