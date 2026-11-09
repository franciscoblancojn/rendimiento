<?php

$config = (new RND_USE_DATA_CONFIG())->get();
$report = RND_CRAWLER::report();
$status = RND_CRAWLER::status();
$selected_types = array_map('strval', (array) $config['post_types']);
$post_types = get_post_types(['public' => true], 'objects');
?>
<div class="rnd-section">
    <p class="rnd-intro">
        El análisis empieza en la home y sigue los enlaces internos de las páginas que encuentra. Al terminar,
        compara lo visitado con el contenido publicado: lo que no aparece enlazado desde la home queda como
        <strong>huérfano</strong>. No usa el sitemap, así que también detecta contenido que solo está ahí.
    </p>

    <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="rnd-settings">
        <input type="hidden" name="action" value="<?= esc_attr(RND_API_SETTINGS::ACTION) ?>">
        <input type="hidden" name="rnd_tab" value="paginas">
        <?php wp_nonce_field(RND_API_SETTINGS::ACTION); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="rnd-start-url">URL de inicio</label></th>
                <td>
                    <input type="url" id="rnd-start-url" name="rnd[start_url]" value="<?= esc_attr($config['start_url']) ?>" placeholder="<?= esc_attr(home_url('/')) ?>" class="regular-text">
                    <p class="description">Vacío = home del sitio. Debe ser del mismo dominio.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Límites del rastreo</th>
                <td class="rnd-inline-fields">
                    <label>Páginas
                        <input type="number" name="rnd[max_pages]" value="<?= (int) $config['max_pages'] ?>" min="10" max="2000" step="10">
                    </label>
                    <label>Profundidad
                        <input type="number" name="rnd[max_depth]" value="<?= (int) $config['max_depth'] ?>" min="1" max="10" step="1">
                    </label>
                    <label>Timeout (s)
                        <input type="number" name="rnd[timeout]" value="<?= (int) $config['timeout'] ?>" min="3" max="20" step="1">
                    </label>
                    <p class="description">Cada lote pide 4 páginas. Con un timeout alto y un servidor lento, baja el número de páginas.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Tipos de contenido</th>
                <td class="rnd-types">
                    <?php foreach ($post_types as $type => $object) : ?>
                        <?php if ('attachment' === $type) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <label>
                            <input type="checkbox" name="rnd[post_types][]" value="<?= esc_attr($type) ?>" <?= in_array($type, $selected_types, true) ? 'checked' : '' ?>>
                            <?= esc_html($object->labels->singular_name ? $object->labels->singular_name : $type) ?>
                            <span class="rnd-muted">(<?= esc_html($type) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">Solo se revisan los tipos marcados. Los productos aparecen si WooCommerce está activo.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Guardar ajustes', 'secondary', 'submit', false); ?>
    </form>

    <hr>

    <h2>Analizar enlaces desde la home</h2>
    <p>
        <button type="button" class="button button-primary" id="rnd-crawl-start">Analizar ahora</button>
        <?php if ($status && empty($status['done'])) : ?>
            <button type="button" class="button" id="rnd-crawl-continue">
                Continuar análisis (<?= (int) $status['visited'] ?>/<?= (int) $status['seen'] ?>)
            </button>
        <?php endif; ?>
        <span class="rnd-muted">El análisis se hace por lotes y puede tardar varios minutos.</span>
    </p>

    <div class="rnd-progress" id="rnd-crawl-progress" hidden>
        <div class="rnd-progress__bar"><span></span></div>
    </div>
    <p class="rnd-progress__text" id="rnd-crawl-status"></p>
    <div id="rnd-crawl-message"></div>

    <?php if ($report) : ?>
        <div class="rnd-report">
            <p class="rnd-summary">
                Último análisis: <strong><?= esc_html($report['date']) ?></strong>
                · <?= (int) $report['visited'] ?> páginas visitadas
                · <?= (int) $report['links'] ?> enlaces seguidos
                · <?= (int) $report['duration'] ?> s
            </p>

            <?php if (!empty($report['truncated'])) : ?>
                <div class="notice notice-warning inline">
                    <p>Se alcanzó el límite de páginas del rastreo. Puede haber falsos huérfanos: sube el límite y vuelve a analizar.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($report['error_count'])) : ?>
                <details class="rnd-errors">
                    <summary><?= (int) $report['error_count'] ?> URLs con error (no se pudieron leer)</summary>
                    <ul>
                        <?php foreach ((array) $report['errors'] as $error) : ?>
                            <li><code><?= esc_html($error['url']) ?></code> — <?= esc_html($error['error']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <?php
            $orphan_count = isset($report['orphan_count']) ? (int) $report['orphan_count'] : count($report['orphans']);
            ?>
            <?php if (empty($report['orphans'])) : ?>
                <p><em>No hay contenido publicado sin enlaces desde la home.</em></p>
            <?php else : ?>
                <h3><?= $orphan_count ?> elementos sin enlaces desde la home</h3>
                <?php if (!empty($report['orphans_truncated'])) : ?>
                    <div class="notice notice-warning inline">
                        <p>La tabla muestra los primeros <?= count($report['orphans']) ?> de <?= $orphan_count ?>. Elimina estos y vuelve a analizar para ver el resto.</p>
                    </div>
                <?php endif; ?>
                <p>
                    <button type="button" class="button" id="rnd-page-trash">Enviar seleccionadas a la papelera</button>
                    <span class="rnd-muted">Se envían a la papelera de WordPress; se pueden recuperar.</span>
                </p>
                <table class="widefat striped rnd-table">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="rnd-page-all" aria-label="Seleccionar todas"></th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>URL</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report['orphans'] as $item) : ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="rnd-page-check" value="<?= (int) $item['id'] ?>" <?= $item['protected'] ? 'disabled' : '' ?>>
                                </th>
                                <td>
                                    <strong><?= esc_html($item['title']) ?></strong>
                                    <?php if ($item['protected']) : ?>
                                        <span class="rnd-badge"><?= esc_html($item['protected']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc_html($item['type_label']) ?></td>
                                <td class="rnd-url">
                                    <a href="<?= esc_url($item['url']) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html($item['url']) ?></a>
                                </td>
                                <td><?= esc_html($item['date']) ?></td>
                                <td class="rnd-actions">
                                    <?php if ($item['edit']) : ?>
                                        <a href="<?= esc_url($item['edit']) ?>">Editar</a>
                                    <?php endif; ?>
                                    <?php if (!$item['protected']) : ?>
                                        <button type="button" class="button-link rnd-page-trash-one" data-id="<?= (int) $item['id'] ?>" data-title="<?= esc_attr($item['title']) ?>">Papelera</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <p><em>Todavía no hay análisis. Pulsa «Analizar ahora» para empezar.</em></p>
    <?php endif; ?>

    <div id="rnd-page-message"></div>
</div>
