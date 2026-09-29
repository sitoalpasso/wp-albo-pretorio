<?php
/**
 * I due passaggi che partono da un atto in verifica: pubblicazione e rimando in bozza.
 *
 * **Tutto o niente.** La pubblicazione scrive insieme lo stato, la data di
 * inizio, la data di fine e la voce di registro con la conferma del controllo
 * sui dati personali. Se uno solo di questi pezzi non riesce, l'atto resta in
 * verifica e identico a prima: le scritture avvengono dentro una transazione
 * della banca dati, che si chiude solo se tutte sono riuscite e rilette.
 *
 * **Il numero di repertorio si prende prima, e fuori.** Il catalogo vuole che
 * un numero non si riusi mai, nemmeno dopo un'operazione fallita a meta':
 * annullarlo insieme al resto lo renderebbe di nuovo disponibile. Il numero
 * preso resta dell'atto, e la pubblicazione riuscita dopo un fallimento
 * ritrova lo stesso.
 *
 * **Le date non si scelgono.** Inizio e fine le scrive il sistema, dal suo
 * orologio e dalla durata del tipo nell'istante della pubblicazione (ALBO-27).
 * Una data di inizio nel futuro, fornita o gia' scritta nella bozza, e' una
 * richiesta di programmazione e si rifiuta nominandola; una data passata e una
 * data di fine qualunque si ignorano.
 *
 * **Che cosa la transazione non copre.** Il numero di repertorio, per scelta.
 * E quello che altri componenti fanno negli agganci di WordPress durante la
 * scrittura: un messaggio inviato non si ritira con un annullamento, e un
 * aggancio che apre o chiude a sua volta una transazione chiude anche questa.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/*
 * La transazione e il controllo delle tabelle sono interrogazioni dirette per
 * natura: nessuna funzione di WordPress apre una transazione, e nessuna lettura
 * dello stato della banca dati puo' passare da una cache.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Pubblicazione e rimando in bozza.
 */
final class Passaggi {

	/**
	 * Azione della voce di registro con la conferma del controllo sui dati personali.
	 *
	 * @var string
	 */
	const AZIONE_CONFERMA = 'conferma_dati_personali';

	/**
	 * Azione della voce di registro del rimando in bozza, con il suo motivo.
	 *
	 * @var string
	 */
	const AZIONE_RIMANDO = 'rimando_in_bozza';

	/**
	 * Le chiavi che una richiesta di pubblicazione puo' portare.
	 *
	 * Le due date sono ammesse come chiavi solo per poter essere rifiutate o
	 * ignorate con un motivo: nessuna delle due diventa mai quella effettiva.
	 * Qualunque altra chiave, per esempio un numero di repertorio, rifiuta la
	 * richiesta per intero.
	 *
	 * @var array<int, string>
	 */
	const CHIAVI_RICHIESTA = array( 'conferma', 'data_inizio', 'data_fine' );

	/**
	 * Istante fissato dalle prove, o nullo per l'ora vera.
	 *
	 * @var \DateTimeImmutable|null
	 */
	private static $orologio = null;

	/**
	 * L'istante corrente, nel fuso del sito.
	 *
	 * @return \DateTimeImmutable
	 */
	public static function ora(): \DateTimeImmutable {
		return null === self::$orologio ? current_datetime() : self::$orologio->setTimezone( wp_timezone() );
	}

	/**
	 * Fissa l'orologio.
	 *
	 * @internal Solo per le prove, che fanno passare i giorni fra la verifica e la pubblicazione.
	 *
	 * @param \DateTimeImmutable $istante Istante.
	 */
	public static function fissa_orologio( \DateTimeImmutable $istante ): void {
		self::$orologio = $istante;
	}

	/**
	 * Rimette l'orologio sull'ora vera.
	 *
	 * @internal Solo per le prove.
	 */
	public static function azzera_orologio(): void {
		self::$orologio = null;
	}

	/**
	 * Pubblica un atto in verifica.
	 *
	 * @param int                  $atto_id   Atto.
	 * @param array<string, mixed> $richiesta `conferma`, obbligatoria e vera; `data_inizio` e `data_fine`, mai effettive.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 */
	public static function pubblica( int $atto_id, array $richiesta = array() ) {
		$sconosciute = array_diff( array_map( 'strval', array_keys( $richiesta ) ), self::CHIAVI_RICHIESTA );

		if ( array() !== $sconosciute ) {
			return new \WP_Error(
				'albo_richiesta_non_valida',
				sprintf(
					/* translators: %s: elenco delle chiavi non ammesse. */
					__( 'L\'atto resta in verifica: la richiesta porta dati che la pubblicazione non accetta (%s). Numero, date e stato li scrive il sistema.', 'albo-pretorio-pa' ),
					implode( ', ', $sconosciute )
				)
			);
		}

		$atto = self::atto_in_verifica( $atto_id, __( 'L\'atto non si pubblica: si pubblica soltanto un atto in verifica.', 'albo-pretorio-pa' ) );

		if ( is_wp_error( $atto ) ) {
			return $atto;
		}

		if ( ! current_user_can( 'publish_post', $atto_id ) ) {
			return new \WP_Error(
				'albo_pubblicazione_non_permessa',
				__( 'L\'atto resta in verifica: non si possiede il permesso di pubblicare questo atto.', 'albo-pretorio-pa' )
			);
		}

		if ( ! isset( $richiesta['conferma'] ) || true !== $richiesta['conferma'] ) {
			return new \WP_Error(
				'albo_conferma_mancante',
				__( 'L\'atto resta in verifica: manca la conferma di aver controllato i dati personali che l\'atto contiene o lascia capire.', 'albo-pretorio-pa' )
			);
		}

		$adesso = self::ora();
		$futura = self::data_inizio_futura( $atto, $richiesta, $adesso );

		if ( is_wp_error( $futura ) ) {
			return $futura;
		}

		$durata = self::giorni_di_pubblicazione( $atto_id );

		if ( is_wp_error( $durata ) ) {
			return $durata;
		}

		$tabelle = self::tabelle_senza_transazioni();

		if ( array() !== $tabelle ) {
			return self::errore_tabelle( $tabelle );
		}

		$prima = self::voci_automatiche( $atto_id, 'pubblicazione' );

		if ( is_wp_error( $prima ) ) {
			return $prima;
		}

		/*
		 * Il numero prima della transazione: vedi l'intestazione del file. Si
		 * prende dopo tutti i controlli che non scrivono niente, cosi' che una
		 * richiesta rifiutata per un motivo noto in anticipo non consumi numeri.
		 */
		$numero = Repertorio::assegna( $atto_id, $adesso );

		if ( is_wp_error( $numero ) ) {
			return $numero;
		}

		$inizio   = $adesso->setTimezone( wp_timezone() );
		$fine     = $inizio->setTime( 0, 0 )->add( new \DateInterval( 'P' . $durata . 'D' ) )->format( 'Y-m-d' );
		$campi    = array(
			'post_status'   => 'publish',
			'post_date'     => $inizio->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $inizio->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return $apertura;
		}

		try {
			$esito = self::scrivi_stato( $atto_id, $campi );

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_fine( $atto_id, $fine );
			}

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::controlla_voce_automatica( $atto_id, 'pubblicazione', $prima );
			}

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_voce(
					array(
						'sezione'   => SEZIONE,
						'azione'    => self::AZIONE_CONFERMA,
						'contenuto' => $atto_id,
						'dettagli'  => array(
							'anno'   => $numero['anno'],
							'numero' => $numero['numero'],
							'inizio' => $campi['post_date'],
							'fine'   => $fine,
						),
						'chiave'    => SEZIONE . ':' . self::AZIONE_CONFERMA . ':' . $atto_id,
					)
				);
			}

			if ( is_wp_error( $esito ) ) {
				self::annulla( $apertura, $atto_id );

				return $esito;
			}

			$chiusura = self::chiudi( $apertura, $atto_id );

			if ( is_wp_error( $chiusura ) ) {
				return $chiusura;
			}
		} catch ( \Throwable $errore ) {
			self::annulla( $apertura, $atto_id );

			throw $errore;
		}

		return array(
			'anno'   => $numero['anno'],
			'numero' => $numero['numero'],
			'inizio' => $campi['post_date'],
			'fine'   => $fine,
			'voce'   => (int) $esito,
		);
	}

	/**
	 * Rimanda in bozza un atto in verifica, con il suo motivo.
	 *
	 * @param int   $atto_id Atto.
	 * @param mixed $motivo  Motivo del rimando, testo non vuoto.
	 * @return int|\WP_Error Numero della voce di registro con il motivo, oppure errore.
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 */
	public static function rimanda_in_bozza( int $atto_id, $motivo ) {
		$atto = self::atto_in_verifica( $atto_id, __( 'L\'atto non si rimanda in bozza: si rimanda soltanto un atto in verifica.', 'albo-pretorio-pa' ) );

		if ( is_wp_error( $atto ) ) {
			return $atto;
		}

		if ( ! current_user_can( 'publish_post', $atto_id ) ) {
			return new \WP_Error(
				'albo_rimando_non_permesso',
				__( 'L\'atto resta in verifica: rimanda in bozza chi puo\' pubblicare questo atto, e non si possiede quel permesso.', 'albo-pretorio-pa' )
			);
		}

		$motivo = is_string( $motivo ) ? trim( sanitize_textarea_field( $motivo ) ) : '';

		if ( '' === $motivo ) {
			return new \WP_Error(
				'albo_motivo_mancante',
				__( 'L\'atto resta in verifica: per rimandarlo in bozza serve il motivo, che finisce nel registro delle modifiche e che chi redige leggera\'.', 'albo-pretorio-pa' )
			);
		}

		$tabelle = self::tabelle_senza_transazioni();

		if ( array() !== $tabelle ) {
			return self::errore_tabelle( $tabelle );
		}

		$prima = self::voci_automatiche( $atto_id, 'cambio_stato' );

		if ( is_wp_error( $prima ) ) {
			return $prima;
		}

		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return $apertura;
		}

		try {
			$esito = self::scrivi_stato( $atto_id, array( 'post_status' => 'draft' ) );

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::controlla_voce_automatica( $atto_id, 'cambio_stato', $prima );
			}

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_voce(
					array(
						'sezione'     => SEZIONE,
						'azione'      => self::AZIONE_RIMANDO,
						'contenuto'   => $atto_id,
						'motivazione' => $motivo,
					)
				);
			}

			if ( is_wp_error( $esito ) ) {
				self::annulla( $apertura, $atto_id );

				return $esito;
			}

			$chiusura = self::chiudi( $apertura, $atto_id );

			if ( is_wp_error( $chiusura ) ) {
				return $chiusura;
			}
		} catch ( \Throwable $errore ) {
			self::annulla( $apertura, $atto_id );

			throw $errore;
		}

		return (int) $esito;
	}

	/**
	 * L'atto, se e' un atto dell'albo in verifica.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $rifiuto Motivo quando l'atto esiste ma non e' in verifica.
	 * @return \WP_Post|\WP_Error
	 */
	private static function atto_in_verifica( int $atto_id, string $rifiuto ) {
		clean_post_cache( $atto_id );

		$atto = get_post( $atto_id );

		if ( ! TipoAtto::tipo_nostro() || ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return new \WP_Error(
				'albo_non_un_atto',
				__( 'Il contenuto indicato non e\' un atto dell\'albo.', 'albo-pretorio-pa' )
			);
		}

		if ( ChiusuraPubblicazione::IN_VERIFICA !== $atto->post_status ) {
			return new \WP_Error( 'albo_atto_non_in_verifica', $rifiuto );
		}

		return $atto;
	}

	/**
	 * Rifiuta una data di inizio nel futuro, fornita o gia' scritta nella bozza.
	 *
	 * Una data passata o uguale all'istante corrente non si rifiuta e non si
	 * usa: la data di inizio e' quella del sistema.
	 *
	 * @param \WP_Post             $atto      Atto.
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @param \DateTimeImmutable   $adesso    Istante della pubblicazione.
	 * @return true|\WP_Error
	 */
	private static function data_inizio_futura( \WP_Post $atto, array $richiesta, \DateTimeImmutable $adesso ) {
		$locale = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );

		if ( array_key_exists( 'data_inizio', $richiesta ) ) {
			$fornita = $richiesta['data_inizio'];
			$valida  = is_string( $fornita ) ? self::data_locale( $fornita ) : null;

			if ( null === $valida ) {
				return new \WP_Error(
					'albo_data_inizio_non_valida',
					__( 'L\'atto resta in verifica: la data di inizio fornita non e\' una data. La data di inizio non si fornisce: la scrive il sistema nell\'istante della pubblicazione.', 'albo-pretorio-pa' )
				);
			}

			if ( $valida > $locale ) {
				return self::errore_programmazione( $valida );
			}
		}

		$memorizzata = (string) $atto->post_date;

		if ( '' !== $memorizzata && '0000-00-00 00:00:00' !== $memorizzata && $memorizzata > $locale ) {
			return self::errore_programmazione( $memorizzata );
		}

		return true;
	}

	/**
	 * Una data o una data con ora, nel formato della banca dati.
	 *
	 * Una data senza ora vale come l'inizio di quel giorno: se quel giorno e'
	 * oggi, non e' nel futuro.
	 *
	 * @param string $valore Valore fornito.
	 * @return string|null
	 */
	private static function data_locale( string $valore ): ?string {
		foreach ( array(
			'!Y-m-d'       => 'Y-m-d',
			'!Y-m-d H:i:s' => 'Y-m-d H:i:s',
		) as $formato => $rilettura ) {
			$data = \DateTimeImmutable::createFromFormat( $formato, $valore, wp_timezone() );

			if ( false !== $data && $data->format( $rilettura ) === $valore ) {
				return $data->format( 'Y-m-d H:i:s' );
			}
		}

		return null;
	}

	/**
	 * Il rifiuto di una programmazione, con la data che la chiede.
	 *
	 * @param string $data Data di inizio chiesta, nel formato della banca dati.
	 * @return \WP_Error
	 */
	public static function errore_programmazione( string $data ): \WP_Error {
		return new \WP_Error(
			'albo_data_inizio_futura',
			sprintf(
				/* translators: %s: data e ora chieste per l'inizio. */
				__( 'L\'atto resta in verifica: la data di inizio %s e\' nel futuro, e la pubblicazione non si programma. La data di inizio la scrive il sistema nell\'istante in cui l\'atto si pubblica.', 'albo-pretorio-pa' ),
				$data
			)
		);
	}

	/**
	 * I giorni di pubblicazione dell'atto, ricontrollati adesso con tutti i suoi dati.
	 *
	 * I dati si sono controllati all'ingresso in verifica, ma fra la verifica e
	 * la pubblicazione possono cambiare fuori dall'atto: la durata tolta al
	 * tipo, una voce cancellata, un documento rimosso. La mancanza della durata
	 * ha il suo motivo, perche' quello che non si puo' fare e' calcolare la
	 * data di fine (ALBO-02).
	 *
	 * @param int $atto_id Atto.
	 * @return int|\WP_Error
	 */
	private static function giorni_di_pubblicazione( int $atto_id ) {
		$mancanti = DatiAtto::mancanti( $atto_id );
		$tipo     = DatiAtto::tipo( $atto_id );

		if ( isset( $mancanti['albo_manca_durata'] ) && null !== $tipo ) {
			unset( $mancanti['albo_manca_durata'] );

			$mancanti = array(
				'albo_fine_non_calcolabile' => sprintf(
					/* translators: %s: nome del tipo di atto. */
					__( 'La data di fine della pubblicazione non si puo\' calcolare: il tipo di atto %s non ha una durata valida configurata.', 'albo-pretorio-pa' ),
					$tipo->name
				),
			) + $mancanti;
		}

		if ( array() !== $mancanti ) {
			$errore = new \WP_Error();

			foreach ( $mancanti as $codice => $messaggio ) {
				$errore->add(
					(string) $codice,
					sprintf(
						/* translators: %s: il dato che manca. */
						__( 'L\'atto resta in verifica e non si pubblica. %s', 'albo-pretorio-pa' ),
						(string) $messaggio
					)
				);
			}

			return $errore;
		}

		$durata = null === $tipo ? null : Durate::del_tipo( (int) $tipo->term_id );

		if ( ! is_array( $durata ) ) {
			return new \WP_Error(
				'albo_fine_non_calcolabile',
				__( 'L\'atto resta in verifica e non si pubblica. La data di fine della pubblicazione non si puo\' calcolare.', 'albo-pretorio-pa' )
			);
		}

		return (int) $durata['giorni'];
	}

	/**
	 * Le tabelle che la pubblicazione scrive e che non conoscono le transazioni.
	 *
	 * Su una tabella di quel tipo annullare non disfa niente: meglio un albo
	 * che non pubblica di uno che pubblica a meta'. Una tabella che non si
	 * trova conta come non transazionale, cosi' che un nome cambiato fermi la
	 * pubblicazione invece di lasciarla senza protezione.
	 *
	 * **Il nome della tabella del registro e' quello del meccanismo comune**,
	 * che non lo espone: e' l'unico punto in cui l'albo lo conosce, e una
	 * prova verifica che la tabella esista con quel nome.
	 *
	 * @return array<int, string> Nomi delle tabelle, vuoto se sono tutte transazionali.
	 */
	public static function tabelle_senza_transazioni(): array {
		global $wpdb;

		$tabelle    = array( $wpdb->posts, $wpdb->postmeta, $wpdb->prefix . 'conformita_core_registro' );
		$segnaposto = implode( ', ', array_fill( 0, count( $tabelle ), '%s' ) );

		$transazionali = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- i segnaposto sono costruiti qui sopra, uno per tabella.
				"SELECT t.TABLE_NAME FROM information_schema.TABLES t JOIN information_schema.ENGINES e ON e.ENGINE = t.ENGINE WHERE t.TABLE_SCHEMA = DATABASE() AND e.TRANSACTIONS = 'YES' AND t.TABLE_NAME IN ( {$segnaposto} )",
				$tabelle
			)
		);

		return array_values( array_diff( $tabelle, is_array( $transazionali ) ? $transazionali : array() ) );
	}

	/**
	 * Il rifiuto per le tabelle che non annullano.
	 *
	 * @param array<int, string> $tabelle Tabelle.
	 * @return \WP_Error
	 */
	private static function errore_tabelle( array $tabelle ): \WP_Error {
		return new \WP_Error(
			'albo_tabelle_non_transazionali',
			sprintf(
				/* translators: %s: elenco delle tabelle. */
				__( 'L\'atto resta in verifica: queste tabelle della banca dati non permettono di annullare una scrittura a meta\' (%s), e una pubblicazione a meta\' non deve esistere. Chi gestisce il sito puo\' convertirle al tipo InnoDB.', 'albo-pretorio-pa' ),
				implode( ', ', $tabelle )
			)
		);
	}

	/**
	 * Apre la transazione, o un punto di ripristino dentro quella di un altro.
	 *
	 * Con la scrittura automatica spenta la connessione e' gia' dentro una
	 * transazione, e aprirne un'altra la chiuderebbe: in quel caso si mette un
	 * punto di ripristino, e si torna a quello.
	 *
	 * @return string|\WP_Error `transazione` o `punto`.
	 */
	private static function apri() {
		global $wpdb;

		$automatica = (string) $wpdb->get_var( 'SELECT @@autocommit' );
		$modo       = '1' === $automatica ? 'transazione' : 'punto';
		$esito      = 'transazione' === $modo
			? $wpdb->query( 'START TRANSACTION' )
			: $wpdb->query( 'SAVEPOINT albo_pretorio_passaggio' );

		if ( false === $esito ) {
			return new \WP_Error(
				'albo_transazione_non_aperta',
				__( 'L\'atto resta in verifica: la banca dati non ha aperto la transazione che rende la pubblicazione tutta o niente.', 'albo-pretorio-pa' )
			);
		}

		return $modo;
	}

	/**
	 * Chiude la transazione aperta, rendendo definitive le scritture.
	 *
	 * @param string $modo    Come e' stata aperta.
	 * @param int    $atto_id Atto, di cui svuotare la memoria se la chiusura non riesce.
	 * @return true|\WP_Error
	 */
	private static function chiudi( string $modo, int $atto_id ) {
		global $wpdb;

		$esito = 'transazione' === $modo
			? $wpdb->query( 'COMMIT' )
			: $wpdb->query( 'RELEASE SAVEPOINT albo_pretorio_passaggio' );

		if ( false === $esito ) {
			self::annulla( $modo, $atto_id );

			return new \WP_Error(
				'albo_transazione_non_chiusa',
				__( 'L\'atto resta in verifica: la banca dati non ha confermato le scritture della pubblicazione, che sono state annullate.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Annulla le scritture e svuota la memoria che le ricorda.
	 *
	 * Dopo l'annullamento la banca dati e' tornata indietro, la copia in
	 * memoria dell'atto no: senza svuotarla, il resto della richiesta
	 * leggerebbe un atto pubblicato che non esiste.
	 *
	 * @param string $modo    Come e' stata aperta.
	 * @param int    $atto_id Atto.
	 */
	private static function annulla( string $modo, int $atto_id ): void {
		global $wpdb;

		ChiusuraPubblicazione::revoca();

		if ( 'transazione' === $modo ) {
			$wpdb->query( 'ROLLBACK' );
		} else {
			$wpdb->query( 'ROLLBACK TO SAVEPOINT albo_pretorio_passaggio' );
		}

		clean_post_cache( $atto_id );
	}

	/**
	 * Scrive lo stato, e le date quando ci sono, attraverso WordPress.
	 *
	 * Passa da `wp_update_post` e non da un'interrogazione diretta perche' i
	 * passaggi di stato si registrano da se' nel meccanismo comune, e lo fanno
	 * dagli agganci di WordPress. Il guardiano lascia passare questa scrittura
	 * e nessun'altra, e scrive lui i campi concessi all'ultima priorita'.
	 *
	 * @param int                   $atto_id Atto.
	 * @param array<string, string> $campi   Campi concessi, stato compreso.
	 * @return true|\WP_Error
	 */
	private static function scrivi_stato( int $atto_id, array $campi ) {
		global $wpdb;

		$richiesta = array( 'ID' => $atto_id ) + $campi;

		if ( isset( $campi['post_date'] ) ) {
			$richiesta['edit_date'] = true;
		}

		ChiusuraPubblicazione::concedi( $atto_id, $campi );

		try {
			$esito = wp_update_post( $richiesta, true );
		} finally {
			ChiusuraPubblicazione::revoca();
		}

		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		clean_post_cache( $atto_id );

		$riga = $wpdb->get_row(
			$wpdb->prepare( "SELECT post_status, post_date, post_date_gmt FROM {$wpdb->posts} WHERE ID = %d", $atto_id ),
			ARRAY_A
		);

		foreach ( $campi as $campo => $valore ) {
			if ( ! is_array( $riga ) || ! isset( $riga[ $campo ] ) || (string) $riga[ $campo ] !== $valore ) {
				return new \WP_Error(
					'albo_stato_non_scritto',
					__( 'L\'atto resta in verifica: lo stato nuovo non risulta scritto nella banca dati.', 'albo-pretorio-pa' )
				);
			}
		}

		return true;
	}

	/**
	 * Scrive la data di fine con la funzione del meccanismo comune.
	 *
	 * La rilettura la fa la funzione stessa, che dichiara riuscita solo una
	 * scrittura che ritrova.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $fine    Data di fine.
	 * @return true|\WP_Error
	 */
	private static function scrivi_fine( int $atto_id, string $fine ) {
		$esito = conformita_core_imposta_fine_pubblicazione( $atto_id, $fine );

		if ( is_wp_error( $esito ) ) {
			return new \WP_Error(
				'albo_fine_non_scritta',
				sprintf(
					/* translators: %s: motivo riportato dal meccanismo comune. */
					__( 'L\'atto resta in verifica: la data di fine non e\' stata scritta. %s', 'albo-pretorio-pa' ),
					$esito->get_error_message()
				)
			);
		}

		return true;
	}

	/**
	 * Quante voci automatiche di un'azione ha l'atto.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $azione  Azione.
	 * @return int|\WP_Error
	 */
	private static function voci_automatiche( int $atto_id, string $azione ) {
		$voci = conformita_core_voci_registro(
			array(
				'contenuto' => $atto_id,
				'azione'    => $azione,
				'origine'   => 'automatica',
			)
		);

		if ( ! is_array( $voci ) ) {
			return new \WP_Error(
				'albo_registro_non_leggibile',
				__( 'L\'atto resta in verifica: il registro delle modifiche non si legge.', 'albo-pretorio-pa' )
			);
		}

		return count( $voci );
	}

	/**
	 * Il meccanismo comune ha scritto la voce del passaggio.
	 *
	 * La scrive da se' dall'aggancio del cambio di stato e, se non ci riesce,
	 * lo annota senza fermare niente: qui si controlla che ci sia una voce in
	 * piu' di prima, e senza quella il passaggio non si conferma.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $azione  Azione attesa.
	 * @param int    $prima   Voci di quell'azione prima del passaggio.
	 * @return true|\WP_Error
	 */
	private static function controlla_voce_automatica( int $atto_id, string $azione, int $prima ) {
		$dopo = self::voci_automatiche( $atto_id, $azione );

		if ( is_wp_error( $dopo ) ) {
			return $dopo;
		}

		if ( $prima + 1 !== $dopo ) {
			return new \WP_Error(
				'albo_voce_automatica_mancante',
				__( 'L\'atto resta in verifica: il registro delle modifiche non ha registrato il passaggio di stato.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Scrive la voce del passaggio nel registro.
	 *
	 * @param array<string, mixed> $voce Voce.
	 * @return int|\WP_Error
	 */
	private static function scrivi_voce( array $voce ) {
		$esito = conformita_core_registra_voce( $voce );

		if ( is_wp_error( $esito ) ) {
			return new \WP_Error(
				'albo_voce_non_scritta',
				sprintf(
					/* translators: %s: motivo riportato dal meccanismo comune. */
					__( 'L\'atto resta in verifica: la voce del registro delle modifiche non e\' stata scritta. %s', 'albo-pretorio-pa' ),
					$esito->get_error_message()
				)
			);
		}

		return (int) $esito;
	}
}
