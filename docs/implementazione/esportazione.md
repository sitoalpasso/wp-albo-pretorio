# Esportazione per la conservazione

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-17, righe di collaudo A-308..A-315.

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

A-308..A-315, in `tests/EsportazioneTest.php`.

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

**Un numero perso non è un atto che non si legge** (dalla rilettura). Il registro dei numeri
tiene anche i numeri presi e poi persi: la bozza cancellata, rimessa in bozza o nel cestino, e
la pubblicazione fallita dopo il numero, che lascia l'atto in verifica. Questi si saltano. La
prima versione saltava anche l'atto che la banca dati in quel momento non restituiva: l'archivio
usciva senza un atto e la voce del registro dichiarava un conteggio sbagliato. Ora tipo e stato
di tutti gli atti numerati si leggono in una sola interrogazione che distingue la riga assente
dalla risposta mancata; una risposta mancata, un atto che c'è ma non si legge, o uno stato che
non è dell'albo fermano l'esportazione.

**L'archivio non resta sul server** (dalla rilettura). Chi consegna lo cancella subito dopo, ma
una richiesta può finire prima: il browser che chiude la connessione, il tempo massimo del
server su un anno grosso. E se la cartella temporanea del sistema non è scrivibile, WordPress
ripiega su una cartella del sito. Per questo l'archivio, appena creato, si segna per la
cancellazione alla chiusura della richiesta, che gira anche in quei casi; e la consegna prosegue
anche se il browser chiude, con le memorie di uscita svuotate prima di mandare il file.

**Le versioni sostituite si leggono come gli atti** (dalla revisione automatica). I figli
dell'atto si leggevano con la funzione di WordPress, che dà un elenco vuoto anche quando la
banca dati non risponde: l'archivio poteva uscire senza le versioni di prima ed essere
registrato come completo. Ora figli e segni di sostituzione si leggono in una sola
interrogazione che distingue le due cose. Un figlio che porta il segno ma guasto (su due righe
o con un valore che non è un documento) entra lo stesso fra le versioni sostituite, senza il
rimando al sostituto: per la conservazione è meglio un file in più che uno in meno.

**Le formule nell'indice** (dalla revisione automatica). L'oggetto lo scrive chi redige, e un
foglio di calcolo esegue come formula una cella che comincia con `=`, `+`, `-`, `@`, una
tabulazione o un ritorno a capo. Nell'indice CSV questi testi escono con un apostrofo davanti;
`atti.json` resta identico. Anno, numero e conteggio dei documenti sono numeri e non si
toccano.

**Ogni dato dell'atto si legge sapendo se la lettura ha risposto** (dalla revisione
automatica, secondo giro). Le funzioni che la scheda usa per tipo, organo, data di adozione,
numero proprio, dati del procedimento, origine dell'importazione e amministrazione ospitata
danno un campo vuoto sia quando il dato manca sia quando la banca dati non risponde: l'atto
usciva con un campo vuoto in un archivio registrato come completo. Ora l'esportazione legge con
interrogazioni proprie tutti i dati dell'atto, le sue voci di tipo e organo e i nomi degli
autori delle modifiche, e li interpreta con le stesse regole della scheda, che per questo sono
diventate funzioni a sé (`data_adozione_da_righe()`, `numero_proprio_da_righe()`,
`valore_da_righe()`, `di_atto_da()`). La fine passata si calcola dall'istante di scadenza,
letto con la risposta mancata distinta: la funzione "scaduto" del componente comune dà
"scaduto" anche quando non legge, giusto per chi mostra, sbagliato per chi conserva. Due
letture restano quelle della scheda, il principale con gli allegati e il nome del file, e si
confrontano con quello che danno le letture già verificate: se non coincidono, l'esportazione si
ferma. L'istante di inizio sta nell'atto già letto e non richiede letture.

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
| atto in verifica con il numero non saltato | A-312 |
| riga assente non saltata | A-312 |
| atto che non si legge saltato | A-312 |
| stato inatteso saltato | A-312 |
| archivio non segnato per la cancellazione | A-312 |
| chiusura della richiesta che non cancella | A-312 |
| figli non letti presi per nessun figlio | A-313 |
| apostrofo tolto dall'indice | A-314 |
| tabulazione dimenticata fra i caratteri delle formule | A-314 |
| rimando al sostituto perso | A-308, A-313 |
| dati dell'atto non letti presi per nessun dato | A-315 |
| tipo e organo non letti presi per nessuna voce | A-315 |
| autori non letti presi per autori cancellati | A-315 |
| principale e allegati non confrontati con le letture verificate | A-315 |
| nome del file non confrontato | A-315 |
| istante di scadenza non controllato | A-315 |
| tipo del file perso | A-315 |
| scelta dell'amministrazione ospitata ignorata | A-311 |
| voci di conferma non filtrate | A-311 |
| apostrofo anche sui numeri | nessuna: anno, numero e conteggio non sono mai negativi, la distinzione resta come garanzia |
