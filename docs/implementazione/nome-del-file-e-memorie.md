# Il nome del file scaricato e le memorie di pagina

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisiti ALBO-30 e ALBO-20 (in parte: l'oscuramento a pubblicazione attiva resta con ALBO-12), righe di collaudo
A-257..A-259. Richiede il meccanismo comune con l'interfaccia `1.8.0`. Com'è andata davvero è
in fondo.

## In tre paragrafi

**Cosa costruiamo.** Un documento scaricato dall'albo può girare da solo, lontano dall'albo,
senza più niente che dica che era un atto pubblicato, quando e fino a quando: è il rischio che
il Garante chiama decontestualizzazione (delib. 243/2014, par. 2.d). Il documento non si tocca,
perché scriverci sopra romperebbe la firma digitale. Si cambia solo il nome con cui il browser
lo salva: `albo-2026-0007-dal-01-10-al-16-10.pdf`, con numero di registro e date di
pubblicazione, e `-allegato-1`, `-allegato-2` per gli allegati. Nel nome non entrano né
l'oggetto dell'atto né il nome originale del file, che possono contenere dati personali. La
seconda cosa riguarda le memorie di pagina (cache): un atto tolto dall'albo non deve restare
servito da una copia conservata da qualche parte.

**Come la costruiamo.** Il nome lo compone il punto di consegna del meccanismo comune, che dalla
versione `1.8.0` dell'interfaccia accetta dal tipo una funzione `nome_scaricato` e ne controlla
il risultato: solo lettere minuscole, cifre, punto, trattino e trattino basso, con l'estensione
vera del file; se la funzione non risponde o risponde storto, il file esce come
`documento.pdf`, mai con il nome che aveva sul disco. L'albo dichiara `NomeScaricato::nome()`,
che legge a ogni consegna anno e numero dal registro, il primo giorno dalla data di
pubblicazione dell'atto e l'ultimo dalla data di fine, proroga e rimozione anticipata comprese.
Per le memorie la strada scelta è l'esclusione, non lo svuotamento: ogni risposta dell'albo
(elenco, scheda, ricerca, "non trovata", rinvio dei vecchi indirizzi, documento) dice già che
non va conservata, quindi alla scadenza non c'è niente da svuotare. L'unico ritocco al codice è
il rinvio dei vecchi indirizzi, che manda le stesse intestazioni per una via che le prove
vedono.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove scaricano dal punto
pubblico di consegna come un visitatore, con atti pubblicati dal passaggio vero, e leggono il
nome dalla risposta e il file dal disco. La prova delle memorie usa una memoria simulata che
conserva una risposta solo se l'intestazione `Cache-Control` non dice `no-store`, come fanno le
memorie condivise e quelle dei browser; il controllo di realtà è che la stessa memoria conservi
una pagina ordinaria del sito. A lavoro finito si introducono guasti nel codice, uno per volta,
e si guarda quale prova li vede.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-nome-scaricato.php` | il nome del documento: radice con numero e date, ordine dell'allegato, estensione |
| `includes/class-tipo-atto.php` | la dichiarazione `nome_scaricato` al meccanismo comune |
| `includes/class-pagina-pubblica.php` | le intestazioni del rinvio dei vecchi indirizzi per una via osservabile |
| `albo-pretorio-pa.php` | caricamento della classe, interfaccia richiesta `1.8.0` |
| `bin/installa-core.sh`, `.wp-env.json` | la revisione del meccanismo comune con l'interfaccia `1.8.0` |
| `tests/NomeScaricatoTest.php`, `tests/PaginaPubblicaTest.php` | le prove |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, `README.md` | righe e stati |

### 2. Perché

ALBO-30, deciso il 2026-09-23: il nome come unica misura contro la decontestualizzazione che
non tocca il documento firmato. ALBO-20: un atto tolto non viene più servito a nessuno, con la
scelta fra esclusione e svuotamento lasciata a noi.

### 3. Il flusso per chi usa il sito

Nessuna schermata e nessuna impostazione nuova. Il cittadino apre la scheda di un atto e
scarica un documento: il browser propone `albo-2026-0007-dal-01-10-al-16-10.pdf`. Chi lavora
negli uffici e scarica dalla consultazione riservata vede lo stesso nome. Il file sul server
resta com'era.

### 4. Dati letti e scritti

Nessun dato scritto. Letti a ogni consegna: il numero di registro (`Repertorio::di()`), la data
di pubblicazione dell'atto, la data di fine del meccanismo comune, il documento principale e
l'elenco degli allegati, l'estensione del file conservato.

### 5. Permessi

Nessun permesso nuovo. Il nome si compone solo dopo che il punto di consegna ha deciso di
consegnare il file, con i controlli di sempre.

### 6. Modi di guasto previsti

- **Fuso orario**: con il sito lontano dal tempo universale, un atto pubblicato la sera del 31
  dicembre avrebbe anno e giorno sbagliati se letti nel tempo universale. Si leggono quelli del
  sito.
- **Numero lungo**: quattro cifre sono il minimo, non il massimo: `12345` non si tronca.
- **Atto senza numero** (in preparazione, o registro che non risponde): `albo-senza-numero`,
  senza date, perché la data di un atto in preparazione non è una data di pubblicazione.
- **Documento che non è più dell'atto**: nessun nome; il meccanismo comune usa quello neutro.
- **Data di fine scritta di lato**: se non si rilegge uguale, il nome resta senza l'ultimo
  giorno invece di indovinarlo.
- **Memoria che ignora le intestazioni**: è fuori dal controllo del componente, ed è un limite
  dichiarato. Le estensioni di memoria più diffuse leggono anche `DONOTCACHEPAGE` (A-202).
- **Intestazioni vere non osservate dalle prove**: in PHPUnit da riga di comando `header()`
  non lascia traccia, quindi le prove guardano le righe che passano all'emettitore delle
  prove e le intestazioni che WordPress passa a `wp_headers` durante la risposta. Le righe
  sono costruite una volta sola (`senza_memoria()`), e cambia solo dove vanno; la chiamata
  finale a `header()` resta fuori dalle prove. È un limite dichiarato.

### 7. Prove aggiunte

A-257 e A-258 in `tests/NomeScaricatoTest.php`, A-259 in `tests/PaginaPubblicaTest.php`.

### 8. Cosa NON si implementa

- **La sostituzione per oscuramento** (ALBO-12) non esiste ancora: le parti delle righe di
  ALBO-30 e ALBO-20 che la riguardano si provano con lei. Con l'esclusione la versione uscente
  non entra in nessuna memoria, e il nome non dipende dall'istante della sostituzione.
- **Lo svuotamento delle memorie alla scadenza**: non serve, perché niente viene conservato.

### 9. Come si prova su un'installazione reale

Aprire la scheda di un atto pubblicato e scaricarne il documento principale e un allegato:
controllare i nomi proposti dal browser. Aprire gli strumenti per sviluppatori del browser,
scheda Rete, ricaricare la pagina dell'albo e controllare che la risposta porti
`Cache-Control` con `no-store`. Se il sito usa un'estensione di memoria di pagina, togliere un
atto dall'albo e riaprirne la scheda da una finestra anonima: deve rispondere "pagina non
trovata".

## Com'è andata davvero

- Le intestazioni che vietano di conservare le risposte c'erano già su tutte le strade
  dell'albo e del punto di consegna. La prova le guarda tutte insieme, con un atto tolto e uno
  scaduto, invece di fidarsi delle prove sparse.
- Il rinvio dei vecchi indirizzi chiamava `nocache_headers()`, che in una prova non manda
  niente perché l'uscita è già cominciata: un rinvio senza divieto passava inosservato. Ora con
  l'emettitore delle prove le righe vanno a lui, e il guasto si vede.
- La rilettura ha trovato che la prova girava su una versione di WordPress che dice `no-store`
  a tutti. Dalla 6.3, versione minima compresa, WordPress lo dice solo a chi ha fatto
  l'accesso; al visitatore anonimo manda solo `no-cache`, che lascia conservare la risposta.
  Le risposte dell'albo (elenco, scheda, ricerca, "non trovata", rinvio dei vecchi indirizzi)
  ora scrivono da sé `Cache-Control` con `no-store` (`PaginaPubblica::senza_memoria()`), e la
  prova riproduce le intestazioni che WordPress manda all'anonimo. Anche la "non trovata"
  decisa dopo le intestazioni (l'atto che scade fra la lettura e il rinvio) rimandava
  `nocache_headers()` di WordPress, che toglieva `no-store` gia' mandato: ora rimanda il
  divieto dell'albo.
- Il secondo giro di revisione ha trovato che il rinvio e la "non trovata" tardiva
  costruivano le intestazioni in due modi: con l'emettitore delle prove da `senza_memoria()`,
  senza da `nocache_headers()` più una riga a parte. Togliere quella riga lasciava le prove
  verdi. Ora c'è una sola costruzione per le due strade, e `Last-Modified` si toglie come fa
  WordPress. La prova delle memorie conserva le intestazioni che WordPress ha mandato durante
  la risposta, non un ricalcolo fatto dopo, e A-222 riproduce l'anonimo di WordPress 6.5.

Guasti introdotti di proposito, uno per volta, e prova che li ha visti:

| Guasto | Prova caduta |
|---|---|
| nome non dichiarato al meccanismo comune | A-257, A-258 |
| numero senza zeri davanti | A-257 |
| numero troncato a quattro cifre | A-258 |
| primo giorno nel tempo universale | A-258 |
| anno nel tempo universale | A-258 |
| allegati contati da zero | A-257 |
| data di fine non riletta | A-258 |
| documento estraneo con un nome | A-258 |
| oggetto dell'atto nel nome | A-257, A-258 |
| atto senza numero con le date | A-258 |
| pagina dell'albo conservabile | A-259 |
| rinvio senza divieto | A-259 |
| rinvio con il divieto senza `no-store` | A-259 |
| intestazioni di WordPress per l'anonimo senza `no-store` | A-259 |
| pagina dell'albo con le intestazioni di WordPress | A-259 |
| rinvio con le intestazioni di WordPress | A-259 |
| "non trovata" decisa dopo le intestazioni con quelle di WordPress | A-222 |
| divieto dell'albo senza `no-store` | A-222, A-259 |
| rinvio e "non trovata" tardiva con le intestazioni di WordPress | A-222, A-259 |
