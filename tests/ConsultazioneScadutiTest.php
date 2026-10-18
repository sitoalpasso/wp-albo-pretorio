<?php
/**
 * La consultazione riservata degli atti scaduti: righe A-180..A-184 (ALBO-34).
 *
 * La schermata vive nell'amministrazione, dove il filtro di scadenza del
 * meccanismo comune non si applica: le prove la aprono con lo schermo
 * dell'amministrazione impostato, come accade su un sito vero, e con
 * l'orologio fissato. Il compito pianificato non gira mai: lo stato resta
 * pubblicato, ed e' la data a dire chi e' scaduto.
 *
 * Che la consultazione non riapra niente sul sito pubblico lo provano A-175,
 * A-177 e A-179, ripetute anche per chi ha soltanto questo permesso.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\SchermataScaduti;

use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/**
 * Chi ha il permesso consulta gli scaduti, e solo loro, senza poterli toccare.
 */
class ConsultazioneScadutiTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'ultimo istante in cui il primo atto e' in pubblicazione.
	 */
	private const ULTIMO_ISTANTE = '2041-10-16 23:59:59';

	/**
	 * Il primo istante in cui il primo atto e' scaduto.
	 */
	private const PRIMO_ISTANTE_SCADUTO = '2041-10-17 00:00:00';

	/**
	 * Chi ha soltanto il permesso di consultare.
	 *
	 * @var int
	 */
	private int $consultatore = 0;

	/**
	 * Le consegne emesse dal meccanismo comune.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $consegne = array();

	/**
	 * Prepara il flusso, chi consulta e lo schermo dell'amministrazione.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->consultatore = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );

		set_current_screen( 'dashboard' );

		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ): void {
				$this->consegne[] = $risposta;
			}
		);
	}

	/**
	 * Ripristina lo stato.
	 */
	public function tear_down(): void {
		\Conformita_Core_Consegna::fissa_emettitore( null );
		$GLOBALS['current_screen'] = null;
		$_GET                      = array();
		$_REQUEST                  = array();
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Un atto pubblicato con il passaggio vero, nell'istante indicato.
	 *
	 * @param string $istante Data e ora nel fuso del sito.
	 * @param string $oggetto Oggetto.
	 * @param string $tipo    Tipo di atto.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto, string $tipo = 'normativo' ): int {
		$this->fissa_orologi( $istante );

		$id    = $this->atto_in_verifica( $tipo, array( 'post_title' => $oggetto ) );
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		return $id;
	}

	/**
	 * Apre la schermata come l'utente indicato, con i parametri indicati.
	 *
	 * @param int                  $utente    Utente.
	 * @param array<string, mixed> $parametri Parametri della richiesta.
	 * @return string|null Il contenuto, o nullo se la schermata ha rifiutato.
	 */
	private function schermata( int $utente, array $parametri = array() ): ?string {
		wp_set_current_user( $utente );

		$_GET     = $parametri;
		$_REQUEST = $parametri;

		ob_start();

		try {
			SchermataScaduti::mostra();
		} catch ( \WPDieException $rifiuto ) {
			ob_end_clean();
			$this->assertStringContainsString( 'permesso di consultare', $rifiuto->getMessage() );

			return null;
		}

		return (string) ob_get_clean();
	}

	/**
	 * Gli atti elencati nel contenuto, nell'ordine.
	 *
	 * @param string $contenuto Contenuto della schermata.
	 * @return array<int, int>
	 */
	private function elencati( string $contenuto ): array {
		preg_match_all( '/data-atto="(\d+)"/', $contenuto, $trovati );

		return array_map( 'intval', $trovati[1] );
	}

	/**
	 * Una ricerca con il gettone giusto.
	 *
	 * @param array<string, mixed> $campi Campi.
	 * @return array<string, mixed>
	 */
	private function cerca( array $campi ): array {
		return array_merge( $campi, array( SchermataScaduti::CAMPO_GETTONE => wp_create_nonce( SchermataScaduti::AZIONE ) ) );
	}

	/**
	 * A-180: il permesso arriva all'amministratore e al ruolo dell'albo, e a nessun altro.
	 *
	 * Lo crea il meccanismo comune, uno per tipo; l'albo lo assegna. Un ruolo
	 * che pubblica senza essere quello dell'albo non lo riceve da se': agli
	 * altri ruoli lo da' l'amministrazione. Chi ha soltanto questo permesso
	 * non modifica, non pubblica e non legge le bozze.
	 */
	public function test_a180_permesso_di_consultazione(): void {
		$permesso = conformita_core_capacita_consultazione_scaduti( TIPO );

		$this->assertIsString( $permesso );
		$this->assertSame( $permesso, Permessi::mappa()[ Permessi::CHIAVE_CONSULTAZIONE ], 'Il nome viene dal meccanismo comune.' );
		$this->assertSame( $permesso, SchermataScaduti::permesso() );

		$mappa = Permessi::mappa();
		add_role( 'pubblica_altrove', 'Pubblica altrove', array( 'read' => true ) );
		$altro = get_role( 'pubblica_altrove' );
		foreach ( Permessi::CHIAVI_PUBBLICAZIONE as $chiave ) {
			$altro->add_cap( $mappa[ $chiave ] );
		}

		$this->assertTrue( Permessi::applica() );

		$this->assertTrue( get_role( 'administrator' )->has_cap( $permesso ), 'L\'amministratore consulta.' );
		$this->assertTrue( get_role( RUOLO )->has_cap( $permesso ), 'Il ruolo dell\'albo consulta.' );
		$this->assertFalse( get_role( 'pubblica_altrove' )->has_cap( $permesso ), 'Un altro ruolo che pubblica non riceve il permesso da se\'.' );
		$this->assertFalse( get_role( 'editor' )->has_cap( $permesso ) );
		$this->assertFalse( user_can( $this->redattore, $permesso ), 'Chi redige non consulta.' );
		$this->assertTrue( user_can( $this->consultatore, $permesso ) );

		$atto = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );

		$this->assertFalse( user_can( $this->consultatore, 'edit_post', $atto ), 'Chi consulta non modifica l\'atto.' );
		$this->assertFalse( user_can( $this->consultatore, 'delete_post', $atto ) );
		$this->assertFalse( user_can( $this->consultatore, $mappa['publish_posts'] ) );
		$this->assertFalse( user_can( $this->consultatore, $mappa['edit_posts'] ) );
		$this->assertFalse( user_can( $this->consultatore, $mappa['read_private_posts'] ) );
		$this->assertFalse( user_can( $this->consultatore, 'edit_post', DocumentiAtto::principale( $atto ) ), 'Chi consulta non modifica il documento.' );
	}

	/**
	 * A-181: la schermata elenca gli scaduti dalla mezzanotte della fine, e solo loro.
	 *
	 * Due atti, fine il 16 e il 20 ottobre 2041, piu' un atto scritto come lo
	 * scrivera' l'importatore, con una fine del 30 settembre. All'ultimo
	 * secondo del 16 c'e' solo l'importato; dalla mezzanotte anche il primo,
	 * il secondo mai. Lo stato resta pubblicato: il compito pianificato non e'
	 * mai partito.
	 */
	public function test_a181_elenca_gli_scaduti_dalla_mezzanotte(): void {
		$primo     = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$secondo   = $this->pubblicato_il( '2041-10-05 10:00:00', 'Avviso di prova' );
		$importato = $this->atto_completo(
			'normativo',
			array(
				'post_title'    => 'Atto importato di prova',
				'post_date'     => '2041-09-15 10:00:00',
				'post_date_gmt' => '2041-09-15 08:00:00',
			)
		);
		$this->scavalca_barriera(
			static function () use ( $importato ) {
				wp_publish_post( $importato );

				return conformita_core_imposta_fine_pubblicazione( $importato, '2041-09-30' );
			}
		);

		foreach ( array(
			'amministratore' => $this->amministratore,
			'consultatore'   => $this->consultatore,
		) as $chi => $utente ) {
			$this->fissa_orologi( self::ULTIMO_ISTANTE );
			$this->assertSame( array( $importato ), $this->elencati( (string) $this->schermata( $utente ) ), $chi . ', ultimo secondo: solo l\'importato e\' scaduto.' );

			$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );
			$contenuto = (string) $this->schermata( $utente );
			$this->assertSame( array( $primo, $importato ), $this->elencati( $contenuto ), $chi . ', mezzanotte: il primo e l\'importato, dal piu\' recente; il secondo no.' );
			$this->assertStringContainsString( 'Graduatoria di prova', $contenuto );
			$this->assertStringContainsString( 'dal 01/10/2041 al 16/10/2041', $contenuto, 'Le date della pubblicazione.' );
			$this->assertStringNotContainsString( 'Avviso di prova', $contenuto, 'Dell\'atto in corso non c\'e\' niente.' );
		}

		$this->assertSame( 'publish', get_post_status( $primo ), 'Lo stato resta pubblicato.' );
		$this->assertNotContains( $secondo, $this->elencati( (string) $this->schermata( $this->amministratore ) ) );
	}

	/**
	 * A-182: chi non ha il permesso non apre la schermata e non ne vede la voce.
	 *
	 * Chi redige, chi e' solo abbonato e il visitatore ricevono il rifiuto;
	 * l'amministratore trova la voce nel menu degli atti, chi consulta e basta
	 * la trova in cima al menu, perche' quello degli atti non lo vede.
	 */
	public function test_a182_accesso_solo_con_il_permesso(): void {
		$atto = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );

		$abbonato = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		foreach ( array(
			'chi redige' => $this->redattore,
			'abbonato'   => $abbonato,
			'visitatore' => 0,
		) as $chi => $utente ) {
			$this->assertNull( $this->schermata( $utente ), $chi . ': la schermata rifiuta.' );
		}

		$this->assertContains( $atto, $this->elencati( (string) $this->schermata( $this->consultatore ) ), 'Precondizione: chi consulta la apre.' );

		$voci = function ( int $utente ): array {
			global $menu, $submenu;

			$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: si osserva il menu costruito da zero.
			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: si osserva il menu costruito da zero.
			wp_set_current_user( $utente );
			SchermataScaduti::menu();

			$in_cima  = array_column( (array) $menu, 2 );
			$sotto    = array_column( (array) ( $submenu[ 'edit.php?post_type=' . TIPO ] ?? array() ), 2 );
			$permesso = SchermataScaduti::permesso();

			return array(
				'in cima'        => in_array( SchermataScaduti::PAGINA, $in_cima, true ),
				'sotto gli atti' => in_array( SchermataScaduti::PAGINA, $sotto, true ),
				'accessibile'    => null !== $permesso && current_user_can( $permesso ),
			);
		};

		$this->assertSame(
			array(
				'in cima'        => false,
				'sotto gli atti' => true,
				'accessibile'    => true,
			),
			$voci( $this->amministratore )
		);
		$this->assertSame(
			array(
				'in cima'        => true,
				'sotto gli atti' => false,
				'accessibile'    => true,
			),
			$voci( $this->consultatore )
		);
		$this->assertFalse( $voci( $this->redattore )['accessibile'], 'Chi redige non ha la voce.' );
	}

	/**
	 * A-183: dalla schermata chi consulta apre i documenti dello scaduto.
	 *
	 * Il collegamento e' quello della consegna amministrativa del meccanismo
	 * comune; seguirlo consegna il file a chi consulta e lo rifiuta a chi
	 * non ha il permesso, con lo stesso collegamento.
	 */
	public function test_a183_documenti_dalla_consegna_amministrativa(): void {
		$atto      = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$documento = (int) DocumentiAtto::principale( $atto );
		$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );

		$contenuto = (string) $this->schermata( $this->consultatore );
		$this->assertSame( 1, preg_match( '/<a href="([^"]+)"[^>]*>/', $contenuto, $collegamento ), 'C\'e\' un collegamento al documento.' );

		$indirizzo = html_entity_decode( $collegamento[1] );
		$this->assertSame( html_entity_decode( (string) conformita_core_indirizzo_consegna_amministrativa( $documento ) ), $indirizzo, 'Il collegamento e\' quello della consegna amministrativa.' );

		wp_parse_str( (string) wp_parse_url( $indirizzo, PHP_URL_QUERY ), $parametri );

		$segui = function ( int $utente ) use ( $parametri ): array {
			wp_set_current_user( $utente );
			$this->consegne = array();
			$_GET           = $parametri;
			$_REQUEST       = $parametri;
			do_action( $utente ? 'admin_post_' . $parametri['action'] : 'admin_post_nopriv_' . $parametri['action'] );

			return $this->consegne;
		};

		$consegnate = $segui( $this->consultatore );
		$this->assertCount( 1, $consegnate );
		$this->assertSame( 200, $consegnate[0]['stato'], 'Chi consulta apre il documento dello scaduto.' );
		$this->assertSame( realpath( get_attached_file( $documento ) ), $consegnate[0]['percorso'] );

		$abbonato   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$consegnate = $segui( $abbonato );
		$this->assertCount( 1, $consegnate );
		$this->assertSame( 404, $consegnate[0]['stato'], 'Senza il permesso lo stesso collegamento e\' rifiutato.' );
	}

	/**
	 * A-184: la ricerca per anno e numero, oggetto e tipo, con il gettone.
	 *
	 * Ogni criterio restringe l'elenco agli scaduti giusti; un numero senza
	 * anno e' rifiutato con un messaggio; senza un gettone valido la ricerca
	 * non si applica e l'elenco resta intero, con un avviso.
	 */
	public function test_a184_ricerca(): void {
		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$secondo = $this->pubblicato_il( '2041-10-02 09:30:00', 'Avviso di gara', 'normativo' );
		$this->fissa_orologi( '2041-11-01 12:00:00' );

		$numero = \AlboPretorioPa\Repertorio::di( $secondo );
		$this->assertIsArray( $numero );

		$tutti = $this->elencati( (string) $this->schermata( $this->consultatore ) );
		$this->assertSame( array( $secondo, $primo ), $tutti, 'Precondizione: tutti e due scaduti, dal piu\' recente.' );

		$this->assertSame(
			array( $secondo ),
			$this->elencati(
				(string) $this->schermata(
					$this->consultatore,
					$this->cerca(
						array(
							SchermataScaduti::CAMPO_ANNO   => (string) $numero['anno'],
							SchermataScaduti::CAMPO_NUMERO => (string) $numero['numero'],
						)
					)
				)
			),
			'Per anno e numero.'
		);
		$this->assertSame( $tutti, $this->elencati( (string) $this->schermata( $this->consultatore, $this->cerca( array( SchermataScaduti::CAMPO_ANNO => (string) $numero['anno'] ) ) ) ), 'Per anno.' );
		$this->assertSame( array(), $this->elencati( (string) $this->schermata( $this->consultatore, $this->cerca( array( SchermataScaduti::CAMPO_ANNO => '2040' ) ) ) ), 'Un altro anno.' );
		$this->assertSame( array( $primo ), $this->elencati( (string) $this->schermata( $this->consultatore, $this->cerca( array( SchermataScaduti::CAMPO_OGGETTO => 'graduatoria' ) ) ) ), 'Per oggetto.' );

		$tipo = get_term( $this->voci['normativo'], TASSONOMIA_TIPO_ATTO );
		$this->assertInstanceOf( \WP_Term::class, $tipo );
		$this->assertSame( $tutti, $this->elencati( (string) $this->schermata( $this->consultatore, $this->cerca( array( SchermataScaduti::CAMPO_TIPO => (string) $tipo->term_id ) ) ) ), 'Per tipo.' );
		$this->assertSame( array(), $this->elencati( (string) $this->schermata( $this->consultatore, $this->cerca( array( SchermataScaduti::CAMPO_TIPO => (string) ( $tipo->term_id + 1000 ) ) ) ) ), 'Un tipo senza atti.' );

		$senza_anno = (string) $this->schermata( $this->consultatore, $this->cerca( array( SchermataScaduti::CAMPO_NUMERO => '1' ) ) );
		$this->assertSame( array(), $this->elencati( $senza_anno ) );
		$this->assertStringContainsString( 'serve anche l&#039;anno', $senza_anno );

		$senza_gettone = (string) $this->schermata(
			$this->consultatore,
			array(
				SchermataScaduti::CAMPO_OGGETTO => 'graduatoria',
				SchermataScaduti::CAMPO_GETTONE => 'sbagliato',
			)
		);
		$this->assertSame( $tutti, $this->elencati( $senza_gettone ), 'Senza gettone valido la ricerca non si applica.' );
		$this->assertStringContainsString( 'non e&#039; stata applicata', $senza_gettone );
	}
}
