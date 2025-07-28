import { Controller } from "@hotwired/stimulus"
import { Chart, registerables } from "chart.js"

// Connects to data-controller="alternance-dashboard"
export default class extends Controller {
    static targets = ["monthlyTrendsChart", "statusDistributionChart"]
    static values = {
        monthlyTrends: Object,
        statusDistribution: Object
    }

    connect() {
        // Register Chart.js components
        Chart.register(...registerables)
        
        this.initializeCharts()
    }

    disconnect() {
        // Clean up charts when controller disconnects
        if (this.monthlyTrendsChart) {
            this.monthlyTrendsChart.destroy()
        }
        if (this.statusDistributionChart) {
            this.statusDistributionChart.destroy()
        }
    }

    initializeCharts() {
        this.createMonthlyTrendsChart()
        this.createStatusDistributionChart()
    }

    createMonthlyTrendsChart() {
        if (!this.hasMonthlyTrendsChartTarget) return

        const ctx = this.monthlyTrendsChartTarget.getContext('2d')
        
        // Prepare monthly trends data with fallbacks
        const monthlyTrendsData = this.monthlyTrendsValue || {}
        const monthlyLabels = monthlyTrendsData.labels || ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc']
        const newContractsData = monthlyTrendsData.new_contracts || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
        const completedContractsData = monthlyTrendsData.completed_contracts || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
        
        this.monthlyTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Nouveaux Contrats',
                    data: newContractsData,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Contrats Terminés',
                    data: completedContractsData,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        })
    }

    createStatusDistributionChart() {
        if (!this.hasStatusDistributionChartTarget) return

        const ctx = this.statusDistributionChartTarget.getContext('2d')
        
        // Prepare status distribution data with fallbacks
        const statusData = this.statusDistributionValue || {}
        const statusLabels = Object.keys(statusData).length > 0 ? Object.keys(statusData) : ['Actif', 'Terminé', 'Suspendu', 'Annulé']
        const statusValues = Object.values(statusData).length > 0 ? Object.values(statusData) : [0, 0, 0, 0]
        
        this.statusDistributionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ]
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
}
