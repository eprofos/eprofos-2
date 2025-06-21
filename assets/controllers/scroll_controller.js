import { Controller } from "@hotwired/stimulus"

/**
 * Stimulus controller for scroll-related functionality
 * Handles back-to-top button visibility and smooth scrolling
 */
export default class extends Controller {
    static targets = ["button"]
    
    /**
     * Initialize the controller
     */
    connect() {
        this.handleScroll = this.handleScroll.bind(this)
        this.showButton = false
        
        // Add scroll event listener
        window.addEventListener('scroll', this.handleScroll)
        
        // Initial check
        this.handleScroll()
    }
    
    /**
     * Clean up when controller disconnects
     */
    disconnect() {
        window.removeEventListener('scroll', this.handleScroll)
    }
    
    /**
     * Handle scroll events to show/hide back-to-top button
     */
    handleScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop
        const shouldShow = scrollTop > 300
        
        if (shouldShow !== this.showButton) {
            this.showButton = shouldShow
            this.toggleButtonVisibility()
        }
    }
    
    /**
     * Toggle button visibility with smooth animation
     */
    toggleButtonVisibility() {
        const button = document.getElementById('btn-back-to-top')
        if (!button) return
        
        if (this.showButton) {
            button.style.display = 'inline-flex'
            button.style.opacity = '0'
            button.style.transform = 'translateY(20px)'
            
            // Trigger animation
            requestAnimationFrame(() => {
                button.style.transition = 'all 0.3s ease-in-out'
                button.style.opacity = '1'
                button.style.transform = 'translateY(0)'
            })
        } else {
            button.style.transition = 'all 0.3s ease-in-out'
            button.style.opacity = '0'
            button.style.transform = 'translateY(20px)'
            
            setTimeout(() => {
                if (button.style.opacity === '0') {
                    button.style.display = 'none'
                }
            }, 300)
        }
    }
    
    /**
     * Scroll to top of page smoothly
     * @param {Event} event - Click event
     */
    scrollToTop(event) {
        event.preventDefault()
        
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        })
    }
}