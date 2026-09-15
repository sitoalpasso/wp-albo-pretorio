<?php
/**
 * Prove dei permessi e del ruolo proprio: righe A-18, A-19, A-20, A-22.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\TipoAtto;
use WP_UnitTestCase;

/**
 * Permessi dedicati, ruolo proprio del componente e avviso quando mancano.
 */
class PermessiTest extends WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Registri in memoria e ruoli riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();

		$GLOBALS['wp_roles'] = new \WP_Roles();

		Permessi::azzera();

		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );
		$this->assertTrue( TipoAtto::registra(), 'Precondizione: il tipo deve essere registrato.' );
	}

	/**
	 * Rimonta tipo e ruoli dopo ogni prova.
	 */
	public function tear_down(): void {
		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * La corrispondenza fra nomi generici e permessi derivati dal tipo.
	 *
	 * @return array<string, string>
	 */
	private function mappa(): array {
		$mappa = conformita_core_capacita_tipo( \AlboPretorioPa\TIPO );

		$this->assertNotWPError( $mappa, 'Precondizione: il nucleo comune deve fornire i permessi del tipo.' );

		return $mappa;
	}

	/**
	 * A-18: i permessi degli articoli non valgono sugli atti.
	 *
	 * Si chiede il permesso a utenti veri: leggere la tabella dei ruoli
	 * direbbe che cosa e' scritto, non che cosa WordPress risponde.
	 */
	public function test_a18_permessi_separati_da_quelli_degli_articoli(): void {
		Permessi::applica();

		$mappa = $this->mappa();
		$atto  = self::factory()->post->create(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$this->assertTrue(
			current_user_can( 'edit_posts' ),
			'Precondizione: l\'autore deve poter modificare gli articoli, altrimenti la prova non dimostra la separazione.'
		);

		$this->assertFalse( current_user_can( $mappa['edit_posts'] ), 'L\'autore non redige atti.' );
		$this->assertFalse( current_user_can( $mappa['publish_posts'] ), 'L\'autore non pubblica atti.' );
		$this->assertFalse( current_user_can( 'edit_post', $atto ), 'L\'autore non modifica questo atto.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( current_user_can( $mappa['edit_posts'] ), 'L\'amministratore redige atti.' );
		$this->assertTrue( current_user_can( 'edit_post', $atto ), 'L\'amministratore modifica questo atto.' );
	}

	/**
	 * A-19: il ruolo proprio esiste, e redigere non e' pubblicare.
	 *
	 * La distinzione fra chi prepara e chi pubblica e' la parte di ALBO-26
	 * verificabile senza chiudere la decisione aperta sugli stati.
	 */
	public function test_a19_ruolo_proprio_e_permessi_distinti(): void {
		Permessi::applica();

		$mappa = $this->mappa();
		$ruolo = get_role( \AlboPretorioPa\RUOLO );

		$this->assertNotNull( $ruolo, 'Il componente deve creare il proprio ruolo.' );
		$this->assertTrue( $ruolo->has_cap( $mappa['edit_posts'] ), 'Il responsabile redige atti.' );
		$this->assertTrue( $ruolo->has_cap( $mappa['publish_posts'] ), 'Il responsabile pubblica atti.' );
		$this->assertFalse( $ruolo->has_cap( 'publish_posts' ), 'Il ruolo non riceve i permessi degli articoli.' );

		add_role( 'albo_prova_redazione', 'Prova redazione', array( 'read' => true ) );

		Permessi::applica( array( 'albo_prova_redazione' => Permessi::CHIAVI_REDAZIONE ) );

		$redazione = get_role( 'albo_prova_redazione' );

		$this->assertTrue( $redazione->has_cap( $mappa['edit_posts'] ), 'Chi redige puo\' redigere.' );
		$this->assertFalse(
			$redazione->has_cap( $mappa['publish_posts'] ),
			'Chi redige non pubblica: sono due permessi distinti e non due nomi dello stesso.'
		);

		remove_role( 'albo_prova_redazione' );
	}

	/**
	 * A-20: l'assegnazione e' ripetibile e non toglie niente.
	 *
	 * La postcondizione si rilegge dai ruoli, non dall'esito della chiamata.
	 */
	public function test_a20_assegnazione_ripetibile(): void {
		Permessi::applica();

		$mappa = $this->mappa();
		$ruolo = get_role( \AlboPretorioPa\RUOLO );
		$ruolo->add_cap( 'albo_permesso_aggiunto_dal_sito' );

		$prima = get_role( \AlboPretorioPa\RUOLO )->capabilities;

		Permessi::applica();

		$dopo = get_role( \AlboPretorioPa\RUOLO )->capabilities;

		$this->assertSame( $prima, $dopo, 'La seconda esecuzione non deve cambiare niente.' );
		$this->assertArrayHasKey(
			'albo_permesso_aggiunto_dal_sito',
			$dopo,
			'Un permesso che il sito aveva aggiunto per conto suo deve restare.'
		);
		$this->assertTrue( $dopo[ $mappa['publish_posts'] ], 'I permessi del componente devono esserci ancora.' );
	}

	/**
	 * A-22: se nessun ruolo possiede i permessi, la bacheca lo dice.
	 *
	 * La precondizione dimostra che prima della rimozione quell'avviso non c'e',
	 * altrimenti "l'avviso compare" sarebbe vero anche senza il meccanismo.
	 */
	public function test_a22_avviso_quando_nessun_ruolo_possiede_i_permessi(): void {
		Permessi::applica();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse(
			Permessi::nessun_ruolo_possiede(),
			'Precondizione: dopo l\'assegnazione almeno un ruolo deve possedere i permessi.'
		);
		$this->assertSame( '', $this->avvisi_in_bacheca(), 'Precondizione: senza il problema non c\'e\' avviso.' );

		Permessi::azzera();

		$this->assertTrue( Permessi::nessun_ruolo_possiede(), 'Nessun ruolo deve possedere i permessi.' );

		$avviso = $this->avvisi_in_bacheca();

		$this->assertNotSame( '', $avviso, 'La condizione deve essere segnalata.' );
		$this->assertStringContainsString(
			esc_html( Permessi::nome_ruolo() ),
			$avviso,
			'L\'avviso deve nominare il ruolo che dovrebbe possedere i permessi.'
		);
	}

	/**
	 * A-22: l'avviso e' riservato a chi puo' attivare i componenti.
	 */
	public function test_a22_avviso_riservato_a_chi_attiva_i_componenti(): void {
		Permessi::azzera();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame(
			'',
			$this->avvisi_in_bacheca(),
			'Chi non puo\' attivare i componenti non vede l\'avviso: non puo\' rimediare.'
		);
	}

	/**
	 * A-38: l'assegnazione riuscita si rilegge dalla banca dati.
	 *
	 * Il controllo positivo viene prima: senza, la riga sarebbe soddisfatta da
	 * un componente che fallisce sempre. Poi si sabota la scrittura di un solo
	 * permesso e si pretende l'errore: un permesso che resta nell'oggetto in
	 * memoria ma non arriva nella banca dati sparisce alla richiesta dopo, e
	 * nessuno se ne accorge.
	 */
	public function test_a38_postcondizione_dei_permessi(): void {
		$mappa = $this->mappa();

		$this->assertTrue( Permessi::applica(), 'Controllo positivo: senza sabotaggi l\'assegnazione riesce.' );

		$riletti = new \WP_Roles();

		$this->assertTrue(
			$riletti->get_role( \AlboPretorioPa\RUOLO )->has_cap( $mappa['publish_posts'] ),
			'Controllo positivo: il permesso si rilegge dalla banca dati, non dall\'oggetto in memoria.'
		);

		Permessi::azzera();

		$mancante = $mappa['publish_posts'];

		$sabota = static function ( $valore ) use ( $mancante ) {
			if ( ! is_array( $valore ) ) {
				return $valore;
			}

			foreach ( array_keys( $valore ) as $ruolo ) {
				unset( $valore[ $ruolo ]['capabilities'][ $mancante ] );
			}

			return $valore;
		};

		add_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );

		try {
			$esito = Permessi::applica();
		} finally {
			remove_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );
		}

		$this->assertWPError( $esito, 'Un permesso che non arriva nella banca dati non e\' un\'assegnazione riuscita.' );
		$this->assertSame(
			'albo_permessi_non_scritti',
			$esito->get_error_code(),
			'L\'errore deve essere quello dedicato alla postcondizione mancata.'
		);
	}
}
