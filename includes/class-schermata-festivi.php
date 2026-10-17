<?php
/**
 * La schermata dei giorni festivi: dove l'amministrazione sceglie (ALBO-32).
 *
 * Segue la regola delle impostazioni: dice che cosa prevede la norma, quali
 * sono le scelte possibili e che cosa comporta ciascuna per chi consulta
 * l'albo e per chi pubblica. Nessuna scelta e' segnata finche' non si salva
 * la prima volta, e finche' manca pubblicazione e proroga si fermano.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata, invio e avviso dei giorni festivi.
 */
final class SchermataFestivi {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-festivi';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_festivi';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_festivi_gettone';

	/**
	 * Nome del campo della scelta.
	 *
	 * @var string
	 */
	const CAMPO_SCELTA = 'albo_pretorio_festivi_scelta';

	/**
	 * Nome del campo dell'elenco.
	 *
	 * @var string
	 */
	const CAMPO_ELENCO = 'albo_pretorio_festivi_elenco';

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
			__( 'Giorni festivi', 'albo-pretorio-pa' ),
			__( 'Giorni festivi', 'albo-pretorio-pa' ),
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
			wp_die( esc_html__( 'Non si possiede il permesso di vedere l\'impostazione dei giorni festivi.', 'albo-pretorio-pa' ), 403 );
		}

		$impostazione = Festivi::impostazione();
		$salvata      = null !== get_option( Festivi::OPZIONE, null );
		$motivo       = self::preleva_rifiuto();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Giorni festivi', 'albo-pretorio-pa' ) );

		if ( null !== $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}

		if ( null === $impostazione ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html(
					$salvata
						? __( 'L\'impostazione salvata non si legge: finche\' non la si salva di nuovo, gli atti non si pubblicano e le proroghe non si dispongono.', 'albo-pretorio-pa' )
						: __( 'La scelta non e\' ancora fatta: finche\' manca, gli atti non si pubblicano e le proroghe non si dispongono.', 'albo-pretorio-pa' )
				)
			);
		}

		self::norma();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );

		self::scelte( null === $impostazione ? null : $impostazione['scelta'] );
		self::elenco( null === $impostazione ? ( $salvata ? '' : Festivi::proposta() ) : Festivi::testo( $impostazione['elenco'] ) );

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
			__( 'Il testo unico degli enti locali (d.lgs. 267/2000, art. 124) chiede che gli atti restino pubblicati per quindici giorni consecutivi, salvo specifiche disposizioni di legge, e non dice che cosa succede quando l\'ultimo giorno e\' festivo.', 'albo-pretorio-pa' ),
			__( 'Le regole generali sul computo dei termini (codice civile, art. 2963 comma 3; codice di procedura civile, art. 155 comma 4) spostano al primo giorno seguente non festivo il termine che scade in un giorno festivo. Nessun testo dice in modo esplicito se valgano anche per il periodo di pubblicazione all\'albo: e\' una lettura che spetta all\'amministrazione.', 'albo-pretorio-pa' ),
			__( 'Le domeniche e le feste nazionali sono giorni festivi per la legge 260/1949, art. 2, e successive modificazioni; il santo patrono e\' festivo nel solo comune. Il sabato non e\' festivo.', 'albo-pretorio-pa' ),
			__( 'In nessuno dei due casi il periodo e\' piu\' corto della durata applicabile: il giorno di pubblicazione non conta, e un atto pubblicato il 1 ottobre per quindici giorni ha come fine il 16, visibile fino alla mezzanotte.', 'albo-pretorio-pa' ),
		);

		foreach ( $paragrafi as $paragrafo ) {
			printf( '<p>%s</p>', esc_html( $paragrafo ) );
		}
	}

	/**
	 * Le due scelte, con i loro effetti, e nessuna segnata se non c'e' una scelta salvata.
	 *
	 * @param string|null $in_vigore Scelta salvata.
	 */
	private static function scelte( ?string $in_vigore ): void {
		$scelte = array(
			Festivi::ACCESO => array(
				'titolo'    => __( 'Spostare la fine al primo giorno non festivo', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta l\'albo: quando la fine cadrebbe di domenica o in un giorno dell\'elenco, l\'atto resta visibile fino al primo giorno feriale, cioe\' uno o piu\' giorni in piu\'.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi prepara e pubblica gli atti: la data di fine puo\' essere piu\' tarda dell\'inizio piu\' la durata; il registro delle modifiche riporta la fine calcolata e i giorni festivi saltati; l\'elenco qui sotto va tenuto aggiornato, santo patrono compreso. Vale anche per la proroga per disservizio.', 'albo-pretorio-pa' ),
			),
			Festivi::SPENTO => array(
				'titolo'    => __( 'Lasciare la fine dove cade', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta l\'albo: la fine e\' sempre l\'inizio piu\' la durata, anche quando cade di domenica o in un giorno festivo, in cui gli uffici sono chiusi.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi prepara e pubblica gli atti: la data di fine e\' sempre prevedibile, l\'inizio piu\' la durata; l\'elenco qui sotto non viene usato.', 'albo-pretorio-pa' ),
			),
		);

		printf( '<h2>%s</h2>', esc_html__( 'La scelta', 'albo-pretorio-pa' ) );
		printf( '<fieldset><legend class="screen-reader-text">%s</legend>', esc_html__( 'Che cosa succede quando la data di fine cade in un giorno festivo', 'albo-pretorio-pa' ) );

		foreach ( $scelte as $valore => $scelta ) {
			$id = self::CAMPO_SCELTA . '_' . $valore;

			printf(
				'<p><input type="radio" id="%1$s" name="%2$s" value="%3$s"%4$s /> <label for="%1$s"><strong>%5$s</strong></label></p><ul style="list-style:disc;margin-left:3em"><li>%6$s</li><li>%7$s</li></ul>',
				esc_attr( $id ),
				esc_attr( self::CAMPO_SCELTA ),
				esc_attr( $valore ),
				$in_vigore === $valore ? ' checked="checked"' : '',
				esc_html( $scelta['titolo'] ),
				esc_html( $scelta['cittadino'] ),
				esc_html( $scelta['ufficio'] )
			);
		}

		echo '</fieldset>';
		printf( '<p class="description">%s</p>', esc_html__( 'La scelta vale per le date di fine calcolate da quando la si salva: quelle gia\' scritte non cambiano.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Il campo dell'elenco, con la sua spiegazione.
	 *
	 * @param string $testo Contenuto del campo.
	 */
	private static function elenco( string $testo ): void {
		printf( '<h2><label for="%1$s">%2$s</label></h2>', esc_attr( self::CAMPO_ELENCO ), esc_html__( 'L\'elenco dei giorni festivi', 'albo-pretorio-pa' ) );
		printf(
			'<p class="description" id="%1$s_aiuto">%2$s</p>',
			esc_attr( self::CAMPO_ELENCO ),
			esc_html__( 'Un giorno per riga, nella forma data | descrizione. La data e\' GG/MM per un giorno che torna ogni anno, GG/MM/AAAA per un giorno solo, Pasqua+N per N giorni dopo Pasqua (Pasqua+1 e\' il lunedi\' dell\'Angelo). Nella descrizione si scrive la norma che istituisce la festa. Le righe che cominciano con # sono note e non contano. Le domeniche sono sempre festive e non si scrivono.', 'albo-pretorio-pa' )
		);
		printf(
			'<textarea id="%1$s" name="%1$s" rows="18" cols="90" class="large-text code" aria-describedby="%1$s_aiuto">%2$s</textarea>',
			esc_attr( self::CAMPO_ELENCO ),
			esc_textarea( $testo )
		);
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
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( ! wp_verify_nonce( $gettone, self::AZIONE ) ) {
			return new \WP_Error( 'albo_festivi_gettone', __( 'Impostazione non salvata: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		}

		$permesso = SchermataRepertorio::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			return new \WP_Error( 'albo_festivi_non_permesso', __( 'Impostazione non salvata: non si possiede il permesso di pubblicare atti.', 'albo-pretorio-pa' ) );
		}

		// Un campo mancante o che non e' un testo arriva come nullo, e `Festivi::salva()` lo rifiuta.
		$scelta = isset( $_POST[ self::CAMPO_SCELTA ] ) && is_string( $_POST[ self::CAMPO_SCELTA ] ) ? sanitize_key( wp_unslash( $_POST[ self::CAMPO_SCELTA ] ) ) : null;
		// L'elenco si controlla riga per riga in `Festivi::leggi_elenco()`, che rifiuta ogni riga con segni di formattazione.
		$elenco = isset( $_POST[ self::CAMPO_ELENCO ] ) && is_string( $_POST[ self::CAMPO_ELENCO ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::CAMPO_ELENCO ] ) ) : null;

		return Festivi::salva( $scelta, $elenco, get_current_user_id(), current_datetime() );
	}

	/**
	 * Chiave del motivo di rifiuto, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return 'albo_pretorio_festivi_rifiuto_' . get_current_user_id();
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

		if ( null === $permesso || ! current_user_can( $permesso ) || null !== Festivi::scelta() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Prima di pubblicare va scelto che cosa succede quando la data di fine di un atto cade in un giorno festivo.', 'albo-pretorio-pa' ),
			esc_url( self::indirizzo() ),
			esc_html__( 'Apri i giorni festivi', 'albo-pretorio-pa' )
		);
	}
}
