# Atti di un'altra amministrazione ospitati sull'albo

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-29, righe di collaudo
A-249..A-256 e A-260. Com'è andata davvero è in fondo.

## In tre paragrafi

**Cosa costruiamo.** La legge consente a un ente di pubblicare i suoi atti sull'albo online
di un'altra amministrazione (l. 69/2009, art. 32, c. 3), per esempio un'unione o un
consorzio. Chi risponde dei dati lo stabiliscono le amministrazioni fra loro; il componente
fa una cosa sola: ogni atto ospitato porta il nome dell'amministrazione che l'ha adottato, e
chi consulta l'albo lo vede nell'elenco, nella scheda e nei risultati della ricerca. Chi
pubblica tiene l'elenco delle amministrazioni ospitate nella schermata **Amministrazioni
ospitate**. Finché l'elenco è vuoto non cambia niente; appena ha una voce, ogni atto chiede
"di chi è?", fra "Questa amministrazione" e le voci dell'elenco, senza nessuna risposta già
segnata, e non va in verifica finché non si sceglie.

**Come la costruiamo.** L'elenco sta in una sola impostazione del sito, con chi l'ha salvata
e quando. Ogni voce ha un numero che non cambia e non si riusa, così la si può rinominare
senza che gli atti in preparazione cambino voce, e una voce nuova non prende mai il numero di
una tolta. Sull'atto si scrive il numero della voce, oppure zero per "questa
amministrazione". Il nome si fissa alla pubblicazione nella voce di conferma del registro,
che è solo in aggiunta: rinominare o togliere la voce dopo non cambia l'autore di un atto
pubblicato, e non c'è una seconda copia sull'atto da difendere. Nessun ruolo e nessun
permesso nuovo: l'atto ospitato lo pubblica e lo toglie chi pubblica tutti gli altri.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove salvano
l'elenco con invii veri della schermata, compilano l'atto con invii veri della schermata
dell'atto, pubblicano con il passaggio vero e leggono la pagina pubblica con richieste vere.
Verificano la domanda assente a elenco vuoto e presente con una voce, la scelta non
preselezionata, il blocco senza scelta, il nome in elenco, scheda, ricerca e consultazione
riservata, il nome della pubblicazione dopo una rinomina, la voce tolta che ferma bozza e
pubblicazione, i valori scritti di lato e il registro che non risponde. A lavoro finito si
introducono guasti nel codice, uno per volta, e si guarda quale prova li vede.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-amministrazioni-ospitate.php` | impostazione e voci, salvataggio tutto o niente, scelta sull'atto, dati mancanti, dettagli per il registro, nome da mostrare |
| `includes/class-schermata-amministrazioni-ospitate.php` | la schermata: norma, le due situazioni con i loro effetti, l'elenco con rinomina e "Togli", le voci nuove, gettone di sicurezza |
| `includes/class-scheda-atto.php` | la scelta nel riquadro **Dati dell'atto** e il suo salvataggio; la riga nella sola lettura |
| `includes/class-dati-atto.php` | la dichiarazione del dato e la mancanza nell'elenco dei dati mancanti |
| `includes/class-passaggi.php` | il nome e il numero della voce nella conferma della pubblicazione, fissati prima del numero di registro |
| `includes/class-vista-pubblica.php` | "Atto di: …" nella riga dell'elenco e la riga nella scheda |
| `includes/class-schermata-scaduti.php` | la riga nella consultazione riservata |
| `albo-pretorio-pa.php` | caricamento e agganci della schermata |
| `tests/AmministrazioniOspitateTest.php` | le prove |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/dati.md`, `docs/architettura.md` | righe e stati |

### 2. Perché

ALBO-29, deciso il 2026-09-23: l'indicazione dell'amministrazione che ha adottato l'atto,
mostrata al pubblico; scelta obbligatoria e senza preselezione appena l'elenco ha una voce;
nessun accesso separato riservato all'amministrazione ospitata.

### 3. Il flusso per chi usa il sito

Chi pubblica apre **Albo pretorio, Amministrazioni ospitate**, legge che cosa dice la norma e
che cosa cambia con l'elenco vuoto e con l'elenco pieno, scrive il nome dell'ente nelle voci
nuove e salva. Chi redige apre un atto in bozza e trova, sotto il numero proprio, il menu
"Amministrazione che ha adottato l'atto" con "Da scegliere" in testa; sceglie e manda in
verifica. Chi pubblica pubblica come sempre. Il cittadino trova "Atto di: Unione …" sotto
l'oggetto nell'elenco e nella ricerca, e la riga "Amministrazione che ha adottato l'atto"
nella scheda. Per correggere il nome di una voce lo si riscrive nella schermata: gli atti già
pubblicati conservano il nome della loro pubblicazione.

### 4. Dati letti e scritti

- Opzione `albo_pretorio_amministrazioni_ospitate`: `voci` (elenco di numero e nome),
  `prossimo` (il numero che avrà la prossima voce), `utente`, `istante`. Scritta solo dalla
  schermata o da `AmministrazioniOspitate::salva()`, tutto insieme o niente.
- Metadato protetto dell'atto `_albo_pretorio_amministrazione`: `0` oppure il numero della
  voce, senza esposizione per programmi. Bloccato, come gli altri dati, dall'atto in verifica
  in poi. Verifica e pubblicazione lo leggono dalla banca dati, non dalla memoria degli
  oggetti.
- Voce di conferma della pubblicazione: dettagli `amministrazione` (il nome, testo vuoto per
  un atto di questa amministrazione) e `amministrazione_voce` (il numero, zero per questa
  amministrazione).

### 5. Permessi

La schermata e l'elenco: chi possiede il permesso di pubblicare, come per i dati del
procedimento. La scelta sull'atto: chi può modificare l'atto, solo in bozza. Nessun permesso
e nessun ruolo nuovo.

### 6. Modi di guasto previsti

- **Voce tolta dopo la scelta**, anche svuotando l'elenco: la bozza e l'atto in verifica che
  la indicano non vanno avanti, e il riquadro ripropone la domanda con il motivo, anche a
  elenco vuoto. Pubblicarli come atti di questa amministrazione cambierebbe l'autore indicato.
- **Voce aggiunta mentre un atto è in verifica senza scelta** (mandato quando l'elenco era
  vuoto): la pubblicazione si ferma, e chi pubblica lo rimanda in bozza perché si scelga.
- **Voce tolta da un'altra richiesta durante la pubblicazione**: la pubblicazione rifà il
  controllo sulla stessa lettura dell'elenco da cui prende il nome, prima di prendere il
  numero di registro; non scrive un atto ospitato come atto di questa amministrazione.
- **Elenco scritto di lato in forma diversa**: non vale come elenco vuoto. La domanda resta,
  con la sola risposta "Questa amministrazione"; gli altri atti aspettano che l'elenco sia
  ripristinato, e la schermata rifiuta di salvarci sopra, perché un elenco ricominciato
  darebbe a un'altra amministrazione il numero di una voce già scelta.
- **Valore scritto di lato sull'atto** (zeri davanti, spazi, due righe, numero enorme): non
  è una scelta assente ma una scelta storta. L'atto si ferma anche a elenco vuoto e la domanda
  resta finché non si sceglie di nuovo; una scelta vera sostituisce tutte le righe.
- **"Da scegliere" inviato così com'è** su un atto la cui scelta non vale più: il menu la
  mostra come "Da scegliere" e il browser manda il campo vuoto. Il vuoto non toglie mai una
  scelta già scritta: una scelta che non vale più resta, e con lei il blocco; una che vale
  resta anche lei, perché fra il controllo e la cancellazione un'altra richiesta potrebbe
  togliere la voce e lasciare l'atto senza scelta a elenco vuoto. Si cambia solo con una
  scelta esplicita, "Questa amministrazione" compresa.
- **Scelta cambiata mentre un'altra richiesta toglie la voce e manda l'atto in verifica**: la
  scelta nuova non si scrive cancellando e riscrivendo, che lascerebbe un istante senza
  scelta. Le righe che ci sono si riscrivono tutte con il valore nuovo in una sola istruzione,
  poi si tolgono quelle in più; la scelta uguale non si tocca. Tutto sta in una transazione:
  se qualcosa non riesce a metà la banca dati torna indietro su tutto, e la scheda dice che è
  rimasta la scelta di prima solo se la banca dati la mostra davvero; altrimenti dice di
  ricontrollare la scelta, che è comunque quella di prima o quella chiesta. Una prima lettura
  che fallisce non scrive niente: una lettura fallita risponde come un elenco vuoto, e si
  riconosce dall'errore della connessione.
- **Lettura dell'elenco che fallisce**: l'elenco si legge dalla banca dati a ogni controllo,
  senza la memoria delle opzioni di WordPress, che risponde con lo stesso valore predefinito
  per "non c'è" e per "la lettura è fallita". Una lettura fallita vale come elenco
  illeggibile: la domanda resta e la schermata non salva.
- **Voce di conferma che non si legge**, per un atto ospitato pubblicato: la pagina dice "non
  leggibile in questo momento" invece di tacere, perché tacere farebbe sembrare l'atto di
  questa amministrazione.
- **Elenco cancellato o sostituito da fuori**: il prossimo numero viene anche dopo ogni numero
  che un atto indica e dopo ogni numero segnato nel registro, così una voce nuova non prende
  mai il numero già dato a un'altra amministrazione, nemmeno se nessun atto l'ha ancora
  scelto; un modulo aperto prima con quel numero non sceglie la voce nuova. Ogni numero si
  segna nel registro con una chiave unica, e la banca dati rifiuta un numero già segnato.
  Un registro che non si legge ferma il salvataggio.
- **Salvataggio rifiutato a metà** (scrittura che non si conferma, numero già segnato,
  lettura di conferma che fallisce): voci del registro ed elenco stanno in una transazione,
  e il rifiuto torna indietro su tutto; l'elenco resta quello di prima.
- **Tabelle che non annullano**: tornare indietro disfa qualcosa solo se la tabella delle
  opzioni e quella del registro conoscono le transazioni. Se una delle due non le conosce, o
  il loro tipo non si legge, il salvataggio si rifiuta prima di scrivere.
- **Transazione di un altro**: il salvataggio non comincia dentro la transazione di un altro
  componente, né durante un passaggio di stato dell'albo, e un passaggio di stato non
  comincia durante il salvataggio. Aprire la propria transazione dentro quella dell'altro la
  confermerebbe a metà; un punto di ripristino lascerebbe il blocco prima della conferma
  vera, e un secondo salvataggio cancellerebbe le modifiche del primo. La banca dati non dice
  sempre se una transazione è aperta: i passaggi di stato la segnano da sé. La pubblicazione
  rifiutata per questo motivo non consuma numeri.

  **Limite dichiarato.** Con la scrittura automatica accesa, una transazione aperta a mano da
  un altro componente si riconosce solo dove la banca dati espone `@@in_transaction`
  (MariaDB). Dove non la espone (MySQL) non c'è un modo affidabile e senza permessi speciali
  per saperlo, e il salvataggio parte: la sua transazione chiuderebbe quella dell'altro. È lo
  stesso limite, con lo stesso controllo, dei passaggi di stato e dell'importazione; serve un
  componente che apra una transazione sulla connessione di WordPress e, senza chiuderla,
  chiami il salvataggio dell'elenco. Rifiutare quando non si sa fermerebbe il salvataggio su
  quasi ogni sito con MySQL.
- **Due persone che salvano l'elenco insieme**: i salvataggi vanno in fila, dalla lettura
  alla scrittura confermata, con un blocco della banca dati; senza, due salvataggi
  sovrapposti darebbero lo stesso numero a due amministrazioni diverse. Dentro la fila, una
  voce aggiunta dall'altra e assente dall'invio resta; si tolgono solo le voci indicate; il
  nome inviato per una voce che l'altra ha tolto rifiuta il salvataggio.

### 7. Prove aggiunte

A-249..A-256 e A-260, in `tests/AmministrazioniOspitateTest.php`; la prova dei salvataggi
sovrapposti lancia processi veri con `tests/concorrenza/ospitate.php`.

### 8. Cosa NON si implementa

- **Accessi separati riservati all'amministrazione ospitata**: non esistono, e si aggiungono se qualcuno li
  chiede (decisione del 2026-09-23).
- **L'esportazione** (ALBO-17) non esiste ancora: quando arriva porta anche questo dato.
- **L'importazione** degli atti di un'altra amministrazione da Albo Pretorio On line
  (`Ente` diverso da zero) resta un dubbio del rapporto: gli atti importati non portano la
  scelta e risultano di questa amministrazione. Il backup del plugin precedente conosce un
  ente per riga, e la corrispondenza con le voci dell'elenco non è automatica.
- **La colonna nell'elenco degli atti dell'amministrazione**: il nome si legge nel riquadro
  dell'atto.
- **La ricerca per amministrazione** nella pagina pubblica: si cerca per oggetto, numero,
  tipo, organo e date, come prima.

### 9. Come si prova su un'installazione reale

Aprire **Amministrazioni ospitate**: elenco vuoto, nessun avviso in bacheca. Aprire una
bozza: nessuna domanda nuova. Aggiungere una voce e salvare. Riaprire la bozza: il menu con
"Da scegliere" segnato; mandarla in verifica senza scegliere, e leggere il motivo. Scegliere
la voce, mandare in verifica e pubblicare. Sulla pagina dell'albo: "Atto di: …" nell'elenco,
la riga nella scheda. Rinominare la voce: la scheda dell'atto pubblicato non cambia.

## Com'è andata davvero

- Il nome non si copia sull'atto ma nella voce di conferma del registro, come il motivo
  dell'annullamento: il registro è solo in aggiunta, e una seconda copia sull'atto sarebbe un
  dato in più da difendere. Sull'atto resta il numero della voce, che dice se l'atto è
  ospitato senza leggere il registro.
- Rileggendo i passaggi di stato prima della revisione è venuto fuori un buco: il nome si
  leggeva dentro la transazione, dopo il controllo dei dati mancanti fatto prima del numero,
  e una voce tolta da un'altra richiesta fra i due avrebbe fatto uscire l'atto ospitato come
  atto di questa amministrazione. Ora la pubblicazione rifà il controllo e prende il nome
  sulla stessa lettura, prima del numero, e scrive quel valore.
- La prova A-202 sulla costante delle memorie di pagina contava di girare prima di ogni
  altra richiesta della pagina dell'albo. Le prove nuove, che leggono la pagina, vengono
  prima in ordine di file: ora A-202 gira in un processo a parte, come già A-205.

- Il primo giro di revisione indipendente ha trovato cinque buchi, tutti corretti con una
  prova che prima non c'era. "Da scegliere" inviato così com'è cancellava una scelta non più
  valida e sbloccava l'atto come atto di questa amministrazione; una scelta storta valeva
  come assente, e a elenco vuoto passava; i salvataggi dell'elenco non erano in fila; la
  pubblicazione leggeva l'elenco due volte, e il commento diceva il contrario; una lettura
  dell'elenco fallita valeva come elenco mai salvato. Ora l'elenco si legge una volta per
  controllo, fresco dalla banca dati, e la scelta storta si distingue da quella assente.

- Il secondo giro ne ha trovati altri due, più rari. Il blocco della banca dati vale per la
  connessione, e un salvataggio chiamato da un aggancio del primo lo riprendeva senza
  attendere: ora un salvataggio annidato nella stessa richiesta si rifiuta. E un elenco
  cancellato da fuori faceva ripartire i numeri da uno, così una voce nuova poteva prendere il
  numero che un atto aveva già scelto per un'altra amministrazione: ora il prossimo numero
  viene anche dopo ogni numero che un atto indica. Il giro ha chiesto anche di legare alcune
  verifiche alla riga dell'atto giusto, invece di cercarle in tutta la pagina.

- Il terzo giro ha visto che il pavimento sugli atti non basta: un numero dato a una voce e
  non ancora scelto da nessun atto, con l'elenco cancellato, si poteva ridare, e un modulo
  aperto prima l'avrebbe assegnato alla voce nuova. Ora ogni numero dato si segna nel
  registro, che è solo in aggiunta, con una chiave unica per numero: il prossimo numero viene
  dopo il più alto segnato, e la banca dati rifiuta un numero già segnato. Un registro che
  non si legge ferma il salvataggio. E un salvataggio rifiutato dopo la scrittura lasciava
  l'elenco cambiato: ora voci del registro ed elenco si scrivono in una transazione, e un
  rifiuto torna indietro su tutto.

- Il quarto giro ha trovato tre punti, tutti sulle transazioni. Tornare indietro non disfa
  niente su tabelle che non conoscono le transazioni: ora il salvataggio controlla prima il
  tipo della tabella delle opzioni e di quella del registro. Un salvataggio chiamato durante
  una pubblicazione, o il contrario, confermava a metà l'altra operazione: ora l'uno rifiuta
  l'altro, in tutte e due le direzioni. E dentro la transazione di un altro componente il
  blocco si lasciava prima della conferma vera: ora il salvataggio lì non comincia. Prova
  nuova A-260.

- Il quinto giro ha chiesto di provare la lettura unica dell'elenco attraverso la
  pubblicazione vera, non solo nella funzione che la fa: A-254 ora toglie la voce dopo
  ciascuna delle letture dell'elenco fatte dalla pubblicazione, una per volta. Ha segnalato
  anche la transazione aperta a mano da un altro componente su MySQL: è il limite dichiarato
  sopra, comune a tutto l'albo.

- Il sesto giro ha trovato che "Da scegliere" toglieva ancora una scelta valida, e fra il
  controllo e la cancellazione un'altra richiesta poteva togliere la voce: l'atto restava
  senza scelta a elenco vuoto, quindi di questa amministrazione. Ora "Da scegliere" non
  toglie mai niente. E la prova attraverso la pubblicazione vera non toglieva la voce dopo
  l'ultima lettura: ora la toglie alla prima scrittura dell'atto, e verifica ogni volta che
  sia stata tolta davvero.

- Il settimo giro ha trovato la stessa cosa sul cambio di scelta: cancellare e poi riscrivere
  lasciava un istante senza scelta, e un invio in verifica in quell'istante, con l'ultima voce
  appena tolta, passava come atto di questa amministrazione. Ora la scelta si riscrive sopra
  quella che c'è, senza toglierla prima, e l'esito si rilegge dalla banca dati.

- L'ottavo giro ha visto che, se la scrittura falliva dopo la riscrittura (righe in più che non
  si tolgono, rilettura che non riesce), la scheda diceva "resta la scelta di prima" senza
  rimetterla. Ora le righe di prima si rimettono e si controlla che ci siano davvero.

- Il nono giro ha mostrato che rimettere le righe a mano apre altri casi: una prima lettura
  fallita valeva come nessuna scelta, e il ripristino cancellava la scelta valida. Ora la
  sostituzione sta in una transazione, come il salvataggio dell'elenco, e ogni lettura
  controlla l'errore della connessione.

- La rilettura prima del decimo giro ha trovato che verifica e pubblicazione leggevano la
  scelta dalla memoria degli oggetti, mentre l'elenco si leggeva gia' dalla banca dati. Con una
  memoria persistente un'altra richiesta poteva rimettervi la scelta di prima nell'istante fra
  la pulizia e la conferma della sostituzione, e la pubblicazione avrebbe fissato
  l'amministrazione vecchia, o pubblicato come atto di questa amministrazione un atto la cui
  voce era stata tolta. Ora le due decisioni leggono la scelta dalla banca dati
  (`scelta_fresca()`), una lettura fallita vale come scelta storta, e la memoria si pulisce di
  nuovo dopo la conferma. La scheda e le pagine continuano a leggere dalla memoria: mostrano,
  non decidono.

- Il decimo giro ha mostrato che anche mostrare decide: con la memoria vecchia l'atto ospitato
  pubblicato appariva di questa amministrazione, e il riquadro poteva segnare "Questa
  amministrazione", che un invio così com'è avrebbe scritto davvero. Ora la scelta si legge
  dalla banca dati per ogni uso (`scelta_letta()`, che ha preso il posto di `scelta_fresca()`):
  una lettura in più per atto nell'elenco pubblico, il prezzo di non mostrare mai un autore
  sbagliato. Lo stesso giro ha trovato che la sostituzione della scelta non controllava che la
  tabella delle scelte annullasse: con MyISAM il ritorno indietro non ripristina niente. Ora,
  come per i passaggi e l'elenco, si controlla il tipo della tabella prima di scrivere e si
  rifiuta anche quando il tipo non si legge.

- L'undicesimo giro ha trovato che la sostituzione sceglieva il punto di ripristino anche
  quando la lettura della scrittura automatica falliva, e un punto di ripristino fuori da
  una transazione non trattiene niente. Ora, come per i passaggi e l'elenco, si decide solo
  su una risposta certa (`modo_scelta()`): scrittura automatica spenta, punto di ripristino;
  accesa, transazione, o punto di ripristino se un'altra transazione è aperta; qualunque
  lettura fallita, nessuna scrittura.

Guasti introdotti di proposito, uno per volta, e prova che li ha visti:

| Guasto | Prova caduta |
|---|---|
| nome mai fissato nel registro | A-250, A-251, A-254, A-255 |
| nome letto dall'elenco di adesso anche per l'atto pubblicato | A-251, A-254 |
| scelta mai pretesa | A-250, A-253, A-254 |
| voce tolta che non ferma l'atto | A-253 |
| "Questa amministrazione" segnata in partenza | A-250 |
| domanda posta solo con l'elenco pieno | A-253, A-254 |
| nome per una voce tolta da altri accettato | A-252 |
| elenco che non si legge trattato come vuoto | A-254 |
| voce di conferma illeggibile taciuta | A-254 |
| nomi ripetuti accettati | A-252 |
| numero di una voce tolta riusato | A-252 |
| voce fuori elenco accettata dalla scheda | A-254 |
| nome assente dall'elenco pubblico | A-250, A-255 |
| nome assente dalla consultazione riservata | A-250 |
| "Da scegliere" che cancella una scelta non più valida | A-253, A-254 |
| scelta storta trattata come assente | A-254 |
| scelta nuova scritta sopra due righe storte senza toglierle | A-254 |
| nome di un atto pubblicato con scelta storta taciuto | A-254 |
| lettura fallita dell'elenco trattata come elenco mai salvato | A-254 |
| nome preso da una seconda lettura dell'elenco | A-254 |
| salvataggi dell'elenco senza fila | A-256 |
| salvataggio annidato nella stessa richiesta accettato | A-253 |
| numeri che ripartono da uno con l'elenco cancellato | A-253 |
| nome nella riga sbagliata della consultazione riservata | A-250 |
| numero dato e mai scelto ridato con l'elenco cancellato | A-253 |
| numero dato non segnato nel registro | A-253 |
| numero gia' segnato non rifiutato dalla chiave unica | A-253 |
| registro illeggibile trattato come nessun numero dato | A-253 |
| salvataggio rifiutato che lascia l'elenco cambiato | A-253 |
| salvataggio dentro la transazione di un altro componente accettato | A-260 |
| salvataggio durante una pubblicazione accettato | A-260 |
| pubblicazione o rimando durante un salvataggio accettati | A-260 |
| pubblicazione rifiutata durante un salvataggio che consuma il numero | A-260 |
| transazione dei passaggi mai segnata, o segnata e mai tolta | A-249..A-255, A-260 |
| tipo delle tabelle mai controllato, o senza la tabella delle opzioni | A-260 |
| pubblicazione che rilegge il nome dopo il controllo | A-254 |
| "Da scegliere" che toglie una scelta che vale | A-253 |
| scelta cambiata cancellando e riscrivendo | A-253 |
| esito della scrittura della scelta non controllato | A-253 |
| righe in più lasciate dopo una scelta vera | A-253 |
| nessun ritorno indietro dopo una scrittura a metà | A-253 |
| ritorno indietro dichiarato senza controllarlo | A-253 |
| lettura fallita delle righe trattata come nessuna scelta | A-253 |
| scelta per la pubblicazione letta dalla memoria | A-254 |
| scelta per la verifica letta dalla memoria | A-254 |
| lettura fallita della scelta fresca trattata come assente | A-254 |
| memoria non pulita dopo la conferma della scelta | A-253 |
| scelta mostrata e segnata nel riquadro letta dalla memoria | A-254 |
| sostituzione della scelta senza controllo della tabella | A-253 |
| tipo della tabella che non si legge trattato come transazionale | A-253 |
| punto di ripristino scelto con la scrittura automatica illeggibile | A-253 |
| punto di ripristino scelto con la transazione in corso illeggibile | A-253 |
