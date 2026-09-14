<?php
/**
 * Prova del meccanismo di attivazione e aggiornamento: riga A-21.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\Installazione;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\TipoAtto;
use WP_UnitTestCase;

/**
 * I permessi nuovi raggiungono anche i siti gia' installati.
 */
class AggiornamentoTest extends WP_UnitTestCase {

	/**
	 * Registri e ruoli riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Tipi::azzera();
		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		TipoAtto::azzera();

		$GLOBALS['wp_roles'] = new \WP_Roles();

		Permessi::azzera();
		delete_option( \AlboPretorioPa\OPZIONE_VERSIONE );

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );
	}

	/**
	 * Rimonta tipo e ruoli dopo ogni prova.
	 */
	public function tear_down(): void {
		Permessi::azzera();
		delete_option( \AlboPretorioPa\OPZIONE_VERSIONE );
		\Conformita_Core_Tipi::azzera();
		TipoAtto::azzera();

		$GLOBALS['wp_roles'] = new \WP_Roles();

		parent::tear_down();
	}

	/**
	 * A-21: un permesso nuovo raggiunge i ruoli che avevano gia' gli altri.
	 *
	 * Il sito della prova e' un sito gia' installato: ha una versione precedente
	 * memorizzata, un insieme di permessi piu' povero, e un ruolo proprio a cui
	 * l'amministrazione aveva dato i permessi dell'albo. **Nessuna nuova
	 * attivazione**: e' il caso che un meccanismo agganciato alla sola
	 * attivazione lascerebbe scoperto.
	 */
	public function test_a21_aggiornamento_senza_nuova_attivazione(): void {
		$mappa = conformita_core_capacita_tipo( \AlboPretorioPa\TIPO );

		$this->assertNotWPError( $mappa, 'Precondizione: il nucleo comune deve fornire i permessi del tipo.' );

		$precedente = array_values(
			array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( 'delete_others_posts' ) )
		);

		Permessi::applica(
			array(
				'administrator'       => $precedente,
				\AlboPretorioPa\RUOLO => $precedente,
			)
		);

		get_role( 'editor' )->add_cap( $mappa['edit_posts'] );
		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, '0.0.1' );

		$this->assertFalse(
			get_role( \AlboPretorioPa\RUOLO )->has_cap( $mappa['delete_others_posts'] ),
			'Precondizione: il permesso nuovo non deve esserci prima dell\'aggiornamento.'
		);

		$this->assertTrue(
			Installazione::aggiorna_se_serve(),
			'Con la versione memorizzata diversa, l\'aggiornamento deve girare.'
		);

		$this->assertTrue(
			get_role( \AlboPretorioPa\RUOLO )->has_cap( $mappa['delete_others_posts'] ),
			'Il permesso nuovo deve arrivare al ruolo proprio.'
		);

		foreach ( Permessi::CHIAVI_REDAZIONE as $chiave ) {
			$this->assertTrue(
				get_role( 'editor' )->has_cap( $mappa[ $chiave ] ),
				'Il ruolo che aveva gia\' un permesso dell\'albo deve ricevere tutto il proprio insieme: ' . $chiave . '.'
			);
		}

		$this->assertFalse(
			get_role( 'editor' )->has_cap( $mappa['publish_posts'] ),
			'Ricevere i permessi mancanti non promuove nessuno: chi redigeva non diventa chi pubblica.'
		);

		$this->assertSame(
			\AlboPretorioPa\VERSIONE,
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE ),
			'La versione memorizzata deve risultare aggiornata.'
		);

		$this->assertFalse(
			Installazione::aggiorna_se_serve(),
			'Con la versione gia\' allineata non si rifa\' niente a ogni richiesta.'
		);
	}
}
