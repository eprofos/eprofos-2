import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["questionsContainer", "questionsInput"];
    static values = { questions: Array };

    connect() {
        this.renderQuestions();
    }

    renderQuestions() {
        this.questionsContainerTarget.innerHTML = '';
        
        this.questionsValue.forEach((question, index) => {
            this.addQuestionToDOM(question, index);
        });
        
        this.updateQuestionsInput();
    }

    addQuestion() {
        const newQuestion = {
            question: '',
            type: 'single',
            answers: ['', '', ''],
            correct_answers: [],
            explanation: '',
            points: 1
        };
        
        this.questionsValue = [...this.questionsValue, newQuestion];
        this.addQuestionToDOM(newQuestion, this.questionsValue.length - 1);
        this.updateQuestionsInput();
    }

    addQuestionToDOM(question, index) {
        const questionDiv = document.createElement('div');
        questionDiv.className = 'card mb-3';
        questionDiv.dataset.questionIndex = index;
        
        questionDiv.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Question ${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" data-action="click->qcm-form#removeQuestion">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Question *</label>
                    <input type="text" class="form-control" data-question-field="question" value="${this.escapeHtml(question.question)}">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Type de question *</label>
                    <select class="form-select" data-question-field="type">
                        <option value="single" ${question.type === 'single' ? 'selected' : ''}>Réponse unique</option>
                        <option value="multiple" ${question.type === 'multiple' ? 'selected' : ''}>Réponses multiples</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Points *</label>
                    <input type="number" class="form-control" data-question-field="points" value="${question.points}" min="1">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Réponses *</label>
                    <div class="answers-container">
                        ${question.answers.map((answer, answerIndex) => `
                            <div class="input-group mb-2">
                                <div class="input-group-text">
                                    <input type="${question.type === 'single' ? 'radio' : 'checkbox'}" 
                                           name="correct_${index}" 
                                           value="${answerIndex}" 
                                           ${question.correct_answers.includes(answerIndex) ? 'checked' : ''}
                                           data-answer-correct>
                                </div>
                                <input type="text" class="form-control" data-answer-field value="${this.escapeHtml(answer)}">
                                <button type="button" class="btn btn-outline-danger" data-action="click->qcm-form#removeAnswer">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="click->qcm-form#addAnswer">
                        <i class="fas fa-plus"></i> Ajouter une réponse
                    </button>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Explication</label>
                    <textarea class="form-control" data-question-field="explanation" rows="2">${this.escapeHtml(question.explanation)}</textarea>
                </div>
            </div>
        `;
        
        this.questionsContainerTarget.appendChild(questionDiv);
        
        // Add event listeners
        this.addQuestionEventListeners(questionDiv, index);
    }

    addQuestionEventListeners(questionDiv, questionIndex) {
        // Question field changes
        questionDiv.querySelectorAll('[data-question-field]').forEach(field => {
            field.addEventListener('input', (e) => {
                const fieldName = e.target.dataset.questionField;
                this.questionsValue[questionIndex][fieldName] = e.target.value;
                
                if (fieldName === 'type') {
                    this.updateAnswerTypes(questionIndex);
                }
                
                this.updateQuestionsInput();
            });
        });
        
        // Answer field changes
        questionDiv.querySelectorAll('[data-answer-field]').forEach((field, answerIndex) => {
            field.addEventListener('input', (e) => {
                this.questionsValue[questionIndex].answers[answerIndex] = e.target.value;
                this.updateQuestionsInput();
            });
        });
        
        // Correct answer changes
        questionDiv.querySelectorAll('[data-answer-correct]').forEach((checkbox, answerIndex) => {
            checkbox.addEventListener('change', (e) => {
                if (this.questionsValue[questionIndex].type === 'single') {
                    this.questionsValue[questionIndex].correct_answers = [answerIndex];
                } else {
                    const correctAnswers = Array.from(
                        questionDiv.querySelectorAll('[data-answer-correct]:checked')
                    ).map(cb => parseInt(cb.value));
                    this.questionsValue[questionIndex].correct_answers = correctAnswers;
                }
                this.updateQuestionsInput();
            });
        });
    }

    addAnswer(event) {
        const questionDiv = event.target.closest('.card');
        const questionIndex = parseInt(questionDiv.dataset.questionIndex);
        
        this.questionsValue[questionIndex].answers.push('');
        this.updateQuestionDisplay(questionIndex);
        this.updateQuestionsInput();
    }

    removeAnswer(event) {
        const answerDiv = event.target.closest('.input-group');
        const questionDiv = event.target.closest('.card');
        const questionIndex = parseInt(questionDiv.dataset.questionIndex);
        const answerIndex = Array.from(answerDiv.parentNode.children).indexOf(answerDiv);
        
        this.questionsValue[questionIndex].answers.splice(answerIndex, 1);
        
        // Update correct answers
        this.questionsValue[questionIndex].correct_answers = 
            this.questionsValue[questionIndex].correct_answers
                .filter(i => i !== answerIndex)
                .map(i => i > answerIndex ? i - 1 : i);
        
        this.updateQuestionDisplay(questionIndex);
        this.updateQuestionsInput();
    }

    removeQuestion(event) {
        const questionDiv = event.target.closest('.card');
        const questionIndex = parseInt(questionDiv.dataset.questionIndex);
        
        this.questionsValue.splice(questionIndex, 1);
        this.renderQuestions();
    }

    updateQuestionDisplay(questionIndex) {
        const questionDiv = this.questionsContainerTarget.children[questionIndex];
        const question = this.questionsValue[questionIndex];
        
        // Update answers display
        const answersContainer = questionDiv.querySelector('.answers-container');
        answersContainer.innerHTML = question.answers.map((answer, answerIndex) => `
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input type="${question.type === 'single' ? 'radio' : 'checkbox'}" 
                           name="correct_${questionIndex}" 
                           value="${answerIndex}" 
                           ${question.correct_answers.includes(answerIndex) ? 'checked' : ''}
                           data-answer-correct>
                </div>
                <input type="text" class="form-control" data-answer-field value="${this.escapeHtml(answer)}">
                <button type="button" class="btn btn-outline-danger" data-action="click->qcm-form#removeAnswer">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        `).join('');
        
        this.addQuestionEventListeners(questionDiv, questionIndex);
    }

    updateAnswerTypes(questionIndex) {
        const questionDiv = this.questionsContainerTarget.children[questionIndex];
        const question = this.questionsValue[questionIndex];
        
        // Reset correct answers for type change
        if (question.type === 'single') {
            this.questionsValue[questionIndex].correct_answers = 
                this.questionsValue[questionIndex].correct_answers.slice(0, 1);
        }
        
        this.updateQuestionDisplay(questionIndex);
    }

    updateQuestionsInput() {
        this.questionsInputTarget.value = JSON.stringify(this.questionsValue);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}