import { Controller } from "@hotwired/stimulus"

/**
 * Counter Controller
 * 
 * Animates numbers from 0 to their target value when they come into view
 * 
 * @example
 * <div data-controller="counter" data-counter-target-value="30000" data-counter-suffix="+" data-counter-duration-value="2000">
 *   <span data-counter-target="number">0</span>
 * </div>
 */
export default class extends Controller {
    static targets = ["number"]
    static values = { 
        target: Number,
        duration: { type: Number, default: 2000 },
        suffix: { type: String, default: "" },
        prefix: { type: String, default: "" }
    }

    connect() {
        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.hasAnimated) {
                        this.animate()
                        this.hasAnimated = true
                    }
                })
            },
            { threshold: 0.5 }
        )
        
        this.observer.observe(this.element)
        this.hasAnimated = false
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect()
        }
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame)
        }
    }

    /**
     * Animate the counter from 0 to target value
     */
    animate() {
        const startTime = performance.now()
        const startValue = 0
        const endValue = this.targetValue
        const duration = this.durationValue

        const updateCounter = (currentTime) => {
            const elapsed = currentTime - startTime
            const progress = Math.min(elapsed / duration, 1)
            
            // Use easeOutCubic for smooth animation
            const easeProgress = 1 - Math.pow(1 - progress, 3)
            const currentValue = Math.floor(startValue + (endValue - startValue) * easeProgress)
            
            this.numberTarget.textContent = this.formatNumber(currentValue)
            
            if (progress < 1) {
                this.animationFrame = requestAnimationFrame(updateCounter)
            } else {
                // Ensure final value is exact
                this.numberTarget.textContent = this.formatNumber(endValue)
            }
        }

        this.animationFrame = requestAnimationFrame(updateCounter)
    }

    /**
     * Format number with prefix and suffix
     * @param {number} value - The number to format
     * @returns {string} Formatted number string
     */
    formatNumber(value) {
        const formattedValue = value.toLocaleString()
        return `${this.prefixValue}${formattedValue}${this.suffixValue}`
    }
}