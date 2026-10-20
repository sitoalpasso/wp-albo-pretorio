<?php
/**
 * L'importazione vera dal backup del vecchio albo: righe A-210..A-217 (ALBO-35).
 *
 * Il backup di prova sta nella cartella del vecchio albo, come sul sito vero.
 * L'orologio e' fissato a mezzanotte e mezza del 16 ottobre 2041 nel fuso del
 * sito: l'atto con fine il 16 e' ancora in pubblicazione, quelli con fine a
 * settembre sono scaduti. La partenza dichiarata per il 2041 e' 40.
 *
 * Gli atti del backup, per numero interno:
 *
 * - 1: 10/2041, scaduto, un documento: si porta;
 * - 2: 11/2041, in pubblicazione: si porta solo con la conferma separata;
 * - 3: 12/2041, scaduto, due allegati e nessun documento: si porta, principale da scegliere;
 * - 4: 13/2041, annullato e scaduto: si porta, con l'annullamento nel registro;
 * - 5: 14/2041, durata piu' corta della categoria: si porta, con il dubbio;
 * - 6: 50/2041, oltre la partenza dichiarata: conflitto, non si porta;
 * - 7: bozza: non si porta;
 * - 8: categoria senza tipo nel nuovo albo: non si porta;
 * - 9: file assente dall'archivio: non si porta;
 * - 10: nessun allegato: non si porta;
 * - 11: inizio futuro: non si porta;
 * - 12: 300/2040, scaduto, di un anno che il nuovo albo non ha mai numerato: si porta.
 *
 * I valori attesi sono scritti per esteso: numeri, date, impronte dei file.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\AnalisiImportazione;
use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DatiProcedimento;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Importazione;
use AlboPretorioPa\LettoreBackup;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchermataImportazione;

use const AlboPretorioPa\META_ORIGINE_IMPORTAZIONE;
use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TIPO;

/**
 * Gli atti si scrivono davvero, una volta sola, tutto o niente.
 */
class ImportazioneVeraTest extends \WP_UnitTestCase {

	use FlussoDiProva;
	use BackupDiProva;

	/**
	 * L'istante dell'importazione: le 00:30 del 16 ottobre, ora del sito.
	 */
	private const ADESSO = '2041-10-16 00:30:00';

	/**
	 * La partenza dichiarata per il 2041 nel nuovo albo.
	 */
	private const PARTENZA = 40;

	/**
	 * Il contenuto dei file dell'archivio, per nome, e la sua impronta scritta per esteso.
	 */
	private const FILE = array(
		'delibera-10.pdf'      => array( '%PDF-1.4 delibera dieci', '675229d790e0f30fab385129a8ddde794e7379e2eea06cc0d3b33e9f10826e12' ),
		'avviso-11.pdf'        => array( '%PDF-1.4 avviso undici', 'd819c3840c7b63d56fa1a1784e2025db49d37eb5e5a7011c5f266c3fae91c166' ),
		'allegato-primo.pdf'   => array( '%PDF-1.4 allegato primo', '49347f4e4803d692f77c24f6d89a349746ad5919fcc803b43961f0dce880493a' ),
		'allegato-secondo.pdf' => array( '%PDF-1.4 allegato secondo', '2180db70ddb82e5fe57c73001e9318dffca4d834e7f34101aedb71c8b0b45be7' ),
		'annullata-13.pdf'     => array( '%PDF-1.4 annullata tredici', '4ff7035dcee8fa7b614974c3b86f2a581adede55156783533f7fa0c6f66bfe3d' ),
		'breve-14.pdf'         => array( '%PDF-1.4 breve quattordici', '55558e9fd2e0e36838f4c0bc9ce371a7de20266a0b5ed31fbc7f92e8500b158a' ),
		'anno-passato.pdf'     => array( '%PDF-1.4 anno passato', '5132dcdcd73e1932fc791211be7b70d6eff1a70d8d5f4ca7f5f47946175a2c72' ),
	);

	/**
	 * Le cartelle create dalla prova, da togliere.
	 *
	 * @var array<int, string>
	 */
	private array $cartelle = array();

	/**
	 * La seconda connessione alla banca dati, se una prova l'ha aperta.
	 *
	 * @var \wpdb|null
	 */
	private ?\wpdb $altra = null;

	/**
	 * Prepara il flusso, la partenza del 2041 e l'orologio.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();

		$this->assertTrue( Repertorio::dichiara( self::PARTENZA, $this->amministratore, Passaggi::ora() ), 'Precondizione: la partenza del 2041 si corregge.' );

		$this->fissa_orologi( self::ADESSO );
		set_current_screen( 'dashboard' );
		delete_option( Importazione::OPZIONE );
	}

	/**
	 * Ripristina lo stato.
	 */
	public function tear_down(): void {
		if ( null !== $this->altra ) {
			$this->altra->query( 'SELECT RELEASE_ALL_LOCKS()' );
			$this->altra->close();
			$this->altra = null;
		}

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

		delete_option( Importazione::OPZIONE );
		$GLOBALS['current_screen'] = null;
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Le righe degli atti del backup di prova.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function atti_di_prova(): array {
		$scaduto = array(
			'DataInizio' => '2041-09-01',
			'DataFine'   => '2041-09-16',
		);

		return array(
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'      => 1,
					'Numero'      => 10,
					'Oggetto'     => 'Delibera "dieci" & <prova>',
					'Riferimento' => 'Delibera 5/2041',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 2,
					'Numero'     => 11,
					'Oggetto'    => 'Avviso undici',
					'DataInizio' => '2041-10-01',
					'DataFine'   => '2041-10-16',
				)
			),
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'  => 3,
					'Numero'  => 12,
					'Oggetto' => 'Atto con due allegati',
				)
			),
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'             => 4,
					'Numero'             => 13,
					'Oggetto'            => 'Atto annullato',
					'DataAnnullamento'   => '2041-09-10',
					'MotivoAnnullamento' => 'Errore materiale',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 5,
					'Numero'     => 14,
					'Oggetto'    => 'Atto breve',
					'DataInizio' => '2041-09-01',
					'DataFine'   => '2041-09-11',
				)
			),
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'  => 6,
					'Numero'  => 50,
					'Oggetto' => 'Numero oltre la partenza',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'  => 7,
					'Numero'  => 0,
					'Oggetto' => 'Bozza',
				)
			),
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'      => 8,
					'Numero'      => 15,
					'Oggetto'     => 'Concorso senza tipo',
					'IdCategoria' => 7,
				)
			),
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'  => 9,
					'Numero'  => 16,
					'Oggetto' => 'File che manca',
				)
			),
			$this->atto_vecchio(
				$scaduto + array(
					'IdAtto'  => 10,
					'Numero'  => 17,
					'Oggetto' => 'Senza documenti',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 11,
					'Numero'     => 18,
					'Oggetto'    => 'Inizio futuro',
					'DataInizio' => '2041-10-20',
					'DataFine'   => '2041-11-04',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 12,
					'Numero'     => 300,
					'Anno'       => 2040,
					'Oggetto'    => 'Atto del 2040',
					'DataInizio' => '2040-09-03',
					'DataFine'   => '2040-09-18',
				)
			),
		);
	}

	/**
	 * Gli allegati del backup di prova.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function allegati_di_prova(): array {
		return array(
			$this->documento( 1, 1, 'delibera-10.pdf', 'D' ),
			$this->documento( 2, 2, 'avviso-11.pdf', 'D' ),
			$this->documento( 3, 3, 'allegato-primo.pdf', 'A' ),
			$this->documento( 4, 3, 'allegato-secondo.pdf', 'A' ),
			$this->documento( 5, 4, 'annullata-13.pdf', 'D' ),
			$this->documento( 6, 5, 'breve-14.pdf', 'D' ),
			$this->documento( 7, 6, 'delibera-10.pdf', 'D' ),
			$this->documento( 8, 8, 'delibera-10.pdf', 'D' ),
			$this->allegato_vecchio(
				array(
					'IdAllegato' => 9,
					'IdAtto'     => 9,
					'Allegato'   => '/var/www/vecchio/wp-content/uploads/AllegatiAttiAlboPretorio/assente.pdf',
					'Impronta'   => hash( 'sha256', 'assente' ),
				)
			),
			$this->documento( 10, 11, 'delibera-10.pdf', 'D' ),
			$this->documento( 11, 12, 'anno-passato.pdf', 'D' ),
		);
	}

	/**
	 * Un allegato del backup con il file nell'archivio.
	 *
	 * @param int    $id     Numero interno dell'allegato.
	 * @param int    $atto   Numero interno dell'atto.
	 * @param string $nome   Nome del file.
	 * @param string $natura D o A.
	 * @return array<string, mixed>
	 */
	private function documento( int $id, int $atto, string $nome, string $natura ): array {
		return $this->allegato_vecchio(
			array(
				'IdAllegato'     => $id,
				'IdAtto'         => $atto,
				'TitoloAllegato' => 'Titolo di ' . $nome,
				'Allegato'       => '/var/www/vecchio/wp-content/uploads/AllegatiAttiAlboPretorio/' . $nome,
				'Impronta'       => self::FILE[ $nome ][1],
				'Natura'         => $natura,
			)
		);
	}

	/**
	 * Scrive il backup nella cartella del vecchio albo, con le tabelle indicate.
	 *
	 * @param array<int, array<string, mixed>>|null $atti     Righe degli atti, quelle di prova se nulle.
	 * @param array<int, array<string, mixed>>|null $allegati Righe degli allegati, quelle di prova se nulle.
	 * @param string                                $nome     Nome del file nella cartella.
	 * @return string Percorso del backup.
	 */
	private function backup_nella_cartella( ?array $atti = null, ?array $allegati = null, string $nome = 'backup.zip' ): string {
		$tabelle = $this->tabelle_vecchie( $atti ?? $this->atti_di_prova(), $allegati ?? $this->allegati_di_prova() );

		$tabelle['albopretorio_categorie'][] = array(
			'IdCategoria' => 7,
			'Nome'        => 'Concorso',
			'Descrizione' => 'Concorsi',
			'Genitore'    => 0,
			'Giorni'      => 30,
		);

		$file = array();

		foreach ( self::FILE as $nome_file => $dati ) {
			$file[ $nome_file ] = $dati[0];
		}

		$zip      = $this->zip_vecchio( $this->sql_vecchio( $tabelle ), $file );
		$cartella = SchermataImportazione::cartella();

		$this->crea_cartella( $cartella );
		$this->assertTrue( copy( $zip, $cartella . '/' . $nome ) );

		return $cartella . '/' . $nome;
	}

	/**
	 * Il rapporto di un backup, con lo stato attuale del nuovo albo.
	 *
	 * @param string $percorso Backup.
	 * @return array<string, mixed>
	 */
	private function rapporto_di( string $percorso ): array {
		$rapporto = Importazione::rapporto( $percorso );

		$this->assertIsArray( $rapporto, is_wp_error( $rapporto ) ? $rapporto->get_error_message() : '' );

		return $rapporto;
	}

	/**
	 * L'invio del modulo dell'importazione, con i numeri del rapporto attuale.
	 *
	 * @param string               $percorso Backup.
	 * @param array<string, mixed> $campi    Campi da aggiungere o cambiare.
	 * @return array<string, mixed>
	 */
	private function invio_avvia( string $percorso, array $campi = array() ): array {
		$rapporto = $this->rapporto_di( $percorso );

		return array_merge(
			array(
				SchermataImportazione::CAMPO_GETTONE_IMPORTA => wp_create_nonce( SchermataImportazione::AZIONE_IMPORTA ),
				SchermataImportazione::CAMPO_OPERAZIONE => 'avvia',
				SchermataImportazione::CAMPO_PRESENTE   => wp_basename( $percorso ),
				SchermataImportazione::CAMPO_IMPRONTA   => hash_file( 'sha256', $percorso ),
				SchermataImportazione::CAMPO_ATTI       => (string) count( Importazione::selezione( $rapporto, false )['atti'] ),
				SchermataImportazione::CAMPO_VISIBILI   => (string) Importazione::selezione( $rapporto, true )['in_vista'],
				SchermataImportazione::CAMPO_CONFERMA   => '1',
			),
			$campi
		);
	}

	/**
	 * L'invio di un'operazione su un'importazione avviata.
	 *
	 * @param string $operazione Continua o interrompi.
	 * @return array<string, string>
	 */
	private function invio( string $operazione ): array {
		return array(
			SchermataImportazione::CAMPO_GETTONE_IMPORTA => wp_create_nonce( SchermataImportazione::AZIONE_IMPORTA ),
			SchermataImportazione::CAMPO_OPERAZIONE      => $operazione,
		);
	}

	/**
	 * Gli atti del nuovo albo che ricordano un'origine, per origine, letti dalla banca dati.
	 *
	 * @return array<string, array{id: int, stato: string}>
	 */
	private function importati(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge cio' che c'e' davvero nella banca dati.
		$righe = $wpdb->get_results( $wpdb->prepare( 'SELECT m.meta_value AS origine, p.ID AS id, p.post_status AS stato FROM %i m JOIN %i p ON p.ID = m.post_id WHERE m.meta_key = %s ORDER BY p.ID', $wpdb->postmeta, $wpdb->posts, META_ORIGINE_IMPORTAZIONE ), ARRAY_A );
		$atti  = array();

		foreach ( $righe as $riga ) {
			$this->assertArrayNotHasKey( $riga['origine'], $atti, 'Un solo atto per origine: ' . $riga['origine'] );

			$atti[ (string) $riga['origine'] ] = array(
				'id'    => (int) $riga['id'],
				'stato' => (string) $riga['stato'],
			);
		}

		ksort( $atti, SORT_NATURAL );

		return $atti;
	}

	/**
	 * Le righe degli anni della numerazione, lette dalla banca dati.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function anni(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge cio' che c'e' davvero nella banca dati.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY anno', Repertorio::tabella_anni() ), ARRAY_A );
	}

	/**
	 * Le impronte dei documenti dell'atto, principale per primo, lette dal disco.
	 *
	 * @param int $atto_id Atto.
	 * @return array{principale: string|null, ulteriori: array<int, string>}
	 */
	private function impronte_dei_documenti( int $atto_id ): array {
		$principale = DocumentiAtto::principale( $atto_id );
		$impronta   = static function ( int $allegato ): string {
			return (string) hash_file( 'sha256', (string) get_attached_file( $allegato ) );
		};

		return array(
			'principale' => null === $principale ? null : $impronta( $principale ),
			'ulteriori'  => array_map( $impronta, DocumentiAtto::ulteriori( $atto_id ) ),
		);
	}

	/**
	 * La voce di registro dell'importazione di un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function voce_importazione( int $atto_id ): array {
		$voci = $this->voci_di(
			$atto_id,
			array(
				'sezione' => SEZIONE,
				'azione'  => 'importazione',
			)
		);

		$this->assertCount( 1, $voci, 'Una voce di importazione per atto.' );

		return $voci[0];
	}

	/**
	 * Avvia dalla schermata e porta tutto, un blocco dopo l'altro.
	 *
	 * @param string               $percorso Backup.
	 * @param array<string, mixed> $campi    Campi da aggiungere o cambiare.
	 * @return array<string, mixed> Stato finale.
	 */
	private function importa_tutto( string $percorso, array $campi = array() ): array {
		$stato = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso, $campi ) );

		$this->assertIsArray( $stato, is_wp_error( $stato ) ? $stato->get_error_message() : '' );

		for ( $giri = 0; 'in_corso' === $stato['stato'] && $giri < 50; ++$giri ) {
			$stato = SchermataImportazione::importazione_da_invio( $this->invio( 'continua' ) );

			$this->assertIsArray( $stato, is_wp_error( $stato ) ? $stato->get_error_message() : '' );
		}

		$this->assertSame( 'finita', $stato['stato'] );

		return $stato;
	}

	/**
	 * I codici dei motivi dei non portati, con i numeri degli atti.
	 *
	 * @param array<string, mixed> $stato Stato.
	 * @return array<string, array<int, string>>
	 */
	private function non_portati( array $stato ): array {
		$codici = array();

		foreach ( $stato['non_portati'] as $chiave => $gruppo ) {
			$codice = strtok( (string) $chiave, ':' );

			$codici[ $codice ] = array_merge( $codici[ $codice ] ?? array(), $gruppo['atti'] );
		}

		ksort( $codici );

		return $codici;
	}

	/**
	 * A-210. Gli atti portabili si scrivono con i valori del vecchio albo.
	 */
	public function test_a210_atti_scritti_come_nel_vecchio_albo(): void {
		$percorso = $this->backup_nella_cartella();
		$stato    = $this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );
		$atti     = $this->importati();

		$this->assertSame( array( '1', '2', '3', '4', '5', '12' ), array_map( 'strval', array_keys( $atti ) ), 'Si portano esattamente gli atti portabili.' );

		foreach ( $atti as $origine => $atto ) {
			$this->assertSame( 'publish', $atto['stato'], 'Pubblicato: ' . $origine );
		}

		$attesi = array(
			'1'  => array(
				'Delibera "dieci" & <prova>',
				'2041-09-01 00:00:00',
				'2041-08-31 22:00:00',
				array(
					'anno'   => 2041,
					'numero' => 10,
				),
				'2041-09-16',
				'Delibera 5/2041',
			),
			'2'  => array(
				'Avviso undici',
				'2041-10-01 00:00:00',
				'2041-09-30 22:00:00',
				array(
					'anno'   => 2041,
					'numero' => 11,
				),
				'2041-10-16',
				'',
			),
			'3'  => array(
				'Atto con due allegati',
				'2041-09-01 00:00:00',
				'2041-08-31 22:00:00',
				array(
					'anno'   => 2041,
					'numero' => 12,
				),
				'2041-09-16',
				'',
			),
			'4'  => array(
				'Atto annullato',
				'2041-09-01 00:00:00',
				'2041-08-31 22:00:00',
				array(
					'anno'   => 2041,
					'numero' => 13,
				),
				'2041-09-16',
				'',
			),
			'5'  => array(
				'Atto breve',
				'2041-09-01 00:00:00',
				'2041-08-31 22:00:00',
				array(
					'anno'   => 2041,
					'numero' => 14,
				),
				'2041-09-11',
				'',
			),
			'12' => array(
				'Atto del 2040',
				'2040-09-03 00:00:00',
				'2040-09-02 22:00:00',
				array(
					'anno'   => 2040,
					'numero' => 300,
				),
				'2040-09-18',
				'',
			),
		);

		foreach ( $attesi as $origine => $atteso ) {
			$id = $atti[ $origine ]['id'];

			clean_post_cache( $id );

			$this->assertSame( $atteso[0], get_post( $id )->post_title, 'Oggetto alla lettera: ' . $origine );
			$this->assertSame( $atteso[1], get_post( $id )->post_date, 'Inizio a mezzanotte, ora del sito: ' . $origine );
			$this->assertSame( $atteso[2], get_post( $id )->post_date_gmt, 'Inizio in tempo universale: ' . $origine );
			$this->assertSame( $this->amministratore, (int) get_post( $id )->post_author, 'Autore chi importa: ' . $origine );
			$this->assertSame( $atteso[3], Repertorio::di( $id ), 'Numero e anno del vecchio albo: ' . $origine );
			$this->assertSame( $atteso[4], conformita_core_fine_pubblicazione( $id ), 'Fine del vecchio albo: ' . $origine );
			$this->assertSame( 'Deliberazione', DatiAtto::tipo( $id )->name, 'Tipo con il nome della categoria: ' . $origine );
			$this->assertSame( $atteso[5], DatiAtto::numero_proprio( $id ), 'Numero proprio dal riferimento: ' . $origine );
		}

		// Lo scaduto non si vede, quello in corso si'.
		foreach ( array( '1', '3', '4', '5', '12' ) as $origine ) {
			$this->assertTrue( conformita_core_scaduto( $atti[ $origine ]['id'] ), 'Scaduto: ' . $origine );
		}

		$this->assertFalse( conformita_core_scaduto( $atti['2']['id'] ), 'In pubblicazione fino a tutto il 16.' );

		// Una lettura del sito pubblico, da visitatore.
		$GLOBALS['current_screen'] = null;
		wp_set_current_user( 0 );
		$visibili = get_posts(
			array(
				'post_type'        => TIPO,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);
		wp_set_current_user( $this->amministratore );

		$this->assertSame( array( $atti['2']['id'] ), array_map( 'intval', $visibili ), 'Il pubblico vede solo l\'atto in corso.' );

		// La voce di registro: numero, date, durata con la sua origine, origine dell'atto, dubbi portati.
		$voce = $this->voce_importazione( $atti['5']['id'] );

		$this->assertSame( 2041, $voce['dettagli']['anno'] );
		$this->assertSame( 14, $voce['dettagli']['numero'] );
		$this->assertSame( '2041-09-01', $voce['dettagli']['inizio'] );
		$this->assertSame( '2041-09-11', $voce['dettagli']['fine'] );
		$this->assertSame( '5', $voce['dettagli']['origine'] );
		$this->assertSame( 'backup.zip', $voce['dettagli']['backup'] );
		$this->assertSame( hash_file( 'sha256', $percorso ), $voce['dettagli']['impronta_backup'] );
		$this->assertSame( 10, $voce['dettagli']['durata_giorni'] );
		$this->assertSame( 'date_scritte_nel_vecchio_albo', $voce['dettagli']['origine_durata'] );
		$this->assertSame( array( 'durata_breve' ), $voce['dettagli']['dubbi_portati'] );
		$this->assertFalse( $voce['dettagli']['conferma_dati_personali'] );
		$this->assertFalse( $voce['dettagli']['principale_da_scegliere'] );

		$annullato = $this->voce_importazione( $atti['4']['id'] );

		$this->assertSame( '2041-09-10', $annullato['dettagli']['annullamento'] );
		$this->assertSame( 'Errore materiale', $annullato['dettagli']['motivo_annullamento'] );
		$this->assertContains( 'annullato', $annullato['dettagli']['dubbi_portati'] );

		$in_corso = $this->voce_importazione( $atti['2']['id'] );

		$this->assertTrue( $in_corso['dettagli']['conferma_dati_personali'], 'La conferma data da chi importa e\' nel registro.' );
		$this->assertContains( 'conferma_dati_personali', $in_corso['dettagli']['dubbi_portati'] );

		// Il rapporto finale.
		$this->assertSame( 12, $stato['nel_backup'] );
		$this->assertSame( 12, $stato['analizzati'] );
		$this->assertCount( 6, $stato['importati'] );
		$this->assertSame( array(), $stato['non_riusciti'] );
		$this->assertSame( array( '12/2041' ), $stato['da_scegliere'] );
	}

	/**
	 * A-211. Gli altri atti non si portano, e il rapporto finale dice perche'; i visibili solo con la seconda conferma.
	 */
	public function test_a211_atti_non_portati_e_conferma_dei_visibili(): void {
		$percorso = $this->backup_nella_cartella();
		$rapporto = $this->rapporto_di( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12' ), Importazione::selezione( $rapporto, false )['atti'], 'Senza la seconda conferma, l\'atto in corso resta fuori.' );
		$this->assertSame( array( '1', '2', '3', '4', '5', '12' ), Importazione::selezione( $rapporto, true )['atti'] );
		$this->assertSame( 0, Importazione::selezione( $rapporto, false )['in_vista'] );
		$this->assertSame( 1, Importazione::selezione( $rapporto, true )['in_vista'] );

		$stato = $this->importa_tutto( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame(
			array(
				'bozza'                   => array( 'bozza (numero interno 7)' ),
				'conferma_dati_personali' => array( '11/2041' ),
				'file_mancante'           => array( '16/2041' ),
				'inizio_futuro'           => array( '18/2041' ),
				'numero_oltre_partenza'   => array( '50/2041' ),
				'senza_documenti'         => array( '17/2041' ),
				'tipo_da_creare'          => array( '15/2041' ),
			),
			$this->non_portati( $stato )
		);

		// Il modulo sotto il rapporto mostra le due caselle, separate.
		$_POST = array(
			SchermataImportazione::CAMPO_GETTONE  => wp_create_nonce( SchermataImportazione::AZIONE ),
			SchermataImportazione::CAMPO_PRESENTE => 'backup.zip',
		);

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . SchermataImportazione::CAMPO_CONFERMA . '"', $pagina );
		$this->assertStringContainsString( 'name="' . SchermataImportazione::CAMPO_CONFERMA_VISIBILI . '"', $pagina );
		$this->assertStringContainsString( 'data-atti="0" data-in-vista="1"', $pagina, 'Dopo l\'importazione resta da portare solo l\'atto in corso.' );
		$this->assertStringContainsString( 'Importati 5 atti su 12 del backup.', $pagina );

		// L'atto in corso arriva con la seconda conferma, al rilancio.
		$secondo = $this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$this->assertSame( array( '1', '2', '3', '4', '5', '12' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertCount( 1, $secondo['importati'] );
		$this->assertSame( 5, $secondo['gia_importati'] );
	}

	/**
	 * A-212. Rilanciare non crea doppioni; una bozza lasciata a meta' si rifa'.
	 */
	public function test_a212_rilancio_senza_doppioni(): void {
		$percorso = $this->backup_nella_cartella();

		$this->importa_tutto( $percorso );

		$prima    = $this->importati();
		$rapporto = $this->rapporto_di( $percorso );

		$this->assertSame( 5, $rapporto['riepilogo']['esiti'][ AnalisiImportazione::IMPORTATO ], 'Il rapporto riconosce gli atti gia\' importati.' );
		$this->assertSame( array(), Importazione::selezione( $rapporto, false )['atti'], 'Niente da portare senza la seconda conferma.' );

		$vuota = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso ) );

		$this->assertWPError( $vuota );
		$this->assertSame( 'albo_importazione_vuota', $vuota->get_error_code() );
		$this->assertSame( $prima, $this->importati(), 'Il rilancio non ha creato niente.' );

		// Una bozza lasciata da un blocco interrotto, con il ricordo dell'origine e un documento.
		$bozza = self::factory()->post->create(
			array(
				'post_type'   => TIPO,
				'post_status' => 'draft',
				'post_title'  => 'Bozza lasciata',
			)
		);
		add_post_meta( $bozza, META_ORIGINE_IMPORTAZIONE, '2', true );
		$documento = $this->deposita_documento( $bozza );
		$sul_disco = (string) get_attached_file( $documento );

		$this->assertFileExists( $sul_disco, 'Precondizione: il file della bozza lasciata e\' sul disco.' );

		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$dopo = $this->importati();

		$this->assertNull( get_post( $bozza ), 'La bozza lasciata e\' stata cancellata.' );
		$this->assertNull( get_post( $documento ), 'Il suo documento e\' stato cancellato.' );
		$this->assertFileDoesNotExist( $sul_disco, 'Il suo file non c\'e\' piu\'.' );
		$this->assertSame( 'publish', $dopo['2']['stato'] );
		$this->assertCount( 6, $dopo );

		foreach ( $prima as $origine => $atto ) {
			$this->assertSame( $atto, $dopo[ $origine ], 'Un atto gia\' importato non si tocca: ' . $origine );
		}
	}

	/**
	 * A-213. La numerazione non riparte: la riga dell'anno non si tocca, e la partenza non scende sotto gli importati.
	 */
	public function test_a213_numerazione(): void {
		$percorso = $this->backup_nella_cartella();
		$anni     = $this->anni();

		$this->assertSame( array( '2041' ), array_column( $anni, 'anno' ), 'Precondizione: solo il 2041 ha una riga.' );
		$this->assertSame( '40', $anni[0]['partenza'] );

		$this->importa_tutto( $percorso );

		$this->assertSame( $anni, $this->anni(), 'Le righe degli anni sono identiche: il 2040 non ne ha una, il 2041 e\' com\'era.' );
		$this->assertSame( 41, Repertorio::prossimo( 2041 ), 'Il prossimo atto pubblicato nel 2041 prende 41.' );
		$this->assertSame( 1, Repertorio::prossimo( 2042 ), 'L\'anno dopo riparte da 1, come deve: il 2041 e\' tenuto qui.' );

		// La partenza non scende sotto un numero importato.
		$sotto = Repertorio::dichiara( 13, $this->amministratore, Passaggi::ora() );

		$this->assertWPError( $sotto );
		$this->assertSame( 'albo_repertorio_partenza_sotto_importati', $sotto->get_error_code() );
		$this->assertTrue( Repertorio::dichiara( 14, $this->amministratore, Passaggi::ora() ), 'Il numero importato piu\' alto e\' 14: la partenza 14 si accetta.' );

		// Il numero si scrive solo entro la partenza, controllata al momento della scrittura.
		$bozza = self::factory()->post->create(
			array(
				'post_type'   => TIPO,
				'post_status' => 'draft',
			)
		);
		$oltre = Repertorio::registra_importato( $bozza, 2041, 15 );

		$this->assertWPError( $oltre );
		$this->assertSame( 'albo_repertorio_importato_oltre_partenza', $oltre->get_error_code() );
		$this->assertNull( Repertorio::di( $bozza ) );

		$doppio = Repertorio::registra_importato( $bozza, 2041, 10 );

		$this->assertWPError( $doppio );
		$this->assertSame( 'albo_repertorio_importato_gia_dato', $doppio->get_error_code() );
		$this->assertNull( Repertorio::di( $bozza ) );
	}

	/**
	 * A-214. A blocchi, un'importazione alla volta, con la conferma e lo stesso rapporto confermato.
	 */
	public function test_a214_blocchi_turno_e_conferme(): void {
		$percorso = $this->backup_nella_cartella();

		// Senza conferma non parte, e non scrive niente.
		$senza = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso, array( SchermataImportazione::CAMPO_CONFERMA => '' ) ) );

		$this->assertSame( 'albo_importazione_non_confermata', $senza->get_error_code() );
		$this->assertNull( Importazione::stato() );

		// Numeri diversi da quelli del rapporto: chi importa ha confermato un'altra cosa.
		foreach ( array(
			'atti'     => array( SchermataImportazione::CAMPO_ATTI => '6' ),
			'in_vista' => array( SchermataImportazione::CAMPO_VISIBILI => '0' ),
			'impronta' => array( SchermataImportazione::CAMPO_IMPRONTA => str_repeat( 'a', 64 ) ),
		) as $cosa => $campi ) {
			$cambiato = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso, $campi ) );

			$this->assertSame( 'albo_importazione_rapporto_cambiato', $cambiato->get_error_code(), $cosa );
			$this->assertNull( Importazione::stato(), $cosa );
		}

		// Un blocco porta al massimo gli atti dati, e sempre almeno uno.
		$this->assertIsArray(
			Importazione::avvia(
				$percorso,
				'backup.zip',
				array(
					'impronta' => hash_file( 'sha256', $percorso ),
					'atti'     => 5,
					'in_vista' => 1,
				),
				true,
				false
			)
		);

		$stato = Importazione::prosegui( 2, microtime( true ) + 60 );

		$this->assertSame( 'in_corso', $stato['stato'] );
		$this->assertSame( 2, $stato['posizione'] );
		$this->assertSame( array( '1', '3' ), array_map( 'strval', array_keys( $this->importati() ) ) );

		$stato = Importazione::prosegui( 5, microtime( true ) - 1 );

		$this->assertSame( 3, $stato['posizione'], 'A tempo scaduto il blocco porta comunque un atto.' );

		// Un'importazione alla volta.
		$seconda = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso ) );

		$this->assertSame( 'albo_importazione_in_corso', $seconda->get_error_code() );

		// Un'altra richiesta che lavora all'importazione: il blocco si ritira senza toccare niente.
		$this->altra = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->assertSame( '1', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Importazione::nome_turno() ) ) );

		$occupata = Importazione::prosegui( 5, microtime( true ) + 60 );

		$this->assertSame( 'albo_importazione_occupata', $occupata->get_error_code() );
		$this->assertSame( 3, Importazione::stato()['posizione'] );

		$this->altra->query( $this->altra->prepare( 'SELECT RELEASE_LOCK( %s )', Importazione::nome_turno() ) );

		// Il backup cambiato fra un blocco e l'altro ferma l'importazione.
		$originale = (string) file_get_contents( $percorso );

		file_put_contents( $percorso, $originale . ' ' );
		$cambiato = Importazione::prosegui( 5, microtime( true ) + 60 );
		file_put_contents( $percorso, $originale );

		$this->assertSame( 'albo_importazione_backup_cambiato', $cambiato->get_error_code() );
		$this->assertSame( 3, Importazione::stato()['posizione'] );

		// Interrompere chiude l'importazione; gli atti importati restano.
		$interrotta = SchermataImportazione::importazione_da_invio( $this->invio( 'interrompi' ) );

		$this->assertSame( 'interrotta', $interrotta['stato'] );
		$this->assertSame( array( '1', '3', '4' ), array_map( 'strval', array_keys( $this->importati() ) ) );

		$nessuna = SchermataImportazione::importazione_da_invio( $this->invio( 'continua' ) );

		$this->assertSame( 'albo_importazione_nessuna', $nessuna->get_error_code() );

		// Dopo l'interruzione se ne avvia un'altra, che porta il resto.
		$finale = $this->importa_tutto( $percorso );

		$this->assertSame( 3, $finale['gia_importati'] );
		$this->assertSame( array( '1', '3', '4', '5', '12' ), array_map( 'strval', array_keys( $this->importati() ) ) );
	}

	/**
	 * A-215. Tutto o niente per ogni atto: un guasto toglie quell'atto intero, e gli altri proseguono.
	 */
	public function test_a215_tutto_o_niente_per_atto(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();
		$disco    = $this->documenti_sul_disco();

		// Un guasto nella scrittura del numero dell'atto 12/2041, dentro la transazione.
		$guasto = static function ( $query ) {
			if ( false !== stripos( (string) $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', (string) $query ) ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $query;
		};

		// Un'eccezione nella preparazione dell'atto 13/2041, dopo il ricordo dell'origine.
		$eccezione = static function ( $meta_id, $atto_id, $chiave, $valore ) {
			unset( $meta_id, $atto_id );

			if ( META_ORIGINE_IMPORTAZIONE === $chiave && '4' === $valore ) {
				throw new \RuntimeException( 'guasto di prova' );
			}
		};

		add_filter( 'query', $guasto );
		add_action( 'added_post_meta', $eccezione, 10, 4 );

		$stato = $this->importa_tutto( $percorso );

		remove_filter( 'query', $guasto );
		remove_action( 'added_post_meta', $eccezione, 10 );

		$this->assertSame( array( '1', '5', '12' ), array_map( 'strval', array_keys( $this->importati() ) ), 'Gli atti guasti non esistono; quelli dopo si sono importati.' );
		$this->assertSame( array( '12/2041', '13/2041' ), array_column( $stato['non_riusciti'], 'numero' ) );
		$this->assertSame( array( 'albo_repertorio_importato_non_scritto', 'albo_importazione_eccezione' ), array_column( $stato['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'guasto di prova', $stato['non_riusciti'][1]['testo'] );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 12 ), 'Il numero 12/2041 e\' libero.' );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 13 ), 'Il numero 13/2041 e\' libero.' );

		// Sul disco ci sono solo i file degli atti importati.
		$impronte = array_map(
			static function ( string $file ): string {
				return (string) hash_file( 'sha256', $file );
			},
			array_values( array_diff( $this->documenti_sul_disco(), $disco ) )
		);
		sort( $impronte );

		$attese = array( self::FILE['delibera-10.pdf'][1], self::FILE['breve-14.pdf'][1], self::FILE['anno-passato.pdf'][1] );
		sort( $attese );

		$this->assertSame( $attese, $impronte );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: nessun allegato orfano nella banca dati.
		$this->assertSame( 3, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' ) ) );

		// Rilanciato senza guasti, porta i due atti rimasti.
		$this->importa_tutto( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12' ), array_map( 'strval', array_keys( $this->importati() ) ) );
	}

	/**
	 * A-216. Permessi, gettone e solo backup della cartella.
	 */
	public function test_a216_permessi_e_gettone(): void {
		$percorso = $this->backup_nella_cartella();
		$invio    = $this->invio_avvia( $percorso );

		foreach ( array(
			'redattore' => $this->redattore,
			'abbonato'  => self::factory()->user->create( array( 'role' => 'subscriber' ) ),
		) as $chi => $utente ) {
			wp_set_current_user( $utente );

			$this->assertSame( 'albo_importazione_permesso', SchermataImportazione::importazione_da_invio( $invio )->get_error_code(), $chi );
			$this->assertSame( 'albo_importazione_permesso', Importazione::avvia( $percorso, 'backup.zip', array(), true, true )->get_error_code(), $chi );
			$this->assertSame( 'albo_importazione_permesso', Importazione::prosegui( 1, microtime( true ) + 60 )->get_error_code(), $chi );
			$this->assertSame( 'albo_importazione_permesso', Importazione::interrompi()->get_error_code(), $chi );
		}

		wp_set_current_user( $this->amministratore );

		$this->assertSame( 'albo_importazione_gettone', SchermataImportazione::importazione_da_invio( array( SchermataImportazione::CAMPO_GETTONE_IMPORTA => 'sbagliato' ) + $invio )->get_error_code() );
		$this->assertSame( 'albo_importazione_gettone', SchermataImportazione::importazione_da_invio( array( SchermataImportazione::CAMPO_GETTONE_IMPORTA => wp_create_nonce( SchermataImportazione::AZIONE ) ) + $invio )->get_error_code(), 'Il gettone della lettura non vale per l\'importazione.' );
		$this->assertSame( 'albo_importazione_operazione', SchermataImportazione::importazione_da_invio( array( SchermataImportazione::CAMPO_OPERAZIONE => 'cancella' ) + $invio )->get_error_code() );

		foreach ( array( '../backup.zip', 'altro.zip', '' ) as $nome ) {
			$this->assertSame( 'albo_importazione_nessuno', SchermataImportazione::importazione_da_invio( array( SchermataImportazione::CAMPO_PRESENTE => $nome ) + $invio )->get_error_code(), $nome );
		}

		$this->assertNull( Importazione::stato(), 'Nessun rifiuto ha avviato niente.' );
		$this->assertSame( array(), $this->importati() );

		// Il modulo dell'importazione c'e' sotto il rapporto di un backup della cartella, e non sotto quello di un file caricato.
		$_POST = array(
			SchermataImportazione::CAMPO_GETTONE  => wp_create_nonce( SchermataImportazione::AZIONE ),
			SchermataImportazione::CAMPO_PRESENTE => 'backup.zip',
		);

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . SchermataImportazione::CAMPO_GETTONE_IMPORTA . '"', $pagina );
		$this->assertStringContainsString( 'value="' . hash_file( 'sha256', $percorso ) . '"', $pagina );

		$_FILES = array(
			SchermataImportazione::CAMPO_FILE => array(
				'name'     => 'backup.zip',
				'tmp_name' => $percorso,
				'error'    => UPLOAD_ERR_OK,
				'size'     => 10,
			),
		);

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'name="' . SchermataImportazione::CAMPO_GETTONE_IMPORTA . '"', $pagina );
	}

	/**
	 * A-217. I documenti arrivano byte per byte, il principale al suo posto; il rapporto finale si legge sulla schermata.
	 */
	public function test_a217_documenti_intatti_e_rapporto_finale(): void {
		$percorso = $this->backup_nella_cartella();

		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$atti = $this->importati();

		$this->assertSame(
			array(
				'principale' => '675229d790e0f30fab385129a8ddde794e7379e2eea06cc0d3b33e9f10826e12',
				'ulteriori'  => array(),
			),
			$this->impronte_dei_documenti( $atti['1']['id'] )
		);
		$this->assertSame(
			array(
				'principale' => null,
				'ulteriori'  => array( '49347f4e4803d692f77c24f6d89a349746ad5919fcc803b43961f0dce880493a', '2180db70ddb82e5fe57c73001e9318dffca4d834e7f34101aedb71c8b0b45be7' ),
			),
			$this->impronte_dei_documenti( $atti['3']['id'] ),
			'Nessun documento principale indovinato: tutti allegati, nell\'ordine del backup.'
		);
		$this->assertSame( '%PDF-1.4 avviso undici', file_get_contents( (string) get_attached_file( (int) DocumentiAtto::principale( $atti['2']['id'] ) ) ) );
		$this->assertSame( 'avviso-11.pdf', wp_basename( (string) get_attached_file( (int) DocumentiAtto::principale( $atti['2']['id'] ) ) ), 'Il nome del file non si tocca.' );

		$voce = $this->voce_importazione( $atti['3']['id'] );

		$this->assertTrue( $voce['dettagli']['principale_da_scegliere'] );
		$this->assertSame( array( 'Titolo di allegato-primo.pdf', 'Titolo di allegato-secondo.pdf' ), $voce['dettagli']['documenti_titoli'] );
		$this->assertSame( array( 'ulteriore', 'ulteriore' ), $voce['dettagli']['documenti_ruoli'] );
		$this->assertSame( array( '49347f4e4803d692f77c24f6d89a349746ad5919fcc803b43961f0dce880493a', '2180db70ddb82e5fe57c73001e9318dffca4d834e7f34101aedb71c8b0b45be7' ), $voce['dettagli']['documenti_impronte'] );

		// Il rapporto finale sulla schermata, con i testi del backup protetti.
		$_POST = array();

		ob_start();
		SchermataImportazione::mostra();
		$pagina = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-stato="finita"', $pagina );
		$this->assertStringContainsString( 'Importati 6 atti su 12 del backup.', $pagina );
		$this->assertStringContainsString( 'data-codice="da_scegliere"', $pagina );
		$this->assertStringContainsString( 'data-codice="tipo_da_creare"', $pagina );
		$this->assertStringContainsString( '15/2041', $pagina );
		$this->assertStringNotContainsString( 'name="' . SchermataImportazione::CAMPO_OPERAZIONE . '" value="continua"', $pagina, 'A importazione finita non si continua.' );
	}

	/**
	 * Crea una cartella, e le sue madri, da togliere a fine prova.
	 *
	 * @param string $cartella Cartella.
	 */
	private function crea_cartella( string $cartella ): void {
		$madri    = array();
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
}
