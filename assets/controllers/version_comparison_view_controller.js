import { Controller } from "@hotwired/stimulus"

/**
 * Version Comparison View Controller
 * 
 * Handles view mode switching in version comparison page.
 * Manages toggle between side-by-side and unified comparison views.
 */
export default class extends Controller {
    static targets = ["sideBySideView", "unifiedView"]

    /**
     * Controller initialization
     */
    connect() {
        console.log('Version comparison view controller connected')
    }

    /**
     * Switch to side-by-side view mode
     */
    showSideBySide(event) {
        this.sideBySideViewTarget.style.display = 'block'
        this.unifiedViewTarget.style.display = 'none'
        
        // Update radio button state
        this.updateRadioState(event.target)
    }

    /**
     * Switch to unified view mode
     */
    showUnified(event) {
        this.sideBySideViewTarget.style.display = 'none'
        this.unifiedViewTarget.style.display = 'block'
        
        // Update radio button state
        this.updateRadioState(event.target)
    }

    /**
     * Update radio button checked state
     */
    updateRadioState(activeRadio) {
        // Find all radio buttons in the same group
        const radioButtons = this.element.querySelectorAll('input[name="display-mode"]')
        
        radioButtons.forEach(radio => {
            radio.checked = (radio === activeRadio)
        })
    }
}
