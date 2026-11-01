# Lucchetti su una banca dati che ne tiene uno solo

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Correttezza dei meccanismi che servono ALBO-08, ALBO-31, ALBO-35, ALBO-36 e
ALBO-29. Righe di collaudo A-340..A-342.

## In tre paragrafi

**Cosa costruiamo.** Alcune operazioni dell'albo non devono sovrapporsi: due importazioni
insieme, due proroghe insieme, due numerazioni dello stesso anno. Per metterle in fila l'albo
usa i lucchetti con nome della banca dati: chi arriva per secondo aspetta, o rinuncia. Le
banche dati recenti permettono a una connessione di tenere più lucchetti insieme. Quelle più
vecchie, che WordPress ammette ancora, no: una connessione ne tiene uno solo, e prenderne un
secondo lascia il primo senza avviso. L'importazione prende il suo turno e poi, per ogni atto,
la numerazione dell'anno: su una banca dati vecchia, in quel momento il turno dell'importazione
si apre, e un'altra richiesta può entrare mentre la prima lavora ancora. Lo abbiamo visto
provando l'albo su MariaDB 5.5: cinque prove rosse, e il turno che si apre in una prova a
parte.

**Come la costruiamo.** Un solo punto del componente prende e lascia i lucchetti. Legge una
volta, per ogni connessione, la versione della banca dati. Dove i lucchetti si tengono insieme,
non cambia niente. Dove se ne tiene uno solo, tutti i lucchetti dell'albo diventano lo stesso
lucchetto: lo si chiede alla banca dati alla prima presa, le prese annidate si contano nel
componente senza chiedere niente, e il lucchetto torna libero quando si lascia l'ultima. Le
operazioni dell'albo vanno allora una alla volta anche quando non si toccherebbero: è più
lento, non meno sicuro. Una versione che non si riconosce vale come banca dati vecchia, perché
il lucchetto unico è corretto anche dove se ne potrebbero tenere di più. Il turno delle
proroghe, che sa recuperare una presa rimasta da una proroga precedente, impara a non
scambiare per presa rimasta il lucchetto di un'altra operazione in corso.

**Come si prova che funziona, e come si prova che il test è vero.** Una banca dati vecchia
qui non c'è, quindi le prove la imitano: riscrivono ogni presa di lucchetto in modo che lasci
prima tutte le altre, tolgono `RELEASE_ALL_LOCKS` e rispondono alla domanda sulla versione come
MariaDB 5.5. Con l'imitazione accesa, la prova sul turno dell'importazione è rossa sul codice
di prima e verde dopo. Si fa girare con l'imitazione anche tutta la suite. Poi la CI prova
l'albo su una MariaDB 5.5 vera, che è la verifica che conta. I guasti introdotti a mano sono
nella tabella in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-lucchetti.php`: il punto unico per i lucchetti.
- `includes/class-importazione.php`, `class-repertorio.php`, `class-passaggi.php`,
  `class-storico.php`, `class-copie-storico.php`, `class-amministrazioni-ospitate.php`: le
  prese e i rilasci passano da lì.
- `tests/lucchetto-unico.php`: l'imitazione della banca dati vecchia.
- `tests/LucchettiTest.php`: A-340..A-342.
- Le prove che usavano `RELEASE_ALL_LOCKS` o i nomi dei lucchetti: rilascio che vale su ogni
  banca dati, e nomi chiesti al componente.
- `.github/workflows/ci.yml`: la suite anche su MariaDB 5.5. `composer.json`: la suite intera
  con l'imitazione, `composer test:lucchetto-unico`.
- `README.md`: la banca dati fra i requisiti tecnici.

### 2. Requisito servito

Nessun requisito nuovo. È la correttezza dei meccanismi che tengono in piedi requisiti già
fatti: la numerazione unica (ALBO-08), la proroga una alla volta (ALBO-31), l'importazione una
alla volta (ALBO-35), le copie dello storico (ALBO-36), l'elenco delle amministrazioni
ospitate (ALBO-29).

### 3. Il percorso di chi usa il sito

Nessun cambiamento per chi lavora sull'albo. Su una banca dati vecchia, chi avvia una
proroga mentre un'importazione lavora aspetta qualche secondo e poi trova il messaggio che
un'altra operazione è in corso: lo stesso di oggi per due proroghe insieme.

### 4. Dati letti e scritti

Nessun dato nuovo. Una domanda in più alla banca dati per connessione, la sua versione, solo
quando serve un lucchetto.

### 5. Permessi coinvolti

Nessuno.

### 6. Modi di guasto previsti

- Un lucchetto preso dentro un altro che apre il primo: lo vede A-340.
- Una presa annidata che lascia il lucchetto a chi l'ha preso prima: A-340, A-341.
- Il turno delle proroghe che scambia il lucchetto di un'altra operazione per una sua presa
  rimasta, e lo lascia: A-341.
- La numerazione presa dentro una transazione aperta da altri, che deve restare fino alla fine
  della connessione, lasciata da un'operazione successiva della stessa richiesta: A-341.
- Una versione letta male, o una versione sconosciuta presa per recente: A-342.

### 7. Test aggiunti

A-340, A-341, A-342. Adattate le cinque rosse su MariaDB 5.5: A-202 e A-205 attraverso la
chiusura comune delle prove, A-152, A-213 e A-268. Poi quelle che usavano
`RELEASE_ALL_LOCKS` o i nomi dei lucchetti e su 5.5 passavano per caso, o che la suite con
l'imitazione ha fatto cadere: A-263, A-214, A-270, la chiusura delle prove dell'importazione
vera e la preparazione comune delle prove.

### 8. Cosa non implementa

- **I lucchetti degli altri componenti.** Su una banca dati vecchia, un altro plugin che tiene
  un lucchetto con nome lo perde quando l'albo ne prende uno, e viceversa. Il componente
  comune oggi non ne usa.
- **Una connessione che cade.** Se la banca dati chiude la connessione a metà, i lucchetti si
  perdono su ogni versione: è il comportamento di prima.

### 9. Come si prova su un'installazione reale

Sulla copia di prova, con la sua banca dati: avviare l'importazione e, mentre lavora, avviarla
di nuovo da un'altra scheda del browser. La seconda deve dire che un'altra richiesta sta
lavorando all'importazione. Lo script del sito che prova i componenti su MariaDB 5.5 rifà
tutta la suite.

## Com'è andata

Come da piano. Le prove girano in locale su MariaDB 10.11. La suite intera conta 436 prove, in
modo normale e con l'imitazione accesa: verdi salvo A-193, che in questo ambiente fallisce
anche su `main`. Sono verdi anche le due suite senza il meccanismo comune e con il meccanismo
incompatibile. La prova su una MariaDB 5.5 vera la fa la CI, nella terza riga della matrice.

Facendo girare tutta la suite con l'imitazione sono venute fuori due cose che riguardano solo
le prove.

- **Le prove girano dentro una transazione che non si conferma mai.** Lì la numerazione
  dell'anno resta presa fino alla fine della connessione, come deve. Con i lucchetti distinti
  questo non disturbava le altre operazioni; con il lucchetto unico le chiude tutte alle altre
  connessioni della prova. Le prove che dopo la preparazione usano una seconda connessione
  considerano quindi confermato quello che è stato scritto prima, e lasciano i lucchetti, come
  faceva già A-213.
- **Un filtro di A-270 si richiamava da solo.** Il filtro spegne lo storico quando vede la
  presa del lucchetto della copia, e lo spegnimento prende a sua volta un lucchetto: con il
  lucchetto unico è lo stesso nome, e il filtro si richiamava senza fine. Ora segna di essere
  già passato prima di spegnere.

In A-152 e A-213 l'atteso dipende dal tipo di banca dati, e la prova lo dice. In A-152 la
seconda presa dello stesso turno si somma alla prima solo dove la banca dati conta le prese:
altrove un rilascio libera il turno, e il componente lo dice libero. In A-213, con il lucchetto
unico, l'importazione non comincia nemmeno mentre un'altra richiesta tiene la numerazione,
invece di rinunciare sul solo atto di quell'anno.

Un'osservazione sull'ambiente, che non c'entra con questo lavoro: A-194 lascia nella cartella
dei backup un file e un collegamento, e A-188 fallisce se la suite gira una seconda volta
senza svuotarla. In CI la cartella parte vuota.

| Guasto introdotto | Visto da |
|---|---|
| Il componente come prima: lucchetti distinti, prese non contate | A-340, A-341, A-342 |
| Una presa annidata chiesta alla banca dati | A-340, A-341 |
| Il rilascio arriva alla banca dati a ogni presa lasciata | A-340 |
| La presa trattenuta dimenticata | A-341 |
| MariaDB letta con la soglia di MySQL | A-342 |
| Una versione sconosciuta presa per recente | A-342 |
| Il turno delle proroghe che lascia, come presa rimasta, il lucchetto di un'altra operazione | A-341 |
| Il turno delle proroghe annidato che lascia il lucchetto alla banca dati | A-341 |
| La numerazione non trattenuta dentro una transazione aperta da altri | A-341 |
