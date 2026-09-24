<?php
/**
 * Prove della chiusura della pubblicazione: righe A-23, A-24, A-25, A-26, A-27.
 *
 * Tre categorie che non vanno confuse. Gli **ingressi supportati** sono
 * intercettati e non lasciano l'atto ne' pubblicato ne' programmato. La
 * **superficie per programmi e' assente**, e la sua prova sta altrove, perche'
 * l'assenza si verifica come tale. Lo **scavalcamento grezzo** cambia davvero lo
 * stato, e la garanzia in quel caso e' l'irraggiungibilita', non l'impedimento.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\TipoAtto;
use WP_UnitTestCase;

/**
 * Nessun atto raggiungibile, e nessun ingresso supportato che pubblichi.
 */
class ChiusuraPubblicazioneTest extends WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Registri e ruoli riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		Permessi::applica();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Rimonta tipo e ruoli dopo ogni prova.
	 */
	public function tear_down(): void {
		$this->azzera_ambiente_albo();
		unset( $GLOBALS['current_screen'] );
		unset( $_POST['action'], $_POST['post_ID'], $_POST['_wpnonce'] );

		parent::tear_down();
	}

	/**
	 * Riproduce un invio vero della schermata classica dell'atto.
	 *
	 * **Non basta `set_current_screen()`.** Quello rende vera la domanda "siamo
	 * in una richiesta di amministrazione", che e' un'altra cosa: anche un
	 * componente che salva durante una richiesta di amministrazione la
	 * soddisfa, e non e' una persona davanti a una schermata. Il modulo si
	 * riconosce dalla sua azione, dal suo identificativo e dal suo codice di
	 * sicurezza, che sono tre cose che solo quel modulo ha.
	 *
	 * @param int $post_id Identificativo dell'atto che la schermata sta salvando.
	 */
	private function invio_dalla_schermata( int $post_id ): void {
		set_current_screen( 'post' );

		$_POST['action']   = 'editpost';
		$_POST['post_ID']  = (string) $post_id;
		$_POST['_wpnonce'] = wp_create_nonce( 'update-post_' . $post_id );
	}

	/**
	 * Un atto in bozza, da cui partono le prove.
	 *
	 * @return int
	 */
	private function bozza(): int {
		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'draft',
				'post_title'  => 'Atto di prova',
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id, 'Precondizione: la bozza deve essere creata.' );
		$this->assertSame( 'draft', get_post_status( $id ), 'Precondizione: deve nascere in bozza.' );

		return $id;
	}

	/**
	 * A-23: la schermata di amministrazione non pubblica.
	 */
	public function test_a23_ingresso_schermata(): void {
		$id = $this->bozza();

		$this->invio_dalla_schermata( $id );

		$this->assertTrue( is_admin(), 'Precondizione: la prova deve girare in un contesto di amministrazione.' );

		$richiesto = 'publish';

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $richiesto,
			)
		);

		$this->assertSame( 'publish', $richiesto, 'Precondizione: lo stato richiesto era la pubblicazione.' );
		$this->assertSame( 'draft', get_post_status( $id ), 'L\'atto deve restare in bozza.' );
	}

	/**
	 * A-23: l'inserimento da codice non pubblica.
	 *
	 * Chi programma riceve la segnalazione di uso scorretto, perche' questo
	 * aggancio di WordPress non puo' restituire un errore al chiamante.
	 */
	public function test_a23_ingresso_inserimento_da_codice(): void {
		set_current_screen( 'front' );

		$this->assertFalse( is_admin(), 'Precondizione: la prova non deve girare in amministrazione.' );

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto inserito da codice',
			)
		);

		$this->assertGreaterThan( 0, $id, 'Precondizione: l\'inserimento deve essere avvenuto.' );
		$this->assertSame( 'draft', get_post_status( $id ), 'L\'atto deve nascere in bozza.' );
	}

	/**
	 * A-23: la richiesta di programmazione non lascia l'atto programmato.
	 *
	 * Conservare una pubblicazione programmata che sappiamo non potersi
	 * concludere sarebbe una promessa non mantenibile.
	 */
	public function test_a23_ingresso_programmazione(): void {
		set_current_screen( 'post' );

		/*
		 * L'inserimento arriva da codice e non dal modulo della schermata, quindi
		 * il rifiuto va a chi programma: e' la segnalazione di uso scorretto.
		 */
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$domani = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

		$id = wp_insert_post(
			array(
				'post_type'     => \AlboPretorioPa\TIPO,
				'post_status'   => 'future',
				'post_title'    => 'Atto programmato',
				'post_date'     => get_date_from_gmt( $domani ),
				'post_date_gmt' => $domani,
			)
		);

		$this->assertGreaterThan( 0, $id, 'Precondizione: l\'inserimento deve essere avvenuto.' );
		$this->assertNotSame( 'future', get_post_status( $id ), 'L\'atto non deve restare programmato.' );
		$this->assertSame( 'draft', get_post_status( $id ), 'L\'atto deve restare in bozza.' );
		$this->assertFalse(
			(bool) wp_next_scheduled( 'publish_future_post', array( $id ) ),
			'Non deve restare in giro un appuntamento per pubblicarlo.'
		);
	}

	/**
	 * A-24: sui tre ingressi supportati non esiste un istante pubblicato.
	 *
	 * Si osserva il valore **scritto** nella tabella dei contenuti, non quello
	 * che si rilegge alla fine: uno stato corretto dopo una riparazione tardiva
	 * sarebbe indistinguibile da uno stato mai scritto.
	 */
	public function test_a24_nessun_intervallo_pubblicato(): void {
		$scritti = array();

		add_filter(
			'wp_insert_post_data',
			static function ( $data ) use ( &$scritti ) {
				if ( \AlboPretorioPa\TIPO === $data['post_type'] ) {
					$scritti[] = $data['post_status'];
				}

				return $data;
			},
			PHP_INT_MAX
		);

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		set_current_screen( 'post' );

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto mai pubblicato',
			)
		);

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		$this->assertNotEmpty( $scritti, 'Precondizione: il filtro deve avere osservato almeno una scrittura.' );
		$this->assertNotContains( 'publish', $scritti, 'Nessuna scrittura deve portare lo stato pubblicato.' );
		$this->assertNotContains( 'future', $scritti, 'Nessuna scrittura deve portare lo stato programmato.' );
	}

	/**
	 * A-25: lo scavalcamento cambia davvero lo stato, e non rende raggiungibile.
	 *
	 * La data di fine e' valida e futura di proposito: cosi' la prova dimostra
	 * la chiusura del tipo e non il filtro di scadenza, che con la data assente
	 * nasconderebbe l'atto per un motivo che non c'entra.
	 */
	public function test_a25_scavalcamento_grezzo_resta_irraggiungibile(): void {
		$id = $this->bozza();

		$this->assertTrue(
			conformita_core_imposta_fine_pubblicazione( $id, gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ) ),
			'Precondizione: la data di fine deve essere scritta.'
		);

		$ordinario = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Atto di prova ordinario',
			)
		);

		wp_publish_post( $id );

		$this->assertSame(
			'publish',
			get_post_status( $id ),
			'Precondizione: lo scavalcamento deve avere davvero portato lo stato a pubblicato.'
		);
		$this->assertFalse(
			conformita_core_scaduto( $id ),
			'Precondizione: il contenuto non deve risultare scaduto, altrimenti a nasconderlo sarebbe il filtro di scadenza.'
		);

		wp_set_current_user( 0 );

		$this->richiesta_pubblica( get_permalink( $ordinario ) );
		$this->assertTrue(
			is_singular(),
			'Precondizione: un contenuto ordinario pubblicato deve essere raggiungibile, altrimenti il non trovato non dimostra niente.'
		);

		$this->richiesta_pubblica( get_permalink( $id ) );
		$this->assertTrue( is_404(), 'L\'indirizzo dell\'atto non deve rispondere.' );

		$this->richiesta_pubblica( home_url( '?s=Atto+di+prova' ) );
		$trovati = wp_list_pluck( (array) $GLOBALS['wp_query']->posts, 'ID' );

		$this->assertContains(
			$ordinario,
			$trovati,
			'Precondizione: la ricerca deve trovare un contenuto ordinario con le stesse parole, altrimenti l\'assenza dell\'atto non dimostra niente.'
		);
		$this->assertNotContains( $id, $trovati, 'L\'atto non deve comparire nella ricerca interna.' );

		$sottotipi = wp_sitemaps_get_server()->registry->get_provider( 'posts' )->get_object_subtypes();
		$this->assertArrayNotHasKey(
			\AlboPretorioPa\TIPO,
			$sottotipi,
			'Il tipo non deve comparire nella mappa per i motori.'
		);
	}

	/**
	 * A-26: i due depositi del motivo di rifiuto, e non un terzo.
	 */
	public function test_a26_due_depositi_del_motivo(): void {
		$id = $this->bozza();

		$this->invio_dalla_schermata( $id );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		$motivi = Rifiuti::preleva_per_utente( $id );

		$this->assertNotSame( array(), $motivi, 'Il motivo deve essere disponibile per chi ha tentato.' );
		$this->assertArrayHasKey(
			'albo_pubblicazione_non_aperta',
			$motivi,
			'Il motivo deve dire che la pubblicazione non e\' aperta.'
		);

		$this->assertSame(
			array(),
			Rifiuti::preleva_per_utente( $id ),
			'Il motivo si consuma alla prima lettura.'
		);

		set_current_screen( 'front' );

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$da_codice = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto da codice',
			)
		);

		$this->assertSame(
			array(),
			Rifiuti::preleva_per_utente( $da_codice ),
			'Il rifiuto di una chiamata da codice non si memorizza.'
		);
		$this->assertSame(
			array(),
			get_post_meta( $da_codice, '_albo_pubblicazione_programmata_fallita', false ),
			'Non esiste un deposito persistente sull\'atto: la programmazione viene respinta, non conservata.'
		);
	}

	/**
	 * A-27: due utenti sullo stesso atto non si scambiano il messaggio.
	 */
	public function test_a27_due_utenti_sullo_stesso_atto(): void {
		$id = $this->bozza();

		$prima = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$dopo  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $prima );
		$this->invio_dalla_schermata( $id );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $dopo );
		$this->invio_dalla_schermata( $id );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $prima );
		$this->assertNotSame( array(), Rifiuti::preleva_per_utente( $id ), 'Il primo utente deve avere il proprio messaggio.' );

		wp_set_current_user( $dopo );
		$this->assertNotSame(
			array(),
			Rifiuti::preleva_per_utente( $id ),
			'Il secondo utente deve avere il proprio messaggio: la lettura del primo non lo consuma.'
		);
	}

	/**
	 * A-34: l'avviso generico non dipende dal dato che deve sostituire.
	 *
	 * Un dato temporaneo puo' essere buttato via prima del tempo da una memoria
	 * condivisa esterna, o la sua scrittura puo' fallire. Se l'indicatore
	 * nell'indirizzo di ritorno dipendesse da lui, nel caso in cui serve non ci
	 * sarebbe ne' il dettaglio ne' l'avviso generico.
	 */
	public function test_a34_avviso_generico_senza_il_dato_temporaneo(): void {
		$id = $this->bozza();

		$this->invio_dalla_schermata( $id );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		$indirizzo = admin_url( 'post.php?post=' . $id . '&action=edit' );

		$this->assertStringContainsString(
			'albo-rifiuto=1',
			Rifiuti::segna_indirizzo_di_ritorno( $indirizzo, $id ),
			'Precondizione: con il dato presente l\'indicatore c\'e\'.'
		);

		$this->assertStringContainsString(
			'registro delle modifiche',
			implode( ' ', Rifiuti::preleva_per_utente( $id ) ),
			'Precondizione: il dato temporaneo esisteva davvero, con il dettaglio.'
		);

		$this->assertStringContainsString(
			'albo-rifiuto=1',
			Rifiuti::segna_indirizzo_di_ritorno( $indirizzo, $id ),
			'Sparito il dato, l\'indicatore deve restare: vive nella richiesta corrente.'
		);

		set_current_screen( 'post' );
		$GLOBALS['current_screen']->post_type = \AlboPretorioPa\TIPO;
		$GLOBALS['post']                      = get_post( $id );

		$this->assertSame(
			\AlboPretorioPa\TIPO,
			get_current_screen()->post_type,
			'Precondizione: la schermata deve essere quella di un atto.'
		);

		$_GET['albo-rifiuto'] = '1';

		ob_start();
		Rifiuti::mostra();
		$avviso = (string) ob_get_clean();

		unset( $_GET['albo-rifiuto'], $GLOBALS['post'] );

		$this->assertStringContainsString(
			'rifiutata',
			$avviso,
			'Senza il dettaglio la schermata deve mostrare comunque l\'avviso generico.'
		);
		$this->assertStringNotContainsString(
			'registro delle modifiche',
			$avviso,
			'Il dettaglio non c\'e\' piu\': quello che resta e\' l\'avviso generico.'
		);
	}

	/**
	 * A-35: un invio vero della schermata lascia il motivo all'utente.
	 */
	public function test_a35_invio_vero_della_schermata(): void {
		$id = $this->bozza();

		$this->invio_dalla_schermata( $id );

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		$this->assertNotSame(
			array(),
			Rifiuti::preleva_per_utente( $id ),
			'Chi ha premuto il pulsante deve trovare il motivo.'
		);
	}

	/**
	 * A-35: una chiamata da codice in amministrazione non e' un invio.
	 *
	 * `is_admin()` dice in quale meta' di WordPress siamo, non chi ha chiesto il
	 * salvataggio. Un componente che salva durante una richiesta di
	 * amministrazione la soddisfa senza essere una persona davanti a una
	 * schermata, e riceverebbe un messaggio che nessuno legge invece della
	 * segnalazione destinata a chi programma.
	 */
	public function test_a35_chiamata_da_codice_in_amministrazione(): void {
		set_current_screen( 'post' );
		unset( $_POST['action'], $_POST['post_ID'], $_POST['_wpnonce'] );

		$this->assertTrue( is_admin(), 'Precondizione: siamo in una richiesta di amministrazione.' );

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto salvato da un componente',
			)
		);

		$this->assertGreaterThan( 0, $id, 'Precondizione: l\'inserimento deve essere avvenuto.' );
		$this->assertSame( 'draft', get_post_status( $id ), 'L\'atto deve restare in bozza.' );
		$this->assertSame(
			array(),
			Rifiuti::preleva_per_utente( $id ),
			'Nessun deposito per l\'utente: non e\' stata una persona a chiedere il salvataggio.'
		);
	}

	/**
	 * A-36: un inserimento annidato non fa perdere il motivo del primo.
	 *
	 * Il secondo inserimento avviene fra la preparazione e la scrittura del
	 * primo. Con un solo appoggio condiviso, il secondo lo sovrascrive e il
	 * primo arriva a destinazione senza niente da depositare.
	 */
	public function test_a36_inserimento_annidato(): void {
		$esterno = $this->bozza();

		$this->invio_dalla_schermata( $esterno );

		$annidato = 0;
		$fatto    = false;

		$filtro = static function ( $data ) use ( &$annidato, &$fatto ) {
			if ( \AlboPretorioPa\TIPO === $data['post_type'] && ! $fatto ) {
				$fatto    = true;
				$annidato = wp_insert_post(
					array(
						'post_type'   => \AlboPretorioPa\TIPO,
						'post_status' => 'publish',
						'post_title'  => 'Atto inserito dentro il salvataggio di un altro',
					)
				);
			}

			return $data;
		};

		add_filter( 'wp_insert_post_data', $filtro, 11 );

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		try {
			wp_update_post(
				array(
					'ID'          => $esterno,
					'post_status' => 'publish',
				)
			);
		} finally {
			remove_filter( 'wp_insert_post_data', $filtro, 11 );
		}

		$this->assertGreaterThan( 0, $annidato, 'Precondizione: l\'inserimento annidato deve essere avvenuto.' );
		$this->assertSame( 'draft', get_post_status( $annidato ), 'Anche l\'annidato resta in bozza.' );
		$this->assertSame( 'draft', get_post_status( $esterno ), 'Il primo resta in bozza.' );

		$this->assertNotSame(
			array(),
			Rifiuti::preleva_per_utente( $esterno ),
			'Il motivo del primo non deve essere stato perso dal secondo.'
		);
		$this->assertSame(
			array(),
			Rifiuti::preleva_per_utente( $annidato ),
			'Il secondo e\' una chiamata da codice: nessun deposito per l\'utente.'
		);
	}

	/**
	 * A-42: il segno nell'indirizzo nasce solo da un invio riconosciuto.
	 *
	 * Le due direzioni insieme, perche' una sola non dimostra niente: se il
	 * segno comparisse sempre, la prima resterebbe verde e una persona si
	 * vedrebbe comparire un avviso per un salvataggio che non ha chiesto.
	 */
	public function test_a42_segno_solo_per_un_invio_riconosciuto(): void {
		$indirizzo = admin_url( 'post.php?action=edit' );

		// Prima direzione: invio vero della schermata, con il dato temporaneo sparito.
		$dalla_schermata = $this->bozza();

		$this->invio_dalla_schermata( $dalla_schermata );

		wp_update_post(
			array(
				'ID'          => $dalla_schermata,
				'post_status' => 'publish',
			)
		);

		$this->assertNotSame(
			array(),
			Rifiuti::preleva_per_utente( $dalla_schermata ),
			'Precondizione: il dettaglio c\'era, e prelevandolo sparisce.'
		);

		$this->assertStringContainsString(
			'albo-rifiuto=1',
			Rifiuti::segna_indirizzo_di_ritorno( $indirizzo, $dalla_schermata ),
			'Sparito il dato, il segno deve restare.'
		);

		// Seconda direzione: chiamata da codice durante una richiesta di amministrazione.
		unset( $_POST['action'], $_POST['post_ID'], $_POST['_wpnonce'] );
		set_current_screen( 'post' );

		$this->assertTrue( is_admin(), 'Precondizione: siamo in una richiesta di amministrazione.' );

		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$da_codice = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto salvato da un componente',
			)
		);

		$this->assertSame( 'draft', get_post_status( $da_codice ), 'Precondizione: il rifiuto e\' avvenuto.' );
		$this->assertSame(
			array(),
			Rifiuti::preleva_per_utente( $da_codice ),
			'Nessun dettaglio per l\'utente.'
		);
		$this->assertStringNotContainsString(
			'albo-rifiuto',
			Rifiuti::segna_indirizzo_di_ritorno( $indirizzo, $da_codice ),
			'E nessun segno: la persona non deve vedere un avviso per un salvataggio che non ha chiesto.'
		);
	}
}
