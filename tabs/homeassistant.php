<div class="fade-in space-y-6">
  <p class="text-xs text-gray-500 uppercase tracking-widest font-semibold mb-2">Scenes</p>
  
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-rose-600/90 border border-rose-500 hover:bg-rose-600 p-5 rounded-2xl flex flex-col items-center justify-center cursor-pointer shadow-lg transition transform hover:-translate-y-0.5">
      <i data-lucide="lock" class="w-6 h-6 text-white mb-2"></i>
      <span class="text-sm font-bold text-white">Leaving Home</span>
      <span class="text-[10px] text-rose-200 mt-1">Lock & Lights Off</span>
    </div>
    
    <div class="bg-indigo-600/90 border border-indigo-500 hover:bg-indigo-600 p-5 rounded-2xl flex flex-col items-center justify-center cursor-pointer shadow-lg transition transform hover:-translate-y-0.5">
      <i data-lucide="moon" class="w-6 h-6 text-white mb-2"></i>
      <span class="text-sm font-bold text-white">Good Night</span>
      <span class="text-[10px] text-indigo-200 mt-1">All Off & Locked</span>
    </div>

    <div class="bg-emerald-600/90 border border-emerald-500 hover:bg-emerald-600 p-5 rounded-2xl flex flex-col items-center justify-center cursor-pointer shadow-lg transition transform hover:-translate-y-0.5">
      <i data-lucide="home" class="w-6 h-6 text-white mb-2"></i>
      <span class="text-sm font-bold text-white">Welcome Home</span>
      <span class="text-[10px] text-emerald-200 mt-1">Lights & AC On</span>
    </div>

    <div class="bg-purple-600/90 border border-purple-500 hover:bg-purple-600 p-5 rounded-2xl flex flex-col items-center justify-center cursor-pointer shadow-lg transition transform hover:-translate-y-0.5">
      <i data-lucide="tv" class="w-6 h-6 text-white mb-2"></i>
      <span class="text-sm font-bold text-white">Movie Mode</span>
      <span class="text-[10px] text-purple-200 mt-1">Lights Dim & Quiet</span>
    </div>
  </div>

  <div class="bg-card border border-border rounded-xl p-4 md:p-5 glow mt-6">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-sm font-semibold text-white">Current Power Draw</h3>
      <span class="text-[10px] uppercase font-bold text-emerald-400 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>Live</span>
    </div>
    
    <div class="flex items-center gap-6">
      <div class="relative w-24 h-24 flex items-center justify-center border-4 border-accent2/30 rounded-full">
         <div class="text-center">
            <span class="text-xl font-bold text-white block">2.4</span>
            <span class="text-[10px] text-gray-500 uppercase tracking-wider">kW</span>
         </div>
      </div>
      <div class="flex-1 space-y-3 text-xs">
         <div>
           <div class="flex justify-between mb-1 text-gray-400"><span>AC Unit</span><span class="mono text-white">1.2 kW</span></div>
           <div class="w-full bg-surface h-2 rounded-full overflow-hidden"><div class="bg-cyan-400 h-full rounded-full" style="width: 50%"></div></div>
         </div>
         <div>
           <div class="flex justify-between mb-1 text-gray-400"><span>Refrigerator</span><span class="mono text-white">0.6 kW</span></div>
           <div class="w-full bg-surface h-2 rounded-full overflow-hidden"><div class="bg-purple-400 h-full rounded-full" style="width: 25%"></div></div>
         </div>
      </div>
    </div>
  </div>
   <!-- Smart Device Activity Stream -->
      <div>
        <h3 class="text-sm font-semibold text-white mb-3">Activity Stream</h3>
        <div id="activityStream" class="space-y-2 max-h-64 overflow-y-auto">
          <div class="flex items-start gap-3 p-2.5 bg-surface rounded-lg border border-border/50">
            <i data-lucide="lock" class="w-4 h-4 text-success flex-shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-white font-medium">Front Door</div>
              <div class="text-xs text-gray-500">Locked • 5 mins ago</div>
            </div>
          </div>
          </div>
          <div class="flex items-start gap-3 p-2.5 bg-surface rounded-lg border border-border/50">
            <i data-lucide="motion" class="w-4 h-4 text-warn flex-shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-white font-medium">Motion Detected</div>
              <div class="text-xs text-gray-500">Garage • 12 mins ago</div>
            </div>
          </div>
          <div class="flex items-start gap-3 p-2.5 bg-surface rounded-lg border border-border/50">
            <i data-lucide="lightbulb" class="w-4 h-4 text-accent flex-shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-white font-medium">Living Room Lights</div>
              <div class="text-xs text-gray-500">Turned On • 18 mins ago</div>
            </div>
          </div>
          <div class="flex items-start gap-3 p-2.5 bg-surface rounded-lg border border-border/50">
            <i data-lucide="thermometer" class="w-4 h-4 text-warn flex-shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-white font-medium">Temperature Alert</div>
              <div class="text-xs text-gray-500">Bedroom 28°C • 25 mins ago</div>
            </div>
          </div>
          <div class="flex items-start gap-3 p-2.5 bg-surface rounded-lg border border-border/50">
            <i data-lucide="unlock" class="w-4 h-4 text-success flex-shrink-0 mt-0.5"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-white font-medium">Garage Door</div>
              <div class="text-xs text-gray-500">Opened • 32 mins ago</div>
            </div>
          </div>
        </div>
    

      <!-- Gateway Management -->
   <div>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center">
              <i data-lucide="router" class="w-5 h-5 text-accent"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-white">Home Router</h3>
              <p class="text-xs text-gray-500">192.168.1.1</p>
            </div>
          </div>
          ${statusBadge('online')}
        </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="p-3 bg-surface rounded-lg border border-border/50">
            <div class="text-xs text-gray-500 mb-1">Connected Devices</div>
            <div class="text-lg font-bold text-white">12</div>
          </div>
          <div class="p-3 bg-surface rounded-lg border border-border/50">
            <div class="text-xs text-gray-500 mb-1">WiFi Strength</div>
            <div class="text-lg font-bold text-success">Strong</div>
          </div>
          <div class="p-3 bg-surface rounded-lg border border-border/50">
            <div class="text-xs text-gray-500 mb-1">Uptime</div>
            <div class="text-sm font-bold text-white">45d 2h</div>
          </div>
          <div class="p-3 bg-surface rounded-lg border border-border/50">
            <div class="text-xs text-gray-500 mb-1">Bandwidth</div>
            <div class="text-lg font-bold text-accent">45 Mbps</div>
          </div>
        </div>
        <div class="flex gap-2">
          <button onclick="gatewayAction('restart')" class="flex-1 px-3 py-2 bg-warn/10 text-warn rounded-lg text-xs font-medium hover:bg-warn/20 transition">Restart</button>
          <button onclick="gatewayAction('reboot')" class="flex-1 px-3 py-2 bg-danger/10 text-danger rounded-lg text-xs font-medium hover:bg-danger/20 transition">Reboot</button>
          <button onclick="gatewayAction('settings')" class="flex-1 px-3 py-2 bg-accent/10 text-accent rounded-lg text-xs font-medium hover:bg-accent/20 transition">Settings</button>
        </div>
   
    </div>
  
</div>
