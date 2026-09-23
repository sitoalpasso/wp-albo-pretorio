# Dati dell'atto

Scheda di lavorazione. Scritta prima del codice come piano e aggiornata a lavoro finito:
la sezione in fondo dice com'è andata davvero e cosa è cambiato rispetto al piano. Righe di
collaudo A-69..A-76, più la prima riga di ALBO-01 nella tabella per requisito.

## In tre paragrafi

**Cosa costruiamo.** Oggi di un atto si scrive soltanto l'oggetto. Con questa unità la
schermata dell'atto ha un riquadro **Dati dell'atto** con quattro campi: il **tipo di atto** e
l'**organo** che lo ha adottato, scelti da un menu fra le voci già configurate e mai scritti a
mano; la **data di adozione**; il **numero proprio dell'atto**, per esempio "determina
45/2026", che è facoltativo. Su un atto nuovo nessuna voce è già scelta, perché una scelta
proposta dal programma e salvata senza guardare varrebbe come una scelta fatta. Accanto a un
tipo di atto senza durata configurata il menu lo dice, perché quegli atti non si
pubblicheranno. La bozza si salva anche incompleta, come prima. Un campo scritto male (una
data che non esiste, una voce che non è di quell'elenco) non viene salvato, e un avviso dice
quale e perché; gli altri campi dello stesso salvataggio si salvano. Chi può solo scrivere atti
non può più creare tipi di atto o organi nuovi di passaggio, scrivendone il nome nella
richiesta di salvataggio: le voci nuove le crea chi governa gli elenchi.

**Come la costruiamo.** Tipo e organo restano dove sono già, nei due elenchi di voci, con la
regola che un atto ne ha una e una sola per elenco. Data di adozione e numero proprio sono due
metadati dell'atto, con il nome che comincia con il trattino basso: WordPress li considera
protetti, e una richiesta di salvataggio che li porta come campi personalizzati viene
ignorata. Così l'unico ingresso dalla schermata è il riquadro, che li valida. Sono
dichiarati a WordPress con l'esposizione per programmi spenta. La **lettura valida il dato
letto**, come per le durate: un dato scritto di lato nella banca dati
e malformato vale come assente, e due tipi di atto sullo stesso atto valgono come nessuno.
Sopra la lettura c'è una funzione che elenca i **dati mancanti** per nome: oggetto, tipo,
organo, data di adozione e durata di pubblicazione del tipo. Oggi non la chiama nessuno: la
chiamerà il passaggio da bozza a in verifica, che arriva con il flusso di pubblicazione (A5).
Il documento principale non è fra i dati, perché non esiste ancora: aspetta la consegna
protetta dei file del meccanismo comune.

**Come si prova che funziona, e come si prova che il test è vero.** Le prove fanno invii veri
della schermata dell'atto, con il suo gettone di sicurezza, e poi rileggono i dati dalla banca
dati invece di fidarsi della risposta. Verificano che i quattro dati si salvino, si cambino e
si tolgano; che ogni valore sbagliato sia rifiutato da solo, con il suo motivo; che senza il
gettone o senza il permesso non cambi niente; che un nome di voce inesistente nella richiesta
non crei una voce nuova; che un dato malformato scritto di lato valga come assente; e che
l'elenco dei dati mancanti nomini un dato per volta. Diventerebbero rosse, per esempio, se il
menu proponesse già una voce, se la data accettasse il 31 febbraio, se la lettura si fidasse
del valore memorizzato, se il gettone non fosse controllato, o se il numero proprio finisse
fra i dati obbligatori. La tabella dei guasti introdotti di proposito, e di quale prova ha
fatto cadere ciascuno, si trova in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-dati-atto.php` | la dichiarazione dei due metadati, la lettura validata dei quattro dati, l'elenco dei dati mancanti, la guardia sulle voci nuove |
| `includes/class-scheda-atto.php` | il riquadro nella schermata dell'atto, il suo salvataggio e l'avviso dei rifiuti |
| `albo-pretorio-pa.php` | nomi dei due metadati, agganci |
| `tests/DatiAttoTest.php` | le prove A-69..A-76 |
| `docs/collaudo.md`, `docs/dati.md`, `docs/architettura.md`, `docs/implementazione/LEGGIMI.md` | righe nuove, colonna "esiste oggi", indice delle schede |

### 2. Perché

La scheda di A2a aveva rimandato i campi dell'atto a "la schermata di compilazione", e
nessuna unità del piano la prendeva. Il flusso di pubblicazione controlla questi dati al
passaggio in verifica (ALBO-22) e alla pubblicazione (ALBO-01): senza, non ha niente da
controllare. È la base di ALBO-01, che però non si chiude qui: ALBO-01 parla di
pubblicazione e di documento principale.

### 3. Il flusso per chi usa il sito

Chi redige apre **Albo pretorio, Nuovo atto**. Scrive l'oggetto. Nel riquadro **Dati
dell'atto** sceglie il tipo "Determinazione" e l'organo "Responsabile del servizio", scrive
la data di adozione dal calendario e il numero "45/2026", poi salva la bozza. Riaprendola
ritrova i quattro valori. Se nel menu dei tipi accanto a una voce legge "senza durata: non si
pubblica", sa che quel tipo va configurato da chi pubblica prima che l'atto possa uscire.
Se una richiesta arriva con una data impossibile, per esempio da un modulo rimaneggiato, la
bozza si salva senza quella data e l'avviso in cima dice che la data di adozione non è una
data valida.

### 4. Dati letti e scritti

| Dato | Dove | Chi lo scrive |
|---|---|---|
| Tipo di atto | voce di `albo_tipo_atto` assegnata all'atto, una e una sola | chi può assegnare le voci, cioè chi redige |
| Organo | voce di `albo_organo` assegnata all'atto, una e una sola | come sopra |
| Data di adozione | metadato protetto, testo `AAAA-MM-GG` | chi può modificare l'atto |
| Numero proprio | metadato protetto, testo ripulito | come sopra, facoltativo |

Si legge anche la durata del tipo, attraverso `Durate`, per l'avviso nel menu e per l'elenco
dei dati mancanti. Non si scrive nient'altro: né le date di pubblicazione, né il numero di
repertorio, né lo stato.

### 5. Permessi

Il salvataggio dal riquadro chiede il gettone del riquadro e il permesso di modificare
**quell'atto**; per tipo e organo anche il permesso di assegnare le voci dell'elenco, che è
derivato dal permesso di redazione. Creare una voce nuova nei due elenchi chiede il permesso di
governarli, derivato dal permesso di pubblicare, anche quando la richiesta passa dal
salvataggio di un atto. Nessun permesso nuovo. Il gettone del riquadro è legato all'atto:
quello preso dalla schermata di un atto non vale per un altro.

### 6. Modi di guasto previsti

- Un valore preselezionato nel menu, salvato da chi non guarda, che diventa un tipo scelto da
  nessuno. Coperto da A-69.
- Una data impossibile accettata perché il formato è giusto (31 febbraio), o una data con
  testo attaccato. Coperto da A-71.
- Una voce di un altro elenco accettata perché il numero esiste. Coperto da A-71.
- Una richiesta senza gettone, con un gettone falso o di un utente che non può modificare
  l'atto, che cambia i dati. Coperto da A-72.
- Un nome di voce inesistente nella richiesta che crea una voce nuova nell'elenco. Coperto da
  A-73.
- Una lettura che si fida del valore memorizzato. Coperto da A-74.
- Un elenco dei mancanti che dimentica un dato, ne nomina due per una sola mancanza, o
  pretende il numero proprio. Coperto da A-75.
- Un salvataggio da codice, senza gettone, che il riquadro scambia per un invio vuoto e che
  quindi cancella i dati. Coperto da A-70.

### 7. Prove che si aggiungeranno

A-69..A-76 in `tests/DatiAttoTest.php`, descritte in `collaudo.md` nella sezione "I dati
dell'atto". La prima riga di ALBO-01 (la bozza con dati mancanti si salva) passa a "fatto"
con A-70.

### 8. Cosa NON si implementa

Il documento principale e gli allegati, che aspettano la consegna protetta dei file del
meccanismo comune. La durata propria dell'atto, che scrive la motivazione nel registro delle
modifiche e quindi aspetta quel registro. Il blocco dei campi quando l'atto è in verifica o
pubblicato, che arriva con i passaggi di stato (A5) e con l'immodificabilità (A6).
L'amministrazione che ha adottato un atto ospitato (A19). Un divieto sulla data di adozione
nel futuro: nessuna fonte letta lo chiede per una bozza, che si prepara anche prima della
seduta.

### 9. Come si prova sul sito vero

Da un utente con il ruolo di redazione: si crea un atto, si sceglie tipo e organo, si scrive
data e numero, si salva, si riapre e si ritrovano i quattro valori. Si verifica che i menu di
un atto nuovo partano senza nessuna voce scelta. Si scrive come data di adozione il 31
febbraio, modificando il campo con gli strumenti del navigatore: la bozza si salva senza
quella data e compare l'avviso.

## Com'è andata davvero

**Le prove.** Novantacinque prove nella suite principale, otto nuove, più le due suite
separate senza meccanismo comune e con meccanismo comune incompatibile, tutte verdi in
locale su WordPress 6.5 con MariaDB. PHPCS pulito. In verifica continua, sul commit 34a9f6e,
verdi le stesse suite su MySQL 8 con WordPress 6.5 e PHP 8.1 e con l'ultima WordPress e PHP
8.3, più lo standard di codifica e la validazione di `publiccode.yml`.

**Cosa è cambiato rispetto al piano.** Tre cose. La prima: il gettone del riquadro è legato
all'atto. Scrivendo A-72 è venuto fuori che con un gettone solo per tutti gli atti quello
preso dalla schermata di un atto valeva per qualunque altro; ora la riga lo prova. La
seconda: un campo che non arriva nell'invio resta com'era, e un campo arrivato come elenco
invece che come testo è un valore sbagliato, non un valore vuoto. A-70 prova il primo caso.
La terza: la versione del componente non sale, perché questa unità non scrive niente nella
banca dati all'installazione.

**Un controllo che nessuna prova raggiunge, dichiarato.** Il salvataggio del tipo e
dell'organo chiede anche il permesso di assegnare le voci. Con i permessi di oggi quel
permesso coincide con quello di redazione, che serve già per modificare l'atto, quindi il
controllo non può fallire da solo e nessuna prova lo esercita. Resta perché è lo stesso
controllo che WordPress fa sulle voci, e diventa utile se un giorno i due permessi si
separano.

**La prova di non vacuità.** Ventidue guasti introdotti uno per volta in una copia usa e
getta, facendo girare le prove di questa unità su ciascuno.

| Guasto introdotto | Prove cadute |
|---|---|
| Il menu senza la voce vuota selezionata, cioè con la prima voce vera proposta | A-69 |
| La data non riletta dopo la conversione, così che il 31 febbraio passa | A-71, A-74 |
| L'ancora che accetta un a capo finale nella data | nessuna: vedi sotto |
| La lettura che si fida della data memorizzata | A-74 |
| Due righe di data accettate | A-69, A-74, A-75 |
| La prima voce presa quando ce ne sono due | A-69, A-74, A-75 |
| Il gettone non controllato | A-72 |
| Il gettone non legato all'atto | A-72 |
| Il permesso sull'atto non controllato | A-72 |
| Un salvataggio senza riquadro letto come un invio vuoto | A-70, A-72, A-75 |
| Un campo assente letto come vuoto | A-70 |
| La guardia sulle voci nuove tolta | A-73 |
| Il numero proprio obbligatorio | A-75 |
| Il tipo senza durata nominato come tipo mancante | A-75 |
| La durata nominata anche quando manca il tipo | A-75 |
| L'oggetto non controllato | A-75 |
| La voce di un altro elenco accettata | A-71 |
| La voce aggiunta invece che sostituita | A-70, A-71, A-72 |
| I dati esposti ai programmi | A-76 |
| I dati non protetti | A-72, A-76 |
| Il riquadro senza controllo del permesso | A-76 |
| Il tipo senza durata non segnalato nel menu | A-69, dopo una correzione della prova: vedi sotto |

**Una prova che non provava.** Al primo giro il menu che non segnala il tipo senza durata
non ha fatto cadere niente: la voce di prova si chiamava "Avviso senza durata", quindi la
frase cercata c'era comunque, nel nome. Ora la voce si chiama "Avviso pubblico", e il guasto
cade.

**Un guasto che non può cadere.** L'ancora della data che accetta un a capo finale non ha
fatto cadere niente, e non per una prova mancante: la conversione della data che viene
subito dopo rifiuta da sola qualunque carattere in più, a capo compreso. Il controllo del
formato resta come primo filtro, ma da solo non porta peso, e la tabella lo dice.
