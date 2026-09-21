<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu · Xiway Coffee</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --paper: #f7f3ec;
  --paper-deep: #efe8dc;
  --ink: #1a1511;
  --ink-soft: rgba(26,21,17,.72);
  --muted: rgba(26,21,17,.42);
  --line: rgba(26,21,17,.10);
  --brass: #8b7355;
  --brass-soft: rgba(139,115,85,.55);
  --star: #8b7355;
  --scale: 1;
}

*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden}

body{
  background:
    radial-gradient(ellipse 90% 70% at 50% 40%, transparent 40%, rgba(26,21,17,.06) 100%),
    linear-gradient(165deg, #faf7f1 0%, var(--paper) 45%, var(--paper-deep) 100%);
  color: var(--ink);
  font-family: 'Source Sans 3', sans-serif;
  font-variant-numeric: tabular-nums;
  -webkit-font-smoothing: antialiased;
  cursor: none;
}

.frame{
  position:fixed;
  inset:1.4vh 1.6vw;
  z-index:0;
  pointer-events:none;
  border:1px solid rgba(139,115,85,.22);
  border-radius:2px;
}
.frame::before,
.frame::after{
  content:'';
  position:absolute;
  width:1.1vw;
  height:1.1vw;
  border-color: var(--brass-soft);
  border-style: solid;
}
.frame::before{ top:-1px; left:-1px; border-width:1.5px 0 0 1.5px; }
.frame::after{ bottom:-1px; right:-1px; border-width:0 1.5px 1.5px 0; }

.watermark{
  position:fixed;
  inset:0;
  z-index:0;
  pointer-events:none;
  display:flex;
  align-items:center;
  justify-content:center;
}
.watermark img{
  width: min(34vw, 420px);
  height: auto;
  opacity: .035;
  user-select: none;
  filter: grayscale(1);
}

.stage{
  position:relative; z-index:1;
  height:100vh;
  display:flex; flex-direction:column;
  padding:3.2vh 3.4vw 2.6vh;
}

.pages{position:relative; flex:1; min-height:0; overflow:hidden}

.page{
  --scale: 1;
  position:absolute; inset:0;
  display:flex;
  flex-direction:column;
  opacity:0; visibility:hidden;
  transition: opacity .55s cubic-bezier(.22,1,.36,1);
}
.page.is-active{opacity:1; visibility:visible}

.page-grid{
  flex:1;
  min-height:0;
  display:grid;
  grid-template-columns: repeat(var(--cols, 2), minmax(0, 1fr));
  gap:0 3.2vw;
  align-content:start;
}

.column{
  min-width:0;
  display:flex; flex-direction:column;
  gap:calc(2vh * var(--scale));
}

.group{min-width:0}

.group-head{
  display:flex;
  align-items:center;
  gap:.5vw;
  padding-bottom:.7vh;
  margin-bottom:.55vh;
  border-bottom:1px solid var(--line);
  position:relative;
}
.group-head::after{
  content:'';
  position:absolute;
  left:0; bottom:-1px;
  width:2.2vw;
  height:1px;
  background: var(--brass-soft);
}
.group-head h2{
  font-family:'Cormorant Garamond', serif;
  font-size:calc(1.85vw * var(--scale));
  font-weight:600;
  font-style:italic;
  letter-spacing:.04em;
  line-height:1.05;
  color: var(--ink);
}
.group-head .cat-icon{
  flex:none;
  width:calc(1.15vw * var(--scale));
  height:calc(1.15vw * var(--scale));
  color: var(--brass);
  opacity:.7;
  margin-left:.15vw;
}

.items{list-style:none}
.item{
  display:flex; align-items:baseline; gap:.45vw;
  padding:calc(.55vh * var(--scale)) 0;
}
.item .name{
  font-size:calc(1.28vw * var(--scale));
  font-weight:500;
  letter-spacing:.015em;
  white-space:nowrap;
  max-width:68%;
  overflow:hidden;
  text-overflow:ellipsis;
  color: var(--ink-soft);
}
.item .name .ch{
  display:inline-block;
  color: inherit;
}
.item.is-signature .name{
  color: var(--ink);
  font-weight:600;
}
.item .leader{
  flex:1; min-width:.8vw; height:1px;
  transform:translateY(-.32em);
  background-image: linear-gradient(90deg, transparent, var(--line) 8%, var(--line) 92%, transparent);
  opacity:.9;
  transform-origin: left center;
}
.item .price{
  flex:none;
  font-size:calc(1.22vw * var(--scale));
  font-weight:600;
  letter-spacing:.04em;
  min-width:2.4em;
  text-align:right;
  color: var(--ink);
}
.item .price sup{
  font-size:.52em; font-weight:500; vertical-align:.35em;
  margin-left:.06em; color:var(--muted);
  letter-spacing:0;
}

.sig-star{
  flex:none;
  color: var(--brass);
  font-size:.72em;
  line-height:1;
  opacity:.9;
  transform: translateY(-.08em);
}

.reveal{
  opacity:0;
  transform: translateY(.55vh);
}
.page.is-ready .reveal{
  animation: rise .95s cubic-bezier(.16,1,.3,1) forwards;
}
.page.is-ready .item.reveal .leader{
  animation: leader-draw 1s cubic-bezier(.16,1,.3,1) both;
  animation-delay: inherit;
}

.page.is-alive .reveal{
  opacity:1;
  transform:none;
  animation:none;
}

@keyframes rise{
  from{opacity:0; transform:translateY(.55vh)}
  to{opacity:1; transform:none}
}
@keyframes leader-draw{
  from{opacity:0; transform:translateY(-.32em) scaleX(.15)}
  to{opacity:.9; transform:translateY(-.32em) scaleX(1)}
}

@media (prefers-reduced-motion:reduce){
  .page.is-ready .reveal,
  .page.is-ready .item.reveal .leader{
    animation:none!important;
  }
  .reveal{opacity:1; transform:none}
  .page{transition:none}
}
</style>
</head>
<body>

<div class="frame" aria-hidden="true"></div>

<div class="watermark" aria-hidden="true">
  <img src="{{ asset('images/logo/xiway-logo.png') }}" alt="">
</div>

<div class="stage">
  <main class="pages" id="pages"></main>
</div>

<script>
const MENU = @json($menu);
const FOCUS_URL = @json($focusUrl);
const pagesEl = document.getElementById('pages');
const AUTO_MS = 25000;
const POLL_MS = 1500;

const esc = s => String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
const letters = (text) => [...String(text)].map((ch, i) =>
  `<span class="ch" style="--c:${i}">${ch === ' ' ? '&nbsp;' : esc(ch)}</span>`
).join('');

const svg = (paths) => `
  <svg class="cat-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linecap="round" stroke-linejoin="round">
    ${paths}
  </svg>`;

const categoryIcon = (name) => {
  const key = String(name).toLowerCase();
  if (key.includes('makanan')) {
    return svg(`
      <path d="M4 11h16v2a6 6 0 0 1-6 6H10a6 6 0 0 1-6-6v-2z"/>
      <path d="M8 11V7m4 4V5m4 6V8"/>
      <path d="M4 20h16"/>`);
  }
  if (key.includes('snack')) {
    return svg(`
      <path d="M12 3c-2.5 3.5-6 5.8-6 10a6 6 0 0 0 12 0c0-4.2-3.5-6.5-6-10z"/>
      <path d="M10 14h4"/>`);
  }
  if (key.includes('mie') || key.includes('indomie')) {
    return svg(`
      <path d="M4 8c2 0 3-1.5 5-1.5S12 8 14 8s3-1.5 5-1.5"/>
      <path d="M4 12c2 0 3-1.5 5-1.5S12 12 14 12s3-1.5 5-1.5"/>
      <path d="M4 16c2 0 3-1.5 5-1.5S12 16 14 16s3-1.5 5-1.5"/>
      <path d="M7 19h10"/>`);
  }
  if (key.includes('tea') || key.includes('teh')) {
    return svg(`
      <path d="M5 9h10v7a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V9z"/>
      <path d="M15 11h1.8a2.2 2.2 0 0 1 0 4.4H15"/>
      <path d="M9 3v3M12 2v4"/>`);
  }
  if (key.includes('non coffee') || key.includes('non-coffee')) {
    return svg(`
      <path d="M8 4h8l-1 14H9L8 4z"/>
      <path d="M9 9h6"/>
      <path d="M10 21h4"/>`);
  }
  if (key.includes('xiway')) {
    return svg(`
      <path d="M12 3l1.8 5.5H20l-4.5 3.3 1.7 5.4L12 14.2 6.8 17.2l1.7-5.4L4 8.5h6.2L12 3z"/>`);
  }
  return svg(`
    <path d="M4 9h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V9z"/>
    <path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/>
    <path d="M8 3c0 1 .5 1.5 0 2.5S7 7 7 8"/>
    <path d="M11 3c0 1 .5 1.5 0 2.5S10 7 10 8"/>`);
};

MENU.forEach((page) => {
  const el = document.createElement('section');
  el.className = 'page';
  el.dataset.key = page.key || '';
  el.style.setProperty('--cols', Math.max(1, (page.columns || []).length));
  el.innerHTML = `
    <div class="page-grid">
      ${(page.columns || []).map(col => `
      <div class="column">
        ${col.map(g => `
          <div class="group">
            <div class="group-head reveal">
              <h2>${esc(g.name)}</h2>
              ${categoryIcon(g.name)}
            </div>
            <ul class="items">
              ${g.items.map(it => `
                <li class="item reveal ${it.star ? 'is-signature' : ''}">
                  <span class="name">${letters(it.name)}</span>
                  ${it.star ? '<span class="sig-star" aria-hidden="true">★</span>' : ''}
                  <span class="leader"></span>
                  <span class="price">${it.price}<sup>k</sup></span>
                </li>`).join('')}
            </ul>
          </div>`).join('')}
      </div>`).join('')}
    </div>`;
  pagesEl.appendChild(el);
});

const pageEls = [...document.querySelectorAll('.page')];
let index = 0;
let mode = @json($initialFocus['mode'] ?? 'auto');
let autoTimer = null;

function contentHeight(el){
  const cols = [...el.querySelectorAll('.column')];
  if (!cols.length) return el.scrollHeight;
  return Math.max(...cols.map(c => c.getBoundingClientRect().height), el.scrollHeight);
}

function fit(el){
  if (!el) return;
  let scale = 1;
  el.style.setProperty('--scale', scale);
  void el.offsetHeight;
  const room = pagesEl.clientHeight;
  if (room < 1) return;

  let guard = 160;
  while (contentHeight(el) > room && scale > 0.4 && guard--){
    scale = +(scale - 0.02).toFixed(2);
    el.style.setProperty('--scale', scale);
    void el.offsetHeight;
  }

  guard = 60;
  while (contentHeight(el) < room * 0.97 && scale < 1.55 && guard--){
    const next = +(scale + 0.02).toFixed(2);
    el.style.setProperty('--scale', next);
    void el.offsetHeight;
    if (contentHeight(el) > room) {
      el.style.setProperty('--scale', scale);
      break;
    }
    scale = next;
  }
}

function playReveal(el){
  if (!el) return;
  el.classList.remove('is-ready', 'is-alive');
  const nodes = [...el.querySelectorAll('.reveal')];
  nodes.forEach((node, i) => {
    node.style.animationDelay = Math.min(i * 42, 1200) + 'ms';
  });
  [...el.querySelectorAll('.item')].forEach((node, i) => {
    node.style.setProperty('--i', i);
  });
  [...el.querySelectorAll('.group')].forEach((node, i) => {
    node.querySelector('.group-head')?.style.setProperty('--g', i);
  });
  void el.offsetWidth;
  el.classList.add('is-ready');

  const entranceMs = Math.min(nodes.length * 42, 1200) + 1000;
  setTimeout(() => {
    el.classList.remove('is-ready');
    el.classList.add('is-alive');
  }, entranceMs);
}

function showIndex(i, animate = true){
  if (!pageEls.length) return;
  index = ((i % pageEls.length) + pageEls.length) % pageEls.length;
  pageEls.forEach((el, n) => el.classList.toggle('is-active', n === index));
  const el = pageEls[index];
  fit(el);
  if (animate) playReveal(el);
  else {
    el.classList.add('is-alive');
  }
}

function indexForMode(m){
  const i = pageEls.findIndex(el => el.dataset.key === m);
  return i >= 0 ? i : 0;
}

function applyMode(next){
  mode = next || 'auto';
  if (autoTimer) {
    clearInterval(autoTimer);
    autoTimer = null;
  }
  if (mode === 'auto') {
    showIndex(index, true);
    if (pageEls.length > 1) {
      autoTimer = setInterval(() => showIndex(index + 1, true), AUTO_MS);
    }
    return;
  }
  showIndex(indexForMode(mode), true);
}

async function pollFocus(){
  try {
    const res = await fetch(FOCUS_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
    if (!res.ok) return;
    const data = await res.json();
    const next = data.mode || 'auto';
    if (next !== mode) applyMode(next);
  } catch (_) {}
}

async function boot(){
  applyMode(mode);
  try {
    if (document.fonts?.ready) await document.fonts.ready;
  } catch (_) {}
  requestAnimationFrame(() => requestAnimationFrame(() => {
    fit(pageEls[index]);
    setTimeout(() => {
      fit(pageEls[index]);
      playReveal(pageEls[index]);
    }, 50);
  }));
  setInterval(pollFocus, POLL_MS);
}

boot();

setTimeout(() => location.reload(), 6 * 60 * 60 * 1000);
addEventListener('resize', () => fit(pageEls[index]));
if (document.fonts?.addEventListener) {
  document.fonts.addEventListener('loadingdone', () => fit(pageEls[index]));
}
</script>
</body>
</html>
