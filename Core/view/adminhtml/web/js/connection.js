/**
 * @api
 */
define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'jquery/ui'
], function ($, alert, confirmation) {
    'use strict';

    $.widget('mage.Connection', {
        options: {
            url: '',
            elementId: '',
            successText: '',
            failedText: '',
            fieldMapping: '',
            txt:''
        },

        /**
         * Bind handlers to events
         */
        _create: function () {
            this._on({
                'click': $.proxy(this._connect, this)
            });
        },

        /**
         * Prepare AJAX request
         * @private
         */
        _connect: function () {
            var result = this.options.failedText,
                element =  $('#' + this.options.elementId),
                self = this,
                params = {},
                msg = '',
                fieldToCheck = this.options.fieldToCheck || 'success';

            element.removeClass('success').addClass('fail');
            $.each($.parseJSON(this.options.fieldMapping), function (key, el) {
                params[key] = $('#' + el).val();
            });

            if(params.store === "0"){
                var content = $.mage.__('Do you want to connect all stores ?');
                if(!self.options.connection){
                    content = $.mage.__('Do you want to disconnect all stores ?');
                }
                confirmation({
                    title: $.mage.__('Are you sure ?'),
                    content: content,
                    actions: {
                        confirm: function() {
                            self._connectAjax(self,params);
                        }
                    },
                    buttons: [{
                        text: $.mage.__('No'),
                        class: 'action-secondary action-dismiss',
                        click: function (event) {
                            this.closeModal(event);
                        }
                    }, {
                        text: $.mage.__('Yes'),
                        class: 'action-primary action-accept',
                        click: function (event) {
                            this.closeModal(event, true);
                        }
                    }]
                });
            }else{
                self._connectAjax(self,params);
            }
        },

        /**
         *  AJAX request to connection
         * @private
         */
        _connectAjax: function (self,params){
            var result = self.options.failedText,
                element =  $('#' + self.options.elementId),
                msg = '',
                fieldToCheck = self.options.fieldToCheck || 'success';

            $.ajax({
                url: self.options.url,
                showLoader: true,
                data: params,
                headers: self.options.headers || {}

            }).done(function (response) {
                if (response[fieldToCheck]) {
                    element.removeClass('fail').addClass('success');
                    result = self.options.successText;
                } else {
                    msg = response.errorMessage;
                    if (msg) {
                        alert({
                            content: msg
                        });
                    }
                }
            }).always(function () {
                $('#' + self.options.elementId + '_result').text(result);
            });
        }
    });
    return $.mage.Connection;
});
