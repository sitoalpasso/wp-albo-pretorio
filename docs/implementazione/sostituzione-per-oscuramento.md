# Sostituzione per oscuramento

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: piano.** Requisito ALBO-12, con le righe che lo nominano come controllo positivo o
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
si può oscurare di nuovo, e ogni passaggio resta registrato. Le frasi che oggi dicono "non
ancora disponibile" (A-280) rinviano a questa operazione.

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
   qui; il thread dei manuali aggiorna il capitolo.
3. **Il resto delle righe**: la versione oscurata già prima della pubblicazione (la prima
   riga di ALBO-12), la memoria di pagina dopo la sostituzione (ALBO-20), il nome del file
   scaricato prima e dopo (ALBO-30), la dichiarazione dell'effetto rifiutata su un atto
   soltanto oscurato e accettata dopo una defissione (ALBO-22).

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-passaggi.php` | il passaggio `oscura_documento()` e la sua azione nel registro |
| `includes/class-chiusura-pubblicazione.php` | la concessione della sola scrittura del dato del documento, con il gettone del passaggio |
| `includes/class-documenti-atto.php` | le versioni sostituite di un atto e la loro lettura, che non le conta fra i documenti pubblici |
| `includes/class-riquadro-passaggi.php` | il riquadro del secondo passo |
| `albo-pretorio-pa.php` | la funzione pubblica `albo_pretorio_oscura()` |
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
