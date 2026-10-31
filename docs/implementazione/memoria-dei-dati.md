# Memoria dei dati dopo un oscuramento

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Requisito ALBO-12, con ALBO-30 sul nome del file. Righe di collaudo
A-326..A-327.

## In tre paragrafi

**Cosa costruiamo.** Dopo la sostituzione di un documento con la sua versione oscurata, a
pubblicazione attiva, il pubblico deve trovare la versione oscurata. Le memorie di pagina sono
già escluse (A-305), e lo scaricamento autorizza leggendo dalla banca dati (A-301). Restava una
copia in memoria: la scheda pubblica, il conteggio dei documenti nell'elenco e il nome del file
scaricato leggevano quali sono i documenti dell'atto dalla memoria dei dati di WordPress. Con
una memoria persistente dei dati (per esempio un servizio di memoria condivisa installato sul
server), una richiesta che ha letto l'atto prima che la sostituzione si chiudesse può rimettere
in memoria i dati di prima dopo lo svuotamento. Da quel momento la scheda porterebbe alla
versione uscente, che non si scarica più, e non a quella oscurata: l'atto pubblicato
sembrerebbe senza documento, finché la memoria non si svuota da sé.

**Come la costruiamo.** Una sola lettura dei documenti dell'atto dalla banca dati, con il posto
di ciascuno (principale o allegato e il suo numero), validata come quella dalla memoria. La
usano la scheda, il conteggio dell'elenco e il nome del file. La lettura che autorizza lo
scaricamento diventa la stessa. Se la lettura non risponde, la scheda dice che i documenti non
sono disponibili in questo momento e l'elenco non scrive un conteggio: nessuno ripiega sulla
memoria.

**Come si prova che funziona, e come si prova che il test è vero.** La memoria persistente si
simula: a ogni richiesta, prima che la pagina o la consegna leggano, la prova rimette in
memoria i dati dell'atto di prima della sostituzione, e controlla che la memoria indichi
davvero l'uscente. Poi apre scheda ed elenco e scarica i documenti come visitatore. Una
seconda prova fa mancare la lettura dalla banca dati. I guasti introdotti nel codice per vedere
quale prova li vede sono in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-documenti-atto.php`: la lettura `posti()`, su cui si appoggia `indicati()`.
- `includes/class-vista-pubblica.php`: scheda e conteggio dell'elenco.
- `includes/class-nome-scaricato.php`: il posto del documento per il nome.
- `tests/PaginaPubblicaTest.php`; `docs/collaudo.md`, `docs/architettura.md`.

### 2. Requisito servito

ALBO-12: dopo la sostituzione la versione pubblica è quella oscurata. ALBO-30: il nome del file
scaricato resta quello dell'albo.

### 3. Il percorso di chi usa il sito

Nessuno nuovo. Il visitatore vede la scheda con la versione oscurata; nel caso raro in cui la
banca dati non risponda, legge che i documenti non sono disponibili in questo momento.

### 4. Dati letti e scritti

Letti, dalla banca dati: i due dati dell'atto che indicano principale e allegati, e per
ciascun documento le condizioni di validità di sempre. Scritti: niente.

### 5. Permessi coinvolti

Nessuno.

### 6. Modi di guasto previsti

- La lettura non risponde: la pagina lo dice, il nome del file scaricato diventa quello
  neutro del meccanismo comune.

### 7. Test aggiunti

A-326 e A-327, in `tests/PaginaPubblicaTest.php`.

### 8. Cosa non implementa

- **Le schermate di amministrazione** (riquadro dei documenti, consultazione degli scaduti,
  copie dello storico) leggono ancora dalla memoria: le apre chi lavora sull'atto, e lo
  svuotamento della memoria a fine passaggio le aggiorna alla richiesta seguente.
- **La validità di ciascun documento** si legge come prima: la sostituzione non cambia la riga
  dei documenti, solo i dati dell'atto che li indicano.

### 9. Come si prova su un'installazione reale

Sulla copia di prova con una memoria persistente attiva: oscurare il principale di un atto
pubblicato, aprire la scheda da una finestra anonima e verificare che il collegamento scarichi
la versione oscurata.

## Com'è andata

Come da piano. Una lettura in più per ogni atto nell'elenco: le righe per pagina sono poche, e
la lettura è sui dati di un atto solo.

| Guasto | Visto da |
|---|---|
| Scheda dalla memoria | A-326, A-327 |
| Conteggio dalla memoria | A-327 (in A-326 la sostituzione non cambia il numero dei documenti) |
| Nome del file dalla memoria | A-326 |
| La lettura che non risponde ripiega sulla memoria | A-327 |
| Il principale conta anche fra gli allegati | A-326 |
| Un allegato ripetuto conta due volte | A-326 |

Le prove girano in locale: le azioni automatiche di GitHub sono ferme. Suite intera verde salvo
A-193, che in questo ambiente fallisce per la conversione dei caratteri anche su `main`; verdi
anche le due suite senza il meccanismo comune e con il meccanismo incompatibile.
