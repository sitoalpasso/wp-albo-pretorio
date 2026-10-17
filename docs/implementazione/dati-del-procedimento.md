# Responsabile del procedimento, ufficio e richiedente

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-33, righe di collaudo
A-144..A-146 e A-165..A-172, tutte a fatto per la parte che non aspetta la pagina pubblica.
Com'è andata davvero è in fondo.

## In tre paragrafi

**Cosa costruiamo.** Chi consulta un atto trova utile sapere a chi rivolgersi. La legge
chiede di stabilire ufficio e responsabile di ogni procedimento e di comunicarli agli
interessati (l. 241/1990, artt. 4, 5 e 8), non di indicarli nella pubblicazione all'albo;
il richiedente serve soprattutto per gli atti che un altro ente chiede di pubblicare. Se
usarli lo sceglie l'amministrazione, dato per dato, nella schermata **Dati del
procedimento**: per ciascuno, "chiederlo e mostrarlo" oppure "non usarlo", con la norma e
gli effetti scritti accanto e nessuna scelta già segnata. Finché la scelta manca i tre dati
non si chiedono e non si mostrano, e l'albo pubblica come prima: la scelta non tocca niente
di ciò che la norma chiede alla pubblicazione. Nella stessa schermata chi pubblica tiene
l'elenco dei responsabili e quello degli uffici, aggiungendo e togliendo nomi; chi redige
sceglie da quegli elenchi, e scrive a mano il solo richiedente. I campi sono facoltativi.

**Come la costruiamo.** Scelte ed elenchi stanno in una sola impostazione del sito, scritta
tutta insieme, con chi l'ha salvata e quando; una forma diversa da quella del salvataggio
vale come scelta non fatta. Sull'atto i tre dati sono testo, non riferimenti alle voci
dell'elenco: un nome tolto dall'elenco smette di essere proposto ma resta sugli atti che lo
portano, e un atto portato da un albo precedente conserva i suoi dati qualunque sia la
scelta. La scelta decide soltanto che cosa si chiede e che cosa si mostra, e vale subito per
tutti gli atti. Alla pubblicazione i tre valori si copiano nella voce di registro della
conferma, così il referto (ALBO-07) li riporterà come erano quel giorno; dopo non si
cambiano, come gli altri dati dell'atto, per il blocco già esistente sui dati di un atto in
verifica o pubblicato.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove salvano la
scelta con invii veri della schermata, compilano la scheda con invii veri della schermata
dell'atto, pubblicano con il passaggio vero e rileggono dalla banca dati. Verificano i
campi assenti o presenti secondo la scelta, la voce vuota proposta, il rifiuto di un nome
fuori elenco, il nome tolto dall'elenco che resta sull'atto, il registro che non cambia con
gli elenchi, la lettura che tratta come assente un valore malformato e un'impostazione
scritta a mano in forma diversa. A lavoro finito si introducono guasti nel codice, uno per
volta, e si guarda quale prova li vede.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-dati-procedimento.php` | impostazione (scelte ed elenchi), lettura degli elenchi, lettura controllata dei tre dati sull'atto, i dati da mostrare e quelli per il registro |
| `includes/class-schermata-dati-procedimento.php` | la schermata: norma, due scelte per dato senza preselezione, i due elenchi, gettone di sicurezza, avviso in bacheca |
| `includes/class-scheda-atto.php` | i campi nel riquadro **Dati dell'atto** e il loro salvataggio; le righe nella sola lettura |
| `includes/class-dati-atto.php` | la dichiarazione dei tre dati a WordPress insieme agli altri |
| `includes/class-passaggi.php` | i tre valori nella voce di conferma della pubblicazione |
| `albo-pretorio-pa.php` | caricamento e agganci della schermata |
| `tests/DatiProcedimentoTest.php` | le prove |
| `tests/ambiente-albo.php` | azzeramento dell'impostazione, avviso escluso dalle osservazioni che non lo chiedono |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, `docs/dati.md` | righe e stati |

### 2. Perché

ALBO-33, deciso il 2026-10-08: lo sceglie l'amministrazione se mostrarli e chiederli sugli
atti nuovi; sugli atti migrati si conservano sempre. Vale la regola sulle impostazioni decisa
lo stesso giorno. Le sette scelte di dettaglio sono state chiuse il 2026-10-17 (vedi
"Punti decisi").

### 3. Il flusso per chi usa il sito

Chi pubblica apre **Albo pretorio, Dati del procedimento**, legge che cosa dice la norma e,
per ciascuno dei tre dati, sceglie. Se sceglie di usare il responsabile, scrive l'elenco
dei responsabili, un nome per riga; per togliere una persona cancella la sua riga. Salva.
Chi redige apre un atto in bozza e trova, sotto il numero proprio, i menu del responsabile
e dell'ufficio, con "Nessuno" segnato, e il campo del richiedente. Sceglie, salva. Pubblicato
l'atto, la scheda mostra i tre dati in sola lettura e il registro li conserva. Se in seguito
una persona esce dall'elenco, gli atti che la portano la conservano, e una bozza che la
portava la mostra segnata "non più nell'elenco".

### 4. Dati letti e scritti

- Opzione `albo_pretorio_dati_procedimento`: `scelte` (per dato, `usa` o `non_usa`),
  `responsabili`, `uffici`, `utente`, `istante`. Scritta solo dalla schermata o da
  `DatiProcedimento::salva()`, tutto insieme o niente.
- Metadati protetti dell'atto `_albo_pretorio_responsabile`, `_albo_pretorio_ufficio`,
  `_albo_pretorio_richiedente`, testo su una riga, al massimo 200 caratteri, senza
  esposizione per programmi.
- Voce di conferma della pubblicazione: dettagli `responsabile`, `ufficio`, `richiedente`,
  testo vuoto se mancano.

### 5. Permessi

La schermata e i due elenchi: chi possiede il permesso di pubblicare. I tre dati sull'atto:
chi può modificare l'atto, solo in bozza.

### 6. Modi di guasto previsti

- Impostazione scritta di lato in forma diversa: scelta non fatta, nessun default.
- Nome fuori elenco nell'invio della scheda, per esempio da un modulo rimaneggiato: non si
  salva, l'avviso lo dice, gli altri dati si salvano.
- Dato non in uso ma presente nell'invio: ignorato, il valore conservato resta.
- Valore malformato scritto di lato sull'atto, per esempio importato: letto come assente e
  mostrato vuoto, e l'invio vuoto della scheda che ne segue non lo cancella.

### 7. Prove che si aggiungeranno

A-144..A-146 e A-165..A-172, in `tests/DatiProcedimentoTest.php`.

### 8. Cosa NON si implementa

- La scheda pubblica dell'atto, che non esiste ancora: `DatiProcedimento::da_mostrare()` è
  ciò che userà, ed è provata qui.
- L'importazione da un albo precedente: qui si garantisce soltanto che i dati scritti
  sull'atto si conservino e si leggano qualunque sia la scelta.
- Il referto (ALBO-07): i valori sono già nel registro da cui si costruirà.

### 9. Come si prova su un'installazione reale

Aprire **Dati del procedimento** prima di salvare: nessuna scelta segnata, un avviso in
bacheca per chi pubblica. Scegliere "chiederlo e mostrarlo" per il responsabile, scrivere due
nomi, salvare. Aprire una bozza: il menu con "Nessuno" segnato e i due nomi. Togliere un nome
dall'elenco dopo averlo scelto sulla bozza: la bozza lo mostra "non più nell'elenco".
Pubblicare e controllare nel registro la voce di conferma.

## Punti decisi (2026-10-17)

1. Una scelta per ciascun dato, non una per tutti e tre.
2. Due scelte per dato: "chiederlo e mostrarlo" oppure "non usarlo".
3. Senza scelta l'albo non si ferma: i campi non compaiono e chi pubblica vede un avviso.
4. I campi sono facoltativi.
5. Responsabile e ufficio si scelgono da elenchi che chi pubblica tiene, aggiungendo e
   togliendo nomi; il richiedente si scrive.
6. Alla pubblicazione i tre dati si fissano e si copiano nella voce di registro.
7. Se la scelta cambia, i dati restano e si mostrano secondo la scelta di adesso, atti
   importati compresi.

## Com'è andata davvero

- I tre dati sull'atto sono testo e non voci di un elenco di WordPress: così togliere un nome
  dall'elenco non tocca nessun atto, e il blocco esistente sui dati di un atto in verifica o
  pubblicato vale anche per questi senza codice nuovo.
- Il dettaglio del registro non ammette valori annidati: i tre valori stanno accanto agli
  altri dettagli della conferma, con il loro nome.
- Negli elenchi gli spazi ripetuti dentro un nome diventano uno, e le righe vuote non
  contano; ogni altra differenza (segni di formattazione, caratteri di controllo, nome
  ripetuto anche con maiuscole diverse, più di 200 caratteri) rifiuta l'intero salvataggio
  con il numero della riga, invece di salvare un nome diverso da quello scritto.

**Primo giro di revisione** (quattro rilievi, tutti accolti):

1. Il valore salvato dalla scheda perdeva le barre rovesciate, perché `update_post_meta` le
   toglie una seconda volta: "Settore A\B" diventava "Settore AB", fuori elenco. Ora il valore
   si passa con le barre rimesse; le prove inviano le richieste con le barre aggiunte, come
   WordPress, e A-172 lo prova su tutti e tre i dati e sui valori conservati.
2. Negli elenchi la pulizia dei lati toglieva anche tabulazioni e altri caratteri di
   controllo, che passavano invece di essere rifiutati. Ora si tolgono ai lati i soli spazi.
3. La scelta inviata passava da una ripulitura prima del confronto, e `USA` o `u!sa`
   diventavano una scelta ammessa. Ora si confronta il testo così come arriva.
4. A-166 non vedeva un salvataggio a metà, perché le parti valide coincidevano con quelle
   già salvate. Ora un caso ha parti valide nuove e una scelta cambiata prima dell'errore.

Guasti introdotti di proposito, uno per volta, e prova che li ha visti:

| Guasto | Prova caduta |
|---|---|
| ogni dato sempre in uso | A-144, A-165, A-167 e altre |
| nome fuori elenco accettato dalla scheda | A-168 |
| nome tolto dall'elenco non conservato al salvataggio della bozza | A-168 |
| tre valori assenti dalla voce di conferma | A-169 |
| una scelta segnata in partenza | A-146 |
| nomi ripetuti accettati | A-166 |
| impostazione con dato in uso ed elenco vuoto letta come valida | A-167 |
| elenco salvato non ricontrollato in lettura | A-167 |
| scelta sconosciuta letta come valida | A-167 |
| avviso mostrato anche dopo la scelta | A-165 |
| valore sull'atto letto senza controllo | A-171 |
| invio vuoto che cancella un valore conservato malformato | A-171 |
| valore salvato senza rimettere le barre rovesciate | A-172 |
| pulizia dei lati che toglie anche i caratteri di controllo | A-166 |
| scelta ripulita prima del confronto | A-166 |
| elenco dei responsabili scritto prima del controllo degli uffici | A-166 |
