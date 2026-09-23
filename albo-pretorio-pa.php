<?php
/**
 * Plugin Name:       Albo Pretorio
 * Plugin URI:        https://github.com/sitoalpasso/wp-albo-pretorio
 * Description:       Pubblicazione con effetto di pubblicità legale. Attua requisiti derivati dalla normativa applicabile ai soggetti dell'art. 2-bis del d.lgs. 33/2013.
 * Version:           0.3.0-alpha
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
const VERSIONE = '0.3.0-alpha';

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
 * Nome del dato che porta la durata di un tipo di atto: giorni, origine ed
 * estremi insieme, in un solo valore.
 */
const META_DURATA = 'albo_pretorio_durata_tipo';

/**
 * Nome del dato della data di adozione dell'atto. Il trattino basso lo rende
 * protetto: non si scrive come campo personalizzato dalla schermata.
 */
const META_DATA_ADOZIONE = '_albo_pretorio_data_adozione';

/**
 * Nome del dato del numero proprio dell'atto, protetto come il precedente.
 */
const META_NUMERO_PROPRIO = '_albo_pretorio_numero_proprio';

/**
 * Tabella degli anni del repertorio, senza il prefisso delle tabelle del sito.
 */
const TABELLA_REPERTORIO_ANNI = 'albo_pretorio_repertorio_anni';

/**
 * Tabella delle assegnazioni del repertorio, senza il prefisso delle tabelle del sito.
 */
const TABELLA_REPERTORIO = 'albo_pretorio_repertorio';

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
require_once __DIR__ . '/includes/class-durate.php';
require_once __DIR__ . '/includes/class-dati-atto.php';
require_once __DIR__ . '/includes/class-scheda-atto.php';
require_once __DIR__ . '/includes/class-repertorio.php';
require_once __DIR__ . '/includes/class-schermata-repertorio.php';
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
add_action( 'init', array( Durate::class, 'da_init' ), 11 );
add_action( 'init', array( DatiAtto::class, 'da_init' ), 11 );
add_action( 'init', array( Installazione::class, 'da_init' ), 20 );

add_action( 'admin_notices', array( Permessi::class, 'mostra_avviso' ) );
add_action( 'admin_notices', array( Rifiuti::class, 'mostra' ) );
add_filter( 'redirect_post_location', array( Rifiuti::class, 'segna_indirizzo_di_ritorno' ), 10, 2 );

/*
 * La durata di ciascun tipo di atto si configura dalla schermata dei tipi, e
 * l'elenco dei tipi mostra lo stato vero di ciascuna.
 */
add_action( TASSONOMIA_TIPO_ATTO . '_add_form_fields', array( Durate::class, 'campi_nuovo' ) );
add_action( TASSONOMIA_TIPO_ATTO . '_edit_form_fields', array( Durate::class, 'campi_modifica' ) );
add_action( 'created_' . TASSONOMIA_TIPO_ATTO, array( Durate::class, 'da_salvataggio' ) );
add_action( 'edited_' . TASSONOMIA_TIPO_ATTO, array( Durate::class, 'da_salvataggio' ) );
add_filter( 'manage_edit-' . TASSONOMIA_TIPO_ATTO . '_columns', array( Durate::class, 'colonne' ) );
add_filter( 'manage_' . TASSONOMIA_TIPO_ATTO . '_custom_column', array( Durate::class, 'colonna' ), 10, 3 );
add_action( 'admin_notices', array( Durate::class, 'mostra_rifiuto' ) );

/*
 * La numerazione ha una schermata sua, sotto il menu degli atti, dove chi
 * pubblica dichiara la partenza nel primo anno d'uso. Finche' l'anno non numera,
 * un avviso in bacheca lo dice.
 */
add_action( 'admin_menu', array( SchermataRepertorio::class, 'menu' ) );
add_action( 'admin_post_' . SchermataRepertorio::AZIONE, array( SchermataRepertorio::class, 'da_invio' ) );
add_action( 'admin_notices', array( SchermataRepertorio::class, 'avviso' ) );

/*
 * I dati dell'atto si compilano nel riquadro della schermata dell'atto. Una
 * voce nuova dei due elenchi la crea solo chi li governa, anche quando arriva
 * con il salvataggio di un atto.
 */
add_action( 'add_meta_boxes_' . TIPO, array( SchedaAtto::class, 'riquadro' ) );
add_action( 'save_post_' . TIPO, array( SchedaAtto::class, 'da_salvataggio' ), 10, 2 );
add_action( 'admin_notices', array( SchedaAtto::class, 'mostra_rifiuto' ) );
add_filter( 'pre_insert_term', array( DatiAtto::class, 'da_pre_insert_term' ), 10, 2 );

/*
 * Lo sbarramento si aggancia al caricamento e non all'avvio riuscito: deve
 * valere anche quando il resto del componente e' inerte, perche' una fase in
 * cui la pubblicazione e' concessa non deve esistere in nessuna condizione.
 */
ChiusuraPubblicazione::aggancia();
