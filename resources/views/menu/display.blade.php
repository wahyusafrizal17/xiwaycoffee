<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu · Xiway Coffee</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
<style>
/* ============================================================
   XIWAY COFFEE — TV MENU DISPLAY (43" / 1920x1080)
   ============================================================ */

:root{
  --bg:         #FFFFFF;
  --ink:        #100E0C;
  --line:       rgba(16,14,12,.12);
  --muted:      rgba(16,14,12,.55);
  --red:        #AF2B22;
  --red-bright: #D4382C;
  --brass:      #9A7A3A;
  --page-duration: 16s;
  --scale: 1;
}

*{margin:0;padding:0;box-sizing:border-box}

html,body{height:100%;overflow:hidden}

body{
  background:
    radial-gradient(90rem 60rem at 12% -10%, rgba(198,161,91,.08), transparent 60%),
    radial-gradient(70rem 50rem at 110% 115%, rgba(175,43,34,.06), transparent 60%),
    var(--bg);
  color:var(--ink);
  font-family:'Archivo',-apple-system,'Segoe UI',sans-serif;
  font-variant-numeric:tabular-nums;
  -webkit-font-smoothing:antialiased;
  cursor:none;
}

body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:url('{{ asset('images/logo/xiway-logo.png') }}') center 52% / min(42vw, 520px) no-repeat;
  opacity:.08;
}
body::after{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:40;
  box-shadow:inset 0 0 14vmin 4vmin rgba(16,14,12,.04);
}

.stage{
  position:relative;z-index:1;
  height:100vh;
  display:flex;flex-direction:column;
  padding:3.2vh 3.6vw 2.4vh;
}

.masthead{
  display:flex;align-items:flex-end;justify-content:space-between;
  gap:2rem;flex:none;
}

.brand{display:flex;align-items:center;gap:1.1vw}

.brand-logo{
  height:7.6vh;
  width:auto;
  max-width:18vw;
  object-fit:contain;
  display:block;
}

.page-title{
  text-align:right;line-height:.88;
}
.page-title em{
  display:block;font-style:normal;
  font-size:.95vw;
  font-weight:600;letter-spacing:.42em;text-transform:uppercase;
  color:var(--brass);
  margin-bottom:.9em;
}
.page-title h1{
  font-size:4.2vw;
  font-weight:800;letter-spacing:-.025em;
  text-transform:uppercase;
}

.pages{position:relative;flex:1;min-height:0;margin-top:2.4vh}

.page{
  --scale: 1;
  position:absolute;inset:0;
  display:grid;gap:0 3.2vw;
  align-content:start;
  opacity:0;visibility:hidden;
  transition:opacity .9s ease;
}
.page.is-active{opacity:1;visibility:visible}

.group{min-width:0;break-inside:avoid}
.group + .group{margin-top:2.6vh}
.group[data-span="2"]{grid-column:span 2}

.group-head{
  display:flex;align-items:baseline;gap:1.2vw;
  padding-bottom:1.1vh;margin-bottom:1.1vh;
  border-bottom:1px solid var(--line);
}
.group-head h2{
  font-size:calc(1.65vw * var(--scale));
  font-weight:700;letter-spacing:.2em;text-transform:uppercase;
}
.group-head small{
  font-size:calc(.85vw * var(--scale));
  font-weight:500;letter-spacing:.2em;
  color:var(--muted);
}

.items{list-style:none}
.group[data-cols="2"] .items{column-count:2;column-gap:3.2vw}

.item{
  display:flex;align-items:baseline;gap:.8vw;
  padding:calc(1.15vh * var(--scale)) 0;
  break-inside:avoid;
}

.item .name{
  font-size:calc(1.55vw * var(--scale));
  font-weight:500;letter-spacing:.005em;
  white-space:nowrap;
}
.item.is-signature .name{font-weight:700}

.item .leader{
  flex:1;height:1px;min-width:1.5vw;
  transform:translateY(-.32em);
  background-image:radial-gradient(circle, rgba(16,14,12,.32) 1px, transparent 1.4px);
  background-size:.55vw 2px;background-repeat:repeat-x;
}

.item .price{
  font-size:calc(1.55vw * var(--scale));
  font-weight:700;letter-spacing:.02em;
  color:var(--ink);
}
.item .price sup{
  font-size:.58em;font-weight:600;vertical-align:.35em;
  margin-left:.15em;color:var(--muted);
}
.item.is-signature .price{color:var(--brass)}

.sig-star{
  flex:none;
  color:var(--brass);
  font-size:.85em;
  line-height:1;
  transform:translateY(-.05em);
}

.footer{
  flex:none;display:flex;align-items:center;justify-content:space-between;
  gap:2rem;padding-top:2vh;margin-top:1.8vh;
  border-top:1px solid var(--line);
  font-size:.82vw;
  font-weight:500;letter-spacing:.28em;text-transform:uppercase;
  color:var(--muted);
}
.legend{display:flex;align-items:center;gap:.7vw}
.legend .sig-star{font-size:1em;transform:none}

.ticker{display:flex;align-items:center;gap:.9vw}
.tick{
  width:2.6vw;height:3px;border-radius:2px;
  background:rgba(16,14,12,.12);overflow:hidden;
}
.tick i{
  display:block;height:100%;width:100%;
  background:var(--red-bright);
  transform-origin:left center;transform:scaleX(0);
}
.tick.is-done i{transform:scaleX(1);background:rgba(16,14,12,.28)}
.tick.is-active i{animation:fill var(--page-duration) linear forwards}
@keyframes fill{from{transform:scaleX(0)}to{transform:scaleX(1)}}

.page.is-active .reveal{animation:rise .75s cubic-bezier(.22,.9,.3,1) backwards}
@keyframes rise{
  from{opacity:0;transform:translateY(1.6vh)}
  to{opacity:1;transform:none}
}

.page-title h1.swap{animation:swap .7s cubic-bezier(.22,.9,.3,1)}
@keyframes swap{
  from{opacity:0;transform:translateY(1.2vh) scale(.985)}
  to{opacity:1;transform:none}
}

@media (prefers-reduced-motion:reduce){
  .page,.page.is-active .reveal,.page-title h1.swap{animation:none!important;transition:none}
  .tick.is-active i{animation:none;transform:scaleX(1)}
}
</style>
</head>
<body>

<div class="stage">

  <header class="masthead">
    <div class="brand">
      <img class="brand-logo" src="{{ asset('images/logo/logo.png') }}" alt="Xiway Coffee">
    </div>

    <div class="page-title">
      <em id="pageKicker">Menu</em>
      <h1 id="pageName">{{ $menu[0]['title'] ?? 'Menu' }}</h1>
    </div>
  </header>

  <main class="pages" id="pages"></main>

  <footer class="footer">
    <div class="legend"><span class="sig-star" aria-hidden="true">★</span> Menu andalan</div>
    <div>Harga dalam ribuan rupiah</div>
    <div class="ticker" id="ticker"></div>
  </footer>

</div>

<script>
const MENU = @json($menu);

const pagesEl  = document.getElementById('pages');
const tickerEl  = document.getElementById('ticker');
const kickerEl  = document.getElementById('pageKicker');
const nameEl    = document.getElementById('pageName');

const esc = s => String(s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));

MENU.forEach((page) => {
  const el = document.createElement('section');
  el.className = 'page';
  el.style.gridTemplateColumns = page.layout || '1fr 1fr';

  el.innerHTML = page.groups.map(g => `
    <div class="group" ${g.span ? `data-span="${g.span}"` : ''} data-cols="${g.cols || 1}">
      <div class="group-head reveal">
        <h2>${esc(g.name)}</h2>
        ${g.note ? `<small>${esc(g.note)}</small>` : ''}
      </div>
      <ul class="items">
        ${g.items.map(it => `
          <li class="item reveal ${it.star ? 'is-signature' : ''}">
            ${it.star ? '<span class="sig-star" aria-hidden="true">★</span>' : ''}
            <span class="name">${esc(it.name)}</span>
            <span class="leader"></span>
            <span class="price">${it.price}<sup>K</sup></span>
          </li>`).join('')}
      </ul>
    </div>`).join('');

  pagesEl.appendChild(el);

  const tick = document.createElement('span');
  tick.className = 'tick';
  tick.innerHTML = '<i></i>';
  tickerEl.appendChild(tick);
});

const pageEls = [...document.querySelectorAll('.page')];
const tickEls = [...document.querySelectorAll('.tick')];

function fit(el){
  let scale = 1;
  el.style.setProperty('--scale', scale);
  const room = pagesEl.clientHeight;
  let guard = 80;

  while (el.scrollHeight < room * 0.97 && scale < 1.65 && guard--){
    scale = +(scale + 0.03).toFixed(2);
    el.style.setProperty('--scale', scale);
  }

  guard = 80;
  while (el.scrollHeight > room && scale > 0.55 && guard--){
    scale = +(scale - 0.02).toFixed(2);
    el.style.setProperty('--scale', scale);
  }
}

let current = -1;

function show(i){
  if (!MENU.length) return;
  const page = MENU[i];
  const el   = pageEls[i];

  pageEls.forEach(p => p.classList.remove('is-active'));
  el.classList.add('is-active');
  fit(el);

  el.querySelectorAll('.reveal').forEach((r, k) => {
    r.style.animation = 'none';
    void r.offsetWidth;
    r.style.animation = '';
    r.style.animationDelay = Math.min(k * 38, 900) + 'ms';
  });

  kickerEl.textContent = page.kicker || 'Menu';
  nameEl.textContent   = page.title;
  nameEl.classList.remove('swap'); void nameEl.offsetWidth; nameEl.classList.add('swap');

  tickEls.forEach((t, k) => {
    t.classList.toggle('is-active', k === i);
    t.classList.toggle('is-done',   k < i);
  });

  current = i;
}

function next(){ show((current + 1) % MENU.length); }

const duration = parseFloat(
  getComputedStyle(document.documentElement).getPropertyValue('--page-duration')
) * 1000;

if (MENU.length) {
  show(0);
  if (MENU.length > 1) setInterval(next, duration);
}

setTimeout(() => location.reload(), 6 * 60 * 60 * 1000);

addEventListener('keydown', e => {
  if (!MENU.length) return;
  if (e.key === 'ArrowRight') next();
  if (e.key === 'ArrowLeft')  show((current - 1 + MENU.length) % MENU.length);
});

addEventListener('resize', () => { if (current >= 0) show(current); });
</script>
</body>
</html>
