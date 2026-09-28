
/* ============================================================
 * 1. Normalização de telefone
 * ============================================================ */
function formatPhoneE164(rawPhone) {
    if (!rawPhone) return '';
    var digits = String(rawPhone).replace(/\D/g, '');
    if (!digits) return '';

    var ddi = (typeof bridge_settings !== 'undefined' && bridge_settings.default_ddi)
        ? bridge_settings.default_ddi : '55';

    if (digits.length >= 10 && digits.length <= 11) {
        return '+' + ddi + digits;
    }
    return '+' + digits;
}

// Telefone pronto para hash (SEM o "+", como Google/Meta exigem)
function phoneForHash(rawPhone) {
    return formatPhoneE164(rawPhone).replace(/^\+/, '');
}

/* ============================================================
 * 2. SHA-256 (Web Crypto)
 * ============================================================ */
async function getSha256(str) {
    if (!str) return '';
    var buffer = new TextEncoder().encode(str);
    var hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
    var hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
}

/* ============================================================
 * 3. Gera um event_id único (para deduplicação Meta Pixel x CAPI)
 * ============================================================ */
function bridgeGenerateEventId(prefix) {
    prefix = prefix || 'evt';
    return prefix + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
}

/* ============================================================
 * 4. Constrói o objeto de dados completo para o dataLayer
 *    (formato unificado: GTM / GA4 / Google Ads / Meta)
 * ============================================================ */
async function bridgeBuildEventData(options) {
    options = options || {};

    var rawEmail = options.email || '';
    var rawPhone = options.phone || '';
    var formName = options.form_name || 'Formulário';
    var formId = options.form_id || '';
    var value = options.value || null;

    var cleanEmail = rawEmail ? rawEmail.trim().toLowerCase() : '';
    var cleanPhone = formatPhoneE164(rawPhone);   // E.164 com "+"
    var phoneHashIn = phoneForHash(rawPhone);      // sem "+"

    var hashedEmail = await getSha256(cleanEmail);
    var hashedPhone = await getSha256(phoneHashIn);

    var sendRaw = (typeof bridge_settings !== 'undefined' && bridge_settings.disable_hash === '1');
    var eventId = bridgeGenerateEventId('lead');

    // ---------- BLOCO 1: user_data (GA4 / GTM genérico) ----------
    var userData = {};
    if (hashedEmail) userData.sha256_email_address = hashedEmail;
    if (hashedPhone) userData.sha256_phone_number = hashedPhone;

    if (sendRaw) {
        if (cleanEmail) userData.email_address_raw = cleanEmail;
        if (cleanPhone) userData.phone_number_raw = cleanPhone;
    }

    // ---------- BLOCO 2: meta (formato Meta CAPI / Pixel) ----------
    var meta = {};
    if (hashedEmail) meta.em = [hashedEmail];
    if (hashedPhone) meta.ph = [hashedPhone];

    if (sendRaw) {
        if (cleanEmail) meta.email_raw = cleanEmail;
        if (cleanPhone) meta.phone_raw = cleanPhone;
    }

    // ---------- BLOCO 3: google_ads (Enhanced Conversions) ----------
    var googleAds = {};
    if (hashedEmail) googleAds.email = hashedEmail;
    if (hashedPhone) googleAds.phone_number = hashedPhone;
    if (cleanEmail) googleAds.email_address = cleanEmail;
    if (cleanPhone) googleAds.phone_number_e164 = cleanPhone;

    // ---------- PUSH ----------
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        // -------- metadados comuns --------
        event: 'generate_lead',
        event_id: eventId,
        form_name: formName,
        form_id: formId,
        form_destination: options.form_destination || 'website',
        value: value,

        // -------- dados por plataforma --------
        user_data: userData,
        meta: meta,
        google_ads: googleAds
    });

    return eventId;
}