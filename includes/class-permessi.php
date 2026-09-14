<?php
/**
 * Permessi del tipo atto, ruolo proprio del componente e avviso quando mancano.
 *
 * **I nomi dei permessi non si scrivono qui.** Li ricava il meccanismo comune
 * dall'identificativo del tipo, e questo file li chiede. Quello che il
 * componente possiede davvero, e che quindi puo' nominare, e' l'identificativo
 * del ruolo e l'elenco dei permessi che vuole, espresso con i nomi generici di
 * WordPress: un nome derivato riscritto a mano e sbagliato di una lettera e' un
 * permesso che nessuno possiede e di cui nessuno si accorge.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Assegnazione dei permessi ai ruoli, in modo ripetibile e additivo.
 */
final class Permessi {

	/**
	 * Chi redige: prepara gli atti e non li espone.
	 *
	 * Nomi generici di WordPress, non nomi derivati: la corrispondenza la
	 * fornisce il meccanismo comune.
	 *
	 * @var array<int, string>
	 */
	const CHIAVI_REDAZIONE = array(
		'edit_posts',
		'edit_published_posts',
		'delete_posts',
		'read_private_posts',
	);

	/**
	 * Chi pubblica: tutto quello che fa chi redige, piu' l'esposizione.
	 *
	 * @var array<int, string>
	 */
	const CHIAVI_PUBBLICAZIONE = array(
		'edit_posts',
		'edit_published_posts',
		'delete_posts',
		'read_private_posts',
		'publish_posts',
		'edit_others_posts',
		'delete_others_posts',
		'delete_published_posts',
	);

	/**
	 * Nome visibile del ruolo proprio del componente.
	 *
	 * Sta in una funzione e non in una costante perche' e' testo mostrato a chi
	 * usa il sito, quindi traducibile.
	 *
	 * @return string
	 */
	public static function nome_ruolo(): string {
		return __( 'Responsabile della pubblicazione all\'albo', 'albo-pretorio-pa' );
	}

	/**
	 * I ruoli a cui il componente assegna i permessi di propria iniziativa.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function assegnazioni(): array {
		return array(
			'administrator' => self::CHIAVI_PUBBLICAZIONE,
			RUOLO           => self::CHIAVI_PUBBLICAZIONE,
		);
	}

	/**
	 * La corrispondenza fra nomi generici e permessi derivati dal tipo.
	 *
	 * @return array<string, string>|\WP_Error
	 */
	public static function mappa() {
		if ( ! function_exists( 'conformita_core_capacita_tipo' ) ) {
			return new \WP_Error(
				'albo_meccanismo_comune_assente',
				__( 'I permessi del tipo atto si chiedono al meccanismo comune, che non e\' caricato.', 'albo-pretorio-pa' )
			);
		}

		return conformita_core_capacita_tipo( TIPO );
	}

	/**
	 * Assegna i permessi ai ruoli.
	 *
	 * **Additiva e ripetibile**: non toglie mai niente, nemmeno un permesso che
	 * l'amministrazione avesse aggiunto per conto suo, e rieseguirla non cambia
	 * lo stato.
	 *
	 * Dopo i ruoli dichiarati passa su **tutti gli altri**, ed e' la parte che
	 * fa arrivare un permesso nuovo anche ai siti gia' installati. Il criterio
	 * e' il permesso distintivo dell'insieme, non un permesso qualunque: chi
	 * possiede quello per pubblicare riceve l'insieme di chi pubblica, chi
	 * possiede solo quello per redigere riceve l'insieme di chi redige. Cosi'
	 * completare i permessi mancanti non promuove nessuno.
	 *
	 * @param array<string, array<int, string>>|null $assegnazioni Ruoli e insiemi da assegnare, oppure quelli del componente.
	 * @return true|\WP_Error
	 */
	public static function applica( ?array $assegnazioni = null ) {
		$mappa = self::mappa();

		if ( is_wp_error( $mappa ) ) {
			return $mappa;
		}

		$assegnazioni = null === $assegnazioni ? self::assegnazioni() : $assegnazioni;

		if ( null === get_role( RUOLO ) ) {
			add_role( RUOLO, self::nome_ruolo(), array( 'read' => true ) );
		}

		foreach ( $assegnazioni as $ruolo => $chiavi ) {
			self::concedi( (string) $ruolo, $chiavi, $mappa );
		}

		foreach ( array_keys( wp_roles()->role_objects ) as $nome ) {
			if ( isset( $assegnazioni[ $nome ] ) ) {
				continue;
			}

			$oggetto = get_role( (string) $nome );

			if ( null === $oggetto ) {
				continue;
			}

			if ( $oggetto->has_cap( $mappa['publish_posts'] ) ) {
				self::concedi( (string) $nome, self::CHIAVI_PUBBLICAZIONE, $mappa );
			} elseif ( $oggetto->has_cap( $mappa['edit_posts'] ) ) {
				self::concedi( (string) $nome, self::CHIAVI_REDAZIONE, $mappa );
			}
		}

		return true;
	}

	/**
	 * Concede a un ruolo i permessi derivati di un insieme.
	 *
	 * @param string                $ruolo  Identificativo del ruolo.
	 * @param array<int, string>    $chiavi Nomi generici dei permessi.
	 * @param array<string, string> $mappa  Corrispondenza fra nomi generici e derivati.
	 */
	private static function concedi( string $ruolo, array $chiavi, array $mappa ): void {
		$oggetto = get_role( $ruolo );

		if ( null === $oggetto ) {
			return;
		}

		foreach ( $chiavi as $chiave ) {
			if ( ! isset( $mappa[ $chiave ] ) ) {
				continue;
			}

			if ( $oggetto->has_cap( $mappa[ $chiave ] ) ) {
				continue;
			}

			$oggetto->add_cap( $mappa[ $chiave ] );
		}
	}

	/**
	 * Nessun ruolo del sito possiede i permessi del tipo atto.
	 *
	 * @return bool
	 */
	public static function nessun_ruolo_possiede(): bool {
		$mappa = self::mappa();

		if ( is_wp_error( $mappa ) ) {
			return false;
		}

		foreach ( wp_roles()->role_objects as $oggetto ) {
			if ( $oggetto->has_cap( $mappa['edit_posts'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Segnala in bacheca che nessun ruolo puo' gestire gli atti.
	 *
	 * E' la condizione che altrimenti viene scambiata per un guasto del
	 * componente: il menu non compare e nessuno sa perche'.
	 */
	public static function mostra_avviso(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::nessun_ruolo_possiede() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: nome del componente, 2: nome visibile del ruolo proprio del componente. */
					__( '%1$s: nessun ruolo possiede i permessi del tipo atto, quindi nessuno puo\' gestirlo dall\'amministrazione. Il ruolo %2$s dovrebbe possederli.', 'albo-pretorio-pa' ),
					NOME,
					self::nome_ruolo()
				)
			)
		);
	}

	/**
	 * Toglie i permessi del tipo da tutti i ruoli e rimuove il ruolo proprio.
	 *
	 * Serve alla suite di prove e alla disinstallazione. **Si tolgono soltanto i
	 * permessi che nominano il tipo**: la corrispondenza del meccanismo comune
	 * contiene anche voci che valgono per tutto WordPress, e toglierle da ogni
	 * ruolo sarebbe un danno vero.
	 */
	public static function azzera(): void {
		$mappa = self::mappa();

		if ( ! is_wp_error( $mappa ) ) {
			foreach ( wp_roles()->role_objects as $oggetto ) {
				foreach ( $mappa as $permesso ) {
					if ( false === strpos( (string) $permesso, TIPO ) ) {
						continue;
					}

					if ( $oggetto->has_cap( $permesso ) ) {
						$oggetto->remove_cap( $permesso );
					}
				}
			}
		}

		if ( null !== get_role( RUOLO ) ) {
			remove_role( RUOLO );
		}
	}
}
