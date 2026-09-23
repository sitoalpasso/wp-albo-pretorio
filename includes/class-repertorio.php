<?php
/**
 * Numero di repertorio (ALBO-08).
 *
 * Ogni atto riceve, alla prima pubblicazione, un numero progressivo che
 * riparte ogni anno, per esempio 122/2026. Le garanzie sono quattro: unico,
 * progressivo, assegnato una sola volta, mai riutilizzato. L'assenza di buchi
 * non e' fra queste.
 *
 * **Le garanzie le fa rispettare la banca dati, non il codice.** Il contatore
 * avanza con un'unica istruzione che legge e incrementa insieme, e la tabella
 * delle assegnazioni ha due vincoli: la coppia anno e numero non si ripete, e
 * lo stesso atto non compare due volte. Due pubblicazioni nello stesso istante
 * non possono ottenere lo stesso numero, e due richieste che numerano lo stesso
 * atto nello stesso istante non possono dargliene due: una vince, l'altra
 * restituisce il numero della prima, e il numero che aveva preso resta un buco.
 *
 * **Il primo anno d'uso non ha una partenza predefinita.** Chi installa il
 * componente a meta' anno ha gia' un registro tenuto altrove: finche'
 * l'amministrazione non dichiara l'ultimo numero gia' usato, non si numera
 * niente. Negli anni successivi la numerazione riparte da 1 da sola, perche'
 * l'anno prima lo teneva gia' il componente.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/*
 * Le interrogazioni dirette sono il punto dell'unita': il contatore si legge e
 * si incrementa nella banca dati con un'istruzione sola, e nessuna lettura puo'
 * passare da una cache, che restituirebbe un numero vecchio.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Contatore annuale, assegnazione e dichiarazione della partenza.
 */
final class Repertorio {

	/**
	 * L'anno si e' aperto con la dichiarazione dell'amministrazione.
	 *
	 * @var string
	 */
	const APERTURA_DICHIARAZIONE = 'dichiarazione';

	/**
	 * L'anno si e' aperto proseguendo una numerazione gia' tenuta qui.
	 *
	 * @var string
	 */
	const APERTURA_PROSECUZIONE = 'prosecuzione';

	/**
	 * Il valore piu' alto che la colonna del contatore contiene.
	 *
	 * Una dichiarazione deve lasciare posto almeno al numero successivo, quindi
	 * l'ultimo numero dichiarabile e' questo meno uno.
	 *
	 * @var int
	 */
	const MASSIMO = 4294967295;

	/**
	 * Nome completo della tabella degli anni.
	 *
	 * @return string
	 */
	public static function tabella_anni(): string {
		global $wpdb;

		return $wpdb->prefix . TABELLA_REPERTORIO_ANNI;
	}

	/**
	 * Nome completo della tabella delle assegnazioni.
	 *
	 * @return string
	 */
	public static function tabella_assegnazioni(): string {
		global $wpdb;

		return $wpdb->prefix . TABELLA_REPERTORIO;
	}

	/**
	 * Crea o aggiorna le due tabelle, e verifica che ci siano.
	 *
	 * La chiama l'installazione, a ogni versione nuova: e' lo stesso percorso
	 * dei permessi, quindi un sito aggiornato riceve le tabelle senza nessuna
	 * attivazione.
	 *
	 * @return true|\WP_Error
	 */
	public static function installa() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collazione   = $wpdb->get_charset_collate();
		$anni         = self::tabella_anni();
		$assegnazioni = self::tabella_assegnazioni();

		dbDelta(
			array(
				"CREATE TABLE {$anni} (
  anno smallint(5) unsigned NOT NULL,
  ultimo int(10) unsigned NOT NULL,
  partenza int(10) unsigned NOT NULL,
  apertura varchar(20) NOT NULL,
  dichiarato_da bigint(20) unsigned NOT NULL,
  dichiarato_il datetime NULL,
  PRIMARY KEY  (anno)
) {$collazione};",
				"CREATE TABLE {$assegnazioni} (
  anno smallint(5) unsigned NOT NULL,
  numero int(10) unsigned NOT NULL,
  atto_id bigint(20) unsigned NOT NULL,
  assegnato_il datetime NOT NULL,
  PRIMARY KEY  (anno,numero),
  UNIQUE KEY atto_id (atto_id)
) {$collazione};",
			)
		);

		if ( ! self::tabelle_presenti() ) {
			return self::errore_tabelle();
		}

		return true;
	}

	/**
	 * Vero se le due tabelle esistono.
	 *
	 * Si chiede alla banca dati ogni volta, senza ricordarlo: assegnazione e
	 * dichiarazione sono operazioni rare, e una tabella che manca deve produrre
	 * un rifiuto con il motivo, non un errore della banca dati.
	 *
	 * @return bool
	 */
	public static function tabelle_presenti(): bool {
		global $wpdb;

		foreach ( array( self::tabella_anni(), self::tabella_assegnazioni() ) as $tabella ) {
			$trovata = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tabella ) ) );

			if ( $tabella !== $trovata ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * L'anno di un istante, nel fuso orario del sito.
	 *
	 * @param \DateTimeImmutable $istante Istante, in qualunque fuso.
	 * @return int
	 */
	public static function anno_di( \DateTimeImmutable $istante ): int {
		return (int) $istante->setTimezone( wp_timezone() )->format( 'Y' );
	}

	/**
	 * Il numero di un atto, se ne ha uno.
	 *
	 * @param int $atto_id Identificativo dell'atto.
	 * @return array{anno: int, numero: int}|null
	 */
	public static function di( int $atto_id ): ?array {
		global $wpdb;

		if ( ! self::tabelle_presenti() ) {
			return null;
		}

		$riga = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT anno, numero FROM %i WHERE atto_id = %d',
				self::tabella_assegnazioni(),
				$atto_id
			),
			ARRAY_A
		);

		if ( ! is_array( $riga ) ) {
			return null;
		}

		return array(
			'anno'   => (int) $riga['anno'],
			'numero' => (int) $riga['numero'],
		);
	}

	/**
	 * La riga di un anno, cosi' come la banca dati la contiene.
	 *
	 * @param int $anno Anno.
	 * @return array{anno: int, ultimo: int, partenza: int, apertura: string, dichiarato_da: int, dichiarato_il: ?string}|null
	 */
	public static function anno( int $anno ): ?array {
		global $wpdb;

		if ( ! self::tabelle_presenti() ) {
			return null;
		}

		$riga = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE anno = %d', self::tabella_anni(), $anno ),
			ARRAY_A
		);

		if ( ! is_array( $riga ) ) {
			return null;
		}

		return array(
			'anno'          => (int) $riga['anno'],
			'ultimo'        => (int) $riga['ultimo'],
			'partenza'      => (int) $riga['partenza'],
			'apertura'      => (string) $riga['apertura'],
			'dichiarato_da' => (int) $riga['dichiarato_da'],
			'dichiarato_il' => null === $riga['dichiarato_il'] ? null : (string) $riga['dichiarato_il'],
		);
	}

	/**
	 * Il numero che riceverebbe il prossimo atto pubblicato in quell'anno.
	 *
	 * @param int $anno Anno.
	 * @return int|\WP_Error Il numero, o il motivo per cui l'anno non numera.
	 */
	public static function prossimo( int $anno ) {
		if ( ! self::tabelle_presenti() ) {
			return self::errore_tabelle();
		}

		$riga = self::anno( $anno );

		if ( null !== $riga ) {
			return $riga['ultimo'] + 1;
		}

		if ( self::anni_precedenti( $anno ) ) {
			return 1;
		}

		return self::errore_partenza( $anno );
	}

	/**
	 * Dichiara l'ultimo numero gia' usato nell'anno, in un registro tenuto altrove.
	 *
	 * Ammessa solo nel primo anno d'uso, cioe' se nessun anno precedente e'
	 * stato tenuto qui, e solo finche' in quell'anno nessun numero e' stato
	 * assegnato. Dentro quella finestra si corregge quante volte serve.
	 *
	 * @param mixed              $ultimo    Ultimo numero gia' usato: intero da 0 in su, o cifre sole.
	 * @param int                $utente_id Chi dichiara.
	 * @param \DateTimeImmutable $ora       Istante della dichiarazione: ne decide l'anno.
	 * @return true|\WP_Error
	 */
	public static function dichiara( $ultimo, int $utente_id, \DateTimeImmutable $ora ) {
		global $wpdb;

		$valore = self::partenza_da_ingresso( $ultimo );

		if ( is_wp_error( $valore ) ) {
			return $valore;
		}

		if ( ! self::tabelle_presenti() ) {
			return self::errore_tabelle();
		}

		$anno = self::anno_di( $ora );

		if ( self::anni_precedenti( $anno ) ) {
			return new \WP_Error(
				'albo_repertorio_partenza_non_dichiarabile',
				sprintf(
					/* translators: %d: anno. */
					__( 'Partenza non dichiarata: la numerazione del %d prosegue quella tenuta qui negli anni precedenti, e riparte da 1 senza dichiarazione.', 'albo-pretorio-pa' ),
					$anno
				)
			);
		}

		$quando = $ora->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

		/*
		 * Prima l'anno nuovo, con un inserimento che non sovrascrive: se la riga
		 * c'e' gia', non tocca niente.
		 */
		$inserite = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (anno, ultimo, partenza, apertura, dichiarato_da, dichiarato_il) VALUES (%d, %d, %d, %s, %d, %s)',
				self::tabella_anni(),
				$anno,
				$valore,
				$valore,
				self::APERTURA_DICHIARAZIONE,
				$utente_id,
				$quando
			)
		);

		if ( 1 !== $inserite ) {
			/*
			 * La riga c'era: la correzione si fa solo se nessun numero e' stato
			 * assegnato, e la condizione sta nell'istruzione stessa, non in una
			 * lettura fatta prima, cosi' un'assegnazione che arriva nel frattempo
			 * non puo' passare in mezzo.
			 */
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET ultimo = %d, partenza = %d, dichiarato_da = %d, dichiarato_il = %s WHERE anno = %d AND apertura = %s AND ultimo = partenza',
					self::tabella_anni(),
					$valore,
					$valore,
					$utente_id,
					$quando,
					$anno,
					self::APERTURA_DICHIARAZIONE
				)
			);
		}

		$riga = self::anno( $anno );

		if ( null === $riga
			|| self::APERTURA_DICHIARAZIONE !== $riga['apertura']
			|| $valore !== $riga['partenza']
			|| $valore !== $riga['ultimo']
			|| $utente_id !== $riga['dichiarato_da']
		) {
			return new \WP_Error(
				'albo_repertorio_partenza_bloccata',
				sprintf(
					/* translators: %d: anno. */
					__( 'Partenza non cambiata: nel %d e\' gia\' stato assegnato almeno un numero, e da quel momento la dichiarazione non si corregge.', 'albo-pretorio-pa' ),
					$anno
				)
			);
		}

		return true;
	}

	/**
	 * Assegna il numero di repertorio a un atto, una volta sola.
	 *
	 * Se l'atto ha gia' un numero lo restituisce e non tocca il contatore. La
	 * chiamera' il passaggio a pubblicato, con l'istante della pubblicazione.
	 *
	 * @param int                $atto_id Identificativo dell'atto.
	 * @param \DateTimeImmutable $istante Istante della pubblicazione: ne decide l'anno.
	 * @return array{anno: int, numero: int}|\WP_Error
	 */
	public static function assegna( int $atto_id, \DateTimeImmutable $istante ) {
		global $wpdb;

		$atto = get_post( $atto_id );

		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return new \WP_Error(
				'albo_repertorio_non_un_atto',
				__( 'Numero non assegnato: il contenuto indicato non e\' un atto dell\'albo.', 'albo-pretorio-pa' )
			);
		}

		if ( ! self::tabelle_presenti() ) {
			return self::errore_tabelle();
		}

		$gia = self::di( $atto_id );

		if ( null !== $gia ) {
			return $gia;
		}

		$anno = self::anno_di( $istante );

		if ( null === self::anno( $anno ) ) {
			if ( ! self::anni_precedenti( $anno ) ) {
				return self::errore_partenza( $anno );
			}

			/*
			 * L'anno prosegue una numerazione tenuta qui, e riparte da 1. Se due
			 * richieste lo aprono insieme, la seconda non sovrascrive niente.
			 */
			$wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO %i (anno, ultimo, partenza, apertura, dichiarato_da, dichiarato_il) VALUES (%d, 0, 0, %s, 0, NULL)',
					self::tabella_anni(),
					$anno,
					self::APERTURA_PROSECUZIONE
				)
			);
		}

		/*
		 * Il cuore dell'unita': leggere e incrementare nella stessa istruzione.
		 * `LAST_INSERT_ID( espressione )` fa ricordare alla connessione il
		 * valore appena scritto, e solo a lei: due richieste simultanee non
		 * possono leggere lo stesso numero.
		 */
		$aggiornate = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET ultimo = LAST_INSERT_ID( ultimo + 1 ) WHERE anno = %d AND ultimo < %d',
				self::tabella_anni(),
				$anno,
				self::MASSIMO
			)
		);

		if ( 1 !== $aggiornate ) {
			return new \WP_Error(
				'albo_repertorio_contatore_non_avanzato',
				sprintf(
					/* translators: %d: anno. */
					__( 'Numero non assegnato: il contatore del %d non e\' avanzato.', 'albo-pretorio-pa' ),
					$anno
				)
			);
		}

		$numero = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (anno, numero, atto_id, assegnato_il) VALUES (%d, %d, %d, %s)',
				self::tabella_assegnazioni(),
				$anno,
				$numero,
				$atto_id,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		/*
		 * Si rilegge invece di fidarsi dell'inserimento. Se un'altra richiesta
		 * ha numerato lo stesso atto un istante prima, il vincolo sull'atto ha
		 * scartato questa riga: l'atto ha il numero dell'altra, e questo resta
		 * un buco.
		 */
		$assegnato = self::di( $atto_id );

		if ( null === $assegnato ) {
			return new \WP_Error(
				'albo_repertorio_non_registrato',
				__( 'Numero non assegnato: l\'assegnazione non risulta registrata.', 'albo-pretorio-pa' )
			);
		}

		return $assegnato;
	}

	/**
	 * Valida la partenza dichiarata.
	 *
	 * @param mixed $ultimo Valore ricevuto.
	 * @return int|\WP_Error
	 */
	private static function partenza_da_ingresso( $ultimo ) {
		$valore = null;

		if ( is_int( $ultimo ) ) {
			$valore = $ultimo;
		} elseif ( is_string( $ultimo ) && 1 === preg_match( '/\A[0-9]+\z/', $ultimo ) ) {
			$convertito = filter_var( $ultimo, FILTER_VALIDATE_INT );
			$valore     = false === $convertito ? null : $convertito;
		}

		if ( null === $valore || $valore < 0 || $valore >= self::MASSIMO ) {
			return new \WP_Error(
				'albo_repertorio_partenza_non_valida',
				__( 'Partenza non dichiarata: scrivere l\'ultimo numero gia\' usato quest\'anno come numero intero, da 0 in su, senza altri segni. Se non ne e\' stato usato nessuno, scrivere 0.', 'albo-pretorio-pa' )
			);
		}

		return $valore;
	}

	/**
	 * Vero se la banca dati contiene un anno precedente a quello dato.
	 *
	 * @param int $anno Anno.
	 * @return bool
	 */
	private static function anni_precedenti( int $anno ): bool {
		global $wpdb;

		$conteggio = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE anno < %d', self::tabella_anni(), $anno )
		);

		return (int) $conteggio > 0;
	}

	/**
	 * Il rifiuto per le tabelle mancanti.
	 *
	 * @return \WP_Error
	 */
	private static function errore_tabelle(): \WP_Error {
		return new \WP_Error(
			'albo_repertorio_tabelle_assenti',
			__( 'La numerazione degli atti non e\' disponibile: le sue tabelle non risultano create.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Il rifiuto per il primo anno senza partenza dichiarata.
	 *
	 * @param int $anno Anno.
	 * @return \WP_Error
	 */
	private static function errore_partenza( int $anno ): \WP_Error {
		return new \WP_Error(
			'albo_repertorio_partenza_non_dichiarata',
			sprintf(
				/* translators: %d: anno. */
				__( 'Nessun numero assegnato: per il %d non e\' ancora stata dichiarata la partenza della numerazione, cioe\' l\'ultimo numero gia\' usato in un registro precedente, oppure 0.', 'albo-pretorio-pa' ),
				$anno
			)
		);
	}
}
