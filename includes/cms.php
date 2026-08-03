<?php
declare(strict_types=1);

date_default_timezone_set('America/Fortaleza');

define('GPMS_ROOT', dirname(__DIR__));
define('GPMS_STORAGE', GPMS_ROOT . '/storage');
define('GPMS_UPLOADS', GPMS_ROOT . '/blog/uploads');

function gpms_host(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'gpms.com.br'));
    return preg_replace('/:\d+$/', '', $host) ?: 'gpms.com.br';
}

function gpms_is_local(): bool
{
    return in_array(gpms_host(), ['localhost', '127.0.0.1'], true);
}

function gpms_is_https(): bool
{
    $forwarded = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on'
        || $forwarded === 'https';
}

function gpms_force_https(): void
{
    if (gpms_is_local() || gpms_is_https()) {
        return;
    }

    $host = gpms_host();
    if (!preg_match('/^(?:www\.|blog\.|admin\.)?gpms\.com\.br$/', $host)) {
        $host = 'gpms.com.br';
    }

    header('Location: https://' . $host . (string) ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

function gpms_security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY');
    if (gpms_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $frames = $admin ? "'none'" : 'https://www.youtube-nocookie.com https://player.vimeo.com';
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "img-src 'self' https://gpms.com.br https://blog.gpms.com.br data:; " .
        "style-src 'self'; script-src 'self'; frame-src {$frames}; " .
        "font-src 'self' https://gpms.com.br; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );
}

function gpms_blog_base(): string
{
    return strpos(gpms_host(), 'blog.') === 0 ? '' : '/blog';
}

function gpms_admin_base(): string
{
    return strpos(gpms_host(), 'admin.') === 0 ? '' : '/admin';
}

function gpms_blog_url(string $path = ''): string
{
    return gpms_blog_base() . '/' . ltrim($path, '/');
}

function gpms_admin_url(string $path = ''): string
{
    return gpms_admin_base() . '/' . ltrim($path, '/');
}

function gpms_canonical_blog_url(string $path = ''): string
{
    return 'https://blog.gpms.com.br/' . ltrim($path, '/');
}

function gpms_asset_url(string $path): string
{
    if (gpms_is_local()) {
        return '/assets/' . ltrim($path, '/');
    }
    return 'https://gpms.com.br/gpms-release-20260803/assets/' . ltrim($path, '/');
}

function gpms_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function gpms_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('gpms_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !gpms_is_local(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function gpms_ensure_directories(): void
{
    foreach ([GPMS_STORAGE, GPMS_UPLOADS] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar a pasta de dados.');
        }
    }
}

function gpms_store_path(string $name): string
{
    if (!preg_match('/^[a-z0-9-]+\.php$/', $name)) {
        throw new InvalidArgumentException('Nome de arquivo inválido.');
    }
    return GPMS_STORAGE . '/' . $name;
}

function gpms_read_store(string $name, array $default = []): array
{
    $path = gpms_store_path($name);
    if (!is_file($path)) {
        return $default;
    }

    $data = include $path;
    return is_array($data) ? $data : $default;
}

function gpms_write_store(string $name, array $data): void
{
    gpms_ensure_directories();
    $path = gpms_store_path($name);
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(6));
    $contents = "<?php\nreturn " . var_export($data, true) . ";\n";

    if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Não foi possível salvar os dados. Verifique a permissão da pasta storage.');
    }
    @chmod($path, 0640);
}

function gpms_seed_posts(): array
{
    return [
        [
            'id' => 'gpms-seed-escuta-estrategia',
            'title' => 'Conflitos familiares de alta complexidade: por que a escuta vem antes da estratégia',
            'slug' => 'conflitos-familiares-escuta-antes-da-estrategia',
            'excerpt' => 'Antes de propor caminhos jurídicos, é preciso compreender vínculos, silêncios, expectativas e riscos que sustentam o impasse.',
            'category' => 'Mediação de conflitos',
            'body' => "Conflitos familiares que envolvem patrimônio, empresas ou sucessão raramente são explicados apenas pelos fatos visíveis. Uma divergência sobre administração, partilha ou tomada de decisão pode carregar histórias antigas, papéis familiares rígidos e expectativas que nunca foram formuladas com clareza.\n\nPor isso, começar pela solução jurídica pronta costuma ser insuficiente. O primeiro trabalho é construir uma leitura do cenário: quem decide, quem se sente excluído, quais vínculos precisam ser preservados e quais riscos já ameaçam pessoas, patrimônio ou reputação.\n\n## Escutar não é adiar a decisão\n\nEscuta qualificada não significa prolongar indefinidamente uma conversa. Ela organiza o problema antes que as partes invistam energia em respostas para a pergunta errada. Ao separar posições declaradas de interesses reais, torna-se possível identificar os pontos negociáveis e os limites que precisam de proteção imediata.\n\nEm situações de alta complexidade, essa etapa também reduz ruídos. Cada pessoa passa a compreender o que está em jogo para as demais, mesmo quando não existe concordância. A negociação deixa de ser uma disputa de versões e ganha critérios mais objetivos.\n\n## O que uma leitura interdisciplinar acrescenta\n\nDireito, estratégia e compreensão das relações humanas oferecem ângulos diferentes do mesmo impasse. A análise jurídica delimita direitos, deveres e riscos. A leitura das relações revela padrões de comunicação, alianças e resistências. A estratégia conecta essas informações a decisões possíveis no tempo certo.\n\nEssa integração ajuda a responder perguntas essenciais:\n\n- O conflito exige contenção imediata ou há espaço para construção gradual?\n- Quais decisões são reversíveis e quais podem comprometer o patrimônio ou a relação?\n- Quem precisa participar da conversa para que um acordo seja sustentável?\n- O que deve permanecer confidencial para proteger pessoas e reputações?\n\n## Da compreensão ao caminho possível\n\nDepois de compreender a estrutura do conflito, a estratégia pode ser desenhada com mais precisão. Em alguns casos, o melhor caminho será uma mediação. Em outros, será necessário combinar negociação, reorganização patrimonial, protocolo familiar ou medidas jurídicas específicas.\n\nA qualidade da solução depende menos de uma fórmula universal e mais da coerência entre o caminho escolhido e a realidade daquela família. Escutar primeiro é o que permite agir com firmeza sem perder de vista os vínculos e o legado envolvidos.",
            'featured_image' => 'asset:images/sobre.png',
            'gallery' => [],
            'video_url' => '',
            'status' => 'published',
            'published_at' => '2026-08-01T09:00:00-03:00',
            'created_at' => '2026-08-01T09:00:00-03:00',
            'updated_at' => '2026-08-01T09:00:00-03:00',
        ],
        [
            'id' => 'gpms-seed-sucessao-legado',
            'title' => 'Sucessão patrimonial: decisões que preservam vínculos e legados',
            'slug' => 'sucessao-patrimonial-decisoes-que-preservam-vinculos-e-legados',
            'excerpt' => 'Planejar a sucessão é organizar responsabilidades, expectativas e continuidade - não apenas distribuir bens.',
            'category' => 'Sucessão e patrimônio',
            'body' => "A sucessão patrimonial costuma ser lembrada quando uma urgência aparece: uma mudança de geração, uma doença, a necessidade de reorganizar uma empresa ou um conflito entre herdeiros. Quando o planejamento começa apenas nesse momento, decisões importantes passam a ser tomadas sob pressão.\n\nPlanejar com antecedência permite transformar um tema sensível em um processo de continuidade. Isso envolve documentos e estruturas jurídicas, mas também conversas sobre responsabilidade, participação, autonomia e futuro.\n\n## Patrimônio e significado caminham juntos\n\nUma empresa familiar, um imóvel ou uma coleção podem ter valores econômicos claros e, ao mesmo tempo, representar reconhecimento, pertencimento ou memória. Ignorar essa dimensão simbólica pode produzir resistências difíceis de compreender por uma análise exclusivamente financeira.\n\nUma condução cuidadosa identifica quais bens exigem tratamento técnico, quais decisões precisam ser compartilhadas e quais expectativas devem ser explicitadas. A transparência adequada reduz interpretações equivocadas e ajuda a distinguir afeto de responsabilidade patrimonial.\n\n## Perguntas que precisam vir antes dos instrumentos\n\nAntes de escolher holdings, doações, testamentos ou acordos, convém responder:\n\n- Qual legado a família deseja preservar?\n- Quem está preparado para administrar e quem prefere outra forma de participação?\n- Como serão tomadas decisões em momentos de divergência?\n- Que proteção cada geração necessita?\n- Quais informações podem ser compartilhadas e em que momento?\n\nOs instrumentos jurídicos ganham consistência quando respondem a objetivos compreendidos por todos os envolvidos. Sem essa base, estruturas formalmente corretas podem alimentar novos conflitos.\n\n## Governança como prática cotidiana\n\nSucessão não termina com a assinatura de documentos. Regras de governança, critérios de entrada na gestão, fóruns de conversa e mecanismos para tratar impasses ajudam a sustentar as escolhas ao longo do tempo.\n\nO melhor planejamento é aquele que protege o patrimônio sem transformar relações familiares em uma sequência de obrigações incompreendidas. Quando estratégia, direito e relações humanas são considerados em conjunto, a sucessão deixa de ser apenas transferência e passa a ser continuidade consciente.",
            'featured_image' => 'asset:images/abordagem.png',
            'gallery' => [],
            'video_url' => '',
            'status' => 'published',
            'published_at' => '2026-07-24T10:30:00-03:00',
            'created_at' => '2026-07-24T10:30:00-03:00',
            'updated_at' => '2026-07-24T10:30:00-03:00',
        ],
        [
            'id' => 'gpms-seed-conflito-societario',
            'title' => 'Quando o conflito societário deixa de ser apenas jurídico',
            'slug' => 'quando-o-conflito-societario-deixa-de-ser-apenas-juridico',
            'excerpt' => 'Divergências entre sócios podem comprometer decisões, equipes e reputações antes mesmo de chegar ao processo judicial.',
            'category' => 'Governança societária',
            'body' => "Conflitos entre sócios nem sempre começam com uma infração contratual. Muitas vezes surgem de expectativas diferentes sobre crescimento, poder de decisão, dedicação ao negócio ou distribuição de resultados. Quando essas diferenças não encontram um espaço adequado de tratamento, o problema se espalha pela organização.\n\nA empresa sente os efeitos antes que o conflito seja formalizado. Decisões são adiadas, lideranças recebem orientações contraditórias e informações deixam de circular. Clientes, colaboradores e parceiros percebem a instabilidade, mesmo sem conhecer sua origem.\n\n## O contrato é essencial, mas não explica tudo\n\nAcordos societários e documentos de governança definem direitos e procedimentos. Eles são indispensáveis para delimitar responsabilidades e oferecer segurança. Ainda assim, a aplicação desses instrumentos ocorre entre pessoas com histórias, interesses e percepções próprias.\n\nUma estratégia consistente precisa considerar simultaneamente:\n\n- O que os documentos permitem e exigem;\n- O impacto financeiro de cada alternativa;\n- A continuidade operacional da empresa;\n- A comunicação com equipes e partes relacionadas;\n- A preservação da reputação dos envolvidos.\n\n## Sinais de que o impasse exige outra abordagem\n\nReuniões que repetem os mesmos argumentos, vetos usados como forma de pressão, decisões importantes tomadas fora dos fóruns adequados e exposição do conflito para funcionários são sinais de alerta. Nesses casos, insistir apenas na discussão técnica pode aprofundar a ruptura.\n\nA mediação ou a negociação estruturada cria um ambiente em que os interesses podem ser reorganizados sem perder os limites jurídicos. O objetivo não é evitar decisões difíceis, mas tomá-las com método, confidencialidade e clareza sobre as consequências.\n\n## Resolver também é proteger a operação\n\nUma solução societária deve permitir que a empresa continue funcionando durante e depois do conflito. Isso pode envolver revisão de governança, redefinição de funções, compra de participação, reorganização societária ou construção de uma saída negociada.\n\nQuando o impasse é compreendido em toda a sua complexidade, o direito deixa de atuar isoladamente e passa a integrar uma estratégia de proteção do negócio, das pessoas e da reputação construída ao longo do tempo.",
            'featured_image' => 'asset:images/atuacao.png',
            'gallery' => [],
            'video_url' => '',
            'status' => 'published',
            'published_at' => '2026-07-16T08:45:00-03:00',
            'created_at' => '2026-07-16T08:45:00-03:00',
            'updated_at' => '2026-07-16T08:45:00-03:00',
        ],
        [
            'id' => 'gpms-seed-direito-psicanalise',
            'title' => 'Direito e psicanálise aplicada: uma leitura interdisciplinar do impasse',
            'slug' => 'direito-e-psicanalise-aplicada-uma-leitura-interdisciplinar-do-impasse',
            'excerpt' => 'Compreender a dimensão humana do conflito permite construir decisões juridicamente seguras e mais sustentáveis.',
            'category' => 'Abordagem interdisciplinar',
            'body' => "Em conflitos patrimoniais, familiares e societários, o que é dito nem sempre corresponde a tudo o que está sendo disputado. Uma posição aparentemente objetiva pode carregar necessidade de reconhecimento, medo de perda, ressentimento ou dificuldade de aceitar uma mudança de papel.\n\nO direito organiza limites, responsabilidades e possibilidades de solução. A psicanálise aplicada contribui para compreender como as pessoas se posicionam diante desses limites e por que determinados impasses resistem mesmo quando existe uma saída técnica disponível.\n\n## Uma contribuição prática\n\nAplicar uma leitura psicanalítica não significa transformar uma negociação em atendimento clínico. Trata-se de observar a dinâmica da fala, das repetições, dos silêncios e das relações de poder para conduzir melhor o processo de decisão.\n\nEssa compreensão pode ajudar a identificar:\n\n- Temas que não devem ser tratados em uma reunião conjunta logo no início;\n- Mensagens que precisam ser reformuladas para não ampliar resistências;\n- Pessoas cuja participação é essencial para legitimar uma decisão;\n- Momentos em que insistir na negociação aumenta o risco;\n- Condições mínimas para que um acordo seja realmente cumprido.\n\n## Segurança jurídica e sustentabilidade relacional\n\nUma solução juridicamente válida pode fracassar na prática se não considerar a forma como será recebida e executada. Da mesma maneira, um entendimento relacional sem estrutura jurídica pode deixar patrimônio e responsabilidades desprotegidos.\n\nO trabalho interdisciplinar aproxima essas duas exigências. A estratégia é construída para ser clara nos documentos, possível na realidade e compatível com a preservação dos vínculos que ainda importam.\n\n## Cada conflito possui sua própria estrutura\n\nNão existe um roteiro único para situações de alta complexidade. Algumas exigem rapidez e contenção. Outras pedem tempo para que interesses possam ser nomeados e reorganizados.\n\nA integração entre direito e psicanálise aplicada amplia a capacidade de leitura sem retirar objetividade da condução. Ao contrário: permite decidir com mais informação, reduzir movimentos impulsivos e construir caminhos que protejam pessoas, patrimônios e reputações.",
            'featured_image' => 'asset:images/fundadores.png',
            'gallery' => [],
            'video_url' => '',
            'status' => 'published',
            'published_at' => '2026-07-08T14:00:00-03:00',
            'created_at' => '2026-07-08T14:00:00-03:00',
            'updated_at' => '2026-07-08T14:00:00-03:00',
        ],
    ];
}

function gpms_posts(): array
{
    $postsPath = gpms_store_path('posts.php');
    $posts = is_file($postsPath) ? gpms_read_store('posts.php') : gpms_seed_posts();
    usort($posts, static function (array $a, array $b): int {
        return strcmp((string) ($b['published_at'] ?? $b['updated_at'] ?? ''), (string) ($a['published_at'] ?? $a['updated_at'] ?? ''));
    });
    return $posts;
}

function gpms_save_posts(array $posts): void
{
    gpms_write_store('posts.php', array_values($posts));
}

function gpms_post_by_id(string $id): ?array
{
    foreach (gpms_posts() as $post) {
        if (($post['id'] ?? '') === $id) {
            return $post;
        }
    }
    return null;
}

function gpms_post_by_slug(string $slug): ?array
{
    foreach (gpms_posts() as $post) {
        if (($post['slug'] ?? '') === $slug && ($post['status'] ?? '') === 'published') {
            return $post;
        }
    }
    return null;
}

function gpms_published_posts(string $query = '', string $category = ''): array
{
    $query = gpms_lower(trim($query));
    return array_values(array_filter(gpms_posts(), static function (array $post) use ($query, $category): bool {
        if (($post['status'] ?? '') !== 'published') {
            return false;
        }
        if ($category !== '' && ($post['category'] ?? '') !== $category) {
            return false;
        }
        if ($query === '') {
            return true;
        }
        $haystack = gpms_lower(implode(' ', [
            (string) ($post['title'] ?? ''),
            (string) ($post['excerpt'] ?? ''),
            (string) ($post['category'] ?? ''),
            (string) ($post['body'] ?? ''),
        ]));
        return strpos($haystack, $query) !== false;
    }));
}

function gpms_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function gpms_substr(string $value, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
}

function gpms_slugify(string $value): string
{
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = strtolower($converted !== false ? $converted : $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'artigo';
}

function gpms_unique_slug(string $requested, string $id = ''): string
{
    $base = gpms_slugify($requested);
    $slug = $base;
    $suffix = 2;
    $posts = gpms_posts();
    while (array_filter($posts, static function (array $post) use ($slug, $id): bool {
        return ($post['slug'] ?? '') === $slug && ($post['id'] ?? '') !== $id;
    })) {
        $slug = $base . '-' . $suffix++;
    }
    return $slug;
}

function gpms_excerpt(string $body, int $length = 180): string
{
    $plain = trim(preg_replace('/\s+/', ' ', preg_replace('/^(?:##\s+|-\s+)/m', '', $body) ?? $body) ?? $body);
    return gpms_substr($plain, 0, $length) . (strlen($plain) > $length ? '…' : '');
}

function gpms_format_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $months = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $time = strtotime($date);
    return date('j', $time) . ' de ' . $months[(int) date('n', $time)] . ' de ' . date('Y', $time);
}

function gpms_render_body(string $body): string
{
    $lines = preg_split('/\R/', trim($body)) ?: [];
    $html = '';
    $paragraph = [];
    $listOpen = false;

    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if ($paragraph) {
            $html .= '<p>' . gpms_e(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $flushParagraph();
            if ($listOpen) {
                $html .= '</ul>';
                $listOpen = false;
            }
            continue;
        }
        if (strpos($trimmed, '## ') === 0) {
            $flushParagraph();
            if ($listOpen) {
                $html .= '</ul>';
                $listOpen = false;
            }
            $html .= '<h2>' . gpms_e(substr($trimmed, 3)) . '</h2>';
            continue;
        }
        if (strpos($trimmed, '- ') === 0) {
            $flushParagraph();
            if (!$listOpen) {
                $html .= '<ul>';
                $listOpen = true;
            }
            $html .= '<li>' . gpms_e(substr($trimmed, 2)) . '</li>';
            continue;
        }
        if ($listOpen) {
            $html .= '</ul>';
            $listOpen = false;
        }
        $paragraph[] = $trimmed;
    }
    $flushParagraph();
    if ($listOpen) {
        $html .= '</ul>';
    }
    return $html;
}

function gpms_image_url(?string $filename): string
{
    if (!$filename) {
        return '';
    }
    if (strpos($filename, 'asset:') === 0) {
        $asset = ltrim(substr($filename, 6), '/');
        return gpms_asset_url($asset);
    }
    $path = 'uploads/' . rawurlencode(basename($filename));
    if (strpos(gpms_host(), 'admin.') === 0) {
        return gpms_canonical_blog_url($path);
    }
    return gpms_blog_url($path);
}

function gpms_save_uploaded_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('A imagem não pôde ser enviada.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Cada imagem deve ter no máximo 5 MB.');
    }

    $temporary = (string) ($file['tmp_name'] ?? '');
    $imageInfo = @getimagesize($temporary);
    $mime = $imageInfo['mime'] ?? '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Use imagens JPG, PNG ou WebP.');
    }

    gpms_ensure_directories();
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporary, GPMS_UPLOADS . '/' . $filename)) {
        throw new RuntimeException('Não foi possível gravar a imagem enviada.');
    }
    return $filename;
}

function gpms_uploaded_images(string $field, int $limit = 6): array
{
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return [];
    }

    $saved = [];
    $count = min(count($_FILES[$field]['name']), $limit);
    for ($index = 0; $index < $count; $index++) {
        if ((int) $_FILES[$field]['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $saved[] = gpms_save_uploaded_image([
            'name' => $_FILES[$field]['name'][$index],
            'type' => $_FILES[$field]['type'][$index],
            'tmp_name' => $_FILES[$field]['tmp_name'][$index],
            'error' => $_FILES[$field]['error'][$index],
            'size' => $_FILES[$field]['size'][$index],
        ]);
    }
    return $saved;
}

function gpms_delete_image(?string $filename): void
{
    if (!$filename || strpos($filename, 'asset:') === 0) {
        return;
    }
    $path = GPMS_UPLOADS . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function gpms_video_embed(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = trim((string) ($parts['path'] ?? ''), '/');
    $id = '';
    $src = '';

    if ($host === 'youtu.be') {
        $id = explode('/', $path)[0] ?? '';
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = (string) ($query['v'] ?? '');
        if (!$id && strpos($path, 'shorts/') === 0) {
            $id = substr($path, 7);
        }
    }
    if ($id && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
        $src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id);
    } elseif (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('/^\d+$/', $path)) {
        $src = 'https://player.vimeo.com/video/' . $path;
    }
    return $src;
}

function gpms_csrf_token(): string
{
    gpms_session_start();
    if (empty($_SESSION['gpms_csrf'])) {
        $_SESSION['gpms_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['gpms_csrf'];
}

function gpms_verify_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals(gpms_csrf_token(), $token)) {
        throw new RuntimeException('A sessão expirou. Atualize a página e tente novamente.');
    }
}

function gpms_auth_config(): array
{
    return gpms_read_store('auth.php');
}

function gpms_is_configured(): bool
{
    $auth = gpms_auth_config();
    return !empty($auth['username']) && !empty($auth['password_hash']);
}

function gpms_is_admin(): bool
{
    gpms_session_start();
    return !empty($_SESSION['gpms_admin']) && $_SESSION['gpms_admin'] === true;
}

function gpms_require_admin(): void
{
    if (!gpms_is_admin()) {
        header('Location: ' . gpms_admin_url(), true, 303);
        exit;
    }
}

function gpms_flash(string $type, string $message): void
{
    gpms_session_start();
    $_SESSION['gpms_flash'] = ['type' => $type, 'message' => $message];
}

function gpms_take_flash(): ?array
{
    gpms_session_start();
    $flash = $_SESSION['gpms_flash'] ?? null;
    unset($_SESSION['gpms_flash']);
    return is_array($flash) ? $flash : null;
}

function gpms_login_blocked(string $ip): bool
{
    $attempts = gpms_read_store('login-attempts.php');
    $key = hash('sha256', $ip);
    $record = $attempts[$key] ?? [];
    return (int) ($record['count'] ?? 0) >= 5 && (int) ($record['last'] ?? 0) > time() - 900;
}

function gpms_record_login_attempt(string $ip, bool $success): void
{
    $attempts = gpms_read_store('login-attempts.php');
    $key = hash('sha256', $ip);
    foreach ($attempts as $attemptKey => $record) {
        if ((int) ($record['last'] ?? 0) < time() - 86400) {
            unset($attempts[$attemptKey]);
        }
    }
    if ($success) {
        unset($attempts[$key]);
    } else {
        $attempts[$key] = [
            'count' => (int) ($attempts[$key]['count'] ?? 0) + 1,
            'last' => time(),
        ];
    }
    gpms_write_store('login-attempts.php', $attempts);
}
