# Sostituzione per oscuramento

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: passo 1 fatto (righe A-293..A-301), passi 2 e 3 da fare.** Requisito ALBO-12, con le righe che lo nominano come controllo positivo o
come caso: ALBO-08 (il repertorio che non cambia), ALBO-09 (l'unica eccezione
all'immodificabilità), ALBO-10 (le due impronte nel registro), ALBO-20 (la memoria di
pagina), ALBO-22 (la dichiarazione dell'effetto su un atto oscurato), ALBO-27 (le date che
non cambiano) e ALBO-30 (il nome del file scaricato). Righe di collaudo da A-293.

## In tre paragrafi

**Cosa costruiamo.** Chi pubblica apre un atto pubblicato e non scaduto che espone dati
eccedenti, non pertinenti o non necessari, sceglie il documento da oscurare (il principale
o un allegato), carica la versione oscurata e conferma che lo è. Da quel momento il pubblico
scarica la versione oscurata, e la scheda pubblica la mostra al posto dell'altra; il numero
di registro, la data di inizio e la data di fine non cambiano, e il termine continua a
correre. La versione uscente non si cancella: è parte dell'atto adottato e resta fuori dalla
vista pubblica, consultabile solo da chi ha il permesso di consultare o di pubblicare. Il
registro delle modifiche conserva le impronte di tutte e due le versioni, chi ha disposto la
sostituzione, quando e il motivo. La sostituzione non si annulla; un documento già oscurato
si può oscurare di nuovo, e ogni passaggio resta registrato. Le frasi che dicevano "non
ancora disponibile" (A-280) ora rinviano a questa operazione.

**Come la costruiamo.** È un passaggio in `Passaggi`, come la defissione: permesso di
pubblicare, atto nello stato pubblicato e con la data di fine non passata letta
nell'istante, documento che è davvero di quell'atto, file entrante depositato dal meccanismo
comune nella cartella protetta con la sua impronta, conferma esplicita, motivo obbligatorio.
Poi, sotto il blocco e nella transazione, una sola scrittura: il dato dell'atto che indica il
documento (principale o posto nell'elenco degli ulteriori) passa all'entrante, e l'uscente
viene legato all'atto come versione sostituita. Il guardiano, che oggi ferma ogni scrittura
su un atto pubblicato, concede soltanto quella scrittura e soltanto con il gettone del
passaggio; un controllo finale verifica che niente altro sia cambiato (stato, date, numero,
oggetto, dati di scheda, gli altri documenti). Se un pezzo fallisce si torna come prima e il
file entrante si toglie. La consegna pubblica serve solo i documenti indicati dal dato
dell'atto, quindi la versione uscente smette di essere servita nell'istante della scrittura;
la consegna amministrativa la dà a chi consulta o pubblica.

**Come si prova che funziona, e come si prova che il test è vero.** Atti portati a
pubblicato dai passaggi veri, orologio fissato, la sostituzione fatta giorni dopo la
pubblicazione e non subito, così che una data riscritta si veda. Le impronte attese si
calcolano nella prova, fuori dal componente, sui due file usati, e il file scaricato dal
pubblico dopo la sostituzione deve avere l'impronta dell'entrante; prima della sostituzione
la prova dimostra che l'uscente era scaricabile, così che la riga non sia verde chiudendo
ogni scaricamento. Le righe d'attacco cambiano una condizione per volta (chi non pubblica,
annullato con fine futura, scaduto con il compito pianificato spento, causa diversa, un
campo estraneo per volta nella richiesta) e ciascuna ha il suo controllo positivo. Il tutto
o niente si prova facendo fallire un pezzo per volta. I guasti introdotti nel codice per
vedere quale prova li vede si elencano a lavoro finito.

## Il piano in tre passi

1. **Il passaggio da codice** (`albo_pretorio_oscura()`): controlli, scrittura sotto il
   guardiano, registro con le due impronte, consegna pubblica dell'entrante e
   amministrativa dell'uscente, tutto o niente. Righe di ALBO-12 sulla sostituzione, di
   ALBO-08, ALBO-09 e ALBO-27 sugli attacchi, di ALBO-10 sulle impronte.
2. **Il riquadro**: nel riquadro Pubblicazione dell'atto pubblicato e non scaduto, per ogni
   documento, "Sostituisci con la versione oscurata", con il campo del file, il motivo e la
   conferma; l'elenco delle sostituzioni già fatte. Le frasi di A-280 cambiano e rinviano
   qui.
3. **Il resto delle righe**: la memoria di pagina dopo la sostituzione (ALBO-20), il nome del file
   scaricato prima e dopo (ALBO-30), la dichiarazione dell'effetto rifiutata su un atto
   soltanto oscurato e accettata dopo una defissione (ALBO-22).
4. **La versione oscurata già alla pubblicazione** (la prima riga di ALBO-12): una bozza
   con l'originale e la sua versione oscurata, di cui il pubblico vede solo la seconda.
   **Rinviato** (decisione del 2026-10-30): chi prepara l'atto carica già la sola versione
   oscurata, e l'originale resta nel sistema documentale dell'amministrazione. Si costruisce
   quando un'amministrazione chiede di conservarlo anche nell'albo.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-passaggi.php` | il passaggio `oscura_documento()` e la sua azione nel registro |
| `includes/class-chiusura-pubblicazione.php` | la concessione della sola scrittura del dato del documento, con il gettone del passaggio |
| `includes/class-documenti-atto.php` | la condizione del punto pubblico (solo i documenti indicati dai dati dell'atto), la lettura del segno della versione sostituita e il rifiuto in amministrazione a chi redige soltanto |
| `includes/class-tipo-atto.php` | la condizione dichiarata al meccanismo comune con la chiave `allegati_seguono_la_sezione` |
| `includes/funzioni-api.php` | la funzione pubblica `albo_pretorio_oscura()` |
| `includes/class-riquadro-passaggi.php` | il riquadro del secondo passo |
| `albo-pretorio-pa.php` | il nome del segno sulla versione sostituita e l'aggancio della consegna amministrativa |
| `tests/OscuramentoTest.php` | le righe da A-293 |

### 2. Requisito servito

ALBO-12, e le parti di ALBO-08, ALBO-09, ALBO-10, ALBO-20, ALBO-22, ALBO-27 e ALBO-30 che
aspettavano la sostituzione.

### 3. Il percorso di chi usa il sito

Chi pubblica apre l'atto, trova nel riquadro Pubblicazione il documento con i dati in più,
sceglie "Sostituisci con la versione oscurata", carica il file, scrive il motivo, spunta la
conferma e invia. Torna sull'atto con l'esito; nel riquadro compare la sostituzione con data,
autore e motivo. Il cittadino, sulla scheda pubblica, scarica da quel momento la versione
oscurata, con lo stesso nome di prima.

### 4. Dati letti e scritti

Si legge l'atto, il suo stato, il numero, la data di fine, i documenti. Si scrive: un
allegato nuovo depositato dal meccanismo comune; il dato del documento dell'atto (principale
o elenco degli ulteriori); il legame dell'uscente come versione sostituita; una voce di
registro `oscuramento` con documento, posto, impronte uscente ed entrante, causa, motivo.
Le date, il numero e lo stato non si scrivono.

### 5. Permessi coinvolti

Il permesso di pubblicare atti. Chi redige soltanto non sostituisce e non vede le versioni
sostituite; chi consulta soltanto le vede in sola lettura.

### 6. Modi di guasto previsti

- La data di inizio o di fine riscritta durante la sostituzione: il controllo finale e A-293.
- Il file uscente ancora servito al pubblico da un percorso dell'elenco chiuso: la consegna
  pubblica legge il dato dell'atto, e la prova scarica dopo.
- Il file entrante rimasto sul disco senza atto dopo un fallimento: si toglie, e la prova lo
  cerca.
- Una richiesta che porta con sé un campo estraneo (data, numero, oggetto): rifiutata per
  intero, un campo per volta.
- L'atto scaduto con il compito pianificato spento, ancora "pubblicato" nella banca dati:
  si guarda la data, non lo stato.
- Due sostituzioni insieme sullo stesso documento: il blocco dell'atto le mette in fila, e
  la seconda vede la prima.

### 7. Test aggiunti

Da A-293, in `tests/OscuramentoTest.php`, scritti prima del codice di ciascun passo.

### 8. Cosa non implementa

Non verifica che il file entrante sia davvero la versione oscurata di quello uscente: per un
programma sono due file diversi, e la responsabilità resta di chi dispone la sostituzione,
che per questo la conferma e la firma nel registro. Non oscura da sé i documenti e non
suggerisce cosa oscurare. Non tocca gli atti scaduti, defissi o annullati.

### 9. Come si prova su un'installazione reale

Sulla copia di prova: pubblicare un atto con un PDF, aprirlo da una finestra anonima e
scaricarlo; sostituirlo con un PDF diverso dal riquadro; riscaricarlo dalla finestra anonima
e verificare che sia il nuovo, con lo stesso nome; verificare nel registro le due impronte e
che numero e date siano rimasti quelli.

## Com'è andato il passo 1

**Il punto pubblico serviva ogni figlio dell'atto.** Il meccanismo comune consegna qualunque
allegato depositato e figlio di un atto pubblicato e non scaduto, senza guardare i dati
dell'atto. La versione uscente, rimasta figlia dell'atto, sarebbe uscita ancora a chi
conosceva il suo indirizzo. L'albo ora dichiara al meccanismo comune la condizione
`DocumentiAtto::consegnabile()` (interfaccia 1.10.0, chiave `allegati_seguono_la_sezione`
con una funzione): serve solo i documenti indicati dai dati dell'atto. La dichiarazione toglie
al meccanismo comune l'anello del tipo consultabile, che la condizione ripete identico.

**La consegna amministrativa dava la versione uscente a chi redige.** Il meccanismo comune
la apre a chi gestisce il tipo e legge l'atto, e un atto pubblicato lo legge chiunque. Un
aggancio dell'albo prima della consegna rifiuta a chi non consulta e non pubblica ogni
figlio dell'atto che i dati dell'atto non indicano, con lo stesso "non trovato" della
consegna (A-298). La regola è la stessa del punto pubblico e non dipende dal segno della
sostituzione, che serve al registro e alle schermate: un segno mancante, su due righe o
guasto non riapre il file.

**Le due autorizzazioni leggono dalla banca dati.** Il punto pubblico e la consegna
amministrativa decidono con `DocumentiAtto::indicati()`, che legge i dati dell'atto
direttamente e non dalla memoria di WordPress: una richiesta che li avesse in memoria da
prima di una sostituzione chiusa nel frattempo non apre la versione uscente. Una lettura che
non risponde vale no (A-301).

**Il file entrante si deposita prima della transazione**, perché il deposito può chiedere
al server di provare la cartella protetta, e una richiesta di rete non sta dentro un blocco
della banca dati. La fotografia di partenza si prende dopo il deposito, con l'entrante già
fra i figli. Se la sostituzione non riesce il file entrante si toglie; il meccanismo comune
registra da sé l'allegato aggiunto e tolto, e quelle due voci restano: sono il racconto
vero di quello che è successo al disco (A-299).

**Una scrittura rifiutata dal guardiano non annulla la sostituzione.** Come negli altri
passaggi, una scrittura dei dati dell'atto fatta con le funzioni di WordPress durante la
sostituzione è rifiutata e il passaggio prosegue con l'atto com'era; una scrittura diretta
nella banca dati passa, e il controllo finale la trova e annulla tutto (A-300).

**La durata propria del singolo atto non esiste ancora** (ALBO-04): la ripetizione della
prima riga di ALBO-12 su un atto abbreviato si fa cambiando la durata del tipo dopo la
pubblicazione, che smaschera lo stesso difetto, un ricalcolo della fine. Quando ALBO-04 ci
sarà la riga si rifà su quella.

**Guasti introdotti per vedere quale prova li vede**, ciascuno tolto subito dopo:

| Guasto | Visto da |
|---|---|
| condizione del punto pubblico non dichiarata | A-294 |
| rifiuto in amministrazione spento | A-298 |
| rifiuto in amministrazione appoggiato al segno invece che ai dati dell'atto | A-298 |
| punto pubblico che legge i dati dell'atto dalla memoria | A-301 |
| consegna amministrativa che legge i dati dell'atto dalla memoria | A-301 |
| lettura fallita che ripiega sulla memoria | A-301 |
| causa non controllata | A-296 |
| dati estranei ammessi | A-297 |
| data di fine non guardata | A-295 |
| permesso di pubblicare non guardato | A-295 |
| stato non guardato | A-295 |
| controllo finale del dato del documento spento | A-300 |
| guardiano che concede ogni chiave durante la sostituzione | A-300 |
| guardiano che concede la chiave giusta con qualunque valore | A-300 |
| file entrante non tolto dopo un fallimento | A-299 |
| impronta dell'entrante registrata al posto di quella dell'uscente | A-294 |
| allegato ulteriore entrante messo in fondo all'elenco | A-294 |
| documento non più nel suo posto sotto il blocco, non controllato | nessuna: il blocco confronta già la fotografia intera, quindi il controllo è una difesa in più che nessuna prova raggiunge |

## Com'è andato il passo 2

**Una parte sola, non un pulsante per documento.** Il piano diceva "per ogni documento". Il
riquadro ha invece una parte "Oscura i dati in più di un documento" con un'opzione per
documento (il principale e ogni allegato con la sua posizione), un solo campo del file, il
motivo, la conferma e il pulsante "Sostituisci con la versione oscurata". Un pulsante per
documento avrebbe ripetuto file, motivo e conferma tante volte quanti sono gli allegati.
Nessuna opzione è già scelta, come per la causa della defissione: la scelta è di chi
sostituisce, e un invio senza scelta è rifiutato.

**Chi la vede.** Solo chi pubblica, solo su un atto pubblicato con la fine non passata,
letta dalla data e non dallo stato: con il compito pianificato fermo, il giorno dopo la
fine la parte non c'è anche se lo stato è ancora "pubblicato". Il passaggio da codice fa
comunque tutti i controlli: la schermata non è una protezione.

**Il file arriva sempre come caricamento.** Il riquadro consegna il file al meccanismo
comune dichiarandolo caricamento, e il meccanismo comune accetta solo un file arrivato con
la richiesta HTTP. Per questo un caricamento vero non si prova nelle prove automatiche: il
controllo positivo di A-303 è un invio con tutti i campi giusti e un file del disco, che
passa ogni controllo dell'albo e si ferma soltanto al deposito.

**L'elenco delle sostituzioni** sta nel riquadro dell'atto pubblicato, tolto o annullato,
in sola lettura dal registro: quando, chi, il posto e il motivo. Il collegamento alla
versione di prima lo vede solo chi la può aprire; per gli altri la consegna risponderebbe
"non trovato". Se il registro non risponde il riquadro lo dice (A-304). Il meccanismo comune
risponde con un elenco vuoto anche a una lettura fallita, quindi l'errore si legge dalla
connessione, come per l'effetto della rimozione.

**I testi.** Le frasi di A-280 rinviano ora a questa parte. La scheda dei documenti, su un
atto non più in bozza, aggiunge che un documento con dati personali in più si sostituisce
soltanto con la sua versione oscurata, dal riquadro Pubblicazione.

**Guasti introdotti per vedere quale prova li vede**, ciascuno tolto subito dopo:

| Guasto | Visto da |
|---|---|
| parte mostrata senza il permesso di pubblicare | A-302 |
| parte nascosta già l'ultimo giorno | A-302 |
| parte mostrata dopo la fine | A-302 |
| conferma accettata per qualunque atto | A-303 |
| file dichiarato percorso del server invece che caricamento | A-303 |
| invio senza documento scelto non fermato dal riquadro | A-303 |
| file mancante non fermato dal riquadro | A-303 |
| file arrivato male non fermato dal riquadro | A-303 |
| collegamento alla versione di prima mostrato a tutti | A-304 |
| elenco assente sull'atto tolto | A-304 |
| registro guasto taciuto | A-304 |
| lettura fallita del registro scambiata per nessuna sostituzione | A-304 |

## Com'è andato il passo 3

**Tre righe che aspettavano la sostituzione, provate senza toccare il codice.** Le strade
erano già giuste: le prove mancavano perché la sostituzione non esisteva.

- **Memorie di pagina (ALBO-20, A-305).** Nessuna risposta dell'albo si lascia conservare
  (A-259), quindi dopo una sostituzione a pubblicazione attiva non c'è nessuna copia della
  versione uscente da svuotare: la scheda porta alla versione oscurata e l'uscente non si
  scarica più.
- **Nome del file (ALBO-30, A-306).** Il nome si calcola da numero, inizio e fine dell'atto,
  che la sostituzione non cambia, e dalla posizione del documento, che l'entrante prende
  dall'uscente: il nome scaricato resta identico a prima.
- **Dichiarazione dell'effetto (ALBO-22, A-307).** Su un atto soltanto oscurato non c'è
  niente da dichiarare, perché il conteggio non si è fermato; dopo una defissione la
  dichiarazione riesce.

**La prima riga di ALBO-12 diventa un passo a sé.** Pubblicare un atto già con la versione
oscurata, tenendo l'originale integro fuori dalla vista pubblica, non è una prova di quello
che c'è: è una funzione nuova della bozza (caricare insieme originale e versione oscurata).
Ha la sua unità, dopo questa.

**Guasti introdotti per vedere quale prova li vede**, ciascuno tolto subito dopo:

| Guasto | Visto da |
|---|---|
| risposte dell'albo senza il divieto di conservarle | A-305 |
| posizione dell'allegato contata sui file dell'atto invece che sui dati dell'atto | A-306 |
| dichiarazione ammessa su un atto con una sostituzione registrata, senza guardare lo stato | A-307 |
