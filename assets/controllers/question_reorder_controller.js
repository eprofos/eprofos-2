import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["row", "tbody"]
    static values = { 
        url: String,
        questionnaireId: Number 
    }

    connect() {
        this.isReorderMode = false
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
        // Make rows draggable
        this.rowTargets.forEach(row => {
            row.draggable = true
            row.classList.add('reorder-mode')
            row.addEventListener('dragstart', this.handleDragStart.bind(this))
            row.addEventListener('dragover', this.handleDragOver.bind(this))
            row.addEventListener('drop', this.handleDrop.bind(this))
            row.addEventListener('dragend', this.handleDragEnd.bind(this))
        })

        // Update button text
        const reorderBtn = document.querySelector('[data-action="click->question-reorder#toggleReorderMode"]')
        if (reorderBtn) {
            reorderBtn.innerHTML = '<i class="fas fa-save me-1"></i>Sauvegarder l\'ordre'
            reorderBtn.classList.remove('btn-outline-primary')
            reorderBtn.classList.add('btn-success')
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
        const reorderBtn = document.querySelector('[data-action="click->question-reorder#toggleReorderMode"]')
        if (reorderBtn) {
            reorderBtn.innerHTML = '<i class="fas fa-sort me-1"></i>Réorganiser'
            reorderBtn.classList.remove('btn-success')
            reorderBtn.classList.add('btn-outline-primary')
        }

        // Hide instructions
        this.hideInstructions()

        // Save the new order
        if (this.orderChanged) {
            this.saveOrder()
        }
    }

    handleDragStart(event) {
        this.draggedRow = event.target.closest('[data-question-reorder-target="row"]')
        this.draggedRow.style.opacity = '0.5'
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/html', this.draggedRow.outerHTML)
    }

    handleDragOver(event) {
        if (event.preventDefault) {
            event.preventDefault()
        }
        
        const targetRow = event.target.closest('[data-question-reorder-target="row"]')
        if (targetRow && targetRow !== this.draggedRow) {
            targetRow.classList.add('drag-over')
        }
        
        event.dataTransfer.dropEffect = 'move'
        return false
    }

    handleDrop(event) {
        if (event.stopPropagation) {
            event.stopPropagation()
        }

        const targetRow = event.target.closest('[data-question-reorder-target="row"]')
        
        if (targetRow && targetRow !== this.draggedRow) {
            const tbody = this.tbodyTarget
            const draggedIndex = Array.from(tbody.children).indexOf(this.draggedRow)
            const targetIndex = Array.from(tbody.children).indexOf(targetRow)

            if (draggedIndex < targetIndex) {
                tbody.insertBefore(this.draggedRow, targetRow.nextSibling)
            } else {
                tbody.insertBefore(this.draggedRow, targetRow)
            }

            this.orderChanged = true
            this.updateRowNumbers()
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

    updateRowNumbers() {
        this.rowTargets.forEach((row, index) => {
            const orderCell = row.querySelector('td:first-child span:first-child')
            if (orderCell) {
                orderCell.textContent = index + 1
            }
        })
    }

    saveOrder() {
        const questionIds = this.rowTargets.map(row => 
            parseInt(row.dataset.questionId)
        )

        const formData = new FormData()
        questionIds.forEach((id, index) => {
            formData.append(`questionIds[${index}]`, id.toString())
        })

        fetch(this.urlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccessMessage('L\'ordre des questions a été sauvegardé avec succès.')
            } else {
                this.showErrorMessage('Erreur lors de la sauvegarde: ' + (data.message || 'Erreur inconnue'))
            }
        })
        .catch(error => {
            console.error('Error:', error)
            this.showErrorMessage('Erreur lors de la sauvegarde de l\'ordre des questions.')
        })
        .finally(() => {
            this.orderChanged = false
        })
    }

    showInstructions() {
        const instructionsHtml = `
            <div id="reorder-instructions" class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Mode réorganisation activé</strong><br>
                Glissez-déposez les lignes pour réorganiser l'ordre des questions. 
                Cliquez sur "Sauvegarder l'ordre" pour confirmer les modifications.
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
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = container.querySelector('.alert')
                if (alert) {
                    alert.remove()
                }
            }, 5000)
        }
    }
}
