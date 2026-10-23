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
 </head>
 	
 <body class="h-full bg-surface text-gray-200 overflow-hidden">
  <div id="app" class="h-full w-full flex">
  	
  	<!-- Sidebar Nav Panel -->
   	<nav id="sidebar" class="fixed lg:static left-0 top-0 w-56 h-full bg-card border-r border-border flex flex-col shrink-0 z-40 transform -translate-x-full lg:transform-none transition-transform duration-300 ease-out">
        <!-- Logo Container -->
        <div>
          <div class="preview-card">
            <div class="brand-header brand-header-hover group cursor-pointer transition-colors duration-200 hover:bg-slate-800/30">
              <div class="p-4 border-b border-border flex items-center justify-between select-none">
                
                <div class="flex items-center gap-3">
                  <svg class="cyber-svg-glow transition-all duration-200 group-hover:brightness-125" width="30" height="28" viewBox="0 0 30 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 5L13 14L4 23" stroke="#00e5ff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity="0.25" />
                    <path d="M13 5L22 14L13 23" stroke="#00e5ff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>

                  <h1 class="flex flex-col gap-[1px] font-['Oxanium'] uppercase leading-none">
                    <span class="glitch-target font-extrabold text-[20px] text-[#00e5ff] cyber-glow-cyan tracking-wide">Network</span>
                    <span class="font-medium text-[10px] text-[#ff007f] cyber-glow-pink tracking-[0.38em] mr-[-0.38em]">Manager</span>
                  </h1>
                </div>
                
                <button type="button" onclick="closeMobileNav()" class="lg:hidden p-1 text-gray-400 hover:text-white rounded hover:bg-border transition">
                  <i data-lucide="x" class="w-4 h-4"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto py-2" id="navList"></div>
        <div class="p-3 border-t border-border">
          <div class="flex items-center gap-2 text-xs text-gray-500">
            <div class="w-2 h-2 rounded-full bg-success pulse-dot"></div>
            <span>System Online</span>
          </div>
        </div>
   </nav>
   
   <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden" onclick="closeMobileNav()"> </div>

   <!-- Content Delivery Area Engine -->
   <main class="flex-1 flex flex-col h-full overflow-hidden">
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
   
   <div id="chatHistorySidebar" class="fixed right-0 top-0 w-64 h-full bg-card border-l border-border flex flex-col shrink-0 z-40 transform translate-x-full transition-transform duration-300 ease-out" data-open="false">
      <div class="p-4 border-b border-border flex items-center justify-between">
        <h2 class="text-xs font-semibold text-white uppercase tracking-wider">Chat History</h2>
        <button type="button" onclick="closeChatHistory()" class="p-1 hover:bg-border rounded text-gray-400 hover:text-white transition">
          <i data-lucide="x" class="w-4 h-4"></i>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto p-3 space-y-2" id="chatHistoryList"></div>
      <div class="p-3 border-t border-border">
        <button onclick="startNewChat()" class="w-full py-2 bg-accent/20 border border-accent/40 rounded-xl text-xs text-accent font-medium hover:bg-accent/30 transition flex items-center justify-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> New Conversation
        </button>
      </div>
   </div>

   
   <div id="chatHistoryOverlay" class="fixed inset-0 bg-black/60 z-30 hidden" onclick="closeChatHistory()"> </div>

  </div>
  
  <script src="/src/app.js"> </script>
  <link rel="stylesheet" href="/src/sentinel-touch.css">
<script src="/src/sentinel-touch.js" defer></script>
 </body>
</html>
