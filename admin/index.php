<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cms.php';
gpms_force_https();
gpms_security_headers(true);
gpms_session_start();

$pageError = '';
$action = (string) ($_POST['action'] ?? '');
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        gpms_verify_csrf();

        if ($action === 'setup' && !gpms_is_configured()) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{4,40}$/', $username)) {
                throw new RuntimeException('O usuário deve ter de 4 a 40 caracteres, usando letras, números, ponto, hífen ou sublinhado.');
            }
            if (strlen($password) < 12) {
                throw new RuntimeException('Use uma senha com pelo menos 12 caracteres.');
            }
            if (!hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) {
                throw new RuntimeException('A confirmação da senha não coincide.');
            }
            gpms_write_store('auth.php', [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date(DATE_ATOM),
            ]);
            session_regenerate_id(true);
            $_SESSION['gpms_admin'] = true;
            gpms_flash('success', 'Painel configurado. Sua conta de proprietário já está protegida.');
            header('Location: ' . gpms_admin_url(), true, 303);
            exit;
        }

        if ($action === 'login' && gpms_is_configured()) {
            if (gpms_login_blocked($ip)) {
                throw new RuntimeException('Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.');
            }
            $auth = gpms_auth_config();
            $valid = hash_equals((string) $auth['username'], trim((string) ($_POST['username'] ?? '')))
                && password_verify((string) ($_POST['password'] ?? ''), (string) $auth['password_hash']);
            gpms_record_login_attempt($ip, $valid);
            if (!$valid) {
                throw new RuntimeException('Usuário ou senha incorretos.');
            }
            session_regenerate_id(true);
            $_SESSION['gpms_admin'] = true;
            gpms_flash('success', 'Acesso realizado com segurança.');
            header('Location: ' . gpms_admin_url(), true, 303);
            exit;
        }

        gpms_require_admin();

        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
            header('Location: ' . gpms_admin_url(), true, 303);
            exit;
        }

        if ($action === 'save-post') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $existing = $id !== '' ? gpms_post_by_id($id) : null;
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $status = (string) ($_POST['status'] ?? 'draft');
            if ($title === '' || $body === '') {
                throw new RuntimeException('Preencha o título e o conteúdo do artigo.');
            }
            if (!in_array($status, ['draft', 'published'], true)) {
                $status = 'draft';
            }

            $featured = (string) ($existing['featured_image'] ?? '');
            if (!empty($_POST['remove_featured']) && $featured) {
                gpms_delete_image($featured);
                $featured = '';
            }
            if (!empty($_FILES['featured_image']['name'])) {
                $newFeatured = gpms_save_uploaded_image($_FILES['featured_image']);
                gpms_delete_image($featured);
                $featured = $newFeatured;
            }

            $gallery = array_values((array) ($existing['gallery'] ?? []));
            foreach ((array) ($_POST['remove_gallery'] ?? []) as $remove) {
                $remove = basename((string) $remove);
                if (in_array($remove, $gallery, true)) {
                    gpms_delete_image($remove);
                    $gallery = array_values(array_diff($gallery, [$remove]));
                }
            }
            $gallery = array_slice(array_merge($gallery, gpms_uploaded_images('gallery_images')), 0, 8);

            $now = date(DATE_ATOM);
            $publishedAt = (string) ($existing['published_at'] ?? '');
            if ($status === 'published') {
                $requestedDate = trim((string) ($_POST['published_at'] ?? ''));
                $timestamp = $requestedDate !== '' ? strtotime($requestedDate) : false;
                $publishedAt = $timestamp ? date(DATE_ATOM, $timestamp) : ($publishedAt ?: $now);
            }

            $post = [
                'id' => $id ?: bin2hex(random_bytes(8)),
                'title' => $title,
                'slug' => gpms_unique_slug((string) ($_POST['slug'] ?: $title), $id),
                'excerpt' => trim((string) ($_POST['excerpt'] ?? '')) ?: gpms_excerpt($body),
                'category' => trim((string) ($_POST['category'] ?? '')),
                'body' => $body,
                'featured_image' => $featured,
                'gallery' => $gallery,
                'video_url' => trim((string) ($_POST['video_url'] ?? '')),
                'status' => $status,
                'published_at' => $publishedAt,
                'created_at' => (string) ($existing['created_at'] ?? $now),
                'updated_at' => $now,
            ];

            $posts = gpms_posts();
            $replaced = false;
            foreach ($posts as $index => $candidate) {
                if (($candidate['id'] ?? '') === $post['id']) {
                    $posts[$index] = $post;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) {
                $posts[] = $post;
            }
            gpms_save_posts($posts);
            gpms_flash('success', $status === 'published' ? 'Artigo publicado com sucesso.' : 'Rascunho salvo com sucesso.');
            header('Location: ' . gpms_admin_url('?edit=' . rawurlencode($post['id'])), true, 303);
            exit;
        }

        if ($action === 'delete-post') {
            $id = (string) ($_POST['id'] ?? '');
            $posts = gpms_posts();
            foreach ($posts as $post) {
                if (($post['id'] ?? '') === $id) {
                    gpms_delete_image($post['featured_image'] ?? '');
                    foreach ((array) ($post['gallery'] ?? []) as $image) {
                        gpms_delete_image((string) $image);
                    }
                }
            }
            $posts = array_values(array_filter($posts, static function (array $post) use ($id): bool {
                return ($post['id'] ?? '') !== $id;
            }));
            gpms_save_posts($posts);
            gpms_flash('success', 'Artigo excluído.');
            header('Location: ' . gpms_admin_url(), true, 303);
            exit;
        }

        if ($action === 'change-password') {
            $auth = gpms_auth_config();
            $current = (string) ($_POST['current_password'] ?? '');
            $password = (string) ($_POST['new_password'] ?? '');
            if (!password_verify($current, (string) ($auth['password_hash'] ?? ''))) {
                throw new RuntimeException('A senha atual está incorreta.');
            }
            if (strlen($password) < 12) {
                throw new RuntimeException('A nova senha deve ter pelo menos 12 caracteres.');
            }
            if (!hash_equals($password, (string) ($_POST['new_password_confirm'] ?? ''))) {
                throw new RuntimeException('A confirmação da nova senha não coincide.');
            }
            $auth['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $auth['updated_at'] = date(DATE_ATOM);
            gpms_write_store('auth.php', $auth);
            gpms_flash('success', 'Senha atualizada.');
            header('Location: ' . gpms_admin_url('?settings=1'), true, 303);
            exit;
        }
    }
} catch (Throwable $error) {
    $pageError = $error->getMessage();
}

$configured = gpms_is_configured();
$loggedIn = gpms_is_admin();
$csrf = gpms_csrf_token();
$flash = gpms_take_flash();

if (!$configured || !$loggedIn):
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#313131">
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="admin.css">
  <script src="admin.js" defer></script>
  <title><?= $configured ? 'Acesso ao painel' : 'Configurar painel' ?> | GPMS</title>
</head>
<body class="auth-body">
  <main class="auth-shell">
    <a class="auth-brand" href="https://gpms.com.br/">
      <img src="<?= gpms_e(gpms_asset_url('images/logo.png')) ?>" alt="GPMS" width="184" height="40">
    </a>
    <section class="auth-card" aria-labelledby="auth-title">
      <p class="eyebrow"><?= $configured ? 'Área restrita' : 'Primeiro acesso' ?></p>
      <h1 id="auth-title"><?= $configured ? 'Painel de conteúdo' : 'Crie a conta do proprietário' ?></h1>
      <p><?= $configured ? 'Entre com suas credenciais para gerenciar o blog.' : 'Esta etapa aparece somente uma vez. Depois de salvar, o cadastro inicial será bloqueado.' ?></p>
      <?php if ($pageError): ?><div class="alert error" role="alert"><?= gpms_e($pageError) ?></div><?php endif; ?>
      <form method="post" action="<?= gpms_e(gpms_admin_url()) ?>">
        <input type="hidden" name="csrf" value="<?= gpms_e($csrf) ?>">
        <input type="hidden" name="action" value="<?= $configured ? 'login' : 'setup' ?>">
        <label for="username">Usuário</label>
        <input id="username" name="username" type="text" autocomplete="username" required autofocus>
        <label for="password">Senha</label>
        <div class="password-field">
          <input id="password" name="password" type="password" autocomplete="<?= $configured ? 'current-password' : 'new-password' ?>" minlength="<?= $configured ? '1' : '12' ?>" required>
          <button type="button" data-toggle-password="password" aria-label="Mostrar senha">Mostrar</button>
        </div>
        <?php if (!$configured): ?>
          <label for="password-confirm">Confirmar senha</label>
          <input id="password-confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="12" required>
          <small>Use pelo menos 12 caracteres e guarde a senha em local seguro.</small>
        <?php endif; ?>
        <button class="primary-button" type="submit"><?= $configured ? 'Entrar' : 'Proteger o painel' ?></button>
      </form>
    </section>
    <a class="back-site" href="https://gpms.com.br/">← Voltar ao site</a>
  </main>
</body>
</html>
<?php
exit;
endif;

$posts = gpms_posts();
$editId = trim((string) ($_GET['edit'] ?? ''));
$isEditor = isset($_GET['new']) || $editId !== '';
$editPost = $editId ? gpms_post_by_id($editId) : null;
if ($editId && !$editPost) {
    $pageError = 'O artigo solicitado não foi encontrado.';
    $isEditor = false;
}
$drafts = count(array_filter($posts, static function (array $post): bool { return ($post['status'] ?? '') === 'draft'; }));
$published = count($posts) - $drafts;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#313131">
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="admin.css">
  <script src="admin.js" defer></script>
  <title><?= $isEditor ? 'Editar artigo' : 'Painel' ?> | GPMS</title>
</head>
<body class="admin-body">
  <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
  <aside class="sidebar">
    <a class="sidebar-brand" href="<?= gpms_e(gpms_admin_url()) ?>">
      <img src="<?= gpms_e(gpms_asset_url('images/logo.png')) ?>" alt="GPMS" width="184" height="40">
      <span>Painel de conteúdo</span>
    </a>
    <nav aria-label="Painel">
      <a class="<?= !$isEditor && !isset($_GET['settings']) ? 'active' : '' ?>" href="<?= gpms_e(gpms_admin_url()) ?>"><span aria-hidden="true">⌂</span> Visão geral</a>
      <a class="<?= $isEditor ? 'active' : '' ?>" href="<?= gpms_e(gpms_admin_url('?new=1')) ?>"><span aria-hidden="true">＋</span> Novo artigo</a>
      <a href="https://blog.gpms.com.br/" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">↗</span> Ver blog</a>
      <a class="<?= isset($_GET['settings']) ? 'active' : '' ?>" href="<?= gpms_e(gpms_admin_url('?settings=1')) ?>"><span aria-hidden="true">⚙</span> Segurança</a>
    </nav>
    <form class="logout-form" method="post" action="<?= gpms_e(gpms_admin_url()) ?>">
      <input type="hidden" name="csrf" value="<?= gpms_e($csrf) ?>">
      <input type="hidden" name="action" value="logout">
      <button type="submit">Sair do painel</button>
    </form>
  </aside>

  <main class="admin-main" id="main-content">
    <header class="admin-topbar">
      <button class="sidebar-toggle" type="button" aria-expanded="false" aria-controls="admin-sidebar">Menu</button>
      <div>
        <p class="eyebrow"><?= $isEditor ? 'Editor' : (isset($_GET['settings']) ? 'Conta' : 'Conteúdo') ?></p>
        <h1><?= $isEditor ? ($editPost ? 'Editar artigo' : 'Novo artigo') : (isset($_GET['settings']) ? 'Segurança' : 'Visão geral') ?></h1>
      </div>
      <?php if (!$isEditor): ?><a class="primary-button top-action" href="<?= gpms_e(gpms_admin_url('?new=1')) ?>">Novo artigo</a><?php endif; ?>
    </header>

    <?php if ($flash): ?><div class="alert <?= gpms_e((string) $flash['type']) ?>" role="status"><?= gpms_e((string) $flash['message']) ?></div><?php endif; ?>
    <?php if ($pageError): ?><div class="alert error" role="alert"><?= gpms_e($pageError) ?></div><?php endif; ?>

    <?php if ($isEditor): ?>
      <?php $formPost = $editPost ?: ['id'=>'','title'=>'','slug'=>'','excerpt'=>'','category'=>'','body'=>'','featured_image'=>'','gallery'=>[],'video_url'=>'','status'=>'draft','published_at'=>'']; ?>
      <form class="editor-form" method="post" action="<?= gpms_e(gpms_admin_url()) ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= gpms_e($csrf) ?>">
        <input type="hidden" name="action" value="save-post">
        <input type="hidden" name="id" value="<?= gpms_e((string) $formPost['id']) ?>">
        <div class="editor-layout">
          <section class="panel editor-primary">
            <div class="field">
              <label for="title">Título do artigo</label>
              <input id="title" name="title" type="text" value="<?= gpms_e((string) $formPost['title']) ?>" maxlength="160" required>
            </div>
            <div class="field">
              <label for="excerpt">Resumo</label>
              <textarea id="excerpt" name="excerpt" rows="3" maxlength="320" placeholder="Breve apresentação exibida na lista do blog."><?= gpms_e((string) $formPost['excerpt']) ?></textarea>
              <small>Se ficar vazio, o painel cria um resumo a partir do texto.</small>
            </div>
            <div class="field">
              <div class="label-row">
                <label for="body">Conteúdo</label>
                <div class="editor-tools" aria-label="Formatação">
                  <button type="button" data-prefix="## ">Título de seção</button>
                  <button type="button" data-prefix="- ">Lista</button>
                </div>
              </div>
              <textarea id="body" name="body" rows="22" required placeholder="Escreva o artigo aqui. Use uma linha em branco entre os parágrafos."><?= gpms_e((string) $formPost['body']) ?></textarea>
              <small>Use “Título de seção” para subtítulos e “Lista” para itens. O texto é protegido contra códigos maliciosos.</small>
            </div>
          </section>

          <aside class="editor-aside">
            <section class="panel">
              <h2>Publicação</h2>
              <div class="field">
                <label for="status">Situação</label>
                <select id="status" name="status">
                  <option value="draft" <?= $formPost['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                  <option value="published" <?= $formPost['status'] === 'published' ? 'selected' : '' ?>>Publicado</option>
                </select>
              </div>
              <div class="field">
                <label for="published-at">Data de publicação</label>
                <input id="published-at" name="published_at" type="datetime-local" value="<?= !empty($formPost['published_at']) ? gpms_e(date('Y-m-d\TH:i', strtotime((string) $formPost['published_at']))) : '' ?>">
              </div>
              <button class="primary-button full-button" type="submit"><?= $formPost['status'] === 'published' ? 'Atualizar artigo' : 'Salvar artigo' ?></button>
              <?php if (!empty($formPost['slug']) && $formPost['status'] === 'published'): ?>
                <a class="secondary-button full-button" href="https://blog.gpms.com.br/artigo.php?slug=<?= rawurlencode((string) $formPost['slug']) ?>" target="_blank" rel="noopener noreferrer">Visualizar publicado ↗</a>
              <?php endif; ?>
            </section>

            <section class="panel">
              <h2>Organização</h2>
              <div class="field">
                <label for="category">Categoria</label>
                <input id="category" name="category" type="text" value="<?= gpms_e((string) $formPost['category']) ?>" maxlength="60" placeholder="Ex.: Direito de Família">
              </div>
              <div class="field">
                <label for="slug">Endereço do artigo</label>
                <input id="slug" name="slug" type="text" value="<?= gpms_e((string) $formPost['slug']) ?>" maxlength="180" placeholder="Gerado pelo título">
              </div>
            </section>

            <section class="panel">
              <h2>Imagem principal</h2>
              <?php if (!empty($formPost['featured_image'])): ?>
                <img class="image-preview" src="<?= gpms_e(gpms_image_url((string) $formPost['featured_image'])) ?>" alt="">
                <label class="check-row"><input type="checkbox" name="remove_featured" value="1"> Remover imagem atual</label>
              <?php endif; ?>
              <div class="field">
                <label for="featured-image">Enviar imagem</label>
                <input id="featured-image" name="featured_image" type="file" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG ou WebP, até 5 MB.</small>
              </div>
            </section>

            <section class="panel">
              <h2>Vídeo e galeria</h2>
              <div class="field">
                <label for="video-url">Link do vídeo</label>
                <input id="video-url" name="video_url" type="url" value="<?= gpms_e((string) $formPost['video_url']) ?>" placeholder="YouTube ou Vimeo">
              </div>
              <?php if (!empty($formPost['gallery'])): ?>
                <div class="gallery-manager">
                  <?php foreach ((array) $formPost['gallery'] as $image): ?>
                    <label>
                      <img src="<?= gpms_e(gpms_image_url((string) $image)) ?>" alt="">
                      <span><input type="checkbox" name="remove_gallery[]" value="<?= gpms_e((string) $image) ?>"> Remover</span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="field">
                <label for="gallery-images">Adicionar imagens</label>
                <input id="gallery-images" name="gallery_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                <small>Até 8 imagens na galeria.</small>
              </div>
            </section>
          </aside>
        </div>
      </form>

    <?php elseif (isset($_GET['settings'])): ?>
      <section class="panel settings-panel">
        <h2>Alterar senha</h2>
        <p>Use uma senha exclusiva com pelo menos 12 caracteres.</p>
        <form method="post" action="<?= gpms_e(gpms_admin_url()) ?>">
          <input type="hidden" name="csrf" value="<?= gpms_e($csrf) ?>">
          <input type="hidden" name="action" value="change-password">
          <div class="field"><label for="current-password">Senha atual</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required></div>
          <div class="field"><label for="new-password">Nova senha</label><input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="12" required></div>
          <div class="field"><label for="new-password-confirm">Confirmar nova senha</label><input id="new-password-confirm" name="new_password_confirm" type="password" autocomplete="new-password" minlength="12" required></div>
          <button class="primary-button" type="submit">Atualizar senha</button>
        </form>
      </section>

    <?php else: ?>
      <section class="stats-grid" aria-label="Resumo">
        <article><span>Total</span><strong><?= count($posts) ?></strong><p>artigos cadastrados</p></article>
        <article><span>Publicados</span><strong><?= $published ?></strong><p>visíveis no blog</p></article>
        <article><span>Rascunhos</span><strong><?= $drafts ?></strong><p>aguardando revisão</p></article>
      </section>

      <section class="panel posts-panel">
        <div class="panel-heading">
          <div><p class="eyebrow">Biblioteca</p><h2>Seus artigos</h2></div>
          <a class="secondary-button" href="<?= gpms_e(gpms_admin_url('?new=1')) ?>">Criar artigo</a>
        </div>
        <?php if ($posts): ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Artigo</th><th>Status</th><th>Atualização</th><th><span class="sr-only">Ações</span></th></tr></thead>
              <tbody>
                <?php foreach ($posts as $post): ?>
                  <tr>
                    <td data-label="Artigo"><strong><?= gpms_e((string) $post['title']) ?></strong><small><?= gpms_e((string) ($post['category'] ?: 'Sem categoria')) ?></small></td>
                    <td data-label="Status"><span class="status <?= gpms_e((string) $post['status']) ?>"><?= $post['status'] === 'published' ? 'Publicado' : 'Rascunho' ?></span></td>
                    <td data-label="Atualização"><?= gpms_e(gpms_format_date($post['updated_at'] ?? '')) ?></td>
                    <td class="row-actions">
                      <a href="<?= gpms_e(gpms_admin_url('?edit=' . rawurlencode((string) $post['id']))) ?>">Editar</a>
                      <form method="post" action="<?= gpms_e(gpms_admin_url()) ?>" data-confirm-delete>
                        <input type="hidden" name="csrf" value="<?= gpms_e($csrf) ?>">
                        <input type="hidden" name="action" value="delete-post">
                        <input type="hidden" name="id" value="<?= gpms_e((string) $post['id']) ?>">
                        <button type="submit">Excluir</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty"><h3>Comece pelo primeiro artigo.</h3><p>Crie um rascunho, adicione imagem e vídeo e publique quando estiver pronto.</p><a class="primary-button" href="<?= gpms_e(gpms_admin_url('?new=1')) ?>">Criar primeiro artigo</a></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
