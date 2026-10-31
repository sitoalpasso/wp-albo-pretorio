# Indizi di accessibilità dei documenti PDF

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Requisito ALBO-13. Righe di collaudo A-331..A-335.

## In tre paragrafi

**Cosa costruiamo.** I documenti pubblicati all'albo devono essere accessibili, cioè leggibili
anche da chi usa un lettore di schermo o altre tecnologie assistive. Se un documento è
accessibile o no dipende da come l'ufficio lo produce, non dal sito: il sito non può renderlo
accessibile, ma può accorgersi dei difetti più frequenti e dirlo a chi lo carica. Il riquadro
Documenti dell'atto, accanto a ogni PDF, mostra gli indizi trovati: il documento non ha la
struttura per le tecnologie assistive (non è "taggato"); sembra una scansione, con immagini e
nessun testo; non ha un titolo; non dichiara la lingua. Sono indizi, non una verifica: la
pubblicazione resta possibile, e il riquadro lo dice.

**Come la costruiamo.** Una classe sola legge il file del documento, fino a un tetto di
dimensione, e cerca nel contenuto i segni che un PDF accessibile porta: la radice della
struttura, i caratteri del testo, il titolo, la lingua. Nei PDF recenti una parte di questi segni
sta dentro blocchi compressi: la classe apre anche quelli, con un tetto, così che un documento
in regola non risulti senza struttura solo perché è compresso. Se il file è cifrato, troppo
grande o non è un PDF, il riquadro dice che non è stato controllato e perché, senza inventare
indizi. Il controllo si fa quando il riquadro si apre: non si salva niente, e non cambia
nessun dato dell'atto.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove costruiscono PDF
piccoli, scritti per esteso nella prova, ciascuno con un solo difetto, più uno senza difetti e
uno senza difetti ma con i segni dentro un blocco compresso. Per ciascuno si controlla l'esito
della classe e il testo del riquadro. Poi si pubblica un atto con un documento pieno di indizi,
per provare che la pubblicazione non è bloccata. I guasti introdotti nel codice per vedere quale
prova li vede sono in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-indizi-accessibilita.php`: nuova, la lettura del PDF e gli indizi.
- `includes/class-scheda-documenti.php`: gli indizi nella riga di ciascun documento.
- `albo-pretorio-pa.php`: il caricamento della classe.
- `tests/IndiziAccessibilitaTest.php`; `docs/collaudo.md`, `docs/architettura.md`.

### 2. Requisito servito

ALBO-13: i documenti pubblicati sono accessibili; il prodotto avvisa con un indizio al
caricamento, e non blocca.

### 3. Il percorso di chi usa il sito

Chi redige carica il PDF e salva la bozza. Il riquadro Documenti mostra, sotto il nome del
file, gli indizi trovati con una frase che dice cosa significano e come si rimedia (rifare il
PDF dall'originale con la struttura attiva, oppure con il riconoscimento del testo se è una
scansione), e che la pubblicazione resta possibile. Gli stessi indizi si vedono anche dopo,
in verifica e da pubblicato, a chi può aprire l'atto.

### 4. Dati letti e scritti

Letto: il file del documento, al più fino al tetto. Scritto: niente.

### 5. Permessi coinvolti

Nessuno nuovo. Il riquadro si mostra già solo a chi può modificare l'atto.

### 6. Modi di guasto previsti

- File oltre il tetto, cifrato, illeggibile o non PDF: "non controllato", con il motivo.
- Blocco compresso che non si apre: si cerca nel resto del file, e l'indizio resta un indizio.
- Blocco compresso che si apre in qualcosa di enorme: la decompressione si ferma al tetto, e il
  tetto vale per tutti i blocchi insieme, contando anche quelli scartati.
- File con moltissimi blocchi: se ne esaminano al più `MASSIMO_BLOCCHI`, cercandoli uno alla volta.

### 7. Test aggiunti

A-331..A-335, in `tests/IndiziAccessibilitaTest.php`.

### 8. Cosa non implementa

- **Non è una verifica di accessibilità.** Un PDF con la struttura può averla sbagliata, e una
  scansione con il testo riconosciuto può avere caratteri e restare poco leggibile. La verifica
  vera si fa con uno strumento apposito.
- **Il controllo non si salva.** Ogni apertura del riquadro rilegge i file: sono pochi per atto.
  Un elenco degli indizi su tutti gli atti, per esempio dopo l'importazione, sarebbe un'unità a
  parte.
- **Solo PDF.** Un documento firmato in busta (`.p7m`) o di altro formato risulta non
  controllato.
- **Il titolo si cerca ovunque nel file.** Un PDF senza titolo nelle proprietà ma con un
  segnalibro (che porta anch'esso un titolo) non ha l'indizio: un indizio mancato, non uno
  falso. Distinguere le proprietà dai segnalibri vorrebbe dire leggere la struttura del PDF per
  intero.
- **ALBO-14** (nessuna misura anti copia che rompa l'accessibilità) è un'unità a parte.

### 9. Come si prova su un'installazione reale

Sulla copia di prova: caricare un PDF esportato da un programma di scrittura con la struttura
attiva, poi una scansione senza riconoscimento del testo, e confrontare gli indizi del riquadro
con l'esito di uno strumento di verifica dei PDF.

## Com'è andata

Come da piano, con due correzioni scoperte dalle prove. Le prove girano in locale, perché le
azioni automatiche di GitHub sono ferme: suite intera di 428 prove, verdi salvo A-193, che in
questo ambiente fallisce anche su `main`; verdi le due suite senza il meccanismo comune e con il
meccanismo incompatibile. La decompressione in un colpo solo con
un limite di lunghezza non si ferma al limite su blocchi grandi: il blocco si apre a pezzi, e si
ferma appena supera il tetto. E un file che non è un PDF il meccanismo comune non lo deposita
nemmeno: quel caso si prova sul contenuto.

Dopo la rilettura, un secondo giro sui limiti di lavoro (riga A-335), con le prove scritte prima e
rosse sul codice precedente. Il tetto ora vale per tutti i blocchi insieme: ogni byte aperto si
conta, anche quello di un blocco poi scartato, e a tetto esaurito ci si ferma. I blocchi si
cercano a passi, uno alla volta, al più `MASSIMO_BLOCCHI`, invece di raccogliere prima tutte le
posizioni. L'apertura si ferma alla fine dei dati compressi, così l'a capo prima di `endstream` non
fa scartare un blocco buono. E la memoria scende: i pezzi si leggono dal file senza copiarlo, e i
segni si cercano nel file e nei blocchi aperti separatamente, senza unirli.

L'idea dei quattro segni da cercare viene dall'analisi del modulo di accessibilità di
ItaliaWP3 (GPL-3.0-or-later). Il codice è scritto da capo, senza copiarne righe.

| Guasto | Visto da |
|---|---|
| Nessun blocco compresso aperto | A-332 |
| I blocchi dei metadati non si aprono | A-332 |
| Si apre ogni blocco compresso, anche quelli di contenuto | A-332 |
| Nessun tetto alla decompressione | A-332 |
| Il cifrato non si riconosce | A-333, A-334 |
| La descrizione dei caratteri vale come carattere | A-331 |
| Scansione senza guardare le immagini | A-331 |
| Il titolo vuoto vale come titolo | A-331 |
| Il titolo esadecimale non vale | A-331 |
| Il titolo in un altro oggetto non vale | A-331 |
| Il titolo XMP vuoto vale come titolo | A-331 |
| Nessun tetto alla dimensione del file | A-333 |
| Nessun controllo dell'intestazione del PDF | A-333 |
| Il riquadro non dice che la pubblicazione resta possibile | A-334 |
| Il riquadro non mostra gli indizi | A-334 |
| I byte aperti non si tolgono dal tetto | A-332, A-335 |
| Nessun massimo ai blocchi esaminati | A-335 |
| I blocchi esaminati non si contano | A-335 |
| L'apertura non si ferma alla fine dei dati | A-335 |
| I segni non si cercano nei blocchi aperti | A-332, A-335 |
| Il titolo non si cerca nei blocchi aperti | A-332, A-335 |

La lingua cercata come `/Lan` invece di `/Lang` non la vede nessuna prova: nessun'altra chiave
dei PDF comincia così, quindi i due controlli si comportano allo stesso modo.

Due guasti non li vede nessuna prova, e lo si dichiara. Togliere l'arresto a tetto esaurito non
cambia l'esito, perché ogni blocco successivo si scarta al primo pezzo: costa solo lavoro, e
l'arresto resta per quello. Togliere l'a capo che separa i blocchi aperti farebbe trovare un segno
spezzato fra la fine di un blocco e l'inizio del successivo: un caso costruito, senza prova.
