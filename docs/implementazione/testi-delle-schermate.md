# Testi delle schermate

Correzioni di frasi e di etichette che dicevano una cosa non vera o lasciavano l'addetto
senza sapere cosa fare. Sono emerse rileggendo le schermate dal punto di vista di chi le
usa, una per una. Nessuna cambia un comportamento: cambiano solo le parole. Righe di
collaudo A-280..A-285.

## 1. Cosa cambia e perché

**Dati eccedenti in un atto pubblicato (A-280).** Il riquadro "Togli dall'albo prima della
scadenza" e il rifiuto della defissione per dati eccedenti dicevano che i dati "si oscurano
sostituendo il documento". La sostituzione per oscuramento di un atto pubblicato (ALBO-12)
non è ancora costruita: l'addetto cercava un pulsante che non c'è. Ora le due frasi dicono
che i dati si oscurano nel documento, che la sostituzione per un atto pubblicato non è
ancora disponibile e che il caso si segnala subito all'amministratore del sito. Il rifiuto
resta quello di prima: i dati eccedenti non sono una causa di rimozione.

**Proroga per disservizio (A-281).** La schermata diceva che un errore nella proroga "si
corregge con una defissione anticipata". Non è vero: la defissione si fa solo per una delle
cause dell'elenco, e una proroga sbagliata non è fra queste. Ora dice che la proroga non si
annulla e non si corregge, e che per questo giorni e atti si controllano nell'anteprima
prima di confermare. L'anteprima c'era già: la frase dice a cosa serve.

**Un allegato alla volta (A-282).** Sotto il campo dell'allegato c'era "Un file per
salvataggio", troppo corta per chi ha un atto con molti allegati. Ora dice il percorso:
scelto il file si salva la bozza, e il campo torna libero per il prossimo. La scelta di più
file insieme non è in questa unità.

**Voci del menu (A-283).** Il tipo atto non dichiarava l'etichetta della voce per l'atto
nuovo, e WordPress mostrava quella degli articoli ("Aggiungi nuovo articolo"). Si dichiarano
le etichette che compaiono nel menu e nell'elenco degli atti: "Nuovo atto", "Tutti gli
atti", "Vedi atto", "Nessun atto nel cestino.".

**Avviso nel riquadro di invio di WordPress (A-284, A-285).** Il riquadro di invio di
WordPress, in alto a destra nella schermata dell'atto, mostra a chi pubblica il pulsante
"Pubblica", che l'albo rifiuta: l'atto si pubblica dal riquadro Pubblicazione quando è in
verifica. E chi manda un atto in verifica cambiando nello stesso invio anche un dato se lo
vede rifiutare, giustamente, e lo scopre solo dal messaggio. Nella bozza dell'atto, sotto
la riga dello stato, compare ora un avviso fisso:

- a chi redige soltanto: prima si salva la bozza con dati e documenti, poi si preme
  "Invia per la revisione";
- a chi pubblica: prima si salva la bozza, poi si sceglie "In attesa di revisione" nel
  menu Stato e si salva di nuovo; e il pulsante "Pubblica" del riquadro non pubblica
  l'atto, che si pubblica con "Pubblica l'atto" del riquadro Pubblicazione.

L'avviso non compare in un atto in verifica o pubblicato, in un contenuto di altro tipo,
né a chi non può modificare l'atto. I nomi dei pulsanti di WordPress sono quelli della
traduzione italiana, da confermare sulla copia di prova. Il pulsante di WordPress resta
dov'è: nasconderlo o rinominarlo toccherebbe una schermata di WordPress, e l'avviso basta
a togliere il tentativo a vuoto.

## 2. File toccati

`includes/class-passaggi.php` e `includes/class-riquadro-passaggi.php` (dati eccedenti),
`includes/class-schermata-proroga.php` (proroga), `includes/class-scheda-documenti.php`
(allegati), `includes/class-tipo-atto.php` (etichette). Per l'avviso,
`RiquadroPassaggi::avviso_invio()` agganciato a `post_submitbox_misc_actions` in
`albo-pretorio-pa.php`. Nei test: `DefissioneTest`, `ProrogaTest`, `DocumentiAttoTest`,
`TipoAttoTest`, `AvvisoInvioTest`.

## 3. Cosa non fa

Non costruisce la sostituzione per oscuramento (ALBO-12) né la scelta di più file insieme.
Non nasconde né rinomina il pulsante "Pubblica" di WordPress e non aggiunge un pulsante
"Manda in verifica" al riquadro dell'albo.

## 4. Come si prova su un'installazione reale

Su un atto pubblicato e non scaduto, aprire il riquadro "Togli dall'albo prima della
scadenza" e leggere la frase sui dati eccedenti; scegliere la causa dei dati eccedenti da
codice e leggere il rifiuto. Aprire la schermata della proroga e leggere la seconda frase.
In una bozza, leggere la frase sotto il campo dell'allegato. Nel menu dell'albo, leggere le
voci "Tutti gli atti" e "Nuovo atto". In una bozza, una volta come utente che redige
soltanto e una volta come responsabile, leggere l'avviso sotto lo stato nel riquadro di
invio e confrontare i nomi dei pulsanti con quelli della schermata.
