// noinspection JSVoidFunctionReturnValueUsed

window.SV = window.SV || {};
window.SV.WarningImprovements = window.SV.WarningImprovements || {};

// noinspection JSUnusedLocalSymbols
(function()
{
    "use strict"

    // ################################## WARNING SELECT HANDLER ###########################################

    SV.WarningImprovements.SelectViewOpts = {
        customTitleRowSelector: null,
        customTitleInputSelector: 'input[type=text][name=custom_title]'
    }

    SV.WarningImprovements.WarningSelectView = XF.extend(SV.StandardLib.Choices, {
        __backup: {
            init: '_svWarningImprovementsInit',
            getConfig: '_svWarningImprovementsGetConfig',
            onAddItem: '_svWarningImprovementsOnAddItem',
            onRemoveItem: '_svWarningImprovementsOnRemoveItem',
        },

        options: SV.extendObject({}, SV.StandardLib.Choices.prototype.options, SV.WarningImprovements.SelectViewOpts),

        customTitleRow: null,
        customTitleInput: null,

        customTitles: [],

        init ()
        {
            const rowSelector = this.options.customTitleRowSelector
            if (rowSelector === null)
            {
                throw new Error('Custom title row selector missing.')
            }

            this.customTitleRow = XF.findRelativeIf(rowSelector, this.target || this.$target)
            if (this.$target)
            {
                this.customTitleRow = this.customTitleRow.get(0)
            }

            if (this.customTitleRow === null)
            {
                throw new Error('Missing custom title row.')
            }

            this.customTitleInput = XF.findRelativeIf(this.options.customTitleInputSelector, this.target || this.$target)
            if (this.$target)
            {
                this.customTitleInput = this.customTitleInput.get(0)
            }

            if (this.customTitleInput === null)
            {
                throw new Error('Custom title input missing.')
            }

            this._svWarningImprovementsInit()
        },

        getConfig ()
        {
            const config = this._svWarningImprovementsGetConfig()

            delete config.customTitleInputSelector
            delete config.customTitleRowSelector

            return config
        },

        previousSelectedItem: null,
        onRemoveItem (event)
        {
            this._svWarningImprovementsOnRemoveItem(event)

            if (typeof event.detail !== 'undefined')
            {
                this.previousSelectedItem = event.detail
            }
            else
            {
                this.previousSelectedItem = null
            }
        },

        onAddItem (event)
        {
            this._svWarningImprovementsOnAddItem(event)

            if (!this.choices)
            {
                throw new Error('Choices not setup.');
            }

            const previousSelectedItem = this.previousSelectedItem
            const selectedItem = this.choices._store.choices.find((choice) => {
                return choice.selected === true
            })

            if (previousSelectedItem)
            {
                // Store the custom title to a dataset in previous selected item
                if (previousSelectedItem.customProperties.allows_custom_title)
                {
                    if (this.customTitleInput.value.length === 0) // empty
                    {
                        this.customTitles[previousSelectedItem.value] = previousSelectedItem.label
                    }
                    else
                    {
                        this.customTitles[previousSelectedItem.value] = this.customTitleInput.value
                    }
                }
                else
                {
                    delete this.customTitles[previousSelectedItem.value]
                }
            }

            if (selectedItem)
            {
                if (selectedItem.customProperties.allows_custom_title)
                {
                    // If there is any stored custom title in the data set of selectedItem then restore that
                    if (typeof this.customTitles[selectedItem.value] !== 'undefined')
                    {
                        this.customTitleInput.value = this.customTitles[selectedItem.value]
                    }
                    else // Or else set the custom title to the warning definition title
                    {
                        this.customTitleInput.value = selectedItem.label
                    }
                }
                else
                {
                    delete this.customTitles[selectedItem.value]
                }
            }

            if (!previousSelectedItem && selectedItem)
            {
                if (selectedItem.customProperties.allows_custom_title)
                {
                    this.showCustomTitleInput()
                }
            }
            else if (previousSelectedItem && !selectedItem)
            {
                this.hideCustomTitleInput()
            }
            else if (previousSelectedItem && selectedItem)
            {
                if (previousSelectedItem.customProperties.allows_custom_title && selectedItem.customProperties.allows_custom_title)
                {
                    // Both the previously selected and newly selected allow custom title.
                    // Force showing
                    this.showCustomTitleInput();
                }
                else if (previousSelectedItem.customProperties.allows_custom_title && !selectedItem.customProperties.allows_custom_title)
                {
                    // The previously selected allowed custom title but the newly selected does not
                    // Hide the custom title input
                    this.hideCustomTitleInput()
                }
                else if (!previousSelectedItem.customProperties.allows_custom_title && selectedItem.customProperties.allows_custom_title)
                {
                    // The previously selected did not allow custom title but the newly selected does
                    // Show the custom title input
                    this.showCustomTitleInput()
                }
            }
        },

        showCustomTitleInput ()
        {
            if (this.customTitleRow.offsetParent !== null)
            {
                return
            }

            if (typeof XF.Animate !== 'function')
            {
                XF.Animate.fadeDown(this.customTitleRow)
            }
            else
            {
                $(this.customTitleRow).xfFadeDown()
            }
        },

        hideCustomTitleInput ()
        {
            if (this.customTitleRow.offsetParent === null)
            {
                return
            }

            if (typeof XF.Animate !== 'function')
            {
                XF.Animate.fadeUp(this.customTitleRow)
            }
            else
            {
                $(this.customTitleRow).xfFadeUp()
            }
        }
    })

    // ################################## SAVE WARNING VIEW PREFERENCE HANDLER ###########################################

    SV.WarningImprovements.SaveWarningViewPref  = XF.Element.newHandler({
        options: {
            warningView: null,
            warningUrl: null
        },

        init ()
        {
            if (this.options.warningUrl === null)
            {
                throw new Error('No warning URL provided.')
            }

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

            XF.ajax('POST', XF.canonicalizeUrl(this.options.warningUrl), {
                view: theTarget.value,
                sv_save_warn_view_pref: true
            }, null, { skipDefaultSuccess: true })
        }
    })

    XF.Element.register('sv-warning-view-select', 'SV.WarningImprovements.WarningSelectView')
    XF.Element.register('warning-title-watcher', 'SV.WarningImprovements.TitleWatcher')
    XF.Element.register('sv-save-warning-view-pref', 'SV.WarningImprovements.SaveWarningViewPref')
}) ()