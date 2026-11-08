'use strict';
const $ = id => document.getElementById(id);
const CZK = new Intl.NumberFormat('cs-CZ',{style:'currency',currency:'CZK',maximumFractionDigits:0});
const INT = new Intl.NumberFormat('cs-CZ');
const TODAY = () => {
 const date=new Date();
 return [date.getFullYear(),String(date.getMonth()+1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');
};
const STAGES = {contact:'Kontakt',phone:'Telefon',meeting1:'1. schůzka',meeting2:'Další schůzky',contract:'Smlouva',care:'Péče'};
const KINDS = {phone:'📞 Telefonát',meeting1:'🤝 První schůzka',meeting2:'🤝 Druhá schůzka',meeting:'🤝 Další schůzka',email:'✉ E-mail',care:'💚 Péče',note:'✏️ Poznámka'};
const FOLLOWUPS = {
 phone:{label:'📞 Zavolat',kind:'phone',icon:'phone'},
 meeting:{label:'🤝 Domluvit schůzku',kind:'meeting',icon:'calendar'},
 email:{label:'✉️ Poslat e-mail nebo podklady',kind:'email',icon:'file'},
 care:{label:'💚 Navázat péčí',kind:'care',icon:'heart'},
 crosssell:{label:'🌱 Probrat další nabídku',kind:'meeting',icon:'branch'},
 referral:{label:'🎁 Požádat o doporučení',kind:'phone',icon:'gift'},
 cooperation:{label:'🤝 Probrat spolupráci',kind:'meeting',icon:'users'},
 recruitment:{label:'🧭 Probrat vstup do týmu',kind:'meeting',icon:'users'},
 team_onboarding:{label:'🎒 Naplánovat zaškolení',kind:'meeting',icon:'users'},
 team_coaching:{label:'🎯 Domluvit koučink nebo nácvik',kind:'meeting',icon:'users'},
 team_checkin:{label:'👥 Domluvit týmový rozhovor',kind:'meeting',icon:'users'},
 team_task:{label:'📋 Zadat nebo zkontrolovat týmový úkol',kind:'meeting',icon:'check'}
};
const ACTIVITY_RESULTS = {success:'✅ Úspěšná',open:'🟡 Otevřená',declined:'⚪ Bez dohody'};
const PRODUCTS = ['Kovový účet','Zlato','Stříbro','Bitcoinový účet','Permanentní fond','Dragon fond','Životní pojištění','Úrazové pojištění','Pojištění majetku','Pojištění nemovitosti','Pojištění firmy','Pojištění auta','Pojištění odpovědnosti','Cestovní pojištění','Jiný produkt'];
const COACHABLE_KINDS = ['phone','meeting1','meeting2','meeting'];
const COACHING_STAGES = [
 {id:'opening', emoji:'🤝', title:'Prolomení ledu a důvěra',
  cue:'Přirozeně navážu kontakt, vysvětlím účel rozhovoru a domluvím rámec.',
  exercise:'Představ si, že klient spěchá. Nacvič krátký, přirozený úvod a ověř, kolik má času.',
  example:'„Děkuju, že jste si našel čas. Jak se vám dnes daří? Je pořád v pořádku, když spolu máme 15 minut?“'},
 {id:'open_questions', emoji:'💬', title:'Otevřené otázky',
  cue:'Nechám klienta popsat situaci vlastními slovy a dám mu prostor domluvit.',
  exercise:'Klient říká, že chce mít v penězích větší pořádek. Polož dvě otevřené otázky a po každé chvíli počkej.',
  example:'„Co byste si přál mít vyřešené jako první?“'},
 {id:'narrowing', emoji:'🔎', title:'Zužující otázky',
  cue:'Navazuji na odpověď klienta a postupně upřesňuji priority, důvody a čas.',
  exercise:'Klient zmíní penzi i bydlení pro děti. Zjisti, co má přednost a proč.',
  example:'„Zmínil jste penzi i bydlení pro děti. Co má pro vás větší prioritu v příštích dvou letech?“'},
 {id:'closed_questions', emoji:'📌', title:'Uzavřené otázky',
  cue:'Potvrzuji konkrétní fakta krátkými otázkami s jednoznačnou odpovědí.',
  exercise:'Zopakuj jeden konkrétní údaj z rozhovoru a ověř ho otázkou ano/ne.',
  example:'„Je pro vás důležité mít tyto peníze k dispozici do dvou let?“'},
 {id:'verification', emoji:'🪞', title:'Ověření porozumění',
  cue:'Shrnu klientovu situaci vlastními slovy a dám mu prostor mě doplnit nebo opravit.',
  exercise:'Shrň dvě zjištěné priority vlastními slovy a vyžádej si korekci.',
  example:'„Rozumím tomu správně, že nejdřív chcete vytvořit rezervu a potom řešit penzi? Co mi případně uniklo?“'},
 {id:'closing', emoji:'🏁', title:'Uzavření a další krok',
  cue:'Domluvím s klientem, co se stane dál, kdo to udělá a do kdy.',
  exercise:'Klient se dnes nechce rozhodnout. Domluv další krok a termín bez tlaku na rozhodnutí.',
  example:'„Ve středu vám pošlu shrnutí. Hodí se vám v pátek krátký hovor, abychom prošli vaše otázky?“'}
];
const COACHING_AVERAGE = new Intl.NumberFormat('cs-CZ',{minimumFractionDigits:1,maximumFractionDigits:1});
let csrf = '';
let store = {contacts:[],activities:[],contracts:[],opportunities:[],referrals:[],recordings:[],scorecards:[]};
let view='overview', editingContactId=0, formKind='',formPrefill={};
let personId=0, coachActivityId=0, recordActivityId=0, wantRecording=false;
let scorecardActivityId=0;
let practiceStageId='';
let recorder=null, recordStream=null, recordChunks=[],recordBlob=null,recordTimer=null,recordStart=0,recordObjectUrl=null;
let toastTimer=null;
let activePlaybackUrl=null, activePlaybackActivityId=0, activePlaybackRecordingId=0, activePlaybackExt='webm';
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
const RETRY_AUTH_CSRF=new Set(['login','register','reset_request','reset_redeem']);
async function request(action,data,form=false,retryCsrf=true){
 const opt={credentials:'same-origin',cache:'no-store',headers:{}};
 if(data!==undefined){opt.method='POST';opt.headers['X-CSRF-Token']=csrf;if(form)opt.body=data;else{opt.headers['Content-Type']='application/json';opt.body=JSON.stringify(data);}}
 const response=await fetch('api.php?action='+encodeURIComponent(action),opt);
 let result;try{result=await response.json();}catch(_){throw Error('Server vrátil neplatnou odpověď. Zkontroluj PHP a umístění souborů.');}
 if(response.status===403&&result.code==='csrf_expired'&&retryCsrf&&RETRY_AUTH_CSRF.has(action)){
  const meta=await request('meta');
  csrf=meta.csrf;
  return request(action,data,form,false);
 }
 if(response.status===401&&signedInAccount&&action!=='login')location.reload();
 if(!response.ok||!result.ok)throw Error(result.error||'Chyba serveru');
 return result;
}
async function start(){
 try{
  const meta=await request('meta');
  csrf=meta.csrf;
  authSetup=meta.setup_required;
  resetEmailAvailable=meta.reset_available&&!authSetup;
  if(!meta.authenticated||resetToken){
    showAuth(resetToken?'redeem':'login');
    $('auth-form').elements.password.autocomplete=authSetup?'new-password':'current-password';
    $('auth-submit').innerHTML=meta.setup_required?'Vytvořit můj účet '+icon('arrow'):'Přihlásit se '+icon('arrow');
    $('auth-help').textContent=meta.setup_required?(meta.setup_available?'Zadej instalační klíč z privátní konfigurace na hostingu.':'Nejprve nastav instalační klíč ve config.local.php (viz README).'):'Přístup k pracovnímu prostoru ti založí správce.';
  }else await signedIn(meta);
 }catch(e){$('boot').textContent=e.message;$('boot').style.color='#b64742';return;}
 $('boot').hidden=true;
}
async function signedIn(meta){
 const account=meta||await request('meta');
 csrf=account.csrf;
 setAccountContext(account);
 await refresh();
 $('settings-email').value=account.email||'';
 applyUserPreferences();
 $('auth').hidden=true;$('app').hidden=false;
 $('date-now').textContent=new Date().toLocaleDateString('cs-CZ',{weekday:'long',day:'numeric',month:'long'});
 go(initialView());
}
async function refresh(){const res=await request('state');store=res.data;render();}
function go(next){
 if(!allowedScreen(next))return;
 const enteringSettings=next==='settings'&&(view!=='settings'||$('settings-view').hidden);
 if(view==='settings'&&next!=='settings'){
  applyAppearance(appearance);
  clearResetLink();
 }
 if(enteringSettings)beginUserSettings(view);
 view=next;
 $('app').classList.toggle('is-settings',next==='settings');
 document.querySelectorAll('.view').forEach(x=>x.hidden=x.id!==next+'-view');
 document.querySelectorAll('[data-view]').forEach(x=>x.classList.toggle('active',x.dataset.view===next));
 document.querySelectorAll('#side-nav [data-view]').forEach(x=>x.dataset.view===next?x.setAttribute('aria-current','page'):x.removeAttribute('aria-current'));
 $('crumb').textContent=menuLabel(next);
 $('settings-back').hidden=initialView()==='settings';
 $('sidebar').classList.remove('open');$('shade').hidden=true;$('hamburger').setAttribute('aria-expanded','false');
 if(next!=='settings')render();
 if(enteringSettings)$('settings-menu-panel').querySelector('h1').focus();
 window.scrollTo({top:0,behavior:next==='settings'?'instant':'smooth'});
}
function render(){
 renderOverview();renderPeople();renderDay();renderResults();
 if(view==='coaching'){renderCoaching();renderPracticeHistory();}
 if(view==='care')renderCareWorkspace();
 if(view==='results')renderRelationshipMetrics();
 if(view==='team')renderTeam();
 $('side-count').textContent=store.contacts.length||'';
 syncAccessControls();
}
function processForStage(stage){
 if(['phone','meeting','meeting1','meeting2'].includes(stage))return 'meeting';
 return ['contract','care'].includes(stage)?stage:'contact';
}
function processForTask(task){
 if(TEAM_FOLLOWUPS.includes(task.topic))return 'team';
 if(['care','referral'].includes(task.topic))return task.topic;
 if(task.icon==='coins'||task.topic==='crosssell')return 'contract';
 return 'meeting';
}
function phaseBadge(stage,index){
 return '<span class="phase-badge" aria-hidden="true">'+(index+1)+' '+stage.emoji+'</span>';
}
function renderCoaching(){
 const cards=new Map((store.scorecards||[]).map(card=>[Number(card.activity_id),card]));
 const activities=store.activities.filter(a=>COACHABLE_KINDS.includes(a.kind))
  .sort((a,b)=>String(b.happened_at).localeCompare(String(a.happened_at))||Number(b.id)-Number(a.id));
 const scored=activities.map(a=>cards.get(Number(a.id))).filter(Boolean);
 const current=scored.slice(0,3);
 const previous=scored.slice(3,6);
 function summary(group,id){
  const values=group.map(card=>card.scores?.[id]).filter(Number.isInteger);
  return {count:values.length,average:values.length?values.reduce((sum,value)=>sum+value,0)/values.length:null};
 }
 let focus=null;
 const rows=COACHING_STAGES.map((stage,index)=>{
  const now=summary(current,stage.id);
  const before=summary(previous,stage.id);
  if(now.count && (!focus || now.average<focus.average))focus={stage,average:now.average};
  const format=stat=>stat.count?COACHING_AVERAGE.format(stat.average)+' / 5 ('+stat.count+')':'—';
  return '<tr data-phase="'+stage.id+'"><th scope="row">'+phaseBadge(stage,index)+stage.title+'</th><td>'+format(now)+'</td><td>'+format(before)+'</td></tr>';
 }).join('');
 $('coaching-progress').innerHTML=scored.length
  ? '<p class="muted">Hodnocené rozhovory celkem <strong>'+scored.length+'</strong>. Číslo v závorce udává počet hodnocení dané fáze.</p>'+
    '<table class="coaching-table"><thead><tr><th scope="col">Fáze</th><th scope="col">Poslední 3</th><th scope="col">Předchozí 3</th></tr></thead><tbody>'+rows+'</tbody></table>'
  : '<p class="muted">Ohodnoť první rozhovor v seznamu níže. Jak budou přibývat další, uvidíš průměry jednotlivých fází i srovnání s předchozími rozhovory.</p>';
 const practice=scored.find(card=>card.practice)?.practice;
 const selectedStage=COACHING_STAGES.find(item=>item.id===practiceStageId);
 const stage=selectedStage||focus?.stage||COACHING_STAGES[0];
 $('coaching-focus-picker').querySelectorAll('button').forEach(button=>{
  button.setAttribute('aria-pressed',String(button.dataset.practiceStage===practiceStageId));
 });
 $('coaching-focus').dataset.phase=stage.id;
 $('coaching-focus').innerHTML='<h3>'+phaseBadge(stage,COACHING_STAGES.indexOf(stage))+stage.title+'</h3>'+
  (!selectedStage&&focus?'<p class="muted">Nejnižší průměr z posledních hodnocených rozhovorů ('+COACHING_AVERAGE.format(focus.average)+' / 5).</p>':'')+
  '<p>'+stage.exercise+'</p><blockquote>'+stage.example+'</blockquote>'+
  (practice?'<p class="coaching-practice"><strong>Můj poslední zapsaný krok</strong><br>'+esc(practice)+'</p>':'');
 $('coaching-activities').innerHTML=activities.slice(0,12).map(a=>{
  const card=cards.get(Number(a.id));
  const label=card?'Upravit hodnocení':'Ohodnotit';
  return '<div class="simple-row coaching-activity"><div class="row-content"><strong>'+esc(fullname(a.contact_id))+
   '</strong><small>'+esc(KINDS[a.kind])+' · '+when(a.happened_at)+'</small></div>'+
   '<button class="button outline" data-scorecard="'+Number(a.id)+'">'+label+'</button></div>';
 }).join('')||'<p class="coaching-body muted">Zatím tu není žádná schůzka ani telefonát. <button class="text-link" data-action="activity">Přidat zápis →</button></p>';
}

function openScorecard(id){
 if(!allowedAction('coaching_scorecard'))return;
 const activity=store.activities.find(a=>Number(a.id)===id);
 if(!activity||!COACHABLE_KINDS.includes(activity.kind))return;
 const card=(store.scorecards||[]).find(item=>Number(item.activity_id)===id);
 scorecardActivityId=id;
 $('scorecard-context').textContent=fullname(activity.contact_id)+' · '+KINDS[activity.kind]+' · '+when(activity.happened_at);
 $('scorecard-skills').innerHTML=COACHING_STAGES.map((stage,index)=>
  '<label data-phase="'+stage.id+'"><span>'+phaseBadge(stage,index)+stage.title+'<small>'+stage.cue+'</small></span><select name="'+stage.id+'">'+
  '<option value="">Bez skóre</option>'+[1,2,3,4,5].map(value=>
   '<option value="'+value+'"'+(card?.scores?.[stage.id]===value?' selected':'')+'>'+value+'</option>'
  ).join('')+'</select></label>'
 ).join('');
 $('scorecard-form').elements.practice.value=card?.practice||'';
 $('scorecard-error').textContent='';
 openDialog('scorecard-dialog');
}
async function saveScorecard(event){
 event.preventDefault();
 const form=new FormData(event.currentTarget);
 const scores=Object.fromEntries(COACHING_STAGES
  .filter(stage=>form.get(stage.id))
  .map(stage=>[stage.id,Number(form.get(stage.id))]));
 const error=$('scorecard-error');
 if(!Object.keys(scores).length){error.textContent='Ohodnoť alespoň jednu fázi rozhovoru.';return;}
 const id=scorecardActivityId;
 $('scorecard-save').disabled=true;
 error.textContent='';
 try{
  await request('coaching_scorecard',{activity_id:id,scores,practice:form.get('practice')});
  await refresh();
  if(scorecardActivityId===id)closeDialog('scorecard-dialog');
  toast('Hodnocení rozhovoru je uložené.');
 }catch(e){
  if(scorecardActivityId===id)error.textContent=e.message;
 }finally{
  $('scorecard-save').disabled=false;
 }
}
function taskRows(){
 const tasks=[];
 store.activities.forEach(a=>{
  if(!a.next_at||a.followup_activity_id)return;
  const plan=FOLLOWUPS[a.next_kind];
  tasks.push({when:a.next_at,time:a.next_time,label:fullname(a.contact_id),detail:nextActionLabel(a),
   cid:a.contact_id,icon:plan?.icon||'calendar',activityId:a.id,topic:a.next_kind});
 });
 store.opportunities.forEach(o=>{if(o.next_at&&o.status==='open')tasks.push({when:o.next_at,label:fullname(o.contact_id),detail:'Následný prodej · '+o.product,cid:o.contact_id,icon:'branch'});});
 store.contacts.forEach(c=>{if(c.next_care)tasks.push({when:c.next_care,label:full(c),detail:'💚 Pravidelná péče · '+careFrequencyLabel(c.care_months),cid:c.id,icon:'heart',recurringCare:true,topic:'care'});});
 store.contracts.forEach(c=>{if(c.expected_at&&c.status!=='cancelled'&&(Number(c.commission_expected)>Number(c.commission_paid)))tasks.push({when:c.expected_at,label:fullname(c.contact_id),detail:'Očekávaná provize '+euros(c.commission_expected),cid:c.contact_id,icon:'coins'});});
 return tasks.sort((a,b)=>a.when.localeCompare(b.when)||(a.time||'99:99').localeCompare(b.time||'99:99'));
}
function nextActionLabel(activity){
 const label=FOLLOWUPS[activity.next_kind]?.label||'📅 Navázat na rozhovor';
 return activity.next_note?label+' · '+activity.next_note:label;
}
function rowTask(t){
 const followup=t.activityId?'<button data-followup="'+Number(t.activityId)+'">Zapsat návaznost</button>':
  t.recurringCare?'<button data-recurring-care="'+Number(t.cid)+'">Zapsat péči</button>':'';
 return '<div class="simple-row'+(followup?' followup-task':'')+'" data-process="'+processForTask(t)+'"><span class="row-icon">'+icon(t.icon||'calendar')+
  '</span><div class="row-content"><strong>'+esc(t.label)+'</strong><small>'+esc(t.detail)+'</small></div><span class="row-date">'+
  when(t.when)+(t.time?' · '+esc(t.time):'')+'</span><div class="task-actions">'+followup+
  '<button data-person="'+Number(t.cid)+'">Otevřít</button></div></div>';
}
function renderTeam(){
 const groups=Object.entries(COLLABORATION_STAGES).filter(([stage])=>stage!=='no_interest');
 const people=store.contacts.slice().sort((a,b)=>full(a).localeCompare(full(b),'cs'));
 $('team-people').innerHTML=groups.map(([stage,label])=>{
  const members=people.filter(contact=>contact.collaboration_stage===stage);
  return '<article class="panel team-group" data-collaboration="'+stage+'"><h2>'+label+' <span>'+members.length+'</span></h2>'+
   (members.map(contact=>'<div class="team-person"><button data-person="'+Number(contact.id)+'">'+esc(full(contact))+
    '</button><button class="button outline" data-team-conversation="'+Number(contact.id)+'">Zapsat rozhovor</button></div>').join('')||
    '<p class="muted">Zatím bez lidí v této fázi.</p>')+'</article>';
 }).join('');
 const tasks=taskRows().filter(t=>TEAM_FOLLOWUPS.includes(t.topic));
 $('team-tasks').innerHTML=tasks.map(rowTask).join('')||noRows('Naplánuj další krok ve spolupráci nebo v práci s týmem v zápisu rozhovoru.');
}
function noRows(label){return '<div class="empty">'+esc(label)+'</div>';}
function kpi(label,value,hint,emoji){return '<div class="kpi"><i>'+emoji+'</i><span>'+esc(label)+'</span><strong>'+value+'</strong><small>'+esc(hint)+'</small></div>';}
function renderOverview(){
 const signed=store.contracts.filter(c=>c.status==='signed');
 const paid=sum(store.contracts,'commission_paid');
 const tasks=taskRows();
 const upcoming=tasks.slice(0,5);
 const relationships=summarizeRelationships(store,tasks,TODAY());
 const priorities=[
  ['care','care','heart','Péče po termínu',relationships.overdue.length,'Otevřít péči'],
  ['referral','care','gift','Získaná doporučení',store.referrals.length,relationships.referralTasks.length+' kroků k doporučením'],
  ['team','team','branch','Kroky ve spolupráci',relationships.teamTasks.length,relationships.team.length+' lidí v přehledu']
 ];
 $('relationship-priorities').innerHTML=priorities.map(([tone,target,symbol,label,value,hint])=>
  '<button class="priority-card" data-process="'+tone+'" data-view="'+target+'">'+icon(symbol)+
  '<span>'+label+'</span><strong>'+INT.format(value)+'</strong><small>'+esc(hint)+' →</small></button>'
 ).join('');
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
 '<div class="people-card" data-process="'+processForStage(c.stage)+'"><div class="avatar">'+esc(initials(c))+'</div><div class="person-info"><strong>'+esc(full(c))+'</strong><small>'+esc(c.phone||c.email||c.source||'Připravený kontakt')+'</small></div><span class="stage-badge">'+esc(STAGES[c.stage]||'Kontakt')+'</span><button data-person="'+Number(c.id)+'">Detail →</button></div>').join('')||noRows(store.contacts.length?'Nenalezen žádný odpovídající kontakt.':'Přidej prvního člověka a pusť se do toho! 💚');
}
function renderDay(){
 const tasks=taskRows();
 $('day-tasks').innerHTML=tasks.length?tasks.slice(0,70).map(rowTask).join(''):noRows('Všechny další kroky najdeš tady. Zatím máš volno.');
 const acts=store.activities.slice().sort((a,b)=>b.happened_at.localeCompare(a.happened_at)||b.id-a.id).slice(0,16);
 $('day-entries').innerHTML=acts.length?acts.map(a=>{
  const rec=store.recordings.filter(r=>+r.activity_id===+a.id);
  return '<div class="simple-row"><span class="row-icon">'+icon(a.kind==='phone'?'phone':'calendar')+'</span><div class="row-content"><strong>'+esc(fullname(a.contact_id))+'</strong><small>'+esc(a.conversation_context==='team'?'👥 Týmový rozhovor':KINDS[a.kind]||'Zápis')+' · '+when(a.happened_at)+' · '+esc(ACTIVITY_RESULTS[a.result_status]||a.outcome||'Bez výsledku')+'</small></div><button data-person="'+Number(a.contact_id)+'">Detail</button><button data-coach="'+Number(a.id)+'">✨ Trénink</button>'+ (rec.length?'<button data-audio="'+Number(rec[0].id)+'">▶</button>':'<button data-record="'+Number(a.id)+'">🎙️</button>')+'</div>';
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
 '<div class="funnel-row" data-process="'+processForStage(s)+'"><div class="funnel-meta"><strong>'+STAGES[s]+'</strong><span>'+counts[i]+'</span></div><div class="funnel-meter"><span style="width:'+Math.max(0,(100*counts[i]/max))+'%"></span></div></div>').join('')+
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
 if(!allowedScreen('people'))return;
 const c=findContact(cid);if(!c)return toast('Klient nebyl nalezen.',true);
 personId=+cid;
 $('person-dialog').dataset.process=processForStage(c.stage);
 $('person-title').textContent=full(c);
 const acts=store.activities.filter(a=>+a.contact_id===+cid).sort((a,b)=>b.id-a.id);
 const contracts=store.contracts.filter(a=>+a.contact_id===+cid);
 const opportunities=store.opportunities.filter(a=>+a.contact_id===+cid);
 const refs=store.referrals.filter(a=>+a.contact_id===+cid);
 const recordings=store.recordings;
 let html='<div class="person-summary"><p>📞 '+esc(c.phone||'Telefon neuveden')+' &nbsp; ✉ '+esc(c.email||'E-mail neuveden')+'</p><p>📍 '+esc(c.address||'Adresa neuvedena')+' &nbsp; · Zdroj: '+esc(c.source||'nezadán')+'</p>'+
  '<p>Aktuální fáze: <b>'+esc(STAGES[c.stage]||'Kontakt')+'</b> · 💚 Péče '+careFrequencyLabel(c.care_months)+
  (c.next_care?' · další termín '+when(c.next_care):'')+'</p>'+
  (c.collaboration_stage?'<p>Spolupráce: <b>'+esc(COLLABORATION_STAGES[c.collaboration_stage]||'')+'</b></p>':'')+
  (c.notes?'<p>📝 '+esc(c.notes)+'</p>':'')+'</div>';
 html+='<div class="person-actions"><button data-action="edit" data-cid="'+Number(cid)+'">'+icon('users')+' Upravit</button><button data-action="activity" data-cid="'+Number(cid)+'">'+icon('mic')+' Schůzka / telefon</button><button data-action="contract" data-cid="'+Number(cid)+'">'+icon('file')+' Nová smlouva</button><button data-action="care" data-cid="'+Number(cid)+'">'+icon('heart')+' Následná péče</button><button data-action="opportunity" data-cid="'+Number(cid)+'">'+icon('branch')+' Další prodej</button><button data-action="referral" data-cid="'+Number(cid)+'">'+icon('gift')+' Doporučení</button></div>';
 html+='<section class="detail-section"><h3>📝 Schůzky, telefonáty a zápisy ('+acts.length+')</h3>';
 html+=acts.length?acts.map(a=>{
  const rec=recordings.filter(r=>+r.activity_id===+a.id);
  const topics=CONVERSATION_GUIDE.filter(topic=>topic.next).map(topic=>[topic.icon+' '+topic.title,a.topic_notes?.[topic.id]]);
  const sections=[['👋 Otevření',a.opening],['💬 Průběh',a.notes],['🔎 Zjištění',a.findings],...topics,
   ['🎁 Domluvené představení',REFERRAL_INTROS[a.referral_intro]],['🪞 Shrnutí',a.summary],['🏁 Závěr',a.outcome]]
   .filter(([,value])=>value).map(([label,value])=>'<dt>'+label+'</dt><dd>'+esc(value)+'</dd>').join('');
  const next=a.next_at?'<small>'+esc(nextActionLabel(a))+' · '+when(a.next_at)+(a.next_time?' '+esc(a.next_time):'')+
   (a.followup_activity_id?' · ✅ Zapsáno':'')+'</small>':'';
  return '<div class="detail-row"><div><b>'+esc(a.conversation_context==='team'?'👥 Týmový rozhovor':KINDS[a.kind]||'Poznámka')+' · '+when(a.happened_at)+'</b>'+
   (a.result_status?'<small>'+esc(ACTIVITY_RESULTS[a.result_status]||'')+'</small>':'')+
   (sections?'<details class="activity-notes"><summary>Průběh a závěr</summary><dl>'+sections+'</dl></details>':'')+next+
   (a.next_at&&!a.followup_activity_id?'<button data-followup="'+Number(a.id)+'">Zapsat návaznost</button>':'')+
   '</div><button data-coach="'+Number(a.id)+'">✨ AI trénink</button>'+
   (rec.length?'<button data-audio="'+Number(rec[0].id)+'" data-activity="'+Number(a.id)+'">▶ Audio</button>':'<button data-record="'+Number(a.id)+'">🎙️ Nahrát</button>')+'</div>';
 }).join(''):noRows('Zatím žádná schůzka.');
 html+='</section><section class="detail-section"><h3>✍️ Smlouvy a provize ('+contracts.length+')</h3>';
 html+=contracts.length?contracts.map(x=>'<div class="detail-row"><div><b>'+esc(x.product)+'</b><small>Objem '+euros(x.value)+' · očekáváno '+euros(x.commission_expected)+' ('+when(x.expected_at)+')</small><small>Vyplaceno '+euros(x.commission_paid)+' ('+when(x.paid_at)+') · '+esc(x.status)+'</small></div></div>').join(''):noRows('Zatím bez smlouvy.');
 html+='</section><section class="detail-section"><h3>🌱 Další prodej ('+opportunities.length+')</h3>';
 html+=opportunities.length?opportunities.map(o=>'<div class="detail-row"><div><b>'+esc(o.product)+'</b><small>Potenciál '+euros(o.potential)+' · provize '+euros(o.expected_commission)+' · kontakt '+when(o.next_at)+'</small><small>'+esc(o.notes||'')+' · '+esc(o.status)+'</small></div></div>').join(''):noRows('Co dalšího by klient mohl potřebovat? Ověř jeho potřeby.');
 html+='</section><section class="detail-section"><h3>🤝 Doporučení ('+refs.length+')</h3>';
 html+=refs.length?refs.map(r=>'<div class="detail-row"><div><b>'+esc(r.name)+'</b><small>'+esc(r.phone)+' · '+(Number(r.permission)?'Souhlas s oslovením uveden':'Bez potvrzeného oprávnění k oslovení')+'</small></div></div>').join(''):noRows('Po úspěšné schůzce se můžeš zeptat na doporučení.');
 html+='</section>';
 $('person-body').innerHTML=html;
 syncAccessControls($('person-body'));openDialog('person-dialog');
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
 return '<label>'+esc(label)+'<select name="'+esc(name)+'"'+(name==='contact_id'?' required':'')+'>'+options+'</select></label>';
}
function formSection(tone,title,content){
 return '<fieldset class="form-section" data-process="'+tone+'"><legend>'+esc(title)+'</legend>'+content+'</fieldset>';
}
function updateNextStepTone(){
 const next=$('dynamic-form').elements.next_kind;
 if(next)next.closest('fieldset').dataset.process=next.value?processForTask({topic:next.value}):'care';
}
function careFrequencyLabel(months){
 const count=Number(months)||3;
 if(count===1)return 'každý měsíc';
 return 'každé '+count+' '+(count<5?'měsíce':'měsíců');
}
function collaborationOptions(empty,selected=''){
 return '<option value="">'+esc(empty)+'</option>'+Object.entries(COLLABORATION_STAGES)
  .map(([stage,label])=>'<option value="'+stage+'"'+(stage===selected?' selected':'')+'>'+label+'</option>').join('');
}
function guideTopic(action){
 if(!action)return '';
 if(TEAM_FOLLOWUPS.includes(action)&&!['cooperation','recruitment'].includes(action))return 'team';
 return CONVERSATION_GUIDE.find(topic=>topic.next===action)?.id||'';
}
function guidePanel(topic,selectedTopic){
 const notesField=topic.field||'guide_'+topic.id;
 const questions=topic.questions.map((question,index)=>'<li><span>'+esc(question)+'</span>'+
  '<button type="button" data-guide-question="'+index+'" aria-label="'+esc('Přidat otázku do zápisu: '+question)+'" title="Přidat do zápisu">＋</button></li>').join('');
 let controls='';
 if(topic.next&&canPlanStep(topic.next)){
  controls='<label>📝 Dohoda, odpovědi a vlastní otázky<textarea name="'+notesField+'" rows="2" maxlength="3000"></textarea></label>';
  if(topic.id==='care'&&allowedAction('care_activity')){
   controls+='<div class="row-fields">'+field('Frekvence péče v měsících','care_frequency','','number',false,'min="1" max="24" step="1" placeholder="Např. 3"')+
    field('První nebo nový termín péče','care_first_at','','date')+'</div>'+
    '<p class="muted">Pravidelná péče je samostatný plán. Jednorázový krok můžeš domluvit níže.</p>';
  }
  if(topic.id==='referral'&&allowedAction('care_activity'))controls+=selectField('Jak proběhne představení?','referral_intro','<option value="">Nevybráno</option>'+
   Object.entries(REFERRAL_INTROS).map(([key,label])=>'<option value="'+key+'">'+label+'</option>').join(''));
  const actions=(topic.id==='team'?TEAM_FOLLOWUPS.filter(action=>guideTopic(action)==='team'):[topic.next])
   .filter(canPlanStep);
  controls+='<div class="guide-actions">'+actions.map(action=>'<button type="button" class="button outline" data-guide-next="'+action+'">'+FOLLOWUPS[action].label+'</button>').join('')+'</div>';
 }
 return '<details class="guide-topic" data-topic="'+topic.id+'"'+(selectedTopic===topic.id?' open':'')+'>'+
  '<summary><span>'+topic.icon+'</span> '+topic.title+'</summary>'+
  '<div class="guide-topic-body"><ul>'+questions+'</ul>'+
  (topic.hint?'<p class="guide-hint">'+esc(topic.hint)+'</p>':'')+controls+'</div></details>';
}
function renderGuide(selectedTopic){
 const [opening,discovery,...remaining]=CONVERSATION_GUIDE;
 const closing=remaining.pop();
 return '<section class="conversation-guide" aria-label="Průvodce obchodním rozhovorem">'+
  '<div class="guide-header"><h3>🧭 Průvodce rozhovorem</h3><p>Otevři si vhodné otázky. Tlačítkem ＋ si otázku přidej do zápisu.</p></div>'+
  '<p class="guide-recommendation" id="guide-recommendation" hidden></p>'+
  '<p class="guide-step">01 · Navaž kontakt a zjisti potřeby</p>'+guidePanel(opening,selectedTopic)+guidePanel(discovery,selectedTopic)+
  '<p class="guide-step">02 · Vyber téma podle toho, co zaznělo</p>'+remaining.map(topic=>guidePanel(topic,selectedTopic)).join('')+
  (allowedAction('team_activity')?selectField('Fáze spolupráce po rozhovoru','collaboration_stage',collaborationOptions('Ponechat dosavadní stav')):'')+
  '<p class="guide-step">03 · Ověř porozumění a domluv návaznost</p>'+guidePanel(closing,selectedTopic)+'</section>';
}
function updateGuideSuggestion(){
 const box=$('guide-recommendation');
 if(!box)return;
 const fields=$('dynamic-form').elements;
 const contact=findContact(fields.contact_id?.value);
 const planned=formPrefill.guideTopic||(formKind==='care'?'care':'');
 const fromStage={potential:'cooperation',discussing:'cooperation',onboarding:'recruitment',partner:'cooperation',team:'team'};
 const topicId=planned||fromStage[contact?.collaboration_stage]||
  (contact?.next_care||['contract','care'].includes(contact?.stage)?'care':'discovery');
 const topic=CONVERSATION_GUIDE.find(item=>item.id===topicId);
 box.hidden=false;
 box.innerHTML='<span>✨ Téma pro tento rozhovor</span> <button type="button" data-guide-open="'+topic.id+'">'+topic.icon+' '+topic.title+'</button>';
}
function openRecurringCare(id){
 const contact=findContact(id);
 if(!contact?.next_care)return;
 openForm('care',id,{careDue:contact.next_care,guideTopic:'care'});
}
function openTeamConversation(id){
 const contact=findContact(id);
 if(!contact)return;
 const topic=['team','onboarding'].includes(contact.collaboration_stage)?'team':'cooperation';
 openForm('activity',id,{kind:'meeting',guideTopic:topic,teamConversation:true});
}
function openFollowup(id){
 const activity=store.activities.find(a=>Number(a.id)===id);
 if(!activity||!activity.next_at||activity.followup_activity_id)return;
 openForm('activity',Number(activity.contact_id),{kind:FOLLOWUPS[activity.next_kind]?.kind||'phone',followup:activity,
  guideTopic:guideTopic(activity.next_kind),teamConversation:TEAM_FOLLOWUPS.includes(activity.next_kind)});
}
function openForm(kind,cid=0,extra={}){
 if(!allowedAction(formRight(kind,extra)))return;
 if(!['contact','edit'].includes(kind)&&!store.contacts.length&&!allowedAction('contact_create')){
  toast('Nejdřív musí být k dispozici kontakt.',true);
  return;
 }
 if(kind!=='contact'&&kind!=='edit'&&!store.contacts.length){toast('Nejdřív přidej prvního člověka. 🙂');kind='contact';}
 if($('person-dialog').open)$('person-dialog').close();
 formKind=kind;formPrefill=extra;editingContactId=kind==='edit'?+cid:0;
 const c=kind==='edit'?findContact(cid):null;
 const tone=extra.teamConversation||extra.teamIntake?'team':extra.followup?processForTask({topic:extra.followup.next_kind}):
  ({edit:'contact',contact:'contact',activity:'meeting',care:'care',contract:'contract',opportunity:'contract',referral:'referral'})[kind];
 let title='',html='';
 if(kind==='contact'||kind==='edit'){
  title=kind==='edit'?'Upravit kontakt':extra.teamIntake?'Kontakt pro spolupráci':'Nový kontakt';
  html=formSection('contact','01 · Kontaktní údaje',
  '<div class="row-fields">'+field('Jméno *','first_name',c?.first_name||'','text',true,'maxlength="100"')+field('Příjmení *','last_name',c?.last_name||'','text',true,'maxlength="100"')+'</div>'+
  '<div class="row-fields">'+field('📞 Telefon','phone',c?.phone||'','tel',false,'maxlength="70"')+field('✉ E-mail','email',c?.email||'','email',false,'maxlength="254"')+'</div>'+
  field('Adresa','address',c?.address||''))+
  formSection('meeting','02 · Kontext vztahu',
  '<div class="row-fields">'+field('Zdroj kontaktu','source',c?.source||'')+
  selectField('Fáze', 'stage',Object.entries(STAGES).map(([v,n])=>'<option value="'+v+'" '+((c?.stage||'contact')===v?'selected':'')+'>'+n+'</option>').join(''))+'</div>'+
  textArea('Poznámky','notes',c?.notes||''))+
  (allowedAction('care_activity')?formSection('care','03 · Pravidelná péče',
  '<div class="row-fields">'+field('Frekvence péče v měsících','care_months',c?.care_months||3,'number',true,'min="1" max="24" step="1"')+
  field('Další pravidelná péče','next_care',c?.next_care||'','date')+'</div>'):'')+
  (allowedAction('team_activity')?formSection('team','04 · Spolupráce a tým',
  selectField('Fáze spolupráce nebo týmu','collaboration_stage',collaborationOptions('Bez zařazení',c?.collaboration_stage||(extra.teamIntake?'potential':'')))):'');
 }else if(kind==='activity'||kind==='care'){
  title=extra.followup?'Navazující aktivita 🔁':extra.teamConversation?'Tým a spolupráce 👥':kind==='care'?'Péče o klienta 💚':'Nový zápis z rozhovoru 🎙️';
  const selected=cid||0;
  const wanted=kind==='care'?'care':(extra.kind||'meeting1');
  const kinds=Object.entries(KINDS).filter(([key])=>
   allowedAction(activityAction(key,extra.teamConversation))&&
   (key!=='care'||allowedAction('care_activity')));
  const fixedKind=extra.careDue||!allowedAction('activity')&&kind==='care';
  html=formSection(tone,'01 · Údaje o aktivitě',(extra.teamConversation?'<input type="hidden" name="team_conversation" value="1">':'')+(extra.followup||extra.careDue?
   '<input type="hidden" name="contact_id" value="'+Number(cid)+'">'+
   (extra.followup?'<input type="hidden" name="followup_from_id" value="'+Number(extra.followup.id)+'">'+
    '<div class="notice">🔁 '+esc(fullname(cid))+' · '+esc(nextActionLabel(extra.followup))+'</div>':
    '<input type="hidden" name="care_due" value="'+esc(extra.careDue)+'">'+
    '<div class="notice">💚 '+esc(fullname(cid))+' · pravidelná péče '+when(extra.careDue)+
    '. Po zápisu se naplánuje další termín podle frekvence, pokud nezadáš vlastní.</div>'):
   selectField('👤 Člověk *','contact_id',contactOptions(selected)))+
  (fixedKind?'<input type="hidden" name="kind" value="care">':
   selectField('Co právě proběhlo?','kind',kinds.map(([v,n])=>'<option value="'+v+'" '+(wanted===v?'selected':'')+'>'+esc(n)+'</option>').join('')))+
  field('Datum','happened_at',TODAY(),'date',true))+
  renderGuide(extra.guideTopic||(kind==='care'?'care':''))+
  '<fieldset class="form-section" data-process="'+tone+'"><legend>02 · Průběh rozhovoru</legend>'+
  '<div class="row-fields">'+textArea('👋 Otevření','opening','','Jak jsem navázal kontakt a domluvil účel?')+
  textArea('💬 Průběh','notes','','Hlavní téma, otázky a reakce klienta…')+'</div>'+
  '<div class="row-fields">'+textArea('🔎 Zjištění','findings','','Co jsem zjistil o potřebách, prioritách nebo překážkách?')+
  textArea('🪞 Shrnutí','summary','','Co jsme si ověřili a na čem se shodujeme?')+'</div>'+
  '<div class="row-fields">'+field('🏁 Závěr','outcome','','text',false,'maxlength="500" placeholder="Např. klient chce vidět návrh"')+
  selectField('Výsledek','result_status','<option value="">Bez hodnocení</option>'+Object.entries(ACTIVITY_RESULTS)
   .map(([v,label])=>'<option value="'+v+'">'+label+'</option>').join(''))+'</div></fieldset>'+
  '<fieldset class="form-section" data-process="care"><legend>03 · Domluvený další krok</legend>'+
  selectField('Co udělám?','next_kind','<option value="">Nevybráno</option>'+Object.entries(FOLLOWUPS).filter(([key])=>canPlanStep(key))
   .map(([v,plan])=>'<option value="'+v+'">'+plan.label+'</option>').join(''))+
  field('Co přesně připravím nebo nabídnu?','next_note','','text',false,'maxlength="1000" placeholder="Např. poslat srovnání, probrat péči nebo se zeptat na doporučení"')+
  '<div class="row-fields">'+field('📅 Datum dalšího kroku','next_at','','date')+
  field('🕒 Čas (volitelný)','next_time','','time')+'</div>'+
  '<p class="muted">Jakmile zadáš datum, krok se objeví v agendě Můj den.</p></fieldset>'+
  '<div class="notice">🎙️ Po uložení můžeš přidat nahrávku schůzky. V telefonu přímo v prohlížeči lze nahrávat mikrofon; telefonní hovory nahrané jinde lze přiložit jako soubor.</div>';
 }else if(kind==='contract'){
  title='Nová smlouva';
  html=formSection('contract','01 · Klient a smlouva',selectField('👤 Klient *','contact_id',contactOptions(cid))+
  selectField('Produkt','product',productOptions())+
  selectField('Stav smlouvy','status','<option value="signed">Uzavřená</option><option value="pending">Rozpracovaná</option><option value="cancelled">Stornovaná</option>')+
  field('Objem smlouvy v Kč','value','0','number',false,'min="0" step="0.01"'))+
  formSection('contract','02 · Očekávaná provize',
  '<div class="row-fields">'+field('Očekávaná provize v Kč','commission_expected','0','number',false,'min="0" step="0.01"')+
  field('Kdy má přijít provize?','expected_at','','date')+'</div>')+
  formSection('care','03 · Skutečná výplata',
  '<div class="row-fields">'+field('Kolik je skutečně vyplaceno v Kč','commission_paid','0','number',false,'min="0" step="0.01"')+
  field('Skutečné datum výplaty','paid_at','','date')+'</div>'+
  textArea('Poznámka','notes','','Doplň číslo smlouvy nebo podmínky…'))+
  '<div class="notice">Provize jsou odděleny na očekávané a skutečně vyplacené. V ostrém provozu je ověř podle vyúčtování.</div>';
 }else if(kind==='opportunity'){
  title='Nová obchodní příležitost';
  html=formSection('contract','01 · Potřeba a nabídka',selectField('👤 Klient *','contact_id',contactOptions(cid))+
  selectField('Produkt, o kterém se pobavíme','product',productOptions())+
  textArea('Proč by to klient mohl potřebovat?','notes','','Nejdřív ověř přání, potřeby a vhodnost produktu…'))+
  formSection('care','02 · Potenciál a termín',
  '<div class="row-fields">'+field('Potenciál v Kč','potential','0','number',false,'min="0" step="0.01"')+
  field('Možná provize v Kč','expected_commission','0','number',false,'min="0" step="0.01"')+'</div>'+
  '<div class="row-fields">'+field('Kdy se ozvu?','next_at','','date')+
  selectField('Stav příležitosti','status','<option value="open">Otevřeno</option><option value="won">Podařilo se</option><option value="lost">Nevyšlo</option>')+'</div>')+
  '<div class="notice">Nápad na další prodej není automatické investiční doporučení. Vhodnost ověř při jednání.</div>';
 }else if(kind==='referral'){
  title='Nové doporučení';
  html=formSection('referral','01 · Doporučující a doporučený',
  selectField('👤 Doporučující klient','contact_id',contactOptions(cid))+
  '<div class="row-fields">'+
  field('Jméno doporučeného člověka *','name','','text',true)+
  field('Telefon doporučeného','phone','','tel')+'</div>')+
  formSection('referral','02 · Domluva na představení',
  '<label class="check-field"><input type="checkbox" name="permission" value="1">Mám ověřené oprávnění doporučeného člověka kontaktovat.</label>'+
  textArea('Jak se představíme?','notes','','Např. klient nás nejdřív propojí.'))+
  '<div class="notice">Bez oprávnění kontakt neoslovuj. Zapisuj jen nezbytné osobní údaje.</div>';
 }
 $('form-dialog').dataset.process=tone;
 $('form-context').textContent=({contact:'KLIENT A KONTAKT',meeting:'SCHŮZKY A HOVORY',care:'PÉČE O KLIENTA',contract:'OBCHOD A PROVIZE',referral:'DOPORUČENÍ',team:'TÝM A SPOLUPRÁCE'})[tone];
 $('form-title').textContent=title;
 $('form-fields').innerHTML=html;
 updateGuideSuggestion();
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
  if(kind==='contact'){go(formPrefill.teamIntake?'team':'people');person(result.id);}
  else if(kind==='activity'){
   go(formPrefill.teamConversation?'team':formKind==='care'||view==='care'?'care':'day');
   if((formPrefill.record||wantRecording)&&allowedAction('upload'))openAudio(result.id);
   wantRecording=false;
  }else if(kind==='referral'){
   go('care');
  }else if(kind==='contract'||kind==='opportunity'){
   go('results');
  }
 }catch(err){$('form-error').textContent=err.message;}
 finally{form.querySelector('[type="submit"]').disabled=false;}
}
function openDialog(id){const el=$(id);if(el&&!el.open)el.showModal();}
function closeDialog(id){const el=$(id);if(el&&el.open)el.close();}
function openAudio(activityId){
 if(!allowedAction('upload'))return;
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

// WebKitty may proxy a binary PHP response as an HTML document (HTTP 200).
// This authenticated, bounded JSON fallback never exposes a public audio URL.
async function fetchRecordingChunks(recordingId,status){
 let offset=0,total=0,mime='',parts=[];
 for(let requestNo=0;requestNo<110;requestNo++){
  const response=await fetch('api.php?action=media_chunk&id='+encodeURIComponent(recordingId)+'&offset='+offset,{credentials:'same-origin',cache:'no-store'});
  let data;
  try{data=await response.json();}catch(_){throw Error('Ani záložní rozhraní na serveru nevrací platná data JSON. Zkontroluj nasazení api.php na WebKitty.');}
  if(!response.ok||!data.ok)throw Error(data.error||'Záložní přenos selhal (HTTP '+response.status+').');
  if(requestNo===0){
   total=Number(data.size);mime=String(data.mime||'');
   if(!Number.isSafeInteger(total)||total<1||total>50*1024*1024||!/^audio\//.test(mime))throw Error('Server poskytl neplatné údaje o nahrávce.');
  }
  const next=Number(data.next_offset);
  if(Number(data.offset)!==offset||Number(data.size)!==total||data.mime!==mime||
     !Number.isSafeInteger(next)||next<=offset||next>total||typeof data.chunk!=='string')
   throw Error('Záložní přenos vrátil nekonzistentní části nahrávky.');
  let raw;
  try{raw=atob(data.chunk);}catch(_){throw Error('Poškozená část zvukových dat.');}
  if(raw.length!==next-offset||raw.length!==Number(data.bytes))throw Error('Nesouhlasí délka přenesené části zvuku.');
  const bytes=new Uint8Array(raw.length);
  for(let i=0;i<raw.length;i++)bytes[i]=raw.charCodeAt(i);
  parts.push(bytes);
  offset=next;
  if(status&&status.isConnected)status.textContent='Načítám nahrávku bezpečným záložním přenosem… '+Math.round(offset/total*100)+' %';
  if(offset===total)return new Blob(parts,{type:mime});
 }
 throw Error('Přenos nahrávky překročil bezpečnostní limit.');
}
function audioMagic(blobBytes){
 const b=blobBytes;
 const magic=(...values)=>values.every((v,i)=>b[i]===v);
 if(magic(0x1a,0x45,0xdf,0xa3))return 'audio/webm';
 if(magic(0x4f,0x67,0x67,0x53))return 'audio/ogg';
 if(magic(0x52,0x49,0x46,0x46)&&b[8]===0x57&&b[9]===0x41&&b[10]===0x56&&b[11]===0x45)return 'audio/wav';
 if(magic(0x49,0x44,0x33)||(b[0]===0xff&&(b[1]&0xe0)===0xe0))return 'audio/mpeg';
 if(b[4]===0x66&&b[5]===0x74&&b[6]===0x79&&b[7]===0x70)return 'audio/mp4';
 if(b[0]===0xff&&(b[1]&0xf0)===0xf0)return 'audio/aac';
 return '';
}
async function openPlayback(recordingId,activityId){
 const info=store.recordings.find(r=>Number(r.id)===Number(recordingId));
 activePlaybackActivityId=Number(activityId)||Number(info?.activity_id)||0;
 activePlaybackRecordingId=Number(recordingId);
 const status=$('playback-status'),error=$('playback-error'),player=$('playback-player');
 $('playback-title').textContent='Přehrát schůzku ▶';
 $('playback-coach').disabled=!activePlaybackActivityId;
 $('playback-download').disabled=true;
 player.pause();player.hidden=true;player.removeAttribute('src');player.load();
 if(activePlaybackUrl){URL.revokeObjectURL(activePlaybackUrl);activePlaybackUrl=null;}
 status.textContent='Načítám zabezpečenou nahrávku…';status.hidden=false;error.textContent='';
 if($('person-dialog').open)$('person-dialog').close();
 openDialog('playback-dialog');
 try{
  let playable=null;
  const failures=[];
  const urls=['media.php?id='+encodeURIComponent(recordingId),'api.php?action=media&id='+encodeURIComponent(recordingId)];
  for(const url of urls){
   try{
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store'});
    if(!response.ok){
     let message='HTTP '+response.status;
     try{const result=await response.json();if(result.error)message=result.error;}catch(_){}
     throw Error(message);
    }
    const type=(response.headers.get('Content-Type')||'').toLowerCase();
    const received=await response.blob();
    if(!received.size)throw Error('Soubor je prázdný.');
    const signature=new Uint8Array(await received.slice(0,20).arrayBuffer());
    const actual=audioMagic(signature);
    if(!actual)throw Error('Server vrátil '+(type||'neznámý formát')+' místo rozpoznatelného audio souboru.');
    // Real magic bytes take precedence over incorrect hosting MIME metadata.
    playable=new Blob([received],{type:actual});
    break;
   }catch(err){failures.push(err.message);}
  }
  if(!playable){
   status.textContent='Běžné přehrávání není dostupné. Zkouším zabezpečený záložní přenos…';
   try{
    playable=await fetchRecordingChunks(recordingId,status);
    const signature=new Uint8Array(await playable.slice(0,20).arrayBuffer());
    const actual=audioMagic(signature);
    if(!actual)throw Error('Nahrávka na disku nemá rozpoznatelný zvukový formát.');
    if(actual!==playable.type)playable=new Blob([playable],{type:actual});
   }catch(err){
    throw Error(err.message+' Běžné cesty: '+failures.join(' | ')+'.');
   }
  }
  if(!$('playback-dialog').open||activePlaybackRecordingId!==Number(recordingId))return;
  const ext=(info?.original_name||'').split('.').pop().toLowerCase();
  activePlaybackExt=['webm','mp3','m4a','mp4','ogg','wav','aac'].includes(ext)?ext:'webm';
  activePlaybackUrl=URL.createObjectURL(playable);
  player.src=activePlaybackUrl;
  player.hidden=false;
  $('playback-download').disabled=false;
  status.textContent='Nahrávka je načtená ('+(playable.size/1024/1024).toFixed(1)+' MB). Spusť ji tlačítkem ▶.';
  player.load();
 }catch(err){
  if(!$('playback-dialog').open)return;
  status.hidden=true;
  error.textContent=err.message+' Pokud se chyba opakuje, zkontroluj aktualizaci PHP a WebKitty.';
 }
}
function closePlayback(){
 const player=$('playback-player');
 player.pause();player.removeAttribute('src');player.load();
 if(activePlaybackUrl){URL.revokeObjectURL(activePlaybackUrl);activePlaybackUrl=null;}
 activePlaybackRecordingId=0;
}
function downloadPlayback(){
 if(!activePlaybackUrl)return toast('Nahrávka ještě není načtená.',true);
 const a=document.createElement('a');a.href=activePlaybackUrl;a.download='moje-schuzka-'+activePlaybackRecordingId+'.'+activePlaybackExt;
 document.body.appendChild(a);a.click();a.remove();
}
function openCoach(id){
 if(!allowedScreen('coaching'))return;
 const a=store.activities.find(x=>+x.id===+id);
 if(!a)return toast('Zápis nenalezen.',true);
 coachActivityId=+id;$('coach-transcript').value=a.transcript||'';$('coach-review').value=a.review||'';$('coach-error').textContent='';$('coach-generated').hidden=true;$('coach-ready-prompt').value='';
 $('coach-scorecard').hidden=!COACHABLE_KINDS.includes(a.kind);
 if($('person-dialog').open)$('person-dialog').close();
 if($('playback-dialog').open)$('playback-dialog').close();
 openDialog('coach-dialog');
}
async function saveCoach(){
 try{await request('review',{id:coachActivityId,transcript:$('coach-transcript').value,review:$('coach-review').value});await refresh();toast('Tvůj přepis a sebehodnocení jsou uložené.');}
 catch(e){$('coach-error').textContent=e.message;}
}
async function copyCoach(){
 const transcript=$('coach-transcript').value.trim();
 if(!transcript){$('coach-error').textContent='Nejdřív vlož anonymizovaný přepis nebo nejdůležitější věty ze schůzky.';return;}
 const prompt=[
  'Jsi můj profesionální a etický obchodní kouč. Analyzuj výhradně dodaný přepis.',
  'Odděl skutečné výroky účastníků od svých domněnek. Neukládej a neodvozuj citlivé údaje.',
  'Připrav přehlednou zpětnou vazbu v češtině:',
  '1. Stručně shrň cíl, potřeby klienta, výsledek a domluvený další krok (pokud zazněly).',
  '2. Co se povedlo: konkrétní příklady z přepisu.',
  '3. Co příště zlepšit: maximálně 3 jasné praktické rady a vhodnější formulace.',
  '4. Posuď těchto šest fází: '+COACHING_STAGES.map(stage=>stage.title).join('; ')+'. Uveď konkrétní příklad a skóre 1–5 jen tam, kde máš dostatek podkladů.',
  '5. Připrav krátký trénink rolí: 3 otázky klienta, na které mám odpovědět, a po mé odpovědi mi dej zpětnou vazbu.',
  '6. Navrhni citlivou formulaci následné péče nebo žádosti o doporučení bez nátlaku.',
  'Jde-li o investice či pojištění, neposuzuj vhodnost konkrétního produktu bez úplného zjištění situace klienta.',
  '',
  'PŘEPIS – před odesláním odstraň osobní a citlivé údaje:',
  transcript,
  '',
  'MOJE VLASTNÍ HODNOCENÍ:',
  $('coach-review').value.trim()||'Zatím nezapsáno'
 ].join('\n');
 $('coach-ready-prompt').value=prompt;
 $('coach-generated').hidden=false;
 $('coach-error').textContent='';
 try{
  await navigator.clipboard.writeText(prompt);
  toast('Zadání pro AI kouče je zkopírované. Otevři ChatGPT a vlož ho tam. ✨');
 }catch(e){
  $('coach-ready-prompt').focus();$('coach-ready-prompt').select();
  toast('Zadání je připravené níže. Označený text zkopíruj ručně (Ctrl+C).');
 }
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
   try{await request('contact',{...record,import:true});created++;if(keyE)known.add(keyE);if(keyP)known.add(keyP);}
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
 if(!target||!controlAllowed(target))return;
 if(target.dataset.close){closeDialog(target.dataset.close);return;}
 if(target.dataset.view){go(target.dataset.view);return;}
 if(target.dataset.person){person(Number(target.dataset.person));return;}
 if(target.dataset.action){
  const kind=target.dataset.action;
  const cid=Number(target.dataset.cid)||0;
  openForm(kind,cid,{record:kind==='activity',teamIntake:target.dataset.teamIntake==='1'});
  return;
 }
 if(target.dataset.record){openAudio(Number(target.dataset.record));return;}
 if(target.dataset.followup){openFollowup(Number(target.dataset.followup));return;}
 if(target.dataset.recurringCare){openRecurringCare(Number(target.dataset.recurringCare));return;}
 if(target.dataset.teamConversation){openTeamConversation(Number(target.dataset.teamConversation));return;}
 if(target.dataset.scorecard){openScorecard(Number(target.dataset.scorecard));return;}
 if(target.dataset.coach){openCoach(Number(target.dataset.coach));return;}
 if(target.dataset.audio){
  openPlayback(Number(target.dataset.audio),Number(target.dataset.activity));
 }
});
$('auth-form').addEventListener('submit',async event=>{
 event.preventDefault();const data=Object.fromEntries(new FormData(event.target));
 const isFirst=!$('setup-row').hidden;
 $('auth-error').textContent='';
 try{
  $('auth-submit').disabled=true;
  await request(isFirst?'register':'login',data);
  event.target.reset();
  await signedIn();toast('Vítej v Moje schůzka! 💚');
 }catch(e){$('auth-error').textContent=e.message;}
 finally{$('auth-submit').disabled=false;}
});
$('dynamic-form').addEventListener('submit',saveForm);
$('dynamic-form').addEventListener('change',event=>{
 if(event.target.name==='contact_id')updateGuideSuggestion();
 if(event.target.name==='next_kind')updateNextStepTone();
});
$('dynamic-form').addEventListener('click',event=>{
 const button=event.target.closest('button[data-guide-open],button[data-guide-question],button[data-guide-next]');
 if(!button)return;
 const fields=event.currentTarget.elements;
 if(button.dataset.guideOpen){
  const section=event.currentTarget.querySelector('.guide-topic[data-topic="'+button.dataset.guideOpen+'"]');
  section.open=true;
  section.querySelector('summary').focus();
  return;
 }
 if(button.dataset.guideNext){
  fields.next_kind.value=button.dataset.guideNext;
  updateNextStepTone();
  fields.next_note.focus();
  return;
 }
 const section=button.closest('.guide-topic');
 const topic=CONVERSATION_GUIDE.find(item=>item.id===section.dataset.topic);
 const field=fields[topic.field||'guide_'+topic.id];
 if(!field)return;
 const question=topic.questions[Number(button.dataset.guideQuestion)];
 const line='Otázka: '+question;
 if(!field.value.split('\n').includes(line)){
  const next=[field.value.trimEnd(),line].filter(Boolean).join('\n');
  if(field.maxLength>0&&next.length>field.maxLength){toast('Do této poznámky se další otázka nevejde.',true);return;}
  field.value=next;
 }
 field.focus();
 field.setSelectionRange(field.value.length,field.value.length);
});
$('people-search').addEventListener('input',renderPeople);
$('people-stage').addEventListener('change',renderPeople);
$('hamburger').addEventListener('click',()=>{const open=$('sidebar').classList.toggle('open');$('shade').hidden=!open;$('hamburger').setAttribute('aria-expanded',String(open));});
$('shade').addEventListener('click',()=>{$('sidebar').classList.remove('open');$('shade').hidden=true;$('hamburger').setAttribute('aria-expanded','false');});
$('logout').addEventListener('click',logout);
$('import-action').addEventListener('click',()=>$('import-file').click());
$('import-file').addEventListener('change',importFile);
function excelSafe(value){
 if(typeof value==='string' && /^[=+\-@\t\r]/.test(value))return "'"+value;
 return value==null?'':value;
}
async function exportExcel(){
 if(!allowedAction('export'))return;
 if(!window.XLSX){
  if(allowedScreen('people'))window.location.href='api.php?action=csv';
  else toast('Knihovna pro export do Excelu se nenačetla.',true);
  return;
 }
 let data;
 try{data=(await request('export')).data;}
 catch(error){toast(error.message,true);return;}
 const workbook=XLSX.utils.book_new();
 const contactName=id=>full(data.contacts.find(c=>Number(c.id)===Number(id))||{});
 const sheets=[
  ['Kontakty',data.contacts.map(c=>({
   Jméno:c.first_name,Příjmení:c.last_name,Telefon:c.phone,'E-mail':c.email,Adresa:c.address,
   'Zdroj kontaktu':c.source,Fáze:STAGES[c.stage]||c.stage,Poznámky:c.notes,'Další péče':c.next_care
  }))],
  ['Schůzky',data.activities.map(a=>({
   Klient:contactName(a.contact_id),Typ:KINDS[a.kind]||a.kind,Datum:a.happened_at,
   Zápis:a.notes,Výsledek:a.outcome,'Další kontakt':a.next_at,Přepis:a.transcript,'Moje hodnocení':a.review
  }))],
  ['Smlouvy a provize',data.contracts.map(c=>({
   Klient:contactName(c.contact_id),Produkt:c.product,'Objem Kč':Number(c.value),'Očekávaná provize Kč':Number(c.commission_expected),
   'Očekávaná výplata':c.expected_at,'Vyplacená provize Kč':Number(c.commission_paid),'Skutečná výplata':c.paid_at,Stav:c.status
  }))],
  ['Křížový prodej',data.opportunities.map(o=>({
   Klient:contactName(o.contact_id),Produkt:o.product,Stav:o.status,'Objem potenciál Kč':Number(o.potential),
   'Předpokládaná provize Kč':Number(o.expected_commission),'Další kontakt':o.next_at,Poznámky:o.notes
  }))],
  ['Doporučení',data.referrals.map(r=>({
   'Doporučil klient':contactName(r.contact_id),'Doporučená osoba':r.name,Telefon:r.phone,
   'Oprávnění k oslovení potvrzeno':Number(r.permission)?'Ano':'Ne',Poznámky:r.notes
  }))]
 ];
 try{
  for(const [name,rows] of sheets){
   const safe=rows.map(r=>Object.fromEntries(Object.entries(r).map(([k,v])=>[k,excelSafe(v)])));
   const ws=safe.length?XLSX.utils.json_to_sheet(safe):XLSX.utils.aoa_to_sheet([['Zatím žádná data']]);
   XLSX.utils.book_append_sheet(workbook,ws,name);
  }
  XLSX.writeFile(workbook,'moje-schuzka-'+TODAY()+'.xlsx',{compression:true});
  toast('Export do Excelu je připraven. 📊');
 }catch(e){toast('Export do Excelu se nezdařil: '+e.message,true);}
}
$('export-action').addEventListener('click',exportExcel);
$('backup-action').addEventListener('click',async()=>{
 try{
  const {data}=await request('backup');
  downloadJSON('moje-schuzka-zaloha-'+TODAY()+'.json',JSON.stringify({exported_at:new Date().toISOString(),...data},null,2));
 }catch(error){toast(error.message,true);}
});
$('export-results').addEventListener('click',exportExcel);
$('start-record').addEventListener('click',startRecording);
$('stop-record').addEventListener('click',stopRecording);
$('audio-file').addEventListener('change',()=>{$('save-audio').disabled=!$('audio-file').files.length;});
$('save-audio').addEventListener('click',saveAudio);
$('coach-save').addEventListener('click',saveCoach);
$('coach-scorecard').addEventListener('click',()=>openScorecard(coachActivityId));
$('scorecard-form').addEventListener('submit',saveScorecard);
$('relationship-period').addEventListener('change',renderRelationshipMetrics);
installScenarioPractice();
installUserSettings();
installAccountAdmin();
const practicePicker=$('coaching-focus-picker');
practicePicker.innerHTML='<button type="button" data-practice-stage="" aria-pressed="true">✨ Doporučeno</button>'+
 COACHING_STAGES.map((stage,index)=>'<button type="button" data-phase="'+stage.id+'" data-practice-stage="'+stage.id+
  '" aria-label="'+stage.title+'" aria-pressed="false">'+(index+1)+' '+stage.emoji+'</button>').join('');
practicePicker.addEventListener('click',event=>{
 const button=event.target.closest('button[data-practice-stage]');
 if(!button)return;
 practiceStageId=button.dataset.practiceStage;
 renderCoaching();
});
$('coach-copy').addEventListener('click',copyCoach);
$('coach-open-chatgpt').addEventListener('click',()=>window.open('https://chatgpt.com/','_blank','noopener,noreferrer'));
$('playback-download').addEventListener('click',downloadPlayback);
$('playback-coach').addEventListener('click',()=>{if(activePlaybackActivityId)openCoach(activePlaybackActivityId);});
$('playback-player').addEventListener('error',()=>{
 if(!$('playback-player').getAttribute('src'))return;
 $('playback-status').hidden=true;
 $('playback-error').textContent='Prohlížeč neumí tento zvuk přehrát nebo je soubor poškozený. Zkus Stáhnout nahrávku a otevřít ji v přehrávači zařízení.';
});
$('playback-dialog').addEventListener('close',closePlayback);
$('audio-dialog').addEventListener('close',()=>{if(recorder?.state==='recording')recorder.stop();clearInterval(recordTimer);recordStream?.getTracks().forEach(t=>t.stop());});
start();
