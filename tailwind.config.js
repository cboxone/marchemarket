/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
    ],
    theme: {
        extend: {
            fontFamily: {
                display: ['"Space Grotesk"', 'sans-serif'],
                sans: ['"Inter"', 'sans-serif'],
            },
            colors: {
                // Palette "commerce de gros" — navy profond + ambre trade,
                // pensée pour un marché B2B, pas un accent terracotta générique.
                navy: {
                    50: '#eef1f6',
                    100: '#d7deea',
                    300: '#8b9ab6',
                    500: '#2a4570',
                    600: '#1d3355',
                    700: '#16273f',
                    800: '#12233d',
                    900: '#0c1830',
                },
                amber: {
                    50: '#fdf3ea',
                    100: '#fbe3cc',
                    400: '#f0985a',
                    500: '#e8792b',
                    600: '#cc611c',
                    700: '#a34d16',
                },
                trade: {
                    green: '#1f8a5f',
                    greenLight: '#e5f4ee',
                },
                ink: '#1f2937',
                paper: '#f7f8fa',
                line: '#e2e5ea',
            },
            boxShadow: {
                card: '0 1px 2px rgba(12, 24, 48, 0.06), 0 1px 3px rgba(12, 24, 48, 0.08)',
            },
        },
    },
    plugins: [],
}
