<?php
/**
 * Lucchetti dell'albo su una banca dati che tiene un solo lucchetto per connessione.
 *
 * Le prove accendono l'imitazione di `lucchetto-unico.php`, e per vedere che
 * cosa vede un'altra richiesta aprono una seconda connessione e la mettono al
 * posto di quella del sito per il tempo di una chiamata: il componente la
 * tratta come la connessione di un'altra richiesta, con il suo stato.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Importazione;
use AlboPretorioPa\Lucchetti;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Repertorio;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lucchetti della banca dati nelle prove.

/**
 * Righe A-340..A-342 del catalogo di collaudo.
 */
class LucchettiTest extends \WP_UnitTestCase {

	/**
	 * La seconda connessione, se aperta.
	 *
	 * @var \wpdb|null
	 */
	private ?\wpdb $altra = null;

	/**
	 * I filtri sulle istruzioni messi dalla prova.
	 *
	 * @var array<int, callable>
	 */
	private array $filtri = array();

	/**
	 * Se l'imitazione era accesa per tutta la suite.
	 *
	 * @var bool
	 */
	private bool $gia_accesa = false;

	/**
	 * Accende l'imitazione.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->gia_accesa = LucchettoUnico::accesa();

		LucchettoUnico::attiva();
	}

	/**
	 * Spegne l'imitazione, se l'ha accesa questa prova, e lascia i lucchetti.
	 */
	public function tear_down(): void {
		$this->togli_filtri();

		if ( null !== $this->altra ) {
			LucchettoUnico::rilascia_tutti( $this->altra );
			$this->altra->close();
			$this->altra = null;
		}

		if ( $this->gia_accesa ) {
			LucchettoUnico::rilascia_tutti( $GLOBALS['wpdb'] );
			LucchettoUnico::dimentica_stato();
		} else {
			LucchettoUnico::disattiva();
		}

		parent::tear_down();
	}

	/**
	 * A-340: il turno dell'importazione resta chiuso quando dentro si prende la numerazione dell'anno.
	 */
	public function test_a340_turno_dell_importazione_e_numerazione_dell_anno(): void {
		$this->senza_transazione_esterna();

		$this->assertTrue( Lucchetti::unico(), 'Precondizione: la banca dati imitata tiene un lucchetto solo.' );

		$this->assertTrue( $this->turno_importazione( 'prendi' ) );

		// Come fa ogni atto importato: la numerazione dell'anno, presa e lasciata dentro il turno.
		$this->assertTrue( Repertorio::blocca_anno( 2041 ) );
		Repertorio::sblocca_anno( 2041 );

		$occupata = $this->da_un_altra_richiesta( fn() => $this->turno_importazione( 'prendi' ) );

		$this->assertSame( array( 'albo_importazione_occupata' ), $occupata->get_error_codes(), 'Un\'altra richiesta non entra nell\'importazione.' );
		$this->assertStringContainsString( 'un\'altra operazione dell\'albo', strtolower( $occupata->get_error_message() ), 'Il messaggio vale per qualunque operazione che tiene il lucchetto.' );
		$this->assertWPError( $this->da_un_altra_richiesta( static fn() => Repertorio::blocca_anno( 2039 ) ), 'Ne\' nella numerazione di un altro anno.' );

		// Finito il turno, il lucchetto e' libero.
		$this->turno_importazione( 'lascia' );

		$this->assertTrue(
			$this->da_un_altra_richiesta(
				function () {
					$preso = $this->turno_importazione( 'prendi' );
					$this->turno_importazione( 'lascia' );

					return $preso;
				}
			),
			'Il turno lasciato si riprende da un\'altra richiesta.'
		);
	}

	/**
	 * A-341: le altre prese annidate, la presa trattenuta e il turno delle proroghe.
	 */
	public function test_a341_prese_annidate_e_trattenute(): void {
		$this->senza_transazione_esterna();

		// Il turno delle proroghe dentro un'altra operazione: non lascia il lucchetto di quella.
		$this->assertTrue( $this->turno_importazione( 'prendi' ) );
		$this->assertTrue( $this->turno_proroghe( 'prendi' ) );
		$this->assertTrue( $this->turno_proroghe( 'lascia' ), 'Il turno delle proroghe risulta lasciato.' );
		$this->assertWPError( $this->da_un_altra_richiesta( fn() => $this->turno_importazione( 'prendi' ) ), 'Il turno dell\'importazione resta chiuso.' );

		$this->turno_importazione( 'lascia' );

		// Il turno delle proroghe da solo: preso, chiude; lasciato, si riapre.
		$this->assertTrue( $this->turno_proroghe( 'prendi' ) );
		$occupata = $this->da_un_altra_richiesta( fn() => $this->turno_proroghe( 'prendi' ) );

		$this->assertSame( array( 'albo_proroga_occupata' ), $occupata->get_error_codes() );
		$this->assertStringContainsString( 'un\'altra operazione dell\'albo', $occupata->get_error_message() );
		$this->assertTrue( $this->turno_proroghe( 'lascia' ) );
		$this->assertTrue(
			$this->da_un_altra_richiesta(
				function () {
					$preso = $this->turno_proroghe( 'prendi' );
					$this->turno_proroghe( 'lascia' );

					return $preso;
				}
			)
		);

		// Dentro una transazione aperta da altri la numerazione resta presa fino alla fine della connessione.
		$this->togli_filtri();

		$this->assertTrue( Repertorio::transazione_esterna(), 'Precondizione: la prova gira nella transazione della suite.' );
		$this->assertTrue( $this->turno_importazione( 'prendi' ) );
		$this->assertTrue( Repertorio::blocca_anno( 2041 ) );
		Repertorio::sblocca_anno( 2041 );
		$this->turno_importazione( 'lascia' );

		$this->assertWPError( $this->da_un_altra_richiesta( static fn() => Repertorio::blocca_anno( 2041 ) ), 'La numerazione trattenuta resta chiusa alle altre richieste.' );

		// E una presa successiva della stessa richiesta, lasciata, non la libera.
		$this->assertTrue( $this->turno_importazione( 'prendi' ) );
		$this->turno_importazione( 'lascia' );

		$this->assertWPError( $this->da_un_altra_richiesta( static fn() => Repertorio::blocca_anno( 2041 ) ), 'Neanche dopo un\'altra operazione della stessa richiesta.' );
	}

	/**
	 * A-342: il tipo di banca dati si legge dalla versione, e nel dubbio vale il lucchetto unico.
	 */
	public function test_a342_tipo_dalla_versione(): void {
		$casi = array(
			'5.5.64-MariaDB'            => true,
			'10.0.1-MariaDB'            => true,
			'10.0.2-MariaDB'            => false,
			'10.11.6-MariaDB-0+deb12u1' => false,
			'5.5.5-10.6.12-MariaDB'     => false,
			'5.5.5-10.0.1-MariaDB'      => true,
			'5.6.51'                    => true,
			'5.7.4-m14'                 => true,
			'5.7.5-m15'                 => false,
			'8.0.36'                    => false,
			'8.4.0-log'                 => false,
			''                          => true,
			'versione sconosciuta'      => true,
		);

		foreach ( $casi as $versione => $unico ) {
			$this->assertSame( $unico, Lucchetti::versione_a_lucchetto_unico( (string) $versione ), (string) $versione );
		}

		// Senza risposta alla domanda sulla versione: lucchetto unico.
		LucchettoUnico::dimentica_stato();
		$this->filtra(
			static function ( $istruzione ) {
				return ( is_string( $istruzione ) && 1 === preg_match( '/^\s*SELECT\s+VERSION\(\)\s*$/i', $istruzione ) ) ? 'SELECT NON_ESISTE()' : $istruzione;
			}
		);

		$zitto = $GLOBALS['wpdb']->suppress_errors( true );

		try {
			$this->assertTrue( Lucchetti::unico() );
		} finally {
			$GLOBALS['wpdb']->suppress_errors( $zitto );
		}
	}

	/**
	 * Prende o lascia il turno dell'importazione.
	 *
	 * @param string $azione `prendi` o `lascia`.
	 * @return mixed
	 */
	private function turno_importazione( string $azione ) {
		$metodo = new \ReflectionMethod( Importazione::class, $azione . '_turno' );
		$metodo->setAccessible( true );

		return $metodo->invoke( null );
	}

	/**
	 * Prende o lascia il turno delle proroghe.
	 *
	 * @param string $azione `prendi` o `lascia`.
	 * @return mixed
	 */
	private function turno_proroghe( string $azione ) {
		$metodo = new \ReflectionMethod( Passaggi::class, $azione . '_turno' );
		$metodo->setAccessible( true );

		return $metodo->invoke( null );
	}

	/**
	 * Esegue una chiamata con la connessione di un'altra richiesta.
	 *
	 * @param callable $chiamata Chiamata.
	 * @return mixed
	 */
	private function da_un_altra_richiesta( callable $chiamata ) {
		if ( null === $this->altra ) {
			$this->altra = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$this->altra->set_prefix( $GLOBALS['wpdb']->prefix );
		}

		$principale      = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = $this->altra; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- la connessione di un'altra richiesta.

		try {
			return $chiamata();
		} finally {
			$GLOBALS['wpdb'] = $principale; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- si rimette quella del sito.
		}
	}

	/**
	 * Fa rispondere la banca dati come fuori da ogni transazione, per la
	 * connessione del sito: la numerazione si lascia come in una richiesta vera.
	 */
	private function senza_transazione_esterna(): void {
		$this->filtra(
			static function ( $istruzione ) {
				if ( ! is_string( $istruzione ) ) {
					return $istruzione;
				}

				if ( 'SELECT @@autocommit' === $istruzione ) {
					return 'SELECT 1';
				}

				return 'SELECT @@in_transaction' === $istruzione ? 'SELECT 0' : $istruzione;
			}
		);
	}

	/**
	 * Mette un filtro sulle istruzioni, prima dell'imitazione.
	 *
	 * @param callable $filtro Filtro.
	 */
	private function filtra( callable $filtro ): void {
		$this->filtri[] = $filtro;
		add_filter( 'query', $filtro, 1 );
	}

	/**
	 * Toglie i filtri messi dalla prova.
	 */
	private function togli_filtri(): void {
		foreach ( $this->filtri as $filtro ) {
			remove_filter( 'query', $filtro, 1 );
		}

		$this->filtri = array();
	}
}
