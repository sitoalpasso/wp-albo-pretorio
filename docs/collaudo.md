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
| ALBO-23 | fatto | Si attiva il componente su un sito pulito | l'amministratore riceve l'insieme minimo di permessi del tipo atto. Si verifica **dopo** l'attivazione e non nel codice: un amministratore vede la voce di menu e riesce a creare un atto |
| ALBO-24 | fatto | Si aggiorna il componente a una versione che introduce permessi nuovi | i permessi nuovi arrivano ai ruoli che avevano già gli altri. Un aggiornamento che li assegna solo alla prima attivazione lascia scoperti i siti già installati |
| ALBO-25 | fatto | Nessun ruolo possiede i permessi del tipo atto | avviso in amministrazione che lo dice, con il nome del ruolo mancante |
| ALBO-26 | fatto | Ruolo proprio del componente, per esempio responsabile della pubblicazione | creato e configurato da questo componente, non dal meccanismo comune |
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

**Perché viene respinta anche la programmazione.** Conservare una pubblicazione programmata
che sappiamo non potersi concludere sarebbe una promessa non mantenibile: la richiesta viene
respinta come quella di pubblicazione e l'atto resta in bozza.

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
| A-30 | fatto | **Collisione su un elenco di voci**, due varianti: il primo elenco è già di qualcun altro, oppure il secondo | in entrambe: l'elenco esterno resta **invariato**, il tipo dell'albo **non nasce**, nessun permesso viene assegnato, nessuna versione viene memorizzata, e compare un avviso che nomina l'identificativo in conflitto. **La verifica precede la registrazione del tipo**: WordPress mette l'oggetto nuovo nel registro e sostituisce quello esistente, e dopo non c'è modo di smontare il solo tipo senza lasciare le cose a metà |
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
| A-43 | fatto | **Stato parziale sicuro** dopo la postcondizione mancata degli elenchi di voci (A-31), in **due momenti del ciclo di vita**: prima installazione su sito pulito, e sito dove il componente aveva già completato almeno una volta | **Ciò che resta**: il tipo resta registrato, e uno dei due elenchi può restare. Non esiste un modo pulito di smontare quel solo tipo. **Ciò che vale in entrambi i momenti**: l'installazione non prosegue, lo sbarramento **respinge** una pubblicazione da un ingresso supportato perché il tipo è ancora nostro, il contenuto resta pubblicamente irraggiungibile, e compare un avviso che dice cosa resta registrato. **Ciò che cambia**: sulla prima installazione non nascono ruolo, permessi né versione; sul sito già installato quelli **esistono già e restano intatti**, né cancellati né ampliati, e la richiesta successiva con gli elenchi ripristinati riporta allo stato completo. Dire in generale che non esistono ruolo, permessi e versione sarebbe vero solo del primo momento |

**Cosa non chiudono queste righe.** Nessun dato dell'atto oltre a oggetto, tipo e organo:
niente schermata di compilazione, niente controllo campo per campo.

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
