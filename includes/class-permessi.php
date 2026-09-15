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
	 * Messaggi da mostrare a chi puo' rimediare.
	 *
	 * @var array<int, string>
	 */
	private static $avvisi = array();

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

		$proprio = self::prepara_ruolo_proprio();

		if ( is_wp_error( $proprio ) ) {
			return $proprio;
		}

		/*
		 * Che cosa e' gia' scritto si chiede alla banca dati e non all'oggetto in
		 * memoria. **Dopo una scrittura persa i due divergono**: l'oggetto in
		 * memoria ha il permesso, la banca dati no, e un ritentativo che si fida
		 * della memoria non riscrive niente e fallisce di nuovo per sempre. E'
		 * il caso in cui il ritentativo serve davvero, quindi e' l'unico in cui
		 * non deve saltare il lavoro.
		 */
		$archivio = new \WP_Roles();
		$toccati  = array();

		foreach ( $assegnazioni as $ruolo => $chiavi ) {
			if ( null === get_role( (string) $ruolo ) ) {
				return self::non_scritti( (string) $ruolo, '' );
			}

			self::concedi( (string) $ruolo, $chiavi, $mappa, $archivio );

			$toccati[ (string) $ruolo ] = $chiavi;
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
				self::concedi( (string) $nome, self::CHIAVI_PUBBLICAZIONE, $mappa, $archivio );

				$toccati[ (string) $nome ] = self::CHIAVI_PUBBLICAZIONE;
			} elseif ( $oggetto->has_cap( $mappa['edit_posts'] ) ) {
				self::concedi( (string) $nome, self::CHIAVI_REDAZIONE, $mappa, $archivio );

				$toccati[ (string) $nome ] = self::CHIAVI_REDAZIONE;
			}
		}

		/*
		 * Si verifica **ogni ruolo toccato**, non solo quelli dichiarati. I ruoli
		 * completati qui sopra sono proprio quelli di cui nessuno tiene il conto:
		 * il componente li scopre da se', e un permesso che non arriva la'
		 * resterebbe invisibile fino alla richiesta dopo, quando la versione
		 * risulta gia' memorizzata e nessuno rifara' il lavoro.
		 */
		return self::verifica_scrittura( $toccati, $mappa );
	}

	/**
	 * Crea il ruolo proprio, oppure riconosce quello che c'e' gia'.
	 *
	 * Tre situazioni, e la terza e' quella per cui questa funzione esiste. Il
	 * ruolo non c'e': si crea, con il marcatore. Il ruolo c'e' e ha il
	 * marcatore: e' nostro, lasciato da una versione precedente, e si aggiorna.
	 * Il ruolo c'e' e il marcatore non c'e': **e' di qualcun altro**, e
	 * adottarlo significherebbe consegnare i permessi dell'albo a chiunque lo
	 * possieda gia'. In quel caso non si tocca niente, nemmeno gli altri ruoli:
	 * un sito in cui un identificativo che ci serve e' di un altro componente e'
	 * un sito da sistemare a mano, non da servire a meta'.
	 *
	 * @return true|\WP_Error
	 */
	private static function prepara_ruolo_proprio() {
		$ruolo = get_role( RUOLO );

		if ( null === $ruolo ) {
			add_role(
				RUOLO,
				self::nome_ruolo(),
				array(
					'read'          => true,
					RUOLO_MARCATORE => true,
				)
			);

			return self::verifica_marcatore();
		}

		if ( $ruolo->has_cap( RUOLO_MARCATORE ) ) {
			return true;
		}

		self::avvisa(
			sprintf(
				/* translators: 1: nome del componente, 2: identificativo del ruolo in conflitto. */
				__( '%1$s resta attivo e inerte: esiste gia\' un ruolo chiamato %2$s che non e\' stato creato da questo componente, e assegnargli i permessi dell\'albo li darebbe a chiunque lo possieda. Rinominare quel ruolo, oppure rimuoverlo se non serve piu\'.', 'albo-pretorio-pa' ),
				NOME,
				RUOLO
			)
		);

		return new \WP_Error(
			'albo_ruolo_in_conflitto',
			sprintf(
				/* translators: %s: identificativo del ruolo in conflitto. */
				__( 'Ruolo %s gia\' esistente e non creato da questo componente.', 'albo-pretorio-pa' ),
				RUOLO
			),
			array( 'ruolo' => RUOLO )
		);
	}

	/**
	 * Il ruolo appena creato risulta scritto, marcatore compreso.
	 *
	 * **Controllare che il ruolo esista non basta**: se il marcatore non arriva
	 * nella banca dati, alla richiesta successiva il componente troverebbe un
	 * ruolo senza marcatore e lo direbbe di un altro, per sempre, su un sito
	 * dove invece lo ha creato lui. Da qui la via di recupero: il ruolo creato a
	 * meta' si toglie, e la rimozione si rilegge. Se nemmeno la rimozione
	 * riesce, l'avviso dice qual e' il ruolo da togliere a mano, perche' una
	 * collisione permanente silenziosa e' il peggiore dei due esiti.
	 *
	 * @return true|\WP_Error
	 */
	private static function verifica_marcatore() {
		$riletti = new \WP_Roles();
		$scritto = $riletti->get_role( RUOLO );

		if ( null !== $scritto && $scritto->has_cap( RUOLO_MARCATORE ) ) {
			return true;
		}

		remove_role( RUOLO );

		$verifica = new \WP_Roles();
		$rimosso  = null === $verifica->get_role( RUOLO );

		self::avvisa(
			$rimosso
				? sprintf(
					/* translators: 1: nome del componente, 2: identificativo del ruolo. */
					__( '%1$s resta attivo e inerte: il ruolo %2$s non e\' stato scritto per intero ed e\' stato rimosso. Il componente riprovera\' alla richiesta successiva.', 'albo-pretorio-pa' ),
					NOME,
					RUOLO
				)
				: sprintf(
					/* translators: 1: nome del componente, 2: identificativo del ruolo. */
					__( '%1$s resta attivo e inerte: il ruolo %2$s non e\' stato scritto per intero e non e\' stato possibile rimuoverlo. Va rimosso a mano, altrimenti il componente lo scambiera\' per un ruolo di un altro.', 'albo-pretorio-pa' ),
					NOME,
					RUOLO
				)
		);

		return new \WP_Error(
			'albo_marcatore_non_scritto',
			sprintf(
				/* translators: %s: identificativo del ruolo. */
				__( 'Il ruolo %s non risulta scritto con il proprio marcatore.', 'albo-pretorio-pa' ),
				RUOLO
			),
			array(
				'ruolo'   => RUOLO,
				'rimosso' => $rimosso,
			)
		);
	}

	/**
	 * I permessi assegnati risultano scritti, riletti dalla banca dati.
	 *
	 * **Si rilegge da una copia nuova del registro dei ruoli e non da quella in
	 * memoria.** La differenza conta: un permesso aggiunto all'oggetto in
	 * memoria la cui scrittura non e' andata a buon fine resta vero per tutta
	 * la richiesta e sparisce alla successiva, quando nessuno sta piu'
	 * guardando. Dichiarare riuscita quell'assegnazione significa non rifarla
	 * mai piu', perche' intanto la versione risulterebbe memorizzata.
	 *
	 * @param array<string, array<int, string>> $toccati Ruoli davvero toccati e insiemi applicati.
	 * @param array<string, string>             $mappa   Corrispondenza fra nomi generici e derivati.
	 * @return true|\WP_Error
	 */
	private static function verifica_scrittura( array $toccati, array $mappa ) {
		$riletti = new \WP_Roles();

		foreach ( $toccati as $nome => $chiavi ) {
			$oggetto = $riletti->get_role( (string) $nome );

			if ( null === $oggetto ) {
				return self::non_scritti( (string) $nome, '' );
			}

			foreach ( $chiavi as $chiave ) {
				if ( ! isset( $mappa[ $chiave ] ) ) {
					continue;
				}

				if ( ! $oggetto->has_cap( $mappa[ $chiave ] ) ) {
					return self::non_scritti( (string) $nome, (string) $mappa[ $chiave ] );
				}
			}
		}

		return true;
	}

	/**
	 * L'errore della postcondizione mancata, con l'avviso che lo accompagna.
	 *
	 * @param string $ruolo    Ruolo su cui la verifica e' caduta.
	 * @param string $permesso Permesso mancante, stringa vuota se manca il ruolo.
	 * @return \WP_Error
	 */
	private static function non_scritti( string $ruolo, string $permesso ) {
		self::avvisa(
			sprintf(
				/* translators: 1: nome del componente, 2: identificativo del ruolo. */
				__( '%1$s resta attivo e inerte: i permessi del tipo atto non risultano scritti sul ruolo %2$s. Il componente riprovera\' alla richiesta successiva.', 'albo-pretorio-pa' ),
				NOME,
				$ruolo
			)
		);

		return new \WP_Error(
			'albo_permessi_non_scritti',
			sprintf(
				/* translators: 1: identificativo del ruolo, 2: identificativo del permesso. */
				__( 'Permessi non scritti sul ruolo %1$s: %2$s.', 'albo-pretorio-pa' ),
				$ruolo,
				'' === $permesso ? __( 'il ruolo stesso non risulta scritto', 'albo-pretorio-pa' ) : $permesso
			),
			array(
				'ruolo'    => $ruolo,
				'permesso' => $permesso,
			)
		);
	}

	/**
	 * Registra un messaggio per chi puo' rimediare.
	 *
	 * @param string $messaggio Testo dell'avviso.
	 */
	private static function avvisa( string $messaggio ): void {
		self::$avvisi[] = $messaggio;
	}

	/**
	 * Concede a un ruolo i permessi derivati di un insieme.
	 *
	 * @param string                $ruolo    Identificativo del ruolo.
	 * @param array<int, string>    $chiavi   Nomi generici dei permessi.
	 * @param array<string, string> $mappa    Corrispondenza fra nomi generici e derivati.
	 * @param \WP_Roles             $archivio I ruoli come stanno nella banca dati.
	 */
	private static function concedi( string $ruolo, array $chiavi, array $mappa, \WP_Roles $archivio ): void {
		$oggetto = get_role( $ruolo );

		if ( null === $oggetto ) {
			return;
		}

		$scritto = $archivio->get_role( $ruolo );

		foreach ( $chiavi as $chiave ) {
			if ( ! isset( $mappa[ $chiave ] ) ) {
				continue;
			}

			if ( null !== $scritto && $scritto->has_cap( $mappa[ $chiave ] ) ) {
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

		foreach ( self::$avvisi as $messaggio ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $messaggio ) );
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

		$ruolo = get_role( RUOLO );

		if ( null !== $ruolo && $ruolo->has_cap( RUOLO_MARCATORE ) ) {
			remove_role( RUOLO );
		}

		self::$avvisi = array();
	}
}
