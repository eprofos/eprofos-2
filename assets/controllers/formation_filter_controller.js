import { Controller } from "@hotwired/stimulus"

/**
 * Formation Filter Controller
 * 
 * Handles real-time filtering of formations using AJAX requests.
 * Provides smooth user experience with loading states and debounced search.
 */
export default class extends Controller {
    static targets = ["search", "category", "level", "sort", "results", "resultsCount", "loading"]
    static values = { url: String }

    /**
     * Controller initialization
     */
    connect() {
        this.timeout = null
        this.debounceDelay = 300 // milliseconds
        
        // Add event listeners for real-time filtering
        this.searchTarget.addEventListener('input', this.debounceFilter.bind(this))
        this.categoryTarget.addEventListener('change', this.filter.bind(this))
        this.levelTarget.addEventListener('change', this.filter.bind(this))
        this.sortTarget.addEventListener('change', this.filter.bind(this))
        
        console.log('Formation filter controller connected')
    }

    /**
     * Disconnect controller and cleanup
     */
    disconnect() {
        if (this.timeout) {
            clearTimeout(this.timeout)
        }
    }

    /**
     * Debounced filter for search input
     * Prevents too many AJAX requests while user is typing
     */
    debounceFilter() {
        if (this.timeout) {
            clearTimeout(this.timeout)
        }
        
        this.timeout = setTimeout(() => {
            this.filter()
        }, this.debounceDelay)
    }

    /**
     * Main filter method
     * Sends AJAX request to filter formations
     */
    async filter() {
        try {
            // Show loading state
            this.showLoading()
            
            // Build query parameters
            const params = new URLSearchParams()
            
            if (this.searchTarget.value.trim()) {
                params.append('search', this.searchTarget.value.trim())
            }
            
            if (this.categoryTarget.value) {
                params.append('category', this.categoryTarget.value)
            }
            
            if (this.levelTarget.value) {
                params.append('level', this.levelTarget.value)
            }
            
            if (this.sortTarget.value) {
                params.append('sort', this.sortTarget.value)
            }
            
            // Make AJAX request
            const url = `${this.urlValue}?${params.toString()}`
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }
            
            const data = await response.json()
            
            // Update results
            this.updateResults(data)
            
            // Update URL without page reload
            this.updateUrl(params)
            
        } catch (error) {
            console.error('Error filtering formations:', error)
            this.showError('Une erreur est survenue lors du filtrage.')
        } finally {
            this.hideLoading()
        }
    }

    /**
     * Update the results display with new data
     * 
     * @param {Object} data - Response data containing HTML and count
     */
    updateResults(data) {
        if (data.html) {
            this.resultsTarget.innerHTML = data.html
        }
        
        if (data.count !== undefined) {
            this.resultsCountTarget.textContent = data.count
        }
        
        // Trigger custom event for other components
        this.dispatch('resultsUpdated', { 
            detail: { 
                count: data.count,
                filters: this.getCurrentFilters()
            } 
        })
    }

    /**
     * Update browser URL without page reload
     * 
     * @param {URLSearchParams} params - Current filter parameters
     */
    updateUrl(params) {
        const newUrl = params.toString() ? 
            `${window.location.pathname}?${params.toString()}` : 
            window.location.pathname
            
        window.history.replaceState({}, '', newUrl)
    }

    /**
     * Get current filter values
     * 
     * @returns {Object} Current filter state
     */
    getCurrentFilters() {
        return {
            search: this.searchTarget.value.trim(),
            category: this.categoryTarget.value,
            level: this.levelTarget.value,
            sort: this.sortTarget.value
        }
    }

    /**
     * Show loading state
     */
    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('d-none')
        }
        
        this.resultsTarget.style.opacity = '0.6'
        this.resultsTarget.style.pointerEvents = 'none'
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('d-none')
        }
        
        this.resultsTarget.style.opacity = '1'
        this.resultsTarget.style.pointerEvents = 'auto'
    }

    /**
     * Show error message
     * 
     * @param {string} message - Error message to display
     */
    showError(message) {
        // Create error alert
        const alert = document.createElement('div')
        alert.className = 'alert alert-danger alert-dismissible fade show'
        alert.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `
        
        // Insert before results
        this.resultsTarget.parentNode.insertBefore(alert, this.resultsTarget)
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove()
            }
        }, 5000)
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        this.searchTarget.value = ''
        this.categoryTarget.value = ''
        this.levelTarget.value = ''
        this.sortTarget.value = 'title'
        
        this.filter()
    }

    /**
     * Reset to default state
     */
    reset() {
        this.clearFilters()
    }
}