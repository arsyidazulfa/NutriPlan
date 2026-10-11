document.addEventListener('DOMContentLoaded', () => {
    const menus = document.querySelectorAll('.cara-menu');

    function aturMenu(menu, buka, scroll = false) {
        const trigger = menu.querySelector('.cara-menu-trigger');
        const detail = menu.querySelector('.cara-menu-detail');

        if (!trigger || !detail) return;

        if (buka) {
            menus.forEach(item => {
                if (item === menu) return;

                item.classList.remove('active');

                item.querySelector('.cara-menu-trigger')
                    ?.setAttribute('aria-expanded', 'false');

                const detailLain = item.querySelector('.cara-menu-detail');

                if (detailLain) {
                    detailLain.hidden = true;
                }
            });
        }

        menu.classList.toggle('active', buka);
        trigger.setAttribute(
            'aria-expanded',
            buka ? 'true' : 'false'
        );

        detail.hidden = !buka;

        if (buka && scroll) {
            setTimeout(() => {
                menu.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }, 80);
        }
    }

    menus.forEach(menu => {
        menu.querySelector('.cara-menu-trigger')
            ?.addEventListener('click', () => {
                aturMenu(
                    menu,
                    !menu.classList.contains('active')
                );
            });
    });

    const params = new URLSearchParams(window.location.search);
    const hari = params.get('hari');
    const slot = window.location.hash.substring(1);

    if (
        hari &&
        ['pagi', 'siang', 'malam'].includes(slot)
    ) {
        const target = document.getElementById(
            `menu-${hari}-${slot}`
        );

        if (target) {
            aturMenu(target, true, true);
        }

    } else if (hari) {
        document.getElementById(`hari-${hari}`)
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
    }
});