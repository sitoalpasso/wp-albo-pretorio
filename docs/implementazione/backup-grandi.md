# Backup grandi e tempo della richiesta

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: passo 1 fatto, passo 2 proposto.** Correttezza dei meccanismi di ALBO-35. Righe di
collaudo da A-347.

## In tre paragrafi

**Cosa costruiamo.** Per leggere un backup l'albo calcola l'impronta di ogni file
dell'archivio. In più calcola l'impronta dell'archivio intero, che serve a riconoscere lo stesso
backup da una richiesta all'altra. Con un archivio di qualche gigabyte questi conti richiedono
più tempo di quello che il server concede a una richiesta. Il server allora interrompe la
pagina a metà, senza un messaggio, e chi importa non sa che cosa è successo. Il conto si rifà
a ogni passo: nel rapporto a secco, all'avvio e a ogni blocco dell'importazione vera. Il
lavoro ha due passi. Il primo, in questa scheda già fatto, fa sì che la lettura si fermi da
sola prima del limite e dica che cosa fare. Il secondo, proposto, toglie il problema: le
impronte si calcolano una volta sola, a blocchi, e si riusano.

**Come la costruiamo (passo 1).** La schermata dell'importazione conosce il tempo che il
server concede a una richiesta. La lettura del backup ne riceve i tre quarti, contati da
quando la richiesta è arrivata; il quarto che resta serve al rapporto e alla pagina. La
lettura guarda l'orologio prima di ogni voce dell'archivio, dopo ogni pezzo letto e mentre
legge il file SQL. Se il tempo è finito si ferma e risponde con un messaggio: quante voci
aveva letto, che non ha scritto niente, che va alzato il tempo massimo di esecuzione di PHP
(`max_execution_time`). Lo stesso vale per l'impronta dell'archivio intero, che ora si legge a
pezzi invece che tutta d'un colpo. Quando il server non pone limiti, non cambia niente.

**Come si prova che funziona, e come si prova che il test è vero.** Un archivio da gigabyte in
una prova non serve: basta una richiesta arrivata cento secondi prima con un limite di trenta,
così il tempo della lettura è già finito. Il limite si fissa per le prove senza toccare
`max_execution_time`, che armerebbe davvero il limite del processo delle prove. Si prova ogni
strada: l'impronta dell'archivio, il rapporto, l'avvio, un blocco. Accanto a ciascuna c'è il
controllo positivo, la stessa strada in tempo. I guasti introdotti a mano sono nella tabella in
fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-lettore-backup.php`: la scadenza della lettura, il controllo del tempo,
  l'errore del tempo, l'impronta di un file intero letta a pezzi.
- `includes/class-schermata-importazione.php`: la scadenza della lettura, ricavata dal tempo
  concesso alla richiesta, passata al rapporto e al modulo dell'importazione.
- `includes/class-importazione.php`: avvio e blocchi leggono con la stessa scadenza.
- `tests/ImportazioneVeraTest.php`: A-347.

### 2. Requisito servito

Nessun requisito nuovo. È la correttezza dell'importazione (ALBO-35) con un backup grande.

### 3. Il percorso di chi usa il sito

Con un backup che sta nel tempo, niente cambia. Con uno che non ci sta, al posto di una pagina
interrotta compare il messaggio. Chi gestisce il sito alza il limite, o lo chiede a chi
gestisce il server, e riprova. Finché non c'è il passo 2 questa resta l'unica strada per un
backup molto grande.

### 4. Dati letti e scritti

Nessuno nuovo. La lettura continua a non scrivere niente.

### 5. Permessi coinvolti

Nessuno nuovo.

### 6. Modi di guasto previsti

- La lettura che non riceve la scadenza, o che non la guarda: A-347.
- L'impronta dell'archivio che si calcola tutta d'un colpo, senza scadenza: A-347.
- Un blocco fermato a metà che lascia l'importazione in uno stato diverso da quello di prima:
  A-347.

### 7. Test aggiunti

A-347.

### 8. Cosa non implementa

- **Il passo 2**, descritto sotto. Con il solo passo 1 un backup da 2,6 GB si importa ancora
  solo alzando il limite, e ogni blocco rilegge tutto l'archivio.
- **I limiti del server diversi da quello di PHP.** Davanti a PHP può esserci un altro limite,
  per esempio quello del server web o del suo intermediario (FastCGI, proxy). L'albo non lo
  conosce: se è più basso di quello di PHP, la pagina si interrompe ancora senza messaggio.
- **Il tempo misurato da PHP.** Su Linux PHP conta il tempo di calcolo, non quello
  dell'orologio. La lettura guarda l'orologio, che corre almeno altrettanto: al più si ferma
  un po' prima del necessario, mai dopo.

### 9. Come si prova su un'installazione reale

Sulla copia di prova, con il backup vero e il limite di PHP rimesso al valore di partenza:
leggere il backup dalla schermata dell'importazione. Al posto della pagina interrotta deve
comparire il messaggio con il numero di voci lette. Poi alzare il limite e rileggere.

## Il passo 2, proposto

**Le impronte si calcolano una volta, a blocchi, e si ricordano.** Il conto pesante dipende
solo dal contenuto dell'archivio, e oggi si rifà fino a quattro volte nella sola richiesta
che avvia l'importazione, poi due volte a ogni blocco. Il passo 2 lo fa una volta:

1. Scelto il backup, una schermata di preparazione calcola le impronte a blocchi, una
   richiesta dopo l'altra, come fa già l'importazione vera con gli atti. Ogni blocco si ferma
   prima della scadenza e riprende da dove era arrivato. La pagina mostra quante voci mancano.
2. Le impronte, quella dell'archivio intero compresa, si salvano in un'impostazione legata a
   quel file. La legano il nome, la dimensione, la data di modifica del file e l'elenco delle
   voci dell'archivio con i loro controlli interni, che si legge in un attimo senza
   decomprimere niente.
3. Il rapporto a secco, l'avvio e i blocchi usano le impronte salvate se il legame regge. Se
   il file è cambiato, si rifà la preparazione.

**La decisione che serve.** Oggi a ogni blocco si ricalcola l'impronta dell'archivio intero,
e così si vede se il file è cambiato a metà dell'importazione. Con il passo 2 il controllo
diventa il legame del punto 2: dimensione, data di modifica e controlli interni dello zip.
Questo legame non vede un file riscritto con la stessa dimensione, la stessa data e gli
stessi controlli interni. Può farlo solo chi ha accesso in scrittura alla cartella dei
backup, cioè chi amministra il server. In alternativa si tiene il ricalcolo completo
dell'impronta, ma a blocchi anch'esso, e l'importazione resta lenta: 2,6 GB letti a ogni
blocco. La scelta è di chi decide sul prodotto.

## Com'è andata (passo 1)

Come da piano. A-347 passa; la suite dell'importazione è verde, tranne A-193, che fallisce
soltanto in questo ambiente e anche su `main`.

Nel blocco dell'importazione vera ci sono due controlli in fila: l'impronta dell'archivio e
poi la lettura. Ognuno ferma il blocco anche se l'altro manca, quindi togliere la scadenza a
uno solo non basta perché la prova se ne accorga. Lo si dichiara, come in altre schede.
Toglierla a tutti e due la prova lo vede. L'impronta dell'archivio, invece, si prova anche da
sola.

Il controllo dell'orologio dentro la lettura di un file, pezzo per pezzo, serve per un file
solo molto grande. Con l'archivio della prova lo anticipa il controllo che si fa prima di ogni
voce. Il modulo dell'importazione mostra il messaggio se il tempo finisce mentre calcola
l'impronta dell'archivio. Nella stessa richiesta però il rapporto legge prima e si ferma prima,
quindi da una prova quella strada non si raggiunge. Tutte e due le cose si dichiarano.

| Guasto introdotto | Visto da |
|---|---|
| La schermata che non dà nessuna scadenza | A-347 |
| La lettura che ignora la scadenza ricevuta | A-347 |
| Nessun controllo prima di ogni voce dell'archivio | A-347 |
| L'impronta dell'archivio senza scadenza | A-347 |
| Il blocco che legge senza scadenza | nessuna, perché lo ferma l'impronta |
| Il blocco che calcola l'impronta senza scadenza | nessuna, perché lo ferma la lettura |
| Il blocco senza scadenza né per l'impronta né per la lettura | A-347 |
