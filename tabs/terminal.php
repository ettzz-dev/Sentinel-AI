<!-- tabs/terminal.php -->
<div class="fade-in">
  <div class="bg-card border border-border rounded-xl p-4 md:p-5 glow">
    
    <div class="flex items-center gap-2 mb-3">
      <div class="flex gap-1.5">
        <div class="w-3 h-3 rounded-full bg-rose-500"></div>
        <div class="w-3 h-3 rounded-full bg-amber-500"></div>
        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
      </div>
      <span class="text-xs text-gray-500 ml-2 mono">netpanel@terminal</span>
    </div>
    
    <div id="terminalOutput" class="mono text-xs space-y-1 mb-2 max-h-80 overflow-y-auto">
      <div class="text-success">NetPanel Terminal v1.0</div>
      <div class="text-gray-500">Type 'help' for available commands.</div>
    </div>
    
    <form onsubmit="handleTerminal(event)" class="flex items-center gap-2">
      <span class="mono text-xs text-accent">$</span>
      <input id="termInput" type="text" class="flex-1 bg-transparent mono text-xs text-white terminal-input focus:outline-none" placeholder="Enter command..." autocomplete="off">
    </form>
    
  </div>
</div>
