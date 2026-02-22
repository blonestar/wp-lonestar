// Version: 1.0.0
import { resolve } from "path";
import { existsSync, readdirSync } from "fs";

const LONESTAR_IGNORED_DIRECTORIES = new Set(["node_modules", "dist", "build", "vendor"]);

/**
 * Get block roots for the theme.
 *
 * @param {string} themeRoot - The root directory of the theme.
 * @returns {string[]} - An array of block roots.
 */
export function lonestarGetBlockRoots(themeRoot) {
    const roots = [resolve(themeRoot, "blocks/acf"), resolve(themeRoot, "blocks/native")];
    const modulesRoot = resolve(themeRoot, "modules");

    if (!existsSync(modulesRoot)) {
        return roots;
    }

    const moduleEntries = readdirSync(modulesRoot, { withFileTypes: true });
    moduleEntries.forEach((entry) => {
        if (!entry.isDirectory()) return;
        if (entry.name.startsWith(".")) return;

        const moduleRoot = resolve(modulesRoot, entry.name);
        const moduleBlockRoots = [resolve(moduleRoot, "blocks/acf"), resolve(moduleRoot, "blocks/native")];

        moduleBlockRoots.forEach((blockRoot) => {
            if (!existsSync(blockRoot)) return;
            roots.push(blockRoot);
        });
    });

    return Array.from(new Set(roots));
}

/**
 * Find block directories in the theme.
 *
 * @param {string} rootDirectory - The root directory of the theme.
 * @returns {string[]} - An array of block directories.
 */
export function lonestarFindBlockDirectories(rootDirectory) {
    const directories = [];

    if (!existsSync(rootDirectory)) {
        return directories;
    }

    const walk = (currentDirectory) => {
        const entries = readdirSync(currentDirectory, { withFileTypes: true });
        const hasMetadata = entries.some((entry) => {
            if (!entry.isFile()) return false;
            return entry.name === "block.json" || entry.name.endsWith(".block.json");
        });

        if (hasMetadata) {
            directories.push(currentDirectory);
        }

        entries.forEach((entry) => {
            if (!entry.isDirectory()) return;
            if (entry.name.startsWith(".")) return;
            if (LONESTAR_IGNORED_DIRECTORIES.has(entry.name)) return;
            walk(resolve(currentDirectory, entry.name));
        });
    };

    walk(rootDirectory);

    return directories;
}
