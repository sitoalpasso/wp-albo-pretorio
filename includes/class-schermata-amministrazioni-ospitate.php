<?php
/**
 * La schermata delle amministrazioni ospitate: dove chi pubblica tiene l'elenco (ALBO-29).
 *
 * Segue la regola delle impostazioni: dice che cosa prevede la norma, quali
 * sono le scelte possibili (lasciare l'elenco vuoto o aggiungere voci) e che
 * cosa comporta ciascuna per chi consulta l'albo e per chi prepara gli atti.
 * L'elenco vuoto e' uno stato pieno e non una scelta mancante: nessun avviso
 * in bacheca, perche' un ente che non pubblica per altri non ha niente da
 * fare.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata e invio dell'elenco delle amministrazioni ospitate.
 */
final class SchermataAmministrazioniOspitate {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-amministrazioni-ospitate';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_amministrazioni_ospitate';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_ospitate_gettone';

	/**
	 * Nome del gruppo dei nomi delle voci esistenti, per numero.
	 *
	 * @var string
	 */
	const CAMPO_NOMI = 'albo_pretorio_ospitate_nomi';

	/**
	 * Nome del gruppo delle caselle "Togli", con il numero della voce.
	 *
	 * @var string
	 */
	const CAMPO_TOGLI = 'albo_pretorio_ospitate_togli';

	/**
	 * Nome del campo delle voci nuove.
	 *
	 * @var string
	 */
	const CAMPO_NUOVE = 'albo_pretorio_ospitate_nuove';

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
			__( 'Amministrazioni ospitate', 'albo-pretorio-pa' ),
			__( 'Amministrazioni ospitate', 'albo-pretorio-pa' ),
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
			wp_die( esc_html__( 'Non si possiede il permesso di vedere l\'elenco delle amministrazioni ospitate.', 'albo-pretorio-pa' ), 403 );
		}

		$impostazione = AmministrazioniOspitate::impostazione();
		$motivo       = self::preleva_rifiuto();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Amministrazioni ospitate', 'albo-pretorio-pa' ) );

		if ( null !== $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}

		if ( AmministrazioniOspitate::illeggibile() ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'L\'elenco salvato non si legge, e da qui non si puo\' salvare sopra: gli atti in preparazione indicano le voci per numero, e un elenco ricominciato darebbe a un\'altra amministrazione il numero di una voce scelta. Va ripristinato dalla banca dati. Nel frattempo passano soltanto gli atti indicati come di questa amministrazione.', 'albo-pretorio-pa' )
			);
		}

		self::norma();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );

		$voci = AmministrazioniOspitate::voci();

		printf( '<h2>%s</h2>', esc_html__( 'L\'elenco', 'albo-pretorio-pa' ) );

		if ( array() === $voci ) {
			printf( '<p>%s</p>', esc_html__( 'L\'elenco e\' vuoto: la domanda non compare negli atti, e ogni atto e\' di questa amministrazione.', 'albo-pretorio-pa' ) );
		} else {
			printf( '<table class="widefat striped"><caption class="screen-reader-text">%s</caption><thead><tr>', esc_html__( 'Le amministrazioni ospitate', 'albo-pretorio-pa' ) );
			printf( '<th scope="col">%1$s</th><th scope="col">%2$s</th></tr></thead><tbody>', esc_html__( 'Nome', 'albo-pretorio-pa' ), esc_html__( 'Togli', 'albo-pretorio-pa' ) );

			foreach ( $voci as $id => $nome ) {
				printf(
					'<tr><td><label class="screen-reader-text" for="%1$s_%3$d">%4$s</label><input type="text" class="regular-text" id="%1$s_%3$d" name="%1$s[%3$d]" value="%5$s" maxlength="%6$d" /></td><td><input type="checkbox" id="%2$s_%3$d" name="%2$s[]" value="%3$d" /> <label for="%2$s_%3$d">%7$s</label></td></tr>',
					esc_attr( self::CAMPO_NOMI ),
					esc_attr( self::CAMPO_TOGLI ),
					(int) $id,
					esc_html__( 'Nome della voce', 'albo-pretorio-pa' ),
					htmlspecialchars( $nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- codificato per intero, entita' comprese, come nella scheda dell'atto.
					(int) DatiProcedimento::LUNGHEZZA_MASSIMA,
					esc_html(
						sprintf(
							/* translators: %s: nome della voce. */
							__( 'Togli %s', 'albo-pretorio-pa' ),
							$nome
						)
					)
				);
			}

			echo '</tbody></table>';
			printf( '<p class="description">%s</p>', esc_html__( 'Per correggere un nome lo si riscrive: gli atti gia\' pubblicati conservano il nome che avevano alla pubblicazione, quelli in preparazione prendono il nome nuovo. Una voce tolta non si propone piu\'; gli atti in preparazione che la indicano vanno scelti di nuovo prima di andare avanti.', 'albo-pretorio-pa' ) );
		}

		printf( '<h3><label for="%1$s">%2$s</label></h3>', esc_attr( self::CAMPO_NUOVE ), esc_html__( 'Aggiungi amministrazioni', 'albo-pretorio-pa' ) );
		printf( '<p class="description" id="%1$s_aiuto">%2$s</p>', esc_attr( self::CAMPO_NUOVE ), esc_html__( 'Un nome per riga, come deve leggerlo chi consulta l\'albo: per esempio il nome ufficiale dell\'ente.', 'albo-pretorio-pa' ) );
		printf( '<textarea id="%1$s" name="%1$s" rows="4" cols="90" class="large-text" aria-describedby="%1$s_aiuto"></textarea>', esc_attr( self::CAMPO_NUOVE ) );

		submit_button( __( 'Salva l\'elenco', 'albo-pretorio-pa' ) );
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
	 * Che cosa dice la norma, e che cosa comporta ciascuna scelta.
	 */
	private static function norma(): void {
		printf( '<h2>%s</h2>', esc_html__( 'Che cosa dice la norma', 'albo-pretorio-pa' ) );

		$paragrafi = array(
			__( 'La legge consente a un ente di pubblicare i propri atti sull\'albo online di un\'altra amministrazione, per esempio di un\'unione, di un consorzio o dell\'ente che lo ospita (l. 69/2009, art. 32, c. 3). Chi risponde dei dati pubblicati lo stabiliscono le amministrazioni fra loro, di solito in una convenzione: questo elenco non lo decide.', 'albo-pretorio-pa' ),
			__( 'Quello che conta per chi consulta l\'albo e\' sapere di chi e\' l\'atto. Per questo ogni atto pubblicato per conto di un\'altra amministrazione ne porta il nome.', 'albo-pretorio-pa' ),
		);

		foreach ( $paragrafi as $paragrafo ) {
			printf( '<p>%s</p>', esc_html( $paragrafo ) );
		}

		printf( '<h2>%s</h2>', esc_html__( 'Le due situazioni', 'albo-pretorio-pa' ) );

		$situazioni = array(
			array(
				'titolo'    => __( 'Elenco vuoto: questo albo pubblica solo atti di questa amministrazione', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta l\'albo: nessuna indicazione in piu\'; ogni atto e\' dell\'amministrazione che tiene l\'albo.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi prepara gli atti: nessuna domanda in piu\'.', 'albo-pretorio-pa' ),
			),
			array(
				'titolo'    => __( 'Una o piu\' amministrazioni nell\'elenco: questo albo pubblica anche per conto loro', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta l\'albo: sugli atti di un\'altra amministrazione il suo nome compare nell\'elenco degli atti, nella scheda e nei risultati della ricerca.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi prepara gli atti: ogni atto chiede di chi e\', fra questa amministrazione e quelle dell\'elenco, senza nessuna risposta gia\' segnata, e non va in verifica finche\' non si sceglie. L\'atto ospitato lo pubblica e lo toglie chi pubblica tutti gli altri: nessun accesso separato per l\'altra amministrazione.', 'albo-pretorio-pa' ),
			),
		);

		foreach ( $situazioni as $situazione ) {
			printf(
				'<p><strong>%1$s</strong></p><ul style="list-style:disc;margin-left:3em"><li>%2$s</li><li>%3$s</li></ul>',
				esc_html( $situazione['titolo'] ),
				esc_html( $situazione['cittadino'] ),
				esc_html( $situazione['ufficio'] )
			);
		}
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
			return new \WP_Error( 'albo_ospitate_gettone', __( 'Elenco non salvato: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		}

		$permesso = SchermataRepertorio::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			return new \WP_Error( 'albo_ospitate_non_permesso', __( 'Elenco non salvato: non si possiede il permesso di pubblicare atti.', 'albo-pretorio-pa' ) );
		}

		// Nomi e voci nuove arrivano interi ad AmministrazioniOspitate::salva(), che li controlla e rifiuta invece
		// di ripulirli: ripuliti prima, un nome scritto male cambierebbe di nascosto.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- controllati in AmministrazioniOspitate::salva().
		$nomi  = isset( $_POST[ self::CAMPO_NOMI ] ) ? wp_unslash( $_POST[ self::CAMPO_NOMI ] ) : array();
		$tolte = isset( $_POST[ self::CAMPO_TOGLI ] ) ? wp_unslash( $_POST[ self::CAMPO_TOGLI ] ) : array();
		$nuove = isset( $_POST[ self::CAMPO_NUOVE ] ) ? wp_unslash( $_POST[ self::CAMPO_NUOVE ] ) : '';
		// phpcs:enable

		return AmministrazioniOspitate::salva( $nomi, $tolte, $nuove, get_current_user_id(), current_datetime() );
	}

	/**
	 * Chiave del motivo di rifiuto, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return 'albo_pretorio_ospitate_rifiuto_' . get_current_user_id();
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
}
