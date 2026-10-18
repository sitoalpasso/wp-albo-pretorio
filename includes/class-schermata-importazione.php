<?php
/**
 * La schermata dell'importazione a secco (ALBO-35).
 *
 * Chi pubblica sceglie un backup del plugin "Albo Pretorio On line", fra quelli
 * che quel plugin ha lasciato nella sua cartella sul server oppure caricandolo
 * dal proprio computer, e legge il rapporto di cosa si porterebbe nel nuovo
 * albo. **Non si scrive niente**: ne' atti, ne' tipi, ne' numeri, ne' file. Il
 * file caricato si legge dal posto temporaneo in cui lo mette PHP, che lo
 * cancella da se' alla fine della richiesta, e il rapporto vive solo nella
 * pagina che lo mostra.
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
			__( 'Importazione a secco', 'albo-pretorio-pa' ),
			__( 'Importazione a secco', 'albo-pretorio-pa' ),
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
	 * Il contenuto della schermata.
	 */
	public static function mostra(): void {
		if ( ! self::permessa() ) {
			wp_die( esc_html__( 'Non si possiede il permesso di leggere i backup dell\'albo.', 'albo-pretorio-pa' ), 403 );
		}

		$esito = null;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il gettone lo verifica da_invio().
		if ( isset( $_POST[ self::CAMPO_GETTONE ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il gettone lo verifica da_invio().
			$esito = self::da_invio( $_POST, $_FILES );
		} elseif ( self::invio_perso() ) {
			$esito = new \WP_Error( 'albo_importazione_caricamento', self::troppo_grande() );
		}

		echo '<div class="wrap albo-pretorio-importazione">';
		printf( '<h1>%s</h1>', esc_html__( 'Importazione a secco dal vecchio albo', 'albo-pretorio-pa' ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'Legge il backup del plugin "Albo Pretorio On line" (menu Albo, Utility, Backup) e mostra cosa si porterebbe nel nuovo albo: atti, date, numeri, documenti, conflitti e dubbi. Non scrive niente: ne\' atti, ne\' tipi, ne\' numeri, ne\' file.', 'albo-pretorio-pa' )
		);

		self::modulo();

		if ( is_wp_error( $esito ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $esito->get_error_message() ) );
		} elseif ( is_array( $esito ) ) {
			self::stampa( $esito );
		}

		echo '</div>';
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

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(
			add_query_arg(
				array(
					'post_type' => TIPO,
					'page'      => self::PAGINA,
				),
				admin_url( 'edit.php' )
			)
		) . '">';
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
				esc_html( $atto['numero'] > 0 ? $atto['numero'] . '/' . $atto['anno'] : __( 'bozza', 'albo-pretorio-pa' ) ),
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
					printf( '<li>%1$s (%2$s): %3$s</li>', esc_html( $documento['titolo'] ), esc_html( $documento['ruolo'] ), esc_html( $documento['verifica'] ) );
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
