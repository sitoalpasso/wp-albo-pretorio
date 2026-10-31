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

		$testo  = $contenuto . "\n" . self::blocchi( $contenuto );
		$indizi = array();

		if ( 1 !== preg_match( '/\/StructTreeRoot\b/', $testo ) ) {
			$indizi[] = self::NON_TAGGATO;
		}

		if ( 1 !== preg_match( '/\/Font\b/', $testo ) && 1 === preg_match( '/\/Subtype\s*\/Image\b/', $testo ) ) {
			$indizi[] = self::SCANSIONE;
		}

		if ( ! self::ha_titolo( $testo ) ) {
			$indizi[] = self::SENZA_TITOLO;
		}

		if ( 1 !== preg_match( '/\/Lang\b/', $testo ) ) {
			$indizi[] = self::SENZA_LINGUA;
		}

		return array(
			'controllato' => true,
			'motivo'      => null,
			'indizi'      => $indizi,
		);
	}

	/**
	 * Il contenuto aperto dei blocchi compressi che portano oggetti o metadati.
	 *
	 * Nei PDF recenti catalogo, caratteri e informazioni stanno spesso in un
	 * blocco di oggetti compresso, e i metadati in un blocco a se'. Si aprono
	 * solo quelli, compressi nel modo comune, e tutti insieme non oltre il
	 * tetto: un blocco che si aprirebbe oltre il tetto, o che non si apre,
	 * resta chiuso, e conta il resto del file.
	 *
	 * @param string $contenuto Contenuto del file.
	 * @return string
	 */
	private static function blocchi( string $contenuto ): string {
		if ( false === preg_match_all( '/>>\s*stream(?:\r\n|\n|\r)/', $contenuto, $trovati, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		$aperti  = '';
		$residuo = self::TETTO;

		foreach ( $trovati[0] as $trovato ) {
			if ( $residuo <= 0 ) {
				break;
			}

			$fine_dizionario = (int) $trovato[1];
			$prima           = substr( $contenuto, max( 0, $fine_dizionario - 1024 ), min( 1024, $fine_dizionario ) );
			$oggetto         = strrpos( $prima, ' obj' );
			$dizionario      = false === $oggetto ? $prima : substr( $prima, $oggetto );

			if ( 1 !== preg_match( '/\/Type\s*\/(?:ObjStm|Metadata)\b/', $dizionario ) || 1 !== preg_match( '/\/FlateDecode\b/', $dizionario ) ) {
				continue;
			}

			$inizio = $fine_dizionario + strlen( (string) $trovato[0] );
			$fine   = strpos( $contenuto, 'endstream', $inizio );

			if ( false === $fine ) {
				continue;
			}

			// L'a capo prima di endstream resta: la decompressione si ferma alla fine dei dati compressi.
			$dati = substr( $contenuto, $inizio, $fine - $inizio );

			$aperto = self::apri( $dati, $residuo );

			if ( null === $aperto ) {
				continue;
			}

			$aperti  .= $aperto . "\n";
			$residuo -= strlen( $aperto );
		}

		return $aperti;
	}

	/**
	 * Un blocco compresso aperto, o nullo se non si apre o se si aprirebbe oltre il tetto.
	 *
	 * Si apre a pezzi piccoli, controllando la lunghezza a ogni pezzo: il
	 * limite di lunghezza delle funzioni di decompressione in un colpo solo
	 * non e' affidabile, e un blocco costruito per gonfiarsi riempirebbe la
	 * memoria.
	 *
	 * @param string $dati    Dati compressi.
	 * @param int    $residuo Byte che si possono ancora aprire.
	 * @return string|null
	 */
	private static function apri( string $dati, int $residuo ): ?string {
		$flusso = inflate_init( ZLIB_ENCODING_DEFLATE );

		if ( false === $flusso ) {
			return null;
		}

		$aperto = '';
		$pezzi  = str_split( $dati, 1024 );

		foreach ( $pezzi as $indice => $pezzo ) {
			$ultimo = count( $pezzi ) - 1 === $indice;
			$uscita = @inflate_add( $flusso, $pezzo, $ultimo ? ZLIB_FINISH : ZLIB_SYNC_FLUSH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- un blocco che non si apre e' un caso previsto, non un errore.

			if ( false === $uscita ) {
				return null;
			}

			$aperto .= $uscita;

			if ( strlen( $aperto ) > $residuo ) {
				return null;
			}
		}

		// Anche un blocco troncato vale per quello che si e' aperto: sono segni veri del file.
		return $aperto;
	}

	/**
	 * Se il documento ha un titolo: nelle proprieta', con un valore, o nei metadati.
	 *
	 * @param string $testo Contenuto, con i blocchi aperti.
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
