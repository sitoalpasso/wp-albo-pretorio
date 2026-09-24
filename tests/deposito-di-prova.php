<?php
/**
 * Aiuti comuni alle prove che depositano documenti veri.
 *
 * Il deposito lo fa il meccanismo comune, e lo rifiuta finche' la protezione
 * della cartella non risulta verificata. La verifica e' una richiesta HTTP al
 * sito stesso: qui la si intercetta con `pre_http_request` e le si fa
 * rispondere un **server finto**, che nega l'accesso all'esca come un server
 * configurato bene. I file partono da file temporanei veri e si depositano con
 * l'origine "percorso locale", perche' dentro la suite nessun file arriva da una
 * richiesta HTTP.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use AlboPretorioPa\DocumentiAtto;

/**
 * Server finto, file temporanei e osservazioni sul disco.
 */
trait DepositoDiProva {

	/**
	 * Quello che il server finto risponde.
	 *
	 * @var array<string, mixed>
	 */
	private $risposta_del_server = array();

	/**
	 * File temporanei da togliere alla fine.
	 *
	 * @var array<int, string>
	 */
	private $temporanei = array();

	/**
	 * Aggancia il server finto che nega l'esca e verifica la protezione.
	 */
	protected function aggancia_server_finto(): void {
		\Conformita_Core_Allegati::azzera();

		$this->risposta_del_server = array(
			'response' => array(
				'code'    => 403,
				'message' => 'Forbidden',
			),
			'body'     => '<html><body>Forbidden</body></html>',
		);

		add_filter( 'pre_http_request', array( $this, 'server_finto' ), 10, 3 );

		$stato = conformita_core_verifica_protezione_allegati();

		$this->assertSame( 'verificata', $stato['copertura'], 'Precondizione: con il server finto la protezione deve risultare verificata.' );
	}

	/**
	 * Da qui in poi il server serve l'esca, come un server che non protegge la cartella.
	 */
	protected function server_che_serve_l_esca(): void {
		$this->risposta_del_server = array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => \Conformita_Core_Allegati::contenuto_esca(),
		);

		$stato = conformita_core_verifica_protezione_allegati();

		$this->assertSame( 'non_coperta', $stato['copertura'], 'Precondizione: la protezione deve risultare non coperta.' );
	}

	/**
	 * Toglie il server finto, i file temporanei e i documenti depositati.
	 */
	protected function sgancia_server_finto(): void {
		remove_filter( 'pre_http_request', array( $this, 'server_finto' ), 10 );

		foreach ( $this->temporanei as $percorso ) {
			if ( file_exists( $percorso ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- prova: si tocca il disco direttamente perche' e' il disco cio' che si sta verificando.
				unlink( $percorso );
			}
		}

		$this->temporanei = array();

		/*
		 * Si tolgono i file e si lasciano le cartelle: `wp_upload_dir()` ricorda
		 * quelle che ha gia' creato e non le ricreerebbe.
		 */
		foreach ( $this->file_sotto( \Conformita_Core_Allegati::cartella() ) as $percorso ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- come sopra.
			unlink( $percorso );
		}

		\Conformita_Core_Allegati::azzera();
	}

	/**
	 * Aggancio di `pre_http_request`: la risposta finta.
	 *
	 * @param mixed                $esito     Esito proposto.
	 * @param array<string, mixed> $argomenti Argomenti della richiesta.
	 * @param string               $indirizzo Indirizzo chiesto.
	 * @return array<string, mixed>
	 */
	public function server_finto( $esito, $argomenti = array(), $indirizzo = '' ): array {
		unset( $esito, $argomenti, $indirizzo );

		return $this->risposta_del_server;
	}

	/**
	 * Un file temporaneo vero, nella forma di `$_FILES`.
	 *
	 * @param string      $nome      Nome del file.
	 * @param string|null $contenuto Contenuto, un PDF minimo se nullo.
	 * @return array<string, mixed>
	 */
	protected function file_temporaneo( string $nome = 'atto.pdf', ?string $contenuto = null ): array {
		if ( null === $contenuto ) {
			$contenuto = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n% " . wp_generate_password( 12, false ) . "\n";
		}

		$percorso = wp_tempnam( $nome );

		file_put_contents( $percorso, $contenuto ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- prova: il file deve esistere sul disco.

		$this->temporanei[] = $percorso;

		return array(
			'name'     => $nome,
			'tmp_name' => $percorso,
			'type'     => '',
			'size'     => strlen( $contenuto ),
			'error'    => 0,
		);
	}

	/**
	 * Deposita un documento vero su un atto, e pretende che riesca.
	 *
	 * @param int    $atto_id Atto.
	 * @param string $ruolo   Ruolo.
	 * @param string $nome    Nome del file.
	 * @return int
	 */
	protected function deposita_documento( int $atto_id, string $ruolo = DocumentiAtto::PRINCIPALE, string $nome = 'atto.pdf' ): int {
		$esito = DocumentiAtto::deposita( $atto_id, $this->file_temporaneo( $nome ), $ruolo, 'percorso_locale' );

		$this->assertIsInt( $esito, 'Precondizione: il deposito deve riuscire. ' . ( is_wp_error( $esito ) ? $esito->get_error_message() : '' ) );

		return $esito;
	}

	/**
	 * I documenti sul disco nella cartella protetta, senza i file di regole e le esche.
	 *
	 * @return array<int, string>
	 */
	protected function documenti_sul_disco(): array {
		$regole   = array( '.htaccess', 'web.config', 'index.php' );
		$trovati  = array();
		$cartella = \Conformita_Core_Allegati::cartella();

		foreach ( $this->file_sotto( $cartella ) as $percorso ) {
			$nome = wp_basename( $percorso );

			if ( in_array( $nome, $regole, true ) || 0 === strpos( $nome, \Conformita_Core_Allegati::ESCA_PREFISSO ) ) {
				continue;
			}

			$trovati[] = $percorso;
		}

		sort( $trovati );

		return $trovati;
	}

	/**
	 * Tutti i file sotto una cartella.
	 *
	 * @param string $cartella Cartella.
	 * @return array<int, string>
	 */
	private function file_sotto( string $cartella ): array {
		if ( ! is_dir( $cartella ) ) {
			return array();
		}

		$file = array();
		$voci = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $cartella, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $voci as $voce ) {
			if ( ! $voce->isDir() ) {
				$file[] = $voce->getPathname();
			}
		}

		return $file;
	}
}
