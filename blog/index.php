<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cms.php';
gpms_force_https();
gpms_security_headers();

$query = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['categoria'] ?? ''));
$posts = gpms_published_posts($query, $category);
$allPublished = gpms_published_posts();
$categories = [];
foreach ($allPublished as $publishedPost) {
    $name = trim((string) ($publishedPost['category'] ?? ''));
    if ($name !== '') {
        $categories[$name] = true;
    }
}
$categories = array_keys($categories);
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
$featured = ($query === '' && $category === '' && $posts) ? array_shift($posts) : null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#313131">
  <meta name="description" content="Artigos e análises da GPMS sobre Direito, Psicanálise, relações, patrimônios e famílias.">
  <link rel="canonical" href="<?= gpms_e(gpms_canonical_blog_url()) ?>">
  <link rel="preload" href="<?= gpms_e(gpms_asset_url('fonts/open-sans-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="blog.css">
  <script src="blog.js" defer></script>
  <title>Conteúdo | GPMS</title>
</head>
<body>
  <a class="skip-link" href="#conteudo">Ir para o conteúdo</a>
  <header class="blog-header">
    <div class="shell header-inner">
      <a class="brand" href="https://gpms.com.br/" aria-label="GPMS — página inicial">
        <img src="<?= gpms_e(gpms_asset_url('images/logo.png')) ?>" alt="GPMS — Grupo Massoni Silva" width="184" height="40">
      </a>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="blog-nav">
        <span></span><span></span><span></span>
        <span class="sr-only">Abrir menu</span>
      </button>
      <nav id="blog-nav" aria-label="Navegação principal">
        <a href="https://gpms.com.br/#sobre">Sobre</a>
        <a href="https://gpms.com.br/#atuacao">Atuação</a>
        <a href="https://gpms.com.br/#abordagem">Abordagem</a>
        <a href="https://gpms.com.br/#a-gpms">A GPMS</a>
        <a class="current" href="<?= gpms_e(gpms_blog_url()) ?>">Conteúdo</a>
        <a href="https://gpms.com.br/#contato">Contato</a>
      </nav>
    </div>
  </header>

  <main id="conteudo">
    <section class="blog-hero">
      <div class="shell">
        <p class="eyebrow">GPMS | Conteúdo</p>
        <h1>Conhecimento para decisões<br> mais conscientes.</h1>
        <p class="hero-copy">Análises sobre Direito, Psicanálise e relações humanas aplicadas a conflitos familiares, sucessórios e societários.</p>
      </div>
    </section>

    <section class="archive shell" aria-labelledby="archive-title">
      <div class="archive-heading">
        <div>
          <p class="eyebrow">Artigos e análises</p>
          <h2 id="archive-title"><?= $query || $category ? 'Resultados' : 'Publicações recentes' ?></h2>
        </div>
        <form class="search-form" method="get" action="<?= gpms_e(gpms_blog_url()) ?>" role="search">
          <label for="blog-search">Buscar no conteúdo</label>
          <div>
            <input id="blog-search" type="search" name="q" value="<?= gpms_e($query) ?>" placeholder="Digite um tema">
            <?php if ($category !== ''): ?><input type="hidden" name="categoria" value="<?= gpms_e($category) ?>"><?php endif; ?>
            <button type="submit">Buscar</button>
          </div>
        </form>
      </div>

      <?php if ($categories): ?>
        <nav class="category-nav" aria-label="Filtrar por categoria">
          <a class="<?= $category === '' ? 'active' : '' ?>" href="<?= gpms_e(gpms_blog_url($query ? '?q=' . rawurlencode($query) : '')) ?>">Todos</a>
          <?php foreach ($categories as $name): ?>
            <a class="<?= $category === $name ? 'active' : '' ?>" href="<?= gpms_e(gpms_blog_url('?categoria=' . rawurlencode($name) . ($query ? '&q=' . rawurlencode($query) : ''))) ?>"><?= gpms_e($name) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <?php if ($featured): ?>
        <article class="featured-post">
          <a class="featured-media" href="artigo.php?slug=<?= rawurlencode((string) $featured['slug']) ?>">
            <?php if (!empty($featured['featured_image'])): ?>
              <img src="<?= gpms_e(gpms_image_url((string) $featured['featured_image'])) ?>" alt="Imagem do artigo <?= gpms_e((string) $featured['title']) ?>" loading="eager">
            <?php else: ?>
              <span aria-hidden="true">GPMS</span>
            <?php endif; ?>
          </a>
          <div class="featured-copy">
            <p class="post-meta"><?= gpms_e((string) ($featured['category'] ?: 'Análise')) ?> <span>•</span> <?= gpms_e(gpms_format_date($featured['published_at'] ?? '')) ?></p>
            <h2><a href="artigo.php?slug=<?= rawurlencode((string) $featured['slug']) ?>"><?= gpms_e((string) $featured['title']) ?></a></h2>
            <p><?= gpms_e((string) ($featured['excerpt'] ?: gpms_excerpt((string) $featured['body']))) ?></p>
            <a class="text-link" href="artigo.php?slug=<?= rawurlencode((string) $featured['slug']) ?>">Ler artigo <span aria-hidden="true">→</span></a>
          </div>
        </article>
      <?php endif; ?>

      <?php if ($posts): ?>
        <div class="post-grid">
          <?php foreach ($posts as $post): ?>
            <article class="post-card">
              <a class="post-media" href="artigo.php?slug=<?= rawurlencode((string) $post['slug']) ?>">
                <?php if (!empty($post['featured_image'])): ?>
                  <img src="<?= gpms_e(gpms_image_url((string) $post['featured_image'])) ?>" alt="Imagem do artigo <?= gpms_e((string) $post['title']) ?>" loading="lazy">
                <?php else: ?>
                  <span aria-hidden="true">GPMS</span>
                <?php endif; ?>
              </a>
              <div class="post-card-copy">
                <p class="post-meta"><?= gpms_e((string) ($post['category'] ?: 'Análise')) ?> <span>•</span> <?= gpms_e(gpms_format_date($post['published_at'] ?? '')) ?></p>
                <h3><a href="artigo.php?slug=<?= rawurlencode((string) $post['slug']) ?>"><?= gpms_e((string) $post['title']) ?></a></h3>
                <p><?= gpms_e((string) ($post['excerpt'] ?: gpms_excerpt((string) $post['body']))) ?></p>
                <a class="text-link" href="artigo.php?slug=<?= rawurlencode((string) $post['slug']) ?>">Ler artigo <span aria-hidden="true">→</span></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php elseif (!$featured): ?>
        <div class="empty-state">
          <p class="eyebrow"><?= $query || $category ? 'Nenhum resultado' : 'Novos conteúdos em breve' ?></p>
          <h2><?= $query || $category ? 'Não encontramos artigos com esse filtro.' : 'Estamos preparando as primeiras publicações.' ?></h2>
          <p><?= $query || $category ? 'Tente outra palavra ou visualize todos os artigos.' : 'Acompanhe este espaço para análises da equipe GPMS.' ?></p>
          <?php if ($query || $category): ?><a class="outline-button" href="<?= gpms_e(gpms_blog_url()) ?>">Limpar filtros</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="blog-footer">
    <div class="shell">
      <img src="<?= gpms_e(gpms_asset_url('images/logo.png')) ?>" alt="GPMS" width="184" height="40">
      <p>© 2026 GPMS — Todos os direitos reservados.</p>
      <a href="https://gpms.com.br/#contato">Fale com a GPMS</a>
    </div>
  </footer>
</body>
</html>
