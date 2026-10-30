<?php
/**
 * L'esportazione di un anno del registro per la conservazione (ALBO-17).
 *
 * Un archivio ZIP con i dati di ogni atto numerato nell'anno, tutti i suoi
 * documenti, anche le versioni sostituite da un oscuramento, e la sua storia
 * dal registro delle modifiche. Non e' un pacchetto di versamento per un
 * conservatore: e' il materiale da cui chi conserva lo prepara. Ogni lettura
 * che non risponde ferma l'esportazione: un archivio incompleto consegnato
 * come completo sarebbe peggio di nessun archivio.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Raccolta, costruzione e lettura dell'archivio.
 */
final class Esportazione {

	/**
	 * Nome del formato, scritto nei dati.
	 *
	 * @var string
	 */
	const FORMATO = 'albo-pretorio-esportazione';

	/**
	 * Versione del formato: cambia solo se cambia la forma dei dati.
	 *
	 * @var int
	 */
	const VERSIONE_FORMATO = 1;

	/**
	 * Il file dei dati dentro l'archivio.
	 *
	 * @var string
	 */
	const FILE_DATI = 'atti.json';

	/**
	 * L'indice da foglio di calcolo dentro l'archivio.
	 *
	 * @var string
	 */
	const FILE_INDICE = 'atti.csv';

	/**
	 * L'elenco delle impronte dentro l'archivio.
	 *
	 * @var string
	 */
	const FILE_IMPRONTE = 'impronte.sha256';

	/**
	 * Azione della voce di registro di ogni esportazione.
	 *
	 * @var string
	 */
	const AZIONE = 'esportazione';

	/**
	 * Gli stati esportati, con il loro nome nei dati.
	 *
	 * @var array<string, string>
	 */
	const STATI = array(
		ChiusuraPubblicazione::PUBBLICATO => 'pubblicato',
		ChiusuraPubblicazione::DEFISSO    => 'defisso',
		ChiusuraPubblicazione::ANNULLATO  => 'annullato',
	);

	/**
	 * Gli stati di un atto che ha preso un numero e poi l'ha perso: non c'e' niente da esportare.
	 *
	 * @var array<int, string>
	 */
	const STATI_SENZA_ATTO = array( 'draft', 'auto-draft', 'trash', ChiusuraPubblicazione::IN_VERIFICA );

	/**
	 * Gli archivi costruiti in questa richiesta, da cancellare alla sua chiusura.
	 *
	 * @var array<int, string>
	 */
	private static $da_cancellare = array();

	/**
	 * Le colonne dell'indice.
	 *
	 * @var array<int, string>
	 */
	const COLONNE_INDICE = array( 'anno', 'numero', 'stato', 'fine_passata', 'oggetto', 'tipo', 'organo', 'data_adozione', 'numero_proprio', 'amministrazione', 'inizio', 'fine', 'documenti' );

	/**
	 * Gli anni che hanno atti numerati, dal piu' recente; nullo se la lettura non risponde.
	 *
	 * @return array<int, int>|null
	 */
	public static function anni(): ?array {
		global $wpdb;

		if ( ! Repertorio::tabelle_presenti() ) {
			return null;
		}

		$righe = Lettura::righe( $wpdb->prepare( 'SELECT DISTINCT anno FROM %i ORDER BY anno DESC', Repertorio::tabella_assegnazioni() ), array( 'anno' ) );

		return null === $righe ? null : array_map( 'intval', array_column( $righe, 'anno' ) );
	}

	/**
	 * I dati dell'anno, senza costruire l'archivio.
	 *
	 * @param int $anno Anno del registro.
	 * @return array{dati: array<string, mixed>, file: array<string, string>}|\WP_Error I dati e, per ogni percorso nell'archivio, il file sul disco.
	 */
	public static function raccogli( int $anno ) {
		global $wpdb;

		if ( ! Repertorio::tabelle_presenti() ) {
			return self::errore_lettura( __( 'il registro dei numeri', 'albo-pretorio-pa' ) );
		}

		// Tipo e stato di ogni atto numerato in una sola lettura: un atto che manca va distinto da una lettura che non ha risposto.
		$atti = Lettura::righe(
			$wpdb->prepare( 'SELECT a.atto_id, p.post_type, p.post_status FROM %i a LEFT JOIN %i p ON p.ID = a.atto_id WHERE a.anno = %d', Repertorio::tabella_assegnazioni(), $wpdb->posts, $anno ),
			array( 'atto_id', 'post_type', 'post_status' )
		);

		if ( null === $atti ) {
			return self::errore_lettura( __( 'il registro dei numeri', 'albo-pretorio-pa' ) );
		}

		$elenco = array();
		$file   = array();

		foreach ( $atti as $riga ) {
			$atto_id = (int) $riga['atto_id'];

			/*
			 * Un numero preso e poi perso resta nel registro: la bozza cancellata
			 * (nessuna riga), rimessa in bozza o nel cestino, o la pubblicazione
			 * fallita dopo il numero, che lascia l'atto in verifica. Non c'e' niente
			 * da conservare. Ogni altro stato che non sia dell'albo ferma tutto.
			 */
			if ( null === $riga['post_type'] || in_array( $riga['post_status'], self::STATI_SENZA_ATTO, true ) ) {
				continue;
			}

			if ( TIPO !== $riga['post_type'] || ! isset( self::STATI[ $riga['post_status'] ] ) ) {
				return new \WP_Error(
					'albo_esportazione_stato',
					/* translators: %d: identificativo dell'atto. */
					sprintf( __( 'Niente e\' stato esportato: il contenuto %d ha un numero dell\'albo ma non e\' un atto pubblicato, tolto o annullato. Va controllato prima di esportare.', 'albo-pretorio-pa' ), $atto_id )
				);
			}

			$atto = get_post( $atto_id );

			if ( ! $atto instanceof \WP_Post ) {
				return self::errore_lettura( __( 'un atto dell\'anno', 'albo-pretorio-pa' ) );
			}

			$dati = self::atto( $atto );

			if ( is_wp_error( $dati ) ) {
				return $dati;
			}

			$elenco[] = $dati['dati'];
			$file     = array_merge( $file, $dati['file'] );
		}

		if ( array() === $elenco ) {
			return new \WP_Error(
				'albo_esportazione_vuota',
				/* translators: %d: anno. */
				sprintf( __( 'Niente da esportare: nel %d non c\'e\' nessun atto numerato.', 'albo-pretorio-pa' ), $anno )
			);
		}

		usort(
			$elenco,
			static function ( array $a, array $b ): int {
				return $a['numero'] <=> $b['numero'];
			}
		);

		return array(
			'dati' => array(
				'formato'  => self::FORMATO,
				'versione' => self::VERSIONE_FORMATO,
				'anno'     => $anno,
				'generato' => current_datetime()->format( 'c' ),
				'fuso'     => wp_timezone_string(),
				'atti'     => $elenco,
			),
			'file' => $file,
		);
	}

	/**
	 * Costruisce l'archivio dell'anno in un file temporaneo, per chi puo' consultare gli scaduti.
	 *
	 * Chi chiama consegna il file e lo cancella.
	 *
	 * @param int $anno Anno del registro.
	 * @return array{percorso: string, atti: int, documenti: int, impronta: string}|\WP_Error
	 */
	public static function costruisci( int $anno ) {
		$permesso = SchermataScaduti::permesso();

		if ( null === $permesso || ! current_user_can( $permesso ) ) {
			return new \WP_Error( 'albo_esportazione_non_permessa', __( 'Niente e\' stato esportato: l\'esportazione contiene atti scaduti, e serve il permesso di consultarli.', 'albo-pretorio-pa' ) );
		}

		if ( ! class_exists( \ZipArchive::class ) ) {
			return new \WP_Error( 'albo_esportazione_senza_zip', __( 'Niente e\' stato esportato: il server non ha l\'estensione di PHP che scrive gli archivi ZIP. La si chiede a chi gestisce il server.', 'albo-pretorio-pa' ) );
		}

		$raccolta = self::raccogli( $anno );

		if ( is_wp_error( $raccolta ) ) {
			return $raccolta;
		}

		$percorso = wp_tempnam( 'albo-esportazione-' . $anno . '.zip' );
		$archivio = new \ZipArchive();

		/*
		 * L'archivio contiene dati personali e non deve restare sul server. Chi
		 * chiama lo cancella dopo la consegna, ma la richiesta puo' finire prima:
		 * il browser che chiude la connessione, il tempo massimo del server. La
		 * chiusura della richiesta lo cancella comunque.
		 */
		if ( array() === self::$da_cancellare ) {
			register_shutdown_function( array( self::class, 'cancella_esportazioni_rimaste' ) );
		}

		self::$da_cancellare[] = $percorso;

		if ( true !== $archivio->open( $percorso, \ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $percorso );

			return self::errore_scrittura();
		}

		$dati      = (string) wp_json_encode( $raccolta['dati'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$indice    = self::indice( $raccolta['dati']['atti'] );
		$impronte  = array(
			self::FILE_DATI   => hash( 'sha256', $dati ),
			self::FILE_INDICE => hash( 'sha256', $indice ),
		);
		$scritto   = $archivio->addFromString( self::FILE_DATI, $dati ) && $archivio->addFromString( self::FILE_INDICE, $indice );
		$documenti = 0;

		foreach ( $raccolta['file'] as $nome => $sorgente ) {
			$scritto           = $scritto && $archivio->addFile( $sorgente, $nome );
			$impronte[ $nome ] = (string) hash_file( 'sha256', $sorgente );
			++$documenti;
		}

		$righe = '';

		foreach ( $impronte as $nome => $impronta ) {
			$righe .= $impronta . '  ' . $nome . "\n";
		}

		$scritto = $scritto && $archivio->addFromString( self::FILE_IMPRONTE, $righe );

		if ( ! $scritto || ! $archivio->close() ) {
			wp_delete_file( $percorso );

			return self::errore_scrittura();
		}

		$impronta = (string) hash_file( 'sha256', $percorso );
		$voce     = conformita_core_registra_voce(
			array(
				'sezione'  => SEZIONE,
				'azione'   => self::AZIONE,
				'dettagli' => array(
					'anno'      => $anno,
					'atti'      => count( $raccolta['dati']['atti'] ),
					'documenti' => $documenti,
					'impronta'  => 'sha256:' . $impronta,
				),
			)
		);

		// Un'uscita in blocco di dati personali che non si registra non si consegna.
		if ( is_wp_error( $voce ) ) {
			wp_delete_file( $percorso );

			return new \WP_Error( 'albo_esportazione_non_registrata', __( 'Niente e\' stato esportato: il registro delle modifiche non ha accettato la voce dell\'esportazione. Riprovare piu\' tardi.', 'albo-pretorio-pa' ) );
		}

		return array(
			'percorso'  => $percorso,
			'atti'      => count( $raccolta['dati']['atti'] ),
			'documenti' => $documenti,
			'impronta'  => $impronta,
		);
	}

	/**
	 * Cancella gli archivi costruiti nella richiesta e rimasti sul disco.
	 *
	 * Si aggancia da se' alla chiusura della richiesta.
	 */
	public static function cancella_esportazioni_rimaste(): void {
		foreach ( self::$da_cancellare as $percorso ) {
			if ( file_exists( $percorso ) ) {
				wp_delete_file( $percorso );
			}
		}

		self::$da_cancellare = array();
	}

	/**
	 * Rilegge un archivio: controlla ogni impronta e restituisce i dati.
	 *
	 * @param string $percorso Archivio.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function leggi( string $percorso ) {
		if ( ! class_exists( \ZipArchive::class ) ) {
			return new \WP_Error( 'albo_esportazione_senza_zip', __( 'L\'archivio non si legge: il server non ha l\'estensione di PHP che legge gli archivi ZIP.', 'albo-pretorio-pa' ) );
		}

		$archivio = new \ZipArchive();

		if ( true !== $archivio->open( $percorso, \ZipArchive::RDONLY ) ) {
			return self::errore_archivio( __( 'non e\' un archivio ZIP leggibile.', 'albo-pretorio-pa' ) );
		}

		try {
			$elenco = $archivio->getFromName( self::FILE_IMPRONTE );

			if ( ! is_string( $elenco ) ) {
				return self::errore_archivio( __( 'manca l\'elenco delle impronte.', 'albo-pretorio-pa' ) );
			}

			$elencati = array();

			foreach ( explode( "\n", trim( $elenco ) ) as $riga ) {
				if ( 1 !== preg_match( '/\A([0-9a-f]{64})  (.+)\z/', $riga, $parti ) ) {
					return self::errore_archivio( __( 'l\'elenco delle impronte ha una riga che non si legge.', 'albo-pretorio-pa' ) );
				}

				$contenuto = $archivio->getFromName( $parti[2] );

				if ( ! is_string( $contenuto ) || ! hash_equals( $parti[1], hash( 'sha256', $contenuto ) ) ) {
					/* translators: %s: nome del file nell'archivio. */
					return self::errore_archivio( sprintf( __( 'il file %s manca o non corrisponde alla sua impronta.', 'albo-pretorio-pa' ), $parti[2] ) );
				}

				$elencati[ $parti[2] ] = true;
			}

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- proprieta' di ZipArchive.
			for ( $i = 0; $i < $archivio->numFiles; $i++ ) {
				$nome = (string) $archivio->getNameIndex( $i );

				if ( self::FILE_IMPRONTE !== $nome && ! isset( $elencati[ $nome ] ) ) {
					/* translators: %s: nome del file nell'archivio. */
					return self::errore_archivio( sprintf( __( 'il file %s non e\' nell\'elenco delle impronte.', 'albo-pretorio-pa' ), $nome ) );
				}
			}

			$dati = json_decode( (string) $archivio->getFromName( self::FILE_DATI ), true );
		} finally {
			$archivio->close();
		}

		if ( ! is_array( $dati ) || self::FORMATO !== ( $dati['formato'] ?? null ) || self::VERSIONE_FORMATO !== ( $dati['versione'] ?? null ) || ! is_array( $dati['atti'] ?? null ) ) {
			return self::errore_archivio( __( 'i dati non sono nella forma di questa esportazione.', 'albo-pretorio-pa' ) );
		}

		foreach ( $dati['atti'] as $atto ) {
			foreach ( (array) ( $atto['documenti'] ?? array() ) as $documento ) {
				if ( ! isset( $elencati[ $documento['file'] ?? '' ] ) ) {
					return self::errore_archivio( __( 'un documento dei dati non e\' nell\'archivio.', 'albo-pretorio-pa' ) );
				}
			}
		}

		return $dati;
	}

	/**
	 * I dati di un atto e i suoi file.
	 *
	 * @param \WP_Post $atto Atto.
	 * @return array{dati: array<string, mixed>, file: array<string, string>}|\WP_Error
	 */
	private static function atto( \WP_Post $atto ) {
		global $wpdb;

		$atto_id = (int) $atto->ID;
		$numero  = Repertorio::di( $atto_id );
		$fine    = conformita_core_fine_pubblicazione( $atto_id );

		if ( null === $numero || ! is_string( $fine ) ) {
			return self::errore_lettura( __( 'il numero o la fine di un atto', 'albo-pretorio-pa' ) );
		}

		$voci = conformita_core_voci_registro(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $atto_id,
			)
		);

		// Il meccanismo comune risponde con un elenco vuoto anche a una lettura fallita: l'errore si legge dalla connessione.
		if ( ! is_array( $voci ) || '' !== (string) $wpdb->last_error ) {
			return self::errore_lettura( __( 'il registro delle modifiche', 'albo-pretorio-pa' ) );
		}

		$documenti = self::documenti( $atto_id, $numero );

		if ( is_wp_error( $documenti ) ) {
			return $documenti;
		}

		$tipo            = DatiAtto::tipo( $atto_id );
		$organo          = DatiAtto::organo( $atto_id );
		$ospitata        = AmministrazioniOspitate::di_atto( $atto_id );
		$inizio          = get_post_datetime( $atto, 'date', 'gmt' );
		$origine         = (string) get_post_meta( $atto_id, META_ORIGINE_IMPORTAZIONE, true );
		$procedimento    = DatiProcedimento::per_registro( $atto_id );
		$amministrazione = null === $ospitata ? null : ( $ospitata['leggibile'] ? $ospitata['nome'] : '' );

		return array(
			'dati' => array(
				'anno'                 => $numero['anno'],
				'numero'               => $numero['numero'],
				'stato'                => self::STATI[ $atto->post_status ],
				'fine_passata'         => (bool) conformita_core_scaduto( $atto_id ),
				'oggetto'              => $atto->post_title,
				'tipo'                 => null === $tipo ? '' : $tipo->name,
				'organo'               => null === $organo ? '' : $organo->name,
				'data_adozione'        => (string) DatiAtto::data_adozione( $atto_id ),
				'numero_proprio'       => DatiAtto::numero_proprio( $atto_id ),
				'amministrazione'      => $amministrazione,
				'procedimento'         => $procedimento,
				'inizio'               => false === $inizio ? '' : $inizio->setTimezone( wp_timezone() )->format( 'c' ),
				'fine'                 => $fine,
				'origine_importazione' => '' === $origine ? null : $origine,
				'documenti'            => $documenti['dati'],
				'registro'             => array_map( array( self::class, 'voce' ), $voci ),
			),
			'file' => $documenti['file'],
		);
	}

	/**
	 * I documenti indicati dall'atto e le versioni sostituite, con impronta e file.
	 *
	 * L'impronta e' quella calcolata al deposito: il file sul disco deve averla
	 * ancora, altrimenti l'esportazione si ferma.
	 *
	 * @param int                           $atto_id Atto.
	 * @param array{anno: int, numero: int} $numero  Numero di registro.
	 * @return array{dati: array<int, array<string, mixed>>, file: array<string, string>}|\WP_Error
	 */
	private static function documenti( int $atto_id, array $numero ) {
		$indicati = DocumentiAtto::indicati( $atto_id );

		if ( null === $indicati ) {
			return self::errore_lettura( __( 'i documenti di un atto', 'albo-pretorio-pa' ) );
		}

		$cartella   = sprintf( 'documenti/%d-%04d/', $numero['anno'], $numero['numero'] );
		$elenco     = array();
		$principale = DocumentiAtto::principale( $atto_id );

		foreach ( array_values( DocumentiAtto::ulteriori( $atto_id ) ) as $posizione => $ulteriore ) {
			$elenco[ $ulteriore ] = array( 'allegato', $posizione + 1 );
		}

		if ( null !== $principale ) {
			$elenco = array( $principale => array( 'principale', 0 ) ) + $elenco;
		}

		global $wpdb;

		// I figli e il loro segno di sostituzione in una sola lettura: una risposta mancata non e' "nessuna versione sostituita".
		$figli = Lettura::righe(
			$wpdb->prepare(
				'SELECT p.ID, m.meta_value FROM %i p LEFT JOIN %i m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_parent = %d AND p.post_type = %s ORDER BY p.ID, m.meta_id',
				$wpdb->posts,
				$wpdb->postmeta,
				META_SOSTITUITO_DA,
				$atto_id,
				'attachment'
			),
			array( 'ID', 'meta_value' )
		);

		if ( null === $figli ) {
			return self::errore_lettura( __( 'i documenti di un atto', 'albo-pretorio-pa' ) );
		}

		$segni = array();

		foreach ( $figli as $figlio ) {
			if ( null !== $figlio['meta_value'] ) {
				$segni[ (int) $figlio['ID'] ][] = (string) $figlio['meta_value'];
			}
		}

		$sostituite = 0;

		foreach ( array_keys( $segni ) as $figlio ) {
			if ( ! isset( $elenco[ $figlio ] ) ) {
				$elenco[ $figlio ] = array( 'sostituito', ++$sostituite );
			}
		}

		$dati = array();
		$file = array();

		foreach ( $elenco as $documento => list( $posto, $posizione ) ) {
			$sorgente = get_attached_file( $documento );
			$impronta = conformita_core_impronta_allegato( $documento );

			if ( ! is_string( $sorgente ) || ! is_readable( $sorgente ) || is_wp_error( $impronta ) || ! hash_equals( (string) $impronta['valore'], (string) hash_file( (string) $impronta['algoritmo'], $sorgente ) ) ) {
				return new \WP_Error(
					'albo_esportazione_documento',
					/* translators: 1: anno, 2: numero di registro. */
					sprintf( __( 'Niente e\' stato esportato: un documento dell\'atto %2$d/%1$d non si legge dal disco, oppure non corrisponde piu\' all\'impronta presa al deposito.', 'albo-pretorio-pa' ), $numero['anno'], $numero['numero'] )
				);
			}

			$estensione = strtolower( (string) pathinfo( $sorgente, PATHINFO_EXTENSION ) );
			$nome       = 'sostituito' === $posto ? 'versione-sostituita-' . $posizione : (string) NomeScaricato::nome( $documento, $atto_id );
			$nome       = 'sostituito' === $posto && '' !== $estensione ? $nome . '.' . $estensione : $nome;
			$segno      = $segni[ $documento ] ?? array();
			$sostituto  = 'sostituito' === $posto && 1 === count( $segno ) && 1 === preg_match( '/\A[1-9][0-9]*\z/', $segno[0] ) ? (int) $segno[0] : null;

			$file[ $cartella . $nome ] = $sorgente;
			$dati[]                    = array(
				'posto'         => $posto,
				'posizione'     => $posizione,
				'file'          => $cartella . $nome,
				'impronta'      => array(
					'algoritmo' => (string) $impronta['algoritmo'],
					'valore'    => (string) $impronta['valore'],
				),
				'dimensione'    => (int) filesize( $sorgente ),
				'tipo_mime'     => (string) get_post_mime_type( $documento ),
				'deposito'      => (string) $impronta['deposito'],
				'sostituito_da' => null === $sostituto ? null : self::riferimento( $sostituto, $elenco, $cartella, $atto_id ),
			);
		}

		return array(
			'dati' => $dati,
			'file' => $file,
		);
	}

	/**
	 * Il file nell'archivio del documento che ne ha sostituito un altro.
	 *
	 * @param int                                  $sostituto Documento entrante.
	 * @param array<int, array{0: string, 1: int}> $elenco    Documenti dell'atto con il loro posto.
	 * @param string                               $cartella  Cartella dell'atto nell'archivio.
	 * @param int                                  $atto_id   Atto.
	 * @return string|null
	 */
	private static function riferimento( int $sostituto, array $elenco, string $cartella, int $atto_id ): ?string {
		if ( ! isset( $elenco[ $sostituto ] ) ) {
			return null;
		}

		if ( 'sostituito' !== $elenco[ $sostituto ][0] ) {
			return $cartella . NomeScaricato::nome( $sostituto, $atto_id );
		}

		$estensione = strtolower( (string) pathinfo( (string) get_attached_file( $sostituto ), PATHINFO_EXTENSION ) );

		return $cartella . 'versione-sostituita-' . $elenco[ $sostituto ][1] . ( '' === $estensione ? '' : '.' . $estensione );
	}

	/**
	 * Una voce del registro nella forma dei dati esportati.
	 *
	 * @param array<string, mixed> $voce Voce del meccanismo comune.
	 * @return array<string, mixed>
	 */
	private static function voce( array $voce ): array {
		$autore = get_userdata( (int) $voce['utente'] );

		return array(
			'istante'     => $voce['istante'] instanceof \DateTimeInterface ? $voce['istante']->setTimezone( wp_timezone() )->format( 'c' ) : '',
			'azione'      => (string) $voce['azione'],
			'utente'      => (int) $voce['utente'],
			'autore'      => $autore instanceof \WP_User ? $autore->display_name : '',
			'motivazione' => null === $voce['motivazione'] ? null : (string) $voce['motivazione'],
			'dettagli'    => $voce['dettagli'],
		);
	}

	/**
	 * L'indice da foglio di calcolo, con la virgola e le virgolette dove servono.
	 *
	 * @param array<int, array<string, mixed>> $atti Atti esportati.
	 * @return string
	 */
	private static function indice( array $atti ): string {
		$flusso = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flusso in memoria.

		fputcsv( $flusso, self::COLONNE_INDICE, ',', '"', '' );

		foreach ( $atti as $atto ) {
			$riga = array();

			foreach ( self::COLONNE_INDICE as $colonna ) {
				$valore = 'documenti' === $colonna ? count( $atto['documenti'] ) : ( $atto[ $colonna ] ?? '' );
				$riga[] = is_bool( $valore ) ? ( $valore ? 'si' : 'no' ) : ( is_string( $valore ) ? self::testo_per_foglio( $valore ) : (string) $valore );
			}

			fputcsv( $flusso, $riga, ',', '"', '' );
		}

		rewind( $flusso );
		$testo = (string) stream_get_contents( $flusso );
		fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- come sopra.

		return $testo;
	}

	/**
	 * Un testo dell'indice che un foglio di calcolo non scambia per una formula.
	 *
	 * Chi redige scrive l'oggetto, e un foglio di calcolo esegue come formula
	 * la cella che comincia con uno di questi caratteri. L'apostrofo davanti
	 * la fa leggere come testo; i dati di `atti.json` restano come sono.
	 *
	 * @param string $valore Testo.
	 * @return string
	 */
	private static function testo_per_foglio( string $valore ): string {
		return '' !== $valore && false !== strpos( "=+-@\t\r", $valore[0] ) ? "'" . $valore : $valore;
	}

	/**
	 * Il rifiuto per una lettura che non ha risposto.
	 *
	 * @param string $cosa Cio' che non si e' letto.
	 * @return \WP_Error
	 */
	private static function errore_lettura( string $cosa ): \WP_Error {
		return new \WP_Error(
			'albo_esportazione_lettura',
			/* translators: %s: cio' che non si e' letto. */
			sprintf( __( 'Niente e\' stato esportato: %s non si legge in questo momento. Riprovare piu\' tardi.', 'albo-pretorio-pa' ), $cosa )
		);
	}

	/**
	 * Il rifiuto per un archivio che non si e' potuto scrivere.
	 *
	 * @return \WP_Error
	 */
	private static function errore_scrittura(): \WP_Error {
		return new \WP_Error( 'albo_esportazione_scrittura', __( 'Niente e\' stato esportato: l\'archivio non si e\' potuto scrivere nella cartella temporanea del server.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Il rifiuto per un archivio che non si rilegge.
	 *
	 * @param string $perche Motivo.
	 * @return \WP_Error
	 */
	private static function errore_archivio( string $perche ): \WP_Error {
		/* translators: %s: motivo. */
		return new \WP_Error( 'albo_esportazione_non_integra', sprintf( __( 'L\'archivio non e\' un\'esportazione integra: %s', 'albo-pretorio-pa' ), $perche ) );
	}
}
