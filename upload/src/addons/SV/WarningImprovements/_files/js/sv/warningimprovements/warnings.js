// noinspection JSVoidFunctionReturnValueUsed

window.SV = window.SV || {};
window.SV.WarningImprovements = window.SV.WarningImprovements || {};

// noinspection JSUnusedLocalSymbols
(function()
{
    "use strict"

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
            console.log(event)

            this._svWarningImprovementsOnRemoveItem(event)
            this.onChange(event)
        },

        onChange (event)
        {
            this.customTitleSelector.value = '';
        }
    })

    // ################################## SAVE WARNING VIEW PREFERENCE HANDLER ###########################################

    SV.WarningImprovements.SaveWarningViewPref  = XF.Element.newHandler({
        options: {
            warningView: null
        },

        init ()
        {
            if (!(['radio', 'select'].includes(this.options.warningView)))
            {
                throw new Error('Invalid warning view provided.')
            }

            if (typeof XF.on !== "function") // XF 2.2
            {
                this.$target.on('change', this.onChange.bind(this))
            }
            else
            {
                XF.on(this.target, 'change', this.onChange.bind(this))
            }
        },

        onChange (e)
        {
            const theTarget = this.target || this.$target.get(0)

            XF.ajax('POST', XF.canonicalizeUrl('index.php?warnings/sv-warning-view-pref'), {
                value: theTarget.value
            }, null, { skipDefaultSuccess: true })
        }
    })

    XF.Element.register('warning-view-select', 'SV.WarningImprovements.SelectView')
    XF.Element.register('warning-title-watcher', 'SV.WarningImprovements.TitleWatcher')
    XF.Element.register('sv-save-warning-view-pref', 'SV.WarningImprovements.SaveWarningViewPref')
}) ()