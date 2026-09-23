# Durate per tipo di atto

Scheda di lavorazione. Scritta prima del codice come piano e aggiornata a lavoro finito:
la sezione in fondo dice com'è andata davvero e cosa è cambiato rispetto al piano. Righe di
collaudo A-48..A-57, più la parte di configurazione di ALBO-04.

## In tre paragrafi

**Cosa costruiamo.** Nella schermata **Tipi di atto** dell'albo, per ogni tipo si potranno
scrivere tre cose: per quanti giorni i suoi atti restano esposti, **da dove viene** quel
numero (da una norma, oppure da una scelta dell'amministrazione) e, se viene da una norma,
**quale** norma. Nessuno dei tre campi ha un valore già pronto, e l'origine non è
preselezionata. Se manca qualcosa, o il numero non è un intero da 1 in su, o si dice
"norma" senza dire quale, la durata non viene salvata e un avviso dice perché: il tipo
resta com'era. Nell'elenco dei tipi una colonna mostra la durata di ciascuno, oppure dice
che non è configurata e che i suoi atti non si potranno pubblicare. Cambiare la durata di un
tipo non richiede di toccare il codice.

**Come la costruiamo.** Le tre informazioni si salvano insieme, in un solo dato attaccato
al tipo di atto, così che non possa esistere un tipo con il numero di giorni ma senza
l'origine perché una scrittura si è fermata a metà. Il controllo si fa in due momenti:
quando qualcuno salva, per rifiutare subito e dirlo; e **ogni volta che la durata si
legge**, per la stessa ragione per cui la scadenza è una proprietà del dato letto. Se
qualcuno scrive nella banca dati una durata malformata passando di lato, il componente non
la usa: per lui quel tipo non ha una durata valida. Chi può configurare le durate è chi può
governare l'elenco dei tipi di atto, cioè chi possiede il permesso di pubblicare; il
salvataggio dalla schermata controlla il gettone di sicurezza del modulo e il permesso sul
singolo tipo.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove configurano una
durata e poi **la rileggono**, invece di fidarsi della risposta. Provano ogni forma di dato
sbagliato, una per volta, e verificano che il tipo resti esattamente com'era, anche quando
aveva già una durata valida. Scrivono di lato nella banca dati durate malformate, una
condizione per volta, e pretendono che la lettura le rifiuti. Provano la schermata con un
invio vero, senza gettone, e da un utente senza permesso. E una prova cerca nei sorgenti il
numero 15 usato come durata. Diventerebbero rosse, per esempio: se la lettura si fidasse di
quello che trova nella banca dati; se il salvataggio scrivesse il numero di giorni prima di
aver controllato l'origine; se un campo avesse un valore di ripiego; se il gestore della
schermata dimenticasse il gettone o il permesso; se qualcuno scrivesse `15` nel codice. La
tabella dei guasti introdotti di proposito, e di quale prova ha fatto cadere ciascuno, si
trova in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-durate.php` | il dato della durata, la sua validazione, la lettura, il salvataggio dalla schermata, la colonna e l'avviso |
| `albo-pretorio-pa.php` | la costante con il nome del dato e gli agganci |
| `tests/DurateTest.php` | le prove A-48..A-57 |
| `tests/ambiente-albo.php` | l'azzeramento dello stato della nuova parte fra una prova e l'altra |
| `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md` | righe nuove e colonna Stato, nello stesso ramo |

### 2. Perché

Attua la parte di configurazione di ALBO-04: durate per tipo di atto, configurabili, senza
default, ciascuna con l'origine dichiarata e, per l'origine normativa, gli estremi. È
l'unità A3 del piano, e precede il flusso di pubblicazione (ALBO-22) perché il passaggio da
bozza a "in verifica" pretende una durata applicabile.

### 3. Il flusso per chi usa il sito

Chi ha il permesso di pubblicare apre **Albo pretorio, Tipi di atto**. Crea un tipo, per
esempio "Deliberazione di consiglio", e compila: giorni 15, origine "norma", estremi "art.
124 d.lgs. 267/2000". Salva: la colonna mostra la durata con la sua origine. Crea poi un
tipo "Avviso", sceglie "scelta dell'amministrazione" e 30 giorni, e lascia vuoti gli
estremi. Se prova a salvare "norma" senza estremi, vede l'avviso che lo dice e la durata non
cambia. Se svuota tutti e tre i campi di un tipo che aveva una durata, la durata viene tolta
e la colonna lo segnala.

### 4. Dati letti e scritti

Un solo metadato sul tipo di atto, con tre valori insieme: giorni, origine, estremi. Si
scrive quando qualcuno salva la schermata o quando il codice chiama la funzione di
configurazione. Si legge ogni volta che serve sapere la durata di un tipo. Un dato
temporaneo di pochi minuti porta il motivo di un rifiuto dalla richiesta di salvataggio
alla pagina che la segue. Nessun'altra scrittura: niente opzioni, niente valori di
ripiego.

### 5. Permessi

| Chi | Cosa può fare |
|---|---|
| Chi possiede il permesso di pubblicare atti (amministratore, Responsabile della pubblicazione all'albo) | configura, cambia e toglie la durata di un tipo |
| Chi possiede solo il permesso di redigere | vede i tipi quando li assegna, non ne cambia la durata |
| Chiunque altro | niente |

Il metadato è dichiarato a WordPress come non esposto all'interfaccia per programmi. Chi può
modificarlo attraverso i controlli di WordPress lo decide WordPress stesso, che per il
metadato di una voce chiede il permesso di modificare quella voce.

### 6. Modi di guasto previsti

| Guasto | Come degrada |
|---|---|
| Il salvataggio arriva senza gettone o da chi non ha il permesso | la durata non cambia, e l'avviso lo dice |
| Una durata malformata scritta di lato nella banca dati | la lettura la rifiuta: il tipo risulta senza durata valida, e i suoi atti non si pubblicheranno |
| Il dato temporaneo con il motivo del rifiuto si perde | la durata resta comunque non salvata; l'avviso manca, la colonna mostra lo stato vero |
| Il tipo viene cancellato | il suo metadato se ne va con lui, come ogni metadato di WordPress |
| La modifica rapida dall'elenco dei tipi | cambia nome e abbreviazione e non tocca la durata, perché non porta i campi della durata |

### 7. Prove che si aggiungeranno

Nuove righe A-48..A-57 in `collaudo.md`, sezione nuova "Le durate per tipo di atto".
ALBO-04 resta **da fare** nella tabella per requisito: le sue righe chiedono anche la
pubblicazione e la durata propria, che arrivano con il flusso di pubblicazione. La riga
statica di ALBO-04, quella sul numero 15, diventa invece **fatto** a CI verde.

### 8. Cosa NON si implementa

La **durata propria dell'atto**: chiede lo stato bozza governato, il registro delle
operazioni e la ripetizione del controllo alla pubblicazione, e nasce con il flusso di
pubblicazione. Il **blocco della pubblicazione** di un atto il cui tipo non ha durata
valida: oggi la pubblicazione è chiusa per tutti, e il controllo sulla durata entrerà nello
sbarramento quando la pubblicazione si aprirà. Nessun controllo che gli estremi citino una
norma vera: il componente pretende che siano scritti, non può sapere se sono giusti.
Nessun tetto massimo al numero di giorni.

### 9. Come si prova sul sito vero

Da amministratore, su **Albo pretorio, Tipi di atto**, si crea un tipo con 15 giorni,
origine "norma" e nessun estremo, e si salva: deve comparire un avviso che chiede gli
estremi, e nella colonna Durata il tipo deve risultare **non configurato**. Se la colonna
mostra 15 giorni, la validazione non gira sul sito vero e il rilascio si ferma. Poi si
aggiungono gli estremi e si salva di nuovo: la colonna deve mostrare 15 giorni, norma, con
gli estremi scritti.

## Com'è andata davvero

**Le prove.** Settantasei prove nella suite principale, dieci nuove, più le due suite
separate senza meccanismo comune e con meccanismo comune incompatibile, tutte verdi in
locale su WordPress 6.5. PHPCS pulito. Il verdetto che vale è quello della verifica continua
sul commit di punta.

**Un pezzo tolto rispetto al piano.** Il piano dichiarava un controllo nostro su chi può
modificare il dato attraverso i controlli di WordPress. La prova di non vacuità ha mostrato
che non serviva a niente: sostituito con un controllo che concede sempre, nessuna prova è
caduta, perché WordPress chiede già il permesso di modificare la voce e un controllo in più
può solo restringerlo. Un pezzo di codice che nessun guasto può rendere visibile non
protegge niente, quindi è stato tolto, e la riga A-55 verifica il comportamento di
WordPress invece di un nostro doppione.

**La prova di non vacuità.** Tredici guasti introdotti uno per volta in una copia usa e
getta, facendo girare le prove delle durate su ciascuna.

| Guasto introdotto | Prove cadute |
|---|---|
| La lettura si fida di qualunque valore trovato | A-51, A-56 |
| Due righe per lo stesso dato accettate | A-51 |
| Il valore scritto prima di essere controllato | A-49, A-50, A-53 |
| Un valore di ripiego quando la durata manca | A-49, A-52, A-56 |
| Il controllo dei giorni con l'ancora che accetta un a capo finale | A-49 |
| La lettura accetta una norma senza estremi | A-51 |
| Il gettone del modulo non controllato | A-53 |
| Il permesso sul tipo non controllato | A-53 |
| I campi mancanti letti come vuoti, così che ogni rinomina toglie la durata | A-54 |
| Il dato esposto all'interfaccia per programmi | A-55 |
| La colonna che legge il dato grezzo invece della durata valida | A-56 |
| Il numero 15 scritto in una costante | A-57 |
| Il controllo nostro su chi modifica il dato che concede sempre | nessuna: il controllo era un doppione di WordPress, ed è stato tolto |

**Due cose che il laboratorio non dice.** Il salvataggio di un tipo nuovo avviene senza
ricaricare la pagina: se la durata viene rifiutata, il tipo nasce comunque con il suo nome,
la colonna lo mostra come non configurato e l'avviso compare alla pagina successiva. E il
nome delle due origini è mostrato a parole, non come codice: chi configura legge "da una
norma, che fissa il termine" e "da una scelta dell'amministrazione, dove la norma non fissa
un termine".
