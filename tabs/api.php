<div class="fade-in space-y-4">
<h3 class="text-sm font-semibold text-white mb-3">API Configuration</h3>
        <div class="space-y-3">
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Cloudflare API Token</label>
            <div class="flex gap-2">
              <input id="cfApiToken" type="password" placeholder="Enter your Cloudflare API token" class="flex-1 bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white mono placeholder-gray-600 focus:border-accent focus:outline-none">
              <button onclick="toggleCfTokenVisibility()" class="px-3 py-2 bg-border text-gray-400 rounded-lg text-sm hover:bg-border/80 transition"><i data-lucide="eye" class="w-4 h-4"></i></button>
            </div>
            <p class="text-xs text-gray-500 mt-2">Your token is stored locally in your browser and used only for API requests from this panel.</p>
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Account ID (optional)</label>
            <input id="cfAccountId" type="text" placeholder="Your Cloudflare Account ID" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white mono placeholder-gray-600 focus:border-accent focus:outline-none">
          </div>
          <button onclick="testCfConnection()" class="w-full px-4 py-2 bg-accent/10 text-accent rounded-lg text-sm hover:bg-accent/20 transition">Test Connection</button>
        </div>
      
<div class="fade-in space-y-4">
        <h3 class="text-sm font-semibold text-white mb-4">API Request Builder</h3>
        <form id="apiForm" onsubmit="handleApi(event)" class="space-y-3">
          <div class="flex gap-2">
            <select id="apiMethod" class="bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-accent focus:outline-none border border-gray-300 rounded-md p-2">
              <option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
            </select>
            <input id="apiUrl" type="text" placeholder="https://api.example.com/endpoint" class="flex-1 bg-surface border border-border rounded-lg px-3 py-2 text-sm text-white mono placeholder-gray-600 focus:border-accent focus:outline-none">
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Headers (JSON)</label>
            <textarea id="apiHeaders" rows="2" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-xs text-white mono placeholder-gray-600 focus:border-accent focus:outline-none" placeholder='{"Content-Type": "application/json"}'></textarea>
          </div>
          <div>
            <label class="text-xs text-gray-500 mb-1 block">Body (JSON)</label>
            <textarea id="apiBody" rows="4" class="w-full bg-surface border border-border rounded-lg px-3 py-2 text-xs text-white mono placeholder-gray-600 focus:border-accent focus:outline-none" placeholder='{"key": "value"}'></textarea>
          </div>
          <div class="flex gap-2">
            <select id="apiTarget" class="bg-surface border border-border rounded-lg px-3 py-2 text-xs text-gray-400 focus:border-accent focus:outline-none">
              <option value="">Quick Targets</option>
              <option value="http://10.0.1.1">routerPi</option>
              <option value="http://10.0.1.2">autoPi</option>
              <option value="http://10.0.1.3">volumePi</option>
              <option value="http://10.0.1.4">homePi</option>
              <option value="https://gps.ettzz.dev">traccar</option>
            </select>
            <button type="submit" class="px-6 py-2 bg-accent text-white rounded-lg text-sm font-medium hover:bg-accent/80 transition flex items-center gap-2">
              <i data-lucide="send" class="w-4 h-4"></i> Send
            </button>
          </div>
        </form>

  <div class="fade-in space-y-4">
        <h3 class="text-sm font-semibold text-white mb-2">Response</h3>
        <div id="apiResponse" class="bg-surface rounded-lg p-3 mono text-xs text-gray-400 min-h-[100px] max-h-60 overflow-auto">Waiting for request...</div>
      
    </div>