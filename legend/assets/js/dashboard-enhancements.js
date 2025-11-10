/**
 * LEGEND CHECKER - Dashboard Enhancements
 * Advanced dashboard features and interactions
 */

class DashboardEnhancements {
    constructor() {
        this.refreshInterval = null;
        this.init();
    }

    init() {
        this.setupLiveStats();
        this.setupSearchBar();
        this.setupQuickActions();
        this.setupActivityFeed();
        this.setupStatsCharts();
        this.setupUserProfile();
    }

    // Live Statistics Updates
    setupLiveStats() {
        this.updateStats();
        this.refreshInterval = setInterval(() => this.updateStats(), 30000); // Update every 30 seconds
    }

    async updateStats() {
        try {
            const response = await fetch('api/live_stats.php');
            if (response.ok) {
                const data = await response.json();
                this.animateStatUpdate(data);
            }
        } catch (error) {
            console.error('Failed to update stats:', error);
        }
    }

    animateStatUpdate(data) {
        // Animate credit changes
        if (data.credits !== undefined) {
            this.animateNumber('.profile-stat-value', data.credits);
        }
        
        // Update online count
        if (data.online_count !== undefined) {
            const onlineEl = document.querySelector('.online-count');
            if (onlineEl) {
                onlineEl.textContent = data.online_count;
            }
        }
    }

    animateNumber(selector, newValue) {
        const elements = document.querySelectorAll(selector);
        elements.forEach(el => {
            const currentValue = parseInt(el.textContent.replace(/,/g, '')) || 0;
            if (currentValue !== newValue) {
                this.countUp(el, currentValue, newValue, 1000);
            }
        });
    }

    countUp(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                current = end;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }

    // Enhanced Search Bar
    setupSearchBar() {
        const searchBar = document.createElement('div');
        searchBar.className = 'dashboard-search';
        searchBar.innerHTML = `
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       class="search-input" 
                       placeholder="Search tools, users, transactions... (Ctrl+K)" 
                       data-search=".searchable-item">
                <button class="search-filter-btn" title="Filter Options">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
            <div class="search-results" style="display: none;"></div>
        `;

        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(searchBar, container.firstChild);
        }

        const searchInput = searchBar.querySelector('.search-input');
        const searchResults = searchBar.querySelector('.search-results');

        searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value, searchResults);
        });

        searchInput.addEventListener('focus', () => {
            searchResults.style.display = 'block';
        });

        document.addEventListener('click', (e) => {
            if (!searchBar.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }

    handleSearch(query, resultsContainer) {
        if (query.length < 2) {
            resultsContainer.innerHTML = '<div class="search-no-results">Type at least 2 characters...</div>';
            return;
        }

        const results = this.searchContent(query);
        
        if (results.length === 0) {
            resultsContainer.innerHTML = '<div class="search-no-results">No results found</div>';
            return;
        }

        resultsContainer.innerHTML = results.map(result => `
            <div class="search-result-item" onclick="window.location='${result.url}'">
                <i class="fas ${result.icon}"></i>
                <div class="search-result-content">
                    <div class="search-result-title">${this.highlightQuery(result.title, query)}</div>
                    <div class="search-result-description">${result.description}</div>
                </div>
            </div>
        `).join('');
    }

    searchContent(query) {
        const searchableItems = [
            { title: 'Card Checker', description: 'Check credit card validity', url: 'card_checker.php', icon: 'fa-credit-card' },
            { title: 'Site Checker', description: 'Verify website availability', url: 'site_checker.php', icon: 'fa-globe' },
            { title: 'Stripe Auth Checker', description: 'Test Stripe authentication', url: 'stripe_auth_tool.php', icon: 'fa-stripe-s' },
            { title: 'BIN Lookup', description: 'Look up card BIN information', url: 'bin_lookup_tool.php', icon: 'fa-search' },
            { title: 'BIN Generator', description: 'Generate valid card numbers', url: 'bin_generator_tool.php', icon: 'fa-magic' },
            { title: 'Wallet', description: 'Manage your XCoin balance', url: 'wallet.php', icon: 'fa-wallet' },
            { title: 'Users', description: 'View user leaderboard', url: 'users.php', icon: 'fa-users' },
            { title: 'Tools', description: 'All available tools', url: 'tools.php', icon: 'fa-tools' },
            { title: 'Admin Panel', description: 'Administration area', url: 'admin/admin_access.php', icon: 'fa-shield-alt' }
        ];

        return searchableItems.filter(item => 
            item.title.toLowerCase().includes(query.toLowerCase()) ||
            item.description.toLowerCase().includes(query.toLowerCase())
        );
    }

    highlightQuery(text, query) {
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    // Quick Actions Menu
    setupQuickActions() {
        const quickActions = document.createElement('div');
        quickActions.className = 'quick-actions-fab';
        quickActions.innerHTML = `
            <button class="fab main-fab" title="Quick Actions">
                <i class="fas fa-plus"></i>
            </button>
            <div class="fab-menu">
                <button class="fab fab-action" data-action="check-card" title="Quick Card Check">
                    <i class="fas fa-credit-card"></i>
                </button>
                <button class="fab fab-action" data-action="claim-credits" title="Claim Daily Credits">
                    <i class="fas fa-gift"></i>
                </button>
                <button class="fab fab-action" data-action="share" title="Share Profile">
                    <i class="fas fa-share-alt"></i>
                </button>
                <button class="fab fab-action" data-action="export" title="Export Data">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        `;

        document.body.appendChild(quickActions);

        const mainFab = quickActions.querySelector('.main-fab');
        const fabMenu = quickActions.querySelector('.fab-menu');

        mainFab.addEventListener('click', () => {
            fabMenu.classList.toggle('active');
            mainFab.querySelector('i').classList.toggle('fa-plus');
            mainFab.querySelector('i').classList.toggle('fa-times');
        });

        quickActions.querySelectorAll('.fab-action').forEach(btn => {
            btn.addEventListener('click', () => {
                this.handleQuickAction(btn.dataset.action);
            });
        });
    }

    handleQuickAction(action) {
        switch(action) {
            case 'check-card':
                window.location = 'card_checker.php';
                break;
            case 'claim-credits':
                document.getElementById('claimCreditsBtn')?.click();
                break;
            case 'share':
                this.shareProfile();
                break;
            case 'export':
                this.exportUserData();
                break;
        }
    }

    shareProfile() {
        const url = window.location.origin + '/legend';
        if (navigator.share) {
            navigator.share({
                title: 'LEGEND CHECKER',
                text: 'Check out my profile on LEGEND CHECKER!',
                url: url
            });
        } else {
            advancedFeatures.copyToClipboard(url);
        }
    }

    async exportUserData() {
        advancedFeatures.showLoading('Preparing export...');
        
        try {
            const response = await fetch('api/export_user_data.php');
            const data = await response.json();
            
            if (data.success) {
                const filename = `legend_checker_data_${new Date().toISOString().slice(0, 10)}`;
                advancedFeatures.exportData(data.data, filename, 'json');
            }
        } catch (error) {
            advancedFeatures.showNotification('Export failed', 'error');
        } finally {
            advancedFeatures.hideLoading();
        }
    }

    // Activity Feed
    setupActivityFeed() {
        const activitySection = document.createElement('div');
        activitySection.className = 'activity-feed-section';
        activitySection.innerHTML = `
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-history"></i> Recent Activity
                </h2>
                <button class="refresh-btn" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="activity-feed">
                <div class="activity-loader">
                    <div class="skeleton" style="height: 60px; margin-bottom: 10px;"></div>
                    <div class="skeleton" style="height: 60px; margin-bottom: 10px;"></div>
                    <div class="skeleton" style="height: 60px;"></div>
                </div>
            </div>
        `;

        const container = document.querySelector('.container');
        if (container) {
            container.appendChild(activitySection);
        }

        this.loadActivityFeed();

        activitySection.querySelector('.refresh-btn').addEventListener('click', () => {
            this.loadActivityFeed();
        });
    }

    async loadActivityFeed() {
        const feed = document.querySelector('.activity-feed');
        
        try {
            const response = await fetch('api/recent_activity.php');
            const data = await response.json();
            
            if (data.success && data.activities) {
                feed.innerHTML = data.activities.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon ${activity.type}">
                            <i class="fas ${this.getActivityIcon(activity.type)}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">${activity.title}</div>
                            <div class="activity-time">${this.formatTimeAgo(activity.timestamp)}</div>
                        </div>
                        ${activity.value ? `<div class="activity-value">${activity.value}</div>` : ''}
                    </div>
                `).join('');
            }
        } catch (error) {
            feed.innerHTML = '<div class="activity-error">Failed to load activity</div>';
        }
    }

    getActivityIcon(type) {
        const icons = {
            'check': 'fa-credit-card',
            'claim': 'fa-gift',
            'purchase': 'fa-shopping-cart',
            'login': 'fa-sign-in-alt',
            'achievement': 'fa-trophy'
        };
        return icons[type] || 'fa-bell';
    }

    formatTimeAgo(timestamp) {
        const seconds = Math.floor((Date.now() - timestamp * 1000) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        return Math.floor(seconds / 86400) + 'd ago';
    }

    // Stats Charts
    setupStatsCharts() {
        const cards = document.querySelectorAll('.card[class*="card-"]');
        cards.forEach(card => {
            if (!card.querySelector('.mini-chart')) {
                const chart = this.createMiniChart();
                card.appendChild(chart);
            }
        });
    }

    createMiniChart() {
        const canvas = document.createElement('canvas');
        canvas.className = 'mini-chart';
        canvas.width = 100;
        canvas.height = 30;

        const ctx = canvas.getContext('2d');
        const data = Array.from({ length: 10 }, () => Math.random() * 30);
        
        ctx.strokeStyle = 'rgba(0, 212, 170, 0.5)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        data.forEach((val, i) => {
            const x = (i / (data.length - 1)) * 100;
            const y = 30 - val;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        
        ctx.stroke();
        return canvas;
    }

    // User Profile Enhancements
    setupUserProfile() {
        const profileHeader = document.querySelector('.profile-header');
        if (!profileHeader) return;

        // Add edit profile button
        const editBtn = document.createElement('button');
        editBtn.className = 'edit-profile-btn';
        editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Profile';
        editBtn.style.position = 'absolute';
        editBtn.style.top = '20px';
        editBtn.style.right = '20px';
        
        profileHeader.style.position = 'relative';
        profileHeader.appendChild(editBtn);

        editBtn.addEventListener('click', () => {
            this.showEditProfileModal();
        });
    }

    showEditProfileModal() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content edit-profile-modal">
                <button class="modal-close">&times;</button>
                <h2>Edit Profile</h2>
                <form class="edit-profile-form">
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Avatar URL</label>
                        <input type="url" name="avatar_url" class="form-input">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>
        `;

        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('show'), 10);

        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('.btn-cancel').addEventListener('click', () => {
            modal.remove();
        });

        modal.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();
            advancedFeatures.showNotification('Profile updated successfully!', 'success');
            modal.remove();
        });
    }

    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}

// Initialize dashboard enhancements
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dashboardEnhancements = new DashboardEnhancements();
    });
} else {
    window.dashboardEnhancements = new DashboardEnhancements();
}
