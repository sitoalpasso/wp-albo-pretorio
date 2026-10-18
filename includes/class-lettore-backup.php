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
	 * @var int
	 */
	const ISTRUZIONE_MASSIMA = 67108864;

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

		$sql  = array();
		$file = array();

		$quanti = $archivio->count();

		for ( $indice = 0; $indice < $quanti; $indice++ ) {
			$voce = $archivio->statIndex( $indice );

			if ( false === $voce || str_ends_with( (string) $voce['name'], '/' ) ) {
				continue;
			}

			$nome = (string) $voce['name'];

			if ( false === strpos( $nome, '/' ) && 1 === preg_match( '/\.sql\z/i', $nome ) ) {
				$sql[] = array(
					'nome' => $nome,
					'byte' => (int) $voce['size'],
				);
				continue;
			}

			$impronta = self::impronta( $archivio, $indice );

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
	 * @param int         $indice   Posizione del file.
	 * @return string|null
	 */
	private static function impronta( \ZipArchive $archivio, int $indice ): ?string {
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

	/**
	 * Le righe di un flusso, una alla volta.
	 *
	 * @param resource $flusso Flusso aperto in lettura.
	 * @return \Generator<int, string>
	 */
	private static function righe( $flusso ): \Generator {
		$letti = 0;

		while ( ! feof( $flusso ) ) {
			$riga = fgets( $flusso );

			if ( false === $riga ) {
				break;
			}

			$letti += strlen( $riga );

			if ( $letti > self::SQL_MASSIMO ) {
				yield "\0TROPPO";
				return;
			}

			yield $riga;
		}
	}

	/**
	 * Interpreta il testo SQL del backup.
	 *
	 * Le istruzioni si separano al punto e virgola che sta fuori dai testi fra
	 * apici. Si riconoscono tre forme: la creazione di una tabella, da cui si
	 * prende l'ordine delle colonne; l'inserimento di righe, con o senza
	 * l'elenco delle colonne e con una o piu' righe per istruzione; e
	 * l'aggiornamento di una impostazione, che il backup usa per il contatore.
	 * Tutto il resto si salta.
	 *
	 * @param iterable $righe Righe del testo, con il loro a capo, una stringa per elemento.
	 * @return array{tabelle: array<string, array<int, array<string, string|null>>>, colonne: array<string, array<int, string>>, opzioni: array<string, string|null>, prefisso: string, ignorate: array<int, string>}|\WP_Error
	 */
	public static function leggi_sql( iterable $righe ) {
		$stato = array(
			'colonne'  => array(),
			'righe'    => array(),
			'opzioni'  => array(),
			'prefissi' => array(),
			'ignorate' => array(),
		);

		$istruzione = '';
		$apice      = '';
		$fuga       = false;
		$commento   = false;

		foreach ( $righe as $riga ) {
			if ( "\0TROPPO" === $riga ) {
				return self::errore( __( 'il file SQL e\' troppo grande per essere letto qui.', 'albo-pretorio-pa' ) );
			}

			// Le righe di commento si saltano solo se cominciano fuori da un testo e fuori da un'istruzione.
			if ( '' === $apice && '' === trim( $istruzione ) && ! $commento ) {
				$pulita = ltrim( $riga );

				if ( str_starts_with( $pulita, '-- ' ) || str_starts_with( $pulita, '#' ) || "--\n" === $pulita || "--\r\n" === $pulita ) {
					continue;
				}
			}

			$lunghezza = strlen( $riga );

			for ( $i = 0; $i < $lunghezza; $i++ ) {
				$carattere = $riga[ $i ];

				if ( $commento ) {
					if ( '*' === $carattere && $i + 1 < $lunghezza && '/' === $riga[ $i + 1 ] ) {
						$commento = false;
						++$i;
					}
					continue;
				}

				if ( '' !== $apice ) {
					$istruzione .= $carattere;

					if ( $fuga ) {
						$fuga = false;
					} elseif ( '\\' === $carattere ) {
						$fuga = true;
					} elseif ( $carattere === $apice ) {
						$apice = '';
					}
					continue;
				}

				if ( "'" === $carattere || '"' === $carattere || '`' === $carattere ) {
					$apice       = $carattere;
					$istruzione .= $carattere;
					continue;
				}

				// I commenti fra barra e asterisco si saltano; quelli condizionali di mysqldump sono impostazioni della sessione.
				if ( '/' === $carattere && $i + 1 < $lunghezza && '*' === $riga[ $i + 1 ] ) {
					$commento = true;
					++$i;
					continue;
				}

				if ( ';' === $carattere ) {
					$esito = self::istruzione( $istruzione, $stato );

					if ( is_wp_error( $esito ) ) {
						return $esito;
					}

					$istruzione = '';
					continue;
				}

				$istruzione .= $carattere;
			}

			if ( strlen( $istruzione ) > self::ISTRUZIONE_MASSIMA ) {
				return self::errore( __( 'il file SQL contiene un\'istruzione troppo lunga, o un testo fra apici che non si chiude.', 'albo-pretorio-pa' ) );
			}
		}

		if ( '' !== $apice || $commento ) {
			return self::errore( __( 'il file SQL finisce a meta\' di un testo: e\' troncato.', 'albo-pretorio-pa' ) );
		}

		if ( '' !== trim( $istruzione ) ) {
			$esito = self::istruzione( $istruzione, $stato );

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
			'opzioni'  => $stato['opzioni'],
			'prefisso' => $prefisso,
			'ignorate' => $ignorate,
		);
	}

	/**
	 * Interpreta una istruzione completa, senza il punto e virgola.
	 *
	 * @param string               $testo Istruzione.
	 * @param array<string, mixed> $stato Stato della lettura, aggiornato.
	 * @return true|\WP_Error
	 */
	private static function istruzione( string $testo, array &$stato ) {
		$testo = trim( $testo );

		if ( 1 === preg_match( '/\ACREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(`[^`]+`|[A-Za-z0-9_$]+)\s*\(/i', $testo, $parti ) ) {
			$nome = self::nome( $parti[1] );

			$stato['colonne'][ $nome ] = self::colonne_create( substr( $testo, strlen( $parti[0] ) ) );
			self::nota_tabella( $nome, $stato );

			return true;
		}

		if ( 1 === preg_match( '/\A(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+(`[^`]+`|[A-Za-z0-9_$]+)\s*/i', $testo, $parti ) ) {
			$nome = self::nome( $parti[1] );
			$tipo = self::nota_tabella( $nome, $stato );

			if ( null === $tipo ) {
				return true;
			}

			return self::inserimento( $nome, $tipo, substr( $testo, strlen( $parti[0] ) ), $stato );
		}

		// L'aggiornamento del contatore che il backup scrive in fondo: prevale sulle righe inserite prima.
		if ( 1 === preg_match( "/\\AUPDATE\\s+(`[^`]+`|[A-Za-z0-9_\$]+)\\s+SET\\s+`?option_value`?\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'\\s+WHERE\\s+`?option_name`?\\s*=\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\z/is", $testo, $parti ) ) {
			$opzione = self::testo( $parti[3] );

			if ( str_starts_with( $opzione, self::PREFISSO_OPZIONI ) && str_ends_with( self::nome( $parti[1] ), 'options' ) ) {
				$stato['opzioni'][ $opzione ] = self::testo( $parti[2] );
			}
		}

		return true;
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

		$stato['ignorate'][ $nome ] = true;

		return null;
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

			$colonne = array_map(
				array( self::class, 'nome' ),
				array_map( 'trim', explode( ',', substr( $resto, 1, $chiusa - 1 ) ) )
			);
			$resto   = ltrim( substr( $resto, $chiusa + 1 ) );
		}

		if ( 1 !== preg_match( '/\AVALUES?\s*/i', $resto, $parti ) ) {
			return self::errore_tabella( $nome );
		}

		$tuple = self::tuple( substr( $resto, strlen( $parti[0] ) ) );

		if ( null === $tuple ) {
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

		foreach ( $tuple as $valori ) {
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

				// Delle impostazioni si tengono solo quelle del plugin di provenienza: il resto del sito non serve e non si porta.
				if ( str_starts_with( $opzione, self::PREFISSO_OPZIONI ) ) {
					$stato['opzioni'][ $opzione ] = $riga['option_value'] ?? null;
				}
				continue;
			}

			$stato['righe'][ $nome ][] = $riga;
		}

		return true;
	}

	/**
	 * Le tuple di valori di un inserimento, o nullo se il testo non si legge.
	 *
	 * @param string $testo Testo dopo VALUES.
	 * @return array<int, array<int, string|null>>|null
	 */
	private static function tuple( string $testo ): ?array {
		$tuple     = array();
		$lunghezza = strlen( $testo );
		$i         = 0;

		while ( true ) {
			while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
				++$i;
			}

			if ( $i >= $lunghezza || '(' !== $testo[ $i ] ) {
				return null;
			}

			++$i;
			$valori = array();

			while ( true ) {
				while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
					++$i;
				}

				if ( $i >= $lunghezza ) {
					return null;
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
						return null;
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
					return null;
				}

				if ( ',' === $testo[ $i ] ) {
					++$i;
					continue;
				}

				if ( ')' === $testo[ $i ] ) {
					++$i;
					break;
				}

				return null;
			}

			$tuple[] = $valori;

			while ( $i < $lunghezza && ctype_space( $testo[ $i ] ) ) {
				++$i;
			}

			if ( $i >= $lunghezza ) {
				return $tuple;
			}

			if ( ',' !== $testo[ $i ] ) {
				return null;
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
		$tuple = self::tuple( "('" . $interno . "')" );

		return null === $tuple ? $interno : (string) $tuple[0][0];
	}

	/**
	 * I nomi delle colonne dal corpo di una creazione di tabella.
	 *
	 * Le definizioni si separano alle virgole di primo livello; quelle che
	 * cominciano con una parola chiave di indice o vincolo non sono colonne.
	 *
	 * @param string $corpo Testo dopo la parentesi aperta.
	 * @return array<int, string>
	 */
	private static function colonne_create( string $corpo ): array {
		$definizioni = array();
		$corrente    = '';
		$livello     = 0;
		$apice       = '';
		$lunghezza   = strlen( $corpo );

		for ( $i = 0; $i < $lunghezza; $i++ ) {
			$carattere = $corpo[ $i ];

			if ( '' !== $apice ) {
				$corrente .= $carattere;

				if ( $carattere === $apice ) {
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
					break;
				}
				--$livello;
			} elseif ( ',' === $carattere && 0 === $livello ) {
				$definizioni[] = $corrente;
				$corrente      = '';
				continue;
			}

			$corrente .= $carattere;
		}

		$definizioni[] = $corrente;
		$colonne       = array();

		foreach ( $definizioni as $definizione ) {
			$definizione = trim( $definizione );

			if ( 1 !== preg_match( '/\A(`[^`]+`|[A-Za-z0-9_$]+)/', $definizione, $parti ) ) {
				continue;
			}

			if ( '`' !== $parti[1][0] && in_array( strtoupper( $parti[1] ), array( 'PRIMARY', 'KEY', 'UNIQUE', 'INDEX', 'CONSTRAINT', 'FULLTEXT', 'SPATIAL', 'CHECK', 'FOREIGN' ), true ) ) {
				continue;
			}

			$colonne[] = self::nome( $parti[1] );
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
