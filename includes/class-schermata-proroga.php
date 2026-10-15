<?php
/**
 * La schermata della proroga per disservizio (ALBO-31).
 *
 * Chi pubblica indica il primo e l'ultimo giorno sotto soglia e gli estremi
 * dell'attestazione, guarda l'anteprima degli atti che cambieranno, e poi
 * dispone la proroga. Sotto, l'elenco delle proroghe gia' disposte. La
 * schermata non decide niente: i controlli e le scritture sono di `Passaggi`,
 * gli stessi della funzione per chi scrive codice.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, schermata e invii della proroga.
 */
final class SchermataProroga {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-proroga';

	/**
	 * Azione dell'invio, e del suo gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_proroga';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_proroga_gettone';

	/**
	 * Nome del campo del primo giorno sotto soglia.
	 *
	 * @var string
	 */
	const CAMPO_DAL = 'albo_pretorio_proroga_dal';

	/**
	 * Nome del campo dell'ultimo giorno sotto soglia, facoltativo.
	 *
	 * @var string
	 */
	const CAMPO_AL = 'albo_pretorio_proroga_al';

	/**
	 * Nome del campo dell'attestazione.
	 *
	 * @var string
	 */
	const CAMPO_ATTESTAZIONE = 'albo_pretorio_proroga_attestazione';

	/**
	 * Nome del pulsante che sceglie fra anteprima e proroga.
	 *
	 * @var string
	 */
	const CAMPO_MODO = 'albo_pretorio_proroga_modo';

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
			__( 'Proroga per disservizio', 'albo-pretorio-pa' ),
			__( 'Proroga per disservizio', 'albo-pretorio-pa' ),
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
			wp_die( esc_html__( 'Non si possiede il permesso di disporre la proroga per disservizio.', 'albo-pretorio-pa' ), 403 );
		}

		$esito = self::preleva_esito();

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Proroga per disservizio', 'albo-pretorio-pa' ) );
		printf(
			'<p>%s</p><p>%s</p>',
			esc_html__( 'Le linee guida AgID sulla pubblicita\' legale contano un giorno di pubblicazione solo se l\'albo e\' rimasto consultabile per almeno dodici ore in quel giorno. Per ogni giorno sotto questa soglia gli atti che erano in pubblicazione restano esposti un giorno in piu\', sulla base di un\'attestazione del responsabile della pubblicazione o di un suo delegato.', 'albo-pretorio-pa' ),
			esc_html__( 'Indicare i giorni gia\' trascorsi e gli estremi dell\'attestazione. Numero e data di inizio degli atti non cambiano; il giorno della proroga non conta, come il giorno di pubblicazione. La proroga non si annulla: un errore si corregge con una defissione anticipata.', 'albo-pretorio-pa' )
		);

		$valori = array(
			'dal'          => '',
			'al'           => '',
			'attestazione' => '',
		);

		if ( null !== $esito ) {
			$valori = array_merge( $valori, array_intersect_key( $esito, $valori ) );

			if ( isset( $esito['errore'] ) ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( (string) $esito['errore'] ) );
			}

			if ( isset( $esito['disposta'] ) ) {
				printf(
					'<div class="notice notice-success"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %d: numero di atti prorogati. */
							_n( 'Proroga disposta: %d atto prorogato.', 'Proroga disposta: %d atti prorogati.', (int) $esito['disposta'], 'albo-pretorio-pa' ),
							(int) $esito['disposta']
						)
					)
				);
				$valori = array_fill_keys( array_keys( $valori ), '' );
			}

			if ( isset( $esito['anteprima'] ) && is_array( $esito['anteprima'] ) ) {
				self::anteprima( $esito['anteprima'] );
			}
		}

		self::modulo( $valori, null !== $esito && isset( $esito['anteprima'] ) );
		self::elenco();

		echo '</div>';
	}

	/**
	 * L'elenco degli atti che la proroga toccherebbe.
	 *
	 * @param array<int|string, array<string, mixed>> $atti Atti toccati.
	 */
	private static function anteprima( array $atti ): void {
		printf( '<h2>%s</h2>', esc_html__( 'Anteprima: niente e\' ancora stato scritto', 'albo-pretorio-pa' ) );

		if ( array() === $atti ) {
			printf( '<p>%s</p>', esc_html__( 'Nessun atto era in pubblicazione nei giorni indicati: la proroga si puo\' disporre lo stesso, e registra i giorni attestati.', 'albo-pretorio-pa' ) );
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Numero', 'albo-pretorio-pa' ), __( 'Oggetto', 'albo-pretorio-pa' ), __( 'Fine attuale', 'albo-pretorio-pa' ), __( 'Giorni in piu\'', 'albo-pretorio-pa' ), __( 'Fine nuova', 'albo-pretorio-pa' ) ) as $titolo ) {
			printf( '<th scope="col">%s</th>', esc_html( $titolo ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $atti as $atto ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$d</td><td>%5$s</td></tr>',
				esc_html( (string) $atto['numero'] ),
				esc_html( (string) $atto['oggetto'] ),
				esc_html( Proroga::leggibile( (string) $atto['fine'] ) ),
				(int) $atto['giorni'],
				esc_html( Proroga::leggibile( (string) $atto['nuova'] ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Il modulo, con i valori dell'ultimo invio dello stesso utente.
	 *
	 * @param array<string, string> $valori   Valori da riproporre.
	 * @param bool                  $disponi  Se mostrare il pulsante che dispone la proroga.
	 */
	private static function modulo( array $valori, bool $disponi ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::AZIONE ) );
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE );
		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="date" id="%1$s" name="%1$s" value="%3$s" required /></p>',
			esc_attr( self::CAMPO_DAL ),
			esc_html__( 'Primo giorno sotto le dodici ore', 'albo-pretorio-pa' ),
			esc_attr( $valori['dal'] )
		);
		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="date" id="%1$s" name="%1$s" value="%3$s" /></p>',
			esc_attr( self::CAMPO_AL ),
			esc_html__( 'Ultimo giorno sotto le dodici ore, se piu\' di uno di seguito', 'albo-pretorio-pa' ),
			esc_attr( $valori['al'] )
		);
		printf(
			'<p><label for="%1$s">%2$s</label><br /><textarea id="%1$s" name="%1$s" rows="3" cols="60" required>%3$s</textarea></p>',
			esc_attr( self::CAMPO_ATTESTAZIONE ),
			esc_html__( 'Attestazione del disservizio (per esempio gli estremi del documento protocollato)', 'albo-pretorio-pa' ),
			esc_textarea( $valori['attestazione'] )
		);
		printf(
			'<p><button type="submit" class="button" name="%1$s" value="anteprima">%2$s</button> ',
			esc_attr( self::CAMPO_MODO ),
			esc_html__( 'Anteprima', 'albo-pretorio-pa' )
		);

		if ( $disponi ) {
			printf(
				'<button type="submit" class="button button-primary" name="%1$s" value="disponi">%2$s</button>',
				esc_attr( self::CAMPO_MODO ),
				esc_html__( 'Disponi la proroga', 'albo-pretorio-pa' )
			);
		}

		echo '</p></form>';
	}

	/**
	 * Le proroghe gia' disposte.
	 */
	private static function elenco(): void {
		printf( '<h2>%s</h2>', esc_html__( 'Proroghe disposte', 'albo-pretorio-pa' ) );

		$disposte = Proroga::disposte();

		if ( null === $disposte ) {
			printf( '<p>%s</p>', esc_html__( 'Il registro delle modifiche non si legge.', 'albo-pretorio-pa' ) );
			return;
		}

		if ( array() === $disposte ) {
			printf( '<p>%s</p>', esc_html__( 'Nessuna proroga disposta finora.', 'albo-pretorio-pa' ) );
			return;
		}

		echo '<ul>';

		foreach ( $disposte as $voce ) {
			$utente = get_userdata( (int) $voce['utente'] );
			$giorni = array_filter( explode( ',', (string) ( $voce['dettagli']['giorni'] ?? '' ) ) );
			$atti   = array_filter( explode( ',', (string) ( $voce['dettagli']['atti'] ?? '' ) ) );

			printf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: 1: data e ora, 2: chi, 3: giorni, 4: numero di atti, 5: attestazione. */
						__( '%1$s, %2$s: giorni %3$s, %4$d atti prorogati. Attestazione: %5$s', 'albo-pretorio-pa' ),
						wp_date( 'd/m/Y H:i', $voce['istante']->getTimestamp(), wp_timezone() ),
						$utente ? $utente->display_name : __( 'utente non piu\' presente', 'albo-pretorio-pa' ),
						implode( ', ', array_map( array( Proroga::class, 'leggibile' ), $giorni ) ),
						count( $atti ),
						(string) $voce['motivazione']
					)
				)
			);
		}

		echo '</ul>';
	}

	/**
	 * Aggancio a `admin_post_` della schermata: anteprima o proroga, poi di nuovo la schermata.
	 */
	public static function da_invio(): void {
		self::elabora();

		wp_safe_redirect( self::indirizzo() );
		exit;
	}

	/**
	 * Controlla il gettone, legge i campi e chiama la proroga o la sua anteprima.
	 *
	 * Il permesso lo controlla `Passaggi`, che e' l'unico controllo che conta.
	 *
	 * @return array<string, mixed> L'esito lasciato alla schermata.
	 */
	public static function elabora(): array {
		$gettone = isset( $_POST[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) ) : '';

		if ( ! wp_verify_nonce( $gettone, self::AZIONE ) ) {
			return self::lascia( array( 'errore' => __( 'Nessuna proroga disposta: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) ) );
		}

		$valori = array(
			'dal'          => isset( $_POST[ self::CAMPO_DAL ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_DAL ] ) ) : '',
			'al'           => isset( $_POST[ self::CAMPO_AL ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_AL ] ) ) : '',
			'attestazione' => isset( $_POST[ self::CAMPO_ATTESTAZIONE ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ self::CAMPO_ATTESTAZIONE ] ) ) : '',
		);
		$modo   = isset( $_POST[ self::CAMPO_MODO ] ) ? sanitize_key( wp_unslash( $_POST[ self::CAMPO_MODO ] ) ) : '';
		$giorni = self::giorni( $valori['dal'], $valori['al'] );

		if ( is_wp_error( $giorni ) ) {
			return self::lascia( $valori + array( 'errore' => $giorni->get_error_message() ) );
		}

		$richiesta = array(
			'giorni'       => $giorni,
			'attestazione' => $valori['attestazione'],
		);

		if ( 'disponi' === $modo ) {
			$esito = Passaggi::proroga( $richiesta );

			return self::lascia( is_wp_error( $esito ) ? $valori + array( 'errore' => $esito->get_error_message() ) : array( 'disposta' => count( $esito['atti'] ) ) );
		}

		$esito = Passaggi::anteprima_proroga( $richiesta );

		return self::lascia( $valori + ( is_wp_error( $esito ) ? array( 'errore' => $esito->get_error_message() ) : array( 'anteprima' => $esito['atti'] ) ) );
	}

	/**
	 * I giorni dal primo all'ultimo, compresi.
	 *
	 * @param string $dal Primo giorno.
	 * @param string $al  Ultimo giorno, o vuoto per uno solo.
	 * @return array<int, string>|\WP_Error
	 */
	private static function giorni( string $dal, string $al ) {
		$formato = '!Y-m-d';
		$inizio  = \DateTimeImmutable::createFromFormat( $formato, $dal, wp_timezone() );
		$fine    = '' === $al ? $inizio : \DateTimeImmutable::createFromFormat( $formato, $al, wp_timezone() );

		if ( false === $inizio || false === $fine || $inizio->format( 'Y-m-d' ) !== $dal || ( '' !== $al && $fine->format( 'Y-m-d' ) !== $al ) ) {
			return new \WP_Error( 'albo_proroga_giorno_non_valido', __( 'Nessuna proroga disposta: indicare il primo giorno, ed eventualmente l\'ultimo, come date valide.', 'albo-pretorio-pa' ) );
		}

		if ( $fine < $inizio ) {
			return new \WP_Error( 'albo_proroga_giorno_non_valido', __( 'Nessuna proroga disposta: l\'ultimo giorno viene prima del primo.', 'albo-pretorio-pa' ) );
		}

		$giorni   = array();
		$corrente = $inizio;

		$quanti = 0;

		// Uno oltre il massimo basta perche' la proroga rifiuti con il suo motivo.
		while ( $corrente <= $fine && $quanti <= Proroga::GIORNI_MASSIMI ) {
			$giorni[] = $corrente->format( 'Y-m-d' );
			$corrente = $corrente->add( new \DateInterval( 'P1D' ) );
			++$quanti;
		}

		return $giorni;
	}

	/**
	 * Lascia l'esito alla schermata, per utente.
	 *
	 * @param array<string, mixed> $esito Esito.
	 * @return array<string, mixed>
	 */
	private static function lascia( array $esito ): array {
		set_transient( self::chiave_esito(), $esito, 5 * MINUTE_IN_SECONDS );

		return $esito;
	}

	/**
	 * Chiave dell'esito, per utente.
	 *
	 * @return string
	 */
	private static function chiave_esito(): string {
		return 'albo_pretorio_proroga_esito_' . get_current_user_id();
	}

	/**
	 * Preleva l'esito dell'ultimo invio, e lo consuma.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function preleva_esito(): ?array {
		$esito = get_transient( self::chiave_esito() );

		if ( ! is_array( $esito ) ) {
			return null;
		}

		delete_transient( self::chiave_esito() );

		return $esito;
	}
}
