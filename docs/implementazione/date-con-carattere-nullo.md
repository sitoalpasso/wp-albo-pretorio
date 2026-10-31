# Date fornite con un carattere nullo

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Requisiti ALBO-27 e ALBO-31. Righe di collaudo A-329 e A-330.

## In tre paragrafi

**Cosa costruiamo.** Il carattere nullo è un carattere invisibile che non compare mai in una
data scritta a mano, ma può arrivare da un programma. Da PHP 8 le funzioni che leggono le date
non lo accettano: invece di dire "non è una data" sollevano un'eccezione, e la richiesta si
interrompe con un errore della pagina invece del rifiuto previsto. Succedeva in tre punti:
la data di inizio passata alla pubblicazione da codice; i giorni passati alla proroga da
codice; il primo e l'ultimo giorno della schermata della proroga, quando il carattere nullo
sta in mezzo (la ripulitura del campo toglie solo quelli alle estremità). In tutti e tre i
casi lo poteva fare solo chi ha il permesso di pubblicare, e non cambiava nessun dato: la
richiesta si fermava prima di scrivere. Ma il rifiuto deve essere quello previsto, con il suo
messaggio.

**Come la costruiamo.** Prima di chiamare le funzioni delle date, il testo deve avere la forma
esatta di una data (quattro cifre, trattino, due cifre, trattino, due cifre, e per la
pubblicazione eventualmente l'ora): ogni altro carattere, nullo compreso, porta al rifiuto di
sempre. È lo stesso controllo che già fanno i dati dell'atto, l'importazione e la pagina
pubblica. Nella proroga da codice il giorno si ripuliva dagli spazi alle estremità, e la
ripulitura toglieva anche i caratteri nulli: un giorno con un carattere nullo in fondo passava
come valido. Ora un giorno con un carattere nullo, ovunque sia, non è valido; e il messaggio di
rifiuto non lo riporta.

**Come si prova che funziona, e come si prova che il test è vero.** A-329 pubblica da codice
con sei date che contengono un carattere nullo in posti diversi: sul codice di prima la
pubblicazione si interrompe con l'eccezione. A-330 fa lo stesso con la proroga, da codice e
dalla schermata: sul codice di prima un giorno con il carattere nullo in fondo veniva accettato
e prorogava l'atto, e uno con il carattere nullo in mezzo interrompeva la schermata. Tutte e due
le prove finiscono con il controllo positivo: la stessa data senza il carattere nullo passa. I
guasti introdotti nel codice per vedere quale prova li vede sono in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-passaggi.php`: `data_locale()`.
- `includes/class-proroga.php`: il controllo dei giorni.
- `includes/class-schermata-proroga.php`: `giorni()`.
- `tests/PubblicazioneTest.php`, `tests/ProrogaTest.php`; `docs/collaudo.md`.

### 2. Requisito servito

ALBO-27: una data di inizio fornita non vale mai, e se non è una data si rifiuta con il suo
messaggio. ALBO-31: la proroga attesta solo giorni validi.

### 3. Il percorso di chi usa il sito

Nessuno nuovo. Chi pubblica, se un programma gli passa una data sporca, legge il rifiuto di
sempre invece di una pagina di errore.

### 4. Dati letti e scritti

Nessuno. Il controllo avviene prima di qualunque lettura o scrittura dell'atto.

### 5. Permessi coinvolti

Nessuno nuovo. I tre ingressi restano riservati a chi pubblica.

### 6. Modi di guasto previsti

- Un testo che non ha la forma di una data: rifiuto con il motivo della data non valida.

### 7. Test aggiunti

A-329 in `tests/PubblicazioneTest.php`, A-330 in `tests/ProrogaTest.php`.

### 8. Cosa non implementa

- **Le altre letture di date** sono state riguardate una per una. I dati dell'atto,
  l'importazione (che passa dagli stessi dati dell'atto), la pagina pubblica, il registro dei
  festivi e i dati del procedimento controllano già la forma prima di leggere. Il riquadro della
  pubblicazione compone la data da numeri. Le altre letture (schermate degli scaduti, delle
  copie, dell'importazione e dell'esportazione, storico pubblico, nome del file scaricato)
  leggono date di atti già usciti dalla verifica: scritte dal sistema, o controllate quando
  sono entrate e poi protette dal guardiano. Non è testo fornito in quel momento.
- **Il carattere nullo alle estremità nella schermata della proroga** lo toglie la ripulitura
  di WordPress del campo, prima che il plugin lo veda: lì il giorno resta valido. È la
  ripulitura di tutti i campi delle schermate, e non si cambia.

### 9. Come si prova su un'installazione reale

Non serve: dalla schermata un carattere nullo non si digita. La prova automatica copre i
programmi che chiamano le funzioni.

## Com'è andata

Come da piano, salvo una scoperta: la proroga da codice accettava come valido un giorno con un
carattere nullo in fondo, perché la ripulitura degli spazi lo toglieva. Ora lo rifiuta, come
la pubblicazione.

| Guasto | Visto da |
|---|---|
| Nessun controllo della forma nella pubblicazione | A-329 |
| La forma della pubblicazione senza l'ora | A-96, A-97 |
| Nessun controllo del carattere nullo nella proroga da codice | A-330 |
| Il messaggio della proroga riporta il carattere nullo | A-330 |
| Nessun controllo della forma nella schermata della proroga | A-330 |
| Nessun controllo della forma dell'ultimo giorno nella schermata | A-330 |

Le prove girano in locale: le azioni automatiche di GitHub sono ferme. Suite intera: 424 prove,
verdi salvo A-193, che in questo ambiente fallisce per la conversione dei caratteri anche su
`main`; verdi anche le due suite senza il meccanismo comune e con il meccanismo incompatibile.
