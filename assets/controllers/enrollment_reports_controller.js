import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

export default class extends Controller {
    static targets = [
        "enrollmentTrendsChart", 
        "dropoutAnalysisChart", 
        "filtersForm",
        "formationSelect",
        "startDateInput", 
        "endDateInput",
        "refreshButton"
    ]
    
    static values = {
        enrollmentTrends: Array,
        dropoutAnalysis: Array,
        exportUrl: String
    }

    connect() {
        this.initializeCharts()
        this.setupFilterHandlers()
        this.setupInteractivity()
    }

    disconnect() {
        // Clean up charts when controller is disconnected
        if (this.enrollmentTrendsChart) {
            this.enrollmentTrendsChart.destroy()
        }
        if (this.dropoutAnalysisChart) {
            this.dropoutAnalysisChart.destroy()
        }
    }

    initializeCharts() {
        this.initializeEnrollmentTrendsChart()
        this.initializeDropoutAnalysisChart()
    }

    initializeEnrollmentTrendsChart() {
        if (!this.hasEnrollmentTrendsChartTarget || this.enrollmentTrendsValue.length === 0) {
            return
        }

        const ctx = this.enrollmentTrendsChartTarget.getContext('2d')
        const trendsData = this.enrollmentTrendsValue

        const chartData = {
            labels: trendsData.map(trend => {
                const date = new Date(trend.date)
                return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
            }),
            datasets: [
                {
                    label: 'Inscriptions',
                    data: trendsData.map(trend => trend.enrollments),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Completions',
                    data: trendsData.map(trend => trend.completions),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Abandons',
                    data: trendsData.map(trend => trend.dropouts),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        }

        this.enrollmentTrendsChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        })
    }

    initializeDropoutAnalysisChart() {
        if (!this.hasDropoutAnalysisChartTarget || this.dropoutAnalysisValue.length === 0) {
            return
        }

        const ctx = this.dropoutAnalysisChartTarget.getContext('2d')
        const dropoutData = this.dropoutAnalysisValue.slice(0, 5)

        const chartData = {
            labels: dropoutData.map(analysis => analysis.dropout_reason || "Non spécifiée"),
            datasets: [{
                data: dropoutData.map(analysis => analysis.count),
                backgroundColor: [
                    '#dc3545', '#fd7e14', '#ffc107', '#198754', '#0dcaf0'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        }

        this.dropoutAnalysisChart = new Chart(ctx, {
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
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                                const percentage = ((context.parsed / total) * 100).toFixed(1)
                                return `${context.label}: ${context.parsed} (${percentage}%)`
                            }
                        }
                    }
                }
            }
        })
    }

    setupFilterHandlers() {
        // Auto-submit form when filters change
        const filterInputs = [
            this.formationSelectTarget,
            this.startDateInputTarget,
            this.endDateInputTarget
        ].filter(input => input) // Filter out undefined targets

        filterInputs.forEach(input => {
            input.addEventListener('change', () => {
                // Add a small delay to allow for rapid changes
                clearTimeout(this.filterTimeout)
                this.filterTimeout = setTimeout(() => {
                    this.submitFilters()
                }, 300)
            })
        })
    }

    setupInteractivity() {
        // Initialize Bootstrap tooltips
        this.initializeTooltips()
        
        // Add card hover effects
        this.addCardHoverEffects()
    }

    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(this.element.querySelectorAll('[data-bs-toggle="tooltip"]'))
        this.tooltipList = tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }

    addCardHoverEffects() {
        const cards = this.element.querySelectorAll('.card')
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

    // Action methods
    submitFilters() {
        if (this.hasFiltersFormTarget) {
            this.filtersFormTarget.submit()
        }
    }

    refreshAnalytics(event) {
        const btn = event.currentTarget
        const icon = btn.querySelector('i')
        
        // Add spinner
        icon.className = 'fas fa-spinner fa-spin'
        btn.disabled = true
        
        // Reload the page after a short delay
        setTimeout(() => {
            window.location.reload()
        }, 1000)
    }

    exportAnalytics(event) {
        event.preventDefault()
        
        const format = event.params.format
        const params = new URLSearchParams({
            format: format,
            formation: this.hasFormationSelectTarget ? this.formationSelectTarget.value : '',
            start_date: this.hasStartDateInputTarget ? this.startDateInputTarget.value : '',
            end_date: this.hasEndDateInputTarget ? this.endDateInputTarget.value : ''
        })
        
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = this.exportUrlValue + '?' + params.toString()
        
        // Add basic fields for general export
        const fields = [
            'student_name', 'student_email', 'formation_title', 'session_name',
            'enrollment_status', 'enrolled_at', 'progress_percentage'
        ]
        
        fields.forEach(field => {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = 'fields[]'
            input.value = field
            form.appendChild(input)
        })
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
    }

    // Data update methods for dynamic updates
    updateEnrollmentTrends(newData) {
        this.enrollmentTrendsValue = newData
        if (this.enrollmentTrendsChart) {
            this.enrollmentTrendsChart.destroy()
        }
        this.initializeEnrollmentTrendsChart()
    }

    updateDropoutAnalysis(newData) {
        this.dropoutAnalysisValue = newData
        if (this.dropoutAnalysisChart) {
            this.dropoutAnalysisChart.destroy()
        }
        this.initializeDropoutAnalysisChart()
    }
}
