import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["selectAll", "contractCheckbox", "bulkSubmit"]

    connect() {
        this.updateBulkButton()
    }

    selectAllChanged() {
        this.contractCheckboxTargets.forEach(checkbox => {
            checkbox.checked = this.selectAllTarget.checked
        })
        this.updateBulkButton()
    }

    checkboxChanged() {
        this.updateBulkButton()
    }

    updateBulkButton() {
        const checkedBoxes = this.contractCheckboxTargets.filter(checkbox => checkbox.checked)
        
        // Enable/disable bulk submit button
        this.bulkSubmitTarget.disabled = checkedBoxes.length === 0
        
        // Update select all checkbox state
        this.selectAllTarget.checked = checkedBoxes.length === this.contractCheckboxTargets.length
        this.selectAllTarget.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < this.contractCheckboxTargets.length
    }
}
