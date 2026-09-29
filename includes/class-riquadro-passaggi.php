<?php
/**
 * Il riquadro Pubblicazione nella schermata dell'atto.
 *
 * Su un atto in verifica, e solo per chi puo' pubblicarlo, porta i due
 * passaggi che partono da li': la pubblicazione, con il controllo sui dati
 * personali da confermare (ALBO-11), e il rimando in bozza, con il motivo. Su
 * un atto pubblicato mostra numero, inizio e fine, in sola lettura.
 *
 * **I pulsanti stanno nel modulo della schermata**, perche' un riquadro non
 * puo' avere un modulo suo. L'invio si intercetta prima che WordPress lo tratti
 * come un salvataggio: se porta il pulsante di un passaggio, lo compie questa
 * classe con `Passaggi` e torna alla schermata, e il salvataggio non avviene.
 * Dal resto del modulo si legge soltanto la data della casella di
 * pubblicazione di WordPress, e solo se chi pubblica l'ha cambiata: e' una
 * richiesta di data di inizio, e va rifiutata se e' nel futuro.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Riquadro, invio ed esito dei due passaggi.
 */
final class RiquadroPassaggi {

	/**
	 * Identificativo del riquadro.
	 *
	 * @var string
	 */
	const RIQUADRO = 'albo-pretorio-pubblicazione';

	/**
	 * Azione del gettone, completata dall'atto.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_passaggi';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_passaggi_gettone';

	/**
	 * Nome dei due pulsanti: il valore dice quale passaggio.
	 *
	 * @var string
	 */
	const CAMPO_PASSAGGIO = 'albo_pretorio_passaggio';

	/**
	 * Nome della casella di conferma: il valore e' l'atto a cui si riferisce.
	 *
	 * @var string
	 */
	const CAMPO_CONFERMA = 'albo_pretorio_conferma_dati_personali';

	/**
	 * Nome del campo del motivo del rimando.
	 *
	 * @var string
	 */
	const CAMPO_MOTIVO = 'albo_pretorio_motivo_rimando';

	/**
	 * Parametro dell'indirizzo di ritorno con l'esito riuscito.
	 *
	 * @var string
	 */
	const ESITO = 'albo-passaggio';

	/**
	 * L'azione del gettone per un atto.
	 *
	 * Legata all'atto: il gettone preso dalla schermata di un atto non vale
	 * per un altro.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	public static function azione( int $atto_id ): string {
		return self::AZIONE . '_' . $atto_id;
	}

	/**
	 * I casi tipici in cui un atto rivela dati delicati senza nominarli (ALBO-11).
	 *
	 * @return array<int, string>
	 */
	public static function casi_di_rivelazione_indiretta(): array {
		return array(
			__( 'richiami a norme sul collocamento mirato delle persone con disabilita\'', 'albo-pretorio-pa' ),
			__( 'congedi per assistenza a familiari', 'albo-pretorio-pa' ),
			__( 'graduatorie con punteggi legati a condizioni sociali o familiari', 'albo-pretorio-pa' ),
			__( 'provvedimenti disciplinari', 'albo-pretorio-pa' ),
			__( 'redditi e ISEE', 'albo-pretorio-pa' ),
		);
	}

	/**
	 * Aggancio a `add_meta_boxes_` del tipo: il riquadro, dove serve.
	 *
	 * @param \WP_Post|null $atto Atto della schermata.
	 */
	public static function riquadro( $atto = null ): void {
		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return;
		}

		$in_verifica = ChiusuraPubblicazione::IN_VERIFICA === $atto->post_status && current_user_can( 'publish_post', $atto->ID );

		if ( ! $in_verifica && ChiusuraPubblicazione::PUBBLICATO !== $atto->post_status ) {
			return;
		}

		add_meta_box(
			self::RIQUADRO,
			__( 'Pubblicazione', 'albo-pretorio-pa' ),
			array( self::class, 'mostra' ),
			TIPO,
			'normal',
			'default'
		);
	}

	/**
	 * Il contenuto del riquadro.
	 *
	 * @param \WP_Post $atto Atto.
	 */
	public static function mostra( $atto ): void {
		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return;
		}

		if ( ChiusuraPubblicazione::PUBBLICATO === $atto->post_status ) {
			self::pubblicato( $atto );
			return;
		}

		if ( ChiusuraPubblicazione::IN_VERIFICA !== $atto->post_status || ! current_user_can( 'publish_post', $atto->ID ) ) {
			return;
		}

		$atto_id = (int) $atto->ID;

		wp_nonce_field( self::azione( $atto_id ), self::CAMPO_GETTONE );

		printf( '<fieldset class="albo-pretorio-passaggio"><legend><strong>%s</strong></legend>', esc_html__( 'Pubblica', 'albo-pretorio-pa' ) );
		printf( '<p>%s</p><ul class="albo-pretorio-casi">', esc_html__( 'Prima di pubblicare, controllare che l\'atto non riveli dati personali delicati, anche senza nominarli. I casi tipici sono:', 'albo-pretorio-pa' ) );

		foreach ( self::casi_di_rivelazione_indiretta() as $caso ) {
			printf( '<li>%s</li>', esc_html( $caso ) );
		}

		echo '</ul>';

		printf(
			'<p>%s</p>',
			esc_html__( 'I dati genetici, biometrici e relativi alla salute non si diffondono mai (art. 2-septies, comma 8, del d.lgs. 196/2003): un atto che li contiene non si pubblica cosi\' com\'e\'.', 'albo-pretorio-pa' )
		);

		printf(
			'<p><input type="checkbox" id="%1$s" name="%1$s" value="%2$d" /> <label for="%1$s">%3$s</label></p>',
			esc_attr( self::CAMPO_CONFERMA ),
			(int) $atto_id,
			esc_html__( 'Ho controllato i dati personali che l\'atto contiene o lascia capire, e confermo che si puo\' pubblicare.', 'albo-pretorio-pa' )
		);

		printf( '<p class="description">%s</p>', esc_html( self::annuncio_date( $atto_id ) ) );

		printf(
			'<p><button type="submit" class="button button-primary" name="%1$s" value="pubblica">%2$s</button></p></fieldset>',
			esc_attr( self::CAMPO_PASSAGGIO ),
			esc_html__( 'Pubblica l\'atto', 'albo-pretorio-pa' )
		);

		printf( '<fieldset class="albo-pretorio-passaggio"><legend><strong>%s</strong></legend>', esc_html__( 'Rimanda in bozza', 'albo-pretorio-pa' ) );

		printf(
			'<p><label for="%1$s">%2$s</label><br /><textarea id="%1$s" name="%1$s" rows="3" class="large-text"></textarea></p>',
			esc_attr( self::CAMPO_MOTIVO ),
			esc_html__( 'Motivo del rimando', 'albo-pretorio-pa' )
		);

		printf( '<p class="description">%s</p>', esc_html__( 'Obbligatorio. Il motivo finisce nel registro delle modifiche, e chi redige lo legge per correggere l\'atto.', 'albo-pretorio-pa' ) );

		printf(
			'<p><button type="submit" class="button" name="%1$s" value="rimanda">%2$s</button></p></fieldset>',
			esc_attr( self::CAMPO_PASSAGGIO ),
			esc_html__( 'Rimanda in bozza', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Che cosa scrivera' il sistema alla pubblicazione.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private static function annuncio_date( int $atto_id ): string {
		$tipo   = DatiAtto::tipo( $atto_id );
		$durata = null === $tipo ? null : Durate::del_tipo( (int) $tipo->term_id );

		if ( ! is_array( $durata ) ) {
			return __( 'Il tipo di atto non ha una durata valida: la data di fine non si puo\' calcolare e l\'atto non si pubblica.', 'albo-pretorio-pa' );
		}

		return sprintf(
			/* translators: %d: giorni di pubblicazione. */
			_n(
				'Alla pubblicazione il sistema assegna il numero di repertorio e scrive la data di inizio, cioe\' adesso, e la data di fine, %d giorno dopo il giorno di inizio.',
				'Alla pubblicazione il sistema assegna il numero di repertorio e scrive la data di inizio, cioe\' adesso, e la data di fine, %d giorni dopo il giorno di inizio.',
				(int) $durata['giorni'],
				'albo-pretorio-pa'
			),
			(int) $durata['giorni']
		);
	}

	/**
	 * Numero, inizio e fine di un atto pubblicato.
	 *
	 * @param \WP_Post $atto Atto.
	 */
	private static function pubblicato( \WP_Post $atto ): void {
		$assente = __( 'non risulta', 'albo-pretorio-pa' );
		$numero  = Repertorio::di( (int) $atto->ID );
		$inizio  = get_post_datetime( $atto, 'date', 'gmt' );
		$fine    = conformita_core_fine_pubblicazione( (int) $atto->ID );
		$giorno  = is_string( $fine ) ? DatiAtto::data_da_ingresso( $fine ) : null;
		$righe   = array(
			__( 'Numero di repertorio', 'albo-pretorio-pa' ) => null === $numero ? $assente : $numero['numero'] . '/' . $numero['anno'],
			__( 'Inizio della pubblicazione', 'albo-pretorio-pa' ) => false === $inizio ? $assente : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $inizio->getTimestamp() ),
			__( 'Fine della pubblicazione', 'albo-pretorio-pa' ) => null === $giorno ? $assente : wp_date( (string) get_option( 'date_format' ), ( new \DateTimeImmutable( $giorno . ' 12:00:00', wp_timezone() ) )->getTimestamp() ),
		);

		echo '<dl class="albo-pretorio-dati">';

		foreach ( $righe as $etichetta => $valore ) {
			printf( '<dt>%1$s</dt><dd>%2$s</dd>', esc_html( $etichetta ), esc_html( (string) $valore ) );
		}

		echo '</dl>';

		printf( '<p class="description">%s</p>', esc_html__( 'Un atto pubblicato non si modifica: una correzione e\' un atto nuovo che rinvia a questo.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Aggancio a `admin_action_editpost`: intercetta l'invio di un passaggio.
	 *
	 * Se l'invio non porta il pulsante di un passaggio, non fa niente e
	 * WordPress prosegue con il salvataggio.
	 */
	public static function da_editpost(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- si guarda solo se il pulsante c'e'; il gettone si verifica prima di fare qualunque cosa.
		if ( ! isset( $_POST[ self::CAMPO_PASSAGGIO ] ) ) {
			return;
		}

		wp_safe_redirect( self::elabora() );
		exit;
	}

	/**
	 * Controlla il gettone e compie il passaggio chiesto. Un rifiuto lascia il motivo.
	 *
	 * @return string Indirizzo a cui tornare.
	 */
	public static function elabora(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il gettone si verifica qui sotto, prima di leggere qualunque altro campo.
		$atto_id   = isset( $_POST['post_ID'] ) ? absint( wp_unslash( $_POST['post_ID'] ) ) : 0;
		$gettone   = isset( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';
		$indirizzo = (string) get_edit_post_link( $atto_id, 'url' );

		if ( '' === $indirizzo ) {
			$indirizzo = admin_url( 'edit.php?post_type=' . TIPO );
		}

		if ( 0 === $atto_id || ! wp_verify_nonce( $gettone, self::azione( $atto_id ) ) ) {
			return self::rifiuta(
				$atto_id,
				$indirizzo,
				new \WP_Error( 'albo_modulo_non_riconosciuto', __( 'L\'atto e\' rimasto com\'era: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) )
			);
		}

		$passaggio = sanitize_key( wp_unslash( $_POST[ self::CAMPO_PASSAGGIO ] ?? '' ) );

		if ( 'pubblica' === $passaggio ) {
			$conferma  = isset( $_POST[ self::CAMPO_CONFERMA ] ) && is_string( $_POST[ self::CAMPO_CONFERMA ] ) && absint( wp_unslash( $_POST[ self::CAMPO_CONFERMA ] ) ) === $atto_id;
			$richiesta = array( 'conferma' => $conferma );
			$data      = self::data_dalla_schermata();

			if ( null !== $data ) {
				$richiesta['data_inizio'] = $data;
			}

			$esito = Passaggi::pubblica( $atto_id, $richiesta );
			$fatto = 'pubblicato';
		} elseif ( 'rimanda' === $passaggio ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- lo ripulisce Passaggi, con la regola dei testi su piu' righe.
			$motivo = isset( $_POST[ self::CAMPO_MOTIVO ] ) ? wp_unslash( $_POST[ self::CAMPO_MOTIVO ] ) : '';
			$esito  = Passaggi::rimanda_in_bozza( $atto_id, $motivo );
			$fatto  = 'rimandato';
		} else {
			$esito = new \WP_Error( 'albo_passaggio_sconosciuto', __( 'L\'atto e\' rimasto com\'era: il passaggio chiesto non esiste.', 'albo-pretorio-pa' ) );
			$fatto = '';
		}

		if ( is_wp_error( $esito ) ) {
			return self::rifiuta( $atto_id, $indirizzo, $esito );
		}

		return add_query_arg( self::ESITO, $fatto, $indirizzo );
	}

	/**
	 * La data della casella di pubblicazione di WordPress, se chi pubblica l'ha cambiata.
	 *
	 * La schermata manda sempre la data dell'atto, e accanto quella di
	 * partenza nei campi nascosti: e' cambiata se una delle due non coincide,
	 * lo stesso criterio che usa WordPress.
	 *
	 * @return string|null Data nel formato della banca dati, o nullo se non e' cambiata.
	 */
	private static function data_dalla_schermata(): ?string {
		$cambiata = false;
		$valori   = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- il gettone e' gia' stato verificato da chi chiama.
		foreach ( array( 'aa', 'mm', 'jj', 'hh', 'mn', 'ss' ) as $unita ) {
			$valori[ $unita ] = isset( $_POST[ $unita ] ) ? absint( wp_unslash( $_POST[ $unita ] ) ) : 0;

			if ( 'ss' !== $unita && isset( $_POST[ 'hidden_' . $unita ] ) && absint( wp_unslash( $_POST[ 'hidden_' . $unita ] ) ) !== $valori[ $unita ] ) {
				$cambiata = true;
			}
		}
		// phpcs:enable

		if ( ! $cambiata ) {
			return null;
		}

		return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $valori['aa'], $valori['mm'], $valori['jj'], $valori['hh'], $valori['mn'], $valori['ss'] );
	}

	/**
	 * Lascia i motivi del rifiuto a chi ha inviato, e segna l'indirizzo di ritorno.
	 *
	 * @param int       $atto_id   Atto.
	 * @param string    $indirizzo Indirizzo della schermata.
	 * @param \WP_Error $errore    Rifiuto.
	 * @return string
	 */
	private static function rifiuta( int $atto_id, string $indirizzo, \WP_Error $errore ): string {
		$motivi = array();

		foreach ( $errore->get_error_codes() as $codice ) {
			$motivi[ (string) $codice ] = implode( ' ', $errore->get_error_messages( $codice ) );
		}

		Rifiuti::deposita( $atto_id, $motivi );

		return Rifiuti::segna_indirizzo_di_ritorno( $indirizzo, $atto_id );
	}

	/**
	 * L'avviso dell'esito riuscito, nella schermata dell'atto.
	 */
	public static function mostra_esito(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || 'post' !== $schermata->base || TIPO !== $schermata->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- si legge un indicatore per mostrare un avviso, non si compie nessuna azione.
		$esito    = isset( $_GET[ self::ESITO ] ) ? sanitize_key( wp_unslash( $_GET[ self::ESITO ] ) ) : '';
		$messaggi = array(
			'pubblicato' => __( 'Atto pubblicato: numero, inizio e fine sono nel riquadro Pubblicazione.', 'albo-pretorio-pa' ),
			'rimandato'  => __( 'Atto rimandato in bozza: il motivo e\' nel registro delle modifiche.', 'albo-pretorio-pa' ),
		);

		if ( ! isset( $messaggi[ $esito ] ) ) {
			return;
		}

		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $messaggi[ $esito ] ) );
	}
}
