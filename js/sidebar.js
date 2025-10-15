// Sidebar functionality for DermaSculpt Admin
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar state
    initializeSidebar();
    
    // Handle window resize
    window.addEventListener('resize', handleWindowResize);
});

function initializeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    // Set initial state based on screen size
    if (window.innerWidth < 1024) {
        // Mobile: hide sidebar by default
        if (sidebar) {
            sidebar.classList.add('-translate-x-full');
        }
        if (overlay) {
            overlay.classList.add('hidden');
        }
    } else {
        // Desktop: show sidebar by default
        if (sidebar) {
            sidebar.classList.remove('-translate-x-full', 'collapsed');
        }
        if (overlay) {
            overlay.classList.add('hidden');
        }
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (!sidebar) return;
    
    if (window.innerWidth >= 1024) {
        // Desktop: toggle collapsed state
        sidebar.classList.toggle('collapsed');
    } else {
        // Mobile: toggle visibility with overlay
        sidebar.classList.toggle('-translate-x-full');
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (!sidebar) return;
    
    if (window.innerWidth < 1024) {
        sidebar.classList.add('-translate-x-full');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    }
}

function handleWindowResize() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (!sidebar) return;
    
    if (window.innerWidth >= 1024) {
        // Desktop: remove mobile classes
        sidebar.classList.remove('-translate-x-full');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    } else {
        // Mobile: remove desktop collapsed class and hide sidebar
        sidebar.classList.remove('collapsed');
        sidebar.classList.add('-translate-x-full');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    }
}

// Dropdown functionality
function toggleDropdown() {
    const dropdown = document.getElementById('user-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('user-dropdown');
    const button = event.target.closest('button');
    
    if (dropdown && (!button || !button.onclick || button.onclick.toString().indexOf('toggleDropdown') === -1)) {
        dropdown.classList.add('hidden');
    }
});

// Close sidebar when clicking overlay
document.addEventListener('click', function(event) {
    const overlay = document.getElementById('sidebar-overlay');
    if (overlay && event.target === overlay) {
        closeSidebar();
    }
});

// Export functions for global access
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.toggleDropdown = toggleDropdown;
