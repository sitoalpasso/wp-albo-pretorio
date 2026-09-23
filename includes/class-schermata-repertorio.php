<?php
/**
 * La schermata della numerazione: dove si dichiara la partenza (ALBO-08).
 *
 * Mostra a chi pubblica quale numero ricevera' il prossimo atto e, nel primo
 * anno d'uso, offre il campo per dichiarare l'ultimo numero gia' usato in un
 * registro precedente. Il campo sparisce al primo numero assegnato, e un invio
 * costruito a mano dopo quel momento lo rifiuta comunque `Repertorio`.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata, invio e avviso della numerazione.
 */
final class SchermataRepertorio {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-numerazione';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_numerazione';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_numerazione_gettone';

	/**
	 * Nome del campo dell'ultimo numero gia' usato.
	 *
	 * @var string
	 */
	const CAMPO_ULTIMO = 'albo_pretorio_numerazione_ultimo';

	/**
	 * Il permesso che apre la schermata: quello di pubblicare atti.
	 *
	 * @return string|null Nome del permesso derivato, o nullo se il meccanismo comune non lo fornisce.
	 */
	public static function permesso(): ?string {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || empty( $mappa['publish_posts'] ) ) {
			return null;
		}

		return (string) $mappa['publish_posts'];
	}

	/**
	 * Aggancio a `admin_menu`: la voce sotto il menu degli atti.
	 */
	public static function menu(): void {
		$permesso = self::permesso();

		if ( null === $permesso || ! post_type_exists( TIPO ) ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=' . TIPO,
			__( 'Numerazione degli atti', 'albo-pretorio-pa' ),
			__( 'Numerazione', 'albo-pretorio-pa' ),
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
		$permesso = self::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			wp_die( esc_html__( 'Non si possiede il permesso di vedere la numerazione degli atti.', 'albo-pretorio-pa' ), 403 );
		}

		$anno     = Repertorio::anno_di( current_datetime() );
		$prossimo = Repertorio::prossimo( $anno );
		$riga     = Repertorio::anno( $anno );
		$motivo   = self::preleva_rifiuto();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Numerazione degli atti', 'albo-pretorio-pa' ) );

		if ( null !== $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}

		if ( is_wp_error( $prossimo ) ) {
			printf( '<p>%s</p>', esc_html( $prossimo->get_error_message() ) );
		} else {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: numero, 2: anno. */
						__( 'Il prossimo atto pubblicato ricevera\' il numero %1$d/%2$d.', 'albo-pretorio-pa' ),
						$prossimo,
						$anno
					)
				)
			);
		}

		if ( null !== $riga && Repertorio::APERTURA_DICHIARAZIONE === $riga['apertura'] ) {
			$utente = get_userdata( $riga['dichiarato_da'] );

			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: anno, 2: ultimo numero dichiarato, 3: nome di chi ha dichiarato, 4: data e ora. */
						__( 'Partenza del %1$d dichiarata: ultimo numero gia\' usato %2$d, da %3$s il %4$s.', 'albo-pretorio-pa' ),
						$anno,
						$riga['partenza'],
						$utente ? $utente->display_name : __( 'utente non piu\' presente', 'albo-pretorio-pa' ),
						null === $riga['dichiarato_il'] ? '' : get_date_from_gmt( $riga['dichiarato_il'], 'd/m/Y H:i' )
					)
				)
			);
		}

		if ( self::dichiarabile( $anno, $prossimo, $riga ) ) {
			self::modulo( $anno );
		}

		echo '</div>';
	}

	/**
	 * Vero se la partenza dell'anno si puo' ancora dichiarare o correggere.
	 *
	 * Rispecchia le condizioni di `Repertorio::dichiara`, che resta il solo
	 * controllo che conta: qui si decide soltanto se mostrare il campo.
	 *
	 * @param int                      $anno     Anno.
	 * @param int|\WP_Error            $prossimo Prossimo numero, o il motivo per cui manca.
	 * @param array<string,mixed>|null $riga Riga dell'anno.
	 * @return bool
	 */
	private static function dichiarabile( int $anno, $prossimo, ?array $riga ): bool {
		if ( is_wp_error( $prossimo ) ) {
			return 'albo_repertorio_partenza_non_dichiarata' === $prossimo->get_error_code();
		}

		return null !== $riga
			&& $anno === $riga['anno']
			&& Repertorio::APERTURA_DICHIARAZIONE === $riga['apertura']
			&& $riga['ultimo'] === $riga['partenza'];
	}

	/**
	 * Il modulo della dichiarazione, senza nessun valore precompilato.
	 *
	 * @param int $anno Anno.
	 */
	private static function modulo( int $anno ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );
		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="text" inputmode="numeric" id="%1$s" name="%1$s" value="" required /></p>',
			esc_attr( self::CAMPO_ULTIMO ),
			esc_html(
				sprintf(
					/* translators: %d: anno. */
					__( 'Ultimo numero gia\' usato nel %d nel registro precedente. Se non ce n\'e\' nessuno, scrivere 0.', 'albo-pretorio-pa' ),
					$anno
				)
			)
		);
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'La dichiarazione si puo\' correggere finche\' nessun atto e\' stato numerato. Dopo il primo numero non si cambia piu\'.', 'albo-pretorio-pa' )
		);
		submit_button( __( 'Dichiara la partenza', 'albo-pretorio-pa' ) );
		echo '</form>';
	}

	/**
	 * Aggancio a `admin_post_` della schermata: registra la dichiarazione e torna alla schermata.
	 */
	public static function da_invio(): void {
		self::elabora();

		wp_safe_redirect( self::indirizzo() );
		exit;
	}

	/**
	 * Controlla gettone e permesso, poi dichiara. Un rifiuto lascia il motivo.
	 */
	private static function elabora(): void {
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( ! wp_verify_nonce( $gettone, self::AZIONE ) ) {
			self::rifiuta( __( 'Partenza non dichiarata: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
			return;
		}

		$permesso = self::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			self::rifiuta( __( 'Partenza non dichiarata: non si possiede il permesso di pubblicare atti.', 'albo-pretorio-pa' ) );
			return;
		}

		$ultimo = isset( $_POST[ self::CAMPO_ULTIMO ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_ULTIMO ] ) ) : '';
		$esito  = Repertorio::dichiara( $ultimo, get_current_user_id(), current_datetime() );

		if ( is_wp_error( $esito ) ) {
			self::rifiuta( $esito->get_error_message() );
			return;
		}

		delete_transient( self::chiave_rifiuto() );
	}

	/**
	 * Lascia il motivo del rifiuto a chi ha inviato.
	 *
	 * @param string $motivo Motivo.
	 */
	private static function rifiuta( string $motivo ): void {
		set_transient( self::chiave_rifiuto(), $motivo, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Chiave del motivo di rifiuto, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return 'albo_pretorio_numerazione_rifiuto_' . get_current_user_id();
	}

	/**
	 * Preleva il motivo dell'ultimo rifiuto, e lo consuma.
	 *
	 * @return string|null
	 */
	public static function preleva_rifiuto(): ?string {
		$motivo = get_transient( self::chiave_rifiuto() );

		if ( false === $motivo ) {
			return null;
		}

		delete_transient( self::chiave_rifiuto() );

		return (string) $motivo;
	}

	/**
	 * Aggancio a `admin_notices`: l'avviso a chi pubblica, finche' l'anno non numera.
	 */
	public static function avviso(): void {
		$permesso = self::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			return;
		}

		$prossimo = Repertorio::prossimo( Repertorio::anno_di( current_datetime() ) );

		if ( ! is_wp_error( $prossimo ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( $prossimo->get_error_message() ),
			esc_url( self::indirizzo() ),
			esc_html__( 'Apri la numerazione degli atti', 'albo-pretorio-pa' )
		);
	}
}
