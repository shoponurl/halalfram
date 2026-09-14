/**
 * Tailwind v3 with the Halal Brothers brand tokens, carried over unchanged from the original design.
 * (Filament ships its own precompiled stylesheet, so the admin panel doesn't depend on this file.)
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                brand:   { 50: '#FFF3EE', 100: '#FFE2D6', 200: '#FFC2AB', 500: '#EC4A24', 600: '#D63A1E', 700: '#B02914', 800: '#C8321A', 900: '#7C1F0E' },
                bone:    { 50: '#FFFCF9', 100: '#FFF8F2', 200: '#FBEDE2', 300: '#F2DDCC', 400: '#DCC3AE' },
                ink:     { 500: '#5B6168', 600: '#41464C', 700: '#2A2E33', 900: '#16181B' },
                halal:   { 50: '#EEF6F0', 100: '#D6EADC', 500: '#2F7A45', 600: '#256238', 700: '#1E4F2E' },
                saffron: { 300: '#FFCB7A', 400: '#F7A234', 500: '#E8891A', 600: '#B25F0A' },
            },
            fontFamily: {
                display: ['Zilla Slab', 'Rockwell', 'Georgia', 'serif'],
                brand:   ['Rye', 'Georgia', 'serif'],
                sans:    ['Manrope', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 2px rgba(22,24,27,.04), 0 8px 24px -12px rgba(22,24,27,.12)',
                lift: '0 2px 4px rgba(22,24,27,.05), 0 24px 48px -20px rgba(200,50,26,.35)',
            },
        },
    },
};
