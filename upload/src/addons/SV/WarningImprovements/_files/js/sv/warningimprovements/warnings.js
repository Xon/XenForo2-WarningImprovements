var SV = window.SV || {};

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

    XF.Element.register('warning-view-toggle', 'SV.WarningViewToggler');
}(jQuery, window, document);