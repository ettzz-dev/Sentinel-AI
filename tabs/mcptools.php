tabs/mcptools.php

<h3 class="text-sm font-semibold text-white mb-4">MCP Server Control</h3>
        <div class="flex items-center gap-2 mb-4 p-3 bg-surface rounded-lg border border-accent/30">
          <div class="w-2 h-2 rounded-full bg-success pulse-dot"></div>
          <span class="text-xs text-gray-400">MCP Server Connected</span>
          <span class="text-xs text-accent ml-auto">Ready</span>
        </div>
        <div class="space-y-3">
          <div>
            <label class="text-xs text-gray-500 mb-2 block">MCP Tools</label>
            <select id="mcpToolSelect" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-accent focus:outline-none">
              <option value="">Select a tool...</option>
              <option value="media_all_off">📺 Turn Off All Media</option>
              <option value="lights_all_off">💡 All Lights Off</option>
              <option value="lights_all_on">💡 All Lights On</option>
              <option value="garage_check">🚪 Check Garage Door</option>
              <option value="ac_temp_set">❄️ Set AC Temperature</option>
              <option value="door_lock_all">🔒 Lock All Doors</option>
              <option value="door_unlock_all">🔓 Unlock All Doors</option>
              <option value="scene_movie">🎬 Movie Scene</option>
              <option value="scene_bedtime">🛏️ Bedtime Scene</option>
              <option value="scene_work">💼 Work Mode</option>
              <option value="media_pause_all">⏸️ Pause All Media</option>
              <option value="speaker_mute_all">🔇 Mute All Speakers</option>
            </select>
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-2 block">Parameters (optional)</label>
            <input id="mcpParams" type="text" placeholder="e.g., room:living_room, temp:22" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-xs text-white mono placeholder-gray-600 focus:border-accent focus:outline-none">
          </div>
          <button onclick="executeMCPTool()" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:bg-accent/80 transition flex items-center justify-center gap-2">
            <i data-lucide="zap" class="w-4 h-4"></i> Execute Tool
          </button>
        </div>

      <!-- MCP Command History -->
        <h3 class="text-sm font-semibold text-white mb-3">Quick Commands</h3>
        <div class="space-y-2 mb-4">
          <button onclick="sendMCPCommand('Heading out, turn off all media and check if garage is closed')" class="w-full text-left p-3 bg-surface rounded-lg border border-border/50 hover:border-accent/50 transition hover:bg-surface/80">
            <div class="text-sm text-white font-medium">🚗 Leaving Home Protocol</div>
            <div class="text-xs text-gray-500 mt-1">Media off, garage check, doors locked</div>
          </button>
          <button onclick="sendMCPCommand('I am going to bed, lock everything and set bedtime mode')" class="w-full text-left p-3 bg-surface rounded-lg border border-border/50 hover:border-accent/50 transition hover:bg-surface/80">
            <div class="text-sm text-white font-medium">🛏️ Bedtime Routine</div>
            <div class="text-xs text-gray-500 mt-1">Doors locked, lights off, AC to night mode</div>
          </button>
          <button onclick="sendMCPCommand('I am arriving home, turn on welcome lights and check all systems')" class="w-full text-left p-3 bg-surface rounded-lg border border-border/50 hover:border-accent/50 transition hover:bg-surface/80">
            <div class="text-sm text-white font-medium">🏠 Welcome Home</div>
            <div class="text-xs text-gray-500 mt-1">Lights on, AC on, system check</div>
          </button>
          <button onclick="sendMCPCommand('Start movie time with dim lights and pause all other media')" class="w-full text-left p-3 bg-surface rounded-lg border border-border/50 hover:border-accent/50 transition hover:bg-surface/80">
            <div class="text-sm text-white font-medium">🎬 Movie Time</div>
            <div class="text-xs text-gray-500 mt-1">Lights dimmed, media paused, ready for viewing</div>
          </button>
        </div>
        <h3 class="text-sm font-semibold text-white mb-3">Recent Commands</h3>
        <div id="mcpHistory" class="space-y-2 max-h-48 overflow-y-auto">
          <div class="p-2 bg-surface rounded-lg border border-border/50">
            <div class="flex justify-between items-start">
              <span class="text-xs text-white">Executed: <strong>Turn Off All Media</strong></span>
              <span class="px-2 py-0.5 rounded-full text-xs bg-success/20 text-success">✓</span>
            </div>
            <div class="text-xs text-gray-600 mt-1">2 mins ago</div>
          </div>
        </div>

      <!-- MCP Server Info -->   
        <h3 class="text-sm font-semibold text-white mb-3">Server Information</h3>
        <div class="space-y-2 text-xs">
          <div class="flex justify-between p-2 bg-surface rounded-lg">
            <span class="text-gray-400">Status</span>
            <span class="text-success font-medium">Connected</span>
          </div>
          <div class="flex justify-between p-2 bg-surface rounded-lg">
            <span class="text-gray-400">Latency</span>
            <span class="text-accent font-medium">24ms</span>
          </div>
          <div class="flex justify-between p-2 bg-surface rounded-lg">
            <span class="text-gray-400">Tools Available</span>
            <span class="text-accent font-medium">32</span>
          </div>
          <div class="flex justify-between p-2 bg-surface rounded-lg">
            <span class="text-gray-400">Last Sync</span>
            <span class="text-gray-500 font-medium mono">5s ago</span>
          </div>
        </div>
  