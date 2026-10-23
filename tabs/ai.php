<div class="fade-in h-full w-full flex flex-col justify-between text-slate-200 antialiased font-sans selection:bg-purple-500/30 selection:text-purple-200 bg-grid-pattern relative">
    <!-- Main Chat Terminal Wrapper (Enhanced Cyberpunk Framing) -->
    <div class="bg-slate-950/70 backdrop-blur-xl border-2 border-slate-800 rounded-none p-5 shadow-[0_0_30px_rgba(139,92,246,0.05),inset_0_0_20px_rgba(0,0,0,0.8)] flex flex-col flex-1 min-h-[450px] mb-4 relative overflow-hidden group/card transition-all duration-300">
        <!-- Sharp Matrix Corner Crosshairs (Replacing smooth curves with industrial styling) -->
        <div class="absolute top-0 left-0 w-3 h-3 border-t-2 border-l-2 border-cyan-400"></div>
        <div class="absolute top-0 right-0 w-3 h-3 border-t-2 border-r-2 border-cyan-400"></div>
        <div class="absolute bottom-0 left-0 w-3 h-3 border-b-2 border-l-2 border-purple-500"></div>
        <div class="absolute bottom-0 right-0 w-3 h-3 border-b-2 border-r-2 border-purple-500"></div>

        <!-- Background Matrix Scanline Simulation Layer -->
        <div class="absolute inset-0 pointer-events-none bg-scanlines opacity-10 mix-blend-overlay z-50"></div>

        <!-- Status Header (Reorganized with dynamic order states) -->
        <div class="relative flex items-center justify-between mb-4 pb-3 border-b-2 border-slate-900 min-h-[20px] gap-4">
            <!-- Left Side Module System Tag (Hidden on mobile, forced first on desktop) -->
            <div class="text-[30%] md:text-[10px] font-mono text-cyan-400 tracking-widest uppercase bg-cyan-950/40 border border-cyan-500/30 px-2 py-0.5 rounded shadow-sm hidden md:block md:order-1 shrink-0">
                SYS.MOD // CHAT_v5.5
            </div>

            <!-- Center Diagnostic Badges (Takes full width on mobile, centers on desktop) -->
            <div class="flex items-center justify-between w-full md:w-auto md:justify-center gap-3 order-first md:order-2 md:mx-auto">
                <p class="text-emerald-400 font-mono tracking-wider font-semibold inline-flex items-center gap-2 md:text-[12px] text-[10px] uppercase bg-emerald-950/40 border border-emerald-500/30 px-3 py-1 rounded shadow-[0_0_15px_rgba(52,211,153,0.15)] shrink-0">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,1)] animate-pulse"></span>
                    LINK: ESTABLISHED
                </p>

                <p class="text-purple-400 font-mono tracking-wider font-semibold inline-flex items-center gap-2 md:text-[12px] text-[10px] uppercase bg-purple-950/40 border border-purple-500/30 px-3 py-1 rounded shadow-[0_0_15px_rgba(168,85,247,0.15)] shrink-0">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-400 shadow-[0_0_8px_rgba(168,85,247,1)] animate-ping"></span>
                    SYNC: ACTIVE
                </p>
            </div>

            <!-- Right Side: Terminal ID (Hidden on mobile, forced last on desktop) -->
            <div class="text-[50%] md:text-[10px] font-mono text-slate-500 hidden md:block tracking-widest uppercase bg-slate-900/60 border border-slate-800 px-2 py-0.5 md:order-3 shrink-0">
                Node // Sentinel_AI
            </div>
        </div>

       <!-- Chat Messages Window -->
<div id="chatMessages" class="flex-1 overflow-y-auto space-y-4 pr-1 custom-scrollbar min-h-[250px] max-h-[480px]">
    <!-- Greeting Message with Holographic Style Sheet Elements -->
    <div class="text-[10px] md:text-[14px] text-slate-300 font-mono tracking-wide flex items-center justify-center text-center rounded-none p-4 bg-slate-950/80 border border-cyan-500/20 shadow-[0_0_20px_rgba(0,229,255,0.03),inset_0_0_12px_rgba(0,229,255,0.05)] max-w-xl mx-auto my-4 relative overflow-hidden mt-[1px]">
     <!-- ✨ Pinned target ID right here for clean script replacement strings -->
        <p id="mainGreeting" class="relative z-10">
        </p>
        <div class="absolute inset-0 bg-gradient-to-r from-cyan-500/5 via-transparent to-purple-500/5 pointer-events-none"></div>
       
    </div>
</div>

    <!-- Quick Actions Panel -->
    <div class="mb-4 quick-action-panel rounded-none p-4 bg-slate-950/80 border-2 border-slate-900 shadow-[0_10px_30px_rgba(0,0,0,0.6)] relative">
        <div class="absolute top-0 right-0 w-2 h-2 border-t border-r border-slate-700"></div>
        <div class="flex items-center justify-between mb-3 px-0.5">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-3 bg-cyan-400 shadow-[0_0_10px_rgba(0,229,255,0.6)]"></span>
                <label for="mainQuickActionSearch" class="text-[11px] font-mono uppercase tracking-widest font-semibold text-cyan-400/90">Core.QuickActions</label>
            </div>
            <span class="text-[10px] font-mono text-cyan-400/70 tracking-wider bg-cyan-950/60 border border-cyan-800/40 px-1.5 py-0.5 rounded">⌘ K</span>
        </div>

        <div class="quick-action-search rounded-none flex items-center gap-2 px-3 py-2.5 mb-2 bg-slate-900/40 border border-slate-800 focus-within:border-cyan-500/50 focus-within:bg-slate-900/80 focus-within:shadow-[0_0_15px_rgba(0,229,255,0.1)] transition-all duration-300">
            <i data-lucide="terminal" class="w-4 h-4 text-cyan-400"></i>
            <input id="mainQuickActionSearch" type="search" aria-label="Search quick actions" placeholder="Input execution command..." oninput="filterMainQuickActions(this.value)" class="flex-1 bg-transparent text-sm font-mono text-slate-200 placeholder-slate-600 focus:outline-none" />
            <kbd class="hidden sm:inline text-[10px] font-mono text-slate-500 border border-slate-800 bg-slate-950 rounded px-2 py-0.5">/</kbd>
        </div>

        <div id="mainQuickActions" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-28 overflow-y-auto pr-1 custom-scrollbar" role="list"></div>
    </div>

    <!-- Chat Input Form -->
    <form id="chatForm" class="flex flex-col gap-2 w-full items-center" onsubmit="handleChat(event)">
        <div id="filePreviewContainer" class="flex flex-wrap gap-2 px-1 text-xs empty:hidden w-full"></div>

        <div class="flex items-end gap-2 p-2 bg-slate-950 border-2 border-slate-800 rounded-none w-full max-w-md md:max-w-2xl lg:max-w-6xl shadow-2xl transition-all duration-300 focus-within:border-cyan-500/60 focus-within:shadow-[0_0_25px_rgba(0,229,255,0.12)]">
            <input id="chatFileInput" type="file" multiple class="hidden" onchange="handleFileSelection(this)" />

            <label for="chatFileInput" class="cursor-pointer bg-slate-900/60 text-slate-500 hover:text-cyan-400 border border-slate-800 hover:border-cyan-500/30 transition-all flex items-center justify-center h-10 w-10 rounded-none shrink-0 mb-[1px]" title="Attach Data Buffer">
                <i data-lucide="paperclip" class="w-4 h-4"></i>
            </label>

            <!-- Text Area (Adjusted row properties for JS auto-sizing compatibility) -->
            <textarea id="chatInput" rows="1" class="flex-1 bg-transparent px-3 text-sm font-mono text-cyan-50 placeholder-slate-700 focus:outline-none resize-none min-h-[24px] max-h-[120px] py-2 tracking-wide leading-relaxed custom-scrollbar" placeholder="SYSTEM.PROMPT // Enter administrative terminal command..."></textarea>

            <button type="submit" class="group relative inline-flex items-center justify-center w-10 h-10 rounded-none bg-cyan-900/40 text-cyan-400 border border-cyan-500/40 transition-all duration-200 hover:bg-cyan-500 hover:text-slate-950 hover:shadow-[0_0_15px_rgba(0,229,255,0.5)] active:scale-95 focus:outline-none shrink-0 mb-[1px]" aria-label="Execute Command Line">
                <i data-lucide="chevron-right" class="w-5 h-5 font-bold group-hover:translate-x-0.5 transition-transform"></i>
            </button>
        </div>
    </form>
</div>