import { Controller } from "@hotwired/stimulus"
import Chart from "chart.js/auto"

// Connects to data-controller="alternance-performance"
export default class extends Controller {
    static targets = ["progressionTrendChart", "skillsEvolutionChart", "attendanceTrendChart"]
    static values = { 
        performanceData: Object,
        selectedMetrics: Array
    }

    connect() {
        this.initializeCharts()
        this.setupFormAutoSubmit()
    }

    initializeCharts() {
        if (this.selectedMetricsValue.includes('progression')) {
            this.createProgressionChart()
        }
        
        if (this.selectedMetricsValue.includes('skills')) {
            this.createSkillsChart()
        }
        
        if (this.selectedMetricsValue.includes('attendance')) {
            this.createAttendanceChart()
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

    createProgressionChart() {
        if (!this.hasProgressionTrendChartTarget) return

        new Chart(this.progressionTrendChartTarget, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Progression (%)',
                    data: this.performanceDataValue.progression_metrics.progression_trend,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        })
    }

    createSkillsChart() {
        if (!this.hasSkillsEvolutionChartTarget) return

        new Chart(this.skillsEvolutionChartTarget, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Score Compétences',
                    data: this.performanceDataValue.skills_metrics.skills_evolution,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 5
                    }
                }
            }
        })
    }

    createAttendanceChart() {
        if (!this.hasAttendanceTrendChartTarget) return

        new Chart(this.attendanceTrendChartTarget, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Assiduité (%)',
                    data: this.performanceDataValue.attendance_metrics.attendance_trend,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        min: 80,
                        max: 100
                    }
                }
            }
        })
    }
}
