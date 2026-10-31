# Concessioni tolte prima del controllo finale della pubblicazione

Scheda di lavorazione. Scritta prima del codice come piano; aggiornata a lavoro finito con
com'è andata davvero.

**Stato: fatto.** Requisito ALBO-22, con ALBO-02 sulla data di fine. Riga di collaudo A-328.

## In tre paragrafi

**Cosa costruiamo.** Ogni passaggio di stato di un atto (pubblicazione, annullamento,
defissione, proroga, importazione, sostituzione per oscuramento) apre una transazione, fa le
sue scritture con un permesso temporaneo che vale solo per quelle (la concessione), poi
controlla che l'atto sia cambiato solo come previsto e conferma. Fra le scritture e il
controllo i passaggi tolgono la concessione: una scrittura che arrivasse dopo il controllo non
passerebbe più da nessun controllo, e la conferma la renderebbe definitiva. Nella
pubblicazione questo mancava. Un altro componente che, durante il controllo finale o alla
conferma, toglieva la data di fine con le funzioni di WordPress, o la riscriveva con
un'istruzione diretta alla banca dati, ci riusciva: l'atto restava pubblicato con una data di
fine diversa da quella scritta nel registro, o senza data di fine.

**Come la costruiamo.** Come negli altri passaggi: nella pubblicazione, finita la voce della
conferma nel registro, si tolgono le concessioni prima del controllo finale. Da lì l'atto torna
fermo per il guardiano e per lo sbarramento delle istruzioni dirette, come ogni atto
pubblicato.

**Come si prova che funziona, e come si prova che il test è vero.** La prova A-328 riprende
A-229 e A-240: un aggancio aspetta la voce della conferma, poi, una volta alla lettura
dell'atto del controllo finale e una volta alla conferma della transazione, prova quattro
scritture. Sul codice di prima la prova fallisce in tutti e due i momenti: la riscrittura
diretta cambia la data di fine; alla lettura del controllo, provata a parte, riesce anche a
toglierla con le funzioni di WordPress. Con la correzione tutte e quattro sono
rifiutate e l'atto è pubblicato con la data calcolata. I guasti introdotti nel codice per
vedere quale prova li vede sono in fondo.

## I nove punti

### 1. File che si pensa di creare o modificare

- `includes/class-passaggi.php`: `pubblica_dentro()`.
- `tests/TuttoONienteTest.php`; `docs/collaudo.md`.

### 2. Requisito servito

ALBO-22: il passaggio a pubblicato scrive solo quello che deve, e niente altro. ALBO-02: la
data di fine dell'atto pubblicato è quella che il sistema ha calcolato e registrato.

### 3. Il percorso di chi usa il sito

Nessuno nuovo. Chi pubblica non vede differenze.

### 4. Dati letti e scritti

Nessun dato nuovo. Cambia solo il momento in cui il permesso temporaneo del passaggio finisce.

### 5. Permessi coinvolti

Nessun permesso nuovo. La concessione interna del passaggio dura meno.

### 6. Modi di guasto previsti

- Un altro componente scrive sull'atto durante il controllo finale o la conferma: la scrittura
  è rifiutata, come su ogni atto pubblicato.

### 7. Test aggiunti

A-328, in `tests/TuttoONienteTest.php`.

### 8. Cosa non implementa

- **Il rimando in bozza** non toglie le concessioni prima del controllo, e non ne ha bisogno:
  dopo la scrittura dello stato non gli resta nessuna concessione (niente data di fine, niente
  documento, il permesso dello stato già consumato), e l'atto è già una bozza, che lo
  sbarramento delle istruzioni dirette non protegge. Togliere le concessioni lì non cambierebbe
  nessun comportamento, e un guasto che le togliesse non sarebbe visibile da nessuna prova.
- **La dichiarazione dell'effetto** non apre nessuna concessione: scrive solo la voce del
  registro.
- Gli altri passaggi le tolgono già: annullamento (A-229), defissione (A-240), importazione
  (A-233), proroga (A-155), sostituzione per oscuramento.

### 9. Come si prova su un'installazione reale

Non si prova a mano: serve un componente che scriva nell'istante del controllo finale. La prova
automatica è A-328.

## Com'è andata

Come da piano. Una riga di codice.

| Guasto | Visto da |
|---|---|
| Nessuna revoca nella pubblicazione | A-328 |
| Revoca spostata dopo il controllo finale | A-328 |
| La revoca lascia l'atto fra quelli con un passaggio in corso | A-328 (e A-229, A-240) |
| La revoca lascia la data di fine attesa | nessuna prova: la stessa scrittura la ferma anche lo sbarramento delle istruzioni dirette, che non vede più l'atto fra quelli in corso. È una seconda difesa, dichiarata qui |

Le prove girano in locale: le azioni automatiche di GitHub sono ferme. Suite intera: 422 prove,
verdi salvo A-193, che in questo ambiente fallisce per la conversione dei caratteri anche su
`main`; verdi anche le due suite senza il meccanismo comune e con il meccanismo incompatibile.
