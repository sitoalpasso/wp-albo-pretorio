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

Un atto non è un PDF buttato in una pagina: è una scheda con campi obbligatori (numero
di repertorio, tipo, oggetto, organo che l'ha adottato, data di adozione, data di inizio
e di fine pubblicazione, allegati). Senza tutti i campi la scheda non si salva
(ALBO-01). In particolare **la data di fine è sempre obbligatoria**: non esiste l'atto
pubblicato "per sempre" (ALBO-02), perché è proprio il caso sanzionato dal Garante.

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

Alla scadenza l'atto esce dalla vista pubblica: se va conservato, va in un archivio ad
accesso controllato, non in una pagina pubblica meno visibile (ALBO-05). E finché è
pubblicato, le sue pagine dicono ai motori di ricerca di non indicizzarlo: meta
`noindex`, esclusione dalla sitemap (ALBO-06). Attenzione: è la regola **opposta** a
quella dell'amministrazione trasparente, dove l'indicizzazione è obbligatoria. Per
questo la politica di indicizzazione è un parametro dichiarato, mai un default.

### Il registro non si tocca (ALBO-08, ALBO-09, ALBO-10)

- Il **numero di repertorio** è progressivo per anno (per esempio 123/2026), lo assegna
  il sistema al primo passaggio in pubblicazione e non si modifica a mano (ALBO-08).
  Niente buchi nella sequenza: in un registro un buco è un sospetto di cancellazione.
  L'atto annullato conserva il suo numero, con stato e motivo.
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
| ALBO-01 | da fare | Scheda atto con campi obbligatori | l. 69/2009 art. 32 |
| ALBO-02 | da fare | Data di fine sempre obbligatoria | Garante, provv. marzo 2026 |
| ALBO-03 | da fare | Defissione automatica da cron di sistema | Garante, linee guida 2014 |
| ALBO-04 | da fare | Durate per tipo di atto, configurabili, senza default | TUEL art. 124 |
| ALBO-05 | da fare | L'atto defisso esce dalla vista pubblica | Garante, linee guida 2014 |
| ALBO-06 | da fare | Niente indicizzazione dei motori di ricerca | Garante, linee guida 2014 |
| ALBO-07 | da fare | Referto di pubblicazione congelato | prassi di pubblicità legale |
| ALBO-08 | da fare | Repertorio progressivo annuale, assegnato dal sistema | prassi di pubblicità legale |
| ALBO-09 | da fare | Atto pubblicato immodificabile | linee guida AgID doc. informatici |
| ALBO-10 | da fare | Log delle operazioni | GDPR art. 5 |
| ALBO-11 | da fare | Controllo preventivo sui dati personali | d.lgs. 196/2003 artt. 2-ter, 2-septies |
| ALBO-12 | da fare | Versione oscurata accanto all'originale riservato | GDPR art. 5.1.c |
| ALBO-13 | da fare | Allegati accessibili, avviso sui PDF sospetti | l. 4/2004 art. 11 |
| ALBO-14 | da fare | Niente anti copia che rompa l'accessibilità | Garante, linee guida 2014 |
| ALBO-15 | da fare | Separazione dall'amministrazione trasparente | d.lgs. 33/2013 |
| ALBO-16 | da fare | Ricerca e filtri accessibili | usabilità |
| ALBO-17 | da fare | Export in formato aperto | linee guida AgID doc. informatici |
| ALBO-18 | da fare | Filtro di scadenza su ogni percorso di lettura | Garante, provv. marzo 2026 |
| ALBO-19 | da fare | Battito di controllo con avviso | Garante, provv. marzo 2026 |
| ALBO-20 | da fare | Niente cache sugli atti defissi | Garante, linee guida 2014 |
| ALBO-21 | da fare | Scadenza sull'ora civile italiana | operativo |
| ALBO-22 | da fare | Stati dell'atto e flusso di pubblicazione, con chi prepara e chi pubblica come permessi distinti | prassi di pubblicità legale |
| ALBO-23 | da fare | All'attivazione l'insieme minimo di permessi sul tipo atto arriva all'amministratore | operativo |
| ALBO-24 | da fare | A ogni aggiornamento i permessi nuovi arrivano ai ruoli che avevano già gli altri | operativo |
| ALBO-25 | da fare | Se nessun ruolo possiede i permessi del tipo atto, l'amministrazione lo segnala | operativo |
| ALBO-26 | da fare | Il componente crea e configura un proprio ruolo per la pubblicazione | operativo |

### Nota sui requisiti da ALBO-22 ad ALBO-26

Questi cinque sono **decisi ma non ancora sviluppati in testo esteso** come i primi
ventuno, e questa nota esiste perché il documento non prometta più di quello che contiene.

Da ALBO-23 ad ALBO-26 riguardano i permessi e i ruoli. Il meccanismo comune ricava i
permessi dedicati dall'identificativo del tipo di contenuto e **non li assegna a nessun
ruolo**: assegnarli spetta a questo componente, che è l'unico a sapere a chi spetta la sua
sezione. Da qui i quattro obblighi: darli all'attivazione, riportarli a ogni aggiornamento
(un componente che li assegna solo alla prima attivazione lascia scoperti i siti già
installati), segnalare in amministrazione la condizione in cui nessun ruolo li possiede, e
creare un ruolo proprio tenendo distinti chi prepara e chi pubblica. Il criterio di verifica
di ciascuno è nella tabella di `collaudo.md`.

ALBO-22 riguarda gli stati dell'atto e il percorso obbligato verso la pubblicazione. Il suo
testo esteso **non è ancora scritto**, e questo documento non lo inventa: verrà redatto
insieme all'unità che costruisce il flusso di pubblicazione. Fino ad allora la riga qui sopra
è il perimetro dichiarato, non la specifica.

## Come leggere la colonna Stato

Tre valori: **da fare** (il requisito esiste come impegno, il codice no), **in corso** (il
codice e i test esistono ma non risultano verdi nella verifica continua), **fatto** (verde
nella verifica continua). Un requisito passa a "fatto" solo quando tutte le sue righe di
`collaudo.md` sono "fatto". Oggi il componente è all'avvio della costruzione: **tutti i
requisiti sono a "da fare"**, e questa colonna esiste perché il repository non prometta
niente di falso a chi lo valuta.
