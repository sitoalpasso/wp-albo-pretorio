# L'elenco in tabella e i dati della vista per il tema

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisiti ALBO-16 e ALBO-28, righe di collaudo A-195, A-196 e
A-203 riviste, A-218 e A-219 nuove. Parte dalla pagina pubblica di
[`pagina-pubblica.md`](pagina-pubblica.md) e ne cambia la decisione 2.
Nessuna versione nuova del meccanismo comune: basta la 1.7.0.

## In tre paragrafi

**Cosa costruiamo.** Due cambiamenti alla pagina che vede il cittadino, e uno per chi fa il
tema del sito. Il primo: l'elenco degli atti diventa una tabella, con una riga per atto e
sei colonne (numero di registro, tipo, oggetto con l'organo sotto, pubblicato dal, fino al,
quanti documenti ha). Su un telefono la tabella non si schiaccia: ogni riga diventa un
riquadro, con il nome della colonna davanti a ciascun dato. Il secondo: nella scheda
dell'atto il periodo si legge in due righe, "Pubblicato dal" e "Fino al", come nelle colonne
dell'elenco. Il terzo: una funzione che il tema può chiamare per sapere se sta disegnando la
pagina dell'albo e, se c'è una scheda aperta, il numero e il tipo dell'atto, così da
scriverli nella sua testata e nel percorso di navigazione. L'albo continua a non chiedere
niente al tema: il tema può usare la funzione oppure ignorarla.

**Come la costruiamo.** La tabella usa i suoi elementi propri: intestazioni di colonna, e
l'oggetto come intestazione di riga, così chi usa un lettore di schermo sente per ogni dato
la colonna e l'atto a cui appartiene. Su uno schermo stretto il foglio di stile dell'albo
cambia il modo in cui le celle si dispongono; alcuni browser, quando succede, smettono di
dire ai lettori di schermo che quella è una tabella, e per questo ogni elemento dichiara anche
il suo ruolo in modo esplicito. Il nome della colonna scritto davanti al dato su schermo
stretto è un testo che i lettori di schermo saltano, perché per loro il nome c'è già
nell'intestazione. Ogni classe nuova è definita nel foglio di stile dell'albo. La funzione per
il tema usa gli stessi tre controlli della scheda (pagina dell'albo guardata come pagina
singola, atto chiesto, atto in pubblicazione letto adesso): per un atto scaduto, una bozza o
un numero mai dato restituisce soltanto l'indirizzo dell'elenco, mai il numero.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove di A-195 e A-196
leggono la tabella e la scheda nuove con gli stessi atti di prima. A-218 legge la tabella come
documento: ogni colonna ha la sua intestazione, ogni riga ha l'oggetto come intestazione,
ogni cella ha il suo ruolo, il testo davanti al dato è nascosto ai lettori di schermo e c'è
lo stile che lo mostra solo su schermo stretto. Se si toglie l'intestazione di riga, il ruolo
di una cella o il testo nascosto, la riga diventa rossa. A-219 chiama la funzione su una
pagina qualunque, sull'elenco, sulla scheda di un atto in corso e sulla scheda di uno scaduto,
di una bozza e di un numero mai dato: il numero esce solo nel secondo caso. Se la funzione
dimentica il controllo della scadenza, la riga diventa rossa all'ultimo istante utile e a
mezzanotte.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa cambia |
|---|---|
| `includes/class-vista-pubblica.php` | l'elenco in tabella, il conteggio dei documenti, le due date separate nella scheda |
| `includes/class-pagina-pubblica.php` | l'atto in vista, letto una volta e usato dal titolo della finestra e dalla funzione per il tema |
| `includes/funzioni-api.php` | la funzione `albo_pretorio_vista_corrente()` |
| `assets/css/albo-pretorio-pubblico.css` | stili della tabella e della disposizione a riquadri su schermo stretto |
| `tests/PaginaPubblicaTest.php` | prove di A-195, A-196, A-203 riviste, A-218 e A-219 nuove |
| `docs/collaudo.md`, `docs/architettura.md`, `pagina-pubblica.md`, questa scheda e l'indice | righe, decisione 2, parte nuova |

### 2. Perché

ALBO-16 (l'elenco si legge e si confronta), ALBO-28 (accessibilità a carico del componente:
la tabella deve restare una tabella per i lettori di schermo anche su schermo stretto). La
funzione per il tema non è un requisito: serve a un tema che vuole una testata coerente con
la scheda aperta, senza leggere da sé i parametri dell'indirizzo e senza rifare i controlli
di pubblicazione, che rifatti male mostrerebbero il numero di un atto scaduto.

### 3. Il flusso per chi usa il sito

Il cittadino apre la pagina dell'albo e, sotto il modulo di ricerca, trova la tabella degli
atti in pubblicazione: per ogni riga il numero `71/2026`, il tipo, l'oggetto che apre la
scheda con l'organo sotto, le due date e "2 documenti". Dal telefono vede un riquadro per
atto con "Numero", "Tipo", "Pubblicato dal" davanti a ciascun dato. Nella scheda trova
"Pubblicato dal 01/10/2026" e "Fino al 16/10/2026" su due righe.

### 4. Dati letti e scritti

Solo letture, le stesse della pagina pubblica, più il conteggio di documento principale e
allegati dell'atto. Nessuna scrittura, nessuna opzione nuova.

### 5. Permessi coinvolti

Nessuno. La funzione per il tema restituisce a chiunque quello che la pagina mostra a
chiunque.

### 6. Modi di guasto previsti

- **Un browser che perde la tabella quando cambia la disposizione delle celle**: i ruoli
  dichiarati la tengono.
- **Un tema che chiama la funzione fuori dalla pagina dell'albo**, per esempio in un riquadro
  laterale di un'altra pagina: restituisce nullo.
- **Un tema che chiama la funzione troppo presto**, prima che WordPress sappia quale pagina
  è chiesta: restituisce nullo, perché la pagina non è ancora riconosciuta. Il tema la chiama
  nei suoi modelli, che vengono dopo.
- **La banca dati che non risponde** sul numero di registro: nessun numero, come nella scheda.

### 7. Test aggiunti

Righe A-195, A-196, A-203 riviste e A-218, A-219 nuove di `collaudo.md`, in
`tests/PaginaPubblicaTest.php`.

### 8. Che cosa non fa

- **Non restituisce l'oggetto dell'atto** al tema: l'oggetto resta nella scheda, che lo
  scrive come titolo. Il tema che vuole una testata scrive "Atto 71/2026", come il titolo
  della finestra.
- **Non mostra il nome del file caricato** né cambia il nome del file scaricato (ALBO-30):
  la scelta di `pagina-pubblica.md` resta.
- **Non mostra il numero di giorni** di pubblicazione: con lo slittamento ai festivi e la
  proroga per disservizio, la differenza fra le due date non è la durata stabilita per quel
  tipo di atto, e un numero che sembra la durata senza esserlo su una pagina che produce
  effetti legali crea più dubbi di quanti ne risolve.
- **Non mostra l'amministrazione** che ha chiesto la pubblicazione di un atto ospitato
  (ALBO-29): arriva con quell'unità, poi fatta in
  [`amministrazioni-ospitate.md`](amministrazioni-ospitate.md), che la mette sotto l'oggetto.
- **Non aggiunge pulsanti di condivisione**: la pagina è fuori dai motori di ricerca e gli
  atti contengono dati di persone; spingerli fuori dall'albo non è il suo compito.

### 9. Come si prova su un sito vero

Sulla pagina dell'albo con tre atti in pubblicazione, da una finestra anonima: la tabella ha
sei colonne e una riga per atto. Restringere la finestra sotto i 640 pixel di larghezza: ogni
atto diventa un riquadro con il nome di ciascun dato davanti, senza scorrimento orizzontale;
se compare una barra di scorrimento orizzontale, la disposizione stretta non funziona. Con un
lettore di schermo (NVDA o VoiceOver) muoversi fra le celle della tabella stretta: per ogni
dato si sente la colonna; se si sente due volte, il testo davanti al dato non è nascosto. Nel
tema, chiamare `albo_pretorio_vista_corrente()` nel modello della pagina: sulla scheda di un
atto in corso restituisce il numero; sulla scheda di un atto scaduto la pagina è "non
trovata" e il tema non la disegna nemmeno.

## Decisioni prese

1. **Tabella invece di elenco a righe**, che cambia la decisione 2 di
   [`pagina-pubblica.md`](pagina-pubblica.md): la tabella permette di confrontare gli atti
   colonna per colonna, e il motivo della scelta di allora, il telefono, è risolto dalla
   disposizione a riquadri su schermo stretto.
2. **Le date in due colonne**, perché si ordinano e si confrontano a colpo d'occhio, e la
   scheda le scrive allo stesso modo.
3. **L'organo sotto l'oggetto**, non in una colonna sua: è il dato che serve a riconoscere
   l'atto insieme all'oggetto, e una settima colonna non entra in uno schermo medio.
4. **Il conteggio dei documenti senza il formato**: "2 documenti", perché il formato di ciascuno
   è nella scheda, accanto al collegamento.

## Com'è andata davvero

Le righe A-218 e A-219 sono verdi, insieme alle 235 prove che c'erano già, con A-195, A-196
e A-203 riscritte per la tabella. PHPCS pulito. Guasti introdotti per vedere quale prova li
vede, sei, tutti visti: senza l'intestazione di riga (A-218), senza il ruolo delle celle
(A-218), con il nome davanti al dato non nascosto ai lettori di schermo (A-218, e A-195 che
legge le celle come un lettore di schermo), senza la disposizione a riquadri su schermo
stretto (A-218), senza lo stile dell'organo (A-203), con la funzione per il tema che non
guarda se l'atto è in pubblicazione (A-219).

Una cosa imparata scrivendo: la funzione per il tema non si può provare togliendo soltanto il
suo controllo, perché la pagina di un atto scaduto risponde già "non trovata" prima che il
tema parta, e la funzione restituisce nullo per quella ragione. La prova toglie anche la
risposta "non trovata", come farebbe un altro componente che la scavalca, e solo così il
controllo della funzione diventa visibile.

Fatto girare a mano il 20 ottobre 2026 su elenco e scheda disegnati dalle prove, con il solo
foglio di stile dell'albo e nessun tema, il motore automatico (axe 4.10, regole WCAG 2.0, 2.1
e 2.2 di livello A e AA, in Chromium) non trova violazioni né a 1280 né a 375 pixel di
larghezza, e a 375 pixel la pagina non scorre di lato. Il controllo di realtà regge: sulla
pagina costruita apposta trova l'immagine senza testo alternativo e il campo senza etichetta.
La prova sul tema predefinito di WordPress resta quella a mano di
[`pagina-pubblica.md`](pagina-pubblica.md), da ripetere sulla tabella prima di unire.
