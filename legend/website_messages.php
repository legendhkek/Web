<?php
/**
 * Website Messages Component
 * This file displays active broadcast messages to users
 * Include this in your main pages to show website messages
 */

function getWebsiteMessagesForUsers() {
    try {
        $messages_file = 'data/website_messages.json';
        
        if (!file_exists($messages_file)) {
            return [];
        }
        
        $messages = json_decode(file_get_contents($messages_file), true) ?: [];
        
        // Filter active messages and sort by priority
        $active_messages = array_filter($messages, function($msg) {
            return $msg['status'] === 'active' && $msg['expires_at'] > time();
        });
        
        // Sort by priority (high, normal, low)
        $priority_order = ['high' => 3, 'normal' => 2, 'low' => 1];
        usort($active_messages, function($a, $b) use ($priority_order) {
            $a_priority = $priority_order[$a['priority']] ?? 1;
            $b_priority = $priority_order[$b['priority']] ?? 1;
            return $b_priority - $a_priority;
        });
        
        return $active_messages;
        
    } catch (Exception $e) {
        return [];
    }
}

function renderWebsiteMessages() {
    $messages = getWebsiteMessagesForUsers();
    if (empty($messages)) return '';

    $html = '<div class="website-messages-container">';
    foreach ($messages as $msg) {
        $priority_class = getPriorityClass($msg['priority']);
        $priority_icon = getPriorityIcon($msg['priority']);

        $html .= '
        <div class="wm-card wm-' . $priority_class . '">
            <div class="wm-icon"><i class="fas ' . $priority_icon . '"></i></div>
            <div class="wm-content">
                <div class="wm-header">
                    <h6><i class="fas fa-bullhorn"></i> Website Announcement</h6>
                    <small>' . date('M j, g:i A', $msg['created_at']) . '</small>
                </div>
                <p>' . htmlspecialchars($msg['message']) . '</p>
                <div class="wm-footer">
                    <small><code>' . $msg['message_id'] . '</code></small>
                    <span class="wm-badge wm-' . $priority_class . '">' . ucfirst($msg['priority']) . '</span>
                </div>
            </div>
            <button class="wm-close" aria-label="Close">&times;</button>
        </div>';
    }
    $html .= '</div>';
    return $html;
}

function getPriorityClass($priority) {
    switch ($priority) {
        case 'high': return 'danger';
        case 'normal': return 'primary';
        case 'low': return 'info';
        default: return 'secondary';
    }
}

function getPriorityIcon($priority) {
    switch ($priority) {
        case 'high': return 'fa-exclamation-triangle';
        case 'normal': return 'fa-info-circle';
        case 'low': return 'fa-info-circle';
        default: return 'fa-info-circle';
    }
}

// Add CSS for website messages
function getWebsiteMessagesCSS() {
    return '
    <style>
    .website-messages-container { position: relative; display: grid; gap: 12px; }
    .wm-card { display: flex; gap: 12px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-card); box-shadow: 0 8px 24px rgba(0,0,0,0.1); position: relative; }
    .wm-icon { width: 40px; height: 40px; border-radius: 10px; display:flex; align-items:center; justify-content:center; color:#fff; }
    .wm-danger .wm-icon { background: #ef4444; }
    .wm-primary .wm-icon { background: #3b82f6; }
    .wm-info .wm-icon { background: #06b6d4; }
    .wm-secondary .wm-icon { background: #6b7280; }
    .wm-content { flex:1; }
    .wm-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; }
    .wm-header h6 { margin:0; font-size:14px; color: var(--text-primary); }
    .wm-header small { color: var(--text-secondary); }
    .wm-content p { margin:6px 0 8px; color: var(--text-primary); }
    .wm-footer { display:flex; align-items:center; justify-content:space-between; }
    .wm-footer code { background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 6px; font-size: 12px; color: var(--text-secondary); }
    .wm-badge { font-size: 11px; padding: 4px 8px; border-radius: 8px; }
    .wm-badge.wm-danger { background: rgba(239,68,68,0.15); color: #fca5a5; }
    .wm-badge.wm-primary { background: rgba(59,130,246,0.15); color: #93c5fd; }
    .wm-badge.wm-info { background: rgba(6,182,212,0.15); color: #67e8f9; }
    .wm-badge.wm-secondary { background: rgba(107,114,128,0.15); color: #cbd5e1; }
    .wm-close { position:absolute; top:8px; right:10px; background:transparent; border:none; color: var(--text-secondary); font-size:18px; cursor:pointer; }
    </style>';
}

// Add JavaScript for website messages
function getWebsiteMessagesJS() {
    return <<<HTML
    <script>
    // Store dismissed messages
    document.addEventListener("click", function(e){
        if(e.target.classList.contains("wm-close")){
            const card = e.target.closest('.wm-card');
            const idEl = card.querySelector('code');
            const messageId = idEl ? idEl.textContent : null;
            if (messageId){
                const dismissed = JSON.parse(localStorage.getItem('dismissedMessages') || '[]');
                if (!dismissed.includes(messageId)){
                    dismissed.push(messageId);
                    localStorage.setItem('dismissedMessages', JSON.stringify(dismissed));
                }
            }
            card.style.display = 'none';
        }
    });

    // Hide messages already dismissed
    document.addEventListener('DOMContentLoaded', function(){
        const dismissed = JSON.parse(localStorage.getItem('dismissedMessages') || '[]');
        dismissed.forEach(id => {
            document.querySelectorAll('.wm-card code').forEach(el => {
                if (el.textContent === id){
                    const card = el.closest('.wm-card');
                    if (card){ card.style.display = 'none'; }
                }
            });
        });
    });
    </script>
HTML;
}

// Function to include website messages in any page
function includeWebsiteMessages() {
    echo getWebsiteMessagesCSS();
    echo renderWebsiteMessages();
    echo getWebsiteMessagesJS();
}

// Function to get messages as array (for custom rendering)
function getWebsiteMessagesArray() {
    return getWebsiteMessagesForUsers();
}

// Function to check if there are active messages
function hasActiveWebsiteMessages() {
    $messages = getWebsiteMessagesForUsers();
    return !empty($messages);
}
?>
