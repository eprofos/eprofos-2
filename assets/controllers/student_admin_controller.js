import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { 
        id: Number,
        resetPasswordUrl: String,
        verifyEmailUrl: String,
        generatePasswordUrl: String,
        resetPasswordToken: String,
        verifyEmailToken: String,
        generatePasswordToken: String
    }

    connect() {
        // Controller is connected
    }

    async sendPasswordReset(event) {
        event.preventDefault()
        
        try {
            const response = await fetch(this.resetPasswordUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `_token=${this.resetPasswordTokenValue}`
            })
            
            const data = await response.json()
            
            if (data.success) {
                this.showSuccess('Email de réinitialisation envoyé avec succès.')
            } else {
                this.showError('Erreur: ' + data.message)
            }
        } catch (error) {
            this.showError('Erreur lors de l\'envoi de l\'email.')
        }
    }

    async sendEmailVerification(event) {
        event.preventDefault()
        
        try {
            const response = await fetch(this.verifyEmailUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `_token=${this.verifyEmailTokenValue}`
            })
            
            const data = await response.json()
            
            if (data.success) {
                this.showSuccess('Email de vérification envoyé avec succès.')
            } else {
                this.showError('Erreur: ' + data.message)
            }
        } catch (error) {
            this.showError('Erreur lors de l\'envoi de l\'email.')
        }
    }

    async generatePassword(event) {
        event.preventDefault()
        
        if (!confirm('Êtes-vous sûr de vouloir générer un nouveau mot de passe pour cet étudiant ?')) {
            return
        }
        
        try {
            const response = await fetch(this.generatePasswordUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `_token=${this.generatePasswordTokenValue}`
            })
            
            const data = await response.json()
            
            if (data.success) {
                this.showSuccess('Nouveau mot de passe généré et envoyé par email.')
                // Optionally refresh the page to update security info
                window.location.reload()
            } else {
                this.showError('Erreur: ' + data.message)
            }
        } catch (error) {
            this.showError('Erreur lors de la génération du mot de passe.')
        }
    }

    showSuccess(message) {
        // You can replace this with a proper notification system like toast notifications
        alert(message)
    }

    showError(message) {
        // You can replace this with a proper notification system like toast notifications
        alert(message)
    }
}
