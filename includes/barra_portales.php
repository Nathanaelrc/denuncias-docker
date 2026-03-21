<?php
/**
 * Portal de Denuncias EPCO - Barra Superior de Navegación entre Portales
 * Permite cambiar entre portales y volver al Landing.
 */
$_landingUrl     = getenv('LANDING_URL') ?: 'http://localhost:8090';
$_otherPortalUrl = getenv('APP_URL_GENERAL') ?: 'http://localhost:8093';
?>
<style>
.barra-portales{position:fixed;top:0;left:0;right:0;z-index:1040;height:36px;background:#0b1929;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-size:.8rem;border-bottom:1px solid rgba(255,255,255,.08)}
.barra-portales a{color:rgba(255,255,255,.7);text-decoration:none;transition:color .2s}
.barra-portales a:hover{color:#fff}
.barra-portales .bp-actual{color:#93c5fd;font-weight:600;font-size:.75rem;letter-spacing:.3px}
.barra-portales .bp-sep{color:rgba(255,255,255,.2);margin:0 .5rem}
.navbar.fixed-top{top:36px !important}
</style>
<div class="barra-portales">
    <div class="d-flex align-items-center">
        <a href="<?= htmlspecialchars($_landingUrl) ?>" title="Volver al portal principal">
            <i class="bi bi-arrow-left me-1"></i>Portal Principal
        </a>
        <span class="bp-sep">|</span>
        <span class="bp-actual"><i class="bi bi-shield-check me-1"></i>Ley Karin</span>
        <span class="bp-sep">|</span>
        <a href="<?= htmlspecialchars($_otherPortalUrl) ?>">
            <i class="bi bi-globe2 me-1"></i>Portal Ciudadano
        </a>
    </div>
    <div class="d-none d-md-flex align-items-center text-white-50" style="font-size:.72rem;">
        <i class="bi bi-building me-1"></i>Empresa Portuaria Coquimbo
    </div>
</div>
