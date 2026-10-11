const fs = require('fs');
const vm = require('vm');
const assert = require('node:assert/strict');
const script = fs.readFileSync('assets/js/planning_mingguan.js', 'utf8');
const callbacks = {};
const button = { disabled: true, addEventListener: (name, fn) => callbacks[name] = fn };
const status = {};
const radios = ['1', '2'].map(value => ({
    value, name: 'menu[1][pagi]', dataset: {day:'1', slot:'pagi'}, checked:false,
    closest: () => null, addEventListener(name, fn) { this[name] = fn; }
}));
const form = {dataset:{planId:'0'}, querySelector(selector) {
    if (selector.includes('kategori_protein')) return {value:'hewani'};
    if (selector.includes('tanggal_mulai')) return {value:'2026-10-05'};
    return null;
}};
const pending = [];
const sent = [];
const context = {
    document: {
        addEventListener: (_, fn) => fn(),
        querySelectorAll: selector => selector.includes('radio') ? radios : [],
        getElementById: id => ({planningForm:form, autosaveStatus:status, lihatCaraMengolah:button})[id]
    },
    fetch: (_, options) => { sent.push(new URLSearchParams(options.body)); return new Promise(resolve => pending.push(resolve)); },
    URLSearchParams, console:{error(){}}, alert(){}, window:{location:{href:''}}
};
vm.runInNewContext(script, context);
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    radios[0].checked = true;
    radios[0].change();
    radios[0].checked = false;
    radios[1].checked = true;
    radios[1].change();
    await tick();
    assert.equal(sent.length, 1);
    assert.equal(button.disabled, true);
    pending.shift()({ok:true,json:async()=>({ok:true,id_plan:10})});
    await tick();
    assert.equal(sent.length, 2);
    assert.equal(sent[1].get('id_menu'), '2');
    assert.equal(button.disabled, true);
    pending.shift()({ok:false,json:async()=>({ok:false,message:'simulasi gagal'})});
    await tick();
    assert.equal(button.disabled, true);
    assert.equal(radios[1].checked, false);
    radios[1].checked = true;
    radios[1].change();
    await tick();
    pending.shift()({ok:true,json:async()=>({ok:true,id_plan:10})});
    await tick();
    assert.equal(button.disabled, false);
    assert.equal(status.textContent, '✓ Tersimpan otomatis');
    console.log('PASS: Autosave berurutan, navigasi menunggu, kegagalan dan retry ditangani');
})().catch(error => { console.error(error); process.exitCode = 1; });
