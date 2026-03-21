<?php
/**
 * Portal Denuncias Ciudadanas - Navbar Única
 */
if (!defined('GENERALES_APP')) die('Acceso no permitido');

$_navUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$_navPage = rtrim($_navUri, '/') ?: '/';
$_landingUrl = getenv('LANDING_URL') ?: 'http://localhost:8090';
$_karinUrl   = getenv('APP_URL_KARIN') ?: 'http://localhost:8091';
?>
<style>
.epco-nav{position:fixed;top:0;left:0;right:0;z-index:1040;height:62px;display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;background:linear-gradient(135deg,#0a1628 0%,#112a42 100%);border-bottom:1px solid rgba(255,255,255,.08);gap:1rem}
.epco-nav .brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:#fff;flex-shrink:0}
.epco-nav .brand img{height:34px;width:auto;object-fit:contain}
.epco-nav .brand-text{line-height:1.15}
.epco-nav .brand-title{font-weight:700;font-size:.92rem}
.epco-nav .brand-sub{font-size:.68rem;opacity:.5}
.epco-nav .nav-center{display:flex;align-items:center;gap:6px;margin:0 auto}
.epco-nav .portal-pill{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:20px;font-size:.78rem;text-decoration:none;transition:all .2s;border:1px solid transparent}
.epco-nav .portal-pill.active{background:rgba(147,197,253,.15);color:#93c5fd;border-color:rgba(147,197,253,.25);font-weight:600}
.epco-nav .portal-pill:not(.active){color:rgba(255,255,255,.5)}
.epco-nav .portal-pill:not(.active):hover{color:rgba(255,255,255,.8);background:rgba(255,255,255,.06)}
.epco-nav .sep-dot{color:rgba(255,255,255,.15);font-size:.7rem;margin:0 2px}
.epco-nav .nav-link-n{color:rgba(255,255,255,.7);text-decoration:none;padding:6px 14px;border-radius:8px;font-size:.85rem;transition:all .2s;display:flex;align-items:center;gap:5px}
.epco-nav .nav-link-n:hover{color:#fff;background:rgba(255,255,255,.08)}
.epco-nav .nav-link-n.active{color:#fff;background:rgba(255,255,255,.12);font-weight:600}
.epco-nav .btn-acceso{background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.18);padding:6px 16px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:6px;flex-shrink:0}
.epco-nav .btn-acceso:hover{background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:#fff}
.epco-nav-toggle{display:none;background:none;border:none;color:#fff;font-size:1.4rem;cursor:pointer;padding:4px 8px}
.epco-nav-collapse{display:flex;align-items:center;gap:6px;flex:1;justify-content:center}
.epco-nav-right{display:flex;align-items:center;gap:8px;flex-shrink:0}
@media(max-width:991px){
    .epco-nav{flex-wrap:wrap;height:auto;min-height:62px;padding:.6rem 1rem}
    .epco-nav-toggle{display:block}
    .epco-nav-collapse{display:none;width:100%;flex-direction:column;padding:.75rem 0 .25rem;gap:4px;order:3}
    .epco-nav-collapse.show{display:flex}
    .epco-nav .nav-link-n,.epco-nav .portal-pill{width:100%;justify-content:center}
    .epco-nav-right{order:2}
    .epco-nav .sep-dot{display:none}
}
</style>
<nav class="epco-nav">
    <a class="brand" href="/">
        <img src="/img/Logo01.png" alt="EPCO" onerror="this.style.display='none'">
        <div class="brand-text">
            <div class="brand-title">Empresa Portuaria Coquimbo</div>
            <div class="brand-sub">Portal Ciudadano de Denuncias</div>
        </div>
    </a>
    <button class="epco-nav-toggle" onclick="document.getElementById('navCollapseG').classList.toggle('show')">
        <i class="bi bi-list"></i>
    </button>
    <div class="epco-nav-collapse" id="navCollapseG">
        <a class="nav-link-n" href="<?= htmlspecialchars($_landingUrl) ?>">
            <i class="bi bi-arrow-left"></i>Inicio
        </a>
        <span class="sep-dot">|</span>
        <a class="portal-pill" href="<?= htmlspecialchars($_karinUrl) ?>"><i class="bi bi-shield-check"></i>Ley Karin</a>
        <span class="portal-pill active"><i class="bi bi-globe2"></i>Ciudadano</span>
        <span class="sep-dot">|</span>
        <a class="nav-link-n <?= str_starts_with($_navPage, '/nueva_denuncia') ? 'active' : '' ?>" href="/nueva_denuncia">
            <i class="bi bi-pencil-square"></i>Realizar Denuncia
        </a>
        <a class="nav-link-n <?= str_starts_with($_navPage, '/seguimiento') ? 'active' : '' ?>" href="/seguimiento">
            <i class="bi bi-search"></i>Seguimiento
        </a>
    </div>
    <div class="epco-nav-right">
        <a href="/iniciar_sesion" class="btn-acceso"><i class="bi bi-lock"></i>Acceso Delegados</a>
    </div>
</nav>
