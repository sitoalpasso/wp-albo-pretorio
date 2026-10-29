<?php
/**
 * Avviso nel riquadro di invio di WordPress: righe A-284 e A-285.
 *
 * Il riquadro di invio di WordPress, in alto a destra nella schermata
 * dell'atto, mostra pulsanti che l'albo non usa per pubblicare. Nella bozza
 * un avviso fisso dice come si manda l'atto in verifica e che il pulsante
 * di pubblicazione di WordPress non pubblica l'atto.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\ChiusuraPubblicazione;

/**
 * L'avviso, a chi serve e dove serve.
 */
class AvvisoInvioTest extends \WP_UnitTestCase {

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
	 * Quello che il riquadro di invio stampa per un contenuto, come l'utente indicato.
	 *
	 * @param int $id     Contenuto.
	 * @param int $utente Utente.
	 * @return string
	 */
	private function invio( int $id, int $utente ): string {
		return $this->come(
			$utente,
			static function () use ( $id ) {
				clean_post_cache( $id );

				ob_start();
				do_action( 'post_submitbox_misc_actions', get_post( $id ) );

				return (string) ob_get_clean();
			}
		);
	}

	/**
	 * A-284: nella bozza l'avviso dice di salvare prima e poi mandare in verifica.
	 *
	 * Chi redige soltanto manda in verifica con il pulsante di WordPress; chi
	 * pubblica passa dal menu Stato e trova anche il richiamo che il pulsante
	 * di pubblicazione di WordPress non pubblica l'atto.
	 */
	public function test_a284_avviso_nella_bozza(): void {
		$bozza = $this->atto_completo();

		$di_chi_redige = $this->invio( $bozza, $this->redattore );

		$this->assertStringContainsString( 'salvare la bozza', $di_chi_redige, 'Prima si salva la bozza.' );
		$this->assertStringContainsString( 'Invia per la revisione', $di_chi_redige, 'Poi il pulsante che manda in verifica.' );
		$this->assertStringNotContainsString( 'menu Stato', $di_chi_redige, 'Chi redige soltanto non passa dal menu Stato.' );

		$di_chi_pubblica = $this->invio( $bozza, $this->pubblicatore );

		$this->assertStringContainsString( 'salvare la bozza', $di_chi_pubblica );
		$this->assertStringContainsString( 'menu Stato', $di_chi_pubblica, 'Chi pubblica manda in verifica dal menu Stato.' );
		$this->assertStringContainsString( 'In attesa di revisione', $di_chi_pubblica );
		$this->assertStringContainsString( 'non pubblica l&#039;atto', $di_chi_pubblica, 'Il pulsante di WordPress non pubblica.' );
		$this->assertStringContainsString( 'Pubblica l&#039;atto', $di_chi_pubblica, 'Il pulsante giusto, nel riquadro Pubblicazione.' );
	}

	/**
	 * A-285: fuori dalla bozza dell'atto l'avviso non compare.
	 *
	 * Non in un atto in verifica o pubblicato, non in un articolo qualunque,
	 * non a chi non puo' modificare l'atto.
	 */
	public function test_a285_l_avviso_solo_dove_serve(): void {
		$bozza       = $this->atto_completo();
		$in_verifica = $this->atto_in_verifica();
		$articolo    = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertSame( ChiusuraPubblicazione::IN_VERIFICA, get_post_status( $in_verifica ), 'Precondizione.' );
		$this->assertStringContainsString( 'salvare la bozza', $this->invio( $bozza, $this->redattore ), 'Precondizione: nella bozza c\'e\'.' );

		$this->assertSame( '', $this->invio( $in_verifica, $this->pubblicatore ), 'Atto in verifica.' );
		$this->assertSame( '', $this->invio( $articolo, $this->amministratore ), 'Articolo qualunque.' );
		$this->assertSame( '', $this->invio( $bozza, self::factory()->user->create( array( 'role' => 'subscriber' ) ) ), 'Chi non puo\' modificare l\'atto.' );
	}
}
