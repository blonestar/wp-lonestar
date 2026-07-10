import { readdirSync, readFileSync } from "node:fs";
import { join, relative } from "node:path";

const root = process.cwd();
const skipped = new Set([".git", "node_modules"]);
const jsonFiles = [];

function walk(directory) {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        if (entry.isDirectory() && skipped.has(entry.name)) continue;
        const absolutePath = join(directory, entry.name);
        if (entry.isDirectory()) {
            walk(absolutePath);
        } else if (entry.isFile() && entry.name.endsWith(".json")) {
            jsonFiles.push(absolutePath);
        }
    }
}

walk(root);
for (const file of jsonFiles) {
    try {
        JSON.parse(readFileSync(file, "utf8"));
    } catch (error) {
        throw new Error(`Invalid JSON: ${relative(root, file)}: ${error.message}`);
    }
}

console.log(`Validated ${jsonFiles.length} JSON files.`);
