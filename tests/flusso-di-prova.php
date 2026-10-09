<?php
/**
 * Aiuti comuni alle prove dei passaggi che partono dalla verifica.
 *
 * Costruiscono l'assetto delle righe di ALBO-22, ALBO-11, ALBO-27, ALBO-02,
 * ALBO-01 e ALBO-08 alla pubblicazione: utenti nominati per i permessi che
 * hanno, un tipo con durata di origine normativa e uno di origine
 * dell'amministrazione, un anno del repertorio aperto, gli orologi fissati e
 * l'atto portato in verifica dal suo autore. Osservano quello che va davvero
 * nella tabella dei contenuti, e fotografano l'atto per intero.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\Avvio;
use AlboPretorioPa\DatiAtto;
use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\Durate;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\RiquadroPassaggi;
use AlboPretorioPa\TipoAtto;

use const AlboPretorioPa\META_DATA_ADOZIONE;
use const AlboPretorioPa\RUOLO;
use const AlboPretorioPa\TASSONOMIA_ORGANO;
use const AlboPretorioPa\TASSONOMIA_TIPO_ATTO;
use const AlboPretorioPa\TIPO;

/**
 * Assetto, ingressi e osservazioni dei due passaggi.
 */
trait FlussoDiProva {

	use AmbienteAlbo;
	use DepositoDiProva;

	/**
	 * L'istante della pubblicazione nelle prove: nel futuro dell'ora vera,
	 * cosi' che la data scritta dalla bozza non sia mai una data futura per
	 * caso, e in un anno che nessun'altra prova numera.
	 *
	 * @var string
	 */
	protected static $istante_di_prova = '2041-10-01 09:30:00';

	/**
	 * Amministratore.
	 *
	 * @var int
	 */
	protected $amministratore = 0;

	/**
	 * Chi redige, autore degli atti di prova.
	 *
	 * @var int
	 */
	protected $redattore = 0;

	/**
	 * Chi pubblica, con il ruolo proprio del componente: redige e pubblica.
	 *
	 * @var int
	 */
	protected $pubblicatore = 0;

	/**
	 * Voci degli elenchi, per nome breve.
	 *
	 * @var array<string, int>
	 */
	protected $voci = array();

	/**
	 * Le scritture osservate nella tabella dei contenuti: stato e data per atto.
	 *
	 * @var array<int, array{id: int, stato: string, data: string}>
	 */
	protected $scritti = array();

	/**
	 * Tipo, permessi, utenti, voci, durate, repertorio, orologi e osservatori.
	 */
	protected function prepara_flusso(): void {
		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->azzera_ambiente_albo();

		update_option( 'timezone_string', 'Europe/Rome' );

		$this->assertTrue( Avvio::esegui() );
		$this->assertTrue( TipoAtto::registra() );
		$this->assertTrue( Durate::registra() );
		$this->assertTrue( DatiAtto::registra() );
		$this->assertTrue( DocumentiAtto::registra() );
		$this->assertTrue( Permessi::applica() );

		$this->amministratore = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->redattore      = $this->utente_con( Permessi::CHIAVI_REDAZIONE );
		$this->pubblicatore   = self::factory()->user->create( array( 'role' => RUOLO ) );

		wp_set_current_user( $this->amministratore );

		$this->voci = array(
			'normativo' => $this->voce( TASSONOMIA_TIPO_ATTO, 'Deliberazione' ),
			'scelto'    => $this->voce( TASSONOMIA_TIPO_ATTO, 'Avviso' ),
			'organo'    => $this->voce( TASSONOMIA_ORGANO, 'Giunta' ),
		);

		$this->assertTrue( Durate::configura( $this->voci['normativo'], 15, 'norma', 'Estremi di prova' ) );
		$this->assertTrue( Durate::configura( $this->voci['scelto'], 10, 'amministrazione', '' ) );

		$this->fissa_orologi( self::$istante_di_prova );

		$this->assertTrue( Repertorio::dichiara( 0, $this->amministratore, Passaggi::ora() ), 'Precondizione: l\'anno delle prove numera.' );

		$this->aggancia_server_finto();

		add_filter( 'wp_insert_post_data', array( $this, 'osserva_scrittura' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Toglie osservatori, orologi, modulo simulato, disco e ambiente.
	 */
	protected function smonta_flusso(): void {
		remove_filter( 'wp_insert_post_data', array( $this, 'osserva_scrittura' ), PHP_INT_MAX );

		Passaggi::azzera_orologio();
		\Conformita_Core_Scadenza::azzera_orologio();

		$_POST  = array();
		$_GET   = array();
		$_FILES = array();
		unset( $GLOBALS['current_screen'] );
		$this->sgancia_server_finto();
		$this->azzera_ambiente_albo();
	}

	/**
	 * Fissa lo stesso istante sull'orologio dell'albo e su quello del meccanismo comune.
	 *
	 * @param string $locale Data e ora nel fuso del sito.
	 */
	protected function fissa_orologi( string $locale ): void {
		$istante = new \DateTimeImmutable( $locale, wp_timezone() );

		Passaggi::fissa_orologio( $istante );
		\Conformita_Core_Scadenza::fissa_orologio( $istante );
	}

	/**
	 * Osservatore delle scritture, all'ultima priorita' e dopo il guardiano.
	 *
	 * @param array<string, mixed> $data    Dati che stanno per essere scritti.
	 * @param array<string, mixed> $postarr Richiesta.
	 * @return array<string, mixed>
	 */
	public function osserva_scrittura( $data, $postarr ) {
		if ( TIPO === $data['post_type'] ) {
			$this->scritti[] = array(
				'id'    => isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0,
				'stato' => (string) $data['post_status'],
				'data'  => (string) $data['post_date'],
			);
		}

		return $data;
	}

	/**
	 * Gli stati scritti per un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, string>
	 */
	protected function stati_scritti( int $atto_id ): array {
		$stati = array();

		foreach ( $this->scritti as $scritto ) {
			if ( $atto_id === $scritto['id'] ) {
				$stati[] = $scritto['stato'];
			}
		}

		return $stati;
	}

	/**
	 * Un utente con i soli permessi indicati, per nome generico.
	 *
	 * @param array<int, string> $chiavi Nomi generici.
	 * @return int
	 */
	protected function utente_con( array $chiavi ): int {
		$mappa = Permessi::mappa();

		$this->assertIsArray( $mappa );

		$id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$utente = new \WP_User( $id );

		foreach ( $chiavi as $chiave ) {
			$utente->add_cap( $mappa[ $chiave ] );
		}

		return $id;
	}

	/**
	 * Una voce di un elenco.
	 *
	 * @param string $tassonomia Elenco.
	 * @param string $nome       Nome.
	 * @return int
	 */
	protected function voce( string $tassonomia, string $nome ): int {
		$esito = wp_insert_term( $nome, $tassonomia );

		$this->assertIsArray( $esito );

		return (int) $esito['term_id'];
	}

	/**
	 * Una bozza completa del redattore, documento principale compreso.
	 *
	 * @param string               $tipo  Nome breve del tipo.
	 * @param array<string, mixed> $campi Campi in piu' della riga.
	 * @return int
	 */
	protected function atto_completo( string $tipo = 'normativo', array $campi = array() ): int {
		$id = wp_insert_post(
			$campi + array(
				'post_type'    => TIPO,
				'post_status'  => 'draft',
				'post_title'   => 'Atto di prova',
				'post_content' => 'Testo di prova.',
				'post_author'  => $this->redattore,
			)
		);

		$this->assertIsInt( $id );

		wp_set_object_terms( $id, array( $this->voci[ $tipo ] ), TASSONOMIA_TIPO_ATTO );
		wp_set_object_terms( $id, array( $this->voci['organo'] ), TASSONOMIA_ORGANO );
		update_post_meta( $id, META_DATA_ADOZIONE, '2041-09-10' );

		$this->deposita_documento( $id );

		$this->assertSame( array(), DatiAtto::mancanti( $id ), 'Precondizione: l\'atto e\' completo.' );

		return $id;
	}

	/**
	 * Un atto completo mandato in verifica dal suo autore.
	 *
	 * @param string               $tipo  Nome breve del tipo.
	 * @param array<string, mixed> $campi Campi in piu' della riga.
	 * @return int
	 */
	protected function atto_in_verifica( string $tipo = 'normativo', array $campi = array() ): int {
		$id = $this->atto_completo( $tipo, $campi );

		$this->come(
			$this->redattore,
			static function () use ( $id ) {
				return wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'pending',
					)
				);
			}
		);

		clean_post_cache( $id );

		$this->assertSame( 'pending', get_post_status( $id ), 'Precondizione: l\'atto e\' in verifica.' );

		return $id;
	}

	/**
	 * Esegue una funzione come l'utente indicato, e rimette quello di prima.
	 *
	 * @param int      $utente   Utente.
	 * @param callable $funzione Funzione.
	 * @return mixed
	 */
	protected function come( int $utente, callable $funzione ) {
		$prima = get_current_user_id();

		wp_set_current_user( $utente );

		try {
			return $funzione();
		} finally {
			wp_set_current_user( $prima );
		}
	}

	/**
	 * Pubblica da codice, come l'utente indicato.
	 *
	 * @param int                  $atto_id   Atto.
	 * @param int                  $utente    Utente.
	 * @param array<string, mixed> $richiesta Richiesta; la conferma c'e' salvo diversa indicazione.
	 * @return mixed
	 */
	protected function pubblica_da_codice( int $atto_id, int $utente, array $richiesta = array( 'conferma' => true ) ) {
		return $this->come(
			$utente,
			static function () use ( $atto_id, $richiesta ) {
				return albo_pretorio_pubblica( $atto_id, $richiesta );
			}
		);
	}

	/**
	 * Un invio vero del riquadro, come l'utente indicato.
	 *
	 * @param int                  $atto_id Atto.
	 * @param int                  $utente  Utente.
	 * @param array<string, mixed> $campi   Campi del riquadro e della schermata, oltre a quelli di base.
	 * @return string Indirizzo di ritorno.
	 */
	protected function invia_riquadro( int $atto_id, int $utente, array $campi ): string {
		return $this->come(
			$utente,
			function () use ( $atto_id, $campi ) {
				set_current_screen( 'post' );

				$_POST = array_merge(
					array(
						'action'                        => 'editpost',
						'post_ID'                       => (string) $atto_id,
						'post_type'                     => TIPO,
						'post_title'                    => get_the_title( $atto_id ),
						'_wpnonce'                      => wp_create_nonce( 'update-post_' . $atto_id ),
						RiquadroPassaggi::CAMPO_GETTONE => wp_create_nonce( RiquadroPassaggi::azione( $atto_id ) ),
					),
					$campi
				);

				try {
					return RiquadroPassaggi::elabora();
				} finally {
					$_POST = array();
					clean_post_cache( $atto_id );
				}
			}
		);
	}

	/**
	 * I campi della casella di data di WordPress, con la data di partenza e quella inviata.
	 *
	 * @param string $partenza Data mostrata dalla schermata, nel formato della banca dati.
	 * @param string $inviata  Data inviata.
	 * @return array<string, string>
	 */
	protected function campi_data( string $partenza, string $inviata ): array {
		$p = new \DateTimeImmutable( $partenza );
		$i = new \DateTimeImmutable( $inviata );

		return array(
			'aa'        => $i->format( 'Y' ),
			'mm'        => $i->format( 'm' ),
			'jj'        => $i->format( 'd' ),
			'hh'        => $i->format( 'H' ),
			'mn'        => $i->format( 'i' ),
			'ss'        => $i->format( 's' ),
			'hidden_aa' => $p->format( 'Y' ),
			'hidden_mm' => $p->format( 'm' ),
			'hidden_jj' => $p->format( 'd' ),
			'hidden_hh' => $p->format( 'H' ),
			'hidden_mn' => $p->format( 'i' ),
		);
	}

	/**
	 * Le voci di registro di un atto, per intero.
	 *
	 * @param int                  $atto_id Atto.
	 * @param array<string, mixed> $filtri  Altri filtri.
	 * @return array<int, array<string, mixed>>
	 */
	protected function voci_di( int $atto_id, array $filtri = array() ): array {
		$voci = conformita_core_voci_registro( array( 'contenuto' => $atto_id ) + $filtri );

		$this->assertIsArray( $voci );

		// L'istante in testo: due letture della stessa voce danno oggetti diversi con lo stesso valore.
		foreach ( $voci as $indice => $voce ) {
			$voci[ $indice ]['istante'] = $voce['istante']->format( 'Y-m-d H:i:s' );
		}

		return $voci;
	}

	/**
	 * La fotografia intera dell'atto: riga, voci, dati, allegati, disco, registro e numero, letti grezzi.
	 *
	 * Fuori dalla fotografia `_edit_last` e `_edit_lock`, che dicono chi ha
	 * avuto la schermata aperta e non com'e' l'atto.
	 *
	 * @param int  $atto_id Atto.
	 * @param bool $numero  Se il numero di repertorio entra nella fotografia.
	 * @return array<string, mixed>
	 */
	protected function fotografia( int $atto_id, bool $numero = true ): array {
		global $wpdb;

		clean_post_cache( $atto_id );
		wp_cache_flush();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: si legge cio' che c'e' davvero nella banca dati.
		$riga     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $atto_id ), ARRAY_A );
		$dati     = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key NOT IN ( '_edit_last', '_edit_lock' ) ORDER BY meta_id", $atto_id ), ARRAY_A );
		$allegati = $wpdb->get_results( "SELECT ID, post_parent, post_status FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID", ARRAY_A );
		// phpcs:enable

		$foto = array(
			'riga'     => $riga,
			'tipi'     => wp_get_object_terms( $atto_id, TASSONOMIA_TIPO_ATTO, array( 'fields' => 'ids' ) ),
			'organi'   => wp_get_object_terms( $atto_id, TASSONOMIA_ORGANO, array( 'fields' => 'ids' ) ),
			'dati'     => $dati,
			'allegati' => $allegati,
			'disco'    => $this->documenti_sul_disco(),
			'registro' => $this->voci_di( $atto_id ),
		);

		if ( $numero ) {
			$foto['numero'] = Repertorio::di( $atto_id );
		}

		return $foto;
	}

	/**
	 * I messaggi di un rifiuto, tutti insieme.
	 *
	 * @param mixed $esito Esito.
	 * @return string
	 */
	protected function messaggi( $esito ): string {
		$this->assertWPError( $esito, 'Il passaggio deve essere rifiutato.' );

		return implode( ' ', $esito->get_error_messages() );
	}
}
