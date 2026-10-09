# Pubblicazione e rimando in bozza

Scheda di lavorazione. Scritta prima del codice come piano, e aggiornata a lavoro finito con
com'è andata davvero. È la seconda parte del flusso di pubblicazione deciso con ALBO-22: la
prima, il passaggio in verifica, è in [`passaggio-in-verifica.md`](passaggio-in-verifica.md).

**Stato: costruita.** Righe di collaudo A-93..A-121. Com'è andata davvero è in fondo, con le
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
| `albo-pretorio-pa.php` | interfaccia richiesta al meccanismo comune `1.4.0`, portata a `1.5.0` al secondo giro; il permesso di leggere il registro dato ai ruoli dell'albo |
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
3. **Il numero quando chi chiama ha già aperto una transazione. Deciso il 2026-10-08: si
   tiene il comportamento di oggi**, cioè la scelta consigliata qui sotto. Di solito la
   pubblicazione prende il numero e poi apre la sua transazione: se qualcosa va storto
   dopo, la pubblicazione si annulla ma il numero resta preso, e nessun altro atto lo
   riceverà mai. C'è però un caso raro: un altro programma, installato sul sito, apre lui
   una transazione e dentro chiama la pubblicazione dell'albo. Lì il numero si prende
   dentro la transazione di quel programma. Se poi quel programma annulla tutto,
   spariscono insieme la pubblicazione e il numero: l'atto torna in verifica, nessuno lo ha
   visto pubblicato con quel numero, e il numero va al prossimo atto pubblicato. Oggi il
   plugin fa così, e A-115 lo prova. Le alternative sono due. La prima è prendere il numero
   con una seconda connessione alla banca dati, tutta sua: il numero resterebbe preso anche
   se quel programma annulla, ma servirebbe aprire una connessione in più a ogni
   pubblicazione, e due connessioni che toccano le stesse tabelle possono aspettarsi a
   vicenda e bloccarsi. La seconda è rifiutare la pubblicazione quando trova una
   transazione già aperta: semplice, ma le prove di WordPress girano proprio così, e con
   loro qualche programma di importazione, quindi l'albo smetterebbe di funzionare in quei
   casi. La scelta consigliata è tenere il comportamento di oggi: il numero sparisce solo
   insieme alla pubblicazione, mai da solo, quindi non esiste un atto pubblicato che
   condivida il numero con un altro. Il rischio che resta è che quel programma abbia già
   mandato fuori il numero, per esempio in una mail, prima di annullare: è il limite
   dichiarato "la transazione copre la banca dati, non il resto".

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
connessione, gira quindi anche su MySQL. Lo stesso esito sul commit e05d094, dopo le
correzioni venute dai guasti. Le righe A-93..A-110 sono passate a "fatto" dopo quell'esito,
e con loro le righe del catalogo "Requisito per requisito" che questa unità chiude: le due
di ALBO-01 alla pubblicazione, quella di ALBO-02, le due di ALBO-08 sulla concorrenza e sulla
richiesta manipolata, quella di ALBO-11, quelle di ALBO-22 su permesso di pubblicare, rimando,
ritorno da pubblicato e stati intermedi, e le prime due di ALBO-27. Due righe di ALBO-22
erano già chiuse da A-85 e A-91 del passaggio in verifica e non erano state segnate:
creazione e risalvataggio della bozza, e verifica saltata. ALBO-01, ALBO-02 e ALBO-11 passano
a "fatto".

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

## Il secondo giro: la revisione indipendente

Una revisione indipendente del codice, fatta sul commit dc305e9, ha trovato nove difetti, uno
critico e sei gravi. Due hanno una causa comune che valeva per quasi tutti: **il guardiano
proteggeva soltanto la porta principale di WordPress**, e **il permesso dato alla
pubblicazione non era legato a quella sola scrittura**. Si sono corretti alla radice, non uno
per uno.

**1. Le funzioni di WordPress che non passano dalla porta principale.** `wp_publish_post()`,
che usa anche la pubblicazione programmata, e `set_post_type()` scrivono la riga da sé: un
altro programma poteva pubblicare un atto in verifica senza conferma, numero né data di fine,
o toglierlo dal tipo dell'albo. Ora sotto WordPress c'è una **barriera**: guarda ogni
istruzione che sta per scrivere la riga di un contenuto e, se è un atto e la scrittura non è
passata dal guardiano, la ferma quando l'atto è in verifica, pubblicato o in mezzo a un
passaggio, o quando la scrittura lo porterebbe fuori dalla bozza. Riconosce la forma delle
istruzioni che WordPress stesso costruisce. A-111.

**2. I dati collegati.** Il guardiano fermava la riga, ma i dati dell'atto (data di adozione,
documento principale, data di fine) si potevano cambiare con le funzioni apposite di
WordPress, e le voci degli elenchi con le loro. Ora su un atto in verifica o pubblicato le
funzioni dei dati sono rifiutate, anche quelle che indicano la riga per numero e quella che
toglie un dato a tutti i contenuti; assegnare o togliere una voce ferma la richiesta. Passano
solo i dati di servizio di WordPress, come il blocco della schermata aperta. A-101 prepara
ora i suoi atti incompleti scavalcando anche la barriera, dichiaratamente. A-112.

**3. Il permesso della pubblicazione.** Il permesso era un metodo pubblico che chiunque
poteva chiamare, e restava valido per tutta la durata della chiamata: una scrittura annidata
dello stesso atto poteva usarlo. Ora non c'è più nessun modo pubblico di ottenerlo. Il
passaggio crea un **gettone** casuale, lo mette dentro la sua scrittura, e il guardiano lo
consuma alla prima domanda: una seconda scrittura, anche dello stesso atto e anche con il
gettone copiato, lo trova già usato. Una scrittura che porta con sé dati o voci non lo ottiene
comunque. A-108.

**4. Le barre rovesciate.** Rimettendo nella riga i valori verificati, il guardiano li
passava a WordPress senza le barre di protezione che WordPress toglie subito dopo: un oggetto
con una barra rovesciata ne perdeva una a ogni passaggio. Corretto, e A-114 lo prova con
barre, apici e virgolette.

**5. Il numero dentro la transazione di chi chiama.** È una scelta, non un errore di codice:
vedi il punto aperto 3, deciso il 2026-10-08: si tiene. A-115 prova il comportamento di oggi.

**6. L'atto durante il passaggio.** Nel rimando, appena l'atto è in bozza, un aggancio di un
altro programma poteva cambiarlo, e nessuno controllava alla fine. Ora l'atto è **fermo per
tutto il passaggio**, qualunque sia il suo stato in quel momento, e prima di chiudere la
transazione riga, dati, voci e documenti si rileggono dalla banca dati e si confrontano con la
fotografia di partenza: se è cambiato qualcosa che il passaggio non doveva cambiare, si annulla
tutto. Dentro un passaggio le scritture annidate si trattano in due modi. Quelle che si possono
rifiutare prima che comincino, dalla porta principale o sui dati, si rifiutano e il passaggio
prosegue con l'atto com'era: un programma che "sistema" i contenuti negli agganci non impedisce
la pubblicazione. Quelle che non si possono fermare senza effetti a valle, la riga scritta
dritta o una voce, fanno annullare il passaggio per intero, con il suo motivo. A-113.

**7. La voce della data di fine.** Il meccanismo comune scrive nel registro anche la modifica
della data di fine, e se non ci riesce lo annota senza fermarsi. Ora la pubblicazione controlla
anche quella voce: se la data cambia ce ne deve essere almeno una in più, se era già giusta
nessuna. A-105 la guasta.

**8. Le prove che non avrebbero visto i difetti.** A-108 non provava una scrittura annidata
dello stesso atto; A-105 non guastava la voce della data di fine. Aggiunte.

**9. Il cambio dell'ora.** A-98 non attraversava nessun cambio dell'ora, e contava i giorni
dividendo i secondi per quelli di un giorno, che in un giorno di 23 o 25 ore sbaglia. Ora
attraversa l'ultima domenica di ottobre e quella di marzo, e conta giorni di calendario.

### Limiti dichiarati, aggiornati

- **Resta fuori un'istruzione scritta a mano** da un altro programma in una forma diversa da
  quelle che WordPress costruisce: la barriera non la riconosce. Chi scrive nella banca dati
  a mano scavalca qualunque plugin.
- **Il limite del passaggio in verifica sulle voci e i dati è chiuso**: ora sono protetti
  dalle funzioni di WordPress.
- **Fuori da un passaggio, una scrittura della riga o delle voci rifiutata ferma la
  richiesta** con un avviso, perché la funzione che l'ha mandata proseguirebbe annunciando un
  passaggio che non è avvenuto. Per chi usa la schermata non cambia niente: la schermata passa
  dalla porta principale, che rifiuta con il suo motivo senza fermare nulla.
- Restano i limiti del primo giro: la transazione copre la banca dati e non il resto, la voce
  di modifica in più alla pubblicazione, il numero a cavallo d'anno, ALBO-27 con la durata
  propria, il vincolo di rilascio della pagina pubblica.

### Le prove, al secondo giro

Centotrentasette prove nella suite principale, cinque righe nuove (A-111..A-115, in
`BarrieraTest.php`) e tre righe allargate (A-98, A-105, A-108), più le due suite separate:
tutte verdi in locale, PHPCS pulito. Le prove girano ora sul componente comune alla punta del
suo ramo principale (4446961, interfaccia `1.5.0`, con registro, blocco dei motori di ricerca e
criterio unico per la chiave della fine), e l'albo richiede la `1.5.0` all'avvio. In verifica
continua, sui commit f2426e9 ed ebfa82e, verdi le due combinazioni di WordPress e PHP su
MySQL, lo standard di codifica e la validazione di `publiccode.yml`. Le righe A-111..A-115 sono
passate a "fatto" dopo quell'esito.

**La prova di non vacuità, rifatta.** Sessantasette guasti, uno per volta, in una copia usa e
getta, facendo girare ogni volta la suite intera: i trentanove del primo giro ancora
applicabili (tre non lo erano più, perché la concessione e l'eccezione del nome non esistono
più) e ventotto nuovi, sulla barriera, sui dati, sul gettone e sul controllo finale. Al primo
passaggio ne sopravvivevano dodici: otto indicavano prove mancanti, e le prove sono state
allargate (A-108 con le tre scritture annidate, A-111 con lo stato riscritto nell'istruzione e
la pubblicazione interrotta, A-113 con la fine riscritta, il dato e la voce scritti a mano e il
conteggio delle scritture del rimando). Uno faceva girare una prova senza fine, perché
l'aggancio di prova si richiamava da sé: ora gira una volta sola. Restano quattro guasti che
non fanno cadere niente, e il motivo è scritto accanto: ciascuno toglie una difesa che ne ha
un'altra davanti o dietro, e il comportamento visibile resta quello giusto.

| Guasto introdotto | Prove cadute |
|---|---|
| Chiavi sconosciute accettate | A-102 |
| Permesso di pubblicare non controllato | A-93, A-104 |
| Conferma presa alla larga | A-95 |
| Conferma ignorata | A-95, A-104 |
| Data futura fornita accettata | A-96, A-104 |
| Data futura della bozza accettata | A-96 |
| Giorno di inizio contato | A-93, A-95, A-96, A-97, A-98, A-99, A-100, A-101 |
| Orologio fissato ignorato | A-96, A-98, A-102 |
| Dati non ricontrollati | A-101 |
| Fine non calcolabile senza il suo motivo | A-100 |
| Tabelle non controllate | A-107 |
| Registro fuori dal controllo | A-107 |
| Annullamento del punto tolto | A-105, A-113 |
| Annullamento della transazione tolto | A-106 |
| Sempre punto di ripristino | A-106 |
| Memoria non svuotata dopo l'annullamento | A-105, A-106 |
| Stato non riletto | nessuna: lo stato e le date li riconfronta comunque il controllo finale prima di chiudere, che annulla con il suo motivo |
| Voce automatica non controllata | A-105 |
| Voce dell'albo non controllata | A-105, A-106 |
| Rimando senza motivo | A-94, A-104 |
| Rimando senza permesso | A-94 |
| Campi concessi non imposti | A-93, A-95, A-96, A-97, A-98, A-99, A-100, A-101, A-102, A-103, A-104, A-105, A-106, A-107, A-108, A-109, A-111, A-112, A-113, A-114, A-115 |
| Campi estranei liberi nel passaggio concesso | A-108 |
| Pubblicato non fermo | A-103, A-111, A-112 |
| Programmazione senza il suo motivo | A-96 |
| Cestino di un pubblicato permesso | nessuna: il cestino passa da una scrittura dello stato e da un dato, e li fermano la porta principale e i filtri dei dati |
| Conferma di un altro atto accettata | A-95 |
| Gettone del riquadro non controllato | A-109 |
| Gettone del riquadro non legato all'atto | A-109 |
| Data della schermata ignorata | A-96 |
| Riquadro registrato per chi redige | A-109 |
| Riquadro stampato senza permesso | A-109 |
| Un caso di rivelazione indiretta tolto | A-95 |
| Conferma già spuntata | A-95 |
| Registro non dato a chi pubblica | A-110 |
| Registro dato a chi redige | A-21, A-110 |
| Barriera sotto WordPress tolta | A-111, A-113 |
| Barriera: atto portato fuori dalla bozza | A-111 |
| Barriera: atto fermo scrivibile | A-111, A-113 |
| Barriera: tipo in entrata ignorato | A-111 |
| Barriera: scrittura vagliata riusabile | A-113 |
| Barriera: stato vagliato non confrontato | A-111 |
| Barriera: passaggio vagliato fuori dal passaggio | A-111 |
| Rifiuto nel passaggio non annotato | A-105, A-113 |
| Rifiuto annotato non controllato | A-105, A-113 |
| Metadati liberi | A-93, A-95, A-96, A-97, A-98, A-99, A-100, A-101, A-102, A-103, A-104, A-105, A-106, A-107, A-108, A-109, A-111, A-112, A-113, A-114, A-115 |
| Metadati per numero liberi | A-112 |
| Cancellazione a tutti libera | A-112 |
| Voci libere | A-112, A-113 |
| Fine qualunque nel passaggio | A-113 |
| Dati liberi nel passaggio | A-93, A-95, A-96, A-97, A-98, A-99, A-100, A-101, A-102, A-103, A-104, A-105, A-106, A-107, A-108, A-109, A-111, A-112, A-113, A-114, A-115 |
| Gettone riutilizzabile | A-108 |
| Gettone non confrontato | A-108 |
| Richiesta con dati ammessa | A-108 |
| Concessione non legata alla scrittura | nessuna: una scrittura dello stesso atto senza il gettone si ferma alla porta principale e non arriva dove il legame si controlla |
| Concessione tenuta aperta | nessuna: per la stessa ragione, una seconda scrittura non arriva dove la concessione rimasta aperta varrebbe |
| Atto libero nel passaggio | A-113 |
| Valori senza barre | A-114 |
| Controllo finale tolto | A-105, A-113 |
| Controllo finale del rimando tolto | A-113 |
| Controllo finale: dati non confrontati | A-113 |
| Controllo finale: voci non confrontate | A-113 |
| Voce della fine non controllata | A-105 |
| Data fornita effettiva | A-97 |
| Fine fornita effettiva | A-99 |
| Fine dall'orologio vero | A-93, A-95, A-96, A-97, A-98, A-99, A-100, A-101 |
| Fine in secondi invece che in giorni | A-98 |

## Il terzo giro: la seconda revisione

La seconda revisione, sul commit 5938786, ha trovato tre difetti gravi, nessun altro. Hanno una
causa in comune con quelli del primo giro: **un controllo più stretto della cosa che doveva
coprire**. Si sono corretti allargando il controllo, non aggiungendo eccezioni.

**1. Un dato di servizio che cambia nome.** La funzione di WordPress che modifica un dato
indicandone il numero di riga può anche cambiarne il nome. Il guardiano guardava soltanto il
nome di partenza: il blocco della schermata aperta, che è un dato di servizio e resta
scrivibile, si poteva rinominare nella data di fine o nella data di adozione di un atto
pubblicato. Ora una rinomina si giudica su tutti e due i nomi: quello vecchio deve poter
essere tolto e quello nuovo scritto. Sulla bozza le rinomine lavorano come prima. A-112.

**2. Le tabelle delle voci.** Prima di cominciare il passaggio controllava che la tabella
dell'atto, quella dei suoi dati e quella del registro sapessero tornare indietro. Ma una voce
tolta da un altro programma durante il passaggio fa annullare il passaggio, e l'annullamento
conta proprio sul fatto che anche le tabelle delle voci tornino indietro: su un sito con
tabelle miste, la voce tolta sarebbe rimasta tolta. Ora il controllo comprende anche la
tabella che lega le voci agli atti e quella dei loro conteggi. A-107 le prova una per volta,
con un aggancio che toglierebbe la voce.

**3. La memoria dopo l'annullamento.** Dopo un annullamento il passaggio svuota la memoria
dell'atto, perché chi legge subito dopo non trovi lo stato annullato. Ma WordPress salta lo
svuotamento quando un programma l'ha sospeso, come fanno per esempio gli importatori:
chi aveva letto l'atto già pubblicato dentro il passaggio lo avrebbe ritrovato pubblicato. Ora
il passaggio riattiva lo svuotamento per il tempo necessario, svuota la memoria dell'atto e
quella dei conteggi delle sue voci, e rimette la sospensione com'era. A-105 lo prova prima di
qualunque fotografia, che svuota la memoria da sé e avrebbe nascosto il difetto; A-113
confronta il conteggio della voce in memoria con la banca dati dopo ogni annullamento.

### Le prove, al terzo giro

Centotrentasette prove nella suite principale, nessuna riga nuova: quattro righe allargate
(A-105, A-107, A-112, A-113), più le due suite separate, tutte verdi in locale; PHPCS pulito.
In verifica continua, sul commit 467aae4 che porta codice e prove, verdi le due combinazioni
di WordPress e PHP su MySQL, lo standard di codifica e la validazione di `publiccode.yml`.

**La prova di non vacuità.** Cinque guasti nuovi, uno per volta, con la suite intera. Al primo
passaggio ne sopravviveva uno, il conteggio delle voci non svuotato: nella pubblicazione la
voce tolta dentro il passaggio aveva un conteggio che, per coincidenza, alla fine tornava lo
stesso. A-113 ha ora un caso in cui la voce viene letta quando conta già l'atto come
pubblicato, e il guasto cade.

| Guasto introdotto | Prove cadute |
|---|---|
| Rinomina per numero giudicata sul nome vecchio | A-112 |
| Tabelle delle voci non controllate | A-107 |
| Memoria svuotata solo se lo svuotamento non è sospeso | A-105 |
| Sospensione di chi chiama non rimessa | A-105 |
| Conteggi delle voci non svuotati | A-113 |

## Il quarto giro: la terza revisione

La terza revisione, sul commit 7d6d7bb, ha trovato due difetti, uno grave e uno medio, tutti e
due sull'annullamento.

**1. Un passaggio dentro un altro.** Un altro programma, da un aggancio della pubblicazione di
un atto, poteva chiamare le funzioni dell'albo e pubblicare o rimandare un secondo atto. Il
secondo passaggio apriva la sua transazione dentro la prima, chiudendola, o sostituiva il suo
punto di ripristino: se poi la pubblicazione del primo falliva, il suo annullamento non tornava
piu' indietro, e l'atto poteva restare pubblicato senza conferma mentre la funzione rispondeva
con un errore. Ora **un passaggio alla volta**: mentre uno e' in corso, nessun altro comincia,
su nessun atto, e il rifiuto arriva prima di prendere il numero o di scrivere qualunque cosa.
Finito il primo, il secondo si fa normalmente. A-116.

Accanto, la stessa causa: l'annullamento non controllava la risposta della banca dati. Ora,
se la banca dati non conferma l'annullamento, il passaggio non dice "annullato": risponde con
un errore che nomina l'atto e dice che il suo stato va controllato, e dopo un'eccezione la
rilancia con questo avviso, tenendo l'eccezione di partenza come causa. A-117 lo prova dopo un
errore, alla chiusura e dopo un'eccezione, nella pubblicazione e nel rimando, e verifica che
l'avviso dica il vero: le scritture ci sono ancora.

**2. La memoria dopo l'annullamento, ancora.** Al giro precedente lo svuotamento copriva l'atto
e le voci che l'atto ha nella banca dati. Ma una voce aggiunta durante il passaggio non c'e'
piu' dopo l'annullamento, e il suo conteggio restava in memoria sbagliato. La causa e' piu'
larga di quel caso: l'annullamento riporta indietro tutto quello che e' stato scritto nella
transazione, dall'albo e da qualunque altro programma negli agganci, e la memoria non sa che
cosa. Ora dopo un annullamento **la memoria si svuota per intero**, senza guardare la
sospensione che chi chiama puo' aver messo. Prima di cominciare, invece, il passaggio continua
a svuotare solo la memoria dell'atto, togliendo la sospensione per il tempo necessario, per
leggerne lo stato dalla banca dati. A-113 confronta ora con la banca dati il conteggio di ogni
voce dell'elenco degli organi, compresa una aggiunta durante il passaggio; A-105 prova la
lettura dell'atto all'inizio con la memoria sospesa e gia' superata dalla banca dati.

### Limiti dichiarati, al quarto giro

- **Lo svuotamento intero ha un costo.** Su un sito con una memoria persistente, per esempio
  un servizio di memoria condiviso, un passaggio annullato la svuota tutta, e le pagine
  seguenti la ricostruiscono. Succede solo quando un passaggio fallisce, cioe' per un guasto
  della banca dati o per un altro programma che scrive l'atto durante il passaggio.
- **Un passaggio alla volta vale per la richiesta**, non per il sito: due persone che
  pubblicano nello stesso momento lavorano in due richieste separate, ciascuna con la sua
  transazione, e il numero di repertorio resta unico per i vincoli della banca dati.
- Resta il limite del primo giro su un aggancio che apre o chiude da se' una transazione: non
  passa dalle funzioni dell'albo, e chiude anche quella del passaggio.

### Le prove, al quarto giro

Centotrentanove prove nella suite principale, due righe nuove (A-116, A-117, in
`TuttoONienteTest.php`) e due allargate (A-105, A-113), piu' le due suite separate, tutte
verdi in locale; PHPCS pulito.
In verifica continua, sul commit 173353d che porta codice e prove, verdi le due combinazioni
di WordPress e PHP su MySQL, lo standard di codifica e la validazione di `publiccode.yml`. Le
righe A-116 e A-117 sono passate a "fatto" dopo quell'esito.

**La prova di non vacuita'.** Sedici guasti, uno per volta, con la suite intera: undici sulle
correzioni di questo giro, tre sulla memoria all'inizio del passaggio e due che tolgono
l'annullamento, riscritti sulla forma nuova del codice. Cadono tutti. I tre guasti del giro
precedente sulla memoria dopo l'annullamento (svuotata solo se non sospesa, sospensione non
rimessa, conteggi delle voci) non si applicano piu': quel codice e' stato sostituito dallo
svuotamento intero, e i primi due vivono ora nella memoria all'inizio del passaggio.

| Guasto introdotto | Prove cadute |
|---|---|
| Passaggio annidato ammesso nella pubblicazione | A-116 |
| Passaggio annidato ammesso nel rimando | A-116 |
| Passaggio annidato rifiutato solo sullo stesso atto | A-116 |
| Esito dell'annullamento ignorato | A-117 |
| Annullamento non riuscito ignorato dopo un errore della pubblicazione | A-117 |
| Annullamento non riuscito ignorato dopo un errore del rimando | A-117 |
| Annullamento non riuscito ignorato dopo un'eccezione della pubblicazione | A-117 |
| Annullamento non riuscito ignorato dopo un'eccezione del rimando | A-117 |
| Annullamento non riuscito ignorato alla chiusura | A-117 |
| Memoria svuotata solo per l'atto dopo l'annullamento | A-113 |
| Memoria non svuotata dopo l'annullamento | A-105, A-106, A-113 |
| Memoria all'inizio svuotata solo se lo svuotamento non e' sospeso | A-105 |
| Sospensione di chi chiama non rimessa | A-105 |
| Memoria non svuotata prima di leggere l'atto | A-105 |
| Annullamento della transazione tolto | A-106 |
| Annullamento del punto di ripristino tolto | A-105, A-113, A-116, A-117 |

## Il quinto giro: la quarta revisione

La quarta revisione, sul commit cce7fd7, ha trovato un solo difetto, medio, con la stessa causa
di quelli sulla memoria dei giri precedenti: lo svuotamento ordinario di WordPress viene
saltato quando chi chiama lo ha sospeso.

**La memoria dopo un passaggio riuscito.** Un importatore che sospende lo svuotamento della
memoria e poi rimanda in bozza un atto: il passaggio all'inizio svuota la memoria dell'atto e
lo rilegge, quindi la memoria torna a ricordarlo in verifica; poi la scrittura riesce, ma lo
svuotamento di WordPress e' sospeso, e chi legge dopo trova ancora l'atto in verifica mentre
la banca dati lo ha in bozza. Lo stesso per una pubblicazione, e per il conteggio delle voci
dell'atto. Ora il passaggio svuota la memoria dell'atto, compresi i conteggi delle sue voci, in
**tutte e tre le uscite**: all'inizio, prima di leggere; alla fine di un passaggio riuscito;
per intero dopo un annullamento. Ogni volta togliendo la sospensione per il tempo necessario e
rimettendola com'era. A-105 prova il rimando e la pubblicazione riusciti con lo svuotamento
sospeso fin dall'ingresso, confrontando stato, data di fine e conteggio della voce con la banca
dati prima di qualunque fotografia.

### Le prove, al quinto giro

Centotrentanove prove nella suite principale, nessuna riga nuova, A-105 allargata, piu' le due
suite separate, tutte verdi in locale; PHPCS pulito. In verifica continua, sul commit 4c31c12,
verdi le due combinazioni di WordPress e PHP su MySQL, lo standard di codifica e la validazione
di `publiccode.yml`. Sei guasti sulla memoria del passaggio,
uno per volta, con la suite intera: cadono tutti.

| Guasto introdotto | Prove cadute |
|---|---|
| Memoria non svuotata alla fine della pubblicazione | A-105 |
| Memoria non svuotata alla fine del rimando | A-105 |
| Conteggi delle voci non svuotati | A-105 |
| Memoria svuotata solo se lo svuotamento non e' sospeso | A-105 |
| Sospensione di chi chiama non rimessa | A-105 |
| Memoria non svuotata prima di leggere l'atto | A-105 |

## Il sesto giro: la quinta revisione

La quinta revisione, sul commit b46bd3c, ha trovato un difetto grave, nato dalla correzione del
giro precedente: lo svuotamento della memoria alla fine di un passaggio riuscito girava ancora
**dentro il passaggio**, dopo la conferma della transazione ma con il passaggio ancora in
corso. Quello svuotamento fa girare gli agganci di WordPress, cioè codice di altri componenti:
un aggancio poteva togliere la data di fine appena confermata, perché la concessione della
pubblicazione era ancora aperta, e la pubblicazione rispondeva riuscita con un atto senza fine.
E se un aggancio sollevava un'eccezione, il passaggio provava ad annullare una transazione già
confermata.

Ora lo svuotamento finale avviene **fuori dal passaggio**: la transazione è confermata, il
passaggio non è più in corso, e l'atto è fermo come ogni atto pubblicato o in bozza, senza
nessuna concessione. Un'eccezione di un aggancio in quel momento arriva a chi chiama con
l'avviso che il passaggio è confermato e l'eccezione di partenza come causa: non c'è più niente
da annullare, e niente si annulla. A-118.

Nello stesso giro è stato deciso il punto aperto 3: si tiene il comportamento di oggi.

### Le prove, al sesto giro

Centoquaranta prove nella suite principale, una riga nuova (A-118, in `TuttoONienteTest.php`),
più le due suite separate, tutte verdi in locale; PHPCS pulito. In verifica continua, sul
commit 83c6491, verdi le due combinazioni di WordPress e PHP su MySQL, lo standard di codifica e
la validazione di `publiccode.yml`; A-118 è passata a "fatto" dopo quell'esito. Sei guasti, uno
per volta, con la suite intera: cadono tutti.

| Guasto introdotto | Prove cadute |
|---|---|
| Svuotamento finale dentro la pubblicazione, come al quinto giro | A-118 |
| Svuotamento finale dentro il rimando, come al quinto giro | A-118 |
| Svuotamento finale con il passaggio ancora in corso | A-118 |
| Eccezione dopo la chiusura non detta | A-118 |
| Svuotamento finale tolto dopo la pubblicazione | A-105, A-118 |
| Svuotamento finale tolto dopo il rimando | A-105, A-118 |

## Il settimo giro: la sesta revisione

La sesta revisione, sul commit 369b9a8, ha trovato tre difetti, uno grave e due medi.

**1. Due richieste sullo stesso atto.** I controlli del passaggio leggono l'atto prima della
transazione, e niente impediva a un'altra richiesta, per esempio un'altra persona che lavora
sullo stesso atto, di rimandarlo in bozza, cambiarne l'oggetto o pubblicarlo proprio in quel
momento. Il passaggio proseguiva sulla copia letta prima: poteva pubblicare un atto ormai in
bozza, rimettendogli il testo vecchio e cancellando in silenzio la modifica dell'altra persona.
Ora la fotografia dell'atto si prende quando i controlli lo leggono; appena aperta la
transazione **la riga dell'atto si blocca** per le altre richieste fino alla chiusura, e l'atto
si rilegge dalla banca dati: se non è identico a quello controllato, il passaggio non comincia
e lo dice. Poi la memoria dell'atto si toglie, senza far girare agganci, così che il passaggio
scriva a partire da quello che la banca dati contiene. Il numero, se già preso, resta
dell'atto, come deciso. A-119, con una seconda connessione e la scrittura automatica accesa.

**2. L'eccezione dopo la chiusura e la memoria.** Se un aggancio solleva un'eccezione mentre la
memoria si svuota dopo la chiusura, lo svuotamento resta a metà: con lo svuotamento sospeso da
chi chiama, il conteggio delle voci restava quello di prima. Ora, prima di rilanciare
l'eccezione, la memoria si svuota per intero. A-118, con la memoria sospesa.

**3. La prova dell'avviso.** A-118 controllava l'eccezione, la sua causa e il numero dell'atto,
ma non che l'avviso dicesse davvero che il passaggio è confermato. Ora lo controlla.

### Limiti dichiarati, al settimo giro

- **Il blocco vale fra passaggi e scritture che passano dalla banca dati.** Una richiesta che
  ha già letto l'atto e lo scrive dopo la chiusura del passaggio trova il guardiano, come per
  ogni atto pubblicato.
- **Dentro la transazione di chi chiama** (punto aperto 3) la rilettura sotto il blocco vede
  la banca dati come la vede quella transazione.
- **Fra la lettura dell'atto e la fotografia dei controlli** passano due letture consecutive;
  una modifica di un'altra richiesta proprio lì la trova comunque il controllo finale, che
  annulla.

### Le prove, al settimo giro

Centoquarantuno prove nella suite principale, una riga nuova (A-119) e A-118 allargata, più le
due suite separate, tutte verdi in locale; PHPCS pulito. In verifica continua, sul commit
ccbd75c che porta codice e prove, verdi le due combinazioni di WordPress e PHP su MySQL, lo
standard di codifica e la validazione di `publiccode.yml`; A-119 è passata a "fatto" dopo
quell'esito. A-106 usa ora lo stesso aiuto di A-119 per la scrittura automatica accesa.

**La prova di non vacuità.** Nove guasti nuovi, uno per volta, con la suite intera. Al primo
passaggio ne sopravvivevano due. Il blocco tolto non faceva cadere niente perché la prova
cercava il blocco solo dopo la prima scrittura del passaggio, che blocca la riga da sé: ora lo
cerca subito dopo l'apertura, prima di ogni scrittura, e il guasto cade. Resta la memoria non
tolta sotto il blocco: serve solo nella finestra fra la lettura dell'atto e la fotografia, dove
il controllo finale annulla comunque.

| Guasto introdotto | Prove cadute |
|---|---|
| Blocco della riga tolto | A-119 |
| Atto non riletto sotto il blocco | A-119 |
| Solo lo stato riletto sotto il blocco | A-119 |
| Memoria non tolta sotto il blocco | nessuna: vedi sopra, la copre il controllo finale |
| Blocco tolto dalla pubblicazione | A-119 |
| Blocco tolto dal rimando | A-119 |
| Rifiuto del blocco senza annullamento | A-119 |
| Svuotamento di recupero tolto | A-118 |
| Avviso dopo la chiusura senza la conferma | A-118 |

Riprovati sul codice nuovo anche i guasti del quarto e del sesto giro: cadono tutti. Quello
dell'eccezione dopo la chiusura non detta non si applica più nella forma del sesto giro; lo
sostituisce l'avviso senza la conferma, qui sopra.

## L'ottavo giro: la settima revisione

La settima revisione, sul commit 664b31b, ha trovato due difetti, uno grave e uno medio.

**1. Una scrittura vagliata su una bozza e una pubblicazione in mezzo.** La barriera ammetteva
la scrittura che il primo livello aveva vagliato senza guardare lo stato della riga in quel
momento. Chi salvava una bozza poteva fermarsi dopo il vaglio, per esempio dentro un aggancio
di WordPress; un'altra persona intanto mandava l'atto in verifica e lo pubblicava; il
salvataggio riprendeva e riportava l'atto pubblicato in bozza, con il testo vecchio. Ora il
vaglio porta con sé **lo stato da cui è partito**, e la barriera lo ammette solo se la riga,
riletta dalla banca dati, ha ancora quello stato. Fra la rilettura e la scrittura non gira
nessun aggancio, ma un'altra richiesta può ancora scrivere proprio lì: per questo l'istruzione
stessa scrive solo se la riga ha ancora lo stato letto. Se non scrive niente, la richiesta si
ferma al primo annuncio del cambio di stato, prima di ogni altro componente, così che nessuno
annunci una scrittura che non c'è stata. A-120, con una seconda connessione e la scrittura
automatica accesa, anche con lo svuotamento della memoria sospeso.

**2. Un'eccezione mentre la riga si blocca.** Il blocco della riga, aggiunto al settimo giro,
stava dopo l'apertura della transazione ma fuori dalla gestione delle eccezioni: un'eccezione
di un altro componente durante la rilettura dell'atto lasciava la transazione aperta e la riga
bloccata, mentre l'albo considerava il passaggio finito. Ora il blocco sta dentro la stessa
difesa delle scritture: un rifiuto o un'eccezione annullano, e se l'annullamento non riesce
l'eccezione lo dice, con quella di partenza come causa. A-121.

### Limiti dichiarati, all'ottavo giro

- **Due richieste che mandano in verifica la stessa bozza nello stesso istante.** Se l'altra
  richiesta scrive proprio fra la rilettura e l'istruzione, e porta l'atto nello stesso stato
  che questa voleva scrivere, la verifica non distingue le due scritture: questa prosegue
  come se avesse scritto, ma l'atto porta i dati dell'altra, che sono passati dal loro vaglio.
  Un atto fermo non viene mai riscritto.
- **Un altro componente agganciato al cambio di stato alla stessa prima priorità** e
  registrato prima dell'albo sente l'annuncio prima della verifica.

### Le prove, all'ottavo giro

Centoquarantatré prove nella suite principale, due righe nuove (A-120, A-121), più le due
suite separate, tutte verdi in locale; PHPCS pulito. In verifica continua, sul commit 955ae22
che porta codice e prove, verdi le due combinazioni di WordPress e PHP su MySQL, lo standard
di codifica e la validazione di `publiccode.yml`; lo stesso sul commit 9b459c2, che rinforza
A-120, e A-120 e A-121 sono passate a "fatto" dopo quell'esito.

**La prova di non vacuità.** Sette guasti nuovi, uno per volta, con la suite intera. Al primo
passaggio ne sopravviveva uno: la verifica spostata dopo gli altri agganci, perché la prova
guardava solo che la richiesta si fermasse, non quando. Ora A-120 controlla anche che un altro
componente in ascolto del cambio di stato non senta niente, e il guasto cade.

| Guasto introdotto | Prove cadute |
|---|---|
| Vaglio senza lo stato di partenza | A-120 |
| Istruzione senza la condizione sullo stato | A-120 |
| Scrittura mancata non verificata | A-120 |
| Verifica non agganciata | A-120 |
| Verifica dopo gli altri agganci | A-120 |
| Blocco fuori dalla difesa nella pubblicazione | A-121 |
| Blocco fuori dalla difesa nel rimando | A-121 |

Riprovati sul codice nuovo i guasti del settimo giro, adattati alla forma nuova del blocco:
cadono tutti salvo la memoria non tolta sotto il blocco, che resta coperta dal controllo
finale come detto al settimo giro.
