<style>
.analytics-consent{position:fixed;z-index:10020;inset:auto 16px 16px;width:min(560px,calc(100% - 32px));padding:18px;border:1px solid #dfe7df;border-radius:16px;background:#fff;color:#233329;box-shadow:0 12px 40px rgba(20,35,25,.2);font:500 14px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}
.analytics-consent[hidden]{display:none!important}.analytics-consent p{margin:0 0 12px}.analytics-consent-actions{display:flex;flex-wrap:wrap;gap:8px}.analytics-consent button{min-height:44px;padding:9px 14px;border:1px solid #cfd9d0;border-radius:10px;background:#fff;color:#24372a;font:inherit;font-weight:750;cursor:pointer}.analytics-consent button[type=submit][value=accepted]{border-color:#17784d;background:#17784d;color:#fff}.analytics-consent button:focus-visible,.fcol [data-cookie-preferences]:focus-visible{outline:3px solid #e6b35c;outline-offset:3px}.fcol [data-cookie-preferences]{display:block;border:0;padding:0;background:transparent;color:inherit;font:inherit;text-align:left;text-decoration:underline;cursor:pointer}
@media(max-width:520px){.analytics-consent{inset:auto 10px 10px;width:calc(100% - 20px);padding:15px}.analytics-consent-actions{display:grid;grid-template-columns:1fr 1fr}.analytics-consent button{padding-inline:8px}}
</style>
<section id="analytics-consent" class="analytics-consent" aria-labelledby="analytics-consent-title" @if(in_array($consent, ['accepted', 'rejected'], true)) hidden @endif>
  <h2 id="analytics-consent-title" style="margin:0 0 6px;font-size:16px">İsteğe bağlı analiz çerezleri</h2>
  <p>Google Analytics yalnızca kabul ederseniz etkinleşir. Reddetmek temel site işlevlerini etkilemez; tercihinizi daha sonra değiştirebilirsiniz.</p>
  <form method="post" action="{{ route('cookie-preferences.analytics') }}">
    @csrf
    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
    <div class="analytics-consent-actions">
      <button type="submit" name="choice" value="accepted">Analiz çerezlerini kabul et</button>
      <button type="submit" name="choice" value="rejected">Reddet</button>
    </div>
  </form>
</section>
<script>
document.querySelectorAll('[data-cookie-preferences]').forEach(function(link){
  link.addEventListener('click',function(event){
    event.preventDefault();
    var panel=document.getElementById('analytics-consent');
    if(!panel)return;
    panel.hidden=false;
    panel.querySelector('button').focus();
  });
});
</script>
