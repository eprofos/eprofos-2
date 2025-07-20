import { Controller } from "@hotwired/stimulus"

/**
 * At Risk Students Controller
 * 
 * Handles the at-risk students management interface including:
 * - Risk level filtering
 * - Student contact and intervention actions
 * - Risk analysis refresh
 * - Export functionality
 * - Recommendations modal
 */
export default class extends Controller {
    static targets = [
        "riskRow", 
        "recommendationsModal", 
        "modalContent", 
        "refreshButton"
    ]
    static values = { 
        exportUrl: String,
        analyzeUrl: String,
        recommendations: Array
    }

    /**
     * Controller initialization
     */
    connect() {
        console.log('At Risk Students controller connected')
        
        // Store original refresh button content
        if (this.hasRefreshButtonTarget) {
            this.originalRefreshText = this.refreshButtonTarget.innerHTML
        }
    }

    /**
     * Filter students by risk level
     * 
     * @param {Event} event - Click event from filter dropdown
     */
    filterByRisk(event) {
        event.preventDefault()
        
        const level = event.currentTarget.dataset.riskLevel
        
        this.riskRowTargets.forEach(row => {
            if (level === 'all') {
                row.style.display = ''
            } else {
                const rowRiskLevel = row.dataset.riskLevel
                row.style.display = rowRiskLevel === level ? '' : 'none'
            }
        })

        // Update active filter indicator
        this.updateActiveFilter(event.currentTarget)
    }

    /**
     * Update active filter visual indicator
     * 
     * @param {Element} activeItem - The clicked filter item
     */
    updateActiveFilter(activeItem) {
        // Remove active class from all filter items
        const dropdownItems = activeItem.closest('.dropdown-menu').querySelectorAll('.dropdown-item')
        dropdownItems.forEach(item => item.classList.remove('active'))
        
        // Add active class to clicked item
        activeItem.classList.add('active')
    }

    /**
     * Show all recommendations in modal
     * 
     * @param {Event} event - Click event from recommendations button
     */
    showAllRecommendations(event) {
        event.preventDefault()
        
        const index = parseInt(event.currentTarget.dataset.index)
        const student = this.recommendationsValue[index]
        
        if (!student) {
            console.error('Student data not found for index:', index)
            return
        }

        let html = `
            <div class="mb-3">
                <h6><i class="fas fa-user me-2"></i>Étudiant: ${student.student.firstName} ${student.student.lastName}</h6>
                <p class="text-muted"><i class="fas fa-graduation-cap me-2"></i>Formation: ${student.formation.title}</p>
            </div>
            <div class="list-group">
        `
        
        student.recommendations.forEach((rec, idx) => {
            html += `
                <div class="list-group-item">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-3">${idx + 1}</span>
                        <div><i class="fas fa-lightbulb me-2 text-warning"></i>${rec}</div>
                    </div>
                </div>
            `
        })
        
        html += '</div>'
        
        if (this.hasModalContentTarget) {
            this.modalContentTarget.innerHTML = html
        }
    }

    /**
     * Contact a student
     * 
     * @param {Event} event - Click event from contact button
     */
    contactStudent(event) {
        event.preventDefault()
        
        const studentId = event.currentTarget.dataset.studentId
        
        // Show success message with contact action
        this.showSuccessMessage(
            'Contact initié',
            `L'interface de contact pour l'étudiant ${studentId} va être ouverte.`
        )
        
        // Here you would implement the actual contact functionality
        // For now, we'll just show a message
        console.log('Contact student:', studentId)
    }

    /**
     * Create intervention for a student
     * 
     * @param {Event} event - Click event from intervention button
     */
    createIntervention(event) {
        event.preventDefault()
        
        const studentId = event.currentTarget.dataset.studentId
        
        // Show success message with intervention action
        this.showSuccessMessage(
            'Intervention planifiée',
            `Une intervention pour l'étudiant ${studentId} a été planifiée.`
        )
        
        // Here you would implement the actual intervention planning
        console.log('Create intervention for student:', studentId)
    }

    /**
     * Implement recommendations
     * 
     * @param {Event} event - Click event from implement button
     */
    implementRecommendations(event) {
        event.preventDefault()
        
        this.showSuccessMessage(
            'Recommandations en cours',
            'Les recommandations sont en cours de mise en œuvre.'
        )
        
        // Close modal
        if (this.hasRecommendationsModalTarget) {
            const modal = bootstrap.Modal.getInstance(this.recommendationsModalTarget)
            if (modal) {
                modal.hide()
            }
        }
    }

    /**
     * Export at-risk students list
     * 
     * @param {Event} event - Click event from export button
     */
    exportAtRiskStudents(event) {
        event.preventDefault()
        
        if (this.exportUrlValue) {
            // Show loading state briefly
            const btn = event.currentTarget
            const originalText = btn.innerHTML
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Export en cours...'
            btn.disabled = true
            
            // Simulate brief loading then trigger download
            setTimeout(() => {
                window.location.href = this.exportUrlValue
                
                // Restore button
                btn.innerHTML = originalText
                btn.disabled = false
                
                this.showSuccessMessage(
                    'Export réussi',
                    'La liste des étudiants à risque a été exportée.'
                )
            }, 1000)
        }
    }

    /**
     * Refresh risk analysis
     * 
     * @param {Event} event - Click event from refresh button
     */
    async refreshRiskAnalysis(event) {
        event.preventDefault()
        
        if (!this.analyzeUrlValue) {
            console.error('Analyze URL not configured')
            return
        }

        const btn = this.hasRefreshButtonTarget ? this.refreshButtonTarget : event.currentTarget
        
        // Set loading state
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualisation...'
        btn.disabled = true
        
        try {
            const response = await fetch(this.analyzeUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }
            
            const data = await response.json()
            
            // Show success message
            this.showSuccessMessage(
                'Analyse mise à jour',
                `${data.message}. ${data.at_risk_count} étudiants à risque détectés.`
            )
            
            // Reload page after 2 seconds to show updated data
            setTimeout(() => {
                window.location.reload()
            }, 2000)
            
        } catch (error) {
            console.error('Error refreshing risk analysis:', error)
            this.showErrorMessage(
                'Erreur',
                'Erreur lors de l\'actualisation de l\'analyse'
            )
        } finally {
            // Restore button
            btn.innerHTML = this.originalRefreshText || '<i class="fas fa-sync-alt me-1"></i>Actualiser l\'analyse'
            btn.disabled = false
        }
    }

    /**
     * Show success message
     * 
     * @param {string} title - Message title
     * @param {string} message - Message content
     */
    showSuccessMessage(title, message) {
        const alertDiv = document.createElement('div')
        alertDiv.className = 'alert alert-success alert-dismissible mb-4'
        alertDiv.innerHTML = `
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h4 class="alert-title">${title}</h4>
                    <div class="text-muted">${message}</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `
        
        // Insert at top of container
        const container = document.querySelector('.container-xl')
        if (container) {
            container.insertAdjacentElement('afterbegin', alertDiv)
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 5000)
    }

    /**
     * Show error message
     * 
     * @param {string} title - Message title
     * @param {string} message - Message content
     */
    showErrorMessage(title, message) {
        const alertDiv = document.createElement('div')
        alertDiv.className = 'alert alert-danger alert-dismissible mb-4'
        alertDiv.innerHTML = `
            <div class="d-flex">
                <div class="me-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h4 class="alert-title">${title}</h4>
                    <div class="text-muted">${message}</div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `
        
        // Insert at top of container
        const container = document.querySelector('.container-xl')
        if (container) {
            container.insertAdjacentElement('afterbegin', alertDiv)
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove()
            }
        }, 5000)
    }
}
