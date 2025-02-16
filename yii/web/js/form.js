document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        const forms = document.querySelectorAll(".focus-on-first-field");
        forms.forEach(function(form) {
            const firstField = form.querySelector("input:not([type='hidden']):not(.resizable-editor), select:not(.resizable-editor), textarea:not(.resizable-editor)");
            if (firstField) {
                firstField.focus();
            }
        });
    }, 100);
});
