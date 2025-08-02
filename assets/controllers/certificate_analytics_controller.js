import { Controller } from "@hotwired/stimulus"
import { Chart, registerables } from "chart.js"

// Register Chart.js components
Chart.register(...registerables)

export default class extends Controller {
    static targets = ["gradeChart", "trendsChart"]
    static values = {
        gradeDistribution: Object,
        monthlyTrends: Object
    }

    connect() {
        this.initializeCharts()
    }

    disconnect() {
        // Clean up charts when controller disconnects
        if (this.gradeChart) {
            this.gradeChart.destroy()
        }
        if (this.trendsChart) {
            this.trendsChart.destroy()
        }
    }

    initializeCharts() {
        this.initializeGradeChart()
        this.initializeTrendsChart()
    }

    initializeGradeChart() {
        if (!this.hasGradeChartTarget || !this.gradeDistributionValue || Object.keys(this.gradeDistributionValue).length === 0) {
            return
        }

        const gradeData = this.gradeDistributionValue
        const gradeLabels = Object.keys(gradeData)
        const gradeValues = Object.values(gradeData)

        const gradeLabelsMap = {
            'A': 'Excellent (A)',
            'B': 'Très bien (B)',
            'C': 'Bien (C)',
            'D': 'Passable (D)',
            'F': 'Insuffisant (F)'
        }

        this.gradeChart = new Chart(this.gradeChartTarget, {
            type: 'doughnut',
            data: {
                labels: gradeLabels.map(label => gradeLabelsMap[label] || label),
                datasets: [{
                    data: gradeValues,
                    backgroundColor: [
                        '#28a745', // Green for A
                        '#17a2b8', // Blue for B
                        '#ffc107', // Yellow for C
                        '#fd7e14', // Orange for D
                        '#dc3545'  // Red for F
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
                    }
                }
            }
        })
    }

    initializeTrendsChart() {
        if (!this.hasTrendsChartTarget || !this.monthlyTrendsValue || Object.keys(this.monthlyTrendsValue).length === 0) {
            return
        }

        const trendsData = this.monthlyTrendsValue
        const trendsLabels = Object.keys(trendsData)
        const trendsValues = Object.values(trendsData)

        this.trendsChart = new Chart(this.trendsChartTarget, {
            type: 'line',
            data: {
                labels: trendsLabels.map(label => {
                    const [year, month] = label.split('-')
                    const date = new Date(year, month - 1)
                    return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' })
                }),
                datasets: [{
                    label: 'Certificats émis',
                    data: trendsValues,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1
                }]
            },
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
                        display: false
                    }
                }
            }
        })
    }
}
