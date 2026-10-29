<?php
/**
 * Gli atti scaduti in amministrazione: righe A-290 e A-291 (ALBO-34).
 *
 * Un atto la cui pubblicazione e' finita si consulta con il permesso apposito,
 * e chi redige soltanto non lo ha. In amministrazione l'atto scaduto non si
 * apre, non si legge, non si cancella e non compare nell'elenco degli atti
 * per chi non ha ne' la consultazione ne' la pubblicazione; i suoi documenti
 * seguono l'atto. Chi pubblica conserva i passaggi sugli scaduti.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Permessi;

use const AlboPretorioPa\RUOLO_REDATTORE;
use const AlboPretorioPa\TIPO;

/**
 * Lo scaduto resta riservato anche in amministrazione.
 */
class ScadutiRiservatiTest extends \WP_UnitTestCase {

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
	 * Un atto del tipo normativo, 15 giorni, pubblicato nell'istante indicato.
	 *
	 * @param string $istante Data e ora nel fuso del sito.
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto ): int {
		$this->fissa_orologi( $istante );

		$id = $this->atto_in_verifica( 'normativo', array( 'post_title' => $oggetto ) );

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

		return $id;
	}

	/**
	 * Gli identificativi dell'elenco degli atti in amministrazione, come l'utente indicato.
	 *
	 * @param int $utente Utente.
	 * @return array<int, int>
	 */
	private function elenco( int $utente ): array {
		return $this->come(
			$utente,
			static function () {
				set_current_screen( 'edit-' . TIPO );

				try {
					wp_edit_posts_query(
						array(
							'post_type' => TIPO,
						)
					);

					$trovati = array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
				} finally {
					set_current_screen( 'front' );
				}

				sort( $trovati );

				return $trovati;
			}
		);
	}

	/**
	 * Il permesso indicato sul contenuto, come l'utente indicato.
	 *
	 * @param int    $utente   Utente.
	 * @param string $permesso Permesso.
	 * @param int    $id       Contenuto.
	 * @return bool
	 */
	private function puo( int $utente, string $permesso, int $id ): bool {
		clean_post_cache( $id );

		return user_can( $utente, $permesso, $id );
	}

	/**
	 * A-290: l'atto scaduto e i suoi documenti non si aprono per chi redige soltanto.
	 */
	public function test_a290_lo_scaduto_non_si_apre_a_chi_redige(): void {
		$scaduto = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto' );
		$attuale = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso' );
		$bozza   = $this->atto_completo();

		$this->fissa_orologi( '2041-10-10 10:00:00' );

		$this->assertTrue( conformita_core_scaduto( $scaduto ), 'Precondizione: il primo e\' scaduto.' );
		$this->assertFalse( conformita_core_scaduto( $attuale ), 'Precondizione: il secondo e\' in corso.' );

		$documento = DocumentiAtto::principale( $scaduto );

		$this->assertIsInt( $documento, 'Precondizione: lo scaduto ha il suo documento.' );

		$redattore          = self::factory()->user->create( array( 'role' => RUOLO_REDATTORE ) );
		$solo_pubblicazione = $this->utente_con( Permessi::CHIAVI_PUBBLICAZIONE );
		$solo_consultazione = $this->utente_con( array( Permessi::CHIAVE_CONSULTAZIONE ) );

		foreach ( array(
			'ruolo del redattore'           => $redattore,
			'insieme di chi redige, a mano' => $this->redattore,
		) as $chi => $utente ) {
			foreach ( array( 'edit_post', 'read_post', 'delete_post' ) as $permesso ) {
				$this->assertFalse( $this->puo( $utente, $permesso, $scaduto ), $chi . ': ' . $permesso . ' sullo scaduto.' );
			}

			$this->assertFalse( $this->puo( $utente, 'read_post', $documento ), $chi . ': il documento dello scaduto segue l\'atto.' );
			$this->assertFalse( $this->puo( $utente, 'edit_post', $documento ), $chi . ': anche in modifica.' );
		}

		$this->assertTrue( $this->puo( $this->redattore, 'edit_post', $attuale ), 'Controllo positivo: l\'atto in corso del suo autore si apre.' );
		$this->assertTrue( $this->puo( $this->redattore, 'edit_post', $bozza ), 'E la sua bozza.' );
		$this->assertTrue( $this->puo( $redattore, 'read_post', $attuale ), 'Il redattore legge l\'atto in corso.' );

		foreach ( array(
			'chi pubblica, con il ruolo dell\'albo' => $this->pubblicatore,
			'chi pubblica senza la consultazione'   => $solo_pubblicazione,
			'amministratore'                        => $this->amministratore,
		) as $chi => $utente ) {
			$this->assertTrue( $this->puo( $utente, 'edit_post', $scaduto ), $chi . ': i passaggi sugli scaduti restano suoi.' );
			$this->assertTrue( $this->puo( $utente, 'read_post', $documento ), $chi . ': il documento si legge.' );
		}

		$this->assertTrue( $this->puo( $solo_consultazione, 'read_post', $scaduto ), 'Chi consulta soltanto legge lo scaduto.' );
		$this->assertFalse( $this->puo( $solo_consultazione, 'edit_post', $scaduto ), 'E non lo modifica.' );
	}

	/**
	 * A-291: l'elenco degli atti non mostra gli scaduti a chi redige soltanto.
	 *
	 * Ci sono anche tre atti con la data di fine guasta, scritta a parole, in
	 * un giorno che non esiste o due volte, che valgono come scaduti, e la
	 * bozza, che una data di fine non l'ha e resta.
	 */
	public function test_a291_l_elenco_non_mostra_gli_scaduti_a_chi_redige(): void {
		$scaduto     = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto' );
		$rotto       = $this->pubblicato_il( '2041-10-01 09:20:00', 'Atto con la fine scritta a parole' );
		$impossibile = $this->pubblicato_il( '2041-10-01 09:21:00', 'Atto con una fine che non esiste' );
		$doppio      = $this->pubblicato_il( '2041-10-01 09:22:00', 'Atto con due fini' );
		$attuale     = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto in corso' );
		$ultimo      = $this->pubblicato_il( '2041-09-25 09:30:00', 'Atto all\'ultimo giorno' );
		$bozza       = $this->atto_completo();

		$this->scavalca_barriera(
			static function () use ( $rotto, $impossibile, $doppio ) {
				update_post_meta( $rotto, conformita_core_chiave_fine_pubblicazione(), 'domani' );
				update_post_meta( $impossibile, conformita_core_chiave_fine_pubblicazione(), '2042-02-31' );
				add_post_meta( $doppio, conformita_core_chiave_fine_pubblicazione(), '2042-12-31' );
			}
		);

		foreach ( array( $rotto, $impossibile, $doppio ) as $guasto ) {
			$this->assertTrue( conformita_core_scaduto( $guasto ), 'Precondizione: la fine guasta vale come scaduta.' );
		}

		$this->fissa_orologi( '2041-10-10 10:00:00' );

		$this->assertSame( '2041-10-10', get_post_meta( $ultimo, conformita_core_chiave_fine_pubblicazione(), true ), 'Precondizione: oggi e\' l\'ultimo giorno del quarto.' );

		$redattore = self::factory()->user->create( array( 'role' => RUOLO_REDATTORE ) );
		$tutti     = array( $scaduto, $rotto, $impossibile, $doppio, $attuale, $ultimo, $bozza );

		sort( $tutti );

		$visibili = array( $attuale, $ultimo, $bozza );

		sort( $visibili );

		$this->assertSame( $visibili, $this->elenco( $this->redattore ), 'Chi redige soltanto: niente scaduti, niente fine guasta.' );
		$this->assertSame( $visibili, $this->elenco( $redattore ), 'Il ruolo del redattore, lo stesso.' );
		$this->assertSame( $tutti, $this->elenco( $this->pubblicatore ), 'Chi pubblica vede tutto.' );
		$this->assertSame( $tutti, $this->elenco( $this->amministratore ), 'L\'amministratore vede tutto.' );

		$articolo = self::factory()->post->create( array( 'post_date' => '2041-01-01 10:00:00' ) );

		$articoli = $this->come(
			$redattore,
			static function () {
				return new \WP_Query(
					array(
						'post_type'   => 'post',
						'post_status' => 'any',
						'fields'      => 'ids',
					)
				);
			}
		);

		$this->assertContains( $articolo, array_map( 'intval', $articoli->posts ), 'Le altre interrogazioni non cambiano.' );
	}
}
