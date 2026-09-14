<?php
/**
 * Riga A-05: avvio con una versione di interfaccia incompatibile.
 *
 * Gira in un avvio separato che definisce la costante della versione prima di
 * caricare il componente comune. E' l'unico modo di far vedere alla guardia
 * vera una versione diversa da quella reale.
 *
 * La prima richiesta e' di amministrazione: e' il caso che la guardia del
 * componente comune copre oggi. Il caso "prima una pagina pubblica, poi
 * l'amministrazione" e' un difetto noto di quel componente, registrato a
 * parte, e non appartiene a questa riga.
 *
 * In questo avvio la procedura di avvio **non e' agganciata**: l'unica sua
 * esecuzione e' quella che la prova fa di persona. Senza, l'esecuzione
 * automatica dell'avvio della suite lascerebbe in bacheca un avviso agganciato
 * dal componente comune con una chiusura anonima, che nessuno puo' rimuovere, e
 * la prova sarebbe verde anche se la propria chiamata non producesse niente.
 * L'isolamento si dimostra qui sotto e non si da' per buono.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use WP_UnitTestCase;

/**
 * Il componente comune c'e' ma non parla la stessa lingua.
 */
class CoreIncompatibileTest extends WP_UnitTestCase {

	/**
	 * Percorso del componente come lo conosce WordPress.
	 *
	 * @var string
	 */
	private $componente;

	/**
	 * Precondizione: l'albo risulta fra i componenti attivi.
	 *
	 * Senza, "non e' piu' fra gli attivi" sarebbe vero per niente.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->componente = plugin_basename( \AlboPretorioPa\FILE_PRINCIPALE );

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();

		update_option( 'active_plugins', array( $this->componente ) );
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
	 * Comprese le due condizioni che rendono attribuibile cio' che si osserva
	 * dopo: la procedura di avvio non e' agganciata, quindi non e' mai girata da
	 * sola, e la bacheca e' pulita.
	 */
	public function test_precondizione_versione_incompatibile(): void {
		$this->assertSame(
			'2.0.0',
			conformita_core_versione_api(),
			'Precondizione: il componente comune deve esporre la versione incompatibile.'
		);

		$this->assertFalse(
			conformita_core_api_compatibile( \AlboPretorioPa\CORE_API_RICHIESTA, conformita_core_versione_api() ),
			'Precondizione: le due versioni non devono essere compatibili.'
		);

		$this->assertContains(
			$this->componente,
			(array) get_option( 'active_plugins', array() ),
			'Precondizione: l\'albo deve risultare fra i componenti attivi.'
		);

		$this->assertFalse(
			has_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) ),
			'Precondizione: in questo avvio la procedura non deve essere agganciata, altrimenti e\' gia\' girata una volta.'
		);

		$this->assertSame(
			'',
			$this->avvisi_in_bacheca(),
			'Precondizione: nessun avviso deve preesistere alla chiamata della prova.'
		);
	}

	/**
	 * A-05: la guardia disattiva il componente e non registra niente.
	 */
	public function test_a05_effetto_completo_della_guardia(): void {
		$this->assertSame(
			'',
			$this->avvisi_in_bacheca(),
			'La bacheca deve essere pulita prima della chiamata: l\'avviso che si verifica dopo nasce da questa chiamata e non dall\'avvio della suite.'
		);

		$this->assertFalse( Avvio::esegui(), 'Con la versione incompatibile l\'avvio non prosegue.' );

		$this->assertNotContains(
			$this->componente,
			(array) get_option( 'active_plugins', array() ),
			'La guardia del componente comune deve disattivare l\'albo.'
		);

		$this->assertNotContains(
			\AlboPretorioPa\SEZIONE,
			conformita_core_sezioni_registrate(),
			'Nessuna sezione deve risultare registrata.'
		);

		$avviso = $this->avvisi_in_bacheca();

		$this->assertStringContainsString(
			\AlboPretorioPa\CORE_API_RICHIESTA,
			$avviso,
			'L\'avviso deve dire la versione richiesta.'
		);

		$this->assertStringContainsString(
			conformita_core_versione_api(),
			$avviso,
			'L\'avviso deve dire la versione trovata.'
		);
	}
}
