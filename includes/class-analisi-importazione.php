<?php
/**
 * L'importazione a secco: cosa porterebbe il backup, senza scrivere niente (ALBO-35).
 *
 * Riceve quello che `LettoreBackup` ha letto e lo confronta con lo stato di
 * questo albo: tipi di atto e loro durate, numeri gia' usati, scelte sui dati
 * del procedimento, elenco dei festivi. Ne esce un rapporto: per ogni atto
 * come verrebbe ricostruito, se sarebbe visibile al pubblico, e i conflitti e
 * i dubbi che chi importa deve risolvere prima; poi categorie, numerazione,
 * campi non riconosciuti e file.
 *
 * **Le date sono fatti avvenuti**: si leggono come giorni di calendario e non
 * si ricalcolano. Da quel momento vale la scadenza di questo albo, che e' una
 * proprieta' della data letta: un atto la cui fine e' passata arriverebbe
 * invisibile al pubblico, senza impostazioni. Il rapporto non decide niente al
 * posto dell'amministrazione: dove la scelta e' sua, la segnala.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Confronto fra il backup letto e questo albo.
 */
final class AnalisiImportazione {

	/**
	 * Esito di un atto che si porterebbe senza altro da decidere.
	 *
	 * @var string
	 */
	const IMPORTABILE = 'importabile';

	/**
	 * Esito di un atto che si porterebbe dopo una decisione di chi importa.
	 *
	 * @var string
	 */
	const DA_DECIDERE = 'da_decidere';

	/**
	 * Esito di un atto che non si porterebbe finche' il conflitto resta.
	 *
	 * @var string
	 */
	const CONFLITTO = 'conflitto';

	/**
	 * Esito di una bozza: non si porta.
	 *
	 * @var string
	 */
	const ESCLUSO = 'escluso';

	/**
	 * Esito di un atto gia' portato in questo albo da un'importazione precedente: non si tocca piu'.
	 *
	 * @var string
	 */
	const IMPORTATO = 'importato';

	/**
	 * Byte oltre i quali un nome di categoria, amministrazione, persona o ufficio non e' un nome vero.
	 *
	 * @var int
	 */
	const NOME_MASSIMO = 1000;

	/**
	 * Le altre persone collegate a un atto che il rapporto nomina; le successive si contano.
	 *
	 * @var int
	 */
	const SOGGETTI_NOMINATI = 20;

	/**
	 * Le voci dell'elenco delle altre persone che si leggono al massimo: oltre, l'elenco non e' piu' un elenco vero.
	 *
	 * @var int
	 */
	const SOGGETTI_LETTI = 1000;

	/**
	 * Byte stimati oltre i quali il rapporto si ferma: gli atti successivi non si analizzano.
	 *
	 * @var int
	 */
	const RAPPORTO_MASSIMO = 67108864;

	/**
	 * Livelli delle segnalazioni, dal piu' grave.
	 *
	 * @var array<int, string>
	 */
	const LIVELLI = array( 'conflitto', 'dubbio', 'nota' );

	/**
	 * Le colonne conosciute di ciascuna tabella, con il loro destino in questo albo.
	 *
	 * Una colonna fuori da questo elenco si dichiara non riconosciuta; una
	 * colonna dell'elenco che manca nel backup si dichiara assente, perche' le
	 * versioni precedenti del plugin non l'avevano.
	 *
	 * @var array<string, array<int, string>>
	 */
	const COLONNE = array(
		'albopretorio_atti'               => array( 'IdAtto', 'Numero', 'Anno', 'Data', 'Riferimento', 'Oggetto', 'DataInizio', 'DataFine', 'Informazioni', 'IdCategoria', 'RespProc', 'DataAnnullamento', 'MotivoAnnullamento', 'Ente', 'DataOblio', 'Soggetti', 'IdUnitaOrganizzativa', 'Richiedente' ),
		'albopretorio_allegati'           => array( 'IdAllegato', 'TitoloAllegato', 'Allegato', 'IdAtto', 'TipoFile', 'DocIntegrale', 'Impronta', 'Natura', 'Note' ),
		'albopretorio_categorie'          => array( 'IdCategoria', 'Nome', 'Descrizione', 'Genitore', 'Giorni' ),
		'albopretorio_enti'               => array( 'IdEnte', 'Nome', 'Indirizzo', 'Url', 'Email', 'Pec', 'Telefono', 'Fax', 'Note' ),
		'albopretorio_resprocedura'       => array( 'IdResponsabile', 'Cognome', 'Nome', 'Email', 'Telefono', 'Orario', 'Note', 'Funzione' ),
		'albopretorio_attimeta'           => array( 'IdAttoMeta', 'IdAtto', 'Meta', 'Value' ),
		'albopretorio_unitaorganizzative' => array( 'IdUO', 'Nome', 'Indirizzo', 'Url', 'Email', 'Pec', 'Telefono', 'Fax', 'Note' ),
	);

	/**
	 * Le tabelle senza le quali il backup non dice abbastanza.
	 *
	 * @var array<int, string>
	 */
	const TABELLE_NECESSARIE = array( 'albopretorio_atti', 'albopretorio_allegati', 'albopretorio_categorie' );

	/**
	 * Gli anni dei numeri scritti nel backup, quelli che sono anni davvero.
	 *
	 * @param array<string, mixed> $letto Il backup letto da LettoreBackup.
	 * @return array<int, int>
	 */
	public static function anni( array $letto ): array {
		$anni = array();

		foreach ( $letto['tabelle']['albopretorio_atti'] ?? array() as $riga ) {
			if ( isset( $riga['Anno'] ) && 1 === preg_match( '/\A[1-9][0-9]{0,3}\z/', (string) $riga['Anno'] ) ) {
				$anni[] = (int) $riga['Anno'];
			}
		}

		return array_values( array_unique( $anni ) );
	}

	/**
	 * Lo stato di questo albo che serve al confronto.
	 *
	 * @param array<int, int>    $anni   Anni degli atti del backup.
	 * @param \DateTimeImmutable $adesso Istante dell'analisi.
	 * @return array<string, mixed>
	 */
	public static function contesto( array $anni, \DateTimeImmutable $adesso ): array {
		$tipi = array();
		$voci = get_terms(
			array(
				'taxonomy'   => TASSONOMIA_TIPO_ATTO,
				'hide_empty' => false,
			)
		);

		foreach ( is_array( $voci ) ? $voci : array() as $voce ) {
			$durata = Durate::del_tipo( (int) $voce->term_id );

			$tipi[ self::chiave( $voce->name ) ] = array(
				'id'     => (int) $voce->term_id,
				'nome'   => (string) $voce->name,
				'durata' => is_wp_error( $durata ) ? null : $durata,
			);
		}

		$numerazione = array();

		foreach ( array_unique( $anni ) as $anno ) {
			$numerazione[ $anno ] = array(
				'riga'  => Repertorio::anno_verificato( $anno ),
				'usati' => Repertorio::numeri_usati( $anno ),
			);
		}

		$festivi = Festivi::impostazione();
		$elenco  = null === $festivi ? Festivi::leggi_elenco( Festivi::proposta() ) : $festivi['elenco'];
		$scelte  = array();

		foreach ( DatiProcedimento::DATI as $dato ) {
			$scelte[ $dato ] = DatiProcedimento::scelta( $dato );
		}

		return array(
			'importati'    => self::importati(),
			'oggi'         => $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' ),
			'anno'         => Repertorio::anno_di( $adesso ),
			'tipi'         => $tipi,
			'numerazione'  => $numerazione,
			'festivi'      => is_wp_error( $elenco ) ? array() : $elenco,
			'festivi_da'   => null === $festivi ? 'proposta' : 'impostazione',
			'scelte'       => $scelte,
			'responsabili' => DatiProcedimento::elenco( DatiProcedimento::RESPONSABILE ),
			'uffici'       => DatiProcedimento::elenco( DatiProcedimento::UFFICIO ),
		);
	}

	/**
	 * Il rapporto dell'importazione a secco.
	 *
	 * @param array<string, mixed> $letto    Quello che il lettore ha letto.
	 * @param array<string, mixed> $contesto Lo stato di questo albo, da `contesto()`.
	 * @return array<string, mixed>
	 */
	public static function rapporto( array $letto, array $contesto ): array {
		$tabelle  = $letto['tabelle'];
		$generali = array();

		foreach ( self::TABELLE_NECESSARIE as $tabella ) {
			if ( ! isset( $tabelle[ $tabella ] ) ) {
				$generali[] = self::segnalazione(
					'dubbio',
					'tabella_assente',
					sprintf(
						/* translators: %s: nome della tabella. */
						__( 'Nel backup manca la tabella %s: le informazioni che contiene risultano assenti per tutti gli atti.', 'albo-pretorio-pa' ),
						$letto['prefisso'] . $tabella
					)
				);
			}
		}

		$campi = self::campi( $letto );

		foreach ( $campi['non_riconosciuti'] as $tabella => $colonne ) {
			foreach ( $colonne as $colonna => $piene ) {
				$generali[] = self::segnalazione(
					'dubbio',
					'campo_non_riconosciuto',
					sprintf(
						/* translators: 1: colonna, 2: tabella, 3: righe in cui e' compilata. */
						__( 'Il campo %1$s della tabella %2$s non e\' conosciuto: non si porterebbe. E\' compilato in %3$d righe.', 'albo-pretorio-pa' ),
						$colonna,
						$tabella,
						$piene
					)
				);
			}
		}

		// Un identificativo che compare due volte non dice quale riga valga: si toglie dall'indice e si segnala, invece di tenere l'ultima.
		$per_id    = static function ( array $righe, string $chiave, string $tabella, array $campi ) use ( &$generali, $letto ): array {
			$indice = array();
			$doppi  = array();
			$lunghi = array();

			foreach ( $righe as $riga ) {
				if ( isset( $riga[ $chiave ] ) ) {
					$id = (string) $riga[ $chiave ];

					// Un nome piu' lungo di qualunque nome vero si ricopierebbe su ogni atto che lo cita: la riga non si usa.
					foreach ( $campi as $campo ) {
						if ( strlen( (string) ( $riga[ $campo ] ?? '' ) ) > self::NOME_MASSIMO ) {
							$lunghi[ $id ] = true;
						}
					}

					if ( isset( $indice[ $id ] ) || isset( $doppi[ $id ] ) ) {
						$doppi[ $id ] = true;
					}

					$indice[ $id ] = $riga;
				}
			}

			if ( array() !== $lunghi ) {
				$indice = array_diff_key( $indice, $lunghi );
				$elenco = array_keys( $lunghi );
				sort( $elenco, SORT_NATURAL );

				$generali[] = self::segnalazione(
					'conflitto',
					'nome_troppo_lungo',
					sprintf(
						/* translators: 1: nome della tabella, 2: identificativi, 3: lunghezza massima. */
						__( 'Nella tabella %1$s alcune righe hanno un nome piu\' lungo di %3$d caratteri (identificativi %2$s): non si usano, e gli atti che le citano le trovano assenti.', 'albo-pretorio-pa' ),
						$letto['prefisso'] . $tabella,
						implode( ', ', $elenco ),
						self::NOME_MASSIMO
					)
				);
			}

			if ( array() !== $doppi ) {
				$indice = array_diff_key( $indice, $doppi );
				$elenco = array_keys( $doppi );
				sort( $elenco, SORT_NATURAL );

				$generali[] = self::segnalazione(
					'conflitto',
					'id_doppio_tabella',
					sprintf(
						/* translators: 1: nome della tabella, 2: identificativi. */
						__( 'Nella tabella %1$s piu\' righe hanno lo stesso identificativo (%2$s): non si sa quale valga, e gli atti che le citano le trovano assenti.', 'albo-pretorio-pa' ),
						$letto['prefisso'] . $tabella,
						implode( ', ', $elenco )
					)
				);
			}

			return $indice;
		};
		$categorie = $per_id( $tabelle['albopretorio_categorie'] ?? array(), 'IdCategoria', 'albopretorio_categorie', array( 'Nome' ) );
		$enti      = $per_id( $tabelle['albopretorio_enti'] ?? array(), 'IdEnte', 'albopretorio_enti', array( 'Nome' ) );
		$persone   = $per_id( $tabelle['albopretorio_resprocedura'] ?? array(), 'IdResponsabile', 'albopretorio_resprocedura', array( 'Cognome', 'Nome' ) );
		$uffici    = $per_id( $tabelle['albopretorio_unitaorganizzative'] ?? array(), 'IdUO', 'albopretorio_unitaorganizzative', array( 'Nome' ) );
		$etichette = array();

		foreach ( $tabelle['albopretorio_attimeta'] ?? array() as $riga ) {
			$meta   = trim( (string) ( $riga['Meta'] ?? '' ) );
			$valore = trim( (string) ( $riga['Value'] ?? '' ) );

			$etichette[ (string) ( $riga['IdAtto'] ?? '' ) ][] = '' === $valore ? $meta : $meta . ': ' . $valore;
		}

		$allegati = array();

		foreach ( $tabelle['albopretorio_allegati'] ?? array() as $riga ) {
			$allegati[ (string) ( $riga['IdAtto'] ?? '' ) ][] = $riga;
		}

		$atti_letti = $tabelle['albopretorio_atti'] ?? array();
		$doppioni   = array();

		// Un numero interno assente o portato da piu' atti non dice di chi siano allegati ed etichette: quegli atti non li ricevono.
		$id_contati = array_count_values(
			array_map(
				static function ( array $riga ): string {
					return (string) ( $riga['IdAtto'] ?? '' );
				},
				$atti_letti
			)
		);
		$id_doppi   = array();

		foreach ( $id_contati as $id_atto => $quante ) {
			if ( $quante > 1 || '' === trim( (string) $id_atto ) ) {
				$id_doppi[ (string) $id_atto ] = true;
			}
		}

		if ( array() !== $id_doppi ) {
			$elenco = array_map(
				static function ( string $id_atto ): string {
					return '' === trim( $id_atto ) ? __( 'vuoto', 'albo-pretorio-pa' ) : $id_atto;
				},
				array_map( 'strval', array_keys( $id_doppi ) )
			);
			sort( $elenco, SORT_NATURAL );

			$generali[] = self::segnalazione(
				'conflitto',
				'id_atto_doppio',
				sprintf(
					/* translators: %s: numeri interni. */
					__( 'Piu\' atti hanno lo stesso numero interno, o non ce l\'hanno (%s): i loro allegati e le loro etichette non si possono attribuire.', 'albo-pretorio-pa' ),
					implode( ', ', $elenco )
				)
			);
		}

		foreach ( $atti_letti as $riga ) {
			$numero = self::intero( $riga['Numero'] ?? null );

			if ( null !== $numero && $numero > 0 ) {
				$doppioni[ ( self::intero( $riga['Anno'] ?? null ) ?? 0 ) . '/' . $numero ][] = (string) ( $riga['IdAtto'] ?? '' );
			}
		}

		// L'elenco degli atti con lo stesso numero sta una volta sola, fra le segnalazioni generali: sugli atti c'e' solo quanti sono.
		foreach ( $doppioni as $chiave => $doppi ) {
			if ( count( $doppi ) > 1 ) {
				list( $anno_doppio, $numero_doppio ) = explode( '/', (string) $chiave );

				$generali[] = self::segnalazione(
					'conflitto',
					'numero_doppio',
					sprintf(
						/* translators: 1: numero/anno, 2: elenco dei numeri interni. */
						__( 'Il numero %1$s e\' portato da piu\' atti: numeri interni %2$s.', 'albo-pretorio-pa' ),
						$numero_doppio . '/' . $anno_doppio,
						implode( ', ', $doppi )
					)
				);
			}
		}

		if ( ! is_array( $contesto['importati'] ?? null ) ) {
			$generali[] = self::segnalazione( 'conflitto', 'importati_non_letti', __( 'Questo albo non ha risposto alla lettura degli atti gia\' importati: non si puo\' dire quali atti del backup ci siano gia\'.', 'albo-pretorio-pa' ) );
		}

		$file_usati = array();
		$atti       = array();
		$misura     = 0;
		$massimo    = (int) ( $contesto['rapporto_massimo'] ?? self::RAPPORTO_MASSIMO );

		foreach ( $atti_letti as $riga ) {
			// Il rapporto si misura atto per atto: oltre il massimo ci si ferma e lo si dice, invece di esaurire la memoria.
			if ( $misura > $massimo ) {
				$generali[] = self::segnalazione(
					'conflitto',
					'rapporto_troppo_grande',
					sprintf(
						/* translators: 1: atti analizzati, 2: atti nel backup. */
						__( 'Il rapporto e\' diventato troppo grande per essere mostrato: atti analizzati %1$d su %2$d, gli altri no. Il riepilogo e le categorie contano solo gli atti analizzati; la numerazione e i file non citati si calcolano invece su tutto il backup.', 'albo-pretorio-pa' ),
						count( $atti ),
						count( $atti_letti )
					)
				);
				break;
			}

			$importato = self::gia_importato( $riga, $contesto, $id_doppi );

			if ( null !== $importato ) {
				$atti[]  = $importato;
				$misura += strlen( serialize( $importato ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- solo per misurare la grandezza dell'atto nel rapporto.

				// I file di un atto gia' importato sono citati: non sono file che nessuno usa.
				foreach ( $allegati[ $importato['id'] ] ?? array() as $allegato ) {
					$file = self::trova_file( (string) ( $allegato['Allegato'] ?? '' ), $letto['file'] );

					if ( $letto['zip'] && null !== $file['nome'] ) {
						$file_usati[ $file['nome'] ] = true;
					}
				}

				continue;
			}

			$atti[]  = self::atto(
				$riga,
				array(
					'categorie' => $categorie,
					'enti'      => $enti,
					'persone'   => $persone,
					'uffici'    => $uffici,
					'etichette' => $etichette,
					'allegati'  => $allegati,
					'doppioni'  => $doppioni,
					'id_doppi'  => $id_doppi,
					'file'      => $letto['file'],
					'zip'       => $letto['zip'],
				),
				$contesto,
				$file_usati
			);
			$misura += strlen( serialize( end( $atti ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- solo per misurare la grandezza dell'atto nel rapporto.
		}

		// Gli atti non analizzati contano lo stesso per i file citati: un file di quegli atti non e' un file che nessuno cita.
		foreach ( array_slice( $atti_letti, count( $atti ) ) as $riga ) {
			$id = (string) ( $riga['IdAtto'] ?? '' );

			if ( ! $letto['zip'] || self::bozza( $riga ) || isset( $id_doppi[ $id ] ) ) {
				continue;
			}

			foreach ( $allegati[ $id ] ?? array() as $allegato ) {
				$file = self::trova_file( (string) ( $allegato['Allegato'] ?? '' ), $letto['file'] );

				if ( null !== $file['nome'] ) {
					$file_usati[ $file['nome'] ] = true;
				}
			}
		}

		$ids = array_flip(
			array_map(
				static function ( array $riga ): string {
					return (string) ( $riga['IdAtto'] ?? '' );
				},
				$atti_letti
			)
		);

		// Gli allegati di atti che non ci sono: una nota sola, con i numeri interni, anche quando sono molti.
		$senza_atto = array();
		$quanti     = 0;

		foreach ( $allegati as $id_atto => $righe ) {
			if ( ! isset( $ids[ $id_atto ] ) ) {
				$senza_atto[] = (string) $id_atto;
				$quanti      += count( $righe );
			}
		}

		if ( array() !== $senza_atto ) {
			sort( $senza_atto, SORT_NATURAL );

			$generali[] = self::segnalazione(
				'nota',
				'allegati_senza_atto',
				sprintf(
					/* translators: 1: numero di allegati, 2: numero di atti, 3: numeri interni degli atti. */
					__( 'Allegati che rimandano ad atti che nel backup non ci sono: %1$d, di %2$d atti (numeri interni %3$s). Non si porterebbero.', 'albo-pretorio-pa' ),
					$quanti,
					count( $senza_atto ),
					implode( ', ', $senza_atto )
				)
			);
		}

		$orfani = array_values( array_diff( array_keys( $letto['file'] ), array_keys( $file_usati ) ) );
		sort( $orfani );

		if ( array() !== $orfani ) {
			$generali[] = self::segnalazione(
				'nota',
				'file_orfani',
				sprintf(
					/* translators: %d: numero di file. */
					_n( '%d file dell\'archivio non e\' citato da nessun allegato: non si porterebbe.', '%d file dell\'archivio non sono citati da nessun allegato: non si porterebbero.', count( $orfani ), 'albo-pretorio-pa' ),
					count( $orfani )
				)
			);
		}

		if ( ! $letto['zip'] ) {
			$generali[] = self::segnalazione(
				'dubbio',
				'file_non_verificabili',
				__( 'E\' stato letto un file SQL senza archivio: i file degli allegati non ci sono, e non si puo\' dire se esistono ne\' se sono integri.', 'albo-pretorio-pa' )
			);
		}

		// La numerazione guarda tutti gli atti del backup, anche quelli che il rapporto non ha potuto analizzare.
		$numerazione = self::numerazione( array_map( array( self::class, 'numero_anno' ), $atti_letti ), $letto['opzioni'], $contesto, $generali );
		$riepilogo   = self::riepilogo( $atti, count( $atti_letti ) );

		return array(
			'pacchetto'    => array(
				'sql'      => $letto['sql'],
				'zip'      => $letto['zip'],
				'prefisso' => $letto['prefisso'],
				'tabelle'  => array_map( 'count', $tabelle ),
				'ignorate' => $letto['ignorate'],
				'file'     => count( $letto['file'] ),
			),
			'oggi'         => $contesto['oggi'],
			'riepilogo'    => $riepilogo,
			'atti'         => $atti,
			'categorie'    => self::categorie( $tabelle['albopretorio_categorie'] ?? array(), $categorie, $atti, $contesto ),
			'numerazione'  => $numerazione,
			'campi'        => $campi,
			'orfani'       => $orfani,
			'segnalazioni' => $generali,
			'procedimento' => array(
				'scelte'     => $contesto['scelte'],
				'festivi_da' => $contesto['festivi_da'],
			),
		);
	}

	/**
	 * Gli atti gia' portati in questo albo da un'importazione, per identificativo d'origine.
	 *
	 * Vale anche la bozza che un'importazione interrotta ha lasciato: non e'
	 * importata, ma chi importa deve sapere che c'e' per toglierla.
	 *
	 * @return array<string, array{atto_id: int, stato: string}>|null Nulla se la lettura non ha risposto.
	 */
	public static function importati(): ?array {
		global $wpdb;

		$righe = Lettura::righe(
			$wpdb->prepare(
				'SELECT m.meta_value AS origine, p.ID AS atto_id, p.post_status AS stato FROM %i m JOIN %i p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type = %s ORDER BY p.ID',
				$wpdb->postmeta,
				$wpdb->posts,
				META_ORIGINE_IMPORTAZIONE,
				TIPO
			),
			array( 'origine', 'atto_id', 'stato' )
		);

		if ( null === $righe ) {
			return null;
		}

		$importati = array();

		foreach ( $righe as $riga ) {
			$origine = (string) $riga['origine'];

			// Un atto pubblicato vince sulla bozza lasciata a meta' con la stessa origine.
			if ( isset( $importati[ $origine ] ) && 'publish' === $importati[ $origine ]['stato'] ) {
				continue;
			}

			$importati[ $origine ] = array(
				'atto_id' => (int) $riga['atto_id'],
				'stato'   => (string) $riga['stato'],
			);
		}

		return $importati;
	}

	/**
	 * L'atto del backup gia' importato, nella forma breve del rapporto, o nulla.
	 *
	 * Un atto importato non si analizza piu': i suoi numeri sono gia' di
	 * questo albo, e confrontarli con se stessi li darebbe per gia' usati.
	 *
	 * @param array<string, string|null> $riga     Riga della tabella degli atti.
	 * @param array<string, mixed>       $contesto Stato di questo albo.
	 * @param array<string, bool>        $id_doppi Numeri interni assenti o ripetuti.
	 * @return array<string, mixed>|null
	 */
	private static function gia_importato( array $riga, array $contesto, array $id_doppi ): ?array {
		$id        = (string) ( $riga['IdAtto'] ?? '' );
		$importato = is_array( $contesto['importati'] ?? null ) ? ( $contesto['importati'][ $id ] ?? null ) : null;

		if ( null === $importato || 'publish' !== $importato['stato'] || isset( $id_doppi[ $id ] ) ) {
			return null;
		}

		$numero = self::numero_anno( $riga );

		return array(
			'id'           => $id,
			'numero'       => $numero['numero'],
			'scritto'      => (string) ( $riga['Numero'] ?? 'NULL' ),
			'anno'         => $numero['anno'],
			'oggetto'      => '',
			'inizio'       => self::data( $riga['DataInizio'] ?? null ),
			'fine'         => self::data( $riga['DataFine'] ?? null ),
			'giorni'       => null,
			'stato'        => 'importato',
			'pubblico'     => __( 'Gia\' importato: e\' in questo albo e non si tocca piu\'.', 'albo-pretorio-pa' ),
			'in_vista'     => false,
			'tipo'         => '',
			'categoria'    => (string) ( $riga['IdCategoria'] ?? '' ),
			'tipo_id'      => null,
			'atto_id'      => $importato['atto_id'],
			'dati'         => array(),
			'documenti'    => array(),
			'esito'        => self::IMPORTATO,
			'segnalazioni' => array(),
		);
	}

	/**
	 * Un atto del backup, come verrebbe ricostruito.
	 *
	 * @param array<string, string|null> $riga       Riga della tabella degli atti.
	 * @param array<string, mixed>       $indici     Tabelle collegate, per identificativo.
	 * @param array<string, mixed>       $contesto   Stato di questo albo.
	 * @param array<string, bool>        $file_usati File dell'archivio citati, aggiornato.
	 * @return array<string, mixed>
	 */
	private static function atto( array $riga, array $indici, array $contesto, array &$file_usati ): array {
		$id      = (string) ( $riga['IdAtto'] ?? '' );
		$grezzo  = $riga['Numero'] ?? null;
		$numero  = self::intero( $grezzo );
		$anno    = self::intero( $riga['Anno'] ?? null ) ?? 0;
		$inizio  = self::data( $riga['DataInizio'] ?? null );
		$fine    = self::data( $riga['DataFine'] ?? null );
		$annullo = self::data( $riga['DataAnnullamento'] ?? null );
		$segnala = array();
		$oggetto = (string) ( $riga['Oggetto'] ?? '' );

		if ( ! self::utf8( $oggetto ) ) {
			$segnala[] = self::segnalazione( 'dubbio', 'testo_non_leggibile', __( 'L\'oggetto contiene caratteri che non si leggono: forse il backup non e\' in UTF-8.', 'albo-pretorio-pa' ) );
			$oggetto   = (string) wp_check_invalid_utf8( $oggetto, true );
		}

		$atto = array(
			'id'           => $id,
			'numero'       => self::numero_anno( $riga )['numero'],
			'scritto'      => null === $grezzo ? 'NULL' : (string) $grezzo,
			'anno'         => $anno,
			'oggetto'      => $oggetto,
			'inizio'       => $inizio,
			'fine'         => $fine,
			'giorni'       => null,
			'stato'        => '',
			'pubblico'     => '',
			'in_vista'     => false,
			'tipo'         => '',
			'categoria'    => (string) ( $riga['IdCategoria'] ?? '' ),
			'tipo_id'      => null,
			'scrittura'    => array(
				'riferimento'  => '',
				'procedimento' => array(),
				'annullamento' => null,
				'motivo'       => '',
			),
			'dati'         => array(),
			'documenti'    => array(),
			'esito'        => '',
			'segnalazioni' => array(),
		);

		// Solo lo zero vero e' una bozza: un numero assente, negativo o illeggibile e' un dato da correggere.
		if ( self::bozza( $riga ) ) {
			$atto['stato']        = 'bozza';
			$atto['pubblico']     = __( 'Non si porta: e\' una bozza mai pubblicata. Si chiude prima in Albo Pretorio On line, o si ricrea qui.', 'albo-pretorio-pa' );
			$atto['esito']        = self::ESCLUSO;
			$atto['segnalazioni'] = array( self::segnalazione( 'nota', 'bozza', __( 'Bozza mai pubblicata: non si porta.', 'albo-pretorio-pa' ) ) );

			return $atto;
		}

		if ( ! is_array( $contesto['importati'] ?? null ) ) {
			$segnala[] = self::segnalazione( 'conflitto', 'importati_non_letti', __( 'Non si sa se questo atto e\' gia\' stato importato: la lettura di questo albo non ha risposto.', 'albo-pretorio-pa' ) );
		}

		// Numero e anno.
		// Il registro di questo albo arriva fino a Repertorio::MASSIMO: oltre, il numero non ci sta.
		if ( null === $numero || $numero <= 0 || $numero > Repertorio::MASSIMO ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_non_valido',
				sprintf(
					/* translators: %s: valore scritto nel backup. */
					__( 'Il numero scritto nel backup (%s) non e\' un numero di registro: l\'atto non si puo\' portare finche\' non si corregge.', 'albo-pretorio-pa' ),
					null === $grezzo ? 'NULL' : (string) $grezzo
				)
			);
			$numero = 0;
		}

		if ( $anno < 1 || $anno > 9999 ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'anno_non_valido',
				sprintf(
					/* translators: %d: anno scritto nel backup. */
					__( 'L\'anno del numero (%d) non e\' un anno: il numero non si puo\' portare finche\' non si corregge.', 'albo-pretorio-pa' ),
					$anno
				)
			);
		}

		$doppi = $indici['doppioni'][ $anno . '/' . $numero ] ?? array();

		if ( count( $doppi ) > 1 ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_doppio',
				sprintf(
					/* translators: 1: numero/anno, 2: quanti atti lo portano. */
					__( 'Il numero %1$s e\' portato da %2$d atti, elencati fra le segnalazioni generali: in questo albo un numero e\' di un atto solo.', 'albo-pretorio-pa' ),
					$numero . '/' . $anno,
					count( $doppi )
				)
			);
		}

		$numerazione = $contesto['numerazione'][ $anno ] ?? null;

		// Se la numerazione di questo albo non ha risposto, non si sa se il numero e' gia' dato o oltre la partenza: l'atto non e' portabile.
		if ( $numero > 0 && $anno >= 1 && $anno <= 9999 && ( ! is_array( $numerazione ) || null === $numerazione['usati'] || false === $numerazione['riga'] ) ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numerazione_non_letta',
				sprintf(
					/* translators: %d: anno. */
					__( 'La numerazione di questo albo per il %d non ha risposto: non si puo\' dire se il numero e\' gia\' stato dato.', 'albo-pretorio-pa' ),
					$anno
				)
			);
		}

		if ( is_array( $numerazione ) && is_array( $numerazione['usati'] ) && isset( $numerazione['usati'][ $numero ] ) ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_gia_usato',
				sprintf(
					/* translators: %s: numero/anno. */
					__( 'Il numero %s e\' gia\' stato dato a un atto di questo albo.', 'albo-pretorio-pa' ),
					$numero . '/' . $anno
				)
			);
		} elseif ( is_array( $numerazione ) && is_array( $numerazione['riga'] ) && $numero > $numerazione['riga']['partenza'] ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_oltre_partenza',
				sprintf(
					/* translators: 1: numero/anno, 2: ultimo numero dichiarato. */
					__( 'Il numero %1$s viene dopo l\'ultimo numero del registro precedente dichiarato in questo albo (%2$d): uno dei due registri e\' sbagliato.', 'albo-pretorio-pa' ),
					$numero . '/' . $anno,
					$numerazione['riga']['partenza']
				)
			);
		}

		if ( null !== $inizio && (int) substr( $inizio, 0, 4 ) !== $anno ) {
			$segnala[] = self::segnalazione(
				'dubbio',
				'anno_diverso',
				sprintf(
					/* translators: 1: anno del numero, 2: data di inizio. */
					__( 'L\'anno del numero (%1$d) non e\' l\'anno di inizio della pubblicazione (%2$s): il numero si conserva com\'e\', ma va controllato.', 'albo-pretorio-pa' ),
					$anno,
					self::leggibile( $inizio )
				)
			);
		}

		// Date, durata e visibilita'.
		self::date( $atto, $riga, $indici, $contesto, $segnala, $annullo );

		// Tipo di atto.
		$id_categoria = (string) ( $riga['IdCategoria'] ?? '' );
		$categoria    = $indici['categorie'][ $id_categoria ] ?? null;

		if ( null === $categoria ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'categoria_assente',
				sprintf(
					/* translators: %s: identificativo della categoria. */
					__( 'La categoria %s dell\'atto non c\'e\' nel backup: il tipo di atto non si sa.', 'albo-pretorio-pa' ),
					'' === $id_categoria ? '?' : $id_categoria
				)
			);
		} else {
			$nome         = (string) ( $categoria['Nome'] ?? '' );
			$tipo         = $contesto['tipi'][ self::chiave( $nome ) ] ?? null;
			$atto['tipo'] = null === $tipo
				? sprintf(
					/* translators: %s: nome della categoria. */
					__( '%s (tipo da creare)', 'albo-pretorio-pa' ),
					$nome
				)
				: $tipo['nome'];

			$atto['tipo_id'] = null === $tipo ? null : $tipo['id'];

			// Le decisioni ancora aperte sul tipo valgono anche per ogni suo atto.
			$aperte = self::tipo_di( $categoria, $contesto )['segnalazioni'];

			if ( array() !== $aperte ) {
				$segnala[] = self::segnalazione(
					'dubbio',
					'tipo_da_decidere',
					sprintf(
						/* translators: 1: nome della categoria, 2: decisioni aperte. */
						__( 'Il tipo di atto della categoria "%1$s" ha decisioni aperte: %2$s', 'albo-pretorio-pa' ),
						$nome,
						implode( ' ', wp_list_pluck( $aperte, 'testo' ) )
					)
				);
			}
		}

		// Dati dell'atto e del procedimento.
		self::dati( $atto, $riga, $indici, $contesto, $segnala );

		// Documenti, solo se il numero interno dice di chi sono.
		if ( isset( $indici['id_doppi'][ $id ] ) ) {
			$segnala[] = self::segnalazione( 'conflitto', 'id_atto_doppio', __( 'Il numero interno di questo atto e\' assente o portato anche da altri atti: allegati ed etichette non si possono attribuire.', 'albo-pretorio-pa' ) );
		} else {
			self::documenti( $atto, $indici, $file_usati, $segnala );
		}

		$atto['segnalazioni'] = $segnala;
		$atto['esito']        = self::esito( $segnala );

		return $atto;
	}

	/**
	 * Date, durata, stato e visibilita' di un atto numerato.
	 *
	 * @param array<string, mixed>       $atto     Atto ricostruito, aggiornato.
	 * @param array<string, string|null> $riga     Riga della tabella degli atti.
	 * @param array<string, mixed>       $indici   Tabelle collegate.
	 * @param array<string, mixed>       $contesto Stato di questo albo.
	 * @param array<int, array>          $segnala  Segnalazioni dell'atto, aggiornate.
	 * @param string|null                $annullo  Data di annullamento.
	 */
	private static function date( array &$atto, array $riga, array $indici, array $contesto, array &$segnala, ?string $annullo ): void {
		$oggi   = $contesto['oggi'];
		$inizio = $atto['inizio'];
		$fine   = $atto['fine'];

		if ( null === $inizio ) {
			$segnala[] = self::segnalazione( 'conflitto', 'inizio_non_valido', __( 'La data di inizio della pubblicazione manca o non e\' una data valida.', 'albo-pretorio-pa' ) );
		}

		if ( null === $fine ) {
			$segnala[] = self::segnalazione( 'dubbio', 'fine_assente', __( 'La data di fine manca o non e\' una data valida. In questo albo un atto senza fine non si vede: arriverebbe invisibile al pubblico.', 'albo-pretorio-pa' ) );
		}

		if ( null !== $inizio && null !== $fine && $fine < $inizio ) {
			$segnala[] = self::segnalazione( 'conflitto', 'fine_prima_inizio', __( 'La data di fine viene prima della data di inizio.', 'albo-pretorio-pa' ) );
		}

		if ( null !== $inizio && null !== $fine && $fine >= $inizio ) {
			$atto['giorni'] = self::giorni( $inizio, $fine );
			self::durata( $atto, $riga, $indici, $contesto, $segnala );
		}

		if ( null !== $annullo ) {
			$motivo        = trim( (string) ( $riga['MotivoAnnullamento'] ?? '' ) );
			$atto['stato'] = 'annullato';

			$atto['scrittura']['annullamento'] = $annullo;
			$atto['scrittura']['motivo']       = $motivo;
			$segnala[]                         = self::segnalazione(
				'dubbio',
				'annullato',
				sprintf(
					/* translators: 1: data di annullamento, 2: motivo. */
					__( 'Atto annullato il %1$s (motivo: %2$s). Si porterebbe annullato, con numero e motivo; cosa ne veda il pubblico nel periodo di pubblicazione e\' una scelta ancora aperta di questo albo.', 'albo-pretorio-pa' ),
					self::leggibile( $annullo ),
					'' === $motivo ? __( 'non indicato', 'albo-pretorio-pa' ) : $motivo
				)
			);
		}

		if ( null === $fine || $fine < $oggi ) {
			if ( '' === $atto['stato'] ) {
				$atto['stato'] = 'finito';
			}

			$atto['pubblico'] = __( 'Invisibile al pubblico: la pubblicazione e\' finita. Si consulterebbe dalla schermata riservata degli atti scaduti.', 'albo-pretorio-pa' );
			return;
		}

		if ( null !== $inizio && $inizio > $oggi ) {
			if ( '' === $atto['stato'] ) {
				$atto['stato'] = 'futuro';
			}

			$atto['pubblico'] = __( 'Inizio futuro: questo albo non programma le pubblicazioni.', 'albo-pretorio-pa' );
			$segnala[]        = self::segnalazione( 'dubbio', 'inizio_futuro', __( 'La pubblicazione comincia in un giorno futuro: questo albo non programma le pubblicazioni. Si pubblica dopo l\'importazione, o si lascia finire in Albo Pretorio On line.', 'albo-pretorio-pa' ) );
			return;
		}

		if ( '' === $atto['stato'] ) {
			$atto['stato'] = 'in_pubblicazione';
		}

		$atto['in_vista'] = true;
		$atto['pubblico'] = sprintf(
			/* translators: %s: data di fine. */
			__( 'Visibile al pubblico fino a tutto il %s.', 'albo-pretorio-pa' ),
			self::leggibile( $fine )
		);

		$segnala[] = self::segnalazione(
			'dubbio',
			'conferma_dati_personali',
			__( 'Atto ancora in pubblicazione: in Albo Pretorio On line non c\'e\' la conferma del responsabile sui dati personali che questo albo chiede prima di pubblicare. Va data da chi importa, guardando il documento.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Il confronto fra la durata scritta e i giorni della categoria.
	 *
	 * La durata scritta e' la differenza in giorni fra inizio e fine: e' la
	 * stessa regola di questo albo, in cui il giorno di pubblicazione non conta.
	 *
	 * @param array<string, mixed>       $atto     Atto ricostruito, aggiornato.
	 * @param array<string, string|null> $riga     Riga della tabella degli atti.
	 * @param array<string, mixed>       $indici   Tabelle collegate.
	 * @param array<string, mixed>       $contesto Stato di questo albo.
	 * @param array<int, array>          $segnala  Segnalazioni dell'atto, aggiornate.
	 */
	private static function durata( array &$atto, array $riga, array $indici, array $contesto, array &$segnala ): void {
		$categoria = $indici['categorie'][ (string) ( $riga['IdCategoria'] ?? '' ) ] ?? null;
		$attesi    = null === $categoria ? null : self::intero( $categoria['Giorni'] ?? null );

		$atto['dati'][ __( 'Durata', 'albo-pretorio-pa' ) ] = sprintf(
			/* translators: %d: numero di giorni. */
			_n( '%d giorno, origine: date scritte in Albo Pretorio On line', '%d giorni, origine: date scritte in Albo Pretorio On line', (int) $atto['giorni'], 'albo-pretorio-pa' ),
			(int) $atto['giorni']
		);

		if ( null === $attesi || $attesi <= 0 ) {
			return;
		}

		if ( $atto['giorni'] < $attesi ) {
			$segnala[] = self::segnalazione(
				'dubbio',
				'durata_breve',
				sprintf(
					/* translators: 1: giorni scritti, 2: giorni della categoria. */
					__( 'La pubblicazione dura %1$d giorni, meno dei %2$d della sua categoria: le date si conservano come sono, ma va controllato perche\' sia stata accorciata.', 'albo-pretorio-pa' ),
					$atto['giorni'],
					$attesi
				)
			);
			return;
		}

		if ( $atto['giorni'] === $attesi ) {
			return;
		}

		// Una fine piu' lunga e' spiegata se tutti i giorni saltati, dalla fine calcolata in poi, sono festivi.
		$calcolata = \DateTimeImmutable::createFromFormat( '!Y-m-d', $atto['inizio'], wp_timezone() )->add( new \DateInterval( 'P' . $attesi . 'D' ) );
		$giorno    = $calcolata;
		$saltati   = array();

		while ( $giorno->format( 'Y-m-d' ) < $atto['fine'] ) {
			$festivo = Festivi::festivo( $giorno, $contesto['festivi'] );

			if ( null === $festivo ) {
				$saltati = null;
				break;
			}

			$saltati[] = $giorno->format( 'd/m/Y' ) . ' ' . $festivo;
			$giorno    = $giorno->add( new \DateInterval( 'P1D' ) );
		}

		// Lo slittamento finisce sul primo giorno non festivo: una fine che cade a sua volta di festa non si spiega cosi'.
		if ( null !== $saltati && null !== Festivi::festivo( \DateTimeImmutable::createFromFormat( '!Y-m-d', $atto['fine'], wp_timezone() ), $contesto['festivi'] ) ) {
			$saltati = null;
		}

		if ( null !== $saltati ) {
			$segnala[] = self::segnalazione(
				'nota',
				'slittamento_festivi',
				sprintf(
					/* translators: %s: giorni festivi saltati. */
					__( 'La fine e\' stata spostata oltre i giorni festivi (%s): e\' lo slittamento al primo giorno non festivo.', 'albo-pretorio-pa' ),
					implode( ', ', $saltati )
				)
			);
			return;
		}

		$segnala[] = self::segnalazione(
			'dubbio',
			'durata_lunga',
			sprintf(
				/* translators: 1: giorni scritti, 2: giorni della categoria. */
				__( 'La pubblicazione dura %1$d giorni, piu\' dei %2$d della sua categoria, e i giorni in piu\' non sono festivi: forse una proroga o una correzione a mano.', 'albo-pretorio-pa' ),
				$atto['giorni'],
				$attesi
			)
		);
	}

	/**
	 * I dati dell'atto e del procedimento.
	 *
	 * @param array<string, mixed>       $atto     Atto ricostruito, aggiornato.
	 * @param array<string, string|null> $riga     Riga della tabella degli atti.
	 * @param array<string, mixed>       $indici   Tabelle collegate.
	 * @param array<string, mixed>       $contesto Stato di questo albo.
	 * @param array<int, array>          $segnala  Segnalazioni dell'atto, aggiornate.
	 */
	private static function dati( array &$atto, array $riga, array $indici, array $contesto, array &$segnala ): void {
		$riferimento = trim( (string) ( $riga['Riferimento'] ?? '' ) );

		if ( '' !== $riferimento ) {
			$pulito = DatiAtto::numero_da_ingresso( $riferimento );

			// Come per i dati del procedimento, di un testo lunghissimo il rapporto mostra solo l'inizio.
			$atto['dati'][ __( 'Numero proprio (proposto dal riferimento)', 'albo-pretorio-pa' ) ] = strlen( $riferimento ) > self::NOME_MASSIMO ? mb_strcut( $riferimento, 0, self::NOME_MASSIMO, 'UTF-8' ) . ' [...]' : $riferimento;

			if ( $pulito !== $riferimento ) {
				$segnala[] = self::segnalazione( 'dubbio', 'riferimento_non_conservabile', __( 'Il riferimento contiene a capo, marcatori o caratteri che il numero proprio non accetta: non si conserverebbe alla lettera.', 'albo-pretorio-pa' ) );
			} else {
				$atto['scrittura']['riferimento'] = $riferimento;
			}
		}

		$atto['dati'][ __( 'Organo', 'albo-pretorio-pa' ) ]           = __( 'non indicato (Albo Pretorio On line non lo registra)', 'albo-pretorio-pa' );
		$atto['dati'][ __( 'Data di adozione', 'albo-pretorio-pa' ) ] = __( 'non indicata (Albo Pretorio On line non la registra)', 'albo-pretorio-pa' );

		$ente = (string) ( $riga['Ente'] ?? '0' );

		if ( '' !== $ente && '0' !== $ente ) {
			$nome      = isset( $indici['enti'][ $ente ] ) ? trim( (string) ( $indici['enti'][ $ente ]['Nome'] ?? '' ) ) : '';
			$segnala[] = self::segnalazione(
				'dubbio',
				'altra_amministrazione',
				'' === $nome
					? sprintf(
						/* translators: %s: identificativo dell'ente. */
						__( 'L\'atto e\' di un\'altra amministrazione (ente %s), che nel backup non c\'e\'.', 'albo-pretorio-pa' ),
						$ente
					)
					: sprintf(
						/* translators: %s: nome dell'amministrazione. */
						__( 'L\'atto e\' di un\'altra amministrazione (%s): questo albo non gestisce ancora le amministrazioni ospitate.', 'albo-pretorio-pa' ),
						$nome
					)
			);
		}

		$valori = array(
			DatiProcedimento::RESPONSABILE => '',
			DatiProcedimento::UFFICIO      => '',
			DatiProcedimento::RICHIEDENTE  => trim( (string) ( $riga['Richiedente'] ?? '' ) ),
		);
		// Il testo com'e' scritto nel backup, prima di togliere gli spazi ai lati: un carattere di controllo ai margini si perderebbe.
		$grezzi = array(
			DatiProcedimento::RICHIEDENTE => (string) ( $riga['Richiedente'] ?? '' ),
		);

		$responsabile = (string) ( $riga['RespProc'] ?? '' );

		if ( '' !== $responsabile && '0' !== $responsabile ) {
			$persona = $indici['persone'][ $responsabile ] ?? null;

			if ( null === $persona ) {
				$segnala[] = self::segnalazione( 'dubbio', 'responsabile_assente', __( 'Il responsabile del procedimento indicato non c\'e\' fra le persone del backup.', 'albo-pretorio-pa' ) );
			} else {
				$valori[ DatiProcedimento::RESPONSABILE ] = self::persona( $persona );
				$grezzi[ DatiProcedimento::RESPONSABILE ] = (string) ( $persona['Cognome'] ?? '' ) . (string) ( $persona['Nome'] ?? '' );
			}
		}

		$ufficio = (string) ( $riga['IdUnitaOrganizzativa'] ?? '' );

		if ( '' !== $ufficio && '0' !== $ufficio ) {
			if ( isset( $indici['uffici'][ $ufficio ] ) ) {
				$valori[ DatiProcedimento::UFFICIO ] = trim( (string) ( $indici['uffici'][ $ufficio ]['Nome'] ?? '' ) );
				$grezzi[ DatiProcedimento::UFFICIO ] = (string) ( $indici['uffici'][ $ufficio ]['Nome'] ?? '' );
			} else {
				$segnala[] = self::segnalazione( 'dubbio', 'ufficio_assente', __( 'L\'ufficio indicato non c\'e\' fra gli uffici del backup.', 'albo-pretorio-pa' ) );
			}
		}

		foreach ( $valori as $dato => $valore ) {
			if ( '' === $valore ) {
				continue;
			}

			$scelta = $contesto['scelte'][ $dato ] ?? null;
			$come   = DatiProcedimento::USA === $scelta
				? __( 'si mostra', 'albo-pretorio-pa' )
				: ( DatiProcedimento::NON_USA === $scelta ? __( 'si conserva e non si mostra', 'albo-pretorio-pa' ) : __( 'si conserva; si mostra se l\'amministrazione sceglie di usarlo', 'albo-pretorio-pa' ) );

			// Un testo lunghissimo non si ricopia per intero nel rapporto: se ne mostra l'inizio.
			$mostrato = strlen( $valore ) > self::NOME_MASSIMO ? mb_strcut( $valore, 0, self::NOME_MASSIMO, 'UTF-8' ) . ' [...]' : $valore;

			$atto['dati'][ DatiProcedimento::etichetta( $dato ) ] = $mostrato . ' (' . $come . ')';

			// Lunghezza e caratteri di controllo si guardano prima di leggere il nome come elenco: un testo
			// con milioni di a capo non si divide in milioni di righe.
			$valido = strlen( $valore ) <= 4 * DatiProcedimento::LUNGHEZZA_MASSIMA
				&& 1 !== preg_match( '/[\x00-\x1F\x7F]/', $grezzi[ $dato ] ?? $valore )
				&& 1 !== preg_match( '/[\x00-\x1F\x7F]/', $valore );

			if ( $valido ) {
				$letto  = DatiProcedimento::leggi_elenco( $valore );
				$valido = ! is_wp_error( $letto ) && array( $valore ) === $letto;
			}

			if ( ! $valido ) {
				$segnala[] = self::segnalazione(
					'dubbio',
					'nome_non_conservabile',
					sprintf(
						/* translators: %s: etichetta del dato. */
						__( '%s: il testo contiene caratteri che questo albo non accetta, o e\' troppo lungo; non si conserverebbe alla lettera.', 'albo-pretorio-pa' ),
						DatiProcedimento::etichetta( $dato )
					)
				);
				continue;
			}

			$atto['scrittura']['procedimento'][ $dato ] = $valore;

			$elenco = DatiProcedimento::RESPONSABILE === $dato ? $contesto['responsabili'] : ( DatiProcedimento::UFFICIO === $dato ? $contesto['uffici'] : null );

			if ( is_array( $elenco ) && ! in_array( self::chiave( $valore ), array_map( array( self::class, 'chiave' ), $elenco ), true ) ) {
				$segnala[] = self::segnalazione(
					'nota',
					'fuori_elenco',
					sprintf(
						/* translators: 1: etichetta del dato, 2: valore. */
						__( '%1$s "%2$s" non e\' nell\'elenco di questo albo: resterebbe sull\'atto come un nome tolto dall\'elenco.', 'albo-pretorio-pa' ),
						DatiProcedimento::etichetta( $dato ),
						$valore
					)
				);
			}
		}

		$letti    = self::soggetti( (string) ( $riga['Soggetti'] ?? '' ) );
		$soggetti = $letti['ids'];

		if ( array() !== $soggetti ) {
			$nomi = array();

			// Si nominano le prime persone e si contano le altre: il rapporto non ricopia un elenco senza fine su ogni atto.
			foreach ( array_slice( $soggetti, 0, self::SOGGETTI_NOMINATI ) as $soggetto ) {
				$nomi[] = isset( $indici['persone'][ $soggetto ] ) ? self::persona( $indici['persone'][ $soggetto ] ) : '?';
			}

			if ( count( $soggetti ) > self::SOGGETTI_NOMINATI ) {
				$nomi[] = sprintf(
					/* translators: %d: numero di altre persone. */
					__( 'e altre %d', 'albo-pretorio-pa' ),
					count( $soggetti ) - self::SOGGETTI_NOMINATI
				);
			}

			if ( $letti['troncato'] ) {
				$nomi[] = sprintf(
					/* translators: %d: numero di voci lette. */
					__( 'e altre ancora: l\'elenco supera %d voci e non si legge per intero', 'albo-pretorio-pa' ),
					self::SOGGETTI_LETTI
				);
			}

			$segnala[] = self::segnalazione(
				'nota',
				'soggetti_senza_posto',
				sprintf(
					/* translators: %s: nomi delle persone. */
					__( 'Altre persone collegate all\'atto (%s): questo albo non ha un campo per loro, non si porterebbero.', 'albo-pretorio-pa' ),
					implode( ', ', $nomi )
				)
			);
		}

		if ( '' !== trim( (string) ( $riga['Informazioni'] ?? '' ) ) ) {
			$segnala[] = self::segnalazione( 'nota', 'informazioni_senza_posto', __( 'L\'atto ha delle note (campo Informazioni): questo albo non ha un campo per loro, non si porterebbero.', 'albo-pretorio-pa' ) );
		}

		if ( isset( $indici['etichette'][ $atto['id'] ] ) && ! isset( $indici['id_doppi'][ $atto['id'] ] ) ) {
			$segnala[] = self::segnalazione(
				'nota',
				'etichette_senza_posto',
				sprintf(
					/* translators: %s: nomi delle etichette. */
					__( 'L\'atto ha delle etichette di raggruppamento (%s): questo albo non le ha, non si porterebbero.', 'albo-pretorio-pa' ),
					implode( ', ', array_unique( $indici['etichette'][ $atto['id'] ] ) )
				)
			);
		}
	}

	/**
	 * I documenti dell'atto: ruolo, presenza del file, impronta.
	 *
	 * @param array<string, mixed> $atto       Atto ricostruito, aggiornato.
	 * @param array<string, mixed> $indici     Tabelle collegate.
	 * @param array<string, bool>  $file_usati File dell'archivio citati, aggiornato.
	 * @param array<int, array>    $segnala    Segnalazioni dell'atto, aggiornate.
	 */
	private static function documenti( array &$atto, array $indici, array &$file_usati, array &$segnala ): void {
		$righe          = $indici['allegati'][ $atto['id'] ] ?? array();
		$principali     = 0;
		$senza_archivio = false;
		$grave          = self::visibile( $atto ) ? 'conflitto' : 'dubbio';

		// Le versioni precedenti di Albo Pretorio On line non distinguono documento e allegati, e nelle successive chi pubblicava
		// lasciava spesso la scelta preimpostata ad allegato: un file solo e' per forza il documento.
		$senza_natura = array() !== $righe && ! array_key_exists( 'Natura', reset( $righe ) );
		$unico        = 1 === count( $righe ) && ( $senza_natura || 'D' !== strtoupper( trim( (string) ( reset( $righe )['Natura'] ?? '' ) ) ) );

		foreach ( $righe as $riga ) {
			$natura = $unico ? 'D' : strtoupper( trim( (string) ( $riga['Natura'] ?? 'A' ) ) );
			$titolo = trim( (string) ( $riga['TitoloAllegato'] ?? '' ) );
			$file   = self::trova_file( (string) ( $riga['Allegato'] ?? '' ), $indici['file'] );

			if ( 'D' === $natura ) {
				++$principali;
			}

			$documento = array(
				'titolo'     => '' === $titolo ? basename( str_replace( '\\', '/', (string) ( $riga['Allegato'] ?? '' ) ) ) : $titolo,
				'ruolo'      => 'D' === $natura ? __( 'documento', 'albo-pretorio-pa' ) : __( 'allegato', 'albo-pretorio-pa' ),
				'principale' => 'D' === $natura,
				'file'       => $file['nome'],
				'impronta'   => null === $file['nome'] || ! isset( $indici['file'][ $file['nome'] ] ) ? '' : (string) $indici['file'][ $file['nome'] ]['impronta'],
				'verifica'   => '',
			);

			if ( ! $indici['zip'] ) {
				$documento['verifica'] = __( 'non verificabile senza archivio', 'albo-pretorio-pa' );
				$senza_archivio        = true;
			} elseif ( null === $file['nome'] ) {
				$documento['verifica'] = __( 'file mancante nell\'archivio', 'albo-pretorio-pa' );
				$segnala[]             = self::segnalazione(
					$grave,
					'file_mancante',
					sprintf(
						/* translators: %s: titolo del documento. */
						__( 'Il file di "%s" non c\'e\' nell\'archivio.', 'albo-pretorio-pa' ),
						$documento['titolo']
					)
				);
			} else {
				$file_usati[ $file['nome'] ] = true;
				$atteso                      = strtolower( trim( (string) ( $riga['Impronta'] ?? '' ) ) );
				$trovato                     = $indici['file'][ $file['nome'] ]['impronta'];

				if ( $file['altrove'] ) {
					$segnala[] = self::segnalazione(
						'nota',
						'file_altrove',
						sprintf(
							/* translators: %s: titolo del documento. */
							__( 'Il file di "%s" e\' stato trovato per nome, in una cartella diversa da quella registrata.', 'albo-pretorio-pa' ),
							$documento['titolo']
						)
					);
				}

				if ( '' === $atteso ) {
					$documento['verifica'] = __( 'trovato; impronta non registrata in Albo Pretorio On line', 'albo-pretorio-pa' );
				} elseif ( hash_equals( $atteso, $trovato ) ) {
					$documento['verifica'] = __( 'trovato, impronta uguale a quella registrata al caricamento', 'albo-pretorio-pa' );
				} else {
					$documento['verifica'] = __( 'trovato, ma diverso da quello caricato', 'albo-pretorio-pa' );
					$segnala[]             = self::segnalazione(
						$grave,
						'impronta_diversa',
						sprintf(
							/* translators: %s: titolo del documento. */
							__( 'Il file di "%s" non e\' quello caricato: la sua impronta non coincide con quella registrata al caricamento.', 'albo-pretorio-pa' ),
							$documento['titolo']
						)
					);
				}
			}

			if ( '0' === trim( (string) ( $riga['DocIntegrale'] ?? '1' ) ) ) {
				$segnala[] = self::segnalazione(
					'dubbio',
					'per_estratto',
					sprintf(
						/* translators: %s: titolo del documento. */
						__( '"%s" e\' pubblicato per estratto: questo albo non ha questa indicazione, va riportata nell\'oggetto o nel documento.', 'albo-pretorio-pa' ),
						$documento['titolo']
					)
				);
			}

			$atto['documenti'][] = $documento;
		}

		if ( $senza_archivio ) {
			$segnala[] = self::segnalazione( 'dubbio', 'documenti_non_verificabili', __( 'E\' stato letto il solo file SQL: i file dei documenti di questo atto non si possono controllare.', 'albo-pretorio-pa' ) );
		}

		if ( $unico && $senza_natura ) {
			$segnala[] = self::segnalazione( 'nota', 'principale_unico', __( 'Questa versione di Albo Pretorio On line non distingue documento e allegati: l\'unico file dell\'atto diventa il documento principale.', 'albo-pretorio-pa' ) );
		} elseif ( $unico ) {
			$segnala[] = self::segnalazione( 'nota', 'principale_unico', __( 'Albo Pretorio On line segna l\'unico file dell\'atto come allegato: diventa il documento principale.', 'albo-pretorio-pa' ) );
		} elseif ( $senza_natura ) {
			$segnala[] = self::segnalazione( 'dubbio', 'principale_assente', __( 'Questa versione di Albo Pretorio On line non distingue documento e allegati: il documento principale lo sceglie chi importa.', 'albo-pretorio-pa' ) );
		} elseif ( 0 === $principali ) {
			$segnala[] = self::segnalazione( 'dubbio', 'principale_assente', __( 'Nessun allegato e\' segnato come documento: il documento principale lo sceglie chi importa.', 'albo-pretorio-pa' ) );
		} elseif ( $principali > 1 ) {
			$segnala[] = self::segnalazione(
				'dubbio',
				'principali_multipli',
				sprintf(
					/* translators: %d: numero di documenti. */
					__( '%d allegati sono segnati come documento: in questo albo il documento principale e\' uno, lo sceglie chi importa.', 'albo-pretorio-pa' ),
					$principali
				)
			);
		}
	}

	/**
	 * Il file dell'archivio che corrisponde al percorso registrato.
	 *
	 * Il percorso registrato e' completo, sul server di provenienza; nell'archivio
	 * i file stanno senza la cartella degli allegati davanti. Vince il file il
	 * cui nome coincide con la parte finale piu' lunga del percorso; se nessuno
	 * coincide, un file con lo stesso nome in un'altra cartella, purche' sia uno solo.
	 *
	 * @param string                                            $percorso Percorso registrato.
	 * @param array<string, array{impronta: string, byte: int}> $file     File dell'archivio.
	 * @return array{nome: string|null, altrove: bool}
	 */
	private static function trova_file( string $percorso, array $file ): array {
		$percorso = '/' . ltrim( str_replace( '\\', '/', $percorso ), '/' );
		$migliore = null;

		foreach ( array_keys( $file ) as $nome ) {
			$nome = (string) $nome;

			if ( str_ends_with( $percorso, '/' . ltrim( $nome, '/' ) ) && ( null === $migliore || strlen( $nome ) > strlen( $migliore ) ) ) {
				$migliore = $nome;
			}
		}

		if ( null !== $migliore ) {
			return array(
				'nome'    => $migliore,
				'altrove' => false,
			);
		}

		$base   = basename( $percorso );
		$uguali = array();

		foreach ( array_keys( $file ) as $nome ) {
			if ( basename( (string) $nome ) === $base ) {
				$uguali[] = (string) $nome;
			}
		}

		return array(
			'nome'    => 1 === count( $uguali ) ? $uguali[0] : null,
			'altrove' => 1 === count( $uguali ),
		);
	}

	/**
	 * Le categorie del backup e il tipo di atto che diventerebbero.
	 *
	 * @param array<int, array<string, string|null>>    $righe     Righe della tabella delle categorie.
	 * @param array<string, array<string, string|null>> $per_id    Categorie per identificativo.
	 * @param array<int, array<string, mixed>>          $atti      Atti ricostruiti.
	 * @param array<string, mixed>                      $contesto  Stato di questo albo.
	 * @return array<int, array<string, mixed>>
	 */
	private static function categorie( array $righe, array $per_id, array $atti, array $contesto ): array {
		$conteggio = array();

		// Si conta per identificativo della categoria: due categorie che diventano lo stesso tipo restano distinte.
		foreach ( $atti as $atto ) {
			$conteggio[ $atto['categoria'] ] = ( $conteggio[ $atto['categoria'] ] ?? 0 ) + 1;
		}

		$esito = array();

		foreach ( $righe as $riga ) {
			$nome     = trim( (string) ( $riga['Nome'] ?? '' ) );
			$giorni   = self::intero( $riga['Giorni'] ?? null ) ?? 0;
			$genitore = (string) ( $riga['Genitore'] ?? '0' );
			$tipo     = self::tipo_di( $riga, $contesto );

			$esito[] = array(
				'id'           => (string) ( $riga['IdCategoria'] ?? '' ),
				'nome'         => $nome,
				'madre'        => ( '0' !== $genitore && isset( $per_id[ $genitore ] ) ) ? trim( (string) ( $per_id[ $genitore ]['Nome'] ?? '' ) ) : '',
				'giorni'       => $giorni,
				'atti'         => $conteggio[ (string) ( $riga['IdCategoria'] ?? '' ) ] ?? 0,
				'corrisponde'  => $tipo['corrisponde'],
				'segnalazioni' => $tipo['segnalazioni'],
			);
		}

		return $esito;
	}

	/**
	 * Il tipo di atto che una categoria diventerebbe, e le decisioni che restano aperte.
	 *
	 * @param array<string, string|null> $riga     Riga della tabella delle categorie.
	 * @param array<string, mixed>       $contesto Stato di questo albo.
	 * @return array{chiave: string, corrisponde: string, segnalazioni: array<int, array>}
	 */
	private static function tipo_di( array $riga, array $contesto ): array {
		$nome    = trim( (string) ( $riga['Nome'] ?? '' ) );
		$giorni  = self::intero( $riga['Giorni'] ?? null ) ?? 0;
		$tipo    = $contesto['tipi'][ self::chiave( $nome ) ] ?? null;
		$segnala = array();

		if ( null === $tipo ) {
			$corrisponde = __( 'tipo di atto da creare; l\'origine della durata (norma o scelta dell\'amministrazione) la dichiara chi importa', 'albo-pretorio-pa' );
			$segnala[]   = self::segnalazione( 'dubbio', 'tipo_da_creare', __( 'In questo albo non c\'e\' un tipo di atto con questo nome.', 'albo-pretorio-pa' ) );
			$chiave      = sprintf(
				/* translators: %s: nome della categoria. */
				__( '%s (tipo da creare)', 'albo-pretorio-pa' ),
				$nome
			);
		} else {
			$chiave = $tipo['nome'];

			if ( null === $tipo['durata'] ) {
				$corrisponde = __( 'tipo di atto esistente, senza durata configurata', 'albo-pretorio-pa' );
			} else {
				$corrisponde = sprintf(
					/* translators: 1: giorni, 2: origine, 3: estremi. */
					__( 'tipo di atto esistente: %1$d giorni, origine %2$s %3$s', 'albo-pretorio-pa' ),
					$tipo['durata']['giorni'],
					'norma' === $tipo['durata']['origine'] ? __( 'norma', 'albo-pretorio-pa' ) : __( 'scelta dell\'amministrazione', 'albo-pretorio-pa' ),
					$tipo['durata']['estremi']
				);

				if ( $giorni > 0 && $tipo['durata']['giorni'] !== $giorni ) {
					$segnala[] = self::segnalazione(
						'dubbio',
						'durata_tipo_diversa',
						sprintf(
							/* translators: 1: giorni della categoria, 2: giorni del tipo. */
							__( 'La categoria ha %1$d giorni, il tipo di atto di questo albo %2$d: gli atti importati conservano le loro date, i nuovi seguiranno il tipo.', 'albo-pretorio-pa' ),
							$giorni,
							$tipo['durata']['giorni']
						)
					);
				}
			}
		}

		if ( $giorni <= 0 ) {
			$segnala[] = self::segnalazione( 'dubbio', 'categoria_senza_giorni', __( 'La categoria non ha giorni di pubblicazione: il tipo di atto non avrebbe durata, e i suoi atti nuovi non si pubblicherebbero finche\' qualcuno non la imposta.', 'albo-pretorio-pa' ) );
		}

		return array(
			'chiave'       => $chiave,
			'corrisponde'  => $corrisponde,
			'segnalazioni' => $segnala,
		);
	}

	/**
	 * Se una riga degli atti e' una bozza: solo lo zero vero, non un numero assente o illeggibile.
	 *
	 * @param array<string, string|null> $riga Riga della tabella degli atti.
	 * @return bool
	 */
	private static function bozza( array $riga ): bool {
		return null !== ( $riga['Numero'] ?? null ) && '0' === trim( (string) $riga['Numero'] );
	}

	/**
	 * Numero di registro e anno di una riga degli atti, come li legge il rapporto: un numero non valido vale zero.
	 *
	 * @param array<string, string|null> $riga Riga della tabella degli atti.
	 * @return array{numero: int, anno: int}
	 */
	private static function numero_anno( array $riga ): array {
		$numero = self::intero( $riga['Numero'] ?? null );

		return array(
			'numero' => ( null !== $numero && $numero > 0 && $numero <= Repertorio::MASSIMO ) ? $numero : 0,
			'anno'   => self::intero( $riga['Anno'] ?? null ) ?? 0,
		);
	}

	/**
	 * La numerazione: per anno, il contatore e la proposta di partenza.
	 *
	 * @param array<int, array<string, mixed>> $atti     Numero e anno di tutti gli atti del backup.
	 * @param array<string, string|null>       $opzioni  Impostazioni del plugin di provenienza.
	 * @param array<string, mixed>             $contesto Stato di questo albo.
	 * @param array<int, array>                $generali Segnalazioni generali, aggiornate.
	 * @return array<string, mixed>
	 */
	private static function numerazione( array $atti, array $opzioni, array $contesto, array &$generali ): array {
		$anni = array();

		foreach ( $atti as $atto ) {
			if ( $atto['numero'] > 0 ) {
				$anni[ $atto['anno'] ][] = $atto['numero'];
			}
		}

		ksort( $anni );
		$per_anno = array();

		foreach ( array_keys( $anni ) as $anno ) {
			// Un anno che non e' un anno ha gia' il suo conflitto sull'atto.
			if ( $anno < 1 || $anno > 9999 ) {
				continue;
			}

			if ( ! isset( $contesto['numerazione'][ $anno ] ) || null === $contesto['numerazione'][ $anno ]['usati'] || false === $contesto['numerazione'][ $anno ]['riga'] ) {
				$generali[] = self::segnalazione(
					'conflitto',
					'numerazione_non_letta',
					sprintf(
						/* translators: %d: anno. */
						__( 'La numerazione di questo albo per il %d non ha risposto: non si puo\' dire se i numeri del backup sono gia\' stati dati.', 'albo-pretorio-pa' ),
						$anno
					)
				);
			}
		}

		foreach ( $anni as $anno => $numeri ) {
			$unici    = array_unique( $numeri );
			$massimo  = max( $unici );
			$mancanti = $massimo - count( $unici );

			$per_anno[] = array(
				'anno'     => (int) $anno,
				'atti'     => count( $numeri ),
				'massimo'  => $massimo,
				'mancanti' => $mancanti,
				'nuovo'    => is_array( $contesto['numerazione'][ $anno ]['riga'] ?? null ) ? $contesto['numerazione'][ $anno ]['riga'] : null,
			);

			if ( $mancanti > 0 ) {
				$generali[] = self::segnalazione(
					'nota',
					'numeri_mancanti',
					sprintf(
						/* translators: 1: quanti numeri, 2: anno, 3: numero massimo. */
						_n( 'Nel %2$d manca %1$d numero fra 1 e %3$d: un atto cancellato, o una partenza diversa da 1.', 'Nel %2$d mancano %1$d numeri fra 1 e %3$d: atti cancellati, o una partenza diversa da 1.', $mancanti, 'albo-pretorio-pa' ),
						$mancanti,
						$anno,
						$massimo
					)
				);
			}
		}

		$contatore = self::intero( $opzioni['opt_AP_NumeroProgressivo'] ?? null );
		$anno_cont = self::intero( $opzioni['opt_AP_AnnoProgressivo'] ?? null );
		$proposta  = null;

		if ( null !== $contatore && null !== $anno_cont && ( $contatore < 0 || $contatore - 1 >= Repertorio::MASSIMO ) ) {
			// Un contatore fuori dai limiti della partenza non da' una proposta: la partenza si dichiara a mano.
			$generali[] = self::segnalazione(
				'dubbio',
				'contatore_non_valido',
				sprintf(
					/* translators: %s: valore del contatore. */
					__( 'Il contatore di Albo Pretorio On line (%s) non e\' un numero che questo albo possa usare come partenza: l\'ultimo numero usato si dichiara a mano.', 'albo-pretorio-pa' ),
					(string) $opzioni['opt_AP_NumeroProgressivo']
				)
			);
		} elseif ( null !== $contatore && null !== $anno_cont ) {
			$massimo  = isset( $anni[ $anno_cont ] ) ? max( $anni[ $anno_cont ] ) : 0;
			$proposta = array(
				'anno'      => $anno_cont,
				'contatore' => $contatore,
				'massimo'   => $massimo,
				'ultimo'    => max( $massimo, $contatore - 1 ),
			);

			// La partenza di questo albo sta sotto Repertorio::MASSIMO: una proposta oltre non si fa.
			if ( $proposta['ultimo'] >= Repertorio::MASSIMO ) {
				$proposta   = null;
				$generali[] = self::segnalazione( 'dubbio', 'proposta_non_valida', __( 'L\'ultimo numero usato in Albo Pretorio On line e\' il piu\' alto che il registro di questo albo possa contenere: la partenza non si puo\' proporre.', 'albo-pretorio-pa' ) );
			}

			if ( $contatore - 1 !== $massimo ) {
				$generali[] = self::segnalazione(
					'dubbio',
					'contatore_diverso',
					sprintf(
						/* translators: 1: anno, 2: prossimo numero del contatore, 3: numero piu' alto trovato. */
						__( 'Il contatore di Albo Pretorio On line per il %1$d darebbe come prossimo numero %2$d, ma il numero piu\' alto fra gli atti di quell\'anno e\' %3$d.', 'albo-pretorio-pa' ),
						$anno_cont,
						$contatore,
						$massimo
					)
				);
			}

			if ( $anno_cont !== $contesto['anno'] ) {
				$generali[] = self::segnalazione(
					'nota',
					'contatore_anno_vecchio',
					sprintf(
						/* translators: 1: anno del contatore, 2: anno in corso. */
						__( 'Il contatore di Albo Pretorio On line e\' fermo al %1$d, mentre l\'anno in corso e\' il %2$d.', 'albo-pretorio-pa' ),
						$anno_cont,
						$contesto['anno']
					)
				);
			}
		} else {
			$generali[] = self::segnalazione( 'dubbio', 'contatore_assente', __( 'Nel backup non c\'e\' il contatore dei numeri: l\'ultimo numero usato si ricava solo dagli atti.', 'albo-pretorio-pa' ) );
		}

		return array(
			'anni'     => $per_anno,
			'proposta' => $proposta,
		);
	}

	/**
	 * I conteggi del rapporto.
	 *
	 * @param array<int, array<string, mixed>> $atti       Atti ricostruiti.
	 * @param int                              $nel_backup Atti nel backup, analizzati o no.
	 * @return array<string, array<string, int>>
	 */
	private static function riepilogo( array $atti, int $nel_backup ): array {
		$riepilogo = array(
			'esiti' => array(
				self::IMPORTABILE => 0,
				self::DA_DECIDERE => 0,
				self::CONFLITTO   => 0,
				self::ESCLUSO     => 0,
				self::IMPORTATO   => 0,
			),
			'stati' => array(),
		);

		foreach ( $atti as $atto ) {
			++$riepilogo['esiti'][ $atto['esito'] ];
			$riepilogo['stati'][ $atto['stato'] ] = ( $riepilogo['stati'][ $atto['stato'] ] ?? 0 ) + 1;
		}

		$riepilogo['totale'] = array(
			'atti'       => count( $atti ),
			'nel_backup' => $nel_backup,
		);

		return $riepilogo;
	}

	/**
	 * Le colonne del backup confrontate con quelle conosciute.
	 *
	 * @param array<string, mixed> $letto Quello che il lettore ha letto.
	 * @return array{non_riconosciuti: array<string, array<string, int>>, assenti: array<string, array<int, string>>}
	 */
	private static function campi( array $letto ): array {
		$campi = array(
			'non_riconosciuti' => array(),
			'assenti'          => array(),
		);

		foreach ( self::COLONNE as $tabella => $conosciute ) {
			if ( ! isset( $letto['colonne'][ $tabella ] ) ) {
				continue;
			}

			$presenti = $letto['colonne'][ $tabella ];

			foreach ( array_diff( $presenti, $conosciute ) as $colonna ) {
				$piene = 0;

				foreach ( $letto['tabelle'][ $tabella ] ?? array() as $riga ) {
					if ( null !== ( $riga[ $colonna ] ?? null ) && '' !== trim( (string) $riga[ $colonna ] ) ) {
						++$piene;
					}
				}

				$campi['non_riconosciuti'][ $letto['prefisso'] . $tabella ][ $colonna ] = $piene;
			}

			$assenti = array_values( array_diff( $conosciute, $presenti ) );

			if ( array() !== $assenti ) {
				$campi['assenti'][ $letto['prefisso'] . $tabella ] = $assenti;
			}
		}

		return $campi;
	}

	/**
	 * Se l'atto ricostruito sarebbe visibile al pubblico appena importato.
	 *
	 * Visibile e' l'atto la cui pubblicazione e' in corso oggi, annullato
	 * compreso: il suo documento lo vedrebbe chiunque.
	 *
	 * @param array<string, mixed> $atto Atto ricostruito.
	 * @return bool
	 */
	private static function visibile( array $atto ): bool {
		return true === $atto['in_vista'];
	}

	/**
	 * L'esito di un atto dalle sue segnalazioni.
	 *
	 * @param array<int, array{livello: string, codice: string, testo: string}> $segnala Segnalazioni.
	 * @return string
	 */
	private static function esito( array $segnala ): string {
		$livelli = array_column( $segnala, 'livello' );

		if ( in_array( 'conflitto', $livelli, true ) ) {
			return self::CONFLITTO;
		}

		return in_array( 'dubbio', $livelli, true ) ? self::DA_DECIDERE : self::IMPORTABILE;
	}

	/**
	 * Una segnalazione.
	 *
	 * @param string $livello Conflitto, dubbio o nota.
	 * @param string $codice  Codice stabile, per le prove.
	 * @param string $testo   Spiegazione per chi importa.
	 * @return array{livello: string, codice: string, testo: string}
	 */
	private static function segnalazione( string $livello, string $codice, string $testo ): array {
		return array(
			'livello' => $livello,
			'codice'  => $codice,
			'testo'   => $testo,
		);
	}

	/**
	 * Una data del backup, se e' un giorno vero.
	 *
	 * La data zero che il plugin di provenienza usa per "nessuna data" vale
	 * come assente.
	 *
	 * @param string|null $valore Valore letto.
	 * @return string|null AAAA-MM-GG.
	 */
	private static function data( ?string $valore ): ?string {
		if ( null === $valore ) {
			return null;
		}

		$valore = substr( trim( $valore ), 0, 10 );

		return DatiAtto::data_da_ingresso( $valore );
	}

	/**
	 * I giorni di calendario fra due date.
	 *
	 * @param string $da Data di inizio.
	 * @param string $a  Data di fine.
	 * @return int
	 */
	private static function giorni( string $da, string $a ): int {
		$inizio = \DateTimeImmutable::createFromFormat( '!Y-m-d', $da, wp_timezone() );
		$fine   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $a, wp_timezone() );

		return (int) $inizio->diff( $fine )->days;
	}

	/**
	 * Un intero scritto in cifre, o nullo.
	 *
	 * @param string|null $valore Valore letto.
	 * @return int|null
	 */
	private static function intero( ?string $valore ): ?int {
		if ( null === $valore || 1 !== preg_match( '/\A-?[0-9]{1,18}\z/', trim( $valore ) ) ) {
			return null;
		}

		return (int) trim( $valore );
	}

	/**
	 * Il nome di una persona, cognome e nome.
	 *
	 * @param array<string, string|null> $persona Riga della tabella delle persone.
	 * @return string
	 */
	private static function persona( array $persona ): string {
		return trim( (string) preg_replace( '/ {2,}/', ' ', trim( (string) ( $persona['Cognome'] ?? '' ) ) . ' ' . trim( (string) ( $persona['Nome'] ?? '' ) ) ) );
	}

	/**
	 * Gli identificativi delle persone collegate, dal testo serializzato del plugin di provenienza.
	 *
	 * Il testo non si deserializza: se ne leggono solo i valori numerici
	 * dell'elenco, chiave per valore.
	 *
	 * Le voci si leggono una alla volta e la lettura si ferma alla voce
	 * SOGGETTI_LETTI: un campo enorme non si espande in memoria.
	 *
	 * @param string $testo Valore del campo.
	 * @return array{ids: array<int, string>, troncato: bool} Gli identificativi distinti e se l'elenco e' stato letto solo in parte.
	 */
	private static function soggetti( string $testo ): array {
		$ids    = array();
		$voci   = 0;
		$inizio = 0;

		while ( 1 === preg_match( '/i:\d+;(?:i:(\d+)|s:\d+:"(\d+)");/', $testo, $trovato, PREG_OFFSET_CAPTURE, $inizio ) ) {
			if ( ++$voci > self::SOGGETTI_LETTI ) {
				break;
			}

			$ids[ isset( $trovato[2] ) && '' !== $trovato[2][0] ? $trovato[2][0] : $trovato[1][0] ] = true;

			$inizio = $trovato[0][1] + strlen( $trovato[0][0] );
		}

		return array(
			'ids'      => array_map( 'strval', array_keys( $ids ) ),
			'troncato' => $voci > self::SOGGETTI_LETTI,
		);
	}

	/**
	 * Se un testo e' UTF-8 valido.
	 *
	 * @param string $testo Testo.
	 * @return bool
	 */
	private static function utf8( string $testo ): bool {
		return '' === $testo || '' !== wp_check_invalid_utf8( $testo );
	}

	/**
	 * La chiave di confronto di un nome: minuscole, spazi ripuliti.
	 *
	 * @param string $nome Nome.
	 * @return string
	 */
	public static function chiave( string $nome ): string {
		return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $nome ) ), 'UTF-8' );
	}

	/**
	 * Una data AAAA-MM-GG scritta come GG/MM/AAAA.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	public static function leggibile( string $data ): string {
		$letta = \DateTimeImmutable::createFromFormat( '!Y-m-d', $data, wp_timezone() );

		return false === $letta ? $data : $letta->format( 'd/m/Y' );
	}
}
