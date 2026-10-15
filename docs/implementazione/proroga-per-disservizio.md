# Proroga per disservizio

Scheda di lavorazione. Scritta prima del codice come piano; si aggiorna a lavoro finito con
com'è andata davvero.

**Stato: costruita, sulla proposta del punto aperto 1, che aspetta conferma.** Requisito
ALBO-31, righe di collaudo A-127..A-138 e A-147..A-155, tutte a fatto tranne A-134 (il
referto). Com'è andata davvero è in fondo.

## In tre paragrafi

**Cosa costruiamo.** Quando il sito è rimasto giù, un giorno in cui l'albo è stato
consultabile per meno di dodici ore non vale come giorno di pubblicazione, e gli atti in
pubblicazione quel giorno devono restare esposti un giorno in più. Chi pubblica apre la
schermata **Proroga per disservizio**, indica i giorni sotto soglia, che devono essere già
passati, e scrive gli estremi dell'attestazione. La schermata mostra prima quali atti
cambieranno e come; alla conferma ciascuno riceve tanti giorni in più quanti sono i giorni
sotto soglia caduti nel suo periodo. Numero di repertorio e data di inizio non cambiano. È
l'unico modo in cui la data di fine di un atto pubblicato va avanti.

**Come la costruiamo.** La proroga è un terzo passaggio, accanto a pubblicazione e rimando,
e ne usa le stesse difese: permesso controllato, transazione della banca dati, atti fermi
per tutta la durata, gettone che autorizza la sola scrittura prevista, rilettura prima di
chiudere. La differenza è che tocca molti atti insieme, e o li proroga tutti o non ne
proroga nessuno. Ogni giorno attestato si scrive nel registro delle modifiche con una
chiave unica: la banca dati stessa impedisce che lo stesso giorno sia attestato due volte,
anche da due persone nello stesso istante.

**Come si prova che funziona, e come si prova che il test è vero.** Ogni riga di collaudo
fissa l'orologio, pubblica atti veri con il passaggio già costruito, dispone la proroga e
rilegge dalla banca dati la data di fine, il registro e la fotografia intera degli atti non
toccati. Il "tutto o niente" si prova facendo fallire di proposito una scrittura per volta.
A lavoro finito si introducono guasti nel codice e si guarda quale prova li vede.

## I nove punti

### 1. File che si pensa di creare o modificare

| File | Cosa conterrà |
|---|---|
| `includes/class-proroga.php` | il calcolo dei giorni per atto, la proroga dentro la transazione, le voci di registro |
| `includes/class-schermata-proroga.php` | la schermata: giorni, attestazione, anteprima, conferma, gettone di sicurezza |
| `includes/class-passaggi.php` | il passaggio in corso su più atti, la concessione della sola data di fine |
| `includes/class-chiusura-pubblicazione.php` | il fermo che lascia passare la data di fine di un atto pubblicato solo dentro una proroga in corso e solo al valore calcolato |
| `includes/funzioni-api.php` | `albo_pretorio_proroga()` per chi scrive codice |
| `tests/ProrogaTest.php` | le prove |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/architettura.md`, `docs/dati.md` | righe e stati |

### 2. Perché

ALBO-31, deciso il 2026-10-08. La norma è la regola delle dodici ore delle linee guida AgID
sulla pubblicità legale: il giorno conta se il documento è stato disponibile per almeno
dodici ore complessive, e il periodo si proroga di un giorno per ogni giorno sotto soglia,
sulla base di un'attestazione del responsabile della pubblicazione o di un suo delegato.
Gli estremi del punto vanno ancora verificati sul testo ufficiale. Senza la proroga l'unica
strada dopo un guasto sarebbe ripubblicare, con un numero nuovo e il conteggio da capo.

### 3. Il flusso per chi usa il sito

Chi pubblica trova nel menu dell'albo la voce **Proroga per disservizio**. La schermata
spiega in poche righe la regola delle dodici ore e chiede due cose: i giorni sotto soglia
(uno o più, già passati) e l'attestazione, cioè un testo con gli estremi del documento che
attesta il disservizio (per esempio il numero di protocollo). Il pulsante **Anteprima**
mostra, senza scrivere niente, l'elenco degli atti che saranno prorogati: numero, oggetto,
fine attuale, fine nuova. Se nessun atto è toccato lo dice. Il pulsante **Disponi la
proroga**, sotto l'anteprima, dispone quella e solo quella: i giorni e l'attestazione
mostrati, non i campi del modulo. Se nel frattempo il risultato è cambiato, niente si
scrive e si rifà l'anteprima. Sotto, l'elenco delle proroghe già disposte, con giorni,
attestazione, chi e quando.

Una proroga non si annulla. Un errore si corregge con una defissione anticipata (ALBO-22),
quando ci sarà.

### 4. Dati letti e scritti

**Il calcolo, per ciascun atto.** Il periodo di un atto sono i giorni dal giorno dopo
l'inizio fino alla fine compresa, nel fuso del sito: il giorno di pubblicazione non conta,
come nel calcolo della fine (ALBO-27). Si contano i giorni sotto soglia che cadono nel
periodo; se sono zero, l'atto non è toccato. Altrimenti la fine nuova è il più tardo fra la
fine attuale e il giorno della proroga, più quei giorni (vedi il punto aperto 1).

**Gli atti candidati** sono quelli pubblicati, letti dalla banca dati sotto il blocco della
transazione. Bozze, atti in verifica e annullati non si toccano mai.

**Si scrive**, dentro una sola transazione:

- per ciascun giorno attestato, una voce di sezione `giorno_sotto_soglia` con la chiave
  `disservizio-AAAA-MM-GG`, unica nel registro;
- una voce di sezione `proroga_per_disservizio` con l'attestazione come motivazione e,
  nei dettagli, i giorni e gli atti toccati;
- per ciascun atto toccato, la data di fine con la funzione del meccanismo comune, che
  scrive da sola la voce automatica `modifica_fine_pubblicazione`, e una voce
  `proroga_atto` con fine precedente, fine nuova, giorni contati e numero della voce della
  proroga.

**Si legge dal registro**, per le proroghe successive, quali giorni sono già attestati e,
per gli atti riaperti, quali giorni non erano di pubblicazione (i giorni fra la vecchia fine
e la proroga): non contano come giorni sotto soglia.

### 5. Permessi

Disporre la proroga richiede il permesso di pubblicare atti del tipo dell'albo, quello che
già governa la pubblicazione. Chi ha il solo permesso di redigere non vede la voce di menu
e, se manda la richiesta direttamente, è rifiutato. La schermata ha il suo gettone di
sicurezza. Nessun permesso nuovo.

### 6. Modi di guasto previsti

- Proroga senza attestazione, o con un giorno di oggi o futuro.
- Lo stesso giorno attestato due volte, anche da due richieste insieme: la chiave unica del
  registro lo impedisce nella banca dati, non solo nel codice.
- **Un atto prorogato a metà**: la fine di uno scritta e quella di un altro no, o la fine
  scritta senza la sua voce. Si provano uno per volta, forzando il guasto.
- Una data di fine spostata in avanti da qualunque altra strada: schermata dell'atto,
  metadati da codice, interfaccia per programmi. Il fermo di A5b oggi rifiuta già ogni
  scrittura su un atto pubblicato; la concessione nuova vale solo dentro una proroga in
  corso, solo per la data di fine e solo per il valore calcolato.
- Un aggancio di un altro componente che, durante la proroga, scrive su un atto prorogato o
  avvia un altro passaggio: rifiutato come nei passaggi già costruiti.
- Un atto pubblicato dopo i giorni indicati, o pubblicato proprio in uno di quei giorni, che
  riceve giorni che non gli spettano.
- Il giorno calcolato sul tempo universale invece che sul fuso del sito.
- Due proroghe insieme, la seconda calcolata su una fine che la prima sta cambiando.
- Una conferma che non corrisponde all'anteprima mostrata: campi cambiati dopo, un'altra
  proroga o la mezzanotte nel mezzo.

### 7. Prove che si aggiungeranno

Righe A-127..A-138 e A-147..A-155 di `collaudo.md`. A-134, il referto, resta da fare finché non esiste il
referto (ALBO-07). A-142, slittamento ai festivi dopo una proroga, appartiene a ALBO-32 e si
chiude con quella unità.

### 8. Cosa NON si implementa

- La misura della disponibilità: il componente non sa da solo se il sito è stato giù, e non
  lo indovina. Il disservizio lo attesta una persona, come vuole la regola.
- L'annullamento di una proroga.
- Lo slittamento ai festivi (ALBO-32) e il referto (ALBO-07).
- La pagina pubblica: un atto prorogato è consultabile nel senso del meccanismo comune, che
  legge la data di fine; la pagina che lo mostra arriva con la sua unità.

### 9. Come si prova su un'installazione reale

Su una copia del sito: pubblicare due atti, uno con fine fra qualche giorno e uno con fine
ieri; disporre una proroga per un giorno della settimana passata; controllare nell'anteprima
e poi nel registro le due fini nuove, e che il secondo atto torni consultabile fino alla
nuova fine.

## Punti aperti

1. **Atto già uscito dalla vista** (costruito sulla proposta, in attesa di conferma). Se un atto aveva la fine il 10, il 10 il sito è stato
   giù e la proroga viene disposta il 13, contare il giorno in più dalla vecchia fine porta
   la fine nuova all'11, già passato: l'atto non tornerebbe mai visibile e il suo periodo
   resterebbe incompleto. **Proposta**: contare i giorni mancanti dal giorno della proroga,
   che non conta come il giorno di pubblicazione; l'atto torna consultabile fino al 14.
   Alternative: non toccare gli atti già usciti, che l'ente ripubblica con un numero nuovo;
   oppure contare dalla vecchia fine anche se cade nel passato. Riga A-135.

## Com'è andata davvero

**Differenze dal piano.** Nessuna nella sostanza. `class-chiusura-pubblicazione.php` non è cambiato: la concessione dei passaggi in corso, che lascia passare la sola data di fine attesa, bastava anche per molti atti insieme. I giorni si indicano nella schermata come
primo e ultimo di una serie consecutiva; giorni non consecutivi sono proroghe separate. Una
proroga attesta al massimo 366 giorni. Un atto pubblicato senza data di fine leggibile non si
salta: la proroga si rifiuta e lo nomina. I messaggi dei pezzi comuni ai passaggi parlano
dell'atto in verifica: un rifiuto della proroga ha come primo messaggio
`albo_proroga_non_riuscita`, e il motivo vero resta fra gli altri con il suo codice.

**Due difese per il giorno ripetuto.** I giorni già attestati si leggono dal registro prima
di cominciare, anche nell'anteprima; poi la chiave unica della voce di ciascun giorno lo
impedisce nella banca dati, se un'altra richiesta lo ha attestato nel frattempo. A-136
prova tutte e due.

**Prova dei guasti.** Tredici guasti introdotti uno per volta nel codice, ciascuno visto da
almeno una prova: giorno di pubblicazione contato (A-128); conteggio dalla vecchia fine e
giorni fuori periodo contati (A-135); permesso non controllato (A-130); oggi ammesso e oggi
calcolato in tempo universale (A-131); attestazione facoltativa (A-129); giorni già
attestati non letti (A-136, dall'anteprima); errore confermato invece che annullato (A-137);
atti in verifica al posto dei pubblicati (sette righe); qualunque data di fine ammessa
durante la proroga (A-133); controllo finale tolto (A-133, con il dato scritto a mano);
anteprima che scrive (A-127, A-138). Due erano sfuggiti al primo giro, i giorni ripetuti e
il controllo finale: le prove sono state completate.

**Secondo giro: tre difetti trovati dalla revisione e corretti.**

- *Due proroghe insieme.* Il calcolo si faceva prima di bloccare gli atti: se un'altra
  proroga confermava nel frattempo, si scriveva una fine calcolata su quella vecchia. Ora
  le proroghe prendono un turno nella banca dati, una alla volta fra tutte le richieste
  (si aspetta al massimo cinque secondi, poi si rifiuta e si riprova dall'anteprima), e il
  calcolo si fa dopo, a transazione aperta. A-149.
- *Il fermo senza atti.* Il fermo degli altri passaggi era legato agli atti toccati: una
  proroga che non ne toccava nessuno lasciava cominciare un rimando in bozza dentro la sua
  transazione. Ora il fermo vale per l'operazione, dall'inizio alla fine, comunque. A-150.
- *Conferma e anteprima.* Il pulsante di conferma rimandava i campi del modulo: bastava
  cambiarli dopo l'anteprima per disporre altro. Ora l'anteprima si conserva per utente con
  un codice casuale, per mezz'ora; la conferma manda solo il codice, e si dispone quello che
  l'anteprima ha mostrato. Dentro la transazione il risultato si ricalcola e si confronta
  con quello mostrato: se un atto, una fine attuale o una fine nuova sono diversi, niente
  si scrive. Lo stesso confronto è disponibile da codice, con la chiave `attese`. A-147,
  A-148.

Guasti del secondo giro, tutti visti: calcolo prima del turno (A-149), turno non lasciato
(A-149), fermo legato ai soli atti (A-150), codice dell'anteprima non controllato o
comune a tutti gli utenti (A-147), anteprima non consumata (A-147), risultato mostrato non
passato o non confrontato (A-148).

**Terzo giro: altri tre rilievi, corretti.**

- *La transazione di chi chiama.* Se un programma chiama la proroga dentro una sua
  transazione, la proroga non ne apre una nuova ma mette un punto di ripristino, e legge i
  dati come la transazione di quel programma li ha visti la prima volta: se nel frattempo
  un'altra richiesta ha confermato una proroga, il calcolo partirebbe dalla fine vecchia.
  Ora, prima di calcolare, la proroga legge gli atti, le date di fine e le voci di registro
  dell'albo due volte: una bloccandoli, che aspetta le scritture in corso e vede l'ultima
  versione confermata, e una come le legge il calcolo. Se differiscono si rifiuta senza
  scrivere niente. I blocchi restano fino alla chiusura della transazione di chi chiama,
  anche dopo che il turno è stato lasciato, e la proroga successiva li aspetta con le
  stesse letture. Nella transazione aperta dalla proroga stessa le due letture coincidono
  sempre. A-151.
- *Il turno non lasciato.* Il rilascio del turno non si controllava. Ora si legge la
  risposta della banca dati: se non conferma, la proroga, già confermata, lo dice con un
  rifiuto che porta l'esito e spiega che il turno si libera alla chiusura della
  connessione. Un'eccezione di un altro componente durante il rilascio non salta più lo
  svuotamento della memoria: esce dopo, con un messaggio che dice che la proroga è
  confermata. A-152.
- *La prova del turno.* A-149 ora controlla con una seconda connessione vera che il turno
  resti preso mentre la proroga scrive, e che si liberi dopo.

Guasti del terzo giro: confronto delle due letture tolto, letture che non bloccano (A-151);
turno lasciato subito dopo averlo preso (A-149, A-152); rilascio non verificato, eccezione
del rilascio non trattenuta (A-152), tutti visti. Uno era rimasto verde, lo svuotamento
della memoria tolto dopo un'eccezione del rilascio: la prova è stata rafforzata nel quarto
giro.

**Quarto giro: due difetti e una prova, corretti.**

- *Il nome nell'indirizzo.* La concessione che lascia a WordPress scrivere il nome
  nell'indirizzo di un atto appena pubblicato valeva per ogni passaggio in corso. Ora il
  passaggio dichiara il suo tipo (pubblicazione, rimando, proroga), e la concessione vale
  solo per la pubblicazione, nel guardiano e nel controllo finale. A-153.
- *La presa rimasta.* Il turno nella banca dati è rientrante: dopo un rilascio fallito, la
  proroga successiva sulla stessa connessione lo prendeva una seconda volta e, lasciandone
  una sola presa, si diceva libera mentre il turno restava occupato. Ora, prima di prendere
  il turno, la proroga lascia la presa rimasta da una proroga finita (o si rifiuta se non ci
  riesce), e dopo il rilascio controlla che la connessione non tenga più il turno. A-152.
- *La prova della memoria.* In A-152 il componente che solleva l'eccezione rimette in
  memoria la fine di prima: la prova vede se lo svuotamento finale la toglie.

Guasti del quarto giro, tutti visti: concessione del nome a ogni passaggio (A-153), presa
rimasta non recuperata (A-152, A-153), turno creduto libero sulla sola risposta del
rilascio (A-152), svuotamento della memoria tolto dopo l'eccezione (A-152).

**Quinto giro: un difetto e una prova, corretti.**

- *La memoria dopo un annullamento.* Lo svuotamento dopo un rilascio del turno non riuscito
  valeva solo per la proroga confermata. Ora, se il rilascio non riesce o un componente
  solleva un'eccezione, la memoria si svuota per intero su ogni strada, confermata,
  annullata o interrotta da un'eccezione, prima di rispondere. A-152.
- *Le due difese del nome.* A-153 ora guarda il guardiano subito dopo la scrittura del nome,
  e scrive il nome anche scavalcando il guardiano, perché lo veda il controllo finale: ogni
  difesa tolta da sola fa fallire la prova.

Guasti del quinto giro, tutti visti: svuotamento dopo il rilascio mancato tolto (A-152);
concessione del nome riportata a ogni passaggio nel solo guardiano, poi nel solo controllo
finale (A-153). Lo svuotamento atto per atto dopo un'eccezione del rilascio, tolto, ora resta
verde: lo copre lo svuotamento intero, che viene prima.

**Sesto giro: un difetto e una prova, corretti.**

- *Le concessioni durante il rilascio.* Le concessioni sugli atti prorogati, che lasciano
  scrivere la sola data di fine attesa, restavano attive durante il rilascio del turno,
  quando la transazione era già confermata o annullata: un aggancio poteva cancellare la
  fine, o scrivere quella tentata dopo un annullamento, senza controllo finale e senza
  registro. Ora le concessioni finiscono con la transazione, prima del rilascio; il fermo
  dell'operazione resta fino alla fine, contro i passaggi annidati. A-154.
- *La memoria dopo l'annullamento.* A-137 guardava gli atti con la fotografia, che pulisce
  la memoria prima di leggere: ora un aggancio legge la fine tentata durante la scrittura, e
  la prova guarda la memoria prima di qualunque pulizia.

Guasti del sesto giro, tutti visti: concessioni tolte dopo il rilascio, fermo tolto insieme
alle concessioni (A-154); svuotamento della memoria tolto dall'annullamento (A-137).

**Settimo giro: un difetto, una lacuna e una prova, corretti.**

- *Le concessioni durante i controlli finali.* Le concessioni restavano attive dopo
  l'ultima scrittura, mentre si controllavano gli atti uno per uno e fino alla conferma: un
  aggancio durante il controllo del secondo atto poteva cancellare la fine del primo, già
  controllato, e la conferma l'avrebbe resa definitiva. Ora finita l'ultima scrittura le
  concessioni si revocano tutte, anche nella condizione del guardiano dentro la banca dati;
  restano il fermo dell'operazione, il tipo e i rifiuti annotati. A-155.
- *La memoria dopo un rilascio riuscito.* Dopo un annullamento la memoria si svuotava di
  nuovo solo se il rilascio del turno non riusciva: un componente che rimette in memoria la
  fine tentata e lascia riuscire il rilascio la lasciava lì. Ora lo svuotamento dopo il
  rilascio vale su ogni strada. A-152.
- *Il guasto sul terzo atto.* A-137 riconosce la voce del terzo atto dalla sua fine nuova
  e conta le voci passate prima: un guasto che blocca già la prima voce fa fallire la prova.

Guasti del settimo giro, tutti visti: revoca tolta e guardiano che ignora la revoca
(A-155); svuotamento dopo il rilascio di nuovo condizionato (A-152); guasto su qualunque
voce di atto (A-137).

**Limiti dichiarati.** Come nei passaggi già costruiti, quello che altri componenti fanno
negli agganci durante la scrittura non si ritira con un annullamento se esce dalla banca
dati. La proroga non si annulla.
