'use strict';

const ROLE_LABELS={admin:'Admin',editor:'Editor',user:'Uživatel',viewer:'Pouze náhled'};
const ROLE_DESCRIPTIONS={
 admin:'Úplný přístup k aplikaci, správě účtů, vzhledu a auditu.',
 editor:'Všechny obrazovky a obchodní akce. Úplnou zálohu lze povolit individuálně.',
 user:'Všechny obrazovky a běžné zápisy. Úpravy kontaktů, smlouvy, import a export lze povolit individuálně.',
 viewer:'Čtení povolených obrazovek a přehrávání nahrávek podle oprávnění.'
};
const PROFILE_FIELDS={
 first_name:['Jméno',80],last_name:['Příjmení',80],phone:['Telefon',70],
 address:['Adresa',400],company:['Firma',160],position:['Pracovní pozice',100]
};
const APPEARANCE_INPUTS={
 background:['Pozadí pracovního prostoru','#f5f7f2'],menu:['Pozadí menu','#103e37'],
 menu_text:['Text v menu','#e7f4e9'],button:['Pozadí tlačítek','#115143'],
 button_text:['Text na tlačítkách','#ffffff'],text:['Text pracovního prostoru','#183934']
};
const AUDIT_NAMES={
 setup:'První aktivace',login:'Přihlášení',login_failed:'Neúspěšné přihlášení',logout:'Odhlášení',
 password_changed:'Změna hesla',password_change_failed:'Neúspěšná změna hesla',password_reset:'Obnovení hesla',
 reset_issued:'Vystavení odkazu správcem',reset_requested:'Vyžádání e-mailu k obnově',reset_mail_failed:'Chyba předání e-mailu',
 account_created:'Založení účtu',account_updated:'Úprava účtu',appearance_saved:'Uložení vzhledu',
 backup:'Stažení zálohy',export:'Export zápisů',csv:'Export CSV'
};
let signedInAccount=null;
let access={screens:{},actions:{}};
let backupAllowed=false;
let resetEmailAvailable=false;
let appearance={};
let appearanceDraft={};
let adminData=null;
let accountId=0;
let resetToken='';
let authSetup=false;

function allowedScreen(id){return id==='settings'||Boolean(access.screens[id]);}
function allowedAction(id){return Boolean(access.actions[id]);}
function initialView(){return normalizedMenuOrder(store.preferences?.menu_order).find(allowedScreen)||'settings';}
function isAdmin(){return signedInAccount?.role==='admin';}
function menuLabel(id,settings=appearance){
 return settings.labels?.[id]||settingsMenuItems.find(item=>item.id===id)?.label||'Nastavení';
}
function profileDisplayName(){
 const accountName=[signedInAccount?.first_name,signedInAccount?.last_name].filter(Boolean).join(' ');
 return store.preferences?.display_name||accountName||signedInAccount?.email||'Můj účet';
}
function setAccountContext(meta){
 signedInAccount=meta.account;
 access=meta.access;
 backupAllowed=meta.can_backup;
 appearance=meta.appearance;
 $('profile-role').textContent=$('settings-role').textContent=ROLE_LABELS[signedInAccount.role];
 document.querySelectorAll('[data-admin-only]').forEach(element=>{element.hidden=!isAdmin();});
 $('settings-account-details').innerHTML=Object.entries(PROFILE_FIELDS)
  .filter(([key])=>signedInAccount[key])
  .map(([key,[label]])=>'<dt>'+label+'</dt><dd>'+esc(signedInAccount[key])+'</dd>').join('');
 applyAppearance(appearance);
}
function applyAppearance(settings){
 const colors=settings.colors||{};
 const app=$('app');
 Object.keys(APPEARANCE_INPUTS).forEach(key=>{
  const property='--appearance-'+key.replace('_','-');
  if(colors[key])app.style.setProperty(property,colors[key]);
  else app.style.removeProperty(property);
 });
 app.dataset.appearanceText=String(Boolean(colors.text));
 app.dataset.appearanceMenu=String(Boolean(colors.menu));
 app.dataset.appearanceMenuText=String(Boolean(colors.menu_text));
 app.dataset.appearanceButton=String(Boolean(colors.button||colors.button_text));
 document.documentElement.style.fontSize=(14*(settings.font_size||100)/100)+'px';
 settingsMenuItems.forEach(item=>{item.labelNode.textContent=' '+menuLabel(item.id,settings)+' ';});
 $('crumb').textContent=menuLabel(view,settings);
}
function activityAction(kind,team=false){
 return team?'team_activity':kind==='care'?'care_activity':'activity';
}
function canPlanStep(kind){
 if(TEAM_FOLLOWUPS.includes(kind))return allowedAction('team_activity');
 if(kind==='care'||kind==='referral')return allowedAction('care_activity');
 return allowedScreen('day');
}
function formRight(kind,extra={}){
 if(kind==='activity'||kind==='care')return activityAction(kind==='care'?'care':extra.kind,extra.teamConversation);
 return {contact:'contact_create',edit:'contact_edit'}[kind]||kind;
}
function followupAllowed(id){
 const source=store.activities.find(item=>Number(item.id)===Number(id));
 if(!source)return false;
 const step=source.next_kind;
 if(!canPlanStep(step))return false;
 return allowedAction(activityAction(FOLLOWUPS[step]?.kind,TEAM_FOLLOWUPS.includes(step)));
}
function controlAllowed(element){
 const data=element.dataset;
 if(data.view)return allowedScreen(data.view);
 if(data.person)return allowedScreen('people');
 if(data.action)return allowedAction(formRight(data.action));
 if(data.followup)return followupAllowed(data.followup);
 if(data.recurringCare)return allowedAction('care_activity');
 if(data.teamConversation)return allowedAction('team_activity');
 if(data.record)return allowedAction('upload');
 if(data.audio)return allowedAction('media');
 if(data.scorecard)return allowedAction('coaching_scorecard');
 if(data.coach)return allowedScreen('coaching');
 return true;
}
function syncAccessControls(root=document){
 const selectors='[data-view],[data-person],[data-action],[data-followup],[data-recurring-care],[data-team-conversation],'+
  '[data-record],[data-audio],[data-scorecard],[data-coach]';
 root.querySelectorAll(selectors).forEach(element=>{
  element.toggleAttribute('data-access-denied',!controlAllowed(element));
 });
 const controls={
  'import-action':allowedAction('contact_create')&&allowedAction('import'),
  'export-action':allowedAction('export'),'export-results':allowedAction('export'),
  'backup-action':backupAllowed,'playback-coach':allowedScreen('coaching'),'coach-save':allowedAction('review'),
  'coach-scorecard':allowedAction('coaching_scorecard'),'practice-form':allowedAction('practice_session')
 };
 Object.entries(controls).forEach(([id,allowed])=>$(id).toggleAttribute('data-access-denied',!allowed));
}
function readResetFragment(){
 const match=/^#reset=([a-f0-9]{64})$/.exec(location.hash);
 if(!match)return;
 resetToken=match[1];
 history.replaceState(null,'',location.pathname+location.search);
}
function showAuth(mode='login'){
 const normal=mode==='login';
 $('auth').hidden=false;
 $('app').hidden=true;
 $('auth-form').hidden=!normal;
 $('reset-request-form').hidden=mode!=='request';
 $('reset-redeem-form').hidden=mode!=='redeem';
 $('forgot-password').hidden=!normal||!resetEmailAvailable;
 $('reset-back').hidden=normal;
 $('setup-row').hidden=!authSetup;
 $('auth-help').hidden=!normal;
 $('auth-title').textContent=mode==='redeem'?'Nastavení nového hesla':mode==='request'?'Obnova hesla':
  authSetup?'První aktivace':'Přihlášení';
 $('auth-description').textContent=mode==='redeem'?'Zadej nové heslo o délce alespoň 12 znaků.':
  mode==='request'?'Na e-mail k účtu ti pošleme jednorázový odkaz.':'Tvůj soukromý obchodní prostor.';
 settingsStatus('reset-status');
}
function bindSubmit(id,statusId,handler){
 $(id).addEventListener('submit',async event=>{
  event.preventDefault();
  const form=event.currentTarget;
  const button=form.querySelector('button:not([type]),button[type="submit"]');
  if(button.disabled)return;
  button.disabled=true;
  settingsStatus(statusId);
  try{await handler(form,Object.fromEntries(new FormData(form)));}
  catch(error){settingsStatus(statusId,error.message,true);}
  finally{button.disabled=false;}
 });
}
function confirmPassword(data){
 if(data.password!==data.confirm)throw Error('Zadaná hesla se neshodují.');
 delete data.confirm;
}
function openAppearance(){
 appearanceDraft=structuredClone(appearance);
 const form=$('settings-appearance-form');
 for(const [key,[,fallback]] of Object.entries(APPEARANCE_INPUTS)){
  form.elements[key].value=appearance.colors?.[key]||fallback;
 }
 for(const item of settingsMenuItems)form.elements['label_'+item.id].value=appearance.labels?.[item.id]||'';
 form.elements.font_size.value=appearance.font_size||100;
 settingsStatus('settings-appearance-status');
 renderAppearancePreview();
}
function renderAppearancePreview(){
 applyAppearance(appearanceDraft);
 const sample=$('appearance-preview-menu');
 const items=settingsMenuItems.slice(0,4).map(item=>'<span>'+esc(menuLabel(item.id,appearanceDraft))+'</span>');
 sample.innerHTML=items.join('');
 $('appearance-font-output').textContent=(appearanceDraft.font_size||100)+' %';
}
function prepareAppearanceForm(){
 $('appearance-colors').innerHTML=Object.entries(APPEARANCE_INPUTS).map(([key,[label,fallback]])=>
  '<label>'+label+'<input name="'+key+'" type="color" value="'+fallback+'"></label>').join('');
 $('appearance-labels').innerHTML=settingsMenuItems.map(item=>
  '<label>'+esc(item.label)+'<input name="label_'+item.id+'" maxlength="32" placeholder="'+esc(item.label)+'"></label>').join('');
 $('settings-appearance-form').addEventListener('input',event=>{
  const {name,value}=event.target;
  if(name==='font_size')appearanceDraft.font_size=Number(value);
  else if(name.startsWith('label_'))(appearanceDraft.labels??={})[name.slice(6)]=value.trim();
  else if(Object.hasOwn(APPEARANCE_INPUTS,name))(appearanceDraft.colors??={})[name]=value;
  renderAppearancePreview();
 });
 $('appearance-defaults').addEventListener('click',()=>{
  const form=$('settings-appearance-form');
  form.reset();
  appearanceDraft={};
  renderAppearancePreview();
  settingsStatus('settings-appearance-status','Výchozí vzhled je připraven k uložení.');
 });
 bindSubmit('settings-appearance-form','settings-appearance-status',async()=>{
  const result=await request('admin_appearance',{
   colors:appearanceDraft.colors||{},labels:appearanceDraft.labels||{},font_size:appearanceDraft.font_size||100
  });
  appearance=result.appearance;
  applyAppearance(appearance);
  settingsStatus('settings-appearance-status','Vzhled uložen.');
 });
}
function permissionFields(labels,group){
 return Object.entries(labels).map(([key,label])=>'<label>'+esc(label)+'<select name="'+group+'_'+key+'">'+
  '<option value="">Podle role</option><option value="allow">Povolit</option><option value="deny">Zakázat</option></select></label>').join('');
}
function updateRoleForm(){
 const form=$('admin-account-form');
 const role=form.elements.role.value;
 $('admin-role-description').textContent=ROLE_DESCRIPTIONS[role];
 $('admin-permissions').hidden=role==='admin';
 form.querySelectorAll('#admin-rights select').forEach(select=>{
  select.disabled=role==='viewer'&&select.name!=='actions_media';
 });
}
function clearResetLink(){
 $('admin-reset-result').hidden=true;
 $('admin-reset-link').value='';
}
function populateAccount(id){
 accountId=id;
 const existing=adminData.accounts.find(row=>Number(row.id)===id);
 const form=$('admin-account-form');
 form.reset();
 $('admin-account-select').value=String(id);
 ['email',...Object.keys(PROFILE_FIELDS)].forEach(key=>{form.elements[key].value=existing?.[key]||'';});
 form.elements.role.value=existing?.role||'user';
 form.elements.active.checked=existing ? Boolean(Number(existing.active)) : true;
 for(const [group,labels] of Object.entries({screens:adminData.screens,actions:adminData.rights})){
  for(const key of Object.keys(labels)){
   const value=existing?.permissions?.[group]?.[key];
   form.elements[group+'_'+key].value=value===true?'allow':value===false?'deny':'';
  }
 }
 updateRoleForm();
 $('admin-issue-reset').hidden=!existing||!Number(existing.active);
 clearResetLink();
 settingsStatus('settings-users-status');
 form.hidden=false;
}
async function loadAdminUsers(id){
 settingsStatus('admin-load-status','Načítám účty…');
 try{
  adminData=await request('admin_state');
  const form=$('admin-account-form');
  $('admin-account-select').innerHTML='<option value="0">Nový účet</option>'+adminData.accounts.map(row=>
   '<option value="'+Number(row.id)+'">'+esc(row.email)+' · '+esc(ROLE_LABELS[row.role])+
   (Number(row.active)?'':' · neaktivní')+'</option>').join('');
  form.elements.role.innerHTML=Object.entries(adminData.roles).map(([key,label])=>
   '<option value="'+key+'">'+esc(label)+'</option>').join('');
  $('admin-screens').innerHTML=permissionFields(adminData.screens,'screens');
  $('admin-rights').innerHTML=permissionFields(adminData.rights,'actions');
  populateAccount(id??Number(adminData.accounts[0]?.id||0));
  settingsStatus('admin-load-status');
 }catch(error){
  settingsStatus('admin-load-status',error.message,true);
 }
}
function describeAudit(row){
 if(row.event!=='account_created'&&row.event!=='account_updated')return '';
 const change=JSON.parse(row.detail);
 const overrides=Object.values(change.permissions).reduce((count,group)=>count+Object.keys(group).length,0);
 return [ROLE_LABELS[change.role],change.active?'aktivní':'neaktivní',overrides+' individuálních pravidel'].join(' · ');
}
async function loadAudit(){
 settingsStatus('settings-audit-status','Načítám audit…');
 try{
  const result=await request('admin_state');
  const labels=new Map(result.accounts.map(row=>[Number(row.id),row.email]));
  const person=id=>id ? labels.get(Number(id))||'#'+Number(id) : '—';
  $('admin-audit-rows').innerHTML=result.audit.map(row=>'<tr>'+
   [row.created_at,AUDIT_NAMES[row.event]||row.event,person(row.actor_id),person(row.target_id),describeAudit(row)]
    .map(value=>'<td>'+esc(value)+'</td>').join('')+'</tr>').join('');
  settingsStatus('settings-audit-status',result.audit.length?'':'Zatím bez událostí.');
 }catch(error){settingsStatus('settings-audit-status',error.message,true);}
}
function installAccountAdmin(){
 readResetFragment();
 prepareAppearanceForm();
 $('settings-logout').addEventListener('click',logout);
 $('forgot-password').addEventListener('click',()=>showAuth('request'));
 $('reset-back').addEventListener('click',()=>{
  resetToken='';
  $('reset-redeem-form').reset();
  showAuth();
 });
 bindSubmit('reset-request-form','reset-status',async(form,data)=>{
  await request('reset_request',data);
  form.reset();
  settingsStatus('reset-status','Pokud je účet aktivní, dostaneš odkaz k obnově hesla.');
 });
 bindSubmit('reset-redeem-form','reset-status',async(form,data)=>{
  confirmPassword(data);
  const result=await request('reset_redeem',{...data,token:resetToken});
  csrf=result.csrf;
  resetToken='';
  form.reset();
  showAuth();
  settingsStatus('reset-status','Heslo uloženo. Teď se můžeš přihlásit.');
 });
 bindSubmit('settings-password-form','settings-password-status',async(form,data)=>{
  confirmPassword(data);
  const result=await request('password_change',data);
  csrf=result.csrf;
  form.reset();
  settingsStatus('settings-password-status','Heslo změněno. Ostatní relace byly ukončeny.');
 });
 $('admin-profile-fields').innerHTML='<label>E-mail<input name="email" type="email" maxlength="254" required></label>'+
  Object.entries(PROFILE_FIELDS).map(([key,[label,max]])=>'<label>'+label+'<input name="'+key+'" maxlength="'+max+'"'+
   (key==='phone'?' type="tel"':'')+'></label>').join('');
 $('admin-account-select').addEventListener('change',event=>populateAccount(Number(event.target.value)));
 $('admin-account-new').addEventListener('click',()=>{if(adminData)populateAccount(0);});
 $('admin-account-form').elements.role.addEventListener('change',updateRoleForm);
 bindSubmit('admin-account-form','settings-users-status',async(form,data)=>{
  const payload={id:accountId,active:form.elements.active.checked,permissions:{screens:{},actions:{}}};
  for(const key of ['email','role',...Object.keys(PROFILE_FIELDS)])payload[key]=data[key];
  for(const group of Object.keys(payload.permissions)){
   for(const [key,value] of Object.entries(data)){
    if(key.startsWith(group+'_')&&value)payload.permissions[group][key.slice(group.length+1)]=value==='allow';
   }
  }
  const result=await request('admin_account',payload);
  if(result.reauthenticate){location.reload();return;}
  if(result.id===signedInAccount.id){
   signedInAccount={...signedInAccount,...payload};
   applyUserPreferences();
  }
  await loadAdminUsers(result.id);
  settingsStatus('settings-users-status','Účet uložen.');
 });
 $('admin-issue-reset').addEventListener('click',async event=>{
  const button=event.currentTarget;
  const id=accountId;
  button.disabled=true;
  clearResetLink();
  try{
   const result=await request('admin_reset',{id});
   if(id!==accountId||$('settings-users-panel').hidden)return;
   $('admin-reset-link').value=new URL(result.reset,location.href).href;
   $('admin-reset-result').hidden=false;
   $('admin-reset-link').select();
   settingsStatus('settings-users-status','Odkaz je připraven.');
  }catch(error){settingsStatus('settings-users-status',error.message,true);}
  finally{button.disabled=false;}
 });
}
