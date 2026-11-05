const fs = require('fs');
const vm = require('vm');
const assert = require('node:assert/strict');
const script = fs.readFileSync('assets/js/planning_mingguan.js', 'utf8');

// DOM tiruan secukupnya untuk planning_mingguan.js.
const kelas = () => {
    const isi = new Set();
    return {
        isi,
        toggle(nama, aktif) { aktif ? isi.add(nama) : isi.delete(nama); },
        add(nama) { isi.add(nama); },
        remove(nama) { isi.delete(nama); },
        contains(nama) { return isi.has(nama); }
    };
};
const tombol = dataset => ({
    dataset, hidden: false, textContent: '', atribut: {}, classList: kelas(),
    setAttribute(nama, nilai) { this.atribut[nama] = nilai; },
    addEventListener(nama, fn) { this[nama] = fn; }
});

function buatSlot(jenisKartu) {
    const section = { dataset: {} };
    const cards = jenisKartu.map(jenis => {
        const card = { dataset: { jenis }, hidden: false, classList: kelas() };
        card.radio = {
            checked: false, name: 'menu[1][pagi]', value: jenis, dataset: { day: '1', slot: 'pagi' },
            closest: selector => selector === '.menu-card' ? card : selector === '.meal-section' ? section : null,
            addEventListener(nama, fn) { this[nama] = fn; }
        };
        card.querySelector = selector => selector.includes('radio') ? card.radio : null;
        return card;
    });
    const jenisUnik = [...new Set(jenisKartu)];
    const chips = [tombol({ jenis: '' }), ...jenisUnik.map(jenis => tombol({ jenis }))];
    const filter = { hidden: false };
    const lainnya = tombol({});
    const ganti = tombol({});
    Object.assign(section, {
        cards, chips, filter, lainnya, ganti,
        querySelectorAll: selector => ({ '.menu-card': cards, '.lauk-chip': chips })[selector] || [],
        querySelector: selector => ({ '.lauk-filter': filter, '.menu-more': lainnya, '.menu-change': ganti })[selector] || null
    });
    return section;
}

function jalankan(sections) {
    const tab = tombol({ day: '1' });
    tab.textContent = 'Senin';
    const tabSelasa = tombol({ day: '2' });
    tabSelasa.textContent = 'Selasa';
    const lanjut = tombol({ next: '2' });
    lanjut.textContent = 'Lanjut ke hari Selasa →';
    lanjut.digulir = 0;
    lanjut.scrollIntoView = () => { lanjut.digulir++; };
    const hari = {
        dataset: { dayContent: '1' }, classList: kelas(),
        querySelectorAll: selector => selector === '.meal-section' ? sections : [],
        querySelector: selector => selector === '.day-next' ? lanjut : null
    };
    const radios = sections.flatMap(section => section.cards.map(card => card.radio));
    const form = { dataset: { planId: '0' }, classList: kelas(), querySelector: () => null };
    const pilihan = {
        '.planning-day-tab': [tab, tabSelasa],
        '.planning-day-content': [hari],
        '.menu-card input[type="radio"]': radios,
        '.meal-section': sections
    };
    vm.runInNewContext(script, {
        document: {
            addEventListener: (_, fn) => fn(),
            querySelectorAll: selector => pilihan[selector] || [],
            getElementById: id => id === 'planningForm' ? form : null
        },
        fetch: () => new Promise(() => {}), URLSearchParams, console, alert() {}, window: { location: { href: '' } },
        setTimeout: () => 0, clearTimeout() {}
    });
    return { tab, lanjut };
}

const tampil = section => section.cards.map(card => !card.hidden);
const pilih = (section, i) => {
    section.cards.forEach((card, j) => { card.radio.checked = j === i; });
    section.cards[i].radio.change();
};
const klikChip = (section, jenis) => section.chips.find(chip => chip.dataset.jenis === jenis).click();

// Kartu sudah terurut menurut skor dari server: posisi 0 paling sesuai.
const slot = buatSlot(['ayam', 'ikan', 'ayam', 'ikan', 'udang']);
const lain = buatSlot(['ayam', 'ikan']);
lain.cards[1].radio.checked = true;
const { tab, lanjut } = jalankan([slot, lain]);

assert.deepEqual(tampil(slot), [true, true, true, false, false], 'Semua: hanya 3 menu teratas yang tampil');
assert.equal(slot.lainnya.hidden, false, 'tombol menu lainnya muncul');
assert.match(slot.lainnya.textContent, /2 menu lainnya/);
assert.equal(slot.ganti.hidden, true, 'tombol ganti belum muncul sebelum ada pilihan');

slot.lainnya.click();
assert.deepEqual(tampil(slot), [true, true, true, true, true], 'Lihat lainnya: semua menu tampil');
assert.equal(slot.lainnya.hidden, true);

klikChip(slot, 'ikan');
assert.deepEqual(tampil(slot), [false, true, false, true, false], 'Ikan: hanya kartu ikan');
assert.equal(slot.chips[2].classList.contains('active'), true);
assert.equal(slot.chips[2].atribut['aria-pressed'], 'true');
assert.equal(slot.chips[0].classList.contains('active'), false);

pilih(slot, 3);
assert.deepEqual(tampil(slot), [false, false, false, true, false], 'Sesudah memilih: hanya menu terpilih yang tampil');
assert.equal(slot.filter.hidden, true, 'tombol jenis lauk disembunyikan saat ringkas');
assert.equal(slot.ganti.hidden, false, 'tombol ganti menu muncul');
assert.equal(slot.lainnya.hidden, true);
assert.equal(lanjut.digulir, 1, 'Hari jadi lengkap: halaman digulir ke tombol lanjut ke hari berikutnya');
assert.equal(lanjut.classList.contains('sorot'), true, 'tombol lanjut disorot');

slot.ganti.click();
assert.deepEqual(tampil(slot), [false, true, false, true, false], 'Ganti menu: daftar dibuka lagi dengan jenis terakhir');
assert.equal(slot.filter.hidden, false);
assert.equal(slot.ganti.hidden, true);

klikChip(slot, 'ayam');
assert.deepEqual(tampil(slot), [true, false, true, true, false], 'Ayam: menu terpilih tetap terlihat');

klikChip(slot, '');
assert.deepEqual(tampil(slot), [true, true, true, true, false], 'Semua: 3 teratas ditambah menu terpilih');

pilih(slot, 0);
assert.equal(lanjut.digulir, 1, 'Mengganti menu di hari yang sudah lengkap tidak menggulir lagi');

assert.deepEqual(tampil(lain), [false, true], 'Slot yang sudah punya pilihan tersimpan langsung ringkas');
assert.equal(lain.ganti.hidden, false);

assert.equal(tab.classList.contains('lengkap'), true, 'Hari dengan semua waktu makan terisi diberi tanda lengkap');

console.log('PASS: Planning menampilkan 3 menu teratas, menyaring jenis lauk, dan meringkas waktu makan yang sudah dipilih');
