<?php

/**
 * Lista de exclusión de CSS/JS.
 *
 * Lo incluyen la página (sections/assets.php) y el AJAX de refresco (RND_API_ASSETS::assets_render).
 * Espera $type, $label y $excluded ya calculados.
 */

if (!$excluded) : ?>
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
<?php endif;
