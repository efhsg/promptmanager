document.addEventListener('DOMContentLoaded', () => {

    const init = node => {
        if (node.dataset.inited) return;          // idempotent
        node.dataset.inited = 1;

        const hidden = document.getElementById(node.dataset.target);
        const cfg    = JSON.parse(node.dataset.config);

        const quill  = new Quill(node, cfg);      // Quill global is present
        if (hidden.value) {
            try { quill.setContents(JSON.parse(hidden.value)); }
            catch (e) { console.warn('Delta parse', e); }
        }

        quill.on('text-change', () =>
            hidden.value = JSON.stringify(quill.getContents())
        );
    };

    // 1️⃣ initial pass
    document.querySelectorAll('[data-editor="quill"]').forEach(init);

    // 2️⃣ future nodes (AJAX, modal, cloneRow...)
    new MutationObserver(muts =>
        muts.forEach(m =>
            m.addedNodes.forEach(n =>
                n.querySelectorAll?.('[data-editor="quill"]').forEach(init)
            )
        )
    ).observe(document.body, {childList:true, subtree:true});
});