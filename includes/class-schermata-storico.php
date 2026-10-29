<?php
/**
 * La schermata dello storico pubblico: dove l'amministrazione sceglie (ALBO-36).
 *
 * Segue la regola delle impostazioni: dice che cosa prevede la norma, quali
 * sono le scelte possibili e che cosa comporta ciascuna per chi consulta e per
 * chi lavora gli atti. Nessuna scelta e' segnata finche' non si salva la prima
 * volta, e finche' manca lo storico e' spento.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata e invio dello storico pubblico.
 */
final class SchermataStorico {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-storico';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_storico';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = self::AZIONE . '_gettone';

	/**
	 * Nome del campo della scelta.
	 *
	 * @var string
	 */
	const CAMPO_SCELTA = self::AZIONE . '_scelta';

	/**
	 * Nome del campo degli anni.
	 *
	 * @var string
	 */
	const CAMPO_ANNI = self::AZIONE . '_anni';

	/**
	 * Nome del campo con lo stato dell'impostazione all'apertura del modulo.
	 *
	 * @var string
	 */
	const CAMPO_MOSTRATO = self::AZIONE . '_mostrato';

	/**
	 * Il permesso dello storico, come lo vede WordPress, o nullo.
	 *
	 * @return string|null
	 */
	public static function permesso(): ?string {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || empty( $mappa[ Permessi::CHIAVE_STORICO ] ) ) {
			return null;
		}

		return (string) $mappa[ Permessi::CHIAVE_STORICO ];
	}

	/**
	 * Aggancio a `admin_menu`.
	 *
	 * Chi gestisce gli atti trova la voce nel loro menu; chi ha soltanto il
	 * permesso dello storico la trova in cima al menu dell'amministrazione.
	 */
	public static function menu(): void {
		$permesso = self::permesso();

		if ( null === $permesso || ! post_type_exists( TIPO ) || ! Storico::pronto() ) {
			return;
		}

		$titolo = __( 'Storico pubblico degli atti', 'albo-pretorio-pa' );
		$voce   = __( 'Storico pubblico', 'albo-pretorio-pa' );
		$tipo   = get_post_type_object( TIPO );

		if ( null !== $tipo && current_user_can( $tipo->cap->edit_posts ) ) {
			add_submenu_page( 'edit.php?post_type=' . TIPO, $titolo, $voce, $permesso, self::PAGINA, array( self::class, 'mostra' ) );
			return;
		}

		add_menu_page( $titolo, $voce, $permesso, self::PAGINA, array( self::class, 'mostra' ), 'dashicons-clock' );
	}

	/**
	 * Indirizzo della schermata, sotto il menu degli atti o in cima.
	 *
	 * @return string
	 */
	public static function indirizzo(): string {
		$tipo = get_post_type_object( TIPO );

		if ( null !== $tipo && current_user_can( $tipo->cap->edit_posts ) ) {
			return add_query_arg(
				array(
					'post_type' => TIPO,
					'page'      => self::PAGINA,
				),
				admin_url( 'edit.php' )
			);
		}

		return add_query_arg( 'page', self::PAGINA, admin_url( 'admin.php' ) );
	}

	/**
	 * Il contenuto della schermata.
	 */
	public static function mostra(): void {
		$permesso = self::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			wp_die( esc_html__( 'Non si possiede il permesso di gestire lo storico pubblico.', 'albo-pretorio-pa' ), 403 );
		}

		$fotografia   = Storico::fotografia();
		$impostazione = $fotografia['impostazione'];
		$motivo       = self::preleva_rifiuto();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Storico pubblico degli atti', 'albo-pretorio-pa' ) );

		if ( null !== $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}

		if ( null === $impostazione ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html( self::senza_impostazione( $fotografia['esito'] ) )
			);
		}

		self::cosa_e();
		self::norma();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::CAMPO_MOSTRATO ), esc_attr( Storico::stato_mostrato( $fotografia ) ) );

		self::scelte( null === $impostazione ? null : $impostazione['scelta'] );
		self::anni( null === $impostazione || null === $impostazione['anni'] ? '' : (string) $impostazione['anni'] );

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
	 * Che cosa dire quando l'impostazione non vale.
	 *
	 * @param string $esito Esito della lettura.
	 * @return string
	 */
	private static function senza_impostazione( string $esito ): string {
		if ( Storico::ILLEGGIBILE === $esito ) {
			return __( 'L\'impostazione non si legge in questo momento: finche\' non si legge, lo storico pubblico e\' spento e non si salva. Ricaricare la pagina.', 'albo-pretorio-pa' );
		}

		if ( Storico::MALFORMATA === $esito ) {
			return __( 'L\'impostazione salvata non si legge: finche\' non la si salva di nuovo, lo storico pubblico e\' spento.', 'albo-pretorio-pa' );
		}

		return __( 'La scelta non e\' ancora fatta: finche\' manca, lo storico pubblico e\' spento.', 'albo-pretorio-pa' );
	}

	/**
	 * Che cos'e' lo storico, in poche righe.
	 */
	private static function cosa_e(): void {
		$paragrafi = array(
			__( 'Lo storico e\' una sezione pubblica separata dall\'albo, dove si mettono a disposizione di chiunque le copie oscurate di atti la cui pubblicazione e\' terminata. L\'albo non cambia: l\'atto scaduto resta non raggiungibile, e il suo documento originale si apre soltanto dalla consultazione riservata degli atti scaduti.', 'albo-pretorio-pa' ),
			__( 'Niente entra nello storico da se\'. Una persona con il permesso dello storico sceglie l\'atto, carica una copia del documento da cui ha tolto i dati personali, ne scrive l\'oggetto e conferma di averli controllati. La copia si toglie in ogni momento, e ogni ingresso e ogni uscita restano nel registro delle modifiche, con chi e quando.', 'albo-pretorio-pa' ),
		);

		foreach ( $paragrafi as $paragrafo ) {
			printf( '<p>%s</p>', esc_html( $paragrafo ) );
		}
	}

	/**
	 * Che cosa dice la norma.
	 */
	private static function norma(): void {
		printf( '<h2>%s</h2>', esc_html__( 'Che cosa dice la norma', 'albo-pretorio-pa' ) );

		$paragrafi = array(
			__( 'Finito il periodo di pubblicazione, l\'amministrazione non puo\' continuare a diffondere i dati personali contenuti negli atti dell\'albo (Garante per la protezione dei dati personali, linee guida approvate con il provvedimento n. 243 del 2014, parte seconda, paragrafo 3.a).', 'albo-pretorio-pa' ),
			__( 'Se gli atti restano in rete, scaduti i termini i documenti vanno privati degli elementi che identificano le persone; il testo integrale resta disponibile con la richiesta di accesso agli atti della legge 241/1990 (stesse linee guida, paragrafo 2.b). I dati personali vanno sottratti ai motori di ricerca (paragrafo 2.a): lo storico, come l\'albo, chiede ai motori di non indicizzare le sue pagine, e la scelta non si cambia.', 'albo-pretorio-pa' ),
			__( 'Dati genetici, biometrici e relativi alla salute non si diffondono mai (d.lgs. 196/2003, articolo 2-septies, comma 8): una copia che li contiene non va nello storico.', 'albo-pretorio-pa' ),
			__( 'Il periodo di cinque anni dell\'articolo 8, comma 3, del d.lgs. 33/2013 vale per gli obblighi di trasparenza e non per gli atti dell\'albo (stesse linee guida, paragrafo 3.a). Per le copie oscurate nessuna norma fissa un termine: per questo gli anni li sceglie l\'amministrazione. Non sono la "data di oblio" che altri sistemi d\'albo associano all\'atto originale: qui valgono soltanto per le copie da cui i dati personali sono stati tolti.', 'albo-pretorio-pa' ),
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
			Storico::ACCESO => array(
				'titolo'    => __( 'Acceso', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta: sulla pagina dello storico trova le copie oscurate degli atti scelti, con l\'avviso che la pubblicazione all\'albo e\' terminata, fino alla fine di ciascuna copia. Gli atti che non sono nello storico si chiedono all\'ufficio, come oggi.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi lavora gli atti: per ogni atto da mettere nello storico va preparata e caricata una copia senza dati personali, e va confermato di averla controllata insieme all\'oggetto. Della diffusione della copia risponde l\'amministrazione, e il registro dice chi l\'ha messa e quando. Gli anni scritti qui valgono per le copie che entrano da quando si salva: la fine delle copie gia\' nello storico non cambia.', 'albo-pretorio-pa' ),
			),
			Storico::SPENTO => array(
				'titolo'    => __( 'Spento', 'albo-pretorio-pa' ),
				'cittadino' => __( 'Per chi consulta: nessuno storico sul sito; gli atti scaduti si chiedono all\'ufficio.', 'albo-pretorio-pa' ),
				'ufficio'   => __( 'Per chi lavora gli atti: nessuna copia entra nello storico. Se ce n\'erano, spariscono dal sito ma restano salvate con la loro fine, e riaccendendo tornano quelle non ancora finite.', 'albo-pretorio-pa' ),
			),
		);

		printf( '<h2>%s</h2>', esc_html__( 'La scelta', 'albo-pretorio-pa' ) );
		printf( '<fieldset><legend class="screen-reader-text">%s</legend>', esc_html__( 'Lo storico pubblico e\' acceso o spento', 'albo-pretorio-pa' ) );

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
	}

	/**
	 * Il campo degli anni, con la sua spiegazione.
	 *
	 * @param string $valore Contenuto del campo.
	 */
	private static function anni( string $valore ): void {
		printf( '<h2><label for="%1$s">%2$s</label></h2>', esc_attr( self::CAMPO_ANNI ), esc_html__( 'Per quanti anni una copia resta nello storico', 'albo-pretorio-pa' ) );
		printf(
			'<p class="description" id="%1$s_aiuto">%2$s</p>',
			esc_attr( self::CAMPO_ANNI ),
			esc_html(
				sprintf(
					/* translators: %d: numero massimo di anni. */
					__( 'Obbligatorio per accendere lo storico, un numero intero da 1 a %d. Gli anni si contano dal giorno in cui la copia entra nello storico: alla fine la copia sparisce da sola dal sito, e si puo\' togliere prima in ogni momento. Nessun numero e\' proposto.', 'albo-pretorio-pa' ),
					Storico::ANNI_MASSIMI
				)
			)
		);
		printf(
			'<input type="text" inputmode="numeric" id="%1$s" name="%1$s" value="%2$s" size="4" aria-describedby="%1$s_aiuto" />',
			esc_attr( self::CAMPO_ANNI ),
			esc_attr( $valore )
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
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) && is_string( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( ! wp_verify_nonce( $gettone, self::AZIONE ) ) {
			return new \WP_Error( 'albo_copia_gettone', __( 'Impostazione non salvata: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		}

		$permesso = self::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			return new \WP_Error( 'albo_copia_non_permesso', __( 'Impostazione non salvata: non si possiede il permesso di gestire lo storico pubblico.', 'albo-pretorio-pa' ) );
		}

		// Un campo mancante o che non e' un testo arriva come nullo, e `Storico::salva()` lo rifiuta.
		$scelta = isset( $_POST[ self::CAMPO_SCELTA ] ) && is_string( $_POST[ self::CAMPO_SCELTA ] ) ? sanitize_key( wp_unslash( $_POST[ self::CAMPO_SCELTA ] ) ) : null;

		if ( ! isset( $_POST[ self::CAMPO_ANNI ] ) ) {
			$anni = null;
		} elseif ( is_string( $_POST[ self::CAMPO_ANNI ] ) ) {
			$anni = sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_ANNI ] ) );
		} else {
			// Un elenco al posto del testo non diventa un campo vuoto: arriva com'e' e `Storico::leggi_anni()` lo rifiuta.
			$anni = array();
		}

		$mostrato = isset( $_POST[ self::CAMPO_MOSTRATO ] ) && is_string( $_POST[ self::CAMPO_MOSTRATO ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_MOSTRATO ] ) ) : null;

		return Storico::salva( $scelta, $anni, $mostrato, get_current_user_id(), current_datetime() );
	}

	/**
	 * Chiave del motivo di rifiuto, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return self::AZIONE . '_rifiuto_' . get_current_user_id();
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
