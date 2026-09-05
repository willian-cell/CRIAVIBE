/**
 * CriaVibe — auth.js
 * Verifica autenticação via /api/auth/me.php e expõe currentUser global
 */

let currentUser = null;

async function checkAuth(redirect = true) {
  try {
    const data = await API.get('/auth/me.php');
    if (data.status === 'ok') {
      currentUser = data.usuario;
      montarElementosAdmin(currentUser);
      return currentUser;
    }
  } catch {}
  if (redirect) window.location.href = '/entrar.html';
  return null;
}

/**
 * Insere, em qualquer página do painel, o atalho para a administração do
 * sistema (somente para o administrador) e a faixa de aviso quando a sessão
 * foi aberta em nome de outro fotógrafo. Fica aqui para não repetir o mesmo
 * bloco de HTML em cada página.
 */
function montarElementosAdmin(u) {
  if (!u) return;

  const naPaginaAdmin = window.location.pathname.endsWith('/admin.html');

  if (u.is_admin && !naPaginaAdmin && !document.getElementById('link-admin')) {
    const sidebar = document.querySelector('.painel-sidebar');
    if (sidebar) {
      const link = document.createElement('a');
      link.id = 'link-admin';
      link.href = '/admin.html';
      link.className = 'sidebar-link';
      link.innerHTML = '<i class="fa-solid fa-shield-halved"></i> Fotógrafos e galerias existentes';
      sidebar.appendChild(link);
    }
  }

  if (u.impersonando && !document.getElementById('faixa-impersonacao')) {
    const faixa = document.createElement('div');
    faixa.id = 'faixa-impersonacao';
    faixa.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:9000;'
      + 'background:#7c2d12;color:#fff;padding:10px 16px;display:flex;align-items:center;'
      + 'justify-content:center;gap:14px;flex-wrap:wrap;font-size:.84rem;font-weight:600;'
      + 'box-shadow:0 -4px 20px rgba(0,0,0,.25)';
    faixa.innerHTML = `
      <span><i class="fa-solid fa-user-secret"></i>
        Você está navegando como <strong>${String(u.nome || '').replace(/[<>&]/g, '')}</strong>.
        Tudo que fizer é gravado como ação desta conta.</span>
      <button onclick="voltarParaAdmin()" style="background:#fff;color:#7c2d12;border:none;
        border-radius:6px;padding:6px 14px;font-family:inherit;font-weight:700;
        font-size:.8rem;cursor:pointer">Voltar para o admin</button>`;
    document.body.appendChild(faixa);
  }
}

async function voltarParaAdmin() {
  try {
    await API.post('/admin/voltar_admin.php', {});
    window.location.href = '/admin.html';
  } catch (e) {
    alert(e.message);
  }
}

async function requireAuth() {
  const u = await checkAuth(true);
  return u;
}

async function logout() {
  await API.post('/auth/logout.php', {});
  window.location.href = '/entrar.html';
}
