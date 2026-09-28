# Bridge Tracker

Plugin WordPress modular para rastreamento de conversões via **Google Tag Manager**, **Meta Conversions API (CAPI)** e **Google Analytics 4 (Measurement Protocol)**.

---

## 📋 Índice

- [Funcionalidades](#-funcionalidades)
- [Instalação](#-instalação)
- [Configuração](#️-configuração)
- [Módulo JS Customizado](#-módulo-js-customizado)
- [Formato do dataLayer](#-formato-do-datalayer)
- [Estrutura de arquivos](#-estrutura-de-arquivos)
- [Como testar](#-como-testar)
- [Requisitos](#-requisitos)
- [Licença](#-licença)

---

## 🚀 Funcionalidades

### Google Tag Manager

- Injeção do contêiner GTM no `<head>` e `<body>` (com fallback para `wp_footer`).
- `dataLayer` unificado que atende **GTM, GA4, Google Ads e Meta** em um único `push`.

### Rastreio de Formulários

- **Formulários HTML genéricos** — captura o `submit` nativo.
- **Contact Form 7** — via evento `wpcf7mailsent`.
- **WPForms** — via ponte jQuery → `CustomEvent`.
- **Elementor Pro Forms** — via ponte jQuery → `CustomEvent`.
- **JS Customizado** — textarea no painel para colar qualquer código próprio, com acesso a todas as funções utilitárias do plugin.

### Privacidade & Segurança

- Hash **SHA-256** executado no navegador (Web Crypto API) antes de empurrar ao dataLayer.
- Hash **SHA-256** executado no servidor (PHP) antes de enviar à Meta CAPI e GA4 MP.
- Telefone normalizado em **E.164** e hasheado **sem o `+`** (padrão Google/Meta).
- Modo **RAW** opcional (debug) — envia dados com e sem hash simultaneamente.
- Aviso automático no painel quando o site está em HTTP (hash client-side não funciona sem HTTPS).

### Meta Conversions API (Server-Side)

- Disparo de eventos `Lead` via PHP sem depender do navegador.
- Injeção automática de `_fbp`, `_fbc` e `external_id` para alta pontuação de Event Match Quality.
- Suporte a `PageView` server-side com deduplicação por `event_id`.
- Painel de diagnóstico mostrando payload e resposta da Meta em tempo real.

### Google Analytics 4 (Measurement Protocol)

- Envio de eventos server-side via `mp/collect`.
- Captura do `client_id` do GA4 (com fallback para cookie próprio).
- Suporte a `page_view` server-side com deduplicação client-side.
- Painel de diagnóstico mostrando payload e resposta do GA4.

### UTM Persistence System

- Captura e armazena `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`, `fbclid` e `gclid` em cookies por **30 dias**.
- Reenvia junto com as conversões (Meta CAPI e GA4 MP).

---

## 🛠️ Instalação

1. Baixe ou clone este repositório em `/wp-content/plugins/`.
2. Ative o plugin em **Plugins → Plugins instalados**.
3. Acesse **Configurações → Bridge Tracker**.

---

## ⚙️ Configuração

### Google Tag Manager (obrigatório para scripts de eventos)

- **ID do Contêiner GTM** — ex.: `GTM-XXXXXXX`.
- **Quais formulários rastrear** — marque os que deseja.
- **DDI padrão** — usado quando o telefone vem sem código de país (default `55`).
- **Modo Debug (RAW)** — envia dados com **e** sem hash para depuração.

### Meta (Facebook) — opcional

- **Pixel ID**
- **Token da CAPI**
- **Código de Teste** (opcional — ex.: `TEST30195`)
- **PageView Server-Side** — ativar/desativar

### Google Analytics 4 — opcional

- **Measurement ID** — ex.: `G-XXXXXXXXXX`
- **API Secret** — gerado no painel do GA4
- **PageView Server-Side (MP)** — ativar/desativar

---

## 🧩 Módulo JS Customizado

Ao marcar **"JS Customizado"**, o plugin:

1. Carrega o `gtm-helpers.js` (funções utilitárias).
2. **Não** carrega o `gtm-events.js` automaticamente (a menos que "Genérico" esteja marcado).
3. Injeta o JavaScript do textarea no `wp_footer` com **prioridade 25** — depois de todos os outros scripts.

O textarea vem **pré-preenchido** com o conteúdo do `gtm-events.js` como template inicial. Há um botão **"↩ Restaurar template genérico"** para reverter edições.

### Funções disponíveis

| Função                          | Descrição                                                        |
| ------------------------------- | ---------------------------------------------------------------- |
| `bridgeBuildEventData(options)` | Empurra o evento completo no dataLayer (GTM + Meta + Google Ads) |
| `getSha256(str)`                | Gera hash SHA-256 (Web Crypto)                                   |
| `formatPhoneE164(raw)`          | Normaliza telefone para E.164 (com `+`)                          |
| `phoneForHash(raw)`             | Retorna telefone sem `+` (para hash)                             |
| `bridgeGenerateEventId(prefix)` | Gera um `event_id` único                                         |

### Exemplo — escutar evento customizado

```js
document.addEventListener("meuEventoCustom", function (e) {
  bridgeBuildEventData({
    email: e.detail.email,
    phone: e.detail.phone,
    form_name: "Formulário Newsletter",
    form_id: "newsletter-1",
  });
});
```

### Exemplo — rastrear cliques em WhatsApp

```js
document.querySelectorAll('a[href*="wa.me"]').forEach(function (btn) {
  btn.addEventListener("click", function () {
    bridgeBuildEventData({
      form_name: "Clique WhatsApp",
      form_id: "cta-whatsapp",
    });
  });
});
```

> ⚠️ **Aviso de duplicação:** se **"Genérico"** e **"Customizado"** estiverem ambos marcados, e o JS customizado já contiver a lógica do genérico (foi pré-preenchido com o template), os eventos serão duplicados no dataLayer. O painel exibe um aviso quando isso acontece.

---

## 📦 Formato do dataLayer

Cada `generate_lead` empurra **três blocos** para o dataLayer — um por plataforma:

```js
window.dataLayer.push({
  // -------- metadados comuns --------
  event: "generate_lead",
  event_id: "lead_1737517200000_a1b2c3d4",
  form_name: "Contact Form 7",
  form_id: "42",
  form_destination: "website",

  // -------- GA4 / GTM genérico --------
  user_data: {
    sha256_email_address: "9b3f1b...",
    sha256_phone_number: "c1e0d7...",
    // (quando RAW ativado)
    // email_address_raw: 'joao@exemplo.com',
    // phone_number_raw:  '+5511987654321'
  },

  // -------- Meta CAPI / Pixel --------
  meta: {
    em: ["9b3f1b..."],
    ph: ["c1e0d7..."],
    // (quando RAW ativado)
    // email_raw: 'joao@exemplo.com',
    // phone_raw: '+5511987654321'
  },

  // -------- Google Ads Enhanced Conversions --------
  google_ads: {
    email: "9b3f1b...",
    phone_number: "c1e0d7...",
    email_address: "joao@exemplo.com",
    phone_number_e164: "+5511987654321",
  },
});
```

### Como usar cada bloco no GTM

- **GA4 → Evento `generate_lead`**: mapeie `form_name`, `form_id`, `event_id`.
- **Google Ads → Conversão Aprimorada**: leia `google_ads.email` e `google_ads.phone_number`. Use `event_id` como Transaction ID.
- **Meta → Pixel + CAPI**: leia `meta.em[0]` e `meta.ph[0]`. Use `event_id` no `eventID` do `fbq('track', ...)`.

---

## 📂 Estrutura de arquivos

```
bridge-tracker/
├── bridge-tracker.php              # Bootstrap do plugin
├── modulo-gtm.php                  # GTM + enqueue + painel + JS customizado
├── modulo-facebook.php             # Pixel client-side + Meta CAPI server-side
├── modulo-google-analytics.php     # gtag client-side + GA4 MP server-side
├── README.md
└── js/
    ├── gtm-helpers.js              # Funções utilitárias + bridgeBuildEventData()
    ├── gtm-events.js               # Listener de formulários HTML genéricos
    ├── gtm-cf7.js                  # Listener do Contact Form 7
    ├── gtm-wpforms.js              # Listener do WPForms
    └── gtm-elementor.js            # Listener do Elementor Pro
```

### Ordem de carregamento no frontend

```
wp_head (0)     → GTM container
wp_head (1)     → GA4 gtag
wp_head (2)     → Meta Pixel
enqueue (10)    → gtm-helpers.js
enqueue (10)    → gtm-events.js / gtm-cf7.js / gtm-wpforms.js / gtm-elementor.js
wp_footer (20)  → ponte jQuery → CustomEvent
wp_footer (25)  → JS customizado do textarea
```

---

## 🧪 Como testar

### 1. Verificar se as funções utilitárias estão carregadas

No Console do navegador (F12):

```js
typeof bridgeBuildEventData; // "function"
typeof getSha256; // "function"
typeof phoneForHash; // "function"
typeof bridgeGenerateEventId; // "function"
```

### 2. Teste manual de evento

```js
bridgeBuildEventData({
  email: "teste@exemplo.com",
  phone: "11987654321",
  form_name: "Teste Manual",
  form_id: "test-1",
});

// Verificar o último push no dataLayer:
window.dataLayer[window.dataLayer.length - 1];
```

### 3. Verificar no GTM Preview

1. Abra [tagassistant.google.com](https://tagassistant.google.com).
2. Conecte à URL do site.
3. Envie um formulário.
4. Confirme o evento `generate_lead` com os três blocos (`user_data`, `meta`, `google_ads`).

### 4. Verificar hash do telefone (deve ser SEM `+`)

```bash
echo -n "5511987654321" | sha256sum
```

Compare com o valor em `meta.ph[0]` no dataLayer.

---

## 🔧 Requisitos

- WordPress 5.8+
- PHP 7.4+
- HTTPS em produção (Web Crypto API exige contexto seguro)
- Um contêiner GTM ativo (para carregar os scripts de eventos)

---

## 📝 Licença

GPL v2 ou superior.

---

## 👤 Autor

**Raphael** — Bridge Tracker © 2026
