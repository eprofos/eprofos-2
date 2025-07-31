import { Controller } from "@hotwired/stimulus"
import { Toast, Modal } from "bootstrap"

export default class extends Controller {
    static targets = [
        "form", 
        "timerDisplay", 
        "progressBar", 
        "progressCount", 
        "answeredCount",
        "timeProgress",
        "timeUsed",
        "questionCard",
        "questionOption",
        "questionNavBtn",
        "questionStatus",
        "autoSaveToast",
        "timerExpiredModal",
        "submitExpiredBtn",
        "reviewUnansweredBtn",
        "scrollToTopBtn"
    ]
    
    static values = {
        remainingTime: Number,
        totalQuestions: Number,
        hasTimeLimit: Boolean,
        timeLimitMinutes: Number,
        saveUrl: String,
        answeredQuestions: Array
    }

    connect() {
        this.timerInterval = null
        this.autoSaveTimeout = null
        this.hasAnswered = new Set(this.answeredQuestionsValue.map(q => String(q)))
        
        // Initialize Bootstrap components
        if (this.hasAutoSaveToastTarget) {
            this.autoSaveToast = new Toast(this.autoSaveToastTarget)
        }
        if (this.hasTimerExpiredModalTarget) {
            this.timerExpiredModal = new Modal(this.timerExpiredModalTarget)
        }
        
        // Initialize UI
        this.updateNavigationButtons()
        
        // Start timer if applicable
        if (this.hasTimeLimitValue && this.remainingTimeValue) {
            this.startTimer()
        }
        
        // Bind event listeners
        this.bindEventListeners()
        
        // Prevent accidental page leave
        this.bindBeforeUnload()
    }

    disconnect() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval)
        }
        if (this.autoSaveTimeout) {
            clearTimeout(this.autoSaveTimeout)
        }
        window.removeEventListener('beforeunload', this.beforeUnloadHandler)
    }

    bindEventListeners() {
        // Question option changes
        this.questionOptionTargets.forEach(option => {
            option.addEventListener('change', this.handleOptionChange.bind(this))
        })
        
        // Navigation buttons
        this.questionNavBtnTargets.forEach(btn => {
            btn.addEventListener('click', this.handleNavigation.bind(this))
        })
        
        // Form submission
        if (this.hasFormTarget) {
            this.formTarget.addEventListener('submit', this.handleFormSubmit.bind(this))
        }
        
        // Quick actions
        if (this.hasReviewUnansweredBtnTarget) {
            this.reviewUnansweredBtnTarget.addEventListener('click', this.reviewUnanswered.bind(this))
        }
        if (this.hasScrollToTopBtnTarget) {
            this.scrollToTopBtnTarget.addEventListener('click', this.scrollToTop.bind(this))
        }
        if (this.hasSubmitExpiredBtnTarget) {
            this.submitExpiredBtnTarget.addEventListener('click', this.submitExpired.bind(this))
        }
    }

    bindBeforeUnload() {
        this.beforeUnloadHandler = (e) => {
            if (this.hasAnswered.size > 0) {
                e.preventDefault()
                e.returnValue = ''
            }
        }
        window.addEventListener('beforeunload', this.beforeUnloadHandler)
    }

    startTimer() {
        this.updateTimer()
        this.timerInterval = setInterval(() => {
            this.updateTimer()
        }, 1000)
    }

    updateTimer() {
        if (this.remainingTimeValue <= 0) {
            clearInterval(this.timerInterval)
            this.showTimerExpiredModal()
            return
        }
        
        const minutes = Math.floor(this.remainingTimeValue / 60)
        const seconds = this.remainingTimeValue % 60
        
        if (this.hasTimerDisplayTarget) {
            this.timerDisplayTarget.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds
        }
        
        // Update time progress bar
        if (this.hasTimeProgressTarget && this.hasTimeUsedTarget) {
            const totalTime = this.timeLimitMinutesValue * 60
            const usedTime = totalTime - this.remainingTimeValue
            const usedPercent = (usedTime / totalTime) * 100
            this.timeProgressTarget.style.width = usedPercent + '%'
            this.timeUsedTarget.textContent = Math.round(usedTime / 60) + 'min'
        }
        
        // Change timer color based on remaining time
        if (this.hasTimerDisplayTarget) {
            if (this.remainingTimeValue < 300) { // Less than 5 minutes
                this.timerDisplayTarget.className = 'h5 mb-0 text-danger'
            } else if (this.remainingTimeValue < 600) { // Less than 10 minutes
                this.timerDisplayTarget.className = 'h5 mb-0 text-warning'
            }
        }
        
        this.remainingTimeValue--
    }

    showTimerExpiredModal() {
        if (this.timerExpiredModal) {
            this.timerExpiredModal.show()
        }
    }

    handleOptionChange(event) {
        const option = event.target
        const questionId = option.getAttribute('data-question-id')
        
        // Update answered status
        if (option.type === 'radio' && option.checked) {
            this.hasAnswered.add(questionId)
        } else if (option.type === 'checkbox') {
            const checkedBoxes = document.querySelectorAll(`input[name="question_${questionId}[]"]:checked`)
            if (checkedBoxes.length > 0) {
                this.hasAnswered.add(questionId)
            } else {
                this.hasAnswered.delete(questionId)
            }
        }
        
        this.updateProgress()
        this.updateNavigationButtons()
        this.triggerAutoSave()
    }

    updateProgress() {
        const total = this.totalQuestionsValue
        const answered = this.hasAnswered.size
        const percentage = (answered / total) * 100
        
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = percentage + '%'
        }
        if (this.hasProgressCountTarget) {
            this.progressCountTarget.textContent = answered
        }
        if (this.hasAnsweredCountTarget) {
            this.answeredCountTarget.textContent = answered
        }
    }

    updateNavigationButtons() {
        this.questionNavBtnTargets.forEach(btn => {
            const questionId = btn.getAttribute('data-question-id')
            if (this.hasAnswered.has(questionId)) {
                btn.className = 'btn btn-sm w-100 btn-success question-nav-btn'
            } else {
                btn.className = 'btn btn-sm w-100 btn-outline-secondary question-nav-btn'
            }
        })
        
        // Update question status badges
        this.questionCardTargets.forEach(card => {
            const questionId = card.getAttribute('data-question-id')
            const statusBadge = card.querySelector('.question-status .badge')
            if (this.hasAnswered.has(questionId)) {
                statusBadge.className = 'badge bg-success'
                statusBadge.innerHTML = '<i class="fas fa-check me-1"></i>Répondu'
            } else {
                statusBadge.className = 'badge bg-light text-dark'
                statusBadge.innerHTML = '<i class="far fa-circle me-1"></i>Non répondu'
            }
        })
    }

    handleNavigation(event) {
        const questionId = event.target.getAttribute('data-question-id')
        const questionCard = document.querySelector(`[data-question-id="${questionId}"]`)
        if (questionCard) {
            questionCard.scrollIntoView({ behavior: 'smooth', block: 'start' })
        }
    }

    reviewUnanswered(event) {
        event.preventDefault()
        const unanswered = this.questionCardTargets
        for (let card of unanswered) {
            const questionId = card.getAttribute('data-question-id')
            if (!this.hasAnswered.has(questionId)) {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' })
                break
            }
        }
    }

    scrollToTop(event) {
        event.preventDefault()
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }

    submitExpired(event) {
        event.preventDefault()
        this.formTarget.querySelector('button[name="action"][value="submit"]').click()
    }

    handleFormSubmit(event) {
        if (event.submitter.value === 'submit') {
            const unansweredCount = this.totalQuestionsValue - this.hasAnswered.size
            if (unansweredCount > 0) {
                const message = `Il vous reste ${unansweredCount} question${unansweredCount > 1 ? 's' : ''} non répondue${unansweredCount > 1 ? 's' : ''}. Êtes-vous sûr de vouloir terminer le QCM ?`
                if (!confirm(message)) {
                    event.preventDefault()
                    return false
                }
            }
            
            // Clear timer
            if (this.timerInterval) {
                clearInterval(this.timerInterval)
            }
        }
    }

    triggerAutoSave() {
        clearTimeout(this.autoSaveTimeout)
        this.autoSaveTimeout = setTimeout(() => {
            this.autoSave()
        }, 2000) // Auto-save after 2 seconds of inactivity
    }

    async autoSave() {
        try {
            const formData = new FormData(this.formTarget)
            formData.set('action', 'auto_save')
            
            const response = await fetch(this.saveUrlValue, {
                method: 'POST',
                body: formData
            })
            
            const data = await response.json()
            
            if (data.success && this.autoSaveToast) {
                this.autoSaveToast.show()
            }
        } catch (error) {
            console.error('Auto-save failed:', error)
        }
    }
}
