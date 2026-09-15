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

		$this->assertFalse( TipoAtto::registrazione_completa(), 'La registrazione dell\'albo non deve risultare completa.' );
		$this->assertFalse( TipoAtto::tipo_nostro(), 'E il tipo non deve essere nato affatto.' );
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
			TipoAtto::registrazione_completa(),
			'La registrazione non si dichiara completa se i suoi elenchi di voci non ci sono.'
		);

		$this->assertTrue(
			TipoAtto::tipo_nostro(),
			'Ma il tipo resta nostro: l\'oggetto si conserva appena il nucleo comune lo registra, prima degli elenchi.'
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
	 * A-41: il tipo sostituito non e' piu' nostro, e non lo governiamo.
	 *
	 * WordPress mette l'oggetto di chi registra per ultimo al posto del nostro,
	 * senza dire niente a nessuno. Da quel momento quei contenuti sono di un
	 * altro componente, e riportarli in bozza sarebbe governare roba di altri.
	 */
	public function test_a41_tipo_sostituito_non_e_piu_nostro(): void {
		$this->preparaTipo();

		$this->assertTrue( TipoAtto::tipo_nostro(), 'Precondizione: il tipo deve essere nostro.' );

		register_post_type(
			\AlboPretorioPa\TIPO,
			array(
				'public'  => false,
				'label'   => 'Tipo sostituito da un altro componente',
				'rewrite' => false,
			)
		);

		$this->assertFalse( TipoAtto::tipo_nostro(), 'Sostituito l\'oggetto, il tipo non e\' piu\' nostro.' );
		$this->assertFalse( TipoAtto::registrazione_completa(), 'E la registrazione non e\' piu\' completa.' );
		$this->assertFalse( Installazione::aggiorna_se_serve(), 'L\'installazione non deve proseguire.' );

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
	 * A-41: un elenco di voci sostituito non toglie la proprieta' del tipo.
	 *
	 * **E' il caso in cui la risposta sbagliata apre invece di chiudere.** Con
	 * una sola condizione per due domande, perdere un elenco faceva smettere lo
	 * sbarramento su un tipo che era ancora nostro: da quel momento una
	 * richiesta supportata sarebbe arrivata a pubblicato.
	 *
	 * @dataProvider elenchi_sostituibili
	 *
	 * @param string $elenco Identificativo dell'elenco che un altro sostituisce.
	 */
	public function test_a41_elenco_sostituito_non_toglie_il_tipo( string $elenco ): void {
		$this->preparaTipo();

		register_taxonomy(
			$elenco,
			array( 'post' ),
			array(
				'public' => false,
				'label'  => 'Elenco sostituito da un altro componente',
			)
		);

		$this->assertTrue(
			TipoAtto::tipo_nostro(),
			'Il tipo resta nostro: un elenco di voci non e\' il tipo.'
		);
		$this->assertFalse(
			TipoAtto::registrazione_completa(),
			'La registrazione non e\' piu\' completa: manca un elenco che era nostro.'
		);

		$this->assertFalse(
			Installazione::aggiorna_se_serve(),
			'L\'installazione si ferma, perche\' ha bisogno dell\'insieme completo.'
		);

		// L'inserimento arriva da codice: il rifiuto va a chi programma.
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto con un elenco di voci perso',
			)
		);

		$this->assertSame(
			'draft',
			get_post_status( $id ),
			'Lo sbarramento deve continuare a respingere: quel tipo e\' ancora il nostro.'
		);
	}

	/**
	 * I due elenchi di voci che un altro componente puo' sostituire.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function elenchi_sostituibili(): array {
		return array(
			'il primo elenco'   => array( \AlboPretorioPa\TASSONOMIA_TIPO_ATTO ),
			'il secondo elenco' => array( \AlboPretorioPa\TASSONOMIA_ORGANO ),
		);
	}

	/**
	 * Provoca la caduta della postcondizione degli elenchi di voci.
	 *
	 * Il guasto si simula smontando il secondo elenco subito dopo che WordPress
	 * lo ha registrato: e' il modo in cui una registrazione puo' non reggere
	 * senza che la chiamata segnali niente.
	 *
	 * @return \WP_Error|true L'esito della registrazione.
	 */
	private function registrazione_con_elenco_perso() {
		$smonta = static function ( $tassonomia ) {
			if ( \AlboPretorioPa\TASSONOMIA_ORGANO === $tassonomia ) {
				unregister_taxonomy( $tassonomia );
			}
		};

		add_action( 'registered_taxonomy', $smonta );

		try {
			return TipoAtto::registra();
		} finally {
			remove_action( 'registered_taxonomy', $smonta );
		}
	}

	/**
	 * Cio' che vale in tutti e due i momenti del ciclo di vita.
	 *
	 * @param string $quando Etichetta del momento, per i messaggi.
	 */
	private function verifica_stato_parziale( string $quando ): void {
		$this->assertTrue(
			post_type_exists( \AlboPretorioPa\TIPO ),
			$quando . ': il tipo resta registrato presso WordPress, e va detto invece che taciuto.'
		);
		$this->assertTrue(
			conformita_core_tipo_registrato( \AlboPretorioPa\TIPO ),
			$quando . ': e resta registrato anche presso il nucleo comune.'
		);

		$this->assertTrue(
			TipoAtto::tipo_nostro(),
			$quando . ': il tipo e\' nostro, e questo tiene in piedi lo sbarramento.'
		);
		$this->assertFalse(
			TipoAtto::registrazione_completa(),
			$quando . ': la registrazione non e\' completa.'
		);

		$oggetto = get_post_type_object( \AlboPretorioPa\TIPO );

		$this->assertFalse( $oggetto->public, $quando . ': gli argomenti restano quelli chiusi.' );
		$this->assertFalse( $oggetto->publicly_queryable, $quando . ': il tipo non e\' interrogabile dal pubblico.' );

		$this->assertFalse( Installazione::aggiorna_se_serve(), $quando . ': l\'installazione non prosegue.' );

		// L'inserimento arriva da codice: il rifiuto va a chi programma.
		$this->setExpectedIncorrectUsage( 'wp_insert_post' );

		$id = wp_insert_post(
			array(
				'post_type'   => \AlboPretorioPa\TIPO,
				'post_status' => 'publish',
				'post_title'  => 'Atto nello stato parziale',
			)
		);

		$this->assertSame(
			'draft',
			get_post_status( $id ),
			$quando . ': una pubblicazione da un ingresso supportato deve restare in bozza.'
		);

		$ordinario = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Contenuto ordinario di controllo',
			)
		);

		$utente = get_current_user_id();

		wp_set_current_user( 0 );

		$this->richiesta_pubblica( get_permalink( $ordinario ) );
		$this->assertTrue( is_singular(), $quando . ': precondizione, un contenuto ordinario e\' raggiungibile.' );

		$this->richiesta_pubblica( get_permalink( $id ) );
		$this->assertTrue( is_404(), $quando . ': nessuna raggiungibilita\' pubblica.' );

		wp_set_current_user( $utente );

		$this->assertStringContainsString(
			\AlboPretorioPa\TIPO,
			$this->avvisi_in_bacheca(),
			$quando . ': l\'avviso deve dire cosa resta registrato.'
		);
	}

	/**
	 * A-43, primo momento: prima installazione su un sito pulito.
	 *
	 * Qui, e soltanto qui, si puo' dire che ruolo, permessi e versione non
	 * esistono: non sono stati creati perche' l'installazione non e' arrivata
	 * fin li'.
	 */
	public function test_a43_stato_parziale_prima_installazione(): void {
		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio deve riuscire.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertWPError( $this->registrazione_con_elenco_perso(), 'Precondizione: la postcondizione deve cadere.' );

		$this->verifica_stato_parziale( 'Prima installazione' );

		$this->assertNull( get_role( \AlboPretorioPa\RUOLO ), 'Nessun ruolo proprio viene creato.' );
		$this->assertSame( array(), $this->permessi_dell_albo_sui_ruoli(), 'Nessun permesso viene scritto.' );
		$this->assertFalse(
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE, false ),
			'Nessuna versione viene memorizzata.'
		);

		$this->assertTrue(
			TipoAtto::registra(),
			'Ripristinati gli elenchi, una seconda chiamata nella stessa richiesta deve completare.'
		);
		$this->assertTrue( TipoAtto::registrazione_completa(), 'E la registrazione risulta completa.' );
	}

	/**
	 * A-43, secondo momento: sito su cui il componente aveva gia' completato.
	 *
	 * **E' il caso che la prova precedente non copriva.** Partendo da un
	 * ambiente azzerato si poteva dire che ruolo, permessi e versione non
	 * esistono, ma quello e' vero della prima installazione e basta. Su un sito
	 * gia' installato esistono, e la domanda giusta non e' se ci sono: e' se il
	 * tentativo fallito li tocca.
	 */
	public function test_a43_stato_parziale_su_sito_gia_installato(): void {
		$this->preparaTipo();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( Installazione::aggiorna_se_serve(), 'Precondizione: la prima installazione riesce.' );

		$ruolo_prima    = get_role( \AlboPretorioPa\RUOLO )->capabilities;
		$permessi_prima = $this->permessi_dell_albo_sui_ruoli();
		$versione_prima = get_option( \AlboPretorioPa\OPZIONE_VERSIONE );

		$this->assertNotSame( array(), $permessi_prima, 'Precondizione: i permessi esistono.' );
		$this->assertSame( \AlboPretorioPa\VERSIONE, $versione_prima, 'Precondizione: la versione e\' memorizzata.' );

		// La richiesta successiva: gli agganci ripartono da zero, la banca dati no.
		\Conformita_Core_Tipi::azzera();
		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		TipoAtto::azzera();

		$this->assertTrue( Avvio::esegui(), 'Precondizione: l\'avvio della richiesta successiva riesce.' );
		$this->assertWPError( $this->registrazione_con_elenco_perso(), 'Precondizione: la postcondizione deve cadere.' );

		$this->verifica_stato_parziale( 'Sito gia\' installato' );

		$this->assertNotNull( get_role( \AlboPretorioPa\RUOLO ), 'Il ruolo preesistente non sparisce.' );
		$this->assertSame(
			$ruolo_prima,
			get_role( \AlboPretorioPa\RUOLO )->capabilities,
			'Il ruolo preesistente non viene ne\' modificato ne\' ampliato dal tentativo fallito.'
		);
		$this->assertSame(
			$permessi_prima,
			$this->permessi_dell_albo_sui_ruoli(),
			'I permessi preesistenti restano quelli che erano.'
		);
		$this->assertSame(
			$versione_prima,
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE ),
			'La versione preesistente resta memorizzata: non si cancella cio\' che era gia\' valido.'
		);

		/*
		 * L'installazione si ferma **per la precondizione e non perche' la
		 * versione coincide**. Senza questo passaggio l'asserzione precedente
		 * sarebbe vuota: con la versione gia' allineata il lavoro esce prima per
		 * un motivo che non c'entra, e un componente che si accontentasse della
		 * proprieta' del tipo passerebbe lo stesso.
		 */
		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, '0.1.0-alpha' );

		$this->assertFalse(
			Installazione::aggiorna_se_serve(),
			'Anche con una versione da aggiornare, l\'installazione si ferma: le manca l\'insieme completo.'
		);
		$this->assertSame(
			'0.1.0-alpha',
			get_option( \AlboPretorioPa\OPZIONE_VERSIONE ),
			'E la versione non viene avanzata.'
		);

		update_option( \AlboPretorioPa\OPZIONE_VERSIONE, $versione_prima );

		// Ancora la richiesta dopo, con gli elenchi al loro posto.
		\Conformita_Core_Tipi::azzera();
		\Conformita_Core_Sezioni::azzera();
		Avvio::azzera();
		TipoAtto::azzera();

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra(), 'Ripristinati gli elenchi, si torna allo stato completo.' );
		$this->assertTrue( TipoAtto::registrazione_completa(), 'E la registrazione risulta completa.' );
	}
}
