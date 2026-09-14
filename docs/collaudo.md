# Collaudo: come si dimostra che funziona

Questo documento spiega come si prova, **automaticamente**, che il plugin fa quello che
`requisiti.md` promette. È il contratto: un requisito non è chiuso finché il suo test non
passa in CI, e una modifica che tocca un requisito aggiorna prima il test.

## Come funziona il collaudo, in breve

- I test girano con **PHPUnit** dentro un WordPress vero avviato da `wp-env`: non
  simulazioni, un sito completo con database.
- La **CI** (GitHub Actions) li esegue a ogni push, insieme a PHPCS (standard di codice)
  e ai test statici (controlli sul testo del codice sorgente). Se qualcosa è rosso, non
  si mergia.
- Tre famiglie di test:
  1. **di funzionamento**: il comportamento giusto avviene (l'atto si pubblica, il
     referto si genera);
  2. **di attacco**: il comportamento sbagliato è impossibile (contrassegnati
     [attacco]; molti riproducono difetti reali osservati in soluzioni esistenti, e
     devono fallire sul comportamento sbagliato, non solo passare su quello giusto);
  3. **statici**: cercano nel codice sorgente le cose che non devono esserci (una
     durata cablata, una rotta senza controllo dei permessi).

I percorsi di lettura citati come C-10..C-21 e C-33 sono l'elenco chiuso definito nel
collaudo di `conformita-core`: pagina singola, elenco, ricerca, feed, sitemap, REST per
collezione e per ID, oEmbed, XML-RPC, pagina allegato, navigazione precedente e
successivo, query di terzi, URL diretto dell'allegato.

## Il test più importante

**ALBO-18, il filtro alla lettura.** Si crea un atto, lo si fa scadere ieri, si tiene il
cron **spento** e si verifica che l'atto sia irraggiungibile su tutti i percorsi
dell'elenco chiuso, compreso il download dell'allegato. È il test che separa un albo
conforme da uno che sembra funzionare finché nessuno guarda.

## Requisito per requisito

La colonna **Stato** ha gli stessi tre valori di `requisiti.md`, applicati qui al test e
non al codice: **da fare** (il test non è scritto), **in corso** (scritto e rosso, cioè
il comportamento non c'è ancora), **fatto** (scritto e verde in CI). Un requisito è
"fatto" in `requisiti.md` solo se tutte le sue righe qui sono "fatto".

| Req | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| ALBO-01 | da fare | Si salva una **bozza** con dati mancanti | riesce. Controllo positivo: impedisce di soddisfare la riga seguente rifiutando tutto |
| ALBO-01 | da fare | Si prova a **pubblicare** senza uno dei dati necessari, uno per volta | bloccato ogni volta, con il nome del dato che manca. Il numero di repertorio non è fra questi: lo assegna il sistema |
| ALBO-01 | da fare | Documento principale e allegati ulteriori | il principale è uno e uno solo e serve per pubblicare; gli ulteriori sono facoltativi e un atto senza nessuno di essi si pubblica |
| ALBO-02 | da fare | Si prova a **pubblicare** senza data di fine da ogni ingresso: interfaccia, REST, inserimento diretto | sempre bloccato. In bozza la data può mancare |
| ALBO-03 | da fare | Si invoca il compito dall'esterno con il cron interno di WordPress disattivato | defissione eseguita, e **voce nel registro delle modifiche del meccanismo comune**. Il comportamento dipende da **due** lavorazioni del meccanismo comune, il compito pianificato e il registro, non dal solo compito |
| ALBO-04 | da fare | Si prova a pubblicare un atto di un tipo senza durata configurata; si cambia la durata di un tipo senza toccare codice | pubblicazione bloccata; cambio possibile |
| ALBO-04 | da fare | [attacco, statico] si cerca la costante 15 usata come durata nel codice | assente |
| ALBO-05 | da fare | Atto defisso, tutti i percorsi C-10..C-21, provati **sia da visitatore anonimo sia da utente con permessi** | irraggiungibile in entrambi i casi. La politica dichiarata e' `irraggiungibile`, quindi l'esenzione non esiste per nessun utente: provarlo solo da anonimo lascerebbe passare un componente che invece dichiara `archivio` |
| ALBO-06 | da fare | Si ispezionano le pagine dell'albo: meta robots, sitemap | noindex presente, URL fuori dalla sitemap |
| ALBO-07 **ipotesi** | da fare | Si genera il referto di un atto defisso. **Requisito da confermare**: poggia sulla prassi, non su una fonte, e l'unità che lo costruisce è bloccata dalla conferma | contiene numero, date effettive, impronta del file, autore; ristampato, è identico |
| ALBO-08 **ipotesi** | da fare | Si pubblicano più atti in parallelo (test di concorrenza). **Requisito da confermare**, come ALBO-07 | ogni atto ottiene un numero unico, progressivo, assegnato una sola volta e mai riutilizzato, neanche dopo un'operazione fallita a metà. L'assenza di buchi non è fra le garanzie |
| ALBO-08 **ipotesi** | da fare | [attacco] si invia una richiesta manipolata che tenta di scrivere il numero di repertorio. **Requisito da confermare** | il numero resta quello del sistema |
| ALBO-09 | da fare | Si tenta di sostituire l'allegato di un atto pubblicato | bloccato; la rettifica è un nuovo atto |
| ALBO-10 | da fare | Si pubblica e si defigge in anticipo con motivo | due voci di registro: chi, quando, perché |
| ALBO-11 | da fare | Si percorre il flusso di pubblicazione saltando la conferma sui dati personali | il flusso non si conclude; la schermata elenca i casi di rivelazione indiretta |
| ALBO-12 | da fare | Si pubblica un atto con versione oscurata | il pubblico vede solo l'oscurata; l'originale richiede il permesso |
| ALBO-13 | da fare | Si carica un PDF che sembra una scansione | avviso al redattore, dichiarato come indizio; pubblicare resta possibile |
| ALBO-14 | da fare | Si legge un atto pubblicato con una sonda di accessibilità | testo vero, niente immagini al posto del testo, niente blocchi di copia |
| ALBO-15 | da fare | Lo stesso documento sta in albo e in trasparenza | due esposizioni, cicli di vita indipendenti, un solo file |
| ALBO-16 | da fare | Ricerca per tipo, organo, date, testo, navigando da sola tastiera | funziona, ordinata per data di pubblicazione |
| ALBO-17 | da fare | Export di un anno di atti con metadati | formato aperto, completo, reimportabile |
| ALBO-18 | da fare | Cron mai eseguito, atto scaduto ieri, tutti i percorsi più l'URL diretto dell'allegato (C-33) | tutto irraggiungibile |
| ALBO-19 | da fare | Si retrodata il timestamp del battito oltre soglia | l'avviso parte verso il responsabile configurato |
| ALBO-20 | da fare | Cache di pagina simulata attiva, poi defissione | la pagina dell'atto non resta servita dalla cache |
| ALBO-21 | da fare | Atti in scadenza nei giorni dei due cambi d'ora, fuso del sito su Roma | la scadenza cade nell'istante civile giusto |
| ALBO-23 | da fare | Si attiva il componente su un sito pulito | l'amministratore riceve l'insieme minimo di permessi del tipo atto. Si verifica **dopo** l'attivazione e non nel codice: un amministratore vede la voce di menu e riesce a creare un atto |
| ALBO-24 | da fare | Si aggiorna il componente a una versione che introduce permessi nuovi | i permessi nuovi arrivano ai ruoli che avevano già gli altri. Un aggiornamento che li assegna solo alla prima attivazione lascia scoperti i siti già installati |
| ALBO-25 | da fare | Nessun ruolo possiede i permessi del tipo atto | avviso in amministrazione che lo dice, con il nome del ruolo mancante |
| ALBO-26 | da fare | Ruolo proprio del componente, per esempio responsabile della pubblicazione | creato e configurato da questo componente, non dal meccanismo comune |
| [attacco] | da fare | Un amministratore tenta di cancellare un atto pubblicato | bloccato: nessuna esenzione |
| [attacco, statico] | da fare | Si cerca ogni rotta REST o AJAX registrata senza nonce o `permission_callback` | nessuna |

**ALBO-22 non ha righe qui, e non è una dimenticanza.** È una decisione incompleta: mancano
le transizioni consentite, chi può compierle e cosa accade a una transizione rifiutata.
Finché quei tre pezzi non sono decisi il requisito non è verificabile, e una riga scritta
adesso misurerebbe quello che il codice avrà fatto invece di quello che deve fare. Vedi la
nota in `requisiti.md`, che contiene anche il vincolo che il flusso non potrà contraddire:
lo stato memorizzato non è la condizione di scadenza.

**ALBO-07 e ALBO-08 hanno righe qui ma sono ipotesi**, non impegni: poggiano sulla prassi e
non su una fonte, e le unità che li costruiscono sono bloccate dalla conferma. Le righe
restano perché il criterio di verifica è già scritto, non perché il requisito sia confermato.

## Avvio del componente e dichiarazione delle politiche

**Perché queste righe hanno un identificativo e le altre no.** Le righe qui sopra sono
indicizzate per requisito, perché ciascuna attua un ALBO-xx. Queste no: costruiscono
l'infrastruttura su cui ALBO-05 e ALBO-06 poggeranno, e nessun requisito è chiuso da loro
sole. Prefisso `A-` con il trattino.

**Le situazioni di guasto non sono simmetriche, ed è il punto che regge la tabella.** Quando
il meccanismo comune manca, il componente resta **attivo e inerte** e può segnalarlo a ogni
richiesta. Lo stesso quando la sezione risulta già registrata **da altri, con politiche
identiche o diverse**, e quando il filtro di scadenza non risulta avviato. Quando la versione
di interfaccia è incompatibile il meccanismo comune **disattiva** il componente, che da quel
momento non viene più caricato e non può più dire niente.

**Perché l'idempotenza non si dimostra confrontando le politiche.** La via facile sarebbe: se
la sezione esiste già con le politiche giuste, l'avvio prosegue in silenzio. Non regge.
L'uguaglianza dei valori dice che qualcuno ha registrato quella sezione con quelle politiche,
**non che sia stato questo componente**. Un altro componente può registrare la stessa sezione
dichiarando gli stessi valori, e l'albo proseguirebbe governando una sezione non sua. Oggi il
danno è teorico, perché A-01..A-10 non derivano niente dalla sezione. **Il conflitto diventa
rilevante quando la capability dell'archivio riservato sarà derivata dall'identificativo
della sezione**: accettare come propria una sezione registrata da altri potrebbe far
condividere involontariamente quella superficie riservata. Da non confondere con le
capability del tipo di contenuto, che derivano dall'identificativo **del tipo**.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| A-01 | da fare | La dipendenza è dichiarata in due posti: intestazione `Requires Plugins` e versione di interfaccia controllata all'avvio | entrambe presenti e coerenti. Sul controllo di WordPress si verifica il **codice** di errore, non il testo del messaggio, che sul sito italiano è tradotto |
| A-02 | da fare | Il file principale viene caricato senza che il meccanismo comune sia già caricato | nessun errore grave e nessuna chiamata: l'avvio è rimandato all'aggancio che scatta a componenti caricati |
| A-03 | da fare | Avvio con le funzioni del meccanismo comune non definite. Si dimostra prima che il componente risulti fra quelli attivi | resta **attivo e inerte**: nessun errore grave, nessuna sezione registrata, nessun avvio parziale. Avviso proprio a ogni richiesta di amministrazione, visibile solo a chi può attivare i componenti, con la versione richiesta e quella trovata |
| A-04 | da fare | Criterio di compatibilità della versione, sui tre casi che contano | versione inferiore con stesso numero maggiore: incompatibile. Numero maggiore diverso: incompatibile. Versione superiore con stesso numero maggiore: compatibile |
| A-05 | da fare | Effetto completo della guardia, in un processo separato con versione incompatibile, con una richiesta di amministrazione come prima richiesta | componente disattivato, **sezione assente fra quelle registrate**, nessun errore grave, avviso presente. La precondizione, cioè che il componente fosse attivo, si dimostra prima |
| A-06 | da fare | Avvio con versione compatibile: il controllo positivo della guardia | il componente resta attivo e la sezione risulta registrata |
| A-07 | da fare | Rilettura delle due politiche attraverso l'interfaccia pubblica del meccanismo comune, non dallo stato interno del componente | indicizzazione vietata, scadenza irraggiungibile |
| A-08 | da fare | **La funzione di avvio invocata due volte nella stessa richiesta**, con la prima andata a buon fine | la seconda **ritorna senza registrare di nuovo**, senza errori né avvisi, e rilegge lo stato osservabile per confermare che sia rimasto corretto: una sola sezione registrata, con le due politiche invariate. L'idempotenza poggia su uno **stato interno della classe di avvio**, non sul confronto fra le politiche trovate e quelle attese |
| A-09 | da fare | La sezione risulta **già registrata da altri alla prima invocazione**, in **due varianti**: con politiche **diverse** e con politiche **identiche** a quelle dell'albo | **conflitto in entrambi i casi**, con lo stesso esito: resta **attivo e inerte**, non registra niente, le politiche preesistenti risultano intatte alla rilettura, e c'è un avviso a ogni richiesta di amministrazione riservato a chi può attivare i componenti. La variante con politiche identiche è quella che conta: vedi la premessa |
| A-10 | da fare | Meccanismo comune compatibile, ma **filtro di scadenza portato allo stato non avviato** con il meccanismo interno che quel componente espone per le prove, poi tentativo di avvio | la registrazione è **rifiutata con il codice di errore dedicato al motore non avviato**; il componente resta **attivo e inerte**, la sezione è assente, e c'è l'avviso riservato a chi può attivare i componenti. **Nessuna disattivazione**: quella appartiene alla sola incompatibilità di versione. Il filtro si ripristina in una chiusura garantita della prova, anche se un'asserzione fallisce |

## Collaudo congiunto con la trasparenza

Con entrambi i plugin attivi sullo stesso sito: le pagine dell'albo hanno noindex e sono
fuori sitemap, quelle della trasparenza non hanno noindex e sono in sitemap. Un solo
test, due asserzioni: è l'errore invisibile a occhio, e per questo ha un test dedicato.

## La regola di crescita

Ogni bug scoperto diventa un test che prima riproduce il bug (e fallisce), poi ne
dimostra la correzione (e passa), e resta per sempre. La batteria può solo crescere: un
test non si cancella e non si disattiva per far passare la CI.
