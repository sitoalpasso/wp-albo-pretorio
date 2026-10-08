<?php
/**
 * Tutto o niente, guardiano, riquadro e permesso del registro: righe A-105..A-110, A-116..A-118.
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
use const AlboPretorioPa\TASSONOMIA_ORGANO;
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
			'voce automatica della data di fine'     => function () {
				$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', "'modifica_fine_pubblicazione'" ) );
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

		/*
		 * Lo svuotamento della memoria sospeso da un altro componente: un
		 * aggancio lo sospende dopo la scrittura dello stato e legge l'atto,
		 * cosi' la memoria ricorda l'atto pubblicato; poi la voce della
		 * conferma fallisce. Si confronta la memoria con la banca dati prima
		 * di qualunque fotografia, che svuoterebbe la memoria da se'.
		 */
		$chiave   = conformita_core_chiave_fine_pubblicazione();
		$id       = $this->atto_in_verifica();
		$letto    = null;
		$sospendi = static function ( $post_id ) use ( $id, &$letto ) {
			if ( $id === (int) $post_id && null === $letto ) {
				wp_suspend_cache_invalidation( true );
				$letto = get_post_status( $id );
			}
		};

		add_action( 'save_post', $sospendi );
		$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', "'" . Passaggi::AZIONE_CONFERMA . "'" ) );

		try {
			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

			// La memoria, letta mentre lo svuotamento e' ancora sospeso.
			$in_memoria = array( get_post_status( $id ), get_post_meta( $id, $chiave, true ) );
			$sospesa    = wp_suspend_cache_invalidation( false );
		} finally {
			$this->ripara();
			remove_action( 'save_post', $sospendi );
			wp_suspend_cache_invalidation( false );
		}

		$in_banca = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );

		$this->assertSame( 'publish', $letto, 'Precondizione: durante il passaggio la memoria ha visto l\'atto pubblicato.' );
		$this->assertSame( array( 'albo_voce_non_scritta' ), $esito->get_error_codes() );
		$this->assertTrue( $sospesa, 'La sospensione di chi l\'aveva chiesta e\' rimessa com\'era.' );
		$this->assertSame( 'pending', $in_banca );
		$this->assertSame( array( 'pending', '' ), $in_memoria, 'La memoria dice quello che dice la banca dati: in verifica, senza data di fine.' );

		/*
		 * Prima di cominciare, con lo svuotamento sospeso: la memoria ricorda
		 * l'atto in verifica, la banca dati lo ha gia' in bozza. Il passaggio
		 * legge la banca dati, e la sospensione resta quella di chi l'ha messa.
		 */
		$id = $this->atto_in_verifica();

		$this->assertSame( 'pending', get_post_status( $id ), 'Precondizione: la memoria ricorda l\'atto in verifica.' );

		wp_suspend_cache_invalidation( true );

		try {
			$this->scavalca_barriera(
				static function () use ( $wpdb, $id ) {
					$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) );
				}
			);

			$ricordato = get_post_status( $id );
			$esito     = $this->pubblica_da_codice( $id, $this->pubblicatore );
			$sospesa   = wp_suspend_cache_invalidation( false );
		} finally {
			wp_suspend_cache_invalidation( false );
		}

		$this->assertSame( 'pending', $ricordato, 'Precondizione: con lo svuotamento sospeso la memoria ricorda ancora l\'atto in verifica.' );
		$this->assertSame( array( 'albo_atto_non_in_verifica' ), $esito->get_error_codes(), 'Il passaggio legge lo stato della banca dati, non quello ricordato.' );
		$this->assertTrue( $sospesa, 'La sospensione di chi l\'aveva chiesta e\' rimessa com\'era.' );

		/*
		 * Passaggi riusciti con lo svuotamento sospeso fin dall'ingresso, come
		 * fa un importatore: subito dopo, prima di qualunque fotografia, la
		 * memoria dice quello che dice la banca dati, conteggio della voce
		 * compreso, e la sospensione resta.
		 */
		foreach ( array( 'rimando', 'pubblicazione' ) as $passaggio ) {
			$id = $this->atto_in_verifica();

			$this->assertSame( 'pending', get_post_status( $id ), $passaggio . ': precondizione, la memoria ricorda l\'atto in verifica.' );
			get_term( $this->voci['organo'] );

			wp_suspend_cache_invalidation( true );

			try {
				$esito = 'rimando' === $passaggio
					? $this->come(
						$this->pubblicatore,
						static function () use ( $id ) {
							return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
						}
					)
					: $this->pubblica_da_codice( $id, $this->pubblicatore );

				$in_memoria = array( get_post_status( $id ), get_post_meta( $id, $chiave, true ), (int) get_term( $this->voci['organo'] )->count );
				$sospesa    = wp_suspend_cache_invalidation( false );
			} finally {
				wp_suspend_cache_invalidation( false );
			}

			$this->assertNotWPError( $esito, $passaggio . ': riuscito.' );

			$organo   = get_term( $this->voci['organo'] );
			$in_banca = array(
				(string) $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) ),
				(string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $chiave ) ),
				(int) $wpdb->get_var( $wpdb->prepare( "SELECT count FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $organo->term_taxonomy_id ) ),
			);

			$this->assertSame( 'rimando' === $passaggio ? 'draft' : 'publish', $in_banca[0], $passaggio . ': precondizione, la banca dati ha lo stato scritto.' );
			$this->assertSame( $in_banca, $in_memoria, $passaggio . ': la memoria dice quello che dice la banca dati, conteggio della voce compreso.' );
			$this->assertTrue( $sospesa, $passaggio . ': la sospensione di chi l\'aveva chiesta e\' rimessa com\'era.' );
		}
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

		/*
		 * La banca dati risponde, una tabella per volta, che non conosce le
		 * transazioni: il registro, e le due tabelle delle voci, che un altro
		 * componente puo' toccare durante il passaggio. Quell'altro componente
		 * c'e': un aggancio che toglie le voci dell'organo al salvataggio. Il
		 * rifiuto arriva prima di qualunque scrittura, e l'aggancio non parte.
		 */
		$toglie_voci = static function ( $post_id ) use ( $id ) {
			if ( $id === (int) $post_id ) {
				wp_set_object_terms( $id, array(), TASSONOMIA_ORGANO );
			}
		};

		add_action( 'save_post', $toglie_voci );

		foreach ( array( $this->tabella_registro(), $wpdb->term_relationships, $wpdb->term_taxonomy ) as $tabella ) {
			$rispondi = static function ( $istruzione ) use ( $tabella ) {
				return str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$tabella}' AND t.TABLE_NAME IN (", (string) $istruzione );
			};

			add_filter( 'query', $rispondi );

			try {
				$this->assertSame( array( $tabella ), Passaggi::tabelle_senza_transazioni(), $tabella . ': controllata.' );

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

			$this->assertSame( array( 'albo_tabelle_non_transazionali' ), $esito->get_error_codes(), $tabella );
			$this->assertStringContainsString( $tabella, $esito->get_error_message(), 'Il rifiuto nomina la tabella.' );
			$this->assertSame( array( 'albo_tabelle_non_transazionali' ), $rimando->get_error_codes(), $tabella );
			$this->assertSame( $prima, $this->fotografia( $id ), $tabella . ': niente cambia, voci comprese, e nessun numero consumato.' );
		}

		remove_action( 'save_post', $toglie_voci );

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

		/*
		 * Lo stesso atto, dentro la stessa scrittura: un altro componente legge
		 * il gettone e lo usa per una scrittura annidata. Prima che il
		 * guardiano lo consumi, dal filtro con cui WordPress ripulisce quel
		 * campo della richiesta, con dati suoi o con un gettone inventato; dopo,
		 * da un filtro che gira prima del guardiano, con il gettone copiato. In
		 * tutti i casi il passaggio vero riesce, scrive la riga una volta sola,
		 * e nessuna istruzione della scrittura annidata arriva alla banca dati.
		 */
		$copiato  = null;
		$varianti = array(
			'prima del consumo, gettone con dati'  => array( 'pre_post_' . Passaggi::CAMPO_GETTONE, true, false ),
			'prima del consumo, gettone inventato' => array( 'pre_post_' . Passaggi::CAMPO_GETTONE, false, true ),
			'dopo il consumo, gettone copiato'     => array( 'wp_insert_post_data', false, false ),
		);

		foreach ( $varianti as $caso => list( $filtro, $con_dati, $inventato ) ) {
			$quarto   = $this->atto_in_verifica();
			$partita  = false;
			$istruite = array();
			$annidata = static function ( $valore, $postarr = null ) use ( $quarto, $filtro, $con_dati, $inventato, &$partita, &$copiato ) {
				$gettone = 'wp_insert_post_data' === $filtro
					? ( is_array( $postarr ) && isset( $postarr['ID'], $postarr[ Passaggi::CAMPO_GETTONE ] ) && $quarto === (int) $postarr['ID'] ? (string) $postarr[ Passaggi::CAMPO_GETTONE ] : null )
					: ( is_string( $valore ) && Passaggi::in_corso( $quarto ) ? $valore : null );

				if ( $partita || null === $gettone ) {
					return $valore;
				}

				$partita = true;
				$copiato = $gettone;
				$annido  = array(
					'ID'                    => $quarto,
					'post_status'           => 'publish',
					'post_title'            => 'Oggetto annidato',
					Passaggi::CAMPO_GETTONE => $inventato ? str_repeat( 'b', 32 ) : $gettone,
				);

				if ( $con_dati ) {
					$annido['meta_input'] = array( 'dato_annidato' => 'x' );
				}

				wp_update_post( $annido );

				return $valore;
			};
			$osserva  = static function ( $istruzione ) use ( &$istruite ) {
				$istruite[] = (string) $istruzione;

				return $istruzione;
			};

			add_filter( $filtro, $annidata, 10, 2 );
			add_filter( 'query', $osserva, PHP_INT_MAX - 1 );

			try {
				$esito = $this->pubblica_da_codice( $quarto, $this->pubblicatore );
			} finally {
				remove_filter( $filtro, $annidata, 10 );
				remove_filter( 'query', $osserva, PHP_INT_MAX - 1 );
			}

			$scritture = preg_grep( '/^UPDATE `' . preg_quote( $wpdb->posts, '/' ) . "` SET .*`post_status` = 'publish'.* WHERE `ID` = " . $quarto . '$/', $istruite );

			$this->assertTrue( $partita, $caso . ': precondizione, la scrittura annidata e\' partita.' );
			$this->assertIsArray( $esito, $caso . ': il passaggio vero riesce.' );
			$this->assertCount( 1, $scritture, $caso . ': la riga si scrive una volta sola.' );
			$this->assertSame( array(), preg_grep( '/Oggetto annidato|dato_annidato/', $istruite ), $caso . ': nessuna istruzione della scrittura annidata arriva alla banca dati.' );
			clean_post_cache( $quarto );
			$this->assertSame( 'Atto di prova', get_the_title( $quarto ), $caso );
			$this->assertSame( '', get_post_meta( $quarto, 'dato_annidato', true ), $caso );
		}

		// Il gettone copiato, dopo il passaggio, non vale su nessun atto; uno inventato nemmeno, con qualunque stato.
		$quinto = $this->atto_in_verifica();

		foreach ( array( $copiato, str_repeat( 'a', 32 ) ) as $gettone ) {
			foreach ( array( 'publish', 'future' ) as $stato ) {
				$this->come(
					$this->pubblicatore,
					static function () use ( $quinto, $gettone, $stato ) {
						return wp_update_post(
							array(
								'ID'                    => $quinto,
								'post_status'           => $stato,
								'post_date'             => '2041-12-01 00:00:00',
								Passaggi::CAMPO_GETTONE => $gettone,
							)
						);
					}
				);
			}
		}

		clean_post_cache( $quinto );
		$this->assertSame( 'pending', get_post_status( $quinto ), 'Nessun gettone apre un atto fuori dal suo passaggio.' );
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

	/**
	 * Conta le istruzioni che aprono una transazione o un punto di ripristino.
	 *
	 * @param int $aperture Contatore.
	 * @return callable Il filtro da agganciare a `query`.
	 */
	private function conta_aperture( int &$aperture ): callable {
		return static function ( $istruzione ) use ( &$aperture ) {
			if ( 1 === preg_match( '/^\s*(START TRANSACTION|SAVEPOINT )/i', (string) $istruzione ) ) {
				++$aperture;
			}

			return $istruzione;
		};
	}

	/**
	 * A-116: un passaggio non comincia mentre un altro e' in corso, nemmeno dalle funzioni dell'albo.
	 */
	public function test_a116_un_passaggio_alla_volta(): void {
		$casi = array(
			'pubblicazione, dentro la pubblicazione di un altro atto' => array( 'pubblicazione', 'pubblica', 'altro' ),
			'pubblicazione, dentro il rimando di un altro atto' => array( 'pubblicazione', 'rimanda', 'altro' ),
			'pubblicazione, dentro la pubblicazione dello stesso atto' => array( 'pubblicazione', 'pubblica', 'stesso' ),
			'rimando, dentro la pubblicazione di un altro atto' => array( 'rimando', 'pubblica', 'altro' ),
		);

		foreach ( $casi as $caso => list( $esterno, $interno, $bersaglio ) ) {
			$id          = $this->atto_in_verifica();
			$altro       = $this->atto_in_verifica();
			$prima       = $this->fotografia( $id );
			$prima_altro = $this->fotografia( $altro );
			$obiettivo   = 'stesso' === $bersaglio ? $id : $altro;
			$risposta    = null;
			$aperture    = 0;

			$aggancio = static function ( $post_id ) use ( $id, $obiettivo, $interno, &$risposta ) {
				if ( $id !== (int) $post_id || null !== $risposta ) {
					return;
				}

				$risposta = 'pubblica' === $interno
					? albo_pretorio_pubblica( $obiettivo, array( 'conferma' => true ) )
					: albo_pretorio_rimanda_in_bozza( $obiettivo, 'Da completare.' );
			};

			// Il passaggio di fuori fallisce dopo quello di dentro: il suo annullamento deve tornare indietro davvero.
			if ( 'pubblicazione' === $esterno ) {
				$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', ':' . Passaggi::AZIONE_CONFERMA . ':' . $id . "'" ) );
			} else {
				$this->guasta_istruzioni( array( 'INSERT INTO `' . $this->tabella_registro() . '`', "'" . Passaggi::AZIONE_RIMANDO . "'" ) );
			}

			$conta = $this->conta_aperture( $aperture );

			add_action( 'save_post', $aggancio, 10, 1 );
			add_filter( 'query', $conta, 1 );

			try {
				$esito = 'pubblicazione' === $esterno
					? $this->pubblica_da_codice( $id, $this->pubblicatore )
					: $this->come(
						$this->pubblicatore,
						static function () use ( $id ) {
							return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
						}
					);
			} finally {
				remove_action( 'save_post', $aggancio, 10 );
				remove_filter( 'query', $conta, 1 );
				$this->ripara();
			}

			$this->assertWPError( $risposta, $caso . ': precondizione, il passaggio di dentro e\' partito.' );
			$this->assertSame( array( 'albo_passaggio_annidato' ), $risposta->get_error_codes(), $caso . ': il passaggio di dentro e\' rifiutato prima di cominciare.' );
			$this->assertSame( 1, $aperture, $caso . ': una sola apertura, quella del passaggio di fuori.' );
			$this->assertSame( array( 'albo_voce_non_scritta' ), $esito->get_error_codes(), $caso . ': il passaggio di fuori fallisce con il suo motivo, e il suo annullamento riesce.' );

			$dopo = $this->fotografia( $id );

			unset( $prima['numero'], $dopo['numero'] );

			$this->assertSame( $prima, $dopo, $caso . ': l\'atto e\' in verifica e identico, registro compreso.' );
			$this->assertSame( $prima_altro, $this->fotografia( $altro ), $caso . ': l\'altro atto e\' in verifica e identico, senza numero.' );

			// Finito il passaggio di fuori, quello che era stato rifiutato comincia.
			$this->assertIsArray( $this->pubblica_da_codice( $altro, $this->pubblicatore ), $caso . ': controllo positivo, l\'altro atto si pubblica dopo.' );
		}
	}

	/**
	 * A-117: un annullamento che la banca dati non conferma non si dichiara riuscito.
	 */
	public function test_a117_annullamento_non_confermato(): void {
		global $wpdb;

		$casi = array(
			'pubblicazione: voce della conferma, poi annullamento' => array( 'pubblicazione', "'" . Passaggi::AZIONE_CONFERMA . "'", 'errore' ),
			'pubblicazione: chiusura, poi annullamento'  => array( 'pubblicazione', 'RELEASE SAVEPOINT albo_pretorio_passaggio', 'errore' ),
			'pubblicazione: eccezione, poi annullamento' => array( 'pubblicazione', null, 'eccezione' ),
			'rimando: voce del motivo, poi annullamento' => array( 'rimando', "'" . Passaggi::AZIONE_RIMANDO . "'", 'errore' ),
			'rimando: eccezione, poi annullamento'       => array( 'rimando', null, 'eccezione' ),
		);

		foreach ( $casi as $caso => list( $passaggio, $pezzo, $come ) ) {
			$id     = $this->atto_in_verifica();
			$lancia = static function ( $post_id ) use ( $id ) {
				if ( $id === (int) $post_id ) {
					throw new \DomainException( 'Guasto di un altro componente.' );
				}
			};

			$wpdb->suppress_errors( true );

			$this->guasto = static function ( string $istruzione ) use ( $pezzo ): bool {
				if ( 0 === strpos( $istruzione, 'ROLLBACK' ) ) {
					return true;
				}

				return null !== $pezzo && false !== strpos( $istruzione, $pezzo );
			};

			if ( 'eccezione' === $come ) {
				add_action( 'save_post', $lancia, 10, 1 );
			}

			$esito    = null;
			$lanciata = null;

			try {
				$esito = 'pubblicazione' === $passaggio
					? $this->pubblica_da_codice( $id, $this->pubblicatore )
					: $this->come(
						$this->pubblicatore,
						static function () use ( $id ) {
							return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
						}
					);
			} catch ( \RuntimeException $errore ) {
				$lanciata = $errore;
			} finally {
				remove_action( 'save_post', $lancia, 10 );
				$this->ripara();
			}

			if ( 'eccezione' === $come ) {
				$this->assertInstanceOf( \RuntimeException::class, $lanciata, $caso . ': l\'eccezione dice che l\'annullamento non e\' riuscito.' );
				$this->assertNotInstanceOf( \DomainException::class, $lanciata, $caso . ': non e\' l\'eccezione di partenza.' );
				$this->assertInstanceOf( \DomainException::class, $lanciata->getPrevious(), $caso . ': l\'eccezione di partenza e\' la causa.' );
				$this->assertStringContainsString( (string) $id, $lanciata->getMessage(), $caso . ': il messaggio nomina l\'atto.' );
			} else {
				$this->assertNull( $lanciata, $caso . ': nessuna eccezione.' );
				$this->assertSame( array( 'albo_annullamento_non_riuscito' ), $esito->get_error_codes(), $caso . ': l\'annullamento non confermato non si dichiara riuscito.' );
				$this->assertStringContainsString( (string) $id, $esito->get_error_message(), $caso . ': il messaggio nomina l\'atto.' );
			}

			// Il messaggio dice il vero: le scritture sono ancora li', finche' non si annulla davvero.
			$this->assertSame( 'pubblicazione' === $passaggio ? 'publish' : 'draft', (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) ), $caso . ': precondizione, le scritture ci sono ancora.' );

			$wpdb->query( 'ROLLBACK TO SAVEPOINT albo_pretorio_passaggio' );
			wp_cache_flush();

			$this->assertSame( 'pending', get_post_status( $id ), $caso . ': annullato a mano, l\'atto torna in verifica.' );
		}
	}

	/**
	 * A-118: dopo la chiusura il passaggio non e' piu' in corso, e niente si annulla.
	 *
	 * Lo svuotamento finale della memoria fa girare gli agganci di altri
	 * componenti: qui uno prova a togliere la data di fine appena confermata,
	 * un altro solleva un'eccezione.
	 */
	public function test_a118_dopo_la_chiusura(): void {
		global $wpdb;

		$chiave = conformita_core_chiave_fine_pubblicazione();
		$casi   = array(
			'pubblicazione, fine tolta dopo la chiusura' => array( 'pubblicazione', 'togli' ),
			'pubblicazione, eccezione dopo la chiusura'  => array( 'pubblicazione', 'lancia' ),
			'rimando, eccezione dopo la chiusura'        => array( 'rimando', 'lancia' ),
		);

		foreach ( $casi as $caso => list( $passaggio, $azione ) ) {
			$id           = $this->atto_in_verifica();
			$chiuso       = false;
			$annullamenti = 0;
			$tolta        = null;
			$lanciata     = null;
			$esito        = null;

			$spia = static function ( $istruzione ) use ( &$chiuso, &$annullamenti ) {
				$istruzione = ltrim( (string) $istruzione );

				if ( 'COMMIT' === $istruzione || 0 === strpos( $istruzione, 'RELEASE SAVEPOINT albo_pretorio_passaggio' ) ) {
					$chiuso = true;
				} elseif ( $chiuso && 0 === stripos( $istruzione, 'ROLLBACK' ) ) {
					++$annullamenti;
				}

				return $istruzione;
			};

			$aggancio = static function ( $post_id ) use ( $id, $chiave, $azione, &$chiuso, &$tolta ) {
				if ( $id !== (int) $post_id || ! $chiuso || null !== $tolta ) {
					return;
				}

				if ( 'togli' === $azione ) {
					$tolta = delete_post_meta( $id, $chiave );

					return;
				}

				$tolta = false;

				throw new \DomainException( 'Guasto di un altro componente.' );
			};

			add_filter( 'query', $spia, 1 );
			add_action( 'clean_post_cache', $aggancio, 10, 1 );

			try {
				$esito = 'pubblicazione' === $passaggio
					? $this->pubblica_da_codice( $id, $this->pubblicatore )
					: $this->come(
						$this->pubblicatore,
						static function () use ( $id ) {
							return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
						}
					);
			} catch ( \RuntimeException $errore ) {
				$lanciata = $errore;
			} finally {
				remove_filter( 'query', $spia, 1 );
				remove_action( 'clean_post_cache', $aggancio, 10 );
			}

			$this->assertTrue( $chiuso, $caso . ': precondizione, il passaggio si e\' chiuso.' );
			$this->assertNotNull( $tolta, $caso . ': precondizione, l\'aggancio e\' partito dopo la chiusura.' );
			$this->assertSame( 0, $annullamenti, $caso . ': dopo la chiusura nessun annullamento.' );

			$stato = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) );
			$fine  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, $chiave ) );

			if ( 'pubblicazione' === $passaggio ) {
				$this->assertSame( 'publish', $stato, $caso . ': l\'atto e\' pubblicato.' );
				$this->assertNotSame( '', $fine, $caso . ': con la sua data di fine.' );
			} else {
				$this->assertSame( 'draft', $stato, $caso . ': l\'atto e\' in bozza.' );
			}

			if ( 'togli' === $azione ) {
				$this->assertNull( $lanciata, $caso . ': nessuna eccezione.' );
				$this->assertFalse( $tolta, $caso . ': la data di fine di un atto pubblicato non si toglie, nemmeno subito dopo la chiusura.' );
				$this->assertIsArray( $esito, $caso . ': riuscita.' );
				$this->assertSame( $esito['fine'], $fine, $caso . ': la fine e\' quella calcolata.' );
				$this->assertSame( $fine, get_post_meta( $id, $chiave, true ), $caso . ': anche in memoria.' );
			} else {
				$this->assertInstanceOf( \RuntimeException::class, $lanciata, $caso . ': l\'eccezione arriva a chi chiama.' );
				$this->assertInstanceOf( \DomainException::class, $lanciata->getPrevious(), $caso . ': con l\'eccezione del componente come causa.' );
				$this->assertStringContainsString( (string) $id, $lanciata->getMessage(), $caso . ': il messaggio nomina l\'atto.' );
				$this->assertNull( $esito, $caso . ': nessun esito restituito.' );
			}
		}
	}
}
