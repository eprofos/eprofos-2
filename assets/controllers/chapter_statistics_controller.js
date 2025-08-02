import { Controller } from "@hotwired/stimulus"
import { Chart } from "chart.js/auto"

// Connects to data-controller="chapter-statistics"
export default class extends Controller {
    static targets = ["statusChart"]
    static values = { 
        activeChapters: Number,
        inactiveChapters: Number
    }
    
    connect() {
        // Prevent multiple initializations
        if (this.initialized) return
        this.initialized = true
        
        // Use a small delay to ensure DOM is ready
        this.initTimeout = setTimeout(() => {
            this.initializeChart()
        }, 200)
    }

    disconnect() {
        // Clear timeout if still pending
        if (this.initTimeout) {
            clearTimeout(this.initTimeout)
        }
        
        // Destroy chart when disconnecting to prevent memory leaks
        if (this.chart && typeof this.chart.destroy === 'function') {
            try {
                this.chart.destroy()
            } catch (error) {
                console.warn('Error destroying chart:', error)
            }
        }
        this.initialized = false
    }

    initializeChart() {
        if (!this.hasStatusChartTarget) return

        // Destroy existing chart first
        if (this.chart) {
            this.chart.destroy()
        }

        try {
            const ctx = this.statusChartTarget.getContext('2d')
            
            // Set fixed canvas size to prevent resize issues
            this.statusChartTarget.width = 400
            this.statusChartTarget.height = 200
            
            this.chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Actifs', 'Inactifs'],
                    datasets: [{
                        data: [this.activeChaptersValue, this.inactiveChaptersValue],
                        backgroundColor: [
                            '#28a745',
                            '#6c757d'
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
        } catch (error) {
            console.error('Error creating status chart:', error)
        }
    }
}
