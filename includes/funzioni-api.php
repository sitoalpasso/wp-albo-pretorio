<?php
/**
 * Le funzioni pubbliche dell'albo, per chi scrive codice.
 *
 * Quelle che scrivono fanno esattamente quello che fa il riquadro
 * Pubblicazione della schermata dell'atto, con gli stessi controlli: sono
 * l'unico ingresso da codice ai due passaggi che partono dalla verifica.
 * `wp_update_post()` con lo stato pubblicato resta rifiutato. L'ultima legge
 * soltanto, per i temi.
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

if ( ! function_exists( 'albo_pretorio_annulla' ) ) {
	/**
	 * Annulla un atto pubblicato o tolto dall'albo, come l'utente corrente, con il motivo.
	 *
	 * L'atto resta nel registro con numero e date, e non cambia piu'. Fino
	 * alla data di fine il pubblico ne vede l'avviso con il motivo, senza
	 * documenti; un atto tolto dall'albo ha la data di fine gia' passata.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $motivo  Motivo, che finisce nel registro delle modifiche e nell'avviso al pubblico.
	 * @return int|WP_Error Numero della voce di registro, oppure errore.
	 */
	function albo_pretorio_annulla( $atto_id, $motivo ) {
		return \AlboPretorioPa\Passaggi::annulla_atto( (int) $atto_id, $motivo );
	}
}

if ( ! function_exists( 'albo_pretorio_defiggi' ) ) {
	/**
	 * Toglie dall'albo un atto pubblicato e non ancora scaduto, come l'utente corrente.
	 *
	 * La causa e' una delle chiavi di `\AlboPretorioPa\Passaggi::cause_defissione()`:
	 * il termine di un atto regolare non si accorcia. L'atto sparisce subito
	 * dalla vista pubblica, perche' la data di fine torna al giorno prima.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $causa   Causa dell'elenco.
	 * @param string $motivo  Motivo, che finisce nel registro delle modifiche con la causa.
	 * @return int|WP_Error Numero della voce di registro, oppure errore.
	 */
	function albo_pretorio_defiggi( $atto_id, $causa, $motivo ) {
		return \AlboPretorioPa\Passaggi::defiggi_atto( (int) $atto_id, $causa, $motivo );
	}
}

if ( ! function_exists( 'albo_pretorio_proroga' ) ) {
	/**
	 * Dispone la proroga per disservizio (ALBO-31), come l'utente corrente.
	 *
	 * Chiavi della richiesta: `giorni`, elenco di date AAAA-MM-GG gia'
	 * trascorse in cui l'albo e' rimasto consultabile per meno di dodici ore;
	 * `attestazione`, testo non vuoto con gli estremi dell'attestazione. Ogni
	 * altra chiave rifiuta la richiesta. Gli atti in pubblicazione in quei
	 * giorni si prorogano tutti, o nessuno.
	 *
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @return array{voce: int, giorni: array<int, string>, atti: array<int, array<string, mixed>>}|WP_Error
	 */
	function albo_pretorio_proroga( $richiesta ) {
		return \AlboPretorioPa\Passaggi::proroga( is_array( $richiesta ) ? $richiesta : array( 'richiesta' => $richiesta ) );
	}
}

if ( ! function_exists( 'albo_pretorio_vista_corrente' ) ) {
	/**
	 * Quello che un tema puo' sapere della pagina in corso, per la sua testata.
	 *
	 * Nullo fuori dalla pagina dell'albo e su una pagina "non trovata". Sulla
	 * pagina dell'albo: `elenco`, l'indirizzo dell'elenco; `atto`, nullo
	 * sull'elenco, oppure `numero` (come 71/2026) e `tipo` dell'atto della
	 * scheda aperta, solo se in pubblicazione adesso. L'oggetto non c'e'. Va
	 * chiamata dai modelli del tema, quando WordPress sa gia' quale pagina e'
	 * chiesta.
	 *
	 * @return array{elenco: string, atto: array{numero: string, tipo: string}|null}|null
	 */
	function albo_pretorio_vista_corrente() {
		return \AlboPretorioPa\PaginaPubblica::vista();
	}
}
