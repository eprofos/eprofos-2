import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["startDate", "endDate", "durationDisplay", "form"]
    
    connect() {
        this.setupDurationCalculation()
        this.setupFormValidation()
    }
    
    setupDurationCalculation() {
        if (this.hasStartDateTarget && this.hasEndDateTarget && this.hasDurationDisplayTarget) {
            this.startDateTarget.addEventListener('change', () => this.calculateDuration())
            this.endDateTarget.addEventListener('change', () => this.calculateDuration())
        }
    }
    
    setupFormValidation() {
        if (this.hasFormTarget && this.formTarget.classList.contains('needs-validation')) {
            this.formTarget.addEventListener('submit', (event) => this.validateForm(event))
        }
    }
    
    calculateDuration() {
        const startDate = new Date(this.startDateTarget.value)
        const endDate = new Date(this.endDateTarget.value)
        
        if (startDate && endDate && endDate > startDate) {
            const diffTime = Math.abs(endDate - startDate)
            const diffMonths = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 30))
            this.durationDisplayTarget.innerHTML = `${diffMonths} mois`
        } else if (this.startDateTarget.value || this.endDateTarget.value) {
            this.durationDisplayTarget.innerHTML = '<span class="text-muted">Sera calculée à partir des dates</span>'
        }
    }
    
    validateForm(event) {
        if (!this.formTarget.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
        }
        this.formTarget.classList.add('was-validated')
    }
}
