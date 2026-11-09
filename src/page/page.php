<?php

use franciscoblancojn\wordpress_utils\FWUPage;
use franciscoblancojn\wordpress_utils\FWURespond;

echo FWUPage::css();

// Una pestaña por archivo en src/page/sections/<key>.php.
$TAGS = [
    [
        'key' => 'categorias',
        'title' => 'Categorías vacías',
    ],
    [
        'key' => 'paginas',
        'title' => 'Páginas huérfanas',
    ],
    [
        'key' => 'css',
        'title' => 'Excluir CSS',
    ],
    [
        'key' => 'js',
        'title' => 'Excluir JS',
    ],
];
$keys = wp_list_pluck($TAGS, 'key');
$requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$defaultTag = in_array($requested, $keys, true) ? $requested : $TAGS[0]['key'];
$respond = get_transient('rnd_respond');

?>
<div id="page-<?= esc_attr(RND_KEY) ?>" class="wrap rnd-page">
    <h1>Rendimiento <span class="rnd-version">v<?= esc_html(RND_get_version()) ?></span></h1>
    <?php
    if ($respond) {
        delete_transient('rnd_respond');
        FWURespond::render($respond);
    }
    ?>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <?php foreach ($TAGS as $value) : ?>
        <div class="tab-content <?= $value['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= esc_attr($value['key']) ?>">
            <?php require RND_DIR . 'src/page/sections/' . $value['key'] . '.php'; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php

echo FWUPage::js(RND_KEY);
