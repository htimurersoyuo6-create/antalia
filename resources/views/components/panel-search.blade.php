<style>
.panel-search-trigger{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:8px 12px;border:1px solid #dce5df;border-radius:11px;background:#fff;color:#20352d;font:700 14px/1.2 system-ui,-apple-system,"Segoe UI",sans-serif;cursor:pointer;box-shadow:0 3px 12px rgba(25,48,37,.06)}
.panel-search-trigger:hover{border-color:#f45116;color:#d83d0b}.panel-search-trigger svg{width:17px;height:17px;flex:none}.panel-search-trigger kbd{padding:3px 5px;border:1px solid #e5e9e6;border-radius:5px;background:#f7f8f7;color:#66736b;font:600 10px/1.1 ui-monospace,SFMono-Regular,Consolas,monospace}
.panel-search-dialog{width:min(560px,calc(100vw - 28px));max-width:none;max-height:min(76vh,680px);padding:0;border:1px solid #dfe6e1;border-radius:18px;background:#fff;color:#1c2b25;box-shadow:0 24px 80px rgba(18,35,28,.25);overflow:hidden}.panel-search-dialog::backdrop{background:rgba(18,29,24,.48);backdrop-filter:blur(3px)}
.panel-search-head{display:flex;align-items:center;gap:12px;padding:17px 18px 12px}.panel-search-head svg{width:20px;height:20px;flex:none;color:#ed4a13}.panel-search-head input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:#17251f;font:600 17px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif}.panel-search-head input::placeholder{color:#87918b}.panel-search-close{width:31px;height:31px;flex:none;border:1px solid #e1e7e3;border-radius:8px;background:#fff;color:#58645d;font-size:18px;cursor:pointer}
.panel-search-hint{padding:0 18px 12px;color:#78837c;font-size:12px}.panel-search-list{max-height:min(56vh,490px);overflow:auto;padding:4px 9px 10px}.panel-search-list:empty{display:none}.panel-search-result{display:flex;width:100%;flex-direction:column;align-items:flex-start;gap:3px;padding:11px 12px;border:0;border-radius:10px;background:#fff;color:#21332a;text-align:left;cursor:pointer}.panel-search-result:hover,.panel-search-result:focus-visible{outline:0;background:#f1f6f2}.panel-search-result strong{font-size:14px}.panel-search-result small{color:#758078;font-size:12px;overflow-wrap:anywhere}.panel-search-result em{color:#d84612;font-size:10px;font-style:normal;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.panel-search-empty{padding:18px 12px;color:#69766e;font-size:14px}.panel-search-foot{display:flex;justify-content:space-between;gap:10px;padding:10px 17px;border-top:1px solid #edf0ed;color:#839087;font-size:11px}.panel-search-target{outline:3px solid rgba(244,81,22,.55)!important;outline-offset:4px!important;border-radius:5px!important;transition:outline-color .25s}
@media(max-width:600px){.panel-search-trigger{min-height:38px;padding:7px 10px}.panel-search-trigger kbd{display:none}.panel-search-head{padding:14px 14px 10px}.panel-search-head input{font-size:16px}.panel-search-list{max-height:55vh}.panel-search-foot{font-size:10px}}
</style>
<button class="panel-search-trigger" type="button" data-panel-search-open aria-haspopup="dialog" aria-controls="panel-search-dialog" aria-label="Panel içinde ara">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
  <span>Ara</span><kbd>Ctrl K</kbd>
</button>
<dialog class="panel-search-dialog" id="panel-search-dialog" aria-labelledby="panel-search-title">
  <div class="panel-search-head">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
    <input id="panel-search-query" type="search" autocomplete="off" placeholder="Sayfa, kayıt veya alan ara…" aria-label="Panelde aranacak ifade">
    <button class="panel-search-close" type="button" data-panel-search-close aria-label="Aramayı kapat">×</button>
  </div>
  <div class="panel-search-hint" id="panel-search-hint">Menülerde ve açık sayfanın içeriğinde arayın.</div>
  <div class="panel-search-list" id="panel-search-results" role="listbox" aria-label="Arama sonuçları"></div>
  <div class="panel-search-foot"><span>Sonuç seçerek sayfaya gidin veya ilgili bölüme atlayın.</span><span>Esc · Kapat</span></div>
</dialog>
<script>
(function(){
  var dialog=document.getElementById('panel-search-dialog'),input=document.getElementById('panel-search-query'),results=document.getElementById('panel-search-results'),hint=document.getElementById('panel-search-hint');
  if(!dialog||!input||!results)return;
  var normalize=function(value){return String(value||'').replace(/[İIı]/g,'i').toLocaleLowerCase('tr-TR').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim()};
  function close(){if(dialog.open)dialog.close()}
  function addResult(title,detail,kind,activate){var button=document.createElement('button');button.type='button';button.className='panel-search-result';button.setAttribute('role','option');var tag=document.createElement('em');tag.textContent=kind;var strong=document.createElement('strong');strong.textContent=title;var small=document.createElement('small');small.textContent=detail||'';button.append(tag,strong);if(detail){button.append(small)}button.addEventListener('click',function(){close();activate()});results.append(button)}
  function search(){
    var term=normalize(input.value);results.replaceChildren();if(term.length<2){hint.textContent='Aramak için en az 2 karakter yazın.';return}
    var found=0,seen=new Set(),navs=document.querySelectorAll('.a-nav a, #owner-panel-sidebar a, nav.owner-panel-nav a, .owner-app-shortcuts a');
    navs.forEach(function(link){if(found>=8)return;var label=(link.innerText||link.textContent||'').replace(/\s+/g,' ').trim(),href=link.getAttribute('href')||'',key=normalize(label);if(!key||seen.has('n'+key)||!normalize(label+' '+href).includes(term))return;seen.add('n'+key);found++;addResult(label,href,'SAYFA',function(){window.location.href=link.href})});
    var content=document.querySelector('.a-main')||document.querySelector('#icerik.owner-panel');
    if(content){var candidates=content.querySelectorAll('h1,h2,h3,h4,label,button:not(.panel-search-trigger),a,p,li,tr,summary,[data-searchable]');candidates.forEach(function(node){if(found>=14||node.closest('dialog')||node.hidden||node.getAttribute('aria-hidden')==='true')return;var label=(node.innerText||node.textContent||'').replace(/\s+/g,' ').trim();if(label.length<2||label.length>260)return;var extra=[node.getAttribute('aria-label'),node.getAttribute('placeholder'),node.value].filter(Boolean).join(' '),all=label+' '+extra,key=normalize(label);if(!normalize(all).includes(term)||seen.has('c'+key))return;seen.add('c'+key);found++;var target=node;addResult(label.slice(0,150),node.tagName==='TR'?'Listedeki eşleşen kayıt':'Açık sayfada bulundu','BU SAYFADA',function(){target.scrollIntoView({behavior:'smooth',block:'center'});target.classList.add('panel-search-target');if(target.matches('h1,h2,h3,h4,label,button,a,[tabindex]')){var old=target.getAttribute('tabindex');if(old===null)target.setAttribute('tabindex','-1');target.focus({preventScroll:true});if(old===null)setTimeout(function(){target.removeAttribute('tabindex')},1200)}setTimeout(function(){target.classList.remove('panel-search-target')},2200)})})}
    hint.textContent=found?found+' eşleşme gösteriliyor.':'Eşleşme bulunamadı. Başka bir kelime deneyin.';
  }
  document.querySelectorAll('[data-panel-search-open]').forEach(function(button){button.addEventListener('click',function(){dialog.showModal();input.value='';results.replaceChildren();hint.textContent='Menülerde ve açık sayfanın içeriğinde arayın.';input.focus()})});
  document.querySelectorAll('[data-panel-search-close]').forEach(function(button){button.addEventListener('click',close)});
  input.addEventListener('input',search);input.addEventListener('keydown',function(event){if(event.key==='Enter'){event.preventDefault();var first=results.querySelector('button');if(first)first.click()}});
  dialog.addEventListener('click',function(event){if(event.target===dialog)close()});
  document.addEventListener('keydown',function(event){if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='k'){event.preventDefault();var trigger=document.querySelector('[data-panel-search-open]');if(trigger)trigger.click()}if(event.key==='Escape')close()});
})();
</script>
