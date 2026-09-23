<?php
/**
 * I dati dell'atto che fornisce chi redige, e l'elenco di quelli che mancano.
 *
 * Quattro dati: tipo di atto e organo, che stanno nei due elenchi di voci, e
 * data di adozione e numero proprio, che sono metadati dell'atto. La lettura
 * **valida il dato letto**, per la stessa ragione delle durate: chi scrive
 * nella banca dati passando di lato non deve poter consegnare al componente un
 * dato che la scheda avrebbe rifiutato. Un dato malformato vale come assente.
 *
 * L'elenco dei dati mancanti oggi non lo chiama nessuno: lo chiamera' il
 * passaggio da bozza a in verifica. Il documento principale non ne fa parte
 * perche' non esiste ancora.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Dichiarazione, lettura e completezza dei dati dell'atto.
 */
final class DatiAtto {

	/**
	 * Dichiara i due metadati a WordPress.
	 *
	 * Il nome comincia con il trattino basso, quindi WordPress li tratta come
	 * protetti: una richiesta di salvataggio che li porta come campi
	 * personalizzati viene ignorata, e l'unico ingresso dalla schermata resta
	 * la scheda, che li valida. L'esposizione per programmi e' spenta in modo
	 * esplicito.
	 *
	 * @return true|\WP_Error
	 */
	public static function registra() {
		if ( ! TipoAtto::registrazione_completa() ) {
			return new \WP_Error(
				'albo_dati_senza_tipo',
				__( 'Dati dell\'atto non dichiarati: il tipo atto non risulta registrato da questo componente.', 'albo-pretorio-pa' )
			);
		}

		foreach ( array( META_DATA_ADOZIONE, META_NUMERO_PROPRIO ) as $chiave ) {
			$registrato = register_post_meta(
				TIPO,
				$chiave,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
				)
			);

			if ( ! $registrato ) {
				return new \WP_Error(
					'albo_dati_non_dichiarati',
					__( 'Dati dell\'atto non dichiarati: WordPress ha rifiutato la dichiarazione di un dato.', 'albo-pretorio-pa' )
				);
			}
		}

		return true;
	}

	/**
	 * Aggancio a `init`, dopo la registrazione del tipo.
	 */
	public static function da_init(): void {
		self::registra();
	}

	/**
	 * Il tipo di atto dell'atto, se ce n'e' uno e uno solo.
	 *
	 * @param int $atto_id Atto.
	 * @return \WP_Term|null
	 */
	public static function tipo( int $atto_id ): ?\WP_Term {
		return self::voce_unica( $atto_id, TASSONOMIA_TIPO_ATTO );
	}

	/**
	 * L'organo dell'atto, se ce n'e' uno e uno solo.
	 *
	 * @param int $atto_id Atto.
	 * @return \WP_Term|null
	 */
	public static function organo( int $atto_id ): ?\WP_Term {
		return self::voce_unica( $atto_id, TASSONOMIA_ORGANO );
	}

	/**
	 * La data di adozione, se c'e' una sola riga ed e' una data vera.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null Data nel formato AAAA-MM-GG.
	 */
	public static function data_adozione( int $atto_id ): ?string {
		$righe = get_post_meta( $atto_id, META_DATA_ADOZIONE, false );

		if ( 1 !== count( $righe ) || ! is_string( $righe[0] ) ) {
			return null;
		}

		return self::data_da_ingresso( $righe[0] );
	}

	/**
	 * Il numero proprio dell'atto, o testo vuoto.
	 *
	 * Facoltativo: la sua assenza non manca a niente. Due righe valgono come
	 * nessuna, perche' quale sia la prima dipende da come sono state scritte.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	public static function numero_proprio( int $atto_id ): string {
		$righe = get_post_meta( $atto_id, META_NUMERO_PROPRIO, false );

		if ( 1 !== count( $righe ) || ! is_string( $righe[0] ) ) {
			return '';
		}

		return self::numero_da_ingresso( $righe[0] );
	}

	/**
	 * I dati che mancano per mandare l'atto in verifica, per codice.
	 *
	 * Uno per mancanza, nominato. Il tipo senza durata valida e' una durata
	 * mancante e non un tipo mancante, e si guarda solo se il tipo c'e':
	 * altrimenti una sola mancanza produrrebbe due motivi. Mai le date di
	 * pubblicazione ne' il numero di repertorio, che li scrive il sistema.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, string> Motivi per codice, vuoto se non manca niente.
	 */
	public static function mancanti( int $atto_id ): array {
		$mancanti = array();
		$atto     = get_post( $atto_id );

		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return array(
				'albo_non_un_atto' => __( 'Il contenuto indicato non e\' un atto.', 'albo-pretorio-pa' ),
			);
		}

		if ( '' === trim( (string) $atto->post_title ) ) {
			$mancanti['albo_manca_oggetto'] = __( 'Manca l\'oggetto dell\'atto.', 'albo-pretorio-pa' );
		}

		$tipo = self::tipo( $atto_id );

		if ( null === $tipo ) {
			$mancanti['albo_manca_tipo'] = __( 'Manca il tipo di atto, che deve essere uno e uno solo.', 'albo-pretorio-pa' );
		} elseif ( is_wp_error( Durate::del_tipo( (int) $tipo->term_id ) ) ) {
			$mancanti['albo_manca_durata'] = sprintf(
				/* translators: %s: nome del tipo di atto. */
				__( 'Manca la durata di pubblicazione: il tipo di atto %s non ha una durata valida configurata.', 'albo-pretorio-pa' ),
				$tipo->name
			);
		}

		if ( null === self::organo( $atto_id ) ) {
			$mancanti['albo_manca_organo'] = __( 'Manca l\'organo che ha adottato l\'atto, che deve essere uno e uno solo.', 'albo-pretorio-pa' );
		}

		if ( null === self::data_adozione( $atto_id ) ) {
			$mancanti['albo_manca_data_adozione'] = __( 'Manca la data di adozione, oppure quella memorizzata non e\' una data valida.', 'albo-pretorio-pa' );
		}

		return $mancanti;
	}

	/**
	 * Una data vera del calendario nel formato AAAA-MM-GG, o nullo.
	 *
	 * Il formato si controlla prima, con le ancore che non accettano un a capo
	 * finale; la data si controlla poi rileggendola, perche' PHP trasforma da
	 * se' il 31 febbraio nel 3 marzo invece di rifiutarlo.
	 *
	 * @param mixed $valore Valore ricevuto o letto.
	 * @return string|null
	 */
	public static function data_da_ingresso( $valore ): ?string {
		if ( ! is_string( $valore ) || 1 !== preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $valore ) ) {
			return null;
		}

		$data = \DateTimeImmutable::createFromFormat( '!Y-m-d', $valore, wp_timezone() );

		if ( false === $data || $data->format( 'Y-m-d' ) !== $valore ) {
			return null;
		}

		return $valore;
	}

	/**
	 * Il numero proprio ripulito: testo su una riga, senza marcatori.
	 *
	 * @param mixed $valore Valore ricevuto o letto.
	 * @return string
	 */
	public static function numero_da_ingresso( $valore ): string {
		return is_string( $valore ) ? sanitize_text_field( $valore ) : '';
	}

	/**
	 * La voce dell'elenco assegnata all'atto, se e' una e una sola.
	 *
	 * @param int    $atto_id    Atto.
	 * @param string $tassonomia Elenco di voci.
	 * @return \WP_Term|null
	 */
	private static function voce_unica( int $atto_id, string $tassonomia ): ?\WP_Term {
		$voci = wp_get_object_terms( $atto_id, $tassonomia );

		if ( ! is_array( $voci ) || 1 !== count( $voci ) || ! $voci[0] instanceof \WP_Term ) {
			return null;
		}

		return $voci[0];
	}

	/**
	 * Filtro `pre_insert_term`: una voce nuova la crea chi governa l'elenco.
	 *
	 * WordPress, per assegnare una voce a un contenuto, chiede solo il permesso
	 * di assegnare; e se nella richiesta di salvataggio arriva il nome di una
	 * voce che non esiste, la crea. Chi redige potrebbe cosi' aggiungere tipi
	 * di atto e organi. La guardia vale quando la richiesta ha un utente: il
	 * codice che gira senza utente, come un comando da terminale, non e' una
	 * persona che scavalca un permesso.
	 *
	 * @param string|\WP_Error $voce       Nome della voce, o errore gia' deciso.
	 * @param string           $tassonomia Elenco di voci.
	 * @return string|\WP_Error
	 */
	public static function da_pre_insert_term( $voce, $tassonomia ) {
		if ( is_wp_error( $voce ) || ! in_array( $tassonomia, array( TASSONOMIA_TIPO_ATTO, TASSONOMIA_ORGANO ), true ) ) {
			return $voce;
		}

		if ( get_current_user_id() <= 0 ) {
			return $voce;
		}

		$oggetto = get_taxonomy( (string) $tassonomia );

		if ( false !== $oggetto && current_user_can( $oggetto->cap->manage_terms ) ) {
			return $voce;
		}

		return new \WP_Error(
			'albo_voce_non_creabile',
			__( 'Voce non creata: le voci nuove dei tipi di atto e degli organi le crea chi governa gli elenchi.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Toglie la dichiarazione dei due metadati.
	 *
	 * Serve alla suite di prove, dove tutto gira nello stesso processo.
	 */
	public static function azzera(): void {
		unregister_post_meta( TIPO, META_DATA_ADOZIONE );
		unregister_post_meta( TIPO, META_NUMERO_PROPRIO );
	}
}
