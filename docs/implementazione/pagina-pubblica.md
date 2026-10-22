# La pagina pubblica dell'albo

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisiti ALBO-06, ALBO-16 e ALBO-28, righe di
collaudo A-195..A-209.
Nessuna versione nuova del meccanismo comune: basta la 1.7.0.

## In tre paragrafi

**Cosa costruiamo.** La pagina che vede il cittadino. L'amministrazione crea una pagina del
sito, per esempio "Albo pretorio", e ci scrive il codice breve `[albo_pretorio]`. Su quella
pagina compaiono: l'elenco degli atti in pubblicazione, dal più recente, venti per pagina;
un modulo di ricerca per testo, numero di registro, tipo di atto, organo e date; la scheda
di ogni atto, con i suoi dati e i documenti da scaricare. Gli atti scaduti, le bozze e gli
atti in verifica non ci sono mai: l'indirizzo della scheda di un atto scaduto risponde
"pagina non trovata", come un indirizzo mai esistito. La pagina dice ai motori di ricerca di
non indicizzarla, non entra nella mappa del sito e non si conserva nelle memorie di pagina.
**Nessuna impostazione nuova**: la pagina è quella in cui l'amministrazione mette il codice
breve.

**Come la costruiamo.** Il tipo atto resta chiuso come oggi: nessun indirizzo di WordPress
apre un atto. L'unica vista pubblica è quella del codice breve, che l'albo disegna da sé,
con i propri stili e senza chiedere niente al tema (ALBO-28). La scheda si apre con un
parametro dell'indirizzo della pagina (`?albo_atto=<numero interno>`); prima che il tema
disegni qualunque cosa, l'albo controlla l'atto e, se non è in pubblicazione, consegna la
pagina "non trovata" del sito con lo stesso percorso di WordPress. Un atto è in
pubblicazione se è pubblicato, ha il numero di registro assegnato dal passaggio vero, e il
meccanismo comune dice che non è scaduto: tre condizioni, tutte lette alla richiesta, mai da
un compito pianificato. I documenti si scaricano dal punto pubblico di consegna del
meccanismo comune, che consegna solo i file di un tipo "consultabile dal pubblico": l'albo
dichiara consultabile il tipo atto **solo se esiste una pagina pubblicata con il codice
breve**, perché quella è la sua vista pubblica. Senza la pagina, il tipo resta non
consultabile e nessun documento esce, come oggi.

**Come si prova che funziona, e come si prova che il test è vero.** Prove vere di WordPress
sulla pagina con il codice breve: un atto in corso, uno scaduto, una bozza, uno in verifica,
uno pubblicato scrivendo lo stato nella banca dati senza numero. All'ultimo secondo del
giorno di fine l'atto in corso si vede in elenco, nella scheda e nel documento; dalla
mezzanotte sparisce da tutti e tre, e la sua scheda risponde come il numero di un atto mai
esistito, per il visitatore e per l'amministratore. L'atto di controllo, che scade dopo,
resta: senza di lui un'assenza direbbe solo che la pagina è rotta. Ogni criterio di ricerca
restringe a un sottoinsieme proprio; un campo scritto male produce un messaggio e nessun
risultato, mai l'elenco intero spacciato per risultato. Per l'accessibilità, le prove
leggono il codice prodotto: ogni campo ha la sua etichetta, i titoli non saltano livelli, i
collegamenti si distinguono dal testo, la pagina corrente è dichiarata, ogni classe usata è
definita nel foglio di stile dell'albo e l'indicatore del fuoco è disegnato dall'albo. Il
motore automatico di verifica (axe) si fa girare a mano su questa versione; dentro la verifica
continua di GitHub entra con il passo successivo.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-pagina-pubblica.php` | il codice breve, il riconoscimento della pagina dell'albo, il tipo consultabile solo con la pagina, la risposta "non trovata", il divieto ai motori, la mappa del sito, le memorie di pagina, l'anteprima incorporata, il foglio di stile |
| `includes/class-vista-pubblica.php` | il disegno di elenco, scheda e ricerca, e la lettura della ricerca chiesta |
| `assets/css/albo-pretorio-pubblico.css` | gli stili dell'albo, con l'indicatore del fuoco |
| `albo-pretorio-pa.php` | caricamento dei due file e agganci |
| `tests/PaginaPubblicaTest.php` | le prove |
| `docs/requisiti.md`, `docs/collaudo.md`, `docs/sicurezza.md`, questa scheda e l'indice | stati, righe nuove, l'eccezione del modulo pubblico senza gettone |

### 2. Perché

ALBO-06 (le pagine dell'albo non si indicizzano), ALBO-16 (ricerca e filtri), ALBO-28
(accessibilità a carico del componente). Più ALBO-05 e ALBO-18 sulle strade nuove: la scheda
"Che cosa non fa" di `scaduti-invisibili.md` prometteva che A-175 e A-177 si sarebbero
ripetute sulla pagina pubblica appena costruita. Nella lista di controllo del verdetto è il
primo lavoro della Parte D, esigenze 1, 11 e 12.

### 3. Il flusso per chi usa il sito

Il cittadino apre la pagina dell'albo. In cima trova il modulo **Cerca negli atti**, con le
etichette sempre visibili: testo dell'oggetto (oppure il numero di registro scritto come
`71/2026`), tipo di atto, organo, pubblicati dal, pubblicati al, e i pulsanti "Cerca" e
"Mostra tutti". Sotto, il titolo **Atti in pubblicazione** con quanti sono, e l'elenco: per
ogni atto l'oggetto, che apre la scheda, e sotto numero di registro, tipo, organo e periodo
"dal 01/10/2026 al 16/10/2026". Se gli atti sono più di venti, sotto c'è la navigazione fra
le pagine.

La scheda ha in cima "Torna all'elenco degli atti", poi l'oggetto come titolo e i dati:
numero di registro, tipo, organo, numero proprio dell'atto, data di adozione, periodo di
pubblicazione e, se l'amministrazione ha scelto di mostrarli, responsabile, ufficio e
richiedente. Poi i **Documenti**: "Documento principale (PDF, 245 kB)", "Allegato 1 (PDF,
1,2 MB)" e così via. Il titolo della finestra del browser diventa "Atto 71/2026", senza
l'oggetto, che resta fuori dalla cronologia del browser.

Chi apre l'indirizzo di una scheda che non è in pubblicazione trova la pagina "non trovata"
del sito.

### 4. Dati letti e scritti

Solo letture. Dell'atto: stato, oggetto, data di inizio (la data del contenuto, scritta dal
passaggio a pubblicato), data di fine dal meccanismo comune, numero di registro, tipo,
organo, numero proprio, data di adozione, dati del procedimento da mostrare, documento
principale e allegati, con dimensione dall'impronta del deposito. Della pagina: contenuto
(per il codice breve), stato, password. Nessuna scrittura, nessuna opzione nuova.

### 5. Permessi coinvolti

Nessuno: la pagina è pubblica e mostra a tutti la stessa cosa. Chi è collegato, anche come
amministratore, vede quello che vede il cittadino: niente bozze, niente scaduti, niente
anteprime (A-196).

### 6. Modi di guasto previsti

- **Il codice breve fuori dalla sua pagina** (in un riquadro laterale, in un altro articolo
  mostrato in un elenco, nell'interfaccia per programmi, in un feed): lì il divieto ai
  motori e la risposta "non trovata" non si applicano. Il codice breve non disegna niente
  fuori dalla pagina che lo contiene, guardata come pagina singola (A-200).
- **Un atto pubblicato scavalcando il passaggio** (stato scritto nella banca dati): non ha
  numero di registro, quindi non entra né nell'elenco né nella scheda (A-196).
- **La banca dati che non risponde** sulla lettura del numero di registro: l'atto non si
  mostra. Nel dubbio si chiude.
- **Un tema che cambia gli stili**: è il limite dichiarato di ALBO-28.
- **Una memoria di pagina del server** che conserva l'elenco oltre la mezzanotte: la pagina
  esce con le intestazioni che vietano di conservarla e con la costante `DONOTCACHEPAGE`, che
  le estensioni di memoria più diffuse rispettano. Una memoria che ignora entrambe è fuori
  dal controllo dell'albo: è il lavoro di ALBO-20.
- **Il parametro della scheda scritto male** (lettere, zero, numero enorme): pagina "non
  trovata", come un atto mai esistito.

### 7. Test aggiunti

Righe A-195..A-209 di `collaudo.md`, in `tests/PaginaPubblicaTest.php`.

### 8. Che cosa non fa

- **Non costruisce il codice breve `[Albo]` né i vecchi indirizzi** del plugin precedente
  (`?action=visatto` e simili): sono un lavoro a sé, poi fatto in
  [`vecchi-indirizzi.md`](vecchi-indirizzi.md). Il nome nuovo, `[albo_pretorio]`, non si
  scontra con quello vecchio, e i due plugin possono restare accesi insieme durante il
  passaggio.
- **Non cambia il nome del file scaricato** (ALBO-30): il documento esce con il nome di oggi.
  L'elenco dei documenti però non mostra il nome del file caricato, che può contenere nomi di
  persona: dice "Documento principale" e "Allegato 1", con formato e dimensione.
- **Non mostra l'amministrazione che ha adottato un atto ospitato** (ALBO-29): arriva con
  quell'unità.
- **Non svuota le memorie di pagina alla scadenza** (ALBO-20): vieta soltanto di conservare
  la pagina.
- **Non porta il motore di verifica dell'accessibilità nella verifica continua**: le due
  righe di ALBO-28 sui due temi sono rimaste "da fare" fino al passo che apre le pagine
  in un browser vero dentro GitHub, arrivato dopo (sotto, "Il browser vero nei controlli").
- **Non costruisce un blocco** dell'editor: il codice breve si scrive anche nell'editor a
  blocchi, nel blocco "Codice breve".

### 9. Come si prova su un sito vero

Creare una pagina "Albo pretorio" con `[albo_pretorio]` e pubblicarla. Pubblicare un atto
con documento e un allegato. Da una finestra anonima: l'atto è in elenco, la scheda si apre,
i documenti si scaricano; il sorgente della pagina contiene `noindex` e la risposta porta
`X-Robots-Tag: noindex`; la mappa del sito (`/wp-sitemap.xml`) non elenca la pagina. Cercare
per numero (`71/2026`) e per una parola dell'oggetto. Percorrere la pagina con il solo
tasto Tab: il fuoco si vede sempre. Il giorno dopo la fine: l'atto non è in elenco, la sua
scheda e il suo documento rispondono "non trovato".

## Decisioni prese

Scelte di default, dette in una riga e da confermare guardando la pagina sulla copia:

1. **Dove sta la pagina**: in una pagina del sito con il codice breve, così ha la testata e
   il piè di pagina del sito e nessuna impostazione nuova.
2. **Elenco a righe, non a tabella**: su un telefono una tabella con cinque colonne non si
   legge, e un elenco con titolo e dati sotto resta lo stesso su ogni schermo. **Superata**
   da [`elenco-in-tabella.md`](elenco-in-tabella.md): l'elenco è una tabella, che su
   schermo stretto diventa un riquadro per atto.
3. **Documenti senza il nome del file caricato**: "Documento principale", "Allegato 1",
   con formato e dimensione.
4. **Ricerca per numero dentro il campo di testo**: chi scrive `71/2026` trova l'atto 71 del
   2026, senza un campo in più.
5. **Il modulo di ricerca pubblico non ha il gettone di sicurezza**: non cambia niente, mostra
   a tutti la stessa cosa, e un gettone legato alla sessione renderebbe la pagina diversa per
   ogni visitatore. È l'eccezione dichiarata in `sicurezza.md`.

## Com'è andata davvero

Le nove righe sono verdi, insieme alle 223 prove che c'erano già. Fatto girare a mano il 19
ottobre 2026 sulla pagina disegnata, il motore automatico (axe 4.10, regole WCAG 2.0 e 2.1 di
livello A e AA, in Chromium con lo schermo di un telefono) non trova violazioni su sei pagine
(elenco, seconda pagina, ricerca con un filtro, ricerca senza risultati, ricerca scritta male,
scheda con allegato) e su due temi: il tema predefinito di WordPress e un tema spoglio
senza nessuno stile. Il controllo di realtà regge: sulla pagina costruita apposta lo stesso
motore trova l'immagine senza testo alternativo e il campo senza etichetta. Percorse con il
tasto Tab, le pagine non hanno punti da cui non si esce e l'indicatore del fuoco è quello
dell'albo, una linea continua di 3 pixel, anche sul tema spoglio. Sul tema spoglio il motore
aveva segnalato una sola cosa, la pagina senza titolo della finestra: era il tema di prova,
che non lo stampava, non l'albo.

Guasti introdotti nel codice per vedere quale prova li vede, diciotto, tutti visti: senza il
controllo del numero di registro (A-196), senza il controllo della scadenza nella scheda
(A-196, A-197), senza la pagina "non trovata" (A-196), senza metatag, intestazione, mappa o
divieto di conservare (A-202), con il tipo sempre consultabile (A-201), senza il blocco
dell'anteprima (A-201), con il codice breve che disegna ovunque (A-200), con l'organo
ignorato, la fine del giorno esclusa o il numero non riconosciuto (A-198), con la pagina
chiesta ignorata (A-195, A-199), con la ricerca persa nei collegamenti delle pagine (A-199),
con un campo scritto male scartato in silenzio (A-199, A-203), con una classe senza stile o
un campo senza etichetta (A-203).

Tre cose imparate scrivendo:

- **Il punto pubblico di consegna non consegna i documenti di un tipo chiuso** (C-176 del
  meccanismo comune). È la ragione della consultabilità dichiarata solo con la pagina: senza,
  la scheda avrebbe mostrato collegamenti a documenti rifiutati.
- **Le versioni di WordPress precedenti alla 6.8 incorporano ogni contenuto consultabile**,
  perché non conoscono il tipo "incorporabile". Con la pagina, l'atto in corso sarebbe uscito
  in anteprima da un indirizzo con il solo numero; l'albo lo toglie, ma solo finché il tipo
  resta chiuso: se un altro componente lo apre, l'anteprima è una delle sue strade e la
  scadenza la governa il meccanismo comune (A-175, che lo prova, resta verde).
- **Un indirizzo per atto**: il parametro della scheda non si ripulisce prima di leggerlo.
  Con uno spazio davanti o uno zero, la scheda risponde "non trovata": ripulito, sarebbe
  stato un secondo indirizzo della stessa scheda.


### La prima revisione indipendente

Cinque rilievi, tutti veri, con una sola causa comune: le condizioni del documento e della
scheda non erano le stesse. Trattati così:

- **Il documento di un atto che la pagina non mostra si scaricava.** Il punto di consegna
  guarda pubblicazione, password e scadenza, non il numero di registro, e una password data
  con il biscotto di WordPress gli basta. Ora, nella richiesta del documento di un atto che
  non è in pubblicazione, l'albo non apre il tipo, e il punto di consegna risponde con il suo
  rifiuto, uguale a quello per uno scaduto. Se il tipo lo ha aperto un altro componente
  resta la sua scelta, come per l'anteprima (A-175, A-177). Riga A-204.
- **La "non trovata" di una scheda perdeva il divieto ai motori e alle memorie**: dopo la
  trasformazione la richiesta non è più una pagina singola, e guardarla di nuovo non la
  riconosceva. Ora la trasformazione si ricorda per la richiesta. La costante delle memorie
  di pagina si prova in un avvio separato, perché una costante già definita da un'altra
  prova renderebbe la riga verde per il motivo sbagliato. Riga A-205.
- **Un divieto rivolto a un solo motore**, scritto da altri, riceveva il `noindex` in coda,
  dove vale solo per quel motore. Ora quel valore esce intero su una riga propria e una riga
  generale `X-Robots-Tag: noindex` esce sempre, come fa il meccanismo comune per le sezioni
  vietate. Riga A-206.
- **Due codici brevi nella stessa pagina** davano identificativi doppi. Dal secondo, gli
  identificativi prendono il numero dell'istanza. Non si disegna una volta sola: un'estensione
  che legge il contenuto prima del tema consumerebbe l'unica istanza. Riga A-207.
- **La prova sull'interfaccia per programmi** passava anche senza il controllo, perché la
  richiesta precedente era un'anteprima. Ora la richiesta principale è la pagina dell'albo,
  così l'unica condizione che manca è l'interfaccia, e il controllo è `wp_is_rest_endpoint()`,
  che risponde anche per una lettura fatta da dentro una pagina. Riga A-200.

Guasti introdotti dopo le correzioni: ventisei, i diciotto di prima e otto nuovi (consegna
senza il controllo della pagina, "non trovata" dimenticata, senza la riga generale propria,
valore per un motore preso per generale o con il divieto in coda, identificativi uguali,
senza il controllo sull'interfaccia, costante solo sulla pagina). Tutti visti.

### La seconda revisione indipendente

Le cinque correzioni del primo giro reggono. Tre rilievi nuovi, ciascuno con una causa
propria:

- **L'atto si leggeva in due modi.** Il controllo dell'albo scartava `123x` come valore non
  numerico, mentre il punto di consegna lo legge come l'atto 123: con un indirizzo storpiato
  il documento di un atto senza numero tornava scaricabile. Ora l'albo legge l'atto con la
  stessa conversione del punto di consegna. Riga A-204, estesa.
- **Una pagina dell'elenco oltre l'ultima** diceva che l'albo era vuoto, perché oltre
  l'ultima pagina WordPress non conta gli atti. Succede con un collegamento salvato alla
  seconda pagina quando un atto scade. Ora si conta dalla prima pagina, e la pagina dice
  quanti atti ha l'elenco, con il collegamento alla prima pagina che conserva la ricerca.
  Riga A-208.
- **Le prove azzeravano a mano la memoria delle pagine dell'albo**, quindi sarebbero
  rimaste verdi anche senza gli agganci che la svuotano. Gli azzeramenti sono tolti, e una
  prova segue la pagina dalla creazione alla ripubblicazione, passando per codice tolto e
  rimesso, password, cestino e ripristino. Riga A-209.

Guasti nuovi: tre (atto letto in un altro modo, conteggio mancante oltre l'ultima pagina,
memoria svuotata solo dal cestino), tutti visti.

### La terza revisione indipendente

Le correzioni del secondo giro reggono, e nessun nuovo modo di scaricare un documento. Due
rilievi sulle prove, ciascuno con un difetto dietro:

- **La "non trovata" di una scheda conservava la pagina dell'albo fra i risultati**:
  `set_404()` non li toglie, e un modello 404 che li percorre l'avrebbe disegnata. La prova
  confrontava due risposte che avevano lo stesso difetto. Ora i risultati si svuotano, e
  A-196 pretende nessun risultato e niente disegnato da un modello che li percorre.
- **A-198 non distingueva la ricerca nell'oggetto da quella nel testo**: ora ci sono due atti
  con la parola solo nel testo e solo nell'estratto, e non devono comparire.

Guasti nuovi: due (risultati lasciati nella "non trovata", ricerca estesa al testo), visti.

### La quarta revisione indipendente

Le correzioni del terzo giro reggono. Un rilievo: un valore fatto di soli segni, come
`<b></b>`, veniva ripulito prima di essere controllato, diventava un campo vuoto e la pagina
mostrava l'elenco intero invece di dire che la ricerca era scritta male. Ora si controlla il
valore com'è arrivato: tipo, organo, date e pagina non si ripuliscono affatto e devono avere
la loro forma, e l'oggetto è vuoto solo se lo era già prima. Riga A-199, estesa. Guasti
nuovi: due (campi rigidi ripuliti, valore svuotato preso per vuoto), visti.

### La quinta revisione indipendente

La correzione del quarto giro regge. Un rilievo: una data con un carattere nullo dentro
arrivava alla lettura della data, che solleva un'eccezione, e la pagina si interrompeva
invece di dire che la ricerca era scritta male. Ora la data deve avere la forma AAAA-MM-GG
prima di essere letta. Riga A-199, estesa; la prova senza la correzione si ferma con
l'eccezione.

### Il browser vero nei controlli

Dopo l'unità, `bin/prova-nel-browser.sh` porta il motore e la tastiera nei controlli di GitHub,
nel passo "Pagina pubblica in un browser vero". Installa un sito WordPress di prova, attiva il
meccanismo comune e l'albo, pubblica ventuno atti con i passaggi veri
(`tests/browser/prepara-sito.php`), serve il sito con il server di PHP sulla stessa macchina e
lo apre in Chrome con `tests/browser/accessibilita.mjs`: il motore (axe 4.10.3, regole WCAG 2.0
e 2.1 di livello A e AA) su sei pagine, con lo schermo di un telefono e con uno largo, su
Twenty Twenty-Five e sul tema spoglio di prova (`tests/browser/tema-spoglio`), e il percorso da
sola tastiera sul tema spoglio, più due ricerche fatte senza mouse. Nessuna rete esterna
durante la prova: sito, motore e browser sono sulla macchina dei controlli.

Prima di esaminare una pagina si controlla che mostri ciò che deve: gli atti attesi, il
messaggio della ricerca senza risultati, l'errore visibile e legato al campo della ricerca
scritta male, i tre documenti della scheda, che si scaricano. Una pagina vuota o sbagliata
non vale come esaminata.

Il percorso da tastiera preme Tab dall'inizio della pagina e pretende che il fuoco passi per
ogni comando nell'ordine in cui sta, con un indicatore visibile, e che dall'albo si esca in
avanti e all'indietro. L'indicatore si guarda nei pixel: il riquadro del comando fotografato
con il fuoco e senza deve cambiare almeno quanto un filo lungo tutto il perimetro, quindi un
contorno trasparente non passa. Per l'uscita si mettono due pulsanti fuori dall'albo, in cima
e in fondo alla pagina: un browser senza barra degli indirizzi, dopo l'ultimo comando, riporta
da sé il fuoco al primo, e senza un punto d'arrivo fuori un ciclo voluto non si
distinguerebbe. Un campo di data in Chrome si percorre a pezzi, giorno, mese e anno: i Tab di seguito
sullo stesso campo di data contano come uno.

Prima delle pagine vere, motore, percorso e verifica del contenuto girano su pagine guastate
apposta (immagine senza testo alternativo, campo senza etichetta, collegamento senza
indicatore del fuoco, collegamento con contorno e ombra trasparenti, ciclo del fuoco
dall'ultimo comando al primo, scheda senza documenti, ricerca scritta male senza messaggio):
se non vi trovano i guasti, la prova si ferma. Guasti introdotti nell'albo per vedere se la
prova li vede, nove, tutti visti: indicatore del fuoco tolto dal foglio di stile, indicatore
reso trasparente, etichetta tolta dal campo di testo, ordine di tabulazione cambiato sul
pulsante "Cerca", documenti della scheda spariti, messaggio della ricerca scritta male tolto,
spiegazione dell'errore tolta lasciando l'avviso, filtro dell'organo spento, collegamenti agli
atti che non portano da nessuna parte.

Oltre alle pagine aperte per indirizzo, la prova segue da tastiera i collegamenti che le
uniscono: dall'elenco alla seconda pagina, alla scheda, e dalla scheda di nuovo all'elenco. I
comandi della ricerca che ALBO-16 richiede sono un elenco fisso: un filtro spento o nascosto
sparirebbe da un inventario ricavato dalla pagina, e la prova resterebbe verde.

Il sito di prova è sempre nuovo: lo script crea una cartella e una banca dati sue, le toglie
alla fine, e se quelle indicate esistono già si ferma prima di scrivere. Non può quindi
sovrascrivere un sito che c'era.

Due cose imparate mettendola in piedi:

- **Su Twenty Twenty-Five il motore trova una violazione del tema**, sugli schermi larghi: il
  menu della testata mette dentro l'elenco degli elementi che un elenco non può contenere. Non
  tocca l'albo, quindi si annota e non ferma la prova; sul tema spoglio ogni violazione ferma.
- **Con l'albo collegato al sito per collegamento simbolico** WordPress calcola male
  l'indirizzo del foglio di stile, e la pagina esce senza: il percorso da tastiera se ne è
  accorto subito, perché il fuoco non aveva più l'indicatore. Lo script copia l'albo nel sito.
