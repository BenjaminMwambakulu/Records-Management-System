#!/usr/bin/env node
const { execSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const root = __dirname;
const failed = [];

function check(name) {
  try {
    execSync(`${name} --version`, { stdio: "ignore", shell: true });
    console.log(`[OK] ${name} found`);
    return true;
  } catch {
    console.error(`[FAIL] ${name} is not installed`);
    return false;
  }
}

function run(cmd, cwd) {
  console.log(`> ${cmd}`);
  execSync(cmd, { cwd, stdio: "inherit", shell: true });
}

function tryRun(cmd, cwd) {
  try {
    run(cmd, cwd);
    return true;
  } catch {
    console.log(`[WARN] "${cmd}" failed — skipping`);
    return false;
  }
}

function ensureEnv(dir) {
  const envPath = path.join(dir, ".env");
  const examplePath = path.join(dir, ".env.example");
  if (!fs.existsSync(envPath) && fs.existsSync(examplePath)) {
    fs.copyFileSync(examplePath, envPath);
    console.log(`[OK] .env created from .env.example`);
  } else if (fs.existsSync(envPath)) {
    console.log(`[OK] .env already exists`);
  } else {
    console.log(`[WARN] No .env.example found in ${dir}`);
  }
}

// ── Prerequisites ──
console.log("=== CSIT Society Records Management System Setup ===\n");
console.log("--- Checking prerequisites ---");

if (!check("node")) failed.push("node");
if (!check("npm")) failed.push("npm");
if (!check("php")) failed.push("php");
if (!check("composer")) failed.push("composer");

if (failed.length > 0) {
  console.error(`\nMissing required tools: ${failed.join(", ")}`);
  console.error("Install them and try again.");
  process.exit(1);
}

// ── Backend ──
console.log("\n--- Setting up backend ---");
const apiDir = path.join(root, "RecordsAPI");

if (!fs.existsSync(path.join(apiDir, "artisan"))) {
  console.error("[FAIL] RecordsAPI/artisan not found — is the backend in the right place?");
  process.exit(1);
}

ensureEnv(apiDir);
tryRun("composer install --no-interaction", apiDir);
tryRun("php artisan key:generate --force", apiDir);
tryRun("php artisan migrate --force", apiDir);
tryRun("php artisan db:seed --force", apiDir);

// ── Frontend ──
console.log("\n--- Setting up frontend ---");
const feDir = path.join(root, "RecordsFrontend");

if (!fs.existsSync(path.join(feDir, "package.json"))) {
  console.error("[FAIL] RecordsFrontend/package.json not found — is the frontend in the right place?");
  process.exit(1);
}

ensureEnv(feDir);
tryRun("npm install", feDir);

console.log("\n=== Setup complete ===\n");
console.log("Run both with:");
console.log("  Terminal 1:  cd RecordsAPI && composer dev");
console.log("  Terminal 2:  cd RecordsFrontend && npm run dev");
