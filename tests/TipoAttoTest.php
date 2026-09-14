<?php
/**
 * Prove del tipo di contenuto atto e dei due elenchi di voci: righe A-11..A-15.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\TipoAtto;
use WP_UnitTestCase;

/**
 * Registrazione del tipo, degli elenchi di voci e chiusura verso il pubblico.
 */
class TipoAttoTest extends WP_UnitTestCase {

	/**
	 * Riporta i registri in memoria allo stato iniziale.
	 *
	 * Il registro delle sezioni, quello dei tipi e lo stato interno dell'albo
	 * vivono per tutta l'esecuzione: una prova che ne lascia dietro uno rende
	 * verde o rossa la successiva per un motivo che non c'entra.
	 */
	public function set_up(): void {
		parent::set_up();

		\Conformita_Core_Tipi::azzera();
		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		TipoAtto::azzera();
	}

	/**
	 * Rimonta il tipo dopo ogni prova, per non lasciarlo alla successiva.
	 */
	public function tear_down(): void {
		\Conformita_Core_Tipi::azzera();
		TipoAtto::azzera();

		parent::tear_down();
	}

	/**
	 * A-11: senza sezione registrata il tipo non nasce.
	 *
	 * Un tipo registrato fuori da una sezione sarebbe contenuto pubblicabile che
	 * nessuna regola governa. La postcondizione si rilegge da WordPress, non si
	 * assume dall'esito della chiamata.
	 */
	public function test_a11_senza_sezione_il_tipo_non_nasce(): void {
		$this->assertNotContains(
			\AlboPretorioPa\SEZIONE,
			conformita_core_sezioni_registrate(),
			'Precondizione: la sezione non deve risultare registrata.'
		);

		$esito = TipoAtto::registra();

		$this->assertWPError( $esito, 'Senza sezione la registrazione deve essere rifiutata.' );
		$this->assertSame(
			'conformita_core_tipo_senza_sezione',
			$esito->get_error_code(),
			'L\'errore deve essere quello dedicato al tipo senza sezione.'
		);

		$this->assertFalse(
			post_type_exists( \AlboPretorioPa\TIPO ),
			'Il tipo non deve risultare fra quelli di WordPress.'
		);
	}

	/**
	 * A-12: controllo positivo, il tipo risulta registrato presso il nucleo comune.
	 *
	 * Senza questa prova ogni prova negativa sarebbe soddisfacibile da un
	 * componente che non registra mai niente.
	 */
	public function test_a12_registrazione_riuscita(): void {
		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );
		$this->assertTrue( TipoAtto::registra(), 'La registrazione deve riuscire.' );

		$this->assertTrue(
			conformita_core_tipo_registrato( \AlboPretorioPa\TIPO ),
			'Il tipo deve risultare registrato presso il nucleo comune.'
		);

		$this->assertSame(
			\AlboPretorioPa\SEZIONE,
			conformita_core_sezione_del_tipo( \AlboPretorioPa\TIPO ),
			'La sezione del tipo deve essere quella dell\'albo, riletta dal nucleo comune.'
		);

		$this->assertTrue(
			post_type_exists( \AlboPretorioPa\TIPO ),
			'Il tipo deve esistere anche per WordPress.'
		);
	}

	/**
	 * A-13: il tipo e' visibile in amministrazione e non interrogabile dal pubblico.
	 *
	 * I valori si dichiarano uno per uno e non si lasciano derivare: WordPress
	 * ne ricava una parte da `public`, e una derivazione che cambiasse in una
	 * versione futura non deve poter aprire il tipo da sola.
	 */
	public function test_a13_argomenti_del_tipo(): void {
		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		$oggetto = get_post_type_object( \AlboPretorioPa\TIPO );

		$this->assertNotNull( $oggetto, 'Precondizione: il tipo deve esistere.' );

		$this->assertFalse( $oggetto->public, 'Il tipo non e\' pubblico.' );
		$this->assertFalse( $oggetto->publicly_queryable, 'Il tipo non e\' interrogabile dal pubblico.' );
		$this->assertFalse( $oggetto->query_var, 'Nessuna variabile di interrogazione.' );
		$this->assertFalse( $oggetto->rewrite, 'Nessun indirizzo pubblico da riscrivere.' );
		$this->assertFalse( $oggetto->has_archive, 'Nessun elenco pubblico.' );
		$this->assertTrue( $oggetto->exclude_from_search, 'Fuori dalla ricerca interna.' );
		$this->assertFalse( $oggetto->show_in_rest, 'Esposizione per programmi dichiarata spenta.' );

		$this->assertTrue( $oggetto->show_ui, 'Visibile in amministrazione.' );
		$this->assertTrue( $oggetto->show_in_menu, 'Con voce di menu propria.' );

		$this->assertArrayNotHasKey(
			'editor',
			(array) get_all_post_type_supports( \AlboPretorioPa\TIPO ),
			'Un atto non ha un corpo di testo: ha un documento.'
		);
		$this->assertFalse(
			post_type_supports( \AlboPretorioPa\TIPO, 'editor' ),
			'Senza il supporto all\'editor WordPress usa la schermata classica.'
		);
		$this->assertFalse(
			use_block_editor_for_post_type( \AlboPretorioPa\TIPO ),
			'La schermata e\' quella classica: e\' la conseguenza del supporto assente, non un filtro nostro.'
		);
	}

	/**
	 * A-14: i due elenchi di voci sono piatti e chiusi verso il pubblico.
	 *
	 * Piatti perche' il dominio non ha rapporti fra voce e sottovoce: ne' i tipi
	 * di atto ne' gli organi si contengono a vicenda.
	 *
	 * @dataProvider elenchi_di_voci
	 *
	 * @param string $tassonomia Identificativo dell'elenco di voci.
	 */
	public function test_a14_elenchi_di_voci( string $tassonomia ): void {
		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		$oggetto = get_taxonomy( $tassonomia );

		$this->assertNotFalse( $oggetto, 'L\'elenco di voci ' . $tassonomia . ' deve essere registrato.' );

		$this->assertFalse( $oggetto->hierarchical, 'L\'elenco e\' piatto.' );
		$this->assertFalse( $oggetto->public, 'L\'elenco non e\' pubblico.' );
		$this->assertFalse( $oggetto->publicly_queryable, 'L\'elenco non e\' interrogabile dal pubblico.' );
		$this->assertFalse( $oggetto->show_in_rest, 'Esposizione per programmi dichiarata spenta.' );
		$this->assertFalse( $oggetto->rewrite, 'Nessun indirizzo pubblico.' );
		$this->assertFalse( $oggetto->query_var, 'Nessuna variabile di interrogazione.' );
		$this->assertTrue( $oggetto->show_ui, 'Visibile in amministrazione.' );
		$this->assertFalse(
			$oggetto->meta_box_cb,
			'Nessun riquadro di scelta in questa fase: un campo a testo libero su un elenco controllato lo snaturerebbe.'
		);

		$this->assertContains(
			\AlboPretorioPa\TIPO,
			(array) $oggetto->object_type,
			'L\'elenco deve essere agganciato al tipo atto.'
		);
	}

	/**
	 * I due elenchi di voci dell'atto.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function elenchi_di_voci(): array {
		return array(
			'tipo di atto' => array( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO ),
			'organo'       => array( \AlboPretorioPa\TASSONOMIA_ORGANO ),
		);
	}

	/**
	 * A-15: nessuna delle tre rotte esiste.
	 *
	 * La precondizione dimostra che il server espone almeno una rotta propria di
	 * WordPress: senza, l'assenza delle nostre sarebbe vera per niente.
	 */
	public function test_a15_le_tre_rotte_non_esistono(): void {
		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );

		$rotte = array_keys( rest_get_server()->get_routes() );

		$this->assertContains(
			'/wp/v2/posts',
			$rotte,
			'Precondizione: il server deve esporre almeno una rotta propria di WordPress.'
		);

		foreach ( array( \AlboPretorioPa\TIPO, \AlboPretorioPa\TASSONOMIA_TIPO_ATTO, \AlboPretorioPa\TASSONOMIA_ORGANO ) as $nome ) {
			$trovate = array_filter(
				$rotte,
				static function ( $rotta ) use ( $nome ) {
					return false !== strpos( $rotta, $nome );
				}
			);

			$this->assertSame(
				array(),
				array_values( $trovate ),
				'Nessuna rotta deve esistere per ' . $nome . '.'
			);
		}
	}
}
