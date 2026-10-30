# Esportazione per la conservazione

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-17, righe di collaudo A-308..A-311.

## In tre paragrafi

**Cosa costruiamo.** Una schermata dell'amministrazione, "Esportazione", dove chi può
consultare gli atti scaduti sceglie un anno del registro e scarica un unico file compresso
con tutti gli atti numerati in quell'anno: quelli in corso, quelli scaduti, quelli tolti
prima della scadenza e quelli annullati, compresi gli importati. Dentro ci sono i dati di
ciascun atto, tutti i suoi documenti, anche le versioni sostituite da un oscuramento, e la
storia dell'atto dal registro delle modifiche (chi l'ha pubblicato, annullato, tolto, oscurato,
quando e perché). È il materiale che l'amministrazione consegna al proprio sistema di
conservazione: l'albo non conserva da sé e non si collega a nessun conservatore.

**Come la costruiamo.** Il file è un archivio ZIP con dentro tre cose leggibili senza
programmi particolari: `atti.json`, con tutti i dati in una forma descritta in
`docs/dati.md`; `atti.csv`, un indice da aprire con un foglio di calcolo; la cartella
`documenti/`, con un file per documento chiamato come lo scarica il pubblico (numero, date e
posizione, ALBO-30). Ogni documento porta nei dati la sua impronta SHA-256 e il suo posto
(principale, allegato, versione sostituita). Un ultimo file, `impronte.sha256`, elenca
l'impronta di ogni file dell'archivio nella forma che i comuni strumenti di verifica
leggono. Dove la mappa delle norme riporta un metadato dell'allegato 5 delle linee guida AgID
(impronta con algoritmo, registrazione con tipo di registro, numero e data, tracciature delle
modifiche) il dato esportato ne usa il significato. Il permesso è quello di consultare gli
scaduti, perché l'archivio contiene atti scaduti e versioni riservate; la schermata ha il
suo gettone. L'archivio si costruisce in una cartella temporanea non raggiungibile dal web,
si consegna e si cancella.

**Come si prova che funziona, e come si prova che il test è vero.** Un anno di prova con un
atto in corso, uno scaduto con il compito pianificato fermo, uno tolto, uno annullato, uno
oscurato e uno importato, più un atto dell'anno dopo e una bozza che non devono comparire.
La prova scarica l'archivio, lo apre con strumenti che non sono il componente, e confronta
ogni dato con quello che ha scritto lei stessa all'inizio: numeri, date, oggetti, impronte
calcolate dalla prova sui file usati, voci del registro. Poi rilegge l'archivio con la
funzione di lettura del componente e pretende gli stessi dati: è la prova che l'esportazione
si può rileggere (la "reimportabilità" della riga di collaudo). Chi non ha il permesso, un
gettone sbagliato e un anno senza atti hanno ciascuno il loro rifiuto. I guasti introdotti
per vedere quale prova li vede si elencano a lavoro finito.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-esportazione.php` (nuovo): raccolta degli atti dell'anno, costruzione
  dell'archivio, lettura di un archivio.
- `includes/class-schermata-esportazione.php` (nuovo): la schermata, il gettone, la consegna.
- `albo-pretorio-pa.php`: la voce di menu e l'invio.
- `funzioni-api.php`: `albo_pretorio_esporta_anno()`.
- `tests/EsportazioneTest.php` (nuovo); `docs/collaudo.md`, `docs/dati.md`,
  `docs/architettura.md`.

### 2. Requisito servito

ALBO-17. Tocca ALBO-29 (l'amministrazione ospitata, con il nome fissato alla pubblicazione)
e ALBO-30 (il nome dei documenti).

### 3. Il percorso di chi usa il sito

Menu degli atti, "Esportazione"; si sceglie l'anno fra quelli che hanno atti numerati, la
schermata dice quanti atti e quanti documenti contiene; "Scarica l'esportazione" consegna
`albo-esportazione-AAAA.zip`.

### 4. Dati letti e scritti

Letti: atti numerati dell'anno (registro, dati dell'atto, documenti, versioni sostituite,
voci del registro delle modifiche dell'atto). Scritti: niente sull'atto; una voce nel
registro delle modifiche per ogni esportazione (chi, quando, anno, numero di atti, impronta
dell'archivio), perché un'uscita in blocco di dati personali va tracciata.

### 5. Permessi coinvolti

Il permesso di consultare gli scaduti, più il gettone della schermata.

### 6. Modi di guasto previsti

- Un documento che non si legge dal disco: l'esportazione si ferma e lo dice, invece di
  consegnare un archivio incompleto.
- La lettura del registro che fallisce: idem.
- Un anno molto grande: il limite di tempo del server. La schermata mostra la dimensione
  prima di scaricare; la divisione in parti è un passo successivo, se servirà.

### 7. Test aggiunti

A-308..A-311, in `tests/EsportazioneTest.php`.

### 8. Cosa non implementa

- **Non è un pacchetto di versamento** per un conservatore accreditato: ogni conservatore ha
  il suo formato, e il pacchetto lo prepara chi conserva, partendo da questo archivio.
- **Non reimporta** un archivio in un altro sito: rileggerlo è provato, ricaricarlo come
  atti nuovi è un'altra unità, da fare solo se qualcuno la chiede.
- **Non esporta le bozze** né gli atti senza numero.
- **Non esporta la configurazione** (durate, festivi, amministrazioni ospitate): è un altro
  passo.

### 9. Come si prova su un'installazione reale

Sulla copia di prova: esportare l'anno con gli atti importati, aprire l'archivio, verificare
le impronte con `sha256sum -c impronte.sha256`, aprire `atti.csv` con un foglio di calcolo e
qualche documento a caso.

## Com'è andata

**La voce di menu** sta sotto il menu degli atti per chi lavora sugli atti, e sotto "Atti
scaduti" per chi consulta e basta, come la consultazione riservata.

**Lo stato e la fine passata sono due dati.** Lo stato è quello scritto (un atto scaduto con
il compito pianificato fermo risulta pubblicato); `fine_passata` si legge dalla data, come fa
ogni lettura dell'albo. Il nome non dice "scaduto": la sentinella di A-178 tiene fuori questa
parola da ogni nome di dato, perché non nasca un'impostazione sugli scaduti.

**Chi ha fatto cosa.** Il registro conserva il numero dell'utente e nessun altro dato
personale. L'esportazione aggiunge accanto al numero il nome mostrato di quell'utente, letto
al momento dell'esportazione, perché fuori dal sito un numero non dice niente. Se l'utente è
stato cancellato, il nome resta vuoto e il numero c'è.

**"Reimportabile" è la rilettura.** La riga di collaudo dice "reimportabile". La lettura scelta
è la più stretta che serve alla conservazione: l'archivio si rilegge con
`albo_pretorio_leggi_esportazione()`, che controlla ogni impronta e rifiuta un archivio
alterato, e dà gli stessi dati che la prova legge da sé. Ricaricare gli atti in un altro sito
non è costruito.

**L'ordine degli atti** è per numero. Il registro dei numeri li restituisce già in quell'ordine,
quindi togliere l'ordinamento non fa fallire nessuna prova: resta come garanzia, perché
quell'ordine della banca dati non è promesso.

**Guasti introdotti per vedere quale prova li vede**, ciascuno tolto subito dopo:

| Guasto | Visto da |
|---|---|
| documento non confrontato con l'impronta del deposito | A-310 |
| lettura fallita del registro scambiata per nessuna voce | A-310 |
| permesso non controllato | A-309 |
| versioni sostituite lasciate fuori | A-308 |
| rilettura che non confronta le impronte | A-310 |
| rilettura che accetta file non elencati | A-310 |
| rilettura che accetta dati che nominano un documento assente | A-310 |
| voce di registro non scritta | A-308, A-309 |
| voce di registro rifiutata e archivio consegnato lo stesso | A-310 |
| archivio lasciato sul disco quando la voce è rifiutata | A-310 |
| archivio lasciato sul disco dopo la consegna | A-309 |
| gettone non controllato | A-309 |
| storia di tutti gli atti in ogni atto | A-308 |
| nome dell'amministrazione ospitata di adesso invece di quello fissato | A-311 |
| amministrazione ospitata non esportata | A-311 |
| ordinamento per numero tolto | nessuna (vedi sopra) |
