/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/**/*.php',
    './js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        'flag-black': '#0f0f0f',
        'flag-green': '#198a4e',
        'flag-red': '#c0392b',
        'flag-orange': '#e07b39',
        'lfs-green': '#4a7c59',
        'lfs-green-light': '#6aad7e',
        'lfs-green-bright': '#7ecb93',
        'lfs-dark-green': '#1e3a2a',
        'lfs-red': '#c0392b',
        'lfs-red-light': '#e05252',
        'lfs-orange': '#e07b39',
        'lfs-orange-light': '#f0a060',
        'lfs-gold': '#c9a84c',
        'lfs-off-white': '#f5f2ec',
        'lfs-warm-white': '#faf8f4',
        'lfs-muted': '#6b6b6b',
      },
      fontFamily: {
        display: ['Bebas Neue', 'sans-serif'],
        body: ['DM Sans', 'sans-serif'],
      },
      fontSize: {
        'display-xl': ['clamp(4rem, 10vw, 9rem)', { lineHeight: '0.92' }],
        'display-lg': ['clamp(3rem, 6vw, 6rem)', { lineHeight: '1' }],
        'display-md': ['clamp(1.75rem, 3vw, 2.5rem)', { lineHeight: '1.1' }],
        label: ['0.7rem', { letterSpacing: '0.15em', fontWeight: '600' }],
      },
      spacing: {
        section: 'clamp(1.5rem, 6vw, 4rem)',
      },
      borderRadius: {
        lfs: '0.25rem',
      },
      boxShadow: {
        card: '0 4px 24px rgba(0,0,0,0.10)',
        hover: '0 12px 40px rgba(0,0,0,0.18)',
        'green-glow': '0 4px 20px rgba(74,124,89,0.30)',
        'red-glow': '0 4px 20px rgba(192,57,43,0.25)',
        'orange-glow': '0 4px 20px rgba(224,123,57,0.25)',
      },
      transitionDuration: {
        150: '150ms',
        300: '300ms',
        600: '600ms',
      },
      animation: {
        'fade-up': 'fadeUp 0.8s ease forwards',
        'fade-in': 'fadeIn 1s ease forwards',
        'pulse-custom': 'pulse 2s ease infinite',
        'grid-move': 'gridMove 20s linear infinite',
      },
      keyframes: {
        fadeUp: {
          from: { opacity: '0', transform: 'translateY(30px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
        fadeIn: {
          from: { opacity: '0' },
          to: { opacity: '1' },
        },
        pulse: {
          '0%,100%': { opacity: '1', transform: 'scale(1)' },
          '50%': { opacity: '0.4', transform: 'scale(1.4)' },
        },
        gridMove: {
          '0%': { transform: 'translate(0,0)' },
          '100%': { transform: 'translate(50px,50px)' },
        },
      },
      backgroundImage: {
        'flag-gradient':
          'linear-gradient(to right, #198a4e 0%, #198a4e 25%, #c0392b 25%, #c0392b 50%, #0f0f0f 50%, #0f0f0f 75%, #e07b39 75%, #e07b39 100%)',
        'green-gradient': 'linear-gradient(135deg, #4a7c59, #7ecb93)',
        'dark-gradient': 'linear-gradient(135deg, #0f0f0f, #1e3a2a)',
        'grid-pattern':
          'linear-gradient(rgba(74,124,89,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(74,124,89,0.05) 1px, transparent 1px)',
      },
    },
  },
  plugins: [],
};
