// Paleta corporativa CRT (logo: #00AA90). Es redefineixen `blue` i `indigo`
// perquè les pàgines Inertia existents agafin el turquesa sense tocar les classes.
const crt = {
    50: '#E6F6F3',
    100: '#C2EBE3',
    200: '#8FDDD0',
    300: '#5CCDBB',
    400: '#2EBDA6',
    500: '#00AA90',
    600: '#00937D',
    700: '#00806C',
    800: '#006B5B',
    900: '#005E50',
    950: '#003A31',
};

export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './resources/**/*.ts',
    ],
    theme: {
        extend: {
            colors: {
                crt,
                blue: crt,
                indigo: crt,
            },
        },
    },
    plugins: [],
};
