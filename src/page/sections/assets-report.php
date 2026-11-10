<?php

/**
 * Informe del análisis de CSS/JS.
 *
 * Lo incluyen la página (sections/assets.php) y el AJAX de refresco (RND_API_ASSETS::assets_render).
 * Espera $type, $label, $report, $excluded y $results ya calculados.
 */

if (!$report) : ?>
    <p><em>Todavía no hay análisis. Pulsa «Analizar ahora» para empezar.</em></p>
<?php else : ?>
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
<?php endif;
