<?php
/**
 * Plugin Name:       Albo Pretorio
 * Plugin URI:        https://github.com/sitoalpasso/wp-albo-pretorio
 * Description:       Pubblicazione con effetto di pubblicità legale. Attua requisiti derivati dalla normativa applicabile ai soggetti dell'art. 2-bis del d.lgs. 33/2013.
 * Version:           0.1.0-alpha
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  conformita-core
 * Author:            sitoalpasso
 * Author URI:        https://github.com/sitoalpasso
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       albo-pretorio-pa
 * Domain Path:       /languages
 *
 * @package AlboPretorioPa
 */

declare( strict_types = 1 );

namespace AlboPretorioPa;

defined( 'ABSPATH' ) || exit;

/**
 * Versione del plugin.
 */
const VERSIONE = '0.1.0-alpha';

/**
 * Versione minima di WordPress dichiarata.
 */
const WP_MINIMA = '6.5';

/**
 * Versione minima di PHP dichiarata.
 */
const PHP_MINIMA = '8.1';

/**
 * Versione dell'API di conformita-core richiesta.
 *
 * Il controllo di compatibilità a runtime arriva insieme al primo meccanismo:
 * qui la costante fissa solo il valore atteso.
 */
const CORE_API_RICHIESTA = '1';

/**
 * Percorso del file principale del plugin.
 */
const FILE_PRINCIPALE = __FILE__;
