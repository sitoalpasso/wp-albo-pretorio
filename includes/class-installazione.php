<?php
/**
 * Attivazione e aggiornamento: un meccanismo solo, agganciato alla versione.
 *
 * **Non c'e' nessun aggancio all'attivazione, ed e' una scelta.** Un
 * componente che assegna i permessi solo quando viene attivato lascia scoperti
 * tutti i siti gia' installati il giorno in cui una versione nuova aggiunge un
 * permesso: e' il difetto che la riga di collaudo A-21 esiste per intercettare.
 * Qui il confronto e' fra la versione memorizzata e quella del codice, quindi
 * la prima attivazione e ogni aggiornamento successivo passano dalla stessa
 * riga, e un'opzione cancellata a mano si ripara da sola alla richiesta dopo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Il lavoro che va fatto una volta per ogni versione installata.
 */
final class Installazione {

	/**
	 * Aggancio a `init`, dopo la registrazione del tipo.
	 *
	 * L'ordine conta: i nomi dei permessi si ricavano dal tipo, quindi il tipo
	 * deve esistere prima che si possa assegnare qualcosa.
	 */
	public static function da_init(): void {
		self::aggiorna_se_serve();
	}

	/**
	 * La versione memorizzata sul sito, stringa vuota se non c'e'.
	 *
	 * @return string
	 */
	public static function versione_installata(): string {
		return (string) get_option( OPZIONE_VERSIONE, '' );
	}

	/**
	 * Esegue il lavoro di installazione se la versione memorizzata e' diversa.
	 *
	 * @return bool Vero se il lavoro e' stato eseguito, falso se non serviva o non e' stato possibile.
	 */
	public static function aggiorna_se_serve(): bool {
		if ( VERSIONE === self::versione_installata() ) {
			return false;
		}

		$esito = Permessi::applica();

		if ( is_wp_error( $esito ) ) {
			return false;
		}

		update_option( OPZIONE_VERSIONE, VERSIONE, false );

		return true;
	}
}
