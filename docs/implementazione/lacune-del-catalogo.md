# Lacune del catalogo di collaudo

Scheda di lavorazione. La rilettura del catalogo del 1 novembre 2026 ha trovato tre righe
coperte in parte, a cui mancano casi piccoli che il componente già rispetta. Ogni caso è una
PR di sole prove, corta. La scheda si aggiorna a ogni PR con com'è andata.

**Stato: fatto.** Requisiti ALBO-04, ALBO-27, ALBO-21, ALBO-18. Righe di collaudo A-339, A-344, A-345, A-346, più un caso aggiunto ad A-231.

## In tre paragrafi

**Cosa costruiamo.** Nessuna funzione nuova. Tre prove che chiudono altrettanti buchi del
catalogo. La prima: chi pubblica un atto il cui tipo non ha una durata valida non lo salva
scrivendo a mano una data di fine. La seconda: la data di inizio di un atto pubblicato non si
cambia nemmeno dalla schermata di WordPress. La terza: un atto la cui fine cade proprio nel
giorno del cambio dell'ora scade all'istante giusto.

**Come la costruiamo.** Ogni prova segue la riga del catalogo che chiude, e usa gli stessi
assetti delle prove vicine: atto preparato e mandato in verifica con il passaggio vero,
orologio fissato, fuso del sito su Roma.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove passano già sul
codice di oggi, perché descrivono cose che il componente rispetta. Per mostrare che sono vere
si introducono a mano i guasti che vogliono impedire, e si guarda quale prova se ne accorge.

## I nove punti

### 1. File che si pensa di creare o modificare

Le prove in `tests/`, `docs/collaudo.md`, questa scheda. Nessun sorgente.

### 2. Requisiti serviti

ALBO-04 (durate per tipo), ALBO-27 (date scritte dal sistema), ALBO-21 (scadenza sull'ora
civile).

### 3. Il percorso di chi usa il sito

Nessun cambiamento.

### 4. Dati letti e scritti

Nessuno nuovo.

### 5. Permessi coinvolti

Nessuno nuovo.

### 6. Modi di guasto previsti

- Una data di fine fornita che sostituisce la durata mancante: A-339.
- Una fine calcolata su una durata letta prima della pubblicazione: A-339.
- La data di inizio di un atto pubblicato cambiata dalla schermata di WordPress o dal riquadro
  dell'albo, da un ruolo qualsiasi: A-344.
- Una fine che nel giorno del cambio dell'ora scade un'ora prima o un'ora dopo la mezzanotte
  del sito, perché il giorno si conta come 24 ore o con lo scarto dall'ora universale di un
  altro giorno: A-345.

### 7. Test aggiunti

A-339 (ALBO-04), A-344 (ALBO-27), A-345 (ALBO-21). A-231 allungata per ALBO-18. A-346
(ALBO-04) per il testo del rifiuto.

### 8. Cosa non implementa

Niente. Il testo del rifiuto quando il tipo non ha durata, rimasto aperto dopo A-339, è stato
deciso il 3 novembre 2026: lo descrive la sezione su A-346.

### 9. Come si prova su un'installazione reale

Sulla copia di prova: togliere la durata a un tipo con un atto in verifica, provare a
pubblicarlo, poi rimettere la durata e pubblicare. Su un atto pubblicato, da ogni ruolo: aprire
la schermata, cambiare la data di pubblicazione con il pulsante Modifica accanto alla data,
premere Aggiorna. L'atto deve restare con la sua data e la schermata deve spiegare il rifiuto.
Per il cambio dell'ora non serve aspettare ottobre: si guarda, nell'elenco pubblico, un atto
la cui fine è il giorno del cambio, la mattina dopo, e deve essere sparito dalla mezzanotte.

## Com'è andata

### A-339 (ALBO-04)

La prova passa sul codice di oggi. I due controlli sulla durata, quello dei dati mancanti e
quello del calcolo della fine, si coprono a vicenda: guastarne uno solo non basta perché la
prova lo veda, e lo si dichiara.

| Guasto introdotto | Visto da |
|---|---|
| La data di fine fornita al posto della durata mancante | A-339 |
| Una durata fissa invece di quella vigente alla pubblicazione | A-339 |
| La durata letta senza validazione nel calcolo della fine | nessuna, perché la ferma il controllo dei dati mancanti |
| La durata letta senza validazione in tutti e due i controlli | A-339 |

### A-344 (ALBO-27)

La prova passa sul codice di oggi. Una scrittura su un atto pubblicato incontra due fermi: il
primo la ferma prima che arrivi alla banca dati, il secondo, se la scrittura ci arriva lo
stesso, riscrive la riga com'era. Togliere solo il secondo per le date non basta perché la
prova lo veda, perché il primo ferma tutto: lo si dichiara, come per A-339. Il riquadro
dell'albo non legge nessun campo di data, e la richiesta manipolata lo conferma.

| Guasto introdotto | Visto da |
|---|---|
| Il primo fermo tolto per gli atti pubblicati | A-344, A-103 |
| Il secondo fermo che lascia passare le date | nessuna, perché il primo fermo ferma la scrittura prima |
| Tutti e due i fermi, il secondo per le sole date | A-344, A-103 |

### A-345 (ALBO-21)

La prova passa sul codice di oggi. La fine la scrive l'albo come giorno di calendario, e
l'istante in cui l'atto scade lo calcola il meccanismo comune: la mezzanotte del sito che
chiude quel giorno. I guasti che contano stanno quindi nel meccanismo comune, e la prova li
vede. Un'osservazione utile per chi tocca quel codice: in PHP `modify( '+86400 seconds' )`
conta sull'ora del muro e dà lo stesso risultato di `+1 day`, quindi non è un guasto; lo è
sommare 24 ore vere. Contare la durata in ore invece che in giorni non cambia niente qui,
perché il cambio cade dopo la mezzanotte del giorno di fine: lo vedono già le prove di A-98.

| Guasto introdotto | Visto da |
|---|---|
| La fine del giorno come inizio del giorno più 24 ore vere | A-345 |
| Il giorno in più aggiunto in tempo universale | A-345 |
| La data di fine letta con uno scarto fisso di un'ora dall'ora universale | A-345 |
| Il confronto fatto sull'ora del muro invece che sull'istante | A-345 |
| Il confronto stretto, che lascia pubblicato l'atto nell'istante della mezzanotte | A-345 |

### A-231 allungata (ALBO-18)

Una quarta lacuna, trovata dopo: la riga ALBO-18 non può dire che A-231 rifiuta il documento
dell'annullato importato dopo la sua fine, perché A-231 dalla mezzanotte guardava l'elenco e
la scheda ma non il documento. Per l'annullato che passa dai passaggi lo prova già A-228. Si
aggiungono tre controlli in fondo ad A-231, senza una riga nuova: l'atto 2 del backup, in
corso con la stessa fine, il 16 alle 23:59:59 si scarica; dalla mezzanotte non si scarica più,
e il documento dell'annullato risponde allo stesso modo, per intero.

Dopo la fine il documento dell'annullato è tenuto da più recinti che non guardano la data: lo
stato annullato non è pubblicato per il punto di consegna, non è uno stato pubblico per
WordPress, e la pagina dell'albo nega la consegna dell'annullato. Guastarne uno o anche tre
insieme non basta perché una prova lo veda, e lo si dichiara come per A-339 e A-344.

| Guasto introdotto | Visto da |
|---|---|
| La pagina dell'albo che non nega più la consegna dell'annullato | nessuna, per gli altri recinti |
| Lo stato dell'albo contato come non esposto nel controllo della scadenza | nessuna, per gli altri recinti |
| Questo, più la consegna che ammette uno stato non pubblicato se scaduto e la pagina che nega l'annullato solo prima della fine | nessuna, per il controllo sulla consultabilità dello stato |
| Il punto di consegna che dà il file dell'annullato dopo la sua fine | A-231, A-228 |

### A-346 (ALBO-04, testo del rifiuto)

La riga di ALBO-04 vuole il rifiuto attribuito alla configurazione del tipo, non a una data
mancante. Il testo di prima diceva che la data di fine non si calcola, e chi lo leggeva poteva
pensare di doverla scrivere lui. Il testo nuovo dice, nell'ordine: che l'atto resta in
verifica; quale tipo non ha una durata valida; che la data di fine la calcola il sistema e non
si inserisce a mano; chi corregge e dove (Albo pretorio > Tipi di atto); che poi si riprova con lo stesso atto. Lo
stesso testo lo legge già chi manda una bozza in verifica, poi chi pubblica da codice e dal
riquadro, e l'annuncio del riquadro lo dice prima che si provi a pubblicare. I codici dei
rifiuti non cambiano.

È l'unica PR della scheda che tocca un sorgente: il testo sta in una funzione sola, `DatiAtto::motivo_senza_durata()`,
usata dal controllo dei dati all'invio in verifica, dal rifiuto, dal rifiuto di riserva e
dall'annuncio. Il rifiuto di riserva, che scatta se la
durata si guasta fra il controllo dei dati e il calcolo della fine nella stessa richiesta,
non si raggiunge da una prova senza un guasto apposta: usa la stessa funzione, e lo si
dichiara.

| Guasto introdotto | Visto da |
|---|---|
| Il rifiuto con il testo di prima | A-346 |
| L'annuncio del riquadro con il testo di prima | A-346 |
| Il testo che non nomina il tipo | A-346 |
| L'invio in verifica con il testo di prima | A-346 |
