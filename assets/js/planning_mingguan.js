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

            if (status) {
                status.textContent = '✓ Tersimpan otomatis';
            }

        } catch (error) {
            gagal.add(radio.name);

            if (
                radio.checked &&
                versiPilihan.get(radio.name) === versi
            ) {
                radio.checked = false;
            }

            perbaruiTampilan();

            if (status) {
                status.textContent = 'Gagal menyimpan otomatis';
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

                alert(
                    `Pilih menu ${labelSlot[slot]} untuk hari ${namaHari} terlebih dahulu.`
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

    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            perbaruiTampilan();

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
                            'Ada pilihan gagal disimpan. Silakan pilih ulang.';
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
            alert('Pilih minimal satu menu terlebih dahulu.');
            return;
        }

        if (!planningLengkap()) return;

        window.location.href =
            `/index.php?page=cara_mengolah&id=${encodeURIComponent(idPlan)}`;
    });
});