/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE.md file
 * that was distributed with this source code.
 */

/**
 * Create the SV namespace, if it does not already exist.
 */
var SV = SV || {};

/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
  /**
   * Allow toggling between select and radio views for choosing warnings.
   */
  SV.WarningViewToggler = function($toggler) { this.__construct($toggler); };
  SV.WarningViewToggler.prototype =
  {
    __construct: function($toggler)
    {
      this.$toggler = $toggler;

      this.$selectView = $toggler.siblings($toggler.data('selectview'));
      this.$radioView = $toggler.siblings($toggler.data('radioview'));

      this.phrases = {
        'toggleSelect': $toggler.data('toggleselecttext'),
        'toggleRadio':  $toggler.data('toggleradiotext')
      };

      this.storageName = 'xf_sv_warningview';
      this.setting = localStorage.getItem(this.storageName);

      this.init();

      $toggler.on('click', $.context(this, 'eClick'));
    },

    init: function()
    {
      if (!this.setting || !this.setting.length)
      {
        this.setSetting('select');
      }

      if (this.setting === 'select')
      {
        this.$selectView.show();
        this.$radioView.remove();
        this.$toggler.text(this.phrases.toggleRadio);
      }
      else
      {
        this.$selectView.remove();
        this.$toggler.text(this.phrases.toggleSelect);
      }
    },

    toggle: function()
    {
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

    setSetting: function(value)
    {
      this.setting = value;
      localStorage.setItem(this.storageName, this.setting);
    },

    eClick: function(e)
    {
      e.preventDefault();
      this.toggle();
    }
  };

  /**
   * Create a Chosen instance and handle change events.
   */
  SV.WarningSelector = function($selector) { this.__construct($selector); };
  SV.WarningSelector.prototype =
  {
    __construct: function($selector)
    {
      this.$selector = $selector;

      this.$customWarningTitle = $selector.siblings($selector.data(
        'customwarningtitle'
      ));

      this.phrases = {
        'noresults':   $selector.data('noresultstext'),
        'placeholder': $selector.data('placeholdertext')
      };

      this.init();

      $selector.on('change', $.context(this, 'eChange'));
    },

    init: function()
    {
      this.$selector.chosen({
        'width':                   '100%',
        'inherit_select_classes':  true,
        'no_result_text':          this.phrases.noresults,
        'placeholder_text_single': this.phrases.placeholder
      });
    },

    eChange: function(e)
    {
      var $target = $(e.target);
      var id = parseInt($target.val());
      var showCustomTitle = false;
      var $warningDefinition = $('select[name="warning_definition_id"] option[value="' + id + '"].warningDefinition');
      if (id === 0) {
          showCustomTitle = true;
      } else if ($warningDefinition.data('custom-title')) {
          showCustomTitle = true;
      }

      this.$selector.find('option[value="'+id+'"]').trigger('click');

      if (showCustomTitle)
      {
        this.$customWarningTitle.show();

        if (id !== 0) {
            this.$customWarningTitle.val($warningDefinition.text());
        }

        setTimeout($.context(function()
        {
          this.$customWarningTitle.focus();
        }, this), 100);
      }
      else
      {
        this.$customWarningTitle.hide();
      }
    }
  };

  /**
   * Create a jsTree instance and handle events.
   */
  SV.WarningItemTree = function($tree) { this.__construct($tree); };
  SV.WarningItemTree.prototype =
  {
    __construct: function($tree)
    {
      this.$tree = $tree;

      this.overlay = $tree.data('overlay');
      this.$searchInput = $($tree.data('searchinput'));

      this.urls = {
        'load':           $tree.data('loadurl'),
        'sync':           $tree.data('syncurl'),
        'rename':         $tree.data('renameurl'),
        'categoryEdit':   $tree.data('categoryediturl'),
        'categoryDelete': $tree.data('categorydeleteurl'),
        'warningEdit':    $tree.data('warningediturl'),
        'warningDelete':  $tree.data('warningdeleteurl'),
      };

      this.phrases = {
        'rename': $tree.data('renametext'),
        'edit':   $tree.data('edittext'),
        'delete': $tree.data('deletetext')
      };

      this.init();
      this.initSearch();

      $tree.on('ready.jstree', $.context(this, 'eReady'));
      $tree.on('move_node.jstree', $.context(this, 'eMoveNode'));
      $tree.on('rename_node.jstree', $.context(this, 'eRenameNode'));
    },

    init: function()
    {
      XenForo.ajax(this.urls.load, '', $.context(function(ajaxData)
      {
        this.$tree.jstree({
          'plugins': [
            'contextmenu',
            'dnd',
            'search',
            'types',
            'wholerow'
          ],
          'core': {
            'data': ajaxData['tree'],
            'check_callback': function (operation, node, parent)
            {
              if (operation === 'rename_node')
              {
                var id = parseInt(node.id.substr(1));
                if (id === 0)
                {
                  return false;
                }

                return true;
              }

              if (operation === 'move_node')
              {
                if (node.type === 'category')
                {
                  if (parent.parent !== '#' && parent.parent != null)
                  {
                    return false;
                  }
                }

                return true;
              }

              return false;
            },
            'multiple': false,
          },
          'contextmenu': {
            'items': {
              'rename': {
                'label': this.phrases.rename,
                '_disabled': function(data)
                {
                  var inst = $.jstree.reference(data.reference);
                  var node = inst.get_node(data.reference);
                  var id = parseInt(node.id.substr(1));

                  return id === 0;
                },
                'action': function(data)
                {
                  var inst = $.jstree.reference(data.reference);
                  var node = inst.get_node(data.reference);

                  inst.edit(node);
                }
              },
              'edit': {
                'label': this.phrases.edit,
                'action': $.context(function(data)
                {
                  var inst = $.jstree.reference(data.reference);
                  var node = inst.get_node(data.reference);
                  var id = node.id.substr(1);

                  var href;
                  if (node.type === 'category')
                  {
                    href = this.urls.categoryEdit.replace('{id}', id);
                  }
                  else if (node.type === 'definition')
                  {
                    href = this.urls.warningEdit.replace('{id}', id);
                  }

                  window.location = XenForo.canonicalizeUrl(href);
                }, this)
              },
              'delete': {
                'label': this.phrases.delete,
                '_disabled': function(data)
                {
                  var inst = $.jstree.reference(data.reference);
                  var node = inst.get_node(data.reference);
                  var id = parseInt(node.id.substr(1));

                  return id === 0;
                },
                'action': $.context(function(data)
                {
                  var inst = $.jstree.reference(data.reference);
                  var node = inst.get_node(data.reference);
                  var id = node.id.substr(1);

                  var href;
                  if (node.type === 'category')
                  {
                    href = this.urls.categoryDelete.replace('{id}', id);
                  }
                  else if (node.type === 'definition')
                  {
                    href = this.urls.warningDelete.replace('{id}', id);
                  }

                  var overlay = new XenForo.OverlayLoader(
                    $({'href': href}),
                    false,
                    {'speed': XenForo.speed.fast}
                  );
                  overlay.load();
                }, this)
              }
            }
          },
          'dnd': {
            'copy': false,
            'is_draggable': true,
            'touch': 'selected',
            'large_drop_target': true,
            'large_drag_target': true
          },
          'search': {
            'show_only_matches': true
          },
          'state': {
            'key': 'xf_sv_warningitemtree'
          },
          'types': {
            '#': {
              'max_depth': 3,
              'valid_children': ['category']
            },
            'category': {
              'max_depth': 2,
              'valid_children': ['category', 'definition']
            },
            'definition': {
              'icon': 'jstree-file',
              'max_depth': 0,
              'valid_children': []
            }
          }
        });
      }, this));
    },

    initSearch: function()
    {
      var timeout = false;
      this.$searchInput.keyup($.context(function()
      {
        if (timeout)
        {
          clearTimeout(timeout);
        }

        timeout = setTimeout($.context(function()
        {
          var query = this.$searchInput.val();
          this.$tree.jstree(true).search(query);
        }, this), 250);
      }, this));
    },

    sync: function()
    {
      var formData = {
        'tree': this.$tree.jstree(true).get_json('#', {'flat': true})
      };

      XenForo.ajax(this.urls.sync, formData, function()
      {
        console.log('Tree synchronized');
      });
    },

    handleLast: function()
    {
      if (window.location.hash)
      {
        var last = window.location.hash.replace('#_', '');

        var id;
        if (last.indexOf('warning-') === 0)
        {
          id = last.replace('warning-', 'd');
        }
        else if (last.indexOf('category-') === 0)
        {
          id = last.replace('category-', 'c');
        }

        if (id)
        {
          this.$tree.jstree(true).select_node(id);
        }
      }
    },

    eReady: function()
    {
      this.sync();
      if (this.overlay)
      {
        this.$tree.find('.jstree-anchor').addClass('OverlayTrigger');
        this.$tree.xfActivate();
      }

      if (localStorage.getItem('xf_sv_warningitemtree') === null)
      {
        this.$tree.jstree(true).open_all();
      }

      this.handleLast();
    },

    eMoveNode: function()
    {
      this.sync();
    },

    eRenameNode: function(e, data)
    {
      var formData = {
        'node': data.node
      };

      XenForo.ajax(this.urls.rename, formData, function()
      {
        console.log('Node renamed');
      });
    }
  };

  // *********************************************************************

  XenForo.ForceDisablerInit = null;

  XenForo.Disabler = function($input)
  {
    var setStatus = function(e, init)
    {
      if (XenForo.ForceDisablerInit !== null) {
          init = XenForo.ForceDisablerInit;
      }
      //console.info('Disabler %o for child container: %o', $input, $childContainer);

      var $childControls = $childContainer.find('input, select, textarea, button, .inputWrapper, .taggingInput'),
          speed = init ? 0 : XenForo.speed.fast,
          select = function(e)
          {
            var $first = $childContainer.find('input:not([type=hidden], [type=file]), textarea, select, button').first();
            $first.focus();
            // hack to select the end of the value
            var value = $first.val();
            $first.val('');
            $first.val(value);
          };

      if ($input.is(':checked:enabled'))
      {
        $childContainer
            .removeAttr('disabled')
            .removeClass('disabled')
            .trigger('DisablerDisabled');

        $childControls
            .removeAttr('disabled')
            .removeClass('disabled');

        if ($input.hasClass('Hider'))
        {
          if (init)
          {
            $childContainer.show();
          }
          else
          {
            $childContainer.xfFadeDown(speed, init ? null : select);
          }
        }
        else if (!init)
        {
          select.call();
        }
      }
      else
      {
        if ($input.hasClass('Hider'))
        {
          if (init)
          {
            $childContainer.hide();
          }
          else
          {
            $childContainer.xfFadeUp(speed, null, speed, 'easeInBack');
          }
        }

        $childContainer
            .prop('disabled', true)
            .addClass('disabled')
            .trigger('DisablerEnabled');

        $childControls
          .prop('disabled', true)
          .addClass('disabled')
          .each(function(i, ctrl)
          {
            var $ctrl = $(ctrl),
                disabledVal = $ctrl.data('disabled');

            if (disabledVal !== null && typeof(disabledVal) !== 'undefined')
            {
              $ctrl.val(disabledVal);
            }
          });
      }
    },

    $childContainer = $('#' + $input.attr('id') + '_Disabler'),

    $form = $input.closest('form');

    var setStatusDelayed = function()
    {
      setTimeout(setStatus, 0);
    };

    if ($input.is(':radio'))
    {
      $form.find('input:radio[name="' + $input.fieldName() + '"]').click(setStatusDelayed);
    }
    else
    {
      $input.click(setStatusDelayed);
    }

    $form.bind('reset', setStatusDelayed);
    $form.bind('XFRecalculate', function() { setStatus(null, true); });

    setStatus(null, true);

    $childContainer.find('label, input, select, textarea').click(function(e)
    {
      if (!$input.is(':checked'))
      {
        $input.prop('checked', true);
        setStatus();
      }
    });

    this.setStatus = setStatus;
  };

  XenForo.FormFiller = function($form)
  {
    var valueCache = {},
        clicked = null,
        xhr = null,
        preventSubmit = false;

    $form.on('submit', function(e)
    {
      if (preventSubmit)
      {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    });

    function handleValuesResponse(clicked, ajaxData)
    {
      if (XenForo.hasResponseError(ajaxData))
      {
        return false;
      }

      XenForo.ForceDisablerInit = true;

      $.each(ajaxData.formValues, function(selector, value)
      {
        var $ctrl = $form.find(selector);

        if ($ctrl.length)
        {
          if ($ctrl.is(':checkbox, :radio'))
          {
            $ctrl.prop('checked', value).triggerHandler('click');
          }
          else if ($ctrl.is('select, input, textarea'))
          {
            $ctrl.val(value);
          }
        }
      });

      setTimeout(function() {
        XenForo.ForceDisablerInit = null;

        var Disabler = $(clicked).data('XenForo.Disabler');
        if (typeof Disabler === 'object')
        {
            Disabler.setStatus();
        }
        else
        {
            clicked.focus();
        }
      }, 0);
    }

    function handleSelection(e)
    {
      var choice = $(e.target).data('choice') || $(e.target).val();
      if (choice === '')
      {
          return true;
      }

      if (xhr)
      {
          //	xhr.abort();
      }

      if (valueCache[choice])
      {
          handleValuesResponse(this, valueCache[choice]);
      }
      else
      {
        clicked = this;
        preventSubmit = true;

        xhr = XenForo.ajax($form.data('form-filler-url'),
            { choice: choice },
            function(ajaxData, textStatus)
            {
              valueCache[choice] = ajaxData;

              handleValuesResponse(clicked, ajaxData);
            }
        );
        xhr.always(function()
        {
          preventSubmit = false;
        });
      }
    }

    this.addControl = function($control)
    {
      $control.click(handleSelection);
    };
  };
  // *********************************************************************

  XenForo.register('a.WarningViewToggler',   'SV.WarningViewToggler');
  XenForo.register('select.WarningSelector', 'SV.WarningSelector');
  XenForo.register('.WarningItemTree',       'SV.WarningItemTree');
}
(jQuery, this, document);
