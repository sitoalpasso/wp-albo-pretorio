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
 * Versione dell'interfaccia di conformita-core richiesta.
 *
 * L'intestazione `Requires Plugins` garantisce la presenza del componente
 * comune e non la sua versione, perché accetta slug e non vincoli di versione:
 * il vincolo di versione vive qui e si verifica a ogni avvio.
 */
const CORE_API_RICHIESTA = '1.2.0';

/**
 * Nome visibile del componente, come compare negli avvisi in amministrazione.
 */
const NOME = 'Albo Pretorio';

/**
 * Nome visibile del componente comune da cui questo dipende.
 *
 * Sta in una costante e non dentro il testo degli avvisi perché è un dato e non
 * una frase: gli avvisi lo ricevono come parametro, e le prove possono
 * verificare che l'avviso nomini il componente da installare senza dipendere da
 * come la frase è scritta.
 */
const CORE_NOME = 'Conformita Core';

/**
 * Identificativo della sezione dichiarata al meccanismo comune.
 *
 * Lettere minuscole, cifre e trattino basso: è l'insieme di caratteri che il
 * registro delle sezioni ammette.
 */
const SEZIONE = 'albo_pretorio';

/**
 * Politica di indicizzazione della sezione.
 *
 * Vietata, e non configurabile. La pubblicità legale ha una durata definita, e
 * un atto defisso che resta nell'indice di un motore di ricerca continua a
 * diffondere i dati che vi compaiono oltre il termine. Non è una preferenza di
 * chi installa il componente: è la ragione per cui la politica è dichiarata qui
 * e non letta da un'opzione.
 */
const INDICIZZAZIONE = 'vietata';

/**
 * Politica di scadenza della sezione.
 *
 * Irraggiungibile: alla scadenza il contenuto non è più consultabile da nessun
 * utente, non passa in un archivio consultabile. La politica opposta esiste nel
 * meccanismo comune e serve ad altri componenti.
 */
const SCADENZA = 'irraggiungibile';

/**
 * Percorso del file principale del plugin.
 */
const FILE_PRINCIPALE = __FILE__;

require_once __DIR__ . '/includes/class-avvio.php';

/*
 * L'avvio si aggancia e non si esegue. Al caricamento di questo file il
 * meccanismo comune può non essere ancora caricato: WordPress carica i
 * componenti in ordine alfabetico di cartella, e `albo-pretorio-pa` viene prima
 * di `conformita-core`. Una chiamata qui sarebbe un errore grave sul sito vero,
 * non una diagnosi. A `plugins_loaded` tutti i componenti attivi sono caricati.
 */
add_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) );
