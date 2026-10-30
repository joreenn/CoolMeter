(() => {
    const root = document.documentElement;
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    let preference;
    try { preference = localStorage.getItem('coolmeter-theme'); } catch (_) {}
    const apply = (theme) => {
        root.dataset.theme = theme;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#0b1220' : '#f3f6f5');
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            const dark = theme === 'dark';
            button.setAttribute('aria-label', `Switch to ${dark ? 'light' : 'dark'} theme`);
            button.setAttribute('aria-pressed', String(dark));
            button.innerHTML = `<span class="theme-symbol" aria-hidden="true">${dark ? '☀' : '☾'}</span><span>${dark ? 'Light theme' : 'Dark theme'}</span>`;
        });
    };
    const current = () => preference === 'light' || preference === 'dark' ? preference : system.matches ? 'dark' : 'light';
    apply(current());
    document.addEventListener('DOMContentLoaded', () => apply(current()));
    document.addEventListener('click', event => {
        if (!event.target.closest('[data-theme-toggle]')) return;
        preference = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('coolmeter-theme', preference); } catch (_) {}
        apply(preference);
    });
    system.addEventListener('change', () => { if (!preference) apply(current()); });
    window.addEventListener('storage', event => {
        if (event.key !== 'coolmeter-theme') return;
        preference = event.newValue;
        apply(current());
    });
})();
