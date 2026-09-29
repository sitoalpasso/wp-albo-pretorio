<?php
/**
 * Tutto o niente, guardiano, riquadro e permesso del registro: righe A-105..A-110.
 *
 * I guasti si forzano da fuori, come li produrrebbe la banca dati o un altro
 * componente: un'istruzione che fallisce, un metadato che non si scrive. Il
 * codice dell'albo non ha nessun interruttore per le prove.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Installazione;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\RiquadroPassaggi;

use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\TIPO;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si osserva e si guasta la banca dati.

/**
 * I pezzi della pubblicazione che falliscono uno per volta, e le difese intorno.
 */
class TuttoONienteTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Il guasto attivo sulle istruzioni della banca dati, o nullo.
	 *
	 * @var callable|null
	 */
	private $guasto = null;

	/**
	 * Assetto comune, e l'aggancio che guasta le istruzioni.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();

		add_filter( 'query', array( $this, 'guasta' ) );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'query', array( $this, 'guasta' ) );

		$this->guasto = null;
		$wpdb->suppress_errors( false );

		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Sostituisce con un'istruzione che fallisce quelle che il guasto attivo riconosce.
	 *
	 * @param string $istruzione Istruzione.
	 * @return string
	 */
	public function guasta( $istruzione ) {
		if ( null !== $this->guasto && ( $this->guasto )( (string) $istruzione ) ) {
			return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
		}

		return $istruzione;
	}

	/**
	 * Attiva un guasto sulle istruzioni che contengono tutti i pezzi indicati.
	 *
	 * @param array<int, string> $pezzi Pezzi dell'istruzione.
	 */
	private function guasta_istruzioni( array $pezzi ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );

		$this->guasto = static function ( string $istruzione ) use ( $pezzi ): bool {
			foreach ( $pezzi as $pezzo ) {
				if ( false === strpos( $istruzione, $pezzo ) ) {
					return false;
				}
			}

			return true;
		};
	}

	/**
	 * Toglie il guasto.
	 */
	private function ripara(): void {
		global $wpdb;

		$this->guasto = null;
		$wpdb->suppress_errors( false );
	}

	/**
	 * Il nome della tabella del registro del meccanismo comune.
	 *
	 * @return string
	 */
	private function tabella_registro(): string {
		global $wpdb;

		return $wpdb->prefix . 'conformita_core_registro';
	}

	/**
	 * A-105: tutto o niente, con i pezzi della pubblicazione che falliscono uno per volta.
	 */
	public function test_a105_tutto_o_niente(): void {
		global $wpdb;

		$chiave = conformita_core_chiave_fine_pubblicazione();
		$guasti = array(
			'scrittura dello stato'                  => function () use ( $wpdb ) {
				$this->guasta_istruzioni( array( 'UPDATE `' . $wpdb->posts . '`', "'publish'" ) );
			},
			'data di fine'                           => function () use ( $chiave ) {
				add_filter(
					'update_post_metadata',
					static function ( $esito, $oggetto, $nome ) use ( $chiave ) {
						return $chiave === $nome ? false : $esito;
					},
					10,
					3
				);
			},
			'stato riscritto da un altro aggancio'   => function () use ( $wpdb ) {
				add_action(
					'wp_insert_post',
					static function ( $post_id ) use ( $wpdb ) {
						$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => (int) $post_id ) );
					},
					5
				);
			},
			'voce automatica della pubblicazione'    => function () {
				$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', "'pubblicazione'" ) );
			},
			'voce della conferma sui dati personali' => function () {
				$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', "'" . Passaggi::AZIONE_CONFERMA . "'" ) );
			},
		);

		foreach ( $guasti as $caso => $guasta ) {
			$id    = $this->atto_in_verifica();
			$prima = $this->fotografia( $id );

			$this->assertNull( $prima['numero'], 'Precondizione: nessun numero prima.' );

			$guasta();

			try {
				$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );
			} finally {
				$this->ripara();
				remove_all_filters( 'update_post_metadata' );
				remove_all_actions( 'wp_insert_post', 5 );
			}

			$this->assertWPError( $esito, $caso . ': rifiutata.' );
			$this->assertSame( 'pending', get_post_status( $id ), $caso . ': in verifica anche per chi legge dalla memoria.' );

			$dopo   = $this->fotografia( $id );
			$numero = $dopo['numero'];

			unset( $prima['numero'], $dopo['numero'] );

			$this->assertSame( $prima, $dopo, $caso . ': l\'atto e\' in verifica e identico a prima, registro compreso.' );
			$this->assertIsArray( $numero, $caso . ': il numero preso resta dell\'atto.' );

			// Tolto il guasto, lo stesso atto si pubblica con lo stesso numero.
			$ancora = $this->pubblica_da_codice( $id, $this->pubblicatore );

			$this->assertIsArray( $ancora, $caso . ': riuscita dopo il guasto.' );
			$this->assertSame( $numero['numero'], $ancora['numero'], $caso . ': con il numero gia\' suo.' );
			$this->assertSame( 'publish', get_post_status( $id ) );
		}

		// Il numero di un tentativo fallito non va a nessun altro: l'atto seguente ha il successivo.
		$ultimo = $this->pubblica_da_codice( $this->atto_in_verifica(), $this->pubblicatore );

		$this->assertSame( count( $guasti ) + 1, $ultimo['numero'] );

		// Il repertorio che non avanza: nessuna scrittura, nessun numero.
		$id    = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );

		$this->guasta_istruzioni( array( 'UPDATE `' . Repertorio::tabella_anni() . '`' ) );

		try {
			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );
		} finally {
			$this->ripara();
		}

		$this->assertSame( array( 'albo_repertorio_contatore_non_avanzato' ), $esito->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Repertorio guasto: niente cambia, e nessun numero.' );

		// Il rimando con la voce del motivo che non si scrive: resta in verifica.
		$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', "'" . Passaggi::AZIONE_RIMANDO . "'" ) );

		try {
			$esito = $this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					return albo_pretorio_rimanda_in_bozza( $id, 'Motivo.' );
				}
			);
		} finally {
			$this->ripara();
		}

		$this->assertSame( array( 'albo_voce_non_scritta' ), $esito->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Rimando senza voce: niente cambia.' );
	}

	/**
	 * A-106: tutto o niente con una transazione vera, fuori da quella della suite.
	 *
	 * Le altre prove girano dentro la transazione che la suite apre a ogni
	 * prova, e la pubblicazione ci mette un punto di ripristino. Qui la
	 * scrittura automatica della banca dati torna accesa, come su un sito
	 * vero: la pubblicazione apre e chiude una transazione sua, e una seconda
	 * connessione guarda l'atto mentre la transazione e' aperta.
	 */
	public function test_a106_transazione_vera(): void {
		global $wpdb;

		$id        = $this->atto_in_verifica();
		$visto     = array();
		$contenuti = array_merge(
			array( $id ),
			array_map(
				'intval',
				get_children(
					array(
						'post_parent' => $id,
						'fields'      => 'ids',
						'post_type'   => 'attachment',
					)
				)
			)
		);

		/*
		 * La seconda connessione vede solo cio' che e' confermato: prima della
		 * conferma qui sotto, le opzioni come erano prima di questa prova. Si
		 * rimettono cosi' alla fine, perche' la conferma rende definitivo anche
		 * l'assetto, e le prove seguenti non devono trovarlo.
		 */
		$altra     = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$originali = $altra->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", OBJECT_K );

		self::commit_transaction();
		$wpdb->query( 'SET autocommit = 1' );

		$legge = static function () use ( $altra, $wpdb, $id ): string {
			return (string) $altra->get_var( $altra->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );
		};

		try {
			$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@autocommit' ), 'Precondizione: scrittura automatica accesa.' );
			$this->assertSame( 'pending', $legge(), 'Precondizione: la seconda connessione vede l\'atto in verifica.' );

			// Guasto sulla voce dell'albo, l'ultima scrittura: la seconda connessione guarda proprio li'.
			$this->guasto = function ( string $istruzione ) use ( $legge, &$visto ): bool {
				if ( false !== strpos( $istruzione, "'" . Passaggi::AZIONE_CONFERMA . "'" ) && 0 === strpos( $istruzione, 'INSERT' ) ) {
					$visto['durante'] = $legge();

					return true;
				}

				return false;
			};

			$wpdb->suppress_errors( true );

			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

			$this->ripara();

			$this->assertWPError( $esito );
			$this->assertSame( 'pending', $visto['durante'] ?? null, 'Mentre la transazione e\' aperta, fuori non si vede niente.' );
			$this->assertSame( 'pending', $legge(), 'Dopo l\'annullamento l\'atto e\' in verifica anche per gli altri.' );
			$this->assertSame( 'pending', get_post_status( $id ) );
			$this->assertSame( '', conformita_core_fine_pubblicazione( $id ) );
			$this->assertSame( array(), $this->voci_di( $id, array( 'azione' => 'pubblicazione' ) ) );

			// Senza guasto: confermata, e visibile agli altri.
			$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );
			$this->assertSame( 'publish', $legge(), 'La pubblicazione confermata la vede anche la seconda connessione.' );
			$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@autocommit' ), 'La scrittura automatica resta com\'era.' );
		} finally {
			$this->ripara();

			/*
			 * Tutto quello che la conferma ha reso definitivo: contenuti, voci,
			 * utenti e termini con l'aiuto della suite, registro e repertorio a
			 * mano, perche' la suite non li conosce.
			 */
			_delete_all_data();

			$segnaposto = implode( ', ', array_fill( 0, count( $contenuti ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- segnaposto costruiti qui sopra.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tabella_registro()} WHERE contenuto IN ( {$segnaposto} )", $contenuti ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Repertorio::tabella_assegnazioni() ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Repertorio::tabella_anni() ) );

			$attuali = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options}", OBJECT_K );

			foreach ( $attuali as $nome => $riga ) {
				if ( ! isset( $originali[ $nome ] ) ) {
					$wpdb->delete( $wpdb->options, array( 'option_name' => $nome ) );
				} elseif ( $originali[ $nome ]->option_value !== $riga->option_value || $originali[ $nome ]->autoload !== $riga->autoload ) {
					$wpdb->update(
						$wpdb->options,
						array(
							'option_value' => $originali[ $nome ]->option_value,
							'autoload'     => $originali[ $nome ]->autoload,
						),
						array( 'option_name' => $nome )
					);
				}
			}

			foreach ( array_diff_key( $originali, $attuali ) as $nome => $riga ) {
				$wpdb->insert(
					$wpdb->options,
					array(
						'option_name'  => $nome,
						'option_value' => $riga->option_value,
						'autoload'     => $riga->autoload,
					)
				);
			}

			wp_cache_flush();
			$altra->close();

			$wpdb->query( 'SET autocommit = 0' );
			self::commit_transaction();
		}
	}

	/**
	 * A-107: su tabelle che non annullano, la pubblicazione non comincia.
	 */
	public function test_a107_tabelle_senza_transazioni(): void {
		global $wpdb;

		$this->assertSame( array(), Passaggi::tabelle_senza_transazioni(), 'Precondizione: nel laboratorio le tabelle annullano.' );
		$this->assertSame(
			$this->tabella_registro(),
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $this->tabella_registro() ) ) ),
			'La tabella del registro ha il nome che l\'albo controlla.'
		);

		$id    = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );

		// La banca dati risponde che la tabella del registro non conosce le transazioni.
		$registro = $this->tabella_registro();
		$rispondi = static function ( $istruzione ) use ( $registro ) {
			return str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$registro}' AND t.TABLE_NAME IN (", (string) $istruzione );
		};

		add_filter( 'query', $rispondi );

		try {
			$this->assertSame( array( $registro ), Passaggi::tabelle_senza_transazioni() );

			$esito   = $this->pubblica_da_codice( $id, $this->pubblicatore );
			$rimando = $this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					return albo_pretorio_rimanda_in_bozza( $id, 'Motivo.' );
				}
			);
		} finally {
			remove_filter( 'query', $rispondi );
		}

		$this->assertSame( array( 'albo_tabelle_non_transazionali' ), $esito->get_error_codes() );
		$this->assertStringContainsString( $registro, $esito->get_error_message(), 'Il rifiuto nomina la tabella.' );
		$this->assertSame( array( 'albo_tabelle_non_transazionali' ), $rimando->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Niente cambia, e nessun numero consumato.' );

		// Controllo positivo: con le tabelle che annullano, lo stesso atto si pubblica.
		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ) );
	}

	/**
	 * A-108: il guardiano concede una scrittura sola, di un atto solo, con i soli campi concessi.
	 */
	public function test_a108_passaggio_concesso_e_nient_altro(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		global $wpdb;

		$id    = $this->atto_in_verifica();
		$altro = $this->atto_in_verifica();
		$terzo = $this->atto_in_verifica();

		// Il primo atto ha gia' un nome nell'indirizzo, scritto prima della verifica.
		$this->scavalca_barriera(
			static function () use ( $wpdb, $id ) {
				$wpdb->update( $wpdb->posts, array( 'post_name' => 'nome-verificato' ), array( 'ID' => $id ) );
			}
		);
		clean_post_cache( $id );

		// Un altro componente che, durante la scrittura, cambia oggetto, nome e data e prova a pubblicare un altro atto.
		$cambia = static function ( $data ) {
			if ( TIPO === $data['post_type'] ) {
				$data['post_name']    = 'nome-cambiato';
				$data['post_title']   = 'Oggetto cambiato di nascosto';
				$data['post_date']    = '2041-01-01 00:00:00';
				$data['post_status']  = 'private';
				$data['post_content'] = 'Testo cambiato di nascosto';
			}

			return $data;
		};
		$annida = static function ( $post_id ) use ( $id, $altro ) {
			if ( $id === (int) $post_id ) {
				wp_update_post(
					array(
						'ID'          => $altro,
						'post_status' => 'publish',
					)
				);
			}
		};

		add_filter( 'wp_insert_post_data', $cambia, 50 );
		add_action( 'save_post', $annida );

		try {
			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );
		} finally {
			remove_filter( 'wp_insert_post_data', $cambia, 50 );
			remove_action( 'save_post', $annida );
		}

		$this->assertIsArray( $esito );

		clean_post_cache( $id );
		clean_post_cache( $altro );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'Atto di prova', get_the_title( $id ), 'L\'oggetto e\' quello verificato.' );
		$this->assertSame( 'Testo di prova.', get_post_field( 'post_content', $id ) );
		$this->assertSame( 'nome-verificato', get_post_field( 'post_name', $id ), 'Il nome gia\' scritto resta.' );
		$this->assertSame( $esito['inizio'], get_post_field( 'post_date', $id ), 'La data e\' quella del sistema.' );

		// Controllo positivo: un atto senza nome lo riceve da WordPress, subito dopo la scrittura della riga.
		$this->assertSame( '', get_post_field( 'post_name', $terzo ), 'Precondizione: il terzo atto non ha nome.' );
		$this->assertIsArray( $this->pubblica_da_codice( $terzo, $this->pubblicatore ) );
		clean_post_cache( $terzo );
		$this->assertNotSame( '', get_post_field( 'post_name', $terzo ) );
		$this->assertSame( 'pending', get_post_status( $altro ), 'La concessione non vale per un altro atto.' );

		// Dopo il passaggio la concessione non c'e' piu'.
		wp_update_post(
			array(
				'ID'          => $altro,
				'post_status' => 'publish',
			)
		);

		clean_post_cache( $altro );

		$this->assertSame( 'pending', get_post_status( $altro ) );

		// Nemmeno per l'atto rimandato in bozza e tornato in verifica.
		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $altro ) {
					return albo_pretorio_rimanda_in_bozza( $altro, 'Da rivedere.' );
				}
			)
		);

		$this->come(
			$this->redattore,
			static function () use ( $altro ) {
				return wp_update_post(
					array(
						'ID'          => $altro,
						'post_status' => 'pending',
					)
				);
			}
		);

		clean_post_cache( $altro );

		$this->assertSame( 'pending', get_post_status( $altro ), 'Precondizione: di nuovo in verifica.' );

		$this->come(
			$this->pubblicatore,
			static function () use ( $altro ) {
				foreach ( array( 'publish', 'draft' ) as $stato ) {
					wp_update_post(
						array(
							'ID'          => $altro,
							'post_status' => $stato,
						)
					);
				}
			}
		);

		clean_post_cache( $altro );

		$this->assertSame( 'pending', get_post_status( $altro ), 'Senza passare dai due passaggi, resta in verifica.' );
	}

	/**
	 * A-109: il riquadro, a chi e dove, e il gettone legato all'atto.
	 */
	public function test_a109_riquadro(): void {
		global $wp_meta_boxes;

		$id     = $this->atto_in_verifica();
		$altro  = $this->atto_in_verifica();
		$bozza  = $this->atto_completo();
		$stampa = function ( int $utente, int $atto ): string {
			return $this->come(
				$utente,
				static function () use ( $atto ) {
					ob_start();
					RiquadroPassaggi::mostra( get_post( $atto ) );
					return (string) ob_get_clean();
				}
			);
		};
		$c_e    = function ( int $utente, int $atto ): bool {
			global $wp_meta_boxes;

			$wp_meta_boxes = array();

			$this->come(
				$utente,
				static function () use ( $atto ) {
					set_current_screen( TIPO );
					RiquadroPassaggi::riquadro( get_post( $atto ) );
				}
			);

			return ! empty( $wp_meta_boxes[ TIPO ]['normal']['default'][ RiquadroPassaggi::RIQUADRO ] );
		};

		$this->assertTrue( $c_e( $this->pubblicatore, $id ), 'Chi pubblica lo vede sull\'atto in verifica.' );
		$this->assertFalse( $c_e( $this->redattore, $id ), 'Chi redige no.' );
		$this->assertFalse( $c_e( $this->pubblicatore, $bozza ), 'Su una bozza non c\'e\'.' );
		$this->assertSame( '', $stampa( $this->redattore, $id ), 'Nemmeno stampato a mano.' );
		$this->assertStringContainsString( RiquadroPassaggi::CAMPO_GETTONE, $stampa( $this->pubblicatore, $id ) );
		$this->assertStringContainsString( '15 giorni', $stampa( $this->pubblicatore, $id ), 'Annuncia la durata che il sistema applichera\'.' );

		// Il gettone di un altro atto non vale.
		$url = $this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_GETTONE   => $this->come(
					$this->pubblicatore,
					static function () use ( $altro ) {
						return wp_create_nonce( RiquadroPassaggi::azione( $altro ) );
					}
				),
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'pubblica',
				RiquadroPassaggi::CAMPO_CONFERMA  => (string) $id,
			)
		);

		$this->assertStringContainsString( 'albo-rifiuto=1', $url );
		$this->assertSame( 'pending', get_post_status( $id ) );

		// Un passaggio che non esiste.
		$this->invia_riquadro( $id, $this->pubblicatore, array( RiquadroPassaggi::CAMPO_PASSAGGIO => 'annulla' ) );

		$this->assertSame( 'pending', get_post_status( $id ) );

		// Sull'atto pubblicato: numero, inizio e fine, a chiunque apra la schermata.
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		clean_post_cache( $id );

		$this->assertTrue( $c_e( $this->redattore, $id ) );

		$pubblicato = $stampa( $this->redattore, $id );

		$this->assertStringContainsString( $esito['numero'] . '/' . $esito['anno'], $pubblicato );
		$this->assertStringContainsString( wp_date( (string) get_option( 'date_format' ), ( new \DateTimeImmutable( $esito['fine'] . ' 12:00:00', wp_timezone() ) )->getTimestamp() ), $pubblicato );
		$this->assertStringNotContainsString( RiquadroPassaggi::CAMPO_GETTONE, $pubblicato, 'Nessun modulo.' );

		$wp_meta_boxes = array();
	}

	/**
	 * A-110: il permesso di leggere il registro va a chi pubblica, anche sui siti gia' installati.
	 */
	public function test_a110_permesso_del_registro(): void {
		$permesso = conformita_core_capacita_registro();

		$this->assertTrue( user_can( $this->pubblicatore, $permesso ), 'Chi pubblica legge il registro.' );
		$this->assertTrue( user_can( $this->amministratore, $permesso ), 'L\'amministratore legge il registro.' );
		$this->assertFalse( user_can( $this->redattore, $permesso ), 'Chi redige no.' );

		// Un sito installato con la versione precedente, senza questo permesso.
		foreach ( array( 'administrator', RUOLO ) as $ruolo ) {
			get_role( $ruolo )->remove_cap( $permesso );
		}

		get_role( 'editor' )->add_cap( Permessi::mappa()['publish_posts'] );
		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, '0.3.0-alpha' );

		$this->assertFalse( get_role( RUOLO )->has_cap( $permesso ), 'Precondizione: il permesso manca.' );
		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'aggiornamento gira.' );

		foreach ( array( 'administrator', RUOLO, 'editor' ) as $ruolo ) {
			$this->assertTrue( ( new \WP_Roles() )->get_role( $ruolo )->has_cap( $permesso ), 'Il permesso arriva al ruolo ' . $ruolo . ', riletto dalla banca dati.' );
		}

		$this->assertFalse( get_role( 'author' )->has_cap( $permesso ), 'Un ruolo senza i permessi dell\'albo non lo riceve.' );
	}
}
