<div class="site-assistant" id="site-assistant" data-endpoint="{{ route('assistant.answer') }}">
  <button class="site-assistant__launcher" type="button" aria-expanded="false" aria-controls="site-assistant-panel" aria-label="Misafir asistanını aç">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10.1c1.4 0 2.4-1.6 2.4-3.5S8.4 3.2 7 3.2 4.6 4.8 4.6 6.6 5.6 10.1 7 10.1Zm10 0c1.4 0 2.4-1.6 2.4-3.5S18.4 3.2 17 3.2s-2.4 1.6-2.4 3.4 1 3.5 2.4 3.5ZM3.8 16c1.1 0 2-1.2 2-2.7s-.9-2.7-2-2.7-2 1.2-2 2.7.9 2.7 2 2.7Zm16.4 0c1.1 0 2-1.2 2-2.7s-.9-2.7-2-2.7-2 1.2-2 2.7.9 2.7 2 2.7ZM12 21c3.4 0 6-1.8 6-4.1 0-2.4-2.7-4.3-6-4.3s-6 1.9-6 4.3C6 19.2 8.6 21 12 21Z"/></svg>
    <span>Asistana sor</span>
  </button>
  <section class="site-assistant__panel" id="site-assistant-panel" aria-labelledby="site-assistant-title" hidden>
    <header class="site-assistant__header">
      <span class="site-assistant__mark" aria-hidden="true">🐾</span>
      <div><h2 id="site-assistant-title">Minik dostunuz için yardım</h2><p>Antalya Pet Otel misafir asistanı</p></div>
      <button class="site-assistant__close" type="button" aria-label="Asistanı kapat">×</button>
    </header>
    <div class="site-assistant__messages" role="log" aria-live="polite" aria-relevant="additions">
      <div class="site-assistant__message site-assistant__message--bot">Merhaba! Tesis, konaklama ve rezervasyonla ilgili sorunuzu yazabilirsiniz. Yalnızca kayıtlı yanıtlarda eşleşme bulursam cevap veririm.</div>
      <div class="site-assistant__suggestions" aria-label="Örnek sorular">
        <button type="button">Giriş-çıkış saatleri</button>
        <button type="button">Canlı kamera var mı?</button>
        <button type="button">Ödeme nasıl yapılır?</button>
      </div>
    </div>
    <form class="site-assistant__form">
      @csrf
      <label class="site-assistant__sr-only" for="site-assistant-input">Sorunuz</label>
      <textarea id="site-assistant-input" name="question" rows="2" maxlength="500" required placeholder="Sorunuzu buraya yazın…"></textarea>
      <button type="submit" aria-label="Soruyu gönder"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-7.2 18-3.7-7.1L3 10.2 21 3Z"/><path d="m10.1 13.9 4.3-4.3"/></svg></button>
    </form>
    <p class="site-assistant__note" role="status">Yanıtlar kayıtlı tesis bilgilerine dayanır. Sorular hizmet kalitesi için kaydedilebilir; kişisel veya sağlık bilgisi paylaşmayın. Yanıtsız sorular otomatik olarak tesise iletilmez.</p>
  </section>
</div>
<script>
(()=>{
  const root=document.getElementById('site-assistant');
  if(!root)return;
  const launcher=root.querySelector('.site-assistant__launcher');
  const panel=root.querySelector('.site-assistant__panel');
  const close=root.querySelector('.site-assistant__close');
  const form=root.querySelector('.site-assistant__form');
  const input=root.querySelector('#site-assistant-input');
  const messages=root.querySelector('.site-assistant__messages');
  const note=root.querySelector('.site-assistant__note');
  const send=form.querySelector('button[type="submit"]');
  const token=form.querySelector('input[name="_token"]');
  const suggestions=[...root.querySelectorAll('.site-assistant__suggestions button')];
  let busy=false;
  function setOpen(open){panel.hidden=!open;launcher.setAttribute('aria-expanded',String(open));if(open)input.focus();}
  function addMessage(text,role){const item=document.createElement('div');item.className='site-assistant__message '+(role==='user'?'site-assistant__message--user':'site-assistant__message--bot');item.textContent=text;messages.appendChild(item);messages.scrollTop=messages.scrollHeight;}
  launcher.addEventListener('click',()=>setOpen(panel.hidden));
  close.addEventListener('click',()=>{setOpen(false);launcher.focus();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden){setOpen(false);launcher.focus();}});
  suggestions.forEach(button=>button.addEventListener('click',()=>{input.value=button.textContent.trim();form.requestSubmit();}));
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    const question=input.value.trim();
    if(!question||busy)return;
    busy=true;send.disabled=true;note.textContent='Kayıtlı tesis bilgileri kontrol ediliyor…';addMessage(question,'user');
    const payload={question};
    const hotel=document.querySelector('[data-hotel-page]');
    if(hotel&&hotel.dataset.hotelPage)payload.hotel_slug=hotel.dataset.hotelPage;
    try{
      const response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token.value,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
      const data=await response.json();
      if(!response.ok)throw new Error(data.message||'Şu anda yanıt alınamadı. Lütfen daha sonra tekrar deneyin.');
      addMessage(data.answer,'bot');
      note.textContent=data.handoff?'Kayıtlı bilgilerde yanıt bulunamadı. Lütfen tesis sayfasındaki iletişim seçeneklerinden doğrulayın.':'Yanıt, kayıtlı soru-cevap bilgileriyle eşleştirildi.';
      input.value='';
    }catch(error){note.textContent=error.message||'Bağlantı kurulamadı. Lütfen tekrar deneyin.';}
    finally{busy=false;send.disabled=false;input.focus();}
  });
})();
</script>
