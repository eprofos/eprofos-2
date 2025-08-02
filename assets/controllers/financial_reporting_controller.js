import { Controller } from "@hotwired/stimulus"
import { Chart, registerables } from "chart.js"

Chart.register(...registerables)

export default class extends Controller {
    static targets = ["costAnalysisChart", "form"]
    static values = {
        costData: Object
    }

    connect() {
        this.initializeCostAnalysisChart()
        this.setupFormAutoSubmit()
    }

    disconnect() {
        if (this.costChart) {
            this.costChart.destroy()
        }
    }

    initializeCostAnalysisChart() {
        if (!this.hasCostAnalysisChartTarget) return

        const ctx = this.costAnalysisChartTarget.getContext('2d')
        
        this.costChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Formation', 'Administration', 'Mentors', 'Infrastructure'],
                datasets: [{
                    data: [
                        this.costDataValue.training_costs,
                        this.costDataValue.administrative_costs,
                        this.costDataValue.mentor_compensation,
                        this.costDataValue.infrastructure_costs
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(255, 99, 132, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed
                                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                                const percentage = ((value / total) * 100).toFixed(1)
                                return context.label + ': ' + (value / 1000).toFixed(0) + 'k€ (' + percentage + '%)'
                            }
                        }
                    }
                }
            }
        })
    }

    setupFormAutoSubmit() {
        if (!this.hasFormTarget) return

        const selects = this.formTarget.querySelectorAll('select')
        selects.forEach(select => {
            select.addEventListener('change', () => {
                this.formTarget.submit()
            })
        })
    }

    // Action method for manual form submission if needed
    submitForm() {
        if (this.hasFormTarget) {
            this.formTarget.submit()
        }
    }
}
