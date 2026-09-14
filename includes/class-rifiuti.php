<?php
/**
 * Dove finisce il motivo per cui una pubblicazione e' stata rifiutata.
 *
 * **Due depositi e non uno**, perche' sono due cose diverse. Il rifiuto che
 * nasce da una schermata appartiene alla persona che ha premuto il pulsante:
 * sta sull'utente e sull'atto insieme, e si consuma appena lo ha letto. Il
 * rifiuto di una chiamata da codice appartiene a chi ha scritto quel codice:
 * non si memorizza affatto, perche' accumulare una diagnosi che nessuno andra'
 * a leggere e' solo un dato in piu' da ripulire.
 *
 * Non esiste un terzo deposito persistente sull'atto: servirebbe alla
 * pubblicazione programmata, che in questa fase viene respinta invece che
 * conservata.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Deposito e prelievo del motivo di un rifiuto.
 */
final class Rifiuti {

	/**
	 * Quanto resta disponibile il motivo di un rifiuto dalla schermata.
	 *
	 * Cinque minuti: il tempo fra il salvataggio e il ricaricamento della
	 * pagina. Se chi ha tentato non torna, il dato se ne va da solo.
	 *
	 * @var int
	 */
	const DURATA = 300;

	/**
	 * Deposita il motivo dove appartiene, a seconda di chi ha tentato.
	 *
	 * @param int                   $post_id Identificativo dell'atto.
	 * @param array<string, string> $motivi  Motivi del rifiuto, per codice.
	 */
	public static function deposita( int $post_id, array $motivi ): void {
		if ( is_admin() && get_current_user_id() > 0 ) {
			set_transient( self::chiave( get_current_user_id(), $post_id ), $motivi, self::DURATA );

			return;
		}

		/*
		 * L'aggancio con cui il rifiuto viene deciso non puo' restituire un
		 * errore al chiamante: e' un limite di WordPress, non una scelta. La
		 * strada corretta per chi programma e' inserire in bozza e aggiornare
		 * dopo, e questa segnalazione lo dice dove chi programma guarda.
		 */
		_doing_it_wrong( 'wp_insert_post', esc_html( implode( ' ', $motivi ) ), esc_html( VERSIONE ) );
	}

	/**
	 * Preleva e consuma il motivo depositato per l'utente corrente.
	 *
	 * @param int $post_id Identificativo dell'atto.
	 * @return array<string, string> Motivi, vuoto se non ce ne sono.
	 */
	public static function preleva_per_utente( int $post_id ): array {
		$utente = get_current_user_id();

		if ( $utente <= 0 ) {
			return array();
		}

		$chiave = self::chiave( $utente, $post_id );
		$motivi = get_transient( $chiave );

		if ( ! is_array( $motivi ) ) {
			return array();
		}

		delete_transient( $chiave );

		return $motivi;
	}

	/**
	 * Segnala nell'indirizzo di ritorno che c'e' stato un rifiuto.
	 *
	 * E' la rete sotto il deposito: un dato temporaneo puo' essere buttato via
	 * prima del tempo da una memoria condivisa esterna, e senza questo
	 * indicatore la schermata non direbbe niente. Con esso l'avviso degrada
	 * verso un messaggio meno preciso, mai verso il silenzio.
	 *
	 * @param string $indirizzo Indirizzo di ritorno costruito da WordPress.
	 * @param int    $post_id   Identificativo dell'atto.
	 * @return string
	 */
	public static function segna_indirizzo_di_ritorno( string $indirizzo, int $post_id ): string {
		if ( TIPO !== get_post_type( $post_id ) ) {
			return $indirizzo;
		}

		if ( array() === self::motivi_disponibili( $post_id ) ) {
			return $indirizzo;
		}

		return add_query_arg( 'albo-rifiuto', '1', $indirizzo );
	}

	/**
	 * Mostra il motivo nella schermata dell'atto, e lo consuma.
	 */
	public static function mostra(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || 'post' !== $schermata->base || TIPO !== $schermata->post_type ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$motivi = self::preleva_per_utente( (int) $post->ID );

		if ( array() !== $motivi ) {
			foreach ( $motivi as $messaggio ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $messaggio ) );
			}

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- si legge un solo indicatore per mostrare un avviso, non si compie nessuna azione.
		if ( ! isset( $_GET['albo-rifiuto'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'La pubblicazione e\' stata rifiutata e l\'atto e\' rimasto in bozza. Il dettaglio non e\' piu\' disponibile: riprovare per rivederlo.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * I motivi depositati, senza consumarli.
	 *
	 * @param int $post_id Identificativo dell'atto.
	 * @return array<string, string>
	 */
	private static function motivi_disponibili( int $post_id ): array {
		$utente = get_current_user_id();

		if ( $utente <= 0 ) {
			return array();
		}

		$motivi = get_transient( self::chiave( $utente, $post_id ) );

		return is_array( $motivi ) ? $motivi : array();
	}

	/**
	 * Chiave del deposito, che tiene insieme l'utente e l'atto.
	 *
	 * Tutti e due, perche' due persone possono tentare la pubblicazione dello
	 * stesso atto nello stesso momento e nessuna delle due deve leggere il
	 * messaggio dell'altra.
	 *
	 * @param int $utente  Identificativo dell'utente.
	 * @param int $post_id Identificativo dell'atto.
	 * @return string
	 */
	private static function chiave( int $utente, int $post_id ): string {
		return 'albo_pretorio_rifiuto_' . $utente . '_' . $post_id;
	}
}
