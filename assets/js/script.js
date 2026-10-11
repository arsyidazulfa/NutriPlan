/* NutriPlan — script.js */

function previewImg(input, previewId) {
    var preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

document.addEventListener('DOMContentLoaded', function () {

    // ---- Toggle password visibility ----
    var EYE_OPEN = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_CLOSED = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    document.querySelectorAll('.toggle-pass').forEach(function (btn) {
        btn.innerHTML = EYE_OPEN;
        btn.addEventListener('click', function () {
            var input = this.closest('.input-wrap').querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = EYE_CLOSED;
            } else {
                input.type = 'password';
                this.innerHTML = EYE_OPEN;
            }
        });
    });

    // ---- Menu mode dropdown toggle ----
    var modeDropdown = document.querySelector('.menu-mode-dropdown');
    if (modeDropdown) {
        var modeBtn = modeDropdown.querySelector('.menu-mode-btn');
        modeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            modeDropdown.classList.toggle('open');
        });
    }

    // ---- Navbar user dropdown toggle ----
    var navUser = document.querySelector('.navbar-user');
    if (navUser) {
        navUser.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('open');
        });
    }

    // ---- Close dropdowns on outside click ----
    document.addEventListener('click', function () {
        document.querySelectorAll('.menu-mode-dropdown, .navbar-user').forEach(function (el) {
            el.classList.remove('open');
        });
    });

    // ---- Highlight active sidebar nav item ----
    var currentPage = new URLSearchParams(window.location.search).get('page') || 'beranda';
    document.querySelectorAll('.sidebar-nav-item[data-page]').forEach(function (item) {
        if (item.dataset.page === currentPage) {
            item.classList.add('active');
        }
    });

    // ---- Anggaran option highlight ----
    document.querySelectorAll('.anggaran-option').forEach(function (opt) {
        var radio = opt.querySelector('input[type="radio"]');
        if (radio && radio.checked) opt.classList.add('selected');
        opt.addEventListener('click', function () {
            document.querySelectorAll('.anggaran-option').forEach(function (o) {
                o.classList.remove('selected');
            });
            this.classList.add('selected');
            var r = this.querySelector('input[type="radio"]');
            if (r) r.checked = true;
        });
    });

    // ---- Admin image preview on file select ----
    var fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            var previewId = this.dataset.preview;
            var preview = document.getElementById(previewId);
            if (!preview) return;
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // ---- Step list (Cara Mengolah) — dynamic add/remove with auto numbering ----
    document.querySelectorAll('[data-step-list]').forEach(function (list) {
        function renumber() {
            list.querySelectorAll('.step-num').forEach(function (el, i) {
                el.textContent = (i + 1) + '.';
            });
        }
        renumber();

        list.addEventListener('click', function (e) {
            if (e.target.classList.contains('step-remove')) {
                var items = list.querySelectorAll('.step-item');
                if (items.length > 1) {
                    e.target.closest('.step-item').remove();
                    renumber();
                } else {
                    var input = items[0].querySelector('input');
                    if (input) input.value = '';
                }
            }
        });

        var addBtn = list.parentElement.querySelector('[data-step-add]');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var item = document.createElement('div');
                item.className = 'step-item';
                item.innerHTML =
                    '<span class="step-num"></span>' +
                    '<input type="text" name="cara_masak[]" placeholder="Tulis langkah...">' +
                    '<button type="button" class="step-remove" aria-label="Hapus langkah">×</button>';
                list.appendChild(item);
                renumber();
                item.querySelector('input').focus();
            });
        }
    });

    // ---- Flash message auto-dismiss after 4s ----
    var flashes = document.querySelectorAll('.flash');
    flashes.forEach(function (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity .5s';
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 500);
        }, 4000);
    });

});
