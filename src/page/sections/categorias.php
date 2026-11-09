<?php

if (!RND_CATEGORIES::available()) :
?>
    <div class="notice notice-warning inline">
        <p>WooCommerce no está activo: no hay categorías de productos que revisar.</p>
    </div>
<?php
    return;
endif;

$all = RND_CATEGORIES::all();
$empty = [];
$nested = [];
foreach ($all as $term) {
    if (0 === (int) $term['total']) {
        $empty[] = $term;
    } elseif (0 === (int) $term['count']) {
        $nested[] = $term;
    }
}
$default = RND_CATEGORIES::default_id();
$cats_url = admin_url('edit-tags.php?taxonomy=product_cat&post_type=product');
?>
<div class="rnd-section">
    <p class="rnd-intro">
        Una categoría se considera <strong>vacía</strong> cuando no tiene productos ni directamente ni en sus
        subcategorías. Solo esas se pueden eliminar sin dejar productos sin categoría. La categoría por defecto
        de WooCommerce nunca se elimina.
    </p>

    <div class="rnd-cards">
        <div class="rnd-card">
            <span class="rnd-card__num"><?= count($all) ?></span>
            <span class="rnd-card__label">Categorías en total</span>
        </div>
        <div class="rnd-card rnd-card--ok">
            <span class="rnd-card__num"><?= count($empty) ?></span>
            <span class="rnd-card__label">Totalmente vacías</span>
        </div>
        <div class="rnd-card rnd-card--warn">
            <span class="rnd-card__num"><?= count($nested) ?></span>
            <span class="rnd-card__label">Vacías directas, con productos en hijas</span>
        </div>
    </div>

    <div id="rnd-cat-message"></div>

    <?php if ($empty) : ?>
        <p>
            <button type="button" class="button button-primary" id="rnd-cat-delete">Eliminar seleccionadas</button>
            <span class="rnd-muted">La eliminación es permanente.</span>
        </p>
        <table class="widefat striped rnd-table">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" class="rnd-cat-all" aria-label="Seleccionar todas"></th>
                    <th>Categoría</th>
                    <th>Slug</th>
                    <th class="rnd-num">Productos directos</th>
                    <th class="rnd-num">Subcategorías</th>
                    <th class="rnd-num">Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empty as $term) : ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" class="rnd-cat-check" value="<?= (int) $term['id'] ?>" <?= $term['default'] ? 'disabled' : '' ?>>
                        </th>
                        <td>
                            <strong><?= esc_html($term['name']) ?></strong>
                            <?php if ($term['default']) : ?>
                                <span class="rnd-badge">por defecto</span>
                            <?php endif; ?>
                            <?php if ($term['parent_name']) : ?>
                                <span class="rnd-muted">· hija de <?= esc_html($term['parent_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= esc_html($term['slug']) ?></code></td>
                        <td class="rnd-num"><?= (int) $term['count'] ?></td>
                        <td class="rnd-num"><?= (int) $term['children'] ?></td>
                        <td class="rnd-num"><?= (int) $term['total'] ?></td>
                        <td class="rnd-actions">
                            <?php if ($term['archive']) : ?>
                                <a href="<?= esc_url($term['archive']) ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                            <?php endif; ?>
                            <?php if ($term['edit']) : ?>
                                <a href="<?= esc_url($term['edit']) ?>">Editar</a>
                            <?php endif; ?>
                            <?php if (!$term['default']) : ?>
                                <button type="button" class="button-link rnd-cat-delete-one" data-id="<?= (int) $term['id'] ?>" data-name="<?= esc_attr($term['name']) ?>">Eliminar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><em>No hay categorías de productos totalmente vacías.</em></p>
    <?php endif; ?>

    <?php if ($nested) : ?>
        <h2>Sin productos directos, pero con productos en subcategorías</h2>
        <p class="description">
            Estas categorías no tienen productos propios, pero sus subcategorías sí. Eliminarlas dejaría esas
            subcategorías sin padre; no se ofrecen para borrar desde aquí.
        </p>
        <table class="widefat striped rnd-table">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Slug</th>
                    <th class="rnd-num">Productos directos</th>
                    <th class="rnd-num">Total con subcategorías</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nested as $term) : ?>
                    <tr>
                        <td>
                            <strong><?= esc_html($term['name']) ?></strong>
                            <?php if ($term['parent_name']) : ?>
                                <span class="rnd-muted">· hija de <?= esc_html($term['parent_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= esc_html($term['slug']) ?></code></td>
                        <td class="rnd-num"><?= (int) $term['count'] ?></td>
                        <td class="rnd-num"><?= (int) $term['total'] ?></td>
                        <td class="rnd-actions">
                            <?php if ($term['archive']) : ?>
                                <a href="<?= esc_url($term['archive']) ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                            <?php endif; ?>
                            <?php if ($term['edit']) : ?>
                                <a href="<?= esc_url($term['edit']) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p>
        <a href="<?= esc_url($cats_url) ?>">Abrir las categorías de productos en WooCommerce</a>
    </p>
</div>
