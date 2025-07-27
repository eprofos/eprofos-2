import { Controller } from '@hotwired/stimulus'

/**
 * Mentor Dashboard Controller
 * 
 * Handles mentor dashboard functionality including statistics,
 * quick actions, and dashboard widgets
 */
export default class extends Controller {
    static targets = ['statsCard', 'quickAction', 'notificationBadge', 'searchInput']
    static values = { 
        refreshUrl: String,
        searchUrl: String 
    }

    connect() {
        console.log('Mentor Dashboard controller connected')
        this.setupAutoRefresh()
        this.setupSearch()
        this.loadNotifications()
    }

    disconnect() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval)
        }
    }

    // Refresh dashboard statistics
    async refreshStats() {
        if (!this.refreshUrlValue) return

        try {
            const response = await fetch(this.refreshUrlValue, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                this.updateStatsCards(data.stats)
                this.updateNotifications(data.notifications)
            }
        } catch (error) {
            console.error('Error refreshing dashboard:', error)
        }
    }

    // Quick actions
    async createMission(event) {
        event.preventDefault()
        window.location.href = event.target.href
    }

    async createAssignment(event) {
        event.preventDefault()
        window.location.href = event.target.href
    }

    async viewMissions(event) {
        event.preventDefault()
        window.location.href = event.target.href
    }

    async viewAssignments(event) {
        event.preventDefault()
        window.location.href = event.target.href
    }

    // Search functionality
    async search() {
        if (!this.hasSearchInputTarget || !this.searchUrlValue) return

        const query = this.searchInputTarget.value.trim()
        
        if (query.length < 2) {
            this.clearSearchResults()
            return
        }

        try {
            const response = await fetch(`${this.searchUrlValue}?q=${encodeURIComponent(query)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                this.displaySearchResults(data.results)
            }
        } catch (error) {
            console.error('Error searching:', error)
        }
    }

    // Mission status toggle
    async toggleMissionStatus(event) {
        const missionId = event.target.dataset.missionId
        const currentStatus = event.target.dataset.currentStatus === 'true'

        try {
            const response = await fetch(`/mentor/missions/${missionId}/toggle-status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const result = await response.json()
                this.updateMissionStatusDisplay(missionId, result.isActive)
                this.showSuccessMessage(result.message)
            } else {
                this.showErrorMessage('Erreur lors du changement de statut')
            }
        } catch (error) {
            console.error('Error toggling mission status:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Assignment completion
    async markAssignmentCompleted(event) {
        const assignmentId = event.target.dataset.assignmentId

        if (!confirm('Êtes-vous sûr de vouloir marquer cette assignation comme terminée ?')) {
            return
        }

        try {
            const response = await fetch(`/mentor/assignments/${assignmentId}/complete`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const result = await response.json()
                this.updateAssignmentDisplay(assignmentId, result)
                this.showSuccessMessage('Assignation marquée comme terminée')
                this.refreshStats() // Refresh dashboard stats
            } else {
                this.showErrorMessage('Erreur lors de la validation')
            }
        } catch (error) {
            console.error('Error marking assignment as completed:', error)
            this.showErrorMessage('Erreur réseau')
        }
    }

    // Private methods
    setupAutoRefresh() {
        // Refresh dashboard every 5 minutes
        this.refreshInterval = setInterval(() => {
            this.refreshStats()
        }, 5 * 60 * 1000)
    }

    setupSearch() {
        if (this.hasSearchInputTarget) {
            this.searchInputTarget.addEventListener('input', 
                this.debounce(this.search.bind(this), 300)
            )
        }
    }

    async loadNotifications() {
        try {
            const response = await fetch('/mentor/notifications', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })

            if (response.ok) {
                const data = await response.json()
                this.updateNotifications(data.notifications)
            }
        } catch (error) {
            console.error('Error loading notifications:', error)
        }
    }

    updateStatsCards(stats) {
        this.statsCardTargets.forEach(card => {
            const statType = card.dataset.statType
            if (stats[statType] !== undefined) {
                const valueElement = card.querySelector('.stat-value')
                if (valueElement) {
                    valueElement.textContent = stats[statType]
                }

                // Update progress bar if present
                const progressBar = card.querySelector('.progress-bar')
                if (progressBar && stats[statType + '_percentage']) {
                    progressBar.style.width = `${stats[statType + '_percentage']}%`
                }
            }
        })
    }

    updateNotifications(notifications) {
        if (this.hasNotificationBadgeTarget) {
            const unreadCount = notifications.filter(n => !n.read).length
            this.notificationBadgeTarget.textContent = unreadCount
            this.notificationBadgeTarget.style.display = unreadCount > 0 ? 'inline' : 'none'
        }

        // Update notification dropdown if present
        const notificationDropdown = document.querySelector('.notification-dropdown')
        if (notificationDropdown) {
            this.renderNotifications(notificationDropdown, notifications)
        }
    }

    renderNotifications(container, notifications) {
        container.innerHTML = ''
        
        if (notifications.length === 0) {
            container.innerHTML = '<div class="dropdown-item text-muted">Aucune notification</div>'
            return
        }

        notifications.slice(0, 5).forEach(notification => {
            const item = document.createElement('div')
            item.className = `dropdown-item ${notification.read ? '' : 'bg-light'}`
            item.innerHTML = `
                <div class="d-flex">
                    <div class="flex-fill">
                        <div class="font-weight-medium">${notification.title}</div>
                        <div class="text-muted small">${notification.message}</div>
                        <div class="text-muted small">${this.formatDate(notification.created_at)}</div>
                    </div>
                    ${!notification.read ? '<div class="badge bg-primary rounded-pill">●</div>' : ''}
                </div>
            `
            container.appendChild(item)
        })

        if (notifications.length > 5) {
            const moreItem = document.createElement('div')
            moreItem.className = 'dropdown-item text-center'
            moreItem.innerHTML = '<a href="/mentor/notifications" class="text-muted">Voir toutes les notifications</a>'
            container.appendChild(moreItem)
        }
    }

    displaySearchResults(results) {
        // Implementation depends on UI design
        console.log('Search results:', results)
    }

    clearSearchResults() {
        // Implementation depends on UI design
    }

    updateMissionStatusDisplay(missionId, isActive) {
        const statusElements = document.querySelectorAll(`[data-mission-id="${missionId}"] .mission-status`)
        statusElements.forEach(element => {
            element.textContent = isActive ? 'Active' : 'Inactive'
            element.className = `badge ${isActive ? 'bg-success' : 'bg-secondary'}`
        })
    }

    updateAssignmentDisplay(assignmentId, data) {
        const assignmentRow = document.querySelector(`[data-assignment-id="${assignmentId}"]`)
        if (assignmentRow) {
            const statusElement = assignmentRow.querySelector('.assignment-status')
            if (statusElement) {
                statusElement.textContent = 'Terminée'
                statusElement.className = 'badge bg-success'
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

    formatDate(dateString) {
        const date = new Date(dateString)
        return date.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        })
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
