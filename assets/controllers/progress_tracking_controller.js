import { Controller } from "@hotwired/stimulus"

/**
 * Progress Tracking Controller
 * 
 * Enhanced real-time progress tracking with granular monitoring,
 * time tracking, and milestone celebrations as specified in Issue #67.
 */
export default class extends Controller {
    static targets = ["progressBar", "completionStatus", "timeDisplay", "milestoneContainer"]
    static values = { 
        contentId: Number,
        contentType: String,
        progressUrl: String,
        statsUrl: String,
        formationId: Number,
        completed: Boolean
    }

    connect() {
        this.startTimeTracking()
        this.setupIdleDetection()
        this.setupProgressPolling()
        this.initializeProgress()
    }

    disconnect() {
        this.stopTimeTracking()
        if (this.progressInterval) {
            clearInterval(this.progressInterval)
        }
        if (this.idleTimer) {
            clearTimeout(this.idleTimer)
        }
    }

    // Time tracking functionality
    startTimeTracking() {
        this.startTime = Date.now()
        this.totalTime = 0
        this.isActive = true
        this.lastActivity = Date.now()

        // Update time display every second
        this.timeInterval = setInterval(() => {
            if (this.isActive) {
                this.updateTimeDisplay()
            }
        }, 1000)

        // Save progress every 30 seconds
        this.saveInterval = setInterval(() => {
            this.saveProgressData()
        }, 30000)
    }

    stopTimeTracking() {
        if (this.timeInterval) {
            clearInterval(this.timeInterval)
        }
        if (this.saveInterval) {
            clearInterval(this.saveInterval)
        }
        this.saveProgressData() // Final save
    }

    setupIdleDetection() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart']
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.handleActivity()
            }, { passive: true })
        })

        // Check for idle state every 5 seconds
        this.idleCheckInterval = setInterval(() => {
            const timeSinceLastActivity = Date.now() - this.lastActivity
            if (timeSinceLastActivity > 60000) { // 1 minute idle
                this.setIdle()
            }
        }, 5000)
    }

    handleActivity() {
        this.lastActivity = Date.now()
        if (!this.isActive) {
            this.setActive()
        }
    }

    setIdle() {
        this.isActive = false
        this.element.classList.add('user-idle')
    }

    setActive() {
        this.isActive = true
        this.element.classList.remove('user-idle')
    }

    updateTimeDisplay() {
        if (!this.isActive) return

        const currentTime = Date.now()
        const sessionTime = currentTime - this.startTime
        this.totalTime = Math.floor(sessionTime / 1000) // Convert to seconds

        if (this.hasTimeDisplayTarget) {
            this.timeDisplayTarget.textContent = this.formatTime(this.totalTime)
        }
    }

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600)
        const minutes = Math.floor((seconds % 3600) / 60)
        const secs = seconds % 60

        if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`
        } else {
            return `${secs}s`
        }
    }

    // Progress tracking functionality
    initializeProgress() {
        if (this.hasProgressBarTarget) {
            this.updateProgressDisplay()
        }
        this.loadMilestones()
    }

    setupProgressPolling() {
        // Poll for progress updates every 2 minutes
        this.progressInterval = setInterval(() => {
            this.refreshProgress()
        }, 120000)
    }

    saveProgressData() {
        if (!this.progressUrlValue || this.totalTime < 5) return // Don't save if less than 5 seconds

        const data = {
            contentId: this.contentIdValue,
            contentType: this.contentTypeValue,
            action: 'time_tracking',
            timeSpent: this.totalTime,
            timestamp: new Date().toISOString()
        }

        fetch(this.progressUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        }).then(response => {
            if (response.ok) {
                return response.json()
            }
            throw new Error('Failed to save progress')
        }).then(data => {
            if (data.overall_progress !== undefined) {
                this.updateOverallProgress(data.overall_progress)
            }
        }).catch(error => {
            console.error('Error saving progress:', error)
        })
    }

    markAsCompleted() {
        if (this.completedValue) return

        const data = {
            contentId: this.contentIdValue,
            contentType: this.contentTypeValue,
            action: 'view_completed',
            timeSpent: this.totalTime,
            timestamp: new Date().toISOString()
        }

        fetch(this.progressUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        }).then(response => {
            if (response.ok) {
                return response.json()
            }
            throw new Error('Failed to mark as completed')
        }).then(data => {
            this.completedValue = true
            this.updateCompletionStatus()
            this.updateOverallProgress(data.overall_progress)
            this.checkForNewMilestones()
            this.celebrateCompletion()
        }).catch(error => {
            console.error('Error marking as completed:', error)
        })
    }

    updateCompletionStatus() {
        if (this.hasCompletionStatusTarget) {
            this.completionStatusTarget.innerHTML = `
                <i class="fas fa-check-circle text-success me-1"></i>
                <span class="text-success">Terminé</span>
            `
        }
    }

    updateProgressDisplay() {
        const progress = this.completedValue ? 100 : 0
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = progress + '%'
            this.progressBarTarget.setAttribute('aria-valuenow', progress)
        }
    }

    updateOverallProgress(progress) {
        // Update overall progress indicators throughout the page
        const progressBars = document.querySelectorAll('.overall-progress-bar')
        progressBars.forEach(bar => {
            bar.style.width = progress + '%'
            bar.setAttribute('aria-valuenow', progress)
        })

        const progressTexts = document.querySelectorAll('.overall-progress-text')
        progressTexts.forEach(text => {
            text.textContent = `${Math.round(progress)}%`
        })
    }

    refreshProgress() {
        if (!this.statsUrlValue) return

        const url = new URL(this.statsUrlValue, window.location.origin)
        url.searchParams.set('formation', this.formationIdValue)

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response => {
            if (response.ok) {
                return response.json()
            }
            throw new Error('Failed to refresh progress')
        }).then(data => {
            this.updateProgressStats(data)
        }).catch(error => {
            console.error('Error refreshing progress:', error)
        })
    }

    updateProgressStats(stats) {
        // Update various progress indicators
        if (stats.completionRate !== undefined) {
            this.updateOverallProgress(stats.completionRate)
        }

        // Update time spent display
        if (stats.timeSpentMinutes !== undefined) {
            const timeElements = document.querySelectorAll('.total-time-spent')
            timeElements.forEach(element => {
                element.textContent = this.formatTimeFromMinutes(stats.timeSpentMinutes)
            })
        }

        // Update engagement score
        if (stats.engagementScore !== undefined) {
            const engagementElements = document.querySelectorAll('.engagement-score')
            engagementElements.forEach(element => {
                element.textContent = `${stats.engagementScore}%`
                element.className = element.className.replace(/bg-(success|warning|danger)/, '')
                if (stats.engagementScore >= 80) {
                    element.classList.add('bg-success')
                } else if (stats.engagementScore >= 60) {
                    element.classList.add('bg-warning')
                } else {
                    element.classList.add('bg-danger')
                }
            })
        }
    }

    formatTimeFromMinutes(minutes) {
        if (minutes < 60) {
            return `${minutes} min`
        }
        
        const hours = Math.floor(minutes / 60)
        const remainingMinutes = minutes % 60
        
        if (hours < 24) {
            return `${hours}h ${remainingMinutes}min`
        }
        
        const days = Math.floor(hours / 24)
        const remainingHours = hours % 24
        return `${days}j ${remainingHours}h`
    }

    // Milestone functionality
    loadMilestones() {
        if (!this.formationIdValue) return

        const url = `/student/progress/milestones?formation=${this.formationIdValue}`
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response => {
            if (response.ok) {
                return response.json()
            }
            throw new Error('Failed to load milestones')
        }).then(data => {
            this.displayMilestones(data.milestones)
        }).catch(error => {
            console.error('Error loading milestones:', error)
        })
    }

    displayMilestones(milestones) {
        if (!this.hasMilestoneContainerTarget) return

        const container = this.milestoneContainerTarget
        container.innerHTML = ''

        milestones.forEach(milestone => {
            const element = document.createElement('div')
            element.className = 'milestone-item d-flex align-items-center mb-2'
            element.innerHTML = `
                <div class="milestone-icon me-2">
                    <i class="fas fa-trophy text-warning"></i>
                </div>
                <div class="milestone-content">
                    <div class="milestone-title fw-bold">${milestone.title}</div>
                    <div class="milestone-description text-muted small">${milestone.description}</div>
                    <div class="milestone-points badge bg-primary">${milestone.points} points</div>
                </div>
            `
            container.appendChild(element)
        })
    }

    checkForNewMilestones() {
        // Re-load milestones to check for new achievements
        setTimeout(() => {
            this.loadMilestones()
        }, 1000)
    }

    celebrateCompletion() {
        // Show completion celebration
        this.showCelebration('🎉 Contenu terminé !', 'Vous avez terminé ce contenu avec succès.')
    }

    showCelebration(title, message) {
        // Create celebration modal/toast
        const celebration = document.createElement('div')
        celebration.className = 'celebration-toast position-fixed top-50 start-50 translate-middle'
        celebration.style.cssText = 'z-index: 1060; animation: celebrationPop 2s ease-out;'
        celebration.innerHTML = `
            <div class="card border-success shadow-lg">
                <div class="card-body text-center p-4">
                    <h4 class="text-success mb-2">${title}</h4>
                    <p class="mb-0">${message}</p>
                </div>
            </div>
        `

        document.body.appendChild(celebration)

        // Remove after animation
        setTimeout(() => {
            if (celebration.parentNode) {
                celebration.parentNode.removeChild(celebration)
            }
        }, 3000)
    }

    // Action methods for template usage
    markComplete(event) {
        event.preventDefault()
        this.markAsCompleted()
    }

    exportProgress(event) {
        event.preventDefault()
        window.location.href = `/student/progress/export?format=pdf&formation=${this.formationIdValue}`
    }

    shareProgress(event) {
        event.preventDefault()
        
        if (navigator.share) {
            navigator.share({
                title: 'Mon progrès de formation',
                text: 'Regardez mon progrès dans cette formation !',
                url: window.location.href
            })
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.showToast('Lien copié dans le presse-papiers')
            })
        }
    }

    showToast(message) {
        // Simple toast notification
        const toast = document.createElement('div')
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed'
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 1050;'
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `
        document.body.appendChild(toast)
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast)
            }
        }, 3000)
    }
}

// Add CSS for celebration animation
const style = document.createElement('style')
style.textContent = `
    @keyframes celebrationPop {
        0% {
            transform: translate(-50%, -50%) scale(0.8);
            opacity: 0;
        }
        50% {
            transform: translate(-50%, -50%) scale(1.1);
            opacity: 1;
        }
        100% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
    }

    .user-idle {
        opacity: 0.7;
    }

    .milestone-item {
        transition: all 0.3s ease;
    }

    .milestone-item:hover {
        background-color: rgba(0, 123, 255, 0.1);
        border-radius: 0.375rem;
        padding: 0.5rem;
    }
`
document.head.appendChild(style)
