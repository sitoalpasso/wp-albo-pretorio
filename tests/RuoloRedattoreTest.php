<?php
/**
 * Il ruolo di chi prepara gli atti senza pubblicarli: righe A-286..A-289 (ALBO-22).
 *
 * Accanto al ruolo di chi pubblica il componente crea un secondo ruolo
 * proprio, per chi redige soltanto: con quello la separazione fra chi prepara
 * e chi pubblica si ottiene dal menu Utenti, senza assegnare permessi a mano.
 * Come il primo si riconosce dal marcatore, non adotta un ruolo omonimo di
 * altri e nasce una volta sola, cosi' un'amministrazione che lo toglie non se
 * lo ritrova al prossimo aggiornamento.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Installazione;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\TipoAtto;
use WP_UnitTestCase;

use const AlboPretorioPa\OPZIONE_VERSIONE;
use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\RUOLO_MARCATORE;
use const AlboPretorioPa\RUOLO_REDATTORE;
use const AlboPretorioPa\VERSIONE;

/**
 * Il ruolo del redattore, come nasce e quando non nasce.
 */
class RuoloRedattoreTest extends WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Registri, ruoli e opzione riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();
		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );
		$this->assertTrue( TipoAtto::registra(), 'Precondizione: il tipo deve essere registrato.' );
	}

	/**
	 * Rimonta tutto dopo ogni prova.
	 */
	public function tear_down(): void {
		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * A-286: all'installazione nasce il redattore, che prepara e non pubblica.
	 *
	 * Ha i permessi di chi redige e nessun altro dell'albo: non pubblica, non
	 * legge il registro, non annulla ne' toglie, non consulta gli scaduti, non
	 * tocca lo storico. Nessun utente lo riceve da se'.
	 */
	public function test_a286_il_redattore_nasce_all_installazione(): void {
		$this->assertNull( get_role( RUOLO_REDATTORE ), 'Precondizione: il ruolo non esiste ancora.' );

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'installazione riesce.' );

		$mappa   = Permessi::mappa();
		$riletti = new \WP_Roles();
		$ruolo   = $riletti->get_role( RUOLO_REDATTORE );

		$this->assertIsArray( $mappa );
		$this->assertNotNull( $ruolo, 'Il ruolo risulta scritto nella banca dati.' );
		$this->assertSame( 'Redattore dell\'albo', $riletti->role_names[ RUOLO_REDATTORE ], 'Con il suo nome, che si legge nel menu Utenti.' );
		$this->assertTrue( $ruolo->has_cap( RUOLO_MARCATORE ), 'Con il marcatore del componente.' );
		$this->assertTrue( $ruolo->has_cap( 'read' ), 'Entra in amministrazione.' );

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			$this->assertTrue( $ruolo->has_cap( $mappa[ $chiave ] ), 'Ha il permesso di chi redige: ' . $chiave . '.' );
		}

		foreach ( array( 'publish_posts', 'edit_others_posts', 'delete_others_posts', 'delete_published_posts', Permessi::CHIAVE_REGISTRO, Permessi::CHIAVE_DEFISSIONE, Permessi::CHIAVE_CONSULTAZIONE, Permessi::CHIAVE_STORICO ) as $chiave ) {
			$this->assertFalse( $ruolo->has_cap( $mappa[ $chiave ] ), 'Non ha il permesso: ' . $chiave . '.' );
		}

		$this->assertFalse( $ruolo->has_cap( 'edit_posts' ), 'Non riceve i permessi degli articoli.' );
		$this->assertFalse( $ruolo->has_cap( 'upload_files' ), 'Ne\' la libreria dei media.' );

		$utente = self::factory()->user->create( array( 'role' => RUOLO_REDATTORE ) );
		$atto   = self::factory()->post->create(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'draft',
				'post_author' => $utente,
			)
		);

		wp_set_current_user( $utente );

		$this->assertTrue( current_user_can( 'edit_post', $atto ), 'Il redattore modifica la sua bozza.' );
		$this->assertFalse( current_user_can( 'publish_post', $atto ), 'E non la pubblica.' );
		$this->assertTrue( get_role( RUOLO )->has_cap( $mappa['publish_posts'] ), 'Il ruolo di chi pubblica resta quello di prima.' );
		$this->assertSame(
			array(),
			get_users(
				array(
					'role'    => RUOLO_REDATTORE,
					'exclude' => array( $utente ),
					'fields'  => 'ID',
				)
			),
			'Nessun altro utente ha ricevuto il ruolo.'
		);
	}

	/**
	 * A-287: un ruolo omonimo di altri non si adotta, e niente si scrive.
	 */
	public function test_a287_ruolo_omonimo_di_altri(): void {
		add_role(
			RUOLO_REDATTORE,
			'Ruolo di un altro componente',
			array(
				'read'                       => true,
				'albo_prova_permesso_altrui' => true,
			)
		);

		$prima = get_role( RUOLO_REDATTORE )->capabilities;

		$esito = Permessi::applica();

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_ruolo_in_conflitto', $esito->get_error_code() );
		$this->assertSame( $prima, get_role( RUOLO_REDATTORE )->capabilities, 'Il ruolo di altri resta intatto.' );
		$this->assertNull( get_role( RUOLO ), 'Nemmeno il ruolo di chi pubblica nasce: il controllo precede ogni scrittura.' );
		$this->assertSame( array(), $this->permessi_dell_albo_sui_ruoli(), 'Nessun ruolo riceve permessi dell\'albo.' );
		$this->assertFalse( Installazione::aggiorna_se_serve(), 'L\'installazione non prosegue.' );
		$this->assertFalse( get_option( OPZIONE_VERSIONE, false ), 'Nessuna versione memorizzata.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString( RUOLO_REDATTORE, $this->avvisi_in_bacheca(), 'L\'avviso nomina il ruolo in conflitto.' );
	}

	/**
	 * A-288: il redattore nasce una volta sola.
	 *
	 * Chi aggiorna da una versione che non lo aveva lo riceve; un'installazione
	 * che lo aveva gia' e da cui l'amministrazione lo ha tolto non se lo
	 * ritrova. Un redattore che c'e' resta com'e', con i permessi aggiunti a mano.
	 */
	public function test_a288_il_redattore_nasce_una_volta_sola(): void {
		update_option( OPZIONE_VERSIONE, '0.7.0-alpha' );

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'aggiornamento da una versione senza il redattore riesce.' );
		$this->assertNotNull( get_role( RUOLO_REDATTORE ), 'E il redattore nasce.' );
		$this->assertSame( VERSIONE, get_option( OPZIONE_VERSIONE ) );

		get_role( RUOLO_REDATTORE )->add_cap( 'albo_prova_aggiunto_a_mano' );

		update_option( OPZIONE_VERSIONE, Permessi::REDATTORE_DAL );
		$this->assertTrue( Permessi::applica( null, Installazione::chiavi_escluse( Permessi::REDATTORE_DAL ) ) );
		$this->assertTrue( get_role( RUOLO_REDATTORE )->has_cap( 'albo_prova_aggiunto_a_mano' ), 'Un redattore che c\'e\' non si tocca.' );

		remove_role( RUOLO_REDATTORE );

		$this->assertTrue( Permessi::applica( null, Installazione::chiavi_escluse( Permessi::REDATTORE_DAL ) ), 'Senza il redattore il resto si applica.' );
		$this->assertNull( get_role( RUOLO_REDATTORE ), 'Tolto dall\'amministrazione, non torna.' );
		$this->assertNotNull( get_role( RUOLO ), 'Il ruolo di chi pubblica c\'e\' sempre.' );

		$this->assertTrue( Permessi::applica( null, Installazione::chiavi_escluse( '0.7.0-alpha' ) ) );
		$this->assertNotNull( get_role( RUOLO_REDATTORE ), 'Controllo positivo: dalla versione precedente nasce.' );
	}

	/**
	 * A-289: il marcatore del redattore che non si scrive, e la via di recupero.
	 */
	public function test_a289_marcatore_del_redattore_non_scritto(): void {
		$sabota = static function ( $valore ) {
			if ( is_array( $valore ) && isset( $valore[ RUOLO_REDATTORE ]['capabilities'] ) ) {
				unset( $valore[ RUOLO_REDATTORE ]['capabilities'][ RUOLO_MARCATORE ] );
			}

			return $valore;
		};

		add_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );

		try {
			$esito = Installazione::aggiorna_se_serve();
		} finally {
			remove_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );
		}

		$riletti = new \WP_Roles();

		$this->assertFalse( $esito, 'Senza il marcatore scritto non si dichiara successo.' );
		$this->assertFalse( get_option( OPZIONE_VERSIONE, false ), 'Nessuna versione memorizzata.' );
		$this->assertNull( $riletti->get_role( RUOLO_REDATTORE ), 'Il ruolo creato a meta\' e\' stato rimosso, riletto dalla banca dati.' );

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'Tolto il guasto, la richiesta dopo riesce.' );
		$this->assertTrue( get_role( RUOLO_REDATTORE )->has_cap( RUOLO_MARCATORE ), 'E il ruolo ricreato ha il marcatore.' );
	}
}
