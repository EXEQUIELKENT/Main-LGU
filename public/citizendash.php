<?php
date_default_timezone_set('Asia/Manila');
$serverTimestamp = time();

if ($_SERVER['HTTP_HOST'] === 'localhost') {
    $BASE_URL      = '/Main LGU/public/';
    $OFFICIAL_LOGO = '/Main LGU/public/logocityhall.png';
} else {
    $BASE_URL      = '/public/';
    $OFFICIAL_LOGO = '/public/logocityhall.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#050a19" id="metaThemeColor">
    <link rel="icon" href="<?= $OFFICIAL_LOGO ?>" type="image/png">
    <title>InfraGovServices – Infrastructure &amp; Utilities</title>

    <!-- Icons & Lightbox CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/SimpleLightbox/2.1.0/simpleLightbox.min.css">

    <!-- Main stylesheet: Bootstrap Creative + citizendash custom styles -->
    <link rel="stylesheet" href="<?= $BASE_URL ?>styles.css">

    <!-- Background image (PHP-injected path, must stay inline) -->
    <style>
        body.dash-page { background-image: url('<?= $BASE_URL ?>assets/img/memcir.jpg'); }
    </style>

    <!-- CRITICAL: block dark-mode / lang flash before first paint -->
    <script>
    (function() {
        try {
            var t = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light';
            if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        } catch(e) {}
        if ((localStorage.getItem('lang') || 'en') === 'tl') {
            document.documentElement.style.cssText = 'visibility:hidden !important;';
        }
    })();
    </script>

    <!-- ★ Critical inline overrides — always wins over linked CSS -->
    <style>
        /* Mobile: center content, full screen height */
        @media screen and (max-width:1024px) {
            .dash-hero {
                justify-content: center !important;
                min-height: 100svh !important;
                min-height: 100dvh !important;
                min-height: 100vh !important;
            }
            .db-hero-content {
                padding-top:    76px !important;
                padding-bottom: 40px !important;
                padding-left:   20px !important;
                padding-right:  20px !important;
            }
        }
        @media screen and (max-width:640px) {
            .dash-hero {
                justify-content: center !important;
                min-height: 100svh !important;
                min-height: 100dvh !important;
                min-height: 100vh !important;
            }
            .db-hero-content {
                padding-top:    70px !important;
                padding-bottom: 36px !important;
                padding-left:   18px !important;
                padding-right:  18px !important;
                text-align:     center !important;
                align-items:    center !important;
                max-width:      100% !important;
            }
            .db-hero-eyebrow,
            .db-hero-h1,
            .db-hero-sub {
                text-align:   center !important;
                margin-left:  auto !important;
                margin-right: auto !important;
            }
            .db-hero-eyebrow::before { display: none !important; }
            .db-hero-pills { justify-content: center !important; }
            .db-hero-cta {
                justify-content: center !important;
                flex-direction:  row !important;
                align-items:     center !important;
                flex-wrap:       nowrap !important;
                gap:             10px !important;
                width:           100% !important;
            }
            .db-btn-solid,
            .db-btn-ghost {
                flex:             1 1 0 !important;
                min-width:        0 !important;
                max-width:        200px !important;
                justify-content:  center !important;
                padding:          12px 16px !important;
                font-size:        .82rem !important;
                white-space:      nowrap !important;
            }
        }
        /* Lightbox: hide arrows on touch devices */
        @media (hover:none),(pointer:coarse) {
            .sl-wrapper .sl-navigation,
            .sl-wrapper button.sl-prev,
            .sl-wrapper button.sl-next,
            .sl-prev, .sl-next {
                display:         none !important;
                visibility:      hidden !important;
                pointer-events:  none !important;
            }
        }
    </style>
</head>
<body class="dash-page" id="page-top">

<!-- ═══════════════════════════════════════════════════
     DESKTOP NAVIGATION — Floating pill nav
════════════════════════════════════════════════════ -->
<header class="db-nav" id="dashMainNav">
    <div class="db-nav-inner">
        <!-- Logo -->
        <a href="<?= $BASE_URL ?>citizendash.php" class="db-nav-logo">
            <img src="<?= $OFFICIAL_LOGO ?>" alt="LGU Logo">
            <span>
                <strong data-i18n="site_title_short">InfraGovServices</strong>
                <em>Quezon City</em>
            </span>
        </a>

        <!-- Center links with pill indicator -->
        <nav class="db-nav-pills-wrap">
            <a href="#"            class="db-nav-link active" data-i18n="nav_home">Home</a>
            <a href="#dashAbout"   class="db-nav-link"        data-i18n="nav_about">About</a>
            <a href="#dashServices"class="db-nav-link"        data-i18n="nav_services">Services</a>
            <a href="#dashGallery" class="db-nav-link"        data-i18n="nav_gallery">Facilities</a>
            <a href="privacy.php"  class="db-nav-link"        data-i18n="nav_privacy">Privacy</a>
        </nav>

        <!-- Right actions -->
        <div class="db-nav-actions">
            <div class="db-nav-clock" id="dashDesktopClock"></div>

            <!-- Language toggle pill -->
            <button class="db-lang-pill" id="dashTranslateBtn" title="Translate">
                <span class="db-lang-globe">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                </span>
                <span class="db-lang-code" id="dashLangLabel">EN</span>
                <span class="db-lang-divider"></span>
                <span class="db-lang-flag" id="dashLangFlag">🇺🇸</span>
            </button>

            <!-- Dark mode toggle switch -->
            <button class="db-theme-toggle" id="dashDarkBtn" title="Toggle Dark Mode" aria-label="Toggle dark mode">
                <span class="db-theme-track">
                    <span class="db-theme-thumb">
                        <span class="db-dark-icon">🌙</span>
                        <span class="db-light-icon">☀️</span>
                    </span>
                </span>
            </button>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════
     MOBILE SIDEBAR — card drawer
════════════════════════════════════════════════════ -->
<nav class="db-sidebar" id="dashSidebar">
    <div class="db-sidebar-header">
        <a href="<?= $BASE_URL ?>citizendash.php" class="db-sidebar-brand">
            <img src="<?= $OFFICIAL_LOGO ?>" alt="LGU Logo">
            <div>
                <strong data-i18n="site_title_short">InfraGovServices</strong>
                <small>Quezon City LGU</small>
            </div>
        </a>
        <button class="db-sidebar-close" id="dashSidebarClose">✕</button>
    </div>
    <div class="db-sidebar-divider"></div>
    <ul class="db-sidebar-links">
        <li><a href="#" class="active"><i class="fas fa-home"></i><span data-i18n="nav_home">Home</span></a></li>
        <li><a href="#dashAbout">   <i class="fas fa-city"></i><span data-i18n="nav_about">About</span></a></li>
        <li><a href="#dashServices"><i class="fas fa-layer-group"></i><span data-i18n="nav_services">Services</span></a></li>
        <li><a href="#dashGallery"> <i class="fas fa-landmark"></i><span data-i18n="nav_gallery">Facilities</span></a></li>
        <li><a href="privacy.php">  <i class="fas fa-file-shield"></i><span data-i18n="nav_privacy">Privacy Policy</span></a></li>
    </ul>

</nav>
<div class="db-sidebar-overlay" id="dashSidebarOverlay"></div>

<!-- ═══════════════════════════════════════════════════
     MOBILE TOP NAV — compact bar
════════════════════════════════════════════════════ -->
<div class="db-mobile-bar" id="dashMobileTopNav">
    <button class="db-mobile-menu-btn" id="dashMobileToggle">
        <span></span><span></span><span></span>
    </button>
    <a href="<?= $BASE_URL ?>citizendash.php" class="db-mobile-logo">
        <img src="<?= $OFFICIAL_LOGO ?>" alt="LGU Logo">
        <span data-i18n="site_title_short">InfraGovServices</span>
    </a>
    <div class="db-mobile-bar-actions">
        <div class="db-mobile-clock" id="dashMobileClock"></div>
        <button class="db-mobile-lang-btn" id="dashMobileTranslateBtn">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span id="dashMobileLangLabel">EN</span>
        </button>
        <button class="db-mobile-dark-btn" id="dashMobileDarkBtn">
            <span class="db-dark-icon">🌙</span>
            <span class="db-light-icon" style="display:none">☀️</span>
        </button>
    </div>
</div>

<!-- Language toast badge -->
<div class="dash-lang-badge" id="dashLangBadge">
    <span id="dashBadgeFlag">🇺🇸</span>
    <span id="dashBadgeText">Switched to English</span>
</div>

<!-- ═══════════════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════════════════ -->
<main class="dash-main" style="margin-top:0">

    <!-- ── HERO — Atelier full-bleed style ──────────── -->
    <section class="dash-hero" id="dashHero">
        <div class="db-hero-overlay"></div>

        <div class="db-hero-content">
            <div class="db-hero-eyebrow" data-i18n="hero_badge">Quezon City · Local Government Unit</div>

            <h1 class="db-hero-h1" data-i18n-html="hero_h1">Welcome to InfraGovServices!</h1>

            <p class="db-hero-sub" data-i18n="hero_tagline">Your unified gateway to all Quezon City infrastructure and utilities departments — transparent, accessible, and built for every resident.</p>

            <!-- Department category pills -->
            <div class="db-hero-pills">
                <a href="https://ipms.infragovservices.com/" class="db-pill active" target="_blank" rel="noopener">
                    <span data-i18n="pill_ipms">Infrastructure</span> <span class="db-pill-count">IPMS</span>
                </a>
                <a href="https://UMAN.infraservices.com/" class="db-pill" target="_blank" rel="noopener">
                    <span data-i18n="pill_uman">Utilities</span> <span class="db-pill-count">UMAN</span>
                </a>
                <a href="https://cprf.infragovservices.com/" class="db-pill" target="_blank" rel="noopener">
                    <span data-i18n="pill_cprf">Facilities</span> <span class="db-pill-count">CPRF</span>
                </a>
                <a href="https://cimm.infragovservices.com/" class="db-pill" target="_blank" rel="noopener">
                    <span data-i18n="pill_cimm">Maintenance</span> <span class="db-pill-count">CIMM</span>
                </a>
                <a href="https://energy.infragovservices.com/" class="db-pill" target="_blank" rel="noopener">
                    <span data-i18n="pill_energy">Energy</span> <span class="db-pill-count">ECM</span>
                </a>
                <a href="https://rtm.infragovservices.com/" class="db-pill" target="_blank" rel="noopener">
                    <span data-i18n="pill_rtm">Roads</span> <span class="db-pill-count">RTM</span>
                </a>
                <a href="https://UPaD.infragovservices.com/" class="db-pill" target="_blank" rel="noopener">
                    <span>Urban Planning</span> <span class="db-pill-count">UPaD</span>
                </a>
            </div>

            <div class="db-hero-cta">
                <a href="#dashServices" class="db-btn-solid" data-i18n="hero_cta_explore">
                    <i class="fas fa-th-large"></i> Explore Departments
                </a>
                <button type="button" class="db-btn-ghost dash-guide-btn" id="dashGuideBtn" data-i18n="cta_guide">
                    🗺️ Guide
                </button>
            </div>
        </div>

        <!-- Scrolling ticker -->
        <div class="db-ticker-bar">
            <!-- Left fade mask -->
            <div class="db-ticker-fade db-ticker-fade--left"></div>
            <!-- Right fade mask -->
            <div class="db-ticker-fade db-ticker-fade--right"></div>

            <div class="db-ticker-track">
                <!-- Set 1 -->
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--blue"><i class="fas fa-hard-hat"></i></span>
                    <span>Infrastructure Project Management</span>
                    <span class="db-ticker-code">(IPMS)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--teal"><i class="fas fa-bolt"></i></span>
                    <span>Utilities Billing &amp; Management</span>
                    <span class="db-ticker-code">(UMAN)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--amber"><i class="fas fa-road"></i></span>
                    <span>Road &amp; Transportation</span>
                    <span class="db-ticker-code">(RTM)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--purple"><i class="fas fa-landmark"></i></span>
                    <span>Public Facilities Reservation</span>
                    <span class="db-ticker-code">(CPRF)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--green"><i class="fas fa-tools"></i></span>
                    <span>Community Infrastructure Maintenance</span>
                    <span class="db-ticker-code">(CIMM)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--coral"><i class="fas fa-leaf"></i></span>
                    <span>Energy Efficiency &amp; Conservation</span>
                    <span class="db-ticker-code">(ECM)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--amber"><i class="fas fa-map"></i></span>
                    <span>Urban Planning and Development</span>
                    <span class="db-ticker-code">(UPaD)</span>
                </div>
                <!-- Set 2 (duplicate for seamless loop) -->
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--blue"><i class="fas fa-hard-hat"></i></span>
                    <span>Infrastructure Project Management</span>
                    <span class="db-ticker-code">(IPMS)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--teal"><i class="fas fa-bolt"></i></span>
                    <span>Utilities Billing &amp; Management</span>
                    <span class="db-ticker-code">(UMAN)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--amber"><i class="fas fa-road"></i></span>
                    <span>Road &amp; Transportation</span>
                    <span class="db-ticker-code">(RTM)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--purple"><i class="fas fa-landmark"></i></span>
                    <span>Public Facilities Reservation</span>
                    <span class="db-ticker-code">(CPRF)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--green"><i class="fas fa-tools"></i></span>
                    <span>Community Infrastructure Maintenance</span>
                    <span class="db-ticker-code">(CIMM)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--coral"><i class="fas fa-leaf"></i></span>
                    <span>Energy Efficiency &amp; Conservation</span>
                    <span class="db-ticker-code">(ECM)</span>
                </div>
                <div class="db-ticker-item">
                    <span class="db-ticker-badge db-ticker-badge--amber"><i class="fas fa-map"></i></span>
                    <span>Urban Planning and Development</span>
                    <span class="db-ticker-code">(UPaD)</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ── STATS BAR — redesigned metrics ───────────── -->
    <section class="db-metrics-section dash-aos" id="dashTrust">
        <div class="db-metrics-inner">
            <div class="db-metric-card dash-aos dash-delay-1">
                <div class="db-metric-icon"><i class="fas fa-building"></i></div>
                <div class="db-metric-body">
                    <div class="db-metric-num">7</div>
                    <div class="db-metric-label" data-i18n="stat_depts">Active Departments</div>
                    <div class="db-metric-sub" data-i18n="stat_depts_sub">City-wide infrastructure coverage</div>
                </div>
                <div class="db-metric-ring"></div>
            </div>
            <div class="db-metric-card dash-aos dash-delay-2">
                <div class="db-metric-icon" style="background:linear-gradient(135deg,#065f46,#10b981)"><i class="fas fa-users"></i></div>
                <div class="db-metric-body">
                    <div class="db-metric-num">3M+</div>
                    <div class="db-metric-label" data-i18n="stat_residents">Residents Served</div>
                    <div class="db-metric-sub" data-i18n="stat_residents_sub">Quezon City population</div>
                </div>
                <div class="db-metric-ring" style="border-color:rgba(16,185,129,.25)"></div>
            </div>
            <div class="db-metric-card dash-aos dash-delay-3">
                <div class="db-metric-icon" style="background:linear-gradient(135deg,#78350f,#f59e0b)"><i class="fas fa-check-double"></i></div>
                <div class="db-metric-body">
                    <div class="db-metric-num">500+</div>
                    <div class="db-metric-label" data-i18n="stat_projects">Projects Completed</div>
                    <div class="db-metric-sub" data-i18n="stat_projects_sub">Public infrastructure works</div>
                </div>
                <div class="db-metric-ring" style="border-color:rgba(245,158,11,.25)"></div>
            </div>
            <div class="db-metric-card dash-aos dash-delay-4">
                <div class="db-metric-icon" style="background:linear-gradient(135deg,#4c1d95,#8b5cf6)"><i class="fas fa-clock"></i></div>
                <div class="db-metric-body">
                    <div class="db-metric-num">24/7</div>
                    <div class="db-metric-label" data-i18n="stat_access">Online Access</div>
                    <div class="db-metric-sub" data-i18n="stat_access_sub">Anytime, anywhere</div>
                </div>
                <div class="db-metric-ring" style="border-color:rgba(139,92,246,.25)"></div>
            </div>
        </div>
    </section>

    <!-- ── ABOUT ─────────────────────────────────────── -->
    <section class="dash-about-section dash-aos" id="dashAbout">
        <div class="dash-about-card">
            <div class="dash-about-text dash-aos dash-delay-1">
                <div class="db-about-eyebrow"><span></span><span data-i18n="about_eyebrow">About Us</span></div>
                <h2 class="db-about-heading" data-i18n-html="about_heading">About <span>Quezon City</span><br>Infrastructure &amp; Utilities</h2>
                <p data-i18n="about_p1">The Quezon City Infrastructure and Utilities Service is dedicated to managing, maintaining, and improving essential public facilities to ensure safe, reliable, and efficient community services.</p>
                <p data-i18n="about_p2">Our goal is to streamline maintenance operations, enhance infrastructure responsiveness, and provide residents with transparent access to service updates and support across all departments.</p>
                <div class="db-bento-grid" id="dashAboutFeatures">
                    <a href="#dashServices" class="db-bento-card db-bento-blue">
                        <div class="db-bento-glow"></div>
                        <div class="db-bento-icon-wrap">
                            <div class="db-bento-ring"></div>
                            <i class="fas fa-hard-hat db-bento-icon"></i>
                        </div>
                        <div class="db-bento-body">
                            <span class="db-bento-title" data-i18n="hl1_title">Project Management</span>
                            <span class="db-bento-desc"  data-i18n="hl1_desc">City-wide construction oversight</span>
                        </div>
                        <div class="db-bento-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                        <div class="db-bento-shimmer"></div>
                    </a>
                    <a href="#dashServices" class="db-bento-card db-bento-green">
                        <div class="db-bento-glow"></div>
                        <div class="db-bento-icon-wrap">
                            <div class="db-bento-ring"></div>
                            <i class="fas fa-bolt db-bento-icon"></i>
                        </div>
                        <div class="db-bento-body">
                            <span class="db-bento-title" data-i18n="hl2_title">Utilities Management</span>
                            <span class="db-bento-desc"  data-i18n="hl2_desc">Water, power &amp; waste services</span>
                        </div>
                        <div class="db-bento-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                        <div class="db-bento-shimmer"></div>
                    </a>
                    <a href="#dashServices" class="db-bento-card db-bento-orange">
                        <div class="db-bento-glow"></div>
                        <div class="db-bento-icon-wrap">
                            <div class="db-bento-ring"></div>
                            <i class="fas fa-tools db-bento-icon"></i>
                        </div>
                        <div class="db-bento-body">
                            <span class="db-bento-title" data-i18n="hl3_title">Maintenance</span>
                            <span class="db-bento-desc"  data-i18n="hl3_desc">Repairs and upkeep of public assets</span>
                        </div>
                        <div class="db-bento-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                        <div class="db-bento-shimmer"></div>
                    </a>
                    <a href="#dashServices" class="db-bento-card db-bento-teal">
                        <div class="db-bento-glow"></div>
                        <div class="db-bento-icon-wrap">
                            <div class="db-bento-ring"></div>
                            <i class="fas fa-leaf db-bento-icon"></i>
                        </div>
                        <div class="db-bento-body">
                            <span class="db-bento-title" data-i18n="hl4_title">Sustainability</span>
                            <span class="db-bento-desc"  data-i18n="hl4_desc">Energy-efficient city planning</span>
                        </div>
                        <div class="db-bento-arrow"><i class="fas fa-arrow-up-right-from-square"></i></div>
                        <div class="db-bento-shimmer"></div>
                    </a>
                </div>
                <a href="#dashServices" class="db-about-btn">
                    <span data-i18n="about_cta">View All Departments</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="dash-about-img-wrap dash-aos dash-delay-2">
                <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/1.jpg"
                     alt="Quezon City Hall"
                     onerror="this.parentElement.style.background='#0a1628'">
                <div class="dash-about-img-overlay">
                    <h3 data-i18n="about_overlay_title">Quezon City Hall</h3>
                    <p  data-i18n="about_overlay_sub">Excellence in Public Service</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── SERVICES — Magazine-card redesign ───────── -->
    <section class="db-svc-v3 dash-aos" id="dashServices">

        <!-- Header -->
        <div class="db-svc-v3-header db-section-header" id="dashServicesHeader">
            <div class="db-svc-v3-eyebrow"><span></span><span data-i18n="services_eyebrow">Departments</span></div>
            <h2 class="db-svc-v3-title" data-i18n="services_title">Community Departments &amp; Services</h2>
            <p  class="db-svc-v3-sub"   data-i18n="services_subtitle">Select a department below to access its dedicated portal and services</p>
        </div>

        <!-- Card grid -->
        <div class="db-svc-v3-grid">

            <!-- Card 1 — blue -->
            <div class="db-svc3-card db-svc3-blue dash-aos dash-delay-1" id="dashSvcCard1">
                <div class="db-svc3-bg-num">01</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-hard-hat"></i></div>
                    <span class="db-svc3-tag">IPMS</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title" data-i18n="svc1_title">Infrastructure Project Management</h3>
                    <p  class="db-svc3-desc"  data-i18n="svc1_desc">Planning and oversight of city-wide construction and development projects.</p>
                </div>
                <a class="db-svc3-btn" href="https://ipms.infragovservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

            <!-- Card 2 — green -->
            <div class="db-svc3-card db-svc3-green dash-aos dash-delay-2" id="dashSvcCard2">
                <div class="db-svc3-bg-num">02</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-file-invoice-dollar"></i></div>
                    <span class="db-svc3-tag">UMAN</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title" data-i18n="svc2_title">Utilities Billing and Management</h3>
                    <p  class="db-svc3-desc"  data-i18n="svc2_desc">Handling water, electricity, and waste disposal accounts and payments.</p>
                </div>
                <a class="db-svc3-btn" href="https://UMAN.infraservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

            <!-- Card 3 — orange -->
            <div class="db-svc3-card db-svc3-orange dash-aos dash-delay-3" id="dashSvcCard3">
                <div class="db-svc3-bg-num">03</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-road"></i></div>
                    <span class="db-svc3-tag">RTM</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title" data-i18n="svc3_title">Road &amp; Transportation Management</h3>
                    <p  class="db-svc3-desc"  data-i18n="svc3_desc">Overseeing road construction, maintenance, and city transportation networks.</p>
                </div>
                <a class="db-svc3-btn" href="https://rtm.infragovservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

            <!-- Card 4 — purple -->
            <div class="db-svc3-card db-svc3-purple dash-aos dash-delay-4" id="dashSvcCard4">
                <div class="db-svc3-bg-num">04</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-calendar-check"></i></div>
                    <span class="db-svc3-tag">CPRF</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title" data-i18n="svc4_title">Public Facilities Reservation</h3>
                    <p  class="db-svc3-desc"  data-i18n="svc4_desc">Book community centers, parks, and sports fields for public use.</p>
                </div>
                <a class="db-svc3-btn" href="https://cprf.infragovservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

            <!-- Card 5 — rose -->
            <div class="db-svc3-card db-svc3-rose dash-aos dash-delay-5" id="dashSvcCard5">
                <div class="db-svc3-bg-num">05</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-tools"></i></div>
                    <span class="db-svc3-tag">CIMM</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title" data-i18n="svc5_title">Community Infrastructure Maintenance</h3>
                    <p  class="db-svc3-desc"  data-i18n="svc5_desc">Coordinating repairs and upkeep for public assets and safety systems.</p>
                </div>
                <a class="db-svc3-btn" href="https://cimm.infragovservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

            <!-- Card 6 — teal -->
            <div class="db-svc3-card db-svc3-teal dash-aos dash-delay-6" id="dashSvcCard6">
                <div class="db-svc3-bg-num">06</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-leaf"></i></div>
                    <span class="db-svc3-tag">ECM</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title" data-i18n="svc6_title">Energy Efficiency &amp; Conservation</h3>
                    <p  class="db-svc3-desc"  data-i18n="svc6_desc">Implementing sustainable energy practices and city-wide conservation programs.</p>
                </div>
                <a class="db-svc3-btn" href="https://energy.infragovservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

            <!-- Card 7 — amber -->
            <div class="db-svc3-card db-svc3-amber dash-aos dash-delay-1" id="dashSvcCard7">
                <div class="db-svc3-bg-num">07</div>
                <div class="db-svc3-orb"></div>
                <div class="db-svc3-top">
                    <div class="db-svc3-chip"><i class="fas fa-map"></i></div>
                    <span class="db-svc3-tag">UPaD</span>
                </div>
                <div class="db-svc3-body">
                    <h3 class="db-svc3-title">Urban Planning and Development</h3>
                    <p  class="db-svc3-desc">Zoning, architectural reviews, and long-term city growth strategies.</p>
                </div>
                <a class="db-svc3-btn" href="https://UPaD.infragovservices.com/" target="_blank" rel="noopener noreferrer">
                    <span data-i18n="svc_visit">Visit Department</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
                <div class="db-svc3-shimmer"></div>
                <div class="db-svc3-border-anim"></div>
            </div>

        </div>
    </section>

    </section>

    <!-- ── GALLERY — Editorial Spread ───────────────── -->
    <section class="db-gallery-v2 dash-aos" id="dashGallery">
        <!-- Section header -->
        <div class="db-gv2-header">
            <div class="db-gv2-eyebrow"><span></span><span data-i18n="gallery_cat">Facility</span></div>
            <h2 class="db-gv2-title" data-i18n="gallery_title">City Facilities</h2>
            <p  class="db-gv2-sub"   data-i18n="gallery_subtitle">Quezon City's landmark public buildings maintained by our departments</p>
        </div>

        <!-- Editorial grid -->
        <div class="db-gv2-grid">
            <!-- Featured hero image -->
            <a class="db-gv2-item db-gv2-hero dash-aos dash-delay-1" id="dashGalleryItem1"
               href="<?= $BASE_URL ?>assets/img/portfolio/fullsize/1.jpg" title="Quezon City Hall">
                <div class="db-gv2-img-wrap">
                    <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/1.jpg" alt="Quezon City Hall">
                    <div class="db-gv2-scan"></div>
                </div>
                <div class="db-gv2-overlay">
                    <div class="db-gv2-tag" data-i18n="gallery_cat">Facility</div>
                    <h3 class="db-gv2-name" data-i18n="gal1_name">Quezon City Hall</h3>
                    <div class="db-gv2-view"><i class="fas fa-expand"></i> View Full</div>
                </div>
                <div class="db-gv2-corner db-gv2-corner-tl"></div>
                <div class="db-gv2-corner db-gv2-corner-br"></div>
            </a>

            <!-- Right stack -->
            <div class="db-gv2-stack">
                <a class="db-gv2-item dash-aos dash-delay-2"
                   href="<?= $BASE_URL ?>assets/img/portfolio/fullsize/2.jpg" title="QC Council Legislative Building">
                    <div class="db-gv2-img-wrap">
                        <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/2.jpg" alt="Legislative Building">
                        <div class="db-gv2-scan"></div>
                    </div>
                    <div class="db-gv2-overlay">
                        <div class="db-gv2-tag" data-i18n="gallery_cat">Facility</div>
                        <h3 class="db-gv2-name" data-i18n="gal2_name">QC Council Legislative Building</h3>
                        <div class="db-gv2-view"><i class="fas fa-expand"></i> View Full</div>
                    </div>
                    <div class="db-gv2-corner db-gv2-corner-tl"></div>
                    <div class="db-gv2-corner db-gv2-corner-br"></div>
                </a>
                <!-- 2-col mini row -->
                <div class="db-gv2-mini-row">
                    <a class="db-gv2-item dash-aos dash-delay-3"
                       href="<?= $BASE_URL ?>assets/img/portfolio/fullsize/3.jpg" title="Quezon City Public Library">
                        <div class="db-gv2-img-wrap">
                            <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/3.jpg" alt="QC Public Library">
                            <div class="db-gv2-scan"></div>
                        </div>
                        <div class="db-gv2-overlay">
                            <div class="db-gv2-tag" data-i18n="gallery_cat">Facility</div>
                            <h3 class="db-gv2-name" data-i18n="gal3_name">QC Public Library</h3>
                            <div class="db-gv2-view"><i class="fas fa-expand"></i></div>
                        </div>
                        <div class="db-gv2-corner db-gv2-corner-tl"></div>
                        <div class="db-gv2-corner db-gv2-corner-br"></div>
                    </a>
                    <a class="db-gv2-item dash-aos dash-delay-4"
                       href="<?= $BASE_URL ?>assets/img/portfolio/fullsize/4.jpg" title="QC MICE Center">
                        <div class="db-gv2-img-wrap">
                            <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/4.jpg" alt="MICE Center">
                            <div class="db-gv2-scan"></div>
                        </div>
                        <div class="db-gv2-overlay">
                            <div class="db-gv2-tag" data-i18n="gallery_cat">Facility</div>
                            <h3 class="db-gv2-name" data-i18n="gal4_name">QC MICE Center</h3>
                            <div class="db-gv2-view"><i class="fas fa-expand"></i></div>
                        </div>
                        <div class="db-gv2-corner db-gv2-corner-tl"></div>
                        <div class="db-gv2-corner db-gv2-corner-br"></div>
                    </a>
                </div>
            </div>

            <!-- Bottom strip -->
            <a class="db-gv2-item db-gv2-strip dash-aos dash-delay-5"
               href="<?= $BASE_URL ?>assets/img/portfolio/fullsize/5.jpg" title="QC Hall Public Plaza">
                <div class="db-gv2-img-wrap">
                    <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/5.jpg" alt="QC Hall Plaza">
                    <div class="db-gv2-scan"></div>
                </div>
                <div class="db-gv2-overlay">
                    <div class="db-gv2-tag" data-i18n="gallery_cat">Facility</div>
                    <h3 class="db-gv2-name" data-i18n="gal5_name">QC Hall Public Plaza &amp; Parking</h3>
                    <div class="db-gv2-view"><i class="fas fa-expand"></i> View Full</div>
                </div>
                <div class="db-gv2-corner db-gv2-corner-tl"></div>
                <div class="db-gv2-corner db-gv2-corner-br"></div>
            </a>
            <a class="db-gv2-item db-gv2-strip dash-aos dash-delay-6"
               href="<?= $BASE_URL ?>assets/img/portfolio/fullsize/6.jpg" title="Citizen One-Stop Service Center">
                <div class="db-gv2-img-wrap">
                    <img src="<?= $BASE_URL ?>assets/img/portfolio/thumbnails/6.jpg" alt="One-Stop Service Center">
                    <div class="db-gv2-scan"></div>
                </div>
                <div class="db-gv2-overlay">
                    <div class="db-gv2-tag" data-i18n="gallery_cat">Facility</div>
                    <h3 class="db-gv2-name" data-i18n="gal6_name">Citizen One-Stop Service Center</h3>
                    <div class="db-gv2-view"><i class="fas fa-expand"></i> View Full</div>
                </div>
                <div class="db-gv2-corner db-gv2-corner-tl"></div>
                <div class="db-gv2-corner db-gv2-corner-br"></div>
            </a>
        </div>
    </section>

</main>

<!-- ═══════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════ -->
<footer class="dash-footer" id="dashFooter">
    <div class="dash-footer-content">
        <div class="dash-footer-about">
            <h3>InfraGovServices</h3>
            <p data-i18n="footer_desc">The central portal for all Quezon City Infrastructure and Utilities departments.</p>
            <div class="dash-footer-contact">
                <div class="dash-contact-item"><i class="fas fa-envelope"></i><span>contact@infragovservices.com</span></div>
                <div class="dash-contact-item"><i class="fas fa-phone"></i><span>(02) 8988-4242</span></div>
                <div class="dash-contact-item"><i class="fas fa-map-marker-alt"></i><span>Quezon City Hall, Quezon City</span></div>
            </div>
        </div>
        <div class="dash-footer-links">
            <h4 data-i18n="footer_quick_links">Quick Links</h4>
            <ul>
                <li><a href="#"            data-i18n="footer_link_home">Home</a></li>
                <li><a href="#dashAbout"   data-i18n="footer_link_about">About</a></li>
                <li><a href="#dashServices"data-i18n="footer_link_services">Services</a></li>
                <li><a href="#dashGallery" data-i18n="footer_link_gallery">Facilities</a></li>
            </ul>
        </div>
        <div class="dash-footer-links">
            <h4 data-i18n="footer_departments">Departments</h4>
            <ul>
                <li><a href="https://cimm.infragovservices.com/"   target="_blank" rel="noopener">CIMMS</a></li>
                <li><a href="https://cprf.infragovservices.com/"   target="_blank" rel="noopener">CPRF</a></li>
                <li><a href="https://ipms.infragovservices.com/"   target="_blank" rel="noopener">IPMS</a></li>
                <li><a href="https://energy.infragovservices.com/" target="_blank" rel="noopener" data-i18n="footer_energy">Energy Dept.</a></li>
            </ul>
        </div>
        <div class="dash-footer-links">
            <h4 data-i18n="footer_legal">Legal</h4>
            <ul>
                <li><a href="privacy.php" data-i18n="footer_link_privacy">Privacy Policy</a></li>
                <li><a href="termcon.php" data-i18n="footer_link_terms">Terms of Service</a></li>
                <li><a href="#"           data-i18n="footer_link_access">Accessibility</a></li>
                <li><a href="#"           data-i18n="footer_link_data">Data Protection</a></li>
            </ul>
        </div>
    </div>
    <div class="dash-footer-bottom">
        <div data-i18n="footer_copyright">© 2026 LGU Quezon City · InfraGovServices · All Rights Reserved</div>
        <div class="dash-footer-social">
            <a href="#" class="dash-social-link" title="Facebook"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
            <a href="#" class="dash-social-link" title="Twitter/X"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
            <a href="#" class="dash-social-link" title="Instagram"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
            <a href="mailto:contact@infragovservices.com" class="dash-social-link" title="Email"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg></a>
        </div>
    </div>
</footer>

<!-- ═══════════════════════════════════════════════════
     GUIDE OVERLAY (DOM nodes only — driven by scripts.js)
════════════════════════════════════════════════════ -->
<div id="dashGuideOverlay"></div>
<div id="dashGuideSpotlight" style="display:none;"></div>
<div id="dashGuideCard" style="display:none;">
    <!-- Animated gradient top bar -->
    <div class="db-guide-top-bar">
        <div class="db-guide-progress-track">
            <div class="db-guide-progress-fill" id="dashGuideProgressFill"></div>
        </div>
    </div>
    <!-- Header -->
    <div class="db-guide-header">
        <div class="db-guide-step-badge" id="dashGuideStepNum">Step 1 of 13</div>
        <button class="db-guide-close" id="dashGuideCloseBtn" aria-label="Close guide">
            <svg viewBox="0 0 24 24" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
        </button>
    </div>
    <!-- Body -->
    <div class="db-guide-body">
        <div class="db-guide-title" id="dashGuideTitle"></div>
        <div class="db-guide-desc"  id="dashGuideDesc"></div>
    </div>
    <!-- Footer -->
    <div class="db-guide-footer">
        <div class="db-guide-dots" id="dashGuideDots"></div>
        <div class="db-guide-btns">
            <button class="db-guide-btn-back" id="dashGuidePrevBtn">← Back</button>
            <button class="db-guide-btn-next" id="dashGuideNextBtn">Next →</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════ -->

<!-- PHP-injected server time for clock (must be inline) -->
<script>window.DASH_SERVER_TIME = <?= $serverTimestamp * 1000 ?>;</script>

<!-- SimpleLightbox JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/SimpleLightbox/2.1.0/simpleLightbox.min.js"></script>

<!-- Main scripts: Creative theme base + all citizendash features -->
<script src="<?= $BASE_URL ?>scripts.js"></script>

</body>
</html>