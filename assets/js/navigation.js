// /assets/js/navigation.js

document.addEventListener("DOMContentLoaded", () => {
    // 1. Setup automatic menu handling if your navigation targets are standard links
    const navLinks = document.querySelectorAll("#navList a, .sidebar-link");
    
    // Set default initial view panel active state (e.g. your Sentinel AI tab panel)
    switchTab("panel-api"); 

    window.switchTab = function(targetPanelId) {
        // Find all panel sections inside the view wrapper
        const panels = document.querySelectorAll(".tab-panel");
        
        panels.forEach(panel => {
            if (panel.id === targetPanelId) {
                panel.classList.remove("hidden");
                // Update your layout top navigation bar title layout dynamically
                const panelTitle = panel.getAttribute('data-title') || "Dashboard";
                const pageHeaderTitle = document.getElementById("pageTitle");
                if(pageHeaderTitle) pageHeaderTitle.textContent = panelTitle;
            } else {
                panel.classList.add("hidden");
            }
        });

        // Trigger Lucide graphics refreshing framework layout engine elements
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
});