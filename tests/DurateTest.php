<?php
/**
 * Prove delle durate per tipo di atto: righe A-48..A-57, e la riga statica di ALBO-04.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Durate;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\TipoAtto;
use WP_UnitTestCase;

/**
 * Configurazione, validazione e lettura della durata di un tipo di atto.
 */
class DurateTest extends WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Estremi di una norma, usati dove serve una durata di origine normativa.
	 *
	 * @var string
	 */
	private const ESTREMI = 'art. 124 d.lgs. 267/2000';

	/**
	 * Tipo, elenchi di voci, permessi e metadato pronti; utente amministratore.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );
		$this->assertTrue( Durate::registra() );

		Permessi::applica();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Rimonta tutto e ripulisce il modulo simulato.
	 */
	public function tear_down(): void {
		$this->azzera_ambiente_albo();
		unset( $GLOBALS['current_screen'] );
		unset(
			$_POST[ Durate::CAMPO_GETTONE ],
			$_POST[ Durate::CAMPO_GIORNI ],
			$_POST[ Durate::CAMPO_ORIGINE ],
			$_POST[ Durate::CAMPO_ESTREMI ]
		);

		parent::tear_down();
	}

	/**
	 * Un tipo di atto nuovo, senza durata.
	 *
	 * @param string $nome Nome del tipo.
	 * @return int
	 */
	private function tipo( string $nome = 'Deliberazione' ): int {
		$id = self::factory()->term->create(
			array(
				'taxonomy' => \AlboPretorioPa\TASSONOMIA_TIPO_ATTO,
				'name'     => $nome,
			)
		);

		$this->assertIsInt( $id );
		$this->assertFalse(
			metadata_exists( 'term', $id, \AlboPretorioPa\META_DURATA ),
			'Precondizione: un tipo nuovo non ha nessuna durata memorizzata.'
		);

		return $id;
	}

	/**
	 * Tutte le righe del metadato come sono nella banca dati, senza cache.
	 *
	 * La lettura passa dalla cache dei metadati, e una prova che guardasse solo
	 * quella vedrebbe cio' che la richiesta ha tenuto in memoria e non cio' che
	 * e' stato scritto.
	 *
	 * @param int $id Tipo di atto.
	 * @return array<int, mixed>
	 */
	private function righe_memorizzate( int $id ): array {
		wp_cache_delete( $id, 'term_meta' );

		return get_term_meta( $id, \AlboPretorioPa\META_DURATA, false );
	}

	/**
	 * Prepara un invio vero della schermata del tipo di atto.
	 *
	 * @param string|null $gettone Gettone da mettere nel modulo; null per quello giusto.
	 * @param string      $giorni  Campo dei giorni.
	 * @param string      $origine Campo dell'origine.
	 * @param string      $estremi Campo degli estremi.
	 */
	private function modulo( ?string $gettone, string $giorni, string $origine, string $estremi ): void {
		set_current_screen( 'edit-' . \AlboPretorioPa\TASSONOMIA_TIPO_ATTO );

		$_POST[ Durate::CAMPO_GETTONE ] = null === $gettone ? wp_create_nonce( Durate::AZIONE_GETTONE ) : $gettone;
		$_POST[ Durate::CAMPO_GIORNI ]  = $giorni;
		$_POST[ Durate::CAMPO_ORIGINE ] = $origine;
		$_POST[ Durate::CAMPO_ESTREMI ] = $estremi;
	}

	/**
	 * Il salvataggio che la schermata fa premendo Aggiorna.
	 *
	 * @param int $id Tipo di atto.
	 */
	private function salva_dalla_schermata( int $id ): void {
		$esito = wp_update_term( $id, \AlboPretorioPa\TASSONOMIA_TIPO_ATTO, array( 'name' => 'Nome salvato dalla schermata' ) );

		$this->assertIsArray( $esito, 'Precondizione: il salvataggio del tipo deve avvenire.' );
	}

	/**
	 * Un utente con i permessi di chi redige e non quelli di chi pubblica.
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

		$this->assertTrue( user_can( $id, $mappa['edit_posts'] ), 'Precondizione: deve poter redigere.' );
		$this->assertFalse( user_can( $id, $mappa['publish_posts'] ), 'Precondizione: non deve poter pubblicare.' );

		return $id;
	}

	/**
	 * A-48: una durata valida si configura, e si rilegge uguale dalla banca dati.
	 *
	 * Le due origini insieme: una prova sola non direbbe se il componente
	 * distingue le due forme o ne accetta una sola.
	 */
	public function test_a48_durata_valida_si_configura_e_si_rilegge(): void {
		$norma = $this->tipo( 'Deliberazione di consiglio' );
		$ente  = $this->tipo( 'Avviso' );

		$this->assertTrue( Durate::configura( $norma, 15, 'norma', self::ESTREMI ) );
		$this->assertTrue( Durate::configura( $ente, '30', 'amministrazione', '' ) );

		$this->assertSame(
			array(
				array(
					'giorni'  => 15,
					'origine' => 'norma',
					'estremi' => self::ESTREMI,
				),
			),
			$this->righe_memorizzate( $norma ),
			'Nella banca dati deve esserci una sola riga, con i tre valori insieme.'
		);

		$this->assertSame(
			array(
				'giorni'  => 30,
				'origine' => 'amministrazione',
				'estremi' => '',
			),
			Durate::del_tipo( $ente ),
			'La lettura deve restituire i valori scritti, con i giorni come numero intero.'
		);
	}

	/**
	 * A-49: ogni forma di dato sbagliato e' rifiutata, una per volta, e niente viene scritto.
	 *
	 * Ogni caso parte da un tipo senza durata e cambia una sola cosa rispetto a
	 * una configurazione valida, cosi' che il rifiuto possa venire solo da li'.
	 */
	public function test_a49_dati_sbagliati_rifiutati_uno_per_volta(): void {
		$casi = array(
			'giorni assenti'                 => array( '', 'norma', self::ESTREMI ),
			'giorni zero'                    => array( 0, 'norma', self::ESTREMI ),
			'giorni negativi'                => array( -3, 'norma', self::ESTREMI ),
			'giorni con la virgola'          => array( 1.5, 'norma', self::ESTREMI ),
			'giorni seguiti da lettere'      => array( '15abc', 'norma', self::ESTREMI ),
			'giorni seguiti da un a capo'    => array( "15\n", 'norma', self::ESTREMI ),
			'giorni oltre il massimo intero' => array( '99999999999999999999', 'norma', self::ESTREMI ),
			'solo i giorni, senza origine'   => array( 15, '', '' ),
			'origine sconosciuta'            => array( 15, 'regolamento', self::ESTREMI ),
			'norma senza estremi'            => array( 15, 'norma', '' ),
			'norma con estremi di spazi'     => array( 15, 'norma', '   ' ),
			'amministrazione con estremi'    => array( 15, 'amministrazione', self::ESTREMI ),
		);

		foreach ( $casi as $caso => $valori ) {
			$id    = $this->tipo( 'Tipo per il caso: ' . $caso );
			$esito = Durate::configura( $id, $valori[0], $valori[1], $valori[2] );

			$this->assertWPError( $esito, 'Rifiuto atteso: ' . $caso );
			$this->assertSame( array(), $this->righe_memorizzate( $id ), 'Niente deve essere scritto: ' . $caso );
			$this->assertWPError( Durate::del_tipo( $id ), 'Il tipo deve restare senza durata: ' . $caso );
		}

		$controllo = $this->tipo( 'Controllo positivo' );

		$this->assertTrue(
			Durate::configura( $controllo, 15, 'norma', self::ESTREMI ),
			'Controllo positivo: gli stessi valori, corretti, devono essere accettati.'
		);
	}

	/**
	 * A-50: una durata valida non viene toccata da un tentativo sbagliato.
	 *
	 * E' il caso che manca a una validazione che scrive un campo per volta: il
	 * numero di giorni nuovo finirebbe salvato accanto all'origine vecchia.
	 */
	public function test_a50_tentativo_sbagliato_non_tocca_la_durata_esistente(): void {
		$id = $this->tipo();

		$this->assertTrue( Durate::configura( $id, 15, 'norma', self::ESTREMI ) );

		$prima = $this->righe_memorizzate( $id );

		$tentativi = array(
			array( 20, 'norma', '' ),
			array( 0, 'norma', self::ESTREMI ),
			array( 20, 'amministrazione', self::ESTREMI ),
			array( 20, '', '' ),
		);

		foreach ( $tentativi as $indice => $valori ) {
			$this->assertWPError( Durate::configura( $id, $valori[0], $valori[1], $valori[2] ), 'Rifiuto atteso, tentativo ' . $indice );
			$this->assertSame( $prima, $this->righe_memorizzate( $id ), 'La durata deve restare quella di prima, tentativo ' . $indice );
		}
	}

	/**
	 * A-51: una durata malformata scritta di lato non e' una durata.
	 *
	 * Lo scavalcamento grezzo: si scrive nella banca dati senza passare dal
	 * componente. La lettura deve rifiutare, una condizione per volta. Il
	 * controllo positivo scrive di lato una durata ben formata: senza, la prova
	 * sarebbe verde con una lettura che rifiuta tutto.
	 */
	public function test_a51_durata_malformata_scritta_di_lato_e_rifiutata(): void {
		$valida = array(
			'giorni'  => 15,
			'origine' => 'norma',
			'estremi' => self::ESTREMI,
		);

		$malformate = array(
			'non un elenco'                => '15',
			'giorni come testo'            => array_merge( $valida, array( 'giorni' => '15' ) ),
			'giorni zero'                  => array_merge( $valida, array( 'giorni' => 0 ) ),
			'giorni con la virgola'        => array_merge( $valida, array( 'giorni' => 15.0 ) ),
			'origine mancante'             => array(
				'giorni'  => 15,
				'estremi' => self::ESTREMI,
			),
			'origine sconosciuta'          => array_merge( $valida, array( 'origine' => 'regolamento' ) ),
			'norma senza estremi'          => array_merge( $valida, array( 'estremi' => '' ) ),
			'amministrazione con estremi'  => array_merge( $valida, array( 'origine' => 'amministrazione' ) ),
			'una chiave in piu'            => array_merge( $valida, array( 'fine' => '2026-12-31' ) ),
		);

		foreach ( $malformate as $caso => $valore ) {
			$id = $this->tipo( 'Tipo scritto di lato: ' . $caso );

			update_term_meta( $id, \AlboPretorioPa\META_DURATA, $valore );

			$this->assertCount( 1, $this->righe_memorizzate( $id ), 'Precondizione: la scrittura di lato deve esserci: ' . $caso );

			$esito = Durate::del_tipo( $id );

			$this->assertWPError( $esito, 'La lettura deve rifiutare: ' . $caso );
			$this->assertSame( 'albo_durata_non_valida', $esito->get_error_code(), 'Il rifiuto deve dire che la durata non e\' valida: ' . $caso );
		}

		$doppia = $this->tipo( 'Due righe per lo stesso dato' );

		add_term_meta( $doppia, \AlboPretorioPa\META_DURATA, $valida );
		add_term_meta( $doppia, \AlboPretorioPa\META_DURATA, array_merge( $valida, array( 'giorni' => 5 ) ) );

		$this->assertCount( 2, $this->righe_memorizzate( $doppia ), 'Precondizione: le due righe devono esserci.' );
		$this->assertWPError( Durate::del_tipo( $doppia ), 'Due righe per lo stesso dato non sono una durata: quale valga non si sa.' );

		$controllo = $this->tipo( 'Controllo positivo scritto di lato' );

		update_term_meta( $controllo, \AlboPretorioPa\META_DURATA, $valida );

		$this->assertSame( $valida, Durate::del_tipo( $controllo ), 'Controllo positivo: una durata ben formata scritta di lato si legge.' );
	}

	/**
	 * A-52: nessun valore di ripiego.
	 *
	 * Un tipo senza durata, una durata tolta, un tipo inesistente e un termine
	 * di un altro elenco: in tutti i casi la risposta e' un errore, mai un
	 * numero di giorni.
	 */
	public function test_a52_nessun_valore_di_ripiego(): void {
		$nuovo = $this->tipo();
		$esito = Durate::del_tipo( $nuovo );

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_durata_assente', $esito->get_error_code() );

		$tolta = $this->tipo( 'Tipo a cui la durata viene tolta' );

		$this->assertTrue( Durate::configura( $tolta, 15, 'norma', self::ESTREMI ) );
		$this->assertTrue( Durate::togli( $tolta ) );
		$this->assertSame( array(), $this->righe_memorizzate( $tolta ), 'Tolta, la durata non deve restare nella banca dati.' );
		$this->assertWPError( Durate::del_tipo( $tolta ) );

		$organo = self::factory()->term->create(
			array(
				'taxonomy' => \AlboPretorioPa\TASSONOMIA_ORGANO,
				'name'     => 'Consiglio',
			)
		);

		$this->assertWPError( Durate::configura( (int) $organo, 15, 'norma', self::ESTREMI ), 'Un organo non e\' un tipo di atto.' );
		$this->assertSame( array(), get_term_meta( (int) $organo, \AlboPretorioPa\META_DURATA, false ) );
		$this->assertWPError( Durate::del_tipo( (int) $organo ) );
		$this->assertWPError( Durate::del_tipo( 999999 ), 'Un tipo inesistente non ha durata.' );
	}

	/**
	 * A-53: la schermata salva con il gettone e il permesso, e non senza.
	 *
	 * Quattro invii, una condizione per volta. Il redattore possiede tutti i
	 * permessi di chi redige, che e' il permesso confondibile con quello giusto;
	 * il controllo positivo lo fa chi pubblica senza essere amministratore.
	 */
	public function test_a53_schermata_con_gettone_e_permesso(): void {
		$id = $this->tipo();

		$this->assertTrue( Durate::configura( $id, 10, 'amministrazione', '' ) );

		$prima = $this->righe_memorizzate( $id );

		$this->modulo( 'gettone-sbagliato', '20', 'amministrazione', '' );
		$this->salva_dalla_schermata( $id );
		$this->assertSame( $prima, $this->righe_memorizzate( $id ), 'Senza il gettone giusto la durata non cambia.' );
		$this->assertNotSame( '', (string) Durate::preleva_rifiuto(), 'Chi ha salvato deve trovare il motivo.' );

		wp_set_current_user( $this->redattore() );
		$this->modulo( null, '20', 'amministrazione', '' );
		$this->salva_dalla_schermata( $id );
		$this->assertSame( $prima, $this->righe_memorizzate( $id ), 'Chi redige soltanto non cambia la durata.' );

		$responsabile = self::factory()->user->create( array( 'role' => \AlboPretorioPa\RUOLO ) );

		wp_set_current_user( $responsabile );
		$this->modulo( null, '20', 'norma', '' );
		$this->salva_dalla_schermata( $id );
		$this->assertSame( $prima, $this->righe_memorizzate( $id ), 'Una durata sbagliata dalla schermata non cambia la durata.' );
		$this->assertStringContainsString(
			'estremi',
			(string) Durate::preleva_rifiuto(),
			'Il motivo deve dire che mancano gli estremi.'
		);

		$this->modulo( null, '20', 'norma', self::ESTREMI );
		$this->salva_dalla_schermata( $id );
		$this->assertSame(
			array(
				'giorni'  => 20,
				'origine' => 'norma',
				'estremi' => self::ESTREMI,
			),
			Durate::del_tipo( $id ),
			'Controllo positivo: chi pubblica, con il gettone giusto e dati validi, cambia la durata senza toccare il codice.'
		);
		$this->assertNull( Durate::preleva_rifiuto(), 'Un salvataggio riuscito non lascia motivi di rifiuto.' );
	}

	/**
	 * A-54: un salvataggio che non viene dal modulo della durata non la tocca.
	 *
	 * La modifica rapida dall'elenco e il codice che rinomina un tipo passano
	 * dallo stesso aggancio della schermata, ma senza i campi della durata. Se
	 * il gestore li leggesse come vuoti, toglierebbe la durata a ogni
	 * rinomina. Il caso opposto, tutti e tre i campi svuotati di proposito dal
	 * modulo, e' la rimozione dichiarata.
	 */
	public function test_a54_salvataggio_senza_modulo_non_tocca_la_durata(): void {
		$id = $this->tipo();

		$this->assertTrue( Durate::configura( $id, 15, 'norma', self::ESTREMI ) );

		$prima = $this->righe_memorizzate( $id );

		$this->salva_dalla_schermata( $id );

		$this->assertSame( $prima, $this->righe_memorizzate( $id ), 'Una rinomina senza modulo non deve toccare la durata.' );
		$this->assertNull( Durate::preleva_rifiuto(), 'Nessun motivo di rifiuto: non c\'era niente da rifiutare.' );

		$this->modulo( null, '', '', '' );
		$this->salva_dalla_schermata( $id );

		$this->assertSame( array(), $this->righe_memorizzate( $id ), 'I tre campi svuotati dal modulo tolgono la durata.' );
	}

	/**
	 * A-55: il dato non e' esposto all'interfaccia per programmi e si modifica solo con il permesso.
	 */
	public function test_a55_dato_dichiarato_chiuso_e_protetto(): void {
		$chiavi = get_registered_meta_keys( 'term', \AlboPretorioPa\TASSONOMIA_TIPO_ATTO );

		$this->assertArrayHasKey( \AlboPretorioPa\META_DURATA, $chiavi, 'Il dato deve essere dichiarato a WordPress.' );
		$this->assertFalse( $chiavi[ \AlboPretorioPa\META_DURATA ]['show_in_rest'], 'Il dato non deve essere esposto ai programmi.' );
		$this->assertTrue( $chiavi[ \AlboPretorioPa\META_DURATA ]['single'] );

		$id = $this->tipo();

		$this->assertFalse(
			user_can( $this->redattore(), 'edit_term_meta', $id, \AlboPretorioPa\META_DURATA ),
			'Chi redige soltanto non deve poter modificare il dato.'
		);
		$this->assertTrue(
			user_can( self::factory()->user->create( array( 'role' => \AlboPretorioPa\RUOLO ) ), 'edit_term_meta', $id, \AlboPretorioPa\META_DURATA ),
			'Controllo positivo: chi pubblica deve poterlo modificare.'
		);
	}

	/**
	 * A-56: la colonna dell'elenco dice lo stato vero, anche per una durata scritta di lato.
	 */
	public function test_a56_colonna_dello_stato_vero(): void {
		$colonne = Durate::colonne( array( 'name' => 'Nome' ) );

		$this->assertArrayHasKey( Durate::COLONNA, $colonne );

		$configurato = $this->tipo( 'Con durata' );
		$vuoto       = $this->tipo( 'Senza durata' );
		$malformato  = $this->tipo( 'Scritto di lato' );

		$this->assertTrue( Durate::configura( $configurato, 15, 'norma', self::ESTREMI ) );
		update_term_meta( $malformato, \AlboPretorioPa\META_DURATA, array( 'giorni' => 15 ) );

		$testo = Durate::colonna( '', Durate::COLONNA, $configurato );

		$this->assertStringContainsString( '15', $testo );
		$this->assertStringContainsString( self::ESTREMI, $testo );

		foreach ( array( $vuoto, $malformato ) as $id ) {
			$testo = Durate::colonna( '', Durate::COLONNA, $id );

			$this->assertStringNotContainsString( '15', $testo, 'Un tipo senza durata valida non mostra numeri di giorni.' );
			$this->assertStringContainsString( 'non si pubblicano', $testo, 'Deve dire la conseguenza.' );
		}

		$this->assertSame( 'altro', Durate::colonna( 'altro', 'name', $configurato ), 'Le altre colonne non si toccano.' );
	}

	/**
	 * A-57 e ALBO-04, riga statica: nei sorgenti non c'e' il numero 15 come durata.
	 *
	 * Si guardano i pezzi di codice e non il testo, cosi' che un commento che
	 * spiega l'art. 124 non faccia fallire la prova e un 15 dentro una stringa
	 * la faccia fallire. Si cercano anche i quindici giorni espressi in secondi.
	 */
	public function test_a57_nessun_quindici_nei_sorgenti(): void {
		$radice = dirname( __DIR__ );
		$file   = array_merge( array( $radice . '/albo-pretorio-pa.php' ), (array) glob( $radice . '/includes/*.php' ) );

		$this->assertGreaterThan( 5, count( $file ), 'Precondizione: i sorgenti devono essere stati trovati.' );

		$trovati = array();

		foreach ( $file as $percorso ) {
			foreach ( token_get_all( (string) file_get_contents( (string) $percorso ) ) as $pezzo ) {
				if ( ! is_array( $pezzo ) ) {
					continue;
				}

				$numerico = in_array( $pezzo[0], array( T_LNUMBER, T_DNUMBER ), true )
					&& in_array( (float) $pezzo[1], array( 15.0, 1296000.0 ), true );
				$testuale = in_array( $pezzo[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true )
					&& 1 === preg_match( '/(?<![0-9])(15|1296000)(?![0-9])/', $pezzo[1] );

				if ( $numerico || $testuale ) {
					$trovati[] = basename( (string) $percorso ) . ':' . $pezzo[2] . ' ' . $pezzo[1];
				}
			}
		}

		$this->assertSame( array(), $trovati, 'Nessuna durata cablata nei sorgenti.' );
	}
}
