<?php
/**
 * La fine che cade in un giorno festivo (ALBO-32).
 *
 * **Che cosa decide l'amministrazione.** Se la data di fine calcolata cade di
 * domenica o in un giorno dell'elenco dei festivi, passa al primo giorno
 * seguente non festivo, oppure resta dove cade. Le due scelte sono
 * ammissibili e nessuna e' gia' pronta: finche' l'amministrazione non sceglie,
 * nessuna fine si calcola, quindi non si pubblica e non si proroga.
 *
 * **Dove sta.** Scelta ed elenco stanno in una sola opzione del sito, scritta
 * tutta insieme con chi l'ha salvata e quando. Un valore di forma diversa da
 * quella che il salvataggio scrive vale come scelta non fatta: il calcolo si
 * ferma invece di indovinare.
 *
 * **L'elenco.** Un giorno per riga, `data | descrizione`, dove la data e'
 * `GG/MM` (ogni anno), `GG/MM/AAAA` (solo quel giorno) o `Pasqua+N` (tanti
 * giorni dopo la domenica di Pasqua). Le domeniche sono sempre festive e non
 * si scrivono. L'elenco vale al momento del calcolo: le fini gia' scritte non
 * cambiano quando cambia.
 *
 * Questa classe legge, controlla e calcola. Le date di fine le scrivono
 * `Passaggi` e `Proroga`, nel loro calcolo.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Impostazione, elenco e slittamento.
 */
final class Festivi {

	/**
	 * Nome dell'opzione.
	 *
	 * @var string
	 */
	const OPZIONE = 'albo_pretorio_festivi';

	/**
	 * La fine passa al primo giorno non festivo.
	 *
	 * @var string
	 */
	const ACCESO = 'acceso';

	/**
	 * La fine resta dove cade.
	 *
	 * @var string
	 */
	const SPENTO = 'spento';

	/**
	 * Quanti giorni di seguito lo slittamento puo' saltare prima di arrendersi.
	 *
	 * @var int
	 */
	const PASSI_MASSIMI = 366;

	/**
	 * Quante righe l'elenco puo' avere.
	 *
	 * @var int
	 */
	const RIGHE_MASSIME = 200;

	/**
	 * Quanti giorni dopo Pasqua una riga puo' indicare.
	 *
	 * @var int
	 */
	const DOPO_PASQUA_MASSIMO = 60;

	/**
	 * Lunghezza massima di una descrizione.
	 *
	 * @var int
	 */
	const DESCRIZIONE_MASSIMA = 200;

	/**
	 * L'impostazione salvata, controllata, o nulla se la scelta non e' fatta o non si legge.
	 *
	 * @return array{scelta: string, elenco: array<int, array{data: string, descrizione: string}>, utente: int, istante: string}|null
	 */
	public static function impostazione(): ?array {
		$valore = get_option( self::OPZIONE, null );

		if ( ! is_array( $valore ) ) {
			return null;
		}

		$chiavi = array( 'scelta', 'elenco', 'utente', 'istante' );

		if ( array_keys( $valore ) !== $chiavi ) {
			return null;
		}

		if ( ! in_array( $valore['scelta'], array( self::ACCESO, self::SPENTO ), true ) || ! is_array( $valore['elenco'] ) || ! is_int( $valore['utente'] ) || ! is_string( $valore['istante'] ) ) {
			return null;
		}

		// L'istante ha la forma esatta che scrive il salvataggio: una data e ora vera, in tempo universale.
		$istante = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $valore['istante'], new \DateTimeZone( 'UTC' ) );

		if ( false === $istante || $istante->format( 'Y-m-d H:i:s' ) !== $valore['istante'] ) {
			return null;
		}

		if ( count( $valore['elenco'] ) > self::RIGHE_MASSIME || ! array_is_list( $valore['elenco'] ) ) {
			return null;
		}

		foreach ( $valore['elenco'] as $riga ) {
			if ( ! is_array( $riga ) || array_keys( $riga ) !== array( 'data', 'descrizione' ) || ! is_string( $riga['data'] ) || ! is_string( $riga['descrizione'] ) ) {
				return null;
			}

			$letta = self::riga( $riga['data'], $riga['descrizione'] );

			if ( is_wp_error( $letta ) || $letta !== $riga ) {
				return null;
			}
		}

		return $valore;
	}

	/**
	 * La scelta in vigore, o nulla se non e' fatta.
	 *
	 * @return string|null
	 */
	public static function scelta(): ?string {
		$impostazione = self::impostazione();

		return null === $impostazione ? null : $impostazione['scelta'];
	}

	/**
	 * Il motivo per cui una fine non si calcola senza scelta.
	 *
	 * @param string $prefisso Frase di apertura del passaggio rifiutato.
	 * @return \WP_Error
	 */
	public static function errore_non_scelta( string $prefisso ): \WP_Error {
		return new \WP_Error(
			'albo_festivi_non_scelti',
			sprintf(
				/* translators: 1: frase di apertura del passaggio rifiutato, 2: nome della schermata. */
				__( '%1$s prima va scelto che cosa succede quando la data di fine cade in un giorno festivo, nella schermata %2$s.', 'albo-pretorio-pa' ),
				$prefisso,
				__( 'Albo pretorio, Giorni festivi', 'albo-pretorio-pa' )
			)
		);
	}

	/**
	 * Controlla e salva scelta ed elenco, tutto insieme o niente.
	 *
	 * @param mixed              $scelta Scelta inviata.
	 * @param mixed              $testo  Elenco inviato, un giorno per riga.
	 * @param int                $utente Chi salva.
	 * @param \DateTimeImmutable $adesso Istante del salvataggio.
	 * @return true|\WP_Error
	 */
	public static function salva( $scelta, $testo, int $utente, \DateTimeImmutable $adesso ) {
		if ( ! is_string( $scelta ) || ! in_array( $scelta, array( self::ACCESO, self::SPENTO ), true ) ) {
			return new \WP_Error(
				'albo_festivi_scelta_mancante',
				__( 'Impostazione non salvata: va scelta una delle due possibilita\' sulla fine che cade in un giorno festivo.', 'albo-pretorio-pa' )
			);
		}

		// Un elenco che non e' un testo si rifiuta: non diventa un elenco vuoto.
		if ( ! is_string( $testo ) ) {
			return new \WP_Error(
				'albo_festivi_elenco_mancante',
				__( 'Impostazione non salvata: l\'elenco dei giorni festivi non e\' arrivato come testo. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' )
			);
		}

		$elenco = self::leggi_elenco( $testo );

		if ( is_wp_error( $elenco ) ) {
			return $elenco;
		}

		$valore = array(
			'scelta'  => $scelta,
			'elenco'  => $elenco,
			'utente'  => $utente,
			'istante' => $adesso->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);

		update_option( self::OPZIONE, $valore, true );

		if ( self::impostazione() !== $valore ) {
			return new \WP_Error(
				'albo_festivi_non_scritti',
				__( 'Impostazione non salvata: la banca dati non ha confermato la scrittura. Riprovare.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * L'elenco inviato, controllato riga per riga, o il motivo del rifiuto.
	 *
	 * @param string $testo Elenco, un giorno per riga.
	 * @return array<int, array{data: string, descrizione: string}>|\WP_Error
	 */
	public static function leggi_elenco( string $testo ) {
		$elenco = array();
		$righe  = preg_split( '/\R/u', $testo );

		if ( false === $righe ) {
			return new \WP_Error(
				'albo_festivi_riga_non_valida',
				__( 'Impostazione non salvata: l\'elenco dei giorni festivi non si legge.', 'albo-pretorio-pa' )
			);
		}

		foreach ( $righe as $indice => $riga ) {
			$riga = trim( $riga );

			if ( '' === $riga || str_starts_with( $riga, '#' ) ) {
				continue;
			}

			$parti = explode( '|', $riga, 2 );
			$letta = self::riga( trim( $parti[0] ), isset( $parti[1] ) ? trim( $parti[1] ) : '' );

			if ( is_wp_error( $letta ) ) {
				return new \WP_Error(
					'albo_festivi_riga_non_valida',
					sprintf(
						/* translators: 1: numero della riga, 2: motivo. */
						__( 'Impostazione non salvata: la riga %1$d dell\'elenco dei giorni festivi non va bene. %2$s', 'albo-pretorio-pa' ),
						$indice + 1,
						$letta->get_error_message()
					)
				);
			}

			$elenco[] = $letta;

			if ( count( $elenco ) > self::RIGHE_MASSIME ) {
				return new \WP_Error(
					'albo_festivi_troppe_righe',
					sprintf(
						/* translators: %d: numero massimo di righe. */
						__( 'Impostazione non salvata: l\'elenco dei giorni festivi ha al massimo %d righe.', 'albo-pretorio-pa' ),
						self::RIGHE_MASSIME
					)
				);
			}
		}

		return $elenco;
	}

	/**
	 * Una riga dell'elenco nella forma in cui si salva, o il motivo per cui non va.
	 *
	 * @param string $data        Data: GG/MM, GG/MM/AAAA o Pasqua+N.
	 * @param string $descrizione Descrizione, con la norma che istituisce la festa.
	 * @return array{data: string, descrizione: string}|\WP_Error
	 */
	public static function riga( string $data, string $descrizione ) {
		$descrizione = sanitize_text_field( $descrizione );

		if ( '' === $descrizione ) {
			return new \WP_Error(
				'albo_festivi_descrizione',
				__( 'Dopo la data, separata da una barra verticale, serve la descrizione del giorno, per esempio la norma che istituisce la festa.', 'albo-pretorio-pa' )
			);
		}

		if ( mb_strlen( $descrizione ) > self::DESCRIZIONE_MASSIMA ) {
			return new \WP_Error(
				'albo_festivi_descrizione',
				sprintf(
					/* translators: %d: lunghezza massima. */
					__( 'La descrizione e\' piu\' lunga di %d caratteri.', 'albo-pretorio-pa' ),
					self::DESCRIZIONE_MASSIMA
				)
			);
		}

		$normalizzata = self::data( $data );

		if ( is_wp_error( $normalizzata ) ) {
			return $normalizzata;
		}

		return array(
			'data'        => $normalizzata,
			'descrizione' => $descrizione,
		);
	}

	/**
	 * La data di una riga nella forma in cui si salva, o il motivo per cui non va.
	 *
	 * @param string $data Data indicata.
	 * @return string|\WP_Error
	 */
	private static function data( string $data ) {
		if ( 1 === preg_match( '/^(\d{1,2})\/(\d{1,2})$/', $data, $parti ) ) {
			// Un anno bisestile, perche' il 29 febbraio e' un giorno che esiste.
			if ( checkdate( (int) $parti[2], (int) $parti[1], 2000 ) ) {
				return sprintf( '%02d/%02d', (int) $parti[1], (int) $parti[2] );
			}
		} elseif ( 1 === preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $data, $parti ) ) {
			if ( checkdate( (int) $parti[2], (int) $parti[1], (int) $parti[3] ) ) {
				return sprintf( '%02d/%02d/%04d', (int) $parti[1], (int) $parti[2], (int) $parti[3] );
			}
		} elseif ( 1 === preg_match( '/^pasqua\s*(?:\+\s*(\d+))?$/i', $data, $parti ) ) {
			$dopo = isset( $parti[1] ) ? (int) $parti[1] : 0;

			if ( $dopo <= self::DOPO_PASQUA_MASSIMO ) {
				return 'Pasqua+' . $dopo;
			}

			return new \WP_Error(
				'albo_festivi_data',
				sprintf(
					/* translators: %d: numero massimo di giorni. */
					__( 'Dopo Pasqua si indicano al massimo %d giorni.', 'albo-pretorio-pa' ),
					self::DOPO_PASQUA_MASSIMO
				)
			);
		} else {
			return new \WP_Error(
				'albo_festivi_data',
				sprintf(
					/* translators: %s: data indicata. */
					__( '"%s" non e\' una data nella forma GG/MM, GG/MM/AAAA o Pasqua+N.', 'albo-pretorio-pa' ),
					$data
				)
			);
		}

		return new \WP_Error(
			'albo_festivi_data',
			sprintf(
				/* translators: %s: data indicata. */
				__( 'Il giorno %s non esiste nel calendario.', 'albo-pretorio-pa' ),
				$data
			)
		);
	}

	/**
	 * La fine dopo lo slittamento, con quella calcolata e i giorni saltati.
	 *
	 * @param string                                                                              $calcolata    Fine calcolata, AAAA-MM-GG.
	 * @param array{scelta: string, elenco: array<int, array{data: string, descrizione: string}>} $impostazione Impostazione in vigore.
	 * @return array{fine: string, calcolata: string, slittamento: string, saltati: array<int, array{giorno: string, descrizione: string}>}|\WP_Error
	 */
	public static function slitta( string $calcolata, array $impostazione ) {
		$esito = array(
			'fine'        => $calcolata,
			'calcolata'   => $calcolata,
			'slittamento' => $impostazione['scelta'],
			'saltati'     => array(),
		);

		if ( self::ACCESO !== $impostazione['scelta'] ) {
			return $esito;
		}

		$giorno = \DateTimeImmutable::createFromFormat( '!Y-m-d', $calcolata, wp_timezone() );

		if ( false === $giorno || $giorno->format( 'Y-m-d' ) !== $calcolata ) {
			return new \WP_Error(
				'albo_fine_non_calcolabile',
				__( 'La data di fine calcolata non e\' una data valida.', 'albo-pretorio-pa' )
			);
		}

		for ( $passi = 0; $passi <= self::PASSI_MASSIMI; $passi++ ) {
			$descrizione = self::festivo( $giorno, $impostazione['elenco'] );

			if ( null === $descrizione ) {
				$esito['fine'] = $giorno->format( 'Y-m-d' );

				return $esito;
			}

			$esito['saltati'][] = array(
				'giorno'      => $giorno->format( 'Y-m-d' ),
				'descrizione' => $descrizione,
			);

			$giorno = $giorno->add( new \DateInterval( 'P1D' ) );
		}

		return new \WP_Error(
			'albo_fine_non_calcolabile',
			__( 'La data di fine non si puo\' calcolare: l\'elenco dei giorni festivi non lascia nessun giorno feriale per un anno intero.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * La descrizione del giorno se e' festivo, o nulla.
	 *
	 * @param \DateTimeImmutable                                   $giorno Giorno, nel fuso del sito.
	 * @param array<int, array{data: string, descrizione: string}> $elenco Elenco dei festivi.
	 * @return string|null
	 */
	public static function festivo( \DateTimeImmutable $giorno, array $elenco ): ?string {
		if ( '7' === $giorno->format( 'N' ) ) {
			return __( 'Domenica', 'albo-pretorio-pa' );
		}

		$anno   = (int) $giorno->format( 'Y' );
		$pasqua = null;

		foreach ( $elenco as $riga ) {
			if ( str_starts_with( $riga['data'], 'Pasqua+' ) ) {
				$pasqua = $pasqua ?? \DateTimeImmutable::createFromFormat( '!Y-m-d', self::pasqua( $anno ), wp_timezone() );
				$dopo   = (int) substr( $riga['data'], strlen( 'Pasqua+' ) );
				$uguale = $pasqua->add( new \DateInterval( 'P' . $dopo . 'D' ) )->format( 'Y-m-d' ) === $giorno->format( 'Y-m-d' );
			} elseif ( 10 === strlen( $riga['data'] ) ) {
				$uguale = $riga['data'] === $giorno->format( 'd/m/Y' );
			} else {
				$uguale = $riga['data'] === $giorno->format( 'd/m' );
			}

			if ( $uguale ) {
				return $riga['descrizione'];
			}
		}

		return null;
	}

	/**
	 * La domenica di Pasqua di un anno del calendario gregoriano, AAAA-MM-GG.
	 *
	 * Il calcolo dall'epatta gregoriana nella forma di Knuth (The Art of
	 * Computer Programming, vol. 1, 1.3.2, esercizio 14): non usa l'estensione
	 * del calendario di PHP, che non tutti i server hanno.
	 *
	 * @param int $anno Anno, dal 1583.
	 * @return string
	 */
	public static function pasqua( int $anno ): string {
		$aureo  = $anno % 19 + 1;
		$secolo = intdiv( $anno, 100 ) + 1;
		$salti  = intdiv( 3 * $secolo, 4 ) - 12;
		$luna   = intdiv( 8 * $secolo + 5, 25 ) - 5;
		$dom    = intdiv( 5 * $anno, 4 ) - $salti - 10;
		$epatta = ( ( 11 * $aureo + 20 + $luna - $salti ) % 30 + 30 ) % 30;

		if ( ( 25 === $epatta && $aureo > 11 ) || 24 === $epatta ) {
			++$epatta;
		}

		$piena = 44 - $epatta;

		if ( $piena < 21 ) {
			$piena += 30;
		}

		$giorno = $piena + 7 - ( ( $dom + $piena ) % 7 );

		return $giorno > 31
			? sprintf( '%04d-04-%02d', $anno, $giorno - 31 )
			: sprintf( '%04d-03-%02d', $anno, $giorno );
	}

	/**
	 * L'elenco salvato come testo, una riga per giorno.
	 *
	 * @param array<int, array{data: string, descrizione: string}> $elenco Elenco.
	 * @return string
	 */
	public static function testo( array $elenco ): string {
		$righe = array();

		foreach ( $elenco as $riga ) {
			$righe[] = $riga['data'] . ' | ' . $riga['descrizione'];
		}

		return implode( "\n", $righe );
	}

	/**
	 * L'elenco proposto la prima volta: le feste nazionali vigenti e il posto del patrono.
	 *
	 * Una proposta, non un valore: la schermata la mette nel campo solo finche'
	 * niente e' stato salvato, e vale solo se l'amministrazione la salva. Sta in
	 * un file di dati, `dati/feste-nazionali.txt`, nella forma del campo, perche'
	 * e' un elenco che cambia per legge e non una regola del codice.
	 *
	 * @return string
	 */
	public static function proposta(): string {
		$percorso = dirname( __DIR__ ) . '/dati/feste-nazionali.txt';
		$testo    = is_readable( $percorso ) ? file_get_contents( $percorso ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del componente.

		return false === $testo ? '' : trim( $testo );
	}
}
