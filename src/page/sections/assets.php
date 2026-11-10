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
$results = RND_ASSETS::results_for($type);
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
        <span class="rnd-settings-message"></span>
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

    <div class="rnd-assets-report" data-type="<?= esc_attr($type) ?>">
        <?php require RND_DIR . 'src/page/sections/assets-report.php'; ?>
    </div>

    <h2>Lista de exclusión (<?= esc_html($label) ?>)</h2>
    <p class="description">
        Estos archivos no se cargan en el frontend. Al guardar se purga la caché de LiteSpeed automáticamente; si usas
        otra caché, púrgala a mano para que el cambio se vea.
    </p>
    <div class="rnd-assets-excluded" data-type="<?= esc_attr($type) ?>">
        <?php require RND_DIR . 'src/page/sections/assets-excluded.php'; ?>
    </div>
</div>
