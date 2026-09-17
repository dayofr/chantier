// Nouveautés depuis la dernière visite. Mémoire par navigateur : id de la dernière entrée vue.
// - Compteur dans la navigation : entrées ajoutées depuis.
// - Sur une page de journal : entrées plus récentes que la visite précédente surlignées
//   (y compris celles arrivées en direct), séparateur avant les entrées déjà vues.
const KEY = 'chantier-last-seen-activity';
const root = document.querySelector('[data-live]');

function read() {
    try {
        const value = Number(localStorage.getItem(KEY));
        return Number.isFinite(value) && value > 0 ? value : null;
    } catch {
        return null;
    }
}

function write(id) {
    try {
        localStorage.setItem(KEY, String(id));
    } catch {}
}

const latest = () => Number(root?.dataset.latestActivity || 0);
const feed = () => document.querySelector('[data-feed]');

// Référence de cette visite : la valeur mémorisée avant d'entrer sur la page.
const previous = read();

function renderCount() {
    const seen = read();
    const count = seen === null ? 0 : Math.max(0, latest() - seen);
    document.querySelectorAll('[data-new-count]').forEach((badge) => {
        badge.hidden = count === 0;
        badge.textContent = count > 99 ? '99+' : String(count);
    });
}

function markFeed() {
    const list = feed();
    if (!list) return;

    // Voir la page vaut lecture de tout le journal existant.
    write(Math.max(latest(), read() ?? 0));

    list.querySelector('[data-seen-separator]')?.remove();
    if (previous === null) return;

    let firstSeen = null;
    list.querySelectorAll('[data-entry]').forEach((entry) => {
        const isNew = Number(entry.dataset.entry) > previous;
        if (isNew) {
            entry.dataset.new = '';
        } else {
            delete entry.dataset.new;
            firstSeen ??= entry;
        }
    });

    // Séparateur seulement s'il y a du nouveau au-dessus et du déjà vu en dessous.
    if (firstSeen && list.querySelector('[data-entry][data-new]')) {
        const separator = document.createElement('li');
        separator.dataset.seenSeparator = '';
        separator.className = 'my-4 flex items-center gap-3 text-label-md font-semibold uppercase tracking-wider text-primary';
        separator.innerHTML = '<span class="h-px flex-1 bg-primary/30"></span><span></span><span class="h-px flex-1 bg-primary/30"></span>';
        separator.children[1].textContent = list.dataset.labelSeen || '';
        // Une entrée regroupée est marquée dans son groupe : le séparateur va avant le groupe.
        const anchor = firstSeen.closest('[data-entry-group]') ?? firstSeen;
        anchor.before(separator);
    }
}

function refresh() {
    markFeed();
    renderCount();
}

document.addEventListener('chantier:refreshed', refresh);
document.addEventListener('chantier:feed-extended', markFeed);
refresh();
