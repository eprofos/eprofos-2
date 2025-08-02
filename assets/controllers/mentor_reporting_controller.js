import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

// Connects to data-controller="mentor-reporting"
export default class extends Controller {
    static targets = ["companySizeChart", "sectorChart"]
    static values = {
        companySizeData: Object,
        sectorData: Object
    }

    connect() {
        console.log("Mentor reporting controller connected")
        this.setupFormAutoSubmit()
        this.initializeCharts()
    }

    disconnect() {
        // Clean up charts when controller disconnects
        if (this.companySizeChart) {
            this.companySizeChart.destroy()
        }
        if (this.sectorChart) {
            this.sectorChart.destroy()
        }
    }

    setupFormAutoSubmit() {
        const selects = this.element.querySelectorAll('form select')
        selects.forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit()
            })
        })
    }

    initializeCharts() {
        this.initializeCompanySizeChart()
        this.initializeSectorChart()
    }

    initializeCompanySizeChart() {
        if (!this.hasCompanySizeChartTarget) return

        const ctx = this.companySizeChartTarget.getContext('2d')
        const data = this.companySizeDataValue

        this.companySizeChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    data: Object.values(data),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || ''
                                const value = context.parsed
                                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                                const percentage = ((value / total) * 100).toFixed(1)
                                return `${label}: ${value} mentors (${percentage}%)`
                            }
                        }
                    }
                }
            }
        })
    }

    initializeSectorChart() {
        if (!this.hasSectorChartTarget) return

        const ctx = this.sectorChartTarget.getContext('2d')
        const data = this.sectorDataValue

        this.sectorChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(data),
                datasets: [{
                    data: Object.values(data),
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || ''
                                const value = context.parsed
                                const total = context.dataset.data.reduce((a, b) => a + b, 0)
                                const percentage = ((value / total) * 100).toFixed(1)
                                return `${label}: ${value} mentors (${percentage}%)`
                            }
                        }
                    }
                }
            }
        })
    }
}
