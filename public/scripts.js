/*!
* Start Bootstrap - Creative v7.0.7 (https://startbootstrap.com/theme/creative)
* Copyright 2013-2023 Start Bootstrap
* Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-creative/blob/master/LICENSE)
*/
//
// Scripts
// 

window.addEventListener('DOMContentLoaded', event => {

    // Navbar shrink function
    var navbarShrink = function () {
        const navbarCollapsible = document.body.querySelector('#mainNav');
        if (!navbarCollapsible) {
            return;
        }
        if (window.scrollY === 0) {
            navbarCollapsible.classList.remove('navbar-shrink')
        } else {
            navbarCollapsible.classList.add('navbar-shrink')
        }

    };

    // Shrink the navbar 
    navbarShrink();

    // Shrink the navbar when page is scrolled
    document.addEventListener('scroll', navbarShrink);

    // Activate Bootstrap scrollspy on the main nav element
    const mainNav = document.body.querySelector('#mainNav');
    if (mainNav && typeof bootstrap !== 'undefined' && bootstrap.ScrollSpy) {
        new bootstrap.ScrollSpy(document.body, {
            target: '#mainNav',
            rootMargin: '0px 0px -40%',
        });
    };

    // Collapse responsive navbar when toggler is visible
    const navbarToggler = document.body.querySelector('.navbar-toggler');
    const responsiveNavItems = [].slice.call(
        document.querySelectorAll('#navbarResponsive .nav-link')
    );
    responsiveNavItems.map(function (responsiveNavItem) {
        responsiveNavItem.addEventListener('click', () => {
            if (window.getComputedStyle(navbarToggler).display !== 'none') {
                navbarToggler.click();
            }
        });
    });

    // Activate SimpleLightbox plugin for portfolio items (old Creative theme pages only)
    if (typeof SimpleLightbox !== 'undefined' && document.querySelector('#portfolio a.portfolio-box')) {
        new SimpleLightbox({
            elements: '#portfolio a.portfolio-box'
        });
    }

});

/* ==========================================================
   InfraGovServices – citizendash.php scripts
   Appended to existing Creative theme JS
   ========================================================== */

(function () {
    'use strict';

    /* ── Scroll animations ─────────────────────────────── */
    function initScrollAnim() {
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.classList.add('dash-aos-in');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.dash-aos').forEach(function (el) { obs.observe(el); });
    }

    /* ── SimpleLightbox for gallery ────────────────────── */
    function initGallery() {
        if (typeof SimpleLightbox !== 'undefined') {
            new SimpleLightbox({
                elements: '.db-gv2-item',
                swipeTolerance: 50,        /* swipe distance to change image */
                closeOnOverlayClick: true,
                showCounter: true,
                scrollZoom: false,
                /* On mobile hide arrows — rely on swipe instead */
                navText: window.innerWidth <= 768 ? ['', ''] : ['&#8249;', '&#8250;'],
            });
        }
    }

    /* ── Smooth scroll for anchor links ────────────────── */
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                var target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    /* ── Mobile sidebar ────────────────────────────────── */
    function initSidebar() {
        var toggle   = document.getElementById('dashMobileToggle');
        var closeBtn = document.getElementById('dashSidebarClose');
        var sidebar  = document.getElementById('dashSidebar');
        var overlay  = document.getElementById('dashSidebarOverlay');

        function openSidebar()  { if (sidebar) sidebar.classList.add('db-sidebar-open');    if (overlay) overlay.classList.add('db-overlay-show'); }
        function closeSidebar() { if (sidebar) sidebar.classList.remove('db-sidebar-open'); if (overlay) overlay.classList.remove('db-overlay-show'); }

        if (toggle)   toggle.addEventListener('click',   function(e){ e.stopPropagation(); openSidebar(); });
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay)  overlay.addEventListener('click',  closeSidebar);

        if (sidebar) {
            sidebar.addEventListener('click', function(e) { e.stopPropagation(); });
            sidebar.querySelectorAll('.db-sidebar-links a').forEach(function(link) {
                link.addEventListener('click', closeSidebar);
            });
        }
    }

    /* ── Clock ─────────────────────────────────────────── */
    function initClock() {
        if (typeof window.DASH_SERVER_TIME === 'undefined') return;

        var TL_DAYS   = ['Linggo','Lunes','Martes','Miyerkules','Huwebes','Biyernes','Sabado'];
        var TL_MONTHS = ['Enero','Pebrero','Marso','Abril','Mayo','Hunyo','Hulyo','Agosto','Setyembre','Oktubre','Nobyembre','Disyembre'];
        var current   = window.DASH_SERVER_TIME;
        var clockInt  = null;
        var lastSec   = null;

        function renderClock(now) {
            var lang = localStorage.getItem('lang') || 'en';
            var datePart;
            if (lang === 'tl') {
                datePart = TL_DAYS[now.getDay()] + ', ' + TL_MONTHS[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
            } else {
                datePart = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
            }
            var ts = now.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', second:'2-digit', hour12:true });
            var m  = ts.match(/^(\d+):(\d+):(\d+)\s?(AM|PM)$/i);
            var h  = m ? m[1] : '--', mi = m ? m[2] : '--', s = m ? m[3] : '--', ap = m ? m[4] : '';
            var flip = function(str) { return str.split('').map(function(c){ return '<span>'+c+'</span>'; }).join(''); };

            var dc = document.getElementById('dashDesktopClock');
            var mc = document.getElementById('dashMobileClock');
            if (dc) dc.innerHTML = '<span class="dash-date-part">'+datePart+'</span>&nbsp;&nbsp;<span class="dash-time-part">'+flip(h)+':'+flip(mi)+':'+flip(s)+' '+ap+'</span>';
            if (mc) mc.textContent = h+':'+mi+':'+s+' '+ap;
        }

        function tick() {
            var now = new Date(current);
            var sec = now.getSeconds();
            if (sec !== lastSec) {
                document.querySelectorAll('.dash-time-part').forEach(function(el){
                    el.classList.add('flip');
                    setTimeout(function(){ el.classList.remove('flip'); }, 250);
                });
                lastSec = sec;
            }
            renderClock(now);
            current += 1000;
        }

        function startClock() { if (clockInt) return; tick(); clockInt = setInterval(tick, 1000); }
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) { clearInterval(clockInt); clockInt = null; } else startClock();
        });
        startClock();
    }

    /* ── Dark mode ─────────────────────────────────────── */
    function initDarkMode() {
        /* Collect ALL dark-mode buttons: desktop toggle, mobile btn, sidebar btn */
        var allDarkBtns = [
            document.getElementById('dashDarkBtn'),
            document.getElementById('dashMobileDarkBtn'),
            document.getElementById('dashSidebarDarkBtn')
        ].filter(Boolean);
        var html = document.documentElement;

        function update(isDark, animate) {
            isDark ? html.setAttribute('data-theme','dark') : html.removeAttribute('data-theme');
            try {
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                localStorage.setItem('theme_backup', isDark ? 'dark' : 'light');
            } catch(e) {}

            allDarkBtns.forEach(function(btn) {
                var di = btn.querySelector('.db-dark-icon, .dash-dark-icon');
                var li = btn.querySelector('.db-light-icon, .dash-light-icon');
                if (di) di.style.display = isDark ? 'none'   : '';
                if (li) li.style.display = isDark ? ''       : 'none';
                if (animate) {
                    btn.classList.add('db-theme-anim');
                    setTimeout(function(){ btn.classList.remove('db-theme-anim'); }, 500);
                }
            });

            /* Update toggle switch track visual */
            var track = document.querySelector('.db-theme-track');
            if (track) track.classList.toggle('is-dark', isDark);
            /* Update browser chrome theme-color */
            var tcMeta = document.getElementById('metaThemeColor');
            if (tcMeta) tcMeta.setAttribute('content', isDark ? '#050a19' : '#f0f4ff');

            /* Update sidebar label */
            var sLbl = document.getElementById('dashSidebarLangLabel');
            // (keep lang label separate)
        }

        try {
            var t = localStorage.getItem('theme') || localStorage.getItem('theme_backup') || 'light';
            update(t === 'dark', false);
        } catch(e) { update(false, false); }

        allDarkBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                update(html.getAttribute('data-theme') !== 'dark', true);
            });
        });
    }

    /* ── Translations ──────────────────────────────────── */
    var DASH_TRANSLATIONS = {
        en: {
            site_title_short: 'InfraGovServices',
            nav_home: 'Home', nav_about: 'About', nav_services: 'Services',
            nav_gallery: 'Facilities', nav_privacy: 'Privacy Policy',
            hero_badge: 'Quezon City · Local Government Unit',
            hero_h1: 'Welcome to InfraGovServices!',
            hero_title: 'Welcome to InfraGovServices',
            hero_subtitle: 'Infrastructure & Utilities Department Portal',
            hero_tagline: 'Your unified gateway to all Quezon City infrastructure and utilities departments — transparent, accessible, and built for every resident.',
            hero_cta_explore: 'Explore Departments', cta_guide: '🗺️ Guide',
            pill_ipms:'Infrastructure', pill_uman:'Utilities', pill_cprf:'Facilities',
            pill_cimm:'Maintenance', pill_energy:'Energy', pill_rtm:'Roads',
            stat_depts:'Active Departments', stat_depts_sub:'City-wide infrastructure coverage',
            stat_residents:'Residents Served', stat_residents_sub:'Quezon City population',
            stat_projects:'Projects Completed', stat_projects_sub:'Public infrastructure works',
            stat_access:'Online Access', stat_access_sub:'Anytime, anywhere',
            about_overlay_title:'Quezon City Hall', about_overlay_sub:'Excellence in Public Service',
            about_title:'About Quezon City Infrastructure & Utilities',
            about_eyebrow:'About Us', about_heading:'About <span>Quezon City</span><br>Infrastructure &amp; Utilities', about_p1:'The Quezon City Infrastructure and Utilities Service is dedicated to managing, maintaining, and improving essential public facilities to ensure safe, reliable, and efficient community services.',
            about_p2:'Our goal is to streamline maintenance operations, enhance infrastructure responsiveness, and provide residents with transparent access to service updates and support across all departments.',
            hl1_title:'Project Management', hl1_desc:'City-wide construction oversight',
            hl2_title:'Utilities Management', hl2_desc:'Water, power & waste services',
            hl3_title:'Maintenance', hl3_desc:'Repairs and upkeep of public assets',
            hl4_title:'Sustainability', hl4_desc:'Energy-efficient city planning',
            about_cta:'View All Departments',
            services_eyebrow:'Departments', services_title:'Community Departments & Services',
            services_subtitle:'Select a department below to access its dedicated portal and services',
            svc1_title:'Infrastructure Project Management', svc1_desc:'Planning and oversight of city-wide construction and development projects.',
            svc2_title:'Utilities Billing and Management',  svc2_desc:'Handling water, electricity, and waste disposal accounts and payments.',
            svc3_title:'Road & Transportation Management',  svc3_desc:'Overseeing road construction, maintenance, and city transportation networks.',
            svc4_title:'Public Facilities Reservation System', svc4_desc:'Book community centers, parks, and sports fields for public use.',
            svc5_title:'Community Infrastructure Maintenance', svc5_desc:'Coordinating repairs and upkeep for public assets and safety systems.',
            svc6_title:'Energy Efficiency & Conservation', svc6_desc:'Implementing sustainable energy practices and city-wide conservation programs.',
            svc_visit:'Visit Department →',
            gallery_title:'City Facilities', gallery_subtitle:"Quezon City's landmark public buildings and spaces maintained by our departments",
            gallery_cat:'Facility',
            gal1_name:'Quezon City Hall', gal2_name:'QC Council Legislative Building',
            gal3_name:'Quezon City Public Library – Main', gal4_name:'QC MICE Center / Convention Center',
            gal5_name:'QC Hall Public Plaza & Parking',   gal6_name:'Citizen One-Stop Service Center QC',
            footer_desc:'The central portal for all Quezon City Infrastructure and Utilities departments.',
            footer_quick_links:'Quick Links', footer_link_home:'Home', footer_link_about:'About',
            footer_link_services:'Services', footer_link_gallery:'Facilities',
            footer_departments:'Departments', footer_energy:'Energy Dept.', footer_legal:'Legal',
            footer_link_privacy:'Privacy Policy', footer_link_terms:'Terms of Service',
            footer_link_access:'Accessibility', footer_link_data:'Data Protection',
            footer_copyright:'© 2026 LGU Quezon City · InfraGovServices · All Rights Reserved',
            translate_btn_title:'Translate to Filipino', lang_label:'EN',
            /* Guide chrome */
            guide_step_label:'Step {n} of {total}', guide_back:'← Back', guide_next:'Next →',
            guide_finish:'Finish ✓', guide_close:'Close Guide', guide_dot_label:'Go to step {n}',
            /* Guide titles */
            guide_nav_title:'🗺️ Navigation Bar', guide_lang_title:'🌐 Language & Dark Mode',
            guide_hero_title:'🏙️ Hero Section', guide_about_title:'ℹ️ About Section',
            guide_svc_title:'🏛️ Department Services', guide_svc2_title:'📋 Utilities Billing Card',
            guide_gallery_title:'🖼️ City Facilities Gallery',
            guide_footer_title:'🔗 Footer',
            /* Guide descriptions */
            guide_nav_desc:'The top navigation bar. Use <b>Home</b>, <b>About</b>, <b>Services</b>, <b>Facilities</b>, and <b>Privacy</b> to navigate. On mobile, tap the <b>☰</b> button to open the sidebar drawer.',
            guide_lang_desc:'Click the <b>globe pill</b> to switch between English and Filipino. The <b>toggle switch</b> next to it turns <b>Dark Mode</b> on or off — your preference is saved automatically.',
            guide_lang_desc_mobile:'Tap the <b>globe button</b> to switch languages. Tap the <b>moon/sun icon</b> on the right to toggle Dark Mode.',
            guide_hero_desc:'The main hero section. Click <b>Explore Departments</b> to jump to the departments grid. Press <b>Guide</b> again any time to restart this walkthrough.',
            guide_about_desc:'These four feature rows summarise the key service areas: <b>Project Management</b>, <b>Utilities</b>, <b>Maintenance</b>, and <b>Sustainability</b>. Click the row to see more.',
            guide_svc_desc:'The section heading for all six city departments. Each card below links directly to that department\'s portal. <b>Hover</b> a card to reveal its description and visit button.',
            guide_svc2_desc:'This is the <b>Utilities Billing and Management (UMAN)</b> card. Click <b>Visit →</b> to manage water, electricity, and waste accounts.',
            guide_svc1_title:'🏗️ Infrastructure Project Management', guide_svc1_desc:'Card <b>01 — IPMS</b>: Handles planning and oversight of city-wide construction and development projects across Quezon City.',
            guide_svc3_title:'🛣️ Road & Transportation Management', guide_svc3_desc:'Card <b>03 — RTM</b>: Oversees road construction, maintenance, and all city transportation network operations.',
            guide_svc4_title:'🏟️ Public Facilities Reservation', guide_svc4_desc:'Card <b>04 — CPRF</b>: Book community centers, parks, and sports fields for events and public use.',
            guide_svc5_title:'🔧 Community Infrastructure Maintenance', guide_svc5_desc:'Card <b>05 — CIMM</b>: Coordinates repairs and upkeep for all public assets, roads, and safety systems.',
            guide_svc6_title:'🌿 Energy Efficiency & Conservation', guide_svc6_desc:'Card <b>06 — ECM</b>: Implements sustainable energy practices and city-wide conservation programmes.',
            guide_gallery_desc:'Browse Quezon City\'s six landmark public facilities. <b>Click any photo</b> to view it full-screen with the lightbox viewer.',
            guide_footer_desc:'Quick links at the bottom of the page — <b>Quick Links</b>, department shortcuts, and <b>Legal</b> pages like Privacy Policy and Terms of Service.',
        },
        tl: {
            site_title_short:'InfraGovServices',
            nav_home:'Tahanan', nav_about:'Tungkol Sa', nav_services:'Mga Serbisyo',
            nav_gallery:'Mga Pasilidad', nav_privacy:'Patakaran sa Privacy',
            hero_badge:'Quezon City · Lokal na Pamahalaan',
            hero_h1: 'Maligayang pagdating sa InfraGovServices!',
            hero_title:'Maligayang Pagdating sa InfraGovServices',
            hero_subtitle:'Portal ng Departamento ng Imprastraktura at Utilities',
            hero_tagline:'Ang inyong pinag-isang pintuan sa lahat ng departamento ng imprastraktura at utilities ng Quezon City — transparent, naa-access, at itinayo para sa bawat residente.',
            hero_cta_explore:'Tuklasin ang mga Departamento', cta_guide:'🗺️ Gabay',
            pill_ipms:'Imprastraktura', pill_uman:'Utilities', pill_cprf:'Pasilidad',
            pill_cimm:'Pagpapanatili', pill_energy:'Enerhiya', pill_rtm:'Kalsada',
            stat_depts:'Aktibong Departamento', stat_depts_sub:'Saklaw ng imprastraktura ng lungsod',
            stat_residents:'Mga Residenteng Pinagsilbihan', stat_residents_sub:'Populasyon ng Quezon City',
            stat_projects:'Mga Proyektong Natapos', stat_projects_sub:'Mga gawain sa pampublikong imprastraktura',
            stat_access:'Online na Access', stat_access_sub:'Kahit saan, kahit kailan',
            about_overlay_title:'Quezon City Hall', about_overlay_sub:'Kahusayan sa Pampublikong Serbisyo',
            about_title:'Tungkol sa Imprastraktura at Utilities ng Quezon City',
            about_eyebrow:'Tungkol Sa Amin', about_heading:'Tungkol sa <span>Lungsod ng Quezon</span><br>Imprastraktura at Mga Serbisyo', about_p1:'Ang Quezon City Infrastructure and Utilities Service ay nakatuon sa pamamahala, pagpapanatili, at pagpapabuti ng mahahalagang pampublikong pasilidad.',
            about_p2:'Ang aming layunin ay gawing mas maayos ang mga operasyon ng pagpapanatili at bigyan ang mga residente ng transparent na access sa mga update at suporta.',
            hl1_title:'Pamamahala ng Proyekto', hl1_desc:'Pangkalahatang pangangasiwa ng konstruksyon',
            hl2_title:'Pamamahala ng Utilities', hl2_desc:'Tubig, kuryente, at basura',
            hl3_title:'Pagpapanatili', hl3_desc:'Pagkukumpuni ng mga pampublikong asset',
            hl4_title:'Sustainability', hl4_desc:'Malikhaing pagpaplano ng lungsod',
            about_cta:'Tingnan ang Lahat ng Departamento',
            services_eyebrow:'Mga Departamento', services_title:'Mga Departamento at Serbisyo ng Komunidad',
            services_subtitle:'Pumili ng departamento sa ibaba para ma-access ang dedikadong portal nito',
            svc1_title:'Pamamahala ng Proyektong Imprastraktura', svc1_desc:'Pagpaplano at pangangasiwa ng mga proyektong konstruksyon sa buong lungsod.',
            svc2_title:'Pamamahala ng Billing ng Utilities',      svc2_desc:'Pangangasiwa ng mga account at bayad sa tubig, kuryente, at basura.',
            svc3_title:'Pamamahala ng Kalsada at Transportasyon', svc3_desc:'Pangangasiwa ng konstruksyon ng kalsada at mga network ng transportasyon.',
            svc4_title:'Sistema ng Reserbasyon ng Pampublikong Pasilidad', svc4_desc:'Mag-book ng mga community center, parke, at sports field para sa publiko.',
            svc5_title:'Pamamahala ng Pagpapanatili ng Imprastraktura', svc5_desc:'Pag-coordinate ng mga pagkukumpuni at pagpapanatili ng mga pampublikong asset.',
            svc6_title:'Kahusayan at Pag-iingat ng Enerhiya', svc6_desc:'Pagpapatupad ng mga napapanatiling gawi sa enerhiya at programa ng konserbasyon.',
            svc_visit:'Bisitahin ang Departamento →',
            gallery_title:'Mga Pasilidad ng Lungsod', gallery_subtitle:'Mga pangunahing pampublikong gusali ng Quezon City na pinamamahalaan ng aming mga departamento',
            gallery_cat:'Pasilidad',
            gal1_name:'Quezon City Hall', gal2_name:'Gusaling Pambatas ng QC Council',
            gal3_name:'Pangunahing Aklatan ng Quezon City', gal4_name:'QC MICE Center / Convention Center',
            gal5_name:'QC Hall Public Plaza at Parking', gal6_name:'Citizen One-Stop Service Center QC',
            footer_desc:'Ang sentral na portal para sa lahat ng departamento ng Imprastraktura at Utilities ng Quezon City.',
            footer_quick_links:'Mabilis na Mga Link', footer_link_home:'Tahanan', footer_link_about:'Tungkol Sa',
            footer_link_services:'Mga Serbisyo', footer_link_gallery:'Mga Pasilidad',
            footer_departments:'Mga Departamento', footer_energy:'Dept. ng Enerhiya', footer_legal:'Legal',
            footer_link_privacy:'Patakaran sa Privacy', footer_link_terms:'Mga Tuntunin ng Serbisyo',
            footer_link_access:'Accessibility', footer_link_data:'Proteksyon ng Data',
            footer_copyright:'© 2026 LGU Quezon City · InfraGovServices · Lahat ng Karapatan ay Nakalaan',
            translate_btn_title:'Isalin sa Filipino', lang_label:'FIL',
            /* Guide chrome */
            guide_step_label:'Hakbang {n} sa {total}', guide_back:'← Bumalik', guide_next:'Susunod →',
            guide_finish:'Tapos ✓', guide_close:'Isara ang Gabay', guide_dot_label:'Pumunta sa hakbang {n}',
            /* Guide titles */
            guide_nav_title:'🗺️ Bar ng Nabigasyon', guide_lang_title:'🌐 Wika at Dark Mode',
            guide_hero_title:'🏙️ Seksyon ng Hero', guide_about_title:'ℹ️ Seksyon ng Tungkol Sa',
            guide_svc_title:'🏛️ Mga Serbisyo ng Departamento', guide_svc2_title:'📋 Card ng Utilities Billing',
            guide_gallery_title:'🖼️ Galeriya ng Mga Pasilidad',
            guide_footer_title:'🔗 Footer ng Pahina',
            /* Guide descriptions */
            guide_nav_desc:'Ang tuktok na navigation bar. Gamitin ang <b>Tahanan</b>, <b>Tungkol Sa</b>, <b>Mga Serbisyo</b>, <b>Mga Pasilidad</b>, at <b>Privacy</b> para mag-navigate. Sa mobile, i-tap ang <b>☰</b> para buksan ang sidebar.',
            guide_lang_desc:'I-click ang <b>globe pill</b> para lumipat sa pagitan ng English at Filipino. Ang <b>toggle switch</b> sa tabi nito ay nagbubukas ng <b>Dark Mode</b>.',
            guide_lang_desc_mobile:'I-tap ang <b>globe na pindutan</b> para lumipat ng wika. I-tap ang <b>moon/sun icon</b> para i-toggle ang Dark Mode.',
            guide_hero_desc:'Ang pangunahing hero section. I-click ang <b>Tuklasin ang mga Departamento</b> para tumalon sa grid ng mga departamento. Pindutin ang <b>Gabay</b> para muling simulan ang walkthrough.',
            guide_about_desc:'Ang apat na feature rows ay nagbubuod ng mga pangunahing serbisyo: <b>Pamamahala ng Proyekto</b>, <b>Utilities</b>, <b>Pagpapanatili</b>, at <b>Sustainability</b>.',
            guide_svc_desc:'Ang heading ng lahat ng anim na departamento ng lungsod. Ang bawat card sa ibaba ay direktang nagtaturo sa portal ng departamento. <b>I-hover</b> ang card para makita ang description at visit button.',
            guide_svc2_desc:'Card <b>02 — UMAN</b>: Para sa pamamahala ng billing ng tubig, kuryente, at basura. I-click ang <b>Visit →</b> para ma-access.',
            guide_svc1_title:'🏗️ Pamamahala ng Proyektong Imprastraktura', guide_svc1_desc:'Card <b>01 — IPMS</b>: Nangangasiwa ng pagpaplano at pagpapatupad ng mga proyektong konstruksyon sa buong Quezon City.',
            guide_svc3_title:'🛣️ Pamamahala ng Kalsada at Transportasyon', guide_svc3_desc:'Card <b>03 — RTM</b>: Nangangasiwa ng konstruksyon ng kalsada at mga network ng transportasyon sa lungsod.',
            guide_svc4_title:'🏟️ Reserbasyon ng Pampublikong Pasilidad', guide_svc4_desc:'Card <b>04 — CPRF</b>: Mag-book ng mga community center, parke, at sports field para sa mga aktibidad at pampublikong paggamit.',
            guide_svc5_title:'🔧 Pagpapanatili ng Imprastraktura ng Komunidad', guide_svc5_desc:'Card <b>05 — CIMM</b>: Nag-coordinate ng mga pagkukumpuni at pagpapanatili ng lahat ng pampublikong asset at sistema ng kaligtasan.',
            guide_svc6_title:'🌿 Kahusayan at Pag-iingat ng Enerhiya', guide_svc6_desc:'Card <b>06 — ECM</b>: Nagpapatupad ng mga napapanatiling gawi sa enerhiya at programa ng konserbasyon sa buong lungsod.',
            guide_gallery_desc:'I-browse ang anim na pangunahing pampublikong pasilidad ng Quezon City. <b>I-click ang anumang larawan</b> para makita ito sa full-screen.',
            guide_footer_desc:'Mga mabilis na link sa ibaba — <b>Mabilis na Mga Link</b>, mga shortcut ng departamento, at <b>Legal</b> na mga pahina.',
        }
    };

    function dashGT(key) {
        var lang = localStorage.getItem('lang') || 'en';
        var set  = DASH_TRANSLATIONS[lang] || DASH_TRANSLATIONS['en'];
        return set[key] || (DASH_TRANSLATIONS['en'][key]) || key;
    }
    window.__dashGT = dashGT;

    function applyTranslations() {
        var lang = localStorage.getItem('lang') || 'en';
        document.querySelectorAll('[data-i18n]').forEach(function(el) {
            var v = dashGT(el.getAttribute('data-i18n'));
            if (v) el.textContent = v;
        });
        document.querySelectorAll('[data-i18n-html]').forEach(function(el) {
            var v = dashGT(el.getAttribute('data-i18n-html'));
            if (v) el.innerHTML = v;
        });

        /* Update all language labels */
        var isTl = lang === 'tl';
        ['dashLangLabel'].forEach(function(id){ var el=document.getElementById(id); if(el) el.textContent = isTl ? 'FIL' : 'EN'; });
        ['dashMobileLangLabel'].forEach(function(id){ var el=document.getElementById(id); if(el) el.textContent = isTl ? 'FIL' : 'EN'; });

        /* Update flag in new nav */
        var flagEl = document.getElementById('dashLangFlag');
        if (flagEl) flagEl.textContent = isTl ? '🇵🇭' : '🇺🇸';

        /* Update sidebar lang button label */
        var sLbl = document.getElementById('dashSidebarLangLabel');
        if (sLbl) sLbl.textContent = isTl ? 'Switch to English' : 'Switch to Filipino';

        /* Toggle active class on translate buttons */
        ['dashTranslateBtn','dashMobileTranslateBtn','dashSidebarTranslateBtn'].forEach(function(id){
            var btn = document.getElementById(id);
            if (!btn) return;
            btn.classList.toggle('lang-active', isTl);
            btn.title = dashGT('translate_btn_title');
        });
    }

    /* ── Language toggle ───────────────────────────────── */
    function initTranslate() {
        var badge    = document.getElementById('dashLangBadge');
        var badgeFlg = document.getElementById('dashBadgeFlag');
        var badgeTxt = document.getElementById('dashBadgeText');
        var timer    = null;

        function showBadge(lang) {
            if (!badge) return;
            if (badgeFlg) badgeFlg.innerHTML = lang === 'tl' ? '&#x1F1F5;&#x1F1ED;' : '&#x1F1FA;&#x1F1F8;';
            if (badgeTxt) badgeTxt.textContent = lang === 'tl' ? 'Isinalin sa Filipino' : 'Switched to English';
            badge.classList.add('show');
            clearTimeout(timer);
            timer = setTimeout(function(){ badge.classList.remove('show'); }, 2500);
        }

        function toggle(e) {
            var btn = e && e.currentTarget;
            /* ripple animation */
            if (btn) {
                btn.classList.add('db-lang-ripple');
                setTimeout(function(){ btn.classList.remove('db-lang-ripple'); }, 400);
            }
            var cur = localStorage.getItem('lang') || 'en';
            var next = cur === 'en' ? 'tl' : 'en';
            localStorage.setItem('lang', next);
            applyTranslations();
            showBadge(next);
            document.dispatchEvent(new CustomEvent('i18nReady', { detail: { lang: next } }));
        }

        ['dashTranslateBtn','dashMobileTranslateBtn','dashSidebarTranslateBtn'].forEach(function(id){
            var btn = document.getElementById(id);
            if (btn) btn.addEventListener('click', toggle);
        });
        document.addEventListener('i18nReady', applyTranslations);
    }

    /* ── Guide ─────────────────────────────────────────── */
    function initGuide() {
        var steps = [
            { tKey:'guide_nav_title',     dKey:'guide_nav_desc',    dKeyMob:'guide_nav_desc',         sel:'#dashMainNav',         mSel:'#dashMobileTopNav', pad:0  },
            { tKey:'guide_lang_title',    dKey:'guide_lang_desc',   dKeyMob:'guide_lang_desc_mobile', sel:'#dashTranslateBtn',    mSel:'#dashMobileTopNav', pad:8  },
            { tKey:'guide_hero_title',    dKey:'guide_hero_desc',   sel:'#dashGuideBtn',    pad:10 },
            { tKey:'guide_about_title',   dKey:'guide_about_desc',  sel:'#dashAboutFeatures', pad:10 },
            { tKey:'guide_svc_title',     dKey:'guide_svc_desc',    sel:'#dashServicesHeader', pad:12 },
            { tKey:'guide_svc1_title',    dKey:'guide_svc1_desc',   sel:'#dashSvcCard1',    pad:10 },
            { tKey:'guide_svc2_title',    dKey:'guide_svc2_desc',   sel:'#dashSvcCard2',    pad:10 },
            { tKey:'guide_svc3_title',    dKey:'guide_svc3_desc',   sel:'#dashSvcCard3',    pad:10 },
            { tKey:'guide_svc4_title',    dKey:'guide_svc4_desc',   sel:'#dashSvcCard4',    pad:10 },
            { tKey:'guide_svc5_title',    dKey:'guide_svc5_desc',   sel:'#dashSvcCard5',    pad:10 },
            { tKey:'guide_svc6_title',    dKey:'guide_svc6_desc',   sel:'#dashSvcCard6',    pad:10 },
            { tKey:'guide_gallery_title', dKey:'guide_gallery_desc',sel:'#dashGalleryItem1', pad:12 },
            { tKey:'guide_footer_title',  dKey:'guide_footer_desc', sel:'#dashFooter',      pad:8  },
        ];

        var overlay   = document.getElementById('dashGuideOverlay');
        var spotlight = document.getElementById('dashGuideSpotlight');
        var card      = document.getElementById('dashGuideCard');
        var stepNumEl = document.getElementById('dashGuideStepNum');
        var titleEl   = document.getElementById('dashGuideTitle');
        var descEl    = document.getElementById('dashGuideDesc');
        var dotsEl    = document.getElementById('dashGuideDots');
        var closeBtn  = document.getElementById('dashGuideCloseBtn');
        var prevBtn   = document.getElementById('dashGuidePrevBtn');
        var nextBtn   = document.getElementById('dashGuideNextBtn');
        if (!overlay || !spotlight || !card) return;

        var cur = 0, active = false, posTimer = null;

        function getEl(step) {
            var mob = window.innerWidth < 640;
            if (mob && step.mSel) return document.querySelector(step.mSel);
            return document.querySelector(step.sel);
        }

        function forceIn(el) {
            var node = el;
            while (node && node !== document.body) { node.classList.add('dash-aos-in'); node = node.parentElement; }
        }

        function startGuide() {
            active = true;
            document.body.classList.add('dash-guide-active');
            overlay.style.display   = 'block';
            spotlight.style.display = 'block';
            card.style.display      = 'block';
            goTo(0);
        }

        function endGuide() {
            active = false;
            document.body.classList.remove('dash-guide-active');
            overlay.style.display   = 'none';
            spotlight.style.display = 'none';
            card.style.display      = 'none';
            clearTimeout(posTimer);
        }

        function goTo(idx) {
            if (idx < 0 || idx >= steps.length) { endGuide(); return; }
            cur = idx;
            var el = getEl(steps[idx]);
            if (!el) { goTo(idx + 1); return; }
            el.scrollIntoView({ behavior:'smooth', block:'center' });
            updateCard(steps[idx], idx);
            positionStep(idx);
        }

        function positionStep(idx) {
            var step = steps[idx], el = getEl(step);
            if (!el) return;
            var pad = step.pad || 12;
            forceIn(el);
            var STABLE=3, TICK=30, MAX=1400, elapsed=0, stab=0, lastT=null, lastL=null;
            clearTimeout(posTimer);
            function tick() {
                var r = el.getBoundingClientRect();
                if (lastT!==null && Math.abs(r.top-lastT)<0.5 && Math.abs(r.left-lastL)<0.5) stab++;
                else stab=0;
                lastT=r.top; lastL=r.left;
                if (stab>=STABLE || elapsed>=MAX) {
                    spotlight.style.top   =(r.top-pad)+'px'; spotlight.style.left =(r.left-pad)+'px';
                    spotlight.style.width =(r.width+pad*2)+'px'; spotlight.style.height=(r.height+pad*2)+'px';
                    placeCard(r,pad,step);
                } else { elapsed+=TICK; posTimer=setTimeout(tick,TICK); }
            }
            tick();
        }

        function placeCard(r,pad) {
            var vw=window.innerWidth, vh=(window.visualViewport?window.visualViewport.height:null)||window.innerHeight;
            var cw=Math.min(320,vw-28), ch=card.offsetHeight||200, gap=14;
            card.style.width=cw+'px'; card.style.bottom='auto'; card.style.right='auto';
            if (vw<640) {
                var MB=70,bt=14,spB=vh-(r.bottom+pad)-bt,spA=r.top-pad-MB,top;
                if (spB>=ch+gap) top=r.bottom+pad+gap;
                else if (spA>=ch+gap) top=r.top-pad-ch-gap;
                else if (spB>=spA) top=vh-ch-bt; else top=MB+4;
                top=Math.max(MB+4,Math.min(top,vh-ch-bt));
                card.style.top=top+'px'; card.style.left=Math.round((vw-cw)/2)+'px';
                card.style.transform='none'; card.style.right='auto'; trigAnim(); return;
            }
            card.style.transform='none';
            var spD=vh-(r.bottom+pad), spU=r.top-pad, top, left;
            if (spD>=ch+gap)      { top=r.bottom+pad+gap; left=r.left+r.width/2-cw/2; }
            else if (spU>=ch+gap) { top=r.top-pad-ch-gap; left=r.left+r.width/2-cw/2; }
            else { top=Math.max(16,Math.min(r.top+r.height/2-ch/2,vh-ch-16)); left=(vw-(r.right+pad)>=cw+gap)?r.right+pad+gap:r.left-pad-cw-gap; }
            card.style.top=Math.max(16,Math.min(top,vh-ch-16))+'px';
            card.style.left=Math.max(16,Math.min(left,vw-cw-16))+'px';
            trigAnim();
        }

        function trigAnim() { card.style.animation='none'; void card.offsetWidth; card.style.animation='dbGuideCardIn 0.22s cubic-bezier(0.34,1.56,0.64,1) forwards'; }

        function updateCard(step, idx) {
            /* Step badge */
            stepNumEl.textContent = dashGT('guide_step_label').replace('{n}',idx+1).replace('{total}',steps.length);

            /* Progress bar fill */
            var fill = document.getElementById('dashGuideProgressFill');
            if (fill) fill.style.width = Math.round(((idx+1)/steps.length)*100) + '%';

            /* Title and desc */
            titleEl.textContent = dashGT(step.tKey);
            descEl.innerHTML    = (window.innerWidth<640 && step.dKeyMob) ? dashGT(step.dKeyMob) : dashGT(step.dKey);
            if (closeBtn) closeBtn.title = dashGT('guide_close');

            /* Dots */
            dotsEl.innerHTML='';
            for (var i=0;i<steps.length;i++) {
                var d=document.createElement('span');
                d.className='db-guide-dot'+(i===idx?' active':'');
                d.title=dashGT('guide_dot_label').replace('{n}',i+1);
                (function(si){ d.addEventListener('click',function(){goTo(si);}); })(i);
                dotsEl.appendChild(d);
            }

            prevBtn.textContent=dashGT('guide_back'); prevBtn.disabled=(idx===0);
            nextBtn.textContent=(idx===steps.length-1)?dashGT('guide_finish'):dashGT('guide_next');
        }

        if (closeBtn) closeBtn.addEventListener('click', endGuide);
        if (overlay)  overlay.addEventListener('click', endGuide);
        if (prevBtn)  prevBtn.addEventListener('click', function(){ goTo(cur-1); });
        if (nextBtn)  nextBtn.addEventListener('click', function(){ if(cur===steps.length-1) endGuide(); else goTo(cur+1); });
        document.addEventListener('keydown', function(e){ if(!active)return; if(e.key==='Escape')endGuide(); if(e.key==='ArrowRight')goTo(cur+1); if(e.key==='ArrowLeft')goTo(cur-1); });
        window.addEventListener('resize', function(){ if(active)positionStep(cur); });

        var guideBtn = document.getElementById('dashGuideBtn');
        if (guideBtn) {
            guideBtn.addEventListener('click', function(e){ e.preventDefault(); startGuide(); });
            setTimeout(function(){
                guideBtn.classList.add('pulse-once');
                guideBtn.addEventListener('animationend', function(){ guideBtn.classList.remove('pulse-once'); },{once:true});
            }, 3000);
        }
    }

    /* ── Init all on DOM ready ─────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        /* Only run on citizendash page */
        if (!document.body.classList.contains('dash-page')) return;

        initScrollAnim();
        initGallery();
        initSmoothScroll();
        initSidebar();
        initClock();
        initDarkMode();
        initTranslate();
        applyTranslations();
        initGuide();

        /* Restore lang visibility block */
        document.documentElement.style.cssText = '';
    });

})();