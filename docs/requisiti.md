# Requisiti: cosa deve fare l'albo pretorio

Questo documento spiega in linguaggio semplice cosa il plugin deve fare e perché. Ogni
requisito ha un identificativo (ALBO-01 fino ad ALBO-27): è lo stesso usato nei test, nei
commit e nelle discussioni, così si può sempre risalire dal codice al motivo per cui
esiste. In fondo c'è il **catalogo corrente dei requisiti**, con le fonti normative e le
decisioni aperte marcate esplicitamente. Non è una specifica completa e non lo sarà finché
ALBO-07 e ALBO-08 restano da confermare.

## L'idea in dieci righe

L'albo pretorio online è la bacheca ufficiale di un ente pubblico: gli atti (delibere,
determinazioni, ordinanze, avvisi) vi restano esposti per un periodo stabilito, e quella
esposizione produce effetti legali. Dal 2010 la pubblicazione sul sito ha sostituito la
bacheca fisica (legge 69/2009, art. 32).

Il punto che decide tutto il progetto: **il rischio principale non è pubblicare male, è
non smettere di pubblicare**. Gli atti contengono spesso dati personali, e la legge ne
autorizza l'esposizione solo per il periodo previsto. Due provvedimenti del Garante
privacy del marzo 2026 (doc. web 10240362 e 10246037) hanno sanzionato esattamente
questo: atti rimasti online per oltre diciotto mesi e atti che rivelavano dati sulla
salute. Quindi l'albo è prima di tutto **un sistema a scadenza**: ogni atto ha una data
di fine e, arrivata quella data, deve sparire dalla vista pubblica da solo, anche se
nessuno se ne ricorda, anche se il sito non riceve visite.

## I requisiti, a gruppi

### L'atto e i suoi campi (ALBO-01, ALBO-02)

Un atto non è un PDF buttato in una pagina: è una scheda con i suoi dati (tipo, oggetto,
organo che l'ha adottato, data di adozione, data di inizio e di fine pubblicazione) più il
**documento principale**, che è uno e uno solo, e gli **allegati ulteriori**, che sono
facoltativi e possono non esserci affatto (ALBO-01). Delle due date, quella di inizio non si
sceglie: la scrive il sistema nel momento in cui l'atto diventa pubblico (ALBO-27).

**Salvare e pubblicare sono due momenti diversi, e il requisito vale sul secondo.** Una
bozza si salva anche incompleta: è così che si lavora a un atto prima di avere tutti i
pezzi, e un sistema che pretende tutto al primo salvataggio costringe a inventare
segnaposto. È il **passaggio a pubblicato** che pretende ogni dato necessario, e lì il
controllo è campo per campo. Il numero di repertorio non è fra i dati che si chiedono a chi
compila: lo assegna il sistema alla prima pubblicazione (ALBO-08), quindi pretenderlo prima
del salvataggio sarebbe una richiesta impossibile da soddisfare.

**La data di fine è obbligatoria per pubblicare** e non esiste l'atto pubblicato "per
sempre" (ALBO-02), perché è il caso sanzionato dal Garante. In bozza può mancare, perché una
bozza non è esposta a nessuno.

### La scadenza, su tre strati (ALBO-03, ALBO-18, ALBO-19, ALBO-20, ALBO-21)

La defissione (la rimozione dell'atto scaduto dalla vista pubblica) non può dipendere da
una sola cosa che potrebbe non accadere. Per questo lavora su tre strati:

1. **Un orologio vero** (ALBO-03): un cron di sistema, esterno a WordPress, esegue ogni
   giorno il lavoro pesante. Il cron interno di WordPress non basta, perché parte solo
   quando qualcuno visita il sito: di notte o nei giorni senza traffico non farebbe
   nulla, ed è esattamente lo scenario sanzionato.
2. **Un filtro alla lettura** (ALBO-18): la scadenza è una condizione controllata a ogni
   richiesta, su ogni percorso con cui il pubblico può raggiungere un atto (pagina,
   elenco, ricerca, feed, sitemap, API REST, download dell'allegato). Anche se il cron
   non girasse mai, l'atto scaduto risulta comunque irraggiungibile. È lo strato che non
   può "non partire", perché non è un evento: è una condizione.
3. **Un battito di controllo** (ALBO-19): ogni esecuzione riuscita del cron lascia un
   timestamp; se il timestamp invecchia oltre soglia, il responsabile riceve un avviso.
   Un cron fermo, altrimenti, non lo scopre nessuno.

Completano il gruppo: nessuna cache deve continuare a servire la pagina di un atto
defisso (ALBO-20), e la scadenza si calcola sull'ora civile italiana con il fuso del
sito, mai sull'ora del server (ALBO-21), perché un cron impostato in UTC girerebbe due
ore avanti o indietro rispetto all'ora legale.

### Sparire davvero (ALBO-05, ALBO-06)

Alla scadenza **l'indirizzo pubblico dell'atto smette di rispondere, per chiunque**, perche'
l'albo dichiara al meccanismo comune la politica `irraggiungibile`. Non e' la politica
`archivio`, che l'albo non usa. Se l'atto va conservato, lo si consulta dall'amministrazione
e in futuro da un archivio riservato: e' una schermata di amministrazione e **non fa tornare
a rispondere quell'indirizzo**. Il testo che segue descrive quell'archivio ad
accesso controllato, non in una pagina pubblica meno visibile (ALBO-05). E finché è
pubblicato, le sue pagine dicono ai motori di ricerca di non indicizzarlo: meta
`noindex`, esclusione dalla sitemap (ALBO-06). Attenzione: è la regola **opposta** a
quella dell'amministrazione trasparente, dove l'indicizzazione è obbligatoria. Per
questo la politica di indicizzazione è un parametro dichiarato, mai un default.

### Il registro non si tocca (ALBO-08, ALBO-09, ALBO-10)

> **Attenzione: repertorio e referto sono due ipotesi, non due impegni.** ALBO-07 (referto)
> e ALBO-08 (repertorio) poggiano sulla prassi della pubblicita' legale, che non e' una
> fonte. Sono **scelte operative da confermare** contro il regolamento dell'amministrazione,
> e finche' non sono confermate quello che segue descrive come le faremmo, non che le
> faremo. Le unita' che le costruiscono sono bloccate da quella conferma.


- Il **numero di repertorio**, *se confermato*, è progressivo per anno (per esempio 123/2026), lo assegna
  il sistema al primo passaggio in pubblicazione e non si modifica a mano (ALBO-08). Le
  garanzie sono quattro: **unico**, **progressivo**, assegnato **una sola volta**, **mai
  riutilizzato**. L'atto annullato conserva il suo numero, con stato e motivo.
  **L'assenza assoluta di buchi non è fra le garanzie**, ed è una scelta operativa da
  confermare contro il regolamento dell'amministrazione. Il motivo è concreto: una sequenza
  senza buchi obbliga a tenere il contatore dentro la stessa transazione della
  pubblicazione, e ogni operazione che fallisce a metà o va in conflitto con un'altra deve
  restituire il numero invece di bruciarlo. È fattibile, costa in complicazione, e la
  decisione se pagarla non è nostra.
- Un atto pubblicato è **immodificabile**: una rettifica è un atto nuovo che rinvia al
  precedente, mai una correzione silenziosa (ALBO-09). La cancellazione di un atto
  pubblicato è vietata per tutti, amministratore compreso. **Esiste una sola eccezione**,
  decisa il 2026-09-22 e descritta in ALBO-12: la sostituzione di un allegato con la sua
  versione oscurata, che toglie informazione e non ne aggiunge, non fa ripartire il termine
  e non cambia il numero di repertorio. Che il termine non riparta è una scelta di prodotto,
  non una conseguenza dei testi, che su questo punto tacciono. Serve a un caso che la norma
  non lascia aperto, cioè un atto già esposto che diffonde dati che non potevano essere
  diffusi.
- Ogni operazione rilevante finisce nel **log**: chi ha pubblicato, quando, chi ha
  disposto una defissione anticipata e perché (ALBO-10).

### Privacy prima di pubblicare (ALBO-11, ALBO-12)

Prima della pubblicazione il flusso impone un **controllo sui dati personali**
(ALBO-11): una schermata che elenca i casi tipici in cui un atto rivela dati delicati
senza nominarli (richiami a norme sul collocamento mirato delle persone con disabilità,
congedi per assistenza, graduatorie con punteggi sociali, provvedimenti disciplinari,
redditi e ISEE) e chiede una conferma esplicita. Non è una casella da spuntare di
fretta: senza conferma la pubblicazione non si conclude. Il sistema permette inoltre di
pubblicare una **versione oscurata** dell'atto tenendo l'originale integro fuori dalla
vista pubblica (ALBO-12). La valutazione su cosa pubblicare resta dell'ente: il plugin
fornisce il passaggio obbligato, non la decisione.

**Quando l'oscuramento arriva tardi.** Il caso in cui un atto è già esposto e diffonde dati
che non potevano essere diffusi non si risolve né lasciandolo lì né togliendolo: l'art.
2-septies comma 8 del d.lgs. 196/2003 vieta in modo assoluto la diffusione dei dati
genetici, biometrici e relativi alla salute, ma l'obbligo di pubblicare quell'atto non
sparisce per questo, e toglierlo per ripubblicarlo corretto farebbe ripartire il termine e
cambiare il numero di repertorio, cioè guasterebbe un adempimento per rimediare a un altro.
La strada decisa il 2026-09-22 è quindi la **sostituzione dell'allegato con la sua versione
oscurata**, a termine che continua a correre: è l'unica eccezione all'immodificabilità di
ALBO-09, vale solo per oscurare e non per cambiare il contenuto, ed è registrata con le
impronte di tutte e due le versioni.

Di questa strada una metà viene dai testi e l'altra no, e la distinzione va tenuta. Dai testi
viene che la diffusione vietata deve cessare. Che il periodo di pubblicazione continui a
correre dopo l'oscuramento **non lo dice nessuna fonte**: le fonti su questo tacciono, e il
componente sceglie la lettura che non guasta l'adempimento, cioè lasciare correre il termine
già fissato. È quindi una scelta di prodotto, registrata come tale, e un'amministrazione che
leggesse diversamente prende la propria determinazione e la fa registrare: il sistema non
decide da sé che il termine è compiuto.

Va detto con chiarezza che cosa il componente può garantire di questa regola e che cosa no.
**Può** pretendere che la sostituzione sia dichiarata come oscuramento e non come modifica,
rifiutare ogni altra causa, conservare l'impronta del file uscente e di quello entrante,
registrare chi l'ha disposta e quando, lasciare intatti repertorio e date, e far sì che il
referto dica quale file era esposto in quale periodo. **Non può** verificare che il file
entrante sia davvero la versione oscurata di quello uscente: due PDF diversi restano due
PDF diversi anche per il computer più attento. Quella responsabilità è di chi dispone la
sostituzione, ed è la ragione per cui l'operazione è tracciata invece che impedita.

### Accessibilità (ALBO-13, ALBO-14)

I PDF pubblicati devono essere leggibili dalle tecnologie assistive: al caricamento il
sistema esamina il file e avvisa se sembra una scansione o manca di struttura (ALBO-13);
l'avviso è dichiarato come indizio, la pubblicazione resta possibile. Ed è vietata ogni
misura anti copia che renda l'atto illeggibile a chi usa un lettore di schermo
(ALBO-14).

Una precisazione su chi risponde di che cosa. L'obbligo di accessibilità grava
sull'amministrazione che pubblica, non sul programma: che il componente se ne faccia carico
sulle pagine che genera è una scelta di prodotto, non una conseguenza della norma. E che una
pubblicazione non accessibile sia per ciò solo invalida non lo dice nessun testo: è una
responsabilità che pesa sulla valutazione di chi dirige, non un vizio dell'atto.

### Il resto (ALBO-04, ALBO-07, ALBO-15, ALBO-16, ALBO-17)

- **Durate configurabili per tipo di atto, senza default** (ALBO-04): i quindici giorni
  dell'art. 124 del TUEL valgono per le deliberazioni di comuni e province, non per
  tutti gli enti e non per tutti gli atti. Nel codice non esiste la costante 15: ogni
  tipo di atto ha la sua durata in configurazione, e un tipo senza durata non si
  pubblica.
- **Referto di pubblicazione** (ALBO-07), *da confermare come sopra*: alla defissione il sistema scatta una
  fotografia immutabile (numero, date effettive, impronta del file, chi ha pubblicato)
  che attesta cosa è stato esposto e quando. Si congela, non si rigenera.
- **Separazione dalla trasparenza** (ALBO-15): stesso documento, due esposizioni con
  cicli di vita indipendenti, file mai duplicato.
- **Ricerca e filtri** (ALBO-16) per tipo, organo, date e testo, usabili da tastiera.
- **Esportazione** (ALBO-17) di atti e metadati in formato aperto, per la conservazione.

## Cosa resta all'ente

Il plugin rende possibile e automatico il rispetto dei termini; **non decide cosa si
pubblica**. Restano all'ente: il regolamento con i termini per tipo di atto, la nomina
del responsabile, la valutazione sui dati personali di ogni atto, la conservazione a
norma, le risposte agli interessati.

## Tabella di riferimento

La colonna **Stato** dice a che punto è il lavoro su ogni requisito, e ha tre valori
soltanto:

- **da fare**: non ancora implementato. Il requisito è scritto e collaudabile, il codice
  non c'è.
- **in corso**: il codice esiste ma i suoi test non sono tutti verdi in CI. Non conta
  come fatto.
- **fatto**: implementato e con tutti i suoi test verdi in CI. È l'unico valore che
  autorizza a dire che il plugin fa quella cosa.

Lo stato lo aggiorna la stessa modifica che lo fa cambiare: passare a **fatto** senza il
test verde è la sola scorrettezza che rende inutile tutta la tabella.

| ID | Stato | In una riga | Fonte principale |
|---|---|---|---|
| ALBO-01 | da fare | Scheda atto: dati, documento principale `[1..1]`, allegati ulteriori `[0..n]`. La bozza si salva incompleta, la pubblicazione no | **norma** l'atto pubblicato dev'essere identificabile e completo dei suoi estremi (l. 69/2009 art. 32). **Prodotto** l'elenco preciso dei campi e la separazione fra salvataggio e pubblicazione |
| ALBO-02 | da fare | Data di fine **obbligatoria per pubblicare**. In bozza può mancare | **norma** nessuna pubblicazione a tempo indeterminato (Garante, provv. marzo 2026). **Prodotto** la data come dato che il sistema pretende al passaggio a pubblicato |
| ALBO-03 | da fare | L'atto scaduto smette di essere pubblico tempestivamente | **norma** il risultato, senza dipendere dal traffico del sito (Garante). **Prodotto** i tre strati, cron esterno, filtro e battito: architettura nostra, non un obbligo |
| ALBO-04 | da fare | Durate per tipo di atto, configurabili, senza default | **norma** l'esistenza di una durata (TUEL art. 124, valido per comuni e province, non universale). **Prodotto** la configurabilità per tipo. **Da confermare** le durate dei singoli tipi, che dipendono dal regolamento |
| ALBO-05 | da fare | L'atto defisso esce dalla vista pubblica | **norma** il risultato (Garante, provvedimenti). **Prodotto** la politica `irraggiungibile` dichiarata al meccanismo comune |
| ALBO-06 | da fare | Le pagine dell'atto non vengono indicizzate | **norma** il risultato (Garante, che raccomanda i metatag). **Prodotto** `noindex`, esclusione dalla mappa e politica dichiarata |
| ALBO-07 | da fare | Referto di pubblicazione congelato | **da confermare**: la prassi non è una fonte. Contenuto e obbligatorietà vanno verificati contro il regolamento, e l'unità è bloccata da quella conferma |
| ALBO-08 | da fare | Repertorio progressivo annuale, assegnato dal sistema. Unico, progressivo, assegnato una sola volta, mai riutilizzato | **da confermare**: la prassi non è una fonte. L'assenza assoluta di buchi non è promessa e dipende dal regolamento. L'unità è bloccata da quella conferma |
| ALBO-09 | da fare | Integrità del documento pubblicato | **norma** il risultato (linee guida AgID doc. informatici). **Prodotto** immodificabilità e rettifica come atto nuovo, con **un'unica eccezione dichiarata**, la sostituzione per oscuramento di ALBO-12 |
| ALBO-10 | da fare | Tracciabilità delle operazioni sugli atti | **norma** responsabilizzazione e tracciabilità (GDPR art. 5). **Prodotto** il registro solo in aggiunta del meccanismo comune è la soluzione scelta, non l'unica |
| ALBO-11 | da fare | Liceità del trattamento dei dati particolari | **norma** il risultato (d.lgs. 196/2003 artt. 2-ter, 2-septies). **Prodotto** il passaggio obbligato con conferma esplicita |
| ALBO-12 | da fare | I dati eccedenti non finiscono nella versione pubblica, e se ci sono finiti si sostituisce l'allegato con la versione oscurata senza far ripartire il termine | **norma** minimizzazione, l'originale non va esposto (GDPR art. 5.1.c); divieto assoluto di diffondere dati genetici, biometrici e sulla salute (d.lgs. 196/2003 art. 2-septies c. 8). **Prodotto** la coppia originale riservato più versione oscurata, la sostituzione come unica eccezione a ALBO-09, e la scelta che il termine non riparta: i testi impongono che la diffusione vietata cessi e tacciono sull'effetto dell'oscuramento sul periodo |
| ALBO-13 | da fare | I documenti pubblicati sono accessibili | **norma** il risultato (l. 69/2009 art. 32 che rinvia a l. 4/2004 art. 11). **Prodotto** l'avviso euristico al caricamento, che segnala un indizio e non blocca |
| ALBO-14 | da fare | Niente anti copia che rompa l'accessibilità | **norma** l'accessibilità non si comprime per ostacolare il prelievo (Garante, l. 4/2004) |
| ALBO-15 | da fare | Separazione dall'amministrazione trasparente | **norma** finalità e durate diverse (d.lgs. 33/2013, Garante). **Prodotto** due esposizioni indipendenti con un file solo |
| ALBO-16 | da fare | Ricerca e filtri accessibili | **prodotto**, con l'accessibilità come vincolo di norma |
| ALBO-17 | da fare | Gli atti sono versabili in conservazione | **norma** il risultato (linee guida AgID doc. informatici). **Prodotto** l'export in formato aperto |
| ALBO-18 | da fare | L'atto scaduto non è pubblico su nessun percorso | **norma** il risultato (Garante, provv. marzo 2026). **Prodotto** il filtro in interrogazione sui percorsi di WordPress, il cui elenco dipende da WordPress e non dalla norma |
| ALBO-19 | da fare | Battito di controllo con avviso | **prodotto**, motivato dal guasto silenzioso sanzionato nel provv. Garante marzo 2026 |
| ALBO-20 | da fare | L'atto defisso non viene più servito a nessuno | **norma** il risultato (Garante). **Prodotto** esclusione dalla memoria di pagina oppure invalidazione immediata: due strade, la scelta è nostra |
| ALBO-21 | da fare | Scadenza sull'ora civile italiana | **prodotto**: correttezza tecnica, nessuna fonte esterna |
| ALBO-22 | da fare | Stati dell'atto, transizioni consentite, chi prepara e chi pubblica come permessi distinti | **prodotto**, decisione chiusa il 2026-09-21: la tabella delle transizioni è nella nota qui sotto. Quattro sotto-decisioni ne sono seguite, di cui **tre restano aperte** e nessuna blocca l'unità; la quarta, sulle cause che legittimano la defissione anticipata, è chiusa il 2026-09-22 |
| ALBO-27 | da fare | **La pubblicazione non si programma.** La data di inizio la scrive il sistema al passaggio a pubblicato, non è modificabile e non può stare nel futuro | **prodotto**, deciso il 2026-09-22. L'albo sostituisce la bacheca di carta, dove programmare non era possibile. Se un'amministrazione lo chiederà, si costruirà allora |
| ALBO-23 | fatto | All'attivazione l'insieme minimo di permessi sul tipo atto arriva all'amministratore | **prodotto** |
| ALBO-24 | fatto | A ogni aggiornamento i permessi nuovi arrivano ai ruoli che avevano già gli altri | **prodotto** |
| ALBO-25 | fatto | Se nessun ruolo possiede i permessi del tipo atto, l'amministrazione lo segnala | **prodotto** |
| ALBO-26 | fatto | Il componente crea e configura un proprio ruolo per la pubblicazione | **prodotto** |

### Come si legge la colonna delle fonti

Tre categorie, e la distinzione non è formale: dice quanto è discutibile un requisito.

- **norma**: esiste un **risultato obbligatorio**, con una fonte primaria. Non significa che
  tutta la riga sia intoccabile: la norma dice che cosa deve essere vero alla fine, e il modo
  con cui il componente lo garantisce resta quasi sempre una nostra decisione. Per questo
  quasi tutte le righe marcate "norma" portano anche una parte marcata "prodotto", nella
  stessa cella, dopo il risultato obbligatorio. Tenerle separate serve a una cosa precisa: se
  "cron esterno" e "il Garante lo impone" stanno insieme senza distinzione, quella scelta
  tecnica non la riapre più nessuno, nemmeno quando ha smesso di essere la migliore.
- **prodotto**: la scelta è nostra. Motivata, ma nostra, e si può discutere.
- **da confermare**: dipende dal regolamento dell'amministrazione o da una fonte che non
  abbiamo ancora reperito. Finché non è confermata è un'ipotesi, non un impegno, e l'unità
  che la costruisce è bloccata da quella conferma.

Fino al 2026-09-11 questa colonna aveva una categoria sola, e formule come "prassi di
pubblicità legale" o "operativo" stavano accanto al GDPR: facevano sembrare obblighi di legge
delle decisioni progettuali nostre.

### Nota su ALBO-22: la decisione, e le sotto-decisioni che ne sono seguite

Era una decisione presa a metà, ed è stata chiusa il **2026-09-21**, prima di aprire l'unità
che costruisce il flusso di pubblicazione e non insieme a essa. Il motivo dell'ordine resta
quello di sempre: chi implementa un flusso mentre lo sta specificando sceglie la strada che
il codice gli rende comoda, e la specifica diventa il resoconto di quello che è uscito.

**Le cinque situazioni** restano quelle già decise: bozza, in verifica, pubblicato, defisso,
annullato.

#### Il criterio con cui il disegno è stato scelto

Erano possibili tre disegni, che si distinguono su due domande: se il passaggio in verifica
sia obbligatorio, e se chi pubblica possa essere la stessa persona che ha preparato l'atto.

1. verifica obbligatoria e **due persone necessariamente diverse**;
2. verifica obbligatoria, **una persona sola ammessa** se possiede entrambi i permessi;
3. **verifica facoltativa**, con la possibilità di pubblicare direttamente da bozza.

È stato scelto il **secondo**. Il primo è contenuto nel secondo: un ente che vuole le due
firme distinte ottiene esattamente quel comportamento non assegnando a nessuno entrambi i
permessi, e il meccanismo dei ruoli del componente lo consente già senza una riga di codice
in più. Imporlo a tutti avrebbe invece cablato nel componente una regola organizzativa che
cambia da ente a ente, proprio ciò che le regole di sviluppo vietano, e reso il componente
inservibile dove una persona sola fa tutto il lavoro. Il terzo è stato scartato perché
sposterebbe il controllo sui dati personali (ALBO-11) da un passaggio obbligato a una
conferma dentro l'azione di pubblicazione: un punto solo invece di due, sul requisito che
nasce dai provvedimenti del Garante del marzo 2026.

#### Le transizioni consentite, e chi le compie

| Da | A | Chi | Condizioni |
|---|---|---|---|
| (nuovo) | bozza | chi redige | nessuna: la bozza si salva incompleta |
| bozza | bozza | chi redige | nessuna |
| bozza | in verifica | chi redige | devono esserci tutti i dati che la pubblicazione pretende, data di fine compresa (ALBO-01, ALBO-02) |
| in verifica | bozza | chi pubblica | motivazione obbligatoria, che finisce nel registro (ALBO-10). L'atto torna modificabile |
| in verifica | pubblicato | chi pubblica | conferma esplicita del controllo sui dati personali (ALBO-11). Tutto o niente |
| pubblicato | defisso | il compito pianificato | alla scadenza. È una registrazione, non un interruttore: vedi il vincolo più sotto |
| pubblicato | defisso | chi pubblica | motivazione obbligatoria, e **soltanto su un atto non ancora scaduto** |
| pubblicato | annullato | chi pubblica | motivazione obbligatoria. L'atto conserva numero, stato e motivo |
| defisso | annullato | chi pubblica | come sopra |

**"Chi pubblica" vuol dire chi possiede l'insieme di permessi di chi pubblica**, non una
persona sola nominata da qualche parte. Dentro quell'insieme la defissione e l'annullamento
hanno una capability propria, distinta da quella per pubblicare: oggi arriva insieme alle
altre, ed è ciò che permetterà di riservare la defissione anticipata a un responsabile
distinto, se la sotto-decisione aperta si chiuderà in quel senso, senza toccare il codice.
La mappa delle capability sta in `dati.md`.

**Chi redige e chi pubblica possono essere la stessa persona**, se possiede entrambi i
permessi. I due passaggi restano due, e il controllo sui dati personali sta nel secondo.

**Un atto in verifica non è modificabile**, né da chi redige né da chi pubblica. Se va
corretto torna in bozza con la sua motivazione. L'invariante è: **quello che è stato
verificato è quello che esce.**

**Tutto il resto è vietato, e non per omissione.** In particolare: non si pubblica saltando
la verifica; non si torna indietro da pubblicato, defisso o annullato, per nessuno e da
nessun ingresso, amministratore compreso; annullato è terminale; un atto pubblicato, defisso
o annullato non si cancella (ALBO-09).

**Cosa succede a una transizione rifiutata.** L'atto resta nello stato in cui era, e la riga
nella banca dati non attraversa mai lo stato richiesto: nessuna riparazione tardiva. Chi ha
tentato da una schermata riceve il motivo; chi ha tentato da un programma riceve la
segnalazione destinata a chi scrive codice.

#### Il vincolo che il flusso non può contraddire

Lo **stato memorizzato** e la **condizione di scadenza** sono due cose diverse. Alla
mezzanotte del giorno dopo la data di fine l'atto diventa **immediatamente invisibile al
pubblico**, per opera del filtro in lettura, anche se nella banca dati risulta ancora
"pubblicato". Il compito pianificato porterà poi lo stato memorizzato a "defisso", e se non
gira quel passaggio non avviene, senza che cambi nulla di ciò che il pubblico vede. Nessuna
transizione della tabella può quindi essere usata come condizione di visibilità, e "defisso"
in particolare è una registrazione contabile: la conformità non dipende da uno stato scritto
da qualcuno, e questo è già costruito e collaudato nel meccanismo comune.

Da qui discende la condizione sulla defissione anticipata: si dispone **solo su un atto non
ancora scaduto**, perché su uno scaduto non c'è niente da anticipare. È già invisibile, e il
compito pianificato lo registrerà.

**Come fa sparire l'atto la defissione anticipata, visto che il filtro guarda la data.** È la
domanda che il vincolo qui sopra rende obbligatoria, e ammette una risposta sola: la
defissione anticipata **riporta indietro la data di fine pubblicazione**, e da quel momento
l'atto sparisce per la ragione di sempre, cioè perché il filtro legge una data passata. Lo
stato "defisso" resta la registrazione di quello che è accaduto e non diventa mai la causa
dell'invisibilità. Ogni altra soluzione farebbe consultare lo stato al filtro in lettura, che
è esattamente ciò che questo componente esiste per evitare.

Da quella risposta discende un dettaglio facile da sbagliare, che va scritto qui perché chi
implementa non lo dedurrebbe da solo: il meccanismo comune tiene la fine della pubblicazione
come **giorno civile** e considera scaduto il contenuto **dalla mezzanotte del giorno dopo**.
Scrivere la data di oggi quindi non toglie niente dalla vista fino a stanotte. Per far
sparire l'atto nell'istante in cui la defissione è disposta si scrive **il giorno precedente
a quello in cui la si dispone**, e la riga di collaudo lo verifica guardando l'orologio e non
lo stato.

La data di fine pianificata non va perduta: la modifica finisce nel registro delle operazioni
(ALBO-10), che è solo in aggiunta, insieme a chi l'ha disposta, quando e perché. Da qui
discende un vincolo che l'unità del referto eredita: il referto di pubblicazione (ALBO-07)
attesta il periodo effettivamente compiuto, e dopo una defissione anticipata quel periodo non
coincide più con la data di fine memorizzata. Il referto va quindi costruito sul registro e
non sul metadato.

#### Quattro sotto-decisioni, di cui tre restano aperte, e nessuna blocca l'unità

1. **Un atto annullato prima della scadenza resta visibile al pubblico, con lo stato
   dichiarato?** Negli albi la pubblicazione dell'annullamento ha una sua funzione
   informativa. La transizione si costruisce comunque: che cosa il pubblico veda di un atto
   annullato è una domanda sul filtro in lettura, separata da chi può compiere il passaggio.
   Finché non è decisa non ha riga in `collaudo.md`.
2. **Chi dispone la defissione anticipata.** Qui è attribuita a chi pubblica, **in via
   provvisoria**. Alcuni enti la riservano a un responsabile nominato, che sarebbe una terza
   figura. La riga di collaudo prova la forma provvisoria e la marca come tale.
3. **Se il giorno civile sia una granularità sufficiente.** Scrivere il giorno precedente
   toglie l'atto dalla vista subito, ma lascia memorizzata una data di fine che per un giorno
   non dice il vero, e il fatto esatto resta solo nel registro. L'alternativa è chiedere al
   meccanismo comune un termine più fine del giorno intero, che però è una modifica di quel
   componente e non di questo. Qui si tiene il giorno precedente, **in via provvisoria**,
   perché fra le due direzioni sbaglia in quella che toglie l'atto dalla vista invece che
   lasciarcelo.

4. **Quali cause legittimano la defissione anticipata. Chiusa il 2026-09-22.** Va scritto
   qui perché nessun'altra riga di questo documento lo dice: **nessun requisito del
   catalogo chiede la defissione anticipata**, e la fonte di ALBO-22 è "prodotto". La
   transizione è entrata disegnando la macchina degli stati, non perché una norma la
   reclami.

   La domanda è stata portata alla lettura delle fonti, che ha risposto il 2026-09-22, e la
   risposta cambia la forma della transizione invece di limitarsi a nominarne il titolare.
   **Accorciare la pubblicazione di un atto regolare non è una funzione che il componente
   offre**, e la frase vale per un solo tipo di durata, cioè quando il termine è fissato
   dalla disciplina applicabile all'atto. L'art. 124 del TUEL prescrive per le deliberazioni
   degli enti locali quindici
   giorni **consecutivi**, e intesta a "specifiche disposizioni di legge", cioè a un'altra
   norma e non all'amministrazione che pubblica, la facoltà di fissare un termine diverso.
   L'art. 32 della legge 69/2009 parla di obblighi che **si intendono assolti** con la
   pubblicazione, e un adempimento interrotto a metà non è assolto. A questa conclusione si
   era appoggiata anche la regola dell'oscuramento prima del termine, letta come regola
   propria del regime della trasparenza
   e del suo arco di cinque anni. Quell'appoggio è stato ridimensionato il 2026-09-22: al
   paragrafo 7 il Garante motiva con la proporzionalità, che è un principio generale e non un
   calendario, quindi la questione resta aperta e l'argomento non porta peso. La conclusione
   regge sull'art. 124, non su questo.

   Restano pacifiche le rimozioni anticipate che **non accorciano il termine**, perché
   tolgono dalla vista una pubblicazione che in quella forma non doveva esserci: dati idonei
   a rivelare lo stato di salute, quando il documento non è oscurabile; pubblicazione priva
   di una norma che prescriva l'affissione di quell'atto, dove un termine non era mai
   cominciato; dati inesatti o non aggiornati, perché l'amministrazione mette a disposizione
   soltanto dati esatti e aggiornati, e un dato può essere pertinente, necessario, coperto da
   una base normativa e insieme sbagliato; ordine di un'autorità. Il caso dei dati eccedenti
   **non è una rimozione**: si sostituisce il file con la versione oscurata e il termine
   continua a correre, ed è ALBO-12, non una transizione di questa tabella. Resta fuori
   dall'elenco, ed è bene che resti fuori, la richiesta dell'interessato su un atto pubblicato
   legittimamente e con dati pertinenti: quel bilanciamento lo fa l'amministrazione con il
   proprio responsabile della protezione dei dati, e il componente registra la decisione e chi
   l'ha presa (ALBO-10).

   **Che natura ha questo elenco.** È un elenco di cause pacifiche, cioè di casi che i testi
   letti sostengono senza discussione, e non un elenco tassativo: dire che altre cause non
   possano esistere non lo dice nessuna fonte, e la quinta voce è arrivata dopo le altre
   quattro proprio perché mancava. Che nel componente l'elenco resti **chiuso** è quindi una
   scelta di prodotto, ampliabile per configurazione senza toccare il codice, e vale finché
   qualcuno non chiede il contrario: serve a impedire che "accorciare il termine" rientri
   dalla porta di servizio come motivazione scritta a mano.

   **La forma da tenere è quindi questa**: la transizione da pubblicato a defisso resta una
   sola, il motivo non è testo libero ma una causa scelta fra quelle dell'elenco, e nessuna
   maschera consente di accorciare il termine di un atto regolare come normale operazione di
   redazione.

   **Il caso opposto esiste e non va confuso con questo.** Dove la norma un termine non lo
   fissa, il periodo lo individua l'amministrazione, e il Garante dice che quel periodo non
   può superare quello ritenuto necessario, valutato caso per caso. Lì la durata configurata
   per il tipo di atto è un massimale che l'ente si è dato, non un termine di legge, e
   sceglierne uno più breve **sul singolo atto, prima di pubblicarlo**, è esattamente la
   valutazione che la fonte gli chiede. La regola in una riga: si può accorciare ciò che
   l'ente ha scelto, non ciò che la norma ha prescritto. Riguarda la scelta della data di
   fine prima della pubblicazione e non tocca nulla di quanto detto sopra, che vale a
   pubblicazione avvenuta. **Se ALBO-04 debba quindi portare, per ogni durata configurata,
   l'indicazione di dove viene, se da una norma o da una scelta dell'ente, è una decisione
   aperta al 2026-09-22**: senza quell'indicazione il componente non sa quale dei due casi
   ha davanti.

   L'art. 134 del TUEL, letto nel testo vigente il 2026-09-22, corrobora questa conclusione
   senza però disciplinare gli effetti di un'interruzione della pubblicazione, che nessun testo
   regola. Il comma 3 dice che le
   deliberazioni non soggette a controllo diventano esecutive **dopo il decimo giorno dalla
   loro pubblicazione**: la durata non è un contenitore che si svuota quando la finalità
   sembra raggiunta, è l'orologio da cui decorre un effetto giuridico, e chi togliesse
   l'atto al settimo giorno toglierebbe il presupposto di un termine ancora in corso. Due
   dettagli dello stesso articolo vanno nella stessa direzione, pur senza provarla. I due
   termini partono insieme e hanno lunghezza diversa, dieci giorni e quindici: se il termine
   fosse un tetto da abbassare a scopo raggiunto, il decimo giorno sarebbe il taglio naturale,
   e la legge invece lascia correre fino al quindicesimo. È un argomento difendibile e non una
   dimostrazione, perché a che cosa servano i cinque giorni ulteriori la legge non lo dice.
   E l'urgenza ha già la sua valvola nel comma 4, la dichiarazione di immediata
   eseguibilità, che opera sull'efficacia dell'atto e non sulla durata della pubblicazione:
   l'ordinamento ha previsto il caso "serve che valga subito" e vi ha risposto senza toccare
   l'albo.

   **La sotto-decisione è chiusa il 2026-09-22**, ed è chiusa nel solo modo che conta: la
   riga di collaudo che rifiuta una defissione anticipata con una causa fuori elenco è
   scritta, quindi la restrizione non vive più soltanto in questa nota.

   Questa risposta aveva portato alla luce una contraddizione, **risolta poi lo stesso
   2026-09-22** e qui conservata perché spiega da dove viene l'eccezione. Le fonti impongono,
   per il caso dei dati eccedenti, che la diffusione vietata cessi, e la risposta scelta è
   sostituire il file esposto con la versione oscurata; che il termine continui a correre è
   invece scelta del componente, perché le fonti tacciono. Ma ALBO-09 prescriveva
   l'immodificabilità del documento pubblicato e la rettifica come atto nuovo, e il catalogo
   di collaudo lo verificava proprio sull'allegato di un atto pubblicato. Le due prescrizioni
   non stavano insieme. La decisione presa è che cede ALBO-09, con **un'unica eccezione
   dichiarata** e non con un'apertura generale: la sostituzione vale solo per oscurare, non
   fa ripartire il termine, non cambia il numero di repertorio, ed è descritta in ALBO-12 con
   le proprie righe di collaudo.

Le prime due si chiudono guardando come si comportano albi pretorio già in esercizio, la
seconda anche contro il regolamento dell'amministrazione. La terza si chiude quando si decide
se il meccanismo comune debba tenere un termine più fine del giorno civile. La quarta non si è
chiusa guardando la prassi ma sul testo vigente dell'art. 124 del TUEL, con l'art. 134 come
argomento interpretativo e non come disciplina dell'interruzione, ed è la sola
delle quattro che ha **ristretto** una transizione già dichiarata invece di limitarsi a
precisarne il titolare.

### Come si legge la colonna Stato

Tre valori: **da fare** (il requisito esiste come impegno, il codice no), **in corso** (il
codice e i test esistono ma non risultano verdi nella verifica continua), **fatto** (verde
nella verifica continua). Un requisito passa a "fatto" solo quando tutte le sue righe di
`collaudo.md` sono "fatto". Oggi sono a **"fatto" ALBO-23, ALBO-24, ALBO-25 e ALBO-26**,
chiusi dall'unità che ha costruito il tipo atto, i permessi e il ruolo proprio. **Tutti gli
altri sono a "da fare"**: il componente registra gli atti e li tiene chiusi, e non ne
pubblica ancora nessuno.
