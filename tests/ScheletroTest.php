<?php
/**
 * Test di scheletro: verificano che l'infrastruttura regga, non i requisiti.
 *
 * I requisiti ALBO-xx hanno test propri, che arrivano con il codice che li
 * attua.
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa\Tests;

use WP_UnitTestCase;

/**
 * Verifiche sulle versioni minime dichiarate e sul fuso orario.
 */
class ScheletroTest extends WP_UnitTestCase {

	/**
	 * Il plugin è caricato e dichiara la propria versione.
	 */
	public function test_plugin_caricato(): void {
		$this->assertTrue( defined( 'AlboPretorioPa\\VERSIONE' ) );
		$this->assertSame( '0.1.0-alpha', \AlboPretorioPa\VERSIONE );
	}

	/**
	 * L'ambiente rispetta le versioni minime dichiarate nell'intestazione.
	 */
	public function test_versioni_minime_rispettate(): void {
		$this->assertTrue(
			version_compare( PHP_VERSION, \AlboPretorioPa\PHP_MINIMA, '>=' ),
			'PHP in uso inferiore alla versione minima dichiarata.'
		);

		$this->assertTrue(
			version_compare( get_bloginfo( 'version' ), \AlboPretorioPa\WP_MINIMA, '>=' ),
			'WordPress in uso inferiore alla versione minima dichiarata.'
		);
	}

	/**
	 * Le intestazioni del file principale coincidono con le costanti.
	 *
	 * Le due dichiarazioni si separano al primo aggiornamento distratto, e la
	 * versione minima sbagliata nell'intestazione non produce nessun errore
	 * visibile.
	 */
	public function test_intestazione_coerente_con_le_costanti(): void {
		$intestazione = get_file_data(
			\AlboPretorioPa\FILE_PRINCIPALE,
			array(
				'versione'    => 'Version',
				'wp_minima'   => 'Requires at least',
				'php_minima'  => 'Requires PHP',
				'text_domain' => 'Text Domain',
			)
		);

		$this->assertSame( \AlboPretorioPa\VERSIONE, $intestazione['versione'] );
		$this->assertSame( \AlboPretorioPa\WP_MINIMA, $intestazione['wp_minima'] );
		$this->assertSame( \AlboPretorioPa\PHP_MINIMA, $intestazione['php_minima'] );
		$this->assertSame( 'albo-pretorio-pa', $intestazione['text_domain'] );
	}

	/**
	 * Il fuso orario si legge da wp_timezone(), mai da una costante cablata.
	 */
	public function test_fuso_orario_dalla_configurazione(): void {
		update_option( 'timezone_string', 'Europe/Rome' );

		$this->assertSame( 'Europe/Rome', wp_timezone()->getName() );

		update_option( 'timezone_string', 'UTC' );

		$this->assertSame( 'UTC', wp_timezone()->getName() );
	}
}
