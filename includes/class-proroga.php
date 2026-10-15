<?php
/**
 * Il calcolo della proroga per disservizio (ALBO-31).
 *
 * **Che cosa conta.** Un giorno in cui l'albo e' rimasto consultabile per
 * meno di dodici ore non vale come giorno di pubblicazione. Per ogni atto
 * pubblicato si contano i giorni sotto soglia caduti nel suo periodo, cioe'
 * dal giorno dopo l'inizio fino alla fine compresa, nel fuso del sito: il
 * giorno di pubblicazione non conta, come nel calcolo della fine (ALBO-27).
 *
 * **Da dove si conta.** La fine nuova e' la piu' tarda fra la fine attuale e
 * il giorno in cui la proroga si dispone, piu' i giorni contati. Per un atto
 * ancora in pubblicazione e' la fine attuale piu' quei giorni; per un atto
 * gia' uscito dalla vista i giorni mancanti partono dal giorno della proroga,
 * che non conta come il giorno di pubblicazione, invece di cadere nel
 * passato. I giorni fra la vecchia fine e la proroga non erano giorni di
 * pubblicazione, e una proroga successiva non li conta.
 *
 * Questa classe calcola e legge, non scrive: le scritture le fa `Passaggi`,
 * dentro la sua transazione.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Giorni attestati, atti toccati, fini nuove.
 */
final class Proroga {

	/**
	 * Azione della voce di sezione che descrive la proroga, con l'attestazione.
	 *
	 * @var string
	 */
	const AZIONE_PROROGA = 'proroga_per_disservizio';

	/**
	 * Azione della voce di sezione di ciascun giorno attestato, con la chiave unica.
	 *
	 * @var string
	 */
	const AZIONE_GIORNO = 'giorno_sotto_soglia';

	/**
	 * Azione della voce di ciascun atto prorogato.
	 *
	 * @var string
	 */
	const AZIONE_ATTO = 'proroga_atto';

	/**
	 * Quanti giorni una proroga sola puo' attestare.
	 *
	 * @var int
	 */
	const GIORNI_MASSIMI = 366;

	/**
	 * La chiave di registro di un giorno attestato: una sola voce per giorno, garantita dalla banca dati.
	 *
	 * @param string $giorno Giorno AAAA-MM-GG.
	 * @return string
	 */
	public static function chiave_giorno( string $giorno ): string {
		return SEZIONE . ':disservizio:' . $giorno;
	}

	/**
	 * I giorni indicati, controllati e in ordine, o il motivo del rifiuto.
	 *
	 * Ogni giorno e' una data AAAA-MM-GG gia' trascorsa nel fuso del sito: si
	 * attesta un disservizio avvenuto, non uno previsto, e il giorno corrente
	 * non e' ancora finito.
	 *
	 * @param mixed              $giorni Giorni indicati.
	 * @param \DateTimeImmutable $adesso Istante della richiesta.
	 * @return array<int, string>|\WP_Error
	 */
	public static function giorni( $giorni, \DateTimeImmutable $adesso ) {
		if ( ! is_array( $giorni ) || array() === $giorni ) {
			return new \WP_Error(
				'albo_proroga_senza_giorni',
				__( 'Nessuna proroga disposta: indicare almeno un giorno in cui l\'albo e\' rimasto consultabile per meno di dodici ore.', 'albo-pretorio-pa' )
			);
		}

		if ( count( $giorni ) > self::GIORNI_MASSIMI ) {
			return new \WP_Error(
				'albo_proroga_troppi_giorni',
				sprintf(
					/* translators: %d: numero massimo di giorni. */
					__( 'Nessuna proroga disposta: una proroga attesta al massimo %d giorni.', 'albo-pretorio-pa' ),
					self::GIORNI_MASSIMI
				)
			);
		}

		$oggi    = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		$validi  = array();
		$fusi    = wp_timezone();
		$formato = '!Y-m-d';

		foreach ( $giorni as $giorno ) {
			$testo = is_string( $giorno ) ? trim( $giorno ) : '';
			$data  = '' === $testo ? false : \DateTimeImmutable::createFromFormat( $formato, $testo, $fusi );

			if ( false === $data || $data->format( 'Y-m-d' ) !== $testo ) {
				return new \WP_Error(
					'albo_proroga_giorno_non_valido',
					sprintf(
						/* translators: %s: valore indicato. */
						__( 'Nessuna proroga disposta: "%s" non e\' una data nel formato AAAA-MM-GG.', 'albo-pretorio-pa' ),
						is_scalar( $giorno ) ? (string) $giorno : ''
					)
				);
			}

			if ( $testo >= $oggi ) {
				return new \WP_Error(
					'albo_proroga_giorno_non_trascorso',
					sprintf(
						/* translators: %s: giorno indicato. */
						__( 'Nessuna proroga disposta: il %s non e\' ancora trascorso. Si attesta un disservizio avvenuto, non uno previsto.', 'albo-pretorio-pa' ),
						self::leggibile( $testo )
					)
				);
			}

			$validi[ $testo ] = $testo;
		}

		ksort( $validi );

		return array_values( $validi );
	}

	/**
	 * I giorni gia' attestati da proroghe precedenti, o nulla se il registro non si legge.
	 *
	 * @return array<int, string>|null
	 */
	public static function giorni_attestati(): ?array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE,
				'azione'  => self::AZIONE_GIORNO,
			)
		);

		if ( ! is_array( $voci ) ) {
			return null;
		}

		$giorni = array();

		foreach ( $voci as $voce ) {
			if ( isset( $voce['dettagli']['giorno'] ) ) {
				$giorni[] = (string) $voce['dettagli']['giorno'];
			}
		}

		return $giorni;
	}

	/**
	 * Le proroghe gia' disposte, dalla piu' recente, o nulla se il registro non si legge.
	 *
	 * @return array<int, array<string, mixed>>|null
	 */
	public static function disposte(): ?array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE,
				'azione'  => self::AZIONE_PROROGA,
				'ordine'  => 'decrescente',
			)
		);

		return is_array( $voci ) ? $voci : null;
	}

	/**
	 * Gli atti che la proroga tocca, con la fine attuale, la fine nuova e i giorni contati.
	 *
	 * Si leggono gli atti pubblicati dalla banca dati. Bozze, atti in verifica
	 * e ogni altro stato non si toccano. Un atto pubblicato senza una data di
	 * fine leggibile non si calcola e la proroga si rifiuta: e' un'anomalia da
	 * guardare, non un atto da saltare in silenzio.
	 *
	 * @param array<int, string> $giorni Giorni sotto soglia, gia' controllati.
	 * @param \DateTimeImmutable $adesso Istante della proroga.
	 * @return array<int, array{fine: string, nuova: string, giorni: int, numero: string, oggetto: string}>|\WP_Error
	 */
	public static function atti_toccati( array $giorni, \DateTimeImmutable $adesso ) {
		global $wpdb;

		$atti = Lettura::righe(
			$wpdb->prepare(
				'SELECT ID, post_date, post_title FROM %i WHERE post_type = %s AND post_status = %s ORDER BY ID',
				$wpdb->posts,
				TIPO,
				'publish'
			),
			array( 'ID', 'post_date', 'post_title' )
		);

		$intervalli = self::giorni_fuori_periodo();

		if ( null === $atti || null === $intervalli ) {
			return new \WP_Error(
				'albo_proroga_non_letta',
				__( 'Nessuna proroga disposta: la banca dati non ha risposto alla lettura degli atti pubblicati o del registro.', 'albo-pretorio-pa' )
			);
		}

		$oggi    = $adesso->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		$toccati = array();

		foreach ( $atti as $atto ) {
			$atto_id = (int) $atto['ID'];
			$fine    = conformita_core_fine_pubblicazione( $atto_id );

			if ( ! is_string( $fine ) || true !== conformita_core_valida_fine_pubblicazione( $fine ) ) {
				return new \WP_Error(
					'albo_proroga_fine_illeggibile',
					sprintf(
						/* translators: %d: identificativo dell'atto. */
						__( 'Nessuna proroga disposta: l\'atto pubblicato %d non ha una data di fine leggibile, e va controllato prima.', 'albo-pretorio-pa' ),
						$atto_id
					)
				);
			}

			$inizio = substr( (string) $atto['post_date'], 0, 10 );
			$fuori  = $intervalli[ $atto_id ] ?? array();
			$conta  = 0;

			foreach ( $giorni as $giorno ) {
				if ( $giorno > $inizio && $giorno <= $fine && ! in_array( $giorno, $fuori, true ) ) {
					++$conta;
				}
			}

			if ( 0 === $conta ) {
				continue;
			}

			$base   = max( $fine, $oggi );
			$nuova  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $base, wp_timezone() )->add( new \DateInterval( 'P' . $conta . 'D' ) )->format( 'Y-m-d' );
			$numero = Repertorio::di( $atto_id );

			$toccati[ $atto_id ] = array(
				'fine'    => $fine,
				'nuova'   => $nuova,
				'giorni'  => $conta,
				'numero'  => is_array( $numero ) ? $numero['numero'] . '/' . $numero['anno'] : '',
				'oggetto' => (string) $atto['post_title'],
			);
		}

		return $toccati;
	}

	/**
	 * Per ogni atto riaperto da una proroga, i giorni fra la vecchia fine e la proroga.
	 *
	 * In quei giorni l'atto non era consultabile, quindi non erano giorni di
	 * pubblicazione: un disservizio caduto li' non lo riguarda.
	 *
	 * @return array<int, array<int, string>>|null
	 */
	private static function giorni_fuori_periodo(): ?array {
		$voci = conformita_core_voci_registro(
			array(
				'sezione' => SEZIONE,
				'azione'  => self::AZIONE_ATTO,
			)
		);

		if ( ! is_array( $voci ) ) {
			return null;
		}

		$fuori = array();

		foreach ( $voci as $voce ) {
			$precedente = (string) ( $voce['dettagli']['fine_precedente'] ?? '' );
			$giorno     = (string) ( $voce['dettagli']['giorno_proroga'] ?? '' );
			$atto_id    = (int) $voce['contenuto'];

			if ( '' === $precedente || '' === $giorno || $giorno <= $precedente ) {
				continue;
			}

			$corrente = \DateTimeImmutable::createFromFormat( '!Y-m-d', $precedente, wp_timezone() );

			while ( false !== $corrente ) {
				$corrente = $corrente->add( new \DateInterval( 'P1D' ) );
				$testo    = $corrente->format( 'Y-m-d' );

				if ( $testo > $giorno ) {
					break;
				}

				$fuori[ $atto_id ][] = $testo;
			}
		}

		return $fuori;
	}

	/**
	 * Un giorno AAAA-MM-GG come lo legge una persona.
	 *
	 * @param string $giorno Giorno.
	 * @return string
	 */
	public static function leggibile( string $giorno ): string {
		$data = \DateTimeImmutable::createFromFormat( '!Y-m-d', $giorno, wp_timezone() );

		return false === $data ? $giorno : wp_date( 'd/m/Y', $data->getTimestamp(), wp_timezone() );
	}
}
