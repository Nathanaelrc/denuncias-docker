/**
 * Wizard.js - Multi-step form with auto-save via localStorage
 * Provides form state persistence and step progression
 */

class ComplaintWizard {
    constructor(formSelector = '#complaintForm', options = {}) {
        this.form = document.querySelector(formSelector);
        this.steps = this.form ? this.form.querySelectorAll('[data-step]') : [];
        this.currentStep = 1;
        this.maxSteps = this.steps.length;
        this.hasSteps = this.maxSteps > 0;
        this.storageKey = 'complaint_wizard_' + (options.storageKey || 'default');
        this.crossChannelStorageKeys = options.crossChannelStorageKeys || [];
        this.autoSaveInterval = options.autoSaveInterval || 10000; // 10 seconds
        this.debug = options.debug || false;
        
        if (!this.form) {
            console.warn('[Wizard] Form not found');
            return;
        }
        
        this.init();
    }
    
    /**
     * Initialize wizard
     */
    init() {
        this.log('Initializing wizard with ' + this.maxSteps + ' steps');

        // Remove cached drafts from other channels to avoid cross-portal leakage.
        this.clearCrossChannelDrafts();
        
        // Restore previous data
        this.restoreData();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Start auto-save timer
        this.startAutoSave();
        
        // Show step state only for real multi-step forms.
        if (this.hasSteps) {
            this.showStep(this.currentStep);
        } else {
            this.updateButtons();
        }
    }
    
    /**
     * Setup form event listeners
     */
    setupEventListeners() {
        // Next button
        const nextButtons = this.form.querySelectorAll('[data-action="next"]');
        nextButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.nextStep();
            });
        });
        
        // Previous button
        const prevButtons = this.form.querySelectorAll('[data-action="prev"]');
        prevButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.prevStep();
            });
        });
        
        // Save just before native submit to keep normal form behavior.
        this.form.addEventListener('submit', () => {
            this.saveData();
        });
        
        // Auto-save on input change
        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                this.log('Form changed, saving...');
                this.saveData();
            });
        });
    }
    
    /**
     * Show specific step
     */
    showStep(step) {
        if (!this.hasSteps) {
            this.updateButtons();
            return;
        }

        // Hide all steps
        this.steps.forEach(s => s.style.display = 'none');
        
        // Show current step
        if (this.steps[step - 1]) {
            this.steps[step - 1].style.display = 'block';
        }
        
        // Update buttons
        this.updateButtons();
        
        // Update progress indicator if exists
        this.updateProgress();
        
        this.log('Showing step ' + step + '/' + this.maxSteps);
    }
    
    /**
     * Move to next step
     */
    nextStep() {
        if (this.currentStep < this.maxSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
            this.saveData();
        }
    }
    
    /**
     * Move to previous step
     */
    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }
    
    /**
     * Update button visibility
     */
    updateButtons() {
        const nextBtn = this.form.querySelector('[data-action="next"]');
        const prevBtn = this.form.querySelector('[data-action="prev"]');
        const submitBtn = this.form.querySelector('[type="submit"]');

        if (!this.hasSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (prevBtn) prevBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'block';
            return;
        }
        
        if (nextBtn) {
            nextBtn.style.display = (this.currentStep < this.maxSteps) ? 'block' : 'none';
        }
        
        if (prevBtn) {
            prevBtn.style.display = (this.currentStep > 1) ? 'block' : 'none';
        }
        
        if (submitBtn) {
            submitBtn.style.display = (this.currentStep === this.maxSteps) ? 'block' : 'none';
        }
    }
    
    /**
     * Update progress indicator
     */
    updateProgress() {
        if (!this.hasSteps) {
            return;
        }

        const progressBar = this.form.querySelector('.progress-bar');
        if (progressBar) {
            const percentage = (this.currentStep / this.maxSteps) * 100;
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', this.currentStep);
        }
        
        // Update step indicators
        this.steps.forEach((step, idx) => {
            const indicator = document.querySelector('[data-step-indicator="' + (idx + 1) + '"]');
            if (indicator) {
                if (idx + 1 < this.currentStep) {
                    indicator.classList.add('completed');
                    indicator.classList.remove('active');
                } else if (idx + 1 === this.currentStep) {
                    indicator.classList.add('active');
                    indicator.classList.remove('completed');
                } else {
                    indicator.classList.remove('active', 'completed');
                }
            }
        });
    }
    
    /**
     * Save form data to localStorage
     */
    saveData() {
        const formData = new FormData(this.form);
        const data = {
            timestamp: new Date().toISOString(),
            currentStep: this.currentStep,
            fields: {}
        };
        
        for (let [key, value] of formData.entries()) {
            data.fields[key] = value;
        }
        
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(data));
            this.log('Data saved to localStorage');
        } catch (e) {
            console.warn('[Wizard] localStorage save failed:', e);
        }
    }
    
    /**
     * Restore form data from localStorage
     */
    restoreData() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            if (!saved) {
                this.log('No saved data found');
                return;
            }
            
            const data = JSON.parse(saved);
            
            // Restore current step (but cap at current view)
            if (data.currentStep && data.currentStep > 1) {
                this.currentStep = Math.min(data.currentStep, this.maxSteps);
            }
            
            // Restore field values
            if (data.fields) {
                for (let [key, value] of Object.entries(data.fields)) {
                    const input = this.form.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = (value === 'on' || value === true);
                        } else if (input.type === 'radio') {
                            const radio = this.form.querySelector(`[name="${key}"][value="${value}"]`);
                            if (radio) radio.checked = true;
                        } else {
                            input.value = value;
                        }
                    }
                }
            }
            
            this.log('Data restored from localStorage (step ' + this.currentStep + ')');
        } catch (e) {
            console.warn('[Wizard] localStorage restore failed:', e);
        }
    }
    
    /**
     * Clear saved data
     */
    clearData() {
        try {
            localStorage.removeItem(this.storageKey);
            this.log('Saved data cleared');
        } catch (e) {
            console.warn('[Wizard] Clear failed:', e);
        }
    }

    /**
     * Clear known draft keys from other channels.
     */
    clearCrossChannelDrafts() {
        if (!Array.isArray(this.crossChannelStorageKeys) || this.crossChannelStorageKeys.length === 0) {
            return;
        }

        this.crossChannelStorageKeys.forEach((key) => {
            if (key && key !== this.storageKey) {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    console.warn('[Wizard] Cross-channel clear failed:', e);
                }
            }
        });
    }
    
    /**
     * Start auto-save timer
     */
    startAutoSave() {
        setInterval(() => {
            this.saveData();
        }, this.autoSaveInterval);
    }
    
    /**
     * Submit form
     */
    submit() {
        this.saveData();
        this.form.dispatchEvent(new Event('submit', { bubbles: true }));
    }
    
    /**
     * Debug logging
     */
    log(message) {
        if (this.debug) {
            console.log('[Wizard] ' + message);
        }
    }
    
    /**
     * Get current form data
     */
    getData() {
        const formData = new FormData(this.form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        return data;
    }
    
    /**
     * Set current step
     */
    goToStep(step) {
        step = Math.max(1, Math.min(step, this.maxSteps));
        this.currentStep = step;
        this.showStep(step);
        this.saveData();
    }
}

// Auto-initialize on page load if element exists
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('[data-wizard]');
    if (form) {
        const configuredKey = form.getAttribute('data-wizard') || 'default';
        const currentStorageKey = 'complaint_wizard_' + configuredKey;
        const knownDraftKeys = [
            'complaint_wizard_complaint',
            'complaint_wizard_complaint_karin',
            'complaint_wizard_complaint_generales'
        ];

        window.complaintWizard = new ComplaintWizard('#' + form.id, {
            storageKey: configuredKey,
            crossChannelStorageKeys: knownDraftKeys.filter((key) => key !== currentStorageKey),
            debug: form.getAttribute('data-debug') === 'true'
        });
    }
});

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ComplaintWizard;
}
