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
