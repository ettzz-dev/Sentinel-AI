<!DOCTYPE html>
<html lang="en" class="h-full">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[ettzz] Network Manager</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"> </script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.577.0/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
    <script src="/assets/js/tailwind.js"> </script>    
    <link href="/assets/css/styles.css" rel="stylesheet">
    <link href="/assets/css/sidebar-rail.css" rel="stylesheet">
 </head>
 	
 <body class="h-full bg-surface text-gray-200 overflow-hidden">
  <div id="app" class="h-full w-full flex">
  	
   <!-- Hardened Cyberpunk Left Sidebar Nav Panel -->
   	<nav id="sidebar" class="fixed lg:static left-0 top-0 w-56 h-full chat-history-glass flex flex-col shrink-0 z-40 transform -translate-x-full lg:transform-none transition-transform duration-300 ease-out select-none">
        
        <!-- Top Core Branding Array -->
        <div class="bg-black/30 border-b border-cyan-500/20">
          <div class="brand-header brand-header-hover group cursor-pointer transition-colors duration-200 hover:bg-cyan-500/5">
            <div class="p-4 flex items-center justify-between">
              
              <div class="flex items-center gap-3">
                <svg class="cyber-svg-glow shrink-0 transition-all duration-200 group-hover:brightness-125" width="28" height="26" viewBox="0 0 30 28" fill="none" xmlns="http://w3.org">
                  <path d="M4 5L13 14L4 23" stroke="#00e5ff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity="0.25" />
                  <path d="M13 5L22 14L13 23" stroke="#00e5ff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>

                <h1 class="rail-hide flex flex-col gap-[2px] font-['Oxanium'] uppercase leading-none">
                  <span class="glitch-target font-extrabold text-[18px] text-[#00e5ff] cyber-glow-cyan tracking-wide">Network</span>
                  <span class="font-medium text-[9px] text-[#8b5cf6] glow-purple tracking-[0.38em] mr-[-0.38em]">Manager</span>
                </h1>
              </div>
              
              <button type="button" onclick="closeMobileNav()" onmouseenter="CyberAudio.playHover()" class="lg:hidden p-1 border border-transparent hover:border-rose-500/30 hover:bg-rose-500/10 text-gray-400 hover:text-rose-400 transition-all">
                <i data-lucide="x" class="w-4 h-4"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Scrollable Module Listing Array Layout -->
        <div class="flex-1 overflow-y-auto py-3 bg-black/5 bg-scanlines space-y-1.5 custom-scrollbar" id="navList"></div>
        
        <!-- Bottom Diagnostic Grid Indicator -->
        <div class="rail-footer p-3 bg-black/40 border-t border-cyan-500/20 flex items-center justify-between gap-2">
          <div class="flex items-center gap-2 text-[10px] mono tracking-wider text-gray-500">
            <div class="w-1.5 h-1.5 rounded-none bg-success pulse-dot shrink-0 shadow-[0_0_8px_#34d399]"></div>
            <span class="rail-hide whitespace-nowrap uppercase">// SYS.READY</span>
          </div>
          <button id="railToggleBtn" type="button" aria-label="Toggle sidebar" onmouseenter="CyberAudio.playHover()" class="hidden lg:flex items-center justify-center w-6 h-6 border border-border/40 text-gray-500 hover:text-[#00e5ff] hover:border-[#00e5ff]/50 hover:bg-cyan-500/10 transition-all">
            <i data-lucide="chevrons-left" class="w-3.5 h-3.5"></i>
          </button>
        </div>
   </nav>
   <!-- Apply saved collapsed state before first paint (no flash) -->
   <script>try{if(localStorage.getItem('nm.sidebar')==='collapsed')document.getElementById('sidebar').classList.add('is-collapsed','rail-no-anim')}catch(e){}</script>
   
   <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden" onclick="closeMobileNav()"> </div>

   <!-- Content Delivery Area Engine -->
   <main class="flex-1 flex flex-col h-full overflow-hidden min-w-0">
    <header class="h-12 bg-card border-b border-border flex items-center px-4 justify-between shrink-0">
      <div class="flex items-center gap-2">
        <button id="mobileMenuBtn" type="button" class="lg:hidden p-1.5 hover:bg-border rounded text-gray-300 hover:text-white transition" onclick="toggleMobileNav()">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <h1 id="pageTitle" class="text-sm font-semibold text-white"></h1>
      </div>

      <div class="flex items-center gap-3">
        <button id="chatHistoryToggleBtn" type="button" onclick="toggleChatHistory()" class="flex items-center gap-1.5 px-2.5 py-1 text-xs rounded-lg text-gray-400 hover:text-white hover:bg-surface border border-border/50 transition" title="Chat History">
          <i data-lucide="history" class="w-4 h-4 text-cyan-400"></i>
          <span class="hidden sm:inline">History</span>
        </button>

        <span id="clockDisplay" class="mono text-xs text-gray-400"></span>
      </div>
    </header>

    <!-- Content Target Container -->
    <div id="content" class="flex-1 overflow-y-auto p-4 md:p-6"></div>
   </main>
   
  <!-- Pimped Cyberpunk Chat History Sidebar -->
<div id="chatHistorySidebar" class="fixed right-0 top-0 w-72 h-full chat-history-glass flex flex-col shrink-0 z-40 transform translate-x-full transition-transform duration-300 ease-out font-['JetBrains_Mono'] uppercase" data-open="false">
   
   <!-- Header Area with Laser Indicator -->
   <div class="p-4 border-b border-cyan-500/20 flex items-center justify-between select-none bg-black/40">
     <div class="flex items-center gap-2">
       <div class="w-1.5 h-3 bg-[#ff007f] cyber-glow-pink animate-pulse"></div>
       <h2 class="text-xs font-bold text-[#00e5ff] cyber-glow-cyan tracking-widest">ARCHIVE // LOGS</h2>
     </div>
     <button type="button" onclick="closeChatHistory()" class="p-1 border border-transparent hover:border-cyan-500/30 hover:bg-cyan-500/10 text-gray-400 hover:text-[#00e5ff] transition duration-200">
       <i data-lucide="x" class="w-4 h-4"></i>
     </button>
   </div>
   
   <!-- Content List with Custom Violet Scrollbar -->
   <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scrollbar bg-slate-950/20" id="chatHistoryList"></div>
   
   <!-- Footer Custom Action Layer -->
   <div class="p-3 border-t border-purple-500/20 bg-black/40">
     <button onclick="startNewChat()" class="w-full py-2.5 bg-cyan-500/5 border border-cyan-500/30 text-xs text-[#00e5ff] font-bold tracking-widest hover:bg-cyan-500/20 hover:border-[#00e5ff] hover:shadow-[0_0_15px_rgba(0,229,255,0.2)] transition-all duration-200 flex items-center justify-center gap-2">
       <i data-lucide="plus" class="w-4 h-4 text-[#ff007f] cyber-glow-pink"></i> INITIALIZE_NEW_SESSION
     </button>
   </div>
</div>

   
   <div id="chatHistoryOverlay" class="fixed inset-0 bg-black/60 z-30 hidden" onclick="closeChatHistory()"> </div>

  </div>
  
 <!-- Consolidated Tactical Script Pipelines -->
  <script src="/src/app.js"></script>
  <script src="/src/navbar.js"></script>
  <script src="/src/sidebar-rail.js" defer></script>
  <link rel="stylesheet" href="/assets/css/sentinel-touch.css">
  <script src="/src/sentinel-touch.js" defer></script>
 </body>
</html>
