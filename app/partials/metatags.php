<?php
/**
 * Metaetiquetas del documento: básicas + SEO (canonical, Open Graph, Twitter).
 *
 * Recibe del layout: $meta (FrontMatter de la página) y $page (contexto).
 * $site (config de site.yml) está disponible globalmente.
 *
 * Imagen social: campo `image` del FrontMatter de la página, o `ogImage` global
 * de site.yml como fallback; se convierte a URL absoluta.
 */

$title = $meta['title'] ?? $site['title'] ?? '';
$description = $meta['description'] ?? $site['description'] ?? '';

// URL canónica absoluta de la página actual (sin query string).
$uri = $page['uri'] ?? '';
$canonical = ($site['url'] ?? '') . ($uri === '' || $uri === 'index' ? '' : '/' . $uri);

// Tipo Open Graph: "article" para posts, "website" para el resto.
$ogType = ($page['layout'] ?? 'page') === 'post' ? 'article' : 'website';

// Imagen social: FrontMatter `image` → `ogImage` global → ninguna. Absolutizada.
$image = $meta['image'] ?? $site['ogImage'] ?? '';
if ($image !== '' && !preg_match('#^https?://#i', $image)) {
  $image = ($site['url'] ?? '') . '/' . ltrim($image, '/');
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $e($title) ?></title>
<meta name="description" content="<?= $e($description) ?>">
<link rel="canonical" href="<?= $e($canonical) ?>">
<link rel="icon" type="image/png" href="<?= $e(($site['url'] ?? '') . '/img/favicon.png') ?>">
<link rel="alternate" type="application/rss+xml" title="<?= $e($site['title'] ?? '') ?>" href="<?= $e(($site['url'] ?? '') . '/feed.xml') ?>">


<!-- Open Graph (Facebook, LinkedIn, WhatsApp, ...) -->
<meta property="og:type" content="<?= $e($ogType) ?>">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($description) ?>">
<meta property="og:url" content="<?= $e($canonical) ?>">
<meta property="og:site_name" content="<?= $e($site['title'] ?? '') ?>">
<?php if ($image !== ''): ?>
<meta property="og:image" content="<?= $e($image) ?>">
<?php endif; ?>
<?php if ($ogType === 'article' && !empty($meta['date']) && ($ts = strtotime((string) $meta['date']))): ?>
<meta property="article:published_time" content="<?= $e(date('c', $ts)) ?>">
<?php endif; ?>

<!-- Twitter Cards -->
<meta name="twitter:card" content="<?= $image !== '' ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= $e($title) ?>">
<meta name="twitter:description" content="<?= $e($description) ?>">
<?php if ($image !== ''): ?>
<meta name="twitter:image" content="<?= $e($image) ?>">
<?php endif; ?>
<?php if (!empty($site['twitter'])): ?>
<meta name="twitter:site" content="<?= $e($site['twitter']) ?>">
<?php endif; ?>
