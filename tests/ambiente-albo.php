<?php
/**
 * Aiuti comuni alle prove che toccano registri condivisi.
 *
 * Tipi di contenuto, elenchi di voci e ruoli vivono in registri che durano
 * quanto il processo, e i ruoli durano anche piu' a lungo, perche' stanno in
 * un'opzione della banca dati che sopravvive alle esecuzioni precedenti della
 * suite. Una prova che parte da quello che ha lasciato la prova prima diventa
 * verde o rossa per un motivo che non c'entra con cio' che verifica.
 *
 * **Perche' l'azzeramento delle prove e' piu' forte di quello del componente.**
 * `Permessi::azzera()` chiede al meccanismo comune i nomi dei permessi derivati,
 * quindi funziona solo quando il tipo e' registrato: in apertura di prova non lo
 * e' ancora. Qui i permessi si riconoscono dalla forma del nome, e il ruolo
 * proprio si toglie sempre, anche senza marcatore, perche' la prova possiede
 * l'ambiente e non deve rispettare il ruolo di nessun altro.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

/**
 * Azzeramento dell'ambiente condiviso e osservazioni ricorrenti.
 */
trait AmbienteAlbo {

	/**
	 * Riporta registri, ruoli e opzione allo stato di un sito pulito.
	 */
	protected function azzera_ambiente_albo(): void {
		\Conformita_Core_Tipi::azzera();
		\Conformita_Core_Sezioni::azzera();
		\AlboPretorioPa\Avvio::azzera();
		\AlboPretorioPa\TipoAtto::azzera();

		foreach ( array( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO, \AlboPretorioPa\TASSONOMIA_ORGANO ) as $elenco ) {
			if ( taxonomy_exists( $elenco ) ) {
				unregister_taxonomy( $elenco );
			}
		}

		if ( post_type_exists( \AlboPretorioPa\TIPO ) ) {
			unregister_post_type( \AlboPretorioPa\TIPO );
		}

		$GLOBALS['wp_roles'] = new \WP_Roles();

		foreach ( wp_roles()->role_objects as $oggetto ) {
			foreach ( array_keys( (array) $oggetto->capabilities ) as $permesso ) {
				if ( false !== strpos( (string) $permesso, \AlboPretorioPa\TIPO ) ) {
					$oggetto->remove_cap( (string) $permesso );
				}
			}
		}

		if ( null !== get_role( \AlboPretorioPa\RUOLO ) ) {
			remove_role( \AlboPretorioPa\RUOLO );
		}

		\AlboPretorioPa\Permessi::azzera();

		delete_option( \AlboPretorioPa\OPZIONE_VERSIONE );
	}

	/**
	 * I permessi derivati dal tipo posseduti da qualche ruolo.
	 *
	 * Si cercano per forma e non per nome: il nome derivato lo conosce il
	 * meccanismo comune, e dove serve questa osservazione il tipo spesso non
	 * esiste, quindi chiederglielo darebbe errore.
	 *
	 * @return array<int, string>
	 */
	protected function permessi_dell_albo_sui_ruoli(): array {
		$trovati = array();

		foreach ( wp_roles()->role_objects as $oggetto ) {
			foreach ( array_keys( (array) $oggetto->capabilities ) as $permesso ) {
				if ( false !== strpos( (string) $permesso, \AlboPretorioPa\TIPO ) ) {
					$trovati[] = (string) $permesso;
				}
			}
		}

		return $trovati;
	}

	/**
	 * Esegue una richiesta pubblica come la eseguirebbe un sito vero.
	 *
	 * **Non basta `go_to()`**, e la differenza cambia l'esito. Quell'aiuto passa
	 * la stringa di interrogazione anche come variabili aggiuntive, cosa che
	 * WordPress in una richiesta vera non fa: `post_type` e' una variabile
	 * riservata, e le variabili riservate vengono rimesse **dopo** il controllo
	 * che scarta i tipi non interrogabili dal pubblico. Il risultato e' che
	 * l'indirizzo risponde nel laboratorio e non risponde sul sito, cioe' la
	 * prova osserverebbe una condizione che non esiste. Qui la richiesta si
	 * rifa' con una istanza pulita e nessuna variabile aggiuntiva, che e' come
	 * WordPress avvia una richiesta vera.
	 *
	 * @param string $indirizzo Indirizzo da richiedere.
	 */
	protected function richiesta_pubblica( string $indirizzo ): void {
		$this->go_to( $indirizzo );

		$GLOBALS['wp_the_query'] = new \WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$GLOBALS['wp']           = new \WP();
		$GLOBALS['wp']->main( '' );
	}

	/**
	 * Testo degli avvisi prodotti in amministrazione.
	 *
	 * @return string
	 */
	protected function avvisi_in_bacheca(): string {
		ob_start();
		do_action( 'admin_notices' );

		return (string) ob_get_clean();
	}
}
