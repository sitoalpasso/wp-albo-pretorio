<?php
/**
 * Dati dell'atto: righe A-69..A-76, e la prima riga di ALBO-01.
 *
 * Ogni esito si verifica rileggendo dalla banca dati, non la risposta della
 * funzione. Gli invii della scheda passano da `edit_post()`, che e' la funzione
 * con cui la schermata dell'atto salva, con i campi e il gettone del riquadro.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\Durate;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\SchedaAtto;
use AlboPretorioPa\TipoAtto;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\META_NUMERO_PROPRIO;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/*
 * Il riquadro si legge con il lettore di documenti di PHP, le cui proprieta'
 * hanno i nomi che hanno: non sono variabili di questo componente.
 */
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * Scheda, lettura validata ed elenco dei dati mancanti.
 */
class DatiAttoTest extends \WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Amministratore, che governa gli elenchi.
	 *
	 * @var int
	 */
	private $amministratore = 0;

	/**
	 * Chi redige, autore degli atti di prova.
	 *
	 * @var int
	 */
	private $redattore = 0;

	/**
	 * Voci degli elenchi, per nome breve.
	 *
	 * @var array<string, int>
	 */
	private $voci = array();

	/**
	 * Tipo, permessi, dati dichiarati, due utenti e le voci di prova.
	 */
	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->azzera_ambiente_albo();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );
		$this->assertTrue( Durate::registra() );
		$this->assertTrue( DatiAtto::registra() );

		Permessi::applica();

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->redattore      = $this->utente_redattore();

		wp_set_current_user( $this->amministratore );

		$this->voci = array(
			'determina'    => $this->voce( TASSONOMIA_TIPO_ATTO, 'Determinazione' ),
			'delibera'     => $this->voce( TASSONOMIA_TIPO_ATTO, 'Deliberazione' ),
			'senza'        => $this->voce( TASSONOMIA_TIPO_ATTO, 'Avviso pubblico' ),
			'giunta'       => $this->voce( TASSONOMIA_ORGANO, 'Giunta' ),
			'responsabile' => $this->voce( TASSONOMIA_ORGANO, 'Responsabile del servizio' ),
		);

		$this->assertTrue( Durate::configura( $this->voci['determina'], 10, 'amministrazione', '' ) );
		$this->assertTrue( Durate::configura( $this->voci['delibera'], 12, 'norma', 'Estremi di prova' ) );
		$this->assertInstanceOf( \WP_Error::class, Durate::del_tipo( $this->voci['senza'] ), 'Precondizione: un tipo senza durata.' );
	}

	/**
	 * Ripulisce modulo simulato e ambiente.
	 */
	public function tear_down(): void {
		$_POST = array();
		unset( $GLOBALS['current_screen'] );
		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * Un utente con i soli permessi di chi redige.
	 *
	 * @return int
	 */
	private function utente_redattore(): int {
		$mappa = Permessi::mappa();

		$this->assertIsArray( $mappa, 'Precondizione: la corrispondenza dei permessi deve esserci.' );

		$id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$utente = new \WP_User( $id );

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			$utente->add_cap( $mappa[ $chiave ] );
		}

		$this->assertFalse( user_can( $id, $mappa['publish_posts'] ), 'Precondizione: non deve poter pubblicare.' );
		$this->assertFalse( user_can( $id, $mappa['edit_others_posts'] ), 'Precondizione: non deve modificare gli atti degli altri.' );

		return $id;
	}

	/**
	 * Una voce di un elenco, creata da chi lo governa.
	 *
	 * @param string $tassonomia Elenco.
	 * @param string $nome       Nome.
	 * @return int
	 */
	private function voce( string $tassonomia, string $nome ): int {
		$esito = wp_insert_term( $nome, $tassonomia );

		$this->assertIsArray( $esito, 'Precondizione: la voce deve essere creata.' );

		return (int) $esito['term_id'];
	}

	/**
	 * Un atto in bozza del redattore.
	 *
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function atto( string $oggetto = 'Atto di prova' ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => TIPO,
				'post_status' => 'draft',
				'post_title'  => $oggetto,
				'post_author' => $this->redattore,
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		return $id;
	}

	/**
	 * Un atto completo, scritto direttamente.
	 *
	 * @param string $tipo Nome breve del tipo.
	 * @return int
	 */
	private function atto_completo( string $tipo = 'determina' ): int {
		$id = $this->atto();

		wp_set_object_terms( $id, array( $this->voci[ $tipo ] ), TASSONOMIA_TIPO_ATTO );
		wp_set_object_terms( $id, array( $this->voci['giunta'] ), TASSONOMIA_ORGANO );
		update_post_meta( $id, META_DATA_ADOZIONE, '2026-03-10' );
		update_post_meta( $id, META_NUMERO_PROPRIO, '45/2026' );

		return $id;
	}

	/**
	 * I campi della scheda, con il gettone valido per l'atto e l'utente corrente.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $campi   Valori per nome breve: tipo, organo, data, numero.
	 * @return array<string, mixed>
	 */
	private function campi_scheda( int $atto_id, array $campi ): array {
		$nomi = array(
			'tipo'   => SchedaAtto::CAMPO_TIPO,
			'organo' => SchedaAtto::CAMPO_ORGANO,
			'data'   => SchedaAtto::CAMPO_DATA,
			'numero' => SchedaAtto::CAMPO_NUMERO,
		);

		$post = array( SchedaAtto::CAMPO_GETTONE => wp_create_nonce( SchedaAtto::azione( $atto_id ) ) );

		foreach ( $campi as $breve => $valore ) {
			$post[ $nomi[ $breve ] ] = $valore;
		}

		return $post;
	}

	/**
	 * Un invio vero della schermata dell'atto.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $post    Campi inviati oltre a quelli della schermata.
	 */
	private function invia( int $atto_id, array $post ): void {
		set_current_screen( 'post' );

		$_POST = array_merge(
			array(
				'action'               => 'editpost',
				'post_ID'              => (string) $atto_id,
				'post_type'            => TIPO,
				'post_title'           => get_the_title( $atto_id ),
				'original_post_status' => 'draft',
				'_wpnonce'             => wp_create_nonce( 'update-post_' . $atto_id ),
			),
			$post
		);

		try {
			edit_post();
		} finally {
			$_POST = array();
		}
	}

	/**
	 * I quattro dati come la lettura li restituisce.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function dati( int $atto_id ): array {
		$tipo   = DatiAtto::tipo( $atto_id );
		$organo = DatiAtto::organo( $atto_id );

		return array(
			'tipo'   => null === $tipo ? null : (int) $tipo->term_id,
			'organo' => null === $organo ? null : (int) $organo->term_id,
			'data'   => DatiAtto::data_adozione( $atto_id ),
			'numero' => DatiAtto::numero_proprio( $atto_id ),
		);
	}

	/**
	 * La fotografia dell'atto: tutto cio' che esiste oggi, letto grezzo.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function fotografia( int $atto_id ): array {
		clean_post_cache( $atto_id );
		wp_cache_flush();

		$atto = get_post( $atto_id );

		return array(
			'oggetto'     => $atto->post_title,
			'stato'       => $atto->post_status,
			'tipi'        => wp_get_object_terms( $atto_id, TASSONOMIA_TIPO_ATTO, array( 'fields' => 'ids' ) ),
			'organi'      => wp_get_object_terms( $atto_id, TASSONOMIA_ORGANO, array( 'fields' => 'ids' ) ),
			'data'        => get_post_meta( $atto_id, META_DATA_ADOZIONE, false ),
			'numero'      => get_post_meta( $atto_id, META_NUMERO_PROPRIO, false ),
			'voci_tipi'   => wp_count_terms(
				array(
					'taxonomy'   => TASSONOMIA_TIPO_ATTO,
					'hide_empty' => false,
				)
			),
			'voci_organi' => wp_count_terms(
				array(
					'taxonomy'   => TASSONOMIA_ORGANO,
					'hide_empty' => false,
				)
			),
		);
	}

	/**
	 * Il riquadro stampato per un atto, dall'utente corrente.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private function riquadro( int $atto_id ): string {
		ob_start();

		try {
			SchedaAtto::mostra( get_post( $atto_id ) );
		} finally {
			$stampato = (string) ob_get_clean();
		}

		return $stampato;
	}

	/**
	 * Le opzioni di un menu del riquadro: valore, testo e se e' selezionata.
	 *
	 * @param string $html  Riquadro.
	 * @param string $campo Nome del menu.
	 * @return array<int, array{valore: string, testo: string, selezionata: bool}>
	 */
	private function opzioni( string $html, string $campo ): array {
		$documento = new \DOMDocument();
		libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		$menu = $documento->getElementById( $campo );

		$this->assertNotNull( $menu, 'Il riquadro deve contenere il menu ' . $campo . '.' );
		$this->assertSame( 'select', $menu->nodeName );

		$opzioni = array();

		foreach ( $menu->getElementsByTagName( 'option' ) as $opzione ) {
			$opzioni[] = array(
				'valore'      => $opzione->getAttribute( 'value' ),
				'testo'       => $opzione->textContent,
				'selezionata' => $opzione->hasAttribute( 'selected' ),
			);
		}

		return $opzioni;
	}

	/**
	 * Il valore di un campo di testo del riquadro.
	 *
	 * @param string $html  Riquadro.
	 * @param string $campo Nome del campo.
	 * @return string
	 */
	private function valore_campo( string $html, string $campo ): string {
		$documento = new \DOMDocument();
		libxml_use_internal_errors( true );
		$documento->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();

		$nodo = $documento->getElementById( $campo );

		$this->assertNotNull( $nodo, 'Il riquadro deve contenere il campo ' . $campo . '.' );
		$this->assertSame( 'input', $nodo->nodeName );
		$this->assertTrue( $nodo->hasAttribute( 'value' ), 'Il campo deve dichiarare il suo valore, anche vuoto.' );

		return $nodo->getAttribute( 'value' );
	}

	/**
	 * A-69: nessuna voce preselezionata, i valori salvati mostrati, i tipi senza durata segnalati.
	 */
	public function test_a69_scheda_senza_preselezione(): void {
		wp_set_current_user( $this->redattore );

		$nuovo = $this->atto();
		$html  = $this->riquadro( $nuovo );

		foreach ( array( SchedaAtto::CAMPO_TIPO, SchedaAtto::CAMPO_ORGANO ) as $campo ) {
			$opzioni     = $this->opzioni( $html, $campo );
			$selezionate = array_values( array_filter( $opzioni, static fn( $o ) => $o['selezionata'] ) );

			$this->assertGreaterThan( 1, count( $opzioni ), 'Precondizione: il menu deve offrire delle voci.' );
			$this->assertCount( 1, $selezionate, 'Una sola opzione selezionata in ' . $campo . '.' );
			$this->assertSame( '', $selezionate[0]['valore'], 'Sull\'atto nuovo e\' selezionata la voce vuota in ' . $campo . '.' );
		}

		$this->assertSame( '', $this->valore_campo( $html, SchedaAtto::CAMPO_DATA ) );
		$this->assertSame( '', $this->valore_campo( $html, SchedaAtto::CAMPO_NUMERO ) );

		$testi = array();

		foreach ( $this->opzioni( $html, SchedaAtto::CAMPO_TIPO ) as $opzione ) {
			$testi[ $opzione['valore'] ] = $opzione['testo'];
		}

		$this->assertStringContainsString( 'senza durata', $testi[ (string) $this->voci['senza'] ] );
		$this->assertStringNotContainsString( 'senza durata', $testi[ (string) $this->voci['determina'] ] );
		$this->assertStringNotContainsString( 'senza durata', $testi[ (string) $this->voci['delibera'] ] );

		$compilato = $this->atto_completo( 'delibera' );
		$html      = $this->riquadro( $compilato );

		$scelte = array();

		foreach ( array( SchedaAtto::CAMPO_TIPO, SchedaAtto::CAMPO_ORGANO ) as $campo ) {
			$selezionate = array_values( array_filter( $this->opzioni( $html, $campo ), static fn( $o ) => $o['selezionata'] ) );

			$this->assertCount( 1, $selezionate );
			$scelte[ $campo ] = $selezionate[0]['valore'];
		}

		$this->assertSame( (string) $this->voci['delibera'], $scelte[ SchedaAtto::CAMPO_TIPO ] );
		$this->assertSame( (string) $this->voci['giunta'], $scelte[ SchedaAtto::CAMPO_ORGANO ] );
		$this->assertSame( '2026-03-10', $this->valore_campo( $html, SchedaAtto::CAMPO_DATA ) );
		$this->assertSame( '45/2026', $this->valore_campo( $html, SchedaAtto::CAMPO_NUMERO ) );

		foreach ( array( 'repertorio', 'inizio', 'fine', 'durata' ) as $assente ) {
			$this->assertDoesNotMatchRegularExpression( '/name="[^"]*' . $assente . '/', $html, 'Nessun campo per ' . $assente . '.' );
		}
	}

	/**
	 * A-70 e prima riga di ALBO-01: i quattro dati nascono, cambiano e si tolgono.
	 */
	public function test_a70_ciclo_di_vita_dei_quattro_dati(): void {
		wp_set_current_user( $this->redattore );

		$atto = $this->atto();

		$this->assertSame( 'draft', get_post_status( $atto ), 'ALBO-01: una bozza con dati mancanti si salva.' );

		$this->invia(
			$atto,
			$this->campi_scheda(
				$atto,
				array(
					'tipo'   => (string) $this->voci['determina'],
					'organo' => (string) $this->voci['giunta'],
					'data'   => '2026-03-10',
					'numero' => " 45/2026 <b>bis</b>\n",
				)
			)
		);

		$this->assertSame( array(), SchedaAtto::preleva_rifiuto( $atto ), 'Nessun rifiuto su dati validi.' );

		$foto = $this->fotografia( $atto );

		$this->assertSame( array( $this->voci['determina'] ), $foto['tipi'] );
		$this->assertSame( array( $this->voci['giunta'] ), $foto['organi'] );
		$this->assertSame( array( '2026-03-10' ), $foto['data'] );
		$this->assertSame( array( '45/2026 bis' ), $foto['numero'] );
		$this->assertSame( 'draft', $foto['stato'] );

		$prima = $this->fotografia( $atto );

		$_POST = array();
		wp_update_post(
			array(
				'ID'         => $atto,
				'post_title' => 'Atto di prova',
			)
		);

		$this->assertSame( $prima, $this->fotografia( $atto ), 'Un salvataggio senza la scheda non tocca niente.' );

		$this->invia( $atto, $this->campi_scheda( $atto, array( 'data' => '2026-03-20' ) ) );

		$foto = $this->fotografia( $atto );

		$this->assertSame( array( '2026-03-20' ), $foto['data'], 'Il campo inviato cambia.' );
		$this->assertSame( array( $this->voci['determina'] ), $foto['tipi'], 'Un campo non inviato non si tocca.' );
		$this->assertSame( array( $this->voci['giunta'] ), $foto['organi'], 'Un campo non inviato non si tocca.' );
		$this->assertSame( array( '45/2026 bis' ), $foto['numero'], 'Un campo non inviato non si tocca.' );

		$this->invia(
			$atto,
			$this->campi_scheda(
				$atto,
				array(
					'tipo'   => (string) $this->voci['delibera'],
					'organo' => (string) $this->voci['responsabile'],
					'data'   => '2026-04-01',
					'numero' => '46/2026',
				)
			)
		);

		$foto = $this->fotografia( $atto );

		$this->assertSame( array( $this->voci['delibera'] ), $foto['tipi'], 'La voce nuova sostituisce la vecchia.' );
		$this->assertSame( array( $this->voci['responsabile'] ), $foto['organi'] );
		$this->assertSame( array( '2026-04-01' ), $foto['data'] );
		$this->assertSame( array( '46/2026' ), $foto['numero'] );

		$this->invia(
			$atto,
			$this->campi_scheda(
				$atto,
				array(
					'tipo'   => '',
					'organo' => '',
					'data'   => '',
					'numero' => '',
				)
			)
		);

		$foto = $this->fotografia( $atto );

		$this->assertSame( array(), $foto['tipi'] );
		$this->assertSame( array(), $foto['organi'] );
		$this->assertSame( array(), $foto['data'] );
		$this->assertSame( array(), $foto['numero'] );
		$this->assertSame( 'draft', $foto['stato'], 'ALBO-01: la bozza svuotata resta salvata.' );
	}

	/**
	 * A-71: un valore sbagliato per volta resta com'era, gli altri si salvano.
	 */
	public function test_a71_valori_sbagliati_uno_per_volta(): void {
		wp_set_current_user( $this->redattore );

		$nuovi = array(
			'tipo'   => (string) $this->voci['delibera'],
			'organo' => (string) $this->voci['responsabile'],
			'data'   => '2026-04-01',
			'numero' => '46/2026',
		);

		$casi = array(
			array( 'tipo', '999999', 'Tipo di atto' ),
			array( 'tipo', (string) $this->voci['giunta'], 'Tipo di atto' ),
			array( 'organo', (string) $this->voci['determina'], 'Organo' ),
			array( 'data', '2026-02-31', 'Data di adozione' ),
			array( 'data', '10/03/2026', 'Data di adozione' ),
			array( 'data', '2026-03-10abc', 'Data di adozione' ),
			array( 'data', "2026-03-10\n", 'Data di adozione' ),
		);

		foreach ( $casi as list( $campo, $sbagliato, $nome ) ) {
			$atto     = $this->atto_completo();
			$prima    = $this->dati( $atto );
			$inviati  = array_merge( $nuovi, array( $campo => $sbagliato ) );
			$contesto = $campo . ' = ' . wp_json_encode( $sbagliato );

			$this->invia( $atto, $this->campi_scheda( $atto, $inviati ) );

			$dopo             = $this->dati( $atto );
			$atteso           = array(
				'tipo'   => (int) $nuovi['tipo'],
				'organo' => (int) $nuovi['organo'],
				'data'   => $nuovi['data'],
				'numero' => $nuovi['numero'],
			);
			$atteso[ $campo ] = $prima[ $campo ];

			$this->assertSame( $atteso, $dopo, 'Il dato sbagliato resta com\'era e gli altri si salvano: ' . $contesto );

			$motivi = SchedaAtto::preleva_rifiuto( $atto );

			$this->assertCount( 1, $motivi, 'Un motivo solo: ' . $contesto );
			$this->assertStringStartsWith( $nome, $motivi[0], 'Il motivo nomina il dato: ' . $contesto );
		}
	}

	/**
	 * A-72: senza gettone, con gettone falso o altrui, senza permesso, o come campi personalizzati: niente cambia.
	 */
	public function test_a72_invii_non_ammessi_non_cambiano_niente(): void {
		wp_set_current_user( $this->redattore );

		$atto  = $this->atto_completo();
		$altro = $this->atto_completo();
		$nuovi = array(
			'tipo'   => (string) $this->voci['delibera'],
			'organo' => (string) $this->voci['responsabile'],
			'data'   => '2026-04-01',
			'numero' => '46/2026',
		);

		$prima = $this->fotografia( $atto );

		$senza = $this->campi_scheda( $atto, $nuovi );
		unset( $senza[ SchedaAtto::CAMPO_GETTONE ] );
		$this->invia( $atto, $senza );
		$this->assertSame( $prima, $this->fotografia( $atto ), 'Senza gettone.' );

		$falso                              = $this->campi_scheda( $atto, $nuovi );
		$falso[ SchedaAtto::CAMPO_GETTONE ] = 'abcdef1234';
		$this->invia( $atto, $falso );
		$this->assertSame( $prima, $this->fotografia( $atto ), 'Gettone falso.' );

		$altrui = $this->campi_scheda( $altro, $nuovi );
		$this->invia( $atto, $altrui );
		$this->assertSame( $prima, $this->fotografia( $atto ), 'Gettone di un altro atto.' );

		$estraneo = $this->utente_redattore();
		wp_set_current_user( $estraneo );

		$this->assertFalse( current_user_can( 'edit_post', $atto ), 'Precondizione: non puo\' modificare l\'atto di un altro autore.' );

		$_POST = $this->campi_scheda( $atto, $nuovi );

		try {
			wp_update_post( array( 'ID' => $atto ) );
		} finally {
			$_POST = array();
		}

		$this->assertSame( $prima, $this->fotografia( $atto ), 'Utente che non puo\' modificare l\'atto.' );

		wp_set_current_user( $this->redattore );

		$this->invia(
			$atto,
			array(
				'metakeyinput' => META_DATA_ADOZIONE,
				'metavalue'    => '1999-01-01',
				'meta'         => array(
					array(
						'key'   => META_NUMERO_PROPRIO,
						'value' => '1/1999',
					),
				),
			)
		);

		$this->assertSame( $prima, $this->fotografia( $atto ), 'I due dati portati come campi personalizzati.' );

		$this->invia( $atto, $this->campi_scheda( $atto, $nuovi ) );

		$this->assertSame(
			array(
				'tipo'   => $this->voci['delibera'],
				'organo' => $this->voci['responsabile'],
				'data'   => '2026-04-01',
				'numero' => '46/2026',
			),
			$this->dati( $atto ),
			'Controllo positivo: con il gettone giusto, da chi puo\' modificare, i dati cambiano.'
		);
	}

	/**
	 * A-73: chi redige non crea voci nuove, ne' dal salvataggio dell'atto ne' da codice.
	 */
	public function test_a73_nessuna_voce_nuova_creata_di_passaggio(): void {
		$mappa = Permessi::mappa();

		wp_set_current_user( $this->redattore );

		$this->assertFalse( current_user_can( get_taxonomy( TASSONOMIA_TIPO_ATTO )->cap->manage_terms ), 'Precondizione: chi redige non governa gli elenchi.' );
		$this->assertTrue( current_user_can( get_taxonomy( TASSONOMIA_TIPO_ATTO )->cap->assign_terms ), 'Precondizione: chi redige assegna le voci.' );
		$this->assertTrue( current_user_can( $mappa['edit_posts'] ) );

		$atto  = $this->atto();
		$prima = $this->fotografia( $atto );

		$this->invia(
			$atto,
			array(
				'tax_input' => array(
					TASSONOMIA_TIPO_ATTO => 'Tipo inventato',
					TASSONOMIA_ORGANO    => 'Organo inventato',
				),
			)
		);

		$this->assertSame( $prima, $this->fotografia( $atto ), 'Nessuna voce nuova e nessuna voce assegnata.' );
		$this->assertEmpty( term_exists( 'Tipo inventato', TASSONOMIA_TIPO_ATTO ) );
		$this->assertEmpty( term_exists( 'Organo inventato', TASSONOMIA_ORGANO ) );

		foreach ( array( TASSONOMIA_TIPO_ATTO, TASSONOMIA_ORGANO ) as $tassonomia ) {
			$esito = wp_insert_term( 'Creata da chi redige', $tassonomia );

			$this->assertWPError( $esito, 'Creazione diretta rifiutata in ' . $tassonomia . '.' );
			$this->assertSame( 'albo_voce_non_creabile', $esito->get_error_code() );
		}

		wp_set_current_user( $this->amministratore );

		$this->assertIsArray( wp_insert_term( 'Creata da chi governa', TASSONOMIA_TIPO_ATTO ), 'Controllo positivo: chi governa l\'elenco crea la voce.' );
		$this->assertIsArray( wp_insert_term( 'Creata da chi governa', TASSONOMIA_ORGANO ) );
	}

	/**
	 * A-74: un dato malformato scritto di lato si legge come assente.
	 */
	public function test_a74_la_lettura_valida_il_dato_letto(): void {
		$date_sbagliate = array( '2026-13-01', '2026-02-31', '31/12/2026', "2026-03-10\n", ' 2026-03-10' );

		foreach ( $date_sbagliate as $data ) {
			$atto = $this->atto_completo();
			update_post_meta( $atto, META_DATA_ADOZIONE, $data );

			$this->assertNull( DatiAtto::data_adozione( $atto ), 'Data malformata: ' . wp_json_encode( $data ) );
			$this->assertArrayHasKey( 'albo_manca_data_adozione', DatiAtto::mancanti( $atto ) );
		}

		$atto = $this->atto_completo();
		add_post_meta( $atto, META_DATA_ADOZIONE, '2026-03-11' );

		$this->assertCount( 2, get_post_meta( $atto, META_DATA_ADOZIONE, false ), 'Precondizione: due righe.' );
		$this->assertNull( DatiAtto::data_adozione( $atto ), 'Due righe valgono come nessuna.' );
		$this->assertArrayHasKey( 'albo_manca_data_adozione', DatiAtto::mancanti( $atto ) );

		$atto = $this->atto_completo();
		wp_set_object_terms( $atto, array( $this->voci['determina'], $this->voci['delibera'] ), TASSONOMIA_TIPO_ATTO );

		$this->assertNull( DatiAtto::tipo( $atto ), 'Due tipi valgono come nessuno.' );
		$this->assertArrayHasKey( 'albo_manca_tipo', DatiAtto::mancanti( $atto ) );

		$atto = $this->atto_completo();
		wp_set_object_terms( $atto, array( $this->voci['giunta'], $this->voci['responsabile'] ), TASSONOMIA_ORGANO );

		$this->assertNull( DatiAtto::organo( $atto ), 'Due organi valgono come nessuno.' );
		$this->assertArrayHasKey( 'albo_manca_organo', DatiAtto::mancanti( $atto ) );

		$atto = $this->atto_completo();
		update_post_meta( $atto, META_DATA_ADOZIONE, '2024-02-29' );

		$this->assertSame( '2024-02-29', DatiAtto::data_adozione( $atto ), 'Controllo positivo: una data vera si legge.' );
		$this->assertSame( (int) $this->voci['determina'], (int) DatiAtto::tipo( $atto )->term_id );
		$this->assertSame( (int) $this->voci['giunta'], (int) DatiAtto::organo( $atto )->term_id );
		$this->assertSame( array(), DatiAtto::mancanti( $atto ) );
	}

	/**
	 * A-75: l'elenco dei mancanti nomina un dato per volta, e mai quelli del sistema.
	 */
	public function test_a75_elenco_dei_dati_mancanti(): void {
		$completo = $this->atto_completo();

		$this->assertSame( array(), DatiAtto::mancanti( $completo ), 'Controllo positivo: l\'atto completo non manca di niente.' );

		$senza_oggetto = $this->atto_completo();
		wp_update_post(
			array(
				'ID'         => $senza_oggetto,
				'post_title' => '   ',
			)
		);

		$senza_tipo = $this->atto_completo();
		wp_set_object_terms( $senza_tipo, array(), TASSONOMIA_TIPO_ATTO );

		$senza_organo = $this->atto_completo();
		wp_set_object_terms( $senza_organo, array(), TASSONOMIA_ORGANO );

		$senza_data = $this->atto_completo();
		delete_post_meta( $senza_data, META_DATA_ADOZIONE );

		$senza_durata = $this->atto_completo( 'senza' );

		$senza_numero = $this->atto_completo();
		delete_post_meta( $senza_numero, META_NUMERO_PROPRIO );

		$attesi = array(
			'albo_manca_oggetto'       => $senza_oggetto,
			'albo_manca_tipo'          => $senza_tipo,
			'albo_manca_organo'        => $senza_organo,
			'albo_manca_data_adozione' => $senza_data,
			'albo_manca_durata'        => $senza_durata,
		);

		foreach ( $attesi as $codice => $atto ) {
			$mancanti = DatiAtto::mancanti( $atto );

			$this->assertSame( array( $codice ), array_keys( $mancanti ), 'Un solo motivo, quello di ' . $codice . '.' );
		}

		$this->assertStringContainsString( 'Avviso pubblico', DatiAtto::mancanti( $senza_durata )['albo_manca_durata'], 'Il motivo nomina il tipo senza durata.' );
		$this->assertSame( array(), DatiAtto::mancanti( $senza_numero ), 'Il numero proprio e\' facoltativo.' );
	}

	/**
	 * A-76: metadati dichiarati, protetti, senza esposizione per programmi; niente scheda senza permesso.
	 */
	public function test_a76_dichiarazioni_a_wordpress(): void {
		$dichiarati = get_registered_meta_keys( 'post', TIPO );

		foreach ( array( META_DATA_ADOZIONE, META_NUMERO_PROPRIO ) as $chiave ) {
			$this->assertArrayHasKey( $chiave, $dichiarati, 'Dichiarato: ' . $chiave );
			$this->assertFalse( $dichiarati[ $chiave ]['show_in_rest'], 'Esposizione per programmi spenta: ' . $chiave );
			$this->assertTrue( is_protected_meta( $chiave, 'post' ), 'Protetto: ' . $chiave );
		}

		foreach ( array_keys( rest_get_server()->get_routes() ) as $rotta ) {
			$this->assertStringNotContainsString( 'adozione', $rotta );
			$this->assertStringNotContainsString( 'numero_proprio', $rotta );
			$this->assertStringNotContainsString( TIPO, $rotta );
		}

		$atto = $this->atto_completo();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( '', $this->riquadro( $atto ), 'Senza permesso il riquadro non stampa niente.' );

		wp_set_current_user( $this->redattore );

		$this->assertStringContainsString( SchedaAtto::CAMPO_TIPO, $this->riquadro( $atto ), 'Controllo positivo: chi puo\' modificare vede il riquadro.' );
	}
}
