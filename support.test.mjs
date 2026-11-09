import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';

const html=readFileSync(new URL('./support.html',import.meta.url),'utf8');
const script=readFileSync(new URL('./support.js',import.meta.url),'utf8');
const paySource=readFileSync(new URL('./support-payment.js',import.meta.url),'utf8');
const mod=await import('data:text/javascript;base64,'+Buffer.from(paySource).toString('base64'));

test('QR platba a jedinečný variabilní symbol',()=>{
  assert.equal(mod.amountInHellers('1,01'),101);
  assert.equal(mod.amountInHellers('250'),25000);
  assert.throws(()=>mod.amountInHellers('0.50'));
  assert.throws(()=>mod.amountInHellers('250*CC:EUR'));
  assert.match(mod.paymentDescriptor('250','12345678'),/\\*AM:250\\.00\\*CC:CZK\\*X-VS:12345678/);
  assert.doesNotMatch(mod.paymentDescriptor('250'),/X-VS/);
  assert.throws(()=>mod.paymentDescriptor('250','badVS'));
  assert.match(mod.payment.message,/MŮJ OBCHOD/);
});

test('Formulář obsahuje všechny používané DOM prvky a souhlas',()=>{
  const ids=[...script.matchAll(/getElementById\\('([^']+)'\\)/g)].map(m=>m[1]);
  for(const id of ids) assert.ok(html.includes('id="'+id+'"'),'Chybí #'+id);
  assert.match(html,/id="donorConsent"/);
  assert.match(html,/id="prepareAnonymous"/);
});

test('Platební požadavek vzniká výhradně na centrálním serveru',()=>{
  assert.match(script,/https:\\/\\/muj-dip\\.jirijanousek\\.cz\\/donation-intent-cross\\.php/);
  assert.match(script,/credentials:'omit'/);
  assert.match(script,/acknowledge:true/);
  assert.doesNotMatch(script,/FIO_TOKEN|fio-token|secret/i);
  assert.match(html,/Tato stránka platbu neprovádí ani nepotvrzuje její přijetí/);
});
