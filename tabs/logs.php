<div class="fade-in space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
     <div>
          <h3 class="text-sm font-semibold text-white mb-3">Date Range Filter</h3>
          <div class="space-y-3">
            <div>
              <label class="text-xs text-gray-500 mb-1 block">From</label>
              <input id="logDateFrom" type="date" value="${weekAgo}" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-accent focus:outline-none border-2 border-white-500 rounded-lg p-2">
            </div>
            <div>
              <label class="text-xs text-gray-500 mb-1 block">To</label>
              <input id="logDateTo" type="date" value="${today}" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-accent focus:outline-none border-2 border-grey-500 rounded-lg p-2">
            </div>
            <button onclick="filterLogsByDateRange()" class="w-full px-4 py-2 bg-accent/10 text-accent rounded-lg text-sm hover:bg-accent/20 transition">Apply Filter</button>
            <button onclick="resetLogDateRange()" class="w-full px-4 py-2 bg-border text-gray-400 rounded-lg text-sm hover:bg-border/80 transition">Reset</button>
          </div>
          </div>
       <div>
          <h3 class="text-sm font-semibold text-white mb-3">Log Level</h3>
          <div class="flex flex-wrap gap-2">
            <button onclick="filterLogs('all')" class="px-3 py-1 text-xs rounded-lg bg-accent/10 text-accent hover:bg-accent/20 transition" data-filter="all">All</button>
            <button onclick="filterLogs('info')" class="px-3 py-1 text-xs rounded-lg bg-border text-gray-400 hover:bg-border/80 transition" data-filter="info">Info</button>
            <button onclick="filterLogs('warning')" class="px-3 py-1 text-xs rounded-lg bg-border text-gray-400 hover:bg-border/80 transition" data-filter="warning">Warning</button>
            <button onclick="filterLogs('error')" class="px-3 py-1 text-xs rounded-lg bg-border text-gray-400 hover:bg-border/80 transition" data-filter="error">Error</button>
            <button onclick="clearLogs()" class="ml-auto px-3 py-1 text-xs rounded-lg bg-danger/10 text-danger hover:bg-danger/20 transition">Clear Logs</button>
          </div>
        </div>
      </div>
     <div>
        <div class="flex justify-between items-center mb-3">
          <h3 class="text-sm font-semibold text-white">Logs</h3>
          <span id="logCount" class="text-xs text-gray-500"></span>
        </div>
        <div id="logContainer" class="space-y-1 max-h-96 overflow-y-auto mono text-xs"></div>
      
    </div>
  </div>