/**
 * Drag-to-reorder for the Positions taxonomy admin list.
 *
 * Makes the term rows sortable; on drop it renumbers the visible Order column
 * and persists the new 1-based order via admin-ajax (rtm_reorder_positions).
 * Also sorts rows by their Order value on load as a safety net, so the list is
 * always shown in rank sequence even if the server list happens to differ.
 */
(function ($) {
    'use strict';

    $(function () {
        var $list = $('#the-list');
        if (!$list.length || typeof RTMPositions === 'undefined') {
            return;
        }

        // 1) Ensure rows are shown in Order sequence on load.
        var rows = $list.find('> tr').get();
        rows.sort(function (a, b) {
            var oa = parseInt($(a).find('.column-rtm_position_order').text(), 10) || 0;
            var ob = parseInt($(b).find('.column-rtm_position_order').text(), 10) || 0;
            return oa - ob;
        });
        $.each(rows, function (i, tr) {
            $list.append(tr);
        });

        // 2) Make rows draggable.
        $list.find('> tr').css('cursor', 'move');
        $list.sortable({
            items: '> tr',
            axis: 'y',
            cursor: 'move',
            opacity: 0.8,
            helper: function (e, tr) {
                // Keep column widths while dragging.
                var $cells = tr.children();
                var $clone = tr.clone();
                $clone.children().each(function (i) {
                    $(this).width($cells.eq(i).width());
                });
                return $clone;
            },
            update: function () {
                var ids = $list.find('> tr').map(function () {
                    return this.id.replace('tag-', '');
                }).get();

                // Reflect the new order numbers immediately.
                $list.find('> tr').each(function (i) {
                    $(this).find('.column-rtm_position_order').text(i + 1);
                });

                $.post(RTMPositions.ajaxurl, {
                    action: 'rtm_reorder_positions',
                    order: ids,
                    _wpnonce: RTMPositions.nonce
                });
            }
        });
    });
})(jQuery);
