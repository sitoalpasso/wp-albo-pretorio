<?php
/**
 * La lettura del backup dell'albo di provenienza (ALBO-35).
 *
 * Il plugin "Albo Pretorio On line" produce dalla sua schermata di utilita' un
 * archivio zip con dentro un file SQL e i file degli allegati. Il file SQL
 * contiene, per ciascuna delle sue tabelle, l'istruzione di creazione e una
 * riga `INSERT INTO ... VALUES (...)` per ogni record, **senza l'elenco delle
 * colonne**: l'ordine delle colonne e' quello della tabella sul sito di
 * provenienza, che dipende dalla storia delle sue versioni, e si ricava quindi
 * dall'istruzione di creazione che precede i dati, mai da un ordine supposto.
 * In fondo ci sono le impostazioni del plugin e due aggiornamenti del contatore.
 *
 * Questa classe **legge e basta**: non esegue il testo SQL, non estrae niente
 * sul disco, non scrive nella banca dati. Il testo SQL si interpreta istruzione
 * per istruzione, e di tutte le tabelle che contiene si conservano soltanto le
 * righe delle tabelle dell'albo e le impostazioni del plugin: una copia intera
 * della banca dati si legge allo stesso modo, e il resto si nomina senza
 * tenerlo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Archivio zip o file SQL, letti in sola lettura.
 */
final class LettoreBackup {

	/**
	 * Le tabelle dell'albo di provenienza, per il nome che segue il prefisso del sito.
	 *
	 * Il registro delle operazioni non c'e': il backup non lo contiene.
	 *
	 * @var array<int, string>
	 */
	const TABELLE = array(
		'albopretorio_atti',
		'albopretorio_allegati',
		'albopretorio_categorie',
		'albopretorio_enti',
		'albopretorio_resprocedura',
		'albopretorio_attimeta',
		'albopretorio_unitaorganizzative',
	);

	/**
	 * Il prefisso delle impostazioni del plugin di provenienza.
	 *
	 * @var string
	 */
	const PREFISSO_OPZIONI = 'opt_AP_';

	/**
	 * La dimensione massima del testo SQL, in byte.
	 *
	 * @var int
	 */
	const SQL_MASSIMO = 536870912;

	/**
	 * La lunghezza massima di una singola istruzione, in byte.
	 *
	 * Il backup del plugin scrive un'istruzione per record; mysqldump le unisce
	 * fino a un megabyte circa.
	 *
	 * @var int
	 */
	const ISTRUZIONE_MASSIMA = 16777216;

	/**
	 * Il totale massimo dei dati conservati, in byte stimati.
	 *
	 * Ogni valore tenuto conta per la sua lunghezza piu' VALORE_COSTO, quanto
	 * costa in memoria un valore anche vuoto.
	 *
	 * @var int
	 */
	const DATI_MASSIMI = 134217728;

	/**
	 * Il costo stimato di un valore conservato, oltre alla sua lunghezza.
	 *
	 * @var int
	 */
	const VALORE_COSTO = 80;

	/**
	 * Il numero massimo di valori in una riga: quello delle colonne di una tabella MySQL.
	 *
	 * @var int
	 */
	const COLONNE_MASSIME = 4096;

	/**
	 * Il numero massimo di file in un archivio.
	 *
	 * @var int
	 */
	const FILE_MASSIMI = 200000;

	/**
	 * Byte oltre i quali due voci con lo stesso nome non si confrontano dove PHP non apre un flusso per posizione.
	 *
	 * @var int
	 */
	const DOPPIO_MASSIMO = 67108864;

	/**
	 * La lunghezza massima di un pezzo letto dal flusso, in byte.
	 *
	 * @var int
	 */
	const PEZZO = 1048576;

	/**
	 * Legge un archivio zip del backup, o un file SQL da solo.
	 *
	 * Senza archivio non ci sono file: gli allegati risultano non verificabili,
	 * e il resto si legge lo stesso.
	 *
	 * @param string $percorso Percorso del file sul server.
	 * @return array{tabelle: array<string, array<int, array<string, string|null>>>, colonne: array<string, array<int, string>>, opzioni: array<string, string|null>, prefisso: string, ignorate: array<int, string>, file: array<string, array{impronta: string, byte: int}>, sql: string, archivio: bool}|\WP_Error
	 */
	public static function leggi( string $percorso ) {
		if ( ! is_file( $percorso ) || ! is_readable( $percorso ) ) {
			return self::errore( __( 'il file non si trova o non si puo\' leggere.', 'albo-pretorio-pa' ) );
		}

		$testa = (string) file_get_contents( $percorso, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- quattro byte di un file locale.

		if ( "PK\x03\x04" === $testa ) {
			return self::leggi_archivio( $percorso );
		}

		if ( filesize( $percorso ) > self::SQL_MASSIMO ) {
			return self::errore( __( 'il file SQL e\' troppo grande per essere letto qui.', 'albo-pretorio-pa' ) );
		}

		$flusso = fopen( $percorso, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- lettura in sola lettura, riga per riga.

		if ( false === $flusso ) {
			return self::errore( __( 'il file non si puo\' aprire.', 'albo-pretorio-pa' ) );
		}

		$letto = self::leggi_sql( self::righe( $flusso ) );
		fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura del flusso aperto qui.

		if ( is_wp_error( $letto ) ) {
			return $letto;
		}

		$letto['file'] = array();
		$letto['sql']  = basename( $percorso );
		$letto['zip']  = false;

		return $letto;
	}

	/**
	 * Legge l'archivio: il file SQL alla radice e l'impronta di ogni altro file.
	 *
	 * Niente si estrae sul disco: ogni file si legge come flusso e se ne
	 * calcola l'impronta SHA-256, la stessa formula del plugin di provenienza.
	 *
	 * @param string $percorso Archivio.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function leggi_archivio( string $percorso ) {
		if ( ! class_exists( '\ZipArchive' ) ) {
			return self::errore( __( 'il server non ha l\'estensione di PHP che legge gli archivi zip.', 'albo-pretorio-pa' ) );
		}

		$archivio = new \ZipArchive();

		if ( true !== $archivio->open( $percorso, \ZipArchive::RDONLY ) ) {
			return self::errore( __( 'l\'archivio zip non si apre: e\' danneggiato o non e\' un archivio.', 'albo-pretorio-pa' ) );
		}

		$sql   = array();
		$file  = array();
		$visti = array();

		$quanti = $archivio->count();

		if ( $quanti > self::FILE_MASSIMI ) {
			$archivio->close();

			return self::errore( __( 'l\'archivio contiene troppi file per essere letto qui.', 'albo-pretorio-pa' ) );
		}

		for ( $indice = 0; $indice < $quanti; $indice++ ) {
			$voce = $archivio->statIndex( $indice );

			if ( false === $voce || str_ends_with( (string) $voce['name'], '/' ) ) {
				continue;
			}

			$nome = (string) $voce['name'];

			// I file si aprono per nome. Il plugin di provenienza aggiunge due volte lo stesso file quando due allegati lo citano:
			// due voci con la stessa impronta, letta per posizione, sono lo stesso file e se ne tiene una. Diverse, l'impronta sarebbe di una sola.
			if ( isset( $visti[ $nome ] ) ) {
				$prima   = self::impronta_indice( $archivio, $visti[ $nome ] );
				$seconda = self::impronta_indice( $archivio, $indice );

				if ( null !== $prima && $prima === $seconda ) {
					continue;
				}

				$archivio->close();

				return self::errore(
					sprintf(
						/* translators: %s: nome del file nell'archivio. */
						__( 'nell\'archivio ci sono due file con lo stesso nome (%s).', 'albo-pretorio-pa' ),
						$nome
					)
				);
			}

			$visti[ $nome ] = $indice;

			if ( false === strpos( $nome, '/' ) && 1 === preg_match( '/\.sql\z/i', $nome ) ) {
				$sql[] = array(
					'nome' => $nome,
					'byte' => (int) $voce['size'],
				);
				continue;
			}

			$impronta = self::impronta( $archivio, $nome );

			if ( null === $impronta ) {
				$archivio->close();

				return self::errore(
					sprintf(
						/* translators: %s: nome del file nell'archivio. */
						__( 'il file %s dell\'archivio non si legge.', 'albo-pretorio-pa' ),
						$nome
					)
				);
			}

			$file[ $nome ] = array(
				'impronta' => $impronta,
				'byte'     => (int) $voce['size'],
			);
		}

		if ( 1 !== count( $sql ) ) {
			$archivio->close();

			return self::errore(
				array() === $sql
					? __( 'nell\'archivio non c\'e\' il file SQL con i dati: non sembra un backup dell\'albo.', 'albo-pretorio-pa' )
					: __( 'nell\'archivio ci sono piu\' file SQL: non si sa quale sia il backup.', 'albo-pretorio-pa' )
			);
		}

		if ( $sql[0]['byte'] > self::SQL_MASSIMO ) {
			$archivio->close();

			return self::errore( __( 'il file SQL e\' troppo grande per essere letto qui.', 'albo-pretorio-pa' ) );
		}

		$flusso = $archivio->getStream( $sql[0]['nome'] );

		if ( false === $flusso ) {
			$archivio->close();

			return self::errore( __( 'il file SQL dell\'archivio non si legge.', 'albo-pretorio-pa' ) );
		}

		$letto = self::leggi_sql( self::righe( $flusso ) );
		fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura del flusso aperto qui.
		$archivio->close();

		if ( is_wp_error( $letto ) ) {
			return $letto;
		}

		$letto['file'] = $file;
		$letto['sql']  = $sql[0]['nome'];
		$letto['zip']  = true;

		return $letto;
	}

	/**
	 * L'impronta SHA-256 di un file dell'archivio, letto a pezzi.
	 *
	 * @param \ZipArchive $archivio Archivio aperto.
	 * @param string      $nome     Nome del file nell'archivio, unico.
	 * @return string|null
	 */
	private static function impronta( \ZipArchive $archivio, string $nome ): ?string {
		$flusso = $archivio->getStream( $nome );

		if ( false === $flusso ) {
			return null;
		}

		$contesto = hash_init( 'sha256' );

		while ( ! feof( $flusso ) ) {
			$pezzo = fread( $flusso, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- lettura a pezzi di un flusso dell'archivio.

			if ( false === $pezzo ) {
				fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura del flusso aperto qui.
				return null;
			}

			hash_update( $contesto, $pezzo );
		}

		fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura del flusso aperto qui.

		return hash_final( $contesto );
	}

	/**
	 * L'impronta SHA-256 di una voce dell'archivio, per posizione: serve per i nomi doppi.
	 * Dove PHP non apre un flusso per posizione la voce si legge intera, solo se piccola.
	 *
	 * @param \ZipArchive $archivio Archivio aperto.
	 * @param int         $indice   Posizione della voce.
	 * @return string|null
	 */
	private static function impronta_indice( \ZipArchive $archivio, int $indice ): ?string {
		if ( method_exists( $archivio, 'getStreamIndex' ) ) {
			$flusso = $archivio->getStreamIndex( $indice );

			if ( false === $flusso ) {
				return null;
			}

			$contesto = hash_init( 'sha256' );

			while ( ! feof( $flusso ) ) {
				$pezzo = fread( $flusso, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- lettura a pezzi di un flusso dell'archivio.

				if ( false === $pezzo ) {
					fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura del flusso aperto qui.
					return null;
				}

				hash_update( $contesto, $pezzo );
			}

			fclose( $flusso ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- chiusura del flusso aperto qui.

			return hash_final( $contesto );
		}

		$voce = $archivio->statIndex( $indice );

		if ( false === $voce || (int) $voce['size'] > self::DOPPIO_MASSIMO ) {
			return null;
		}

		$contenuto = $archivio->getFromIndex( $indice );

		return false === $contenuto ? null : hash( 'sha256', $contenuto );
	}

	/**
	 * Il testo di un flusso, a pezzi di lunghezza limitata.
	 *
	 * Un pezzo finisce all'a capo o dopo PEZZO byte: una riga lunghissima non
	 * si carica mai intera prima dei controlli.
	 *
	 * @param resource $flusso Flusso aperto in lettura.
	 * @return \Generator<int, string>
	 */
	private static function righe( $flusso ): \Generator {
		while ( ! feof( $flusso ) ) {
			$riga = fgets( $flusso, self::PEZZO + 1 );

			if ( false === $riga ) {
				break;
			}

			yield $riga;
		}
	}

	/**
	 * Interpreta il testo SQL del backup.
	 *
	 * Le istruzioni si separano al punto e virgola che sta fuori dai testi fra
	 * apici; i commenti (`-- `, `#`, fra barra e asterisco) valgono come uno
	 * spazio in qualunque punto fuori dai testi. Si riconoscono tre forme: la
	 * creazione di una tabella, da cui si prende l'ordine delle colonne;
	 * l'inserimento di righe, con o senza l'elenco delle colonne e con una o
	 * piu' righe per istruzione; e l'aggiornamento di una impostazione, che il
	 * backup usa per il contatore. Tutto il resto si salta.
	 *
	 * I limiti si controllano prima di tenere i dati: il testo intero, ogni
	 * istruzione prima di interpretarla, e il totale dei valori conservati.
	 *
	 * @param iterable           $righe  Il testo, a pezzi, una stringa per elemento.
	 * @param array<string, int> $limiti Limiti diversi da quelli predefiniti, per le prove: 'sql', 'istruzione', 'dati'.
	 * @return array{tabelle: array<string, array<int, array<string, string|null>>>, colonne: array<string, array<int, string>>, opzioni: array<string, string|null>, prefisso: string, ignorate: array<int, string>}|\WP_Error
	 */
	public static function leggi_sql( iterable $righe, array $limiti = array() ) {
		$stato = array(
			'colonne'  => array(),
			'righe'    => array(),
			'opzioni'  => array(),
			'prefissi' => array(),
			'ignorate' => array(),
			'dati'     => 0,
			'limiti'   => array(
				'sql'        => (int) ( $limiti['sql'] ?? self::SQL_MASSIMO ),
				'istruzione' => (int) ( $limiti['istruzione'] ?? self::ISTRUZIONE_MASSIMA ),
				'dati'       => (int) ( $limiti['dati'] ?? self::DATI_MASSIMI ),
			),
		);

		$lettura = array(
			'istruzione' => '',
			'apice'      => '',
			'fuga'       => false,
			'commento'   => false,
			'riga'       => false,
		);

		$letti = 0;
		$coda  = '';

		foreach ( $righe as $pezzo ) {
			$letti += strlen( $pezzo );

			if ( $letti > $stato['limiti']['sql'] ) {
				return self::errore( __( 'il file SQL e\' troppo grande per essere letto qui.', 'albo-pretorio-pa' ) );
			}

			// Quel che resta di un pezzo (un "-" o una "/" in fondo) si decide con il pezzo dopo.
			$coda = self::scorri( $coda . $pezzo, $lettura, $stato, false );

			if ( is_wp_error( $coda ) ) {
				return $coda;
			}
		}

		$coda = self::scorri( $coda, $lettura, $stato, true );

		if ( is_wp_error( $coda ) ) {
			return $coda;
		}

		if ( '' !== $lettura['apice'] || $lettura['commento'] ) {
			return self::errore( __( 'il file SQL finisce a meta\' di un testo: e\' troncato.', 'albo-pretorio-pa' ) );
		}

		// L'ultima istruzione puo' mancare del punto e virgola, come nel backup del plugin: si accetta solo se e' completa.
		if ( '' !== trim( $lettura['istruzione'] ) ) {
			$esito = self::istruzione( $lettura['istruzione'], $stato, true );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}
		}

		$prefissi = array_keys( $stato['prefissi'] );

		if ( array() === $prefissi ) {
			return self::errore( __( 'nel file SQL non c\'e\' la tabella degli atti dell\'albo: non sembra un backup dell\'albo.', 'albo-pretorio-pa' ) );
		}

		if ( count( $prefissi ) > 1 ) {
			return self::errore( __( 'nel file SQL ci sono le tabelle dell\'albo di piu\' siti: non si sa quali leggere.', 'albo-pretorio-pa' ) );
		}

		$prefisso = (string) $prefissi[0];
		$tabelle  = array();
		$colonne  = array();

		foreach ( self::TABELLE as $tabella ) {
			$nome = $prefisso . $tabella;

			if ( isset( $stato['colonne'][ $nome ] ) ) {
				$colonne[ $tabella ] = $stato['colonne'][ $nome ];
			}

			if ( isset( $stato['righe'][ $nome ] ) || isset( $stato['colonne'][ $nome ] ) ) {
				$tabelle[ $tabella ] = $stato['righe'][ $nome ] ?? array();
			}
		}

		$ignorate = array_keys( $stato['ignorate'] );

		return array(
			'tabelle'  => $tabelle,
			'colonne'  => $colonne,
			'opzioni'  => $stato['opzioni'][ $prefisso . 'options' ] ?? array(),
			'prefisso' => $prefisso,
			'ignorate' => $ignorate,
		);
	}

	/**
	 * Scorre un pezzo di testo SQL e interpreta le istruzioni che si chiudono.
	 *
	 * Un "-", una "/" o un "*" in fondo al pezzo non si possono ancora decidere:
	 * tornano indietro e si rileggono davanti al pezzo dopo.
	 *
	 * @param string               $riga    Pezzo di testo.
	 * @param array<string, mixed> $lettura Stato dello scorrimento fra un pezzo e l'altro, aggiornato.
	 * @param array<string, mixed> $stato   Stato della lettura, aggiornato.
	 * @param bool                 $finale  Vero per l'ultimo pezzo: dopo non viene niente.
	 * @return string|\WP_Error Il testo da rileggere con il pezzo dopo.
	 */
	private static function scorri( string $riga, array &$lettura, array &$stato, bool $finale ) {
		$lunghezza = strlen( $riga );
		$i         = 0;

		while ( $i < $lunghezza ) {
			// Commento fino all'a capo: l'a capo resta e separa.
			if ( $lettura['riga'] ) {
				$fine = strpos( $riga, "\n", $i );

				if ( false === $fine ) {
					break;
				}

				$lettura['riga'] = false;
				$i               = $fine;
				continue;
			}

			if ( $lettura['commento'] ) {
				$chiusura = strpos( $riga, '*/', $i );

				if ( false === $chiusura ) {
					return ( ! $finale && str_ends_with( $riga, '*' ) ) ? '*' : self::fine_pezzo( $lettura, $stato );
				}

				$lettura['commento']    = false;
				$lettura['istruzione'] .= ' ';
				$i                      = $chiusura + 2;
				continue;
			}

			if ( '' !== $lettura['apice'] ) {
				if ( $lettura['fuga'] ) {
					$lettura['istruzione'] .= $riga[ $i ];
					$lettura['fuga']        = false;
					++$i;
					continue;
				}

				// Si salta in un colpo fino al prossimo apice o barra rovesciata.
				$tratto                 = strcspn( $riga, $lettura['apice'] . '\\', $i );
				$lettura['istruzione'] .= substr( $riga, $i, $tratto );
				$i                     += $tratto;

				if ( $i >= $lunghezza ) {
					break;
				}

				$carattere              = $riga[ $i ];
				$lettura['istruzione'] .= $carattere;
				++$i;

				if ( '\\' === $carattere ) {
					$lettura['fuga'] = true;
				} else {
					$lettura['apice'] = '';
				}
				continue;
			}

			// Fuori dai testi si salta fino al prossimo carattere che conta.
			$tratto                 = strcspn( $riga, "'\"`/;-#", $i );
			$lettura['istruzione'] .= substr( $riga, $i, $tratto );
			$i                     += $tratto;

			if ( $i >= $lunghezza ) {
				break;
			}

			$carattere = $riga[ $i ];

			// Senza il seguito non si sa se comincia un commento: si rimanda al pezzo dopo.
			if ( ! $finale && ( ( '/' === $carattere && $i + 1 >= $lunghezza ) || ( '-' === $carattere && ( $i + 1 >= $lunghezza || ( '-' === $riga[ $i + 1 ] && $i + 2 >= $lunghezza ) ) ) ) ) {
				$resto = substr( $riga, $i );
				$esito = self::fine_pezzo( $lettura, $stato );

				return is_wp_error( $esito ) ? $esito : $resto;
			}

			++$i;

			if ( "'" === $carattere || '"' === $carattere || '`' === $carattere ) {
				$lettura['apice']       = $carattere;
				$lettura['istruzione'] .= $carattere;
				continue;
			}

			// Commento fra barra e asterisco; quelli condizionali di mysqldump sono impostazioni della sessione.
			if ( '/' === $carattere && $i < $lunghezza && '*' === $riga[ $i ] ) {
				$lettura['commento'] = true;
				++$i;
				continue;
			}

			// "#" e "--" seguito da uno spazio, da un carattere di controllo o dalla fine: commento fino all'a capo.
			if ( '#' === $carattere || ( '-' === $carattere && $i < $lunghezza && '-' === $riga[ $i ] && ( $i + 1 >= $lunghezza || ord( $riga[ $i + 1 ] ) <= 32 ) ) ) {
				$lettura['riga']        = true;
				$lettura['istruzione'] .= ' ';
				continue;
			}

			if ( ';' === $carattere ) {
				if ( strlen( $lettura['istruzione'] ) > $stato['limiti']['istruzione'] ) {
					return self::troppo_lunga();
				}

				$esito = self::istruzione( $lettura['istruzione'], $stato );

				if ( is_wp_error( $esito ) ) {
					return $esito;
				}

				$lettura['istruzione'] = '';
				continue;
			}

			$lettura['istruzione'] .= $carattere;
		}

		return self::fine_pezzo( $lettura, $stato );
	}

	/**
	 * Il controllo in fondo a ogni pezzo: un'istruzione che cresce oltre il limite si ferma qui.
	 *
	 * @param array<string, mixed> $lettura Stato dello scorrimento.
	 * @param array<string, mixed> $stato   Stato della lettura.
	 * @return string|\WP_Error Niente da rileggere, o il rifiuto.
	 */
	private static function fine_pezzo( array $lettura, array $stato ) {
		if ( strlen( $lettura['istruzione'] ) > $stato['limiti']['istruzione'] ) {
			return self::troppo_lunga();
		}

		return '';
	}

	/**
	 * Il rifiuto per un'istruzione oltre il limite.
	 *
	 * @return \WP_Error
	 */
	private static function troppo_lunga(): \WP_Error {
		return self::errore( __( 'il file SQL contiene un\'istruzione troppo lunga, o un testo fra apici che non si chiude.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Interpreta una istruzione completa, senza il punto e virgola.
	 *
	 * Le istruzioni che contano devono essere intere: una creazione senza la
	 * parentesi che la chiude, o un aggiornamento delle impostazioni che non
	 * arriva alla condizione, vogliono dire un file troncato. L'ultima
	 * istruzione del testo, che puo' mancare del punto e virgola, si accetta
	 * solo se e' una di quelle riconosciute e intera.
	 *
	 * @param string               $testo  Istruzione.
	 * @param array<string, mixed> $stato  Stato della lettura, aggiornato.
	 * @param bool                 $ultima Vero per l'ultima istruzione, senza punto e virgola.
	 * @return true|\WP_Error
	 */
	private static function istruzione( string $testo, array &$stato, bool $ultima = false ) {
		$testo = trim( $testo );

		if ( 1 === preg_match( '/\ACREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(`[^`]+`|[A-Za-z0-9_$]+)\s*\(/i', $testo, $parti ) ) {
			$nome    = self::nome( $parti[1] );
			$colonne = self::colonne_create( substr( $testo, strlen( $parti[0] ) ) );

			if ( null === $colonne ) {
				return self::troncato();
			}

			if ( 'troppe' === $colonne ) {
				return self::errore(
					sprintf(
						/* translators: %s: nome della tabella. */
						__( 'la creazione della tabella %s ha piu\' colonne di quante una tabella ne possa avere.', 'albo-pretorio-pa' ),
						$nome
					)
				);
			}

			if ( 'doppia' === $colonne ) {
				return self::errore(
					sprintf(
						/* translators: %s: nome della tabella. */
						__( 'la creazione della tabella %s ha due colonne con lo stesso nome: non si puo\' sapere quale valore vale.', 'albo-pretorio-pa' ),
						$nome
					)
				);
			}

			// Delle altre tabelle si tiene solo il nome; le colonne si tengono, e si contano, solo per le tabelle che servono.
			if ( null === self::nota_tabella( $nome, $stato ) ) {
				return self::ignora( $nome, $stato );
			}

			$esito = self::conta( $stato, array_merge( array( $nome ), $colonne ) );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}

			$stato['colonne'][ $nome ] = $colonne;

			return true;
		}

		if ( 1 === preg_match( '/\A(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+(`[^`]+`|[A-Za-z0-9_$]+)\s*/i', $testo, $parti ) ) {
			$nome = self::nome( $parti[1] );
			$tipo = self::nota_tabella( $nome, $stato );

			if ( null === $tipo ) {
				return $ultima ? self::troncato() : self::ignora( $nome, $stato );
			}

			$esito = self::inserimento( $nome, $tipo, substr( $testo, strlen( $parti[0] ) ), $stato );

			// Un inserimento che non si legge in fondo al file e' quasi sempre un file tagliato: lo si dice.
			return ( $ultima && is_wp_error( $esito ) ) ? self::troncato() : $esito;
		}

		if ( 1 === preg_match( '/\AUPDATE\s+(`[^`]+`|[A-Za-z0-9_$]+)/i', $testo, $tabella ) && str_ends_with( self::nome( $tabella[1] ), 'options' ) ) {
			// L'aggiornamento del contatore che il backup scrive in fondo: prevale sulle righe inserite prima.
			if ( 1 !== preg_match( "/\\AUPDATE\\s+(`[^`]+`|[A-Za-z0-9_\$]+)\\s+SET\\s+`?option_value`?\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'\\s+WHERE\\s+`?option_name`?\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\z/is", $testo, $parti ) ) {
				return self::troncato();
			}

			$opzione = self::testo( $parti[3] );

			if ( str_starts_with( $opzione, self::PREFISSO_OPZIONI ) ) {
				$esito = self::conta( $stato, array( $parti[1], $opzione, $parti[2] ) );

				if ( is_wp_error( $esito ) ) {
					return $esito;
				}

				$stato['opzioni'][ self::nome( $parti[1] ) ][ $opzione ] = self::testo( $parti[2] );
			}

			return true;
		}

		return $ultima ? self::troncato() : true;
	}

	/**
	 * Mette in conto i valori che si stanno per tenere, e rifiuta oltre il limite.
	 *
	 * @param array<string, mixed> $stato  Stato della lettura, aggiornato.
	 * @param array<int, mixed>    $valori Valori.
	 * @return true|\WP_Error
	 */
	private static function conta( array &$stato, array $valori ) {
		foreach ( $valori as $valore ) {
			$stato['dati'] += strlen( (string) $valore ) + self::VALORE_COSTO;
		}

		if ( $stato['dati'] > $stato['limiti']['dati'] ) {
			return self::errore( __( 'i dati dell\'albo nel file SQL sono troppi per essere letti qui.', 'albo-pretorio-pa' ) );
		}

		return true;
	}

	/**
	 * Vero se le opzioni dopo le colonne di una creazione sono intere.
	 *
	 * Si accetta la forma che scrive MySQL nell'istruzione di creazione: coppie
	 * nome=valore, come `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='...'`.
	 * Un nome senza valore vuol dire un testo tagliato.
	 *
	 * @param string $resto Testo dopo la parentesi che chiude le colonne.
	 * @return bool
	 */
	private static function opzioni_intere( string $resto ): bool {
		$resto = trim( $resto );

		return '' === $resto || 1 === preg_match( "/\\A(?:(?:DEFAULT\\s+)?[A-Za-z_]+(?:\\s+[A-Za-z_]+)?\\s*=\\s*(?:'(?:[^'\\\\]|\\\\.|'')*'|[A-Za-z0-9_]+)\\s*,?\\s*)+\\z/s", $resto );
	}

	/**
	 * Il rifiuto per un file che finisce a meta' di un'istruzione.
	 *
	 * @return \WP_Error
	 */
	private static function troncato(): \WP_Error {
		return self::errore( __( 'il file SQL finisce a meta\' di un\'istruzione: e\' troncato.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Annota una tabella incontrata e dice se e' dell'albo, delle impostazioni o d'altri.
	 *
	 * @param string               $nome  Nome completo.
	 * @param array<string, mixed> $stato Stato della lettura, aggiornato.
	 * @return string|null 'albo', 'opzioni' o nullo.
	 */
	private static function nota_tabella( string $nome, array &$stato ): ?string {
		foreach ( self::TABELLE as $tabella ) {
			if ( str_ends_with( $nome, $tabella ) ) {
				$prefisso = substr( $nome, 0, strlen( $nome ) - strlen( $tabella ) );

				if ( 'albopretorio_atti' === $tabella ) {
					$stato['prefissi'][ $prefisso ] = true;
				}

				return 'albo';
			}
		}

		if ( str_ends_with( $nome, 'options' ) ) {
			return 'opzioni';
		}

		return null;
	}

	/**
	 * Annota il nome di una tabella che non serve: una volta sola, e contato come i dati.
	 *
	 * @param string               $nome  Tabella.
	 * @param array<string, mixed> $stato Stato della lettura, aggiornato.
	 * @return true|\WP_Error
	 */
	private static function ignora( string $nome, array &$stato ) {
		if ( isset( $stato['ignorate'][ $nome ] ) ) {
			return true;
		}

		$esito = self::conta( $stato, array( $nome ) );

		if ( is_wp_error( $esito ) ) {
			return $esito;
		}

		$stato['ignorate'][ $nome ] = true;

		return true;
	}

	/**
	 * Le righe di un inserimento.
	 *
	 * @param string               $nome  Tabella.
	 * @param string               $tipo  'albo' o 'opzioni'.
	 * @param string               $resto Testo dopo il nome della tabella.
	 * @param array<string, mixed> $stato Stato della lettura, aggiornato.
	 * @return true|\WP_Error
	 */
	private static function inserimento( string $nome, string $tipo, string $resto, array &$stato ) {
		$colonne = null;
		$resto   = ltrim( $resto );

		if ( str_starts_with( $resto, '(' ) ) {
			$chiusa = strpos( $resto, ')' );

			if ( false === $chiusa ) {
				return self::errore_tabella( $nome );
			}

			$colonne = self::colonne_inserimento( substr( $resto, 1, $chiusa - 1 ) );

			if ( null === $colonne ) {
				return self::errore(
					sprintf(
						/* translators: %s: nome della tabella. */
						__( 'nel file SQL i dati della tabella %s portano un elenco di colonne non valido: vuoto, ripetuto o piu\' lungo di quanto una tabella possa avere.', 'albo-pretorio-pa' ),
						$nome
					)
				);
			}

			$resto = ltrim( substr( $resto, $chiusa + 1 ) );
		}

		if ( 1 !== preg_match( '/\AVALUES?\s*/i', $resto, $parti ) ) {
			return self::errore_tabella( $nome );
		}

		if ( null === $colonne ) {
			$colonne = $stato['colonne'][ $nome ] ?? ( 'opzioni' === $tipo ? array( 'option_id', 'option_name', 'option_value', 'autoload' ) : null );
		}

		if ( null === $colonne ) {
			return self::errore(
				sprintf(
					/* translators: %s: nome della tabella. */
					__( 'nel file SQL i dati della tabella %s arrivano senza le sue colonne: l\'ordine dei valori non si puo\' sapere.', 'albo-pretorio-pa' ),
					$nome
				)
			);
		}

		foreach ( self::tuple( substr( $resto, strlen( $parti[0] ) ) ) as $valori ) {
			if ( null === $valori ) {
				return self::errore_tabella( $nome );
			}

			if ( count( $valori ) !== count( $colonne ) ) {
				return self::errore(
					sprintf(
						/* translators: 1: nome della tabella, 2: numero di valori, 3: numero di colonne. */
						__( 'nel file SQL una riga della tabella %1$s ha %2$d valori per %3$d colonne.', 'albo-pretorio-pa' ),
						$nome,
						count( $valori ),
						count( $colonne )
					)
				);
			}

			$riga = array_combine( $colonne, $valori );

			if ( 'opzioni' === $tipo ) {
				$opzione = (string) ( $riga['option_name'] ?? '' );

				// Delle impostazioni si tengono solo quelle del plugin di provenienza, per tabella: il resto del sito non serve e non si porta.
				if ( str_starts_with( $opzione, self::PREFISSO_OPZIONI ) ) {
					$esito = self::conta( $stato, array( $nome, $opzione, $riga['option_value'] ?? null ) );

					if ( is_wp_error( $esito ) ) {
						return $esito;
					}

					$stato['opzioni'][ $nome ][ $opzione ] = $riga['option_value'] ?? null;
				}
				continue;
			}

			$esito = self::conta( $stato, $valori );

			if ( is_wp_error( $esito ) ) {
				return $esito;
			}

			$stato['righe'][ $nome ][] = $riga;
		}

		return true;
	}

	/**
	 * Le tuple di valori di un inserimento, una alla volta; nullo al posto di una tupla che non si legge.
	 *
	 * Una alla volta, cosi' i limiti si controllano prima di tenerle tutte.
	 *
	 * @param string $testo Testo dopo VALUES.
	 * @return \Generator<int, array<int, string|null>|null>
	 */
	private static function tuple( string $testo ): \Generator {
		$lunghezza = strlen( $testo );
		$i         = 0;

		while ( true ) {
			while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
				++$i;
			}

			if ( $i >= $lunghezza || '(' !== $testo[ $i ] ) {
				yield null;
				return;
			}

			++$i;
			$valori = array();

			while ( true ) {
				while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
					++$i;
				}

				if ( $i >= $lunghezza ) {
					yield null;
					return;
				}

				if ( "'" === $testo[ $i ] || '"' === $testo[ $i ] ) {
					$apice = $testo[ $i ];
					$fine  = $i + 1;
					$letto = '';

					while ( $fine < $lunghezza ) {
						if ( '\\' === $testo[ $fine ] && $fine + 1 < $lunghezza ) {
							$letto .= self::fuga( $testo[ $fine + 1 ] );
							$fine  += 2;
							continue;
						}

						if ( $testo[ $fine ] === $apice ) {
							// Due apici di fila sono un apice dentro il testo.
							if ( $fine + 1 < $lunghezza && $testo[ $fine + 1 ] === $apice ) {
								$letto .= $apice;
								$fine  += 2;
								continue;
							}
							break;
						}

						$letto .= $testo[ $fine ];
						++$fine;
					}

					if ( $fine >= $lunghezza ) {
						yield null;
						return;
					}

					$valori[] = $letto;
					$i        = $fine + 1;
				} else {
					$inizio = $i;

					while ( $i < $lunghezza && ',' !== $testo[ $i ] && ')' !== $testo[ $i ] ) {
						++$i;
					}

					$parola   = trim( substr( $testo, $inizio, $i - $inizio ) );
					$valori[] = self::parola( $parola );
				}

				while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
					++$i;
				}

				if ( $i >= $lunghezza ) {
					yield null;
					return;
				}

				if ( ',' === $testo[ $i ] ) {
					++$i;

					// Piu' valori di quante colonne possa avere una tabella MySQL: non e' una riga vera.
					if ( count( $valori ) >= self::COLONNE_MASSIME ) {
						yield null;
						return;
					}
					continue;
				}

				if ( ')' === $testo[ $i ] ) {
					++$i;
					break;
				}

				yield null;
				return;
			}

			yield $valori;

			while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
				++$i;
			}

			if ( $i >= $lunghezza ) {
				return;
			}

			if ( ',' !== $testo[ $i ] ) {
				yield null;
				return;
			}

			++$i;
		}
	}

	/**
	 * Il carattere che una sequenza di fuga rappresenta, come la legge MySQL.
	 *
	 * @param string $carattere Carattere dopo la barra rovesciata.
	 * @return string
	 */
	private static function fuga( string $carattere ): string {
		$mappa = array(
			'0' => "\0",
			'n' => "\n",
			'r' => "\r",
			't' => "\t",
			'b' => "\x08",
			'Z' => "\x1a",
		);

		// Barra, apici e ogni altro carattere valgono se stessi; per % e _ MySQL tiene anche la barra.
		if ( '%' === $carattere || '_' === $carattere ) {
			return '\\' . $carattere;
		}

		return $mappa[ $carattere ] ?? $carattere;
	}

	/**
	 * Un valore scritto senza apici: nullo, numero o testo esadecimale.
	 *
	 * @param string $parola Valore.
	 * @return string|null
	 */
	private static function parola( string $parola ): ?string {
		if ( 0 === strcasecmp( $parola, 'NULL' ) ) {
			return null;
		}

		if ( 1 === preg_match( '/\A0x([0-9a-f]*)\z/i', $parola, $parti ) && 0 === strlen( $parti[1] ) % 2 ) {
			return (string) hex2bin( $parti[1] );
		}

		return $parola;
	}

	/**
	 * Il testo di una stringa SQL fra apici singoli, senza gli apici.
	 *
	 * @param string $interno Testo fra gli apici.
	 * @return string
	 */
	private static function testo( string $interno ): string {
		$tupla = self::tuple( "('" . $interno . "')" )->current();

		return null === $tupla ? $interno : (string) $tupla[0];
	}

	/**
	 * I nomi delle colonne dal corpo di una creazione di tabella.
	 *
	 * Le definizioni si separano alle virgole di primo livello; quelle che
	 * cominciano con una parola chiave di indice o vincolo non sono colonne.
	 *
	 * @param string $corpo Testo dopo la parentesi aperta.
	 * @return array<int, string>|string|null Nullo se la parentesi della creazione non si chiude (istruzione troncata), 'troppe' oltre il massimo di colonne o di definizioni, 'doppia' con due colonne dallo stesso nome.
	 */
	private static function colonne_create( string $corpo ) {
		$colonne     = array();
		$visti       = array();
		$definizioni = 0;
		$corrente    = '';
		$livello     = 0;
		$apice       = '';
		$chiusa      = false;
		$lunghezza   = strlen( $corpo );

		for ( $i = 0; $i < $lunghezza; $i++ ) {
			$carattere = $corpo[ $i ];

			if ( '' !== $apice ) {
				$corrente .= $carattere;

				// Nei testi fra apici la barra rovesciata protegge il carattere dopo; nei nomi fra accenti gravi no.
				if ( '\\' === $carattere && '`' !== $apice && $i + 1 < $lunghezza ) {
					$corrente .= $corpo[ ++$i ];
				} elseif ( $carattere === $apice ) {
					$apice = '';
				}
				continue;
			}

			if ( "'" === $carattere || '`' === $carattere || '"' === $carattere ) {
				$apice     = $carattere;
				$corrente .= $carattere;
				continue;
			}

			if ( '(' === $carattere ) {
				++$livello;
			} elseif ( ')' === $carattere ) {
				if ( 0 === $livello ) {
					// Dopo la parentesi vengono le opzioni della tabella: anche quelle devono essere intere.
					$chiusa = self::opzioni_intere( substr( $corpo, $i + 1 ) );
					break;
				}
				--$livello;
			} elseif ( ',' === $carattere && 0 === $livello ) {
				// Ogni definizione si guarda appena finisce: oltre il massimo la lettura si ferma, senza accumulare.
				$motivo = self::definizione( $corrente, $colonne, $visti, $definizioni );

				if ( '' !== $motivo ) {
					return $motivo;
				}

				$corrente = '';
				continue;
			}

			$corrente .= $carattere;
		}

		if ( ! $chiusa ) {
			return null;
		}

		$motivo = self::definizione( $corrente, $colonne, $visti, $definizioni );

		return '' === $motivo ? $colonne : $motivo;
	}

	/**
	 * Una definizione della creazione: se e' una colonna, il suo nome si aggiunge.
	 *
	 * @param string              $definizione Testo della definizione.
	 * @param array<int, string>  $colonne     Colonne trovate, aggiornate.
	 * @param array<string, bool> $visti      Nomi gia' visti, in minuscolo, aggiornati.
	 * @param int                 $definizioni Definizioni viste, aggiornate.
	 * @return string Vuoto se la definizione va bene; 'troppe' oltre il massimo di colonne o di definizioni; 'doppia' con un nome gia' visto.
	 */
	private static function definizione( string $definizione, array &$colonne, array &$visti, int &$definizioni ): string {
		// Indici e vincoli si aggiungono alle colonne: il doppio del massimo di colonne e' gia' oltre ogni tabella vera.
		if ( ++$definizioni > 2 * self::COLONNE_MASSIME ) {
			return 'troppe';
		}

		$definizione = trim( $definizione );

		if ( 1 !== preg_match( '/\A(`[^`]+`|[A-Za-z0-9_$]+)/', $definizione, $parti ) ) {
			return '';
		}

		if ( '`' !== $parti[1][0] && in_array( strtoupper( $parti[1] ), array( 'PRIMARY', 'KEY', 'UNIQUE', 'INDEX', 'CONSTRAINT', 'FULLTEXT', 'SPATIAL', 'CHECK', 'FOREIGN' ), true ) ) {
			return '';
		}

		$colonna = self::nome( $parti[1] );

		// Come per MySQL, due nomi che differiscono solo per le maiuscole sono la stessa colonna.
		if ( isset( $visti[ strtolower( $colonna ) ] ) ) {
			return 'doppia';
		}

		$visti[ strtolower( $colonna ) ] = true;
		$colonne[]                       = $colonna;

		return count( $colonne ) <= self::COLONNE_MASSIME ? '' : 'troppe';
	}

	/**
	 * L'elenco esplicito delle colonne di un inserimento, letto una colonna alla volta.
	 *
	 * La lettura si ferma alla prima colonna oltre il massimo, senza costruire il resto dell'elenco.
	 *
	 * @param string $elenco Testo fra le parentesi.
	 * @return string[]|null Le colonne, o null se l'elenco ha nomi vuoti, ripetuti o troppi.
	 */
	private static function colonne_inserimento( string $elenco ): ?array {
		$colonne = array();
		$visti   = array();
		$inizio  = 0;
		$lungo   = strlen( $elenco );

		while ( $inizio <= $lungo ) {
			if ( count( $colonne ) >= self::COLONNE_MASSIME ) {
				return null;
			}

			$virgola = strpos( $elenco, ',', $inizio );
			$fine    = false === $virgola ? $lungo : $virgola;
			$colonna = self::nome( substr( $elenco, $inizio, $fine - $inizio ) );

			if ( '' === $colonna || isset( $visti[ strtolower( $colonna ) ] ) ) {
				return null;
			}

			$visti[ strtolower( $colonna ) ] = true;
			$colonne[]                       = $colonna;
			$inizio                          = $fine + 1;
		}

		return $colonne;
	}

	/**
	 * Un nome di tabella o colonna senza gli accenti gravi.
	 *
	 * @param string $nome Nome.
	 * @return string
	 */
	private static function nome( string $nome ): string {
		$nome = trim( $nome );

		if ( strlen( $nome ) >= 2 && '`' === $nome[0] && '`' === substr( $nome, -1 ) ) {
			return substr( $nome, 1, -1 );
		}

		return $nome;
	}

	/**
	 * L'errore di lettura di un inserimento.
	 *
	 * @param string $nome Tabella.
	 * @return \WP_Error
	 */
	private static function errore_tabella( string $nome ): \WP_Error {
		return self::errore(
			sprintf(
				/* translators: %s: nome della tabella. */
				__( 'nel file SQL i dati della tabella %s non si leggono.', 'albo-pretorio-pa' ),
				$nome
			)
		);
	}

	/**
	 * L'errore di lettura del backup.
	 *
	 * @param string $motivo Motivo.
	 * @return \WP_Error
	 */
	private static function errore( string $motivo ): \WP_Error {
		return new \WP_Error(
			'albo_importazione_lettura',
			sprintf(
				/* translators: %s: motivo. */
				__( 'Il backup non si legge: %s', 'albo-pretorio-pa' ),
				$motivo
			)
		);
	}
}
