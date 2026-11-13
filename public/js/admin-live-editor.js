(() => {
  const toolbar = document.querySelector('[data-live-edit-ui]');
  if (!toolbar) return;
  const page = location.pathname === '/' ? '/' : location.pathname.replace(/\/+$/, '');
  const loadUrl = toolbar.dataset.loadUrl, saveUrl = toolbar.dataset.saveUrl, deleteUrl = toolbar.dataset.deleteUrl;
  const uploadUrl = toolbar.dataset.uploadUrl, layoutUrl = toolbar.dataset.layoutUrl, csrf = toolbar.dataset.csrf;
  const toggle = toolbar.querySelector('[data-live-toggle]'), reset = toolbar.querySelector('[data-live-reset]');
  const collapseButton = toolbar.querySelector('[data-live-collapse]');
  const imageButton = toolbar.querySelector('[data-live-image]'), fileInput = toolbar.querySelector('[data-live-file]');
  const moveButton = toolbar.querySelector('[data-live-move]');
  const status = toolbar.querySelector('[data-live-status]');
  const excluded = 'script,style,noscript,svg,canvas,iframe,textarea,input,select,option,form,[aria-live],[data-no-live-edit],[data-live-edit-ui],.a-side,.a-main,.owner-panel,.owner-sidebar,[data-private]';
  let active = false, moveMode = false, focusedTarget = null, focusedImage = null, focusedImageKind = 'image', saveTimer = 0, saving = false, dragged = null, moveOverlay = null, moveOrigin = null, dropTarget = null, dropMode = '', dragPoint = null, autoScrollFrame = 0;
  const targets = new Map(), imageTargets = new Map(), dragGroups = new Map();
  const collapsedStorageKey = 'apoLiveEditorToolbarCollapsed';
  function setToolbarCollapsed(collapsed, persist = true) {
    toolbar.dataset.collapsed = String(collapsed);
    collapseButton.setAttribute('aria-expanded', String(!collapsed));
    collapseButton.setAttribute('aria-label', collapsed ? 'Canlı düzenleme araçlarını göster' : 'Canlı düzenleme çubuğunu küçült');
    collapseButton.title = collapsed ? 'Canlı düzenleme araçlarını göster' : 'Çubuğu küçült';
    collapseButton.textContent = collapsed ? '✎' : '−';
    if (persist) { try { localStorage.setItem(collapsedStorageKey, String(collapsed)); } catch (_) {} }
  }
  let toolbarWasCollapsed = false;
  try { toolbarWasCollapsed = localStorage.getItem(collapsedStorageKey) === 'true'; } catch (_) {}
  setToolbarCollapsed(toolbarWasCollapsed, false);
  collapseButton.addEventListener('click', () => setToolbarCollapsed(toolbar.dataset.collapsed !== 'true'));
  function setStatus(message, state = '') { status.textContent = message; status.dataset.state = state; }
  function elementPath(element) {
    const parts = []; let current = element;
    while (current && current !== document.body) {
      const tag = current.tagName.toLowerCase();
      let ordinal = 1;
      for (let sibling = current.previousElementSibling; sibling; sibling = sibling.previousElementSibling) if (sibling.tagName === current.tagName) ordinal++;
      parts.unshift(tag + ':nth-of-type(' + ordinal + ')');
      if (current.id && document.querySelectorAll('#' + CSS.escape(current.id)).length === 1) return '#' + CSS.escape(current.id) + (parts.length > 1 ? '>' + parts.slice(1).join('>') : '');
      current = current.parentElement;
    }
    return 'body>' + parts.join('>');
  }
  function identity(node) {
    if (node.dataset.liveContainerKey) return node.dataset.liveContainerKey;
    if (node.id) return 'id:' + node.id;
    const cls = [...node.classList].filter(c => !['apo-live-edit-target','apo-live-dragging','apo-live-drop-before','apo-live-drop-after'].includes(c)).sort().join('.');
    const base = node.tagName.toLowerCase() + (cls ? '.' + cls : '');
    const heading=node.matches('h1,h2,h3,h4')?node:node.querySelector('h1,h2,h3,h4');
    const label=heading?.textContent.trim()||node.getAttribute('alt')||node.getAttribute('title')||node.querySelector('caption')?.textContent.trim()||node.textContent.trim().replace(/\s+/g,' ').slice(0,120);
    if(label) return base+':label:'+label.slice(0,140);
    const peers = [...node.parentElement.children].filter(s => s.tagName === node.tagName && [...s.classList].filter(c => !c.startsWith('apo-live-')).sort().join('.') === cls);
    return base + ':' + (peers.indexOf(node) + 1);
  }
  function assignLayoutKeys(){const counts=new Map();document.querySelectorAll('main *').forEach(el=>{const base=identity(el),n=(counts.get(base)||0)+1;counts.set(base,n);el.dataset.liveContainerKey=base+(n>1?':g'+n:'');});}
  function layoutKey(element){return element.dataset.liveContainerKey||identity(element);}
  async function requestJson(url, options = {}) {
    const response = await fetch(url, { credentials:'same-origin', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest',...(options.headers||{})}, ...options });
    if (!response.ok) throw new Error(response.status === 419 ? 'Oturum yenilenmeli. Sayfayı tazeleyin.' : 'Kaydetme başarısız oldu. Tekrar deneyin.');
    return response.json();
  }
  function collectTextNodes() {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT), found = []; let node;
    while ((node = walker.nextNode())) {
      const value = node.nodeValue || '', parent = node.parentElement;
      if (!parent || !value.trim() || parent.closest(excluded)) continue;
      const rect = parent.getBoundingClientRect(); if (!rect.width || !rect.height || getComputedStyle(parent).visibility === 'hidden') continue;
      const ordinal = Array.from(parent.childNodes).filter(item => item.nodeType === Node.TEXT_NODE).indexOf(node);
      found.push({node, selector:elementPath(parent)+'::text('+ordinal+')', value});
    }
    return found;
  }
  function wrapTargets(edits) {
    collectTextNodes().forEach(({node,selector,value}) => {
      const wrapper = document.createElement('span'); wrapper.className = 'apo-live-edit-target';
      wrapper.dataset.liveSelector = selector; wrapper.dataset.defaultText = value;
      if (Object.prototype.hasOwnProperty.call(edits,selector)) node.nodeValue = edits[selector];
      wrapper.dataset.savedText = node.nodeValue; wrapper.textContent = node.nodeValue; node.parentNode.replaceChild(wrapper,node); targets.set(selector,wrapper);
    });
  }
  function applyImages(images) {
    document.querySelectorAll('img').forEach(img => {
      if (img.closest(excluded)) return;
      const selector = elementPath(img)+'::image'; img.dataset.liveImageSelector = selector; img.dataset.defaultSrc = img.dataset.defaultSrc || img.getAttribute('src') || '';
      if (images[selector]) img.src = images[selector]; imageTargets.set(selector,img);
    });
    document.querySelectorAll('*').forEach(el => {
      if (el.closest(excluded)) return;
      const background = getComputedStyle(el).backgroundImage;
      if (!background || background === 'none' || !background.includes('url(')) return;
      const selector = elementPath(el)+'::background'; el.dataset.liveBackgroundSelector=selector;
      el.dataset.defaultBackground=el.style.backgroundImage; el.dataset.backgroundBase=background;
      if (images[selector]) el.style.backgroundImage=background.replace(/url\(["']?[^"')]+["']?\)/, `url("${images[selector]}")`);
    });
  }
  function orderSections(layouts) {
    const globalItems=new Map([...document.querySelectorAll('main *')].map(el=>[identity(el),el]));
    Object.entries(layouts).forEach(([container,order])=>{
      if(!Array.isArray(order))return;
      const parent=[...document.querySelectorAll('[data-live-container-key]')].find(el=>el.dataset.liveContainerKey===container);
      if(!parent)return;
      order.forEach(key=>{const el=globalItems.get(key);if(el&&!el.contains(parent))parent.appendChild(el);});
    });
  }
  function setupDragDrop(){}
  function moveCandidate(target){
    if(!target||target.closest(excluded)||!target.closest('main'))return null;
    const special=target.closest('img,picture,table,thead,tbody,tfoot,tr,th,td,figure,article,section,aside,nav,h1,h2,h3,h4,h5,h6,p,blockquote,ul,ol,li,button,a,[data-live-movable]');
    if(special&&special.closest('main'))return special;
    let current=target;const main=document.querySelector('main');
    while(current&&current!==main){const display=getComputedStyle(current).display;if(['block','flex','grid','table','table-row','list-item'].includes(display)&&current.parentElement?.children.length>1)return current;current=current.parentElement;}
    return null;
  }
  function selectMoveNode(element){if(!element||element===document.querySelector('main')||!element.closest('main'))return false;dragged?.classList.remove('apo-live-move-selected','apo-live-dragging');dragged=element;dragged.classList.add('apo-live-move-selected');const parent=dragged.parentElement,child=dragged.querySelector(':scope > *');moveOverlay.querySelector('[data-live-move-control="parent"]').disabled=!parent||parent===document.querySelector('main');moveOverlay.querySelector('[data-live-move-control="child"]').disabled=!child;const label=dragged.tagName.toLowerCase()+(dragged.id?'#'+dragged.id:'')+([...dragged.classList].filter(c=>!c.startsWith('apo-live-')).slice(0,1).map(c=>'.'+c).join(''));moveOverlay.querySelector('[data-live-move-label]').textContent=label;moveOverlay.querySelector('[data-live-move-label]').title=label;updateMoveOverlay();return true;}
  function updateMoveOverlay(){if(!moveOverlay||!dragged)return;const r=dragged.getBoundingClientRect(),width=moveOverlay.offsetWidth||270;moveOverlay.style.left=Math.max(8,Math.min(innerWidth-width-8,r.right-width+8))+'px';moveOverlay.style.top=Math.max(8,Math.min(innerHeight-48,r.top+8))+'px';}
  function hideMoveOverlay(){if(dragged)dragged.classList.remove('apo-live-move-selected','apo-live-dragging');moveOverlay?.remove();moveOverlay=null;dragged=null;moveOrigin=null;dropTarget=null;dropMode='';cancelAnimationFrame(autoScrollFrame);autoScrollFrame=0;}
  function showMoveOverlay(element,origin){moveOverlay=document.createElement('div');moveOverlay.dataset.noLiveEdit='';moveOverlay.dataset.liveMovePanel='';moveOverlay.className='apo-live-move-panel';moveOverlay.innerHTML='<button type="button" data-live-move-handle title="Seçili öğeyi sürükle">⠿ Taşı</button><button type="button" data-live-move-control="parent" title="Bir üst öğeyi seç">↑ Üst</button><button type="button" data-live-move-control="child" title="Alt öğeyi seç">↓ Alt</button><span data-live-move-label></span>';document.body.appendChild(moveOverlay);moveOrigin=origin||element;selectMoveNode(element);}
  async function persistMove(parents){try{const layouts=[...new Set(parents)].map(parent=>({container:layoutKey(parent),order:[...parent.children].map(identity)}));const result=await requestJson(layoutUrl,{method:'PUT',body:JSON.stringify({page,layouts})});setStatus(result.message,'saved');}catch(error){setStatus(error.message,'error');}}
  function moveTo(target,after){if(!dragged||!target||target===dragged||target.parentElement!==dragged.parentElement)return false;const parent=target.parentElement;parent.insertBefore(dragged,after?target.nextSibling:target);parent.querySelectorAll('.apo-live-drop-before,.apo-live-drop-after,.apo-live-drop-inside').forEach(el=>el.classList.remove('apo-live-drop-before','apo-live-drop-after','apo-live-drop-inside'));dragged.classList.remove('apo-live-dragging');updateMoveOverlay();persistMove([parent]);return true;}
  function moveInto(container){if(!dragged||!container||container===dragged||dragged.contains(container))return false;const source=dragged.parentElement;container.appendChild(dragged);container.classList.remove('apo-live-drop-inside');dragged.classList.remove('apo-live-dragging');updateMoveOverlay();persistMove([source,container]);return true;}
  async function saveTarget(target) {
    if (!target || !target.dataset.liveSelector || saving) return; clearTimeout(saveTimer); const content=target.textContent;
    if (content===target.dataset.defaultText) {
      try { await requestJson(deleteUrl,{method:'DELETE',body:JSON.stringify({page,selector:target.dataset.liveSelector})}); target.dataset.savedText=target.dataset.defaultText; reset.disabled=true; setStatus('Metin varsayılan haline döndürüldü.','saved'); }
      catch(error){setStatus(error.message,'error');} return;
    }
    saving=true; setStatus('Kaydediliyor…','saving');
    try { const result=await requestJson(saveUrl,{method:'PUT',body:JSON.stringify({page,selector:target.dataset.liveSelector,content})}); target.dataset.savedText=content; setStatus(result.message||'Değişiklik kaydedildi.','saved'); }
    catch(error){setStatus(error.message,'error');} finally{saving=false;}
  }
  function setEditing(on) {
    active=on; toolbar.dataset.active=String(on); toggle.textContent=on?'✓ Düzenleme açık':'✎ Sayfayı düzenle'; toggle.setAttribute('aria-pressed',String(on));
    targets.forEach(target=>{target.contentEditable=on?'true':'false';target.classList.toggle('is-editable',on);target.tabIndex=on?0:-1;});
    if(!on){moveMode=false;moveButton?.setAttribute('aria-pressed','false');hideMoveOverlay();}
    if(moveButton)moveButton.disabled=!on;
    document.querySelectorAll('img[data-live-image-selector]').forEach(img=>img.classList.toggle('apo-live-image-editable',on));
    document.querySelectorAll('[data-live-background-selector]').forEach(el=>el.classList.toggle('apo-live-background-editable',on));
    setStatus(on?'Metni ve görseli düzenleyin; “Gelişmiş taşıma” ile öğeleri yeniden konumlandırın.':'Düzenleme kapalı.');
  }
  toggle.addEventListener('click',()=>setEditing(!active));
  moveButton.addEventListener('click',()=>{if(!active)return;moveMode=!moveMode;moveButton.setAttribute('aria-pressed',String(moveMode));hideMoveOverlay();setStatus(moveMode?'Herhangi bir metin, görsel, bağlantı, tablo hücresi veya kartı seçin. Üst/alt kontrolleriyle öğe seviyesini seçip sürükleyin.':'Gelişmiş taşıma kapatıldı.');});
  reset.addEventListener('click',async()=>{
    if (focusedImage) {
      const img=focusedImage, selector=focusedImageKind==='background'?img.dataset.liveBackgroundSelector:img.dataset.liveImageSelector;
      try { await requestJson(deleteUrl,{method:'DELETE',body:JSON.stringify({page,selector})}); if(focusedImageKind==='background')img.style.backgroundImage=img.dataset.defaultBackground;else img.src=img.dataset.defaultSrc; focusedImage=null;imageButton.disabled=true;setStatus('Görsel varsayılan haline döndürüldü.','saved');reset.disabled=true; }
      catch(error){setStatus(error.message,'error');} return;
    }
    if (!focusedTarget) return;
    try { await requestJson(deleteUrl,{method:'DELETE',body:JSON.stringify({page,selector:focusedTarget.dataset.liveSelector})});focusedTarget.textContent=focusedTarget.dataset.defaultText;focusedTarget.dataset.savedText=focusedTarget.dataset.defaultText;setStatus('Metin varsayılan haline döndürüldü.','saved');reset.disabled=true; }
    catch(error){setStatus(error.message,'error');}
  });
  imageButton.addEventListener('click',()=>{if(focusedImage)fileInput.click();});
  fileInput.addEventListener('change',async()=>{
    const image=fileInput.files?.[0];if(!image||!focusedImage)return;
    const suffix=focusedImageKind==='background'?'::background':'::image', selector=focusedImageKind==='background'?focusedImage.dataset.liveBackgroundSelector:focusedImage.dataset.liveImageSelector;
    const form=new FormData();form.append('page',page);form.append('selector',selector.replace(new RegExp(suffix+'$'),''));form.append('kind',focusedImageKind);form.append('image',image);
    try { const response=await fetch(uploadUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},body:form});if(!response.ok)throw new Error('Görsel yüklenemedi. Dosya 8 MB altında JPG, PNG, WebP veya GIF olmalı.');const result=await response.json();if(focusedImageKind==='background')focusedImage.style.backgroundImage=focusedImage.dataset.backgroundBase.replace(/url\(["']?[^"')]+["']?\)/,`url("${result.url}")`);else focusedImage.src=result.url;setStatus(result.message,'saved');reset.disabled=false; }
    catch(error){setStatus(error.message,'error');} finally{fileInput.value='';}
  });
  document.addEventListener('click',event=>{
    if(!active)return;
    if(event.target.closest('[data-live-edit-ui]'))return;
    const handle=event.target.closest('[data-live-move-handle]');if(handle){event.preventDefault();event.stopImmediatePropagation();return;}
    const control=event.target.closest('[data-live-move-control]');if(control&&moveMode&&dragged){event.preventDefault();event.stopImmediatePropagation();const main=document.querySelector('main'),kind=control.dataset.liveMoveControl;if(kind==='parent'&&dragged.parentElement!==main)selectMoveNode(dragged.parentElement);else if(kind==='child'){let child=moveOrigin&&dragged.contains(moveOrigin)?moveOrigin:null;while(child&&child.parentElement!==dragged)child=child.parentElement;selectMoveNode(child||dragged.firstElementChild);}return;}
    if(moveMode){event.preventDefault();event.stopImmediatePropagation();const block=moveCandidate(event.target);if(!block){setStatus('Bu öğe taşınamıyor.');return;}hideMoveOverlay();showMoveOverlay(block,event.target);setStatus('Öğe seçildi. Üst/alt ile seviyesini seçin veya sürükleyin.');return;}
    const target=event.target.closest('.apo-live-edit-target');if(target){event.stopPropagation();if(target.closest('a,button'))event.preventDefault();focusedTarget=target;focusedImage=null;imageButton.disabled=true;reset.disabled=target.textContent===target.dataset.defaultText;return;}
    const img=event.target.closest('img[data-live-image-selector]');const background=event.target.closest('[data-live-background-selector]');const media=img||background;
    if(media){event.preventDefault();event.stopPropagation();focusedImage=media;focusedTarget=null;focusedImageKind=img?'image':'background';imageButton.disabled=false;reset.disabled=img?img.src===img.dataset.defaultSrc:media.style.backgroundImage===media.dataset.defaultBackground;setStatus('Görsel seçildi. “Görsel değiştir” ile yeni görsel yükleyin.');}
  },true);
  document.addEventListener('focusin',event=>{const target=event.target.closest?.('.apo-live-edit-target');if(target&&active){focusedTarget=target;focusedImage=null;imageButton.disabled=true;reset.disabled=target.textContent===target.dataset.defaultText;}});
  document.addEventListener('input',event=>{const target=event.target.closest?.('.apo-live-edit-target');if(!target||!active)return;focusedTarget=target;reset.disabled=target.textContent===target.dataset.defaultText;setStatus('Değişiklik kaydediliyor…','saving');clearTimeout(saveTimer);saveTimer=setTimeout(()=>saveTarget(target),700);});
  document.addEventListener('blur',event=>{const target=event.target.closest?.('.apo-live-edit-target');if(target&&active)saveTarget(target);},true);
  document.addEventListener('paste',event=>{if(!active||!event.target.closest?.('.apo-live-edit-target'))return;event.preventDefault();document.execCommand('insertText',false,event.clipboardData?.getData('text/plain')||'');});
  document.addEventListener('keydown',event=>{const target=event.target.closest?.('.apo-live-edit-target');if(!target||!active)return;if(event.key==='Escape'){event.preventDefault();target.textContent=target.dataset.savedText;target.blur();setStatus('Düzenleme iptal edildi.');}else if(event.key==='Enter'&&!event.shiftKey&&!['P','DIV','LI'].includes(target.parentElement?.tagName)){event.preventDefault();target.blur();}});
  document.addEventListener('pointerdown',event=>{if(!active||!moveMode||!event.target.closest('[data-live-move-handle]')||!dragged)return;event.preventDefault();event.target.closest('[data-live-move-handle]').setPointerCapture(event.pointerId);moveOverlay.style.pointerEvents='none';dragged.classList.add('apo-live-dragging');dragPoint={x:event.clientX,y:event.clientY};});
  document.addEventListener('pointermove',event=>{if(!dragged?.classList.contains('apo-live-dragging'))return;dragPoint={x:event.clientX,y:event.clientY};if(!autoScrollFrame)autoScrollFrame=requestAnimationFrame(function scrollWhileDragging(){autoScrollFrame=0;if(!dragged?.classList.contains('apo-live-dragging')||!dragPoint)return;const edge=Math.min(82,innerHeight*.14);if(dragPoint.y<edge)window.scrollBy(0,-Math.ceil((edge-dragPoint.y)/4));else if(dragPoint.y>innerHeight-edge)window.scrollBy(0,Math.ceil((dragPoint.y-(innerHeight-edge))/4));autoScrollFrame=requestAnimationFrame(scrollWhileDragging);});let sibling=null;const stack=document.elementsFromPoint(event.clientX,event.clientY);for(const el of stack){let node=el;while(node&&node.parentElement!==dragged.parentElement)node=node.parentElement;if(node&&node!==dragged){sibling=node;break;}}document.querySelectorAll('.apo-live-drop-before,.apo-live-drop-after,.apo-live-drop-inside').forEach(el=>el.classList.remove('apo-live-drop-before','apo-live-drop-after','apo-live-drop-inside'));if(sibling){dropTarget=sibling;dropMode='sibling';const r=sibling.getBoundingClientRect();sibling.classList.add(event.clientY>r.top+r.height/2?'apo-live-drop-after':'apo-live-drop-before');return;}const container=stack.map(el=>el.closest('main section,main article,main figure,main table,main td,main th,main div,main ul,main ol')).find(el=>el&&!el.contains(dragged)&&el!==dragged.parentElement);if(!container)return;dropTarget=container;dropMode='inside';container.classList.add('apo-live-drop-inside');});
  document.addEventListener('pointerup',()=>{if(!dragged?.classList.contains('apo-live-dragging'))return;cancelAnimationFrame(autoScrollFrame);autoScrollFrame=0;dragPoint=null;const target=dropTarget,mode=dropMode,after=target?.classList.contains('apo-live-drop-after');dragged.classList.remove('apo-live-dragging');if(moveOverlay)moveOverlay.style.pointerEvents='auto';if(mode==='inside'&&target&&moveInto(target))setStatus('Öğe yeni kutusuna taşınıyor…','saving');else if(target&&moveTo(target,after))setStatus('Öğe sırası kaydediliyor…','saving');else{document.querySelectorAll('.apo-live-drop-before,.apo-live-drop-after,.apo-live-drop-inside').forEach(el=>el.classList.remove('apo-live-drop-before','apo-live-drop-after','apo-live-drop-inside'));updateMoveOverlay();}dropTarget=null;dropMode='';});
  document.addEventListener('pointercancel',()=>{cancelAnimationFrame(autoScrollFrame);autoScrollFrame=0;dragPoint=null;if(dragged)dragged.classList.remove('apo-live-dragging');if(moveOverlay)moveOverlay.style.pointerEvents='auto';dropTarget=null;updateMoveOverlay();});
  window.addEventListener('scroll',updateMoveOverlay,true);window.addEventListener('resize',updateMoveOverlay);
  requestJson(loadUrl+'?page='+encodeURIComponent(page),{method:'GET',headers:{'Content-Type':'application/json'}}).then(result=>{assignLayoutKeys();applyImages(result.images||{});wrapTargets(result.edits||{});orderSections(result.layouts||{});setupDragDrop();setStatus('Düzenleme kapalı.');}).catch(()=>setStatus('Canlı düzenleyici yüklenemedi. Sayfayı yenileyin.','error'));
})();
