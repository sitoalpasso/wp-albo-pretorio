# Albo Pretorio - istruzioni di sviluppo

Plugin WordPress per la pubblicità legale. Implementa i requisiti derivati dalla normativa
applicabile ai soggetti dell'art. 2-bis del d.lgs. 33/2013: l'elenco completo, con fonte e
criterio di verifica per ciascuno, è in `docs/requisiti.md`.

Licenza: GPL-3.0-or-later. Versioni minime: WordPress 6.5, PHP 8.1. Multisite non
supportato.

## Comandi

- Ambiente: `npx wp-env start` (configurazione in `.wp-env.json`)
- Test: `composer test` (PHPUnit sulla suite di test di WordPress)
- Standard: `composer lint` (PHPCS, WordPress Coding Standards)

## Regole di lavoro

- Ogni requisito di `docs/requisiti.md` ha almeno un test che lo verifica: una modifica
  che tocca un requisito aggiorna prima il test. I test sono la specifica eseguibile.
- La scadenza dei contenuti è una proprietà del dato letto, non un evento pianificato:
  nessuna funzione può assumere che il compito pianificato sia stato eseguito.
- Ogni tipo di contenuto dichiara `show_in_rest` esplicitamente, in un senso o
  nell'altro.
- Nessun valore dipendente dal singolo ente cablato nel codice: durate, denominazioni e
  soglie sono configurazione con validazione. I default esistono solo dove un default
  sbagliato è impossibile.
- Fuso orario: sempre `wp_timezone()`, mai un fuso cablato.
- Sicurezza: nonce e `permission_callback` su ogni superficie registrata, sanitizzazione
  in ingresso, escaping in uscita, capability dedicate.
- Documentazione e messaggi di commit in italiano, descrittivi e senza riferimenti a
  installazioni specifiche.
- La cartella `docs/` descrive il plugin in linguaggio semplice: `requisiti.md`,
  `architettura.md`, `dati.md`, `collaudo.md`, `sicurezza.md`, più una scheda in
  `docs/implementazione/` per ogni funzione realizzata. Un commit che cambia un
  comportamento descritto lì aggiorna il documento nello stesso commit. I nomi di classe
  di `architettura.md` e `dati.md` sono il contratto: se un nome deve cambiare, si
  cambia prima nel documento.
- Prima di implementare una funzione nuova si scrive la scheda di lavorazione, otto
  punti: file toccati, requisito ALBO-xx, flusso per chi usa il sito, dati letti e
  scritti, permessi coinvolti, modi di guasto previsti, test che si aggiungono, cosa
  deliberatamente non si implementa. A fine lavoro la scheda aggiornata con com'è andata
  davvero si committa in `docs/implementazione/<nome-funzione>.md`.
- Ogni bug scoperto produce prima un test che lo riproduce e fallisce, poi la
  correzione che lo fa passare. Il test resta per sempre nella batteria: non si cancella
  e non si disattiva per far passare la CI.
- Richiede il plugin `conformita-core`; la compatibilità di versione è verificata
  all'attivazione.
- La politica di indicizzazione di questo componente è `noindex` imposto, ed è dichiarata
  a core come parametro esplicito. Non è un'opzione configurabile.
