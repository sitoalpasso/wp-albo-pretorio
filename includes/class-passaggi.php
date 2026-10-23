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
	 * Azione della voce di registro dell'atto importato da Albo Pretorio On line, con origine e dubbi portati.
	 *
	 * @var string
	 */
	const AZIONE_IMPORTAZIONE = 'importazione';

	/**
	 * Azione della voce di registro dell'annullamento, con il suo motivo.
	 *
	 * @var string
	 */
	const AZIONE_ANNULLAMENTO = 'annullamento';

	/**
	 * L'origine scritta nei dettagli dell'annullamento avvenuto in Albo Pretorio On line.
	 *
	 * @var string
	 */
	const ORIGINE_ANNULLAMENTO_IMPORTATO = 'Albo Pretorio On line';

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
	 * Una proroga e' in corso in questa richiesta, anche senza atti toccati.
	 *
	 * @var bool
	 */
	private static $operazione = false;

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
	 * Se il passaggio in corso su questo atto e' una pubblicazione.
	 *
	 * Solo la pubblicazione, e l'importazione che porta a pubblicato un atto
	 * di Albo Pretorio On line, lasciano scrivere a WordPress il nome nell'indirizzo
	 * di un atto pubblicato che non lo ha ancora: la proroga e il rimando no.
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function pubblicazione_in_corso( int $atto_id ): bool {
		return isset( self::$in_corso[ $atto_id ] ) && in_array( self::$in_corso[ $atto_id ]['tipo'], array( 'pubblicazione', 'importazione' ), true );
	}

	/**
	 * Se il passaggio in corso su questo atto e' un'importazione da Albo Pretorio On line.
	 *
	 * E' l'unico passaggio che parte da una bozza: la bozza preparata
	 * dall'importazione, che arriva pubblicata senza passare dalla verifica.
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function importazione_in_corso( int $atto_id ): bool {
		return isset( self::$in_corso[ $atto_id ] ) && self::AZIONE_IMPORTAZIONE === self::$in_corso[ $atto_id ]['tipo'];
	}

	/**
	 * Se il passaggio in corso su questo atto e' un annullamento.
	 *
	 * E' l'unico passaggio che parte da un atto pubblicato.
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function annullamento_in_corso( int $atto_id ): bool {
		return isset( self::$in_corso[ $atto_id ] ) && self::AZIONE_ANNULLAMENTO === self::$in_corso[ $atto_id ]['tipo'];
	}

	/**
	 * Gli atti con un passaggio in corso.
	 *
	 * @return array<int, int>
	 */
	public static function atti_in_corso(): array {
		return array_keys(
			array_filter(
				self::$in_corso,
				static function ( array $passaggio ): bool {
					return empty( $passaggio['revocata'] );
				}
			)
		);
	}

	/**
	 * Toglie le concessioni sugli atti del passaggio, finite le scritture previste.
	 *
	 * Gli atti restano nel passaggio, cosi' che un rifiuto si annoti e il
	 * passaggio annulli; ma nessuna scrittura e' piu' concessa, nemmeno quella
	 * della data di fine, e la protezione delle istruzioni li considera di
	 * nuovo fermi. Serve fra le scritture e i controlli finali: una scrittura
	 * su un atto gia' controllato non passerebbe piu' da nessun controllo.
	 */
	private static function revoca_concessioni(): void {
		foreach ( array_keys( self::$in_corso ) as $atto_id ) {
			self::$in_corso[ $atto_id ]['fine']     = null;
			self::$in_corso[ $atto_id ]['campi']    = array();
			self::$in_corso[ $atto_id ]['usato']    = true;
			self::$in_corso[ $atto_id ]['revocata'] = true;
		}
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
	 * @param string                $tipo    `pubblicazione`, `rimando`, `proroga`, `importazione` o `annullamento`.
	 */
	private static function inizia( int $atto_id, array $campi, ?string $fine, string $tipo ): void {
		self::$in_corso[ $atto_id ] = array(
			'tipo'    => $tipo,
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
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, o se un altro componente solleva un'eccezione dopo la conferma; con l'eccezione come causa.
	 */
	public static function pubblica( int $atto_id, array $richiesta = array() ) {
		if ( self::occupato() ) {
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

		// L'atto come i controlli lo leggono: sotto il blocco della transazione dovra' essere ancora questo.
		$partenza = self::fotografia( $atto_id );

		if ( null === $partenza ) {
			return self::errore_lettura();
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

		/*
		 * La fine si calcola qui, prima del numero: senza la scelta sui
		 * festivi non si sa quale fine scrivere, e il rifiuto non deve
		 * consumare numeri.
		 */
		$inizio    = $adesso->setTimezone( wp_timezone() );
		$calcolata = $inizio->setTime( 0, 0 )->add( new \DateInterval( 'P' . $durata . 'D' ) )->format( 'Y-m-d' );
		$slittata  = self::fine_slittata( $calcolata, __( 'L\'atto resta in verifica:', 'albo-pretorio-pa' ) );

		if ( is_wp_error( $slittata ) ) {
			return $slittata;
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

		$fine        = $slittata['fine'];
		$campi       = array(
			'post_status'   => 'publish',
			'post_date'     => $inizio->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $inizio->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
		$fine_uguale = array( $fine ) === self::righe_fine( $partenza );

		self::inizia( $atto_id, $campi, $fine, 'pubblicazione' );

		try {
			$esito = self::pubblica_dentro( $atto_id, $campi, $slittata, $numero, $partenza, $prima_fine, ! $fine_uguale, $prima );
		} finally {
			unset( self::$in_corso[ $atto_id ] );
		}

		return self::dopo_la_chiusura( $atto_id, $esito );
	}

	/**
	 * Le scritture della pubblicazione, dentro la transazione.
	 *
	 * @param int                           $atto_id    Atto.
	 * @param array<string, string>         $campi      Stato e date.
	 * @param array<string, mixed>          $slittata   Data di fine, con la fine calcolata e i festivi saltati.
	 * @param array{anno: int, numero: int} $numero     Numero preso.
	 * @param array<string, mixed>          $partenza   Fotografia di partenza.
	 * @param int                           $prima_fine Voci della fine prima del passaggio.
	 * @param bool                          $cambia     La data di fine cambia.
	 * @param int                           $prima      Voci della pubblicazione prima.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	private static function pubblica_dentro( int $atto_id, array $campi, array $slittata, array $numero, array $partenza, int $prima_fine, bool $cambia, int $prima ) {
		$fine     = (string) $slittata['fine'];
		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return $apertura;
		}

		/*
		 * Dalla transazione aperta in poi tutto sta dentro la stessa difesa,
		 * blocco compreso: un rifiuto o un'eccezione mentre la riga si blocca
		 * e si rilegge annullano come quelli delle scritture.
		 */
		try {
			$esito = self::blocca( $atto_id, $partenza );

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_stato( $atto_id, $campi );
			}

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
							'anno'            => $numero['anno'],
							'numero'          => $numero['numero'],
							'inizio'          => $campi['post_date'],
							'fine'            => $fine,
							'fine_calcolata'  => $slittata['calcolata'],
							'slittamento'     => $slittata['slittamento'],
							'festivi_saltati' => self::saltati_per_registro( $slittata['saltati'] ),
						) + DatiProcedimento::per_registro( $atto_id ),
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
	 * Pubblica una bozza preparata dall'importazione, con il numero e le date di Albo Pretorio On line (ALBO-35).
	 *
	 * E' l'unico passaggio da bozza a pubblicato, e non e' una pubblicazione:
	 * l'atto e' stato pubblicato in Albo Pretorio On line, e qui arriva con i fatti
	 * avvenuti la'. Numero, anno, inizio e fine non li calcola il sistema, li
	 * porta chi importa dal backup; tutto il resto e' come la pubblicazione:
	 * stesse difese del guardiano, stessa transazione, stesso controllo finale
	 * che niente sia cambiato oltre a quello che il passaggio scrive. Il numero
	 * si registra dentro la transazione: e' un numero gia' usato altrove, e se
	 * l'importazione dell'atto non riesce deve tornare libero.
	 *
	 * Un inizio nel futuro si rifiuta: questo albo non programma.
	 *
	 * L'atto annullato in Albo Pretorio On line arriva annullato, nella
	 * stessa transazione (ALBO-22): dopo le scritture della pubblicazione
	 * vengono quelle dell'annullamento, e il controllo finale pretende lo
	 * stato annullato. Pubblicato con i documenti non lo vede mai nessuno.
	 *
	 * @param int                  $atto_id Bozza preparata dall'importazione.
	 * @param array<string, mixed> $dati    `anno`, `numero`, `inizio` e `fine` (AAAA-MM-GG), `dettagli` per la voce di registro, `annullamento` con `il` (AAAA-MM-GG) e `motivo` per l'atto annullato in Albo Pretorio On line.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, o se un altro componente solleva un'eccezione dopo la conferma; con l'eccezione come causa.
	 */
	public static function importa( int $atto_id, array $dati ) {
		if ( self::occupato() ) {
			return self::errore_annidato();
		}

		self::svuota_memoria( $atto_id );

		$atto = get_post( $atto_id );

		if ( ! TipoAtto::tipo_nostro() || ! $atto instanceof \WP_Post || TIPO !== $atto->post_type || 'draft' !== $atto->post_status ) {
			return new \WP_Error(
				'albo_importazione_non_una_bozza',
				__( 'L\'atto non si importa: si porta a pubblicato soltanto la bozza preparata dall\'importazione.', 'albo-pretorio-pa' )
			);
		}

		/*
		 * Il passaggio salta la verifica: lo si concede solo alla bozza che
		 * l'importazione sta scrivendo in questo momento, che porta il ricordo
		 * dell'origine, a chi puo' importare. Una bozza qualunque non passa.
		 */
		if ( ! Importazione::in_lavorazione( $atto_id ) || '' === (string) get_post_meta( $atto_id, META_ORIGINE_IMPORTAZIONE, true ) ) {
			return new \WP_Error(
				'albo_importazione_non_una_bozza',
				__( 'L\'atto non si importa: si porta a pubblicato soltanto la bozza preparata dall\'importazione.', 'albo-pretorio-pa' )
			);
		}

		if ( ! current_user_can( 'publish_post', $atto_id ) || ! SchermataImportazione::permessa() ) {
			return new \WP_Error(
				'albo_pubblicazione_non_permessa',
				__( 'L\'atto non si importa: non si possiede il permesso di pubblicare questo atto.', 'albo-pretorio-pa' )
			);
		}

		$inizio = is_string( $dati['inizio'] ?? null ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $dati['inizio'], wp_timezone() ) : false;
		$fine   = is_string( $dati['fine'] ?? null ) ? (string) $dati['fine'] : '';
		$anno   = is_int( $dati['anno'] ?? null ) ? $dati['anno'] : 0;
		$numero = is_int( $dati['numero'] ?? null ) ? $dati['numero'] : 0;
		$adesso = self::ora();
		$valida = false !== $inizio && $inizio->format( 'Y-m-d' ) === $dati['inizio'] && true === conformita_core_valida_fine_pubblicazione( $fine );
		$oggi   = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		if ( ! $valida || $fine < $dati['inizio'] ) {
			return new \WP_Error(
				'albo_importazione_date_non_valide',
				__( 'L\'atto non si importa: le date di inizio e di fine di Albo Pretorio On line non sono valide.', 'albo-pretorio-pa' )
			);
		}

		if ( $dati['inizio'] > $oggi ) {
			return self::errore_programmazione( $inizio->format( 'Y-m-d H:i:s' ) );
		}

		$annullamento = self::annullamento_importato( $dati['annullamento'] ?? null, $oggi );

		if ( is_wp_error( $annullamento ) ) {
			return $annullamento;
		}

		// Un atto che arriva visibile ha bisogno della conferma sui dati personali, come ogni pubblicazione (ALBO-11); anche annullato, perche' il suo oggetto si vede.
		if ( $fine >= $oggi && true !== ( $dati['dettagli']['conferma_dati_personali'] ?? null ) ) {
			return new \WP_Error(
				'albo_importazione_conferma_mancante',
				__( 'L\'atto non si importa: e\' ancora in pubblicazione e arriverebbe visibile, ma manca la conferma sui dati personali.', 'albo-pretorio-pa' )
			);
		}

		$tabelle = self::tabelle_senza_transazioni( true );

		if ( array() !== $tabelle ) {
			return self::errore_tabelle( $tabelle );
		}

		$partenza = self::fotografia( $atto_id );

		if ( null === $partenza ) {
			return self::errore_lettura();
		}

		$prima       = self::voci_automatiche( $atto_id, 'pubblicazione' );
		$prima_fine  = self::voci_automatiche( $atto_id, 'modifica_fine_pubblicazione' );
		$prima_rimoz = null === $annullamento ? 0 : self::voci_automatiche( $atto_id, 'rimozione' );

		if ( is_wp_error( $prima ) ) {
			return $prima;
		}

		if ( is_wp_error( $prima_fine ) ) {
			return $prima_fine;
		}

		if ( is_wp_error( $prima_rimoz ) ) {
			return $prima_rimoz;
		}

		$campi   = array(
			'post_status'   => 'publish',
			'post_date'     => $inizio->format( 'Y-m-d H:i:s' ),
			'post_date_gmt' => $inizio->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);
		$partito = array(
			'anno'         => $anno,
			'numero'       => $numero,
			'dettagli'     => is_array( $dati['dettagli'] ?? null ) ? $dati['dettagli'] : array(),
			'annullamento' => null === $annullamento ? null : $annullamento + array( 'prima' => $prima_rimoz ),
		);

		// Il blocco dell'anno, tenuto fino a dopo la conferma: una dichiarazione della partenza non passa in mezzo.
		$blocco = Repertorio::blocca_anno( $anno );

		if ( is_wp_error( $blocco ) ) {
			return $blocco;
		}

		try {
			self::inizia( $atto_id, $campi, $fine, 'importazione' );

			try {
				$esito = self::importa_dentro( $atto_id, $campi, $fine, $partito, $partenza, $prima_fine, array( $fine ) !== self::righe_fine( $partenza ), $prima );
			} finally {
				unset( self::$in_corso[ $atto_id ] );
			}

			return self::dopo_la_chiusura( $atto_id, $esito );
		} finally {
			Repertorio::sblocca_anno( $anno );
		}
	}

	/**
	 * Le scritture dell'importazione di un atto, dentro la transazione.
	 *
	 * @param int                   $atto_id    Atto.
	 * @param array<string, string> $campi      Stato e date.
	 * @param string                $fine       Data di fine.
	 * @param array<string, mixed>  $numero     Anno, numero e dettagli per il registro.
	 * @param array<string, mixed>  $partenza   Fotografia di partenza.
	 * @param int                   $prima_fine Voci della fine prima del passaggio.
	 * @param bool                  $cambia     La data di fine cambia.
	 * @param int                   $prima      Voci della pubblicazione prima.
	 * @return array{anno: int, numero: int, inizio: string, fine: string, voce: int}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	private static function importa_dentro( int $atto_id, array $campi, string $fine, array $numero, array $partenza, int $prima_fine, bool $cambia, int $prima ) {
		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return $apertura;
		}

		try {
			$esito = self::blocca( $atto_id, $partenza );

			if ( ! is_wp_error( $esito ) ) {
				$esito = Repertorio::registra_importato( $atto_id, $numero['anno'], $numero['numero'] );
			}

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_stato( $atto_id, $campi );
			}

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
						'azione'    => self::AZIONE_IMPORTAZIONE,
						'contenuto' => $atto_id,
						'dettagli'  => array(
							'anno'   => $numero['anno'],
							'numero' => $numero['numero'],
							'inizio' => substr( $campi['post_date'], 0, 10 ),
							'fine'   => $fine,
						) + $numero['dettagli'] + DatiProcedimento::per_registro( $atto_id ),
						'chiave'    => SEZIONE . ':' . self::AZIONE_IMPORTAZIONE . ':' . $atto_id,
					)
				);
			}

			$attesi = $campi;

			if ( ! is_wp_error( $esito ) && null !== $numero['annullamento'] ) {
				$attesi = array( 'post_status' => ChiusuraPubblicazione::ANNULLATO ) + $campi;
				$voce   = self::annulla_importato( $atto_id, $numero, $fine );
				$esito  = is_wp_error( $voce ) ? $voce : $esito;
			}

			// Finite le scritture, prima del controllo e della conferma: niente piu' e' concesso.
			self::revoca_concessioni();

			if ( ! is_wp_error( $esito ) ) {
				$controllo = self::controlla_invariato( $atto_id, $partenza, $attesi, $fine );
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
			'inizio' => substr( $campi['post_date'], 0, 10 ),
			'fine'   => $fine,
			'voce'   => (int) $esito,
		);
	}

	/**
	 * L'annullamento avvenuto in Albo Pretorio On line, controllato: data e motivo, nulla se l'atto non era annullato.
	 *
	 * La data e' un giorno, non nel futuro. Il motivo vuoto si scrive come
	 * "non indicato": la voce dell'annullamento un motivo lo porta sempre.
	 *
	 * @param mixed  $annullamento `il` (AAAA-MM-GG) e `motivo`, oppure nulla.
	 * @param string $oggi         Il giorno di oggi nell'ora del sito.
	 * @return array{il: string, motivo: string}|null|\WP_Error
	 */
	private static function annullamento_importato( $annullamento, string $oggi ) {
		if ( null === $annullamento ) {
			return null;
		}

		$il     = is_array( $annullamento ) && is_string( $annullamento['il'] ?? null ) ? $annullamento['il'] : '';
		$giorno = \DateTimeImmutable::createFromFormat( '!Y-m-d', $il, wp_timezone() );
		$motivo = is_array( $annullamento ) && is_string( $annullamento['motivo'] ?? null ) ? trim( sanitize_textarea_field( $annullamento['motivo'] ) ) : null;

		if ( false === $giorno || $giorno->format( 'Y-m-d' ) !== $il || $il > $oggi || null === $motivo ) {
			return new \WP_Error(
				'albo_importazione_annullamento_non_valido',
				__( 'L\'atto non si importa: la data o il motivo dell\'annullamento in Albo Pretorio On line non sono validi.', 'albo-pretorio-pa' )
			);
		}

		return array(
			'il'     => $il,
			'motivo' => '' === $motivo ? __( 'non indicato in Albo Pretorio On line', 'albo-pretorio-pa' ) : $motivo,
		);
	}

	/**
	 * Porta ad annullato l'atto appena importato, nella stessa transazione: stato, voce automatica e voce con il motivo.
	 *
	 * L'atto annullato in Albo Pretorio On line non resta mai pubblicato con i
	 * suoi documenti: la conferma viene dopo queste scritture, o non viene.
	 * La concessione si rinnova per la sola scrittura dello stato, come
	 * nell'annullamento.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $numero  Anno, numero, dettagli e annullamento.
	 * @param string               $fine    Data di fine.
	 * @return int|\WP_Error
	 */
	private static function annulla_importato( int $atto_id, array $numero, string $fine ) {
		$campi = array( 'post_status' => ChiusuraPubblicazione::ANNULLATO );

		self::$in_corso[ $atto_id ] = array(
			'tipo'      => self::AZIONE_ANNULLAMENTO,
			'gettone'   => bin2hex( random_bytes( 16 ) ),
			'campi'     => $campi,
			'usato'     => false,
			'fine'      => null,
			'rifiuto'   => self::$in_corso[ $atto_id ]['rifiuto'],
			'importato' => true,
		);

		$esito = self::scrivi_stato( $atto_id, $campi );

		if ( ! is_wp_error( $esito ) ) {
			$esito = self::controlla_voce_automatica( $atto_id, 'rimozione', $numero['annullamento']['prima'] );
		}

		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		return self::scrivi_voce(
			array(
				'sezione'     => SEZIONE,
				'azione'      => self::AZIONE_ANNULLAMENTO,
				'contenuto'   => $atto_id,
				'motivazione' => $numero['annullamento']['motivo'],
				'dettagli'    => array(
					'anno'         => $numero['anno'],
					'numero'       => $numero['numero'],
					'fine'         => $fine,
					'annullato_il' => $numero['annullamento']['il'],
					'origine'      => self::ORIGINE_ANNULLAMENTO_IMPORTATO,
				),
				'chiave'      => SEZIONE . ':' . self::AZIONE_ANNULLAMENTO . ':' . $atto_id,
			)
		);
	}

	/**
	 * Rimanda in bozza un atto in verifica, con il suo motivo.
	 *
	 * @param int   $atto_id Atto.
	 * @param mixed $motivo  Motivo del rimando, testo non vuoto.
	 * @return int|\WP_Error Numero della voce di registro con il motivo, oppure errore.
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, o se un altro componente solleva un'eccezione dopo la conferma; con l'eccezione come causa.
	 */
	public static function rimanda_in_bozza( int $atto_id, $motivo ) {
		if ( self::occupato() ) {
			return self::errore_annidato();
		}

		$atto = self::atto_in_verifica( $atto_id, __( 'L\'atto non si rimanda in bozza: si rimanda soltanto un atto in verifica.', 'albo-pretorio-pa' ) );

		if ( is_wp_error( $atto ) ) {
			return $atto;
		}

		// L'atto come i controlli lo leggono: sotto il blocco della transazione dovra' essere ancora questo.
		$partenza = self::fotografia( $atto_id );

		if ( null === $partenza ) {
			return self::errore_lettura();
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

		$campi = array( 'post_status' => 'draft' );

		self::inizia( $atto_id, $campi, null, 'rimando' );

		try {
			$esito = self::rimanda_dentro( $atto_id, $campi, $motivo, $partenza, $prima );
		} finally {
			unset( self::$in_corso[ $atto_id ] );
		}

		return self::dopo_la_chiusura( $atto_id, $esito );
	}

	/**
	 * Annulla un atto pubblicato, con il suo motivo (ALBO-22).
	 *
	 * L'atto resta nel registro con il suo numero e le sue date, e da qui non
	 * cambia piu': annullato e' terminale. Fino alla data di fine il pubblico
	 * ne vede l'avviso, senza documenti; dopo, sparisce come ogni atto
	 * scaduto. Si annulla anche un atto gia' scaduto: la scadenza e' una
	 * proprieta' della data letta e non cambia il passaggio.
	 *
	 * @param int   $atto_id Atto.
	 * @param mixed $motivo  Motivo dell'annullamento, testo non vuoto.
	 * @return int|\WP_Error Numero della voce di registro con il motivo, oppure errore.
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento delle scritture non e' riuscito, o se un altro componente solleva un'eccezione dopo la conferma; con l'eccezione come causa.
	 */
	public static function annulla_atto( int $atto_id, $motivo ) {
		if ( self::occupato() ) {
			return self::errore_annidato();
		}

		self::svuota_memoria( $atto_id );

		$atto = get_post( $atto_id );

		if ( ! TipoAtto::tipo_nostro() || ! $atto instanceof \WP_Post || TIPO !== $atto->post_type ) {
			return new \WP_Error(
				'albo_non_un_atto',
				__( 'Il contenuto indicato non e\' un atto dell\'albo.', 'albo-pretorio-pa' )
			);
		}

		if ( ChiusuraPubblicazione::ANNULLATO === $atto->post_status ) {
			return new \WP_Error(
				'albo_atto_gia_annullato',
				__( 'L\'atto e\' gia\' annullato: annullato e\' terminale, e il motivo resta quello registrato.', 'albo-pretorio-pa' )
			);
		}

		if ( ChiusuraPubblicazione::PUBBLICATO !== $atto->post_status ) {
			return new \WP_Error(
				'albo_atto_non_pubblicato',
				__( 'L\'atto non si annulla: si annulla soltanto un atto pubblicato. Una bozza si corregge, un atto in verifica si rimanda in bozza.', 'albo-pretorio-pa' )
			);
		}

		$numero = Repertorio::di( $atto_id );

		if ( null === $numero ) {
			return new \WP_Error(
				'albo_atto_senza_numero',
				__( 'L\'atto non si annulla: non risulta un numero di registro, quindi non e\' stato pubblicato dal passaggio dell\'albo, oppure la numerazione non risponde in questo momento.', 'albo-pretorio-pa' )
			);
		}

		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || ! isset( $mappa[ Permessi::CHIAVE_DEFISSIONE ] ) || ! current_user_can( $mappa[ Permessi::CHIAVE_DEFISSIONE ] ) ) {
			return new \WP_Error(
				'albo_annullamento_non_permesso',
				__( 'L\'atto resta pubblicato: per annullarlo serve il permesso di annullare e defiggere gli atti, e non si possiede.', 'albo-pretorio-pa' )
			);
		}

		$motivo = is_string( $motivo ) ? trim( sanitize_textarea_field( $motivo ) ) : '';

		if ( '' === $motivo ) {
			return new \WP_Error(
				'albo_motivo_mancante',
				__( 'L\'atto resta pubblicato: per annullarlo serve il motivo, che finisce nel registro delle modifiche e che il pubblico legge accanto all\'atto fino alla sua data di fine.', 'albo-pretorio-pa' )
			);
		}

		// L'atto come i controlli lo leggono: sotto il blocco della transazione dovra' essere ancora questo.
		$partenza = self::fotografia( $atto_id );

		if ( null === $partenza ) {
			return self::errore_annullamento( self::errore_lettura() );
		}

		$tabelle = self::tabelle_senza_transazioni();

		if ( array() !== $tabelle ) {
			return self::errore_annullamento( self::errore_tabelle( $tabelle ) );
		}

		$prima = self::voci_automatiche( $atto_id, 'rimozione' );

		if ( is_wp_error( $prima ) ) {
			return self::errore_annullamento( $prima );
		}

		$fine  = conformita_core_fine_pubblicazione( $atto_id );
		$campi = array( 'post_status' => ChiusuraPubblicazione::ANNULLATO );
		$voce  = array(
			'sezione'     => SEZIONE,
			'azione'      => self::AZIONE_ANNULLAMENTO,
			'contenuto'   => $atto_id,
			'motivazione' => $motivo,
			'dettagli'    => array(
				'anno'   => (int) $numero['anno'],
				'numero' => (int) $numero['numero'],
				'fine'   => is_string( $fine ) ? $fine : null,
			),
			'chiave'      => SEZIONE . ':' . self::AZIONE_ANNULLAMENTO . ':' . $atto_id,
		);

		self::inizia( $atto_id, $campi, null, self::AZIONE_ANNULLAMENTO );

		try {
			$esito = self::annulla_dentro( $atto_id, $campi, $voce, $partenza, $prima );
		} finally {
			unset( self::$in_corso[ $atto_id ] );
		}

		return self::dopo_la_chiusura( $atto_id, is_wp_error( $esito ) ? self::errore_annullamento( $esito ) : $esito );
	}

	/**
	 * La voce dell'annullamento di un atto: motivo e istante, o nulla se il registro non la da'.
	 *
	 * Il motivo che il pubblico legge viene dal registro e non da un dato
	 * dell'atto: il registro e' solo in aggiunta, e una seconda copia
	 * sull'atto sarebbe un dato in piu' da difendere.
	 *
	 * Per l'atto annullato in Albo Pretorio On line e importato, la data e'
	 * quella scritta la', che ha solo il giorno: l'istante e' la sua
	 * mezzanotte nell'ora del sito, e `giorno` lo dice.
	 *
	 * @param int $atto_id Atto.
	 * @return array{motivo: string, istante: \DateTimeImmutable, giorno: bool}|null
	 */
	public static function annullamento( int $atto_id ): ?array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $atto_id,
				'azione'    => self::AZIONE_ANNULLAMENTO,
			)
		);

		if ( ! is_array( $voci ) || 1 !== count( $voci ) || ! isset( $voci[0]['motivazione'], $voci[0]['istante'] ) || ! $voci[0]['istante'] instanceof \DateTimeImmutable ) {
			return null;
		}

		$giorno = self::giorno_importato( $atto_id, $voci[0] );

		if ( null !== $giorno ) {
			return array(
				'motivo'  => (string) $voci[0]['motivazione'],
				'istante' => $giorno,
				'giorno'  => true,
			);
		}

		return array(
			'motivo'  => (string) $voci[0]['motivazione'],
			'istante' => $voci[0]['istante'],
			'giorno'  => false,
		);
	}

	/**
	 * Il giorno dell'annullamento in Albo Pretorio On line, se la voce e' quella scritta dall'importazione.
	 *
	 * Il dettaglio vale solo se la voce ne dichiara l'origine, se l'atto ha la
	 * sua voce di importazione con la stessa data dell'annullamento, e se il
	 * giorno non viene dopo la voce: un'altra voce con una data nei dettagli
	 * si legge con il suo istante.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $voce    Voce dell'annullamento.
	 * @return \DateTimeImmutable|null
	 */
	private static function giorno_importato( int $atto_id, array $voce ): ?\DateTimeImmutable {
		$il = $voce['dettagli']['annullato_il'] ?? null;

		if ( ! is_string( $il ) || self::ORIGINE_ANNULLAMENTO_IMPORTATO !== ( $voce['dettagli']['origine'] ?? null ) ) {
			return null;
		}

		$giorno = \DateTimeImmutable::createFromFormat( '!Y-m-d', $il, wp_timezone() );

		if ( false === $giorno || $giorno->format( 'Y-m-d' ) !== $il || $giorno > $voce['istante'] ) {
			return null;
		}

		$importazione = conformita_core_voci_registro(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $atto_id,
				'azione'    => self::AZIONE_IMPORTAZIONE,
			)
		);

		if ( ! is_array( $importazione ) || 1 !== count( $importazione ) || $il !== ( $importazione[0]['dettagli']['annullamento'] ?? null ) ) {
			return null;
		}

		return $giorno;
	}

	/**
	 * Le scritture dell'annullamento, dentro la transazione.
	 *
	 * @param int                   $atto_id  Atto.
	 * @param array<string, string> $campi    Stato.
	 * @param array<string, mixed>  $voce     Voce del registro con il motivo.
	 * @param array<string, mixed>  $partenza Fotografia di partenza.
	 * @param int                   $prima    Voci della rimozione prima.
	 * @return int|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento delle scritture non e' riuscito, con l'eccezione come causa.
	 */
	private static function annulla_dentro( int $atto_id, array $campi, array $voce, array $partenza, int $prima ) {
		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return $apertura;
		}

		try {
			$esito = self::blocca( $atto_id, $partenza );

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_stato( $atto_id, $campi );
			}

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::controlla_voce_automatica( $atto_id, 'rimozione', $prima );
			}

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_voce( $voce );
			}

			// Finite le scritture, prima del controllo e della conferma: niente piu' e' concesso.
			self::revoca_concessioni();

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
	 * Il rifiuto di un annullamento non riuscito, con il motivo vero fra gli altri.
	 *
	 * Le difese comuni ai passaggi parlano di un atto in verifica, da cui
	 * partono gli altri passaggi: qui il primo messaggio dice che l'atto e'
	 * rimasto pubblicato, e il motivo vero resta leggibile con il suo codice.
	 * L'annullamento delle scritture che non e' riuscito resta il primo: lo
	 * stato dell'atto non e' garantito.
	 *
	 * @param \WP_Error $motivo Motivo.
	 * @return \WP_Error
	 */
	private static function errore_annullamento( \WP_Error $motivo ): \WP_Error {
		if ( 'albo_annullamento_non_riuscito' === $motivo->get_error_code() ) {
			return $motivo;
		}

		$errore = new \WP_Error(
			'albo_atto_non_annullato',
			__( 'L\'atto resta pubblicato e com\'era: una delle scritture dell\'annullamento non e\' riuscita, e tutto e\' tornato come prima.', 'albo-pretorio-pa' )
		);

		foreach ( $motivo->get_error_codes() as $codice ) {
			foreach ( $motivo->get_error_messages( $codice ) as $messaggio ) {
				$errore->add( $codice, $messaggio );
			}
		}

		return $errore;
	}

	/**
	 * Le chiavi che una richiesta di proroga puo' portare.
	 *
	 * @var array<int, string>
	 */
	const CHIAVI_PROROGA = array( 'giorni', 'attestazione', 'attese' );

	/**
	 * Quanti secondi una proroga aspetta che finisca quella in corso in un'altra richiesta.
	 *
	 * @var int
	 */
	const ATTESA_TURNO = 5;

	/**
	 * Gli atti che una proroga toccherebbe adesso, senza scrivere niente.
	 *
	 * Fa gli stessi controlli della proroga, tranne la scrittura: e' quello
	 * che la schermata mostra prima della conferma.
	 *
	 * @param array<string, mixed> $richiesta `giorni` e `attestazione`.
	 * @return array{giorni: array<int, string>, atti: array<int, array<string, mixed>>, attese: array<int, string>}|\WP_Error
	 */
	public static function anteprima_proroga( array $richiesta ) {
		$controllo = self::controlla_proroga( $richiesta );

		if ( is_wp_error( $controllo ) ) {
			return $controllo;
		}

		$atti = Proroga::atti_toccati( $controllo['giorni'], self::ora() );

		if ( is_wp_error( $atti ) ) {
			return $atti;
		}

		return array(
			'giorni' => $controllo['giorni'],
			'atti'   => $atti,
			'attese' => self::attese( $atti ),
		);
	}

	/**
	 * Dispone la proroga per disservizio (ALBO-31): tutti gli atti toccati o nessuno.
	 *
	 * @param array<string, mixed> $richiesta `giorni`, elenco di date AAAA-MM-GG gia' trascorse; `attestazione`, testo non vuoto; `attese`, facoltativo, il risultato dell'anteprima che chi dispone ha visto, come restituito da `anteprima_proroga()`.
	 * @return array{voce: int, giorni: array<int, string>, atti: array<int, array<string, mixed>>}|\WP_Error
	 * @throws \RuntimeException Se dopo un'eccezione l'annullamento non e' riuscito, se un altro componente solleva un'eccezione dopo la conferma, o durante il rilascio del turno; con l'eccezione come causa. Un'eccezione sollevata durante le scritture esce invece com'e', dopo averle annullate.
	 */
	public static function proroga( array $richiesta ) {
		if ( self::occupato() ) {
			return self::errore_annidato();
		}

		$controllo = self::controlla_proroga( $richiesta );

		if ( is_wp_error( $controllo ) ) {
			return $controllo;
		}

		$tabelle = self::tabelle_senza_transazioni();

		if ( array() !== $tabelle ) {
			return self::errore_tabelle( $tabelle );
		}

		/*
		 * Il fermo dell'operazione vale da qui alla fine, anche quando la
		 * proroga non tocca nessun atto: nessun altro passaggio comincia
		 * mentre la sua transazione e' aperta.
		 */
		self::$operazione = true;
		$rilascio         = true;

		try {
			$turno = null;

			try {
				$turno = self::prendi_turno();
			} finally {
				/*
				 * Il recupero di una presa rimasta passa da un rilascio, dove un
				 * componente agganciato puo' lasciare in memoria dati che la
				 * banca dati non ha: se il turno non e' preso, per un rifiuto o
				 * un'eccezione, la memoria si svuota prima di uscire.
				 */
				if ( true !== $turno ) {
					wp_cache_flush();
				}
			}

			if ( is_wp_error( $turno ) ) {
				return $turno;
			}

			try {
				$esito = self::proroga_dentro( $controllo, self::ora() );
			} finally {
				/*
				 * Le concessioni sugli atti finiscono con la transazione, prima
				 * del rilascio: un aggancio che scrive durante il rilascio non ha
				 * piu' niente da usare. Il fermo dell'operazione resta fino alla
				 * fine, contro i passaggi annidati.
				 */
				self::$in_corso = array();

				// Anche dopo un'eccezione: il turno non resta preso da una proroga finita.
				$rilascio = self::lascia_turno();

				/*
				 * Un componente agganciato al rilascio, riuscito o no, puo' aver
				 * lasciato in memoria dati che la banca dati non ha, su ogni
				 * strada: confermata, annullata o interrotta da un'eccezione. La
				 * memoria si svuota per intero, che non fa girare agganci, prima
				 * di rispondere o di rilanciare.
				 */
				wp_cache_flush();
			}
		} finally {
			self::$in_corso   = array();
			self::$operazione = false;
		}

		if ( is_wp_error( $esito ) ) {
			if ( true !== $rilascio ) {
				$esito->add( 'albo_proroga_turno_non_lasciato', self::messaggio_turno_non_lasciato( false ) );
			}

			return $esito;
		}

		// La memoria degli atti prorogati si svuota prima di dire qualunque cosa sul turno.
		foreach ( array_keys( $esito['atti'] ) as $atto_id ) {
			self::dopo_la_chiusura( $atto_id, $esito );
		}

		if ( $rilascio instanceof \Throwable ) {
			throw new \RuntimeException( esc_html( self::messaggio_turno_non_lasciato( true ) ), 0, $rilascio ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- gia' sfuggito.
		}

		if ( true !== $rilascio ) {
			return new \WP_Error( 'albo_proroga_turno_non_lasciato', self::messaggio_turno_non_lasciato( true ), $esito );
		}

		return $esito;
	}

	/**
	 * Il messaggio del turno non lasciato, che dice se la proroga e' confermata o no.
	 *
	 * @param bool $confermata Se la proroga e' confermata nella banca dati.
	 * @return string
	 */
	private static function messaggio_turno_non_lasciato( bool $confermata ): string {
		$turno = __( 'La banca dati non ha confermato di aver lasciato il turno delle proroghe: finche\' questa connessione resta aperta, le altre proroghe sono rifiutate come se una fosse in corso. Il turno si libera da solo alla chiusura della connessione, o alla prossima proroga chiesta su questa stessa connessione.', 'albo-pretorio-pa' );

		return $confermata
			? __( 'La proroga e\' disposta e confermata nella banca dati: le date di fine e il registro sono gia\' scritti, e non va ripetuta.', 'albo-pretorio-pa' ) . ' ' . $turno
			: $turno;
	}

	/**
	 * Se un passaggio o una proroga sono in corso in questa richiesta.
	 *
	 * @return bool
	 */
	private static function occupato(): bool {
		return array() !== self::$in_corso || self::$operazione;
	}

	/**
	 * Il nome del turno delle proroghe nella banca dati, uno per sito.
	 *
	 * @return string
	 */
	public static function nome_turno(): string {
		global $wpdb;

		return $wpdb->prefix . 'albo_pretorio_proroga';
	}

	/**
	 * Prende il turno delle proroghe: una alla volta, fra tutte le richieste.
	 *
	 * Il calcolo legge le fini e il registro: se due proroghe calcolassero
	 * insieme, la seconda scriverebbe una fine calcolata su quella che la
	 * prima sta cambiando. Il turno si prende prima di aprire la transazione,
	 * cosi' che le letture dentro la transazione vedano quello che la proroga
	 * precedente ha confermato.
	 *
	 * @return true|\WP_Error
	 */
	private static function prendi_turno() {
		global $wpdb;

		/*
		 * Il turno e' rientrante: se questa connessione lo tiene ancora da una
		 * proroga finita il cui rilascio non e' riuscito, prenderlo di nuovo ne
		 * aggiungerebbe un'altra presa, e il rilascio di questa proroga non lo
		 * libererebbe. Quella proroga e' finita, e nessun'altra e' in corso in
		 * questa richiesta: la presa rimasta si lascia prima, e se non si lascia
		 * la proroga non comincia.
		 */
		$volte = 0;

		while ( true === self::turno_mio() && $volte < 10 ) {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::nome_turno() ) );
			++$volte;
		}

		if ( false !== self::turno_mio() ) {
			return new \WP_Error(
				'albo_proroga_turno_bloccato',
				__( 'Nessuna proroga disposta: questa connessione alla banca dati tiene ancora il turno delle proroghe da una proroga precedente, e non lo lascia. Riprovare da una nuova richiesta.', 'albo-pretorio-pa' )
			);
		}

		$preso = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', self::nome_turno(), self::ATTESA_TURNO ) );

		if ( '1' !== (string) $preso ) {
			return new \WP_Error(
				'albo_proroga_occupata',
				__( 'Nessuna proroga disposta: un\'altra proroga e\' in corso in questo momento. Riprovare fra poco, ripartendo dall\'anteprima.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Lascia il turno delle proroghe, e dice se la banca dati lo ha lasciato davvero.
	 *
	 * Un'eccezione sollevata da un altro componente durante il rilascio non
	 * esce da qui: chi chiama deve prima svuotare la memoria degli atti
	 * prorogati, e poi riferirla.
	 *
	 * @return true|false|\Throwable Vero se lasciato; falso se la banca dati non lo conferma; l'eccezione, se una e' stata sollevata.
	 */
	private static function lascia_turno() {
		global $wpdb;

		try {
			$righe = Lettura::righe( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s ) AS lasciato', self::nome_turno() ), array( 'lasciato' ) );
		} catch ( \Throwable $errore ) {
			return $errore;
		}

		if ( null === $righe || 1 !== count( $righe ) || '1' !== (string) $righe[0]['lasciato'] ) {
			return false;
		}

		// Una presa lasciata non basta: il turno e' libero solo se questa connessione non ne tiene altre.
		try {
			return false === self::turno_mio();
		} catch ( \Throwable $errore ) {
			return $errore;
		}
	}

	/**
	 * Se questa connessione tiene il turno delle proroghe.
	 *
	 * @return bool|null Nulla se la banca dati non risponde.
	 */
	private static function turno_mio(): ?bool {
		global $wpdb;

		$righe = Lettura::righe( $wpdb->prepare( 'SELECT COALESCE( IS_USED_LOCK( %s ) = CONNECTION_ID(), 0 ) AS mio', self::nome_turno() ), array( 'mio' ) );

		if ( null === $righe || 1 !== count( $righe ) || ! in_array( (string) $righe[0]['mio'], array( '0', '1' ), true ) ) {
			return null;
		}

		return '1' === (string) $righe[0]['mio'];
	}

	/**
	 * I controlli della proroga che non scrivono niente: permesso, attestazione, giorni.
	 *
	 * @param array<string, mixed> $richiesta Richiesta.
	 * @return array{giorni: array<int, string>, attestazione: string}|\WP_Error
	 */
	private static function controlla_proroga( array $richiesta ) {
		$sconosciute = array_diff( array_map( 'strval', array_keys( $richiesta ) ), self::CHIAVI_PROROGA );

		if ( array() !== $sconosciute ) {
			return new \WP_Error(
				'albo_richiesta_non_valida',
				sprintf(
					/* translators: %s: elenco delle chiavi non ammesse. */
					__( 'Nessuna proroga disposta: la richiesta porta dati che la proroga non accetta (%s). Le date di fine le calcola il sistema.', 'albo-pretorio-pa' ),
					implode( ', ', $sconosciute )
				)
			);
		}

		$mappa = Permessi::mappa();

		if ( is_wp_error( $mappa ) || empty( $mappa['publish_posts'] ) || ! current_user_can( $mappa['publish_posts'] ) ) {
			return new \WP_Error(
				'albo_proroga_non_permessa',
				__( 'Nessuna proroga disposta: la dispone chi puo\' pubblicare gli atti, e non si possiede quel permesso.', 'albo-pretorio-pa' )
			);
		}

		if ( null === Festivi::scelta() ) {
			return Festivi::errore_non_scelta( __( 'Nessuna proroga disposta:', 'albo-pretorio-pa' ) );
		}

		$attestazione = isset( $richiesta['attestazione'] ) && is_string( $richiesta['attestazione'] ) ? trim( sanitize_textarea_field( $richiesta['attestazione'] ) ) : '';

		if ( '' === $attestazione ) {
			return new \WP_Error(
				'albo_proroga_senza_attestazione',
				__( 'Nessuna proroga disposta: serve l\'attestazione del disservizio, per esempio gli estremi del documento protocollato che lo attesta. Finisce nel registro delle modifiche.', 'albo-pretorio-pa' )
			);
		}

		$giorni = Proroga::giorni( $richiesta['giorni'] ?? null, self::ora() );

		if ( is_wp_error( $giorni ) ) {
			return $giorni;
		}

		$attestati = Proroga::giorni_attestati();

		if ( null === $attestati ) {
			return new \WP_Error(
				'albo_registro_non_leggibile',
				__( 'Nessuna proroga disposta: il registro delle modifiche non si legge, e senza non si sa quali giorni sono gia\' stati attestati.', 'albo-pretorio-pa' )
			);
		}

		$ripetuti = array_values( array_intersect( $giorni, $attestati ) );

		if ( array() !== $ripetuti ) {
			return self::errore_giorni_ripetuti( $ripetuti );
		}

		$attese = $richiesta['attese'] ?? null;

		if ( null !== $attese && ( ! is_array( $attese ) || array() !== array_filter( $attese, static fn( $valore ): bool => ! is_string( $valore ) ) ) ) {
			return new \WP_Error(
				'albo_richiesta_non_valida',
				__( 'Nessuna proroga disposta: il risultato atteso deve essere un elenco di atti con la loro fine nuova.', 'albo-pretorio-pa' )
			);
		}

		return array(
			'giorni'       => $giorni,
			'attestazione' => $attestazione,
			'attese'       => $attese,
		);
	}

	/**
	 * Il risultato atteso, nella forma con cui si confronta: atto, fine attuale e fine nuova.
	 *
	 * @param array<int, array<string, mixed>> $atti Atti toccati.
	 * @return array<int, string>
	 */
	public static function attese( array $atti ): array {
		$attese = array();

		foreach ( $atti as $atto_id => $atto ) {
			$attese[ (int) $atto_id ] = $atto['fine'] . '>' . $atto['nuova'];
		}

		ksort( $attese );

		return $attese;
	}

	/**
	 * Il calcolo coincide con il risultato che chi dispone ha visto.
	 *
	 * @param array<int, array<string, mixed>> $atti   Atti toccati, calcolati ora.
	 * @param array<int|string, string>|null   $attese Risultato atteso, o nulla se la richiesta non lo porta.
	 * @return true|\WP_Error
	 */
	private static function confronta_attese( array $atti, ?array $attese ) {
		if ( null === $attese ) {
			return true;
		}

		$normali = array();

		foreach ( $attese as $atto_id => $valore ) {
			$normali[ (int) $atto_id ] = $valore;
		}

		ksort( $normali );

		if ( self::attese( $atti ) !== $normali ) {
			return new \WP_Error(
				'albo_proroga_anteprima_superata',
				__( 'Nessuna proroga disposta: da quando e\' stata mostrata l\'anteprima gli atti da prorogare o le loro date sono cambiati. Ripetere l\'anteprima e controllarla prima di confermare.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Il rifiuto di una proroga che ripete giorni gia' attestati.
	 *
	 * @param array<int, string> $ripetuti Giorni.
	 * @return \WP_Error
	 */
	private static function errore_giorni_ripetuti( array $ripetuti ): \WP_Error {
		return new \WP_Error(
			'albo_proroga_giorno_gia_attestato',
			sprintf(
				/* translators: %s: elenco dei giorni. */
				__( 'Nessuna proroga disposta: questi giorni sono gia\' stati attestati in una proroga precedente, e un giorno non si conta due volte (%s).', 'albo-pretorio-pa' ),
				implode( ', ', array_map( array( Proroga::class, 'leggibile' ), $ripetuti ) )
			)
		);
	}

	/**
	 * Il calcolo e le scritture della proroga, dentro la transazione.
	 *
	 * Gli atti toccati si calcolano qui, a turno preso e transazione aperta,
	 * dalla banca dati: nessuna proroga confermata nel frattempo sfugge al
	 * calcolo. Se la richiesta porta il risultato che chi la dispone ha visto
	 * nell'anteprima e il calcolo non coincide, niente si scrive.
	 *
	 * @param array{giorni: array<int, string>, attestazione: string, attese: array<int, string>|null} $controllo Giorni, attestazione e risultato atteso.
	 * @param \DateTimeImmutable                                                                       $adesso    Istante della proroga.
	 * @return array{voce: int, giorni: array<int, string>, atti: array<int, array<string, mixed>>}|\WP_Error
	 * @throws \Throwable L'eccezione sollevata durante le scritture, dopo averle annullate.
	 * @throws \RuntimeException Se dopo l'eccezione l'annullamento non e' riuscito, con l'eccezione come causa.
	 */
	private static function proroga_dentro( array $controllo, \DateTimeImmutable $adesso ) {
		$apertura = self::apri();

		if ( is_wp_error( $apertura ) ) {
			return self::errore_proroga( $apertura );
		}

		$primo  = 0;
		$atti   = array();
		$giorno = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		try {
			$esito = self::lettura_aggiornata();

			if ( ! is_wp_error( $esito ) ) {
				$esito = Proroga::atti_toccati( $controllo['giorni'], $adesso );
			}

			if ( ! is_wp_error( $esito ) ) {
				$atti  = $esito;
				$primo = array() === $atti ? 0 : (int) array_key_first( $atti );
				$esito = self::confronta_attese( $atti, $controllo['attese'] );
			}

			$partenze = array();
			$prime    = array();

			foreach ( $atti as $atto_id => $atto ) {
				if ( is_wp_error( $esito ) ) {
					break;
				}

				$partenza = self::fotografia( $atto_id );
				$prima    = self::voci_automatiche( $atto_id, 'modifica_fine_pubblicazione' );

				if ( null === $partenza ) {
					$esito = self::errore_lettura();
				} elseif ( is_wp_error( $prima ) ) {
					$esito = $prima;
				} else {
					$partenze[ $atto_id ] = $partenza;
					$prime[ $atto_id ]    = $prima;
					$esito                = self::blocca( $atto_id, $partenza );

					self::inizia( $atto_id, array(), $atto['nuova'], 'proroga' );
				}
			}

			foreach ( $controllo['giorni'] as $attestato ) {
				if ( is_wp_error( $esito ) ) {
					break;
				}

				// La chiave unica: se un'altra richiesta ha attestato lo stesso giorno nel frattempo, lo dice la banca dati.
				$esito = conformita_core_registra_voce(
					array(
						'sezione'     => SEZIONE,
						'azione'      => Proroga::AZIONE_GIORNO,
						'motivazione' => $controllo['attestazione'],
						'dettagli'    => array( 'giorno' => $attestato ),
						'chiave'      => Proroga::chiave_giorno( $attestato ),
					)
				);

				if ( is_wp_error( $esito ) && 'conformita_core_registro_chiave_esistente' === $esito->get_error_code() ) {
					$esito = self::errore_giorni_ripetuti( array( $attestato ) );
				} elseif ( is_wp_error( $esito ) ) {
					$esito = new \WP_Error( 'albo_voce_non_scritta', $esito->get_error_message() );
				}
			}

			$voce = 0;

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_voce(
					array(
						'sezione'     => SEZIONE,
						'azione'      => Proroga::AZIONE_PROROGA,
						'motivazione' => $controllo['attestazione'],
						'dettagli'    => array(
							'giorni'         => implode( ',', $controllo['giorni'] ),
							'atti'           => implode( ',', array_keys( $atti ) ),
							'giorno_proroga' => $giorno,
						),
					)
				);
				$voce  = is_wp_error( $esito ) ? 0 : (int) $esito;
			}

			foreach ( $atti as $atto_id => $atto ) {
				if ( is_wp_error( $esito ) ) {
					break;
				}

				$esito = conformita_core_imposta_fine_pubblicazione( $atto_id, $atto['nuova'] );

				if ( ! is_wp_error( $esito ) ) {
					$esito = self::controlla_voce_della_fine( $atto_id, $prime[ $atto_id ], true );
				}

				if ( ! is_wp_error( $esito ) ) {
					$esito = self::scrivi_voce(
						array(
							'sezione'     => SEZIONE,
							'azione'      => Proroga::AZIONE_ATTO,
							'contenuto'   => $atto_id,
							'motivazione' => $controllo['attestazione'],
							'dettagli'    => array(
								'fine_precedente' => $atto['fine'],
								'fine_nuova'      => $atto['nuova'],
								'giorni_contati'  => $atto['giorni'],
								'giorno_proroga'  => $giorno,
								'proroga'         => $voce,
								'fine_calcolata'  => $atto['calcolata'],
								'slittamento'     => $atto['slittamento'],
								'festivi_saltati' => self::saltati_per_registro( $atto['saltati'] ),
							),
						)
					);
				}
			}

			// Finite le scritture, prima dei controlli e della conferma: niente piu' e' concesso.
			self::revoca_concessioni();

			foreach ( $atti as $atto_id => $atto ) {
				if ( is_wp_error( $esito ) ) {
					break;
				}

				$esito = self::controlla_invariato( $atto_id, $partenze[ $atto_id ], array(), $atto['nuova'] );
			}

			if ( is_wp_error( $esito ) ) {
				$annullato = self::annulla( $apertura, $primo );

				return is_wp_error( $annullato ) ? $annullato : self::errore_proroga( $esito );
			}

			$chiusura = self::chiudi( $apertura, $primo );

			if ( is_wp_error( $chiusura ) ) {
				return 'albo_annullamento_non_riuscito' === $chiusura->get_error_code() ? $chiusura : self::errore_proroga( $chiusura );
			}
		} catch ( \Throwable $errore ) {
			$annullato = self::annulla( $apertura, $primo );

			if ( is_wp_error( $annullato ) ) {
				throw new \RuntimeException( esc_html( $annullato->get_error_message() ), 0, $errore ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- gia' sfuggito.
			}

			throw $errore;
		}

		return array(
			'voce'   => $voce,
			'giorni' => $controllo['giorni'],
			'atti'   => $atti,
		);
	}

	/**
	 * Le letture del calcolo vedono l'ultima versione confermata dei dati.
	 *
	 * Prima di qualunque altra lettura della proroga, gli atti, le loro date di
	 * fine e le voci di registro dell'albo si leggono bloccandoli: una lettura
	 * che blocca aspetta le scritture non ancora confermate di altre richieste
	 * e vede l'ultima versione confermata. Poi si rileggono senza bloccare,
	 * come li leggono il calcolo e il meccanismo comune.
	 *
	 * In una transazione aperta qui le due letture coincidono, perche' la
	 * lettura coerente della transazione nasce con la seconda. Dentro la
	 * transazione di un altro programma, invece, la lettura coerente puo' essere
	 * nata prima che un'altra richiesta confermasse una proroga: allora le due
	 * letture differiscono, e la proroga si rifiuta invece di calcolare su dati
	 * vecchi. I blocchi restano fino alla chiusura della transazione di chi
	 * chiama, anche dopo che il turno e' stato lasciato: la proroga successiva
	 * li aspetta con le stesse letture.
	 *
	 * @return true|\WP_Error
	 */
	private static function lettura_aggiornata() {
		global $wpdb;

		$letture  = array(
			array(
				$wpdb->prepare( 'SELECT ID, post_status, post_date, post_modified_gmt FROM %i WHERE post_type = %s ORDER BY ID', $wpdb->posts, TIPO ),
				array( 'ID', 'post_status', 'post_date', 'post_modified_gmt' ),
			),
			array(
				$wpdb->prepare( 'SELECT meta_id, post_id, meta_value FROM %i WHERE meta_key = %s ORDER BY meta_id', $wpdb->postmeta, conformita_core_chiave_fine_pubblicazione() ),
				array( 'meta_id', 'post_id', 'meta_value' ),
			),
			array(
				$wpdb->prepare( 'SELECT id FROM %i WHERE sezione = %s ORDER BY id', $wpdb->prefix . 'conformita_core_registro', SEZIONE ),
				array( 'id' ),
			),
		);
		$bloccate = array();
		$semplici = array();

		foreach ( $letture as $lettura ) {
			$bloccate[] = Lettura::righe( $lettura[0] . ' FOR UPDATE', $lettura[1] );
		}

		foreach ( $letture as $lettura ) {
			$semplici[] = Lettura::righe( $lettura[0], $lettura[1] );
		}

		if ( in_array( null, $bloccate, true ) || in_array( null, $semplici, true ) ) {
			return self::errore_lettura();
		}

		if ( $bloccate !== $semplici ) {
			return new \WP_Error(
				'albo_proroga_lettura_superata',
				__( 'Nessuna proroga disposta: la proroga e\' stata chiamata dentro una transazione gia\' aperta da un altro programma, che vede i dati come erano prima di modifiche confermate nel frattempo da altre richieste. Calcolare su quei dati darebbe date sbagliate: la proroga va ripetuta fuori da quella transazione.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Il rifiuto di una proroga fallita a meta' e annullata, con il motivo di partenza in coda.
	 *
	 * I messaggi dei pezzi comuni ai passaggi parlano dell'atto in verifica:
	 * qui il primo messaggio dice che cosa e' successo alla proroga, e il
	 * motivo vero resta leggibile, con il suo codice, fra gli altri.
	 *
	 * @param \WP_Error $motivo Motivo.
	 * @return \WP_Error
	 */
	private static function errore_proroga( \WP_Error $motivo ): \WP_Error {
		// Rifiuti decisi prima di scrivere: il loro messaggio dice gia' tutto.
		if ( in_array( $motivo->get_error_code(), array( 'albo_proroga_giorno_gia_attestato', 'albo_proroga_anteprima_superata', 'albo_proroga_lettura_superata', 'albo_festivi_non_scelti' ), true ) ) {
			return $motivo;
		}

		$errore = new \WP_Error(
			'albo_proroga_non_riuscita',
			__( 'Nessuna proroga disposta e nessun atto cambiato: una delle scritture non e\' riuscita e tutto e\' stato annullato.', 'albo-pretorio-pa' )
		);

		foreach ( $motivo->get_error_codes() as $codice ) {
			foreach ( $motivo->get_error_messages( $codice ) as $messaggio ) {
				$errore->add( $codice, $messaggio );
			}
		}

		return $errore;
	}

	/**
	 * Dopo un passaggio riuscito e chiuso: svuota la memoria dell'atto.
	 *
	 * **Fuori dal passaggio, non dentro.** Lo svuotamento della memoria fa
	 * girare gli agganci di WordPress, cioe' codice di altri componenti. Qui
	 * la transazione e' gia' confermata e il passaggio non e' piu' in corso:
	 * l'atto e' fermo come ogni atto pubblicato o in bozza, e una scrittura
	 * da quegli agganci trova il guardiano senza nessuna concessione. Se un
	 * aggancio solleva un'eccezione, non c'e' piu' niente da annullare: la
	 * memoria, svuotata a meta', si svuota per intero, e l'eccezione si
	 * rilancia dicendo che il passaggio e' confermato, con quella di partenza
	 * come causa.
	 *
	 * @param int   $atto_id Atto.
	 * @param mixed $esito   Esito del passaggio.
	 * @return mixed L'esito, invariato.
	 * @throws \RuntimeException Se un aggancio solleva un'eccezione dopo la conferma.
	 */
	private static function dopo_la_chiusura( int $atto_id, $esito ) {
		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		try {
			self::svuota_memoria( $atto_id );
		} catch ( \Throwable $errore ) {
			// Lo svuotamento e' rimasto a meta': quello intero non fa girare agganci.
			wp_cache_flush();

			throw new \RuntimeException(
				esc_html(
					sprintf(
						/* translators: %d: identificativo dell'atto. */
						__( 'Il passaggio sull\'atto %d e\' confermato nella banca dati; un altro componente ha sollevato un\'eccezione subito dopo, durante lo svuotamento della memoria.', 'albo-pretorio-pa' ),
						$atto_id
					)
				),
				0,
				$errore // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- causa, non testo.
			);
		}

		return $esito;
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

		/*
		 * Dalla transazione aperta in poi tutto sta dentro la stessa difesa,
		 * blocco compreso: un rifiuto o un'eccezione mentre la riga si blocca
		 * e si rilegge annullano come quelli delle scritture.
		 */
		try {
			$esito = self::blocca( $atto_id, $partenza );

			if ( ! is_wp_error( $esito ) ) {
				$esito = self::scrivi_stato( $atto_id, $campi );
			}

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
	 * I festivi saltati nella forma che il registro accetta: un elenco di testi.
	 *
	 * @param array<int, array{giorno: string, descrizione: string}> $saltati Festivi saltati.
	 * @return array<int, string> Un testo per giorno, `AAAA-MM-GG (descrizione)`.
	 */
	private static function saltati_per_registro( array $saltati ): array {
		return array_map(
			static fn( array $saltato ): string => $saltato['giorno'] . ' (' . $saltato['descrizione'] . ')',
			$saltati
		);
	}

	/**
	 * La fine dopo lo slittamento ai festivi (ALBO-32), o il motivo per cui non si calcola.
	 *
	 * @param string $calcolata Fine calcolata, AAAA-MM-GG.
	 * @param string $prefisso  Frase di apertura del rifiuto.
	 * @return array{fine: string, calcolata: string, slittamento: string, saltati: array<int, array{giorno: string, descrizione: string}>}|\WP_Error
	 */
	public static function fine_slittata( string $calcolata, string $prefisso ) {
		$impostazione = Festivi::impostazione();

		if ( null === $impostazione ) {
			return Festivi::errore_non_scelta( $prefisso );
		}

		$slittata = Festivi::slitta( $calcolata, $impostazione );

		if ( is_wp_error( $slittata ) ) {
			return new \WP_Error( $slittata->get_error_code(), $prefisso . ' ' . $slittata->get_error_message() );
		}

		return $slittata;
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
	 * @param bool $con_repertorio Anche le tabelle del repertorio, per il passaggio che vi scrive dentro la transazione.
	 * @return array<int, string> Nomi delle tabelle, vuoto se sono tutte transazionali.
	 */
	public static function tabelle_senza_transazioni( bool $con_repertorio = false ): array {
		global $wpdb;

		$tabelle = array( $wpdb->posts, $wpdb->postmeta, $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->prefix . 'conformita_core_registro' );

		// L'importazione scrive il numero dentro la transazione: anche le tabelle del repertorio devono annullare.
		if ( $con_repertorio ) {
			$tabelle[] = Repertorio::tabella_anni();
			$tabelle[] = Repertorio::tabella_assegnazioni();
		}

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

		/*
		 * Si decide solo su una risposta certa. Una lettura fallita non vuol
		 * dire scrittura automatica spenta: un punto di ripristino fuori da una
		 * transazione non trattiene niente, e le scritture del passaggio
		 * diventerebbero definitive una per una.
		 */
		$automatica = $wpdb->get_var( 'SELECT @@autocommit' );
		$modi       = array(
			'1' => 'transazione',
			'0' => 'punto',
		);
		$modo       = null === $automatica ? null : ( $modi[ (string) $automatica ] ?? null );
		$esito      = false;

		if ( 'transazione' === $modo ) {
			$esito = $wpdb->query( 'START TRANSACTION' );
		} elseif ( 'punto' === $modo ) {
			$esito = $wpdb->query( 'SAVEPOINT albo_pretorio_passaggio' );
		}

		if ( null === $modo || false === $esito ) {
			return new \WP_Error(
				'albo_transazione_non_aperta',
				__( 'L\'atto resta in verifica: la banca dati non ha aperto la transazione che rende la pubblicazione tutta o niente.', 'albo-pretorio-pa' )
			);
		}

		return $modo;
	}

	/**
	 * Blocca la riga dell'atto per le altre richieste e controlla che sia ancora quello controllato.
	 *
	 * I controlli del passaggio leggono l'atto prima della transazione, e fra
	 * quei controlli e la transazione un'altra richiesta puo' averlo rimandato
	 * in bozza, cambiato, rimesso in verifica o pubblicato. Qui, dentro la
	 * transazione, la riga si blocca fino alla chiusura, e l'atto si rilegge
	 * dalla banca dati: se non e' identico alla fotografia dei controlli il
	 * passaggio non comincia. Poi la memoria dell'atto si toglie senza far
	 * girare agganci, cosi' che le scritture del passaggio partano da quello
	 * che la banca dati contiene.
	 *
	 * @param int                  $atto_id  Atto.
	 * @param array<string, mixed> $partenza Fotografia dei controlli.
	 * @return true|\WP_Error
	 */
	private static function blocca( int $atto_id, array $partenza ) {
		global $wpdb;

		$riga = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $atto_id ) );

		$adesso = self::fotografia( $atto_id );

		if ( null === $adesso ) {
			return self::errore_lettura();
		}

		if ( null === $riga || $adesso !== $partenza ) {
			return new \WP_Error(
				'albo_atto_cambiato_prima_del_passaggio',
				__( 'L\'atto non e\' piu\' quello controllato: un\'altra operazione lo ha cambiato mentre il passaggio cominciava. Niente e\' stato scritto; si riapra l\'atto e si ripeta, se serve.', 'albo-pretorio-pa' )
			);
		}

		wp_cache_delete( $atto_id, 'posts' );
		wp_cache_delete( $atto_id, 'post_meta' );

		foreach ( get_object_taxonomies( TIPO ) as $tassonomia ) {
			wp_cache_delete( $atto_id, $tassonomia . '_relationships' );
		}

		return true;
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
	 * Si usa all'inizio del passaggio, prima di leggere l'atto, e dopo un
	 * passaggio riuscito e chiuso, perche' chi legge dopo trovi lo stato
	 * scritto.
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
			$voci = Lettura::righe(
				$wpdb->prepare( 'SELECT term_taxonomy_id FROM %i WHERE object_id = %d', $wpdb->term_relationships, $atto_id ),
				array( 'term_taxonomy_id' )
			);

			// Senza sapere quali voci, si dimentica tutto: una memoria in piu' svuotata costa meno di un conteggio sbagliato.
			if ( null === $voci ) {
				wp_cache_flush();
			} elseif ( array() !== $voci ) {
				clean_term_cache( array_map( 'intval', array_column( $voci, 'term_taxonomy_id' ) ), '', false );
			}
		} finally {
			wp_suspend_cache_invalidation( (bool) $sospesa );

			/*
			 * La pulizia fa girare gli agganci degli altri componenti, che
			 * possono rimettere in memoria dati vecchi di questo atto o di un
			 * altro gia' pulito: lo svuotamento intero, che non fa girare
			 * agganci, viene per ultimo, su ogni uscita.
			 */
			wp_cache_flush();
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
	 * Il rifiuto per una lettura dell'atto che non ha risposto.
	 *
	 * @return \WP_Error
	 */
	private static function errore_lettura(): \WP_Error {
		return new \WP_Error(
			'albo_atto_non_letto',
			__( 'L\'atto resta in verifica: la banca dati non ha risposto a una lettura dell\'atto, e senza quella lettura non si puo\' sapere se e\' ancora quello controllato.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * L'atto com'e' nella banca dati: riga, dati, voci e contenuti figli.
	 *
	 * Letto direttamente, senza la memoria di WordPress, perche' serve a
	 * confrontare quello che la transazione sta per confermare.
	 *
	 * Se anche una sola lettura non risponde non c'e' fotografia: una lettura
	 * fallita darebbe un elenco vuoto, e due letture fallite allo stesso modo
	 * sembrerebbero un atto che non e' cambiato.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, mixed>|null
	 */
	private static function fotografia( int $atto_id ): ?array {
		global $wpdb;

		$riga  = Lettura::righe( $wpdb->prepare( 'SELECT * FROM %i WHERE ID = %d', $wpdb->posts, $atto_id ), array( 'ID', 'post_status' ) );
		$dati  = Lettura::righe( $wpdb->prepare( 'SELECT meta_id, meta_key, meta_value FROM %i WHERE post_id = %d ORDER BY meta_id', $wpdb->postmeta, $atto_id ), array( 'meta_id', 'meta_key', 'meta_value' ) );
		$voci  = Lettura::righe( $wpdb->prepare( 'SELECT term_taxonomy_id FROM %i WHERE object_id = %d ORDER BY term_taxonomy_id', $wpdb->term_relationships, $atto_id ), array( 'term_taxonomy_id' ) );
		$figli = Lettura::righe( $wpdb->prepare( 'SELECT ID, post_type, post_status, post_parent FROM %i WHERE post_parent = %d ORDER BY ID', $wpdb->posts, $atto_id ), array( 'ID', 'post_type', 'post_status', 'post_parent' ) );

		if ( null === $riga || null === $dati || null === $voci || null === $figli ) {
			return null;
		}

		return array(
			'riga'  => $riga[0] ?? null,
			'dati'  => $dati,
			'voci'  => array_column( $voci, 'term_taxonomy_id' ),
			'figli' => $figli,
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

		if ( null === $arrivo ) {
			return self::errore_lettura();
		}

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

			if ( 'post_name' === $colonna && '' === (string) $valore && ( self::pubblicazione_in_corso( $atto_id ) || ! empty( self::$in_corso[ $atto_id ]['importato'] ) ) && '' !== (string) $nuovo ) {
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
