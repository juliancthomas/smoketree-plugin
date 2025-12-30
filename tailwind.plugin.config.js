/** @type {import('tailwindcss').Config} */
module.exports = {
  prefix: 'st-',
  content: [
    './*.php',
    './**/*.php',
    './public/js/**/*.{js,jsx,ts,tsx}',
    '!./vendor/**',
    '!./node_modules/**',
  ],
  theme: {
    extend: {
      spacing: {
        '1': '0.25rem', // 1rem = 4
        '5': '1.25rem', // 5 = 1.25rem
        '7': '1.75rem', // 7 = 1.75rem
        '15': '3.75rem', // 15 = 3.75rem
        '18': '4.5rem', // 18 = 4.5rem
        '22': '5.5rem', // 22 = 5.5rem
        '30': '7.5rem', // 30 = 7.5rem
        '50': '12.5rem', // 50 = 12.5rem
        '100': '25rem', // 100 = 25rem
      },
      fontFamily: {
        roboto: ['Roboto', 'sans-serif'],
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
};

