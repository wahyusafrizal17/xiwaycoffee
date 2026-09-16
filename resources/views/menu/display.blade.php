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
  --muted: rgba(28,22,18,.48);
  --line: rgba(28,22,18,.16);
  --star: #B8890E;
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
  width: min(42vw, 520px);
  height: auto;
  opacity: .07;
  user-select: none;
  animation: watermark-breathe 10s ease-in-out infinite;
}

.stage{
  position:relative; z-index:1;
  height:100vh;
  display:flex; flex-direction:column;
  padding:2.2vh 3vw 1.6vh;
}

.pages{position:relative; flex:1; min-height:0; overflow:hidden}
.page{
  --scale: 1;
  position:absolute; inset:0;
  display:grid;
  grid-template-columns: 1.06fr 1fr 1fr;
  gap:0 3vw;
  align-content:start;
  opacity:0; visibility:hidden;
}
.page.is-active{opacity:1; visibility:visible}

.column{
  min-width:0;
  display:flex; flex-direction:column;
  gap:calc(1.8vh * var(--scale));
}

.group{min-width:0}
.group-head{
  padding-bottom:.45vh;
  margin-bottom:.3vh;
  border-bottom:1px solid var(--line);
}
.group-head h2{
  font-family:'Cormorant Garamond', serif;
  font-size:calc(1.45vw * var(--scale));
  font-weight:600;
  font-style:italic;
  letter-spacing:.02em;
  line-height:1;
  color: var(--ink);
}

.items{list-style:none}
.item{
  display:flex; align-items:baseline; gap:.35vw;
  padding:calc(.36vh * var(--scale)) 0;
}
.item .name{
  font-size:calc(.98vw * var(--scale));
  font-weight:500;
  letter-spacing:.01em;
  white-space:nowrap;
  max-width:66%;
  overflow:hidden;
  text-overflow:ellipsis;
}
.item .name .ch{
  display:inline-block;
  will-change: transform, opacity;
}
.item .leader{
  flex:1; min-width:.6vw; height:1px;
  transform:translateY(-.28em);
  background-image: radial-gradient(circle, rgba(28,22,18,.22) .7px, transparent 1px);
  background-size:.34vw 2px; background-repeat:repeat-x;
  transform-origin: left center;
}
.item .price{
  flex:none;
  font-size:calc(.98vw * var(--scale));
  font-weight:600;
  letter-spacing:.01em;
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
  font-size:.78em;
  line-height:1;
}

.reveal{
  opacity:0;
  transform: translateY(1.1vh);
}
.page.is-ready .reveal{
  animation: rise .7s cubic-bezier(.22,.9,.3,1) forwards;
}
.page.is-ready .item.reveal .leader{
  animation: leader-draw .85s ease both;
  animation-delay: inherit;
}

.page.is-alive .reveal{
  opacity:1;
  transform:none;
  animation:none;
}
.page.is-alive .item .name .ch{
  animation: letter-wave 5.5s ease-in-out infinite;
  animation-delay: calc(var(--i, 0) * 140ms + var(--c, 0) * 42ms);
}
.page.is-alive .group-head{
  animation: head-breathe 7s ease-in-out infinite;
  animation-delay: calc(var(--g, 0) * 900ms);
}
.page.is-alive .item.is-signature .sig-star{
  animation: star-glow 2.8s ease-in-out infinite;
  animation-delay: calc(var(--i, 0) * 120ms);
}

.footer{
  flex:none;
  display:flex; align-items:center; justify-content:space-between;
  gap:1.5rem;
  padding-top:1vh; margin-top:.9vh;
  border-top:1px solid var(--line);
  font-size:.64vw;
  font-weight:600; letter-spacing:.22em; text-transform:uppercase;
  color:var(--muted);
  opacity:0;
  animation: rise .8s cubic-bezier(.22,.9,.3,1) .35s forwards;
}
.footer.is-alive{
  animation: footer-soft 6s ease-in-out infinite;
  opacity:1;
}
.legend{display:flex; align-items:center; gap:.45vw}
.legend .sig-star{font-size:1em; animation: star-glow 2.8s ease-in-out infinite}

@keyframes rise{
  from{opacity:0; transform:translateY(1.1vh)}
  to{opacity:1; transform:none}
}
@keyframes watermark-breathe{
  0%,100%{opacity:.055; transform:scale(1) rotate(0deg)}
  50%{opacity:.1; transform:scale(1.035) rotate(.4deg)}
}
@keyframes star-glow{
  0%,100%{opacity:.7; transform:scale(1)}
  50%{opacity:1; transform:scale(1.18)}
}
@keyframes leader-draw{
  from{opacity:0; transform:translateY(-.28em) scaleX(.35)}
  to{opacity:1; transform:translateY(-.28em) scaleX(1)}
}
@keyframes letter-wave{
  0%,70%,100%{
    transform: translateY(0);
    opacity: .9;
  }
  78%{
    transform: translateY(-.2em);
    opacity: 1;
  }
  86%{
    transform: translateY(.05em);
    opacity: 1;
  }
  94%{
    transform: translateY(0);
    opacity: .95;
  }
}
@keyframes head-breathe{
  0%,100%{opacity:.88}
  50%{opacity:1}
}
@keyframes footer-soft{
  0%,100%{opacity:.72}
  50%{opacity:1}
}

@media (prefers-reduced-motion:reduce){
  .watermark img,
  .page.is-ready .reveal,
  .page.is-ready .item.reveal .leader,
  .page.is-alive .item .name .ch,
  .page.is-alive .group-head,
  .page.is-alive .item.is-signature .sig-star,
  .legend .sig-star,
  .footer,
  .footer.is-alive{
    animation:none!important;
  }
  .reveal,.footer{opacity:1; transform:none}
}</style>
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

MENU.forEach((page) => {
  const el = document.createElement('section');
  el.className = 'page';
  el.innerHTML = (page.columns || []).map(col => `
    <div class="column">
      ${col.map(g => `
        <div class="group">
          <div class="group-head reveal">
            <h2>${esc(g.name)}</h2>
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
    node.style.animationDelay = Math.min(i * 28, 1100) + 'ms';
  });
  [...el.querySelectorAll('.item')].forEach((node, i) => {
    node.style.setProperty('--i', i);
  });
  [...el.querySelectorAll('.group')].forEach((node, i) => {
    node.querySelector('.group-head')?.style.setProperty('--g', i);
  });
  void el.offsetWidth;
  el.classList.add('is-ready');

  // after entrance finishes, keep ambient motion looping
  const entranceMs = Math.min(nodes.length * 28, 1100) + 800;
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
