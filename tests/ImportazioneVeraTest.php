<?php
/**
 * L'importazione vera dal backup di Albo Pretorio On line: righe A-210..A-217 (ALBO-35).
 *
 * Il backup di prova sta nella cartella di Albo Pretorio On line, come sul sito vero.
 * L'orologio e' fissato a mezzanotte e mezza del 16 ottobre 2041 nel fuso del
 * sito: l'atto con fine il 16 e' ancora in pubblicazione, quelli con fine a
 * settembre sono scaduti. La partenza dichiarata per il 2041 e' 40.
 *
 * Gli atti del backup, per numero interno:
 *
 * - 1: 10/2041, scaduto, un documento: si porta;
 * - 2: 11/2041, in pubblicazione: si porta solo con la conferma separata;
 * - 3: 12/2041, scaduto, due allegati e nessun documento: si porta, principale da scegliere;
 * - 4: 13/2041, annullato e scaduto: si porta annullato, con l'annullamento nel registro;
 * - 5: 14/2041, durata piu' corta della categoria: si porta, con il dubbio;
 * - 6: 50/2041, oltre la partenza dichiarata: conflitto, non si porta;
 * - 7: bozza: non si porta;
 * - 8: categoria senza tipo in questo albo: non si porta;
 * - 9: file assente dall'archivio: non si porta;
 * - 10: nessun allegato: non si porta;
 * - 11: inizio futuro: non si porta;
 * - 12: 300/2040, scaduto, di un anno che questo albo non ha mai numerato: si porta;
 * - 13: 19/2041, annullato e ancora in pubblicazione: si porta annullato, solo con la conferma separata;
 * - 14: 301/2040, scaduto, un file solo segnato come allegato: si porta, con quel file come principale.
 *
 * L'atto 1 ha il richiedente, che arriva fra i dati del procedimento.
 *
 * I valori attesi sono scritti per esteso: numeri, date, impronte dei file.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\AnalisiImportazione;
use AlboPretorioPa\ChiusuraPubblicazione;
use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DatiProcedimento;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Importazione;
use AlboPretorioPa\LettoreBackup;
use AlboPretorioPa\PaginaPubblica;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\RiquadroPassaggi;
use AlboPretorioPa\SchermataScaduti;
use AlboPretorioPa\SchermataImportazione;

use const AlboPretorioPa\META_ALLEGATI_ULTERIORI;
use const AlboPretorioPa\META_NUMERO_PROPRIO;
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
	 * Gli atti annullati in Albo Pretorio On line, per numero interno: arrivano annullati.
	 *
	 * @var array<int, string>
	 */
	const ANNULLATI = array( '4', '13' );

	/**
	 * L'istante dell'importazione: le 00:30 del 16 ottobre, ora del sito.
	 */
	private const ADESSO = '2041-10-16 00:30:00';

	/**
	 * La partenza dichiarata per il 2041 in questo albo.
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
		'unico-301.pdf'        => array( '%PDF-1.4 unico trecentouno', '089b6105cc06fe6f522472a4788bc30cbc79fd12742a8f55a554bf81d1a2875d' ),
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

		// La prova gira dentro la transazione della suite, che annulla tutto alla fine: qui l'importazione e' ammessa.
		$this->ammetti_transazione_esterna( true );
	}

	/**
	 * Ammette o no l'importazione dentro la transazione della suite di prova.
	 *
	 * @param bool $ammessa Se ammessa.
	 */
	private function ammetti_transazione_esterna( bool $ammessa ): void {
		$valore = new \ReflectionProperty( Importazione::class, 'dentro_altra_transazione' );
		$valore->setAccessible( true );
		$valore->setValue( null, $ammessa );
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
		$this->ammetti_transazione_esterna( false );

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
					'IdAtto'               => 1,
					'Numero'               => 10,
					'Oggetto'              => 'Delibera "dieci" & <prova>',
					'Riferimento'          => 'Delibera 5/2041',
					'Richiedente'          => 'Ditta Rossi & figli',
					'RespProc'             => 3,
					'IdUnitaOrganizzativa' => 2,
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
			$this->atto_vecchio(
				array(
					'IdAtto'             => 13,
					'Numero'             => 19,
					'Oggetto'            => 'Annullato in pubblicazione',
					'DataInizio'         => '2041-10-01',
					'DataFine'           => '2041-10-16',
					'DataAnnullamento'   => '2041-10-05',
					'MotivoAnnullamento' => 'Revoca',
				)
			),
			$this->atto_vecchio(
				array(
					'IdAtto'     => 14,
					'Numero'     => 301,
					'Anno'       => 2040,
					'Oggetto'    => 'Atto con un solo allegato',
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
			$this->documento( 12, 13, 'delibera-10.pdf', 'D' ),
			$this->documento( 13, 14, 'unico-301.pdf', 'A' ),
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
	 * Scrive il backup nella cartella di Albo Pretorio On line, con le tabelle indicate.
	 *
	 * @param array<int, array<string, mixed>>|null $atti     Righe degli atti, quelle di prova se nulle.
	 * @param array<int, array<string, mixed>>|null $allegati Righe degli allegati, quelle di prova se nulle.
	 * @param string                                $nome     Nome del file nella cartella.
	 * @return string Percorso del backup.
	 */
	private function backup_nella_cartella( ?array $atti = null, ?array $allegati = null, string $nome = 'backup.zip' ): string {
		$tabelle = $this->tabelle_vecchie( $atti ?? $this->atti_di_prova(), $allegati ?? $this->allegati_di_prova() );

		$tabelle['albopretorio_resprocedura'][] = array(
			'IdResponsabile' => 3,
			'Cognome'        => 'Bianchi',
			'Nome'           => 'Anna',
			'Email'          => '',
			'Telefono'       => '',
			'Orario'         => '',
			'Note'           => '',
			'Funzione'       => '',
		);

		$tabelle['albopretorio_unitaorganizzative'][] = array(
			'IdUO'      => 2,
			'Nome'      => 'Ufficio tecnico',
			'Indirizzo' => '',
			'Url'       => '',
			'Email'     => '',
			'Pec'       => '',
			'Telefono'  => '',
			'Fax'       => '',
			'Note'      => '',
		);

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
	 * Il rapporto di un backup, con lo stato attuale di questo albo.
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
	 * Gli atti di questo albo che ricordano un'origine, per origine, letti dalla banca dati.
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
	 * La fotografia completa degli atti importati, per origine: riga, dati, voci, documenti, registro, numero.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function fotografie(): array {
		$foto = array();

		foreach ( $this->importati() as $origine => $atto ) {
			$completa = $this->fotografia( $atto['id'] );

			// Gli allegati e il disco di tutto il sito non sono dell'atto: dell'atto si guardano i suoi documenti.
			unset( $completa['allegati'], $completa['disco'] );

			$completa['documenti'] = array(
				'principale' => DocumentiAtto::principale( $atto['id'] ),
				'ulteriori'  => DocumentiAtto::ulteriori( $atto['id'] ),
				'impronte'   => $this->impronte_dei_documenti( $atto['id'] ),
			);

			$foto[ $origine ] = $completa;
		}

		return $foto;
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
	 * A-210. Gli atti portabili si scrivono con i valori di Albo Pretorio On line.
	 */
	public function test_a210_atti_scritti_come_in_albo_pretorio_on_line(): void {
		$percorso = $this->backup_nella_cartella();
		$stato    = $this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );
		$atti     = $this->importati();

		$this->assertSame( array( '1', '2', '3', '4', '5', '12', '13', '14' ), array_map( 'strval', array_keys( $atti ) ), 'Si portano esattamente gli atti portabili.' );

		foreach ( $atti as $origine => $atto ) {
			$this->assertSame( in_array( (string) $origine, self::ANNULLATI, true ) ? 'albo_annullato' : 'publish', $atto['stato'], 'Pubblicato, o annullato se lo era in Albo Pretorio On line: ' . $origine );
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
			'13' => array(
				'Annullato in pubblicazione',
				'2041-10-01 00:00:00',
				'2041-09-30 22:00:00',
				array(
					'anno'   => 2041,
					'numero' => 19,
				),
				'2041-10-16',
				'',
			),
			'14' => array(
				'Atto con un solo allegato',
				'2040-09-03 00:00:00',
				'2040-09-02 22:00:00',
				array(
					'anno'   => 2040,
					'numero' => 301,
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
			$this->assertSame( $atteso[3], Repertorio::di( $id ), 'Numero e anno di Albo Pretorio On line: ' . $origine );
			$this->assertSame( $atteso[4], conformita_core_fine_pubblicazione( $id ), 'Fine di Albo Pretorio On line: ' . $origine );
			$this->assertSame( 'Deliberazione', DatiAtto::tipo( $id )->name, 'Tipo con il nome della categoria: ' . $origine );
			$this->assertSame( $atteso[5], DatiAtto::numero_proprio( $id ), 'Numero proprio dal riferimento: ' . $origine );
		}

		// Lo scaduto non si vede, quello in corso si'.
		foreach ( array( '1', '3', '4', '5', '12', '14' ) as $origine ) {
			$this->assertTrue( conformita_core_scaduto( $atti[ $origine ]['id'] ), 'Scaduto: ' . $origine );
		}

		$this->assertFalse( conformita_core_scaduto( $atti['2']['id'] ), 'In pubblicazione fino a tutto il 16.' );
		$this->assertFalse( conformita_core_scaduto( $atti['13']['id'] ), 'Annullato, ma in pubblicazione fino a tutto il 16.' );

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

		$this->assertSame( array( $atti['2']['id'] ), array_map( 'intval', $visibili ), 'Fra i pubblicati il pubblico vede solo l\'atto in corso: l\'annullato non e\' pubblicato.' );

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
		$this->assertSame( 'date_scritte_in_albo_pretorio_on_line', $voce['dettagli']['origine_durata'] );
		$this->assertSame( array( 'durata_breve' ), $voce['dettagli']['dubbi_portati'] );
		$this->assertFalse( $voce['dettagli']['conferma_dati_personali'] );
		$this->assertFalse( $voce['dettagli']['principale_da_scegliere'] );

		// I dati del procedimento alla lettera, sull'atto e nel registro, anche se l'amministrazione non ha scelto di mostrarli.
		foreach ( DatiProcedimento::DATI as $dato ) {
			$this->assertFalse( DatiProcedimento::in_uso( $dato ), 'Precondizione: non si mostra: ' . $dato );
		}

		$procedimento = array(
			DatiProcedimento::RESPONSABILE => 'Bianchi Anna',
			DatiProcedimento::UFFICIO      => 'Ufficio tecnico',
			DatiProcedimento::RICHIEDENTE  => 'Ditta Rossi & figli',
		);

		foreach ( $procedimento as $dato => $valore ) {
			$this->assertSame( $valore, DatiProcedimento::valore( $atti['1']['id'], $dato ), 'Sull\'atto: ' . $dato );
			$this->assertSame( $valore, $this->voce_importazione( $atti['1']['id'] )['dettagli'][ $dato ], 'Nel registro: ' . $dato );
			$this->assertSame( '', DatiProcedimento::valore( $atti['5']['id'], $dato ), 'Vuoto dove Albo Pretorio On line non l\'aveva: ' . $dato );
		}

		$this->assertSame( array(), DatiProcedimento::da_mostrare( $atti['1']['id'] ), 'Conservati, non mostrati.' );

		$annullato = $this->voce_importazione( $atti['4']['id'] );

		$this->assertSame( '2041-09-10', $annullato['dettagli']['annullamento'] );
		$this->assertSame( 'Errore materiale', $annullato['dettagli']['motivo_annullamento'] );
		$this->assertContains( 'annullato', $annullato['dettagli']['dubbi_portati'] );

		$in_corso = $this->voce_importazione( $atti['2']['id'] );

		$this->assertTrue( $in_corso['dettagli']['conferma_dati_personali'], 'La conferma data da chi importa e\' nel registro.' );
		$this->assertContains( 'conferma_dati_personali', $in_corso['dettagli']['dubbi_portati'] );

		// Il rapporto finale.
		$this->assertSame( 14, $stato['nel_backup'] );
		$this->assertSame( 14, $stato['analizzati'] );
		$this->assertCount( 8, $stato['importati'] );
		$this->assertSame( array(), $stato['non_riusciti'] );
		$this->assertSame( array( '12/2041' ), $stato['da_scegliere'] );
	}

	/**
	 * A-211. Gli altri atti non si portano, e il rapporto finale dice perche'; i visibili solo con la seconda conferma.
	 */
	public function test_a211_atti_non_portati_e_conferma_dei_visibili(): void {
		$percorso = $this->backup_nella_cartella();
		$rapporto = $this->rapporto_di( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), Importazione::selezione( $rapporto, false )['atti'], 'Senza la seconda conferma, l\'atto in corso resta fuori.' );
		$this->assertSame( array( '1', '2', '3', '4', '5', '12', '13', '14' ), Importazione::selezione( $rapporto, true )['atti'], 'Con la seconda conferma entrano l\'atto in corso e l\'annullato in pubblicazione.' );
		$this->assertSame( 0, Importazione::selezione( $rapporto, false )['in_vista'] );
		$this->assertSame( 2, Importazione::selezione( $rapporto, true )['in_vista'] );

		$stato = $this->importa_tutto( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame(
			array(
				'bozza'                   => array( 'bozza (numero interno 7)' ),
				'conferma_dati_personali' => array( '11/2041', '19/2041' ),
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
		$this->assertStringContainsString( 'data-atti="0" data-in-vista="2"', $pagina, 'Dopo l\'importazione restano da portare solo i due atti in pubblicazione.' );
		$this->assertStringContainsString( 'Importati 6 atti su 14 del backup.', $pagina );

		// L'atto in corso e l'annullato in pubblicazione arrivano con la seconda conferma, al rilancio.
		$secondo = $this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$this->assertSame( array( '1', '2', '3', '4', '5', '12', '13', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertCount( 2, $secondo['importati'] );
		$this->assertSame( 6, $secondo['gia_importati'] );
		$this->assertArrayNotHasKey( 'annullato', $this->non_portati( $secondo ), 'Annullato non e\' piu\' un motivo per restare fuori.' );
		$this->assertArrayNotHasKey( 'conferma_dati_personali', $this->non_portati( $secondo ) );
	}

	/**
	 * A-212. Rilanciare non crea doppioni; una bozza lasciata a meta' si rifa'.
	 */
	public function test_a212_rilancio_senza_doppioni(): void {
		$percorso = $this->backup_nella_cartella();

		$this->importa_tutto( $percorso );

		$prima    = $this->fotografie();
		$rapporto = $this->rapporto_di( $percorso );

		$this->assertCount( 6, $prima, 'Precondizione: sei atti importati.' );

		$this->assertSame( 6, $rapporto['riepilogo']['esiti'][ AnalisiImportazione::IMPORTATO ], 'Il rapporto riconosce gli atti gia\' importati.' );
		$this->assertSame( array(), Importazione::selezione( $rapporto, false )['atti'], 'Niente da portare senza la seconda conferma.' );

		$vuota = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso ) );

		$this->assertWPError( $vuota );
		$this->assertSame( 'albo_importazione_vuota', $vuota->get_error_code() );
		$this->assertSame( $prima, $this->fotografie(), 'Il rilancio non ha creato ne\' cambiato niente: righe, dati, voci, documenti, registro e numeri sono identici.' );

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

		// Una bozza qualunque, scritta da una persona con un nome che somiglia a quelli dell'importazione: non si tocca.
		$ordinaria = self::factory()->post->create(
			array(
				'post_type'   => TIPO,
				'post_status' => 'draft',
				'post_title'  => 'Bozza di una persona',
				'post_name'   => 'albo-importazione-2',
			)
		);
		$suo_file  = (string) get_attached_file( $this->deposita_documento( $ordinaria ) );
		$foto_sua  = $this->fotografia( $ordinaria );

		unset( $foto_sua['allegati'], $foto_sua['disco'] );
		$this->assertSame( 'albo-importazione-2', $foto_sua['riga']['post_name'], 'Precondizione: il nome e\' quello scritto.' );

		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$dopo = $this->importati();
		$foto = $this->fotografie();

		$this->assertNull( get_post( $bozza ), 'La bozza lasciata e\' stata cancellata.' );
		$this->assertNull( get_post( $documento ), 'Il suo documento e\' stato cancellato.' );
		$this->assertFileDoesNotExist( $sul_disco, 'Il suo file non c\'e\' piu\'.' );
		$this->assertSame( 'publish', $dopo['2']['stato'] );
		$this->assertCount( 8, $dopo );

		foreach ( $prima as $origine => $atto ) {
			$this->assertSame( $atto, $foto[ $origine ], 'Un atto gia\' importato non si tocca, in niente: ' . $origine );
		}

		$foto_dopo = $this->fotografia( $ordinaria );

		unset( $foto_dopo['allegati'], $foto_dopo['disco'] );
		$this->assertSame( $foto_sua, $foto_dopo, 'La bozza della persona e\' identica: riga, dati, voci, registro.' );
		$this->assertFileExists( $suo_file, 'Il suo documento e\' ancora sul disco.' );
		$this->assertArrayNotHasKey( 'lavorazione', array_filter( Importazione::stato() ), 'Nessun segno resta a importazione riuscita.' );
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

		// Un anno che prosegue la numerazione tenuta qui non riceve numeri importati: ripartirebbe da 1 sopra di loro.
		$tenuto = Repertorio::registra_importato( $bozza, 2042, 3 );

		$this->assertWPError( $tenuto );
		$this->assertSame( 'albo_repertorio_importato_anno_tenuto_qui', $tenuto->get_error_code() );
		$this->assertNull( Repertorio::di( $bozza ) );

		// Se la lettura degli anni precedenti non risponde, non vale "nessun anno prima": il numero non si registra.
		$cieca = static function ( $query ) {
			return false !== strpos( (string) $query, 'COUNT(*) AS quanti' ) ? 'SELECT * FROM tabella_che_non_esiste' : $query;
		};

		add_filter( 'query', $cieca );
		$non_letto = Repertorio::registra_importato( $bozza, 2042, 1 );
		$prossimo  = Repertorio::prossimo( 2042 );
		remove_filter( 'query', $cieca );

		$this->assertWPError( $non_letto );
		$this->assertSame( 'albo_repertorio_non_letto', $non_letto->get_error_code() );
		$this->assertWPError( $prossimo );
		$this->assertSame( 'albo_repertorio_non_letto', $prossimo->get_error_code() );
		$this->assertNull( Repertorio::di( $bozza ) );
		$this->assertSame( array(), Repertorio::atti_numerati( 2042 ), 'Nessuna assegnazione nel 2042.' );
		$this->assertSame( 1, Repertorio::prossimo( 2042 ), 'Il primo atto nuovo del 2042 prende 1.' );

		/*
		 * Una dichiarazione e un'importazione insieme, su un anno senza riga:
		 * il numero 5/2039 si registra dopo il controllo della dichiarazione e
		 * prima della sua scrittura. La condizione sta nella scrittura: la
		 * partenza 3 non si scrive. Fra due richieste vere lo stesso ordine lo
		 * impone la banca dati, che fa aspettare la scrittura della
		 * dichiarazione finche' il numero non e' confermato.
		 */
		$assegnazioni = Repertorio::tabella_assegnazioni();
		$in_mezzo     = static function ( $query ) use ( $assegnazioni ) {
			global $wpdb;

			if ( false !== stripos( (string) $query, 'INSERT IGNORE INTO' ) && false !== stripos( (string) $query, 'FROM DUAL WHERE NOT EXISTS' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: il numero arriva in mezzo.
				$wpdb->query( $wpdb->prepare( 'INSERT INTO %i ( anno, numero, atto_id, assegnato_il ) VALUES ( 2039, 5, %d, %s )', $assegnazioni, 987654321, '2039-03-01 09:00:00' ) );
			}

			return $query;
		};

		add_filter( 'query', $in_mezzo );
		$insieme = Repertorio::dichiara( 3, $this->amministratore, new \DateTimeImmutable( '2039-03-01 10:00:00', wp_timezone() ) );
		remove_filter( 'query', $in_mezzo );

		$this->assertWPError( $insieme );
		$this->assertSame( 'albo_repertorio_partenza_sotto_importati', $insieme->get_error_code() );
		$this->assertSame( array( '2041' ), array_column( $this->anni(), 'anno' ), 'Il 2039 non ha una riga: la partenza sotto il numero registrato non si e\' scritta.' );

		// Un atto pubblicato davvero dopo l'importazione prende il numero dopo la partenza, non un numero importato.
		$nuovo = $this->atto_in_verifica();

		$this->assertIsArray( $this->pubblica_da_codice( $nuovo, $this->pubblicatore ) );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => 15,
			),
			Repertorio::di( $nuovo ),
			'La partenza corretta a 14 prosegue con 15.'
		);
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
			'atti'     => array( SchermataImportazione::CAMPO_ATTI => '7' ),
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
					'atti'     => 6,
					'in_vista' => 2,
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
		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
	}

	/**
	 * A-214. La richiesta che si ferma fra la conferma di un atto e il salvataggio dello stato: "Continua" ricostruisce l'esito.
	 */
	public function test_a214_ripresa_fra_atto_e_stato(): void {
		$percorso = $this->backup_nella_cartella();

		$this->assertIsArray(
			Importazione::avvia(
				$percorso,
				'backup.zip',
				array(
					'impronta' => hash_file( 'sha256', $percorso ),
					'atti'     => 6,
					'in_vista' => 2,
				),
				true,
				false
			)
		);

		// La banca dati non salva lo stato dopo l'atto 12/2041 (numero interno 3), che ha il principale da scegliere.
		$non_salva = static function ( $nuovo, $vecchio ) {
			return isset( $nuovo['importati']['3'] ) ? $vecchio : $nuovo;
		};

		add_filter( 'pre_update_option_' . Importazione::OPZIONE, $non_salva, 10, 2 );
		$fermo = Importazione::prosegui( 5, microtime( true ) + 60 );
		remove_filter( 'pre_update_option_' . Importazione::OPZIONE, $non_salva, 10 );

		$this->assertWPError( $fermo );
		$this->assertSame( 'albo_importazione_stato_non_salvato', $fermo->get_error_code() );
		$this->assertSame( array( '1', '3' ), array_map( 'strval', array_keys( $this->importati() ) ), 'L\'atto e\' confermato nella banca dati.' );
		$this->assertSame( array( '1' ), array_map( 'strval', array_keys( Importazione::stato()['importati'] ) ), 'Lo stato salvato non lo sa.' );
		$this->assertSame( 1, Importazione::stato()['posizione'] );

		// La lettura del registro non risponde, una volta: il blocco si ferma sull'atto, senza avanzare ne' perderlo.
		$fallita  = false;
		$registro = static function ( $query ) use ( &$fallita ) {
			if ( ! $fallita && 0 === stripos( ltrim( (string) $query ), 'SELECT' ) && false !== strpos( (string) $query, 'conformita_core_registro' ) && false !== strpos( (string) $query, "'importazione'" ) ) {
				$fallita = true;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $query;
		};

		add_filter( 'query', $registro );
		$non_letto = Importazione::prosegui( 5, microtime( true ) + 60 );
		remove_filter( 'query', $registro );

		$this->assertTrue( $fallita, 'Precondizione: la lettura del registro e\' fallita.' );
		$this->assertWPError( $non_letto );
		$this->assertSame( 'albo_importazione_registro_non_letto', $non_letto->get_error_code() );
		$this->assertSame( 1, Importazione::stato()['posizione'], 'Non avanza.' );
		$this->assertSame( array(), Importazione::stato()['non_riusciti'], 'L\'atto non e\' fra i non riusciti.' );
		$this->assertArrayHasKey( '3', Importazione::stato()['lavorazione'], 'Il segno resta.' );

		$finale = $this->importa_tutto_da_qui();
		$atti   = $this->importati();

		$this->assertSame(
			array(
				'1'  => $atti['1']['id'],
				'3'  => $atti['3']['id'],
				'4'  => $atti['4']['id'],
				'5'  => $atti['5']['id'],
				'12' => $atti['12']['id'],
				'14' => $atti['14']['id'],
			),
			$finale['importati'],
			'L\'atto confermato prima della fermata e\' fra gli importati, con il suo identificativo.'
		);
		$this->assertSame( array( '12/2041' ), $finale['da_scegliere'], 'Il principale da scegliere si ricostruisce dal registro.' );
		$this->assertSame( 0, $finale['gia_importati'] );
		$this->assertArrayNotHasKey( 'gia_importato', $this->non_portati( $finale ) );
		$this->assertSame( array(), $finale['non_riusciti'] );
		$this->assertSame( array(), $finale['lavorazione'], 'Nessun segno resta per l\'atto ritrovato.' );
	}

	/**
	 * Porta a termine l'importazione gia' avviata, un blocco dopo l'altro.
	 *
	 * @return array<string, mixed> Stato finale.
	 */
	private function importa_tutto_da_qui(): array {
		$stato = array( 'stato' => 'in_corso' );

		for ( $giri = 0; 'in_corso' === $stato['stato'] && $giri < 50; ++$giri ) {
			$stato = SchermataImportazione::importazione_da_invio( $this->invio( 'continua' ) );

			$this->assertIsArray( $stato, is_wp_error( $stato ) ? $stato->get_error_message() : '' );
		}

		$this->assertSame( 'finita', $stato['stato'] );

		return $stato;
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

		$this->assertSame( array( '1', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ), 'Gli atti guasti non esistono; quelli dopo si sono importati.' );
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

		$attese = array( self::FILE['delibera-10.pdf'][1], self::FILE['breve-14.pdf'][1], self::FILE['anno-passato.pdf'][1], self::FILE['unico-301.pdf'][1] );
		sort( $attese );

		$this->assertSame( $attese, $impronte );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: nessun allegato orfano nella banca dati.
		$this->assertSame( 4, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' ) ) );

		// Rilanciato senza guasti, porta i due atti rimasti.
		$this->importa_tutto( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario(), 'Nessuna bozza rimasta.' );
	}

	/**
	 * A-215. Bozze senza origine, esiti dopo la conferma, cancellazioni non riuscite e tabelle che non annullano.
	 */
	public function test_a215_esiti_confermati_annullati_e_incerti(): void {
		global $wpdb;

		$percorso     = $this->backup_nella_cartella();
		$assegnazioni = Repertorio::tabella_assegnazioni();

		// La tabella dei numeri non annulla: nessun atto si scrive, e nessuna bozza resta.
		$senza_transazioni = static function ( $query ) use ( $assegnazioni ) {
			return false !== strpos( (string) $query, 'information_schema' ) ? str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$assegnazioni}' AND t.TABLE_NAME IN (", (string) $query ) : $query;
		};

		add_filter( 'query', $senza_transazioni );
		$rifiutata = $this->importa_tutto( $percorso );
		remove_filter( 'query', $senza_transazioni );

		$this->assertSame( array_fill( 0, 6, 'albo_tabelle_non_transazionali' ), array_column( $rifiutata['non_riusciti'], 'codice' ) );
		$this->assertSame( array(), $rifiutata['importati'] );
		$this->assertSame( array(), $this->inventario(), 'Nessun atto, neanche in bozza.' );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 10 ) );

		/*
		 * Il passaggio dell'atto 12/2041 (numero interno 3) fallisce e tutto si
		 * annulla, ma la sua bozza non si cancella: lo stato e' incerto, e il
		 * blocco si ferma su quell'atto invece di proseguire.
		 */
		$guasto    = static function ( $query ) {
			return false !== stripos( (string) $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', (string) $query ) ? 'SELECT * FROM tabella_che_non_esiste' : $query;
		};
		$trattiene = static function ( $cancella, $post ) {
			return 'Atto con due allegati' === $post->post_title ? false : $cancella;
		};

		add_filter( 'query', $guasto );
		add_filter( 'pre_delete_post', $trattiene, 10, 2 );
		// Avvio e blocco chiamati direttamente: il tempo del blocco dalla schermata parte con la richiesta, cioe' con l'intera suite di prove.
		$this->assertIsArray(
			Importazione::avvia(
				$percorso,
				'backup.zip',
				array(
					'impronta' => hash_file( 'sha256', $percorso ),
					'atti'     => 6,
					'in_vista' => 2,
				),
				true,
				false
			)
		);
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $guasto );
		remove_filter( 'pre_delete_post', $trattiene, 10 );

		$this->assertSame( 'in_corso', $fermo['stato'], 'Il blocco si e\' fermato.' );
		$this->assertSame( 2, $fermo['posizione'], 'Fermo subito dopo l\'atto incerto.' );
		$this->assertSame(
			array(
				'1' => 'publish',
				'3' => 'draft',
			),
			array_map(
				static function ( array $atto ): string {
					return $atto['stato'];
				},
				$this->importati()
			),
			'Gli atti dopo non sono stati toccati; la bozza dell\'atto incerto e\' rimasta.'
		);
		$this->assertSame( array( '12/2041' ), array_column( $fermo['non_riusciti'], 'numero' ) );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 12 ), 'Il numero e\' libero: il passaggio si e\' annullato.' );

		$bozze = $this->inventario( 'draft' );

		$this->assertCount( 1, $bozze, 'Resta la bozza che non si e\' cancellata, e si vede.' );
		$this->assertCount( 1, Importazione::stato()['lavorazione']['3'], 'Il segno della bozza incerta resta nello stato.' );
		$this->assertSame( Importazione::nome_bozza( Importazione::stato()['lavorazione']['3'][0] ), $bozze[0]['nome'], 'La bozza porta il nome con il segno salvato prima di nascere.' );
		$this->assertMatchesRegularExpression( '/^albo-importazione-[0-9a-f]{32}$/', $bozze[0]['nome'] );

		$this->assertSame( 'interrotta', SchermataImportazione::importazione_da_invio( $this->invio( 'interrompi' ) )['stato'] );

		/*
		 * Una nuova importazione toglie la bozza rimasta e porta l'atto. Nel
		 * frattempo: l'atto 13/2041 (numero interno 4) si ferma con
		 * un'eccezione mentre WordPress crea la bozza, prima che l'importazione
		 * ne conosca il numero; l'atto 14/2041 (numero interno 5) solleva
		 * un'eccezione dopo la conferma, durante lo svuotamento della memoria.
		 */
		$lancia_prima = static function ( $post_id, $post ) {
			if ( TIPO === $post->post_type && 'Atto annullato' === $post->post_title ) {
				throw new \RuntimeException( 'guasto alla creazione' );
			}
		};
		$lanciata     = false;
		$lancia_dopo  = static function ( $post_id ) use ( &$lanciata, $wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato vero, non quello in memoria.
			$riga = $wpdb->get_row( $wpdb->prepare( 'SELECT post_status, post_title FROM %i WHERE ID = %d', $wpdb->posts, $post_id ), ARRAY_A );

			if ( ! $lanciata && is_array( $riga ) && 'publish' === $riga['post_status'] && 'Atto breve' === $riga['post_title'] && ! Passaggi::in_corso( (int) $post_id ) ) {
				$lanciata = true;

				throw new \RuntimeException( 'guasto dopo la conferma' );
			}
		};

		add_action( 'save_post', $lancia_prima, 10, 2 );
		add_action( 'clean_post_cache', $lancia_dopo );
		$finale = $this->importa_tutto( $percorso );
		remove_action( 'save_post', $lancia_prima, 10 );
		remove_action( 'clean_post_cache', $lancia_dopo );

		$this->assertTrue( $lanciata, 'Precondizione: l\'eccezione dopo la conferma e\' partita.' );
		$this->assertSame( array( '1', '3', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame( array( '3', '5', '12', '14' ), array_map( 'strval', array_keys( $finale['importati'] ) ), 'L\'atto confermato prima dell\'eccezione e\' fra gli importati.' );
		$this->assertSame( array( '12/2041' ), $finale['da_scegliere'] );
		$this->assertSame( array( '13/2041' ), array_column( $finale['non_riusciti'], 'numero' ) );
		$this->assertSame( 'albo_importazione_eccezione', $finale['non_riusciti'][0]['codice'] );
		$this->assertStringNotContainsString( 'Stato incerto', $finale['non_riusciti'][0]['testo'], 'La bozza senza origine si e\' ritrovata dal nome e cancellata.' );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario(), 'Tutti gli atti del tipo, in ogni stato: solo gli importati, nessuna bozza.' );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 13 ) );
	}

	/**
	 * A-215. Un errore nella preparazione della bozza, o una bozza lasciata che non si toglie, fermano il blocco.
	 */
	public function test_a215_preparazione_e_pulizia_incerte(): void {
		$percorso = $this->backup_nella_cartella();

		// Il numero proprio dell'atto 10/2041 (numero interno 1) non si scrive, e la sua bozza non si cancella.
		$non_scrive = static function ( $controllo, $atto_id, $chiave ) {
			unset( $atto_id );

			return META_NUMERO_PROPRIO === $chiave ? false : $controllo;
		};
		$trattiene  = static function ( $cancella, $post ) {
			return 'Delibera "dieci" & <prova>' === $post->post_title ? false : $cancella;
		};

		add_filter( 'update_post_metadata', $non_scrive, 10, 3 );
		add_filter( 'pre_delete_post', $trattiene, 10, 2 );

		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );

		remove_filter( 'update_post_metadata', $non_scrive, 10 );

		$this->assertSame( 'in_corso', $fermo['stato'] );
		$this->assertSame( 1, $fermo['posizione'], 'Fermo sul primo atto: nessun atto dopo e\' stato lavorato.' );
		$this->assertSame( array( 'albo_importazione_dato' ), array_column( $fermo['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'numero proprio', $fermo['non_riusciti'][0]['testo'] );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );
		$this->assertCount( 1, $this->inventario(), 'Solo la bozza che non si e\' cancellata.' );
		$this->assertSame( 'draft', $this->inventario()[0]['stato'] );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );

		// Una nuova importazione trova la bozza lasciata e non riesce a toglierla: si ferma subito, senza crearne un'altra.
		$this->avvia_diretto( $percorso );
		$ancora = Importazione::prosegui( 50, microtime( true ) + 60 );

		remove_filter( 'pre_delete_post', $trattiene, 10 );

		$this->assertSame( 1, $ancora['posizione'] );
		$this->assertSame( array( 'albo_importazione_bozza_rimasta' ), array_column( $ancora['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'Stato incerto', $ancora['non_riusciti'][0]['testo'] );
		$this->assertCount( 1, $this->inventario(), 'Nessuna bozza nuova.' );
		$this->assertCount( 2, Importazione::stato()['lavorazione']['1'], 'I segni di entrambe le lavorazioni restano.' );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );

		// Senza guasti, la bozza lasciata si toglie e tutto si porta.
		$this->importa_tutto( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
	}

	/**
	 * A-215. Un documento o un file della bozza che non si cancella lascia la bozza, e il rilancio finisce la pulizia.
	 */
	public function test_a215_documenti_della_bozza_incerti(): void {
		$percorso = $this->backup_nella_cartella();
		$prima    = $this->documenti_sul_disco();

		// Il passaggio dell'atto 12/2041 (numero interno 3) fallisce dopo il deposito dei suoi due documenti.
		$guasto = static function ( $query ) {
			return false !== stripos( (string) $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', (string) $query ) ? 'SELECT * FROM tabella_che_non_esiste' : $query;
		};
		// I documenti di quella bozza rifiutano la cancellazione.
		$trattiene = static function ( $cancella, $documento ) {
			return 'Atto con due allegati' === get_the_title( (int) $documento->post_parent ) ? false : $cancella;
		};

		add_filter( 'query', $guasto );
		add_filter( 'pre_delete_attachment', $trattiene, 10, 2 );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $guasto );
		remove_filter( 'pre_delete_attachment', $trattiene, 10 );

		$this->assertSame( 2, $fermo['posizione'], 'Fermo subito dopo l\'atto incerto.' );
		$this->assertSame( array( '12/2041' ), array_column( $fermo['non_riusciti'], 'numero' ) );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );

		$bozza = $this->importati()['3']['id'];

		$this->assertSame( 'draft', get_post_status( $bozza ), 'La bozza resta, e con lei la strada per ritrovare i documenti.' );
		$this->assertCount(
			2,
			get_children(
				array(
					'post_parent' => $bozza,
					'post_type'   => 'attachment',
					'post_status' => 'any',
				)
			)
		);
		$this->assertCount( 2, get_post_meta( $bozza, Importazione::META_FILE_DA_TOGLIERE, false ), 'L\'elenco dei file e\' scritto prima di cancellare.' );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );

		// Una nuova importazione toglie i documenti, ma i loro file restano sul disco.
		$file     = array_map( 'strval', get_post_meta( $bozza, Importazione::META_FILE_DA_TOGLIERE, false ) );
		$conserva = static function ( $da_togliere ) use ( $file ) {
			return in_array( $da_togliere, $file, true ) ? '' : $da_togliere;
		};

		add_filter( 'wp_delete_file', $conserva );
		$this->avvia_diretto( $percorso, 5 );
		$ancora = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'wp_delete_file', $conserva );

		$this->assertSame( array( 'albo_importazione_bozza_rimasta' ), array_column( $ancora['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'Stato incerto', $ancora['non_riusciti'][0]['testo'] );
		$this->assertSame(
			array(),
			get_children(
				array(
					'post_parent' => $bozza,
					'post_type'   => 'attachment',
					'post_status' => 'any',
				)
			),
			'Precondizione: i documenti se ne sono andati.'
		);

		foreach ( $file as $uno ) {
			$this->assertFileExists( $uno, 'Precondizione: il file e\' rimasto.' );
		}

		$this->assertSame( 'draft', get_post_status( $bozza ), 'La bozza resta: e\' lei a ricordare dove sono i file.' );
		$this->assertSame( $file, array_map( 'strval', get_post_meta( $bozza, Importazione::META_FILE_DA_TOGLIERE, false ) ) );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );

		// Una nuova importazione non riesce a leggere l'elenco dei file: la bozza resta, con l'elenco.
		wp_cache_flush();

		$letto = false;
		$cieca = static function ( $query ) use ( &$letto ) {
			if ( false !== strpos( (string) $query, Importazione::META_FILE_DA_TOGLIERE ) && 0 === stripos( ltrim( (string) $query ), 'SELECT' ) ) {
				$letto = true;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $query;
		};

		add_filter( 'query', $cieca );
		$this->avvia_diretto( $percorso, 5 );
		$ancora = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $cieca );

		$this->assertTrue( $letto, 'Precondizione: la lettura dell\'elenco e\' fallita.' );
		$this->assertSame( array( 'albo_importazione_bozza_rimasta' ), array_column( $ancora['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'Stato incerto', $ancora['non_riusciti'][0]['testo'] );
		$this->assertSame( 'draft', get_post_status( $bozza ), 'Senza l\'elenco letto la bozza non si cancella.' );
		$this->assertSame( $file, array_map( 'strval', get_post_meta( $bozza, Importazione::META_FILE_DA_TOGLIERE, false ) ) );

		foreach ( $file as $uno ) {
			$this->assertFileExists( $uno );
		}

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );

		// Senza guasti il rilancio toglie i file e la bozza, e porta tutto: sul disco solo i documenti degli importati.
		$this->importa_tutto( $percorso );

		$this->assertFalse( get_post( $bozza ) instanceof \WP_Post );
		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );

		$attesi = $prima;

		foreach ( $this->importati() as $atto ) {
			foreach ( get_children(
				array(
					'post_parent' => $atto['id'],
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			) as $documento ) {
				$attesi[] = get_attached_file( (int) $documento, true );
			}
		}

		sort( $attesi );

		// Il documento portato di nuovo puo' riprendere lo stesso nome di file: conta che ogni file abbia il suo documento.
		$this->assertSame( $attesi, $this->documenti_sul_disco(), 'Nessun file senza documento.' );
	}

	/**
	 * A-215. Una lettura dei documenti che fallisce, o un documento non registrato il cui file resta, lasciano la bozza da ritrovare.
	 */
	public function test_a215_lettura_e_deposito_incerti(): void {
		$percorso  = $this->backup_nella_cartella();
		$prima     = $this->documenti_sul_disco();
		$documenti = static function ( int $bozza ): array {
			return get_children(
				array(
					'post_parent' => $bozza,
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			);
		};

		/*
		 * Il passaggio dell'atto 12/2041 (numero interno 3) fallisce dopo il
		 * deposito; da quel momento ogni lettura dei documenti di una bozza
		 * fallisce, anche quella di WordPress, mentre le scritture riescono.
		 */
		$rotta  = false;
		$guasto = static function ( $query ) use ( &$rotta ) {
			$query = (string) $query;

			if ( false !== stripos( $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', $query ) ) {
				$rotta = true;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $rotta && 0 === stripos( ltrim( $query ), 'SELECT' ) && false !== strpos( $query, 'post_parent' ) ? 'SELECT * FROM tabella_che_non_esiste' : $query;
		};

		add_filter( 'query', $guasto );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $guasto );

		$this->assertTrue( $rotta, 'Precondizione: il guasto e\' partito.' );
		$this->assertSame( 2, $fermo['posizione'], 'Fermo subito dopo l\'atto incerto.' );
		$this->assertSame( array( '12/2041' ), array_column( $fermo['non_riusciti'], 'numero' ) );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );

		$bozza = $this->importati()['3']['id'];

		$this->assertSame( 'draft', get_post_status( $bozza ), 'Senza l\'elenco dei documenti la bozza non si cancella.' );
		$this->assertCount( 2, $documenti( $bozza ) );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->importa_tutto( $percorso );

		$this->assertFalse( get_post( $bozza ) instanceof \WP_Post );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
		$this->assertSame( $this->file_attesi( $prima ), $this->documenti_sul_disco(), 'Nessun file senza documento.' );
	}

	/**
	 * A-215. La lettura di controllo prima di togliere la bozza, se non risponde, lascia la bozza.
	 */
	public function test_a215_controllo_finale_fallito(): void {
		$percorso = $this->backup_nella_cartella();
		$prima    = $this->documenti_sul_disco();

		// Il passaggio dell'atto 12/2041 fallisce; la pulizia legge i documenti, li toglie, e la seconda lettura non risponde.
		$rotta   = false;
		$letture = 0;
		$guasto  = static function ( $query ) use ( &$rotta, &$letture ) {
			$query = (string) $query;

			if ( false !== stripos( $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', $query ) ) {
				$rotta = true;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			if ( $rotta && false !== strpos( $query, \Conformita_Core_Allegati::MARCA ) && false !== strpos( $query, 'p.post_parent' ) ) {
				++$letture;

				return 2 === $letture ? 'SELECT * FROM tabella_che_non_esiste' : $query;
			}

			return $query;
		};

		add_filter( 'query', $guasto );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $guasto );

		$this->assertSame( 2, $letture, 'Precondizione: la seconda lettura e\' quella di controllo.' );
		$this->assertSame( 2, $fermo['posizione'] );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );

		$bozza = $this->importati()['3']['id'];

		$this->assertSame( 'draft', get_post_status( $bozza ), 'Senza la conferma che non resta niente, la bozza resta.' );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->importa_tutto( $percorso );

		$this->assertFalse( get_post( $bozza ) instanceof \WP_Post );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
		$this->assertSame( $this->file_attesi( $prima ), $this->documenti_sul_disco() );
	}

	/**
	 * A-215. La bozza la cui riga non si cancella, con la lettura finale che non risponde, resta ritrovabile.
	 */
	public function test_a215_bozza_cancellata_a_meta(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();
		$prima    = $this->documenti_sul_disco();

		/*
		 * Il passaggio dell'atto 12/2041 (numero interno 3) fallisce; poi la
		 * cancellazione della riga della sua bozza fallisce, quando WordPress
		 * ne ha gia' cancellato i dati, ricordo dell'origine compreso; e da li'
		 * ogni lettura di quella riga non risponde.
		 */
		$rotta  = false;
		$bozza  = 0;
		$guasto = static function ( $query ) use ( &$rotta, &$bozza, $wpdb ) {
			$query = (string) $query;

			if ( false !== stripos( $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', $query ) ) {
				$rotta = true;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			if ( $rotta && 0 === $bozza && 1 === preg_match( '/^DELETE FROM `?' . preg_quote( $wpdb->posts, '/' ) . '`? WHERE `ID` = (\d+)/', $query, $trovato ) && TIPO === get_post_type( (int) $trovato[1] ) ) {
				$bozza = (int) $trovato[1];
			}

			if ( $bozza > 0 && 1 === preg_match( '/' . preg_quote( $wpdb->posts, '/' ) . '.*\bID`? = ' . $bozza . '\b/', $query ) && 1 !== preg_match( '/^SELECT\s+\*\s+FROM\s+tabella/', $query ) ) {
				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $query;
		};

		add_filter( 'query', $guasto );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $guasto );

		$this->assertGreaterThan( 0, $bozza, 'Precondizione: la cancellazione della bozza e\' fallita.' );
		$this->assertSame( 2, $fermo['posizione'] );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );
		$this->assertNotEmpty( Importazione::stato()['lavorazione']['3'] ?? array(), 'I segni della bozza restano.' );

		clean_post_cache( $bozza );

		$this->assertSame( 'draft', get_post_status( $bozza ), 'Precondizione: la riga della bozza e\' rimasta.' );
		$this->assertSame( '', (string) get_post_meta( $bozza, META_ORIGINE_IMPORTAZIONE, true ), 'Precondizione: senza il ricordo dell\'origine.' );

		// Il rilancio la ritrova dal segno nel nome e la toglie.
		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->importa_tutto( $percorso );

		clean_post_cache( $bozza );

		$this->assertNull( get_post( $bozza ) );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
		$this->assertSame( $this->file_attesi( $prima ), $this->documenti_sul_disco() );
		$this->assertSame( array(), $this->documenti_senza_atto() );
	}

	/**
	 * A-215. Un passaggio che non riesce ad annullare le sue scritture ferma il blocco con l'esito incerto, senza dedurre niente.
	 */
	public function test_a215_annullamento_non_riuscito(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();

		// La data di fine del primo atto non si scrive, dopo lo stato; e l'annullamento non si esegue.
		$chiave       = conformita_core_chiave_fine_pubblicazione();
		$non_scrive   = static function ( $controllo, $atto_id, $nome ) use ( $chiave ) {
			unset( $atto_id );

			return $chiave === $nome ? false : $controllo;
		};
		$annullamenti = 0;
		$non_annulla  = static function ( $query ) use ( &$annullamenti ) {
			if ( 0 === stripos( ltrim( (string) $query ), 'ROLLBACK TO SAVEPOINT albo_pretorio_passaggio' ) ) {
				++$annullamenti;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $query;
		};

		add_filter( 'update_post_metadata', $non_scrive, 10, 3 );
		add_filter( 'query', $non_annulla );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $non_annulla );
		remove_filter( 'update_post_metadata', $non_scrive, 10 );

		$this->assertSame( 1, $annullamenti, 'Precondizione: un solo annullamento, non eseguito.' );
		$this->assertSame( 1, $fermo['posizione'], 'Fermo sul primo atto: nessun atto dopo e\' stato lavorato.' );
		$this->assertSame( array( 'albo_annullamento_non_riuscito' ), array_column( $fermo['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );
		$this->assertSame( array(), $fermo['importati'], 'L\'atto che sembra pubblicato non si conta fra gli importati.' );
		$this->assertCount( 1, Importazione::stato()['lavorazione']['1'] ?? array(), 'I segni restano.' );

		$bozza = $this->importati()['1']['id'];

		$this->assertSame( 'publish', $this->importati()['1']['stato'], 'Precondizione: questa connessione vede ancora le scritture non annullate.' );

		// La transazione si risolve: l'annullamento si fa davvero, e la ripresa toglie la bozza e porta tutto.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: l'annullamento che prima non era riuscito.
		$this->assertNotFalse( $wpdb->query( 'ROLLBACK TO SAVEPOINT albo_pretorio_passaggio' ) );
		wp_cache_flush();

		$this->assertSame( 'draft', get_post_status( $bozza ) );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->importa_tutto( $percorso );

		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ) );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
	}

	/**
	 * A-215. Un documento depositato che l'atto non registra, con il file che resta, lascia la bozza da ritrovare.
	 */
	public function test_a215_deposito_non_registrato(): void {
		$percorso = $this->backup_nella_cartella();
		$prima    = $this->documenti_sul_disco();
		$cartella = \Conformita_Core_Allegati::cartella();
		$titolo   = 'Atto con due allegati';

		// Il dato degli allegati ulteriori di quell'atto non si scrive, e i file dei documenti non si cancellano.
		$non_scrive = static function ( $controllo, $atto_id, $chiave ) use ( $titolo ) {
			return META_ALLEGATI_ULTERIORI === $chiave && get_the_title( (int) $atto_id ) === $titolo ? false : $controllo;
		};
		$conserva   = static function ( $file ) use ( $cartella ) {
			return 0 === strpos( (string) $file, $cartella ) ? '' : $file;
		};

		add_filter( 'update_post_metadata', $non_scrive, 10, 3 );
		add_filter( 'wp_delete_file', $conserva );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'update_post_metadata', $non_scrive, 10 );
		remove_filter( 'wp_delete_file', $conserva );

		$this->assertSame( 2, $fermo['posizione'] );
		$this->assertSame( array( 'albo_importazione_deposito' ), array_column( $fermo['non_riusciti'], 'codice' ) );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );

		$bozza = $this->importati()['3']['id'];
		$file  = array_map( 'strval', get_post_meta( $bozza, Importazione::META_FILE_DA_TOGLIERE, false ) );

		$this->assertSame( 'draft', get_post_status( $bozza ) );
		$this->assertSame(
			array(),
			get_children(
				array(
					'post_parent' => $bozza,
					'post_type'   => 'attachment',
					'post_status' => 'any',
				)
			),
			'Precondizione: il documento se n\'e\' andato.'
		);
		$this->assertCount( 1, $file, 'Il file del documento non registrato e\' nell\'elenco della bozza.' );
		$this->assertFileExists( $file[0], 'Precondizione: il file e\' rimasto.' );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->importa_tutto( $percorso );

		$this->assertFalse( get_post( $bozza ) instanceof \WP_Post );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
		$this->assertSame( $this->file_attesi( $prima ), $this->documenti_sul_disco(), 'Nessun file senza documento.' );
	}

	/**
	 * I file che devono stare sul disco: quelli di prima e quelli dei documenti degli importati.
	 *
	 * @param array<int, string> $prima File sul disco prima dell'importazione.
	 * @return array<int, string>
	 */
	private function file_attesi( array $prima ): array {
		$attesi = $prima;

		foreach ( $this->importati() as $atto ) {
			foreach ( get_children(
				array(
					'post_parent' => $atto['id'],
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
				)
			) as $documento ) {
				$attesi[] = get_attached_file( (int) $documento, true );
			}
		}

		sort( $attesi );

		return $attesi;
	}

	/**
	 * A-215. Una copia dall'archivio che non riesce non lascia file temporanei.
	 */
	public function test_a215_temporanei_tolti(): void {
		$percorso = $this->backup_nella_cartella();
		$estrai   = new \ReflectionMethod( Importazione::class, 'estrai' );
		$estrai->setAccessible( true );

		$archivio = new \ZipArchive();
		$this->assertTrue( $archivio->open( $percorso ) );
		$voce = '';

		$quanti = $archivio->count();

		for ( $i = 0; $i < $quanti; ++$i ) {
			if ( 'unico-301.pdf' === wp_basename( (string) $archivio->getNameIndex( $i ) ) ) {
				$voce = (string) $archivio->getNameIndex( $i );
			}
		}

		$archivio->close();
		$this->assertNotSame( '', $voce, 'Precondizione: il file e\' nell\'archivio.' );

		$atteso     = self::FILE['unico-301.pdf'][1];
		$temporanei = static function (): array {
			$trovati = glob( trailingslashit( get_temp_dir() ) . '*' );

			return array_values( array_filter( is_array( $trovati ) ? $trovati : array(), 'is_file' ) );
		};
		$prima      = $temporanei();

		foreach ( array(
			'archivio che non si apre' => array( $percorso . '-che-non-esiste', $voce, $atteso ),
			'file che non c\'e\''      => array( $percorso, $voce . '-che-non-esiste', $atteso ),
			'impronta diversa'         => array( $percorso, $voce, str_repeat( '0', 64 ) ),
		) as $caso => $argomenti ) {
			$esito = $estrai->invokeArgs( null, $argomenti );

			$this->assertWPError( $esito, $caso );
			$this->assertSame( 'albo_importazione_estrazione', $esito->get_error_code(), $caso );
			$this->assertSame( $prima, $temporanei(), $caso . ': nessun temporaneo resta.' );
		}

		// Il file intero passa a chi chiama, che lo cancella dopo il deposito.
		$copiato = $estrai->invokeArgs( null, array( $percorso, $voce, $atteso ) );

		$this->assertIsString( $copiato );
		$this->assertSame( $atteso, hash_file( 'sha256', $copiato ) );

		wp_delete_file( $copiato );

		$this->assertSame( $prima, $temporanei() );
	}

	/**
	 * A-215. Un documento la cui riga non si cancella dopo i suoi dati resta ricordato dalla bozza, e il rilancio lo toglie.
	 */
	public function test_a215_documento_cancellato_a_meta(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();
		$prima    = $this->documenti_sul_disco();

		/*
		 * Il passaggio dell'atto 12/2041 (numero interno 3) fallisce dopo il
		 * deposito; poi la cancellazione della riga dei suoi documenti
		 * fallisce, quando WordPress ne ha gia' cancellato i dati, marca
		 * compresa.
		 */
		$rotta      = false;
		$trattenuti = array();
		$guasto     = static function ( $query ) use ( &$rotta, &$trattenuti, $wpdb ) {
			$query = (string) $query;

			if ( false !== stripos( $query, 'INSERT IGNORE' ) && 1 === preg_match( '/VALUES\s*\(\s*2041\s*,\s*12\s*,/i', $query ) ) {
				$rotta = true;

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			if ( $rotta && 1 === preg_match( '/^DELETE FROM `?' . preg_quote( $wpdb->posts, '/' ) . '`? WHERE `ID` = (\d+)/', $query, $trovato ) ) {
				$trattenuti[] = (int) $trovato[1];

				return 'SELECT * FROM tabella_che_non_esiste';
			}

			return $query;
		};

		add_filter( 'query', $guasto );
		$this->avvia_diretto( $percorso );
		$fermo = Importazione::prosegui( 50, microtime( true ) + 60 );
		remove_filter( 'query', $guasto );

		$this->assertNotSame( array(), $trattenuti, 'Precondizione: la cancellazione della riga e\' fallita.' );
		$this->assertSame( 2, $fermo['posizione'] );
		$this->assertStringContainsString( 'Stato incerto', $fermo['non_riusciti'][0]['testo'] );

		$bozza = $this->importati()['3']['id'];

		$this->assertSame( 'draft', get_post_status( $bozza ) );

		foreach ( $trattenuti as $documento ) {
			clean_post_cache( $documento );

			$this->assertSame( 'attachment', get_post_type( $documento ), 'Precondizione: la riga del documento e\' rimasta.' );
			$this->assertSame( '', (string) get_post_meta( $documento, \Conformita_Core_Allegati::MARCA, true ), 'Precondizione: senza la marca.' );
		}

		$this->assertContains( (string) $trattenuti[0], get_post_meta( $bozza, Importazione::META_DOCUMENTI_DA_TOGLIERE, false ) );

		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->importa_tutto( $percorso );

		$this->assertFalse( get_post( $bozza ) instanceof \WP_Post );

		foreach ( $trattenuti as $documento ) {
			clean_post_cache( $documento );

			$this->assertNull( get_post( $documento ), 'Il documento rimasto a meta\' si e\' tolto.' );
		}

		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
		$this->assertSame( $this->file_attesi( $prima ), $this->documenti_sul_disco(), 'Nessun file senza documento.' );
		$this->assertSame( array(), $this->documenti_senza_atto(), 'Nessun documento senza atto.' );
	}

	/**
	 * A-215. Una lettura dell'elenco degli allegati che non risponde fra due depositi non porta un atto con un allegato in meno.
	 */
	public function test_a215_allegato_perso_fra_due_depositi(): void {
		$percorso = $this->backup_nella_cartella();
		$prima    = $this->documenti_sul_disco();
		$titolo   = 'Atto con due allegati';

		// La lettura dell'elenco degli allegati di quell'atto risponde vuoto: il secondo deposito lo riscrive con se' solo.
		$vuoto = static function ( $valore, $atto_id, $chiave ) use ( $titolo ) {
			return META_ALLEGATI_ULTERIORI === $chiave && get_the_title( (int) $atto_id ) === $titolo ? array() : $valore;
		};

		add_filter( 'get_post_metadata', $vuoto, 10, 3 );
		$stato = $this->importa_tutto( $percorso );
		remove_filter( 'get_post_metadata', $vuoto, 10 );

		$this->assertSame( array( '12/2041' ), array_column( $stato['non_riusciti'], 'numero' ) );
		$this->assertSame( 'albo_importazione_collegamenti', $stato['non_riusciti'][0]['codice'] );
		$this->assertStringNotContainsString( 'Stato incerto', $stato['non_riusciti'][0]['testo'], 'La bozza si e\' tolta: l\'esito e\' certo.' );
		$this->assertArrayNotHasKey( '3', $this->importati(), 'Nessun atto con un allegato in meno.' );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
		$this->assertSame( $this->file_attesi( $prima ), $this->documenti_sul_disco() );
		$this->assertSame( array(), $this->documenti_senza_atto() );

		// Senza guasti il rilancio lo porta con entrambi gli allegati.
		$this->importa_tutto( $percorso );

		$atto = $this->importati()['3']['id'];

		$this->assertCount( 2, DocumentiAtto::ulteriori( $atto ) );
	}

	/**
	 * I documenti il cui atto non esiste piu'.
	 *
	 * @return array<int, int>
	 */
	private function documenti_senza_atto(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge cio' che c'e' davvero nella banca dati.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT a.ID FROM %i a LEFT JOIN %i p ON p.ID = a.post_parent WHERE a.post_type = %s AND a.post_parent > 0 AND p.ID IS NULL', $wpdb->posts, $wpdb->posts, 'attachment' ) ) );
	}

	/**
	 * A-210. Con le revisioni spente nella configurazione del sito, l'importazione funziona lo stesso.
	 */
	public function test_a210_con_le_revisioni_spente(): void {
		$percorso = $this->backup_nella_cartella();

		add_filter( 'wp_revisions_to_keep', '__return_zero' );

		try {
			$this->assertFalse(
				wp_revisions_enabled(
					get_post(
						self::factory()->post->create(
							array(
								'post_type'   => TIPO,
								'post_status' => 'draft',
							)
						)
					)
				),
				'Precondizione: revisioni spente.'
			);

			$stato = $this->importa_tutto( $percorso );
		} finally {
			remove_filter( 'wp_revisions_to_keep', '__return_zero' );
		}

		$this->assertSame( array(), $stato['non_riusciti'] );
		$this->assertSame( array( '1', '3', '4', '5', '12', '14' ), array_map( 'strval', array_keys( $stato['importati'] ) ) );
	}

	/**
	 * A-213. Dichiarazione e importazione dello stesso anno prendono lo stesso blocco, anche quando la riga dell'anno manca.
	 */
	public function test_a213_blocco_dell_anno(): void {
		$percorso    = $this->backup_nella_cartella();
		$this->altra = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );

		// Le dichiarazioni della preparazione tengono il blocco dentro la transazione della suite: le si considera confermate.
		$GLOBALS['wpdb']->query( 'SELECT RELEASE_ALL_LOCKS()' );

		// Un'altra richiesta tiene il 2041, come un'importazione nel mezzo della sua transazione.
		$this->assertSame( '1', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2041 ) ) ) );

		$this->avvia_diretto( $percorso );
		$stato = Importazione::prosegui( 1, microtime( true ) + 60 );

		$this->assertSame( array( 'albo_repertorio_anno_occupato' ), array_column( $stato['non_riusciti'], 'codice' ), 'L\'atto del 2041 aspetta e poi rinuncia.' );
		$this->assertStringNotContainsString( 'Stato incerto', $stato['non_riusciti'][0]['testo'] );
		$this->assertSame( array(), $this->inventario(), 'Nessuna bozza resta.' );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 10 ) );

		$this->altra->query( $this->altra->prepare( 'SELECT RELEASE_LOCK( %s )', Repertorio::nome_blocco_anno( 2041 ) ) );

		// Un'altra richiesta tiene il 2039, che non ha riga: la dichiarazione della partenza aspetta e poi rinuncia, senza scrivere.
		$this->assertSame( '1', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2039 ) ) ) );

		$dichiarata = Repertorio::dichiara( 3, $this->amministratore, new \DateTimeImmutable( '2039-03-01 10:00:00', wp_timezone() ) );

		$this->assertWPError( $dichiarata );
		$this->assertSame( 'albo_repertorio_anno_occupato', $dichiarata->get_error_code() );
		$this->assertSame( array( '2041' ), array_column( $this->anni(), 'anno' ) );

		$this->altra->query( $this->altra->prepare( 'SELECT RELEASE_LOCK( %s )', Repertorio::nome_blocco_anno( 2039 ) ) );

		/*
		 * Liberato il blocco, la dichiarazione passa. La prova gira dentro la
		 * transazione della suite, che non e' ancora confermata: la
		 * dichiarazione tiene il blocco fino alla fine della connessione, e
		 * l'altra richiesta non lo prende. Lo stesso per l'importazione, che
		 * qui la prova ammette.
		 */
		$this->assertTrue( Repertorio::dichiara( 3, $this->amministratore, new \DateTimeImmutable( '2039-03-01 10:00:00', wp_timezone() ) ) );
		$this->assertSame( '0', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2039 ) ) ), 'Dentro una transazione non confermata la dichiarazione tiene il blocco.' );

		$finale = Importazione::prosegui( 50, microtime( true ) + 60 );

		$this->assertSame( 'finita', $finale['stato'] );
		$this->assertSame( '0', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2041 ) ) ), 'Dentro una transazione non confermata l\'importazione tiene il blocco.' );

		// Alla fine della connessione la banca dati lascia i blocchi.
		$GLOBALS['wpdb']->query( 'SELECT RELEASE_ALL_LOCKS()' );

		$this->assertSame( '1', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2039 ) ) ) );
		$this->assertSame( '1', (string) $this->altra->get_var( $this->altra->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2041 ) ) ) );
	}

	/**
	 * A-214. Dentro la transazione di un altro componente l'importazione non comincia, e non prosegue.
	 */
	public function test_a214_transazione_esterna(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge lo stato della connessione.
		$this->assertSame( '0', (string) $wpdb->get_var( 'SELECT @@autocommit' ), 'Precondizione: la suite tiene aperta la sua transazione.' );
		$this->assertTrue( Repertorio::transazione_esterna() );

		$this->ammetti_transazione_esterna( false );

		$rifiutata = Importazione::avvia(
			$percorso,
			'backup.zip',
			array(
				'impronta' => hash_file( 'sha256', $percorso ),
				'atti'     => 6,
				'in_vista' => 2,
			),
			true,
			false
		);

		$this->assertWPError( $rifiutata );
		$this->assertSame( 'albo_importazione_transazione_esterna', $rifiutata->get_error_code() );
		$this->assertNull( Importazione::stato() );

		// Avviata dove e' ammessa, non prosegue dentro la transazione di un altro: niente si scrive.
		$this->ammetti_transazione_esterna( true );
		$this->avvia_diretto( $percorso );
		$this->ammetti_transazione_esterna( false );

		$ferma = Importazione::prosegui( 50, microtime( true ) + 60 );

		$this->assertWPError( $ferma );
		$this->assertSame( 'albo_importazione_transazione_esterna', $ferma->get_error_code() );
		$this->assertSame( 0, Importazione::stato()['posizione'] );
		$this->assertSame( array(), $this->inventario(), 'Nessuna bozza, nessun atto.' );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 10 ) );
	}

	/**
	 * A-214. Con la scrittura automatica accesa e una transazione aperta da altri, una lettura fallita dello stato vale come transazione.
	 */
	public function test_a214_transazione_aperta_e_lettura_fallita(): void {
		$percorso    = $this->backup_nella_cartella();
		$principale  = $GLOBALS['wpdb'];
		$this->altra = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->altra->set_prefix( $principale->prefix );

		$this->ammetti_transazione_esterna( false );

		// Le risposte della banca dati si scelgono qui: MySQL non ha la variabile, MariaDB si'.
		$risposta = 'SELECT 0';
		$elenco   = "SELECT 'in_transaction', 'ON'";
		$riscrivi = static function ( $query ) use ( &$risposta, &$elenco ) {
			if ( 'SELECT @@in_transaction' === $query ) {
				return $risposta;
			}

			return 0 === strpos( (string) $query, 'SHOW SESSION VARIABLES' ) ? $elenco : $query;
		};

		add_filter( 'query', $riscrivi );
		$GLOBALS['wpdb'] = $this->altra; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: la connessione di un altro componente.

		try {
			$this->altra->query( 'SET autocommit = 1' );

			$this->assertFalse( Repertorio::transazione_esterna(), 'Precondizione: scrittura automatica, nessuna transazione.' );

			$this->altra->query( 'START TRANSACTION' );
			$risposta = 'SELECT 1';

			$this->assertTrue( Repertorio::transazione_esterna(), 'Precondizione: la banca dati dice che la transazione e\' aperta.' );

			// La lettura fallisce su una banca dati che ha la variabile: non si sa, quindi transazione.
			$risposta = 'SELECT * FROM tabella_che_non_esiste';

			$this->assertTrue( Repertorio::transazione_esterna() );

			// Fallisce anche l'elenco delle variabili: ancora transazione.
			$elenco = 'SELECT * FROM tabella_che_non_esiste';

			$this->assertTrue( Repertorio::transazione_esterna() );

			$elenco = "SELECT 'in_transaction', 'ON'";

			$rifiutata = Importazione::avvia(
				$percorso,
				'backup.zip',
				array(
					'impronta' => hash_file( 'sha256', $percorso ),
					'atti'     => 6,
					'in_vista' => 2,
				),
				true,
				false
			);

			$this->assertWPError( $rifiutata );
			$this->assertSame( 'albo_importazione_transazione_esterna', $rifiutata->get_error_code() );

			// Il blocco dell'anno resta: lo rilascera' la fine della richiesta, non una lettura fallita.
			$this->assertTrue( Repertorio::blocca_anno( 2039 ) );
			Repertorio::sblocca_anno( 2039 );

			$this->assertSame( '0', (string) $principale->get_var( $principale->prepare( 'SELECT GET_LOCK( %s, 0 )', Repertorio::nome_blocco_anno( 2039 ) ) ), 'Il blocco e\' ancora tenuto.' );

			// Solo una banca dati che non ha la variabile nel suo elenco dice "nessuna transazione": una versione che non ne tiene il conto.
			$risposta = 'SELECT @@variabile_che_non_esiste';
			$elenco   = "SHOW SESSION VARIABLES LIKE 'variabile\\_che\\_non\\_esiste'";

			$this->assertFalse( Repertorio::transazione_esterna() );

			$this->altra->query( 'ROLLBACK' );
		} finally {
			$GLOBALS['wpdb'] = $principale; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- prova: si rimette la connessione della suite.
			remove_filter( 'query', $riscrivi );
		}

		$this->assertNull( Importazione::stato(), 'Niente si e\' scritto.' );
	}

	/**
	 * A-214. Un avvio o un'interruzione che la banca dati non salva non si dichiarano riusciti.
	 */
	public function test_a214_avvio_e_interruzione_non_salvati(): void {
		$percorso  = $this->backup_nella_cartella();
		$non_salva = static function ( $nuovo, $vecchio ) {
			unset( $nuovo );

			return $vecchio;
		};

		add_filter( 'pre_update_option_' . Importazione::OPZIONE, $non_salva, 10, 2 );
		$avvio = Importazione::avvia(
			$percorso,
			'backup.zip',
			array(
				'impronta' => hash_file( 'sha256', $percorso ),
				'atti'     => 6,
				'in_vista' => 2,
			),
			true,
			false
		);
		remove_filter( 'pre_update_option_' . Importazione::OPZIONE, $non_salva, 10 );

		$this->assertWPError( $avvio );
		$this->assertSame( 'albo_importazione_stato_non_salvato', $avvio->get_error_code() );
		$this->assertStringContainsString( 'Importazione non avviata', $avvio->get_error_message() );
		$this->assertNull( Importazione::stato() );

		$this->avvia_diretto( $percorso );
		$this->assertSame( 1, Importazione::prosegui( 1, microtime( true ) + 60 )['posizione'] );

		add_filter( 'pre_update_option_' . Importazione::OPZIONE, $non_salva, 10, 2 );
		$interruzione = Importazione::interrompi();
		remove_filter( 'pre_update_option_' . Importazione::OPZIONE, $non_salva, 10 );

		$this->assertWPError( $interruzione );
		$this->assertSame( 'albo_importazione_stato_non_salvato', $interruzione->get_error_code() );
		$this->assertSame( 'in_corso', Importazione::stato()['stato'], 'Lo stato salvato dice ancora "in corso", e la risposta non dice altro.' );

		// "Interrompi" di nuovo, questa volta salvato: "Continua" non porta piu' niente.
		$this->assertSame( 'interrotta', Importazione::interrompi()['stato'] );
		$this->assertWPError( Importazione::prosegui( 50, microtime( true ) + 60 ) );
		$this->assertSame( 1, Importazione::stato()['posizione'] );
	}

	/**
	 * Avvia l'importazione senza la seconda conferma, senza il primo blocco della schermata.
	 *
	 * @param string $percorso Backup.
	 * @param int    $atti     Atti da portare secondo il rapporto confermato.
	 */
	private function avvia_diretto( string $percorso, int $atti = 6 ): void {
		$avviata = Importazione::avvia(
			$percorso,
			'backup.zip',
			array(
				'impronta' => hash_file( 'sha256', $percorso ),
				'atti'     => $atti,
				'in_vista' => 2,
			),
			true,
			false
		);

		$this->assertIsArray( $avviata, is_wp_error( $avviata ) ? $avviata->get_error_message() : '' );
	}

	/**
	 * Tutti gli atti del tipo, in ogni stato, letti dalla banca dati.
	 *
	 * @param string|null $stato Solo gli atti in questo stato.
	 * @return array<int, array{id: int, stato: string, nome: string}>
	 */
	private function inventario( ?string $stato = null ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge cio' che c'e' davvero nella banca dati.
		$righe = $wpdb->get_results( $wpdb->prepare( 'SELECT ID, post_status, post_name FROM %i WHERE post_type = %s ORDER BY ID', $wpdb->posts, TIPO ), ARRAY_A );
		$atti  = array();

		foreach ( $righe as $riga ) {
			if ( null === $stato || $stato === $riga['post_status'] ) {
				$atti[] = array(
					'id'    => (int) $riga['ID'],
					'stato' => (string) $riga['post_status'],
					'nome'  => (string) $riga['post_name'],
				);
			}
		}

		return $atti;
	}

	/**
	 * L'inventario atteso quando ci sono solo gli atti importati.
	 *
	 * @return array<int, array{id: int, stato: string, nome: string}>
	 */
	private function importati_per_inventario(): array {
		$attesi = array();

		foreach ( $this->importati() as $origine => $atto ) {
			$attesi[] = array(
				'id'    => $atto['id'],
				'stato' => in_array( (string) $origine, self::ANNULLATI, true ) ? 'albo_annullato' : 'publish',
				'nome'  => (string) get_post_field( 'post_name', $atto['id'], 'raw' ),
			);
		}

		usort(
			$attesi,
			static function ( array $a, array $b ): int {
				return $a['id'] <=> $b['id'];
			}
		);

		return $attesi;
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

			// La schermata stessa non si apre, neanche con un invio valido.
			$_POST = $invio;

			try {
				ob_start();
				SchermataImportazione::mostra();
				$this->fail( 'La schermata si e\' aperta: ' . $chi );
			} catch ( \WPDieException $fermata ) {
				$this->assertStringContainsString( 'permesso', $fermata->getMessage(), $chi );
			} finally {
				ob_end_clean();
				$_POST = array();
			}
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

		// Il passaggio a pubblicato senza verifica non si concede a una bozza qualunque, neanche all'amministratore.
		$ordinaria   = $this->atto_completo();
		$con_ricordo = $this->atto_completo();
		$dati        = array(
			'anno'     => 2041,
			'numero'   => 30,
			'inizio'   => '2041-09-01',
			'fine'     => '2041-09-16',
			'dettagli' => array( 'conferma_dati_personali' => true ),
		);

		add_post_meta( $con_ricordo, META_ORIGINE_IMPORTAZIONE, '1', true );

		foreach ( array(
			'bozza ordinaria'              => $ordinaria,
			'bozza con il ricordo scritto' => $con_ricordo,
		) as $cosa => $bozza ) {
			$prima   = $this->fotografia( $bozza );
			$rifiuto = Passaggi::importa( $bozza, $dati );

			$this->assertWPError( $rifiuto, $cosa );
			$this->assertSame( 'albo_importazione_non_una_bozza', $rifiuto->get_error_code(), $cosa );
			$this->assertSame( $prima, $this->fotografia( $bozza ), $cosa . ': niente cambia.' );
		}

		// Anche la bozza che l'importazione sta scrivendo, se arriva visibile, vuole la conferma sui dati personali.
		$lavorazione = new \ReflectionProperty( Importazione::class, 'in_lavorazione' );
		$lavorazione->setAccessible( true );
		$lavorazione->setValue( null, $con_ricordo );

		try {
			$senza_conferma = Passaggi::importa(
				$con_ricordo,
				array(
					'fine'     => '2041-10-16',
					'inizio'   => '2041-10-01',
					'dettagli' => array( 'conferma_dati_personali' => 'si' ),
				) + $dati
			);
		} finally {
			$lavorazione->setValue( null, 0 );
		}

		$this->assertWPError( $senza_conferma );
		$this->assertSame( 'albo_importazione_conferma_mancante', $senza_conferma->get_error_code() );
		$this->assertSame( 'draft', get_post_status( $con_ricordo ) );
		$this->assertNull( Repertorio::di( $con_ricordo ) );

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

		// Il file della prova non arriva da un caricamento HTTP: lo si accetta come tale, solo qui.
		$verifica = new \ReflectionProperty( SchermataImportazione::class, 'verifica_caricamento' );
		$verifica->setAccessible( true );
		$verifica->setValue(
			null,
			static function ( string $file ) use ( $percorso ): bool {
				return $percorso === $file;
			}
		);

		try {
			ob_start();
			SchermataImportazione::mostra();
			$pagina = (string) ob_get_clean();
		} finally {
			$verifica->setValue( null, null );
		}

		ob_start();
		SchermataImportazione::stampa( Importazione::rapporto( $percorso ) );
		$rapporto = (string) ob_get_clean();

		$this->assertNotSame( '', $rapporto );
		$this->assertStringContainsString( $rapporto, $pagina, 'Precondizione: il file caricato si e\' letto e il suo rapporto c\'e\'.' );
		$this->assertStringNotContainsString( 'name="' . SchermataImportazione::CAMPO_GETTONE_IMPORTA . '"', $pagina );
	}

	/**
	 * A-217. I documenti arrivano byte per byte, il principale al suo posto; il rapporto finale si legge sulla schermata.
	 */
	public function test_a217_documenti_intatti_e_rapporto_finale(): void {
		$percorso = $this->backup_nella_cartella();

		// Un componente che si ferma sull'atto 13/2041 con un messaggio scritto in HTML: il rapporto lo mostra come testo.
		$ostile = static function ( $post_id, $post ) {
			if ( TIPO === $post->post_type && 'Atto annullato' === $post->post_title ) {
				throw new \RuntimeException( '<script>alert("ostile")</script><b>grassetto</b>' );
			}
		};

		add_action( 'save_post', $ostile, 10, 2 );
		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );
		remove_action( 'save_post', $ostile, 10 );

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
		$this->assertSame(
			array(
				'principale' => '089b6105cc06fe6f522472a4788bc30cbc79fd12742a8f55a554bf81d1a2875d',
				'ulteriori'  => array(),
			),
			$this->impronte_dei_documenti( $atti['14']['id'] ),
			'Il file unico segnato come allegato e\' il documento principale.'
		);
		$this->assertSame( array( 'principale' ), $this->voce_importazione( $atti['14']['id'] )['dettagli']['documenti_ruoli'] );
		$this->assertFalse( $this->voce_importazione( $atti['14']['id'] )['dettagli']['principale_da_scegliere'] );
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
		$this->assertStringContainsString( 'Importati 7 atti su 14 del backup.', $pagina );
		$this->assertStringContainsString( '13/2041', $pagina );
		$this->assertStringContainsString( '&lt;script&gt;alert(&quot;ostile&quot;)&lt;/script&gt;&lt;b&gt;grassetto&lt;/b&gt;', $pagina, 'Il messaggio arriva come testo.' );
		$this->assertStringNotContainsString( '<script>alert', $pagina );
		$this->assertStringNotContainsString( '<b>grassetto', $pagina );
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

	/**
	 * A-230. Un atto importato e poi annullato resta importato: il rilancio non lo riporta.
	 *
	 * L'annullato non e' piu' pubblicato, ma l'importazione lo riconosce
	 * ancora come suo: il rapporto lo conta fra gli importati e un rilancio,
	 * anche con la seconda conferma, non trova niente da portare.
	 */
	public function test_a230_annullato_dopo_l_importazione(): void {
		$percorso = $this->backup_nella_cartella();

		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$atti = $this->importati();
		$this->assertCount( 8, $atti, 'Precondizione: otto atti importati, compresi i due in pubblicazione.' );
		$this->assertSame( 'publish', $atti['2']['stato'], 'Precondizione: l\'atto in pubblicazione e\' pubblicato.' );

		$atto  = $atti['2']['id'];
		$esito = $this->come(
			$this->amministratore,
			static function () use ( $atto ) {
				return albo_pretorio_annulla( $atto, 'Atto adottato per errore materiale.' );
			}
		);
		$this->assertIsInt( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : 'Precondizione: l\'annullamento.' );
		$this->assertSame( 'albo_annullato', $this->importati()['2']['stato'], 'Precondizione: l\'atto e\' annullato.' );

		$prima    = $this->fotografie();
		$rapporto = $this->rapporto_di( $percorso );

		$this->assertSame( 8, $rapporto['riepilogo']['esiti'][ AnalisiImportazione::IMPORTATO ], 'Il rapporto conta gli annullati fra gli importati.' );
		$this->assertSame( array(), Importazione::selezione( $rapporto, true )['atti'], 'Niente da portare, nemmeno con la seconda conferma.' );

		$vuota = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) ) );

		$this->assertWPError( $vuota );
		$this->assertSame( 'albo_importazione_vuota', $vuota->get_error_code() );
		$this->assertSame( $prima, $this->fotografie(), 'Il rilancio non ha creato ne\' cambiato niente, l\'annullato compreso.' );
	}

	/**
	 * La voce dell'annullamento di un atto, letta dal registro.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private function voce_annullamento( int $atto_id ): array {
		$voci = $this->voci_di(
			$atto_id,
			array(
				'sezione' => SEZIONE,
				'azione'  => Passaggi::AZIONE_ANNULLAMENTO,
			)
		);

		$this->assertCount( 1, $voci, 'Una voce di annullamento.' );

		return $voci[0];
	}

	/**
	 * Quello che la pagina pubblica disegna per un indirizzo, da visitatore; nullo se "non trovata".
	 *
	 * @param string $indirizzo Indirizzo.
	 * @return string|null
	 */
	private function pubblico( string $indirizzo ): ?string {
		$GLOBALS['current_screen'] = null;
		wp_set_current_user( 0 );

		/*
		 * La costante per le memorie di pagina, una volta definita, resta per
		 * tutta la suite: la sua prova (A-202) vuole trovarla non definita.
		 * Qui si guarda cosa la pagina disegna, non le sue intestazioni.
		 */
		remove_action( 'wp', array( PaginaPubblica::class, 'da_wp' ) );

		try {
			$this->go_to( $indirizzo );

			if ( is_404() ) {
				return null;
			}

			$pagina          = get_queried_object();
			$GLOBALS['post'] = $pagina;
			setup_postdata( $pagina );

			return (string) apply_filters( 'the_content', $pagina->post_content );
		} finally {
			add_action( 'wp', array( PaginaPubblica::class, 'da_wp' ) );
			wp_set_current_user( $this->amministratore );
			unset( $GLOBALS['post'] );
		}
	}

	/**
	 * Tutto cio' che il punto pubblico di consegna emette per un documento, da visitatore.
	 *
	 * @param int $documento Documento.
	 * @return array<int, array<string, mixed>>
	 */
	private function consegna_pubblica( int $documento ): array {
		$consegne = array();

		\Conformita_Core_Consegna::fissa_emettitore(
			static function ( array $risposta ) use ( &$consegne ) {
				$consegne[] = $risposta;
			}
		);
		$GLOBALS['current_screen'] = null;
		wp_set_current_user( 0 );

		try {
			$this->go_to( (string) conformita_core_indirizzo_consegna( $documento ) );
		} finally {
			\Conformita_Core_Consegna::azzera_emettitore();
			wp_set_current_user( $this->amministratore );
		}

		return $consegne;
	}

	/**
	 * Gli atti nell'elenco pubblico disegnato.
	 *
	 * @param string $html Codice disegnato.
	 * @return array<int, int>
	 */
	private function elencati( string $html ): array {
		preg_match_all( '/<tr class="albo-pretorio__atto" role="row" data-atto="([0-9]+)"/', $html, $trovati );

		return array_map( 'intval', $trovati[1] );
	}

	/**
	 * A-231. L'annullato in pubblicazione arriva annullato, solo con la seconda conferma, e il pubblico ne vede l'avviso.
	 */
	public function test_a231_annullato_in_pubblicazione(): void {
		$this->set_permalink_structure( '' );

		$percorso = $this->backup_nella_cartella();
		$pagina   = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Albo pretorio',
				'post_content' => '[albo_pretorio]',
			)
		);

		// Senza la seconda conferma resta fuori, come l'atto in corso.
		$primo = $this->importa_tutto( $percorso );

		$this->assertArrayNotHasKey( '13', $this->importati() );
		$this->assertSame( array( '11/2041', '19/2041' ), $this->non_portati( $primo )['conferma_dati_personali'] );

		// Con la conferma arriva annullato.
		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$atti = $this->importati();
		$id   = $atti['13']['id'];

		// Prima dalla memoria, senza pulirla: l'importazione non deve lasciarci lo stato pubblicato.
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ), 'Annullato anche per chi legge dalla memoria.' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $atti['13']['stato'], 'Annullato nella banca dati.' );
		clean_post_cache( $id );
		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => 19,
			),
			Repertorio::di( $id )
		);
		$this->assertSame( '2041-10-01 00:00:00', get_post( $id )->post_date );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ) );
		$this->assertNotNull( DocumentiAtto::principale( $id ), 'Il documento c\'e\', intatto.' );
		$this->assertSame( '2041-10-05', $this->voce_importazione( $id )['dettagli']['annullamento'] );
		$this->assertTrue( $this->voce_importazione( $id )['dettagli']['conferma_dati_personali'] );

		$voce = $this->voce_annullamento( $id );

		$this->assertSame( 'Revoca', $voce['motivazione'] );
		$this->assertSame(
			array(
				'anno'         => 2041,
				'numero'       => 19,
				'fine'         => '2041-10-16',
				'annullato_il' => '2041-10-05',
				'origine'      => 'Albo Pretorio On line',
			),
			$voce['dettagli']
		);
		$this->assertCount(
			1,
			$this->voci_di(
				$id,
				array(
					'azione'  => 'rimozione',
					'origine' => 'automatica',
				)
			),
			'La voce automatica del cambio di stato.'
		);

		$annullamento = Passaggi::annullamento( $id );

		$this->assertSame( 'Revoca', $annullamento['motivo'] );
		$this->assertTrue( $annullamento['giorno'] );
		$this->assertSame( '2041-10-05 00:00:00', $annullamento['istante']->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ), 'La data del vecchio albo, non quella dell\'importazione.' );

		// Il riquadro dice la data senza ora.
		ob_start();
		RiquadroPassaggi::mostra( get_post( $id ) );
		$riquadro = (string) ob_get_clean();

		$this->assertStringContainsString( '<dd>' . esc_html( wp_date( get_option( 'date_format' ), $annullamento['istante']->getTimestamp() ) ) . '</dd>', $riquadro );
		$this->assertStringContainsString( 'Revoca', $riquadro );

		// Il pubblico, alle 00:30 del 16: in elenco con l'avviso, senza documenti.
		$elenco = (string) $this->pubblico( (string) get_permalink( $pagina ) );

		$this->assertEqualsCanonicalizing( array( $atti['2']['id'], $id ), $this->elencati( $elenco ), 'In elenco l\'atto in corso e l\'annullato in pubblicazione.' );
		$this->assertSame( 1, preg_match( '/data-atto="' . $id . '"(.*?)<\/tr>/s', $elenco, $riga ) );
		$this->assertStringContainsString( 'Annullato', $riga[1] );
		$this->assertStringContainsString( 'nessuno: atto annullato', $riga[1] );

		$scheda = $this->pubblico( add_query_arg( PaginaPubblica::PARAMETRO_ATTO, $id, (string) get_permalink( $pagina ) ) );

		$this->assertNotNull( $scheda, 'La scheda dell\'annullato si apre.' );
		$this->assertStringContainsString( '<dt>Annullato il</dt><dd>05/10/2041</dd>', $scheda );
		$this->assertStringContainsString( '<dt>Motivo dell&#039;annullamento</dt><dd>Revoca</dd>', $scheda );

		// I documenti: lo stesso rifiuto, per intero, del documento di uno scaduto; controllo positivo sull'atto in corso.
		$rifiuto = $this->consegna_pubblica( (int) DocumentiAtto::principale( $atti['1']['id'] ) );

		$this->assertNotSame( 200, $rifiuto[0]['stato'] ?? null, 'Precondizione: il documento dello scaduto non si scarica.' );
		$this->assertSame( $rifiuto, $this->consegna_pubblica( (int) DocumentiAtto::principale( $id ) ) );
		$this->assertSame( 200, $this->consegna_pubblica( (int) DocumentiAtto::principale( $atti['2']['id'] ) )[0]['stato'] ?? null, 'Il documento dell\'atto in corso si scarica.' );

		// Dalla mezzanotte del 17 sparisce.
		$this->fissa_orologi( '2041-10-17 00:00:00' );

		$this->assertSame( array(), $this->elencati( (string) $this->pubblico( (string) get_permalink( $pagina ) ) ) );
		$this->assertNull( $this->pubblico( add_query_arg( PaginaPubblica::PARAMETRO_ATTO, $id, (string) get_permalink( $pagina ) ) ), 'La scheda risponde "non trovata".' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ), 'Lo stato resta annullato: a toglierlo e\' la data.' );
	}

	/**
	 * A-232. L'annullato scaduto arriva annullato e si trova nella consultazione riservata; il motivo vuoto si dice.
	 */
	public function test_a232_annullato_scaduto(): void {
		$percorso = $this->backup_nella_cartella();

		$this->importa_tutto( $percorso );

		$id = $this->importati()['4']['id'];

		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $this->importati()['4']['stato'] );
		$this->assertTrue( conformita_core_scaduto( $id ) );
		$this->assertSame( 'Errore materiale', $this->voce_annullamento( $id )['motivazione'] );
		$this->assertSame( '2041-09-10', $this->voce_annullamento( $id )['dettagli']['annullato_il'] );

		$_GET     = array();
		$_REQUEST = array();

		ob_start();
		SchermataScaduti::mostra();
		$schermata = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '/<tr data-atto="' . $id . '">(.*?)<\/tr>/s', $schermata, $riga ), 'L\'annullato scaduto e\' elencato.' );
		$this->assertStringContainsString( '<strong>Annullato il</strong>: 10/09/2041', $riga[1] );
		$this->assertStringContainsString( '<strong>Motivo dell&#039;annullamento</strong>: Errore materiale', $riga[1] );
	}

	/**
	 * A-232. Il motivo vuoto in Albo Pretorio On line si scrive come "non indicato".
	 */
	public function test_a232_motivo_vuoto(): void {
		$atti = array_map(
			static function ( array $atto ): array {
				if ( 4 === (int) $atto['IdAtto'] ) {
					$atto['MotivoAnnullamento'] = '  ';
				}

				return $atto;
			},
			$this->atti_di_prova()
		);

		$this->importa_tutto( $this->backup_nella_cartella( $atti ) );

		$id = $this->importati()['4']['id'];

		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ) );
		$this->assertSame( 'non indicato in Albo Pretorio On line', $this->voce_annullamento( $id )['motivazione'] );
	}

	/**
	 * A-233. Tutto o niente: un pezzo dell'annullamento che fallisce lascia l'atto non importato, con il numero libero.
	 */
	public function test_a233_tutto_o_niente(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();
		$registro = $wpdb->prefix . 'conformita_core_registro';
		$guasti   = array(
			'scrittura dello stato annullato' => array( 'albo_stato_non_scritto', array( 'UPDATE `' . $wpdb->posts . '`', "'" . ChiusuraPubblicazione::ANNULLATO . "'" ) ),
			'voce automatica della rimozione' => array( 'albo_voce_automatica_mancante', array( 'INSERT INTO `' . $registro . '`', "'rimozione'" ) ),
			'voce dell\'annullamento'         => array( 'albo_voce_non_scritta', array( 'INSERT INTO `' . $registro . '`', "'" . Passaggi::AZIONE_ANNULLAMENTO . "'" ) ),
		);

		foreach ( $guasti as $caso => list( $codice, $pezzi ) ) {
			$guasto = static function ( $istruzione ) use ( $pezzi ) {
				foreach ( $pezzi as $pezzo ) {
					if ( false === strpos( (string) $istruzione, $pezzo ) ) {
						return $istruzione;
					}
				}

				return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
			};

			$wpdb->suppress_errors( true );
			add_filter( 'query', $guasto );

			try {
				$stato = $this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );
			} finally {
				remove_filter( 'query', $guasto );
				$wpdb->suppress_errors( false );
			}

			$this->assertSame( array( '1', '2', '3', '5', '12', '14' ), array_map( 'strval', array_keys( $this->importati() ) ), $caso . ': gli annullati non esistono, gli altri si\'.' );
			$this->assertSame( array( '13/2041', '19/2041' ), array_column( $stato['non_riusciti'], 'numero' ), $caso );
			$this->assertSame( array( $codice, $codice ), array_column( $stato['non_riusciti'], 'codice' ), $caso . ': con il codice del pezzo fallito.' );
			$this->assertSame( array(), Repertorio::atti_numerati( 2041, 13 ), $caso . ': il numero 13/2041 e\' libero.' );
			$this->assertSame( array(), Repertorio::atti_numerati( 2041, 19 ), $caso . ': il numero 19/2041 e\' libero.' );
			$this->assertSame( $this->importati_per_inventario(), $this->inventario(), $caso . ': nessuna bozza rimasta.' );
		}

		// Senza guasti il rilancio li porta annullati.
		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$atti = $this->importati();

		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $atti['4']['stato'] );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $atti['13']['stato'] );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
	}

	/**
	 * A-233. Data o motivo dell'annullamento non validi: il passaggio rifiuta prima di scrivere.
	 */
	public function test_a233_annullamento_non_valido(): void {
		$bozza = $this->atto_completo();

		add_post_meta( $bozza, META_ORIGINE_IMPORTAZIONE, '4', true );

		$lavorazione = new \ReflectionProperty( Importazione::class, 'in_lavorazione' );
		$lavorazione->setAccessible( true );
		$lavorazione->setValue( null, $bozza );

		$prima = $this->fotografia( $bozza );

		try {
			foreach ( array(
				'data impossibile'   => array(
					'il'     => '2041-02-30',
					'motivo' => 'Revoca',
				),
				'data futura'        => array(
					'il'     => '2041-10-17',
					'motivo' => 'Revoca',
				),
				'data con l\'ora'    => array(
					'il'     => '2041-10-05 10:00:00',
					'motivo' => 'Revoca',
				),
				'data non testo'     => array(
					'il'     => 20411005,
					'motivo' => 'Revoca',
				),
				'motivo non testo'   => array(
					'il'     => '2041-10-05',
					'motivo' => array( 'Revoca' ),
				),
				'motivo assente'     => array( 'il' => '2041-10-05' ),
				'annullamento testo' => 'si',
			) as $caso => $annullamento ) {
				$rifiuto = Passaggi::importa(
					$bozza,
					array(
						'anno'         => 2041,
						'numero'       => 30,
						'inizio'       => '2041-09-01',
						'fine'         => '2041-09-16',
						'annullamento' => $annullamento,
						'dettagli'     => array( 'conferma_dati_personali' => true ),
					)
				);

				$this->assertWPError( $rifiuto, $caso );
				$this->assertSame( 'albo_importazione_annullamento_non_valido', $rifiuto->get_error_code(), $caso );
				$this->assertSame( $prima, $this->fotografia( $bozza ), $caso . ': niente cambia.' );
			}
		} finally {
			$lavorazione->setValue( null, 0 );
		}
	}

	/**
	 * A-234. Il rilancio riconosce gli annullati importati e non tocca niente.
	 */
	public function test_a234_rilancio(): void {
		$percorso = $this->backup_nella_cartella();

		$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$prima    = $this->fotografie();
		$rapporto = $this->rapporto_di( $percorso );

		$this->assertSame( 8, $rapporto['riepilogo']['esiti'][ AnalisiImportazione::IMPORTATO ] );
		$this->assertSame( array(), Importazione::selezione( $rapporto, true )['atti'], 'Niente da portare.' );

		$vuota = SchermataImportazione::importazione_da_invio( $this->invio_avvia( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) ) );

		$this->assertSame( 'albo_importazione_vuota', $vuota->get_error_code() );
		$this->assertSame( $prima, $this->fotografie(), 'Il rilancio non ha creato ne\' cambiato niente.' );
	}

	/**
	 * A-234. Un'eccezione di un altro componente dopo la conferma dell'annullato: l'atto e' fra gli importati.
	 */
	public function test_a234_eccezione_dopo_la_conferma(): void {
		global $wpdb;

		$percorso = $this->backup_nella_cartella();
		$lanciata = false;
		$lancia   = static function ( $post_id ) use ( &$lanciata, $wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato vero, non quello in memoria.
			$riga = $wpdb->get_row( $wpdb->prepare( 'SELECT post_status, post_title FROM %i WHERE ID = %d', $wpdb->posts, $post_id ), ARRAY_A );

			if ( ! $lanciata && is_array( $riga ) && ChiusuraPubblicazione::ANNULLATO === $riga['post_status'] && 'Annullato in pubblicazione' === $riga['post_title'] && ! Passaggi::in_corso( (int) $post_id ) ) {
				$lanciata = true;

				throw new \RuntimeException( 'guasto dopo la conferma' );
			}
		};

		add_action( 'clean_post_cache', $lancia );

		try {
			$finale = $this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );
		} finally {
			remove_action( 'clean_post_cache', $lancia );
		}

		$this->assertTrue( $lanciata, 'Precondizione: l\'eccezione dopo la conferma e\' partita.' );
		$this->assertArrayHasKey( '13', $finale['importati'], 'L\'annullato confermato prima dell\'eccezione e\' fra gli importati.' );
		$this->assertSame( array(), $finale['non_riusciti'] );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $this->importati()['13']['stato'] );
		$this->assertSame( $this->importati_per_inventario(), $this->inventario() );
	}

	/**
	 * A-233. Finite le scritture dell'annullamento, durante il controllo finale, la fine non si riscrive.
	 */
	public function test_a233_nessuna_concessione_durante_i_controlli(): void {
		$this->riscrive_la_fine_durante( 'controlli' );
	}

	/**
	 * A-233. Finite le scritture dell'annullamento, alla conferma, la fine non si riscrive.
	 */
	public function test_a233_nessuna_concessione_alla_conferma(): void {
		$this->riscrive_la_fine_durante( 'conferma' );
	}

	/**
	 * Importa con un componente che, finita la voce dell'annullamento dell'atto 19/2041, prova a riscriverne la fine.
	 *
	 * @param string $caso `controlli`: alla lettura dell'atto del controllo finale; `conferma`: alla conferma della transazione.
	 */
	private function riscrive_la_fine_durante( string $caso ): void {
		global $wpdb;

		$percorso    = $this->backup_nella_cartella();
		$chiave      = conformita_core_chiave_fine_pubblicazione();
		$tabella     = $wpdb->prefix . 'conformita_core_registro';
		$lavorazione = new \ReflectionProperty( Importazione::class, 'in_lavorazione' );
		$lavorazione->setAccessible( true );

		$scritte    = 0;
		$tentativi  = array();
		$intromette = static function ( $istruzione ) use ( $wpdb, $tabella, $chiave, $caso, $lavorazione, &$scritte, &$tentativi ) {
			if ( ! is_string( $istruzione ) || array() !== $tentativi ) {
				return $istruzione;
			}

			// L'ultima scrittura dell'annullamento e' la sua voce nel registro; quella dell'atto 19/2041 nei dettagli ha il numero 19.
			if ( 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) && false !== strpos( $istruzione, "'" . Passaggi::AZIONE_ANNULLAMENTO . "'" ) && 1 === preg_match( '/numero\\\\?":19,/', $istruzione ) ) {
				$scritte = (int) $lavorazione->getValue();

				return $istruzione;
			}

			$e_il_momento = 'controlli' === $caso
				? 1 === preg_match( '/^\s*SELECT \* FROM `?' . preg_quote( $wpdb->posts, '/' ) . '`? WHERE ID = ' . $scritte . '\s*$/', $istruzione )
				: 1 === preg_match( '/^\s*(COMMIT|RELEASE SAVEPOINT)/i', $istruzione );

			if ( $scritte > 0 && $e_il_momento ) {
				$tentativi = array( 'in corso' );
				$tentativi = array(
					'riscrive' => update_post_meta( $scritte, $chiave, '2041-12-31' ),
					'diretta'  => $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: la scrittura dell'aggancio.
						$wpdb->postmeta,
						array( 'meta_value' => '2041-12-31' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- la scrittura dell'aggancio.
						array(
							'post_id'  => $scritte,
							'meta_key' => $chiave, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- la scrittura dell'aggancio.
						)
					),
				);
			}

			return $istruzione;
		};

		add_filter( 'query', $intromette );

		try {
			$this->importa_tutto( $percorso, array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );
		} finally {
			remove_filter( 'query', $intromette );
		}

		$id = $this->importati()['13']['id'];

		clean_post_cache( $id );

		$this->assertSame( $id, $scritte, $caso . ': precondizione, la voce dell\'annullamento e\' di questo atto.' );
		$this->assertArrayHasKey( 'riscrive', $tentativi, $caso . ': precondizione, l\'aggancio ha provato.' );
		$this->assertFalse( $tentativi['riscrive'], $caso . ': la riscrittura con le funzioni di WordPress e\' rifiutata.' );
		$this->assertSame( 0, $tentativi['diretta'], $caso . ': la riscrittura diretta non tocca niente.' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $id ), $caso . ': l\'atto e\' annullato.' );
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $id ), $caso . ': la fine e\' quella di Albo Pretorio On line.' );
	}

	/**
	 * A-233. Una data dell'annullamento presente ma impossibile nel backup: l'atto non si porta, come un conflitto.
	 */
	public function test_a233_data_dell_annullamento_illeggibile(): void {
		$atti = array_map(
			static function ( array $atto ): array {
				if ( 13 === (int) $atto['IdAtto'] ) {
					$atto['DataAnnullamento'] = '2041-02-30';
				}

				return $atto;
			},
			$this->atti_di_prova()
		);

		$stato = $this->importa_tutto( $this->backup_nella_cartella( $atti ), array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$this->assertArrayNotHasKey( '13', $this->importati(), 'L\'atto con la data illeggibile non si porta, ne\' pubblicato ne\' annullato.' );
		$this->assertSame( array( '19/2041' ), $this->non_portati( $stato )['annullamento_non_leggibile'] ?? null );
		$this->assertSame( array(), Repertorio::atti_numerati( 2041, 19 ) );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, $this->importati()['4']['stato'], 'Controllo positivo: l\'altro annullato si porta.' );
		$this->assertSame( 'publish', $this->importati()['2']['stato'], 'Controllo positivo: l\'atto in corso si porta, con il valore vuoto del vecchio albo.' );
	}

	/**
	 * A-231. La data del vecchio albo vale solo per l'annullamento portato dall'importazione.
	 *
	 * Una voce di annullamento con una data nei dettagli, scritta da un altro
	 * componente su un atto non importato, con un'origine diversa, o con una
	 * data dopo la voce stessa, si legge con l'istante della voce.
	 */
	public function test_a231_data_del_vecchio_albo_solo_dall_importazione(): void {
		$casi = array(
			'senza voce di importazione'  => array( null, '2041-10-03', Passaggi::ORIGINE_ANNULLAMENTO_IMPORTATO, false ),
			'importazione con altra data' => array( '2041-10-02', '2041-10-03', Passaggi::ORIGINE_ANNULLAMENTO_IMPORTATO, false ),
			'origine diversa'             => array( '2041-10-03', '2041-10-03', 'altro componente', false ),
			'data dopo la voce'           => array( '2041-10-06', '2041-10-06', Passaggi::ORIGINE_ANNULLAMENTO_IMPORTATO, false ),
			'tutto coerente'              => array( '2041-10-03', '2041-10-03', Passaggi::ORIGINE_ANNULLAMENTO_IMPORTATO, true ),
		);

		foreach ( $casi as $caso => list( $importato, $il, $origine, $vale ) ) {
			$this->fissa_orologi( '2041-10-01 09:30:00' );

			$id = $this->atto_in_verifica( 'normativo' );

			$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ), $caso . ': precondizione, pubblicato.' );
			$this->fissa_orologi( '2041-10-05 10:00:00' );

			if ( null !== $importato ) {
				$this->assertIsInt(
					conformita_core_registra_voce(
						array(
							'sezione'   => SEZIONE,
							'azione'    => Passaggi::AZIONE_IMPORTAZIONE,
							'contenuto' => $id,
							'dettagli'  => array( 'annullamento' => $importato ),
						)
					),
					$caso . ': precondizione, la voce di importazione.'
				);
			}

			$voce = conformita_core_registra_voce(
				array(
					'sezione'     => SEZIONE,
					'azione'      => Passaggi::AZIONE_ANNULLAMENTO,
					'contenuto'   => $id,
					'motivazione' => 'Scritta da un altro componente.',
					'dettagli'    => array(
						'annullato_il' => $il,
						'origine'      => $origine,
					),
				)
			);

			$this->assertIsInt( $voce, $caso . ': precondizione, la voce.' );

			$letta = Passaggi::annullamento( $id );

			$this->assertSame( $vale, $letta['giorno'], $caso );
			$this->assertSame(
				$vale ? $il . ' 00:00:00' : $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_ANNULLAMENTO ) )[0]['istante'],
				$letta['istante']->setTimezone( $vale ? wp_timezone() : new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				$caso . ': la data letta.'
			);
		}
	}

	/**
	 * A-231. Se la voce di importazione non si legge, la data dell'annullamento importato non si legge: non si sostituisce con l'istante della voce.
	 */
	public function test_a231_data_con_il_registro_che_non_risponde(): void {
		global $wpdb;

		$this->importa_tutto( $this->backup_nella_cartella(), array( SchermataImportazione::CAMPO_CONFERMA_VISIBILI => '1' ) );

		$id       = $this->importati()['13']['id'];
		$registro = $wpdb->prefix . 'conformita_core_registro';
		$guasti   = 0;
		$guasto   = static function ( $istruzione ) use ( $registro, &$guasti ) {
			if ( is_string( $istruzione ) && 1 === preg_match( '/^\s*SELECT/i', $istruzione ) && false !== strpos( $istruzione, $registro ) && false !== strpos( $istruzione, "'" . Passaggi::AZIONE_IMPORTAZIONE . "'" ) ) {
				++$guasti;

				return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
			}

			return $istruzione;
		};

		$wpdb->suppress_errors( true );
		add_filter( 'query', $guasto );

		try {
			$letta = Passaggi::annullamento( $id );
		} finally {
			remove_filter( 'query', $guasto );
			$wpdb->suppress_errors( false );
		}

		$this->assertGreaterThan( 0, $guasti, 'Precondizione: la lettura della voce di importazione e\' fallita.' );
		$this->assertNull( $letta, 'Data non leggibile in questo momento, non l\'istante della voce.' );

		$letta = Passaggi::annullamento( $id );

		$this->assertTrue( $letta['giorno'], 'Tolto il guasto, la data del vecchio albo.' );
		$this->assertSame( '2041-10-05', $letta['istante']->setTimezone( wp_timezone() )->format( 'Y-m-d' ) );
	}
}
