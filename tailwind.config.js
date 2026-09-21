import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.vue",
    ],

    theme: {
        extend: {
            colors: {
                ink: "#1C1712",
                paper: "#FAF7F2",
                surface: "#FFFFFF",
                line: "#E8E1D6",
                accent: {
                    DEFAULT: "#B8863A",
                    dark: "#8A6224",
                },
                ok: "#2F6B4F",
                warn: "#A65A1E",
                danger: "#A13B2E",
            },
            fontFamily: {
                sans: ["Inter", ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
