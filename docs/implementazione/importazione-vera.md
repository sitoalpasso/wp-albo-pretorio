# Importazione vera dal plugin "Albo Pretorio On line"

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, in attesa di revisione.** Requisito ALBO-35, righe di collaudo
A-210..A-217. Segue l'importazione a secco (`importazione-a-secco.md`), di cui riusa la
lettura del backup e il rapporto.

## In tre paragrafi

**Cosa costruiamo.** Il passo che, dopo il rapporto a secco, scrive davvero nel nuovo albo
gli atti del vecchio: oggetto, numero di registro e anno com'erano, date di inizio e di fine
com'erano, tipo di atto, numero proprio, dati del procedimento e documenti, copiati byte per
byte. Un atto la cui fine è passata arriva pubblicato ma invisibile, come ogni scaduto, e si
consulta dalla schermata riservata; un atto ancora in pubblicazione arriva visibile fino a
tutto il suo giorno di fine, solo se chi importa ha dato la conferma sui dati personali.
Si portano soltanto gli atti che il rapporto dice portabili, più quelli con un dubbio che
non si può risolvere prima e che quindi si porta con sé (elenco sotto). Alla fine un
rapporto dice quanti atti sono stati importati su quanti del backup, e perché gli altri no.

**Come la costruiamo.** Dalla schermata dell'importazione: si sceglie un backup dalla
cartella del vecchio albo, si legge il rapporto a secco, e sotto il rapporto c'è il modulo
"Importa" con il numero di atti che si porterebbero e una casella di conferma esplicita
(più una seconda casella, separata, per gli atti che arriverebbero visibili). L'invio
rilegge il backup, rifà il rapporto e lo confronta con quello confermato: se è cambiato
qualcosa (il file, o il numero di atti portabili) non parte. Poi l'importazione va **a
blocchi**: ogni richiesta rilegge il backup, rifà il rapporto e porta gli atti successivi
finché non ha usato il suo tempo, e la pagina offre "Continua". Ogni atto importato ricorda
il suo identificativo nel vecchio albo, quindi rilanciare non crea doppioni: un atto già
importato il rapporto lo dice "già importato" e non lo tocca più. Ogni atto si scrive **tutto
o niente**: si prepara in bozza con dati e documenti, poi un passaggio dell'albo dedicato lo
porta a pubblicato con numero, date, fine e voce di registro dentro una transazione; se una
parte non riesce la bozza si cancella con i suoi file e l'atto risulta "non riuscito", gli
altri proseguono. Un'importazione alla volta: finché una è in corso non se ne avvia un'altra,
e due "Continua" insieme non lavorano mai contemporaneamente.

**Come si prova che funziona, e come si prova che il test è vero.** Un backup di prova con
uno scaduto, uno in corso, uno con due allegati e nessun documento, uno con un file solo
segnato come allegato, un annullato, uno di durata più corta della categoria, più gli atti che non si portano (conflitto, bozza, tipo da
creare, file mancante, senza documenti, inizio futuro, annullato ancora in pubblicazione): dopo l'importazione esistono solo i
primi, con i valori scritti per esteso nelle prove (numero, date, impronta dei file uguale a
quella dell'archivio), lo scaduto non si vede e quello in corso sì. Rilanciata, non crea
niente. Con un guasto iniettato nel passaggio di pubblicazione dell'atto, quell'atto non
esiste, il suo numero è libero e i suoi file non ci sono più, e l'atto dopo si importa lo
stesso. Mutazioni provate: togliendo il ricordo dell'identificativo di origine la riga dei
doppioni diventa rossa; scrivendo la riga dell'anno nella numerazione la riga della
numerazione diventa rossa; togliendo la casella separata la riga degli atti visibili
diventa rossa.

## Quali atti si portano

Un atto si porta se il rapporto lo dice **importabile**, oppure **da decidere** con soli
dubbi di questo elenco, che l'importazione porta con sé e scrive nella voce di registro:

| Dubbio | Perché si porta lo stesso |
|---|---|
| nessun allegato segnato come documento, o più di uno (`principale_assente`, `principali_multipli`) | decisione del coordinamento (20 ottobre 2026): non si indovina. Tutti i file arrivano come allegati e l'atto è segnalato "documento principale da scegliere"; la scelta resta all'ente, in un secondo momento |
| durata più corta o più lunga della categoria (`durata_breve`, `durata_lunga`) | le date sono fatti avvenuti: nessuna decisione presa adesso le cambia. Si conservano e la voce di registro porta il dubbio |
| anno del numero diverso dall'anno di inizio (`anno_diverso`) | il numero si conserva com'è (già deciso a secco) |
| responsabile o ufficio citati e assenti dal backup | non c'è niente da portare: il dato manca già nel vecchio albo |
| tipo da decidere (`tipo_da_decidere`) | solo se il tipo di atto con quel nome esiste nel nuovo albo: l'ente l'ha deciso creandolo. Le differenze di giorni fra categoria e tipo non toccano gli importati, che conservano le loro date |
| annullato (`annullato`) | solo se la pubblicazione è finita: l'annullamento con data e motivo va nella voce di registro. Un annullato ancora in pubblicazione non si porta, perché cosa ne veda il pubblico è una decisione ancora aperta |
| in pubblicazione, manca la conferma sui dati personali (`conferma_dati_personali`) | solo se chi importa spunta la casella apposita, separata dalla conferma dell'importazione; la voce di registro la riporta |

Non si porta, e il rapporto finale lo dice con il motivo: ogni conflitto; la bozza; un atto
senza nessun documento (il documento principale è obbligatorio, ALBO-01); e ogni altro
dubbio, perché è un dato che non arriverebbe intero o una decisione che spetta all'ente
prima: tipo da creare, file mancante o diverso da quello caricato, documenti non
verificabili (backup senza archivio), pubblicazione per estratto, testo non leggibile o non
conservabile alla lettera, altra amministrazione, inizio futuro, fine assente. Un atto non
portato oggi si porta a un rilancio, quando la causa è risolta.

**Il documento principale.** Con un file solo, quel file (già deciso). Con un solo file
segnato documento, quello. Negli altri casi nessuno: tutti allegati, segnalato.

## Cosa si scrive per ogni atto

- **L'atto**, pubblicato, con oggetto, autore chi importa, data di pubblicazione il giorno
  di inizio a mezzanotte nel fuso del sito, fine della pubblicazione il giorno di fine (dal
  meccanismo comune, come ogni atto: da lì in poi vale la scadenza del nuovo albo).
- **Il numero di registro e l'anno** del vecchio albo, nella tabella delle assegnazioni.
  **La riga dell'anno non si tocca**: contatore e partenza restano quelli dichiarati
  (ALBO-08). Un anno passato che avesse una riga farebbe credere al nuovo albo di aver già
  tenuto la numerazione, e l'anno nuovo ripartirebbe da 1 da solo: è proprio la "numerazione
  che riparte da zero" che non si vuole. Il numero si scrive solo se non supera la partenza
  dichiarata per quell'anno, controllata dentro la transazione; e da qui in poi la
  dichiarazione della partenza rifiuta un valore più basso di un numero già importato.
- **Il tipo di atto** con il nome della categoria; nessun organo e nessuna data di adozione
  (il vecchio albo non li ha).
- **Il numero proprio** dal riferimento, già controllato a secco.
- **Responsabile, ufficio e richiedente**, conservati sempre e mostrati secondo la scelta
  dell'amministrazione (ALBO-33).
- **I documenti**, letti dall'archivio un file alla volta in un file temporaneo,
  controllati con l'impronta letta a secco, depositati dal meccanismo comune come
  "percorso locale" e ricontrollati con l'impronta che il meccanismo comune calcola al
  deposito. Nome e contenuto non si toccano.
- **Il ricordo dell'origine**: l'identificativo dell'atto nel vecchio albo, in un dato
  dell'atto.
- **La voce di registro dell'importazione**, con numero, date, durata e **origine della
  durata: "date scritte nel vecchio albo"**, identificativo d'origine, annullamento, dubbi
  portati, conferma sui dati personali se data, file con la loro impronta.

## Cosa non fa

- **Non legge i PDF da una cartella del server**: solo dall'archivio zip del backup. Il
  backup del solo file SQL non porta nessun atto (tutti hanno il dubbio dei documenti non
  verificabili). La lettura da cartella è un passo a sé, quando la cartella arriva.
- **Non importa da un file caricato**: solo da un backup nella cartella del vecchio albo,
  perché i blocchi successivi devono rileggere lo stesso file e un caricamento sparisce alla
  fine della richiesta.
- **Non dà ancora il modo di scegliere il documento principale** sugli atti importati senza:
  un atto pubblicato non si modifica, e la scelta a posteriori è una funzione da costruire.
- **Non crea i tipi di atto**: si creano dalla loro schermata, con durata e origine
  dichiarate da chi importa, e poi si rilancia.
- **Non porta le altre persone collegate, le note e le etichette**: il nuovo albo non ha
  dove metterle (già detto a secco).
- **Non lavora da solo in sottofondo**: ogni blocco parte da un "Continua" di chi importa.

## Permessi, sicurezza, modi di guasto

- Stessa schermata, stessi due permessi della lettura a secco (pubblicare e consultare gli
  scaduti), gettone di sicurezza su avvio, continua e interruzione.
- Un'importazione alla volta: lo stato sta in un'opzione del sito; ogni blocco prende un
  turno nella banca dati (`GET_LOCK`) senza attendere, e il secondo che arriva si ritira
  dicendolo. "Interrompi" chiude un'importazione lasciata a metà; gli atti già importati
  restano.
- Un blocco interrotto a metà (tempo scaduto, errore fatale) può lasciare una bozza
  preparata e non pubblicata. Prima che la bozza nasca si salva nello stato
  dell'importazione un segno casuale; la bozza nasce con un nome nell'indirizzo che contiene
  quel segno, scritto insieme alla bozza stessa, e poco dopo riceve anche il ricordo
  dell'origine. Il rilancio la ritrova dall'uno o dall'altro e la cancella con i suoi file
  prima di rifarla, anche se il blocco si è fermato prima del ricordo. La cancellazione va
  in ordine: prima l'elenco dei file dei documenti, scritto sulla bozza; poi i documenti,
  verificando che non ci siano più; poi i file, verificando che non siano più sul disco
  (solo dentro la cartella protetta); per ultima la bozza. Se un passo non riesce l'esito
  è incerto, la bozza resta con l'elenco, e il rilancio riprende da lì: nessun documento e
  nessun file resta senza qualcuno che lo ritrovi. Una bozza scritta da
  una persona non ha né il ricordo né un segno salvato, e non si tocca mai, qualunque nome
  abbia. I segni delle bozze rimaste incerte passano all'importazione successiva.
- Il passaggio a pubblicato senza verifica si concede soltanto alla bozza che l'importazione
  sta scrivendo in quel momento, a chi ha i permessi dell'importazione, e solo se l'atto che
  arriva visibile ha la conferma sui dati personali. Una bozza qualunque, anche con il
  ricordo dell'origine scritto a mano, non passa.
- Prima di scrivere si controlla che tutte le tabelle toccate annullino, comprese le due
  della numerazione: altrimenti un guasto dopo la scrittura del numero lo lascerebbe
  occupato senza atto.
- Ogni atto ha uno di tre esiti: **confermato** (pubblicato con il suo numero, anche se un
  altro componente solleva un'eccezione subito dopo la conferma), **annullato** (la bozza è
  cancellata e si è verificato che non c'è più) o **incerto** (la bozza non si cancella, o il
  numero risulta senza l'atto pubblicato). Ogni uscita passa da questa distinzione: anche un
  dato della bozza che non si scrive, e una bozza lasciata da prima che non si toglie. Un
  esito incerto ferma il blocco su quell'atto e lo dice; si guarda l'atto prima di
  continuare.
- Se la richiesta si ferma fra la conferma di un atto e il salvataggio dello stato,
  "Continua" ritrova l'atto fra quelli importati da questa importazione e ne ricostruisce
  l'esito dalla voce di registro, compreso il documento principale da scegliere. Se la banca
  dati non salva lo stato, il blocco si ferma e lo dice; lo stesso vale per l'avvio e per
  "Interrompi", che non si dichiarano riusciti se lo stato non si è salvato.
- La dichiarazione della partenza e l'importazione di un numero dello stesso anno non si
  scavalcano: entrambe prendono lo stesso blocco dell'anno nella banca dati (`GET_LOCK`,
  attesa di pochi secondi) e lo tengono fino alla fine della scrittura, transazione
  compresa. Vale qualunque sia l'isolamento della banca dati e anche quando la riga
  dell'anno manca. In più, la condizione "nessun numero importato oltre la partenza" sta
  dentro la scrittura della dichiarazione. Se la connessione è dentro una transazione aperta
  da altri, le scritture diventano definitive solo alla sua chiusura: allora il blocco
  dell'anno non si lascia, e lo lascia la banca dati alla fine della connessione.
- L'importazione non comincia, e non prosegue, se la connessione alla banca dati è dentro una
  transazione aperta da un altro componente (scrittura automatica spenta, o transazione già
  aperta dove la banca dati lo dice): bozze, documenti e numeri non sarebbero definitivi, e
  un annullamento esterno lascerebbe i file sul disco. Se la domanda "c'è una transazione
  aperta?" non ha risposta, vale come sì: l'unica eccezione è la banca dati che dichiara di
  non conoscere la variabile (MySQL non la ha; MariaDB sì). Su MySQL, quindi, una
  transazione aperta a mano con la scrittura automatica accesa non si vede: è un limite
  dichiarato, che richiede un componente insolito.
- Alla ripresa, se il registro non si legge, l'esito dell'atto già confermato non si
  ricostruisce: il blocco si ferma su quell'atto senza avanzare, e "Continua" riprova.
- La prima versione della bozza si salva solo se le revisioni sono attive per quella bozza:
  con le revisioni spente nella configurazione del sito l'importazione funziona lo stesso.
- Il backup si riconosce dalla sua impronta: se fra un blocco e l'altro il file cambia,
  l'importazione si ferma e lo dice.
- Ogni blocco si ferma dopo un tempo fisso o un numero di atti, il primo che arriva, e porta
  sempre almeno un atto.

## File toccati

- `includes/class-importazione.php`: avvio, blocchi, scrittura di un atto, rapporto finale.
- `includes/class-passaggi.php`: `importa()`, il passaggio da bozza a pubblicato dell'atto
  importato, con le stesse difese della pubblicazione.
- `includes/class-repertorio.php`: `registra_importato()`; la dichiarazione della partenza
  non scende sotto un numero importato.
- `includes/class-analisi-importazione.php`: i dati di scrittura di ciascun atto, gli atti
  già importati.
- `includes/class-schermata-importazione.php`: il modulo di conferma, l'avanzamento, il
  rapporto finale.
- `includes/class-chiusura-pubblicazione.php`: il guardiano concede la scrittura dello stato
  anche alla bozza, ma solo durante il passaggio di importazione e solo con il suo gettone.
- `tests/ImportazioneVeraTest.php`, `docs/collaudo.md`, `docs/requisiti.md`, `docs/dati.md`,
  questa scheda e l'indice delle schede.

## Com'è andata

- Il guardiano della pubblicazione concedeva la scrittura dello stato solo a un atto in
  verifica: l'atto importato parte da una bozza, e la concessione si è estesa a quel solo
  passaggio.
- Il primo aggiornamento di una bozza crea la sua prima versione in WordPress, e il controllo
  "niente cambia oltre lo stato" la vedeva come un contenuto nuovo. La bozza preparata
  dall'importazione salva la sua prima versione prima del passaggio, come succede a un atto
  scritto a mano quando va in verifica.
- La riga A-215 ha trovato due difetti, corretti: un'eccezione durante la preparazione della
  bozza la lasciava a metà (ora si cancella subito; il rilancio l'avrebbe comunque tolta), e un
  rifiuto della banca dati nella scrittura del numero si leggeva come "numero già dato".
- Le tre mutazioni della scheda rendono rosse le loro righe.
- La prima revisione esterna ha chiesto sette correzioni, fatte: il passaggio di importazione
  era pubblico e bastava una bozza qualunque (ora solo la bozza in lavorazione, con i
  permessi e la conferma sui dati personali); il controllo delle tabelle che annullano non
  comprendeva quelle della numerazione; la prima dichiarazione di un anno senza riga poteva
  passare in mezzo a un'importazione; una bozza fermata prima del ricordo dell'origine non
  si ritrovava; un'eccezione dopo la conferma faceva dire "non riuscito" a un atto
  importato, e una cancellazione non riuscita non fermava niente; la ripresa dopo una
  fermata fra atto e stato perdeva l'atto dal conteggio. Le prove coprono ora ciascun caso,
  e le mutazioni corrispondenti (togliere la ripresa, la distinzione degli esiti, la
  concessione legata alla bozza, la conferma, il controllo delle tabelle, la condizione
  nella dichiarazione, la fermata sull'incerto, il nome della bozza, lo sfuggire dei
  messaggi, la scrittura dei dati del procedimento) rendono rossa la loro riga.
- La seconda revisione esterna ha chiesto quattro correzioni, fatte: la bozza si riconosceva
  anche dal solo nome, che una persona può scrivere (ora un segno casuale salvato prima);
  un errore nel completare la bozza e una bozza lasciata che non si toglieva non davano
  l'esito incerto; con le revisioni spente nessun atto si importava; con l'isolamento
  "read committed" della banca dati dichiarazione e importazione potevano confermarsi
  insieme (ora un blocco dell'anno comune). Ciascun caso ha la sua prova, e la mutazione
  corrispondente la rende rossa.
- La terza revisione esterna ha chiesto tre correzioni, fatte: dentro la transazione di un
  altro componente il blocco dell'anno si lasciava prima della conferma vera, e
  l'importazione lavorava lo stesso (ora non comincia, e la dichiarazione tiene il blocco);
  una lettura del registro fallita alla ripresa contava fra i non riusciti un atto
  importato (ora il blocco si ferma e riprova); le prove non avevano responsabile e ufficio
  compilati (ora sì, conservati anche se l'amministrazione non li mostra). Le prove girano
  dentro la transazione della suite, che annullano alla fine: lì l'importazione è ammessa da
  un valore che solo le prove scrivono, e una riga apposta prova il rifiuto.
- La quarta revisione esterna ha chiesto tre correzioni, fatte: la cancellazione di una
  bozza guardava solo la bozza, e un documento o un file che non se ne andava restava senza
  nessuno che lo ritrovasse (ora l'ordine descritto sopra, con l'elenco dei file scritto
  prima); una lettura fallita di "c'è una transazione aperta?" valeva come no (ora come
  sì); avvio e interruzione non controllavano il salvataggio dello stato (ora sì). Ogni
  caso ha la sua prova, e la mutazione corrispondente la rende rossa. Nelle prove, il
  blocco dell'anno preso dentro la transazione della suite si rilascia alla fine di ogni
  prova, come alla fine di una richiesta vera.
- La riga A-193 dell'importazione a secco fallisce in questo ambiente di prova anche sul ramo
  principale, per la versione di `iconv`: non dipende da questo lavoro.

## Come si prova su un sito vero

Sulla copia di prova: creare i tipi di atto con i nomi delle categorie del vecchio albo,
dichiarare la partenza dell'anno in corso, copiare il backup zip nella cartella del vecchio
albo, leggere il rapporto e importare. Poi confrontare a campione numero, date e documento
di qualche atto con il vecchio albo, aprire un importato scaduto dalla schermata degli atti
scaduti, controllare che sul sito pubblico ci siano solo gli atti in corso, e rilanciare per
vedere che non si crea niente di nuovo.
