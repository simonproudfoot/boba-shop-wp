const defaultTheme = require("tailwindcss/defaultTheme");
const colors = require('tailwindcss/colors');
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: ['class', '[data-mode="black"]'],
  mode: 'jit',

  content: [
    "./develop/**/*.{php,svg}",
    "./develop/js/**/*.js",
    "./develop/js/site/**/*.js",
    "./develop/template-parts/**/*.php",
    "./wp-content/themes/boba_shop/**/*.php",
    "./*.php"
  ],

  safelist: [
    'lg:w-5/12',
    'lg:w-6/12',
    'lg:w-7/12',
    'lg:w-full'
  ],
  theme: {

    extend: {
      screens: {
        'sm': '640px',
        'md': '768px',
        'lg': '1024px',
        'xl': '1280px',
        '2xl': '1512px',
      },
      colors: {
        yellow: "#fbd355",
        pink: "#ec608d",
        yellowDark: "#c1633a",
        pinkDark: "#CC527E",
        cream: '#FFFFE0',
        black: '#393939',
        white: '#ffffff',

      },
      fontFamily: {
        heading: ["DynaPuff", "system-ui"],
        subheading: ["DynaPuff", "system-ui"],
        body: ["PT Sans", "sans-serif"]
      },
      fontWeight: {
        'dynapuff-400': '400',
        'dynapuff-500': '500',
        'dynapuff-600': '600',
        'dynapuff-700': '700',
      },
      aspectRatio: {
        "9/12": "9 / 12",
        "portait": "9 / 12",
      },
      lineHeight: {
        'heading': '1',
      },
      padding: {
        'h-mob': '84px',
        'h-md': '113px',
        'h-lg': '137px',
      }
    },

    container: {
      padding: {
        DEFAULT: '40px',
        md: '60px',
        xl: '100px'
      },
      center: true
    }

  },
  plugins: [
    require("@tailwindcss/forms"),
    require("@tailwindcss/line-clamp"),
    require('@tailwindcss/typography'),

    ({ matchUtilities, theme /* … */ }) => {
      // …
      matchUtilities(
        // https://gist.github.com/olets/9b833a33d01384eed1e9f1e106003a3b
        {
          aspect: (value) => ({
            "@supports (aspect-ratio: 1 / 1)": {
              aspectRatio: value,
            },
            "@supports not (aspect-ratio: 1 / 1)": {
              // https://github.com/takamoso/postcss-aspect-ratio-polyfill

              "&::before": {
                content: '""',
                float: "left",
                paddingTop: `calc(100% / (${value}))`,
              },
              "&::after": {
                clear: "left",
                content: '""',
                display: "block",
              },
            },
          }),
        },
        { values: theme("aspectRatio") }
      );
    },
  ],
};



