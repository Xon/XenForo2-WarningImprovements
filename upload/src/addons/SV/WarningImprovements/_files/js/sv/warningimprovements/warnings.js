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

        onAddItem (event)
        {
            this._svWarningImprovementsOnAddItem(event)

            this.onAddRemoveItem(event)
        },

        onRemoveItem (event)
        {
            this._svWarningImprovementsOnRemoveItem(event)

            this.onAddRemoveItem(event)
        },

        onAddRemoveItem(event)
        {
            if (!this.choices)
            {
                throw new Error('Choices not setup.');
            }

            const previousSelectedItem = this.choices._currentState.items.find(() => true)
            const selectedItem = this.choices._currentState.choices.find((choice) => {
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

                    this.showCustomTitleInput()
                }
                else
                {
                    delete this.customTitles[selectedItem.value]

                    this.hideCustomTitleInput()
                }
            }
            else
            {
                if (previousSelectedItem.customProperties.allows_custom_title)
                {
                    // If someone clicks on the "X" button when any warning definition is selected leaving none selected
                    // The custom title row needs to be hidden AND marked as disabled
                    this.hideCustomTitleInput()
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

    XF.Element.register('sv-warning-view-select', 'SV.WarningImprovements.WarningSelectView')
    XF.Element.register('warning-title-watcher', 'SV.WarningImprovements.TitleWatcher')
    XF.Element.register('sv-save-warning-view-pref', 'SV.WarningImprovements.SaveWarningViewPref')
}) ()