<?php
/**
 * Prove statiche sui sorgenti dell'albo: righe A-16, A-17 e A-44.
 *
 * Cercano nel testo del codice le cose che non devono esserci. Sono le prove
 * che restano vere anche quando nessuno esegue quel ramo di codice.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use WP_UnitTestCase;

/**
 * Controlli sul testo dei sorgenti.
 */
class StaticoTest extends WP_UnitTestCase {

	/**
	 * Sorgenti dell'albo, senza commenti, indicizzati per percorso.
	 *
	 * I commenti si tolgono perche' una spiegazione che nomina una funzione non
	 * e' una chiamata a quella funzione, e una prova che non lo distingue
	 * costringe a scrivere documentazione reticente.
	 *
	 * @return array<string, string>
	 */
	private function sorgenti(): array {
		$radice  = dirname( __DIR__ );
		$trovati = glob( $radice . '/includes/*.php' );
		$file    = array_merge(
			array( $radice . '/albo-pretorio-pa.php' ),
			is_array( $trovati ) ? $trovati : array()
		);

		$sorgenti = array();

		foreach ( $file as $percorso ) {
			$testo = (string) file_get_contents( $percorso );
			$testo = (string) preg_replace( '#/\*.*?\*/#s', '', $testo );
			$testo = (string) preg_replace( '#^\s*//.*$#m', '', $testo );

			$sorgenti[ basename( $percorso ) ] = $testo;
		}

		return $sorgenti;
	}

	/**
	 * Tutto il codice del ramo, sorgenti e prove, senza commenti.
	 *
	 * A-44 guarda anche le prove: una prova che chiama il metodo tolto e' un
	 * errore di esecuzione tanto quanto una chiamata dal componente.
	 *
	 * @return array<string, string>
	 */
	private function tutto_il_ramo(): array {
		$radice  = dirname( __DIR__ );
		$trovati = glob( $radice . '/tests/*.php' );
		$prove   = array();

		foreach ( is_array( $trovati ) ? $trovati : array() as $percorso ) {
			$testo = (string) file_get_contents( $percorso );
			$testo = (string) preg_replace( '#/\*.*?\*/#s', '', $testo );
			$testo = (string) preg_replace( '#^\s*//.*$#m', '', $testo );

			$prove[ 'tests/' . basename( $percorso ) ] = $testo;
		}

		return array_merge( $this->sorgenti(), $prove );
	}

	/**
	 * A-16: il tipo passa dal nucleo comune, gli elenchi di voci no.
	 *
	 * La differenza si vede qui invece di scoprirla nel codice: il nucleo comune
	 * offre un meccanismo per i tipi di contenuto e non ne offre uno per gli
	 * elenchi di voci.
	 */
	public function test_a16_registrazione_del_tipo_e_degli_elenchi(): void {
		$sorgenti = $this->sorgenti();

		$this->assertNotSame( array(), $sorgenti, 'Precondizione: i sorgenti devono essere stati letti.' );

		foreach ( $sorgenti as $nome => $testo ) {
			$this->assertSame(
				0,
				preg_match( '/\bregister_post_type\s*\(/', $testo ),
				'Il tipo si registra attraverso il nucleo comune, non direttamente: ' . $nome . '.'
			);
		}

		$this->assertGreaterThan(
			0,
			preg_match( '/\bregister_taxonomy\s*\(/', implode( "\n", $sorgenti ) ),
			'Gli elenchi di voci si registrano direttamente, perche\' il nucleo comune non offre il meccanismo.'
		);
	}

	/**
	 * A-17: i nomi derivati dei permessi non compaiono nei sorgenti.
	 *
	 * Si chiedono al nucleo comune, che li ricava dall'identificativo del tipo.
	 * Un nome riscritto a mano e sbagliato di una lettera e' un permesso che
	 * nessuno possiede e di cui nessuno si accorge.
	 */
	public function test_a17_nomi_derivati_dei_permessi_assenti(): void {
		$sorgenti = $this->sorgenti();

		$this->assertNotSame( array(), $sorgenti, 'Precondizione: i sorgenti devono essere stati letti.' );

		$tipo = preg_quote( \AlboPretorioPa\TIPO, '/' );

		foreach ( $sorgenti as $nome => $testo ) {
			$this->assertSame(
				0,
				preg_match( '/(edit|delete|publish|read|create)_' . $tipo . '/', $testo ),
				'I nomi derivati dei permessi non si scrivono a mano: ' . $nome . '.'
			);
		}
	}

	/**
	 * Il nome del metodo tolto, composto pezzo per pezzo.
	 *
	 * Si compone invece di scriverlo perche' cosi' la forma cercata non compare
	 * per intero in nessun punto di questo file, e la prova puo' guardare anche
	 * se stessa senza trovarsi addosso.
	 *
	 * @return string
	 */
	private function nome_tolto(): string {
		return implode( '', array( 'registr', 'ato' ) );
	}

	/**
	 * A-44: nel ramo non resta il metodo che rispondeva alle due domande insieme.
	 *
	 * **Deve essere una prova statica.** Quel metodo non lo chiama piu' nessuno,
	 * quindi nessuna prova di funzionamento lo esercita e la verifica continua
	 * resta verde mentre il metodo legge una proprieta' statica che non esiste
	 * piu': chiamarlo sarebbe un errore di esecuzione. Il danno peggiore pero'
	 * non e' l'errore, e' che chi lo trovasse avrebbe di nuovo a disposizione la
	 * domanda ambigua che la correzione ha eliminato, e la userebbe credendo di
	 * chiedere la proprieta' del tipo.
	 *
	 * Gli aghi si compongono pezzo per pezzo, cosi' la prova guarda anche se
	 * stessa senza trovarsi addosso.
	 */
	public function test_a44_osservazione_ambigua_rimossa(): void {
		$ramo = $this->tutto_il_ramo();

		$this->assertNotSame( array(), $ramo, 'Precondizione: i sorgenti devono essere stati letti.' );
		$this->assertArrayHasKey(
			'class-tipo-atto.php',
			$ramo,
			'Precondizione: il file da cui il metodo e\' stato tolto deve essere fra quelli letti.'
		);

		$nome = $this->nome_tolto();

		$forme = array(
			'la chiamata al metodo' => '/TipoAtto::' . $nome . '\b/',
			'la sua dichiarazione'  => '/function\s+' . $nome . '\s*\(/',
			'la proprieta\' letta'  => '/self::\$' . $nome . '\b/',
		);

		foreach ( $ramo as $file => $testo ) {
			foreach ( $forme as $descrizione => $ago ) {
				$this->assertSame(
					0,
					preg_match( $ago, $testo ),
					'Nel ramo non deve restare ' . $descrizione . ' che rispondeva alle due domande insieme: ' . $file . '.'
				);
			}
		}
	}

	/**
	 * Controllo positivo di A-44: gli aghi trovano cio' che c'e' davvero.
	 *
	 * Senza, le tre asserzioni sopra sarebbero vere anche con espressioni
	 * scritte male, che non troverebbero niente in nessun caso.
	 */
	public function test_a44_gli_aghi_trovano_le_forme_vive(): void {
		$nome = $this->nome_tolto();

		$this->assertSame(
			1,
			preg_match( '/TipoAtto::' . $nome . '\b/', 'if ( TipoAtto::' . $nome . '() ) {' ),
			'L\'ago della chiamata deve trovare una chiamata.'
		);
		$this->assertSame(
			1,
			preg_match( '/function\s+' . $nome . '\s*\(/', 'public static function ' . $nome . '(): bool {' ),
			'L\'ago della dichiarazione deve trovare una dichiarazione.'
		);
		$this->assertSame(
			1,
			preg_match( '/self::\$' . $nome . '\b/', 'if ( ! self::$' . $nome . ' ) {' ),
			'L\'ago della proprieta\' deve trovare una lettura.'
		);

		$this->assertSame(
			0,
			preg_match( '/TipoAtto::' . $nome . '\b/', 'conformita_core_tipo_' . $nome . '( TIPO )' ),
			'E nessuno dei tre deve confondersi con i nomi vicini che restano leciti.'
		);
	}
}
