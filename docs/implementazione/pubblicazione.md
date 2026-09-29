# Pubblicazione e rimando in bozza

Scheda di lavorazione. Scritta prima del codice come piano, e aggiornata a lavoro finito con
com'è andata davvero. È la seconda parte del flusso di pubblicazione deciso con ALBO-22: la
prima, il passaggio in verifica, è in [`passaggio-in-verifica.md`](passaggio-in-verifica.md).

**Stato: costruita.** Righe di collaudo A-93..A-110. Com'è andata davvero è in fondo, con le
differenze dal piano, i limiti dichiarati e la prova dei guasti.

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
2. **Il computo della fine. Deciso il 2026-09-24: il giorno della pubblicazione non si
   conta.** Con la durata di quindici giorni, un atto pubblicato il 1° ottobre ha fine il 16
   ottobre e resta visibile fino alla mezzanotte fra il 16 e il 17. È la regola generale per
   cui il giorno iniziale di un termine non si computa, e la lettura più prudente: i giorni
   interi di esposizione non sono mai meno della durata. Se il regolamento di
   un'amministrazione contasse il giorno della pubblicazione come primo, questa lettura
   terrebbe l'atto esposto un giorno in più; se un'amministrazione lo chiederà, il computo
   diventerà una scelta di configurazione.

## Com'è andata davvero

Il piano ha retto nella sostanza. Le differenze sono nove, e tutte stringono il piano invece
di allargarlo.

**Le prove stanno in due file, non in due per passaggio.** `PubblicazioneTest.php` ha le
righe A-93..A-104 e A-109..A-110, rimando in bozza compreso; `TuttoONienteTest.php` ha le
righe dei guasti forzati e della transazione, A-105..A-108. Il laboratorio comune, con gli
orologi fissati, i tipi con la durata e la fotografia intera dell'atto, sta in
`tests/flusso-di-prova.php`.

**La richiesta da codice ha un elenco chiuso di voci.** Il piano diceva che una data fornita
si ignora. Per la funzione pubblica si è andati oltre: `albo_pretorio_pubblica()` accetta
solo la conferma, la data di inizio e la data di fine, e una richiesta con qualunque altra
voce, per esempio un numero, un repertorio o un anno, è rifiutata per intero prima di
consumare un numero. Una voce scritta male non deve poter passare in silenzio. Dalla
schermata i campi costruiti a mano si ignorano, perché lì il modulo lo scrive il plugin.

**La conferma vale solo se è proprio "sì".** Da codice, un testo, un numero o qualunque valore
che non sia il vero è rifiutato; dalla schermata la casella porta il numero dell'atto a cui si
riferisce, e quella di un altro atto non vale.

**Il guardiano non si apre, concede.** Il piano diceva che il guardiano lascia passare lo
stato nuovo quando la richiesta arriva dai due passaggi. Si è fatto più stretto: `Passaggi`
concede **un atto, una scrittura e i campi che quella scrittura deve cambiare**, cioè lo stato
e, per la pubblicazione, le date. Tutto il resto della riga torna com'era, anche se un altro
componente lo cambia a metà strada, e la concessione cade appena usata. Un secondo atto in
verifica toccato nello stesso momento resta fermo (A-108). Il fermo che valeva per l'atto in
verifica vale ora anche per quello pubblicato, cestino e cancellazione compresi (A-103).

**La transazione si adatta a come la banca dati è già impostata.** Su un sito normale, dove
ogni scrittura si salva da sola, la pubblicazione apre e chiude una transazione vera. Dove la
scrittura automatica è già spenta, come nelle prove di WordPress, apre un punto di ripristino
dentro quella in corso. A-106 prova il primo caso sul serio, riaccendendo la scrittura
automatica e guardando l'atto da una **seconda connessione** mentre la transazione è aperta.

**Si controlla che le tabelle sappiano annullare, e quali.** Il controllo guarda la tabella dei
contenuti, quella dei loro dati e quella del registro del meccanismo comune. Il nome di
quest'ultima l'albo lo scrive per esteso, perché il meccanismo comune non lo espone: se un
giorno cambiasse, la tabella non si troverebbe e la pubblicazione si rifiuterebbe, che è il
lato sicuro. A-107 controlla che il nome esista davvero.

**Le voci automatiche del meccanismo comune si ricontano.** Il meccanismo comune scrive da sé
la voce del passaggio di stato, ma se non ci riesce lo annota e non ferma niente, per scelta
sua. Per l'albo una pubblicazione senza la sua voce è una pubblicazione a metà: i due passaggi
contano le voci automatiche prima e dopo, e se manca quella nuova annullano tutto.

**La durata mancante alla pubblicazione ha il suo nome.** L'elenco dei dati mancanti dice
"manca la durata"; alla pubblicazione il motivo diventa quello di ALBO-02, cioè che la data
di fine non si può calcolare, così chi lo legge sa che cosa non può esistere.

**La data della schermata conta come richiesta.** La casella della data che WordPress mostra
accanto al pulsante è un ingresso anche lei: se chi pubblica la cambia, vale come data di
inizio chiesta, e una data futura è rifiutata con il suo motivo (A-96). Una data passata si
ignora come le altre.

### Limiti dichiarati

- **La transazione copre la banca dati, non il resto.** Se un altro componente, agganciato
  alla pubblicazione, manda una mail o scrive un file, l'annullamento non lo disfa. Se apre o
  chiude una transazione sua, la banca dati chiude da sola quella dell'albo, e da qui non si
  impedisce.
- **Chi chiama la funzione pubblica dentro una transazione già aperta**, su un sito dove la
  scrittura automatica è accesa, se la vede chiusa: aprirne una nuova chiude la precedente.
  Chi scrive codice che chiama l'albo lo deve sapere.
- **Il meccanismo comune registra anche una "modifica" alla pubblicazione**, con le due date
  cambiate: le date si scrivono in modo esplicito, e il meccanismo comune annota ogni data
  scritta. Non è un errore, ma è una voce in più che chi legge il registro vedrà.
- **Il numero preso resta dell'atto anche a cavallo d'anno.** Se una pubblicazione fallisce a
  dicembre e riesce a gennaio, l'atto esce con il numero dell'anno prima. È la conseguenza
  della regola "un numero preso non si riusa mai", che il catalogo chiede.
- **Le metà di ALBO-27 con la durata propria restano aperte**, finché non c'è la durata propria
  (ALBO-04).
- **Il blocco dei motori di ricerca non serve ancora**, e con lui la pagina pubblica: vale il
  vincolo di rilascio del punto 8.

### Le prove

Centotrentadue prove nella suite principale, diciotto nuove, più le due suite separate senza
meccanismo comune e con meccanismo comune incompatibile, tutte verdi in locale su WordPress 6.5
con MariaDB. PHPCS pulito. In verifica continua, sul commit dbed178, verdi le stesse suite su
MySQL 8 con WordPress 6.5 e PHP 8.1 e con l'ultima WordPress e PHP 8.3, più lo standard di
codifica e la validazione di `publiccode.yml`: A-106, la transazione vera con la seconda
connessione, gira quindi anche su MySQL.

**La prova di non vacuità.** Quarantadue guasti introdotti uno per volta in una copia usa e
getta, facendo girare ogni volta la suite intera.

| Guasto introdotto | Prove cadute |
|---|---|
| Chiavi sconosciute accettate nella richiesta | A-102 |
| Permesso di pubblicare non controllato | A-93, A-104 |
| Conferma presa alla larga (qualunque valore) | A-95 |
| Conferma ignorata | A-95, A-104 |
| Data futura fornita accettata | A-96, A-104 |
| Data futura della bozza accettata | A-96 |
| Data fornita resa effettiva | A-97 |
| Fine fornita resa effettiva | A-99 |
| Giorno di inizio contato nella durata | A-93, A-95..A-101 |
| Fine calcolata sull'orologio vero | A-93, A-95..A-101 |
| Orologio fissato ignorato | A-96, A-98, A-102 |
| Dati non ricontrollati alla pubblicazione | A-101 |
| Fine non calcolabile senza il suo motivo | A-100 |
| Tabelle non controllate | A-107 |
| Tabella del registro fuori dal controllo | A-107 |
| Annullamento del punto di ripristino tolto | A-105 |
| Annullamento della transazione tolto | A-106 |
| Sempre punto di ripristino, mai transazione | A-106 |
| Memoria non svuotata dopo l'annullamento | A-105, A-106 |
| Stato non riletto dopo la scrittura | A-105 |
| Voce automatica non ricontata | A-105 |
| Voce della conferma non controllata | A-105, A-106 |
| Rimando senza motivo | A-94, A-104 |
| Rimando senza permesso | A-94 |
| Concessione non revocata | A-108 |
| Concessione valida per ogni atto | A-108 |
| Campi concessi non imposti | A-93, A-95..A-109 |
| Campi estranei liberi nel passaggio concesso | A-108 |
| Nome nell'indirizzo libero nel passaggio concesso | A-108 (al secondo giro) |
| Atto pubblicato non fermo | A-103 |
| Programmazione senza il suo motivo | A-96 |
| Cestino di un atto pubblicato permesso | A-90, A-103 |
| Conferma di un altro atto accettata | A-95 |
| Gettone del riquadro non controllato | A-109 |
| Gettone del riquadro non legato all'atto | A-109 |
| Data della schermata ignorata | A-96 |
| Riquadro registrato per chi redige | A-109 |
| Riquadro stampato senza permesso | A-109 |
| Un caso di rivelazione indiretta tolto | A-95 |
| Conferma già spuntata in partenza | A-95 |
| Registro non dato a chi pubblica | A-110 |
| Registro dato a chi redige | A-21, A-110 |

**Un guasto che al primo giro passava.** Lasciare libero il nome nell'indirizzo durante il
passaggio concesso non faceva cadere niente: nessuna prova dava all'atto un nome prima della
verifica, né provava a cambiarlo. A-108 ora lo fa. Guardando perché, è venuto fuori che
l'eccezione del guardiano per il nome era inutile: quando l'atto non ha un nome, WordPress lo
genera comunque **dopo** aver scritto la riga, con una scrittura sua che il guardiano non
vede. L'eccezione è stata tolta, e A-108 controlla anche che un atto senza nome lo riceva.
