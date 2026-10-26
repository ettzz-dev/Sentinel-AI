<!-- tabs/cloudflare.php -->
<div class="fade-in space-y-4">
  <!-- Metric Header Configuration Cards -->
  <div class="flex items-center justify-between mb-4">
 
</div>

  <div id="cfStatCards" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="bg-card p-4 border border-border rounded-xl flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center"><i data-lucide="cloud" class="w-5 h-5 text-accent"></i></div>
          <div><div class="text-xs text-gray-500">Total Tunnels</div><div id="cf-total-val" class="text-lg font-bold text-white">0</div></div>
      </div>
      <div class="bg-card p-4 border border-border rounded-xl flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-success/10 flex items-center justify-center"><i data-lucide="check-circle" class="w-5 h-5 text-success"></i></div>
          <div><div class="text-xs text-gray-500">Healthy</div><div id="cf-healthy-val" class="text-lg font-bold text-emerald-400">0</div></div>
      </div>
      <div class="bg-card p-4 border border-border rounded-xl flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-danger/10 flex items-center justify-center"><i data-lucide="alert-triangle" class="w-5 h-5 text-danger"></i></div>
          <div><div class="text-xs text-gray-500">Down</div><div id="cf-down-val" class="text-lg font-bold text-rose-500">0</div></div>
      </div>
  </div>
 <!-- Manual Refresh Button -->
  <button 
    id="refreshTunnelsBtn" 
    onclick="manualTunnelRefresh()" 
    class="flex items-baseline gap-1.5 px-2.5 py-1.5 text-xs bg-card hover:bg-surface border border-border hover:border-accent/40 text-gray-300 hover:text-white rounded-lg transition"
  >
    <i data-lucide="refresh-cw" id="refreshIcon" class="w-3.5 h-3.5 text-accent"></i>
    <span>Refresh</span>
  </button>
  <!-- Zero Trust Tunnels Stream Area -->
  <div class="bg-card border border-border rounded-xl p-4 md:p-5 glow">
    <h3 class="text-sm font-semibold text-white mb-3">Zero Trust Tunnels</h3>
    <div class="space-y-3" id="tunnelsListContainer">
       <div class="text-xs text-gray-500 text-center py-4">Synchronizing active tunnels...</div>
    </div>
  </div>

</div>