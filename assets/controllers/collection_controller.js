import { Controller } from '@hotwired/stimulus'

/**
 * Collection controller for managing dynamic form collections
 * 
 * Handles adding and removing items from collection fields like
 * operational objectives, evaluable objectives, etc.
 */
export default class extends Controller {
    static targets = ['collection', 'prototype']
    static values = { 
        index: Number,
        prototypeName: String
    }

    connect() {
        // Initialize index based on existing items
        this.indexValue = this.collectionTarget.children.length
        
        // Set up prototype name (default to __name__)
        if (!this.prototypeName) {
            this.prototypeNameValue = '__name__'
        }
    }

    addItem(event) {
        event.preventDefault()
        
        // Get the prototype HTML
        const prototype = this.prototypeTarget.dataset.prototype
        
        // Replace the prototype name with the current index
        const newItem = prototype.replace(
            new RegExp(this.prototypeNameValue, 'g'),
            this.indexValue
        )
        
        // Create a wrapper div for the new item
        const wrapper = document.createElement('div')
        wrapper.className = 'collection-item mb-2'
        wrapper.innerHTML = `
            <div class="input-group">
                ${newItem}
                <button type="button" class="btn btn-outline-danger" data-action="click->collection#removeItem">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `
        
        // Add the new item to the collection
        this.collectionTarget.appendChild(wrapper)
        
        // Increment the index for the next item
        this.indexValue++
        
        // Focus on the new input
        const newInput = wrapper.querySelector('input')
        if (newInput) {
            newInput.focus()
        }
    }

    removeItem(event) {
        event.preventDefault()
        
        // Find the collection item wrapper and remove it
        const item = event.target.closest('.collection-item')
        if (item) {
            item.remove()
        }
    }
}
