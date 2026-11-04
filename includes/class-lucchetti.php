<?php
/**
 * Lucchetti della banca dati, anche dove una connessione ne tiene uno solo.
 *
 * L'albo mette in fila le operazioni che non devono sovrapporsi con i
 * lucchetti con nome della banca dati (`GET_LOCK`): il turno dell'importazione,
 * il turno delle proroghe, la numerazione di un anno, le impostazioni dello
 * storico, le copie dello storico, le amministrazioni ospitate.
 *
 * Il meccanismo sta nel componente comune, dall'interfaccia 1.14.0: dove la
 * banca dati tiene un solo lucchetto per connessione, tutti i lucchetti di
 * tutti i componenti diventano lo stesso, contato una volta per connessione.
 * Deve stare li', e non qui, perche' la connessione la condividono tutti i
 * componenti: un conto tenuto dall'albo per se' non vedrebbe le prese degli
 * altri. Questa classe resta come punto unico dell'albo e passa ogni chiamata
 * al componente comune.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Prende e lascia i lucchetti dell'albo, con il meccanismo del componente comune.
 */
final class Lucchetti {

	/**
	 * Se la connessione tiene un solo lucchetto alla volta.
	 *
	 * @return bool
	 */
	public static function unico(): bool {
		return (bool) \conformita_core_lucchetto_unico();
	}

	/**
	 * Il nome con cui un lucchetto dell'albo arriva alla banca dati.
	 *
	 * @param string $nome Nome del lucchetto.
	 * @return string
	 */
	public static function nome( string $nome ): string {
		return (string) \conformita_core_nome_lucchetto( $nome );
	}

	/**
	 * Prende un lucchetto, attendendo al massimo i secondi indicati.
	 *
	 * @param string $nome   Nome del lucchetto.
	 * @param int    $attesa Secondi di attesa.
	 * @return bool Vero se preso.
	 */
	public static function prendi( string $nome, int $attesa ): bool {
		return (bool) \conformita_core_prendi_lucchetto( $nome, $attesa );
	}

	/**
	 * Lascia un lucchetto preso con `prendi()`.
	 *
	 * @param string $nome Nome del lucchetto.
	 */
	public static function lascia( string $nome ): void {
		\conformita_core_lascia_lucchetto( $nome );
	}

	/**
	 * Chiude una presa senza lasciare il lucchetto alla banca dati: lo lascia
	 * la banca dati stessa, alla fine della connessione.
	 *
	 * @param string $nome Nome del lucchetto.
	 */
	public static function trattieni( string $nome ): void {
		\conformita_core_trattieni_lucchetto( $nome );
	}

	/**
	 * Se il lucchetto unico e' gia' tenuto da un'altra presa di questa
	 * connessione, anche di un altro componente.
	 *
	 * @param int $mie Le prese aperte che sono di chi chiede.
	 * @return bool
	 */
	public static function tenuto_da_altri_qui( int $mie = 0 ): bool {
		return (bool) \conformita_core_lucchetto_tenuto_qui( $mie );
	}

	/**
	 * Chiude una presa senza chiedere niente alla banca dati, per chi ha
	 * lasciato il lucchetto con un'istruzione propria.
	 */
	public static function presa_chiusa(): void {
		\conformita_core_lucchetto_chiuso_senza_rilascio();
	}

	/**
	 * Se la versione della banca dati tiene un solo lucchetto per connessione.
	 *
	 * @param string $versione La risposta di `VERSION()`.
	 * @return bool
	 */
	public static function versione_a_lucchetto_unico( string $versione ): bool {
		return (bool) \conformita_core_versione_a_lucchetto_unico( $versione );
	}
}
