# Checklist di sicurezza

Ogni modifica al codice deve superare questa lista prima del merge. La lista è divisa in
due parti: i controlli che la CI fa da sola a ogni push, e quelli che chi rilegge il
codice spunta a mano. Un punto non applicabile si dichiara non applicabile nella scheda
di lavorazione, non si salta in silenzio.

Il principio che regge tutto: **in caso di dubbio il sistema si chiude, non si apre**.
Se un controllo non riesce a stabilire se un contenuto è visibile, il contenuto non si
mostra. Se un dato in ingresso non è valido, l'operazione non parte. Un errore deve
produrre un rifiuto rumoroso, mai un permesso silenzioso.

## Parte automatica: la CI la verifica a ogni push

| # | Controllo | Come |
|---|---|---|
| A1 | Ogni rotta REST registrata ha un `permission_callback` esplicito, e nessuna rotta di scrittura usa `__return_true` | test statico sui sorgenti |
| A2 | Ogni azione AJAX e ogni modulo di amministrazione verifica un nonce | test statico più test di integrazione: la chiamata senza nonce fallisce |
| A3 | Nessun uso diretto di `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER` senza funzione di sanitizzazione | PHPCS con le regole di sicurezza WordPress |
| A4 | Nessuna query SQL costruita concatenando variabili: ogni variabile passa da `$wpdb->prepare()` | PHPCS più revisione |
| A5 | Ogni file PHP inizia con il blocco che ferma l'accesso diretto (`defined( 'ABSPATH' ) || exit`) | test statico |
| A6 | Nessun segreto nel repository: chiavi, password, token, indirizzi di installazioni reali | scansione dei sorgenti e della storia |
| A7 | Nessuna funzione pericolosa: `eval`, `exec`, `system`, `unserialize` su dati esterni | test statico |
| A8 | Escaping in uscita: le stampe verso il browser passano da `esc_html`, `esc_attr`, `esc_url` o `wp_kses` | PHPCS con le regole di escaping |
| A9 | I test di attacco del catalogo di collaudo passano (chiamate senza permessi, ID manipolati, campi sovrascritti dal client) | PHPUnit |

## Parte manuale: si spunta rileggendo il diff

**Permessi e identità**

- Ogni operazione che cambia dati controlla una capability dedicata con
  `current_user_can()`, mai il nome di un ruolo, mai `is_super_admin()` come
  scorciatoia. Dove i requisiti lo dicono, il divieto vale anche per
  l'amministratore: per esempio la cancellazione di un atto pubblicato è bloccata
  per tutti.
- Il nonce non è un controllo di permesso: protegge dal CSRF, cioè dal far compiere
  a un utente autenticato un'azione che non voleva. Serve **insieme** alla
  capability, non al suo posto.
- **Un'eccezione dichiarata al nonce**: il modulo di ricerca della pagina pubblica
  dell'albo non ne ha. Non cambia niente, mostra a tutti la stessa cosa e non dipende da
  chi è collegato; un nonce legato alla sessione renderebbe la pagina diversa per ogni
  visitatore e scadrebbe in mano a chi ha salvato l'indirizzo di una ricerca. I suoi
  parametri si validano comunque uno per uno, e uno scritto male ferma la ricerca.
- Nessuna operazione privilegiata decisa solo da dati che arrivano dal browser: lo
  stato, il numero di repertorio, le date, l'autore li assegna il server. Se il
  client manda un campo che non gli spetta, il campo si ignora o la richiesta si
  rifiuta.

**Dati in ingresso**

- Ogni ID ricevuto dall'esterno si verifica: esiste, è del tipo di contenuto giusto,
  è in uno stato su cui l'operazione è lecita, l'utente ha titolo su di esso.
- Validazione sempre lato server. Il controllo nel browser è cortesia, non difesa.
- **I vecchi indirizzi di Albo Pretorio On line** (`?action=visatto`, `printatto`,
  `dwnalle`) sulla pagina dell'albo leggono un solo numero, di sole cifre, e cercano l'unico
  atto, in qualunque stato, che lo ricorda come origine; poi, a parte, si controlla che
  quell'atto sia in pubblicazione. Il rinvio va soltanto alla scheda
  dell'atto sulla pagina stessa, con `wp_safe_redirect()`: nessuna parte dell'indirizzo di
  arrivo viene dalla richiesta, quindi non può portare fuori dal sito. Ogni caso che non
  rinvia risponde come un atto mai esistito.
- Percorsi di file mai costruiti da input esterno senza normalizzazione: niente
  `../` che risale le cartelle. L'endpoint di consegna degli allegati riceve un ID,
  mai un percorso.

**File e caricamenti**

- Tipo ed estensione dei file caricati verificati lato server; gli allegati dei
  contenuti gestiti finiscono nella cartella protetta e si servono solo
  dall'endpoint di consegna, mai da URL diretto.
- L'endpoint di consegna applica il filtro di scadenza e la politica del componente
  a ogni richiesta: un file di un contenuto scaduto o non pubblico non si scarica.

**Uscita ed errori**

- Escaping il più tardi possibile, nel punto in cui il dato incontra l'HTML.
- I messaggi di errore verso il pubblico non rivelano dettagli interni: query, percorsi
  del filesystem, tracce di esecuzione, nomi di tabelle. Il dettaglio va nel log, non nel
  browser.
- **Le versioni non sono nell'elenco dei divieti assoluti.** Un avviso diagnostico
  riservato a chi ha `activate_plugins` puo' e deve dire la versione richiesta e quella
  trovata: senza quei due numeri l'avviso non e' azionabile, e chi lo legge ha gia' accesso
  all'amministrazione. La regola e' sul destinatario, non sul dato: al pubblico anonimo non
  si espongono numeri di versione dei componenti; a chi puo' installarli e attivarli si'.
- Le operazioni sensibili scrivono nel registro delle modifiche: chi, cosa, quando.
  Nel registro non si scrivono dati personali oltre l'identificativo dell'utente.

**Concorrenza e cache**

- Le operazioni che assegnano numeri o cambiano stato sono atomiche: due richieste
  contemporanee non producono due numeri uguali né due referti.
- Il compito pianificato è idempotente e protetto da lock: eseguito due volte,
  l'effetto è uno.
- Nessuna cache pubblica su contenuti a scadenza: intestazioni corrette
  sull'endpoint di consegna e invalidazione alla defissione.

**REST e visibilità**

- `show_in_rest` dichiarato deliberatamente per ogni tipo di contenuto, e la REST
  rispetta le stesse regole di visibilità del resto del sito: un contenuto scaduto
  non è raggiungibile nemmeno per ID diretto.
- I documenti di un atto annullato non si consegnano al pubblico, anche mentre
  l'avviso dell'annullamento è in vista: lo stato non pubblico li fa rifiutare dal
  meccanismo comune e l'albo li nega a sua volta, con lo stesso rifiuto di un atto
  scaduto. Nell'amministrazione si aprono a chi gestisce gli atti, non a chi ha il
  solo permesso di consultare gli scaduti.
- Un atto tolto dall'albo prima della scadenza è irraggiungibile dall'istante dopo, per la
  data di fine riportata al giorno prima e non per lo stato: riscritto lo stato a pubblicato
  nella banca dati, resta irraggiungibile (A-236). Limite noto, comune all'annullato
  scaduto: se un altro componente apre il tipo atto, per uno stato non pubblico WordPress
  risponde "non hai il permesso" invece di "non trovato" su alcune strade dell'interfaccia
  per programmi, e all'amministratore ne consegna documenti e commenti. Va chiuso nel
  meccanismo comune (scheda `implementazione/defissione-anticipata.md`).
- I metadati non pensati per il pubblico non sono esposti dalla REST.

## Domande di controllo

Chi approva può fare queste domande, e la risposta giusta è sempre un file di test con
nome e riga:

- Fammi vedere il test che dimostra che un utente senza autorizzazione non può
  chiamare questa funzione.
- Fammi vedere il test in cui un ID manipolato viene rifiutato.
- Fammi vedere il test in cui il contenuto scaduto non si scarica dall'endpoint.
