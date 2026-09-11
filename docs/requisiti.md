# Requisiti: cosa deve fare l'albo pretorio

Questo documento spiega in linguaggio semplice cosa il plugin deve fare e perché. Ogni
requisito ha un identificativo (ALBO-01 fino ad ALBO-26): è lo stesso usato nei test, nei
commit e nelle discussioni, così si può sempre risalire dal codice al motivo per cui
esiste. La specifica tecnica completa, con le fonti normative, è la tabella in fondo.

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

- Il **numero di repertorio** è progressivo per anno (per esempio 123/2026), lo assegna
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
- **Referto di pubblicazione** (ALBO-07): alla defissione il sistema scatta una
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
| ALBO-01 | da fare | Scheda atto: dati, documento principale `[1..1]`, allegati ulteriori `[0..n]`. La bozza si salva incompleta, la pubblicazione no | **norma** l. 69/2009 art. 32 per l'esistenza dei dati identificativi; **prodotto** per l'elenco preciso dei campi |
| ALBO-02 | da fare | Data di fine sempre obbligatoria | **norma** Garante, provv. marzo 2026 |
| ALBO-03 | da fare | Defissione automatica da cron di sistema | **norma** Garante, linee guida 2014 |
| ALBO-04 | da fare | Durate per tipo di atto, configurabili, senza default | **norma** TUEL art. 124 per l'esistenza di una durata, valido per comuni e province e non universale; **da confermare** le durate dei singoli tipi, che dipendono dal regolamento |
| ALBO-05 | da fare | L'atto defisso esce dalla vista pubblica | **norma** Garante, linee guida 2014 |
| ALBO-06 | da fare | Niente indicizzazione dei motori di ricerca | **norma** Garante, linee guida 2014 |
| ALBO-07 | da fare | Referto di pubblicazione congelato | **da confermare**: la prassi non è una fonte. Il contenuto va verificato contro il regolamento |
| ALBO-08 | da fare | Repertorio progressivo annuale, assegnato dal sistema. Unico, progressivo, assegnato una sola volta, mai riutilizzato | **da confermare**: la prassi non è una fonte. L'assenza assoluta di buchi non è promessa e dipende dal regolamento |
| ALBO-09 | da fare | Atto pubblicato immodificabile | **norma** linee guida AgID doc. informatici |
| ALBO-10 | da fare | Log delle operazioni | **norma** GDPR art. 5 |
| ALBO-11 | da fare | Controllo preventivo sui dati personali | **norma** d.lgs. 196/2003 artt. 2-ter, 2-septies |
| ALBO-12 | da fare | Versione oscurata accanto all'originale riservato | **norma** GDPR art. 5.1.c |
| ALBO-13 | da fare | Allegati accessibili, avviso sui PDF sospetti | **norma** l. 4/2004 art. 11 |
| ALBO-14 | da fare | Niente anti copia che rompa l'accessibilità | **norma** Garante, linee guida 2014 |
| ALBO-15 | da fare | Separazione dall'amministrazione trasparente | **norma** d.lgs. 33/2013 |
| ALBO-16 | da fare | Ricerca e filtri accessibili | **prodotto**, con l'accessibilità come vincolo di norma |
| ALBO-17 | da fare | Export in formato aperto | **norma** linee guida AgID doc. informatici |
| ALBO-18 | da fare | Filtro di scadenza su ogni percorso di lettura | **norma** Garante, provv. marzo 2026 |
| ALBO-19 | da fare | Battito di controllo con avviso | **prodotto**, motivato dal guasto silenzioso sanzionato nel provv. Garante marzo 2026 |
| ALBO-20 | da fare | Niente cache sugli atti defissi | **norma** Garante, linee guida 2014 |
| ALBO-21 | da fare | Scadenza sull'ora civile italiana | **prodotto**: correttezza tecnica, nessuna fonte esterna |
| ALBO-22 | da fare | Stati dell'atto, transizioni consentite, chi prepara e chi pubblica come permessi distinti | **prodotto, decisione incompleta**: vedi la nota qui sotto |
| ALBO-23 | da fare | All'attivazione l'insieme minimo di permessi sul tipo atto arriva all'amministratore | **prodotto** |
| ALBO-24 | da fare | A ogni aggiornamento i permessi nuovi arrivano ai ruoli che avevano già gli altri | **prodotto** |
| ALBO-25 | da fare | Se nessun ruolo possiede i permessi del tipo atto, l'amministrazione lo segnala | **prodotto** |
| ALBO-26 | da fare | Il componente crea e configura un proprio ruolo per la pubblicazione | **prodotto** |

### Come si legge la colonna delle fonti

Tre categorie, e la distinzione non è formale: dice quanto è discutibile un requisito.

- **norma**: c'è una fonte primaria, legge, decreto o provvedimento. Non è negoziabile e il
  componente non lo può scegliere diversamente.
- **prodotto**: la scelta è nostra. È motivata, ma resta una decisione di progettazione e si
  può discutere.
- **da confermare**: dipende dal regolamento dell'amministrazione o da una fonte che non
  abbiamo ancora reperito. Finché non è confermata è un'ipotesi, non un impegno.

Fino al 2026-09-11 questa colonna aveva una categoria sola, e formule come "prassi di
pubblicità legale" o "operativo" stavano accanto al GDPR: facevano sembrare obblighi di
legge delle nostre decisioni progettuali, che è il modo più rapido per rendere non
discutibile una cosa discutibile.

### Nota su ALBO-22: decisione incompleta

Non è un requisito con il testo ancora da battere a macchina: è una **decisione che non è
stata presa fino in fondo**, e va chiusa **prima** di aprire l'unità che costruisce il flusso
di pubblicazione, non insieme a essa. Chi implementa un flusso mentre lo sta specificando
sceglie la strada che il codice gli rende comoda, e la specifica diventa il resoconto di
quello che è uscito.

**Cosa è già deciso**: gli stati sono bozza, in verifica, pubblicato, defisso, annullato;
defisso è uno stato che discende dalla data e non da un evento; la restituzione in bozza
richiede una motivazione; la pubblicazione è tutto o niente; chi prepara e chi pubblica sono
permessi distinti.

**Cosa manca**: quali transizioni sono consentite fra quali stati, chi può compiere ciascuna,
e cosa succede a una transizione rifiutata. Senza questi tre pezzi il requisito non è
verificabile, e per questo non ha righe in `collaudo.md`.

### Come si legge la colonna Stato

Tre valori: **da fare** (il requisito esiste come impegno, il codice no), **in corso** (il
codice e i test esistono ma non risultano verdi nella verifica continua), **fatto** (verde
nella verifica continua). Un requisito passa a "fatto" solo quando tutte le sue righe di
`collaudo.md` sono "fatto". Oggi il componente è all'avvio della costruzione: **tutti i
requisiti sono a "da fare"**, e questa colonna esiste perché il repository non prometta
niente di falso a chi lo valuta.
