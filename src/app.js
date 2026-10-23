// ── State ──
const defaultConfig = {
  panel_title: 'Network Manager',
  bg_color: '#0a0e17',
  card_color: '#111827',
  text_color: '#e5e7eb',
  accent_color: '#06b6d4',
  secondary_color: '#8b5cf6'
}

let logs = []
let currentTab = 'devices'
let chatHistory = []
let currentChatSession = null

let stagedChatFiles = []

const chatGreetings = [
  "System check complete. <span class='text-cyan-400 font-bold glow-cyan'>All ports clear</span>",
  "Online and connected. <span class='text-purple-400 font-semibold glow-purple'>Show me the data</span>",
  "Network status: <span class='text-emerald-400 font-bold glow-emerald animate-pulse'>Optimal</span>. How can I help?",
  "Ping received! <span class='text-cyan-400 font-bold glow-cyan'>Ready for execution</span>",
  "Welcome back, Admin. Let's <span class='text-purple-400 font-bold glow-purple'>optimize the grid</span>",
  "Control panel <span class='text-cyan-400 font-semibold glow-cyan'>Active</span>. Input command parameter...",
  "The grid is secure. <span class='text-purple-400 font-bold glow-purple'>Awaiting mission parameters</span>",
  "System online. <span class='text-emerald-400 font-semibold glow-emerald'>Ask me anything</span>"
]

function getFreshGreeting () {
  return chatGreetings[Math.floor(Math.random() * chatGreetings.length)]
}

function greetingBubble () {
  return `<div class="chat-bubble bg-accent2/10 text-gray-300 p-3 rounded-xl rounded-tl-sm text-sm">${getFreshGreeting()}</div>`
}

// Nav Tabs
const tabs = [
  {
    id: 'ai',
    label: 'Sentinel AI',
    icon: 'bot'
  },
  {
    id: 'cloudflare',
    label: 'Cloudflare',
    icon: 'cloud'
  },
  {
    id: 'homeassistant',
    label: 'Home Assistant',
    icon: 'home'
  },
  {
    id: 'traccar',
    label: 'Live-Vehicle Tracking',
    icon: 'map-pin'
  },
  {
    id: 'mcptools',
    label: 'MCP Toolbox',
    icon: 'zap'
  },
  {
    id: 'terminal',
    label: 'Termimal',
    icon: 'terminal'
  },
  {
    id: 'api',
    label: 'API Tester',
    icon: 'send'
  },
  {
    id: 'logs',
    label: 'Logs',
    icon: 'scroll-text'
  }
]

let tunnels = [
  {
    name: 'AI Tunnel',
    target: 'localhost:5000',
    status: 'healthy'
  },
  {
    name: 'HomeAssistant Tunnel',
    target: 'localhost:8123',
    status: 'healthy'
  },
  {
    name: 'Traccar Tunnel',
    target: 'localhost:8082',
    status: 'degraded'
  }
]

// Helpers
function statusBadge (s) {
  const m = {
    healthy: 'bg-success/20 text-success',
    online: 'bg-success/20 text-success',
    degraded: 'bg-warn/20 text-warn',
    offline: 'bg-danger/20 text-danger',
    active: 'bg-success/20 text-success'
  }
  return `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${
    m[s] || m.active
  }">${s}</span>`
}

function card (content, cls = '') {
  return `<div class="bg-card border border-border rounded-xl p-4 md:p-5 glow ${cls}">${content}</div>`
}

function statCard (icon, label, value, color = 'accent') {
  return card(`
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-${color}/10 flex items-center justify-center"><i data-lucide="${icon}" class="w-5 h-5 text-${color}"></i></div>
      <div><div class="text-xs text-gray-500">${label}</div><div class="text-lg font-bold text-white">${value}</div></div>
    </div>`)
}

function progressBar (pct, color = '#06b6d4') {
  return `<div class="w-full h-1.5 bg-border rounded-full overflow-hidden"><div class="h-full rounded-full" style="width:${pct}%;background:${color};transition:width 0.5s"></div></div>`
}

function addLog (msg, source = 'system', level = 'info') {
  const entry = {
    type: 'log',
    message: msg,
    source,
    level,
    timestamp: new Date().toISOString()
  }
  logs.unshift(entry)
  if (logs.length > 200) logs.pop()
  if (window.dataSdk && logs.length <= 999) {
    window.dataSdk.create(entry)
  }
}

function formatChatDate (dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr.replace(' ', 'T')) // Handles SQL "YYYY-MM-DD HH:MM:SS" format safely
  if (isNaN(d)) return dateStr

  return d
    .toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short'
    })
    .replace(' ', '-')
}

// Log Helpers
function renderLogs (filter) {
  const filtered =
    filter === 'all' ? logs : logs.filter(l => l.level === filter)
  if (!filtered.length)
    return '<div class="text-gray-600 py-4 text-center">No logs yet. Activity will appear here.</div>'
  const colors = {
    info: 'text-accent',
    warning: 'text-warn',
    error: 'text-danger'
  }
  return filtered
    .slice(0, 100)
    .map(l => {
      const d = new Date(l.timestamp)
      const ts = d.toLocaleTimeString()
      return `<div class="flex gap-3 py-1 border-b border-border/30"><span class="text-gray-600 shrink-0">${ts}</span><span class="${
        colors[l.level] || 'text-gray-400'
      } shrink-0 uppercase w-14">[${
        l.level
      }]</span><span class="text-gray-400">${l.message}</span></div>`
    })
    .join('')
}

// Logs
window.filterLogs = function (filter) {
  const c = document.getElementById('logContainer')
  if (c) c.innerHTML = renderLogs(filter)
}

const today = new Date().toISOString().split('T')[0]
const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
  .toISOString()
  .split('T')[0]

window.clearLogs = function () {
  logs = []
  const c = document.getElementById('logContainer')
  if (c) c.innerHTML = renderLogs('all')
}

window.filterLogsByDateRange = function () {
  const fromInput = document.getElementById('logDateFrom')
  const toInput = document.getElementById('logDateTo')
  if (!fromInput || !toInput) return

  const fromDate = new Date(fromInput.value)
  const toDate = new Date(toInput.value)
  toDate.setHours(23, 59, 59, 999) // Include entire end day

  const filtered = logs.filter(l => {
    const logDate = new Date(l.timestamp)
    return logDate >= fromDate && logDate <= toDate
  })

  const c = document.getElementById('logContainer')
  const count = document.getElementById('logCount')
  if (!c) return

  if (filtered.length === 0) {
    c.innerHTML =
      '<div class="text-gray-600 py-4 text-center">No logs in this date range.</div>'
    if (count) count.textContent = '0 logs'
    return
  }

  const colors = {
    info: 'text-accent',
    warning: 'text-warn',
    error: 'text-danger'
  }
  const html = filtered
    .slice(0, 100)
    .map(l => {
      const d = new Date(l.timestamp)
      const ts = d.toLocaleTimeString()
      return `<div class="flex gap-3 py-1 border-b border-border/30"><span class="text-gray-600 shrink-0">${ts}</span><span class="${
        colors[l.level] || 'text-gray-400'
      } shrink-0 uppercase w-14">[${
        l.level
      }]</span><span class="text-gray-400">${l.message}</span></div>`
    })
    .join('')

  c.innerHTML = html
  if (count) count.textContent = `${filtered.length} logs`
}

window.resetLogDateRange = function () {
  const today = new Date().toISOString().split('T')[0]
  const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split('T')[0]

  const fromInput = document.getElementById('logDateFrom')
  const toInput = document.getElementById('logDateTo')
  if (fromInput) fromInput.value = weekAgo
  if (toInput) toInput.value = today

  window.filterLogsByDateRange()
}

// Home Assistant
window.triggerScene = function (scene) {
  const sceneNames = {
    leaving: 'Leaving Home',
    goodnight: 'Good Night',
    welcome: 'Welcome Home',
    movie: 'Movie Mode'
  }

  showToast(`🎬 Scene triggered: ${sceneNames[scene]}`, 'success')
  addLog(`Home Assistant scene: ${sceneNames[scene]}`, 'homeassistant', 'info')

  // Animate power consumption
  const powerValue = document.getElementById('powerValue')
  if (powerValue) {
    const newPower =
      scene === 'leaving'
        ? 0.3
        : scene === 'goodnight'
        ? 0.2
        : scene === 'welcome'
        ? 3.8
        : 0.8
    powerValue.textContent = newPower.toFixed(1)
    const ring = document.getElementById('powerRing')
    if (ring) {
      const percentage = newPower / 5 // Assuming max is 5kW
      ring.style.strokeDasharray = `${percentage * 282.74} 282.74`
    }
  }
}

window.gatewayAction = function (action) {
  const actions = {
    restart: 'Restarting router...',
    reboot: 'Rebooting router...',
    settings: 'Opening router settings...'
  }
  showToast(actions[action], 'info')
  addLog(`Gateway action: ${action}`, 'homeassistant', 'info')
}

// ── MCP Tools ──
window.executeMCPTool = function () {
  const toolSelect = document.getElementById('mcpToolSelect')
  const paramsInput = document.getElementById('mcpParams')
  const tool = toolSelect?.value
  const params = paramsInput?.value || ''

  if (!tool) {
    showToast('Please select a tool', 'error')
    return
  }

  const toolLabels = {
    media_all_off: '📺 Turn Off All Media',
    lights_all_off: '💡 All Lights Off',
    lights_all_on: '💡 All Lights On',
    garage_check: '🚪 Check Garage Door',
    ac_temp_set: '❄️ Set AC Temperature',
    door_lock_all: '🔒 Lock All Doors',
    door_unlock_all: '🔓 Unlock All Doors',
    scene_movie: '🎬 Movie Scene',
    scene_bedtime: '🛏️ Bedtime Scene',
    scene_work: '💼 Work Mode',
    media_pause_all: '⏸️ Pause All Media',
    speaker_mute_all: '🔇 Mute All Speakers'
  }

  showToast(`⚙️ Executing: ${toolLabels[tool]}`, 'info')
  addLog(
    `MCP tool executed: ${toolLabels[tool]} ${
      params ? '(' + params + ')' : ''
    }`,
    'mcptools',
    'info'
  )

  // Add to history
  const history = document.getElementById('mcpHistory')
  if (history) {
    const entry = document.createElement('div')
    entry.className = 'p-2 bg-surface rounded-lg border border-border/50'
    entry.innerHTML = `
      <div class="flex justify-between items-start">
        <span class="text-xs text-white">Executed: <strong>${toolLabels[tool]}</strong></span>
        <span class="px-2 py-0.5 rounded-full text-xs bg-success/20 text-success">✓</span>
      </div>
      <div class="text-xs text-gray-600 mt-1">Just now</div>
    `
    history.insertBefore(entry, history.firstChild)
  }

  // Reset form
  if (toolSelect) toolSelect.value = ''
  if (paramsInput) paramsInput.value = ''
}

window.sendMCPCommand = function (msg) {
  // Add user message to AI chat
  addMessageToUI('user', msg)

  // Send to AI assistant (which handles MCP integration)
  sendMessage(msg)

  // Add to MCP history
  const history = document.getElementById('mcpHistory')
  if (history) {
    const entry = document.createElement('div')
    entry.className = 'p-2 bg-surface rounded-lg border border-border/50'
    entry.innerHTML = `
      <div class="flex justify-between items-start">
        <span class="text-xs text-white">Query: <strong>${msg.substring(
          0,
          50
        )}...</strong></span>
        <span class="px-2 py-0.5 rounded-full text-xs bg-accent/20 text-accent">→</span>
      </div>
      <div class="text-xs text-gray-600 mt-1">Processing...</div>
    `
    history.insertBefore(entry, history.firstChild)
  }
}

// Terminal
const termHistory = []
window.handleTerminal = function (e) {
  e.preventDefault()
  const input = document.getElementById('termInput')
  const output = document.getElementById('terminalOutput')
  const cmd = input.value.trim()
  if (!cmd) return
  input.value = ''

  output.innerHTML += `<div><span class="text-accent">$ </span><span class="text-white">${cmd}</span></div>`
  addLog(`Terminal: ${cmd}`, 'terminal', 'info')

  const parts = cmd.split(' ')
  const base = parts[0].toLowerCase()
  let result = ''

  if (base === 'help') {
    result = `<span class="text-gray-400">Available commands: ping, traceroute, nslookup, whoami, uptime, ifconfig, ls, date, clear, devices, tunnels</span>`
  } else if (base === 'ping') {
    const target = parts[1] || '8.8.8.8'
    result = `<span class="text-gray-400">PING ${target}: 64 bytes, time=${Math.floor(
      Math.random() * 50 + 5
    )}ms\nPING ${target}: 64 bytes, time=${Math.floor(
      Math.random() * 50 + 5
    )}ms\n--- ${target} ping statistics ---\n2 packets transmitted, 2 received, 0% loss</span>`
  } else if (base === 'traceroute') {
    result = `<span class="text-gray-400">1  gateway (192.168.1.1)  ${Math.floor(
      Math.random() * 5 + 1
    )}ms\n2  isp-router  ${Math.floor(Math.random() * 15 + 5)}ms\n3  ${
      parts[1] || '8.8.8.8'
    }  ${Math.floor(Math.random() * 30 + 10)}ms</span>`
  } else if (base === 'whoami') {
    result = `<span class="text-accent">admin@netpanel</span>`
  } else if (base === 'uptime') {
    result = `<span class="text-gray-400">up 30 days, 2:14, load average: 0.15, 0.10, 0.05</span>`
  } else if (base === 'date') {
    result = `<span class="text-gray-400">${new Date().toString()}</span>`
  } else if (base === 'ifconfig') {
    result = `<span class="text-gray-400">eth0: inet 192.168.1.100  netmask 255.255.255.0\nwlan0: inet 192.168.1.105  netmask 255.255.255.0\ntun0: inet 10.0.0.1  netmask 255.255.255.0</span>`
  } else if (base === 'devices') {
    result = `<span class="text-gray-400">${raspberries
      .map(r => `${r.name} - ${r.ip} [${r.status}]`)
      .join('\n')}</span>`
  } else if (base === 'tunnels') {
    result = `<span class="text-gray-400">${tunnels
      .map(t => `${t.name} -> ${t.target} [${t.status}]`)
      .join('\n')}</span>`
  } else if (base === 'clear') {
    output.innerHTML = ''
    return
  } else if (base === 'nslookup') {
    const t = parts[1] || 'ettzz.dev'
    result = `<span class="text-gray-400">Server: 1.1.1.1\nAddress: 1.1.1.1#53\n\nName: ${t}\nAddress: 104.21.${Math.floor(
      Math.random() * 255
    )}.${Math.floor(Math.random() * 255)}</span>`
  } else {
    result = `<span class="text-danger">Command not found: ${base}. Type 'help' for commands.</span>`
  }

  output.innerHTML += `<div style="white-space:pre-wrap">${result}</div>`
  output.scrollTop = output.scrollHeight
}

// Quick Actions by Tab
const quickActionsByTab = {
  ai: [
    {
      label: '🔍 Scan Network',
      action: 'scan'
    },
    {
      label: '📊 System Status Report',
      action: 'status'
    },
    {
      label: '🌐 Check Cloudflare Tunnels',
      action: 'tunnels'
    },
    {
      label: '📱 List All Devices',
      action: 'devices'
    },
    {
      label: '🚨 Show Recent Alerts',
      action: 'alerts'
    }
  ],
  cloudflare: [
    {
      label: '🌐 Tunnel status',
      action: 'tunnel_status'
    },
    {
      label: '🔧 Restart tunnel',
      action: 'restart_tunnel'
    },
    {
      label: '📊 Traffic analytics',
      action: 'cf_traffic'
    },
    {
      label: '🔐 SSL certificate status',
      action: 'ssl_status'
    }
  ],
  traccar: [
    {
      label: '🗺️ Where is my car',
      action: 'car_location'
    },
    {
      label: '📍 Geofence alerts',
      action: 'geofence_alerts'
    },
    {
      label: '📊 Trip history',
      action: 'trip_history'
    },
    {
      label: '🔋 Device battery status',
      action: 'device_battery'
    }
  ],
  terminal: [
    {
      label: '🖥️ System uptime',
      action: 'uptime'
    },
    {
      label: '🔗 Network connectivity',
      action: 'ping_test'
    },
    {
      label: '📁 Disk space',
      action: 'disk_space'
    },
    {
      label: '👥 Current users',
      action: 'users'
    }
  ]
}

const quickActionPrompts = {
  // Main AI tab
  scan: 'Perform a network scan and tell me what you find',
  status: 'Give me a comprehensive system status report',
  tunnels: 'Check the status of all Cloudflare tunnels',
  devices: 'List all devices on the network with their status',
  alerts: 'Show me any recent alerts or warnings',

  // Cloudflare tab
  tunnel_status: 'What is the current status of all tunnels',
  restart_tunnel: 'Help me restart a Cloudflare tunnel',
  cf_traffic: 'Show me Cloudflare traffic analytics',
  ssl_status: 'Check SSL certificate status',

  // Traccar tab
  car_location: 'Where is my car right now',
  geofence_alerts: 'Show me geofence alerts and violations',
  trip_history: 'Show me recent trip history',
  device_battery: 'What is the battery status of all tracking devices',

  // Terminal tab
  uptime: 'Check system uptime',
  ping_test: 'Test network connectivity to major services',
  disk_space: 'Show current disk space usage',
  users: 'List current logged in users'
}

// Dynamic Model Switching
async function fetchData () {
  const response = await fetch('/api/chat.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      message: promptText,
      model_tier: document.getElementById('modelSelector').value
    })
  })
}

// Unified Chat System
const API_BASE_URL = window.location.origin
const API_URL = `${API_BASE_URL}/api/chat.php`
let conversationId = localStorage.getItem('conversation_id')

// Initialize chat on page load
function initChatSystem () {
  const saved = localStorage.getItem('conversation_id')
  if (saved && saved !== 'null') {
    conversationId = saved
  }
  setFreshGreetings()
  loadConversations()
}

// Welcome Greeting
function setFreshGreetings () {
  // 1. Get your raw random greeting string row
  const greetingRaw = getFreshGreeting()
  const mainGreeting = document.getElementById('mainGreeting')
  const widgetGreeting = document.getElementById('widgetGreeting')

  if (!mainGreeting) return

  // 2. Extract your safe PHP username component layout if present
  const usernameSpan = mainGreeting.querySelector('span')
  const usernameHTML = usernameSpan ? usernameSpan.outerHTML : ''

  // ✨ FIX: Convert ONLY the raw text value to uppercase so your HTML tags remain completely lowercase!
  const upperCaseGreeting = greetingRaw.toUpperCase()

  // 3. Construct the clean, tag-safe string layout matrix
  const fullText = `>_ ${upperCaseGreeting} ${usernameHTML} `

  // Reset element conditions and append typewriter baseline configurations
  mainGreeting.innerHTML = ''
  mainGreeting.classList.add('terminal-caret')

  let index = 0

  function type () {
    if (index >= fullText.length) return

    // If the tracking cursor hits an open code bracket, skip it instantly
    while (fullText.charAt(index) === '<') {
      const closeTagIndex = fullText.indexOf('>', index)
      if (closeTagIndex !== -1) {
        index = closeTagIndex + 1
      } else {
        break
      }
    }

    mainGreeting.innerHTML = fullText.slice(0, index)
    index++

    // Typing interval frame rate ticks
    setTimeout(type, 25)
  }

  type()

  if (widgetGreeting) {
    widgetGreeting.innerHTML = greetingRaw
  }
}

// Chat bubble helpers
function escapeHtml (str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
}

// Turn markdown into safe HTML for assistant bubbles.
function renderMarkdown (text) {
  if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
    console.warn('marked/DOMPurify not loaded - showing plain text')
    return escapeHtml(text).replace(/\n/g, '<br>')
  }
  return DOMPurify.sanitize(
    marked.parse(String(text ?? ''), {
      breaks: true
    })
  )
}

// One place that builds a bubble, used for live messages AND loaded history
function buildBubble (role, text) {
  const isUser = role === 'user'
  const isBg = isUser
    ? 'bg-accent/10 ml-auto rounded-tr-sm'
    : 'bg-accent2/10 rounded-tl-sm'
  const body = isUser ? escapeHtml(text) : renderMarkdown(text)
  const wrap = isUser ? 'white-space:pre-wrap' : ''
  return `<div class="chat-bubble md ${isBg} text-gray-300 p-3 rounded-xl text-[12px] md:text-[12px]" style="${wrap}">${body}</div>`
}

// Add message to both UIs
function addMessageToUI (role, text) {
  const mainMsgs = document.getElementById('chatMessages')
  const bubble = buildBubble(role, text)

  if (mainMsgs) {
    mainMsgs.innerHTML += bubble
    mainMsgs.scrollTop = mainMsgs.scrollHeight
  }

  lucide.createIcons()
}

// Send message through Claude (optionally with file attachments) to chat.php
async function sendMessage (message, files = []) {
  try {
    // Show loading state
    const loadingId = 'chat-loading-' + Date.now()
    const mainMsgs = document.getElementById('chatMessages')
    const loadingHTML = `
  <div id="${loadingId}" class="chat-bubble mb-3 bg-[#1e293b]/70 border border-purple-500/20 text-purple-200 p-3 rounded-xl rounded-tl-sm text-sm flex items-center gap-2">
    <i data-lucide="loader" class="w-5 h-5 animate-spin text-green-400"></i>
    <span>...</span>
  </div>`

    if (mainMsgs) {
      mainMsgs.innerHTML += loadingHTML
      mainMsgs.scrollTop = mainMsgs.scrollHeight
    }

    lucide.createIcons()

    // Build the request: multipart when there are files, JSON otherwise
    let fetchOpts
    if (files.length) {
      const fd = new FormData()
      fd.append('message', message)
      fd.append('conversation_id', conversationId ?? '')
      files.forEach(f => fd.append('files[]', f))
      // No Content-Type header: the browser sets the multipart boundary itself
      fetchOpts = {
        method: 'POST',
        credentials: 'include',
        body: fd
      }
    } else {
      fetchOpts = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({
          message,
          conversation_id: conversationId
        })
      }
    }

    const res = await fetch(API_URL, fetchOpts)
    const responseText = await res.text()

    let data
    try {
      data = JSON.parse(responseText)
    } catch (parseError) {
      document.getElementById(loadingId)?.remove()
      console.error('RAW PHP ERROR RESPONSE:', responseText)
      throw new Error(
        `Server returned ${res.status} ${res.statusText}, not JSON. Check console for raw log.`
      )
    }

    // Remove loading
    document.getElementById(loadingId)?.remove()

    // Handle errors
    if (data.error) {
      addMessageToUI('assistant', `⚠ Error: ${data.error}`)
      addLog(`AI error: ${data.error}`, 'ai', 'error')
      return
    }

    // Update conversation ID if new
    if (data.conversation_id) {
      conversationId = data.conversation_id
      localStorage.setItem('conversation_id', conversationId)
    }

    // Add assistant reply
    addMessageToUI('assistant', data.reply)
    addLog(`AI: ${data.reply.substring(0, 50)}...`, 'ai', 'info')

    // Refresh conversation list
    loadConversations()
  } catch (err) {
    console.error('FETCH ERROR:', err)
    addMessageToUI('assistant', `⚠ Error: ${err.message}`)
    addLog(`AI error: ${err.message}`, 'ai', 'error')
  }
}
// Load conversations for sidebar
async function loadConversations () {
  try {
    const res = await fetch(`${API_BASE_URL}/api/load_conversations.php`, {
      credentials: 'include'
    })
    const data = await res.json()
    // Correctly reference the array inside the response object
    const convs = data.conversations || []

    const list = document.getElementById('chatHistoryList')
    if (!list) return

    if (!Array.isArray(convs) || convs.length === 0) {
      list.innerHTML =
        '<div class="text-center text-gray-600 text-xs py-6">No conversations yet</div>'
      return
    }

    list.innerHTML = convs
      .map(
        c => `
      <button onclick="selectConversation('${
        c.id
      }')" class="w-full text-left p-3 rounded-xl border text-xs transition ${
          conversationId == c.id
            ? 'bg-surface border-accent/40 text-white'
            : 'bg-card border-border/50 hover:border-accent/30 text-gray-300'
        }">
        <div class="font-medium truncate">${c.title || 'Untitled Chat'}</div>
        <div class="text-gray-500 text-[10px] mt-1">${formatChatDate(
          c.created_at
        )}</div>
      </button>
    `
      )
      .join('')
  } catch (err) {
    console.error('Load conversations error:', err)
  }
}

// Load messages from a conversation
async function loadMessages (id) {
  try {
    const res = await fetch(
      `${API_BASE_URL}/api/get_messages.php?conversation_id=${encodeURIComponent(
        id
      )}`
    )
    const messages = await res.json()

    const mainMsgs = document.getElementById('chatMessages')
    const widgetMsgs = document.getElementById('widgetMessages')

    if (!mainMsgs && !widgetMsgs) return

    const html = messages.map(m => buildBubble(m.role, m.content)).join('')

    if (mainMsgs) {
      mainMsgs.innerHTML = html
      mainMsgs.scrollTop = mainMsgs.scrollHeight
    }
    lucide.createIcons()
  } catch (err) {
    console.error('Load messages error:', err)
  }
}

// Select conversation from history
window.selectConversation = function (id) {
  conversationId = id
  localStorage.setItem('conversation_id', id)
  loadMessages(id)

  // Keep the right-side drawer state consistent on mobile.
  if (window.innerWidth < 1024) {
    closeChatHistory()
  }
}

// Chat History
function setChatHistoryState (open) {
  const sidebar = document.getElementById('chatHistorySidebar')
  const overlay = document.getElementById('chatHistoryOverlay')
  if (!sidebar || !overlay) return

  // Always anchor this drawer to the right; never reuse left-side drawer classes.
  sidebar.style.left = 'auto'
  sidebar.style.right = '0'
  sidebar.classList.remove(
    '-translate-x-full',
    'translate-x-0',
    'translate-x-full'
  )
  sidebar.classList.add(open ? 'translate-x-0' : 'translate-x-full')
  sidebar.dataset.open = open ? 'true' : 'false'
  overlay.classList.toggle('hidden', !open)
}

function openChatHistory () {
  setChatHistoryState(true)
}

function closeChatHistory () {
  setChatHistoryState(false)
}

window.toggleChatHistory = function () {
  const sidebar = document.getElementById('chatHistorySidebar')
  if (!sidebar) return
  setChatHistoryState(sidebar.dataset.open !== 'true')
}

// - Cloudflare API Tester -
async function testCfConnection () {
  const token = document.getElementById('cfApiToken').value
  const accountId = document.getElementById('cfAccountId').value

  if (!token) {
    alert('API token required')
    return
  }

  try {
    const res = await fetch('/api/tunnel_status.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        token,
        accountId
      })
    })

    const data = await res.json()
    console.log(data)

    if (data.success) {
      alert('✅ Connected to Cloudflare')
    } else {
      alert('❌ Failed: ' + data.error)
    }
  } catch (err) {
    console.error(err)
    alert('Request failed')
  }
}

// Cloudflare Zero Trust Tunnels -
async function loadCloudflareTunnels () {
  try {
    const res = await fetch('/api/tunnel_status.php')
    const data = await res.json()

    // Cloudflare returns tunnels inside the 'result' array property
    if (!data.success || !data.result || !Array.isArray(data.result)) return

    const liveTunnels = data.result // Define liveTunnels from the API result

    // Initialize status counters
    let healthyCount = 0
    let downCount = 0

    // Grab and sort: healthy/active first, then alphabetical by name
    const tunnelsList = [...liveTunnels].sort((a, b) => {
      // 1. Force strings to lowercase and strip hidden spacing/newlines
      const aStatus = (a.status || '').toString().trim().toLowerCase()
      const bStatus = (b.status || '').toString().trim().toLowerCase()

      // 2. Comprehensive check for active proxy flags
      const aOnline =
        aStatus === 'healthy' || aStatus === 'active' || aStatus === 'online'
      const bOnline =
        bStatus === 'healthy' || bStatus === 'active' || bStatus === 'online'

      // 3. Sort by network status first
      if (aOnline !== bOnline) {
        return aOnline ? -1 : 1
      }

      // 4. Force mobile-safe case-insensitive alphabetical sorting
      const nameA = (a.name || '').toString().trim().toLowerCase()
      const nameB = (b.name || '').toString().trim().toLowerCase()

      return nameA.localeCompare(nameB, undefined, {
        numeric: true,
        sensitivity: 'base'
      })
    })

    // Render the calculation stat cards
    const statCards = document.getElementById('cfStatCards')
    if (statCards) {
      statCards.innerHTML = `
        <div class="bg-card p-4 border border-border rounded-xl">
          <p class="text-xs text-gray-500 font-medium">Total Tunnels</p>
          <p class="text-lg font-bold text-white">${tunnelsList.length}</p>
        </div>
        <div class="bg-card p-4 border border-border rounded-xl">
          <p class="text-xs text-gray-500 font-medium">Healthy</p>
          <p class="text-lg font-bold text-emerald-400">${healthyCount}</p>
        </div>
        <div class="bg-card p-4 border border-border rounded-xl">
          <p class="text-xs text-gray-500 font-medium">Down</p>
          <p class="text-lg font-bold text-rose-500">${downCount}</p>
        </div>
      `
    }

    // Clear out the loading notice and map over active tunnels
    const container = document.getElementById('tunnelsListContainer')
    if (!container) return // Already exists, but ensure it stops execution early if null

    if (tunnelsList.length === 0) {
      container.innerHTML = `<div class="text-xs text-gray-500 text-center py-4">No tunnels detected or loading status...</div>`
      return
    }

    container.innerHTML = tunnelsList
      .map(t => {
        const isHealthy =
          t.status.toLowerCase() === 'healthy' ||
          t.status.toLowerCase() === 'active'
        return `
        <div class="p-3 bg-surface border border-border/60 rounded-xl flex items-center justify-between">
          <div>
            <h4 class="text-xs font-semibold text-white">${
              t.name || 'Unnamed Tunnel'
            }</h4>
            <p class="text-[13px] text-gray-500 mono font-light mt-1.5">ID: ${
              t.id || 'N/A'
            }</p>
            <p class="text-[13px] text-gray-500 mono font-light mt-0.5">Created: ${
              t.created_at || 'N/A'
            }</p>
            <p class="text-[13px] text-gray-500 mono font-light mt-0.5">Active Conn: ${
              t.conns_active_at || 'N/A'
            }</p>
            <p class="text-[13px] text-gray-500 mono font-light mt-0.5">Tunnel Type: ${
              t.tun_type || 'N/A'
            }</p>
          </div>
          <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider ${
            isHealthy
              ? 'bg-emerald-500/10 text-emerald-400'
              : 'bg-rose-500/10 text-rose-400'
          }">
            ${t.status}
          </span>
        </div>
      `
      })
      .join('')

    lucide.createIcons()
  } catch (err) {
    console.error('Tunnel fetch failed:', err)
  }
}

// Load tunnels on startup
loadCloudflareTunnels()
setInterval(loadCloudflareTunnels, 30000)

// Tunnel refresh
window.manualTunnelRefresh = async function () {
  const btn = document.getElementById('refreshTunnelsBtn')
  const icon = document.getElementById('refreshIcon')

  if (!btn || !icon) return

  // 1. Disable the button and add the spin animation
  btn.disabled = true
  icon.classList.add('animate-spin')

  // 2. Await the existing fetch function to complete
  await loadCloudflareTunnels()

  // 3. Enable the button again and stop the spin animation
  btn.disabled = false
  icon.classList.remove('animate-spin')
}

// Cloudflare API tester
window.toggleCfTokenVisibility = function () {
  const input = document.getElementById('cfApiToken')
  if (input) {
    input.type = input.type === 'password' ? 'text' : 'password'
  }
}

window.testCfConnection = function () {
  const token = document.getElementById('cfApiToken')?.value
  if (!token) {
    showToast('Please enter your Cloudflare API token', 'error')
    return
  }

  showToast('Testing connection...', 'info')

  fetch('https://api.cloudflare.com/client/v4/user', {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`✓ Connected! User: ${data.result.email}`, 'success')
        addLog(
          `Cloudflare API connected for ${data.result.email}`,
          'cloudflare',
          'info'
        )
      } else {
        showToast(
          `✗ Connection failed: ${
            data.errors?.[0]?.message || 'Unknown error'
          }`,
          'error'
        )
        addLog(
          `Cloudflare API error: ${data.errors?.[0]?.message}`,
          'cloudflare',
          'error'
        )
      }
    })
    .catch(err => {
      showToast(`✗ Connection error: ${err.message}`, 'error')
      addLog(`Cloudflare API error: ${err.message}`, 'cloudflare', 'error')
    })
}

function showToast (msg, type = 'info') {
  const colors = {
    info: 'text-accent',
    error: 'text-danger',
    success: 'text-success'
  }
  const bgColors = {
    info: 'bg-accent/10',
    error: 'bg-danger/10',
    success: 'bg-success/10'
  }
  const toast = document.createElement('div')
  toast.className = `fixed bottom-4 right-4 ${bgColors[type]} border border-border rounded-lg px-4 py-3 text-sm ${colors[type]} shadow-lg z-50 fade-in`
  toast.innerHTML = msg
  document.body.appendChild(toast)
  setTimeout(() => toast.remove(), 4000)
}

// API Sender
window.handleApi = function (e) {
  e.preventDefault()
  const method = document.getElementById('apiMethod').value
  const url = document.getElementById('apiUrl').value
  const target = document.getElementById('apiTarget').value
  const headersRaw = document.getElementById('apiHeaders').value
  const body = document.getElementById('apiBody').value
  const resp = document.getElementById('apiResponse')

  const fullUrl = target
    ? target + (url.startsWith('/') ? url : '/' + url)
    : url
  if (!fullUrl) {
    resp.innerHTML = '<span class="text-danger">Please enter a URL</span>'
    return
  }

  resp.innerHTML = '<span class="text-accent">Sending request...</span>'
  addLog(`API ${method} ${fullUrl}`, 'api', 'info')

  let headers = {}
  try {
    if (headersRaw) headers = JSON.parse(headersRaw)
  } catch (e) {
    resp.innerHTML = '<span class="text-danger">Invalid headers JSON</span>'
    return
  }

  const opts = {
    method,
    headers
  }
  if (['POST', 'PUT', 'PATCH'].includes(method) && body) opts.body = body

  fetch(fullUrl, opts)
    .then(async r => {
      const text = await r.text()
      let display = text
      try {
        display = JSON.stringify(JSON.parse(text), null, 2)
      } catch (e) {}
      resp.innerHTML = `<div class="text-gray-500 mb-2">Status: <span class="${
        r.ok ? 'text-success' : 'text-danger'
      }">${r.status} ${
        r.statusText
      }</span></div><pre class="text-gray-300 whitespace-pre-wrap">${display.substring(
        0,
        5000
      )}</pre>`
      addLog(`API response: ${r.status}`, 'api', r.ok ? 'info' : 'error')
    })
    .catch(err => {
      resp.innerHTML = `<span class="text-danger">Error: ${err.message}</span>\n<span class="text-gray-500 text-[10px]">Note: CORS or network restrictions may block requests from this panel.</span>`
      addLog(`API error: ${err.message}`, 'api', 'error')
    })
}

// Chat History
function updateChatHistoryUI () {
  const list = document.getElementById('chatHistoryList')
  if (!list) return

  if (chatHistory.length === 0) {
    list.innerHTML =
      '<div class="text-center text-gray-600 text-xs py-6">No conversations yet</div>'
    return
  }

  list.innerHTML = chatHistory
    .map(
      (session, idx) => `
    <button onclick="loadChatSession(${
      session.id
    })" class="w-full text-left p-3 bg-${
        currentChatSession?.id === session.id ? 'surface' : 'card'
      } hover:bg-surface rounded-lg border border-border/50 hover:border-accent/30 transition text-xs">
      <div class="text-white font-medium truncate">${session.title}</div>
      <div class="text-gray-500 text-[10px] mt-1">${formatChatDate(
        session.created
      )}</div>
      <div class="text-gray-600 text-[10px] mt-1">${
        session.messages.length
      } messages</div>
    </button>
  `
    )
    .join('')
}

function formatChatDate (dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr.replace(' ', 'T'))
  if (isNaN(d)) return dateStr

  const date = d
    .toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short'
    })
    .replace(' ', '-')
  const time = d.toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })

  return `${date} • ${time}`
}

window.loadChatSession = function (sessionId) {
  const session = chatHistory.find(s => s.id === sessionId)
  if (!session) return

  currentChatSession = session
  updateChatHistoryUI()

  // Switch to AI tab if not already there
  if (currentTab !== 'ai') {
    switchTab('ai')
  }

  // Update chat UI
  const msgs = document.getElementById('chatMessages')
  if (msgs) {
    msgs.innerHTML = session.messages
      .map(m => buildBubble(m.role, m.text))
      .join('')
    msgs.scrollTop = msgs.scrollHeight
    lucide.createIcons()
  }

  // Close sidebar
  closeChatHistory()
}

window.startNewChat = function () {
  currentChatSession = null

  // Reset the conversation so the next message creates a new one
  conversationId = null
  localStorage.removeItem('conversation_id')
  loadConversations() // refresh the history list highlight

  const freshGreeting = getFreshGreeting()
  const msgs = document.getElementById('chatMessages')
  if (msgs) {
    msgs.innerHTML = `<div class="chat-bubble bg-accent2/10 text-gray-300 p-3 rounded-xl rounded-tl-sm text-sm">${freshGreeting}</div>`
  }
  const widgetMsgs = document.getElementById('widgetMessages')
  if (widgetMsgs) {
    widgetMsgs.innerHTML = `<div class="chat-bubble bg-accent2/10 text-gray-300 p-3 rounded-xl rounded-tl-sm text-sm">${freshGreeting}</div>`
  }
  const input = document.getElementById('chatInput')
  if (input) input.focus()

  // Close sidebar
  closeChatHistory()
  switchTab('ai')
}

// Navigation
async function switchTab (id) {
  currentTab = id
  const tab = tabs.find(t => t.id === id)
  if (tab && document.getElementById('pageTitle')) {
    document.getElementById('pageTitle').textContent = tab.label
  }

  // Close menu when a navigation item is tapped on mobile
  if (window.innerWidth < 1024) {
    window.closeMobileNav()
  }

  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.tab === id)
  })

  const content = document.getElementById('content')

  try {
    // Dynamically fetch the HTML component built via PHP
    const response = await fetch(`tabs/${id}.php`)
    if (response.ok) {
      content.innerHTML = await response.text()

      // ✨ Fresh randomized greeting immediately after loading
      if (id === 'ai') {
        setFreshGreetings()
      }
    } else {
      content.innerHTML = `<div class="text-gray-500">Tab view [${id}] not found.</div>`
    }
  } catch (error) {
    content.innerHTML = `<div class="text-danger">Error loading component context.</div>`
  }

  lucide.createIcons()
}

function buildNav () {
  const list = document.getElementById('navList')
  list.innerHTML = tabs
    .map(
      t => `
    <button data-tab="${t.id}" class="nav-item w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-400 border-l-3 border-transparent" onclick="switchTab('${t.id}')">
      <i data-lucide="${t.icon}" class="w-4 h-4"></i>
      <span>${t.label}</span>
    </button>
  `
    )
    .join('')
}

// Unified, Error-Free Navigation System
async function switchTab (id) {
  currentTab = id
  const tab = tabs.find(t => t.id === id)
  if (tab && document.getElementById('pageTitle')) {
    document.getElementById('pageTitle').textContent = tab.label
  }

  // Close navigation menu drawer when items are selected on mobile layout scales
  if (window.innerWidth < 1024) {
    window.closeMobileNav()
  }

  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.tab === id)
  })

  const content = document.getElementById('content')
  if (!content) return

  try {
    // Append a clear timestamp query parameter to force clear cached browser copies
    const response = await fetch(`tabs/${id}.php?v=${new Date().getTime()}`)

    if (response.ok) {
      content.innerHTML = await response.text()

      // ✨ CRITICAL ROW: Safely re-initialize dynamic terminal texts
      if (id === 'ai') {
        setFreshGreetings()
      }
    } else {
      content.innerHTML = `<div class="text-gray-500">Tab view [${id}] not found.</div>`
    }
  } catch (error) {
    content.innerHTML = `<div class="text-danger">Error loading component context.</div>`
  }

  // Refresh active vector badge renders via Lucide bundle runtime paths
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    lucide.createIcons()
  }
}

// Dynamically fetch the HTML component built via PHP
window.openMobileNav = function () {
  const sidebar = document.getElementById('sidebar')
  const overlay = document.getElementById('sidebarOverlay')
  if (sidebar) {
    sidebar.classList.remove('-translate-x-full')
    sidebar.classList.add('translate-x-0')
  }
  if (overlay) {
    overlay.classList.remove('hidden')
  }
}

window.closeMobileNav = function () {
  const sidebar = document.getElementById('sidebar')
  const overlay = document.getElementById('sidebarOverlay')
  if (sidebar) {
    sidebar.classList.remove('translate-x-0')
    sidebar.classList.add('-translate-x-full')
  }
  if (overlay) {
    overlay.classList.add('hidden')
  }
}

window.toggleMobileNav = function () {
  const sidebar = document.getElementById('sidebar')
  if (!sidebar) return
  // If it currently has -translate-x-full, open it; otherwise close it
  if (sidebar.classList.contains('-translate-x-full')) {
    window.openMobileNav()
  } else {
    window.closeMobileNav()
  }
}

// Clock
function updateClock () {
  const el = document.getElementById('clockDisplay')
  if (el) el.textContent = new Date().toLocaleTimeString()
}

// Init
buildNav()
switchTab('ai')
updateClock()
initChatSystem()
setInterval(updateClock, 1000)

// Seed some logs
addLog('System initialized', 'system', 'info')
addLog('All Cloudflare tunnels connected', 'cloudflare', 'info')
addLog('traccar-server tunnel degraded', 'cloudflare', 'warning')
addLog('NAS device unreachable', 'openmediavault', 'error')
addLog('routerPi CPU normal', 'raspberry', 'info')

// SDK Init
const dataHandler = {
  onDataChanged (data) {}
}
// Merge persisted logs with in-memory
;(async () => {
  if (window.dataSdk) {
    const r = await window.dataSdk.init(dataHandler)
    if (!r.isOk) console.error('Data SDK init failed')
  }
})()

if (window.elementSdk) {
  window.elementSdk.init({
    defaultConfig,
    async onConfigChange (config) {
      const title = config.panel_title || defaultConfig.panel_title
      const el = document.getElementById('panelTitle')
      if (el) el.textContent = title
      // Colors
      document.body.style.background = config.bg_color || defaultConfig.bg_color
      document.documentElement.style.setProperty(
        '--card',
        config.card_color || defaultConfig.card_color
      )
    },
    mapToCapabilities (config) {
      return {
        recolorables: [
          {
            get: () => config.bg_color || defaultConfig.bg_color,
            set: v => {
              config.bg_color = v
              window.elementSdk.setConfig({
                bg_color: v
              })
            }
          },
          {
            get: () => config.card_color || defaultConfig.card_color,
            set: v => {
              config.card_color = v
              window.elementSdk.setConfig({
                card_color: v
              })
            }
          },
          {
            get: () => config.text_color || defaultConfig.text_color,
            set: v => {
              config.text_color = v
              window.elementSdk.setConfig({
                text_color: v
              })
            }
          },
          {
            get: () => config.accent_color || defaultConfig.accent_color,
            set: v => {
              config.accent_color = v
              window.elementSdk.setConfig({
                accent_color: v
              })
            }
          },
          {
            get: () => config.secondary_color || defaultConfig.secondary_color,
            set: v => {
              config.secondary_color = v
              window.elementSdk.setConfig({
                secondary_color: v
              })
            }
          }
        ],
        borderables: [],
        fontEditable: {
          get: () => config.font_family || 'Outfit',
          set: v => {
            config.font_family = v
            window.elementSdk.setConfig({
              font_family: v
            })
          }
        },
        fontSizeable: undefined
      }
    },
    mapToEditPanelValues (config) {
      return new Map([
        ['panel_title', config.panel_title || defaultConfig.panel_title]
      ])
    }
  })
}

// Handles real-time selection arrays on standard input element updates
window.handleFileSelection = function (inputElement) {
  if (!inputElement.files.length) return

  // Convert FileList into standard mutable Array and merge into state
  const selectedFiles = Array.from(inputElement.files)
  stagedChatFiles = [...stagedChatFiles, ...selectedFiles]

  // Render visual badges to notify the client user
  renderFilePreviews()

  // Reset input target value clear to let the user add the same file name twice if wanted
  inputElement.value = ''
}

// UI Rendering loop for preview badges
function renderFilePreviews () {
  const container = document.getElementById('filePreviewContainer')
  if (!container) return

  container.innerHTML = stagedChatFiles
    .map(
      (file, index) => `
    <div class="file-preview-chip">
      <i data-lucide="file-text" class="w-3 h-3 text-purple-400"></i>
      <span class="truncate max-w-[140px]" title="${escapeHtml(
        file.name
      )}">${escapeHtml(file.name)}</span>
      <button type="button" onclick="removeStagedFile(${index})" aria-label="Remove file">
        <i data-lucide="x" class="w-3.5 h-3.5"></i>
      </button>
    </div>
  `
    )
    .join('')

  // Explicitly call Lucide runtime rendering for dynamically added vector tags
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons()
  }
}

// Drops a targeted index item out of local state
;(window.removeStagedFile = function (indexToRemove) {
  stagedChatFiles = stagedChatFiles.filter((_, idx) => idx !== indexToRemove)
  renderFilePreviews()
}),
  // Override
  (window.handleChat = function (e) {
    e.preventDefault()
    const input = document.getElementById('chatInput')
    const q = input.value.trim()

    // Intercept blocking constraints checking state values
    if (!q && stagedChatFiles.length === 0) return

    // If files exist, you can handle alternative layout insertions
    if (stagedChatFiles.length > 0) {
      let fileNotificationText = q ? `${q}\n` : ''
      fileNotificationText += `[📎 Attached ${
        stagedChatFiles.length
      } file(s): ${stagedChatFiles.map(f => f.name).join(', ')}]`

      addMessageToUI('user', fileNotificationText)
    } else {
      addMessageToUI('user', q)
    }

    // Replace your handleChat blocks with this unified implementation:
    window.handleChat = function (e) {
      e.preventDefault()
      const input = document.getElementById('chatInput')
      if (!input) return

      const q = input.value.trim()

      // Block processing if both the text prompt and attachments are completely empty
      if (!q && stagedChatFiles.length === 0) return

      // Format message text inside the terminal bubble depending on file presence
      if (stagedChatFiles.length > 0) {
        let fileNotificationText = q ? `${q}\n` : ''
        fileNotificationText += `[📎 Attached ${
          stagedChatFiles.length
        } file(s): ${stagedChatFiles.map(f => f.name).join(', ')}]`
        addMessageToUI('user', fileNotificationText)
      } else {
        addMessageToUI('user', q)
      }

      // Clear input box and reset focus states
      input.value = ''

      // Forward payload off to backend API pipeline
      sendMessage(q, stagedChatFiles)

      // Flush staged attachment data buffers cleanly
      stagedChatFiles = []
      if (typeof renderFilePreviews === 'function') {
        renderFilePreviews()
      }
    }

    // Reset active configurations immediately following payload reading operations
    input.value = ''

    // Pass variables off securely to your processing pipeline target
    // In a real environment, you will use FormData to upload file buffers via POST
    sendMessage(q, stagedChatFiles)

    // Clear file container arrays out completely
    stagedChatFiles = []
    renderFilePreviews()
  })

function handleSend () {
  const btn = document.getElementById('sendBtn')
  const plane = document.getElementById('planeIcon')
  const check = document.getElementById('checkIcon')

  // Fly plane forward
  plane.classList.add('translate-x-6', '-translate-y-6', 'opacity-0')

  setTimeout(() => {
    plane.classList.add('hidden')
    check.classList.remove('scale-0', 'opacity-0')
    check.classList.add('scale-100', 'opacity-100')
    btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-500')
    btn.classList.add('bg-emerald-600')
  }, 150)

  // Reset back to original state after 1.5 seconds
  setTimeout(() => {
    check.classList.remove('scale-100', 'opacity-100')
    check.classList.add('scale-0', 'opacity-0')

    plane.classList.remove(
      'hidden',
      'translate-x-6',
      '-translate-y-6',
      'opacity-0'
    )
    btn.classList.remove('bg-emerald-600')
    btn.classList.add('bg-indigo-600', 'hover:bg-indigo-500')
  }, 1500)
}

// Quick target auto-fill
document.addEventListener('change', e => {
  if (e.target.id === 'apiTarget' && e.target.value) {
    const urlInput = document.getElementById('apiUrl')
    if (urlInput && !urlInput.value) urlInput.value = e.target.value
  }
})

lucide.createIcons()
