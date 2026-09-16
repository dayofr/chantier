// Barre latérale réductible. L'état est posé sur <html> (data-sidebar) et mémorisé.
const KEY = 'chantier-sidebar';
const html = document.documentElement;

function sync() {
    const collapsed = html.dataset.sidebar === 'collapsed';
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        const label = collapsed ? button.dataset.labelExpand : button.dataset.labelCollapse;
        button.title = `${label} ([)`;
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-expanded', String(!collapsed));
        button.querySelector('.icon').textContent = collapsed ? 'left_panel_open' : 'left_panel_close';
    });
}

function toggle() {
    const collapsed = html.dataset.sidebar !== 'collapsed';
    if (collapsed) {
        html.dataset.sidebar = 'collapsed';
    } else {
        delete html.dataset.sidebar;
    }
    try {
        localStorage.setItem(KEY, collapsed ? 'collapsed' : 'expanded');
    } catch {}
    sync();
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-sidebar-toggle]')) toggle();
});

// Raccourci "[" comme dans Linear, hors champs de saisie.
document.addEventListener('keydown', (event) => {
    if (event.key !== '[' || event.metaKey || event.ctrlKey || event.altKey) return;
    if (event.target.closest('input, textarea, select, [contenteditable]')) return;
    toggle();
});

sync();
