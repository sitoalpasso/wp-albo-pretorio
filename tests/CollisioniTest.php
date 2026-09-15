<?php
/**
 * Prove sulle collisioni di identificativi globali: righe A-30..A-33.
 *
 * Elenchi di voci e ruoli vivono in registri di WordPress che sono di tutti.
 * Registrarci sopra senza guardare sostituisce la roba di un altro componente,
 * e nel caso dei ruoli regala i permessi dell'albo a chiunque possieda gia' un
 * ruolo con quel nome.
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
 * Nessun identificativo globale di altri viene sostituito o adottato.
 */
class CollisioniTest extends WP_UnitTestCase {

	use AmbienteAlbo;

	/**
	 * Registri, ruoli e opzione riportati allo stato iniziale.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->azzera_ambiente_albo();
	}

	/**
	 * Rimonta tutto dopo ogni prova.
	 */
	public function tear_down(): void {
		foreach ( array( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO, \AlboPretorioPa\TASSONOMIA_ORGANO ) as $nome ) {
			if ( taxonomy_exists( $nome ) ) {
				unregister_taxonomy( $nome );
			}
		}

		$this->azzera_ambiente_albo();

		parent::tear_down();
	}

	/**
	 * A-30: un elenco di voci gia' di altri blocca tutto e resta intatto.
	 *
	 * @dataProvider elenchi_in_conflitto
	 *
	 * @param string $occupato Identificativo gia' registrato da un altro componente.
	 */
	public function test_a30_collisione_su_un_elenco_di_voci( string $occupato ): void {
		register_taxonomy(
			$occupato,
			array( 'post' ),
			array(
				'label'        => 'Elenco di un altro componente',
				'public'       => true,
				'hierarchical' => true,
			)
		);

		$prima = get_taxonomy( $occupato );

		$this->assertNotFalse( $prima, 'Precondizione: l\'elenco esterno deve esistere.' );

		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );

		$esito = TipoAtto::registra();

		$this->assertWPError( $esito, 'La collisione deve fermare la registrazione.' );
		$this->assertSame(
			'albo_elenco_di_voci_in_conflitto',
			$esito->get_error_code(),
			'L\'errore deve essere quello dedicato alla collisione sugli elenchi di voci.'
		);

		$dopo = get_taxonomy( $occupato );

		$this->assertSame( $prima->label, $dopo->label, 'L\'elenco esterno deve restare invariato.' );
		$this->assertTrue( $dopo->public, 'L\'elenco esterno deve restare pubblico com\'era.' );
		$this->assertSame(
			array( 'post' ),
			(array) $dopo->object_type,
			'L\'elenco esterno deve restare agganciato a cio\' a cui era agganciato.'
		);

		$this->assertFalse( TipoAtto::registrato(), 'Il tipo dell\'albo non deve risultare registrato.' );
		$this->assertFalse( post_type_exists( \AlboPretorioPa\TIPO ), 'Il tipo dell\'albo non deve nascere.' );
		$this->assertFalse(
			conformita_core_tipo_registrato( \AlboPretorioPa\TIPO ),
			'Il tipo non deve risultare registrato presso il nucleo comune: la verifica precede la registrazione.'
		);

		$this->assertFalse( Installazione::aggiorna_se_serve(), 'L\'installazione non deve proseguire.' );
		$this->assertSame( array(), $this->permessi_dell_albo_sui_ruoli(), 'Nessun permesso deve essere assegnato.' );
		$this->assertNull( get_role( \AlboPretorioPa\RUOLO ), 'Il ruolo proprio non deve nascere.' );
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione deve risultare memorizzata.'
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString(
			$occupato,
			$this->avvisi_in_bacheca(),
			'L\'avviso deve nominare l\'identificativo in conflitto.'
		);
	}

	/**
	 * I due elenchi di voci, uno per volta.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function elenchi_in_conflitto(): array {
		return array(
			'il primo elenco'   => array( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO ),
			'il secondo elenco' => array( \AlboPretorioPa\TASSONOMIA_ORGANO ),
		);
	}

	/**
	 * A-31: se una registrazione non regge, il tipo non si dichiara registrato.
	 *
	 * Il guasto si simula smontando il secondo elenco subito dopo che WordPress
	 * lo ha registrato: e' il modo in cui una registrazione puo' non reggere
	 * senza che la chiamata segnali niente.
	 */
	public function test_a31_postcondizione_degli_elenchi_di_voci(): void {
		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );

		$smonta = static function ( $tassonomia ) {
			if ( \AlboPretorioPa\TASSONOMIA_ORGANO === $tassonomia ) {
				unregister_taxonomy( $tassonomia );
			}
		};

		add_action( 'registered_taxonomy', $smonta );

		try {
			$esito = TipoAtto::registra();
		} finally {
			remove_action( 'registered_taxonomy', $smonta );
		}

		$this->assertWPError( $esito, 'Una registrazione che non regge deve dare errore.' );
		$this->assertSame(
			'albo_elenco_di_voci_non_registrato',
			$esito->get_error_code(),
			'L\'errore deve essere quello dedicato alla postcondizione mancata.'
		);
		$this->assertFalse(
			TipoAtto::registrato(),
			'Il tipo non si dichiara registrato se i suoi elenchi di voci non ci sono.'
		);
		$this->assertFalse( Installazione::aggiorna_se_serve(), 'L\'installazione non deve proseguire.' );
	}

	/**
	 * A-32: il ruolo di una versione precedente si aggiorna.
	 *
	 * Si riconosce dal permesso marcatore, che solo l'albo assegna.
	 */
	public function test_a32_ruolo_di_una_versione_precedente(): void {
		$this->preparaTipo();

		add_role(
			\AlboPretorioPa\RUOLO,
			'Nome dato da una versione precedente',
			array(
				'read'                          => true,
				\AlboPretorioPa\RUOLO_MARCATORE => true,
			)
		);

		$this->assertTrue( Permessi::applica(), 'Un ruolo nostro si aggiorna, non si rifiuta.' );

		$mappa = conformita_core_capacita_tipo( \AlboPretorioPa\TIPO );
		$ruolo = get_role( \AlboPretorioPa\RUOLO );

		$this->assertTrue( $ruolo->has_cap( $mappa['publish_posts'] ), 'Il ruolo deve ricevere i permessi mancanti.' );
		$this->assertTrue(
			$ruolo->has_cap( \AlboPretorioPa\RUOLO_MARCATORE ),
			'Il marcatore deve restare: e\' quello che lo rende riconoscibile la volta dopo.'
		);
	}

	/**
	 * A-32: un ruolo omonimo di altri non si adotta.
	 */
	public function test_a32_ruolo_omonimo_di_altri(): void {
		$this->preparaTipo();

		add_role(
			\AlboPretorioPa\RUOLO,
			'Ruolo di un altro componente',
			array(
				'read'                       => true,
				'albo_prova_permesso_altrui' => true,
			)
		);

		$prima = get_role( \AlboPretorioPa\RUOLO )->capabilities;

		$esito = Permessi::applica();

		$this->assertWPError( $esito, 'Un ruolo omonimo di altri e\' un conflitto, non un aggiornamento.' );
		$this->assertSame(
			'albo_ruolo_in_conflitto',
			$esito->get_error_code(),
			'L\'errore deve essere quello dedicato al ruolo in conflitto.'
		);

		$this->assertSame(
			$prima,
			get_role( \AlboPretorioPa\RUOLO )->capabilities,
			'Il ruolo di altri deve restare intatto.'
		);
		$this->assertSame(
			array(),
			$this->permessi_dell_albo_sui_ruoli(),
			'Nessun ruolo deve ricevere permessi dell\'albo, nemmeno l\'amministratore.'
		);
		$this->assertFalse( Installazione::aggiorna_se_serve(), 'L\'installazione non deve proseguire.' );
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione deve risultare memorizzata.'
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString(
			\AlboPretorioPa\RUOLO,
			$this->avvisi_in_bacheca(),
			'L\'avviso deve nominare il ruolo in conflitto.'
		);
	}

	/**
	 * A-33: cancellata la sola versione, il lavoro riparte e si riconosce.
	 *
	 * E' il caso che un riconoscimento basato sull'opzione sbaglierebbe:
	 * troverebbe un ruolo senza versione memorizzata e lo direbbe estraneo.
	 */
	public function test_a33_autoriparazione_dopo_la_perdita_della_versione(): void {
		$this->preparaTipo();

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'Precondizione: la prima installazione deve riuscire.' );
		$this->assertNotNull( get_role( \AlboPretorioPa\RUOLO ), 'Precondizione: il ruolo deve esistere.' );

		delete_option( \AlboPretorioPa\OPZIONE_VERSIONE );

		$this->assertTrue(
			Installazione::aggiorna_se_serve(),
			'Persa la versione, il lavoro deve ripartire e riconoscere il proprio ruolo.'
		);
		$this->assertSame(
			\AlboPretorioPa\VERSIONE,
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE ),
			'La versione deve tornare memorizzata.'
		);
	}

	/**
	 * Avvio riuscito e tipo registrato, precondizione delle prove sui ruoli.
	 */
	private function preparaTipo(): void {
		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );
		$this->assertTrue( TipoAtto::registra(), 'Precondizione: il tipo deve essere registrato.' );
	}

	/**
	 * A-40: il marcatore che non si scrive, e la via di recupero.
	 *
	 * Senza recupero il ruolo creato a meta' diventerebbe una collisione
	 * permanente: alla richiesta dopo il componente lo troverebbe senza
	 * marcatore e lo direbbe di un altro, per sempre, su un sito dove invece
	 * lo aveva creato lui.
	 */
	public function test_a40_marcatore_non_scritto_e_recupero(): void {
		$this->preparaTipo();

		$this->assertNull( get_role( \AlboPretorioPa\RUOLO ), 'Precondizione: il ruolo non esiste ancora.' );

		$sabota = static function ( $valore ) {
			if ( ! is_array( $valore ) ) {
				return $valore;
			}

			foreach ( array_keys( $valore ) as $ruolo ) {
				unset( $valore[ $ruolo ]['capabilities'][ \AlboPretorioPa\RUOLO_MARCATORE ] );
			}

			return $valore;
		};

		add_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );

		try {
			$esito = Installazione::aggiorna_se_serve();
		} finally {
			remove_filter( 'pre_update_option_' . wp_roles()->role_key, $sabota );
		}

		$this->assertFalse( $esito, 'Senza il marcatore scritto non si dichiara successo.' );
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione deve risultare memorizzata.'
		);

		$this->assertNull(
			get_role( \AlboPretorioPa\RUOLO ),
			'Il ruolo creato a meta\' deve essere stato rimosso.'
		);

		$riletti = new \WP_Roles();

		$this->assertNull(
			$riletti->get_role( \AlboPretorioPa\RUOLO ),
			'La rimozione si rilegge dalla banca dati: e\' la postcondizione del recupero.'
		);

		$this->assertTrue(
			Installazione::aggiorna_se_serve(),
			'Tolto il sabotaggio, la richiesta successiva deve riuscire: nessuna collisione permanente.'
		);
		$this->assertTrue(
			get_role( \AlboPretorioPa\RUOLO )->has_cap( \AlboPretorioPa\RUOLO_MARCATORE ),
			'E il ruolo ricreato deve avere il marcatore.'
		);
	}

	/**
	 * A-41: la proprieta' si rilegge dallo stato corrente.
	 *
	 * Un valore memorizzato dice che abbiamo registrato qualcosa in passato,
	 * non che l'oggetto nel registro sia ancora il nostro: WordPress lo
	 * sostituisce senza dire niente a nessuno.
	 *
	 * @dataProvider oggetti_sostituibili
	 *
	 * @param string $quale `tipo` oppure l'identificativo di un elenco di voci.
	 */
	public function test_a41_proprieta_riletta_dallo_stato_corrente( string $quale ): void {
		$this->preparaTipo();

		$this->assertTrue( TipoAtto::registrato(), 'Precondizione: il tipo deve risultare nostro.' );

		if ( 'tipo' === $quale ) {
			register_post_type(
				\AlboPretorioPa\TIPO,
				array(
					'public'  => false,
					'label'   => 'Tipo sostituito da un altro componente',
					'rewrite' => false,
				)
			);
		} else {
			register_taxonomy(
				$quale,
				array( 'post' ),
				array(
					'public' => false,
					'label'  => 'Elenco sostituito da un altro componente',
				)
			);
		}

		$this->assertFalse(
			TipoAtto::registrato(),
			'Sostituito l\'oggetto nel registro, quello che c\'e\' non e\' piu\' nostro.'
		);

		$this->assertFalse( Installazione::aggiorna_se_serve(), 'L\'installazione non deve proseguire.' );
		$this->assertSame( array(), $this->permessi_dell_albo_sui_ruoli(), 'Nessun permesso deve essere assegnato.' );
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione deve risultare memorizzata.'
		);

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Contenuto del tipo sostituito',
			)
		);

		$this->assertSame(
			'publish',
			get_post_status( $id ),
			'Lo sbarramento non deve toccare i contenuti di un tipo che non e\' piu\' nostro.'
		);
	}

	/**
	 * Gli oggetti globali che un altro componente puo' sostituire.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function oggetti_sostituibili(): array {
		return array(
			'il tipo atto'      => array( 'tipo' ),
			'il primo elenco'   => array( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO ),
			'il secondo elenco' => array( \AlboPretorioPa\TASSONOMIA_ORGANO ),
		);
	}

	/**
	 * A-43: cosa resta registrato quando la postcondizione cade, e cosa no.
	 *
	 * **Non basta dire inerte.** Il tipo resta registrato e non c'e' un modo
	 * pulito di smontarlo: il nucleo comune non espone una funzione per togliere
	 * quel solo tipo, e toglierlo a WordPress lascerebbe il registro del nucleo
	 * a dire il contrario. Quello che si garantisce e' l'elenco chiuso di cio'
	 * che non succede, e questa prova lo verifica voce per voce.
	 */
	public function test_a43_stato_parziale_sicuro(): void {
		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );

		$smonta = static function ( $tassonomia ) {
			if ( \AlboPretorioPa\TASSONOMIA_ORGANO === $tassonomia ) {
				unregister_taxonomy( $tassonomia );
			}
		};

		add_action( 'registered_taxonomy', $smonta );

		try {
			$esito = TipoAtto::registra();
		} finally {
			remove_action( 'registered_taxonomy', $smonta );
		}

		$this->assertWPError( $esito, 'Precondizione: la postcondizione deve essere caduta.' );

		// Cio' che resta, detto invece che taciuto.
		$this->assertTrue(
			post_type_exists( \AlboPretorioPa\TIPO ),
			'Il tipo resta registrato presso WordPress: non c\'e\' modo di smontarlo in modo pulito.'
		);
		$this->assertTrue(
			conformita_core_tipo_registrato( \AlboPretorioPa\TIPO ),
			'E resta registrato anche presso il nucleo comune, per la stessa ragione.'
		);

		// Cio' che e' comunque garantito.
		$this->assertFalse( TipoAtto::registrato(), 'Il componente non lo considera proprio.' );

		$oggetto = get_post_type_object( \AlboPretorioPa\TIPO );

		$this->assertFalse( $oggetto->public, 'Gli argomenti restano quelli chiusi.' );
		$this->assertFalse( $oggetto->publicly_queryable, 'Il tipo non e\' interrogabile dal pubblico.' );

		$this->assertFalse( Installazione::aggiorna_se_serve(), 'Nessuna installazione.' );
		$this->assertSame( array(), $this->permessi_dell_albo_sui_ruoli(), 'Nessun permesso su nessun ruolo.' );
		$this->assertNull( get_role( \AlboPretorioPa\RUOLO ), 'Nessun ruolo proprio.' );
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione memorizzata.'
		);

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Contenuto nello stato parziale',
			)
		);

		$this->assertSame(
			'publish',
			get_post_status( $id ),
			'Nessuna azione sui contenuti: il componente non tocca niente.'
		);

		$ordinario = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Contenuto ordinario di controllo',
			)
		);

		wp_set_current_user( 0 );

		$this->richiesta_pubblica( get_permalink( $ordinario ) );
		$this->assertTrue( is_singular(), 'Precondizione: un contenuto ordinario pubblicato e\' raggiungibile.' );

		$this->richiesta_pubblica( get_permalink( $id ) );
		$this->assertTrue( is_404(), 'Nessuna raggiungibilita\' pubblica, che e\' la garanzia che regge tutto il resto.' );
	}
}
