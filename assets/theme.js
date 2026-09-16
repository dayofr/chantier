// Bascule de thème : system → light → dark. Le choix est mémorisé dans le navigateur.
const KEY = 'chantier-theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

function stored() {
    try {
        return localStorage.getItem(KEY) || 'system';
    } catch {
        return 'system';
    }
}

function apply(choice) {
    const dark = choice === 'dark' || (choice === 'system' && media.matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.dataset.choice = choice;
        button.title = button.dataset[`label${choice[0].toUpperCase()}${choice.slice(1)}`];
        button.querySelector('.icon').textContent = { system: 'contrast', light: 'light_mode', dark: 'dark_mode' }[choice];
    });
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-toggle]');
    if (!button) return;
    const next = { system: 'light', light: 'dark', dark: 'system' }[stored()];
    try {
        localStorage.setItem(KEY, next);
    } catch {}
    apply(next);
});

media.addEventListener('change', () => apply(stored()));
apply(stored());
