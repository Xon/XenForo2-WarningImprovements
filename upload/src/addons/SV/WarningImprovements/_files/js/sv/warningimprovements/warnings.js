var SV = SV || {};

/** @param {jQuery} $ jQuery Object */
!function ($, window, document, _undefined) {
    "use strict";

    SV.WarningViewToggler = XF.Element.newHandler({
        eventNameSpace: 'SVWarningViewToggler',
        options: {},

        $selectViewContainer: null,
        $radioViewContainer: null,

        toggleSelectViewPhrase: null,
        toggleRadioViewPhrase: null,

        storageName: "xf_sv_warning_view",
        setting: null,

        init: function () {
            var selectViewContainer = this.$target.data('select-view'),
                radioViewContainer = this.$target.data('radio-view');

            if (!selectViewContainer) {
                console.error("Warning View Toggler must have a data-select-view");
                return;
            }

            if (!radioViewContainer) {
                console.error("Warning View Toggler must have a data-radio-view");
                return;
            }

            var toggleSelectPhrase = this.$target.data('toggle-select-phrase'),
                toggleRadioPhrase = this.$target.data('toggle-radio-phrase');

            if (!toggleSelectPhrase) {
                console.error("Warning View Toggler must have a data-toggle-select-phrase");
                return;
            }

            if (!toggleRadioPhrase) {
                console.error("Warning View Toggler must have a data-toggle-radio-phrase");
                return;
            }

            this.selectViewContainer = selectViewContainer;
            this.radioViewContainer = radioViewContainer;

            this.toggleSelectViewPhrase = toggleSelectPhrase;
            this.toggleRadioViewPhrase = toggleRadioPhrase;

            this.setting = localStorage.getItem(this.storageName);

            this.$target.on('click', $.proxy(this, 'click'));

            if (this.setting === 'select')
            {
                this.hideRadioView();
                this.showSelectView();
            }
            else
            {
                this.hideSelectView();
                this.showRadioView();
            }
        },

        click: function(e) {
            e.preventDefault();

            this.toggle();
        },

        toggle: function () {
            if (this.setting === 'select')
            {
                this.setSetting('radio');
            }
            else
            {
                this.setSetting('select');
            }

            window.location.reload();
        },

        showSelectView: function () {
            $(this.selectViewContainer).xfFadeDown();

            this.$target.text(this.toggleRadioViewPhrase);
        },

        showRadioView: function () {
            $(this.radioViewContainer).xfFadeDown();

            this.$target.text(this.toggleSelectViewPhrase);
        },

        hideSelectView: function () {
            $(this.selectViewContainer).xfFadeUp();
            $(this.selectViewContainer).remove();
        },

        hideRadioView: function () {
            $(this.radioViewContainer).xfFadeUp();
            $(this.radioViewContainer).remove();
        },

        getSetting: function () {
            return this.setting;
        },

        setSetting: function (value) {
            this.setting = value;
            localStorage.setItem(this.storageName, this.setting);
        }
    });

    // ################################## WARNING SELECT HANDLER ###########################################

    SV.WarningViewSelect = XF.Element.newHandler({

        options: {},

        $container: null,

        init: function()
        {
            var config = {
                tags: false,
                width: '100%',
                containerCssClass: 'input',
                minimumInputLength: 0,
                selectOnClose: true,
                openOnEnter: true, // for lazy people like me :/
                disabled: this.$target.prop('disabled')
            };

            this.$target.select2(config);

            var api = this.$target.data('select2');
            this.$container = api.$container;
            this.$selection = api.$selection;

            api.on('results:message', function(params)
            {
                this.dropdown._resizeDropdown();
                this.dropdown._positionDropdown();
            });

            api.$selection.addClass('is-focused');

            api.$container.on('focusin focusout', $.proxy(this, 'inputFocusBlur'));
            this.$target.on('change', $.proxy(this, 'change'));
        },

        inputFocusBlur: function(e)
        {
            switch (e.type)
            {
                case 'focusout':
                    this.$selection.removeClass('is-focused');
                    break;

                case 'focusin':
                default:
                    this.$selection.addClass('is-focused');
                    break;
            }
        },

        change: function (e) {
            this.$target.trigger('click');
        }
    });

    XF.Element.register('warning-view-toggle', 'SV.WarningViewToggler');
    XF.Element.register('warning-view-select', 'SV.WarningViewSelect');
}
(jQuery, window, document);