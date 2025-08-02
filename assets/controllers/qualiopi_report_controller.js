import { Controller } from "@hotwired/stimulus"
import { Chart } from 'chart.js/auto'

export default class extends Controller {
    static targets = ["progressBar", "score", "filterForm"]
    static values = { 
        exportUrl: String,
        exportFormat: String
    }

    connect() {
        this.initializeTooltips()
        this.animateProgressBars()
        this.setupFilterAutoSubmit()
        this.setupCardHoverEffects()
        this.colorizeComplianceScores()
    }

    // Initialize Bootstrap tooltips
    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }

    // Animate progress bars on load
    animateProgressBars() {
        this.progressBarTargets.forEach(bar => {
            const width = bar.style.width
            bar.style.width = '0%'
            setTimeout(() => {
                bar.style.width = width
                bar.style.transition = 'width 1s ease-in-out'
            }, 100)
        })
    }

    // Setup auto-submit for filter form
    setupFilterAutoSubmit() {
        if (this.hasFilterFormTarget) {
            const filterInputs = this.filterFormTarget.querySelectorAll('select, input[type="date"]')
            filterInputs.forEach(input => {
                input.addEventListener('change', () => {
                    setTimeout(() => {
                        this.filterFormTarget.submit()
                    }, 100)
                })
            })
        }
    }

    // Setup card hover effects
    setupCardHoverEffects() {
        const cards = document.querySelectorAll('.card')
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)'
                card.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)'
                card.style.transition = 'all 0.3s ease'
            })
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)'
                card.style.boxShadow = ''
            })
        })
    }

    // Colorize compliance scores based on value
    colorizeComplianceScores() {
        this.scoreTargets.forEach(score => {
            const value = parseFloat(score.textContent)
            score.classList.remove('text-success', 'text-warning', 'text-danger')
            
            if (value >= 90) {
                score.classList.add('text-success')
            } else if (value >= 70) {
                score.classList.add('text-warning')
            } else {
                score.classList.add('text-danger')
            }
        })
    }

    // Export Qualiopi report
    exportReport(event) {
        event.preventDefault()
        
        const format = event.currentTarget.dataset.format
        const formData = new FormData()
        
        // Get filter values
        const formation = document.getElementById('formation')?.value || ''
        const startDate = document.getElementById('start_date')?.value || ''
        const endDate = document.getElementById('end_date')?.value || ''
        
        // Set parameters
        formData.append('format', format)
        formData.append('formation', formation)
        formData.append('start_date', startDate)
        formData.append('end_date', endDate)
        
        // Add Qualiopi-specific fields
        const fields = [
            'student_name', 'student_email', 'formation_title', 'session_name',
            'enrollment_status', 'enrolled_at', 'completed_at', 'progress_percentage'
        ]
        
        fields.forEach(field => {
            formData.append('fields[]', field)
        })
        
        // Create and submit form
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = this.exportUrlValue
        
        // Convert FormData to form inputs
        for (let [key, value] of formData.entries()) {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = key
            input.value = value
            form.appendChild(input)
        }
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
    }

    // Generate Qualiopi Certificate
    generateCertificate(event) {
        event.preventDefault()
        
        const btn = event.currentTarget
        const icon = btn.querySelector('i')
        const originalClass = icon.className
        
        // Add spinner
        icon.className = 'fas fa-spinner fa-spin'
        btn.disabled = true
        
        // Simulate certificate generation
        setTimeout(() => {
            this.showNotification('Certificat Qualiopi généré avec succès !', 'success')
            icon.className = originalClass
            btn.disabled = false
        }, 2000)
    }

    // Schedule Audit
    scheduleAudit(event) {
        event.preventDefault()
        
        const btn = event.currentTarget
        const icon = btn.querySelector('i')
        const originalClass = icon.className
        
        // Add spinner
        icon.className = 'fas fa-spinner fa-spin'
        btn.disabled = true
        
        // Simulate audit scheduling
        setTimeout(() => {
            this.showNotification('Audit Qualiopi planifié pour le prochain trimestre.', 'info')
            icon.className = originalClass
            btn.disabled = false
        }, 1500)
    }

    // Refresh Qualiopi data
    refreshData(event) {
        event.preventDefault()
        
        const btn = event.currentTarget
        const icon = btn.querySelector('i')
        
        // Add spinner
        icon.className = 'fas fa-spinner fa-spin'
        btn.disabled = true
        
        // Reload page
        setTimeout(() => {
            window.location.reload()
        }, 1000)
    }

    // Show notification (can be replaced with toast system)
    showNotification(message, type = 'info') {
        // For now, use alert - can be replaced with proper toast notifications
        alert(message)
        
        // TODO: Implement proper toast notification system
        // Example with Bootstrap toast:
        /*
        const toastContainer = document.querySelector('.toast-container') || this.createToastContainer()
        const toast = this.createToast(message, type)
        toastContainer.appendChild(toast)
        new bootstrap.Toast(toast).show()
        */
    }

    // Helper method to create toast container (if implementing toast system)
    createToastContainer() {
        const container = document.createElement('div')
        container.className = 'toast-container position-fixed top-0 end-0 p-3'
        document.body.appendChild(container)
        return container
    }

    // Helper method to create toast element (if implementing toast system)
    createToast(message, type) {
        const toast = document.createElement('div')
        toast.className = `toast align-items-center text-white bg-${type} border-0`
        toast.setAttribute('role', 'alert')
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `
        return toast
    }
}
