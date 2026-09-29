/*
 * Paleta e tipografia da BS Agro Capital para o Tailwind carregado via CDN.
 *
 * Este arquivo precisa ser incluído DEPOIS do script do Tailwind e ANTES do
 * fechamento do <head>, porque o CDN lê `tailwind.config` no momento em que
 * varre o documento:
 *
 *   <script src="https://cdn.tailwindcss.com"></script>
 *   <script src="assets/tailwind-config.js"></script>
 *
 * As mesmas cores estão espelhadas como variáveis CSS em assets/styles.css,
 * para que o CSS customizado use os tokens sem depender do Tailwind. Ao mudar
 * uma cor aqui, mudar também lá.
 */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        /* Verde-floresta, cor de fundo da logo */
        olive: {
          950: "#08201a",
          900: "#0d3527",
          800: "#124a35",
          700: "#1a6247",
          600: "#237a58",
          500: "#2f9470",
          400: "#55ac8d",
          300: "#8ecab0",
          200: "#c3e3d3",
          100: "#e2f2ea",
          50: "#f1f9f5",
        },
        /* Dourado metálico, cor do logotipo */
        gold: {
          900: "#5c4009",
          800: "#7a5710",
          700: "#9c7418",
          600: "#b8860b",
          500: "#d4af37",
          400: "#e3c667",
          300: "#edd68f",
          200: "#f5e6b8",
          100: "#faf0d8",
          50: "#fdf8ec",
        },
        /* Grafite / azul-marinho, texto e contraste */
        graphite: {
          950: "#10131a",
          900: "#171b24",
          800: "#232833",
          700: "#333a47",
          600: "#4b5262",
          500: "#6b7180",
          400: "#9298a3",
          300: "#bcc0c7",
          200: "#dcdee2",
          100: "#eceef0",
        },
        /* Papel de fundo: neutro quente, não branco puro */
        paper: {
          DEFAULT: "#fbf9f4",
          dim: "#f5f1e8",
        },
      },
      fontFamily: {
        /* Raleway nos títulos e elementos de voz forte; Roboto no texto. */
        display: ['"Raleway"', "ui-sans-serif", "system-ui", "sans-serif"],
        sans: ['"Roboto"', "ui-sans-serif", "system-ui", "sans-serif"],
      },
      boxShadow: {
        soft: "0 20px 45px -20px rgb(23 27 36 / 0.25)",
      },
    },
  },
};
