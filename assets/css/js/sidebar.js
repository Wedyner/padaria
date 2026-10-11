/* ============================================================
   SIDEBAR + TOPBAR - Padaria do Gil
   ============================================================ */
(function () {
    'use strict';

    const layout  = document.getElementById('layout');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    // ============================================================
    // 1. RECOLHER / EXPANDIR (desktop)
    // ============================================================
    const btnRecolher = document.getElementById('btnRecolher');
    const estadoSalvo = localStorage.getItem('sidebar-recolhida') === '1';

    if (estadoSalvo && window.innerWidth > 900) {
        layout?.classList.add('sidebar-recolhida');
    }

    btnRecolher?.addEventListener('click', () => {
        layout.classList.toggle('sidebar-recolhida');
        const recolhida = layout.classList.contains('sidebar-recolhida');
        localStorage.setItem('sidebar-recolhida', recolhida ? '1' : '0');
    });

    // ============================================================
    // 2. MOBILE: abre/fecha sidebar
    // ============================================================
    const btnMenu = document.getElementById('menuToggle');

    function abrirSidebar() {
        sidebar?.classList.add('aberta');
        overlay?.classList.add('ativo');
        document.body.style.overflow = 'hidden';
    }

    function fecharSidebar() {
        sidebar?.classList.remove('aberta');
        overlay?.classList.remove('ativo');
        document.body.style.overflow = '';
    }

    btnMenu?.addEventListener('click', () => {
        if (sidebar.classList.contains('aberta')) {
            fecharSidebar();
        } else {
            abrirSidebar();
        }
    });

    overlay?.addEventListener('click', fecharSidebar);

    // Fecha ao clicar em link no mobile
    sidebar?.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 900) fecharSidebar();
        });
    });

    // Fecha ao redimensionar
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) fecharSidebar();
    });

    // Fecha com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') fecharSidebar();
    });

    // ============================================================
    // 3. DARK MODE (topbar)
    // ============================================================
    const btnTema = document.getElementById('btnTema');
    const iconeClaro  = btnTema?.querySelector('.icone-tema-claro');
    const iconeEscuro = btnTema?.querySelector('.icone-tema-escuro');

    function aplicarIconeTema() {
        const escuro = document.documentElement.getAttribute('data-tema') === 'escuro';
        if (iconeClaro)  iconeClaro.style.display  = escuro ? 'none' : 'flex';
        if (iconeEscuro) iconeEscuro.style.display = escuro ? 'flex' : 'none';
    }

    aplicarIconeTema();

    btnTema?.addEventListener('click', () => {
        const atual = document.documentElement.getAttribute('data-tema');
        const novo  = atual === 'escuro' ? 'claro' : 'escuro';

        document.documentElement.setAttribute('data-tema', novo);
        localStorage.setItem('tema', novo);
        aplicarIconeTema();

        if (typeof toast === 'function') {
            toast(`Tema ${novo === 'escuro' ? 'escuro 🌙' : 'claro ☀️'} ativado`, 'info', 1500);
        }
    });

    // ============================================================
    // 4. NOTIFICAÇÕES (dropdown simples)
    // ============================================================
    const btnNotif = document.getElementById('btnNotif');

    btnNotif?.addEventListener('click', () => {
        if (typeof toast === 'function') {
            toast('🔔 Você tem alertas no menu lateral!', 'aviso', 2500);
        }
        // Destaca a sidebar
        sidebar?.animate(
            [
                { transform: 'translateX(0)' },
                { transform: 'translateX(-6px)' },
                { transform: 'translateX(6px)' },
                { transform: 'translateX(0)' }
            ],
            { duration: 400, easing: 'ease-in-out' }
        );
    });

    // ============================================================
    // 5. BUSCA GLOBAL (Ctrl+K)
    // ============================================================
    const busca = document.getElementById('buscaGlobal');

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (busca) {
                busca.focus();
                busca.select();
            }
        }
    });

    busca?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && busca.value.trim()) {
            const termo = encodeURIComponent(busca.value.trim());
            // Redireciona para uma busca genérica
            // Você pode customizar por página
            const destino = `cadastro_produto.php?busca=${termo}`;
            window.location.href = destino;
        }
    });

    // ============================================================
    // 6. ATALHOS DE TECLADO
    // ============================================================
    document.addEventListener('keydown', (e) => {
        // Alt+N → Nova venda
        if (e.altKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            window.location.href = 'venda.php';
        }
    });

})();