// Version: 1.0.0
// View your website on your local WordPress domain
// for example http://evblog.local
//
// http://localhost:3000 serves Vite in development mode.
// Accessing it directly is expected to be empty because WordPress loads assets from it.

import { defineConfig } from "vite";
import { resolve, dirname } from "path";
import { fileURLToPath } from "url";
import { lonestarGetBlockEntryPoints } from "./vite-entry-points.mjs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default defineConfig(({ command }) => ({
    plugins: [],

    // config
    root: "",
    base: command === "serve" ? "/" : "/dist/",

    build: {
        // output dir for production build
        outDir: resolve(__dirname, "./dist"),
        emptyOutDir: true,

        // emit manifest so PHP can find the hashed files
        manifest: "manifest.json",

        // esbuild target
        target: "es2018",

        cssCodeSplit: true,

        // bundle entries discovered from block directories + main.js
        rollupOptions: {
            treeshake: false,
            input: lonestarGetBlockEntryPoints(__dirname),

            output: {
                entryFileNames: "[name].js",
                chunkFileNames: "[name].js",
                assetFileNames: "[name].[ext]",
            },
        },

        // minifying switch
        minify: true,
        write: true,
    },

    server: {
        // required to load scripts from custom host
        cors: true,

        // we need a strict port to match on PHP side
        // change freely, but update in your inc/core/vite.php to match the same port
        strictPort: true,
        port: 3000,

        // serve over http
        https: false,

        hmr: {
            host: "localhost",
        },
        watch: {
            ignored: ["**/node_modules/**"], // ignore node_modules
        },
    },
}));
