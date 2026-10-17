<?php
/**
 * La schermata dei dati del procedimento: dove l'amministrazione sceglie (ALBO-33).
 *
 * Segue la regola delle impostazioni: dice che cosa prevede la norma, quali
 * sono le scelte possibili e che cosa comporta ciascuna per chi consulta
 * l'albo e per chi prepara gli atti. Nessuna scelta e' segnata finche' non si
 * salva la prima volta. Qui chi pubblica tiene anche i due elenchi da cui chi
 * redige sceglie responsabile e ufficio.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata, invio e avviso dei dati del procedimento.
 */
final class SchermataDatiProcedimento {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-dati-procedimento';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_dati_procedimento';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_procedimento_gettone';

	/**
	 * Nome del gruppo di campi delle scelte, uno per dato.
	 *
	 * @var string
	 */
	const CAMPO_SCELTE = 'albo_pretorio_procedimento_scelte';

	/**
	 * Nome del campo dell'elenco dei responsabili.
	 *
	 * @var string
	 */
	const CAMPO_RESPONSABILI = 'albo_pretorio_procedimento_responsabili';

	/**
	 * Nome del campo dell'elenco degli uffici.
	 *
	 * @var string
	 */
	const CAMPO_UFFICI = 'albo_pretorio_procedimento_uffici';

	/**
	 * Aggancio a `admin_menu`: la voce sotto il menu degli atti.
	 */
	public static function menu(): void {
		$permesso = SchermataRepertorio::permesso();

		if ( null === $permesso || ! post_type_exists( TIPO ) ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=' . TIPO,
			__( 'Dati del procedimento', 'albo-pretorio-pa' ),
			__( 'Dati del procedimento', 'albo-pretorio-pa' ),
			$permesso,
			self::PAGINA,
			array( self::class, 'mostra' )
		);
	}

	/**
	 * Indirizzo della schermata.
	 *
	 * @return string
	 */
	public static function indirizzo(): string {
		return add_query_arg(
			array(
				'post_type' => TIPO,
				'page'      => self::PAGINA,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Il contenuto della schermata.
	 */
	public static function mostra(): void {
		$permesso = SchermataRepertorio::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			wp_die( esc_html__( 'Non si possiede il permesso di vedere l\'impostazione dei dati del procedimento.', 'albo-pretorio-pa' ), 403 );
		}

		$impostazione = DatiProcedimento::impostazione();
		$salvata      = null !== get_option( DatiProcedimento::OPZIONE, null );
		$motivo       = self::preleva_rifiuto();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Dati del procedimento', 'albo-pretorio-pa' ) );

		if ( null !== $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}

		if ( null === $impostazione ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html(
					$salvata
						? __( 'L\'impostazione salvata non si legge: finche\' non la si salva di nuovo, responsabile, ufficio e richiedente non si chiedono e non si mostrano. Gli atti si pubblicano come sempre.', 'albo-pretorio-pa' )
						: __( 'La scelta non e\' ancora fatta: finche\' manca, responsabile, ufficio e richiedente non si chiedono e non si mostrano. Gli atti si pubblicano come sempre.', 'albo-pretorio-pa' )
				)
			);
		}

		self::norma();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );

		foreach ( DatiProcedimento::DATI as $dato ) {
			self::scelte( $dato, null === $impostazione ? null : $impostazione['scelte'][ $dato ] );

			if ( isset( DatiProcedimento::ELENCHI[ $dato ] ) ) {
				self::elenco( $dato, null === $impostazione ? array() : $impostazione[ DatiProcedimento::ELENCHI[ $dato ] ] );
			}
		}

		printf( '<p class="description">%s</p>', esc_html__( 'La scelta vale da subito per tutti gli atti, anche gia\' pubblicati, e decide soltanto che cosa si chiede e che cosa si mostra: i dati gia\' scritti sugli atti restano conservati, e tornano visibili se la scelta cambia.', 'albo-pretorio-pa' ) );

		submit_button( __( 'Salva l\'impostazione', 'albo-pretorio-pa' ) );
		echo '</form>';

		if ( null !== $impostazione ) {
			$utente = get_userdata( $impostazione['utente'] );

			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: nome di chi ha salvato, 2: data e ora. */
						__( 'Ultimo salvataggio: %1$s, il %2$s.', 'albo-pretorio-pa' ),
						$utente ? $utente->display_name : __( 'utente non piu\' presente', 'albo-pretorio-pa' ),
						get_date_from_gmt( $impostazione['istante'], 'd/m/Y H:i' )
					)
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Che cosa dice la norma.
	 */
	private static function norma(): void {
		printf( '<h2>%s</h2>', esc_html__( 'Che cosa dice la norma', 'albo-pretorio-pa' ) );

		$paragrafi = array(
			__( 'La legge sul procedimento amministrativo (l. 241/1990, artt. 4, 5 e 8) chiede all\'amministrazione di stabilire per ogni procedimento l\'ufficio responsabile e la persona responsabile, e di comunicarli a chi e\' interessato quando il procedimento comincia. Non chiede di indicarli nella pubblicazione all\'albo.', 'albo-pretorio-pa' ),
			__( 'Le norme sulla pubblicazione all\'albo (l. 69/2009, art. 32; d.lgs. 267/2000, art. 124) non elencano questi dati, e di solito l\'atto li riporta gia\' nel suo testo. Mostrarli sulla scheda dell\'atto e\' quindi una scelta dell\'amministrazione, utile a chi cerca a chi rivolgersi.', 'albo-pretorio-pa' ),
			__( 'Il richiedente serve soprattutto per gli atti che un altro ente chiede di pubblicare. Si scrive il nome dell\'ente o dell\'ufficio che chiede la pubblicazione, non quello di una persona privata: quel nome resterebbe visibile a chiunque per tutta la durata della pubblicazione.', 'albo-pretorio-pa' ),
		);

		foreach ( $paragrafi as $paragrafo ) {
			printf( '<p>%s</p>', esc_html( $paragrafo ) );
		}
	}

	/**
	 * Le due scelte di un dato, con i loro effetti, e nessuna segnata se non c'e' una scelta salvata.
	 *
	 * @param string      $dato      Uno dei tre dati.
	 * @param string|null $in_vigore Scelta salvata.
	 */
	private static function scelte( string $dato, ?string $in_vigore ): void {
		$elenco = isset( DatiProcedimento::ELENCHI[ $dato ] )
			? __( 'lo sceglie dall\'elenco qui sotto, che chi pubblica tiene aggiornato', 'albo-pretorio-pa' )
			: __( 'lo scrive', 'albo-pretorio-pa' );

		$scelte = array(
			DatiProcedimento::USA     => array(
				'titolo'    => __( 'Chiederlo e mostrarlo', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta l\'albo: sulla scheda dell\'atto trova questo dato, quando chi ha preparato l\'atto lo ha indicato.', 'albo-pretorio-pa' ),
				'ufficio'   => sprintf(
					/* translators: %s: come si compila il dato. */
					__( 'Per chi prepara gli atti: un campo in piu\' nella scheda dell\'atto, facoltativo; chi redige %s. Alla pubblicazione il valore si fissa, non si cambia piu\' e finisce nel registro delle modifiche.', 'albo-pretorio-pa' ),
					$elenco
				),
			),
			DatiProcedimento::NON_USA => array(
				'titolo'    => __( 'Non usarlo', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta l\'albo: questo dato non compare sulla scheda dell\'atto; lo trova solo se l\'atto lo riporta nel suo testo.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi prepara gli atti: nessun campo in piu\'. I valori gia\' presenti su un atto, per esempio portati da un albo precedente, restano conservati.', 'albo-pretorio-pa' ),
			),
		);

		printf( '<h2>%s</h2>', esc_html( DatiProcedimento::etichetta( $dato ) ) );
		printf( '<fieldset><legend class="screen-reader-text">%s</legend>', esc_html( DatiProcedimento::etichetta( $dato ) ) );

		foreach ( $scelte as $valore => $scelta ) {
			$id = self::CAMPO_SCELTE . '_' . $dato . '_' . $valore;

			printf(
				'<p><input type="radio" id="%1$s" name="%2$s[%3$s]" value="%4$s"%5$s /> <label for="%1$s"><strong>%6$s</strong></label></p><ul style="list-style:disc;margin-left:3em"><li>%7$s</li><li>%8$s</li></ul>',
				esc_attr( $id ),
				esc_attr( self::CAMPO_SCELTE ),
				esc_attr( $dato ),
				esc_attr( $valore ),
				$in_vigore === $valore ? ' checked="checked"' : '',
				esc_html( $scelta['titolo'] ),
				esc_html( $scelta['cittadino'] ),
				esc_html( $scelta['ufficio'] )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Il campo di un elenco, con la sua spiegazione.
	 *
	 * @param string             $dato   Responsabile o ufficio.
	 * @param array<int, string> $elenco Nomi in vigore.
	 */
	private static function elenco( string $dato, array $elenco ): void {
		$campo = self::campo_elenco( $dato );

		printf(
			'<h3><label for="%1$s">%2$s</label></h3>',
			esc_attr( $campo ),
			esc_html(
				DatiProcedimento::RESPONSABILE === $dato
					? __( 'L\'elenco dei responsabili', 'albo-pretorio-pa' )
					: __( 'L\'elenco degli uffici', 'albo-pretorio-pa' )
			)
		);
		printf(
			'<p class="description" id="%1$s_aiuto">%2$s</p>',
			esc_attr( $campo ),
			esc_html__( 'Un nome per riga. Per aggiungere un nome si scrive una riga nuova, per toglierlo si cancella la sua riga. Un nome tolto non sparisce dagli atti che lo portano gia\': smette solo di essere proposto per gli atti nuovi.', 'albo-pretorio-pa' )
		);
		printf(
			'<textarea id="%1$s" name="%1$s" rows="8" cols="90" class="large-text" aria-describedby="%1$s_aiuto">%2$s</textarea>',
			esc_attr( $campo ),
			esc_textarea( implode( "\n", $elenco ) )
		);
	}

	/**
	 * Il nome del campo dell'elenco di un dato.
	 *
	 * @param string $dato Responsabile o ufficio.
	 * @return string
	 */
	private static function campo_elenco( string $dato ): string {
		return DatiProcedimento::RESPONSABILE === $dato ? self::CAMPO_RESPONSABILI : self::CAMPO_UFFICI;
	}

	/**
	 * Aggancio a `admin_post_` della schermata: salva e torna alla schermata.
	 */
	public static function da_invio(): void {
		self::elabora();

		wp_safe_redirect( self::indirizzo() );
		exit;
	}

	/**
	 * Controlla gettone e permesso, poi salva. Un rifiuto lascia il motivo.
	 *
	 * @return true|\WP_Error
	 */
	public static function elabora() {
		$esito = self::salva();

		if ( is_wp_error( $esito ) ) {
			set_transient( self::chiave_rifiuto(), $esito->get_error_message(), 5 * MINUTE_IN_SECONDS );
		} else {
			delete_transient( self::chiave_rifiuto() );
		}

		return $esito;
	}

	/**
	 * Il salvataggio, dopo gettone e permesso.
	 *
	 * @return true|\WP_Error
	 */
	private static function salva() {
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) && is_string( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( ! wp_verify_nonce( $gettone, self::AZIONE ) ) {
			return new \WP_Error( 'albo_procedimento_gettone', __( 'Impostazione non salvata: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		}

		$permesso = SchermataRepertorio::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			return new \WP_Error( 'albo_procedimento_non_permesso', __( 'Impostazione non salvata: non si possiede il permesso di pubblicare atti.', 'albo-pretorio-pa' ) );
		}

		// Ogni scelta che manca o non e' un testo arriva come nulla, e `DatiProcedimento::salva()` la rifiuta.
		$scelte = array();
		$invio  = isset( $_POST[ self::CAMPO_SCELTE ] ) && is_array( $_POST[ self::CAMPO_SCELTE ] ) ? wp_unslash( $_POST[ self::CAMPO_SCELTE ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ogni scelta si controlla qui sotto, contro i due valori ammessi.

		foreach ( DatiProcedimento::DATI as $dato ) {
			$scelte[ $dato ] = isset( $invio[ $dato ] ) && is_string( $invio[ $dato ] ) ? sanitize_key( $invio[ $dato ] ) : null;
		}

		// Gli elenchi arrivano interi a `DatiProcedimento::leggi_elenco()`, che controlla ogni riga e rifiuta
		// invece di ripulire: ripulirli prima cambierebbe di nascosto un nome scritto male.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- controllato riga per riga in DatiProcedimento::leggi_elenco().
		$responsabili = isset( $_POST[ self::CAMPO_RESPONSABILI ] ) && is_string( $_POST[ self::CAMPO_RESPONSABILI ] ) ? wp_unslash( $_POST[ self::CAMPO_RESPONSABILI ] ) : null;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- come sopra.
		$uffici = isset( $_POST[ self::CAMPO_UFFICI ] ) && is_string( $_POST[ self::CAMPO_UFFICI ] ) ? wp_unslash( $_POST[ self::CAMPO_UFFICI ] ) : null;

		return DatiProcedimento::salva( $scelte, $responsabili, $uffici, get_current_user_id(), current_datetime() );
	}

	/**
	 * Chiave del motivo di rifiuto, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return 'albo_pretorio_procedimento_rifiuto_' . get_current_user_id();
	}

	/**
	 * Preleva il motivo dell'ultimo rifiuto, e lo consuma.
	 *
	 * @return string|null
	 */
	private static function preleva_rifiuto(): ?string {
		$motivo = get_transient( self::chiave_rifiuto() );

		if ( false === $motivo ) {
			return null;
		}

		delete_transient( self::chiave_rifiuto() );

		return (string) $motivo;
	}

	/**
	 * Aggancio a `admin_notices`: l'avviso a chi pubblica, finche' la scelta non e' fatta.
	 */
	public static function avviso(): void {
		$permesso = SchermataRepertorio::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) || null !== DatiProcedimento::impostazione() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Resta da scegliere se chiedere e mostrare sugli atti il responsabile del procedimento, l\'ufficio e il richiedente della pubblicazione. Gli atti si pubblicano anche prima della scelta.', 'albo-pretorio-pa' ),
			esc_url( self::indirizzo() ),
			esc_html__( 'Apri i dati del procedimento', 'albo-pretorio-pa' )
		);
	}
}
