<?php
/**
 * Numero di repertorio: righe A-58..A-68.
 *
 * Ogni esito si verifica rileggendo le due tabelle dalla banca dati, non la
 * risposta della funzione. Gli istanti sono espliciti e scelti dalla prova, in
 * anni lontani da quello in corso, salvo dove la riga parla della schermata,
 * che usa l'orologio del sito.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Installazione;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchermataRepertorio;
use AlboPretorioPa\TipoAtto;

/*
 * Le prove leggono le tabelle del repertorio direttamente, e deve essere cosi':
 * l'esito si verifica su cio' che la banca dati contiene, non su una cache ne'
 * sulla risposta del componente. La prova di concorrenza lancia processi veri.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open

/**
 * Prove del contatore, della partenza dichiarata e della schermata.
 */
class RepertorioTest extends \WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Un istante di un anno di prova, in tempo universale.
	 */
	private const ISTANTE = '2031-03-10T09:00:00+00:00';

	/**
	 * L'anno di quell'istante.
	 */
	private const ANNO = 2031;

	/**
	 * Amministratore corrente.
	 *
	 * @var int
	 */
	private $amministratore = 0;

	/**
	 * Tipo, permessi e un amministratore.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		Permessi::applica();

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->amministratore );

		$this->assertTrue( Repertorio::tabelle_presenti(), 'Precondizione: le tabelle del repertorio devono esistere.' );
		$this->assertSame( array(), $this->righe_anni(), 'Precondizione: nessun anno.' );
		$this->assertSame( array(), $this->righe_assegnazioni(), 'Precondizione: nessuna assegnazione.' );
	}

	/**
	 * Ripulisce modulo simulato, fuso e ambiente.
	 */
	public function tear_down(): void {
		unset(
			$_POST[ SchermataRepertorio::CAMPO_GETTONE ],
			$_POST[ SchermataRepertorio::CAMPO_ULTIMO ],
			$_POST['action']
		);
		remove_all_filters( 'wp_redirect' );
		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * Un atto nuovo, in bozza.
	 *
	 * @return int
	 */
	private function atto(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Un istante, in tempo universale salvo diversa indicazione.
	 *
	 * @param string $quando Istante in formato ISO.
	 * @return \DateTimeImmutable
	 */
	private function istante( string $quando = self::ISTANTE ): \DateTimeImmutable {
		return new \DateTimeImmutable( $quando );
	}

	/**
	 * Le righe della tabella degli anni, lette dalla banca dati.
	 *
	 * @return array<int, array<string, string|null>>
	 */
	private function righe_anni(): array {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY anno', Repertorio::tabella_anni() ), ARRAY_A );
	}

	/**
	 * Le righe della tabella delle assegnazioni, senza l'istante, lette dalla banca dati.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function righe_assegnazioni(): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT anno, numero, atto_id FROM %i ORDER BY anno, numero', Repertorio::tabella_assegnazioni() ),
			ARRAY_A
		);
	}

	/**
	 * Una riga di assegnazione come la restituisce la banca dati.
	 *
	 * @param int $anno    Anno.
	 * @param int $numero  Numero.
	 * @param int $atto_id Atto.
	 * @return array<string, string>
	 */
	private function assegnazione( int $anno, int $numero, int $atto_id ): array {
		return array(
			'anno'    => (string) $anno,
			'numero'  => (string) $numero,
			'atto_id' => (string) $atto_id,
		);
	}

	/**
	 * Un utente con i soli permessi di chi redige.
	 *
	 * @return int
	 */
	private function redattore(): int {
		$mappa = Permessi::mappa();

		$this->assertIsArray( $mappa, 'Precondizione: la corrispondenza dei permessi deve esserci.' );

		$id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$utente = new \WP_User( $id );

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			$utente->add_cap( $mappa[ $chiave ] );
		}

		$this->assertFalse( user_can( $id, $mappa['publish_posts'] ), 'Precondizione: non deve poter pubblicare.' );

		return $id;
	}

	/**
	 * A-58: nel primo anno d'uso, senza dichiarazione, non si numera niente.
	 */
	public function test_a58_primo_anno_senza_dichiarazione_non_numera(): void {
		$atto  = $this->atto();
		$esito = Repertorio::assegna( $atto, $this->istante() );

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_repertorio_partenza_non_dichiarata', $esito->get_error_code() );
		$this->assertStringContainsString( (string) self::ANNO, $esito->get_error_message() );
		$this->assertSame( array(), $this->righe_anni(), 'Nessun anno aperto.' );
		$this->assertSame( array(), $this->righe_assegnazioni(), 'Nessuna assegnazione.' );
		$this->assertNull( Repertorio::di( $atto ) );

		// Controllo positivo: dichiarato 121, si parte da 122.
		$this->assertTrue( Repertorio::dichiara( 121, $this->amministratore, $this->istante() ) );

		$secondo = $this->atto();

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 122,
			),
			Repertorio::assegna( $atto, $this->istante() )
		);
		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 123,
			),
			Repertorio::assegna( $secondo, $this->istante() )
		);
		$this->assertSame(
			array(
				$this->assegnazione( self::ANNO, 122, $atto ),
				$this->assegnazione( self::ANNO, 123, $secondo ),
			),
			$this->righe_assegnazioni()
		);

		$anni = $this->righe_anni();

		$this->assertCount( 1, $anni );
		$this->assertSame( '123', $anni[0]['ultimo'] );
		$this->assertSame( '121', $anni[0]['partenza'] );
		$this->assertSame( Repertorio::APERTURA_DICHIARAZIONE, $anni[0]['apertura'] );
		$this->assertSame( (string) $this->amministratore, $anni[0]['dichiarato_da'] );
	}

	/**
	 * A-59: zero e' una partenza; le dichiarazioni sbagliate non lasciano niente.
	 */
	public function test_a59_dichiarazioni_sbagliate_rifiutate_una_per_volta(): void {
		$sbagliate = array(
			'vuota'                  => '',
			'negativa'               => -1,
			'negativa come testo'    => '-1',
			'con la virgola'         => '12,5',
			'seguita da lettere'     => '12a',
			'seguita da un a capo'   => "12\n",
			'con spazi intorno'      => ' 12 ',
			'oltre la colonna'       => Repertorio::MASSIMO,
			'oltre la colonna testo' => (string) Repertorio::MASSIMO,
			'frazione'               => 12.0,
			'con separatore'         => '1_000',
			'esponenziale'           => '1e3',
			'nulla'                  => null,
			'vero'                   => true,
			'elenco'                 => array( 12 ),
		);

		foreach ( $sbagliate as $caso => $valore ) {
			$esito = Repertorio::dichiara( $valore, $this->amministratore, $this->istante() );

			$this->assertWPError( $esito, "Deve essere rifiutata: {$caso}." );
			$this->assertSame( 'albo_repertorio_partenza_non_valida', $esito->get_error_code(), $caso );
			$this->assertSame( array(), $this->righe_anni(), "Niente scritto: {$caso}." );
		}

		$this->assertTrue( Repertorio::dichiara( '0', $this->amministratore, $this->istante() ), 'Zero e\' una partenza.' );

		$atto = $this->atto();

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 1,
			),
			Repertorio::assegna( $atto, $this->istante() )
		);
		$this->assertSame( array( $this->assegnazione( self::ANNO, 1, $atto ) ), $this->righe_assegnazioni() );
	}

	/**
	 * A-60: la partenza si corregge fino al primo numero, poi non piu'.
	 */
	public function test_a60_partenza_bloccata_dopo_il_primo_numero(): void {
		$altro = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( Repertorio::dichiara( 121, $this->amministratore, $this->istante() ) );
		$this->assertTrue( Repertorio::dichiara( 124, $this->amministratore, $this->istante() ), 'La correzione prima del primo numero riesce.' );

		$primo = $this->atto();

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 125,
			),
			Repertorio::assegna( $primo, $this->istante() )
		);

		$fotografia = $this->righe_anni();

		foreach ( array( 130, 100, 124 ) as $valore ) {
			$esito = Repertorio::dichiara( $valore, $altro, $this->istante( '2031-05-01T09:00:00+00:00' ) );

			$this->assertWPError( $esito, "Dopo il primo numero deve essere rifiutata: {$valore}." );
			$this->assertSame( 'albo_repertorio_partenza_bloccata', $esito->get_error_code() );
			$this->assertSame( $fotografia, $this->righe_anni(), "Riga dell'anno identica dopo {$valore}." );
		}

		$secondo = $this->atto();

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 126,
			),
			Repertorio::assegna( $secondo, $this->istante() )
		);
	}

	/**
	 * A-61: l'anno dopo prosegue da 1, e non si dichiara.
	 */
	public function test_a61_cambio_d_anno_per_prosecuzione(): void {
		$this->assertTrue( Repertorio::dichiara( 121, $this->amministratore, $this->istante() ) );
		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 122,
			),
			Repertorio::assegna( $this->atto(), $this->istante() )
		);

		$nuovo = $this->istante( '2032-01-05T09:00:00+00:00' );

		// Prima di ogni numero dell'anno nuovo: la dichiarazione non si fa comunque.
		$prima = Repertorio::dichiara( 50, $this->amministratore, $nuovo );

		$this->assertWPError( $prima );
		$this->assertSame( 'albo_repertorio_partenza_non_dichiarabile', $prima->get_error_code() );
		$this->assertCount( 1, $this->righe_anni(), 'L\'anno nuovo non si apre per dichiarazione.' );

		$atto    = $this->atto();
		$assegna = Repertorio::assegna( $atto, $nuovo );

		$this->assertSame(
			array(
				'anno'   => 2032,
				'numero' => 1,
			),
			$assegna
		);

		$anni = $this->righe_anni();

		$this->assertCount( 2, $anni );
		$this->assertSame( '2032', $anni[1]['anno'] );
		$this->assertSame( Repertorio::APERTURA_PROSECUZIONE, $anni[1]['apertura'] );
		$this->assertSame( '0', $anni[1]['partenza'] );
		$this->assertSame( '1', $anni[1]['ultimo'] );
		$this->assertSame( '0', $anni[1]['dichiarato_da'] );
		$this->assertNull( $anni[1]['dichiarato_il'] );

		$esito = Repertorio::dichiara( 50, $this->amministratore, $nuovo );

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_repertorio_partenza_non_dichiarabile', $esito->get_error_code() );
		$this->assertSame( $anni, $this->righe_anni(), 'Nessun anno cambiato.' );
		$this->assertSame(
			array(
				'anno'   => 2032,
				'numero' => 2,
			),
			Repertorio::assegna( $this->atto(), $nuovo )
		);
	}

	/**
	 * A-62: l'anno cambia alla mezzanotte del fuso del sito.
	 */
	public function test_a62_anno_nel_fuso_del_sito(): void {
		update_option( 'timezone_string', 'Europe/Rome' );

		$this->assertTrue( Repertorio::dichiara( 0, $this->amministratore, $this->istante( '2031-06-01T09:00:00+00:00' ) ) );

		// Tutti e due nel 2031 in tempo universale; a cavallo della mezzanotte di Roma.
		$prima = $this->istante( '2031-12-31T22:30:00+00:00' );
		$dopo  = $this->istante( '2031-12-31T23:30:00+00:00' );

		$this->assertSame( '2031', $prima->format( 'Y' ), 'Precondizione: primo istante nel 2031 in tempo universale.' );
		$this->assertSame( '2031', $dopo->format( 'Y' ), 'Precondizione: secondo istante nel 2031 in tempo universale.' );

		$this->assertSame(
			array(
				'anno'   => 2031,
				'numero' => 1,
			),
			Repertorio::assegna( $this->atto(), $prima )
		);
		$this->assertSame(
			array(
				'anno'   => 2032,
				'numero' => 1,
			),
			Repertorio::assegna( $this->atto(), $dopo )
		);
	}

	/**
	 * A-63: un numero per atto, e nessun numero consumato da chi non e' un atto.
	 */
	public function test_a63_una_volta_per_atto(): void {
		$this->assertTrue( Repertorio::dichiara( 121, $this->amministratore, $this->istante() ) );

		$atto  = $this->atto();
		$primo = Repertorio::assegna( $atto, $this->istante() );

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 122,
			),
			$primo
		);

		$fotografia_anni = $this->righe_anni();

		$this->assertSame( $primo, Repertorio::assegna( $atto, $this->istante( '2032-02-01T09:00:00+00:00' ) ), 'La seconda chiamata restituisce lo stesso numero, anche con un altro anno.' );
		$this->assertSame( $fotografia_anni, $this->righe_anni(), 'Il contatore non si muove.' );

		$pagina = self::factory()->post->create( array( 'post_type' => 'page' ) );

		foreach ( array( $pagina, $pagina + 100000 ) as $id ) {
			$esito = Repertorio::assegna( $id, $this->istante() );

			$this->assertWPError( $esito );
			$this->assertSame( 'albo_repertorio_non_un_atto', $esito->get_error_code() );
			$this->assertSame( $fotografia_anni, $this->righe_anni(), 'Nessun numero consumato.' );
		}

		$this->assertSame( array( $this->assegnazione( self::ANNO, 122, $atto ) ), $this->righe_assegnazioni() );
	}

	/**
	 * A-64: il numero di un atto cancellato non torna disponibile.
	 */
	public function test_a64_mai_riusato(): void {
		$this->assertTrue( Repertorio::dichiara( 121, $this->amministratore, $this->istante() ) );

		$cancellato = $this->atto();

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 122,
			),
			Repertorio::assegna( $cancellato, $this->istante() )
		);
		$this->assertInstanceOf( \WP_Post::class, wp_delete_post( $cancellato, true ) );
		$this->assertNull( get_post( $cancellato ), 'Precondizione: l\'atto non esiste piu\'.' );

		$nuovo = $this->atto();

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 123,
			),
			Repertorio::assegna( $nuovo, $this->istante() )
		);
		$this->assertSame(
			array(
				$this->assegnazione( self::ANNO, 122, $cancellato ),
				$this->assegnazione( self::ANNO, 123, $nuovo ),
			),
			$this->righe_assegnazioni()
		);
	}

	/**
	 * A-65: processi separati e simultanei non producono numeri doppi.
	 *
	 * **Questa prova scrive davvero nella banca dati.** I processi figli hanno
	 * una connessione loro e non vedono niente di cio' che la prova tiene nella
	 * sua transazione, quindi gli atti e la dichiarazione si confermano prima di
	 * lanciarli. Quello che viene confermato si toglie alla fine, anche se la
	 * prova fallisce.
	 */
	public function test_a65_concorrenza_fra_processi(): void {
		global $wpdb, $table_prefix;

		$processi  = 6;
		$per_ogni  = 30;
		$istante   = '2041-06-15T10:00:00+00:00';
		$anno      = 2041;
		$partenza  = 121;
		$conteso   = $this->atto();
		$gruppi    = array();
		$tutti     = array( $conteso );
		$cartella  = get_temp_dir() . 'albo-concorrenza-' . wp_generate_password( 8, false );
		$core_file = albo_pretorio_file_core();

		for ( $i = 0; $i < $processi; $i++ ) {
			$gruppi[ $i ] = array( $conteso );

			for ( $j = 0; $j < $per_ogni; $j++ ) {
				$id             = $this->atto();
				$gruppi[ $i ][] = $id;
				$tutti[]        = $id;
			}
		}

		$this->assertTrue( Repertorio::dichiara( $partenza, $this->amministratore, $this->istante( $istante ) ) );
		$this->assertNotSame( '', $core_file, 'Precondizione: il componente comune deve essere raggiungibile dai figli.' );
		$this->assertTrue( wp_mkdir_p( $cartella ) );

		self::commit_transaction();

		$tubi  = array();
		$figli = array();

		try {
			for ( $i = 0; $i < $processi; $i++ ) {
				$ambiente                     = getenv();
				$ambiente['ALBO_CONCORRENZA'] = wp_json_encode(
					array(
						'abspath'     => ABSPATH,
						'db_name'     => DB_NAME,
						'db_user'     => DB_USER,
						'db_password' => DB_PASSWORD,
						'db_host'     => DB_HOST,
						'prefisso'    => $table_prefix,
						'core'        => $core_file,
						'albo'        => \AlboPretorioPa\FILE_PRINCIPALE,
						'cartella'    => $cartella,
						'indice'      => $i,
						'istante'     => $istante,
						'atti'        => $gruppi[ $i ],
					)
				);

				$figli[ $i ] = proc_open(
					array( PHP_BINARY, __DIR__ . '/concorrenza/assegna.php' ),
					array(
						1 => array( 'pipe', 'w' ),
						2 => array( 'pipe', 'w' ),
					),
					$tubi[ $i ],
					null,
					$ambiente
				);

				$this->assertIsResource( $figli[ $i ], "Il processo {$i} deve partire." );
			}

			$limite = microtime( true ) + 60;

			do {
				$pronti = count( (array) glob( $cartella . '/pronto-*' ) );

				$this->assertLessThan( $limite, microtime( true ), 'I processi figli non sono diventati pronti.' );
				usleep( 10000 );
			} while ( $pronti < $processi );

			touch( $cartella . '/via' );

			$risultati = array();

			for ( $i = 0; $i < $processi; $i++ ) {
				$uscita = stream_get_contents( $tubi[ $i ][1] );
				$errori = stream_get_contents( $tubi[ $i ][2] );
				$codice = proc_close( $figli[ $i ] );

				$figli[ $i ] = null;

				$this->assertSame( 0, $codice, "Il processo {$i} e' terminato male: {$errori}" );

				$risultati[ $i ] = json_decode( (string) $uscita, true );

				$this->assertIsArray( $risultati[ $i ], "Il processo {$i} non ha restituito esiti: {$uscita} {$errori}" );
			}

			// Una transazione nuova, per vedere quello che i figli hanno confermato.
			self::commit_transaction();

			// Controllo di realta': i processi si sono sovrapposti davvero.
			$inizi = array_column( $risultati, 'inizio' );
			$fini  = array_column( $risultati, 'fine' );

			$this->assertLessThan( min( $fini ), max( $inizi ), 'I processi non si sono sovrapposti: la prova non misurerebbe niente.' );

			$righe = $wpdb->get_results(
				$wpdb->prepare( 'SELECT numero, atto_id FROM %i WHERE anno = %d', Repertorio::tabella_assegnazioni(), $anno ),
				ARRAY_A
			);

			$numeri      = array_map( 'intval', array_column( $righe, 'numero' ) );
			$atti        = array_map( 'intval', array_column( $righe, 'atto_id' ) );
			$per_atto    = array_combine( $atti, $numeri );
			$riga_anno   = Repertorio::anno( $anno );
			$attesi      = $tutti;
			$ricevuti    = $atti;
			$conteso_num = $per_atto[ $conteso ] ?? null;

			sort( $attesi );
			sort( $ricevuti );

			$this->assertSame( $attesi, $ricevuti, 'Ogni atto deve avere un numero, e uno solo.' );
			$this->assertSame( count( $numeri ), count( array_unique( $numeri ) ), 'Nessun numero doppio.' );
			$this->assertNotNull( $conteso_num, 'L\'atto conteso deve avere un numero.' );
			$this->assertGreaterThan( $partenza, min( $numeri ), 'Nessun numero sotto la partenza.' );
			$this->assertLessThanOrEqual( $riga_anno['ultimo'], max( $numeri ), 'Nessun numero oltre il contatore.' );

			foreach ( $risultati as $i => $risultato ) {
				foreach ( $risultato['esiti'] as $esito ) {
					$this->assertIsArray( $esito['esito'], "Il processo {$i} ha ricevuto un rifiuto per l'atto {$esito['atto']}." );
					$this->assertSame( $per_atto[ $esito['atto'] ], $esito['esito']['numero'], "Il processo {$i} ha ricevuto un numero diverso da quello registrato." );
				}
			}
		} finally {
			foreach ( $figli as $figlio ) {
				if ( is_resource( $figlio ) ) {
					proc_terminate( $figlio );
				}
			}

			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Repertorio::tabella_assegnazioni() ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Repertorio::tabella_anni() ) );

			foreach ( $tutti as $id ) {
				wp_delete_post( $id, true );
			}

			self::commit_transaction();

			foreach ( (array) glob( $cartella . '/*' ) as $file ) {
				unlink( $file );
			}

			rmdir( $cartella );
		}
	}

	/**
	 * A-66: nessuna richiesta che scrive metadati tocca il numero.
	 */
	public function test_a66_richiesta_manipolata_non_tocca_il_numero(): void {
		require_once ABSPATH . 'wp-admin/includes/post.php';

		$this->assertTrue( Repertorio::dichiara( 121, $this->amministratore, $this->istante() ) );

		$atto   = $this->atto();
		$numero = Repertorio::assegna( $atto, $this->istante() );

		$this->assertSame(
			array(
				'anno'   => self::ANNO,
				'numero' => 122,
			),
			$numero
		);

		$anni         = $this->righe_anni();
		$assegnazioni = $this->righe_assegnazioni();

		wp_update_post(
			array(
				'ID'         => $atto,
				'meta_input' => array(
					'albo_pretorio_repertorio'      => '1/1999',
					'albo_pretorio_repertorio_anni' => '1999',
					'numero'                        => 1,
					'anno'                          => 1999,
				),
			)
		);

		$_POST = array(
			'post_ID'              => $atto,
			'post_type'            => \AlboPretorioPa\TIPO,
			'post_title'           => 'Atto manipolato',
			'metakeyinput'         => 'albo_pretorio_repertorio',
			'metavalue'            => '7/1999',
			'numero'               => '7',
			'anno'                 => '1999',
			'repertorio'           => '7/1999',
			'original_post_status' => 'draft',
		);

		edit_post();

		$_POST = array();

		$this->assertSame( $numero, Repertorio::di( $atto ), 'Il numero deve restare quello del sistema.' );
		$this->assertSame( $anni, $this->righe_anni() );
		$this->assertSame( $assegnazioni, $this->righe_assegnazioni() );

		$dichiarati = array_merge(
			array_keys( get_registered_meta_keys( 'post', \AlboPretorioPa\TIPO ) ),
			array_keys( get_registered_meta_keys( 'post', '' ) )
		);

		foreach ( $dichiarati as $chiave ) {
			$this->assertStringNotContainsString( 'repertorio', (string) $chiave, 'Nessun metadato del numero dichiarato a WordPress.' );
		}

		foreach ( array_keys( rest_get_server()->get_routes() ) as $rotta ) {
			$this->assertStringNotContainsString( 'repertorio', $rotta, 'Nessuna rotta del repertorio.' );
			$this->assertStringNotContainsString( 'numerazione', $rotta, 'Nessuna rotta della numerazione.' );
		}
	}

	/**
	 * Invia davvero il modulo della numerazione, e ferma il reindirizzamento finale.
	 *
	 * @param string|null $gettone Gettone, o nullo per uno valido dell'utente corrente.
	 * @param string      $ultimo  Valore del campo.
	 * @return string Indirizzo a cui l'invio rimanda.
	 */
	private function invia( ?string $gettone, string $ultimo ): string {
		$_POST['action']                             = SchermataRepertorio::AZIONE;
		$_POST[ SchermataRepertorio::CAMPO_GETTONE ] = null === $gettone ? wp_create_nonce( SchermataRepertorio::AZIONE ) : $gettone;
		$_POST[ SchermataRepertorio::CAMPO_ULTIMO ]  = $ultimo;

		add_filter(
			'wp_redirect',
			static function ( $indirizzo ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- non arriva mai a una pagina: lo raccoglie la prova.
				throw new \RuntimeException( 'reindirizzato:' . $indirizzo );
			}
		);

		try {
			do_action( 'admin_post_' . SchermataRepertorio::AZIONE );
		} catch ( \RuntimeException $rimando ) {
			remove_all_filters( 'wp_redirect' );

			return substr( $rimando->getMessage(), strlen( 'reindirizzato:' ) );
		}

		remove_all_filters( 'wp_redirect' );
		$this->fail( 'L\'invio deve rimandare alla schermata.' );
	}

	/**
	 * Quello che la schermata mostra all'utente corrente.
	 *
	 * @return string
	 */
	private function schermata(): string {
		require_once ABSPATH . 'wp-admin/includes/template.php';

		ob_start();

		try {
			SchermataRepertorio::mostra();
		} finally {
			$stampato = (string) ob_get_clean();
		}

		return $stampato;
	}

	/**
	 * Vero se la schermata nega l'accesso all'utente corrente.
	 *
	 * @return bool
	 */
	private function schermata_negata(): bool {
		try {
			$this->schermata();
		} catch ( \WPDieException $negata ) {
			return true;
		}

		return false;
	}

	/**
	 * A-67: la schermata controlla gettone e permesso, e smette di offrire il campo.
	 */
	public function test_a67_schermata_della_numerazione(): void {
		$anno         = (int) current_datetime()->format( 'Y' );
		$responsabile = self::factory()->user->create( array( 'role' => \AlboPretorioPa\RUOLO ) );
		$redattore    = $this->redattore();
		$permesso     = SchermataRepertorio::permesso();

		$this->assertNotNull( $permesso );
		$this->assertTrue( user_can( $responsabile, $permesso ), 'Precondizione: il responsabile pubblica.' );
		$this->assertFalse( user_can( $responsabile, 'manage_options' ), 'Precondizione: il responsabile non e\' amministratore.' );

		// L'avviso, prima: a chi pubblica si', a chi redige no.
		wp_set_current_user( $responsabile );
		$this->assertStringContainsString( SchermataRepertorio::PAGINA, $this->avvisi_in_bacheca( true ) );

		wp_set_current_user( $redattore );
		$this->assertStringNotContainsString( SchermataRepertorio::PAGINA, $this->avvisi_in_bacheca( true ) );

		// Il menu: a chi redige no, a chi pubblica si'.
		set_current_screen( 'dashboard' );
		$GLOBALS['submenu'] = array();
		SchermataRepertorio::menu();
		$this->assertStringNotContainsString( SchermataRepertorio::PAGINA, (string) wp_json_encode( $GLOBALS['submenu'] ) );

		wp_set_current_user( $responsabile );
		SchermataRepertorio::menu();
		$this->assertStringContainsString( SchermataRepertorio::PAGINA, (string) wp_json_encode( $GLOBALS['submenu'] ) );

		// Gettone sbagliato.
		$this->assertStringContainsString( SchermataRepertorio::PAGINA, $this->invia( 'sbagliato', '121' ) );
		$this->assertSame( array(), $this->righe_anni() );
		$this->assertStringContainsString( 'modulo', (string) SchermataRepertorio::preleva_rifiuto() );

		// Chi redige soltanto, con un gettone suo valido.
		wp_set_current_user( $redattore );
		$this->invia( null, '121' );
		$this->assertSame( array(), $this->righe_anni() );
		$this->assertStringContainsString( 'permesso', (string) SchermataRepertorio::preleva_rifiuto() );

		$this->assertTrue( $this->schermata_negata(), 'A chi redige soltanto la schermata e\' negata.' );

		// Chi pubblica, con un valore sbagliato.
		wp_set_current_user( $responsabile );
		$this->invia( null, '12a' );
		$this->assertSame( array(), $this->righe_anni() );
		$this->assertStringContainsString( 'numero intero', (string) SchermataRepertorio::preleva_rifiuto() );

		$prima = $this->schermata();

		$this->assertStringContainsString( SchermataRepertorio::CAMPO_ULTIMO, $prima, 'Prima della dichiarazione il campo c\'e\'.' );
		$this->assertStringContainsString(
			'name="' . SchermataRepertorio::CAMPO_ULTIMO . '" value=""',
			$prima,
			'Il campo non e\' precompilato.'
		);

		// Chi pubblica, con 121.
		$this->invia( null, '121' );
		$this->assertNull( SchermataRepertorio::preleva_rifiuto() );

		$anni = $this->righe_anni();

		$this->assertCount( 1, $anni );
		$this->assertSame( '121', $anni[0]['partenza'] );
		$this->assertSame( (string) $responsabile, $anni[0]['dichiarato_da'] );
		$this->assertStringContainsString( '122/' . $anno, $this->schermata() );
		$this->assertStringNotContainsString( SchermataRepertorio::PAGINA, $this->avvisi_in_bacheca( true ), 'Dopo la dichiarazione l\'avviso sparisce.' );

		// Il primo numero chiude la dichiarazione.
		$this->assertSame(
			array(
				'anno'   => $anno,
				'numero' => 122,
			),
			Repertorio::assegna( $this->atto(), current_datetime() )
		);

		$dopo = $this->schermata();

		$this->assertStringContainsString( '123/' . $anno, $dopo );
		$this->assertStringNotContainsString( SchermataRepertorio::CAMPO_ULTIMO, $dopo, 'Dopo il primo numero il campo non c\'e\' piu\'.' );

		$fotografia = $this->righe_anni();

		$this->invia( null, '130' );
		$this->assertSame( $fotografia, $this->righe_anni(), 'Un invio costruito a mano dopo il primo numero non cambia niente.' );
		$this->assertNotNull( SchermataRepertorio::preleva_rifiuto() );
	}

	/**
	 * A-68: le tabelle, i loro vincoli, e il rifiuto quando mancano.
	 */
	public function test_a68_tabelle_vincoli_e_assenza(): void {
		global $wpdb;

		$indici = array();

		foreach ( $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', Repertorio::tabella_assegnazioni() ), ARRAY_A ) as $indice ) {
			$indici[ $indice['Key_name'] ]['unico']     = '0' === (string) $indice['Non_unique'];
			$indici[ $indice['Key_name'] ]['colonne'][] = $indice['Column_name'];
		}

		$this->assertSame(
			array(
				'PRIMARY' => array(
					'unico'   => true,
					'colonne' => array( 'anno', 'numero' ),
				),
				'atto_id' => array(
					'unico'   => true,
					'colonne' => array( 'atto_id' ),
				),
			),
			$indici,
			'La coppia anno e numero, e l\'atto, devono essere unici per la banca dati.'
		);

		// Una versione nuova passa le due tabelle alla creazione.
		$passate = array();

		add_filter(
			'dbdelta_queries',
			static function ( $istruzioni ) use ( &$passate ) {
				$passate = array_merge( $passate, (array) $istruzioni );
				return $istruzioni;
			}
		);

		delete_option( \AlboPretorioPa\OPZIONE_VERSIONE );
		$this->assertTrue( Installazione::aggiorna_se_serve() );
		remove_all_filters( 'dbdelta_queries' );

		$testo = implode( "\n", $passate );

		$this->assertStringContainsString( 'CREATE TABLE ' . Repertorio::tabella_anni() . ' ', $testo );
		$this->assertStringContainsString( 'CREATE TABLE ' . Repertorio::tabella_assegnazioni() . ' ', $testo );

		// Senza tabelle: rifiuti con il motivo, niente errori della banca dati.
		$atto         = $this->atto();
		$prefisso     = $wpdb->prefix;
		$wpdb->prefix = 'wptests_mancante_';

		try {
			ob_start();

			$assegna  = Repertorio::assegna( $atto, $this->istante() );
			$dichiara = Repertorio::dichiara( 121, $this->amministratore, $this->istante() );
			$prossimo = Repertorio::prossimo( self::ANNO );

			$stampato = (string) ob_get_clean();
			$errore   = $wpdb->last_error;
		} finally {
			$wpdb->prefix = $prefisso;
		}

		foreach ( array( $assegna, $dichiara, $prossimo ) as $esito ) {
			$this->assertWPError( $esito );
			$this->assertSame( 'albo_repertorio_tabelle_assenti', $esito->get_error_code() );
		}

		$this->assertSame( '', $stampato, 'Nessun errore della banca dati mostrato.' );
		$this->assertSame( '', $errore, 'Nessun errore della banca dati.' );
		$this->assertSame( array(), $this->righe_anni() );
	}
}
