'use strict';
const $ = id => document.getElementById(id);
const CZK = new Intl.NumberFormat('cs-CZ',{style:'currency',currency:'CZK',maximumFractionDigits:0});
const INT = new Intl.NumberFormat('cs-CZ');
const TODAY = () => new Date().toLocaleDateString('en-CA');
const STAGES = {contact:'Kontakt',phone:'Telefon',meeting1:'1. schůzka',meeting2:'Další schůzky',contract:'Smlouva',care:'Péče'};
const KINDS = {phone:'📞 Telefonát',meeting1:'🤝 První schůzka',meeting2:'🤝 Druhá schůzka',meeting:'🤝 Další schůzka',email:'✉ E-mail',care:'💚 Péče',note:'✏️ Poznámka'};
const PRODUCTS = ['Kovový účet','Zlato','Stříbro','Bitcoinový účet','Permanentní fond','Dragon fond','Životní pojištění','Úrazové pojištění','Pojištění majetku','Pojištění nemovitosti','Pojištění firmy','Pojištění auta','Pojištění odpovědnosti','Cestovní pojištění','Jiný produkt'];
let csrf = '';
let store = {contacts:[],activities:[],contracts:[],opportunities:[],referrals:[],recordings:[]};
let view='overview', editingContactId=0, formKind='',formPrefill={};
let personId=0, coachActivityId=0, recordActivityId=0, wantRecording=false;
let recorder=null, recordStream=null, recordChunks=[],recordBlob=null,recordTimer=null,recordStart=0,recordObjectUrl=null;
let toastTimer=null;
function esc(v){return String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function euros(v){return CZK.format(Number(v)||0);}
function when(v){if(!v)return 'Bez termínu';const a=String(v).substring(0,10).split('-');return a.length===3?a[2]+'. '+Number(a[1])+'. '+a[0]:'—';}
function initials(c){return ((c.first_name||'?').charAt(0)+(c.last_name||'?').charAt(0)).toUpperCase();}
function full(c){return [c.first_name,c.last_name].filter(Boolean).join(' ');}
function findContact(id){return store.contacts.find(c=>+c.id===+id);}
function fullname(id){const c=findContact(id);return c?full(c):'Neznámý klient';}
function sum(a,field){return a.reduce((total,x)=>total+(Number(x[field])||0),0);}
function icon(name){return '<svg><use href="#i-'+name+'"/></svg>';}
function dateList(a,field){return a.filter(x=>x[field]).sort((a,b)=>String(a[field]).localeCompare(String(b[field])));}
function toast(message,isError=false){const box=$('toast');box.textContent=message;box.style.background=isError?'#aa4e4b':'#164638';box.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>box.classList.remove('show'),3800);}
async function request(action,data,form=false){
 const opt={credentials:'same-origin',headers:{}};
 if(data!==undefined){opt.method='POST';opt.headers['X-CSRF-Token']=csrf;if(form)opt.body=data;else{opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(data);}}
 const response=await fetch('api.php?action='+encodeURIComponent(action),opt);
 let result;try{result=await response.json();}catch(_){throw Error('Server vrátil neplatnou odpověď. Zkontroluj PHP a umístění souborů.');}
 if(!response.ok||!result.ok)throw Error(result.error||'Chyba serveru');
 return result;
}
async function start(){
 try{
  const meta=await request('meta');
  csrf=meta.csrf;
  if(!meta.authenticated){
    $('auth').hidden=false;$('app').hidden=true;
    $('setup-row').hidden=!meta.setup_required;
    $('auth-title').textContent=meta.setup_required?'První aktivace':'Přihlášení';
    $('auth-submit').innerHTML=meta.setup_required?'Vytvořit můj účet '+icon('arrow'):'Přihlásit se '+icon('arrow');
    $('auth-description').textContent=meta.setup_required?'Vytvoř si soukromý účet na svém WebKitty.':'Tvůj soukromý obchodní prostor.';
    $('auth-help').textContent=meta.setup_required?(meta.setup_available?'Zadej instalační klíč z privátní konfigurace na hostingu.':'Nejprve nastav instalační klíč ve config.local.php (viz README).'):'Účet je přístupný pouze oprávněnému správci.';
  }else await signedIn();
 }catch(e){$('boot').textContent=e.message;$('boot').style.color='#b64742';return;}
 $('boot').hidden=true;
}
async function signedIn(){
 await refresh();
 $('auth').hidden=true;$('app').hidden=false;
 $('date-now').textContent=new Date().toLocaleDateString('cs-CZ',{weekday:'long',day:'numeric',month:'long'});
 go('overview');
}
async function refresh(){const res=await request('state');store=res.data;render();}
function go(next){
 view=next;
 document.querySelectorAll('.view').forEach(x=>x.hidden=x.id!==next+'-view');
 document.querySelectorAll('[data-view]').forEach(x=>x.classList.toggle('active',x.dataset.view===next));
 $('crumb').textContent={overview:'Přehled',people:'Lidé',day:'Můj den',results:'Výsledky'}[next]||'Přehled';
 $('sidebar').classList.remove('open');$('shade').hidden=true;$('hamburger').setAttribute('aria-expanded','false');
 render();window.scrollTo({top:0,behavior:'smooth'});
}
function render(){renderOverview();renderPeople();renderDay();renderResults();$('side-count').textContent=store.contacts.length||'';}
function taskRows(){
 const tasks=[];
 store.activities.forEach(a=>{if(a.next_at)tasks.push({when:a.next_at,label:fullname(a.contact_id),detail:'Další kontakt po: '+(KINDS[a.kind]||'Schůzka'),cid:a.contact_id,icon:'calendar'});});
 store.opportunities.forEach(o=>{if(o.next_at&&o.status==='open')tasks.push({when:o.next_at,label:fullname(o.contact_id),detail:'Následný prodej · '+o.product,cid:o.contact_id,icon:'branch'});});
 store.contacts.forEach(c=>{if(c.next_care)tasks.push({when:c.next_care,label:full(c),detail:'Pravidelná péče',cid:c.id,icon:'heart'});});
 store.contracts.forEach(c=>{if(c.expected_at&&c.status!=='cancelled'&&(Number(c.commission_expected)>Number(c.commission_paid)))tasks.push({when:c.expected_at,label:fullname(c.contact_id),detail:'Očekávaná provize '+euros(c.commission_expected),cid:c.contact_id,icon:'coins'});});
 return tasks.sort((a,b)=>a.when.localeCompare(b.when));
}
function rowTask(t){
 return '<div class="simple-row"><span class="row-icon">'+icon(t.icon||'calendar')+'</span><div class="row-content"><strong>'+esc(t.label)+'</strong><small>'+esc(t.detail)+'</small></div><span class="row-date">'+when(t.when)+'</span><button data-person="'+Number(t.cid)+'">Otevřít</button></div>';
}
function noRows(label){return '<div class="empty">'+esc(label)+'</div>';}
function kpi(label,value,hint,emoji){return '<div class="kpi"><i>'+emoji+'</i><span>'+esc(label)+'</span><strong>'+value+'</strong><small>'+esc(hint)+'</small></div>';}
function renderOverview(){
 const signed=store.contracts.filter(c=>c.status==='signed');
 const paid=sum(store.contracts,'commission_paid');
 const upcoming=taskRows().slice(0,5);
 $('kpi-grid').innerHTML=
  kpi('Kontakty',INT.format(store.contacts.length),'V tvé databázi','👥')+
  kpi('Schůzky',INT.format(store.activities.filter(a=>a.kind.startsWith('meeting')).length),'Zapsané návštěvy','🤝')+
  kpi('Smlouvy',INT.format(signed.length),'Uzavřené obchody','✍️')+
  kpi('Provize vyplacené',euros(paid),'Skutečně evidované','💰');
 $('next-up').innerHTML=upcoming.length?upcoming.map(rowTask).join(''):noRows('Zatím nic nečeká. Začni novým kontaktem. 🌱');
 const expected=sum(store.contracts.filter(c=>c.status!=='cancelled'),'commission_expected');
 const open=store.opportunities.filter(o=>o.status==='open');
 $('money-overview').innerHTML='<div class="money-line"><span>💰 Očekávané provize</span><strong>'+euros(expected)+'</strong></div>'+
 '<div class="money-line"><span>✅ Evidované výplaty</span><strong>'+euros(paid)+'</strong></div>'+
 '<div class="money-line"><span>🌱 Otevřené další příležitosti</span><strong>'+INT.format(open.length)+'</strong></div>'+
 '<div class="money-line"><span>🤝 Získaná doporučení</span><strong>'+INT.format(store.referrals.length)+'</strong></div>';
}
function renderPeople(){
 const needle=($('people-search').value||'').toLocaleLowerCase('cs-CZ').trim();
 const stage=$('people-stage').value;
 const all=store.contacts.filter(c=>{
  if(stage&&c.stage!==stage)return false;
  return !needle||[full(c),c.phone,c.email,c.source,c.address].join(' ').toLocaleLowerCase('cs-CZ').includes(needle);
 }).sort((a,b)=>full(a).localeCompare(full(b),'cs'));
 $('people-list').innerHTML=all.map(c=>
 '<div class="people-card"><div class="avatar">'+esc(initials(c))+'</div><div class="person-info"><strong>'+esc(full(c))+'</strong><small>'+esc(c.phone||c.email||c.source||'Připravený kontakt')+'</small></div><span class="stage-badge'+(c.stage==='contract'?' signed':'')+'">'+esc(STAGES[c.stage]||'Kontakt')+'</span><button data-person="'+Number(c.id)+'">Detail →</button></div>').join('')||noRows(store.contacts.length?'Nenalezen žádný odpovídající kontakt.':'Přidej prvního člověka a pusť se do toho! 💚');
}
function renderDay(){
 const tasks=taskRows();
 $('day-tasks').innerHTML=tasks.length?tasks.slice(0,70).map(rowTask).join(''):noRows('Všechny další kroky najdeš tady. Zatím máš volno.');
 const acts=store.activities.slice().sort((a,b)=>b.happened_at.localeCompare(a.happened_at)||b.id-a.id).slice(0,16);
 $('day-entries').innerHTML=acts.length?acts.map(a=>{
  const rec=store.recordings.filter(r=>+r.activity_id===+a.id);
  return '<div class="simple-row"><span class="row-icon">'+icon(a.kind==='phone'?'phone':'calendar')+'</span><div class="row-content"><strong>'+esc(fullname(a.contact_id))+'</strong><small>'+esc(KINDS[a.kind]||'Zápis')+' · '+when(a.happened_at)+' · '+esc(a.notes||'Bez poznámky')+'</small></div><button data-coach="'+Number(a.id)+'">✨ Trénink</button>'+ (rec.length?'<button data-audio="'+Number(rec[0].id)+'">▶</button>':'<button data-record="'+Number(a.id)+'">🎙️</button>')+'</div>';
 }).join(''):noRows('Zatím tu nejsou žádné zápisy. Přidej schůzku a můžeš nahrávat.');
}
function renderResults(){
 const signed=store.contracts.filter(c=>c.status==='signed');
 const meets=store.activities.filter(a=>a.kind.startsWith('meeting'));
 const pay=sum(store.contracts,'commission_paid');
 const expected=sum(store.contracts.filter(c=>c.status!=='cancelled'),'commission_expected');
 $('results-kpis').innerHTML=kpi('Kontaktů',INT.format(store.contacts.length),'Celkem','👥')+
 kpi('Schůzek',INT.format(meets.length),'První i další','🤝')+
 kpi('Smluv',INT.format(signed.length),'Uzavřeno','✍️')+
 kpi('Vyplacené provize',euros(pay),'Dle evidence','💰');
 const stages=['contact','phone','meeting1','meeting2','contract','care'];
 const counts=stages.map(s=>store.contacts.filter(c=>c.stage===s).length);
 const max=Math.max(1,...counts);
 $('funnel').innerHTML=stages.map((s,i)=>
 '<div class="funnel-row"><div class="funnel-meta"><strong>'+STAGES[s]+'</strong><span>'+counts[i]+'</span></div><div class="funnel-meter"><span style="width:'+Math.max(0,(100*counts[i]/max))+'%"></span></div></div>').join('')+
 '<p class="empty">Aktuální fáze kontaktů, nikoli historická konverze.</p>';
 const outstanding=Math.max(0,expected-pay);
 const slices=expected+pay ? Math.round(100*pay/Math.max(expected,pay)) : 0;
 $('commissions').innerHTML='<div class="donut-layout"><div class="donut" style="background:conic-gradient(#16614d 0% '+slices+'%,#d8b25a '+slices+'% 100%)"><strong>'+slices+' %</strong></div><div class="donut-legend"><span><b>✅ Vyplaceno</b><em>'+euros(pay)+'</em></span><span><b>🕒 Z očekávaných zbývá</b><em>'+euros(outstanding)+'</em></span><span><b>📅 Celkově očekáváno</b><em>'+euros(expected)+'</em></span></div></div><p class="empty">Pozor: pouze ručně zadaná data, ne bankovní potvrzení.</p>';
 const now=new Date();
 const months=Array.from({length:6},(_,i)=>{const d=new Date(now.getFullYear(),now.getMonth()-5+i,1);return {key:d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0'),text:d.toLocaleDateString('cs-CZ',{month:'short'})};});
 const vals=months.map(m=>({text:m.text,meets:meets.filter(a=>a.happened_at.startsWith(m.key)).length,signed:signed.filter(c=>c.created_at.startsWith(m.key)).length}));
 const upper=Math.max(1,...vals.map(x=>Math.max(x.meets,x.signed)));
 $('trend-chart').innerHTML='<div class="chart-bars">'+vals.map(x=>'<div class="bar-group"><div class="bar-pair"><div class="bar" title="Schůzky: '+x.meets+'" style="height:'+(x.meets/upper*112)+'px"></div><div class="bar signed" title="Smlouvy: '+x.signed+'" style="height:'+(x.signed/upper*112)+'px"></div></div><small>'+esc(x.text)+'</small></div>').join('')+'</div><div class="legend"><span><i></i> Schůzky</span><span><i class="signed"></i> Smlouvy</span></div>';
 const opp=store.opportunities.slice().sort((a,b)=>String(a.next_at||'9999').localeCompare(String(b.next_at||'9999')));
 $('crosssell-list').innerHTML=opp.length?opp.slice(0,12).map(o=>'<div class="simple-row"><span class="row-icon">'+icon('branch')+'</span><div class="row-content"><strong>'+esc(fullname(o.contact_id))+'</strong><small>'+esc(o.product)+' · '+esc(o.status==='open'?'Otevřeno':o.status==='won'?'Úspěch':'Neuskutečněno')+'</small></div><span class="row-date">'+euros(o.potential)+'</span><button data-person="'+Number(o.contact_id)+'">Detail</button></div>').join(''):noRows('Křížový prodej založíš jednoduše z karty člověka.');
 const contracts=store.contracts.filter(c=>c.status!=='cancelled').sort((a,b)=>String(a.expected_at||'9999').localeCompare(String(b.expected_at||'9999')));
 $('commission-list').innerHTML=contracts.length?contracts.map(c=>'<div class="simple-row"><span class="row-icon">'+icon('coins')+'</span><div class="row-content"><strong>'+esc(fullname(c.contact_id))+' · '+esc(c.product)+'</strong><small>Očekáváno '+euros(c.commission_expected)+' · vyplaceno '+euros(c.commission_paid)+'</small></div><span class="row-date">'+when(c.expected_at)+'</span><button data-person="'+Number(c.contact_id)+'">Detail</button></div>').join(''):noRows('Provize se zobrazí po uložení smlouvy.');
}
function person(cid){
 const c=findContact(cid);if(!c)return toast('Klient nebyl nalezen.',true);
 personId=+cid;
 $('person-title').textContent=full(c);
 const acts=store.activities.filter(a=>+a.contact_id===+cid).sort((a,b)=>b.id-a.id);
 const contracts=store.contracts.filter(a=>+a.contact_id===+cid);
 const opportunities=store.opportunities.filter(a=>+a.contact_id===+cid);
 const refs=store.referrals.filter(a=>+a.contact_id===+cid);
 const recordings=store.recordings;
 let html='<div class="person-summary"><p>📞 '+esc(c.phone||'Telefon neuveden')+' &nbsp; ✉ '+esc(c.email||'E-mail neuveden')+'</p><p>📍 '+esc(c.address||'Adresa neuvedena')+' &nbsp; · Zdroj: '+esc(c.source||'nezadán')+'</p><p>Aktuální fáze: <b>'+esc(STAGES[c.stage]||'Kontakt')+'</b> · Péče: každé '+(Number(c.care_months)||3)+' měsíce</p>'+(c.notes?'<p>📝 '+esc(c.notes)+'</p>':'')+'</div>';
 html+='<div class="person-actions"><button data-action="edit" data-cid="'+Number(cid)+'">'+icon('users')+' Upravit</button><button data-action="activity" data-cid="'+Number(cid)+'">'+icon('mic')+' Schůzka / telefon</button><button data-action="contract" data-cid="'+Number(cid)+'">'+icon('file')+' Nová smlouva</button><button data-action="care" data-cid="'+Number(cid)+'">'+icon('heart')+' Následná péče</button><button data-action="opportunity" data-cid="'+Number(cid)+'">'+icon('branch')+' Další prodej</button><button data-action="referral" data-cid="'+Number(cid)+'">'+icon('gift')+' Doporučení</button></div>';
 html+='<section class="detail-section"><h3>📝 Schůzky, telefonáty a zápisy ('+acts.length+')</h3>';
 html+=acts.length?acts.map(a=>{
  const rec=recordings.filter(r=>+r.activity_id===+a.id);
  return '<div class="detail-row"><div><b>'+esc(KINDS[a.kind]||'Poznámka')+' · '+when(a.happened_at)+'</b><small>'+esc(a.notes||'Bez poznámek')+' · '+esc(a.outcome||'')+'</small>'+(a.next_at?'<small>Další krok: '+when(a.next_at)+'</small>':'')+'</div><button data-coach="'+Number(a.id)+'">✨ AI trénink</button>'+(rec.length?'<button data-audio="'+Number(rec[0].id)+'">▶ Audio</button>':'<button data-record="'+Number(a.id)+'">🎙️ Nahrát</button>')+'</div>';
 }).join(''):noRows('Zatím žádná schůzka.');
 html+='</section><section class="detail-section"><h3>✍️ Smlouvy a provize ('+contracts.length+')</h3>';
 html+=contracts.length?contracts.map(x=>'<div class="detail-row"><div><b>'+esc(x.product)+'</b><small>Objem '+euros(x.value)+' · očekáváno '+euros(x.commission_expected)+' ('+when(x.expected_at)+')</small><small>Vyplaceno '+euros(x.commission_paid)+' ('+when(x.paid_at)+') · '+esc(x.status)+'</small></div></div>').join(''):noRows('Zatím bez smlouvy.');
 html+='</section><section class="detail-section"><h3>🌱 Další prodej ('+opportunities.length+')</h3>';
 html+=opportunities.length?opportunities.map(o=>'<div class="detail-row"><div><b>'+esc(o.product)+'</b><small>Potenciál '+euros(o.potential)+' · provize '+euros(o.expected_commission)+' · kontakt '+when(o.next_at)+'</small><small>'+esc(o.notes||'')+' · '+esc(o.status)+'</small></div></div>').join(''):noRows('Co dalšího by klient mohl potřebovat? Ověř jeho potřeby.');
 html+='</section><section class="detail-section"><h3>🤝 Doporučení ('+refs.length+')</h3>';
 html+=refs.length?refs.map(r=>'<div class="detail-row"><div><b>'+esc(r.name)+'</b><small>'+esc(r.phone)+' · '+(Number(r.permission)?'Souhlas s oslovením uveden':'Bez potvrzeného oprávnění k oslovení')+'</small></div></div>').join(''):noRows('Po úspěšné schůzce se můžeš zeptat na doporučení.');
 html+='</section>';
 $('person-body').innerHTML=html;openDialog('person-dialog');
}
function contactOptions(selected){
 return '<option value="">Vyber člověka…</option>'+store.contacts.slice().sort((a,b)=>full(a).localeCompare(full(b),'cs')).map(c=>'<option value="'+Number(c.id)+'" '+(+selected===+c.id?'selected':'')+'>'+esc(full(c))+'</option>').join('');
}
function productOptions(selected){
 return PRODUCTS.map(p=>'<option value="'+esc(p)+'" '+(p===selected?'selected':'')+'>'+esc(p)+'</option>').join('');
}
function field(label,name,value='',type='text',required=false,extra=''){
 return '<label>'+esc(label)+'<input name="'+esc(name)+'" type="'+type+'" '+(required?'required ':'')+extra+' value="'+esc(value??'')+'"></label>';
}
function textArea(label,name,value='',placeholder=''){
 return '<label>'+esc(label)+'<textarea name="'+esc(name)+'" rows="3" placeholder="'+esc(placeholder)+'">'+esc(value??'')+'</textarea></label>';
}
function selectField(label,name,options){
 return '<label>'+esc(label)+'<select name="'+esc(name)+'">'+options+'</select></label>';
}
function openForm(kind,cid=0,extra={}){
 if(kind!=='contact'&&kind!=='edit'&&!store.contacts.length){toast('Nejdřív přidej prvního člověka. 🙂');kind='contact';}
 if($('person-dialog').open)$('person-dialog').close();
 formKind=kind;formPrefill=extra;editingContactId=kind==='edit'?+cid:0;
 const c=kind==='edit'?findContact(cid):null;
 let title='',html='';
 if(kind==='contact'||kind==='edit'){
  title=kind==='edit'?'Upravit člověka':'Nový kontakt 👋';
  html='<div class="notice">Začni jen jménem a příjmením. Ostatní údaje můžeš doplnit později.</div>'+
  '<div class="row-fields">'+field('Jméno *','first_name',c?.first_name||'','text',true,'maxlength="100"')+field('Příjmení *','last_name',c?.last_name||'','text',true,'maxlength="100"')+'</div>'+
  '<div class="row-fields">'+field('📞 Telefon','phone',c?.phone||'','tel',false,'maxlength="70"')+field('✉ E-mail','email',c?.email||'','email',false,'maxlength="254"')+'</div>'+
  field('Adresa','address',c?.address||'')+field('Zdroj kontaktu','source',c?.source||'')+
  selectField('Fáze','stage',Object.entries(STAGES).map(([v,n])=>'<option value="'+v+'" '+((c?.stage||'contact')===v?'selected':'')+'>'+n+'</option>').join(''))+
  textArea('Poznámky','notes',c?.notes||'')+
  '<div class="row-fields">'+selectField('Pravidelná péče','care_months',[1,3,6,12].map(n=>'<option value="'+n+'" '+(Number(c?.care_months||3)===n?'selected':'')+'>Každé '+n+' měs.</option>').join(''))+field('Další péče','next_care',c?.next_care||'','date')+'</div>';
 }else if(kind==='activity'||kind==='care'){
  title=kind==='care'?'Péče o klienta 💚':'Nový zápis ze schůzky 🎙️';
  const selected=cid||0;
  const wanted=kind==='care'?'care':(extra.kind||'meeting1');
  html=selectField('👤 Člověk *','contact_id',contactOptions(selected))+
  selectField('Co právě proběhlo?','kind',Object.entries(KINDS).map(([v,n])=>'<option value="'+v+'" '+(wanted===v?'selected':'')+'>'+esc(n)+'</option>').join(''))+
  field('Datum','happened_at',TODAY(),'date',true)+
  textArea('1. Co jste probírali?','notes','','Hlavní téma, přání klienta, otázky…')+
  field('2. Jak to dopadlo?','outcome','','text',false,'placeholder="Např. domluven návrh / zájem / odmítnuto"')+
  field('3. Kdy je další krok?','next_at','','date')+
  '<div class="notice">🎙️ Po uložení můžeš přidat nahrávku schůzky. V telefonu přímo v prohlížeči lze nahrávat mikrofon; telefonní hovory nahrané jinde lze přiložit jako soubor.</div>';
 }else if(kind==='contract'){
  title='Nová smlouva ✍️';
  html=selectField('👤 Klient *','contact_id',contactOptions(cid))+
  selectField('Produkt','product',productOptions())+
  '<div class="row-fields">'+field('Objem smlouvy v Kč','value','0','number',false,'min="0" step="0.01"')+
  field('Očekávaná provize v Kč','commission_expected','0','number',false,'min="0" step="0.01"')+'</div>'+
  '<div class="row-fields">'+field('Kdy má přijít provize?','expected_at','','date')+
  selectField('Stav smlouvy','status','<option value="signed">Uzavřená</option><option value="pending">Rozpracovaná</option><option value="cancelled">Stornovaná</option>')+'</div>'+
  '<div class="row-fields">'+field('Kolik je skutečně vyplaceno v Kč','commission_paid','0','number',false,'min="0" step="0.01"')+
  field('Skutečné datum výplaty','paid_at','','date')+'</div>'+
  textArea('Poznámka','notes','','Doplň číslo smlouvy nebo podmínky…')+
  '<div class="notice">Provize jsou odděleny na očekávané a skutečně vyplacené. V ostrém provozu je ověř podle vyúčtování.</div>';
 }else if(kind==='opportunity'){
  title='Co dalšího nabídnout? 🌱';
  html=selectField('👤 Klient *','contact_id',contactOptions(cid))+
  selectField('Produkt, o kterém se pobavíme','product',productOptions())+
  textArea('Proč by to klient mohl potřebovat?','notes','','Nejdřív ověř přání, potřeby a vhodnost produktu…')+
  '<div class="row-fields">'+field('Potenciál v Kč','potential','0','number',false,'min="0" step="0.01"')+
  field('Možná provize v Kč','expected_commission','0','number',false,'min="0" step="0.01"')+'</div>'+
  '<div class="row-fields">'+field('Kdy se ozvu?','next_at','','date')+
  selectField('Stav příležitosti','status','<option value="open">Otevřeno</option><option value="won">Podařilo se</option><option value="lost">Nevyšlo</option>')+'</div>'+
  '<div class="notice">Nápad na další prodej není automatické investiční doporučení. Vhodnost ověř při jednání.</div>';
 }else if(kind==='referral'){
  title='Získat doporučení 🤝';
  html='<div class="notice">Zkus se zeptat: „Co se vám na naší spolupráci líbí?“ „Komu dalšímu by mohlo něco podobného pomoci?“</div>'+
  selectField('👤 Doporučující klient','contact_id',contactOptions(cid))+
  field('Jméno doporučeného člověka *','name','','text',true)+
  field('Telefon doporučeného','phone','','tel')+
  '<label><input type="checkbox" name="permission" value="1" style="width:auto;margin-right:8px">Mám ověřené oprávnění doporučeného člověka kontaktovat.</label>'+
  textArea('Poznámka','notes','')+
  '<div class="notice">Bez oprávnění kontakt neoslovuj. Zapisuj jen nezbytné osobní údaje.</div>';
 }
 $('form-title').textContent=title;
 $('form-fields').innerHTML=html;
 $('form-error').textContent='';
 openDialog('form-dialog');
}
async function saveForm(e){
 e.preventDefault();const form=e.currentTarget;const data=Object.fromEntries(new FormData(form));
 let kind=formKind==='edit'?'contact':formKind==='care'?'activity':formKind;
 if(formKind==='edit')data.id=editingContactId;
 if(formKind==='referral')data.permission=form.elements.permission?.checked?1:0;
 if(kind!=='contact'&&!data.contact_id){$('form-error').textContent='Vyber člověka.';return;}
 try{
  const saveButton=form.querySelector('[type="submit"]');saveButton.disabled=true;
  const result=await request(kind,data);
  $('form-dialog').close();await refresh();
  toast('Uloženo! Další krok je zase o něco blíž. ✨');
  if(kind==='contact'){go('people');person(result.id);}
  else if(kind==='activity'){
   go('day');
   if(formPrefill.record || wantRecording){wantRecording=false;openAudio(result.id);}
  }else if(kind==='contract'||kind==='referral'||kind==='opportunity'){
   go('results');
  }
 }catch(err){$('form-error').textContent=err.message;}
 finally{form.querySelector('[type="submit"]').disabled=false;}
}
function openDialog(id){const el=$(id);if(el&&!el.open)el.showModal();}
function closeDialog(id){const el=$(id);if(el&&el.open)el.close();}
function openAudio(activityId){
 recordActivityId=+activityId;recordBlob=null;$('audio-file').value='';$('audio-preview').hidden=true;$('audio-preview').removeAttribute('src');
 $('audio-error').textContent='';$('save-audio').disabled=true;$('record-time').textContent='00:00';
 $('start-record').disabled=false;$('stop-record').disabled=true;
 if($('person-dialog').open)$('person-dialog').close();
 openDialog('audio-dialog');
}
async function startRecording(){
 $('audio-error').textContent='';
 if(!window.MediaRecorder||!navigator.mediaDevices?.getUserMedia){$('audio-error').textContent='Tento prohlížeč nepodporuje nahrávání. Přilož zvukový soubor.';return;}
 try{
  recordStream=await navigator.mediaDevices.getUserMedia({audio:true});
  const variants=['audio/webm;codecs=opus','audio/webm','audio/mp4'];
  const mime=variants.find(t=>MediaRecorder.isTypeSupported(t));
  recorder=new MediaRecorder(recordStream,mime?{mimeType:mime}:{});
  recordChunks=[];recordBlob=null;
  recorder.ondataavailable=e=>{if(e.data.size)recordChunks.push(e.data);};
  recorder.onstop=()=>{
   recordBlob=new Blob(recordChunks,{type:recorder.mimeType||'audio/webm'});
   recordStream?.getTracks().forEach(t=>t.stop());recordStream=null;
   if(recordObjectUrl)URL.revokeObjectURL(recordObjectUrl);
   recordObjectUrl=URL.createObjectURL(recordBlob);
   $('audio-preview').src=recordObjectUrl;$('audio-preview').hidden=false;
   $('save-audio').disabled=!recordBlob.size;
   $('recorder-circle').classList.remove('recording');
  };
  recorder.start(1000);recordStart=Date.now();$('start-record').disabled=true;$('stop-record').disabled=false;
  $('recorder-circle').classList.add('recording');
  clearInterval(recordTimer);recordTimer=setInterval(()=>{
   const sec=Math.floor((Date.now()-recordStart)/1000);
   $('record-time').textContent=String(Math.floor(sec/60)).padStart(2,'0')+':'+String(sec%60).padStart(2,'0');
  },500);
 }catch(err){$('audio-error').textContent='Mikrofon nelze zapnout: '+err.message;}
}
function stopRecording(){
 if(recorder?.state==='recording')recorder.stop();
 clearInterval(recordTimer);$('stop-record').disabled=true;$('start-record').disabled=false;
}
async function saveAudio(){
 let file=$('audio-file').files[0];
 if(!file&&recordBlob){
  let ext=recordBlob.type.includes('mp4')?'m4a':'webm';
  file=new File([recordBlob],'schuzka-'+TODAY()+'.'+ext,{type:recordBlob.type});
 }
 if(!file){toast('Nejdřív nahraj nebo vyber audio.',true);return;}
 if(file.size>50*1024*1024){toast('Soubor je příliš velký (max. 50 MB).',true);return;}
 const fd=new FormData();fd.set('activity_id',String(recordActivityId));fd.set('audio',file);
 try{$('save-audio').disabled=true;await request('upload',fd,true);closeDialog('audio-dialog');await refresh();toast('Nahrávka uložena bezpečně ke schůzce. 🎙️');}
 catch(err){$('audio-error').textContent=err.message;$('save-audio').disabled=false;}
}
function openCoach(id){
 const a=store.activities.find(x=>+x.id===+id);
 if(!a)return toast('Zápis nenalezen.',true);
 coachActivityId=+id;$('coach-transcript').value=a.transcript||'';$('coach-review').value=a.review||'';$('coach-error').textContent='';
 if($('person-dialog').open)$('person-dialog').close();
 openDialog('coach-dialog');
}
async function saveCoach(){
 try{await request('review',{id:coachActivityId,transcript:$('coach-transcript').value,review:$('coach-review').value});await refresh();toast('Tvůj přepis a sebehodnocení jsou uložené.');}
 catch(e){$('coach-error').textContent=e.message;}
}
async function copyCoach(){
 const text=$('coach-transcript').value.trim();
 if(!text){$('coach-error').textContent='Nejdřív vlož přepis nebo hlavní věty ze schůzky.';return;}
 const prompt='Jsi můj profesionální obchodní kouč. Vyhodnoť průběh schůzky empaticky a s ohledem na etiku finančního poradenství. Navrhni: 1) co se povedlo, 2) 3 konkrétní zlepšení, 3) jak lépe naslouchat, 4) jak domluvit další krok, 5) otázky pro trénink rozhovoru. Nepřipisuj klientovi výroky, které nejsou v přepisu.\\n\\nPŘEPIS (anonymizován před vložením):\\n'+text+'\\n\\nMÉ HODNOCENÍ:\\n'+$('coach-review').value;
 try{await navigator.clipboard.writeText(prompt);toast('Připravený text pro AI kouče je zkopírovaný. Zkontroluj anonymizaci. ✨');}
 catch(e){$('coach-error').textContent='Kopírování se nezdařilo. Zkus HTTPS a povolení schránky.';}
}
function csvRows(text){
 const delimiter=(text.split(/\r?\n/,1)[0].match(/;/g)||[]).length >= (text.split(/\r?\n/,1)[0].match(/,/g)||[]).length?';':',';
 const rows=[];let row=[],part='',quote=false;
 for(let i=0;i<text.length;i++){
  const ch=text[i];
  if(ch==='"'){if(quote&&text[i+1]==='"'){part+='"';i++;}else quote=!quote;}
  else if(ch===delimiter&&!quote){row.push(part);part='';}
  else if((ch==='\n'||ch==='\r')&&!quote){if(ch==='\r'&&text[i+1]==='\n')i++;row.push(part);part='';if(row.some(Boolean))rows.push(row);row=[];}
  else part+=ch;
 }
 row.push(part);if(row.some(Boolean))rows.push(row);
 return rows;
}
function normalizeName(s){return String(s||'').trim().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[\s_\-]+/g,'');}
function importMap(headers){
 const synonyms={
  first_name:['jmeno','firstname','name','krestnijmeno'],
  last_name:['prijmeni','lastname','surname'],
  phone:['telefon','telefonni','mobil','mobile','phone','tel'],
  email:['email','mail','emailovaadresa'],
  address:['adresa','address'],
  source:['zdroj','zdrojkontaktu','source'],
  notes:['poznamky','poznamka','notes'],
 };
 const result={};for(const [name,variants] of Object.entries(synonyms))result[name]=headers.findIndex(h=>variants.includes(normalizeName(h)));
 return result;
}
async function importFile(event){
 const file=event.target.files[0];event.target.value='';if(!file)return;
 if(file.size>5*1024*1024){toast('Import má limit 5 MB.',true);return;}
 let rows;
 try{
  if(/\.xlsx?$/i.test(file.name)){
   if(typeof XLSX==='undefined'){toast('Import Excel XLSX bude doplněn. Ulož list do CSV UTF-8 a importuj CSV.',true);return;}
   const wb=XLSX.read(await file.arrayBuffer(),{type:'array'});rows=XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]],{header:1,defval:''});
  }else rows=csvRows((await file.text()).replace(/^\uFEFF/,''));
  if(!rows||rows.length<2)throw Error('Tabulka je prázdná.');
  const map=importMap(rows[0]);
  if(map.first_name<0||map.last_name<0)throw Error('Nenalezeny sloupce Jméno a Příjmení. Uprav názvy hlaviček.');
  const records=rows.slice(1).map(r=>Object.fromEntries(Object.entries(map).map(([k,i])=>[k,i>=0?String(r[i]??'').trim():'']))).filter(r=>r.first_name&&r.last_name);
  if(records.length>250)throw Error('V jedné dávce lze naimportovat nejvýše 250 kontaktů.');
  if(!confirm('Importovat '+records.length+' kontaktů? Existující e-maily a telefony budou přeskočeny.'))return;
  const known=new Set(store.contacts.flatMap(c=>[c.email&&'e:'+c.email.toLowerCase(),c.phone&&'p:'+c.phone.replace(/\D/g,'')]).filter(Boolean));
  let created=0,skipped=0,failed=0;
  for(const record of records){
   const keyE=record.email&&'e:'+record.email.toLowerCase(),keyP=record.phone&&'p:'+record.phone.replace(/\D/g,'');
   if((keyE&&known.has(keyE))||(keyP&&known.has(keyP))){skipped++;continue;}
   try{await request('contact',record);created++;if(keyE)known.add(keyE);if(keyP)known.add(keyP);}
   catch(e){failed++;}
  }
  await refresh();go('people');toast('Import: '+created+' přidáno, '+skipped+' duplicit, '+failed+' chyb.');
 }catch(e){toast(e.message,true);}
}
function downloadJSON(name,data,type='application/json'){
 const blob=new Blob([data],{type:type+';charset=utf-8'});
 const a=document.createElement('a');const url=URL.createObjectURL(blob);
 a.href=url;a.download=name;document.body.append(a);a.click();a.remove();
 setTimeout(()=>URL.revokeObjectURL(url),1000);
}
async function logout(){
 try{await request('logout',{});window.location.reload();}
 catch(e){toast(e.message,true);}
}
document.addEventListener('click',event=>{
 const target=event.target.closest('button,[data-action],[data-view],[data-person],[data-close],[data-record],[data-coach],[data-audio]');
 if(!target)return;
 if(target.dataset.close){closeDialog(target.dataset.close);return;}
 if(target.dataset.view){go(target.dataset.view);return;}
 if(target.dataset.person){person(Number(target.dataset.person));return;}
 if(target.dataset.action){
  const kind=target.dataset.action;
  const cid=Number(target.dataset.cid)||0;
  openForm(kind,cid,kind==='activity'?{record:true}:{});
  return;
 }
 if(target.dataset.record){openAudio(Number(target.dataset.record));return;}
 if(target.dataset.coach){openCoach(Number(target.dataset.coach));return;}
 if(target.dataset.audio){
  const id=Number(target.dataset.audio);
  const audio=new Audio('api.php?action=media&id='+id);
  audio.play().catch(()=>toast('Nahrávku nelze přehrát.',true));
 }
});
$('auth-form').addEventListener('submit',async event=>{
 event.preventDefault();const data=Object.fromEntries(new FormData(event.target));
 const isFirst=!$('setup-row').hidden;
 $('auth-error').textContent='';
 try{
  $('auth-submit').disabled=true;
  await request(isFirst?'register':'login',data);
  await signedIn();toast('Vítej v Moje schůzka! 💚');
 }catch(e){$('auth-error').textContent=e.message;}
 finally{$('auth-submit').disabled=false;}
});
$('dynamic-form').addEventListener('submit',saveForm);
$('people-search').addEventListener('input',renderPeople);
$('people-stage').addEventListener('change',renderPeople);
$('hamburger').addEventListener('click',()=>{const open=$('sidebar').classList.toggle('open');$('shade').hidden=!open;$('hamburger').setAttribute('aria-expanded',String(open));});
$('shade').addEventListener('click',()=>{$('sidebar').classList.remove('open');$('shade').hidden=true;$('hamburger').setAttribute('aria-expanded','false');});
$('logout').addEventListener('click',logout);
$('import-action').addEventListener('click',()=>$('import-file').click());
$('import-file').addEventListener('change',importFile);
$('export-action').addEventListener('click',()=>{window.location.href='api.php?action=csv';});
$('backup-action').addEventListener('click',()=>downloadJSON('moje-schuzka-zaloha-'+TODAY()+'.json',JSON.stringify({exported_at:new Date().toISOString(),...store},null,2)));
$('export-results').addEventListener('click',()=>downloadJSON('moje-schuzka-vysledky-'+TODAY()+'.json',JSON.stringify({exported_at:new Date().toISOString(),...store},null,2)));
$('start-record').addEventListener('click',startRecording);
$('stop-record').addEventListener('click',stopRecording);
$('audio-file').addEventListener('change',()=>{$('save-audio').disabled=!$('audio-file').files.length;});
$('save-audio').addEventListener('click',saveAudio);
$('coach-save').addEventListener('click',saveCoach);
$('coach-copy').addEventListener('click',copyCoach);
$('audio-dialog').addEventListener('close',()=>{if(recorder?.state==='recording')recorder.stop();clearInterval(recordTimer);recordStream?.getTracks().forEach(t=>t.stop());});
start();
