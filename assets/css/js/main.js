/* ============================================================
   PADARIA DO GIL - JavaScript Principal
   Versão: 2.0
   ============================================================ */

(function () {
    'use strict';

    // ============================================================
    // 1. UTILITÁRIOS GLOBAIS
    // ============================================================
    const PadariaGil = {
        // Formata número como moeda brasileira
        formatarMoeda(valor) {
            return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        // Formata data BR
        formatarData(data) {
            const d = new Date(data);
            return d.toLocaleDateString('pt-BR');
        },

        // Debounce (para buscas em tempo real)
        debounce(fn, delay = 300) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        }
    };

    // Expõe globalmente para uso em outras páginas
    window.PadariaGil = PadariaGil;

    // ============================================================
    // 2. NOTIFICAÇÕES TOAST (substitui alert())
    // ============================================================
    function criarContainerToast() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    /**
     * Mostra uma notificação toast
     * @param {string} mensagem
     * @param {string} tipo - 'sucesso' | 'erro' | 'aviso' | 'info'
     * @param {number} duracao - ms (padrão 3500)
     */
    function toast(mensagem, tipo = 'info', duracao = 3500) {
        const container = criarContainerToast();

        const icones = {
            sucesso: '✅',
            erro: '❌',
            aviso: '⚠️',
            info: 'ℹ️'
        };

        const el = document.createElement('div');
        el.className = `toast toast-${tipo}`;
        el.innerHTML = `
            <span class="toast-icone">${icones[tipo] || ''}</span>
            <span class="toast-msg">${mensagem}</span>
            <button class="toast-fechar" aria-label="Fechar">×</button>
        `;

        // Botão fechar
        el.querySelector('.toast-fechar').addEventListener('click', () => {
            el.classList.add('toast-saindo');
            setTimeout(() => el.remove(), 300);
        });

        container.appendChild(el);

        // Anima entrada
        requestAnimationFrame(() => el.classList.add('toast-visivel'));

        // Auto remove
        if (duracao > 0) {
            setTimeout(() => {
                if (!el.parentNode) return;
                el.classList.add('toast-saindo');
                setTimeout(() => el.remove(), 300);
            }, duracao);
        }
    }

    window.toast = toast;

    // ============================================================
    // 3. MODAL DE CONFIRMAÇÃO (substitui confirm())
    // ============================================================
    /**
     * Mostra um modal de confirmação
     * @param {object} opcoes - { titulo, mensagem, tipoBotao, textoBotao }
     * @returns {Promise<boolean>}
     */
    function confirmar(opcoes = {}) {
        const {
            titulo = 'Confirmação',
            mensagem = 'Tem certeza?',
            tipoBotao = 'perigo',
            textoBotao = 'Confirmar'
        } = opcoes;

        return new Promise((resolve) => {
            // Overlay
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.innerHTML = `
                <div class="modal-box">
                    <h3 class="modal-titulo">${titulo}</h3>
                    <p class="modal-mensagem">${mensagem}</p>
                    <div class="modal-botoes">
                        <button class="btn-modal btn-cancelar">Cancelar</button>
                        <button class="btn-modal btn-${tipoBotao}">${textoBotao}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('modal-visivel'));

            const fechar = (resultado) => {
                overlay.classList.remove('modal-visivel');
                setTimeout(() => {
                    overlay.remove();
                    resolve(resultado);
                }, 250);
            };

            overlay.querySelector('.btn-cancelar').addEventListener('click', () => fechar(false));
            overlay.querySelector('.btn-' + tipoBotao).addEventListener('click', () => fechar(true));
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) fechar(false);
            });
            document.addEventListener('keydown', function esc(e) {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', esc);
                    fechar(false);
                }
            });
        });
    }

    window.confirmar = confirmar;

    // ============================================================
    // 4. AUTO-CONVERTER MENSAGENS PHP EM TOASTS
    // ============================================================
    // Qualquer <p class="msg msg-ok">, .msg-erro, etc. vira toast
    function converterMensagens() {
        document.querySelectorAll('.msg').forEach((el) => {
            const texto = el.textContent.trim();
            if (!texto) return;

            let tipo = 'info';
            if (el.classList.contains('msg-ok') || el.classList.contains('sucesso')) tipo = 'sucesso';
            else if (el.classList.contains('msg-erro') || el.classList.contains('erro')) tipo = 'erro';
            else if (el.classList.contains('msg-aviso')) tipo = 'aviso';

            toast(texto, tipo);
            el.remove();
        });
    }

    // ============================================================
    // 5. LOADING EM FORMULÁRIOS (evita duplo clique)
    // ============================================================
    function ativarLoadingForms() {
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', function () {
                const btn = form.querySelector('button[type="submit"]');
                if (!btn || btn.disabled) return;

                // Guarda texto original
                const textoOriginal = btn.innerHTML;

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner"></span> Processando...';

                // Reativa após 8s (fallback)
                setTimeout(() => {
                    if (btn.disabled) {
                        btn.disabled = false;
                        btn.innerHTML = textoOriginal;
                    }
                }, 8000);
            });
        });
    }

    // ============================================================
    // 6. SIDEBAR MOBILE (botão hambúrguer)
    // ============================================================
    function ativarSidebarMobile() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        // Cria botão hambúrguer se não existir
        if (!document.querySelector('.menu-toggle')) {
            const btn = document.createElement('button');
            btn.className = 'menu-toggle';
            btn.setAttribute('aria-label', 'Abrir menu');
            btn.innerHTML = '☰';
            document.body.appendChild(btn);

            btn.addEventListener('click', () => {
                sidebar.classList.toggle('sidebar-aberta');
            });

            // Fecha ao clicar em um link (mobile)
            sidebar.querySelectorAll('a').forEach((a) => {
                a.addEventListener('click', () => {
                    sidebar.classList.remove('sidebar-aberta');
                });
            });
        }
    }

    // ============================================================
    // 7. BOTÃO VOLTAR AO TOPO
    // ============================================================
    function ativarBotaoTopo() {
        const btn = document.createElement('button');
        btn.className = 'btn-topo';
        btn.innerHTML = '↑';
        btn.setAttribute('aria-label', 'Voltar ao topo');
        btn.style.display = 'none';
        document.body.appendChild(btn);

        window.addEventListener('scroll', () => {
            btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
        });

        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ============================================================
    // 8. BUSCA EM TABELAS (filtro em tempo real)
    // ============================================================
    function ativarBuscaTabelas() {
        document.querySelectorAll('table[data-busca]').forEach((tabela) => {
            const inputId = 'busca-' + Math.random().toString(36).slice(2, 9);
            const input = document.createElement('input');
            input.type = 'search';
            input.placeholder = '🔍 Buscar na tabela...';
            input.className = 'input-busca';
            input.id = inputId;

            tabela.parentNode.insertBefore(input, tabela);

            input.addEventListener('input', PadariaGil.debounce(function () {
                const termo = this.value.toLowerCase().trim();
                const linhas = tabela.querySelectorAll('tbody tr');
                let visiveis = 0;

                linhas.forEach((tr) => {
                    const texto = tr.textContent.toLowerCase();
                    const mostrar = !termo || texto.includes(termo);
                    tr.style.display = mostrar ? '' : 'none';
                    if (mostrar) visiveis++;
                });

                // Mensagem "nenhum resultado"
                let vazio = tabela.parentNode.querySelector('.tabela-vazia');
                if (visiveis === 0 && termo) {
                    if (!vazio) {
                        vazio = document.createElement('p');
                        vazio.className = 'msg msg-aviso tabela-vazia';
                        vazio.textContent = '🔍 Nenhum resultado encontrado.';
                        tabela.parentNode.insertBefore(vazio, tabela.nextSibling);
                    }
                } else if (vazio) {
                    vazio.remove();
                }
            }, 200));
        });
    }

    // ============================================================
    // 9. CONFIRMAR EXCLUSÃO (links/botões com data-confirmar)
    // ============================================================
    function ativarConfirmacoes() {
        document.querySelectorAll('[data-confirmar]').forEach((el) => {
            el.addEventListener('click', async function (e) {
                e.preventDefault();
                const msg = this.dataset.confirmar || 'Tem certeza?';
                const ok = await confirmar({
                    titulo: '⚠️ Confirmação',
                    mensagem: msg,
                    textoBotao: 'Sim, continuar',
                    tipoBotao: 'perigo'
                });

                if (ok) {
                    if (this.tagName === 'A' && this.href) {
                        window.location.href = this.href;
                    } else if (this.dataset.acao) {
                        window.location.href = this.dataset.acao;
                    }
                }
            });
        });
    }

    // ============================================================
    // 10. DARK MODE
    // ============================================================
    function ativarDarkMode() {
        // Aplica tema salvo
        const temaSalvo = localStorage.getItem('tema') || 'claro';
        if (temaSalvo === 'escuro') {
            document.documentElement.setAttribute('data-tema', 'escuro');
        }

        // Cria botão flutuante de alternância
        const btn = document.createElement('button');
        btn.className = 'btn-tema';
        btn.setAttribute('aria-label', 'Alternar tema');
        btn.innerHTML = temaSalvo === 'escuro' ? '☀️' : '🌙';
        document.body.appendChild(btn);

        btn.addEventListener('click', () => {
            const atual = document.documentElement.getAttribute('data-tema');
            const novo = atual === 'escuro' ? 'claro' : 'escuro';

            document.documentElement.setAttribute('data-tema', novo);
            localStorage.setItem('tema', novo);
            btn.innerHTML = novo === 'escuro' ? '☀️' : '🌙';

            toast(`Tema ${novo === 'escuro' ? 'escuro' : 'claro'} ativado`, 'info', 1800);
        });
    }

    // ============================================================
    // 11. ANIMAÇÃO DE CARDS (contador) — bônus
    // ============================================================
    function animarCards() {
        document.querySelectorAll('.card p').forEach((el) => {
            const texto = el.textContent;
            const valor = parseFloat(texto.replace(/[^\d,-]/g, '').replace(',', '.'));
            if (isNaN(valor) || valor === 0) return;

            const prefixo = texto.includes('R$') ? 'R$ ' : '';
            const duracao = 900;
            const inicio = performance.now();

            function animar(agora) {
                const progresso = Math.min((agora - inicio) / duracao, 1);
                const atual = valor * (1 - Math.pow(1 - progresso, 3)); // easeOutCubic

                el.textContent = prefixo + atual.toLocaleString('pt-BR', {
                    minimumFractionDigits: prefixo ? 2 : 0,
                    maximumFractionDigits: prefixo ? 2 : 0
                });

                if (progresso < 1) requestAnimationFrame(animar);
            }

            requestAnimationFrame(animar);
        });
    }

    // ============================================================
    // 12. FADE-IN NAS TABELAS/LINHAS
    // ============================================================
    function animarLinhasTabela() {
        document.querySelectorAll('tbody tr').forEach((tr, i) => {
            tr.style.animation = `fadeInUp .35s ease ${i * 0.03}s both`;
        });
    }

    // ============================================================
    // 13. SIDEBAR: MARCAR LINK ATIVO
    // ============================================================
    function marcarLinkAtivo() {
        const paginaAtual = window.location.pathname.split('/').pop() || 'index.php';
        document.querySelectorAll('.sidebar nav a').forEach((a) => {
            const href = a.getAttribute('href');
            if (href === paginaAtual) {
                a.classList.add('link-ativo');
            }
        });
    }

    // ============================================================
    // 14. ATALHOS DE TECLADO
    // ============================================================
    function atalhosTeclado() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+K → busca
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const busca = document.querySelector('.input-busca');
                if (busca) busca.focus();
            }
        });
    }

    // ============================================================
    // 15. INICIALIZAÇÃO
    // ============================================================
    function init() {
        converterMensagens();
        ativarLoadingForms();
        ativarSidebarMobile();
        ativarBotaoTopo();
        ativarBuscaTabelas();
        ativarConfirmacoes();
        ativarDarkMode();
        animarCards();
        animarLinhasTabela();
        marcarLinkAtivo();
        atalhosTeclado();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();