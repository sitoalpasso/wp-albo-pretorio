# Importazione degli atti annullati

Scheda di lavorazione. Scritta prima del codice come piano, aggiornata a lavoro finito con
com'è andata davvero (in fondo).

**Stato: fatto.** Requisiti ALBO-35 (passaggio da un albo precedente) e ALBO-22 (annullato
terminale, sotto-decisione 1 chiusa il 2026-10-22 con "Solo l'avviso"). Righe di collaudo
A-231..A-234. Segue l'annullamento (`annullamento.md`) e l'importazione vera
(`importazione-vera.md`), di cui cambia soltanto il destino degli atti annullati.

## In tre paragrafi

**Cosa costruiamo.** Un atto che in Albo Pretorio On line era stato annullato arriva in
questo albo **annullato**, con il suo numero, le sue date, la data e il motivo
dell'annullamento scritti nel vecchio albo. Vale per l'annullato già scaduto e per quello
ancora in pubblicazione, che fino a oggi restava fuori dall'importazione perché la
decisione su che cosa ne veda il pubblico era aperta. Con "Solo l'avviso" la decisione c'è:
fino alla scadenza il cittadino vede l'avviso dell'annullamento, senza documenti, come per
un atto annullato in questo albo; dopo la scadenza sparisce. Per l'annullato in
pubblicazione resta la seconda conferma sui dati personali degli atti visibili, perché il
suo oggetto si vede. **Nessuna impostazione nuova.**

**Come la costruiamo.** Nella stessa transazione dell'importazione, dopo le scritture che
portano la bozza a pubblicato, il passaggio porta l'atto ad annullato e scrive la voce
`annullamento` del registro, con il motivo del vecchio albo e, nei dettagli, la data
dell'annullamento in quel albo e l'origine. Il controllo finale pretende lo stato
annullato. Se un pezzo fallisce non si importa niente, come per ogni altro atto: l'atto
non passa mai, nemmeno per un istante visibile ad altri, da pubblicato con i documenti. La
data che il pubblico, il riquadro e la consultazione riservata leggono è quella del vecchio
albo, presa dai dettagli della voce; per un annullamento fatto qui resta l'istante della
voce. Un motivo vuoto nel vecchio albo si scrive come "non indicato in Albo Pretorio On
line", perché la voce dell'annullamento un motivo lo pretende.

**Come si prova che funziona, e come si prova che il test è vero.** Prove vere
dell'importazione dal backup di prova: l'atto 13 (annullato in pubblicazione) entra con la
seconda conferma e mostra l'avviso con la data del vecchio albo, i suoi documenti si
rifiutano come quelli di uno scaduto; senza la conferma resta fuori. L'atto 4 (annullato e
scaduto) entra annullato e compare nella consultazione riservata con data e motivo. Un
guasto per volta nelle scritture nuove lascia l'atto non importato e il numero libero.
Ogni prova ha un controllo positivo, e le prove che devono fallire si fanno fallire
togliendo il pezzo di codice che sorvegliano.

## I nove punti

### 1. File che si pensa di modificare

| File | Cosa cambia |
|---|---|
| `includes/class-passaggi.php` | `importa()` con l'annullamento nella stessa transazione; `annullamento()` che legge la data del vecchio albo dai dettagli |
| `includes/class-importazione.php` | l'annullato in pubblicazione non è più fra i non portati; dopo un'eccezione l'atto annullato si riconosce come confermato |
| `includes/class-analisi-importazione.php` | il testo della segnalazione dell'annullato |
| `tests/ImportazioneVeraTest.php`, `tests/ImportazioneTest.php` | le righe A-231..A-234 e quelle che cambiano |
| `docs/collaudo.md`, `docs/requisiti.md`, `docs/dati.md`, questa scheda, l'indice | le righe nuove e il destino degli annullati importati |

### 2. Perché

ALBO-35: il passaggio non ripara né peggiora gli atti del vecchio albo, li porta come
sono. Un atto annullato là è annullato anche qui. ALBO-22: annullato è terminale, e quello
che il pubblico ne vede è deciso.

### 3. Il flusso per chi usa il sito

Chi importa legge nel rapporto che l'atto è annullato, con data e motivo, e che arriva
annullato. Per quello ancora in pubblicazione la casella della seconda conferma lo porta
insieme agli altri atti visibili. Il cittadino, fino alla scadenza, ne vede l'avviso.

### 4. Dati letti e scritti

Si legge la data e il motivo dell'annullamento dal backup. Si scrivono, in più rispetto a
un atto importato qualunque: lo stato annullato e la voce `annullamento` con motivo,
dettagli (numero, anno, fine, data dell'annullamento nel vecchio albo, origine). Il
meccanismo comune aggiunge la voce automatica `rimozione` del cambio di stato.

### 5. Permessi

Quelli dell'importazione: chi importa. Il permesso di annullare non si chiede, perché
l'annullamento è un fatto avvenuto nel vecchio albo e non un atto di chi importa.

### 6. Modi di guasto previsti

- L'atto che resta pubblicato con i documenti per un guasto fra pubblicazione e
  annullamento.
- La data dell'annullamento presa dal giorno dell'importazione invece che dal vecchio albo.
- L'annullato in pubblicazione che entra senza la seconda conferma.
- Un rilancio che lo riporta una seconda volta.
- Dopo un'eccezione, l'atto annullato non riconosciuto come importato e la sua bozza tolta.

### 7. Prove che si aggiungeranno

Righe A-231..A-234 in `collaudo.md`, in `tests/ImportazioneVeraTest.php`.

### 8. Cosa NON si implementa

La defissione anticipata e lo stato defisso. Gli atti annullati in questo albo restano come
sono.

### 9. Come si prova sul sito vero

Sulla copia di prova, dopo l'importazione: cercare nell'elenco pubblico un atto annullato
ancora in pubblicazione, se ce n'è uno, e aprirne la scheda; cercare un annullato scaduto
nella schermata "Atti scaduti" e controllare data e motivo col vecchio albo.

## Com'è andata

- Il passaggio di importazione, finite le scritture della pubblicazione, rinnova la
  concessione per la sola scrittura dello stato annullato, come l'annullamento, e la toglie
  prima del controllo finale. Toglierla prima del controllo vale ora anche per gli atti
  importati non annullati: una riscrittura durante il controllo o alla conferma non passa.
- La data che si legge è un giorno: il riquadro la mostra senza ora, la pagina pubblica e la
  consultazione riservata come sempre con il solo giorno.
- Una data dell'annullamento impossibile, futura, con l'ora, o un motivo che non è testo,
  sono rifiutati dal passaggio prima di scrivere: l'analisi del backup li darebbe già
  giusti, ma il passaggio non si fida di chi lo chiama.
- Le prove dell'importazione vera che contavano gli atti portati sono cambiate di
  conseguenza: otto atti con le due conferme, due atti in vista invece di uno, e gli atti 4
  e 13 annullati invece che pubblicati.
- Primo giro di revisione: una data dell'annullamento scritta ma impossibile nel backup
  passava come "non annullato" e l'atto arrivava pubblicato con i documenti. Ora è un
  conflitto e l'atto non si porta. La data dei dettagli vale solo se la voce viene davvero
  dall'importazione (origine, voce di importazione con la stessa data, giorno non dopo la
  voce). Un aggancio che conferma da sé la transazione a metà del passaggio resta fuori da
  questa unità: è il limite dichiarato in testa a `class-passaggi.php`, comune a tutti i
  passaggi.
- Secondo giro: se la voce di importazione non si legge, la provenienza non si sa e la data
  dell'annullamento risulta "non leggibile in questo momento", invece di cadere sull'istante
  della voce, che per un atto importato è il giorno dell'importazione.

