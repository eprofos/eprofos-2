import { Controller } from "@hotwired/stimulus"

/**
 * Enhanced QCM Form Controller
 * 
 * Handles advanced QCM interactions including timer management,
 * auto-save, navigation, and real-time validation.
 */
export default class extends Controller {
    static targets = ["timer", "question", "progressBar", "submitButton", "navigationButton"]
    static values = { 
        timeLimit: Number,
        totalQuestions: Number,
        autoSave: Boolean,
        autoSaveUrl: String,
        qcmId: Number
    }

    connect() {
        this.currentQuestion = 1
        this.startTime = Date.now()
        this.answers = {}
        this.timeRemaining = this.timeLimitValue * 60 // Convert to seconds
        
        this.initializeTimer()
        this.initializeNavigation()
        this.setupAutoSave()
        this.setupKeyboardShortcuts()
        this.loadSavedAnswers()
    }

    disconnect() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval)
        }
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval)
        }
    }

    initializeTimer() {
        if (this.timeLimitValue && this.hasTimerTarget) {
            this.updateTimerDisplay()
            this.timerInterval = setInterval(() => {
                this.timeRemaining--
                this.updateTimerDisplay()
                
                if (this.timeRemaining <= 0) {
                    this.timeUp()
                }
            }, 1000)
        }
    }

    updateTimerDisplay() {
        if (!this.hasTimerTarget) return
        
        const minutes = Math.floor(this.timeRemaining / 60)
        const seconds = this.timeRemaining % 60
        const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`
        
        this.timerTarget.textContent = timeString
        
        // Change color based on remaining time
        if (this.timeRemaining <= 300) { // Last 5 minutes
            this.timerTarget.classList.add('text-danger')
            this.timerTarget.classList.remove('text-warning')
        } else if (this.timeRemaining <= 600) { // Last 10 minutes
            this.timerTarget.classList.add('text-warning')
        }
        
        // Flash when very low
        if (this.timeRemaining <= 60) {
            this.timerTarget.classList.add('animate-pulse')
        }
    }

    timeUp() {
        clearInterval(this.timerInterval)
        alert('Temps écoulé ! Le QCM va être soumis automatiquement.')
        this.submitQCM(true) // Force submit
    }

    initializeNavigation() {
        this.updateNavigationState()
        this.updateProgressBar()
    }

    setupAutoSave() {
        if (this.autoSaveValue && this.autoSaveUrlValue) {
            this.autoSaveInterval = setInterval(() => {
                this.autoSave()
            }, 30000) // Auto-save every 30 seconds
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            // Don't trigger shortcuts when typing in text inputs
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
                return
            }
            
            switch(event.key) {
                case 'ArrowLeft':
                    event.preventDefault()
                    this.previousQuestion()
                    break
                case 'ArrowRight':
                    event.preventDefault()
                    this.nextQuestion()
                    break
                case 'Enter':
                    if (event.ctrlKey || event.metaKey) {
                        event.preventDefault()
                        this.submitQCM()
                    }
                    break
                case '1':
                case '2':
                case '3':
                case '4':
                    if (!event.ctrlKey && !event.metaKey) {
                        const optionIndex = parseInt(event.key) - 1
                        this.selectOptionByIndex(optionIndex)
                    }
                    break
            }
        })
    }

    loadSavedAnswers() {
        // Load any previously saved answers from localStorage
        const savedAnswers = localStorage.getItem(`qcm_${this.qcmIdValue}_answers`)
        if (savedAnswers) {
            try {
                this.answers = JSON.parse(savedAnswers)
                this.restoreAnswers()
            } catch (error) {
                console.error('Error loading saved answers:', error)
            }
        }
    }

    restoreAnswers() {
        Object.keys(this.answers).forEach(questionId => {
            const answer = this.answers[questionId]
            if (Array.isArray(answer)) {
                // Multiple choice
                answer.forEach(optionId => {
                    const input = document.querySelector(`input[name="answers[${questionId}][]"][value="${optionId}"]`)
                    if (input) input.checked = true
                })
            } else {
                // Single choice
                const input = document.querySelector(`input[name="answers[${questionId}]"][value="${answer}"]`)
                if (input) input.checked = true
            }
        })
        this.updateNavigationState()
    }

    // Navigation methods
    showQuestion(questionNumber) {
        // Hide all questions
        this.questionTargets.forEach(question => {
            question.style.display = 'none'
        })
        
        // Show target question
        const targetQuestion = this.questionTargets[questionNumber - 1]
        if (targetQuestion) {
            targetQuestion.style.display = 'block'
            this.currentQuestion = questionNumber
            this.updateNavigationState()
            this.updateProgressBar()
        }
    }

    nextQuestion() {
        if (this.currentQuestion < this.totalQuestionsValue) {
            this.showQuestion(this.currentQuestion + 1)
        }
    }

    previousQuestion() {
        if (this.currentQuestion > 1) {
            this.showQuestion(this.currentQuestion - 1)
        }
    }

    goToQuestion(event) {
        const questionNumber = parseInt(event.currentTarget.dataset.question)
        this.showQuestion(questionNumber)
    }

    updateNavigationState() {
        // Update navigation buttons
        this.navigationButtonTargets.forEach(button => {
            const questionNumber = parseInt(button.dataset.question)
            
            // Reset classes
            button.classList.remove('btn-primary', 'btn-success', 'btn-outline-secondary')
            
            if (questionNumber === this.currentQuestion) {
                button.classList.add('btn-primary')
            } else if (this.isQuestionAnswered(questionNumber)) {
                button.classList.add('btn-success')
            } else {
                button.classList.add('btn-outline-secondary')
            }
        })
    }

    updateProgressBar() {
        if (this.hasProgressBarTarget) {
            const progress = (this.currentQuestion / this.totalQuestionsValue) * 100
            this.progressBarTarget.style.width = `${progress}%`
            this.progressBarTarget.setAttribute('aria-valuenow', progress)
        }
    }

    isQuestionAnswered(questionNumber) {
        const question = this.questionTargets[questionNumber - 1]
        if (!question) return false
        
        const inputs = question.querySelectorAll('input[type="radio"]:checked, input[type="checkbox"]:checked')
        return inputs.length > 0
    }

    selectOptionByIndex(index) {
        const currentQuestionElement = this.questionTargets[this.currentQuestion - 1]
        if (!currentQuestionElement) return
        
        const options = currentQuestionElement.querySelectorAll('input[type="radio"], input[type="checkbox"]')
        if (options[index]) {
            options[index].click()
        }
    }

    // Answer tracking
    answerChanged(event) {
        const input = event.target
        const questionId = this.extractQuestionId(input.name)
        
        if (input.type === 'checkbox') {
            // Handle multiple choice
            if (!this.answers[questionId]) {
                this.answers[questionId] = []
            }
            
            if (input.checked) {
                if (!this.answers[questionId].includes(input.value)) {
                    this.answers[questionId].push(input.value)
                }
            } else {
                this.answers[questionId] = this.answers[questionId].filter(val => val !== input.value)
            }
        } else {
            // Handle single choice
            this.answers[questionId] = input.value
        }
        
        this.updateNavigationState()
        this.saveAnswersToLocalStorage()
    }

    extractQuestionId(inputName) {
        // Extract question ID from input name like "answers[123]" or "answers[123][]"
        const match = inputName.match(/answers\[(\d+)\]/)
        return match ? match[1] : null
    }

    saveAnswersToLocalStorage() {
        localStorage.setItem(`qcm_${this.qcmIdValue}_answers`, JSON.stringify(this.answers))
    }

    autoSave() {
        if (!this.autoSaveUrlValue) return
        
        const formData = new FormData()
        formData.append('answers', JSON.stringify(this.answers))
        formData.append('currentQuestion', this.currentQuestion)
        formData.append('timeRemaining', this.timeRemaining)
        formData.append('action', 'auto_save')
        
        fetch(this.autoSaveUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).catch(error => {
            console.error('Auto-save failed:', error)
        })
    }

    // Submission methods
    prepareSubmit(event) {
        event.preventDefault()
        
        const unansweredQuestions = this.getUnansweredQuestions()
        if (unansweredQuestions.length > 0) {
            const proceed = confirm(
                `Attention: ${unansweredQuestions.length} question(s) non répondue(s) (${unansweredQuestions.join(', ')}). Voulez-vous continuer ?`
            )
            if (!proceed) return
        }
        
        this.submitQCM()
    }

    submitQCM(forced = false) {
        if (!forced) {
            const confirmed = confirm('Êtes-vous sûr de vouloir soumettre vos réponses ? Cette action est irréversible.')
            if (!confirmed) return
        }
        
        // Clear auto-save
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval)
        }
        
        // Clear timer
        if (this.timerInterval) {
            clearInterval(this.timerInterval)
        }
        
        // Calculate time spent
        const timeSpent = Math.floor((Date.now() - this.startTime) / 1000)
        
        // Add hidden fields to form
        const form = this.element.querySelector('form')
        
        const timeSpentInput = document.createElement('input')
        timeSpentInput.type = 'hidden'
        timeSpentInput.name = 'timeSpent'
        timeSpentInput.value = timeSpent
        form.appendChild(timeSpentInput)
        
        const finalAnswersInput = document.createElement('input')
        finalAnswersInput.type = 'hidden'
        finalAnswersInput.name = 'finalAnswers'
        finalAnswersInput.value = JSON.stringify(this.answers)
        form.appendChild(finalAnswersInput)
        
        // Clear localStorage
        localStorage.removeItem(`qcm_${this.qcmIdValue}_answers`)
        
        // Remove beforeunload warning
        window.removeEventListener('beforeunload', this.beforeUnloadHandler)
        
        // Submit form
        form.submit()
    }

    getUnansweredQuestions() {
        const unanswered = []
        for (let i = 1; i <= this.totalQuestionsValue; i++) {
            if (!this.isQuestionAnswered(i)) {
                unanswered.push(i)
            }
        }
        return unanswered
    }

    // Utility methods
    beforeUnloadHandler = (event) => {
        event.preventDefault()
        event.returnValue = 'Vos réponses seront perdues si vous quittez la page.'
        return 'Vos réponses seront perdues si vous quittez la page.'
    }

    // Setup beforeunload warning
    setupBeforeUnloadWarning() {
        window.addEventListener('beforeunload', this.beforeUnloadHandler)
    }

    // Action methods callable from templates
    markForReview(event) {
        const button = event.currentTarget
        const questionNumber = this.currentQuestion
        
        button.classList.toggle('btn-warning')
        button.classList.toggle('btn-outline-warning')
        
        // Store review status
        const reviewKey = `qcm_${this.qcmIdValue}_review`
        let reviewedQuestions = JSON.parse(localStorage.getItem(reviewKey) || '[]')
        
        if (button.classList.contains('btn-warning')) {
            if (!reviewedQuestions.includes(questionNumber)) {
                reviewedQuestions.push(questionNumber)
            }
            button.innerHTML = '<i class="fas fa-flag me-1"></i>Marqué pour révision'
        } else {
            reviewedQuestions = reviewedQuestions.filter(q => q !== questionNumber)
            button.innerHTML = '<i class="fas fa-flag me-1"></i>Marquer pour révision'
        }
        
        localStorage.setItem(reviewKey, JSON.stringify(reviewedQuestions))
    }

    clearAnswer(event) {
        event.preventDefault()
        
        const currentQuestionElement = this.questionTargets[this.currentQuestion - 1]
        const inputs = currentQuestionElement.querySelectorAll('input[type="radio"], input[type="checkbox"]')
        
        inputs.forEach(input => {
            input.checked = false
        })
        
        // Update answers
        const questionId = this.extractQuestionId(inputs[0]?.name)
        if (questionId) {
            delete this.answers[questionId]
            this.saveAnswersToLocalStorage()
        }
        
        this.updateNavigationState()
    }
}
