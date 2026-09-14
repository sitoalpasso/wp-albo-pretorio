<?php
/**
 * Avvio del componente e dichiarazione delle politiche della sezione.
 *
 * Righe di collaudo A-01..A-10.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Porta il componente da "caricato" a "operativo", oppure spiega perché non ci arriva.
 *
 * Tre passaggi in ordine fisso: il meccanismo comune deve esserci, la sua
 * interfaccia deve essere compatibile, la sezione deve risultare registrata con
 * le due politiche dichiarate per intero. Il primo che non riesce ferma gli
 * altri, perché ciascuno è la precondizione del successivo.
 */
final class Avvio {

	/**
	 * L'avvio è già stato tentato in questa richiesta.
	 *
	 * Non è un'ottimizzazione. L'idempotenza poggia qui, su uno stato interno
	 * della classe, e non sul confronto fra le politiche già registrate e quelle
	 * attese: l'uguaglianza dei valori direbbe che qualcuno ha registrato quella
	 * sezione con quelle politiche, non che sia stato questo componente. Riga di
	 * collaudo A-08.
	 *
	 * @var bool
	 */
	private static $eseguito = false;

	/**
	 * Esito del primo tentativo, restituito ai successivi.
	 *
	 * @var true|false|WP_Error
	 */
	private static $esito = false;

	/**
	 * La sezione è stata registrata da questo componente.
	 *
	 * @var bool
	 */
	private static $registrata = false;

	/**
	 * Avvisi da mostrare in amministrazione, in ordine di emissione.
	 *
	 * @var array<int, string>
	 */
	private static $avvisi = array();

	/**
	 * Aggancio a `plugins_loaded`.
	 *
	 * Il valore di ritorno si ignora deliberatamente: a componenti caricati non
	 * c'è più nessuno a cui restituirlo, e l'esito è già stato messo in un
	 * avviso leggibile da chi può rimediare.
	 */
	public static function da_plugins_loaded(): void {
		self::esegui();
	}

	/**
	 * Esegue l'avvio, una volta sola per richiesta.
	 *
	 * Convenzione del valore restituito: `true` se l'avvio è arrivato in fondo;
	 * l'oggetto errore del meccanismo comune quando è quello a rifiutare, così il
	 * codice dell'errore non va perso per strada; `false` quando è questo
	 * componente a fermarsi da sé, cioè quando il meccanismo comune non è
	 * caricato o quando la sua guardia di versione lo ha disattivato, casi in cui
	 * nessun oggetto errore esiste.
	 *
	 * @return true|false|WP_Error
	 */
	public static function esegui() {
		if ( self::$eseguito ) {
			return self::$esito;
		}

		self::$eseguito = true;
		self::$esito    = self::tenta();

		return self::$esito;
	}

	/**
	 * La sezione risulta registrata da questo componente.
	 *
	 * Distinta da `conformita_core_sezione_registrata()`, che dice se quella
	 * sezione esiste nel registro comune senza dire chi ce l'ha messa.
	 *
	 * @return bool
	 */
	public static function registrata(): bool {
		return self::$registrata;
	}

	/**
	 * Riporta la classe allo stato che ha al caricamento del file.
	 *
	 * @internal Serve alle prove: girano tutte nello stesso processo, e uno stato
	 *           lasciato dietro renderebbe verde o rossa la prova successiva per
	 *           un motivo che non c'entra con quello che quella prova verifica.
	 */
	public static function azzera(): void {
		self::$eseguito   = false;
		self::$esito      = false;
		self::$registrata = false;
		self::$avvisi     = array();
	}

	/**
	 * Mostra gli avvisi accumulati.
	 *
	 * Riservati a chi può attivare i componenti: sono tutti condizioni a cui si
	 * rimedia installando, aggiornando o disattivando un componente, e chi non
	 * può farlo leggerebbe un guasto che non è in grado di riparare.
	 */
	public static function mostra_avvisi(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( self::$avvisi as $messaggio ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $messaggio )
			);
		}
	}

	/**
	 * I tre passaggi dell'avvio.
	 *
	 * @return true|false|WP_Error
	 */
	private static function tenta() {
		if ( ! function_exists( 'conformita_core_avvia_componente' ) ) {
			self::avvisa(
				sprintf(
					/* translators: 1: nome del componente, 2: versione di interfaccia richiesta. */
					__( '%1$s resta attivo ma inerte: richiede la versione %2$s dell\'interfaccia di Conformita Core, e nessuna versione risulta disponibile perché quel componente non è caricato. Installare e attivare Conformita Core.', 'albo-pretorio-pa' ),
					NOME,
					CORE_API_RICHIESTA
				)
			);

			return false;
		}

		/*
		 * La guardia appartiene al meccanismo comune, ed è l'unico punto in cui
		 * questo componente può essere disattivato: se la versione non è
		 * compatibile è quel componente a disattivarlo e a scrivere l'avviso, con
		 * la versione richiesta e quella trovata. Qui non si aggiunge niente,
		 * perché da questo momento il componente non viene più caricato e un
		 * secondo avviso non arriverebbe comunque a nessuno.
		 */
		if ( ! conformita_core_avvia_componente( NOME, plugin_basename( FILE_PRINCIPALE ), CORE_API_RICHIESTA ) ) {
			return false;
		}

		$esito = conformita_core_registra_sezione(
			SEZIONE,
			array(
				'indicizzazione' => INDICIZZAZIONE,
				'scadenza'       => SCADENZA,
			)
		);

		if ( true === $esito ) {
			self::$registrata = true;

			return true;
		}

		/*
		 * Registrazione rifiutata: il componente resta attivo e inerte. Non si
		 * disattiva da sé, perché un componente disattivato non viene più
		 * caricato e non può più segnalare la condizione a ogni richiesta di
		 * amministrazione. Il motivo del rifiuto arriva dal meccanismo comune e si
		 * riporta com'è, preceduto da chi è rimasto fermo e su quale sezione.
		 */
		self::avvisa(
			sprintf(
				/* translators: 1: nome del componente, 2: identificativo della sezione, 3: motivo riportato dal meccanismo comune. */
				__( '%1$s resta attivo ma inerte: la sezione %2$s non è stata registrata. %3$s', 'albo-pretorio-pa' ),
				NOME,
				SEZIONE,
				$esito->get_error_message()
			)
		);

		return $esito;
	}

	/**
	 * Accoda un avviso e assicura l'aggancio che lo mostra.
	 *
	 * L'aggancio si registra una volta sola e legge la coda al momento di
	 * stampare: così azzerare la coda basta a togliere di mezzo gli avvisi, cosa
	 * che con una chiusura per avviso non sarebbe possibile.
	 *
	 * @param string $messaggio Testo dell'avviso.
	 */
	private static function avvisa( string $messaggio ): void {
		self::$avvisi[] = $messaggio;

		if ( ! has_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) ) ) {
			add_action( 'admin_notices', array( self::class, 'mostra_avvisi' ) );
		}
	}
}
