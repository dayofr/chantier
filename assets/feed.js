// « Plus ancien » en AJAX : ajoute la page suivante au journal sans recharger.
// Sans JS, ou en cas d'erreur, le lien recharge la page normalement.
document.addEventListener('click', async (event) => {
    const link = event.target.closest('a[data-load-more]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const feed = document.getElementById(link.dataset.loadMore);
    if (!feed) return;
    event.preventDefault();
    if (link.getAttribute('aria-busy') === 'true') return;
    link.setAttribute('aria-busy', 'true');

    try {
        const url = new URL(link.href);
        url.searchParams.set('fragment', '1');
        const response = await fetch(url, { headers: { Accept: 'text/html' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const fragment = new DOMParser().parseFromString(await response.text(), 'text/html');

        const items = fragment.querySelector('[data-feed-items]');
        // Même jour que la dernière entrée affichée : pas de second en-tête.
        const first = items.firstElementChild;
        const lastDay = [...feed.querySelectorAll('[data-day]')].pop()?.dataset.day;
        if (first?.dataset.day && first.dataset.day === lastDay) first.remove();

        feed.append(...items.children);
        feed.dataset.count = String(feed.querySelectorAll('[data-entry]').length);

        const next = fragment.querySelector('[data-load-more-wrap]');
        const wrap = link.closest('[data-load-more-wrap]');
        if (next) {
            wrap.replaceWith(document.adoptNode(next));
        } else {
            wrap.remove();
        }
    } catch {
        window.location.assign(link.href);
    }
});
