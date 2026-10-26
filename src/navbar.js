// ── CYBERPUNK NAVIGATION GRID MODULE ──

// Core Tab Infrastructure Configurations
const tabs = [
  { id: 'ai', label: 'Sentinel AI', icon: 'bot' },
  { id: 'cloudflare', label: 'Cloudflare', icon: 'cloud' },
  { id: 'homeassistant', label: 'Home Assistant', icon: 'home' },
  { id: 'traccar', label: 'Live-Vehicle Tracking', icon: 'map-pin' },
  { id: 'mcptools', label: 'MCP Toolbox', icon: 'zap' },
  { id: 'terminal', label: 'Terminal', icon: 'terminal' },
  { id: 'api', label: 'API Tester', icon: 'send' },
  { id: 'logs', label: 'Logs', icon: 'scroll-text' }
];

// Telemetry Synced Sidebar Menu Renderer (Hardened Pipeline Fix)
function buildNav() {
  const list = document.getElementById('navList');
  if (!list) return;

  list.innerHTML = tabs
    .map((t, index) => `
    <button data-tab="${t.id}" 
            onmouseenter="CyberAudio.playHover()" 
            onclick="switchTab('${t.id}')" 
            class="nav-item w-full flex flex-col px-4 py-2.5 text-left relative overflow-hidden group border-b border-border/5 ${
              currentTab === t.id ? 'active' : ''
            }">
      
      <!-- Cyan Active Boundary Border Rail -->
      <div class="active-rail-indicator absolute left-0 top-0 h-full w-[2px] transition-all duration-200"></div>
      
      <div class="flex items-center gap-3 w-full min-w-0">
        <div class="nav-icon w-4 h-4 flex items-center justify-center shrink-0">
          <i data-lucide="${t.icon}" class="w-4 h-4 text-gray-400 group-hover:text-cyan-400 transition-colors"></i>
        </div>
        <span class="rail-hide font-['Oxanium'] font-bold uppercase text-[11px] tracking-wide text-gray-400 group-hover:text-gray-200 transition-colors truncate block" title="${t.label}">
          ${t.label}
        </span>
      </div>
      
      <div class="rail-hide flex items-center justify-between text-[8px] mono text-gray-600 mt-1 select-none opacity-40 group-hover:opacity-100 transition-opacity w-full">
        <span>// NODE_${String(index + 1).padStart(2, '0')}</span>
        <span class="text-purple-500 font-bold group-hover:text-purple-400 truncate max-w-[100px]">SYS_ACC.${t.id.toUpperCase()}</span>
      </div>
    </button>
  `).join('');
}

// ── MOBILE DRAWER (< 1024px) ──
// These were lost when the nav was split out of app.js. Without them the
// hamburger did nothing and switchTab() threw on mobile, so no tab content loaded.
window.openMobileNav = function () {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  if (!sb) return;
  sb.classList.remove('-translate-x-full');
  sb.classList.add('translate-x-0');
  if (ov) ov.classList.remove('hidden');
};

window.closeMobileNav = function () {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  if (!sb) return;
  sb.classList.remove('translate-x-0');
  sb.classList.add('-translate-x-full');
  if (ov) ov.classList.add('hidden');
};

window.toggleMobileNav = function () {
  const sb = document.getElementById('sidebar');
  if (!sb) return;
  if (sb.classList.contains('-translate-x-full')) window.openMobileNav();
  else window.closeMobileNav();
};

// Reset the drawer if the screen grows to desktop size while it's open
window.addEventListener('resize', () => {
  if (window.innerWidth >= 1024) window.closeMobileNav();
});

// Unified Page Module Switcher
async function switchTab(id) {
  currentTab = id;
  const tab = tabs.find(t => t.id === id);
  if (tab && document.getElementById('pageTitle')) {
    document.getElementById('pageTitle').textContent = tab.label;
  }

  if (window.innerWidth < 1024 && typeof window.closeMobileNav === 'function') {
    window.closeMobileNav();
  }

  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.tab === id);
  });

  const content = document.getElementById('content');
  if (!content) return;

  try {
    const response = await fetch(`tabs/${id}.php?v=${new Date().getTime()}`);
    if (response.ok) {
      content.innerHTML = await response.text();
      if (id === 'ai' && typeof setFreshGreetings === 'function') {
        setFreshGreetings();
      }
    } else {
      content.innerHTML = `<div class="text-gray-500">Tab view [${id}] not found.</div>`;
    }
  } catch (error) {
    content.innerHTML = `<div class="text-danger">Error loading component context.</div>`;
  }

  // GLOBAL FALLBACK: Run the icon generator only AFTER all dynamic text fields have loaded
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}

// Boot is handled by bootConsoleSystem() in app.js (buildNav + switchTab('ai')).
// A second DOMContentLoaded handler here made the AI tab load twice.
