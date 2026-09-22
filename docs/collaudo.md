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
| ALBO-02 | da fare | Si prova a **pubblicare** senza data di fine dai **due ingressi da cui una pubblicazione può concludersi**: schermata, inserimento e aggiornamento da codice | sempre bloccato, e il rifiuto **nomina la data di fine mancante**. In bozza la data può mancare. **La richiesta di programmazione non è fra gli ingressi di questa riga**: ALBO-27 la rifiuta comunque, quindi da lì il blocco arriverebbe per un'altra ragione e la riga sarebbe verde senza dimostrare niente sulla data di fine. **L'interfaccia per programmi non è fra gli ingressi** perché non esiste: il tipo la dichiara spenta e le sue rotte sono assenti (A-15) |
| ALBO-03 | da fare | Si invoca il compito dall'esterno con il cron interno di WordPress disattivato | defissione eseguita, e **voce nel registro delle modifiche del meccanismo comune**. Il comportamento dipende da **due** lavorazioni del meccanismo comune, il compito pianificato e il registro, non dal solo compito |
| ALBO-04 | da fare | Si prova a pubblicare un atto di un tipo senza durata configurata; si cambia la durata di un tipo senza toccare codice | pubblicazione bloccata; cambio possibile |
| ALBO-04 | da fare | [attacco, statico] si cerca la costante 15 usata come durata nel codice | assente |
| ALBO-05 | da fare | Atto defisso, tutti i percorsi C-10..C-21, provati **sia da visitatore anonimo sia da utente con permessi** | irraggiungibile in entrambi i casi. La politica dichiarata e' `irraggiungibile`, quindi l'esenzione non esiste per nessun utente: provarlo solo da anonimo lascerebbe passare un componente che invece dichiara `archivio` |
| ALBO-06 | da fare | Si ispezionano le pagine dell'albo: meta robots, sitemap | noindex presente, URL fuori dalla sitemap |
| ALBO-07 **ipotesi** | da fare | Si genera il referto di un atto defisso. **Requisito da confermare**: poggia sulla prassi, non su una fonte, e l'unità che lo costruisce è bloccata dalla conferma | contiene numero, date effettive, impronta del file, autore; ristampato, è identico |
| ALBO-08 **ipotesi** | da fare | Si pubblicano più atti in parallelo (test di concorrenza). **Requisito da confermare**, come ALBO-07 | ogni atto ottiene un numero unico, progressivo, assegnato una sola volta e mai riutilizzato, neanche dopo un'operazione fallita a metà. L'assenza di buchi non è fra le garanzie |
| ALBO-08 **ipotesi** | da fare | [attacco] si invia una richiesta manipolata che tenta di scrivere il numero di repertorio. **Requisito da confermare** | il numero resta quello del sistema |
| ALBO-09 | da fare | Si tenta di sostituire l'allegato di un atto pubblicato **dichiarando una causa diversa dall'oscuramento**, e si tenta di cambiarne i dati di scheda | bloccato in tutti e due i casi; la rettifica è un nuovo atto che rinvia al precedente. **Controllo positivo**: la stessa sostituzione dichiarata come oscuramento riesce (righe di ALBO-12 qui sotto), altrimenti la riga sarebbe soddisfatta rifiutando ogni sostituzione e l'unica eccezione dichiarata non esisterebbe |
| ALBO-10 | da fare | Si pubblica e si defigge in anticipo con motivo | due voci di registro: chi, quando, perché |
| ALBO-11 | da fare | Si percorre il flusso di pubblicazione saltando la conferma sui dati personali | il flusso non si conclude; la schermata elenca i casi di rivelazione indiretta |
| ALBO-12 | da fare | Si pubblica un atto con versione oscurata | il pubblico vede solo l'oscurata; l'originale richiede il permesso |
| ALBO-12 | da fare | **Sostituzione per oscuramento** su un atto pubblicato e non scaduto: si sostituisce l'allegato con la sua versione oscurata dichiarando quella causa | riesce, e **il contatore dei giorni non riparte**: data di fine invariata, numero di repertorio invariato, stato ancora pubblicato. Si guarda l'orologio e non lo stato. È la riga che distingue questa operazione dal togliere e ripubblicare, che farebbe ripartire il termine e cambiare il repertorio |
| ALBO-12 | da fare | Dopo una sostituzione per oscuramento si rileggono le tracce | restano registrate **le impronte di tutte e due le versioni**, quella uscente e quella entrante, con chi l'ha disposta e quando (ALBO-10). Il file uscente **non è più scaricabile dal pubblico** da nessun percorso. Senza le due impronte il referto non potrebbe dire quale file era esposto in quale periodo (ALBO-07) |
| ALBO-12 | da fare | [attacco] Si tenta la sostituzione per oscuramento da chi non possiede il permesso di pubblicare, e su un atto **annullato** | rifiutata in tutti e due i casi, e l'allegato resta quello di prima. **Controllo positivo**: chi pubblica la compie su un atto pubblicato, altrimenti la riga sarebbe vera anche con una funzione che non esiste |
| ALBO-13 | da fare | Si carica un PDF che sembra una scansione | avviso al redattore, dichiarato come indizio; pubblicare resta possibile |
| ALBO-14 | da fare | Si legge un atto pubblicato con una sonda di accessibilità | testo vero, niente immagini al posto del testo, niente blocchi di copia |
| ALBO-15 | da fare | Lo stesso documento sta in albo e in trasparenza | due esposizioni, cicli di vita indipendenti, un solo file |
| ALBO-16 | da fare | Ricerca per tipo, organo, date, testo, navigando da sola tastiera | funziona, ordinata per data di pubblicazione |
| ALBO-17 | da fare | Export di un anno di atti con metadati | formato aperto, completo, reimportabile |
| ALBO-18 | da fare | Cron mai eseguito, atto scaduto ieri, tutti i percorsi più l'URL diretto dell'allegato (C-33) | tutto irraggiungibile |
| ALBO-19 | da fare | Si retrodata il timestamp del battito oltre soglia | l'avviso parte verso il responsabile configurato |
| ALBO-20 | da fare | Cache di pagina simulata attiva, poi defissione | la pagina dell'atto non resta servita dalla cache |
| ALBO-21 | da fare | Atti in scadenza nei giorni dei due cambi d'ora, fuso del sito su Roma | la scadenza cade nell'istante civile giusto |
| ALBO-22 | da fare | **Creazione e risalvataggio della bozza**: chi redige crea un atto nuovo con dati mancanti, poi riapre quella stessa bozza, la modifica e la risalva | riescono tutte e due e l'atto resta in bozza. Sono le prime due transizioni che la tabella dichiara, e provare la sola creazione lascerebbe passare un'implementazione che consente di aprire una bozza ma non di ritoccarla |
| ALBO-22 | da fare | Si manda in verifica una bozza a cui manca un dato necessario, uno per volta | bloccato ogni volta, con il nome del dato che manca. **Controllo positivo**: con tutti i dati la bozza passa in verifica, altrimenti la riga sarebbe soddisfatta rifiutando tutto |
| ALBO-22 | da fare | Il passaggio da in verifica a pubblicato tentato da chi possiede **il solo permesso di redazione** | rifiutato, e l'atto resta in verifica. **Due controlli positivi**: chi possiede il permesso di pubblicare lo compie; e **una stessa persona che possiede entrambi i permessi** compie i due passaggi di fila, con la conferma sui dati personali nel secondo. Senza il primo controllo la riga sarebbe vera anche con uno sbarramento che nega a tutti; senza il secondo imporrebbe la separazione fra le due figure, che la decisione **non** impone. Il soggetto va nominato per i permessi che ha e non per il suo ruolo, altrimenti la riga chiede di respingere una persona autorizzata |
| ALBO-22 | da fare | Si rimanda in bozza un atto in verifica, **senza** motivazione e **con** motivazione | senza, rifiutato e l'atto resta in verifica; con, riesce, l'atto torna modificabile e la motivazione finisce nel registro delle modifiche |
| ALBO-22 | da fare | Si tenta di modificare un atto **in verifica**, prima da chi redige e poi da chi pubblica | bloccato in entrambi i casi: quello che è stato verificato è quello che esce. Per correggerlo si torna in bozza, che è una transizione tracciata |
| ALBO-22 | da fare | [attacco] Si tenta di pubblicare **saltando la verifica**, da ogni ingresso da cui una pubblicazione può concludersi: schermata, inserimento e aggiornamento da codice | sempre bloccato, l'atto resta in bozza. Si osserva il valore **scritto** nella tabella dei contenuti: non attraversa mai lo stato pubblicato. La richiesta di programmazione non compare qui per la stessa ragione per cui non compare in ALBO-02: ALBO-27 la rifiuta comunque, quindi da lì il blocco non direbbe niente sulla verifica saltata |
| ALBO-22 | da fare | [attacco] Si tenta di riportare indietro un atto **pubblicato**, a bozza e a in verifica, da ogni ingresso e per ogni ruolo, **amministratore compreso** | sempre bloccato: una correzione è un atto nuovo che rinvia al precedente (ALBO-09). Nessuna esenzione per nessuno |
| ALBO-22 | da fare | **Annullamento** da chi pubblica, **senza** e **con** motivazione, e da entrambi gli stati di partenza: un atto pubblicato e uno defisso | senza motivazione, rifiutato e l'atto resta dov'era; con motivazione, riesce da tutti e due, e la motivazione finisce nel registro (ALBO-10). Dopo il passaggio si rilegge l'atto: **conserva il numero di repertorio**, risulta annullato e il motivo resta registrato su di esso. Che cosa il pubblico veda di un atto annullato e' la sotto-decisione aperta, e non la decide questa riga. Provarlo dal solo stato pubblicato lascerebbe scoperta la seconda transizione, che la tabella delle transizioni dichiara e nessun'altra riga tocca |
| ALBO-22 | da fare | [attacco] Si tenta ogni passaggio a partire da **annullato** | sempre bloccato: annullato è terminale, e lo si verifica su tutte e **cinque** le destinazioni possibili invece che su una sola. La quinta è annullato stesso: risalvare un atto annullato lasciandogli lo stato che ha è pur sempre modificarlo, e va rifiutato come gli altri quattro (ALBO-09) |
| ALBO-22 | da fare | **Defissione anticipata** da chi pubblica su un atto non ancora scaduto, **senza** e **con** motivazione; poi la stessa richiesta, con motivazione, su un atto già scaduto | senza motivazione è rifiutata e l'atto resta pubblicato e raggiungibile. Con motivazione riesce, la motivazione finisce nel registro, **lo stato memorizzato diventa defisso**, e l'atto è irraggiungibile **già nell'istante successivo e non da stanotte**: si guarda l'orologio. Le due verifiche servono tutte e due: senza quella sullo stato, un'implementazione che si limitasse ad arretrare la data lascerebbe l'atto pubblicato e la riga sarebbe verde lo stesso, pur non avendo compiuto la transizione dichiarata. Scrivere come data di fine il giorno corrente lo lascerebbe visibile fino a mezzanotte, ed è l'errore che questa riga esiste per cogliere. Sull'atto già scaduto è **rifiutata**: non c'è niente da anticipare, è già invisibile e il compito pianificato lo registrerà. Quell'atto va preparato con il compito **spento**, così che la data sia passata ma **lo stato memorizzato risulti ancora pubblicato**, e lo si verifica prima di provare: su un atto già defisso il rifiuto arriverebbe dal divieto di passare da defisso a defisso, e la riga sarebbe verde anche per un'implementazione che consente di anticipare la defissione di un atto scaduto. Dopo il rifiuto, stato e data risultano invariati. La motivazione usata qui è una **causa dell'elenco**, perché quali cause legittimino la defissione anticipata è deciso: la riga successiva prova il rifiuto di una causa fuori elenco. **Chi possa disporla resta invece una sotto-decisione aperta**, e la riga prova la forma provvisoria dichiarandola tale: qui chi pubblica |
| ALBO-22 | da fare | [attacco] Dopo una defissione anticipata riuscita, **dimostrato prima che lo stato memorizzato sia davvero defisso**, si **riporta lo stato a pubblicato** scrivendolo dritto nella banca dati, e si lascia la data di fine come la defissione l'ha scritta | l'atto resta **irraggiungibile** su tutti i percorsi dell'elenco chiuso. È la riga che smaschera un'implementazione che avesse fatto sparire l'atto commutando lo stato invece che spostando la data: quella tornerebbe visibile. La sparizione dipende dalla data letta, mai dallo stato memorizzato |
| ALBO-22 | da fare | Il compito pianificato porta un atto scaduto da pubblicato a defisso, e si verifica che **la visibilità al pubblico non dipenda da quello stato** | con il compito **spento** e la data passata, l'atto è già irraggiungibile su ogni percorso mentre nell'archivio risulta ancora pubblicato; eseguito il compito, lo stato diventa defisso e **non cambia nulla di ciò che il pubblico vede**. È la riga che impedisce di trattare "defisso" come l'interruttore della visibilità. L'invariante riguarda **la visibilità e non il flusso**: le transizioni consentite dipendono eccome dallo stato, tanto che il compito pianificato può portare a defisso solo un atto pubblicato. Ciò che non può dipendere dallo stato è che il pubblico veda l'atto |
| ALBO-22 | da fare | **Defissione anticipata con una causa fuori elenco**: si chiede la defissione anticipata di un atto regolare e non ancora scaduto indicando come motivo la sola volontà dell'amministrazione | rifiutata: l'atto resta pubblicato e raggiungibile, con la data di fine invariata. **Controllo positivo**: la stessa richiesta, sullo stesso atto, con una causa dell'elenco riesce, altrimenti la riga sarebbe soddisfatta rifiutando ogni defissione anticipata. È la riga che rende esigibile il vincolo dell'art. 124 del TUEL: il termine di pubblicazione non è un tetto che l'amministrazione può abbassare, perché la facoltà di fissarlo diverso è intestata a un'altra norma e non a chi pubblica |
| ALBO-22 | da fare | [attacco] Una transizione rifiutata non lascia **stati intermedi** | si osserva il valore scritto nella tabella dei contenuti, non quello che si rilegge alla fine: non attraversa mai lo stato richiesto e rifiutato. Uno stato corretto dopo una riparazione tardiva sarebbe indistinguibile da uno stato mai scritto |
| ALBO-27 | da fare | Si prova a **pubblicare con una data di inizio nel futuro**, dai due ingressi da cui una pubblicazione può concludersi e dalla richiesta di programmazione | sempre rifiutato, e l'atto resta in bozza: la pubblicazione non si programma. Si osserva il valore **scritto** nella tabella dei contenuti, che non attraversa mai lo stato pubblicato né quello programmato. **Controllo positivo**: la stessa pubblicazione senza data di inizio nel futuro riesce, altrimenti la riga sarebbe soddisfatta rifiutando ogni pubblicazione |
| ALBO-27 | da fare | Si pubblica un atto e si rilegge la **data di inizio** | è quella dell'istante in cui la transizione è avvenuta, confrontata con l'orologio, e non un valore che chi pubblica ha potuto scegliere. Da questa data decorrono termini di legge, quindi una data scelta a mano sarebbe una dichiarazione falsa e non una comodità |
| ALBO-27 | da fare | [attacco] Si tenta di cambiare la data di inizio di un atto **pubblicato**, da ogni ingresso e per ogni ruolo, **amministratore compreso** | sempre bloccato, come ogni altra modifica di un atto pubblicato (ALBO-09). Nessuna esenzione per nessuno |
| ALBO-23 | fatto | Si attiva il componente su un sito pulito | l'amministratore riceve l'insieme minimo di permessi del tipo atto. Si verifica **dopo** l'attivazione e non nel codice: un amministratore vede la voce di menu e riesce a creare un atto |
| ALBO-24 | fatto | Si aggiorna il componente a una versione che introduce permessi nuovi | i permessi nuovi arrivano ai ruoli che avevano già gli altri. Un aggiornamento che li assegna solo alla prima attivazione lascia scoperti i siti già installati |
| ALBO-25 | fatto | Nessun ruolo possiede i permessi del tipo atto | avviso in amministrazione che lo dice, con il nome del ruolo mancante |
| ALBO-26 | fatto | Ruolo proprio del componente, per esempio responsabile della pubblicazione | creato e configurato da questo componente, non dal meccanismo comune |
| [attacco] | da fare | Un amministratore tenta di cancellare un atto pubblicato | bloccato: nessuna esenzione |
| [attacco, statico] | da fare | Si cerca ogni rotta REST o AJAX registrata senza nonce o `permission_callback` | nessuna |

**ALBO-22 ha ora le sue righe**, perché la decisione è stata chiusa il 2026-09-21: le
transizioni consentite, chi le compie e cosa accade a una rifiutata stanno nella nota in
`requisiti.md`, il diagramma in `architettura.md`. Le righe sono **da fare**: il codice non
esiste ancora, e nascono prima di esso come prescrive il processo.

**Tre sotto-decisioni restano aperte**, ed è visibile nelle righe. Se un atto annullato prima
della scadenza resti visibile al pubblico non è deciso, quindi **non ha riga**: scriverla
adesso significherebbe misurare quello che il codice avrà fatto. Chi possa disporre la
defissione anticipata è attribuito a chi pubblica in via provvisoria, e la riga lo prova in
quella forma dichiarandola tale. Se il giorno civile basti come granularità del termine è
aperto allo stesso modo: le righe provano la forma provvisoria, cioè il giorno precedente,
e non vincolano la scelta se un giorno il meccanismo comune tenesse un termine più fine.
**Una quarta sotto-decisione è invece chiusa**, il 2026-09-22, sul testo vigente dell'art.
124 del TUEL, che intesta a un'altra norma e non a chi pubblica la facoltà di fissare un
termine diverso: accorciare il termine di un atto regolare non è una funzione del
componente. L'art. 134, per cui dal decimo giorno di pubblicazione decorre l'esecutività
della deliberazione, corrobora la lettura ma non disciplina gli effetti di un'interruzione. Le
cause pacifiche sono quelle in cui la pubblicazione non doveva esistere in quella forma. La
riga che rifiuta una defissione anticipata con una causa fuori elenco è scritta qui sopra,
ed è ciò che rende la restrizione esigibile invece di lasciarla vivere in una nota.

La riga che le sta accanto, quella che prova che la sostituzione del file con la versione
oscurata **non** ferma il termine, è stata scritta il 2026-09-22 fra le righe di ALBO-12.
Prova una **scelta di prodotto** e non una regola di legge: che la diffusione vietata debba
cessare viene dai testi, che il periodo continui a correre dopo l'oscuramento non lo dice
nessuna fonte, e il componente sceglie di non far ripartire il conteggio perché ripartire
guasterebbe l'adempimento. Fino al 2026-09-22 la riga non poteva esistere: la sostituzione
era la risposta al caso dei dati eccedenti, mentre la riga di ALBO-09 prescriveva l'opposto,
cioè che sostituire l'allegato di un atto pubblicato fosse bloccato. Le due prescrizioni non
potevano stare insieme, ed è stato deciso quale cede: l'immodificabilità di ALBO-09 conserva
**un'unica eccezione dichiarata**, che toglie informazione e non ne aggiunge, e la riga di
ALBO-09 la nomina come proprio controllo positivo.

**Che cosa queste righe non provano, e nessuna riga potrebbe provare.** Il componente non
può verificare che il file entrante sia davvero la versione oscurata di quello uscente: per
un programma restano due file diversi, e nessuna prova automatica distingue un oscuramento
da una sostituzione di contenuto. Quello che le righe pretendono è tutto ciò che è
verificabile, cioè la causa dichiarata, il rifiuto di ogni altra, le due impronte, la traccia
di chi e quando, e il termine che continua a correre. La responsabilità di che cosa contenga
il file entrante resta di chi dispone la sostituzione, ed è la ragione per cui l'operazione
è tracciata invece che impedita.

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
| A-01 | fatto | La dipendenza è dichiarata in due posti: intestazione `Requires Plugins` e versione di interfaccia controllata all'avvio | entrambe presenti e coerenti. Sul controllo di WordPress si verifica il **codice** di errore, non il testo del messaggio, che sul sito italiano è tradotto |
| A-02 | fatto | Il file principale viene caricato senza che il meccanismo comune sia già caricato | nessun errore grave e nessuna chiamata: l'avvio è rimandato all'aggancio che scatta a componenti caricati |
| A-03 | fatto | Avvio con le funzioni del meccanismo comune non definite. Si dimostra prima che il componente risulti fra quelli attivi | resta **attivo e inerte**: nessun errore grave, nessuna sezione registrata, nessun avvio parziale. Avviso proprio a ogni richiesta di amministrazione, visibile solo a chi può attivare i componenti, che porta tre valori verificati uno per uno: il nome del componente fermo, il nome del meccanismo comune da installare e la **versione richiesta**. Si verifica anche che nel testo compaia **un solo** numero di versione: nessuna versione è stata trovata, perché il meccanismo comune non c'è, ed è ciò che distingue questo avviso da quello dell'incompatibilità |
| A-04 | fatto | Criterio di compatibilità della versione, sui tre casi che contano | versione inferiore con stesso numero maggiore: incompatibile. Numero maggiore diverso: incompatibile. Versione superiore con stesso numero maggiore: compatibile |
| A-05 | fatto | Effetto completo della guardia, in un processo separato con versione incompatibile, con una richiesta di amministrazione come prima richiesta | componente disattivato, **sezione assente fra quelle registrate**, nessun errore grave, avviso presente con la versione richiesta e quella trovata. Tre precondizioni dimostrate prima: il componente era attivo, la procedura di avvio **non è agganciata** in quell'esecuzione, e la bacheca è pulita. Le ultime due servono a rendere l'avviso attribuibile: se la procedura girasse da sola all'avvio della suite, il meccanismo comune lascerebbe un avviso agganciato con una chiusura anonima, che nessuno può rimuovere, e la prova resterebbe verde anche se la propria chiamata non producesse niente |
| A-06 | fatto | Avvio con versione compatibile: il controllo positivo della guardia | il componente resta attivo e la sezione risulta registrata |
| A-07 | fatto | Rilettura delle due politiche attraverso l'interfaccia pubblica del meccanismo comune, non dallo stato interno del componente | indicizzazione vietata, scadenza irraggiungibile |
| A-08 | fatto | **La funzione di avvio invocata due volte nella stessa richiesta**, con la prima andata a buon fine | la seconda **ritorna senza registrare di nuovo**, senza errori né avvisi, e rilegge lo stato osservabile per confermare che sia rimasto corretto: una sola sezione registrata, con le due politiche invariate. L'idempotenza poggia su uno **stato interno della classe di avvio**, non sul confronto fra le politiche trovate e quelle attese |
| A-09 | fatto | La sezione risulta **già registrata da altri alla prima invocazione**, in **due varianti**: con politiche **diverse** e con politiche **identiche** a quelle dell'albo | **conflitto in entrambi i casi**, con lo stesso esito: resta **attivo e inerte**, non registra niente, le politiche preesistenti risultano intatte alla rilettura, e c'è un avviso a ogni richiesta di amministrazione riservato a chi può attivare i componenti. La variante con politiche identiche è quella che conta: vedi la premessa |
| A-10 | fatto | Meccanismo comune compatibile, ma **filtro di scadenza portato allo stato non avviato** con il meccanismo interno che quel componente espone per le prove, poi tentativo di avvio | la registrazione è **rifiutata con il codice di errore dedicato al motore non avviato**; il componente resta **attivo e inerte**, la sezione è assente, e c'è l'avviso riservato a chi può attivare i componenti. **Nessuna disattivazione**: quella appartiene alla sola incompatibilità di versione. Il filtro si ripristina in una chiusura garantita della prova, anche se un'asserzione fallisce |

## Il tipo atto, i permessi e i ruoli

Stesso prefisso `A-` delle righe qui sopra, numerazione che prosegue: anche queste
costruiscono l'infrastruttura su cui i requisiti poggeranno, e nessuna di esse chiude un
requisito da sola.

**Tre cose diverse, che questa tabella tiene separate perché confonderle produce una
promessa che il codice non mantiene.**

| Categoria | Cosa comprende | Che cosa è garantito |
|---|---|---|
| **Ingressi supportati e intercettati** | la schermata di amministrazione, l'inserimento e l'aggiornamento da codice, la richiesta di pubblicazione programmata | l'atto **non resta né pubblicato né programmato**: torna o resta in bozza, e la riga nella tabella dei contenuti non attraversa mai quegli stati |
| **Superficie assente** | l'interfaccia per programmi (REST) del tipo e dei due elenchi di voci | **non esiste**. Non è un ingresso intercettato: è una superficie che non c'è, e si verifica come assenza |
| **Scavalcamento grezzo** | la funzione di WordPress che pubblica un contenuto scrivendo direttamente nella banca dati, chiamata da codice di terzi | lo stato **cambia davvero** a pubblicato, e il componente non lo impedisce. La garanzia è un'altra: quel contenuto resta **irraggiungibile su ogni superficie pubblica**, per come il tipo è configurato |

**Il componente non promette che nessuno stato pubblicato sia possibile. Promette che nessun
atto sia raggiungibile.**

**Perché in questa fase il tipo atto non è pubblico.** Il documento principale di un atto ha
bisogno della consegna protetta del meccanismo comune, che non esiste ancora: senza, un file
caricato risponde al proprio indirizzo diretto, e lo stato dell'atto non lo protegge. Finché
quella consegna non c'è, il tipo è visibile in amministrazione e non interrogabile dal
pubblico, e la pubblicazione si apre in una lavorazione dedicata, tutta insieme, quando i
controlli che impediscono un'esposizione oltre il termine esistono davvero.

**Perché viene respinta anche la programmazione.** In questa lavorazione il motivo era
provvisorio: conservare una pubblicazione programmata che sappiamo non potersi concludere
sarebbe una promessa non mantenibile, quindi la richiesta viene respinta come quella di
pubblicazione e l'atto resta in bozza. Dal 2026-09-22 il motivo è diventato definitivo ed è
ALBO-27: la pubblicazione non si programma, perché l'albo sostituisce la bacheca di carta e
lì programmare non era possibile. Quando la pubblicazione si aprirà, questo rifiuto resterà,
e le righe che lo provano sono quelle di ALBO-27 e non queste, che misurano una lavorazione
in cui la pubblicazione era chiusa per tutti.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| A-11 | fatto | Si registra il tipo **senza che la sezione risulti registrata da questo componente** | rifiutato **dal componente stesso, prima di chiedere niente al meccanismo comune**, e il tipo **non risulta fra quelli di WordPress**: la verifica rilegge, non assume. Il rifiuto è nostro perché la precondizione non è che il meccanismo comune ci sia, è che la sezione sia nostra |
| A-12 | fatto | Registrazione riuscita, **controllo positivo** | il tipo risulta registrato presso il meccanismo comune e la sua sezione è quella dell'albo, letti dall'interfaccia pubblica di quel componente e non dallo stato interno di questo. Senza questa riga, ogni riga negativa qui sotto sarebbe soddisfacibile non registrando niente |
| A-13 | fatto | Si rileggono gli argomenti con cui il tipo è registrato | non pubblico, non interrogabile dal pubblico, senza indirizzo proprio, senza elenco pubblico, fuori dalla ricerca interna; e insieme visibile in amministrazione, con voce di menu propria. **Sono valori scritti uno per uno e non lasciati derivare**: WordPress ne deriva una parte, e una derivazione che cambiasse in una versione futura non deve poter aprire il tipo da sola |
| A-14 | fatto | Si rileggono i due elenchi di voci, tipo di atto e organo | registrati, **piatti**, non pubblici, non interrogabili dal pubblico, senza indirizzo proprio. Piatti perché il dominio non ha rapporti fra voce e sottovoce: né i tipi di atto né gli organi si contengono a vicenda. In questa fase non c'è nessun riquadro di scelta, per non consegnare un campo a testo libero su un elenco controllato: l'interfaccia di scelta arriva con la schermata di compilazione |
| A-15 | fatto | Interfaccia per programmi dichiarata spenta, e **assenza delle tre rotte** | il valore riletto dal tipo è spento, e fra le rotte del server non compaiono né quella degli atti né quelle dei due elenchi di voci. **Precondizione**: il server espone almeno una rotta propria di WordPress, altrimenti l'assenza sarebbe vera per niente |
| A-16 | fatto | [statico] si cerca nei sorgenti la registrazione diretta di un tipo di contenuto e quella di un elenco di voci | la prima **assente**, perché il tipo passa dal meccanismo comune. La seconda **presente**, perché per gli elenchi di voci quel meccanismo non esiste: è una differenza che si vede qui invece di scoprirla nel codice |
| A-17 | fatto | [statico] si cercano nei sorgenti i nomi dei permessi derivati dal tipo | assenti: si chiedono al meccanismo comune. Un nome di permesso riscritto a mano e sbagliato di una lettera è un permesso che nessuno possiede e di cui nessuno si accorge |
| A-18 | fatto | Permessi separati da quelli degli articoli, provati **chiedendo il permesso a utenti veri** | un utente con il solo ruolo Autore di WordPress non può creare, modificare né pubblicare atti; un amministratore può crearli e modificarli. Leggere una tabella di ruoli non basta |
| A-19 | fatto | Il ruolo proprio del componente | esiste, ha i permessi del suo insieme e non quelli degli articoli. Chi redige e chi pubblica restano permessi **distinti** |
| A-20 | fatto | Si esegue l'assegnazione dei permessi **due volte** | la seconda non duplica e non toglie, e un permesso che il sito avesse aggiunto per conto suo a quel ruolo resta. La verifica rilegge dai ruoli |
| A-21 | fatto | **Aggiornamento senza nuova attivazione**: un sito con la versione precedente memorizzata riceve un insieme con un permesso in più | il permesso nuovo arriva ai ruoli che avevano già gli altri, e la versione memorizzata risulta aggiornata. È la riga che distingue un meccanismo di aggiornamento da uno di sola attivazione: senza, i siti già installati resterebbero scoperti |
| A-22 | fatto | Nessun ruolo possiede i permessi del tipo | avviso in amministrazione, visibile solo a chi può attivare i componenti, che nomina il ruolo che dovrebbe averli. **Precondizione dimostrata**: prima della rimozione quell'avviso non c'è |
| A-23 | fatto | **I tre ingressi supportati**: schermata, inserimento e aggiornamento da codice, richiesta di programmazione | nessuno dei tre lascia l'atto pubblicato né programmato: resta in bozza. Per ciascuno si dimostra prima che lo stato richiesto fosse davvero quello, altrimenti "è rimasto in bozza" sarebbe vero per niente |
| A-24 | fatto | **Nessun intervallo pubblicato** sui tre ingressi supportati | si osserva il valore **scritto** nella tabella dei contenuti, non quello finale: non è mai stato né pubblicato né programmato. Vale per i tre ingressi e non per lo scavalcamento, dove lo stato pubblicato è il punto di partenza |
| A-25 | fatto | [attacco] **Scavalcamento grezzo**: si pubblica un atto scrivendo direttamente lo stato, con data di fine **valida e futura** | lo stato risulta davvero pubblicato, e il meccanismo comune dice che il contenuto **non è scaduto**: così la riga dimostra la chiusura del tipo e non il filtro di scadenza. L'atto è comunque irraggiungibile: indirizzo non trovato per un visitatore non autenticato, assente dalla ricerca interna, assente dalla mappa per i motori. **Precondizione**: un contenuto ordinario pubblicato nello stesso momento è raggiungibile, altrimenti il non trovato direbbe soltanto che nell'ambiente di prova gli indirizzi non funzionano |
| A-26 | fatto | I **due** posti in cui finisce il motivo di un rifiuto | quello della schermata sta sull'utente e sull'atto insieme e si consuma alla prima lettura; quello della chiamata da codice **non si memorizza** e viene segnalato a chi programma. Non esiste un terzo posto persistente sull'atto, perché non esiste la programmazione che lo richiederebbe |
| A-27 | fatto | Due utenti che tentano la pubblicazione **dello stesso atto** | ciascuno vede il proprio messaggio e non quello dell'altro |

### Postcondizioni e collisioni

**Le prime due righe correggono lo stesso errore visto da due lati.** Le righe qui sopra
verificano che il componente faccia la cosa giusta quando tutto va bene, e che rifiuti quando
qualcosa va male nel suo perimetro. Non verificavano la domanda più importante: **di chi è la
roba su cui si sta agendo.** La presenza delle funzioni del meccanismo comune dice che quel
componente c'è, non che la sezione sia nostra; il nome di un tipo di contenuto dice come si
chiama, non chi lo ha registrato. Un componente che decide su quelle basi governa contenuti
di altri, e si dichiara inerte mentre non lo è.

| # | Stato | Il test, in parole | Esito atteso |
|---|---|---|---|
| A-28 | fatto | **Avvio non riuscito, poi il percorso normale di avvio dei tipi**: qualcun altro registra la sezione dell'albo prima, con le stesse politiche, poi si esegue il percorso come in una richiesta vera | non nasce niente: nessun tipo, nessuno dei due elenchi di voci, nessun ruolo, nessun permesso su nessun ruolo, nessuna versione memorizzata. **Il componente è inerte davvero**, non solo nella dichiarazione dell'avvio. Ogni passaggio dipende dal fatto che la sezione risulti registrata **da questo componente**, non dal fatto che il meccanismo comune sia caricato: sono due cose diverse |
| A-29 | fatto | **Lo sbarramento non tocca contenuti di altri**: un componente esterno registra un proprio tipo con lo stesso identificativo mentre l'avvio dell'albo fallisce, poi pubblica un proprio contenuto | la pubblicazione **riesce** e il contenuto resta pubblicato. Lo sbarramento agisce solo quando risulta che il tipo è stato registrato da questa istanza dell'albo: decidere sul solo nome vuol dire riportare in bozza contenuti che non sono nostri |
| A-30 | fatto | **Collisione su un elenco di voci**, due varianti: il primo elenco è già di qualcun altro, oppure il secondo | in entrambe: l'elenco esterno resta **invariato**, il tipo dell'albo **non nasce**, nessun permesso viene assegnato, nessuna versione viene memorizzata, e compare un avviso che nomina l'identificativo in conflitto. **La verifica precede la registrazione del tipo**: WordPress mette l'oggetto nuovo nel registro e sostituisce quello esistente, e dopo non c'è modo di smontare il solo tipo senza lasciare le cose a metà. **La verifica precede ogni tentativo e non solo il primo**: vedi A-45 |
| A-31 | fatto | **Postcondizione degli elenchi di voci**: si controlla l'esito delle due registrazioni e si rilegge il registro prima di considerare **completa** la registrazione | la registrazione risulta completa **solo se** entrambi gli elenchi risultano registrati e agganciati al tipo. **La proprietà del tipo però non si perde**: l'oggetto del tipo si conserva appena il meccanismo comune lo registra, prima degli elenchi, così lo sbarramento continua a proteggerlo. Cosa resta registrato, nei due momenti del ciclo di vita, sta in A-43 |
| A-32 | fatto | **Ruolo con lo stesso nome**, due varianti: creato da una versione precedente dell'albo, oppure di qualcun altro | il ruolo dell'albo si riconosce da un **permesso marcatore** che solo l'albo assegna, e viene aggiornato. Quello di altri **non si adotta**: resta intatto, nessun permesso viene assegnato, nessuna versione memorizzata, e compare un avviso che nomina il ruolo in conflitto. Adottarlo vorrebbe dire consegnare i permessi dell'albo a chiunque possieda già quel ruolo |
| A-33 | fatto | **Autoriparazione**: si cancella la sola versione memorizzata, lasciando ruolo e permessi | il lavoro riparte alla richiesta successiva, **riconosce il proprio ruolo dal marcatore** invece di scambiarlo per altrui, e la versione torna memorizzata |
| A-34 | fatto | **L'avviso generico non dipende dal dato che deve sostituire**: rifiuto dalla schermata, il dato temporaneo sparisce subito, poi si costruisce l'indirizzo di ritorno | l'indicatore generico c'è lo stesso, perché vive nella richiesta corrente e non nel dato temporaneo, e la schermata mostra l'avviso generico. Farlo dipendere dal dato temporaneo vuol dire non avere nessun avviso proprio nel caso in cui serve |
| A-35 | fatto | **L'origine del salvataggio**: invio vero della schermata classica con la sua azione, il suo identificativo e il suo codice di sicurezza; e chiamata da codice durante una richiesta di amministrazione, senza quel modulo | nel primo caso il motivo finisce nel deposito dell'utente, nel secondo **no**, e la segnalazione va a chi programma. Un componente che salva durante una richiesta di amministrazione non è una persona davanti a una schermata |
| A-36 | fatto | **Inserimento annidato**: un secondo inserimento avviene fra la preparazione e la scrittura del primo | il motivo del primo non viene perso né attribuito al secondo |
| A-37 | fatto | **La scrittura della versione memorizzata fallisce** | il lavoro **non dichiara successo**, la versione riletta resta quella precedente, e alla richiesta successiva viene ritentato. Dichiarare concluso un aggiornamento mai scritto vuol dire non rifarlo mai più |
| A-38 | fatto | **Postcondizione dei permessi**: si rileggono i ruoli dopo averli scritti | l'assegnazione risulta riuscita solo se **ogni ruolo toccato**, dichiarato o completato automaticamente, possiede davvero ogni permesso del proprio insieme. Vedi A-39 |
| A-39 | fatto | **Postcondizione dei permessi su ogni ruolo toccato**, non solo su quelli dichiarati: si sabota la scrittura di un permesso nuovo sul **solo ruolo completato automaticamente** | l'assegnazione fallisce, l'installazione non dichiara successo, la versione riletta resta la precedente, e alla richiesta successiva il lavoro viene ritentato. Verificare i soli ruoli dichiarati lascia scoperti proprio quelli che il componente completa da sé |
| A-40 | fatto | **Il permesso marcatore del ruolo non viene scritto**: si sabota la sua persistenza mentre il ruolo viene creato | nessun successo e nessuna versione memorizzata. **E c'è una via di recupero**: il ruolo appena creato senza marcatore viene rimosso, e la rimozione si rilegge. Senza recupero quel ruolo diventerebbe una collisione permanente, perché alla richiesta dopo il componente lo troverebbe senza marcatore e lo direbbe di altri, per sempre. Se anche la rimozione non riesce, l'avviso nomina il ruolo da togliere a mano |
| A-41 | fatto | **Due domande diverse, e non una sola**: dopo una registrazione riuscita un componente esterno sostituisce il tipo, oppure il primo elenco di voci, oppure il secondo. Tre varianti | **il tipo sostituito** non è più nostro, e lo sbarramento non tocca i suoi contenuti: governarli sarebbe governare roba di altri. **Un elenco sostituito o perso** non toglie la proprietà del tipo: lo sbarramento **continua a respingere** una pubblicazione da un ingresso supportato, perché quel tipo è ancora il nostro. In tutte e tre l'installazione si ferma, perché ha bisogno dell'insieme completo. Confondere le due domande è il peggiore dei due errori possibili: la risposta sbagliata **apre** lo sbarramento invece di chiuderlo |
| A-42 | fatto | **Il segno nell'indirizzo di ritorno nasce solo da un invio riconosciuto**, in due direzioni: invio vero della schermata con il dato temporaneo sparito, e chiamata da codice durante una richiesta di amministrazione | nel primo caso il segno c'è lo stesso e la schermata mostra l'avviso generico; nel secondo **non c'è né il dettaglio né il segno**. Segnare prima di distinguere farebbe comparire a una persona un avviso per un salvataggio che non ha chiesto |
| A-43 | fatto | **Stato parziale sicuro** dopo la postcondizione mancata degli elenchi di voci (A-31), in **due momenti del ciclo di vita**: prima installazione su sito pulito, e sito dove il componente aveva già completato almeno una volta | **Ciò che resta**: il tipo resta registrato, e uno dei due elenchi può restare. Non esiste un modo pulito di smontare quel solo tipo. **Ciò che vale in entrambi i momenti**: l'installazione non prosegue, lo sbarramento **respinge** una pubblicazione da un ingresso supportato perché il tipo è ancora nostro, il contenuto resta pubblicamente irraggiungibile, e compare un avviso che dice cosa resta registrato. **Ciò che cambia**: sulla prima installazione non nascono ruolo, permessi né versione; sul sito già installato quelli **esistono già e restano intatti**, né cancellati né ampliati, e la richiesta successiva con gli elenchi ripristinati riporta allo stato completo **e ritenta davvero l'installazione**, che avanza la versione memorizzata. Dire in generale che non esistono ruolo, permessi e versione sarebbe vero solo del primo momento. **La versione non si riallinea a mano prima del recupero**: riportarla alla corrente renderebbe vuota la verifica del ritentativo, perché il lavoro uscirebbe prima per un motivo che non c'entra |
| A-44 | fatto | [attacco, statico] **Nel ramo non resta l'osservazione ambigua che A-41 ha eliminato**: si cercano nei sorgenti e nelle prove le tre forme in cui compariva, cioè la chiamata al metodo pubblico, la sua dichiarazione e la proprietà che leggeva | nessuna delle tre compare. È una riga statica perché deve esserlo: un metodo che nessuno chiama non lo esercita nessuna prova di funzionamento, quindi la verifica continua resta verde mentre il metodo legge una proprietà che non esiste più. Chiamarlo sarebbe un errore di esecuzione, e trovarlo rimetterebbe a disposizione la domanda ambigua che la correzione ha eliminato |
| A-45 | fatto | **Il ritentativo non sostituisce un elenco diventato di altri**: si provoca la postcondizione parziale di A-31, un componente esterno registra l'elenco che manca, poi l'albo ritenta nella stessa richiesta | il ritentativo si ferma con l'errore di collisione; l'elenco esterno è ancora lo **stesso oggetto** e i suoi argomenti sono invariati; la registrazione resta incompleta; l'installazione non prosegue; lo sbarramento continua a respingere una pubblicazione da un ingresso supportato, perché il tipo è ancora nostro. **La verifica delle collisioni appartiene al flusso di `registra()`, prima di ogni tentativo**, e non alla sola registrazione del tipo: là dentro il ritentativo la salta, perché il tipo è già nostro, e cancella l'elenco dell'altro. È la garanzia di A-30 che si perde alla seconda richiesta |
| A-46 | fatto | **L'avviso di registrazione incompleta sparisce quando il recupero riesce**, in due direzioni: subito dopo il fallimento della postcondizione, e dopo la seconda chiamata che nella stessa richiesta completa la registrazione | dopo il fallimento l'avviso c'è; dopo il recupero **non viene più mostrato**, perché descrive uno stato che non è più vero e chi lo legge in bacheca non ha modo di sapere che è vecchio. Gli avvisi hanno una chiave e si tolgono per chiave: si tolgono i tre avvisi del tentativo di registrazione, non tutti gli avvisi. **Controllo che non sia una cancellazione indiscriminata**: l'avviso di un'altra superficie, quello sui permessi che nessun ruolo possiede, continua a comparire |
| A-47 | fatto | **Gli elenchi di voci non nascono mai con i permessi predefiniti di WordPress**, in due direzioni: controllo positivo che rilegge `get_taxonomy()` dopo una registrazione riuscita, e ramo negativo con la corrispondenza dei permessi non disponibile | nel controllo positivo i quattro permessi di ciascun elenco, amministrare, modificare, cancellare e assegnare le voci, sono **esattamente i nomi derivati** che il meccanismo comune restituisce per il tipo, e nessuno di essi è `manage_categories` o `edit_posts`. Nel ramo negativo la registrazione degli elenchi **fallisce con errore prima di chiamare `register_taxonomy`** e **nessun elenco nasce**. Registrare con permessi vuoti non lascia l'elenco senza permessi: fa ricadere WordPress sui propri predefiniti, cioè consegna il vocabolario dell'albo a chi amministra le categorie e l'assegnazione a chi scrive articoli. Fra i due modi di sbagliare è quello che **apre**, ed è esattamente ciò che il commento della funzione dichiarava di voler evitare. **Il ramo negativo non è raggiungibile dal flusso di `registra()`**: la corrispondenza manca soltanto quando il meccanismo comune non è caricato, e in quel caso la precondizione sulla paternità della sezione ferma prima. Si prova perciò nell'avvio della suite **senza il meccanismo comune**, dove quella condizione è reale e non simulata, entrando nella funzione per riflessione: nessun gancio di prova nel codice di produzione. La riga verifica anche che per la via normale `registra()` si fermi prima, con il proprio errore, e che nemmeno così nasca un elenco |

**Cosa non chiudono queste righe.** Nessun dato dell'atto oltre all'oggetto: tipo e organo
esistono come elenchi di voci e non si assegnano ancora all'atto. Niente schermata di
compilazione, niente controllo campo per campo.

**Sulla pubblicazione la garanzia va detta per intero**, perché "nessuna pubblicazione per
nessuno" contraddice A-25 e prometterebbe più di quello che il codice mantiene. I tre
ingressi supportati non portano un atto a pubblicato né a programmato. La funzione che scrive
lo stato direttamente nella banca dati non passa dallo sbarramento: chiamata da codice di
terzi **può portare un atto a pubblicato**, e quello che si garantisce in quel caso è che
resti pubblicamente irraggiungibile.

ALBO-01 e ALBO-02 restano intatte. Il divieto di cancellare un atto pubblicato
**non esiste ancora**: finché non c'è, chi ha il permesso di cancellare un atto lo cancella,
pubblicato compreso.

## Collaudo congiunto con la trasparenza

Con entrambi i plugin attivi sullo stesso sito: le pagine dell'albo hanno noindex e sono
fuori sitemap, quelle della trasparenza non hanno noindex e sono in sitemap. Un solo
test, due asserzioni: è l'errore invisibile a occhio, e per questo ha un test dedicato.

## La regola di crescita

Ogni bug scoperto diventa un test che prima riproduce il bug (e fallisce), poi ne
dimostra la correzione (e passa), e resta per sempre. La batteria può solo crescere: un
test non si cancella e non si disattiva per far passare la CI.
