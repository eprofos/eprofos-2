import { Controller } from '@hotwired/stimulus';
import { Modal } from '@tabler/core';

export default class extends Controller {
    static targets = ['modal'];
    static values = { modalId: String };
    
    connect() {
        // Initialize modal instance when controller connects
        this.modalElement = this.hasModalTarget ? this.modalTarget : document.getElementById(this.modalIdValue);
        if (this.modalElement) {
            this.modal = new Modal(this.modalElement);
        }
    }
    
    disconnect() {
        // Clean up modal when controller disconnects
        if (this.modal) {
            this.modal.dispose();
        }
    }
    
    open(event) {
        event.preventDefault();
        if (this.modal) {
            this.modal.show();
        }
    }
    
    close(event) {
        event.preventDefault();
        if (this.modal) {
            this.modal.hide();
        }
    }
}
