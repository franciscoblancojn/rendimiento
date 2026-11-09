/* Rendimiento — admin (ES5 + jQuery). Datos de PHP en window.RND_ADMIN. */
jQuery(function ($) {
    var $page = $('.rnd-page');
    if (!$page.length) {
        return;
    }

    var busy = false;
    var crawling = false;

    function rnd_post(action, data) {
        return $.ajax({
            url: RND_ADMIN.ajax,
            method: 'POST',
            dataType: 'json',
            data: $.extend({ action: 'rnd_' + action, nonce: RND_ADMIN.nonce }, data)
        }).then(function (res) {
            if (!res || !res.success) {
                return $.Deferred().reject(res && res.data ? res.data : 'Error inesperado.').promise();
            }
            return res.data;
        }, function (xhr) {
            return $.Deferred().reject('Error de conexión (' + xhr.status + ').').promise();
        });
    }

    function rnd_message(selector, text, type) {
        var $target = $(selector);
        $target.html('');
        if (!text) {
            return;
        }
        var $message = $('<p></p>').addClass('fwue-message rnd-message');
        if (type) {
            $message.addClass(type);
        }
        $message.text(text);
        $target.append($message);
    }

    function rnd_checked(selector) {
        return $page.find(selector + ':checked').map(function () {
            return this.value;
        }).get();
    }

    function rnd_delete(action, ids, question, messageSelector) {
        if (busy) {
            return;
        }
        if (!ids.length) {
            rnd_message(messageSelector, 'Selecciona al menos un elemento.', 'error');
            return;
        }
        if (!window.confirm(question)) {
            return;
        }

        busy = true;
        rnd_message(messageSelector, 'Procesando…', '');
        rnd_post(action, { ids: ids }).then(function (res) {
            var text = 'Listo: ' + res.deleted + ' elementos procesados';
            if (res.skipped && res.skipped.length) {
                text += ', ' + res.skipped.length + ' omitidos';
            }
            if (res.errors && res.errors.length) {
                text += ', ' + res.errors.length + ' con error';
            }
            rnd_message(messageSelector, text + '. Recargando…', 'ok');
            window.setTimeout(function () {
                window.location.reload();
            }, 1200);
        }, function (error) {
            busy = false;
            rnd_message(messageSelector, error || 'No se pudo completar.', 'error');
        });
    }

    // ------------------------------------------------------------------ categorías

    $page.on('change', '.rnd-cat-all', function () {
        $page.find('.rnd-cat-check').not(':disabled').prop('checked', this.checked);
    });

    $page.on('click', '#rnd-cat-delete', function () {
        var ids = rnd_checked('.rnd-cat-check');
        rnd_delete(
            'delete_categories',
            ids,
            '¿Eliminar ' + ids.length + ' categorías vacías?\n\nEs permanente: no se pueden recuperar.',
            '#rnd-cat-message'
        );
    });

    $page.on('click', '.rnd-cat-delete-one', function () {
        var id = String($(this).data('id'));
        var name = $(this).data('name');
        rnd_delete(
            'delete_categories',
            [id],
            '¿Eliminar la categoría "' + name + '"?\n\nEs permanente: no se puede recuperar.',
            '#rnd-cat-message'
        );
    });

    // ------------------------------------------------------------------ páginas

    $page.on('change', '.rnd-page-all', function () {
        $page.find('.rnd-page-check').not(':disabled').prop('checked', this.checked);
    });

    $page.on('click', '#rnd-page-trash', function () {
        var ids = rnd_checked('.rnd-page-check');
        rnd_delete(
            'delete_pages',
            ids,
            '¿Enviar ' + ids.length + ' elementos a la papelera?\n\nSe pueden recuperar desde la papelera de WordPress.',
            '#rnd-page-message'
        );
    });

    $page.on('click', '.rnd-page-trash-one', function () {
        var id = String($(this).data('id'));
        var title = $(this).data('title');
        rnd_delete(
            'delete_pages',
            [id],
            '¿Enviar "' + title + '" a la papelera?\n\nSe puede recuperar desde la papelera de WordPress.',
            '#rnd-page-message'
        );
    });

    // ------------------------------------------------------------------ rastreo

    function rnd_crawl_progress(summary) {
        var seen = Number(summary.seen) || 0;
        var visited = Number(summary.visited) || 0;
        var queued = Number(summary.queued) || 0;
        var percent = seen ? Math.round(visited / seen * 100) : 0;

        var $progress = $('#rnd-crawl-progress');
        $progress.prop('hidden', false);
        $progress.find('.rnd-progress__bar span').css('width', percent + '%');

        var text = 'Visitadas ' + visited + ' de ' + seen + ' · en cola ' + queued;
        if (summary.errors) {
            text += ' · ' + summary.errors + ' errores';
        }
        $('#rnd-crawl-status').text(text);
    }

    function rnd_crawl_run(first) {
        if (crawling) {
            return;
        }
        crawling = true;
        busy = true;
        $('#rnd-crawl-start, #rnd-crawl-continue').prop('disabled', true);
        rnd_message('#rnd-crawl-message', 'Analizando enlaces internos…', '');

        function next(summary) {
            rnd_crawl_progress(summary);
            if (summary.done) {
                return rnd_post('crawl_finish');
            }
            return rnd_post('crawl_batch').then(next);
        }

        var request = first ? rnd_post('crawl_start') : rnd_post('crawl_batch');
        request.then(next).then(function (report) {
            crawling = false;
            busy = false;
            rnd_message(
                '#rnd-crawl-message',
                'Análisis terminado: ' + report.orphans + ' elementos sin enlaces desde la home. Recargando…',
                'ok'
            );
            window.setTimeout(function () {
                window.location.reload();
            }, 1400);
        }, function (error) {
            crawling = false;
            busy = false;
            $('#rnd-crawl-start, #rnd-crawl-continue').prop('disabled', false);
            rnd_message('#rnd-crawl-message', error || 'No se pudo completar el análisis.', 'error');
        });
    }

    $('#rnd-crawl-start').on('click', function () {
        rnd_crawl_run(true);
    });

    $('#rnd-crawl-continue').on('click', function () {
        rnd_crawl_run(false);
    });
});
