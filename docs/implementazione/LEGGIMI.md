# Schede di implementazione

Una scheda per ogni funzione realizzata, con lo stesso nome della funzione. Risponde alla
domanda che ci si fa prima di modificare qualcosa: **com'è fatta davvero questa parte, cosa
fa e cosa deliberatamente non fa, e quali test la sorvegliano.**

Ogni scheda si scrive **prima** di toccare il codice, come piano, e si aggiorna a lavoro
finito con com'è andata davvero. Contiene: quali file tocca e perché, quale requisito serve,
il percorso di chi usa il sito, i dati letti e scritti, i permessi coinvolti, i modi di
guasto previsti, i test aggiunti con il riferimento a `collaudo.md`, cosa non implementa, e
come si prova la funzione su un'installazione reale dopo il rilascio.

Schede presenti:

- [`avvio-e-politiche.md`](avvio-e-politiche.md): avvio del componente, controllo di
  compatibilità con il meccanismo comune, registrazione della sezione e dichiarazione delle
  due politiche. Righe di collaudo A-01..A-10.
- [`tipo-atto-permessi-e-chiusura.md`](tipo-atto-permessi-e-chiusura.md): il tipo di
  contenuto atto, i due elenchi di voci, i permessi, il ruolo proprio del componente e lo
  sbarramento che tiene chiusa la pubblicazione. Righe di collaudo A-11..A-47.
- [`durate-per-tipo.md`](durate-per-tipo.md): la durata di pubblicazione di ciascun tipo di
  atto, con la sua origine. Righe di collaudo A-48..A-57.
- [`repertorio.md`](repertorio.md): il numero progressivo annuale, la partenza dichiarata e
  la schermata della numerazione. Righe di collaudo A-58..A-68.
- [`dati-dell-atto.md`](dati-dell-atto.md): tipo di atto, organo, data di adozione e numero
  proprio, il riquadro in cui si compilano e l'elenco dei dati mancanti. Righe di collaudo
  A-69..A-76.
- [`documenti-dell-atto.md`](documenti-dell-atto.md): il documento principale e gli allegati
  ulteriori, depositati dal meccanismo comune, il riquadro da cui si caricano e i permessi
  con cui si governano. Righe di collaudo A-77..A-84.
- [`passaggio-in-verifica.md`](passaggio-in-verifica.md): la prima parte del flusso di
  pubblicazione, cioè bozza, passaggio in verifica e blocco dell'atto in verifica. Righe di
  collaudo A-85..A-92.
- [`pubblicazione.md`](pubblicazione.md): la seconda parte del flusso, cioè rimando in bozza
  con motivazione e pubblicazione con la conferma sui dati personali, le date e il numero,
  tutto o niente. Righe di collaudo A-93..A-110.
- [`proroga-per-disservizio.md`](proroga-per-disservizio.md): la proroga degli atti in
  pubblicazione dopo un disservizio, con attestazione obbligatoria e tutto o niente su più
  atti. Righe di collaudo A-127..A-138 e A-147..A-155.
- [`slittamento-ai-festivi.md`](slittamento-ai-festivi.md): la scelta dell'amministrazione
  sulla fine che cade in un giorno festivo, l'elenco dei festivi e lo slittamento nella
  pubblicazione e nella proroga. Righe di collaudo A-139..A-143 e A-156..A-164.
- [`dati-del-procedimento.md`](dati-del-procedimento.md): la scelta dell'amministrazione su
  responsabile del procedimento, ufficio e richiedente, gli elenchi da cui si scelgono e la
  loro copia nel registro alla pubblicazione. Righe di collaudo A-144..A-146 e A-165..A-173.
- [`scaduti-invisibili.md`](scaduti-invisibili.md): l'atto scaduto non si vede su nessuna
  strada del sito pubblico, per nessuno e senza impostazioni, atti importati compresi. Righe
  di collaudo A-175..A-179.
- [`consultazione-riservata.md`](consultazione-riservata.md): la schermata "Atti scaduti"
  dell'amministrazione, il permesso apposito e i documenti in sola lettura. Righe di collaudo
  A-180..A-185.
- [`importazione-a-secco.md`](importazione-a-secco.md): la lettura del backup del plugin
  "Albo Pretorio On line" e il rapporto di cosa si porterebbe, senza scrivere niente. Righe
  di collaudo A-186..A-194.
- [`importazione-vera.md`](importazione-vera.md): la scrittura degli atti di Albo Pretorio On line
  dopo il rapporto, a blocchi, tutto o niente per atto e senza doppioni. Righe di collaudo
  A-210..A-217.
- [`pagina-pubblica.md`](pagina-pubblica.md): la pagina pubblica dell'albo con il codice breve
  `[albo_pretorio]`: elenco, scheda, ricerca, divieto ai motori, accessibilità del codice
  prodotto. Righe di collaudo A-195..A-209.
- [`elenco-in-tabella.md`](elenco-in-tabella.md): l'elenco degli atti in tabella, che su
  schermo stretto diventa un riquadro per atto, le date separate nella scheda e la funzione
  `albo_pretorio_vista_corrente()` per i temi. Righe di collaudo A-195, A-196, A-203 riviste,
  A-218 e A-219 nuove.
- [`vecchi-indirizzi.md`](vecchi-indirizzi.md): il codice breve `[Albo]` e gli indirizzi di
  scheda, stampa e documenti di Albo Pretorio On line dopo il passaggio, e la convivenza dei
  due plugin. Righe di collaudo A-220..A-223.
- [`annullamento.md`](annullamento.md): l'annullamento di un atto pubblicato, con il motivo
  nel registro, il permesso apposito, l'avviso pubblico fino alla scadenza senza documenti e
  l'annullato nella consultazione riservata. Righe di collaudo A-224..A-230.
- [`importazione-annullati.md`](importazione-annullati.md): gli atti annullati in Albo
  Pretorio On line arrivano annullati, con data e motivo di là, anche quelli ancora in
  pubblicazione. Righe di collaudo A-231..A-234.
- [`defissione-anticipata.md`](defissione-anticipata.md): la defissione anticipata di un atto
  pubblicato per una delle quattro cause dell'elenco, con la data di fine riportata al giorno
  prima, lo stato defisso e l'annullamento che parte anche da defisso. Righe di collaudo
  A-235..A-242.
- [`effetto-rimozione.md`](effetto-rimozione.md): dopo una defissione anticipata,
  l'amministrazione dichiara una volta sola se la pubblicazione vale per il periodo
  trascorso o va rifatta, senza valore preselezionato. Righe di collaudo A-243..A-248.
- [`amministrazioni-ospitate.md`](amministrazioni-ospitate.md): l'elenco delle
  amministrazioni per cui l'albo pubblica, la scelta senza preselezione sull'atto quando
  l'elenco ha voci, e il nome fissato alla pubblicazione e mostrato al pubblico. Righe di
  collaudo A-249..A-256 e A-260.
- [`nome-del-file-e-memorie.md`](nome-del-file-e-memorie.md): il nome del file scaricato, con
  numero di registro e date di pubblicazione e senza oggetto né nome originale, e la prova che
  nessuna memoria di pagina conserva una risposta dell'albo. Righe di collaudo A-257..A-259.
