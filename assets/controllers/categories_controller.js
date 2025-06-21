import { Controller } from "@hotwired/stimulus"

/**
 * Categories Controller
 * Handles the interactive navigation for course categories section
 */
export default class extends Controller {
    static targets = ["grid", "card"]
    static values = { 
        currentIndex: Number,
        itemsPerView: Number,
        autoPlay: Boolean,
        autoPlayDelay: Number
    }

    connect() {
        this.currentIndexValue = 0
        this.itemsPerViewValue = this.getItemsPerView()
        this.autoPlayValue = true
        this.autoPlayDelayValue = 5000
        
        this.setupEventListeners()
        this.updateNavigation()
        
        if (this.autoPlayValue) {
            this.startAutoPlay()
        }
        
        // Handle resize events
        window.addEventListener('resize', this.handleResize.bind(this))
    }

    disconnect() {
        this.stopAutoPlay()
        window.removeEventListener('resize', this.handleResize.bind(this))
    }

    /**
     * Navigate to previous category
     */
    previous() {
        this.stopAutoPlay()
        
        if (this.currentIndexValue > 0) {
            this.currentIndexValue--
        } else {
            this.currentIndexValue = Math.max(0, this.cardTargets.length - this.itemsPerViewValue)
        }
        
        this.updateDisplay()
        this.restartAutoPlay()
    }

    /**
     * Navigate to next category
     */
    next() {
        this.stopAutoPlay()
        
        const maxIndex = Math.max(0, this.cardTargets.length - this.itemsPerViewValue)
        
        if (this.currentIndexValue < maxIndex) {
            this.currentIndexValue++
        } else {
            this.currentIndexValue = 0
        }
        
        this.updateDisplay()
        this.restartAutoPlay()
    }

    /**
     * Handle category card click
     * @param {Event} event
     */
    selectCategory(event) {
        const card = event.currentTarget
        const category = card.dataset.category
        
        // Add visual feedback
        this.highlightCard(card)
        
        // Emit custom event for other controllers to listen
        this.dispatch('categorySelected', { 
            detail: { 
                category: category,
                card: card 
            } 
        })
    }

    /**
     * Handle card hover effects
     * @param {Event} event
     */
    hoverCard(event) {
        const card = event.currentTarget
        this.addHoverEffect(card)
    }

    /**
     * Handle card hover leave
     * @param {Event} event
     */
    leaveCard(event) {
        const card = event.currentTarget
        this.removeHoverEffect(card)
    }

    /**
     * Update the display based on current index
     */
    updateDisplay() {
        if (!this.hasGridTarget || this.cardTargets.length === 0) return

        const cardWidth = this.cardTargets[0].offsetWidth
        const gap = 32 // 2rem gap
        const translateX = -(this.currentIndexValue * (cardWidth + gap))
        
        this.gridTarget.style.transform = `translateX(${translateX}px)`
        this.updateNavigation()
    }

    /**
     * Update navigation button states
     */
    updateNavigation() {
        const prevButton = this.element.querySelector('.nav-prev')
        const nextButton = this.element.querySelector('.nav-next')
        
        if (!prevButton || !nextButton) return

        const maxIndex = Math.max(0, this.cardTargets.length - this.itemsPerViewValue)
        
        // Update button states
        prevButton.disabled = this.currentIndexValue === 0
        nextButton.disabled = this.currentIndexValue >= maxIndex
        
        // Add visual feedback
        prevButton.classList.toggle('disabled', this.currentIndexValue === 0)
        nextButton.classList.toggle('disabled', this.currentIndexValue >= maxIndex)
    }

    /**
     * Get number of items to show per view based on screen size
     * @returns {number}
     */
    getItemsPerView() {
        const width = window.innerWidth
        
        if (width >= 1200) return 4
        if (width >= 992) return 3
        if (width >= 768) return 2
        return 1
    }

    /**
     * Handle window resize
     */
    handleResize() {
        const newItemsPerView = this.getItemsPerView()
        
        if (newItemsPerView !== this.itemsPerViewValue) {
            this.itemsPerViewValue = newItemsPerView
            this.currentIndexValue = Math.min(
                this.currentIndexValue, 
                Math.max(0, this.cardTargets.length - this.itemsPerViewValue)
            )
            this.updateDisplay()
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Add click listeners to navigation buttons
        const prevButton = this.element.querySelector('.nav-prev')
        const nextButton = this.element.querySelector('.nav-next')
        
        if (prevButton) {
            prevButton.addEventListener('click', this.previous.bind(this))
        }
        
        if (nextButton) {
            nextButton.addEventListener('click', this.next.bind(this))
        }

        // Add hover listeners to cards
        this.cardTargets.forEach(card => {
            card.addEventListener('mouseenter', this.hoverCard.bind(this))
            card.addEventListener('mouseleave', this.leaveCard.bind(this))
            card.addEventListener('click', this.selectCategory.bind(this))
        })

        // Add keyboard navigation
        this.element.addEventListener('keydown', this.handleKeydown.bind(this))
    }

    /**
     * Handle keyboard navigation
     * @param {KeyboardEvent} event
     */
    handleKeydown(event) {
        switch (event.key) {
            case 'ArrowLeft':
                event.preventDefault()
                this.previous()
                break
            case 'ArrowRight':
                event.preventDefault()
                this.next()
                break
            case 'Home':
                event.preventDefault()
                this.currentIndexValue = 0
                this.updateDisplay()
                break
            case 'End':
                event.preventDefault()
                this.currentIndexValue = Math.max(0, this.cardTargets.length - this.itemsPerViewValue)
                this.updateDisplay()
                break
        }
    }

    /**
     * Highlight selected card
     * @param {HTMLElement} card
     */
    highlightCard(card) {
        // Remove previous highlights
        this.cardTargets.forEach(c => c.classList.remove('selected'))
        
        // Add highlight to selected card
        card.classList.add('selected')
        
        // Add pulse animation
        card.style.animation = 'pulse 0.3s ease-in-out'
        setTimeout(() => {
            card.style.animation = ''
        }, 300)
    }

    /**
     * Add hover effect to card
     * @param {HTMLElement} card
     */
    addHoverEffect(card) {
        const icon = card.querySelector('.category-icon')
        if (icon) {
            icon.style.transform = 'scale(1.1) rotate(5deg)'
        }
    }

    /**
     * Remove hover effect from card
     * @param {HTMLElement} card
     */
    removeHoverEffect(card) {
        const icon = card.querySelector('.category-icon')
        if (icon) {
            icon.style.transform = ''
        }
    }

    /**
     * Start auto-play functionality
     */
    startAutoPlay() {
        if (!this.autoPlayValue) return
        
        this.autoPlayInterval = setInterval(() => {
            this.next()
        }, this.autoPlayDelayValue)
    }

    /**
     * Stop auto-play functionality
     */
    stopAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval)
            this.autoPlayInterval = null
        }
    }

    /**
     * Restart auto-play after user interaction
     */
    restartAutoPlay() {
        if (!this.autoPlayValue) return
        
        setTimeout(() => {
            this.startAutoPlay()
        }, 2000) // Wait 2 seconds before restarting
    }

    /**
     * Handle current index value change
     */
    currentIndexValueChanged() {
        this.updateDisplay()
    }

    /**
     * Handle items per view value change
     */
    itemsPerViewValueChanged() {
        this.updateDisplay()
    }
}