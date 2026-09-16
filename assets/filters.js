// Formulaires de filtres : chaque changement relance la recherche, sans paramètres vides dans l'URL.
document.addEventListener('change', (event) => {
    const form = event.target.closest('form[data-autosubmit]');
    if (form) form.requestSubmit();
});

// Entrée dans un champ texte : envoi explicite, sans dépendre de la soumission implicite du navigateur.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.isComposing) return;
    const field = event.target.closest('form[data-autosubmit] input[type="text"], form[data-autosubmit] input[type="search"]');
    if (!field) return;
    event.preventDefault();
    field.form.requestSubmit();
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-autosubmit]');
    if (!form) return;
    // Les champs vides sont désactivés le temps de l'envoi pour ne pas apparaître dans l'URL.
    form.querySelectorAll('input, select').forEach((field) => {
        if ((field.type === 'text' || field.type === 'search' || field.type === 'radio') && field.value === '' && (field.type !== 'radio' || field.checked)) {
            field.disabled = true;
        }
    });
});
