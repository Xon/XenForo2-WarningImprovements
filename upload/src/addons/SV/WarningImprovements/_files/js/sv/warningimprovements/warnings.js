// noinspection JSVoidFunctionReturnValueUsed

window.SV = window.SV || {};
window.SV.WarningImprovements = window.SV.WarningImprovements || {};

// noinspection JSUnusedLocalSymbols
(function()
{
    "use strict"

    SV.WarningImprovements.ViewToggler = XF.Element.newHandler({
        eventNameSpace: 'SVWarningViewToggler',
        options: {
            selectViewSelector: null,
            radioViewSelector: null,

            toggleSelectViewPhrase: null,
            toggleRadioViewPhrase: null
        },

        selectViewContainer: null,
        radioViewContainer: null,

        toggleSelectViewPhrase: null,
        toggleRadioViewPhrase: null,

        storageName: "xf_sv_warning_view",
        setting: null,

        init ()
        {
            if (!this.options.selectViewSelector)
            {
                console.error("Warning view toggler must have a select view selector")
                return
            }

            if (!this.options.radioViewSelector)
            {
                console.error("Warning view toggler must have a radio view selector.")
                return
            }

            if (this.options.toggleSelectViewPhrase === null)
            {
                console.error("Warning View Toggler must have a data-toggle-select-phrase")
                return
            }

            if (this.options.toggleRadioViewPhrase === null)
            {
                console.error("Warning View Toggler must have a data-toggle-radio-phrase")
                return
            }

            this.selectViewContainer = XF.findRelativeIf(this.options.selectViewSelector, this.target || this.$target.get(0))
            this.radioViewContainer = XF.findRelativeIf(this.options.radioViewSelector, this.target || this.$target.get(0))
            if (this.$target) // XF 2.2
            {
                this.selectViewContainer = this.selectViewContainer.get(0)
                this.radioViewContainer = this.radioViewContainer.get(0)
            }

            this.setting = localStorage.getItem(this.storageName)

            if (typeof XF.on !== "function") // XF 2.2
            {
                this.$target.on('click', this.onClick.bind(this))
            }
            else
            {
                XF.on(this.target, 'click', this.onClick.bind(this))
            }

            if (this.setting === 'select')
            {
                this.showSelectView()
            }
            else
            {
                this.showRadioView()
            }
        },

        onClick (e)
        {
            e.preventDefault()

            this.toggle()
        },

        toggle ()
        {
            if (this.setting === 'select')
            {
                this.setSetting('radio')
            }
            else
            {
                this.setSetting('select')
            }

            window.location.reload()
        },

        showSelectView ()
        {
            if (typeof this.$target !== 'undefined') // XF 2.2
            {
                $(this.selectViewContainer).xfFadeDown()
                this.$target.text(this.options.toggleRadioViewPhrase)

                $('[data-warning-view-type="radio"]').remove()

                $(this.radioViewContainer).xfFadeUp()
                $(this.radioViewContainer).remove()

                $('select[data-warning-select="true"]').trigger('change')
            }
            else
            {
                XF.Animate.fadeDown(this.selectViewContainer, {
                    complete: () =>
                    {
                        this.target.text = this.options.toggleRadioViewPhrase
                        document.querySelectorAll('[data-warning-view-type="radio"]').forEach((element) =>
                        {
                            element.remove()
                        })
                    }
                })

                XF.Animate.fadeUp(this.radioViewContainer, {
                    complete: () =>
                    {
                        this.radioViewContainer.remove()
                        XF.trigger(document.querySelector('select[data-warning-select="true"]'), 'change')
                    }
                })
            }
        },

        showRadioView ()
        {
            if (typeof this.$target !== 'undefined') // XF 2.2
            {
                $(this.radioViewContainer).xfFadeDown()
                this.$target.text(this.options.toggleSelectViewPhrase)

                $('[data-warning-view-type="select"]').remove()

                $(this.selectViewContainer).xfFadeUp()
                $(this.selectViewContainer).remove()

                $('input[type=radio][data-warning-radio="true"]:enabled:visible:checked').first().trigger('click')
            }
            else
            {
                XF.Animate.fadeDown(this.radioViewContainer, {
                    complete: () =>
                    {
                        this.target.text = this.options.toggleSelectViewPhrase

                        document.querySelectorAll('[data-warning-view-type="select"]').forEach((element) =>
                        {
                            element.remove()
                        })
                    }
                })

                XF.Animate.fadeUp(this.selectViewContainer, {
                    complete: () =>
                    {
                        this.selectViewContainer.remove()

                        //@todo: check if the visible element is the one that gets click event triggered
                        XF.trigger(document.querySelector('input[type=radio][data-warning-radio="true"]:enabled:checked'), 'click')
                    }
                })
            }
        },

        getSetting ()
        {
            return this.setting
        },

        setSetting (value)
        {
            this.setting = value
            localStorage.setItem(this.storageName, this.setting)
        }
    })

    // ################################## WARNING SELECT HANDLER ###########################################

    SV.WarningImprovements.SelectViewOpts = {
        customTitleSelector: 'input[type=text][name=custom_title]'
    }

    SV.WarningImprovements.SelectView = XF.extend(SV.StandardLib.Choices, {
        __backup: {
            init: '_svWarningImprovementsInit',
            onAddItem: '_svWarningImprovementsOnAddItem',
            onRemoveItem: '_svWarningImprovementsOnRemoveItem',
        },

        options: SV.extendObject({}, SV.StandardLib.Choices.prototype.options, SV.WarningImprovements.SelectViewOpts),

        customTitleSelector: null,

        init ()
        {
            this.customTitleSelector = XF.findRelativeIf(this.options.customTitleSelector, this.target || this.$target)
            if (this.$target)
            {
                this.customTitleSelector = this.customTitleSelector.get(0)
            }

            if (this.customTitleSelector === null)
            {
                console.error('Missing custom title input.')
                return
            }

            this._svWarningImprovementsInit()
        },

        onAddItem (event)
        {
            this._svWarningImprovementsOnAddItem(event)
            this.onChange(event)
        },

        onRemoveItem (event)
        {
            this._svWarningImprovementsOnRemoveItem(event)
            this.onChange(event)
        },

        onChange (event)
        {
            this.customTitleSelector.value = '';
        }
    })

    // ################################## WARNING SELECT HANDLER ###########################################

    SV.WarningImprovements.TitleWatcher  = XF.Element.newHandler({
        eventNameSpace: 'WarningTitleWatcher',
        options: {
            publicWarningSelector: 'input[name=\'action_options[public_message]\']',
            copyTitle: true // Default value must match with that of the option: sv_warningimprovements_copy_title
        },
        publicWarning: null,

        init ()
        {
            this.publicWarning = XF.findRelativeIf(this.options.publicWarningSelector, this.target || this.$target)
            if (this.$target)
            {
                this.publicWarning = this.publicWarning.get(0)
            }

            if (this.publicWarning === null)
            {
                console.error('Could not find public warning input')
                return
            }

            if (typeof XF.on !== "function") // XF 2.2
            {
                this.$target.on('change input', this.onChangeOrClick.bind(this))

                if (this.$target.is('input:radio'))
                {
                    this.$target.on('click', this.onChangeOrClick.bind(this))
                }
            }
            else
            {
                XF.on(this.target, 'change input', this.onInputChangeOrClick.bind(this))

                if (this.target.getAttribute('type') === 'radio')
                {
                    XF.on(this.target, 'click', this.onInputChangeOrClick.bind(this))
                }
            }
        },

        onInputChangeOrClick ()
        {
            const theTarget = this.target || this.$target.get(0)

            if (theTarget.getAttribute('type') === 'text')
            {
                this.onChangeForTextbox(theTarget)
            }
            else if (theTarget.getAttribute('type') === 'radio')
            {
                this.onChangeForRadio(theTarget)
            }
            else if (theTarget instanceof HTMLSelectElement)
            {
                this.onChangeForSelect(theTarget)
            }
        },

        /**
         * @param {HTMLInputElement} target
         */
        onChangeForTextbox (target)
        {
            this.setPublicMessage(target.value)
        },

        /**
         * @param {HTMLInputElement} target
         */
        onChangeForRadio (target)
        {
            // document.querySelectorAll("input[data-warning-title-input='1'][data-for-warning=']")

            let inputForWarning = document.querySelector("input[data-warning-title-input='1'][data-for-warning='" + target.value + "']"),
                inputNotForWarning = document.querySelector("input[data-warning-title-input='1']:not([data-for-warning='" + target.value + "'])")

            if (typeof this.$target !== 'undefined') // XF 2.2
            {
                $(inputForWarning).parent().parent().parent().xfFadeDown(XF.config.speed.xxfast)
                if (inputNotForWarning)
                {
                    $(inputNotForWarning).parent().parent().parent().xfFadeUp(XF.config.speed.xxfast)
                }
            }
            else
            {
                XF.Animate.fadeDown(inputForWarning.parentNode.parentNode.parentNode, {
                    speed: XF.config.speed.xxfast
                })
                if (inputNotForWarning)
                {
                    XF.Animate.fadeUp(inputNotForWarning.parentNode.parentNode.parentNode, {
                        speed: XF.config.speed.xxfast
                    })
                }
            }

            this.setPublicMessage('')

            if (target.dataset.warningLabel !== null)
            {
                this.setPublicMessage(target.dataset.warningLabel)
            }
        },

        /**
         * @param {HTMLSelectElement} target
         */
        onChangeForSelect (target)
        {
            if (typeof this.$target !== 'undefined') // XF 2.2
            {
                $("input[data-warning-title-input=1][data-for-warning!='" + this.$target.find("option:selected").val() + "']")
                    .prop('disabled', true)
                    .parent().parent()
                    .xfFadeUp(XF.config.speed.xxfast)
            }
            else
            {
                let input = document.querySelector("input[data-warning-title-input='1']:not([data-for-warning='" + target.querySelector('[selected]') + "'])")
                input.disabled = true

                XF.Animate.fadeUp(input.parentNode.parentNode, {
                    speed: XF.config.speed.xxfast,
                })
            }

            let selectedWarning = target.querySelector('option[selected]')
            if (selectedWarning === null)
            {
                return
            }

            let warningId = selectedWarning.value,
                warningInput = document.querySelector("input[data-warning-title-input=1][data-for-warning=" + warningId + "]"),
                warningLabel = document.querySelector("dl[data-for-warning=" + warningId + "][data-warning-label]")

            if (warningInput === null)
            {
                console.error('Warning input missing.');
                return
            }

            if (warningLabel !== null)
            {
                warningInput.value = warningLabel.dataset.warningLabel
            }

            this.setPublicMessage('')

            if (warningInput.type === 'hidden')
            {
                warningInput.disabled = true

                if (typeof this.$target !== 'undefined') // XF 2.2
                {
                    $(warningInput)
                        .prop('disabled', false)
                        .parent().parent()
                        .xfFadeDown(XF.config.speed.xxfast)
                }
                else
                {
                    XF.Animate.fadeDown(warningInput.parentNode.parentNode, {
                        speed: XF.config.speed.xxfast,
                    })
                }
            }
        },

        /**
         *
         * @param message
         */
        setPublicMessage (message)
        {
            if (this.options.copyTitle && this.publicWarning !== null)
            {
                this.publicWarning.value = message
            }
        },
    })

    XF.Element.register('warning-view-toggle', 'SV.WarningImprovements.ViewToggler')
    XF.Element.register('warning-view-select', 'SV.WarningImprovements.SelectView')
    XF.Element.register('warning-title-watcher', 'SV.WarningImprovements.TitleWatcher')
}) ()