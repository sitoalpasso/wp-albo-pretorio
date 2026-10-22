/**
 * Prova della pagina pubblica dell'albo in un browser vero (ALBO-16, ALBO-28).
 *
 * Due controlli, sulle pagine preparate da `prepara-sito.php`:
 *
 * 1. il motore automatico di accessibilita' (axe), regole WCAG 2.0 e 2.1 di
 *    livello A e AA, su ogni pagina, con lo schermo di un telefono e con uno
 *    schermo largo: nessuna violazione;
 * 2. con `--tastiera`, il percorso da sola tastiera: ogni comando si raggiunge
 *    con il tasto Tab, nell'ordine in cui sta nella pagina, con l'indicatore del
 *    fuoco visibile, e dall'ultimo si esce, come dal primo all'indietro; poi due
 *    ricerche fatte senza mouse.
 *
 * Prima di esaminare una pagina se ne controlla il contenuto: gli atti attesi,
 * il messaggio d'errore della ricerca scritta male, i tre documenti della
 * scheda. Una pagina che non mostra cio' che deve non vale come esaminata.
 *
 * L'indicatore del fuoco si guarda nei pixel: lo stesso riquadro dello schermo
 * con il fuoco e senza deve cambiare in modo visibile. Un contorno trasparente
 * ha le sue proprieta' di stile ma non si vede, e non basta.
 *
 * Prima delle pagine vere, ogni controllo si fa girare su pagine guastate
 * apposta: se non vi trova i guasti, non e' un controllo, e la prova si ferma.
 *
 * Tutto da questo computer: le pagine dal sito di prova locale, il motore dalla
 * cartella delle dipendenze, nessuna rete esterna.
 *
 * Uso: node accessibilita.mjs --pagine <file.json> --tema <nome> [--tastiera] [--tutta-la-pagina]
 *
 * Su un tema vero una violazione che sta tutta fuori dal codice dell'albo e'
 * del tema: si annota e non ferma la prova (ALBO-28, limiti dichiarati). Con
 * `--tutta-la-pagina`, per il tema spoglio di prova, ogni violazione ferma.
 * Variabili: CHROME, percorso del browser (Chrome o Chromium).
 *
 * @package AlboPretorioPa
 */

import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { chromium } from 'playwright-core';

const require = createRequire( import.meta.url );
const argomenti = process.argv.slice( 2 );
const valore = ( nome ) => {
	const i = argomenti.indexOf( nome );
	return i >= 0 ? argomenti[ i + 1 ] : undefined;
};

const filePagine = valore( '--pagine' );
const tema = valore( '--tema' ) ?? 'sconosciuto';
const conTastiera = argomenti.includes( '--tastiera' );
const tuttaLaPagina = argomenti.includes( '--tutta-la-pagina' );

if ( ! filePagine || ! process.env.CHROME ) {
	console.error( 'Uso: CHROME=<browser> node accessibilita.mjs --pagine <file.json> --tema <nome> [--tastiera]' );
	process.exit( 2 );
}

const dati = JSON.parse( readFileSync( filePagine, 'utf8' ) );
const PAGINE = [ 'elenco', 'seconda pagina', 'ricerca con un filtro', 'ricerca senza risultati', 'ricerca scritta male', 'scheda con allegati' ];
const SCHERMI = {
	telefono: { width: 390, height: 844 },
	largo: { width: 1280, height: 900 },
};
const REGOLE = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];
const motore = readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );

/*
 * La pagina guastata apposta: un'immagine senza testo alternativo, un campo
 * senza etichetta, un fuoco senza indicatore, uno con il contorno e l'ombra
 * trasparenti, e un ciclo che dall'ultimo comando riporta il fuoco al primo.
 */
const GUASTA = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Pagina guastata</title>
<style>body { background: #fff; } .albo-pretorio a { display: inline-block; padding: 4px; margin: 8px; color: #000; }
.albo-pretorio a:focus { outline: none; }
.albo-pretorio a.trasparente:focus { outline: 3px solid transparent; box-shadow: 0 0 0 4px transparent; }</style></head>
<body><main><div class="albo-pretorio"><h1>Prova</h1><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
<form><input type="text" name="senza-etichetta"></form><a href="#uno" id="uno">Uno</a> <a href="#due" id="due" class="trasparente">Due</a></div></main>
<script>document.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Tab' && ! e.shiftKey && document.activeElement && document.activeElement.id === 'due' ) { e.preventDefault(); document.querySelector( 'input' ).focus(); } } );</script></body></html>`;

// Le pagine con il codice dell'albo ma senza cio' che devono mostrare: la verifica del contenuto deve accorgersene.
const SENZA_DOCUMENTI = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Scheda</title></head>
<body><main><div class="albo-pretorio"><h1 id="albo-pretorio-titolo-atto">OGGETTO</h1><p class="albo-pretorio__conteggio">Nessun documento.</p>
<p class="albo-pretorio__torna"><a class="albo-pretorio__collegamento" href="#">Torna</a></p></div></main></body></html>`;
// L'avviso c'e', con il suo ruolo e legato al campo, ma senza la spiegazione.
const ERRORE_VUOTO = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Ricerca</title>
<style>.albo-pretorio__avviso { padding: 8px; border: 2px solid #000; }</style></head>
<body><main><div class="albo-pretorio"><p id="albo-pretorio-errore-ricerca" class="albo-pretorio__avviso" role="alert"></p>
<form><label for="albo_dal">Dal</label><input type="date" id="albo_dal" name="albo_dal" aria-invalid="true" aria-describedby="albo-pretorio-errore-ricerca"></form></div></main></body></html>`;

// Il modulo di ricerca con un filtro spento: i comandi richiesti devono esserci tutti, e funzionanti.
const FILTRO_SPENTO = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Ricerca</title></head>
<body><main><div class="albo-pretorio"><form>
<label for="albo_testo">Testo</label><input type="text" id="albo_testo" name="albo_testo">
<label for="albo_tipo">Tipo</label><select id="albo_tipo" name="albo_tipo"><option value="">Tutti</option></select>
<label for="albo_organo">Organo</label><select id="albo_organo" name="albo_organo" disabled><option value="">Tutti</option></select>
<label for="albo_dal">Dal</label><input type="date" id="albo_dal" name="albo_dal">
<label for="albo_al">Al</label><input type="date" id="albo_al" name="albo_al">
<button type="submit">Cerca</button></form></div></main></body></html>`;

// Le date di sola lettura: si raggiungono, ma non si compilano.
const DATE_FERME = FILTRO_SPENTO.replace( ' disabled>', '>' ).replace( 'name="albo_dal">', 'name="albo_dal" readonly>' );

// Organo spostato a vista prima del testo, mentre il Tab lo visita dopo il tipo.
const FILTRO_RIORDINATO = FILTRO_SPENTO.replace( ' disabled>', '>' )
	.replace( '<form>', '<form style="display: flex; flex-wrap: wrap; gap: 8px">' )
	.replace( /<label for="(\w+)">([^<]+)<\/label>(<(?:input|select)[^>]*>(?:<option[^>]*>[^<]*<\/option><\/select>)?)/g, '<div class="campo-$1"><label for="$1">$2</label>$3</div>' )
	.replace( '<div class="campo-albo_organo">', '<div class="campo-albo_organo" style="order: -1">' );

// Il contrario: nel codice organo prima di tipo, a vista rimessi nell'ordine dell'elenco fisso.
const FILTRO_SCAMBIATO = FILTRO_RIORDINATO
	.replace( ' style="order: -1"', '' )
	.replace( /(<div class="campo-albo_tipo">.*?<\/div>)(\s*)(<div class="campo-albo_organo">.*?<\/div>)/s, ( _, tipo, a_capo, organo ) => organo.replace( '">', '" style="order: 1">' ) + a_capo + tipo.replace( '">', '" style="order: 0">' ) )
	.replace( /<div class="campo-albo_(testo|dal|al)">/g, ( d, c ) => d.replace( '">', `" style="order: ${ { testo: -1, dal: 2, al: 3 }[ c ] }">` ) )
	.replace( '<button type="submit">', '<button type="submit" style="order: 4">' );

// La ricerca per date con il conteggio giusto ma senza la tabella degli atti.
const DATE_SENZA_TABELLA = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Ricerca</title></head>
<body><main><div class="albo-pretorio"><p class="albo-pretorio__conteggio">CONTEGGIO</p></div></main></body></html>`;

// Collegamenti che hanno il fuoco ma non portano da nessuna parte.
const COLLEGAMENTI_MORTI = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Elenco</title></head>
<body><main><div class="albo-pretorio"><nav><a class="albo-pretorio__collegamento" href="#">2</a></nav></div></main></body></html>`;

const SENZA_ERRORE = `<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Ricerca</title></head>
<body><main><div class="albo-pretorio"><form><label for="albo_dal">Dal</label><input type="date" id="albo_dal" name="albo_dal"></form></div></main></body></html>`;

let errori = 0;

function sbaglia( messaggio ) {
	errori++;
	console.log( `  ERRORE: ${ messaggio }` );
}

async function esamina( pagina ) {
	await pagina.addScriptTag( { content: motore } );

	return pagina.evaluate( async ( regole ) => {
		// eslint-disable-next-line no-undef
		const esito = await axe.run( document, { runOnly: { type: 'tag', values: regole }, resultTypes: [ 'violations' ] } );

		return esito.violations.map( ( v ) => ( {
			regola: v.id,
			impatto: v.impact,
			cosa: v.help,
			dove: v.nodes.map( ( n ) => n.target.join( ' ' ) ),
			// Almeno un punto dentro il codice dell'albo, o nel documento intero (titolo, lingua).
			albo: v.nodes.some( ( n ) => {
				const nodo = document.querySelector( n.target[ 0 ] );

				return ! nodo || nodo === document.documentElement || nodo.closest( '.albo-pretorio' ) !== null;
			} ),
		} ) );
	}, REGOLE );
}

/**
 * Il percorso con il tasto Tab dall'inizio della pagina.
 *
 * Ogni elemento che si puo' raggiungere riceve il suo posto nell'ordine della
 * pagina; poi si preme Tab una volta per elemento, piu' due, e si annota dove
 * sta il fuoco e se l'indicatore si vede.
 */
/**
 * Quanti pixel cambiano, in modo visibile, fra due fotografie dello stesso riquadro.
 *
 * Il confronto si fa in una pagina a parte, con una tela: nessuna dipendenza in
 * piu'. Cambia un pixel che in almeno un canale si sposta di 48 su 255.
 */
let confronto = null;

async function pixel_cambiati( prima, dopo ) {
	return confronto.evaluate( async ( [ a, b ] ) => {
		const carica = ( dati ) => new Promise( ( fatto, rotto ) => {
			const immagine = new Image();
			immagine.onload = () => fatto( immagine );
			immagine.onerror = rotto;
			immagine.src = 'data:image/png;base64,' + dati;
		} );
		const [ uno, due ] = await Promise.all( [ carica( a ), carica( b ) ] );
		const larghezza = Math.min( uno.width, due.width );
		const altezza = Math.min( uno.height, due.height );
		const leggi = ( immagine ) => {
			const tela = document.createElement( 'canvas' );
			tela.width = larghezza;
			tela.height = altezza;
			const disegno = tela.getContext( '2d' );
			disegno.drawImage( immagine, 0, 0 );
			return disegno.getImageData( 0, 0, larghezza, altezza ).data;
		};
		const x = leggi( uno );
		const y = leggi( due );
		let cambiati = 0;

		for ( let i = 0; i < x.length; i += 4 ) {
			if ( Math.max( Math.abs( x[ i ] - y[ i ] ), Math.abs( x[ i + 1 ] - y[ i + 1 ] ), Math.abs( x[ i + 2 ] - y[ i + 2 ] ) ) >= 48 ) {
				cambiati++;
			}
		}

		return cambiati;
	}, [ prima.toString( 'base64' ), dopo.toString( 'base64' ) ] );
}

/**
 * L'indicatore del fuoco dell'elemento che ha il fuoco si vede?
 *
 * Si fotografa il riquadro dell'elemento, con un margine per il contorno, con
 * il fuoco e senza, e si contano i pixel cambiati: almeno quanti ne servono per
 * un filo lungo tutto il perimetro. Poi il fuoco torna dov'era, e il Tab dopo
 * riparte da li'.
 */
async function indicatore_visibile( pagina ) {
	const riquadro = await pagina.evaluate( () => {
		const r = document.activeElement.getBoundingClientRect();
		const margine = 6;
		const x = Math.max( 0, r.left - margine );
		const y = Math.max( 0, r.top - margine );

		return {
			x,
			y,
			width: Math.max( 1, Math.min( window.innerWidth, r.right + margine ) - x ),
			height: Math.max( 1, Math.min( window.innerHeight, r.bottom + margine ) - y ),
			perimetro: 2 * ( r.width + r.height ),
		};
	} );
	const { perimetro, ...clip } = riquadro;
	const con = await pagina.screenshot( { clip, animations: 'disabled', caret: 'hide' } );

	await pagina.evaluate( () => {
		window.__alboFuoco = document.activeElement;
		document.activeElement.blur();
	} );
	const senza = await pagina.screenshot( { clip, animations: 'disabled', caret: 'hide' } );
	await pagina.evaluate( () => window.__alboFuoco.focus() );

	return ( await pixel_cambiati( con, senza ) ) >= perimetro;
}

async function percorso( pagina ) {
	const elementi = await pagina.evaluate( () => {
		/*
		 * Due pulsanti fuori dall'albo, in cima e in fondo alla pagina: dall'albo
		 * si deve poter arrivare a loro, in avanti e all'indietro. Senza, in un
		 * browser senza barra degli indirizzi il fuoco dopo l'ultimo comando
		 * torna da se' al primo, e un ciclo voluto non si distinguerebbe.
		 */
		for ( const [ dove, nome ] of [ [ 'afterbegin', 'sentinella in cima' ], [ 'beforeend', 'sentinella in fondo' ] ] ) {
			document.body.insertAdjacentHTML( dove, `<button type="button" data-sentinella-prova>${ nome }</button>` );
		}

		const selettore = 'a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
		const visibili = [ ...document.querySelectorAll( selettore ) ].filter( ( e ) => {
			const s = getComputedStyle( e );
			const r = e.getBoundingClientRect();

			return s.visibility !== 'hidden' && s.display !== 'none' && ( r.width > 0 || r.height > 0 );
		} );

		visibili.forEach( ( e, i ) => e.setAttribute( 'data-ordine-prova', String( i ) ) );

		return visibili.map( ( e ) => ( {
			albo: e.closest( '.albo-pretorio' ) !== null,
			date: [ 'date', 'time', 'datetime-local', 'month', 'week' ].includes( e.getAttribute( 'type' ) ),
			nome: ( e.getAttribute( 'id' ) || e.getAttribute( 'name' ) || e.textContent || e.tagName ).trim().slice( 0, 40 ),
		} ) );
	} );

	await pagina.evaluate( () => {
		document.activeElement?.blur();
		window.scrollTo( 0, 0 );
	} );

	const passi = [];
	// Un campo di data si percorre a pezzi, giorno, mese e anno: fino a tre Tab sullo stesso campo.
	const pressioni = elementi.length + 2 + 2 * elementi.filter( ( e ) => e.date ).length;

	for ( let i = 0; i < pressioni; i++ ) {
		await pagina.keyboard.press( 'Tab' );

		const posto = await pagina.evaluate( () => {
			const a = document.activeElement;

			return ! a || a === document.body || ! a.hasAttribute( 'data-ordine-prova' ) ? null : Number( a.getAttribute( 'data-ordine-prova' ) );
		} );

		if ( null === posto ) {
			passi.push( null );
			continue;
		}

		// Si fotografa all'arrivo sull'elemento, non a ogni pezzo di un campo di data.
		const precedente = passi[ passi.length - 1 ];
		const arrivo = ! precedente || precedente.posto !== posto;

		passi.push( { posto, visibile: arrivo && elementi[ posto ].albo ? await indicatore_visibile( pagina ) : precedente?.visibile ?? true } );
	}

	// All'indietro: dal primo comando dell'albo, Maiuscolo e Tab portano fuori dall'albo.
	await pagina.evaluate( () => document.querySelector( '.albo-pretorio [data-ordine-prova]' )?.focus() );
	await pagina.keyboard.press( 'Shift+Tab' );
	const indietro = await pagina.evaluate( () => {
		const a = document.activeElement;

		return ! a || a === document.body || ! a.hasAttribute( 'data-ordine-prova' ) ? null : Number( a.getAttribute( 'data-ordine-prova' ) );
	} );

	return { elementi, passi, indietro };
}

function controlla_percorso( { elementi, passi: grezzi, indietro }, etichetta ) {
	const trovati = [];
	const n = elementi.length;

	// I Tab di seguito sullo stesso campo di data sono i suoi pezzi: contano come uno.
	const passi = grezzi.filter( ( p, i ) => ! ( i > 0 && p && grezzi[ i - 1 ] && p.posto === grezzi[ i - 1 ].posto && elementi[ p.posto ]?.date ) );

	if ( elementi.filter( ( e ) => e.albo ).length === 0 ) {
		sbaglia( `${ etichetta }: nessun comando dell'albo nella pagina, la prova non guarda niente` );
		return trovati;
	}

	for ( let i = 0; i < n; i++ ) {
		const passo = passi[ i ];

		if ( ! passo || passo.posto !== i ) {
			if ( passo && ! elementi[ i ].albo && elementi[ passo.posto ]?.albo ) {
				trovati.push( `dopo l'ultimo comando dell'albo il fuoco resta nell'albo, su "${ elementi[ passo.posto ].nome }", invece di passare a "${ elementi[ i ].nome }"` );
				break;
			}

			trovati.push( `al Tab numero ${ i + 1 } il fuoco doveva essere su "${ elementi[ i ].nome }" (posto ${ i }), e' su ${ passo ? `"${ elementi[ passo.posto ]?.nome }" (posto ${ passo.posto })` : 'nessun elemento' }` );
			break;
		}

		if ( elementi[ i ].albo && ! passo.visibile ) {
			trovati.push( `"${ elementi[ i ].nome }" riceve il fuoco senza un indicatore visibile` );
		}
	}

	/*
	 * Dall'albo si esce: in avanti lo dice l'ordine, che dopo l'ultimo comando
	 * dell'albo vuole il primo che sta fuori (al piu' la sentinella in fondo);
	 * all'indietro, dal primo comando dell'albo il fuoco va fuori dall'albo. Un
	 * ritorno al primo o all'ultimo comando dell'albo e' un ciclo che trattiene
	 * chi usa la tastiera.
	 */
	if ( null === indietro || elementi[ indietro ]?.albo ) {
		trovati.push( `all'indietro dal primo comando dell'albo il fuoco resta nell'albo, su ${ null === indietro ? 'nessun elemento' : `"${ elementi[ indietro ].nome }"` }` );
	}

	return trovati;
}

/**
 * Gli oggetti degli atti elencati nella pagina, nell'ordine.
 */
async function oggetti_elencati( pagina ) {
	return ( await pagina.locator( 'tr.albo-pretorio__atto .albo-pretorio__cella--oggetto a.albo-pretorio__collegamento' ).allTextContents() ).map( ( t ) => t.trim() );
}

function stessi( trovati, attesi ) {
	return trovati.length === attesi.length && [ ...trovati ].sort().join( '\n' ) === [ ...attesi ].sort().join( '\n' );
}

/**
 * La pagina mostra cio' che il collaudo dice di esaminare? Elenco dei problemi.
 *
 * @param {Object} pagina  Pagina del browser.
 * @param {string} nome    Quale pagina.
 * @param {Object} attese  Cio' che deve mostrare, da `prepara-sito.php`.
 * @param {Object} memoria Gli atti visti nell'elenco, per la seconda pagina.
 */
async function verifica_contenuto( pagina, nome, attese, memoria = {} ) {
	const problemi = [];
	const oggetti = await oggetti_elencati( pagina );
	const tutti = attese.oggetti ?? [];
	const perPagina = attese[ 'atti per pagina' ];

	if ( 'elenco' === nome ) {
		if ( oggetti.length !== Math.min( perPagina, tutti.length ) || ! oggetti.every( ( o ) => tutti.includes( o ) ) || new Set( oggetti ).size !== oggetti.length ) {
			problemi.push( `l'elenco mostra ${ oggetti.length } atti, attesi i primi ${ Math.min( perPagina, tutti.length ) } dei ${ tutti.length } pubblicati` );
		}

		memoria.elenco = oggetti;
	}

	if ( 'seconda pagina' === nome ) {
		const resto = tutti.filter( ( o ) => ! ( memoria.elenco ?? [] ).includes( o ) );

		if ( ! memoria.elenco || ! stessi( oggetti, resto ) ) {
			problemi.push( `la seconda pagina mostra ${ JSON.stringify( oggetti ) }, attesi gli atti che la prima non mostra (${ JSON.stringify( resto ) })` );
		}
	}

	if ( 'ricerca con un filtro' === nome && ! stessi( oggetti, attese[ 'oggetti del tipo scelto' ] ?? [ '' ] ) ) {
		problemi.push( `la ricerca per tipo mostra ${ oggetti.length } atti, attesi i ${ attese[ 'oggetti del tipo scelto' ]?.length } del tipo scelto` );
	}

	if ( 'ricerca senza risultati' === nome ) {
		const conteggio = ( await pagina.locator( '.albo-pretorio__conteggio' ).allTextContents() ).join( ' ' );

		if ( oggetti.length !== 0 || ! conteggio.includes( 'Nessun atto' ) ) {
			problemi.push( `la ricerca senza risultati mostra ${ oggetti.length } atti e il messaggio "${ conteggio.trim() }"` );
		}
	}

	if ( 'ricerca scritta male' === nome ) {
		const errore = pagina.locator( '#albo-pretorio-errore-ricerca' );
		const campo = pagina.locator( 'input[name="albo_dal"]' );
		const testo = ( await errore.count() ) === 1 ? ( await errore.innerText() ).trim() : '';
		const visibile = ( await errore.count() ) === 1 && ( await errore.isVisible() ) && ( await errore.getAttribute( 'role' ) ) === 'alert' && testo.includes( attese[ 'errore della ricerca scritta male' ] ?? '\u0000' );
		const legato = ( await campo.count() ) === 1 && ( await campo.getAttribute( 'aria-invalid' ) ) === 'true' && ( ( await campo.getAttribute( 'aria-describedby' ) ) ?? '' ).split( ' ' ).includes( 'albo-pretorio-errore-ricerca' );

		if ( ! visibile || ! legato || oggetti.length !== 0 ) {
			problemi.push( `la ricerca scritta male non mostra l'errore legato al campo (messaggio visibile con la spiegazione attesa: ${ visibile }, testo "${ testo }", legato al campo: ${ legato }, atti: ${ oggetti.length })` );
		}
	}

	if ( 'scheda con allegati' === nome ) {
		const titolo = ( await pagina.locator( '#albo-pretorio-titolo-atto' ).allTextContents() ).map( ( t ) => t.trim() );
		const documenti = pagina.locator( 'li.albo-pretorio__documento a.albo-pretorio__collegamento' );
		const nomi = ( await documenti.allTextContents() ).map( ( t ) => t.trim() );

		if ( titolo.length !== 1 || titolo[ 0 ] !== attese[ 'oggetto della scheda' ] ) {
			problemi.push( `la scheda ha il titolo ${ JSON.stringify( titolo ) }, atteso "${ attese[ 'oggetto della scheda' ] }"` );
		}

		// Nel codice non basta: ogni documento si deve vedere, o nessuno lo apre.
		const nascosti = await documenti.evaluateAll( ( a ) => a.filter( ( e ) => {
			const r = e.getBoundingClientRect();

			return ! e.checkVisibility( { checkOpacity: true, checkVisibilityCSS: true } ) || r.width === 0 || r.height === 0 || e.closest( '[inert]' ) !== null;
		} ).map( ( e ) => e.textContent.trim() ) );

		if ( JSON.stringify( nomi ) !== JSON.stringify( attese[ 'documenti della scheda' ] ?? [] ) ) {
			problemi.push( `la scheda ha i documenti ${ JSON.stringify( nomi ) }, attesi ${ JSON.stringify( attese[ 'documenti della scheda' ] ) }` );
		} else if ( nascosti.length > 0 ) {
			problemi.push( `i documenti ${ JSON.stringify( nascosti ) } della scheda ci sono nel codice ma non si vedono` );
		} else {
			for ( const indirizzo of await documenti.evaluateAll( ( a ) => a.map( ( e ) => e.href ) ) ) {
				const risposta = await pagina.request.get( indirizzo );

				if ( risposta.status() !== 200 || ! ( await risposta.body() ).subarray( 0, 5 ).equals( Buffer.from( '%PDF-' ) ) ) {
					problemi.push( `il documento ${ indirizzo } risponde ${ risposta.status() } senza il PDF` );
				}
			}
		}
	}

	return problemi;
}

/**
 * I documenti della scheda che il Tab non raggiunge.
 *
 * Un elemento sta nel percorso se e' visibile e il Tab ci e' arrivato.
 */
function documenti_fuori_dal_percorso( giro, nome, attese ) {
	if ( 'scheda con allegati' !== nome ) {
		return [];
	}

	const toccati = new Set( giro.passi.filter( Boolean ).map( ( p ) => p.posto ) );

	return ( attese[ 'documenti della scheda' ] ?? [] )
		.filter( ( d ) => ! giro.elementi.some( ( e, posto ) => e.albo && e.nome === d && toccati.has( posto ) ) )
		.map( ( d ) => `il documento "${ d }" non e' nel percorso` );
}

/**
 * La ricerca per organo senza mouse, dalla pagina aperta: Tab fino all'elenco
 * degli organi, si sceglie scrivendo l'inizio del nome, Tab fino a "Cerca",
 * Invio. Devono uscire gli atti di quell'organo, e soltanto loro.
 */
async function ricerca_per_organo( pagina, attese ) {
	if ( ! ( await tab_fino_a( pagina, 'albo_organo' ) ) ) {
		return [ 'l\'elenco degli organi non si raggiunge con Tab' ];
	}

	await pagina.keyboard.type( attese[ 'nome dell\'organo scelto' ].slice( 0, 3 ) );

	if ( ! ( await tab_fino_al_pulsante( pagina, 'Cerca' ) ) ) {
		return [ 'il pulsante "Cerca" non si raggiunge con Tab' ];
	}

	await Promise.all( [ pagina.waitForNavigation( { waitUntil: 'load' } ), pagina.keyboard.press( 'Enter' ) ] );

	const scelto = new URL( pagina.url() ).searchParams.get( 'albo_organo' );
	const attesi = attese[ 'oggetti dell\'organo scelto' ];

	if ( scelto !== String( attese[ 'organo scelto' ] ) ) {
		return [ `la ricerca per organo manda l'organo ${ scelto }, atteso ${ attese[ 'organo scelto' ] } (${ attese[ 'nome dell\'organo scelto' ] })` ];
	}

	const esito = await verifica_tutti_gli_atti( pagina, { oggetti: attesi } );

	return esito.problemi;
}

/**
 * Una ricerca che deve trovare tutti gli atti: il conteggio e le righe di ogni
 * pagina dei risultati, seguita da tastiera, sono esattamente gli atti attesi.
 *
 * Restituisce i problemi e gli indirizzi delle pagine seguenti visitate.
 */
async function verifica_tutti_gli_atti( pagina, attese ) {
	const problemi = [];
	const indirizzi = [];
	const conteggio = ( await pagina.locator( '.albo-pretorio__conteggio' ).first().textContent( { timeout: 2000 } ).catch( () => '' ) )?.trim();
	const visti = await oggetti_elencati( pagina );

	if ( conteggio !== `${ attese.oggetti.length } atti` ) {
		problemi.push( `il conteggio dice "${ conteggio }", attesi ${ attese.oggetti.length } atti` );
	}

	for ( let numero = 2; visti.length > 0 && visti.length < attese.oggetti.length && numero <= attese.oggetti.length; numero++ ) {
		const indirizzo = await segui_da_tastiera( pagina, String( numero ) );

		if ( ! indirizzo ) {
			problemi.push( `la pagina ${ numero } dei risultati non si raggiunge da tastiera` );
			break;
		}

		indirizzi.push( indirizzo );

		const altri = await oggetti_elencati( pagina );

		if ( altri.length === 0 ) {
			break;
		}

		visti.push( ...altri );
	}

	if ( ! stessi( visti, attese.oggetti ) ) {
		problemi.push( `le righe dei risultati sono ${ visti.length } (${ new Set( visti ).size } diverse), attesi esattamente i ${ attese.oggetti.length } atti` );
	}

	return { problemi, indirizzi };
}

/**
 * I comandi della ricerca che ALBO-16 richiede: ci sono tutti, si vedono, sono attivi.
 *
 * L'elenco e' fisso e non ricavato dalla pagina: un campo spento o nascosto
 * sparirebbe dall'inventario del percorso, e la prova resterebbe verde.
 */
const COMANDI_RICHIESTI = [ 'albo_testo', 'albo_tipo', 'albo_organo', 'albo_dal', 'albo_al' ];

async function verifica_comandi( pagina ) {
	return pagina.evaluate( ( richiesti ) => {
		const problemi = [];
		const albo = document.querySelector( '.albo-pretorio' );
		const pulsanti = [ ...( albo?.querySelectorAll( 'button' ) ?? [] ) ].filter( ( b ) => b.textContent.trim() === 'Cerca' );
		const usabile = ( e ) => {
			const s = getComputedStyle( e );
			const r = e.getBoundingClientRect();

			return ! e.disabled && ! e.readOnly && ! e.closest( '[inert]' ) && s.visibility !== 'hidden' && s.display !== 'none' && r.width > 0 && r.height > 0;
		};

		for ( const id of richiesti ) {
			const campo = albo?.querySelector( `#${ id }` );

			if ( ! campo ) {
				problemi.push( `il comando "${ id }" della ricerca manca` );
			} else if ( ! usabile( campo ) ) {
				problemi.push( `il comando "${ id }" della ricerca c'e' ma non si usa (spento, di sola lettura o nascosto)` );
			}
		}

		if ( pulsanti.length !== 1 || ! usabile( pulsanti[ 0 ] ) ) {
			problemi.push( 'il pulsante "Cerca" manca o non si usa' );
		}

		/*
		 * L'ordine in cui si vedono deve essere quello in cui li visita il Tab,
		 * che il percorso confronta con l'ordine della pagina: per righe, dall'alto,
		 * e in ogni riga da sinistra. Due comandi stanno nella stessa riga se i
		 * loro riquadri si sovrappongono in altezza.
		 */
		const comandi = [ ...richiesti.map( ( id ) => albo?.querySelector( `#${ id }` ) ), pulsanti[ 0 ] ];

		if ( problemi.length === 0 && comandi.every( Boolean ) ) {
			const riquadri = comandi.map( ( e ) => ( { e, nome: e.id || e.textContent.trim(), r: e.getBoundingClientRect() } ) );
			const righe = [];

			for ( const q of [ ...riquadri ].sort( ( a, b ) => a.r.top - b.r.top ) ) {
				const riga = righe.find( ( g ) => g.some( ( o ) => q.r.top < o.r.bottom && o.r.top < q.r.bottom ) );

				( riga ?? righe[ righe.push( [] ) - 1 ] ).push( q );
			}

			const aVista = righe.flatMap( ( g ) => g.sort( ( a, b ) => a.r.left - b.r.left ) ).map( ( q ) => q.nome );
			// L'ordine della pagina e' quello del codice, non quello dell'elenco fisso: e' l'ordine che segue il Tab.
			const nellaPagina = [ ...riquadri ].sort( ( a, b ) => ( a.e.compareDocumentPosition( b.e ) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1 ) ).map( ( q ) => q.nome );

			if ( aVista.join( ',' ) !== nellaPagina.join( ',' ) ) {
				problemi.push( `i comandi della ricerca si vedono nell'ordine ${ aVista.join( ', ' ) }, ma il Tab li visita nell'ordine ${ nellaPagina.join( ', ' ) }` );
			}
		}

		return problemi;
	}, COMANDI_RICHIESTI );
}

/**
 * Tab fino al collegamento dell'albo con quel testo, poi Invio: dove si arriva?
 *
 * Restituisce il nuovo indirizzo, o nullo se il collegamento non si raggiunge o
 * non porta a una pagina diversa.
 */
async function segui_da_tastiera( pagina, testo, massimo = 80 ) {
	await pagina.evaluate( () => document.activeElement?.blur() );

	for ( let i = 0; i < massimo; i++ ) {
		await pagina.keyboard.press( 'Tab' );

		const trovato = await pagina.evaluate( ( t ) => {
			const a = document.activeElement;

			return a?.tagName === 'A' && a.closest( '.albo-pretorio' ) !== null && a.textContent.trim() === t;
		}, testo );

		if ( trovato ) {
			const prima = pagina.url();

			await pagina.keyboard.press( 'Enter' );

			try {
				await pagina.waitForURL( ( u ) => u.toString() !== prima && ! u.toString().startsWith( prima + '#' ) && ! u.toString().endsWith( '#' ), { timeout: 5000, waitUntil: 'load' } );
			} catch {
				return null;
			}

			return pagina.url();
		}
	}

	return null;
}

/**
 * Le cifre da scrivere in un campo di data per quel giorno.
 *
 * L'ordine di giorno, mese e anno dipende dalla lingua del browser, che sulla
 * macchina dei controlli non si sceglie: lo si prova su un campo a parte, e si
 * usa quello che da' la data giusta.
 */
async function cifre_della_data( iso ) {
	const [ anno, mese, giorno ] = iso.split( '-' );

	for ( const cifre of [ giorno + mese + anno, mese + giorno + anno, anno + mese + giorno ] ) {
		await confronto.setContent( '<input type="date" id="prova-data">' );
		await confronto.focus( '#prova-data' );
		await confronto.keyboard.type( cifre );

		if ( iso === ( await confronto.evaluate( () => document.getElementById( 'prova-data' ).value ) ) ) {
			return cifre;
		}
	}

	throw new Error( `nessun ordine di scrittura da' la data ${ iso } in un campo di data` );
}

async function tab_fino_a( pagina, id, massimo = 80, daCapo = true ) {
	if ( daCapo ) {
		await pagina.evaluate( () => document.activeElement?.blur() );
	}

	for ( let i = 0; i < massimo; i++ ) {
		await pagina.keyboard.press( 'Tab' );

		if ( await pagina.evaluate( ( atteso ) => document.activeElement?.id === atteso, id ) ) {
			return true;
		}
	}

	return false;
}

async function tab_fino_al_pulsante( pagina, testo, massimo = 20 ) {
	for ( let i = 0; i < massimo; i++ ) {
		await pagina.keyboard.press( 'Tab' );

		if ( await pagina.evaluate( ( t ) => document.activeElement?.tagName === 'BUTTON' && document.activeElement.textContent.trim() === t, testo ) ) {
			return true;
		}
	}

	return false;
}

const browser = await chromium.launch( { executablePath: process.env.CHROME } );

try {
	confronto = await ( await browser.newContext() ).newPage();

	// Controllo di realta': il motore e il percorso vedono i guasti della pagina guastata.
	{
		const contesto = await browser.newContext( { viewport: SCHERMI.largo } );
		const pagina = await contesto.newPage();

		await pagina.setContent( GUASTA );

		// Trovati, e riconosciuti come dell'albo: altrimenti su un tema vero diventerebbero note.
		const regole = ( await esamina( pagina ) ).filter( ( v ) => v.albo ).map( ( v ) => v.regola );

		for ( const attesa of [ 'image-alt', 'label' ] ) {
			if ( ! regole.includes( attesa ) ) {
				sbaglia( `controllo di realta': sulla pagina guastata il motore non trova "${ attesa }" (trova: ${ regole.join( ', ' ) || 'niente' })` );
			}
		}

		if ( conTastiera ) {
			await pagina.setContent( GUASTA );
			const trovati = controlla_percorso( await percorso( pagina ), 'pagina guastata' );

			for ( const [ nome, attesa ] of [ [ 'uno', 'il fuoco senza indicatore' ], [ 'due', 'il contorno trasparente' ] ] ) {
				if ( ! trovati.some( ( t ) => t.startsWith( `"${ nome }" riceve il fuoco senza un indicatore visibile` ) ) ) {
					sbaglia( `controllo di realta': sulla pagina guastata il percorso da tastiera non vede ${ attesa }` );
				}
			}

			if ( ! trovati.some( ( t ) => t.startsWith( 'dopo l\'ultimo comando dell\'albo il fuoco resta nell\'albo' ) ) ) {
				sbaglia( 'controllo di realta\': sulla pagina guastata il percorso da tastiera non vede il ciclo dall\'ultimo comando al primo' );
			}
		}

		for ( const [ html, nome, attesa ] of [ [ SENZA_DOCUMENTI, 'scheda con allegati', 'la scheda senza documenti' ], [ SENZA_ERRORE, 'ricerca scritta male', 'la ricerca scritta male senza messaggio' ], [ ERRORE_VUOTO, 'ricerca scritta male', 'l\'avviso d\'errore vuoto' ] ] ) {
			await pagina.setContent( html.replace( 'OGGETTO', dati[ 'oggetto della scheda' ] ) );

			if ( ( await verifica_contenuto( pagina, nome, dati ) ).length === 0 ) {
				sbaglia( `controllo di realta': la verifica del contenuto non vede ${ attesa }` );
			}
		}

		await pagina.setContent( FILTRO_SPENTO );

		if ( ! ( await verifica_comandi( pagina ) ).some( ( p ) => p.includes( '"albo_organo"' ) ) ) {
			sbaglia( 'controllo di realta\': la verifica dei comandi non vede il filtro dell\'organo spento' );
		}

		await pagina.setContent( DATE_FERME );

		if ( ! ( await verifica_comandi( pagina ) ).some( ( p ) => p.includes( '"albo_dal"' ) ) ) {
			sbaglia( 'controllo di realta\': la verifica dei comandi non vede la data di sola lettura' );
		}

		await pagina.setContent( FILTRO_RIORDINATO );

		if ( ! ( await verifica_comandi( pagina ) ).some( ( p ) => p.startsWith( 'i comandi della ricerca si vedono nell\'ordine' ) ) ) {
			sbaglia( 'controllo di realta\': la verifica dei comandi non vede l\'organo spostato a vista prima del testo' );
		}

		await pagina.setContent( FILTRO_SCAMBIATO );

		if ( ! ( await verifica_comandi( pagina ) ).some( ( p ) => p.startsWith( 'i comandi della ricerca si vedono nell\'ordine' ) ) ) {
			sbaglia( 'controllo di realta\': la verifica dei comandi non vede tipo e organo scambiati nel codice e rimessi in ordine a vista' );
		}

		await pagina.setContent( FILTRO_SPENTO.replace( ' disabled>', '>' ) );

		if ( ( await verifica_comandi( pagina ) ).length > 0 ) {
			sbaglia( 'controllo di realta\': la verifica dei comandi rifiuta un modulo in ordine e funzionante' );
		}

		if ( conTastiera ) {
			await pagina.setContent( DATE_SENZA_TABELLA.replace( 'CONTEGGIO', `${ dati.oggetti.length } atti` ) );

			if ( ( await verifica_tutti_gli_atti( pagina, dati ) ).problemi.length === 0 ) {
				sbaglia( 'controllo di realta\': la ricerca per date con il conteggio giusto ma senza la tabella risulta riuscita' );
			}

			// La scheda vera con la lista dei documenti nascosta: nomi, indirizzi e PDF giusti, ma nessuno li vede.
			await pagina.goto( dati[ 'scheda con allegati' ], { waitUntil: 'load' } );
			await pagina.addStyleTag( { content: '.albo-pretorio__documenti { display: none !important; }' } );

			if ( ( await verifica_contenuto( pagina, 'scheda con allegati', dati ) ).length === 0 ) {
				sbaglia( 'controllo di realta\': la verifica del contenuto non vede i documenti della scheda nascosti' );
			}

			if ( documenti_fuori_dal_percorso( await percorso( pagina ), 'scheda con allegati', dati ).length === 0 ) {
				sbaglia( 'controllo di realta\': il percorso da tastiera non vede i documenti della scheda nascosti' );
			}

			// L'elenco vero senza l'organo da scegliere, poi con l'organo spento.
			for ( const [ guasto, attesa ] of [ [ ( o ) => o.remove(), 'tolto' ], [ ( o ) => { o.disabled = true; }, 'spento' ] ] ) {
				await pagina.goto( dati.elenco, { waitUntil: 'load' } );
				await pagina.locator( `#albo_organo option[value="${ dati[ 'organo scelto' ] }"]` ).evaluate( guasto );

				if ( ( await ricerca_per_organo( pagina, dati ) ).length === 0 ) {
					sbaglia( `controllo di realta': la ricerca per organo riesce con l'organo da scegliere ${ attesa }` );
				}
			}

			await pagina.setContent( COLLEGAMENTI_MORTI );

			if ( null !== ( await segui_da_tastiera( pagina, '2' ) ) ) {
				sbaglia( 'controllo di realta\': un collegamento che non porta da nessuna parte risulta seguito' );
			}
		}

		await contesto.close();
		console.log( `Controllo di realta' (${ tema }): ${ errori === 0 ? 'il motore e il percorso vedono i guasti' : 'NON RIUSCITO' }` );
	}

	if ( errori > 0 ) {
		throw new Error( 'controllo di realta\' non riuscito: la prova non e\' affidabile' );
	}

	for ( const [ schermo, viewport ] of Object.entries( SCHERMI ) ) {
		const contesto = await browser.newContext( { viewport } );
		const pagina = await contesto.newPage();
		const memoria = {};

		for ( const nome of PAGINE ) {
			const risposta = await pagina.goto( dati[ nome ], { waitUntil: 'load' } );
			const etichetta = `${ tema }, ${ schermo }, ${ nome }`;

			if ( ! risposta || risposta.status() !== 200 ) {
				sbaglia( `${ etichetta }: la pagina risponde ${ risposta?.status() }` );
				continue;
			}

			if ( ( await pagina.locator( '.albo-pretorio' ).count() ) === 0 ) {
				sbaglia( `${ etichetta }: la pagina non contiene l'albo` );
				continue;
			}

			const problemi = await verifica_contenuto( pagina, nome, dati, memoria );

			// Il modulo di ricerca sta in ogni pagina dell'elenco; la scheda non lo ha.
			if ( 'scheda con allegati' !== nome ) {
				problemi.push( ...( await verifica_comandi( pagina ) ) );
			}

			if ( problemi.length > 0 ) {
				problemi.forEach( ( p ) => sbaglia( `${ etichetta }: ${ p }` ) );
				continue;
			}

			const violazioni = await esamina( pagina );

			for ( const v of violazioni ) {
				const riga = `${ etichetta }: ${ v.regola } (${ v.impatto }) ${ v.cosa } in ${ v.dove.join( '; ' ) }`;

				// Sul tema spoglio tutto e' dell'albo. Su un tema vero, cio' che sta tutto fuori dall'albo e' del tema: si annota, non ferma la prova.
				if ( v.albo || tuttaLaPagina ) {
					sbaglia( riga );
				} else {
					console.log( `  NOTA, del tema e non dell'albo: ${ riga }` );
				}
			}

			if ( conTastiera ) {
				await pagina.goto( dati[ nome ], { waitUntil: 'load' } );

				const giro = await percorso( pagina );

				for ( const t of controlla_percorso( giro, etichetta ) ) {
					sbaglia( `${ etichetta }, da tastiera: ${ t }` );
				}

				// Ogni documento della scheda e ogni comando richiesto sono fra quelli che il Tab ha toccato.
				for ( const p of documenti_fuori_dal_percorso( giro, nome, dati ) ) {
					sbaglia( `${ etichetta }, da tastiera: ${ p }` );
				}

				if ( 'scheda con allegati' !== nome ) {
					for ( const id of COMANDI_RICHIESTI ) {
						if ( ! giro.elementi.some( ( e ) => e.albo && e.nome === id ) ) {
							sbaglia( `${ etichetta }, da tastiera: il comando "${ id }" non e' nel percorso` );
						}
					}
				}
			}

			console.log( `${ etichetta }: ${ violazioni.filter( ( v ) => v.albo || tuttaLaPagina ).length } violazioni dell'albo${ conTastiera ? ', percorso da tastiera controllato' : '' }` );
		}

		if ( conTastiera ) {
			// La ricerca per testo senza mouse: Tab fino al campo, si scrive, Invio.
			await pagina.goto( dati.elenco, { waitUntil: 'load' } );

			if ( ! ( await tab_fino_a( pagina, 'albo_testo' ) ) ) {
				sbaglia( `${ tema }, ${ schermo }: il campo di ricerca non si raggiunge con Tab` );
			} else {
				await pagina.keyboard.type( 'manutenzione' );
				await Promise.all( [ pagina.waitForNavigation( { waitUntil: 'load' } ), pagina.keyboard.press( 'Enter' ) ] );

				const righe = await pagina.locator( 'tr.albo-pretorio__atto' ).allTextContents();

				if ( righe.length !== 1 || ! righe[ 0 ].includes( dati[ 'oggetto della scheda' ] ) ) {
					sbaglia( `${ tema }, ${ schermo }: la ricerca per testo da tastiera trova ${ righe.length } atti invece dell'atto atteso` );
				}
			}

			// La ricerca per tipo senza mouse: Tab fino all'elenco dei tipi, si sceglie scrivendo, Tab fino a "Cerca", Invio.
			await pagina.goto( dati.elenco, { waitUntil: 'load' } );

			if ( ! ( await tab_fino_a( pagina, 'albo_tipo' ) ) ) {
				sbaglia( `${ tema }, ${ schermo }: l'elenco dei tipi non si raggiunge con Tab` );
			} else {
				await pagina.keyboard.type( 'Avv' );

				if ( ! ( await tab_fino_al_pulsante( pagina, 'Cerca' ) ) ) {
					sbaglia( `${ tema }, ${ schermo }: il pulsante "Cerca" non si raggiunge con Tab` );
				} else {
					await Promise.all( [ pagina.waitForNavigation( { waitUntil: 'load' } ), pagina.keyboard.press( 'Enter' ) ] );

					const indirizzo = new URL( pagina.url() );
					const conteggio = ( await pagina.locator( '.albo-pretorio__conteggio' ).first().textContent() )?.trim();
					const attesi = dati[ 'oggetti del tipo scelto' ];

					if ( indirizzo.searchParams.get( 'albo_tipo' ) !== String( dati[ 'tipo scelto' ] ) || conteggio !== `${ attesi.length } atti` || ! stessi( await oggetti_elencati( pagina ), attesi ) ) {
						sbaglia( `${ tema }, ${ schermo }: la ricerca per tipo da tastiera da' "${ conteggio }" con tipo ${ indirizzo.searchParams.get( 'albo_tipo' ) }, attesi i ${ attesi.length } atti del tipo ${ dati[ 'tipo scelto' ] }` );
					}
				}
			}

			// La ricerca per organo senza mouse.
			await pagina.goto( dati.elenco, { waitUntil: 'load' } );

			for ( const p of await ricerca_per_organo( pagina, dati ) ) {
				sbaglia( `${ tema }, ${ schermo }: la ricerca per organo da tastiera: ${ p }` );
			}

			/*
			 * La ricerca per date senza mouse: Tab fino a "dal", si scrive la data di
			 * oggi a cifre, Tab fino ad "al", la stessa, Tab fino a "Cerca", Invio.
			 * Gli atti sono stati pubblicati oggi: li trova tutti.
			 */
			await pagina.goto( dati.elenco, { waitUntil: 'load' } );

			const oggi = dati.oggi;
			const cifre = await cifre_della_data( oggi );
			let date = await tab_fino_a( pagina, 'albo_dal' );

			if ( date ) {
				await pagina.keyboard.type( cifre );
				date = await tab_fino_a( pagina, 'albo_al', 10, false );
			}

			if ( date ) {
				await pagina.keyboard.type( cifre );
				date = await tab_fino_al_pulsante( pagina, 'Cerca' );
			}

			if ( ! date ) {
				sbaglia( `${ tema }, ${ schermo }: i campi delle date o "Cerca" non si raggiungono con Tab` );
			} else {
				await Promise.all( [ pagina.waitForNavigation( { waitUntil: 'load' } ), pagina.keyboard.press( 'Enter' ) ] );

				const indirizzo = new URL( pagina.url() );

				if ( indirizzo.searchParams.get( 'albo_dal' ) !== oggi || indirizzo.searchParams.get( 'albo_al' ) !== oggi ) {
					sbaglia( `${ tema }, ${ schermo }: la ricerca per date da tastiera manda dal ${ indirizzo.searchParams.get( 'albo_dal' ) } al ${ indirizzo.searchParams.get( 'albo_al' ) }, attesi ${ oggi } e ${ oggi }` );
				} else {
					const esito = await verifica_tutti_gli_atti( pagina, dati );

					// Anche le pagine seguenti restano dentro la ricerca.
					for ( const altra of esito.indirizzi ) {
						const parametri = new URL( altra ).searchParams;

						if ( parametri.get( 'albo_dal' ) !== oggi || parametri.get( 'albo_al' ) !== oggi ) {
							esito.problemi.push( `la pagina ${ altra } dei risultati ha perso le date della ricerca` );
						}
					}

					esito.problemi.forEach( ( p ) => sbaglia( `${ tema }, ${ schermo }: la ricerca per date da tastiera: ${ p }` ) );
				}
			}

			/*
			 * I collegamenti si seguono da tastiera e portano dove devono: dall'elenco
			 * alla seconda pagina, dall'elenco alla scheda, dalla scheda all'elenco.
			 * Le pagine aperte per indirizzo non lo dimostrano.
			 */
			for ( const [ partenza, testo, arrivo, parametro ] of [
				[ 'elenco', '2', 'seconda pagina', 'albo_pagina' ],
				// La scheda si apre dalla pagina dell'elenco che la mostra.
				[ ( memoria.elenco ?? [] ).includes( dati[ 'oggetto della scheda' ] ) ? 'elenco' : 'seconda pagina', dati[ 'oggetto della scheda' ], 'scheda con allegati', 'albo_atto' ],
				[ 'scheda con allegati', 'Torna all\'elenco degli atti', 'elenco', null ],
			] ) {
				await pagina.goto( dati[ partenza ], { waitUntil: 'load' } );

				const indirizzo = await segui_da_tastiera( pagina, testo );
				const attesi = new URL( dati[ arrivo ] ).searchParams;
				const ottenuti = indirizzo ? new URL( indirizzo ).searchParams : null;

				if ( ! ottenuti || ( parametro && ottenuti.get( parametro ) !== attesi.get( parametro ) ) || ( ! parametro && [ ...ottenuti.keys() ].some( ( k ) => k.startsWith( 'albo_' ) ) ) ) {
					sbaglia( `${ tema }, ${ schermo }: da "${ partenza }" il collegamento "${ testo }" seguito con Invio porta a ${ indirizzo ?? 'nessuna pagina nuova' }, atteso "${ arrivo }"` );
					continue;
				}

				for ( const p of await verifica_contenuto( pagina, arrivo, dati, memoria ) ) {
					sbaglia( `${ tema }, ${ schermo }: arrivati a "${ arrivo }" da tastiera, ${ p }` );
				}
			}

			console.log( `${ tema }, ${ schermo }: ricerca per testo, per tipo, per organo e per date, seconda pagina, scheda e ritorno fatti da sola tastiera` );
		}

		await contesto.close();
	}
} finally {
	await browser.close();
}

if ( errori > 0 ) {
	console.log( `\n${ errori } problemi su ${ tema }.` );
	process.exit( 1 );
}

console.log( `\nNessun problema su ${ tema }.` );
