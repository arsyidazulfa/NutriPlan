// Kalender tanggal mulai pada halaman Pilih Sumber Protein.
// Native picker mengikuti tampilan browser/OS. Hari selain Minggu ditolak.
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('tanggal_mulai');
    const info = document.getElementById('periodePlanning');
    if (!input || !info) return;

    const pesanAwal = 'Pilih hari Minggu. Planning berlangsung 7 hari sampai Sabtu.';
    const formatTanggal = tanggal => tanggal.toLocaleDateString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric'
    });

    function perbaruiPeriode() {
        if (!input.value) {
            input.setCustomValidity('');
            info.textContent = pesanAwal;
            return;
        }

        // Jam 12:00 lokal untuk menghindari pergeseran tanggal akibat timezone.
        const mulai = new Date(input.value + 'T12:00:00');
        if (Number.isNaN(mulai.getTime())) {
            input.setCustomValidity('Tanggal tidak valid.');
            info.textContent = 'Masukkan tanggal yang valid.';
            return;
        }
        if (mulai.getDay() !== 0) {
            input.setCustomValidity('Tanggal mulai planning harus hari Minggu.');
            info.textContent = 'Tanggal mulai harus hari Minggu. Silakan pilih ulang.';
            return;
        }

        input.setCustomValidity('');
        const selesai = new Date(mulai);
        selesai.setDate(selesai.getDate() + 6);
        info.textContent = `Periode planning: ${formatTanggal(mulai)} – ${formatTanggal(selesai)}`;
    }

    input.addEventListener('input', perbaruiPeriode);
    input.addEventListener('change', perbaruiPeriode);
    perbaruiPeriode();
});

// Isi otomatis: hari ke-n memakai menu peringkat ke-n dari 7 menu teratas
// (berputar lagi dari peringkat 1 bila kandidat kurang dari 7). Menu yang sudah
// dipakai di hari yang sama atau sehari sebelumnya dilewati supaya menunya bervariasi;
// bila semua kandidat sudah terpakai, aturan "sehari sebelumnya" dilonggarkan dulu.
// hari: [[{ kandidat: [id, ...] terurut menurut skor, dipilih: id atau '' }, ...], ...]
function pilihMenuOtomatis(hari, batas = 7) {
    let kemarin = new Set();

    return hari.map((slots, urutanHari) => {
        const hariIni = new Set(slots.map(slot => slot.dipilih).filter(Boolean));

        const hasil = slots.map(slot => {
            if (slot.dipilih) return slot.dipilih;

            const n = Math.min(batas, slot.kandidat.length);
            if (n === 0) return '';

            const urutan = Array.from(
                { length: n },
                (_, geser) => slot.kandidat[(urutanHari + geser) % n]
            );

            const id =
                urutan.find(item => !hariIni.has(item) && !kemarin.has(item)) ??
                urutan.find(item => !hariIni.has(item)) ??
                urutan[0];

            hariIni.add(id);
            return id;
        });

        kemarin = new Set(hasil.filter(Boolean));
        return hasil;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('planningForm');
    if (!form) return;

    const tabs = document.querySelectorAll('.planning-day-tab');
    const contents = document.querySelectorAll('.planning-day-content');
    const radios = document.querySelectorAll('.menu-card input[type="radio"]');
    const status = document.getElementById('autosaveStatus');
    const tombolLanjut = document.getElementById('lihatCaraMengolah');

    let idPlan = Number(form.dataset.planId || 0);
    let antrean = Promise.resolve();
    let pending = 0;

    const gagal = new Set();
    const versiPilihan = new Map();

    // Pesan singkat di bawah layar, pengganti alert() dan status yang ada di paling bawah halaman.
    const toast = document.getElementById('planningToast');
    let timerToast;
    let pesanSelesai = '';
    let pesanErrorTerakhir = '';

    function tampilkanPesan(teks, lama = 3500) {
        if (!toast) return;

        toast.textContent = teks;
        toast.hidden = false;

        clearTimeout(timerToast);
        timerToast = setTimeout(() => {
            toast.hidden = true;
        }, lama);
    }

    function bukaHari(hari) {
        tabs.forEach(tab => {
            const aktif = tab.dataset.day === String(hari);
            tab.classList.toggle('active', aktif);
            tab.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });

        contents.forEach(content => {
            content.classList.toggle(
                'active',
                content.dataset.dayContent === String(hari)
            );
        });
    }

    function perbaruiTampilan() {
        radios.forEach(radio => {
            const card = radio.closest('.menu-card');
            if (!card) return;

            card.classList.toggle('selected', radio.checked);

            const button = card.querySelector('.menu-select-button');
            if (button) {
                button.textContent = radio.checked
                    ? '✓ Dipilih'
                    : 'Pilih Menu';
            }
        });

        sections.forEach(aturSlot);
        tandaiHari();
    }

    function perbaruiTombolLanjut() {
        if (!tombolLanjut) return;

        tombolLanjut.disabled =
            idPlan <= 0 ||
            pending > 0 ||
            gagal.size > 0;
    }

    async function simpanOtomatis(radio, versi) {
        const data = new URLSearchParams({
            aksi: 'autosave',
            kategori_protein:
                form.querySelector('[name="kategori_protein"]')?.value || '',
            tanggal_mulai:
                form.querySelector('[name="tanggal_mulai"]')?.value || '',
            hari_ke: radio.dataset.day || '',
            slot: radio.dataset.slot || '',
            id_menu: radio.value || ''
        });

        if (status) status.textContent = 'Menyimpan...';

        try {
            const response = await fetch(
                '/index.php?page=planning_mingguan',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: data.toString()
                }
            );

            const result = await response.json();

            if (!response.ok || !result.ok) {
                throw new Error(
                    result.message || 'Pilihan gagal disimpan.'
                );
            }

            idPlan = Number(result.id_plan || idPlan);
            gagal.delete(radio.name);
            if (gagal.size === 0) pesanErrorTerakhir = '';

            if (status) {
                status.textContent = '✓ Tersimpan otomatis';
            }

        } catch (error) {
            gagal.add(radio.name);
            pesanErrorTerakhir = error.message || 'Tidak diketahui';

            if (
                radio.checked &&
                versiPilihan.get(radio.name) === versi
            ) {
                radio.checked = false;
            }

            perbaruiTampilan();

            if (status) {
                status.textContent = 'Gagal menyimpan: ' + pesanErrorTerakhir;
            }

            console.error(error);
        }
    }

    function planningLengkap() {
        const labelSlot = {
            pagi: 'sarapan',
            siang: 'makan siang/bekal',
            malam: 'makan malam'
        };

        for (let hari = 1; hari <= 7; hari++) {
            for (const slot of Object.keys(labelSlot)) {
                const dipilih = form.querySelector(
                    `input[name="menu[${hari}][${slot}]"]:checked`
                );

                if (dipilih) continue;

                bukaHari(hari);

                const namaHari =
                    tabs[hari - 1]?.textContent.trim() || 'tersebut';

                const section = form
                    .querySelector(`input[name="menu[${hari}][${slot}]"]`)
                    ?.closest('.meal-section');

                if (section) {
                    section.dataset.ganti = '1';
                    aturSlot(section);
                    section.classList.add('perlu-dipilih');
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                tampilkanPesan(
                    `Pilih menu ${labelSlot[slot]} untuk hari ${namaHari} dulu.`
                );

                return false;
            }
        }

        return true;
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            bukaHari(tab.dataset.day);
        });
    });

    // Satu waktu makan (sarapan/siang/malam) punya tiga keadaan tampilan:
    // - belum dipilih: tampil 3 menu teratas, sisanya lewat tombol "menu lainnya";
    // - tombol jenis lauk: hanya kartu jenis itu (menu terpilih tetap terlihat);
    // - sudah dipilih: hanya kartu terpilih + tombol "Ganti menu".
    // Kartu sudah terurut menurut skor dari server, jadi 3 teratas = 3 paling sesuai.
    const MENU_AWAL = 3;
    const sections = document.querySelectorAll('.meal-section');

    function aturSlot(section) {
        const cards = Array.from(section.querySelectorAll('.menu-card'));
        const dipilih = cards.find(
            card => card.querySelector('input[type="radio"]')?.checked
        );
        const jenis = section.dataset.jenis || '';
        const ringkas = Boolean(dipilih) && section.dataset.ganti !== '1';
        const semua = section.dataset.semua === '1';

        let urutan = 0;
        let tersembunyi = 0;

        cards.forEach(card => {
            if (card === dipilih) {
                card.hidden = false;
                return;
            }

            if (ringkas || (jenis !== '' && card.dataset.jenis !== jenis)) {
                card.hidden = true;
                return;
            }

            urutan++;
            card.hidden = jenis === '' && !semua && urutan > MENU_AWAL;
            if (card.hidden) tersembunyi++;
        });

        const filter = section.querySelector('.lauk-filter');
        if (filter) filter.hidden = ringkas;

        const lainnya = section.querySelector('.menu-more');
        if (lainnya) {
            lainnya.hidden = tersembunyi === 0;
            lainnya.textContent = `Lihat ${tersembunyi} menu lainnya`;
        }

        const ganti = section.querySelector('.menu-change');
        if (ganti) ganti.hidden = !ringkas;
    }

    function tandaiHari() {
        contents.forEach(content => {
            const slot = Array.from(content.querySelectorAll('.meal-section'));
            const lengkap = slot.length > 0 && slot.every(section =>
                Array.from(section.querySelectorAll('.menu-card')).some(
                    card => card.querySelector('input[type="radio"]')?.checked
                )
            );

            tabs.forEach(tab => {
                if (tab.dataset.day === content.dataset.dayContent) {
                    tab.classList.toggle('lengkap', lengkap);
                }
            });
        });
    }

    sections.forEach(section => {
        const chips = section.querySelectorAll('.lauk-chip');

        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                section.dataset.jenis = chip.dataset.jenis || '';

                chips.forEach(item => {
                    const aktif = item === chip;
                    item.classList.toggle('active', aktif);
                    item.setAttribute('aria-pressed', aktif ? 'true' : 'false');
                });

                aturSlot(section);
            });
        });

        section.querySelector('.menu-more')?.addEventListener('click', () => {
            section.dataset.semua = '1';
            aturSlot(section);
        });

        section.querySelector('.menu-change')?.addEventListener('click', () => {
            section.dataset.ganti = '1';
            aturSlot(section);
        });

        aturSlot(section);
    });

    tandaiHari();

    // Tombol "Lanjut ke hari berikutnya" di bawah tiap hari.
    document.querySelectorAll('.day-next').forEach(button => {
        button.addEventListener('click', () => {
            bukaHari(button.dataset.next);
            document.querySelector('.planning-day-tabs')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Begitu satu hari lengkap, arahkan ke tombol "Lanjut ke hari ..." di bawahnya
    // supaya orang tua tidak perlu menggulir ke atas untuk memilih hari.
    let isiOtomatisBerjalan = false;

    function arahkanKeHariBerikutnya(hari) {
        const content = Array.from(contents).find(
            item => item.dataset.dayContent === String(hari)
        );
        const tombol = content?.querySelector('.day-next');
        const namaHari = tabs[Number(hari) - 1]?.textContent.trim() || 'Hari ini';
        const semuaLengkap = Array.from(tabs).every(
            tab => tab.classList.contains('lengkap')
        );

        let pesan;

        if (semuaLengkap) {
            tombolLanjut?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            pesan = '✓ Semua hari sudah lengkap. Tekan "Lihat Cara Mengolah".';
        } else if (tombol) {
            tombol.scrollIntoView({ behavior: 'smooth', block: 'center' });
            tombol.classList.add('sorot');
            setTimeout(() => tombol.classList.remove('sorot'), 2400);
            const besok = tabs[Number(hari)]?.textContent.trim() || 'hari berikutnya';
            pesan = `✓ ${namaHari} lengkap. Tekan tombol hijau untuk lanjut ke ${besok}.`;
        } else {
            return;
        }

        pesanSelesai = pesan;
        tampilkanPesan(pesan, 6000);
    }

    // Tombol isi otomatis: hanya mengisi waktu makan yang masih kosong.
    document.getElementById('isiOtomatis')?.addEventListener('click', () => {
        const hari = Array.from(contents).map(content =>
            Array.from(content.querySelectorAll('.meal-section')).map(section => {
                const pilihan = Array.from(section.querySelectorAll('.menu-card'))
                    .map(card => card.querySelector('input[type="radio"]'))
                    .filter(Boolean);

                return {
                    pilihan,
                    kandidat: pilihan.map(radio => radio.value),
                    dipilih: pilihan.find(radio => radio.checked)?.value || ''
                };
            })
        );

        const hasil = pilihMenuOtomatis(hari);
        let jumlah = 0;

        hari.forEach((slots, i) => {
            slots.forEach((slot, j) => {
                const radio = slot.pilihan.find(item => item.value === hasil[i][j]);

                if (slot.dipilih || !radio) return;

                radio.checked = true;
                isiOtomatisBerjalan = true;
                radio.dispatchEvent(new Event('change'));
                isiOtomatisBerjalan = false;
                jumlah++;
            });
        });

        if (jumlah === 0) {
            tampilkanPesan('Semua waktu makan sudah terisi.');
            return;
        }

        pesanSelesai =
            `✓ ${jumlah} menu terisi otomatis dan tersimpan. ` +
            'Menu yang kurang cocok bisa diganti lewat tombol "Ganti menu".';
        tampilkanPesan(`Mengisi dan menyimpan ${jumlah} menu...`, 60000);
    });

    // Target, selisih, dan skor disembunyikan dari orang tua;
    // bisa ditampilkan lagi untuk pengujian.
    const tampilHitung = document.getElementById('tampilHitung');

    if (tampilHitung) {
        try {
            tampilHitung.checked =
                window.localStorage.getItem('nutriplanTampilHitung') === '1';
        } catch (error) {}

        const terapkan = () => {
            form.classList.toggle('tampil-hitung', tampilHitung.checked);
        };

        tampilHitung.addEventListener('change', () => {
            terapkan();

            try {
                window.localStorage.setItem(
                    'nutriplanTampilHitung',
                    tampilHitung.checked ? '1' : '0'
                );
            } catch (error) {}
        });

        terapkan();
    }

    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            const section = radio.closest('.meal-section');
            const tabHari = Array.from(tabs).find(
                tab => tab.dataset.day === radio.dataset.day
            );
            const lengkapSebelum = tabHari?.classList.contains('lengkap');

            if (section && radio.checked) {
                section.dataset.ganti = '';
                section.dataset.semua = '';
                section.classList?.remove('perlu-dipilih');
            }

            perbaruiTampilan();

            if (
                !isiOtomatisBerjalan &&
                radio.checked &&
                tabHari &&
                !lengkapSebelum &&
                tabHari.classList.contains('lengkap')
            ) {
                arahkanKeHariBerikutnya(radio.dataset.day);
            }

            const versi = (versiPilihan.get(radio.name) || 0) + 1;
            versiPilihan.set(radio.name, versi);

            pending++;
            perbaruiTombolLanjut();

            antrean = antrean
                .then(() => simpanOtomatis(radio, versi))
                .finally(() => {
                    pending--;

                    if (pending === 0 && gagal.size > 0 && status) {
                        status.textContent =
                            'Gagal menyimpan: ' + pesanErrorTerakhir;
                    }

                    if (pending === 0) {
                        tampilkanPesan(
                            gagal.size > 0
                                ? 'Gagal menyimpan: ' + pesanErrorTerakhir
                                : (pesanSelesai || '✓ Pilihan tersimpan'),
                            pesanSelesai ? 6000 : 2500
                        );
                        pesanSelesai = '';
                    }

                    perbaruiTombolLanjut();
                });
        });
    });

    perbaruiTampilan();
    perbaruiTombolLanjut();

    tombolLanjut?.addEventListener('click', () => {
        if (pending > 0 || gagal.size > 0) return;

        if (idPlan <= 0) {
            tampilkanPesan('Pilih minimal satu menu terlebih dahulu.');
            return;
        }

        if (!planningLengkap()) return;

        window.location.href =
            `/index.php?page=cara_mengolah&id=${encodeURIComponent(idPlan)}`;
    });
});