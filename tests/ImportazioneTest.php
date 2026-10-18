<?php
/**
 * L'importazione a secco dal backup del vecchio albo: righe A-186..A-194 (ALBO-35).
 *
 * I backup di prova sono scritti come li scrive il plugin di provenienza (vedi
 * `backup-di-prova.php`). L'orologio e' fissato a mezzanotte e mezza del 16
 * ottobre 2041 nel fuso del sito, quando in tempo universale e' ancora il 15:
 * un atto con fine il 16 e' in pubblicazione, uno con fine il 15 e' scaduto, e
 * una lettura che prendesse il giorno sul tempo universale sbaglierebbe tutti
 * e due.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\AnalisiImportazione;
use AlboPretorioPa\DatiProcedimento;
use AlboPretorioPa\Festivi;
use AlboPretorioPa\LettoreBackup;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchermataImportazione;

use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/**
 * Il backup si legge, si confronta e si racconta, e niente si scrive.
 */
class ImportazioneTest extends \WP_UnitTestCase {

	use FlussoDiProva;
	use BackupDiProva;

	/**
	 * L'istante dell'analisi: le 00:30 del 16 ottobre, ora del sito.
	 */
	private const ADESSO = '2041-10-16 00:30:00';

	/**
	 * La partenza dichiarata per il 2041 nel nuovo albo.
	 */
	private const PARTENZA = 40;

	/**
	 * Le cartelle create dalla prova, da togliere.
	 *
	 * @var array<int, string>
	 */
	private array $cartelle = array();

	/**
	 * Prepara il flusso, la partenza del 2041 e l'orologio.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();

		$this->assertTrue( Repertorio::dichiara( self::PARTENZA, $this->amministratore, Passaggi::ora() ), 'Precondizione: la partenza del 2041 si corregge.' );

		$this->fissa_orologi( self::ADESSO );
		set_current_screen( 'dashboard' );
	}

	/**
	 * Ripristina lo stato.
	 */
	public function tear_down(): void {
		$this->togli_temporanei();

		foreach ( array_reverse( $this->cartelle ) as $cartella ) {
			foreach ( (array) glob( $cartella . '/*' ) as $file ) {
				if ( is_link( (string) $file ) || is_file( (string) $file ) ) {
					unlink( (string) $file );
				}
			}

			if ( is_dir( $cartella ) ) {
				rmdir( $cartella );
			}
		}

		$GLOBALS['current_screen'] = null;
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Legge un backup e ne fa il rapporto, con lo stato attuale del nuovo albo.
	 *
	 * @param string $percorso Backup.
	 * @return array<string, mixed>
	 */
	private function rapporto_di( string $percorso ): array {
		$letto = LettoreBackup::leggi( $percorso );

		$this->assertIsArray( $letto, is_wp_error( $letto ) ? $letto->get_error_message() : '' );

		return AnalisiImportazione::rapporto( $letto, AnalisiImportazione::contesto( AnalisiImportazione::anni( $letto ), Passaggi::ora() ) );
	}

	/**
	 * Un atto del rapporto, per numero interno.
	 *
	 * @param array<string, mixed> $rapporto Rapporto.
	 * @param string               $id       Numero interno.
	 * @return array<string, mixed>
	 */
	private function atto( array $rapporto, string $id ): array {
		foreach ( $rapporto['atti'] as $atto ) {
			if ( $id === $atto['id'] ) {
				return $atto;
			}
		}

		$this->fail( 'Atto interno ' . $id . ' assente dal rapporto.' );
	}

	/**
	 * I codici delle segnalazioni, con il loro livello.
	 *
	 * @param array<int, array{livello: string, codice: string, testo: string}> $segnalazioni Segnalazioni.
	 * @return array<string, string>
	 */
	private function codici( array $segnalazioni ): array {
		$codici = array();

		foreach ( $segnalazioni as $segnalazione ) {
			$codici[ $segnalazione['codice'] ] = $segnalazione['livello'];
		}

		return $codici;
	}

	/**
	 * Un documento con la sua impronta registrata.
	 *
	 * @param int    $id        Numero interno dell'allegato.
	 * @param int    $atto      Numero interno dell'atto.
	 * @param string $nome      Nome nell'archivio.
	 * @param string $contenuto Contenuto.
	 * @param string $natura    D o A.
	 * @return array<string, mixed>
	 */
	private function documento( int $id, int $atto, string $nome, string $contenuto, string $natura = 'D' ): array {
		return $this->allegato_vecchio(
			array(
				'IdAllegato' => $id,
				'IdAtto'     => $atto,
				'Allegato'   => '/var/www/vecchio/wp-content/uploads/AllegatiAttiAlboPretorio/' . $nome,
				'Impronta'   => hash( 'sha256', $contenuto ),
				'Natura'     => $natura,
			)
		);
	}

	/**
	 * A-186. Il formato del backup si legge com'e', in tutte le sue varianti.
	 */
	public function test_a186_lettura_del_formato(): void {
		$difficile = "L'avviso \\ con \"virgolette\"; e (parentesi), ');\r\nseconda riga\x1a fine";

		$schema = $this->schema_vecchio();
		// Una tabella con le colonne in un ordine diverso: il riferimento in fondo, dopo il richiedente.
		$riferimento = $schema['albopretorio_atti']['Riferimento'];
		unset( $schema['albopretorio_atti']['Riferimento'] );
		$schema['albopretorio_atti']['Riferimento'] = $riferimento;

		$sql = $this->sql_vecchio(
			$this->tabelle_vecchie(
				array(
					$this->atto_vecchio(
						array(
							'Oggetto'     => $difficile,
							'Riferimento' => 'Determina 45/2041',
							'Richiedente' => 'Ufficio di prova',
							'RespProc'    => null,
						)
					),
				)
			),
			array(
				'opt_AP_NumeroProgressivo' => '2',
				'opt_AP_AnnoProgressivo'   => '2041',
				'opt_AP_FolderUpload'      => 'AllegatiAttiAlboPretorio',
				'opt_altro_plugin'         => 'non serve',
			),
			'wp_',
			$schema
		);

		// Un'altra tabella del sito, come in una copia intera della banca dati, e un contatore riscritto dopo.
		$sql = "-- MySQL dump\n/*!40101 SET NAMES utf8mb4 */;\nCREATE TABLE `wp_posts` (\n  `ID` bigint(20) NOT NULL,\n  `post_title` text\n) ;\nINSERT INTO `wp_posts` VALUES (1,'Pagina riservata;');\n"
			. "INSERT INTO `wp_options` VALUES (1,'blogname','Sito','yes');\n"
			. $sql;

		$percorso = $this->temporaneo( '.sql' );
		file_put_contents( $percorso, $sql );

		$letto = LettoreBackup::leggi( $percorso );

		$this->assertIsArray( $letto, is_wp_error( $letto ) ? $letto->get_error_message() : '' );
		$this->assertSame( 'wp_', $letto['prefisso'] );
		$this->assertFalse( $letto['zip'] );
		$this->assertSame( array(), $letto['file'] );
		$this->assertSame( array( 'wp_posts' ), $letto['ignorate'], 'La tabella d\'altri si nomina e basta.' );

		$atto = $letto['tabelle']['albopretorio_atti'][0];

		$this->assertSame( $difficile, $atto['Oggetto'], 'Il testo con apici, barre, a capo e punto e virgola torna identico.' );
		$this->assertSame( 'Determina 45/2041', $atto['Riferimento'], 'L\'ordine delle colonne viene dalla creazione della tabella.' );
		$this->assertSame( 'Ufficio di prova', $atto['Richiedente'] );
		$this->assertNull( $atto['RespProc'], 'Un intero senza valore e senza predefinito e\' scritto NULL e letto nullo.' );
		$this->assertSame(
			array(
				'opt_AP_NumeroProgressivo' => '2',
				'opt_AP_AnnoProgressivo'   => '2041',
				'opt_AP_FolderUpload'      => 'AllegatiAttiAlboPretorio',
			),
			$letto['opzioni'],
			'Delle impostazioni restano solo quelle del plugin di provenienza.'
		);

		// Le righe come le scrive mysqldump: elenco delle colonne, piu' righe per istruzione, prefisso diverso.
		$dump  = "CREATE TABLE `x_albopretorio_atti` (`IdAtto` int(11), `Numero` int(4), `Anno` int(4), `Oggetto` text, `DataInizio` date, `DataFine` date, `IdCategoria` int(11), PRIMARY KEY (`IdAtto`), KEY `Anno` (`Anno`));\n";
		$dump .= "INSERT INTO `x_albopretorio_atti` (`IdAtto`,`Numero`,`Anno`,`Oggetto`,`DataInizio`,`DataFine`,`IdCategoria`) VALUES (7,1,2041,'Primo','2041-10-01','2041-10-16',1),(8,2,2041,'Secondo con \\'apice\\' e ''doppio''','2041-10-02','2041-10-17',1);\n";
		$dump .= "INSERT INTO `x_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES (5,'opt_AP_NumeroProgressivo','3','yes');\n";
		$dump .= "UPDATE `x_options` SET `option_value` = '9' WHERE `option_name` ='opt_AP_NumeroProgressivo'";

		// A pezzi di lunghezza fissa, come li consegna la lettura a blocchi del flusso.
		$letto = LettoreBackup::leggi_sql( str_split( $dump, 13 ) );

		$this->assertIsArray( $letto, is_wp_error( $letto ) ? $letto->get_error_message() : '' );
		$this->assertSame( 'x_', $letto['prefisso'] );
		$this->assertCount( 2, $letto['tabelle']['albopretorio_atti'] );
		$this->assertSame( "Secondo con 'apice' e 'doppio'", $letto['tabelle']['albopretorio_atti'][1]['Oggetto'] );
		$this->assertSame( array( 'IdAtto', 'Numero', 'Anno', 'Oggetto', 'DataInizio', 'DataFine', 'IdCategoria' ), $letto['colonne']['albopretorio_atti'], 'Indici e chiavi non sono colonne.' );
		$this->assertSame( '9', $letto['opzioni']['opt_AP_NumeroProgressivo'], 'L\'aggiornamento del contatore in fondo prevale.' );

		// Commenti in ogni posizione fuori dai testi, letti a pezzi di ogni misura: un commento non si mangia l'istruzione dopo.
		$commenti  = "CREATE TABLE `c_albopretorio_atti` (`IdAtto` int(11), -- chiave\n `Oggetto` text # testo\n);\n";
		$commenti .= "INSERT INTO `c_albopretorio_atti` VALUES (1,'uno'); -- dopo il punto e virgola\nINSERT INTO `c_albopretorio_atti` VALUES (2,'due -- non e\\' un commento # neppure /* questo */');\n";
		$commenti .= "INSERT INTO `c_albopretorio_atti` VALUES -- prima della riga\n(3,'tre'), # fra due righe\n(4,/* dentro */'quattro');\n";
		$commenti .= "INSERT INTO `c_albopretorio_atti` VALUES (5,'cinque')/* in fondo */;#ultimo\n";
		$commenti .= "INSERT INTO `c_albopretorio_atti` VALUES (6,'sei');--";

		foreach ( array( 1, 2, 3, 7, 4096 ) as $passo ) {
			$letto = LettoreBackup::leggi_sql( str_split( $commenti, $passo ) );

			$this->assertIsArray( $letto, is_wp_error( $letto ) ? $letto->get_error_message() : '' );
			$this->assertSame( array( 'IdAtto', 'Oggetto' ), $letto['colonne']['albopretorio_atti'], 'Pezzi di ' . $passo );
			$this->assertSame( array( 'uno', "due -- non e' un commento # neppure /* questo */", 'tre', 'quattro', 'cinque', 'sei' ), wp_list_pluck( $letto['tabelle']['albopretorio_atti'], 'Oggetto' ), 'Pezzi di ' . $passo );
		}

		// Le impostazioni sono quelle della tabella delle opzioni con il prefisso dell'albo, non di un altro sito della stessa banca dati.
		$due  = "CREATE TABLE `wp_albopretorio_atti` (`IdAtto` int(11), `Numero` int(4));\nINSERT INTO `wp_albopretorio_atti` VALUES (1,1);\n";
		$due .= "INSERT INTO `wp_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES (1,'opt_AP_NumeroProgressivo','7','yes');\n";
		$due .= "INSERT INTO `wp_2_options` (`option_id`,`option_name`,`option_value`,`autoload`) VALUES (1,'opt_AP_NumeroProgressivo','90','yes'),(2,'opt_AP_AnnoProgressivo','2039','yes');\n";
		$due .= "UPDATE `wp_2_options` SET `option_value` = '91' WHERE `option_name` ='opt_AP_NumeroProgressivo';\n";

		$letto = LettoreBackup::leggi_sql( array( $due ) );

		$this->assertIsArray( $letto );
		$this->assertSame( array( 'opt_AP_NumeroProgressivo' => '7' ), $letto['opzioni'], 'Le impostazioni di un altro sito non entrano.' );

		// Un testo lunghissimo su una riga sola, letto dal flusso a blocchi: torna intero.
		$lungo    = str_repeat( "abc'\\;", 400000 );
		$percorso = $this->temporaneo( '.sql' );
		file_put_contents( $percorso, "CREATE TABLE `wp_albopretorio_atti` (`IdAtto` int(11), `Oggetto` longtext);\nINSERT INTO `wp_albopretorio_atti` VALUES (1," . $this->valore_sql( $lungo, 'longtext' ) . ');' );
		$letto = LettoreBackup::leggi( $percorso );

		$this->assertIsArray( $letto, is_wp_error( $letto ) ? $letto->get_error_message() : '' );
		$this->assertSame( $lungo, $letto['tabelle']['albopretorio_atti'][0]['Oggetto'], 'Una riga piu\' lunga di un blocco si ricompone.' );

		// Una versione precedente: senza richiedente, ufficio, impronta, natura e note.
		$vecchio = $this->schema_vecchio();
		unset( $vecchio['albopretorio_atti']['Richiedente'], $vecchio['albopretorio_atti']['IdUnitaOrganizzativa'] );
		unset( $vecchio['albopretorio_allegati']['Impronta'], $vecchio['albopretorio_allegati']['Natura'], $vecchio['albopretorio_allegati']['Note'] );

		$zip = $this->zip_vecchio(
			$this->sql_vecchio( $this->tabelle_vecchie( array( $this->atto_vecchio( array() ) ), array( $this->allegato_vecchio( array() ) ) ), array(), 'wp_', $vecchio ),
			array( 'documento.pdf' => 'contenuto' )
		);

		$rapporto = $this->rapporto_di( $zip );

		$this->assertSame( array( 'Richiedente', 'IdUnitaOrganizzativa' ), array_values( array_intersect( array( 'Richiedente', 'IdUnitaOrganizzativa' ), $rapporto['campi']['assenti']['wp_albopretorio_atti'] ) ) );
		$this->assertSame( array( 'Impronta', 'Natura', 'Note' ), $rapporto['campi']['assenti']['wp_albopretorio_allegati'] );
		$this->assertCount( 1, $rapporto['atti'] );
		$this->assertSame( array(), $rapporto['orfani'], 'Il file si trova anche senza le colonne nuove.' );
	}

	/**
	 * A-187. Un pacchetto che non e' un backup leggibile si rifiuta, con il motivo.
	 */
	public function test_a187_pacchetti_non_validi(): void {
		$buono = $this->sql_vecchio( $this->tabelle_vecchie( array( $this->atto_vecchio( array() ) ) ) );

		$casi = array();

		$casi['file inesistente'] = sys_get_temp_dir() . '/non-esiste-' . wp_generate_password( 8, false ) . '.zip';

		$testo = $this->temporaneo( '.sql' );
		file_put_contents( $testo, "Lettera di accompagnamento, non un backup.\n" );
		$casi['testo qualunque'] = $testo;

		$senza = $this->temporaneo( '.zip' );
		$zip   = new \ZipArchive();
		$zip->open( $senza, \ZipArchive::CREATE );
		$zip->addFromString( 'documento.pdf', 'x' );
		$zip->addFromString( 'cartella/AlboPretorio.sql', $buono );
		$zip->close();
		$casi['archivio senza sql alla radice'] = $senza;

		$doppio = $this->temporaneo( '.zip' );
		$zip    = new \ZipArchive();
		$zip->open( $doppio, \ZipArchive::CREATE );
		$zip->addFromString( 'uno.sql', $buono );
		$zip->addFromString( 'due.sql', $buono );
		$zip->close();
		$casi['archivio con due sql'] = $doppio;

		// Due voci con lo stesso nome: si scrivono con nomi diversi della stessa lunghezza e poi si rinomina nei byte.
		$omonimi = $this->temporaneo( '.zip' );
		$zip     = new \ZipArchive();
		$zip->open( $omonimi, \ZipArchive::CREATE );
		$zip->addFromString( 'AlboPretorio.sql', $buono );
		$zip->addFromString( 'doc-a.pdf', 'primo' );
		$zip->addFromString( 'doc-b.pdf', 'secondo' );
		$zip->close();
		file_put_contents( $omonimi, str_replace( 'doc-b.pdf', 'doc-a.pdf', (string) file_get_contents( $omonimi ) ) );
		$casi['archivio con due file omonimi'] = $omonimi;

		$rotto = $this->temporaneo( '.zip' );
		file_put_contents( $rotto, "PK\x03\x04" . str_repeat( 'x', 64 ) );
		$casi['archivio rotto'] = $rotto;

		$troncato = $this->temporaneo( '.sql' );
		file_put_contents( $troncato, substr( $buono, 0, (int) strpos( $buono, 'Atto di prova' ) + 4 ) );
		$casi['testo troncato a meta\' di un valore'] = $troncato;

		$conti = $this->temporaneo( '.sql' );
		file_put_contents( $conti, str_replace( 'VALUES (1, 1, 2041,', 'VALUES (1, 2041,', $buono ) );
		$casi['riga con un valore in meno'] = $conti;

		$senza_colonne = $this->temporaneo( '.sql' );
		file_put_contents( $senza_colonne, "INSERT INTO `wp_albopretorio_atti` VALUES (1, 1, 2041);\n" );
		$casi['dati prima della creazione, senza colonne'] = $senza_colonne;

		$due_siti = $this->temporaneo( '.sql' );
		file_put_contents( $due_siti, $buono . "\n" . $this->sql_vecchio( $this->tabelle_vecchie( array( $this->atto_vecchio( array() ) ) ), array(), 'altro_' ) );
		$casi['tabelle di due siti'] = $due_siti;

		foreach ( $casi as $caso => $percorso ) {
			$letto = LettoreBackup::leggi( $percorso );

			$this->assertWPError( $letto, $caso );
			$this->assertSame( 'albo_importazione_lettura', $letto->get_error_code(), $caso );
			$this->assertStringStartsWith( 'Il backup non si legge:', $letto->get_error_message(), $caso );
		}

		$this->assertSame( 'Il backup non si legge: nell\'archivio ci sono piu\' file SQL: non si sa quale sia il backup.', LettoreBackup::leggi( $doppio )->get_error_message() );
		$this->assertSame( 'Il backup non si legge: nell\'archivio ci sono due file con lo stesso nome (doc-a.pdf).', LettoreBackup::leggi( $omonimi )->get_error_message() );

		// Troncato fuori dai testi: dentro l'ultimo aggiornamento del contatore, o dentro una creazione.
		$contatore = $this->sql_vecchio(
			$this->tabelle_vecchie( array( $this->atto_vecchio( array() ) ) ),
			array(
				'opt_AP_NumeroProgressivo' => '2',
				'opt_AP_AnnoProgressivo'   => '2041',
			)
		);
		$ultimo    = (int) strrpos( $contatore, 'UPDATE' );
		$tagli     = array(
			'dopo SET'                   => substr( $contatore, 0, (int) strpos( $contatore, 'SET', $ultimo ) + 3 ),
			'dopo WHERE'                 => substr( $contatore, 0, (int) strpos( $contatore, 'WHERE', $ultimo ) + 5 ),
			'dentro una creazione'       => substr( $contatore, 0, (int) strpos( $contatore, '`Oggetto`' ) ),
			'dentro un inserimento'      => substr( $contatore, 0, (int) strpos( $contatore, 'VALUES (' ) + 10 ),
			'dopo il nome della tabella' => substr( $contatore, 0, (int) strpos( $contatore, 'INSERT INTO `wp_options`' ) + 24 ),
		);

		foreach ( $tagli as $caso => $testo ) {
			$letto = LettoreBackup::leggi_sql( array( $testo ) );

			$this->assertWPError( $letto, $caso );
			$this->assertStringContainsString( 'troncato', $letto->get_error_message(), $caso );
		}

		$this->assertIsArray( LettoreBackup::leggi_sql( array( $contatore ) ), 'Intero, con l\'ultimo aggiornamento senza punto e virgola, si legge.' );

		// I limiti: appena sotto si legge, appena sopra si rifiuta, con e senza a capo e punto e virgola finale.
		$creazione = 'CREATE TABLE `l_albopretorio_atti` (`IdAtto` int(11), `Oggetto` text);';
		$riga      = "INSERT INTO `l_albopretorio_atti` VALUES (1,'" . str_repeat( 'a', 100 ) . "')";
		$misura    = strlen( $riga );

		foreach ( array( ";\n", ';', '' ) as $fine ) {
			$testo = array( $creazione . $riga . $fine );

			$this->assertIsArray( LettoreBackup::leggi_sql( $testo, array( 'istruzione' => $misura ) ), 'Al limite, fine ' . wp_json_encode( $fine ) );
			$this->assertStringContainsString( 'troppo lunga', LettoreBackup::leggi_sql( $testo, array( 'istruzione' => $misura - 1 ) )->get_error_message(), 'Oltre il limite, fine ' . wp_json_encode( $fine ) );
		}

		$testo = array( $creazione . $riga . ';' );
		$dati  = 1 + 100 + 2 * LettoreBackup::VALORE_COSTO;

		$this->assertIsArray( LettoreBackup::leggi_sql( $testo, array( 'dati' => $dati ) ) );
		$this->assertStringContainsString( 'troppi', LettoreBackup::leggi_sql( $testo, array( 'dati' => $dati - 1 ) )->get_error_message() );
		$this->assertIsArray( LettoreBackup::leggi_sql( $testo, array( 'sql' => strlen( $testo[0] ) ) ) );
		$this->assertStringContainsString( 'troppo grande', LettoreBackup::leggi_sql( $testo, array( 'sql' => strlen( $testo[0] ) - 1 ) )->get_error_message() );
	}

	/**
	 * A-188. Leggere il backup dalla schermata non scrive niente, da nessuna parte.
	 */
	public function test_a188_nessuna_scrittura(): void {
		global $wpdb;

		$contenuto = '%PDF-1.4 documento';
		$sql       = $this->sql_vecchio(
			$this->tabelle_vecchie(
				array(
					$this->atto_vecchio( array( 'Numero' => 3 ) ),
					$this->atto_vecchio(
						array(
							'IdAtto'     => 2,
							'Numero'     => 0,
							'Oggetto'    => 'Bozza',
							'DataInizio' => '0000-00-00',
							'DataFine'   => '0000-00-00',
						)
					),
				),
				array( $this->documento( 1, 1, '2041/10/documento.pdf', $contenuto ) )
			),
			array(
				'opt_AP_NumeroProgressivo' => '4',
				'opt_AP_AnnoProgressivo'   => '2041',
			)
		);

		$cartella = SchermataImportazione::cartella();
		$this->crea_cartella( $cartella );
		$backup = $cartella . '/backup.zip';
		copy( $this->zip_vecchio( $sql, array( '2041/10/documento.pdf' => $contenuto ) ), $backup );

		$scritture = array();

		$osserva = static function ( $query ) use ( &$scritture ) {
			if ( 1 === preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i', (string) $query ) ) {
				$scritture[] = (string) $query;
			}

			return $query;
		};

		wp_set_current_user( $this->amministratore );

		// Il gettone si crea prima di osservare: la prima creazione scrive le chiavi segrete del sito.
		$_POST = array(
			SchermataImportazione::CAMPO_GETTONE  => wp_create_nonce( SchermataImportazione::AZIONE ),
			SchermataImportazione::CAMPO_PRESENTE => 'backup.zip',
		);

		$impronta_backup = hash_file( 'sha256', $backup );
		$banca_prima     = $this->fotografia_banca();
		$disco_prima     = $this->fotografia_disco();

		add_filter( 'query', $osserva );

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		remove_filter( 'query', $osserva );

		$this->assertStringContainsString( 'data-atto="1"', $pagina, 'Precondizione: il rapporto e\' stato fatto.' );
		$this->assertSame( array(), $scritture, 'Nessuna istruzione di scrittura e\' arrivata alla banca dati.' );
		$this->assertSame( $banca_prima, $this->fotografia_banca(), 'La banca dati e\' identica.' );
		$this->assertSame( $disco_prima, $this->fotografia_disco(), 'Nessun file e\' stato creato, cambiato o tolto.' );
		$this->assertSame( $impronta_backup, hash_file( 'sha256', $backup ), 'Il backup non e\' stato toccato.' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: conta gli atti nella banca dati, senza passare dal componente.
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type = %s', $wpdb->posts, \AlboPretorioPa\TIPO ) ) );
	}

	/**
	 * A-189. Stato e visibilita' si ricostruiscono dalle date, nel fuso del sito.
	 */
	public function test_a189_stato_e_visibilita(): void {
		$file  = array();
		$righe = array(
			'in_corso'     => array(
				'DataInizio' => '2041-10-01',
				'DataFine'   => '2041-10-16',
			),
			'scaduto'      => array(
				'DataInizio' => '2041-09-30',
				'DataFine'   => '2041-10-15',
			),
			'bozza'        => array(
				'Numero'     => 0,
				'DataInizio' => '0000-00-00',
				'DataFine'   => '0000-00-00',
			),
			'senza_fine'   => array(
				'DataInizio' => '2041-10-01',
				'DataFine'   => '0000-00-00',
			),
			'futuro'       => array(
				'DataInizio' => '2041-10-20',
				'DataFine'   => '2041-11-04',
			),
			'rovesciato'   => array(
				'DataInizio' => '2041-10-10',
				'DataFine'   => '2041-10-05',
			),
			'annullato'    => array(
				'DataInizio'         => '2041-10-01',
				'DataFine'           => '2041-10-16',
				'DataAnnullamento'   => '2041-10-10',
				'MotivoAnnullamento' => 'Errore materiale',
			),
			'senza_inizio' => array(
				'DataInizio' => '0000-00-00',
				'DataFine'   => '2041-10-20',
			),
		);

		$atti     = array();
		$allegati = array();
		$id       = 0;

		foreach ( $righe as $nome => $campi ) {
			++$id;
			$atti[]                 = $this->atto_vecchio(
				$campi + array(
					'IdAtto'  => $id,
					'Numero'  => $id,
					'Oggetto' => $nome,
				)
			);
			$allegati[]             = $this->documento( $id, $id, $nome . '.pdf', 'pdf ' . $nome );
			$file[ $nome . '.pdf' ] = 'pdf ' . $nome;
		}

		$rapporto = $this->rapporto_di( $this->zip_vecchio( $this->sql_vecchio( $this->tabelle_vecchie( $atti, $allegati ) ), $file ) );
		$per_nome = array();

		foreach ( $rapporto['atti'] as $atto ) {
			$per_nome[ $atto['oggetto'] ] = $atto;
		}

		$this->assertSame( '2041-10-16', $rapporto['oggi'], 'Il giorno e\' quello del sito, non del tempo universale.' );

		$this->assertSame( 'in_pubblicazione', $per_nome['in_corso']['stato'] );
		$this->assertTrue( $per_nome['in_corso']['in_vista'], 'Fine oggi: visibile fino a mezzanotte.' );
		$this->assertSame( 'dubbio', $this->codici( $per_nome['in_corso']['segnalazioni'] )['conferma_dati_personali'] ?? null, 'Chi importa deve dare la conferma sui dati personali.' );
		$this->assertSame( AnalisiImportazione::DA_DECIDERE, $per_nome['in_corso']['esito'] );

		$this->assertSame( 'finito', $per_nome['scaduto']['stato'] );
		$this->assertFalse( $per_nome['scaduto']['in_vista'], 'Fine ieri: invisibile, senza impostazioni.' );
		$this->assertStringStartsWith( 'Invisibile al pubblico', $per_nome['scaduto']['pubblico'] );
		$this->assertSame( array(), $per_nome['scaduto']['segnalazioni'], 'Uno scaduto con il suo documento integro non chiede niente.' );
		$this->assertSame( AnalisiImportazione::IMPORTABILE, $per_nome['scaduto']['esito'] );

		$this->assertSame( AnalisiImportazione::ESCLUSO, $per_nome['bozza']['esito'] );
		$this->assertSame( 'bozza', $per_nome['bozza']['stato'] );

		$this->assertArrayHasKey( 'fine_assente', $this->codici( $per_nome['senza_fine']['segnalazioni'] ) );
		$this->assertFalse( $per_nome['senza_fine']['in_vista'], 'Senza fine arriva invisibile, come lo tratta la scadenza del nuovo albo.' );

		$this->assertSame( 'futuro', $per_nome['futuro']['stato'] );
		$this->assertArrayHasKey( 'inizio_futuro', $this->codici( $per_nome['futuro']['segnalazioni'] ) );
		$this->assertFalse( $per_nome['futuro']['in_vista'] );

		$this->assertSame( 'conflitto', $this->codici( $per_nome['rovesciato']['segnalazioni'] )['fine_prima_inizio'] ?? null );
		$this->assertSame( AnalisiImportazione::CONFLITTO, $per_nome['rovesciato']['esito'] );

		$this->assertSame( 'annullato', $per_nome['annullato']['stato'] );
		$this->assertTrue( $per_nome['annullato']['in_vista'] );
		$this->assertStringContainsString( 'Errore materiale', $this->testo_di( $per_nome['annullato']['segnalazioni'], 'annullato' ) );

		$this->assertSame( 'conflitto', $this->codici( $per_nome['senza_inizio']['segnalazioni'] )['inizio_non_valido'] ?? null );

		$this->assertSame( 1, $rapporto['riepilogo']['esiti'][ AnalisiImportazione::ESCLUSO ] );
		$this->assertSame( 8, $rapporto['riepilogo']['totale']['atti'] );
	}

	/**
	 * A-190. La durata scritta si confronta con la categoria, e la categoria con il tipo di atto.
	 */
	public function test_a190_durate_e_tipi(): void {
		$righe = array(
			'giusta'        => array(
				'DataInizio' => '2041-10-01',
				'DataFine'   => '2041-10-16',
			),
			'breve'         => array(
				'DataInizio' => '2041-10-01',
				'DataFine'   => '2041-10-11',
			),
			// 21 settembre piu' quindici giorni fa domenica 6 ottobre: la fine e' il lunedi'.
			'festiva'       => array(
				'DataInizio' => '2041-09-21',
				'DataFine'   => '2041-10-07',
			),
			'lunga'         => array(
				'DataInizio' => '2041-10-01',
				'DataFine'   => '2041-10-21',
			),
			// Dal 20 ottobre al 4 novembre passa il cambio dell'ora legale: sono quindici giorni lo stesso.
			'ora_legale'    => array(
				'DataInizio' => '2041-10-20',
				'DataFine'   => '2041-11-04',
			),
			'senza_giorni'  => array(
				'DataInizio'  => '2041-10-01',
				'DataFine'    => '2041-10-04',
				'IdCategoria' => 4,
			),
			'avviso'        => array(
				'DataInizio'  => '2041-10-01',
				'DataFine'    => '2041-10-31',
				'IdCategoria' => 2,
			),
			'bando'         => array(
				'DataInizio'  => '2041-10-01',
				'DataFine'    => '2041-10-31',
				'IdCategoria' => 3,
			),
			// 28 settembre piu' quindici giorni fa domenica 13 ottobre, ma il lunedi' 14 e' festa locale: non e' il primo giorno non festivo.
			'festa_dopo'    => array(
				'DataInizio' => '2041-09-28',
				'DataFine'   => '2041-10-14',
			),
			// Scaduti con la durata giusta: l'unico dubbio e' il tipo di atto ancora da decidere.
			'bando_finito'  => array(
				'DataInizio'  => '2041-09-01',
				'DataFine'    => '2041-10-01',
				'IdCategoria' => 3,
			),
			'avviso_finito' => array(
				'DataInizio'  => '2041-09-01',
				'DataFine'    => '2041-10-01',
				'IdCategoria' => 2,
			),
		);

		$this->assertTrue( Festivi::salva( Festivi::ACCESO, '14/10/2041|Festa locale di prova', $this->amministratore, Passaggi::ora() ) );

		$atti = array();
		$id   = 0;

		foreach ( $righe as $nome => $campi ) {
			++$id;
			$atti[] = $this->atto_vecchio(
				$campi + array(
					'IdAtto'  => $id,
					'Numero'  => $id,
					'Oggetto' => $nome,
				)
			);
		}

		$tabelle                             = $this->tabelle_vecchie(
			$atti,
			array(
				$this->documento( 1, 10, 'bando-finito.pdf', 'bando' ),
				$this->documento( 2, 11, 'avviso-finito.pdf', 'avviso' ),
			)
		);
		$tabelle['albopretorio_categorie'][] = array(
			'IdCategoria' => 2,
			'Nome'        => '  avviso ',
			'Descrizione' => '',
			'Genitore'    => 0,
			'Giorni'      => 30,
		);
		$tabelle['albopretorio_categorie'][] = array(
			'IdCategoria' => 3,
			'Nome'        => 'Bandi',
			'Descrizione' => '',
			'Genitore'    => 1,
			'Giorni'      => 30,
		);
		$tabelle['albopretorio_categorie'][] = array(
			'IdCategoria' => 4,
			'Nome'        => 'Comunicazioni',
			'Descrizione' => '',
			'Genitore'    => 0,
			'Giorni'      => 0,
		);

		$percorso = $this->temporaneo( '.sql' );
		file_put_contents( $percorso, $this->sql_vecchio( $tabelle ) );

		$rapporto = $this->rapporto_di( $percorso );
		$per_nome = array();

		foreach ( $rapporto['atti'] as $atto ) {
			$per_nome[ $atto['oggetto'] ] = $atto;
		}

		$durate = array();

		foreach ( $per_nome as $nome => $atto ) {
			$durate[ $nome ] = array_intersect( array_keys( $this->codici( $atto['segnalazioni'] ) ), array( 'durata_breve', 'durata_lunga', 'slittamento_festivi' ) );
		}

		$this->assertSame( 15, $per_nome['giusta']['giorni'] );
		$this->assertSame( array(), $durate['giusta'] );
		$this->assertSame( '15 giorni, origine: date scritte nel vecchio albo', $per_nome['giusta']['dati']['Durata'], 'Ogni durata dichiara la sua origine.' );
		$this->assertSame( array( 'durata_breve' ), array_values( $durate['breve'] ) );
		$this->assertSame( array( 'slittamento_festivi' ), array_values( $durate['festiva'] ) );
		$this->assertStringContainsString( '06/10/2041 Domenica', $this->testo_di( $per_nome['festiva']['segnalazioni'], 'slittamento_festivi' ) );
		$this->assertSame( array( 'durata_lunga' ), array_values( $durate['lunga'] ) );
		$this->assertSame( 15, $per_nome['ora_legale']['giorni'] );
		$this->assertSame( array(), $durate['ora_legale'] );
		$this->assertSame( array(), $durate['senza_giorni'], 'Senza giorni nella categoria non c\'e\' niente da confrontare.' );
		$this->assertSame( 'Avviso', $per_nome['avviso']['tipo'], 'Il nome si confronta senza maiuscole e spazi ai lati.' );
		$this->assertSame( 'Bandi (tipo da creare)', $per_nome['bando']['tipo'] );
		$this->assertSame( array( 'durata_lunga' ), array_values( $durate['festa_dopo'] ), 'La fine che cade a sua volta di festa non e\' uno slittamento.' );

		foreach ( array( 'bando_finito', 'avviso_finito' ) as $nome ) {
			$this->assertSame( 'finito', $per_nome[ $nome ]['stato'], $nome );
			$this->assertSame( array( 'tipo_da_decidere' ), array_keys( $this->codici( $per_nome[ $nome ]['segnalazioni'] ) ), $nome );
			$this->assertSame( AnalisiImportazione::DA_DECIDERE, $per_nome[ $nome ]['esito'], 'Una decisione aperta sul tipo cambia l\'esito dell\'atto: ' . $nome );
		}

		$this->assertStringContainsString( 'Nel nuovo albo non c\'e\' un tipo di atto con questo nome.', $this->testo_di( $per_nome['bando_finito']['segnalazioni'], 'tipo_da_decidere' ) );
		$this->assertStringContainsString( 'La categoria ha 30 giorni', $this->testo_di( $per_nome['avviso_finito']['segnalazioni'], 'tipo_da_decidere' ) );

		$categorie = array();

		foreach ( $rapporto['categorie'] as $categoria ) {
			$categorie[ $categoria['nome'] ] = $categoria;
		}

		$this->assertStringContainsString( '15 giorni, origine norma Estremi di prova', $categorie['Deliberazione']['corrisponde'] );
		$this->assertSame( 6, $categorie['Deliberazione']['atti'] );
		$this->assertArrayHasKey( 'durata_tipo_diversa', $this->codici( $categorie['avviso']['segnalazioni'] ) );
		$this->assertArrayHasKey( 'tipo_da_creare', $this->codici( $categorie['Bandi']['segnalazioni'] ) );
		$this->assertStringContainsString( 'origine della durata (norma o scelta dell\'amministrazione) la dichiara chi importa', $categorie['Bandi']['corrisponde'], 'Nessuna origine proposta.' );
		$this->assertSame( 'Deliberazione', $categorie['Bandi']['madre'] );
		$this->assertArrayHasKey( 'categoria_senza_giorni', $this->codici( $categorie['Comunicazioni']['segnalazioni'] ) );
	}

	/**
	 * A-191. Numeri doppi, gia' dati o oltre la partenza, e la proposta per la partenza.
	 */
	public function test_a191_numerazione(): void {
		$this->fissa_orologi( '2041-10-01 09:30:00' );
		$pubblicato = $this->atto_in_verifica();
		$this->assertIsArray( $this->pubblica_da_codice( $pubblicato, $this->pubblicatore ) );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => self::PARTENZA + 1,
			),
			Repertorio::di( $pubblicato ),
			'Precondizione: il nuovo albo ha dato il 41/2041.'
		);
		$this->fissa_orologi( self::ADESSO );

		$atti = array(
			$this->atto_vecchio(
				array(
					'IdAtto'     => 1,
					'Numero'     => 3,
					'Anno'       => 2040,
					'DataInizio' => '2040-03-01',
					'DataFine'   => '2040-03-16',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 2,
					'Numero'     => 3,
					'Anno'       => 2040,
					'DataInizio' => '2040-03-02',
					'DataFine'   => '2040-03-17',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 3,
					'Numero'     => 1,
					'Anno'       => 2040,
					'DataInizio' => '2041-01-03',
					'DataFine'   => '2041-01-18',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 4,
					'Numero' => 12,
					'Anno'   => 2041,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 5,
					'Numero' => self::PARTENZA + 1,
					'Anno'   => 2041,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 6,
					'Numero' => self::PARTENZA + 5,
					'Anno'   => 2041,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 7,
					'Numero' => 3,
					'Anno'   => 0,
				)
			),
			// Numeri che non sono numeri di registro: non diventano bozze.
			$this->atto_vecchio(
				array(
					'IdAtto' => 8,
					'Numero' => 'NULL',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 9,
					'Numero' => -4,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 10,
					'Numero' => "'12a'",
				)
			),
		);

		$scrivi = function ( array $opzioni ) use ( $atti ): string {
			$percorso = $this->temporaneo( '.sql' );
			file_put_contents( $percorso, $this->sql_vecchio( $this->tabelle_vecchie( $atti ), $opzioni ) );

			return $percorso;
		};

		$rapporto = $this->rapporto_di(
			$scrivi(
				array(
					'opt_AP_NumeroProgressivo' => '46',
					'opt_AP_AnnoProgressivo'   => '2041',
				)
			)
		);

		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '1' )['segnalazioni'] )['numero_doppio'] ?? null );
		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '2' )['segnalazioni'] )['numero_doppio'] ?? null );
		$this->assertStringContainsString( 'numeri interni 1, 2', $this->testo_di( $this->atto( $rapporto, '1' )['segnalazioni'], 'numero_doppio' ) );
		$this->assertSame( 'dubbio', $this->codici( $this->atto( $rapporto, '3' )['segnalazioni'] )['anno_diverso'] ?? null );
		$this->assertSame( array(), array_intersect( array( 'numero_doppio', 'numero_gia_usato', 'numero_oltre_partenza' ), array_keys( $this->codici( $this->atto( $rapporto, '4' )['segnalazioni'] ) ) ) );
		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '5' )['segnalazioni'] )['numero_gia_usato'] ?? null );
		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '6' )['segnalazioni'] )['numero_oltre_partenza'] ?? null );
		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '7' )['segnalazioni'] )['anno_non_valido'] ?? null, 'Un anno zero non e\' un anno.' );
		$this->assertArrayNotHasKey( 'numerazione_non_letta', $this->codici( $rapporto['segnalazioni'] ), 'L\'anno zero non si chiede alla numerazione del nuovo albo.' );

		foreach ( array(
			'8'  => 'NULL',
			'9'  => '-4',
			'10' => '12a',
		) as $id => $scritto ) {
			$atto = $this->atto( $rapporto, (string) $id );

			$this->assertNotSame( 'bozza', $atto['stato'], 'Un numero non valido non e\' una bozza: ' . $scritto );
			$this->assertSame( 'conflitto', $this->codici( $atto['segnalazioni'] )['numero_non_valido'] ?? null, $scritto );
			$this->assertStringContainsString( '(' . $scritto . ')', $this->testo_di( $atto['segnalazioni'], 'numero_non_valido' ), 'Il valore scritto si mostra com\'e\'.' );
		}

		$anni = array_column( $rapporto['numerazione']['anni'], null, 'anno' );

		$this->assertSame( 1, $anni[2040]['mancanti'], 'Fra 1 e 3 manca il 2.' );
		$this->assertNull( $anni[2040]['nuovo'] );
		$this->assertSame( self::PARTENZA, $anni[2041]['nuovo']['partenza'] );
		$this->assertSame(
			array(
				'anno'      => 2041,
				'contatore' => 46,
				'massimo'   => 45,
				'ultimo'    => 45,
			),
			$rapporto['numerazione']['proposta']
		);
		$this->assertArrayNotHasKey( 'contatore_diverso', $this->codici( $rapporto['segnalazioni'] ) );

		$rapporto = $this->rapporto_di(
			$scrivi(
				array(
					'opt_AP_NumeroProgressivo' => '60',
					'opt_AP_AnnoProgressivo'   => '2040',
				)
			)
		);
		$generali = $this->codici( $rapporto['segnalazioni'] );

		$this->assertSame( 'dubbio', $generali['contatore_diverso'] ?? null );
		$this->assertArrayHasKey( 'contatore_anno_vecchio', $generali );
		$this->assertSame( 59, $rapporto['numerazione']['proposta']['ultimo'], 'La proposta e\' il piu\' alto fra contatore e atti, e resta una proposta.' );

		$rapporto = $this->rapporto_di( $scrivi( array() ) );

		$this->assertArrayHasKey( 'contatore_assente', $this->codici( $rapporto['segnalazioni'] ) );
		$this->assertNull( $rapporto['numerazione']['proposta'] );
		$this->assertSame( self::PARTENZA + 1, Repertorio::anno( 2041 )['ultimo'], 'La lettura non ha toccato la numerazione.' );
	}

	/**
	 * A-192. I documenti: trovati, cambiati, mancanti, spostati, e il documento principale.
	 */
	public function test_a192_documenti(): void {
		$atti = array(
			$this->atto_vecchio(
				array(
					'IdAtto' => 1,
					'Numero' => 1,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 2,
					'Numero' => 2,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 3,
					'Numero'     => 3,
					'DataInizio' => '2041-09-01',
					'DataFine'   => '2041-09-16',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 4,
					'Numero'     => 4,
					'DataInizio' => '2041-09-01',
					'DataFine'   => '2041-09-16',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 5,
					'Numero'     => 5,
					'DataInizio' => '2041-09-01',
					'DataFine'   => '2041-09-16',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 6,
					'Numero'     => 6,
					'DataInizio' => '2041-09-01',
					'DataFine'   => '2041-09-16',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto' => 7,
					'Numero' => 7,
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 8,
					'Numero'     => 8,
					'DataInizio' => '2041-09-01',
					'DataFine'   => '2041-09-16',
				)
			),
		);

		$allegati = array(
			$this->documento( 1, 1, '2041/10/delibera.pdf', 'delibera' ),
			// Lo stesso albo spostato su un altro server: il percorso registrato e' diverso, la parte finale no.
			array_merge( $this->documento( 2, 2, '2041/10/avviso.pdf', 'avviso originale' ), array( 'Allegato' => 'C:\\siti\\altro\\wp-content\\uploads\\AllegatiAttiAlboPretorio\\2041\\10\\avviso.pdf' ) ),
			$this->documento( 3, 3, '2041/09/sparito.pdf', 'sparito' ),
			$this->documento( 4, 4, '2040/05/bando.pdf', 'bando' ),
			$this->documento( 5, 5, 'principale-uno.pdf', 'uno' ),
			array_merge(
				$this->documento( 6, 5, 'principale-due.pdf', 'due' ),
				array(
					'DocIntegrale' => 0,
					'Impronta'     => '',
				)
			),
			$this->documento( 7, 5, 'allegato.pdf', 'allegato', 'A' ),
			$this->documento( 8, 7, '2041/10/mancante-visibile.pdf', 'x' ),
			$this->documento( 9, 8, 'doppio.pdf', 'doppio' ),
			$this->documento( 10, 99, 'di-nessuno.pdf', 'nessuno' ),
		);

		$file = array(
			'2041/10/delibera.pdf' => 'delibera',
			'2041/10/avviso.pdf'   => 'avviso cambiato dopo',
			'vecchi/bando.pdf'     => 'bando',
			'principale-uno.pdf'   => 'uno',
			'principale-due.pdf'   => 'due',
			'allegato.pdf'         => 'allegato',
			'2040/doppio.pdf'      => 'doppio',
			'2041/doppio.pdf'      => 'doppio',
			'sparso.pdf'           => 'non citato',
			'di-nessuno.pdf'       => 'nessuno',
		);

		$rapporto = $this->rapporto_di( $this->zip_vecchio( $this->sql_vecchio( $this->tabelle_vecchie( $atti, $allegati ) ), $file ) );

		$this->assertSame( 'trovato, impronta uguale a quella registrata al caricamento', $this->atto( $rapporto, '1' )['documenti'][0]['verifica'] );
		$this->assertSame( '2041/10/delibera.pdf', $this->atto( $rapporto, '1' )['documenti'][0]['file'] );

		$this->assertSame( '2041/10/avviso.pdf', $this->atto( $rapporto, '2' )['documenti'][0]['file'], 'Si trova anche con il percorso di un altro server.' );
		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '2' )['segnalazioni'] )['impronta_diversa'] ?? null, 'Un file cambiato su un atto visibile e\' un conflitto.' );

		$this->assertSame( 'dubbio', $this->codici( $this->atto( $rapporto, '3' )['segnalazioni'] )['file_mancante'] ?? null, 'Su uno scaduto il file mancante e\' un dubbio.' );
		$this->assertSame( 'conflitto', $this->codici( $this->atto( $rapporto, '7' )['segnalazioni'] )['file_mancante'] ?? null, 'Su un atto visibile e\' un conflitto.' );

		$this->assertSame( 'vecchi/bando.pdf', $this->atto( $rapporto, '4' )['documenti'][0]['file'] );
		$this->assertSame( 'nota', $this->codici( $this->atto( $rapporto, '4' )['segnalazioni'] )['file_altrove'] ?? null );

		$cinque = $this->codici( $this->atto( $rapporto, '5' )['segnalazioni'] );
		$this->assertSame( 'dubbio', $cinque['principali_multipli'] ?? null );
		$this->assertSame( 'dubbio', $cinque['per_estratto'] ?? null );
		$this->assertSame( 'trovato; impronta non registrata nel vecchio albo', $this->atto( $rapporto, '5' )['documenti'][1]['verifica'] );
		$this->assertSame( 'allegato', $this->atto( $rapporto, '5' )['documenti'][2]['ruolo'] );

		$this->assertSame( 'dubbio', $this->codici( $this->atto( $rapporto, '6' )['segnalazioni'] )['principale_assente'] ?? null );

		$this->assertNull( $this->atto( $rapporto, '8' )['documenti'][0]['file'], 'Due file con lo stesso nome in cartelle diverse: non si sceglie a caso.' );

		$this->assertSame( array( '2040/doppio.pdf', '2041/doppio.pdf', 'di-nessuno.pdf', 'sparso.pdf' ), $rapporto['orfani'], 'Anche il file di un allegato senza atto resta fuori.' );
		$generali = $this->codici( $rapporto['segnalazioni'] );
		$this->assertArrayHasKey( 'file_orfani', $generali );
		$this->assertArrayHasKey( 'allegati_senza_atto', $generali );

		// La schermata mostra quali file restano fuori e quale file e' stato scelto per ciascun documento.
		ob_start();
		SchermataImportazione::stampa( $rapporto );
		$pagina = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '#<ul class="albo-pretorio-orfani">(.*?)</ul>#s', $pagina, $elenco ), 'L\'elenco dei file esclusi e\' nella pagina.' );
		$this->assertSame( array( '2040/doppio.pdf', '2041/doppio.pdf', 'di-nessuno.pdf', 'sparso.pdf' ), preg_match_all( '#<code>([^<]*)</code>#', $elenco[1], $nomi ) ? $nomi[1] : array() );
		$this->assertStringContainsString( 'File nell&#039;archivio: <code>vecchi/bando.pdf</code>', $pagina, 'Il file trovato altrove si vede per percorso.' );
		$this->assertStringContainsString( 'File nell&#039;archivio: <code>2041/10/avviso.pdf</code>', $pagina );
		$this->assertSame( 6, substr_count( $pagina, 'class="albo-pretorio-file"' ), 'Solo i documenti con un file trovato hanno il percorso: non il mancante, non il doppio.' );
	}

	/**
	 * A-193. Dati dell'atto e del procedimento, campi senza posto e campi sconosciuti.
	 */
	public function test_a193_dati_e_campi(): void {
		$schema                                    = $this->schema_vecchio();
		$schema['albopretorio_atti']['Protocollo'] = "varchar(20) NOT NULL DEFAULT ''";

		$atti = array(
			$this->atto_vecchio(
				array(
					'IdAtto'               => 1,
					'Riferimento'          => 'Determina 45/2041',
					'RespProc'             => 5,
					'IdUnitaOrganizzativa' => 2,
					'Richiedente'          => 'Comune vicino',
					'Soggetti'             => 'a:2:{i:0;s:1:"5";i:1;s:1:"6";}',
					'Informazioni'         => 'Nota interna',
					'Protocollo'           => '1234',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'      => 2,
					'Numero'      => 2,
					'RespProc'    => 77,
					'Richiedente' => '<b>Ufficio</b>',
					'Riferimento' => "45\n2041",
					'Ente'        => 3,
					'Oggetto'     => "Oggetto \xC0\xAF non leggibile",
				)
			),
		);

		$tabelle                                    = $this->tabelle_vecchie( $atti );
		$tabelle['albopretorio_resprocedura']       = array(
			array(
				'IdResponsabile' => 5,
				'Cognome'        => 'Rossi',
				'Nome'           => 'Maria',
				'Email'          => '',
				'Telefono'       => '',
				'Orario'         => '',
				'Note'           => '',
				'Funzione'       => 'RP',
			),
			array(
				'IdResponsabile' => 6,
				'Cognome'        => 'Bianchi',
				'Nome'           => 'Luca',
				'Email'          => '',
				'Telefono'       => '',
				'Orario'         => '',
				'Note'           => '',
				'Funzione'       => 'OP',
			),
		);
		$tabelle['albopretorio_unitaorganizzative'] = array(
			array(
				'IdUO'      => 2,
				'Nome'      => 'Ufficio tecnico',
				'Indirizzo' => '',
				'Url'       => '',
				'Email'     => '',
				'Pec'       => '',
				'Telefono'  => '',
				'Fax'       => '',
				'Note'      => '',
			),
		);
		$tabelle['albopretorio_enti'][]             = array(
			'IdEnte'    => 3,
			'Nome'      => 'Amministrazione ospitata di prova',
			'Indirizzo' => '',
			'Url'       => '',
			'Email'     => '',
			'Pec'       => '',
			'Telefono'  => '',
			'Fax'       => '',
			'Note'      => '',
		);
		$tabelle['albopretorio_attimeta']           = array(
			array(
				'IdAttoMeta' => 1,
				'IdAtto'     => 1,
				'Meta'       => 'gruppo',
				'Value'      => 'concorsi',
			),
		);

		$percorso = $this->temporaneo( '.sql' );
		file_put_contents( $percorso, $this->sql_vecchio( $tabelle, array(), 'wp_', $schema ) );

		$rapporto = $this->rapporto_di( $percorso );
		$primo    = $this->atto( $rapporto, '1' );

		$this->assertSame( 'Determina 45/2041', $primo['dati']['Numero proprio (proposto dal riferimento)'] );
		$this->assertStringStartsWith( 'non indicato', $primo['dati']['Organo'] );
		$this->assertStringStartsWith( 'non indicata', $primo['dati']['Data di adozione'] );
		$this->assertSame( 'Rossi Maria (si conserva; si mostra se l\'amministrazione sceglie di usarlo)', $primo['dati'][ DatiProcedimento::etichetta( DatiProcedimento::RESPONSABILE ) ], 'Senza scelta dell\'amministrazione il dato si conserva.' );
		$this->assertSame( 'Ufficio tecnico (si conserva; si mostra se l\'amministrazione sceglie di usarlo)', $primo['dati'][ DatiProcedimento::etichetta( DatiProcedimento::UFFICIO ) ] );
		$this->assertSame( 'Comune vicino (si conserva; si mostra se l\'amministrazione sceglie di usarlo)', $primo['dati'][ DatiProcedimento::etichetta( DatiProcedimento::RICHIEDENTE ) ] );

		$codici = $this->codici( $primo['segnalazioni'] );
		$this->assertSame( 'nota', $codici['soggetti_senza_posto'] ?? null );
		$this->assertStringContainsString( 'Rossi Maria, Bianchi Luca', $this->testo_di( $primo['segnalazioni'], 'soggetti_senza_posto' ) );
		$this->assertSame( 'nota', $codici['informazioni_senza_posto'] ?? null );
		$this->assertSame( 'nota', $codici['etichette_senza_posto'] ?? null );
		$this->assertStringContainsString( '(gruppo: concorsi)', $this->testo_di( $primo['segnalazioni'], 'etichette_senza_posto' ), 'L\'etichetta si nomina con il suo valore.' );
		$this->assertSame( 1, $rapporto['campi']['non_riconosciuti']['wp_albopretorio_atti']['Protocollo'] );
		$this->assertArrayHasKey( 'campo_non_riconosciuto', $this->codici( $rapporto['segnalazioni'] ) );

		$secondo = $this->codici( $this->atto( $rapporto, '2' )['segnalazioni'] );
		$this->assertSame( 'dubbio', $secondo['responsabile_assente'] ?? null );
		$this->assertSame( 'dubbio', $secondo['nome_non_conservabile'] ?? null );
		$this->assertSame( 'dubbio', $secondo['riferimento_non_conservabile'] ?? null );
		$this->assertSame( 'dubbio', $secondo['altra_amministrazione'] ?? null );
		$this->assertStringContainsString( 'Amministrazione ospitata di prova', $this->testo_di( $this->atto( $rapporto, '2' )['segnalazioni'], 'altra_amministrazione' ) );
		$this->assertSame( 'dubbio', $secondo['testo_non_leggibile'] ?? null );

		// L'amministrazione sceglie: il responsabile si mostra ed e' in elenco, l'ufficio si conserva soltanto.
		$this->assertTrue(
			DatiProcedimento::salva(
				array(
					DatiProcedimento::RESPONSABILE => DatiProcedimento::USA,
					DatiProcedimento::UFFICIO      => DatiProcedimento::NON_USA,
					DatiProcedimento::RICHIEDENTE  => DatiProcedimento::USA,
				),
				'Rossi Maria',
				'',
				$this->amministratore,
				Passaggi::ora()
			)
		);

		$primo  = $this->atto( $this->rapporto_di( $percorso ), '1' );
		$codici = $this->codici( $primo['segnalazioni'] );

		$this->assertSame( 'Rossi Maria (si mostra)', $primo['dati'][ DatiProcedimento::etichetta( DatiProcedimento::RESPONSABILE ) ] );
		$this->assertSame( 'Ufficio tecnico (si conserva e non si mostra)', $primo['dati'][ DatiProcedimento::etichetta( DatiProcedimento::UFFICIO ) ] );
		$this->assertSame( 'Comune vicino (si mostra)', $primo['dati'][ DatiProcedimento::etichetta( DatiProcedimento::RICHIEDENTE ) ], 'Il richiedente si mostra quando l\'amministrazione sceglie di usarlo.' );
		$this->assertStringContainsString( 'Ufficio tecnico', $this->testo_di( $primo['segnalazioni'], 'fuori_elenco' ) );
		$this->assertStringNotContainsString( 'Rossi Maria', $this->testo_di( $primo['segnalazioni'], 'fuori_elenco' ) );
	}

	/**
	 * A-194. La schermata: chi la apre, da dove legge, e che il rapporto esce sfuggito.
	 */
	public function test_a194_schermata(): void {
		$cartella = SchermataImportazione::cartella();
		$this->crea_cartella( $cartella );

		$sql = $this->sql_vecchio( $this->tabelle_vecchie( array( $this->atto_vecchio( array( 'Oggetto' => '<script>alert(1)</script> Avviso' ) ) ) ) );
		copy( $this->zip_vecchio( $sql ), $cartella . '/backup.zip' );

		$fuori = $this->zip_vecchio( $sql );
		symlink( $fuori, $cartella . '/collegamento.zip' );

		$this->assertSame( array( 'backup.zip' ), array_keys( SchermataImportazione::presenti() ), 'Un collegamento simbolico non e\' un backup presente.' );

		$gettone = function (): array {
			return array( SchermataImportazione::CAMPO_GETTONE => wp_create_nonce( SchermataImportazione::AZIONE ) );
		};

		$soltanto_consulta = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );
		$soltanto_pubblica = $this->utente_con( array( 'publish_posts' ) );
		$abbonato          = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// La voce del menu c'e' solo per chi puo' aprire la schermata.
		$voce = function (): bool {
			global $submenu;

			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- menu dell'amministrazione ricostruito per la prova.
			SchermataImportazione::menu();

			return in_array( SchermataImportazione::PAGINA, wp_list_pluck( $submenu[ 'edit.php?post_type=' . TIPO ] ?? array(), 2 ), true );
		};

		foreach ( array(
			'redattore'         => $this->redattore,
			'soltanto consulta' => $soltanto_consulta,
			'soltanto pubblica' => $soltanto_pubblica,
			'abbonato'          => $abbonato,
		) as $chi => $utente ) {
			wp_set_current_user( $utente );

			$this->assertFalse( SchermataImportazione::permessa(), $chi );
			$this->assertFalse( $voce(), $chi );

			$esito = SchermataImportazione::da_invio( $gettone() + array( SchermataImportazione::CAMPO_PRESENTE => 'backup.zip' ), array() );
			$this->assertWPError( $esito, $chi );
			$this->assertSame( 'albo_importazione_permesso', $esito->get_error_code(), $chi );

			try {
				ob_start();
				SchermataImportazione::mostra();
				ob_end_clean();
				$this->fail( 'La schermata si apre a: ' . $chi );
			} catch ( \WPDieException $rifiuto ) {
				ob_end_clean();
				$this->assertStringContainsString( 'permesso', $rifiuto->getMessage(), $chi );
			}
		}

		$this->assertTrue( user_can( $soltanto_pubblica, Permessi::mappa()['publish_posts'] ), 'Precondizione: pubblica davvero.' );

		foreach ( array(
			'amministratore'   => $this->amministratore,
			'ruolo dell\'albo' => $this->pubblicatore,
		) as $chi => $utente ) {
			wp_set_current_user( $utente );
			$this->assertTrue( SchermataImportazione::permessa(), $chi );
			$this->assertTrue( $voce(), $chi );
		}

		wp_set_current_user( $this->amministratore );

		$esito = SchermataImportazione::da_invio(
			array(
				SchermataImportazione::CAMPO_GETTONE  => 'sbagliato',
				SchermataImportazione::CAMPO_PRESENTE => 'backup.zip',
			),
			array()
		);
		$this->assertSame( 'albo_importazione_gettone', $esito->get_error_code() );

		foreach ( array( '../backup.zip', 'collegamento.zip', 'altro.zip', '' ) as $nome ) {
			$esito = SchermataImportazione::da_invio( $gettone() + array( SchermataImportazione::CAMPO_PRESENTE => $nome ), array() );
			$this->assertWPError( $esito, $nome );
			$this->assertSame( 'albo_importazione_nessuno', $esito->get_error_code(), $nome );
		}

		$non_caricato = array(
			SchermataImportazione::CAMPO_FILE => array(
				'name'     => 'backup.zip',
				'tmp_name' => $cartella . '/backup.zip',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 10,
			),
		);
		$esito        = SchermataImportazione::da_invio( $gettone(), $non_caricato );
		$this->assertSame( 'albo_importazione_caricamento', $esito->get_error_code(), 'Un file che PHP non ha ricevuto come caricamento non si legge.' );

		$troppo = array(
			SchermataImportazione::CAMPO_FILE => array(
				'name'     => 'b.zip',
				'tmp_name' => '',
				'error'    => UPLOAD_ERR_INI_SIZE,
				'size'     => 0,
			),
		);
		$esito  = SchermataImportazione::da_invio( $gettone(), $troppo );
		$this->assertStringContainsString( 'cartella dei backup', $esito->get_error_message() );

		$_POST = $gettone() + array( SchermataImportazione::CAMPO_PRESENTE => 'backup.zip' );

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt; Avviso', $pagina );
		$this->assertStringNotContainsString( '<script>alert(1)', $pagina );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $pagina );

		// Un invio oltre post_max_size arriva senza campi e senza file: si dice perche'.
		$_POST                     = array();
		$_FILES                    = array();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_LENGTH'] = '999999999';

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_LENGTH'] );

		$this->assertStringContainsString( 'cartella dei backup', $pagina, 'Un invio scartato da PHP non passa in silenzio.' );
		$this->assertStringContainsString( 'notice-error', $pagina );

		wp_set_current_user( $soltanto_consulta );
		$this->expectException( \WPDieException::class );
		SchermataImportazione::mostra();
	}

	/**
	 * Il testo di una segnalazione, per codice.
	 *
	 * @param array<int, array{livello: string, codice: string, testo: string}> $segnalazioni Segnalazioni.
	 * @param string                                                            $codice       Codice.
	 * @return string
	 */
	private function testo_di( array $segnalazioni, string $codice ): string {
		foreach ( $segnalazioni as $segnalazione ) {
			if ( $codice === $segnalazione['codice'] ) {
				return $segnalazione['testo'];
			}
		}

		return '';
	}

	/**
	 * Crea una cartella, e le sue madri, da togliere a fine prova.
	 *
	 * @param string $cartella Cartella.
	 */
	private function crea_cartella( string $cartella ): void {
		$madri = array();

		$corrente = $cartella;

		while ( ! is_dir( $corrente ) ) {
			$madri[]  = $corrente;
			$corrente = dirname( $corrente );
		}

		foreach ( array_reverse( $madri ) as $nuova ) {
			$this->assertTrue( mkdir( $nuova ) );
			$this->cartelle[] = $nuova;
		}
	}

	/**
	 * Il contenuto delle tabelle del sito, per confrontarlo.
	 *
	 * @return array<string, string>
	 */
	private function fotografia_banca(): array {
		global $wpdb;

		$fotografia = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: l'elenco delle tabelle del sito.
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) ) as $tabella ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- prova: fotografia del contenuto, mai riletta come dati.
			$fotografia[ (string) $tabella ] = md5( serialize( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $tabella ), ARRAY_N ) ) );
		}

		return $fotografia;
	}

	/**
	 * I file sotto wp-content, con dimensione e data.
	 *
	 * @return array<string, string>
	 */
	private function fotografia_disco(): array {
		$fotografia = array();
		$filtro     = new \RecursiveCallbackFilterIterator(
			new \RecursiveDirectoryIterator( WP_CONTENT_DIR, \FilesystemIterator::SKIP_DOTS ),
			static function ( \SplFileInfo $file ): bool {
				// Il codice di WordPress, dei temi e dei componenti non e' dove una lettura potrebbe scrivere.
				return ! in_array( $file->getPathname(), array( WP_CONTENT_DIR . '/plugins', WP_CONTENT_DIR . '/themes' ), true );
			}
		);

		foreach ( new \RecursiveIteratorIterator( $filtro ) as $file ) {
			// Anche il contenuto: una riscrittura della stessa lunghezza nello stesso secondo non deve passare.
			$fotografia[ (string) $file ] = $file->getSize() . ' ' . $file->getMTime() . ' ' . sha1_file( (string) $file );
		}

		ksort( $fotografia );

		return $fotografia;
	}
}
