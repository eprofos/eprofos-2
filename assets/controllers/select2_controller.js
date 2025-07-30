import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { 
        url: String, 
        placeholder: String 
    }

    connect() {
        this.initializeSelect2()
    }

    initializeSelect2() {
        if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') {
            // Fallback if Select2 is not available
            console.warn('Select2 is not available')
            return
        }

        const options = {
            placeholder: this.placeholderValue || 'Rechercher...',
            allowClear: true,
            width: '100%'
        }

        if (this.urlValue) {
            options.ajax = {
                url: this.urlValue,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term
                    }
                },
                processResults: function (data) {
                    return {
                        results: data
                    }
                },
                cache: true
            }
            options.minimumInputLength = 2
        }

        $(this.element).select2(options)
    }

    disconnect() {
        if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
            $(this.element).select2('destroy')
        }
    }
}
