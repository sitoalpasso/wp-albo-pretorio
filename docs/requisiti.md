# Requisiti: cosa deve fare l'albo pretorio

Questo documento spiega in linguaggio semplice cosa il plugin deve fare e perché. Ogni
requisito ha un identificativo (ALBO-01 fino ad ALBO-26): è lo stesso usato nei test, nei
commit e nelle discussioni, così si può sempre risalire dal codice al motivo per cui
esiste. In fondo c'è il **catalogo corrente dei requisiti**, con le fonti normative e le
decisioni aperte marcate esplicitamente. Non è una specifica completa e non lo sarà finché
ALBO-22 resta una decisione incompleta e ALBO-07 e ALBO-08 restano da confermare.

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
facoltativi e possono non esserci affatto (ALBO-01).

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
  pubblicato è vietata per tutti, amministratore compreso.
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

### Accessibilità (ALBO-13, ALBO-14)

I PDF pubblicati devono essere leggibili dalle tecnologie assistive: al caricamento il
sistema esamina il file e avvisa se sembra una scansione o manca di struttura (ALBO-13);
l'avviso è dichiarato come indizio, la pubblicazione resta possibile. Ed è vietata ogni
misura anti copia che renda l'atto illeggibile a chi usa un lettore di schermo
(ALBO-14).

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
| ALBO-09 | da fare | Integrità del documento pubblicato | **norma** il risultato (linee guida AgID doc. informatici). **Prodotto** immodificabilità assoluta e rettifica come atto nuovo |
| ALBO-10 | da fare | Tracciabilità delle operazioni sugli atti | **norma** responsabilizzazione e tracciabilità (GDPR art. 5). **Prodotto** il registro solo in aggiunta del meccanismo comune è la soluzione scelta, non l'unica |
| ALBO-11 | da fare | Liceità del trattamento dei dati particolari | **norma** il risultato (d.lgs. 196/2003 artt. 2-ter, 2-septies). **Prodotto** il passaggio obbligato con conferma esplicita |
| ALBO-12 | da fare | I dati eccedenti non finiscono nella versione pubblica | **norma** minimizzazione, l'originale non va esposto (GDPR art. 5.1.c). **Prodotto** la coppia originale riservato più versione oscurata |
| ALBO-13 | da fare | I documenti pubblicati sono accessibili | **norma** il risultato (l. 69/2009 art. 32 che rinvia a l. 4/2004 art. 11). **Prodotto** l'avviso euristico al caricamento, che segnala un indizio e non blocca |
| ALBO-14 | da fare | Niente anti copia che rompa l'accessibilità | **norma** l'accessibilità non si comprime per ostacolare il prelievo (Garante, l. 4/2004) |
| ALBO-15 | da fare | Separazione dall'amministrazione trasparente | **norma** finalità e durate diverse (d.lgs. 33/2013, Garante). **Prodotto** due esposizioni indipendenti con un file solo |
| ALBO-16 | da fare | Ricerca e filtri accessibili | **prodotto**, con l'accessibilità come vincolo di norma |
| ALBO-17 | da fare | Gli atti sono versabili in conservazione | **norma** il risultato (linee guida AgID doc. informatici). **Prodotto** l'export in formato aperto |
| ALBO-18 | da fare | L'atto scaduto non è pubblico su nessun percorso | **norma** il risultato (Garante, provv. marzo 2026). **Prodotto** il filtro in interrogazione sui percorsi di WordPress, il cui elenco dipende da WordPress e non dalla norma |
| ALBO-19 | da fare | Battito di controllo con avviso | **prodotto**, motivato dal guasto silenzioso sanzionato nel provv. Garante marzo 2026 |
| ALBO-20 | da fare | L'atto defisso non viene più servito a nessuno | **norma** il risultato (Garante). **Prodotto** esclusione dalla memoria di pagina oppure invalidazione immediata: due strade, la scelta è nostra |
| ALBO-21 | da fare | Scadenza sull'ora civile italiana | **prodotto**: correttezza tecnica, nessuna fonte esterna |
| ALBO-22 | da fare | Stati dell'atto, transizioni consentite, chi prepara e chi pubblica come permessi distinti | **prodotto, decisione incompleta**: vedi la nota qui sotto |
| ALBO-23 | da fare | All'attivazione l'insieme minimo di permessi sul tipo atto arriva all'amministratore | **prodotto** |
| ALBO-24 | da fare | A ogni aggiornamento i permessi nuovi arrivano ai ruoli che avevano già gli altri | **prodotto** |
| ALBO-25 | da fare | Se nessun ruolo possiede i permessi del tipo atto, l'amministrazione lo segnala | **prodotto** |
| ALBO-26 | da fare | Il componente crea e configura un proprio ruolo per la pubblicazione | **prodotto** |

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

### Nota su ALBO-22: decisione incompleta

Non è un requisito con il testo ancora da battere a macchina: è una **decisione che non è
stata presa fino in fondo**, e va chiusa **prima** di aprire l'unità che costruisce il flusso
di pubblicazione, non insieme a essa. Chi implementa un flusso mentre lo sta specificando
sceglie la strada che il codice gli rende comoda, e la specifica diventa il resoconto di
quello che è uscito.

**Cosa è già deciso**: gli stati sono bozza, in verifica, pubblicato, defisso, annullato; la
restituzione in bozza richiede una motivazione; la pubblicazione è tutto o niente; chi
prepara e chi pubblica sono permessi distinti.

**Una cosa che il flusso non può contraddire.** Lo **stato memorizzato** e la **condizione di
scadenza** sono due cose diverse. Alla mezzanotte del giorno dopo la data di fine l'atto
diventa **immediatamente invisibile al pubblico**, per opera del filtro in lettura, anche se
nella banca dati risulta ancora "pubblicato". Il compito pianificato porterà poi lo stato
memorizzato a "defisso", e se non gira quel passaggio non avviene, senza che cambi nulla di
ciò che il pubblico vede. Il flusso non può quindi trattare "defisso" come lo stato che rende
invisibile un atto: la conformità non dipende da uno stato scritto da qualcuno, e questo è già
costruito e collaudato nel meccanismo comune.

**Cosa manca**: quali transizioni sono consentite fra quali stati, chi può compiere ciascuna,
e cosa succede a una transizione rifiutata. Senza questi tre pezzi il requisito non è
verificabile, e per questo non ha righe in `collaudo.md`.

### Come si legge la colonna Stato

Tre valori: **da fare** (il requisito esiste come impegno, il codice no), **in corso** (il
codice e i test esistono ma non risultano verdi nella verifica continua), **fatto** (verde
nella verifica continua). Un requisito passa a "fatto" solo quando tutte le sue righe di
`collaudo.md` sono "fatto". Oggi il componente è all'avvio della costruzione: **tutti i
requisiti sono a "da fare"**.
