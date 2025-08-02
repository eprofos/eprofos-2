import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

export default class extends Controller {
    static targets = ["missionTypesChart", "complexityChart"]
    static values = { 
        missionTypes: Object,
        complexityDistribution: Object
    }

    connect() {
        this.initializeCharts()
        this.setupFormAutoSubmit()
    }

    disconnect() {
        // Clean up charts when controller disconnects
        if (this.missionTypesChart) {
            this.missionTypesChart.destroy()
        }
        if (this.complexityChart) {
            this.complexityChart.destroy()
        }
    }

    initializeCharts() {
        this.createMissionTypesChart()
        this.createComplexityChart()
    }

    createMissionTypesChart() {
        if (!this.hasMissionTypesChartTarget) return

        const labels = Object.keys(this.missionTypesValue)
        const data = Object.values(this.missionTypesValue)

        this.missionTypesChart = new Chart(this.missionTypesChartTarget, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Nombre de Missions',
                    data: data,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        })
    }

    createComplexityChart() {
        if (!this.hasComplexityChartTarget) return

        const labels = Object.keys(this.complexityDistributionValue)
        const data = Object.values(this.complexityDistributionValue)

        this.complexityChart = new Chart(this.complexityChartTarget, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        })
    }

    setupFormAutoSubmit() {
        // Find all select elements within the form
        const selects = this.element.querySelectorAll('form select')
        selects.forEach(select => {
            select.addEventListener('change', (event) => {
                event.target.form.submit()
            })
        })
    }
}
