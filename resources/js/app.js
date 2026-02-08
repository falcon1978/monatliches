import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('balanceEditor', (value, display) => ({
    editing: false,
    value,
    display,
    focusInput() {
        this.editing = true;
        this.$nextTick(() => this.$refs.input?.focus());
    },
    sync() {
        this.value = (this.display || '').toString().replace(/'/g, '').replace(',', '.');
    },
    format() {
        const normalized = (this.display || '').toString().replace(/'/g, '').replace(',', '.');
        const num = parseFloat(normalized);
        if (Number.isNaN(num)) {
            return;
        }
        const fixed = num.toFixed(2);
        const parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, "'");
        this.display = parts.join('.');
        this.value = fixed;
    },
}));

const getPreferredTheme = () => {
    const stored = window.localStorage.getItem('theme');
    if (stored === 'dark' || stored === 'light') {
        return stored;
    }

    return 'dark';
};

const applyTheme = (theme) => {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        button.setAttribute('aria-label', isDark ? 'Hellmodus' : 'Dunkelmodus');
        button.setAttribute('title', isDark ? 'Hellmodus' : 'Dunkelmodus');
    });
};

const initTheme = () => {
    applyTheme(getPreferredTheme());
};

const toggleTheme = () => {
    const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
    window.localStorage.setItem('theme', nextTheme);
    applyTheme(nextTheme);
};

const applyAccentFavicon = () => {
    const accent = getComputedStyle(document.body).getPropertyValue('--accent').trim();
    if (!accent) {
        return;
    }

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="none" stroke="${accent}">` +
        `<rect x="96" y="180" width="320" height="180" rx="28" ry="28" stroke-width="20" stroke-linecap="round" stroke-linejoin="round" />` +
        `<path d="M128,180h256l-32-40h-192l-32,40Z" stroke-width="20" stroke-linecap="round" stroke-linejoin="round" />` +
        `<circle cx="380" cy="270" r="12" fill="${accent}" stroke="none" />` +
        `<path d="M200,300v-70" stroke-width="18" stroke-linecap="round" />` +
        `<path d="M180,250l20-20,20,20" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />` +
        `<path d="M280,230v70" stroke-width="18" stroke-linecap="round" />` +
        `<path d="M260,280l20,20,20-20" stroke-width="18" stroke-linecap="round" stroke-linejoin="round" />` +
        `</svg>`;

    const encoded = encodeURIComponent(svg).replace(/%20/g, ' ');
    const href = `data:image/svg+xml,${encoded}`;
    let link = document.querySelector('link[data-accent-favicon]');
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        link.setAttribute('data-accent-favicon', 'true');
        document.head.appendChild(link);
    }
    link.type = 'image/svg+xml';
    link.href = href;
};

const initTooltips = () => {
    const TOOLTIP_SELECTOR = '.help-tooltip';
    const TOOLTIP_MARGIN = 12;
    const TOOLTIP_GAP = 8;
    const TOOLTIP_MAX_WIDTH = 320;
    let activeTooltip = null;
    let bubble = null;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const getViewport = () => {
        const viewport = window.visualViewport;
        if (viewport) {
            return {
                width: viewport.width,
                left: viewport.offsetLeft,
                right: viewport.offsetLeft + viewport.width,
                top: viewport.offsetTop,
                bottom: viewport.offsetTop + viewport.height,
            };
        }
        const width = document.documentElement?.clientWidth || window.innerWidth;
        const height = document.documentElement?.clientHeight || window.innerHeight;
        return { width, left: 0, right: width, top: 0, bottom: height };
    };

    const ensureBubble = () => {
        if (bubble) {
            return bubble;
        }
        bubble = document.createElement('div');
        bubble.className = 'tooltip-bubble';
        bubble.dataset.show = 'false';
        bubble.dataset.placement = 'bottom';
        bubble.setAttribute('role', 'tooltip');
        document.body.appendChild(bubble);
        return bubble;
    };

    const positionBubble = (el) => {
        const tooltipText = el.dataset.tooltip?.trim();
        if (!tooltipText) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const viewport = getViewport();
        const maxWidth = Math.min(TOOLTIP_MAX_WIDTH, viewport.width - TOOLTIP_MARGIN * 2);
        const bubbleEl = ensureBubble();

        bubbleEl.textContent = tooltipText;
        bubbleEl.dataset.show = 'true';
        bubbleEl.dataset.placement = 'bottom';
        bubbleEl.style.maxWidth = `${maxWidth}px`;
        bubbleEl.style.width = 'auto';
        bubbleEl.style.visibility = 'hidden';

        const measuredWidth = Math.min(bubbleEl.offsetWidth, maxWidth);
        const measuredHeight = bubbleEl.offsetHeight;
        bubbleEl.style.width = `${measuredWidth}px`;
        const centerX = rect.left + rect.width / 2;
        const left = clamp(
            centerX - measuredWidth / 2,
            viewport.left + TOOLTIP_MARGIN,
            viewport.right - measuredWidth - TOOLTIP_MARGIN
        );

        let placement = 'bottom';
        let top = rect.bottom + TOOLTIP_GAP;
        if (top + measuredHeight + TOOLTIP_MARGIN > viewport.bottom) {
            const above = rect.top - TOOLTIP_GAP - measuredHeight;
            if (above >= viewport.top + TOOLTIP_MARGIN) {
                placement = 'top';
                top = above;
            } else {
                top = viewport.bottom - measuredHeight - TOOLTIP_MARGIN;
            }
        }
        if (top < viewport.top + TOOLTIP_MARGIN) {
            top = viewport.top + TOOLTIP_MARGIN;
        }

        const arrowLeft = clamp(centerX - left, 12, measuredWidth - 12);

        bubbleEl.dataset.placement = placement;
        bubbleEl.style.left = `${left}px`;
        bubbleEl.style.top = `${top}px`;
        bubbleEl.style.setProperty('--tooltip-arrow-left', `${arrowLeft}px`);
        bubbleEl.style.visibility = 'visible';
    };

    const showTooltip = (el) => {
        activeTooltip = el;
        positionBubble(el);
    };

    const hideTooltip = (el) => {
        if (activeTooltip !== el) {
            return;
        }
        activeTooltip = null;
        if (bubble) {
            bubble.dataset.show = 'false';
            bubble.dataset.placement = 'bottom';
        }
    };

    const handleMouseOver = (event) => {
        const el = event.target.closest(TOOLTIP_SELECTOR);
        if (!el) {
            return;
        }
        if (activeTooltip !== el) {
            showTooltip(el);
        }
    };

    const handleMouseOut = (event) => {
        const el = event.target.closest(TOOLTIP_SELECTOR);
        if (!el) {
            return;
        }
        if (!el.contains(event.relatedTarget)) {
            hideTooltip(el);
        }
    };

    const handleFocusIn = (event) => {
        const el = event.target.closest(TOOLTIP_SELECTOR);
        if (!el) {
            return;
        }
        showTooltip(el);
    };

    const handleFocusOut = (event) => {
        const el = event.target.closest(TOOLTIP_SELECTOR);
        if (!el) {
            return;
        }
        hideTooltip(el);
    };

    document.addEventListener('mouseover', handleMouseOver);
    document.addEventListener('mouseout', handleMouseOut);
    document.addEventListener('focusin', handleFocusIn);
    document.addEventListener('focusout', handleFocusOut);

    window.addEventListener(
        'scroll',
        () => {
            if (activeTooltip) {
                positionBubble(activeTooltip);
            }
        },
        true
    );
    window.addEventListener('resize', () => {
        if (activeTooltip) {
            positionBubble(activeTooltip);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            toggleTheme();
        });
    });
    applyAccentFavicon();
    initTooltips();

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }
        const button = event.submitter;
        if (!button) {
            return;
        }
        button.dataset.loading = 'true';
        button.disabled = true;
    });
});

Alpine.start();
