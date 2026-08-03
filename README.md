# Site GPMS

Site institucional e blog próprio do Grupo Massoni Silva, preparados para publicação direta na KingHost.

## Publicação

- O domínio abre por `index.php`, que força HTTPS e entrega o site institucional.
- A raiz pública do domínio deve apontar para a raiz deste repositório.
- Não há comando de build nem dependências externas.
- As pastas `assets/`, `includes/`, `blog/`, `admin/` e `storage/` devem ser publicadas.

## Blog e painel

- Blog público: `/blog/`
- Painel do proprietário: `/admin/`
- Subdomínios configurados: `https://blog.gpms.com.br/` e `https://admin.gpms.com.br/`
- No primeiro acesso ao painel, crie o usuário e uma senha de pelo menos 12 caracteres.
- Faça essa primeira configuração imediatamente após a publicação; depois dela, a tela de criação da conta é desativada.
- Os dados são gravados em `storage/` e as imagens em `blog/uploads/`. Essas pastas precisam de permissão de escrita para o PHP.
- Os arquivos de conteúdo e credenciais gerados no servidor não entram no Git, para não serem apagados por uma futura publicação.

## Tecnologias

- PHP 7.4+
- HTML5, CSS3 e JavaScript puro
- Open Sans hospedada localmente
- Vídeo MP4 em H.264/AAC

## Formulário

O formulário envia a mensagem diretamente para `gpms@gpms.com.br` por PHP, com validação no cliente e no servidor, campo anti-spam invisível e limite de frequência por IP. O visitante não depende de aplicativo de email.
