<?php
/**
 * Il nome del file scaricato: righe A-257, A-258 e A-306 (ALBO-30).
 *
 * Le prove scaricano dal punto pubblico di consegna del meccanismo comune,
 * come farebbe un visitatore dalla scheda dell'atto, e leggono il nome dalla
 * risposta. Gli atti arrivano a pubblicato con il passaggio vero e
 * l'orologio fissato.
 *
 * I valori attesi sono scritti per esteso: 1 ottobre 2041 alle 9:30 piu' 15
 * giorni fa fine il 16 ottobre.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;
use AlboPretorioPa\NomeScaricato;
use AlboPretorioPa\Passaggi;
use AlboPretorioPa\Repertorio;

/**
 * Prove del nome del file scaricato.
 */
class NomeScaricatoTest extends \WP_UnitTestCase {

	use FlussoDiProva;

	/**
	 * Le risposte del punto di consegna nella richiesta in corso.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $consegne = array();

	/**
	 * Assetto comune, pagina dell'albo e consegna osservata.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->prepara_flusso();
		$this->set_permalink_structure( '' );

		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Albo pretorio',
				'post_content' => '[albo_pretorio]',
			)
		);

		\Conformita_Core_Consegna::fissa_emettitore(
			function ( array $risposta ) {
				$this->consegne[] = $risposta;
			}
		);
	}

	/**
	 * Ripulisce.
	 */
	public function tear_down(): void {
		\Conformita_Core_Consegna::azzera_emettitore();
		$this->smonta_flusso();

		parent::tear_down();
	}

	/**
	 * Un atto pubblicato con il passaggio vero, con il principale e gli allegati indicati.
	 *
	 * @param string                $istante  Data e ora nel fuso del sito.
	 * @param string                $oggetto  Oggetto.
	 * @param array<string, string> $allegati Nome del file e contenuto degli allegati ulteriori.
	 * @param array<int, string>    $principale Nome del file e contenuto del documento principale, o vuoto per quello comune.
	 * @return int
	 */
	private function pubblicato_il( string $istante, string $oggetto, array $allegati = array(), array $principale = array() ): int {
		$this->fissa_orologi( $istante );

		$id = $this->atto_completo( 'normativo', array( 'post_title' => $oggetto ) );

		if ( array() !== $principale ) {
			$this->assertIsInt( DocumentiAtto::deposita( $id, $this->file_temporaneo( $principale[0], $principale[1] ), DocumentiAtto::PRINCIPALE, 'percorso_locale' ) );
		}

		foreach ( $allegati as $nome => $contenuto ) {
			$this->assertIsInt( DocumentiAtto::deposita( $id, $this->file_temporaneo( $nome, $contenuto ), DocumentiAtto::ULTERIORE, 'percorso_locale' ) );
		}

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

		$esito = $this->pubblica_da_codice( $id, $this->pubblicatore );

		$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );
		$this->assertSame( 'publish', get_post_status( $id ), 'Precondizione: l\'atto e\' pubblicato.' );

		return $id;
	}

	/**
	 * Scarica un documento come un visitatore: nome proposto e file consegnato.
	 *
	 * @param int $documento Documento.
	 * @return array{nome: string, percorso: string}
	 */
	private function scarica( int $documento ): array {
		$this->consegne = array();
		wp_set_current_user( 0 );

		$this->go_to( (string) conformita_core_indirizzo_consegna( $documento ) );

		$this->assertCount( 1, $this->consegne, 'Precondizione: una risposta del punto di consegna.' );
		$this->assertSame( 200, $this->consegne[0]['stato'] ?? 0, 'Precondizione: il documento si scarica.' );

		$disposizione = (string) ( $this->consegne[0]['intestazioni']['Content-Disposition'] ?? '' );

		$this->assertSame( 1, preg_match( '/; filename="([^"]*)"/', $disposizione, $parti ), 'Precondizione: la risposta propone un nome.' );
		$this->assertStringContainsString( "filename*=UTF-8''" . rawurlencode( $parti[1] ), $disposizione, 'Le due forme del nome dicono la stessa cosa.' );

		return array(
			'nome'     => $parti[1],
			'percorso' => (string) $this->consegne[0]['percorso'],
		);
	}

	/**
	 * A-257: numero di registro e date nel nome, niente oggetto e niente nome originale, contenuto identico.
	 */
	public function test_a257_nome_del_file_scaricato(): void {
		$contenuti = array(
			'principale' => "%PDF-1.4\n% documento principale\n%%EOF\n",
			'primo'      => "%PDF-1.4\n% primo allegato\n%%EOF\n",
			'secondo'    => "%PDF-1.4\n% secondo allegato\n%%EOF\n",
		);

		$atto = $this->pubblicato_il(
			'2041-10-01 09:30:00',
			'Ordinanza di sgombero nei confronti di Mario Rossi',
			array(
				'Rossi-Mario-planimetria.pdf' => $contenuti['primo'],
				'Rossi Mario ricevuta.PDF'    => $contenuti['secondo'],
			),
			array( 'Mario_Rossi_ordinanza_firmata.pdf', $contenuti['principale'] )
		);

		$this->assertSame(
			array(
				'anno'   => 2041,
				'numero' => 1,
			),
			Repertorio::di( $atto ),
			'Precondizione: il primo numero dell\'anno.'
		);
		$this->assertSame( '2041-10-16', conformita_core_fine_pubblicazione( $atto ), 'Precondizione: la fine e\' il 16 ottobre.' );

		$documenti = array(
			'principale' => (int) DocumentiAtto::principale( $atto ),
			'primo'      => DocumentiAtto::ulteriori( $atto )[0],
			'secondo'    => DocumentiAtto::ulteriori( $atto )[1],
		);
		$attesi    = array(
			'principale' => 'albo-2041-0001-dal-01-10-al-16-10.pdf',
			'primo'      => 'albo-2041-0001-dal-01-10-al-16-10-allegato-1.pdf',
			'secondo'    => 'albo-2041-0001-dal-01-10-al-16-10-allegato-2.pdf',
		);

		foreach ( $documenti as $quale => $documento ) {
			$scaricato = $this->scarica( $documento );

			$this->assertSame( $attesi[ $quale ], $scaricato['nome'], $quale . ': il nome proposto.' );

			foreach ( array( 'rossi', 'mario', 'ordinanza', 'sgombero', 'planimetria', 'ricevuta' ) as $parola ) {
				$this->assertStringNotContainsStringIgnoringCase( $parola, $scaricato['nome'], $quale . ': il nome non contiene "' . $parola . '".' );
			}

			$this->assertSame( hash( 'sha256', $contenuti[ $quale ] ), hash_file( 'sha256', $scaricato['percorso'] ), $quale . ': il file consegnato e\' identico a quello depositato.' );
			$this->assertSame( $scaricato['percorso'], get_attached_file( $documento ), $quale . ': si consegna il file conservato, non una copia.' );
		}

		// Il file sul disco conserva il nome che aveva: a cambiare e' solo quello proposto.
		$this->assertStringContainsStringIgnoringCase( 'rossi', wp_basename( (string) get_attached_file( $documenti['primo'] ) ), 'Controllo positivo: sul disco il nome originale c\'e\' ancora.' );
	}

	/**
	 * A-258: cifre del numero, fuso del sito, atto senza numero, documento fuori dall'atto.
	 */
	public function test_a258_casi_al_margine_del_nome(): void {
		// Un numero a cinque cifre: si dichiara la partenza prima del primo numero dell'anno.
		$this->assertTrue( Repertorio::dichiara( 12344, $this->amministratore, Passaggi::ora() ) );

		$grande = $this->pubblicato_il( '2041-10-01 09:30:00', 'Atto con numero grande' );
		$this->assertSame( 12345, Repertorio::di( $grande )['numero'] ?? null, 'Precondizione: il numero ha cinque cifre.' );
		$this->assertSame( 'albo-2041-12345-dal-01-10-al-16-10.pdf', $this->scarica( (int) DocumentiAtto::principale( $grande ) )['nome'], 'Cinque cifre, senza troncare.' );

		/*
		 * L'ultima sera dell'anno, con il sito otto ore indietro sul tempo
		 * universale: alle 23:30 del 31 dicembre qui, nel tempo universale e'
		 * gia' il 1 gennaio dell'anno dopo.
		 */
		update_option( 'timezone_string', 'America/Los_Angeles' );

		$sera = $this->pubblicato_il( '2041-12-31 23:30:00', 'Atto dell\'ultima sera' );
		$fine = conformita_core_fine_pubblicazione( $sera );

		$this->assertSame( 2041, Repertorio::di( $sera )['anno'] ?? null, 'Precondizione: l\'anno del registro e\' quello del sito.' );
		$this->assertSame( '2042-01-15', $fine, 'Precondizione: quindici giorni dal 31 dicembre, senza contare il primo.' );
		$this->assertSame( 'albo-2041-12346-dal-31-12-al-15-01.pdf', $this->scarica( (int) DocumentiAtto::principale( $sera ) )['nome'], 'Anno e date del fuso del sito.' );

		// Un atto in preparazione non ha numero: il nome lo dice e non porta date.
		$bozza = $this->atto_completo( 'normativo', array( 'post_title' => 'Atto in preparazione' ) );
		$this->assertSame( 'albo-senza-numero.pdf', NomeScaricato::nome( (int) DocumentiAtto::principale( $bozza ), $bozza ), 'Atto senza numero.' );

		// Un documento che non e' fra quelli dell'atto non ha nome: il meccanismo comune usa quello neutro.
		$this->assertNull( NomeScaricato::nome( (int) DocumentiAtto::principale( $bozza ), $sera ), 'Documento di un altro atto.' );

		// La data di fine scritta di lato in una forma che non si rilegge uguale: il nome resta senza l'ultimo giorno.
		$this->scavalca_barriera(
			static function () use ( $sera ) {
				update_post_meta( $sera, conformita_core_chiave_fine_pubblicazione(), '2042-02-31' );
			}
		);
		$this->assertSame( 'albo-2041-12346-dal-31-12.pdf', NomeScaricato::nome( (int) DocumentiAtto::principale( $sera ), $sera ), 'Data di fine che non si rilegge uguale.' );
	}

	/**
	 * A-306: dopo una sostituzione per oscuramento il nome proposto resta identico, e il contenuto e' quello oscurato (ALBO-30, ALBO-12).
	 *
	 * La sostituzione avviene il 5 ottobre, quattro giorni dopo la
	 * pubblicazione: un nome calcolato sull'istante della sostituzione
	 * direbbe "dal 05-10" e farebbe sembrare ripartita la pubblicazione. Si
	 * sostituiscono il principale e il secondo allegato; il primo resta com'era.
	 */
	public function test_a306_nome_dopo_l_oscuramento(): void {
		$atto = $this->pubblicato_il(
			'2041-10-01 09:30:00',
			'Atto che si oscurera\'',
			array(
				'primo.pdf'   => "%PDF-1.4\n% primo allegato\n%%EOF\n",
				'secondo.pdf' => "%PDF-1.4\n% secondo allegato\n%%EOF\n",
			)
		);

		$attesi    = array(
			'principale' => 'albo-2041-0001-dal-01-10-al-16-10.pdf',
			'primo'      => 'albo-2041-0001-dal-01-10-al-16-10-allegato-1.pdf',
			'secondo'    => 'albo-2041-0001-dal-01-10-al-16-10-allegato-2.pdf',
		);
		$documenti = array(
			'principale' => (int) DocumentiAtto::principale( $atto ),
			'primo'      => DocumentiAtto::ulteriori( $atto )[0],
			'secondo'    => DocumentiAtto::ulteriori( $atto )[1],
		);

		foreach ( $documenti as $quale => $documento ) {
			$this->assertSame( $attesi[ $quale ], $this->scarica( $documento )['nome'], $quale . ': precondizione, il nome prima.' );
		}

		$this->fissa_orologi( '2041-10-05 10:00:00' );

		$oscurati = array();

		foreach ( array( 'principale', 'secondo' ) as $quale ) {
			$contenuto = "%PDF-1.4\n% " . $quale . " oscurato\n%%EOF\n";
			$file      = $this->file_temporaneo( 'Mario-Rossi-oscurato.pdf', $contenuto );
			$uscente   = $documenti[ $quale ];
			$esito     = $this->come(
				$this->pubblicatore,
				static function () use ( $atto, $uscente, $file ) {
					return albo_pretorio_oscura(
						$atto,
						$uscente,
						$file,
						'percorso_locale',
						array(
							'conferma' => true,
							'causa'    => Passaggi::CAUSA_DATI_ECCEDENTI,
							'motivo'   => 'Il documento riporta l\'indirizzo di casa del destinatario.',
						)
					);
				}
			);

			$this->assertIsArray( $esito, is_wp_error( $esito ) ? $esito->get_error_message() : '' );

			$documenti[ $quale ] = $esito['documento'];
			$oscurati[ $quale ]  = hash( 'sha256', $contenuto );
		}

		$this->assertSame( array( $documenti['primo'], $documenti['secondo'] ), DocumentiAtto::ulteriori( $atto ), 'Precondizione: l\'entrante ha preso il posto del secondo allegato.' );

		foreach ( $documenti as $quale => $documento ) {
			$scaricato = $this->scarica( $documento );

			$this->assertSame( $attesi[ $quale ], $scaricato['nome'], $quale . ': il nome resta quello di prima.' );
			$this->assertStringNotContainsStringIgnoringCase( 'rossi', $scaricato['nome'], $quale . ': niente del nome caricato.' );

			if ( isset( $oscurati[ $quale ] ) ) {
				$this->assertSame( $oscurati[ $quale ], hash_file( 'sha256', $scaricato['percorso'] ), $quale . ': il contenuto e\' la versione oscurata.' );
			}
		}
	}
}
