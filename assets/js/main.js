/**
 * Main JavaScript file
 * Global functions and utilities
 */

/**
 * Toggle submenu open/close
 * @param {Event} event - Click event
 * @param {HTMLElement} element - The clicked anchor element
 */
function toggleSubmenu(event, element) {
    // Prevent default link behavior
    event.preventDefault();
    event.stopPropagation();

    // Get parent li element
    const parentLi = element.closest('.has-submenu');

    if (!parentLi) {
        console.error('Parent .has-submenu not found');
        return;
    }

    // Check current state
    const isOpen = parentLi.classList.contains('submenu-open');

    // Close all other submenus first (accordion behavior)
    document.querySelectorAll('.has-submenu.submenu-open').forEach(item => {
        if (item !== parentLi) {
            item.classList.remove('submenu-open');
        }
    });

    // Toggle current submenu
    if (isOpen) {
        parentLi.classList.remove('submenu-open');
    } else {
        parentLi.classList.add('submenu-open');
    }
}

/**
 * Initialize submenu state on page load
 * Auto-open submenu if a child item is active
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if any submenu item is active
    const activeSubmenuItem = document.querySelector('.submenu .submenu-item.active');

    if (activeSubmenuItem) {
        // Find parent has-submenu and open it
        const parentSubmenu = activeSubmenuItem.closest('.has-submenu');
        if (parentSubmenu) {
            parentSubmenu.classList.add('submenu-open');
        }
    }
});

/**
 * Show notification message
 * @param {string} message - Message to display
 * @param {string} type - Type of notification (success, error, warning, info)
 */
function showNotification(message, type = 'info') {
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        max-width: 400px;
        font-size: 14px;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;
document.head.appendChild(style);
