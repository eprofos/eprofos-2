import { Controller } from "@hotwired/stimulus"

/**
 * Metadata Statistics Controller
 * 
 * Handles metadata key statistics display via AJAX.
 * Shows statistics in a modal when clicking on metadata keys.
 */
export default class extends Controller {
    static targets = ["modal", "content", "title", "loading"]
    static values = { 
        url: String,
        modalTitle: String
    }

    /**
     * Controller initialization
     */
    connect() {
        console.log('Metadata statistics controller connected')
    }

    /**
     * Show statistics for a specific metadata key
     */
    async showStatistics(event) {
        event.preventDefault()
        
        const key = event.currentTarget.dataset.key
        if (!key) {
            console.error('No metadata key provided')
            return
        }

        try {
            // Show modal and loading state
            this.openModal(key)
            this.showLoading()

            // Make AJAX request for statistics
            const url = `/admin/document-metadata/statistics/${encodeURIComponent(key)}`
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }

            const statistics = await response.json()
            
            // Display statistics
            this.displayStatistics(key, statistics)
            
        } catch (error) {
            console.error('Error loading metadata statistics:', error)
            this.showError('Erreur lors du chargement des statistiques.')
        } finally {
            this.hideLoading()
        }
    }

    /**
     * Open the statistics modal
     */
    openModal(key) {
        // Find the modal element
        const modalElement = document.getElementById('statisticsModal')
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement)
            modal.show()
        }
        
        const titleElement = modalElement?.querySelector('[data-metadata-statistics-target="title"]')
        if (titleElement) {
            titleElement.textContent = `Statistiques pour "${key}"`
        }
    }

    /**
     * Display statistics in the modal content
     */
    displayStatistics(key, stats) {
        const modalElement = document.getElementById('statisticsModal')
        const contentElement = modalElement?.querySelector('[data-metadata-statistics-target="content"]')
        if (!contentElement) return

        let html = `
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h3 class="text-primary mb-1">${stats.total_count}</h3>
                            <small class="text-muted">Utilisations totales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <h3 class="text-info mb-1">${stats.documents_count}</h3>
                            <small class="text-muted">Documents concernés</small>
                        </div>
                    </div>
                </div>
            </div>
        `

        // Data types distribution
        if (Object.keys(stats.data_types).length > 0) {
            html += `
                <div class="mt-4">
                    <h6 class="mb-3">Répartition par type de données</h6>
                    <div class="row g-2">
            `
            
            for (const [type, count] of Object.entries(stats.data_types)) {
                const percentage = Math.round((count / stats.total_count) * 100)
                html += `
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                            <span class="badge bg-secondary">${type}</span>
                            <div class="text-end">
                                <div class="fw-medium">${count}</div>
                                <small class="text-muted">${percentage}%</small>
                            </div>
                        </div>
                    </div>
                `
            }
            
            html += `
                    </div>
                </div>
            `
        }

        // Value distribution
        if (Object.keys(stats.value_distribution).length > 0) {
            html += `
                <div class="mt-4">
                    <h6 class="mb-3">Valeurs les plus utilisées</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Valeur</th>
                                    <th class="text-end">Nombre</th>
                                    <th class="text-end">Pourcentage</th>
                                </tr>
                            </thead>
                            <tbody>
            `
            
            for (const [value, count] of Object.entries(stats.value_distribution)) {
                const percentage = Math.round((count / stats.total_count) * 100)
                const displayValue = value === '(vide)' ? '<em>Valeur vide</em>' : value
                html += `
                    <tr>
                        <td class="text-truncate" style="max-width: 200px;" title="${value}">${displayValue}</td>
                        <td class="text-end">${count}</td>
                        <td class="text-end">${percentage}%</td>
                    </tr>
                `
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `
        }

        contentElement.innerHTML = html
    }

    /**
     * Show loading state
     */
    showLoading() {
        const modalElement = document.getElementById('statisticsModal')
        if (modalElement) {
            const loadingElement = modalElement.querySelector('[data-metadata-statistics-target="loading"]')
            if (loadingElement) {
                loadingElement.classList.remove('d-none')
            }
            
            const contentElement = modalElement.querySelector('[data-metadata-statistics-target="content"]')
            if (contentElement) {
                contentElement.innerHTML = ''
            }
        }
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        const modalElement = document.getElementById('statisticsModal')
        if (modalElement) {
            const loadingElement = modalElement.querySelector('[data-metadata-statistics-target="loading"]')
            if (loadingElement) {
                loadingElement.classList.add('d-none')
            }
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        const modalElement = document.getElementById('statisticsModal')
        if (modalElement) {
            const contentElement = modalElement.querySelector('[data-metadata-statistics-target="content"]')
            if (contentElement) {
                contentElement.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${message}
                    </div>
                `
            }
        }
    }

    /**
     * Close modal
     */
    closeModal() {
        const modalElement = document.getElementById('statisticsModal')
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement)
            if (modal) {
                modal.hide()
            }
        }
    }
}
