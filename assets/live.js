import { Idiomorph } from 'idiomorph';

// Rafraîchissement en direct : interroge la version des données et, si elle change,
// recharge la page en arrière-plan puis fusionne le DOM (scroll et blocs dépliés conservés).
const root = document.querySelector('[data-live]');

if (root) {
    const POLL_MS = 5000;
    // Rafraîchit quand même de temps en temps pour les durées relatives ("il y a 3 min").
    const STALE_MS = 60000;
    const indicator = document.querySelector('[data-live-indicator]');
    let version = root.dataset.liveVersion;
    let lastRender = Date.now();
    let timer = null;
    let busy = false;

    const setState = (state) => {
        if (!indicator) return;
        indicator.dataset.state = state;
        const label = indicator.querySelector('[data-live-label]');
        if (label) label.textContent = indicator.dataset[`label${state[0].toUpperCase()}${state.slice(1)}`];
    };

    const refresh = async () => {
        const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        // Le rétablissement du focus par Idiomorph peut faire défiler la page : on fige la position.
        const { scrollX, scrollY } = window;

        document.querySelectorAll('[data-live-region][id]').forEach((region) => {
            const next = html.getElementById(region.id);
            if (!next) return;
            Idiomorph.morph(region, next, {
                morphStyle: 'outerHTML',
                callbacks: {
                    // Garde l'état ouvert/fermé choisi par l'utilisateur.
                    beforeAttributeUpdated: (name, node) => !(name === 'open' && node.tagName === 'DETAILS'),
                },
            });
        });

        window.scrollTo(scrollX, scrollY);

        const title = html.querySelector('title');
        if (title) document.title = title.textContent;
        lastRender = Date.now();
    };

    const tick = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(root.dataset.liveUrl, { cache: 'no-store' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { version: current } = await response.json();
            if (current !== version || Date.now() - lastRender > STALE_MS) {
                await refresh();
                version = current;
            }
            setState('on');
        } catch {
            setState('offline');
        } finally {
            busy = false;
        }
    };

    const start = () => {
        if (timer) return;
        timer = setInterval(tick, POLL_MS);
        tick();
    };

    const stop = () => {
        clearInterval(timer);
        timer = null;
        setState('paused');
    };

    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
    if (!document.hidden) start();
}
