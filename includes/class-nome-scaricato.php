<?php
/**
 * Il nome del file scaricato (ALBO-30).
 *
 * Il rischio che il Garante chiama decontestualizzazione (delib. 243/2014,
 * par. 2.d) e' quello di un documento che circola da solo, lontano dall'albo,
 * senza piu' niente che dica che era un atto pubblicato, quando e fino a
 * quando. Il documento non si tocca: scriverci sopra romperebbe la firma
 * digitale e lo renderebbe diverso dall'atto adottato (ALBO-09). Quello che si
 * puo' fare senza toccarlo e' dargli un nome che porta il numero di registro e
 * le date di pubblicazione:
 *
 *     albo-2026-0007-dal-01-10-al-16-10.pdf
 *     albo-2026-0007-dal-01-10-al-16-10-allegato-1.pdf
 *
 * Il nome non contiene ne' l'oggetto dell'atto ne' il nome originale del file,
 * perche' l'uno e l'altro possono contenere dati personali.
 *
 * Il nome lo compone il punto di consegna del meccanismo comune, che chiama
 * questa funzione e ne controlla il risultato: se la funzione non risponde o
 * risponde qualcosa di storto, il file esce con un nome neutro e mai con il
 * nome che aveva sul disco.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Composizione del nome del file scaricato.
 */
final class NomeScaricato {

	/**
	 * Il nome proposto per un documento dell'atto, o nullo.
	 *
	 * Le parti vengono dai dati dell'atto, letti a ogni consegna e mai
	 * conservati: anno e numero dal registro, con almeno quattro cifre e senza
	 * troncare; il primo giorno dalla data di pubblicazione dell'atto, che e'
	 * gia' nell'ora del sito; l'ultimo dalla data di fine, cioe' dal giorno che
	 * la scheda mostra, proroga compresa. Gli allegati portano il numero
	 * d'ordine che la scheda pubblica mostra accanto a "Allegato".
	 *
	 * Un atto senza numero di registro non e' pubblicato, oppure il registro
	 * non ha risposto: il nome lo dice e non ha date, perche' la data di un
	 * atto in preparazione non e' una data di pubblicazione. Un documento che
	 * non e' piu' fra quelli dell'atto non ha nome: il meccanismo comune usa
	 * quello neutro.
	 *
	 * @param int $allegato_id Documento.
	 * @param int $atto_id     Atto.
	 * @return string|null
	 */
	public static function nome( int $allegato_id, int $atto_id ): ?string {
		$ordine = self::ordine( $allegato_id, $atto_id );

		if ( null === $ordine ) {
			return null;
		}

		$nome = self::radice( $atto_id );

		if ( $ordine > 0 ) {
			$nome .= '-allegato-' . $ordine;
		}

		$estensione = strtolower( (string) pathinfo( (string) get_attached_file( $allegato_id ), PATHINFO_EXTENSION ) );

		return '' === $estensione ? $nome : $nome . '.' . $estensione;
	}

	/**
	 * Zero per il documento principale, il numero d'ordine per un allegato, nullo per il resto.
	 *
	 * @param int $allegato_id Documento.
	 * @param int $atto_id     Atto.
	 * @return int|null
	 */
	private static function ordine( int $allegato_id, int $atto_id ): ?int {
		if ( DocumentiAtto::principale( $atto_id ) === $allegato_id ) {
			return 0;
		}

		$posizione = array_search( $allegato_id, DocumentiAtto::ulteriori( $atto_id ), true );

		return false === $posizione ? null : (int) $posizione + 1;
	}

	/**
	 * La parte del nome che vale per tutti i documenti dell'atto.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private static function radice( int $atto_id ): string {
		$numero = Repertorio::di( $atto_id );

		if ( null === $numero ) {
			return 'albo-senza-numero';
		}

		$radice = sprintf( 'albo-%d-%04d', $numero['anno'], $numero['numero'] );
		$dal    = get_post_time( 'd-m', false, $atto_id );

		if ( is_string( $dal ) && 1 === preg_match( '/\A[0-9]{2}-[0-9]{2}\z/', $dal ) ) {
			$radice .= '-dal-' . $dal;
		}

		$al = self::ultimo_giorno( $atto_id );

		return null === $al ? $radice : $radice . '-al-' . $al;
	}

	/**
	 * L'ultimo giorno di pubblicazione come giorno e mese, o nullo se la data non si legge.
	 *
	 * La data di fine e' un giorno, senza ora: si legge cosi' com'e' e non si
	 * converte di fuso. Vale solo se rileggendola si ottiene lo stesso testo,
	 * cosi' che un 31 febbraio scritto di lato non diventi il 3 marzo.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null
	 */
	private static function ultimo_giorno( int $atto_id ): ?string {
		$fine = conformita_core_fine_pubblicazione( $atto_id );

		if ( ! is_string( $fine ) ) {
			return null;
		}

		$letta = \DateTimeImmutable::createFromFormat( '!Y-m-d', $fine, wp_timezone() );

		return false === $letta || $letta->format( 'Y-m-d' ) !== $fine ? null : $letta->format( 'd-m' );
	}
}
