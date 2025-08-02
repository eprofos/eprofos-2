import { Controller } from "@hotwired/stimulus"
import { Chart, registerables } from "chart.js"

// Connects to data-controller="metrics"
export default class extends Controller {
    static targets = ["durationAnalysisChart"]
    static values = {
        durationAnalysis: Object
    }

    connect() {
        // Register Chart.js components
        Chart.register(...registerables)
        
        this.initializeCharts()
    }

    disconnect() {
        // Clean up charts when controller disconnects
        if (this.durationAnalysisChart) {
            this.durationAnalysisChart.destroy()
        }
    }

    initializeCharts() {
        this.createDurationAnalysisChart()
    }

    createDurationAnalysisChart() {
        if (!this.hasDurationAnalysisChartTarget) return

        const ctx = this.durationAnalysisChartTarget.getContext('2d')
        
        // Prepare duration analysis data with fallbacks
        const durationData = this.durationAnalysisValue || {}
        
        this.durationAnalysisChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['6-12 mois', '12-18 mois', '18-24 mois', '24+ mois'],
                datasets: [{
                    label: 'Nombre de Contrats',
                    data: [
                        durationData.short_duration || 0,
                        durationData.medium_duration || 0,
                        durationData.long_duration || 0,
                        durationData.extended_duration || 0
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(255, 99, 132, 0.8)'
                    ]
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

    // Export functions
    exportToExcel() {
        // Implementation for Excel export
        alert('Export Excel en cours de développement')
    }

    exportToPDF() {
        // Implementation for PDF export
        alert('Export PDF en cours de développement')
    }

    exportToCSV() {
        // Implementation for CSV export
        alert('Export CSV en cours de développement')
    }
}
