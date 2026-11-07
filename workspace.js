'use strict';

const PRACTICE_SCENARIOS = [
 {id:'care_checkin',tone:'care',title:'💚 Pravidelná péče',situation:'Klient po schůzce říká, že je spokojený a další kontakt zatím nepotřebuje. Domluv péči v rytmu, který mu vyhovuje.',
  client:'„Zatím je všechno v pořádku. Nevím, co bychom na další schůzce řešili.“',
  example:'„Co se u vás může změnit tak, že by stálo za to se znovu spojit? Co říkáte na krátkou kontrolu za půl roku? Řekneme si, co se změnilo a jestli je potřeba něco upravit.“',
  checks:['Zjistil jsem, co má pro klienta smysl.','Navrhl jsem konkrétní frekvenci a účel.','Nechal jsem klientovi volbu dalšího kroku.']},
 {id:'care_return',tone:'care',title:'📞 Návrat po delší pauze',situation:'Ozýváš se po delší době. Klient má pět minut a chce vědět, proč voláš.',
  client:'„Teď opravdu nemám moc času. Co se děje?“',
  example:'„Rád bych zjistil, jestli se od poslední schůzky něco změnilo a jestli potřebujete podporu. Co je u vás teď nejaktuálnější? Pokud se vám to hodí, na probrání detailů najdeme jiný termín.“',
  checks:['Vysvětlil jsem důvod hovoru jednou větou.','Položil jsem otevřenou otázku.','Respektoval jsem čas klienta.']},
 {id:'referral_value',tone:'referral',title:'🎁 Požádat o doporučení',situation:'Klient právě popsal konkrétní přínos vaší spolupráce. Navaž na jeho slova a požádej o doporučení.',
  client:'„Konečně mám jasno, co budu řešit nejdřív.“',
  example:'„Co konkrétně vám k tomu jasnějšímu rozhodnutí pomohlo? Napadá vás někdo, kdo řeší podobnou situaci a komu by takový rozhovor mohl pomoct? Jak by pro vás bylo příjemné mu o tom říct?“',
  checks:['Navázal jsem na přínos, který pojmenoval klient.','Položil jsem otevřenou otázku na konkrétní situaci.','Domluvil jsem přirozený způsob představení.']},
 {id:'referral_hesitation',tone:'referral',title:'🤝 Doporučení bez tlaku',situation:'Klient má někoho na mysli, ale váhá, zda předat jeho kontakt.',
  client:'„Jedna kamarádka mě napadá, ale její číslo dávat nechci.“',
  example:'„To respektuji. Pokud vám to dává smysl, můžete jí o rozhovoru říct vlastními slovy. Jestli se bude chtít spojit, může se ozvat sama nebo nás můžete představit. Jak vám to zní?“',
  checks:['Přijal jsem hranici bez přesvědčování.','Nabídl jsem možnost bez předání kontaktu.','Ověřil jsem, jak se klient s návrhem cítí.']},
 {id:'team_invite',tone:'team',title:'🧭 První rozhovor o spolupráci',situation:'Člověka zajímá, jak funguje tvůj tým. Má současnou práci a neví, kolik času by tomu mohl dát.',
  client:'„Zní to zajímavě, ale nevím, jestli na to mám prostor.“',
  example:'„Co vás na tom zaujalo a kolik prostoru by pro vás bylo realistické? Můžeme si nejdřív projít, jak spolupráce vypadá v praxi, a pak si říct, jestli dává smysl udělat první malý krok.“',
  checks:['Zjišťoval jsem motivaci i časové možnosti.','Vysvětlil jsem realistický první krok.','Nechal jsem prostor na otázky a rozhodnutí.']},
 {id:'team_feedback',tone:'team',title:'👥 Koučink člena týmu',situation:'Člen týmu má za sebou první schůzku. Chce tvoji radu, protože rozhovor skončil bez dohody.',
  client:'„Mluvil jsem dlouho a na konci jsme se na ničem nedomluvili.“',
  example:'„Která část se ti přesto povedla? Kdy v rozhovoru by šlo ověřit, co si klient odnáší? Pojďme si závěr nacvičit a po příští schůzce se k němu společně vrátit.“',
  checks:['Nechal jsem spolupracovníka zhodnotit vlastní práci.','Vybrali jsme jednu konkrétní dovednost.','Domluvili jsme nácvik a kontrolu v čase.']},
 {id:'crosssell_need',tone:'contract',title:'🌱 Další nabídka podle potřeb',situation:'Klient během péče zmínil nový cíl. Nevíš zatím, zda potřebuje další službu.',
  client:'„Změnily se mi plány. Budu asi potřebovat víc rezervy.“',
  example:'„Co se změnilo a kdy budete peníze potřebovat? Jak si rezervu řešíte dnes? Až budeme mít představu o částce a čase, můžeme probrat, jestli a co má smysl upravit.“',
  checks:['Zjišťoval jsem potřebu před nabídkou.','Upřesnil jsem čas a priority.','Dohodl jsem ověření nebo další krok.']},
 {id:'close_summary',tone:'meeting',title:'🏁 Ověřit dohodu a termín',situation:'Rozhovor je u konce, zaznělo několik možností a čas vypršel. Potřebuješ uzavřít jedinou srozumitelnou dohodu.',
  client:'„Dobře, já si to promyslím a pak se uvidí.“',
  example:'„Jestli vám rozumím, nejdřív si chcete ověřit dvě věci. Je to tak, nebo něco chybí? Podklady vám pošlu ve středu. Kdy vám vyhovuje, abychom si krátce řekli, co z nich vyplynulo?“',
  checks:['Ověřil jsem společné porozumění.','Domluvili jsme, kdo co udělá.','Zazněl termín kontroly nebo dalšího kroku.']}
];

function summarizeRelationships(data,tasks,today){
 const signed=new Set(data.contracts.filter(item=>item.status==='signed').map(item=>Number(item.contact_id)));
 const clients=data.contacts.filter(item=>['contract','care'].includes(item.stage)||signed.has(Number(item.id)));
 const careTasks=tasks.filter(item=>item.topic==='care');
 const team=data.contacts.filter(item=>Object.hasOwn(COLLABORATION_STAGES,item.collaboration_stage)&&item.collaboration_stage!=='no_interest');
 return {
  clients,planned:clients.filter(item=>item.next_care),careTasks,overdue:careTasks.filter(item=>item.when<today),
  referralTasks:tasks.filter(item=>item.topic==='referral'),team,
  teamTasks:tasks.filter(item=>TEAM_FOLLOWUPS.includes(item.topic))
 };
}

function shiftDate(date,days){
 const value=new Date(date+'T12:00:00Z');
 value.setUTCDate(value.getUTCDate()+days);
 return value.toISOString().slice(0,10);
}

function relationshipTrend(data,today,days){
 const series=[
  {title:'Záznamy péče',tone:'care',dates:data.activities.filter(a=>a.kind==='care'&&a.conversation_context!=='team').map(a=>a.happened_at)},
  {title:'Nová doporučení',tone:'referral',dates:data.referrals.map(a=>a.created_at)},
  {title:'Týmové rozhovory',tone:'team',dates:data.activities.filter(a=>a.conversation_context==='team').map(a=>a.happened_at)}
 ];
 const windowStart=shiftDate(today,1-days);
 const groups=Array.from({length:6},(_,i)=>{
  const from=shiftDate(windowStart,i*days/6);
  const to=shiftDate(from,days/6-1);
  const counts=series.map(item=>item.dates.filter(date=>{
   const key=String(date||'').slice(0,10);
   return key>=from&&key<=to;
  }).length);
  return {from,to,counts};
 });
 return {series,groups,windowStart};
}

function metricBars(items){
 const max=Math.max(1,...items.map(item=>item.value));
 return '<div class="metric-bars">'+items.map(({label,value,tone})=>
  '<div class="metric-row" data-process="'+tone+'"><div><span>'+esc(label)+'</span><strong>'+INT.format(value)+'</strong></div>'+
  '<div class="metric-track" aria-hidden="true"><span style="width:'+(100*value/max)+'%"></span></div></div>'
 ).join('')+'</div>';
}

function renderRelationshipMetrics(){
 const today=TODAY();
 const days=Number($('relationship-period').value)||90;
 const {series,groups,windowStart}=relationshipTrend(store,today,days);
 const total=groups.map(group=>group.counts).flat();
 const max=Math.max(1,...total);
 $('relationship-trend').innerHTML=total.some(Boolean)?
  '<div class="relationship-bars" role="group" aria-label="Počty záznamů za jednotlivá období">'+groups.map(group=>
   '<div class="relationship-bar-group"><div class="relationship-columns">'+group.counts.map((count,i)=>
    '<div data-process="'+series[i].tone+'" role="img" aria-label="'+esc(series[i].title+' '+when(group.from)+' až '+when(group.to)+' '+count)+'">'+
    '<small>'+count+'</small><span style="height:'+(count/max*78)+'px"></span></div>'
   ).join('')+'</div><small title="'+esc(when(group.from)+' až '+when(group.to))+'">'+when(group.from).split(' ').slice(0,2).join(' ')+'</small></div>'
  ).join('')+'</div><div class="metric-legend">'+series.map(item=>'<span data-process="'+item.tone+'">'+item.title+'</span>').join('')+'</div>'+
  '<p class="metric-caption">'+when(windowStart)+' až '+when(today)+' · skupina sloupců za '+(days/6)+' dní</p>'
  :'<p class="muted metric-empty">V tomto období zatím nejsou záznamy péče, doporučení ani týmových rozhovorů.</p>';
 const {clients,planned,overdue,team}=summarizeRelationships(store,taskRows(),today);
 const referralWindow=store.referrals.filter(item=>{
  const date=String(item.created_at||'').slice(0,10);
  return date>=windowStart&&date<=today;
 });
 const withPermission=referralWindow.filter(item=>Number(item.permission)===1).length;
 $('relationship-coverage').innerHTML=(clients.length?
  '<p class="metric-summary"><strong>'+Math.round(100*planned.length/clients.length)+' %</strong> klientů má termín pravidelné péče</p>':'')+metricBars([
  {label:'Klienti s termínem péče',value:planned.length,tone:'care'},
  {label:'Klienti bez termínu péče',value:clients.length-planned.length,tone:'meeting'},
  {label:'Úkoly péče po termínu',value:overdue.length,tone:'referral'}
 ]);
 $('referral-metrics').innerHTML=(referralWindow.length?
  '<p class="metric-summary"><strong>'+Math.round(100*withPermission/referralWindow.length)+' %</strong> s potvrzeným oprávněním</p>':'')+metricBars([
  {label:'Potvrzené oprávnění k oslovení',value:withPermission,tone:'care'},
  {label:'Oprávnění zatím nepotvrzeno',value:referralWindow.length-withPermission,tone:'referral'}
 ]);
 $('team-metrics').innerHTML=metricBars(Object.entries(COLLABORATION_STAGES).filter(([stage])=>stage!=='no_interest').map(([stage,label])=>
  ({label,value:team.filter(contact=>contact.collaboration_stage===stage).length,tone:stage==='potential'?'contract':'team'})));
}

function renderCareWorkspace(){
 const today=TODAY();
 const summary=summarizeRelationships(store,taskRows(),today);
 $('care-overdue').innerHTML=summary.overdue.map(rowTask).join('')||noRows('Žádná péče po termínu.');
 $('care-upcoming').innerHTML=summary.careTasks.filter(task=>task.when>=today).map(rowTask).join('')||noRows('Zatím bez dalších termínů péče.');
 $('referral-tasks').innerHTML=summary.referralTasks.map(rowTask).join('')||noRows('Domluv další krok při rozhovoru nebo zapiš získané doporučení.');
 $('referral-register').innerHTML=store.referrals.map(item=>
  '<div class="simple-row"><span class="row-icon">'+icon('gift')+'</span><div class="row-content"><strong>'+esc(item.name)+'</strong><small>'+
  esc(fullname(item.contact_id))+' · '+when(item.created_at)+'</small><small>'+(
   Number(item.permission)===1?'Potvrzené oprávnění k oslovení':'Oprávnění zatím nepotvrzeno'
  )+'</small></div><button data-person="'+Number(item.contact_id)+'">Otevřít kartu</button></div>'
 ).join('')||noRows('Zatím bez zapsaných doporučení.');
}

function renderPracticeScenario(){
 const scenario=PRACTICE_SCENARIOS.find(item=>item.id===$('practice-scenario').value);
 const panel=$('practice-situation');
 panel.dataset.process=scenario.tone;
 panel.innerHTML='<h3>'+scenario.title+'</h3><p>'+esc(scenario.situation)+'</p><blockquote>'+esc(scenario.client)+'</blockquote>';
 const feedback=$('practice-feedback');
 feedback.hidden=true;
 feedback.innerHTML='<h3>Modelová formulace</h3><p>'+esc(scenario.example)+'</p>'+
  '<fieldset class="form-section" data-process="'+scenario.tone+'"><legend>Jak se mi to povedlo?</legend>'+
  scenario.checks.map(check=>'<label class="check-field"><input type="checkbox" name="practice-check">'+esc(check)+'</label>').join('')+'</fieldset>';
 $('practice-save').disabled=true;
 $('practice-reveal').hidden=false;
 $('practice-error').textContent='';
}

function renderPracticeHistory(){
 const sessions=store.practice_sessions||[];
 $('practice-history').innerHTML=sessions.length?
  '<p class="muted">Dokončené nácviky <strong>'+INT.format(sessions.length)+'</strong> · Průměr sebehodnocení <strong>'+
   COACHING_AVERAGE.format(sessions.reduce((total,item)=>total+Number(item.score),0)/sessions.length)+' / 3</strong></p>'+
  sessions.slice(0,8).map(item=>{
   const scenario=PRACTICE_SCENARIOS.find(s=>s.id===item.scenario);
   return '<details class="practice-history-item"><summary>'+esc(scenario?.title||'Nácvik')+' · '+when(item.created_at)+' · '+
    Number(item.score)+' / 3</summary><p>'+esc(item.response)+'</p></details>';
  }).join(''):'<p class="muted">Zatím bez uloženého nácviku.</p>';
}

function installScenarioPractice(){
 const form=$('practice-form');
 const select=$('practice-scenario');
 const response=$('practice-response');
 const drafts=new Map();
 let requestKey=null;
 select.innerHTML=PRACTICE_SCENARIOS.map(item=>'<option value="'+item.id+'">'+item.title+'</option>').join('');
 let selected=select.value;
 renderPracticeScenario();
 select.addEventListener('change',()=>{
  drafts.set(selected,response.value);
  selected=select.value;
  response.value=drafts.get(selected)||'';
  requestKey=null;
  renderPracticeScenario();
 });
 response.addEventListener('input',()=>{requestKey=null;});
 $('practice-feedback').addEventListener('change',()=>{requestKey=null;});
 $('practice-reveal').addEventListener('click',()=>{
  if(!response.value.trim()){
   $('practice-error').textContent='Nejdřív napiš vlastní odpověď.';
   response.focus();
   return;
  }
  $('practice-error').textContent='';
  $('practice-feedback').hidden=false;
  $('practice-save').disabled=false;
  $('practice-reveal').hidden=true;
 });
 form.addEventListener('submit',async event=>{
  event.preventDefault();
  if($('practice-feedback').hidden||!response.value.trim())return;
  const save=$('practice-save');
  const criteria=form.querySelector('#practice-feedback fieldset');
  save.disabled=true;
  select.disabled=true;
  response.readOnly=true;
  criteria.disabled=true;
  $('practice-error').textContent='';
  try{
   requestKey??=crypto.randomUUID();
   const score=form.querySelectorAll('input[name="practice-check"]:checked').length;
   await request('practice_session',{scenario:selected,response:response.value,score,request_key:requestKey});
   await refresh();
   drafts.delete(selected);
   response.value='';
   requestKey=null;
   renderPracticeScenario();
   toast('Nácvik uložen do historie.');
  }catch(error){
   $('practice-error').textContent=error.message;
   save.disabled=false;
  }finally{
   select.disabled=false;
   response.readOnly=false;
   criteria.disabled=false;
  }
 });
}
