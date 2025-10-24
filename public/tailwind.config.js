module.exports = {
  content: [
    "./pages/*.{html,js}",
    "./index.html",
    "./components/**/*.{html,js}",
    "./src/**/*.{html,js}"
  ],
  theme: {
    extend: {
      colors: {
        // Primary Colors
        primary: {
          DEFAULT: "#2D7D32", // green-800
          50: "#E8F5E8", // green-50
          100: "#C8E6C9", // green-100
          200: "#A5D6A7", // green-200
          300: "#81C784", // green-300
          400: "#66BB6A", // green-400
          500: "#4CAF50", // green-500
          600: "#43A047", // green-600
          700: "#388E3C", // green-700
          800: "#2E7D32", // green-800
          900: "#1B5E20", // green-900
        },
        // Secondary Colors
        secondary: {
          DEFAULT: "#558B5F", // green-600 variant
          50: "#EDF7ED", // light green-50
          100: "#D4EDDA", // light green-100
          200: "#B8DCC0", // light green-200
          300: "#9BCBA6", // light green-300
          400: "#7EBA8C", // light green-400
          500: "#6BA572", // light green-500
          600: "#558B5F", // light green-600
          700: "#4A7A54", // light green-700
          800: "#3F6949", // light green-800
          900: "#34583E", // light green-900
        },
        // Accent Colors
        accent: {
          DEFAULT: "#FF8F00", // amber-600
          50: "#FFF8E1", // amber-50
          100: "#FFECB3", // amber-100
          200: "#FFE082", // amber-200
          300: "#FFD54F", // amber-300
          400: "#FFCA28", // amber-400
          500: "#FFC107", // amber-500
          600: "#FF8F00", // amber-600
          700: "#FF6F00", // amber-700
          800: "#E65100", // amber-800
          900: "#BF360C", // amber-900
        },
        // Background Colors
        background: "#FAFAFA", // gray-50
        surface: {
          DEFAULT: "#FFFFFF", // white
          hover: "#F5F5F5", // gray-100
          active: "#EEEEEE", // gray-200
        },
        // Text Colors
        text: {
          primary: "#1A1A1A", // gray-900
          secondary: "#666666", // gray-600
          tertiary: "#9E9E9E", // gray-500
          disabled: "#BDBDBD", // gray-400
        },
        // Status Colors
        success: {
          DEFAULT: "#4CAF50", // green-500
          50: "#E8F5E8", // green-50
          100: "#C8E6C9", // green-100
          600: "#43A047", // green-600
          700: "#388E3C", // green-700
        },
        warning: {
          DEFAULT: "#FF9800", // orange-500
          50: "#FFF3E0", // orange-50
          100: "#FFE0B2", // orange-100
          600: "#FB8C00", // orange-600
          700: "#F57C00", // orange-700
        },
        error: {
          DEFAULT: "#F44336", // red-500
          50: "#FFEBEE", // red-50
          100: "#FFCDD2", // red-100
          600: "#E53935", // red-600
          700: "#D32F2F", // red-700
        },
        // Border Colors
        border: {
          light: "#E0E0E0", // gray-300
          medium: "#CCCCCC", // gray-400
          dark: "#BDBDBD", // gray-400
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        inter: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Consolas', 'Monaco', 'monospace'],
      },
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],
        'base': ['1rem', { lineHeight: '1.5rem' }],
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
      },
      boxShadow: {
        'sm': '0 1px 2px rgba(0, 0, 0, 0.05)',
        'md': '0 2px 8px rgba(0, 0, 0, 0.1)',
        'lg': '0 4px 16px rgba(0, 0, 0, 0.15)',
        'xl': '0 8px 32px rgba(0, 0, 0, 0.2)',
      },
      transitionDuration: {
        '200': '200ms',
        '250': '250ms',
        '300': '300ms',
      },
      transitionTimingFunction: {
        'ease-out': 'ease-out',
        'smooth': 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
      animation: {
        'fade-in': 'fadeIn 250ms ease-out',
        'slide-up': 'slideUp 300ms cubic-bezier(0.4, 0, 0.2, 1)',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
        '128': '32rem',
      },
      borderRadius: {
        'xl': '0.75rem',
        '2xl': '1rem',
        '3xl': '1.5rem',
      },
    },
  },
  plugins: [
    function({ addUtilities }) {
      const newUtilities = {
        '.text-balance': {
          'text-wrap': 'balance',
        },
        '.animation-fade-in': {
          'animation': 'fadeIn 250ms ease-out',
        },
        '.animation-slide-up': {
          'animation': 'slideUp 300ms cubic-bezier(0.4, 0, 0.2, 1)',
        },
      }
      addUtilities(newUtilities)
    }
  ],
}