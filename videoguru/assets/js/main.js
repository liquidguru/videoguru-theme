/* VideoGuru: instant filtering, search, Random, hover previews, back-to-top. No dependencies. */
(function () {
  var btn = document.getElementById('to-top');
  if (!btn) return;
  var shown = false, ticking = false;
  function update() {
    var want = window.scrollY > 600;
    if (want !== shown) { shown = want; btn.hidden = false; btn.classList.toggle('show', want); }
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  btn.addEventListener('click', function () {
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: calm ? 'auto' : 'smooth' });
  });
  update();
})();

(function () {
  var grid = document.getElementById('grid');
  if (!grid) return;

  var cards = Array.prototype.slice.call(grid.querySelectorAll('.card'));
  var whole = grid.dataset.all === '1';   // the whole wall is on this page, so we can filter in the browser

  // Ordering is done here, in the browser, so page caches can't freeze a "random" order.
  // Crawlers and no-JS visitors get the server's order (newest first unless the owner chose otherwise).
  function arrange(mode) {
    var list = cards.slice();
    if (mode === 'random') {
      for (var i = list.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1)), t = list[i];
        list[i] = list[j]; list[j] = t;
      }
    } else if (mode === 'oldest') list.sort(function (a, b) { return a.dataset.date - b.dataset.date; });
    else if (mode === 'title_asc' || mode === 'title_desc') {
      // Sort by the first letter or number, so a title starting with a quotation mark files under its first word.
      var key = function (c) { return c.dataset.title.replace(/^[^\p{L}\p{N}]+/u, ''); };
      var dir = mode === 'title_asc' ? 1 : -1;
      list.sort(function (a, b) { return dir * key(a).localeCompare(key(b), undefined, { numeric: true, sensitivity: 'base' }); });
    }
    else list.sort(function (a, b) { return b.dataset.date - a.dataset.date; });
    var frag = document.createDocumentFragment();
    list.forEach(function (c, n) {
      var im = c.querySelector('img');
      if (im) { im.loading = n < 4 ? 'eager' : 'lazy'; im.setAttribute('fetchpriority', n < 4 ? 'high' : 'auto'); }
      frag.appendChild(c);
    });
    grid.appendChild(frag);
  }
  if (grid.dataset.order === 'random' && document.body.classList.contains('home') && !location.search) arrange('random');

  // Visitor's Sort menu (home page, whole wall on one page).
  var sort = document.getElementById('sort');
  if (sort) {
    sort.hidden = false;
    sort.addEventListener('change', function () { arrange(sort.value); window.scrollTo({ top: 0 }); });
  }

  var chips = document.getElementById('chips');
  var q = document.getElementById('q');
  var count = document.getElementById('count');
  var empty = document.getElementById('empty');
  var group = 'all';
  var one = count ? count.textContent.replace(/^[\d.,\s]+/, '') : 'videos';

  function visible() { return cards.filter(function (c) { return !c.hidden; }); }

  function apply() {
    var term = q ? q.value.trim().toLowerCase() : '';
    var n = 0;
    cards.forEach(function (c) {
      var ok = (group === 'all' || (' ' + c.dataset.groups + ' ').indexOf(' ' + group + ' ') !== -1) && (!term || c.dataset.search.indexOf(term) !== -1);
      c.hidden = !ok;
      if (ok) n++;
    });
    if (count) count.textContent = n + ' ' + (n === 1 && /s$/.test(one) ? one.slice(0, -1) : one);
    if (empty) empty.hidden = n !== 0;
  }

  if (chips) {
    chips.addEventListener('click', function (e) {
      var b = e.target.closest('button.chip');
      if (!b) return;
      group = b.dataset.g;
      chips.querySelectorAll('button.chip').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      apply();
    });
  }
  if (q && whole) {
    q.addEventListener('input', apply);
    if (q.value) apply();
    // Everything is already on the page, so Enter just filters; no round trip to the server.
    if (q.form) q.form.addEventListener('submit', function (e) { e.preventDefault(); });
  }

  var rand = document.getElementById('rand');
  if (rand) rand.addEventListener('click', function () {
    var v = visible();
    if (v.length) location.href = v[Math.floor(Math.random() * v.length)].href;
  });

  // Hover preview: a muted, looping player after a short dwell. Mouse only.
  var conn = navigator.connection || {};
  var ok = grid.dataset.hover === '1' && window.matchMedia('(hover: hover)').matches && !conn.saveData && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!ok) return;
  var timer;
  function previewSrc(c) {
    if (c.dataset.vimeo) return 'https://player.vimeo.com/video/' + c.dataset.vimeo + '?background=1&autoplay=1&muted=1&loop=1&dnt=1' + (c.dataset.h ? '&h=' + c.dataset.h : '');
    if (c.dataset.youtube) return 'https://www.youtube-nocookie.com/embed/' + c.dataset.youtube + '?autoplay=1&mute=1&controls=0&loop=1&playlist=' + c.dataset.youtube + '&playsinline=1&modestbranding=1&rel=0&disablekb=1';
    return '';
  }
  document.addEventListener('pointerover', function (e) {
    if (e.pointerType === 'touch') return;
    var c = e.target.closest('.card[data-vimeo],.card[data-youtube]');
    if (!c || c.querySelector('iframe')) return;
    clearTimeout(timer);
    timer = setTimeout(function () {
      var f = document.createElement('iframe');
      f.src = previewSrc(c);
      f.allow = 'autoplay';
      f.title = '';
      f.tabIndex = -1;
      f.onload = function () { setTimeout(function () { c.classList.add('live'); }, 600); };
      c.insertBefore(f, c.querySelector('.dur') || c.querySelector('.meta'));
    }, 450);
  });
  document.addEventListener('pointerout', function (e) {
    var c = e.target.closest('.card[data-vimeo],.card[data-youtube]');
    if (!c || c.contains(e.relatedTarget)) return;
    clearTimeout(timer);
    c.classList.remove('live');
    var f = c.querySelector('iframe');
    if (f) f.remove();
  });
})();
