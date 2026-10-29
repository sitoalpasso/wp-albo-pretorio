<?php
/**
 * Lo storico pubblico, secondo passo: righe A-266..A-271 (ALBO-36).
 *
 * Mettere e togliere le copie oscurate: quali atti entrano, l'ingresso con
 * il documento caricato a parte, l'oggetto proprio e la conferma, tutto o
 * niente, l'uscita con causa e motivo, la fine propria di ogni copia e le
 * schermate. Sul sito pubblico ancora niente: e' il terzo passo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\CopieStorico;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\SchermataCopie;
use AlboPretorioPa\SchermataScaduti;
use AlboPretorioPa\SchermataStorico;
use AlboPretorioPa\Storico;

use const AlboPretorioPa\SEZIONE_STORICO;
use const AlboPretorioPa\TIPO_STORICO;

/**
 * Prove dell'ingresso e dell'uscita delle copie.
 */
class CopieStoricoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Un istante in cui l'atto di prova e' scaduto da qualche giorno.
	 */
	private const DOPO_LA_FINE = '2041-10-20 10:00:00';

	/**
	 * Utente che ha soltanto il permesso dello storico.
	 *
	 * @var int
	 */
	private $custode = 0;

	/**
	 * Utente che ha soltanto il permesso di consultare gli scaduti.
	 *
	 * @var int
	 */
	private $consultatore = 0;

	/**
	 * Prepara il flusso, chi gestisce lo storico e lo storico acceso con tre anni.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->custode      = $this->utente_con( array( Permessi::CHIAVE_STORICO ) );
		$this->consultatore = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );

		set_current_screen( 'dashboard' );

		$this->imposta( Storico::ACCESO, '3' );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		$GLOBALS['current_screen'] = null;
		$_GET                      = array();
		$_POST                     = array();
		$_REQUEST                  = array();
		$_FILES                    = array();

		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Salva l'impostazione dello storico, e pretende che riesca.
	 *
	 * @param string $scelta Scelta.
	 * @param string $anni   Anni.
	 */
	private function imposta( string $scelta, string $anni ): void {
		$esito = Storico::salva( $scelta, $anni, Storico::stato_mostrato(), $this->amministratore, Passaggi::ora() );

		$this->assertTrue( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
	}

	/**
	 * Un atto pubblicato il primo ottobre 2041, con la fine il 16.
	 *
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function pubblicato( string $oggetto = 'Concessione a Mario Rossi' ): int {
		$this->fissa_orologi( '2041-10-01 09:30:00' );

		$id    = $this->atto_in_verifica( 'normativo', array( 'post_title' => $oggetto ) );
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		return $id;
	}

	/**
	 * Mette la copia di un atto come l'utente indicato, con un documento vero.
	 *
	 * @param int                  $atto    Atto.
	 * @param int                  $utente  Utente.
	 * @param array<string, mixed> $diverso Argomenti diversi: `file`, `oggetto`, `conferma`.
	 * @return int|\WP_Error
	 */
	private function metti( int $atto, int $utente, array $diverso = array() ) {
		$argomenti = $diverso + array(
			'file'     => $this->file_temporaneo( 'copia-oscurata.pdf' ),
			'oggetto'  => 'Concessione di un contributo',
			'conferma' => true,
		);

		return $this->come(
			$utente,
			static function () use ( $atto, $argomenti ) {
				return CopieStorico::metti( $atto, $argomenti['file'], $argomenti['oggetto'], $argomenti['conferma'], 'percorso_locale' );
			}
		);
	}

	/**
	 * Toglie una copia come l'utente indicato.
	 *
	 * @param int   $copia  Copia.
	 * @param mixed $causa  Causa.
	 * @param mixed $motivo Motivo.
	 * @param int   $utente Utente.
	 * @return true|\WP_Error
	 */
	private function togli( int $copia, $causa, $motivo, int $utente ) {
		return $this->come(
			$utente,
			static function () use ( $copia, $causa, $motivo ) {
				return CopieStorico::togli( $copia, $causa, $motivo );
			}
		);
	}

	/**
	 * Tutte le copie scritte, in qualunque stato.
	 *
	 * @return array<int, int>
	 */
	private function copie_scritte(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => TIPO_STORICO,
					'post_status'      => 'any',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			)
		);
	}

	/**
	 * Le voci di registro dello storico con l'azione indicata, dalla prima.
	 *
	 * @param string $azione Azione.
	 * @return array<int, array<string, mixed>>
	 */
	private function voci( string $azione ): array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE_STORICO,
				'azione'  => $azione,
				'ordine'  => 'crescente',
			)
		);

		$this->assertIsArray( $voci );

		return $voci;
	}

	/**
	 * I dettagli di una voce come coppie di nome e valore.
	 *
	 * @param array<string, mixed> $voce Voce del registro.
	 * @return array<string, mixed>
	 */
	private function dettagli( array $voce ): array {
		$coppie = array();

		foreach ( (array) $voce['dettagli'] as $nome => $valore ) {
			if ( is_array( $valore ) && isset( $valore['nome'] ) ) {
				$coppie[ (string) $valore['nome'] ] = $valore['valore'] ?? null;
			} else {
				$coppie[ (string) $nome ] = $valore;
			}
		}

		return $coppie;
	}

	/**
	 * Una schermata come la vede l'utente indicato, con i parametri indicati.
	 *
	 * @param callable             $mostra    La funzione che la mostra.
	 * @param int                  $utente    Utente.
	 * @param array<string, mixed> $parametri Parametri della richiesta.
	 * @return string
	 */
	private function schermata( callable $mostra, int $utente, array $parametri = array() ): string {
		wp_set_current_user( $utente );

		$_GET     = $parametri;
		$_REQUEST = $parametri;

		ob_start();

		try {
			$mostra();
		} finally {
			$contenuto = (string) ob_get_clean();
			$_GET      = array();
			$_REQUEST  = array();
		}

		return $contenuto;
	}

	/**
	 * A-266: entrano soltanto gli atti arrivati regolarmente alla fine.
	 *
	 * Un atto pubblicato e scaduto entra; lo stesso atto prima della fine no.
	 * Un annullato o un atto tolto dall'albo prima della fine non entrano
	 * nemmeno dopo la fine: il loro documento non torna in circolazione. Una
	 * bozza o un contenuto che non e' un atto non entrano.
	 */
	public function test_a266_quali_atti_entrano(): void {
		$regolare  = $this->pubblicato();
		$annullato = $this->pubblicato( 'Atto da annullare' );
		$tolto     = $this->pubblicato( 'Atto da togliere' );

		$this->fissa_orologi( '2041-10-05 10:00:00' );
		$this->assertIsInt( $this->come( $this->amministratore, static fn () => albo_pretorio_annulla( $annullato, 'Errore materiale.' ) ) );
		$this->assertIsInt( $this->come( $this->amministratore, static fn () => albo_pretorio_defiggi( $tolto, 'dati_salute', 'Contiene una diagnosi.' ) ) );

		$this->fissa_orologi( '2041-10-16 23:59:59' );
		$this->assertSame( 'albo_copia_in_corso', CopieStorico::ammissibile( $regolare )->get_error_code(), 'L\'ultimo istante della pubblicazione: non ancora.' );

		$this->fissa_orologi( '2041-10-17 00:00:00' );
		$this->assertTrue( CopieStorico::ammissibile( $regolare ), 'Il primo istante dopo la fine: entra.' );

		$bozza = $this->atto_completo( 'normativo', array( 'post_title' => 'Bozza di prova' ) );
		$altro = self::factory()->post->create();

		foreach ( array(
			'annullato'       => array( $annullato, 'albo_copia_non_regolare' ),
			'tolto'           => array( $tolto, 'albo_copia_non_regolare' ),
			'bozza'           => array( $bozza, 'albo_copia_non_regolare' ),
			'non e\' un atto' => array( $altro, 'albo_copia_non_atto' ),
			'inesistente'     => array( 999999, 'albo_copia_non_atto' ),
		) as $caso => $prova ) {
			$esito = CopieStorico::ammissibile( $prova[0] );
			$this->assertWPError( $esito, $caso );
			$this->assertSame( $prova[1], $esito->get_error_code(), $caso );

			$this->assertWPError( $this->metti( $prova[0], $this->custode ), $caso . ': la copia non si crea.' );
		}

		$this->assertSame( array(), $this->copie_scritte(), 'Nessuna copia scritta.' );
		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Niente nel registro.' );
	}

	/**
	 * A-267: l'ingresso, con i suoi rifiuti.
	 *
	 * Spento, senza permesso, senza conferma, senza oggetto o con un oggetto
	 * troppo lungo, senza documento: niente si scrive. Poi l'ingresso: la
	 * copia e' un contenuto distinto, con l'oggetto scritto da chi la mette,
	 * il documento caricato, i soli dati che identificano l'atto e una fine
	 * propria, e il registro dice chi, quando e che cosa. L'atto non cambia.
	 * Una seconda copia dello stesso atto si rifiuta.
	 */
	public function test_a267_ingresso(): void {
		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$foto_atto  = $this->fotografia( $atto );
		$principale = (int) DocumentiAtto::principale( $atto );

		$this->imposta( Storico::SPENTO, '3' );
		$this->assertSame( 'albo_copia_spento', $this->metti( $atto, $this->custode )->get_error_code(), 'Spento: niente.' );
		$this->imposta( Storico::ACCESO, '3' );

		foreach ( array(
			'senza permesso'    => array( $this->redattore, array(), 'albo_copia_non_permesso' ),
			'chi consulta'      => array( $this->consultatore, array(), 'albo_copia_non_permesso' ),
			'senza conferma'    => array( $this->custode, array( 'conferma' => false ), 'albo_copia_senza_conferma' ),
			'conferma a parole' => array( $this->custode, array( 'conferma' => '1' ), 'albo_copia_senza_conferma' ),
			'oggetto vuoto'     => array( $this->custode, array( 'oggetto' => '   ' ), 'albo_copia_senza_oggetto' ),
			'oggetto elenco'    => array( $this->custode, array( 'oggetto' => array( 'a' ) ), 'albo_copia_senza_oggetto' ),
			'oggetto lungo'     => array( $this->custode, array( 'oggetto' => str_repeat( 'a', CopieStorico::OGGETTO_MASSIMO + 1 ) ), 'albo_copia_oggetto_lungo' ),
			'senza documento'   => array( $this->custode, array( 'file' => array() ), 'albo_copia_senza_documento' ),
			'caricamento rotto' => array(
				$this->custode,
				array(
					'file' => array(
						'name'     => 'copia.pdf',
						'tmp_name' => '',
						'error'    => UPLOAD_ERR_PARTIAL,
					),
				),
				'albo_copia_senza_documento',
			),
		) as $caso => $prova ) {
			$esito = $this->metti( $atto, $prova[0], $prova[1] );

			$this->assertWPError( $esito, $caso );
			$this->assertSame( $prova[2], $esito->get_error_code(), $caso );
			$this->assertSame( array(), $this->copie_scritte(), $caso . ': nessuna copia.' );
		}

		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Nessun rifiuto nel registro.' );

		$copia = $this->metti( $atto, $this->custode, array( 'oggetto' => ' Concessione di un contributo <b>economico</b> ' ) );

		$this->assertIsInt( $copia, is_wp_error( $copia ) ? $copia->get_error_message() : '' );
		$this->assertSame( array( $copia ), $this->copie_scritte() );
		$this->assertSame( TIPO_STORICO, get_post_type( $copia ) );
		$this->assertSame( 'publish', get_post_status( $copia ) );
		$this->assertSame( 'Concessione di un contributo economico', get_post_field( 'post_title', $copia ), 'L\'oggetto scritto da chi mette la copia, ripulito.' );
		$this->assertSame( '', get_post_field( 'post_content', $copia ), 'Nessun testo copiato dall\'atto.' );
		$this->assertSame( (string) $atto, get_post_meta( $copia, CopieStorico::META_ATTO, true ) );
		$this->assertSame( '2041-10-20', CopieStorico::giorno_di_ingresso( $copia ), 'Il giorno dell\'ingresso.' );
		$this->assertSame( '2044-10-20', conformita_core_fine_pubblicazione( $copia ), 'Tre anni dal giorno dell\'ingresso, nel fuso del sito.' );
		$this->assertFalse( conformita_core_scaduto( $copia ) );
		$this->assertSame( array( $copia ), CopieStorico::presenti() );
		$this->assertSame( array( $copia ), CopieStorico::presenti_di( $atto ) );

		$dati = CopieStorico::dati( $copia );
		$this->assertSame( array( 'anno', 'numero', 'inizio', 'fine', 'tipo', 'organo', 'numero_proprio', 'data_adozione', 'amministrazione' ), array_keys( (array) $dati ), 'Soltanto i dati che identificano l\'atto.' );
		$this->assertSame( '2041-10-01', $dati['inizio'] );
		$this->assertSame( '2041-10-16', $dati['fine'] );
		$this->assertSame( 'Deliberazione', $dati['tipo'] );
		$this->assertStringNotContainsString( 'Rossi', (string) wp_json_encode( $dati ), 'Mai l\'oggetto originale.' );

		$documento = CopieStorico::documento( $copia );
		$this->assertIsInt( $documento );
		$this->assertNotSame( $principale, $documento, 'Il documento della copia non e\' l\'originale.' );
		$this->assertTrue( conformita_core_allegato_protetto( $documento ), 'Depositato dal meccanismo comune.' );
		$this->assertNotSame( conformita_core_impronta_allegato( $principale )['valore'], conformita_core_impronta_allegato( $documento )['valore'], 'E non ha i byte dell\'originale.' );

		$dopo   = $this->fotografia( $atto );
		$propri = static function ( array $foto ) use ( $atto ): array {
			$foto['allegati'] = array_values(
				array_filter(
					$foto['allegati'],
					static function ( array $allegato ) use ( $atto ): bool {
						return (int) $allegato['post_parent'] === $atto;
					}
				)
			);
			unset( $foto['disco'] );

			return $foto;
		};
		$this->assertSame( $propri( $foto_atto ), $propri( $dopo ), 'L\'atto non cambia: riga, dati, documenti, fine, registro.' );
		$this->assertSame( array(), array_diff( $foto_atto['disco'], $dopo['disco'] ), 'Nessun file dell\'atto toccato.' );
		$this->assertCount( 1, array_diff( $dopo['disco'], $foto_atto['disco'] ), 'Sul disco, in piu\', soltanto il documento della copia.' );
		$this->assertTrue( conformita_core_scaduto( $atto ), 'E resta scaduto.' );

		$voci = $this->voci( CopieStorico::AZIONE_INGRESSO );
		$this->assertCount( 1, $voci );
		$this->assertSame( $this->custode, (int) $voci[0]['utente'], 'Chi.' );
		$this->assertSame( $copia, (int) $voci[0]['contenuto'] );
		$this->assertSame(
			array(
				'atto'               => (string) $atto,
				'anno'               => '2041',
				'numero'             => (string) $dati['numero'],
				'fine_copia'         => '2044-10-20',
				'anni'               => '3',
				'impronta_algoritmo' => conformita_core_impronta_allegato( $documento )['algoritmo'],
				'impronta_valore'    => conformita_core_impronta_allegato( $documento )['valore'],
				'conferma_controllo' => 'si',
			),
			$this->dettagli( $voci[0] ),
			'Che cosa: l\'atto, la fine, gli anni, l\'impronta del documento e la conferma.'
		);

		$doppia = $this->metti( $atto, $this->custode );
		$this->assertWPError( $doppia );
		$this->assertSame( 'albo_copia_gia_presente', $doppia->get_error_code() );
		$this->assertSame( array( $copia ), $this->copie_scritte() );
		$this->assertCount( 1, $this->voci( CopieStorico::AZIONE_INGRESSO ) );
	}

	/**
	 * A-268: l'ingresso e' tutto o niente.
	 *
	 * Il registro che non scrive, il deposito che si rifiuta, la pubblicazione
	 * della copia che non riesce: in ogni caso nessuna copia, nessun documento
	 * e nessun file restano. Due ingressi insieme sullo stesso atto lasciano
	 * una copia sola, la piu' vecchia.
	 */
	public function test_a268_tutto_o_niente(): void {
		global $wpdb;

		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$sul_disco = $this->documenti_sul_disco();
		$allegati  = static function (): array {
			return get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
		};
		$prima     = $allegati();
		$registro  = static function ( $istruzione ) {
			return 0 === stripos( ltrim( (string) $istruzione ), 'INSERT' ) && false !== strpos( (string) $istruzione, 'conformita_core_registro' ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$pubblica  = static function ( $istruzione ) use ( $wpdb ) {
			return 0 === stripos( ltrim( (string) $istruzione ), 'UPDATE' ) && false !== strpos( (string) $istruzione, $wpdb->posts ) && false !== strpos( (string) $istruzione, "'publish'" ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};

		foreach ( array(
			'registro guasto'      => array( $registro, 'albo_copia_registro' ),
			'pubblicazione guasta' => array( $pubblica, 'albo_copia_non_scritta' ),
		) as $caso => $prova ) {
			$zitto = $wpdb->suppress_errors( true );
			add_filter( 'query', $prova[0] );

			try {
				$esito = $this->metti( $atto, $this->custode );
			} finally {
				remove_filter( 'query', $prova[0] );
				$wpdb->suppress_errors( $zitto );
			}

			$this->assertWPError( $esito, $caso );
			$this->assertSame( $prova[1], $esito->get_error_code(), $caso );
			$this->assertSame( array(), $this->copie_scritte(), $caso . ': nessuna copia rimasta.' );
			$this->assertSame( $prima, $allegati(), $caso . ': nessun documento rimasto.' );
			$this->assertSame( $sul_disco, $this->documenti_sul_disco(), $caso . ': nessun file rimasto.' );
		}

		/*
		 * Due ingressi insieme sullo stesso atto: tutti e due passano il primo
		 * controllo. Resta la copia piu' vecchia; l'altra si ritira prima di
		 * andare nel registro.
		 */
		$rivale          = 0;
		$entra           = function () use ( $atto, &$rivale ): void {
			$rivale = (int) wp_insert_post(
				array(
					'post_type'   => TIPO_STORICO,
					'post_status' => 'publish',
					'post_title'  => 'Copia entrata nello stesso momento',
				)
			);
			update_post_meta( $rivale, CopieStorico::META_ATTO, $atto );
			$this->assertTrue( conformita_core_imposta_fine_pubblicazione( $rivale, '2044-10-20' ) );
		};
		$prima_di_questa = function ( $data ) use ( $entra, &$rivale ) {
			if ( 0 === $rivale && TIPO_STORICO === $data['post_type'] && 'draft' === $data['post_status'] ) {
				$entra();
			}

			return $data;
		};

		add_filter( 'wp_insert_post_data', $prima_di_questa );

		try {
			$esito = $this->metti( $atto, $this->custode );
		} finally {
			remove_filter( 'wp_insert_post_data', $prima_di_questa );
		}

		$this->assertWPError( $esito, 'L\'altra e\' piu\' vecchia: questa si ritira.' );
		$this->assertSame( 'albo_copia_gia_presente', $esito->get_error_code() );
		$this->assertSame( array( $rivale ), $this->copie_scritte(), 'Resta soltanto l\'altra.' );
		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ) );

		wp_delete_post( $rivale, true );
		$rivale = 0;

		/*
		 * Un secondo ingresso vero, che parte mentre il primo e' a meta'
		 * (dopo il deposito del documento): gli ingressi di un atto vanno in
		 * fila, e il secondo si rifiuta senza scrivere niente.
		 */
		$secondo = null;
		$dentro  = function ( $meta, $contenuto, $chiave ) use ( $atto, &$secondo ): void {
			if ( null === $secondo && CopieStorico::META_DOCUMENTO === $chiave ) {
				$secondo = CopieStorico::metti( $atto, $this->file_temporaneo( 'altra-copia.pdf' ), 'Altra copia', true, 'percorso_locale' );
			}
		};

		add_action( 'added_post_meta', $dentro, 10, 3 );

		try {
			$questa = $this->metti( $atto, $this->custode );
		} finally {
			remove_action( 'added_post_meta', $dentro, 10 );
		}

		$this->assertWPError( $secondo, 'Il secondo ingresso non passa.' );
		$this->assertSame( 'albo_copia_occupato', $secondo->get_error_code() );
		$this->assertIsInt( $questa, 'Il primo finisce.' );
		$this->assertSame( array( $questa ), $this->copie_scritte(), 'Una copia sola.' );
		$this->assertCount( 1, $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Una voce sola.' );

		// Un ingresso e un'uscita in corso su un'altra connessione: si aspetta il turno, poi ci si rifiuta senza scrivere.
		$altra = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );

		try {
			$this->assertSame( '1', (string) $altra->get_var( $altra->prepare( 'SELECT GET_LOCK( %s, 0 )', CopieStorico::nome_blocco_atto( $atto ) ) ) );
			$this->assertSame( '1', (string) $altra->get_var( $altra->prepare( 'SELECT GET_LOCK( %s, 0 )', CopieStorico::nome_blocco_copia( $questa ) ) ) );

			$occupato = $this->togli( $questa, 'scelta_amministrazione', 'Prova.', $this->custode );
			$this->assertWPError( $occupato );
			$this->assertSame( 'albo_copia_occupato', $occupato->get_error_code() );
			$this->assertFalse( conformita_core_scaduto( $questa ), 'L\'uscita in fila non ha scritto.' );

			$occupato = $this->metti( $atto, $this->custode );
			$this->assertWPError( $occupato );
			$this->assertSame( 'albo_copia_occupato', $occupato->get_error_code() );
			$this->assertSame( array( $questa ), $this->copie_scritte(), 'L\'ingresso in fila non ha scritto.' );
		} finally {
			$altra->query( 'SELECT RELEASE_ALL_LOCKS()' );
			$altra->close();
		}

		$this->assertTrue( $this->togli( $questa, 'scelta_amministrazione', 'Prova finita.', $this->custode ) );

		// La cartella che non risulta protetta: il deposito si rifiuta, e la copia non resta.
		$this->server_che_serve_l_esca();
		$esito = $this->metti( $atto, $this->custode );

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_documento', $esito->get_error_code() );
		$this->assertSame( array( $questa ), $this->copie_scritte(), 'Nessuna copia nuova.' );
		$this->assertCount( 1, $this->voci( CopieStorico::AZIONE_INGRESSO ) );
	}

	/**
	 * A-269: l'uscita, con causa e motivo, anche a storico spento.
	 *
	 * La copia sparisce da subito per la data letta, resta salvata, e il
	 * registro dice chi l'ha tolta, quando, con quale causa e perche'. Tolta,
	 * lo stesso atto puo' rientrare con una copia nuova.
	 */
	public function test_a269_uscita(): void {
		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$copia = $this->metti( $atto, $this->custode );
		$this->assertIsInt( $copia );

		foreach ( array(
			'senza permesso'    => array( $this->redattore, 'richiesta_interessato', 'Motivo.', 'albo_copia_non_permesso' ),
			'causa sconosciuta' => array( $this->custode, 'perche_si', 'Motivo.', 'albo_copia_senza_causa' ),
			'causa mancante'    => array( $this->custode, null, 'Motivo.', 'albo_copia_senza_causa' ),
			'motivo vuoto'      => array( $this->custode, 'richiesta_interessato', ' ', 'albo_copia_senza_motivo' ),
		) as $caso => $prova ) {
			$esito = $this->togli( $copia, $prova[1], $prova[2], $prova[0] );

			$this->assertWPError( $esito, $caso );
			$this->assertSame( $prova[3], $esito->get_error_code(), $caso );
			$this->assertFalse( conformita_core_scaduto( $copia ), $caso . ': la copia resta.' );
		}

		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_USCITA ) );

		// A storico spento la copia si toglie lo stesso.
		$this->imposta( Storico::SPENTO, '3' );
		$this->assertTrue( $this->togli( $copia, 'richiesta_interessato', 'Chiesto dalla persona nominata.', $this->custode ) );
		$this->imposta( Storico::ACCESO, '3' );

		$this->assertTrue( conformita_core_scaduto( $copia ), 'Fuori da subito, per la data letta.' );
		$this->assertSame( '2041-10-19', conformita_core_fine_pubblicazione( $copia ), 'La fine riportata al giorno prima.' );
		$this->assertSame( 'publish', get_post_status( $copia ), 'Salvata, non cancellata.' );
		$this->assertSame( array(), CopieStorico::presenti() );
		$this->assertSame( array(), CopieStorico::presenti_di( $atto ) );

		$voci = $this->voci( CopieStorico::AZIONE_USCITA );
		$this->assertCount( 1, $voci );
		$this->assertSame( $this->custode, (int) $voci[0]['utente'] );
		$this->assertSame( $copia, (int) $voci[0]['contenuto'] );
		$this->assertSame( 'Chiesto dalla persona nominata.', $voci[0]['motivazione'] );
		$this->assertSame(
			array(
				'causa'           => 'richiesta_interessato',
				'atto'            => (string) $atto,
				'fine_precedente' => '2044-10-20',
				'fine_scritta'    => '2041-10-19',
			),
			$this->dettagli( $voci[0] )
		);

		$this->assertSame( 'albo_copia_gia_fuori', $this->togli( $copia, 'dati_nella_copia', 'Ancora.', $this->custode )->get_error_code(), 'Una copia gia\' fuori non si toglie due volte.' );
		$this->assertCount( 1, $this->voci( CopieStorico::AZIONE_USCITA ) );

		$nuova = $this->metti( $atto, $this->custode, array( 'oggetto' => 'Concessione di un contributo, copia rifatta' ) );
		$this->assertIsInt( $nuova, 'Tolta la copia, ne entra una nuova.' );
		$this->assertSame( array( $nuova ), CopieStorico::presenti_di( $atto ) );
		$this->assertSame( 'albo_copia_sconosciuta', $this->togli( $atto, 'dati_nella_copia', 'Motivo.', $this->custode )->get_error_code(), 'Un atto non e\' una copia.' );
	}

	/**
	 * A-270: ogni copia ha la sua fine, e lo storico spento le conserva.
	 *
	 * Gli anni valgono per le copie che entrano da quando si salvano: la fine
	 * delle copie gia' nello storico non cambia. Spento, nessuna copia entra
	 * e quelle salvate restano. Alla fine la copia esce da sola, senza il
	 * compito pianificato.
	 */
	public function test_a270_fine_propria_e_spegnimento(): void {
		$primo   = $this->pubblicato();
		$secondo = $this->pubblicato( 'Secondo atto' );

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$prima = $this->metti( $primo, $this->custode );
		$this->assertIsInt( $prima );

		$this->imposta( Storico::ACCESO, '5' );
		$this->assertSame( '2044-10-20', conformita_core_fine_pubblicazione( $prima ), 'La fine della copia gia\' entrata non cambia.' );

		$seconda = $this->metti( $secondo, $this->custode );
		$this->assertIsInt( $seconda );
		$this->assertSame( '2046-10-20', conformita_core_fine_pubblicazione( $seconda ), 'La copia nuova prende gli anni in vigore.' );

		$this->imposta( Storico::SPENTO, '5' );
		$this->assertSame( array( $seconda, $prima ), CopieStorico::presenti(), 'Spento: le copie restano salvate.' );

		$terzo = $this->pubblicato( 'Terzo atto' );
		$this->fissa_orologi( self::DOPO_LA_FINE );
		$this->assertSame( 'albo_copia_spento', $this->metti( $terzo, $this->custode )->get_error_code(), 'Spento: non entra niente.' );

		$this->fissa_orologi( '2044-10-20 23:59:59' );
		$this->assertSame( array( $seconda, $prima ), CopieStorico::presenti(), 'L\'ultimo istante della prima copia.' );

		$this->fissa_orologi( '2044-10-21 00:00:00' );
		$this->assertSame( array( $seconda ), CopieStorico::presenti(), 'Il primo istante dopo: la prima copia e\' uscita da sola.' );
		$this->assertTrue( conformita_core_scaduto( $prima ) );
	}

	/**
	 * A-271: le schermate.
	 *
	 * Il collegamento accanto all'atto scaduto, soltanto a chi ha il permesso
	 * dello storico e con lo storico acceso. Il modulo dell'ingresso, con
	 * l'oggetto da rileggere e la conferma non segnata. L'invio con il
	 * gettone giusto e con un percorso del server al posto del caricamento
	 * non deposita niente. L'elenco delle copie con il modulo per toglierle.
	 */
	public function test_a271_schermate(): void {
		$atto = $this->pubblicato();
		$this->fissa_orologi( self::DOPO_LA_FINE );

		$scaduti = function ( int $utente ): string {
			$contenuto = $this->schermata( array( SchermataScaduti::class, 'mostra' ), $utente );

			$this->assertSame( 1, preg_match( '/<tr data-atto="' . $GLOBALS['albo_prova_atto'] . '">(.*?)<\/tr>/s', $contenuto, $riga ), 'La riga dell\'atto.' );

			return $riga[1];
		};

		$GLOBALS['albo_prova_atto'] = $atto;

		try {
			$riga = $scaduti( $this->amministratore );
			$this->assertSame( 1, preg_match( '/<p class="albo-copia"><a href="([^"]+)">/', $riga, $collegamento ), 'Il collegamento a chi ha il permesso dello storico.' );
			$this->assertStringContainsString( SchermataCopie::CAMPO_ATTO . '=' . $atto, html_entity_decode( $collegamento[1] ) );
			$this->assertStringNotContainsString( 'albo-copia', $scaduti( $this->consultatore ), 'A chi consulta soltanto, niente.' );

			$this->imposta( Storico::SPENTO, '3' );
			$this->assertStringNotContainsString( 'albo-copia', $scaduti( $this->amministratore ), 'Spento, niente.' );
			$this->imposta( Storico::ACCESO, '3' );
		} finally {
			unset( $GLOBALS['albo_prova_atto'] );
		}

		$in_corso = $this->pubblicato( 'Atto ancora in corso' );
		$this->fissa_orologi( '2041-10-10 10:00:00' );
		$modulo = $this->schermata( array( SchermataStorico::class, 'mostra' ), $this->custode, array( SchermataCopie::CAMPO_ATTO => (string) $in_corso ) );
		$this->assertStringContainsString( 'non e&#039; ancora finita', $modulo, 'Per un atto in corso, il motivo al posto del modulo.' );
		$this->assertStringNotContainsString( 'enctype', $modulo );

		$this->fissa_orologi( self::DOPO_LA_FINE );
		$modulo = $this->schermata( array( SchermataStorico::class, 'mostra' ), $this->custode, array( SchermataCopie::CAMPO_ATTO => (string) $atto ) );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $modulo );
		$this->assertStringContainsString( 'name="' . SchermataCopie::CAMPO_DOCUMENTO . '"', $modulo );
		$this->assertSame( 1, preg_match( '/<textarea id="' . SchermataCopie::CAMPO_OGGETTO . '"[^>]*>Concessione a Mario Rossi<\/textarea>/', $modulo ), 'L\'oggetto proposto e\' quello dell\'atto, da rileggere.' );
		$this->assertSame( 1, preg_match( '/<input type="checkbox" id="' . SchermataCopie::CAMPO_CONFERMA . '" name="' . SchermataCopie::CAMPO_CONFERMA . '" value="1" required="required" \/>/', $modulo ), 'La conferma non e\' segnata.' );
		$this->assertStringContainsString( 'dati genetici, biometrici o relativi alla salute', $modulo );
		$this->assertStringContainsString( 'per 3 anni da oggi', $modulo );

		// L'invio del modulo.
		$invia = function ( array $campi, array $file = array() ) {
			wp_set_current_user( $this->custode );

			$_POST    = $campi;
			$_REQUEST = $campi;
			$_FILES   = $file;

			try {
				return SchermataCopie::elabora_metti();
			} finally {
				$_POST    = array();
				$_REQUEST = array();
				$_FILES   = array();
			}
		};
		wp_set_current_user( $this->custode );
		$gettone = wp_create_nonce( SchermataCopie::AZIONE_METTI . '_' . $atto );
		$campi   = array(
			SchermataCopie::CAMPO_ATTO     => (string) $atto,
			SchermataCopie::CAMPO_GETTONE  => $gettone,
			SchermataCopie::CAMPO_OGGETTO  => 'Concessione di un contributo',
			SchermataCopie::CAMPO_CONFERMA => '1',
		);
		$locale  = array( SchermataCopie::CAMPO_DOCUMENTO => $this->file_temporaneo( 'copia.pdf' ) );

		$this->assertSame( 'albo_copia_gettone', $invia( array( SchermataCopie::CAMPO_GETTONE => 'sbagliato' ) + $campi, $locale )->get_error_code() );
		$this->assertSame( 'albo_copia_gettone', $invia( array( SchermataCopie::CAMPO_ATTO => (string) ( $atto + 1 ) ) + $campi, $locale )->get_error_code(), 'Il gettone vale per un atto solo.' );
		$this->assertSame( 'albo_copia_senza_conferma', $invia( array_diff_key( $campi, array( SchermataCopie::CAMPO_CONFERMA => 1 ) ), $locale )->get_error_code() );

		$esito = $invia( $campi, $locale );
		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_documento', $esito->get_error_code(), 'Un percorso del server al posto del caricamento non si deposita.' );
		$this->assertStringContainsString( 'non proviene da un caricamento', $esito->get_error_message() );
		$this->assertSame( array(), $this->copie_scritte() );

		$this->assertStringContainsString( 'notice-error', $this->schermata( array( SchermataStorico::class, 'mostra' ), $this->custode ), 'Il rifiuto arriva sulla schermata.' );

		// L'elenco, con la copia messa dalla funzione e il modulo per toglierla.
		$copia = $this->metti( $atto, $this->custode );
		$this->assertIsInt( $copia );

		$elenco = $this->schermata( array( SchermataStorico::class, 'mostra' ), $this->custode );
		$this->assertSame( 1, preg_match( '/<tr data-copia="' . $copia . '">(.*?)<\/tr>/s', $elenco, $riga ), 'La copia nell\'elenco.' );
		$this->assertStringContainsString( 'Concessione di un contributo', $riga[1] );
		$this->assertStringContainsString( 'dal 20/10/2041 al 20/10/2044', $riga[1] );
		$this->assertStringContainsString( '<option value="">', $riga[1], 'Nessuna causa scelta in partenza.' );

		$GLOBALS['albo_prova_atto'] = $atto;

		try {
			$this->assertStringContainsString( 'nello storico pubblico fino al 20/10/2044', $scaduti( $this->amministratore ), 'Accanto all\'atto, la copia che c\'e\'.' );
		} finally {
			unset( $GLOBALS['albo_prova_atto'] );
		}

		$togli = function ( array $campi ) {
			wp_set_current_user( $this->custode );

			$_POST    = $campi;
			$_REQUEST = $campi;

			try {
				return SchermataCopie::elabora_togli();
			} finally {
				$_POST    = array();
				$_REQUEST = array();
			}
		};
		wp_set_current_user( $this->custode );
		$campi = array(
			SchermataCopie::CAMPO_COPIA   => (string) $copia,
			SchermataCopie::CAMPO_GETTONE => wp_create_nonce( SchermataCopie::AZIONE_TOGLI . '_' . $copia ),
			SchermataCopie::CAMPO_CAUSA   => 'dati_nella_copia',
			SchermataCopie::CAMPO_MOTIVO  => 'Nella seconda pagina c\'e\' ancora un nome.',
		);

		$this->assertSame( 'albo_copia_gettone', $togli( array( SchermataCopie::CAMPO_GETTONE => 'sbagliato' ) + $campi )->get_error_code() );
		$this->assertFalse( conformita_core_scaduto( $copia ) );
		$this->assertTrue( $togli( $campi ) );
		$this->assertTrue( conformita_core_scaduto( $copia ) );
		$this->assertSame( 'Nella seconda pagina c\'e\' ancora un nome.', $this->voci( CopieStorico::AZIONE_USCITA )[0]['motivazione'] );
		$this->assertStringContainsString( 'stata tolta dallo storico', $this->schermata( array( SchermataStorico::class, 'mostra' ), $this->custode ) );
	}

	/**
	 * A-268: due uscite insieme sulla stessa copia lasciano una voce sola.
	 *
	 * La seconda parte mentre la prima scrive la fine: le uscite di una
	 * copia vanno in fila, e la seconda si rifiuta.
	 */
	public function test_a268_due_uscite_insieme(): void {
		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$copia = $this->metti( $atto, $this->custode );
		$this->assertIsInt( $copia );

		$seconda = null;
		$dentro  = function ( $meta, $contenuto, $chiave ) use ( $copia, &$seconda ): void {
			if ( null === $seconda && $copia === (int) $contenuto && conformita_core_chiave_fine_pubblicazione() === $chiave ) {
				$seconda = CopieStorico::togli( $copia, 'dati_nella_copia', 'Seconda uscita.' );
			}
		};

		add_action( 'updated_post_meta', $dentro, 10, 3 );

		try {
			$prima = $this->togli( $copia, 'richiesta_interessato', 'Prima uscita.', $this->custode );
		} finally {
			remove_action( 'updated_post_meta', $dentro, 10 );
		}

		$this->assertTrue( $prima );
		$this->assertWPError( $seconda, 'La seconda uscita non passa.' );
		$this->assertSame( 'albo_copia_occupato', $seconda->get_error_code() );

		$voci = $this->voci( CopieStorico::AZIONE_USCITA );
		$this->assertCount( 1, $voci, 'Una voce sola.' );
		$this->assertSame( '2044-10-20', $this->dettagli( $voci[0] )['fine_precedente'] );
	}

	/**
	 * A-268: una copia non riuscita che non si cancella non si dice ritirata.
	 *
	 * Il registro non scrive e la banca dati non cancella: la rilettura vede
	 * che la copia c'e' ancora, la copia esce con la fine al giorno prima, e
	 * il rifiuto lo dice.
	 */
	public function test_a268_ritiro_che_non_riesce(): void {
		global $wpdb;

		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$guasto = static function ( $istruzione ) use ( $wpdb ) {
			$testo = ltrim( (string) $istruzione );

			if ( 0 === stripos( $testo, 'INSERT' ) && false !== strpos( $testo, 'conformita_core_registro' ) ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return 0 === stripos( $testo, 'DELETE' ) && false !== strpos( $testo, $wpdb->posts ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$zitto  = $wpdb->suppress_errors( true );

		add_filter( 'query', $guasto );

		try {
			$esito = $this->metti( $atto, $this->custode );
		} finally {
			remove_filter( 'query', $guasto );
			$wpdb->suppress_errors( $zitto );
		}

		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_non_ritirata', $esito->get_error_code(), 'Non si dice ritirata.' );
		$this->assertStringContainsString( 'fuori dallo storico', $esito->get_error_message() );

		$rimaste = $this->copie_scritte();
		$this->assertCount( 1, $rimaste, 'La copia c\'e\' ancora.' );
		$this->assertTrue( conformita_core_scaduto( $rimaste[0] ), 'Ma e\' fuori dallo storico.' );
		$this->assertSame( '2041-10-19', conformita_core_fine_pubblicazione( $rimaste[0] ) );
		$this->assertSame( array(), CopieStorico::presenti() );
	}

	/**
	 * A-270: l'elenco che non si legge non dice "nessuna copia".
	 */
	public function test_a270_elenco_illeggibile(): void {
		global $wpdb;

		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );
		$this->assertIsInt( $this->metti( $atto, $this->custode ) );

		$guasto = static function ( $istruzione ) use ( $wpdb ) {
			return 0 === strpos( ltrim( (string) $istruzione ), "SELECT ID FROM {$wpdb->posts} WHERE post_type = '" . TIPO_STORICO . "'" ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$zitto  = $wpdb->suppress_errors( true );

		add_filter( 'query', $guasto );

		try {
			$this->assertNull( CopieStorico::presenti() );
			$contenuto = $this->schermata( array( SchermataStorico::class, 'mostra' ), $this->custode );
		} finally {
			remove_filter( 'query', $guasto );
			$wpdb->suppress_errors( $zitto );
		}

		$this->assertStringContainsString( 'non si leggono in questo momento', $contenuto );
		$this->assertStringNotContainsString( 'Nessuna copia nello storico', $contenuto );
	}

	/**
	 * A-270: una copia entrata il 29 febbraio finisce il 28 negli anni che non lo hanno.
	 *
	 * La lettura prudente: nessun giorno di diffusione in piu'.
	 */
	public function test_a270_ventinove_febbraio(): void {
		$giorno = new \DateTimeImmutable( '2044-02-29 10:00:00', wp_timezone() );

		$this->assertSame( '2047-02-28', CopieStorico::fine( $giorno, 3 ) );
		$this->assertSame( '2048-02-29', CopieStorico::fine( $giorno, 4 ) );
		$this->assertSame( '2044-10-20', CopieStorico::fine( new \DateTimeImmutable( '2041-10-20 10:00:00', wp_timezone() ), 3 ) );

		$atto = $this->pubblicato();

		$this->fissa_orologi( '2044-02-29 10:00:00' );

		$copia = $this->metti( $atto, $this->custode );
		$this->assertIsInt( $copia );
		$this->assertSame( '2047-02-28', conformita_core_fine_pubblicazione( $copia ) );
	}

	/**
	 * A-268: i dati dell'atto che non si rileggono non diventano un atto 0/0.
	 *
	 * Le letture del numero nei due controlli dell'atto rispondono; quella
	 * che fissa i dati della copia, no. La copia non entra e
	 * il registro non ha voci.
	 */
	public function test_a268_numero_che_non_si_rilegge(): void {
		global $wpdb;

		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$letture = 0;
		$guasto  = static function ( $istruzione ) use ( &$letture ) {
			if ( 0 === strpos( ltrim( (string) $istruzione ), 'SELECT anno, numero FROM' ) ) {
				++$letture;

				if ( $letture > 2 ) {
					return 'SELECT * FROM tabella_che_non_esiste';
				}
			}

			return $istruzione;
		};
		$zitto   = $wpdb->suppress_errors( true );

		add_filter( 'query', $guasto );

		try {
			$esito = $this->metti( $atto, $this->custode );
		} finally {
			remove_filter( 'query', $guasto );
			$wpdb->suppress_errors( $zitto );
		}

		$this->assertGreaterThan( 2, $letture, 'Il numero si e\' letto nei due controlli e poi per i dati.' );
		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_lettura', $esito->get_error_code() );
		$this->assertSame( array(), $this->copie_scritte(), 'Nessuna copia.' );
		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Nessuna voce.' );
	}

	/**
	 * A-270: lo storico spento mentre l'ingresso aspetta il blocco.
	 *
	 * Un'altra richiesta spegne lo storico quando l'ingresso ha gia' passato i
	 * primi controlli e chiede il blocco dell'atto: l'ingresso rilegge
	 * l'impostazione e si ferma. Lo stesso se l'impostazione, a quel punto,
	 * non si legge.
	 */
	public function test_a270_spento_mentre_aspetta(): void {
		global $wpdb;

		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$blocco = CopieStorico::nome_blocco_atto( $atto );
		$spento = null;
		$spegni = function ( $istruzione ) use ( $blocco, &$spento ) {
			if ( null === $spento && false !== strpos( (string) $istruzione, 'GET_LOCK' ) && false !== strpos( (string) $istruzione, $blocco ) ) {
				$spento = Storico::salva( Storico::SPENTO, '3', Storico::stato_mostrato(), $this->amministratore, Passaggi::ora() );
			}

			return $istruzione;
		};

		add_filter( 'query', $spegni );

		try {
			$esito = $this->metti( $atto, $this->custode );
		} finally {
			remove_filter( 'query', $spegni );
		}

		$this->assertTrue( $spento, 'Lo storico si e\' spento durante l\'attesa.' );
		$this->assertWPError( $esito );
		$this->assertSame( 'albo_copia_spento', $esito->get_error_code() );
		$this->assertSame( array(), $this->copie_scritte(), 'Spento: nessuna copia.' );
		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Spento: nessuna voce.' );

		$this->imposta( Storico::ACCESO, '3' );

		$preso  = false;
		$guasto = static function ( $istruzione ) use ( $wpdb, $blocco, &$preso ) {
			$testo = (string) $istruzione;

			if ( false !== strpos( $testo, 'GET_LOCK' ) && false !== strpos( $testo, $blocco ) ) {
				$preso = true;
			}

			return $preso && 0 === strpos( ltrim( $testo ), "SELECT option_value FROM {$wpdb->options}" ) && false !== strpos( $testo, Storico::OPZIONE ) ? 'SELECT * FROM tabella_che_non_esiste' : $istruzione;
		};
		$zitto  = $wpdb->suppress_errors( true );

		add_filter( 'query', $guasto );

		try {
			$illeggibile = $this->metti( $atto, $this->custode );
		} finally {
			remove_filter( 'query', $guasto );
			$wpdb->suppress_errors( $zitto );
		}

		$this->assertWPError( $illeggibile );
		$this->assertSame( 'albo_copia_spento', $illeggibile->get_error_code() );
		$this->assertSame( array(), $this->copie_scritte(), 'Illeggibile: nessuna copia.' );
		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Illeggibile: nessuna voce.' );
	}

	/**
	 * Un cambio dell'atto fatto da un'altra richiesta durante l'ingresso.
	 *
	 * `prima`: quando l'ingresso chiede il blocco dell'atto, dopo i primi
	 * controlli. `durante`: quando la copia si rende pubblica, prima del
	 * registro.
	 *
	 * @param int      $atto     Atto.
	 * @param string   $momento  `prima` oppure `durante`.
	 * @param callable $cambio   Il cambio.
	 * @return int|\WP_Error
	 */
	private function metti_mentre( int $atto, string $momento, callable $cambio ) {
		$fatto  = false;
		$blocco = CopieStorico::nome_blocco_atto( $atto );
		$query  = static function ( $istruzione ) use ( $blocco, $cambio, &$fatto ) {
			if ( ! $fatto && false !== strpos( (string) $istruzione, 'GET_LOCK' ) && false !== strpos( (string) $istruzione, $blocco ) ) {
				$fatto = true;
				$cambio();
			}

			return $istruzione;
		};
		$stato  = static function ( $nuovo, $vecchio, $contenuto ) use ( $cambio, &$fatto ): void {
			if ( ! $fatto && 'publish' === $nuovo && $contenuto instanceof \WP_Post && TIPO_STORICO === $contenuto->post_type ) {
				$fatto = true;
				$cambio();
			}
		};

		if ( 'prima' === $momento ) {
			add_filter( 'query', $query );
		} else {
			add_action( 'transition_post_status', $stato, 10, 3 );
		}

		try {
			$esito = $this->metti( $atto, $this->custode );
		} finally {
			remove_filter( 'query', $query );
			remove_action( 'transition_post_status', $stato, 10 );
		}

		$this->assertTrue( $fatto, 'Il cambio e\' arrivato durante l\'ingresso.' );

		return $esito;
	}

	/**
	 * L'atto annullato da un'altra richiesta durante l'ingresso.
	 *
	 * @param int $atto Atto.
	 * @return callable
	 */
	private function annullamento( int $atto ): callable {
		return function () use ( $atto ): void {
			$esito = $this->come(
				$this->pubblicatore,
				static function () use ( $atto ) {
					return albo_pretorio_annulla( $atto, 'Errore materiale nell\'oggetto.' );
				}
			);

			$this->assertIsInt( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		};
	}

	/**
	 * La proroga disposta da un'altra richiesta durante l'ingresso: l'atto torna consultabile.
	 *
	 * @return callable
	 */
	private function proroga_dell_albo(): callable {
		return function (): void {
			$esito = $this->come(
				$this->pubblicatore,
				static function () {
					return albo_pretorio_proroga(
						array(
							'giorni'       => array( '2041-10-16' ),
							'attestazione' => 'Attestazione',
						)
					);
				}
			);

			$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		};
	}

	/**
	 * Dopo un cambio dell'atto durante l'ingresso: nessuna copia, nessuna voce.
	 *
	 * @param mixed  $esito  Esito dell'ingresso.
	 * @param string $codice Codice atteso.
	 */
	private function nessun_ingresso( $esito, string $codice ): void {
		$this->assertWPError( $esito );
		$this->assertSame( $codice, $esito->get_error_code(), $esito->get_error_message() );
		$this->assertSame( array(), CopieStorico::presenti(), 'Nessuna copia nello storico.' );
		$this->assertSame( array(), $this->voci( CopieStorico::AZIONE_INGRESSO ), 'Nessuna voce.' );
	}

	/**
	 * A-268: l'atto annullato mentre la copia entra, prima e durante.
	 */
	public function test_a268_annullato_durante_ingresso(): void {
		$primo   = $this->pubblicato();
		$secondo = $this->pubblicato( 'Concessione a Anna Bianchi' );

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$this->nessun_ingresso( $this->metti_mentre( $primo, 'prima', $this->annullamento( $primo ) ), 'albo_copia_non_regolare' );
		$this->nessun_ingresso( $this->metti_mentre( $secondo, 'durante', $this->annullamento( $secondo ) ), 'albo_copia_non_regolare' );
	}

	/**
	 * A-268: l'atto prorogato quando l'ingresso chiede il blocco.
	 */
	public function test_a268_prorogato_prima_della_copia(): void {
		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$this->nessun_ingresso( $this->metti_mentre( $atto, 'prima', $this->proroga_dell_albo() ), 'albo_copia_in_corso' );
		$this->assertFalse( conformita_core_scaduto( $atto ), 'Precondizione: l\'atto e\' di nuovo consultabile.' );
	}

	/**
	 * A-268: l'atto prorogato mentre la copia si rende pubblica.
	 */
	public function test_a268_prorogato_durante_ingresso(): void {
		$atto = $this->pubblicato();

		$this->fissa_orologi( self::DOPO_LA_FINE );

		$this->nessun_ingresso( $this->metti_mentre( $atto, 'durante', $this->proroga_dell_albo() ), 'albo_copia_in_corso' );
		$this->assertFalse( conformita_core_scaduto( $atto ), 'Precondizione: l\'atto e\' di nuovo consultabile.' );
	}
}
