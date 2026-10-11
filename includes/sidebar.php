<?php

$current = $_GET['page'] ?? '';

?>

<button
type="button"
class="sidebar-toggle"
id="sidebarToggle"
aria-label="Buka menu"
>
☰
</button>

<aside
class="sidebar-drawer"
id="sidebarDrawer"
>

<div class="sidebar-drawer-header">

<a
href="/index.php?page=profil_ibu"
class="sidebar-profile-link"
>
<div>
<span class="sidebar-profile-label">
Profil
</span>

<strong class="sidebar-profile-name">
<?= htmlspecialchars(
$_SESSION['nama'] ?? 'Pengguna'
); ?>
</strong>
</div>
</a>

<button
type="button"
class="sidebar-close"
id="sidebarClose"
aria-label="Tutup menu"
>
×
</button>

</div>

<nav class="sidebar-nav">

<a
href="/index.php?page=profil_ibu"
class="sidebar-nav-item <?= $current === 'profil_ibu' ? 'active' : ''; ?>"
>
<span class="nav-icon">👤</span>
<span>Profil Ibu</span>
</a>

<a
href="/index.php?page=data_anak"
class="sidebar-nav-item <?= $current === 'data_anak' ? 'active' : ''; ?>"
>
<span class="nav-icon">👶</span>
<span>Data Anak</span>
</a>

<a
href="/index.php?page=sumber_protein"
class="sidebar-nav-item <?= $current === 'sumber_protein' ? 'active' : ''; ?>"
>
<span class="nav-icon">🌱</span>
<span>Sumber Protein</span>
</a>

<a
href="/index.php?page=camilan_sehat"
class="sidebar-nav-item <?= $current === 'camilan_sehat' ? 'active' : ''; ?>"
>
<span class="nav-icon">🍎</span>
<span>Camilan Sehat</span>
</a>

<a
href="/index.php?page=planning_mingguan"
class="sidebar-nav-item <?= $current === 'planning_mingguan' ? 'active' : ''; ?>"
>
<span class="nav-icon">📅</span>
<span>Planning Mingguan</span>
</a>

<a
href="/index.php?page=cara_mengolah"
class="sidebar-nav-item <?= $current === 'cara_mengolah' ? 'active' : ''; ?>"
>
<span class="nav-icon">🍳</span>
<span>Cara Mengolah</span>
</a>

</nav>

<a
href="/logout.php"
class="sidebar-logout"
>
<span>⎋</span>
<span>Keluar</span>
</a>

</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {

const toggle = document.getElementById('sidebarToggle');
const drawer = document.getElementById('sidebarDrawer');
const close = document.getElementById('sidebarClose');

function bukaSidebar() {
drawer.classList.add('open');
document.body.classList.add('sidebar-open');
}

function tutupSidebar() {
drawer.classList.remove('open');
document.body.classList.remove('sidebar-open');
}

toggle.addEventListener('click', function () {

if (drawer.classList.contains('open')) {
tutupSidebar();
} else {
bukaSidebar();
}

});

close.addEventListener('click', tutupSidebar);

document.addEventListener('keydown', function (event) {

if (event.key === 'Escape') {
tutupSidebar();
}

});

});
</script>