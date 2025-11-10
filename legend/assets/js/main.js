/**
 * Ex Chk - Main JavaScript File
 * Enhanced functionality with animations and interactions
 */

class ExChkApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initAnimations();
        this.setupPresenceHeartbeat();
        this.setupNotifications();
        this.setupFormValidation();
        this.setupProgressBars();
        this.setupTooltips();
    }

    setupEventListeners() {
        // Navigation interactions
        document.addEventListener('DOMContentLoaded', () => {
            this.setupNavigation();
            this.setupModals();
            this.setupButtons();
            this.setupTabs();
            this.setupCards();
        });

        // Window events
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });

        // Visibility change for presence
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseHeartbeat();
            } else {
                this.resumeHeartbeat();
            }
        });
    }

    setupNavigation() {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                this.animateNavClick(item);
            });
        });

        // Mobile menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const sideDrawer = document.querySelector('.side-drawer');
        
        if (menuToggle && sideDrawer) {
            menuToggle.addEventListener('click', () => {
                this.toggleSideDrawer();
            });
        }

        // Back button animations
        const backButtons = document.querySelectorAll('.back-btn, .back-to-dashboard');
        backButtons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                this.animateBackButton(btn, true);
            });
            btn.addEventListener('mouseleave', () => {
                this.animateBackButton(btn, false);
            });
        });
    }

    setupModals() {
        // History modal
        const historyButton = document.getElementById('historyButton');
        const historyModal = document.getElementById('historyModal');
        const closeModal = document.querySelector('.close-modal');

        if (historyButton && historyModal) {
            historyButton.addEventListener('click', () => {
                this.openModal(historyModal);
            });
        }

        if (closeModal) {
            closeModal.addEventListener('click', () => {
                this.closeModal();
            });
        }

        // Close modal on outside click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeModal();
            }
        });
    }

    setupButtons() {
        // Enhanced button interactions
        const buttons = document.querySelectorAll('.btn, .tool-btn, .telegram-login-button');
        buttons.forEach(btn => {
            if (!btn.disabled) {
                btn.addEventListener('click', (e) => {
                    this.animateButtonClick(btn);
                });

                btn.addEventListener('mouseenter', () => {
                    this.animateButtonHover(btn, true);
                });

                btn.addEventListener('mouseleave', () => {
                    this.animateButtonHover(btn, false);
                });
            }
        });

        // Credit claim button
        const claimButton = document.getElementById('claimCreditsBtn');
        if (claimButton) {
            claimButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleCreditClaim();
            });
        }

        // Check buttons
        const checkButton = document.getElementById('checkButton');
        if (checkButton) {
            checkButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleCheck();
            });
        }
    }

    setupTabs() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.dataset.tab;
                this.switchTab(targetTab, tabButtons, tabContents);
            });
        });
    }

    setupCards() {
        const cards = document.querySelectorAll('.card, .tool-card, .wallet-card, .stat-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.animateCardHover(card, true);
            });
            card.addEventListener('mouseleave', () => {
                this.animateCardHover(card, false);
            });
        });
    }

    // Animation methods
    initAnimations() {
        // Fade in page content
        const content = document.querySelector('.container, .main-wrapper');
        if (content) {
            content.style.opacity = '0';
            content.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                content.style.transition = 'all 0.6s ease-out';
                content.style.opacity = '1';
                content.style.transform = 'translateY(0)';
            }, 100);
        }

        // Animate cards on load
        const cards = document.querySelectorAll('.card, .tool-card, .stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 200 + (index * 100));
        });
    }

    animateNavClick(item) {
        item.style.transform = 'scale(0.95)';
        setTimeout(() => {
            item.style.transform = 'scale(1)';
        }, 150);
    }

    animateBackButton(btn, isHover) {
        if (isHover) {
            btn.style.transform = 'translateX(-5px)';
            btn.style.color = '#ffffff';
        } else {
            btn.style.transform = 'translateX(0)';
            btn.style.color = '#00d4ff';
        }
    }

    animateButtonClick(btn) {
        btn.style.transform = 'scale(0.95)';
        btn.style.boxShadow = '0 5px 15px rgba(0, 212, 255, 0.4)';
        
        setTimeout(() => {
            btn.style.transform = 'scale(1)';
            btn.style.boxShadow = '0 10px 20px rgba(0, 212, 255, 0.3)';
        }, 150);
    }

    animateButtonHover(btn, isHover) {
        if (isHover) {
            btn.style.transform = 'translateY(-2px)';
            btn.style.boxShadow = '0 15px 30px rgba(0, 212, 255, 0.4)';
        } else {
            btn.style.transform = 'translateY(0)';
            btn.style.boxShadow = '0 10px 20px rgba(0, 212, 255, 0.3)';
        }
    }

    animateCardHover(card, isHover) {
        if (isHover) {
            card.style.transform = 'translateY(-5px) scale(1.02)';
            card.style.boxShadow = '0 20px 40px rgba(0, 212, 255, 0.15)';
        } else {
            card.style.transform = 'translateY(0) scale(1)';
            card.style.boxShadow = '0 10px 20px rgba(0, 0, 0, 0.1)';
        }
    }

    switchTab(targetTab, tabButtons, tabContents) {
        // Remove active class from all tabs
        tabButtons.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => {
            content.classList.remove('active');
            content.style.opacity = '0';
        });

        // Add active class to clicked tab
        const activeButton = document.querySelector(`[data-tab="${targetTab}"]`);
        const activeContent = document.getElementById(targetTab);

        if (activeButton && activeContent) {
            activeButton.classList.add('active');
            
            setTimeout(() => {
                activeContent.classList.add('active');
                activeContent.style.opacity = '1';
            }, 150);
        }
    }

    // Modal methods
    openModal(modal) {
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        
        setTimeout(() => {
            modal.style.opacity = '1';
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.transform = 'scale(1)';
            }
        }, 10);
    }

    closeModal() {
        const modal = document.querySelector('.modal[style*="flex"]');
        if (modal) {
            modal.style.opacity = '0';
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.transform = 'scale(0.9)';
            }
            
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    }

    toggleSideDrawer() {
        const drawer = document.querySelector('.side-drawer');
        const overlay = document.querySelector('.drawer-overlay');
        
        if (drawer) {
            drawer.classList.toggle('open');
            if (overlay) {
                overlay.classList.toggle('active');
            }
        }
    }

    // Presence heartbeat
    setupPresenceHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            this.sendHeartbeat();
        }, 120000); // 2 minutes
    }

    sendHeartbeat() {
        if (!document.hidden) {
            fetch('api/presence.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    timestamp: Date.now()
                })
            }).catch(err => {
                console.warn('Heartbeat failed:', err);
            });
        }
    }

    pauseHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }
    }

    resumeHeartbeat() {
        this.setupPresenceHeartbeat();
        this.sendHeartbeat(); // Send immediate heartbeat
    }

    // Credit claim functionality
    async handleCreditClaim() {
        const button = document.getElementById('claimCreditsBtn');
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';

        try {
            const response = await fetch('api/claim_credits.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Credits claimed successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                this.showNotification(result.message || 'Failed to claim credits', 'error');
                button.disabled = false;
                button.innerHTML = originalText;
            }
        } catch (error) {
            this.showNotification('Network error occurred', 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    // Check functionality
    async handleCheck() {
        const form = document.getElementById('checkForm');
        const button = document.getElementById('checkButton');
        const input = document.querySelector('#cardsInput, #sitesInput');
        
        if (!input || !input.value.trim()) {
            this.showNotification('Please enter data to check', 'warning');
            return;
        }

        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';

        // Add progress animation
        this.showProgressBar();

        try {
            // Simulate check process with progress updates
            await this.processChecks(input.value.trim());
        } catch (error) {
            this.showNotification('Check failed: ' + error.message, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
            this.hideProgressBar();
        }
    }

    async processChecks(data) {
        const lines = data.split('\n').filter(line => line.trim());
        const total = lines.length;
        let completed = 0;

        for (const line of lines) {
            // Process each line
            await this.checkSingleItem(line.trim());
            completed++;
            this.updateProgress((completed / total) * 100);
            
            // Small delay to show progress
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    async checkSingleItem(item) {
        // This would be implemented based on the specific checker
        return new Promise(resolve => setTimeout(resolve, 500));
    }

    // Progress bar
    showProgressBar() {
        let progressBar = document.querySelector('.progress-container');
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.className = 'progress-container';
            progressBar.innerHTML = `
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-text">0%</div>
            `;
            document.body.appendChild(progressBar);
        }
        progressBar.style.display = 'block';
    }

    updateProgress(percent) {
        const fill = document.querySelector('.progress-fill');
        const text = document.querySelector('.progress-text');
        
        if (fill && text) {
            fill.style.width = percent + '%';
            text.textContent = Math.round(percent) + '%';
        }
    }

    hideProgressBar() {
        const progressBar = document.querySelector('.progress-container');
        if (progressBar) {
            progressBar.style.display = 'none';
        }
    }

    // Notifications
    setupNotifications() {
        // Create notification container if it doesn't exist
        if (!document.querySelector('.notification-container')) {
            const container = document.createElement('div');
            container.className = 'notification-container';
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info') {
        const container = document.querySelector('.notification-container');
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        notification.innerHTML = `
            <i class="fas ${icons[type] || icons.info}"></i>
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;

        container.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        // Auto remove after 5 seconds
        setTimeout(() => {
            this.removeNotification(notification);
        }, 5000);

        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            this.removeNotification(notification);
        });
    }

    removeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Form validation
    setupFormValidation() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    validateForm(form) {
        const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.showFieldError(input, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(input);
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        const error = document.createElement('div');
        error.className = 'field-error';
        error.textContent = message;
        
        field.parentNode.appendChild(error);
        field.classList.add('error');
    }

    clearFieldError(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        field.classList.remove('error');
    }

    // Tooltips
    setupTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

        setTimeout(() => {
            tooltip.classList.add('show');
        }, 10);
    }

    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    // Cleanup
    cleanup() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }
    }
}

// Initialize the app
const app = new ExChkApp();

// Export for global access
window.ExChkApp = app;
