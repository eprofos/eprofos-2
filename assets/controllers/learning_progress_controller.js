import { Controller } from "@hotwired/stimulus"
import { Chart, registerables } from "chart.js"

Chart.register(...registerables)

/**
 * Learning Progress Controller
 * 
 * Handles real-time progress visualization and analytics for student
 * learning journey with interactive charts and progress indicators.
 */
export default class extends Controller {
    static targets = ["progressChart", "statsContainer", "completionRate", "timeSpent"]
    static values = { 
        studentId: Number,
        formationId: Number,
        progressData: Object,
        statsUrl: String
    }

    connect() {
        this.initializeProgressChart()
        this.loadProgressStats()
        this.setupRealTimeUpdates()
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy()
        }
        if (this.updateInterval) {
            clearInterval(this.updateInterval)
        }
    }

    initializeProgressChart() {
        if (!this.hasProgressChartTarget) return

        const ctx = this.progressChartTarget.getContext('2d')
        const progressData = this.progressDataValue

        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Terminé', 'En cours', 'Non commencé'],
                datasets: [{
                    data: [
                        progressData.completed || 0,
                        progressData.inProgress || 0,
                        progressData.notStarted || 0
                    ],
                    backgroundColor: [
                        '#28a745',  // success green
                        '#ffc107',  // warning yellow
                        '#6c757d'   // secondary gray
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || ''
                                const value = context.parsed || 0
                                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0
                                return `${label}: ${value} (${percentage}%)`
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        })
    }

    loadProgressStats() {
        if (!this.statsUrlValue) return

        fetch(this.statsUrlValue, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            this.updateStatsDisplay(data)
        })
        .catch(error => {
            console.error('Error loading progress stats:', error)
        })
    }

    updateStatsDisplay(stats) {
        // Update completion rate
        if (this.hasCompletionRateTarget) {
            this.completionRateTarget.textContent = `${stats.completionRate}%`
            
            // Update progress bar
            const progressBar = this.completionRateTarget.parentElement.querySelector('.progress-bar')
            if (progressBar) {
                progressBar.style.width = `${stats.completionRate}%`
                progressBar.setAttribute('aria-valuenow', stats.completionRate)
            }
        }

        // Update time spent
        if (this.hasTimeSpentTarget) {
            this.timeSpentTarget.textContent = this.formatDuration(stats.timeSpentMinutes)
        }

        // Update stats container with detailed metrics
        if (this.hasStatsContainerTarget) {
            this.renderDetailedStats(stats)
        }
    }

    renderDetailedStats(stats) {
        const container = this.statsContainerTarget
        
        container.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-book-open fa-2x text-primary mb-2"></i>
                            <h6 class="card-title">Modules terminés</h6>
                            <h4 class="text-primary">${stats.completedModules}/${stats.totalModules}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-tasks fa-2x text-success mb-2"></i>
                            <h6 class="card-title">Exercices réussis</h6>
                            <h4 class="text-success">${stats.completedExercises}/${stats.totalExercises}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-question-circle fa-2x text-warning mb-2"></i>
                            <h6 class="card-title">QCM moyenne</h6>
                            <h4 class="text-warning">${stats.averageQcmScore}%</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                            <h6 class="card-title">Dernière activité</h6>
                            <h6 class="text-info">${this.formatDate(stats.lastActivity)}</h6>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <h6>Progression par module</h6>
                <div class="module-progress">
                    ${stats.moduleProgress.map(module => `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small">${module.title}</span>
                            <div class="flex-grow-1 mx-3">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar ${this.getProgressBarClass(module.progress)}" 
                                         role="progressbar" 
                                         style="width: ${module.progress}%" 
                                         aria-valuenow="${module.progress}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                            </div>
                            <span class="badge bg-secondary">${module.progress}%</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `
    }

    getProgressBarClass(progress) {
        if (progress >= 100) return 'bg-success'
        if (progress >= 75) return 'bg-info'
        if (progress >= 50) return 'bg-warning'
        return 'bg-danger'
    }

    setupRealTimeUpdates() {
        // Update progress stats every 30 seconds
        this.updateInterval = setInterval(() => {
            this.loadProgressStats()
        }, 30000)
    }

    formatDuration(minutes) {
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

    formatDate(dateString) {
        if (!dateString) return 'Jamais'
        
        const date = new Date(dateString)
        const now = new Date()
        const diffInMs = now - date
        const diffInDays = Math.floor(diffInMs / (1000 * 60 * 60 * 24))
        
        if (diffInDays === 0) return 'Aujourd\'hui'
        if (diffInDays === 1) return 'Hier'
        if (diffInDays < 7) return `Il y a ${diffInDays} jours`
        
        return date.toLocaleDateString('fr-FR')
    }

    // Action methods for template usage
    refreshStats(event) {
        event.preventDefault()
        this.loadProgressStats()
        
        // Show loading indicator
        const button = event.currentTarget
        const originalText = button.innerHTML
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Actualisation...'
        button.disabled = true
        
        setTimeout(() => {
            button.innerHTML = originalText
            button.disabled = false
        }, 1000)
    }

    exportProgress(event) {
        event.preventDefault()
        
        // Generate and download progress report
        window.location.href = `/student/progress/export?format=pdf&formation=${this.formationIdValue}`
    }

    shareProgress(event) {
        event.preventDefault()
        
        if (navigator.share) {
            navigator.share({
                title: 'Mon progrès de formation',
                text: `J'ai terminé ${this.progressDataValue.completed || 0}% de ma formation !`,
                url: window.location.href
            })
        }
    }

    // Event handler for progress updates from other controllers
    progressUpdated(event) {
        const { detail } = event
        console.log('Progress updated:', detail)
        
        // Refresh stats when progress is updated
        setTimeout(() => {
            this.loadProgressStats()
        }, 500)
    }
}
