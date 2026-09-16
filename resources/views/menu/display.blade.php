<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu · Xiway Coffee</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --paper: #FFFFFF;
  --ink: #1C1612;
  --muted: rgba(28,22,18,.45);
  --line: rgba(28,22,18,.14);
  --star: #A67C0A;
  --brass: #9A7A3A;
  --scale: 1;
}

*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden}

body{
  background: var(--paper);
  color: var(--ink);
  font-family: 'Source Sans 3', sans-serif;
  font-variant-numeric: tabular-nums;
  -webkit-font-smoothing: antialiased;
  cursor: none;
}

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
  width: min(40vw, 480px);
  height: auto;
  opacity: .05;
  user-select: none;
  animation: watermark-drift 18s ease-in-out infinite;
}

.stage{
  position:relative; z-index:1;
  height:100vh;
  display:flex; flex-direction:column;
  padding:2.4vh 3.2vw 1.8vh;
}

.pages{position:relative; flex:1; min-height:0; overflow:hidden}
.page{
  --scale: 1;
  position:absolute; inset:0;
  display:grid;
  grid-template-columns: 1.06fr 1fr 1fr;
  gap:0 3.2vw;
  align-content:start;
  opacity:0; visibility:hidden;
}
.page.is-active{opacity:1; visibility:visible}

.column{
  min-width:0;
  display:flex; flex-direction:column;
  gap:calc(1.9vh * var(--scale));
}

.group{min-width:0}
.group-head{
  display:flex;
  align-items:center;
  gap:.55vw;
  padding-bottom:.5vh;
  margin-bottom:.35vh;
  border-bottom:1px solid transparent;
  background:
    linear-gradient(90deg,
      rgba(28,22,18,.08) 0%,
      rgba(154,122,58,.45) 42%,
      rgba(28,22,18,.08) 100%)
      left bottom / 220% 1px no-repeat;
}
.group-head h2{
  font-family:'Cormorant Garamond', serif;
  font-size:calc(1.42vw * var(--scale));
  font-weight:600;
  font-style:italic;
  letter-spacing:.03em;
  line-height:1;
  color: var(--ink);
}
.group-head .cat-icon{
  flex:none;
  width:calc(1.15vw * var(--scale));
  height:calc(1.15vw * var(--scale));
  color: var(--brass);
  opacity:.85;
}

.items{list-style:none}
.item{
  display:flex; align-items:baseline; gap:.35vw;
  padding:calc(.34vh * var(--scale)) 0;
}
.item .name{
  font-size:calc(.96vw * var(--scale));
  font-weight:500;
  letter-spacing:.015em;
  white-space:nowrap;
  max-width:66%;
  overflow:hidden;
  text-overflow:ellipsis;
}
.item .name .ch{
  display:inline-block;
  color: var(--ink);
}
.item .leader{
  flex:1; min-width:.6vw; height:1px;
  transform:translateY(-.28em);
  background-image: radial-gradient(circle, rgba(28,22,18,.2) .65px, transparent 1px);
  background-size:.32vw 2px; background-repeat:repeat-x;
  transform-origin: left center;
  opacity:.85;
}
.item .price{
  flex:none;
  font-size:calc(.96vw * var(--scale));
  font-weight:600;
  letter-spacing:.015em;
  min-width:2.3em;
  text-align:right;
  color: var(--ink);
}
.item .price sup{
  font-size:.55em; font-weight:600; vertical-align:.32em;
  margin-left:.05em; color:var(--muted);
}

.sig-star{
  flex:none;
  color:var(--star);
  font-size:.76em;
  line-height:1;
}

.reveal{
  opacity:0;
  transform: translateY(.7vh);
}
.page.is-ready .reveal{
  animation: rise 1s cubic-bezier(.16,1,.3,1) forwards;
}
.page.is-ready .item.reveal .leader{
  animation: leader-draw 1.1s cubic-bezier(.16,1,.3,1) both;
  animation-delay: inherit;
}

.page.is-alive .reveal{
  opacity:1;
  transform:none;
  animation:none;
}
.page.is-alive .item .name .ch{
  animation: letter-shimmer 9s ease-in-out infinite;
  animation-delay: calc(var(--i, 0) * 160ms + var(--c, 0) * 55ms);
}
.page.is-alive .item .leader{
  animation: leader-soft 9s ease-in-out infinite;
  animation-delay: calc(var(--i, 0) * 160ms);
}
.page.is-alive .group-head{
  animation: rule-glide 10s linear infinite;
  animation-delay: calc(var(--g, 0) * 1.1s);
}
.page.is-alive .item.is-signature .sig-star{
  animation: star-pulse 4.5s ease-in-out infinite;
  animation-delay: calc(var(--i, 0) * 180ms);
}

.footer{
  flex:none;
  display:flex; align-items:center; justify-content:space-between;
  gap:1.5rem;
  padding-top:1.1vh; margin-top:1vh;
  border-top:1px solid var(--line);
  font-size:.62vw;
  font-weight:600; letter-spacing:.24em; text-transform:uppercase;
  color:var(--muted);
  opacity:0;
  animation: rise 1.1s cubic-bezier(.16,1,.3,1) .4s forwards;
}
.footer.is-alive{
  animation: footer-glow 8s ease-in-out infinite;
  opacity:1;
}
.legend{display:flex; align-items:center; gap:.45vw}
.legend .sig-star{font-size:1em; animation: star-pulse 4.5s ease-in-out infinite}

@keyframes rise{
  from{opacity:0; transform:translateY(.7vh)}
  to{opacity:1; transform:none}
}
@keyframes watermark-drift{
  0%,100%{opacity:.04; transform:scale(1)}
  50%{opacity:.07; transform:scale(1.02)}
}
@keyframes star-pulse{
  0%,100%{opacity:.55; filter:brightness(1)}
  50%{opacity:1; filter:brightness(1.15)}
}
@keyframes leader-draw{
  from{opacity:0; transform:translateY(-.28em) scaleX(.2)}
  to{opacity:.85; transform:translateY(-.28em) scaleX(1)}
}
@keyframes letter-shimmer{
  0%,76%,100%{
    opacity:.78;
    color: var(--ink);
    text-shadow: none;
  }
  84%{
    opacity:1;
    color: var(--brass);
    text-shadow: 0 0 .35em rgba(154,122,58,.28);
  }
  92%{
    opacity:.95;
    color: var(--ink);
    text-shadow: none;
  }
}
@keyframes leader-soft{
  0%,76%,100%{opacity:.55}
  84%{opacity:1}
  92%{opacity:.75}
}
@keyframes rule-glide{
  0%{background-position: 120% bottom}
  100%{background-position: -120% bottom}
}
@keyframes footer-glow{
  0%,100%{opacity:.62}
  50%{opacity:.92}
}

@media (prefers-reduced-motion:reduce){
  .watermark img,
  .page.is-ready .reveal,
  .page.is-ready .item.reveal .leader,
  .page.is-alive .item .name .ch,
  .page.is-alive .item .leader,
  .page.is-alive .group-head,
  .page.is-alive .item.is-signature .sig-star,
  .legend .sig-star,
  .footer,
  .footer.is-alive{
    animation:none!important;
  }
  .reveal,.footer{opacity:1; transform:none}
  .page.is-alive .group-head{background-position: center bottom}
}
</style>
</head>
<body>

<div class="watermark" aria-hidden="true">
  <img src="{{ asset('images/logo/xiway-logo.png') }}" alt="">
</div>

<div class="stage">

  <main class="pages" id="pages"></main>

  <footer class="footer">
    <div class="legend"><span class="sig-star" aria-hidden="true">★</span> Menu andalan</div>
    <div>Harga dalam ribuan rupiah</div>
    <div>Specialty Arabika Gayo</div>
  </footer>

</div>

<script>
const MENU = @json($menu);
const pagesEl = document.getElementById('pages');
const esc = s => String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
const letters = (text) => [...String(text)].map((ch, i) =>
  `<span class="ch" style="--c:${i}">${ch === ' ' ? '&nbsp;' : esc(ch)}</span>`
).join('');

const svg = (paths) => `
  <svg class="cat-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
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
  if (key.includes('paket') || key.includes('bundle')) {
    return svg(`
      <path d="M4 8h16v12H4z"/>
      <path d="M4 12h16"/>
      <path d="M9 8V6a3 3 0 0 1 6 0v2"/>`);
  }
  // Coffee default
  return svg(`
    <path d="M4 9h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V9z"/>
    <path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16"/>
    <path d="M8 3c0 1 .5 1.5 0 2.5S7 7 7 8"/>
    <path d="M11 3c0 1 .5 1.5 0 2.5S10 7 10 8"/>`);
};

MENU.forEach((page) => {
  const el = document.createElement('section');
  el.className = 'page';
  el.innerHTML = (page.columns || []).map(col => `
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
    </div>`).join('');
  pagesEl.appendChild(el);
});

const pageEls = [...document.querySelectorAll('.page')];
const page = pageEls[0];

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

  let guard = 140;
  while (contentHeight(el) > room && scale > 0.42 && guard--){
    scale = +(scale - 0.02).toFixed(2);
    el.style.setProperty('--scale', scale);
    void el.offsetHeight;
  }

  guard = 40;
  while (contentHeight(el) < room * 0.94 && scale < 1.15 && guard--){
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
    node.style.animationDelay = Math.min(i * 36, 1400) + 'ms';
  });
  [...el.querySelectorAll('.item')].forEach((node, i) => {
    node.style.setProperty('--i', i);
  });
  [...el.querySelectorAll('.group')].forEach((node, i) => {
    node.querySelector('.group-head')?.style.setProperty('--g', i);
  });
  void el.offsetWidth;
  el.classList.add('is-ready');

  const entranceMs = Math.min(nodes.length * 36, 1400) + 1100;
  setTimeout(() => {
    el.classList.remove('is-ready');
    el.classList.add('is-alive');
    document.querySelector('.footer')?.classList.add('is-alive');
  }, entranceMs);
}

function show(){
  if (!page) return;
  page.classList.add('is-active');
  fit(page);
}

async function boot(){
  show();
  try {
    if (document.fonts?.ready) await document.fonts.ready;
  } catch (_) {}
  requestAnimationFrame(() => requestAnimationFrame(() => {
    fit(page);
    setTimeout(() => {
      fit(page);
      playReveal(page);
    }, 50);
  }));
}

boot();

setTimeout(() => location.reload(), 6 * 60 * 60 * 1000);
addEventListener('resize', () => fit(page));
if (document.fonts?.addEventListener) {
  document.fonts.addEventListener('loadingdone', () => fit(page));
}
</script>
</body>
</html>
