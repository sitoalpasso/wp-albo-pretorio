<?php
/**
 * Responsabile del procedimento, ufficio e richiedente: la scelta dell'amministrazione (ALBO-33).
 *
 * Per ciascuno dei tre dati l'amministrazione sceglie se usarlo, cioe'
 * chiederlo a chi redige e mostrarlo, oppure no. Nessuna scelta e'
 * preimpostata; finche' manca, il dato non si chiede e non si mostra, e
 * l'albo pubblica come prima: la scelta non tocca niente di cio' che la norma
 * chiede alla pubblicazione. Responsabile e ufficio si scelgono da due elenchi
 * che tiene chi pubblica; il richiedente si scrive.
 *
 * Sull'atto i tre dati sono testo, non riferimenti alle voci degli elenchi: un
 * nome tolto dall'elenco smette di essere proposto ma resta sugli atti che lo
 * portano, e un atto portato da un albo precedente conserva i suoi dati
 * qualunque sia la scelta. La scelta decide solo che cosa si chiede e che cosa
 * si mostra.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Impostazione, elenchi e lettura dei dati del procedimento.
 */
final class DatiProcedimento {

	/**
	 * Opzione dell'impostazione.
	 *
	 * @var string
	 */
	const OPZIONE = 'albo_pretorio_dati_procedimento';

	/**
	 * Scelta: il dato si chiede e si mostra.
	 *
	 * @var string
	 */
	const USA = 'usa';

	/**
	 * Scelta: il dato non si chiede e non si mostra.
	 *
	 * @var string
	 */
	const NON_USA = 'non_usa';

	/**
	 * Il dato del responsabile del procedimento.
	 *
	 * @var string
	 */
	const RESPONSABILE = 'responsabile';

	/**
	 * Il dato dell'ufficio.
	 *
	 * @var string
	 */
	const UFFICIO = 'ufficio';

	/**
	 * Il dato del richiedente della pubblicazione.
	 *
	 * @var string
	 */
	const RICHIEDENTE = 'richiedente';

	/**
	 * I tre dati, nell'ordine in cui si presentano.
	 *
	 * @var array<int, string>
	 */
	const DATI = array( self::RESPONSABILE, self::UFFICIO, self::RICHIEDENTE );

	/**
	 * I dati che si scelgono da un elenco, con la chiave del loro elenco nell'impostazione.
	 *
	 * @var array<string, string>
	 */
	const ELENCHI = array(
		self::RESPONSABILE => 'responsabili',
		self::UFFICIO      => 'uffici',
	);

	/**
	 * Righe massime di un elenco.
	 *
	 * @var int
	 */
	const RIGHE_MASSIME = 500;

	/**
	 * Lunghezza massima di un nome, in caratteri.
	 *
	 * @var int
	 */
	const LUNGHEZZA_MASSIMA = 200;

	/**
	 * Il nome del dato sull'atto.
	 *
	 * @param string $dato Uno dei tre dati.
	 * @return string
	 */
	public static function meta( string $dato ): string {
		return '_albo_pretorio_' . $dato;
	}

	/**
	 * Dichiara i tre dati dell'atto a WordPress, protetti e senza esposizione per programmi.
	 *
	 * @return bool Vero se WordPress li ha accettati tutti.
	 */
	public static function registra(): bool {
		foreach ( self::DATI as $dato ) {
			$registrato = register_post_meta(
				TIPO,
				self::meta( $dato ),
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
				)
			);

			if ( ! $registrato ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Toglie la dichiarazione dei tre dati. Serve alla suite di prove.
	 */
	public static function azzera(): void {
		foreach ( self::DATI as $dato ) {
			unregister_post_meta( TIPO, self::meta( $dato ) );
		}
	}

	/**
	 * L'impostazione salvata, controllata, o nulla se non e' fatta o non si legge.
	 *
	 * Un valore scritto di lato nella banca dati in una forma diversa da quella
	 * del salvataggio vale come scelta non fatta: nessun default indovinato.
	 *
	 * @return array{scelte: array<string, string>, responsabili: array<int, string>, uffici: array<int, string>, utente: int, istante: string}|null
	 */
	public static function impostazione(): ?array {
		$valore = get_option( self::OPZIONE, null );

		if ( ! is_array( $valore ) || array_keys( $valore ) !== array( 'scelte', 'responsabili', 'uffici', 'utente', 'istante' ) ) {
			return null;
		}

		if ( ! is_array( $valore['scelte'] ) || array_keys( $valore['scelte'] ) !== self::DATI || ! is_int( $valore['utente'] ) || ! is_string( $valore['istante'] ) ) {
			return null;
		}

		foreach ( $valore['scelte'] as $scelta ) {
			if ( ! in_array( $scelta, array( self::USA, self::NON_USA ), true ) ) {
				return null;
			}
		}

		// L'istante ha la forma esatta che scrive il salvataggio, controllata prima della lettura.
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $valore['istante'] ) ) {
			return null;
		}

		$istante = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $valore['istante'], new \DateTimeZone( 'UTC' ) );

		if ( false === $istante || $istante->format( 'Y-m-d H:i:s' ) !== $valore['istante'] ) {
			return null;
		}

		foreach ( self::ELENCHI as $dato => $chiave ) {
			$elenco = $valore[ $chiave ];

			if ( ! is_array( $elenco ) || ! array_is_list( $elenco ) ) {
				return null;
			}

			foreach ( $elenco as $nome ) {
				if ( ! is_string( $nome ) ) {
					return null;
				}
			}

			if ( self::leggi_elenco( implode( "\n", $elenco ) ) !== $elenco ) {
				return null;
			}

			// Un dato in uso con l'elenco vuoto non si salva: letto cosi', non e' una scelta valida.
			if ( self::USA === $valore['scelte'][ $dato ] && array() === $elenco ) {
				return null;
			}
		}

		return $valore;
	}

	/**
	 * La scelta in vigore per un dato, o nulla se non e' fatta.
	 *
	 * @param string $dato Uno dei tre dati.
	 * @return string|null
	 */
	public static function scelta( string $dato ): ?string {
		$impostazione = self::impostazione();

		return null === $impostazione ? null : ( $impostazione['scelte'][ $dato ] ?? null );
	}

	/**
	 * Se il dato si chiede e si mostra. Senza scelta, no.
	 *
	 * @param string $dato Uno dei tre dati.
	 * @return bool
	 */
	public static function in_uso( string $dato ): bool {
		return self::USA === self::scelta( $dato );
	}

	/**
	 * L'elenco in vigore per responsabile o ufficio; vuoto senza impostazione.
	 *
	 * @param string $dato Responsabile o ufficio.
	 * @return array<int, string>
	 */
	public static function elenco( string $dato ): array {
		$impostazione = self::impostazione();

		if ( null === $impostazione || ! isset( self::ELENCHI[ $dato ] ) ) {
			return array();
		}

		return $impostazione[ self::ELENCHI[ $dato ] ];
	}

	/**
	 * Controlla e salva scelte ed elenchi, tutto insieme o niente.
	 *
	 * @param mixed              $scelte       Scelte inviate, per dato.
	 * @param mixed              $responsabili Elenco dei responsabili, un nome per riga.
	 * @param mixed              $uffici       Elenco degli uffici, un nome per riga.
	 * @param int                $utente       Chi salva.
	 * @param \DateTimeImmutable $adesso       Istante del salvataggio.
	 * @return true|\WP_Error
	 */
	public static function salva( $scelte, $responsabili, $uffici, int $utente, \DateTimeImmutable $adesso ) {
		$lette = array();

		foreach ( self::DATI as $dato ) {
			$scelta = is_array( $scelte ) ? ( $scelte[ $dato ] ?? null ) : null;

			if ( ! is_string( $scelta ) || ! in_array( $scelta, array( self::USA, self::NON_USA ), true ) ) {
				return new \WP_Error(
					'albo_procedimento_scelta_mancante',
					sprintf(
						/* translators: %s: nome del dato. */
						__( 'Impostazione non salvata: per %s va scelto se usarlo o no.', 'albo-pretorio-pa' ),
						self::nome( $dato )
					)
				);
			}

			$lette[ $dato ] = $scelta;
		}

		$elenchi = array(
			self::RESPONSABILE => $responsabili,
			self::UFFICIO      => $uffici,
		);
		$letti   = array();

		foreach ( $elenchi as $dato => $testo ) {
			// Un elenco che non e' un testo si rifiuta: non diventa un elenco vuoto.
			if ( ! is_string( $testo ) ) {
				return new \WP_Error(
					'albo_procedimento_elenco_mancante',
					sprintf(
						/* translators: %s: nome dell'elenco. */
						__( 'Impostazione non salvata: l\'elenco %s non e\' arrivato come testo. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ),
						self::nome_elenco( $dato )
					)
				);
			}

			$elenco = self::leggi_elenco( $testo );

			if ( is_wp_error( $elenco ) ) {
				return new \WP_Error(
					$elenco->get_error_code(),
					sprintf(
						/* translators: 1: nome dell'elenco, 2: motivo. */
						__( 'Impostazione non salvata: nell\'elenco %1$s, %2$s', 'albo-pretorio-pa' ),
						self::nome_elenco( $dato ),
						$elenco->get_error_message()
					)
				);
			}

			if ( self::USA === $lette[ $dato ] && array() === $elenco ) {
				return new \WP_Error(
					'albo_procedimento_elenco_vuoto',
					sprintf(
						/* translators: 1: nome del dato, 2: nome dell'elenco. */
						__( 'Impostazione non salvata: per usare %1$s serve almeno un nome nell\'elenco %2$s, da cui chi redige sceglie.', 'albo-pretorio-pa' ),
						self::nome( $dato ),
						self::nome_elenco( $dato )
					)
				);
			}

			$letti[ $dato ] = $elenco;
		}

		$valore = array(
			'scelte'       => $lette,
			'responsabili' => $letti[ self::RESPONSABILE ],
			'uffici'       => $letti[ self::UFFICIO ],
			'utente'       => $utente,
			'istante'      => $adesso->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);

		update_option( self::OPZIONE, $valore, true );

		if ( self::impostazione() !== $valore ) {
			return new \WP_Error(
				'albo_procedimento_non_scritto',
				__( 'Impostazione non salvata: la banca dati non ha confermato la scrittura. Riprovare.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Legge un elenco scritto un nome per riga.
	 *
	 * Le righe vuote non contano, e gli spazi ripetuti diventano uno. Per il
	 * resto un nome **non si ripulisce di nascosto**: una
	 * riga con segni di formattazione, a capo interni o caratteri di controllo
	 * si rifiuta con il suo numero, invece di salvare un nome diverso da quello
	 * scritto. Lo stesso per i nomi ripetuti, anche con maiuscole diverse.
	 *
	 * @param string $testo Elenco.
	 * @return array<int, string>|\WP_Error
	 */
	public static function leggi_elenco( string $testo ) {
		$righe  = preg_split( '/\r\n|\r|\n/', $testo );
		$elenco = array();
		$visti  = array();

		foreach ( false === $righe ? array() : $righe as $indice => $riga ) {
			// Gli spazi ripetuti dentro un nome diventano uno: e' l'unica pulizia, e si vede.
			$nome = trim( (string) preg_replace( '/ {2,}/', ' ', $riga ) );

			if ( '' === $nome ) {
				continue;
			}

			$numero = $indice + 1;
			$errore = self::errore_nome( $nome );

			if ( null !== $errore ) {
				return new \WP_Error(
					'albo_procedimento_riga',
					sprintf(
						/* translators: 1: numero della riga, 2: motivo. */
						__( 'riga %1$d: %2$s', 'albo-pretorio-pa' ),
						$numero,
						$errore
					)
				);
			}

			$chiave = mb_strtolower( $nome, 'UTF-8' );

			if ( isset( $visti[ $chiave ] ) ) {
				return new \WP_Error(
					'albo_procedimento_riga',
					sprintf(
						/* translators: 1: numero della riga, 2: numero della riga precedente. */
						__( 'riga %1$d: il nome e\' gia\' scritto alla riga %2$d.', 'albo-pretorio-pa' ),
						$numero,
						$visti[ $chiave ]
					)
				);
			}

			$visti[ $chiave ] = $numero;
			$elenco[]         = $nome;
		}

		if ( count( $elenco ) > self::RIGHE_MASSIME ) {
			return new \WP_Error(
				'albo_procedimento_troppi',
				sprintf(
					/* translators: %d: numero massimo di nomi. */
					__( 'ci sono piu\' di %d nomi.', 'albo-pretorio-pa' ),
					self::RIGHE_MASSIME
				)
			);
		}

		return $elenco;
	}

	/**
	 * Perche' un nome non e' accettabile, o nullo se lo e'.
	 *
	 * @param string $nome Nome gia' senza spazi ai lati.
	 * @return string|null
	 */
	private static function errore_nome( string $nome ): ?string {
		if ( '' === wp_check_invalid_utf8( $nome ) || 1 === preg_match( '/[\x00-\x1F\x7F]/', $nome ) ) {
			return __( 'il testo non e\' leggibile o contiene caratteri di controllo.', 'albo-pretorio-pa' );
		}

		if ( mb_strlen( $nome, 'UTF-8' ) > self::LUNGHEZZA_MASSIMA ) {
			return sprintf(
				/* translators: %d: lunghezza massima. */
				__( 'il nome supera i %d caratteri.', 'albo-pretorio-pa' ),
				self::LUNGHEZZA_MASSIMA
			);
		}

		if ( sanitize_text_field( $nome ) !== $nome ) {
			return __( 'il nome contiene segni di formattazione o caratteri non ammessi.', 'albo-pretorio-pa' );
		}

		return null;
	}

	/**
	 * Il valore di un dato sull'atto, controllato, o testo vuoto.
	 *
	 * Si legge qualunque sia la scelta: i dati si conservano anche quando non
	 * si usano. Due righe valgono come nessuna, e cosi' un valore che la scheda
	 * non avrebbe salvato.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $dato    Uno dei tre dati.
	 * @return string
	 */
	public static function valore( int $atto_id, string $dato ): string {
		if ( ! in_array( $dato, self::DATI, true ) ) {
			return '';
		}

		$righe = get_post_meta( $atto_id, self::meta( $dato ), false );

		if ( 1 !== count( $righe ) || ! is_string( $righe[0] ) || '' === $righe[0] ) {
			return '';
		}

		return null === self::errore_nome( $righe[0] ) && trim( $righe[0] ) === $righe[0] ? $righe[0] : '';
	}

	/**
	 * I tre valori sull'atto, per la voce di registro della pubblicazione.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, string>
	 */
	public static function per_registro( int $atto_id ): array {
		$valori = array();

		foreach ( self::DATI as $dato ) {
			$valori[ $dato ] = self::valore( $atto_id, $dato );
		}

		return $valori;
	}

	/**
	 * I dati da mostrare a chi consulta l'atto: quelli in uso e compilati, per etichetta.
	 *
	 * Testo non sfuggito: lo sfugge chi lo stampa.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, string>
	 */
	public static function da_mostrare( int $atto_id ): array {
		$mostrati = array();

		foreach ( self::DATI as $dato ) {
			$valore = self::valore( $atto_id, $dato );

			if ( self::in_uso( $dato ) && '' !== $valore ) {
				$mostrati[ self::etichetta( $dato ) ] = $valore;
			}
		}

		return $mostrati;
	}

	/**
	 * L'etichetta del dato, come si legge sulla scheda.
	 *
	 * @param string $dato Uno dei tre dati.
	 * @return string
	 */
	public static function etichetta( string $dato ): string {
		switch ( $dato ) {
			case self::RESPONSABILE:
				return __( 'Responsabile del procedimento', 'albo-pretorio-pa' );
			case self::UFFICIO:
				return __( 'Ufficio', 'albo-pretorio-pa' );
			default:
				return __( 'Richiedente della pubblicazione', 'albo-pretorio-pa' );
		}
	}

	/**
	 * Il nome del dato dentro una frase.
	 *
	 * @param string $dato Uno dei tre dati.
	 * @return string
	 */
	public static function nome( string $dato ): string {
		switch ( $dato ) {
			case self::RESPONSABILE:
				return __( 'il responsabile del procedimento', 'albo-pretorio-pa' );
			case self::UFFICIO:
				return __( 'l\'ufficio', 'albo-pretorio-pa' );
			default:
				return __( 'il richiedente della pubblicazione', 'albo-pretorio-pa' );
		}
	}

	/**
	 * Il nome dell'elenco dentro una frase.
	 *
	 * @param string $dato Responsabile o ufficio.
	 * @return string
	 */
	public static function nome_elenco( string $dato ): string {
		return self::RESPONSABILE === $dato ? __( 'dei responsabili', 'albo-pretorio-pa' ) : __( 'degli uffici', 'albo-pretorio-pa' );
	}
}
