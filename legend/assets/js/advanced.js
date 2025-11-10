/**
 * LEGEND CHECKER - Advanced Features & Enhancements
 * Modern JavaScript for enhanced user experience
 */

class AdvancedFeatures {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'dark';
        this.notifications = [];
        this.init();
    }

    init() {
        this.setupThemeToggle();
        this.setupAdvancedNotifications();
        this.setupSearchFilters();
        this.setupKeyboardShortcuts();
        this.setupAdvancedAnimations();
        this.setupDataVisualization();
        this.setupAutoSave();
        this.setupPerformanceMonitoring();
    }

    // Theme System
    setupThemeToggle() {
        // Create theme toggle button
        const themeToggle = document.createElement('button');
        themeToggle.id = 'themeToggle';
        themeToggle.className = 'theme-toggle';
        themeToggle.innerHTML = `<i class="fas fa-${this.theme === 'dark' ? 'sun' : 'moon'}"></i>`;
        themeToggle.title = 'Toggle Theme';
        
        // Add to header
        const header = document.querySelector('.header, .header-content');
        if (header) {
            header.appendChild(themeToggle);
        }

        // Apply current theme
        this.applyTheme(this.theme);

        // Toggle event
        themeToggle.addEventListener('click', () => {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            this.applyTheme(this.theme);
            localStorage.setItem('theme', this.theme);
            themeToggle.innerHTML = `<i class="fas fa-${this.theme === 'dark' ? 'sun' : 'moon'}"></i>`;
            this.showNotification('Theme changed to ' + this.theme + ' mode', 'success');
        });
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        if (theme === 'light') {
            document.documentElement.style.setProperty('--bg-primary', '#ffffff');
            document.documentElement.style.setProperty('--bg-secondary', '#f5f5f5');
            document.documentElement.style.setProperty('--bg-card', '#ffffff');
            document.documentElement.style.setProperty('--bg-card-hover', '#f0f0f0');
            document.documentElement.style.setProperty('--text-primary', '#1a1a1a');
            document.documentElement.style.setProperty('--text-secondary', '#666666');
            document.documentElement.style.setProperty('--border-color', '#e0e0e0');
        } else {
            document.documentElement.style.setProperty('--bg-primary', '#0a0a0a');
            document.documentElement.style.setProperty('--bg-secondary', '#1a1a1a');
            document.documentElement.style.setProperty('--bg-card', '#2a2a2a');
            document.documentElement.style.setProperty('--bg-card-hover', '#333333');
            document.documentElement.style.setProperty('--text-primary', '#ffffff');
            document.documentElement.style.setProperty('--text-secondary', '#b0b0b0');
            document.documentElement.style.setProperty('--border-color', '#3a3a3a');
        }
    }

    // Advanced Notification System
    setupAdvancedNotifications() {
        // Create notification container if it doesn't exist
        if (!document.querySelector('.advanced-notification-container')) {
            const container = document.createElement('div');
            container.className = 'advanced-notification-container';
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        const container = document.querySelector('.advanced-notification-container');
        const notification = document.createElement('div');
        notification.className = `advanced-notification advanced-notification-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        notification.innerHTML = `
            <div class="notification-icon">
                <i class="fas ${icons[type] || icons.info}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-message">${message}</div>
                <div class="notification-progress"></div>
            </div>
            <button class="notification-close">&times;</button>
        `;

        container.appendChild(notification);

        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);

        // Progress bar
        const progress = notification.querySelector('.notification-progress');
        progress.style.width = '100%';
        progress.style.transition = `width ${duration}ms linear`;
        setTimeout(() => progress.style.width = '0%', 10);

        // Auto remove
        const timeout = setTimeout(() => this.removeNotification(notification), duration);

        // Close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            clearTimeout(timeout);
            this.removeNotification(notification);
        });

        this.notifications.push({ notification, timeout });
    }

    removeNotification(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Search and Filter System
    setupSearchFilters() {
        const searchInputs = document.querySelectorAll('[data-search]');
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                this.filterContent(e.target.value, e.target.dataset.search);
            });
        });
    }

    filterContent(query, targetSelector) {
        const items = document.querySelectorAll(targetSelector);
        const lowerQuery = query.toLowerCase();

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(lowerQuery)) {
                item.style.display = '';
                item.classList.add('search-match');
            } else {
                item.style.display = 'none';
                item.classList.remove('search-match');
            }
        });
    }

    // Keyboard Shortcuts
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('[data-search]');
                if (searchInput) searchInput.focus();
            }

            // Ctrl/Cmd + /: Show shortcuts
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                this.showKeyboardShortcuts();
            }

            // Escape: Close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal[style*="flex"]');
                modals.forEach(modal => modal.style.display = 'none');
            }
        });
    }

    showKeyboardShortcuts() {
        const shortcuts = [
            { keys: 'Ctrl/Cmd + K', action: 'Focus search' },
            { keys: 'Ctrl/Cmd + /', action: 'Show shortcuts' },
            { keys: 'Escape', action: 'Close modals' },
            { keys: 'Ctrl/Cmd + S', action: 'Save current state' }
        ];

        let html = '<div style="padding: 20px;"><h3>Keyboard Shortcuts</h3><ul style="list-style: none; padding: 0;">';
        shortcuts.forEach(shortcut => {
            html += `<li style="margin: 10px 0; display: flex; justify-content: space-between;">
                <kbd style="background: var(--bg-card); padding: 5px 10px; border-radius: 5px;">${shortcut.keys}</kbd>
                <span>${shortcut.action}</span>
            </li>`;
        });
        html += '</ul></div>';

        this.showNotification(html, 'info', 8000);
    }

    // Advanced Animations
    setupAdvancedAnimations() {
        // Intersection Observer for scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.card, .tool-card, .stat-item').forEach(el => {
            observer.observe(el);
        });

        // Parallax effect
        if (window.innerWidth > 768) {
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const parallaxElements = document.querySelectorAll('.parallax');
                parallaxElements.forEach(el => {
                    const speed = el.dataset.speed || 0.5;
                    el.style.transform = `translateY(${scrolled * speed}px)`;
                });
            });
        }
    }

    // Data Visualization
    setupDataVisualization() {
        // Add sparklines to stats
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach(stat => {
            if (!stat.querySelector('.sparkline')) {
                const sparkline = this.createSparkline();
                stat.appendChild(sparkline);
            }
        });
    }

    createSparkline() {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'sparkline');
        svg.setAttribute('width', '60');
        svg.setAttribute('height', '20');
        svg.setAttribute('viewBox', '0 0 60 20');

        // Generate random data for demo
        const data = Array.from({ length: 10 }, () => Math.random() * 20);
        const points = data.map((val, i) => `${i * 6},${20 - val}`).join(' ');

        const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        polyline.setAttribute('points', points);
        polyline.setAttribute('fill', 'none');
        polyline.setAttribute('stroke', 'var(--accent-green)');
        polyline.setAttribute('stroke-width', '2');

        svg.appendChild(polyline);
        return svg;
    }

    // Auto-save functionality
    setupAutoSave() {
        const autoSaveInputs = document.querySelectorAll('[data-autosave]');
        autoSaveInputs.forEach(input => {
            let timeout;
            input.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    const key = e.target.dataset.autosave;
                    localStorage.setItem(key, e.target.value);
                    this.showNotification('Draft saved', 'success', 2000);
                }, 1000);
            });

            // Restore on load
            const saved = localStorage.getItem(input.dataset.autosave);
            if (saved && !input.value) {
                input.value = saved;
            }
        });
    }

    // Performance Monitoring
    setupPerformanceMonitoring() {
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (entry.duration > 1000) {
                        console.warn('Slow operation detected:', entry.name, entry.duration);
                    }
                }
            });

            observer.observe({ entryTypes: ['measure', 'navigation'] });
        }
    }

    // Advanced Data Export
    exportData(data, filename, format = 'json') {
        let content, mimeType;

        if (format === 'json') {
            content = JSON.stringify(data, null, 2);
            mimeType = 'application/json';
        } else if (format === 'csv') {
            content = this.convertToCSV(data);
            mimeType = 'text/csv';
        } else if (format === 'xml') {
            content = this.convertToXML(data);
            mimeType = 'application/xml';
        }

        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${filename}.${format}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        this.showNotification(`Data exported as ${format.toUpperCase()}`, 'success');
    }

    convertToCSV(data) {
        if (!Array.isArray(data) || data.length === 0) return '';
        const headers = Object.keys(data[0]);
        const rows = data.map(obj => headers.map(header => obj[header]).join(','));
        return [headers.join(','), ...rows].join('\n');
    }

    convertToXML(data) {
        let xml = '<?xml version="1.0" encoding="UTF-8"?>\n<data>\n';
        data.forEach(item => {
            xml += '  <item>\n';
            Object.entries(item).forEach(([key, value]) => {
                xml += `    <${key}>${value}</${key}>\n`;
            });
            xml += '  </item>\n';
        });
        xml += '</data>';
        return xml;
    }

    // Copy to Clipboard with feedback
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showNotification('Copied to clipboard!', 'success', 2000);
            return true;
        } catch (err) {
            this.showNotification('Failed to copy', 'error');
            return false;
        }
    }

    // Loading Overlay
    showLoading(message = 'Loading...') {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="loading-spinner"></div>
                <div class="loading-message">${message}</div>
            `;
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    hideLoading() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    // Confirmation Dialog
    async confirm(message, title = 'Confirm Action') {
        return new Promise((resolve) => {
            const dialog = document.createElement('div');
            dialog.className = 'confirm-dialog';
            dialog.innerHTML = `
                <div class="confirm-dialog-content">
                    <h3>${title}</h3>
                    <p>${message}</p>
                    <div class="confirm-dialog-buttons">
                        <button class="btn-cancel">Cancel</button>
                        <button class="btn-confirm">Confirm</button>
                    </div>
                </div>
            `;

            document.body.appendChild(dialog);
            setTimeout(() => dialog.classList.add('show'), 10);

            dialog.querySelector('.btn-confirm').addEventListener('click', () => {
                dialog.remove();
                resolve(true);
            });

            dialog.querySelector('.btn-cancel').addEventListener('click', () => {
                dialog.remove();
                resolve(false);
            });
        });
    }
}

// Initialize advanced features
const advancedFeatures = new AdvancedFeatures();

// Export for global access
window.advancedFeatures = advancedFeatures;
