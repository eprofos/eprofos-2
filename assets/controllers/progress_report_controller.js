import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

// Connects to data-controller="progress-report"
export default class extends Controller {
    static targets = [
        "progressChart",
        "filtersForm", 
        "formationSelect",
        "progressSelect",
        "dateRangeSelect",
        "atRiskAlert"
    ]
    
    static values = {
        progressDistribution: Array,
        exportUrl: String,
        atRiskCount: Number
    }

    connect() {
        this.initializeChart()
        this.setupFilters()
        this.setupInteractivity()
        this.showAtRiskAlert()
        this.startAutoRefresh()
        
        // Handle visibility change for auto-refresh
        document.addEventListener('visibilitychange', this.handleVisibilityChange.bind(this))
    }

    disconnect() {
        // Remove event listener
        document.removeEventListener('visibilitychange', this.handleVisibilityChange.bind(this))
        this.cleanup()
    }

    cleanup() {
        // Destroy chart
        if (this.progressChart) {
            this.progressChart.destroy()
        }
        
        // Clear auto-refresh interval
        this.stopAutoRefresh()
        
        // Destroy tooltips
        if (this.tooltipList) {
            this.tooltipList.forEach(tooltip => tooltip.dispose())
        }
    }

    initializeChart() {
        if (!this.hasProgressChartTarget || this.progressDistributionValue.length === 0) {
            return
        }

        const ctx = this.progressChartTarget.getContext('2d')
        const distributionData = this.progressDistributionValue

        const chartData = {
            labels: distributionData.map(dist => dist.progress_range),
            datasets: [{
                data: distributionData.map(dist => dist.count),
                backgroundColor: [
                    '#dc3545', // Low (red)
                    '#ffc107', // Medium (yellow) 
                    '#17a2b8', // Good (teal)
                    '#28a745'  // Excellent (green)
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        }

        this.progressChart = new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            generateLabels: (chart) => {
                                const data = chart.data
                                const total = data.datasets[0].data.reduce((a, b) => a + b, 0)
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i]
                                    const percentage = ((value / total) * 100).toFixed(1)
                                    return {
                                        text: `${label}: ${value} (${percentage}%)`,
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        pointStyle: 'circle'
                                    }
                                })
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                                const percentage = ((context.parsed / total) * 100).toFixed(1)
                                return `${context.label}: ${context.parsed} étudiants (${percentage}%)`
                            }
                        }
                    }
                }
            }
        })
    }

    setupFilters() {
        // Auto-submit form when filters change
        const filterInputs = [
            this.formationSelectTarget,
            this.progressSelectTarget,
            this.dateRangeSelectTarget
        ].filter(input => input) // Filter out undefined targets

        filterInputs.forEach(input => {
            input.addEventListener('change', () => {
                // Add small delay for better UX
                clearTimeout(this.filterTimeout)
                this.filterTimeout = setTimeout(() => {
                    this.submitFilters()
                }, 100)
            })
        })
    }

    setupInteractivity() {
        this.initializeTooltips()
        this.enhanceAtRiskRows()
    }

    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(this.element.querySelectorAll('[data-bs-toggle="tooltip"]'))
        this.tooltipList = tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Add tooltips to progress bars
        const progressBars = this.element.querySelectorAll('.progress-bar')
        progressBars.forEach(bar => {
            const percentage = bar.getAttribute('aria-valuenow')
            if (percentage) {
                bar.setAttribute('title', `Progression: ${percentage}%`)
                bar.setAttribute('data-bs-toggle', 'tooltip')
                new bootstrap.Tooltip(bar)
            }
        })
    }

    enhanceAtRiskRows() {
        const atRiskRows = this.element.querySelectorAll('tr.table-warning')
        atRiskRows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.style.backgroundColor = '#fff3cd'
                row.style.boxShadow = '0 2px 4px rgba(255, 193, 7, 0.3)'
            })
            row.addEventListener('mouseleave', () => {
                row.style.backgroundColor = ''
                row.style.boxShadow = ''
            })
        })
    }

    showAtRiskAlert() {
        if (this.atRiskCountValue > 0) {
            const alertContainer = document.createElement('div')
            alertContainer.className = 'alert alert-warning alert-dismissible fade show position-fixed'
            alertContainer.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;'
            alertContainer.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Attention!</strong> ${this.atRiskCountValue} étudiant(s) à risque d'abandon détecté(s).
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `
            document.body.appendChild(alertContainer)
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertContainer.parentNode) {
                    alertContainer.remove()
                }
            }, 5000)
        }
    }

    startAutoRefresh() {
        this.autoRefreshInterval = setInterval(() => {
            // In a real implementation, this would fetch updated progress data
            console.log('Auto-refreshing progress data...')
        }, 60000) // Refresh every minute
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval)
        }
    }

    // Action methods
    submitFilters() {
        if (this.hasFiltersFormTarget) {
            this.filtersFormTarget.submit()
        }
    }

    exportReport(event) {
        const format = event.params.format
        const params = new URLSearchParams({
            format: format,
            formation: this.hasFormationSelectTarget ? this.formationSelectTarget.value : '',
            progress: this.hasProgressSelectTarget ? this.progressSelectTarget.value : '',
            date_range: this.hasDateRangeSelectTarget ? this.dateRangeSelectTarget.value : ''
        })
        
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = this.exportUrlValue + '?' + params.toString()
        
        // Add fields selection for progress report
        const fields = [
            'student_name', 'student_email', 'formation_title', 'session_name',
            'enrolled_at', 'progress_percentage', 'enrollment_status'
        ]
        
        fields.forEach(field => {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = 'fields[]'
            input.value = field
            form.appendChild(input)
        })
        
        // Add filter for enrolled status
        const statusInput = document.createElement('input')
        statusInput.type = 'hidden'
        statusInput.name = 'filters[status]'
        statusInput.value = 'enrolled'
        form.appendChild(statusInput)
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
    }

    showFollowUpActions(event) {
        const enrollmentId = event.params.enrollmentId
        
        // This could open a modal with follow-up options
        // For now, show an alert with suggested actions
        alert(`Actions de suivi pour l'inscription ${enrollmentId}:\n\n` +
              '• Envoyer un email de motivation\n' +
              '• Programmer un entretien individuel\n' +
              '• Proposer un accompagnement personnalisé\n' +
              '• Adapter le rythme de formation\n' +
              '• Organiser une session de rattrapage\n' +
              '• Contacter le tuteur/mentor')
    }

    // Handle visibility change to pause/resume auto-refresh
    handleVisibilityChange() {
        if (document.hidden) {
            this.stopAutoRefresh()
        } else {
            this.startAutoRefresh()
        }
    }

    // Data update methods for dynamic updates
    updateProgressDistribution(newData) {
        this.progressDistributionValue = newData
        if (this.progressChart) {
            this.progressChart.destroy()
        }
        this.initializeChart()
    }

    // Lifecycle methods
    progressDistributionValueChanged() {
        if (this.progressChart) {
            this.progressChart.destroy()
        }
        this.initializeChart()
    }

    atRiskCountValueChanged() {
        this.showAtRiskAlert()
    }
}
