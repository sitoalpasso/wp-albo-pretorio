# Scheda di implementazione: avvio del componente e dichiarazione delle politiche

Unità A1. Scheda compilata a lavoro finito: i verbi sono al passato e i punti in cui il
lavoro si è discostato dal piano sono segnalati come tali.

## 1. File toccati

| File | Perché |
|---|---|
| `albo-pretorio-pa.php` | Ci vivono le costanti che dichiarano l'identità del componente verso il meccanismo comune: versione di interfaccia richiesta, nome visibile, identificativo di sezione e le due politiche. Ci vive anche l'aggancio dell'avvio, e solo quello: è il file che WordPress carica per primo, quando il meccanismo comune può non esserci ancora |
| `includes/class-avvio.php` | Nuovo. La procedura di avvio vera. Sta in un file separato perché il file principale deve poter essere caricato senza eseguire niente: se la procedura stesse lì, il solo caricamento la eseguirebbe |
| `bin/installa-core.sh` | Nuovo. Procura il meccanismo comune alla revisione fissata dentro l'installazione di prova, in una cartella che si chiama come lo slug. Senza, i test fallirebbero perché il meccanismo comune non c'è, e non per il motivo che devono misurare |
| `tests/avvio-comune.php` | Nuovo. Le parti condivise dai tre avvii della suite |
| `tests/bootstrap.php`, `tests/bootstrap-senza-core.php`, `tests/bootstrap-core-incompatibile.php` | Tre avvii e non uno. La versione dell'interfaccia è una costante, e una costante si definisce una volta sola per processo: i tre ambienti non possono convivere nella stessa esecuzione |
| `tests/AvvioTest.php`, `tests/SenzaCoreTest.php`, `tests/CoreIncompatibileTest.php` | Nuovi. Le prove delle righe A-01..A-10, distribuite fra i tre ambienti |
| `phpunit-senza-core.xml.dist`, `phpunit-core-incompatibile.xml.dist`, `phpunit.xml.dist` | Una configurazione per avvio; quella normale esclude i due file che appartengono agli altri due |
| `composer.json` | I due comandi nuovi che eseguono gli avvii separati |
| `.github/workflows/ci.yml` | Il passo che procura il meccanismo comune e le tre esecuzioni di PHPUnit invece di una |
| `phpcs.xml.dist` | Tre esclusioni mirate al singolo controllo e al percorso, non alla cartella intera |
| `docs/collaudo.md` | Le righe A-01..A-10 e le due premesse che le reggono. Scritte prima dei test, come prescrive il processo |

## 2. Requisito servito

Nessun ALBO-xx si chiude qui, e la cosa è scritta anche nel catalogo. A1 costruisce il
piano su cui poggeranno ALBO-05 (contenuto defisso irraggiungibile) e ALBO-06 (pagine
dell'albo fuori dagli indici dei motori di ricerca): entrambi discendono dalla politica
della sezione, e la politica esiste solo se qualcuno la dichiara al meccanismo comune. A1
è quel qualcuno.

Il meccanismo di core servito è il registro delle sezioni: fornisce lo strumento e pretende
la politica come parametro esplicito, senza valore predefinito. Questo componente la
dichiara per intero, indicizzazione **vietata** e scadenza **irraggiungibile**, e senza di
essa non ottiene niente.

## 3. Flusso per chi usa il sito

Nessuno se ne accorge quando funziona, ed è il comportamento voluto: l'avvio non aggiunge
nessuna schermata e nessun pulsante. Si vede solo quando qualcosa non va.

```
WordPress carica i componenti attivi
        |
        v
  albo-pretorio-pa.php    (in ordine alfabetico puo' venire prima
        |                  di conformita-core: qui non si chiama
        |                  ancora niente, si aggancia soltanto)
        v
  tutti i componenti caricati  --> parte la procedura di avvio
        |
        +-- meccanismo comune non caricato
        |     -> il componente resta attivo e inerte
        |     -> avviso in bacheca a ogni richiesta di amministrazione
        |
        +-- versione di interfaccia incompatibile
        |     -> il meccanismo comune disattiva il componente e lo scrive
        |     -> da quel momento il componente non viene piu' caricato
        |
        +-- sezione gia' registrata da altri, oppure motore di scadenza spento
        |     -> il componente resta attivo e inerte
        |     -> avviso in bacheca che nomina la sezione e riporta il motivo
        |
        +-- tutto a posto
              -> sezione registrata con le due politiche, nessun avviso
```

Chi amministra il sito vede, nei casi di guasto, un riquadro rosso in cima alla bacheca che
dice quale componente è fermo e cosa fare. Chi visita il sito non vede niente in nessun
caso: un avviso di configurazione su una pagina pubblica sarebbe un'informazione data a chi
non può usarla.

## 4. Dati letti e scritti

Nessuna scrittura, da nessuna parte. La procedura non tocca le opzioni, non tocca il
database e non pianifica niente: quello che produce vive in memoria per la durata della
richiesta e si rifà identico alla richiesta successiva.

In lettura: la versione di interfaccia che il meccanismo comune espone, e il proprio
percorso come lo conosce WordPress. Nessun dato nuovo, quindi `docs/dati.md` non cambia.

**Perché nessuna scrittura, che è una scelta e non una dimenticanza.** L'alternativa
sarebbe registrare la sezione una volta sola all'attivazione e fidarsi. Ma la politica
serve a ogni richiesta, anche a quelle che arrivano dopo un aggiornamento, un cambio di
tema o un ripristino da backup: un dato salvato una volta racconterebbe la configurazione
del giorno dell'attivazione, non quella di adesso.

## 5. Permessi

La procedura non è invocabile da nessuno: la esegue WordPress a ogni caricamento, e non
esiste nessuna rotta, nessun modulo e nessun comando che la faccia partire.

L'unica superficie visibile sono gli avvisi in bacheca, riservati a chi ha la capacità di
attivare i componenti (`activate_plugins`). Chi non ce l'ha non li vede: sono tutte
condizioni a cui si rimedia installando, aggiornando o disattivando un componente, e
mostrarle a chi non può farlo significa dare un allarme a chi non ha l'interruttore.

## 6. Modi di guasto

Quattro, tutti previsti, tutti chiusi verso la prudenza.

| Guasto | Come degrada | Perché così |
|---|---|---|
| Meccanismo comune non caricato | Attivo e inerte, con avviso a ogni richiesta di amministrazione | Il componente non può registrare niente, ma può continuare a dire che è fermo. Disattivarsi lo zittirebbe |
| Versione di interfaccia incompatibile | Disattivato dal meccanismo comune, con avviso che riporta la versione richiesta e quella trovata | Non è una scelta di questo componente: la guardia appartiene al meccanismo comune, ed è l'unico punto in cui questo componente viene disattivato |
| Sezione già registrata da altri | Attivo e inerte, con avviso che nomina la sezione; le politiche preesistenti restano intatte | Sovrascrivere la politica di un altro componente è il guasto peggiore possibile in questo punto. Vale anche quando le politiche coincidono: vedi la premessa del catalogo |
| Motore di scadenza non avviato | Attivo e inerte, con avviso che riporta il codice del rifiuto | Una sezione registrata con il motore spento avrebbe una politica di scadenza dichiarata e nessuno ad applicarla. Il rifiuto arriva dal meccanismo comune e si riporta com'è |

Il filo comune: **fra fermarsi e proseguire a metà si sceglie sempre di fermarsi**, e
fermarsi non significa sparire. Un componente disattivato non viene più caricato e non può
più segnalare niente, quindi la disattivazione resta il caso in cui è il meccanismo comune
a deciderla.

## 7. Test

Righe A-01..A-10 del catalogo, distribuite su tre esecuzioni.

| Riga | File | Metodo |
|---|---|---|
| A-01 | `tests/AvvioTest.php` | `test_a01_dipendenza_dichiarata_in_due_posti`, `test_a01_wordpress_rifiuta_con_il_codice_dedicato` |
| A-02 | `tests/AvvioTest.php` | `test_a02_avvio_rimandato_a_plugins_loaded` |
| A-03 | `tests/SenzaCoreTest.php` | `test_precondizione_core_assente`, `test_a03_resta_attivo_e_inerte`, `test_a03_avviso_riservato` |
| A-04 | `tests/AvvioTest.php` | `test_a04_criterio_di_compatibilita`, sui tre casi |
| A-05 | `tests/CoreIncompatibileTest.php` | `test_precondizione_versione_incompatibile`, `test_a05_effetto_completo_della_guardia` |
| A-06 | `tests/AvvioTest.php` | `test_a06_avvio_con_core_compatibile` |
| A-07 | `tests/AvvioTest.php` | `test_a07_politiche_rilette_dal_core` |
| A-08 | `tests/AvvioTest.php` | `test_a08_avvio_invocato_due_volte_nella_stessa_richiesta` |
| A-09 | `tests/AvvioTest.php` | `test_a09_sezione_gia_registrata_da_altri` (due varianti), `test_a09_avviso_riservato_a_chi_attiva_i_componenti` |
| A-10 | `tests/AvvioTest.php` | `test_a10_motore_di_scadenza_non_avviato` |

**Prova di non vacuità, eseguita.** I test sono stati scritti e mandati in verifica continua
**prima** dell'implementazione, e sono falliti tutti con "classe Avvio non trovata": non per
un errore di sintassi e non per un problema dell'ambiente, ma perché il comportamento non
c'era. È la dimostrazione che misurano qualcosa. Il registro della verifica continua
conserva l'esecuzione rossa e quella verde sullo stesso ramo, a due commit di distanza.

**Tre precondizioni scritte nei test e non lasciate al buon senso.** Le prove che
asseriscono uno stato dell'elenco dei componenti attivi ce lo mettono prima, perché
nell'ambiente di prova il componente non compare in quell'elenco e senza quel passaggio
"è rimasto attivo" sarebbe vero per niente. L'esecuzione senza meccanismo comune verifica
prima che le sue funzioni non esistano davvero. E l'utente corrente delle prove è un
amministratore, perché gli avvisi sono riservati a chi può attivare i componenti: senza
dire chi guarda la bacheca, "l'avviso c'è" e "l'avviso non c'è" sarebbero la stessa
osservazione.

**Due scostamenti dal piano, tutti e due nei test e tutti e due verso il più severo.**
La riga A-09 osserva ora il codice dell'errore restituito dall'avvio invece di un semplice
falso: la convenzione sul valore restituito è diventata uniforme, e un'asserzione sul codice
è più stretta di una sul falso. La precondizione sull'utente amministratore, appena
descritta, non era nel piano: senza di essa le asserzioni sugli avvisi non avrebbero
misurato niente.

## 8. Cosa deliberatamente NON fa

- **Non registra il tipo di contenuto atto.** È l'unità A2, insieme ai ruoli, alle capacità
  e all'avviso che scatta quando nessun ruolo le possiede (ALBO-23..26).
- **Non scrive niente all'attivazione.** L'infrastruttura di attivazione e aggiornamento
  nasce con la prima cosa da scrivere, cioè con le capacità di A2.
- **Non impedisce l'attivazione con il meccanismo comune assente.** Non può: l'intestazione
  `Requires Plugins` è l'unico strumento che WordPress offre per farlo, e verifica la
  presenza per slug, non la versione. Il vincolo di versione può quindi solo essere
  controllato dopo, ad avvio avvenuto.
- **Non ripara l'avviso mancato del meccanismo comune.** Quando la disattivazione per
  incompatibilità avviene durante una richiesta che non è di amministrazione, l'avviso non
  viene mai mostrato: il componente è già disattivato e non c'è più nessuno a riagganciarlo.
  È un difetto del meccanismo comune, registrato a parte e correggibile solo lì.
- **Non rende configurabili le due politiche.** Non sono preferenze: sono ciò che distingue
  la pubblicità legale da una pagina qualsiasi.

## 9. Come si prova sul sito vero

Quattro prove, ciascuna con l'azione, cosa si deve vedere e cosa vuol dire se non lo si
vede. Si eseguono nell'ordine, su un'installazione reale, dopo il rilascio. Le righe vivono
nel `collaudo-di-rilascio.md` del repository di progetto.

**Prova 1, il caso normale.** Con entrambi i componenti attivi, apro la bacheca. Devo vedere
la bacheca normale, senza nessun riquadro rosso. Se vedo un riquadro rosso, l'avvio non è
arrivato in fondo su questo sito e il testo del riquadro dice a che punto si è fermato: il
rilascio si annulla e si legge quel testo.

**Prova 2, il meccanismo comune spento.** Disattivo Conformita Core dall'elenco dei
componenti e ricarico la bacheca. Devo vedere Albo Pretorio ancora fra i componenti attivi,
e un riquadro rosso che dice che resta attivo ma inerte e che va installato e attivato
Conformita Core. Se Albo Pretorio risulta disattivato, la degradazione sta andando nel verso
sbagliato. Se non vedo nessun riquadro, l'avviso non arriva a chi deve rimediare e il
guasto è silenzioso: in entrambi i casi il rilascio si annulla. Poi riattivo Conformita
Core e verifico che il riquadro sparisca.

**Prova 3, chi vede l'avviso.** Con Conformita Core ancora disattivato, entro con un utente
che non può attivare componenti (per esempio un sottoscrittore) e apro l'area di
amministrazione che quel ruolo può vedere. Non devo vedere nessun riquadro. Se lo vedo,
stiamo mostrando un guasto a chi non ha l'interruttore per ripararlo.

**Prova 4, il visitatore.** Da un browser dove non ho fatto accesso, con Conformita Core
ancora disattivato, apro la home del sito. La pagina deve caricarsi normalmente, senza
nessun messaggio e senza nessun errore. Se la pagina si rompe, un componente di conformità
fermo sta buttando giù il sito pubblico, che è esattamente il contrario di quello che deve
fare. Al termine riattivo Conformita Core e ripeto la prova 1.
