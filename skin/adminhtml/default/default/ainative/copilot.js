/* OpenMage AI Suite — admin copilot (vanilla JS, no Prototype dependency) */
(function (global) {
  'use strict';

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }

  function post(url, data, formKey) {
    var body = new URLSearchParams();
    body.set('form_key', formKey);
    Object.keys(data || {}).forEach(function (k) { if (data[k] !== undefined && data[k] !== null) body.set(k, data[k]); });
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
      .then(function (r) { return r.text().then(function (t) { var j; try { j = JSON.parse(t); } catch (e) { j = { error: 'Unexpected response (' + r.status + ')' }; } if (!r.ok || j.error) throw new Error(j.error || ('HTTP ' + r.status)); return j; }); });
  }

  /** very small markdown → html (bold, code, lists, tables, line breaks) for assistant answers */
  function md(text) {
    var lines = String(text || '').split('\n'), out = [], inList = false, table = [];
    function flushTable() {
      if (!table.length) return;
      var rows = table.filter(function (r) { return !/^\s*\|?\s*:?-{2,}/.test(r); });
      var html = '<table>';
      rows.forEach(function (r, i) {
        var cells = r.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|');
        html += '<tr>' + cells.map(function (c) { return (i === 0 ? '<th>' : '<td>') + inline(c.trim()) + (i === 0 ? '</th>' : '</td>'); }).join('') + '</tr>';
      });
      out.push(html + '</table>'); table = [];
    }
    function inline(s) { return esc(s).replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>'); }
    lines.forEach(function (l) {
      if (/^\s*\|.*\|\s*$/.test(l)) { table.push(l); return; }
      flushTable();
      var m = l.match(/^\s*[-*•]\s+(.*)/);
      if (m) { if (!inList) { out.push('<ul>'); inList = true; } out.push('<li>' + inline(m[1]) + '</li>'); return; }
      if (inList) { out.push('</ul>'); inList = false; }
      var h = l.match(/^\s*#{1,4}\s+(.*)/);
      if (h) { out.push('<strong>' + inline(h[1]) + '</strong>'); return; }
      out.push(inline(l) + '<br>');
    });
    flushTable(); if (inList) out.push('</ul>');
    return out.join('\n').replace(/(<br>\s*){3,}/g, '<br><br>');
  }

  var Copilot = {};

  /* ---------- Product / category edit: Generate with AI ---------- */
  Copilot.catalog = function (cfg) {
    var modal = document.getElementById('ainative-copilot-modal');
    if (!modal) return;
    var fieldMap = cfg.entityType === 'category'
      ? { description: 'group_5description', meta_title: 'group_5meta_title', meta_keywords: 'group_5meta_keywords', meta_description: 'group_5meta_description' }
      : { name: 'name', short_description: 'short_description', description: 'description', meta_title: 'meta_title', meta_keyword: 'meta_keyword', meta_description: 'meta_description' };

    function findInput(field) {
      var id = fieldMap[field];
      var el = id && document.getElementById(id);
      if (!el && cfg.entityType === 'category') { el = document.querySelector('[name="general[' + field + ']"]'); }
      if (!el) { el = document.querySelector('[name="product[' + field + ']"]') || document.querySelector('[name="' + field + '"]'); }
      return el;
    }
    function setValue(field, value) {
      var el = findInput(field);
      if (!el) return false;
      if (global.tinyMCE && el.id && tinyMCE.get(el.id)) { tinyMCE.get(el.id).setContent(value); }
      el.value = value;
      el.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    }

    // inject a button next to the name field (or into the page header as fallback)
    var anchor = findInput(cfg.entityType === 'category' ? 'description' : 'name');
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'ainative-inline-btn'; btn.textContent = cfg.labels.button;
    btn.addEventListener('click', function () { modal.hidden = false; });
    if (anchor && anchor.parentNode) { anchor.parentNode.appendChild(btn); }
    else { var head = document.querySelector('.content-header .form-buttons'); if (head) head.insertBefore(btn, head.firstChild); }

    modal.querySelectorAll('[data-ainative-close]').forEach(function (b) { b.addEventListener('click', function () { modal.hidden = true; }); });
    var status = document.getElementById('ainative-status'), preview = document.getElementById('ainative-preview'), applyBtn = modal.querySelector('[data-ainative-apply]');
    var last = {};

    var ref = document.getElementById('ainative_ref'), refId = document.getElementById('ainative_ref_id'), refList = document.getElementById('ainative_ref_list');
    if (ref) {
      var t;
      ref.addEventListener('input', function () {
        refId.value = '';
        clearTimeout(t);
        t = setTimeout(function () {
          post(cfg.searchUrl, { q: ref.value }, cfg.formKey).then(function (j) {
            refList.innerHTML = j.items.map(function (i) { return '<option data-id="' + i.id + '" value="' + esc(i.label) + '">'; }).join('');
            var hit = j.items.filter(function (i) { return i.label === ref.value; })[0];
            if (hit) refId.value = hit.id;
          }).catch(function () {});
        }, 250);
      });
      ref.addEventListener('change', function () { var o = refList.querySelector('option[value="' + ref.value.replace(/"/g, '\\"') + '"]'); if (o) refId.value = o.getAttribute('data-id'); });
    }

    modal.querySelector('[data-ainative-generate]').addEventListener('click', function () {
      var fields = Array.from(modal.querySelectorAll('input[name="ainative_fields"]:checked')).map(function (c) { return c.value; });
      if (!fields.length) { status.textContent = 'Pick at least one field.'; status.className = 'ainative-status err'; return; }
      status.textContent = cfg.labels.working; status.className = 'ainative-status'; preview.hidden = true; applyBtn.hidden = true;
      var nameEl = findInput('name'), skuEl = document.getElementById('sku');
      post(cfg.generateUrl, {
        type: cfg.entityType, id: cfg.entityId, store: cfg.storeId, fields: fields.join(','),
        tone: document.getElementById('ainative_tone').value, hints: document.getElementById('ainative_hints').value,
        reference_product_id: refId ? refId.value : '', name: nameEl ? nameEl.value : '', sku: skuEl ? skuEl.value : ''
      }, cfg.formKey).then(function (j) {
        last = j.fields || {};
        status.textContent = 'Done (' + (j.tokens || 0) + ' tokens).';
        preview.innerHTML = Object.keys(last).map(function (f) {
          var cur = findInput(f); var curVal = cur ? cur.value : '';
          return '<div class="ainative-field" data-field="' + esc(f) + '"><div class="ainative-field__head"><label><input type="checkbox" checked data-ainative-pick> <strong>' + esc(f) + '</strong></label></div>'
            + '<div class="ainative-compare"><div><label>' + esc(cfg.labels.current) + '</label><div class="ainative-current">' + (esc(curVal) || '<i>—</i>') + '</div></div>'
            + '<div><label>' + esc(cfg.labels.proposed) + '</label><textarea rows="' + (f === 'description' ? 10 : 3) + '" data-ainative-value>' + esc(last[f]) + '</textarea></div></div></div>';
        }).join('');
        preview.hidden = false; applyBtn.hidden = false;
      }).catch(function (e) { status.textContent = e.message; status.className = 'ainative-status err'; });
    });

    applyBtn.addEventListener('click', function () {
      var n = 0;
      preview.querySelectorAll('.ainative-field').forEach(function (box) {
        if (!box.querySelector('[data-ainative-pick]').checked) return;
        if (setValue(box.getAttribute('data-field'), box.querySelector('[data-ainative-value]').value)) n++;
      });
      status.textContent = n + ' field(s) applied to the form. Save the ' + cfg.entityType + ' to persist.';
      modal.hidden = true;
    });
  };

  /* ---------- Order view: reply draft ---------- */
  Copilot.orderReply = function (cfg) {
    var gen = document.getElementById('ainative-reply-generate'); if (!gen) return;
    var intent = document.getElementById('ainative-reply-intent'), out = document.getElementById('ainative-reply-text'), status = document.getElementById('ainative-reply-status'), actions = document.getElementById('ainative-reply-actions');
    gen.addEventListener('click', function () {
      status.textContent = 'Drafting…'; status.className = 'ainative-status';
      post(cfg.url, { order_id: cfg.orderId, intent: intent.value }, cfg.formKey).then(function (j) {
        out.value = j.text; out.hidden = false; actions.hidden = false; status.textContent = 'Draft ready (' + (j.tokens || 0) + ' tokens). Review before sending.';
      }).catch(function (e) { status.textContent = e.message; status.className = 'ainative-status err'; });
    });
    document.getElementById('ainative-reply-copy').addEventListener('click', function () { out.select(); document.execCommand('copy'); status.textContent = 'Copied.'; });
    document.getElementById('ainative-reply-to-comment').addEventListener('click', function () {
      var c = document.getElementById('history_comment');
      if (c) { c.value = out.value; c.scrollIntoView({ behavior: 'smooth' }); c.focus(); status.textContent = 'Inserted into the order comment form — tick "Notify customer" to e-mail it.'; }
      else { status.textContent = 'Order comment form not found; use Copy.'; }
    });
  };

  /* ---------- Ask your store ---------- */
  Copilot.ask = function (cfg) {
    var thread = document.getElementById('ainative-ask-thread'), input = document.getElementById('ainative-ask-input'), form = document.getElementById('ainative-ask-form'), send = document.getElementById('ainative-ask-send'), hist = document.getElementById('ainative-ask-history');
    if (!thread) return;
    var conversationId = 0, busy = false;

    function bubble(role, html) {
      var d = document.createElement('div'); d.className = 'ainative-msg ainative-msg--' + role;
      d.innerHTML = '<div class="ainative-msg__body">' + html + '</div>';
      thread.appendChild(d); thread.scrollTop = thread.scrollHeight; return d;
    }
    function renderAssistant(j) {
      var html = md(j.text);
      if (j.tools && j.tools.length) {
        html += '<div class="ainative-tools">' + esc(cfg.labels.used) + ': ' + j.tools.map(function (t) { return '<code title="' + esc(JSON.stringify(t.arguments)) + '">' + esc(t.name) + (t.is_error ? ' (failed)' : '') + '</code>'; }).join(' ') + '</div>';
      }
      (j.pending || []).forEach(function (p) {
        html += '<div class="ainative-pending"><strong>' + esc(cfg.labels.proposed) + ':</strong> <code>' + esc(p.name) + '</code><pre>' + esc(JSON.stringify(p.arguments, null, 1)) + '</pre>'
          + '<button type="button" class="scalable save" data-confirm="' + esc(p.key) + '"><span><span>' + esc(cfg.labels.confirm) + '</span></span></button> '
          + '<button type="button" class="scalable" data-dismiss><span><span>' + esc(cfg.labels.dismiss) + '</span></span></button></div>';
      });
      var b = bubble('assistant', html);
      b.querySelectorAll('[data-confirm]').forEach(function (btn) { btn.addEventListener('click', function () { btn.disabled = true; submit('', btn.getAttribute('data-confirm')); }); });
      b.querySelectorAll('[data-dismiss]').forEach(function (btn) { btn.addEventListener('click', function () { btn.closest('.ainative-pending').remove(); }); });
    }
    function submit(message, confirmKey) {
      if (busy) return; busy = true; send.disabled = true;
      if (message) bubble('user', esc(message));
      var thinking = bubble('assistant', '<i>' + esc(cfg.labels.thinking) + '</i>');
      post(cfg.sendUrl, { message: message, conversation_id: conversationId, confirm: confirmKey || '' }, cfg.formKey).then(function (j) {
        thinking.remove(); conversationId = j.conversation_id; renderAssistant(j); loadHistory();
      }).catch(function (e) { thinking.querySelector('.ainative-msg__body').innerHTML = '<span style="color:#d40707">' + esc(e.message) + '</span>'; })
        .finally(function () { busy = false; send.disabled = false; input.focus(); });
    }
    form.addEventListener('submit', function () { var v = input.value.trim(); if (!v) return; input.value = ''; submit(v); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.dispatchEvent(new Event('submit')); } });
    document.querySelectorAll('[data-ainative-suggest]').forEach(function (c) { c.addEventListener('click', function () { input.value = c.textContent; form.dispatchEvent(new Event('submit')); }); });
    document.getElementById('ainative-ask-new').addEventListener('click', function () { conversationId = 0; thread.innerHTML = ''; input.focus(); });

    function loadHistory() {
      fetch(cfg.historyUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        hist.innerHTML = (j.conversations || []).map(function (c) { return '<li data-id="' + c.id + '">' + esc(c.title || '(untitled)') + '<br><small>' + esc(c.updated_at || '') + ' · ' + c.messages + ' msgs</small></li>'; }).join('');
        hist.querySelectorAll('li').forEach(function (li) { li.addEventListener('click', function () { load(parseInt(li.getAttribute('data-id'), 10)); }); });
      }).catch(function () {});
    }
    function load(id) {
      fetch(cfg.loadUrl + (cfg.loadUrl.indexOf('?') > -1 ? '&' : '?') + 'id=' + id, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        conversationId = j.conversation_id || 0; thread.innerHTML = '';
        (j.messages || []).forEach(function (m) { if (m.role === 'user') bubble('user', esc(m.content)); else renderAssistant({ text: m.content, tools: (m.cards && m.cards.tools) || [], pending: [] }); });
      });
    }
    loadHistory();
  };

  global.AiNativeCopilot = Copilot;
})(window);
