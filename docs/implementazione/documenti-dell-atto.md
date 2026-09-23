# Documenti dell'atto

Scheda di lavorazione. Scritta prima del codice come piano e aggiornata a lavoro finito:
la sezione in fondo dice com'è andata davvero e cosa è cambiato rispetto al piano. Righe di
collaudo A-77..A-84.

## In tre paragrafi

**Cosa costruiamo.** Un atto porta un **documento principale**, uno e uno solo, e zero o più
**allegati ulteriori**. Nella schermata dell'atto un riquadro **Documenti dell'atto** mostra
quelli già caricati, con il nome, la dimensione e un collegamento per aprirli, e permette di
caricarne di nuovi e di togliere quelli sbagliati finché l'atto è in bozza. Caricare un
documento principale quando ce n'è già uno lo sostituisce, e il vecchio sparisce del tutto:
un documento di una bozza non è mai stato esposto a nessuno, quindi non c'è niente da
conservare. Gli allegati ulteriori restano nell'ordine in cui sono stati caricati, che
servirà a dare loro un nome al momento dello scarico (ALBO-30). L'elenco dei dati mancanti
nomina il documento principale quando manca. I documenti di un atto si governano con i
permessi dell'atto: chi gestisce la libreria dei media del sito non li vede e non li tocca.

**Come la costruiamo.** I file non li scrive l'albo: li deposita il meccanismo comune, nella
sua cartella protetta, dopo aver verificato che il server neghi l'accesso diretto. Se la
verifica non è riuscita il deposito è rifiutato, e il riquadro dice perché. Sull'atto l'albo
tiene due dati: quale allegato è il principale, e l'elenco ordinato degli ulteriori. La
lettura valida il dato letto: vale solo un allegato che esiste, che appartiene a quell'atto,
che il meccanismo comune ha depositato e che non è nel cestino. Il collegamento del riquadro
è l'indirizzo di consegna amministrativo del meccanismo comune, mai il percorso del file.
Caricare e togliere si può solo in bozza; il blocco in verifica e dopo la pubblicazione
arriva con i passaggi di stato. Quando un atto in bozza viene cancellato definitivamente, i
suoi documenti vengono cancellati con lui, file compresi, perché altrimenti resterebbero sul
disco documenti con dati personali che nessuna schermata mostra più. La versione
dell'interfaccia richiesta al meccanismo comune sale a `1.3.0`, quella che porta il deposito.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove depositano file
veri, attraverso il meccanismo comune, con la verifica della protezione fatta rispondere da
un server finto, e poi guardano la banca dati e il disco. Verificano la sostituzione del
principale con la scomparsa del vecchio file, l'ordine degli ulteriori e la loro rimozione,
i rifiuti (contenuto che non è un atto, atto non in bozza, protezione non verificata, tipo
di file non ammesso), che un invio della schermata senza gettone o senza permesso non
deposita niente, che un file indicato con un percorso del server invece che caricato è
rifiutato, che la lettura non si fida dei dati memorizzati, che un utente del sito senza i
permessi dell'albo non può toccare i documenti, e che cancellando una bozza spariscono anche
i suoi file. **Un limite del laboratorio va detto subito**: un caricamento vero dal
navigatore non si riproduce dentro la suite, perché PHP riconosce come caricato solo un file
arrivato con una richiesta HTTP. La prova positiva di quel percorso sta fra le prove sul sito
vero. La tabella dei guasti introdotti di proposito si trova in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-documenti-atto.php` | deposito, sostituzione, rimozione e lettura validata dei documenti, permessi sugli allegati, pulizia alla cancellazione di una bozza, esclusione dalla libreria dei media |
| `includes/class-scheda-documenti.php` | il riquadro, il suo invio con i file e l'avviso dei rifiuti |
| `includes/class-dati-atto.php` | il documento principale fra i dati mancanti |
| `albo-pretorio-pa.php` | versione dell'interfaccia richiesta, nomi dei due dati, agganci |
| `bin/installa-core.sh`, `.wp-env.json` | la revisione del meccanismo comune che porta il deposito |
| `tests/DocumentiAttoTest.php` | le prove A-77..A-84 |
| `tests/AvvioTest.php` | i casi del criterio di compatibilità riportati attorno alla nuova versione |
| `README.md`, `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md`, `docs/implementazione/LEGGIMI.md` | righe nuove, versione dell'interfaccia, colonna "esiste oggi" |

### 2. Perché

ALBO-01 vuole il documento principale per pubblicare, e fino a oggi non poteva esistere: senza
la consegna protetta del meccanismo comune un file caricato risponde al proprio indirizzo
diretto, anche dopo la defissione. Quella consegna è stata unita nel meccanismo comune il
2026-09-23. Questa unità non chiude ALBO-01, che parla di pubblicazione: il passaggio a
pubblicato arriva con il flusso di pubblicazione.

### 3. Il flusso per chi usa il sito

Chi redige ha già scritto i dati dell'atto. Nel riquadro **Documenti dell'atto** sceglie il
file del documento principale e preme "Salva bozza": la pagina si ricarica e il riquadro
mostra il nome del file, la dimensione e il collegamento "Apri". Carica poi due allegati
ulteriori, uno per salvataggio, e li vede elencati nell'ordine. Si accorge che il secondo è
sbagliato, spunta "Togli" accanto a lui e salva: sparisce. Carica di nuovo il documento
principale, corretto: il vecchio sparisce e resta solo il nuovo. Se il server del sito non
nega l'accesso diretto alla cartella dei documenti, ogni caricamento è rifiutato con un
avviso che lo spiega, e il documento non viene scritto.

### 4. Dati letti e scritti

| Dato | Dove | Chi lo scrive |
|---|---|---|
| Documento principale | metadato protetto dell'atto con l'identificativo dell'allegato | il componente, al caricamento dal riquadro o da codice |
| Allegati ulteriori | metadato protetto dell'atto con l'elenco ordinato degli identificativi | come sopra |
| Il file, il suo percorso e la sua impronta | allegato di WordPress figlio dell'atto, depositato dal meccanismo comune | il meccanismo comune |

### 5. Permessi

Caricare e togliere dal riquadro chiede il gettone del riquadro, legato all'atto, e il
permesso di modificare **quell'atto**. Sugli allegati di un atto, modificare, cancellare e
leggere si chiedono con i permessi dell'atto e non con quelli della libreria dei media:
altrimenti chi modifica gli articoli del sito potrebbe cancellare il documento di una
delibera. Nessun permesso nuovo.

### 6. Modi di guasto previsti

- Il vecchio documento principale che resta sul disco o nella banca dati dopo la
  sostituzione. Coperto da A-77.
- L'ordine degli ulteriori perso alla rimozione di uno di loro. Coperto da A-78.
- Un deposito su un atto non in bozza, su un contenuto che non è un atto, o con la
  protezione non verificata. Coperto da A-79.
- Un invio senza gettone, con il gettone di un altro atto o da chi non può modificare l'atto
  che deposita o toglie. Coperto da A-80.
- Un percorso del server fatto passare per un caricamento, che leggerebbe un file qualunque
  del disco. Coperto da A-80.
- Una lettura che si fida dell'identificativo memorizzato, anche se punta all'allegato di un
  altro atto o a un file della libreria pubblica. Coperto da A-81.
- Un utente del sito senza i permessi dell'albo che cancella un documento dalla libreria dei
  media. Coperto da A-82.
- File orfani rimasti sul disco dopo la cancellazione di una bozza. Coperto da A-83.
- Il riquadro che stampa il percorso del file. Coperto da A-84.

### 7. Prove che si aggiungeranno

A-77..A-84 in `tests/DocumentiAttoTest.php`, descritte in `collaudo.md` nella sezione "I
documenti dell'atto".

### 8. Cosa NON si implementa

Il blocco dei documenti in verifica e dopo la pubblicazione (A5, A6). La sostituzione per
oscuramento su un atto pubblicato (A7). Il nome del file allo scarico (ALBO-30, A20). L'avviso
sui PDF che sembrano scansioni (ALBO-13, A9). Un elenco proprio dei formati ammessi: valgono
quelli che l'installazione di WordPress ammette, che il meccanismo comune controlla. Il
caricamento di più file in un solo invio.

### 9. Come si prova sul sito vero

Da chi redige, su un atto in bozza: si carica un PDF come documento principale, si salva, si
apre il collegamento e si verifica che il documento sia quello. Si copia l'indirizzo e lo si
apre in una finestra anonima: non deve rispondere, perché l'atto è in bozza. Si carica un
secondo PDF come principale e si verifica che il primo non sia più elencato. Nella libreria
dei media, i due file non devono comparire.

## Com'è andata davvero

Il piano ha retto nelle sue linee. Le differenze sono cinque, e tre sono più severe del
piano.

**Cancellare un documento per la via generica non è concesso a nessuno.** Il piano diceva che
cancellare un documento si chiede con i permessi dell'atto. Ma chi può cancellare l'atto può
cancellare anche un atto pubblicato, e con quella regola avrebbe potuto togliere il suo
documento dalla libreria o dall'interfaccia per programmi, senza passare dal controllo dello
stato. Ora la domanda "può cancellare questo documento?" risponde no a chiunque, amministratore
compreso: un documento si toglie dal riquadro, che controlla che l'atto sia in bozza e
aggiorna i suoi dati. Le funzioni interne che cancellano non passano da quella domanda.
Modificare e leggere seguono i permessi dell'atto, come previsto.

**La pulizia alla cancellazione guarda i file, non i due dati.** Quando un atto mai
pubblicato viene cancellato, si cancellano tutti i suoi allegati depositati dal meccanismo
comune, anche quelli che i due dati dell'atto non indicano più per un guasto o una scrittura
di lato. Un allegato della libreria normale attaccato all'atto non si tocca, perché il suo
file può essere usato altrove. "Mai pubblicato" vuol dire in bozza o in revisione, e per un
atto nel cestino vale lo stato che aveva prima: la riga A-83 prova anche questo percorso.

**I due dati sono dichiarati a WordPress**, protetti e fuori dall'interfaccia per programmi,
come i dati dell'atto. Non era scritto nel piano, e le prove lo hanno fatto notare: vedi la
tabella dei guasti. La riga A-81 ora lo verifica.

**Le prove dei dati dell'atto depositano un documento vero.** Con il documento principale fra
i dati mancanti, l'atto completo delle prove A-69..A-76 deve averne uno. Il server finto, i
file temporanei e il controllo del disco stanno ora in un aiuto comune alle due classi di
prove, e la riga A-75 nomina anche il documento mancante.

**Il tipo di un altro componente non si governa.** Come per il resto del componente, ogni
aggancio di questa unità controlla che il tipo atto registrato sia il nostro, e se non lo è
non fa niente. Questo caso non ha una prova dedicata in questa unità: il comportamento del
componente inerte è provato dalle righe A-28 e A-29 per le parti che esistevano allora.

**La prova di non vacuità.** Trentacinque guasti introdotti uno per volta in una copia usa e
getta, facendo girare le prove di questa unità e quelle dei dati dell'atto su ciascuno.

| Guasto introdotto | Prove cadute |
|---|---|
| Il vecchio principale non cancellato alla sostituzione | A-77 |
| Il documento tolto dai dati ma non cancellato | A-78, A-80 |
| L'ordine degli ulteriori perso alla rimozione | A-78 |
| L'ulteriore nuovo aggiunto in testa invece che in fondo | A-78, A-83, A-84 |
| Gli ulteriori riordinati per numero invece che per deposito | A-81 |
| Lo stato dell'atto non controllato | A-79 |
| L'atto in revisione ancora modificabile | A-79 |
| Il tipo del contenuto non controllato | A-79 |
| Il ruolo del documento non controllato | A-79 |
| Il gettone del riquadro non controllato | A-80 |
| Il gettone del riquadro non legato all'atto | A-80 |
| Il permesso sull'atto non controllato al salvataggio | A-80 |
| Il file della schermata dichiarato "percorso locale" invece che "caricamento" | A-80 |
| Il principale letto senza validazione | A-81 |
| Due righe di principale accettate | A-81 |
| Gli ulteriori letti senza validazione | A-81 |
| Un ulteriore ripetuto accettato due volte | A-81 |
| Un allegato non depositato dal meccanismo comune accettato | A-81 |
| L'allegato di un altro atto accettato | A-81 |
| Un allegato nel cestino accettato | A-81 |
| I permessi dell'atto non applicati ai suoi documenti | A-82 |
| La cancellazione concessa con i permessi dell'atto | A-82 |
| La libreria dei media a griglia non esclusa | A-82 |
| La libreria dei media a elenco non esclusa | A-82 |
| L'esclusione applicata a ogni interrogazione degli allegati | A-82, dopo una correzione della prova: vedi sotto |
| La pulizia alla cancellazione tolta | A-83 |
| Lo stato di prima del cestino non letto | A-83 |
| La pulizia che non guarda di quale atto sono i documenti | A-83 |
| Il riquadro che stampa il percorso del file | A-84 |
| Il collegamento pubblico al posto di quello amministrativo | A-84 |
| Il modulo della schermata senza l'invio di file | A-84 |
| Il riquadro senza controllo del permesso | A-84 |
| Il documento principale non fra i dati mancanti | A-75, A-77, A-78, A-81 |
| I due dati esposti ai programmi | A-81, dopo l'aggiunta della prova: vedi sotto |
| I due dati non protetti | A-81, dopo l'aggiunta della prova: vedi sotto |

**Una prova che non provava.** Al primo giro l'esclusione applicata a ogni interrogazione degli
allegati non ha fatto cadere niente. Il controllo positivo chiedeva gli allegati con
`get_posts()`, che spegne i filtri delle interrogazioni: il controllo sarebbe passato anche
con l'esclusione ovunque. Ora chiede con `WP_Query`, come fa il resto del sito, e il guasto
cade.

**Due guasti senza prova.** Al primo giro i due dati esposti ai programmi, o scritti senza il
trattino basso che li protegge, non hanno fatto cadere niente, perché nessuna prova lo
guardava. La riga A-81 ora legge le dichiarazioni prima di tutto il resto, e i due guasti
cadono.

**Il limite che resta.** Un caricamento vero dal navigatore non si riproduce dentro la suite,
perché PHP riconosce come caricato solo un file arrivato con una richiesta HTTP. La prova
positiva di quel percorso sta fra le prove sul sito vero, punto 9 qui sopra; qui si prova
che un percorso del server fatto passare per caricamento è rifiutato.
