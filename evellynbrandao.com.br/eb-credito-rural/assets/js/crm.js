/* EB Crédito Rural — CRM: quadro Kanban com arrastar e soltar nativo (HTML5) e fallback por seleção (teclado). */
(function () {
  'use strict';
  var cfg = window.ebcrCrm || {};
  var i18n = cfg.i18n || {};
  var board = document.querySelector('[data-kanban]');
  if (!board || !cfg.root) { return; }

  var toast = document.querySelector('[data-toast]');
  var toastTimer = null;
  function notify(msg, isError) {
    if (!toast) { return; }
    toast.textContent = msg;
    toast.classList.toggle('is-error', !!isError);
    toast.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toast.hidden = true; }, isError ? 5000 : 2500);
  }

  function updateCounts() {
    board.querySelectorAll('.ebcr-kanban-col').forEach(function (col) {
      var n = col.querySelectorAll('.ebcr-card').length;
      var badge = col.querySelector('[data-count]');
      if (badge) { badge.textContent = String(n); }
    });
  }

  function saveStage(card, stage) {
    card.classList.add('is-saving');
    return window.fetch(cfg.root + 'crm/contacts/' + encodeURIComponent(card.getAttribute('data-contact')) + '/stage', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify({ stage: stage })
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        if (!res.ok || !json || !json.ok) {
          throw new Error(json && json.message ? json.message : (i18n.error || 'Erro'));
        }
        return json;
      });
    }).then(function (json) {
      card.classList.remove('is-saving');
      var sel = card.querySelector('[data-move]');
      if (sel) { sel.value = json.stage; }
      notify(i18n.moved || 'OK', false);
      return json;
    }, function (err) {
      card.classList.remove('is-saving');
      notify(err && err.message ? err.message : (i18n.error || 'Erro'), true);
      throw err;
    });
  }

  function moveCard(card, zone) {
    var from = card.parentNode;
    var col = zone.closest('[data-stage]');
    var stage = col ? col.getAttribute('data-stage') : '';
    if (!stage || from === zone) { return; }
    var next = zone.querySelector('.ebcr-card');
    zone.insertBefore(card, next);
    updateCounts();
    saveStage(card, stage).catch(function () {
      from.appendChild(card);
      updateCounts();
    });
  }

  var dragged = null;
  board.querySelectorAll('.ebcr-card[draggable]').forEach(function (card) {
    card.addEventListener('dragstart', function (e) {
      dragged = card;
      card.classList.add('is-dragging');
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', card.getAttribute('data-contact')); } catch (_) { /* IE */ }
      }
    });
    card.addEventListener('dragend', function () {
      card.classList.remove('is-dragging');
      board.querySelectorAll('.is-over').forEach(function (z) { z.classList.remove('is-over'); });
      dragged = null;
    });
    // Fallback acessível: seleção "Mover para" (visível só com JS).
    var sel = card.querySelector('[data-move]');
    if (sel) {
      sel.hidden = false;
      sel.addEventListener('change', function () {
        var target = board.querySelector('[data-stage="' + sel.value.replace(/"/g, '') + '"] [data-dropzone]');
        if (target) { moveCard(card, target); }
      });
      sel.addEventListener('click', function (e) { e.stopPropagation(); });
    }
  });

  board.querySelectorAll('[data-dropzone]').forEach(function (zone) {
    zone.addEventListener('dragover', function (e) {
      if (!dragged) { return; }
      e.preventDefault();
      if (e.dataTransfer) { e.dataTransfer.dropEffect = 'move'; }
      zone.classList.add('is-over');
    });
    zone.addEventListener('dragleave', function (e) {
      if (!zone.contains(e.relatedTarget)) { zone.classList.remove('is-over'); }
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('is-over');
      if (!dragged) { return; }
      moveCard(dragged, zone);
      dragged.classList.remove('is-dragging');
      dragged = null;
    });
  });

  updateCounts();
})();
