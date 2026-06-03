import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** Bajaj royal blue — primary brand color from logo (#005EB8) */
const brand = {
    DEFAULT: '#005EB8',
    hover: '#004E99',
    dark: '#003D7A',
    light: '#3379C6',
    soft: '#E6F0FA',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                primary: brand,
                brand,
                sidebar: {
                    DEFAULT: '#FFFFFF',
                    hover: '#F1F5F9',
                    active: '#E6F0FA',
                    border: '#E2E8F0',
                    muted: '#64748B',
                },
                surface: {
                    DEFAULT: '#FFFFFF',
                    muted: '#F8FAFC',
                    border: '#E2E8F0',
                },
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                card: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 4px 16px -2px rgb(15 23 42 / 0.08)',
                'card-lg': '0 4px 24px -4px rgb(15 23 42 / 0.12), 0 8px 32px -8px rgb(0 94 184 / 0.12)',
            },
        },
    },

    plugins: [forms],
};
