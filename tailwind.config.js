import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // FIZJOroom colours (logo and website). "indigo" is remapped to the
            // brand blue so every existing link and accent follows the brand.
            colors: {
                indigo: {
                    50: '#e6f6fd',
                    100: '#ccedfb',
                    200: '#99dbf7',
                    300: '#66c9f2',
                    400: '#33b6ee',
                    500: '#00a2e6',
                    600: '#008fcc',
                    700: '#0079ad',
                    800: '#00628c',
                    900: '#004a6a',
                    950: '#00334a',
                },
                brand: {
                    50: '#fde7f2',
                    100: '#fbcfe5',
                    200: '#f79fcb',
                    500: '#e5097f',
                    600: '#c4066b',
                    700: '#a10558',
                    ink: '#16232e',
                    paper: '#f6f3ee',
                },
            },
        },
    },

    plugins: [forms],
};
