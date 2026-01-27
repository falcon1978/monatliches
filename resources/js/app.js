import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const getPreferredTheme = () => {
    const stored = window.localStorage.getItem('theme');
    if (stored === 'dark' || stored === 'light') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
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

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            toggleTheme();
        });
    });
    applyAccentFavicon();
});
