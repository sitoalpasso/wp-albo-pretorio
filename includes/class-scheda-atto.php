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
	 * Campo dell'amministrazione che ha adottato l'atto (ALBO-29).
	 *
	 * @var string
	 */
	const CAMPO_AMMINISTRAZIONE = 'albo_pretorio_amministrazione';

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
			self::attributo( DatiAtto::numero_proprio( $atto_id ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- codificato per intero da self::attributo().
			esc_html__( 'Facoltativo. E\' il numero dell\'atto, per esempio della determinazione, non quello di pubblicazione, che assegna il sistema.', 'albo-pretorio-pa' )
		);

		self::campo_amministrazione( $atto_id );
		self::campi_procedimento( $atto_id );
	}

	/**
	 * La scelta dell'amministrazione che ha adottato l'atto, quando va chiesta (ALBO-29).
	 *
	 * **Nessuna risposta segnata in partenza**: la voce in testa e' "da
	 * scegliere", che non e' una risposta. Il campo non porta l'obbligo di
	 * compilazione del browser, perche' la bozza si salva anche incompleta: la
	 * scelta si pretende al passaggio in verifica.
	 *
	 * @param int $atto_id Atto.
	 */
	private static function campo_amministrazione( int $atto_id ): void {
		if ( ! AmministrazioniOspitate::da_chiedere( $atto_id ) ) {
			return;
		}

		$scelta = AmministrazioniOspitate::scelta_valida( $atto_id );
		$campo  = self::CAMPO_AMMINISTRAZIONE;

		printf( '<p><label for="%1$s">%2$s</label><br /><select id="%1$s" name="%1$s" aria-describedby="%1$s_aiuto">', esc_attr( $campo ), esc_html( AmministrazioniOspitate::etichetta() ) );
		printf( '<option value=""%1$s>%2$s</option>', null === $scelta ? ' selected="selected"' : '', esc_html__( 'Da scegliere', 'albo-pretorio-pa' ) );
		printf( '<option value="%1$d"%2$s>%3$s</option>', (int) AmministrazioniOspitate::QUESTA, AmministrazioniOspitate::QUESTA === $scelta ? ' selected="selected"' : '', esc_html__( 'Questa amministrazione', 'albo-pretorio-pa' ) );

		foreach ( AmministrazioniOspitate::voci() as $id => $nome ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- codificato per intero da self::attributo().
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $id, $id === $scelta ? ' selected="selected"' : '', self::attributo( $nome ) );
		}

		echo '</select></p>';

		$aiuto = __( 'Obbligatorio per mandare l\'atto in verifica. Si sceglie un\'altra amministrazione quando l\'atto e\' suo e questo albo lo pubblica per conto di quella: il nome compare a chi consulta l\'albo, nell\'elenco e nella scheda. Alla pubblicazione il nome si fissa e non cambia piu\'.', 'albo-pretorio-pa' );
		$manca = null === $scelta && array() !== get_post_meta( $atto_id, AmministrazioniOspitate::META, false ) ? AmministrazioniOspitate::manca( $atto_id ) : null;

		printf( '<p class="description" id="%1$s_aiuto">%2$s</p>', esc_attr( $campo ), esc_html( null === $manca ? $aiuto : $manca . ' ' . $aiuto ) );
	}

	/**
	 * I campi dei dati del procedimento che l'amministrazione ha scelto di usare (ALBO-33).
	 *
	 * Responsabile e ufficio si scelgono dall'elenco in vigore, senza nessuna
	 * voce proposta. Un valore che l'atto porta e che non e' piu' nell'elenco
	 * resta fra le voci, segnato, cosi' che salvare la bozza non lo tolga.
	 *
	 * @param int $atto_id Atto.
	 */
	private static function campi_procedimento( int $atto_id ): void {
		foreach ( DatiProcedimento::DATI as $dato ) {
			if ( ! DatiProcedimento::in_uso( $dato ) ) {
				continue;
			}

			$campo  = self::campo_procedimento( $dato );
			$valore = DatiProcedimento::valore( $atto_id, $dato );

			if ( ! isset( DatiProcedimento::ELENCHI[ $dato ] ) ) {
				printf(
					'<p><label for="%1$s">%2$s</label><br /><input type="text" id="%1$s" name="%1$s" value="%3$s" maxlength="%4$d" /></p><p class="description">%5$s</p>',
					esc_attr( $campo ),
					esc_html( DatiProcedimento::etichetta( $dato ) ),
					self::attributo( $valore ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- codificato per intero da self::attributo().
					(int) DatiProcedimento::LUNGHEZZA_MASSIMA,
					esc_html__( 'Facoltativo. L\'ente o l\'ufficio che chiede la pubblicazione, per esempio per un atto di un altro ente; mai il nome di una persona privata. Si mostra sulla scheda dell\'atto.', 'albo-pretorio-pa' )
				);
				continue;
			}

			$elenco = DatiProcedimento::elenco( $dato );

			printf( '<p><label for="%1$s">%2$s</label><br /><select id="%1$s" name="%1$s">', esc_attr( $campo ), esc_html( DatiProcedimento::etichetta( $dato ) ) );
			printf( '<option value=""%1$s>%2$s</option>', selected( '', $valore, false ), esc_html__( 'Nessuno', 'albo-pretorio-pa' ) );

			if ( '' !== $valore && ! in_array( $valore, $elenco, true ) ) {
				printf(
					'<option value="%1$s" selected="selected">%2$s</option>',
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- codificato per intero da self::attributo().
					self::attributo( $valore ),
					self::attributo(
						sprintf(
							/* translators: %s: nome non piu' presente nell'elenco. */
							__( '%s (non piu\' nell\'elenco)', 'albo-pretorio-pa' ),
							$valore
						)
					)
					// phpcs:enable
				);
			}

			foreach ( $elenco as $nome ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- codificato per intero da self::attributo().
				printf( '<option value="%1$s"%2$s>%3$s</option>', self::attributo( $nome ), selected( $nome, $valore, false ), self::attributo( $nome ) );
			}

			echo '</select></p>';
			printf( '<p class="description">%s</p>', esc_html__( 'Facoltativo. Si mostra sulla scheda dell\'atto. L\'elenco lo tiene chi pubblica, nella schermata Dati del procedimento.', 'albo-pretorio-pa' ) );
		}
	}

	/**
	 * Un valore dei dati del procedimento pronto per un attributo o un testo, codificato per intero.
	 *
	 * **Anche le entita' gia' presenti**, a differenza di `esc_attr()`: un nome conservato come
	 * `Ente &quot;Nord&quot;` arriverebbe al browser come `Ente "Nord"`, e il campo lo rimanderebbe
	 * cosi', cambiando il dato al primo salvataggio della bozza.
	 *
	 * @param string $testo Valore.
	 * @return string
	 */
	private static function attributo( string $testo ): string {
		return htmlspecialchars( $testo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
	}

	/**
	 * Il nome del campo di un dato del procedimento.
	 *
	 * @param string $dato Uno dei tre dati.
	 * @return string
	 */
	public static function campo_procedimento( string $dato ): string {
		return 'albo_pretorio_procedimento_' . $dato;
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

		$di = AmministrazioniOspitate::di_atto( $atto_id );

		if ( null !== $di ) {
			$righe[ AmministrazioniOspitate::etichetta() ] = $di['leggibile']
				? $di['nome']
				: ( $di['fissato'] ? __( 'non leggibile in questo momento', 'albo-pretorio-pa' ) : __( 'voce non piu\' nell\'elenco', 'albo-pretorio-pa' ) );
		} elseif ( AmministrazioniOspitate::QUESTA === AmministrazioniOspitate::scelta( $atto_id ) ) {
			$righe[ AmministrazioniOspitate::etichetta() ] = __( 'Questa amministrazione', 'albo-pretorio-pa' );
		}

		foreach ( DatiProcedimento::DATI as $dato ) {
			if ( DatiProcedimento::in_uso( $dato ) ) {
				$valore = DatiProcedimento::valore( $atto_id, $dato );

				$righe[ DatiProcedimento::etichetta( $dato ) ] = '' === $valore ? $assente : $valore;
			}
		}

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
				update_post_meta( $atto_id, META_NUMERO_PROPRIO, wp_slash( $numero ) );
			}
		}

		$amministrazione = self::campo( self::CAMPO_AMMINISTRAZIONE );

		/*
		 * "Da scegliere" non toglie mai una scelta gia' scritta. Una scelta che
		 * non vale piu' (la voce e' stata tolta, o sull'atto c'e' un valore
		 * storto) resta dov'e', e con lei il blocco: il menu la mostra come
		 * "Da scegliere", e un invio cosi' com'e' non deve sbloccare l'atto
		 * come atto di questa amministrazione senza che nessuno l'abbia scelto.
		 * Nemmeno una scelta che vale si toglie: fra il controllo e la
		 * cancellazione un'altra richiesta potrebbe togliere la voce, e l'atto
		 * resterebbe senza scelta a elenco vuoto, cioe' di questa
		 * amministrazione. Solo una scelta esplicita, "Questa amministrazione"
		 * compresa, sostituisce tutte le righe, anche due righe storte.
		 */
		if ( '' === $amministrazione ) {
			$amministrazione = null;
		} elseif ( is_string( $amministrazione ) && 1 === preg_match( '/^(0|[1-9]\d{0,8})$/D', $amministrazione ) && ( AmministrazioniOspitate::QUESTA === (int) $amministrazione || isset( AmministrazioniOspitate::voci()[ (int) $amministrazione ] ) ) ) {
			$sostituita = self::sostituisci_scelta( $atto_id, $amministrazione );

			if ( 'ripristinata' === $sostituita ) {
				$motivi[] = __( 'Amministrazione che ha adottato l\'atto non salvata: la scrittura non e\' riuscita, e resta la scelta di prima.', 'albo-pretorio-pa' );
			} elseif ( 'incerta' === $sostituita ) {
				$motivi[] = __( 'Amministrazione che ha adottato l\'atto: la scrittura non e\' riuscita e la banca dati non ha confermato se e\' rimasta la scelta di prima o quella nuova. Ricontrollare la scelta prima di mandare l\'atto in verifica.', 'albo-pretorio-pa' );
			}
		} elseif ( null !== $amministrazione ) {
			$motivi[] = __( 'Amministrazione che ha adottato l\'atto non salvata: la voce scelta non e\' nell\'elenco delle amministrazioni ospitate.', 'albo-pretorio-pa' );
		}

		$motivi = array_merge( $motivi, self::salva_procedimento( $atto_id ) );

		if ( array() !== $motivi ) {
			self::rifiuta( $atto_id, $motivi );
		}
	}

	/**
	 * Sostituisce la scelta dell'amministrazione senza lasciare l'atto senza scelta nemmeno per un istante.
	 *
	 * Cancellare e poi scrivere lascerebbe un momento senza scelta: un'altra
	 * richiesta che togliesse l'ultima voce e mandasse l'atto in verifica in
	 * quel momento lo vedrebbe come atto di questa amministrazione. Qui le
	 * righe che ci sono si riscrivono tutte con il valore nuovo in una sola
	 * istruzione, poi si tolgono quelle in piu'; si scrive una riga nuova solo
	 * se non ce n'e' nessuna. La scelta uguale non si tocca.
	 *
	 * Tutto sta in una transazione, o in un punto di ripristino dentro quella
	 * di un altro: o la banca dati mostra una riga sola con il valore chiesto,
	 * o si torna indietro su tutto. Ogni lettura controlla l'errore della
	 * connessione, perche' una lettura fallita risponde come un elenco vuoto.
	 * Una prima lettura che fallisce non scrive niente. `ripristinata` vuol
	 * dire che la banca dati mostra di nuovo le righe di prima; `incerta`
	 * che non lo si e' potuto confermare: lo stato e' comunque quello di prima
	 * o quello chiesto, mai un altro, perche' la transazione non lascia vie di
	 * mezzo, ma va ricontrollato.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $valore  Scelta gia' validata.
	 * @return string `scritta`, `ripristinata` o `incerta`.
	 */
	private static function sostituisci_scelta( int $atto_id, string $valore ): string {
		global $wpdb;

		$righe = static function () use ( $wpdb, $atto_id ) {
			$wpdb->last_error = '';
			$lette            = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $atto_id, AmministrazioniOspitate::META ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- si legge la banca dati, non la memoria.

			return is_array( $lette ) && '' === (string) $wpdb->last_error ? $lette : null;
		};

		$prima = $righe();

		if ( null === $prima ) {
			return 'ripristinata';
		}

		if ( 1 === count( $prima ) && $valore === (string) $prima[0]['meta_value'] ) {
			return 'scritta';
		}

		$modo = Repertorio::transazione_esterna() ? 'punto' : 'transazione';

		if ( false === $wpdb->query( 'punto' === $modo ? 'SAVEPOINT albo_pretorio_scelta' : 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- istruzioni fisse della transazione.
			return 'ripristinata';
		}

		$riuscita = false;

		try {
			if ( array() === $prima ) {
				add_post_meta( $atto_id, AmministrazioniOspitate::META, $valore, true );
			} else {
				update_post_meta( $atto_id, AmministrazioniOspitate::META, $valore );

				$adesso = $righe();

				if ( null !== $adesso && array( $valore ) === array_values( array_unique( array_map( 'strval', array_column( $adesso, 'meta_value' ) ) ) ) ) {
					foreach ( array_slice( $adesso, 1 ) as $riga ) {
						delete_metadata_by_mid( 'post', (int) $riga['meta_id'] );
					}
				}
			}

			wp_cache_delete( $atto_id, 'post_meta' );
			$dopo     = $righe();
			$riuscita = null !== $dopo && 1 === count( $dopo ) && $valore === (string) $dopo[0]['meta_value']
				&& false !== $wpdb->query( 'punto' === $modo ? 'RELEASE SAVEPOINT albo_pretorio_scelta' : 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- istruzioni fisse della transazione.
		} finally {
			if ( ! $riuscita ) {
				$wpdb->query( 'punto' === $modo ? 'ROLLBACK TO SAVEPOINT albo_pretorio_scelta' : 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- istruzioni fisse della transazione.
				wp_cache_delete( $atto_id, 'post_meta' );
			}
		}

		if ( $riuscita ) {
			return 'scritta';
		}

		return $righe() === $prima ? 'ripristinata' : 'incerta';
	}

	/**
	 * Salva i dati del procedimento in uso; quelli non in uso non si toccano.
	 *
	 * **Un dato non in uso resta com'e', anche se l'invio lo porta**: la scelta
	 * dell'amministrazione decide che cosa si chiede, e un atto portato da un
	 * albo precedente conserva i suoi dati qualunque sia. Per responsabile e
	 * ufficio vale una voce dell'elenco in vigore, oppure il valore che l'atto
	 * porta gia', che resta anche se nel frattempo e' stato tolto dall'elenco.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, string> Motivi dei rifiuti.
	 */
	private static function salva_procedimento( int $atto_id ): array {
		$motivi = array();

		foreach ( DatiProcedimento::DATI as $dato ) {
			if ( ! DatiProcedimento::in_uso( $dato ) ) {
				continue;
			}

			$valore = self::campo( self::campo_procedimento( $dato ) );

			if ( null === $valore ) {
				continue;
			}

			$nome = is_string( $valore ) ? trim( $valore ) : '';

			if ( false === $valore ) {
				$errore = true;
			} elseif ( '' === $nome ) {
				// Un valore conservato che la lettura non riconosce, per esempio importato, la scheda lo mostra
				// vuoto: l'invio vuoto che ne segue non e' una scelta di toglierlo, e non lo cancella.
				if ( '' !== DatiProcedimento::valore( $atto_id, $dato ) || array() === get_post_meta( $atto_id, DatiProcedimento::meta( $dato ), false ) ) {
					delete_post_meta( $atto_id, DatiProcedimento::meta( $dato ) );
				}
				continue;
			} elseif ( isset( DatiProcedimento::ELENCHI[ $dato ] ) ) {
				$errore = ! in_array( $nome, DatiProcedimento::elenco( $dato ), true ) && DatiProcedimento::valore( $atto_id, $dato ) !== $nome;
			} else {
				$nome   = DatiAtto::numero_da_ingresso( $nome );
				$errore = '' === $nome || mb_strlen( $nome, 'UTF-8' ) > DatiProcedimento::LUNGHEZZA_MASSIMA;
			}

			if ( $errore ) {
				$motivi[] = sprintf(
					/* translators: %s: etichetta del dato. */
					__( '%s non salvato: il valore non e\' una voce dell\'elenco in vigore oppure non e\' un testo valido.', 'albo-pretorio-pa' ),
					DatiProcedimento::etichetta( $dato )
				);
				continue;
			}

			// `update_post_meta` toglie le barre rovesciate al valore, che qui e' gia' senza: si rimettono, o un
			// nome come "Settore A\B" si salverebbe come "Settore AB".
			update_post_meta( $atto_id, DatiProcedimento::meta( $dato ), wp_slash( $nome ) );
		}

		return $motivi;
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
