import { existsSync, readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";

const requiredFiles = [
    "style.css",
    "functions.php",
    "theme.json",
    "dist/manifest.json",
];
for (const file of requiredFiles) {
    if (!existsSync(file))
        throw new Error(`Required release file is missing: ${file}`);
}

const style = readFileSync("style.css", "utf8");
const styleVersion = style.match(/^[\s*]*Version:\s*([^\s]+)/m)?.[1] ?? "";
const packageVersion = JSON.parse(readFileSync("package.json", "utf8")).version;
if (!/^\d+\.\d+\.\d+$/.test(styleVersion) || styleVersion !== packageVersion) {
    throw new Error(
        `Version mismatch: style.css=${styleVersion || "missing"} package.json=${packageVersion}`,
    );
}

let trackedFiles = [];
try {
    trackedFiles = execFileSync(process.env.GIT_BIN || "git", ["ls-files"], {
        encoding: "utf8",
    })
        .trim()
        .split("\n")
        .filter(Boolean);
} catch (error) {
    if (!process.env.GIT_BIN && existsSync("/usr/bin/git")) {
        trackedFiles = execFileSync("/usr/bin/git", ["ls-files"], {
            encoding: "utf8",
        })
            .trim()
            .split("\n")
            .filter(Boolean);
    } else {
        throw new Error(
            `Unable to inspect tracked release files: ${error.message}`,
        );
    }
}

const forbidden = [
    /(^|\/)node_modules\//,
    /(^|\/)\.env(?:\.|$)/,
    /(^|\/)\.DS_Store$/,
    /(^|\/)(?:id_rsa|id_ed25519)(?:\.|$)/,
    /(^|\/)(?:secrets?|credentials?)\.(?:json|ya?ml|txt)$/i,
];
const violations = trackedFiles.filter((file) =>
    forbidden.some((pattern) => pattern.test(file)),
);
if (violations.length > 0) {
    throw new Error(
        `Forbidden release files are tracked: ${violations.join(", ")}`,
    );
}

console.log(
    `Package contract valid for Lonestar ${styleVersion} (${trackedFiles.length} tracked files).`,
);
