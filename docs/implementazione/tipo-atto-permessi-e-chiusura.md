# Tipo atto, permessi e chiusura della pubblicazione

Scheda di lavorazione, verbi al passato. Righe di collaudo A-11..A-27, più ALBO-23,
ALBO-24, ALBO-25 e ALBO-26.

## In tre paragrafi

**Cosa è stato costruito.** Nel menu dell'amministrazione compare la voce **Albo
pretorio**, e chi ha il permesso può creare un atto, dargli un oggetto e salvarlo in bozza.
**Tipo di atto e organo esistono come elenchi amministrabili, ma dalla scheda dell'atto non
si scelgono ancora**: il riquadro di scelta è spento di proposito, perché quello predefinito
è un campo a testo libero e su un elenco controllato lo snaturerebbe. L'associazione fra un
atto e le sue voci arriva con la schermata di compilazione. Gli atti non si governano con i
permessi degli articoli ma con permessi propri, e il componente crea un ruolo suo,
Responsabile della pubblicazione all'albo. Se nessun ruolo del sito possiede quei
permessi, la bacheca lo dice a chi può rimediare, invece di lasciare che l'assenza del
menu venga scambiata per un guasto. **La pubblicazione non è aperta**: dalla schermata, da
codice e per programmazione, ogni tentativo lascia l'atto in bozza con un messaggio che dice
il perché.

**Come è stato costruito.** Il tipo di contenuto non è registrato direttamente in
WordPress: passa dal meccanismo comune, che lo accetta solo dentro una sezione già
dichiarata e che ricava da sé i nomi dei permessi dall'identificativo del tipo. Quei nomi
non sono scritti da nessuna parte nel codice dell'albo: si chiedono. Gli argomenti di
registrazione tengono il tipo visibile in amministrazione e chiuso verso il pubblico, e
sono scritti uno per uno invece di essere lasciati derivare. I due elenchi di voci, tipo di
atto e organo, sono registrati direttamente, perché per loro il meccanismo comune non
offre niente, e i loro permessi sono ricavati da quelli del tipo. I permessi si assegnano
con un meccanismo agganciato al numero di versione memorizzato sul sito, non
all'attivazione. La chiusura della pubblicazione è un elenco ordinato di regole dentro un
solo sbarramento, agganciato al punto in cui WordPress prepara la scrittura di un
contenuto: oggi la lista contiene una regola sola.

**Come si prova, e cosa lo farebbe diventare rosso.** Le prove sono settantaquattro in
tutto, su tre avvii separati della suite. Rileggono lo stato invece di fidarsi di quello
che una funzione risponde: il tipo risulta registrato chiedendolo al meccanismo comune, i
permessi risultano addosso ai ruoli chiedendo il permesso a utenti veri, e l'atto risulta
irraggiungibile facendo una richiesta pubblica al suo indirizzo. La prova di non vacuità è
stata eseguita a parte, con ventiquattro guasti introdotti uno per volta in una copia usa
e getta: la tabella in fondo dice quale guasto ha fatto cadere quale prova.

## I nove punti

### 1. File creati o modificati

| File | Cosa contiene |
|---|---|
| `albo-pretorio-pa.php` | le cinque costanti nuove e gli agganci |
| `includes/class-tipo-atto.php` | registrazione del tipo attraverso il meccanismo comune, i due elenchi di voci, la chiusura verso il pubblico |
| `includes/class-permessi.php` | insiemi di permessi, ruolo proprio, assegnazione ripetibile, avviso quando nessun ruolo li possiede |
| `includes/class-installazione.php` | il confronto con la versione memorizzata |
| `includes/class-chiusura-pubblicazione.php` | lo sbarramento e la sua unica regola attuale |
| `includes/class-rifiuti.php` | i due posti in cui finisce il motivo di un rifiuto |
| `tests/TipoAttoTest.php`, `tests/StaticoTest.php`, `tests/PermessiTest.php`, `tests/AggiornamentoTest.php`, `tests/ChiusuraPubblicazioneTest.php`, `tests/InerziaTest.php`, `tests/CollisioniTest.php` | le prove |
| `tests/SenzaCoreTest.php` | preesistente, esteso dalla sesta revisione: è il solo avvio della suite dove la corrispondenza dei permessi manca davvero |
| `tests/ambiente-albo.php` | l'azzeramento dell'ambiente condiviso, comune alle prove |
| `phpcs.xml.dist` | una esclusione per i test, motivata sul posto |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, `docs/dati.md` | contratto e mappa aggiornati nello stesso ramo |

### 2. Perché ciascuno

- **`class-tipo-atto.php`** attua la parte strutturale di ALBO-01 e la dichiarazione
  esplicita dell'esposizione per programmi. Sta separato dall'avvio perché l'avvio dichiara
  una sezione e questo registra una cosa dentro la sezione: due momenti diversi della vita
  del componente, che si romperanno per motivi diversi.
- **`class-permessi.php`** attua ALBO-23, ALBO-25 e ALBO-26.
- **`class-installazione.php`** attua ALBO-24.
- **`class-chiusura-pubblicazione.php`** non attua un requisito: impedisce che il
  componente ne prometta uno che non può ancora mantenere.
- **`class-rifiuti.php`** sta a parte perché i due depositi hanno due cicli di vita
  diversi, e tenerli insieme è il modo in cui si finisce con un contenitore indistinto.

### 3. Il flusso per chi usa il sito

Attivazione su un sito con il meccanismo comune attivo, poi la prima pagina di
amministrazione: i permessi sono assegnati e la voce **Albo pretorio** compare. Si crea un
atto, si scrive l'oggetto, si salva la bozza. Tipo di atto e organo si amministrano dalle
loro due voci di menu e non si assegnano ancora all'atto. Il tentativo di pubblicare non
riesce e
l'avviso dice che la pubblicazione non è ancora aperta e per quale motivo. Un utente con il
solo ruolo Autore di WordPress non vede gli atti; un utente con il ruolo Responsabile della
pubblicazione all'albo li vede e li redige.

### 4. Dati letti e scritti

Scritti una volta per ogni cambio di versione: il numero di versione installata in
un'opzione, i permessi sui ruoli, il ruolo proprio. L'opzione è il primo dato che questo
componente scrive nella banca dati: l'unità precedente non ne scriveva nessuno. Scritti
quando qualcuno redige: oggetto e le due voci scelte. Scritto quando uno sbarramento
interviene: il motivo del rifiuto, in un dato temporaneo che vive cinque minuti. Letto: la
corrispondenza fra nomi generici e permessi derivati, chiesta al meccanismo comune dopo la
registrazione del tipo.

### 5. Permessi

I nomi non sono scritti nel codice dell'albo: si chiedono al meccanismo comune, che li
ricava dall'identificativo del tipo. Gli insiemi sono elenchi di **nomi generici** di
WordPress, ed è il minimo indispensabile: bisogna pur dire quali permessi si vogliono. C'è
una prova statica che cerca i nomi derivati nei sorgenti e pretende di non trovarli.

| Chi | Cosa può fare sugli atti |
|---|---|
| Amministratore | l'insieme completo. Pubblicare gli è concesso come permesso e resta impossibile in pratica, perché lo sbarramento vale per tutti |
| Responsabile della pubblicazione all'albo | lo stesso insieme |
| Un ruolo che possedeva già il permesso di redigere atti | riceve tutto l'insieme di chi redige, e **non** quello di chi pubblica |
| Redattore, Autore, Collaboratore di WordPress | niente |
| Visitatore anonimo | niente: il tipo non è interrogabile dal pubblico |

Il divieto di cancellare un atto pubblicato **non esiste**: è una lavorazione successiva, e
finché non c'è, chi ha il permesso di cancellare un atto lo cancella.

La consultazione pubblica dipende da tre cose insieme, e non dalla sola politica della
sezione: dagli argomenti con cui il tipo è registrato, dallo stato del contenuto, e dal
filtro di scadenza del meccanismo comune. In questa fase la prima da sola la impedisce.

### 6. Modi di guasto

| Guasto | Come degrada |
|---|---|
| L'avvio non è andato a buon fine | il tipo non nasce: nessun menu, nessun atto, e resta l'avviso dell'unità precedente |
| L'identificativo del tipo è già in uso | il meccanismo comune rifiuta con un errore preciso invece di sostituire in silenzio il tipo esistente, e il componente lo dice in bacheca |
| L'assegnazione dei permessi gira due volte | additiva e ripetibile: non duplica e non toglie niente che il sito avesse aggiunto |
| Nessun ruolo possiede i permessi | avviso in bacheca con il nome del ruolo che dovrebbe averli |
| L'opzione con la versione viene cancellata a mano | il lavoro riparte alla richiesta successiva: il guasto si autoripara |
| Un componente di terzi scrive lo stato pubblicato direttamente nella banca dati | non lo impediamo. L'atto risulta pubblicato **e resta irraggiungibile**, perché il tipo non è interrogabile dal pubblico. C'è una prova d'attacco che lo dimostra |
| Il dato temporaneo con il motivo del rifiuto viene buttato via prima del tempo | resta l'indicatore nell'indirizzo di ritorno, e l'avviso degrada verso un messaggio meno preciso, mai verso il silenzio |

### 7. Prove aggiunte

Righe A-11..A-27 del catalogo, cioè quelle della prima stesura; le righe A-28..A-47 sono
nate nelle revisioni e ciascuna è spiegata nella sezione del giro che l'ha prodotta. Le tre
categorie restano distinte anche nelle prove: A-23 e
A-24 valgono sui **tre ingressi supportati**, A-15 verifica l'**assenza** delle tre rotte,
e A-25 verifica che lo **scavalcamento grezzo** cambi davvero lo stato e non renda
raggiungibile.

**Una cosa imparata scrivendo A-25, che vale la pena tenere.** L'aiuto della suite che
simula una richiesta consegna la stringa di interrogazione anche come variabili aggiuntive,
cosa che WordPress in una richiesta vera non fa. `post_type` è una variabile riservata, e
le variabili riservate rientrano **dopo** il controllo che scarta i tipi non interrogabili
dal pubblico: nel laboratorio l'indirizzo rispondeva, sul sito no. Preso alla lettera,
quell'aiuto avrebbe fatto scrivere una prova che descrive una condizione inesistente. La
prova ora rifà la richiesta con un'istanza pulita e nessuna variabile aggiuntiva, che è
come WordPress avvia una richiesta vera, e il commento nel file spiega perché.

### 8. Cosa NON è stato implementato

Nessun dato dell'atto oltre all'oggetto e ai due elenchi di voci, che esistono ma non si
assegnano ancora. Nessuna schermata di compilazione. Nessun controllo campo per campo.

**Sulla pubblicazione la garanzia va detta per intero, perché "nessuna pubblicazione per
nessuno" sarebbe più di quello che il codice mantiene.** I tre ingressi supportati, cioè la
schermata, l'inserimento e l'aggiornamento da codice e la richiesta di programmazione, non
portano un atto a pubblicato né a programmato, e la riga nella tabella dei contenuti non
attraversa mai quegli stati. La funzione di WordPress che scrive lo stato direttamente nella
banca dati non passa da quello sbarramento: chiamata da codice di terzi **può portare un atto
a pubblicato**, e quello che garantiamo in quel caso è che resti irraggiungibile su ogni
superficie pubblica.

Nessun documento
principale e nessun allegato: il file di un atto ha bisogno della consegna protetta del
meccanismo comune, che non esiste, e senza di essa un file caricato risponde al proprio
indirizzo diretto, indipendentemente dallo stato dell'atto. Nessuna esposizione per
programmi. Nessun divieto di modifica o cancellazione dell'atto pubblicato. Nessun numero
di repertorio, nessun referto, nessuna durata configurabile, nessun controllo sui dati
personali.

**Gli stati dell'atto non sono governati**, ed è diverso dal dire che ne esistono soltanto
tre. A2a rifiuta due richieste, quella di pubblicare e quella di programmare; tutti gli altri
stati che WordPress conosce, in attesa di revisione o nel cestino per esempio, restano quelli
di WordPress e nessuna regola dell'albo li tocca. Gli stati propri dell'atto, con le
transizioni consentite e chi può compierle, sono una decisione ancora aperta e non si
implementano finché non è chiusa.

ALBO-01 e ALBO-02 **restano intatte**: nulla qui le chiude nemmeno in parte.

### 9. Come si prova sul sito vero

Sette prove, raccolte nel documento di collaudo di rilascio del progetto.

| # | Azione | Cosa si deve vedere | Se non lo si vede |
|---|---|---|---|
| 1 | Attivare il componente su un sito pulito con il meccanismo comune attivo, poi aprire la bacheca | nel menu c'è **Albo pretorio**, e Aggiungi apre la schermata di un atto nuovo | i permessi non sono arrivati: il rilascio si annulla |
| 2 | Creare un atto con il solo oggetto e salvare la bozza | la bozza si salva | il controllo sta rifiutando tutto invece di rifiutare al momento giusto |
| 3 | Sullo stesso atto premere Pubblica | resta in bozza e compare l'avviso che dice che la pubblicazione non è aperta e perché | se l'atto risulta pubblicato, il rilascio si annulla |
| 4 | Creare un utente con il solo ruolo Autore ed entrare con quello | non si vedono gli atti, e aprendo a mano l'indirizzo di creazione WordPress rifiuta | i permessi non sono separati da quelli degli articoli |
| 5 | Creare un utente con il ruolo Responsabile della pubblicazione all'albo ed entrare con quello | si vedono gli atti e si può creare una bozza | il ruolo proprio non è configurato |
| 6 | Aprire `/wp-json/wp/v2/albo-atti` da un browser senza accesso | **rotta non trovata** | l'esposizione per programmi non è spenta: il rilascio si annulla |
| 7 | Togliere i permessi dell'albo a tutti i ruoli e ricaricare la bacheca da amministratore | compare l'avviso, che nomina il ruolo che dovrebbe averli. Poi si ripristina | la condizione verrebbe scambiata per un guasto |

## Prova di non vacuità

Eseguita in una copia presa fuori dal controllo di versione, quindi senza la possibilità
materiale di committare codice rotto. Ventiquattro guasti in tutto, uno per volta, ogni
volta con ripristino verificato: quattro nella prima stesura, cinque dopo la prima
revisione, sei dopo la seconda, tre dopo la terza, cinque dopo la quarta, uno dopo la
quinta.

| Guasto introdotto | Prova diventata rossa | Come è caduta |
|---|---|---|
| Il tipo non guarda più chi ha registrato la sezione | A-28 | tipo, elenchi, ruolo e permessi nascono dentro la sezione di un altro |
| Lo sbarramento decide sul solo nome del tipo | A-29 | il contenuto di un altro componente viene riportato in bozza |
| La collisione sugli elenchi di voci non si guarda | A-30, tutte e due le varianti | l'elenco di un altro componente viene sostituito |
| L'indicatore dell'avviso torna a dipendere dal dato temporaneo | A-34 | sparito il dato, non resta nemmeno l'avviso generico |
| L'esito della scrittura della versione torna ignorato | A-37 | il lavoro si dichiara concluso senza aver scritto niente |
| Il tipo diventa interrogabile dal pubblico | A-25 | l'indirizzo dell'atto risponde invece di dare non trovato |
| Lo stato programmato esce dagli stati negati | A-23, ingresso della programmazione | l'atto resta programmato invece di tornare in bozza |
| L'esposizione per programmi si accende | A-15 | compaiono le rotte che non devono esistere |
| La verifica dei permessi torna ai soli ruoli dichiarati | A-39 | il permesso perso sul ruolo completato da sé non viene visto |
| Il ritentativo torna a fidarsi della memoria invece che della banca dati | A-39 | il secondo tentativo non riscrive niente e fallisce di nuovo |
| Dopo la creazione del ruolo si controlla solo che esista | A-40 | il marcatore non scritto passa per buono |
| La proprietà torna a essere il solo valore memorizzato | A-41, tutte e tre le varianti | un oggetto sostituito da altri continua a risultare nostro |
| Il segno nell'indirizzo torna prima della distinzione | A-42 | una chiamata da codice fa comparire l'avviso a una persona |
| Il tipo diventa interrogabile dal pubblico | A-43 | nello stato parziale il contenuto diventa raggiungibile |
| Lo sbarramento torna a pretendere la registrazione completa | A-41, le due varianti sugli elenchi | perso un elenco, una pubblicazione supportata arriva a pubblicato |
| L'oggetto del tipo si conserva solo a registrazione completa | A-43, prima installazione | nello stato parziale il tipo non risulta più nostro e lo sbarramento smette |
| L'installazione si accontenta della proprietà del tipo | A-41 e A-43, sito già installato | i permessi si scrivono su un insieme incompleto |
| L'assegnazione non passa più sui ruoli non dichiarati | A-21 | il permesso nuovo non raggiunge il ruolo che aveva già gli altri |
| Il metodo dell'osservazione ambigua torna nel ramo | A-44 | la forma tolta ricompare, e con essa la domanda che confondeva le due cose |
| La verifica delle collisioni torna dentro la registrazione del tipo | A-45, mentre **A-30 resta verde** | il ritentativo la salta e sostituisce l'elenco di un altro componente |
| L'avviso del tentativo non si toglie alla postcondizione completa | A-46, seconda direzione | in bacheca resta un errore che descrive uno stato non più vero |
| Al recupero si cancellano tutti gli avvisi invece dei tre per chiave | A-46, controllo di non indiscriminatezza | sparisce anche l'avviso di un'altra superficie, che era ancora vero |
| La versione si riallinea a mano prima della richiesta di recupero | A-43, sito già installato | l'installazione esce perché non ha niente da fare, e il ritentativo non viene provato |
| Il ripiego alla corrispondenza vuota torna al posto dell'errore | A-47, ramo negativo, mentre **il controllo positivo resta verde** | i due elenchi nascono lo stesso, con i permessi predefiniti di WordPress |

Prima dei guasti e dopo ogni ripristino: settantaquattro prove verdi, cioè sessantasei, sei
e due sui tre avvii della suite. Al termine la copia è stata cancellata e l'albero
di lavoro non presentava differenze.

**Limite dichiarato:** quella prova è girata su PHP 8.4, che non è nessuna delle due
combinazioni della verifica continua, cioè WordPress 6.5 con PHP 8.1 e WordPress recente
con PHP 8.3. Vale come dimostrazione che le prove non sono vuote, non come sostituto della
verifica continua.

**Il rosso storico c'è anche prima della prova mirata**, ed è quello vero: le prove sono
state scritte prima del codice e mandate in esecuzione, con diciannove errori sulle classi
inesistenti e un fallimento sulla ricerca statica della registrazione degli elenchi di
voci. Le diciassette prove preesistenti erano verdi nello stesso passaggio.

## La seconda revisione, e cosa ha trovato

Nove difetti, tutti dello stesso genere: il componente faceva la cosa giusta nel suo
perimetro e non si chiedeva **di chi fosse la roba su cui agiva**, oppure dichiarava un
esito che non poteva dimostrare. Nessuno di essi produceva un errore: producevano un verde.

| # | Che cosa faceva | Che cosa fa adesso |
|---|---|---|
| 1 | Registrava il tipo guardando se le funzioni del meccanismo comune esistevano | Guarda se la sezione risulta registrata **da questo componente**. Che il meccanismo comune ci sia dice un'altra cosa: un altro componente può avere registrato la sezione prima, anche con le stesse politiche, e il tipo sarebbe nato dentro la sezione sua |
| 2 | Lo sbarramento decideva sul nome del tipo | Agisce solo su un tipo registrato da questa istanza. Un componente esterno può registrare un tipo con lo stesso identificativo, e riportare in bozza i suoi contenuti sarebbe governare roba di altri |
| 3 | Registrava gli elenchi di voci senza guardare se esistevano | Controlla **prima di registrare il tipo**: WordPress sostituisce l'oggetto che trova, e accorgersene dopo significa averlo già cancellato. Controlla anche l'esito delle due chiamate e rilegge il registro prima di dichiararsi registrato |
| 4 | Adottava un ruolo omonimo già esistente | Lo riconosce da un permesso marcatore che solo l'albo assegna: quello di una versione precedente si aggiorna, quello di un altro non si adotta. Il marcatore non poggia sull'opzione della versione, così l'autoriparazione dopo la perdita di quella sola opzione non scambia il proprio ruolo per estraneo |
| 5 | L'indicatore dell'avviso dipendeva dal dato che doveva sostituire | Vive nella richiesta corrente. Se il dato temporaneo non viene scritto o sparisce, l'avviso generico compare lo stesso |
| 6 | Deduceva da `is_admin()` che il salvataggio venisse da una persona | Riconosce il modulo della schermata dalla sua azione, dall'identificativo che dichiara e dal proprio codice di sicurezza. E le decisioni in attesa stanno in una pila, così un inserimento annidato non cancella quella di chi lo contiene |
| 7 | Ignorava l'esito della scrittura della versione | Lo controlla e rilegge. Dichiarare concluso un aggiornamento mai scritto significa non rifarlo mai più. L'assegnazione dei permessi si rilegge a sua volta da una copia nuova del registro dei ruoli, perché un permesso che resta solo in memoria sparisce alla richiesta dopo |
| 8 | Dichiarava ancora la versione dell'unità precedente | Dichiara `0.2.0-alpha` nell'intestazione e nella costante, e c'è una prova che pretende che i due posti dicano la stessa cosa invece di confrontare un numero scritto a mano |
| 9 | La scheda prometteva più del codice | Corretta in quattro punti, elencati qui sotto |

**Le quattro affermazioni corrette in questa scheda.** Che in A2a si scelgano tipo e organo
dalla scheda dell'atto, mentre il riquadro di scelta è spento e si amministrano soltanto i
due elenchi. Che le prove sul sito vero fossero sei, mentre ne sono sette. Che esistano
soltanto tre stati, mentre il componente rifiuta due richieste e non governa gli altri stati
di WordPress. E "nessuna pubblicazione per nessuno", che era più di quello che il codice
mantiene: la garanzia vale sui tre ingressi supportati, mentre la funzione che scrive lo
stato direttamente può portare un atto a pubblicato, e lì la garanzia è l'irraggiungibilità.

**Una cosa imparata sull'ambiente di prova.** I ruoli non vivono in memoria come i tipi di
contenuto: stanno in un'opzione della banca dati, che sopravvive alle esecuzioni precedenti
della suite. Una prova che si affida all'azzeramento del componente parte da quello che ha
lasciato l'esecuzione prima, perché quell'azzeramento chiede al meccanismo comune i nomi dei
permessi e quindi funziona solo a tipo registrato. Le prove hanno ora un azzeramento proprio,
che riconosce i permessi dalla forma del nome e non chiede niente a nessuno.

## La terza revisione, e cosa ha trovato

Quattro difetti tecnici e due contraddizioni fra documenti. I difetti sono di nuovo dello
stesso genere del giro precedente, e la cosa è istruttiva: **una postcondizione scritta
male somiglia moltissimo a una postcondizione giusta**, perché in condizioni normali dice
la stessa cosa.

| # | Che cosa faceva | Che cosa fa adesso |
|---|---|---|
| 1 | Verificava i permessi sui soli ruoli dichiarati | Verifica **ogni ruolo toccato**, compresi quelli che l'assegnazione completa da sé. Erano proprio quelli di cui nessuno tiene il conto |
| 2 | Dopo aver creato il ruolo controllava che esistesse | Controlla che il **marcatore** sia stato scritto, e se non lo è rimuove il ruolo e rilegge la rimozione. Senza recupero un ruolo creato a metà sarebbe diventato una collisione permanente |
| 3 | La proprietà del tipo era un valore booleano | È il confronto per identità con gli oggetti ricevuti alla registrazione. Un altro componente può sostituirli e WordPress non lo dice a nessuno |
| 4 | Il segno nell'indirizzo si metteva prima di distinguere l'origine | Si mette solo dentro il ramo della schermata. Prima, una chiamata da codice faceva comparire un avviso a una persona che non aveva chiesto niente |
| 5 | Nel catalogo A-28..A-38 erano ancora "da fare" | Portate a "fatto": erano verdi in verifica continua |
| 6 | Nel catalogo restava "nessuna pubblicazione per nessuno" | Riscritta: contraddiceva A-25 |

**Un difetto trovato correggendone un altro, e vale la pena raccontarlo.** Estendendo la
verifica a tutti i ruoli, la prova ha mostrato che **il ritentativo non ritentava**. Dopo
una scrittura persa l'oggetto in memoria ha il permesso e la banca dati no; la funzione che
concede saltava il lavoro perché chiedeva alla memoria se il permesso c'era già, e trovava
di sì. Risultato: il secondo tentativo non riscriveva niente e falliva di nuovo, per
sempre. Adesso legge dalla banca dati anche per decidere che cosa scrivere. È l'unico caso
in cui saltare il lavoro è esattamente l'errore da non fare.

### Che cosa resta registrato quando la postcondizione degli elenchi cade

La domanda era lecita e la risposta precedente, "il componente resta inerte", non bastava.
Quando la registrazione di un elenco di voci non regge, **il tipo è già registrato** presso
WordPress e presso il meccanismo comune, e uno dei due elenchi può essere già registrato.

**Non si può smontare in modo pulito.** Il meccanismo comune non espone una funzione per
togliere quel solo tipo, e toglierlo a WordPress lascerebbe il registro del meccanismo
comune a dire il contrario, cioè uno stato peggiore di quello che si vuole riparare. Per
questo la verifica sulle collisioni avviene **prima** della registrazione del tipo: è
l'unico momento in cui indietro si torna gratis.

Quello che si garantisce è quindi un elenco chiuso di cose che **non** succedono, e la riga
A-43 le verifica una per una: nessun permesso su nessun ruolo, nessun ruolo proprio creato,
nessuna versione memorizzata, nessuna azione del componente sui contenuti, e nessuna
raggiungibilità pubblica, perché gli argomenti di registrazione restano quelli chiusi.
Detto in breve: **il tipo resta, e resta muto.** Chi amministra il sito deve saperlo, e
l'avviso glielo dice.

## La quarta revisione: una domanda sola per due cose diverse

Il difetto trovato in questo giro non è un pezzo dimenticato: è un **errore di modello**, e
produceva il danno nella direzione peggiore.

L'osservazione unica che c'era prima rispondeva insieme a due domande che non sono la stessa:

1. il tipo atto oggi nel registro è ancora quello che abbiamo registrato noi?
2. la registrazione è **completa**, cioè lo sono anche tutti e due gli elenchi di voci?

Lo sbarramento della pubblicazione usava quella condizione unica. Conseguenza: bastava che
un elenco di voci venisse sostituito o perso perché lo sbarramento **smettesse di agire su
un tipo che era ancora il nostro**. Una richiesta supportata sarebbe arrivata a pubblicato.
Quando una condizione di sicurezza sbaglia, può sbagliare chiudendo troppo o aprendo troppo:
questa apriva.

**La correzione non richiede il ripristino impossibile del tipo.** Richiede due osservazioni
distinte, con nomi che non si possono confondere:

| Osservazione | Che cosa dice | Chi la usa |
|---|---|---|
| `TipoAtto::tipo_nostro()` | l'oggetto del tipo oggi nel registro è, per identità, quello ricevuto alla registrazione | **lo sbarramento della pubblicazione**, e soltanto lui |
| `TipoAtto::registrazione_completa()` | tipo e tutti e due gli elenchi presenti e ancora nostri | l'installazione, e chiunque abbia bisogno dell'insieme intero |

**L'oggetto del tipo si conserva appena il meccanismo comune lo registra, prima degli
elenchi.** È l'ordine che rende vera la distinzione: se gli elenchi non reggono, il tipo è
comunque nostro e lo sbarramento continua a proteggerlo.

### Che cosa sapevamo dire, e che cosa no

La prova precedente sullo stato parziale partiva da un ambiente azzerato. Poteva quindi dire
che non esistono ruolo, permessi e versione, e la scheda lo riportava come se valesse in
generale. **Vale della prima installazione e basta.** Su un sito dove il componente aveva
già completato almeno una volta, quelle tre cose esistono, e la domanda giusta non è se ci
siano: è se un tentativo fallito le tocchi.

Adesso A-43 copre i due momenti.

| | Prima installazione | Sito già installato |
|---|---|---|
| Ruolo, permessi, versione | non nascono | **esistono già, e restano intatti**: né cancellati né ampliati |
| Installazione | non prosegue | non prosegue, e la prova lo verifica anche con una versione da aggiornare, perché altrimenti l'asserzione sarebbe vuota |
| Sbarramento | respinge: il tipo è nostro | respinge, per la stessa ragione |
| Raggiungibilità pubblica | nessuna | nessuna |
| Ritorno allo stato completo | seconda chiamata nella stessa richiesta | richiesta successiva con gli elenchi al loro posto |

Resta separato, e non si usa come scusa, il caso già documentato dello scavalcamento grezzo:
la funzione che scrive lo stato direttamente può ancora portare un atto a pubblicato, e lì la
garanzia è che resti irraggiungibile. Non giustifica il mancato sbarramento degli ingressi
che controlliamo.

## La quinta revisione: tre difetti che sessantasei prove non toccavano

I tre difetti di questo giro hanno in comune il motivo per cui erano invisibili: **nessuno
dei tre sta su un percorso che le prove esistenti percorrevano.** Il primo su nessun
percorso affatto, il secondo su un percorso che si apre solo alla seconda chiamata, il terzo
su cosa resta in bacheca dopo che la correzione ha già funzionato.

### Il metodo tolto che era rimasto

La quarta revisione ha sostituito l'osservazione ambigua con due osservazioni distinte, e ha
cambiato tutti i chiamanti. **Non ha tolto il metodo.** Quel metodo leggeva una proprietà
statica che nel frattempo era stata rinominata, quindi chiamarlo sarebbe stato un errore di
esecuzione; ma nessuno lo chiamava, quindi nessuna prova di funzionamento lo esercitava e la
verifica continua restava verde.

Il danno peggiore non era l'errore di esecuzione. Era che chi lo avesse trovato avrebbe
avuto di nuovo a disposizione, con un nome che sembra quello giusto, esattamente la domanda
ambigua che la correzione aveva eliminato, e l'avrebbe usata credendo di chiedere la
proprietà del tipo.

Per questo A-44 è una riga **statica**: una prova che esegue codice non può trovare codice
che nessuno esegue. Cerca le tre forme in cui quel nome poteva comparire, e guarda anche le
prove, perché una prova che chiama il metodo tolto è un errore di esecuzione tanto quanto
una chiamata dal componente.

### La verifica delle collisioni che il ritentativo saltava

La verifica stava **dentro** la registrazione del tipo. Il ritentativo però quella parte non
la esegue: il tipo è già nostro, quindi si salta direttamente alla registrazione degli
elenchi. Sequenza possibile dentro una sola richiesta:

1. registrazione parziale: il tipo e il primo elenco restano nostri, il secondo cade;
2. un altro componente registra il secondo elenco, che è un identificativo libero;
3. l'albo ritenta;
4. la verifica non viene eseguita, perché stava in un passaggio che il ritentativo non fa;
5. la registrazione dell'elenco **sostituisce quello dell'altro componente**.

È la garanzia di A-30 che vale alla prima richiesta e non alla seconda. La verifica adesso
sta nel flusso esterno, prima sia della registrazione del tipo sia di quella degli elenchi, e
continua a distinguere i tre casi: elenco già nostro non è una collisione, elenco assente si
può registrare, elenco presente con un oggetto diverso da quello conservato è un conflitto e
non si sostituisce niente.

**A-30 da sola non bastava a scoprirlo**, e la prova di non vacuità lo mostra: rimesso il
controllo dentro la registrazione del tipo, A-45 diventa rossa e le due varianti di A-30
restano verdi.

### L'avviso che sopravviveva al recupero

Un tentativo fallito lascia un avviso che dice "non ha completato la registrazione, riproverà
alla richiesta successiva". Ma A-43 dimostra che una seconda chiamata **nella stessa
richiesta** può completare. Quando succedeva, l'avviso restava in lista, e al momento di
mostrare la bacheca compariva un errore che descriveva uno stato non più vero.

Un avviso falso è peggio di nessun avviso: chi lo legge non ha modo di sapere che è vecchio,
e cerca di rimediare a un guasto che non c'è più.

Adesso gli avvisi hanno una chiave. Raggiunta la postcondizione completa si tolgono **per
chiave** i tre che parlano di un tentativo di registrazione: registrazione incompleta,
elenco in conflitto, tipo non registrato. Sono i tre che quella postcondizione smentisce.
Gli avvisi delle altre superfici non si toccano, e la prova lo verifica: dopo il recupero
l'avviso sui permessi che nessun ruolo possiede, che in quel momento è ancora vero, continua
a comparire.

Sono tre avvisi e non uno perché il difetto è identico in tutti e tre: ciascuno descrive un
tentativo che non ha completato, e ciascuno diventa falso nello stesso istante. Toglierne uno
solo avrebbe lasciato due copie dello stesso problema.

### Il caso che la prova non provava

Nella prova sul sito già installato, la versione memorizzata veniva riportata a quella
corrente prima della richiesta di recupero. Con la versione già allineata l'installazione
esce subito perché non ha niente da fare, quindi la prova non distingueva "il lavoro
riparte" da "il lavoro non serviva": la verifica del ritentativo era vuota.

Adesso la versione resta quella vecchia, e la richiesta di recupero verifica che
l'installazione venga **davvero** rieseguita e che la versione avanzi a quella corrente.
**Qui non c'era nessun difetto nel codice**, che già ritentava correttamente: c'era una prova
che non lo dimostrava. Vale la pena dirlo, perché una prova che passa per il motivo sbagliato
è indistinguibile da una che passa per quello giusto finché qualcuno non la rompe apposta.

## La sesta revisione: un ripiego che sbagliava nella direzione che apre

Un difetto solo, e non stava su un percorso raggiungibile: stava su un percorso che non
deve esistere.

### Il ripiego che consegnava il vocabolario a chi gestisce le categorie

I permessi dei due elenchi di voci si ricavano da quelli del tipo, e la funzione che li
ricava dichiarava nel proprio commento la ragione per cui esiste: agganciarli ai permessi
delle categorie li renderebbe governabili da chi non ha niente a che fare con l'albo.
Poi, quando la corrispondenza non era disponibile, restituiva una corrispondenza vuota.

**Una corrispondenza vuota non lascia l'elenco senza permessi.** WordPress, quando non gli
si dice niente, mette i propri predefiniti: `manage_categories` per amministrare,
modificare e cancellare le voci, `edit_posts` per assegnarle. Cioè esattamente i permessi
delle categorie e degli articoli che quel commento dichiarava di voler evitare. Il ripiego
sembrava la scelta prudente e faceva l'opposto: fra i due modi di sbagliare, chiudere
troppo e aprire troppo, sceglieva quello che **apre**.

Il ramo era irraggiungibile, e lo è ancora: la corrispondenza manca soltanto quando il
meccanismo comune non è caricato, e in quel caso la precondizione sulla paternità della
sezione ferma `registra()` prima di arrivare agli elenchi. Non è una ragione per tenerlo.
Un ramo scritto per il caso di guasto è la parte di codice che qualcuno leggerà proprio
quando qualcosa è andato storto, e un ramo che in quel momento consegna il vocabolario
dell'albo a chi amministra le categorie non deve esistere, nemmeno se oggi nessuno ci
arriva. Adesso quel ramo restituisce un errore, e la registrazione degli elenchi si ferma
**prima di chiamare `register_taxonomy`**: nessun elenco nasce.

### Il ramo negativo, e come si raggiunge senza barare

La domanda posta prima di scrivere la prova era: si può mettere quel ramo nella sua
condizione vera senza aggiungere al componente un gancio che serve soltanto alle prove?

Sì, in un posto solo. Dei tre avvii della suite, quello **senza il meccanismo comune** è
l'unico in cui la corrispondenza manca davvero: le funzioni di quel componente non
esistono, e non è una simulazione. Là dentro la funzione si chiama per riflessione, che è
una cosa che fa la prova e non tocca il codice di produzione. La condizione osservata è
reale; artificiale è solo il punto di ingresso.

Quello che resta artificiale va detto invece che nascosto, e per questo la riga A-47 ha una
seconda prova nello stesso avvio: `registra()` chiamata per la via normale si ferma prima,
con il proprio errore, e nemmeno così nasce un elenco. Senza quella seconda prova, la prima
lascerebbe credere che la corrispondenza mancante sia una condizione che il componente
incontra passando dal flusso normale. Non la incontra.

**Il controllo positivo era la parte mancante da più tempo.** Nessuna delle settanta
prove leggeva i permessi con cui i due elenchi sono registrati: A-14 ne rileggeva sette
argomenti e non quello. Restavano quindi indistinguibili due esiti molto diversi, elenchi
con i nomi derivati dal tipo ed elenchi con i predefiniti di WordPress. Adesso A-47 chiede
i nomi attesi al meccanismo comune, che è quello che li deriva, invece di scriverli a mano,
e nega esplicitamente i quattro predefiniti.

**Il controllo positivo da solo non avrebbe scoperto niente**, e la prova di non vacuità lo
mostra: rimesso il ripiego alla corrispondenza vuota, il ramo negativo di A-47 diventa
rosso e il controllo positivo **resta verde**, perché nella suite con il meccanismo comune
la corrispondenza c'è e il ripiego non viene mai preso. Le due direzioni servono tutte e
due.
