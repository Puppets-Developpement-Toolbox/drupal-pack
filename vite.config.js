import { defineConfig } from "vite";
import liveReload from "vite-plugin-live-reload";

export default function pptsViteConfig({ theme, root, port }) {
  return defineConfig(({ mode }) => {
    const config = {
      plugins: [liveReload(`${root}/web/themes/${theme}/**/*`)],
      root: `${root}/web${mode !== "development" ? `/themes/${theme}` : ""}`,
      base: mode !== "development" ? `/themes/${theme}/dist/` : "",
      build: {
        manifest: true,
        sourcemap: mode == "development",
        outDir: `${root}/web/themes/${theme}/dist`,
        rollupOptions: {
          input: {
            main: `${root}/web/themes/${theme}/js/main.js`,
            global: `${root}/web/themes/${theme}/css/global.css`,
            print: `${root}/web/themes/${theme}/css/print.css`,
          },
        },
        target: "es2018",
        write: true,
      },
      server: {
        port: port || 80,
        host: true,
      },
      css: {
        devSourcemap: mode == "development",
      },
    };

    return config;
  });
}
