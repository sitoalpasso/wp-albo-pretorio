<?php
/**
 * Indizi di accessibilita' dei documenti PDF: righe A-331..A-335 (ALBO-13).
 *
 * I PDF di prova sono scritti per esteso qui, piccoli, ciascuno con un solo
 * difetto. Gli esiti attesi sono scritti per esteso: nessuno si calcola con
 * la classe provata.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\IndiziAccessibilita;
use AlboPretorioPa\SchedaDocumenti;

/**
 * Gli indizi trovati, quelli che non devono comparire, e il riquadro.
 */
class IndiziAccessibilitaTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Assetto comune.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Un PDF piccolo, con le parti indicate.
	 *
	 * @param array<string, mixed> $parti `struttura`, `lingua`, `carattere`, `immagine` (vero o falso),
	 *                                    `titolo` (il valore scritto dopo /Title, o nullo), `xmp` (testo del titolo XMP, o nullo),
	 *                                    `compresso` (catalogo, carattere e informazioni dentro un blocco compresso),
	 *                                    `cifrato`, `extra` (testo in piu' nel corpo).
	 * @return string
	 */
	private function pdf( array $parti = array() ): string {
		$parti += array(
			'struttura' => true,
			'lingua'    => true,
			'carattere' => true,
			'immagine'  => false,
			'titolo'    => '(Delibera di prova)',
			'xmp'       => null,
			'compresso' => false,
			'cifrato'   => false,
			'extra'     => '',
		);

		$catalogo = '1 0 obj << /Type /Catalog /Pages 2 0 R'
			. ( $parti['struttura'] ? ' /StructTreeRoot 5 0 R /MarkInfo << /Marked true >>' : '' )
			. ( $parti['lingua'] ? ' /Lang (it-IT)' : '' )
			. ( null !== $parti['xmp'] ? ' /Metadata 10 0 R' : '' )
			. " >> endobj\n";

		$risorse = array();

		if ( $parti['carattere'] ) {
			$risorse[] = '/Font << /F1 4 0 R >>';
		}

		if ( $parti['immagine'] ) {
			$risorse[] = '/XObject << /Im1 8 0 R >>';
		}

		$pagina    = '3 0 obj << /Type /Page /Parent 2 0 R /Resources << /ProcSet [/PDF /Text /ImageB] ' . implode( ' ', $risorse ) . " >> /Contents 6 0 R >> endobj\n";
		$carattere = $parti['carattere'] ? "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n" : '';
		$info      = null !== $parti['titolo'] ? '7 0 obj << /Title ' . $parti['titolo'] . " /Producer (Prova) >> endobj\n" : "7 0 obj << /Producer (Prova) >> endobj\n";
		$nascosti  = $catalogo . $carattere . $info;

		$corpo  = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
		$corpo .= $parti['compresso']
			? '9 0 obj << /Type /ObjStm /N 3 /First 12 /Filter /FlateDecode >> stream' . "\n" . gzcompress( $nascosti ) . "\nendstream endobj\n"
			: $nascosti;
		$corpo .= "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n" . $pagina;
		$corpo .= $parti['struttura'] ? "5 0 obj << /Type /StructTreeRoot /K [] >> endobj\n" : '';
		$corpo .= "6 0 obj << /Length 0 >> stream\n\nendstream endobj\n";
		$corpo .= $parti['immagine'] ? "8 0 obj << /Type /XObject /Subtype /Image /Width 1 /Height 1 /BitsPerComponent 8 /ColorSpace /DeviceGray /Length 1 >> stream\n\x00\nendstream endobj\n" : '';
		$corpo .= null !== $parti['xmp'] ? "10 0 obj << /Type /Metadata /Subtype /XML >> stream\n<x:xmpmeta xmlns:x=\"adobe:ns:meta/\"><rdf:RDF><rdf:Description><dc:title><rdf:Alt><rdf:li xml:lang=\"x-default\">" . $parti['xmp'] . "</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta>\nendstream endobj\n" : '';
		$corpo .= $parti['extra'];
		$corpo .= 'trailer << /Root 1 0 R /Info 7 0 R' . ( $parti['cifrato'] ? ' /Encrypt 11 0 R' : '' ) . " >>\n%%EOF\n";

		return $corpo;
	}

	/**
	 * Deposita un PDF come documento principale di un atto in bozza nuovo.
	 *
	 * @param string $contenuto Contenuto del file.
	 * @param string $nome      Nome del file.
	 * @return array{0: int, 1: int} Atto e documento.
	 */
	private function atto_con( string $contenuto, string $nome = 'documento.pdf' ): array {
		$atto  = $this->atto_completo();
		$esito = DocumentiAtto::deposita( $atto, $this->file_temporaneo( $nome, $contenuto ), DocumentiAtto::PRINCIPALE, 'percorso_locale' );

		$this->assertIsInt( $esito, 'Precondizione: il deposito riesce. ' . ( is_wp_error( $esito ) ? $esito->get_error_message() : '' ) );

		return array( $atto, $esito );
	}

	/**
	 * L'esito della classe su un contenuto depositato.
	 *
	 * @param string $contenuto Contenuto.
	 * @return array<string, mixed>
	 */
	private function esito_di( string $contenuto ): array {
		return IndiziAccessibilita::esamina( $this->atto_con( $contenuto )[1] );
	}

	/**
	 * Il riquadro dei documenti stampato per chi redige.
	 *
	 * @param int $atto Atto.
	 * @return string
	 */
	private function riquadro( int $atto ): string {
		clean_post_cache( $atto );

		return (string) $this->come(
			$this->redattore,
			static function () use ( $atto ): string {
				ob_start();
				SchedaDocumenti::mostra( get_post( $atto ) );

				return (string) ob_get_clean();
			}
		);
	}

	/**
	 * A-331: un PDF in regola non ha indizi, e ogni difetto da solo ha il suo indizio (ALBO-13).
	 */
	public function test_a331_un_indizio_per_difetto(): void {
		$casi = array(
			'in regola'                         => array( array(), array() ),
			'senza struttura'                   => array( array( 'struttura' => false ), array( 'non_taggato' ) ),
			'scansione'                         => array(
				array(
					'carattere' => false,
					'immagine'  => true,
				),
				array( 'scansione' ),
			),
			'senza titolo'                      => array( array( 'titolo' => null ), array( 'senza_titolo' ) ),
			'titolo vuoto'                      => array( array( 'titolo' => '()' ), array( 'senza_titolo' ) ),
			'titolo di soli spazi'              => array( array( 'titolo' => '(   )' ), array( 'senza_titolo' ) ),
			'titolo vuoto in esadecimale'       => array( array( 'titolo' => '<>' ), array( 'senza_titolo' ) ),
			'titolo in esadecimale'             => array( array( 'titolo' => '<FEFF00410074>' ), array() ),
			'titolo in un altro oggetto'        => array( array( 'titolo' => '12 0 R' ), array() ),
			'titolo solo nei metadati XMP'      => array(
				array(
					'titolo' => null,
					'xmp'    => 'Delibera di prova',
				),
				array(),
			),
			'titolo XMP vuoto'                  => array(
				array(
					'titolo' => null,
					'xmp'    => ' ',
				),
				array( 'senza_titolo' ),
			),
			'senza lingua'                      => array( array( 'lingua' => false ), array( 'senza_lingua' ) ),
			'testo e immagine'                  => array( array( 'immagine' => true ), array() ),
			'senza testo e senza immagini'      => array( array( 'carattere' => false ), array() ),
			'solo la descrizione dei caratteri' => array(
				array(
					'carattere' => false,
					'immagine'  => true,
					'extra'     => "12 0 obj << /Type /FontDescriptor /FontName /Prova /FontFile2 13 0 R >> endobj\n",
				),
				array( 'scansione' ),
			),
			'tutti i difetti'                   => array(
				array(
					'struttura' => false,
					'lingua'    => false,
					'carattere' => false,
					'immagine'  => true,
					'titolo'    => null,
				),
				array( 'non_taggato', 'scansione', 'senza_titolo', 'senza_lingua' ),
			),
		);

		foreach ( $casi as $caso => list( $parti, $attesi ) ) {
			$esito = $this->esito_di( $this->pdf( $parti ) );

			$this->assertTrue( $esito['controllato'], $caso . ': controllato.' );
			$this->assertNull( $esito['motivo'], $caso . ': senza motivo di mancato controllo.' );
			$this->assertSame( $attesi, $esito['indizi'], $caso . ': gli indizi attesi, nell\'ordine.' );
		}
	}

	/**
	 * A-332: i segni dentro un blocco compresso contano, e il blocco si apre fino a un tetto (ALBO-13).
	 *
	 * Nei PDF recenti catalogo, caratteri e informazioni stanno spesso in un
	 * blocco compresso: un documento in regola non deve risultare senza
	 * struttura per questo.
	 */
	public function test_a332_blocchi_compressi(): void {
		$this->assertSame( array(), $this->esito_di( $this->pdf( array( 'compresso' => true ) ) )['indizi'], 'In regola, con i segni nel blocco compresso: nessun indizio.' );

		$senza = $this->esito_di(
			$this->pdf(
				array(
					'compresso' => true,
					'struttura' => false,
					'lingua'    => false,
				)
			)
		);

		$this->assertSame( array( 'non_taggato', 'senza_lingua' ), $senza['indizi'], 'Nel blocco compresso mancano struttura e lingua: i due indizi.' );

		// Il titolo nei metadati XMP compressi.
		$xmp = $this->esito_di(
			$this->pdf(
				array(
					'titolo' => null,
					'extra'  => "15 0 obj << /Type /Metadata /Subtype /XML /Filter /FlateDecode >> stream\n" . gzcompress( '<x:xmpmeta><rdf:RDF><rdf:Description><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Delibera di prova</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta>' ) . "\nendstream endobj\n",
				)
			)
		);

		$this->assertSame( array(), $xmp['indizi'], 'Titolo nei metadati compressi: nessun indizio.' );

		// Un blocco compresso che non porta oggetti ne' metadati non si apre: il segno dentro non conta.
		$altro = $this->esito_di(
			$this->pdf(
				array(
					'struttura' => false,
					'extra'     => "15 0 obj << /Length 40 /Filter /FlateDecode >> stream\n" . gzcompress( '/StructTreeRoot 5 0 R' ) . "\nendstream endobj\n",
				)
			)
		);

		$this->assertSame( array( 'non_taggato' ), $altro['indizi'], 'Blocco di contenuto: non si apre.' );

		// Un blocco che non si apre: si guarda il resto del file, senza eccezioni.
		$rotto = $this->esito_di(
			$this->pdf(
				array(
					'struttura' => false,
					'extra'     => "14 0 obj << /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode >> stream\nquesto non e' compresso\nendstream endobj\n",
				)
			)
		);

		$this->assertTrue( $rotto['controllato'], 'Blocco rotto: controllato lo stesso.' );
		$this->assertSame( array( 'non_taggato' ), $rotto['indizi'], 'Blocco rotto: gli indizi del resto del file.' );

		// Un blocco che si aprirebbe oltre il tetto: il segno messo dopo il tetto non si trova.
		$gonfio = $this->esito_di(
			$this->pdf(
				array(
					'struttura' => false,
					'extra'     => "14 0 obj << /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode >> stream\n" . gzcompress( str_repeat( ' ', IndiziAccessibilita::TETTO ) . '/StructTreeRoot 5 0 R' ) . "\nendstream endobj\n",
				)
			)
		);

		$this->assertTrue( $gonfio['controllato'], 'Blocco oltre il tetto: controllato.' );
		$this->assertSame( array( 'non_taggato' ), $gonfio['indizi'], 'Blocco oltre il tetto: si ferma al tetto, e il segno dopo non conta.' );

		// Controllo positivo: lo stesso segno prima del tetto si trova.
		$entro = $this->esito_di(
			$this->pdf(
				array(
					'struttura' => false,
					'extra'     => "14 0 obj << /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode >> stream\n" . gzcompress( str_repeat( ' ', 1000 ) . '/StructTreeRoot 5 0 R' ) . "\nendstream endobj\n",
				)
			)
		);

		$this->assertSame( array(), $entro['indizi'], 'Lo stesso segno entro il tetto: nessun indizio.' );
	}

	/**
	 * A-333: file che non si controllano, ciascuno con il suo motivo e senza indizi inventati (ALBO-13).
	 */
	public function test_a333_documenti_non_controllati(): void {
		$cifrato = $this->esito_di( $this->pdf( array( 'cifrato' => true ) ) );

		$this->assertFalse( $cifrato['controllato'], 'Cifrato: non controllato.' );
		$this->assertSame( 'cifrato', $cifrato['motivo'] );
		$this->assertSame( array(), $cifrato['indizi'] );

		// Un file che non e' un PDF il meccanismo comune non lo deposita: lo si esamina come contenuto.
		$testo = IndiziAccessibilita::esamina_contenuto( "Questo non e' un PDF, anche se il nome finisce in .pdf.\n" );

		$this->assertFalse( $testo['controllato'], 'Non PDF: non controllato.' );
		$this->assertSame( 'non_pdf', $testo['motivo'] );
		$this->assertSame( array(), $testo['indizi'] );

		// Oltre il tetto di lettura: il file e' grande davvero, ma vuoto in mezzo.
		list( , $grande ) = $this->atto_con( $this->pdf( array( 'struttura' => false ) ) );
		$percorso         = (string) get_attached_file( $grande );
		$maniglia         = fopen( $percorso, 'r+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- prova: il file si allunga sul posto.

		$this->assertNotFalse( $maniglia );
		fseek( $maniglia, IndiziAccessibilita::TETTO );
		fwrite( $maniglia, "\n%%EOF\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- prova.
		fclose( $maniglia ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- prova.
		clearstatcache();

		$this->assertGreaterThan( IndiziAccessibilita::TETTO, filesize( $percorso ), 'Precondizione: il file supera il tetto.' );

		$oltre = IndiziAccessibilita::esamina( $grande );

		$this->assertFalse( $oltre['controllato'], 'Oltre il tetto: non controllato.' );
		$this->assertSame( 'troppo_grande', $oltre['motivo'] );
		$this->assertSame( array(), $oltre['indizi'] );

		// Il file sparito dal disco.
		list( , $sparito ) = $this->atto_con( $this->pdf( array( 'struttura' => false ) ) );

		unlink( (string) get_attached_file( $sparito ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- prova: il file manca.

		$mancante = IndiziAccessibilita::esamina( $sparito );

		$this->assertFalse( $mancante['controllato'], 'File mancante: non controllato.' );
		$this->assertSame( 'illeggibile', $mancante['motivo'] );

		// Un contenuto che non e' un documento.
		$articolo = self::factory()->post->create();
		$nessuno  = IndiziAccessibilita::esamina( $articolo );

		$this->assertFalse( $nessuno['controllato'], 'Non un documento: non controllato.' );
		$this->assertSame( 'illeggibile', $nessuno['motivo'] );
	}

	/**
	 * A-334: il riquadro mostra gli indizi come indizi, e la pubblicazione resta possibile (ALBO-13).
	 */
	public function test_a334_riquadro_e_pubblicazione(): void {
		list( $atto ) = $this->atto_con(
			$this->pdf(
				array(
					'struttura' => false,
					'lingua'    => false,
					'carattere' => false,
					'immagine'  => true,
					'titolo'    => null,
				)
			),
			'scansione.pdf'
		);

		$in_bozza = $this->riquadro( $atto );

		$this->assertStringContainsString( 'Indizi di accessibilit', $in_bozza, 'In bozza: la voce degli indizi.' );

		foreach ( array(
			'non ha la struttura per le tecnologie assistive',
			'sembra una scansione',
			'non ha un titolo',
			'non dichiara la lingua',
		) as $frase ) {
			$this->assertStringContainsString( $frase, $in_bozza, 'In bozza: ' . $frase . '.' );
		}

		$this->assertStringContainsString( 'non una verifica', $in_bozza, 'Detto come indizio.' );
		$this->assertStringContainsString( 'La pubblicazione resta possibile', $in_bozza, 'E che non blocca.' );

		// Controllo positivo: un documento in regola dice che non ci sono indizi, e non elenca difetti.
		list( $buono ) = $this->atto_con( $this->pdf() );
		$regolare      = $this->riquadro( $buono );

		$this->assertStringContainsString( 'Nessun indizio', $regolare, 'In regola: nessun indizio.' );
		$this->assertStringNotContainsString( 'sembra una scansione', $regolare );
		$this->assertStringNotContainsString( 'non dichiara la lingua', $regolare );

		// Un documento non controllato lo dice, con il motivo.
		list( $chiuso ) = $this->atto_con( $this->pdf( array( 'cifrato' => true ) ) );

		$this->assertStringContainsString( 'cifrato', $this->riquadro( $chiuso ), 'Cifrato: il motivo.' );
		$this->assertStringNotContainsString( 'Nessun indizio', $this->riquadro( $chiuso ), 'Cifrato: non si dice che e\' in regola.' );

		// La pubblicazione non e' bloccata dagli indizi, e da pubblicato si vedono ancora.
		$this->come(
			$this->redattore,
			static function () use ( $atto ) {
				return wp_update_post(
					array(
						'ID'          => $atto,
						'post_status' => 'pending',
					)
				);
			}
		);
		clean_post_cache( $atto );

		$esito = $this->pubblica_da_codice( $atto, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 'publish', get_post_status( $atto ) );
		$this->assertStringContainsString( 'sembra una scansione', $this->riquadro( $atto ), 'Da pubblicato: gli indizi restano visibili.' );
	}

	/**
	 * A-335: il lavoro sui blocchi compressi ha un tetto che vale per tutti i blocchi, e un numero massimo di blocchi esaminati (ALBO-13).
	 *
	 * Il controllo gira a ogni apertura del riquadro: il suo lavoro non deve
	 * dipendere da come e' fatto il file oltre un limite fisso. E un blocco
	 * che finisce esattamente alla fine di un pezzo di lettura vale come gli
	 * altri.
	 */
	public function test_a335_limiti_di_lavoro(): void {
		$piccolo = "16 0 obj << /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode >> stream\n" . gzcompress( '/StructTreeRoot 5 0 R' ) . "\nendstream endobj\n";
		$grande  = "14 0 obj << /Type /ObjStm /N 1 /First 4 /Filter /FlateDecode >> stream\n" . gzcompress( str_repeat( ' ', IndiziAccessibilita::TETTO + 1 ) ) . "\nendstream endobj\n";

		// Controllo positivo: il blocco piccolo da solo porta il segno.
		$this->assertSame(
			array(),
			$this->esito_di(
				$this->pdf(
					array(
						'struttura' => false,
						'extra'     => $piccolo,
					)
				)
			)['indizi'],
			'Il blocco piccolo da solo: il segno si trova.'
		);

		// Il blocco grande esaurisce il tetto anche se si scarta: il blocco dopo non si apre piu'.
		$this->assertSame(
			array( 'non_taggato' ),
			$this->esito_di(
				$this->pdf(
					array(
						'struttura' => false,
						'extra'     => $grande . $piccolo,
					)
				)
			)['indizi'],
			'Dopo un blocco oltre il tetto: il tetto e\' esaurito.'
		);

		// Il numero di blocchi esaminati ha un massimo: il blocco oltre il massimo non si guarda.
		$vuoto = "<< /Length 0 >> stream\n\nendstream\n";

		$this->assertSame(
			array(),
			$this->esito_di(
				$this->pdf(
					array(
						'struttura' => false,
						'extra'     => str_repeat( $vuoto, IndiziAccessibilita::MASSIMO_BLOCCHI - 10 ) . $piccolo,
					)
				)
			)['indizi'],
			'Entro il massimo dei blocchi: il segno si trova.'
		);
		$this->assertSame(
			array( 'non_taggato' ),
			$this->esito_di(
				$this->pdf(
					array(
						'struttura' => false,
						'extra'     => str_repeat( $vuoto, IndiziAccessibilita::MASSIMO_BLOCCHI ) . $piccolo,
					)
				)
			)['indizi'],
			'Oltre il massimo dei blocchi: il blocco non si guarda.'
		);

		// Un blocco lungo esattamente un pezzo di lettura, con l'a capo di due caratteri prima della fine.
		$xmp = '<x:xmpmeta><rdf:RDF><rdf:Description><dc:title><rdf:Alt><rdf:li xml:lang="x-default">Delibera di prova</rdf:li></rdf:Alt></dc:title></rdf:Description></rdf:RDF></x:xmpmeta>';

		// Un riempimento poco comprimibile, allungato finche' i dati compressi fanno esattamente 1024 byte.
		$casuale = implode( '', array_map( 'md5', array_map( 'strval', range( 0, 63 ) ) ) );
		$dati    = '';

		foreach ( range( 0, 2048 ) as $n ) {
			$dati = gzcompress( $xmp . '<!--' . substr( $casuale, 0, $n ) . '-->' );

			if ( 1024 === strlen( $dati ) ) {
				break;
			}
		}

		$this->assertSame( 1024, strlen( $dati ), 'Precondizione: un pezzo di lettura esatto.' );
		$this->assertStringNotContainsString( '<dc:title>', $dati, 'Precondizione: il titolo non si legge senza aprire il blocco.' );
		$this->assertSame(
			array(),
			$this->esito_di(
				$this->pdf(
					array(
						'titolo' => null,
						'extra'  => "15 0 obj << /Type /Metadata /Subtype /XML /Filter /FlateDecode >> stream\r\n" . $dati . "\r\nendstream endobj\n",
					)
				)
			)['indizi'],
			'Il titolo nel blocco lungo un pezzo esatto si trova.'
		);
	}
}
