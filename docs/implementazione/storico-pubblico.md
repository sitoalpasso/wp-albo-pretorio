# Storico pubblico degli atti

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: i tre passi uniti; la ricerca dello storico in revisione.** Requisito ALBO-36, righe di
collaudo A-261..A-265 per il primo passo. Nessuna versione nuova del meccanismo comune.

## In tre paragrafi

**Cosa costruiamo.** Una sezione pubblica separata dall'albo, lo "storico", dove
l'amministrazione mette a disposizione di chiunque una **copia oscurata** di atti la cui
pubblicazione è finita, così che chi cerca un atto vecchio lo trovi senza dover chiedere
all'ufficio. L'albo non cambia: l'atto scaduto resta "pagina non trovata" per tutti (ALBO-05),
e il suo documento originale si apre soltanto dalla consultazione riservata (ALBO-34). Lo
storico è spento finché l'amministrazione non lo accende, e quando lo accende dichiara per
quanti anni ogni copia resta consultabile: nessun numero è proposto. Un atto entra nello
storico solo perché una persona con il permesso apposito lo sceglie, carica la copia oscurata
e conferma di aver controllato documento e oggetto; da lì si toglie in ogni momento, anche su
richiesta di chi vi è nominato. Ogni passaggio finisce nel registro.

**Come la costruiamo.** La copia nello storico è un **contenuto distinto** dall'atto, di un
tipo proprio (`albo_storico`) registrato dal meccanismo comune in una seconda sezione
(`albo_pretorio_storico`), con la stessa politica dell'albo: indicizzazione vietata e
scadenza `irraggiungibile`. Così lo storico non riapre l'atto scaduto, che resta governato
dalla sua sezione come oggi, e la copia ha una data di fine propria, calcolata all'ingresso
con gli anni dichiarati: alla fine sparisce da sola, per la data letta, su ogni strada che il
meccanismo comune già sorveglia, documento compreso. Il documento della copia si deposita
con il meccanismo comune e si scarica dal suo punto di consegna, mai dalla cartella dei
caricamenti. I permessi del tipo dello storico che il meccanismo comune crea non vanno a
nessun ruolo: la copia si crea e si toglie soltanto dalle schermate dell'albo, con il
permesso apposito `albo_pretorio_storico_atto_albo`, che l'albo dà all'amministratore e al
proprio ruolo una volta sola e che l'amministrazione può togliere o dare ad altri.

**Come si prova che funziona, e come si prova che il test è vero.** Ogni riga del collaudo
ha accanto la sua presenza di controllo: lo storico spento accanto allo storico acceso, chi
ha il permesso accanto a chi non l'ha, la copia prima della sua fine accanto alla stessa
copia dopo, l'atto scaduto che resta "non trovato" nello stesso istante in cui la sua copia
si apre. Le mutazioni provate sono elencate sotto, in "Com'è andata davvero".

## Il piano, in tre passi

Ogni passo è una richiesta di unione a sé, corta, leggibile dal telefono.

1. **Interruttore, permesso, tipo** (righe A-261..A-265). La schermata "Storico pubblico"
   con la norma, le due scelte senza preselezione e gli anni; il salvataggio nel registro; il
   permesso apposito; la seconda sezione e il tipo dello storico, chiuso a tutti. Nessuna
   copia si crea ancora e niente compare sul sito pubblico.
2. **Ingresso e uscita** (righe A-266..A-271). Dalla consultazione riservata, su un atto
   arrivato regolarmente alla fine, il pulsante "Metti nello storico": caricamento della
   copia oscurata, oggetto proprio della copia precompilato e modificabile, conferma
   obbligatoria, fine calcolata con gli anni in vigore, voce nel registro con l'impronta del
   file. La rimozione con il motivo, fra cui la richiesta dell'interessato (GDPR art. 17),
   anch'essa nel registro.
3. **Pagina pubblica dello storico** (righe successive). Il codice breve
   `[albo_pretorio_storico]`, su una pagina diversa da quella dell'albo: elenco, ricerca,
   scheda della copia con l'avviso "copia per consultazione: la pubblicazione all'albo è
   terminata il ...", documento dal punto di consegna, divieto ai motori. Spento lo
   storico, la pagina non mostra niente e i documenti non si scaricano; le copie restano.

## Che cosa dice la norma, e che cosa è scelta di prodotto

- **Norma.** Finito il periodo di pubblicazione, l'ente non può continuare a diffondere i
  dati personali contenuti negli atti dell'albo (Garante, linee guida approvate con provv. 15
  maggio 2014 n. 243, parte seconda, par. 3.a). Se li mantiene in rete, scaduti i termini i
  documenti vanno privati degli elementi che identificano gli interessati, e il testo
  integrale resta accessibile con l'accesso agli atti della l. 241/1990 (par. 2.b). I dati
  vanno sottratti ai motori di ricerca esterni (par. 2.a). Il periodo di cinque anni dell'art.
  8, comma 3, del d.lgs. 33/2013 riguarda gli obblighi di trasparenza e non le pubblicazioni
  all'albo (par. 3.a). Dati genetici, biometrici e relativi alla salute non si diffondono mai
  (d.lgs. 196/2003, art. 2-septies, comma 8).
- **Prodotto.** Che lo storico esista, che sia separato dall'albo, che sia spento finché
  l'amministrazione non lo accende, che ogni copia abbia una fine dichiarata in anni e che
  l'ingresso sia una scelta di una persona con un permesso apposito: sono scelte di questo
  componente (decisione del 29 ottobre 2026). La norma non fissa un termine per le copie
  prive di dati personali: per questo gli anni li dichiara l'amministrazione, senza valore
  proposto, e una copia non resta in rete senza una fine.
- **Non è la "data di oblio" di altri sistemi d'albo**, che richiama l'art. 8 del d.lgs.
  33/2013 per gli atti originali: qui gli anni valgono soltanto per le copie oscurate, e la
  schermata lo dice.

## I nove punti, per il primo passo

### 1. File che si creano o si modificano

| File | Cosa contiene |
|---|---|
| `includes/class-storico.php` | la registrazione della seconda sezione e del tipo, l'impostazione con la sua lettura e il salvataggio con la voce di registro |
| `includes/class-schermata-storico.php` | la schermata "Storico pubblico": norma, scelte, anni, invio con gettone e permesso, avviso di rifiuto |
| `includes/class-permessi.php`, `includes/class-installazione.php` | il permesso apposito, a amministratore e ruolo dell'albo, una volta sola dalla versione 0.7.0-alpha |
| `albo-pretorio-pa.php` | le costanti della sezione e del tipo, la versione 0.7.0-alpha, gli agganci |
| `tests/StoricoTest.php` | le righe A-261..A-265 |
| `tests/ScadutiInvisibiliTest.php` | A-178 rivista: due registrazioni di sezione, ciascuna con le due costanti dritte; i nomi dello storico autorizzati uno per uno |
| `tests/ambiente-albo.php` | l'azzeramento del tipo e dell'impostazione dello storico |
| `docs/requisiti.md`, `docs/collaudo.md`, questa scheda, l'indice delle schede |  |

### 2. Requisito servito

ALBO-36, nuovo. Tocca ALBO-05 e ALBO-34 senza cambiarli: l'atto scaduto resta irraggiungibile
e la consultazione riservata resta l'unico posto dell'originale.

### 3. Percorso di chi usa il sito

Chi ha il permesso apre "Storico pubblico" nel menu degli atti (in cima al menu, se non
gestisce gli atti). Legge che cosa dice la norma, sceglie fra "Acceso" e "Spento", e se
accende scrive gli anni. Salva. Il visitatore, in questo passo, non vede niente di nuovo.

### 4. Dati letti e scritti

L'opzione `albo_pretorio_storico_pubblico`, con scelta, anni, utente e istante. Una voce del
registro per ogni salvataggio, nella sezione dello storico, con scelta e anni.

### 5. Permessi

`albo_pretorio_storico_atto_albo`: lo ricevono amministratore e ruolo dell'albo
all'installazione e all'aggiornamento da una versione precedente alla 0.7.0-alpha, non più
dopo, così che una revoca dell'amministrazione resti. Non lo riceve da sé un altro ruolo che
pubblica. I permessi del tipo dello storico che crea il meccanismo comune non vanno a nessuno.

### 6. Modi di guasto previsti

- Seconda sezione rifiutata dal meccanismo comune: lo storico resta fermo con un avviso,
  l'albo lavora come prima.
- Impostazione salvata ma illeggibile: lo storico vale spento, e la schermata lo dice.
- Registro che non scrive: l'impostazione torna com'era e il salvataggio si rifiuta.
- Lettura dell'opzione che fallisce: non vale "nessuna impostazione". Il salvataggio non
  scrive, e un'impostazione valida non si cancella. Per questo l'opzione si legge dalla banca
  dati a ogni chiamata, senza la memoria delle opzioni.
- Scrittura che la banca dati non ha fatto: la rilettura guarda la banca dati, non la
  memoria, e il salvataggio si rifiuta. Anche il ripristino si rilegge: se non si conferma,
  il rifiuto lo dice invece di dire che tutto è tornato com'era.
- Due persone con la schermata aperta: il modulo porta lo stato che mostrava, e se nel
  frattempo un altro salvataggio l'ha cambiato si rifiuta per conflitto. I salvataggi vanno
  in fila, uno alla volta, con un blocco della banca dati. Una scelta uguale a quella in
  vigore non scrive e non va nel registro.

### 7. Test aggiunti

Righe A-261..A-265 di `collaudo.md`, in `tests/StoricoTest.php`; A-178 rivista.

### 8. Che cosa non fa (in questo passo)

Non crea copie, non le mostra, non le toglie: sono il secondo e il terzo passo. Non porta
nello storico niente in automatico, né in questo passo né dopo. Non tocca gli atti.

### 9. Come si prova su un sito vero

Entrare come amministratore: la voce "Storico pubblico" è nel menu degli atti, nessuna scelta
è segnata. Salvare "Acceso" senza anni: rifiuto con il motivo. Salvare "Acceso" con 5 anni:
l'impostazione si legge sotto il modulo, e il registro delle modifiche ha la voce. Entrare con
un redattore: la voce non c'è.

## I nove punti, per il secondo passo

### 1. File che si creano o si modificano

| File | Che cosa |
|---|---|
| `includes/class-copie-storico.php` | quali atti entrano, l'ingresso tutto o niente, l'uscita, la lettura delle copie |
| `includes/class-schermata-copie.php` | il collegamento nella consultazione degli scaduti, il modulo dell'ingresso, l'elenco con il modulo dell'uscita, i due invii |
| `includes/class-schermata-storico.php`, `includes/class-schermata-scaduti.php` | ospitano le parti nuove; l'esito di ogni invio torna sulla schermata |
| `tests/CopieStoricoTest.php` | righe A-266..A-271 |

### 2. Requisito servito

ALBO-36, ingresso e uscita delle copie.

### 3. Percorso di chi usa il sito

Chi ha il permesso dello storico apre la consultazione degli atti scaduti, trova l'atto e
sceglie "Metti nello storico una copia oscurata". Il modulo mostra numero e date, i documenti
originali da cui preparare la copia, chiede il documento oscurato, propone l'oggetto dell'atto
da rileggere e chiede la conferma. Nella schermata dello storico l'elenco delle copie ha, per
ciascuna, la causa e il motivo per toglierla. Il visitatore, in questo passo, non vede ancora
niente: la pagina pubblica è il terzo passo.

### 4. Dati letti e scritti

La copia è un contenuto del tipo dello storico con i dati elencati in `dati.md`; l'atto non si
tocca. Voci di registro `ingresso_copia` e `uscita_copia`.

### 5. Permessi

Il permesso dello storico per mettere e togliere, verificato su chi fa la richiesta, che è
anche chi il registro scrive. I moduli hanno un gettone per atto e per copia.

### 6. Modi di guasto previsti

- Registro, deposito o pubblicazione della copia che non riescono: la copia, il suo documento
  e il file si tolgono, e l'atto resta senza copia.
- Due ingressi insieme sullo stesso atto: si mettono in fila con un blocco per atto, e chi
  arriva secondo trova l'atto occupato e non scrive nulla. Se il blocco non si prende entro
  cinque secondi, il messaggio chiede di riprovare. Il controllo sulla copia più vecchia
  resta come seconda difesa.
- Due uscite insieme sulla stessa copia: un blocco per copia, e chi arriva secondo trova la
  copia occupata; una sola voce di uscita nel registro.
- Ritiro di una copia mal riuscita che non va a buon fine: dopo il ritiro si rileggono dal
  database le righe e dal disco i file. Se qualcosa resta, la fine della copia si porta a
  ieri, così non si mostra comunque, e il messaggio dice che va tolta a mano.
- Storico spento da un'altra richiesta mentre l'ingresso aspetta il blocco, oppure
  impostazione o dati dell'atto (numero, anno, fine) che non si leggono: con il blocco preso
  si rileggono, e se mancano l'ingresso si ferma prima di creare la copia. Una lettura che
  non risponde non diventa mai zero anni o un atto senza numero.
- Atto annullato o prorogato da un'altra richiesta mentre la copia entra: annullamento e
  proroga non passano dal blocco dell'atto, perché la proroga ha già un turno suo per tutto
  il sito e l'annullamento non ne usa. Scelta: il controllo dell'atto si rifà sulla banca
  dati, senza la memoria, con il blocco preso e poi di nuovo subito prima del registro; se
  l'atto non è più ammissibile, prima non si crea niente, dopo la copia si ritira. Resta solo
  l'istante fra l'ultimo controllo e la voce del registro.
- Elenco delle copie che non si legge: lo schermo lo dice, invece di mostrare un elenco
  vuoto.
- Atto entrato il 29 febbraio: in un anno non bisestile la fine cade il 28 febbraio.
- Registro che non scrive l'uscita: la copia resta fuori, e il messaggio chiede di annotare a
  parte chi l'ha tolta e perché. Fra una copia tolta senza voce e una copia rimessa in rete
  contro chi la toglie, si sceglie la prima.
- Un percorso del server mandato al posto del caricamento: l'origine dichiarata è sempre il
  caricamento, e il meccanismo comune lo rifiuta.

### 7. Test aggiunti

Righe A-266..A-271 di `collaudo.md`, in `tests/CopieStoricoTest.php`.

### 8. Che cosa non fa (in questo passo)

Non mostra niente sul sito pubblico. Nell'elenco delle copie non c'è il collegamento al
documento della copia: la consegna amministrativa pretende i permessi del tipo dello storico,
che non ha nessuno; il documento si aprirà dalla pagina pubblica. Non sostituisce una copia:
per rifarla la si toglie e se ne mette una nuova.

### 9. Come si prova su un sito vero

Accendere lo storico con tre anni. Dalla consultazione degli scaduti scegliere un atto
scaduto, caricare un PDF oscurato, correggere l'oggetto, confermare. La copia è nell'elenco
dello storico con la fine a tre anni, e il registro ha la voce. Toglierla con la causa e il
motivo: sparisce dall'elenco, e il registro ha la voce dell'uscita.

## I nove punti, per il terzo passo

### 1. File che si creano o si modificano

| File | Che cosa |
|---|---|
| `includes/class-pagina-storico.php` | il codice breve `[albo_pretorio_storico]`, le pagine che lo contengono, quando una copia si mostra, la "non trovata", la porta del punto di consegna |
| `includes/class-vista-storico.php` | elenco in tabella, pagine dell'elenco, scheda della copia con l'avviso e il documento |
| `includes/class-pagina-pubblica.php` | la pagina dello storico riceve le regole della risposta dell'albo: divieto ai motori e alle memorie, fuori dalla mappa del sito, foglio di stile |
| `includes/class-vista-pubblica.php` | quattro pezzi del disegno resi usabili dallo storico, senza cambiarli |
| `tests/PaginaStoricoTest.php` | righe A-272..A-277 |

### 2. Requisito servito

ALBO-36, la pagina pubblica dello storico.

### 3. Percorso di chi usa il sito

L'amministrazione scrive `[albo_pretorio_storico]` in una pagina diversa da quella dell'albo.
Il visitatore vede un avviso che dice che sono copie con i dati personali oscurati e come
chiedere l'originale, poi l'elenco: numero di registro, tipo, oggetto della copia, periodo
all'albo, fino a quando la copia resta consultabile. Dalla scheda scarica la copia oscurata
del documento. Spento lo storico, la pagina resta ma il codice breve non disegna niente.

### 4. Dati letti e scritti

Soltanto letture: la copia, i suoi dati fissati all'ingresso, il suo documento, la sua fine,
l'impostazione dello storico. Nessuna scrittura.

### 5. Permessi

Nessuno: è una pagina pubblica, in sola lettura. Il tipo della copia resta chiuso agli
indirizzi di WordPress e all'interfaccia per programmi; si dichiara consultabile soltanto per il
punto di consegna, con lo storico acceso e la pagina presente.

### 6. Modi di guasto previsti

- Storico spento, copia tolta o finita, copia mai esistita, numero scritto male: la scheda
  risponde "non trovata" con le stesse intestazioni della "non trovata" dell'albo, e il
  documento non esce.
- Un altro file appeso alla copia, o il documento originale chiesto con il numero della copia:
  non esce, perché si consegna soltanto il documento registrato nella copia.
- Una pagina con il codice dell'albo e quello dello storico insieme: è la pagina dell'albo, e
  lo storico non vi si disegna.
- Elenco delle copie, o dati e documento delle copie, che non si leggono: la pagina lo dice,
  invece di mostrare un elenco vuoto o più corto. Dati e documento di tutte le copie si leggono
  in una volta, con una lettura che distingue l'assenza dalla risposta mancata.
- Una pagina dell'elenco che non esiste o scritta male: la pagina lo dice e porta alla prima.

### 7. Test aggiunti

Righe A-272..A-277 di `collaudo.md`, in `tests/PaginaStoricoTest.php`. Mutazioni provate:
togliere il controllo che il documento chiesto sia quello della copia fa fallire A-275;
togliere la pagina dello storico dalle risposte con il divieto fa fallire A-276.

### 8. Che cosa non fa (in questo passo)

La ricerca, per parole dell'oggetto della copia o per numero di registro, è arrivata in una richiesta di unione a sé (A-278, A-279); non cerca mai nell'oggetto originale, che la copia non porta con sé. Non mostra mai
l'oggetto originale, il documento originale, il richiedente o i dati del procedimento.

### 9. Come si prova su un sito vero

Con una copia già nello storico, creare una pagina con `[albo_pretorio_storico]`. Da un
browser senza accesso: la copia è nell'elenco, la scheda ha l'avviso e il documento oscurato
si scarica; l'indirizzo della scheda dell'atto sulla pagina dell'albo resta "non trovata".
Nel sorgente della pagina c'è `noindex`. Spegnere lo storico: la pagina è vuota, la scheda
"non trovata", il documento non si scarica. Riaccenderlo: tutto torna.

## Decisioni prese

1. **Si costruisce**, con le quattro regole proposte dal coordinatore (29 ottobre 2026):
   spento finché l'ente non lo accende, niente in automatico, mai l'originale, tutto nel
   registro.
2. **Anni scelti dall'amministrazione** (29 ottobre 2026): ogni copia ha una fine, il
   meccanismo comune resta com'è.
3. **Scelte del costruttore, annunciate senza obiezioni** (29 ottobre 2026): entrano solo
   gli atti arrivati regolarmente alla fine, importati compresi, non gli annullati né i tolti
   in anticipo; la copia ha un oggetto proprio, coperto dalla conferma; il richiedente non
   compare mai; pagina propria, avviso "copia per consultazione", divieto ai motori; spento
   vuol dire niente sul sito, con le copie conservate.
4. **Chi agisce è chi fa la richiesta** (29 ottobre 2026): ingresso e uscita non ricevono
   l'utente come parametro, perché il registro scrive sempre l'utente della richiesta e i due
   devono coincidere.

## Com'è andata davvero

(da scrivere a lavoro finito)
