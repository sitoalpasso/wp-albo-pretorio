<?php
/**
 * La barriera sotto WordPress e l'atto fermo per tutto il passaggio: righe A-111..A-115,
 * A-124, A-125 per la pubblicazione, A-126.
 *
 * Le scritture arrivano dalle funzioni di WordPress che non passano da
 * `wp_insert_post` e dalle interfacce dei metadati e degli elenchi, come le
 * userebbe un altro componente, senza istruzioni scritte a mano. Dentro un
 * passaggio le scritture annidate arrivano dagli agganci che WordPress chiama
 * mentre il passaggio scrive.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si scrive e si legge la banca dati come farebbe un altro componente.

/**
 * Scritture che non passano dalla prima porta, e scritture dentro un passaggio.
 */
class BarrieraTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Assetto comune.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * La scrittura ferma la richiesta: restituisce il messaggio del rifiuto.
	 *
	 * @param callable $scrittura Scrittura.
	 * @param string   $caso      Caso, per i messaggi.
	 * @return string
	 */
	private function richiesta_fermata( callable $scrittura, string $caso ): string {
		try {
			$scrittura();
		} catch ( \WPDieException $fermata ) {
			return $fermata->getMessage();
		}

		$this->fail( $caso . ': la richiesta doveva fermarsi.' );
	}

	/**
	 * Un atto pubblicato con il passaggio vero.
	 *
	 * @return int
	 */
	private function atto_pubblicato(): int {
		$id = $this->atto_in_verifica();

		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ), 'Precondizione: pubblicato.' );

		return $id;
	}

	/**
	 * Il numero della riga di un metadato.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $chiave  Chiave.
	 * @return int
	 */
	private function riga_di( int $atto_id, string $chiave ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id LIMIT 1", $atto_id, $chiave ) );
	}

	/**
	 * A-111: ALBO-22, le funzioni di WordPress che scrivono la riga da se' non spostano un atto.
	 *
	 * `wp_publish_post()`, `set_post_type()` in uscita e in entrata e la
	 * pubblicazione programmata del compito pianificato fermano la richiesta,
	 * e l'atto resta identico.
	 */
	public function test_a111_barriera_sotto_wordpress(): void {
		$in_verifica = $this->atto_in_verifica();
		$pubblicato  = $this->atto_pubblicato();

		foreach ( array(
			'in verifica' => $in_verifica,
			'pubblicato'  => $pubblicato,
		) as $caso => $id ) {
			$prima = $this->fotografia( $id );

			if ( 'pubblicato' === $caso ) {
				// Su un contenuto gia' pubblicato WordPress non scrive niente: resta da vedere che niente cambi.
				wp_publish_post( $id );
			} else {
				$messaggio = $this->richiesta_fermata(
					static function () use ( $id ) {
						wp_publish_post( $id );
					},
					'wp_publish_post, ' . $caso
				);

				$this->assertStringContainsString( 'Scrittura rifiutata', $messaggio );
			}

			$this->assertSame( $prima, $this->fotografia( $id ), 'wp_publish_post, ' . $caso . ': niente cambia.' );

			$this->richiesta_fermata(
				static function () use ( $id ) {
					set_post_type( $id, 'post' );
				},
				'set_post_type in uscita, ' . $caso
			);

			$this->assertSame( $prima, $this->fotografia( $id ), 'set_post_type in uscita, ' . $caso . ': niente cambia.' );
		}

		// In entrata: un contenuto ordinario pubblicato non diventa un atto pubblicato.
		$ordinario = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->richiesta_fermata(
			static function () use ( $ordinario ) {
				set_post_type( $ordinario, TIPO );
			},
			'set_post_type in entrata'
		);

		clean_post_cache( $ordinario );
		$this->assertSame( 'post', get_post_type( $ordinario ), 'Il contenuto ordinario resta quello che era.' );

		// La pubblicazione programmata: un atto portato a programmato da fuori non si pubblica dal compito pianificato.
		$programmato = $this->atto_in_verifica();

		$this->scavalca_barriera(
			static function () use ( $programmato ) {
				global $wpdb;

				$wpdb->update(
					$wpdb->posts,
					array(
						'post_status'   => 'future',
						'post_date'     => '2020-01-01 01:00:00',
						'post_date_gmt' => '2020-01-01 00:00:00',
					),
					array( 'ID' => $programmato )
				);
			}
		);

		$prima = $this->fotografia( $programmato );

		$this->assertSame( 'future', $prima['riga']['post_status'], 'Precondizione: programmato, con la data passata.' );

		$this->richiesta_fermata(
			static function () use ( $programmato ) {
				check_and_publish_future_post( $programmato );
			},
			'pubblicazione programmata'
		);

		$this->assertSame( $prima, $this->fotografia( $programmato ), 'Pubblicazione programmata: niente cambia.' );

		/*
		 * Una scrittura vagliata dal primo livello vale per quello che il primo
		 * livello ha visto: un altro filtro delle istruzioni che ne cambia lo
		 * stato strada facendo la fa fermare.
		 */
		$riscritta = $this->atto_completo();
		$riscrivi  = static function ( $istruzione ) use ( $riscritta ) {
			return 1 === preg_match( '/ WHERE `ID` = ' . $riscritta . '$/', (string) $istruzione )
				? str_replace( "`post_status` = 'draft'", "`post_status` = 'publish'", (string) $istruzione )
				: $istruzione;
		};

		add_filter( 'query', $riscrivi );

		try {
			$this->richiesta_fermata(
				static function () use ( $riscritta ) {
					wp_update_post(
						array(
							'ID'         => $riscritta,
							'post_title' => 'Oggetto della bozza',
						)
					);
				},
				'stato riscritto nell\'istruzione'
			);
		} finally {
			remove_filter( 'query', $riscrivi );
		}

		clean_post_cache( $riscritta );
		$this->assertSame( 'draft', get_post_status( $riscritta ), 'La bozza resta bozza.' );

		/*
		 * Una pubblicazione interrotta da un'eccezione prima di arrivare alla
		 * banca dati lascia una scrittura vagliata senza istruzione: non la
		 * usa la prima scrittura dritta che arriva dopo, a passaggio finito.
		 */
		$interrotto = $this->atto_in_verifica();
		$interrompi = static function ( $post_id ) use ( $interrotto ) {
			if ( $interrotto === (int) $post_id ) {
				throw new \RuntimeException( 'Interruzione di prova.' );
			}
		};

		add_action( 'pre_post_update', $interrompi );

		try {
			$this->pubblica_da_codice( $interrotto, $this->pubblicatore );
			$this->fail( 'Precondizione: la pubblicazione doveva interrompersi.' );
		} catch ( \RuntimeException $interruzione ) {
			$this->assertSame( 'Interruzione di prova.', $interruzione->getMessage() );
		} finally {
			remove_action( 'pre_post_update', $interrompi );
		}

		$prima = $this->fotografia( $interrotto, false );

		$this->richiesta_fermata(
			static function () use ( $interrotto ) {
				global $wpdb;

				$wpdb->update( $wpdb->posts, array( 'post_title' => 'Oggetto cambiato dopo' ), array( 'ID' => $interrotto ) );
			},
			'scrittura dritta dopo il passaggio interrotto'
		);

		$this->assertSame( $prima, $this->fotografia( $interrotto, false ), 'Dopo il passaggio interrotto: niente cambia.' );

		// Controlli positivi: le stesse funzioni lavorano sui contenuti ordinari e sulle bozze.
		$articolo = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		wp_publish_post( $articolo );
		clean_post_cache( $articolo );
		$this->assertSame( 'publish', get_post_status( $articolo ), 'Un contenuto ordinario si pubblica.' );

		$bozza = $this->atto_completo();

		$this->assertNotFalse( set_post_type( $bozza, 'post' ), 'Una bozza cambia tipo.' );
		$this->assertNotFalse( set_post_type( $bozza, TIPO ), 'E torna atto, in bozza.' );
		clean_post_cache( $bozza );
		$this->assertSame( 'draft', get_post_status( $bozza ) );
		$this->assertSame( TIPO, get_post_type( $bozza ) );
	}

	/**
	 * A-112: ALBO-22, dati e voci di un atto in verifica o pubblicato non cambiano dalle interfacce di WordPress.
	 */
	public function test_a112_dati_e_voci_di_un_atto_fermo(): void {
		$chiave_fine = conformita_core_chiave_fine_pubblicazione();
		$altra_voce  = $this->voce( TASSONOMIA_ORGANO, 'Consiglio' );

		foreach ( array(
			'in verifica' => $this->atto_in_verifica(),
			'pubblicato'  => $this->atto_pubblicato(),
		) as $caso => $id ) {
			$prima = $this->fotografia( $id );
			$riga  = $this->riga_di( $id, META_DATA_ADOZIONE );

			$this->assertGreaterThan( 0, $riga, 'Precondizione: la data di adozione ha la sua riga.' );

			$this->assertFalse( add_post_meta( $id, 'dato_aggiunto', 'x' ), $caso . ': aggiunta.' );
			$this->assertFalse( update_post_meta( $id, META_DATA_ADOZIONE, '2041-01-01' ), $caso . ': modifica.' );
			$this->assertFalse( delete_post_meta( $id, META_DATA_ADOZIONE ), $caso . ': cancellazione.' );
			$this->assertFalse( update_metadata_by_mid( 'post', $riga, '2041-01-01' ), $caso . ': modifica per numero di riga.' );
			$this->assertFalse( delete_metadata_by_mid( 'post', $riga ), $caso . ': cancellazione per numero di riga.' );
			$this->assertFalse( delete_post_meta_by_key( META_DATA_ADOZIONE ), $caso . ': cancellazione a tutti i contenuti.' );
			$this->assertFalse( update_post_meta( $id, $chiave_fine, '2099-12-31' ), $caso . ': la data di fine da fuori.' );

			$this->richiesta_fermata(
				static function () use ( $id, $altra_voce ) {
					wp_set_object_terms( $id, array( $altra_voce ), TASSONOMIA_ORGANO );
				},
				$caso . ', voce assegnata'
			);
			$this->richiesta_fermata(
				static function () use ( $id ) {
					wp_set_object_terms( $id, array(), TASSONOMIA_TIPO_ATTO );
				},
				$caso . ', voce tolta'
			);

			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': niente cambia.' );

			// I dati di servizio di WordPress restano scrivibili: non dicono niente dell'atto.
			$this->assertNotFalse( update_post_meta( $id, '_edit_lock', time() . ':1' ), $caso . ': dato di servizio.' );

			// Ma una riga di servizio non diventa un dato dell'atto rinominandola per numero di riga.
			$servizio = $this->riga_di( $id, '_edit_lock' );
			$prima    = $this->fotografia( $id );

			$this->assertGreaterThan( 0, $servizio, 'Precondizione: la riga di servizio c\'e\'.' );
			$this->assertFalse( update_metadata_by_mid( 'post', $servizio, '2099-12-31', $chiave_fine ), $caso . ': rinominata nella data di fine.' );
			$this->assertFalse( update_metadata_by_mid( 'post', $servizio, '2041-01-01', META_DATA_ADOZIONE ), $caso . ': rinominata nella data di adozione.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': niente cambia.' );
			$this->assertSame( $servizio, $this->riga_di( $id, '_edit_lock' ), $caso . ': la riga di servizio resta quella.' );
		}

		// Controllo positivo: sulla bozza le stesse interfacce lavorano.
		$bozza = $this->atto_completo();
		$riga  = $this->riga_di( $bozza, META_DATA_ADOZIONE );

		$this->assertNotFalse( add_post_meta( $bozza, 'dato_della_bozza', 'x' ) );
		$this->assertTrue( update_metadata_by_mid( 'post', $this->riga_di( $bozza, 'dato_della_bozza' ), 'y', 'dato_rinominato' ) );
		$this->assertTrue( update_metadata_by_mid( 'post', $this->riga_di( $bozza, 'dato_rinominato' ), 'x', 'dato_della_bozza' ) );
		$this->assertTrue( update_metadata_by_mid( 'post', $riga, '2041-09-11' ) );
		$this->assertNotFalse( update_post_meta( $bozza, META_DATA_ADOZIONE, '2041-09-12' ) );
		$this->assertTrue( delete_post_meta_by_key( 'dato_della_bozza' ) );
		$this->assertIsArray( wp_set_object_terms( $bozza, array( $altra_voce ), TASSONOMIA_ORGANO ) );
		$this->assertTrue( delete_post_meta( $bozza, META_DATA_ADOZIONE ) );
	}

	/**
	 * A-113: ALBO-22, durante un passaggio l'atto e' fermo; una scrittura che non si puo' fermare annulla tutto.
	 *
	 * Una scrittura annidata che si rifiuta prima di cominciare lascia
	 * proseguire il passaggio con l'atto com'era. Una scrittura della riga o
	 * delle voci, che non si puo' fermare senza effetti a valle, fa annullare
	 * il passaggio per intero: nel rimando, e nella pubblicazione anche mentre
	 * si scrive la data di fine.
	 */
	public function test_a113_atto_fermo_per_tutto_il_passaggio(): void {
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		// Nel rimando, un aggancio che normalizza l'oggetto e la data con le interfacce di WordPress.
		$id           = $this->atto_in_verifica();
		$normalizzato = false;
		$normalizza   = static function ( $post_id ) use ( $id, &$normalizzato ) {
			if ( $id === (int) $post_id && ! $normalizzato ) {
				$normalizzato = true;
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => 'Oggetto normalizzato',
					)
				);
				update_post_meta( $id, META_DATA_ADOZIONE, '1999-01-01' );
			}
		};

		$istruite = array();
		$osserva  = static function ( $istruzione ) use ( &$istruite ) {
			$istruite[] = (string) $istruzione;

			return $istruzione;
		};

		add_action( 'save_post', $normalizza );
		add_filter( 'query', $osserva, PHP_INT_MAX - 1 );

		try {
			$esito = $this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
				}
			);
		} finally {
			remove_action( 'save_post', $normalizza );
			remove_filter( 'query', $osserva, PHP_INT_MAX - 1 );
		}

		global $wpdb;

		$this->assertTrue( $normalizzato, 'Precondizione: l\'aggancio e\' partito.' );
		$this->assertIsInt( $esito, 'Le scritture annidate rifiutate non fermano il rimando.' );
		$this->assertCount(
			1,
			preg_grep( '/^UPDATE `' . preg_quote( $wpdb->posts, '/' ) . '` SET .* WHERE `ID` = ' . $id . '$/', $istruite ),
			'La riga si scrive una volta sola: la scrittura annidata si ferma prima di cominciare.'
		);
		clean_post_cache( $id );
		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 'Atto di prova', get_the_title( $id ), 'L\'oggetto e\' quello di prima.' );
		$this->assertSame( '2041-09-10', get_post_meta( $id, META_DATA_ADOZIONE, true ), 'La data di adozione e\' quella di prima.' );

		// Nella pubblicazione, un aggancio che riscrive la data di fine appena scritta: rifiutato, e la fine resta quella calcolata.
		$id        = $this->atto_in_verifica();
		$riscritta = false;
		$chiave    = conformita_core_chiave_fine_pubblicazione();
		$riscrivi  = static function ( $meta_id, $post_id, $nome ) use ( $id, $chiave, &$riscritta ) {
			if ( $id === (int) $post_id && $chiave === $nome && ! $riscritta ) {
				$riscritta = true;
				update_post_meta( $id, $chiave, '2099-12-31' );
			}
		};

		add_action( 'added_post_meta', $riscrivi, 10, 3 );
		add_action( 'updated_post_meta', $riscrivi, 10, 3 );

		try {
			$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );
		} finally {
			remove_action( 'added_post_meta', $riscrivi, 10 );
			remove_action( 'updated_post_meta', $riscrivi, 10 );
		}

		$this->assertTrue( $riscritta, 'Precondizione: la riscrittura della fine e\' partita.' );
		$this->assertIsArray( $esito, 'La riscrittura rifiutata non ferma la pubblicazione.' );
		$this->assertSame( $esito['fine'], conformita_core_fine_pubblicazione( $id ), 'La fine e\' quella calcolata.' );

		// Una voce che nessun atto ha, creata fuori da ogni passaggio.
		$collegio = $this->voce( TASSONOMIA_ORGANO, 'Collegio dei revisori' );

		// Le scritture che non si fermano prima di cominciare: la riga scritta dritta e le voci.
		$casi = array(
			'rimando, riga scritta dritta'         => array(
				'rimando',
				'save_post',
				static function ( int $atto_id ) {
					global $wpdb;

					$wpdb->update( $wpdb->posts, array( 'post_title' => 'Oggetto cambiato' ), array( 'ID' => $atto_id ) );
				},
			),
			'rimando, voce tolta'                  => array(
				'rimando',
				'save_post',
				static function ( int $atto_id ) {
					$voci = wp_get_object_terms( $atto_id, TASSONOMIA_ORGANO, array( 'fields' => 'ids' ) );

					wp_set_object_terms( $atto_id, array(), TASSONOMIA_ORGANO );

					// Un altro componente legge le voci subito dopo: la memoria ricorda i conteggi di adesso.
					foreach ( $voci as $voce ) {
						get_term( (int) $voce );
					}
				},
			),
			'pubblicazione, voce tolta'            => array(
				'pubblicazione',
				'save_post',
				static function ( int $atto_id ) {
					$voci = wp_get_object_terms( $atto_id, TASSONOMIA_ORGANO, array( 'fields' => 'ids' ) );

					wp_set_object_terms( $atto_id, array(), TASSONOMIA_ORGANO );

					// Un altro componente legge le voci subito dopo: la memoria ricorda i conteggi di adesso.
					foreach ( $voci as $voce ) {
						get_term( (int) $voce );
					}
				},
			),
			// A save_post l'atto e' gia' pubblicato e la sua voce lo conta: la memoria ricorda un conteggio che l'annullamento toglie.
			'pubblicazione, voce letta e riga scritta dritta' => array(
				'pubblicazione',
				'save_post',
				function ( int $atto_id ) {
					global $wpdb;

					get_term( $this->voci['organo'] );
					$wpdb->update( $wpdb->posts, array( 'post_title' => 'Oggetto cambiato' ), array( 'ID' => $atto_id ) );
				},
			),
			// Una voce che l'atto non aveva: dopo l'annullamento non e' fra le sue, e la memoria del conteggio va svuotata lo stesso.
			'pubblicazione, voce aggiunta e letta' => array(
				'pubblicazione',
				'save_post',
				static function ( int $atto_id ) use ( $collegio ) {
					wp_set_object_terms( $atto_id, array( $collegio ), TASSONOMIA_ORGANO, true );
					get_term( $collegio );
				},
			),
			'pubblicazione, stato alla fine'       => array(
				'pubblicazione',
				'fine',
				static function ( int $atto_id ) {
					global $wpdb;

					$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $atto_id ) );
				},
			),
			'pubblicazione, wp_publish_post di un altro atto' => array(
				'pubblicazione',
				'save_post',
				'altro',
			),
			// Scritte a mano, sotto ogni filtro: le vede soltanto il controllo finale.
			'pubblicazione, dato scritto a mano'   => array(
				'pubblicazione',
				'save_post',
				static function ( int $atto_id ) {
					global $wpdb;

					// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- prova: una riga scritta a mano, non una ricerca.
					$wpdb->insert(
						$wpdb->postmeta,
						array(
							'post_id'    => $atto_id,
							'meta_key'   => 'dato_scritto_a_mano',
							'meta_value' => 'x',
						)
					);
					// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				},
				'albo_atto_cambiato_nel_passaggio',
			),
			'rimando, voce scritta a mano'         => array(
				'rimando',
				'save_post',
				function ( int $atto_id ) {
					global $wpdb;

					$wpdb->insert(
						$wpdb->term_relationships,
						array(
							'object_id'        => $atto_id,
							'term_taxonomy_id' => (int) get_term( $this->voce( TASSONOMIA_ORGANO, 'Consiglio' ) )->term_taxonomy_id,
						)
					);
				},
				'albo_atto_cambiato_nel_passaggio',
			),
		);

		foreach ( $casi as $caso => $voce_del_caso ) {
			list( $passaggio, $momento, $scrittura ) = $voce_del_caso;

			$atteso = isset( $voce_del_caso[3] ) ? $voce_del_caso[3] : 'albo_scrittura_rifiutata_nel_passaggio';
			$id     = $this->atto_in_verifica();
			$altro  = $this->atto_in_verifica();
			$prima  = $this->fotografia( $id );
			$fatta  = false;

			if ( 'altro' === $scrittura ) {
				$scrittura = static function () use ( $altro ) {
					wp_publish_post( $altro );
				};
			}

			$aggancio = static function ( ...$argomenti ) use ( $id, $scrittura, $momento, &$fatta ) {
				$atto_id = 'fine' === $momento ? (int) $argomenti[1] : (int) $argomenti[0];
				$chiave  = 'fine' === $momento ? (string) $argomenti[2] : '';

				if ( $id !== $atto_id || $fatta || ( 'fine' === $momento && conformita_core_chiave_fine_pubblicazione() !== $chiave ) ) {
					return;
				}

				$fatta = true;
				$scrittura( $atto_id );
			};

			$ganci = 'fine' === $momento ? array( 'added_post_meta', 'updated_post_meta' ) : array( 'save_post' );

			foreach ( $ganci as $gancio ) {
				add_action( $gancio, $aggancio, 10, 4 );
			}

			try {
				$esito = 'rimando' === $passaggio
					? $this->come(
						$this->pubblicatore,
						static function () use ( $id ) {
							return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
						}
					)
					: $this->pubblica_da_codice( $id, $this->pubblicatore );
			} finally {
				foreach ( $ganci as $gancio ) {
					remove_action( $gancio, $aggancio, 10 );
				}
			}

			$this->assertTrue( $fatta, $caso . ': precondizione, la scrittura annidata e\' partita.' );
			$this->assertSame( array( $atteso ), $esito->get_error_codes(), $caso . ': il passaggio e\' annullato.' );

			// Prima di qualunque fotografia, che svuota la memoria da se': il conteggio di ogni voce e' quello della banca dati.
			global $wpdb;

			$conteggi = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, count FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", TASSONOMIA_ORGANO ) );

			$this->assertNotEmpty( $conteggi, $caso . ': precondizione, le voci dell\'organo ci sono.' );

			foreach ( $conteggi as $riga ) {
				$this->assertSame(
					(int) $riga->count,
					(int) get_term( (int) $riga->term_id )->count,
					$caso . ': la memoria non ricorda un conteggio annullato, voce ' . $riga->term_id . '.'
				);
			}

			$dopo = $this->fotografia( $id );

			// Il numero preso resta dell'atto: e' preso prima della transazione, apposta.
			unset( $prima['numero'], $dopo['numero'] );

			$this->assertSame( $prima, $dopo, $caso . ': l\'atto e\' in verifica e identico, registro compreso.' );

			clean_post_cache( $altro );
			$this->assertSame( 'pending', get_post_status( $altro ), $caso . ': nessun altro atto e\' cambiato.' );
		}
	}

	/**
	 * A-114: ALBO-22, pubblicazione e rimando conservano oggetto e testo carattere per carattere.
	 *
	 * Barre rovesciate, apici e virgolette sono i caratteri che WordPress
	 * toglie o aggiunge fra una lettura e una scrittura.
	 */
	public function test_a114_oggetto_e_testo_carattere_per_carattere(): void {
		global $wpdb;

		$oggetto = 'Avviso dell\'ufficio \\ sezione "B" \\\'citata\\\'';
		$testo   = "Percorso C:\\atti\\2041\\n e sequenza \\\\ doppia, apice ' e \\' protetto.";
		$sunto   = 'Sunto con \\ barra';

		foreach ( array( 'pubblicazione', 'rimando' ) as $passaggio ) {
			$id = $this->atto_in_verifica(
				'normativo',
				wp_slash(
					array(
						'post_title'   => $oggetto,
						'post_content' => $testo,
						'post_excerpt' => $sunto,
					)
				)
			);

			$campi = static function () use ( $wpdb, $id ): array {
				return $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
			};

			$this->assertSame(
				array(
					'post_title'   => $oggetto,
					'post_content' => $testo,
					'post_excerpt' => $sunto,
				),
				$campi(),
				'Precondizione: memorizzati come scritti.'
			);

			$esito = 'pubblicazione' === $passaggio
				? $this->pubblica_da_codice( $id, $this->pubblicatore )
				: $this->come(
					$this->pubblicatore,
					static function () use ( $id ) {
						return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
					}
				);

			$this->assertNotWPError( $esito, $passaggio . ': riuscito.' );
			$this->assertSame(
				array(
					'post_title'   => $oggetto,
					'post_content' => $testo,
					'post_excerpt' => $sunto,
				),
				$campi(),
				$passaggio . ': nessun carattere perso o aggiunto.'
			);
		}
	}

	/**
	 * A-115: numero e pubblicazione dentro la transazione di chi chiama vanno insieme.
	 *
	 * Quando la connessione e' gia' dentro una transazione aperta da altri, il
	 * numero si prende in quella. Se chi l'ha aperta la annulla, spariscono
	 * insieme la pubblicazione e il numero: nessun atto resta pubblicato con
	 * quel numero, e il numero va all'atto pubblicato dopo. Scelta dichiarata
	 * nella scheda di lavorazione, da confermare.
	 */
	public function test_a115_transazione_di_chi_chiama(): void {
		global $wpdb;

		$this->assertSame( '0', (string) $wpdb->get_var( 'SELECT @@autocommit' ), 'Precondizione: le prove girano con una transazione aperta.' );

		$id    = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );
		$anno  = (int) Repertorio::anno_di( \AlboPretorioPa\Passaggi::ora() );
		$primo = Repertorio::prossimo( $anno );

		$wpdb->query( 'SAVEPOINT prova_chiamante' );

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, 'Precondizione: pubblicato dentro la transazione di chi chiama.' );
		$this->assertSame( $primo, $esito['numero'] );

		$wpdb->query( 'ROLLBACK TO SAVEPOINT prova_chiamante' );

		$this->assertSame( $prima, $this->fotografia( $id ), 'Annullata la transazione di chi chiama, l\'atto e\' in verifica e senza numero.' );
		$this->assertSame( $primo, Repertorio::prossimo( $anno ), 'Il numero torna libero insieme alla pubblicazione.' );

		$ancora = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $ancora );
		$this->assertSame( $primo, $ancora['numero'], 'Il numero va alla pubblicazione che resta.' );
	}

	/**
	 * Esegue una funzione con un guasto sulle istruzioni che contengono tutti i pezzi, dalla volta indicata in poi.
	 *
	 * @param array<int, string> $pezzi    Pezzi dell'istruzione.
	 * @param callable           $funzione Funzione.
	 * @param int                $dal      Prima volta guastata, contando da uno.
	 * @return array{0: mixed, 1: int} Esito della funzione e istruzioni riconosciute.
	 */
	private function con_guasto( array $pezzi, callable $funzione, int $dal = 1 ): array {
		global $wpdb;

		$viste  = 0;
		$guasta = static function ( $istruzione ) use ( $pezzi, $dal, &$viste ) {
			foreach ( $pezzi as $pezzo ) {
				if ( false === strpos( (string) $istruzione, $pezzo ) ) {
					return $istruzione;
				}
			}

			++$viste;

			return $viste >= $dal ? 'SELECT guasto_forzato FROM tabella_che_non_esiste' : $istruzione;
		};

		add_filter( 'query', $guasta );
		$wpdb->suppress_errors( true );

		try {
			$esito = $funzione();
		} finally {
			remove_filter( 'query', $guasta );
			$wpdb->suppress_errors( false );
		}

		return array( $esito, $viste );
	}

	/**
	 * A-124: ALBO-22, una lettura che non risponde non vale come permesso.
	 *
	 * Ogni lettura con cui la barriera decide se una scrittura e' ammessa
	 * fallisce, una per volta: la richiesta si ferma, l'atto resta identico e
	 * WordPress non annuncia nessun cambio di stato. Le verifiche dopo una
	 * scrittura su una bozza fermano la richiesta se non si leggono. Dopo un
	 * passaggio riuscito la memoria si svuota per intero se non si leggono le
	 * voci da dimenticare. Dentro un passaggio fallisce ogni lettura dell'atto
	 * da confrontare: il passaggio rifiuta e annulla.
	 */
	public function test_a124_lettura_che_non_risponde(): void {
		global $wpdb;

		$in_verifica = $this->atto_in_verifica();
		$pubblicato  = $this->atto_pubblicato();
		$annunci     = array();
		$ascolta     = static function ( $nuovo, $vecchio, $atto ) use ( &$annunci ) {
			$annunci[] = (int) $atto->ID;
		};

		add_action( 'transition_post_status', $ascolta, 10, 3 );

		try {
			$casi = array(
				'wp_publish_post, riga dell\'atto non letta' => array(
					$in_verifica,
					array( 'SELECT post_type, post_status, post_name FROM' ),
					static function () use ( $in_verifica ) {
						get_post( $in_verifica );
						wp_publish_post( $in_verifica );
					},
				),
				'dato di un atto pubblicato, stato non letto' => array(
					$pubblicato,
					array( 'SELECT post_type, post_status FROM' ),
					static function () use ( $pubblicato ) {
						update_post_meta( $pubblicato, META_DATA_ADOZIONE, '2041-01-01' );
					},
				),
				'voce di un atto pubblicato, stato non letto' => array(
					$pubblicato,
					array( 'SELECT post_type, post_status FROM' ),
					function () use ( $pubblicato ) {
						wp_set_object_terms( $pubblicato, array( $this->voci['scelto'] ), TASSONOMIA_TIPO_ATTO );
					},
				),
				'dato per numero di riga, riga non letta' => array(
					$in_verifica,
					array( 'SELECT post_id, meta_key FROM' ),
					function () use ( $in_verifica ) {
						update_metadata_by_mid( 'post', $this->riga_di( $in_verifica, META_DATA_ADOZIONE ), '2041-01-01' );
					},
				),
				'dato tolto a tutti, atti che lo portano non letti' => array(
					$pubblicato,
					array( 'SELECT m.post_id FROM' ),
					static function () {
						delete_post_meta_by_key( META_DATA_ADOZIONE );
					},
				),
			);

			foreach ( $casi as $caso => list( $id, $pezzi, $scrittura ) ) {
				$prima   = $this->fotografia( $id );
				$annunci = array();

				list( $messaggio, $viste ) = $this->con_guasto(
					$pezzi,
					function () use ( $scrittura, $caso ) {
						return $this->richiesta_fermata( $scrittura, $caso );
					}
				);

				$this->assertGreaterThan( 0, $viste, $caso . ': precondizione, la lettura e\' stata tentata.' );
				$this->assertStringContainsString( 'non ha risposto', $messaggio, $caso . ': il rifiuto dice perche\'.' );
				$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': l\'atto e\' identico.' );
				$this->assertSame( array(), $annunci, $caso . ': nessun cambio di stato annunciato.' );
			}
		} finally {
			remove_action( 'transition_post_status', $ascolta, 10 );
		}

		/*
		 * Le verifiche dopo la scrittura, su una bozza: la scrittura c'e'
		 * stata, ma se la lettura che lo conferma non risponde non si sa, e la
		 * richiesta si ferma invece di annunciarla.
		 */
		$bozza = $this->atto_completo();
		$altra = $this->voce( TASSONOMIA_ORGANO, 'Consiglio' );
		$dopo  = array(
			'voce aggiunta a una bozza, conferma non letta' => array(
				array( 'SELECT COUNT(*) AS n FROM' ),
				static function () use ( $bozza, $altra ) {
					wp_set_object_terms( $bozza, array( $altra ), TASSONOMIA_ORGANO, true );
				},
			),
			'voce tolta a una bozza, conferma non letta' => array(
				array( 'SELECT COUNT(*) AS n FROM' ),
				static function () use ( $bozza, $altra ) {
					wp_remove_object_terms( $bozza, array( $altra ), TASSONOMIA_ORGANO );
				},
			),
			'dato tolto a una bozza, conferma non letta' => array(
				array( 'SELECT post_id FROM ' . $wpdb->postmeta . ' WHERE meta_id IN' ),
				function () use ( $bozza ) {
					delete_metadata_by_mid( 'post', $this->riga_di( $bozza, META_DATA_ADOZIONE ) );
				},
			),
		);

		foreach ( $dopo as $caso => list( $pezzi, $scrittura ) ) {
			list( $messaggio, $viste ) = $this->con_guasto(
				$pezzi,
				function () use ( $scrittura, $caso ) {
					return $this->richiesta_fermata( $scrittura, $caso );
				}
			);

			$this->assertSame( 1, $viste, $caso . ': precondizione, la conferma e\' stata tentata una volta.' );
			$this->assertStringContainsString( 'non ha risposto', $messaggio, $caso . ': il rifiuto dice perche\'.' );
		}

		/*
		 * Dopo un passaggio riuscito, con lo svuotamento sospeso come fa un
		 * importatore: se la lettura delle voci da dimenticare non risponde, si
		 * dimentica tutto, e il conteggio della voce e' quello della banca dati.
		 */
		$id     = $this->atto_in_verifica();
		$voci   = 'SELECT term_taxonomy_id FROM `' . $wpdb->term_relationships . '` WHERE object_id = ' . $id;
		$viste  = 0;
		$guasta = static function ( $istruzione ) use ( $voci, &$viste ) {
			if ( $voci !== $istruzione ) {
				return $istruzione;
			}

			++$viste;

			return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
		};

		get_term( $this->voci['organo'] );
		add_filter( 'query', $guasta );
		$wpdb->suppress_errors( true );
		wp_suspend_cache_invalidation( true );

		try {
			$esito      = $this->pubblica_da_codice( $id, $this->pubblicatore );
			$in_memoria = (int) get_term( $this->voci['organo'] )->count;
		} finally {
			wp_suspend_cache_invalidation( false );
			remove_filter( 'query', $guasta );
			$wpdb->suppress_errors( false );
		}

		$organo = get_term( $this->voci['organo'] );

		$this->assertIsArray( $esito, 'Memoria: il passaggio riesce.' );
		$this->assertGreaterThan( 0, $viste, 'Memoria: precondizione, la lettura delle voci e\' stata tentata.' );
		$this->assertSame( (int) $wpdb->get_var( $wpdb->prepare( "SELECT count FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $organo->term_taxonomy_id ) ), $in_memoria, 'Memoria: il conteggio della voce e\' quello della banca dati.' );

		foreach ( array( 'pubblicazione', 'rimando' ) as $passaggio ) {
			for ( $volta = 1; $volta <= 3; $volta++ ) {
				$id    = $this->atto_in_verifica();
				$prima = $this->fotografia( $id, false );
				$caso  = $passaggio . ', lettura dell\'atto ' . $volta . ' non riuscita';

				list( $esito, $viste ) = $this->con_guasto(
					array( 'SELECT term_taxonomy_id FROM `' . $wpdb->term_relationships . '` WHERE object_id = ' . $id . ' ORDER BY' ),
					function () use ( $passaggio, $id ) {
						return 'pubblicazione' === $passaggio
							? $this->pubblica_da_codice( $id, $this->pubblicatore )
							: $this->come(
								$this->pubblicatore,
								static function () use ( $id ) {
									return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
								}
							);
					},
					$volta
				);

				$this->assertSame( $volta, $viste, $caso . ': precondizione, la lettura guastata e\' l\'ultima tentata.' );
				$this->assertWPError( $esito, $caso . ': il passaggio e\' rifiutato.' );
				$this->assertSame( array( 'albo_atto_non_letto' ), $esito->get_error_codes(), $caso . ': perche\' l\'atto non si e\' letto.' );
				$this->assertSame( $prima, $this->fotografia( $id, false ), $caso . ': l\'atto e\' identico.' );
				$this->assertSame( 'pending', get_post_status( $id ), $caso . ': l\'atto resta in verifica, anche in memoria.' );
			}
		}
	}

	/**
	 * A-125, nella pubblicazione: il numero preso che non si legge ferma il passaggio.
	 */
	public function test_a125_pubblicazione_con_numero_non_letto(): void {
		$id    = $this->atto_in_verifica();
		$prima = $this->fotografia( $id );

		list( $esito, $viste ) = $this->con_guasto(
			array( 'SELECT LAST_INSERT_ID()' ),
			function () use ( $id ) {
				return $this->pubblica_da_codice( $id, $this->pubblicatore );
			}
		);

		$this->assertSame( 1, $viste, 'Precondizione: il numero si legge una volta.' );
		$this->assertWPError( $esito );
		$this->assertSame( array( 'albo_repertorio_non_letto' ), $esito->get_error_codes() );
		$this->assertSame( $prima, $this->fotografia( $id ), 'L\'atto e\' identico, senza numero.' );
		$this->assertSame( 'pending', get_post_status( $id ) );
	}

	/**
	 * A-126: ALBO-22, le scritture di WordPress su molti contenuti insieme saltano gli atti fermi.
	 *
	 * La cancellazione di un utente con l'attribuzione dei suoi contenuti a un
	 * altro: gli atti in verifica e pubblicati restano identici, autore
	 * compreso, la bozza e i contenuti ordinari passano al nuovo autore.
	 * Un'istruzione su molti contenuti che scrive lo stato non tocca nessun
	 * atto, nemmeno in bozza; una che fa diventare atti altri contenuti non
	 * scrive niente.
	 */
	public function test_a126_scritture_su_molti_contenuti(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$autore   = $this->utente_con( Permessi::CHIAVI_REDAZIONE );
		$erede    = self::factory()->user->create( array( 'role' => 'editor' ) );
		$bozza    = $this->atto_completo( 'normativo', array( 'post_author' => $autore ) );
		$verifica = $this->atto_completo( 'normativo', array( 'post_author' => $autore ) );
		$id       = $this->atto_completo( 'normativo', array( 'post_author' => $autore ) );

		foreach ( array( $verifica, $id ) as $da_inviare ) {
			$this->come(
				$autore,
				static function () use ( $da_inviare ) {
					return wp_update_post(
						array(
							'ID'          => $da_inviare,
							'post_status' => 'pending',
						)
					);
				}
			);

			clean_post_cache( $da_inviare );

			$this->assertSame( 'pending', get_post_status( $da_inviare ), 'Precondizione: in verifica.' );
		}

		$this->assertIsArray( $this->pubblica_da_codice( $id, $this->pubblicatore ), 'Precondizione: pubblicato.' );

		$ordinario = self::factory()->post->create( array( 'post_author' => $autore ) );
		$fermi     = array(
			'in verifica' => $verifica,
			'pubblicato'  => $id,
		);
		$prima     = array_map( array( $this, 'fotografia' ), $fermi );

		$this->assertTrue( wp_delete_user( $autore, $erede ), 'L\'utente si cancella.' );
		$this->assertFalse( get_userdata( $autore ), 'Precondizione: l\'utente non c\'e\' piu\'.' );

		foreach ( $fermi as $caso => $fermo ) {
			$this->assertSame( $prima[ $caso ], $this->fotografia( $fermo ), $caso . ': l\'atto e\' identico, autore compreso.' );
			$this->assertSame( (string) $autore, $wpdb->get_var( $wpdb->prepare( "SELECT post_author FROM {$wpdb->posts} WHERE ID = %d", $fermo ) ), $caso . ': l\'autore resta chi l\'ha scritto.' );
		}

		$this->assertSame( (string) $erede, get_post( $bozza )->post_author, 'La bozza passa al nuovo autore.' );
		$this->assertSame( (string) $erede, get_post( $ordinario )->post_author, 'Il contenuto ordinario passa al nuovo autore.' );

		$prima = array_map( array( $this, 'fotografia' ), $fermi + array( 'bozza' => $bozza ) );

		$this->assertSame( 0, $wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'post_type' => TIPO ) ), 'Lo stato scritto a molti contenuti non tocca nessun atto.' );

		$pagina = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame(
			0,
			$wpdb->update(
				$wpdb->posts,
				array( 'post_type' => TIPO ),
				array(
					'ID'        => $pagina,
					'post_type' => 'page',
				)
			),
			'Nessun contenuto diventa un atto.'
		);
		$this->assertSame( 'page', $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $pagina ) ) );

		foreach ( $prima as $caso => $foto ) {
			$this->assertSame( $foto, $this->fotografia( $fermi[ $caso ] ?? $bozza ), $caso . ': identico dopo le scritture su molti contenuti.' );
		}

		$this->assertSame( 1, $wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'post_type' => 'page' ) ), 'Controllo positivo: lo stato dei contenuti ordinari si scrive.' );

		// Senza condizione da completare, nella forma di WordPress: la condizione diventa l'unica.
		$prima = array_map( array( $this, 'fotografia' ), $fermi );

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `post_author` = %d', $wpdb->posts, $erede ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `meta_value` = %s', $wpdb->postmeta, '2041-01-01' ) );

		foreach ( $fermi as $caso => $fermo ) {
			$this->assertSame( $prima[ $caso ], $this->fotografia( $fermo ), $caso . ': identico dopo le scritture senza condizione.' );
		}

		$this->assertSame( '2041-01-01', get_post_meta( $bozza, META_DATA_ADOZIONE, true ), 'Controllo positivo: i dati della bozza si scrivono.' );
	}
}
