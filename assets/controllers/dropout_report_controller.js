import { Controller } from "@hotwired/stimulus"
import { Chart, ArcElement, Tooltip, Legend } from "chart.js"

// Register Chart.js components
Chart.register(ArcElement, Tooltip, Legend)

export default class extends Controller {
    static targets = ["chart", "formationFilter", "reasonFilter", "dateRangeFilter"]
    static values = { 
        dropoutReasons: Array,
        exportUrl: String,
        formSubmitDelay: { type: Number, default: 100 },
        searchDebounceDelay: { type: Number, default: 500 }
    }

    connect() {
        this.searchTimeout = null
        this.initializeChart()
        this.initializeFilters()
        this.initializeTooltips()
        this.initializeTableHover()
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy()
        }
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }
    }

    initializeChart() {
        if (!this.hasChartTarget || this.dropoutReasonsValue.length === 0) {
            return
        }

        const ctx = this.chartTarget.getContext('2d')
        
        const labels = this.dropoutReasonsValue.map(reason => 
            reason.dropout_reason || "Non spécifiée"
        )
        
        const data = this.dropoutReasonsValue.map(reason => reason.count)
        
        const chartData = {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#dc3545', '#fd7e14', '#ffc107', '#198754', '#0dcaf0', 
                    '#6f42c1', '#d63384', '#6c757d', '#495057', '#212529'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        }

        this.chart = new Chart(ctx, {
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
                                        text: `${label} (${percentage}%)`,
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
                                return `${context.label}: ${context.parsed} (${percentage}%)`
                            }
                        }
                    }
                }
            }
        })
    }

    initializeFilters() {
        // Auto-submit form on filter change
        if (this.hasFormationFilterTarget) {
            this.formationFilterTarget.addEventListener('change', (event) => {
                this.submitFormWithDelay(event.target)
            })
        }

        if (this.hasDateRangeFilterTarget) {
            this.dateRangeFilterTarget.addEventListener('change', (event) => {
                this.submitFormWithDelay(event.target)
            })
        }

        // Search reason input with debounce
        if (this.hasReasonFilterTarget) {
            this.reasonFilterTarget.addEventListener('input', (event) => {
                this.debouncedSubmit(event.target)
            })
        }
    }

    initializeTooltips() {
        // Initialize Bootstrap tooltips
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

    initializeTableHover() {
        // Table row hover effects for better UX
        const tableRows = this.element.querySelectorAll('tbody tr')
        tableRows.forEach(row => {
            row.addEventListener('mouseenter', () => {
                row.style.backgroundColor = '#f8f9fa'
            })
            row.addEventListener('mouseleave', () => {
                row.style.backgroundColor = ''
            })
        })
    }

    submitFormWithDelay(element) {
        setTimeout(() => {
            element.closest('form').submit()
        }, this.formSubmitDelayValue)
    }

    debouncedSubmit(element) {
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout)
        }
        
        this.searchTimeout = setTimeout(() => {
            element.closest('form').submit()
        }, this.searchDebounceDelayValue)
    }

    // Action methods for template interactions
    exportReport(event) {
        event.preventDefault()
        const format = event.params.format
        
        const params = new URLSearchParams({
            format: format,
            formation: this.hasFormationFilterTarget ? this.formationFilterTarget.value : '',
            reason: this.hasReasonFilterTarget ? this.reasonFilterTarget.value : '',
            date_range: this.hasDateRangeFilterTarget ? this.dateRangeFilterTarget.value : ''
        })
        
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = this.exportUrlValue + '?' + params.toString()
        
        // Add fields selection for dropout report
        const fields = [
            'student_name', 'student_email', 'formation_title', 'session_name',
            'enrolled_at', 'dropout_reason', 'progress_percentage'
        ]
        
        fields.forEach(field => {
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = 'fields[]'
            input.value = field
            form.appendChild(input)
        })
        
        // Add filter for dropout status
        const statusInput = document.createElement('input')
        statusInput.type = 'hidden'
        statusInput.name = 'filters[status]'
        statusInput.value = 'dropped_out'
        form.appendChild(statusInput)
        
        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
    }

    showReengagementActions(event) {
        event.preventDefault()
        const enrollmentId = event.params.enrollmentId
        
        // This could open a modal with reengagement options
        // For now, just show an alert
        alert(`Actions de reconquête pour l'inscription ${enrollmentId}:\n\n` +
              '• Envoyer un email de reconquête\n' +
              '• Proposer un entretien de motivation\n' +
              '• Offrir un accompagnement personnalisé\n' +
              '• Adapter le parcours de formation')
    }
}
