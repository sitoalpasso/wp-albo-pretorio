# Passaggio in verifica

Scheda di lavorazione. Scritta prima del codice come piano e aggiornata a lavoro finito:
la sezione in fondo dice com'è andata davvero e cosa è cambiato rispetto al piano. Righe di
collaudo A-85..A-92.

## In tre paragrafi

**Cosa costruiamo.** La prima parte del flusso di pubblicazione deciso con ALBO-22. Una bozza
si crea e si risalva liberamente. Passa **in verifica** solo quando ha tutti i dati che la
pubblicazione pretende: oggetto, tipo di atto con una durata valida, organo, data di adozione
e documento principale. Se ne manca uno resta in bozza, e l'avviso nomina quello che manca.
Un atto in verifica **non si modifica più**, da nessuno: né l'oggetto, né i dati, né i
documenti, né lo stato, e non si cestina. Il passaggio da bozza a pubblicato, cioè saltare la
verifica, resta vietato da ogni ingresso. Questa unità **non** apre la pubblicazione e non
costruisce il ritorno in bozza: tutti e due scrivono una motivazione nel registro delle
modifiche del meccanismo comune, che non esiste ancora, e la pubblicazione ha bisogno anche del
blocco dei motori di ricerca. Arrivano con la seconda parte.

**Come la costruiamo.** Lo sbarramento che oggi tiene chiusa la pubblicazione diventa il
guardiano di tutti i passaggi di stato, con l'elenco chiuso dei passaggi consentiti. Guarda lo
stato di partenza, letto dalla banca dati, e quello richiesto. Per una bozza che chiede di
andare in verifica controlla l'elenco dei dati mancanti; se qualcosa manca, la scrittura avviene
ma con lo stato di bozza, come oggi succede a chi tenta di pubblicare. Per un atto in verifica
ogni scrittura è **fermata per intero prima che cominci**, così che né la riga dell'atto né i
suoi dati collegati cambino. I due riquadri della schermata dell'atto, se l'invio chiede di
passare in verifica, salvano i loro dati **prima** del controllo: altrimenti chi compila tutto
e preme "Invia per la revisione" si vedrebbe rifiutare dati che ha appena scritto, e i
documenti caricati in quello stesso invio verrebbero rifiutati perché l'atto non è più in
bozza. In verifica i due riquadri mostrano i dati in sola lettura.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove fanno invii veri
della schermata e chiamate da codice, e rileggono la banca dati e il disco. Per ogni rifiuto si
osserva il valore **scritto** nella tabella dei contenuti, non soltanto quello riletto alla
fine: uno stato corretto dopo il fatto sarebbe indistinguibile da uno stato mai scritto. I dati
mancanti si provano uno per volta con tutti gli altri presenti, e il controllo positivo è la
stessa bozza completa, senza nessuna delle due date, che passa. Il blocco in verifica si prova
con tentativi indipendenti, ciascuno sulla fotografia intera dell'atto; il controllo positivo,
cioè le stesse modifiche che riescono sull'atto tornato in bozza, riporta l'atto in bozza
scrivendo lo stato nella banca dati, perché il ritorno in bozza vero arriva con la seconda
parte. La tabella dei guasti introdotti di proposito si trova in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-chiusura-pubblicazione.php` | l'elenco chiuso dei passaggi, il controllo dei dati mancanti, il fermo delle scritture su un atto in verifica, del cestino e della cancellazione; il motivo aggiornato della pubblicazione chiusa |
| `includes/class-scheda-atto.php`, `includes/class-scheda-documenti.php` | l'elaborazione dell'invio una volta per richiesta, chiamabile prima del controllo; la sola lettura fuori dalla bozza |
| `albo-pretorio-pa.php` | agganci |
| `tests/PassaggioInVerificaTest.php` | le prove A-85..A-92 |
| `tests/DocumentiAttoTest.php` | A-79 porta l'atto in verifica con il passaggio vero, che ora pretende l'atto completo |
| `docs/collaudo.md`, `docs/architettura.md`, `docs/requisiti.md`, `docs/implementazione/LEGGIMI.md` | righe nuove e stato dei requisiti |

### 2. Perché

ALBO-22, decisa il 2026-09-21: i passaggi consentiti sono un elenco chiuso, il passaggio in
verifica pretende i dati, e quello che è stato verificato è quello che esce. Oggi un atto può
andare "in attesa di revisione" con qualunque dato, e restarci modificabile.

### 3. Il flusso per chi usa il sito

Chi redige compila la bozza, carica il documento principale e preme "Invia per la revisione".
Se manca qualcosa la pagina si ricarica con l'atto ancora in bozza e un avviso che nomina il
dato mancante; i dati scritti in quell'invio sono salvati. Quando tutto c'è, l'atto passa in
verifica: i due riquadri mostrano i dati senza campi da compilare, e un salvataggio della
schermata viene rifiutato con un avviso che lo spiega. Chi pubblica apre l'atto e lo vede
com'è; la pubblicazione e il rimando in bozza arrivano con la seconda parte.

### 4. Dati letti e scritti

Nessun dato nuovo. Si legge lo stato memorizzato dell'atto e l'elenco dei dati mancanti; si
scrive lo stato, solo quando il passaggio è consentito.

### 5. Permessi

Il passaggio in verifica lo compie chi può modificare **quell'atto**. Nessun permesso nuovo.
In verifica il permesso di modificare resta, perché senza non si potrebbe nemmeno aprire la
schermata per guardare l'atto: a fermare le modifiche è il guardiano dei passaggi, non il
permesso.

### 6. Modi di guasto previsti

- Una bozza incompleta che passa in verifica. Coperto da A-86.
- Il controllo fatto sui dati di prima dell'invio, che rifiuta una bozza completata nello
  stesso invio. Coperto da A-87.
- Chi non può modificare l'atto che lo manda in verifica. Coperto da A-88.
- Un atto in verifica modificato da una via qualunque, anche solo nei dati collegati. Coperto
  da A-89.
- Un atto in verifica che esce dalla verifica per una via non ancora costruita, o finisce nel
  cestino. Coperto da A-90.
- Una bozza completa pubblicata saltando la verifica. Coperto da A-91.
- I riquadri che in verifica offrono ancora campi da compilare. Coperto da A-92.
- Uno stato rifiutato che viene scritto e poi corretto. Coperto da A-86, A-90 e A-91, che
  osservano il valore scritto.

### 7. Prove che si aggiungeranno

A-85..A-92 in `tests/PassaggioInVerificaTest.php`, descritte in `collaudo.md` nella sezione
"Il passaggio in verifica".

### 8. Cosa NON si implementa

La pubblicazione, con la conferma sui dati personali, il numero di repertorio e le date
(ALBO-11, ALBO-22, ALBO-27): seconda parte. Il rimando in bozza con motivazione: seconda
parte, sul registro delle modifiche. Defissione anticipata, annullamento e dichiarazione
dell'effetto sul periodo. La durata propria dell'atto (ALBO-04). Una chiamata diretta alle
funzioni che assegnano voci o scrivono dati di un atto, da codice di terzi, non passa da nessun
aggancio che possa fermarla: come per `wp_publish_post()`, quel caso non si impedisce e lo si
dichiara.

### 9. Come si prova sul sito vero

Da chi redige: una bozza senza documento principale, "Invia per la revisione", deve restare
in bozza con l'avviso che nomina il documento. Caricato il documento nello stesso invio o in
uno successivo, l'atto passa in verifica. Riaperto, i riquadri non hanno più campi, e cambiare
l'oggetto e salvare lascia l'oggetto com'era, con un avviso.

## Com'è andata davvero

Il piano ha retto. Le differenze sono otto, e sette sono più severe del piano.

**Il fermo avviene prima della scrittura, non dopo.** Il piano diceva "fermata per intero
prima che cominci" senza dire come. WordPress scrive le voci e i dati collegati dopo la riga
dell'atto, e riscrivere la sola riga avrebbe lasciato passare tipo e organo mandati insieme
alla richiesta di salvataggio. Il guardiano risponde quindi alla domanda che WordPress fa
prima di ogni altra cosa, cioè se il contenuto è vuoto, dicendo di sì: la scrittura si
ferma senza toccare niente. Dietro quel fermo restano due difese, per il caso in cui un altro
componente lo annulli: la riga dell'atto riscritta com'era, e i riquadri che salvano solo in
bozza. Al primo giro dei guasti nessuna prova le raggiungeva, perché il fermo arriva prima;
la seconda metà di A-89 le esercita con un aggancio che annulla il fermo.

**Un atto nuovo non nasce in verifica.** Il piano parlava di bozze che passano in verifica e
non diceva cosa succede a un atto creato direttamente con lo stato di verifica, da codice. Ora
resta in bozza, con il motivo "nasce sempre in bozza": il controllo dei dati mancanti e del
permesso sull'atto si fa solo su un atto che esiste già.

**L'oggetto si giudica su quello mandato.** L'elenco dei dati mancanti legge l'atto memorizzato;
per l'oggetto, che sta nella riga che si sta scrivendo, conta invece quello dell'invio. Senza,
chi scrive l'oggetto e chiede la verifica nello stesso invio si vedrebbe rifiutare.

**Cestino e cancellazione di un atto in verifica sono negati a tutti.** Il piano lo diceva per
il cestino; la cancellazione definitiva, che non passa dal cestino, è stata chiusa allo stesso
modo. È la lettura più prudente: un atto verificato che sparisce senza lasciare traccia è
proprio quello che il registro delle modifiche dovrà impedire. Il dubbio è annotato.

**Il passaggio in verifica non porta cambiamenti con sé.** Trovato rileggendo il codice a lavoro
finito, cercando la forma dei rilievi passati: un controllo che guarda una cosa vicina a quella
che serve. Il controllo dei dati mancanti guardava i dati com'erano **prima** della richiesta,
ma WordPress assegna le voci e scrive i dati mandati con la richiesta **dopo** aver scritto la
riga. Una bozza completa mandata in verifica da codice togliendo nella stessa richiesta il tipo,
l'organo, la data di adozione o il documento principale finiva in verifica senza quel dato; con
il tipo cambiato, finiva in verifica con un tipo mai controllato. Ora un passaggio in verifica
che porta voci dell'albo o dati dell'albo resta in bozza, e il motivo dice di salvare prima
nella bozza. La schermata non è toccata: non manda mai quelle voci, perché i due elenchi non
hanno il riquadro di WordPress, e i dati passano dai riquadri dell'albo, che salvano prima del
controllo. La seconda metà di A-86 lo prova.

**Il guardiano decide per ultimo.** Trovato con la stessa rilettura, cercando l'altra forma dei
rilievi passati: un valore deciso da noi che un aggancio di WordPress può cambiare dopo. Il
filtro che riporta in bozza lo stato rifiutato girava alla priorità normale, e un altro
componente agganciato a una priorità più alta poteva riportare lo stato a pubblicato: la
seconda metà di A-91 lo ha fatto vedere, pubblicando davvero una bozza. Il difetto era più
vecchio di questa unità, perché c'era già nello sbarramento della pubblicazione; si chiude con
una riga, e il filtro ora gira all'ultima priorità. Resta possibile solo a un componente che si
agganci anche lui all'ultima priorità dopo di noi, e quel caso non si può impedire da qui.

**Il motivo della pubblicazione chiusa è cambiato.** Prima rimandava alla consegna protetta dei
documenti, che ora esiste. Ora rimanda al registro delle modifiche e al passaggio dalla
verifica. La riga A-34 cerca il motivo nuovo, e la sua precondizione controlla che il motivo
prelevato lo contenga davvero.

**Il gettone del riquadro dei documenti si stampa solo in bozza.** In verifica il riquadro
mostra nomi e collegamenti senza modulo, e senza gettone: un invio di documenti su un atto in
verifica non ha nemmeno la forma di una richiesta valida.

**Le prove.** Centoquattordici prove nella suite principale, undici nuove, più le due suite separate
senza meccanismo comune e con meccanismo comune incompatibile, tutte verdi in locale su
WordPress 6.5 con MariaDB. PHPCS pulito. L'esito in verifica continua si legge sulla richiesta
di unione, e le righe passano a "fatto" solo dopo.

**La prova di non vacuità.** Ventiquattro guasti introdotti uno per volta in una copia usa e getta,
facendo girare le prove di questa unità, dei documenti, dei dati dell'atto e della
pubblicazione chiusa su ciascuno.

| Guasto introdotto | Prove cadute |
|---|---|
| Dati mancanti non controllati | A-86, A-87 |
| Riquadri salvati dopo il controllo | A-87 |
| Documenti salvati dopo il controllo | A-87 |
| Oggetto giudicato su quello memorizzato | A-86 |
| Permesso sull'atto non controllato | A-88 |
| Verifica ammessa anche per un atto nuovo | A-86 |
| Fermo della scrittura in verifica tolto | A-89, A-90 |
| Fermo e riscrittura della riga tolti | A-89, A-90 |
| Riscrittura della riga tolta, fermo presente | A-89 (seconda metà) |
| Cestino di un atto in verifica permesso | A-90 |
| Cancellazione di un atto in verifica permessa | A-90 |
| Bozza verso privato permessa | A-91 |
| Ripristino dal cestino negato | A-90 |
| Segno di fine scrittura mai tolto | A-70, A-72, A-80 |
| Documenti elaborati due volte nello stesso invio | A-87 |
| Dati elaborati due volte nello stesso invio | A-87 |
| Dati salvati anche fuori dalla bozza | A-89 (seconda metà) |
| Riquadro dei dati compilabile in verifica | A-92 |
| Gettone dei documenti stampato anche fuori dalla bozza | A-92 |
| Cambiamenti portati con il passaggio non controllati | A-86 (seconda metà) |
| Voci portate con il passaggio non guardate | A-86 (seconda metà) |
| Dati portati con il passaggio non guardati | A-86 (seconda metà) |
| Guardiano dello stato alla priorità normale | A-91 (seconda metà) |
| Pubblicazione concessa | A-26, A-34, A-91 |

**Cinque guasti che al primo giro passavano.** Un atto nuovo creato in verifica, la bozza
mandata a privato e il ripristino dal cestino negato non facevano cadere niente: le prove non
li tentavano, o guardavano solo lo stato finale, che per il ripristino negato è comunque bozza.
A-86 e A-91 ora li tentano, e A-90 pretende che il ripristino avvenga senza nessuna
segnalazione di rifiuto. La riscrittura della riga e il rifiuto dei dati fuori dalla bozza non
facevano cadere niente perché il fermo arriva prima di loro: li raggiunge la seconda metà di
A-89.

**I limiti che restano.** Una chiamata diretta alle funzioni che assegnano voci o scrivono dati
collegati, da codice di terzi, non passa da nessun aggancio che possa fermarla, come già detto
al punto 8. Per la stessa ragione, se un altro componente annulla il fermo e chiede il
salvataggio da codice con tipo e organo nella richiesta, la riga resta com'era ma le voci possono
cambiare: le due difese dietro il fermo proteggono la riga e i riquadri, non le voci mandate
da codice.
