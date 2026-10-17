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
		\AlboPretorioPa\Durate::azzera();
		\AlboPretorioPa\DatiAtto::azzera();
		\AlboPretorioPa\DocumentiAtto::azzera();

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

			if ( function_exists( 'conformita_core_capacita_registro' ) && $oggetto->has_cap( conformita_core_capacita_registro() ) ) {
				$oggetto->remove_cap( conformita_core_capacita_registro() );
			}
		}

		if ( null !== get_role( \AlboPretorioPa\RUOLO ) ) {
			remove_role( \AlboPretorioPa\RUOLO );
		}

		\AlboPretorioPa\Permessi::azzera();

		delete_option( \AlboPretorioPa\OPZIONE_VERSIONE );
		delete_option( \AlboPretorioPa\Festivi::OPZIONE );
		delete_option( \AlboPretorioPa\DatiProcedimento::OPZIONE );
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
	 * Quello che la bacheca stampa fra gli avvisi.
	 *
	 * **Senza gli avvisi delle impostazioni da fare, salvo richiesta.** Su un
	 * sito di prova l'anno della numerazione e' sempre da aprire e le scelte sui
	 * giorni festivi e sui dati del procedimento da fare, quindi quegli avvisi
	 * compaiono a ogni amministratore e renderebbero rossa ogni prova che
	 * osserva un altro avviso, per una ragione che non c'entra con cio' che
	 * verifica. Hanno le loro righe, A-67, A-164 e A-165, che li chiedono
	 * esplicitamente.
	 *
	 * @param bool $con_impostazioni Includi gli avvisi della numerazione, dei giorni festivi e dei dati del procedimento.
	 * @return string
	 */
	protected function avvisi_in_bacheca( bool $con_impostazioni = false ): string {
		$tolti = array();

		if ( ! $con_impostazioni ) {
			foreach ( array( \AlboPretorioPa\SchermataRepertorio::class, \AlboPretorioPa\SchermataFestivi::class, \AlboPretorioPa\SchermataDatiProcedimento::class ) as $classe ) {
				$avviso   = array( $classe, 'avviso' );
				$priorita = has_action( 'admin_notices', $avviso );

				if ( false !== $priorita ) {
					remove_action( 'admin_notices', $avviso, (int) $priorita );
					$tolti[] = array( $avviso, (int) $priorita );
				}
			}
		}

		ob_start();
		do_action( 'admin_notices' );
		$stampato = (string) ob_get_clean();

		foreach ( $tolti as $tolto ) {
			add_action( 'admin_notices', $tolto[0], $tolto[1] );
		}

		return $stampato;
	}

	/**
	 * Esegue una scrittura scavalcando anche la barriera sotto WordPress.
	 *
	 * Serve alle prove che preparano un atto in una condizione che i passaggi
	 * dell'albo non producono, come farebbe chi scrive nella banca dati con
	 * uno strumento esterno al sito: la barriera guarda le istruzioni, i
	 * metadati e le voci, e qui si stacca per la sola scrittura della prova.
	 *
	 * @param callable $scrittura Scrittura da eseguire.
	 * @return mixed Quello che la scrittura restituisce.
	 */
	protected function scavalca_barriera( callable $scrittura ) {
		$classe   = \AlboPretorioPa\ChiusuraPubblicazione::class;
		$agganci  = array(
			array( 'query', 'da_query', PHP_INT_MAX, 1 ),
			array( 'add_post_metadata', 'da_add_post_metadata', PHP_INT_MAX, 4 ),
			array( 'update_post_metadata', 'da_update_post_metadata', PHP_INT_MAX, 4 ),
			array( 'delete_post_metadata', 'da_delete_post_metadata', PHP_INT_MAX, 5 ),
			array( 'update_post_metadata_by_mid', 'da_update_post_metadata_by_mid', PHP_INT_MAX, 4 ),
			array( 'delete_post_metadata_by_mid', 'da_delete_post_metadata_by_mid', PHP_INT_MAX, 2 ),
			array( 'add_term_relationship', 'da_voce_dell_atto', PHP_INT_MIN, 1 ),
			array( 'delete_term_relationships', 'da_voce_dell_atto', PHP_INT_MIN, 1 ),
			array( 'transition_post_status', 'da_transition_post_status', PHP_INT_MIN, 3 ),
			array( 'added_term_relationship', 'da_voce_aggiunta', PHP_INT_MIN, 2 ),
			array( 'deleted_term_relationships', 'da_voci_tolte', PHP_INT_MIN, 2 ),
			array( 'deleted_post_meta', 'da_metadati_tolti', PHP_INT_MIN, 1 ),
		);
		$staccati = array();

		foreach ( $agganci as $aggancio ) {
			if ( remove_filter( $aggancio[0], array( $classe, $aggancio[1] ), $aggancio[2] ) ) {
				$staccati[] = $aggancio;
			}
		}

		try {
			return $scrittura();
		} finally {
			foreach ( $staccati as $aggancio ) {
				add_filter( $aggancio[0], array( $classe, $aggancio[1] ), $aggancio[2], $aggancio[3] );
			}
		}
	}
}
