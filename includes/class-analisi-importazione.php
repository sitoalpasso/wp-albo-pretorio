<?php
/**
 * L'importazione a secco: cosa porterebbe il backup, senza scrivere niente (ALBO-35).
 *
 * Riceve quello che `LettoreBackup` ha letto e lo confronta con lo stato del
 * nuovo albo: tipi di atto e loro durate, numeri gia' usati, scelte sui dati
 * del procedimento, elenco dei festivi. Ne esce un rapporto: per ogni atto
 * come verrebbe ricostruito, se sarebbe visibile al pubblico, e i conflitti e
 * i dubbi che chi importa deve risolvere prima; poi categorie, numerazione,
 * campi non riconosciuti e file.
 *
 * **Le date sono fatti avvenuti**: si leggono come giorni di calendario e non
 * si ricalcolano. Da quel momento vale la scadenza del nuovo albo, che e' una
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
 * Confronto fra il backup letto e il nuovo albo.
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
	 * Livelli delle segnalazioni, dal piu' grave.
	 *
	 * @var array<int, string>
	 */
	const LIVELLI = array( 'conflitto', 'dubbio', 'nota' );

	/**
	 * Le colonne conosciute di ciascuna tabella, con il loro destino nel nuovo albo.
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
	 * Lo stato del nuovo albo che serve al confronto.
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
				'riga'  => Repertorio::anno( $anno ),
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
	 * @param array<string, mixed> $contesto Lo stato del nuovo albo, da `contesto()`.
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

		$per_id    = static function ( array $righe, string $chiave ): array {
			$indice = array();

			foreach ( $righe as $riga ) {
				if ( isset( $riga[ $chiave ] ) ) {
					$indice[ (string) $riga[ $chiave ] ] = $riga;
				}
			}

			return $indice;
		};
		$categorie = $per_id( $tabelle['albopretorio_categorie'] ?? array(), 'IdCategoria' );
		$enti      = $per_id( $tabelle['albopretorio_enti'] ?? array(), 'IdEnte' );
		$persone   = $per_id( $tabelle['albopretorio_resprocedura'] ?? array(), 'IdResponsabile' );
		$uffici    = $per_id( $tabelle['albopretorio_unitaorganizzative'] ?? array(), 'IdUO' );
		$etichette = array();

		foreach ( $tabelle['albopretorio_attimeta'] ?? array() as $riga ) {
			$etichette[ (string) ( $riga['IdAtto'] ?? '' ) ][] = (string) ( $riga['Meta'] ?? '' );
		}

		$allegati = array();

		foreach ( $tabelle['albopretorio_allegati'] ?? array() as $riga ) {
			$allegati[ (string) ( $riga['IdAtto'] ?? '' ) ][] = $riga;
		}

		$atti_letti = $tabelle['albopretorio_atti'] ?? array();
		$doppioni   = array();

		foreach ( $atti_letti as $riga ) {
			$numero = self::intero( $riga['Numero'] ?? null );

			if ( null !== $numero && $numero > 0 ) {
				$doppioni[ self::intero( $riga['Anno'] ?? null ) . '/' . $numero ][] = (string) ( $riga['IdAtto'] ?? '' );
			}
		}

		$file_usati = array();
		$atti       = array();

		foreach ( $atti_letti as $riga ) {
			$atti[] = self::atto(
				$riga,
				array(
					'categorie' => $categorie,
					'enti'      => $enti,
					'persone'   => $persone,
					'uffici'    => $uffici,
					'etichette' => $etichette,
					'allegati'  => $allegati,
					'doppioni'  => $doppioni,
					'file'      => $letto['file'],
					'zip'       => $letto['zip'],
				),
				$contesto,
				$file_usati
			);
		}

		$ids = array_flip(
			array_map(
				static function ( array $riga ): string {
					return (string) ( $riga['IdAtto'] ?? '' );
				},
				$atti_letti
			)
		);

		foreach ( $allegati as $id_atto => $righe ) {
			if ( ! isset( $ids[ $id_atto ] ) ) {
				$generali[] = self::segnalazione(
					'nota',
					'allegati_senza_atto',
					sprintf(
						/* translators: 1: numero di allegati, 2: numero interno dell'atto. */
						_n( '%1$d allegato rimanda all\'atto interno %2$s, che nel backup non c\'e\': non si porterebbe.', '%1$d allegati rimandano all\'atto interno %2$s, che nel backup non c\'e\': non si porterebbero.', count( $righe ), 'albo-pretorio-pa' ),
						count( $righe ),
						$id_atto
					)
				);
			}
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

		$numerazione = self::numerazione( $atti, $letto['opzioni'], $contesto, $generali );
		$riepilogo   = self::riepilogo( $atti );

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
	 * Un atto del backup, come verrebbe ricostruito.
	 *
	 * @param array<string, string|null> $riga       Riga della tabella degli atti.
	 * @param array<string, mixed>       $indici     Tabelle collegate, per identificativo.
	 * @param array<string, mixed>       $contesto   Stato del nuovo albo.
	 * @param array<string, bool>        $file_usati File dell'archivio citati, aggiornato.
	 * @return array<string, mixed>
	 */
	private static function atto( array $riga, array $indici, array $contesto, array &$file_usati ): array {
		$id      = (string) ( $riga['IdAtto'] ?? '' );
		$numero  = self::intero( $riga['Numero'] ?? null ) ?? 0;
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
			'numero'       => $numero,
			'anno'         => $anno,
			'oggetto'      => $oggetto,
			'inizio'       => $inizio,
			'fine'         => $fine,
			'giorni'       => null,
			'stato'        => '',
			'pubblico'     => '',
			'in_vista'     => false,
			'tipo'         => '',
			'dati'         => array(),
			'documenti'    => array(),
			'esito'        => '',
			'segnalazioni' => array(),
		);

		if ( $numero <= 0 ) {
			$atto['stato']        = 'bozza';
			$atto['pubblico']     = __( 'Non si porta: e\' una bozza mai pubblicata. Si chiude prima nel vecchio albo, o si ricrea nel nuovo.', 'albo-pretorio-pa' );
			$atto['esito']        = self::ESCLUSO;
			$atto['segnalazioni'] = array( self::segnalazione( 'nota', 'bozza', __( 'Bozza mai pubblicata: non si porta.', 'albo-pretorio-pa' ) ) );

			return $atto;
		}

		// Numero e anno.
		$doppi = $indici['doppioni'][ $anno . '/' . $numero ] ?? array();

		if ( count( $doppi ) > 1 ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_doppio',
				sprintf(
					/* translators: 1: numero/anno, 2: elenco dei numeri interni. */
					__( 'Il numero %1$s e\' portato da piu\' atti (numeri interni %2$s): nel nuovo albo un numero e\' di un atto solo.', 'albo-pretorio-pa' ),
					$numero . '/' . $anno,
					implode( ', ', $doppi )
				)
			);
		}

		$numerazione = $contesto['numerazione'][ $anno ] ?? null;

		if ( is_array( $numerazione ) && is_array( $numerazione['usati'] ) && isset( $numerazione['usati'][ $numero ] ) ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_gia_usato',
				sprintf(
					/* translators: %s: numero/anno. */
					__( 'Il numero %s e\' gia\' stato dato a un atto del nuovo albo.', 'albo-pretorio-pa' ),
					$numero . '/' . $anno
				)
			);
		} elseif ( is_array( $numerazione ) && is_array( $numerazione['riga'] ) && $numero > $numerazione['riga']['partenza'] ) {
			$segnala[] = self::segnalazione(
				'conflitto',
				'numero_oltre_partenza',
				sprintf(
					/* translators: 1: numero/anno, 2: ultimo numero dichiarato. */
					__( 'Il numero %1$s viene dopo l\'ultimo numero del vecchio registro dichiarato nel nuovo albo (%2$d): uno dei due registri e\' sbagliato.', 'albo-pretorio-pa' ),
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
		}

		// Dati dell'atto e del procedimento.
		self::dati( $atto, $riga, $indici, $contesto, $segnala );

		// Documenti.
		self::documenti( $atto, $indici, $file_usati, $segnala );

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
	 * @param array<string, mixed>       $contesto Stato del nuovo albo.
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
			$segnala[] = self::segnalazione( 'dubbio', 'fine_assente', __( 'La data di fine manca o non e\' una data valida. Nel nuovo albo un atto senza fine non si vede: arriverebbe invisibile al pubblico.', 'albo-pretorio-pa' ) );
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
			$segnala[]     = self::segnalazione(
				'dubbio',
				'annullato',
				sprintf(
					/* translators: 1: data di annullamento, 2: motivo. */
					__( 'Atto annullato il %1$s (motivo: %2$s). Si porterebbe annullato, con numero e motivo; cosa ne veda il pubblico nel periodo di pubblicazione e\' una scelta ancora aperta del nuovo albo.', 'albo-pretorio-pa' ),
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

			$atto['pubblico'] = __( 'Inizio futuro: il nuovo albo non programma le pubblicazioni.', 'albo-pretorio-pa' );
			$segnala[]        = self::segnalazione( 'dubbio', 'inizio_futuro', __( 'La pubblicazione comincia in un giorno futuro: il nuovo albo non programma le pubblicazioni. Si pubblica dopo l\'importazione, o si lascia finire nel vecchio albo.', 'albo-pretorio-pa' ) );
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
			__( 'Atto ancora in pubblicazione: nel vecchio albo non c\'e\' la conferma del responsabile sui dati personali che il nuovo albo chiede prima di pubblicare. Va data da chi importa, guardando il documento.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Il confronto fra la durata scritta e i giorni della categoria.
	 *
	 * La durata scritta e' la differenza in giorni fra inizio e fine: e' la
	 * stessa regola del nuovo albo, in cui il giorno di pubblicazione non conta.
	 *
	 * @param array<string, mixed>       $atto     Atto ricostruito, aggiornato.
	 * @param array<string, string|null> $riga     Riga della tabella degli atti.
	 * @param array<string, mixed>       $indici   Tabelle collegate.
	 * @param array<string, mixed>       $contesto Stato del nuovo albo.
	 * @param array<int, array>          $segnala  Segnalazioni dell'atto, aggiornate.
	 */
	private static function durata( array &$atto, array $riga, array $indici, array $contesto, array &$segnala ): void {
		$categoria = $indici['categorie'][ (string) ( $riga['IdCategoria'] ?? '' ) ] ?? null;
		$attesi    = null === $categoria ? null : self::intero( $categoria['Giorni'] ?? null );

		$atto['dati'][ __( 'Durata', 'albo-pretorio-pa' ) ] = sprintf(
			/* translators: %d: numero di giorni. */
			_n( '%d giorno, origine: date scritte nel vecchio albo', '%d giorni, origine: date scritte nel vecchio albo', (int) $atto['giorni'], 'albo-pretorio-pa' ),
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
	 * @param array<string, mixed>       $contesto Stato del nuovo albo.
	 * @param array<int, array>          $segnala  Segnalazioni dell'atto, aggiornate.
	 */
	private static function dati( array &$atto, array $riga, array $indici, array $contesto, array &$segnala ): void {
		$riferimento = trim( (string) ( $riga['Riferimento'] ?? '' ) );

		if ( '' !== $riferimento ) {
			$pulito = DatiAtto::numero_da_ingresso( $riferimento );

			$atto['dati'][ __( 'Numero proprio (proposto dal riferimento)', 'albo-pretorio-pa' ) ] = $riferimento;

			if ( $pulito !== $riferimento ) {
				$segnala[] = self::segnalazione( 'dubbio', 'riferimento_non_conservabile', __( 'Il riferimento contiene a capo, marcatori o caratteri che il numero proprio non accetta: non si conserverebbe alla lettera.', 'albo-pretorio-pa' ) );
			}
		}

		$atto['dati'][ __( 'Organo', 'albo-pretorio-pa' ) ]           = __( 'non indicato (il vecchio albo non lo registra)', 'albo-pretorio-pa' );
		$atto['dati'][ __( 'Data di adozione', 'albo-pretorio-pa' ) ] = __( 'non indicata (il vecchio albo non la registra)', 'albo-pretorio-pa' );

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
						__( 'L\'atto e\' di un\'altra amministrazione (%s): il nuovo albo non gestisce ancora le amministrazioni ospitate.', 'albo-pretorio-pa' ),
						$nome
					)
			);
		}

		$valori = array(
			DatiProcedimento::RESPONSABILE => '',
			DatiProcedimento::UFFICIO      => '',
			DatiProcedimento::RICHIEDENTE  => trim( (string) ( $riga['Richiedente'] ?? '' ) ),
		);

		$responsabile = (string) ( $riga['RespProc'] ?? '' );

		if ( '' !== $responsabile && '0' !== $responsabile ) {
			$persona = $indici['persone'][ $responsabile ] ?? null;

			if ( null === $persona ) {
				$segnala[] = self::segnalazione( 'dubbio', 'responsabile_assente', __( 'Il responsabile del procedimento indicato non c\'e\' fra le persone del backup.', 'albo-pretorio-pa' ) );
			} else {
				$valori[ DatiProcedimento::RESPONSABILE ] = self::persona( $persona );
			}
		}

		$ufficio = (string) ( $riga['IdUnitaOrganizzativa'] ?? '' );

		if ( '' !== $ufficio && '0' !== $ufficio ) {
			if ( isset( $indici['uffici'][ $ufficio ] ) ) {
				$valori[ DatiProcedimento::UFFICIO ] = trim( (string) ( $indici['uffici'][ $ufficio ]['Nome'] ?? '' ) );
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

			$atto['dati'][ DatiProcedimento::etichetta( $dato ) ] = $valore . ' (' . $come . ')';

			$letto = DatiProcedimento::leggi_elenco( $valore );

			if ( is_wp_error( $letto ) || array( $valore ) !== $letto ) {
				$segnala[] = self::segnalazione(
					'dubbio',
					'nome_non_conservabile',
					sprintf(
						/* translators: %s: etichetta del dato. */
						__( '%s: il testo contiene caratteri che il nuovo albo non accetta, o e\' troppo lungo; non si conserverebbe alla lettera.', 'albo-pretorio-pa' ),
						DatiProcedimento::etichetta( $dato )
					)
				);
				continue;
			}

			$elenco = DatiProcedimento::RESPONSABILE === $dato ? $contesto['responsabili'] : ( DatiProcedimento::UFFICIO === $dato ? $contesto['uffici'] : null );

			if ( is_array( $elenco ) && ! in_array( self::chiave( $valore ), array_map( array( self::class, 'chiave' ), $elenco ), true ) ) {
				$segnala[] = self::segnalazione(
					'nota',
					'fuori_elenco',
					sprintf(
						/* translators: 1: etichetta del dato, 2: valore. */
						__( '%1$s "%2$s" non e\' nell\'elenco del nuovo albo: resterebbe sull\'atto come un nome tolto dall\'elenco.', 'albo-pretorio-pa' ),
						DatiProcedimento::etichetta( $dato ),
						$valore
					)
				);
			}
		}

		$soggetti = self::soggetti( (string) ( $riga['Soggetti'] ?? '' ) );

		if ( array() !== $soggetti ) {
			$nomi = array();

			foreach ( $soggetti as $soggetto ) {
				$nomi[] = isset( $indici['persone'][ $soggetto ] ) ? self::persona( $indici['persone'][ $soggetto ] ) : '?';
			}

			$segnala[] = self::segnalazione(
				'nota',
				'soggetti_senza_posto',
				sprintf(
					/* translators: %s: nomi delle persone. */
					__( 'Altre persone collegate all\'atto (%s): il nuovo albo non ha un campo per loro, non si porterebbero.', 'albo-pretorio-pa' ),
					implode( ', ', $nomi )
				)
			);
		}

		if ( '' !== trim( (string) ( $riga['Informazioni'] ?? '' ) ) ) {
			$segnala[] = self::segnalazione( 'nota', 'informazioni_senza_posto', __( 'L\'atto ha delle note (campo Informazioni): il nuovo albo non ha un campo per loro, non si porterebbero.', 'albo-pretorio-pa' ) );
		}

		if ( isset( $indici['etichette'][ $atto['id'] ] ) ) {
			$segnala[] = self::segnalazione(
				'nota',
				'etichette_senza_posto',
				sprintf(
					/* translators: %s: nomi delle etichette. */
					__( 'L\'atto ha delle etichette di raggruppamento (%s): il nuovo albo non le ha, non si porterebbero.', 'albo-pretorio-pa' ),
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
		$righe      = $indici['allegati'][ $atto['id'] ] ?? array();
		$principali = 0;
		$grave      = self::visibile( $atto ) ? 'conflitto' : 'dubbio';

		foreach ( $righe as $riga ) {
			$natura = strtoupper( trim( (string) ( $riga['Natura'] ?? 'A' ) ) );
			$titolo = trim( (string) ( $riga['TitoloAllegato'] ?? '' ) );
			$file   = self::trova_file( (string) ( $riga['Allegato'] ?? '' ), $indici['file'] );

			if ( 'D' === $natura ) {
				++$principali;
			}

			$documento = array(
				'titolo'   => '' === $titolo ? basename( str_replace( '\\', '/', (string) ( $riga['Allegato'] ?? '' ) ) ) : $titolo,
				'ruolo'    => 'D' === $natura ? __( 'documento', 'albo-pretorio-pa' ) : __( 'allegato', 'albo-pretorio-pa' ),
				'file'     => $file['nome'],
				'verifica' => '',
			);

			if ( ! $indici['zip'] ) {
				$documento['verifica'] = __( 'non verificabile senza archivio', 'albo-pretorio-pa' );
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
					$documento['verifica'] = __( 'trovato; impronta non registrata nel vecchio albo', 'albo-pretorio-pa' );
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
						__( '"%s" e\' pubblicato per estratto: il nuovo albo non ha questa indicazione, va riportata nell\'oggetto o nel documento.', 'albo-pretorio-pa' ),
						$documento['titolo']
					)
				);
			}

			$atto['documenti'][] = $documento;
		}

		if ( 0 === $principali ) {
			$segnala[] = self::segnalazione( 'dubbio', 'principale_assente', __( 'Nessun allegato e\' segnato come documento: il documento principale lo sceglie chi importa.', 'albo-pretorio-pa' ) );
		} elseif ( $principali > 1 ) {
			$segnala[] = self::segnalazione(
				'dubbio',
				'principali_multipli',
				sprintf(
					/* translators: %d: numero di documenti. */
					__( '%d allegati sono segnati come documento: nel nuovo albo il documento principale e\' uno, lo sceglie chi importa.', 'albo-pretorio-pa' ),
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
	 * @param array<string, mixed>                      $contesto  Stato del nuovo albo.
	 * @return array<int, array<string, mixed>>
	 */
	private static function categorie( array $righe, array $per_id, array $atti, array $contesto ): array {
		$conteggio = array();

		foreach ( $atti as $atto ) {
			if ( '' !== $atto['tipo'] ) {
				$conteggio[ $atto['tipo'] ] = ( $conteggio[ $atto['tipo'] ] ?? 0 ) + 1;
			}
		}

		$esito = array();

		foreach ( $righe as $riga ) {
			$nome     = trim( (string) ( $riga['Nome'] ?? '' ) );
			$giorni   = self::intero( $riga['Giorni'] ?? null ) ?? 0;
			$genitore = (string) ( $riga['Genitore'] ?? '0' );
			$tipo     = $contesto['tipi'][ self::chiave( $nome ) ] ?? null;
			$segnala  = array();

			if ( null === $tipo ) {
				$corrisponde = __( 'tipo di atto da creare; l\'origine della durata (norma o scelta dell\'amministrazione) la dichiara chi importa', 'albo-pretorio-pa' );
				$segnala[]   = self::segnalazione( 'dubbio', 'tipo_da_creare', __( 'Nel nuovo albo non c\'e\' un tipo di atto con questo nome.', 'albo-pretorio-pa' ) );
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
								__( 'La categoria ha %1$d giorni, il tipo di atto del nuovo albo %2$d: gli atti importati conservano le loro date, i nuovi seguiranno il tipo.', 'albo-pretorio-pa' ),
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

			$esito[] = array(
				'id'           => (string) ( $riga['IdCategoria'] ?? '' ),
				'nome'         => $nome,
				'madre'        => ( '0' !== $genitore && isset( $per_id[ $genitore ] ) ) ? trim( (string) ( $per_id[ $genitore ]['Nome'] ?? '' ) ) : '',
				'giorni'       => $giorni,
				'atti'         => $conteggio[ $chiave ] ?? 0,
				'corrisponde'  => $corrisponde,
				'segnalazioni' => $segnala,
			);
		}

		return $esito;
	}

	/**
	 * La numerazione: per anno, il contatore e la proposta di partenza.
	 *
	 * @param array<int, array<string, mixed>> $atti     Atti ricostruiti.
	 * @param array<string, string|null>       $opzioni  Impostazioni del plugin di provenienza.
	 * @param array<string, mixed>             $contesto Stato del nuovo albo.
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
			if ( ! isset( $contesto['numerazione'][ $anno ] ) || null === $contesto['numerazione'][ $anno ]['usati'] ) {
				$generali[] = self::segnalazione(
					'conflitto',
					'numerazione_non_letta',
					sprintf(
						/* translators: %d: anno. */
						__( 'La numerazione del nuovo albo per il %d non ha risposto: non si puo\' dire se i numeri del backup sono gia\' stati dati.', 'albo-pretorio-pa' ),
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
				'nuovo'    => $contesto['numerazione'][ $anno ]['riga'] ?? null,
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

		if ( null !== $contatore && null !== $anno_cont ) {
			$massimo  = isset( $anni[ $anno_cont ] ) ? max( $anni[ $anno_cont ] ) : 0;
			$proposta = array(
				'anno'      => $anno_cont,
				'contatore' => $contatore,
				'massimo'   => $massimo,
				'ultimo'    => max( $massimo, $contatore - 1 ),
			);

			if ( $contatore - 1 !== $massimo ) {
				$generali[] = self::segnalazione(
					'dubbio',
					'contatore_diverso',
					sprintf(
						/* translators: 1: anno, 2: prossimo numero del contatore, 3: numero piu' alto trovato. */
						__( 'Il contatore del vecchio albo per il %1$d darebbe come prossimo numero %2$d, ma il numero piu\' alto fra gli atti di quell\'anno e\' %3$d.', 'albo-pretorio-pa' ),
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
						__( 'Il contatore del vecchio albo e\' fermo al %1$d, mentre l\'anno in corso e\' il %2$d.', 'albo-pretorio-pa' ),
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
	 * @param array<int, array<string, mixed>> $atti Atti ricostruiti.
	 * @return array<string, array<string, int>>
	 */
	private static function riepilogo( array $atti ): array {
		$riepilogo = array(
			'esiti' => array(
				self::IMPORTABILE => 0,
				self::DA_DECIDERE => 0,
				self::CONFLITTO   => 0,
				self::ESCLUSO     => 0,
			),
			'stati' => array(),
		);

		foreach ( $atti as $atto ) {
			++$riepilogo['esiti'][ $atto['esito'] ];
			$riepilogo['stati'][ $atto['stato'] ] = ( $riepilogo['stati'][ $atto['stato'] ] ?? 0 ) + 1;
		}

		$riepilogo['totale'] = array( 'atti' => count( $atti ) );

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
	 * @param string $testo Valore del campo.
	 * @return array<int, string>
	 */
	private static function soggetti( string $testo ): array {
		if ( '' === trim( $testo ) || 0 === preg_match_all( '/i:\d+;(?:i:(\d+)|s:\d+:"(\d+)");/', $testo, $trovati, PREG_SET_ORDER ) ) {
			return array();
		}

		$ids = array();

		foreach ( $trovati as $trovato ) {
			$ids[] = '' !== $trovato[1] ? $trovato[1] : $trovato[2];
		}

		return array_values( array_unique( $ids ) );
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
