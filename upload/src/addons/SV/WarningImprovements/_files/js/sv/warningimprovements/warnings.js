var SV = SV || {};

// noinspection JSUnusedLocalSymbols
(function ($, window, document, _undefined) {
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
                this.showSelectView();
            }
            else
            {
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

            $('[data-warning-view-type="radio"]').remove();

            $(this.radioViewContainer).xfFadeUp();
            $(this.radioViewContainer).remove();

            $('select[data-warning-select="true"]').trigger('change');
        },

        showRadioView: function () {
            $(this.radioViewContainer).xfFadeDown();
            this.$target.text(this.toggleSelectViewPhrase);

            $('[data-warning-view-type="select"]').remove();

            $(this.selectViewContainer).xfFadeUp();
            $(this.selectViewContainer).remove();

            $('input[type=radio][data-warning-radio="true"]:enabled:visible:checked').first().trigger('click');
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
        eventNameSpace: 'WarningViewSelect',
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
            $('input[type=text][name=custom_title]').val('');
        }
    });

    // ################################## WARNING SELECT HANDLER ###########################################

    SV.WarningTitleWatcher  = XF.Element.newHandler({
        eventNameSpace: 'WarningTitleWatcher',
        options: {},

        init: function () {
            this.$target.on('change', $.proxy(this, 'change'));
            this.$target.on('input', $.proxy(this, 'input'));

            if (this.$target.is('input:radio'))
            {
                this.$target.on('click', $.proxy(this, 'click'));
            }
        },

        change: function()
        {
            this.input();
        },

        click: function()
        {
            this.input();
        },

        input: function() {
            if (this.$target.is('input:text'))
            {
                this.setPublicMessage(this.$target.val());
            }
            else if (this.$target.is('input:radio'))
            {
                $("input[data-warning-title-input=1][data-for-warning=" + this.$target.val() + "]").parent().parent().parent().xfFadeDown(XF.config.speed.xxfast, function() {});

                $("input[data-warning-title-input=1][data-for-warning!='" + this.$target.val() + "']").parent().parent().parent().xfFadeUp(XF.config.speed.xxfast);

                this.setPublicMessage("");

                if (this.$target.is("[data-warning-label]"))
                {
                    this.setPublicMessage(this.$target.data('warning-label'));
                }
            }
            else if (this.$target.is('select'))
            {
                $("input[data-warning-title-input=1][data-for-warning!='" + this.$target.find("option:selected").val() + "']")
                    .prop('disabled', true)
                    .parent().parent()
                    .xfFadeUp(XF.config.speed.xxfast);

                var warningId = this.$target.find("option:selected").val(),
                    warningInput = $("input[data-warning-title-input=1][data-for-warning=" + warningId + "]"),
                    warningLabel = $("dl[data-for-warning=" + warningId + "][data-warning-label]"),
                    warningText = "";

                if (warningLabel.length > 0)
                {
                    warningText = warningLabel.data('warning-label');
                    warningInput.prop('value', warningText);
                }

                this.setPublicMessage(warningText);

                if (warningInput.prop('type') !== 'hidden')
                {
                    warningInput
                        .prop('disabled', false)
                        .parent().parent()
                        .xfFadeDown(XF.config.speed.xxfast);
                }
            }
        },

        setPublicMessage: function (message) {
            if (XF.config.sv_warningimprovements_copy_title)
            {
                $("input[name='action_options[public_message]']").prop('value', message);
            }
        }
    });

    XF.Element.register('warning-view-toggle', 'SV.WarningViewToggler');
    XF.Element.register('warning-view-select', 'SV.WarningViewSelect');
    XF.Element.register('warning-title-watcher', 'SV.WarningTitleWatcher');
} (jQuery, window, document));