@extends('layouts.admin')

@section('title', 'SSS Asistanı Testi | Yönetim Paneli')
@section('heading', 'SSS asistan testi')
@section('sub', 'Yayınlanmış AntalyaPetOtel SSS içeriğinden anında, sabit cevaplar.')

@section('content')
<style>
.local-ai-test{max-width:860px}.local-ai-notice{margin:0 0 16px;padding:14px 16px;border:1px solid #d6e5d6;border-radius:12px;background:#f4faf2;line-height:1.55}.local-ai-chat{display:grid;gap:12px;margin:18px 0}.local-ai-message{max-width:90%;padding:12px 15px;border-radius:14px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.5}.local-ai-message.user{justify-self:end;background:#e6f2e8}.local-ai-message.assistant{justify-self:start;background:#f2f4f1}.local-ai-match{margin-top:5px;color:#66746a;font-size:12px}.local-ai-form{display:grid;gap:10px}.local-ai-form textarea{min-height:90px;padding:12px;border:1px solid #ccd6cd;border-radius:12px;font:inherit;resize:vertical}.local-ai-status{min-height:1.5em;color:#59685d;font-size:14px}
</style>
<section class="cardbox local-ai-test">
  <div class="local-ai-notice"><strong>Model kullanılmıyor.</strong> Bu test, sitede yayınlanmış SSS metnini anahtar kelimelerle eşleştirir; CPU/GPU tüketmez ve sohbeti kaydetmez. Eşleşme bulunmazsa yanıt uydurmaz. Kamera ve fiyat gibi tesis bazında değişen bilgileri ilgili işletmeden doğrulayın.</div>
  <div id="local-ai-chat" class="local-ai-chat" aria-live="polite"></div>
  <form id="local-ai-form" class="local-ai-form">
    @csrf
    <label for="local-ai-input"><strong>Soru</strong></label>
    <textarea id="local-ai-input" maxlength="500" required placeholder="Örnek: Köpeğimi bıraktığımda canlı kameradan görebiliyor muyum?"></textarea>
    <div id="local-ai-status" class="local-ai-status" role="status"></div>
    <div><button id="local-ai-submit" class="btn" type="submit">Gönder</button></div>
  </form>
</section>
<script>
(()=>{
  const form=document.getElementById('local-ai-form');
  const input=document.getElementById('local-ai-input');
  const chat=document.getElementById('local-ai-chat');
  const status=document.getElementById('local-ai-status');
  const button=document.getElementById('local-ai-submit');
  function addMessage(role,content,matchedQuestion){const item=document.createElement('div');item.className='local-ai-message '+role;item.textContent=content;chat.appendChild(item);if(matchedQuestion){const match=document.createElement('div');match.className='local-ai-match';match.textContent='Eşleşen SSS: '+matchedQuestion;item.appendChild(match)}item.scrollIntoView({behavior:'smooth',block:'nearest'});}
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    const question=input.value.trim();
    if(!question||button.disabled)return;
    addMessage('user',question);
    input.value='';button.disabled=true;status.textContent='Yayınlanmış SSS içinde eşleşme aranıyor…';
    try{
      const response=await fetch(@json(route('admin.ai-test.chat')),{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({question})});
      const data=await response.json();
      if(!response.ok)throw new Error(data.message||'İstek başarısız oldu.');
      addMessage('assistant',data.answer,data.matched_question);status.textContent='Yanıt hazır.';
    }catch(error){status.textContent=error.message||'Yanıt alınamadı.';}
    finally{button.disabled=false;input.focus();}
  });
})();
</script>
@endsection
