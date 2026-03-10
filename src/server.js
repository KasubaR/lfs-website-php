/* ============================================================
   LFS — Lusaka Fitness Squad
   server.js — HTTP server entry point
   
   Usage:
     node server.js              (production)
     npm run dev                 (nodemon watch)
     NODE_ENV=production node server.js
   
   This file only starts the server.
   All Express config lives in app.js.
   ============================================================ */

'use strict';

const path = require('path');

/* ── Load .env from project root so it works when run from src/ or repo root ── */
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

const http = require('http');
const app  = require('./app');

/* ════════════════════════════════════════════════════════════
   CONFIGURATION
   ════════════════════════════════════════════════════════════ */
const PORT = normalisePort(process.env.PORT || '3000');
const HOST = process.env.HOST || '0.0.0.0';

app.set('port', PORT);

/* ════════════════════════════════════════════════════════════
   CREATE HTTP SERVER
   ════════════════════════════════════════════════════════════ */
const server = http.createServer(app);

/* ════════════════════════════════════════════════════════════
   LISTEN
   ════════════════════════════════════════════════════════════ */
server.listen(PORT, HOST);

server.on('listening', onListening);
server.on('error',     onError);

/* ════════════════════════════════════════════════════════════
   GRACEFUL SHUTDOWN
   Allows in-flight requests to complete before exiting.
   Triggered by Ctrl-C, Docker stop, PM2 reload, etc.
   ════════════════════════════════════════════════════════════ */
const SHUTDOWN_TIMEOUT_MS = 10_000; // 10 seconds

function gracefulShutdown(signal) {
  console.log(`\n[LFS] Received ${signal}. Shutting down gracefully…`);

  server.close((err) => {
    if (err) {
      console.error('[LFS] Error during shutdown:', err.message);
      process.exit(1);
    }
    console.log('[LFS] HTTP server closed. Goodbye! 🏃');
    process.exit(0);
  });

  // Force-kill if shutdown takes too long
  setTimeout(() => {
    console.error('[LFS] Shutdown timed out. Forcing exit.');
    process.exit(1);
  }, SHUTDOWN_TIMEOUT_MS).unref();
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT',  () => gracefulShutdown('SIGINT'));

/* ════════════════════════════════════════════════════════════
   UNHANDLED REJECTIONS & EXCEPTIONS
   ════════════════════════════════════════════════════════════ */
process.on('unhandledRejection', (reason, promise) => {
  console.error('[LFS] Unhandled Promise Rejection at:', promise);
  console.error('[LFS] Reason:', reason);
  // In production you may want to trigger gracefulShutdown here
});

process.on('uncaughtException', (err) => {
  console.error('[LFS] Uncaught Exception:', err.message);
  console.error(err.stack);
  gracefulShutdown('uncaughtException');
});

/* ════════════════════════════════════════════════════════════
   HELPERS
   ════════════════════════════════════════════════════════════ */

/**
 * Normalise a port value (string → number, named pipe, or false).
 * @param {string} val
 * @returns {number|string|false}
 */
function normalisePort(val) {
  const port = parseInt(val, 10);
  if (isNaN(port))  return val;    // named pipe
  if (port >= 0)    return port;   // valid port number
  return false;
}

/**
 * Event listener for server "error" event.
 * Provides human-friendly messages for common bind errors.
 */
function onError(error) {
  if (error.syscall !== 'listen') throw error;

  const bind = typeof PORT === 'string'
    ? `Pipe ${PORT}`
    : `Port ${PORT}`;

  switch (error.code) {
    case 'EACCES':
      console.error(`[LFS] ${bind} requires elevated privileges.`);
      process.exit(1);
      break;
    case 'EADDRINUSE':
      console.error(`[LFS] ${bind} is already in use.`);
      process.exit(1);
      break;
    default:
      throw error;
  }
}

/**
 * Event listener for server "listening" event.
 */
function onListening() {
  const addr = server.address();
  const bind = typeof addr === 'string'
    ? `pipe ${addr}`
    : `http://localhost:${addr.port}`;

  console.log('');
  console.log('  ██╗     ███████╗███████╗');
  console.log('  ██║     ██╔════╝██╔════╝');
  console.log('  ██║     █████╗  ███████╗');
  console.log('  ██║     ██╔══╝  ╚════██║');
  console.log('  ███████╗██║     ███████║');
  console.log('  ╚══════╝╚═╝     ╚══════╝');
  console.log('');
  console.log(`  Lusaka Fitness Squad — We're In This Together`);
  console.log(`  ─────────────────────────────────────────────`);
  console.log(`  🟢 Server running  → ${bind}`);
  console.log(`  🌍 Environment     → ${process.env.NODE_ENV || 'development'}`);
  console.log(`  📅 Started         → ${new Date().toLocaleString()}`);
  console.log('');
}
