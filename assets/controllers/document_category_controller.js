import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["row", "tbody"]
    static values = { 
        moveUrl: String 
    }

    connect() {
        this.isReorderMode = false
        this.orderChanged = false
    }

    toggleReorderMode() {
        this.isReorderMode = !this.isReorderMode
        
        if (this.isReorderMode) {
            this.enableReorderMode()
        } else {
            this.disableReorderMode()
        }
    }

    enableReorderMode() {
        // Make only top-level rows draggable (level 0)
        this.rowTargets.forEach(row => {
            const level = parseInt(row.dataset.level)
            if (level === 0) {
                row.draggable = true
                row.classList.add('reorder-mode')
                row.addEventListener('dragstart', this.handleDragStart.bind(this))
                row.addEventListener('dragover', this.handleDragOver.bind(this))
                row.addEventListener('drop', this.handleDrop.bind(this))
                row.addEventListener('dragend', this.handleDragEnd.bind(this))
            }
        })

        // Update button text
        const reorderBtn = document.querySelector('[data-action="click->document-category#toggleReorderMode"]')
        if (reorderBtn) {
            reorderBtn.innerHTML = '<i class="fas fa-times me-1"></i>Annuler'
            reorderBtn.classList.remove('btn-outline-primary')
            reorderBtn.classList.add('btn-outline-secondary')
        }

        // Show instructions
        this.showInstructions()
    }

    disableReorderMode() {
        // Remove draggable properties and event listeners
        this.rowTargets.forEach(row => {
            row.draggable = false
            row.classList.remove('reorder-mode', 'drag-over')
            row.removeEventListener('dragstart', this.handleDragStart.bind(this))
            row.removeEventListener('dragover', this.handleDragOver.bind(this))
            row.removeEventListener('drop', this.handleDrop.bind(this))
            row.removeEventListener('dragend', this.handleDragEnd.bind(this))
        })

        // Update button text
        const reorderBtn = document.querySelector('[data-action="click->document-category#toggleReorderMode"]')
        if (reorderBtn) {
            reorderBtn.innerHTML = '<i class="fas fa-arrows-alt me-1"></i>Réorganiser'
            reorderBtn.classList.remove('btn-outline-secondary')
            reorderBtn.classList.add('btn-outline-primary')
        }

        // Hide instructions
        this.hideInstructions()
        this.orderChanged = false
    }

    handleDragStart(event) {
        this.draggedRow = event.target.closest('[data-document-category-target="row"]')
        this.draggedRow.style.opacity = '0.5'
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/html', this.draggedRow.outerHTML)
    }

    handleDragOver(event) {
        if (event.preventDefault) {
            event.preventDefault()
        }
        
        const targetRow = event.target.closest('[data-document-category-target="row"]')
        const targetLevel = targetRow ? parseInt(targetRow.dataset.level) : null
        
        // Only allow dropping on top-level categories (level 0)
        if (targetRow && targetRow !== this.draggedRow && targetLevel === 0) {
            targetRow.classList.add('drag-over')
        }
        
        event.dataTransfer.dropEffect = 'move'
        return false
    }

    handleDrop(event) {
        if (event.stopPropagation) {
            event.stopPropagation()
        }

        const targetRow = event.target.closest('[data-document-category-target="row"]')
        const targetLevel = targetRow ? parseInt(targetRow.dataset.level) : null
        
        // Only allow dropping on top-level categories
        if (targetRow && targetRow !== this.draggedRow && targetLevel === 0) {
            this.moveToNewParent(this.draggedRow, targetRow)
        }

        return false
    }

    handleDragEnd(event) {
        event.target.style.opacity = ''
        
        // Remove drag-over class from all rows
        this.rowTargets.forEach(row => {
            row.classList.remove('drag-over')
        })
    }

    moveToNewParent(draggedRow, targetRow) {
        const draggedCategoryId = parseInt(draggedRow.dataset.categoryId)
        const targetCategoryId = parseInt(targetRow.dataset.categoryId)

        // Get CSRF token from the dragged row's data attribute
        const csrfToken = draggedRow.dataset.csrfToken
        
        if (!csrfToken) {
            this.showErrorMessage('Token CSRF introuvable pour cette catégorie.')
            return
        }

        const formData = new FormData()
        formData.append('parent_id', targetCategoryId.toString())
        formData.append('_token', csrfToken)

        // Show loading state
        this.showLoadingMessage('Déplacement en cours...')

        fetch(this.moveUrlValue.replace('__ID__', draggedCategoryId.toString()), {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccessMessage(data.message || 'La catégorie a été déplacée avec succès.')
                // Reload the page to reflect the changes
                setTimeout(() => {
                    window.location.reload()
                }, 1500)
            } else {
                this.showErrorMessage(data.error || 'Erreur lors du déplacement de la catégorie.')
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showErrorMessage('Erreur lors du déplacement de la catégorie.')
        })
        .finally(() => {
            this.hideLoadingMessage()
        })
    }

    showInstructions() {
        const instructionsHtml = `
            <div id="reorder-instructions" class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Mode réorganisation activé</strong><br>
                Glissez-déposez une catégorie sur une autre catégorie de niveau supérieur pour la déplacer.
                Les sous-catégories seront automatiquement déplacées avec leur parent.
            </div>
        `
        
        const cardBody = document.querySelector('.card-body')
        if (cardBody) {
            cardBody.insertAdjacentHTML('afterbegin', instructionsHtml)
        }
    }

    hideInstructions() {
        const instructions = document.getElementById('reorder-instructions')
        if (instructions) {
            instructions.remove()
        }
    }

    showLoadingMessage(message) {
        this.showFlashMessage(message, 'info')
    }

    hideLoadingMessage() {
        const loadingAlert = document.querySelector('.alert-info')
        if (loadingAlert && loadingAlert.textContent.includes('en cours')) {
            loadingAlert.remove()
        }
    }

    showSuccessMessage(message) {
        this.showFlashMessage(message, 'success')
    }

    showErrorMessage(message) {
        this.showFlashMessage(message, 'danger')
    }

    showFlashMessage(message, type) {
        const flashHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `
        
        const container = document.querySelector('.container-xl')
        if (container) {
            container.insertAdjacentHTML('afterbegin', flashHtml)
            
            // Auto-dismiss after 5 seconds (except for loading messages)
            if (type !== 'info') {
                setTimeout(() => {
                    const alert = container.querySelector(`.alert-${type}`)
                    if (alert) {
                        alert.remove()
                    }
                }, 5000)
            }
        }
    }
}
