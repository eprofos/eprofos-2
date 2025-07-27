import { Controller } from '@hotwired/stimulus'

/**
 * Student Alternance Controller
 * 
 * Handles student alternance dashboard functionality including
 * mission progress updates, statistics refresh, and interactions
 */
export default class extends Controller {
    static targets = ['progressInput', 'statsCard', 'missionCard']
    static values = { 
        updateUrl: String,
        statsUrl: String 
    }

    connect() {
        console.log('Student Alternance controller connected')
        this.setupProgressHandlers()
        this.loadStats()
    }

    // Update mission progress
    async updateProgress(event) {
        const missionId = event.target.dataset.missionId
        const progress = parseInt(event.target.value)
        const selfAssessment = event.target.closest('.mission-card')
            ?.querySelector('textarea[name="self_assessment"]')?.value || ''

        if (!missionId || isNaN(progress)) return

        const formData = new FormData()
        formData.append('completion_rate', progress)
        formData.append('self_assessment', selfAssessment)

        try {
            const response = await fetch(`${this.updateUrlValue}/${missionId}/progress`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                this.updateProgressDisplay(missionId, progress)
                this.showSuccessMessage('Progression mise à jour avec succès')
                
                // Refresh stats
                setTimeout(() => this.loadStats(), 1000)
            } else {
                this.showErrorMessage('Erreur lors de la mise à jour')
            }
        } catch (error) {
            console.error('Error updating progress:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Quick progress update buttons
    async quickUpdateProgress(event) {
        const missionId = event.target.dataset.missionId
        const progress = parseInt(event.target.dataset.progress)

        if (!missionId || isNaN(progress)) return

        const formData = new FormData()
        formData.append('completion_rate', progress)

        try {
            const response = await fetch(`${this.updateUrlValue}/${missionId}/progress`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                this.updateProgressDisplay(missionId, progress)
                this.showSuccessMessage(`Progression mise à ${progress}%`)
                
                // Update the progress input if it exists
                const progressInput = document.querySelector(`input[data-mission-id="${missionId}"]`)
                if (progressInput) {
                    progressInput.value = progress
                }

                // Refresh stats
                setTimeout(() => this.loadStats(), 1000)
            } else {
                this.showErrorMessage('Erreur lors de la mise à jour')
            }
        } catch (error) {
            console.error('Error updating progress:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Load dashboard statistics
    async loadStats() {
        if (!this.statsUrlValue) return

        try {
            const response = await fetch(this.statsUrlValue, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                this.updateStatsDisplay(data.stats)
            }
        } catch (error) {
            console.error('Error loading stats:', error)
        }
    }

    // View mission details
    viewMission(event) {
        const missionId = event.target.dataset.missionId
        if (missionId) {
            window.location.href = `/student/alternance/missions/${missionId}`
        }
    }

    // View assessment details
    viewAssessment(event) {
        const assessmentId = event.target.dataset.assessmentId
        if (assessmentId) {
            window.location.href = `/student/alternance/assessments/${assessmentId}`
        }
    }

    // View meeting details
    viewMeeting(event) {
        const meetingId = event.target.dataset.meetingId
        if (meetingId) {
            window.location.href = `/student/alternance/meetings/${meetingId}`
        }
    }

    // Save self-assessment note
    async saveSelfAssessment(event) {
        const missionId = event.target.dataset.missionId
        const selfAssessment = event.target.value

        if (!missionId) return

        // Debounce the save operation
        clearTimeout(this.saveTimeout)
        this.saveTimeout = setTimeout(async () => {
            try {
                const formData = new FormData()
                formData.append('self_assessment', selfAssessment)

                const response = await fetch(`${this.updateUrlValue}/${missionId}/self-assessment`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })

                if (response.ok) {
                    this.showSuccessMessage('Auto-évaluation sauvegardée', 2000)
                }
            } catch (error) {
                console.error('Error saving self-assessment:', error)
            }
        }, 1000)
    }

    // Private methods
    setupProgressHandlers() {
        // Add change listeners to progress inputs
        this.progressInputTargets.forEach(input => {
            input.addEventListener('change', this.debounce(this.updateProgress.bind(this), 500))
        })

        // Add listeners for self-assessment textareas
        const selfAssessmentInputs = this.element.querySelectorAll('textarea[name="self_assessment"]')
        selfAssessmentInputs.forEach(textarea => {
            textarea.addEventListener('input', this.saveSelfAssessment.bind(this))
        })
    }

    updateProgressDisplay(missionId, progress) {
        // Update progress bar
        const progressBar = document.querySelector(`[data-mission-id="${missionId}"] .progress-bar`)
        if (progressBar) {
            progressBar.style.width = `${progress}%`
            progressBar.className = `progress-bar ${this.getProgressBarClass(progress)}`
        }

        // Update progress text
        const progressText = document.querySelector(`[data-mission-id="${missionId}"] .progress-text`)
        if (progressText) {
            progressText.textContent = `${progress}%`
        }

        // Update mission status if completed
        if (progress >= 100) {
            const statusBadge = document.querySelector(`[data-mission-id="${missionId}"] .mission-status`)
            if (statusBadge) {
                statusBadge.textContent = 'Terminée'
                statusBadge.className = 'badge bg-success mission-status'
            }
        }
    }

    updateStatsDisplay(stats) {
        this.statsCardTargets.forEach(card => {
            const statType = card.dataset.statType
            if (stats[statType] !== undefined) {
                const valueElement = card.querySelector('.stat-value, .h1')
                if (valueElement) {
                    let value = stats[statType]
                    if (statType.includes('percentage') || statType.includes('progress')) {
                        value += '%'
                    }
                    valueElement.textContent = value
                }

                // Update progress bar if present
                const progressBar = card.querySelector('.progress-bar')
                if (progressBar && typeof stats[statType] === 'number') {
                    const percentage = statType.includes('percentage') ? stats[statType] : 100
                    progressBar.style.width = `${percentage}%`
                }
            }
        })
    }

    getProgressBarClass(rate) {
        if (rate >= 100) return 'bg-success'
        if (rate >= 75) return 'bg-info'
        if (rate >= 50) return 'bg-warning'
        return 'bg-danger'
    }

    showSuccessMessage(message, duration = 5000) {
        this.showMessage(message, 'success', duration)
    }

    showErrorMessage(message, duration = 5000) {
        this.showMessage(message, 'danger', duration)
    }

    showMessage(message, type, duration = 5000) {
        // Create a toast notification
        const toast = document.createElement('div')
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `

        document.body.appendChild(toast)

        // Auto-remove after specified duration
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast)
            }
        }, duration)
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
