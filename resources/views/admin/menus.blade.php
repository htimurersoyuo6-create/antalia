@extends('layouts.admin')
@section('title','Menüler ve altbilgi')
@section('heading','Menüler ve altbilgi')
@section('sub','Sayfaları menülere ekleyin, sıralayın ve altbilgi sütunlarını yayınlayın.')
@section('actions')<a class="a-btn ghost" href="{{ route('admin.settings') }}">Site ayarlarına dön</a>@endsection
@section('content')
<form class="a-card a-form menu-editor" method="post" action="{{ route('admin.menus.save') }}" id="menu-editor">@csrf @method('PUT')
  <div class="menu-editor-top"><div><span class="a-settings-kicker">GEZİNME DÜZENLEYİCİSİ</span><h2>Site bağlantıları</h2><p>Her sütunu açıp düzenleyin. Sayfa seçerek adı ve adresi doldurabilir, isterseniz ikisini de değiştirebilirsiniz.</p></div><button class="a-btn" type="submit">Değişiklikleri yayınla</button></div>
  <div class="menu-search" role="search">
    <label for="menu-search-query">Hızlı erişim</label>
    <div class="menu-search-controls"><input type="search" id="menu-search-query" placeholder="Bağlantı veya sayfa ara…" autocomplete="off"><button class="a-btn" type="button" id="menu-search-submit">Ara</button><button class="a-btn ghost" type="button" id="menu-search-clear">Temizle</button></div>
    <small id="menu-search-status" aria-live="polite">Menü bağlantılarını adı veya adresiyle bulun.</small>
  </div>
  <div class="menu-editor-layout">
    <div class="menu-groups">
      @php $labels=['header_menu'=>['Üst menü','Sitenin ana gezinti menüsü'],'footer_explore'=>['Altbilgi · Keşfet','Otel, blog ve topluluk bağlantıları'],'footer_corporate'=>['Altbilgi · Kurumsal','Kurumsal ve iletişim sayfaları'],'footer_legal'=>['Altbilgi · Yasal','Gizlilik, kullanım ve politika sayfaları']]; @endphp
      @foreach($labels as $key=>$meta)
      <details class="menu-group" @if($loop->first) open @endif data-group="{{ $key }}">
        <summary><span class="menu-group-icon">☷</span><span><b>{{ $meta[0] }}</b><small>{{ $meta[1] }}</small></span><em>{{ count($groups[$key]) }} bağlantı</em></summary>
        <div class="menu-group-body">
          <div class="menu-row-head"><span>Sayfa</span><span>Menüde görünen ad</span><span>Bağlantı adresi</span><span>Sıra</span></div>
          <div class="menu-rows" data-rows="{{ $key }}">
          @foreach($groups[$key] as $item)
            <div class="menu-item-row">
              <select class="menu-page-select" aria-label="Sayfa seç"><option value="">Özel bağlantı / sayfa seç</option>@foreach($availablePages as $page)<option value="{{ $page['url'] }}" data-label="{{ $page['label'] }}" @selected($page['url']===$item['url'])>{{ $page['label'] }}</option>@endforeach</select>
              <input name="{{ $key }}[{{ $loop->index }}][label]" value="{{ $item['label'] }}" maxlength="100" placeholder="Bağlantı adı" required aria-label="Menüde görünen ad">
              <input name="{{ $key }}[{{ $loop->index }}][url]" value="{{ $item['url'] }}" maxlength="500" placeholder="/sayfa-yolu" required aria-label="Bağlantı adresi">
              <div class="menu-row-actions"><button type="button" class="a-btn sm ghost" data-row-action="up" aria-label="Yukarı taşı">↑</button><button type="button" class="a-btn sm ghost" data-row-action="down" aria-label="Aşağı taşı">↓</button><button type="button" class="a-btn sm ghost" data-row-action="delete" aria-label="Bağlantıyı kaldır">×</button></div>
            </div>
          @endforeach
          </div>
          <button type="button" class="a-btn ghost menu-add" data-add="{{ $key }}">＋ Bağlantı ekle</button>
          <small class="menu-help">Bağlantıları oklarla sıralayın. Harici adreslerde <b>https://</b> kullanın.</small>
        </div>
      </details>
      @endforeach
    </div>
    <aside class="menu-preview"><span class="a-settings-kicker">ALT BİLGİ METİNLERİ</span><h3>Altbilgi tanıtımı</h3>
      <label>Tanıtım yazısı<textarea name="footer_description" rows="4" maxlength="1200">{{ old('footer_description',$footerDescription) }}</textarea></label>
      <label>Rezervasyon notu<textarea name="footer_booking_note" rows="3" maxlength="500">{{ old('footer_booking_note',$footerBookingNote) }}</textarea></label>
      <label>Telif satırı<input name="footer_copyright" value="{{ old('footer_copyright',$footerCopyright) }}" maxlength="200"><small>{year} bulunduğu yerde güncel yılı gösterir.</small></label>
      <div class="menu-preview-card"><b>Önizleme</b><div class="menu-preview-title">Antalya Pet Otel</div><p>{{ $footerDescription }}</p><div>{{ $footerBookingNote }}</div><small>{{ $footerCopyright }}</small></div>
    </aside>
  </div>
  <div class="menu-save-bottom"><span>Kaydettiğiniz değişiklikler site menülerinde hemen görünür.</span><button class="a-btn" type="submit">Değişiklikleri yayınla</button></div>
</form>
<script>
(()=>{
 const pages=@json($availablePages);const form=document.getElementById('menu-editor');if(!form)return;
 const renumber=list=>list.querySelectorAll('.menu-item-row').forEach((row,i)=>row.querySelectorAll('input').forEach((input,j)=>input.name=list.dataset.rows+'['+i+']['+(j===0?'label':'url')+']'));
 const bind=row=>{const select=row.querySelector('.menu-page-select'),inputs=row.querySelectorAll('input');select.addEventListener('change',()=>{const option=select.selectedOptions[0];if(!select.value)return;inputs[0].value=option.dataset.label||option.textContent.trim();inputs[1].value=select.value});row.querySelector('.menu-row-actions').addEventListener('click',e=>{const button=e.target.closest('button');if(!button)return;const list=row.parentElement;if(button.dataset.rowAction==='delete')row.remove();if(button.dataset.rowAction==='up'&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);if(button.dataset.rowAction==='down'&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);renumber(list)})};
 form.querySelectorAll('.menu-item-row').forEach(bind);
 form.querySelectorAll('[data-add]').forEach(button=>button.addEventListener('click',()=>{const key=button.dataset.add,list=form.querySelector('[data-rows="'+key+'"]'),index=list.querySelectorAll('.menu-item-row').length,row=document.createElement('div');row.className='menu-item-row';const select=document.createElement('select');select.className='menu-page-select';select.setAttribute('aria-label','Sayfa seç');const blank=new Option('Özel bağlantı / sayfa seç','');select.add(blank);pages.forEach(page=>{const option=new Option(page.label,page.url);option.dataset.label=page.label;select.add(option)});const label=document.createElement('input');label.name=key+'['+index+'][label]';label.placeholder='Bağlantı adı';label.maxLength=100;label.required=true;label.setAttribute('aria-label','Menüde görünen ad');const url=document.createElement('input');url.name=key+'['+index+'][url]';url.placeholder='/sayfa-yolu';url.maxLength=500;url.required=true;url.setAttribute('aria-label','Bağlantı adresi');const actions=document.createElement('div');actions.className='menu-row-actions';[['up','↑','Yukarı taşı'],['down','↓','Aşağı taşı'],['delete','×','Bağlantıyı kaldır']].forEach(([action,text,title])=>{const b=document.createElement('button');b.type='button';b.className='a-btn sm ghost';b.dataset.rowAction=action;b.textContent=text;b.title=title;b.setAttribute('aria-label',title);actions.append(b)});row.append(select,label,url,actions);list.append(row);bind(row);renumber(list);label.focus()}));
 form.addEventListener('submit',()=>form.querySelectorAll('.menu-rows').forEach(renumber));
 const query=document.getElementById('menu-search-query'),submit=document.getElementById('menu-search-submit'),clear=document.getElementById('menu-search-clear'),status=document.getElementById('menu-search-status');
 function search(){const term=query.value.trim().toLocaleLowerCase('tr-TR');let matches=0;form.querySelectorAll('.menu-item-row').forEach(row=>{const text=[row.querySelector('input[aria-label="Menüde görünen ad"]')?.value,row.querySelector('input[aria-label="Bağlantı adresi"]')?.value,row.querySelector('.menu-page-select')?.selectedOptions[0]?.textContent].join(' ').toLocaleLowerCase('tr-TR');const found=!term||text.includes(term);row.hidden=!found;if(term&&found){matches++;row.closest('.menu-group').open=true}});status.textContent=term?(matches?matches+' bağlantı bulundu.':'Eşleşen bağlantı bulunamadı.'):'Menü bağlantılarını adı veya adresiyle bulun.';if(term&&matches){const first=form.querySelector('.menu-item-row:not([hidden])');first?.scrollIntoView({behavior:'smooth',block:'center'});first?.querySelector('input')?.focus({preventScroll:true})}}
 submit.addEventListener('click',search);query.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();search()}});clear.addEventListener('click',()=>{query.value='';search();query.focus()});
})();
</script>
<style>
.menu-editor{padding:20px}.menu-editor-top{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:4px 2px 20px;border-bottom:1px solid #e8eae6}.menu-editor-top h2{margin:7px 0;font-size:22px}.menu-editor-top p{margin:0;color:#707b73;line-height:1.6}.menu-editor-layout{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(250px,.8fr);gap:18px;margin-top:18px}.menu-groups{display:grid;gap:11px;align-content:start}.menu-group{border:1px solid #e4e8e2;border-radius:14px;background:#fff;overflow:hidden}.menu-group summary{display:flex;align-items:center;gap:12px;padding:15px;cursor:pointer;list-style:none}.menu-group summary::-webkit-details-marker{display:none}.menu-group-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#fff1eb;color:#ee4c17;font-size:20px}.menu-group summary span:nth-child(2){display:grid;gap:3px;flex:1}.menu-group summary b{color:#28342e}.menu-group summary small{color:#78827a}.menu-group summary em{font-style:normal;color:#e94c18;font-size:12px;font-weight:800}.menu-group-body{padding:0 14px 14px}.menu-row-head,.menu-item-row{display:grid;grid-template-columns:minmax(135px,1fr) minmax(130px,1fr) minmax(135px,1.2fr) 116px;gap:8px;align-items:center}.menu-row-head{padding:5px 8px;color:#758078;font-size:10px;font-weight:800}.menu-item-row{padding:8px;margin-bottom:7px;border:1px solid #e6e9e5;border-radius:11px;background:#fcfdfc}.menu-item-row select,.menu-item-row input,.menu-preview label input,.menu-preview textarea{width:100%;min-width:0;padding:9px;border:1px solid #dce2dd;border-radius:8px;background:#fff;color:#29342e;font:inherit;font-size:12px}.menu-row-actions{display:flex;justify-content:flex-end;gap:3px}.menu-add{margin-top:3px}.menu-help{display:block;margin:8px 3px;color:#79837c}.menu-preview{align-self:start;position:sticky;top:16px;padding:17px;border:1px solid #e7e8e1;border-radius:14px;background:#faf9f6}.menu-preview h3{margin:7px 0 16px}.menu-preview label{display:grid;gap:6px;margin:12px 0;font-weight:700;font-size:12px}.menu-preview label small{font-weight:400;color:#78827a}.menu-preview-card{margin-top:18px;padding:14px;border:1px solid #e7e2d9;border-radius:12px;background:#fff}.menu-preview-card>b{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#dd4d1c}.menu-preview-title{margin-top:10px;font-weight:850}.menu-preview-card p,.menu-preview-card div:not(.menu-preview-title){color:#68736c;font-size:12px;line-height:1.5}.menu-preview-card small{display:block;margin-top:12px;color:#7a827d}.menu-save-bottom{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid #e8eae6}.menu-save-bottom span{color:#758078;font-size:12px}
.menu-search{display:grid;gap:7px;margin:17px 0 0;padding:13px 14px;border:1px solid #e5e9e4;border-radius:12px;background:#f9faf8}.menu-search>label{font-size:12px;font-weight:850;color:#303b34}.menu-search-controls{display:flex;gap:8px}.menu-search-controls input{flex:1;min-width:0;padding:10px 12px;border:1px solid #dce2dd;border-radius:9px;background:#fff;font:inherit}.menu-search-controls input:focus{outline:2px solid #ff9a74;border-color:#f45116}.menu-search>small{color:#78827a}.menu-item-row[hidden]{display:none!important}
@media(max-width:1000px){.menu-editor-layout{grid-template-columns:1fr}.menu-preview{position:static}}
@media(max-width:700px){.menu-editor{padding:13px}.menu-editor-top{align-items:flex-start;flex-direction:column}.menu-row-head{display:none}.menu-item-row{grid-template-columns:1fr;gap:7px}.menu-item-row select,.menu-item-row input{font-size:14px}.menu-row-actions{justify-content:flex-start}.menu-save-bottom{align-items:flex-start;flex-direction:column}}
</style>
@endsection
