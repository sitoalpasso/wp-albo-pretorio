<?php
/**
 * I documenti dell'atto: il principale e gli allegati ulteriori.
 *
 * I file non li scrive questo componente: li deposita il meccanismo comune,
 * nella sua cartella protetta, dopo aver verificato che il server neghi
 * l'accesso diretto. Qui si decide soltanto quale allegato ha quale ruolo,
 * quando si puo' cambiare, e chi puo' toccarlo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Deposito, sostituzione, rimozione e lettura validata dei documenti.
 */
final class DocumentiAtto {

	/**
	 * Ruolo del documento principale.
	 *
	 * @var string
	 */
	const PRINCIPALE = 'principale';

	/**
	 * Ruolo di un allegato ulteriore.
	 *
	 * @var string
	 */
	const ULTERIORE = 'ulteriore';

	/**
	 * Stati in cui i documenti si depositano e si tolgono.
	 *
	 * Solo la bozza: un atto in revisione e' gia' stato sottoposto con quei
	 * documenti, e uno pubblicato li ha gia' esposti.
	 *
	 * @var array<int, string>
	 */
	const STATI_MODIFICABILI = array( 'draft', 'auto-draft' );

	/**
	 * Stati di un atto mai pubblicato, i cui documenti non sono mai stati esposti.
	 *
	 * @var array<int, string>
	 */
	const STATI_MAI_ESPOSTI = array( 'draft', 'auto-draft', 'pending' );

	/**
	 * Variabile di interrogazione che toglie i documenti degli atti da un elenco di allegati.
	 *
	 * @var string
	 */
	const VAR_ESCLUDI = 'albo_pretorio_escludi_documenti';

	/**
	 * Aggancio a `init`: i due dati sull'atto.
	 */
	public static function da_init(): void {
		self::registra();
	}

	/**
	 * Registra i due dati sull'atto, fuori dall'interfaccia REST.
	 *
	 * @return bool
	 */
	public static function registra(): bool {
		if ( ! TipoAtto::registrazione_completa() ) {
			return false;
		}

		register_post_meta(
			TIPO,
			META_DOCUMENTO_PRINCIPALE,
			array(
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => false,
			)
		);

		register_post_meta(
			TIPO,
			META_ALLEGATI_ULTERIORI,
			array(
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => false,
			)
		);

		return true;
	}

	/**
	 * Deposita un documento su un atto in bozza.
	 *
	 * **Il principale si sostituisce, e il vecchio sparisce del tutto**, file
	 * compreso: una bozza non e' mai stata esposta, e un documento sostituito
	 * che restasse sarebbe un file con dati personali che nessuna schermata
	 * mostra piu'. Gli ulteriori si aggiungono in fondo all'elenco.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $file    Voce nella forma di `$_FILES`.
	 * @param string               $ruolo   `principale` oppure `ulteriore`.
	 * @param string               $origine `caricamento` oppure `percorso_locale`, come per il meccanismo comune.
	 * @return int|\WP_Error Identificativo dell'allegato, oppure errore.
	 */
	public static function deposita( int $atto_id, array $file, string $ruolo, string $origine ) {
		$controllo = self::modificabile( $atto_id );

		if ( is_wp_error( $controllo ) ) {
			return $controllo;
		}

		if ( self::PRINCIPALE !== $ruolo && self::ULTERIORE !== $ruolo ) {
			return new \WP_Error(
				'albo_ruolo_documento_sconosciuto',
				__( 'Documento non depositato: il ruolo indicato non e\' ne\' documento principale ne\' allegato ulteriore.', 'albo-pretorio-pa' )
			);
		}

		$allegato = conformita_core_deposita_allegato( $atto_id, $file, array( 'origine' => $origine ) );

		if ( is_wp_error( $allegato ) ) {
			return $allegato;
		}

		$allegato = (int) $allegato;

		if ( self::PRINCIPALE === $ruolo ) {
			$vecchio = self::principale( $atto_id );

			update_post_meta( $atto_id, META_DOCUMENTO_PRINCIPALE, $allegato );

			// Si cancella solo un documento di questo atto: il dato memorizzato non si crede.
			if ( null !== $vecchio ) {
				wp_delete_attachment( $vecchio, true );
			}
		} else {
			$ulteriori   = self::ulteriori( $atto_id );
			$ulteriori[] = $allegato;

			update_post_meta( $atto_id, META_ALLEGATI_ULTERIORI, $ulteriori );
		}

		return $allegato;
	}

	/**
	 * Toglie un documento da un atto in bozza, e lo cancella con il suo file.
	 *
	 * @param int $atto_id     Atto.
	 * @param int $allegato_id Documento da togliere.
	 * @return true|\WP_Error
	 */
	public static function togli( int $atto_id, int $allegato_id ) {
		$controllo = self::modificabile( $atto_id );

		if ( is_wp_error( $controllo ) ) {
			return $controllo;
		}

		$ulteriori = self::ulteriori( $atto_id );

		if ( self::principale( $atto_id ) === $allegato_id ) {
			delete_post_meta( $atto_id, META_DOCUMENTO_PRINCIPALE );
		} elseif ( in_array( $allegato_id, $ulteriori, true ) ) {
			update_post_meta( $atto_id, META_ALLEGATI_ULTERIORI, array_values( array_diff( $ulteriori, array( $allegato_id ) ) ) );
		} else {
			return new \WP_Error(
				'albo_documento_non_dell_atto',
				__( 'Documento non tolto: non e\' un documento di questo atto.', 'albo-pretorio-pa' )
			);
		}

		wp_delete_attachment( $allegato_id, true );

		return true;
	}

	/**
	 * L'atto esiste ed e' in bozza.
	 *
	 * @param int $atto_id Atto.
	 * @return true|\WP_Error
	 */
	private static function modificabile( int $atto_id ) {
		$atto = get_post( $atto_id );

		if ( ! TipoAtto::tipo_nostro() || ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return new \WP_Error(
				'albo_non_un_atto',
				__( 'Documento non depositato: il contenuto indicato non e\' un atto.', 'albo-pretorio-pa' )
			);
		}

		if ( ! in_array( $atto->post_status, self::STATI_MODIFICABILI, true ) ) {
			return new \WP_Error(
				'albo_documenti_non_in_bozza',
				__( 'Documenti non modificati: si caricano e si tolgono solo mentre l\'atto e\' in bozza.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Il documento principale dell'atto, o nullo.
	 *
	 * **La lettura valida il dato letto**: vale solo una riga, che indica un
	 * documento valido di questo atto.
	 *
	 * @param int $atto_id Atto.
	 * @return int|null
	 */
	public static function principale( int $atto_id ): ?int {
		$righe = get_post_meta( $atto_id, META_DOCUMENTO_PRINCIPALE, false );

		if ( ! is_array( $righe ) || 1 !== count( $righe ) ) {
			return null;
		}

		$id = self::identificativo( $righe[0] );

		return null !== $id && self::valido( $atto_id, $id ) ? $id : null;
	}

	/**
	 * Gli allegati ulteriori dell'atto, nell'ordine del deposito.
	 *
	 * Dell'elenco memorizzato si tengono solo i documenti validi di questo
	 * atto, ciascuno una volta sola e mai il principale.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, int>
	 */
	public static function ulteriori( int $atto_id ): array {
		$righe = get_post_meta( $atto_id, META_ALLEGATI_ULTERIORI, false );

		if ( ! is_array( $righe ) || 1 !== count( $righe ) || ! is_array( $righe[0] ) ) {
			return array();
		}

		$principale = self::principale( $atto_id );
		$validi     = array();

		foreach ( $righe[0] as $voce ) {
			$id = self::identificativo( $voce );

			if ( null === $id || $id === $principale || in_array( $id, $validi, true ) || ! self::valido( $atto_id, $id ) ) {
				continue;
			}

			$validi[] = $id;
		}

		return $validi;
	}

	/**
	 * Un identificativo positivo letto dalla banca dati, o nullo.
	 *
	 * @param mixed $valore Valore memorizzato.
	 * @return int|null
	 */
	private static function identificativo( $valore ): ?int {
		if ( is_int( $valore ) ) {
			return $valore > 0 ? $valore : null;
		}

		if ( is_string( $valore ) && 1 === preg_match( '/\A[1-9][0-9]*\z/', $valore ) ) {
			return (int) $valore;
		}

		return null;
	}

	/**
	 * Un allegato e' un documento valido dell'atto.
	 *
	 * Esiste, e' figlio di quell'atto, non e' nel cestino e l'ha depositato il
	 * meccanismo comune: un allegato della libreria normale ha un indirizzo
	 * diretto che nessuna scadenza chiude.
	 *
	 * @param int $atto_id     Atto.
	 * @param int $allegato_id Allegato.
	 * @return bool
	 */
	public static function valido( int $atto_id, int $allegato_id ): bool {
		if ( ! TipoAtto::tipo_nostro() ) {
			return false;
		}

		$allegato = get_post( $allegato_id );

		return $allegato instanceof \WP_Post
			&& 'attachment' === $allegato->post_type
			&& 'inherit' === $allegato->post_status
			&& $atto_id === (int) $allegato->post_parent
			&& conformita_core_allegato_protetto( $allegato_id );
	}

	/**
	 * Aggancio a `map_meta_cap`: i documenti di un atto si governano con i permessi dell'atto.
	 *
	 * Senza, un allegato si governa con i permessi della libreria dei media, e
	 * chi modifica gli articoli del sito potrebbe cancellare il documento di una
	 * delibera. **Cancellarlo non e' concesso a nessuno** per questa via: un
	 * documento si toglie dal riquadro dell'atto, che controlla lo stato
	 * dell'atto e aggiorna i suoi dati. Le funzioni interne che cancellano non
	 * passano da qui.
	 *
	 * @param array<int, string> $permessi  Permessi primitivi calcolati.
	 * @param string             $permesso  Permesso chiesto.
	 * @param int                $utente_id Utente.
	 * @param array<int, mixed>  $argomenti Argomenti del permesso.
	 * @return array<int, string>
	 */
	public static function da_map_meta_cap( $permessi, $permesso, $utente_id, $argomenti ) {
		if ( ! in_array( $permesso, array( 'edit_post', 'delete_post', 'read_post' ), true ) || ! isset( $argomenti[0] ) ) {
			return $permessi;
		}

		$allegato = get_post( (int) $argomenti[0] );

		if ( ! $allegato instanceof \WP_Post || 'attachment' !== $allegato->post_type || $allegato->post_parent <= 0 ) {
			return $permessi;
		}

		// Il tipo di un altro componente con lo stesso nome non si governa.
		if ( TIPO !== get_post_type( (int) $allegato->post_parent ) || ! TipoAtto::tipo_nostro() ) {
			return $permessi;
		}

		if ( 'delete_post' === $permesso ) {
			return array( 'do_not_allow' );
		}

		return map_meta_cap( $permesso, (int) $utente_id, (int) $allegato->post_parent );
	}

	/**
	 * Aggancio a `ajax_query_attachments_args`: la libreria a griglia non elenca i documenti degli atti.
	 *
	 * @param array<string, mixed> $argomenti Argomenti dell'interrogazione.
	 * @return array<string, mixed>
	 */
	public static function da_ajax_query_attachments_args( $argomenti ) {
		$argomenti                      = is_array( $argomenti ) ? $argomenti : array();
		$argomenti[ self::VAR_ESCLUDI ] = true;

		return $argomenti;
	}

	/**
	 * Aggancio a `pre_get_posts`: la libreria a elenco non elenca i documenti degli atti.
	 *
	 * @param \WP_Query $interrogazione Interrogazione.
	 */
	public static function da_pre_get_posts( $interrogazione ): void {
		if ( ! $interrogazione instanceof \WP_Query || ! is_admin() || ! $interrogazione->is_main_query() ) {
			return;
		}

		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null !== $schermata && 'upload' === $schermata->base ) {
			$interrogazione->set( self::VAR_ESCLUDI, true );
		}
	}

	/**
	 * Aggancio a `posts_where`: toglie gli allegati figli di un atto.
	 *
	 * @param string    $condizione     Condizione SQL.
	 * @param \WP_Query $interrogazione Interrogazione.
	 * @return string
	 */
	public static function da_posts_where( $condizione, $interrogazione ) {
		if ( ! $interrogazione instanceof \WP_Query || true !== $interrogazione->get( self::VAR_ESCLUDI ) || ! TipoAtto::tipo_nostro() ) {
			return $condizione;
		}

		global $wpdb;

		return $condizione . $wpdb->prepare(
			" AND {$wpdb->posts}.post_parent NOT IN ( SELECT atti.ID FROM {$wpdb->posts} AS atti WHERE atti.post_type = %s )",
			TIPO
		);
	}

	/**
	 * Aggancio a `before_delete_post`: un atto mai pubblicato porta via i suoi documenti.
	 *
	 * Senza, WordPress staccherebbe gli allegati dall'atto e li lascerebbe sul
	 * disco: documenti con dati personali che nessuna schermata mostra piu'. Un
	 * atto nel cestino vale per lo stato che aveva prima.
	 *
	 * @param int      $atto_id Contenuto che sta per essere cancellato.
	 * @param \WP_Post $atto    Contenuto.
	 */
	public static function da_before_delete_post( $atto_id, $atto = null ): void {
		$atto = $atto instanceof \WP_Post ? $atto : get_post( (int) $atto_id );

		if ( ! TipoAtto::tipo_nostro() || ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return;
		}

		$stato = 'trash' === $atto->post_status
			? (string) get_post_meta( (int) $atto->ID, '_wp_trash_meta_status', true )
			: $atto->post_status;

		if ( ! in_array( $stato, self::STATI_MAI_ESPOSTI, true ) ) {
			return;
		}

		/*
		 * Si cercano i figli depositati dal meccanismo comune, non i due dati
		 * dell'atto: un documento che i dati non indicano piu', per un guasto o
		 * per una scrittura di lato, resterebbe altrimenti sul disco. Un
		 * allegato della libreria normale non si tocca: il suo file puo' essere
		 * usato altrove.
		 */
		$figli = get_children(
			array(
				'post_parent' => (int) $atto->ID,
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		);

		foreach ( is_array( $figli ) ? $figli : array() as $figlio ) {
			if ( conformita_core_allegato_protetto( (int) $figlio ) ) {
				wp_delete_attachment( (int) $figlio, true );
			}
		}
	}

	/**
	 * Toglie la registrazione dei due dati.
	 *
	 * @internal Solo per le prove.
	 */
	public static function azzera(): void {
		unregister_post_meta( TIPO, META_DOCUMENTO_PRINCIPALE );
		unregister_post_meta( TIPO, META_ALLEGATI_ULTERIORI );
	}
}
