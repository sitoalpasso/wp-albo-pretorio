# Niente misure anti copia che rompano l'accessibilità

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Requisito ALBO-14. Righe di collaudo A-337..A-338.

## In tre paragrafi

**Cosa costruiamo.** Alcuni albi provano a impedire che gli atti si copino. Bloccano la
selezione del testo, disattivano il tasto destro del mouse, mettono l'atto come immagine al
posto del testo, oppure servono il documento dentro un visore che non lo lascia scaricare.
Sono tutte misure che un lettore di schermo non attraversa: chi non vede non legge l'atto.
L'albo non fa niente di tutto questo, e oggi è già così. Il lavoro è dunque un'altra cosa:
scrivere le prove che tengono questa regola vera anche domani, quando qualcuno, con buone
intenzioni, potrebbe aggiungere una di quelle misure.

**Come la costruiamo.** Niente codice nuovo nel componente. Le prove sono di due tipi. Il
primo apre come un visitatore l'elenco e la scheda di un atto pubblicato e controlla tre cose.
La pagina non porta comandi contro la copia, la selezione, il tasto destro o il trascinamento.
L'oggetto e i dati dell'atto sono testo vero e non immagini. Il documento si raggiunge con un
collegamento semplice. Poi scarica il documento e controlla che arrivi identico a quello
caricato e mostrabile dal browser: non trasformato, non chiuso in una pagina che ne impedisca
la lettura. Il secondo tipo legge il testo dei sorgenti e dei fogli di stile del componente,
e cerca le stesse misure: vale anche per le pagine che la prova non apre, come lo storico.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove passano già sul
codice di oggi, perché descrivono una cosa che il componente già rispetta. Per mostrare che
sono vere si introducono a mano, uno alla volta, i guasti che vogliono impedire: la selezione
bloccata nel foglio di stile, il tasto destro disattivato nella scheda, il documento servito
in una pagina chiusa. Per ciascuno si guarda quale prova se ne accorge. La tabella dei guasti
è in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `tests/PaginaPubblicaTest.php`: la prova sulle pagine e sul documento consegnato (A-337).
- `tests/StaticoTest.php`: la prova sui sorgenti e sui fogli di stile (A-338).
- `docs/collaudo.md`, `docs/requisiti.md`: le due righe e lo stato del requisito.

### 2. Requisito servito

ALBO-14: nessuna misura anti copia che renda l'atto illeggibile a chi usa un lettore di
schermo.

### 3. Il percorso di chi usa il sito

Chi visita l'albo apre l'elenco e la scheda, seleziona e copia il testo come in qualunque
pagina, e apre il documento nel browser o lo scarica. Chi usa un lettore di schermo legge le
stesse cose, perché sono testo.

### 4. Dati letti e scritti

Nessuno nuovo.

### 5. Permessi coinvolti

Nessuno nuovo.

### 6. Modi di guasto previsti

- Uno stile o un comando contro la selezione o la copia aggiunto al componente: lo vedono la
  prova sulle pagine, se tocca l'elenco o la scheda, e la prova sui sorgenti in ogni caso.
- Il documento servito trasformato, o chiuso in una pagina che ne impedisce la lettura: lo
  vede la prova sul documento consegnato.

### 7. Test aggiunti

A-337 e A-338.

### 8. Cosa non implementa

- **Il tema e le altre estensioni del sito.** Un tema può aggiungere da fuori un blocco della
  selezione su tutte le pagine. Il componente risponde di quello che produce, come per
  ALBO-28.
- **I documenti dell'ente.** Un PDF cifrato con i permessi che vietano la copia ha la misura
  dentro il file, e il file è dell'ente. ALBO-13 lo dice a chi lo carica, con il motivo "il
  documento è cifrato", e non lo blocca.

### 9. Come si prova su un'installazione reale

Sulla copia di prova, con il tema vero: aprire la scheda di un atto, selezionare e copiare
l'oggetto, aprire il menu con il tasto destro, aprire il documento nel browser. Poi lo stesso
con un lettore di schermo, per esempio NVDA.

## Com'è andata

Come da piano, senza codice nuovo nel componente. Le prove girano in locale, perché le azioni
automatiche di GitHub sono ferme. La suite intera conta 432 prove, verdi salvo A-193, che in
questo ambiente fallisce anche su `main`. Sono verdi anche le due suite senza il meccanismo
comune e con il meccanismo incompatibile.

Che il documento non sia servito dentro una pagina chiusa lo garantisce il meccanismo comune,
che lo prova da sé. A-337 lo riprova dal lato dell'albo, così che un cambio nel meccanismo
comune si veda anche qui.

| Guasto introdotto | Visto da |
|---|---|
| Selezione bloccata nel foglio di stile pubblico | A-338 |
| Tasto destro disattivato nella scheda | A-337, A-338 |
| Copia bloccata nello storico pubblico | A-338 |
| Un'immagine nella scheda | A-337 |
| Il documento senza collegamento semplice | A-337 |
| Il documento servito in una pagina chiusa, nel meccanismo comune | A-337 |
