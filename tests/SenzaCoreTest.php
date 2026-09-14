<?php
/**
 * Riga A-03: avvio con il componente comune assente.
 *
 * Gira in un avvio separato, senza quel componente caricato. Non e' una
 * simulazione: le sue funzioni non esistono proprio, che e' la condizione da
 * verificare.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use WP_UnitTestCase;

/**
 * Il componente comune non c'e'.
 */
class SenzaCoreTest extends WP_UnitTestCase {

	/**
	 * Percorso del componente come lo conosce WordPress.
	 *
	 * @var string
	 */
	private $componente;

	/**
	 * Precondizione: l'albo risulta fra i componenti attivi.
	 *
	 * Senza questa, "e' rimasto attivo" sarebbe vero per niente: nell'ambiente
	 * di prova l'albo e' caricato dall'avvio della suite e non compare
	 * nell'elenco degli attivi. L'utente corrente e' un amministratore perche'
	 * l'avviso e' riservato a chi puo' attivare i componenti.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->componente = plugin_basename( \AlboPretorioPa\FILE_PRINCIPALE );

		Avvio::azzera();

		update_option( 'active_plugins', array( $this->componente ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Testo degli avvisi prodotti in amministrazione.
	 *
	 * @return string
	 */
	private function avvisi_in_bacheca(): string {
		ob_start();
		do_action( 'admin_notices' );

		return (string) ob_get_clean();
	}

	/**
	 * L'ambiente e' davvero quello previsto.
	 *
	 * Se il componente comune fosse caricato, tutto il resto di questo file
	 * verificherebbe un'altra cosa restando verde.
	 */
	public function test_precondizione_core_assente(): void {
		$this->assertFalse(
			function_exists( 'conformita_core_avvia_componente' ),
			'Precondizione: le funzioni del componente comune non devono esistere.'
		);

		$this->assertContains(
			$this->componente,
			(array) get_option( 'active_plugins', array() ),
			'Precondizione: l\'albo deve risultare fra i componenti attivi.'
		);
	}

	/**
	 * A-03: l'albo resta attivo e inerte, e lo segnala.
	 */
	public function test_a03_resta_attivo_e_inerte(): void {
		$esito = Avvio::esegui();

		$this->assertFalse( $esito, 'Senza il componente comune l\'avvio non prosegue.' );

		$this->assertContains(
			$this->componente,
			(array) get_option( 'active_plugins', array() ),
			'L\'albo resta attivo: la disattivazione appartiene alla sola incompatibilita\' di versione.'
		);

		$this->assertFalse(
			Avvio::registrata(),
			'Nessuna sezione registrata e nessun avvio parziale.'
		);

		$this->assertNotSame( '', $this->avvisi_in_bacheca(), 'La condizione deve essere segnalata.' );
	}

	/**
	 * A-03: l'avviso e' riservato a chi puo' attivare i componenti.
	 */
	public function test_a03_avviso_riservato(): void {
		Avvio::esegui();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame(
			'',
			$this->avvisi_in_bacheca(),
			'Chi non puo\' attivare i componenti non deve vedere l\'avviso.'
		);
	}
}
