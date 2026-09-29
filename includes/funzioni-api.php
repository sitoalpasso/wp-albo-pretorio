<?php
/**
 * Le funzioni pubbliche dell'albo, per chi scrive codice.
 *
 * Fanno esattamente quello che fa il riquadro Pubblicazione della schermata
 * dell'atto, con gli stessi controlli: sono l'unico ingresso da codice ai due
 * passaggi che partono dalla verifica. `wp_update_post()` con lo stato
 * pubblicato resta rifiutato.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'albo_pretorio_pubblica' ) ) {
	/**
	 * Pubblica un atto in verifica, come l'utente corrente.
	 *
	 * Chiavi della richiesta: `conferma`, obbligatoria e vera, dichiara che
	 * chi pubblica ha controllato i dati personali che l'atto contiene o lascia
	 * capire (ALBO-11). `data_inizio` e `data_fine` non diventano mai quelle
	 * effettive: una data di inizio nel futuro rifiuta la pubblicazione, una
	 * passata e una data di fine si ignorano. Ogni altra chiave rifiuta la
	 * richiesta.
	 *
	 * @param int                  $atto_id   Atto.
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|WP_Error
	 */
	function albo_pretorio_pubblica( $atto_id, $richiesta = array() ) {
		return \AlboPretorioPa\Passaggi::pubblica( (int) $atto_id, is_array( $richiesta ) ? $richiesta : array( 'richiesta' => $richiesta ) );
	}
}

if ( ! function_exists( 'albo_pretorio_rimanda_in_bozza' ) ) {
	/**
	 * Rimanda in bozza un atto in verifica, come l'utente corrente, con il motivo.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $motivo  Motivo, che finisce nel registro delle modifiche.
	 * @return int|WP_Error Numero della voce di registro, oppure errore.
	 */
	function albo_pretorio_rimanda_in_bozza( $atto_id, $motivo ) {
		return \AlboPretorioPa\Passaggi::rimanda_in_bozza( (int) $atto_id, $motivo );
	}
}
