<?php
/**
 * La schermata dell'importazione dal vecchio albo (ALBO-35).
 *
 * Chi pubblica sceglie un backup del plugin "Albo Pretorio On line", fra quelli
 * che quel plugin ha lasciato nella sua cartella sul server oppure caricandolo
 * dal proprio computer, e legge il rapporto di cosa si porterebbe nel nuovo
 * albo. La lettura **non scrive niente**: ne' atti, ne' tipi, ne' numeri, ne'
 * file. Il file caricato si legge dal posto temporaneo in cui lo mette PHP, che
 * lo cancella da se' alla fine della richiesta, e il rapporto vive solo nella
 * pagina che lo mostra.
 *
 * Sotto il rapporto di un backup della cartella c'e' il modulo
 * dell'importazione vera, con la sua conferma esplicita: il lavoro lo fa
 * `Importazione`, a blocchi, e la schermata ne mostra l'avanzamento e il
 * rapporto finale.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, scelta del backup e rapporto dell'importazione a secco.
 */
final class SchermataImportazione {

	/**
	 * Identificativo della pagina.
	 *
	 * @var string
	 */
	const PAGINA = 'albo-pretorio-importazione';

	/**
	 * Azione del gettone di sicurezza.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_importazione_a_secco';

	/**
	 * Nome del campo del gettone.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_importazione_gettone';

	/**
	 * Nome del campo con il backup scelto dalla cartella.
	 *
	 * @var string
	 */
	const CAMPO_PRESENTE = 'albo_pretorio_importazione_presente';

	/**
	 * Nome del campo del file caricato.
	 *
	 * @var string
	 */
	const CAMPO_FILE = 'albo_pretorio_importazione_file';

	/**
	 * La cartella in cui il plugin di provenienza lascia i suoi backup, dentro wp-content.
	 *
	 * @var string
	 */
	const CARTELLA = 'AlboOnLine/BackupDatiAlbo';

	/**
	 * Azione del gettone di sicurezza dell'importazione vera.
	 *
	 * @var string
	 */
	const AZIONE_IMPORTA = 'albo_pretorio_importazione_vera';

	/**
	 * Nome del campo del gettone dell'importazione vera.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE_IMPORTA = 'albo_pretorio_importazione_vera_gettone';

	/**
	 * Nome del campo con l'operazione: avvia, continua o interrompi.
	 *
	 * @var string
	 */
	const CAMPO_OPERAZIONE = 'albo_pretorio_importazione_operazione';

	/**
	 * Nome del campo con l'impronta del backup letto.
	 *
	 * @var string
	 */
	const CAMPO_IMPRONTA = 'albo_pretorio_importazione_impronta';

	/**
	 * Nome del campo con il numero di atti che chi importa ha visto.
	 *
	 * @var string
	 */
	const CAMPO_ATTI = 'albo_pretorio_importazione_atti';

	/**
	 * Nome del campo con il numero di atti visibili che chi importa ha visto.
	 *
	 * @var string
	 */
	const CAMPO_VISIBILI = 'albo_pretorio_importazione_in_vista';

	/**
	 * Nome della casella di conferma dell'importazione.
	 *
	 * @var string
	 */
	const CAMPO_CONFERMA = 'albo_pretorio_importazione_conferma';

	/**
	 * Nome della casella di conferma sui dati personali degli atti visibili.
	 *
	 * @var string
	 */
	const CAMPO_CONFERMA_VISIBILI = 'albo_pretorio_importazione_conferma_in_vista';

	/**
	 * I due permessi che aprono la schermata: pubblicare atti e consultare gli scaduti.
	 *
	 * Il rapporto mostra gli oggetti di tutti gli atti del backup, scaduti
	 * compresi: chi lo legge deve poter gia' consultare gli scaduti.
	 *
	 * @return array<int, string>|null I due nomi, o nullo se il meccanismo comune non li fornisce.
	 */
	public static function permessi(): ?array {
		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || empty( $mappa['publish_posts'] ) || empty( $mappa[ Permessi::CHIAVE_CONSULTAZIONE ] ) ) {
			return null;
		}

		return array( (string) $mappa['publish_posts'], (string) $mappa[ Permessi::CHIAVE_CONSULTAZIONE ] );
	}

	/**
	 * Vero se l'utente corrente puo' aprire la schermata.
	 *
	 * @return bool
	 */
	public static function permessa(): bool {
		$permessi = self::permessi();

		return null !== $permessi && current_user_can( $permessi[0] ) && current_user_can( $permessi[1] );
	}

	/**
	 * Aggancio a `admin_menu`: la voce sotto il menu degli atti.
	 */
	public static function menu(): void {
		$permessi = self::permessi();

		if ( null === $permessi || ! post_type_exists( TIPO ) || ! self::permessa() ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=' . TIPO,
			__( 'Importazione dal vecchio albo', 'albo-pretorio-pa' ),
			__( 'Importazione dal vecchio albo', 'albo-pretorio-pa' ),
			$permessi[0],
			self::PAGINA,
			array( self::class, 'mostra' )
		);
	}

	/**
	 * Il percorso della cartella dei backup del plugin di provenienza.
	 *
	 * @return string
	 */
	public static function cartella(): string {
		return wp_normalize_path( WP_CONTENT_DIR . '/' . self::CARTELLA );
	}

	/**
	 * I backup presenti nella cartella, per nome.
	 *
	 * @return array<string, array{byte: int, data: int}>
	 */
	public static function presenti(): array {
		$cartella = self::cartella();

		if ( ! is_dir( $cartella ) ) {
			return array();
		}

		$presenti = array();

		foreach ( (array) scandir( $cartella ) as $nome ) {
			$nome     = (string) $nome;
			$percorso = $cartella . '/' . $nome;

			if ( 1 === preg_match( '/\A[^\/\\\\]+\.zip\z/i', $nome ) && is_file( $percorso ) && ! is_link( $percorso ) ) {
				$presenti[ $nome ] = array(
					'byte' => (int) filesize( $percorso ),
					'data' => (int) filemtime( $percorso ),
				);
			}
		}

		ksort( $presenti );

		return $presenti;
	}

	/**
	 * Il rapporto chiesto dall'invio, o il motivo per cui non c'e'.
	 *
	 * Il gettone e i permessi si controllano qui, non solo nella schermata.
	 * Un backup della cartella si accetta solo per nome, e solo se e' fra
	 * quelli presenti: nessun percorso arriva dall'invio. Un file caricato si
	 * accetta solo se PHP lo ha davvero ricevuto come caricamento.
	 *
	 * @param array<string, mixed> $invio Campi inviati.
	 * @param array<string, mixed> $file  File caricati.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function da_invio( array $invio, array $file ) {
		if ( ! self::permessa() ) {
			return new \WP_Error( 'albo_importazione_permesso', __( 'Non si possiede il permesso di leggere i backup dell\'albo.', 'albo-pretorio-pa' ) );
		}

		$gettone = isset( $invio[ self::CAMPO_GETTONE ] ) && is_string( $invio[ self::CAMPO_GETTONE ] ) ? sanitize_text_field( wp_unslash( $invio[ self::CAMPO_GETTONE ] ) ) : '';

		if ( false === wp_verify_nonce( $gettone, self::AZIONE ) ) {
			return new \WP_Error( 'albo_importazione_gettone', __( 'Il modulo e\' scaduto: ripetere la scelta del backup.', 'albo-pretorio-pa' ) );
		}

		$caricato = $file[ self::CAMPO_FILE ] ?? null;
		$percorso = null;

		if ( is_array( $caricato ) && isset( $caricato['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $caricato['error'] ) {
			if ( UPLOAD_ERR_OK !== (int) $caricato['error'] ) {
				return new \WP_Error(
					'albo_importazione_caricamento',
					in_array( (int) $caricato['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
						? self::troppo_grande()
						: __( 'Il caricamento del file non e\' riuscito.', 'albo-pretorio-pa' )
				);
			}

			$temporaneo = isset( $caricato['tmp_name'] ) && is_string( $caricato['tmp_name'] ) ? $caricato['tmp_name'] : '';

			if ( '' === $temporaneo || ! is_uploaded_file( $temporaneo ) ) {
				return new \WP_Error( 'albo_importazione_caricamento', __( 'Il caricamento del file non e\' riuscito.', 'albo-pretorio-pa' ) );
			}

			$percorso = $temporaneo;
		} else {
			$nome = isset( $invio[ self::CAMPO_PRESENTE ] ) && is_string( $invio[ self::CAMPO_PRESENTE ] ) ? wp_unslash( $invio[ self::CAMPO_PRESENTE ] ) : '';

			if ( '' === $nome ) {
				return new \WP_Error( 'albo_importazione_nessuno', __( 'Scegliere un backup dalla cartella o caricarne uno.', 'albo-pretorio-pa' ) );
			}

			if ( ! array_key_exists( $nome, self::presenti() ) ) {
				return new \WP_Error( 'albo_importazione_nessuno', __( 'Il backup scelto non e\' fra quelli presenti nella cartella.', 'albo-pretorio-pa' ) );
			}

			$percorso = self::cartella() . '/' . $nome;
		}

		$letto = LettoreBackup::leggi( $percorso );

		if ( is_wp_error( $letto ) ) {
			return $letto;
		}

		$adesso = Passaggi::ora();

		return AnalisiImportazione::rapporto( $letto, AnalisiImportazione::contesto( AnalisiImportazione::anni( $letto ), $adesso ) );
	}

	/**
	 * Il nome del backup della cartella scelto dall'invio, o nulla se l'invio porta un file caricato.
	 *
	 * Solo un backup della cartella si importa: i blocchi successivi devono
	 * rileggere lo stesso file, e un caricamento sparisce alla fine della richiesta.
	 *
	 * @param array<string, mixed> $invio Campi inviati.
	 * @param array<string, mixed> $file  File caricati.
	 * @return string|null
	 */
	private static function nome_dalla_cartella( array $invio, array $file ): ?string {
		$caricato = $file[ self::CAMPO_FILE ] ?? null;

		if ( is_array( $caricato ) && isset( $caricato['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $caricato['error'] ) {
			return null;
		}

		$nome = isset( $invio[ self::CAMPO_PRESENTE ] ) && is_string( $invio[ self::CAMPO_PRESENTE ] ) ? wp_unslash( $invio[ self::CAMPO_PRESENTE ] ) : '';

		return '' !== $nome && array_key_exists( $nome, self::presenti() ) ? $nome : null;
	}

	/**
	 * Esegue l'operazione dell'importazione vera chiesta dall'invio.
	 *
	 * Il gettone e i permessi si controllano qui. Il backup si accetta solo per
	 * nome e solo fra quelli della cartella; l'avvio porta subito il primo blocco.
	 *
	 * @param array<string, mixed> $invio Campi inviati.
	 * @return array<string, mixed>|\WP_Error Lo stato dell'importazione.
	 */
	public static function importazione_da_invio( array $invio ) {
		if ( ! self::permessa() ) {
			return new \WP_Error( 'albo_importazione_permesso', __( 'Non si possiede il permesso di importare gli atti del vecchio albo.', 'albo-pretorio-pa' ) );
		}

		$gettone = isset( $invio[ self::CAMPO_GETTONE_IMPORTA ] ) && is_string( $invio[ self::CAMPO_GETTONE_IMPORTA ] ) ? sanitize_text_field( wp_unslash( $invio[ self::CAMPO_GETTONE_IMPORTA ] ) ) : '';

		if ( false === wp_verify_nonce( $gettone, self::AZIONE_IMPORTA ) ) {
			return new \WP_Error( 'albo_importazione_gettone', __( 'Il modulo e\' scaduto: rileggere il backup e ripetere la conferma.', 'albo-pretorio-pa' ) );
		}

		$operazione = isset( $invio[ self::CAMPO_OPERAZIONE ] ) && is_string( $invio[ self::CAMPO_OPERAZIONE ] ) ? sanitize_key( wp_unslash( $invio[ self::CAMPO_OPERAZIONE ] ) ) : '';

		if ( 'continua' === $operazione ) {
			return Importazione::prosegui( Importazione::ATTI_PER_BLOCCO, self::scadenza() );
		}

		if ( 'interrompi' === $operazione ) {
			return Importazione::interrompi();
		}

		if ( 'avvia' !== $operazione ) {
			return new \WP_Error( 'albo_importazione_operazione', __( 'Operazione dell\'importazione non riconosciuta.', 'albo-pretorio-pa' ) );
		}

		$nome = self::nome_dalla_cartella( $invio, array() );

		if ( null === $nome ) {
			return new \WP_Error( 'albo_importazione_nessuno', __( 'Il backup scelto non e\' fra quelli presenti nella cartella.', 'albo-pretorio-pa' ) );
		}

		$impronta = isset( $invio[ self::CAMPO_IMPRONTA ] ) && is_string( $invio[ self::CAMPO_IMPRONTA ] ) ? strtolower( sanitize_text_field( wp_unslash( $invio[ self::CAMPO_IMPRONTA ] ) ) ) : '';
		$attese   = array(
			'impronta' => 1 === preg_match( '/\A[0-9a-f]{64}\z/', $impronta ) ? $impronta : '',
			'atti'     => isset( $invio[ self::CAMPO_ATTI ] ) && is_scalar( $invio[ self::CAMPO_ATTI ] ) ? absint( $invio[ self::CAMPO_ATTI ] ) : -1,
			'in_vista' => isset( $invio[ self::CAMPO_VISIBILI ] ) && is_scalar( $invio[ self::CAMPO_VISIBILI ] ) ? absint( $invio[ self::CAMPO_VISIBILI ] ) : -1,
		);

		$stato = Importazione::avvia(
			self::cartella() . '/' . $nome,
			$nome,
			$attese,
			! empty( $invio[ self::CAMPO_CONFERMA ] ),
			! empty( $invio[ self::CAMPO_CONFERMA_VISIBILI ] )
		);

		if ( is_wp_error( $stato ) ) {
			return $stato;
		}

		return Importazione::prosegui( Importazione::ATTI_PER_BLOCCO, self::scadenza() );
	}

	/**
	 * L'istante dopo il quale il blocco di questa richiesta non comincia un altro atto.
	 *
	 * Al piu' i secondi fissati, e mai oltre meta' del tempo che il server
	 * concede alla richiesta, contato da quando la richiesta e' arrivata.
	 *
	 * @return float
	 */
	public static function scadenza(): float {
		$inizio  = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		$secondi = (float) Importazione::SECONDI_PER_BLOCCO;
		$limite  = (int) ini_get( 'max_execution_time' );

		if ( $limite > 0 ) {
			$secondi = min( $secondi, $limite / 2 );
		}

		return $inizio + $secondi;
	}

	/**
	 * Il contenuto della schermata.
	 */
	public static function mostra(): void {
		if ( ! self::permessa() ) {
			wp_die( esc_html__( 'Non si possiede il permesso di leggere i backup dell\'albo.', 'albo-pretorio-pa' ), 403 );
		}

		$esito          = null;
		$importa        = null;
		$dalla_cartella = null;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- i gettoni li verificano da_invio() e importazione_da_invio().
		if ( isset( $_POST[ self::CAMPO_GETTONE_IMPORTA ] ) ) {
			$importa = self::importazione_da_invio( $_POST );
		} elseif ( isset( $_POST[ self::CAMPO_GETTONE ] ) ) {
			$esito          = self::da_invio( $_POST, $_FILES );
			$dalla_cartella = self::nome_dalla_cartella( $_POST, $_FILES );
		} elseif ( self::invio_perso() ) {
			$esito = new \WP_Error( 'albo_importazione_caricamento', self::troppo_grande() );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		echo '<div class="wrap albo-pretorio-importazione">';
		printf( '<h1>%s</h1>', esc_html__( 'Importazione dal vecchio albo', 'albo-pretorio-pa' ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'Legge il backup del plugin "Albo Pretorio On line" (menu Albo, Utility, Backup) e mostra cosa si porterebbe nel nuovo albo: atti, date, numeri, documenti, conflitti e dubbi. La lettura non scrive niente: ne\' atti, ne\' tipi, ne\' numeri, ne\' file. L\'importazione vera si avvia dopo, sotto il rapporto, con una conferma esplicita.', 'albo-pretorio-pa' )
		);

		if ( is_wp_error( $importa ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $importa->get_error_message() ) );
		}

		$stato = Importazione::stato();

		if ( null !== $stato ) {
			self::stampa_importazione( $stato );
		}

		self::modulo();

		if ( is_wp_error( $esito ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $esito->get_error_message() ) );
		} elseif ( is_array( $esito ) ) {
			self::stampa( $esito );

			if ( null !== $dalla_cartella ) {
				self::modulo_importa( $esito, $dalla_cartella );
			}
		}

		echo '</div>';
	}

	/**
	 * L'indirizzo della schermata, a cui tornano i moduli.
	 *
	 * @return string
	 */
	private static function indirizzo(): string {
		return add_query_arg(
			array(
				'post_type' => TIPO,
				'page'      => self::PAGINA,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * I campi nascosti comuni ai moduli dell'importazione vera.
	 *
	 * @param string $operazione Operazione.
	 */
	private static function campi_importa( string $operazione ): void {
		wp_nonce_field( self::AZIONE_IMPORTA, self::CAMPO_GETTONE_IMPORTA, false );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::CAMPO_OPERAZIONE ), esc_attr( $operazione ) );
	}

	/**
	 * Il modulo dell'importazione vera, sotto il rapporto di un backup della cartella.
	 *
	 * Mostra quanti atti si porterebbero e chiede la conferma esplicita; la
	 * seconda casella, separata, riguarda gli atti che arriverebbero visibili.
	 * I numeri mostrati viaggiano con l'invio: l'avvio li confronta con un
	 * rapporto rifatto, e se sono cambiati non parte.
	 *
	 * @param array<string, mixed> $rapporto Rapporto del backup.
	 * @param string               $nome     Nome del backup nella cartella.
	 */
	private static function modulo_importa( array $rapporto, string $nome ): void {
		printf( '<h2 id="albo-pretorio-importa">%s</h2>', esc_html__( 'Importa nel nuovo albo', 'albo-pretorio-pa' ) );

		if ( ! $rapporto['pacchetto']['zip'] ) {
			printf( '<p>%s</p>', esc_html__( 'Si importa solo da un archivio zip, che porta i documenti: il solo file SQL non porta nessun atto.', 'albo-pretorio-pa' ) );
			return;
		}

		if ( Importazione::in_corso() ) {
			printf( '<p>%s</p>', esc_html__( 'C\'e\' gia\' un\'importazione in corso: si continua o si interrompe quella, qui sopra.', 'albo-pretorio-pa' ) );
			return;
		}

		$senza    = Importazione::selezione( $rapporto, false );
		$con      = Importazione::selezione( $rapporto, true );
		$visibili = $con['in_vista'];
		$impronta = Importazione::impronta_backup( self::cartella() . '/' . $nome );

		if ( array() === $con['atti'] || null === $impronta ) {
			printf( '<p>%s</p>', esc_html__( 'Nel rapporto non c\'e\' nessun atto che si porti.', 'albo-pretorio-pa' ) );
			return;
		}

		printf(
			'<p class="albo-pretorio-da-importare" data-atti="%1$d" data-in-vista="%2$d">%3$s</p>',
			count( $senza['atti'] ),
			(int) $visibili,
			esc_html(
				sprintf(
					/* translators: 1: atti che si porterebbero, 2: atti nel backup, 3: atti con il documento principale da scegliere. */
					__( 'Si porterebbero %1$d atti su %2$d del backup, pubblicati e con la pubblicazione gia\' finita: non si vedranno sul sito e si consultano dalla schermata degli atti scaduti. Di questi, %3$d arrivano con il documento principale da scegliere. Gli altri atti e il motivo per cui restano fuori sono nel rapporto qui sopra.', 'albo-pretorio-pa' ),
					count( $senza['atti'] ),
					$rapporto['riepilogo']['totale']['nel_backup'],
					$senza['principale_da_scegliere']
				)
			)
		);

		echo '<form method="post" action="' . esc_url( self::indirizzo() . '#albo-pretorio-importa' ) . '">';
		self::campi_importa( 'avvia' );

		foreach ( array(
			self::CAMPO_PRESENTE => $nome,
			self::CAMPO_IMPRONTA => $impronta,
			self::CAMPO_ATTI     => (string) count( $senza['atti'] ),
			self::CAMPO_VISIBILI => (string) $visibili,
		) as $campo => $valore ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $campo ), esc_attr( $valore ) );
		}

		printf(
			'<p><label><input type="checkbox" name="%1$s" value="1" /> %2$s</label></p>',
			esc_attr( self::CAMPO_CONFERMA ),
			esc_html(
				sprintf(
					/* translators: %s: nome del backup. */
					__( 'Confermo l\'importazione degli atti del backup %s. Gli atti importati non si tolgono con un clic: restano nel nuovo albo con il loro numero e la loro voce di registro.', 'albo-pretorio-pa' ),
					$nome
				)
			)
		);

		if ( $visibili > 0 ) {
			printf(
				'<p><label><input type="checkbox" name="%1$s" value="1" /> %2$s</label></p>',
				esc_attr( self::CAMPO_CONFERMA_VISIBILI ),
				esc_html(
					sprintf(
						/* translators: %d: atti ancora in pubblicazione. */
						_n(
							'Porta anche %d atto ancora in pubblicazione, che arriva visibile sul sito fino a tutto il suo giorno di fine. Confermo, come responsabile, di averne guardato i documenti e che non diffondono dati personali oltre quanto la norma consente.',
							'Porta anche i %d atti ancora in pubblicazione, che arrivano visibili sul sito fino a tutto il loro giorno di fine. Confermo, come responsabile, di averne guardato i documenti e che non diffondono dati personali oltre quanto la norma consente.',
							$visibili,
							'albo-pretorio-pa'
						),
						$visibili
					)
				)
			);
		}

		submit_button( __( 'Importa', 'albo-pretorio-pa' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * L'avanzamento e il rapporto finale dell'importazione in corso o dell'ultima.
	 *
	 * @param array<string, mixed> $stato Stato dell'importazione.
	 */
	public static function stampa_importazione( array $stato ): void {
		$titoli = array(
			'in_corso'   => __( 'Importazione in corso', 'albo-pretorio-pa' ),
			'finita'     => __( 'Ultima importazione: finita', 'albo-pretorio-pa' ),
			'interrotta' => __( 'Ultima importazione: interrotta', 'albo-pretorio-pa' ),
		);

		$importati = count( (array) $stato['importati'] );

		printf( '<div class="albo-pretorio-stato-importazione" data-stato="%1$s"><h2>%2$s</h2>', esc_attr( (string) $stato['stato'] ), esc_html( $titoli[ $stato['stato'] ] ?? (string) $stato['stato'] ) );
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: nome del backup, 2: data e ora dell'avvio. */
					__( 'Backup %1$s, avviata il %2$s.', 'albo-pretorio-pa' ),
					(string) $stato['backup'],
					wp_date( 'd/m/Y H:i', (int) strtotime( (string) $stato['avviata'] . ' UTC' ) )
				)
			)
		);
		printf(
			'<p class="albo-pretorio-importati" data-importati="%1$d" data-nel-backup="%2$d"><strong>%3$s</strong></p>',
			(int) $importati,
			(int) $stato['nel_backup'],
			esc_html(
				sprintf(
					/* translators: 1: atti importati, 2: atti nel backup. */
					__( 'Importati %1$d atti su %2$d del backup.', 'albo-pretorio-pa' ),
					$importati,
					(int) $stato['nel_backup']
				)
			)
		);

		if ( (int) $stato['analizzati'] !== (int) $stato['nel_backup'] ) {
			printf(
				'<p><strong>%s</strong></p>',
				esc_html(
					sprintf(
						/* translators: 1: atti analizzati, 2: atti nel backup. */
						__( 'Rapporto parziale: il backup e\' stato analizzato per %1$d atti su %2$d; gli altri non sono stati considerati.', 'albo-pretorio-pa' ),
						(int) $stato['analizzati'],
						(int) $stato['nel_backup']
					)
				)
			);
		}

		if ( 'in_corso' === $stato['stato'] ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: atti esaminati, 2: atti da esaminare. */
						__( 'Esaminati %1$d atti su %2$d da portare. Si continua con il pulsante qui sotto: ogni clic porta un blocco di atti.', 'albo-pretorio-pa' ),
						(int) $stato['posizione'],
						count( (array) $stato['selezione'] )
					)
				)
			);

			foreach ( array(
				'continua'   => __( 'Continua', 'albo-pretorio-pa' ),
				'interrompi' => __( 'Interrompi', 'albo-pretorio-pa' ),
			) as $operazione => $etichetta ) {
				echo '<form method="post" style="display:inline-block;margin-right:1em" action="' . esc_url( self::indirizzo() ) . '">';
				self::campi_importa( $operazione );
				submit_button( $etichetta, 'continua' === $operazione ? 'primary' : 'secondary', 'submit', false );
				echo '</form>';
			}
		}

		if ( (int) $stato['gia_importati'] > 0 ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: atti importati da un'importazione precedente. */
						__( 'Atti gia\' importati prima di questa importazione, e non toccati: %d.', 'albo-pretorio-pa' ),
						(int) $stato['gia_importati']
					)
				)
			);
		}

		self::elenco_numeri( 'da_scegliere', __( 'Atti con il documento principale da scegliere: tutti i file sono arrivati come allegati', 'albo-pretorio-pa' ), (array) $stato['da_scegliere'] );

		if ( array() !== $stato['non_riusciti'] ) {
			printf( '<details class="albo-pretorio-non-riusciti" open><summary><strong>%1$s (%2$d)</strong></summary><ul>', esc_html__( 'Atti non riusciti: non esistono nel nuovo albo, si riprovano rilanciando', 'albo-pretorio-pa' ), count( (array) $stato['non_riusciti'] ) );

			foreach ( (array) $stato['non_riusciti'] as $fallito ) {
				printf( '<li data-codice="%1$s">%2$s: %3$s</li>', esc_attr( (string) $fallito['codice'] ), esc_html( (string) $fallito['numero'] ), esc_html( (string) $fallito['testo'] ) );
			}

			echo '</ul></details>';
		}

		if ( array() !== $stato['non_portati'] ) {
			printf( '<h3>%s</h3>', esc_html__( 'Atti del backup non portati, per motivo', 'albo-pretorio-pa' ) );

			foreach ( (array) $stato['non_portati'] as $chiave => $gruppo ) {
				self::elenco_numeri( strtok( (string) $chiave, ':' ), (string) $gruppo['testo'], (array) $gruppo['atti'] );
			}
		}

		echo '</div>';
	}

	/**
	 * Un gruppo di atti, per numero, chiuso in un riquadro che si apre.
	 *
	 * @param string             $codice Codice del gruppo.
	 * @param string             $titolo Titolo.
	 * @param array<int, string> $numeri Numeri degli atti.
	 */
	private static function elenco_numeri( string $codice, string $titolo, array $numeri ): void {
		if ( array() === $numeri ) {
			return;
		}

		printf(
			'<details class="albo-pretorio-gruppo-importazione" data-codice="%1$s"><summary>%2$s: <strong>%3$d</strong></summary><p>%4$s</p></details>',
			esc_attr( $codice ),
			esc_html( $titolo ),
			count( $numeri ),
			esc_html( implode( ', ', $numeri ) )
		);
	}

	/**
	 * Il messaggio per un file oltre i limiti di caricamento del server.
	 */
	private static function troppo_grande(): string {
		return __( 'Il file e\' piu\' grande di quanto il server accetti in un caricamento. Il backup si puo\' lasciare nella cartella dei backup del vecchio albo e sceglierlo da li\'.', 'albo-pretorio-pa' );
	}

	/**
	 * Vero se e' arrivato un invio che PHP ha scartato tutto.
	 *
	 * Quando la richiesta supera post_max_size PHP svuota sia i campi sia i
	 * file: senza questo controllo la pagina si ripresenterebbe come se
	 * nessuno avesse inviato niente.
	 */
	private static function invio_perso(): bool {
		$metodo    = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		$lunghezza = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- si controlla solo che l'invio sia vuoto.
		return 'POST' === $metodo && $lunghezza > 0 && array() === $_POST && array() === $_FILES;
	}

	/**
	 * Il modulo di scelta del backup.
	 */
	private static function modulo(): void {
		$presenti = self::presenti();

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( self::indirizzo() ) . '">';
		wp_nonce_field( self::AZIONE, self::CAMPO_GETTONE, false );

		if ( array() === $presenti ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: percorso della cartella. */
						__( 'Nella cartella dei backup del vecchio albo (%s) non c\'e\' nessun archivio.', 'albo-pretorio-pa' ),
						self::CARTELLA
					)
				)
			);
		} else {
			printf( '<p><label for="%1$s">%2$s</label><br /><select id="%1$s" name="%1$s">', esc_attr( self::CAMPO_PRESENTE ), esc_html__( 'Backup presenti nella cartella del vecchio albo', 'albo-pretorio-pa' ) );
			printf( '<option value="">%s</option>', esc_html__( 'Nessuno', 'albo-pretorio-pa' ) );

			foreach ( $presenti as $nome => $dati ) {
				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( $nome ),
					esc_html( $nome . ' (' . size_format( $dati['byte'] ) . ', ' . wp_date( 'd/m/Y H:i', $dati['data'] ) . ')' )
				);
			}

			echo '</select></p>';
		}

		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="file" id="%1$s" name="%1$s" accept=".zip,.sql" /></p>',
			esc_attr( self::CAMPO_FILE ),
			esc_html__( 'Oppure un backup dal proprio computer (archivio zip, o il solo file SQL)', 'albo-pretorio-pa' )
		);

		submit_button( __( 'Leggi il backup', 'albo-pretorio-pa' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Il rapporto.
	 *
	 * @param array<string, mixed> $rapporto Rapporto di `AnalisiImportazione`.
	 */
	public static function stampa( array $rapporto ): void {
		$esiti = array(
			AnalisiImportazione::CONFLITTO   => __( 'Atti con un conflitto: non si porterebbero finche\' resta', 'albo-pretorio-pa' ),
			AnalisiImportazione::DA_DECIDERE => __( 'Atti da decidere: si porterebbero dopo una scelta di chi importa', 'albo-pretorio-pa' ),
			AnalisiImportazione::IMPORTABILE => __( 'Atti che si porterebbero cosi\' come sono', 'albo-pretorio-pa' ),
			AnalisiImportazione::ESCLUSO     => __( 'Bozze: non si portano', 'albo-pretorio-pa' ),
			AnalisiImportazione::IMPORTATO   => __( 'Atti gia\' importati: non si toccano piu\'', 'albo-pretorio-pa' ),
		);

		printf( '<h2>%s</h2>', esc_html__( 'Rapporto', 'albo-pretorio-pa' ) );
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: nome del file SQL, 2: numero di atti, 3: numero di file, 4: data dell'analisi. */
					__( 'File letto: %1$s. Atti: %2$d. File nell\'archivio: %3$d. Visibilita\' calcolata al %4$s.', 'albo-pretorio-pa' ),
					$rapporto['pacchetto']['sql'],
					$rapporto['riepilogo']['totale']['atti'],
					$rapporto['pacchetto']['file'],
					AnalisiImportazione::leggibile( $rapporto['oggi'] )
				)
			)
		);

		if ( $rapporto['riepilogo']['totale']['atti'] !== $rapporto['riepilogo']['totale']['nel_backup'] ) {
			printf(
				'<p><strong>%s</strong></p>',
				esc_html(
					sprintf(
						/* translators: 1: atti analizzati, 2: atti nel backup. */
						__( 'Rapporto parziale: i conteggi qui sotto e le categorie riguardano solo gli atti analizzati, %1$d su %2$d.', 'albo-pretorio-pa' ),
						$rapporto['riepilogo']['totale']['atti'],
						$rapporto['riepilogo']['totale']['nel_backup']
					)
				)
			);
		}

		echo '<ul class="albo-pretorio-riepilogo">';

		foreach ( $esiti as $esito => $titolo ) {
			printf( '<li data-esito="%1$s">%2$s: <strong>%3$d</strong></li>', esc_attr( $esito ), esc_html( $titolo ), (int) $rapporto['riepilogo']['esiti'][ $esito ] );
		}

		echo '</ul>';

		if ( array() !== $rapporto['segnalazioni'] ) {
			printf( '<h3>%s</h3>', esc_html__( 'Segnalazioni sul backup', 'albo-pretorio-pa' ) );
			self::segnalazioni( $rapporto['segnalazioni'] );
		}

		self::numerazione( $rapporto['numerazione'] );
		self::categorie( $rapporto['categorie'] );
		self::campi( $rapporto );
		self::orfani( $rapporto['orfani'] );

		foreach ( $esiti as $esito => $titolo ) {
			$atti = array_filter(
				$rapporto['atti'],
				static function ( array $atto ) use ( $esito ): bool {
					return $atto['esito'] === $esito;
				}
			);

			if ( array() === $atti ) {
				continue;
			}

			printf(
				'<details class="albo-pretorio-esito" data-esito="%1$s"%2$s><summary><strong>%3$s (%4$d)</strong></summary>',
				esc_attr( $esito ),
				AnalisiImportazione::CONFLITTO === $esito ? ' open' : '',
				esc_html( $titolo ),
				count( $atti )
			);
			self::atti( $atti );
			echo '</details>';
		}
	}

	/**
	 * Il numero di un atto come si mostra: bozza solo se e' davvero una bozza.
	 *
	 * @param array<string, mixed> $atto Atto del rapporto.
	 * @return string
	 */
	private static function numero( array $atto ): string {
		if ( 'bozza' === $atto['stato'] ) {
			return __( 'bozza', 'albo-pretorio-pa' );
		}

		if ( $atto['numero'] > 0 ) {
			return $atto['numero'] . '/' . $atto['anno'];
		}

		return sprintf(
			/* translators: %s: numero scritto nel backup. */
			__( 'numero non valido: %s', 'albo-pretorio-pa' ),
			$atto['scritto']
		);
	}

	/**
	 * I file dell'archivio che nessun allegato di un atto cita, per nome.
	 *
	 * @param array<int, string> $orfani Percorsi nell'archivio.
	 */
	private static function orfani( array $orfani ): void {
		if ( array() === $orfani ) {
			return;
		}

		printf( '<h3>%s</h3>', esc_html__( 'File dell\'archivio che resterebbero fuori', 'albo-pretorio-pa' ) );
		echo '<ul class="albo-pretorio-orfani">';

		foreach ( $orfani as $nome ) {
			printf( '<li><code>%s</code></li>', esc_html( $nome ) );
		}

		echo '</ul>';
	}

	/**
	 * Un elenco di segnalazioni.
	 *
	 * @param array<int, array{livello: string, codice: string, testo: string}> $segnalazioni Segnalazioni.
	 */
	private static function segnalazioni( array $segnalazioni ): void {
		$nomi = array(
			'conflitto' => __( 'Conflitto', 'albo-pretorio-pa' ),
			'dubbio'    => __( 'Da decidere', 'albo-pretorio-pa' ),
			'nota'      => __( 'Nota', 'albo-pretorio-pa' ),
		);

		echo '<ul>';

		foreach ( $segnalazioni as $segnalazione ) {
			printf(
				'<li data-codice="%1$s"><strong>%2$s</strong>: %3$s</li>',
				esc_attr( $segnalazione['codice'] ),
				esc_html( $nomi[ $segnalazione['livello'] ] ?? $segnalazione['livello'] ),
				esc_html( $segnalazione['testo'] )
			);
		}

		echo '</ul>';
	}

	/**
	 * La numerazione per anno e la proposta per la partenza.
	 *
	 * @param array<string, mixed> $numerazione Numerazione del rapporto.
	 */
	private static function numerazione( array $numerazione ): void {
		printf( '<h3>%s</h3>', esc_html__( 'Numerazione', 'albo-pretorio-pa' ) );

		if ( null !== $numerazione['proposta'] ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: anno, 2: ultimo numero, 3: prossimo numero del contatore, 4: numero piu' alto trovato. */
						__( 'Per il %1$d l\'ultimo numero usato nel vecchio albo risulterebbe %2$d (contatore: prossimo numero %3$d; numero piu\' alto fra gli atti: %4$d). E\' una proposta: la partenza la dichiara chi pubblica, dalla schermata della numerazione.', 'albo-pretorio-pa' ),
						$numerazione['proposta']['anno'],
						$numerazione['proposta']['ultimo'],
						$numerazione['proposta']['contatore'],
						$numerazione['proposta']['massimo']
					)
				)
			);
		}

		if ( array() === $numerazione['anni'] ) {
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';

		foreach ( array( __( 'Anno', 'albo-pretorio-pa' ), __( 'Atti numerati', 'albo-pretorio-pa' ), __( 'Numero piu\' alto', 'albo-pretorio-pa' ), __( 'Numeri mancanti', 'albo-pretorio-pa' ), __( 'Nel nuovo albo', 'albo-pretorio-pa' ) ) as $intestazione ) {
			printf( '<th scope="col">%s</th>', esc_html( $intestazione ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $numerazione['anni'] as $anno ) {
			$nuovo = null === $anno['nuovo']
				? __( 'anno non ancora numerato', 'albo-pretorio-pa' )
				: sprintf(
					/* translators: 1: ultimo numero dichiarato del registro precedente, 2: ultimo numero dato. */
					__( 'partenza %1$d, ultimo numero dato %2$d', 'albo-pretorio-pa' ),
					$anno['nuovo']['partenza'],
					$anno['nuovo']['ultimo']
				);

			printf( '<tr><td>%1$d</td><td>%2$d</td><td>%3$d</td><td>%4$d</td><td>%5$s</td></tr>', (int) $anno['anno'], (int) $anno['atti'], (int) $anno['massimo'], (int) $anno['mancanti'], esc_html( $nuovo ) );
		}

		echo '</tbody></table>';
	}

	/**
	 * Le categorie e i tipi di atto che diventerebbero.
	 *
	 * @param array<int, array<string, mixed>> $categorie Categorie del rapporto.
	 */
	private static function categorie( array $categorie ): void {
		if ( array() === $categorie ) {
			return;
		}

		printf( '<h3>%s</h3>', esc_html__( 'Categorie e tipi di atto', 'albo-pretorio-pa' ) );
		echo '<table class="widefat striped"><thead><tr>';

		foreach ( array( __( 'Categoria', 'albo-pretorio-pa' ), __( 'Giorni', 'albo-pretorio-pa' ), __( 'Atti', 'albo-pretorio-pa' ), __( 'Nel nuovo albo', 'albo-pretorio-pa' ) ) as $intestazione ) {
			printf( '<th scope="col">%s</th>', esc_html( $intestazione ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $categorie as $categoria ) {
			printf(
				'<tr><td>%1$s</td><td>%2$d</td><td>%3$d</td><td>%4$s',
				esc_html( '' === $categoria['madre'] ? $categoria['nome'] : $categoria['madre'] . ' > ' . $categoria['nome'] ),
				(int) $categoria['giorni'],
				(int) $categoria['atti'],
				esc_html( $categoria['corrisponde'] )
			);

			if ( array() !== $categoria['segnalazioni'] ) {
				self::segnalazioni( $categoria['segnalazioni'] );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * I campi non riconosciuti e quelli assenti.
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 */
	private static function campi( array $rapporto ): void {
		if ( array() !== $rapporto['campi']['assenti'] ) {
			printf( '<h3>%s</h3><ul>', esc_html__( 'Campi assenti nel backup (versione precedente del vecchio albo)', 'albo-pretorio-pa' ) );

			foreach ( $rapporto['campi']['assenti'] as $tabella => $colonne ) {
				printf( '<li>%1$s: %2$s</li>', esc_html( $tabella ), esc_html( implode( ', ', $colonne ) ) );
			}

			echo '</ul>';
		}

		if ( array() !== $rapporto['pacchetto']['ignorate'] ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: numero di tabelle. */
						_n( '%d altra tabella nel file SQL non e\' dell\'albo ed e\' stata saltata.', '%d altre tabelle nel file SQL non sono dell\'albo e sono state saltate.', count( $rapporto['pacchetto']['ignorate'] ), 'albo-pretorio-pa' ),
						count( $rapporto['pacchetto']['ignorate'] )
					)
				)
			);
		}
	}

	/**
	 * Gli atti di un gruppo.
	 *
	 * @param array<int, array<string, mixed>> $atti Atti.
	 */
	private static function atti( array $atti ): void {
		echo '<table class="widefat striped albo-pretorio-atti-importati"><thead><tr>';

		foreach ( array( __( 'Numero', 'albo-pretorio-pa' ), __( 'Oggetto', 'albo-pretorio-pa' ), __( 'Pubblicazione', 'albo-pretorio-pa' ), __( 'Dati', 'albo-pretorio-pa' ), __( 'Documenti', 'albo-pretorio-pa' ), __( 'Segnalazioni', 'albo-pretorio-pa' ) ) as $intestazione ) {
			printf( '<th scope="col">%s</th>', esc_html( $intestazione ) );
		}

		echo '</tr></thead><tbody>';

		foreach ( $atti as $atto ) {
			$date = null === $atto['inizio'] && null === $atto['fine']
				? __( 'date non valide', 'albo-pretorio-pa' )
				: sprintf(
					/* translators: 1: data di inizio, 2: data di fine. */
					__( 'dal %1$s al %2$s', 'albo-pretorio-pa' ),
					null === $atto['inizio'] ? '?' : AnalisiImportazione::leggibile( $atto['inizio'] ),
					null === $atto['fine'] ? '?' : AnalisiImportazione::leggibile( $atto['fine'] )
				);

			printf(
				'<tr data-atto="%1$s"><td>%2$s<br /><small>%3$s</small></td><td>%4$s<br /><small>%5$s</small></td><td>%6$s<br /><small>%7$s</small></td><td>',
				esc_attr( $atto['id'] ),
				esc_html( self::numero( $atto ) ),
				esc_html(
					sprintf(
						/* translators: %s: numero interno dell'atto nel vecchio albo. */
						__( 'interno %s', 'albo-pretorio-pa' ),
						$atto['id']
					)
				),
				esc_html( $atto['oggetto'] ),
				esc_html( $atto['tipo'] ),
				esc_html( $date ),
				esc_html( $atto['pubblico'] )
			);

			foreach ( $atto['dati'] as $etichetta => $valore ) {
				printf( '<strong>%1$s</strong>: %2$s<br />', esc_html( (string) $etichetta ), esc_html( (string) $valore ) );
			}

			echo '</td><td>';

			if ( array() === $atto['documenti'] ) {
				esc_html_e( 'Nessun documento.', 'albo-pretorio-pa' );
			} else {
				echo '<ul>';

				foreach ( $atto['documenti'] as $documento ) {
					printf( '<li>%1$s (%2$s): %3$s', esc_html( $documento['titolo'] ), esc_html( $documento['ruolo'] ), esc_html( $documento['verifica'] ) );

					if ( null !== $documento['file'] ) {
						printf(
							'<br /><span class="albo-pretorio-file">%1$s <code>%2$s</code></span>',
							esc_html__( 'File nell\'archivio:', 'albo-pretorio-pa' ),
							esc_html( $documento['file'] )
						);
					}

					echo '</li>';
				}

				echo '</ul>';
			}

			echo '</td><td>';
			self::segnalazioni( $atto['segnalazioni'] );
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}
}
