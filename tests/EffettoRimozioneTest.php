<?php
/**
 * L'effetto della rimozione anticipata sul periodo: righe A-243..A-248 (ALBO-22, ALBO-10).
 *
 * Gli atti si portano a pubblicato e si tolgono dall'albo con i passaggi veri,
 * con l'orologio fissato. Prima e dopo ogni dichiarazione la fotografia
 * completa dell'atto, registro compreso: la dichiarazione aggiunge una voce e
 * nient'altro. Ogni rifiuto ha accanto il suo controllo positivo.
 *
 * I valori attesi sono scritti per esteso: 1 ottobre 2041 alle 9:30 piu' 15
 * giorni fa fine il 16 ottobre; la defissione avviene il 5 ottobre alle
 * 10:00.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\ChiusuraPubblicazione;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Permessi;
use AlboPretorioPa\Repertorio;
use AlboPretorioPa\Rifiuti;
use AlboPretorioPa\RiquadroPassaggi;

use const AlboPretorioPa\SEZIONE;
use const AlboPretorioPa\TIPO;

/**
 * Effetto della rimozione: due valori, nessuno scelto, una volta sola, legato alla defissione.
 */
class EffettoRimozioneTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * L'istante della defissione.
	 */
	private const ISTANTE_DEFISSIONE = '2041-10-05 10:00:00';

	/**
	 * La causa della defissione.
	 */
	private const CAUSA = 'dati_inesatti';

	/**
	 * Il motivo della defissione.
	 */
	private const MOTIVO = 'Il nome del destinatario e\' sbagliato: si ripubblica corretto.';

	/**
	 * Il guasto attivo sulle istruzioni, o nullo.
	 *
	 * @var callable|null
	 */
	private $guasto = null;

	/**
	 * Assetto comune, e l'aggancio che guasta le istruzioni.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();

		add_filter( 'query', array( $this, 'guasta' ) );
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'query', array( $this, 'guasta' ) );

		$this->guasto = null;
		$wpdb->suppress_errors( false );

		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Sostituisce con un'istruzione che fallisce quelle che il guasto attivo riconosce.
	 *
	 * @param string $istruzione Istruzione.
	 * @return string
	 */
	public function guasta( $istruzione ) {
		if ( null !== $this->guasto && ( $this->guasto )( (string) $istruzione ) ) {
			return 'SELECT guasto_forzato FROM tabella_che_non_esiste';
		}

		return $istruzione;
	}

	/**
	 * Attiva un guasto sulle istruzioni che contengono tutti i pezzi indicati.
	 *
	 * @param array<int, string> $pezzi Pezzi dell'istruzione.
	 */
	private function guasta_istruzioni( array $pezzi ): void {
		global $wpdb;

		$wpdb->suppress_errors( true );

		$this->guasto = static function ( string $istruzione ) use ( $pezzi ): bool {
			foreach ( $pezzi as $pezzo ) {
				if ( false === strpos( $istruzione, $pezzo ) ) {
					return false;
				}
			}

			return true;
		};
	}

	/**
	 * Toglie il guasto.
	 */
	private function ripara(): void {
		global $wpdb;

		$this->guasto = null;
		$wpdb->suppress_errors( false );
	}

	/**
	 * Un atto pubblicato con il passaggio vero, poi l'orologio sull'istante della defissione.
	 *
	 * @param string $istante Data e ora nel fuso del sito.
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function pubblicato_il( string $istante = '2041-10-01 09:30:00', string $oggetto = 'Atto di prova' ): int {
		$this->fissa_orologi( $istante );

		$id    = $this->atto_in_verifica( 'normativo', array( 'post_title' => $oggetto ) );
		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->fissa_orologi( self::ISTANTE_DEFISSIONE );

		return $id;
	}

	/**
	 * Un atto pubblicato e tolto dall'albo, senza dichiarazione.
	 *
	 * @param string $oggetto Oggetto.
	 * @return int
	 */
	private function defisso( string $oggetto = 'Atto tolto di prova' ): int {
		$id   = $this->pubblicato_il( '2041-10-01 09:30:00', $oggetto );
		$voce = $this->defiggi( $id, $this->pubblicatore );

		$this->assertIsInt( $voce, is_wp_error( $voce ) ? $voce->get_error_message() : '' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), 'Precondizione: l\'atto e\' tolto dall\'albo.' );

		return $id;
	}

	/**
	 * Toglie dall'albo da codice, come l'utente indicato.
	 *
	 * @param int   $atto_id Atto.
	 * @param int   $utente  Utente.
	 * @param mixed $effetto Effetto, o nullo.
	 * @return mixed
	 */
	private function defiggi( int $atto_id, int $utente, $effetto = null ) {
		return $this->come(
			$utente,
			static function () use ( $atto_id, $effetto ) {
				return albo_pretorio_defiggi( $atto_id, self::CAUSA, self::MOTIVO, $effetto );
			}
		);
	}

	/**
	 * Dichiara da codice, come l'utente indicato.
	 *
	 * @param int   $atto_id Atto.
	 * @param int   $utente  Utente.
	 * @param mixed $effetto Effetto.
	 * @return mixed
	 */
	private function dichiara( int $atto_id, int $utente, $effetto ) {
		return $this->come(
			$utente,
			static function () use ( $atto_id, $effetto ) {
				return albo_pretorio_dichiara_effetto( $atto_id, $effetto );
			}
		);
	}

	/**
	 * Annulla da codice, come chi pubblica.
	 *
	 * @param int $atto_id Atto.
	 */
	private function annulla( int $atto_id ): void {
		$voce = $this->come(
			$this->pubblicatore,
			static function () use ( $atto_id ) {
				return albo_pretorio_annulla( $atto_id, 'Atto annullato in autotutela.' );
			}
		);

		$this->assertIsInt( $voce, is_wp_error( $voce ) ? $voce->get_error_message() : '' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $atto_id ), 'Precondizione: l\'atto e\' annullato.' );
	}

	/**
	 * I codici di un rifiuto.
	 *
	 * @param mixed $esito Esito.
	 * @return array<int, string>
	 */
	private function codici( $esito ): array {
		$this->assertWPError( $esito, 'La richiesta deve essere rifiutata.' );

		return $esito->get_error_codes();
	}

	/**
	 * Le voci dell'effetto di un atto.
	 *
	 * @param int $atto_id Atto.
	 * @return array<int, array<string, mixed>>
	 */
	private function effetti( int $atto_id ): array {
		return $this->voci_di(
			$atto_id,
			array(
				'sezione' => SEZIONE,
				'azione'  => Passaggi::AZIONE_EFFETTO,
			)
		);
	}

	/**
	 * La dichiarazione ha aggiunto una sola voce, e nient'altro dell'atto e' cambiato.
	 *
	 * @param array<string, mixed> $prima   Fotografia prima.
	 * @param int                  $atto_id Atto.
	 * @param string               $caso    Caso, per i messaggi.
	 */
	private function solo_una_voce( array $prima, int $atto_id, string $caso ): void {
		$dopo = $this->fotografia( $atto_id );

		$this->assertCount( count( $prima['registro'] ) + 1, $dopo['registro'], $caso . ': una sola voce in piu\'.' );
		$this->assertSame( $prima['registro'], array_slice( $dopo['registro'], 0, count( $prima['registro'] ) ), $caso . ': le voci di prima sono identiche per intero.' );

		unset( $prima['registro'], $dopo['registro'] );

		$this->assertSame( $prima, $dopo, $caso . ': l\'atto e\' identico, data di inizio compresa.' );
	}

	/**
	 * La defissione ha cambiato solo cio' che le e' proprio: stato, fine e voci nuove in coda.
	 *
	 * Le date di inizio, gli altri dati, le voci di prima e tutto il resto
	 * della fotografia sono identici.
	 *
	 * @param array<string, mixed> $prima   Fotografia prima.
	 * @param int                  $atto_id Atto.
	 * @param string               $caso    Caso, per i messaggi.
	 */
	private function solo_la_defissione( array $prima, int $atto_id, string $caso ): void {
		$dopo   = $this->fotografia( $atto_id );
		$chiave = conformita_core_chiave_fine_pubblicazione();

		$this->assertSame( $prima['riga']['post_date'], $dopo['riga']['post_date'], $caso . ': la data di inizio resta.' );
		$this->assertSame( $prima['riga']['post_date_gmt'], $dopo['riga']['post_date_gmt'], $caso . ': anche nell\'ora universale.' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, $dopo['riga']['post_status'], $caso . ': defisso.' );
		$this->assertSame( $prima['registro'], array_slice( $dopo['registro'], 0, count( $prima['registro'] ) ), $caso . ': le voci di prima sono identiche per intero.' );

		foreach ( array( 'post_status', 'post_modified', 'post_modified_gmt' ) as $campo ) {
			unset( $prima['riga'][ $campo ], $dopo['riga'][ $campo ] );
		}

		$fine = static function ( array $dati ) use ( $chiave ): array {
			return array_values(
				array_filter(
					$dati,
					static function ( array $dato ) use ( $chiave ): bool {
						return $chiave !== $dato['meta_key'];
					}
				)
			);
		};

		$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $atto_id ), $caso . ': la fine e\' il giorno prima.' );
		$prima['dati'] = $fine( $prima['dati'] );
		$dopo['dati']  = $fine( $dopo['dati'] );

		unset( $prima['registro'], $dopo['registro'] );

		$this->assertSame( $prima, $dopo, $caso . ': il resto dell\'atto e\' identico.' );
	}

	/**
	 * I motivi del rifiuto lasciati dal riquadro all'utente.
	 *
	 * @param int $atto_id Atto.
	 * @param int $utente  Utente.
	 * @return array<string, string>
	 */
	private function rifiuti_del_riquadro( int $atto_id, int $utente ): array {
		return $this->come(
			$utente,
			static function () use ( $atto_id ) {
				return Rifiuti::preleva_per_utente( $atto_id );
			}
		);
	}

	/**
	 * Quello che il riquadro disegna per l'utente indicato.
	 *
	 * @param int $atto_id Atto.
	 * @param int $utente  Utente.
	 * @return string
	 */
	private function riquadro( int $atto_id, int $utente ): string {
		return $this->come(
			$utente,
			static function () use ( $atto_id ) {
				clean_post_cache( $atto_id );

				ob_start();
				RiquadroPassaggi::mostra( get_post( $atto_id ) );

				return (string) ob_get_clean();
			}
		);
	}

	/**
	 * Le scelte dell'effetto che il riquadro offre: valore e se e' gia' scelto.
	 *
	 * @param string $html Riquadro.
	 * @return array<string, bool>
	 */
	private function scelte( string $html ): array {
		preg_match_all( '/<input type="radio" id="[^"]*" name="' . preg_quote( RiquadroPassaggi::CAMPO_EFFETTO, '/' ) . '" value="([^"]*)"([^>]*)\/>/', $html, $trovate, PREG_SET_ORDER );

		$scelte = array();

		foreach ( $trovate as $trovata ) {
			$scelte[ $trovata[1] ] = false !== strpos( $trovata[2], 'checked' );
		}

		return $scelte;
	}

	/**
	 * A-243: la dichiarazione insieme alla defissione, da codice e dal riquadro; tutte e due o nessuna.
	 */
	public function test_a243_insieme_alla_defissione(): void {
		$conta = static function (): int {
			global $wpdb;

			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", TIPO ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: tutti gli atti, in ogni stato.
		};

		$da_codice   = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto da codice' );
		$da_riquadro = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto dal riquadro' );
		$atti        = $conta();

		// Un valore fuori dai due rifiuta la defissione per intero.
		$prima = $this->fotografia( $da_codice );
		$this->assertSame( array( 'albo_effetto_non_ammesso' ), $this->codici( $this->defiggi( $da_codice, $this->pubblicatore, 'forse' ) ), 'Valore fuori dai due.' );
		$this->assertSame( $prima, $this->fotografia( $da_codice ), 'Valore fuori dai due: l\'atto resta pubblicato e identico.' );

		// La voce della dichiarazione non si scrive: la defissione e' annullata.
		global $wpdb;

		$this->guasta_istruzioni( array( 'INSERT INTO `' . $wpdb->prefix . 'conformita_core_registro`', "'" . Passaggi::AZIONE_EFFETTO . "'" ) );
		$esito = $this->defiggi( $da_codice, $this->pubblicatore, 'va_rifatta' );
		$this->ripara();

		$this->assertContains( 'albo_atto_non_defisso', $this->codici( $esito ), 'Voce della dichiarazione non scritta: defissione rifiutata.' );
		$this->assertSame( $prima, $this->fotografia( $da_codice ), 'Voce non scritta: tutte e due o nessuna.' );

		// Da codice.
		$voce = $this->defiggi( $da_codice, $this->pubblicatore, 'va_rifatta' );
		$this->assertIsInt( $voce, is_wp_error( $voce ) ? $voce->get_error_message() : '' );
		$this->solo_la_defissione( $prima, $da_codice, 'Da codice' );

		$numero  = Repertorio::di( $da_codice );
		$effetti = $this->effetti( $da_codice );

		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $da_codice ) );
		$this->assertCount( 1, $effetti, 'Una voce dell\'effetto.' );
		$this->assertSame(
			array(
				'effetto' => 'va_rifatta',
				'anno'    => $numero['anno'],
				'numero'  => $numero['numero'],
			),
			$effetti[0]['dettagli']
		);
		$this->assertNull( $effetti[0]['motivazione'], 'Senza un perche\'.' );
		$this->assertSame( $this->pubblicatore, $effetti[0]['utente'], 'Chi.' );
		$this->assertSame( '2041-10-05 08:00:00', $effetti[0]['istante'], 'Quando, nell\'ora universale.' );

		$defissione = $this->voci_di( $da_codice, array( 'azione' => Passaggi::AZIONE_DEFISSIONE ) );
		$this->assertCount( 1, $defissione );
		$this->assertLessThan( $effetti[0]['id'], $defissione[0]['id'], 'La dichiarazione segue la voce della defissione.' );

		// Dal riquadro.
		$prima = $this->fotografia( $da_riquadro );
		$this->invia_riquadro(
			$da_riquadro,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO         => 'defiggi',
				RiquadroPassaggi::CAMPO_CAUSA             => self::CAUSA,
				RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => self::MOTIVO,
				RiquadroPassaggi::CAMPO_EFFETTO           => 'vale_periodo_trascorso',
			)
		);

		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $da_riquadro ), 'Dal riquadro: tolto.' );
		$this->solo_la_defissione( $prima, $da_riquadro, 'Dal riquadro' );
		$this->assertSame( 'vale_periodo_trascorso', $this->effetti( $da_riquadro )[0]['dettagli']['effetto'] ?? null, 'Dal riquadro: dichiarato.' );
		$this->assertSame( $atti, $conta(), '"Va rifatta" non crea nessun atto.' );
	}

	/**
	 * A-244: dopo la defissione, dal riquadro senza scelta, poi una volta sola, poi con campi estranei.
	 */
	public function test_a244_dopo_la_defissione(): void {
		$id = $this->pubblicato_il();

		// La defissione dal riquadro: osservato prima di inviare, inviato senza scegliere.
		$html = $this->riquadro( $id, $this->pubblicatore );
		$this->assertStringContainsString( 'value="defiggi"', $html, 'Precondizione: il modulo della defissione.' );
		$this->assertSame(
			array(
				'vale_periodo_trascorso' => false,
				'va_rifatta'             => false,
			),
			$this->scelte( $html ),
			'Il modulo della defissione offre i due valori, nessuno scelto.'
		);

		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO         => 'defiggi',
				RiquadroPassaggi::CAMPO_CAUSA             => self::CAUSA,
				RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => self::MOTIVO,
			)
		);

		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), 'Senza scelta la defissione riesce.' );
		$this->assertTrue( conformita_core_scaduto( $id ), 'E l\'atto e\' subito fuori dalla vista.' );
		$this->assertSame( array(), $this->effetti( $id ), 'Nessun effetto nel registro, nemmeno predefinito.' );

		$html = $this->riquadro( $id, $this->pubblicatore );
		$this->assertSame(
			array(
				'vale_periodo_trascorso' => false,
				'va_rifatta'             => false,
			),
			$this->scelte( $html ),
			'Il riquadro dell\'atto tolto offre i due valori, nessuno scelto.'
		);
		$this->assertStringContainsString( 'value="dichiara"', $html, 'Con il pulsante.' );

		// Inviato cosi' com'e': non dichiara niente.
		$prima = $this->fotografia( $id );
		$this->invia_riquadro( $id, $this->pubblicatore, array( RiquadroPassaggi::CAMPO_PASSAGGIO => 'dichiara' ) );

		$this->assertArrayHasKey( 'albo_effetto_mancante', $this->rifiuti_del_riquadro( $id, $this->pubblicatore ), 'Il rifiuto chiede il valore.' );
		$this->assertSame( array(), $this->effetti( $id ), 'Nessun valore nel registro, nemmeno predefinito.' );
		$this->assertSame( $prima, $this->fotografia( $id ), 'Niente cambia.' );
		$this->assertTrue( conformita_core_scaduto( $id ), 'Fra la defissione e la dichiarazione l\'atto e\' fuori dalla vista.' );

		// Giorni dopo, dal riquadro.
		$this->fissa_orologi( '2041-10-09 11:00:00' );
		$this->invia_riquadro(
			$id,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'dichiara',
				RiquadroPassaggi::CAMPO_EFFETTO   => 'vale_periodo_trascorso',
			)
		);

		$this->assertSame( array(), $this->rifiuti_del_riquadro( $id, $this->pubblicatore ), 'Nessun rifiuto.' );
		$this->assertSame( 'vale_periodo_trascorso', $this->effetti( $id )[0]['dettagli']['effetto'] ?? null, 'Dichiarato dal riquadro.' );
		$this->assertSame( '2041-10-09 09:00:00', $this->effetti( $id )[0]['istante'], 'Giorni dopo, nell\'ora universale.' );
		$this->solo_una_voce( $prima, $id, 'Dichiarazione' );
		$this->assertSame( '2041-10-01 09:30:00', get_post( $id )->post_date, 'L\'inizio resta.' );
		$this->assertSame( '2041-10-04', conformita_core_fine_pubblicazione( $id ), 'La fine resta.' );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), 'Lo stato resta.' );

		$dichiarata = $this->fotografia( $id );

		foreach ( array( 'va_rifatta', 'vale_periodo_trascorso' ) as $valore ) {
			$this->assertSame( array( 'albo_effetto_gia_dichiarato' ), $this->codici( $this->dichiara( $id, $this->pubblicatore, $valore ) ), 'Seconda dichiarazione, ' . $valore . '.' );
		}

		$this->assertSame( $dichiarata, $this->fotografia( $id ), 'Resta la prima.' );

		// Il riquadro mostra il valore, senza la domanda.
		$html = $this->riquadro( $id, $this->pubblicatore );
		$this->assertSame( array(), $this->scelte( $html ), 'La domanda non c\'e\' piu\'.' );
		$this->assertStringContainsString( '<dt>Effetto dichiarato</dt><dd>La pubblicazione vale per il periodo trascorso</dd>', $html );
		$this->assertStringNotContainsString( 'value="dichiara"', $html );

		// Dal riquadro, con un campo estraneo per volta.
		foreach ( array(
			'data'           => $this->campi_data( '2041-10-01 09:30:00', '2041-09-01 08:00:00' ),
			'oggetto'        => array( 'post_title' => 'Oggetto cambiato' ),
			'documento'      => array( 'albo_pretorio_documento_principale' => '999' ),
			'numero'         => array( 'albo_pretorio_numero' => '999' ),
			'durata propria' => array( 'albo_pretorio_durata_propria' => '5' ),
		) as $campo => $estranei ) {
			$altro = $this->defisso( 'Atto con ' . $campo );
			$prima = $this->fotografia( $altro );

			$this->invia_riquadro(
				$altro,
				$this->pubblicatore,
				array_merge(
					$estranei,
					array(
						RiquadroPassaggi::CAMPO_PASSAGGIO => 'dichiara',
						RiquadroPassaggi::CAMPO_EFFETTO   => 'va_rifatta',
					)
				)
			);

			$this->assertCount( 1, $this->effetti( $altro ), $campo . ': dichiarato.' );
			$this->solo_una_voce( $prima, $altro, 'Con ' . $campo );
		}
	}

	/**
	 * A-245: tentativi indipendenti, una condizione per volta, e il controllo positivo per ultimo.
	 */
	public function test_a245_chi_e_su_che_cosa(): void {
		$id    = $this->defisso();
		$prima = $this->fotografia( $id );

		$senza_defiggere = $this->utente_con( array_values( array_diff( Permessi::CHIAVI_PUBBLICAZIONE, array( Permessi::CHIAVE_DEFISSIONE ) ) ) );
		$solo_defiggere  = $this->utente_con( array( Permessi::CHIAVE_DEFISSIONE ) );

		$this->assertTrue( user_can( $senza_defiggere, (string) Permessi::mappa()['publish_posts'] ), 'Precondizione: pubblica.' );
		$this->assertSame( array( 'albo_effetto_non_permesso' ), $this->codici( $this->dichiara( $id, $senza_defiggere, 'va_rifatta' ) ), 'Chi pubblica senza il permesso di defiggere.' );
		$this->assertSame( array( 'albo_effetto_non_permesso' ), $this->codici( $this->dichiara( $id, $this->redattore, 'va_rifatta' ) ), 'Chi redige.' );

		foreach ( array(
			'valore fuori dai due' => array( 'forse', 'albo_effetto_non_ammesso' ),
			'senza valore'         => array( '', 'albo_effetto_mancante' ),
			'nullo'                => array( null, 'albo_effetto_mancante' ),
			'un elenco'            => array( array( 'va_rifatta' ), 'albo_effetto_non_ammesso' ),
			'un numero'            => array( 1, 'albo_effetto_non_ammesso' ),
			'maiuscole'            => array( 'VA_RIFATTA', 'albo_effetto_non_ammesso' ),
		) as $caso => list( $valore, $codice ) ) {
			$this->assertSame( array( $codice ), $this->codici( $this->dichiara( $id, $solo_defiggere, $valore ) ), $caso . '.' );
		}

		$this->assertSame( $prima, $this->fotografia( $id ), 'Il primo atto resta identico.' );

		// Gli atti senza defissione anticipata.
		$riscritto = $this->pubblicato_il( '2041-09-01 09:30:00', 'Atto scaduto e registrato' );
		$this->scavalca_barriera(
			static function () use ( $riscritto ) {
				global $wpdb;

				$wpdb->update( $wpdb->posts, array( 'post_status' => ChiusuraPubblicazione::DEFISSO ), array( 'ID' => $riscritto ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prova: lo stato come lo lascera' il compito pianificato.
			}
		);
		clean_post_cache( $riscritto );
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $riscritto ), 'Precondizione: defisso per scadenza naturale.' );
		$this->assertSame( array(), $this->voci_di( $riscritto, array( 'azione' => Passaggi::AZIONE_DEFISSIONE ) ), 'Precondizione: nessuna defissione anticipata.' );

		$html = $this->riquadro( $riscritto, $solo_defiggere );
		$this->assertSame( array(), $this->scelte( $html ), 'Defisso per scadenza naturale: il riquadro non offre la domanda.' );
		$this->assertStringNotContainsString( 'value="dichiara"', $html, 'Ne\' il pulsante.' );
		$this->assertStringNotContainsString( 'Effetto della rimozione', $html, 'Ne\' l\'effetto.' );
		$this->assertStringNotContainsString( 'prima della scadenza</strong>', $html, 'Ne\' una defissione anticipata.' );

		$annullato = $this->pubblicato_il( '2041-10-02 09:30:00', 'Atto annullato senza defissione' );
		$this->annulla( $annullato );

		$pubblicato  = $this->pubblicato_il( '2041-10-03 09:30:00', 'Atto in corso' );
		$bozza       = $this->atto_completo();
		$in_verifica = $this->atto_in_verifica();
		$articolo    = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		foreach ( array(
			'defisso per scadenza naturale' => $riscritto,
			'annullato senza defissione'    => $annullato,
			'pubblicato'                    => $pubblicato,
			'bozza'                         => $bozza,
			'in verifica'                   => $in_verifica,
		) as $caso => $atto ) {
			$this->assertSame( array( 'albo_effetto_senza_defissione' ), $this->codici( $this->dichiara( $atto, $solo_defiggere, 'va_rifatta' ) ), $caso . '.' );
			$this->assertSame( array(), $this->effetti( $atto ), $caso . ': nessuna voce.' );
		}

		$this->assertSame( array( 'albo_non_un_atto' ), $this->codici( $this->dichiara( $articolo, $solo_defiggere, 'va_rifatta' ) ), 'Un articolo.' );
		$this->assertSame( array( 'albo_non_un_atto' ), $this->codici( $this->dichiara( 999999999, $solo_defiggere, 'va_rifatta' ) ), 'Un numero mai dato.' );

		// Controllo positivo: chi ha il permesso di defiggere e non quello di pubblicare.
		$prima = $this->fotografia( $id );
		$this->assertFalse( user_can( $solo_defiggere, (string) Permessi::mappa()['publish_posts'] ), 'Precondizione: non pubblica.' );
		$this->assertIsInt( $this->dichiara( $id, $solo_defiggere, 'va_rifatta' ), 'Chi ha il permesso di defiggere dichiara.' );
		$this->solo_una_voce( $prima, $id, 'Controllo positivo' );
	}

	/**
	 * A-245, dal riquadro: valori manipolati, rifiutati senza scritture con tutti e due i pulsanti.
	 *
	 * Un valore che una ripulitura renderebbe valido, o renderebbe nessuna
	 * scelta, non e' una scelta di chi dichiara: e' rifiutato, e la
	 * defissione con il campo assente riesce.
	 */
	public function test_a245_valori_manipolati_dal_riquadro(): void {
		$manipolati = array(
			'maiuscole'            => 'VA_RIFATTA',
			'spazio in mezzo'      => 'va_ri fatta',
			'carattere estraneo'   => 'va_rifatta!',
			'solo segni'           => '!!!',
			'testo vuoto'          => '',
			'un elenco'            => array( 'va_rifatta' ),
			'un elenco con chiave' => array( 'effetto' => 'vale_periodo_trascorso' ),
		);

		$tolto      = $this->defisso();
		$pubblicato = $this->pubblicato_il( '2041-10-02 09:30:00', 'Atto da togliere' );

		foreach ( $manipolati as $caso => $valore ) {
			foreach ( array(
				'dichiara' => array( $tolto, array() ),
				'defiggi'  => array(
					$pubblicato,
					array(
						RiquadroPassaggi::CAMPO_CAUSA => self::CAUSA,
						RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => self::MOTIVO,
					),
				),
			) as $pulsante => list( $atto, $altri ) ) {
				$prima = $this->fotografia( $atto );

				$this->invia_riquadro(
					$atto,
					$this->pubblicatore,
					array_merge(
						$altri,
						array(
							RiquadroPassaggi::CAMPO_PASSAGGIO => $pulsante,
							RiquadroPassaggi::CAMPO_EFFETTO   => $valore,
						)
					)
				);

				$atteso = '' === $valore && 'dichiara' === $pulsante ? 'albo_effetto_mancante' : 'albo_effetto_non_ammesso';
				$this->assertArrayHasKey( $atteso, $this->rifiuti_del_riquadro( $atto, $this->pubblicatore ), $pulsante . ', ' . $caso . ': rifiutato.' );
				$this->assertSame( $prima, $this->fotografia( $atto ), $pulsante . ', ' . $caso . ': niente scritto.' );
			}
		}

		$this->assertSame( 'publish', get_post_status( $pubblicato ), 'Il secondo atto resta pubblicato.' );
		$this->assertSame( array(), $this->effetti( $tolto ), 'Nessuna dichiarazione sul primo.' );

		// Controllo positivo: con il campo assente la defissione riesce, con un valore ammesso la dichiarazione.
		$this->invia_riquadro(
			$pubblicato,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO         => 'defiggi',
				RiquadroPassaggi::CAMPO_CAUSA             => self::CAUSA,
				RiquadroPassaggi::CAMPO_MOTIVO_DEFISSIONE => self::MOTIVO,
			)
		);
		$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $pubblicato ), 'Campo assente: la defissione riesce.' );
		$this->assertSame( array(), $this->effetti( $pubblicato ), 'Campo assente: nessuna dichiarazione.' );

		$this->invia_riquadro(
			$tolto,
			$this->pubblicatore,
			array(
				RiquadroPassaggi::CAMPO_PASSAGGIO => 'dichiara',
				RiquadroPassaggi::CAMPO_EFFETTO   => 'va_rifatta',
			)
		);
		$this->assertSame( 'va_rifatta', $this->effetti( $tolto )[0]['dettagli']['effetto'] ?? null, 'Valore ammesso: dichiarato.' );
	}

	/**
	 * A-246: la dichiarazione resta dopo l'annullamento, e si rende anche sull'annullato dopo la defissione.
	 */
	public function test_a246_dichiarazione_e_annullamento(): void {
		$primo   = $this->defisso( 'Primo atto' );
		$secondo = $this->defisso( 'Secondo atto' );

		$this->assertIsInt( $this->dichiara( $primo, $this->pubblicatore, 'va_rifatta' ) );

		$effetto = $this->effetti( $primo );

		$this->annulla( $primo );
		$this->assertSame( $effetto, $this->effetti( $primo ), 'Dopo l\'annullamento la dichiarazione e\' identica.' );

		$this->annulla( $secondo );

		$html = $this->riquadro( $secondo, $this->pubblicatore );
		$this->assertSame(
			array(
				'vale_periodo_trascorso' => false,
				'va_rifatta'             => false,
			),
			$this->scelte( $html ),
			'L\'annullato dopo una defissione offre la domanda.'
		);

		$prima = $this->fotografia( $secondo );
		$this->assertIsInt( $this->dichiara( $secondo, $this->pubblicatore, 'vale_periodo_trascorso' ), 'Sull\'annullato la dichiarazione riesce.' );
		$this->solo_una_voce( $prima, $secondo, 'Annullato' );
		$this->assertSame( ChiusuraPubblicazione::ANNULLATO, get_post_status( $secondo ), 'Resta annullato.' );
		$this->assertStringContainsString( '<dt>Effetto dichiarato</dt><dd>La pubblicazione vale per il periodo trascorso</dd>', $this->riquadro( $secondo, $this->pubblicatore ) );

		// L'annullato senza defissione non offre la domanda.
		$annullato = $this->pubblicato_il( '2041-10-02 09:30:00', 'Annullato senza defissione' );
		$this->annulla( $annullato );
		$this->assertStringNotContainsString( RiquadroPassaggi::CAMPO_EFFETTO, $this->riquadro( $annullato, $this->pubblicatore ), 'Nessuna domanda senza defissione.' );
	}

	/**
	 * A-247: due dichiarazioni nello stesso istante, ne entra una sola.
	 */
	public function test_a247_due_nello_stesso_istante(): void {
		global $wpdb;

		$id      = $this->defisso();
		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$numero  = Repertorio::di( $id );
		$entrata = null;

		$intromette = static function ( $istruzione ) use ( $tabella, $id, $numero, &$entrata ) {
			if ( null !== $entrata || ! is_string( $istruzione ) || 0 !== strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) || false === strpos( $istruzione, "'" . Passaggi::AZIONE_EFFETTO . "'" ) ) {
				return $istruzione;
			}

			$entrata = false;
			$entrata = conformita_core_registra_voce(
				array(
					'sezione'   => SEZIONE,
					'azione'    => Passaggi::AZIONE_EFFETTO,
					'contenuto' => $id,
					'dettagli'  => array(
						'effetto' => 'va_rifatta',
						'anno'    => (int) $numero['anno'],
						'numero'  => (int) $numero['numero'],
					),
					'chiave'    => SEZIONE . ':' . Passaggi::AZIONE_EFFETTO . ':' . $id,
				)
			);

			return $istruzione;
		};

		add_filter( 'query', $intromette );

		try {
			$wpdb->suppress_errors( true );
			$esito = $this->dichiara( $id, $this->pubblicatore, 'vale_periodo_trascorso' );
		} finally {
			$wpdb->suppress_errors( false );
			remove_filter( 'query', $intromette );
		}

		$this->assertIsInt( $entrata, 'Precondizione: l\'altra richiesta ha scritto la sua.' );
		$this->assertSame( array( 'albo_effetto_gia_dichiarato' ), $this->codici( $esito ), 'La seconda e\' rifiutata.' );

		$effetti = $this->effetti( $id );

		$this->assertCount( 1, $effetti, 'Ne entra una sola.' );
		$this->assertSame( 'va_rifatta', $effetti[0]['dettagli']['effetto'], 'Quella entrata per prima.' );
	}

	/**
	 * A-247, voce scritta da altri con un'altra chiave: la dichiarazione insieme alla defissione e' rifiutata per intero.
	 *
	 * Il vincolo sulla chiave non la vede. Due casi: la voce c'e' gia' prima
	 * della defissione, oppure la scrive un aggancio mentre la defissione
	 * scrive la sua. La defissione con la dichiarazione e' annullata per
	 * intero; senza dichiarazione riesce.
	 */
	public function test_a247_voce_scritta_da_altri(): void {
		global $wpdb;

		$tabella = $wpdb->prefix . 'conformita_core_registro';
		$scrivi  = static function ( int $id, $chiave ) {
			$numero = Repertorio::di( $id );
			$voce   = array(
				'sezione'   => SEZIONE,
				'azione'    => Passaggi::AZIONE_EFFETTO,
				'contenuto' => $id,
				'dettagli'  => array(
					'effetto' => 'vale_periodo_trascorso',
					'anno'    => (int) $numero['anno'],
					'numero'  => (int) $numero['numero'],
				),
			);

			if ( null !== $chiave ) {
				$voce['chiave'] = $chiave . ':' . $id;
			}

			return conformita_core_registra_voce( $voce );
		};

		foreach ( array(
			'gia\' presente, senza chiave'         => array( 'prima', null ),
			'gia\' presente, con un\'altra chiave' => array( 'prima', 'altro:effetto' ),
			'scritta durante la defissione'        => array( 'durante', 'altro:effetto' ),
		) as $caso => list( $quando, $chiave ) ) {
			$id = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto ' . $caso );

			if ( 'prima' === $quando ) {
				$this->assertIsInt( $scrivi( $id, $chiave ), $caso . ': precondizione, la voce dell\'altro.' );
			}

			$prima   = $this->fotografia( $id );
			$entrata = null;

			$intromette = static function ( $istruzione ) use ( $tabella, $id, $chiave, $quando, $scrivi, &$entrata ) {
				if ( 'durante' !== $quando || null !== $entrata || ! is_string( $istruzione ) || 0 !== strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) || false === strpos( $istruzione, "'" . Passaggi::AZIONE_EFFETTO . "'" ) ) {
					return $istruzione;
				}

				$entrata = false;
				$entrata = $scrivi( $id, $chiave );

				return $istruzione;
			};

			add_filter( 'query', $intromette );

			try {
				$esito = $this->defiggi( $id, $this->pubblicatore, 'va_rifatta' );
			} finally {
				remove_filter( 'query', $intromette );
			}

			if ( 'durante' === $quando ) {
				$this->assertIsInt( $entrata, $caso . ': precondizione, l\'aggancio ha scritto.' );
			}

			$codici = $this->codici( $esito );
			$this->assertContains( 'albo_atto_non_defisso', $codici, $caso . ': defissione rifiutata.' );
			$this->assertContains( 'albo_effetto_gia_dichiarato', $codici, $caso . ': perche\' l\'effetto c\'e\' gia\'.' );
			clean_post_cache( $id );
			$this->assertSame( 'publish', get_post_status( $id ), $caso . ': l\'atto resta pubblicato.' );
			$this->assertSame( array(), $this->voci_di( $id, array( 'azione' => Passaggi::AZIONE_DEFISSIONE ) ), $caso . ': nessuna defissione.' );

			if ( 'prima' === $quando ) {
				$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': l\'atto e il registro sono quelli di prima.' );
			}

			$effetti = $this->effetti( $id );
			$this->assertCount( 'durante' === $quando ? 0 : 1, $effetti, $caso . ': la nostra voce non e\' entrata.' );

			// Controllo positivo: senza dichiarazione la rimozione riesce lo stesso.
			$this->assertIsInt( $this->defiggi( $id, $this->pubblicatore ), $caso . ': senza dichiarazione l\'atto si toglie.' );
			$this->assertSame( ChiusuraPubblicazione::DEFISSO, get_post_status( $id ), $caso . ': tolto.' );
		}
	}

	/**
	 * A-247, voce scritta da altri mentre si dichiara dopo la defissione: la propria non entra.
	 *
	 * Un aggancio scrive, appena prima della dichiarazione, una voce della
	 * stessa azione senza chiave o con un'altra chiave. La dichiarazione e'
	 * rifiutata e la sua voce annullata.
	 */
	public function test_a247_voce_di_altri_alla_dichiarazione(): void {
		global $wpdb;

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		foreach ( array(
			'senza chiave'         => null,
			'con un\'altra chiave' => 'altro:effetto',
		) as $caso => $chiave ) {
			$id      = $this->defisso( 'Atto ' . $caso );
			$numero  = Repertorio::di( $id );
			$entrata = null;

			$intromette = static function ( $istruzione ) use ( $tabella, $id, $numero, $chiave, &$entrata ) {
				if ( null !== $entrata || ! is_string( $istruzione ) || 0 !== strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) || false === strpos( $istruzione, "'" . Passaggi::AZIONE_EFFETTO . "'" ) ) {
					return $istruzione;
				}

				$voce = array(
					'sezione'   => SEZIONE,
					'azione'    => Passaggi::AZIONE_EFFETTO,
					'contenuto' => $id,
					'dettagli'  => array(
						'effetto' => 'vale_periodo_trascorso',
						'anno'    => (int) $numero['anno'],
						'numero'  => (int) $numero['numero'],
					),
				);

				if ( null !== $chiave ) {
					$voce['chiave'] = $chiave . ':' . $id;
				}

				$entrata = false;
				$entrata = conformita_core_registra_voce( $voce );

				return $istruzione;
			};

			add_filter( 'query', $intromette );

			try {
				$esito = $this->dichiara( $id, $this->pubblicatore, 'va_rifatta' );
			} finally {
				remove_filter( 'query', $intromette );
			}

			$this->assertIsInt( $entrata, $caso . ': precondizione, l\'aggancio ha scritto.' );
			$this->assertSame( array( 'albo_effetto_gia_dichiarato' ), $this->codici( $esito ), $caso . ': rifiutata.' );

			foreach ( $this->effetti( $id ) as $voce ) {
				$this->assertNotSame( 'va_rifatta', $voce['dettagli']['effetto'] ?? null, $caso . ': la propria voce non e\' entrata.' );
			}
		}

		// Controllo positivo: senza l'aggancio la dichiarazione riesce.
		$altro = $this->defisso( 'Atto senza aggancio' );
		$prima = $this->fotografia( $altro );
		$this->assertIsInt( $this->dichiara( $altro, $this->pubblicatore, 'va_rifatta' ) );
		$this->solo_una_voce( $prima, $altro, 'Controllo positivo' );
	}

	/**
	 * A-248, parte del registro che non risponde: la dichiarazione e' rifiutata, le viste lo dicono.
	 */
	public function test_a248_registro_che_non_risponde(): void {
		global $wpdb;

		$tabella = $wpdb->prefix . 'conformita_core_registro';

		foreach ( array( Passaggi::AZIONE_DEFISSIONE, Passaggi::AZIONE_EFFETTO ) as $azione ) {
			$id    = $this->defisso( 'Atto con la lettura di ' . $azione );
			$prima = $this->fotografia( $id );

			$this->guasta_istruzioni( array( 'SELECT', $tabella, "'" . $azione . "'" ) );
			$esito = $this->dichiara( $id, $this->pubblicatore, 'va_rifatta' );
			$this->ripara();

			$this->assertSame( array( 'albo_registro_non_risponde' ), $this->codici( $esito ), $azione . ': rifiutata.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $azione . ': niente scritto.' );
		}

		$this->guasta_istruzioni( array( 'SELECT', $tabella, "'" . Passaggi::AZIONE_EFFETTO . "'" ) );
		$letto = Passaggi::effetto( $id );
		$html  = $this->riquadro( $id, $this->pubblicatore );
		$this->ripara();

		$this->assertFalse( $letto, 'La lettura dice che non risponde, non che manca.' );
		$this->assertStringContainsString( 'l&#039;effetto dichiarato non si legge in questo momento', $html, 'Il riquadro lo dice.' );
		$this->assertSame( array(), $this->scelte( $html ), 'E non offre la domanda, che forse ha gia\' una risposta.' );

		// Il registro che non annulla, o la verifica che non risponde: la dichiarazione non comincia.
		foreach ( array(
			'registro non transazionale' => static function ( $istruzione ) use ( $tabella ) {
				return str_replace( 't.TABLE_NAME IN (', "t.TABLE_NAME <> '{$tabella}' AND t.TABLE_NAME IN (", (string) $istruzione );
			},
			'verifica che non risponde'  => static function ( $istruzione ) {
				return false !== strpos( (string) $istruzione, 'information_schema.TABLES' ) ? 'SELECT guasto_forzato FROM tabella_che_non_esiste' : $istruzione;
			},
		) as $caso => $rispondi ) {
			$prima    = $this->fotografia( $id );
			$inserite = 0;
			$conta    = static function ( $istruzione ) use ( $tabella, &$inserite ) {
				if ( is_string( $istruzione ) && 0 === strpos( $istruzione, 'INSERT INTO `' . $tabella . '`' ) ) {
					++$inserite;
				}

				return $istruzione;
			};

			$wpdb->suppress_errors( true );
			add_filter( 'query', $rispondi );
			add_filter( 'query', $conta );

			try {
				$esito = $this->dichiara( $id, $this->pubblicatore, 'va_rifatta' );
			} finally {
				remove_filter( 'query', $conta );
				remove_filter( 'query', $rispondi );
				$wpdb->suppress_errors( false );
			}

			$this->assertSame( array( 'albo_tabelle_non_transazionali' ), $this->codici( $esito ), $caso . ': rifiutata.' );
			$this->assertSame( 0, $inserite, $caso . ': prima di scrivere.' );
			$this->assertSame( $prima, $this->fotografia( $id ), $caso . ': niente scritto.' );
		}

		// Controllo positivo: riparato, la dichiarazione riesce.
		$this->assertIsInt( $this->dichiara( $id, $this->pubblicatore, 'va_rifatta' ) );

		// Una seconda voce scritta da altri con un'altra chiave: la lettura non sceglie, le viste lo dicono.
		$numero = Repertorio::di( $id );
		$this->assertIsInt(
			conformita_core_registra_voce(
				array(
					'sezione'   => SEZIONE,
					'azione'    => Passaggi::AZIONE_EFFETTO,
					'contenuto' => $id,
					'dettagli'  => array(
						'effetto' => 'vale_periodo_trascorso',
						'anno'    => (int) $numero['anno'],
						'numero'  => (int) $numero['numero'],
					),
					'chiave'    => 'altro:effetto:' . $id,
				)
			),
			'Precondizione: la voce dell\'altro.'
		);
		$this->assertFalse( Passaggi::effetto( $id ), 'Due voci: la lettura non ne sceglie una.' );

		$html = $this->riquadro( $id, $this->pubblicatore );
		$this->assertStringContainsString( 'l&#039;effetto dichiarato non si legge in questo momento', $html, 'Il riquadro lo dice.' );
		$this->assertSame( array(), $this->scelte( $html ), 'E non offre la domanda.' );

		// L'annullato dopo la defissione, con e senza effetto dichiarato, guastando una lettura per volta.
		$dichiarato = $this->defisso( 'Annullato con effetto' );
		$this->assertIsInt( $this->dichiara( $dichiarato, $this->pubblicatore, 'va_rifatta' ) );
		$this->annulla( $dichiarato );

		$senza = $this->defisso( 'Annullato senza effetto' );
		$this->annulla( $senza );

		$defisso = $this->defisso( 'Defisso senza effetto' );

		foreach ( array(
			'annullato con effetto'   => $dichiarato,
			'annullato senza effetto' => $senza,
			'defisso senza effetto'   => $defisso,
		) as $caso => $atto ) {
			foreach ( array( Passaggi::AZIONE_DEFISSIONE, Passaggi::AZIONE_EFFETTO ) as $azione ) {
				$this->guasta_istruzioni( array( 'SELECT', $tabella, "'" . $azione . "'" ) );
				$html = $this->riquadro( $atto, $this->pubblicatore );
				$this->ripara();

				$this->assertStringContainsString( 'Il registro delle modifiche non risponde', $html, $caso . ', ' . $azione . ': il riquadro lo dice.' );
				$this->assertSame( array(), $this->scelte( $html ), $caso . ', ' . $azione . ': e non offre la domanda.' );
				$this->assertStringNotContainsString( 'Non ancora dichiarato', $html, $caso . ', ' . $azione . ': e non dice che manca.' );
			}
		}

		// Controllo positivo: riparato, il riquadro mostra il valore e la domanda.
		$this->assertStringContainsString( '<dt>Effetto dichiarato</dt><dd>La pubblicazione va rifatta, con un atto nuovo</dd>', $this->riquadro( $dichiarato, $this->pubblicatore ) );
		$this->assertCount( 2, $this->scelte( $this->riquadro( $senza, $this->pubblicatore ) ) );
	}
}
