import { Controller } from "@hotwired/stimulus"

/**
 * Controller for managing alternance form fields
 * Handles conditional display and percentage validation
 */
export default class extends Controller {
    static targets = ["field", "centerPercentage", "companyPercentage", "fieldsContainer"]

    connect() {
        this.toggle()
    }

    /**
     * Toggle visibility of alternance fields based on checkbox state
     */
    toggle() {
        const checkbox = this.element
        const isAlternance = checkbox.checked
        
        // Show/hide the fields container
        if (this.hasFieldsContainerTarget) {
            const container = this.fieldsContainerTarget
            if (isAlternance) {
                container.style.display = 'block'
                container.classList.remove('hide')
                container.classList.add('show')
            } else {
                container.classList.remove('show')
                container.classList.add('hide')
                setTimeout(() => {
                    container.style.display = 'none'
                }, 300)
            }
        } else {
            // Fallback: show/hide individual field containers
            this.fieldTargets.forEach(field => {
                const container = field.closest('.mb-3, .form-group, .row')
                if (container) {
                    if (isAlternance) {
                        container.style.display = 'block'
                        container.style.opacity = '0'
                        container.style.transition = 'opacity 0.3s ease-in-out'
                        setTimeout(() => {
                            container.style.opacity = '1'
                        }, 10)
                    } else {
                        container.style.opacity = '0'
                        container.style.transition = 'opacity 0.3s ease-in-out'
                        setTimeout(() => {
                            container.style.display = 'none'
                        }, 300)
                    }
                }
            })
        }

        // Clear values if disabling alternance
        if (!isAlternance) {
            this.clearAlternanceFields()
        }

        // Update required attributes
        this.updateRequiredFields(isAlternance)
    }

    /**
     * Update company percentage when center percentage changes
     */
    updateCompanyPercentage() {
        if (this.hasCenterPercentageTarget && this.hasCompanyPercentageTarget) {
            const centerValue = parseInt(this.centerPercentageTarget.value) || 0
            const companyValue = Math.max(0, Math.min(100, 100 - centerValue))
            
            this.companyPercentageTarget.value = companyValue || ''
            this.validatePercentages()
        }
    }

    /**
     * Update center percentage when company percentage changes
     */
    updateCenterPercentage() {
        if (this.hasCenterPercentageTarget && this.hasCompanyPercentageTarget) {
            const companyValue = parseInt(this.companyPercentageTarget.value) || 0
            const centerValue = Math.max(0, Math.min(100, 100 - companyValue))
            
            this.centerPercentageTarget.value = centerValue || ''
            this.validatePercentages()
        }
    }

    /**
     * Validate that percentages sum to 100%
     */
    validatePercentages() {
        if (this.hasCenterPercentageTarget && this.hasCompanyPercentageTarget) {
            const centerValue = parseInt(this.centerPercentageTarget.value) || 0
            const companyValue = parseInt(this.companyPercentageTarget.value) || 0
            const total = centerValue + companyValue

            // Remove existing validation messages
            this.removeValidationMessages()

            if (total !== 100 && (centerValue > 0 || companyValue > 0)) {
                this.showValidationError('Les pourcentages doivent totaliser 100%')
            } else if (total === 100) {
                this.showValidationSuccess()
            }
        }
    }

    /**
     * Clear all alternance field values
     */
    clearAlternanceFields() {
        this.fieldTargets.forEach(field => {
            if (field.type === 'checkbox') {
                field.checked = false
            } else if (field.tagName === 'SELECT') {
                field.selectedIndex = 0
            } else {
                field.value = ''
            }
        })
    }

    /**
     * Update required attributes for alternance fields
     */
    updateRequiredFields(isRequired) {
        // Fields that should be required when alternance is enabled
        const requiredFields = ['alternanceType', 'alternanceRhythm']
        
        this.fieldTargets.forEach(field => {
            const fieldName = field.name || field.id
            if (requiredFields.some(name => fieldName.includes(name))) {
                if (isRequired) {
                    field.setAttribute('required', 'required')
                    this.addRequiredIndicator(field)
                } else {
                    field.removeAttribute('required')
                    this.removeRequiredIndicator(field)
                }
            }
        })
    }

    /**
     * Add visual indicator for required fields
     */
    addRequiredIndicator(field) {
        const label = this.findLabel(field)
        if (label && !label.querySelector('.text-danger')) {
            const asterisk = document.createElement('span')
            asterisk.className = 'text-danger'
            asterisk.textContent = ' *'
            label.appendChild(asterisk)
        }
    }

    /**
     * Remove visual indicator for required fields
     */
    removeRequiredIndicator(field) {
        const label = this.findLabel(field)
        if (label) {
            const asterisk = label.querySelector('.text-danger')
            if (asterisk) {
                asterisk.remove()
            }
        }
    }

    /**
     * Find label associated with a field
     */
    findLabel(field) {
        const fieldId = field.id
        if (fieldId) {
            return document.querySelector(`label[for="${fieldId}"]`)
        }
        
        // Fallback: find label in same container
        const container = field.closest('.mb-3, .form-group, .row')
        return container ? container.querySelector('label') : null
    }

    /**
     * Show validation error message
     */
    showValidationError(message) {
        if (this.hasCompanyPercentageTarget) {
            const container = this.companyPercentageTarget.closest('.mb-3, .form-group, .row')
            if (container && !container.querySelector('.invalid-feedback')) {
                const errorDiv = document.createElement('div')
                errorDiv.className = 'invalid-feedback d-block'
                errorDiv.textContent = message
                container.appendChild(errorDiv)

                // Add error styling to both percentage fields
                this.centerPercentageTarget.classList.add('is-invalid')
                this.companyPercentageTarget.classList.add('is-invalid')
                this.centerPercentageTarget.classList.remove('is-valid')
                this.companyPercentageTarget.classList.remove('is-valid')
            }
        }
    }

    /**
     * Show validation success state
     */
    showValidationSuccess() {
        if (this.hasCenterPercentageTarget && this.hasCompanyPercentageTarget) {
            // Add success styling
            this.centerPercentageTarget.classList.add('is-valid')
            this.companyPercentageTarget.classList.add('is-valid')
            this.centerPercentageTarget.classList.remove('is-invalid')
            this.companyPercentageTarget.classList.remove('is-invalid')

            // Remove error messages
            this.removeValidationMessages()
        }
    }

    /**
     * Remove validation error message
     */
    removeValidationError() {
        if (this.hasCenterPercentageTarget && this.hasCompanyPercentageTarget) {
            // Remove error styling
            this.centerPercentageTarget.classList.remove('is-invalid')
            this.companyPercentageTarget.classList.remove('is-invalid')

            // Remove error messages
            this.removeValidationMessages()
        }
    }

    /**
     * Remove all validation messages
     */
    removeValidationMessages() {
        const containers = [
            this.hasCenterPercentageTarget ? this.centerPercentageTarget.closest('.mb-3, .form-group, .row') : null,
            this.hasCompanyPercentageTarget ? this.companyPercentageTarget.closest('.mb-3, .form-group, .row') : null
        ].filter(Boolean)

        containers.forEach(container => {
            const errorDiv = container.querySelector('.invalid-feedback')
            if (errorDiv) {
                errorDiv.remove()
            }
        })
    }

    /**
     * Handle form submission validation
     */
    validateOnSubmit(event) {
        const checkbox = this.element
        if (checkbox.checked) {
            // Validate required fields
            const requiredFields = this.fieldTargets.filter(field => field.hasAttribute('required'))
            let hasErrors = false

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid')
                    hasErrors = true
                } else {
                    field.classList.remove('is-invalid')
                }
            })

            // Validate percentages
            if (this.hasCenterPercentageTarget && this.hasCompanyPercentageTarget) {
                const centerValue = parseInt(this.centerPercentageTarget.value) || 0
                const companyValue = parseInt(this.companyPercentageTarget.value) || 0
                
                if (centerValue + companyValue !== 100) {
                    this.showValidationError('Les pourcentages doivent totaliser 100%')
                    hasErrors = true
                }
            }

            if (hasErrors) {
                event.preventDefault()
                event.stopPropagation()
                
                // Scroll to first error
                const firstError = this.element.closest('form').querySelector('.is-invalid')
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' })
                    firstError.focus()
                }
            }
        }
    }
}
