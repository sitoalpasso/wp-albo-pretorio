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
 * **L'atto e' fermo per tutto il passaggio.** Dal primo controllo alla
 * chiusura della transazione l'atto non cambia se non per la scrittura
 * concessa: il guardiano la riconosce dal gettone di questo passaggio, che si
 * consuma alla prima scrittura. Prima di chiudere, la riga, i dati, le voci e
 * i documenti si rileggono dalla banca dati e si confrontano con quelli di
 * partenza: un cambiamento che non e' quello del passaggio annulla tutto.
 *
 * **Un passaggio alla volta.** Mentre un passaggio e' in corso nessun altro
 * comincia, nemmeno su un altro atto e nemmeno da un aggancio che usa le
 * funzioni dell'albo: il secondo aprirebbe la sua transazione dentro la
 * prima, chiudendola, o sostituirebbe il suo punto di ripristino, e
 * l'annullamento del primo non tornerebbe piu' indietro.
 *
 * **Che cosa la transazione non copre.** Il numero di repertorio, quando la
 * transazione e' la nostra; quando la connessione e' gia' dentro quella di un
 * altro, il numero segue quella. E quello che altri componenti fanno negli
 * agganci di WordPress durante la scrittura: un messaggio inviato non si
 * ritira con un annullamento, e un aggancio che apre o chiude a sua volta una
 * transazione chiude anche questa.
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
	 * Il campo della scrittura che porta il gettone del passaggio.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_gettone_passaggio';

	/**
	 * I passaggi in corso, per atto.
	 *
	 * Il gettone e' casuale e non esce da questa classe se non dentro la
	 * scrittura che lo porta. I campi sono quelli che la scrittura deve avere,
	 * e la fine e' quella che la pubblicazione scrivera'.
	 *
	 * @var array<int, array{gettone: string, campi: array<string, string>, usato: bool, fine: string|null, rifiuto: bool}>
	 */
	private static $in_corso = array();

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
	 * Se un passaggio e' in corso su questo atto.
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function in_corso( int $atto_id ): bool {
		return isset( self::$in_corso[ $atto_id ] );
	}

	/**
	 * Gli atti con un passaggio in corso.
	 *
	 * @return array<int, int>
	 */
	public static function atti_in_corso(): array {
		return array_keys( self::$in_corso );
	}

	/**
	 * La data di fine che la pubblicazione in corso scrive, o nulla.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null
	 */
	public static function fine_attesa( int $atto_id ): ?string {
		return isset( self::$in_corso[ $atto_id ] ) ? self::$in_corso[ $atto_id ]['fine'] : null;
	}

	/**
	 * Consuma il gettone del passaggio in corso, e restituisce i campi concessi.
	 *
	 * Risponde una volta sola, e solo al gettone giusto: e' la domanda che il
	 * guardiano fa a ogni scrittura di un atto con un passaggio in corso.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $gettone Gettone portato dalla scrittura.
	 * @return array<string, string>|null
	 */
	public static function consuma( int $atto_id, string $gettone ): ?array {
		if ( ! isset( self::$in_corso[ $atto_id ] ) || self::$in_corso[ $atto_id ]['usato'] || ! hash_equals( self::$in_corso[ $atto_id ]['gettone'], $gettone ) ) {
			return null;
		}

		self::$in_corso[ $atto_id ]['usato'] = true;

		return self::$in_corso[ $atto_id ]['campi'];
	}

	/**
	 * Annota che il guardiano ha rifiutato una scrittura mentre un passaggio e' in corso.
	 *
	 * Dentro un passaggio il guardiano non ferma la richiesta: rifiuta la
	 * scrittura e lo annota qui, e il passaggio, trovando l'annotazione,
	 * annulla tutto e risponde con il suo errore. Cosi' la transazione si
	 * chiude da questa classe, e non resta aperta a chi scrive dopo.
	 *
	 * @return bool Se c'era un passaggio in corso a cui annotarlo.
	 */
	public static function annota_rifiuto(): bool {
		foreach ( array_keys( self::$in_corso ) as $atto_id ) {
			self::$in_corso[ $atto_id ]['rifiuto'] = true;
		}

		return array() !== self::$in_corso;
	}

	/**
	 * Apre il passaggio su un atto: da qui l'atto e' fermo fino alla fine.
	 *
	 * @param int                   $atto_id Atto.
	 * @param array<string, string> $campi   Campi della scrittura: lo stato, pubblicato o bozza, e per la pubblicazione le date.
	 * @param string|null           $fine    Data di fine che la pubblicazione scrive.
	 */
	private static function inizia( int $atto_id, array $campi, ?string $fine ): void {
		self::$in_corso[ $atto_id ] = array(
			'gettone' => bin2hex( random_bytes( 16 ) ),
			'campi'   => $campi,
			'usato'   => false,
			'fine'    => $fine,
			'rifiuto' => false,
		);
	}

	/**
	 * Pubblica un atto in verifica.
	 *
	 * @param int                  $atto_id   Atto.
	 * @param array<string, mixed> $richiesta `conferma`, obbligatoria e vera; `data_inizio` e `data_fine`, mai effettive.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	public static function pubblica( int $atto_id, array $richiesta = array() ) {
		if ( array() !== self::$in_corso ) {
			return self::errore_annidato();
		}

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

		$prima      = self::voci_automatiche( $atto_id, 'pubblicazione' );
		$prima_fine = self::voci_automatiche( $atto_id, 'modifica_fine_pubblicazione' );

		if ( is_wp_error( $prima ) ) {
			return $prima;
		}

		if ( is_wp_error( $prima_fine ) ) {
			return $prima_fine;
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

		$inizio      = $adesso->setTimezone( wp_timezone() );
		$fine        = $inizio->setTime( 0, 0 )->add( new \DateInterval( 'P' . $durata . 'D' ) )->format( 'Y-m-d' );
		$campi       = array(
			'post_status'   => 'publish',
			'post_date'     => $inizio->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $inizio->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
		$partenza    = self::fotografia( $atto_id );
		$fine_uguale = array( $fine ) === self::righe_fine( $partenza );

		self::inizia( $atto_id, $campi, $fine );

		try {
			return self::pubblica_dentro( $atto_id, $campi, $fine, $numero, $partenza, $prima_fine, ! $fine_uguale, $prima );
		} finally {
			unset( self::$in_corso[ $atto_id ] );
		}
	}

	/**
	 * Le scritture della pubblicazione, dentro la transazione.
	 *
	 * @param int                           $atto_id    Atto.
	 * @param array<string, string>         $campi      Stato e date.
	 * @param string                        $fine       Data di fine.
	 * @param array{anno: int, numero: int} $numero     Numero preso.
	 * @param array<string, mixed>          $partenza   Fotografia di partenza.
	 * @param int                           $prima_fine Voci della fine prima del passaggio.
	 * @param bool                          $cambia     La data di fine cambia.
	 * @param int                           $prima      Voci della pubblicazione prima.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	private static function pubblica_dentro( int $atto_id, array $campi, string $fine, array $numero, array $partenza, int $prima_fine, bool $cambia, int $prima ) {
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
				$esito = self::controlla_voce_della_fine( $atto_id, $prima_fine, $cambia );
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

			if ( ! is_wp_error( $esito ) ) {
				$controllo = self::controlla_invariato( $atto_id, $partenza, $campi, $fine );
				$esito     = is_wp_error( $controllo ) ? $controllo : $esito;
			}

			if ( is_wp_error( $esito ) ) {
				$annullato = self::annulla( $apertura, $atto_id );

				return is_wp_error( $annullato ) ? $annullato : $esito;
			}

			$chiusura = self::chiudi( $apertura, $atto_id );

			if ( is_wp_error( $chiusura ) ) {
				return $chiusura;
			}

			self::svuota_memoria( $atto_id );
		} catch ( \Throwable $errore ) {
			$annullato = self::annulla( $apertura, $atto_id );

			if ( is_wp_error( $annullato ) ) {
				throw new \RuntimeException( esc_html( $annullato->get_error_message() ), 0, $errore ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- gia' sfuggito.
			}

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
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	public static function rimanda_in_bozza( int $atto_id, $motivo ) {
		if ( array() !== self::$in_corso ) {
			return self::errore_annidato();
		}

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

		$campi    = array( 'post_status' => 'draft' );
		$partenza = self::fotografia( $atto_id );

		self::inizia( $atto_id, $campi, null );

		try {
			return self::rimanda_dentro( $atto_id, $campi, $motivo, $partenza, $prima );
		} finally {
			unset( self::$in_corso[ $atto_id ] );
		}
	}

	/**
	 * Le scritture del rimando, dentro la transazione.
	 *
	 * @param int                   $atto_id  Atto.
	 * @param array<string, string> $campi    Stato.
	 * @param string                $motivo   Motivo.
	 * @param array<string, mixed>  $partenza Fotografia di partenza.
	 * @param int                   $prima    Voci del cambio di stato prima.
	 * @return int|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	private static function rimanda_dentro( int $atto_id, array $campi, string $motivo, array $partenza, int $prima ) {
		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return $apertura;
		}

		try {
			$esito = self::scrivi_stato( $atto_id, $campi );

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

			if ( ! is_wp_error( $esito ) ) {
				$controllo = self::controlla_invariato( $atto_id, $partenza, $campi, null );
				$esito     = is_wp_error( $controllo ) ? $controllo : $esito;
			}

			if ( is_wp_error( $esito ) ) {
				$annullato = self::annulla( $apertura, $atto_id );

				return is_wp_error( $annullato ) ? $annullato : $esito;
			}

			$chiusura = self::chiudi( $apertura, $atto_id );

			if ( is_wp_error( $chiusura ) ) {
				return $chiusura;
			}

			self::svuota_memoria( $atto_id );
		} catch ( \Throwable $errore ) {
			$annullato = self::annulla( $apertura, $atto_id );

			if ( is_wp_error( $annullato ) ) {
				throw new \RuntimeException( esc_html( $annullato->get_error_message() ), 0, $errore ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- gia' sfuggito.
			}

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
		self::svuota_memoria( $atto_id );

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
	 * **Le tabelle delle voci ci sono anche se la pubblicazione non le scrive.**
	 * Durante un passaggio una voce assegnata o tolta da un altro componente
	 * non si puo' fermare prima che avvenga: si lascia avvenire e il passaggio
	 * si annulla. L'annullamento la disfa solo se le tabelle delle voci, le
	 * relazioni e i loro conteggi, conoscono le transazioni.
	 *
	 * **Il nome della tabella del registro e' quello del meccanismo comune**,
	 * che non lo espone: e' l'unico punto in cui l'albo lo conosce, e una
	 * prova verifica che la tabella esista con quel nome.
	 *
	 * @return array<int, string> Nomi delle tabelle, vuoto se sono tutte transazionali.
	 */
	public static function tabelle_senza_transazioni(): array {
		global $wpdb;

		$tabelle    = array( $wpdb->posts, $wpdb->postmeta, $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->prefix . 'conformita_core_registro' );
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
			$annullato = self::annulla( $modo, $atto_id );

			if ( is_wp_error( $annullato ) ) {
				return $annullato;
			}

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
	 * Dopo l'annullamento la banca dati e' tornata indietro su tutto quello
	 * che e' stato scritto nella transazione, dall'albo e da chiunque altro
	 * negli agganci di WordPress: l'atto, le sue voci, i loro conteggi, e
	 * qualunque altro contenuto un aggancio abbia toccato. La memoria non sa
	 * che cosa: l'unica di cui fidarsi e' una memoria vuota, e si svuota per
	 * intero. Succede solo quando un passaggio fallisce. Lo svuotamento
	 * intero non dipende dalla sospensione che chi chiama puo' aver messo.
	 *
	 * Se la banca dati non conferma l'annullamento, le scritture potrebbero
	 * esserci ancora: lo si dice, invece di dichiarare annullato un passaggio
	 * che forse non lo e'.
	 *
	 * @param string $modo    Come e' stata aperta.
	 * @param int    $atto_id Atto.
	 * @return true|\WP_Error
	 */
	private static function annulla( string $modo, int $atto_id ) {
		global $wpdb;

		$esito = 'transazione' === $modo
			? $wpdb->query( 'ROLLBACK' )
			: $wpdb->query( 'ROLLBACK TO SAVEPOINT albo_pretorio_passaggio' );

		wp_cache_flush();

		if ( false === $esito ) {
			return new \WP_Error(
				'albo_annullamento_non_riuscito',
				sprintf(
					/* translators: %d: identificativo dell'atto. */
					__( 'La banca dati non ha confermato l\'annullamento delle scritture del passaggio sull\'atto %d: lo stato dell\'atto non e\' garantito e va controllato prima di qualunque altra operazione.', 'albo-pretorio-pa' ),
					$atto_id
				)
			);
		}

		return true;
	}

	/**
	 * Il rifiuto di un passaggio cominciato mentre un altro e' in corso.
	 *
	 * @return \WP_Error
	 */
	private static function errore_annidato(): \WP_Error {
		return new \WP_Error(
			'albo_passaggio_annidato',
			__( 'L\'atto resta com\'era: e\' gia\' in corso una pubblicazione o un rimando in bozza, e un altro passaggio comincia solo quando quello e\' finito.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Svuota la memoria di WordPress sull'atto: riga, dati, voci e i conteggi delle sue voci.
	 *
	 * Si usa all'inizio del passaggio, prima di leggere l'atto, e alla fine di
	 * un passaggio riuscito, perche' chi legge dopo trovi lo stato scritto.
	 * Dopo un annullamento la memoria si svuota invece per intero.
	 *
	 * **Anche quando chi chiama ha sospeso lo svuotamento.** WordPress lascia
	 * sospendere lo svuotamento della memoria per tutta la richiesta, e in
	 * quel caso `clean_post_cache()` non fa niente: il passaggio leggerebbe lo
	 * stato che la memoria ricorda invece di quello della banca dati, e chi
	 * legge dopo un passaggio riuscito lo stato di prima. Qui la
	 * sospensione si toglie per il tempo dello svuotamento, e si rimette
	 * com'era.
	 *
	 * @param int $atto_id Atto.
	 */
	private static function svuota_memoria( int $atto_id ): void {
		global $wpdb;

		$sospesa = wp_suspend_cache_invalidation( false );

		try {
			clean_post_cache( $atto_id );

			// I conteggi delle voci: una pubblicazione riuscita conta l'atto, un rimando non piu'.
			$voci = $wpdb->get_col(
				$wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d", $atto_id )
			);

			if ( is_array( $voci ) && array() !== $voci ) {
				clean_term_cache( array_map( 'intval', $voci ), '', false );
			}
		} finally {
			wp_suspend_cache_invalidation( (bool) $sospesa );
		}
	}

	/**
	 * Scrive lo stato, e le date quando ci sono, attraverso WordPress.
	 *
	 * Passa da `wp_update_post` e non da un'interrogazione diretta perche' i
	 * passaggi di stato si registrano da se' nel meccanismo comune, e lo fanno
	 * dagli agganci di WordPress. Il guardiano lascia passare questa scrittura
	 * e nessun'altra, riconoscendola dal gettone, e scrive lui i campi concessi
	 * all'ultima priorita'. Se il gettone non e' stato consumato, la scrittura
	 * non e' passata dal guardiano e non vale.
	 *
	 * @param int                   $atto_id Atto.
	 * @param array<string, string> $campi   Campi concessi, stato compreso.
	 * @return true|\WP_Error
	 */
	private static function scrivi_stato( int $atto_id, array $campi ) {
		global $wpdb;

		$richiesta = array( 'ID' => $atto_id ) + $campi + array( self::CAMPO_GETTONE => self::$in_corso[ $atto_id ]['gettone'] );

		if ( isset( $campi['post_date'] ) ) {
			$richiesta['edit_date'] = true;
		}

		$esito = wp_update_post( $richiesta, true );

		if ( is_wp_error( $esito ) || 0 === $esito || ! self::$in_corso[ $atto_id ]['usato'] ) {
			return new \WP_Error(
				'albo_stato_non_scritto',
				__( 'L\'atto resta in verifica: lo stato nuovo non risulta scritto nella banca dati.', 'albo-pretorio-pa' )
			);
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

	/**
	 * Il meccanismo comune ha registrato la data di fine, se e' cambiata.
	 *
	 * Se la fine era gia' quella giusta il meccanismo comune non scrive
	 * niente, e non deve comparire nessuna voce; se cambia, almeno una.
	 *
	 * @param int  $atto_id Atto.
	 * @param int  $prima   Voci della fine prima del passaggio.
	 * @param bool $cambia  La data di fine cambia.
	 * @return true|\WP_Error
	 */
	private static function controlla_voce_della_fine( int $atto_id, int $prima, bool $cambia ) {
		$dopo = self::voci_automatiche( $atto_id, 'modifica_fine_pubblicazione' );

		if ( is_wp_error( $dopo ) ) {
			return $dopo;
		}

		if ( $cambia ? $dopo <= $prima : $dopo !== $prima ) {
			return new \WP_Error(
				'albo_voce_automatica_mancante',
				__( 'L\'atto resta in verifica: il registro delle modifiche non corrisponde alla data di fine scritta.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * L'atto com'e' nella banca dati: riga, dati, voci e contenuti figli.
	 *
	 * Letto direttamente, senza la memoria di WordPress, perche' serve a
	 * confrontare quello che la transazione sta per confermare.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>
	 */
	private static function fotografia( int $atto_id ): array {
		global $wpdb;

		return array(
			'riga'  => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $atto_id ), ARRAY_A ),
			'dati'  => $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $atto_id ), ARRAY_A ),
			'voci'  => $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id = %d ORDER BY term_taxonomy_id", $atto_id ) ),
			'figli' => $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_parent FROM {$wpdb->posts} WHERE post_parent = %d ORDER BY ID", $atto_id ), ARRAY_A ),
		);
	}

	/**
	 * I valori della data di fine in una fotografia, nell'ordine in cui sono scritti.
	 *
	 * @param array<string, mixed> $fotografia Fotografia.
	 * @return array<int, string>
	 */
	private static function righe_fine( array $fotografia ): array {
		$chiave = conformita_core_chiave_fine_pubblicazione();
		$valori = array();

		foreach ( (array) $fotografia['dati'] as $riga ) {
			if ( $chiave === $riga['meta_key'] ) {
				$valori[] = (string) $riga['meta_value'];
			}
		}

		return $valori;
	}

	/**
	 * L'atto e' cambiato solo come il passaggio vuole.
	 *
	 * Nella riga cambiano lo stato, le date della pubblicazione, le due date di
	 * modifica e, alla pubblicazione, il nome nell'indirizzo se non c'era. Nei
	 * dati cambiano la data di fine della pubblicazione, che deve essere una
	 * sola e quella calcolata, e i dati di servizio di WordPress. Voci e
	 * contenuti figli non cambiano.
	 *
	 * @param int                   $atto_id  Atto.
	 * @param array<string, mixed>  $partenza Fotografia di partenza.
	 * @param array<string, string> $campi    Campi concessi.
	 * @param string|null           $fine     Data di fine, per la pubblicazione.
	 * @return true|\WP_Error
	 */
	private static function controlla_invariato( int $atto_id, array $partenza, array $campi, ?string $fine ) {
		if ( ! empty( self::$in_corso[ $atto_id ]['rifiuto'] ) ) {
			return new \WP_Error(
				'albo_scrittura_rifiutata_nel_passaggio',
				__( 'L\'atto resta com\'era: durante il passaggio un\'altra scrittura ha provato a cambiare un atto dell\'albo ed e\' stata rifiutata, e tutto e\' stato annullato.', 'albo-pretorio-pa' )
			);
		}

		$arrivo = self::fotografia( $atto_id );
		$errore = new \WP_Error(
			'albo_atto_cambiato_nel_passaggio',
			__( 'L\'atto resta in verifica: durante il passaggio e\' cambiato qualcosa che il passaggio non doveva cambiare, e tutto e\' stato annullato.', 'albo-pretorio-pa' )
		);

		if ( ! is_array( $partenza['riga'] ) || ! is_array( $arrivo['riga'] ) ) {
			return $errore;
		}

		foreach ( $partenza['riga'] as $colonna => $valore ) {
			$nuovo = isset( $arrivo['riga'][ $colonna ] ) ? (string) $arrivo['riga'][ $colonna ] : null;

			if ( in_array( $colonna, ChiusuraPubblicazione::CAMPI_DI_WORDPRESS, true ) ) {
				continue;
			}

			if ( array_key_exists( $colonna, $campi ) ) {
				if ( $campi[ $colonna ] !== $nuovo ) {
					return $errore;
				}

				continue;
			}

			if ( 'post_name' === $colonna && '' === (string) $valore && null !== $fine && '' !== (string) $nuovo ) {
				continue;
			}

			if ( ( null === $valore ? null : (string) $valore ) !== $nuovo ) {
				return $errore;
			}
		}

		$chiave = conformita_core_chiave_fine_pubblicazione();
		$dati   = static function ( array $fotografia ) use ( $chiave, $fine ): array {
			return array_values(
				array_filter(
					(array) $fotografia['dati'],
					static function ( $riga ) use ( $chiave, $fine ): bool {
						return ! in_array( $riga['meta_key'], ChiusuraPubblicazione::CHIAVI_DI_SERVIZIO, true ) && ( null === $fine || $chiave !== $riga['meta_key'] );
					}
				)
			);
		};

		if ( $dati( $partenza ) !== $dati( $arrivo ) || ( null !== $fine && array( $fine ) !== self::righe_fine( $arrivo ) ) ) {
			return $errore;
		}

		if ( $partenza['voci'] !== $arrivo['voci'] || $partenza['figli'] !== $arrivo['figli'] ) {
			return $errore;
		}

		return true;
	}
}
