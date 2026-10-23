/* Sentinel touch: additive chat enhancements. Load AFTER app.js.
   Uses event delegation and a MutationObserver because your tabs are
   injected into #content and chat bubbles are rebuilt via innerHTML.
   Remove the <script> include to undo everything. */
(() => {
  'use strict'

  const $ = (sel, root = document) => root.querySelector(sel)

  /* ---- 1. Model commands: type "/" in the chat box ---- */
  const COMMANDS = [
    { cmd: '/pro', note: 'Opus, for complex problems. Slower.' },
    { cmd: '/lite', note: 'Haiku, for quick lookups.' }
  ]
  let palette = null
  let shown = []
  let active = 0

  function ensurePalette () {
    const form = $('#chatForm')
    if (!form) return null
    if (palette && palette.parentElement === form) return palette
    palette = document.createElement('div')
    palette.className = 'st-palette'
    palette.setAttribute('role', 'listbox')
    palette.hidden = true
    form.appendChild(palette)
    return palette
  }

  const closePalette = () => { if (palette) palette.hidden = true }

  function renderPalette (value) {
    const el = ensurePalette()
    if (!el) return
    shown = /^\/\S*$/.test(value)
      ? COMMANDS.filter(c => c.cmd.startsWith(value.toLowerCase()))
      : []
    if (!shown.length) { el.hidden = true; return }
    active = Math.min(active, shown.length - 1)
    el.replaceChildren(...shown.map((c, i) => {
      const b = document.createElement('button')
      b.type = 'button'
      b.className = 'st-opt'
      b.dataset.cmd = c.cmd
      b.setAttribute('role', 'option')
      b.setAttribute('aria-selected', String(i === active))
      const code = document.createElement('code')
      code.textContent = c.cmd
      const note = document.createElement('small')
      note.textContent = c.note
      b.append(code, note)
      return b
    }))
    el.hidden = false
  }

  function accept (cmd) {
    const input = $('#chatInput')
    if (!input) return
    input.value = cmd + ' '
    closePalette()
    input.focus()
  }

  document.addEventListener('input', e => {
    if (e.target.id !== 'chatInput') return
    active = 0
    renderPalette(e.target.value)
  })

  document.addEventListener('mousedown', e => {
    const opt = e.target.closest('.st-opt')
    if (opt) { e.preventDefault(); accept(opt.dataset.cmd) } // keep focus in the input
    else if (palette && !palette.contains(e.target) && e.target.id !== 'chatInput') closePalette()
  })

  /* ---- 2. Keyboard: palette navigation + the shortcuts your UI already advertises ---- */
  document.addEventListener('keydown', e => {
    if (palette && !palette.hidden && e.target.id === 'chatInput') {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault()
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % shown.length
        renderPalette(e.target.value)
        return
      }
      if (e.key === 'Tab' || e.key === 'Enter') {
        e.preventDefault()
        accept(shown[active].cmd)
        return
      }
      if (e.key === 'Escape') { e.preventDefault(); closePalette(); return }
    }

    const search = $('#mainQuickActionSearch')
    if (!search) return
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault()
      search.focus()
      search.select()
    } else if (e.key === '/' && !typing && !e.metaKey && !e.ctrlKey && !e.altKey) {
      e.preventDefault()
      search.focus()
    }
  })

  /* ---- 3. Copy button on code blocks (delegated: bubbles get rebuilt via innerHTML) ---- */
  document.addEventListener('click', async e => {
    const btn = e.target.closest('.st-copy')
    if (!btn) return
    const code = btn.parentElement.querySelector('pre code') || btn.parentElement.querySelector('pre')
    try {
      await navigator.clipboard.writeText(code.innerText)
      btn.textContent = 'Copied'
    } catch {
      btn.textContent = 'Copy failed'
    }
    setTimeout(() => { btn.textContent = 'Copy' }, 1600)
  })

  /* ---- 4. Decorate new content: copy buttons + the working indicator ---- */
  const HOPS =
    '<span class="st-hops" aria-hidden="true">' +
    '<span class="st-hop"></span><span class="st-hop"></span>' +
    '<span class="st-hop"></span><span class="st-hop"></span></span>' +
    '<span>Working on it</span>'

  function decorate () {
    document.querySelectorAll('.chat-bubble.md pre').forEach(pre => {
      if (pre.parentElement.classList.contains('st-code')) return
      const wrap = document.createElement('div')
      wrap.className = 'st-code'
      pre.replaceWith(wrap)
      const b = document.createElement('button')
      b.type = 'button'
      b.className = 'st-copy'
      b.textContent = 'Copy'
      b.setAttribute('aria-label', 'Copy code')
      wrap.append(pre, b)
    })
    document.querySelectorAll('[id^="chat-loading-"]:not([data-st])').forEach(node => {
      node.dataset.st = '1'
      node.classList.add('st-thinking')
      node.setAttribute('role', 'status')
      node.innerHTML = HOPS
    })
  }

  let queued = false
  new MutationObserver(() => {
    if (queued) return
    queued = true
    requestAnimationFrame(() => { queued = false; decorate() })
  }).observe(document.body, { childList: true, subtree: true })
  decorate()

  /* ---- 5. Drag files anywhere onto the chat tab ----
     Hands files to your own handleFileSelection(), so previews and
     sending work exactly like the paperclip button. */
  const drop = document.createElement('div')
  drop.className = 'st-drop'
  drop.innerHTML = '<div><strong>Drop to attach</strong><span>Text files only: logs, configs, JSON</span></div>'
  document.body.appendChild(drop)

  const isFileDrag = e => !!$('#chatFileInput') && Array.from(e.dataTransfer?.types || []).includes('Files')
  let depth = 0

  document.addEventListener('dragenter', e => {
    if (!isFileDrag(e)) return
    e.preventDefault()
    depth++
    document.body.classList.add('st-dropping')
  })
  document.addEventListener('dragover', e => { if (isFileDrag(e)) e.preventDefault() })
  document.addEventListener('dragleave', e => {
    if (!isFileDrag(e)) return
    depth = Math.max(0, depth - 1)
    if (!depth) document.body.classList.remove('st-dropping')
  })
  document.addEventListener('drop', e => {
    if (!isFileDrag(e)) return
    e.preventDefault()
    depth = 0
    document.body.classList.remove('st-dropping')
    const input = $('#chatFileInput')
    const dt = new DataTransfer()
    Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f))
    input.files = dt.files
    if (typeof window.handleFileSelection === 'function') window.handleFileSelection(input)
  })
})()
