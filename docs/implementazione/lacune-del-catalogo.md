# Lacune del catalogo di collaudo

Scheda di lavorazione. La rilettura del catalogo del 1 novembre 2026 ha trovato tre righe
coperte in parte, a cui mancano casi piccoli che il componente già rispetta. Ogni caso è una
PR di sole prove, corta. La scheda si aggiorna a ogni PR con com'è andata.

**Stato: in corso.** Requisiti ALBO-04, ALBO-27, ALBO-21. Righe di collaudo da A-339.

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

### 7. Test aggiunti

A-339 (ALBO-04).

### 8. Cosa non implementa

Il testo del rifiuto quando il tipo non ha durata resta quello di oggi, che nomina la data di
fine che non si calcola. La riga di ALBO-04 lo vorrebbe attribuito alla configurazione del
tipo: è una scelta da fare, non una lacuna delle prove.

### 9. Come si prova su un'installazione reale

Sulla copia di prova: togliere la durata a un tipo con un atto in verifica, provare a
pubblicarlo, poi rimettere la durata e pubblicare.

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
