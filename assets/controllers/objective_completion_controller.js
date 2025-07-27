import { Controller } from "@hotwired/stimulus"

/**
 * Objective Completion Controller
 * 
 * Handles the completion state of objectives in mission assignments
 */
export default class extends Controller {
    static targets = ["dateField"]
    
    connect() {
        this.toggleDateField()
    }
    
    toggle(event) {
        this.toggleDateField()
    }
    
    toggleDateField() {
        const checkbox = this.element.querySelector('input[type="checkbox"]')
        
        if (checkbox && this.hasDateFieldTarget) {
            const dateField = this.dateFieldTarget.querySelector('input[type="date"]')
            
            if (dateField) {
                if (checkbox.checked) {
                    // Objective completed - show and enable date field
                    this.dateFieldTarget.style.display = 'block'
                    dateField.disabled = false
                    
                    // Set current date if not already set
                    if (!dateField.value) {
                        const today = new Date().toISOString().split('T')[0]
                        dateField.value = today
                    }
                } else {
                    // Objective not completed - hide date field and clear value
                    this.dateFieldTarget.style.display = 'none'
                    dateField.disabled = true
                    dateField.value = ''
                }
            }
        }
    }
}
