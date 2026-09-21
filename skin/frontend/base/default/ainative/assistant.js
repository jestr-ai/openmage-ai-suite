/* OpenMage AI Suite — storefront assistant widget. Vanilla JS, no dependencies, theme-agnostic. */
(function () {
  'use strict';
  var root = document.getElementById('ainative-assistant');
  if (!root) return;
  var cfg, cfgEl = document.getElementById('ainative-assistant-config');
  try { cfg = JSON.parse(cfgEl ? cfgEl.textContent : root.getAttribute('data-config')); } catch (e) { return; }
  var L = cfg.labels || {};
  var storeKey = 'ainative_assistant_conv';
  var conversationId = 0, busy = false, opened = false;
  try { conversationId = parseInt(sessionStorage.getItem(storeKey) || '0', 10) || 0; } catch (e) {}

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function md(t) { return esc(t).replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/^\s*[-•]\s+(.*)$/gm, '• $1'); }

  root.className = 'ainav ainav--' + cfg.position;
  root.style.setProperty('--ainav-accent', cfg.accent);
  var launch = el('button', 'ainav__launch', '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.5 3 2 6.9 2 11.7c0 2.4 1.1 4.6 3 6.2L4 21l4.3-1.6c1.2.3 2.4.5 3.7.5 5.5 0 10-3.9 10-8.7S17.5 3 12 3z"/></svg><span>' + esc(L.open) + '</span>');
  launch.type = 'button'; launch.setAttribute('aria-label', L.open);
  var panel = el('div', 'ainav__panel'); panel.hidden = true; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', cfg.name);
  panel.innerHTML = '<div class="ainav__head"><strong>' + esc(cfg.name) + '</strong><span><button type="button" data-new>' + esc(L.newChat) + '</button><button type="button" data-close aria-label="' + esc(L.close) + '">✕</button></span></div>'
    + '<div class="ainav__thread" aria-live="polite"></div>'
    + '<form class="ainav__form"><textarea rows="1" placeholder="' + esc(L.placeholder) + '" aria-label="' + esc(L.placeholder) + '"></textarea><button type="submit">' + esc(L.send) + '</button></form>'
    + '<div class="ainav__foot">' + esc(L.disclaimer) + '</div>';
  root.appendChild(launch); root.appendChild(panel);
  var thread = panel.querySelector('.ainav__thread'), form = panel.querySelector('form'), input = form.querySelector('textarea'), sendBtn = form.querySelector('button');

  function scroll() { thread.scrollTop = thread.scrollHeight; }
  function msg(role, html) { var m = el('div', 'ainav__msg ainav__msg--' + role, html); thread.appendChild(m); scroll(); return m; }
  function greet() {
    var m = msg('bot', md(cfg.greeting));
    if (cfg.suggestions && cfg.suggestions.length) {
      var chips = el('div', 'ainav__chips');
      cfg.suggestions.forEach(function (s) { var c = el('button', 'ainav__chip', esc(s)); c.type = 'button'; c.addEventListener('click', function () { send(s); }); chips.appendChild(c); });
      m.appendChild(chips);
    }
  }
  function productCard(p) {
    var price = '<span class="ainav__price">' + (p.price_is_from ? 'from ' : '') + esc(p.price) + (p.regular_price ? '<s>' + esc(p.regular_price) + '</s>' : '') + '</span>' + (p.on_sale ? '<span class="ainav__badge">Sale</span>' : '') + (!p.in_stock ? '<span class="ainav__badge">Out of stock</span>' : '');
    var action = p.type === 'add_to_cart' && p.add_to_cart_url
      ? '<a class="ainav__btn" href="' + esc(p.add_to_cart_url) + '" data-track="add_to_cart">' + esc(L.addToCart) + (p.qty > 1 ? ' ×' + p.qty : '') + '</a> <a class="ainav__btn ainav__btn--ghost" href="' + esc(p.url) + '">' + esc(L.view) + '</a>'
      : '<a class="ainav__btn" href="' + esc(p.url) + '" data-track="view">' + esc(L.view) + '</a>';
    return '<div class="ainav__card">' + (p.image ? '<a href="' + esc(p.url) + '"><img src="' + esc(p.image) + '" alt="" loading="lazy"></a>' : '') + '<div class="ainav__card-body"><a class="ainav__card-name" href="' + esc(p.url) + '">' + esc(p.name) + '</a>' + price + '<div>' + action + '</div></div></div>';
  }
  function orderCard(o) {
    var items = (o.items || []).map(function (i) { return '<li>' + esc(i.name) + ' × ' + esc(i.qty) + (i.shipped ? ' (' + esc(i.shipped) + ' shipped)' : '') + '</li>'; }).join('');
    var tracks = (o.tracking || []).map(function (t) { return '<div>' + esc(t.carrier) + ': <strong>' + esc(t.number) + '</strong></div>'; }).join('');
    return '<div class="ainav__order"><strong>Order #' + esc(o.order_number) + '</strong> · ' + esc(o.placed_at) + '<br>Status: <strong>' + esc(o.status) + '</strong> (' + esc(o.status_plain) + ')<ul>' + items + '</ul>' + (o.shipping_method ? '<div>' + esc(o.shipping_method) + (o.ship_to ? ' → ' + esc(o.ship_to) : '') + '</div>' : '') + tracks + '<div>Total: <strong>' + esc(o.total) + '</strong></div></div>';
  }
  function render(j) {
    var m = msg('bot', md(j.text));
    var products = (j.cards || []).filter(function (c) { return c.type === 'product' || c.type === 'add_to_cart'; });
    if (products.length) { m.appendChild(el('div', 'ainav__cards', products.map(productCard).join(''))); }
    (j.cards || []).filter(function (c) { return c.type === 'order'; }).forEach(function (o) { m.appendChild(el('div', '', orderCard(o))); });
    (j.cards || []).filter(function (c) { return c.type === 'handoff'; }).forEach(function (h) { m.appendChild(el('div', '', '<a class="ainav__btn" href="' + esc(h.url) + '">' + esc(L.contact) + '</a>')); });
    scroll();
  }
  function send(text) {
    text = (text || '').trim(); if (!text || busy) return;
    busy = true; sendBtn.disabled = true;
    msg('user', esc(text));
    var thinking = msg('bot', '<i>' + esc(L.thinking) + '</i>');
    fetch(cfg.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ message: text, conversation_id: conversationId, form_key: cfg.formKey, page: location.href }) })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.error) { var err = new Error(j.error || 'Error ' + r.status); err.retryable = !!j.retryable; throw err; } return j; }); })
      .then(function (j) { thinking.remove(); conversationId = j.conversation_id; try { sessionStorage.setItem(storeKey, String(conversationId)); } catch (e) {} render(j); })
      .catch(function (e) {
        thinking.innerHTML = '<span class="ainav__err">' + esc(e.message) + '</span>';
        if (e.retryable) {
          var again = el('button', 'ainav__chip', esc(L.retry || 'Try again'));
          again.type = 'button';
          again.addEventListener('click', function () { thinking.remove(); send(text); });
          thinking.appendChild(again);
        }
      })
      .then(function () { busy = false; sendBtn.disabled = false; input.focus(); });
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); var v = input.value; input.value = ''; input.style.height = ''; send(v); });
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.dispatchEvent(new Event('submit', { cancelable: true })); } });
  input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 100) + 'px'; });
  panel.querySelector('[data-close]').addEventListener('click', function () { panel.hidden = true; launch.focus(); });
  panel.querySelector('[data-new]').addEventListener('click', function () { conversationId = 0; try { sessionStorage.removeItem(storeKey); } catch (e) {} thread.innerHTML = ''; greet(); input.focus(); });
  launch.addEventListener('click', function () { panel.hidden = !panel.hidden; if (!panel.hidden) { if (!opened) { opened = true; greet(); } input.focus(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { panel.hidden = true; } });
})();
