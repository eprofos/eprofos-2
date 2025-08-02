import { Controller } from "@hotwired/stimulus"
import { Chart, registerables } from "chart.js"

// Connects to data-controller="performance-chart"
export default class extends Controller {
    static targets = ["canvas"]
    static values = { 
        monthlyData: Object,
        period: Number
    }

    connect() {
        Chart.register(...registerables)
        this.initializeChart()
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy()
        }
    }

    initializeChart() {
        if (!this.hasCanvasTarget || !this.monthlyDataValue) {
            return
        }

        const ctx = this.canvasTarget.getContext('2d')
        const monthlyData = this.monthlyDataValue

        this.chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.labels || [],
                datasets: [
                    {
                        label: 'Alternants actifs',
                        data: monthlyData.alternants || [],
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Évaluations',
                        data: monthlyData.evaluations || [],
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Taux de satisfaction (%)',
                        data: monthlyData.satisfaction || [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Mois'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nombre'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pourcentage'
                        },
                        min: 0,
                        max: 100,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: `Évolution des performances sur ${this.periodValue} mois`
                    }
                }
            }
        })
    }
}
