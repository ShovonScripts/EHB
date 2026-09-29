import './bootstrap';

// Mobile nav toggle (DESIGN_SYSTEM.md §10 — hamburger on mobile).
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const menu = document.querySelector('[data-nav-menu]');

    if (!toggle || !menu) {
        return;
    }

    const closeMenu = (returnFocus = false) => {
        toggle.setAttribute('aria-expanded', 'false');
        menu.classList.remove('nav-open');
        menu.classList.add('hidden');

        if (returnFocus) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';

        if (expanded) {
            closeMenu();
        } else {
            toggle.setAttribute('aria-expanded', 'true');
            menu.classList.remove('hidden');
            requestAnimationFrame(() => {
                menu.classList.add('nav-open');
            });
        }
    });

    // The menu is a disclosure, not a modal: Escape still dismisses it and
    // returns focus to the toggle so keyboard users are not stranded.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || toggle.getAttribute('aria-expanded') !== 'true') {
            return;
        }

        closeMenu(true);
    });

    // A tap on a mobile link navigates; without this the panel stays open
    // (and aria-expanded stays true) when the user comes back.
    menu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            closeMenu();
        }
    });
});

// Scroll-triggered reveals (editorial, minimal — Intersection Observer).
document.addEventListener('DOMContentLoaded', () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (prefersReducedMotion) {
        return;
    }

    const revealElements = document.querySelectorAll('.reveal');

    if (!revealElements.length || !('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        {
            rootMargin: '0px 0px -40px 0px',
            threshold: 0.1,
        }
    );

    revealElements.forEach((el) => observer.observe(el));
});

// Move focus to the contact form's confirmation message after a
// POST→redirect so screen-reader users hear it land.
document.addEventListener('DOMContentLoaded', () => {
    const success = document.getElementById('form-success');

    if (success) {
        success.focus();
    }
});

// Copy-link share button (FRONTEND.md §2 — share links on detail pages).
// Prefers the async clipboard API, falls back to a hidden textarea +
// execCommand for non-secure contexts (http on a LAN, older browsers).
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-share-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.dataset.shareUrl;
            const status = button.parentElement.querySelector('[data-share-status]');
            let copied = false;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(url);
                    copied = true;
                } else {
                    const scratch = document.createElement('textarea');
                    scratch.value = url;
                    scratch.setAttribute('readonly', '');
                    scratch.style.position = 'fixed';
                    scratch.style.left = '-9999px';
                    document.body.appendChild(scratch);
                    scratch.select();
                    copied = document.execCommand('copy');
                    document.body.removeChild(scratch);
                }
            } catch (error) {
                copied = false;
            }

            if (status) {
                status.textContent = copied ? 'Link copied to clipboard.' : 'Could not copy — the link is in the address bar.';
            }
        });
    });
});
