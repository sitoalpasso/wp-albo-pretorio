<?php
/**
 * Le copie nella schermata dello storico pubblico: metterle e toglierle (ALBO-36).
 *
 * Si entra dalla consultazione riservata degli atti scaduti, con il
 * collegamento "Metti nello storico" accanto a un atto che puo' entrare. Il
 * modulo chiede il documento oscurato, caricato a parte, l'oggetto della
 * copia e la conferma del controllo. L'elenco delle copie nella schermata
 * dello storico ha per ciascuna il modulo per toglierla, con la causa e il
 * motivo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Moduli, invii ed elenco delle copie.
 */
final class SchermataCopie {

	/**
	 * Azione dell'invio che mette una copia.
	 *
	 * @var string
	 */
	const AZIONE_METTI = SchermataStorico::AZIONE . '_metti';

	/**
	 * Azione dell'invio che toglie una copia.
	 *
	 * @var string
	 */
	const AZIONE_TOGLI = SchermataStorico::AZIONE . '_togli';

	/**
	 * Nome del campo del gettone, per entrambi i moduli.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_copia_gettone';

	/**
	 * Nome del parametro e del campo dell'atto.
	 *
	 * @var string
	 */
	const CAMPO_ATTO = 'albo_copia_atto';

	/**
	 * Nome del campo del documento oscurato.
	 *
	 * @var string
	 */
	const CAMPO_DOCUMENTO = 'albo_copia_documento';

	/**
	 * Nome del campo dell'oggetto della copia.
	 *
	 * @var string
	 */
	const CAMPO_OGGETTO = 'albo_copia_oggetto';

	/**
	 * Nome della casella della conferma.
	 *
	 * @var string
	 */
	const CAMPO_CONFERMA = 'albo_copia_conferma';

	/**
	 * Nome del campo della copia da togliere.
	 *
	 * @var string
	 */
	const CAMPO_COPIA = 'albo_copia_numero';

	/**
	 * Nome del campo della causa dell'uscita.
	 *
	 * @var string
	 */
	const CAMPO_CAUSA = 'albo_copia_causa';

	/**
	 * Nome del campo del motivo dell'uscita.
	 *
	 * @var string
	 */
	const CAMPO_MOTIVO = 'albo_copia_motivo';

	/**
	 * Chi agisce ha il permesso dello storico.
	 *
	 * @return bool
	 */
	private static function permesso(): bool {
		$permesso = SchermataStorico::permesso();

		return null !== $permesso && current_user_can( $permesso );
	}

	/**
	 * Il collegamento per la consultazione riservata, accanto a un atto scaduto.
	 *
	 * Soltanto a chi ha il permesso dello storico, con lo storico acceso, per
	 * un atto che puo' entrare. Se l'atto ha gia' una copia, lo dice.
	 *
	 * @param int $atto Atto.
	 */
	public static function collegamento( int $atto ): void {
		if ( ! Storico::pronto() || ! self::permesso() || ! Storico::acceso() || true !== CopieStorico::ammissibile( $atto ) ) {
			return;
		}

		$presenti = CopieStorico::presenti_di( $atto );

		if ( null === $presenti ) {
			return;
		}

		if ( array() !== $presenti ) {
			$fine = conformita_core_fine_pubblicazione( $presenti[0] );

			printf(
				'<p class="albo-copia">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: data di fine della copia. */
						__( 'Una copia oscurata e\' nello storico pubblico fino al %s.', 'albo-pretorio-pa' ),
						is_string( $fine ) ? self::data( $fine ) : __( 'fine non leggibile', 'albo-pretorio-pa' )
					)
				)
			);
			return;
		}

		printf(
			'<p class="albo-copia"><a href="%1$s">%2$s</a></p>',
			esc_url( add_query_arg( self::CAMPO_ATTO, $atto, SchermataStorico::indirizzo() ) ),
			esc_html__( 'Metti nello storico una copia oscurata', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Il modulo che mette nello storico la copia di un atto.
	 *
	 * @param int $atto Atto.
	 */
	public static function modulo_ingresso( int $atto ): void {
		printf( '<h2>%s</h2>', esc_html__( 'Mettere un atto nello storico', 'albo-pretorio-pa' ) );

		if ( ! Storico::acceso() ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html__( 'Lo storico pubblico e\' spento: nessuna copia entra finche\' non lo si accende.', 'albo-pretorio-pa' ) );
			return;
		}

		$ammesso = CopieStorico::ammissibile( $atto );

		if ( is_wp_error( $ammesso ) ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $ammesso->get_error_message() ) );
			return;
		}

		$numero = Repertorio::di( $atto );
		$fine   = conformita_core_fine_pubblicazione( $atto );
		$anni   = (int) Storico::anni();

		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: numero, 2: anno, 3: data di inizio, 4: data di fine. */
					__( 'Atto n. %1$d/%2$d, pubblicato all\'albo dal %3$s al %4$s.', 'albo-pretorio-pa' ),
					null === $numero ? 0 : (int) $numero['numero'],
					null === $numero ? 0 : (int) $numero['anno'],
					get_the_date( 'd/m/Y', $atto ),
					is_string( $fine ) ? self::data( $fine ) : __( 'fine non leggibile', 'albo-pretorio-pa' )
				)
			)
		);
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: anni. */
					_n(
						'Nello storico entra una copia, mai il documento originale: si prepara a parte togliendo i dati personali, e si carica qui. L\'originale resta nella consultazione riservata. La copia resta nello storico per %d anno da oggi, e si puo\' togliere prima in ogni momento.',
						'Nello storico entra una copia, mai il documento originale: si prepara a parte togliendo i dati personali, e si carica qui. L\'originale resta nella consultazione riservata. La copia resta nello storico per %d anni da oggi, e si puo\' togliere prima in ogni momento.',
						$anni,
						'albo-pretorio-pa'
					),
					$anni
				)
			)
		);

		self::documenti_originali( $atto );

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE_METTI ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::CAMPO_ATTO ), (int) $atto );
		wp_nonce_field( self::AZIONE_METTI . '_' . $atto, self::CAMPO_GETTONE );

		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br /><input type="file" id="%1$s" name="%1$s" required="required" /></p>',
			esc_attr( self::CAMPO_DOCUMENTO ),
			esc_html__( 'Documento oscurato', 'albo-pretorio-pa' )
		);
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br /><span class="description" id="%1$s_aiuto">%3$s</span><br /><textarea id="%1$s" name="%1$s" rows="3" cols="80" maxlength="%4$d" required="required" aria-describedby="%1$s_aiuto">%5$s</textarea></p>',
			esc_attr( self::CAMPO_OGGETTO ),
			esc_html__( 'Oggetto della copia', 'albo-pretorio-pa' ),
			esc_html__( 'E\' l\'oggetto dell\'atto, da rileggere: va riscritto senza nomi o altri dati che identificano le persone. Sullo storico compare questo, non quello originale.', 'albo-pretorio-pa' ),
			(int) CopieStorico::OGGETTO_MASSIMO,
			esc_textarea( get_the_title( $atto ) )
		);
		printf(
			'<p><input type="checkbox" id="%1$s" name="%1$s" value="1" required="required" /> <label for="%1$s">%2$s</label></p>',
			esc_attr( self::CAMPO_CONFERMA ),
			esc_html__( 'Ho controllato il documento caricato e l\'oggetto: non contengono dati che identificano le persone, e in nessun caso dati genetici, biometrici o relativi alla salute.', 'albo-pretorio-pa' )
		);

		submit_button( __( 'Metti nello storico', 'albo-pretorio-pa' ) );
		echo '</form>';
	}

	/**
	 * I documenti originali dell'atto, da aprire per preparare la copia.
	 *
	 * @param int $atto Atto.
	 */
	private static function documenti_originali( int $atto ): void {
		$documenti  = array();
		$principale = DocumentiAtto::principale( $atto );

		if ( null !== $principale ) {
			$documenti[] = $principale;
		}

		$documenti = array_merge( $documenti, DocumentiAtto::ulteriori( $atto ) );

		if ( array() === $documenti ) {
			return;
		}

		printf( '<p>%s</p><ul>', esc_html__( 'Documenti originali, da cui preparare la copia:', 'albo-pretorio-pa' ) );

		foreach ( $documenti as $documento ) {
			$indirizzo = conformita_core_indirizzo_consegna_amministrativa( $documento );

			echo '<li>';

			if ( is_string( $indirizzo ) ) {
				printf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $indirizzo ), esc_html( get_the_title( $documento ) ) );
			} else {
				echo esc_html( get_the_title( $documento ) );
			}

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * L'elenco delle copie nello storico, ciascuna con il modulo per toglierla.
	 */
	public static function elenco(): void {
		printf( '<h2>%s</h2>', esc_html__( 'Copie nello storico', 'albo-pretorio-pa' ) );

		if ( ! Storico::acceso() ) {
			printf( '<p>%s</p>', esc_html__( 'Lo storico e\' spento: le copie qui sotto restano salvate, ma non sono sul sito.', 'albo-pretorio-pa' ) );
		}

		$copie = CopieStorico::presenti();

		if ( null === $copie ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html__( 'Le copie nello storico non si leggono in questo momento: ricaricare la pagina.', 'albo-pretorio-pa' ) );
			return;
		}

		if ( array() === $copie ) {
			printf( '<p>%s</p>', esc_html__( 'Nessuna copia nello storico. Per metterne una, cercare l\'atto nella consultazione degli atti scaduti.', 'albo-pretorio-pa' ) );
			return;
		}

		echo '<table class="widefat striped albo-pretorio-copie"><thead><tr>';

		foreach ( array(
			__( 'Atto', 'albo-pretorio-pa' ),
			__( 'Oggetto della copia', 'albo-pretorio-pa' ),
			__( 'Nello storico', 'albo-pretorio-pa' ),
			__( 'Togliere', 'albo-pretorio-pa' ),
		) as $intestazione ) {
			printf( '<th scope="col">%s</th>', esc_html( $intestazione ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $copie as $copia ) {
			self::riga( $copia );
		}

		echo '</tbody></table>';
	}

	/**
	 * Una riga dell'elenco delle copie.
	 *
	 * @param int $copia Copia.
	 */
	private static function riga( int $copia ): void {
		$dati = CopieStorico::dati( $copia );
		$fine = conformita_core_fine_pubblicazione( $copia );

		printf(
			'<tr data-copia="%1$d"><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>',
			(int) $copia,
			esc_html( null === $dati ? __( 'dati non leggibili', 'albo-pretorio-pa' ) : $dati['numero'] . '/' . $dati['anno'] ),
			esc_html( get_the_title( $copia ) ),
			esc_html(
				sprintf(
					/* translators: 1: data di ingresso, 2: data di fine. */
					__( 'dal %1$s al %2$s', 'albo-pretorio-pa' ),
					null === CopieStorico::giorno_di_ingresso( $copia ) ? __( 'data non leggibile', 'albo-pretorio-pa' ) : self::data( (string) CopieStorico::giorno_di_ingresso( $copia ) ),
					is_string( $fine ) ? self::data( $fine ) : __( 'fine non leggibile', 'albo-pretorio-pa' )
				)
			)
		);

		$causa  = self::CAMPO_CAUSA . '_' . $copia;
		$motivo = self::CAMPO_MOTIVO . '_' . $copia;

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE_TOGLI ) );
		printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( self::CAMPO_COPIA ), (int) $copia );
		wp_nonce_field( self::AZIONE_TOGLI . '_' . $copia, self::CAMPO_GETTONE, false );

		printf( '<p><label for="%1$s">%2$s</label><br /><select id="%1$s" name="%3$s" required="required">', esc_attr( $causa ), esc_html__( 'Causa', 'albo-pretorio-pa' ), esc_attr( self::CAMPO_CAUSA ) );
		printf( '<option value="">%s</option>', esc_html__( 'Scegliere la causa', 'albo-pretorio-pa' ) );

		foreach ( CopieStorico::cause() as $valore => $etichetta ) {
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $valore ), esc_html( $etichetta ) );
		}

		echo '</select></p>';
		printf(
			'<p><label for="%1$s">%2$s</label><br /><textarea id="%1$s" name="%3$s" rows="2" cols="40" required="required"></textarea></p>',
			esc_attr( $motivo ),
			esc_html__( 'Motivo', 'albo-pretorio-pa' ),
			esc_attr( self::CAMPO_MOTIVO )
		);
		submit_button( __( 'Togli dallo storico', 'albo-pretorio-pa' ), 'secondary', 'submit', false );
		echo '</form></td></tr>';
	}

	/**
	 * Aggancio a `admin_post_` dell'ingresso: mette la copia e torna alla schermata.
	 */
	public static function da_metti(): void {
		$esito = self::elabora_metti();
		$torna = SchermataStorico::indirizzo();

		if ( is_wp_error( $esito ) && isset( $_POST[ self::CAMPO_ATTO ] ) && is_string( $_POST[ self::CAMPO_ATTO ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- serve solo a tornare al modulo; il gettone lo verifica elabora_metti().
			$torna = add_query_arg( self::CAMPO_ATTO, absint( wp_unslash( $_POST[ self::CAMPO_ATTO ] ) ), $torna ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- come sopra.
		}

		wp_safe_redirect( $torna );
		exit;
	}

	/**
	 * Aggancio a `admin_post_` dell'uscita: toglie la copia e torna alla schermata.
	 */
	public static function da_togli(): void {
		self::elabora_togli();

		wp_safe_redirect( SchermataStorico::indirizzo() );
		exit;
	}

	/**
	 * Controlla gettone e permesso, poi mette la copia. L'esito resta per la schermata.
	 *
	 * L'origine del documento e' sempre il caricamento: un percorso arrivato
	 * con la richiesta non diventa mai un file da depositare.
	 *
	 * @return int|\WP_Error
	 */
	public static function elabora_metti() {
		$atto    = isset( $_POST[ self::CAMPO_ATTO ] ) && is_string( $_POST[ self::CAMPO_ATTO ] ) ? absint( wp_unslash( $_POST[ self::CAMPO_ATTO ] ) ) : 0;
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) && is_string( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( $atto < 1 || ! wp_verify_nonce( $gettone, self::AZIONE_METTI . '_' . $atto ) ) {
			$esito = new \WP_Error( 'albo_copia_gettone', __( 'Copia non creata: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		} elseif ( ! self::permesso() ) {
			$esito = new \WP_Error( 'albo_copia_non_permesso', __( 'Copia non creata: non si possiede il permesso di gestire lo storico pubblico.', 'albo-pretorio-pa' ) );
		} else {
			$oggetto = isset( $_POST[ self::CAMPO_OGGETTO ] ) && is_string( $_POST[ self::CAMPO_OGGETTO ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_OGGETTO ] ) ) : null;
			$esito   = CopieStorico::metti(
				$atto,
				self::caricato(),
				$oggetto,
				isset( $_POST[ self::CAMPO_CONFERMA ] ) && '1' === $_POST[ self::CAMPO_CONFERMA ],
				'caricamento'
			);
		}

		if ( is_wp_error( $esito ) ) {
			SchermataStorico::ricorda( $esito->get_error_message(), false );
		} else {
			SchermataStorico::ricorda( __( 'La copia e\' nello storico pubblico.', 'albo-pretorio-pa' ), true );
		}

		return $esito;
	}

	/**
	 * Controlla gettone e permesso, poi toglie la copia. L'esito resta per la schermata.
	 *
	 * @return true|\WP_Error
	 */
	public static function elabora_togli() {
		$copia   = isset( $_POST[ self::CAMPO_COPIA ] ) && is_string( $_POST[ self::CAMPO_COPIA ] ) ? absint( wp_unslash( $_POST[ self::CAMPO_COPIA ] ) ) : 0;
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) && is_string( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( $copia < 1 || ! wp_verify_nonce( $gettone, self::AZIONE_TOGLI . '_' . $copia ) ) {
			$esito = new \WP_Error( 'albo_copia_gettone', __( 'Copia non tolta: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		} elseif ( ! self::permesso() ) {
			$esito = new \WP_Error( 'albo_copia_non_permesso', __( 'Copia non tolta: non si possiede il permesso di gestire lo storico pubblico.', 'albo-pretorio-pa' ) );
		} else {
			$causa  = isset( $_POST[ self::CAMPO_CAUSA ] ) && is_string( $_POST[ self::CAMPO_CAUSA ] ) ? sanitize_key( wp_unslash( $_POST[ self::CAMPO_CAUSA ] ) ) : null;
			$motivo = isset( $_POST[ self::CAMPO_MOTIVO ] ) && is_string( $_POST[ self::CAMPO_MOTIVO ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::CAMPO_MOTIVO ] ) ) : null;
			$esito  = CopieStorico::togli( $copia, $causa, $motivo );
		}

		if ( is_wp_error( $esito ) ) {
			SchermataStorico::ricorda( $esito->get_error_message(), false );
		} else {
			SchermataStorico::ricorda( __( 'La copia e\' stata tolta dallo storico pubblico.', 'albo-pretorio-pa' ), true );
		}

		return $esito;
	}

	/**
	 * Il documento caricato, con i soli campi che servono, tutti testi o numeri.
	 *
	 * @return array<string, mixed>
	 */
	private static function caricato(): array {
		$file = isset( $_FILES[ self::CAMPO_DOCUMENTO ] ) && is_array( $_FILES[ self::CAMPO_DOCUMENTO ] ) ? $_FILES[ self::CAMPO_DOCUMENTO ] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- il gettone lo verifica elabora_metti(); i campi si ripuliscono qui sotto.

		if ( ! isset( $file['name'], $file['tmp_name'], $file['error'] ) || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) ) {
			return array();
		}

		return array(
			'name'     => sanitize_file_name( wp_unslash( $file['name'] ) ),
			'type'     => isset( $file['type'] ) && is_string( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '',
			'tmp_name' => $file['tmp_name'],
			'error'    => (int) $file['error'],
			'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
		);
	}

	/**
	 * Una data AAAA-MM-GG come GG/MM/AAAA.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	private static function data( string $data ): string {
		$giorno = \DateTimeImmutable::createFromFormat( '!Y-m-d', $data, wp_timezone() );

		return false === $giorno ? $data : $giorno->format( 'd/m/Y' );
	}
}
