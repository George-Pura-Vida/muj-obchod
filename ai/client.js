'use strict';
const $ = id => document.getElementById(id);
let meetings = [], active = null, csrf = '', aiAvailable = false, busy = false;
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
function message(text, type=''){const el=$('status');el.className=type;el.textContent=text;}
async function ask(operation, data){
 const opts={credentials:'same-origin',cache:'no-store',headers:{}};
 if(data){opts.method='POST';opts.headers['Content-Type']='application/json';opts.headers['X-CSRF-Token']=csrf;opts.body=JSON.stringify(data);}
 const resp=await fetch('api.php?do='+encodeURIComponent(operation),opts);
 const res=await resp.json().catch(()=>({ok:false,error:'Server nevrátil JSON. Zkontroluj PHP a přístupová práva.'}));
 if(!resp.ok || !res.ok)throw new Error(res.error || 'Operace se nezdařila.');
 return res;
}
function updateSelection(){
 document.querySelectorAll('.item').forEach(b=>b.classList.toggle('selected',+b.dataset.id===+active?.id));
 const selected=meetings.find(a=>Number(a.id)===Number(active?.id));
 if(!selected){$('detail-content').classList.add('hidden');return;}
 active=selected;
 $('detail-content').classList.remove('hidden');
 $('meeting-title').textContent=(selected.first_name||'')+' '+(selected.last_name||'');
 $('meeting-meta').textContent='Zápis #'+selected.id+' · '+selected.happened_at+' · '+selected.kind;
 $('transcript').value=selected.transcript||'';$('review').value=selected.review||'';
 $('legal-confirm').checked=false;
 const rid=Number(selected.recording_id||0);
 $('audio-box').classList.toggle('hidden',!rid);
 $('player').pause();$('player').removeAttribute('src');$('player').load();
 if(rid)$('player').src='../api.php?action=media&id='+encodeURIComponent(rid);
 $('transcribe').disabled=!rid||!aiAvailable||busy;
 $('analyze').disabled=!aiAvailable||busy;
 $('save').disabled=busy;
}
async function refresh(reselect){
 const data=await ask('list'); csrf=data.csrf;aiAvailable=data.ai_available;
 meetings=data.items||[];
 const usedT=data.usage?.transcribe?.count||0,usedA=data.usage?.analyze?.count||0;
 $('usage').textContent='Měsíční kvóty: Přepisy '+usedT+'/'+data.quotas.transcriptions+' · Analýzy '+usedA+'/'+data.quotas.analyses+'\n'+(!aiAvailable?'⚠️ AI klíč zatím není na serveru nastaven.':'');
 $('meetings').innerHTML=meetings.length ? meetings.map(a=>'<button type="button" class="item" data-id="'+Number(a.id)+'"><b>'+escapeHtml(a.first_name)+' '+escapeHtml(a.last_name)+'</b><small>'+escapeHtml(a.happened_at)+' · '+escapeHtml(a.kind)+(a.recording_id?' · 🎙️ audio':'')+'</small></button>').join(''):'<p class="hint">Zatím žádný zápis. Nejprve vytvoř schůzku v hlavní aplikaci.</p>';
 document.querySelectorAll('.item').forEach(b=>b.addEventListener('click',()=>{active={id:Number(b.dataset.id)};message('');updateSelection();}));
 if(reselect) active={id:reselect};
 if(active)updateSelection();
}
async function run(kind){
 if(!active||busy)return;
 const id=Number(active.id);
 if(kind!=='save'&&!$('legal-confirm').checked){message('Před zpracováním pomocí AI potvrď oprávnění a informování účastníků.','error');return;}
 if(kind==='transcribe'&&!confirm('Přepis nahrávky odešle zvukové údaje do OpenAI. Stávající přepis této schůzky bude nahrazen. Pokračovat?'))return;
 if(kind==='analyze'){
  if($('transcript').value!==String(active.transcript||'')){
   message('Nejprve ulož upravený přepis tlačítkem „Uložit přepis“.','error');return;
  }
  if(!$('transcript').value.trim()){message('Chybí přepis schůzky.','error');return;}
 }
 busy=true;
 ['save','transcribe','analyze'].forEach(x=>$(x).disabled=true);
 message(kind==='transcribe'?'Zpracovávám nahrávku. Nezavírej tuto stránku.':kind==='analyze'?'AI připravuje hodnocení.':'Ukládám...');
 try{
  const request={activity_id:id};
  if(kind==='transcribe')request.recording_id=Number(active.recording_id);
  if(kind!=='save')request.consent_confirmed=true;
  if(kind==='save')request.transcript=$('transcript').value;
  const result=await ask(kind,request);
  await refresh(id);
  message(result.message||(kind==='analyze'?'AI vyhodnocení je uložené.':'Hotovo.'),'ok');
 }catch(error){message(error.message,'error');}
 finally{busy=false; $('save').disabled=false; $('transcribe').disabled=!Number(active?.recording_id)||!aiAvailable; $('analyze').disabled=!aiAvailable;}
}
$('save').addEventListener('click',()=>run('save'));
$('transcribe').addEventListener('click',()=>run('transcribe'));
$('analyze').addEventListener('click',()=>run('analyze'));
refresh().catch(e=>message(e.message,'error'));
