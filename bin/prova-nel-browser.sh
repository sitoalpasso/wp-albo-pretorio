#!/usr/bin/env bash
#
# Prova della pagina pubblica dell'albo in un browser vero (ALBO-16, ALBO-28).
#
# Installa un sito WordPress di prova, attiva il componente comune e l'albo,
# pubblica gli atti con i passaggi veri (tests/browser/prepara-sito.php), lo
# serve con il server di PHP su questo computer e lo esamina con
# tests/browser/accessibilita.mjs: motore automatico di accessibilita' su due
# temi, il predefinito di WordPress e un tema spoglio, e percorso da sola
# tastiera sul tema spoglio. Nessuna rete esterna durante la prova: il sito, il
# motore e il browser sono tutti su questo computer.
#
# Uso: bin/prova-nel-browser.sh
#
# La prova crea da se' una cartella nuova e una banca dati nuova, e alla fine le
# toglie: non riusa ne' tocca niente che esisteva prima. Se la cartella o la banca
# dati indicate esistono gia', si ferma prima di scrivere.
#
# Variabili (con il valore che si usa se mancano):
#   WP_DIR       cartella del sito di prova, che non deve esistere (una nuova sotto /tmp)
#   WP_SORGENTE  cartella con WordPress da copiare invece di scaricarlo (nessuna)
#   WP_VERSION   versione da scaricare (latest)
#   WP_CLI       comando di wp-cli (wp)
#   DB_NAME      banca dati, che non deve esistere (albo_prova_ seguito da un codice casuale)
#   DB_USER, DB_PASS, DB_HOST  accesso alla banca dati (root, root, 127.0.0.1)
#   CHROME       percorso del browser (obbligatoria)
#   PORTA        porta del server di prova (8889)
#   TEMA_PREDEFINITO  tema di WordPress da esaminare (twentytwentyfive)

set -euo pipefail

RADICE="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
CODICE="$( openssl rand -hex 6 )"
WP_DIR="${WP_DIR:-/tmp/albo-prova-${CODICE}}"
WP_VERSION="${WP_VERSION:-latest}"
read -r -a WP <<< "${WP_CLI:-wp}"
DB_NAME="${DB_NAME:-albo_prova_${CODICE}}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
DB_HOST="${DB_HOST:-127.0.0.1}"
PORTA="${PORTA:-8889}"
TEMA_PREDEFINITO="${TEMA_PREDEFINITO:-twentytwentyfive}"
TEMA_SPOGLIO="albo-prova-tema-spoglio"
INDIRIZZO="http://127.0.0.1:${PORTA}"
PAGINE="$( mktemp )"
SERVER=""
CARTELLA_CREATA=""
BANCA_CREATA=""

: "${CHROME:?indicare il percorso del browser nella variabile CHROME}"

wp_sito() {
	"${WP[@]}" --path="${WP_DIR}" --url="${INDIRIZZO}" "$@"
}

# Si toglie solo cio' che questa esecuzione ha creato.
pulisci() {
	if [ -n "${SERVER}" ]; then
		kill "${SERVER}" 2> /dev/null || true
	fi

	if [ -n "${BANCA_CREATA}" ]; then
		wp_sito db drop --yes > /dev/null 2>&1 || true
	fi

	if [ -n "${CARTELLA_CREATA}" ]; then
		rm -rf -- "${CARTELLA_CREATA}"
	fi

	rm -f "${PAGINE}"
}
trap pulisci EXIT

case "${DB_NAME}" in
	*[!A-Za-z0-9_]*)
		echo "Nome della banca dati non accettato: ${DB_NAME}" >&2
		exit 2
		;;
esac

if [ -e "${WP_DIR}" ] || [ -L "${WP_DIR}" ]; then
	echo "La cartella ${WP_DIR} esiste gia': la prova ne vuole una nuova e non tocca quelle che ci sono." >&2
	exit 2
fi

mkdir -p -- "$( dirname -- "${WP_DIR}" )"
mkdir -- "${WP_DIR}"
CARTELLA_CREATA="$( cd -- "${WP_DIR}" && pwd -P )"
WP_DIR="${CARTELLA_CREATA}"

if [ -n "${WP_SORGENTE:-}" ]; then
	cp -R -- "${WP_SORGENTE}/." "${WP_DIR}/"
else
	"${WP[@]}" core download --path="${WP_DIR}" --version="${WP_VERSION}"
fi

wp_sito config create --dbname="${DB_NAME}" --dbuser="${DB_USER}" --dbpass="${DB_PASS}" --dbhost="${DB_HOST}" --skip-check

# `db create` si rifiuta se la banca dati esiste gia': niente di esistente viene azzerato.
if ! wp_sito db create; then
	echo "La banca dati ${DB_NAME} non si crea (esiste gia'?): la prova si ferma senza toccarla." >&2
	exit 2
fi
BANCA_CREATA=1

wp_sito core install --url="${INDIRIZZO}" --title="Sito di prova" --admin_user=amministratore --admin_password="$( openssl rand -hex 16 )" --admin_email=amministratore@example.org --skip-email

# L'indirizzo del sito lo si scrive: wp-cli lo indovinerebbe dalla cartella.
wp_sito option update siteurl "${INDIRIZZO}"
wp_sito option update home "${INDIRIZZO}"

# Copiati e non collegati: con un collegamento simbolico WordPress calcola male
# l'indirizzo del foglio di stile dell'albo, e la pagina uscirebbe senza.
rsync -a --exclude=.git --exclude=vendor --exclude=node_modules --exclude=tests "${RADICE}/" "${WP_DIR}/wp-content/plugins/albo-pretorio-pa/"
cp -r "${RADICE}/tests/browser/tema-spoglio" "${WP_DIR}/wp-content/themes/${TEMA_SPOGLIO}"

if [ ! -f "${WP_DIR}/wp-content/plugins/conformita-core/conformita-core.php" ]; then
	WP_CORE_DIR="${WP_DIR}" "${RADICE}/bin/installa-core.sh"
fi

wp_sito plugin activate conformita-core albo-pretorio-pa
wp_sito eval-file "${RADICE}/tests/browser/prepara-sito.php" > "${PAGINE}"

php -S "127.0.0.1:${PORTA}" -t "${WP_DIR}" > "${WP_DIR}/server-di-prova.log" 2>&1 &
SERVER=$!

for _ in $( seq 1 50 ); do
	curl -fs -o /dev/null "${INDIRIZZO}/" && break
	sleep 0.2
done

( cd "${RADICE}/tests/browser" && npm ci --no-audit --no-fund --silent )

esito=0

wp_sito theme activate "${TEMA_PREDEFINITO}"
CHROME="${CHROME}" node "${RADICE}/tests/browser/accessibilita.mjs" --pagine "${PAGINE}" --tema "${TEMA_PREDEFINITO}" || esito=1

wp_sito theme activate "${TEMA_SPOGLIO}"
CHROME="${CHROME}" node "${RADICE}/tests/browser/accessibilita.mjs" --pagine "${PAGINE}" --tema "${TEMA_SPOGLIO}" --tastiera --tutta-la-pagina || esito=1

exit "${esito}"
