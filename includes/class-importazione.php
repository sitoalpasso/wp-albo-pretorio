<?php
/**
 * L'importazione vera dal plugin "Albo Pretorio On line" (ALBO-35).
 *
 * Dopo il rapporto a secco, scrive in questo albo gli atti che il rapporto
 * dice portabili, con il numero, l'anno e le date di Albo Pretorio On line e i
 * documenti copiati byte per byte dall'archivio del backup.
 *
 * **A blocchi.** L'avvio rilegge il backup, rifa' il rapporto, lo confronta
 * con quello confermato da chi importa e fissa l'elenco degli atti da portare.
 * Ogni blocco rilegge il backup, rifa' il rapporto con lo stato attuale di
 * questo albo e porta gli atti successivi dell'elenco finche' non ha usato il
 * suo tempo; lo stato si salva dopo ogni atto, cosi' che un blocco interrotto
 * riprenda dall'atto in cui si e' fermato.
 *
 * **Senza doppioni.** Ogni atto importato ricorda il suo identificativo in
 * Albo Pretorio On line: per il rapporto e' "gia' importato" e non si tocca piu'. Una
 * bozza lasciata da un blocco interrotto a meta' ha lo stesso ricordo, e si
 * cancella con i suoi file prima di rifare l'atto.
 *
 * **Tutto o niente per atto.** La bozza si prepara con dati e documenti; il
 * passaggio dell'albo `Passaggi::importa()` la porta a pubblicato con numero,
 * date, fine e voce di registro in una transazione. Se qualcosa non riesce la
 * bozza si cancella con i suoi file, e l'atto risulta non riuscito.
 *
 * **Un'importazione alla volta.** Lo stato sta in un'opzione del sito, e ogni
 * operazione prende un turno nella banca dati senza attendere.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/*
 * Il turno e' un'interrogazione diretta per natura: nessuna funzione di
 * WordPress prende un blocco della banca dati.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Avvio, blocchi e rapporto finale dell'importazione.
 */
final class Importazione {

	/**
	 * L'opzione con lo stato dell'importazione in corso o dell'ultima finita.
	 *
	 * @var string
	 */
	const OPZIONE = 'albo_pretorio_importazione';

	/**
	 * Gli atti che un blocco porta al massimo.
	 *
	 * @var int
	 */
	const ATTI_PER_BLOCCO = 200;

	/**
	 * I secondi di una richiesta oltre i quali un blocco non comincia un altro atto.
	 *
	 * @var int
	 */
	const SECONDI_PER_BLOCCO = 20;

	/**
	 * L'origine della durata di un atto importato.
	 *
	 * @var string
	 */
	const ORIGINE_DURATA = 'date_scritte_in_albo_pretorio_on_line';

	/**
	 * I dubbi del rapporto che l'importazione porta con se' senza condizioni.
	 *
	 * Sono dubbi che nessuna decisione presa adesso puo' risolvere: si
	 * scrivono nella voce di registro dell'atto. Gli altri tre ammessi hanno
	 * una condizione e si guardano a parte.
	 *
	 * @var array<int, string>
	 */
	const DUBBI_PORTATI = array( 'principale_assente', 'principali_multipli', 'durata_breve', 'durata_lunga', 'anno_diverso', 'responsabile_assente', 'ufficio_assente' );

	/**
	 * Nome del dato della bozza con i file dei suoi documenti da togliere.
	 *
	 * Si scrive prima di cancellare i documenti: se un documento se ne va e il
	 * suo file resta, la bozza, che resta anche lei, ricorda ancora dove sta.
	 */
	const META_FILE_DA_TOGLIERE = '_albo_pretorio_importazione_file_da_togliere';

	/**
	 * Nome del dato della bozza con i documenti da togliere.
	 *
	 * WordPress cancella i dati di un documento prima della sua riga: se la
	 * riga resta, il documento perde la marca del meccanismo comune e non si
	 * ritroverebbe piu'. La bozza ne ricorda l'identificativo.
	 */
	const META_DOCUMENTI_DA_TOGLIERE = '_albo_pretorio_importazione_documenti_da_togliere';

	/**
	 * La bozza che l'importazione sta portando a pubblicato in questo momento, o zero.
	 *
	 * @var int
	 */
	private static $in_lavorazione = 0;

	/**
	 * Se l'importazione lavora anche dentro la transazione di un altro: mai, fuori dalle prove.
	 *
	 * Le prove girano dentro una transazione che annullano alla fine, e la
	 * aprono con un valore che solo loro scrivono.
	 *
	 * @var bool
	 */
	private static $dentro_altra_transazione = false;

	/**
	 * Se l'importazione sta portando a pubblicato questa bozza, in questo momento.
	 *
	 * Il passaggio di importazione salta la verifica, e lo concede solo qui.
	 *
	 * @param int $atto_id Bozza.
	 * @return bool
	 */
	public static function in_lavorazione( int $atto_id ): bool {
		return $atto_id > 0 && self::$in_lavorazione === $atto_id;
	}

	/**
	 * Il nome nell'indirizzo della bozza preparata per un atto di Albo Pretorio On line.
	 *
	 * Il segno e' casuale e sta nello stato dell'importazione prima che la bozza
	 * nasca; il nome si scrive insieme alla bozza, nella stessa istruzione. Una
	 * bozza rimasta prima del ricordo dell'origine si riconosce cosi', e una
	 * bozza qualunque non puo' avere quel nome per caso.
	 *
	 * @param string $segno Segno casuale della lavorazione.
	 * @return string
	 */
	public static function nome_bozza( string $segno ): string {
		return 'albo-importazione-' . sanitize_key( $segno );
	}

	/**
	 * Il nome del turno nella banca dati, uno per sito.
	 *
	 * @return string
	 */
	public static function nome_turno(): string {
		global $wpdb;

		return $wpdb->prefix . 'albo_pretorio_importazione';
	}

	/**
	 * Lo stato salvato, o nulla se non c'e' mai stata un'importazione.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function stato(): ?array {
		$stato = get_option( self::OPZIONE, null );

		return is_array( $stato ) && isset( $stato['stato'], $stato['selezione'], $stato['posizione'] ) ? $stato : null;
	}

	/**
	 * Se un'importazione e' in corso.
	 *
	 * @return bool
	 */
	public static function in_corso(): bool {
		$stato = self::stato();

		return null !== $stato && 'in_corso' === $stato['stato'];
	}

	/**
	 * Perche' un atto del rapporto non si porta, o nulla se si porta.
	 *
	 * @param array<string, mixed> $atto              Atto del rapporto.
	 * @param bool                 $conferma_visibili Chi importa ha dato la conferma sui dati personali degli atti visibili.
	 * @return array{codice: string, testo: string}|null
	 */
	public static function motivo( array $atto, bool $conferma_visibili ): ?array {
		if ( AnalisiImportazione::IMPORTATO === $atto['esito'] ) {
			return self::motivo_di( 'gia_importato', '' );
		}

		if ( AnalisiImportazione::ESCLUSO === $atto['esito'] ) {
			return self::motivo_di( 'bozza', '' );
		}

		foreach ( array( 'conflitto', 'dubbio' ) as $livello ) {
			foreach ( $atto['segnalazioni'] as $segnalazione ) {
				if ( $livello !== $segnalazione['livello'] ) {
					continue;
				}

				$codice = $segnalazione['codice'];

				if ( 'dubbio' === $livello && self::dubbio_portato( $codice, $atto, $conferma_visibili ) ) {
					continue;
				}

				if ( 'tipo_da_decidere' === $codice ) {
					$codice = 'tipo_da_creare';
				}

				return self::motivo_di( $codice, $segnalazione['testo'] );
			}
		}

		if ( array() === $atto['documenti'] ) {
			return self::motivo_di( 'senza_documenti', '' );
		}

		return null;
	}

	/**
	 * Se un dubbio dell'atto si porta con l'atto.
	 *
	 * @param string               $codice            Codice del dubbio.
	 * @param array<string, mixed> $atto              Atto del rapporto.
	 * @param bool                 $conferma_visibili Conferma sui dati personali degli atti visibili.
	 * @return bool
	 */
	private static function dubbio_portato( string $codice, array $atto, bool $conferma_visibili ): bool {
		if ( in_array( $codice, self::DUBBI_PORTATI, true ) ) {
			return true;
		}

		switch ( $codice ) {
			case 'tipo_da_decidere':
				// Il tipo e' deciso quando esiste: l'ente lo ha creato.
				return is_int( $atto['tipo_id'] );
			case 'annullato':
				// Cosa veda il pubblico di un annullato in pubblicazione e' una decisione ancora aperta.
				return true !== $atto['in_vista'];
			case 'conferma_dati_personali':
				return $conferma_visibili;
		}

		return false;
	}

	/**
	 * Il motivo con la sua spiegazione breve, uguale per tutti gli atti che lo hanno.
	 *
	 * @param string $codice Codice della segnalazione.
	 * @param string $testo  Testo della segnalazione, per i codici senza spiegazione breve.
	 * @return array{codice: string, testo: string}
	 */
	private static function motivo_di( string $codice, string $testo ): array {
		$brevi = array(
			'gia_importato'                => __( 'gia\' importato', 'albo-pretorio-pa' ),
			'bozza'                        => __( 'bozza mai pubblicata', 'albo-pretorio-pa' ),
			'senza_documenti'              => __( 'nessun documento: il documento principale e\' obbligatorio', 'albo-pretorio-pa' ),
			'tipo_da_creare'               => __( 'il tipo di atto con il nome della categoria non c\'e\' in questo albo: si crea, con la sua durata, e si rilancia', 'albo-pretorio-pa' ),
			'conferma_dati_personali'      => __( 'in pubblicazione: manca la conferma sui dati personali degli atti visibili', 'albo-pretorio-pa' ),
			'annullato'                    => __( 'annullato e ancora in pubblicazione: cosa ne veda il pubblico e\' una decisione ancora aperta', 'albo-pretorio-pa' ),
			'documenti_non_verificabili'   => __( 'documenti non verificabili: serve l\'archivio zip del backup', 'albo-pretorio-pa' ),
			'file_mancante'                => __( 'un file non c\'e\' nell\'archivio', 'albo-pretorio-pa' ),
			'impronta_diversa'             => __( 'un file non e\' quello caricato in Albo Pretorio On line', 'albo-pretorio-pa' ),
			'per_estratto'                 => __( 'documento pubblicato per estratto', 'albo-pretorio-pa' ),
			'fine_assente'                 => __( 'data di fine assente', 'albo-pretorio-pa' ),
			'inizio_futuro'                => __( 'inizio della pubblicazione nel futuro', 'albo-pretorio-pa' ),
			'altra_amministrazione'        => __( 'atto di un\'altra amministrazione', 'albo-pretorio-pa' ),
			'testo_non_leggibile'          => __( 'oggetto non leggibile', 'albo-pretorio-pa' ),
			'nome_non_conservabile'        => __( 'responsabile, ufficio o richiedente non si conserverebbero alla lettera', 'albo-pretorio-pa' ),
			'riferimento_non_conservabile' => __( 'riferimento che non si conserverebbe alla lettera', 'albo-pretorio-pa' ),
			'fine_prima_inizio'            => __( 'fine prima dell\'inizio', 'albo-pretorio-pa' ),
			'inizio_non_valido'            => __( 'data di inizio assente o non valida', 'albo-pretorio-pa' ),
			'numero_non_valido'            => __( 'numero di registro non valido', 'albo-pretorio-pa' ),
			'anno_non_valido'              => __( 'anno del numero non valido', 'albo-pretorio-pa' ),
			'numero_doppio'                => __( 'numero portato da piu\' atti', 'albo-pretorio-pa' ),
			'numero_gia_usato'             => __( 'numero gia\' dato a un atto di questo albo', 'albo-pretorio-pa' ),
			'numero_oltre_partenza'        => __( 'numero oltre l\'ultimo numero del registro precedente dichiarato in questo albo', 'albo-pretorio-pa' ),
			'numerazione_non_letta'        => __( 'la numerazione di questo albo non ha risposto', 'albo-pretorio-pa' ),
			'categoria_assente'            => __( 'categoria assente nel backup', 'albo-pretorio-pa' ),
			'id_atto_doppio'               => __( 'numero interno assente o ripetuto', 'albo-pretorio-pa' ),
			'importati_non_letti'          => __( 'la lettura degli atti gia\' importati non ha risposto', 'albo-pretorio-pa' ),
			'non_nel_rapporto'             => __( 'non piu\' nel rapporto', 'albo-pretorio-pa' ),
		);

		return array(
			'codice' => $codice,
			'testo'  => $brevi[ $codice ] ?? $testo,
		);
	}

	/**
	 * Gli atti del rapporto che si portano, e quanti di loro arrivano visibili.
	 *
	 * @param array<string, mixed> $rapporto          Rapporto.
	 * @param bool                 $conferma_visibili Conferma sui dati personali degli atti visibili.
	 * @return array{atti: array<int, string>, visibili: int, principale_da_scegliere: int}
	 */
	public static function selezione( array $rapporto, bool $conferma_visibili ): array {
		$scelta = array(
			'atti'                    => array(),
			'in_vista'                => 0,
			'principale_da_scegliere' => 0,
		);

		foreach ( $rapporto['atti'] as $atto ) {
			if ( null !== self::motivo( $atto, $conferma_visibili ) ) {
				continue;
			}

			$scelta['atti'][] = (string) $atto['id'];

			if ( true === $atto['in_vista'] ) {
				++$scelta['in_vista'];
			}

			if ( null === self::principale( $atto ) ) {
				++$scelta['principale_da_scegliere'];
			}
		}

		return $scelta;
	}

	/**
	 * Quale documento dell'atto e' il principale, o nulla se non si sa.
	 *
	 * @param array<string, mixed> $atto Atto del rapporto.
	 * @return int|null Posizione nell'elenco dei documenti.
	 */
	private static function principale( array $atto ): ?int {
		$principali = array_keys(
			array_filter(
				$atto['documenti'],
				static function ( array $documento ): bool {
					return true === $documento['principale'];
				}
			)
		);

		return 1 === count( $principali ) ? (int) $principali[0] : null;
	}

	/**
	 * L'impronta del file del backup, che lo riconosce fra un blocco e l'altro.
	 *
	 * @param string $percorso Backup.
	 * @return string|null
	 */
	public static function impronta_backup( string $percorso ): ?string {
		$impronta = is_file( $percorso ) ? hash_file( 'sha256', $percorso ) : false;

		return false === $impronta ? null : $impronta;
	}

	/**
	 * Avvia l'importazione, dopo aver rifatto il rapporto e averlo confrontato con quello confermato.
	 *
	 * @param string               $percorso          Backup, gia' scelto fra quelli della cartella.
	 * @param string               $nome              Nome del backup, per il rapporto finale.
	 * @param array<string, mixed> $attese            Quello che chi importa ha visto: `impronta`, `atti`, `visibili`.
	 * @param bool                 $conferma          Conferma esplicita dell'importazione.
	 * @param bool                 $conferma_visibili Conferma sui dati personali degli atti che arrivano visibili.
	 * @return array<string, mixed>|\WP_Error Lo stato salvato.
	 */
	public static function avvia( string $percorso, string $nome, array $attese, bool $conferma, bool $conferma_visibili ) {
		if ( ! SchermataImportazione::permessa() ) {
			return self::errore_permesso();
		}

		if ( ! $conferma ) {
			return new \WP_Error( 'albo_importazione_non_confermata', __( 'Importazione non avviata: manca la conferma esplicita.', 'albo-pretorio-pa' ) );
		}

		$turno = self::prendi_turno();

		if ( is_wp_error( $turno ) ) {
			return $turno;
		}

		try {
			return self::avvia_dentro( $percorso, $nome, $attese, $conferma_visibili );
		} finally {
			self::lascia_turno();
		}
	}

	/**
	 * L'avvio, con il turno preso.
	 *
	 * @param string               $percorso          Backup.
	 * @param string               $nome              Nome del backup.
	 * @param array<string, mixed> $attese            Quello che chi importa ha visto.
	 * @param bool                 $conferma_visibili Conferma sui dati personali degli atti visibili.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function avvia_dentro( string $percorso, string $nome, array $attese, bool $conferma_visibili ) {
		if ( self::in_corso() ) {
			return new \WP_Error( 'albo_importazione_in_corso', __( 'Importazione non avviata: ce n\'e\' gia\' una in corso. Si continua o si interrompe quella.', 'albo-pretorio-pa' ) );
		}

		$esterna = self::errore_transazione_esterna();

		if ( null !== $esterna ) {
			return $esterna;
		}

		$protezione = conformita_core_stato_protezione_allegati();

		if ( 'verificata' !== ( $protezione['copertura'] ?? '' ) && empty( $protezione['scavalcata'] ) ) {
			return new \WP_Error( 'albo_importazione_protezione', __( 'Importazione non avviata: la protezione della cartella dei documenti non risulta verificata, e nessun documento si depositerebbe. Si verifica dalla schermata del meccanismo comune, poi si riprova.', 'albo-pretorio-pa' ) );
		}

		$impronta = self::impronta_backup( $percorso );

		if ( null === $impronta || ! isset( $attese['impronta'] ) || ! is_string( $attese['impronta'] ) || ! hash_equals( $impronta, $attese['impronta'] ) ) {
			return self::errore_cambiato();
		}

		$rapporto = self::rapporto( $percorso );

		if ( is_wp_error( $rapporto ) ) {
			return $rapporto;
		}

		if ( ! $rapporto['pacchetto']['zip'] ) {
			return new \WP_Error( 'albo_importazione_senza_zip', __( 'Importazione non avviata: si importa solo da un archivio zip, che porta i documenti. Il solo file SQL non porta nessun atto.', 'albo-pretorio-pa' ) );
		}

		$senza  = self::selezione( $rapporto, false );
		$scelta = self::selezione( $rapporto, $conferma_visibili );

		// Si confrontano i numeri che chi importa ha visto: se il rapporto e' cambiato, ha confermato un'altra cosa.
		if ( (int) ( $attese['atti'] ?? -1 ) !== count( $senza['atti'] ) || (int) ( $attese['in_vista'] ?? -1 ) !== self::selezione( $rapporto, true )['in_vista'] ) {
			return self::errore_cambiato();
		}

		if ( array() === $scelta['atti'] ) {
			return new \WP_Error( 'albo_importazione_vuota', __( 'Importazione non avviata: nel rapporto non c\'e\' nessun atto che si porti.', 'albo-pretorio-pa' ) );
		}

		$precedente = self::stato();
		$stato      = array(
			'stato'             => 'in_corso',
			'backup'            => $nome,
			'percorso'          => $percorso,
			'impronta'          => $impronta,
			'utente'            => get_current_user_id(),
			'avviata'           => gmdate( 'Y-m-d H:i:s' ),
			'finita'            => null,
			'conferma_in_vista' => $conferma_visibili,
			'nel_backup'        => (int) $rapporto['riepilogo']['totale']['nel_backup'],
			'analizzati'        => (int) $rapporto['riepilogo']['totale']['atti'],
			'gia_importati'     => (int) $rapporto['riepilogo']['esiti'][ AnalisiImportazione::IMPORTATO ],
			'selezione'         => $scelta['atti'],
			'posizione'         => 0,
			'importati'         => array(),
			'non_portati'       => self::non_portati( $rapporto, $conferma_visibili ),
			'non_riusciti'      => array(),
			'da_scegliere'      => array(),
			// I segni delle bozze rimaste incerte passano all'importazione nuova, che le ritrova.
			'lavorazione'       => is_array( $precedente['lavorazione'] ?? null ) ? $precedente['lavorazione'] : array(),
		);

		// Lo stato prima era un altro (nessuno, interrotto o finito): un valore uguale non c'e', e falso vuol dire non salvato.
		if ( ! update_option( self::OPZIONE, $stato, false ) ) {
			return new \WP_Error( 'albo_importazione_stato_non_salvato', __( 'Importazione non avviata: la banca dati non ha salvato l\'avvio. Nessun atto e\' stato scritto; si riprova.', 'albo-pretorio-pa' ) );
		}

		return $stato;
	}

	/**
	 * Gli atti del backup che non si portano, raggruppati per motivo, con i loro numeri.
	 *
	 * @param array<string, mixed> $rapporto          Rapporto.
	 * @param bool                 $conferma_visibili Conferma sui dati personali degli atti visibili.
	 * @return array<string, array{testo: string, atti: array<int, string>}>
	 */
	private static function non_portati( array $rapporto, bool $conferma_visibili ): array {
		$gruppi = array();

		foreach ( $rapporto['atti'] as $atto ) {
			$motivo = self::motivo( $atto, $conferma_visibili );

			if ( null === $motivo || 'gia_importato' === $motivo['codice'] ) {
				continue;
			}

			$chiave = $motivo['codice'] . ':' . md5( $motivo['testo'] );

			$gruppi[ $chiave ]['testo']  = $motivo['testo'];
			$gruppi[ $chiave ]['atti'][] = self::numero( $atto );
		}

		return $gruppi;
	}

	/**
	 * Il numero dell'atto come si mostra nel rapporto finale.
	 *
	 * @param array<string, mixed> $atto Atto del rapporto.
	 * @return string
	 */
	private static function numero( array $atto ): string {
		if ( 'bozza' === $atto['stato'] ) {
			return sprintf(
				/* translators: %s: numero interno. */
				__( 'bozza (numero interno %s)', 'albo-pretorio-pa' ),
				$atto['id']
			);
		}

		return $atto['numero'] > 0 ? $atto['numero'] . '/' . $atto['anno'] : sprintf(
			/* translators: %s: numero interno. */
			__( 'senza numero valido (numero interno %s)', 'albo-pretorio-pa' ),
			$atto['id']
		);
	}

	/**
	 * Un blocco: porta gli atti successivi dell'elenco, fino al massimo o al tempo dato.
	 *
	 * Porta sempre almeno un atto, se ne restano.
	 *
	 * @param int   $massimo  Atti al massimo.
	 * @param float $scadenza Istante, in secondi, dopo il quale non si comincia un altro atto.
	 * @return array<string, mixed>|\WP_Error Lo stato salvato.
	 */
	public static function prosegui( int $massimo, float $scadenza ) {
		if ( ! SchermataImportazione::permessa() ) {
			return self::errore_permesso();
		}

		$turno = self::prendi_turno();

		if ( is_wp_error( $turno ) ) {
			return $turno;
		}

		try {
			return self::prosegui_dentro( max( 1, $massimo ), $scadenza );
		} finally {
			self::lascia_turno();
		}
	}

	/**
	 * Il blocco, con il turno preso.
	 *
	 * @param int   $massimo  Atti al massimo.
	 * @param float $scadenza Istante dopo il quale non si comincia un altro atto.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function prosegui_dentro( int $massimo, float $scadenza ) {
		$stato = self::stato();

		if ( null === $stato || 'in_corso' !== $stato['stato'] ) {
			return new \WP_Error( 'albo_importazione_nessuna', __( 'Nessuna importazione in corso.', 'albo-pretorio-pa' ) );
		}

		$esterna = self::errore_transazione_esterna();

		if ( null !== $esterna ) {
			return $esterna;
		}

		$impronta = self::impronta_backup( (string) $stato['percorso'] );

		if ( null === $impronta || ! hash_equals( (string) $stato['impronta'], $impronta ) ) {
			return new \WP_Error( 'albo_importazione_backup_cambiato', __( 'L\'importazione si e\' fermata: il file del backup non c\'e\' piu\' o e\' cambiato dall\'avvio. Gli atti gia\' importati restano; si interrompe e si riparte dal rapporto.', 'albo-pretorio-pa' ) );
		}

		$letto = LettoreBackup::leggi( (string) $stato['percorso'] );

		if ( is_wp_error( $letto ) ) {
			return $letto;
		}

		$rapporto = AnalisiImportazione::rapporto( $letto, AnalisiImportazione::contesto( AnalisiImportazione::anni( $letto ), Passaggi::ora() ) );
		$indice   = array();

		foreach ( $rapporto['atti'] as $atto ) {
			$indice[ (string) $atto['id'] ] = $atto;
		}

		$fatti  = 0;
		$totale = count( $stato['selezione'] );

		while ( $stato['posizione'] < $totale ) {
			if ( $fatti >= $massimo || ( $fatti > 0 && microtime( true ) > $scadenza ) ) {
				break;
			}

			$id     = (string) $stato['selezione'][ $stato['posizione'] ];
			$atto   = $indice[ $id ] ?? null;
			$fermo  = false;
			$motivo = null === $atto ? self::motivo_di( 'non_nel_rapporto', '' ) : self::motivo( $atto, (bool) $stato['conferma_in_vista'] );

			if ( null !== $atto && null !== $motivo && 'gia_importato' === $motivo['codice'] && ! isset( $stato['importati'][ $id ] ) ) {
				/*
				 * Importato da questa importazione, ma lo stato non lo sa: la
				 * richiesta si e' fermata fra la conferma dell'atto e il
				 * salvataggio. Si ricostruisce l'esito dal registro.
				 */
				$ritrovato = self::esito_dal_registro( (int) $atto['atto_id'] );

				// Senza il registro l'esito non si ricostruisce: ci si ferma qui, e "Continua" riprova.
				if ( is_wp_error( $ritrovato ) ) {
					return $ritrovato;
				}

				$stato = self::annota_importazione( $stato, $atto, $ritrovato );

				// L'atto e' pubblicato: nessuna sua bozza e' rimasta da ritrovare.
				unset( $stato['lavorazione'][ $id ] );
			} elseif ( null === $motivo ) {
				// Il segno della bozza si salva prima che la bozza nasca.
				$stato['lavorazione'][ $id ][] = bin2hex( random_bytes( 16 ) );

				if ( ! update_option( self::OPZIONE, $stato, false ) ) {
					return self::errore_stato_non_salvato();
				}

				$esito = self::importa_atto( $atto, $letto, $stato );
				$fermo = is_wp_error( $esito ) && ! empty( $esito->get_error_data()['incerto'] );
				$stato = self::annota_importazione( $stato, $atto, $esito );

				// Con un esito certo nessuna bozza di questo atto e' rimasta: i segni non servono piu'.
				if ( ! $fermo ) {
					unset( $stato['lavorazione'][ $id ] );
				}
			} else {
				$stato = self::annota_non_portato( $stato, $motivo, null === $atto ? $id : self::numero( $atto ) );
			}

			++$stato['posizione'];
			++$fatti;

			if ( $stato['posizione'] >= $totale ) {
				$stato['stato']  = 'finita';
				$stato['finita'] = gmdate( 'Y-m-d H:i:s' );
			}

			// Dopo ogni atto: un blocco interrotto riprende da qui, non da capo.
			if ( ! update_option( self::OPZIONE, $stato, false ) ) {
				return self::errore_stato_non_salvato();
			}

			// Uno stato incerto non si supera da soli: chi importa guarda prima l'atto.
			if ( $fermo ) {
				break;
			}
		}

		return $stato;
	}

	/**
	 * Il rifiuto quando la connessione e' dentro la transazione di un altro, o nulla.
	 *
	 * Bozze, documenti e numeri scritti li' diventerebbero definitivi, o
	 * sparirebbero, solo con quella transazione, e i file resterebbero sul
	 * disco: l'importazione non comincia.
	 *
	 * @return \WP_Error|null
	 */
	private static function errore_transazione_esterna(): ?\WP_Error {
		if ( self::$dentro_altra_transazione || ! Repertorio::transazione_esterna() ) {
			return null;
		}

		return new \WP_Error( 'albo_importazione_transazione_esterna', __( 'L\'importazione non comincia: la connessione alla banca dati e\' dentro una transazione aperta da un altro componente, e gli atti scritti non sarebbero definitivi. Si controllano i componenti attivi.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Il rifiuto quando la banca dati non salva lo stato dell'importazione.
	 *
	 * @return \WP_Error
	 */
	private static function errore_stato_non_salvato(): \WP_Error {
		return new \WP_Error( 'albo_importazione_stato_non_salvato', __( 'L\'importazione si e\' fermata: la banca dati non ha salvato a che punto e\' arrivata. Con "Continua" riprende, e gli atti gia\' scritti si riconoscono.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Lo stato con un atto in piu' fra quelli non portati.
	 *
	 * @param array<string, mixed>                 $stato  Stato.
	 * @param array{codice: string, testo: string} $motivo Motivo.
	 * @param string                               $numero Numero dell'atto come si mostra.
	 * @return array<string, mixed>
	 */
	private static function annota_non_portato( array $stato, array $motivo, string $numero ): array {
		$chiave = $motivo['codice'] . ':' . md5( $motivo['testo'] );

		$stato['non_portati'][ $chiave ]['testo']  = $motivo['testo'];
		$stato['non_portati'][ $chiave ]['atti'][] = $numero;

		return $stato;
	}

	/**
	 * Lo stato con l'esito dell'importazione di un atto.
	 *
	 * @param array<string, mixed>           $stato Stato.
	 * @param array<string, mixed>           $atto  Atto del rapporto.
	 * @param array<string, mixed>|\WP_Error $esito Atto importato, o il motivo.
	 * @return array<string, mixed>
	 */
	private static function annota_importazione( array $stato, array $atto, $esito ): array {
		$numero = self::numero( $atto );

		if ( is_wp_error( $esito ) ) {
			$stato['non_riusciti'][] = array(
				'numero' => $numero,
				'codice' => (string) $esito->get_error_code(),
				'testo'  => $esito->get_error_message(),
			);

			return $stato;
		}

		$stato['importati'][ (string) $atto['id'] ] = (int) $esito['atto_id'];

		if ( $esito['da_scegliere'] ) {
			$stato['da_scegliere'][] = $numero;
		}

		return $stato;
	}

	/**
	 * L'esito di un atto gia' importato, ricostruito dalla sua voce di registro.
	 *
	 * @param int $atto_id Atto importato.
	 * @return array{atto_id: int, da_scegliere: bool}|\WP_Error
	 */
	private static function esito_dal_registro( int $atto_id ) {
		$voci = conformita_core_voci_registro(
			array(
				'contenuto' => $atto_id,
				'sezione'   => SEZIONE,
				'azione'    => Passaggi::AZIONE_IMPORTAZIONE,
			)
		);

		if ( ! is_array( $voci ) || 1 !== count( $voci ) ) {
			return new \WP_Error( 'albo_importazione_registro_non_letto', __( 'L\'atto risulta importato, ma la sua voce di registro non si legge: si controlla dalla schermata degli atti.', 'albo-pretorio-pa' ) );
		}

		return array(
			'atto_id'      => $atto_id,
			'da_scegliere' => true === ( $voci[0]['dettagli']['principale_da_scegliere'] ?? null ),
		);
	}

	/**
	 * Interrompe l'importazione in corso. Gli atti gia' importati restano.
	 *
	 * @return array<string, mixed>|\WP_Error Lo stato salvato.
	 */
	public static function interrompi() {
		if ( ! SchermataImportazione::permessa() ) {
			return self::errore_permesso();
		}

		$turno = self::prendi_turno();

		if ( is_wp_error( $turno ) ) {
			return $turno;
		}

		try {
			$stato = self::stato();

			if ( null === $stato || 'in_corso' !== $stato['stato'] ) {
				return new \WP_Error( 'albo_importazione_nessuna', __( 'Nessuna importazione in corso.', 'albo-pretorio-pa' ) );
			}

			$stato['stato']  = 'interrotta';
			$stato['finita'] = gmdate( 'Y-m-d H:i:s' );

			// Da "in corso" a "interrotta": il valore cambia sempre, e falso vuol dire non salvato.
			if ( ! update_option( self::OPZIONE, $stato, false ) ) {
				return new \WP_Error( 'albo_importazione_stato_non_salvato', __( 'L\'importazione non si e\' interrotta: la banca dati non ha salvato l\'interruzione, e risulta ancora in corso. Si riprova.', 'albo-pretorio-pa' ) );
			}

			return $stato;
		} finally {
			self::lascia_turno();
		}
	}

	/**
	 * Il rapporto di un backup, con lo stato attuale di questo albo.
	 *
	 * @param string $percorso Backup.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function rapporto( string $percorso ) {
		$letto = LettoreBackup::leggi( $percorso );

		if ( is_wp_error( $letto ) ) {
			return $letto;
		}

		return AnalisiImportazione::rapporto( $letto, AnalisiImportazione::contesto( AnalisiImportazione::anni( $letto ), Passaggi::ora() ) );
	}

	/**
	 * Importa un atto: la bozza con dati e documenti, poi il passaggio a pubblicato.
	 *
	 * Un'eccezione non dice da se' a che punto si e' fermata la scrittura: si
	 * guarda la banca dati. Atto pubblicato con il suo numero: importato.
	 * Bozza rimasta: si cancella. Se la cancellazione non riesce, o il numero
	 * e' registrato senza l'atto pubblicato, lo stato e' incerto e il blocco
	 * si ferma.
	 *
	 * @param array<string, mixed> $atto  Atto del rapporto, portabile.
	 * @param array<string, mixed> $letto Backup letto.
	 * @param array<string, mixed> $stato Stato dell'importazione.
	 * @return array{atto_id: int, da_scegliere: bool}|\WP_Error
	 */
	private static function importa_atto( array $atto, array $letto, array $stato ) {
		$id    = (string) $atto['id'];
		$segni = array_map( 'strval', (array) ( $stato['lavorazione'][ $id ] ?? array() ) );
		$tolte = self::togli_bozze_lasciate( $id, $segni );

		// Una bozza lasciata che non si toglie: lo stato dell'atto non e' garantito.
		if ( is_wp_error( $tolte ) ) {
			return self::incerto( $tolte );
		}

		$atto_id   = 0;
		$documenti = null;

		try {
			$bozza = self::prepara_bozza( $atto, (string) end( $segni ) );

			if ( is_wp_error( $bozza ) ) {
				return self::dopo_il_fallimento( $id, 0, $atto, $bozza, $segni );
			}

			$atto_id   = $bozza;
			$documenti = self::deposita_documenti( $atto_id, $atto, (string) $stato['percorso'], $letto );

			if ( is_wp_error( $documenti ) ) {
				return self::dopo_il_fallimento( $id, $atto_id, $atto, $documenti, $segni );
			}

			$portati = array();

			foreach ( $atto['segnalazioni'] as $segnalazione ) {
				if ( 'dubbio' === $segnalazione['livello'] ) {
					$portati[] = $segnalazione['codice'];
				}
			}

			self::$in_lavorazione = $atto_id;

			try {
				$esito = Passaggi::importa(
					$atto_id,
					array(
						'anno'     => (int) $atto['anno'],
						'numero'   => (int) $atto['numero'],
						'inizio'   => (string) $atto['inizio'],
						'fine'     => (string) $atto['fine'],
						'dettagli' => array(
							'origine'                 => $id,
							'backup'                  => (string) $stato['backup'],
							'impronta_backup'         => (string) $stato['impronta'],
							'durata_giorni'           => (int) $atto['giorni'],
							'origine_durata'          => self::ORIGINE_DURATA,
							'annullamento'            => $atto['scrittura']['annullamento'],
							'motivo_annullamento'     => (string) $atto['scrittura']['motivo'],
							'dubbi_portati'           => array_values( array_unique( $portati ) ),
							'conferma_dati_personali' => true === $atto['in_vista'] && true === $stato['conferma_in_vista'],
							'principale_da_scegliere' => null === $documenti['principale'],
							'documenti_titoli'        => $documenti['titoli'],
							'documenti_ruoli'         => $documenti['ruoli'],
							'documenti_impronte'      => $documenti['impronte'],
						),
					)
				);
			} finally {
				self::$in_lavorazione = 0;
			}
		} catch ( \Throwable $errore ) {
			return self::dopo_il_fallimento(
				$id,
				$atto_id,
				$atto,
				new \WP_Error(
					'albo_importazione_eccezione',
					sprintf(
						/* translators: %s: messaggio dell'eccezione. */
						__( 'L\'atto non e\' stato importato: un errore inatteso ha fermato la scrittura (%s).', 'albo-pretorio-pa' ),
						$errore->getMessage()
					)
				),
				$segni,
				is_array( $documenti ) ? null === $documenti['principale'] : null
			);
		}

		if ( is_wp_error( $esito ) ) {
			// I messaggi del passaggio parlano di un atto in verifica: qui l'atto non c'e' piu'.
			return self::dopo_il_fallimento(
				$id,
				$atto_id,
				$atto,
				new \WP_Error(
					(string) $esito->get_error_code(),
					__( 'L\'atto non e\' stato importato: il passaggio a pubblicato non e\' riuscito, tutto e\' stato annullato e la bozza cancellata. Il codice dice il motivo.', 'albo-pretorio-pa' )
				),
				$segni
			);
		}

		return array(
			'atto_id'      => $atto_id,
			'da_scegliere' => null === $documenti['principale'],
		);
	}

	/**
	 * Cosa resta di un atto la cui importazione non e' andata come previsto, guardando la banca dati.
	 *
	 * @param string             $id           Identificativo in Albo Pretorio On line.
	 * @param int                $atto_id      Bozza, se si conosce.
	 * @param array              $atto         Atto del rapporto.
	 * @param \WP_Error          $errore       Il motivo.
	 * @param array<int, string> $segni        Segni delle bozze di questo atto.
	 * @param bool|null          $da_scegliere Principale da scegliere, se i documenti sono stati depositati.
	 * @return array{atto_id: int, da_scegliere: bool}|\WP_Error
	 */
	private static function dopo_il_fallimento( string $id, int $atto_id, array $atto, \WP_Error $errore, array $segni, ?bool $da_scegliere = null ) {
		if ( $atto_id > 0 ) {
			clean_post_cache( $atto_id );

			$numero = Repertorio::di( $atto_id );

			// Confermato nella banca dati prima dell'eccezione: l'atto c'e', pubblicato e numerato.
			if ( 'publish' === get_post_status( $atto_id ) && array(
				'anno'   => (int) $atto['anno'],
				'numero' => (int) $atto['numero'],
			) === $numero ) {
				return null === $da_scegliere ? self::esito_dal_registro( $atto_id ) : array(
					'atto_id'      => $atto_id,
					'da_scegliere' => $da_scegliere,
				);
			}

			if ( null !== $numero || ! in_array( get_post_status( $atto_id ), array( false, 'draft', 'auto-draft' ), true ) ) {
				return self::incerto( $errore );
			}
		}

		// Anche la bozza di cui non si conosce il numero: la si riconosce dal nome.
		$tolte = self::togli_bozze_lasciate( $id, $segni );

		return is_wp_error( $tolte ) ? self::incerto( $errore ) : $errore;
	}

	/**
	 * Il rifiuto con lo stato incerto, che ferma il blocco.
	 *
	 * @param \WP_Error $errore Il motivo.
	 * @return \WP_Error
	 */
	private static function incerto( \WP_Error $errore ): \WP_Error {
		return new \WP_Error(
			(string) $errore->get_error_code(),
			$errore->get_error_message() . ' ' . __( 'Stato incerto: l\'atto o il suo numero potrebbero essere rimasti a meta\'. L\'importazione si e\' fermata; si controlla l\'atto prima di continuare.', 'albo-pretorio-pa' ),
			array( 'incerto' => true )
		);
	}

	/**
	 * Toglie le bozze che un'importazione interrotta ha lasciato per questo atto di Albo Pretorio On line.
	 *
	 * Una bozza si riconosce dal ricordo dell'origine, che solo l'importazione
	 * scrive, o dal nome con uno dei segni casuali salvati per questo atto.
	 *
	 * @param string             $id    Identificativo in Albo Pretorio On line.
	 * @param array<int, string> $segni Segni delle bozze di questo atto.
	 * @return true|\WP_Error
	 */
	private static function togli_bozze_lasciate( string $id, array $segni ) {
		global $wpdb;

		$nomi = array_map( array( self::class, 'nome_bozza' ), $segni );

		// Il nome di nessuna bozza: con nessun segno, vale solo il ricordo dell'origine.
		if ( array() === $nomi ) {
			$nomi[] = '';
		}

		$segnaposto = implode( ', ', array_fill( 0, count( $nomi ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- i segnaposto dei nomi sono costruiti qui sopra, uno per nome.
		$righe = Lettura::righe(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM %i p LEFT JOIN %i m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s AND p.post_status IN ( %s, %s ) AND ( m.meta_value = %s OR ( p.post_name <> '' AND p.post_name IN ( {$segnaposto} ) ) )",
				array_merge( array( $wpdb->posts, $wpdb->postmeta, META_ORIGINE_IMPORTAZIONE, TIPO, 'draft', 'auto-draft', $id ), $nomi )
			),
			array( 'ID' )
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $righe ) {
			return new \WP_Error( 'albo_importazione_non_letta', __( 'L\'atto non e\' stato importato: la banca dati non ha risposto alla ricerca delle bozze lasciate da un\'importazione interrotta.', 'albo-pretorio-pa' ) );
		}

		foreach ( $righe as $riga ) {
			if ( ! self::togli_bozza( (int) $riga['ID'] ) ) {
				return new \WP_Error( 'albo_importazione_bozza_rimasta', __( 'L\'atto non e\' stato importato: la bozza lasciata da un\'importazione interrotta non si e\' cancellata.', 'albo-pretorio-pa' ) );
			}
		}

		return true;
	}

	/**
	 * Cancella una bozza preparata dall'importazione, con i suoi documenti e i loro file.
	 *
	 * Ogni cancellazione si verifica: un documento o un file che resta lascia
	 * al suo posto anche la bozza, con l'elenco dei file, e il rilancio
	 * riprova da li'. Cancellare la bozza per prima lascerebbe documenti e
	 * file senza nessuno che li ritrovi.
	 *
	 * @param int $atto_id Bozza.
	 * @return bool Se la bozza non c'e' piu'.
	 */
	private static function togli_bozza( int $atto_id ): bool {
		global $wpdb;

		$documenti = self::documenti_della_bozza( $atto_id );

		// Prima gli elenchi dei documenti e dei file, scritti sulla bozza: la bozza si cancella per ultima, quindi resta finche' resta qualcosa.
		$ricordati = self::ricordati( $atto_id, self::META_DOCUMENTI_DA_TOGLIERE );
		$file      = self::ricordati( $atto_id, self::META_FILE_DA_TOGLIERE );

		if ( null === $documenti || null === $ricordati || null === $file ) {
			return false;
		}

		foreach ( $documenti as $documento => $percorso ) {
			if ( ! in_array( (string) $documento, $ricordati, true ) ) {
				if ( false === add_post_meta( $atto_id, self::META_DOCUMENTI_DA_TOGLIERE, (string) $documento ) ) {
					return false;
				}

				$ricordati[] = (string) $documento;
			}

			if ( '' !== $percorso && ! in_array( $percorso, $file, true ) ) {
				if ( false === add_post_meta( $atto_id, self::META_FILE_DA_TOGLIERE, wp_slash( $percorso ) ) ) {
					return false;
				}

				$file[] = $percorso;
			}
		}

		// Anche i documenti ricordati che hanno perso la marca in una cancellazione a meta': sono ancora figli di questa bozza.
		$da_togliere = self::figli_fra( $atto_id, $ricordati );

		if ( null === $da_togliere ) {
			return false;
		}

		foreach ( array_unique( array_merge( array_keys( $documenti ), $da_togliere ) ) as $documento ) {
			wp_delete_attachment( $documento, true );
			clean_post_cache( $documento );

			if ( get_post( $documento ) instanceof \WP_Post ) {
				return false;
			}
		}

		foreach ( $file as $percorso ) {
			if ( ! self::file_dei_documenti( $percorso ) ) {
				continue;
			}

			wp_delete_file( $percorso );
			clearstatcache( true, $percorso );

			if ( file_exists( $percorso ) ) {
				return false;
			}
		}

		// Prima di togliere la bozza, letture che rispondono davvero: nessun documento le e' rimasto, con la marca o senza.
		if ( array() !== self::documenti_della_bozza( $atto_id ) || array() !== self::figli_fra( $atto_id, $ricordati ) ) {
			return false;
		}

		wp_delete_post( $atto_id, true );
		clean_post_cache( $atto_id );

		// Solo una lettura che risponde, e vuota, dice che la bozza non c'e' piu': una lettura fallita lascerebbe l'esito certo e la bozza dimenticata.
		return array() === Lettura::righe( $wpdb->prepare( 'SELECT ID FROM %i WHERE ID = %d', $wpdb->posts, $atto_id ), array( 'ID' ) );
	}

	/**
	 * Un elenco scritto sulla bozza (documenti o file da togliere), o nulla se la banca dati non risponde.
	 *
	 * Letto dalla banca dati com'e': un elenco vuoto per errore farebbe
	 * cancellare la bozza, che e' l'unica a ricordare cosa resta da togliere.
	 *
	 * @param int    $atto_id Bozza.
	 * @param string $chiave  Nome del dato.
	 * @return array<int, string>|null
	 */
	private static function ricordati( int $atto_id, string $chiave ): ?array {
		global $wpdb;

		$righe = Lettura::righe(
			$wpdb->prepare( 'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id', $wpdb->postmeta, $atto_id, $chiave ),
			array( 'meta_value' )
		);

		return null === $righe ? null : array_map(
			static function ( array $riga ): string {
				return (string) $riga['meta_value'];
			},
			$righe
		);
	}

	/**
	 * Fra i documenti ricordati, quelli che esistono ancora come figli della bozza, o nulla se la banca dati non risponde.
	 *
	 * @param int                $atto_id   Bozza.
	 * @param array<int, string> $ricordati Identificativi ricordati.
	 * @return array<int, int>|null
	 */
	private static function figli_fra( int $atto_id, array $ricordati ): ?array {
		global $wpdb;

		$id = array_values( array_filter( array_map( 'intval', $ricordati ) ) );

		if ( array() === $id ) {
			return array();
		}

		$segnaposto = implode( ', ', array_fill( 0, count( $id ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- i segnaposto degli identificativi sono costruiti qui sopra, uno per identificativo.
		$righe = Lettura::righe(
			$wpdb->prepare(
				"SELECT ID FROM %i WHERE post_parent = %d AND post_type = %s AND ID IN ( {$segnaposto} ) ORDER BY ID",
				array_merge( array( $wpdb->posts, $atto_id, 'attachment' ), $id )
			),
			array( 'ID' )
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return null === $righe ? null : array_map(
			static function ( array $riga ): int {
				return (int) $riga['ID'];
			},
			$righe
		);
	}

	/**
	 * I documenti protetti di una bozza, con il percorso del file, o nulla se la banca dati non risponde.
	 *
	 * Letti dalla banca dati com'e', con una lettura che distingue "nessun
	 * documento" da "nessuna risposta": un elenco vuoto per errore farebbe
	 * cancellare la bozza lasciando i documenti senza nessuno che li ritrovi.
	 *
	 * @param int $atto_id Bozza.
	 * @return array<int, string>|null Identificativo del documento => percorso del file, vuoto se non c'e'.
	 */
	private static function documenti_della_bozza( int $atto_id ): ?array {
		global $wpdb;

		$righe = Lettura::righe(
			$wpdb->prepare(
				'SELECT p.ID, f.meta_value AS file FROM %i p JOIN %i m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = %s LEFT JOIN %i f ON f.post_id = p.ID AND f.meta_key = %s WHERE p.post_parent = %d AND p.post_type = %s ORDER BY p.ID',
				$wpdb->posts,
				$wpdb->postmeta,
				\Conformita_Core_Allegati::MARCA,
				'1',
				$wpdb->postmeta,
				'_wp_attached_file',
				$atto_id,
				'attachment'
			),
			array( 'ID', 'file' )
		);

		if ( null === $righe ) {
			return null;
		}

		$caricamenti = wp_get_upload_dir();
		$documenti   = array();

		foreach ( $righe as $riga ) {
			$file = (string) $riga['file'];

			// Come get_attached_file(): un percorso relativo e' dentro la cartella dei caricamenti.
			if ( '' !== $file && ! path_is_absolute( $file ) ) {
				$file = $caricamenti['basedir'] . '/' . $file;
			}

			$documenti[ (int) $riga['ID'] ] = $file;
		}

		return $documenti;
	}

	/**
	 * Il file sta nella cartella protetta dei documenti: fuori da li' non si cancella niente.
	 *
	 * @param string $file Percorso.
	 * @return bool
	 */
	private static function file_dei_documenti( string $file ): bool {
		$cartella = realpath( \Conformita_Core_Allegati::cartella() );
		$dove     = realpath( dirname( $file ) );

		return is_string( $cartella ) && is_string( $dove ) && 0 === strpos( $dove . '/', rtrim( $cartella, '/' ) . '/' );
	}

	/**
	 * La bozza dell'atto con oggetto, origine, tipo, numero proprio e dati del procedimento.
	 *
	 * Ogni dato si rilegge: un valore che questo albo non conserverebbe alla
	 * lettera ferma l'atto invece di arrivare cambiato.
	 *
	 * Un rifiuto o un'eccezione lasciano la bozza dov'e': la toglie chi chiama,
	 * ritrovandola dal nome, e se non ci riesce l'esito e' incerto.
	 *
	 * @param array<string, mixed> $atto  Atto del rapporto.
	 * @param string               $segno Segno casuale della lavorazione, gia' salvato.
	 * @return int|\WP_Error
	 */
	private static function prepara_bozza( array $atto, string $segno ) {
		$atto_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => TIPO,
					'post_status'  => 'draft',
					'post_title'   => (string) $atto['oggetto'],
					'post_name'    => self::nome_bozza( $segno ),
					'post_content' => '',
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);

		if ( is_wp_error( $atto_id ) || 0 === $atto_id ) {
			return new \WP_Error( 'albo_importazione_bozza', __( 'L\'atto non e\' stato importato: la bozza non si e\' creata.', 'albo-pretorio-pa' ) );
		}

		return self::completa_bozza( (int) $atto_id, $atto );
	}

	/**
	 * Scrive nella bozza appena creata origine, tipo, numero proprio e dati del procedimento.
	 *
	 * @param int                  $atto_id Bozza.
	 * @param array<string, mixed> $atto    Atto del rapporto.
	 * @return int|\WP_Error
	 */
	private static function completa_bozza( int $atto_id, array $atto ) {
		// Il ricordo dell'origine per primo: se il blocco si ferma da qui in poi, il rilancio riconosce la bozza.
		$errore = null;

		if ( false === add_post_meta( $atto_id, META_ORIGINE_IMPORTAZIONE, (string) $atto['id'], true ) ) {
			$errore = __( 'l\'identificativo d\'origine non si e\' scritto', 'albo-pretorio-pa' );
		}

		clean_post_cache( $atto_id );

		if ( null === $errore && get_post( $atto_id )->post_title !== (string) $atto['oggetto'] ) {
			$errore = __( 'l\'oggetto non si conserverebbe alla lettera', 'albo-pretorio-pa' );
		}

		if ( null === $errore ) {
			$voci = wp_set_object_terms( $atto_id, array( (int) $atto['tipo_id'] ), TASSONOMIA_TIPO_ATTO );
			$tipo = DatiAtto::tipo( $atto_id );

			if ( is_wp_error( $voci ) || null === $tipo || (int) $atto['tipo_id'] !== $tipo->term_id ) {
				$errore = __( 'il tipo di atto non si e\' assegnato', 'albo-pretorio-pa' );
			}
		}

		$riferimento = (string) $atto['scrittura']['riferimento'];

		if ( null === $errore && '' !== $riferimento ) {
			update_post_meta( $atto_id, META_NUMERO_PROPRIO, wp_slash( $riferimento ) );

			if ( DatiAtto::numero_proprio( $atto_id ) !== $riferimento ) {
				$errore = __( 'il numero proprio non si conserverebbe alla lettera', 'albo-pretorio-pa' );
			}
		}

		foreach ( (array) $atto['scrittura']['procedimento'] as $dato => $valore ) {
			if ( null !== $errore ) {
				break;
			}

			update_post_meta( $atto_id, DatiProcedimento::meta( (string) $dato ), wp_slash( (string) $valore ) );

			if ( DatiProcedimento::valore( $atto_id, (string) $dato ) !== $valore ) {
				$errore = sprintf(
					/* translators: %s: nome del dato. */
					__( '%s non si conserverebbe alla lettera', 'albo-pretorio-pa' ),
					DatiProcedimento::etichetta( (string) $dato )
				);
			}
		}

		/*
		 * La prima versione della bozza, come la lascia il passaggio in verifica
		 * di un atto scritto a mano: il passaggio a pubblicato non cambia oggetto
		 * ne' testo, e quindi non ne aggiunge un'altra.
		 */
		if ( null === $errore && wp_revisions_enabled( get_post( $atto_id ) ) && ! is_int( wp_save_post_revision( $atto_id ) ) ) {
			$errore = __( 'la prima versione della bozza non si e\' salvata', 'albo-pretorio-pa' );
		}

		if ( null !== $errore ) {
			return new \WP_Error(
				'albo_importazione_dato',
				sprintf(
					/* translators: %s: il dato che non si e' scritto. */
					__( 'L\'atto non e\' stato importato: %s.', 'albo-pretorio-pa' ),
					$errore
				)
			);
		}

		return $atto_id;
	}

	/**
	 * Deposita i documenti dell'atto, copiati dall'archivio e ricontrollati con l'impronta.
	 *
	 * @param int                  $atto_id  Bozza.
	 * @param array<string, mixed> $atto     Atto del rapporto.
	 * @param string               $percorso Backup.
	 * @param array<string, mixed> $letto    Backup letto.
	 * @return array{principale: int|null, titoli: array<int, string>, ruoli: array<int, string>, impronte: array<int, string>}|\WP_Error
	 */
	private static function deposita_documenti( int $atto_id, array $atto, string $percorso, array $letto ) {
		$principale = self::principale( $atto );
		$esito      = array(
			'principale' => $principale,
			'titoli'     => array(),
			'ruoli'      => array(),
			'impronte'   => array(),
		);
		$attesi     = array(
			'principale' => null,
			'ulteriori'  => array(),
		);

		// Il principale per primo: gli allegati ulteriori restano nell'ordine del backup.
		$ordine = array_keys( $atto['documenti'] );

		if ( null !== $principale ) {
			$ordine = array_merge( array( $principale ), array_values( array_diff( $ordine, array( $principale ) ) ) );
		}

		foreach ( $ordine as $posizione ) {
			$documento = $atto['documenti'][ $posizione ];
			$nome      = (string) $documento['file'];
			$atteso    = (string) $documento['impronta'];

			if ( '' === $nome || '' === $atteso || ! isset( $letto['file'][ $nome ] ) || ! hash_equals( (string) $letto['file'][ $nome ]['impronta'], $atteso ) ) {
				return new \WP_Error( 'albo_importazione_file', __( 'L\'atto non e\' stato importato: un suo file non e\' nell\'archivio come nel rapporto.', 'albo-pretorio-pa' ) );
			}

			$temporaneo = self::estrai( $percorso, $nome, $atteso );

			if ( is_wp_error( $temporaneo ) ) {
				return $temporaneo;
			}

			$ruolo = $posizione === $principale ? DocumentiAtto::PRINCIPALE : DocumentiAtto::ULTERIORE;

			try {
				$allegato = DocumentiAtto::deposita(
					$atto_id,
					array(
						'name'     => wp_basename( $nome ),
						'tmp_name' => $temporaneo,
						'type'     => '',
						'size'     => (int) filesize( $temporaneo ),
						'error'    => 0,
					),
					$ruolo,
					'percorso_locale',
					true
				);
			} finally {
				if ( file_exists( $temporaneo ) ) {
					wp_delete_file( $temporaneo );
				}
			}

			if ( is_wp_error( $allegato ) ) {
				return new \WP_Error(
					'albo_importazione_deposito',
					sprintf(
						/* translators: %s: motivo del meccanismo comune. */
						__( 'L\'atto non e\' stato importato: un documento non si e\' depositato. %s', 'albo-pretorio-pa' ),
						$allegato->get_error_message()
					)
				);
			}

			$impronta = conformita_core_impronta_allegato( (int) $allegato );

			// Il file depositato deve essere quello dell'archivio, byte per byte.
			if ( ! is_array( $impronta ) || 'sha256' !== strtolower( (string) ( $impronta['algoritmo'] ?? '' ) ) || ! hash_equals( $atteso, strtolower( (string) ( $impronta['valore'] ?? '' ) ) ) ) {
				return new \WP_Error( 'albo_importazione_impronta', __( 'L\'atto non e\' stato importato: un documento depositato non ha la stessa impronta del file nell\'archivio.', 'albo-pretorio-pa' ) );
			}

			$esito['titoli'][]   = (string) $documento['titolo'];
			$esito['ruoli'][]    = $ruolo;
			$esito['impronte'][] = $atteso;

			if ( DocumentiAtto::PRINCIPALE === $ruolo ) {
				$attesi['principale'] = (int) $allegato;
			} else {
				$attesi['ulteriori'][] = (int) $allegato;
			}
		}

		// Ogni deposito e' riuscito: l'atto deve elencare proprio quei documenti, nell'ordine. Una lettura mancata in mezzo puo' averne perso uno.
		if ( self::collegamenti( $atto_id ) !== $attesi ) {
			return new \WP_Error( 'albo_importazione_collegamenti', __( 'L\'atto non e\' stato importato: dopo il deposito i documenti indicati dall\'atto non sono tutti e soli quelli depositati.', 'albo-pretorio-pa' ) );
		}

		return $esito;
	}

	/**
	 * Il documento principale e gli allegati ulteriori come li indica la bozza, letti dalla banca dati com'e'.
	 *
	 * Nulla se la lettura non risponde o i dati non hanno la forma attesa: per
	 * il confronto con i documenti depositati vale come una differenza.
	 *
	 * @param int $atto_id Bozza.
	 * @return array{principale: int|null, ulteriori: array<int, int>}|null
	 */
	private static function collegamenti( int $atto_id ): ?array {
		global $wpdb;

		$righe = Lettura::righe(
			$wpdb->prepare( 'SELECT meta_key, meta_value FROM %i WHERE post_id = %d AND meta_key IN ( %s, %s ) ORDER BY meta_id', $wpdb->postmeta, $atto_id, META_DOCUMENTO_PRINCIPALE, META_ALLEGATI_ULTERIORI ),
			array( 'meta_key', 'meta_value' )
		);

		if ( null === $righe ) {
			return null;
		}

		$valori = array();

		foreach ( $righe as $riga ) {
			// Una riga sola per dato: due righe sarebbero un dato che WordPress non sa leggere.
			if ( isset( $valori[ $riga['meta_key'] ] ) ) {
				return null;
			}

			$valori[ $riga['meta_key'] ] = maybe_unserialize( (string) $riga['meta_value'] );
		}

		$principale = $valori[ META_DOCUMENTO_PRINCIPALE ] ?? null;
		$ulteriori  = $valori[ META_ALLEGATI_ULTERIORI ] ?? array();

		if ( ( null !== $principale && ! is_numeric( $principale ) ) || ! is_array( $ulteriori ) ) {
			return null;
		}

		return array(
			'principale' => null === $principale ? null : (int) $principale,
			'ulteriori'  => array_map( 'intval', array_values( $ulteriori ) ),
		);
	}

	/**
	 * Copia un file dell'archivio in un file temporaneo, controllandone l'impronta.
	 *
	 * @param string $percorso Backup.
	 * @param string $nome     Nome del file nell'archivio.
	 * @param string $atteso   Impronta attesa.
	 * @return string|\WP_Error Percorso del file temporaneo.
	 */
	private static function estrai( string $percorso, string $nome, string $atteso ) {
		$errore     = new \WP_Error( 'albo_importazione_estrazione', __( 'L\'atto non e\' stato importato: un suo file non si e\' copiato dall\'archivio.', 'albo-pretorio-pa' ) );
		$temporaneo = wp_tempnam( wp_basename( $nome ) );

		if ( '' === $temporaneo ) {
			return $errore;
		}

		// Il temporaneo passa a chi chiama solo con il file intero: ogni altra uscita lo cancella qui.
		$archivio = new \ZipArchive();
		$aperto   = false;
		$flusso   = false;
		$uscita   = false;
		$riuscito = false;

		try {
			$aperto = true === $archivio->open( $percorso, \ZipArchive::RDONLY );

			if ( ! $aperto ) {
				return $errore;
			}

			$flusso = $archivio->getStream( $nome );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- si copia a pezzi un file che puo' essere grande.
			$uscita = fopen( $temporaneo, 'wb' );

			if ( false === $flusso || false === $uscita ) {
				return $errore;
			}

			$calcolo = hash_init( 'sha256' );
			$intero  = true;

			while ( ! feof( $flusso ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- lettura a pezzi del flusso dell'archivio.
				$pezzo = fread( $flusso, LettoreBackup::PEZZO );

				if ( false === $pezzo ) {
					$intero = false;
					break;
				}

				hash_update( $calcolo, $pezzo );

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- scrittura a pezzi del file temporaneo.
				if ( strlen( $pezzo ) !== fwrite( $uscita, $pezzo ) ) {
					$intero = false;
					break;
				}
			}

			if ( ! $intero || ! hash_equals( $atteso, hash_final( $calcolo ) ) ) {
				return $errore;
			}

			$riuscito = true;

			return $temporaneo;
		} finally {
			// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura dei flussi aperti qui.
			if ( is_resource( $flusso ) ) {
				fclose( $flusso );
			}

			if ( is_resource( $uscita ) ) {
				fclose( $uscita );
			}
			// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			if ( $aperto ) {
				$archivio->close();
			}

			if ( ! $riuscito && file_exists( $temporaneo ) ) {
				wp_delete_file( $temporaneo );
			}
		}
	}

	/**
	 * Prende il turno dell'importazione, senza attendere.
	 *
	 * @return true|\WP_Error
	 */
	private static function prendi_turno() {
		global $wpdb;

		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', self::nome_turno() ) ) ) {
			return new \WP_Error( 'albo_importazione_occupata', __( 'Un\'altra richiesta sta lavorando all\'importazione in questo momento. Si riprova fra poco.', 'albo-pretorio-pa' ) );
		}

		return true;
	}

	/**
	 * Lascia il turno dell'importazione.
	 */
	private static function lascia_turno(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', self::nome_turno() ) );
	}

	/**
	 * Il rifiuto per il permesso.
	 *
	 * @return \WP_Error
	 */
	private static function errore_permesso(): \WP_Error {
		return new \WP_Error( 'albo_importazione_permesso', __( 'Non si possiede il permesso di importare gli atti di Albo Pretorio On line.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Il rifiuto per un rapporto diverso da quello confermato.
	 *
	 * @return \WP_Error
	 */
	private static function errore_cambiato(): \WP_Error {
		return new \WP_Error( 'albo_importazione_rapporto_cambiato', __( 'Importazione non avviata: il backup o il rapporto sono cambiati da quando sono stati letti. Si rilegge il backup e si conferma di nuovo.', 'albo-pretorio-pa' ) );
	}
}
