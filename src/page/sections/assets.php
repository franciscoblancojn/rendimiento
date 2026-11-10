<?php

/**
 * Sección compartida por las pestañas «Excluir CSS» y «Excluir JS».
 *
 * La incluye css.php / js.php con $rnd_asset_type definido. No usa IDs duplicados:
 * todo se marca con data-type y las clases se buscan dentro de la sección.
 */

$type = isset($rnd_asset_type) ? $rnd_asset_type : 'css';
$is_css = 'css' === $type;
$label = $is_css ? 'CSS' : 'JS';
$config = RND_ASSETS::config();
$report = RND_ASSETS::report();
$status = RND_ASSETS::status();
$excluded = RND_ASSETS::exclusions($type);
$results = [];

if ($report && !empty($report['results'])) {
    foreach ($report['results'] as $item) {
        if (isset($item['type']) && $type === $item['type']) {
            $results[] = $item;
        }
    }
}
usort($results, function ($a, $b) {
    return (int) $b['score'] - (int) $a['score'];
});
?>
<div class="rnd-section rnd-assets" data-type="<?= esc_attr($type) ?>">
    <p class="rnd-intro">
        Analiza la home como un robot, lista sus archivos <strong><?= esc_html($label) ?></strong> y calcula un
        <strong>% de innecesario</strong> (0 = obligatorio, 100 = probablemente se pueda quitar). Al excluir un
        archivo deja de cargarse en <strong>todo el sitio</strong>.
    </p>

    <div class="notice notice-warning inline">
        <p>
            <strong>Antes de excluir:</strong> el % se calcula solo con la home y sin medir interacción. Un archivo
            puede usarse en otras páginas o generarse por JavaScript. Haz una copia de seguridad y prueba en una
            ventana privada; si algo se rompe, quita la exclusión desde la lista.
        </p>
    </div>

    <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="rnd-settings">
        <input type="hidden" name="action" value="<?= esc_attr(RND_API_ASSETS::ACTION_CONFIG) ?>">
        <input type="hidden" name="rnd_tab" value="<?= esc_attr($type) ?>">
        <?php wp_nonce_field(RND_API_ASSETS::ACTION_CONFIG); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Aplicación en el frontend</th>
                <td>
                    <label>
                        <input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>>
                        Activar las exclusiones (CSS y JS)
                    </label>
                    <p class="description">
                        El interruptor y el modo prueba son comunes a las dos listas. Si se desactiva, los archivos
                        vuelven a cargarse.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">Modo prueba</th>
                <td>
                    <label>
                        <input type="checkbox" name="test_mode" value="1" <?= !empty($config['test_mode']) ? 'checked' : '' ?>>
                        Aplicar solo a administradores conectados
                    </label>
                    <p class="description">Los visitantes siguen viendo el sitio normal mientras validas.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Guardar ajustes', 'secondary', 'submit-' . $type, false); ?>
    </form>

    <hr>

    <h2>Analizar <?= esc_html($label) ?> de la home</h2>
    <p>
        <button type="button" class="button button-primary rnd-assets-start" data-type="<?= esc_attr($type) ?>">Analizar ahora</button>
        <?php if ($status && empty($status['done'])) : ?>
            <button type="button" class="button rnd-assets-continue" data-type="<?= esc_attr($type) ?>">
                Continuar análisis (<?= (int) $status['index'] ?>/<?= (int) $status['total'] ?>)
            </button>
        <?php endif; ?>
        <span class="rnd-muted">El análisis revisa CSS y JS a la vez, por lotes.</span>
    </p>

    <div class="rnd-progress" hidden>
        <div class="rnd-progress__bar"><span></span></div>
    </div>
    <p class="rnd-progress__text"></p>
    <div class="rnd-assets-message"></div>

    <?php if ($report) : ?>
        <div class="rnd-report">
            <p class="rnd-summary">
                Último análisis: <strong><?= esc_html($report['date']) ?></strong>
                · <?= (int) $report['css_count'] ?> CSS
                · <?= (int) $report['js_count'] ?> JS
                · <?= (int) $report['duration'] ?> s
            </p>

            <?php if (!empty($report['optimized'])) : ?>
                <div class="notice notice-info inline">
                    <p>
                        La home venía con los archivos <strong>combinados por el optimizador</strong> (LiteSpeed). Para
                        poder listarlos y excluirlos por separado se analizó la versión <strong>sin optimizar</strong> de
                        la home. La exclusión se aplica igualmente en el servidor, antes de que se combine la página.
                    </p>
                </div>
            <?php elseif (!empty($report['combined_count'])) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        Se encontraron <strong>archivos combinados por el optimizador</strong>. No se pueden excluir
                        por separado: quitar ese archivo dejaría la página sin CSS/JS. Purga la caché o desactiva la
                        combinación para analizarlos uno a uno.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($report['error_count'])) : ?>
                <details class="rnd-errors">
                    <summary><?= (int) $report['error_count'] ?> archivos que no se pudieron leer</summary>
                    <ul>
                        <?php foreach ((array) $report['errors'] as $error) : ?>
                            <li><code><?= esc_html($error['url']) ?></code> — <?= esc_html($error['error']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <?php if ($results) : ?>
                <h3>Archivos <?= esc_html($label) ?> encontrados</h3>
                <p class="rnd-muted">0 = obligatorio · 100 = se puede eliminar. El color solo orienta.</p>
                <p>
                    <button type="button" class="button rnd-assets-exclude-bulk" data-type="<?= esc_attr($type) ?>">Excluir seleccionados</button>
                    <span class="rnd-muted">Se aplica en todo el sitio.</span>
                </p>
                <table class="widefat striped rnd-table">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="rnd-asset-all" data-type="<?= esc_attr($type) ?>" aria-label="Seleccionar todos"></th>
                            <th>Archivo</th>
                            <th class="rnd-num">Tamaño</th>
                            <th class="rnd-num">% innecesario</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $item) : ?>
                            <?php
                            $key = isset($item['key']) ? $item['key'] : RND_ASSETS::normalize($item['url']);
                            $is_excluded = isset($excluded[$key]);
                            $is_combined = !empty($item['combined']);
                            $score = max(0, min(100, (int) $item['score']));
                            if ($score >= 75) {
                                $score_class = 'rnd-score--high';
                            } elseif ($score >= 50) {
                                $score_class = 'rnd-score--mid';
                            } elseif ($score >= 25) {
                                $score_class = 'rnd-score--low';
                            } else {
                                $score_class = 'rnd-score--keep';
                            }
                            $file_name = basename((string) wp_parse_url($item['url'], PHP_URL_PATH));
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" class="rnd-asset-check" data-type="<?= esc_attr($type) ?>" data-url="<?= esc_attr($item['url']) ?>" data-handle="<?= esc_attr($item['handle']) ?>" <?= ($is_excluded || $is_combined) ? 'disabled' : '' ?>>
                                </th>
                                <td class="rnd-url" title="<?= esc_attr($item['url']) ?>">
                                    <code><?= esc_html('' !== $file_name ? $file_name : $item['url']) ?></code>
                                    <span class="rnd-muted"><?= esc_html($item['url']) ?> · <?= !empty($item['local']) ? 'local' : 'externo' ?></span>
                                </td>
                                <td class="rnd-num"><?= esc_html(size_format((int) $item['size'])) ?></td>
                                <td class="rnd-num"><span class="rnd-score <?= esc_attr($score_class) ?>"><?= $score ?>%</span></td>
                                <td class="rnd-reason"><?= esc_html($item['reason']) ?></td>
                                <td>
                                    <?php if ($is_excluded) : ?>
                                        <span class="rnd-badge rnd-badge--ok">Excluido</span>
                                    <?php elseif ($is_combined) : ?>
                                        <span class="rnd-badge rnd-badge--warn">Combinado</span>
                                    <?php else : ?>
                                        <span class="rnd-badge">Se carga</span>
                                    <?php endif; ?>
                                </td>
                                <td class="rnd-actions">
                                    <?php if ($is_combined) : ?>
                                        <span class="rnd-muted">No excluir</span>
                                    <?php elseif ($is_excluded) : ?>
                                        <button type="button" class="button-link rnd-asset-include" data-type="<?= esc_attr($type) ?>" data-key="<?= esc_attr($key) ?>">Incluir</button>
                                    <?php else : ?>
                                        <button type="button" class="button-link rnd-asset-exclude" data-type="<?= esc_attr($type) ?>" data-url="<?= esc_attr($item['url']) ?>" data-handle="<?= esc_attr($item['handle']) ?>">Excluir</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><em>El último análisis no encontró archivos <?= esc_html($label) ?> en la home.</em></p>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <p><em>Todavía no hay análisis. Pulsa «Analizar ahora» para empezar.</em></p>
    <?php endif; ?>

    <h2>Lista de exclusión (<?= esc_html($label) ?>)</h2>
    <p class="description">
        Estos archivos no se cargan en el frontend. Al guardar se purga la caché de LiteSpeed automáticamente; si usas
        otra caché, púrgala a mano para que el cambio se vea.
    </p>
    <?php if (!$excluded) : ?>
        <p><em>No hay archivos <?= esc_html($label) ?> excluidos.</em></p>
    <?php else : ?>
        <table class="widefat striped rnd-table">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Handle</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($excluded as $key => $item) : ?>
                    <tr>
                        <td class="rnd-url" title="<?= esc_attr($item['url']) ?>">
                            <code><?= esc_html($item['url']) ?></code>
                        </td>
                        <td><?= '' !== (string) $item['handle'] ? '<code>' . esc_html($item['handle']) . '</code>' : '<span class="rnd-muted">—</span>' ?></td>
                        <td><?= esc_html($item['date']) ?></td>
                        <td class="rnd-actions">
                            <button type="button" class="button-link rnd-asset-include" data-type="<?= esc_attr($type) ?>" data-key="<?= esc_attr($key) ?>">Quitar de la lista</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
