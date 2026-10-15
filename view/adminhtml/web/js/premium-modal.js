/**
 * Reva Magento 2
 *
 * @category   Reva
 * @package    Reva_Core
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */
define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($) {
    'use strict';

    /**
     * Premium extensions popup on the Information page: Magento's own modal (already loaded in the admin),
     * with tabs when there is more than one extension.
     */
    return function (config, element) {
        var $element = $(element),
            $modal = $element.find('[data-role="reva-premium-modal"]'),
            $tabs = $modal.find('[role="tab"]');

        $modal.modal({
            type: 'popup',
            title: config.title,
            modalClass: 'reva-premium-modal' + (config.single ? ' reva-premium-modal--single' : ''),
            responsive: false,
            buttons: []
        });

        $element.find('[data-role="reva-premium-open"]').on('click', function () {
            $modal.modal('openModal');
        });

        /**
         * Show the pane of a tab.
         *
         * @param {jQuery} $tab
         * @param {Boolean} focus
         */
        function select($tab, focus) {
            $tabs.attr({'aria-selected': 'false', tabindex: '-1'});
            $tab.attr({'aria-selected': 'true', tabindex: '0'});
            $modal.find('[role="tabpanel"]').prop('hidden', true);
            $modal.find('#' + $tab.attr('aria-controls')).prop('hidden', false);
            $modal.find('.reva-premium__panes').scrollTop(0);

            if (focus) {
                $tab.trigger('focus');
            }
        }

        $tabs.on('click', function () {
            select($(this), false);
        }).on('keydown', function (event) {
            var index = $tabs.index(this),
                next = null;

            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
                next = (index + 1) % $tabs.length;
            } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
                next = (index - 1 + $tabs.length) % $tabs.length;
            } else if (event.key === 'Home') {
                next = 0;
            } else if (event.key === 'End') {
                next = $tabs.length - 1;
            }

            if (next !== null) {
                event.preventDefault();
                select($tabs.eq(next), true);
            }
        });
    };
});
