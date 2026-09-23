<?php
/**
 * Il riquadro "Documenti dell'atto" nella schermata dell'atto.
 *
 * Mostra i documenti gia' depositati, con il nome, la dimensione e il
 * collegamento amministrativo del meccanismo comune, e finche' l'atto e' in
 * bozza permette di caricarne e di toglierne. I file arrivano con l'invio della
 * schermata dell'atto, che per questo dichiara l'invio di file.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Riquadro, invio e avviso dei rifiuti dei documenti.
 */
final class SchedaDocumenti {

	/**
	 * Identificativo del riquadro.
	 *
	 * @var string
	 */
	const RIQUADRO = 'albo-pretorio-documenti-atto';

	/**
	 * Radice dell'azione del gettone del riquadro.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_documenti_atto';

	/**
	 * Nome del campo del gettone: la sua presenza distingue un invio del riquadro.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_documenti_gettone';

	/**
	 * Campo file del documento principale.
	 *
	 * @var string
	 */
	const CAMPO_PRINCIPALE = 'albo_pretorio_documento_principale';

	/**
	 * Campo file di un allegato ulteriore.
	 *
	 * @var string
	 */
	const CAMPO_ULTERIORE = 'albo_pretorio_allegato_ulteriore';

	/**
	 * Campo dell'elenco dei documenti da togliere.
	 *
	 * @var string
	 */
	const CAMPO_TOGLI = 'albo_pretorio_togli_documenti';

	/**
	 * Per quanti secondi i motivi di un rifiuto aspettano la pagina successiva.
	 *
	 * @var int
	 */
	const DURATA_RIFIUTO = 300;

	/**
	 * L'azione del gettone per un atto: il gettone di un atto non vale per un altro.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	public static function azione( int $atto_id ): string {
		return self::AZIONE . '_' . $atto_id;
	}

	/**
	 * Aggancio a `add_meta_boxes_` del tipo: il riquadro.
	 */
	public static function riquadro(): void {
		add_meta_box(
			self::RIQUADRO,
			__( 'Documenti dell\'atto', 'albo-pretorio-pa' ),
			array( self::class, 'mostra' ),
			TIPO,
			'normal',
			'high'
		);
	}

	/**
	 * Aggancio a `post_edit_form_tag`: il modulo della schermata dell'atto porta i file.
	 *
	 * @param \WP_Post $atto Contenuto della schermata.
	 */
	public static function modulo( $atto ): void {
		if ( $atto instanceof \WP_Post && TIPO === $atto->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	/**
	 * Il contenuto del riquadro.
	 *
	 * **Il percorso del file non si stampa mai**: il collegamento e'
	 * l'indirizzo di consegna amministrativo, e il nome e' quello del documento.
	 *
	 * @param \WP_Post $atto Atto.
	 */
	public static function mostra( $atto ): void {
		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type || ! current_user_can( 'edit_post', $atto->ID ) ) {
			return;
		}

		$atto_id    = (int) $atto->ID;
		$in_bozza   = in_array( $atto->post_status, DocumentiAtto::STATI_MODIFICABILI, true );
		$principale = DocumentiAtto::principale( $atto_id );
		$ulteriori  = DocumentiAtto::ulteriori( $atto_id );

		wp_nonce_field( self::azione( $atto_id ), self::CAMPO_GETTONE );

		printf( '<h4>%s</h4>', esc_html__( 'Documento principale', 'albo-pretorio-pa' ) );

		if ( null === $principale ) {
			printf( '<p>%s</p>', esc_html__( 'Nessun documento principale: senza, l\'atto non si pubblica.', 'albo-pretorio-pa' ) );
		} else {
			echo '<ul class="albo-pretorio-documenti">';
			self::voce( $principale, $in_bozza );
			echo '</ul>';
		}

		if ( $in_bozza ) {
			self::campo_file(
				self::CAMPO_PRINCIPALE,
				null === $principale
					? __( 'Carica il documento principale', 'albo-pretorio-pa' )
					: __( 'Sostituisci il documento principale', 'albo-pretorio-pa' )
			);
		}

		printf( '<h4>%s</h4>', esc_html__( 'Allegati ulteriori', 'albo-pretorio-pa' ) );

		if ( array() === $ulteriori ) {
			printf( '<p>%s</p>', esc_html__( 'Nessun allegato ulteriore.', 'albo-pretorio-pa' ) );
		} else {
			echo '<ol class="albo-pretorio-documenti">';

			foreach ( $ulteriori as $ulteriore ) {
				self::voce( $ulteriore, $in_bozza );
			}

			echo '</ol>';
		}

		if ( $in_bozza ) {
			self::campo_file( self::CAMPO_ULTERIORE, __( 'Aggiungi un allegato ulteriore', 'albo-pretorio-pa' ) );
			printf( '<p class="description">%s</p>', esc_html__( 'Un file per salvataggio. I documenti si caricano e si tolgono salvando la bozza.', 'albo-pretorio-pa' ) );
		} else {
			printf( '<p class="description">%s</p>', esc_html__( 'I documenti si cambiano solo mentre l\'atto e\' in bozza.', 'albo-pretorio-pa' ) );
		}
	}

	/**
	 * Una riga dell'elenco: nome, dimensione, collegamento e, in bozza, la casella per toglierlo.
	 *
	 * @param int  $documento Documento.
	 * @param bool $in_bozza  L'atto e' in bozza.
	 */
	private static function voce( int $documento, bool $in_bozza ): void {
		$impronta  = conformita_core_impronta_allegato( $documento );
		$indirizzo = conformita_core_indirizzo_consegna_amministrativa( $documento );
		$campo     = self::CAMPO_TOGLI . '_' . $documento;

		printf(
			'<li>%1$s (%2$s)',
			esc_html( get_the_title( $documento ) ),
			esc_html( is_array( $impronta ) ? (string) size_format( (int) $impronta['dimensione'] ) : __( 'dimensione non disponibile', 'albo-pretorio-pa' ) )
		);

		if ( is_string( $indirizzo ) ) {
			printf( ' <a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $indirizzo ), esc_html__( 'Apri', 'albo-pretorio-pa' ) );
		}

		if ( $in_bozza ) {
			printf(
				' <label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[]" value="%3$d" /> %4$s</label>',
				esc_attr( $campo ),
				esc_attr( self::CAMPO_TOGLI ),
				(int) $documento,
				esc_html__( 'Togli', 'albo-pretorio-pa' )
			);
		}

		echo '</li>';
	}

	/**
	 * Un campo per scegliere un file.
	 *
	 * @param string $nome      Nome del campo.
	 * @param string $etichetta Etichetta.
	 */
	private static function campo_file( string $nome, string $etichetta ): void {
		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="file" id="%1$s" name="%1$s" /></p>',
			esc_attr( $nome ),
			esc_html( $etichetta )
		);
	}

	/**
	 * Aggancio a `save_post_` del tipo: toglie e deposita i documenti inviati dal riquadro.
	 *
	 * Prima si tolgono i documenti spuntati, poi si deposita il principale, poi
	 * l'ulteriore. Ogni operazione vale da sola: quella rifiutata e' nominata
	 * nell'avviso, le altre si fanno.
	 *
	 * @param int      $atto_id Atto.
	 * @param \WP_Post $atto    Atto appena scritto.
	 */
	public static function da_salvataggio( $atto_id, $atto ): void {
		$atto_id = (int) $atto_id;

		// Senza il gettone non e' un invio del riquadro: niente da fare.
		if ( ! isset( $_POST[ self::CAMPO_GETTONE ] ) ) {
			return;
		}

		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type || wp_is_post_revision( $atto_id ) || wp_is_post_autosave( $atto_id ) ) {
			return;
		}

		$gettone = sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) );

		if ( ! wp_verify_nonce( $gettone, self::azione( $atto_id ) ) ) {
			self::rifiuta( $atto_id, array( __( 'Documenti non modificati: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) ) );
			return;
		}

		if ( ! current_user_can( 'edit_post', $atto_id ) ) {
			self::rifiuta( $atto_id, array( __( 'Documenti non modificati: non si possiede il permesso di modificare questo atto.', 'albo-pretorio-pa' ) ) );
			return;
		}

		$motivi = array();

		foreach ( self::da_togliere() as $documento ) {
			$esito = DocumentiAtto::togli( $atto_id, $documento );

			if ( is_wp_error( $esito ) ) {
				$motivi[] = $esito->get_error_message();
			}
		}

		$ruoli = array(
			self::CAMPO_PRINCIPALE => DocumentiAtto::PRINCIPALE,
			self::CAMPO_ULTERIORE  => DocumentiAtto::ULTERIORE,
		);

		foreach ( $ruoli as $campo => $ruolo ) {
			$file = self::file( $campo );

			if ( null === $file ) {
				continue;
			}

			$esito = is_wp_error( $file ) ? $file : DocumentiAtto::deposita( $atto_id, $file, $ruolo, 'caricamento' );

			if ( is_wp_error( $esito ) ) {
				$motivi[] = $esito->get_error_message();
			}
		}

		if ( array() !== $motivi ) {
			self::rifiuta( $atto_id, $motivi );
		}
	}

	/**
	 * Gli identificativi spuntati da togliere.
	 *
	 * Un valore che non e' un identificativo si scarta: non indica nessun
	 * documento, e la rimozione vera controlla comunque che sia di quell'atto.
	 *
	 * @return array<int, int>
	 */
	private static function da_togliere(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il gettone e' verificato da chi chiama, prima di leggere qualunque campo.
		if ( ! isset( $_POST[ self::CAMPO_TOGLI ] ) || ! is_array( $_POST[ self::CAMPO_TOGLI ] ) ) {
			return array();
		}

		$identificativi = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- come sopra; ogni voce si valida qui sotto contro il formato stretto.
		foreach ( wp_unslash( $_POST[ self::CAMPO_TOGLI ] ) as $voce ) {
			if ( is_string( $voce ) && 1 === preg_match( '/\A[1-9][0-9]*\z/', $voce ) ) {
				$identificativi[] = (int) $voce;
			}
		}

		return array_values( array_unique( $identificativi ) );
	}

	/**
	 * Il file arrivato in un campo, nella forma di `$_FILES`.
	 *
	 * **Nessun controllo sull'origine qui**: il deposito si dichiara
	 * `caricamento`, e il meccanismo comune rifiuta un file che non e' arrivato
	 * con la richiesta HTTP. Qui si controlla solo la forma.
	 *
	 * @param string $nome Nome del campo.
	 * @return array<string, mixed>|\WP_Error|null Nullo se il campo e' assente o vuoto.
	 */
	private static function file( string $nome ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il gettone e' verificato da chi chiama.
		if ( ! isset( $_FILES[ $nome ] ) || ! is_array( $_FILES[ $nome ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- come sopra; la voce si controlla qui sotto e il file lo verifica il meccanismo comune.
		$file = $_FILES[ $nome ];

		$errore = isset( $file['error'] ) && is_int( $file['error'] ) ? $file['error'] : -1;

		if ( UPLOAD_ERR_NO_FILE === $errore ) {
			return null;
		}

		if ( UPLOAD_ERR_OK !== $errore || ! isset( $file['name'], $file['tmp_name'] ) || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) ) {
			return new \WP_Error(
				'albo_caricamento_non_riuscito',
				__( 'Documento non caricato: il file non e\' arrivato per intero, oppure supera la dimensione che il server accetta.', 'albo-pretorio-pa' )
			);
		}

		return array(
			'name'     => $file['name'],
			'tmp_name' => $file['tmp_name'],
			'type'     => isset( $file['type'] ) && is_string( $file['type'] ) ? $file['type'] : '',
			'size'     => isset( $file['size'] ) && is_int( $file['size'] ) ? $file['size'] : 0,
			'error'    => UPLOAD_ERR_OK,
		);
	}

	/**
	 * Conserva i motivi di un rifiuto per la pagina che segue il salvataggio.
	 *
	 * @param int                $atto_id Atto.
	 * @param array<int, string> $motivi  Motivi.
	 */
	private static function rifiuta( int $atto_id, array $motivi ): void {
		set_transient( self::chiave_rifiuto( $atto_id ), $motivi, self::DURATA_RIFIUTO );
	}

	/**
	 * Chiave del dato temporaneo, per utente e atto.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private static function chiave_rifiuto( int $atto_id ): string {
		return 'albo_pretorio_documenti_rifiuto_' . get_current_user_id() . '_' . $atto_id;
	}

	/**
	 * Preleva i motivi dell'ultimo rifiuto dell'utente su un atto, e li toglie.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, string>
	 */
	public static function preleva_rifiuto( int $atto_id ): array {
		$motivi = get_transient( self::chiave_rifiuto( $atto_id ) );

		if ( ! is_array( $motivi ) ) {
			return array();
		}

		delete_transient( self::chiave_rifiuto( $atto_id ) );

		return array_map( 'strval', $motivi );
	}

	/**
	 * Aggancio a `admin_notices`: i motivi nella schermata dell'atto.
	 */
	public static function mostra_rifiuto(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || 'post' !== $schermata->base || TIPO !== $schermata->post_type ) {
			return;
		}

		$atto = get_post();

		if ( ! $atto instanceof \WP_Post ) {
			return;
		}

		foreach ( self::preleva_rifiuto( (int) $atto->ID ) as $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}
	}
}
