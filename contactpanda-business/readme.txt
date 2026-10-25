=== ContactPanda Business ===
Contributors: kultur3gmbh
Tags: contactpanda, projects, corrections, kanban, customer-portal
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.8.11
License: GPLv2 or later

Independent business administration and project management for ContactPanda.

== Description ==

ContactPanda Business provides project and correction management with customer-visible and internal tasks, structured document imports, reports, and an authenticated MCP interface.

== Changelog ==

= 0.8.11 =
* Reorganizes project details into compact editable overview fields and six clear project-information blocks with financing across the full width.
* Adds directly editable project dates, status, progress, budget, description, financing notes, and general notes.
* Produces a landscape tabular PDF/print report with a dedicated customer-comments column.
* Repairs late Business MCP ability registration and discovery while keeping license and capability checks on every operation.
* Aligns Business with the refreshed ContactPanda 0.8.11 update contract.

= 0.8.10 =
* Aligns Business with the verified ContactPanda Basic 0.8.10 release archive and update metadata.
* Retains every individually licensed Business module as valid access to Business maintenance releases.

= 0.8.9 =
* Uses the corrected package-update authorization supplied by ContactPanda Basic 0.8.9.
* Recognizes every individually licensed Business module as valid access to Business maintenance releases.

= 0.8.8 =
* Coordinates ContactPanda Business with the shared 0.8.8 product-family release.
* Preserves the project workspace, reviewed imports, reports, MCP interface, and current database schema.

= 0.8.7 =
* Renames the project administration to Projects and adds separate General information and Tasks and corrections tabs.
* Adds project dates, budget and financing notes, project parts, milestones, project documents, and a structured payment plan.
* Makes project and task text directly clickable for editing and keeps inline task creation collapsed until requested.
* Restricts direct task creation to Open and In progress and moves smaller duplicate/trash controls into the card footer.
* Improves board spacing and centers the add-file icon.

= 0.8.6 =
* Introduces a compact Trello-inspired task dialog with project data, description, comments, activity, and task editing in one workspace.
* Adds multiple file attachments, task checklists with progress, and direct task creation inside every board column.
* Moves task status and compact duplicate/trash controls to the card edge to use board space more efficiently.
* Records completed imports with date, time, and run count and requires an explicit warning confirmation before applying one again.

= 0.8.5 =
* Adds selection-aware bulk editing to reviewed imports for project, area, assignee, priority, due date, visibility, and import action.
* Keeps individual row values unchanged unless the administrator explicitly selects a bulk value.
* Adds select-all and a live selected-task count without bypassing the final import confirmation.

= 0.8.4 =
* Registers Business abilities before secure MCP plugins initialize the WordPress Abilities registry.
* Keeps licensed Business tools hidden when the project-management entitlement is unavailable.

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
