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

	use AmbienteAlbo;

	/**
	 * La versione che un sito installato con l'unita' precedente ha memorizzato.
	 *
	 * Non un numero inventato: se la prova ne usasse uno finto, resterebbe verde
	 * anche il giorno in cui il componente dimentica di cambiare la propria
	 * versione, che e' proprio il caso che rende inutile il meccanismo.
	 *
	 * @var string
	 */
	const VERSIONE_PRECEDENTE = '0.1.0-alpha';

	/**
	 * Registri e ruoli riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();

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
		$this->azzera_ambiente_albo();

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
		// La versione dell'unita' precedente, non un numero inventato: e' il sito vero che riceve questa.
		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, self::VERSIONE_PRECEDENTE );

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

	/**
	 * A-37: una scrittura fallita non si dichiara riuscita.
	 *
	 * Dichiarare concluso un aggiornamento che non e' stato scritto significa
	 * non rifarlo mai piu': alla richiesta dopo la versione memorizzata sarebbe
	 * ancora quella vecchia, ma nessuno tornerebbe a guardarla.
	 */
	public function test_a37_scrittura_della_versione_fallita(): void {
		$this->assertTrue( TipoAtto::registrazione_completa(), 'Precondizione: la registrazione deve essere completa.' );

		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, self::VERSIONE_PRECEDENTE );

		$blocca = static function () {
			return self::VERSIONE_PRECEDENTE;
		};

		add_filter( 'pre_update_option_' . \AlboPretorioPa\OPZIONE_VERSIONE, $blocca );

		try {
			$esito = Installazione::aggiorna_se_serve();
		} finally {
			remove_filter( 'pre_update_option_' . \AlboPretorioPa\OPZIONE_VERSIONE, $blocca );
		}

		$this->assertFalse( $esito, 'Con la scrittura fallita il lavoro non deve dichiararsi concluso.' );
		$this->assertSame(
			self::VERSIONE_PRECEDENTE,
			Installazione::versione_installata(),
			'La versione riletta deve essere ancora quella precedente.'
		);

		$this->assertTrue(
			Installazione::aggiorna_se_serve(),
			'Alla richiesta successiva il lavoro deve essere ritentato.'
		);
		$this->assertSame(
			\AlboPretorioPa\VERSIONE,
			Installazione::versione_installata(),
			'Al secondo tentativo la versione deve risultare scritta.'
		);
	}

	/**
	 * A-39: la verifica copre anche i ruoli completati automaticamente.
	 *
	 * Sono proprio quelli di cui nessuno tiene il conto: il componente li
	 * scopre da se' perche' possiedono gia' un permesso dell'albo, e se la
	 * verifica finale guarda solo i ruoli dichiarati un permesso che non arriva
	 * la' resta invisibile fino alla richiesta dopo, quando intanto la versione
	 * risulta memorizzata e nessuno rifara' il lavoro.
	 */
	public function test_a39_postcondizione_sui_ruoli_completati_da_se(): void {
		$mappa = conformita_core_capacita_tipo( \AlboPretorioPa\TIPO );

		$this->assertNotWPError( $mappa, 'Precondizione: il nucleo comune deve fornire i permessi del tipo.' );

		get_role( 'editor' )->add_cap( $mappa['edit_posts'] );
		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, self::VERSIONE_PRECEDENTE );

		$this->assertFalse(
			get_role( 'editor' )->has_cap( $mappa['edit_published_posts'] ),
			'Precondizione: al ruolo completato automaticamente deve mancare un permesso del suo insieme.'
		);

		$mancante = $mappa['edit_published_posts'];

		$sabota = static function ( $valore ) use ( $mancante ) {
			if ( is_array( $valore ) && isset( $valore['editor']['capabilities'][ $mancante ] ) ) {
				unset( $valore['editor']['capabilities'][ $mancante ] );
			}

			return $valore;
		};

		add_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );

		try {
			$esito = Installazione::aggiorna_se_serve();
		} finally {
			remove_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );
		}

		$this->assertFalse( $esito, 'Con un permesso che non arriva, l\'installazione non deve dichiararsi conclusa.' );
		$this->assertSame(
			self::VERSIONE_PRECEDENTE,
			Installazione::versione_installata(),
			'La versione riletta deve restare quella precedente.'
		);

		$this->assertTrue(
			Installazione::aggiorna_se_serve(),
			'Alla richiesta successiva il lavoro deve essere ritentato.'
		);
		$this->assertTrue(
			get_role( 'editor' )->has_cap( $mancante ),
			'Al secondo tentativo il permesso deve arrivare anche al ruolo completato automaticamente.'
		);
	}
}
