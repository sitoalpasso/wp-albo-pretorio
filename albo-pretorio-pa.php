<?php
/**
 * Plugin Name:       Albo Pretorio
 * Plugin URI:        https://github.com/sitoalpasso/wp-albo-pretorio
 * Description:       Pubblicazione con effetto di pubblicità legale. Attua requisiti derivati dalla normativa applicabile ai soggetti dell'art. 2-bis del d.lgs. 33/2013.
 * Version:           0.2.0-alpha
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
const VERSIONE = '0.2.0-alpha';

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
 * Identificativo del tipo di contenuto atto.
 *
 * Non `atto` e basta: da questo identificativo il meccanismo comune ricava i
 * nomi dei permessi, e un nome generico e' un nome che un altro componente puo'
 * avere gia' preso. In quel caso la registrazione verrebbe rifiutata e l'albo
 * resterebbe inerte su quel sito. L'indirizzo pubblico degli atti non viene da
 * qui: viene dal parametro di riscrittura, che resta libero.
 */
const TIPO = 'atto_albo';

/**
 * Elenco di voci dei tipi di atto.
 */
const TASSONOMIA_TIPO_ATTO = 'albo_tipo_atto';

/**
 * Elenco di voci degli organi che adottano gli atti.
 */
const TASSONOMIA_ORGANO = 'albo_organo';

/**
 * Ruolo proprio del componente, per chi pubblica gli atti.
 */
const RUOLO = 'albo_responsabile_pubblicazione';

/**
 * Permesso marcatore che rende riconoscibile il ruolo creato da questo componente.
 *
 * Serve a distinguere due situazioni che si assomigliano e non lo sono: un
 * ruolo creato da una versione precedente dell'albo, che va aggiornato, e un
 * ruolo omonimo di un altro componente, che non va adottato. Adottarlo
 * significherebbe consegnare i permessi dell'albo a chiunque possieda gia'
 * quel ruolo.
 *
 * Il riconoscimento non poggia sull'opzione della versione, e non e' un
 * dettaglio: cancellata quella sola opzione il componente deve ripararsi da
 * solo, e con un riconoscimento basato su di essa scambierebbe il proprio ruolo
 * per estraneo proprio nel momento in cui deve rimetterlo a posto.
 */
const RUOLO_MARCATORE = 'albo_pretorio_ruolo_del_componente';

/**
 * Opzione che ricorda quale versione del componente e' installata su questo sito.
 *
 * E' il primo dato che il componente scrive nella banca dati, ed e' la
 * sentinella dell'installazione: finche' il valore memorizzato e' diverso da
 * quello del codice, il lavoro di installazione va rifatto.
 */
const OPZIONE_VERSIONE = 'albo_pretorio_pa_versione';

/**
 * Percorso del file principale del plugin.
 */
const FILE_PRINCIPALE = __FILE__;

require_once __DIR__ . '/includes/class-avvio.php';
require_once __DIR__ . '/includes/class-permessi.php';
require_once __DIR__ . '/includes/class-tipo-atto.php';
require_once __DIR__ . '/includes/class-installazione.php';
require_once __DIR__ . '/includes/class-rifiuti.php';
require_once __DIR__ . '/includes/class-chiusura-pubblicazione.php';

/*
 * L'avvio si aggancia e non si esegue. Al caricamento di questo file il
 * meccanismo comune può non essere ancora caricato: WordPress carica i
 * componenti in ordine alfabetico di cartella, e `albo-pretorio-pa` viene prima
 * di `conformita-core`. Una chiamata qui sarebbe un errore grave sul sito vero,
 * non una diagnosi. A `plugins_loaded` tutti i componenti attivi sono caricati.
 */
add_action( 'plugins_loaded', array( Avvio::class, 'da_plugins_loaded' ) );

/*
 * I tipi di contenuto si registrano su `init`, non prima. L'installazione viene
 * dopo, perche' i nomi dei permessi si ricavano dal tipo e il tipo deve gia'
 * esistere.
 */
add_action( 'init', array( TipoAtto::class, 'da_init' ) );
add_action( 'init', array( Installazione::class, 'da_init' ), 20 );

add_action( 'admin_notices', array( Permessi::class, 'mostra_avviso' ) );
add_action( 'admin_notices', array( Rifiuti::class, 'mostra' ) );
add_filter( 'redirect_post_location', array( Rifiuti::class, 'segna_indirizzo_di_ritorno' ), 10, 2 );

/*
 * Lo sbarramento si aggancia al caricamento e non all'avvio riuscito: deve
 * valere anche quando il resto del componente e' inerte, perche' una fase in
 * cui la pubblicazione e' concessa non deve esistere in nessuna condizione.
 */
ChiusuraPubblicazione::aggancia();
