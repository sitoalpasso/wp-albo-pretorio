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

	/**
	 * Registri e ruoli riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Tipi::azzera();
		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		TipoAtto::azzera();

		$GLOBALS['wp_roles'] = new \WP_Roles();
		Permessi::azzera();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		Permessi::applica();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Rimonta tipo e ruoli dopo ogni prova.
	 */
	public function tear_down(): void {
		Permessi::azzera();
		\Conformita_Core_Tipi::azzera();
		TipoAtto::azzera();

		$GLOBALS['wp_roles'] = new \WP_Roles();
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * Esegue una richiesta pubblica come la eseguirebbe un sito vero.
	 *
	 * **Non basta `go_to()`**, e la differenza cambia l'esito. Quell'aiuto passa
	 * la stringa di interrogazione anche come variabili aggiuntive, cosa che
	 * WordPress in una richiesta vera non fa: `post_type` e' una variabile
	 * riservata, e le variabili riservate vengono rimesse **dopo** il controllo
	 * che scarta i tipi non interrogabili dal pubblico. Il risultato e' che
	 * l'indirizzo risponde nel laboratorio e non risponde sul sito, cioe' la
	 * prova osserverebbe una condizione che non esiste. Qui la richiesta si
	 * rifa' con una istanza pulita e nessuna variabile aggiuntiva, che e' come
	 * WordPress avvia una richiesta vera.
	 *
	 * @param string $indirizzo Indirizzo da richiedere.
	 */
	private function richiesta_pubblica( string $indirizzo ): void {
		$this->go_to( $indirizzo );

		$GLOBALS['wp_the_query'] = new \WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$GLOBALS['wp']           = new \WP();
		$GLOBALS['wp']->main( '' );
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

		set_current_screen( 'post' );

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

		set_current_screen( 'post' );

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

		set_current_screen( 'post' );

		$prima = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$dopo  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $prima );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $dopo );
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
}
