<?php
/**
 * Backup di prova del plugin "Albo Pretorio On line", scritti come li scrive lui.
 *
 * Il formato e' quello che quel plugin produce dalla sua schermata di utilita'
 * (versione 4.13.0): per ogni tabella l'istruzione di creazione nella forma
 * che restituisce MySQL, una cancellazione, poi una riga di inserimento per
 * ogni record senza l'elenco delle colonne; i testi fra apici singoli con la
 * barra rovesciata davanti a barra e apice, e i quattro caratteri di controllo
 * scritti come sequenze; gli interi senza apici e, se vuoti, sostituiti dal
 * valore predefinito della colonna. In fondo le impostazioni del plugin e due
 * aggiornamenti del contatore. Nell'archivio il file SQL sta alla radice e gli
 * allegati senza la cartella degli allegati davanti.
 *
 * Le colonne di ogni tabella stanno nell'ordine di un'installazione recente:
 * quelle aggiunte dalle versioni successive in fondo, dopo la chiave.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

/**
 * Costruzione di backup di prova.
 */
trait BackupDiProva {

	/**
	 * I file temporanei da togliere a fine prova.
	 *
	 * @var array<int, string>
	 */
	protected $backup_temporanei = array();

	/**
	 * Le colonne di ogni tabella, con il tipo come lo scrive MySQL.
	 *
	 * @return array<string, array<string, string>>
	 */
	protected function schema_vecchio(): array {
		return array(
			'albopretorio_allegati'           => array(
				'IdAllegato'     => 'int(11) NOT NULL AUTO_INCREMENT',
				'TitoloAllegato' => "varchar(255) NOT NULL DEFAULT ''",
				'Allegato'       => "varchar(255) NOT NULL DEFAULT ''",
				'IdAtto'         => "int(11) NOT NULL DEFAULT '0'",
				'TipoFile'       => "varchar(6) DEFAULT ''",
				'DocIntegrale'   => "tinyint(1) NOT NULL DEFAULT '1'",
				'Impronta'       => 'char(64) NOT NULL',
				'Natura'         => "char(1) NOT NULL DEFAULT 'A'",
				'Note'           => "varchar(255) DEFAULT ''",
			),
			'albopretorio_atti'               => array(
				'IdAtto'               => 'int(11) NOT NULL AUTO_INCREMENT',
				'Numero'               => "int(4) NOT NULL DEFAULT '0'",
				'Anno'                 => "int(4) NOT NULL DEFAULT '0'",
				'Data'                 => "date NOT NULL DEFAULT '0000-00-00'",
				'Riferimento'          => 'text NOT NULL',
				'Oggetto'              => 'text NOT NULL',
				'DataInizio'           => "date NOT NULL DEFAULT '0000-00-00'",
				'DataFine'             => "date DEFAULT '0000-00-00'",
				'Informazioni'         => 'text NOT NULL',
				'IdCategoria'          => "int(11) NOT NULL DEFAULT '0'",
				'RespProc'             => 'int(11) NOT NULL',
				'DataAnnullamento'     => "date DEFAULT '0000-00-00'",
				'MotivoAnnullamento'   => 'varchar(255) DEFAULT NULL',
				'Ente'                 => "int(11) NOT NULL DEFAULT '0'",
				'DataOblio'            => "date NOT NULL DEFAULT '0000-00-00'",
				'Soggetti'             => 'varchar(100) NOT NULL',
				'IdUnitaOrganizzativa' => "int(11) NOT NULL DEFAULT '0'",
				'Richiedente'          => "varchar(100) NOT NULL DEFAULT ''",
			),
			'albopretorio_categorie'          => array(
				'IdCategoria' => 'int(11) NOT NULL AUTO_INCREMENT',
				'Nome'        => "varchar(255) NOT NULL DEFAULT ''",
				'Descrizione' => "varchar(255) NOT NULL DEFAULT ''",
				'Genitore'    => "int(11) NOT NULL DEFAULT '0'",
				'Giorni'      => "smallint(3) NOT NULL DEFAULT '0'",
			),
			'albopretorio_enti'               => array(
				'IdEnte'    => 'int(11) NOT NULL AUTO_INCREMENT',
				'Nome'      => 'varchar(100) NOT NULL',
				'Indirizzo' => "varchar(150) NOT NULL DEFAULT ''",
				'Url'       => "varchar(100) NOT NULL DEFAULT ''",
				'Email'     => "varchar(100) NOT NULL DEFAULT ''",
				'Pec'       => "varchar(100) NOT NULL DEFAULT ''",
				'Telefono'  => "varchar(40) NOT NULL DEFAULT ''",
				'Fax'       => "varchar(40) NOT NULL DEFAULT ''",
				'Note'      => 'text',
			),
			'albopretorio_resprocedura'       => array(
				'IdResponsabile' => 'int(11) NOT NULL AUTO_INCREMENT',
				'Cognome'        => "varchar(20) NOT NULL DEFAULT ''",
				'Nome'           => "varchar(20) NOT NULL DEFAULT ''",
				'Email'          => "varchar(100) NOT NULL DEFAULT ''",
				'Telefono'       => "varchar(30) NOT NULL DEFAULT ''",
				'Orario'         => "varchar(60) NOT NULL DEFAULT ''",
				'Note'           => 'text',
				'Funzione'       => "char(8) DEFAULT 'RP'",
			),
			'albopretorio_attimeta'           => array(
				'IdAttoMeta' => 'int(11) NOT NULL AUTO_INCREMENT',
				'IdAtto'     => 'int(11) NOT NULL',
				'Meta'       => 'varchar(100) NOT NULL',
				'Value'      => 'text',
			),
			'albopretorio_unitaorganizzative' => array(
				'IdUO'      => 'int(11) NOT NULL AUTO_INCREMENT',
				'Nome'      => 'varchar(100) NOT NULL',
				'Indirizzo' => "varchar(150) NOT NULL DEFAULT ''",
				'Url'       => "varchar(100) NOT NULL DEFAULT ''",
				'Email'     => "varchar(100) NOT NULL DEFAULT ''",
				'Pec'       => "varchar(100) NOT NULL DEFAULT ''",
				'Telefono'  => "varchar(40) NOT NULL DEFAULT ''",
				'Fax'       => "varchar(40) NOT NULL DEFAULT ''",
				'Note'      => 'text',
			),
		);
	}

	/**
	 * Una riga della tabella degli atti, con i campi indicati.
	 *
	 * @param array<string, string|int|null> $campi Campi da cambiare.
	 * @return array<string, string|int|null>
	 */
	protected function atto_vecchio( array $campi ): array {
		return array_merge(
			array(
				'IdAtto'               => 1,
				'Numero'               => 1,
				'Anno'                 => 2041,
				'Data'                 => '2041-10-01',
				'Riferimento'          => '',
				'Oggetto'              => 'Atto di prova',
				'DataInizio'           => '2041-10-01',
				'DataFine'             => '2041-10-16',
				'Informazioni'         => '',
				'IdCategoria'          => 1,
				'RespProc'             => 0,
				'DataAnnullamento'     => '0000-00-00',
				'MotivoAnnullamento'   => '',
				'Ente'                 => 0,
				'DataOblio'            => '2046-10-16',
				'Soggetti'             => '',
				'IdUnitaOrganizzativa' => 0,
				'Richiedente'          => '',
			),
			$campi
		);
	}

	/**
	 * Una riga della tabella degli allegati, con i campi indicati.
	 *
	 * @param array<string, string|int|null> $campi Campi da cambiare.
	 * @return array<string, string|int|null>
	 */
	protected function allegato_vecchio( array $campi ): array {
		return array_merge(
			array(
				'IdAllegato'     => 1,
				'TitoloAllegato' => 'Documento',
				'Allegato'       => '/var/www/sito/wp-content/uploads/AllegatiAttiAlboPretorio/documento.pdf',
				'IdAtto'         => 1,
				'TipoFile'       => 'pdf',
				'DocIntegrale'   => 1,
				'Impronta'       => '',
				'Natura'         => 'D',
				'Note'           => '',
			),
			$campi
		);
	}

	/**
	 * Le tabelle minime: una categoria da quindici giorni e le persone vuote.
	 *
	 * @param array<int, array<string, mixed>> $atti     Righe degli atti.
	 * @param array<int, array<string, mixed>> $allegati Righe degli allegati.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	protected function tabelle_vecchie( array $atti, array $allegati = array() ): array {
		return array(
			'albopretorio_allegati'           => $allegati,
			'albopretorio_atti'               => $atti,
			'albopretorio_categorie'          => array(
				array(
					'IdCategoria' => 1,
					'Nome'        => 'Deliberazione',
					'Descrizione' => 'Deliberazioni',
					'Genitore'    => 0,
					'Giorni'      => 15,
				),
			),
			'albopretorio_enti'               => array(
				array(
					'IdEnte'    => 0,
					'Nome'      => 'Amministrazione di prova',
					'Indirizzo' => '',
					'Url'       => '',
					'Email'     => '',
					'Pec'       => '',
					'Telefono'  => '',
					'Fax'       => '',
					'Note'      => null,
				),
			),
			'albopretorio_resprocedura'       => array(),
			'albopretorio_attimeta'           => array(),
			'albopretorio_unitaorganizzative' => array(),
		);
	}

	/**
	 * Il testo SQL del backup.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $tabelle  Righe, per tabella senza prefisso.
	 * @param array<string, string>                           $opzioni  Impostazioni del plugin.
	 * @param string                                          $prefisso Prefisso del sito.
	 * @param array<string, array<string, string>>|null       $schema   Colonne, se diverse da quelle recenti.
	 * @return string
	 */
	protected function sql_vecchio( array $tabelle, array $opzioni = array(), string $prefisso = 'wp_', ?array $schema = null ): string {
		$schema = $schema ?? $this->schema_vecchio();
		$testo  = '';

		foreach ( $tabelle as $tabella => $righe ) {
			$nome    = $prefisso . $tabella;
			$colonne = $schema[ $tabella ];

			if ( 'albopretorio_enti' === $tabella ) {
				$testo .= "SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';\r\n";
			}

			$definizioni = array();

			foreach ( $colonne as $colonna => $tipo ) {
				$definizioni[] = '  `' . $colonna . '` ' . $tipo;
			}

			$definizioni[] = '  PRIMARY KEY (`' . array_key_first( $colonne ) . '`)';

			$testo .= 'CREATE TABLE IF NOT EXISTS `' . $nome . "` (\n" . implode( ",\n", $definizioni ) . "\n) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ;\r\n";
			$testo .= 'Delete From ' . $nome . " ;\r\n";

			foreach ( $righe as $riga ) {
				$valori = array();

				foreach ( $colonne as $colonna => $tipo ) {
					$valori[] = $this->valore_sql( $riga[ $colonna ] ?? null, $tipo );
				}

				$testo .= 'INSERT INTO `' . $nome . '` VALUES (' . implode( ', ', $valori ) . ");\r\n";
			}
		}

		$testo .= 'CREATE TABLE IF NOT EXISTS `' . $prefisso . "options` (\n  `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `option_name` varchar(191) NOT NULL DEFAULT '',\n  `option_value` longtext NOT NULL,\n  `autoload` varchar(20) NOT NULL DEFAULT 'yes',\n  PRIMARY KEY (`option_id`),\n  UNIQUE KEY `option_name` (`option_name`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;\r\n";

		$identificativo = 100;

		foreach ( $opzioni as $opzione => $valore ) {
			$testo .= 'INSERT INTO `' . $prefisso . 'options` VALUES (' . ( $identificativo++ ) . ', ' . $this->valore_sql( $opzione, 'varchar' ) . ', ' . $this->valore_sql( $valore, 'longtext' ) . ", 'yes');\r\n";
		}

		if ( isset( $opzioni['opt_AP_AnnoProgressivo'], $opzioni['opt_AP_NumeroProgressivo'] ) ) {
			$testo .= 'UPDATE `' . $prefisso . "options` SET `option_value` = '" . $opzioni['opt_AP_AnnoProgressivo'] . "'	WHERE `option_name` ='opt_AP_AnnoProgressivo';\n";
			$testo .= 'UPDATE `' . $prefisso . "options` SET `option_value` = '" . $opzioni['opt_AP_NumeroProgressivo'] . "' WHERE `option_name` ='opt_AP_NumeroProgressivo';";
		}

		return $testo;
	}

	/**
	 * Un valore scritto come lo scrive il backup.
	 *
	 * @param mixed  $valore Valore.
	 * @param string $tipo   Tipo della colonna.
	 * @return string
	 */
	protected function valore_sql( $valore, string $tipo ): string {
		if ( 1 === preg_match( '/^(tiny|small|medium|big)?int/i', $tipo ) ) {
			if ( null === $valore || '' === $valore ) {
				return 1 === preg_match( "/DEFAULT '([^']*)'/", $tipo, $parti ) ? $parti[1] : 'NULL';
			}

			return (string) $valore;
		}

		$testo = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $valore );

		return "'" . str_replace( array( "\x00", "\x0a", "\x0d", "\x1a" ), array( '\0', '\n', '\r', '\Z' ), $testo ) . "'";
	}

	/**
	 * Un archivio zip del backup, in un file temporaneo.
	 *
	 * @param string                $sql  Testo SQL.
	 * @param array<string, string> $file Contenuto dei file, per nome nell'archivio.
	 * @return string Percorso dell'archivio.
	 */
	protected function zip_vecchio( string $sql, array $file = array() ): string {
		$percorso = $this->temporaneo( '.zip' );
		$archivio = new \ZipArchive();

		$this->assertTrue( $archivio->open( $percorso, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) );
		$archivio->addFromString( 'AlboPretorio20411001_0930.sql', $sql );

		foreach ( $file as $nome => $contenuto ) {
			$archivio->addFromString( $nome, $contenuto );
		}

		$archivio->close();

		return $percorso;
	}

	/**
	 * Un file temporaneo vuoto, tolto a fine prova.
	 *
	 * @param string $estensione Estensione con il punto.
	 * @return string
	 */
	protected function temporaneo( string $estensione ): string {
		$percorso = sys_get_temp_dir() . '/albo-backup-' . wp_generate_password( 12, false ) . $estensione;

		$this->backup_temporanei[] = $percorso;

		return $percorso;
	}

	/**
	 * Toglie i file temporanei.
	 */
	protected function togli_temporanei(): void {
		foreach ( $this->backup_temporanei as $percorso ) {
			if ( is_file( $percorso ) ) {
				unlink( $percorso );
			}
		}

		$this->backup_temporanei = array();
	}
}
