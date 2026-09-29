<?php
/**
 * La barriera sotto WordPress e l'atto fermo per tutto il passaggio: righe A-111..A-115.
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
		}

		// Controllo positivo: sulla bozza le stesse interfacce lavorano.
		$bozza = $this->atto_completo();
		$riga  = $this->riga_di( $bozza, META_DATA_ADOZIONE );

		$this->assertNotFalse( add_post_meta( $bozza, 'dato_della_bozza', 'x' ) );
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
		$id         = $this->atto_in_verifica();
		$normalizza = static function ( $post_id ) use ( $id ) {
			if ( $id === (int) $post_id ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => 'Oggetto normalizzato',
					)
				);
				update_post_meta( $id, META_DATA_ADOZIONE, '1999-01-01' );
			}
		};

		add_action( 'save_post', $normalizza );

		try {
			$esito = $this->come(
				$this->pubblicatore,
				static function () use ( $id ) {
					return albo_pretorio_rimanda_in_bozza( $id, 'Da completare.' );
				}
			);
		} finally {
			remove_action( 'save_post', $normalizza );
		}

		$this->assertIsInt( $esito, 'Le scritture annidate rifiutate non fermano il rimando.' );
		clean_post_cache( $id );
		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 'Atto di prova', get_the_title( $id ), 'L\'oggetto e\' quello di prima.' );
		$this->assertSame( '2041-09-10', get_post_meta( $id, META_DATA_ADOZIONE, true ), 'La data di adozione e\' quella di prima.' );

		// Le scritture che non si fermano prima di cominciare: la riga scritta dritta e le voci.
		$casi = array(
			'rimando, riga scritta dritta'   => array(
				'rimando',
				'save_post',
				static function ( int $atto_id ) {
					global $wpdb;

					$wpdb->update( $wpdb->posts, array( 'post_title' => 'Oggetto cambiato' ), array( 'ID' => $atto_id ) );
				},
			),
			'rimando, voce tolta'            => array(
				'rimando',
				'save_post',
				static function ( int $atto_id ) {
					wp_set_object_terms( $atto_id, array(), TASSONOMIA_ORGANO );
				},
			),
			'pubblicazione, voce tolta'      => array(
				'pubblicazione',
				'save_post',
				static function ( int $atto_id ) {
					wp_set_object_terms( $atto_id, array(), TASSONOMIA_ORGANO );
				},
			),
			'pubblicazione, stato alla fine' => array(
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
		);

		foreach ( $casi as $caso => list( $passaggio, $momento, $scrittura ) ) {
			$id    = $this->atto_in_verifica();
			$altro = $this->atto_in_verifica();
			$prima = $this->fotografia( $id );
			$fatta = false;

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
			$this->assertSame( array( 'albo_scrittura_rifiutata_nel_passaggio' ), $esito->get_error_codes(), $caso . ': il passaggio e\' annullato.' );

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
}
