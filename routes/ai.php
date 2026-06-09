<?php

use App\Mcp\ThePisteServer;
use Laravel\Mcp\Facades\Mcp;

// Remote MCP server for Claude Desktop (and any MCP client).
// Authenticated with a Sanctum personal access token (Authorization: Bearer).
Mcp::web('/mcp', ThePisteServer::class)
    ->middleware(['throttle:60,1', 'auth:sanctum']);
