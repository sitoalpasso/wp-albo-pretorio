<?php
/**
 * La scheda "Dati dell'atto" nella schermata dell'atto.
 *
 * Tipo di atto e organo si scelgono fra le voci esistenti, senza nessuna
 * preselezione; data di adozione e numero proprio si scrivono. Al salvataggio
 * ogni dato si valida da solo: quello sbagliato resta com'era e un avviso lo
 * nomina, gli altri si salvano. Il "tutto o niente" vale per i passaggi di
 * stato, non per il salvataggio di una bozza.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Riquadro, salvataggio e avviso dei rifiuti della scheda.
 */
final class SchedaAtto {

	/**
	 * Identificativo del riquadro.
	 *
	 * @var string
	 */
	const RIQUADRO = 'albo-pretorio-dati-atto';

	/**
	 * Radice dell'azione del gettone della scheda.
	 *
	 * @var string
	 */
	const AZIONE = 'albo_pretorio_dati_atto';

	/**
	 * Nome del campo del gettone.
	 *
	 * **La sua presenza e' cio' che distingue un invio della scheda** da ogni
	 * altro salvataggio dell'atto: un salvataggio da codice non porta i campi
	 * della scheda, e leggerli come vuoti toglierebbe i dati a ogni salvataggio.
	 *
	 * @var string
	 */
	const CAMPO_GETTONE = 'albo_pretorio_dati_atto_gettone';

	/**
	 * Campo del tipo di atto.
	 *
	 * @var string
	 */
	const CAMPO_TIPO = 'albo_pretorio_tipo_atto';

	/**
	 * Campo dell'organo.
	 *
	 * @var string
	 */
	const CAMPO_ORGANO = 'albo_pretorio_organo';

	/**
	 * Campo della data di adozione.
	 *
	 * @var string
	 */
	const CAMPO_DATA = 'albo_pretorio_data_adozione';

	/**
	 * Campo del numero proprio.
	 *
	 * @var string
	 */
	const CAMPO_NUMERO = 'albo_pretorio_numero_proprio';

	/**
	 * Per quanti secondi i motivi di un rifiuto aspettano la pagina successiva.
	 *
	 * @var int
	 */
	const DURATA_RIFIUTO = 300;

	/**
	 * Gli atti il cui invio e' gia' stato elaborato nella scrittura in corso.
	 *
	 * Il guardiano dei passaggi elabora l'invio **prima** di decidere il
	 * passaggio in verifica; l'aggancio di fine salvataggio lo troverebbe di
	 * nuovo e lo rifarebbe. Il segno si toglie alla fine di ogni scrittura.
	 *
	 * @var array<int, true>
	 */
	private static $elaborati = array();

	/**
	 * L'azione del gettone per un atto.
	 *
	 * **Legata all'atto, non soltanto alla scheda.** Con un'azione sola il
	 * gettone preso dalla schermata di un atto varrebbe per qualunque altro.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	public static function azione( int $atto_id ): string {
		return self::AZIONE . '_' . $atto_id;
	}

	/**
	 * Aggancio a `add_meta_boxes_` del tipo: il riquadro.
	 */
	public static function riquadro(): void {
		add_meta_box(
			self::RIQUADRO,
			__( 'Dati dell\'atto', 'albo-pretorio-pa' ),
			array( self::class, 'mostra' ),
			TIPO,
			'normal',
			'high'
		);
	}

	/**
	 * Il contenuto del riquadro.
	 *
	 * @param \WP_Post $atto Atto.
	 */
	public static function mostra( $atto ): void {
		if ( ! $atto instanceof \WP_Post || ! current_user_can( 'edit_post', $atto->ID ) ) {
			return;
		}

		$atto_id = (int) $atto->ID;
		$tipo    = DatiAtto::tipo( $atto_id );
		$organo  = DatiAtto::organo( $atto_id );

		if ( ! in_array( $atto->post_status, ChiusuraPubblicazione::STATI_BOZZA, true ) ) {
			self::sola_lettura( $atto_id, $tipo, $organo );
			return;
		}

		wp_nonce_field( self::azione( $atto_id ), self::CAMPO_GETTONE );

		self::menu(
			self::CAMPO_TIPO,
			__( 'Tipo di atto', 'albo-pretorio-pa' ),
			TASSONOMIA_TIPO_ATTO,
			null === $tipo ? 0 : (int) $tipo->term_id
		);

		self::menu(
			self::CAMPO_ORGANO,
			__( 'Organo che ha adottato l\'atto', 'albo-pretorio-pa' ),
			TASSONOMIA_ORGANO,
			null === $organo ? 0 : (int) $organo->term_id
		);

		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="date" id="%1$s" name="%1$s" value="%3$s" /></p>',
			esc_attr( self::CAMPO_DATA ),
			esc_html__( 'Data di adozione', 'albo-pretorio-pa' ),
			esc_attr( (string) DatiAtto::data_adozione( $atto_id ) )
		);

		printf(
			'<p><label for="%1$s">%2$s</label><br /><input type="text" id="%1$s" name="%1$s" value="%3$s" /></p><p class="description">%4$s</p>',
			esc_attr( self::CAMPO_NUMERO ),
			esc_html__( 'Numero proprio dell\'atto', 'albo-pretorio-pa' ),
			esc_attr( DatiAtto::numero_proprio( $atto_id ) ),
			esc_html__( 'Facoltativo. E\' il numero dell\'atto, per esempio della determinazione, non quello di pubblicazione, che assegna il sistema.', 'albo-pretorio-pa' )
		);
	}

	/**
	 * I dati dell'atto senza campi da compilare, per un atto che non e' in bozza.
	 *
	 * Niente gettone: senza, nessun invio di questa schermata e' un invio del riquadro.
	 *
	 * @param int           $atto_id Atto.
	 * @param \WP_Term|null $tipo    Tipo di atto.
	 * @param \WP_Term|null $organo  Organo.
	 */
	private static function sola_lettura( int $atto_id, ?\WP_Term $tipo, ?\WP_Term $organo ): void {
		$assente = __( 'non indicato', 'albo-pretorio-pa' );
		$righe   = array(
			__( 'Tipo di atto', 'albo-pretorio-pa' )     => null === $tipo ? $assente : $tipo->name,
			__( 'Organo che ha adottato l\'atto', 'albo-pretorio-pa' ) => null === $organo ? $assente : $organo->name,
			__( 'Data di adozione', 'albo-pretorio-pa' ) => (string) ( DatiAtto::data_adozione( $atto_id ) ?? $assente ),
			__( 'Numero proprio dell\'atto', 'albo-pretorio-pa' ) => '' === DatiAtto::numero_proprio( $atto_id ) ? $assente : DatiAtto::numero_proprio( $atto_id ),
		);

		echo '<dl class="albo-pretorio-dati">';

		foreach ( $righe as $etichetta => $valore ) {
			printf( '<dt>%1$s</dt><dd>%2$s</dd>', esc_html( $etichetta ), esc_html( $valore ) );
		}

		echo '</dl>';

		printf( '<p class="description">%s</p>', esc_html__( 'I dati si modificano solo mentre l\'atto e\' in bozza.', 'albo-pretorio-pa' ) );
	}

	/**
	 * Un menu di scelta fra le voci di un elenco, con la voce vuota in testa.
	 *
	 * La voce vuota e' selezionata quando l'atto non ne ha una valida: nessun
	 * valore proposto dal programma.
	 *
	 * @param string $campo      Nome del campo.
	 * @param string $etichetta  Etichetta.
	 * @param string $tassonomia Elenco di voci.
	 * @param int    $scelta     Voce assegnata, zero se nessuna.
	 */
	private static function menu( string $campo, string $etichetta, string $tassonomia, int $scelta ): void {
		$voci = get_terms(
			array(
				'taxonomy'   => $tassonomia,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		printf( '<p><label for="%1$s">%2$s</label><br /><select id="%1$s" name="%1$s">', esc_attr( $campo ), esc_html( $etichetta ) );
		printf( '<option value=""%1$s>%2$s</option>', selected( 0, $scelta, false ), esc_html__( 'Scegliere una voce', 'albo-pretorio-pa' ) );

		foreach ( is_array( $voci ) ? $voci : array() as $voce ) {
			if ( ! $voce instanceof \WP_Term ) {
				continue;
			}

			$nome = $voce->name;

			if ( TASSONOMIA_TIPO_ATTO === $tassonomia && is_wp_error( Durate::del_tipo( (int) $voce->term_id ) ) ) {
				$nome = sprintf(
					/* translators: %s: nome del tipo di atto. */
					__( '%s (senza durata: non si pubblica)', 'albo-pretorio-pa' ),
					$voce->name
				);
			}

			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $voce->term_id,
				selected( (int) $voce->term_id, $scelta, false ),
				esc_html( $nome )
			);
		}

		echo '</select></p>';
	}

	/**
	 * Aggancio a `save_post_` del tipo: salva i dati inviati dalla scheda.
	 *
	 * @param int      $atto_id Atto.
	 * @param \WP_Post $atto    Atto appena scritto.
	 */
	public static function da_salvataggio( $atto_id, $atto ): void {
		$atto_id = (int) $atto_id;

		// Senza il gettone non e' un invio della scheda: niente da fare.
		if ( ! isset( $_POST[ self::CAMPO_GETTONE ] ) ) {
			return;
		}

		if ( ! $atto instanceof \WP_Post || TIPO !== $atto->post_type || wp_is_post_revision( $atto_id ) || wp_is_post_autosave( $atto_id ) ) {
			return;
		}

		if ( isset( self::$elaborati[ $atto_id ] ) ) {
			return;
		}

		self::$elaborati[ $atto_id ] = true;

		$gettone = sanitize_text_field( wp_unslash( $_POST[ self::CAMPO_GETTONE ] ) );

		if ( ! wp_verify_nonce( $gettone, self::azione( $atto_id ) ) ) {
			self::rifiuta( $atto_id, array( __( 'Dati dell\'atto non salvati: il modulo e\' scaduto o non e\' stato riconosciuto. Ricaricare la pagina e riprovare.', 'albo-pretorio-pa' ) ) );
			return;
		}

		if ( ! current_user_can( 'edit_post', $atto_id ) ) {
			self::rifiuta( $atto_id, array( __( 'Dati dell\'atto non salvati: non si possiede il permesso di modificare questo atto.', 'albo-pretorio-pa' ) ) );
			return;
		}

		if ( ! in_array( get_post_status( $atto_id ), ChiusuraPubblicazione::STATI_BOZZA, true ) ) {
			self::rifiuta( $atto_id, array( __( 'Dati dell\'atto non salvati: si modificano solo mentre l\'atto e\' in bozza.', 'albo-pretorio-pa' ) ) );
			return;
		}

		$motivi = array();

		$voci = array(
			TASSONOMIA_TIPO_ATTO => array( self::CAMPO_TIPO, __( 'Tipo di atto non salvato: la voce scelta non e\' un tipo di atto.', 'albo-pretorio-pa' ) ),
			TASSONOMIA_ORGANO    => array( self::CAMPO_ORGANO, __( 'Organo non salvato: la voce scelta non e\' un organo.', 'albo-pretorio-pa' ) ),
		);

		foreach ( $voci as $tassonomia => list( $campo, $rifiuto ) ) {
			$valore = self::campo( $campo );

			if ( null === $valore ) {
				continue;
			}

			$esito = false === $valore ? $rifiuto : self::salva_voce( $atto_id, $tassonomia, $valore, $rifiuto );

			if ( null !== $esito ) {
				$motivi[] = $esito;
			}
		}

		$data = self::campo( self::CAMPO_DATA );

		if ( '' === $data ) {
			delete_post_meta( $atto_id, META_DATA_ADOZIONE );
		} elseif ( is_string( $data ) && null !== DatiAtto::data_da_ingresso( $data ) ) {
			update_post_meta( $atto_id, META_DATA_ADOZIONE, $data );
		} elseif ( null !== $data ) {
			$motivi[] = __( 'Data di adozione non salvata: non e\' una data valida del calendario.', 'albo-pretorio-pa' );
		}

		$numero = self::campo( self::CAMPO_NUMERO );

		if ( false === $numero ) {
			$motivi[] = __( 'Numero proprio non salvato: non e\' un testo.', 'albo-pretorio-pa' );
		} elseif ( null !== $numero ) {
			$numero = DatiAtto::numero_da_ingresso( $numero );

			if ( '' === $numero ) {
				delete_post_meta( $atto_id, META_NUMERO_PROPRIO );
			} else {
				update_post_meta( $atto_id, META_NUMERO_PROPRIO, $numero );
			}
		}

		if ( array() !== $motivi ) {
			self::rifiuta( $atto_id, $motivi );
		}
	}

	/**
	 * Aggancio a `wp_insert_post`, all'ultima priorita': la scrittura e' finita, il segno si toglie.
	 *
	 * @param int $atto_id Contenuto appena scritto.
	 */
	public static function da_fine_scrittura( $atto_id ): void {
		unset( self::$elaborati[ (int) $atto_id ] );
	}

	/**
	 * Il valore di un campo della scheda, cosi' com'e' arrivato.
	 *
	 * **Non passa da `sanitize_text_field`**, che toglierebbe un a capo finale e
	 * farebbe passare per buono un valore che non lo e': ogni campo si valida
	 * poi contro il suo formato stretto, e il numero proprio si ripulisce con la
	 * sua regola. Un campo assente non si tocca; uno che non e' un testo, per
	 * esempio arrivato come elenco, e' un valore sbagliato e non uno vuoto.
	 *
	 * @param string $nome Nome del campo.
	 * @return string|false|null Testo, falso se non e' un testo, nullo se assente.
	 */
	private static function campo( string $nome ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il gettone e' verificato da chi chiama, prima di leggere qualunque campo.
		if ( ! isset( $_POST[ $nome ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- come sopra; ogni campo si valida contro il suo formato stretto.
		$valore = wp_unslash( $_POST[ $nome ] );

		return is_string( $valore ) ? $valore : false;
	}

	/**
	 * Assegna all'atto la voce scelta, o la toglie se la scelta e' vuota.
	 *
	 * La voce si assegna per identificativo, mai per nome: un nome che non
	 * esiste farebbe creare a WordPress una voce nuova.
	 *
	 * @param int    $atto_id    Atto.
	 * @param string $tassonomia Elenco di voci.
	 * @param string $valore     Identificativo ricevuto, o vuoto.
	 * @param string $rifiuto    Motivo se la voce non e' valida.
	 * @return string|null Motivo del rifiuto, o nullo se salvato.
	 */
	private static function salva_voce( int $atto_id, string $tassonomia, string $valore, string $rifiuto ): ?string {
		$oggetto = get_taxonomy( $tassonomia );

		if ( false === $oggetto || ! current_user_can( $oggetto->cap->assign_terms ) ) {
			return __( 'Dati dell\'atto non salvati: non si possiede il permesso di assegnare le voci degli elenchi.', 'albo-pretorio-pa' );
		}

		if ( '' === $valore ) {
			wp_set_object_terms( $atto_id, array(), $tassonomia );
			return null;
		}

		$voce_id = 1 === preg_match( '/\A[1-9][0-9]*\z/', $valore ) ? filter_var( $valore, FILTER_VALIDATE_INT ) : false;
		$voce    = false === $voce_id ? null : get_term( (int) $voce_id, $tassonomia );

		if ( ! $voce instanceof \WP_Term || $tassonomia !== $voce->taxonomy ) {
			return $rifiuto;
		}

		$esito = wp_set_object_terms( $atto_id, array( (int) $voce->term_id ), $tassonomia );

		return is_wp_error( $esito ) ? $rifiuto : null;
	}

	/**
	 * Conserva i motivi di un rifiuto per la pagina che segue il salvataggio.
	 *
	 * @param int                $atto_id Atto.
	 * @param array<int, string> $motivi  Motivi.
	 */
	private static function rifiuta( int $atto_id, array $motivi ): void {
		set_transient( self::chiave_rifiuto( $atto_id ), $motivi, self::DURATA_RIFIUTO );
	}

	/**
	 * Chiave del dato temporaneo, per utente e atto.
	 *
	 * @param int $atto_id Atto.
	 * @return string
	 */
	private static function chiave_rifiuto( int $atto_id ): string {
		return 'albo_pretorio_dati_rifiuto_' . get_current_user_id() . '_' . $atto_id;
	}

	/**
	 * Preleva i motivi dell'ultimo rifiuto dell'utente su un atto, e li toglie.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, string>
	 */
	public static function preleva_rifiuto( int $atto_id ): array {
		$motivi = get_transient( self::chiave_rifiuto( $atto_id ) );

		if ( ! is_array( $motivi ) ) {
			return array();
		}

		delete_transient( self::chiave_rifiuto( $atto_id ) );

		return array_map( 'strval', $motivi );
	}

	/**
	 * Aggancio a `admin_notices`: i motivi nella schermata dell'atto.
	 */
	public static function mostra_rifiuto(): void {
		$schermata = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $schermata || 'post' !== $schermata->base || TIPO !== $schermata->post_type ) {
			return;
		}

		$atto = get_post();

		if ( ! $atto instanceof \WP_Post ) {
			return;
		}

		foreach ( self::preleva_rifiuto( (int) $atto->ID ) as $motivo ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $motivo ) );
		}
	}
}
