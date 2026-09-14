<?php
/**
 * Prove dell'unita' di avvio: righe A-01, A-02, A-04, A-06, A-07, A-08, A-09, A-10.
 *
 * Le righe A-03 e A-05 stanno in avvii separati, perche' il loro caso non esiste
 * in un processo dove il componente comune e' caricato e compatibile.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use WP_UnitTestCase;

/**
 * Avvio del componente e dichiarazione delle politiche.
 */
class AvvioTest extends WP_UnitTestCase {

	/**
	 * Percorso del componente come lo conosce WordPress.
	 *
	 * @var string
	 */
	private $componente;

	/**
	 * Registri azzerati prima di ogni prova.
	 *
	 * Il registro delle sezioni del componente comune e lo stato interno
	 * dell'albo vivono in memoria per tutta l'esecuzione: una prova che ne
	 * lascia dietro uno renderebbe verde o rossa la successiva per un motivo
	 * che non c'entra con cio' che quella prova verifica.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->componente = plugin_basename( \AlboPretorioPa\FILE_PRINCIPALE );

		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();

		update_option( 'active_plugins', array( $this->componente ) );
	}

	/**
	 * Elenco corrente dei componenti attivi.
	 *
	 * @return array<int, string>
	 */
	private function componenti_attivi(): array {
		return (array) get_option( 'active_plugins', array() );
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
	 * La politica che l'albo dichiara, come la rilegge il componente comune.
	 *
	 * @return array<string, string>
	 */
	private function politica_registrata(): array {
		$politica = conformita_core_politica_sezione( \AlboPretorioPa\SEZIONE );

		$this->assertNotWPError( $politica, 'La sezione deve risultare registrata.' );

		return $politica->come_array();
	}

	/**
	 * A-01: la dipendenza e' dichiarata in due posti, e i due dicono la stessa cosa.
	 *
	 * L'intestazione garantisce la presenza e non la versione; il controllo
	 * all'avvio guarda la versione ma non puo' impedire un'attivazione.
	 */
	public function test_a01_dipendenza_dichiarata_in_due_posti(): void {
		$intestazione = get_file_data(
			\AlboPretorioPa\FILE_PRINCIPALE,
			array( 'richiede' => 'Requires Plugins' )
		);

		$this->assertSame(
			'conformita-core',
			$intestazione['richiede'],
			'L\'intestazione deve dichiarare la dipendenza con lo slug del componente comune.'
		);

		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+$/',
			\AlboPretorioPa\CORE_API_RICHIESTA,
			'La versione di interfaccia richiesta deve essere completa.'
		);

		$this->assertTrue(
			conformita_core_api_compatibile( \AlboPretorioPa\CORE_API_RICHIESTA, conformita_core_versione_api() ),
			'La versione richiesta deve essere soddisfatta da quella che il componente comune espone.'
		);
	}

	/**
	 * A-01: WordPress rifiuta l'attivazione con il codice dedicato.
	 *
	 * Si verifica il **codice** dell'errore e non il testo del messaggio, che su
	 * un sito italiano e' tradotto. La prova costruisce un componente finto che
	 * dichiara una dipendenza inesistente, perche' l'albo nell'ambiente di prova
	 * non sta nella cartella dei componenti e fallirebbe per un altro motivo.
	 */
	public function test_a01_wordpress_rifiuta_con_il_codice_dedicato(): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$cartella = WP_PLUGIN_DIR . '/albo-prova-dipendenza';
		$file     = $cartella . '/albo-prova-dipendenza.php';

		if ( ! is_dir( $cartella ) ) {
			mkdir( $cartella, 0777, true );
		}

		file_put_contents(
			$file,
			"<?php\n/**\n * Plugin Name: Prova dipendenza\n * Requires Plugins: componente-che-non-esiste\n */\n"
		);

		try {
			wp_clean_plugins_cache( false );
			\WP_Plugin_Dependencies::initialize();

			$esito = validate_plugin_requirements( 'albo-prova-dipendenza/albo-prova-dipendenza.php' );

			$this->assertWPError( $esito, 'Una dipendenza inesistente deve far fallire la verifica.' );
			$this->assertSame(
				'plugin_missing_dependencies',
				$esito->get_error_code(),
				'Il codice dell\'errore e\' l\'appiglio stabile: il messaggio e\' tradotto.'
			);
		} finally {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}

			if ( is_dir( $cartella ) ) {
				rmdir( $cartella );
			}

			wp_clean_plugins_cache( false );
		}
	}

	/**
	 * A-02: il file principale non chiama il componente comune al caricamento.
	 *
	 * In ordine alfabetico l'albo puo' essere caricato prima, e una chiamata al
	 * caricamento del file sarebbe un errore fatale su un sito vero.
	 */
	public function test_a02_avvio_rimandato_a_plugins_loaded(): void {
		$this->assertNotFalse(
			has_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) ),
			'L\'avvio deve essere agganciato a plugins_loaded.'
		);

		$sorgente = file_get_contents( \AlboPretorioPa\FILE_PRINCIPALE );
		$sorgente = preg_replace( '/^\s*\*.*$/m', '', (string) $sorgente );

		$this->assertSame(
			0,
			preg_match( '/conformita_core_[a-z_]+\s*\(/', (string) $sorgente ),
			'Il file principale non deve chiamare il componente comune: al suo caricamento potrebbe non esserci.'
		);
	}

	/**
	 * A-04: il criterio di compatibilita', sui tre casi che contano.
	 *
	 * @dataProvider casi_di_compatibilita
	 *
	 * @param string $disponibile Versione esposta dal componente comune.
	 * @param bool   $atteso      Esito atteso.
	 */
	public function test_a04_criterio_di_compatibilita( string $disponibile, bool $atteso ): void {
		$this->assertSame(
			$atteso,
			conformita_core_api_compatibile( \AlboPretorioPa\CORE_API_RICHIESTA, $disponibile ),
			'Criterio di compatibilita\' sbagliato per la versione ' . $disponibile . '.'
		);
	}

	/**
	 * I tre casi della compatibilita'.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function casi_di_compatibilita(): array {
		return array(
			'versione inferiore, stesso numero maggiore' => array( '1.1.0', false ),
			'numero maggiore diverso'                    => array( '2.0.0', false ),
			'versione superiore, stesso numero maggiore' => array( '1.3.0', true ),
		);
	}

	/**
	 * A-06: con il componente comune compatibile l'avvio prosegue davvero.
	 *
	 * E' il controllo positivo: senza, le prove che pretendono un rifiuto
	 * sarebbero soddisfatte da un componente che non parte mai.
	 */
	public function test_a06_avvio_con_core_compatibile(): void {
		$this->assertTrue( Avvio::esegui(), 'Con il componente comune compatibile l\'avvio deve riuscire.' );

		$this->assertContains(
			$this->componente,
			$this->componenti_attivi(),
			'Il componente deve restare attivo.'
		);

		$this->assertContains(
			\AlboPretorioPa\SEZIONE,
			conformita_core_sezioni_registrate(),
			'La sezione deve risultare registrata presso il componente comune.'
		);

		$this->assertSame( '', $this->avvisi_in_bacheca(), 'Un avvio riuscito non produce avvisi.' );
	}

	/**
	 * A-07: le due politiche si rileggono dal componente comune.
	 *
	 * Non dallo stato interno dell'albo: scrivere i valori giusti in una
	 * variabile propria senza dichiararli non deve bastare a far passare
	 * questa prova.
	 */
	public function test_a07_politiche_rilette_dal_core(): void {
		$this->assertTrue( Avvio::esegui() );

		$this->assertSame(
			array(
				'indicizzazione' => 'vietata',
				'scadenza'       => 'irraggiungibile',
			),
			$this->politica_registrata(),
			'Le due politiche devono essere quelle dichiarate dall\'albo.'
		);
	}

	/**
	 * A-08: la seconda invocazione nella stessa richiesta non registra di nuovo.
	 *
	 * L'idempotenza poggia sullo stato interno della classe, non sul confronto
	 * fra le politiche trovate e quelle attese: vedi A-09.
	 */
	public function test_a08_avvio_invocato_due_volte_nella_stessa_richiesta(): void {
		$this->assertTrue( Avvio::esegui(), 'La prima invocazione deve riuscire.' );

		$prima = $this->politica_registrata();

		$this->assertTrue( Avvio::esegui(), 'La seconda invocazione deve riuscire senza registrare di nuovo.' );

		$this->assertSame(
			array( \AlboPretorioPa\SEZIONE ),
			array_values(
				array_filter(
					conformita_core_sezioni_registrate(),
					static function ( $sezione ) {
						return \AlboPretorioPa\SEZIONE === $sezione;
					}
				)
			),
			'Dopo due invocazioni la sezione deve risultare registrata una volta sola.'
		);

		$this->assertSame( $prima, $this->politica_registrata(), 'Le politiche non devono cambiare.' );
		$this->assertSame( '', $this->avvisi_in_bacheca(), 'La seconda invocazione non produce avvisi.' );
	}

	/**
	 * A-09: la sezione gia' registrata da altri e' un conflitto in entrambi i casi.
	 *
	 * @dataProvider politiche_preesistenti
	 *
	 * @param array<string, string> $politica Politica con cui altri hanno registrato la sezione.
	 */
	public function test_a09_sezione_gia_registrata_da_altri( array $politica ): void {
		$this->assertTrue(
			conformita_core_registra_sezione( \AlboPretorioPa\SEZIONE, $politica ),
			'Precondizione: la sezione deve risultare registrata da altri prima dell\'avvio.'
		);

		$this->assertFalse( Avvio::esegui(), 'La sezione gia\' registrata da altri e\' un conflitto.' );

		$this->assertContains(
			$this->componente,
			$this->componenti_attivi(),
			'Il componente resta attivo e inerte: non si disattiva per un conflitto di sezione.'
		);

		$this->assertSame(
			$politica,
			$this->politica_registrata(),
			'Le politiche preesistenti devono restare intatte.'
		);

		$avviso = $this->avvisi_in_bacheca();

		$this->assertStringContainsString(
			\AlboPretorioPa\SEZIONE,
			$avviso,
			'L\'avviso deve nominare la sezione in conflitto.'
		);
	}

	/**
	 * Le due preregistrazioni esterne.
	 *
	 * Quella con politiche identiche e' il caso che conta: l'uguaglianza dei
	 * valori non dimostra che la registrazione sia dell'albo.
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	public function politiche_preesistenti(): array {
		return array(
			'politiche diverse'   => array(
				array(
					'indicizzazione' => 'consentita',
					'scadenza'       => 'archivio',
				),
			),
			'politiche identiche' => array(
				array(
					'indicizzazione' => 'vietata',
					'scadenza'       => 'irraggiungibile',
				),
			),
		);
	}

	/**
	 * A-09: l'avviso del conflitto e' riservato a chi puo' attivare i componenti.
	 */
	public function test_a09_avviso_riservato_a_chi_attiva_i_componenti(): void {
		conformita_core_registra_sezione(
			\AlboPretorioPa\SEZIONE,
			array(
				'indicizzazione' => 'consentita',
				'scadenza'       => 'archivio',
			)
		);

		Avvio::esegui();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame(
			'',
			$this->avvisi_in_bacheca(),
			'Chi non puo\' attivare i componenti non deve vedere l\'avviso: non puo\' rimediare.'
		);
	}

	/**
	 * A-10: con il filtro di scadenza non avviato la registrazione e' rifiutata.
	 *
	 * L'errore si osserva **attraverso la funzione di avvio dell'albo**, non
	 * chiamando la registrazione del componente comune: quest'ultima
	 * ricollauderebbe la guardia del componente comune e non direbbe niente su
	 * come reagisce l'albo.
	 */
	public function test_a10_motore_di_scadenza_non_avviato(): void {
		\Conformita_Core_Filtro_Scadenza::azzera_avvio();

		try {
			$esito = Avvio::esegui();

			$this->assertInstanceOf(
				'WP_Error',
				$esito,
				'Con il motore non avviato l\'avvio deve restituire l\'errore, non un semplice falso.'
			);

			$this->assertSame(
				'conformita_core_motore_non_avviato',
				$esito->get_error_code(),
				'L\'errore deve essere quello dedicato al motore non avviato.'
			);

			$this->assertContains(
				$this->componente,
				$this->componenti_attivi(),
				'Il componente resta attivo e inerte: la disattivazione appartiene alla sola incompatibilita\' di versione.'
			);

			$this->assertNotContains(
				\AlboPretorioPa\SEZIONE,
				conformita_core_sezioni_registrate(),
				'Nessuna sezione deve risultare registrata.'
			);

			$this->assertNotSame( '', $this->avvisi_in_bacheca(), 'La condizione deve essere segnalata.' );
		} finally {
			\Conformita_Core_Filtro_Scadenza::avvia();
		}

		$this->assertTrue(
			\Conformita_Core_Filtro_Scadenza::avviato(),
			'Il filtro deve essere ripristinato anche se un\'asserzione fallisce.'
		);
	}
}
