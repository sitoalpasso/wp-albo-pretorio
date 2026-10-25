<?php
/**
 * Gli atti di un'altra amministrazione pubblicati su questo albo (ALBO-29).
 *
 * Le fonti prevedono che un ente pubblichi sull'albo di un altro (l. 69/2009,
 * art. 32, c. 3). Il componente non stabilisce chi risponde dei dati: lo
 * stabiliscono le amministrazioni fra loro. Fa una cosa sola: ogni atto
 * ospitato porta il nome dell'amministrazione che l'ha adottato, e il pubblico
 * lo vede.
 *
 * Le amministrazioni ospitate sono un elenco che tiene chi pubblica. Finche'
 * l'elenco e' vuoto la domanda non compare: un atto che non puo' essere di
 * nessun altro non ha niente da dichiarare. Appena l'elenco ha una voce, ogni
 * atto pretende la scelta prima di andare in verifica, **senza nessuna
 * risposta preselezionata**: un atto ospitato attribuito per errore all'ente
 * che ospita sarebbe una pubblicazione con l'autore sbagliato.
 *
 * Ogni voce ha un numero che non cambia e non si riusa, cosi' che la si possa
 * rinominare. Sull'atto si scrive il numero della voce scelta, oppure zero per
 * "questa amministrazione". **Il nome si fissa alla pubblicazione**, nella
 * voce di conferma del registro, che e' solo in aggiunta: rinominare o
 * togliere la voce dopo non cambia l'autore di un atto pubblicato, e una
 * seconda copia sull'atto sarebbe un dato in piu' da difendere.
 *
 * Nessun ruolo e nessun permesso proprio: l'atto ospitato lo pubblica e lo
 * toglie chi pubblica tutti gli altri.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Elenco delle amministrazioni ospitate e scelta sull'atto.
 */
final class AmministrazioniOspitate {

	/**
	 * Opzione dell'elenco.
	 *
	 * @var string
	 */
	const OPZIONE = 'albo_pretorio_amministrazioni_ospitate';

	/**
	 * Il dato dell'atto: zero per questa amministrazione, altrimenti il numero della voce.
	 *
	 * @var string
	 */
	const META = '_albo_pretorio_amministrazione';

	/**
	 * Il valore della scelta "questa amministrazione".
	 *
	 * @var int
	 */
	const QUESTA = 0;

	/**
	 * Il dettaglio della voce di conferma con il nome fissato alla pubblicazione.
	 *
	 * @var string
	 */
	const DETTAGLIO_NOME = 'amministrazione';

	/**
	 * Il dettaglio della voce di conferma con il numero della voce scelta.
	 *
	 * @var string
	 */
	const DETTAGLIO_VOCE = 'amministrazione_voce';

	/**
	 * Voci massime dell'elenco.
	 *
	 * @var int
	 */
	const VOCI_MASSIME = 100;

	/**
	 * Numero massimo di una voce: oltre, il dato sull'atto non si legge.
	 *
	 * @var int
	 */
	const NUMERO_MASSIMO = 999999999;

	/**
	 * Dichiara il dato dell'atto a WordPress, protetto e senza esposizione per programmi.
	 *
	 * @return bool
	 */
	public static function registra(): bool {
		return register_post_meta(
			TIPO,
			self::META,
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
	}

	/**
	 * Toglie la dichiarazione. Serve alla suite di prove.
	 */
	public static function azzera(): void {
		unregister_post_meta( TIPO, self::META );
	}

	/**
	 * L'impostazione salvata, controllata, o nulla se manca o non si legge.
	 *
	 * @return array{voci: array<int, array{id: int, nome: string}>, prossimo: int, utente: int, istante: string}|null
	 */
	public static function impostazione(): ?array {
		$valore = get_option( self::OPZIONE, null );

		if ( ! is_array( $valore ) || array_keys( $valore ) !== array( 'voci', 'prossimo', 'utente', 'istante' ) ) {
			return null;
		}

		if ( ! is_array( $valore['voci'] ) || ! array_is_list( $valore['voci'] ) || ! is_int( $valore['prossimo'] ) || ! is_int( $valore['utente'] ) || ! is_string( $valore['istante'] ) ) {
			return null;
		}

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $valore['istante'] ) || count( $valore['voci'] ) > self::VOCI_MASSIME ) {
			return null;
		}

		if ( $valore['prossimo'] < 1 || $valore['prossimo'] > self::NUMERO_MASSIMO + 1 ) {
			return null;
		}

		$visti = array();
		$nomi  = array();

		foreach ( $valore['voci'] as $voce ) {
			if ( ! is_array( $voce ) || array_keys( $voce ) !== array( 'id', 'nome' ) || ! is_int( $voce['id'] ) || ! is_string( $voce['nome'] ) ) {
				return null;
			}

			// Ogni numero sotto il prossimo, una volta sola; ogni nome valido, una volta sola anche con maiuscole diverse.
			if ( $voce['id'] < 1 || $voce['id'] >= $valore['prossimo'] || isset( $visti[ $voce['id'] ] ) || null !== self::errore_nome( $voce['nome'] ) ) {
				return null;
			}

			$chiave = mb_strtolower( $voce['nome'], 'UTF-8' );

			if ( isset( $nomi[ $chiave ] ) ) {
				return null;
			}

			$visti[ $voce['id'] ] = true;
			$nomi[ $chiave ]      = true;
		}

		return $valore;
	}

	/**
	 * Le voci in vigore, per numero. Vuoto senza impostazione.
	 *
	 * **Un'impostazione che non si legge non vale come elenco vuoto**: la
	 * domanda resta, e passa solo l'atto indicato come di questa
	 * amministrazione, vedi `manca()`.
	 *
	 * @return array<int, string>
	 */
	public static function voci(): array {
		$impostazione = self::impostazione();
		$voci         = array();

		foreach ( null === $impostazione ? array() : $impostazione['voci'] as $voce ) {
			$voci[ $voce['id'] ] = $voce['nome'];
		}

		return $voci;
	}

	/**
	 * Se l'impostazione e' stata scritta ma non si legge.
	 *
	 * @return bool
	 */
	public static function illeggibile(): bool {
		return null !== get_option( self::OPZIONE, null ) && null === self::impostazione();
	}

	/**
	 * Controlla e salva l'elenco, tutto insieme o niente.
	 *
	 * Le voci si tolgono solo se indicate: una voce aggiunta da un altro
	 * mentre la schermata era aperta, e quindi assente dall'invio, resta com'e'.
	 * Il nome inviato per una voce che non c'e' piu' rifiuta il salvataggio.
	 *
	 * @param mixed              $nomi   Nomi delle voci esistenti, per numero.
	 * @param mixed              $tolte  Numeri delle voci da togliere.
	 * @param mixed              $nuove  Voci nuove, un nome per riga.
	 * @param int                $utente Chi salva.
	 * @param \DateTimeImmutable $adesso Istante del salvataggio.
	 * @return true|\WP_Error
	 */
	public static function salva( $nomi, $tolte, $nuove, int $utente, \DateTimeImmutable $adesso ) {
		if ( self::illeggibile() ) {
			return self::errore( 'albo_ospitate_illeggibile', __( 'l\'elenco salvato non si legge, e salvarlo sopra perderebbe le voci che gli atti indicano. Va ripristinato dalla banca dati.', 'albo-pretorio-pa' ) );
		}

		if ( ! is_array( $nomi ) || ! is_array( $tolte ) || ! is_string( $nuove ) ) {
			return self::errore( 'albo_ospitate_invio', __( 'l\'invio non ha la forma della schermata. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
		}

		$impostazione = self::impostazione();
		$voci         = self::voci();
		$prossimo     = null === $impostazione ? 1 : $impostazione['prossimo'];

		foreach ( $tolte as $numero ) {
			$id = is_string( $numero ) && 1 === preg_match( '/^[1-9]\d{0,8}$/D', $numero ) ? (int) $numero : 0;

			if ( 0 === $id ) {
				return self::errore( 'albo_ospitate_invio', __( 'una voce da togliere non e\' indicata come la schermata la indica. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
			}

			// Una voce gia' tolta da altri non e' un errore: il risultato e' quello chiesto.
			unset( $voci[ $id ] );
		}

		foreach ( $nomi as $numero => $nome ) {
			$id = 1 === preg_match( '/^[1-9]\d{0,8}$/D', (string) $numero ) ? (int) $numero : 0;

			if ( 0 === $id || ! is_string( $nome ) ) {
				return self::errore( 'albo_ospitate_invio', __( 'un nome non e\' arrivato come la schermata lo manda. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
			}

			if ( in_array( (string) $id, $tolte, true ) ) {
				continue;
			}

			if ( ! isset( $voci[ $id ] ) ) {
				return self::errore( 'albo_ospitate_voce_sparita', __( 'una delle voci rinominate e\' stata tolta da qualcun altro mentre la schermata era aperta. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
			}

			$pulito = self::pulisci( $nome );
			$errore = self::errore_nome( $pulito );

			if ( null !== $errore ) {
				/* translators: 1: nome attuale della voce, 2: motivo. */
				return self::errore( 'albo_ospitate_nome', sprintf( __( 'il nome nuovo di %1$s non va: %2$s', 'albo-pretorio-pa' ), $voci[ $id ], $errore ) );
			}

			$voci[ $id ] = $pulito;
		}

		$aggiunte = DatiProcedimento::leggi_elenco( $nuove );

		if ( is_wp_error( $aggiunte ) ) {
			/* translators: %s: motivo. */
			return self::errore( 'albo_ospitate_nome', sprintf( __( 'fra le voci nuove, %s', 'albo-pretorio-pa' ), $aggiunte->get_error_message() ) );
		}

		foreach ( $aggiunte as $nome ) {
			if ( $prossimo > self::NUMERO_MASSIMO ) {
				return self::errore( 'albo_ospitate_troppe', __( 'i numeri delle voci sono esauriti.', 'albo-pretorio-pa' ) );
			}

			$voci[ $prossimo ] = $nome;
			++$prossimo;
		}

		if ( count( $voci ) > self::VOCI_MASSIME ) {
			/* translators: %d: numero massimo di voci. */
			return self::errore( 'albo_ospitate_troppe', sprintf( __( 'l\'elenco supererebbe le %d voci.', 'albo-pretorio-pa' ), self::VOCI_MASSIME ) );
		}

		$visti = array();

		foreach ( $voci as $nome ) {
			$chiave = mb_strtolower( $nome, 'UTF-8' );

			if ( isset( $visti[ $chiave ] ) ) {
				/* translators: %s: nome ripetuto. */
				return self::errore( 'albo_ospitate_ripetuta', sprintf( __( 'il nome %s compare due volte.', 'albo-pretorio-pa' ), $nome ) );
			}

			$visti[ $chiave ] = true;
		}

		ksort( $voci );

		$elenco = array();

		foreach ( $voci as $id => $nome ) {
			$elenco[] = array(
				'id'   => (int) $id,
				'nome' => $nome,
			);
		}

		$valore = array(
			'voci'     => $elenco,
			'prossimo' => $prossimo,
			'utente'   => $utente,
			'istante'  => $adesso->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
		);

		update_option( self::OPZIONE, $valore, true );

		if ( self::impostazione() !== $valore ) {
			return self::errore( 'albo_ospitate_non_scritto', __( 'la banca dati non ha confermato la scrittura. Riprovare.', 'albo-pretorio-pa' ) );
		}

		return true;
	}

	/**
	 * Il nome come lo si salva: spazi ripetuti ridotti a uno e tolti ai lati, nient'altro.
	 *
	 * @param string $nome Nome inviato.
	 * @return string
	 */
	private static function pulisci( string $nome ): string {
		return trim( (string) preg_replace( '/ {2,}/', ' ', $nome ), ' ' );
	}

	/**
	 * Perche' un nome non va, o nullo se va. Le regole sono quelle degli elenchi del procedimento.
	 *
	 * @param string $nome Nome gia' pulito.
	 * @return string|null
	 */
	private static function errore_nome( string $nome ): ?string {
		if ( '' === $nome ) {
			return __( 'e\' vuoto. Per togliere una voce si usa la sua casella "Togli".', 'albo-pretorio-pa' );
		}

		$letto = DatiProcedimento::leggi_elenco( $nome );

		if ( is_wp_error( $letto ) ) {
			// Il motivo comincia con il numero della riga, che qui non c'e': si tiene solo la spiegazione.
			return (string) preg_replace( '/^[^:]*: /', '', $letto->get_error_message() );
		}

		return array( $nome ) === $letto ? null : __( 'contiene caratteri non ammessi.', 'albo-pretorio-pa' );
	}

	/**
	 * Un rifiuto del salvataggio.
	 *
	 * @param string $codice Codice.
	 * @param string $motivo Motivo, dopo i due punti.
	 * @return \WP_Error
	 */
	private static function errore( string $codice, string $motivo ): \WP_Error {
		/* translators: %s: motivo. */
		return new \WP_Error( $codice, sprintf( __( 'Elenco non salvato: %s', 'albo-pretorio-pa' ), $motivo ) );
	}

	/**
	 * La scelta scritta sull'atto: zero, il numero di una voce, oppure nullo se manca o non si legge.
	 *
	 * Non dice se la voce c'e' ancora: lo dice `scelta_valida()`.
	 *
	 * @param int $atto_id Atto.
	 * @return int|null
	 */
	public static function scelta( int $atto_id ): ?int {
		$righe = get_post_meta( $atto_id, self::META, false );

		if ( 1 !== count( $righe ) || ! is_string( $righe[0] ) || 1 !== preg_match( '/^(0|[1-9]\d{0,8})$/D', $righe[0] ) ) {
			return null;
		}

		return (int) $righe[0];
	}

	/**
	 * La scelta sull'atto se vale con l'elenco di adesso, oppure nullo.
	 *
	 * @param int $atto_id Atto.
	 * @return int|null
	 */
	public static function scelta_valida( int $atto_id ): ?int {
		$scelta = self::scelta( $atto_id );

		if ( null === $scelta ) {
			return null;
		}

		return self::QUESTA === $scelta || isset( self::voci()[ $scelta ] ) ? $scelta : null;
	}

	/**
	 * Se la domanda va posta per questo atto: l'elenco ha voci, oppure l'atto indica una voce.
	 *
	 * Il secondo caso e' l'elenco svuotato dopo la scelta: senza la domanda chi
	 * redige non potrebbe correggere un atto che non va piu' avanti. Con
	 * l'elenco che non si legge la domanda c'e', con la sola risposta "questa
	 * amministrazione".
	 *
	 * @param int $atto_id Atto.
	 * @return bool
	 */
	public static function da_chiedere( int $atto_id ): bool {
		$scelta = self::scelta( $atto_id );

		return array() !== self::voci() || self::illeggibile() || ( null !== $scelta && self::QUESTA !== $scelta );
	}

	/**
	 * Il motivo per cui l'atto non puo' andare avanti, o nullo.
	 *
	 * Manca la scelta quando l'elenco ha voci e l'atto non ne indica una valida,
	 * e quando l'atto indica una voce che non c'e' piu', anche a elenco vuoto:
	 * pubblicarlo come atto di questa amministrazione cambierebbe l'autore che
	 * chi redige ha indicato. Un elenco che non si legge non si indovina:
	 * passa solo l'atto che chi redige ha indicato come di questa
	 * amministrazione.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null
	 */
	public static function manca( int $atto_id ): ?string {
		$scelta = self::scelta( $atto_id );

		if ( self::illeggibile() && self::QUESTA !== $scelta ) {
			return __( 'L\'elenco delle amministrazioni ospitate non si legge, quindi non si sa se l\'atto e\' di un\'altra amministrazione. Si puo\' indicare che e\' di questa amministrazione, oppure aspettare che l\'elenco sia ripristinato.', 'albo-pretorio-pa' );
		}

		if ( null !== $scelta && self::QUESTA !== $scelta && ! isset( self::voci()[ $scelta ] ) ) {
			return __( 'L\'amministrazione indicata come autrice dell\'atto non e\' piu\' nell\'elenco delle amministrazioni ospitate: va scelta di nuovo.', 'albo-pretorio-pa' );
		}

		if ( array() !== self::voci() && null === self::scelta_valida( $atto_id ) ) {
			return __( 'Manca l\'amministrazione che ha adottato l\'atto: va scelto se e\' di questa amministrazione o di una di quelle ospitate.', 'albo-pretorio-pa' );
		}

		return null;
	}

	/**
	 * I dettagli per la voce di conferma della pubblicazione: il nome si fissa qui.
	 *
	 * **Si rifa' il controllo sulla stessa lettura dell'elenco da cui si
	 * prende il nome**, e non ci si fida di quello fatto prima: fra i due
	 * un'altra richiesta puo' togliere la voce, e l'atto ospitato uscirebbe
	 * come atto di questa amministrazione. Il nome si legge una volta sola e
	 * la pubblicazione lo scrive cosi'.
	 *
	 * @param int $atto_id Atto.
	 * @return array<string, string|int>|\WP_Error
	 */
	public static function per_registro( int $atto_id ) {
		$manca = self::manca( $atto_id );

		if ( null !== $manca ) {
			return new \WP_Error(
				'albo_manca_amministrazione',
				sprintf(
					/* translators: %s: il motivo. */
					__( 'L\'atto resta in verifica e non si pubblica. %s', 'albo-pretorio-pa' ),
					$manca
				)
			);
		}

		$scelta = self::scelta( $atto_id );
		$voci   = self::voci();

		if ( null === $scelta || self::QUESTA === $scelta || ! isset( $voci[ $scelta ] ) ) {
			return array(
				self::DETTAGLIO_NOME => '',
				self::DETTAGLIO_VOCE => self::QUESTA,
			);
		}

		return array(
			self::DETTAGLIO_NOME => $voci[ $scelta ],
			self::DETTAGLIO_VOCE => $scelta,
		);
	}

	/**
	 * L'amministrazione da mostrare per l'atto, oppure nullo se l'atto non e' ospitato.
	 *
	 * Per un atto pubblicato, annullato o tolto il nome e' quello fissato alla
	 * pubblicazione, letto dalla voce di conferma; se quella voce non si legge
	 * si dice, invece di tacere: tacere farebbe sembrare l'atto di questa
	 * amministrazione. Per un atto in bozza o in verifica e' il nome di adesso.
	 *
	 * @param int $atto_id Atto.
	 * @return array{nome: string, leggibile: bool, fissato: bool}|null
	 */
	public static function di_atto( int $atto_id ): ?array {
		$scelta = self::scelta( $atto_id );

		if ( null === $scelta || self::QUESTA === $scelta ) {
			return null;
		}

		if ( in_array( get_post_status( $atto_id ), ChiusuraPubblicazione::STATI_BOZZA, true ) || ChiusuraPubblicazione::IN_VERIFICA === get_post_status( $atto_id ) ) {
			$voci = self::voci();

			return array(
				'nome'      => $voci[ $scelta ] ?? '',
				'leggibile' => isset( $voci[ $scelta ] ),
				'fissato'   => false,
			);
		}

		$voci = conformita_core_voci_registro(
			array(
				'sezione'   => SEZIONE,
				'contenuto' => $atto_id,
				'azione'    => Passaggi::AZIONE_CONFERMA,
			)
		);

		$nome = '';

		if ( is_array( $voci ) && 1 === count( $voci ) && isset( $voci[0]['dettagli'] ) && is_array( $voci[0]['dettagli'] ) ) {
			$dettagli = $voci[0]['dettagli'];
			$nome     = isset( $dettagli[ self::DETTAGLIO_NOME ], $dettagli[ self::DETTAGLIO_VOCE ] ) && is_string( $dettagli[ self::DETTAGLIO_NOME ] ) && $scelta === $dettagli[ self::DETTAGLIO_VOCE ]
				? $dettagli[ self::DETTAGLIO_NOME ]
				: '';
		}

		return array(
			'nome'      => $nome,
			'leggibile' => '' !== $nome,
			'fissato'   => true,
		);
	}

	/**
	 * Il testo da mostrare al pubblico per un atto ospitato, oppure nullo.
	 *
	 * @param int $atto_id Atto.
	 * @return string|null Testo non sfuggito.
	 */
	public static function da_mostrare( int $atto_id ): ?string {
		$di = self::di_atto( $atto_id );

		if ( null === $di ) {
			return null;
		}

		return $di['leggibile'] ? $di['nome'] : __( 'non leggibile in questo momento', 'albo-pretorio-pa' );
	}

	/**
	 * L'etichetta del dato.
	 *
	 * @return string
	 */
	public static function etichetta(): string {
		return __( 'Amministrazione che ha adottato l\'atto', 'albo-pretorio-pa' );
	}
}
