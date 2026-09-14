# Tipo atto, permessi e chiusura della pubblicazione

Scheda di lavorazione, verbi al passato. Righe di collaudo A-11..A-27, più ALBO-23,
ALBO-24, ALBO-25 e ALBO-26.

## In tre paragrafi

**Cosa è stato costruito.** Nel menu dell'amministrazione compare la voce **Albo
pretorio**, e chi ha il permesso può creare un atto, dargli un oggetto, sceglierne il tipo
e l'organo che lo ha adottato, e salvarlo in bozza. Gli atti non si governano con i
permessi degli articoli ma con permessi propri, e il componente crea un ruolo suo,
Responsabile della pubblicazione all'albo. Se nessun ruolo del sito possiede quei
permessi, la bacheca lo dice a chi può rimediare, invece di lasciare che l'assenza del
menu venga scambiata per un guasto. **La pubblicazione non è aperta**: qualunque tentativo
di pubblicare o di programmare la pubblicazione di un atto lo lascia in bozza, con un
messaggio che dice il perché.

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

**Come si prova, e cosa lo farebbe diventare rosso.** Le prove sono quarantaquattro in
tutto, su tre avvii separati della suite. Rileggono lo stato invece di fidarsi di quello
che una funzione risponde: il tipo risulta registrato chiedendolo al meccanismo comune, i
permessi risultano addosso ai ruoli chiedendo il permesso a utenti veri, e l'atto risulta
irraggiungibile facendo una richiesta pubblica al suo indirizzo. La prova di non vacuità è
stata eseguita a parte, con quattro guasti introdotti uno per volta in una copia usa e
getta: la tabella in fondo dice quale guasto ha fatto cadere quale prova.

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
| `tests/TipoAttoTest.php`, `tests/StaticoTest.php`, `tests/PermessiTest.php`, `tests/AggiornamentoTest.php`, `tests/ChiusuraPubblicazioneTest.php` | le prove |
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
atto, si sceglie tipo e organo, si salva la bozza. Il tentativo di pubblicare non riesce e
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

Righe A-11..A-27 del catalogo. Le tre categorie restano distinte anche nelle prove: A-23 e
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

Nessun dato dell'atto oltre a oggetto, tipo e organo. Nessuna schermata di compilazione.
Nessun controllo campo per campo. **Nessuna pubblicazione, per nessuno.** Nessun documento
principale e nessun allegato: il file di un atto ha bisogno della consegna protetta del
meccanismo comune, che non esiste, e senza di essa un file caricato risponde al proprio
indirizzo diretto, indipendentemente dallo stato dell'atto. Nessuna esposizione per
programmi. Nessun divieto di modifica o cancellazione dell'atto pubblicato. Nessun numero
di repertorio, nessun referto, nessuna durata configurabile, nessun controllo sui dati
personali. Nessuno stato oltre bozza, programmato e pubblicato, perché la decisione sugli
stati dell'atto è ancora aperta.

ALBO-01 e ALBO-02 **restano intatte**: nulla qui le chiude nemmeno in parte.

### 9. Come si prova sul sito vero

Sei prove, raccolte nel documento di collaudo di rilascio del progetto.

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
materiale di committare codice rotto. Quattro guasti, uno per volta, ogni volta con
ripristino verificato.

| Guasto introdotto | Prova diventata rossa | Come è caduta |
|---|---|---|
| Il tipo diventa interrogabile dal pubblico | A-25 | l'indirizzo dell'atto risponde invece di dare non trovato |
| Lo stato programmato esce dagli stati negati | A-23, ingresso della programmazione | l'atto resta programmato invece di tornare in bozza |
| L'esposizione per programmi si accende | A-15 | compaiono le rotte che non devono esistere |
| L'assegnazione non passa più sui ruoli non dichiarati | A-21 | il permesso nuovo non raggiunge il ruolo che aveva già gli altri |

Prima dei guasti e dopo ogni ripristino: quarantaquattro prove verdi, cioè trentotto,
quattro e due sui tre avvii della suite. Al termine la copia è stata cancellata e l'albero
di lavoro non presentava differenze.

**Limite dichiarato:** quella prova è girata su PHP 8.4, che non è nessuna delle due
combinazioni della verifica continua, cioè WordPress 6.5 con PHP 8.1 e WordPress recente
con PHP 8.3. Vale come dimostrazione che le prove non sono vuote, non come sostituto della
verifica continua.

**Il rosso storico c'è anche prima della prova mirata**, ed è quello vero: le prove sono
state scritte prima del codice e mandate in esecuzione, con diciannove errori sulle classi
inesistenti e un fallimento sulla ricerca statica della registrazione degli elenchi di
voci. Le diciassette prove preesistenti erano verdi nello stesso passaggio.
