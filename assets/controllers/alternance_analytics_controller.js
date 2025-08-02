import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

// Connects to data-controller="alternance-analytics"
export default class extends Controller {
    static targets = ["enrollmentChart", "completionChart", "satisfactionChart"]
    static values = { 
        analyticsData: Object,
        selectedDimensions: Array
    }

    connect() {
        this.initializeCharts()
        this.setupFormAutoSubmit()
    }

    initializeCharts() {
        if (this.selectedDimensionsValue.includes('time')) {
            this.createEnrollmentChart()
            this.createCompletionChart()
            this.createSatisfactionChart()
        }
    }

    setupFormAutoSubmit() {
        const formElements = this.element.querySelectorAll('form select, form input[type="checkbox"]')
        formElements.forEach(element => {
            element.addEventListener('change', (event) => {
                event.target.form.submit()
            })
        })
    }

    createEnrollmentChart() {
        if (!this.hasEnrollmentChartTarget) return

        new Chart(this.enrollmentChartTarget, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Inscriptions',
                    data: this.analyticsDataValue.trends.enrollment_trend,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
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

    createCompletionChart() {
        if (!this.hasCompletionChartTarget) return

        new Chart(this.completionChartTarget, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Taux de Réussite (%)',
                    data: this.analyticsDataValue.trends.completion_trend,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.1
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
                        min: 70,
                        max: 100
                    }
                }
            }
        })
    }

    createSatisfactionChart() {
        if (!this.hasSatisfactionChartTarget) return

        new Chart(this.satisfactionChartTarget, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Satisfaction',
                    data: this.analyticsDataValue.trends.satisfaction_trend,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
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
                        min: 3,
                        max: 5
                    }
                }
            }
        })
    }
}
