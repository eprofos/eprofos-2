import { Controller } from "@hotwired/stimulus"

/**
 * Version Comparison Controller
 * 
 * Handles version comparison form logic and navigation.
 * Manages version selection validation and comparison URL generation.
 */
export default class extends Controller {
    static targets = ["version1", "version2", "compareButton", "form"]

    /**
     * Controller initialization
     */
    connect() {
        console.log('Version comparison controller connected')
        this.updateCompareButton()
    }

    /**
     * Handle version selection change
     */
    versionChanged() {
        this.updateCompareButton()
    }

    /**
     * Update compare button state based on selections
     */
    updateCompareButton() {
        const v1 = this.version1Target.value
        const v2 = this.version2Target.value
        
        if (v1 && v2 && v1 !== v2) {
            this.compareButtonTarget.disabled = false
        } else {
            this.compareButtonTarget.disabled = true
        }
    }

    /**
     * Build comparison URL using known route pattern
     * Note: This matches the route defined in DocumentVersionController
     * Route: /admin/document-versions/compare/{id1}/{id2}
     */
    buildCompareUrl(id1, id2) {
        return `/admin/document-versions/compare/${id1}/${id2}`
    }

    /**
     * Handle form submission for comparison
     */
    submit(event) {
        event.preventDefault()
        
        const v1 = this.version1Target.value
        const v2 = this.version2Target.value
        
        if (v1 && v2 && v1 !== v2) {
            const compareUrl = this.buildCompareUrl(v1, v2)
            window.location.href = compareUrl
        }
    }

    /**
     * Clear all selections
     */
    clear() {
        this.version1Target.value = ''
        this.version2Target.value = ''
        this.updateCompareButton()
    }

    /**
     * Set quick comparison (e.g., latest vs previous)
     * Called from dropdown buttons in version table
     */
    quickCompare(event) {
        const { version1, version2 } = event.params
        
        if (version1 && version2) {
            // Build the comparison URL directly and navigate
            const compareUrl = this.buildCompareUrl(version1, version2)
            window.location.href = compareUrl
        } else if (version1 || version2) {
            // If only one version is provided, set it in the form for manual selection
            if (version1) {
                this.version1Target.value = version1
            }
            
            if (version2) {
                this.version2Target.value = version2
            }
            
            this.updateCompareButton()
            
            // Scroll to the comparison form
            this.element.scrollIntoView({ behavior: 'smooth' })
        }
    }
}
