<?php
/**
 * Prove dell'inerzia vera dopo un avvio non riuscito: righe A-28 e A-29.
 *
 * Le due righe correggono lo stesso errore visto da due lati. La presenza delle
 * funzioni del nucleo comune dice che quel componente c'e', **non** che la
 * sezione sia nostra. Il nome di un tipo di contenuto dice come si chiama,
 * **non** chi lo ha registrato. Un componente che decide su quelle basi
 * costruisce dentro la sezione di altri e riporta in bozza contenuti che non
 * sono suoi, mentre si dichiara inerte.
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
 * Quando l'avvio non arriva in fondo, non deve nascere niente.
 */
class InerziaTest extends WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Registri in memoria, ruoli e opzione riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();
	}

	/**
	 * Rimonta tutto dopo ogni prova.
	 */
	public function tear_down(): void {
		if ( post_type_exists( \AlboPretorioPa\TIPO ) ) {
			unregister_post_type( \AlboPretorioPa\TIPO );
		}

		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * Un soggetto esterno registra la sezione dell'albo prima dell'avvio.
	 *
	 * Con politiche **identiche**: e' il caso che conta, perche' l'uguaglianza
	 * dei valori non dimostra che la registrazione sia nostra.
	 */
	private function sezione_registrata_da_altri(): void {
		$esito = conformita_core_registra_sezione(
			\AlboPretorioPa\SEZIONE,
			array(
				'indicizzazione' => \AlboPretorioPa\INDICIZZAZIONE,
				'scadenza'       => \AlboPretorioPa\SCADENZA,
			)
		);

		$this->assertTrue( $esito, 'Precondizione: la sezione deve risultare registrata da altri.' );
	}

	/**
	 * A-28: avvio non riuscito, e il percorso normale non costruisce niente.
	 */
	public function test_a28_avvio_non_riuscito_non_costruisce_niente(): void {
		$this->sezione_registrata_da_altri();

		$esito = Avvio::esegui();

		$this->assertWPError( $esito, 'Precondizione: l\'avvio deve rifiutare la paternita\' della sezione.' );
		$this->assertFalse( Avvio::registrata(), 'Precondizione: la sezione non risulta registrata da noi.' );

		$this->assertNotFalse(
			has_action( 'init', array( TipoAtto::class, 'da_init' ) ),
			'Precondizione: la registrazione del tipo deve essere agganciata a init.'
		);
		$this->assertNotFalse(
			has_action( 'init', array( Installazione::class, 'da_init' ) ),
			'Precondizione: l\'installazione deve essere agganciata a init.'
		);

		TipoAtto::da_init();
		Installazione::da_init();

		$this->assertFalse( TipoAtto::registrato(), 'Il tipo non risulta registrato da questo componente.' );
		$this->assertFalse( post_type_exists( \AlboPretorioPa\TIPO ), 'Il tipo non deve esistere per WordPress.' );
		$this->assertFalse(
			conformita_core_tipo_registrato( \AlboPretorioPa\TIPO ),
			'Il tipo non deve risultare registrato presso il nucleo comune.'
		);

		$this->assertFalse(
			taxonomy_exists( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO ),
			'Il primo elenco di voci non deve nascere.'
		);
		$this->assertFalse(
			taxonomy_exists( \AlboPretorioPa\TASSONOMIA_ORGANO ),
			'Il secondo elenco di voci non deve nascere.'
		);

		$this->assertNull( get_role( \AlboPretorioPa\RUOLO ), 'Il ruolo proprio non deve nascere.' );
		$this->assertSame(
			array(),
			$this->permessi_dell_albo_sui_ruoli(),
			'Nessun ruolo deve ricevere permessi dell\'albo.'
		);
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione deve risultare memorizzata.'
		);
	}

	/**
	 * A-29: lo sbarramento non tocca contenuti di un altro componente.
	 *
	 * Un componente esterno puo' registrare un tipo con lo stesso
	 * identificativo. Se l'avvio dell'albo non e' riuscito, quel tipo non e'
	 * nostro e i suoi contenuti non si governano.
	 */
	public function test_a29_sbarramento_non_tocca_contenuti_di_altri(): void {
		$this->sezione_registrata_da_altri();

		Avvio::esegui();
		TipoAtto::da_init();

		$this->assertFalse(
			TipoAtto::registrato(),
			'Precondizione: il tipo non deve risultare registrato da questo componente.'
		);

		register_post_type(
			\AlboPretorioPa\TIPO,
			array(
				'public'  => true,
				'label'   => 'Tipo di un altro componente',
				'rewrite' => false,
			)
		);

		$this->assertTrue(
			post_type_exists( \AlboPretorioPa\TIPO ),
			'Precondizione: il tipo del componente esterno deve esistere.'
		);

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Contenuto di un altro componente',
			)
		);

		$this->assertGreaterThan( 0, $id, 'Precondizione: l\'inserimento deve essere avvenuto.' );
		$this->assertSame(
			'publish',
			get_post_status( $id ),
			'Un contenuto che non e\' dell\'albo non deve essere riportato in bozza dall\'albo.'
		);
	}
}
