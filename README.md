# Bridge Tracker (GTM & Meta CAPI)

Um plugin modular e leve para WordPress focado na gestão do Google Tag Manager (DataLayer) e disparo de eventos Server-Side via API de Conversões da Meta (CAPI).

## 🚀 Funcionalidades

- **Google Tag Manager:** Injeção limpa e configurável do contêiner GTM no `<head>` e `<body>`.
- **Rastreio de Formulários no DataLayer:**
  - Suporte nativo ao **WPForms** (eventos AJAX).
  - Suporte nativo ao **Contact Form 7** (evento `wpcf7mailsent` / `wpcf7_submit`).
  - Rastreio de formulários HTML genéricos.
- **Privacidade & Segurança:** Criptografia SHA-256 nativa no navegador para e-mails capturados em tempo real antes de enviar ao DataLayer.
- **Meta Conversions API (CAPI - Server-Side):**
  - Disparo de eventos `Lead` via PHP sem depender apenas do navegador do cliente.
  - Hashing de e-mail SHA-256 direto no servidor.
  - Injeção automática de `_fbp` e `_fbc` para alta pontuação de correspondência (Event Match Quality).
- **UTM Persistence System:** Captura e armazena parâmetros de campanha (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`, `fbclid`, `gclid`) em cookies por 30 dias para envio conjunto com as conversões.
- **Painel de Diagnóstico:** Log em tempo real no admin do WP mostrando o payload exato retornado pelos servidores da Meta.

## 🛠️ Instalação

1. Baixe ou clone este repositório na pasta `/wp-content/plugins/` do seu WordPress.
2. Ative o plugin no painel administrativo do WordPress.
3. Acesse **Configurações > Tag Manager** e insira o ID do seu GTM e as credenciais do Pixel e Token da CAPI.
