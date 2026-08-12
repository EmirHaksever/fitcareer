/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#059669',
          50: '#ecfdf5',
          100: '#d1fae5',
          600: '#059669',
          700: '#047857',
          800: '#065f46',
        },
        secondary: {
          DEFAULT: '#0F766E',
          700: '#0f766e',
          800: '#115e59',
        },
        supporting: '#2563EB',
        background: '#F5F7F8',
        surface: '#E2E8F0',
        success: '#D1FAE5',
        warning: '#F59E0B',
        danger: '#DC2626',
        ink: {
          DEFAULT: '#0F172A',
          muted: '#64748B',
          subtle: '#94A3B8',
        },
      },
      fontFamily: {
        sans: ['Inter', 'Segoe UI', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.04)',
      },
      borderRadius: {
        xl: '0.875rem',
      },
    },
  },
  plugins: [],
};
