/**
 * Smart Cycle Rental Management System
 * Vanilla JavaScript Frontend Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------------------------------------
    // 1. Mobile Navigation Toggle
    // ------------------------------------------------------------------
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // ------------------------------------------------------------------
    // 2. Dropdown Menus for Touch & Click Devices
    // ------------------------------------------------------------------
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const parent = toggle.closest('.dropdown');
                if (parent) {
                    parent.classList.toggle('show');
                }
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown') && !e.target.closest('.nav-toggle')) {
            document.querySelectorAll('.dropdown.show').forEach(d => d.classList.remove('show'));
        }
    });

    // ------------------------------------------------------------------
    // 3. Auto-Dismiss Flash Alerts (5 seconds)
    // ------------------------------------------------------------------
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 6000);
    });

    // ------------------------------------------------------------------
    // 4. Modal Backdrop Close
    // ------------------------------------------------------------------
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // ------------------------------------------------------------------
    // 5. Client-Side Form Enhancements
    // ------------------------------------------------------------------
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.hasAttribute('formaction')) {
                // Submit button indicator
                submitBtn.setAttribute('data-original-text', submitBtn.innerText);
            }
        });
    });
});
