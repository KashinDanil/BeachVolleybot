## Overview

This project was created to address a common frustration: _manually copying participant lists, adding names, and reposting them in group chats_. This workflow is inconvenient and error-prone, especially when **multiple people attempt to join simultaneously**, which can lead to **concurrency issues**.

## Solution

**BeachVolleyBot** simplifies this process by allowing participants to **join a game with a single button click**, eliminating the need for manual list management in group chats.

## Technical Approach

To make the project more technically interesting and educational, it was intentionally built **without external dependencies**, such as:

- Databases
- Caching services
- Queue services
- etc.

Despite this constraint, the system remains **reliable and concurrency-aware**, demonstrating how core functionality can be implemented with minimal infrastructure.

## Architecture

The architecture is designed with **future scalability in mind**. While the current implementation avoids external infrastructure, it can be **easily migrated to a traditional stack** if needed.