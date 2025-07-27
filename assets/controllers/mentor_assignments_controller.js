import { Controller } from '@hotwired/stimulus'

/**
 * Mentor Assignment Management Controller
 * 
 * Handles assignment filtering, progress updates, and bulk operations
 */
export default class extends Controller {
    static targets = ['filterForm', 'assignmentsList', 'progressBar', 'bulkActions']
    static values = { 
        updateUrl: String,
        filterUrl: String 
    }

    connect() {
        console.log('Mentor Assignment controller connected')
        this.setupFilterHandlers()
        this.setupProgressHandlers()
    }

    // Filter assignments based on form inputs
    async filterAssignments() {
        if (!this.hasFilterFormTarget) return

        const formData = new FormData(this.filterFormTarget)
        const params = new URLSearchParams(formData)
        
        try {
            const response = await fetch(`${this.filterUrlValue}?${params}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const html = await response.text()
                if (this.hasAssignmentsListTarget) {
                    this.assignmentsListTarget.innerHTML = html
                }
            }
        } catch (error) {
            console.error('Error filtering assignments:', error)
        }
    }

    // Update assignment progress
    async updateProgress(event) {
        const assignmentId = event.target.dataset.assignmentId
        const progress = event.target.value

        if (!assignmentId || progress === '') return

        const data = {
            completionRate: progress
        }

        try {
            const response = await fetch(`${this.updateUrlValue}/${assignmentId}/progress`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })

            if (response.ok) {
                const result = await response.json()
                this.updateProgressDisplay(assignmentId, result)
                this.showSuccessMessage('Progression mise à jour')
            } else {
                this.showErrorMessage('Erreur lors de la mise à jour')
            }
        } catch (error) {
            console.error('Error updating progress:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Mark assignment as completed
    async markCompleted(event) {
        const assignmentId = event.target.dataset.assignmentId

        if (!assignmentId) return

        if (!confirm('Êtes-vous sûr de vouloir marquer cette assignation comme terminée ?')) {
            return
        }

        try {
            const response = await fetch(`${this.updateUrlValue}/${assignmentId}/complete`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                // Refresh the page or update the UI
                window.location.reload()
            } else {
                this.showErrorMessage('Erreur lors de la validation')
            }
        } catch (error) {
            console.error('Error marking as completed:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Bulk operations
    selectAll(event) {
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"][name="assignment_ids[]"]')
        checkboxes.forEach(cb => cb.checked = event.target.checked)
        this.updateBulkActions()
    }

    selectAssignment() {
        this.updateBulkActions()
    }

    async bulkUpdate(event) {
        const action = event.target.dataset.action
        const selectedIds = this.getSelectedAssignments()

        if (selectedIds.length === 0) {
            this.showErrorMessage('Veuillez sélectionner au moins une assignation')
            return
        }

        if (!confirm(`Êtes-vous sûr de vouloir ${action} ${selectedIds.length} assignation(s) ?`)) {
            return
        }

        try {
            const response = await fetch(`${this.updateUrlValue}/bulk`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: action,
                    assignment_ids: selectedIds
                })
            })

            if (response.ok) {
                window.location.reload()
            } else {
                this.showErrorMessage('Erreur lors de l\'opération groupée')
            }
        } catch (error) {
            console.error('Error in bulk operation:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Private methods
    setupFilterHandlers() {
        if (this.hasFilterFormTarget) {
            const inputs = this.filterFormTarget.querySelectorAll('select, input')
            inputs.forEach(input => {
                input.addEventListener('change', this.debounce(this.filterAssignments.bind(this), 300))
            })
        }
    }

    setupProgressHandlers() {
        // Add listeners for progress inputs
        const progressInputs = this.element.querySelectorAll('input[data-assignment-id]')
        progressInputs.forEach(input => {
            input.addEventListener('change', this.updateProgress.bind(this))
        })
    }

    updateProgressDisplay(assignmentId, data) {
        const progressBar = this.element.querySelector(`[data-assignment-id="${assignmentId}"] .progress-bar`)
        const progressText = this.element.querySelector(`[data-assignment-id="${assignmentId}"] .progress-text`)

        if (progressBar) {
            progressBar.style.width = `${data.completionRate}%`
            progressBar.className = `progress-bar ${this.getProgressBarClass(data.completionRate)}`
        }

        if (progressText) {
            progressText.textContent = `${data.completionRate}%`
        }
    }

    getProgressBarClass(rate) {
        if (rate >= 100) return 'bg-success'
        if (rate >= 75) return 'bg-info'
        if (rate >= 50) return 'bg-warning'
        return 'bg-danger'
    }

    getSelectedAssignments() {
        const checkboxes = this.element.querySelectorAll('input[type="checkbox"][name="assignment_ids[]"]:checked')
        return Array.from(checkboxes).map(cb => cb.value)
    }

    updateBulkActions() {
        const selectedCount = this.getSelectedAssignments().length
        if (this.hasBulkActionsTarget) {
            this.bulkActionsTarget.style.display = selectedCount > 0 ? 'block' : 'none'
            const countElement = this.bulkActionsTarget.querySelector('.selected-count')
            if (countElement) {
                countElement.textContent = selectedCount
            }
        }
    }

    showSuccessMessage(message) {
        this.showMessage(message, 'success')
    }

    showErrorMessage(message) {
        this.showMessage(message, 'danger')
    }

    showMessage(message, type) {
        // Create a toast notification
        const toast = document.createElement('div')
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `

        document.body.appendChild(toast)

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast)
            }
        }, 5000)
    }

    debounce(func, wait) {
        let timeout
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout)
                func(...args)
            }
            clearTimeout(timeout)
            timeout = setTimeout(later, wait)
        }
    }
}
