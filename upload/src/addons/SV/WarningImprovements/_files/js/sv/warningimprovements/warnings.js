// noinspection ES6ConvertVarToLetConst
var SV = window.SV || {};
SV.$ = SV.$ || window.jQuery || null;
SV.extendObject = SV.extendObject || XF.extendObject || jQuery.extend;
SV.WarningImprovements = SV.WarningImprovements || {};

// noinspection JSUnusedLocalSymbols
(function()
{
    "use strict"
    const $ = SV.$, xf22 = typeof XF.on !== 'function';

    // ################################## WARNING SELECT HANDLER ###########################################

    SV.WarningImprovements.SelectViewOpts = {
        customTitleRowSelector: null,
        customTitleInputSelector: 'input[type=text][name=custom_title]',

        publicWarningSelector: 'input[name=\'action_options[public_message]\']',
        copyTitle: true // Default value must match with that of the option: sv_warningimprovements_copy_title
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
        previousSelectedItem: null,

        customTitles: [],

        init ()
        {
            const rowSelector = this.options.customTitleRowSelector
            if (rowSelector === null)
            {
                throw new Error('Custom title row selector missing.')
            }

            this.customTitleRow = XF.findRelativeIf(rowSelector, this.target || this.$target)
            if (xf22)
            {
                this.customTitleRow = this.customTitleRow.get(0)
            }

            if (this.customTitleRow === null)
            {
                throw new Error('Missing custom title row.')
            }

            this.customTitleInput = XF.findRelativeIf(this.options.customTitleInputSelector, this.target || this.$target)
            if (xf22)
            {
                this.customTitleInput = this.customTitleInput.get(0)
            }

            if (this.customTitleInput === null)
            {
                throw new Error('Custom title input missing.')
            }

            this.publicWarning = XF.findRelativeIf(this.options.publicWarningSelector, this.target || this.$target)
            if (xf22)
            {
                this.publicWarning = this.publicWarning.get(0)
            }

            if (this.publicWarning === null)
            {
                throw new Error('Could not find public warning input')
            }

            this._svWarningImprovementsInit()
        },

        getConfig ()
        {
            const config = this._svWarningImprovementsGetConfig()

            config.silent = true;

            return config
        },

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

                this.setPublicMessage(selectedItem.label)
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

            if (xf22)
            {
                $(this.customTitleRow).xfFadeDown()
            }
            else
            {
                XF.Animate.fadeDown(this.customTitleRow)
            }
        },

        hideCustomTitleInput ()
        {
            if (this.customTitleRow.offsetParent === null)
            {
                return
            }

            if (xf22)
            {
                $(this.customTitleRow).xfFadeUp()
            }
            else
            {
                XF.Animate.fadeUp(this.customTitleRow)
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

    // ###################################### WARNING TITLE WATCHER HANDLER ############################################

    SV.WarningImprovements.TitleWatcher  = XF.Element.newHandler({
        eventNameSpace: 'WarningTitleWatcher',
        options: {
            publicWarningSelector: 'input[name=\'action_options[public_message]\']',
            copyTitle: true, // Default value must match with that of the option: sv_warningimprovements_copy_title
            warningDefTitle: null,
        },
        publicWarning: null,

        init ()
        {
            this.publicWarning = XF.findRelativeIf(this.options.publicWarningSelector, this.target || this.$target)
            if (xf22)
            {
                this.publicWarning = this.publicWarning.get(0)
            }

            if (this.publicWarning === null)
            {
                console.error('Could not find public warning input')
                return
            }

            if (xf22) // XF 2.2
            {
                this.$target.on('change input', this.onInputChangeOrClick.bind(this))

                if (this.$target.is('input:radio'))
                {
                    this.$target.on('click', this.onInputChangeOrClick.bind(this))
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
            if (this.options.warningDefTitle === null)
            {
                throw new Error('No warning definition title provided.')
            }

            this.setPublicMessage(this.options.warningDefTitle)
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

    // ################################## SAVE WARNING VIEW PREFERENCE HANDLER #########################################

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

            if (xf22) // XF 2.2
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
    XF.Element.register('sv-warning-title-watcher', 'SV.WarningImprovements.TitleWatcher')
    XF.Element.register('sv-save-warning-view-pref', 'SV.WarningImprovements.SaveWarningViewPref')
}) ()