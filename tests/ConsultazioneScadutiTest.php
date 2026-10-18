<?php
/**
 * La consultazione riservata degli atti scaduti: righe A-180..A-185 (ALBO-34).
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
use AlboPretorioPa\Installazione;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchermataScaduti;

use const AlboPretorioPa\OPZIONE_VERSIONE;
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
	 * Chiede un documento alla consegna amministrativa, come l'utente indicato.
	 *
	 * Il collegamento si costruisce per quell'utente, con il suo gettone: il
	 * rifiuto, quando arriva, viene dal permesso e non dal gettone.
	 *
	 * @param int         $utente    Utente.
	 * @param int         $documento Documento.
	 * @param string|null $indirizzo Collegamento da seguire, o nullo per quello dell'utente.
	 * @return array<int, array<string, mixed>>
	 */
	private function consegna_come( int $utente, int $documento, ?string $indirizzo = null ): array {
		wp_set_current_user( $utente );

		$indirizzo = $indirizzo ?? html_entity_decode( (string) conformita_core_indirizzo_consegna_amministrativa( $documento ) );
		wp_parse_str( (string) wp_parse_url( $indirizzo, PHP_URL_QUERY ), $parametri );
		$this->assertNotEmpty( $parametri['action'] ?? '', 'Precondizione: il collegamento della consegna amministrativa.' );

		$this->consegne = array();
		$_GET           = $parametri;
		$_REQUEST       = $parametri;
		do_action( ( $utente ? 'admin_post_' : 'admin_post_nopriv_' ) . $parametri['action'] );

		return $this->consegne;
	}

	/**
	 * Un atto con lo stato e la fine indicati, scritti direttamente.
	 *
	 * @param string      $stato   Stato.
	 * @param string|null $fine    Fine, o nulla.
	 * @param string      $oggetto Oggetto.
	 * @return int
	 */
	private function scritto_direttamente( string $stato, ?string $fine, string $oggetto ): int {
		$atto = $this->atto_completo( 'normativo', array( 'post_title' => $oggetto ) );

		$this->scavalca_barriera(
			static function () use ( $atto, $stato, $fine ) {
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato scritto come lo lascerebbe un altro programma.
				$wpdb->update( $wpdb->posts, array( 'post_status' => $stato ), array( 'ID' => $atto ) );
				clean_post_cache( $atto );

				return null === $fine ? true : conformita_core_imposta_fine_pubblicazione( $atto, $fine );
			}
		);
		clean_post_cache( $atto );
		$this->assertSame( $stato, get_post_status( $atto ), 'Precondizione: lo stato scritto.' );

		return $atto;
	}

	/**
	 * I collegamenti alle altre pagine dell'elenco, come parametri.
	 *
	 * @param string $contenuto Contenuto della schermata.
	 * @return array<int, array<string, mixed>>
	 */
	private function collegamenti_di_pagina( string $contenuto ): array {
		preg_match_all( '/<p class="albo-pretorio-pagine">(.*?)<\/p>/s', $contenuto, $blocchi );
		preg_match_all( '/href="([^"]+)"/', implode( '', $blocchi[1] ), $indirizzi );

		$pagine = array();
		foreach ( $indirizzi[1] as $indirizzo ) {
			wp_parse_str( (string) wp_parse_url( html_entity_decode( $indirizzo ), PHP_URL_QUERY ), $parametri );
			$pagine[] = $parametri;
		}

		return $pagine;
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
		$this->assertFalse( user_can( $this->consultatore, 'delete_post', DocumentiAtto::principale( $atto ) ), 'Chi consulta non cancella il documento.' );

		$bozza   = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$privato = $this->scritto_direttamente( 'private', '2041-09-30', 'Atto privato di prova' );
		$this->assertFalse( user_can( $this->consultatore, 'read_post', $bozza ), 'Chi consulta non legge le bozze.' );
		$this->assertFalse( user_can( $this->consultatore, 'read_post', $privato ), 'Chi consulta non legge i privati.' );

		// Un sito gia' installato riceve il permesso con l'aggiornamento di versione.
		get_role( 'administrator' )->remove_cap( $permesso );
		get_role( RUOLO )->remove_cap( $permesso );
		update_option( OPZIONE_VERSIONE, '0.4.0-alpha' );
		$this->assertFalse( get_role( 'administrator' )->has_cap( $permesso ), 'Precondizione: il sito di prima non ha il permesso.' );

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'L\'aggiornamento gira.' );
		$this->assertTrue( ( new \WP_Roles() )->get_role( 'administrator' )->has_cap( $permesso ), 'Dopo l\'aggiornamento l\'amministratore consulta, riletto dalla banca dati.' );
		$this->assertTrue( ( new \WP_Roles() )->get_role( RUOLO )->has_cap( $permesso ), 'Dopo l\'aggiornamento il ruolo dell\'albo consulta.' );
		$this->assertFalse( ( new \WP_Roles() )->get_role( 'pubblica_altrove' )->has_cap( $permesso ), 'L\'aggiornamento non lo da\' a un altro ruolo che pubblica.' );
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
		$bozza   = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$privato = $this->scritto_direttamente( 'private', '2041-09-30', 'Atto privato di prova' );
		$cestino = $this->scritto_direttamente( 'trash', '2041-09-30', 'Atto cestinato di prova' );

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
			foreach ( array( $bozza, $privato, $cestino ) as $non_pubblicato ) {
				$this->assertNotContains( $non_pubblicato, $this->elencati( $contenuto ), $chi . ': bozze, privati e cestinati non sono nell\'elenco.' );
			}
		}

		$this->assertSame( 'publish', get_post_status( $primo ), 'Lo stato resta pubblicato.' );
		$this->assertNotContains( $secondo, $this->elencati( (string) $this->schermata( $this->amministratore ) ) );
	}

	/**
	 * A-182: chi non ha il permesso non apre la schermata e non ne vede la voce.
	 *
	 * Chi redige, chi e' solo abbonato e il visitatore ricevono il rifiuto. Il
	 * menu si costruisce come lo costruisce WordPress, dall'aggancio
	 * `admin_menu`, e la voce conta come visibile solo se l'utente ha il
	 * permesso che la apre, perche' WordPress toglie le altre. L'amministratore
	 * trova la voce nel menu degli atti, chi consulta e basta in cima al menu,
	 * chi redige non la trova; e la pagina risulta raggiungibile solo ai primi
	 * due.
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
		$this->assertSame( 10, has_action( 'admin_menu', array( SchermataScaduti::class, 'menu' ) ), 'La voce si aggiunge dall\'aggancio del menu.' );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$voci = function ( int $utente ): array {
			global $menu, $submenu, $_registered_pages, $_parent_pages, $pagenow, $plugin_page, $typenow;

			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: il menu si costruisce da zero per ogni utente.
			$menu              = array();
			$submenu           = array();
			$_registered_pages = array();
			$_parent_pages     = array();
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

			wp_set_current_user( $utente );
			do_action( 'admin_menu', '' );

			$visibili = static function ( array $voci ): array {
				$slug = array();
				foreach ( $voci as $voce ) {
					if ( current_user_can( $voce[1] ) ) {
						$slug[] = $voce[2];
					}
				}

				return $slug;
			};

			// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: la richiesta della pagina, come la vede WordPress.
			$pagenow     = 'admin.php';
			$plugin_page = SchermataScaduti::PAGINA;
			$typenow     = '';
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
			$_GET['page'] = SchermataScaduti::PAGINA;

			$aggancio = get_plugin_page_hook( SchermataScaduti::PAGINA, 'admin.php' );

			return array(
				'in cima'        => in_array( SchermataScaduti::PAGINA, $visibili( (array) $menu ), true ),
				'sotto gli atti' => in_array( SchermataScaduti::PAGINA, $visibili( (array) ( $submenu[ 'edit.php?post_type=' . TIPO ] ?? array() ) ), true ),
				'raggiungibile'  => null !== $aggancio && has_action( $aggancio ) && user_can_access_admin_page(),
			);
		};

		$this->assertSame(
			array(
				'in cima'        => false,
				'sotto gli atti' => true,
				'raggiungibile'  => true,
			),
			$voci( $this->amministratore ),
			'Amministratore.'
		);
		$this->assertSame(
			array(
				'in cima'        => true,
				'sotto gli atti' => false,
				'raggiungibile'  => true,
			),
			$voci( $this->consultatore ),
			'Chi consulta e basta.'
		);
		$this->assertSame(
			array(
				'in cima'        => false,
				'sotto gli atti' => false,
				'raggiungibile'  => false,
			),
			$voci( $this->redattore ),
			'Chi redige.'
		);
	}

	/**
	 * A-183: dalla schermata chi consulta apre i documenti dello scaduto, e solo quelli pubblicati.
	 *
	 * Il collegamento e' quello della consegna amministrativa del meccanismo
	 * comune, e seguirlo consegna il file a chi consulta. Un abbonato non lo
	 * apre: ne' con il collegamento copiato, ne' con un collegamento valido
	 * costruito per lui, cosi' il rifiuto viene dal permesso e non dal
	 * gettone. Chi consulta, con il proprio gettone valido, non apre i
	 * documenti di una bozza ne' di un atto privato.
	 */
	public function test_a183_documenti_dalla_consegna_amministrativa(): void {
		$atto      = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$documento = (int) DocumentiAtto::principale( $atto );
		$bozza     = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$privato   = $this->scritto_direttamente( 'private', '2041-09-30', 'Atto privato di prova' );
		$this->fissa_orologi( self::PRIMO_ISTANTE_SCADUTO );

		$contenuto = (string) $this->schermata( $this->consultatore );
		$this->assertSame( 1, preg_match( '/<tr data-atto="' . $atto . '">.*?<a href="([^"]+)"[^>]*>/s', $contenuto, $collegamento ), 'C\'e\' un collegamento al documento.' );

		$indirizzo = html_entity_decode( $collegamento[1] );
		$this->assertSame( html_entity_decode( (string) conformita_core_indirizzo_consegna_amministrativa( $documento ) ), $indirizzo, 'Il collegamento e\' quello della consegna amministrativa.' );

		$consegnate = $this->consegna_come( $this->consultatore, $documento, $indirizzo );
		$this->assertCount( 1, $consegnate );
		$this->assertSame( 200, $consegnate[0]['stato'], 'Chi consulta apre il documento dello scaduto.' );
		$this->assertSame( realpath( get_attached_file( $documento ) ), $consegnate[0]['percorso'] );

		$abbonato = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		foreach ( array(
			'collegamento copiato' => $indirizzo,
			'collegamento proprio' => null,
		) as $quale => $da_seguire ) {
			$consegnate = $this->consegna_come( $abbonato, $documento, $da_seguire );
			$this->assertCount( 1, $consegnate );
			$this->assertSame( 404, $consegnate[0]['stato'], 'Abbonato, ' . $quale . ': rifiutato.' );
		}

		foreach ( array(
			'bozza'   => $bozza,
			'privato' => $privato,
		) as $quale => $non_pubblicato ) {
			$consegnate = $this->consegna_come( $this->consultatore, (int) DocumentiAtto::principale( $non_pubblicato ) );
			$this->assertCount( 1, $consegnate );
			$this->assertSame( 404, $consegnate[0]['stato'], 'Chi consulta non apre il documento di un atto ' . $quale . '.' );
		}

		$consegnate = $this->consegna_come( $this->amministratore, (int) DocumentiAtto::principale( $bozza ) );
		$this->assertSame( 200, $consegnate[0]['stato'] ?? 0, 'Precondizione: il documento della bozza si apre a chi gestisce gli atti.' );
	}

	/**
	 * A-184: la ricerca per anno e numero, oggetto e tipo, con il gettone.
	 *
	 * Ogni criterio restringe l'elenco a un sottoinsieme proprio degli
	 * scaduti; il numero di dieci cifre si cerca come gli altri. Un campo
	 * scritto male non si scarta in silenzio: la ricerca non si fa e c'e' un
	 * messaggio. Un numero senza anno e' rifiutato; senza un gettone valido la
	 * ricerca non si applica e l'elenco resta intero, con un avviso.
	 */
	public function test_a184_ricerca(): void {
		$primo   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Graduatoria di prova' );
		$secondo = $this->pubblicato_il( '2041-10-02 09:30:00', 'Bando di gara è aperto', 'scelto' );
		$alto    = $this->scritto_direttamente( 'publish', '2041-10-20', 'Atto con numero alto' );

		$this->scavalca_barriera(
			static function () use ( $alto ) {
				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- prova: un numero di dieci cifre, come lo lascerebbe un registro precedente.
				return $wpdb->insert(
					Repertorio::tabella_assegnazioni(),
					array(
						'anno'         => 2041,
						'numero'       => 1000000000,
						'atto_id'      => $alto,
						'assegnato_il' => '2041-10-05 10:00:00',
					)
				);
			}
		);
		$this->fissa_orologi( '2041-11-01 12:00:00' );

		$numero = Repertorio::di( $secondo );
		$this->assertIsArray( $numero );
		$this->assertSame( 2041, $numero['anno'] );

		$tutti = $this->elencati( (string) $this->schermata( $this->consultatore ) );
		$this->assertSame( array( $alto, $primo, $secondo ), $tutti, 'Precondizione: tutti e tre scaduti, dalla fine piu\' recente.' );

		$cerca = function ( array $campi ): array {
			return $this->elencati( (string) $this->schermata( $this->consultatore, $this->cerca( $campi ) ) );
		};

		$this->assertSame(
			array( $secondo ),
			$cerca(
				array(
					SchermataScaduti::CAMPO_ANNO   => '2041',
					SchermataScaduti::CAMPO_NUMERO => (string) $numero['numero'],
				)
			),
			'Per anno e numero.'
		);
		$this->assertSame(
			array( $alto ),
			$cerca(
				array(
					SchermataScaduti::CAMPO_ANNO   => '2041',
					SchermataScaduti::CAMPO_NUMERO => '1000000000',
				)
			),
			'Un numero di dieci cifre.'
		);
		$this->assertSame( $tutti, $cerca( array( SchermataScaduti::CAMPO_ANNO => '2041' ) ), 'Per anno.' );
		$this->assertSame( array(), $cerca( array( SchermataScaduti::CAMPO_ANNO => '2040' ) ), 'Un altro anno.' );
		$this->assertSame( array( $primo ), $cerca( array( SchermataScaduti::CAMPO_OGGETTO => 'graduatoria' ) ), 'Per oggetto.' );
		$this->assertSame( array( $secondo ), $cerca( array( SchermataScaduti::CAMPO_OGGETTO => 'gara è' ) ), 'Per oggetto, con spazio e accento.' );
		$this->assertSame( array( $secondo ), $cerca( array( SchermataScaduti::CAMPO_TIPO => (string) $this->voci['scelto'] ) ), 'Per tipo: solo quello di quel tipo.' );
		$this->assertSame( array( $alto, $primo ), $cerca( array( SchermataScaduti::CAMPO_TIPO => (string) $this->voci['normativo'] ) ), 'Per l\'altro tipo.' );

		foreach ( array(
			'numero oltre il massimo' => array(
				SchermataScaduti::CAMPO_ANNO   => '2041',
				SchermataScaduti::CAMPO_NUMERO => '4294967296',
			),
			'numero con lettere'      => array(
				SchermataScaduti::CAMPO_ANNO   => '2041',
				SchermataScaduti::CAMPO_NUMERO => '12a',
			),
			'anno con il punto'       => array( SchermataScaduti::CAMPO_ANNO => '2.041' ),
			'tipo come elenco'        => array( SchermataScaduti::CAMPO_TIPO => array( '1' ) ),
			'oggetto come elenco'     => array( SchermataScaduti::CAMPO_OGGETTO => array( 'Bando' ) ),
		) as $caso => $campi ) {
			$contenuto = (string) $this->schermata( $this->consultatore, $this->cerca( $campi ) );
			$this->assertSame( array(), $this->elencati( $contenuto ), $caso . ': la ricerca non si fa.' );
			$this->assertStringContainsString( 'La ricerca non e&#039; stata fatta', $contenuto, $caso . ': c\'e\' il messaggio.' );
		}

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

	/**
	 * A-185: le pagine dell'elenco, seguite dai loro collegamenti, con e senza ricerca.
	 *
	 * Ventitre' scaduti, ventuno con la stessa fine e lo stesso inizio di
	 * oggetto, piu' uno in corso. Seguendo i collegamenti generati dalla
	 * schermata, le pagine messe insieme danno tutti e soli gli scaduti, una
	 * volta ciascuno e nell'ordine dichiarato; con una ricerca per oggetto,
	 * con spazi e accento, i collegamenti la conservano.
	 */
	public function test_a185_pagine_dell_elenco(): void {
		$bandi = array();
		for ( $i = 1; $i <= 21; $i++ ) {
			$bandi[] = $this->pubblicato_il( '2041-10-01 09:30:00', 'Bando di gara è aperto ' . $i );
		}
		$altri    = array(
			$this->pubblicato_il( '2041-10-02 09:30:00', 'Graduatoria di prova' ),
			$this->pubblicato_il( '2041-10-03 09:30:00', 'Avviso di prova' ),
		);
		$in_corso = $this->pubblicato_il( '2041-10-20 09:30:00', 'Bando di gara è aperto in corso' );
		$this->fissa_orologi( '2041-10-25 12:00:00' );

		rsort( $bandi );
		$attesi = array_merge( array( $altri[1], $altri[0] ), $bandi );

		// Il gettone della ricerca vale per chi lo usa: si crea come chi consulta.
		wp_set_current_user( $this->consultatore );

		foreach ( array(
			'senza ricerca'  => array( array(), $attesi ),
			'con la ricerca' => array( $this->cerca( array( SchermataScaduti::CAMPO_OGGETTO => 'gara è' ) ), $bandi ),
		) as $caso => list( $parametri, $previsti ) ) {
			$prima  = (string) $this->schermata( $this->consultatore, $parametri );
			$pagine = $this->collegamenti_di_pagina( $prima );
			$this->assertCount( 1, $pagine, $caso . ': un collegamento, alla seconda pagina.' );
			$this->assertSame( '2', $pagine[0][ SchermataScaduti::CAMPO_PAGINA ] ?? '', $caso . ': il collegamento porta alla seconda pagina.' );

			$seconda = (string) $this->schermata( $this->consultatore, $pagine[0] );
			$insieme = array_merge( $this->elencati( $prima ), $this->elencati( $seconda ) );

			$this->assertCount( SchermataScaduti::PER_PAGINA, $this->elencati( $prima ), $caso . ': la prima pagina e\' piena.' );
			$this->assertSame( $previsti, $insieme, $caso . ': tutti e soli, una volta ciascuno, nell\'ordine.' );
			$this->assertNotContains( $in_corso, $insieme, $caso . ': l\'atto in corso non c\'e\'.' );
		}
	}
}
