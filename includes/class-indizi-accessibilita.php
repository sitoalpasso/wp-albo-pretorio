<?php
/**
 * Indizi di accessibilita' dei documenti PDF (ALBO-13).
 *
 * Cerca nel file di un documento i segni che un PDF accessibile porta: la
 * radice della struttura per le tecnologie assistive, i caratteri del testo,
 * il titolo, la lingua. Quello che manca diventa un indizio, mostrato a chi
 * carica il documento. Sono indizi e non una verifica: la pubblicazione non
 * ne dipende, e la verifica vera si fa con uno strumento apposito.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Lettura del PDF e indizi.
 */
final class IndiziAccessibilita {

	/**
	 * Il documento non ha la radice della struttura: non e' un PDF taggato.
	 *
	 * @var string
	 */
	const NON_TAGGATO = 'non_taggato';

	/**
	 * Il documento ha immagini e nessun carattere: sembra una scansione.
	 *
	 * @var string
	 */
	const SCANSIONE = 'scansione';

	/**
	 * Il documento non ha un titolo nelle proprieta' ne' nei metadati.
	 *
	 * @var string
	 */
	const SENZA_TITOLO = 'senza_titolo';

	/**
	 * Il documento non dichiara la lingua.
	 *
	 * @var string
	 */
	const SENZA_LINGUA = 'senza_lingua';

	/**
	 * Quanti byte si leggono al piu', del file e dei blocchi compressi messi insieme.
	 *
	 * Non dipende dall'amministrazione: e' il limite di quello che il
	 * controllo fa in una richiesta. Oltre, il documento e' "non controllato".
	 *
	 * @var int
	 */
	const TETTO = 20971520;

	/**
	 * Quanti blocchi del file si esaminano al piu', per decidere se aprirli.
	 *
	 * Come il tetto, e' un limite del lavoro di una richiesta, non una scelta
	 * dell'amministrazione.
	 *
	 * @var int
	 */
	const MASSIMO_BLOCCHI = 10000;

	/**
	 * L'esito del controllo su un documento.
	 *
	 * @param int $documento Documento, cioe' l'allegato.
	 * @return array{controllato: bool, motivo: string|null, indizi: array<int, string>}
	 */
	public static function esamina( int $documento ): array {
		$allegato = get_post( $documento );

		if ( ! $allegato instanceof \WP_Post || 'attachment' !== $allegato->post_type ) {
			return self::non_controllato( 'illeggibile' );
		}

		$percorso = get_attached_file( $documento );

		if ( ! is_string( $percorso ) || '' === $percorso || ! is_file( $percorso ) || ! is_readable( $percorso ) ) {
			return self::non_controllato( 'illeggibile' );
		}

		$dimensione = filesize( $percorso );

		if ( false === $dimensione ) {
			return self::non_controllato( 'illeggibile' );
		}

		if ( $dimensione > self::TETTO ) {
			return self::non_controllato( 'troppo_grande' );
		}

		$contenuto = file_get_contents( $percorso, false, null, 0, self::TETTO ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del deposito.

		if ( false === $contenuto ) {
			return self::non_controllato( 'illeggibile' );
		}

		return self::esamina_contenuto( $contenuto );
	}

	/**
	 * L'esito del controllo su un contenuto gia' letto.
	 *
	 * @param string $contenuto Contenuto del file.
	 * @return array{controllato: bool, motivo: string|null, indizi: array<int, string>}
	 */
	public static function esamina_contenuto( string $contenuto ): array {
		// L'intestazione puo' stare dopo qualche byte, ma nel primo kilobyte.
		$intestazione = strpos( substr( $contenuto, 0, 1024 ), '%PDF-' );

		if ( false === $intestazione ) {
			return self::non_controllato( 'non_pdf' );
		}

		// Un PDF cifrato ha cifrati anche i blocchi compressi: i segni non si leggono per intero.
		if ( 1 === preg_match( '/\/Encrypt\b/', $contenuto ) ) {
			return self::non_controllato( 'cifrato' );
		}

		// Il file e i blocchi aperti si guardano separati: unirli raddoppierebbe la memoria.
		$aperti = self::blocchi( $contenuto );
		$indizi = array();

		if ( ! self::trova( '/\/StructTreeRoot\b/', $contenuto, $aperti ) ) {
			$indizi[] = self::NON_TAGGATO;
		}

		if ( ! self::trova( '/\/Font\b/', $contenuto, $aperti ) && self::trova( '/\/Subtype\s*\/Image\b/', $contenuto, $aperti ) ) {
			$indizi[] = self::SCANSIONE;
		}

		if ( ! self::ha_titolo( $contenuto ) && ! self::ha_titolo( $aperti ) ) {
			$indizi[] = self::SENZA_TITOLO;
		}

		if ( ! self::trova( '/\/Lang\b/', $contenuto, $aperti ) ) {
			$indizi[] = self::SENZA_LINGUA;
		}

		return array(
			'controllato' => true,
			'motivo'      => null,
			'indizi'      => $indizi,
		);
	}

	/**
	 * Se un segno c'e' nel file o nei blocchi aperti.
	 *
	 * @param string $segno     Espressione del segno.
	 * @param string $contenuto Contenuto del file.
	 * @param string $aperti    Blocchi aperti.
	 * @return bool
	 */
	private static function trova( string $segno, string $contenuto, string $aperti ): bool {
		return 1 === preg_match( $segno, $contenuto ) || 1 === preg_match( $segno, $aperti );
	}

	/**
	 * Il contenuto aperto dei blocchi compressi che portano oggetti o metadati.
	 *
	 * Nei PDF recenti catalogo, caratteri e informazioni stanno spesso in un
	 * blocco di oggetti compresso, e i metadati in un blocco a se'. Si aprono
	 * solo quelli, compressi nel modo comune. Il lavoro ha due limiti, che
	 * valgono per il file intero: i blocchi si cercano uno alla volta, al piu'
	 * MASSIMO_BLOCCHI; e i byte aperti, anche quelli di un blocco poi scartato,
	 * si contano tutti contro il tetto. Esaurito un limite, ci si ferma e
	 * conta quello che si e' trovato.
	 *
	 * @param string $contenuto Contenuto del file.
	 * @return string
	 */
	private static function blocchi( string $contenuto ): string {
		$aperti    = '';
		$residuo   = self::TETTO;
		$posizione = 0;
		$esaminati = 0;

		while ( $residuo > 0 && $esaminati < self::MASSIMO_BLOCCHI ) {
			if ( 1 !== preg_match( '/>>\s*stream(?:\r\n|\n|\r)/', $contenuto, $trovato, PREG_OFFSET_CAPTURE, $posizione ) ) {
				break;
			}

			++$esaminati;

			$fine_dizionario = (int) $trovato[0][1];
			$inizio          = $fine_dizionario + strlen( (string) $trovato[0][0] );
			$posizione       = $inizio;
			$prima           = substr( $contenuto, max( 0, $fine_dizionario - 1024 ), min( 1024, $fine_dizionario ) );
			$oggetto         = strrpos( $prima, ' obj' );
			$dizionario      = false === $oggetto ? $prima : substr( $prima, $oggetto );

			if ( 1 !== preg_match( '/\/Type\s*\/(?:ObjStm|Metadata)\b/', $dizionario ) || 1 !== preg_match( '/\/FlateDecode\b/', $dizionario ) ) {
				continue;
			}

			$fine = strpos( $contenuto, 'endstream', $inizio );

			if ( false === $fine ) {
				break;
			}

			$aperto = self::apri( $contenuto, $inizio, $fine, $residuo );

			if ( null !== $aperto ) {
				$aperti .= $aperto . "\n";
			}
		}

		return $aperti;
	}

	/**
	 * Un blocco compresso aperto, o nullo se non si apre o se supera il tetto.
	 *
	 * Si apre a pezzi piccoli, presi dal file senza copiarlo, controllando la
	 * lunghezza a ogni pezzo: il limite di lunghezza delle funzioni di
	 * decompressione in un colpo solo non e' affidabile, e un blocco costruito
	 * per gonfiarsi riempirebbe la memoria. Ogni byte aperto si toglie dal
	 * residuo, anche quando il blocco poi si scarta. Ci si ferma alla fine dei
	 * dati compressi: quello che segue, per esempio l'a capo prima di
	 * endstream, non fa parte del blocco.
	 *
	 * @param string $contenuto Contenuto del file.
	 * @param int    $inizio    Dove cominciano i dati compressi.
	 * @param int    $fine      Dove finiscono, escluso.
	 * @param int    $residuo   Byte che si possono ancora aprire; diminuisce.
	 * @return string|null
	 */
	private static function apri( string $contenuto, int $inizio, int $fine, int &$residuo ): ?string {
		$flusso = inflate_init( ZLIB_ENCODING_DEFLATE );

		if ( false === $flusso ) {
			return null;
		}

		$aperto = '';

		for ( $pezzo = $inizio; $pezzo < $fine; $pezzo += 1024 ) {
			$ultimo = $pezzo + 1024 >= $fine;
			$uscita = @inflate_add( $flusso, substr( $contenuto, $pezzo, min( 1024, $fine - $pezzo ) ), $ultimo ? ZLIB_FINISH : ZLIB_SYNC_FLUSH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- un blocco che non si apre e' un caso previsto, non un errore.

			if ( false === $uscita ) {
				return null;
			}

			$residuo -= strlen( $uscita );

			if ( $residuo < 0 ) {
				return null;
			}

			$aperto .= $uscita;

			if ( ZLIB_STREAM_END === inflate_get_status( $flusso ) ) {
				break;
			}
		}

		// Anche un blocco troncato vale per quello che si e' aperto: sono segni veri del file.
		return $aperto;
	}

	/**
	 * Se il documento ha un titolo: nelle proprieta', con un valore, o nei metadati.
	 *
	 * @param string $testo Contenuto del file, o blocchi aperti.
	 * @return bool
	 */
	private static function ha_titolo( string $testo ): bool {
		// Un testo con almeno un carattere che non sia uno spazio, una sequenza esadecimale non vuota, o un altro oggetto.
		if ( 1 === preg_match( '/\/Title\s*(?:\(\s*[^\s)]|<\s*[0-9A-Fa-f]{2}|\d+\s+\d+\s+R\b)/', $testo ) ) {
			return true;
		}

		$inizio = strpos( $testo, '<dc:title>' );

		while ( false !== $inizio ) {
			$fine = strpos( $testo, '</dc:title>', $inizio );

			if ( false === $fine ) {
				return false;
			}

			if ( '' !== trim( wp_strip_all_tags( substr( $testo, $inizio, $fine - $inizio ) ) ) ) {
				return true;
			}

			$inizio = strpos( $testo, '<dc:title>', $fine );
		}

		return false;
	}

	/**
	 * L'esito di un documento non controllato.
	 *
	 * @param string $motivo `illeggibile`, `troppo_grande`, `non_pdf` o `cifrato`.
	 * @return array{controllato: bool, motivo: string|null, indizi: array<int, string>}
	 */
	private static function non_controllato( string $motivo ): array {
		return array(
			'controllato' => false,
			'motivo'      => $motivo,
			'indizi'      => array(),
		);
	}

	/**
	 * Il testo di un indizio, per chi carica il documento.
	 *
	 * @param string $indizio Indizio.
	 * @return string
	 */
	public static function testo_indizio( string $indizio ): string {
		switch ( $indizio ) {
			case self::NON_TAGGATO:
				return __( 'il documento non ha la struttura per le tecnologie assistive (non e\' un PDF "taggato"): conviene rifarlo dal file originale, salvando in PDF con l\'opzione dei tag attiva;', 'albo-pretorio-pa' );
			case self::SCANSIONE:
				return __( 'il documento sembra una scansione: contiene immagini e nessun testo, e un lettore di schermo non lo legge. Conviene rifarlo dal file originale, o passarlo al riconoscimento del testo;', 'albo-pretorio-pa' );
			case self::SENZA_TITOLO:
				return __( 'il documento non ha un titolo nelle sue proprieta\';', 'albo-pretorio-pa' );
			case self::SENZA_LINGUA:
				return __( 'il documento non dichiara la lingua del testo;', 'albo-pretorio-pa' );
		}

		return '';
	}

	/**
	 * Il testo del motivo per cui un documento non e' stato controllato.
	 *
	 * @param string $motivo Motivo.
	 * @return string
	 */
	public static function testo_motivo( string $motivo ): string {
		switch ( $motivo ) {
			case 'cifrato':
				return __( 'il documento e\' cifrato, e la cifratura puo\' anche impedirne la lettura alle tecnologie assistive.', 'albo-pretorio-pa' );
			case 'troppo_grande':
				return sprintf(
					/* translators: %s: dimensione massima, leggibile. */
					__( 'il documento supera %s, il limite del controllo automatico.', 'albo-pretorio-pa' ),
					size_format( self::TETTO )
				);
			case 'non_pdf':
				return __( 'il documento non e\' un PDF.', 'albo-pretorio-pa' );
		}

		return __( 'il file non si e\' potuto leggere.', 'albo-pretorio-pa' );
	}
}
