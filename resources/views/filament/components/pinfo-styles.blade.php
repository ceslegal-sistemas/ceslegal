@once
    <script src="https://cdn.lordicon.com/lordicon.js"></script>
    <script>
        (function () {
            function isDark() {
                return document.documentElement.classList.contains('dark');
            }
            function applyPtColors() {
                document.querySelectorAll('lord-icon[data-pt-icon]').forEach(function (el) {
                    el.setAttribute('colors', isDark() ? (el.dataset.ptDark || '') : (el.dataset.ptLight || ''));
                });
            }
            applyPtColors();
            setTimeout(applyPtColors, 50);
            setTimeout(applyPtColors, 300);
            new MutationObserver(applyPtColors).observe(document.documentElement, {
                attributes: true, attributeFilter: ['class']
            });
            new MutationObserver(function (mutations) {
                if (mutations.some(function (m) { return m.addedNodes.length > 0; })) applyPtColors();
            }).observe(document.body, { childList: true, subtree: true });
        })();
    </script>
@endonce

<style>
    .pt-card {
        border-left: 4px solid #6366f1;
        border-radius: .75rem;
        padding: 1.125rem 1.25rem 1rem;
        margin-bottom: .25rem;
    }
    .pt-title  { margin: 0; font-size: .8125rem; font-weight: 700; color: #e2e8f0; letter-spacing: .04em; text-transform: uppercase; }
    .pt-body   { margin: 0 0 .75rem; font-size: .875rem; line-height: 1.6; color: #cbd5e1; }
    .pt-bullet { display: flex; align-items: flex-start; gap: .5rem; font-size: .8125rem; line-height: 1.55; color: #94a3b8; }
    .pt-bullet strong { color: #e2e8f0; font-weight: 600; }
    .pt-footer { margin: 0; font-size: .75rem; color: #64748b; line-height: 1.5; border-top: 1px solid rgba(99,102,241,.18); padding-top: .625rem; }

    html:not(.dark) .pt-card              { border-left-color: #4f46e5; }
    html:not(.dark) .pt-title             { color: #111827; }
    html:not(.dark) .pt-body              { color: #374151; }
    html:not(.dark) .pt-bullet            { color: #4b5563; }
    html:not(.dark) .pt-bullet strong     { color: #111827; }
    html:not(.dark) .pt-footer            { color: #6b7280; border-top-color: rgba(99,102,241,.15); }

    .t-gold                               { color: #fbbf24; }
    html:not(.dark) .t-gold              { color: #d97706; }
</style>
