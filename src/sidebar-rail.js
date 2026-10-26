/* Collapsible icon-rail sidebar
   - Toggle button in sidebar footer, or press "[" (when not typing)
   - Hovering a collapsed rail expands it as an overlay ("peek")
   - State is remembered in localStorage
*/
(() => {
    const KEY = "nm.sidebar";
    const HOVER_PEEK = true;   // set false to disable hover-to-expand
    const PEEK_DELAY = 200;    // ms before peek opens (lets you click icons quickly)

    const sb = document.getElementById("sidebar");
    const btn = document.getElementById("railToggleBtn");
    if (!sb) return;

    const desktop = matchMedia("(min-width: 1024px)");
    const canHover = matchMedia("(hover: hover) and (pointer: fine)");
    let peekTimer;

    const save = (v) => { try { localStorage.setItem(KEY, v); } catch (e) {} };

    function setCollapsed(collapsed) {
        sb.classList.toggle("is-collapsed", collapsed);
        sb.classList.remove("is-peek");
        save(collapsed ? "collapsed" : "expanded");
        if (btn) {
            btn.title = collapsed ? "Expand sidebar  [" : "Collapse sidebar  [";
            btn.setAttribute("aria-expanded", String(!collapsed));
        }
    }

    window.toggleSidebarRail = () => setCollapsed(!sb.classList.contains("is-collapsed"));

    btn?.addEventListener("click", (e) => {
        e.stopPropagation();
        window.toggleSidebarRail();
    });

    // Hover peek
    sb.addEventListener("mouseenter", () => {
        if (!HOVER_PEEK || !desktop.matches || !canHover.matches) return;
        if (!sb.classList.contains("is-collapsed")) return;
        clearTimeout(peekTimer);
        peekTimer = setTimeout(() => sb.classList.add("is-peek"), PEEK_DELAY);
    });
    sb.addEventListener("mouseleave", () => {
        clearTimeout(peekTimer);
        sb.classList.remove("is-peek");
    });

    // Keyboard: "[" toggles, Esc closes a peek
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") sb.classList.remove("is-peek");
        if (e.key !== "[" || e.ctrlKey || e.metaKey || e.altKey || !desktop.matches) return;
        const el = document.activeElement;
        if (el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName))) return;
        e.preventDefault();
        window.toggleSidebarRail();
    });

    // Sync button state with whatever the inline script restored, then re-enable animations
    setCollapsed(sb.classList.contains("is-collapsed"));
    requestAnimationFrame(() => requestAnimationFrame(() => sb.classList.remove("rail-no-anim")));

    if (window.lucide) lucide.createIcons();
})();
