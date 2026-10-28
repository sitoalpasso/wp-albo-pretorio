# Istruzioni per gli agenti che leggono questo repository

Questo file vale per gli strumenti automatici di revisione e di scrittura del codice. Le
regole di sviluppo sono in `CLAUDE.md`: questo file non le ripete, le presuppone. Dove i due
testi sembrano dire cose diverse, vale `CLAUDE.md`, e il dubbio va segnalato.

Scrivi sempre in italiano: commenti di revisione, sintesi, proposte di correzione.

## Che cosa è il repository

Plugin WordPress per l'albo pretorio online di una pubblica amministrazione (slug
`albo-pretorio-pa`). Versioni minime: WordPress 6.5, PHP 8.1, sito singolo. Si appoggia al
componente comune `conformita-core` (repository a parte), che fornisce il filtro di scadenza
a ogni lettura, la consegna dei documenti da un punto PHP e il registro delle modifiche.
L'albo dichiara a quel componente le proprie politiche; il componente non ne ha di sue.

Il rischio principale del prodotto non è pubblicare male, è **non smettere di pubblicare**:
un atto scaduto che resta raggiungibile espone dati personali oltre il periodo consentito.
Subito dopo vengono l'atto pubblicato con data, numero o autore sbagliati, perché dalla data
di inizio decorre un termine di legge.

## Dove sono le cose

| Percorso | Contenuto |
|---|---|
| `albo-pretorio-pa.php` | intestazione del plugin, costanti di versione, avvio |
| `includes/` | una classe per parte; la tabella "Le parti del plugin" in `docs/architettura.md` dice cosa fa ciascuna |
| `tests/` | PHPUnit sulla suite di test di WordPress; un file `*Test.php` per parte |
| `dati/` | dati che non sono codice (per esempio le feste nazionali) |
| `docs/requisiti.md` | i requisiti ALBO-nn, con fonte normativa e criterio di verifica |
| `docs/collaudo.md` | il catalogo di collaudo: una riga A-nnn per ogni cosa da dimostrare, con la prova che la dimostra |
| `docs/architettura.md` | stati dell'atto, passaggi consentiti, parti del plugin, confine con il componente comune |
| `docs/sicurezza.md` | checklist di sicurezza, eccezioni dichiarate, limiti noti |
| `docs/implementazione/` | una scheda per funzione: cosa fa, cosa non fa, limiti dichiarati, righe di collaudo. Indice in `LEGGIMI.md` |
| `docs/dati.md`, `docs/mappa-norme.md` | dati scritti e letti; norme e dove si applicano |

## Come girano le prove

- `composer install`, poi `composer test` (PHPUnit), `composer lint` (PHPCS con le WordPress
  Coding Standards).
- Due avvii separati con un componente comune diverso: `composer test:senza-core` e
  `composer test:core-incompatibile`. Le loro prove non girano nella suite principale.
- Ambiente locale: `npx wp-env start` (configurazione in `.wp-env.json`, componente comune a
  una revisione fissata).
- La CI (`.github/workflows/ci.yml`) esegue PHPCS e PHPUnit su WordPress 6.5 con PHP 8.1 e
  sull'ultima versione con PHP 8.3. Un comportamento che cambia fra le due versioni di
  WordPress va provato su entrambe.

## Review guidelines

### Prima di tutto: la materia della PR

Leggi la descrizione della pull request. Dice quale unità di lavoro si revisiona, quali
requisiti (ALBO-nn) e quali righe di collaudo (A-nnn) tocca, quali rilievi di giri precedenti
sono già stati accolti e quale codice è stato scritto per ultimo. **Rileggi per primo il
codice scritto per ultimo**: le correzioni dei giri precedenti sono il punto dove nascono i
difetti nuovi.

Poi leggi la scheda in `docs/implementazione/` che la PR modifica o aggiunge: dice che cosa
il codice deve fare e che cosa **deliberatamente non fa**. Un comportamento dichiarato come
limite o come unità futura non è un difetto di questa PR.

### Che cosa cercare

1. **Scadenza e invisibilità degli scaduti.** La scadenza è una proprietà del dato letto,
   non un evento pianificato. Nessun percorso pubblico deve dipendere dal compito pianificato
   per nascondere un atto: elenco, scheda, ricerca, REST per collezione e per ID, feed,
   sitemap, oEmbed, XML-RPC, pagina allegato, navigazione precedente e successivo, query di
   terzi, documenti. Un atto scaduto, tolto, annullato oltre la scadenza, in bozza o in
   verifica risponde come un atto mai esistito ("pagina non trovata"), non "non hai il
   permesso", e i suoi documenti non si scaricano. Cerca il controllo fatto su una proprietà
   vicina a quella giusta: lo stato `publish` invece di "si legge oggi", lo stato del
   contenuto padre invece di quello del documento, un percorso invece della cartella.

2. **Passaggi di stato.** I passaggi consentiti sono un elenco chiuso (tabella in
   `docs/architettura.md`). Per ogni controllo su un passaggio chiediti che cosa WordPress
   scrive **dopo** il punto del controllo nella stessa richiesta (`meta_input`, `tax_input`,
   `tags_input`, `post_category`, agganci successivi) e se un aggancio a priorità più alta
   può cambiare la decisione: il guardiano decide all'ultima priorità. Cerca le strade che
   non passano da `wp_insert_post` (`wp_publish_post()`, `set_post_type()`, scritture dirette
   dei metadati e delle voci). Un atto in verifica, pubblicato, defisso o annullato non
   cambia se non con il passaggio in corso. Le operazioni a più scritture sono tutto o
   niente: dopo un fallimento l'atto deve essere identico a prima (salvo il numero di
   repertorio, che preso non si riusa).

3. **Date in ora italiana.** Ogni data mostrata, calcolata o confrontata usa il fuso del
   sito (`wp_timezone()`), mai UTC per errore e mai un fuso cablato. Il giorno di
   pubblicazione non conta nella durata; l'atto è visibile fino alla mezzanotte del fuso del
   sito dell'ultimo giorno. Controlla cambio dell'ora legale, fine anno, mezzanotte, proroga,
   slittamento ai festivi. Date di inizio e fine le scrive il sistema, mai il browser.

4. **Permessi e nonce.** Ogni operazione che cambia dati controlla una capability dedicata
   con `current_user_can()`, mai il nome di un ruolo. Il nonce accompagna la capability, non
   la sostituisce. Ogni superficie registrata (rotta REST, azione AJAX, modulo di
   amministrazione) ha `permission_callback` o nonce più capability. Ogni ID che arriva
   dall'esterno si verifica: esiste, è del tipo giusto, è in uno stato su cui l'operazione è
   lecita, l'utente ha titolo. Stato, numero, date e autore li assegna il server. L'unica
   eccezione dichiarata al nonce è la ricerca della pagina pubblica (vedi
   `docs/sicurezza.md`).

5. **Cache e memoria.** Un valore che decide qualcosa (stato, scelta, elenco) letto dalla
   memoria degli oggetti invece che dalla banca dati può essere vecchio: segnalalo dove la
   decisione conta. Dopo un annullamento della transazione la memoria non deve ricordare ciò
   che il rollback ha tolto. Le risposte pubbliche dell'albo non devono restare in una
   memoria di pagina dopo la scadenza: controlla le intestazioni `Cache-Control` per il
   visitatore anonimo, non solo per chi ha fatto l'accesso.

6. **Letture che falliscono.** Una lettura della banca dati che non risponde non vale come
   "elenco vuoto" o "nessun dato": deve fermare la decisione. Controlla `$wpdb->last_error`
   dove una lettura decide qualcosa.

7. **Dati personali.** Niente oggetto dell'atto, nome originale del file o dati dei
   procedimenti dove non servono (nomi di file scaricati, registro delle modifiche, messaggi
   di errore al pubblico, attributi HTML). Il registro contiene solo l'identificativo
   dell'utente.

8. **Valori d'ente cablati.** Durate, denominazioni, soglie, festivi locali sono
   configurazione con validazione. Una scelta lasciata all'amministrazione non ha risposta
   preselezionata e, finché manca, l'operazione che ne dipende si rifiuta.

9. **Prove vuote o che non arrivano alla guardia.** Per ogni prova nuova o modificata
   chiediti che cosa dovrebbe rompersi perché diventi rossa. Segnala:
   - il valore atteso calcolato con la funzione sotto esame invece che scritto per esteso;
   - la prova al negativo che non dimostra prima che il caso esiste (precondizione);
   - la prova che aspetta un codice d'errore senza dimostrare quale controllo l'ha prodotto,
     e che quindi passerebbe anche se il rifiuto arrivasse da un controllo precedente;
   - la prova che conta chiamate a un aggancio comprese quelle fatte dalla prova stessa;
   - l'operazione che risponde "riuscito" senza che lo stato risultante venga riletto;
   - la prova che passerebbe anche togliendo la riga che dovrebbe proteggere.

10. **Righe di collaudo coerenti.** Ogni riga A-nnn citata dalla PR esiste in
    `docs/collaudo.md`, non si sovrappone a righe già usate in `main`, punta a una prova che
    esiste e verifica ciò che la riga promette. Lo stato "fatto" vale solo con la prova
    verde. Se il codice cambia un comportamento descritto in `docs/`, il documento cambia
    nella stessa PR. La scheda descrive ciò che il codice fa davvero, non ciò che farà.

11. **Sicurezza di base.** Sanitizzazione in ingresso, escaping nel punto in cui il dato
    incontra l'HTML, SQL solo con `$wpdb->prepare()`, nessun percorso di file costruito da
    input esterno, documenti serviti solo dal punto di consegna del componente comune, mai
    da URL diretto della cartella dei caricamenti. Elenco completo in `docs/sicurezza.md`.

### Che cosa non segnalare

- Stile, nomi, lingua dei commenti, formattazione: li controlla PHPCS.
- Rilievi fuori dalla materia della PR, salvo un difetto grave di sicurezza o di scadenza,
  o un difetto sfruttabile senza poteri da amministratore: in quel caso segnalalo una volta,
  dicendo che è fuori materia.
- Il codice di WordPress o del componente comune in sé.
- Le decisioni di prodotto già prese e scritte nei documenti: le trovi nelle schede di
  `docs/implementazione/` e in `docs/requisiti.md`. Esempi: niente pubblicazione
  programmata; rimozione anticipata solo per le cause dell'elenco chiuso; l'atto annullato
  in corso mostra solo l'avviso, senza documenti; nessuna risposta preselezionata nelle
  scelte lasciate all'amministrazione; il documento firmato non si modifica.
- I limiti dichiarati come tali in `docs/sicurezza.md` o nella scheda della PR, e le parti
  indicate come unità future (per esempio esportazione, oscuramento, referto). Eccezione: un
  limite dichiarato che si rivela sfruttabile senza poteri da amministratore si segnala
  sempre.
- Le prove HTTP che da riga di comando non si possono fare (in PHPUnit `header()` non lascia
  traccia), se la scheda le dichiara.

### Come scrivere ogni rilievo

Un rilievo per difetto concreto e riproducibile, con il file, la riga, lo scenario che lo fa
emergere e la correzione precisa. Nel testo di ogni rilievo aggiungi tre righe:

- **Realismo:** "configurazione comune" se basta un'installazione o un uso ordinario di
  WordPress; "componente insolito" se serve un altro plugin o un tema che fa qualcosa di non
  comune, e dici quale.
- **Causa comune:** se il difetto ha la stessa causa di un altro rilievo di questa revisione,
  dilla e nomina l'altro rilievo; altrimenti scrivi "isolato".
- **Chi può sfruttarlo:** "visitatore anonimo", "utente con un ruolo basso" oppure "solo chi
  può già installare plugin o scrivere nella banca dati". Un difetto sfruttabile senza
  poteri da amministratore va sempre corretto, per quanto raro sia il caso: non si dichiara
  come limite.

Priorità: un atto scaduto o non pubblico raggiungibile, un documento scaricabile quando non
dovrebbe, un dato personale esposto, un passaggio di stato non autorizzato o un atto
pubblicato con data, numero o autore sbagliati sono sempre la priorità più alta. Una prova
che passerebbe con il comportamento sbagliato su uno di questi punti è alta anche lei.

Se non trovi difetti concreti, dillo in una riga. Non riempire la revisione di suggerimenti.

## Vincoli sui testi del repository

Tutto ciò che entra nel ramo principale diventa pubblico. In nessun file, commento o commit
devono comparire nomi di enti reali, loro domini, o frasi che attribuiscano un requisito a
una singola amministrazione invece che alla norma. I requisiti derivano
dalla normativa e si citano per identificativo (ALBO-nn) ed estremi di legge. Niente trattini
lunghi nei testi. Se una PR viola questi vincoli, segnalalo con priorità alta.
