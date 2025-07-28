import { Controller } from "@hotwired/stimulus"
import { Chart } from "chart.js/auto"

// Connects to data-controller="analytics"
export default class extends Controller {
    static targets = ["attendanceChart", "completionChart", "formationChart", "rhythmChart"]
    static values = { 
        attendance: Array, 
        completion: Array,
        formationLabels: Array,
        formationValues: Array,
        rhythmLabels: Array,
        rhythmValues: Array
    }
    
    connect() {
        // Prevent multiple initializations
        if (this.initialized) return
        this.initialized = true
        
        // Use a small delay to ensure DOM is ready and prevent resize loops
        this.initTimeout = setTimeout(() => {
            this.initializeCharts()
        }, 200)
    }

    disconnect() {
        // Clear timeout if still pending
        if (this.initTimeout) {
            clearTimeout(this.initTimeout)
        }
        
        // Destroy charts when disconnecting to prevent memory leaks
        this.destroyAllCharts()
        this.initialized = false
    }

    destroyAllCharts() {
        if (this.charts) {
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.destroy === 'function') {
                    try {
                        chart.destroy()
                    } catch (error) {
                        console.warn('Error destroying chart:', error)
                    }
                }
            })
            this.charts = {}
        }
    }

    initializeCharts() {
        // Destroy existing charts first
        this.destroyAllCharts()
        
        this.charts = {}

        // Use real data from controller via Stimulus values with fallbacks
        const chartData = {
            attendance: this.hasAttendanceValue && this.attendanceValue.length > 0 ? this.attendanceValue : [90, 91, 92, 93, 94],
            completion: this.hasCompletionValue && this.completionValue.length > 0 ? this.completionValue : [80, 82, 84, 86, 88],
            formationLabels: this.hasFormationLabelsValue && this.formationLabelsValue.length > 0 ? this.formationLabelsValue : ['Aucune formation'],
            formationValues: this.hasFormationValuesValue && this.formationValuesValue.length > 0 ? this.formationValuesValue : [0],
            rhythmLabels: this.hasRhythmLabelsValue && this.rhythmLabelsValue.length > 0 ? this.rhythmLabelsValue : ['3/1 semaines'],
            rhythmValues: this.hasRhythmValuesValue && this.rhythmValuesValue.length > 0 ? this.rhythmValuesValue : [1]
        }

        // Common chart options to prevent infinite resize loops
        const commonOptions = {
            responsive: false, // Disable responsive to prevent resize loops
            maintainAspectRatio: false,
            animation: false, // Disable animations completely
            resizeDelay: 0, // Disable resize delay
            interaction: {
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            },
            onResize: null, // Disable custom resize handler
            devicePixelRatio: 1 // Fixed pixel ratio
        }

        // Doughnut specific options to prevent infinite resize
        const doughnutOptions = {
            ...commonOptions,
            cutout: '60%',
            radius: '90%',
            plugins: {
                ...commonOptions.plugins,
                legend: {
                    display: true,
                    position: 'bottom',
                    maxHeight: 100
                }
            }
        }

        // Create charts with error handling and size constraints
        this.createAttendanceChart(chartData, commonOptions)
        this.createCompletionChart(chartData, commonOptions)
        this.createFormationChart(chartData, doughnutOptions)
        this.createRhythmChart(chartData, doughnutOptions)
    }

    createAttendanceChart(chartData, options) {
        if (!this.hasAttendanceChartTarget) return

        try {
            const ctx = this.attendanceChartTarget.getContext('2d')
            
            // Set fixed canvas size to prevent resize issues
            this.attendanceChartTarget.width = 400
            this.attendanceChartTarget.height = 300
            
            this.charts.attendance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.generateMonthLabels(chartData.attendance.length),
                    datasets: [{
                        label: 'Taux de Présence (%)',
                        data: chartData.attendance,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false
                    }]
                },
                options: {
                    ...options,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: Math.max(0, Math.min(...chartData.attendance) - 5),
                            max: Math.min(100, Math.max(...chartData.attendance) + 5)
                        }
                    }
                }
            })
        } catch (error) {
            console.error('Error creating attendance chart:', error)
        }
    }

    createCompletionChart(chartData, options) {
        if (!this.hasCompletionChartTarget) return

        try {
            const ctx = this.completionChartTarget.getContext('2d')
            
            // Set fixed canvas size to prevent resize issues
            this.completionChartTarget.width = 400
            this.completionChartTarget.height = 300
            
            this.charts.completion = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.generateMonthLabels(chartData.completion.length),
                    datasets: [{
                        label: 'Taux de Réussite (%)',
                        data: chartData.completion,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false
                    }]
                },
                options: {
                    ...options,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: Math.max(0, Math.min(...chartData.completion) - 5),
                            max: Math.min(100, Math.max(...chartData.completion) + 5)
                        }
                    }
                }
            })
        } catch (error) {
            console.error('Error creating completion chart:', error)
        }
    }

    createFormationChart(chartData, options) {
        if (!this.hasFormationChartTarget || chartData.formationLabels.length === 0) return

        try {
            const ctx = this.formationChartTarget.getContext('2d')
            
            // Set fixed canvas size to prevent resize issues
            this.formationChartTarget.width = 400
            this.formationChartTarget.height = 300
            
            this.charts.formation = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.formationLabels,
                    datasets: [{
                        data: chartData.formationValues,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB', 
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: options
            })
        } catch (error) {
            console.error('Error creating formation chart:', error)
        }
    }

    createRhythmChart(chartData, options) {
        if (!this.hasRhythmChartTarget || chartData.rhythmLabels.length === 0) return

        try {
            const ctx = this.rhythmChartTarget.getContext('2d')
            
            // Set fixed canvas size to prevent resize issues
            this.rhythmChartTarget.width = 400
            this.rhythmChartTarget.height = 300
            
            this.charts.rhythm = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.rhythmLabels,
                    datasets: [{
                        data: chartData.rhythmValues,
                        backgroundColor: [
                            '#FF9F40',
                            '#FF6384',
                            '#C9CBCF'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: options
            })
        } catch (error) {
            console.error('Error creating rhythm chart:', error)
        }
    }

    generateMonthLabels(count) {
        const labels = []
        const now = new Date()
        
        for (let i = count - 1; i >= 0; i--) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1)
            labels.push(date.toLocaleDateString('fr-FR', { month: 'short' }))
        }
        
        return labels
    }
}
