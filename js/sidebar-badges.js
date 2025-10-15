// Shared sidebar badge update system for all pages
let sidebarBadgeInterval;

// Update sidebar badges function
function updateSidebarBadges(badgeData) {
    if (!badgeData) return;
    
    // Update appointment badge
    const appointmentBadge = document.getElementById('appointmentBadge');
    if (appointmentBadge) {
        appointmentBadge.textContent = badgeData.pending_appointments;
        if (badgeData.pending_appointments > 0) {
            appointmentBadge.classList.remove('hidden');
            appointmentBadge.classList.add('flex');
        } else {
            appointmentBadge.classList.add('hidden');
            appointmentBadge.classList.remove('flex');
        }
    }
    
    // Update message badge
    const messageBadge = document.getElementById('messageBadge');
    if (messageBadge) {
        messageBadge.textContent = badgeData.unread_messages;
        if (badgeData.unread_messages > 0) {
            messageBadge.classList.remove('hidden');
            messageBadge.classList.add('flex');
        } else {
            messageBadge.classList.add('hidden');
            messageBadge.classList.remove('flex');
        }
    }
}

// Check for badge updates
async function checkSidebarBadgeUpdates() {
    try {
        // Determine the correct path based on current location
        const currentPath = window.location.pathname;
        const isInPagesFolder = currentPath.includes('/pages/');
        const backendPath = isInPagesFolder ? '../backend/get_badge_counts.php' : 'backend/get_badge_counts.php';
        
        const response = await fetch(backendPath);
        const data = await response.json();
        
        if (data.error) return;
        
        updateSidebarBadges(data);
    } catch (error) {
        // Silently handle badge update errors
    }
}

// Start badge updates (only if not on appointments page which has its own system)
function startSidebarBadgeUpdates() {
    // Don't start if we're on appointments page (it has its own integrated system)
    if (window.location.pathname.includes('appointments.php')) {
        return;
    }
    
    // Update badges every 3 seconds
    sidebarBadgeInterval = setInterval(checkSidebarBadgeUpdates, 3000);
    
    // Also do an immediate check
    checkSidebarBadgeUpdates();
}

function stopSidebarBadgeUpdates() {
    if (sidebarBadgeInterval) {
        clearInterval(sidebarBadgeInterval);
    }
}

// Auto-start when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    startSidebarBadgeUpdates();
});

// Stop updates when page is hidden/minimized to save resources
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopSidebarBadgeUpdates();
    } else {
        startSidebarBadgeUpdates();
    }
});
