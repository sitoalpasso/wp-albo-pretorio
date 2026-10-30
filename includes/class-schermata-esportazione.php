<?php
/**
 * La schermata dell'esportazione per la conservazione (ALBO-17).
 *
 * Chi puo' consultare gli scaduti sceglie un anno del registro e scarica
 * l'archivio. Il file si costruisce nella cartella temporanea del server, si
 * consegna e si cancella subito.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata, invio e consegna dell'esportazione.
 */
final class SchermataEsportazione {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-esportazione';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_esportazione';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_esportazione_gettone';

	/**
	 * Nome del campo dell'anno.
	 *
	 * @var string
	 */
	const CAMPO_ANNO = 'albo_pretorio_esportazione_anno';

	/**
	 * Chi consegna il file: sostituibile nelle prove.
	 *
	 * @var callable|null
	 */
	private static $emettitore = null;

	/**
	 * Aggancio a `admin_menu`: accanto alla consultazione degli scaduti.
	 */
	public static function menu(): void {
		$permesso = SchermataScaduti::permesso();

		if ( null === $permesso || ! post_type_exists( TIPO ) ) {
			return;
		}

		$titolo = __( 'Esportazione per la conservazione', 'albo-pretorio-pa' );
		$voce   = __( 'Esportazione', 'albo-pretorio-pa' );
		$tipo   = get_post_type_object( TIPO );
		$padre  = null !== $tipo && current_user_can( $tipo->cap->edit_posts ) ? 'edit.php?post_type=' . TIPO : SchermataScaduti::PAGINA;

		add_submenu_page( $padre, $titolo, $voce, $permesso, self::PAGINA, array( self::class, 'mostra' ) );
	}

	/**
	 * Il contenuto della schermata.
	 */
	public static function mostra(): void {
		$permesso = SchermataScaduti::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			wp_die( esc_html__( 'Non si possiede il permesso di esportare gli atti.', 'albo-pretorio-pa' ), 403 );
		}

		$motivo = self::preleva_rifiuto();
		$anni   = Esportazione::anni();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Esportazione per la conservazione', 'albo-pretorio-pa' ) );

		if ( null !== $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}

		printf(
			'<p>%s</p>',
			esc_html__( 'Si scarica un archivio ZIP con tutti gli atti numerati nell\'anno scelto, in corso, scaduti, tolti e annullati: i dati di ciascun atto (atti.json, e atti.csv da aprire con un foglio di calcolo), tutti i documenti, anche le versioni sostituite da un oscuramento, la storia di ogni atto dal registro delle modifiche e l\'impronta di ogni file (impronte.sha256). E\' il materiale da consegnare al sistema di conservazione dell\'amministrazione: l\'albo non conserva da se\'.', 'albo-pretorio-pa' )
		);
		printf(
			'<p>%s</p>',
			esc_html__( 'L\'archivio contiene dati personali, anche di atti scaduti: va custodito come gli originali. Ogni esportazione resta nel registro delle modifiche, con chi l\'ha fatta, quando e l\'impronta dell\'archivio.', 'albo-pretorio-pa' )
		);

		if ( null === $anni ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div></div>', esc_html__( 'Il registro dei numeri non si legge in questo momento: gli anni da esportare non si conoscono. Riprovare piu\' tardi.', 'albo-pretorio-pa' ) );
			return;
		}

		if ( array() === $anni ) {
			printf( '<p>%s</p></div>', esc_html__( 'Non c\'e\' ancora nessun atto numerato da esportare.', 'albo-pretorio-pa' ) );
			return;
		}

		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );
		printf( '<p><label for="%1$s">%2$s</label> <select id="%1$s" name="%1$s">', esc_attr( self::CAMPO_ANNO ), esc_html__( 'Anno del registro', 'albo-pretorio-pa' ) );

		foreach ( $anni as $anno ) {
			printf( '<option value="%1$d">%1$d</option>', (int) $anno );
		}

		echo '</select></p>';
		printf( '<p><button type="submit" class="button button-primary">%s</button></p></form></div>', esc_html__( 'Scarica l\'esportazione', 'albo-pretorio-pa' ) );
	}

	/**
	 * Aggancio a `admin_post_` della schermata: consegna l'archivio, o torna alla schermata con il motivo.
	 */
	public static function da_invio(): void {
		$esito = self::elabora();

		if ( is_wp_error( $esito ) ) {
			wp_safe_redirect( add_query_arg( 'page', self::PAGINA, admin_url( 'admin.php' ) ) );
			exit;
		}

		exit;
	}

	/**
	 * Controlla gettone, permesso e anno, costruisce l'archivio, lo consegna e lo cancella.
	 *
	 * @return array{percorso: string, atti: int, documenti: int, impronta: string}|\WP_Error
	 */
	public static function elabora() {
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) && is_string( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( ! wp_verify_nonce( $gettone, self::AZIONE ) ) {
			return self::rifiuta( new \WP_Error( 'albo_esportazione_gettone', __( 'Niente e\' stato esportato: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) ) );
		}

		$scelto = isset( $_POST[ self::CAMPO_ANNO ] ) && is_string( $_POST[ self::CAMPO_ANNO ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_ANNO ] ) ) : '';
		$anno   = 1 === preg_match( '/\A[0-9]{4}\z/', $scelto ) ? (int) $scelto : 0;

		if ( 0 === $anno ) {
			return self::rifiuta( new \WP_Error( 'albo_esportazione_anno', __( 'Niente e\' stato esportato: l\'anno va scelto dall\'elenco.', 'albo-pretorio-pa' ) ) );
		}

		$esito = Esportazione::costruisci( $anno );

		if ( is_wp_error( $esito ) ) {
			return self::rifiuta( $esito );
		}

		try {
			self::consegna( $esito['percorso'], 'albo-esportazione-' . $anno . '.zip' );
		} finally {
			wp_delete_file( $esito['percorso'] );
		}

		return $esito;
	}

	/**
	 * Sostituisce chi consegna il file, nelle prove.
	 *
	 * @param callable|null $emettitore Riceve percorso e nome; nullo per tornare alla consegna vera.
	 */
	public static function fissa_emettitore( ?callable $emettitore ): void {
		self::$emettitore = $emettitore;
	}

	/**
	 * Consegna il file al browser, senza memoria.
	 *
	 * @param string $percorso File.
	 * @param string $nome     Nome proposto.
	 */
	private static function consegna( string $percorso, string $nome ): void {
		if ( null !== self::$emettitore ) {
			( self::$emettitore )( $percorso, $nome );
			return;
		}

		// La consegna arriva in fondo anche se il browser chiude prima, cosi' che la cancellazione che la segue giri.
		ignore_user_abort( true );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Cache-Control: ' . PaginaPubblica::CACHE_CONTROL );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
		header( 'Content-Length: ' . (string) filesize( $percorso ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $percorso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- consegna del file appena costruito.
	}

	/**
	 * Conserva il motivo del rifiuto per la schermata.
	 *
	 * @param \WP_Error $errore Rifiuto.
	 * @return \WP_Error
	 */
	private static function rifiuta( \WP_Error $errore ): \WP_Error {
		set_transient( self::chiave_rifiuto(), $errore->get_error_message(), 5 * MINUTE_IN_SECONDS );

		return $errore;
	}

	/**
	 * Chiave del motivo di rifiuto, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return 'albo_pretorio_esportazione_rifiuto_' . get_current_user_id();
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
