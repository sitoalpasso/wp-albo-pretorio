<?php
/**
 * Le letture della banca dati da cui dipende una decisione.
 *
 * **Una lettura fallita non e' una risposta.** Le funzioni di lettura di
 * WordPress restituiscono nulla, o un elenco vuoto, sia quando la riga non
 * c'e' sia quando la banca dati non ha risposto: per chi decide se una
 * scrittura e' ammessa, scambiare il secondo caso per il primo vuol dire
 * lasciar passare proprio quando non si sa niente. Qui le due cose restano
 * distinte: un elenco, anche vuoto, solo se la lettura e' riuscita e ogni riga
 * ha le colonne attese; nulla in ogni altro caso, e chi chiama rifiuta.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Lettura che distingue la riga assente dalla risposta mancata.
 */
final class Lettura {

	/**
	 * Le righe di un'interrogazione, o nulla se la lettura non e' riuscita.
	 *
	 * @param string             $istruzione Interrogazione gia' preparata.
	 * @param array<int, string> $colonne    Colonne che ogni riga deve avere.
	 * @return array<int, array<string, mixed>>|null
	 */
	public static function righe( string $istruzione, array $colonne ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- l'istruzione arriva gia' preparata da chi chiama; la lettura e' della banca dati com'e', senza la memoria di WordPress.
		$esito = $wpdb->query( $istruzione );

		if ( ! is_int( $esito ) || ! is_array( $wpdb->last_result ) || count( $wpdb->last_result ) !== $esito ) {
			return null;
		}

		$righe = array();

		foreach ( $wpdb->last_result as $riga ) {
			$campi = is_object( $riga ) ? get_object_vars( $riga ) : null;

			if ( null === $campi ) {
				return null;
			}

			foreach ( $colonne as $colonna ) {
				if ( ! array_key_exists( $colonna, $campi ) ) {
					return null;
				}
			}

			$righe[] = $campi;
		}

		return $righe;
	}

	/**
	 * Un conteggio, o nulla se la lettura non e' riuscita.
	 *
	 * @param string $istruzione Interrogazione gia' preparata, con la colonna `n`.
	 * @return int|null
	 */
	public static function conteggio( string $istruzione ): ?int {
		$righe = self::righe( $istruzione, array( 'n' ) );

		if ( null === $righe || 1 !== count( $righe ) || 1 !== preg_match( '/^\d+$/', (string) $righe[0]['n'] ) ) {
			return null;
		}

		return (int) $righe[0]['n'];
	}
}
