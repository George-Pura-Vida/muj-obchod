'use strict';

const DEFAULT_DISPLAY_NAME='Jiří Janoušek';
let settingsMenuItems=[];
let settingsMenuDraft=[];
let settingsReturnView='overview';
let settingsMenuSaving=false;
let settingsDraggedItem=null;

function normalizedMenuOrder(saved){
 const defaults=settingsMenuItems.map(item=>item.id);
 const known=Array.isArray(saved)?saved.filter(id=>id!=='overview'&&defaults.includes(id)):[];
 return [...new Set(['overview',...known,...defaults])];
}
function applyUserPreferences(){
 const name=store.preferences?.display_name||DEFAULT_DISPLAY_NAME;
 const badge=name.split(/\s+/).slice(0,2).map(part=>Array.from(part)[0]).join('').toLocaleUpperCase('cs');
 ['profile-name','settings-account-name'].forEach(id=>{$(id).textContent=name;});
 ['profile-avatar','settings-avatar','header-avatar'].forEach(id=>{$(id).textContent=badge;});
 const nav=$('side-nav');
 const ordered=normalizedMenuOrder(store.preferences?.menu_order);
 ordered.forEach((id,index)=>{
  const button=settingsMenuItems.find(item=>item.id===id).button;
  if(nav.children[index]!==button)nav.insertBefore(button,nav.children[index]||null);
 });
}
function settingsStatus(id,message='',error=false){
 const status=$(id);
 status.dataset.error=String(error);
 status.textContent=message;
}
function selectUserSettingsTab(tab,focus=true){
 document.querySelectorAll('[data-settings-tab]').forEach(button=>{
  if(button.dataset.settingsTab===tab)button.setAttribute('aria-current','page');
  else button.removeAttribute('aria-current');
 });
 ['menu','profile'].forEach(id=>{$('settings-'+id+'-panel').hidden=id!==tab;});
 if(focus)$('settings-'+tab+'-panel').querySelector('h1').focus();
}
function beginUserSettings(previousView){
 settingsReturnView=previousView;
 settingsMenuDraft=normalizedMenuOrder(store.preferences?.menu_order);
 $('settings-display-name').value=store.preferences?.display_name||DEFAULT_DISPLAY_NAME;
 settingsStatus('settings-menu-status');
 settingsStatus('settings-profile-status');
 selectUserSettingsTab('menu',false);
 renderSettingsMenu();
}
function renderSettingsMenu(){
 const locked=id=>id==='overview';
 $('settings-menu-list').innerHTML=settingsMenuDraft.map((id,index)=>{
  const item=settingsMenuItems.find(entry=>entry.id===id);
  const label=esc(item.label);
  return '<li data-menu-item="'+id+'" tabindex="-1"'+(!locked(id)&&!settingsMenuSaving?' draggable="true"':'')+
   ' class="settings-menu-row'+(locked(id)?' locked':'')+'"><svg aria-hidden="true"><use href="'+esc(item.icon)+'"/></svg>'+
   '<span>'+label+'</span>'+
   (locked(id)?'<span class="settings-locked"><svg aria-hidden="true"><use href="#i-lock"/></svg> Pevná položka</span>':
    '<span class="settings-menu-controls"><span class="settings-grip" aria-hidden="true">⠿</span>'+
    '<button type="button" data-menu-move="-1" aria-label="'+label+' posunout nahoru"'+(index===1||settingsMenuSaving?' disabled':'')+'>↑</button>'+
    '<button type="button" data-menu-move="1" aria-label="'+label+' posunout dolů"'+(index===settingsMenuDraft.length-1||settingsMenuSaving?' disabled':'')+'>↓</button></span>'
   )+'</li>';
 }).join('');
 $('settings-menu-save').disabled=settingsMenuSaving||
  settingsMenuDraft.join()===normalizedMenuOrder(store.preferences?.menu_order).join();
 $('settings-menu-reset').disabled=settingsMenuSaving||settingsMenuDraft.join()===normalizedMenuOrder([]).join();
}
function reportSettingsMove(id,direction){
 const item=settingsMenuItems.find(entry=>entry.id===id);
 renderSettingsMenu();
 const row=$('settings-menu-list').querySelector('[data-menu-item="'+id+'"]');
 if(direction){
  const button=row.querySelector('[data-menu-move="'+direction+'"]:not(:disabled)');
  (button||row).focus();
 }
 settingsStatus('settings-menu-status',item.label+' přesunuto na pozici '+(settingsMenuDraft.indexOf(id)+1)+'.');
}
function installUserSettings(){
 const list=$('settings-menu-list');
 settingsMenuItems=[...$('side-nav').querySelectorAll('[data-view]')].map(button=>({
  id:button.dataset.view,button,icon:button.querySelector('use').getAttribute('href'),
  label:[...button.childNodes].filter(node=>node.nodeType===Node.TEXT_NODE).map(node=>node.textContent).join('').trim()
 }));
 $('settings-back').addEventListener('click',()=>{
  go(settingsReturnView);
  $('header-avatar').focus();
 });
 $('settings-view').addEventListener('click',event=>{
  const button=event.target.closest('[data-settings-tab]');
  if(button)selectUserSettingsTab(button.dataset.settingsTab);
 });
 list.addEventListener('click',event=>{
  const button=event.target.closest('[data-menu-move]');
  if(!button||settingsMenuSaving)return;
  const id=button.closest('[data-menu-item]').dataset.menuItem;
  const index=settingsMenuDraft.indexOf(id);
  const direction=Number(button.dataset.menuMove);
  const target=index+direction;
  if(index<1||target<1||target>=settingsMenuDraft.length)return;
  [settingsMenuDraft[index],settingsMenuDraft[target]]=[settingsMenuDraft[target],settingsMenuDraft[index]];
  reportSettingsMove(id,direction);
 });
 const clearDropTarget=()=>{const target=list.querySelector('[data-drop]');if(target)delete target.dataset.drop;};
 list.addEventListener('dragstart',event=>{
  const row=event.target.closest('[data-menu-item]');
  if(!row?.draggable||settingsMenuSaving)return;
  settingsDraggedItem=row.dataset.menuItem;
  event.dataTransfer.effectAllowed='move';
  event.dataTransfer.setData('text/plain',settingsDraggedItem);
 });
 list.addEventListener('dragover',event=>{
  const target=event.target.closest('[data-menu-item]');
  clearDropTarget();
  if(!settingsDraggedItem||!target?.draggable||target.dataset.menuItem===settingsDraggedItem)return;
  event.preventDefault();
  const rect=target.getBoundingClientRect();
  target.dataset.drop=event.clientY>rect.top+rect.height/2?'after':'before';
 });
 list.addEventListener('drop',event=>{
  const row=event.target.closest('[data-menu-item]');
  const source=settingsDraggedItem;
  settingsDraggedItem=null;
  const placement=row?.dataset.drop;
  clearDropTarget();
  if(!source||!placement||settingsMenuSaving)return;
  event.preventDefault();
  const order=settingsMenuDraft.filter(id=>id!==source);
  const index=order.indexOf(row.dataset.menuItem);
  order.splice(index+(placement==='after'?1:0),0,source);
  if(order.join()===settingsMenuDraft.join())return;
  settingsMenuDraft=order;
  reportSettingsMove(source);
 });
 list.addEventListener('dragend',()=>{settingsDraggedItem=null;clearDropTarget();});
 $('settings-menu-reset').addEventListener('click',()=>{
  settingsMenuDraft=normalizedMenuOrder([]);
  renderSettingsMenu();
  settingsStatus('settings-menu-status',$('settings-menu-save').disabled?'Výchozí pořadí je aktivní.':
   'Výchozí pořadí je připravené. Potvrď ho tlačítkem Uložit pořadí.');
 });
 $('settings-menu-form').addEventListener('submit',async event=>{
  event.preventDefault();
  if($('settings-menu-save').disabled)return;
  const order=[...settingsMenuDraft];
  settingsMenuSaving=true;
  renderSettingsMenu();
  try{
   await request('user_preferences',{menu_order:order});
   store.preferences={...store.preferences,menu_order:order};
   settingsMenuDraft=[...order];
   applyUserPreferences();
   settingsStatus('settings-menu-status','Pořadí menu uloženo.');
  }catch(error){
   settingsStatus('settings-menu-status',error.message,true);
  }finally{
   settingsMenuSaving=false;
   renderSettingsMenu();
  }
 });
 $('settings-profile-form').addEventListener('submit',async event=>{
  event.preventDefault();
  const field=$('settings-display-name');
  const save=$('settings-profile-save');
  const name=field.value.trim();
  save.disabled=true;
  field.readOnly=true;
  try{
   await request('user_preferences',{display_name:name});
   store.preferences={...store.preferences,display_name:name};
   field.value=name;
   applyUserPreferences();
   settingsStatus('settings-profile-status','Zobrazované jméno uloženo.');
  }catch(error){
   settingsStatus('settings-profile-status',error.message,true);
  }finally{
   field.readOnly=false;
   save.disabled=false;
  }
 });
}
