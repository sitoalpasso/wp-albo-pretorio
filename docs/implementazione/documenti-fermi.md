# Documenti di un atto fermo

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Requisito ALBO-09, con ALBO-10 e ALBO-12. Righe di collaudo A-317..A-323.

## In tre paragrafi

**Cosa costruiamo.** Un atto in verifica, pubblicato, tolto o annullato è fermo: nessuno
cambia i suoi dati, nemmeno l'amministratore del sito (A-239). Oggi però i suoi documenti,
cioè i PDF, sono contenuti a sé e non godono della stessa protezione. Un amministratore li
può staccare dall'atto dalla libreria dei media, e un altro componente o un comando li può
cancellare, mettere nel cestino, cambiare di stato o far puntare a un altro file. In tutti
questi casi l'atto pubblicato perde il suo documento, oppure ne mostra uno diverso da quello
pubblicato, e nessuno lo vede. Questa unità estende la protezione dell'atto fermo ai suoi
documenti.

**Come la costruiamo.** È un documento dell'atto il principale e ogni allegato indicato dai
dati dell'atto, più ogni versione sostituita da un oscuramento, che porta il suo segno.
Finché l'atto è fermo, un suo documento non si cancella e non va nel cestino. Non cambia atto,
stato, tipo, tipo di file né indirizzo. Il file a cui punta resta lo stesso, con la marca e
l'impronta del deposito, e il segno della sostituzione, una volta scritto, resta. Si chiudono
le vie che WordPress offre: la cancellazione, il cestino, il salvataggio del contenuto (anche
dall'interfaccia per programmi), lo stacco e l'aggancio dalla libreria dei media e i metadati.
Lo stato dell'atto e i dati che lo legano al documento si leggono dalla banca dati, con la
risposta mancata distinta: se la lettura non risponde, il documento vale come protetto. Un
figlio dell'atto che nessun dato indica, come il file caricato da un oscuramento non riuscito,
non è un documento dell'atto, e si toglie come prima. Titolo e descrizione del contenuto, che
nessuna pagina pubblica mostra, restano modificabili.

**Come si prova che funziona, e come si prova che il test è vero.** Un atto pubblicato con
principale e allegato, oscurato una volta, così che abbia anche una versione sostituita. Da
amministratore, per ciascun documento, si tenta ogni via, una alla volta. Dopo ogni tentativo
la prova rilegge dalla banca dati la riga del documento e i suoi metadati, controlla che il
file sia sul disco con la sua impronta e che il pubblico scarichi lo stesso file. Lo stesso
vale per un atto tolto e per uno annullato. Una lettura guasta deve fermare la scrittura. I
controlli positivi: il documento di una bozza si toglie dal riquadro, un allegato qualunque
del sito si stacca e si cancella, il file di un oscuramento fallito si toglie e il titolo si
cambia. I guasti introdotti per vedere quale prova li vede si elencano a lavoro finito.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-documenti-fermi.php` (nuovo): quali sono i documenti di un atto fermo e gli
  agganci che li proteggono.
- `albo-pretorio-pa.php`: il caricamento e l'aggancio.
- `tests/DocumentiFermiTest.php` (nuovo); `docs/collaudo.md`, `docs/architettura.md`.

### 2. Requisito servito

ALBO-09, integrità del documento pubblicato. Chiude il punto lasciato aperto dalla
defissione anticipata (A-239: "il documento come contenuto a sé non è di questa riga").

### 3. Il percorso di chi usa il sito

Nessuno nuovo. Chi tenta di staccare o cancellare il documento di un atto fermo dalla
libreria dei media non ottiene niente; la libreria non elenca già oggi i documenti degli
atti (A-82).

### 4. Dati letti e scritti

Letti: tipo, stato e atto del contenuto; stato dell'atto; i dati dell'atto che indicano
principale e allegati; il segno della sostituzione. Scritti: niente.

### 5. Permessi coinvolti

Nessuno nuovo. La protezione non dipende dai permessi: vale anche per l'amministratore e
per il codice che gira senza utente, come lo sbarramento dell'atto.

### 6. Modi di guasto previsti

- Una lettura che non risponde: la scrittura non passa.
- Un'istruzione scritta a mano nella banca dati da un altro componente, diversa da quella
  con cui WordPress stacca e aggancia gli allegati: non è coperta, come dichiarato al punto 8.

### 7. Test aggiunti

A-317..A-323, in `tests/DocumentiFermiTest.php`.

### 8. Cosa non implementa

- **Il file sul disco**: chi ha accesso ai file del server può sostituirlo. Lo rivela
  l'impronta (ALBO-10), che l'esportazione e la consegna controllano.
- **Le istruzioni dirette sulla banca dati** diverse da quella di WordPress per staccare e
  agganciare: lo sbarramento dell'atto le copre per l'atto, qui il costo non è in
  proporzione.
- **Le copie dello storico pubblico**, che sono contenuti di un altro tipo con regole proprie.

### 9. Come si prova su un'installazione reale

Sulla copia di prova, da amministratore: aprire la libreria dei media in modalità elenco
con l'indirizzo di stacco di un documento di un atto pubblicato, e verificare che l'atto
mostri ancora il suo documento; poi dalla scheda pubblica scaricarlo.

## Com'è andata

Come da piano, con quattro cose da dire.

- **Le vie, una per una.** La cancellazione si ferma su `pre_delete_attachment`, da cui passano
  anche `wp_delete_post()` e il cestino chiesto a `wp_delete_attachment()`; il cestino diretto
  su `pre_trash_post`. Il salvataggio si vaglia su `wp_insert_post_empty_content`, confrontando
  atto, stato, tipo e tipo di file con la riga letta dalla banca dati; dietro il vaglio, su
  `wp_insert_attachment_data` e `wp_insert_post_data`, quei quattro campi si rimettono com'erano,
  perché un altro aggancio (per esempio quello sull'atto di un allegato) li può cambiare dopo.
  Sotto WordPress si riconoscono due istruzioni: quella della libreria dei media che stacca e
  aggancia, da cui escono i documenti protetti, e quella di `set_post_type()`. I metadati si
  fermano con i filtri di WordPress, anche per numero di riga e tolti a tutti i contenuti.
- **Protetto è il documento in vita.** Un documento che un'istruzione diretta ha già messo nel
  cestino o fatto privato non è più protetto, e si ripara: tornato in vita, l'atto lo indica di
  nuovo e da lì è protetto. Proteggere anche lui avrebbe impedito di riparare.
- **Ogni rifiuto si segnala a chi programma**, con `_doing_it_wrong` e il nome
  `AlboPretorioPa\DocumentiFermi`: gli agganci di WordPress per queste scritture non portano un
  messaggio a chi chiama. Dall'interfaccia per programmi il salvataggio rifiutato torna con
  l'errore generico di WordPress sul contenuto vuoto, come per lo sbarramento dell'atto.
- **Due prove esistenti toccate.** In A-298 le due condizioni guaste sul segno della
  sostituzione, che i passaggi non producono, ora si scrivono di lato con `scavalca_barriera()`,
  che stacca anche questa protezione. In A-299 i guasti colpiscono le scritture del dato del
  documento e del segno, e non più ogni istruzione che li nomina: una lettura guasta ferma ora
  anche la pulizia del file entrante, che resta sul disco con l'avviso già previsto
  (`albo_file_entrante_rimasto`), ed è la direzione giusta.

Guasti introdotti nel codice per vedere quale prova li vede (tutti visti):

| Guasto | Visto da |
|---|---|
| Niente fermo sulla cancellazione | A-317, A-321, A-322 |
| Niente fermo sul cestino | A-317, A-322 |
| Niente vaglio del salvataggio | A-318, A-321, A-322 |
| Niente ripristino dei campi dopo il vaglio, aggancio tolto o campi non rimessi | A-318 |
| Niente filtro sulle istruzioni | A-319, A-321, A-322 |
| Niente fermo sull'aggiunta dei metadati | A-320 |
| Niente fermo sulla modifica dei metadati | A-320, A-321, A-322 |
| Niente fermo sulla cancellazione dei metadati | A-320, A-322 |
| Niente fermo sulla modifica per numero di riga | A-320 |
| Niente fermo sulla cancellazione per numero di riga | A-320, A-322 |
| La prima lettura senza risposta vale come libero | A-322 |
| La seconda lettura senza risposta vale come libero | A-322 |
| Il segno della sostituzione non protegge | A-317..A-321, A-323 |
| Allegati ulteriori non protetti | A-317..A-321, A-323 |
| Principale non protetto | A-317..A-321, A-323 |
| Protetto solo l'atto pubblicato | A-321 |
| Protetto il figlio di un contenuto di qualunque tipo | A-323 |
| Protetto ogni figlio di un atto fermo | A-317..A-323, e dieci righe dell'oscuramento |
| Protetto anche il documento già fuori vita | A-323 |
| Il vaglio guarda soltanto l'atto del documento | A-318 |
| Il segno si scrive sempre | A-320, A-322 |
| L'istruzione della libreria parte anche senza allegati rimasti | A-319 |
| Cancellazione a tutti i contenuti libera | A-320, A-322 |
| Rinomina per numero di riga libera | A-320 |
| Impronta non protetta | A-320 |
| Deposito non protetto | A-320 |
| File del documento non protetto | A-320, A-321, A-322 |
| `set_post_type()` non coperto | A-319 |

Le prove girano in locale: le azioni automatiche di GitHub sono ferme. Suite intera verde
salvo A-193, che in questo ambiente fallisce per la conversione dei caratteri anche su `main`;
verdi anche le due suite senza il meccanismo comune e con il meccanismo incompatibile.
