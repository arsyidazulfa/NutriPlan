const fs = require('fs');
const vm = require('vm');
const assert = require('node:assert/strict');
const script = fs.readFileSync('assets/js/planning_mingguan.js', 'utf8');

/* ---------- Aturan isi otomatis (fungsi murni) ---------- */

const konteks = {
    document: { addEventListener() {} },
    console
};
vm.runInNewContext(script, konteks);
const pilih = konteks.pilihMenuOtomatis;

assert.equal(typeof pilih, 'function', 'pilihMenuOtomatis belum ada');

const urut = (awalan, n) => Array.from({ length: n }, (_, i) => `${awalan}${i + 1}`);
const minggu = slotPerHari => Array.from({ length: 7 }, (_, hari) => slotPerHari(hari));

// Hari ke-n memakai menu peringkat ke-n dari 7 menu teratas.
let hasil = pilih(minggu(() => [{ kandidat: urut('p', 9), dipilih: '' }]));
assert.deepEqual(hasil.map(h => h[0]), urut('p', 7), 'Senin peringkat 1, Selasa peringkat 2, ... Minggu peringkat 7');

// Kandidat kurang dari 7: berputar dari peringkat 1 lagi.
hasil = pilih(minggu(() => [{ kandidat: ['a', 'b', 'c'], dipilih: '' }]));
assert.deepEqual(hasil.map(h => h[0]), ['a', 'b', 'c', 'a', 'b', 'c', 'a'], 'kandidat sedikit berputar');

// Menu yang sudah dipakai di hari yang sama dilewati.
hasil = pilih([[
    { kandidat: ['m1', 'm2'], dipilih: '' },
    { kandidat: ['m1', 'm3'], dipilih: '' }
]]);
assert.deepEqual(hasil[0], ['m1', 'm3'], 'makan malam tidak mengulang menu makan siang');

// Menu yang dipakai kemarin juga dilewati selama masih ada pilihan lain.
hasil = pilih([
    [{ kandidat: ['m1', 'm2', 'm3'], dipilih: '' }, { kandidat: ['m2', 'm1', 'm4'], dipilih: '' }],
    [{ kandidat: ['m1', 'm2', 'm3'], dipilih: '' }, { kandidat: ['m2', 'm1', 'm4'], dipilih: '' }]
]);
assert.deepEqual(hasil, [['m1', 'm2'], ['m3', 'm4']], 'Selasa tidak mengulang menu Senin');

// Pilihan orang tua tidak diubah dan ikut dihitung sebagai menu terpakai.
hasil = pilih([[
    { kandidat: ['m1', 'm2'], dipilih: 'm9' },
    { kandidat: ['m9', 'm2'], dipilih: '' },
    { kandidat: [], dipilih: '' }
]]);
assert.deepEqual(hasil[0], ['m9', 'm2', ''], 'pilihan yang ada dipertahankan; slot tanpa kandidat tetap kosong');

// Semua kandidat sudah terpakai di hari itu: tetap diisi peringkat sesuai hari.
hasil = pilih([[
    { kandidat: ['x'], dipilih: '' },
    { kandidat: ['x'], dipilih: '' }
]]);
assert.deepEqual(hasil[0], ['x', 'x'], 'kalau tidak ada pilihan lain, menu boleh sama');

console.log('PASS: Isi otomatis memutar 7 menu teratas dan tidak mengulang menu hari itu maupun kemarin');

/* ---------- Tombol isi otomatis di halaman ---------- */

const kelas = () => {
    const isi = new Set();
    return {
        toggle(nama, aktif) { aktif ? isi.add(nama) : isi.delete(nama); },
        add(nama) { isi.add(nama); }, remove(nama) { isi.delete(nama); },
        contains(nama) { return isi.has(nama); }
    };
};
const tombol = dataset => ({
    dataset, hidden: false, textContent: '', classList: kelas(), atribut: {},
    setAttribute(nama, nilai) { this.atribut[nama] = nilai; },
    addEventListener(nama, fn) { this[nama] = fn; }
});

function buatSlot(hari, slot, ids, dipilih = '') {
    const section = { dataset: {}, scrollIntoView() {} , classList: kelas() };
    const cards = ids.map(id => {
        const card = { dataset: { jenis: 'ayam' }, hidden: false, classList: kelas() };
        card.radio = {
            checked: id === dipilih, value: id, name: `menu[${hari}][${slot}]`, dataset: { day: String(hari), slot },
            closest: s => s === '.menu-card' ? card : s === '.meal-section' ? section : null,
            listener: {},
            addEventListener(nama, fn) { this.listener[nama] = fn; },
            dispatchEvent(event) { this.listener[event.type]?.(event); }
        };
        card.querySelector = s => s.includes('radio') ? card.radio : null;
        return card;
    });
    Object.assign(section, {
        cards,
        querySelectorAll: s => s === '.menu-card' ? cards : s === '.lauk-chip' ? [] : [],
        querySelector: s => ({ '.menu-more': tombol({}), '.menu-change': tombol({}) })[s] || null
    });
    return section;
}

const hariDom = [];
const tabs = [];
for (let hari = 1; hari <= 7; hari++) {
    const sections = [
        buatSlot(hari, 'pagi', urut('p', 8), hari === 2 ? 'p8' : ''),
        buatSlot(hari, 'siang', ['s1', 's2']),
        buatSlot(hari, 'malam', ['s1', 's3'])
    ];
    const lanjut = tombol({ next: String(hari + 1) });
    lanjut.digulir = 0;
    lanjut.scrollIntoView = () => { lanjut.digulir++; };
    hariDom.push({
        dataset: { dayContent: String(hari) }, classList: kelas(), sections, lanjut,
        querySelectorAll: s => s === '.meal-section' ? sections : [],
        querySelector: s => s === '.day-next' ? lanjut : null
    });
    tabs.push(tombol({ day: String(hari) }));
}
const semuaSection = hariDom.flatMap(h => h.sections);
const radios = semuaSection.flatMap(s => s.cards.map(c => c.radio));
const isiOtomatis = tombol({});
const toast = { hidden: true, textContent: '' };
const form = { dataset: { planId: '0' }, classList: kelas(), querySelector: () => null };
const dikirim = [];

vm.runInNewContext(script, {
    document: {
        addEventListener: (_, fn) => fn(),
        querySelectorAll: s => ({
            '.planning-day-tab': tabs,
            '.planning-day-content': hariDom,
            '.menu-card input[type="radio"]': radios,
            '.meal-section': semuaSection
        })[s] || [],
        querySelector: () => null,
        getElementById: id => ({ planningForm: form, isiOtomatis, planningToast: toast })[id] || null
    },
    Event: class { constructor(type) { this.type = type; } },
    fetch: (_, opsi) => { dikirim.push(String(opsi.body)); return new Promise(() => {}); },
    URLSearchParams, console, setTimeout, clearTimeout, alert() { throw new Error('alert tidak boleh dipakai'); },
    window: { location: { href: '' } }
});

(async () => {
isiOtomatis.click();
await new Promise(selesai => setImmediate(selesai));

const dipilihDi = hari => hariDom[hari - 1].sections.map(
    s => s.cards.find(c => c.radio.checked)?.radio.value || ''
);

assert.deepEqual(dipilihDi(1), ['p1', 's1', 's3'], 'Senin terisi peringkat 1, malam tidak mengulang siang');
assert.deepEqual(dipilihDi(2), ['p8', 's2', 's3'], 'Selasa: sarapan pilihan orang tua (p8) tidak diubah');
assert.deepEqual(dipilihDi(7), ['p7', 's1', 's3'], 'Minggu terisi peringkat 7');
assert.equal(semuaSection.every(s => s.cards.some(c => c.radio.checked)), true, 'semua 21 waktu makan terisi');
// Selasa sarapan sudah ada, jadi yang perlu disimpan 20 pilihan. Disimpan berurutan,
// jadi yang langsung terkirim baru yang pertama; sisanya menunggu di antrean.
assert.equal(dikirim.length, 1, 'penyimpanan berjalan lewat antrean autosave');
assert.match(dikirim[0], /hari_ke=1&slot=pagi&id_menu=p1/);
assert.equal(tabs.every(tab => tab.classList.contains('lengkap')), true, 'semua tab hari bertanda lengkap');
assert.equal(hariDom.every(h => h.lanjut.digulir === 0), true, 'isi otomatis tidak menggulir ke tombol lanjut');
assert.equal(toast.hidden, false, 'pesan hasil isi otomatis tampil');
assert.match(toast.textContent, /20 menu/);

console.log('PASS: Tombol isi otomatis mengisi waktu makan yang kosong dan menyimpannya lewat autosave');
process.exit(0);
})().catch(error => { console.error(error); process.exit(1); });
