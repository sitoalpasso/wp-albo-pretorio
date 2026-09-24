# Pubblicazione e rimando in bozza

Scheda di lavorazione. Scritta prima del codice come piano, e aggiornata a lavoro finito con
com'è andata davvero. È la seconda parte del flusso di pubblicazione deciso con ALBO-22: la
prima, il passaggio in verifica, è in [`passaggio-in-verifica.md`](passaggio-in-verifica.md).

**Stato: piano.** Il codice aspetta il registro delle modifiche del meccanismo comune
(interfaccia `1.4.0`), che questa unità usa per la motivazione del rimando in bozza. Le righe
di collaudo nuove si numerano quando il piano è confermato.

## In tre paragrafi

**Cosa costruiamo.** I due passaggi che partono da un atto in verifica, e solo quelli. Chi
pubblica può **rimandarlo in bozza**, scrivendo il motivo, che finisce nel registro delle
modifiche; l'atto torna modificabile. Oppure può **pubblicarlo**, dopo aver confermato il
controllo sui dati personali (ALBO-11). Alla pubblicazione il sistema scrive, tutti insieme,
la data di inizio (adesso, mai una data scelta a mano), la data di fine (inizio più la durata
del tipo vigente in quel momento) e il numero di repertorio. Se una sola di queste cose non
si può fare, l'atto resta in verifica e non cambia niente. Un atto pubblicato non si modifica
e non torna indietro, da nessuno. Defissione anticipata, annullamento e sostituzione per
oscuramento arrivano dopo.

**Come la costruiamo.** I due passaggi non passano dai pulsanti generici di WordPress, che
restano rifiutati come oggi: hanno un riquadro proprio nella schermata dell'atto, visibile
solo a chi può pubblicare e solo sull'atto in verifica, e una funzione pubblica per chi scrive
codice. Riquadro e funzione fanno la stessa cosa: controllano il permesso, aprono una
transazione della banca dati, compiono il passaggio, scrivono date e voce di registro, e
chiudono la transazione solo se tutto è riuscito. Il guardiano dei passaggi lascia passare lo
stato nuovo solo quando la richiesta arriva da lì. Il numero di repertorio si prende **prima**
della transazione, e resta dell'atto anche se la pubblicazione fallisce: il catalogo vuole
che un numero non si riusi mai, nemmeno dopo un'operazione fallita a metà.

**Come si prova che funziona, e come si prova che il test è vero.** Ogni passaggio si prova
riuscito e rifiutato, con una condizione diversa per volta, guardando il valore scritto nella
tabella e la fotografia intera dell'atto, registro e repertorio compresi. Per il "tutto o
niente" si fa fallire di proposito ciascun pezzo, uno per volta: la voce di registro, la data
di fine, il repertorio, la scrittura dello stato. Ogni volta si controlla che l'atto sia
ancora in verifica, identico a prima, e che il numero preso resti suo. Come sempre, a lavoro
finito si introducono guasti nel codice e si guarda quale prova li vede.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-passaggi.php` | i due passaggi, la transazione, l'ordine delle scritture, il permesso di chi li compie |
| `includes/class-riquadro-passaggi.php` | il riquadro "Pubblicazione" nella schermata: elenco dei casi di ALBO-11, conferma, motivo del rimando, due pulsanti, gettone legato all'atto |
| `includes/class-chiusura-pubblicazione.php` | i passaggi da "in verifica" nell'elenco chiuso, concessi solo a una richiesta dei due passaggi; il fermo esteso all'atto pubblicato |
| `includes/funzioni-api.php` | `albo_pretorio_pubblica()` e `albo_pretorio_rimanda_in_bozza()` |
| `albo-pretorio-pa.php` | interfaccia richiesta al meccanismo comune `1.4.0`; il permesso di leggere il registro dato ai ruoli dell'albo |
| `tests/PubblicazioneTest.php`, `tests/RimandoInBozzaTest.php` | le prove |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, `docs/dati.md` | righe nuove e stati |

### 2. Perché

ALBO-22, decisa il 2026-09-21, con le due righe che partono da "in verifica". ALBO-11 per la
conferma, ALBO-27 per le date, ALBO-08 per il repertorio, ALBO-02 per la data di fine che
deve essere calcolabile, ALBO-10 per la motivazione nel registro.

### 3. Il flusso per chi usa il sito

Chi pubblica apre un atto in verifica. Sotto ai dati, in sola lettura, c'è il riquadro
**Pubblicazione** con due strade.

- **Rimanda in bozza**: un campo per il motivo e un pulsante. Senza motivo l'avviso lo dice e
  l'atto resta in verifica. Con il motivo l'atto torna in bozza e chi redige lo ritrova
  modificabile; il motivo si legge nel registro delle modifiche.
- **Pubblica**: l'elenco dei casi tipici in cui un atto rivela dati delicati senza nominarli
  (i cinque di `requisiti.md`: collocamento mirato delle persone con disabilità, congedi per
  assistenza, graduatorie con punteggi sociali, provvedimenti disciplinari, redditi e ISEE),
  il richiamo al divieto assoluto di diffondere dati genetici, biometrici e sulla salute, e
  una conferma da spuntare. Senza conferma l'avviso lo dice e l'atto resta in verifica. Con
  la conferma l'atto è pubblicato, e il riquadro mostra numero, inizio e fine.

Chi possiede sia il permesso di redigere sia quello di pubblicare compie i due passaggi di
fila, come deciso: la verifica resta un passaggio, e la conferma sta nel secondo.

### 4. Dati letti e scritti

Si leggono lo stato memorizzato, l'elenco dei dati mancanti e la durata del tipo nell'istante
della pubblicazione. Si scrivono: lo stato; la data di inizio nei campi di WordPress
(`post_date` e `post_date_gmt`, dall'orologio del sistema); la data di fine con la funzione
del meccanismo comune, come **giorno civile di inizio più la durata**, nel fuso del sito; il
numero di repertorio con la funzione già costruita; nel registro, la voce del rimando con il
motivo, e per la pubblicazione la voce con la conferma. Le voci automatiche del meccanismo
comune (`cambio_stato`, `pubblicazione`, `modifica_fine_pubblicazione`) si scrivono da sole,
dentro la stessa transazione.

**Una data fornita da chi pubblica non diventa mai quella effettiva.** Una data di inizio nel
futuro è rifiutata con un motivo che la nomina, perché chiederla vuol dire chiedere una
programmazione (ALBO-27); una data passata e una data di fine qualunque si ignorano, e l'atto
si pubblica con quelle del sistema. Vale per quelle già scritte nella bozza e per quelle
mandate con la richiesta.

### 5. Permessi

Pubblicare e rimandare in bozza richiedono il permesso di pubblicare di quell'atto. Il
permesso di leggere il registro delle modifiche, che il meccanismo comune non dà a nessuno,
l'albo lo dà a chi pubblica, all'installazione e a ogni aggiornamento, come le altre
capability. Nessun permesso nuovo per l'albo.

### 6. Modi di guasto previsti

- Pubblicazione da chi può solo redigere; e il contrario, una persona con entrambi i permessi
  respinta. Due controlli positivi, come chiede la riga di ALBO-22.
- Pubblicazione senza conferma, o con la conferma data su un altro atto.
- **Un pezzo della pubblicazione che fallisce e lascia gli altri scritti**: l'atto pubblicato
  senza data di fine, o con la data e senza numero, o pubblicato senza la sua voce. Si
  provano uno per volta, forzando il guasto.
- **La transazione che non annulla niente.** Se le tabelle della banca dati non sono di un
  tipo che conosce le transazioni, annullare non disfa. Il piano è controllarlo prima di
  cominciare e, se non lo sono, rifiutare il passaggio con un avviso che lo spiega: meglio un
  albo che non pubblica di uno che pubblica a metà.
- **La memoria interna di WordPress che ricorda lo stato annullato.** Dopo un annullamento la
  banca dati è tornata indietro ma la copia in memoria dell'atto no: si svuota, e le prove
  rileggono dalla banca dati.
- Un atto in verifica con i dati diventati mancanti dopo il passaggio, per esempio la durata
  tolta al tipo: si ricontrolla l'elenco dei dati mancanti alla pubblicazione, e la data di
  fine non calcolabile ha il suo motivo (ALBO-02).
- Il numero di repertorio restituito a un altro atto dopo una pubblicazione fallita.
- Una data di inizio o di fine fornita a mano che diventa quella effettiva.
- Un atto pubblicato riportato indietro, modificato, cestinato o cancellato, da qualunque
  ingresso e da qualunque ruolo, amministratore compreso.
- Un aggancio di un altro componente, dentro la transazione, che ne apre un'altra o la
  chiude: la banca dati chiude da sola quella aperta. È un caso che da qui non si impedisce;
  si dichiara, e la prova di "tutto o niente" lo mostra.

### 7. Prove che si aggiungeranno

Le righe di catalogo già scritte in `collaudo.md` alla sezione "Requisito per requisito":
ALBO-22 (pubblicazione da chi può solo redigere, rimando senza e con motivazione, ritorno da
pubblicato, nessuno stato intermedio), ALBO-11, ALBO-27 (data futura, data passata, fine
fornita), ALBO-02, ALBO-01 alla pubblicazione, ALBO-08 alla pubblicazione. Diventeranno righe
A-93 e seguenti, più quelle del "tutto o niente" con i guasti forzati.

### 8. Cosa NON si implementa

Defissione anticipata, annullamento e dichiarazione dell'effetto sul periodo (ALBO-22, righe
successive). La sostituzione per oscuramento (ALBO-12). La durata propria dell'atto
(ALBO-04): la riga di ALBO-27 sull'atto con durata abbreviata resta aperta. La pagina
pubblica: un atto pubblicato **non si vede ancora dal pubblico**, perché il tipo resta non
consultabile e il meccanismo comune non consegna gli allegati di un tipo non consultabile.
Per questo il blocco dei motori di ricerca non serve a questa unità: serve alla pagina
pubblica. Ne discende un vincolo di rilascio: **nessuna versione si rilascia con la
pubblicazione aperta e la pagina pubblica assente**, perché la data di inizio farebbe correre
un termine di legge su un atto che nessuno vede.

### 9. Come si prova sul sito vero

Con un atto in verifica: rimandarlo in bozza senza motivo, poi con il motivo, e trovare il
motivo nel registro delle modifiche. Rimandarlo in verifica e pubblicarlo senza conferma, poi
con la conferma: leggere numero, inizio e fine nel riquadro, controllare che la fine sia il
giorno di inizio più la durata del tipo, e che l'atto non si modifichi più.

## Punti aperti, da decidere prima del codice

1. **Cosa chiede la conferma di ALBO-11. Deciso il 2026-09-24: la sola conferma.** Nessun
   campo in cui scrivere la norma che prescrive la pubblicazione: aggiungerebbe attrito al
   lavoro quotidiano, e della liceità di quello che si pubblica risponde chi pubblica, non il
   programma, come già deciso il 2026-09-23 per il regolamento sui dati sensibili.
2. **Il computo della fine.** La proposta è "giorno civile di inizio più la durata": con la
   durata di quindici giorni, un atto pubblicato il 1° ottobre ha fine il 16 ottobre e resta
   visibile fino alla mezzanotte del 16. Il giorno della pubblicazione non conta fra i
   quindici, quindi i giorni interi di esposizione non sono mai meno della durata. Se il
   regolamento di un'amministrazione contasse il giorno della pubblicazione come primo,
   questa lettura terrebbe l'atto esposto un giorno in più di quanto quel regolamento
   chiede. È il rischio minore fra i due: un giorno in meno invaliderebbe la pubblicazione,
   un giorno in più espone dati personali oltre il necessario. Va comunque scelto
   esplicitamente.
