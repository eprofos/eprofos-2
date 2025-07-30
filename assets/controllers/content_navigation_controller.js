import { Controller } from "@hotwired/stimulus"

/**
 * Content Navigation Controller
 * 
 * Handles enhanced navigation and progress tracking for student content
 * consumption with real-time progress updates and interactive features.
 */
export default class extends Controller {
    static targets = ["progressBar", "completionStatus", "nextButton", "prevButton"]
    static values = { 
        contentId: Number,
        contentType: String,
        progressUrl: String,
        completed: Boolean
    }

    connect() {
        this.initializeProgress()
        this.setupScrollTracking()
        this.setupKeyboardNavigation()
    }

    initializeProgress() {
        // Initialize progress tracking
        if (this.hasProgressBarTarget) {
            this.updateProgressDisplay()
        }
    }

    setupScrollTracking() {
        // Track reading progress based on scroll position
        let scrollTimeout
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout)
            scrollTimeout = setTimeout(() => {
                this.trackScrollProgress()
            }, 250)
        })
    }

    setupKeyboardNavigation() {
        // Enable keyboard navigation
        document.addEventListener('keydown', (event) => {
            if (event.ctrlKey || event.metaKey) {
                switch(event.key) {
                    case 'ArrowLeft':
                        event.preventDefault()
                        this.navigatePrevious()
                        break
                    case 'ArrowRight':
                        event.preventDefault()
                        this.navigateNext()
                        break
                }
            }
        })
    }

    trackScrollProgress() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop
        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight
        const scrollPercent = Math.round((scrollTop / scrollHeight) * 100)

        if (scrollPercent > 80 && !this.completedValue) {
            this.markAsViewed()
        }

        // Update progress bar if available
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = scrollPercent + '%'
            this.progressBarTarget.setAttribute('aria-valuenow', scrollPercent)
        }
    }

    markAsViewed() {
        if (this.completedValue) return

        // Send progress update to server
        if (this.progressUrlValue) {
            fetch(this.progressUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    contentId: this.contentIdValue,
                    contentType: this.contentTypeValue,
                    action: 'view_completed',
                    timestamp: new Date().toISOString()
                })
            }).then(response => {
                if (response.ok) {
                    this.completedValue = true
                    this.updateCompletionStatus()
                    this.dispatchProgressEvent()
                }
            }).catch(error => {
                console.error('Error tracking progress:', error)
            })
        }
    }

    updateCompletionStatus() {
        if (this.hasCompletionStatusTarget) {
            this.completionStatusTarget.innerHTML = `
                <i class="fas fa-check-circle text-success me-1"></i>
                Terminé
            `
        }
    }

    updateProgressDisplay() {
        // Update overall progress display
        const progress = this.completedValue ? 100 : 0
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = progress + '%'
            this.progressBarTarget.setAttribute('aria-valuenow', progress)
        }
    }

    navigateNext() {
        if (this.hasNextButtonTarget && !this.nextButtonTarget.disabled) {
            this.nextButtonTarget.click()
        }
    }

    navigatePrevious() {
        if (this.hasPrevButtonTarget && !this.prevButtonTarget.disabled) {
            this.prevButtonTarget.click()
        }
    }

    dispatchProgressEvent() {
        // Dispatch custom event for other components to listen to
        this.dispatch('progressUpdated', {
            detail: {
                contentId: this.contentIdValue,
                contentType: this.contentTypeValue,
                completed: this.completedValue
            }
        })
    }

    // Action methods for template usage
    markAsComplete(event) {
        event.preventDefault()
        this.markAsViewed()
    }

    toggleBookmark(event) {
        event.preventDefault()
        const button = event.currentTarget
        const isBookmarked = button.classList.contains('bookmarked')
        
        // Send bookmark request to server
        fetch('/student/content/bookmark', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                contentId: this.contentIdValue,
                contentType: this.contentTypeValue,
                action: isBookmarked ? 'remove' : 'add'
            })
        }).then(response => {
            if (response.ok) {
                if (isBookmarked) {
                    button.classList.remove('bookmarked', 'btn-warning')
                    button.classList.add('btn-outline-warning')
                    button.innerHTML = '<i class="fas fa-bookmark me-1"></i>Ajouter aux favoris'
                } else {
                    button.classList.add('bookmarked', 'btn-warning')
                    button.classList.remove('btn-outline-warning')
                    button.innerHTML = '<i class="fas fa-bookmark me-1"></i>Retiré des favoris'
                }
            }
        })
    }

    printContent(event) {
        event.preventDefault()
        window.print()
    }

    shareContent(event) {
        event.preventDefault()
        if (navigator.share) {
            navigator.share({
                title: document.title,
                url: window.location.href
            })
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.showToast('Lien copié dans le presse-papiers')
            })
        }
    }

    showToast(message) {
        // Simple toast notification
        const toast = document.createElement('div')
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed'
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 1050;'
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `
        document.body.appendChild(toast)
        
        const bsToast = new bootstrap.Toast(toast)
        bsToast.show()
        
        toast.addEventListener('hidden.bs.toast', () => {
            document.body.removeChild(toast)
        })
    }
}
