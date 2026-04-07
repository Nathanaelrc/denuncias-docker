<?php
/**
 * Versiones de dependencias CDN - Portal Ciudadano
 * =================================================
 * Para actualizar una librería, cambia solo el número de versión aquí.
 * El cambio se aplicará automáticamente en todo el portal.
 *
 * Verificar últimas versiones en:
 *   Bootstrap:       https://github.com/twbs/bootstrap/releases
 *   Bootstrap Icons: https://github.com/twbs/icons/releases
 *   GSAP:            https://github.com/greensock/GSAP/releases
 *   Chart.js:        https://github.com/chartjs/Chart.js/releases
 *   jsPDF:           https://github.com/parallax/jsPDF/releases
 *   jsPDF-AutoTable: https://github.com/simonbengtsson/jsPDF-AutoTable/releases
 */

// --- Números de versión ---
define('VER_BOOTSTRAP',       '5.3.8');
define('VER_BOOTSTRAP_ICONS', '1.13.1');
define('VER_GSAP',            '3.12.5');
define('VER_CHARTJS',         '4.5.1');
define('VER_JSPDF',           '2.5.2');
define('VER_JSPDF_AUTOTABLE', '3.8.4');

// --- URLs de CDN (generadas automáticamente) ---
define('CDN_BS_CSS',
    'https://cdn.jsdelivr.net/npm/bootstrap@' . VER_BOOTSTRAP . '/dist/css/bootstrap.min.css');
define('CDN_BS_JS',
    'https://cdn.jsdelivr.net/npm/bootstrap@' . VER_BOOTSTRAP . '/dist/js/bootstrap.bundle.min.js');
define('CDN_BS_ICONS',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@' . VER_BOOTSTRAP_ICONS . '/font/bootstrap-icons.css');
define('CDN_GSAP',
    'https://cdn.jsdelivr.net/npm/gsap@' . VER_GSAP . '/dist/gsap.min.js');
define('CDN_GSAP_ST',
    'https://cdn.jsdelivr.net/npm/gsap@' . VER_GSAP . '/dist/ScrollTrigger.min.js');
define('CDN_CHARTJS',
    'https://cdn.jsdelivr.net/npm/chart.js@' . VER_CHARTJS . '/dist/chart.umd.min.js');
define('CDN_JSPDF',
    'https://cdn.jsdelivr.net/npm/jspdf@' . VER_JSPDF . '/dist/jspdf.umd.min.js');
define('CDN_JSPDF_AUTO',
    'https://cdn.jsdelivr.net/npm/jspdf-autotable@' . VER_JSPDF_AUTOTABLE . '/dist/jspdf.plugin.autotable.min.js');
