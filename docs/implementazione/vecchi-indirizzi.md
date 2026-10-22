# Vecchi codici brevi e vecchi indirizzi di Albo Pretorio On line

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-35, righe di collaudo
A-220..A-223. Segue la pagina pubblica (`pagina-pubblica.md`) e l'importazione vera
(`importazione-vera.md`), di cui usa il ricordo del numero interno di origine. Nessuna
versione nuova del meccanismo comune.

## In tre paragrafi

**Cosa costruiamo.** Un sito che passa da Albo Pretorio On line a questo albo ha già una
pagina con dentro il codice breve `[Albo]`, a volte con attributi come `stato="1"`, e in
giro ci sono indirizzi vecchi: la scheda di un atto (`?action=visatto&id=123`), la sua stampa
o il certificato (`?action=printatto&id=123&pdf=c`), il documento da scaricare
(`?action=dwnalle&id=45&idAtto=123`). Spento il plugin precedente, la stessa pagina mostra
l'albo nuovo senza che nessuno la riscriva, e gli indirizzi vecchi di un atto importato
portano alla sua scheda nuova **solo finché l'atto è in pubblicazione**. Per un atto scaduto,
annullato, mai importato, o per un indirizzo scritto male, la risposta è "pagina non
trovata", identica a quella di un atto mai esistito. **Nessuna impostazione nuova.**

**Come la costruiamo.** All'inizio di `init`, quando tutti i plugin sono caricati, l'albo
prende il codice `[Albo]` solo se nessuno lo ha già registrato: con il plugin precedente
acceso resta suo, e le sue pagine restano sue. Se lo prende, `[Albo]` disegna esattamente
come `[albo_pretorio]` e ignora gli attributi, e la pagina che lo contiene diventa una pagina
dell'albo con tutte le sue regole (divieto ai motori, niente memorie di pagina, scheda "non
trovata" per ciò che non è in pubblicazione). Gli altri due codici del plugin precedente,
`[AlboAtto]` e `[AlboGruppiAtti]`, li prende allo stesso modo e non disegnano niente. Sulla
pagina dell'albo, un indirizzo con `action` uguale a `visatto`, `printatto` o `dwnalle` si
legge prima del tema: si prende il numero interno (`id` per i primi due, `idAtto` per il
terzo), si cerca l'unico atto pubblicato che lo ricorda come origine, e se quell'atto è in
pubblicazione si rinvia con un rinvio temporaneo alla sua scheda; altrimenti la richiesta
diventa la stessa "non trovata" della scheda nuova.

**Come si prova che funziona, e come si prova che il test è vero.** Prove vere di WordPress
sulle pagine con `[Albo]` e con `[albo_pretorio]`, con atti pubblicati dal passaggio vero
che ricordano un numero di origine come li lascia l'importazione. All'ultimo secondo del
giorno di fine i tre indirizzi vecchi dell'atto in corso rinviano alla scheda nuova; dalla
mezzanotte rispondono come un atto mai esistito, confrontando la risposta intera (stato,
risultati, titolo, intestazioni, divieto ai motori, corpo). Il plugin precedente acceso si
imita registrando i suoi tre codici prima dell'albo. Guasti introdotti nel codice per vedere
quale prova li vede: elencati sotto, in "Com'è andata davvero".

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-pagina-pubblica.php` | la presa dei codici vecchi se liberi, il riconoscimento delle pagine con `[Albo]`, la lettura degli indirizzi vecchi, la ricerca dell'atto per origine, il rinvio |
| `tests/PaginaPubblicaTest.php` | le righe A-220..A-223 |
| `docs/requisiti.md`, `docs/collaudo.md`, `docs/sicurezza.md`, questa scheda, l'indice, `pagina-pubblica.md` | il passaggio in ALBO-35, le righe nuove, il rinvio fra le regole di sicurezza |

### 2. Perché

ALBO-35: il passaggio da un albo precedente senza ripartire da zero. Ripartire da zero vuol
dire anche riscrivere le pagine del sito e rompere ogni collegamento già dato. Insieme
ALBO-05 e ALBO-18: un indirizzo vecchio è una strada in più verso l'atto, e su questa strada
lo scaduto deve restare invisibile come sulle altre.

### 3. Il flusso per chi usa il sito

Il cittadino apre la pagina "Albo pretorio" di sempre: vede l'elenco nuovo. Chi ha in un
messaggio o in un segnalibro l'indirizzo vecchio di un atto ancora affisso lo apre e si
ritrova sulla scheda nuova dello stesso atto, con i suoi documenti. Chi apre l'indirizzo
vecchio di un atto già defisso trova la pagina "non trovata" del sito, come per un indirizzo
mai esistito. Per l'amministrazione non cambia niente: spegne il plugin precedente, non lo
elimina (l'eliminazione cancella le sue tabelle), e le pagine restano dove sono.

### 4. Dati letti e scritti

Solo letture: il contenuto e lo stato delle pagine, chi possiede i codici brevi, il numero
interno di origine degli atti (`_albo_pretorio_origine_importazione`, scritto
dall'importazione vera), e le tre condizioni della pubblicazione. Nessuna scrittura, nessuna
opzione nuova.

### 5. Permessi coinvolti

Nessuno. Come la pagina pubblica, il rinvio e la "non trovata" sono gli stessi per il
visitatore e per l'amministratore (A-222).

### 6. Modi di guasto previsti

- **I due plugin accesi insieme**: il plugin precedente registra i suoi codici quando si
  carica, prima di `init`, e l'albo li trova occupati. Le sue pagine restano sue: l'albo non
  le riconosce, non le disegna, non ci applica nessuna regola, non apre il tipo per loro
  (A-221). L'albo nuovo si prova, nel frattempo, su un'altra pagina con `[albo_pretorio]`.
- **Un componente che prende `[Albo]` dopo l'albo**: chi possiede il codice si guarda quando
  si chiede quali sono le pagine dell'albo, e quelle pagine smettono di esserlo (A-221).
- **Due atti che ricordano la stessa origine** (non dovrebbe succedere: l'importazione non
  crea doppioni): l'indirizzo non sa quale dei due è il suo, e risponde "non trovata"
  (A-222).
- **La banca dati che non risponde** sulla ricerca dell'origine: "non trovata". Nel dubbio
  si chiude.
- **Un numero scritto in un altro modo** (`0123`, lettere, vuoto, ripetuto): "non trovata".
  Il plugin precedente stampava solo cifre senza zeri davanti, quindi nessun indirizzo vero
  si perde.
- **Un rinvio conservato da un browser o da una memoria**: il rinvio è temporaneo (302) ed
  esce con le intestazioni che vietano di conservarlo. Se qualcuno lo conservasse lo stesso,
  porterebbe alla scheda nuova, che ricontrolla l'atto e dopo la scadenza risponde "non
  trovata": nessun dato esce.

### 7. Test aggiunti

Righe A-220..A-223 di `collaudo.md`, in `tests/PaginaPubblicaTest.php`.

### 8. Che cosa non fa

- **Non apre il file del documento dall'indirizzo vecchio**: il documento di un atto in corso
  porta alla scheda dell'atto, dove ogni documento si scarica. Per aprire proprio quel file
  servirebbe che l'importazione ricordasse anche il numero interno di ogni documento, e oggi
  non lo fa.
- **Non chiude la cartella dei file del plugin precedente**
  (`wp-content/uploads/AllegatiAttiAlboPretorio/`): quei file li serve il server web senza
  passare da WordPress, quindi nessun plugin può fermarli. Dopo l'importazione la cartella va
  chiusa o tolta dal server, conservandone una copia: è un passo del piano di passaggio, non
  del programma.
- **Non traduce le ricerche e i filtri vecchi** (`?filtra=`, `categoria=`, `numero=`, `Pag=`)
  né gli attributi di `[Albo]` (`stato`, `cat`, `per_page`): si apre l'elenco nuovo intero.
  Le categorie di quel plugin non hanno un legame salvato con i tipi di questo albo, e
  `stato="2"` chiedeva lo storico degli scaduti, che qui non esiste.
- **Non risponde agli indirizzi vecchi fuori dalla pagina dell'albo**, per esempio sulla home
  con `?action=dwnalle`: il plugin precedente li stampava solo sulla sua pagina.
- **Non mostra l'atto incollato con `[AlboAtto]`** né il gruppo di `[AlboGruppiAtti]`: fuori
  dalla pagina dell'albo non valgono il divieto ai motori e la risposta "non trovata", quindi
  non si disegna niente. Il codice fra parentesi quadre non resta però scritto sul sito.
- **Non fa il certificato di pubblicazione** (`pdf=c`): porta alla scheda; il referto è
  ALBO-07, ancora bloccato.

### 9. Come si prova su un sito vero

Sulla copia di prova, con l'importazione fatta: disattivare (non eliminare) Albo Pretorio On
line. Aprire la pagina che conteneva `[Albo]`: mostra l'elenco nuovo, e il sorgente contiene
`noindex`. Prendere dal backup il numero interno (`IdAtto`) di un atto in corso e uno di un
atto scaduto, e aprire da una finestra anonima `pagina-albo/?action=visatto&id=<numero>` per
entrambi: il primo porta alla scheda nuova, il secondo risponde "pagina non trovata". Lo
stesso con `?action=dwnalle&id=1&idAtto=<numero>`. Riattivare per un momento il plugin
precedente: la pagina torna sua.

## Decisioni prese

Scelte di default, dette in una riga e da confermare guardando la copia:

1. **Con i due plugin accesi, `[Albo]` resta del plugin precedente.** È la via più sicura: un
   solo albo risponde su ogni pagina, e il passaggio avviene nel momento in cui si spegne il
   vecchio, senza toccare le pagine. Prendere `[Albo]` anche con il vecchio acceso farebbe
   disegnare a due programmi la stessa pagina.
2. **Rinvio temporaneo (302), non permanente.** L'indirizzo vecchio vale solo finché l'atto
   è affisso: un rinvio permanente lo direbbe valido per sempre.
3. **Il documento porta alla scheda**, non al file (punto 8).
4. **`[AlboAtto]` e `[AlboGruppiAtti]` muti**, per non lasciare il codice scritto sul sito.

## Com'è andata davvero

Le quattro righe sono verdi, insieme alle prove che c'erano già. Guasti introdotti nel codice
per vedere quale prova li vede, otto, tutti visti: rinvio senza il controllo della
pubblicazione (A-222, A-223), `[Albo]` preso anche se occupato (A-221), pagine con `[Albo]`
riconosciute anche se il codice è d'altri (A-221), origine su due atti accettata (A-222),
codici minori che disegnano (A-221), numero ripulito dagli zeri davanti (A-222), azione letta
senza distinguere maiuscole (A-222), numero dell'atto del documento letto dal parametro
sbagliato (A-223).
