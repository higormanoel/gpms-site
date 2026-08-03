<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/cms.php';
gpms_force_https();
gpms_security_headers();

$slug = trim((string) ($_GET['slug'] ?? ''));
$post = gpms_post_by_slug($slug);
if (!$post) {
    http_response_code(404);
    $post = [
        'title' => 'Conteúdo não encontrado',
        'excerpt' => 'Este artigo não está disponível ou ainda não foi publicado.',
        'body' => '',
        'category' => '',
        'published_at' => '',
        'featured_image' => '',
        'gallery' => [],
        'video_url' => '',
        'slug' => '',
    ];
}
$isNotFound = http_response_code() === 404;
$embed = gpms_video_embed($post['video_url'] ?? '');
$related = [];
if (!$isNotFound) {
    foreach (gpms_published_posts() as $candidate) {
        if (($candidate['id'] ?? '') !== ($post['id'] ?? '') && count($related) < 3) {
            $related[] = $candidate;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#313131">
  <meta name="description" content="<?= gpms_e((string) ($post['excerpt'] ?: gpms_excerpt((string) $post['body']))) ?>">
  <?php if (!$isNotFound): ?><link rel="canonical" href="<?= gpms_e(gpms_canonical_blog_url('artigo.php?slug=' . rawurlencode((string) $post['slug']))) ?>"><?php endif; ?>
  <link rel="stylesheet" href="blog.css">
  <script src="blog.js" defer></script>
  <title><?= gpms_e((string) $post['title']) ?> | GPMS</title>
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
    <article class="article">
      <header class="article-header shell">
        <a class="back-link" href="<?= gpms_e(gpms_blog_url()) ?>">← Voltar para conteúdos</a>
        <?php if (!$isNotFound): ?><p class="post-meta"><?= gpms_e((string) ($post['category'] ?: 'Análise')) ?> <span>•</span> <?= gpms_e(gpms_format_date($post['published_at'] ?? '')) ?></p><?php endif; ?>
        <h1><?= gpms_e((string) $post['title']) ?></h1>
        <p class="article-lead"><?= gpms_e((string) $post['excerpt']) ?></p>
      </header>

      <?php if (!$isNotFound && !empty($post['featured_image'])): ?>
        <figure class="article-cover shell">
          <img src="<?= gpms_e(gpms_image_url((string) $post['featured_image'])) ?>" alt="Imagem do artigo <?= gpms_e((string) $post['title']) ?>">
        </figure>
      <?php endif; ?>

      <?php if ($isNotFound): ?>
        <div class="article-body"><p><a class="outline-button" href="<?= gpms_e(gpms_blog_url()) ?>">Ver todos os artigos</a></p></div>
      <?php else: ?>
        <div class="article-body">
          <?= gpms_render_body((string) $post['body']) ?>
          <?php if ($embed): ?>
            <div class="video-frame">
              <iframe src="<?= gpms_e($embed) ?>" title="Vídeo relacionado ao artigo" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
          <?php elseif (!empty($post['video_url'])): ?>
            <p><a class="text-link" href="<?= gpms_e((string) $post['video_url']) ?>" target="_blank" rel="noopener noreferrer">Assistir ao vídeo relacionado →</a></p>
          <?php endif; ?>
          <?php if (!empty($post['gallery'])): ?>
            <div class="article-gallery">
              <?php foreach ((array) $post['gallery'] as $image): ?>
                <img src="<?= gpms_e(gpms_image_url((string) $image)) ?>" alt="" loading="lazy">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </article>

    <?php if ($related): ?>
      <section class="related shell" aria-labelledby="related-title">
        <p class="eyebrow">Continue lendo</p>
        <h2 id="related-title">Outras publicações</h2>
        <div class="related-grid">
          <?php foreach ($related as $item): ?>
            <article>
              <p class="post-meta"><?= gpms_e((string) ($item['category'] ?: 'Análise')) ?></p>
              <h3><a href="artigo.php?slug=<?= rawurlencode((string) $item['slug']) ?>"><?= gpms_e((string) $item['title']) ?></a></h3>
              <a class="text-link" href="artigo.php?slug=<?= rawurlencode((string) $item['slug']) ?>">Ler artigo →</a>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
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
