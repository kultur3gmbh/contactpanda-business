=== ContactPanda Business ===
Contributors: kultur3gmbh
Tags: contactpanda, projects, corrections, kanban, customer-portal
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.8.3
License: GPLv2 or later

Independent business administration and project management for ContactPanda.

== Description ==

ContactPanda Business provides project and correction management with customer-visible and internal tasks, structured document imports, reports, and an authenticated MCP interface.

== Changelog ==

= 0.8.3 =
* Registers projects, tasks, reviewed import drafts, and report preparation with the WordPress Abilities API for secure MCP connectors.
* Keeps the existing authenticated Business MCP endpoint available for older WordPress installations and direct integrations.
* Ensures MCP document imports remain review drafts until an administrator explicitly confirms them in WordPress.

= 0.8.2 =
* Separates projects and tasks into dedicated administration views and adds recoverable project and task deletion, restoration, and task duplication.
* Adds project areas with colors and assignees plus persistent board filters for areas, individual users, and the current user.
* Adds staged CSV, Excel, Word, text, and MCP imports with duplicate matching, assignments, comments, updates, and explicit confirmation before live changes.
* Adds customer-visible print/PDF and editable Word reports with stable task references for reviewed return imports.
* Adds an authenticated Business MCP interface for reading projects and tasks, preparing import drafts, and preparing report links.
* Hides licensed Business menus without entitlement and replaces stale-form blank pages with a clear license message.
* Completes German and formal German translations for the new Business workflow.

= 0.8.1 =
* Replaces numeric person and company fields with a searchable multi-participant project picker for customer accounts, people, companies, agencies, and contact persons.
* Adds per-participant read, write, or management permissions and customer-portal enforcement.
* Adds project colors, editable project popups, clickable task cards, and clearer task-detail dialogs.
* Improves board drag and drop, reports failed moves, removes the redundant list view, and shows linked projects in Plus customer details.
* Migrates legacy project assignments repeatably without deleting existing Business data.

= 0.8.0 =
* Introduces ContactPanda Business as an independent product.
* Adds projects and a Trello-style correction board.
* Adds open sub-statuses, progress colors, due dates, comments, and history.
* Adds customer-visible versus internal tasks and customer task submission.
* Adds optional conversion from ContactPanda Pro support requests.
