/**
 * LEGEND CHECKER - Security Enhancements
 * Advanced security features and protections
 */

class SecurityEnhancements {
    constructor() {
        this.csrfToken = null;
        this.requestQueue = new Map();
        this.rateLimits = new Map();
        this.init();
    }

    init() {
        this.setupCSRFProtection();
        this.setupXSSProtection();
        this.setupRateLimiting();
        this.setupSecureStorage();
        this.setupInputSanitization();
        this.monitorSuspiciousActivity();
    }

    // CSRF Protection
    setupCSRFProtection() {
        // Generate CSRF token
        this.csrfToken = this.generateToken();
        sessionStorage.setItem('csrf_token', this.csrfToken);

        // Add CSRF token to all POST requests
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            if (args[1] && args[1].method && args[1].method.toUpperCase() === 'POST') {
                args[1].headers = args[1].headers || {};
                args[1].headers['X-CSRF-Token'] = this.csrfToken;
            }
            return originalFetch.apply(window, args);
        };
    }

    generateToken() {
        const array = new Uint8Array(32);
        crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    // XSS Protection
    setupXSSProtection() {
        // Sanitize all user inputs
        document.addEventListener('input', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                if (e.target.dataset.sanitize !== 'false') {
                    e.target.value = this.sanitizeInput(e.target.value);
                }
            }
        });

        // Prevent inline script execution
        this.preventInlineScripts();
    }

    sanitizeInput(input) {
        if (typeof input !== 'string') return input;
        
        // Remove potentially dangerous characters
        return input
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;')
            .replace(/\//g, '&#x2F;');
    }

    preventInlineScripts() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.tagName === 'SCRIPT' && !node.getAttribute('nonce')) {
                        node.remove();
                        console.warn('Blocked unauthorized script injection');
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Rate Limiting
    setupRateLimiting() {
        this.rateLimitConfig = {
            'api/claim_credits.php': { limit: 1, window: 86400000 }, // 1 per day
            'check_card_ajax.php': { limit: 100, window: 60000 },    // 100 per minute
            'api/presence.php': { limit: 30, window: 60000 }         // 30 per minute
        };
    }

    checkRateLimit(endpoint) {
        const config = this.rateLimitConfig[endpoint];
        if (!config) return true;

        const now = Date.now();
        const key = endpoint;
        
        if (!this.rateLimits.has(key)) {
            this.rateLimits.set(key, []);
        }

        const requests = this.rateLimits.get(key);
        const validRequests = requests.filter(time => now - time < config.window);
        
        if (validRequests.length >= config.limit) {
            advancedFeatures.showNotification(
                'Rate limit exceeded. Please slow down.',
                'warning'
            );
            return false;
        }

        validRequests.push(now);
        this.rateLimits.set(key, validRequests);
        return true;
    }

    // Secure Storage
    setupSecureStorage() {
        // Encrypt sensitive data before storing
        this.storage = {
            set: (key, value) => {
                const encrypted = this.encryptData(JSON.stringify(value));
                localStorage.setItem(key, encrypted);
            },
            get: (key) => {
                const encrypted = localStorage.getItem(key);
                if (!encrypted) return null;
                try {
                    return JSON.parse(this.decryptData(encrypted));
                } catch {
                    return null;
                }
            },
            remove: (key) => {
                localStorage.removeItem(key);
            }
        };
    }

    encryptData(data) {
        // Simple XOR encryption (for demonstration - use proper encryption in production)
        const key = this.getEncryptionKey();
        let encrypted = '';
        for (let i = 0; i < data.length; i++) {
            encrypted += String.fromCharCode(data.charCodeAt(i) ^ key.charCodeAt(i % key.length));
        }
        return btoa(encrypted);
    }

    decryptData(encrypted) {
        const key = this.getEncryptionKey();
        const data = atob(encrypted);
        let decrypted = '';
        for (let i = 0; i < data.length; i++) {
            decrypted += String.fromCharCode(data.charCodeAt(i) ^ key.charCodeAt(i % key.length));
        }
        return decrypted;
    }

    getEncryptionKey() {
        // Generate or retrieve encryption key
        let key = sessionStorage.getItem('encryption_key');
        if (!key) {
            key = this.generateToken();
            sessionStorage.setItem('encryption_key', key);
        }
        return key;
    }

    // Input Sanitization
    setupInputSanitization() {
        // Add validators for common input types
        this.validators = {
            email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
            url: (value) => {
                try {
                    new URL(value);
                    return true;
                } catch {
                    return false;
                }
            },
            card: (value) => /^\d{4}\|\d{2}\|\d{4}\|\d{3}$/.test(value),
            number: (value) => /^\d+$/.test(value),
            alphanumeric: (value) => /^[a-zA-Z0-9]+$/.test(value)
        };
    }

    validate(value, type) {
        const validator = this.validators[type];
        return validator ? validator(value) : true;
    }

    // Monitor Suspicious Activity
    monitorSuspiciousActivity() {
        let clickCount = 0;
        let clickTimer = null;

        document.addEventListener('click', () => {
            clickCount++;
            
            if (clickTimer) clearTimeout(clickTimer);
            
            clickTimer = setTimeout(() => {
                if (clickCount > 50) {
                    console.warn('Suspicious activity detected: Rapid clicking');
                    this.reportSuspiciousActivity('rapid_clicking');
                }
                clickCount = 0;
            }, 1000);
        });

        // Detect console tampering
        const originalConsole = console.log;
        console.log = function(...args) {
            originalConsole.apply(console, args);
        };

        // Detect DevTools
        let devtoolsOpen = false;
        const detectDevTools = () => {
            const threshold = 160;
            const widthDiff = window.outerWidth - window.innerWidth > threshold;
            const heightDiff = window.outerHeight - window.innerHeight > threshold;
            
            if ((widthDiff || heightDiff) && !devtoolsOpen) {
                devtoolsOpen = true;
                console.log('Developer tools detected');
            } else if (!widthDiff && !heightDiff && devtoolsOpen) {
                devtoolsOpen = false;
            }
        };

        setInterval(detectDevTools, 1000);
    }

    reportSuspiciousActivity(type) {
        // Report to server
        fetch('api/report_suspicious_activity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type, timestamp: Date.now() })
        }).catch(() => {}); // Silently fail
    }

    // Password Strength Checker
    checkPasswordStrength(password) {
        let strength = 0;
        const feedback = [];

        if (password.length >= 8) strength++;
        else feedback.push('Use at least 8 characters');

        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        else feedback.push('Use both lowercase and uppercase letters');

        if (/\d/.test(password)) strength++;
        else feedback.push('Include at least one number');

        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        else feedback.push('Include at least one special character');

        const levels = ['Weak', 'Fair', 'Good', 'Strong'];
        return {
            strength: strength,
            level: levels[strength],
            feedback: feedback
        };
    }

    // Secure Form Submission
    secureSubmit(form, endpoint) {
        return new Promise(async (resolve, reject) => {
            // Check rate limit
            if (!this.checkRateLimit(endpoint)) {
                reject(new Error('Rate limit exceeded'));
                return;
            }

            // Validate form data
            const formData = new FormData(form);
            const data = {};
            let isValid = true;

            for (let [key, value] of formData.entries()) {
                const input = form.querySelector(`[name="${key}"]`);
                const validator = input?.dataset.validate;
                
                if (validator && !this.validate(value, validator)) {
                    isValid = false;
                    advancedFeatures.showNotification(
                        `Invalid ${key}: ${value}`,
                        'error'
                    );
                    break;
                }
                
                data[key] = value;
            }

            if (!isValid) {
                reject(new Error('Validation failed'));
                return;
            }

            // Submit securely
            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                resolve(result);
            } catch (error) {
                reject(error);
            }
        });
    }

    // Prevent Clickjacking
    preventClickjacking() {
        if (window.top !== window.self) {
            window.top.location = window.self.location;
        }
    }

    // Session Timeout
    setupSessionTimeout(minutes = 30) {
        let timeoutId;
        
        const resetTimer = () => {
            if (timeoutId) clearTimeout(timeoutId);
            
            timeoutId = setTimeout(() => {
                advancedFeatures.showNotification(
                    'Session expired. Please log in again.',
                    'warning'
                );
                setTimeout(() => {
                    window.location = 'logout.php';
                }, 3000);
            }, minutes * 60 * 1000);
        };

        // Reset on user activity
        ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        resetTimer();
    }
}

// Initialize security enhancements
const securityEnhancements = new SecurityEnhancements();
window.securityEnhancements = securityEnhancements;
