# Numero di repertorio

Scheda di lavorazione. Scritta prima del codice come piano, da aggiornare a lavoro finito
con com'è andata davvero. Righe di collaudo A-58..A-68, più le righe di ALBO-08 nella
tabella per requisito.

## In tre paragrafi

**Cosa costruiamo.** Ogni atto, quando viene pubblicato per la prima volta, riceve un numero
del tipo 122/2026: un contatore che riparte ogni anno. Lo assegna il sistema, e nessuno lo
scrive o lo corregge a mano. Chi installa il componente a metà anno ha già un registro,
tenuto altrove, arrivato a un certo numero: per questo, nel **primo anno d'uso**, il
componente non assegna nessun numero finché chi pubblica non ha dichiarato, in una schermata
apposita, **l'ultimo numero già usato quell'anno**. Se ha scritto 121, il primo atto
pubblicato qui prende il 122. Se non c'è un registro precedente si scrive 0, e si parte da 1.
Un valore già pronto non c'è: partire da 1 in silenzio produrrebbe due atti con lo stesso
numero, uno nel registro vecchio e uno qui. La schermata mostra quale sarà il prossimo
numero prima e dopo la dichiarazione, che si può correggere finché nessun numero di
quell'anno è stato assegnato, e dopo resta com'è. Dal primo gennaio successivo la
numerazione riparte da 1 da sola, perché il registro dell'anno prima lo teneva già il
componente.

**Come la costruiamo.** Il contatore non sta nei dati dell'atto ma in due tabelle proprie:
una con una riga per anno (ultimo numero usato, numero dichiarato alla partenza, chi l'ha
dichiarato e quando) e una con le assegnazioni, cioè quale atto ha quale numero. La seconda
ha due vincoli scritti nella banca dati stessa: la coppia numero e anno non si ripete, e lo
stesso atto non compare due volte. Così le garanzie non dipendono dal fatto che il codice
sia scritto bene, ma da una regola che la banca dati fa rispettare anche a due pubblicazioni
nello stesso istante. Il contatore avanza con un'unica istruzione che legge e incrementa
insieme (operazione atomica): due richieste simultanee non possono leggere lo stesso valore.
Se due richieste provano a numerare lo stesso atto nello stesso istante, una vince, l'altra
trova il numero già assegnato e lo restituisce; il numero che aveva preso si perde. Un buco
nella sequenza è ammesso (ALBO-08 non lo esclude), un numero doppio no. Il numero non è un
metadato dell'atto, quindi nessuna richiesta che scrive metadati, dalla schermata o da
altrove, può toccarlo. L'anno è quello dell'istante di pubblicazione nel fuso orario del
sito. **Chi chiama l'assegnazione** arriva con il flusso di pubblicazione: oggi la
pubblicazione è chiusa per tutti, e questa unità costruisce il meccanismo e la schermata.

**Come si prova che funziona, e come si prova che il test è vero.** Una prova lancia più
processi PHP veri, in parallelo, che numerano atti diversi e lo stesso atto nello stesso
momento, e poi controlla che nessun numero sia doppio e che ogni atto ne abbia uno solo.
Altre prove verificano che senza dichiarazione non si numera niente, che dopo 121 viene 122,
che le dichiarazioni sbagliate sono rifiutate una per volta senza lasciare traccia, che la
dichiarazione si blocca dopo il primo numero, che il cambio d'anno avviene alla mezzanotte
del fuso del sito e non a quella di Greenwich, che una richiesta manipolata non sposta il
numero, e che la schermata controlla gettone e permesso. Diventerebbero rosse, per esempio:
se il contatore leggesse e poi scrivesse in due istruzioni; se mancasse uno dei due vincoli
della banca dati; se il primo anno partisse da 1 senza dichiarazione; se la dichiarazione
restasse modificabile dopo il primo numero; se l'anno si calcolasse sul tempo universale.
La tabella dei guasti introdotti di proposito sarà in fondo, a lavoro finito.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-repertorio.php` | le due tabelle, l'assegnazione, la dichiarazione di partenza, la lettura del numero di un atto |
| `includes/class-schermata-repertorio.php` | la schermata della dichiarazione, il suo salvataggio e l'avviso quando manca |
| `includes/class-installazione.php` | la creazione delle tabelle, legata alla versione come i permessi |
| `albo-pretorio-pa.php` | nomi delle tabelle, agganci, versione che sale |
| `tests/RepertorioTest.php`, `tests/concorrenza/assegna.php` | le prove A-58..A-68 e il processo figlio della prova di concorrenza |
| `docs/requisiti.md`, `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md`, `docs/sicurezza.md` | ALBO-08 non più ipotesi, righe nuove, colonna Stato |

### 2. Perché

Attua ALBO-08, promosso da ipotesi a scelta di prodotto il 2026-09-23, con il numero di
partenza dichiarato deciso lo stesso giorno. È l'unità A4 del piano e precede il flusso di
pubblicazione (A5), che la chiamerà nel passaggio a pubblicato.

### 3. Il flusso per chi usa il sito

Chi ha il permesso di pubblicare installa il componente a settembre. In bacheca vede un
avviso: prima di pubblicare va dichiarato l'ultimo numero usato quest'anno. Apre **Albo
pretorio, Numerazione**, legge che il vecchio registro è arrivato a 121, scrive 121 e salva.
La schermata risponde: il prossimo atto avrà il numero 122/2026, dichiarato da lui oggi.
Si accorge di un errore, era 124: corregge, e la schermata dice 125/2026. Poi si pubblica il
primo atto, che prende il 125. Da quel momento la schermata mostra la dichiarazione ma non
offre più il campo. Il primo gennaio l'avviso non compare: il 2027 comincia da 1.

### 4. Dati letti e scritti

Due tabelle nuove, create alla prima installazione e a ogni cambio di versione, come i
permessi. Nella tabella degli anni: anno, ultimo numero usato, numero dichiarato alla
partenza, come l'anno si è aperto (dichiarazione oppure prosecuzione di un anno precedente),
chi e quando. Nella tabella delle assegnazioni: anno, numero, atto, istante. Nessun
metadato dell'atto, nessuna opzione. Un dato temporaneo di pochi minuti porta il motivo di
un rifiuto dalla richiesta di salvataggio alla pagina che la segue, come per le durate.

### 5. Permessi

| Chi | Cosa può fare |
|---|---|
| Chi possiede il permesso di pubblicare atti (amministratore, Responsabile della pubblicazione all'albo) | vede la schermata, dichiara e corregge la partenza finché si può |
| Chi possiede solo il permesso di redigere | niente: né la schermata né la dichiarazione |
| Il sistema, nel passaggio a pubblicato | assegna il numero |
| Chiunque altro | niente |

Nessuna superficie per programmi: né rotte, né metadati registrati.

### 6. Modi di guasto previsti

| Guasto | Come degrada |
|---|---|
| Due pubblicazioni nello stesso istante | numeri diversi, garantito dalla banca dati |
| Due richieste numerano lo stesso atto insieme | un numero solo per l'atto; l'altro va perso e lascia un buco, ammesso |
| Un'assegnazione che si ferma dopo l'incremento | il numero va perso, buco ammesso, mai riusato |
| Primo anno senza dichiarazione | nessun numero assegnato, rifiuto con il motivo; il flusso di pubblicazione non pubblicherà |
| Le tabelle mancano, per esempio per un'installazione interrotta | assegnazione e dichiarazione rifiutate con il motivo; alla richiesta dopo l'installazione riprova |
| Un atto con numero viene cancellato | la sua assegnazione resta, e il numero non torna disponibile |
| Il salvataggio arriva senza gettone o da chi non ha il permesso | la dichiarazione non cambia, e l'avviso lo dice |

### 7. Prove che si aggiungeranno

Righe A-58..A-68 in `collaudo.md`, sezione nuova "Il numero di repertorio". Le righe di
ALBO-08 nella tabella per requisito perdono il segno di ipotesi, e quella dell'attacco si
divide in due. Restano **da fare**: la concorrenza e la richiesta manipolata si chiudono
quando il passaggio a pubblicato chiama l'assegnazione (A5), la sostituzione per
oscuramento con l'oscuramento (A7). Nel catalogo dei requisiti ALBO-08 passa a **in corso**.

### 8. Cosa NON si implementa

**Registri separati** per settore o per tipo: la documentazione li ipotizzava, nessuno li
ha chiesti, e si aggiungono se un'amministrazione li chiede. **Una sequenza senza buchi**:
non è fra le garanzie di ALBO-08. **La correzione della partenza dopo il primo numero**:
nessuna via, nemmeno per l'amministratore, perché renderebbe possibile un numero doppio o un
salto a ritroso. **La dichiarazione per un anno diverso da quello in corso**. **La chiamata
dal passaggio a pubblicato**, che arriva con A5.

### 9. Come si prova sul sito vero

Da amministratore, su un sito appena installato: in bacheca deve comparire l'avviso sulla
numerazione. Su **Albo pretorio, Numerazione** si scrive `12a` e si salva: rifiutato con un
avviso, e il prossimo numero resta non disponibile. Si scrive 121 e si salva: la schermata
deve dire 122 e l'anno in corso. Da un utente con il solo ruolo di redazione la voce di menu
non deve comparire, e l'indirizzo della schermata aperto a mano deve negare l'accesso.
