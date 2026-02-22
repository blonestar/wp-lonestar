// Version: 1.0.0
import { resolve, basename, relative } from "path";
import { existsSync, readFileSync } from "fs";
import { lonestarGetBlockRoots, lonestarFindBlockDirectories } from "./vite-block-discovery.mjs";

/**
 * Build an entry key from a path.
 *
 * @param {string} themeRoot - The root directory of the theme.
 * @param {string} absolutePath - The absolute path to the block.
 * @returns {string} - The entry key.
 */
function lonestarBuildEntryKeyFromPath(themeRoot, absolutePath) {
    const relativePath = relative(themeRoot, absolutePath).replace(/\\/g, "/");
    return relativePath
        .replace(/\.[^/.]+$/, "")
        .replace(/[^a-zA-Z0-9/_-]/g, "")
        .replace(/[\\/]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "")
        .toLowerCase();
}

/**
 * Check if a block uses file assets.
 *
 * @param {string} blockDirectory - The directory of the block.
 * @returns {boolean} - True if the block uses file assets, false otherwise.
 */
function lonestarBlockUsesFileAssets(blockDirectory) {
    const blockSlug = basename(blockDirectory);
    const metadataCandidates = [resolve(blockDirectory, "block.json"), resolve(blockDirectory, blockSlug + ".block.json")];
    const metadataPath = metadataCandidates.find((candidate) => existsSync(candidate));

    if (!metadataPath) {
        return false;
    }

    try {
        const metadata = JSON.parse(readFileSync(metadataPath, "utf8"));
        if (!metadata || typeof metadata !== "object") {
            return false;
        }

        const fields = ["script", "editorScript", "viewScript", "viewScriptModule", "style", "editorStyle", "viewStyle"];

        for (const field of fields) {
            if (!(field in metadata)) continue;
            const value = metadata[field];
            const values = Array.isArray(value) ? value : [value];
            if (values.some((item) => typeof item === "string" && item.startsWith("file:"))) {
                return true;
            }
        }
    } catch (error) {
        // Ignore invalid metadata here; PHP validation handles runtime errors.
    }

    return false;
}

/**
 * Get block entry points for the theme.
 *
 * @param {string} themeRoot - The root directory of the theme.
 * @returns {object} - An object with the entry points.
 */
export function lonestarGetBlockEntryPoints(themeRoot) {
    const entryPoints = {};
    entryPoints.main = resolve(themeRoot, "main.js");

    const blockRoots = lonestarGetBlockRoots(themeRoot);
    const usedEntryKeys = new Set(["main"]);

    blockRoots.forEach((blockRoot) => {
        const blockDirectories = lonestarFindBlockDirectories(blockRoot);
        blockDirectories.forEach((blockDirectory) => {
            if (lonestarBlockUsesFileAssets(blockDirectory)) {
                // create-block style file: assets are built by @wordpress/scripts
                return;
            }

            const blockSlug = basename(blockDirectory);
            const blockEntryBase = lonestarBuildEntryKeyFromPath(themeRoot, blockDirectory);
            const cssCandidates = [resolve(blockDirectory, blockSlug + ".css"), resolve(blockDirectory, "style.css")];
            const jsCandidates = [resolve(blockDirectory, blockSlug + ".js"), resolve(blockDirectory, "index.js")];

            const cssEntryKey = blockEntryBase + "-css";
            const cssFilePath = cssCandidates.find((filePath) => existsSync(filePath));
            if (cssFilePath && !usedEntryKeys.has(cssEntryKey)) {
                entryPoints[cssEntryKey] = cssFilePath;
                usedEntryKeys.add(cssEntryKey);
            }

            const jsEntryKey = blockEntryBase + "-js";
            const jsFilePath = jsCandidates.find((filePath) => existsSync(filePath));
            if (jsFilePath && !usedEntryKeys.has(jsEntryKey)) {
                entryPoints[jsEntryKey] = jsFilePath;
                usedEntryKeys.add(jsEntryKey);
            }
        });
    });

    return entryPoints;
}
