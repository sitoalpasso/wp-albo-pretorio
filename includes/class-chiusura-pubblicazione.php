<?php
/**
 * Lo sbarramento che tiene chiusa la pubblicazione degli atti.
 *
 * **Non e' una condizione, e' un elenco ordinato di regole.** Oggi ce n'e' una
 * sola, che nega tutto perche' la pubblicazione non e' ancora aperta. La
 * lavorazione che porta i dati dell'atto ne aggiunge una seconda, il controllo
 * campo per campo, e quella che apre la pubblicazione toglie la prima. Cosi' la
 * prova del controllo dei campi non diventa vera per niente il giorno in cui
 * tutto e' chiuso comunque: si puo' esercitare la stessa composizione che poi
 * girera' in esercizio.
 *
 * **Tre cose diverse, e questa classe ne copre una sola.** Gli ingressi
 * supportati passano tutti da `wp_insert_post`, quindi da qui, e vengono
 * fermati prima della scrittura: non esiste nessun istante in cui la riga
 * abbia lo stato pubblicato o programmato. La superficie per programmi non
 * esiste, quindi non e' un ingresso da intercettare. E `wp_publish_post()`,
 * chiamata di persona da codice di terzi, scrive lo stato direttamente nella
 * banca dati e **non passa di qui**: quel caso non si impedisce, e la garanzia
 * e' che il contenuto resti comunque irraggiungibile, perche' il tipo non e'
 * interrogabile dal pubblico.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Rifiuto della pubblicazione sugli ingressi supportati.
 */
final class ChiusuraPubblicazione {

	/**
	 * Motivi del rifiuto deciso nella scrittura in corso.
	 *
	 * Si tengono qui perche' la decisione si prende prima che la riga esista, e
	 * il motivo si deposita dopo, quando l'atto ha un identificativo.
	 *
	 * @var array<string, string>
	 */
	private static $rifiuto = array();

	/**
	 * Gli stati che questa fase non concede.
	 *
	 * Anche quello programmato: conservare una pubblicazione che sappiamo non
	 * potersi concludere sarebbe una promessa non mantenibile.
	 *
	 * @return array<int, string>
	 */
	public static function stati_non_concessi(): array {
		return array( 'publish', 'future' );
	}

	/**
	 * Aggancia lo sbarramento agli ingressi supportati.
	 */
	public static function aggancia(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'da_wp_insert_post_data' ), 10, 2 );
		add_action( 'wp_insert_post', array( self::class, 'da_wp_insert_post' ), 10, 2 );
	}

	/**
	 * Le ragioni per cui lo stato proposto non e' concesso.
	 *
	 * Riceve lo stato proposto e non legge niente da se': e' cio' che la rende
	 * esercitabile da sola, senza passare da un aggancio di WordPress.
	 *
	 * @param array<string, mixed> $stato_proposto Stato dell'atto come sara' dopo questa richiesta.
	 * @return array<string, string> Motivi per codice, vuoto se non ce ne sono.
	 */
	public static function motivi( array $stato_proposto ): array {
		$motivi = array();

		$richiesto = isset( $stato_proposto['post_status'] ) ? (string) $stato_proposto['post_status'] : '';

		if ( in_array( $richiesto, self::stati_non_concessi(), true ) ) {
			$motivi['albo_pubblicazione_non_aperta'] = sprintf(
				/* translators: %s: nome del componente. */
				__( '%s: la pubblicazione degli atti non e\' ancora aperta, quindi l\'atto resta in bozza. Il documento principale di un atto ha bisogno della consegna protetta del meccanismo comune, che non e\' ancora disponibile: finche\' non c\'e\', un file pubblicato resterebbe scaricabile dal suo indirizzo anche dopo la defissione.', 'albo-pretorio-pa' ),
				NOME
			);
		}

		return $motivi;
	}

	/**
	 * Riporta in bozza la scrittura in corso, prima che avvenga.
	 *
	 * WordPress applica questo filtro **prima** di scrivere nella tabella dei
	 * contenuti: e' il motivo per cui non esiste un istante in cui l'atto
	 * risulti pubblicato. Una correzione fatta dopo la scrittura sarebbe una
	 * riparazione tardiva, con quell'istante in mezzo e con gli effetti
	 * collaterali della pubblicazione gia' partiti.
	 *
	 * @param array<string, mixed> $data    Dati che stanno per essere scritti.
	 * @param array<string, mixed> $postarr Dati come sono arrivati alla funzione di inserimento.
	 * @return array<string, mixed>
	 */
	public static function da_wp_insert_post_data( $data, $postarr ) {
		unset( $postarr );

		if ( ! is_array( $data ) || ! isset( $data['post_type'] ) || TIPO !== $data['post_type'] ) {
			return $data;
		}

		self::$rifiuto = array();

		$motivi = self::motivi( array( 'post_status' => isset( $data['post_status'] ) ? $data['post_status'] : '' ) );

		if ( array() === $motivi ) {
			return $data;
		}

		$data['post_status'] = 'draft';
		self::$rifiuto       = $motivi;

		return $data;
	}

	/**
	 * Deposita il motivo, ora che l'atto ha un identificativo.
	 *
	 * @param int      $post_id Identificativo dell'atto.
	 * @param \WP_Post $post    Contenuto appena scritto.
	 */
	public static function da_wp_insert_post( $post_id, $post ): void {
		if ( array() === self::$rifiuto ) {
			return;
		}

		$motivi        = self::$rifiuto;
		self::$rifiuto = array();

		if ( ! $post instanceof \WP_Post || TIPO !== $post->post_type ) {
			return;
		}

		Rifiuti::deposita( (int) $post_id, $motivi );
	}
}
