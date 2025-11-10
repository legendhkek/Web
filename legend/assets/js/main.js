/**
 * Ex Chk - Main JavaScript File
 * Enhanced functionality with animations and interactions
 */

class ExChkApp {
    constructor() {
        this.themeKey = 'legend_theme';
        this.heartbeatInterval = null;
        this.clockInterval = null;
        this.countdownInterval = null;
        this.ready = false;
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => this.onReady());

        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseHeartbeat();
            } else {
                this.resumeHeartbeat();
            }
        });
    }

    onReady() {
        if (this.ready) {
            return;
        }
        this.ready = true;

        this.initializeTheme();
        this.setupThemeToggle();
        this.initializeClock();
        this.setupNavigation();
        this.setupModals();
        this.setupButtons();
        this.setupTabs();
        this.setupCards();
        this.initAnimations();
        this.setupNotifications();
        this.setupFormValidation();
        this.setupTooltips();
        this.setupPresenceHeartbeat();
        this.setupDashboard();
    }

    setupNavigation() {
        const navItems = document.querySelectorAll('.nav-item, .bottom-nav__item');
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                this.animateNavClick(item);
            });
        });

        const menuToggle = document.querySelector('.menu-toggle');
        const drawer = document.querySelector('.side-drawer, .drawer');
        const overlay = document.querySelector('.drawer-overlay');

        if (menuToggle && drawer) {
            menuToggle.addEventListener('click', () => this.toggleSideDrawer());
        }

        if (overlay) {
            overlay.addEventListener('click', () => this.toggleSideDrawer(false));
        }

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

    toggleSideDrawer(forceState = null) {
        const drawer = document.querySelector('.side-drawer, .drawer');
        const overlay = document.querySelector('.drawer-overlay');
        
        if (!drawer) {
            return;
        }

        const isOpen = drawer.classList.contains('open') || drawer.classList.contains('active');
        const shouldOpen = forceState === null ? !isOpen : forceState;

        drawer.classList.toggle('open', shouldOpen);
        drawer.classList.toggle('active', shouldOpen);

        if (overlay) {
            overlay.classList.toggle('active', shouldOpen);
        }
    }

    // Presence heartbeat
    setupPresenceHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }

        this.heartbeatInterval = setInterval(() => {
            this.sendHeartbeat();
        }, 120000); // 2 minutes

        this.sendHeartbeat();
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
    }

    // Credit claim functionality
    async handleCreditClaim() {
        const button = document.getElementById('claimCreditsBtn');
        if (!button) {
            return;
        }

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
                this.createInlineAlert('success', result.message || 'Credits claimed successfully!');

                if (result.next_claim_time) {
                    const countdown = document.querySelector('[data-next-claim]');
                    if (countdown) {
                        countdown.dataset.nextClaim = result.next_claim_time;
                        this.setupCountdownFromDataset(true);
                    }
                }

                setTimeout(() => {
                    location.reload();
                }, 1800);
            } else {
                this.createInlineAlert('error', result.message || 'Failed to claim credits.');
                button.disabled = false;
                button.innerHTML = originalText;

                if (result.next_claim_time) {
                    const countdown = document.querySelector('[data-next-claim]');
                    if (countdown) {
                        countdown.dataset.nextClaim = result.next_claim_time;
                        this.setupCountdownFromDataset(true);
                    }
                }
            }
        } catch (error) {
            console.error('Claim error:', error);
            this.createInlineAlert('error', 'Network error occurred. Please try again.');
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

    // Dashboard specific enhancements
    setupDashboard() {
        if (!document.body.classList.contains('page-dashboard')) {
            return;
        }

        this.animateStaggeredContent();
        this.activateProgressMeters();
        this.renderSparklines();
        this.setupCountdownFromDataset();
    }

    animateStaggeredContent() {
        const items = document.querySelectorAll('.stagger');
        if (!items.length) {
            return;
        }

        items.forEach((item, index) => {
            setTimeout(() => {
                item.classList.add('fade-in-up');
            }, index * 120);
        });
    }

    activateProgressMeters() {
        const hosts = document.querySelectorAll('[data-progress]');
        hosts.forEach(host => {
            const fill = host.querySelector('.progress-bar__fill');
            if (!fill) {
                return;
            }

            const raw = parseFloat(host.dataset.progress);
            const value = Number.isNaN(raw) ? 0 : Math.max(0, Math.min(100, raw));

            fill.style.transform = 'scaleX(0)';
            requestAnimationFrame(() => {
                fill.style.transform = `scaleX(${value / 100})`;
            });
        });
    }

    renderSparklines() {
        const sparklineElements = document.querySelectorAll('.sparkline[data-points]');
        sparklineElements.forEach(element => {
            const rawPoints = element.dataset.points || '';
            const points = rawPoints.split(',').map(value => parseFloat(value.trim())).filter(value => !Number.isNaN(value));

            if (!points.length) {
                return;
            }

            const max = Math.max(...points);
            const min = Math.min(...points);
            const spread = max - min || 1;

            element.innerHTML = '<div class="sparkline__bar"></div>';
            const plot = document.createElement('div');
            plot.className = 'sparkline__plot';

            const width = 100 / Math.max(points.length, 1);
            points.forEach((value, index) => {
                const normalized = (value - min) / spread;
                const point = document.createElement('div');
                point.className = 'sparkline__point';
                point.style.left = `${(index / points.length) * 100}%`;
                point.style.width = `${Math.max(6, width * 0.7)}%`;
                point.style.height = `${Math.max(12, normalized * 100)}%`;
                plot.appendChild(point);
            });

            element.appendChild(plot);
        });
    }

    setupCountdownFromDataset(force = false) {
        const countdown = document.querySelector('[data-next-claim]');
        if (!countdown) {
            return;
        }

        const nextClaimRaw = parseInt(countdown.dataset.nextClaim, 10);
        if (!nextClaimRaw) {
            return;
        }

        const defaultText = countdown.dataset.defaultText || 'Claim free credits once per day';
        const readyText = countdown.dataset.readyText || 'Claim is ready';

        const update = () => {
            const now = Math.floor(Date.now() / 1000);
            const diff = nextClaimRaw - now;

            if (diff <= 0) {
                countdown.textContent = readyText;
                if (this.countdownInterval) {
                    clearInterval(this.countdownInterval);
                    this.countdownInterval = null;
                }
                return;
            }

            const hours = Math.floor(diff / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            const seconds = diff % 60;

            let label;
            if (hours > 0) {
                label = `${hours}h ${minutes}m ${seconds}s`;
            } else if (minutes > 0) {
                label = `${minutes}m ${seconds}s`;
            } else {
                label = `${seconds}s`;
            }

            countdown.textContent = `Next claim in ${label}`;
        };

        if (force && this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }

        update();

        if (!this.countdownInterval) {
            this.countdownInterval = setInterval(update, 1000);
        }
    }

    createInlineAlert(type, message) {
        const container = document.querySelector('[data-inline-alerts]') || document.querySelector('.page-shell') || document.body;

        const alert = document.createElement('div');
        alert.className = `alert ${type === 'error' ? 'alert--danger' : ''}`;
        alert.innerHTML = `
            <div class="alert__icon">
                <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
            </div>
            <div class="alert__content">
                <div class="alert__title">${type === 'error' ? 'Action Required' : 'Success'}</div>
                <div class="alert__text">${message}</div>
            </div>
        `;

        container.insertBefore(alert, container.firstChild);

        requestAnimationFrame(() => {
            alert.classList.add('fade-in-up');
        });

        setTimeout(() => {
            alert.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 260);
        }, 6000);

        return alert;
    }

    initializeTheme() {
        const storedTheme = localStorage.getItem(this.themeKey);
        const fallbackTheme = document.body.dataset.theme || 'dark';
        this.setTheme(storedTheme || fallbackTheme);
    }

    setTheme(theme) {
        const normalized = theme === 'light' ? 'light' : 'dark';
        document.body.dataset.theme = normalized;
        document.documentElement.setAttribute('data-theme', normalized);
        localStorage.setItem(this.themeKey, normalized);
        this.updateThemeToggleUI(normalized);
    }

    setupThemeToggle() {
        const toggles = document.querySelectorAll('[data-action="toggle-theme"]');
        if (!toggles.length) {
            return;
        }

        toggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const nextTheme = document.body.dataset.theme === 'light' ? 'dark' : 'light';
                this.setTheme(nextTheme);
            });
        });

        this.updateThemeToggleUI(document.body.dataset.theme || 'dark');
    }

    updateThemeToggleUI(theme) {
        const isLight = theme === 'light';

        document.querySelectorAll('[data-theme-icon]').forEach(icon => {
            icon.classList.remove('fa-sun', 'fa-moon');
            icon.classList.add(isLight ? 'fa-moon' : 'fa-sun');
        });

        document.querySelectorAll('[data-theme-label]').forEach(label => {
            label.textContent = isLight ? 'Light' : 'Dark';
        });
    }

    initializeClock() {
        const targets = document.querySelectorAll('.js-current-time');
        if (!targets.length) {
            return;
        }

        const update = () => {
            const now = new Date();
            targets.forEach(target => {
                const format = target.dataset.format || 'HH:mm';
                target.textContent = this.formatTime(now, format);
            });
        };

        update();

        if (this.clockInterval) {
            clearInterval(this.clockInterval);
        }
        this.clockInterval = setInterval(update, 1000);
    }

    formatTime(date, format = 'HH:mm') {
        const hours = date.getHours();
        const minutes = date.getMinutes().toString().padStart(2, '0');
        const seconds = date.getSeconds().toString().padStart(2, '0');

        if (format === 'hh:mm A') {
            const displayHour = ((hours + 11) % 12) + 1;
            const period = hours >= 12 ? 'PM' : 'AM';
            return `${displayHour.toString().padStart(2, '0')}:${minutes} ${period}`;
        }

        if (format === 'HH:mm:ss') {
            return `${hours.toString().padStart(2, '0')}:${minutes}:${seconds}`;
        }

        return `${hours.toString().padStart(2, '0')}:${minutes}`;
    }

    // Cleanup
    cleanup() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
        }
        if (this.clockInterval) {
            clearInterval(this.clockInterval);
        }
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }
}

// Initialize the app
const app = new ExChkApp();

// Export for global access
window.ExChkApp = app;
