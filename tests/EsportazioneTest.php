<?php
/**
 * L'esportazione di un anno del registro per la conservazione: righe A-308..A-314 (ALBO-17, ALBO-29).
 *
 * Gli atti si portano a pubblicato, si tolgono, si annullano e si oscurano
 * con i passaggi veri, con l'orologio fissato. L'archivio si apre con
 * `ZipArchive` e i dati si leggono con `json_decode` e `str_getcsv`, non con
 * il componente; le impronte attese si calcolano qui, sui file usati. Poi
 * l'archivio si rilegge con la funzione del componente, che deve dare gli
 * stessi dati.
 *
 * Il calendario: un atto pubblicato il 1 settembre 2041 (numero 1, gia'
 * scaduto il 16 settembre, con il compito pianificato fermo), poi uno al
 * giorno dal 1 al 4 ottobre (numeri 2..5), uno importato il 4 ottobre
 * (numero 6); il 5 ottobre si toglie il 3, si annulla il 4 e si oscura un
 * allegato del 5. L'esportazione si fa il 10 gennaio 2042, quando c'e' anche
 * un atto numerato del 2042 e una bozza, che non devono comparire.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\AmministrazioniOspitate;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Esportazione;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\SchedaAtto;
use AlboPretorioPa\SchermataEsportazione;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\META_ORIGINE_IMPORTAZIONE;
use const AlboPretorioPa\META_SOSTITUITO_DA;
use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/**
 * Contenuto, permessi e integrita' dell'esportazione.
 */
class EsportazioneTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Il motivo dell'oscuramento.
	 */
	private const MOTIVO_OSCURAMENTO = 'L\'allegato riporta l\'indirizzo di casa del destinatario.';

	/**
	 * Le consegne della schermata.
	 *
	 * @var array<int, array{percorso: string, nome: string, impronta: string}>
	 */
	private array $consegne = array();

	/**
	 * Gli archivi costruiti dalle prove, da cancellare.
	 *
	 * @var array<int, string>
	 */
	private array $archivi = array();

	/**
	 * Il guasto attivo sulle istruzioni, o nullo.
	 *
	 * @var callable|null
	 */
	private $guasto = null;

	/**
	 * Assetto comune e consegna osservata.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();

		SchermataEsportazione::fissa_emettitore(
			function ( string $percorso, string $nome ): void {
				$this->consegne[] = array(
					'percorso' => $percorso,
					'nome'     => $nome,
					'impronta' => (string) hash_file( 'sha256', $percorso ),
				);
			}
		);

		add_filter( 'query', array( $this, 'guasta' ) );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'query', array( $this, 'guasta' ) );
		$this->guasto = null;
		$wpdb->suppress_errors( false );

		SchermataEsportazione::fissa_emettitore( null );
		$_POST = array();

		foreach ( $this->archivi as $archivio ) {
			if ( file_exists( $archivio ) ) {
				wp_delete_file( $archivio );
			}
		}

		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Sostituisce con un'istruzione che fallisce quelle che il guasto attivo riconosce.
	 *
	 * @param string $istruzione Istruzione.
	 * @return string
	 */
	public function guasta( $istruzione ) {
		if ( null !== $this->guasto && ( $this->guasto )( (string) $istruzione ) ) {
			return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
		}

		return $istruzione;
	}

	/**
	 * Un atto pubblicato con il passaggio vero all'istante indicato, con gli allegati indicati.
	 *
	 * @param string             $istante  Data e ora nel fuso del sito.
	 * @param string             $oggetto  Oggetto.
	 * @param array<int, string> $allegati Contenuti degli allegati ulteriori.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto, array $allegati = array() ): int {
		$this->fissa_orologi( $istante );

		$id = $this->atto_completo( 'normativo', array( 'post_title' => $oggetto ) );

		foreach ( $allegati as $indice => $contenuto ) {
			$this->assertIsInt( DocumentiAtto::deposita( $id, $this->file_temporaneo( 'allegato-' . $indice . '.pdf', $contenuto ), DocumentiAtto::ULTERIORE, 'percorso_locale' ) );
		}

		$this->come(
			$this->redattore,
			static function () use ( $id ) {
				return wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'pending',
					)
				);
			}
		);
		clean_post_cache( $id );

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		return $id;
	}

	/**
	 * L'impronta di un documento calcolata qui, sul file conservato.
	 *
	 * @param int $documento Documento.
	 * @return string
	 */
	private function impronta( int $documento ): string {
		return (string) hash_file( 'sha256', (string) get_attached_file( $documento ) );
	}

	/**
	 * Prepara l'anno di prova e restituisce gli atti per nome, con le impronte attese dei documenti.
	 *
	 * @return array{atti: array<string, int>, impronte: array<string, array<int, string>>, oscurato: array{entrante: int, uscente: int, contenuto: string}}
	 */
	private function anno_di_prova(): array {
		$atti = array(
			'scaduto'   => $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto gia\' scaduto' ),
			'corso'     => $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso' ),
			'tolto'     => $this->pubblicato_il( '2041-10-02 09:30:00', 'Atto che si togliera\'' ),
			'annullato' => $this->pubblicato_il( '2041-10-03 09:30:00', 'Atto che si annullera\'' ),
			'oscurato'  => $this->pubblicato_il( '2041-10-04 09:30:00', 'Atto che si oscurera\'', array( "%PDF-1.4\n% allegato con l'indirizzo\n%%EOF\n" ) ),
			'importato' => $this->pubblicato_il( '2041-10-04 11:00:00', 'Atto importato' ),
		);

		$importato = $atti['importato'];
		$this->scavalca_barriera(
			static function () use ( $importato ) {
				add_post_meta( $importato, META_ORIGINE_IMPORTAZIONE, '501', true );
			}
		);

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$tolto     = $atti['tolto'];
		$annullato = $atti['annullato'];
		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $tolto ) {
					return albo_pretorio_defiggi( $tolto, 'senza_norma', 'Pubblicato senza una norma che lo preveda.' );
				}
			)
		);
		$this->assertIsInt(
			$this->come(
				$this->pubblicatore,
				static function () use ( $annullato ) {
					return albo_pretorio_annulla( $annullato, 'Atto pubblicato per errore.' );
				}
			)
		);

		$oscurato  = $atti['oscurato'];
		$uscente   = DocumentiAtto::ulteriori( $oscurato )[0];
		$contenuto = "%PDF-1.4\n% allegato oscurato\n%%EOF\n";
		$file      = $this->file_temporaneo( 'oscurato.pdf', $contenuto );
		$esito     = $this->come(
			$this->pubblicatore,
			static function () use ( $oscurato, $uscente, $file ) {
				return albo_pretorio_oscura(
					$oscurato,
					$uscente,
					$file,
					'percorso_locale',
					array(
						'conferma' => true,
						'causa'    => Passaggi::CAUSA_DATI_ECCEDENTI,
						'motivo'   => self::MOTIVO_OSCURAMENTO,
					)
				);
			}
		);
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		$impronte = array();

		foreach ( $atti as $nome => $atto ) {
			$impronte[ $nome ] = array_map( array( $this, 'impronta' ), array_merge( array( (int) DocumentiAtto::principale( $atto ) ), DocumentiAtto::ulteriori( $atto ) ) );
		}

		$this->assertSame( hash( 'sha256', $contenuto ), $impronte['oscurato'][1], 'Precondizione: l\'allegato e\' la versione oscurata.' );

		// Una bozza, e un atto dell'anno dopo.
		$this->atto_completo( 'normativo', array( 'post_title' => 'Bozza' ) );
		$this->fissa_orologi( '2042-01-10 10:00:00' );
		$del_2042 = $this->pubblicato_il( '2042-01-10 10:00:00', 'Atto del 2042' );
		$this->assertSame( 2042, Repertorio::di( $del_2042 )['anno'], 'Precondizione: l\'atto del 2042 ha un numero del 2042.' );

		return array(
			'atti'     => $atti,
			'impronte' => $impronte,
			'oscurato' => array(
				'entrante'  => $esito['documento'],
				'uscente'   => $uscente,
				'contenuto' => $contenuto,
			),
		);
	}

	/**
	 * Esporta come l'utente indicato.
	 *
	 * @param int $anno   Anno.
	 * @param int $utente Utente.
	 * @return mixed
	 */
	private function esporta( int $anno, int $utente ) {
		$esito = $this->come(
			$utente,
			static function () use ( $anno ) {
				return albo_pretorio_esporta_anno( $anno );
			}
		);

		if ( is_array( $esito ) ) {
			$this->archivi[] = $esito['percorso'];
		}

		return $esito;
	}

	/**
	 * Il contenuto di un archivio letto con `ZipArchive`, per nome.
	 *
	 * @param string $percorso Archivio.
	 * @return array<string, string>
	 */
	private function apri( string $percorso ): array {
		$archivio = new \ZipArchive();

		$this->assertTrue( $archivio->open( $percorso, \ZipArchive::RDONLY ), 'L\'archivio si apre.' );

		$contenuto = array();

		$quanti = $archivio->count();

		for ( $i = 0; $i < $quanti; $i++ ) {
			$nome               = (string) $archivio->getNameIndex( $i );
			$contenuto[ $nome ] = (string) $archivio->getFromIndex( $i );
		}

		$archivio->close();

		return $contenuto;
	}

	/**
	 * Le voci di esportazione nel registro.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function voci_esportazione(): array {
		return (array) conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE,
				'azione'  => Esportazione::AZIONE,
			)
		);
	}

	/**
	 * Un utente che consulta gli scaduti e basta.
	 *
	 * @return int
	 */
	private function consultatore(): int {
		return $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );
	}

	/**
	 * I codici di un rifiuto.
	 *
	 * @param mixed $esito Esito.
	 * @return array<int, string>
	 */
	private function codici( $esito ): array {
		$this->assertWPError( $esito, 'Doveva essere rifiutato.' );

		return $esito->get_error_codes();
	}

	/**
	 * A-308: l'archivio contiene tutti e soli gli atti numerati dell'anno, con dati, documenti, versioni sostituite, storia e impronte, e si rilegge.
	 */
	public function test_a308_contenuto_dell_esportazione(): void {
		$prova = $this->anno_di_prova();
		$atti  = $prova['atti'];

		$esito = $this->esporta( 2041, $this->consultatore() );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 6, $esito['atti'], 'Sei atti numerati nel 2041.' );

		$contenuto = $this->apri( $esito['percorso'] );
		$dati      = json_decode( $contenuto['atti.json'], true );

		$this->assertSame( 'albo-pretorio-esportazione', $dati['formato'] );
		$this->assertSame( 1, $dati['versione'] );
		$this->assertSame( 2041, $dati['anno'] );
		$this->assertSame( 'Europe/Rome', $dati['fuso'] );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6 ), array_column( $dati['atti'], 'numero' ), 'Tutti e soli i numeri del 2041, in ordine: niente bozza, niente 2042.' );
		$this->assertSame( array( 2041 ), array_values( array_unique( array_column( $dati['atti'], 'anno' ) ) ) );
		$this->assertSame(
			array( 'Atto gia\' scaduto', 'Atto in corso', 'Atto che si togliera\'', 'Atto che si annullera\'', 'Atto che si oscurera\'', 'Atto importato' ),
			array_column( $dati['atti'], 'oggetto' )
		);
		$this->assertSame( array( 'pubblicato', 'pubblicato', 'defisso', 'annullato', 'pubblicato', 'pubblicato' ), array_column( $dati['atti'], 'stato' ), 'Lo stato scritto: lo scaduto con il compito pianificato fermo resta pubblicato.' );
		$this->assertSame( array( true, true, true, true, true, true ), array_column( $dati['atti'], 'fine_passata' ), 'Il 10 gennaio 2042 sono tutti scaduti, letto dalla data.' );
		$this->assertSame( '2041-09-01T09:30:00+02:00', $dati['atti'][0]['inizio'], 'L\'inizio con il fuso del sito.' );
		$this->assertSame( '2041-09-16', $dati['atti'][0]['fine'] );
		$this->assertSame( '2041-10-04', $dati['atti'][2]['fine'], 'Il tolto finisce il giorno prima della defissione.' );
		$this->assertSame( 'Deliberazione', $dati['atti'][1]['tipo'] );
		$this->assertSame( 'Giunta', $dati['atti'][1]['organo'] );
		$this->assertSame( '2041-09-10', $dati['atti'][1]['data_adozione'] );
		$this->assertSame( '501', $dati['atti'][5]['origine_importazione'] );
		$this->assertNull( $dati['atti'][1]['origine_importazione'] );
		$this->assertNull( $dati['atti'][1]['amministrazione'], 'Un atto di questa amministrazione.' );

		// I documenti: impronte calcolate qui, e i file nell'archivio identici.
		foreach ( array_values( $atti ) as $indice => $atto ) {
			$nome      = array_keys( $atti )[ $indice ];
			$documenti = $dati['atti'][ $indice ]['documenti'];
			$indicati  = array_values(
				array_filter(
					$documenti,
					static function ( array $documento ): bool {
						return 'sostituito' !== $documento['posto'];
					}
				)
			);
			$attese    = $prova['impronte'][ $nome ];

			$this->assertSame( $attese, array_column( array_column( $indicati, 'impronta' ), 'valore' ), $nome . ': le impronte dei documenti indicati.' );
			$this->assertSame( array( 'sha256' ), array_values( array_unique( array_column( array_column( $documenti, 'impronta' ), 'algoritmo' ) ) ), $nome . ': l\'algoritmo.' );
			$this->assertSame( 'principale', $documenti[0]['posto'], $nome . ': il principale per primo.' );

			foreach ( $documenti as $documento ) {
				$this->assertArrayHasKey( $documento['file'], $contenuto, $nome . ': ' . $documento['file'] . ' e\' nell\'archivio.' );
				$this->assertSame( $documento['impronta']['valore'], hash( 'sha256', $contenuto[ $documento['file'] ] ), $nome . ': il file nell\'archivio ha la sua impronta.' );
			}
		}

		$this->assertSame( 'documenti/2041-0002/albo-2041-0002-dal-01-10-al-16-10.pdf', $dati['atti'][1]['documenti'][0]['file'], 'I documenti si chiamano come li scarica il pubblico.' );

		// L'oscurato: l'allegato entrante, e la versione sostituita con il suo rinvio.
		$oscurato = $dati['atti'][4]['documenti'];
		$this->assertSame( array( 'principale', 'allegato', 'sostituito' ), array_column( $oscurato, 'posto' ) );
		$this->assertSame( 'documenti/2041-0005/albo-2041-0005-dal-04-10-al-19-10-allegato-1.pdf', $oscurato[1]['file'] );
		$this->assertSame( hash( 'sha256', $prova['oscurato']['contenuto'] ), $oscurato[1]['impronta']['valore'] );
		$this->assertSame( 'documenti/2041-0005/versione-sostituita-1.pdf', $oscurato[2]['file'] );
		$this->assertSame( $this->impronta( $prova['oscurato']['uscente'] ), $oscurato[2]['impronta']['valore'], 'La versione sostituita con la sua impronta.' );
		$this->assertSame( $oscurato[1]['file'], $oscurato[2]['sostituito_da'], 'Rinvia al documento che l\'ha sostituita.' );
		$this->assertNull( $oscurato[1]['sostituito_da'] );

		// La storia.
		$azioni = static function ( array $atto ): array {
			return array_column( $atto['registro'], 'azione' );
		};
		$this->assertContains( Passaggi::AZIONE_CONFERMA, $azioni( $dati['atti'][1] ), 'La pubblicazione.' );
		$this->assertContains( Passaggi::AZIONE_DEFISSIONE, $azioni( $dati['atti'][2] ), 'La defissione.' );
		$this->assertContains( Passaggi::AZIONE_ANNULLAMENTO, $azioni( $dati['atti'][3] ), 'L\'annullamento.' );
		$voce = array_values(
			array_filter(
				$dati['atti'][4]['registro'],
				static function ( array $voce ): bool {
					return Passaggi::AZIONE_OSCURAMENTO === $voce['azione'];
				}
			)
		);
		$this->assertCount( 1, $voce, 'La sostituzione.' );
		$this->assertSame( self::MOTIVO_OSCURAMENTO, $voce[0]['motivazione'] );
		$this->assertSame( '2041-10-05T10:00:00+02:00', $voce[0]['istante'] );
		$this->assertSame( get_userdata( $this->pubblicatore )->display_name, $voce[0]['autore'] );
		$this->assertNotContains( Passaggi::AZIONE_OSCURAMENTO, $azioni( $dati['atti'][1] ), 'Ogni atto ha la sua storia, non quella degli altri.' );

		// L'indice.
		$righe = array_map(
			static function ( string $riga ): array {
				return str_getcsv( $riga, ',', '"', '' );
			},
			explode( "\n", trim( $contenuto['atti.csv'] ) )
		);
		$this->assertCount( 7, $righe, 'L\'intestazione e sei righe.' );
		$this->assertSame( array( 'anno', 'numero', 'stato', 'fine_passata', 'oggetto', 'tipo', 'organo', 'data_adozione', 'numero_proprio', 'amministrazione', 'inizio', 'fine', 'documenti' ), $righe[0] );
		$this->assertSame( array( '2041', '5', 'pubblicato', 'si', 'Atto che si oscurera\'', 'Deliberazione', 'Giunta', '2041-09-10', '', '', '2041-10-04T09:30:00+02:00', '2041-10-19', '3' ), $righe[5] );

		// Le impronte di ogni file, nella forma degli strumenti di verifica.
		$elencati = array();

		foreach ( explode( "\n", trim( $contenuto['impronte.sha256'] ) ) as $riga ) {
			$this->assertSame( 1, preg_match( '/\A([0-9a-f]{64})  (.+)\z/', $riga, $parti ), 'Riga ' . $riga );
			$this->assertSame( $parti[1], hash( 'sha256', $contenuto[ $parti[2] ] ), $parti[2] . ': l\'impronta elencata.' );
			$elencati[] = $parti[2];
		}

		$tutti = array_keys( $contenuto );
		sort( $tutti );
		$elencati[] = 'impronte.sha256';
		sort( $elencati );
		$this->assertSame( $tutti, $elencati, 'Ogni file dell\'archivio e\' elencato, e nient\'altro.' );

		// La voce dell'esportazione.
		$voci = $this->voci_esportazione();
		$this->assertCount( 1, $voci );
		$this->assertSame( 2041, $voci[0]['dettagli']['anno'] );
		$this->assertSame( 6, $voci[0]['dettagli']['atti'] );
		$this->assertSame( 'sha256:' . hash_file( 'sha256', $esito['percorso'] ), $voci[0]['dettagli']['impronta'], 'Con l\'impronta dell\'archivio consegnato.' );

		// Si rilegge: la funzione del componente da' gli stessi dati.
		$this->assertSame( $dati, albo_pretorio_leggi_esportazione( $esito['percorso'] ), 'L\'archivio si rilegge con gli stessi dati.' );
	}

	/**
	 * A-309: chi non consulta gli scaduti, un anno senza atti, la schermata con un gettone sbagliato o senza anno; poi la consegna dalla schermata.
	 */
	public function test_a309_chi_esporta_e_dalla_schermata(): void {
		$this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso' );

		$pubblica_soltanto = $this->utente_con( Permessi::CHIAVI_PUBBLICAZIONE );

		foreach ( array(
			'chi redige'           => $this->redattore,
			'chi pubblica e basta' => $pubblica_soltanto,
		) as $chi => $utente ) {
			$this->assertSame( array( 'albo_esportazione_non_permessa' ), $this->codici( $this->esporta( 2041, $utente ) ), $chi . '.' );
		}

		$this->assertSame( array( 'albo_esportazione_vuota' ), $this->codici( $this->esporta( 2039, $this->consultatore() ) ), 'Un anno senza atti.' );
		$this->assertSame( array(), $this->voci_esportazione(), 'Nessuna voce per le esportazioni rifiutate.' );

		$consultatore = $this->consultatore();
		$invia        = function ( array $campi ) use ( $consultatore ) {
			return $this->come(
				$consultatore,
				static function () use ( $campi ) {
					$_POST = $campi;

					try {
						return SchermataEsportazione::elabora();
					} finally {
						$_POST = array();
					}
				}
			);
		};
		$gettone      = $this->come(
			$consultatore,
			static function () {
				return wp_create_nonce( SchermataEsportazione::AZIONE );
			}
		);

		foreach ( array(
			'senza gettone'       => array( array( SchermataEsportazione::CAMPO_ANNO => '2041' ), 'albo_esportazione_gettone' ),
			'gettone di un altro' => array(
				array(
					SchermataEsportazione::CAMPO_ANNO    => '2041',
					SchermataEsportazione::CAMPO_GETTONE => wp_create_nonce( SchermataEsportazione::AZIONE ),
				),
				'albo_esportazione_gettone',
			),
			'senza anno'          => array( array( SchermataEsportazione::CAMPO_GETTONE => $gettone ), 'albo_esportazione_anno' ),
			'anno non numerico'   => array(
				array(
					SchermataEsportazione::CAMPO_ANNO    => '2041 OR 1=1',
					SchermataEsportazione::CAMPO_GETTONE => $gettone,
				),
				'albo_esportazione_anno',
			),
			'anno come elenco'    => array(
				array(
					SchermataEsportazione::CAMPO_ANNO    => array( '2041' ),
					SchermataEsportazione::CAMPO_GETTONE => $gettone,
				),
				'albo_esportazione_anno',
			),
		) as $caso => list( $campi, $codice ) ) {
			$this->assertSame( array( $codice ), $this->codici( $invia( $campi ) ), $caso . '.' );
		}

		$this->assertSame( array(), $this->consegne, 'Nessuna consegna per i rifiuti.' );

		// La schermata: chi consulta sceglie l'anno fra quelli con atti numerati; agli altri si rifiuta.
		$html = $this->come(
			$consultatore,
			static function () {
				ob_start();
				SchermataEsportazione::mostra();

				return (string) ob_get_clean();
			}
		);
		$this->assertStringContainsString( '<option value="2041">2041</option>', $html, 'L\'anno con atti numerati.' );
		$this->assertSame( 1, substr_count( $html, '<option ' ), 'E solo quello.' );
		$this->assertStringContainsString( 'name="' . SchermataEsportazione::CAMPO_GETTONE . '"', $html, 'Con il gettone.' );

		foreach ( array(
			'chi redige'           => $this->redattore,
			'chi pubblica e basta' => $pubblica_soltanto,
		) as $chi => $utente ) {
			try {
				$this->come(
					$utente,
					static function () {
						SchermataEsportazione::mostra();
					}
				);
				$this->fail( $chi . ': la schermata doveva rifiutare.' );
			} catch ( \WPDieException $rifiuto ) {
				$this->assertStringContainsString( 'permesso di esportare', $rifiuto->getMessage(), $chi . '.' );
			}
		}
		$this->assertSame( array(), $this->voci_esportazione() );

		// Controllo positivo: con gettone e anno l'archivio si consegna e poi si cancella.
		$esito = $invia(
			array(
				SchermataEsportazione::CAMPO_ANNO    => '2041',
				SchermataEsportazione::CAMPO_GETTONE => $gettone,
			)
		);

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertCount( 1, $this->consegne, 'Una consegna.' );
		$this->assertSame( 'albo-esportazione-2041.zip', $this->consegne[0]['nome'] );
		$this->assertSame( $esito['impronta'], $this->consegne[0]['impronta'], 'Il file consegnato e\' l\'archivio registrato.' );
		$this->assertFileDoesNotExist( $esito['percorso'], 'Dopo la consegna il file temporaneo non c\'e\' piu\'.' );
		$this->assertCount( 1, $this->voci_esportazione() );
	}

	/**
	 * A-310: un documento alterato sul disco o il registro che non risponde fermano l'esportazione; un archivio alterato non si rilegge.
	 */
	public function test_a310_integrita(): void {
		global $wpdb;

		$atto         = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso', array( "%PDF-1.4\n% allegato\n%%EOF\n" ) );
		$consultatore = $this->consultatore();
		$allegato     = (string) get_attached_file( DocumentiAtto::ulteriori( $atto )[0] );
		$originale    = (string) file_get_contents( $allegato ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- prova: il file sul disco.

		file_put_contents( $allegato, $originale . '% aggiunta' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- prova: un documento alterato sul disco.
		$this->assertSame( array( 'albo_esportazione_documento' ), $this->codici( $this->esporta( 2041, $consultatore ) ), 'Un documento che non corrisponde all\'impronta del deposito.' );
		file_put_contents( $allegato, $originale ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- come sopra.

		$wpdb->suppress_errors( true );
		$this->guasto = static function ( string $istruzione ): bool {
			return false !== strpos( $istruzione, 'conformita_core_registro' ) && 0 === strpos( ltrim( $istruzione ), 'SELECT' );
		};
		$guasto       = $this->esporta( 2041, $consultatore );
		$this->guasto = null;
		$wpdb->suppress_errors( false );

		$this->assertSame( array( 'albo_esportazione_lettura' ), $this->codici( $guasto ), 'Il registro che non risponde.' );

		// Il registro che non accetta la voce dell'esportazione: niente consegna, e niente file lasciato.
		$temporanei = glob( get_temp_dir() . 'albo-esportazione-*' );
		$wpdb->suppress_errors( true );
		$this->guasto = static function ( string $istruzione ): bool {
			return false !== strpos( $istruzione, 'conformita_core_registro' ) && 0 === strpos( ltrim( $istruzione ), 'INSERT' );
		};
		$rifiutata    = $this->esporta( 2041, $consultatore );
		$this->guasto = null;
		$wpdb->suppress_errors( false );

		$this->assertSame( array( 'albo_esportazione_non_registrata' ), $this->codici( $rifiutata ), 'Un\'esportazione che non si registra non si consegna.' );
		$this->assertSame( $temporanei, glob( get_temp_dir() . 'albo-esportazione-*' ), 'E l\'archivio costruito si cancella.' );
		$this->assertSame( array(), $this->voci_esportazione(), 'Nessuna voce per le esportazioni fermate.' );

		// Controllo positivo: senza guasti l'esportazione riesce e si rilegge.
		$esito = $this->esporta( 2041, $consultatore );
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertIsArray( albo_pretorio_leggi_esportazione( $esito['percorso'] ) );
		$this->assertStringStartsWith( get_temp_dir() . 'albo-esportazione-', $esito['percorso'], 'Precondizione: il controllo sui file lasciati guarda il posto giusto.' );

		$dati     = json_decode( $this->apri( $esito['percorso'] )['atti.json'], true );
		$file     = $dati['atti'][0]['documenti'][1]['file'];
		$alterati = array(
			'un documento cambiato' => static function ( \ZipArchive $archivio ) use ( $file ): void {
				$archivio->addFromString( $file, '%PDF-1.4 altro' );
			},
			'un file in piu\''      => static function ( \ZipArchive $archivio ): void {
				$archivio->addFromString( 'documenti/estraneo.pdf', '%PDF-1.4' );
			},
			'un documento tolto'    => static function ( \ZipArchive $archivio ) use ( $file ): void {
				$archivio->deleteName( $file );
			},
			'i dati cambiati'       => static function ( \ZipArchive $archivio ): void {
				$archivio->addFromString( 'atti.json', '{}' );
			},
			'i dati che nominano un documento che non c\'e\'' => static function ( \ZipArchive $archivio ) use ( $dati ): void {
				$dati['atti'][0]['documenti'][1]['file'] = 'documenti/mancante.pdf';
				$nuovi                                   = (string) wp_json_encode( $dati );
				$impronte                                = (string) $archivio->getFromName( 'impronte.sha256' );
				$impronte                                = (string) preg_replace( '/\A[0-9a-f]{64}  atti\.json$/m', hash( 'sha256', $nuovi ) . '  atti.json', $impronte );

				$archivio->addFromString( 'atti.json', $nuovi );
				$archivio->addFromString( 'impronte.sha256', $impronte );
			},
		);

		foreach ( $alterati as $caso => $altera ) {
			$copia = wp_tempnam( 'alterato.zip' );
			copy( $esito['percorso'], $copia );
			$this->archivi[] = $copia;

			$archivio = new \ZipArchive();
			$this->assertTrue( $archivio->open( $copia ) );
			$altera( $archivio );
			$archivio->close();

			$this->assertSame( array( 'albo_esportazione_non_integra' ), $this->codici( albo_pretorio_leggi_esportazione( $copia ) ), $caso . '.' );
		}

		$this->assertSame( array( 'albo_esportazione_non_integra' ), $this->codici( albo_pretorio_leggi_esportazione( $allegato ) ), 'Un file che non e\' un archivio.' );
	}

	/**
	 * A-311: un atto ospitato porta l'amministrazione che l'ha adottato con il nome fissato alla pubblicazione, anche dopo che la voce cambia nome (ALBO-29).
	 */
	public function test_a311_atto_ospitato(): void {
		$this->fissa_orologi( '2041-10-01 09:00:00' );

		// Come in AmministrazioniOspitateTest: l'elenco si salva dentro la transazione delle prove.
		$ammessa = new \ReflectionProperty( AmministrazioniOspitate::class, 'dentro_altra_transazione' );
		$ammessa->setAccessible( true );
		$ammessa->setValue( null, true );

		try {
			$this->esporta_ospitato();
		} finally {
			$ammessa->setValue( null, false );
		}
	}

	/**
	 * Il corpo di A-311, con l'elenco delle amministrazioni salvabile.
	 */
	private function esporta_ospitato(): void {
		$this->assertTrue( AmministrazioniOspitate::salva( array(), array(), 'Unione dei comuni', $this->pubblicatore, Passaggi::ora() ) );

		$voce = array_search( 'Unione dei comuni', AmministrazioniOspitate::voci(), true );
		$this->assertIsInt( $voce, 'Precondizione: la voce c\'e\'.' );

		$atti = array();

		// Con una voce nell'elenco la bozza non e' completa finche' non si sceglie: si scrive senza il controllo di atto_completo().
		foreach ( array( 'Atto proprio', 'Atto ospitato' ) as $oggetto ) {
			$id = wp_insert_post(
				array(
					'post_type'    => TIPO,
					'post_status'  => 'draft',
					'post_title'   => $oggetto,
					'post_content' => 'Testo di prova.',
					'post_author'  => $this->redattore,
				)
			);
			$this->assertIsInt( $id );
			wp_set_object_terms( $id, array( $this->voci['normativo'] ), TASSONOMIA_TIPO_ATTO );
			wp_set_object_terms( $id, array( $this->voci['organo'] ), TASSONOMIA_ORGANO );
			update_post_meta( $id, META_DATA_ADOZIONE, '2041-09-10' );
			$this->deposita_documento( $id );
			$atti[] = $id;
		}

		foreach ( array(
			$atti[0] => (string) AmministrazioniOspitate::QUESTA,
			$atti[1] => (string) $voce,
		) as $atto => $scelta ) {
			$this->come(
				$this->redattore,
				static function () use ( $atto, $scelta ) {
					set_current_screen( 'post' );

					$_POST = array(
						'action'                          => 'editpost',
						'post_ID'                         => (string) $atto,
						'post_type'                       => TIPO,
						'post_title'                      => get_the_title( $atto ),
						'original_post_status'            => 'draft',
						'post_status'                     => 'pending',
						'_wpnonce'                        => wp_create_nonce( 'update-post_' . $atto ),
						SchedaAtto::CAMPO_GETTONE         => wp_create_nonce( SchedaAtto::azione( $atto ) ),
						SchedaAtto::CAMPO_AMMINISTRAZIONE => $scelta,
					);

					try {
						edit_post();
					} catch ( \WPDieException $fermata ) {
						unset( $fermata );
					} finally {
						$_POST = array();
						clean_post_cache( $atto );
					}
				}
			);

			$this->assertSame( 'pending', get_post_status( $atto ), 'Precondizione: in verifica con la scelta.' );
			$this->assertIsArray( $this->pubblica_da_codice( $atto, $this->pubblicatore ) );
		}

		$this->assertTrue( AmministrazioniOspitate::salva( array( $voce => 'Unione rinominata' ), array(), '', $this->pubblicatore, Passaggi::ora() ) );
		$this->assertSame( array( $voce => 'Unione rinominata' ), AmministrazioniOspitate::voci(), 'Precondizione: la voce ha cambiato nome.' );

		$esito = $this->esporta( 2041, $this->consultatore() );
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		$dati = json_decode( $this->apri( $esito['percorso'] )['atti.json'], true );

		$this->assertSame( array( 'Atto proprio', 'Atto ospitato' ), array_column( $dati['atti'], 'oggetto' ) );
		$this->assertNull( $dati['atti'][0]['amministrazione'], 'L\'atto di questa amministrazione non ne porta un\'altra.' );
		$this->assertSame( 'Unione dei comuni', $dati['atti'][1]['amministrazione'], 'L\'ospitato porta il nome fissato alla pubblicazione, non quello di adesso.' );
	}

	/**
	 * A-312: un numero perso si salta, un atto che non si legge o in uno stato inatteso ferma l'esportazione; l'archivio rimasto si cancella alla chiusura della richiesta.
	 */
	public function test_a312_atti_numerati_e_archivio_rimasto(): void {
		global $wpdb;

		$atto         = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso' );
		$consultatore = $this->consultatore();
		$istante      = new \DateTimeImmutable( '2041-10-02 10:00:00', wp_timezone() );
		$persi        = array();

		// Tre numeri presi e poi persi: la bozza rimasta, la pubblicazione fallita in verifica, la bozza cancellata.
		foreach ( array( 'draft', 'pending', 'cancellata' ) as $caso ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => TIPO,
					'post_status' => 'draft',
					'post_title'  => 'Numero perso: ' . $caso,
				)
			);
			$this->assertIsArray( Repertorio::assegna( $id, $istante ) );
			$persi[ $caso ] = $id;
		}

		$this->scavalca_barriera(
			static function () use ( $wpdb, $persi ) {
				return $wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $persi['pending'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato scritto senza i passaggi dell'albo.
			}
		);
		$this->scavalca_barriera(
			static function () use ( $wpdb, $persi ) {
				return $wpdb->delete( $wpdb->posts, array( 'ID' => $persi['cancellata'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato scritto senza i passaggi dell'albo.
			}
		);
		clean_post_cache( $persi['pending'] );
		clean_post_cache( $persi['cancellata'] );

		$esito = $this->esporta( 2041, $consultatore );
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 1, $esito['atti'], 'I numeri persi non portano niente nell\'archivio.' );
		$this->assertSame( array( 'Atto in corso' ), array_column( json_decode( $this->apri( $esito['percorso'] )['atti.json'], true )['atti'], 'oggetto' ) );

		// L'archivio che la richiesta non ha consegnato si cancella alla sua chiusura.
		$this->assertFileExists( $esito['percorso'] );
		Esportazione::cancella_esportazioni_rimaste();
		$this->assertFileDoesNotExist( $esito['percorso'], 'La chiusura della richiesta cancella l\'archivio rimasto.' );

		// Un contenuto numerato in uno stato che non e' dell'albo.
		$this->scavalca_barriera(
			static function () use ( $wpdb, $persi ) {
				return $wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $persi['draft'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato scritto senza i passaggi dell'albo.
			}
		);
		clean_post_cache( $persi['draft'] );
		$this->assertSame( array( 'albo_esportazione_stato' ), $this->codici( $this->esporta( 2041, $consultatore ) ), 'Uno stato inatteso ferma l\'esportazione.' );
		$this->scavalca_barriera(
			static function () use ( $wpdb, $persi ) {
				return $wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $persi['draft'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato scritto senza i passaggi dell'albo.
			}
		);
		clean_post_cache( $persi['draft'] );

		$wpdb->suppress_errors( true );

		// La lettura degli atti numerati che non risponde.
		$tabella      = Repertorio::tabella_assegnazioni();
		$this->guasto = static function ( string $istruzione ) use ( $tabella ): bool {
			return false !== strpos( $istruzione, $tabella ) && false !== strpos( $istruzione, 'LEFT JOIN' );
		};
		$guasto       = $this->esporta( 2041, $consultatore );
		$this->guasto = null;
		$this->assertSame( array( 'albo_esportazione_lettura' ), $this->codici( $guasto ), 'Gli atti numerati che non si leggono.' );

		// Un atto che c'e' ma non si legge non e' un numero perso.
		$posts        = $wpdb->posts;
		$this->guasto = static function ( string $istruzione ) use ( $posts, $atto ): bool {
			return 1 === preg_match( '/\ASELECT \* FROM ' . preg_quote( $posts, '/' ) . ' WHERE ID = ' . $atto . ' LIMIT 1\z/', trim( $istruzione ) );
		};
		clean_post_cache( $atto );
		$guasto       = $this->esporta( 2041, $consultatore );
		$this->guasto = null;
		$wpdb->suppress_errors( false );

		$this->assertSame( array( 'albo_esportazione_lettura' ), $this->codici( $guasto ), 'Un atto che non si legge ferma l\'esportazione.' );
		$this->assertCount( 1, $this->voci_esportazione(), 'Nessuna voce per le esportazioni fermate.' );
	}

	/**
	 * A-313: la lettura dei figli di un atto che non risponde ferma l'esportazione, invece di consegnarla senza le versioni sostituite.
	 */
	public function test_a313_figli_che_non_si_leggono(): void {
		global $wpdb;

		$atto         = $this->pubblicato_il( '2041-10-04 09:30:00', 'Atto che si oscurera\'', array( "%PDF-1.4\n% allegato con l'indirizzo\n%%EOF\n" ) );
		$consultatore = $this->consultatore();
		$uscente      = DocumentiAtto::ulteriori( $atto )[0];
		$file         = $this->file_temporaneo( 'oscurato.pdf', "%PDF-1.4\n% allegato oscurato\n%%EOF\n" );

		$this->fissa_orologi( '2041-10-05 10:00:00' );
		$esito = $this->come(
			$this->pubblicatore,
			static function () use ( $atto, $uscente, $file ) {
				return albo_pretorio_oscura(
					$atto,
					$uscente,
					$file,
					'percorso_locale',
					array(
						'conferma' => true,
						'causa'    => Passaggi::CAUSA_DATI_ECCEDENTI,
						'motivo'   => self::MOTIVO_OSCURAMENTO,
					)
				);
			}
		);
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		$temporanei = glob( get_temp_dir() . 'albo-esportazione-*' );
		$wpdb->suppress_errors( true );
		$this->guasto = static function ( string $istruzione ): bool {
			return false !== strpos( $istruzione, 'post_parent' ) && false !== strpos( $istruzione, META_SOSTITUITO_DA );
		};
		$guasto       = $this->esporta( 2041, $consultatore );
		$this->guasto = null;
		$wpdb->suppress_errors( false );

		$this->assertSame( array( 'albo_esportazione_lettura' ), $this->codici( $guasto ), 'I figli che non si leggono non sono "nessuna versione sostituita".' );
		$this->assertSame( array(), $this->voci_esportazione(), 'Nessuna voce per l\'esportazione fermata.' );
		$this->assertSame( $temporanei, glob( get_temp_dir() . 'albo-esportazione-*' ), 'E nessun file lasciato.' );

		// Controllo positivo: senza guasto la versione sostituita c'e', con il suo sostituto.
		$riuscita = $this->esporta( 2041, $consultatore );
		$this->assertIsArray( $riuscita, is_wp_error( $riuscita ) ? $riuscita->get_error_message() : '' );

		$documenti  = json_decode( $this->apri( $riuscita['percorso'] )['atti.json'], true )['atti'][0]['documenti'];
		$sostituiti = array_values(
			array_filter(
				$documenti,
				static function ( array $documento ): bool {
					return 'sostituito' === $documento['posto'];
				}
			)
		);

		$this->assertCount( 1, $sostituiti );
		$this->assertSame( $documenti[1]['file'], $sostituiti[0]['sostituito_da'], 'La versione sostituita rimanda al documento che l\'ha sostituita.' );
	}

	/**
	 * A-314: nell'indice CSV un testo che un foglio di calcolo prenderebbe per una formula esce con l'apostrofo davanti; i dati JSON e i numeri restano come sono.
	 */
	public function test_a314_formule_nell_indice(): void {
		$oggetti = array( '=1+1', '@SOMMA(A1)', "\tTabulato", '-meno', '+piu\'', 'Oggetto normale' );

		foreach ( $oggetti as $indice => $oggetto ) {
			$id = $this->pubblicato_il( sprintf( '2041-10-%02d 09:30:00', $indice + 1 ), $oggetto );

			// WordPress toglie gli spazi in testa all'oggetto: la tabulazione si scrive di lato, come farebbe un'altra estensione.
			if ( "\t" === $oggetto[0] ) {
				$this->scavalca_barriera(
					static function () use ( $id, $oggetto ) {
						global $wpdb;

						return $wpdb->update( $wpdb->posts, array( 'post_title' => $oggetto ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: l'oggetto scritto senza i filtri di WordPress.
					}
				);
				clean_post_cache( $id );
			}
		}

		$esito = $this->esporta( 2041, $this->consultatore() );
		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		$contenuto = $this->apri( $esito['percorso'] );
		$this->assertSame( $oggetti, array_column( json_decode( $contenuto['atti.json'], true )['atti'], 'oggetto' ), 'I dati JSON restano identici.' );

		$righe = array_map(
			static function ( string $riga ): array {
				return str_getcsv( $riga, ',', '"', '' );
			},
			array_slice( explode( "\n", trim( $contenuto['atti.csv'] ) ), 1 )
		);

		$this->assertSame( array( "'=1+1", "'@SOMMA(A1)", "'\tTabulato", "'-meno", "'+piu'", 'Oggetto normale' ), array_column( $righe, 4 ), 'Le formule escono come testo, l\'oggetto normale invariato.' );
		$this->assertSame( array( '2041', '2041', '2041', '2041', '2041', '2041' ), array_column( $righe, 0 ), 'L\'anno non si tocca.' );
		$this->assertSame( array( '1', '2', '3', '4', '5', '6' ), array_column( $righe, 1 ), 'Il numero non si tocca.' );
	}
}
