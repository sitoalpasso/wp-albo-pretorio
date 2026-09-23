<?php
/**
 * Durata di pubblicazione per tipo di atto (ALBO-04, parte di configurazione).
 *
 * Ogni tipo di atto porta un solo dato con tre valori insieme: per quanti
 * giorni i suoi atti restano esposti, da dove viene quel numero (una norma o
 * una scelta dell'amministrazione) e, per una norma, i suoi estremi. Nessun
 * valore ha un ripiego: un tipo senza durata valida non ha durata, e i suoi
 * atti non si pubblicheranno.
 *
 * La validazione gira in due momenti. Al salvataggio, per rifiutare subito e
 * dirlo a chi ha salvato. E a ogni lettura, per la stessa ragione per cui la
 * scadenza e' una proprieta' del dato letto: chi scrive nella banca dati
 * passando di lato non deve poter consegnare al componente una durata che il
 * salvataggio avrebbe rifiutato.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Configurazione, validazione e lettura della durata di un tipo di atto.
 */
final class Durate {

	/**
	 * Le due origini ammesse, e nessun'altra.
	 *
	 * @var array<int, string>
	 */
	const ORIGINI = array( 'norma', 'amministrazione' );

	/**
	 * Azione del gettone di sicurezza del modulo.
	 *
	 * @var string
	 */
	const AZIONE_GETTONE = 'albo_pretorio_durata_tipo';

	/**
	 * Nome del campo del gettone.
	 *
	 * **La sua presenza e' cio' che distingue un invio del modulo della durata**
	 * da ogni altro salvataggio del tipo, come la modifica rapida dall'elenco o
	 * un componente che rinomina un tipo: quelli passano dallo stesso aggancio
	 * senza i campi della durata, e leggerli come vuoti toglierebbe la durata a
	 * ogni rinomina.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_durata_gettone';

	/**
	 * Nome del campo dei giorni.
	 *
	 * @var string
	 */
	const CAMPO_GIORNI = 'albo_pretorio_durata_giorni';

	/**
	 * Nome del campo dell'origine.
	 *
	 * @var string
	 */
	const CAMPO_ORIGINE = 'albo_pretorio_durata_origine';

	/**
	 * Nome del campo degli estremi.
	 *
	 * @var string
	 */
	const CAMPO_ESTREMI = 'albo_pretorio_durata_estremi';

	/**
	 * Identificativo della colonna nell'elenco dei tipi.
	 *
	 * @var string
	 */
	const COLONNA = 'albo_pretorio_durata';

	/**
	 * Per quanti secondi il motivo di un rifiuto aspetta la pagina successiva.
	 *
	 * @var int
	 */
	const DURATA_RIFIUTO = 300;

	/**
	 * Dichiara il dato a WordPress.
	 *
	 * Il dato non e' esposto all'interfaccia per programmi. Chi puo'
	 * modificarlo attraverso i controlli di WordPress lo decide WordPress
	 * stesso, che per il metadato di una voce chiede il permesso di modificare
	 * quella voce: un controllo nostro in piu' potrebbe solo restringere quello,
	 * e restringerlo non serve.
	 *
	 * @return true|\WP_Error
	 */
	public static function registra() {
		/*
		 * L'elenco dei tipi deve essere nostro, non soltanto esistere: se un
		 * altro componente avesse registrato lo stesso identificativo, il dato
		 * della durata finirebbe dichiarato sulle voci di qualcun altro.
		 */
		if ( ! TipoAtto::registrazione_completa() ) {
			return new \WP_Error(
				'albo_durata_senza_elenco',
				__( 'Durate non dichiarate: l\'elenco dei tipi di atto non risulta registrato da questo componente.', 'albo-pretorio-pa' )
			);
		}

		$registrato = register_term_meta(
			TASSONOMIA_TIPO_ATTO,
			META_DURATA,
			array(
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => false,
			)
		);

		if ( ! $registrato ) {
			return new \WP_Error(
				'albo_durata_non_dichiarata',
				__( 'Durate non dichiarate: WordPress ha rifiutato la dichiarazione del dato.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Aggancio a `init`, dopo la registrazione degli elenchi di voci.
	 */
	public static function da_init(): void {
		self::registra();
	}

	/**
	 * Configura la durata di un tipo di atto.
	 *
	 * I tre valori si scrivono insieme o per niente. Chi chiama da codice e' il
	 * componente o chi lo estende, come per le funzioni di WordPress che
	 * scrivono i termini: il permesso si controlla alla schermata.
	 *
	 * @param int   $tipo_id Tipo di atto.
	 * @param mixed $giorni  Numero di giorni, intero da 1 in su.
	 * @param mixed $origine `norma` oppure `amministrazione`.
	 * @param mixed $estremi Estremi della norma; vuoti per l'amministrazione.
	 * @return true|\WP_Error
	 */
	public static function configura( int $tipo_id, $giorni, $origine, $estremi ) {
		$tipo = self::tipo_di_atto( $tipo_id );

		if ( is_wp_error( $tipo ) ) {
			return $tipo;
		}

		$valore = self::da_ingresso( $giorni, $origine, $estremi );

		if ( is_wp_error( $valore ) ) {
			return $valore;
		}

		update_term_meta( $tipo_id, META_DURATA, $valore );

		/*
		 * La postcondizione si rilegge dalla banca dati. `update_term_meta`
		 * risponde falso anche quando il valore era gia' quello, quindi la sua
		 * risposta non dice niente: conta cio' che si legge dopo.
		 */
		wp_cache_delete( $tipo_id, 'term_meta' );

		if ( self::del_tipo( $tipo_id ) !== $valore ) {
			return new \WP_Error(
				'albo_durata_non_scritta',
				__( 'La durata non risulta scritta: rileggendola, non corrisponde a quella richiesta.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Toglie la durata a un tipo di atto.
	 *
	 * @param int $tipo_id Tipo di atto.
	 * @return true|\WP_Error
	 */
	public static function togli( int $tipo_id ) {
		$tipo = self::tipo_di_atto( $tipo_id );

		if ( is_wp_error( $tipo ) ) {
			return $tipo;
		}

		delete_term_meta( $tipo_id, META_DURATA );
		wp_cache_delete( $tipo_id, 'term_meta' );

		if ( metadata_exists( 'term', $tipo_id, META_DURATA ) ) {
			return new \WP_Error(
				'albo_durata_non_tolta',
				__( 'La durata non risulta tolta: rileggendola, c\'e\' ancora.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * La durata di un tipo di atto, se c'e' ed e' valida.
	 *
	 * @param int $tipo_id Tipo di atto.
	 * @return array{giorni: int, origine: string, estremi: string}|\WP_Error
	 */
	public static function del_tipo( int $tipo_id ) {
		$tipo = self::tipo_di_atto( $tipo_id );

		if ( is_wp_error( $tipo ) ) {
			return $tipo;
		}

		$righe = get_term_meta( $tipo_id, META_DURATA, false );

		if ( array() === $righe ) {
			return new \WP_Error(
				'albo_durata_assente',
				__( 'Il tipo di atto non ha una durata configurata: i suoi atti non si pubblicano.', 'albo-pretorio-pa' )
			);
		}

		/*
		 * Due righe per lo stesso dato non sono una durata: WordPress ne
		 * restituirebbe la prima, e quale sia la prima dipende da come sono
		 * state scritte, non da una scelta di qualcuno.
		 */
		if ( 1 !== count( $righe ) || ! self::ben_formata( $righe[0] ) ) {
			return new \WP_Error(
				'albo_durata_non_valida',
				__( 'La durata memorizzata per il tipo di atto non e\' valida: i suoi atti non si pubblicano finche\' non viene configurata di nuovo.', 'albo-pretorio-pa' )
			);
		}

		return $righe[0];
	}

	/**
	 * Converte i campi ricevuti nel valore da memorizzare, o spiega perche' no.
	 *
	 * @param mixed $giorni  Numero di giorni.
	 * @param mixed $origine Origine.
	 * @param mixed $estremi Estremi.
	 * @return array{giorni: int, origine: string, estremi: string}|\WP_Error
	 */
	private static function da_ingresso( $giorni, $origine, $estremi ) {
		/*
		 * Solo interi veri o testo fatto di sole cifre. Il filtro di PHP accetta
		 * spazi e a capo intorno al numero, che qui non sono un numero; e
		 * rifiuta da se' i valori oltre il massimo intero, che una conversione
		 * diretta ridurrebbe in silenzio.
		 */
		$numero = false;

		if ( is_int( $giorni ) ) {
			$numero = $giorni;
		} elseif ( is_string( $giorni ) && 1 === preg_match( '/\A[0-9]+\z/', $giorni ) ) {
			$numero = filter_var( $giorni, FILTER_VALIDATE_INT );
		}

		if ( false === $numero || $numero < 1 ) {
			return new \WP_Error(
				'albo_durata_giorni',
				__( 'Durata non salvata: i giorni devono essere un numero intero da 1 in su.', 'albo-pretorio-pa' )
			);
		}

		if ( ! is_string( $origine ) || ! in_array( $origine, self::ORIGINI, true ) ) {
			return new \WP_Error(
				'albo_durata_origine',
				__( 'Durata non salvata: va detto se il numero di giorni viene da una norma o da una scelta dell\'amministrazione.', 'albo-pretorio-pa' )
			);
		}

		$estremi = is_string( $estremi ) ? trim( $estremi ) : null;

		if ( null === $estremi ) {
			return new \WP_Error(
				'albo_durata_estremi',
				__( 'Durata non salvata: gli estremi della norma non sono un testo.', 'albo-pretorio-pa' )
			);
		}

		if ( 'norma' === $origine && '' === $estremi ) {
			return new \WP_Error(
				'albo_durata_estremi',
				__( 'Durata non salvata: una durata che viene da una norma deve riportare gli estremi della norma.', 'albo-pretorio-pa' )
			);
		}

		if ( 'amministrazione' === $origine && '' !== $estremi ) {
			return new \WP_Error(
				'albo_durata_estremi',
				__( 'Durata non salvata: gli estremi di una norma non si indicano per una durata scelta dall\'amministrazione, perche\' la farebbero sembrare prescritta.', 'albo-pretorio-pa' )
			);
		}

		$valore = array(
			'giorni'  => (int) $numero,
			'origine' => $origine,
			'estremi' => $estremi,
		);

		return self::ben_formata( $valore ) ? $valore : new \WP_Error(
			'albo_durata_non_valida',
			__( 'Durata non salvata: i valori non formano una durata valida.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Il valore memorizzato e' una durata: la stessa regola del salvataggio.
	 *
	 * Esattamente tre chiavi, giorni come intero vero da 1 in su, origine fra
	 * le due ammesse, estremi presenti per la norma e assenti per
	 * l'amministrazione.
	 *
	 * @param mixed $valore Valore letto.
	 * @return bool
	 */
	private static function ben_formata( $valore ): bool {
		if ( ! is_array( $valore ) ) {
			return false;
		}

		$chiavi = array_keys( $valore );
		sort( $chiavi );

		if ( array( 'estremi', 'giorni', 'origine' ) !== $chiavi ) {
			return false;
		}

		if ( ! is_int( $valore['giorni'] ) || $valore['giorni'] < 1 ) {
			return false;
		}

		if ( ! is_string( $valore['origine'] ) || ! in_array( $valore['origine'], self::ORIGINI, true ) ) {
			return false;
		}

		if ( ! is_string( $valore['estremi'] ) || trim( $valore['estremi'] ) !== $valore['estremi'] ) {
			return false;
		}

		if ( 'norma' === $valore['origine'] ) {
			return '' !== $valore['estremi'];
		}

		return '' === $valore['estremi'];
	}

	/**
	 * Il termine esiste ed e' un tipo di atto.
	 *
	 * @param int $tipo_id Identificativo del termine.
	 * @return true|\WP_Error
	 */
	private static function tipo_di_atto( int $tipo_id ) {
		$termine = get_term( $tipo_id, TASSONOMIA_TIPO_ATTO );

		if ( ! $termine instanceof \WP_Term ) {
			return new \WP_Error(
				'albo_durata_tipo_inesistente',
				__( 'Il tipo di atto indicato non esiste.', 'albo-pretorio-pa' )
			);
		}

		return true;
	}

	/**
	 * Aggancio al salvataggio del tipo dalla schermata, nuovo o modificato.
	 *
	 * @param int $tipo_id Tipo di atto appena salvato.
	 */
	public static function da_salvataggio( $tipo_id ): void {
		$tipo_id = (int) $tipo_id;

		// Senza il gettone non e' un invio del modulo della durata: niente da fare.
		if ( ! isset( $_POST[ self::CAMPO_GETTONE ] ) ) {
			return;
		}

		$gettone = sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) );

		if ( ! wp_verify_nonce( $gettone, self::AZIONE_GETTONE ) ) {
			self::rifiuta( __( 'Durata non salvata: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) );
			return;
		}

		if ( ! current_user_can( 'edit_term', $tipo_id ) ) {
			self::rifiuta( __( 'Durata non salvata: non si possiede il permesso di configurare i tipi di atto.', 'albo-pretorio-pa' ) );
			return;
		}

		$giorni  = isset( $_POST[ self::CAMPO_GIORNI ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GIORNI ] ) ) : '';
		$origine = isset( $_POST[ self::CAMPO_ORIGINE ] ) ? sanitize_key( wp_unslash( $_POST[ self::CAMPO_ORIGINE ] ) ) : '';
		$estremi = isset( $_POST[ self::CAMPO_ESTREMI ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_ESTREMI ] ) ) : '';

		$esito = ( '' === $giorni && '' === $origine && '' === $estremi )
			? self::togli( $tipo_id )
			: self::configura( $tipo_id, $giorni, $origine, $estremi );

		if ( is_wp_error( $esito ) ) {
			self::rifiuta( $esito->get_error_message() );
			return;
		}

		delete_transient( self::chiave_rifiuto() );
	}

	/**
	 * Conserva il motivo di un rifiuto per la pagina che segue il salvataggio.
	 *
	 * @param string $motivo Motivo.
	 */
	private static function rifiuta( string $motivo ): void {
		set_transient( self::chiave_rifiuto(), $motivo, self::DURATA_RIFIUTO );
	}

	/**
	 * Chiave del dato temporaneo, per utente.
	 *
	 * @return string
	 */
	private static function chiave_rifiuto(): string {
		return 'albo_pretorio_durata_rifiuto_' . get_current_user_id();
	}

	/**
	 * Preleva il motivo dell'ultimo rifiuto dell'utente corrente, e lo toglie.
	 *
	 * @return string|null
	 */
	public static function preleva_rifiuto(): ?string {
		$motivo = get_transient( self::chiave_rifiuto() );

		if ( false === $motivo ) {
			return null;
		}

		delete_transient( self::chiave_rifiuto() );

		return (string) $motivo;
	}

	/**
	 * Mostra il motivo di un rifiuto sulle schermate dei tipi di atto.
	 */
	public static function mostra_rifiuto(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || TASSONOMIA_TIPO_ATTO !== $schermata->taxonomy ) {
			return;
		}

		$motivo = self::preleva_rifiuto();

		if ( null === $motivo ) {
			return;
		}

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
	}

	/**
	 * Campi della durata sul modulo di un tipo nuovo.
	 */
	public static function campi_nuovo(): void {
		wp_nonce_field( self::AZIONE_GETTONE, self::CAMPO_GETTONE );

		echo '<div class="form-field">';
		self::campi( null );
		echo '</div>';
	}

	/**
	 * Campi della durata sul modulo di modifica di un tipo.
	 *
	 * @param \WP_Term $tipo Tipo di atto.
	 */
	public static function campi_modifica( $tipo ): void {
		$durata = $tipo instanceof \WP_Term ? self::del_tipo( (int) $tipo->term_id ) : null;

		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'Durata di pubblicazione', 'albo-pretorio-pa' ) . '</th><td>';
		wp_nonce_field( self::AZIONE_GETTONE, self::CAMPO_GETTONE );
		self::campi( is_array( $durata ) ? $durata : null );
		echo '</td></tr>';
	}

	/**
	 * I tre campi, senza nessun valore preimpostato.
	 *
	 * @param array{giorni: int, origine: string, estremi: string}|null $durata Durata valida esistente.
	 */
	private static function campi( ?array $durata ): void {
		$giorni  = null === $durata ? '' : (string) $durata['giorni'];
		$origine = null === $durata ? '' : $durata['origine'];
		$estremi = null === $durata ? '' : $durata['estremi'];

		printf(
			'<p><label for="%1$s">%2$s</label> <input type="number" min="1" step="1" id="%1$s" name="%1$s" value="%3$s"></p>',
			esc_attr( self::CAMPO_GIORNI ),
			esc_html__( 'Giorni di pubblicazione', 'albo-pretorio-pa' ),
			esc_attr( $giorni )
		);

		echo '<fieldset><legend>' . esc_html__( 'Da dove viene questo numero di giorni', 'albo-pretorio-pa' ) . '</legend>';

		$etichette = array(
			'norma'           => __( 'Da una norma, che fissa il termine', 'albo-pretorio-pa' ),
			'amministrazione' => __( 'Da una scelta dell\'amministrazione, dove la norma non fissa un termine', 'albo-pretorio-pa' ),
		);

		foreach ( $etichette as $valore => $etichetta ) {
			printf(
				'<p><label><input type="radio" name="%1$s" value="%2$s"%3$s> %4$s</label></p>',
				esc_attr( self::CAMPO_ORIGINE ),
				esc_attr( $valore ),
				checked( $origine, $valore, false ),
				esc_html( $etichetta )
			);
		}

		echo '</fieldset>';

		printf(
			'<p><label for="%1$s">%2$s</label> <input type="text" id="%1$s" name="%1$s" value="%3$s"></p><p class="description">%4$s</p>',
			esc_attr( self::CAMPO_ESTREMI ),
			esc_html__( 'Estremi della norma', 'albo-pretorio-pa' ),
			esc_attr( $estremi ),
			esc_html__( 'Obbligatori se il numero di giorni viene da una norma, da lasciare vuoti altrimenti. Svuotando tutti e tre i campi la durata viene tolta, e gli atti di questo tipo non si pubblicano.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * Aggiunge la colonna della durata all'elenco dei tipi.
	 *
	 * @param array<string, string> $colonne Colonne esistenti.
	 * @return array<string, string>
	 */
	public static function colonne( $colonne ): array {
		$colonne                  = (array) $colonne;
		$colonne[ self::COLONNA ] = __( 'Durata di pubblicazione', 'albo-pretorio-pa' );

		return $colonne;
	}

	/**
	 * Contenuto della colonna: lo stato vero, letto con la stessa regola di tutti.
	 *
	 * @param string $contenuto Contenuto proposto.
	 * @param string $colonna   Colonna.
	 * @param int    $tipo_id   Tipo di atto.
	 * @return string
	 */
	public static function colonna( $contenuto, $colonna, $tipo_id ): string {
		if ( self::COLONNA !== $colonna ) {
			return (string) $contenuto;
		}

		$durata = self::del_tipo( (int) $tipo_id );

		if ( is_wp_error( $durata ) ) {
			return esc_html__( 'Non configurata: gli atti di questo tipo non si pubblicano.', 'albo-pretorio-pa' );
		}

		if ( 'norma' === $durata['origine'] ) {
			return esc_html(
				sprintf(
					/* translators: 1: numero di giorni, 2: estremi della norma. */
					_n( '%1$d giorno, fissato dalla norma: %2$s', '%1$d giorni, fissati dalla norma: %2$s', $durata['giorni'], 'albo-pretorio-pa' ),
					$durata['giorni'],
					$durata['estremi']
				)
			);
		}

		return esc_html(
			sprintf(
				/* translators: %d: numero di giorni. */
				_n( '%d giorno, scelto dall\'amministrazione', '%d giorni, scelti dall\'amministrazione', $durata['giorni'], 'albo-pretorio-pa' ),
				$durata['giorni']
			)
		);
	}

	/**
	 * Toglie la dichiarazione del dato. Serve alle prove.
	 */
	public static function azzera(): void {
		unregister_term_meta( TASSONOMIA_TIPO_ATTO, META_DURATA );
	}
}
