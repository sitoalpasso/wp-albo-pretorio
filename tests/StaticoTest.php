<?php
/**
 * Prove statiche sui sorgenti dell'albo: righe A-16 e A-17.
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
}
