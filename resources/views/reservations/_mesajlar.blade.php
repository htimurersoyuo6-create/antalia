{{-- Rezervasyona bagli, sadece misafir ve tesis arasinda canli mesajlasma. --}}
<section class="booking-chat" data-poll-url="{{ $pollUrl }}" data-after="{{ $res->messages->last()?->id ?? 0 }}">
  <header class="booking-chat-head"><div><h3>{{ $chatTitle ?? 'Otel ile mesajlaşma' }}</h3><p>{{ $chatSubtitle ?? 'Rezervasyonunuzla ilgili güvenli görüşme' }}</p></div><span class="chat-live"><i></i> Canlı</span></header>
  <div class="mesajlar" aria-live="polite" aria-label="Rezervasyon mesajları">
  @forelse($res->messages as $m)
    <article class="mesaj {{ $m->user_id === $benId ? 'ben' : 'onlar' }}" data-message-id="{{ $m->id }}"><small>{{ $m->user_id === $benId ? 'Siz' : ($m->user?->id === $res->user_id ? $res->user->name : $res->hotel->name) }} · {{ $m->created_at?->format('d.m.Y H:i') }}</small><p>{{ $m->body }}</p></article>
  @empty <p class="hint chat-empty">Henüz mesaj yok. Sorularınızı buradan iletebilirsiniz.</p>
  @endforelse
  </div>
  <form class="form chat-compose" method="post" action="{{ $action }}">@csrf
    <label class="sr-only" for="chat-{{ $res->id }}">Mesajınız</label><textarea id="chat-{{ $res->id }}" name="body" required maxlength="1000" placeholder="Mesajınızı yazın…" rows="2"></textarea>
    <div><span class="chat-status" role="status">Yanıt gelince bu konuşma otomatik güncellenir.</span><button class="btn sm">Gönder</button></div>
  </form>
</section>
<script>
(function(){var root=document.currentScript.previousElementSibling;if(!root||!root.matches('.booking-chat'))return;
var list=root.querySelector('.mesajlar'),form=root.querySelector('form'),status=root.querySelector('.chat-status'),after=Number(root.dataset.after||0),busy=false;
function add(m){var empty=list.querySelector('.chat-empty');if(empty)empty.remove();var row=document.createElement('article');row.className='mesaj '+(m.mine?'ben':'onlar');row.dataset.messageId=m.id;var small=document.createElement('small');small.textContent=(m.sender||'Mesaj')+' · '+(m.created_at||'şimdi');var p=document.createElement('p');p.textContent=m.body;row.append(small,p);list.append(row);after=Math.max(after,Number(m.id)||0);root.dataset.after=after;list.scrollTop=list.scrollHeight;}
async function poll(){if(busy||document.hidden)return;busy=true;try{var u=new URL(root.dataset.pollUrl,location.href);u.searchParams.set('after',after);var r=await fetch(u,{headers:{Accept:'application/json'},cache:'no-store',credentials:'same-origin'});if(r.ok){var d=await r.json();(d.messages||[]).forEach(add);}}catch(e){}finally{busy=false;}}
form.addEventListener('submit',async function(e){e.preventDefault();var b=form.querySelector('button');if(b.disabled)return;b.disabled=true;status.textContent='Gönderiliyor…';try{var r=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});var d=await r.json();if(!r.ok)throw new Error(d.message||'Mesaj gönderilemedi.');add(d);form.reset();status.textContent='Mesajınız iletildi.';}catch(err){status.textContent=err.message||'Bağlantı kurulamadı; yeniden deneyin.';}finally{b.disabled=false;}});
window.setInterval(poll,4000);
})();
</script>
